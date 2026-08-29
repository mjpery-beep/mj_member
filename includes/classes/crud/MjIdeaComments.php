<?php

namespace Mj\Member\Classes\Crud;

use Mj\Member\Classes\MjTools;

if (!defined('ABSPATH')) {
    exit;
}

class MjIdeaComments extends MjTools
{
    private const TABLE = 'mj_idea_comments';

    public static function table_name(): string
    {
        if (function_exists('mj_member_get_idea_comments_table_name')) {
            return mj_member_get_idea_comments_table_name();
        }

        return self::getTableName(self::TABLE);
    }

    public static function add(int $ideaId, int $memberId, string $content): int|false
    {
        $content = sanitize_textarea_field($content);
        if ($ideaId <= 0 || $memberId <= 0 || $content === '') {
            return false;
        }

        if (function_exists('mb_substr')) {
            $content = mb_substr($content, 0, 1000);
        } else {
            $content = substr($content, 0, 1000);
        }

        global $wpdb;
        $result = $wpdb->insert(
            self::table_name(),
            array(
                'idea_id' => $ideaId,
                'member_id' => $memberId,
                'content' => $content,
                'created_at' => current_time('mysql'),
            ),
            array('%d', '%d', '%s', '%s')
        );

        return $result ? (int) $wpdb->insert_id : false;
    }

    /**
     * @return object|null
     */
    public static function get(int $commentId)
    {
        global $wpdb;
        $commentsTable = self::table_name();
        $membersTable = MjMembers::getTableName(MjMembers::TABLE_NAME);

        return $wpdb->get_row($wpdb->prepare(
            "SELECT c.*, m.first_name, m.last_name, m.photo_id FROM {$commentsTable} c LEFT JOIN {$membersTable} m ON m.id = c.member_id WHERE c.id = %d",
            $commentId
        ));
    }

    /**
     * @param array<int,int> $ideaIds
     * @return array<int,array<int,object>>
     */
    public static function get_for_ideas(array $ideaIds): array
    {
        $ideaIds = array_values(array_unique(array_filter(array_map('intval', $ideaIds), static function (int $ideaId): bool {
            return $ideaId > 0;
        })));
        if (empty($ideaIds)) {
            return array();
        }

        global $wpdb;
        $commentsTable = self::table_name();
        $membersTable = MjMembers::getTableName(MjMembers::TABLE_NAME);
        $placeholders = implode(',', array_fill(0, count($ideaIds), '%d'));
        $sql = $wpdb->prepare(
            "SELECT c.*, m.first_name, m.last_name, m.photo_id FROM {$commentsTable} c LEFT JOIN {$membersTable} m ON m.id = c.member_id WHERE c.idea_id IN ({$placeholders}) ORDER BY c.created_at ASC, c.id ASC",
            $ideaIds
        );
        $rows = $wpdb->get_results($sql);
        $comments = array();

        foreach ((array) $rows as $row) {
            $ideaId = isset($row->idea_id) ? (int) $row->idea_id : 0;
            if ($ideaId > 0) {
                $comments[$ideaId][] = $row;
            }
        }

        return $comments;
    }

    public static function delete(int $commentId): bool
    {
        global $wpdb;
        return $wpdb->delete(self::table_name(), array('id' => $commentId), array('%d')) !== false;
    }

    public static function delete_for_idea(int $ideaId): bool
    {
        global $wpdb;
        return $wpdb->delete(self::table_name(), array('idea_id' => $ideaId), array('%d')) !== false;
    }

    /**
     * @param object $comment
     * @return array<string,mixed>
     */
    public static function format_for_json($comment): array
    {
        $firstName = isset($comment->first_name) ? sanitize_text_field((string) $comment->first_name) : '';
        $lastName = isset($comment->last_name) ? sanitize_text_field((string) $comment->last_name) : '';
        $lastInitial = $lastName !== '' ? (function_exists('mb_substr') ? mb_substr($lastName, 0, 1) : substr($lastName, 0, 1)) . '.' : '';
        $name = trim($firstName . ($lastInitial !== '' ? ' ' . $lastInitial : ''));
        $photoId = isset($comment->photo_id) ? (int) $comment->photo_id : 0;
        $avatarUrl = $photoId > 0 ? wp_get_attachment_image_url($photoId, 'thumbnail') : false;

        return array(
            'id' => isset($comment->id) ? (int) $comment->id : 0,
            'memberId' => isset($comment->member_id) ? (int) $comment->member_id : 0,
            'memberName' => $name,
            'avatarUrl' => is_string($avatarUrl) ? $avatarUrl : '',
            'content' => isset($comment->content) ? sanitize_textarea_field((string) $comment->content) : '',
            'createdAt' => isset($comment->created_at) ? (string) $comment->created_at : '',
            'createdAgo' => isset($comment->created_at) ? human_time_diff(strtotime((string) $comment->created_at), current_time('timestamp')) : '',
        );
    }
}
