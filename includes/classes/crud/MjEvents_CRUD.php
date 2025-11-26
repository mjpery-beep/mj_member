<?php

if (!defined('ABSPATH')) {
    exit;
}

class MjEvents_CRUD {
    const TABLE = 'mj_events';
    const REG_TABLE = 'mj_event_registrations';

    const STATUS_ACTIVE = 'actif';
    const STATUS_DRAFT = 'brouillon';
    const STATUS_PAST = 'passe';

    const TYPE_STAGE = 'stage';
    const TYPE_SOIREE = 'soiree';
    const TYPE_SORTIE = 'sortie';
    const TYPE_ATELIER = 'atelier';

    /** @var array<string, string> */
    private static $columnFormats = array(
        'title' => '%s',
        'slug' => '%s',
        'status' => '%s',
        'type' => '%s',
        'accent_color' => '%s',
        'cover_id' => '%d',
        'location_id' => '%d',
        'animateur_id' => '%d',
        'article_id' => '%d',
        'allow_guardian_registration' => '%d',
        'description' => '%s',
        'age_min' => '%d',
        'age_max' => '%d',
        'date_debut' => '%s',
        'date_fin' => '%s',
        'date_fin_inscription' => '%s',
        'prix' => '%f',
        'schedule_mode' => '%s',
        'schedule_payload' => '%s',
        'recurrence_until' => '%s',
        'capacity_total' => '%d',
        'capacity_waitlist' => '%d',
        'capacity_notify_threshold' => '%d',
        'capacity_notified' => '%d',
        'created_at' => '%s',
        'updated_at' => '%s',
    );

    /**
     * @return array<string, string>
     */
    public static function get_status_labels() {
        return array(
            self::STATUS_ACTIVE => 'Actif',
            self::STATUS_DRAFT => 'Brouillon',
            self::STATUS_PAST => 'Passe',
        );
    }

    /**
     * @return array<string, string>
     */
    public static function get_type_labels() {
        return array(
            self::TYPE_STAGE => 'Stage',
            self::TYPE_SOIREE => 'Soiree',
            self::TYPE_SORTIE => 'Sortie',
            self::TYPE_ATELIER => 'Atelier',
        );
    }

    /**
     * @return array<string, string>
     */
    public static function get_type_colors() {
        return array(
            self::TYPE_STAGE => '#0026FF',
            self::TYPE_SOIREE => '#C300FF',
            self::TYPE_SORTIE => '#FF5100',
            self::TYPE_ATELIER => '#0C8301',

        );
    }
    

    /**
     * @param string $type
     * @return string
     */
    public static function get_default_color_for_type($type) {
        $type = sanitize_key((string) $type);
        $palette = self::get_type_colors();
        if (isset($palette[$type])) {
            $normalized = self::normalize_hex_color($palette[$type]);
            if ($normalized !== '') {
                return $normalized;
            }
        }

        return '#D8E3FB';
    }

