<?php

namespace Mj\Member\Classes\Crud;

use Mj\Member\Classes\MjTools;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * CRUD pour l'attribution des trophées aux membres.
 */
final class MjMemberTrophies extends MjTools implements CrudRepositoryInterface
{
    private const TABLE = 'mj_member_trophies';

    public const STATUS_AWARDED = 'awarded';
    public const STATUS_REVOKED = 'revoked';

    public const LEVEL_BRONZE = 'bronze';
    public const LEVEL_SILVER = 'silver';
    public const LEVEL_GOLD = 'gold';

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

    /**
     * @return array<string,string>
     */
    public static function get_level_labels(): array
    {
        return array(
            self::LEVEL_BRONZE => __('Bronze', 'mj-member'),
            self::LEVEL_SILVER => __('Argent', 'mj-member'),
            self::LEVEL_GOLD => __('Or', 'mj-member'),
        );
    }

    public static function table_name(): string
    {
        if (function_exists('mj_member_get_member_trophies_table_name')) {
            return mj_member_get_member_trophies_table_name();
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

        $defaults = array(
            'member_id' => 0,
            'trophy_id' => 0,
            'status' => '',
            'orderby' => 'awarded_at',
            'order' => 'DESC',
            'limit' => 0,
            'offset' => 0,
        );
        $args = wp_parse_args($args, $defaults);

        $where = array();
        $params = array();

        if ((int) $args['member_id'] > 0) {
            $where[] = 'member_id = %d';
            $params[] = (int) $args['member_id'];
        }

        if ((int) $args['trophy_id'] > 0) {
            $where[] = 'trophy_id = %d';
            $params[] = (int) $args['trophy_id'];
        }

        if (!empty($args['status'])) {
            $normalized = self::normalize_status($args['status']);
            if ($normalized !== '') {
                $where[] = 'status = %s';
                $params[] = $normalized;
            }
        }

        $sql = "SELECT * FROM {$table}";
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $allowedOrderBy = array('awarded_at', 'created_at', 'id', 'member_id', 'trophy_id');
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
     * @param int $memberId
     * @return array<int,array<string,mixed>>
     */
    public static function get_for_member(int $memberId): array
    {
        return self::get_all(array(
            'member_id' => $memberId,
            'status' => self::STATUS_AWARDED,
        ));
    }

    /**
     * @param int $trophyId
     * @return array<int,array<string,mixed>>
     */
    public static function get_for_trophy(int $trophyId): array
    {
        return self::get_all(array(
            'trophy_id' => $trophyId,
            'status' => self::STATUS_AWARDED,
        ));
    }

    /**
     * @param int $memberId
     * @param int $trophyId
     * @return array<string,mixed>|null
     */
    public static function get_assignment(int $memberId, int $trophyId): ?array
    {
        global $wpdb;
        $table = self::table_name();

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE member_id = %d AND trophy_id = %d LIMIT 1",
            $memberId,
            $trophyId
        ), ARRAY_A);

        if (!$row) {
            return null;
        }

        return self::format_row($row);
    }

