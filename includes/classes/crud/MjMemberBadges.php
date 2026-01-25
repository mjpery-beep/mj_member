<?php

namespace Mj\Member\Classes\Crud;

use Mj\Member\Classes\MjTools;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

final class MjMemberBadges extends MjTools implements CrudRepositoryInterface
{
    private const TABLE = 'mj_member_badges';

    public const STATUS_AWARDED = 'awarded';
    public const STATUS_REVOKED = 'revoked';

    /**
     * @return array<int,string>
     */
    public static function statuses(): array
    {
        return array(self::STATUS_AWARDED, self::STATUS_REVOKED);
    }

    public static function get_status_labels(): array
    {
        return array(
            self::STATUS_AWARDED => __('Attribué', 'mj-member'),
            self::STATUS_REVOKED => __('Révoqué', 'mj-member'),
        );
    }

    public static function table_name(): string
    {
        if (function_exists('mj_member_get_member_badges_table_name')) {
            return mj_member_get_member_badges_table_name();
        }

        return self::getTableName(self::TABLE);
    }

    /**
     * @param array<string,mixed> $args
     * @return array<int,array<string,mixed>>
     */
    public static function get_all(array $args = array())
    {
        global $wpdb;
        $table = self::table_name();
        $badgesTable = MjBadges::table_name();
        $membersTable = MjMembers::getTableName(MjMembers::TABLE_NAME);

        $defaults = array(
            'member_id' => 0,
            'badge_id' => 0,
            'status' => '',
            'limit' => 0,
            'offset' => 0,
            'orderby' => 'awarded_at',
            'order' => 'DESC',
            'search' => '',
        );
        $args = wp_parse_args($args, $defaults);

        $select = "SELECT mb.*, b.label AS badge_label, b.slug AS badge_slug, b.icon AS badge_icon, m.first_name, m.last_name, m.role AS member_role FROM {$table} mb" .
            " LEFT JOIN {$badgesTable} b ON b.id = mb.badge_id" .
            " LEFT JOIN {$membersTable} m ON m.id = mb.member_id";

        $where = array();
        $params = array();

        if (!empty($args['member_id'])) {
            $where[] = 'mb.member_id = %d';
            $params[] = (int) $args['member_id'];
        }

        if (!empty($args['badge_id'])) {
            $where[] = 'mb.badge_id = %d';
            $params[] = (int) $args['badge_id'];
        }

        if (!empty($args['status'])) {
            $status = self::normalize_status($args['status']);
            if ($status !== '') {
                $where[] = 'mb.status = %s';
                $params[] = $status;
            }
        }

        if (!empty($args['search'])) {
            $like = '%' . $wpdb->esc_like((string) $args['search']) . '%';
            $where[] = '(b.label LIKE %s OR m.first_name LIKE %s OR m.last_name LIKE %s)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if (!empty($where)) {
            $select .= ' WHERE ' . implode(' AND ', $where);
        }

        $allowedOrderBy = array('awarded_at', 'updated_at', 'status');
        $orderby = sanitize_key((string) $args['orderby']);
        if ($orderby === 'badge_label') {
            $select .= ' ORDER BY b.label ';
        } elseif ($orderby === 'member_name') {
            $select .= ' ORDER BY m.last_name ';
        } else {
            if (!in_array($orderby, $allowedOrderBy, true)) {
                $orderby = 'awarded_at';
            }
            $select .= ' ORDER BY mb.' . $orderby . ' ';
        }

        $order = strtoupper((string) $args['order']) === 'ASC' ? 'ASC' : 'DESC';
        $select .= $order;

        $limit = (int) $args['limit'];
        $offset = max(0, (int) $args['offset']);
        if ($limit > 0) {
            $select .= $wpdb->prepare(' LIMIT %d OFFSET %d', $limit, $offset);
        }

        if (!empty($params)) {
            $select = $wpdb->prepare($select, $params);
        }

        $rows = $wpdb->get_results($select, ARRAY_A);
        if (empty($rows)) {
            return array();
        }

        return array_map(array(__CLASS__, 'format_row'), $rows);
    }

