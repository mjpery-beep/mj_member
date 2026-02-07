<?php

namespace Mj\Member\Classes;

use Mj\Member\Classes\Crud\MjNotifications;
use Mj\Member\Classes\Crud\MjNotificationRecipients;
use Mj\Member\Classes\Crud\MjMembers;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class MjNotificationManager {
    /**
     * @param array<string,mixed> $notification_data
     * @param array<int,mixed> $recipients
     * @return array{notification_id:int,recipient_ids:array<int,int>}|WP_Error
     */
    public static function record(array $notification_data, array $recipients) {
        if (empty($recipients)) {
            return new WP_Error('mj_notification_missing_recipients', __('Aucun destinataire fourni.', 'mj-member'));
        }

        $notification_id = MjNotifications::create($notification_data);
        if (is_wp_error($notification_id)) {
            return $notification_id;
        }

        $normalized = self::normalize_recipient_specs((int) $notification_id, $recipients);
        if (is_wp_error($normalized)) {
            MjNotifications::delete((int) $notification_id);
            return $normalized;
        }

        $expanded = self::expand_role_recipients($normalized);
        $result = self::persist_recipients($expanded);
        if (is_wp_error($result)) {
            self::cleanup_created_entities((int) $notification_id);
            return $result;
        }

        return array(
            'notification_id' => (int) $notification_id,
            'recipient_ids' => $result,
        );
    }

    /**
     * @param int $member_id
     * @param array<string,mixed> $args
     * @return array<int,array<string,mixed>>
     */
    public static function get_member_feed($member_id, array $args = array()) {
        $member_id = (int) $member_id;
        if ($member_id <= 0) {
            return array();
        }

        $notifications_table = MjNotifications::get_table_name();
        $recipients_table = MjNotificationRecipients::get_table_name();
        if ($notifications_table === '' || $recipients_table === '') {
            return array();
        }

        global $wpdb;

        $defaults = array(
            'limit' => 20,
            'offset' => 0,
            'statuses' => array(),
            'types' => array(),
            'include_archived' => false,
            'include_drafts' => false,
            'include_expired' => false,
            'order' => 'DESC',
        );
        $args = wp_parse_args($args, $defaults);

        $where = array($wpdb->prepare('r.member_id = %d', $member_id));
        $params = array();

        $allowed_statuses = MjNotificationRecipients::get_statuses();
        $statuses = array();
        foreach ((array) $args['statuses'] as $status) {
            $status = sanitize_key($status);
            if (in_array($status, $allowed_statuses, true)) {
                $statuses[$status] = $status;
            }
        }
        if (!empty($statuses)) {
            $placeholders = implode(',', array_fill(0, count($statuses), '%s'));
            $where[] = sprintf('r.status IN (%s)', $placeholders);
            $params = array_merge($params, array_values($statuses));
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

        if (empty($args['include_archived'])) {
            $where[] = $wpdb->prepare('n.status != %s', MjNotifications::STATUS_ARCHIVED);
        }

        if (empty($args['include_drafts'])) {
            $where[] = $wpdb->prepare('n.status != %s', MjNotifications::STATUS_DRAFT);
        }

        if (empty($args['include_expired'])) {
            $where[] = '(n.expires_at IS NULL OR n.expires_at > %s)';
            $params[] = current_time('mysql', 1);
        }

        $order = strtoupper((string) $args['order']) === 'ASC' ? 'ASC' : 'DESC';
        $limit = max(1, min(200, (int) $args['limit']));
        $offset = max(0, (int) $args['offset']);

        $sql = sprintf(
            'SELECT r.id AS recipient_id, r.notification_id, r.status AS recipient_status, r.read_at, r.delivered_at, r.created_at AS recipient_created_at, r.extra_meta,
                    n.id AS notification_id_alias, n.uid, n.type, n.status AS notification_status, n.priority, n.title, n.excerpt, n.payload, n.url, n.context, n.source, n.created_at, n.expires_at
             FROM %1$s r
             INNER JOIN %2$s n ON n.id = r.notification_id
             WHERE %3$s
             ORDER BY n.created_at %4$s, r.id %4$s
             LIMIT %5$d OFFSET %6$d',
            $recipients_table,
            $notifications_table,
            implode(' AND ', $where),
            $order,
            $limit,
            $offset
        );

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        $rows = $wpdb->get_results($sql);
        if (empty($rows)) {
            return array();
        }

        return array_map(array(__CLASS__, 'format_feed_row'), $rows);
    }
    /**
     * @param int $user_id
     * @param array<string,mixed> $args
     * @return array<int,array<string,mixed>>
     */
    public static function get_user_feed($user_id, array $args = array()) {
        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            return array();
        }

        $notifications_table = MjNotifications::get_table_name();
        $recipients_table = MjNotificationRecipients::get_table_name();
        if ($notifications_table === '' || $recipients_table === '') {
            return array();
        }

        global $wpdb;

        $defaults = array(
            'limit' => 20,
            'offset' => 0,
            'statuses' => array(),
            'types' => array(),
            'include_archived' => false,
            'include_drafts' => false,
            'include_expired' => false,
            'order' => 'DESC',
        );
        $args = wp_parse_args($args, $defaults);

        $where = array($wpdb->prepare('r.user_id = %d', $user_id));
        $params = array();

        $allowed_statuses = MjNotificationRecipients::get_statuses();
        $statuses = array();
        foreach ((array) $args['statuses'] as $status) {
            $status = sanitize_key($status);
            if (in_array($status, $allowed_statuses, true)) {
                $statuses[$status] = $status;
            }
        }
        if (!empty($statuses)) {
            $placeholders = implode(',', array_fill(0, count($statuses), '%s'));
            $where[] = sprintf('r.status IN (%s)', $placeholders);
            $params = array_merge($params, array_values($statuses));
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

        if (empty($args['include_archived'])) {
            $where[] = $wpdb->prepare('n.status != %s', MjNotifications::STATUS_ARCHIVED);
        }

        if (empty($args['include_drafts'])) {
            $where[] = $wpdb->prepare('n.status != %s', MjNotifications::STATUS_DRAFT);
        }

        if (empty($args['include_expired'])) {
            $where[] = '(n.expires_at IS NULL OR n.expires_at > %s)';
            $params[] = current_time('mysql', 1);
        }

        $order = strtoupper((string) $args['order']) === 'ASC' ? 'ASC' : 'DESC';
        $limit = max(1, min(200, (int) $args['limit']));
        $offset = max(0, (int) $args['offset']);

        $sql = sprintf(
            'SELECT r.id AS recipient_id, r.notification_id, r.status AS recipient_status, r.read_at, r.delivered_at, r.created_at AS recipient_created_at, r.extra_meta,
                    n.id AS notification_id_alias, n.uid, n.type, n.status AS notification_status, n.priority, n.title, n.excerpt, n.payload, n.url, n.context, n.source, n.created_at, n.expires_at
             FROM %1$s r
             INNER JOIN %2$s n ON n.id = r.notification_id
             WHERE %3$s
             ORDER BY n.created_at %4$s, r.id %4$s
             LIMIT %5$d OFFSET %6$d',
            $recipients_table,
            $notifications_table,
            implode(' AND ', $where),
            $order,
            $limit,
            $offset
        );

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        $rows = $wpdb->get_results($sql);
        if (empty($rows)) {
            return array();
        }

        return array_map(array(__CLASS__, 'format_feed_row'), $rows);
    }
    /**
     * @param int $member_id
     * @param array<int,int> $notification_ids
     * @param string|null $timestamp
     * @return int|WP_Error
     */
    public static function mark_member_notifications_read($member_id, array $notification_ids = array(), $timestamp = null) {
        return MjNotificationRecipients::mark_read_for_member($member_id, $notification_ids, $timestamp);
    }

    /**
     * @param int $user_id
     * @param array<int,int> $notification_ids
     * @param string|null $timestamp
     * @return int|WP_Error
     */
    public static function mark_user_notifications_read($user_id, array $notification_ids = array(), $timestamp = null) {
        return MjNotificationRecipients::mark_read_for_user($user_id, $notification_ids, $timestamp);
    }

    /**
     * @param array<int,int> $recipient_ids
     * @param string $status
     * @param string|null $timestamp
     * @return int|WP_Error
     */
    public static function mark_recipient_status(array $recipient_ids, $status, $timestamp = null) {
        return MjNotificationRecipients::mark_status($recipient_ids, $status, $timestamp);
    }

    /**
     * @param int $member_id
     * @return int
     */
    public static function get_member_unread_count($member_id) {
        return MjNotificationRecipients::get_unread_count_for_member($member_id);
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
    public static function get_user_unread_count($user_id, array $args = array()) {
        return MjNotificationRecipients::get_unread_count_for_user($user_id, $args);
    }

    /**
     * @param array<int,object> $rows
     * @return array<string,mixed>
     */
    private static function format_feed_row($row) {
        $payload = array();
        if (isset($row->payload)) {
            $decoded = json_decode((string) $row->payload, true);
            $payload = is_array($decoded) ? $decoded : array();
        }

        $extra_meta = array();
        if (isset($row->extra_meta)) {
            $decoded_meta = json_decode((string) $row->extra_meta, true);
            $extra_meta = is_array($decoded_meta) ? $decoded_meta : array();
        }

        return array(
            'recipient_id' => (int) $row->recipient_id,
            'notification_id' => (int) $row->notification_id,
            'recipient_status' => sanitize_key($row->recipient_status),
            'read_at' => $row->read_at ? (string) $row->read_at : null,
            'delivered_at' => $row->delivered_at ? (string) $row->delivered_at : null,
            'recipient_created_at' => $row->recipient_created_at ? (string) $row->recipient_created_at : null,
            'extra_meta' => $extra_meta,
            'notification' => array(
                'id' => (int) $row->notification_id_alias,
                'uid' => (string) $row->uid,
                'type' => sanitize_title($row->type),
                'status' => sanitize_key($row->notification_status),
                'priority' => (int) $row->priority,
                'title' => (string) $row->title,
                'excerpt' => (string) $row->excerpt,
                'payload' => $payload,
                'url' => (string) $row->url,
                'context' => (string) $row->context,
                'source' => (string) $row->source,
                'created_at' => (string) $row->created_at,
                'expires_at' => $row->expires_at ? (string) $row->expires_at : null,
            ),
        );
    }

    /**
     * @param int $notification_id
     * @param array<int,mixed> $specs
     * @return array<int,array<string,mixed>>|WP_Error
     */
    private static function normalize_recipient_specs($notification_id, array $specs) {
        $normalized = array();
        $dedupe = array();

        foreach ($specs as $index => $spec) {
            $row = self::normalize_single_recipient($notification_id, $spec);
            if (is_wp_error($row)) {
                return $row;
            }

            $key = self::recipient_uniqueness_key($row);
            if (isset($dedupe[$key])) {
                continue;
            }

            $dedupe[$key] = true;
            $normalized[] = $row;
        }

        if (empty($normalized)) {
            return new WP_Error('mj_notification_no_valid_recipient', __('Aucun destinataire valide.', 'mj-member'));
        }

        return $normalized;
    }

    /**
     * @param int $notification_id
     * @param mixed $spec
     * @return array<string,mixed>|WP_Error
     */
    private static function normalize_single_recipient($notification_id, $spec) {
        $row = array('notification_id' => $notification_id);

        if (is_numeric($spec)) {
            $row['member_id'] = (int) $spec;
        } elseif (is_string($spec)) {
            $role = sanitize_key($spec);
            if ($role === '') {
                return new WP_Error('mj_notification_recipient_invalid', __('Destinataire invalide.', 'mj-member'));
            }
            $row['role'] = $role;
        } elseif (is_array($spec)) {
            $whitelist = array('member_id', 'user_id', 'role', 'status', 'read_at', 'delivered_at', 'extra_meta');
            foreach ($whitelist as $key) {
                if (!array_key_exists($key, $spec)) {
                    continue;
                }

                $row[$key] = $spec[$key];
            }
        } else {
            return new WP_Error('mj_notification_recipient_invalid', __('Destinataire invalide.', 'mj-member'));
        }

        $has_target = (isset($row['member_id']) && (int) $row['member_id'] > 0)
            || (isset($row['user_id']) && (int) $row['user_id'] > 0)
            || (isset($row['role']) && $row['role'] !== '');

        if (!$has_target) {
            return new WP_Error('mj_notification_recipient_missing_target', __('Destinataire sans cible.', 'mj-member'));
        }

        return $row;
    }

    /**
     * @param array<string,mixed> $row
     * @return string
     */
    private static function recipient_uniqueness_key(array $row) {
        if (isset($row['member_id']) && (int) $row['member_id'] > 0) {
            return 'member:' . (int) $row['member_id'];
        }

        if (isset($row['user_id']) && (int) $row['user_id'] > 0) {
            return 'user:' . (int) $row['user_id'];
        }

        if (isset($row['role']) && $row['role'] !== '') {
            return 'role:' . $row['role'];
        }

        return uniqid('recipient-', false);
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,int|WP_Error>|WP_Error
     */
    private static function persist_recipients(array $rows) {
        $inserted = array();

        foreach ($rows as $row) {
            $result = MjNotificationRecipients::create($row);
            if (is_wp_error($result)) {
                return $result;
            }
            $inserted[] = (int) $result;
        }

        return $inserted;
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private static function expand_role_recipients(array $rows) {
        $expanded = array();

        foreach ($rows as $row) {
            if (!isset($row['role']) || !empty($row['user_id']) || !empty($row['member_id'])) {
                $expanded[] = $row;
                continue;
            }

            $role = sanitize_key((string) $row['role']);
            if ($role === '') {
                $expanded[] = $row;
                continue;
            }

            $role_targets = self::collect_role_targets($role);
            if (empty($role_targets)) {
                $expanded[] = $row;
                continue;
            }

            foreach ($role_targets as $target) {
                $expanded[] = array_merge($row, $target);
            }
        }

        return $expanded;
    }

    /**
     * @param string $role
     * @return array<int,array<string,mixed>>
     */
    private static function collect_role_targets($role) {
        $role = sanitize_key($role);
        if ($role === '') {
            return array();
        }

        $results = array();
        $seen = array();

        // Chercher les membres MJ Member avec ce rôle
        if (class_exists(MjMembers::class)) {
            global $wpdb;
            $table = MjMembers::getTableName(MjMembers::TABLE_NAME);
            if (!empty($table)) {
                $members = $wpdb->get_results($wpdb->prepare(
                    "SELECT id, wp_user_id FROM {$table} WHERE role = %s AND status = 'active'",
                    $role
                ));

                if (!empty($members)) {
                    foreach ($members as $member) {
                        $member_id = (int) $member->id;
                        if ($member_id <= 0 || isset($seen['member:' . $member_id])) {
                            continue;
                        }

                        $seen['member:' . $member_id] = true;

                        $spec = array('member_id' => $member_id);

                        if (!empty($member->wp_user_id)) {
                            $spec['user_id'] = (int) $member->wp_user_id;
                        }

                        $results[] = $spec;
                    }
                }
            }
        }

        return $results;
    }

    /**
     * @param int $notification_id
     * @return void
     */
    private static function cleanup_created_entities($notification_id) {
        global $wpdb;

        $notifications_table = MjNotifications::get_table_name();
        $recipients_table = MjNotificationRecipients::get_table_name();

        if ($recipients_table !== '') {
            $wpdb->delete($recipients_table, array('notification_id' => (int) $notification_id), array('%d'));
        }

        if ($notifications_table !== '') {
            $wpdb->delete($notifications_table, array('id' => (int) $notification_id), array('%d'));
        }
    }
}
