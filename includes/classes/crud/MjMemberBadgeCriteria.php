<?php

namespace Mj\Member\Classes\Crud;

use Mj\Member\Classes\MjTools;
use Mj\Member\Classes\Crud\MjMemberXp;
use Mj\Member\Classes\Crud\MjMemberCoins;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

final class MjMemberBadgeCriteria extends MjTools implements CrudRepositoryInterface
{
    private const TABLE = 'mj_member_badge_criteria';

    public const STATUS_AWARDED = 'awarded';
    public const STATUS_REVOKED = 'revoked';

    /**
     * @return array<int,string>
     */
    public static function statuses(): array
    {
        return array(self::STATUS_AWARDED, self::STATUS_REVOKED);
    }

    /**
     * @return array<string,string>
     */
    public static function get_status_labels(): array
    {
        return array(
            self::STATUS_AWARDED => __('Attribué', 'mj-member'),
            self::STATUS_REVOKED => __('Révoqué', 'mj-member'),
        );
    }

    public static function table_name(): string
    {
        if (function_exists('mj_member_get_member_badge_criteria_table_name')) {
            return mj_member_get_member_badge_criteria_table_name();
        }

        return self::getTableName(self::TABLE);
    }

    /**
     * @param array<string,mixed> $args
     * @return array<int,array<string,mixed>>
     */
    public static function get_all(array $args = array())
    {
        $args = wp_parse_args($args, array(
            'member_id' => 0,
            'badge_id' => 0,
            'criterion_id' => 0,
            'status' => '',
            'orderby' => 'awarded_at',
            'order' => 'DESC',
            'limit' => 0,
            'offset' => 0,
        ));

        global $wpdb;
        $table = self::table_name();

        $where = array();
        $params = array();

        if (!empty($args['member_id'])) {
            $where[] = 'member_id = %d';
            $params[] = (int) $args['member_id'];
        }

        if (!empty($args['badge_id'])) {
            $where[] = 'badge_id = %d';
            $params[] = (int) $args['badge_id'];
        }

        if (!empty($args['criterion_id'])) {
            $where[] = 'criterion_id = %d';
            $params[] = (int) $args['criterion_id'];
        }

        if (!empty($args['status'])) {
            $status = self::normalize_status($args['status']);
            if ($status !== '') {
                $where[] = 'status = %s';
                $params[] = $status;
            }
        }

        $sql = "SELECT * FROM {$table}";
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $allowedOrderBy = array('awarded_at', 'updated_at', 'created_at', 'status', 'id', 'revoked_at');
        $orderby = sanitize_key((string) $args['orderby']);
        if (!in_array($orderby, $allowedOrderBy, true)) {
            $orderby = 'awarded_at';
        }

        $order = strtoupper((string) $args['order']) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY {$orderby} {$order}";

        $limit = (int) $args['limit'];
        $offset = max(0, (int) $args['offset']);
        if ($limit > 0) {
            $sql .= $wpdb->prepare(' LIMIT %d OFFSET %d', $limit, $offset);
        }

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        $rows = $wpdb->get_results($sql, ARRAY_A);
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
        $args = wp_parse_args($args, array(
            'member_id' => 0,
            'badge_id' => 0,
            'criterion_id' => 0,
            'status' => '',
        ));

        global $wpdb;
        $table = self::table_name();

        $where = array();
        $params = array();

        if (!empty($args['member_id'])) {
            $where[] = 'member_id = %d';
            $params[] = (int) $args['member_id'];
        }

        if (!empty($args['badge_id'])) {
            $where[] = 'badge_id = %d';
            $params[] = (int) $args['badge_id'];
        }

        if (!empty($args['criterion_id'])) {
            $where[] = 'criterion_id = %d';
            $params[] = (int) $args['criterion_id'];
        }

        if (!empty($args['status'])) {
            $status = self::normalize_status($args['status']);
            if ($status !== '') {
                $where[] = 'status = %s';
                $params[] = $status;
            }
        }

        $sql = "SELECT COUNT(*) FROM {$table}";
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        return (int) $wpdb->get_var($sql);
    }

