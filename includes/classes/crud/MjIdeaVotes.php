<?php

namespace Mj\Member\Classes\Crud;

use Mj\Member\Classes\MjTools;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class MjIdeaVotes extends MjTools
{
    private const TABLE = 'mj_idea_votes';

    private static function table_name(): string
    {
        if (function_exists('mj_member_get_idea_votes_table_name')) {
            return mj_member_get_idea_votes_table_name();
        }

        return self::getTableName(self::TABLE);
    }

    public static function table_name_static(): string
    {
        return self::table_name();
    }

    /**
     * @return bool|WP_Error
     */
    public static function add(int $ideaId, int $memberId)
    {
        $ideaId = (int) $ideaId;
        $memberId = (int) $memberId;
        if ($ideaId <= 0 || $memberId <= 0) {
            return new WP_Error('mj_member_idea_vote_invalid', __('Vote invalide.', 'mj-member'));
        }

        global $wpdb;
        $table = self::table_name();

        $result = $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$table} (idea_id, member_id, created_at) VALUES (%d, %d, %s)",
            $ideaId,
            $memberId,
            current_time('mysql')
        ));

        if ($result === false) {
            return new WP_Error('mj_member_idea_vote_failed', __('Impossible d\'enregistrer le vote.', 'mj-member'));
        }

        if ($result > 0) {
            MjIdeas::increment_vote_count($ideaId, 1);
            return true;
        }

        return false;
    }

    /**
     * @return bool|WP_Error
     */
    public static function remove(int $ideaId, int $memberId)
    {
        $ideaId = (int) $ideaId;
        $memberId = (int) $memberId;
        if ($ideaId <= 0 || $memberId <= 0) {
            return new WP_Error('mj_member_idea_vote_invalid', __('Vote invalide.', 'mj-member'));
        }

        global $wpdb;
        $table = self::table_name();

        $result = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} WHERE idea_id = %d AND member_id = %d",
            $ideaId,
            $memberId
        ));

        if ($result === false) {
            return new WP_Error('mj_member_idea_vote_failed', __('Impossible de retirer le vote.', 'mj-member'));
        }

        if ($result > 0) {
            MjIdeas::increment_vote_count($ideaId, -1);
            return true;
        }

        return false;
    }

    /**
     * @return array<int,int>
     */
    public static function get_member_vote_ids(int $memberId): array
    {
        $memberId = (int) $memberId;
        if ($memberId <= 0) {
            return array();
        }

        global $wpdb;
        $table = self::table_name();

        $rows = $wpdb->get_col($wpdb->prepare(
            "SELECT idea_id FROM {$table} WHERE member_id = %d",
            $memberId
        ));

        if (!is_array($rows) || empty($rows)) {
            return array();
        }

        $ids = array();
        foreach ($rows as $row) {
            $id = (int) $row;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public static function delete_for_idea(int $ideaId): void
    {
        $ideaId = (int) $ideaId;
        if ($ideaId <= 0) {
            return;
        }

        global $wpdb;
        $table = self::table_name();
        $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE idea_id = %d", $ideaId));
    }

    public static function count_for_idea(int $ideaId): int
    {
        $ideaId = (int) $ideaId;
        if ($ideaId <= 0) {
            return 0;
        }

        global $wpdb;
        $table = self::table_name();

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE idea_id = %d",
            $ideaId
        ));

        return $count ? (int) $count : 0;
    }

    /**
     * Returns voter names for a given idea.
     *
     * @return list<array{id:int,first_name:string,last_name:string}>
     */
    public static function get_voters_for_idea(int $ideaId): array
    {
        $ideaId = (int) $ideaId;
        if ($ideaId <= 0) {
            return array();
        }

        global $wpdb;
        $votes_table = self::table_name();
        $members_table = MjMembers::getTableName(MjMembers::TABLE_NAME);

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT m.id, m.first_name, m.last_name
             FROM {$votes_table} v
             INNER JOIN {$members_table} m ON m.id = v.member_id
             WHERE v.idea_id = %d
             ORDER BY v.created_at DESC",
            $ideaId
        ), ARRAY_A);

        if (!is_array($rows)) {
            return array();
        }

        $voters = array();
        foreach ($rows as $row) {
            $voters[] = array(
                'id'         => (int) $row['id'],
                'first_name' => (string) $row['first_name'],
                'last_name'  => (string) $row['last_name'],
            );
        }

        return $voters;
    }

    /**
     * Returns voter names for multiple ideas in a single query.
     *
     * @param  list<int> $ideaIds
     * @return array<int, list<array{id:int,first_name:string,last_name:string}>>
     */
    public static function get_voters_for_ideas(array $ideaIds): array
    {
        $ideaIds = array_filter(array_map('intval', $ideaIds), function ($id) { return $id > 0; });
        if (empty($ideaIds)) {
            return array();
        }

        global $wpdb;
        $votes_table = self::table_name();
        $members_table = MjMembers::getTableName(MjMembers::TABLE_NAME);

        $placeholders = implode(',', array_fill(0, count($ideaIds), '%d'));

        // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT v.idea_id, m.id, m.first_name, m.last_name
             FROM {$votes_table} v
             INNER JOIN {$members_table} m ON m.id = v.member_id
             WHERE v.idea_id IN ({$placeholders})
             ORDER BY v.created_at DESC",
            ...$ideaIds
        ), ARRAY_A);

        $result = array();
        foreach ($ideaIds as $id) {
            $result[$id] = array();
        }

        if (is_array($rows)) {
            foreach ($rows as $row) {
                $ideaId = (int) $row['idea_id'];
                $result[$ideaId][] = array(
                    'id'         => (int) $row['id'],
                    'first_name' => (string) $row['first_name'],
                    'last_name'  => (string) $row['last_name'],
                );
            }
        }

        return $result;
    }
}