    /**
     * @param int $id
     * @return array<string,mixed>|null
     */
    public static function get(int $id): ?array
    {
        global $wpdb;
        $table = self::table_name();

        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id), ARRAY_A);
        if (!$row) {
            return null;
        }

        return self::format_row($row);
    }

    /**
     * @param array<string,mixed>|null $row
     * @return array<string,mixed>
     */
    private static function format_row($row): array
    {
        return array(
            'id' => isset($row['id']) ? (int) $row['id'] : 0,
            'member_id' => isset($row['member_id']) ? (int) $row['member_id'] : 0,
            'trophy_id' => isset($row['trophy_id']) ? (int) $row['trophy_id'] : 0,
            'status' => self::normalize_status($row['status'] ?? self::STATUS_AWARDED) ?: self::STATUS_AWARDED,
            'current_level' => self::normalize_level($row['current_level'] ?? '') ?: '',
            'notes' => isset($row['notes']) ? sanitize_textarea_field((string) $row['notes']) : '',
            'awarded_by_user_id' => isset($row['awarded_by_user_id']) ? (int) $row['awarded_by_user_id'] : 0,
            'awarded_at' => isset($row['awarded_at']) ? (string) $row['awarded_at'] : '',
            'revoked_at' => isset($row['revoked_at']) ? (string) $row['revoked_at'] : '',
            'created_at' => isset($row['created_at']) ? (string) $row['created_at'] : '',
            'updated_at' => isset($row['updated_at']) ? (string) $row['updated_at'] : '',
        );
    }

    /**
     * @param mixed $value
     * @return string
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

        return $candidate === '' ? '' : self::STATUS_AWARDED;
    }

    /**
     * @param mixed $value
     * @return string
     */
    private static function normalize_level($value): string
    {
        $candidate = sanitize_key((string) $value);
        if (in_array($candidate, array(self::LEVEL_BRONZE, self::LEVEL_SILVER, self::LEVEL_GOLD), true)) {
            return $candidate;
        }

        return '';
    }

    /**
     * @param string $level
     * @return int
     */
    private static function level_rank(string $level): int
    {
        if ($level === self::LEVEL_GOLD) {
            return 3;
        }
        if ($level === self::LEVEL_SILVER) {
            return 2;
        }
        if ($level === self::LEVEL_BRONZE) {
            return 1;
        }
        return 0;
    }

    /**
     * Attribue un trophée à un membre.
     *
     * @param int $memberId
     * @param int $trophyId
     * @param array<string,mixed> $options
     * @return int|WP_Error
     */
    public static function award(int $memberId, int $trophyId, array $options = array())
    {
        global $wpdb;
        $table = self::table_name();

        $memberId = (int) $memberId;
        $trophyId = (int) $trophyId;

        if ($memberId <= 0) {
            return new WP_Error('mj_trophy_invalid_member', __('Membre invalide.', 'mj-member'));
        }

        if ($trophyId <= 0) {
            return new WP_Error('mj_trophy_invalid_trophy', __('Trophée invalide.', 'mj-member'));
        }

        // Vérifier si déjà attribué
        $existing = self::get_assignment($memberId, $trophyId);
        if ($existing && $existing['status'] === self::STATUS_AWARDED) {
            return (int) $existing['id'];
        }

        $now = current_time('mysql');
        $awardedBy = isset($options['awarded_by_user_id']) ? (int) $options['awarded_by_user_id'] : get_current_user_id();
        $notes = isset($options['notes']) ? sanitize_textarea_field((string) $options['notes']) : '';
        $requestedLevel = isset($options['level']) ? self::normalize_level($options['level']) : '';

        $trophy = MjTrophies::get($trophyId);
        $level = '';
        if ($trophy && !empty($trophy['tier_enabled'])) {
            $level = $requestedLevel !== '' ? $requestedLevel : self::LEVEL_BRONZE;
        }

        if ($existing) {
            $existingLevel = self::normalize_level($existing['current_level'] ?? '');
            $nextLevel = $existingLevel;
            if (self::level_rank($level) > self::level_rank($existingLevel)) {
                $nextLevel = $level;
            }

            // Réactiver un trophée révoqué
            $wpdb->update(
                $table,
                array(
                    'status' => self::STATUS_AWARDED,
                    'current_level' => $nextLevel,
                    'notes' => $notes,
                    'awarded_by_user_id' => $awardedBy,
                    'awarded_at' => $now,
                    'revoked_at' => null,
                    'updated_at' => $now,
                ),
                array('id' => $existing['id']),
                array('%s', '%s', '%s', '%d', '%s', '%s', '%s'),
                array('%d')
            );

            // Ajouter les XP du trophée au membre (réactivation)
            self::award_trophy_xp($memberId, $trophyId);

            /**
             * Action déclenchée après l'attribution d'un trophée (réactivation).
             *
             * @param int $memberId      ID du membre
             * @param int $trophyId      ID du trophée
             * @param int $assignmentId  ID de l'attribution
             * @param bool $isReactivation True si c'est une réactivation
             */
            do_action('mj_member_trophy_awarded', $memberId, $trophyId, (int) $existing['id'], true);

            if ($nextLevel !== '' && self::level_rank($nextLevel) > self::level_rank($existingLevel)) {
                do_action('mj_member_trophy_level_up', $memberId, $trophyId, $nextLevel, $existingLevel);
            }

            return (int) $existing['id'];
        }

        $result = $wpdb->insert(
            $table,
            array(
                'member_id' => $memberId,
                'trophy_id' => $trophyId,
                'status' => self::STATUS_AWARDED,
                'current_level' => $level,
                'notes' => $notes,
                'awarded_by_user_id' => $awardedBy,
                'awarded_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ),
            array('%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s')
        );

        if ($result === false) {
            return new WP_Error('mj_trophy_award_failed', __('Impossible d\'attribuer le trophée.', 'mj-member'));
        }

        $insertId = (int) $wpdb->insert_id;

        // Ajouter les XP du trophée au membre
        self::award_trophy_xp($memberId, $trophyId);

        /**
         * Action déclenchée après l'attribution d'un trophée (nouvelle attribution).
         *
         * @param int $memberId      ID du membre
         * @param int $trophyId      ID du trophée
         * @param int $assignmentId  ID de l'attribution
         * @param bool $isReactivation True si c'est une réactivation
         */
        do_action('mj_member_trophy_awarded', $memberId, $trophyId, $insertId, false);

        if ($level !== '') {
            do_action('mj_member_trophy_level_up', $memberId, $trophyId, $level, '');
        }

        return $insertId;
    }

    /**
     * Révoque un trophée d'un membre.
     *
     * @param int $memberId
     * @param int $trophyId
     * @return bool
     */
    public static function revoke(int $memberId, int $trophyId): bool
    {
        global $wpdb;
        $table = self::table_name();

        $existing = self::get_assignment($memberId, $trophyId);
        if (!$existing) {
            return false;
        }

        $now = current_time('mysql');
        $result = $wpdb->update(
            $table,
            array(
                'status' => self::STATUS_REVOKED,
                'current_level' => '',
                'revoked_at' => $now,
                'updated_at' => $now,
            ),
            array('id' => $existing['id']),
            array('%s', '%s', '%s', '%s'),
            array('%d')
        );

        if ($result !== false) {
            // Retirer les XP du trophée au membre
            self::revoke_trophy_xp($memberId, $trophyId);
        }

        return $result !== false;
    }

    /**
     * Ajoute les XP d'un trophée au membre.
     *
     * @param int $memberId
     * @param int $trophyId
     * @return void
     */
    private static function award_trophy_xp(int $memberId, int $trophyId): void
    {
        $trophy = MjTrophies::get($trophyId);
        if ($trophy && isset($trophy['xp']) && (int) $trophy['xp'] > 0) {
            MjMemberXp::add($memberId, (int) $trophy['xp']);
        }
        // Award coins for the trophy
        MjMemberCoins::awardForTrophy($memberId, $trophyId);
    }

    /**
     * Retire les XP d'un trophée au membre.
     *
     * @param int $memberId
     * @param int $trophyId
     * @return void
     */
    private static function revoke_trophy_xp(int $memberId, int $trophyId): void
    {
        $trophy = MjTrophies::get($trophyId);
        if ($trophy && isset($trophy['xp']) && (int) $trophy['xp'] > 0) {
            MjMemberXp::subtract($memberId, (int) $trophy['xp']);
        }
        // Revoke coins for the trophy
        MjMemberCoins::revokeForTrophy($memberId, $trophyId);
    }

    /**
     * Vérifie si un membre possède un trophée.
     *
     * @param int $memberId
     * @param int $trophyId
     * @return bool
     */
    public static function has_trophy(int $memberId, int $trophyId): bool
    {
        $assignment = self::get_assignment($memberId, $trophyId);
        return $assignment !== null && $assignment['status'] === self::STATUS_AWARDED;
    }

    /**
     * @param int $memberId
     * @param int $trophyId
     * @param int $actionCount
     * @param int $bronzeThreshold
     * @param int $silverThreshold
     * @param int $goldThreshold
     * @return array<string,mixed>
     */
    public static function promote_from_action_count(
        int $memberId,
        int $trophyId,
        int $actionCount,
        int $bronzeThreshold,
        int $silverThreshold,
        int $goldThreshold
    ): array {
        $nextLevel = self::resolve_level_for_count($actionCount, $bronzeThreshold, $silverThreshold, $goldThreshold);
        if ($nextLevel === '') {
            return array(
                'changed' => false,
                'level' => '',
                'assignment_id' => 0,
            );
        }

        $assignment = self::get_assignment($memberId, $trophyId);
        $previousLevel = $assignment ? self::normalize_level($assignment['current_level'] ?? '') : '';
        if (self::level_rank($nextLevel) <= self::level_rank($previousLevel)) {
            return array(
                'changed' => false,
                'level' => $previousLevel,
                'assignment_id' => $assignment ? (int) $assignment['id'] : 0,
            );
        }

        $result = self::award($memberId, $trophyId, array('level' => $nextLevel));
        if (is_wp_error($result)) {
            return array(
                'changed' => false,
                'level' => $previousLevel,
                'assignment_id' => 0,
                'error' => $result,
            );
        }

        return array(
            'changed' => true,
            'level' => $nextLevel,
            'assignment_id' => (int) $result,
        );
    }

    /**
     * @param int $count
     * @param int $bronze
     * @param int $silver
     * @param int $gold
     * @return string
     */
    private static function resolve_level_for_count(int $count, int $bronze, int $silver, int $gold): string
    {
        if ($count >= $gold && $gold > 0) {
            return self::LEVEL_GOLD;
        }
        if ($count >= $silver && $silver > 0) {
            return self::LEVEL_SILVER;
        }
        if ($count >= $bronze && $bronze > 0) {
            return self::LEVEL_BRONZE;
        }
        return '';
    }

    /**
     * Compte le total d'XP pour un membre.
     *
     * @param int $memberId
     * @return int
     */
    public static function get_total_xp(int $memberId): int
    {
        global $wpdb;
        $table = self::table_name();
        $trophiesTable = MjTrophies::table_name();

        $memberId = (int) $memberId;
        if ($memberId <= 0) {
            return 0;
        }

        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(t.xp), 0) FROM {$table} mt
             INNER JOIN {$trophiesTable} t ON mt.trophy_id = t.id
             WHERE mt.member_id = %d AND mt.status = %s",
            $memberId,
            self::STATUS_AWARDED
        ));

        return (int) $total;
    }

    /**
     * @param int $id
     * @return bool
     */
    public static function delete($id)
    {
        global $wpdb;
        $table = self::table_name();
        $id = (int) $id;

        $result = $wpdb->delete($table, array('id' => $id), array('%d'));
        return $result !== false;
    }

    /**
     * Stub pour l'interface.
     */
    public static function create($data)
    {
        $data = is_array($data) ? $data : array();
        return self::award((int) ($data['member_id'] ?? 0), (int) ($data['trophy_id'] ?? 0), $data);
    }

    /**
     * Stub pour l'interface.
     */
    public static function update($id, $data)
    {
        global $wpdb;
        $table = self::table_name();
        $id = (int) $id;
        $data = is_array($data) ? $data : array();

        $payload = array();
        if (isset($data['notes'])) {
            $payload['notes'] = sanitize_textarea_field((string) $data['notes']);
        }
        if (isset($data['status'])) {
            $payload['status'] = self::normalize_status($data['status']);
        }

        if (empty($payload)) {
            return true;
        }

        $payload['updated_at'] = current_time('mysql');

        $formats = array();
        foreach ($payload as $key => $value) {
            $formats[] = '%s';
        }

        return $wpdb->update($table, $payload, array('id' => $id), $formats, array('%d')) !== false;
    }

    /**
     * Stub pour l'interface.
     */
    public static function count(array $args = array())
    {
        global $wpdb;
        $table = self::table_name();

        $where = array();
        $params = array();

        if (isset($args['member_id']) && (int) $args['member_id'] > 0) {
            $where[] = 'member_id = %d';
            $params[] = (int) $args['member_id'];
        }

        if (isset($args['trophy_id']) && (int) $args['trophy_id'] > 0) {
            $where[] = 'trophy_id = %d';
            $params[] = (int) $args['trophy_id'];
        }

        if (!empty($args['status'])) {
            $where[] = 'status = %s';
            $params[] = self::normalize_status($args['status']);
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
}
