<?php

namespace Mj\Member\Classes\Crud;

if (!defined('ABSPATH')) {
    exit;
}

class MjEventVolunteers
{
    const TABLE = 'mj_event_volunteers';

    public static function is_ready()
    {
        self::ensure_schema();
        if (!function_exists('mj_member_get_event_volunteers_table_name') || !function_exists('mj_member_table_exists')) {
            return false;
        }

        $table = mj_member_get_event_volunteers_table_name();
        return mj_member_table_exists($table);
    }

    public static function get_table_name()
    {
        return function_exists('mj_member_get_event_volunteers_table_name') ? mj_member_get_event_volunteers_table_name() : '';
    }

    public static function get_ids_by_event($event_id)
    {
        $event_id = (int) $event_id;
        if ($event_id <= 0) {
            return array();
        }

        $ids = array();

        if (self::is_ready()) {
            global $wpdb;
            $table = self::get_table_name();
            $results = $wpdb->get_col($wpdb->prepare(
                "SELECT volunteer_id FROM {$table} WHERE event_id = %d ORDER BY id ASC",
                $event_id
            ));
            if (is_array($results)) {
                foreach ($results as $value) {
                    $value = (int) $value;
                    if ($value > 0) {
                        $ids[$value] = $value;
                    }
                }
            }
        }

        return array_values($ids);
    }

    public static function get_members_by_event($event_id)
    {
        $ids = self::get_ids_by_event($event_id);
        if (empty($ids)) {
            return array();
        }

        $table = MjMembers::getTableName(MjMembers::TABLE_NAME);
        global $wpdb;

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $query = $wpdb->prepare("SELECT * FROM {$table} WHERE id IN ($placeholders)", ...$ids);
        $results = $wpdb->get_results($query);
        if (empty($results)) {
            return array();
        }

        $indexed = array();
        foreach ($results as $row) {
            $indexed[(int) $row->id] = $row;
        }

        $ordered = array();
        foreach ($ids as $id) {
            if (isset($indexed[$id])) {
                $ordered[] = $indexed[$id];
            }
        }

        return $ordered;
    }

    public static function get_event_ids_for_member($volunteer_id)
    {
        $volunteer_id = (int) $volunteer_id;
        if ($volunteer_id <= 0) {
            return array();
        }

        self::ensure_schema();

        $event_ids = array();
        $member_row = MjMembers::getById($volunteer_id);
        $wp_user_id = ($member_row && isset($member_row->wp_user_id)) ? (int) $member_row->wp_user_id : 0;

        if (self::is_ready()) {
            global $wpdb;
            $table = self::get_table_name();
            $results = $wpdb->get_col($wpdb->prepare(
                "SELECT event_id FROM {$table} WHERE volunteer_id = %d ORDER BY event_id ASC",
                $volunteer_id
            ));
            if (is_array($results)) {
                foreach ($results as $value) {
                    $value = (int) $value;
                    if ($value > 0) {
                        $event_ids[$value] = $value;
                    }
                }
            }

            if ($wp_user_id > 0) {
                $legacy_results = $wpdb->get_col($wpdb->prepare(
                    "SELECT event_id FROM {$table} WHERE volunteer_id = %d ORDER BY event_id ASC",
                    $wp_user_id
                ));
                if (is_array($legacy_results)) {
                    foreach ($legacy_results as $value) {
                        $value = (int) $value;
                        if ($value > 0) {
                            $event_ids[$value] = $value;
                        }
                    }
                }
            }
        }

        return array_values($event_ids);
    }

