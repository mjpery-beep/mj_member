<?php

use Mj\Member\Classes\MjDatabaseBackup;

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

    MjDatabaseBackup::run();
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
