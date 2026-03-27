<?php

use Mj\Member\Classes\MjBackupProfile;
use Mj\Member\Classes\MjDatabaseBackup;
use Mj\Member\Classes\MjManualActionLog;

if (!defined('ABSPATH')) {
    exit;
}

/* ------------------------------------------------------------------
 * Register the 'weekly' cron schedule (not built-in to WordPress)
 * ----------------------------------------------------------------*/
add_filter('cron_schedules', 'mj_member_backup_add_cron_schedules');
function mj_member_backup_add_cron_schedules(array $schedules): array
{
    if (!isset($schedules['weekly'])) {
        $schedules['weekly'] = [
            'interval' => WEEK_IN_SECONDS,
            'display'  => __('Une fois par semaine', 'mj-member'),
        ];
    }
    return $schedules;
}

/* ------------------------------------------------------------------
 * Schedule / unschedule the cron job on every 'init'
 * ----------------------------------------------------------------*/
add_action('init', 'mj_member_backup_maybe_schedule');
function mj_member_backup_maybe_schedule(): void
{
    $enabled = get_option('mj_backup_enabled', '0') === '1';

    if (!$enabled) {
        if (wp_next_scheduled('mj_member_run_backup')) {
            wp_clear_scheduled_hook('mj_member_run_backup');
        }
        return;
    }

    $frequency = (string) get_option('mj_backup_frequency', 'daily');
    if (!in_array($frequency, ['daily', 'weekly', 'twicedaily'], true)) {
        $frequency = 'daily';
    }

    $scheduled = wp_next_scheduled('mj_member_run_backup');

    // If the frequency changed, reschedule
    if ($scheduled) {
        $event = wp_get_scheduled_event('mj_member_run_backup');
        if ($event && isset($event->schedule) && $event->schedule !== $frequency) {
            wp_clear_scheduled_hook('mj_member_run_backup');
            $scheduled = false;
        }
    }

    if (!$scheduled) {
        wp_schedule_event(time() + HOUR_IN_SECONDS, $frequency, 'mj_member_run_backup');
    }
}

/* ------------------------------------------------------------------
 * Schedule / unschedule DB backup profiles (single events with custom slots)
 * ----------------------------------------------------------------*/
add_action('init', 'mj_member_backup_sync_profile_schedules', 20);
function mj_member_backup_sync_profile_schedules(): void
{
    if (!class_exists(MjBackupProfile::class)) {
        return;
    }

    $profiles = MjBackupProfile::getAll();

    foreach ($profiles as $profile) {
        if (!$profile instanceof MjBackupProfile) {
            continue;
        }

        $hook = $profile->getCronHook();
        $args = array($profile->id);

        if (!$profile->enabled) {
            wp_clear_scheduled_hook($hook, $args);
            continue;
        }

        $nextTs = wp_next_scheduled($hook, $args);
        $desiredTs = mj_member_backup_next_profile_timestamp($profile);

        if (!$nextTs || abs((int) $nextTs - (int) $desiredTs) > 300) {
            wp_clear_scheduled_hook($hook, $args);
            wp_schedule_single_event($desiredTs, $hook, $args);
        }
    }
}

/**
 * Compute next run timestamp for a profile in site timezone.
 */
function mj_member_backup_next_profile_timestamp(MjBackupProfile $profile): int
{
    $tz = wp_timezone();
    $now = new DateTimeImmutable('now', $tz);

    $hourA = min(23, max(0, (int) $profile->dailyHour));
    $hourB = min(23, max(0, (int) $profile->twiceDailySecondHour));
    $weeklyDay = min(6, max(0, (int) $profile->weeklyDay));
    $weeklyHour = min(23, max(0, (int) $profile->weeklyHour));

    if ($profile->frequency === 'twicedaily') {
        $slots = array_values(array_unique(array($hourA, $hourB)));
        sort($slots);

        $candidates = array();
        foreach ($slots as $slotHour) {
            $candidate = $now->setTime($slotHour, 0, 0);
            if ($candidate <= $now) {
                $candidate = $candidate->modify('+1 day');
            }
            $candidates[] = $candidate;
        }

        usort($candidates, static fn(DateTimeImmutable $a, DateTimeImmutable $b) => $a <=> $b);
        return $candidates[0]->getTimestamp();
    }

    if ($profile->frequency === 'weekly') {
        $currentDow = (int) $now->format('w');
        $deltaDays = ($weeklyDay - $currentDow + 7) % 7;
        $candidate = $now->modify('+' . $deltaDays . ' day')->setTime($weeklyHour, 0, 0);
        if ($candidate <= $now) {
            $candidate = $candidate->modify('+7 days');
        }
        return $candidate->getTimestamp();
    }

    // daily (default)
    $candidate = $now->setTime($hourA, 0, 0);
    if ($candidate <= $now) {
        $candidate = $candidate->modify('+1 day');
    }
    return $candidate->getTimestamp();
}

