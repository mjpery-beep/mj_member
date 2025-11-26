<?php

if (!defined('ABSPATH')) {
    exit;
}

class MjEventClosures {
    const TABLE = 'mj_event_closures';

    /**
     * Ensure the closures table exists, create it on the fly if missing.
     *
     * @return bool
     */
    public static function ensure_table() {
        global $wpdb;

        $table = self::get_table_name();
        if (mj_member_table_exists($table)) {
            return true;
        }

        if (!function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            closure_date date NOT NULL,
            description varchar(190) DEFAULT '',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY uniq_closure_date (closure_date)
        ) {$charset_collate};";

        dbDelta($sql);

        return mj_member_table_exists($table);
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
                $where[] = 'closure_date >= %s';
                $params[] = $from;
            }
        }

        if (!empty($args['to'])) {
            $to = self::normalize_date($args['to']);
            if ($to !== false) {
                $where[] = 'closure_date <= %s';
                $params[] = $to;
            }
        }

        $sql = "SELECT * FROM {$table}";
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= " ORDER BY closure_date {$order}";
        if ($limit > 0) {
            $sql .= $wpdb->prepare(' LIMIT %d', $limit);
        }

        if (!empty($params)) {
            array_unshift($params, $sql);
            $prepared = call_user_func_array(array($wpdb, 'prepare'), $params);
        } else {
            $prepared = $sql;
        }

        return $wpdb->get_results($prepared);
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

        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE closure_date = %s", $date));
    }

    /**
     * @param string $date_string
     * @param string $description
     * @return int|WP_Error
     */
    public static function create($date_string, $description = '') {
        global $wpdb;
        $table = self::get_table_name();

        if (!self::ensure_table()) {
            return new WP_Error('mj_event_closure_missing_table', __('La table des fermetures est introuvable.', 'mj-member'));
        }

        $date = self::normalize_date($date_string);
        if ($date === false) {
            return new WP_Error('mj_event_closure_bad_date', __('Date de fermeture invalide.', 'mj-member'));
        }

        $description = sanitize_text_field($description);

        $existing = self::get_by_date($date);
        if ($existing) {
            return new WP_Error('mj_event_closure_duplicate', __('Une fermeture existe déjà pour cette date.', 'mj-member'));
        }

        $insert = $wpdb->insert(
            $table,
            array(
                'closure_date' => $date,
                'description' => $description,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ),
            array('%s', '%s', '%s', '%s')
        );

        if ($insert === false) {
            return new WP_Error('mj_event_closure_insert_failed', __('Impossible d\'ajouter cette fermeture.', 'mj-member'));
        }

        return (int) $wpdb->insert_id;
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
     * @param string $start
     * @param string $end
     * @return array<string,bool>
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

        $results = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT closure_date FROM {$table} WHERE closure_date BETWEEN %s AND %s",
                $start_norm,
                $end_norm
            )
        );

        if (empty($results)) {
            return array();
        }

        $map = array();
        foreach ($results as $date_value) {
            $map[$date_value] = true;
        }

        return $map;
    }
}
