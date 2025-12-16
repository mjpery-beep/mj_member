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
}
