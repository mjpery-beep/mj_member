<?php

namespace Mj\Member\Classes\Crud;

use DateTime;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class MjEventClosures implements CrudRepositoryInterface {
    const TABLE = 'mj_event_closures';

    /**
     * Ensure the closures table exists, create it on the fly if missing.
     *
     * @return bool
     */
    public static function ensure_table() {
        global $wpdb;

        $table = self::get_table_name();
        if (!mj_member_table_exists($table)) {
            if (!function_exists('dbDelta')) {
                require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            }

            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE {$table} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                start_date date NOT NULL,
                end_date date NOT NULL,
                cover_id bigint(20) unsigned DEFAULT NULL,
                description varchar(190) DEFAULT '',
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                UNIQUE KEY idx_closure_range (start_date, end_date),
                KEY idx_closure_start (start_date),
                KEY idx_closure_end (end_date)
            ) {$charset_collate};";

            dbDelta($sql);
        }

        self::maybe_upgrade_structure($table);

        return mj_member_table_exists($table);
    }

    /**
     * @param string $table
     * @return void
     */
    private static function maybe_upgrade_structure($table) {
        global $wpdb;

        if (!mj_member_table_exists($table)) {
            return;
        }

        $has_start = mj_member_column_exists($table, 'start_date');
        $has_legacy = mj_member_column_exists($table, 'closure_date');

        if (!$has_start && $has_legacy) {
            $wpdb->query("ALTER TABLE {$table} CHANGE COLUMN closure_date start_date date NOT NULL");
            $has_start = mj_member_column_exists($table, 'start_date');
        }

        if (!$has_start) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN start_date date NOT NULL AFTER id");
        }

        if (!mj_member_column_exists($table, 'end_date')) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN end_date date NOT NULL AFTER start_date");
            $wpdb->query("UPDATE {$table} SET end_date = start_date WHERE end_date IS NULL OR end_date = '0000-00-00'");
        }

        if (!mj_member_column_exists($table, 'cover_id')) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN cover_id bigint(20) unsigned DEFAULT NULL AFTER end_date");
        }

        if (mj_member_index_exists($table, 'uniq_closure_date')) {
            $wpdb->query("ALTER TABLE {$table} DROP INDEX uniq_closure_date");
        }

        if (!mj_member_index_exists($table, 'idx_closure_range')) {
            $wpdb->query("ALTER TABLE {$table} ADD UNIQUE KEY idx_closure_range (start_date, end_date)");
        }

        if (!mj_member_index_exists($table, 'idx_closure_start')) {
            $wpdb->query("ALTER TABLE {$table} ADD KEY idx_closure_start (start_date)");
        }

        if (!mj_member_index_exists($table, 'idx_closure_end')) {
            $wpdb->query("ALTER TABLE {$table} ADD KEY idx_closure_end (end_date)");
        }
    }

    /**
     * @return string
     */
    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE;
    }

    /**
     * @param string $date_string
     * @return string|false
     */
    protected static function normalize_date($date_string) {
        $date_string = trim((string) $date_string);
        if ($date_string === '') {
            return false;
        }

        $date = DateTime::createFromFormat('Y-m-d', $date_string, wp_timezone());
        if ($date instanceof DateTime) {
            return $date->format('Y-m-d');
        }

        $timestamp = strtotime($date_string);
        if ($timestamp === false) {
            return false;
        }

        return gmdate('Y-m-d', $timestamp);
    }

    /**
     * @param array<string,mixed> $args
     * @return array<int,object>
     */
    public static function get_all($args = array()) {
        global $wpdb;
        $table = self::get_table_name();

        if (!self::ensure_table()) {
            return array();
        }

        $defaults = array(
            'order' => 'ASC',
            'limit' => 0,
            'from' => '',
            'to' => '',
        );
        $args = wp_parse_args($args, $defaults);

        $order = strtoupper($args['order']) === 'DESC' ? 'DESC' : 'ASC';
        $limit = (int) $args['limit'];
        if ($limit < 0) {
            $limit = 0;
        }

        $where = array();
        $params = array();

        if (!empty($args['from'])) {
            $from = self::normalize_date($args['from']);
            if ($from !== false) {
                $where[] = 'end_date >= %s';
                $params[] = $from;
            }
        }

        if (!empty($args['to'])) {
            $to = self::normalize_date($args['to']);
            if ($to !== false) {
                $where[] = 'start_date <= %s';
                $params[] = $to;
            }
        }

        $sql = "SELECT *, start_date AS closure_date FROM {$table}";
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= " ORDER BY start_date {$order}";

        if (!empty($params)) {
            array_unshift($params, $sql);
            $prepared = call_user_func_array(array($wpdb, 'prepare'), $params);
        } else {
            $prepared = $sql;
        }

        if ($limit > 0) {
            $prepared .= ' LIMIT ' . (int) $limit;
        }

        $results = $wpdb->get_results($prepared);
        if (empty($results)) {
            return array();
        }

        $normalized = array_map(static function ($row) {
            return self::normalize_row($row);
        }, $results);

        return array_values(array_filter($normalized));
    }

    /**
     * @param array<string,mixed> $args
     * @return int
     */
    public static function count($args = array()) {
        global $wpdb;
        $table = self::get_table_name();

        if (!self::ensure_table()) {
            return 0;
        }

        $defaults = array(
            'from' => '',
            'to' => '',
        );
        $args = wp_parse_args($args, $defaults);

        $where = array();
        $params = array();

        if (!empty($args['from'])) {
            $from = self::normalize_date($args['from']);
            if ($from !== false) {
                $where[] = 'end_date >= %s';
                $params[] = $from;
            }
        }

        if (!empty($args['to'])) {
            $to = self::normalize_date($args['to']);
            if ($to !== false) {
                $where[] = 'start_date <= %s';
                $params[] = $to;
            }
        }

        $sql = "SELECT COUNT(*) FROM {$table}";
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        if (!empty($params)) {
            array_unshift($params, $sql);
            $prepared = call_user_func_array(array($wpdb, 'prepare'), $params);
        } else {
            $prepared = $sql;
        }

        return (int) $wpdb->get_var($prepared);
    }

    /**
     * @param string $date_string
     * @return object|null
     */
    public static function get_by_date($date_string) {
        global $wpdb;
        $table = self::get_table_name();

        if (!self::ensure_table()) {
            return null;
        }

        $date = self::normalize_date($date_string);
        if ($date === false) {
            return null;
        }

        $sql = $wpdb->prepare(
            "SELECT *, start_date AS closure_date FROM {$table} WHERE start_date <= %s AND end_date >= %s ORDER BY start_date ASC LIMIT 1",
            $date,
            $date
        );

        $row = $wpdb->get_row($sql);

        return self::normalize_row($row);
    }

    /**
     * @param array<string,mixed>|string $data
     * @return int|WP_Error
     */
    public static function create($data) {
        global $wpdb;
        $table = self::get_table_name();

        if (!self::ensure_table()) {
            return new WP_Error('mj_event_closure_missing_table', __('La table des fermetures est introuvable.', 'mj-member'));
        }

        $payload = self::normalize_payload($data);
        if (is_wp_error($payload)) {
            return $payload;
        }

        list($start, $end, $cover_id, $description) = $payload;
        if ($start === false || $end === false) {
            return new WP_Error('mj_event_closure_bad_date', __('Plage de fermeture invalide.', 'mj-member'));
        }

        $overlap = self::find_overlapping_range($start, $end);
        if ($overlap) {
            return new WP_Error('mj_event_closure_overlap', __('Une fermeture existe déjà sur cette période.', 'mj-member'));
        }

        $insert = $wpdb->insert(
            $table,
            array(
                'start_date' => $start,
                'end_date' => $end,
                'description' => $description,
                'cover_id' => $cover_id,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ),
            array('%s', '%s', '%s', '%d', '%s', '%s')
        );

        if ($insert === false) {
            return new WP_Error('mj_event_closure_insert_failed', __('Impossible d\'ajouter cette fermeture.', 'mj-member'));
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * @param int $id
     * @param array<string,mixed>|string $data
     * @return true|WP_Error
     */
    public static function update($id, $data) {
        global $wpdb;
        $table = self::get_table_name();

        if (!self::ensure_table()) {
            return new WP_Error('mj_event_closure_missing_table', __('La table des fermetures est introuvable.', 'mj-member'));
        }

        $id = (int) $id;
        if ($id <= 0) {
            return new WP_Error('mj_event_closure_invalid_id', __('Identifiant de fermeture invalide.', 'mj-member'));
        }

        $current = self::get_by_id($id);
        if (!$current) {
            return new WP_Error('mj_event_closure_missing', __('Cette fermeture est introuvable.', 'mj-member'));
        }

        if (!is_array($data)) {
            $data = array('start_date' => $data);
        }

        $update_data = array();
        $formats = array();

        $start_candidate = null;
        if (array_key_exists('start_date', $data)) {
            $start_candidate = $data['start_date'];
        } elseif (array_key_exists('closure_date', $data)) {
            $start_candidate = $data['closure_date'];
        } elseif (array_key_exists('date', $data)) {
            $start_candidate = $data['date'];
        } elseif (array_key_exists('from', $data)) {
            $start_candidate = $data['from'];
        } elseif (array_key_exists('closure_start', $data)) {
            $start_candidate = $data['closure_start'];
        }

        $end_candidate = null;
        if (array_key_exists('end_date', $data)) {
            $end_candidate = $data['end_date'];
        } elseif (array_key_exists('closure_end', $data)) {
            $end_candidate = $data['closure_end'];
        } elseif (array_key_exists('to', $data)) {
            $end_candidate = $data['to'];
        }

        if ($start_candidate !== null || $end_candidate !== null) {
            $normalized_start = $start_candidate !== null ? self::normalize_date($start_candidate) : self::normalize_date($current->start_date);
            $normalized_end = $end_candidate !== null ? self::normalize_date($end_candidate) : self::normalize_date($current->end_date);

            if ($normalized_start === false || $normalized_end === false) {
                return new WP_Error('mj_event_closure_bad_date', __('Plage de fermeture invalide.', 'mj-member'));
            }

            if ($normalized_end < $normalized_start) {
                return new WP_Error('mj_event_closure_bad_range', __('La date de fin doit être postérieure ou égale à la date de début.', 'mj-member'));
            }

            $overlap = self::find_overlapping_range($normalized_start, $normalized_end, $id);
            if ($overlap) {
                return new WP_Error('mj_event_closure_overlap', __('Une fermeture existe déjà sur cette période.', 'mj-member'));
            }

            $update_data['start_date'] = $normalized_start;
            $formats[] = '%s';
            $update_data['end_date'] = $normalized_end;
            $formats[] = '%s';
        }

        if (array_key_exists('description', $data)) {
            $update_data['description'] = sanitize_text_field((string) $data['description']);
            $formats[] = '%s';
        } elseif (array_key_exists('closure_description', $data)) {
            $update_data['description'] = sanitize_text_field((string) $data['closure_description']);
            $formats[] = '%s';
        }

        if (array_key_exists('cover_id', $data)) {
            $update_data['cover_id'] = max(0, (int) $data['cover_id']);
            $formats[] = '%d';
        } elseif (array_key_exists('closure_cover_id', $data)) {
            $update_data['cover_id'] = max(0, (int) $data['closure_cover_id']);
            $formats[] = '%d';
        }

        if (empty($update_data)) {
            return true;
        }

        $update_data['updated_at'] = current_time('mysql');
        $formats[] = '%s';

        $updated = $wpdb->update($table, $update_data, array('id' => $id), $formats, array('%d'));
        if ($updated === false) {
            return new WP_Error('mj_event_closure_update_failed', __('Impossible de mettre à jour cette fermeture.', 'mj-member'));
        }

        return true;
    }

    /**
     * @param int $id
     * @return true|WP_Error
     */
    public static function delete($id) {
        global $wpdb;
        $table = self::get_table_name();

        if (!self::ensure_table()) {
            return new WP_Error('mj_event_closure_missing_table', __('La table des fermetures est introuvable.', 'mj-member'));
        }

        $id = (int) $id;
        if ($id <= 0) {
            return new WP_Error('mj_event_closure_invalid_id', __('Identifiant de fermeture invalide.', 'mj-member'));
        }

        $deleted = $wpdb->delete($table, array('id' => $id), array('%d'));
        if ($deleted === false) {
            return new WP_Error('mj_event_closure_delete_failed', __('Impossible de supprimer cette fermeture.', 'mj-member'));
        }

        return true;
    }

    /**
     * @param int $id
     * @return object|null
     */
    public static function get_by_id($id) {
        global $wpdb;
        $table = self::get_table_name();

        if (!self::ensure_table()) {
            return null;
        }

        $id = (int) $id;
        if ($id <= 0) {
            return null;
        }

        $row = $wpdb->get_row($wpdb->prepare("SELECT *, start_date AS closure_date FROM {$table} WHERE id = %d", $id));

        return self::normalize_row($row);
    }

    /**
     * @param string $start
     * @param string $end
     * @return array<string,array<string,string>>
     */
    public static function get_dates_map_between($start, $end) {
        $start_norm = self::normalize_date($start);
        $end_norm = self::normalize_date($end);
        if ($start_norm === false || $end_norm === false) {
            return array();
        }

        global $wpdb;
        $table = self::get_table_name();
        if (!self::ensure_table()) {
            return array();
        }

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, start_date, end_date, cover_id, description FROM {$table} WHERE start_date <= %s AND end_date >= %s",
                $end_norm,
                $start_norm
            )
        );

        if (empty($results)) {
            return array();
        }

        $map = array();
        $timezone = wp_timezone();

        foreach ($results as $entry) {
            $entry = self::normalize_row($entry);
            if (!is_object($entry) || empty($entry->start_date)) {
                continue;
            }

            $range_start = $entry->start_date;
            $range_end = !empty($entry->end_date) ? $entry->end_date : $entry->start_date;

            $effective_start = $range_start < $start_norm ? $start_norm : $range_start;
            $effective_end = $range_end > $end_norm ? $end_norm : $range_end;

            try {
                $current = new DateTime($effective_start, $timezone);
                $end_date = new DateTime($effective_end, $timezone);
            } catch (\Exception $exception) {
                continue;
            }

            $description = '';
            if (isset($entry->description)) {
                $description = sanitize_text_field((string) $entry->description);
            }

            $cover_id = isset($entry->cover_id) ? (int) $entry->cover_id : 0;
            $cover_full = '';
            $cover_thumb = '';
            if ($cover_id > 0) {
                if (wp_attachment_is_image($cover_id)) {
                    $cover_full = wp_get_attachment_image_url($cover_id, 'large');
                    if (!$cover_full) {
                        $cover_full = wp_get_attachment_url($cover_id);
                    }
                    $cover_thumb = wp_get_attachment_image_url($cover_id, 'medium');
                    if (!$cover_thumb) {
                        $cover_thumb = $cover_full;
                    }
                } else {
                    $cover_full = wp_get_attachment_url($cover_id);
                    $cover_thumb = $cover_full;
                }
            }

            while ((int) $current->format('Ymd') <= (int) $end_date->format('Ymd')) {
                $day_key = $current->format('Y-m-d');
                $map[$day_key] = array(
                    'description' => $description,
                    'start_date' => $entry->start_date,
                    'end_date' => $entry->end_date,
                    'closure_id' => isset($entry->id) ? (int) $entry->id : 0,
                    'cover_id' => $cover_id,
                    'cover_full' => $cover_full ? esc_url_raw($cover_full) : '',
                    'cover_thumb' => $cover_thumb ? esc_url_raw($cover_thumb) : '',
                );
                $current->modify('+1 day');
            }
        }

        return $map;
    }

    /**
     * @param array<string,mixed>|string $data
     * @return array{0: string|false, 1: string}|WP_Error
     */
    private static function find_overlapping_range($start, $end, $exclude_id = 0) {
        global $wpdb;
        $table = self::get_table_name();

        if (!self::ensure_table()) {
            return null;
        }

        $conditions = array('start_date <= %s', 'end_date >= %s');
        $params = array($end, $start);

        $exclude_id = (int) $exclude_id;
        if ($exclude_id > 0) {
            $conditions[] = 'id <> %d';
            $params[] = $exclude_id;
        }

        $sql = "SELECT id, start_date, end_date FROM {$table} WHERE " . implode(' AND ', $conditions) . ' LIMIT 1';
        array_unshift($params, $sql);

        $prepared = call_user_func_array(array($wpdb, 'prepare'), $params);
        $row = $wpdb->get_row($prepared);

        return self::normalize_row($row);
    }

    /**
     * @param mixed $row
     * @return object|null
     */
    private static function normalize_row($row) {
        if (empty($row)) {
            return null;
        }

        if (is_array($row)) {
            $row = (object) $row;
        }

        if (!is_object($row)) {
            return null;
        }

        if (!isset($row->start_date) && isset($row->closure_date)) {
            $row->start_date = $row->closure_date;
        }

        if (!isset($row->closure_date) && isset($row->start_date)) {
            $row->closure_date = $row->start_date;
        }

        if (!isset($row->end_date) || $row->end_date === null || $row->end_date === '') {
            $row->end_date = isset($row->start_date) ? $row->start_date : '';
        }

        if (!isset($row->cover_id) || $row->cover_id === null) {
            $row->cover_id = 0;
        } else {
            $row->cover_id = (int) $row->cover_id;
        }

        return $row;
    }

    /**
     * @param array<string,mixed>|string $data
     * @return array{0: string|false, 1: string|false, 2: int, 3: string}|WP_Error
     */
    protected static function normalize_payload($data) {
        if (is_array($data)) {
            $start = null;
            if (array_key_exists('start_date', $data)) {
                $start = $data['start_date'];
            } elseif (array_key_exists('closure_start', $data)) {
                $start = $data['closure_start'];
            } elseif (array_key_exists('closure_date', $data)) {
                $start = $data['closure_date'];
            } elseif (array_key_exists('date', $data)) {
                $start = $data['date'];
            } elseif (array_key_exists('from', $data)) {
                $start = $data['from'];
            }

            if ($start === null) {
                return new WP_Error('mj_event_closure_missing_date', __('Date de début manquante.', 'mj-member'));
            }

            $end = null;
            if (array_key_exists('end_date', $data)) {
                $end = $data['end_date'];
            } elseif (array_key_exists('closure_end', $data)) {
                $end = $data['closure_end'];
            } elseif (array_key_exists('to', $data)) {
                $end = $data['to'];
            }

            if ($end === null) {
                $end = $start;
            }

            $normalized_start = self::normalize_date($start);
            $normalized_end = self::normalize_date($end);

            if ($normalized_start === false || $normalized_end === false) {
                return new WP_Error('mj_event_closure_bad_date', __('Plage de fermeture invalide.', 'mj-member'));
            }

            if ($normalized_end < $normalized_start) {
                return new WP_Error('mj_event_closure_bad_range', __('La date de fin doit être postérieure ou égale à la date de début.', 'mj-member'));
            }

            $cover_id = 0;
            if (isset($data['cover_id'])) {
                $cover_id = (int) $data['cover_id'];
            } elseif (isset($data['closure_cover_id'])) {
                $cover_id = (int) $data['closure_cover_id'];
            }
            if ($cover_id < 0) {
                $cover_id = 0;
            }

            $description = '';
            if (isset($data['description'])) {
                $description = $data['description'];
            } elseif (isset($data['closure_description'])) {
                $description = $data['closure_description'];
            }

            return array($normalized_start, $normalized_end, $cover_id, sanitize_text_field((string) $description));
        }

        if (is_scalar($data)) {
            $date = self::normalize_date($data);
            if ($date === false) {
                return new WP_Error('mj_event_closure_bad_date', __('Date de fermeture invalide.', 'mj-member'));
            }

            return array($date, $date, 0, '');
        }

        return new WP_Error('mj_event_closure_invalid_payload', __('Format de données invalide pour la fermeture.', 'mj-member'));
    }
}

class_alias(__NAMESPACE__ . '\\MjEventClosures', 'MjEventClosures');
