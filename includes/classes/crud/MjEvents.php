<?php

namespace Mj\Member\Classes\Crud;

use DateTimeInterface;
use Mj\Member\Classes\MjEventSchedule;
use Mj\Member\Classes\Value\EventData;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class MjEvents implements CrudRepositoryInterface {
    const TABLE = 'mj_events';
    const REG_TABLE = 'mj_event_registrations';

    const STATUS_ACTIVE = 'actif';
    const STATUS_DRAFT = 'brouillon';
    const STATUS_PAST = 'passe';

    const TYPE_STAGE = 'stage';
    const TYPE_SOIREE = 'soiree';
    const TYPE_SORTIE = 'sortie';
    const TYPE_ATELIER = 'atelier';
    const TYPE_INTERNE = 'interne';

    /** @var array<string, string> */
    private static $columnFormats = array(
        'title' => '%s',
        'slug' => '%s',
        'status' => '%s',
        'type' => '%s',
        'accent_color' => '%s',
        'emoji' => '%s',
        'cover_id' => '%d',
        'location_id' => '%d',
        'animateur_id' => '%d',
        'article_id' => '%d',
        'allow_guardian_registration' => '%d',
        'requires_validation' => '%d',
        'free_participation' => '%d',
        'registration_payload' => '%s',
        'description' => '%s',
        'registration_document' => '%s',
        'age_min' => '%d',
        'age_max' => '%d',
        'date_debut' => '%s',
        'date_fin' => '%s',
        'date_fin_inscription' => '%s',
        'prix' => '%f',
        'schedule_mode' => '%s',
        'schedule_payload' => '%s',
        'occurrence_selection_mode' => '%s',
        'recurrence_until' => '%s',
        'capacity_total' => '%d',
        'capacity_waitlist' => '%d',
        'capacity_notify_threshold' => '%d',
        'capacity_notified' => '%d',
        'created_at' => '%s',
        'updated_at' => '%s',
    );

    /**
     * @return string
     */
    private static function table_name() {
        return mj_member_get_events_table_name();
    }

    /**
     * @param array<string,mixed> $args
     * @return array<int,EventData>
     */
    public static function get_all(array $args = array()) {
        global $wpdb;
        $table = self::table_name();

        $defaults = array(
            'ids' => array(),
            'exclude_ids' => array(),
            'statuses' => array(),
            'types' => array(),
            'search' => '',
            'location_id' => 0,
            'animateur_id' => 0,
            'after' => '',
            'before' => '',
            'orderby' => 'date_debut',
            'order' => 'DESC',
            'limit' => 0,
            'offset' => 0,
        );
        $args = wp_parse_args($args, $defaults);

        $builder = CrudQueryBuilder::for_table($table);
        self::apply_common_filters($builder, $args);

        $allowed_orderby = array(
            'date_debut',
            'date_fin',
            'created_at',
            'updated_at',
            'title',
            'status',
            'type',
        );
        $orderby = sanitize_key($args['orderby']);
        if (!in_array($orderby, $allowed_orderby, true)) {
            $orderby = 'date_debut';
        }

        $order = strtoupper((string) $args['order']);
        $order = $order === 'ASC' ? 'ASC' : 'DESC';

        $limit = (int) $args['limit'];
        $offset = max(0, (int) $args['offset']);

        list($sql, $params) = $builder->build_select('*', $orderby, $order, $limit, $offset);

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        $results = $wpdb->get_results($sql);
        if (!is_array($results) || empty($results)) {
            return array();
        }

        return self::hydrate_events($results);
    }

    /**
     * @param array<string,mixed> $args
     * @return int
     */
    public static function count(array $args = array()) {
        global $wpdb;
        $table = self::table_name();

        $defaults = array(
            'ids' => array(),
            'exclude_ids' => array(),
            'statuses' => array(),
            'types' => array(),
            'search' => '',
            'location_id' => 0,
            'animateur_id' => 0,
            'after' => '',
            'before' => '',
        );
        $args = wp_parse_args($args, $defaults);

        $builder = CrudQueryBuilder::for_table($table);
        self::apply_common_filters($builder, $args);

        list($sql, $params) = $builder->build_count('*');

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        $count = $wpdb->get_var($sql);
        return $count ? (int) $count : 0;
    }

    /**
     * @param CrudQueryBuilder $builder
     * @param array<string,mixed> $args
     * @return void
     */
    private static function apply_common_filters(CrudQueryBuilder $builder, array $args) {
        $builder
            ->where_in_int('id', (array) $args['ids'])
            ->where_not_in_int('id', (array) $args['exclude_ids'])
            ->where_in_strings('status', (array) $args['statuses'], 'sanitize_key')
            ->where_in_strings('type', (array) $args['types'], 'sanitize_key');

        $search = trim((string) $args['search']);
        if ($search !== '') {
            $builder->where_like_any(array('title', 'slug', 'description'), $search);
        }

        $builder
            ->where_equals_int('location_id', (int) $args['location_id'])
            ->where_equals_int('animateur_id', (int) $args['animateur_id']);

        $after = isset($args['after']) ? self::sanitize_datetime_filter($args['after']) : '';
        if ($after !== '') {
            $builder->where_compare('date_debut', '>=', $after, '%s');
        }

        $before = isset($args['before']) ? self::sanitize_datetime_filter($args['before']) : '';
        if ($before !== '') {
            $builder->where_compare('date_debut', '<=', $before, '%s');
        }
    }

    private static function sanitize_datetime_filter($value) {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_array($value)) {
            $value = reset($value);
        }

        if (is_numeric($value)) {
            $timestamp = (int) $value;
            if ($timestamp <= 0) {
                return '';
            }

            return function_exists('wp_date')
                ? wp_date('Y-m-d H:i:s', $timestamp)
                : gmdate('Y-m-d H:i:s', $timestamp);
        }

        $candidate = trim((string) $value);
        if ($candidate === '') {
            return '';
        }

        $normalized = str_replace('T', ' ', $candidate);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $normalized)) {
            return $normalized . ' 00:00:00';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}$/', $normalized)) {
            return $normalized . ':00';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}$/', $normalized)) {
            return $normalized;
        }

        $timestamp = strtotime($normalized);
        if ($timestamp === false) {
            return '';
        }

        return function_exists('wp_date')
            ? wp_date('Y-m-d H:i:s', $timestamp)
            : gmdate('Y-m-d H:i:s', $timestamp);
    }

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
            self::TYPE_INTERNE => 'Interne',
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
            self::TYPE_INTERNE => '#5050A4',

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
     * @return EventData|null
     */
    public static function find($event_id) {
        $event_id = (int) $event_id;
        if ($event_id <= 0) {
            return null;
        }

        global $wpdb;
        $table = mj_member_get_events_table_name();

        $sql = $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $event_id);
        $row = $wpdb->get_row($sql);

        return $row ? EventData::fromRow($row) : null;
    }

    /**
     * @param mixed $data
     * @return int|WP_Error
     */
    public static function create($data) {
        if (!is_array($data)) {
            return new WP_Error('mj_event_invalid_payload', 'Format de donnees invalide pour l\'evenement.');
        }

        global $wpdb;
        $table = self::table_name();

        self::ensure_table_supports_emoji();

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

        if (array_key_exists('emoji', $data)) {
            if (!self::supports_emoji_column()) {
                unset($data['emoji']);
            } else {
                $normalized_emoji = self::sanitize_emoji($data['emoji']);
                if ($normalized_emoji === '') {
                    unset($data['emoji']);
                } else {
                    $data['emoji'] = $normalized_emoji;
                }
            }
        }

        list($filtered, $formats) = self::prepare_for_db($data);
        if (empty($filtered)) {
            return new WP_Error('mj_event_empty_payload', 'Aucune donnee valide pour creer cet evenement.');
        }

        $result = $wpdb->insert($table, $filtered, $formats);
        if ($result === false) {
            $error_detail = $wpdb->last_error ? ' (MySQL: ' . $wpdb->last_error . ')' : '';
            return new WP_Error('mj_event_insert_failed', 'Impossible de creer cet evenement.' . $error_detail);
        }

        $event_id = (int) $wpdb->insert_id;
        if ($event_id > 0) {
            self::sync_slug($event_id, $slug_candidate !== '' ? $slug_candidate : (isset($data['title']) ? $data['title'] : ''));
            self::refresh_event_occurrences($event_id);
        }

        return $event_id;
    }

    /**
     * @param int $event_id
     * @param mixed $data
     * @return true|WP_Error
     */
    public static function update($event_id, $data) {
        $event_id = (int) $event_id;
        if ($event_id <= 0) {
            return new WP_Error('mj_event_invalid_id', 'Identifiant d\'evenement invalide.');
        }

        if (!is_array($data)) {
            return new WP_Error('mj_event_invalid_payload', 'Format de donnees invalide pour l\'evenement.');
        }

        if (!self::find($event_id)) {
            return new WP_Error('mj_event_missing', 'Evenement introuvable.');
        }

        global $wpdb;
        $table = self::table_name();

        self::ensure_table_supports_emoji();

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
        if (array_key_exists('registration_payload', $data) && (empty($data['registration_payload']) || $data['registration_payload'] === array())) {
            $nullable_columns[] = 'registration_payload';
            unset($data['registration_payload']);
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

        if (array_key_exists('emoji', $data)) {
            if (!self::supports_emoji_column()) {
                unset($data['emoji']);
            } else {
                $normalized_emoji = self::sanitize_emoji($data['emoji']);
                if ($normalized_emoji === '') {
                    $nullable_columns[] = 'emoji';
                    unset($data['emoji']);
                } else {
                    $data['emoji'] = $normalized_emoji;
                }
            }
        }
   
        
        list($filtered, $formats) = self::prepare_for_db($data);
        if (empty($filtered) && empty($nullable_columns) && $slug_candidate === null) {
            return true;
        }

        if (!empty($filtered)) {
            $update_result = $wpdb->update($table, $filtered, array('id' => $event_id), $formats, array('%d'));
            if ($update_result === false) {
                $error_detail = $wpdb->last_error ? ' (MySQL: ' . $wpdb->last_error . ')' : '';
                return new WP_Error('mj_event_update_failed', 'Impossible de mettre a jour cet evenement.' . $error_detail);
            }
        }

        if (!empty($nullable_columns)) {
            foreach ($nullable_columns as $column) {
                if (!isset(self::$columnFormats[$column])) {
                    continue;
                }
                $reset = $wpdb->query($wpdb->prepare("UPDATE {$table} SET {$column} = NULL WHERE id = %d", $event_id));
                if ($reset === false) {
                    $error_detail = $wpdb->last_error ? ' (MySQL: ' . $wpdb->last_error . ')' : '';
                    return new WP_Error('mj_event_update_failed', 'Impossible de mettre a jour cet evenement.' . $error_detail);
                }
            }
        }

        if ($slug_candidate !== null) {
            self::sync_slug($event_id, $slug_candidate);
        }

        self::refresh_event_occurrences($event_id);

        return true;
    }

    /**
     * @param int $event_id
     * @return true|WP_Error
     */
    public static function delete($event_id) {
        $event_id = (int) $event_id;
        if ($event_id <= 0) {
            return new WP_Error('mj_event_invalid_id', 'Identifiant d\'evenement invalide.');
        }

        if (!self::find($event_id)) {
            return new WP_Error('mj_event_missing', 'Evenement introuvable.');
        }

        global $wpdb;
        $events_table = self::table_name();

        if (!function_exists('mj_member_table_exists') || !mj_member_table_exists($events_table)) {
            return new WP_Error('mj_event_table_missing', 'Table des evenements introuvable.');
        }

        $registration_ids = array();

        if (function_exists('mj_member_get_event_registrations_table_name')) {
            $registrations_table = mj_member_get_event_registrations_table_name();
            if ($registrations_table && mj_member_table_exists($registrations_table)) {
                $registration_ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$registrations_table} WHERE event_id = %d", $event_id));
                if (!is_array($registration_ids)) {
                    $registration_ids = array();
                }

                $deleted = $wpdb->delete($registrations_table, array('event_id' => $event_id), array('%d'));
                if ($deleted === false) {
                    return new WP_Error('mj_event_delete_failed', 'Impossible de supprimer les inscriptions liees a cet evenement.');
                }
            }
        }

        if (function_exists('mj_member_get_event_animateurs_table_name')) {
            $animateurs_table = mj_member_get_event_animateurs_table_name();
            if ($animateurs_table && mj_member_table_exists($animateurs_table)) {
                $removed = $wpdb->delete($animateurs_table, array('event_id' => $event_id), array('%d'));
                if ($removed === false) {
                    return new WP_Error('mj_event_delete_failed', 'Impossible de supprimer les animateurs lies a cet evenement.');
                }
            }
        }

        $payments_table = $wpdb->prefix . 'mj_payments';
        if (mj_member_table_exists($payments_table)) {
            $reset_event = $wpdb->query($wpdb->prepare("UPDATE {$payments_table} SET event_id = NULL WHERE event_id = %d", $event_id));
            if ($reset_event === false) {
                return new WP_Error('mj_event_delete_failed', 'Impossible de reinitialiser les paiements lies a cet evenement.');
            }

            if (!empty($registration_ids)) {
                $registration_ids = array_values(array_unique(array_map('intval', $registration_ids)));
                $registration_ids = array_filter($registration_ids, static function ($value) {
                    return $value > 0;
                });

                if (!empty($registration_ids)) {
                    $placeholders = implode(',', array_fill(0, count($registration_ids), '%d'));
                    $args = $registration_ids;
                    array_unshift($args, "UPDATE {$payments_table} SET registration_id = NULL WHERE registration_id IN ($placeholders)");
                    $prepared = call_user_func_array(array($wpdb, 'prepare'), $args);
                    $reset_registration = $wpdb->query($prepared);
                    if ($reset_registration === false) {
                        return new WP_Error('mj_event_delete_failed', 'Impossible de reinitialiser les paiements lies aux inscriptions de cet evenement.');
                    }
                }
            }
        }

        if (class_exists(__NAMESPACE__ . '\\MjEventOccurrences')) {
            MjEventOccurrences::delete_for_event($event_id);
        }

        $deleted = $wpdb->delete($events_table, array('id' => $event_id), array('%d'));
        if ($deleted === false || $deleted === 0) {
            return new WP_Error('mj_event_delete_failed', 'Suppression de l\'evenement impossible.');
        }

        return true;
    }

    /**
     * @param int $event_id
     * @return void
     */
    private static function refresh_event_occurrences($event_id) {
        $event_id = (int) $event_id;
        if ($event_id <= 0) {
            return;
        }

        if (!class_exists(__NAMESPACE__ . '\\MjEventOccurrences') || !class_exists('Mj\\Member\\Classes\\MjEventSchedule')) {
            return;
        }

        $event = self::find($event_id);
        if (!$event) {
            MjEventOccurrences::delete_for_event($event_id);
            return;
        }

        $occurrences = MjEventSchedule::build_all_occurrences($event);
        if (!is_array($occurrences)) {
            $occurrences = array();
        }

        $rows = array();
        $fallback_source = isset($event->schedule_mode) ? sanitize_key((string) $event->schedule_mode) : 'fixed';
        if ($fallback_source === '') {
            $fallback_source = 'fixed';
        }

        foreach ($occurrences as $occurrence) {
            if (!is_array($occurrence)) {
                continue;
            }

            $start_value = isset($occurrence['start']) ? (string) $occurrence['start'] : '';
            $end_value = isset($occurrence['end']) ? (string) $occurrence['end'] : '';
            if ($start_value === '' || $end_value === '') {
                continue;
            }

            $source_value = isset($occurrence['source']) ? sanitize_key((string) $occurrence['source']) : $fallback_source;
            if ($source_value === '') {
                $source_value = $fallback_source;
            }

            $rows[] = array(
                'start' => $start_value,
                'end' => $end_value,
                'source' => $source_value,
            );
        }

        MjEventOccurrences::replace_for_event($event_id, $rows);
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
            'emoji' => '',
            'cover_id' => 0,
            'location_id' => 0,
            'allow_guardian_registration' => 0,
            'requires_validation' => 1,
            'free_participation' => 0,
            'registration_payload' => array(),
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
            'occurrence_selection_mode' => 'member_choice',
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

            if ($column === 'emoji' && !self::supports_emoji_column()) {
                continue;
            }

            $value = $data[$column];

            if ($column === 'description' && $value === null) {
                $value = '';
            }

            if ($value === null || $value === '') {
                if (in_array($column, array('description', 'title', 'registration_document'), true)) {
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
                case 'requires_validation':
                    $value = (int) $value;
                    if ($value <= 0 && in_array($column, array('cover_id', 'location_id', 'animateur_id'), true)) {
                        continue 2;
                    }
                    if ($column === 'article_id' && $value <= 0) {
                        continue 2;
                    }
                    break;
                case 'free_participation':
                    $value = !empty($value) ? 1 : 0;
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
                case 'registration_payload':
                    if (is_array($value)) {
                        $value = wp_json_encode($value);
                    } else {
                        $value = (string) $value;
                    }
                    break;
                case 'occurrence_selection_mode':
                    $value = sanitize_key((string) $value);
                    if (!in_array($value, array('member_choice', 'all_occurrences'), true)) {
                        $value = 'member_choice';
                    }
                    break;
                case 'accent_color':
                    $value = self::normalize_hex_color($value);
                    if ($value === '') {
                        continue 2;
                    }
                    break;
                case 'emoji':
                    $value = self::sanitize_emoji($value);
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
    * @return EventData|null
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
            return EventData::fromRow($row);
        }

        if (ctype_digit($original_slug)) {
            return self::find((int) $original_slug);
        }

        return null;
    }

    /**
     * @param array<int,object|array<string,mixed>|EventData> $rows
     * @return array<int,EventData>
     */
    private static function hydrate_events(array $rows) {
        $hydrated = array();

        foreach ($rows as $row) {
            $hydrated[] = EventData::fromRow($row);
        }

        return $hydrated;
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

    private static function sanitize_emoji($value) {
        if (is_object($value) && method_exists($value, '__toString')) {
            $value = (string) $value;
        }

        if (!is_scalar($value)) {
            return '';
        }

        $candidate = wp_check_invalid_utf8((string) $value);
        if ($candidate === '') {
            return '';
        }

        $candidate = wp_strip_all_tags($candidate, false);
        $candidate = preg_replace('/[\x00-\x1F\x7F]+/', '', $candidate);
        if (!is_string($candidate)) {
            return '';
        }
        $candidate = trim($candidate);
        if ($candidate === '') {
            return '';
        }

        $candidate = \wp_html_excerpt($candidate, 16, '');

        return trim($candidate);
    }

    private static function supports_emoji_column() {
        static $supported = null;

        if ($supported !== null) {
            return $supported;
        }

        if (!function_exists('mj_member_column_exists')) {
            $supported = false;
            return $supported;
        }

        $table = mj_member_get_events_table_name();
        $supported = mj_member_column_exists($table, 'emoji');
        return $supported;
    }

    private static function ensure_table_supports_emoji() {
        static $ensured = false;

        if ($ensured) {
            return;
        }

        $ensured = true;

        if (!self::supports_emoji_column()) {
            return;
        }

        if (!function_exists('mj_member_convert_table_to_utf8mb4')) {
            return;
        }

        $table = self::table_name();
        mj_member_convert_table_to_utf8mb4($table);
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

\class_alias(__NAMESPACE__ . '\\MjEvents', 'MjEvents');