    /**
     * @param array<string,mixed> $args
     */
    public static function count(array $args = array())
    {
        global $wpdb;
        $table = self::table_name();

        $defaults = array(
            'member_id' => 0,
            'badge_id' => 0,
            'status' => '',
            'search' => '',
        );
        $args = wp_parse_args($args, $defaults);

        $sql = "SELECT COUNT(*) FROM {$table} mb";
        $where = array();
        $params = array();

        if (!empty($args['member_id'])) {
            $where[] = 'mb.member_id = %d';
            $params[] = (int) $args['member_id'];
        }

        if (!empty($args['badge_id'])) {
            $where[] = 'mb.badge_id = %d';
            $params[] = (int) $args['badge_id'];
        }

        if (!empty($args['status'])) {
            $status = self::normalize_status($args['status']);
            if ($status !== '') {
                $where[] = 'mb.status = %s';
                $params[] = $status;
            }
        }

        if (!empty($args['search'])) {
            $like = '%' . $wpdb->esc_like((string) $args['search']) . '%';
            $badgesTable = MjBadges::table_name();
            $badgesTable = MjBadges::table_name();
            $membersTable = MjMembers::getTableName(MjMembers::TABLE_NAME);
            $sql .= " LEFT JOIN {$badgesTable} b ON b.id = mb.badge_id";
            $sql .= " LEFT JOIN {$membersTable} m ON m.id = mb.member_id";
            $where[] = '(b.label LIKE %s OR m.first_name LIKE %s OR m.last_name LIKE %s)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        return (int) $wpdb->get_var($sql);
    }

    /**
     * @param mixed $data
     * @return int|WP_Error
     */
    public static function create($data)
    {
        $payload = self::sanitize_payload($data, false);
        if (is_wp_error($payload)) {
            return $payload;
        }

        return self::insert_or_update_existing($payload);
    }

    /**
     * @param int $id
     * @param mixed $data
     * @return true|WP_Error
     */
    public static function update($id, $data)
    {
        global $wpdb;
        $table = self::table_name();
        $id = (int) $id;
        if ($id <= 0) {
            return new WP_Error('mj_member_badge_assign_invalid_id', __('Identifiant invalide.', 'mj-member'));
        }

        $payload = self::sanitize_payload($data, true, $id);
        if (is_wp_error($payload)) {
            return $payload;
        }

        if (empty($payload)) {
            return true;
        }

        $payload['updated_at'] = current_time('mysql');

        $updated = $wpdb->update($table, $payload, array('id' => $id), self::get_format($payload), array('%d'));
        if ($updated === false) {
            return new WP_Error('mj_member_badge_assign_update_failed', __('Impossible de mettre à jour l\'attribution.', 'mj-member'));
        }

        return true;
    }

    /**
     * @param int $id
     * @return true|WP_Error
     */
    public static function delete($id)
    {
        global $wpdb;
        $table = self::table_name();
        $id = (int) $id;
        if ($id <= 0) {
            return new WP_Error('mj_member_badge_assign_invalid_id', __('Identifiant invalide.', 'mj-member'));
        }

        $deleted = $wpdb->delete($table, array('id' => $id), array('%d'));
        if ($deleted === false) {
            return new WP_Error('mj_member_badge_assign_delete_failed', __('Impossible de supprimer l\'attribution.', 'mj-member'));
        }

        return true;
    }

    /**
     * @param int $memberId
     * @return array<int,array<string,mixed>>
     */
    public static function get_for_member(int $memberId): array
    {
        return self::get_all(array('member_id' => $memberId));
    }

    /**
     * @param int $badgeId
     * @return array<int,array<string,mixed>>
     */
    public static function get_for_badge(int $badgeId): array
    {
        return self::get_all(array('badge_id' => $badgeId));
    }

    /**
     * @param array<string,mixed>|null $row
     * @return array<string,mixed>
     */
    private static function format_row($row): array
    {
        $id = isset($row['id']) ? (int) $row['id'] : 0;
        $memberId = isset($row['member_id']) ? (int) $row['member_id'] : 0;
        $badgeId = isset($row['badge_id']) ? (int) $row['badge_id'] : 0;
        $status = self::normalize_status($row['status'] ?? self::STATUS_AWARDED) ?: self::STATUS_AWARDED;

        $memberName = trim(sprintf('%s %s', isset($row['first_name']) ? (string) $row['first_name'] : '', isset($row['last_name']) ? (string) $row['last_name'] : ''));
        if ($memberName === '') {
            $memberName = $memberId > 0 ? sprintf(__('Membre #%d', 'mj-member'), $memberId) : '';
        }

        return array(
            'id' => $id,
            'badge_id' => $badgeId,
            'member_id' => $memberId,
            'badge_label' => isset($row['badge_label']) ? sanitize_text_field((string) $row['badge_label']) : '',
            'badge_slug' => isset($row['badge_slug']) ? sanitize_title((string) $row['badge_slug']) : '',
            'badge_icon' => isset($row['badge_icon']) ? sanitize_key((string) $row['badge_icon']) : '',
            'member_name' => $memberName,
            'member_role' => isset($row['member_role']) ? sanitize_key((string) $row['member_role']) : '',
            'status' => $status,
            'notes' => isset($row['notes']) ? sanitize_textarea_field((string) $row['notes']) : '',
            'evidence' => isset($row['evidence']) ? wp_kses_post((string) $row['evidence']) : '',
            'awarded_at' => isset($row['awarded_at']) ? (string) $row['awarded_at'] : '',
            'updated_at' => isset($row['updated_at']) ? (string) $row['updated_at'] : '',
            'awarded_by_user_id' => isset($row['awarded_by_user_id']) ? (int) $row['awarded_by_user_id'] : 0,
            'awarded_by_member_id' => isset($row['awarded_by_member_id']) ? (int) $row['awarded_by_member_id'] : 0,
            'revoked_at' => isset($row['revoked_at']) ? (string) $row['revoked_at'] : '',
        );
    }

    /**
     * @param mixed $value
     */
    private static function normalize_status($value): string
    {
        $candidate = sanitize_key((string) $value);
        if ($candidate === self::STATUS_REVOKED) {
            return self::STATUS_REVOKED;
        }

        if ($candidate === self::STATUS_AWARDED) {
            return self::STATUS_AWARDED;
        }

        return '';
    }

    /**
     * @param mixed $data
     * @param bool $isUpdate
     * @param int $currentId
     * @return array<string,mixed>|WP_Error
     */
    private static function sanitize_payload($data, bool $isUpdate = false, int $currentId = 0)
    {
        $payload = array();

        if (isset($data['badge_id'])) {
            $payload['badge_id'] = (int) $data['badge_id'];
            if ($payload['badge_id'] <= 0) {
                return new WP_Error('mj_member_badge_assign_badge_required', __('Badge requis.', 'mj-member'));
            }
        } elseif (!$isUpdate) {
            return new WP_Error('mj_member_badge_assign_badge_required', __('Badge requis.', 'mj-member'));
        }

        if (isset($data['member_id'])) {
            $payload['member_id'] = (int) $data['member_id'];
            if ($payload['member_id'] <= 0) {
                return new WP_Error('mj_member_badge_assign_member_required', __('Membre requis.', 'mj-member'));
            }
        } elseif (!$isUpdate) {
            return new WP_Error('mj_member_badge_assign_member_required', __('Membre requis.', 'mj-member'));
        }

        $statusProvided = false;
        if (isset($data['status'])) {
            $status = self::normalize_status($data['status']);
            if ($status === '') {
                $status = self::STATUS_AWARDED;
            }
            $payload['status'] = $status;
            $statusProvided = true;
        }

        if (!$isUpdate && !$statusProvided) {
            $payload['status'] = self::STATUS_AWARDED;
        }

        if (isset($data['notes'])) {
            $payload['notes'] = sanitize_textarea_field((string) $data['notes']);
        }

        if (isset($data['evidence'])) {
            $payload['evidence'] = wp_kses_post((string) $data['evidence']);
        }

        if (isset($data['awarded_by_user_id'])) {
            $payload['awarded_by_user_id'] = (int) $data['awarded_by_user_id'];
        }

        if (isset($data['awarded_by_member_id'])) {
            $payload['awarded_by_member_id'] = (int) $data['awarded_by_member_id'];
        }

        if (isset($data['awarded_at'])) {
            $payload['awarded_at'] = self::normalize_datetime($data['awarded_at']);
        }

        $revokedProvided = array_key_exists('revoked_at', $data);
        if ($revokedProvided) {
            $payload['revoked_at'] = self::normalize_datetime($data['revoked_at']);
        }

        if (isset($payload['status'])) {
            if ($payload['status'] === self::STATUS_REVOKED && !$revokedProvided) {
                $payload['revoked_at'] = current_time('mysql');
            }

            if ($payload['status'] === self::STATUS_AWARDED && !$revokedProvided) {
                $payload['revoked_at'] = null;
            }
        }

        return $payload;
    }

    /**
     * @param mixed $datetime
     */
    private static function normalize_datetime($datetime): ?string
    {
        if ($datetime === null || $datetime === '') {
            return null;
        }

        if ($datetime instanceof \DateTimeInterface) {
            return $datetime->format('Y-m-d H:i:s');
        }

        $candidate = strtotime((string) $datetime);
        if ($candidate === false) {
            return null;
        }

        return gmdate('Y-m-d H:i:s', $candidate);
    }

    /**
     * @param array<string,mixed> $payload
     * @return int|WP_Error
     */
    private static function insert_or_update_existing(array $payload)
    {
        global $wpdb;
        $table = self::table_name();

        $badgeId = isset($payload['badge_id']) ? (int) $payload['badge_id'] : 0;
        $memberId = isset($payload['member_id']) ? (int) $payload['member_id'] : 0;
        if ($badgeId <= 0 || $memberId <= 0) {
            return new WP_Error('mj_member_badge_assign_invalid_ids', __('Badge ou membre invalide.', 'mj-member'));
        }

        $existingId = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE badge_id = %d AND member_id = %d",
            $badgeId,
            $memberId
        ));

