<?php

namespace Mj\Member\Classes\Crud;

use Mj\Member\Classes\Value\EventLocationData;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class MjEventLocations implements CrudRepositoryInterface {
    const TABLE = 'mj_event_locations';

    /**
     * @return string
     */
    private static function table_name() {
        return mj_member_get_event_locations_table_name();
    }

    /**
     * @param array<string, mixed> $args
     * @return array<int, EventLocationData>
     */
    public static function get_all(array $args = array()) {
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

        $results = $wpdb->get_results($sql);
        if (!is_array($results) || empty($results)) {
            return array();
        }

        return array_map(static function ($row) {
            return EventLocationData::fromRow($row);
        }, $results);
    }

    /**
     * @param array<string,mixed> $args
     * @return int
     */
    public static function count(array $args = array()) {
        global $wpdb;
        $table = self::table_name();

        $defaults = array(
            'search' => '',
        );
        $args = wp_parse_args($args, $defaults);

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

        $sql = "SELECT COUNT(*) FROM {$table} {$where_sql}";
        if (!empty($where_values)) {
            array_unshift($where_values, $sql);
            $sql = call_user_func_array(array($wpdb, 'prepare'), $where_values);
        }

        return (int) $wpdb->get_var($sql);
    }

    /**
     * @param int $location_id
     * @return EventLocationData|null
     */
    public static function find($location_id) {
        $location_id = (int) $location_id;
        if ($location_id <= 0) {
            return null;
        }

        global $wpdb;
        $table = self::table_name();
        $sql = $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $location_id);
        $row = $wpdb->get_row($sql);

        return $row ? EventLocationData::fromRow($row) : null;
    }

    /**
     * @param string $slug
     * @return EventLocationData|null
     */
    public static function find_by_slug($slug) {
        $slug = sanitize_title($slug);
        if ($slug === '') {
            return null;
        }

        global $wpdb;
        $table = self::table_name();
        $sql = $wpdb->prepare("SELECT * FROM {$table} WHERE slug = %s", $slug);
        $row = $wpdb->get_row($sql);

        return $row ? EventLocationData::fromRow($row) : null;
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
    public static function create($data) {
        if (!is_array($data)) {
            return new WP_Error('mj_event_location_invalid_payload', 'Format de donnees invalide pour le lieu.');
        }
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
    public static function update($location_id, $data) {
        if (!is_array($data)) {
            return new WP_Error('mj_event_location_invalid_payload', 'Format de donnees invalide pour le lieu.');
        }
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

        $location = self::normalize_location_array($location);
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

        // Essayer d'utiliser la clé API Google Maps d'Elementor si disponible
        $google_api_key = get_option('elementor_google_maps_api_key', '');
        
        if (!empty($google_api_key)) {
            // Utiliser l'API Embed officielle de Google avec la clé
            return 'https://www.google.com/maps/embed/v1/place?key=' . rawurlencode($google_api_key) . '&q=' . rawurlencode($query);
        }

        // Sans clé API, l'embed Google ne fonctionne plus (X-Frame-Options)
        // Retourner une chaîne vide - le template affichera le lien cliquable à la place
        return '';
    }

    /**
     * @param object|array<string, mixed>|null $location
     * @return string
     */
    public static function format_address($location) {
        if (empty($location)) {
            return '';
        }

        $location = self::normalize_location_array($location);
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
     * Extracts normalized location types from a raw location record.
     *
     * @param object|array<string, mixed>|null $location
     * @return array<int, array{slug: string, label: string}>
     */
    public static function extract_types($location) {
        $location = self::normalize_location_array($location);
        if (empty($location)) {
            return array();
        }

        $types = array();
        $seen = array();

        $push_type = static function ($label, $slug = '') use (&$types, &$seen) {
            $label = is_string($label) ? trim(wp_strip_all_tags($label)) : '';
            if ($label === '') {
                return;
            }

            if ($slug === '') {
                $slug = sanitize_title($label);
            } else {
                $slug = sanitize_title($slug);
            }

            if ($slug === '' || isset($seen[$slug])) {
                return;
            }

            $types[] = array(
                'slug' => $slug,
                'label' => $label,
            );
            $seen[$slug] = true;
        };

        $normalize_list = static function ($value) {
            if (is_array($value)) {
                return $value;
            }

            if (!is_string($value)) {
                return array();
            }

            $value = trim($value);
            if ($value === '') {
                return array();
            }

            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && $decoded !== null) {
                if (is_array($decoded)) {
                    return $decoded;
                }

                if (is_string($decoded)) {
                    return array($decoded);
                }
            }

            $parts = preg_split('/[,;|\n]+/', $value);
            if (!is_array($parts)) {
                return array();
            }

            return array_map('trim', array_filter($parts, static function ($part) {
                return is_string($part) && trim($part) !== '';
            }));
        };

        $from_filter = apply_filters('mj_member_location_types', array(), $location);
        if (!is_array($from_filter)) {
            $from_filter = array($from_filter);
        }

        foreach ($from_filter as $entry) {
            if (is_array($entry) && isset($entry['label'])) {
                $push_type($entry['label'], isset($entry['slug']) ? $entry['slug'] : '');
                continue;
            }
            if (is_array($entry)) {
                foreach ($entry as $value) {
                    $push_type($value);
                }
                continue;
            }
            $push_type($entry);
        }

        $list_keys = array('types', 'location_types', 'categories', 'location_categories');
        foreach ($list_keys as $list_key) {
            if (!array_key_exists($list_key, $location)) {
                continue;
            }
            foreach ($normalize_list($location[$list_key]) as $value) {
                $push_type($value);
            }
        }

        $scalar_keys = array('type', 'location_type', 'category', 'type_label');
        foreach ($scalar_keys as $scalar_key) {
            if (empty($location[$scalar_key])) {
                continue;
            }
            $push_type($location[$scalar_key], isset($location[$scalar_key . '_slug']) ? $location[$scalar_key . '_slug'] : '');
        }

        if (!empty($location['notes']) && is_string($location['notes'])) {
            if (preg_match_all('/type\s*:\s*([^\r\n;,]+)/i', $location['notes'], $matches) && !empty($matches[1])) {
                foreach ($matches[1] as $match) {
                    $push_type($match);
                }
            }
        }

        if (isset($location['metadata'])) {
            $metadata = $location['metadata'];
            if (is_string($metadata)) {
                $decoded_metadata = json_decode($metadata, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_metadata)) {
                    if (isset($decoded_metadata['types'])) {
                        foreach ($normalize_list($decoded_metadata['types']) as $value) {
                            $push_type($value);
                        }
                    }
                    if (isset($decoded_metadata['type'])) {
                        $push_type($decoded_metadata['type']);
                    }
                }
            } elseif (is_array($metadata)) {
                if (isset($metadata['types'])) {
                    foreach ($normalize_list($metadata['types']) as $value) {
                        $push_type($value);
                    }
                }
                if (isset($metadata['type'])) {
                    $push_type($metadata['type']);
                }
            }
        }

        $types = apply_filters('mj_member_location_types_extracted', $types, $location);
        if (!is_array($types)) {
            return array();
        }

        return array_values($types);
    }

    /**
     * @param mixed $location
     * @return array<string,mixed>
     */
    private static function normalize_location_array($location) {
        if ($location instanceof EventLocationData) {
            return $location->toArray();
        }

        if (is_array($location)) {
            return $location;
        }

        if (is_object($location)) {
            return get_object_vars($location);
        }

        return array();
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

class_alias(__NAMESPACE__ . '\\MjEventLocations', 'MjEventLocations');