    /**
     * @param array<string,mixed> $data
     * @return int|WP_Error
     */
    public static function create($data)
    {
        global $wpdb;
        $table = self::table_name();

        $payload = self::sanitize_payload($data, false);
        if (is_wp_error($payload)) {
            return $payload;
        }

        $payload['created_at'] = current_time('mysql');
        $payload['updated_at'] = current_time('mysql');
        if (!isset($payload['awarded_at']) || $payload['awarded_at'] === null) {
            $payload['awarded_at'] = current_time('mysql');
        }

        $inserted = $wpdb->insert($table, $payload, self::get_format($payload));
        if ($inserted === false) {
            return new WP_Error('mj_member_badge_criteria_insert_failed', __('Impossible d’assigner le critère.', 'mj-member'));
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * @param int $id
     * @param array<string,mixed> $data
     * @return true|WP_Error
     */
    public static function update($id, $data)
    {
        global $wpdb;
        $table = self::table_name();
        $id = (int) $id;
        if ($id <= 0) {
            return new WP_Error('mj_member_badge_criteria_invalid_id', __('Identifiant invalide.', 'mj-member'));
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
            return new WP_Error('mj_member_badge_criteria_update_failed', __('Impossible de mettre à jour le critère attribué.', 'mj-member'));
        }

        return true;
    }

    public static function delete($id)
    {
        global $wpdb;
        $table = self::table_name();
        $id = (int) $id;
        if ($id <= 0) {
            return new WP_Error('mj_member_badge_criteria_invalid_id', __('Identifiant invalide.', 'mj-member'));
        }

        $deleted = $wpdb->delete($table, array('id' => $id), array('%d'));
        if ($deleted === false) {
            return new WP_Error('mj_member_badge_criteria_delete_failed', __('Impossible de supprimer le critère attribué.', 'mj-member'));
        }

        return true;
    }

    /**
     * @param int $memberId
     * @param int $badgeId
     * @return array<int,array<string,mixed>>
     */
    public static function get_for_member_badge(int $memberId, int $badgeId): array
    {
        $memberId = (int) $memberId;
        $badgeId = (int) $badgeId;
        if ($memberId <= 0 || $badgeId <= 0) {
            return array();
        }

        global $wpdb;
        $table = self::table_name();

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE member_id = %d AND badge_id = %d",
                $memberId,
                $badgeId
            ),
            ARRAY_A
        );

        if (empty($rows)) {
            return array();
        }

        return array_map(array(__CLASS__, 'format_row'), $rows);
    }

