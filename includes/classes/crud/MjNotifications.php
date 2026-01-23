<?php

namespace Mj\Member\Classes\Crud;

use Exception;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class MjNotifications implements CrudRepositoryInterface {
    const STATUS_DRAFT = 'draft';
    const STATUS_PUBLISHED = 'published';
    const STATUS_ARCHIVED = 'archived';

    /**
     * @return string
     */
    public static function get_table_name() {
        return function_exists('mj_member_get_notifications_table_name') ? mj_member_get_notifications_table_name() : '';
    }

    /**
     * @return array<string,mixed>
     */
    public static function get_default_values() {
        return array(
            'uid' => '',
            'type' => '',
            'status' => self::STATUS_PUBLISHED,
            'priority' => 0,
            'title' => '',
            'excerpt' => '',
            'payload' => array(),
            'url' => '',
            'context' => '',
            'source' => '',
            'created_at' => current_time('mysql', 1),
            'expires_at' => null,
        );
    }

    /**
     * @return array<int,object>
     */
    public static function get_all(array $args = array()) {
        $table = self::get_table_name();
        if ($table === '') {
            return array();
        }

        global $wpdb;

        $defaults = array(
            'ids' => array(),
            'exclude_ids' => array(),
            'types' => array(),
            'statuses' => array(),
            'search' => '',
            'priority_min' => null,
            'priority_max' => null,
            'created_after' => '',
            'created_before' => '',
            'expires_after' => '',
            'expires_before' => '',
            'order' => 'DESC',
            'orderby' => 'created_at',
            'limit' => 0,
            'offset' => 0,
        );
        $args = wp_parse_args($args, $defaults);

        $builder = CrudQueryBuilder::for_table($table);
        self::apply_common_filters($builder, $args);

        list($sql, $params) = $builder->build_select('*', self::sanitize_orderby($args['orderby']), $args['order'], (int) $args['limit'], (int) $args['offset']);

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        $rows = $wpdb->get_results($sql);
        if (empty($rows)) {
            return array();
        }

        return array_map(array(__CLASS__, 'hydrate_row'), $rows);
    }

    /**
     * @param array<string,mixed> $args
     * @return int
     */
    public static function count(array $args = array()) {
        $table = self::get_table_name();
        if ($table === '') {
            return 0;
        }

        global $wpdb;

        $builder = CrudQueryBuilder::for_table($table);
        self::apply_common_filters($builder, wp_parse_args($args, array()));

        list($sql, $params) = $builder->build_count('*');
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        return (int) $wpdb->get_var($sql);
    }

    /**
     * @param array<string,mixed> $data
     * @return int|WP_Error
     */
    public static function create($data) {
        $table = self::get_table_name();
        if ($table === '') {
            return new WP_Error('mj_notification_table_missing', __('Table des notifications introuvable.', 'mj-member'));
        }

        global $wpdb;

        $record = self::sanitize_record($data);
        if (empty($record['uid'])) {
            $record['uid'] = self::generate_uid();
        }

        $existing = self::get_by_uid($record['uid']);
        if ($existing) {
            return new WP_Error('mj_notification_duplicate_uid', __('Cette notification existe déjà.', 'mj-member'));
        }

        $formats = array(
            '%s', // uid
            '%s', // type
            '%s', // status
            '%d', // priority
            '%s', // title
            '%s', // excerpt
            '%s', // payload
            '%s', // url
            '%s', // context
            '%s', // source
            '%s', // created_at
            '%s', // expires_at
        );

        $inserted = $wpdb->insert($table, $record, $formats);
        if ($inserted === false) {
            return new WP_Error('mj_notification_insert_failed', __('Impossible de créer la notification.', 'mj-member'));
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * @param int $id
     * @param array<string,mixed> $data
     * @return true|WP_Error
     */
    public static function update($id, $data) {
        $table = self::get_table_name();
        if ($table === '') {
            return new WP_Error('mj_notification_table_missing', __('Table des notifications introuvable.', 'mj-member'));
        }

        $id = (int) $id;
        if ($id <= 0) {
            return new WP_Error('mj_notification_invalid_id', __('Identifiant invalide.', 'mj-member'));
        }

        global $wpdb;

        $record = self::sanitize_record($data, false);
        if (empty($record)) {
            return true;
        }

        $updated = $wpdb->update($table, $record, array('id' => $id), self::formats_for_record($record), array('%d'));
        if ($updated === false) {
            return new WP_Error('mj_notification_update_failed', __('Impossible de mettre à jour la notification.', 'mj-member'));
        }

        return true;
    }

    /**
     * @param int $id
     * @return true|WP_Error
     */
    public static function delete($id) {
        $table = self::get_table_name();
        if ($table === '') {
            return new WP_Error('mj_notification_table_missing', __('Table des notifications introuvable.', 'mj-member'));
        }

        $id = (int) $id;
        if ($id <= 0) {
            return new WP_Error('mj_notification_invalid_id', __('Identifiant invalide.', 'mj-member'));
        }

        global $wpdb;

        $deleted = $wpdb->delete($table, array('id' => $id), array('%d'));
        if ($deleted === false) {
            return new WP_Error('mj_notification_delete_failed', __('Impossible de supprimer la notification.', 'mj-member'));
        }

        return true;
    }

    /**
     * @param int $id
     * @return object|null
     */
    public static function get($id) {
        $table = self::get_table_name();
        if ($table === '') {
            return null;
        }

        global $wpdb;
        $id = (int) $id;
        if ($id <= 0) {
            return null;
        }

        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . $table . ' WHERE id = %d LIMIT 1', $id));
        return $row ? self::hydrate_row($row) : null;
    }

    /**
     * @param string $uid
     * @return object|null
     */
    public static function get_by_uid($uid) {
        $table = self::get_table_name();
        if ($table === '') {
            return null;
        }

        global $wpdb;
        $uid = self::sanitize_uid($uid);
        if ($uid === '') {
            return null;
        }

        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . $table . ' WHERE uid = %s LIMIT 1', $uid));
        return $row ? self::hydrate_row($row) : null;
    }

    /**
     * @param object $row
     * @return object
     */
    private static function hydrate_row($row) {
        if (isset($row->payload)) {
            $decoded = json_decode((string) $row->payload, true);
            $row->payload = is_array($decoded) ? $decoded : array();
        }
        if (isset($row->priority)) {
            $row->priority = (int) $row->priority;
        }
        return $row;
    }

    /**
     * @param array<string,mixed> $data
     * @param bool $include_defaults
     * @return array<string,mixed>
     */
    private static function sanitize_record($data, $include_defaults = true) {
        $defaults = self::get_default_values();
        $allowed_keys = array_keys($defaults);
        $allowed_index = array_flip($allowed_keys);

        $data = is_array($data) ? $data : array();
        $record = $include_defaults ? wp_parse_args($data, $defaults) : array_intersect_key($data, $defaults);

        $record['uid'] = self::sanitize_uid(isset($record['uid']) ? $record['uid'] : '');
        $record['type'] = sanitize_title(isset($record['type']) ? $record['type'] : '');

        $allowed_statuses = self::get_statuses();
        $status = isset($record['status']) ? sanitize_key($record['status']) : self::STATUS_PUBLISHED;
        if (!in_array($status, $allowed_statuses, true)) {
            $status = self::STATUS_PUBLISHED;
        }
        $record['status'] = $status;

        $priority = isset($record['priority']) ? (int) $record['priority'] : 0;
        $record['priority'] = max(0, min(65535, $priority));

        $record['title'] = wp_strip_all_tags(isset($record['title']) ? (string) $record['title'] : '');
        $record['excerpt'] = wp_strip_all_tags(isset($record['excerpt']) ? (string) $record['excerpt'] : '');

        $payload = isset($record['payload']) ? $record['payload'] : array();
        if (is_string($payload)) {
            $record['payload'] = $payload;
        } else {
            $encoded = wp_json_encode($payload);
            $record['payload'] = is_string($encoded) ? $encoded : '[]';
        }

        $record['url'] = esc_url_raw(isset($record['url']) ? $record['url'] : '');
        $record['context'] = sanitize_text_field(isset($record['context']) ? $record['context'] : '');
        $record['source'] = sanitize_text_field(isset($record['source']) ? $record['source'] : '');

        $record['created_at'] = self::sanitize_datetime(isset($record['created_at']) ? $record['created_at'] : null, current_time('mysql', 1));
        $record['expires_at'] = self::sanitize_datetime(isset($record['expires_at']) ? $record['expires_at'] : null, null);
        if ($record['expires_at'] === null) {
            unset($record['expires_at']);
        }

        return array_intersect_key($record, $allowed_index);
    }

    /**
     * @param array<string,mixed> $record
     * @return array<int,string>
     */
    private static function formats_for_record(array $record) {
        $formats = array();
        $map = array(
            'uid' => '%s',
            'type' => '%s',
            'status' => '%s',
            'priority' => '%d',
            'title' => '%s',
            'excerpt' => '%s',
            'payload' => '%s',
            'url' => '%s',
            'context' => '%s',
            'source' => '%s',
            'created_at' => '%s',
            'expires_at' => '%s',
        );

        foreach ($record as $key => $value) {
            if (isset($map[$key])) {
                $formats[] = $map[$key];
            }
        }

        return $formats;
    }

    /**
     * @param CrudQueryBuilder $builder
     * @param array<string,mixed> $args
     * @return void
     */
    private static function apply_common_filters(CrudQueryBuilder $builder, array $args) {
        if (!empty($args['ids'])) {
            $builder->where_in_int('id', (array) $args['ids']);
        }

        if (!empty($args['exclude_ids'])) {
            $builder->where_not_in_int('id', (array) $args['exclude_ids']);
        }

        if (!empty($args['types'])) {
            $builder->where_in_strings('type', (array) $args['types'], 'sanitize_title');
        }

        if (!empty($args['statuses'])) {
            $statuses = array_intersect(self::get_statuses(), array_map('sanitize_key', (array) $args['statuses']));
            if (!empty($statuses)) {
                $builder->where_in_strings('status', $statuses, 'sanitize_key');
            }
        }

        if (!empty($args['priority_min'])) {
            $builder->where_compare('priority', '>=', (int) $args['priority_min'], '%d');
        }

        if (!empty($args['priority_max'])) {
            $builder->where_compare('priority', '<=', (int) $args['priority_max'], '%d');
        }

        if (!empty($args['search'])) {
            $builder->where_like_any(array('title', 'excerpt', 'payload', 'context', 'source'), (string) $args['search']);
        }

        if (!empty($args['created_after'])) {
            $builder->where_compare('created_at', '>=', (string) $args['created_after']);
        }

        if (!empty($args['created_before'])) {
            $builder->where_compare('created_at', '<=', (string) $args['created_before']);
        }

        if (!empty($args['expires_after'])) {
            $builder->where_compare('expires_at', '>=', (string) $args['expires_after']);
        }

        if (!empty($args['expires_before'])) {
            $builder->where_compare('expires_at', '<=', (string) $args['expires_before']);
        }
    }

    /**
     * @return array<string>
     */
    public static function get_statuses() {
        return array(
            self::STATUS_DRAFT,
            self::STATUS_PUBLISHED,
            self::STATUS_ARCHIVED,
        );
    }

    /**
     * @param string $uid
     * @return string
     */
    private static function sanitize_uid($uid) {
        $uid = sanitize_text_field((string) $uid);
        if ($uid === '') {
            return '';
        }
        if (strlen($uid) > 64) {
            $uid = substr($uid, 0, 64);
        }
        return $uid;
    }

    /**
     * @param string|null $value
     * @param string|null $fallback
     * @return string|null
     */
    private static function sanitize_datetime($value, $fallback = null) {
        $value = $value !== null ? sanitize_text_field((string) $value) : '';
        if ($value === '') {
            return $fallback;
        }
        return $value;
    }

    /**
     * @return string
     */
    private static function generate_uid() {
        if (function_exists('wp_generate_uuid4')) {
            return wp_generate_uuid4();
        }

        try {
            return bin2hex(random_bytes(16));
        } catch (Exception $e) {
            return uniqid('mj-notif-', false);
        }
    }

    /**
     * @param string $orderby
     * @return string
     */
    private static function sanitize_orderby($orderby) {
        $allowed = array('created_at', 'priority', 'expires_at', 'id');
        $orderby = sanitize_key((string) $orderby);
        if (!in_array($orderby, $allowed, true)) {
            $orderby = 'created_at';
        }
        return $orderby;
    }
}
