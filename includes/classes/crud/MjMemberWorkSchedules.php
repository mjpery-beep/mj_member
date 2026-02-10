<?php

namespace Mj\Member\Classes\Crud;

use Mj\Member\Classes\MjTools;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * CRUD class for member work schedules.
 *
 * Allows managing multiple work schedule periods per member with start/end dates.
 * Dates cannot overlap for the same member.
 */
class MjMemberWorkSchedules extends MjTools
{
    /**
     * Get the table name for work schedules.
     */
    public static function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'mj_member_work_schedules';
    }

    /**
     * Get all work schedules for a member.
     *
     * @param int $member_id Member ID
     * @return array Array of schedule objects
     */
    public static function get_for_member(int $member_id): array
    {
        global $wpdb;
        $table = self::table();

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE member_id = %d ORDER BY start_date DESC",
            $member_id
        ));

        return is_array($results) ? $results : [];
    }

    /**
     * Get a single work schedule by ID.
     *
     * @param int $id Schedule ID
     * @return object|null
     */
    public static function get(int $id): ?object
    {
        global $wpdb;
        $table = self::table();

        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $id
        ));

        return $result ?: null;
    }

    /**
     * Get the currently active work schedule for a member.
     *
     * @param int $member_id Member ID
     * @param string|null $reference_date Reference date (Y-m-d), defaults to today
     * @return object|null
     */
    public static function get_active_for_member(int $member_id, ?string $reference_date = null): ?object
    {
        global $wpdb;
        $table = self::table();
        $date = $reference_date ?: current_time('Y-m-d');

        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE member_id = %d
               AND start_date <= %s
               AND (end_date IS NULL OR end_date >= %s)
             ORDER BY start_date DESC
             LIMIT 1",
            $member_id,
            $date,
            $date
        ));

        return $result ?: null;
    }

    /**
     * Check if dates overlap with existing schedules for a member.
     *
     * @param int $member_id Member ID
     * @param string $start_date Start date (Y-m-d)
     * @param string|null $end_date End date (Y-m-d) or null for ongoing
     * @param int|null $exclude_id Schedule ID to exclude from check (for updates)
     * @return bool True if overlap exists
     */
    public static function has_overlap(int $member_id, string $start_date, ?string $end_date, ?int $exclude_id = null): bool
    {
        global $wpdb;
        $table = self::table();

        // Build overlap condition:
        // Two ranges [A_start, A_end] and [B_start, B_end] overlap if:
        // A_start <= B_end AND A_end >= B_start
        // When end is NULL, treat it as infinity (always overlaps with future dates)

        $exclude_clause = $exclude_id ? $wpdb->prepare(" AND id != %d", $exclude_id) : '';

        if ($end_date === null) {
            // New schedule is ongoing (no end date)
            // Overlaps with any existing schedule that hasn't ended before start_date
            $sql = $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table}
                 WHERE member_id = %d
                   AND (end_date IS NULL OR end_date >= %s)
                   {$exclude_clause}",
                $member_id,
                $start_date
            );
        } else {
            // New schedule has an end date
            $sql = $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table}
                 WHERE member_id = %d
                   AND start_date <= %s
                   AND (end_date IS NULL OR end_date >= %s)
                   {$exclude_clause}",
                $member_id,
                $end_date,
                $start_date
            );
        }

        $count = (int) $wpdb->get_var($sql);
        return $count > 0;
    }

    /**
     * Create a new work schedule.
     *
     * @param array $data Schedule data
     * @return int|false Insert ID or false on failure
     */
    public static function create(array $data)
    {
        global $wpdb;
        $table = self::table();

        $member_id = isset($data['member_id']) ? (int) $data['member_id'] : 0;
        $start_date = isset($data['start_date']) ? sanitize_text_field($data['start_date']) : '';
        $end_date = isset($data['end_date']) && $data['end_date'] ? sanitize_text_field($data['end_date']) : null;
        $schedule = isset($data['schedule']) ? $data['schedule'] : '[]';

        if (!$member_id || !$start_date) {
            return false;
        }

        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) {
            return false;
        }

        if ($end_date !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
            return false;
        }

        // Check for overlapping schedules
        if (self::has_overlap($member_id, $start_date, $end_date)) {
            return false;
        }

        // Ensure schedule is a valid JSON string
        if (is_array($schedule)) {
            $schedule = wp_json_encode($schedule);
        }

        $result = $wpdb->insert(
            $table,
            [
                'member_id' => $member_id,
                'start_date' => $start_date,
                'end_date' => $end_date,
                'schedule' => $schedule,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s']
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Update an existing work schedule.
     *
     * @param int $id Schedule ID
     * @param array $data Schedule data
     * @return bool True on success
     */
    public static function update(int $id, array $data): bool
    {
        global $wpdb;
        $table = self::table();

        $existing = self::get($id);
        if (!$existing) {
            return false;
        }

        $member_id = (int) $existing->member_id;
        $start_date = isset($data['start_date']) ? sanitize_text_field($data['start_date']) : $existing->start_date;
        $end_date = isset($data['end_date']) ? ($data['end_date'] ? sanitize_text_field($data['end_date']) : null) : $existing->end_date;
        $schedule = isset($data['schedule']) ? $data['schedule'] : $existing->schedule;

        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) {
            return false;
        }

        if ($end_date !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
            return false;
        }

        // Check for overlapping schedules (excluding this one)
        if (self::has_overlap($member_id, $start_date, $end_date, $id)) {
            return false;
        }

        // Ensure schedule is a valid JSON string
        if (is_array($schedule)) {
            $schedule = wp_json_encode($schedule);
        }

        $result = $wpdb->update(
            $table,
            [
                'start_date' => $start_date,
                'end_date' => $end_date,
                'schedule' => $schedule,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $id],
            ['%s', '%s', '%s', '%s'],
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Delete a work schedule.
     *
     * @param int $id Schedule ID
     * @return bool True on success
     */
    public static function delete(int $id): bool
    {
        global $wpdb;
        $table = self::table();

        $result = $wpdb->delete($table, ['id' => $id], ['%d']);
        return $result !== false;
    }

    /**
     * Format schedules for API response.
     *
     * @param array $schedules Array of schedule objects
     * @return array Formatted array
     */
    public static function format_for_response(array $schedules): array
    {
        $formatted = [];

        foreach ($schedules as $schedule) {
            $decoded = is_string($schedule->schedule) 
                ? json_decode($schedule->schedule, true) 
                : $schedule->schedule;

            $formatted[] = [
                'id' => (int) $schedule->id,
                'startDate' => $schedule->start_date,
                'endDate' => $schedule->end_date,
                'schedule' => is_array($decoded) ? $decoded : [],
                'createdAt' => $schedule->created_at,
                'updatedAt' => $schedule->updated_at,
            ];
        }

        return $formatted;
    }
}
