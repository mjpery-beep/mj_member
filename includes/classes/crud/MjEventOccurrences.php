<?php

namespace Mj\Member\Classes\Crud;

use Mj\Member\Classes\MjRoles;

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

    const VISIBILITY_TOUS = 'tous';
    const VISIBILITY_COORDINATEUR = MjRoles::COORDINATEUR;
    const VISIBILITY_ANIMATEUR = MjRoles::ANIMATEUR;
    const VISIBILITY_JEUNE = MjRoles::JEUNE;

    /** @var bool|null */
    private static $supports_status_column = null;

    /** @var bool|null */
    private static $supports_generation_batch_column = null;

    /** @var bool|null */
    private static $supports_visibility_column = null;

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
        $generation_batch_select = self::support_generation_batch_column() ? ', generation_batch_id' : '';
        $visibility_select = self::support_visibility_column() ? ', audience_visibility' : '';
        $query = $wpdb->prepare(
            "SELECT id, event_id, start_at, end_at{$status_select}, source{$generation_batch_select}{$visibility_select}, meta FROM {$table} WHERE event_id = %d ORDER BY start_at ASC",
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

        self::insert_rows($table, $event_id, $occurrences);
    }

    /**
     * @param int $event_id
     * @param array<int,array<string,mixed>> $occurrences
     * @return void
     */
    public static function add_for_event($event_id, array $occurrences) {
        $event_id = (int) $event_id;
        if ($event_id <= 0 || !self::table_ready() || empty($occurrences)) {
            return;
        }

        global $wpdb;
        $table = self::table_name();

        self::insert_rows($table, $event_id, $occurrences);
    }

    /**
     * @param int $event_id
     * @param string $generation_batch_id
     * @return void
     */
    public static function delete_for_generation_batch($event_id, $generation_batch_id) {
        $event_id = (int) $event_id;
        $generation_batch_id = self::normalize_generation_batch_id($generation_batch_id);
        if ($event_id <= 0 || $generation_batch_id === null || !self::table_ready() || !self::support_generation_batch_column()) {
            return;
        }

        global $wpdb;
        $table = self::table_name();
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE event_id = %d AND generation_batch_id = %s",
                $event_id,
                $generation_batch_id
            )
        );
    }

    /**
     * @param string $table
     * @param int $event_id
     * @param array<int,array<string,mixed>> $occurrences
     * @return void
     */
    private static function insert_rows($table, $event_id, array $occurrences) {
        global $wpdb;

        foreach ($occurrences as $occurrence) {
            if (!is_array($occurrence)) {
                continue;
            }

            $row = self::normalize_occurrence_row($event_id, $occurrence);
            if (empty($row)) {
                continue;
            }

            $formats = self::get_insert_formats($row);
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

        $generation_batch_id = self::normalize_generation_batch_id(
            isset($occurrence['generation_batch_id'])
                ? $occurrence['generation_batch_id']
                : (isset($occurrence['batch_id']) ? $occurrence['batch_id'] : null)
        );

        $visibility = self::normalize_visibility(
            isset($occurrence['audience_visibility'])
                ? $occurrence['audience_visibility']
                : (isset($occurrence['visibility']) ? $occurrence['visibility'] : null)
        );

        $meta = self::normalize_meta(isset($occurrence['meta']) ? $occurrence['meta'] : null);
        if (!self::support_generation_batch_column() && $generation_batch_id !== null) {
            $meta['generation_batch_id'] = $generation_batch_id;
        }
        if (!self::support_visibility_column()) {
            $meta['visibility'] = $visibility;
        }

        $meta_value = self::serialize_meta($meta);

        $row = array(
            'event_id' => $event_id,
            'start_at' => $start_dt->format('Y-m-d H:i:s'),
            'end_at' => $end_dt->format('Y-m-d H:i:s'),
            'source' => $source,
        );

        if (self::support_status_column()) {
            $row['status'] = $status;
        }

        if (self::support_generation_batch_column()) {
            $row['generation_batch_id'] = $generation_batch_id;
        }

        if (self::support_visibility_column()) {
            $row['audience_visibility'] = $visibility;
        }

        $row['meta'] = $meta_value;

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
     * @return bool
     */
    private static function support_generation_batch_column() {
        if (self::$supports_generation_batch_column !== null) {
            return self::$supports_generation_batch_column;
        }

        if (!self::table_ready()) {
            self::$supports_generation_batch_column = false;
            return false;
        }

        global $wpdb;
        $table = self::table_name();
        $column = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", 'generation_batch_id'));

        self::$supports_generation_batch_column = !empty($column);

        return self::$supports_generation_batch_column;
    }

    /**
     * @return bool
     */
    private static function support_visibility_column() {
        if (self::$supports_visibility_column !== null) {
            return self::$supports_visibility_column;
        }

        if (!self::table_ready()) {
            self::$supports_visibility_column = false;
            return false;
        }

        global $wpdb;
        $table = self::table_name();
        $column = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", 'audience_visibility'));

        self::$supports_visibility_column = !empty($column);

        return self::$supports_visibility_column;
    }

    /**
     * @param array<string,mixed> $row
     * @return array<int,string>
     */
    private static function get_insert_formats(array $row) {
        $formats = array();
        foreach ($row as $column => $value) {
            switch ($column) {
                case 'event_id':
                    $formats[] = '%d';
                    break;
                case 'generation_batch_id':
                case 'meta':
                    $formats[] = $value === null ? '%s' : '%s';
                    break;
                default:
                    $formats[] = '%s';
                    break;
            }
        }

        return $formats;
    }

    /**
     * @param mixed $value
     * @return string|null
     */
    private static function normalize_generation_batch_id($value) {
        if ($value === null) {
            return null;
        }

        $candidate = wp_check_invalid_utf8((string) $value, true);
        $candidate = preg_replace('/[^A-Za-z0-9_-]/', '', $candidate);
        if (!is_string($candidate)) {
            return null;
        }

        $candidate = trim($candidate);
        if ($candidate === '') {
            return null;
        }

        return substr($candidate, 0, 64);
    }

    /**
     * @param mixed $value
     * @return string
     */
    private static function normalize_visibility($value) {
        $candidate = sanitize_key((string) $value);
        $aliases = array(
            'all' => self::VISIBILITY_TOUS,
            'tous' => self::VISIBILITY_TOUS,
            MjRoles::COORDINATEUR => self::VISIBILITY_COORDINATEUR,
            MjRoles::ANIMATEUR => self::VISIBILITY_ANIMATEUR,
            MjRoles::JEUNE => self::VISIBILITY_JEUNE,
        );

        if ($candidate === '' || !isset($aliases[$candidate])) {
            return self::VISIBILITY_TOUS;
        }

        return $aliases[$candidate];
    }

    /**
     * @param mixed $value
     * @return array<string,mixed>
     */
    private static function normalize_meta($value) {
        if (is_array($value)) {
            return $value;
        }

        if (is_object($value)) {
            return (array) $value;
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return array();
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
