<?php

namespace Mj\Member\Classes\Crud;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class MjNotificationRecipients implements CrudRepositoryInterface {
    const STATUS_UNREAD = 'unread';
    const STATUS_READ = 'read';
    const STATUS_ARCHIVED = 'archived';

    /**
     * @return string
     */
    public static function get_table_name() {
        return function_exists('mj_member_get_notification_recipients_table_name') ? mj_member_get_notification_recipients_table_name() : '';
    }

    /**
     * @return array<string,mixed>
     */
    public static function get_default_values() {
        return array(
            'notification_id' => 0,
            'member_id' => null,
            'user_id' => null,
            'role' => '',
            'status' => self::STATUS_UNREAD,
            'read_at' => null,
            'delivered_at' => null,
            'extra_meta' => array(),
            'created_at' => current_time('mysql', 1),
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
            'notification_ids' => array(),
            'member_ids' => array(),
            'user_ids' => array(),
            'roles' => array(),
            'statuses' => array(),
            'created_after' => '',
            'created_before' => '',
            'read_after' => '',
            'read_before' => '',
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
            return new WP_Error('mj_notification_recipients_table_missing', __('Table des destinataires introuvable.', 'mj-member'));
        }

        global $wpdb;

        $record = self::sanitize_record($data);
        if ((int) $record['notification_id'] <= 0) {
            return new WP_Error('mj_notification_recipients_invalid_notification', __('Notification invalide.', 'mj-member'));
        }

        $formats = self::formats_for_record($record);
        $inserted = $wpdb->insert($table, $record, $formats);
        if ($inserted === false) {
            return new WP_Error('mj_notification_recipients_insert_failed', __('Impossible de créer le destinataire.', 'mj-member'));
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
            return new WP_Error('mj_notification_recipients_table_missing', __('Table des destinataires introuvable.', 'mj-member'));
        }

        $id = (int) $id;
        if ($id <= 0) {
            return new WP_Error('mj_notification_recipients_invalid_id', __('Identifiant invalide.', 'mj-member'));
        }

        global $wpdb;

        $record = self::sanitize_record($data, false);
        if (empty($record)) {
            return true;
        }

        $updated = $wpdb->update($table, $record, array('id' => $id), self::formats_for_record($record), array('%d'));
        if ($updated === false) {
            return new WP_Error('mj_notification_recipients_update_failed', __('Impossible de mettre à jour le destinataire.', 'mj-member'));
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
            return new WP_Error('mj_notification_recipients_table_missing', __('Table des destinataires introuvable.', 'mj-member'));
        }

        $id = (int) $id;
        if ($id <= 0) {
            return new WP_Error('mj_notification_recipients_invalid_id', __('Identifiant invalide.', 'mj-member'));
        }

        global $wpdb;

        $deleted = $wpdb->delete($table, array('id' => $id), array('%d'));
        if ($deleted === false) {
            return new WP_Error('mj_notification_recipients_delete_failed', __('Impossible de supprimer le destinataire.', 'mj-member'));
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
     * @param array<int,int> $ids
     * @param string $status
     * @param string|null $timestamp
     * @return int|WP_Error nombre de lignes mises à jour
     */
    public static function mark_status(array $ids, $status, $timestamp = null) {
        $table = self::get_table_name();
        if ($table === '') {
            return new WP_Error('mj_notification_recipients_table_missing', __('Table des destinataires introuvable.', 'mj-member'));
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static function ($value) {
            return $value > 0;
        })));
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '%d'));
            $sql .= sprintf(' AND notification_id IN (%s)', $placeholders);
            $params = array_merge($params, $ids);
        }
        if (!in_array($status, self::get_statuses(), true)) {
            return new WP_Error('mj_notification_recipients_invalid_status', __('Statut invalide.', 'mj-member'));
        }

        global $wpdb;

        $timestamp = self::sanitize_datetime($timestamp, current_time('mysql', 1));
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $sql = sprintf(
            'UPDATE %s SET status = %%s, read_at = CASE WHEN %%s = %%s THEN %%s WHEN %%s = %%s THEN NULL ELSE read_at END WHERE id IN (%s)',
            $table,
            $placeholders
        );

        $params = array($status, $status, self::STATUS_READ, $timestamp, $status, self::STATUS_UNREAD);
        foreach ($ids as $id) {
            $params[] = $id;
        }

        $prepared = $wpdb->prepare($sql, $params);
        $result = $wpdb->query($prepared);
        if ($result === false) {
            return new WP_Error('mj_notification_recipients_mark_failed', __('Impossible de mettre à jour les statuts.', 'mj-member'));
        }

        return (int) $result;
    }

    /**
     * @param int $member_id
     * @param array<int,int> $notification_ids
     * @param string|null $timestamp
     * @return int|WP_Error lignes mises à jour
     */
    public static function mark_read_for_member($member_id, array $notification_ids = array(), $timestamp = null) {
        $table = self::get_table_name();
        if ($table === '') {
            return new WP_Error('mj_notification_recipients_table_missing', __('Table des destinataires introuvable.', 'mj-member'));
        }

        $member_id = (int) $member_id;
        if ($member_id <= 0) {
            return new WP_Error('mj_notification_recipients_invalid_member', __('Membre invalide.', 'mj-member'));
        }

        $timestamp = self::sanitize_datetime($timestamp, current_time('mysql', 1));

        global $wpdb;

        $params = array(self::STATUS_READ, $timestamp, $member_id);
        $sql = sprintf('UPDATE %s SET status = %%s, read_at = %%s WHERE member_id = %%d', $table);

        $ids = array_values(array_unique(array_filter(array_map('intval', $notification_ids), static function ($value) {
            return $value > 0;
        })));
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '%d'));
            $sql .= sprintf(' AND notification_id IN (%s)', $placeholders);
            $params = array_merge($params, $ids);
        }

        $prepared = $wpdb->prepare($sql, $params);
        $result = $wpdb->query($prepared);
        if ($result === false) {
            return new WP_Error('mj_notification_recipients_mark_failed', __('Impossible de marquer les notifications comme lues.', 'mj-member'));
        }

        return (int) $result;
    }

    /**
     * @param int $user_id
     * @param array<int,int> $notification_ids
     * @param string|null $timestamp
     * @return int|WP_Error lignes mises à jour
     */
    public static function mark_read_for_user($user_id, array $notification_ids = array(), $timestamp = null) {
        $table = self::get_table_name();
        if ($table === '') {
            return new WP_Error('mj_notification_recipients_table_missing', __('Table des destinataires introuvable.', 'mj-member'));
        }

        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            return new WP_Error('mj_notification_recipients_invalid_user', __('Utilisateur invalide.', 'mj-member'));
        }

        $timestamp = self::sanitize_datetime($timestamp, current_time('mysql', 1));

        global $wpdb;

        $params = array(self::STATUS_READ, $timestamp, $user_id);
        $sql = sprintf('UPDATE %s SET status = %%s, read_at = %%s WHERE user_id = %%d', $table);

        $ids = array_values(array_unique(array_filter(array_map('intval', $notification_ids), static function ($value) {
            return $value > 0;
        })));
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '%d'));
            $sql .= sprintf(' AND notification_id IN (%s)', $placeholders);
            $params = array_merge($params, $ids);
        }

        $prepared = $wpdb->prepare($sql, $params);
        $result = $wpdb->query($prepared);
        if ($result === false) {
            return new WP_Error('mj_notification_recipients_mark_failed', __('Impossible de marquer les notifications comme lues.', 'mj-member'));
        }

        return (int) $result;
    }

    /**
     * @param int $member_id
     * @return int
     */
    public static function get_unread_count_for_member($member_id) {
        $table = self::get_table_name();
        $notifications_table = MjNotifications::get_table_name();
        if ($table === '' || $notifications_table === '') {
            return 0;
        }

        global $wpdb;

        $member_id = (int) $member_id;
        if ($member_id <= 0) {
            return 0;
        }

        $now = current_time('mysql', 1);

        $sql = sprintf(
            'SELECT COUNT(r.id) FROM %1$s r INNER JOIN %2$s n ON n.id = r.notification_id WHERE r.member_id = %%d AND r.status = %%s AND n.status = %%s AND (n.expires_at IS NULL OR n.expires_at > %%s)',
            $table,
            $notifications_table
        );

        $prepared = $wpdb->prepare($sql, $member_id, self::STATUS_UNREAD, MjNotifications::STATUS_PUBLISHED, $now);
        return (int) $wpdb->get_var($prepared);
    }

    /**
     * @param int $user_id
     * @return int
     */
    /**
     * @param int $user_id
     * @param array<string,mixed> $args
     * @return int
     */
    public static function get_unread_count_for_user($user_id, array $args = array()) {
        $table = self::get_table_name();
        $notifications_table = MjNotifications::get_table_name();
        if ($table === '' || $notifications_table === '') {
            return 0;
        }

        global $wpdb;

        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            return 0;
        }

        $defaults = array(
            'types' => array(),
            'exclude_types' => array(),
            'include_expired' => false,
        );
        $args = wp_parse_args($args, $defaults);

        $where = array(
            $wpdb->prepare('r.user_id = %d', $user_id),
            $wpdb->prepare('r.status = %s', self::STATUS_UNREAD),
            $wpdb->prepare('n.status = %s', MjNotifications::STATUS_PUBLISHED),
        );
        $params = array();

        if (empty($args['include_expired'])) {
            $where[] = '(n.expires_at IS NULL OR n.expires_at > %s)';
            $params[] = current_time('mysql', 1);
        }

        $types = array();
        foreach ((array) $args['types'] as $type) {
            $type = sanitize_title($type);
            if ($type !== '') {
                $types[$type] = $type;
            }
        }
        if (!empty($types)) {
            $placeholders = implode(',', array_fill(0, count($types), '%s'));
            $where[] = sprintf('n.type IN (%s)', $placeholders);
            $params = array_merge($params, array_values($types));
        }

        $exclude_types = array();
        foreach ((array) $args['exclude_types'] as $type) {
            $type = sanitize_title($type);
            if ($type !== '') {
                $exclude_types[$type] = $type;
            }
        }
        if (!empty($exclude_types)) {
            $placeholders = implode(',', array_fill(0, count($exclude_types), '%s'));
            $where[] = sprintf('n.type NOT IN (%s)', $placeholders);
            $params = array_merge($params, array_values($exclude_types));
        }

        $sql = sprintf(
            'SELECT COUNT(r.id) FROM %1$s r INNER JOIN %2$s n ON n.id = r.notification_id WHERE %3$s',
            $table,
            $notifications_table,
            implode(' AND ', $where)
        );

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        $result = $wpdb->get_var($sql);
        return (int) $result;
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,int|WP_Error>
     */
    public static function bulk_insert(array $rows) {
        $inserted = array();
        foreach ($rows as $row) {
            $inserted[] = self::create($row);
        }
        return $inserted;
    }

    /**
     * @param object $row
     * @return object
     */
    private static function hydrate_row($row) {
        if (isset($row->notification_id)) {
            $row->notification_id = (int) $row->notification_id;
        }
        if (isset($row->member_id)) {
            $row->member_id = $row->member_id !== null ? (int) $row->member_id : null;
        }
        if (isset($row->user_id)) {
            $row->user_id = $row->user_id !== null ? (int) $row->user_id : null;
        }
        if (isset($row->extra_meta)) {
            $decoded = json_decode((string) $row->extra_meta, true);
            $row->extra_meta = is_array($decoded) ? $decoded : array();
        }
        return $row;
    }

    /**
     * @param array<string,mixed> $data
     * @param bool $include_defaults
     * @return array<string,mixed>
     */
    private static function sanitize_record($data, $include_defaults = true) {
        $data = is_array($data) ? $data : array();
        $defaults = self::get_default_values();
        $allowed_keys = array_keys($defaults);

        $allowed_index = array_flip($allowed_keys);

        $record = $include_defaults ? wp_parse_args($data, $defaults) : array_intersect_key($data, $defaults);

        $record['notification_id'] = isset($record['notification_id']) ? (int) $record['notification_id'] : 0;

        $member_id = isset($record['member_id']) ? (int) $record['member_id'] : 0;
        $record['member_id'] = $member_id > 0 ? $member_id : null;

        $user_id = isset($record['user_id']) ? (int) $record['user_id'] : 0;
        $record['user_id'] = $user_id > 0 ? $user_id : null;

        $role = isset($record['role']) ? sanitize_key($record['role']) : '';
        $record['role'] = substr($role, 0, 64);

        $status = isset($record['status']) ? sanitize_key($record['status']) : self::STATUS_UNREAD;
        if (!in_array($status, self::get_statuses(), true)) {
            $status = self::STATUS_UNREAD;
        }
        $record['status'] = $status;

        $record['read_at'] = self::sanitize_datetime(isset($record['read_at']) ? $record['read_at'] : null, null);
        $record['delivered_at'] = self::sanitize_datetime(isset($record['delivered_at']) ? $record['delivered_at'] : null, null);

        $extra = isset($record['extra_meta']) ? $record['extra_meta'] : array();
        if (is_string($extra)) {
            $record['extra_meta'] = $extra;
        } else {
            $encoded = wp_json_encode($extra);
            $record['extra_meta'] = is_string($encoded) ? $encoded : '[]';
        }

        $record['created_at'] = self::sanitize_datetime(isset($record['created_at']) ? $record['created_at'] : null, current_time('mysql', 1));

        if ($record['member_id'] === null) {
            unset($record['member_id']);
        }
        if ($record['user_id'] === null) {
            unset($record['user_id']);
        }
        if ($record['read_at'] === null) {
            unset($record['read_at']);
        }
        if ($record['delivered_at'] === null) {
            unset($record['delivered_at']);
        }

        return array_intersect_key($record, $allowed_index);
    }

    /**
     * @param array<string,mixed> $record
     * @return array<int,string>
     */
    private static function formats_for_record(array $record) {
        $map = array(
            'notification_id' => '%d',
            'member_id' => '%d',
            'user_id' => '%d',
            'role' => '%s',
            'status' => '%s',
            'read_at' => '%s',
            'delivered_at' => '%s',
            'extra_meta' => '%s',
            'created_at' => '%s',
        );

        $formats = array();
        foreach ($record as $key => $value) {
            if (!isset($map[$key])) {
                continue;
            }

            $formats[] = $map[$key];
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

        if (!empty($args['notification_ids'])) {
            $builder->where_in_int('notification_id', (array) $args['notification_ids']);
        }

        if (!empty($args['member_ids'])) {
            $builder->where_in_int('member_id', (array) $args['member_ids']);
        }

        if (!empty($args['user_ids'])) {
            $builder->where_in_int('user_id', (array) $args['user_ids']);
        }

        if (!empty($args['roles'])) {
            $builder->where_in_strings('role', (array) $args['roles'], 'sanitize_key');
        }

        if (!empty($args['statuses'])) {
            $statuses = array_intersect(self::get_statuses(), array_map('sanitize_key', (array) $args['statuses']));
            if (!empty($statuses)) {
                $builder->where_in_strings('status', $statuses, 'sanitize_key');
            }
        }

        if (!empty($args['created_after'])) {
            $builder->where_compare('created_at', '>=', (string) $args['created_after']);
        }

        if (!empty($args['created_before'])) {
            $builder->where_compare('created_at', '<=', (string) $args['created_before']);
        }

        if (!empty($args['read_after'])) {
            $builder->where_compare('read_at', '>=', (string) $args['read_after']);
        }

        if (!empty($args['read_before'])) {
            $builder->where_compare('read_at', '<=', (string) $args['read_before']);
        }
    }

    /**
     * @return array<string>
     */
    public static function get_statuses() {
        return array(
            self::STATUS_UNREAD,
            self::STATUS_READ,
            self::STATUS_ARCHIVED,
        );
    }

    /**
     * @param string $orderby
     * @return string
     */
    private static function sanitize_orderby($orderby) {
        $allowed = array('created_at', 'read_at', 'delivered_at', 'id');
        $orderby = sanitize_key((string) $orderby);
        if (!in_array($orderby, $allowed, true)) {
            $orderby = 'created_at';
        }
        return $orderby;
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
}