    /**
     * @param int $memberId
     * @param int $badgeId
     * @param array<int> $criterionIds
     * @param int $awardedByUserId
     * @return true|WP_Error
     */
    public static function sync_awards(int $memberId, int $badgeId, array $criterionIds, int $awardedByUserId = 0)
    {
        $memberId = (int) $memberId;
        $badgeId = (int) $badgeId;
        if ($memberId <= 0 || $badgeId <= 0) {
            return new WP_Error('mj_member_badge_criteria_invalid_params', __('Membre ou badge invalide.', 'mj-member'));
        }

        $criterionIds = array_values(array_unique(array_map('intval', $criterionIds)));
        $criterionIds = array_filter($criterionIds, static function ($id) {
            return $id > 0;
        });

        $validCriterionIds = empty($criterionIds)
            ? array()
            : MjBadgeCriteria::filter_ids_for_badge($badgeId, $criterionIds);

        $selected = array_fill_keys($validCriterionIds, true);

        global $wpdb;
        $table = self::table_name();

        $existingRows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE member_id = %d AND badge_id = %d",
                $memberId,
                $badgeId
            ),
            ARRAY_A
        );

        $processed = array();
        $now = current_time('mysql');

        // XP and Coins tracking: collect newly awarded and revoked criteria IDs
        $criteriaIdsNewlyAwarded = array();
        $criteriaIdsRevoked = array();

        if (is_array($existingRows)) {
            foreach ($existingRows as $row) {
                $id = isset($row['id']) ? (int) $row['id'] : 0;
                if ($id <= 0) {
                    continue;
                }
                $criterionId = isset($row['criterion_id']) ? (int) $row['criterion_id'] : 0;
                $currentStatus = isset($row['status']) ? (string) $row['status'] : self::STATUS_AWARDED;

                if (isset($selected[$criterionId])) {
                    $updates = array(
                        'status' => self::STATUS_AWARDED,
                        'updated_at' => $now,
                        'revoked_at' => null,
                    );

                    // Track XP and Coins: criterion was revoked and is now being re-awarded
                    if ($currentStatus !== self::STATUS_AWARDED) {
                        $updates['awarded_at'] = $now;
                        $criteriaIdsNewlyAwarded[] = $criterionId;
                    }

                    if ($awardedByUserId > 0) {
                        $updates['awarded_by_user_id'] = $awardedByUserId;
                    }

                    $needsRevokedNull = array_key_exists('revoked_at', $updates) && $updates['revoked_at'] === null;
                    $wpdb->update(
                        $table,
                        $updates,
                        array('id' => $id),
                        self::get_format($updates),
                        array('%d')
                    );

                    if ($needsRevokedNull) {
                        $wpdb->query($wpdb->prepare("UPDATE {$table} SET revoked_at = NULL WHERE id = %d", $id));
                    }

                    $processed[$criterionId] = true;
                } else {
                    // Track XP and Coins: criterion was awarded and is now being revoked
                    if ($currentStatus !== self::STATUS_REVOKED) {
                        $revokeUpdates = array(
                            'status' => self::STATUS_REVOKED,
                            'revoked_at' => $now,
                            'updated_at' => $now,
                        );

                        $wpdb->update(
                            $table,
                            $revokeUpdates,
                            array('id' => $id),
                            self::get_format($revokeUpdates),
                            array('%d')
                        );

                        $criteriaIdsRevoked[] = $criterionId;
                    }
                }
            }
        }

        foreach ($selected as $criterionId => $_) {
            if (isset($processed[$criterionId])) {
                continue;
            }

            $payload = array(
                'badge_id' => $badgeId,
                'criterion_id' => $criterionId,
                'member_id' => $memberId,
                'status' => self::STATUS_AWARDED,
                'awarded_at' => $now,
            );

            if ($awardedByUserId > 0) {
                $payload['awarded_by_user_id'] = $awardedByUserId;
            }

            $result = self::create($payload);
            if (is_wp_error($result)) {
                return $result;
            }

            // Track XP and Coins: brand new criterion awarded
            $criteriaIdsNewlyAwarded[] = $criterionId;
        }

        // Apply XP and Coins changes
        $criteriaNewlyAwardedCount = count($criteriaIdsNewlyAwarded);
        $criteriaRevokedCount = count($criteriaIdsRevoked);

        if ($criteriaNewlyAwardedCount > 0 || $criteriaRevokedCount > 0) {
            // XP: use count-based award (fixed XP per criterion)
            if ($criteriaNewlyAwardedCount > 0) {
                MjMemberXp::awardForCriteria($memberId, $criteriaNewlyAwardedCount);
            }
            if ($criteriaRevokedCount > 0) {
                MjMemberXp::revokeForCriteria($memberId, $criteriaRevokedCount);
            }

            // Coins: use criterion-specific coins values
            if (!empty($criteriaIdsNewlyAwarded)) {
                MjMemberCoins::awardForCriteria($memberId, $criteriaIdsNewlyAwarded);
            }
            if (!empty($criteriaIdsRevoked)) {
                MjMemberCoins::revokeForCriteria($memberId, $criteriaIdsRevoked);
            }
        }

        return true;
    }

    /**
     * @param array<string,mixed>|null $row
     * @return array<string,mixed>
     */
    private static function format_row($row): array
    {
        return array(
            'id' => isset($row['id']) ? (int) $row['id'] : 0,
            'badge_id' => isset($row['badge_id']) ? (int) $row['badge_id'] : 0,
            'criterion_id' => isset($row['criterion_id']) ? (int) $row['criterion_id'] : 0,
            'member_id' => isset($row['member_id']) ? (int) $row['member_id'] : 0,
            'status' => self::normalize_status($row['status'] ?? self::STATUS_AWARDED) ?: self::STATUS_AWARDED,
            'notes' => isset($row['notes']) ? sanitize_textarea_field((string) $row['notes']) : '',
            'evidence' => isset($row['evidence']) ? wp_kses_post((string) $row['evidence']) : '',
            'awarded_by_user_id' => isset($row['awarded_by_user_id']) ? (int) $row['awarded_by_user_id'] : 0,
            'awarded_at' => isset($row['awarded_at']) ? (string) $row['awarded_at'] : '',
            'revoked_at' => isset($row['revoked_at']) ? (string) $row['revoked_at'] : '',
            'created_at' => isset($row['created_at']) ? (string) $row['created_at'] : '',
            'updated_at' => isset($row['updated_at']) ? (string) $row['updated_at'] : '',
        );
    }

    private static function normalize_status($status): string
    {
        $candidate = sanitize_key((string) $status);
        if ($candidate === self::STATUS_REVOKED) {
            return self::STATUS_REVOKED;
        }

        if ($candidate === self::STATUS_AWARDED) {
            return self::STATUS_AWARDED;
        }

        return self::STATUS_AWARDED;
    }

    /**
     * @param array<string,mixed> $data
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
                return new WP_Error('mj_member_badge_criteria_badge_required', __('Badge requis.', 'mj-member'));
            }
        } elseif (!$isUpdate) {
            return new WP_Error('mj_member_badge_criteria_badge_required', __('Badge requis.', 'mj-member'));
        }

        if (isset($data['criterion_id'])) {
            $payload['criterion_id'] = (int) $data['criterion_id'];
            if ($payload['criterion_id'] <= 0) {
                return new WP_Error('mj_member_badge_criteria_invalid_criterion', __('Critère invalide.', 'mj-member'));
            }
        } elseif (!$isUpdate) {
            return new WP_Error('mj_member_badge_criteria_invalid_criterion', __('Critère invalide.', 'mj-member'));
        }

        if (isset($data['member_id'])) {
            $payload['member_id'] = (int) $data['member_id'];
            if ($payload['member_id'] <= 0) {
                return new WP_Error('mj_member_badge_criteria_member_required', __('Membre requis.', 'mj-member'));
            }
        } elseif (!$isUpdate) {
            return new WP_Error('mj_member_badge_criteria_member_required', __('Membre requis.', 'mj-member'));
        }

        if (isset($data['status'])) {
            $payload['status'] = self::normalize_status($data['status']);
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

        if (array_key_exists('awarded_at', $data)) {
            $payload['awarded_at'] = self::normalize_datetime($data['awarded_at']);
        }

        if (array_key_exists('revoked_at', $data)) {
            $payload['revoked_at'] = self::normalize_datetime($data['revoked_at']);
        }

        if (isset($payload['status']) && $payload['status'] === self::STATUS_REVOKED && !isset($payload['revoked_at'])) {
            $payload['revoked_at'] = current_time('mysql');
        }

        if (isset($payload['status']) && $payload['status'] === self::STATUS_AWARDED && array_key_exists('revoked_at', $payload) && $payload['revoked_at'] === null) {
            $payload['revoked_at'] = null;
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
     * @return array<int,string>
     */
    private static function get_format(array $payload): array
    {
        $map = array(
            'badge_id' => '%d',
            'criterion_id' => '%d',
            'member_id' => '%d',
            'status' => '%s',
            'notes' => '%s',
            'evidence' => '%s',
            'awarded_by_user_id' => '%d',
            'awarded_at' => '%s',
            'revoked_at' => '%s',
            'created_at' => '%s',
            'updated_at' => '%s',
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