    public static function get_events_for_member($volunteer_id, array $args = array())
    {
        $volunteer_id = (int) $volunteer_id;
        if ($volunteer_id <= 0) {
            return array();
        }

        $event_ids = self::get_event_ids_for_member($volunteer_id);
        if (empty($event_ids)) {
            return array();
        }

        global $wpdb;
        $events_table = mj_member_get_events_table_name();

        $order_column = 'date_debut';
        $order_direction = 'ASC';

        if (!empty($args['orderby'])) {
            $candidate = sanitize_key($args['orderby']);
            $allowed = array(
                'date_debut' => 'date_debut',
                'date_fin' => 'date_fin',
                'title' => 'title',
                'created_at' => 'created_at',
                'updated_at' => 'updated_at',
            );
            if (isset($allowed[$candidate])) {
                $order_column = $allowed[$candidate];
            }
        }

        if (!empty($args['order']) && strtoupper((string) $args['order']) === 'DESC') {
            $order_direction = 'DESC';
        }

        $placeholders = implode(',', array_fill(0, count($event_ids), '%d'));
        $query = "SELECT * FROM {$events_table} WHERE id IN ({$placeholders}) ORDER BY {$order_column} {$order_direction}";

        $params = $event_ids;
        array_unshift($params, $query);
        $prepared = call_user_func_array(array($wpdb, 'prepare'), $params);

        $rows = $wpdb->get_results($prepared);
        if (!is_array($rows)) {
            return array();
        }

        $status_filters = array();
        if (!empty($args['statuses']) && is_array($args['statuses'])) {
            foreach ($args['statuses'] as $status) {
                $status = sanitize_key($status);
                if ($status !== '') {
                    $status_filters[$status] = true;
                }
            }
        }

        if (!empty($status_filters)) {
            $rows = array_filter(
                $rows,
                static function ($row) use ($status_filters) {
                    if (!isset($row->status)) {
                        return false;
                    }
                    $status = sanitize_key((string) $row->status);
                    return isset($status_filters[$status]);
                }
            );
        }

        $rows = array_values($rows);

        if (!empty($args['limit'])) {
            $limit = (int) $args['limit'];
            if ($limit > 0 && count($rows) > $limit) {
                $rows = array_slice($rows, 0, $limit);
            }
        }

        return $rows;
    }

    public static function sync_for_event($event_id, array $volunteer_ids)
    {
        $event_id = (int) $event_id;
        if ($event_id <= 0) {
            return;
        }

        self::ensure_schema();

        $clean_ids = array();
        foreach ($volunteer_ids as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $clean_ids[$id] = $id;
            }
        }

        if (self::is_ready()) {
            global $wpdb;
            $table = self::get_table_name();
            $wpdb->delete($table, array('event_id' => $event_id), array('%d'));
            foreach ($clean_ids as $clean_id) {
                $wpdb->insert(
                    $table,
                    array(
                        'event_id' => $event_id,
                        'volunteer_id' => $clean_id,
                        'created_at' => current_time('mysql'),
                    ),
                    array('%d', '%d', '%s')
                );
            }
        }
    }

    public static function member_is_assigned($event_id, $member_id)
    {
        $event_id = (int) $event_id;
        $member_id = (int) $member_id;

        if ($event_id <= 0 || $member_id <= 0) {
            return false;
        }

        $assigned_ids = self::get_ids_by_event($event_id);
        if (!empty($assigned_ids) && in_array($member_id, $assigned_ids, true)) {
            return true;
        }

        $member_row = MjMembers::getById($member_id);
        $wp_user_id = ($member_row && isset($member_row->wp_user_id)) ? (int) $member_row->wp_user_id : 0;
        if ($wp_user_id > 0 && !empty($assigned_ids) && in_array($wp_user_id, $assigned_ids, true)) {
            return true;
        }

        return false;
    }

    private static function ensure_schema()
    {
        static $checked = false;

        if ($checked) {
            return;
        }
        $checked = true;

        if (!function_exists('mj_member_table_exists') || !function_exists('mj_member_get_event_volunteers_table_name')) {
            return;
        }

        global $wpdb;

        $pivot_table = mj_member_get_event_volunteers_table_name();

        if (!function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        if (function_exists('dbDelta')) {
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE {$pivot_table} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                event_id bigint(20) unsigned NOT NULL,
                volunteer_id bigint(20) unsigned NOT NULL,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                UNIQUE KEY uniq_event_volunteer (event_id, volunteer_id),
                KEY idx_event (event_id),
                KEY idx_volunteer (volunteer_id)
            ) {$charset_collate};";
            dbDelta($sql);
        }
    }
}

\class_alias(__NAMESPACE__ . '\\MjEventVolunteers', 'MjEventVolunteers');
