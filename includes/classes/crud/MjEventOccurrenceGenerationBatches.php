<?php

namespace Mj\Member\Classes\Crud;

if (!defined('ABSPATH')) {
    exit;
}
/**
 * Class MjEventOccurrenceGenerationBatches
 * @desc CRUD for event occurrence generation batches, used to track the generation of occurrences for events, especially for recurring events.
 * @package Mj\Member\Classes\Crud
 */

class MjEventOccurrenceGenerationBatches {
    const TABLE = 'mj_event_occurrence_generation_batches';

    const STATUS_ACTIVE = 'active';
    const STATUS_DELETED = 'deleted';

    /**
     * @return string
     */
    private static function table_name() {
        if (function_exists('mj_member_get_event_occurrence_generation_batches_table_name')) {
            return mj_member_get_event_occurrence_generation_batches_table_name();
        }

        return self::TABLE;
    }

    /**
     * @return bool
     */
    private static function table_ready() {
        $table = self::table_name();
        if ($table === '' || !function_exists('mj_member_table_exists')) {
            return false;
        }

        return mj_member_table_exists($table);
    }

    /**
     * @param int $event_id
     * @param bool $include_deleted
     * @return array<int,array<string,mixed>>
     */
    public static function get_for_event($event_id, $include_deleted = true) {
        $event_id = (int) $event_id;
        if ($event_id <= 0 || !self::table_ready()) {
            return array();
        }

        global $wpdb;
        $table = self::table_name();
        $where = 'event_id = %d';
        $args = array($event_id);

        if (!$include_deleted) {
            $where .= ' AND status != %s';
            $args[] = self::STATUS_DELETED;
        }

        array_unshift($args, "SELECT id, batch_uuid, event_id, status, generated_by_member_id, config_snapshot, summary, occurrences_count, created_at, deleted_at FROM {$table} WHERE {$where} ORDER BY created_at DESC, id DESC");
        $query = call_user_func_array(array($wpdb, 'prepare'), $args);
        $rows = $wpdb->get_results($query, ARRAY_A);

        return is_array($rows) ? $rows : array();
    }

    /**
     * @param int $event_id
     * @param string $batch_uuid
     * @param array<string,mixed> $args
     * @return bool
     */
    public static function create($event_id, $batch_uuid, array $args = array()) {
        $event_id = (int) $event_id;
        $batch_uuid = self::normalize_batch_uuid($batch_uuid);
        if ($event_id <= 0 || $batch_uuid === '' || !self::table_ready()) {
            return false;
        }

        global $wpdb;
        $table = self::table_name();

        $row = array(
            'batch_uuid' => $batch_uuid,
            'event_id' => $event_id,
            'status' => self::normalize_status(isset($args['status']) ? $args['status'] : self::STATUS_ACTIVE),
            'generated_by_member_id' => isset($args['generated_by_member_id']) ? max(0, (int) $args['generated_by_member_id']) : null,
            'config_snapshot' => self::serialize_nullable(isset($args['config_snapshot']) ? $args['config_snapshot'] : null),
            'summary' => self::serialize_nullable(isset($args['summary']) ? $args['summary'] : null),
            'occurrences_count' => isset($args['occurrences_count']) ? max(0, (int) $args['occurrences_count']) : 0,
        );

        $existing = self::find_by_batch_uuid($batch_uuid);
        if (!empty($existing)) {
            $updated = $wpdb->update(
                $table,
                $row,
                array('batch_uuid' => $batch_uuid),
                array('%s', '%d', '%s', '%d', '%s', '%s', '%d'),
                array('%s')
            );

            return $updated !== false;
        }

        $inserted = $wpdb->insert(
            $table,
            $row,
            array('%s', '%d', '%s', '%d', '%s', '%s', '%d')
        );

        return $inserted !== false;
    }

    /**
     * @param string $batch_uuid
     * @return array<string,mixed>|null
     */
    public static function find_by_batch_uuid($batch_uuid) {
        $batch_uuid = self::normalize_batch_uuid($batch_uuid);
        if ($batch_uuid === '' || !self::table_ready()) {
            return null;
        }

        global $wpdb;
        $table = self::table_name();
        $query = $wpdb->prepare(
            "SELECT id, batch_uuid, event_id, status, generated_by_member_id, config_snapshot, summary, occurrences_count, created_at, deleted_at FROM {$table} WHERE batch_uuid = %s LIMIT 1",
            $batch_uuid
        );
        $row = $wpdb->get_row($query, ARRAY_A);

        return is_array($row) ? $row : null;
    }

    /**
     * @param int $event_id
     * @param string $batch_uuid
     * @return bool
     */
    public static function mark_deleted($event_id, $batch_uuid) {
        $event_id = (int) $event_id;
        $batch_uuid = self::normalize_batch_uuid($batch_uuid);
        if ($event_id <= 0 || $batch_uuid === '' || !self::table_ready()) {
            return false;
        }

        global $wpdb;
        $table = self::table_name();
        $updated = $wpdb->update(
            $table,
            array(
                'status' => self::STATUS_DELETED,
                'deleted_at' => current_time('mysql'),
            ),
            array(
                'event_id' => $event_id,
                'batch_uuid' => $batch_uuid,
            ),
            array('%s', '%s'),
            array('%d', '%s')
        );

        return $updated !== false;
    }

    /**
     * @param mixed $value
     * @return string
     */
    private static function normalize_batch_uuid($value) {
        $candidate = wp_check_invalid_utf8((string) $value, true);
        $candidate = preg_replace('/[^A-Za-z0-9_-]/', '', $candidate);
        if (!is_string($candidate)) {
            return '';
        }

        return substr(trim($candidate), 0, 64);
    }

    /**
     * @param mixed $value
     * @return string
     */
    private static function normalize_status($value) {
        $candidate = sanitize_key((string) $value);
        if (!in_array($candidate, array(self::STATUS_ACTIVE, self::STATUS_DELETED), true)) {
            return self::STATUS_ACTIVE;
        }

        return $candidate;
    }

    /**
     * @param mixed $value
     * @return string|null
     */
    private static function serialize_nullable($value) {
        if ($value === null) {
            return null;
        }

        if (is_array($value) || is_object($value)) {
            return wp_json_encode($value);
        }

        $string = wp_check_invalid_utf8((string) $value, true);
        return $string !== '' ? $string : null;
    }
}