<?php

if (!defined('ABSPATH')) {
    exit;
}

class MjEventLocations {
    const TABLE = 'mj_event_locations';

    /**
     * @return string
     */
    private static function table_name() {
        return mj_member_get_event_locations_table_name();
    }

    /**
     * @param array<string, mixed> $args
     * @return array<int, object>
     */
    public static function get_all($args = array()) {
        global $wpdb;
        $table = self::table_name();

        $orderby = isset($args['orderby']) ? sanitize_key($args['orderby']) : 'name';
        $order = isset($args['order']) ? strtoupper(sanitize_text_field($args['order'])) : 'ASC';
        $order = $order === 'DESC' ? 'DESC' : 'ASC';

        $allowed_orderby = array('name', 'city', 'created_at');
        if (!in_array($orderby, $allowed_orderby, true)) {
            $orderby = 'name';
        }

        $where_parts = array();
        $where_values = array();

        if (!empty($args['search'])) {
            $search = sanitize_text_field($args['search']);
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where_parts[] = '(name LIKE %s OR city LIKE %s OR address_line LIKE %s)';
            $where_values[] = $like;
            $where_values[] = $like;
            $where_values[] = $like;
        }

        $where_sql = '';
        if (!empty($where_parts)) {
            $where_sql = 'WHERE ' . implode(' AND ', $where_parts);
        }

        $sql = "SELECT * FROM {$table} {$where_sql} ORDER BY {$orderby} {$order}";
        if (!empty($where_values)) {
            array_unshift($where_values, $sql);
            $sql = call_user_func_array(array($wpdb, 'prepare'), $where_values);
        }

        return $wpdb->get_results($sql);
    }

