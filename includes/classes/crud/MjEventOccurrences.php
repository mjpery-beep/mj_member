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
     * Ensure generation batch id is persisted on freshly inserted generated rows.
     *
     * @param int $event_id
     * @param string $generation_batch_id
     * @param array<int,array<string,mixed>> $occurrences
     * @return void
     */
    public static function enforce_generation_batch_for_rows($event_id, $generation_batch_id, array $occurrences) {
        $event_id = (int) $event_id;
        $batch_lookup = self::resolve_generation_batch_lookup($generation_batch_id);
        $generation_batch_storage_id = $batch_lookup['storage_id'];
        $generation_batch_uuid = $batch_lookup['uuid'];
        if ($event_id <= 0 || $generation_batch_storage_id === null || empty($occurrences) || !self::table_ready()) {
            return;
        }

        global $wpdb;
        $table = self::table_name();

        if (self::has_generation_batch_column() && $generation_batch_uuid !== null) {
            $batch_like = '%' . $wpdb->esc_like($generation_batch_uuid) . '%';
            $updated_from_meta = $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table} SET generation_batch_id = %s WHERE event_id = %d AND source = %s AND meta LIKE %s",
                    $generation_batch_storage_id,
                    $event_id,
                    self::SOURCE_GENERATED,
                    $batch_like
                )
            );
            error_log('[MjEventOccurrences::enforce_generation_batch_for_rows] Updated from meta count=' . (string) $updated_from_meta . ' for event_id=' . $event_id . ', batch_id=' . $generation_batch_storage_id . ', batch_uuid=' . $generation_batch_uuid);
        }

        foreach ($occurrences as $occurrence) {
            if (!is_array($occurrence)) {
                continue;
            }

            $start = isset($occurrence['start']) ? trim((string) $occurrence['start']) : '';
            $end = isset($occurrence['end']) ? trim((string) $occurrence['end']) : '';
            if ($start === '' || $end === '') {
                continue;
            }

            if (self::has_generation_batch_column()) {
                $matched_before = (int) $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM {$table} WHERE event_id = %d AND source = %s AND start_at = %s AND end_at = %s AND (generation_batch_id IS NULL OR generation_batch_id = '' OR generation_batch_id = %s)",
                        $event_id,
                        self::SOURCE_GENERATED,
                        $start,
                        $end,
                        $generation_batch_storage_id
                    )
                );
                $null_before = (int) $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM {$table} WHERE event_id = %d AND source = %s AND start_at = %s AND end_at = %s AND (generation_batch_id IS NULL OR generation_batch_id = '')",
                        $event_id,
                        self::SOURCE_GENERATED,
                        $start,
                        $end
                    )
                );
                $updated_precise = $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$table} SET generation_batch_id = %s WHERE event_id = %d AND source = %s AND start_at = %s AND end_at = %s AND (generation_batch_id IS NULL OR generation_batch_id = '' OR generation_batch_id = %s)",
                        $generation_batch_storage_id,
                        $event_id,
                        self::SOURCE_GENERATED,
                        $start,
                        $end,
                        $generation_batch_storage_id
                    )
                );
                $null_after = (int) $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM {$table} WHERE event_id = %d AND source = %s AND start_at = %s AND end_at = %s AND (generation_batch_id IS NULL OR generation_batch_id = '')",
                        $event_id,
                        self::SOURCE_GENERATED,
                        $start,
                        $end
                    )
                );
                error_log('[MjEventOccurrences::enforce_generation_batch_for_rows] Precise update for event_id=' . $event_id . ', start=' . $start . ', end=' . $end . ', matched_before=' . $matched_before . ', null_before=' . $null_before . ', updated_count=' . (string) $updated_precise . ', null_after=' . $null_after . ', batch_id=' . $generation_batch_storage_id . ', batch_uuid=' . ($generation_batch_uuid ?? ''));
            }
        }
    }

    /**
     * @param int $event_id
     * @param string $generation_batch_id
     * @return void
     */
    public static function delete_for_generation_batch($event_id, $generation_batch_id) {
        $event_id = (int) $event_id;
        $batch_lookup = self::resolve_generation_batch_lookup($generation_batch_id);
        $generation_batch_storage_id = $batch_lookup['storage_id'];
        $generation_batch_uuid = $batch_lookup['uuid'];
        if ($event_id <= 0 || ($generation_batch_storage_id === null && $generation_batch_uuid === null) || !self::table_ready()) {
            error_log('[MjEventOccurrences::delete_for_generation_batch] Early return: event_id=' . $event_id . ', batch_id=' . (($generation_batch_storage_id ?? $generation_batch_uuid) ?? 'NULL'));
            return;
        }

        global $wpdb;
        $table = self::table_name();
        error_log('[MjEventOccurrences::delete_for_generation_batch] support_generation_batch_column=' . (self::support_generation_batch_column() ? 'true' : 'false'));
        
        if (self::support_generation_batch_column() && $generation_batch_storage_id !== null) {
            $query = $wpdb->prepare(
                "DELETE FROM {$table} WHERE event_id = %d AND generation_batch_id = %s",
                $event_id,
                $generation_batch_storage_id
            );
            error_log('[MjEventOccurrences::delete_for_generation_batch] SQL column query: ' . $query);
            $result = $wpdb->query($query);
            error_log('[MjEventOccurrences::delete_for_generation_batch] Column delete result: ' . $result);
        }

        // Legacy fallback when generation_batch_id is stored inside the meta JSON payload.
        // Use broad LIKE conditions to tolerate JSON spacing/format differences.
        if ($generation_batch_uuid !== null) {
            $meta_key_like = '%' . $wpdb->esc_like('generation_batch_id') . '%';
            $meta_value_like = '%' . $wpdb->esc_like($generation_batch_uuid) . '%';
            $query = $wpdb->prepare(
                "DELETE FROM {$table} WHERE event_id = %d AND source = %s AND meta LIKE %s AND meta LIKE %s",
                $event_id,
                self::SOURCE_GENERATED,
                $meta_key_like,
                $meta_value_like
            );
            error_log('[MjEventOccurrences::delete_for_generation_batch] SQL meta query: ' . $query);
            $result = $wpdb->query($query);
            error_log('[MjEventOccurrences::delete_for_generation_batch] Meta delete result: ' . $result);
        }
    }

    /**
     * Delete generated occurrences for an event matching a set of start/end pairs.
     *
     * @param int $event_id
     * @param array<int,array<string,mixed>> $occurrences
     * @return void
     */
    public static function delete_generated_matching_rows($event_id, array $occurrences) {
        $event_id = (int) $event_id;
        if ($event_id <= 0 || empty($occurrences) || !self::table_ready()) {
            return;
        }

        global $wpdb;
        $table = self::table_name();

        foreach ($occurrences as $occurrence) {
            if (!is_array($occurrence)) {
                continue;
            }

            $start = isset($occurrence['start']) ? trim((string) $occurrence['start']) : '';
            $end = isset($occurrence['end']) ? trim((string) $occurrence['end']) : '';
            if ($start === '' || $end === '') {
                continue;
            }

            $deleted = $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$table} WHERE event_id = %d AND source = %s AND start_at = %s AND end_at = %s",
                    $event_id,
                    self::SOURCE_GENERATED,
                    $start,
                    $end
                )
            );
            error_log('[MjEventOccurrences::delete_generated_matching_rows] Deleted count=' . (string) $deleted . ' for event_id=' . $event_id . ', start=' . $start . ', end=' . $end);
        }
    }

    /**
     * Delete generated rows that still have NULL/empty generation_batch_id for the given start/end pairs.
     *
     * @param int $event_id
     * @param array<int,array<string,mixed>> $occurrences
     * @return int
     */
    public static function delete_generated_null_matching_rows($event_id, array $occurrences) {
        $event_id = (int) $event_id;
        if ($event_id <= 0 || empty($occurrences) || !self::table_ready() || !self::has_generation_batch_column()) {
            return 0;
        }

        global $wpdb;
        $table = self::table_name();
        $deleted_total = 0;

        foreach ($occurrences as $occurrence) {
            if (!is_array($occurrence)) {
                continue;
            }

            $start = isset($occurrence['start']) ? trim((string) $occurrence['start']) : '';
            $end = isset($occurrence['end']) ? trim((string) $occurrence['end']) : '';
            if ($start === '' || $end === '') {
                continue;
            }

            $deleted = $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$table} WHERE event_id = %d AND source = %s AND start_at = %s AND end_at = %s AND (generation_batch_id IS NULL OR generation_batch_id = '')",
                    $event_id,
                    self::SOURCE_GENERATED,
                    $start,
                    $end
                )
            );
            $deleted_total += max(0, (int) $deleted);
            error_log('[MjEventOccurrences::delete_generated_null_matching_rows] Deleted count=' . (string) $deleted . ' for event_id=' . $event_id . ', start=' . $start . ', end=' . $end);
        }

        return $deleted_total;
    }

    /**
     * Count generated rows with NULL/empty generation_batch_id for the given start/end pairs.
     *
     * @param int $event_id
     * @param array<int,array<string,mixed>> $occurrences
     * @return int
     */
    public static function count_generated_null_matching_rows($event_id, array $occurrences) {
        $event_id = (int) $event_id;
        if ($event_id <= 0 || empty($occurrences) || !self::table_ready() || !self::has_generation_batch_column()) {
            return 0;
        }

        global $wpdb;
        $table = self::table_name();
        $total = 0;

        foreach ($occurrences as $occurrence) {
            if (!is_array($occurrence)) {
                continue;
            }

            $start = isset($occurrence['start']) ? trim((string) $occurrence['start']) : '';
            $end = isset($occurrence['end']) ? trim((string) $occurrence['end']) : '';
            if ($start === '' || $end === '') {
                continue;
            }

            $count = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table} WHERE event_id = %d AND source = %s AND start_at = %s AND end_at = %s AND (generation_batch_id IS NULL OR generation_batch_id = '')",
                    $event_id,
                    self::SOURCE_GENERATED,
                    $start,
                    $end
                )
            );
            $total += max(0, $count);
        }

        return $total;
    }

    /**
     * Count all generated rows with NULL/empty generation_batch_id for an event.
     *
     * @param int $event_id
     * @return int
     */
    public static function count_generated_null_rows_for_event($event_id) {
        $event_id = (int) $event_id;
        if ($event_id <= 0 || !self::table_ready() || !self::has_generation_batch_column()) {
            return 0;
        }

        global $wpdb;
        $table = self::table_name();

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE event_id = %d AND source = %s AND (generation_batch_id IS NULL OR generation_batch_id = '')",
                $event_id,
                self::SOURCE_GENERATED
            )
        );
    }

    /**
     * Delete generated rows with NULL/empty generation_batch_id that do not match allowed start/end slots.
     *
     * @param int $event_id
     * @param array<int,string> $allowed_slot_keys format: "YYYY-mm-dd HH:ii:ss|YYYY-mm-dd HH:ii:ss"
     * @return int
     */
    public static function purge_orphan_generated_null_rows($event_id, array $allowed_slot_keys) {
        $event_id = (int) $event_id;
        if ($event_id <= 0 || !self::table_ready() || !self::has_generation_batch_column()) {
            return 0;
        }

        global $wpdb;
        $table = self::table_name();

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, start_at, end_at FROM {$table} WHERE event_id = %d AND source = %s AND (generation_batch_id IS NULL OR generation_batch_id = '')",
                $event_id,
                self::SOURCE_GENERATED
            ),
            ARRAY_A
        );
        if (!is_array($rows) || empty($rows)) {
            return 0;
        }

        $allowed_map = array();
        foreach ($allowed_slot_keys as $slot_key) {
            if (!is_string($slot_key) || $slot_key === '') {
                continue;
            }
            $allowed_map[$slot_key] = true;
        }

        $deleted_total = 0;
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $id = isset($row['id']) ? (int) $row['id'] : 0;
            $start = isset($row['start_at']) ? trim((string) $row['start_at']) : '';
            $end = isset($row['end_at']) ? trim((string) $row['end_at']) : '';
            if ($id <= 0 || $start === '' || $end === '') {
                continue;
            }

            $slot_key = $start . '|' . $end;
            if (isset($allowed_map[$slot_key])) {
                continue;
            }

            $deleted = $wpdb->delete($table, array('id' => $id), array('%d'));
            $deleted_total += max(0, (int) $deleted);
            error_log('[MjEventOccurrences::purge_orphan_generated_null_rows] Deleted orphan row id=' . $id . ' for event_id=' . $event_id . ', start=' . $start . ', end=' . $end);
        }

        return $deleted_total;
    }

    /**
     * @param int $event_id
     * @param string $generation_batch_id
     * @param string $status
     * @return void
     */
    public static function update_status_for_generation_batch($event_id, $generation_batch_id, $status) {
        $event_id = (int) $event_id;
        $batch_lookup = self::resolve_generation_batch_lookup($generation_batch_id);
        $generation_batch_storage_id = $batch_lookup['storage_id'];
        $generation_batch_uuid = $batch_lookup['uuid'];
        if ($event_id <= 0 || ($generation_batch_storage_id === null && $generation_batch_uuid === null) || !self::table_ready() || !self::support_status_column()) {
            return;
        }

        global $wpdb;
        $table = self::table_name();
        $normalized_status = self::normalize_status($status);

        if (self::support_generation_batch_column() && $generation_batch_storage_id !== null) {
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table} SET status = %s WHERE event_id = %d AND generation_batch_id = %s",
                    $normalized_status,
                    $event_id,
                    $generation_batch_storage_id
                )
            );
        }

        // Legacy fallback when generation_batch_id is stored inside the meta JSON payload.
        if ($generation_batch_uuid !== null) {
            $meta_key_like = '%' . $wpdb->esc_like('generation_batch_id') . '%';
            $meta_value_like = '%' . $wpdb->esc_like($generation_batch_uuid) . '%';

            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table} SET status = %s WHERE event_id = %d AND source = %s AND meta LIKE %s AND meta LIKE %s",
                    $normalized_status,
                    $event_id,
                    self::SOURCE_GENERATED,
                    $meta_key_like,
                    $meta_value_like
                )
            );
        }
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
            $inserted = $wpdb->insert($table, $row, $formats);
            if ($inserted === false) {
                error_log('[MjEventOccurrences::insert_rows] Insert failed: ' . $wpdb->last_error . ' | row=' . wp_json_encode($row));
            }
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

        $generation_batch_uuid_raw = null;
        if (isset($occurrence['generation_batch_uuid'])) {
            $generation_batch_uuid_raw = $occurrence['generation_batch_uuid'];
        } elseif (isset($occurrence['generationBatchUuid'])) {
            $generation_batch_uuid_raw = $occurrence['generationBatchUuid'];
        } elseif (isset($occurrence['generation_batch_id'])) {
            $generation_batch_uuid_raw = $occurrence['generation_batch_id'];
        } elseif (isset($occurrence['generationBatchId'])) {
            $generation_batch_uuid_raw = $occurrence['generationBatchId'];
        } elseif (isset($occurrence['batch_id'])) {
            $generation_batch_uuid_raw = $occurrence['batch_id'];
        }
        $generation_batch_ref_raw = null;
        if (isset($occurrence['generation_batch_ref'])) {
            $generation_batch_ref_raw = $occurrence['generation_batch_ref'];
        } elseif (isset($occurrence['generationBatchRef'])) {
            $generation_batch_ref_raw = $occurrence['generationBatchRef'];
        }

        $meta = self::normalize_meta(isset($occurrence['meta']) ? $occurrence['meta'] : null);
        if ($generation_batch_uuid_raw === null && isset($meta['generation_batch_id']) && $meta['generation_batch_id'] !== '') {
            $generation_batch_uuid_raw = $meta['generation_batch_id'];
        }
        if ($generation_batch_ref_raw === null && isset($meta['generation_batch_ref']) && $meta['generation_batch_ref'] !== '') {
            $generation_batch_ref_raw = $meta['generation_batch_ref'];
        }

        $generation_batch_uuid = self::normalize_generation_batch_id($generation_batch_uuid_raw);
        $generation_batch_storage_id = self::normalize_generation_batch_id($generation_batch_ref_raw);
        if ($generation_batch_storage_id === null && $generation_batch_uuid !== null) {
            $batch_lookup = self::resolve_generation_batch_lookup($generation_batch_uuid);
            $generation_batch_storage_id = $batch_lookup['storage_id'];
            $generation_batch_uuid = $batch_lookup['uuid'] !== null ? $batch_lookup['uuid'] : $generation_batch_uuid;
        }
        if ($source === self::SOURCE_GENERATED && $generation_batch_storage_id === null) {
            $inferred_batch_storage_id = self::infer_single_active_generation_batch_storage_id($event_id);
            if ($inferred_batch_storage_id !== null) {
                $generation_batch_storage_id = $inferred_batch_storage_id;
            }
        }

        $visibility = self::normalize_visibility(
            isset($occurrence['audience_visibility'])
                ? $occurrence['audience_visibility']
                : (isset($occurrence['visibility']) ? $occurrence['visibility'] : null)
        );

        if ($generation_batch_uuid !== null) {
            $meta['generation_batch_id'] = $generation_batch_uuid;
        }
        if ($generation_batch_storage_id !== null) {
            $meta['generation_batch_ref'] = $generation_batch_storage_id;
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

        if (self::has_generation_batch_column()) {
            $row['generation_batch_id'] = $generation_batch_storage_id;
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
    private static function has_generation_batch_column() {
        if (!self::table_ready()) {
            return false;
        }

        global $wpdb;
        $table = self::table_name();
        $column = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", 'generation_batch_id'));

        return !empty($column);
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
     * @return array{storage_id:?string,uuid:?string}
     */
    private static function resolve_generation_batch_lookup($value) {
        $token = self::normalize_generation_batch_id($value);
        if ($token === null) {
            return array('storage_id' => null, 'uuid' => null);
        }

        if (ctype_digit($token)) {
            $uuid = null;
            if (class_exists(MjEventOccurrenceGenerationBatches::class)) {
                $batch = MjEventOccurrenceGenerationBatches::find_by_id((int) $token);
                if (is_array($batch) && !empty($batch['batch_uuid'])) {
                    $uuid = self::normalize_generation_batch_id($batch['batch_uuid']);
                }
            }

            return array('storage_id' => $token, 'uuid' => $uuid);
        }

        $storage_id = null;
        if (class_exists(MjEventOccurrenceGenerationBatches::class)) {
            $batch = MjEventOccurrenceGenerationBatches::find_by_batch_uuid($token);
            if (is_array($batch) && isset($batch['id']) && (int) $batch['id'] > 0) {
                $storage_id = (string) (int) $batch['id'];
            }
        }

        return array('storage_id' => $storage_id, 'uuid' => $token);
    }

    /**
     * @param int $event_id
     * @return string|null
     */
    private static function infer_single_active_generation_batch_storage_id($event_id) {
        $event_id = (int) $event_id;
        if ($event_id <= 0 || !class_exists(MjEventOccurrenceGenerationBatches::class)) {
            return null;
        }

        $rows = MjEventOccurrenceGenerationBatches::get_for_event($event_id, false);
        if (!is_array($rows) || count($rows) !== 1) {
            return null;
        }

        $row = $rows[0];
        if (!is_array($row) || !isset($row['id']) || (int) $row['id'] <= 0) {
            return null;
        }

        return (string) (int) $row['id'];
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