    /**
     * @param int $event_id
     * @return object|null
     */
    public static function find($event_id) {
        $event_id = (int) $event_id;
        if ($event_id <= 0) {
            return null;
        }

        global $wpdb;
        $table = mj_member_get_events_table_name();

        $sql = $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $event_id);
        return $wpdb->get_row($sql);
    }

    /**
     * @param array<string, mixed> $data
     * @return int|false
     */
    public static function create(array $data) {
        global $wpdb;
        $table = mj_member_get_events_table_name();

        $data['created_at'] = current_time('mysql');
        $data['updated_at'] = current_time('mysql');

        $slug_candidate = '';
        if (array_key_exists('slug', $data)) {
            $slug_candidate = (string) $data['slug'];
            unset($data['slug']);
        }

        if (array_key_exists('article_id', $data) && (int) $data['article_id'] <= 0) {
            unset($data['article_id']);
        }

        if (array_key_exists('accent_color', $data)) {
            $normalized_color = self::normalize_hex_color($data['accent_color']);
            if ($normalized_color === '') {
                unset($data['accent_color']);
            } else {
                $data['accent_color'] = $normalized_color;
            }
        }

        list($filtered, $formats) = self::prepare_for_db($data);
        if (empty($filtered)) {
            return false;
        }

        $result = $wpdb->insert($table, $filtered, $formats);
        if ($result === false) {
            return false;
        }

        $event_id = (int) $wpdb->insert_id;
        if ($event_id > 0) {
            self::sync_slug($event_id, $slug_candidate !== '' ? $slug_candidate : (isset($data['title']) ? $data['title'] : ''));
        }

        return $event_id;
    }

    /**
     * @param int $event_id
     * @param array<string, mixed> $data
     * @return bool
     */
    public static function update($event_id, array $data) {
        $event_id = (int) $event_id;
        if ($event_id <= 0) {
            return false;
        }

        global $wpdb;
        $table = mj_member_get_events_table_name();

        $data['updated_at'] = current_time('mysql');

        $slug_candidate = null;
        if (array_key_exists('slug', $data)) {
            $slug_candidate = (string) $data['slug'];
            unset($data['slug']);
        } elseif (array_key_exists('title', $data)) {
            $slug_candidate = (string) $data['title'];
        }

        $nullable_columns = array();
        if (array_key_exists('cover_id', $data) && (int) $data['cover_id'] <= 0) {
            $nullable_columns[] = 'cover_id';
            unset($data['cover_id']);
        }
        if (array_key_exists('date_fin_inscription', $data) && empty($data['date_fin_inscription'])) {
            $nullable_columns[] = 'date_fin_inscription';
            unset($data['date_fin_inscription']);
        }
        if (array_key_exists('location_id', $data) && (int) $data['location_id'] <= 0) {
            $nullable_columns[] = 'location_id';
            unset($data['location_id']);
        }
        if (array_key_exists('recurrence_until', $data) && empty($data['recurrence_until'])) {
            $nullable_columns[] = 'recurrence_until';
            unset($data['recurrence_until']);
        }
        if (array_key_exists('schedule_payload', $data) && (empty($data['schedule_payload']) || $data['schedule_payload'] === array())) {
            $nullable_columns[] = 'schedule_payload';
            unset($data['schedule_payload']);
        }
        if (!self::supports_animateur_column()) {
            unset($data['animateur_id']);
        } elseif (array_key_exists('animateur_id', $data) && (int) $data['animateur_id'] <= 0) {
            $nullable_columns[] = 'animateur_id';
            unset($data['animateur_id']);
        }
        if (array_key_exists('article_id', $data) && (int) $data['article_id'] <= 0) {
            $nullable_columns[] = 'article_id';
            unset($data['article_id']);
        }

        if (array_key_exists('accent_color', $data)) {
            $normalized_color = self::normalize_hex_color($data['accent_color']);
            if ($normalized_color === '') {
                $nullable_columns[] = 'accent_color';
                unset($data['accent_color']);
            } else {
                $data['accent_color'] = $normalized_color;
            }
        }

        list($filtered, $formats) = self::prepare_for_db($data);
        if (empty($filtered) && empty($nullable_columns) && $slug_candidate === null) {
            return false;
        }

        $result = true;
        if (!empty($filtered)) {
            $update_result = $wpdb->update($table, $filtered, array('id' => $event_id), $formats, array('%d'));
            $result = ($update_result !== false);
        }

        if ($result && !empty($nullable_columns)) {
            foreach ($nullable_columns as $column) {
                if (!isset(self::$columnFormats[$column])) {
                    continue;
                }
                $wpdb->query($wpdb->prepare("UPDATE {$table} SET {$column} = NULL WHERE id = %d", $event_id));
            }
        }

        if ($result && $slug_candidate !== null) {
            self::sync_slug($event_id, $slug_candidate);
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public static function get_default_values() {
        return array(
            'title' => '',
            'slug' => '',
            'status' => self::STATUS_DRAFT,
            'type' => self::TYPE_STAGE,
            'accent_color' => '',
            'cover_id' => 0,
            'location_id' => 0,
            'allow_guardian_registration' => 0,
            'animateur_id' => 0,
            'animateur_ids' => array(),
            'description' => '',
            'age_min' => 12,
            'age_max' => 26,
            'date_debut' => '',
            'date_fin' => '',
            'date_fin_inscription' => '',
            'prix' => '0.00',
            'schedule_mode' => 'fixed',
            'schedule_payload' => array(),
            'recurrence_until' => '',
            'capacity_total' => 0,
            'capacity_waitlist' => 0,
            'capacity_notify_threshold' => 0,
            'capacity_notified' => 0,
            'article_id' => 0,
        );
    }

    /**
     * @param array<string, mixed> $data
     * @return array{0: array<string, mixed>, 1: array<int, string>}
     */
    private static function prepare_for_db(array $data) {
        $prepared = array();
        $formats = array();

        foreach (self::$columnFormats as $column => $format) {
            if (!array_key_exists($column, $data)) {
                continue;
            }

            if ($column === 'animateur_id' && !self::supports_animateur_column()) {
                continue;
            }

            $value = $data[$column];

            if ($column === 'description' && $value === null) {
                $value = '';
            }

            if ($value === null || $value === '') {
                if (in_array($column, array('description', 'title'), true)) {
                    $prepared[$column] = '';
                    $formats[] = $format;
                }
                continue;
            }

            switch ($column) {
                case 'cover_id':
                case 'location_id':
                case 'animateur_id':
                case 'article_id':
                case 'age_min':
                case 'age_max':
                case 'allow_guardian_registration':
                    $value = (int) $value;
                    if ($value <= 0 && in_array($column, array('cover_id', 'location_id', 'animateur_id'), true)) {
                        continue 2;
                    }
                    if ($column === 'article_id' && $value <= 0) {
                        continue 2;
                    }
                    break;
                case 'prix':
                    $value = (float) $value;
                    break;
                case 'capacity_total':
                case 'capacity_waitlist':
                case 'capacity_notify_threshold':
                case 'capacity_notified':
                    $value = max(0, (int) $value);
                    break;
                case 'schedule_payload':
                    if (is_array($value)) {
                        $value = wp_json_encode($value);
                    } else {
                        $value = (string) $value;
                    }
                    break;
                case 'accent_color':
                    $value = self::normalize_hex_color($value);
                    if ($value === '') {
                        continue 2;
                    }
                    break;
                case 'slug':
                    $value = self::sanitize_slug_candidate($value);
                    if ($value === '') {
                        continue 2;
                    }
                    break;
                default:
                    $value = is_string($value) ? $value : (string) $value;
                    break;
            }

            $prepared[$column] = $value;
            $formats[] = $format;
        }

        return array($prepared, $formats);
    }

    /**
     * @param string $slug
     * @return object|null
     */
    public static function find_by_slug($slug) {
        $original_slug = is_string($slug) ? $slug : (string) $slug;
        $sanitized = self::sanitize_slug_candidate($original_slug);
        if ($sanitized === '') {
            if (ctype_digit($original_slug)) {
                return self::find((int) $original_slug);
            }

            return null;
        }

        global $wpdb;
        $table = mj_member_get_events_table_name();
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE slug = %s LIMIT 1", $sanitized));
        if ($row) {
            return $row;
        }

        if (ctype_digit($original_slug)) {
            return self::find((int) $original_slug);
        }

        return null;
    }

    /**
     * @param int $event_id
     * @return string
     */
    public static function get_slug($event_id) {
        $event_id = (int) $event_id;
        if ($event_id <= 0) {
            return '';
        }

        global $wpdb;
        $table = mj_member_get_events_table_name();
        $value = $wpdb->get_var($wpdb->prepare("SELECT slug FROM {$table} WHERE id = %d", $event_id));
        if (!is_string($value) || $value === '') {
            return '';
        }

        return self::sanitize_slug_candidate($value);
    }

    /**
     * @param int $event_id
     * @return string
     */
    public static function get_or_create_slug($event_id) {
        $slug = self::get_slug($event_id);
        if ($slug !== '') {
            return $slug;
        }

        return self::sync_slug($event_id);
    }

    /**
     * @param int $event_id
     * @param string $base
     * @return string
     */
    public static function sync_slug($event_id, $base = '') {
        $event_id = (int) $event_id;
        if ($event_id <= 0) {
            return '';
        }

        $sanitized_base = self::sanitize_slug_candidate($base);
        $current_slug = self::get_slug($event_id);

        if ($sanitized_base === '') {
            if ($current_slug !== '') {
                return $current_slug;
            }

            $event = self::find($event_id);
            if ($event && !empty($event->slug)) {
                $sanitized_base = self::sanitize_slug_candidate($event->slug);
            }
            if ($sanitized_base === '' && $event && !empty($event->title)) {
                $sanitized_base = self::sanitize_slug_candidate($event->title);
            }
        }

        if ($sanitized_base === '') {
            $sanitized_base = 'evenement-' . $event_id;
        }

        $unique_slug = self::ensure_unique_slug($sanitized_base, $event_id);

        if ($unique_slug === $current_slug) {
            return $current_slug;
        }

        global $wpdb;
        $table = mj_member_get_events_table_name();
        $wpdb->update($table, array('slug' => $unique_slug), array('id' => $event_id), array('%s'), array('%d'));

        return $unique_slug;
    }

    /**
     * @param mixed $value
     * @return string
     */
    private static function sanitize_slug_candidate($value) {
        if (is_object($value) && method_exists($value, '__toString')) {
            $value = (string) $value;
        } elseif (!is_string($value)) {
            $value = (string) $value;
        }

        $value = sanitize_title($value);
        if ($value === '') {
            return '';
        }

        if (strlen($value) > 180) {
            $value = substr($value, 0, 180);
        }

        return $value;
    }

    /**
     * @param string $slug_base
     * @param int $event_id
     * @return string
     */
    private static function ensure_unique_slug($slug_base, $event_id) {
        $slug_base = $slug_base !== '' ? $slug_base : 'evenement';
        $slug = $slug_base;
        $event_id = (int) $event_id;

        global $wpdb;
        $table = mj_member_get_events_table_name();

        $suffix = 2;
        while ($slug !== '' && $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE slug = %s AND id <> %d LIMIT 1", $slug, $event_id))) {
            $candidate = $slug_base . '-' . $suffix;
            if (strlen($candidate) > 191) {
                $candidate = substr($candidate, 0, 191);
            }
            $slug = $candidate;
            $suffix++;
            if ($suffix > 200 && strlen($slug) >= 187) {
                $slug = substr($slug_base, 0, 160) . '-' . $event_id;
                break;
            }
        }

        if ($slug === '') {
            $slug = $slug_base . '-' . $event_id;
        }

        return $slug;
    }

    /**
     * @param string $value
     * @return string
     */
    private static function normalize_hex_color($value) {
        if (is_string($value)) {
            $candidate = trim($value);
        } elseif (is_object($value) && method_exists($value, '__toString')) {
            $candidate = trim((string) $value);
        } else {
            $candidate = (string) $value;
            $candidate = trim($candidate);
        }

        if ($candidate === '') {
            return '';
        }

        if ($candidate[0] !== '#') {
            $candidate = '#' . $candidate;
        }

        $sanitized = sanitize_hex_color($candidate);
        if (!is_string($sanitized) || $sanitized === '') {
            return '';
        }

        $sanitized = strtoupper($sanitized);
        if (strlen($sanitized) === 4) {
            return '#' . $sanitized[1] . $sanitized[1] . $sanitized[2] . $sanitized[2] . $sanitized[3] . $sanitized[3];
        }

        return $sanitized;
    }

    private static function supports_animateur_column() {
        static $supported = null;

        if ($supported !== null) {
            return $supported;
        }

        if (!function_exists('mj_member_column_exists')) {
            $supported = false;
            return $supported;
        }

        $table = mj_member_get_events_table_name();
        $supported = mj_member_column_exists($table, 'animateur_id');
        return $supported;
    }
}
