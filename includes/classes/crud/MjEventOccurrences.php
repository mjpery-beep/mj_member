<?php

namespace Mj\Member\Classes\Crud;

if (!defined('ABSPATH')) {
    exit;
}

class MjEventOccurrences {
    const TABLE = 'mj_event_occurrences';

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
        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT id, event_id, start_at, end_at, source, meta FROM {$table} WHERE event_id = %d ORDER BY start_at ASC", $event_id),
            ARRAY_A
        );

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

            $wpdb->insert(
                $table,
                $row,
                array('%d', '%s', '%s', '%s', '%s')
            );
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

        $start_valid = strtotime($start_raw);
        $end_valid = strtotime($end_raw);
        if ($start_valid === false || $end_valid === false) {
            return array();
        }

        if ($end_valid <= $start_valid) {
            return array();
        }

        $source = isset($occurrence['source']) ? sanitize_key((string) $occurrence['source']) : '';
        if ($source === '') {
            $source = 'generated';
        }

        $meta_value = isset($occurrence['meta']) ? self::serialize_meta($occurrence['meta']) : null;

        return array(
            'event_id' => $event_id,
            'start_at' => function_exists('wp_date') ? wp_date('Y-m-d H:i:s', $start_valid) : date('Y-m-d H:i:s', $start_valid),
            'end_at' => function_exists('wp_date') ? wp_date('Y-m-d H:i:s', $end_valid) : date('Y-m-d H:i:s', $end_valid),
            'source' => $source,
            'meta' => $meta_value,
        );
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
