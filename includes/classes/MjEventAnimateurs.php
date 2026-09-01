<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . 'MjMembers_CRUD.php';

class MjEventAnimateurs {
    const TABLE = 'mj_event_animateurs';

    public static function is_ready() {
        self::ensure_schema();
        if (!function_exists('mj_member_get_event_animateurs_table_name') || !function_exists('mj_member_table_exists')) {
            return false;
        }

        $table = mj_member_get_event_animateurs_table_name();
        return mj_member_table_exists($table);
    }

    public static function get_table_name() {
        return function_exists('mj_member_get_event_animateurs_table_name') ? mj_member_get_event_animateurs_table_name() : '';
    }

    public static function get_ids_by_event($event_id) {
        $event_id = (int) $event_id;
        if ($event_id <= 0) {
            return array();
        }

        $ids = array();

        if (self::is_ready()) {
            global $wpdb;
            $table = self::get_table_name();
            $results = $wpdb->get_col($wpdb->prepare(
                "SELECT animateur_id FROM {$table} WHERE event_id = %d ORDER BY id ASC",
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

        if (empty($ids) && function_exists('mj_member_column_exists')) {
            global $wpdb;
            $events_table = mj_member_get_events_table_name();
            if (mj_member_column_exists($events_table, 'animateur_id')) {
                $legacy_id = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT animateur_id FROM {$events_table} WHERE id = %d",
                    $event_id
                ));
                if ($legacy_id > 0) {
                    $ids[$legacy_id] = $legacy_id;
                }
            }
        }

        return array_values($ids);
    }

    public static function get_members_by_event($event_id) {
        $ids = self::get_ids_by_event($event_id);
        if (empty($ids)) {
            return array();
        }

        $table = MjMembers_CRUD::getTableName(MjMembers_CRUD::TABLE_NAME);
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

    public static function sync_for_event($event_id, array $animateur_ids) {
        $event_id = (int) $event_id;
        if ($event_id <= 0) {
            return;
        }

        self::ensure_schema();

        $clean_ids = array();
        foreach ($animateur_ids as $id) {
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
                        'animateur_id' => $clean_id,
                        'created_at' => current_time('mysql'),
                    ),
                    array('%d', '%d', '%s')
                );
            }
        }

        self::sync_primary_column($event_id, $clean_ids);
    }

    private static function sync_primary_column($event_id, array $animateur_ids) {
        if (!function_exists('mj_member_column_exists')) {
            return;
        }

        self::ensure_schema();

        $events_table = mj_member_get_events_table_name();
        if (!mj_member_column_exists($events_table, 'animateur_id')) {
            return;
        }

        global $wpdb;
        if (empty($animateur_ids)) {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$events_table} SET animateur_id = NULL WHERE id = %d",
                $event_id
            ));
            return;
        }

        $first = reset($animateur_ids);
        $wpdb->update(
            $events_table,
            array('animateur_id' => (int) $first),
            array('id' => $event_id),
            array('%d'),
            array('%d')
        );
    }

    private static function ensure_schema() {
        static $checked = false;

        if ($checked) {
            return;
        }
        $checked = true;

        if (!function_exists('mj_member_table_exists') || !function_exists('mj_member_get_event_animateurs_table_name')) {
            return;
        }

        global $wpdb;

        $pivot_table = mj_member_get_event_animateurs_table_name();
        $events_table = mj_member_get_events_table_name();

        if (!function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        if (function_exists('dbDelta')) {
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE {$pivot_table} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                event_id bigint(20) unsigned NOT NULL,
                animateur_id bigint(20) unsigned NOT NULL,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                UNIQUE KEY uniq_event_animateur (event_id, animateur_id),
                KEY idx_event (event_id),
                KEY idx_animateur (animateur_id)
            ) {$charset_collate};";
            dbDelta($sql);
        }

        if (mj_member_table_exists($events_table) && function_exists('mj_member_column_exists') && !mj_member_column_exists($events_table, 'animateur_id')) {
            $wpdb->query("ALTER TABLE {$events_table} ADD COLUMN animateur_id bigint(20) unsigned DEFAULT NULL AFTER location_id");
        }

        if (mj_member_table_exists($events_table) && function_exists('mj_member_index_exists') && !mj_member_index_exists($events_table, 'idx_animateur')) {
            $wpdb->query("ALTER TABLE {$events_table} ADD KEY idx_animateur (animateur_id)");
        }

        if (mj_member_table_exists($events_table) && mj_member_column_exists($events_table, 'animateur_id') && mj_member_table_exists($pivot_table)) {
            $migration_flag = get_option('mj_member_event_animateurs_migrated', '0');
            if ($migration_flag !== '1') {
                $existing_pairs = $wpdb->get_results("SELECT id AS event_id, animateur_id FROM {$events_table} WHERE animateur_id IS NOT NULL AND animateur_id > 0", ARRAY_A);
                if (!empty($existing_pairs)) {
                    foreach ($existing_pairs as $pair) {
                        $event_id = (int) $pair['event_id'];
                        $animateur_id = (int) $pair['animateur_id'];
                        if ($event_id <= 0 || $animateur_id <= 0) {
                            continue;
                        }
                        $wpdb->query($wpdb->prepare(
                            "INSERT IGNORE INTO {$pivot_table} (event_id, animateur_id, created_at) VALUES (%d, %d, %s)",
                            $event_id,
                            $animateur_id,
                            current_time('mysql')
                        ));
                    }
                }
                update_option('mj_member_event_animateurs_migrated', '1', false);
            }
        }
    }
}