        $payload['updated_at'] = current_time('mysql');
        if (!isset($payload['awarded_at']) || $payload['awarded_at'] === null) {
            $payload['awarded_at'] = current_time('mysql');
        }

        if ($existingId > 0) {
            $update = $wpdb->update($table, $payload, array('id' => $existingId), self::get_format($payload), array('%d'));
            if ($update === false) {
                return new WP_Error('mj_member_badge_assign_update_failed', __('Impossible de mettre à jour l\'attribution.', 'mj-member'));
            }
            return $existingId;
        }

        $payload['created_at'] = current_time('mysql');
        $inserted = $wpdb->insert($table, $payload, self::get_format($payload));
        if ($inserted === false) {
            return new WP_Error('mj_member_badge_assign_insert_failed', __('Impossible d\'attribuer le badge.', 'mj-member'));
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<int,string>
     */
    private static function get_format(array $payload): array
    {
        $map = array(
            'badge_id' => '%d',
            'member_id' => '%d',
            'status' => '%s',
            'notes' => '%s',
            'evidence' => '%s',
            'awarded_by_user_id' => '%d',
            'awarded_by_member_id' => '%d',
            'awarded_at' => '%s',
            'updated_at' => '%s',
            'created_at' => '%s',
            'revoked_at' => '%s',
        );

        $formats = array();
        foreach ($payload as $key => $_) {
            if (isset($map[$key])) {
                $formats[] = $map[$key];
            }
        }

        return $formats;
    }
}
