<?php

namespace Mj\Member\Classes\Crud;

use DateTime;
use DateTimeInterface;
use Exception;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class MjEventAttendance {
    const STATUS_PRESENT = 'present';
    const STATUS_ABSENT = 'absent';
    const STATUS_PENDING = 'pending';

    /** @var array<string,object> */
    private static $registration_cache = array();

    /**
     * @return string
     */
    public static function get_table_name() {
        if (!function_exists('mj_member_get_event_registrations_table_name')) {
            return '';
        }

        $table = mj_member_get_event_registrations_table_name();
        self::ensure_columns($table);

        return $table;
    }

    /**
     * @return array<int,string>
     */
    public static function get_statuses() {
        return array(self::STATUS_PRESENT, self::STATUS_ABSENT, self::STATUS_PENDING);
    }

    /**
     * @param mixed $value
     * @return string
     */
    public static function normalize_occurrence($value) {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        $value = (string) $value;
        if ($value === '') {
            return '';
        }

        try {
            $date = new DateTime($value, wp_timezone());
            return $date->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            $timestamp = strtotime($value);
            if ($timestamp === false) {
                return '';
            }
            $date = date_create('@' . $timestamp);
            if (!$date) {
                return '';
            }
            $date->setTimezone(wp_timezone());
            return $date->format('Y-m-d H:i:s');
        }
    }

    /**
     * @param string $status
     * @return string
     */
    public static function normalize_status($status) {
        $status = sanitize_key($status);
        if (in_array($status, self::get_statuses(), true)) {
            return $status;
        }
        return '';
    }

    /**
     * @param int $event_id
     * @param int $member_id
     * @param string $occurrence_start
     * @return array<string,mixed>|null
     */
    public static function get_record($event_id, $member_id, $occurrence_start) {
        $event_id = (int) $event_id;
        $member_id = (int) $member_id;
        $occurrence = self::normalize_occurrence($occurrence_start);

        if ($event_id <= 0 || $member_id <= 0 || $occurrence === '') {
            return null;
        }

        $registration = self::get_registration_row(0, $event_id, $member_id);
        if (!$registration) {
            return null;
        }

        $payload = self::decode_payload(isset($registration->attendance_payload) ? $registration->attendance_payload : null);
        if (empty($payload['occurrences'][$occurrence])) {
            return null;
        }

        $entry = $payload['occurrences'][$occurrence];
        $entry['status'] = self::normalize_status(isset($entry['status']) ? $entry['status'] : '');
        if ($entry['status'] === '') {
            return null;
        }

        $entry['registration_id'] = isset($registration->id) ? (int) $registration->id : 0;

        return $entry;
    }

    /**
     * @param int $event_id
     * @return array<string,array<int,array<string,mixed>>>
     */
    public static function get_map($event_id) {
        $event_id = (int) $event_id;
        if ($event_id <= 0) {
            return array();
        }

        $table = self::get_table_name();
        if ($table === '') {
            return array();
        }

        global $wpdb;
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, member_id, attendance_payload FROM {$table} WHERE event_id = %d",
                $event_id
            )
        );

        if (empty($rows)) {
            return array();
        }

        $map = array();

        foreach ($rows as $row) {
            if (!isset($row->member_id)) {
                continue;
            }
            $member_id = (int) $row->member_id;
            if ($member_id <= 0) {
                continue;
            }

            $payload = self::decode_payload(isset($row->attendance_payload) ? $row->attendance_payload : null);
            if (empty($payload['occurrences'])) {
                continue;
            }

            foreach ($payload['occurrences'] as $occurrence => $entry) {
                $occurrence_key = self::normalize_occurrence($occurrence);
                if ($occurrence_key === '') {
                    continue;
                }

                $status = self::normalize_status(isset($entry['status']) ? $entry['status'] : '');
                if ($status === '') {
                    continue;
                }

                if (!isset($map[$occurrence_key])) {
                    $map[$occurrence_key] = array();
                }

                $map[$occurrence_key][$member_id] = array(
                    'record_id' => isset($entry['record_id']) ? (int) $entry['record_id'] : (isset($row->id) ? (int) $row->id : 0),
                    'registration_id' => isset($row->id) ? (int) $row->id : 0,
                    'status' => $status,
                    'recorded_by' => isset($entry['recorded_by']) ? (int) $entry['recorded_by'] : 0,
                    'recorded_at' => isset($entry['recorded_at']) ? (string) $entry['recorded_at'] : '',
                );
            }
        }

        return $map;
    }

    /**
     * @param int $event_id
     * @param int $member_id
     * @param string $occurrence_start
     * @param string $status
     * @param array<string,mixed> $args
     * @return true|WP_Error
     */
    public static function record($event_id, $member_id, $occurrence_start, $status, array $args = array()) {
        $event_id = (int) $event_id;
        $member_id = (int) $member_id;
        $occurrence = self::normalize_occurrence($occurrence_start);
        $status = self::normalize_status($status);

        if ($event_id <= 0 || $member_id <= 0 || $occurrence === '') {
            return new WP_Error('mj_event_attendance_invalid_args', __('Parametres de presence invalides.', 'mj-member'));
        }

        $registration_id = isset($args['registration_id']) ? (int) $args['registration_id'] : 0;
        $registration = self::get_registration_row($registration_id, $event_id, $member_id);
        if (!$registration) {
            return new WP_Error('mj_event_attendance_missing_registration', __('Inscription introuvable.', 'mj-member'));
        }

        $payload = self::decode_payload(isset($registration->attendance_payload) ? $registration->attendance_payload : null);

        if ($status === '' || $status === self::STATUS_PENDING) {
            if (isset($payload['occurrences'][$occurrence])) {
                unset($payload['occurrences'][$occurrence]);
                if (!self::save_payload($registration, $payload)) {
                    return new WP_Error('mj_event_attendance_update_failed', __('Impossible de mettre a jour la presence.', 'mj-member'));
                }
            }
            return true;
        }

        $entry = array(
            'status' => $status,
            'recorded_at' => current_time('mysql'),
            'registration_id' => isset($registration->id) ? (int) $registration->id : 0,
        );

        $recorded_by = isset($args['recorded_by']) ? (int) $args['recorded_by'] : 0;
        if ($recorded_by > 0) {
            $entry['recorded_by'] = $recorded_by;
        }

        if (!empty($args['notes'])) {
            $entry['notes'] = sanitize_textarea_field($args['notes']);
        }

        if (!empty($args['occurrence_end'])) {
            $occurrence_end = self::normalize_occurrence($args['occurrence_end']);
            if ($occurrence_end !== '') {
                $entry['occurrence_end'] = $occurrence_end;
            }
        }

        $payload['occurrences'][$occurrence] = $entry;

        if (!self::save_payload($registration, $payload)) {
            return new WP_Error('mj_event_attendance_update_failed', __('Impossible de mettre a jour la presence.', 'mj-member'));
        }

        return true;
    }

    /**
     * @param int $event_id
     * @param int $member_id
     * @param string $occurrence_start
     * @return true|WP_Error
     */
    public static function delete($event_id, $member_id, $occurrence_start) {
        $event_id = (int) $event_id;
        $member_id = (int) $member_id;
        $occurrence = self::normalize_occurrence($occurrence_start);

        if ($event_id <= 0 || $member_id <= 0 || $occurrence === '') {
            return true;
        }

        $registration = self::get_registration_row(0, $event_id, $member_id);
        if (!$registration) {
            return true;
        }

        $payload = self::decode_payload(isset($registration->attendance_payload) ? $registration->attendance_payload : null);
        if (empty($payload['occurrences'][$occurrence])) {
            return true;
        }

        unset($payload['occurrences'][$occurrence]);

        if (!self::save_payload($registration, $payload)) {
            return new WP_Error('mj_event_attendance_delete_failed', __('Impossible de supprimer la presence.', 'mj-member'));
        }

        return true;
    }

    /**
     * @param int $event_id
     * @param string $occurrence_start
     * @param array<int,array<string,mixed>> $entries
     * @param int $user_id
     * @return array<string,mixed>|WP_Error
     */
    public static function bulk_record($event_id, $occurrence_start, array $entries, $user_id = 0) {
        $occurrence = self::normalize_occurrence($occurrence_start);
        if ($occurrence === '') {
            return new WP_Error('mj_event_attendance_bad_occurrence', __('Date d\'occurrence invalide.', 'mj-member'));
        }

        $results = array(
            'updated' => 0,
            'removed' => 0,
        );

        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $member_id = isset($entry['member_id']) ? (int) $entry['member_id'] : 0;
            if ($member_id <= 0) {
                continue;
            }

            $registration_id = isset($entry['registration_id']) ? (int) $entry['registration_id'] : 0;
            $status = isset($entry['status']) ? sanitize_key((string) $entry['status']) : '';
            $status = self::normalize_status($status);

            if ($status === '' || $status === self::STATUS_PENDING) {
                $existing = self::get_record($event_id, $member_id, $occurrence);
                $delete = self::delete($event_id, $member_id, $occurrence);
                if (is_wp_error($delete)) {
                    return $delete;
                }
                if ($existing !== null) {
                    $results['removed']++;
                }
                continue;
            }

            $record = self::record(
                $event_id,
                $member_id,
                $occurrence,
                $status,
                array(
                    'registration_id' => $registration_id,
                    'recorded_by' => $user_id,
                )
            );

            if (is_wp_error($record)) {
                return $record;
            }

            $results['updated']++;
        }

        return $results;
    }

    /**
     * @param int $event_id
     * @param string $occurrence_start
     * @return array<string,int>
     */
    public static function get_counts($event_id, $occurrence_start) {
        $event_id = (int) $event_id;
        $occurrence = self::normalize_occurrence($occurrence_start);

        if ($event_id <= 0 || $occurrence === '') {
            return array();
        }

        $map = self::get_map($event_id);
        if (empty($map) || empty($map[$occurrence])) {
            return array();
        }

        $counts = array();

        foreach ($map[$occurrence] as $entry) {
            $status = isset($entry['status']) ? self::normalize_status($entry['status']) : '';
            if ($status === '') {
                continue;
            }
            if (!isset($counts[$status])) {
                $counts[$status] = 0;
            }
            $counts[$status]++;
        }

        return $counts;
    }

    /**
     * @param int $registration_id
     * @param int $event_id
     * @param int $member_id
     * @return object|null
     */
    private static function get_registration_row($registration_id, $event_id, $member_id) {
        $registration_id = (int) $registration_id;
        $event_id = (int) $event_id;
        $member_id = (int) $member_id;

        $cache_key = '';
        if ($registration_id > 0) {
            $cache_key = 'id:' . $registration_id;
        } elseif ($event_id > 0 && $member_id > 0) {
            $cache_key = 'event:' . $event_id . ':member:' . $member_id;
        }

        if ($cache_key !== '' && isset(self::$registration_cache[$cache_key])) {
            return self::$registration_cache[$cache_key];
        }

        $table = self::get_table_name();
        if ($table === '') {
            return null;
        }

        global $wpdb;
        if ($registration_id > 0) {
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $registration_id));
        } elseif ($event_id > 0 && $member_id > 0) {
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$table} WHERE event_id = %d AND member_id = %d ORDER BY created_at DESC LIMIT 1",
                    $event_id,
                    $member_id
                )
            );
        } else {
            $row = null;
        }

        if (!$row) {
            return null;
        }

        self::store_registration_in_cache($row);
        return $row;
    }

    /**
     * @param object $registration
     * @return void
     */
    private static function store_registration_in_cache($registration) {
        if (!is_object($registration) || !isset($registration->id)) {
            return;
        }

        self::$registration_cache['id:' . (int) $registration->id] = $registration;

        if (isset($registration->event_id) && isset($registration->member_id)) {
            $key = 'event:' . (int) $registration->event_id . ':member:' . (int) $registration->member_id;
            self::$registration_cache[$key] = $registration;
        }
    }

    private static function get_default_assignments() {
        return array(
            'mode' => 'all',
            'occurrences' => array(),
        );
    }

    private static function normalize_assignments($input) {
        $assignments = self::get_default_assignments();

        if (!is_array($input)) {
            return $assignments;
        }

        $mode = isset($input['mode']) ? sanitize_key((string) $input['mode']) : '';
        if ($mode !== 'custom') {
            return $assignments;
        }

        $occurrence_map = array();
        if (!empty($input['occurrences']) && is_array($input['occurrences'])) {
            foreach ($input['occurrences'] as $occurrence) {
                $normalized = self::normalize_occurrence($occurrence);
                if ($normalized !== '' && !isset($occurrence_map[$normalized])) {
                    $occurrence_map[$normalized] = true;
                }
            }
        }

        if (empty($occurrence_map)) {
            return $assignments;
        }

        $assignments['mode'] = 'custom';
        $assignments['occurrences'] = array_values(array_keys($occurrence_map));

        return $assignments;
    }

    /**
     * @param array<string,mixed>|null $assignments
     * @param string|null $occurrence
     * @return bool
     */
    public static function assignments_cover_occurrence($assignments, $occurrence) {
        $normalized = self::normalize_assignments(is_array($assignments) ? $assignments : array());
        $occurrence_key = self::normalize_occurrence($occurrence);

        if ($occurrence_key === '') {
            return $normalized['mode'] !== 'custom';
        }

        if ($normalized['mode'] !== 'custom') {
            return true;
        }

        foreach ($normalized['occurrences'] as $assigned_occurrence) {
            if (self::normalize_occurrence($assigned_occurrence) === $occurrence_key) {
                return true;
            }
        }

        return false;
    }

    private static function normalize_payload(array $payload) {
        if (!isset($payload['occurrences']) || !is_array($payload['occurrences'])) {
            $payload['occurrences'] = array();
        }

        if (isset($payload['assignments'])) {
            $payload['assignments'] = self::normalize_assignments($payload['assignments']);
        } else {
            $payload['assignments'] = self::get_default_assignments();
        }

        return $payload;
    }

    /**
     * @param string|null $raw
     * @return array{occurrences:array<string,array<string,mixed>>,assignments:array{mode:string,occurrences:array<int,string>}}
     */
    private static function decode_payload($raw) {
        $payload = array(
            'occurrences' => array(),
            'assignments' => self::get_default_assignments(),
        );

        if (!is_string($raw) || $raw === '') {
            return $payload;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return $payload;
        }

        if (isset($decoded['occurrences']) && is_array($decoded['occurrences'])) {
            $payload['occurrences'] = $decoded['occurrences'];
        } elseif (!isset($decoded['assignments'])) {
            $payload['occurrences'] = $decoded;
        }

        if (isset($decoded['assignments'])) {
            $payload['assignments'] = self::normalize_assignments($decoded['assignments']);
        }

        return $payload;
    }

    /**
     * @param array{occurrences:array<string,array<string,mixed>>,assignments?:array{mode:string,occurrences:array<int,string>}} $payload
     * @return string|null
     */
    private static function encode_payload(array $payload) {
        $payload = self::normalize_payload($payload);

        $data = array();
        if (!empty($payload['occurrences'])) {
            $data['occurrences'] = $payload['occurrences'];
        }

        if (!empty($payload['assignments']) && $payload['assignments']['mode'] === 'custom' && !empty($payload['assignments']['occurrences'])) {
            $data['assignments'] = $payload['assignments'];
        }

        if (empty($data)) {
            return null;
        }

        $encoded = wp_json_encode($data);
        if ($encoded === false || $encoded === null) {
            return null;
        }

        return $encoded;
    }

    /**
     * @param object $registration
     * @param array{occurrences:array<string,array<string,mixed>>} $payload
     * @return bool
     */
    private static function save_payload($registration, array $payload) {
        if (!isset($registration->id)) {
            return false;
        }

        $table = self::get_table_name();
        if ($table === '') {
            return false;
        }

        global $wpdb;

        $normalized_payload = self::normalize_payload($payload);
        $serialized = self::encode_payload($normalized_payload);
        $timestamp = current_time('mysql');

        if ($serialized === null) {
            $result = $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table} SET attendance_payload = NULL, attendance_updated_at = %s WHERE id = %d",
                    $timestamp,
                    (int) $registration->id
                )
            );
            $registration->attendance_payload = null;
        } else {
            $result = $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table} SET attendance_payload = %s, attendance_updated_at = %s WHERE id = %d",
                    $serialized,
                    $timestamp,
                    (int) $registration->id
                )
            );
            $registration->attendance_payload = $serialized;
        }

        if ($result === false) {
            return false;
        }

        $registration->attendance_updated_at = $timestamp;
        $registration->assignments = $normalized_payload['assignments'];
        self::store_registration_in_cache($registration);

        return true;
    }

    /**
     * @param object|array|null $registration
     * @return array{mode:string,occurrences:array<int,string>}
     */
    public static function get_registration_assignments($registration) {
        if (!$registration) {
            return self::get_default_assignments();
        }

        $payload_raw = null;
        if (is_object($registration) && isset($registration->attendance_payload)) {
            $payload_raw = $registration->attendance_payload;
        } elseif (is_array($registration) && isset($registration['attendance_payload'])) {
            $payload_raw = $registration['attendance_payload'];
        }

        $decoded = self::decode_payload($payload_raw);

        if (isset($decoded['assignments'])) {
            return self::normalize_assignments($decoded['assignments']);
        }

        return self::get_default_assignments();
    }

    /**
     * @param int $registration_id
     * @param array{mode:string,occurrences?:array<int,string>} $assignments
     * @return true|WP_Error
     */
    public static function set_registration_assignments($registration_id, array $assignments) {
        $registration_id = (int) $registration_id;
        if ($registration_id <= 0) {
            return new WP_Error('mj_event_attendance_bad_registration', __('Inscription invalide.', 'mj-member'));
        }

        $registration = self::get_registration_row($registration_id, 0, 0);
        if (!$registration) {
            return new WP_Error('mj_event_attendance_missing_registration', __('Inscription introuvable.', 'mj-member'));
        }

        $payload = self::decode_payload(isset($registration->attendance_payload) ? $registration->attendance_payload : null);
        $payload['assignments'] = self::normalize_assignments($assignments);

        if (!self::save_payload($registration, $payload)) {
            return new WP_Error('mj_event_attendance_assignment_failed', __('Impossible de mettre à jour les occurrences assignées.', 'mj-member'));
        }

        return true;
    }

    /**
     * @param string $table
     * @return void
     */
    private static function ensure_columns($table) {
        static $checked = array();

        $table_key = (string) $table;
        if ($table_key === '' || isset($checked[$table_key])) {
            return;
        }

        $checked[$table_key] = true;

        if (!function_exists('mj_member_table_exists') || !function_exists('mj_member_column_exists')) {
            return;
        }

        if (!mj_member_table_exists($table_key)) {
            return;
        }

        global $wpdb;

        if (!mj_member_column_exists($table_key, 'attendance_payload')) {
            $wpdb->query("ALTER TABLE {$table_key} ADD COLUMN attendance_payload longtext DEFAULT NULL");
        }

        if (!mj_member_column_exists($table_key, 'attendance_updated_at')) {
            $wpdb->query("ALTER TABLE {$table_key} ADD COLUMN attendance_updated_at datetime DEFAULT NULL");
        }

        if (!mj_member_column_exists($table_key, 'payment_status')) {
            $wpdb->query("ALTER TABLE {$table_key} ADD COLUMN payment_status varchar(20) NOT NULL DEFAULT 'unpaid'");
        }

        if (!mj_member_column_exists($table_key, 'payment_method')) {
            $wpdb->query("ALTER TABLE {$table_key} ADD COLUMN payment_method varchar(40) DEFAULT NULL");
        }

        if (!mj_member_column_exists($table_key, 'payment_recorded_at')) {
            $wpdb->query("ALTER TABLE {$table_key} ADD COLUMN payment_recorded_at datetime DEFAULT NULL");
        }

        if (!mj_member_column_exists($table_key, 'payment_recorded_by')) {
            $wpdb->query("ALTER TABLE {$table_key} ADD COLUMN payment_recorded_by bigint(20) unsigned DEFAULT NULL");
        }
    }
}

class_alias(__NAMESPACE__ . '\\MjEventAttendance', 'MjEventAttendance');