/* ------------------------------------------------------------------
 * Cron event handler for DB backup profiles
 * ----------------------------------------------------------------*/
add_action('mj_backup_run_profile', 'mj_member_handle_scheduled_profile_backup', 10, 1);
function mj_member_handle_scheduled_profile_backup(string $profileId): void
{
    if (!class_exists(MjBackupProfile::class) || !class_exists(MjDatabaseBackup::class)) {
        return;
    }

    $profile = MjBackupProfile::getById($profileId);
    if (!$profile || !$profile->enabled) {
        wp_clear_scheduled_hook('mj_backup_run_profile', array($profileId));
        return;
    }

    $result = MjDatabaseBackup::runProfile($profile);

    if (class_exists(MjManualActionLog::class)) {
        if (is_wp_error($result)) {
            MjManualActionLog::add(
                'db_backup_profile_cron',
                false,
                $result->get_error_message(),
                array('source' => 'wp_cron', 'profile_id' => $profileId, 'profile_name' => $profile->name)
            );
        } else {
            $st = $profile->getLastStatus();
            MjManualActionLog::add(
                'db_backup_profile_cron',
                true,
                (string) ($st['filename'] ?? 'Sauvegarde profil exécutée'),
                array('source' => 'wp_cron', 'profile_id' => $profileId, 'profile_name' => $profile->name)
            );
        }
    }

    // Ensure the next single event is queued.
    $hook = $profile->getCronHook();
    $args = array($profile->id);
    if (!wp_next_scheduled($hook, $args)) {
        wp_schedule_single_event(mj_member_backup_next_profile_timestamp($profile), $hook, $args);
    }
}

/* ------------------------------------------------------------------
 * Cron event handler
 * ----------------------------------------------------------------*/
add_action('mj_member_run_backup', 'mj_member_handle_scheduled_backup');
function mj_member_handle_scheduled_backup(): void
{
    if (get_option('mj_backup_enabled', '0') !== '1') {
        return;
    }

    if (!class_exists(MjDatabaseBackup::class)) {
        return;
    }

    $result = MjDatabaseBackup::run();

    if (!class_exists(MjManualActionLog::class)) {
        return;
    }

    if (is_wp_error($result)) {
        MjManualActionLog::add(
            'db_backup_cron',
            false,
            $result->get_error_message(),
            array('source' => 'wp_cron')
        );
        return;
    }

    $status = MjDatabaseBackup::getLastStatus();
    $filename = isset($status['filename']) ? (string) $status['filename'] : '';
    $message = $filename !== ''
        ? ('Sauvegarde cron exécutée : ' . $filename)
        : ((string) ($status['message'] ?? 'Sauvegarde cron exécutée'));

    MjManualActionLog::add(
        'db_backup_cron',
        true,
        $message,
        array('source' => 'wp_cron')
    );
}

/* ------------------------------------------------------------------
 * AJAX: manual backup trigger (admin only)
 * ----------------------------------------------------------------*/
add_action('wp_ajax_mj_member_run_backup_now', 'mj_member_ajax_run_backup_now');
function mj_member_ajax_run_backup_now(): void
{
    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('Permissions insuffisantes', 'mj-member'));
    }

    if (!check_ajax_referer('mj_member_backup_nonce', '_wpnonce', false)) {
        wp_send_json_error(__('Nonce invalide', 'mj-member'));
    }

    if (!class_exists(MjDatabaseBackup::class)) {
        wp_send_json_error('Classe MjDatabaseBackup introuvable.');
    }

    $result = MjDatabaseBackup::run();

    if (is_wp_error($result)) {
        wp_send_json_error($result->get_error_message());
    }

    $status = MjDatabaseBackup::getLastStatus();
    wp_send_json_success([
        'message'  => sprintf('Sauvegarde créée : %s', $status['filename'] ?? ''),
        'filename' => $status['filename'] ?? '',
        'last_run' => MjDatabaseBackup::getLastRun(),
    ]);
}
