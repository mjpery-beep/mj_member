<?php

namespace Mj\Member\Classes\Crud;

if (!defined('ABSPATH')) {
    exit;
}

class MjEventOccurrences {
    const TABLE = 'mj_event_date_occurrences';

    const STATUS_ACTIVE = 'active';
    const STATUS_A_CONFIRMER = 'pending';
    const STATUS_REPORTE = 'postponed';
    const STATUS_ANNULE = 'cancelled';
    const STATUS_SUPPRIME = 'deleted';

    const SOURCE_MANUAL = 'manual';
    const SOURCE_GENERATED = 'generated';

    /** @var bool|null */
    private static $supports_status_column = null;

    /**
     * @return string
     */
    private static function table_name() {
        if (function_exists('mj_member_get_event_occurrences_table_name')) {
            return mj_member_get_event_occurrences_table_name();
        }

        return self::TABLE;
    }

    /**
     * @return bool
     */
    private static function table_ready() {
        $table = self::table_name();
        if ($table === '') {
            return false;
        }

        if (!function_exists('mj_member_table_exists')) {
            return false;
        }

        return mj_member_table_exists($table);
    }

    /**
     * @param int $event_id
     * @return array<int,array<string,mixed>>
     */
    public static function get_for_event($event_id) {
        $event_id = (int) $event_id;
        if ($event_id <= 0 || !self::table_ready()) {
            return array();
        }

        global $wpdb;
        $table = self::table_name();
        $status_select = self::support_status_column() ? ', status' : '';
        $query = $wpdb->prepare(
            "SELECT id, event_id, start_at, end_at{$status_select}, source, meta FROM {$table} WHERE event_id = %d ORDER BY start_at ASC",
            $event_id
        );

        $rows = $wpdb->get_results($query, ARRAY_A);

        return is_array($rows) ? $rows : array();
    }

    /**
     * @param int $event_id
     * @param array<int,array<string,mixed>> $occurrences
     * @return void
     */
    public static function replace_for_event($event_id, array $occurrences) {
        $event_id = (int) $event_id;
        if ($event_id <= 0 || !self::table_ready()) {
            return;
        }

        global $wpdb;
        $table = self::table_name();
        $wpdb->delete($table, array('event_id' => $event_id), array('%d'));

        if (empty($occurrences)) {
            return;
        }

        foreach ($occurrences as $occurrence) {
            if (!is_array($occurrence)) {
                continue;
            }

            $row = self::normalize_occurrence_row($event_id, $occurrence);
            if (empty($row)) {
                continue;
            }

            $formats = array('%d', '%s', '%s', '%s', '%s', '%s');
            if (!self::support_status_column()) {
                unset($row['status']);
                $formats = array('%d', '%s', '%s', '%s', '%s');
            }

            $wpdb->insert($table, $row, $formats);
        }
    }

    /**
     * @param int $event_id
     * @return void
     */
    public static function delete_for_event($event_id) {
        $event_id = (int) $event_id;
        if ($event_id <= 0 || !self::table_ready()) {
            return;
        }

        global $wpdb;
        $table = self::table_name();
        $wpdb->delete($table, array('event_id' => $event_id), array('%d'));
    }

    /**
     * @param int $event_id
     * @param array<string,mixed> $occurrence
     * @return array<string,mixed>
     */
    private static function normalize_occurrence_row($event_id, array $occurrence) {
        $start_raw = isset($occurrence['start']) ? trim((string) $occurrence['start']) : '';
        $end_raw = isset($occurrence['end']) ? trim((string) $occurrence['end']) : '';
        if ($start_raw === '' || $end_raw === '') {
            return array();
        }

        $timezone = function_exists('wp_timezone') ? wp_timezone() : new \DateTimeZone('UTC');

        $start_dt = \DateTime::createFromFormat('Y-m-d H:i:s', $start_raw, $timezone);
        if (!$start_dt instanceof \DateTime) {
            $start_timestamp = strtotime($start_raw);
            if ($start_timestamp === false) {
                return array();
            }
            $start_dt = new \DateTime('@' . $start_timestamp);
            $start_dt->setTimezone($timezone);
        }

        $end_dt = \DateTime::createFromFormat('Y-m-d H:i:s', $end_raw, $timezone);
        if (!$end_dt instanceof \DateTime) {
            $end_timestamp = strtotime($end_raw);
            if ($end_timestamp === false) {
                return array();
            }
            $end_dt = new \DateTime('@' . $end_timestamp);
            $end_dt->setTimezone($timezone);
        }

        if ($end_dt <= $start_dt) {
            return array();
        }

        $status = self::normalize_status(isset($occurrence['status']) ? $occurrence['status'] : null);
        $source = isset($occurrence['source']) ? sanitize_key((string) $occurrence['source']) : '';
        if ($source === '') {
            $source = self::SOURCE_MANUAL;
        }

        $meta_value = isset($occurrence['meta']) ? self::serialize_meta($occurrence['meta']) : null;

        $row = array(
            'event_id' => $event_id,
            'start_at' => $start_dt->format('Y-m-d H:i:s'),
            'end_at' => $end_dt->format('Y-m-d H:i:s'),
            'source' => $source,
            'meta' => $meta_value,
        );

        if (self::support_status_column()) {
            $row['status'] = $status;
        }

        return $row;
    }

    /**
     * @param mixed $value
     * @return string
     */
    private static function normalize_status($value) {
        $candidate = sanitize_key((string) $value);
        $allowed = array(
            self::STATUS_ACTIVE,
            self::STATUS_A_CONFIRMER,
            self::STATUS_REPORTE,
            self::STATUS_ANNULE,
            self::STATUS_SUPPRIME,
        );

        if ($candidate === '' || !in_array($candidate, $allowed, true)) {
            return self::STATUS_ACTIVE;
        }

        return $candidate;
    }

    /**
     * @return bool
     */
    private static function support_status_column() {
        if (self::$supports_status_column !== null) {
            return self::$supports_status_column;
        }

        if (!self::table_ready()) {
            self::$supports_status_column = false;
            return false;
        }

        global $wpdb;
        $table = self::table_name();
        $column = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", 'status'));

        self::$supports_status_column = !empty($column);

        return self::$supports_status_column;
    }

    /**
     * @param mixed $value
     * @return string|null
     */
    private static function serialize_meta($value) {
        if ($value === null) {
            return null;
        }

        if (is_array($value) || is_object($value)) {
            return wp_json_encode($value);
        }

        $string = (string) $value;
        $string = wp_check_invalid_utf8($string, true);

        return $string !== '' ? $string : null;
    }
}
