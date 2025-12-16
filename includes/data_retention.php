<?php

use Mj\Member\Classes\Crud\MjMembers;
use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('mj_member_schedule_data_retention_event')) {
    function mj_member_schedule_data_retention_event(): void
    {
        if (!function_exists('wp_next_scheduled') || !function_exists('wp_schedule_event')) {
            return;
        }

        if (wp_next_scheduled('mj_member_purge_expired_members')) {
            return;
        }

        $first_run = apply_filters('mj_member_data_retention_first_run', time() + DAY_IN_SECONDS);
        $first_run = is_int($first_run) && $first_run > 0 ? $first_run : (time() + DAY_IN_SECONDS);

        wp_schedule_event($first_run, 'daily', 'mj_member_purge_expired_members');
    }

    add_action('init', 'mj_member_schedule_data_retention_event');
}

if (!function_exists('mj_member_clear_data_retention_schedule')) {
    function mj_member_clear_data_retention_schedule(): void
    {
        if (!function_exists('wp_next_scheduled') || !function_exists('wp_unschedule_event')) {
            return;
        }

        $timestamp = wp_next_scheduled('mj_member_purge_expired_members');
        while ($timestamp) {
            wp_unschedule_event($timestamp, 'mj_member_purge_expired_members');
            $timestamp = wp_next_scheduled('mj_member_purge_expired_members');
        }
    }
}

if (!function_exists('mj_member_handle_data_retention')) {
    function mj_member_handle_data_retention(): void
    {
        if (!function_exists('current_time')) {
            return;
        }

        $retention_days = (int) apply_filters('mj_member_data_retention_days', Config::dataRetentionDays());
        if ($retention_days <= 0) {
            do_action('mj_member_data_retention_disabled');
            return;
        }

        $batch_size = (int) apply_filters('mj_member_data_retention_batch_size', 50);
        if ($batch_size <= 0) {
            $batch_size = 50;
        }

        $threshold_timestamp = time() - ($retention_days * DAY_IN_SECONDS);
        $threshold_mysql = gmdate('Y-m-d H:i:s', $threshold_timestamp);

        $candidate_ids = mj_member_get_data_retention_candidates($threshold_mysql, $batch_size);
        if (empty($candidate_ids)) {
            return;
        }

        foreach ($candidate_ids as $member_id) {
            $member = MjMembers::getById($member_id);
            if (!$member) {
                continue;
            }

            $should_anonymize = apply_filters('mj_member_should_anonymize_member', true, $member, $threshold_mysql);
            if (!$should_anonymize) {
                continue;
            }

            $result = MjMembers::anonymizePersonalData($member_id);
            if (is_wp_error($result)) {
                do_action('mj_member_data_retention_error', $result, $member);
            } else {
                do_action('mj_member_data_retention_success', $member_id, $member);
            }
        }
    }

    add_action('mj_member_purge_expired_members', 'mj_member_handle_data_retention');
}

if (!function_exists('mj_member_get_data_retention_candidates')) {
    /**
     * @return int[]
     */
    function mj_member_get_data_retention_candidates(string $threshold_mysql, int $limit): array
    {
        global $wpdb;

        if (!isset($wpdb)) {
            return array();
        }

        $table = $wpdb->prefix . 'mj_members';
        $limit = max(1, $limit);
        $null_datetime = '0000-00-00 00:00:00';

        $sql = $wpdb->prepare(
            "SELECT id FROM {$table}
            WHERE status = %s
              AND ((date_last_payement IS NOT NULL AND date_last_payement <> %s AND date_last_payement <= %s)
                OR ((date_last_payement IS NULL OR date_last_payement = %s) AND date_inscription <= %s))
              AND (anonymized_at IS NULL OR anonymized_at = %s)
            ORDER BY id ASC
            LIMIT %d",
            MjMembers::STATUS_INACTIVE,
            $null_datetime,
            $threshold_mysql,
            $null_datetime,
            $threshold_mysql,
            $null_datetime,
            $limit
        );

        $sql = apply_filters('mj_member_data_retention_query', $sql, $threshold_mysql, $limit);

        $results = $wpdb->get_col($sql);
        if (empty($results)) {
            return array();
        }

        return array_map('intval', $results);
    }
}