    /**
     * @param int $location_id
     * @return object|null
     */
    public static function find($location_id) {
        $location_id = (int) $location_id;
        if ($location_id <= 0) {
            return null;
        }

        global $wpdb;
        $table = self::table_name();
        $sql = $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $location_id);
        return $wpdb->get_row($sql);
    }

    /**
     * @param string $slug
     * @return object|null
     */
    public static function find_by_slug($slug) {
        $slug = sanitize_title($slug);
        if ($slug === '') {
            return null;
        }

        global $wpdb;
        $table = self::table_name();
        $sql = $wpdb->prepare("SELECT * FROM {$table} WHERE slug = %s", $slug);
        return $wpdb->get_row($sql);
    }

    /**
     * @return array<string, mixed>
     */
    public static function get_default_values() {
        return array(
            'name' => '',
            'slug' => '',
            'address_line' => '',
            'postal_code' => '',
            'city' => '',
            'country' => 'Belgique',
            'latitude' => '',
            'longitude' => '',
            'map_query' => '',
            'cover_id' => 0,
            'notes' => '',
        );
    }

    /**
     * @param array<string, mixed> $data
     * @return int|WP_Error
     */
    public static function create(array $data) {
        global $wpdb;
        $table = self::table_name();

        $prepared = self::prepare_for_db($data, 0);
        if (is_wp_error($prepared)) {
            return $prepared;
        }

        list($insert_data, $formats) = $prepared;
        $insert_data['created_at'] = current_time('mysql');
        $insert_data['updated_at'] = current_time('mysql');

        $result = $wpdb->insert($table, $insert_data, $formats);
        if ($result === false) {
            return new WP_Error('mj_event_location_insert_failed', 'Impossible de creer ce lieu.');
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * @param int $location_id
     * @param array<string, mixed> $data
     * @return true|WP_Error
     */
    public static function update($location_id, array $data) {
        $location_id = (int) $location_id;
        if ($location_id <= 0) {
            return new WP_Error('mj_event_location_invalid_id', 'Identifiant de lieu invalide.');
        }

        global $wpdb;
        $table = self::table_name();

        $current = self::find($location_id);
        if (!$current) {
            return new WP_Error('mj_event_location_missing', 'Lieu introuvable.');
        }

        $prepared = self::prepare_for_db($data, $location_id, $current);
        if (is_wp_error($prepared)) {
            return $prepared;
        }

        list($update_data, $formats) = $prepared;
        if (empty($update_data)) {
            return true;
        }

        $update_data['updated_at'] = current_time('mysql');
        $formats[] = '%s';

        $result = $wpdb->update($table, $update_data, array('id' => $location_id), $formats, array('%d'));
        if ($result === false) {
            return new WP_Error('mj_event_location_update_failed', 'Impossible de mettre a jour ce lieu.');
        }

        return true;
    }

    /**
     * @param int $location_id
     * @return true|WP_Error
     */
    public static function delete($location_id) {
        $location_id = (int) $location_id;
        if ($location_id <= 0) {
            return new WP_Error('mj_event_location_invalid_id', 'Identifiant de lieu invalide.');
        }

        global $wpdb;
        $events_table = mj_member_get_events_table_name();
        $in_use = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$events_table} WHERE location_id = %d", $location_id));
        if ($in_use > 0) {
            return new WP_Error('mj_event_location_in_use', 'Ce lieu est associe a au moins un evenement.');
        }

        $table = self::table_name();
        $deleted = $wpdb->delete($table, array('id' => $location_id), array('%d'));
        if ($deleted === false) {
            return new WP_Error('mj_event_location_delete_failed', 'Suppression impossible.');
        }

        return true;
    }

    /**
     * @param object|array<string, mixed>|null $location
     * @return string
     */
    public static function build_map_embed_src($location) {
        if (empty($location)) {
            return '';
        }

        $location = (array) $location;
        $query = '';

        if (!empty($location['map_query'])) {
            $query = $location['map_query'];
        } elseif (!empty($location['latitude']) && !empty($location['longitude'])) {
            $query = trim($location['latitude']) . ',' . trim($location['longitude']);
        } else {
            $parts = array();
            if (!empty($location['address_line'])) {
                $parts[] = $location['address_line'];
            }
            if (!empty($location['postal_code'])) {
                $parts[] = $location['postal_code'];
            }
            if (!empty($location['city'])) {
                $parts[] = $location['city'];
            }
            if (!empty($location['country'])) {
                $parts[] = $location['country'];
            }
            if (!empty($parts)) {
                $query = implode(', ', $parts);
            }
        }

        if ($query === '') {
            return '';
        }

        return 'https://www.google.com/maps?q=' . rawurlencode($query) . '&output=embed';
    }

    /**
     * @param object|array<string, mixed>|null $location
     * @return string
     */
    public static function format_address($location) {
        if (empty($location)) {
            return '';
        }

        $location = (array) $location;
        $parts = array();
        if (!empty($location['address_line'])) {
            $parts[] = $location['address_line'];
        }
        $city_parts = array();
        if (!empty($location['postal_code'])) {
            $city_parts[] = $location['postal_code'];
        }
        if (!empty($location['city'])) {
            $city_parts[] = $location['city'];
        }
        if (!empty($city_parts)) {
            $parts[] = implode(' ', $city_parts);
        }
        if (!empty($location['country'])) {
            $parts[] = $location['country'];
        }

        return implode(', ', $parts);
    }

    /**
     * @param array<string, mixed> $data
     * @param int $location_id
     * @param object|null $current
     * @return array{0: array<string, mixed>, 1: array<int, string>}|WP_Error
     */
    private static function prepare_for_db(array $data, $location_id = 0, $current = null) {
        $columns = array(
            'slug' => '%s',
            'name' => '%s',
            'address_line' => '%s',
            'postal_code' => '%s',
            'city' => '%s',
            'country' => '%s',
            'latitude' => '%f',
            'longitude' => '%f',
            'map_query' => '%s',
            'cover_id' => '%d',
            'notes' => '%s',
        );

        $prepared = array();
        $formats = array();

        $name = isset($data['name']) ? sanitize_text_field($data['name']) : '';
        if ($location_id === 0 && $name === '') {
            return new WP_Error('mj_event_location_missing_name', 'Le nom du lieu est obligatoire.');
        }

        if ($name !== '') {
            $prepared['name'] = $name;
            $formats[] = $columns['name'];
        }

        $slug_source = isset($data['slug']) ? sanitize_title($data['slug']) : '';
        if ($slug_source === '' && $name !== '') {
            $slug_source = sanitize_title($name);
        }

        if ($slug_source !== '') {
            $unique_slug = self::generate_unique_slug($slug_source, $location_id);
            if ($unique_slug === '') {
                return new WP_Error('mj_event_location_slug_failed', 'Impossible de generer un identifiant unique pour ce lieu.');
            }
            $prepared['slug'] = $unique_slug;
            $formats[] = $columns['slug'];
        }

        $string_fields = array('address_line', 'postal_code', 'city', 'country', 'map_query');
        foreach ($string_fields as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            $value = sanitize_text_field($data[$field]);
            if ($value === '' && in_array($field, array('map_query'), true)) {
                $value = null;
            }
            if ($value === null) {
                $prepared[$field] = null;
            } else {
                $prepared[$field] = $value;
            }
            $formats[] = $columns[$field];
        }

        $float_fields = array('latitude', 'longitude');
        foreach ($float_fields as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            $value_raw = $data[$field];
            if ($value_raw === '' || $value_raw === null) {
                $prepared[$field] = null;
                $formats[] = $columns[$field];
                continue;
            }
            $value = floatval(str_replace(',', '.', (string) $value_raw));
            $prepared[$field] = $value;
            $formats[] = $columns[$field];
        }

        if (array_key_exists('cover_id', $data)) {
            $cover_id = (int) $data['cover_id'];
            if ($cover_id <= 0) {
                $prepared['cover_id'] = null;
            } else {
                $prepared['cover_id'] = $cover_id;
            }
            $formats[] = $columns['cover_id'];
        }

        if (array_key_exists('notes', $data)) {
            $prepared['notes'] = sanitize_textarea_field($data['notes']);
            $formats[] = $columns['notes'];
        }

        return array($prepared, $formats);
    }

    /**
     * @param string $slug
     * @param int $exclude_id
     * @return string
     */
    private static function generate_unique_slug($slug, $exclude_id = 0) {
        $slug = sanitize_title($slug);
        if ($slug === '') {
            return '';
        }

        global $wpdb;
        $table = self::table_name();
        $base_slug = $slug;
        $suffix = 1;

        while (true) {
            $query = $wpdb->prepare("SELECT id FROM {$table} WHERE slug = %s" . ($exclude_id > 0 ? ' AND id <> %d' : ''), $slug, $exclude_id);
            $found_id = $wpdb->get_var($query);
            if (!$found_id) {
                return $slug;
            }
            $slug = $base_slug . '-' . $suffix;
            $suffix++;
        }
    }
}
