<?php

namespace Mj\Member\Classes\Crud;

use Mj\Member\Classes\MjRoles;
use Mj\Member\Classes\MjTools;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class MjIdeas extends MjTools implements CrudRepositoryInterface
{
    private const TABLE = 'mj_ideas';

    public const STATUS_PUBLISHED = 'published';
    public const STATUS_ARCHIVED = 'archived';

    /**
     * @return array<int,string>
     */
    public static function statuses(): array
    {
        return array(self::STATUS_PUBLISHED, self::STATUS_ARCHIVED);
    }

    private static function table_name(): string
    {
        if (function_exists('mj_member_get_ideas_table_name')) {
            return mj_member_get_ideas_table_name();
        }

        return self::getTableName(self::TABLE);
    }

    private static function normalize_status($value): string
    {
        $value = sanitize_key((string) $value);
        if (in_array($value, self::statuses(), true)) {
            return $value;
        }

        return self::STATUS_PUBLISHED;
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private static function format_row(array $row): array
    {
        return array(
            'id' => isset($row['id']) ? (int) $row['id'] : 0,
            'member_id' => isset($row['member_id']) ? (int) $row['member_id'] : 0,
            'title' => isset($row['title']) ? sanitize_text_field((string) $row['title']) : '',
            'content' => isset($row['content']) ? sanitize_textarea_field((string) $row['content']) : '',
            'status' => isset($row['status']) ? sanitize_key((string) $row['status']) : self::STATUS_PUBLISHED,
            'vote_count' => isset($row['vote_count']) ? max(0, (int) $row['vote_count']) : 0,
            'created_at' => isset($row['created_at']) ? (string) $row['created_at'] : '',
            'updated_at' => isset($row['updated_at']) ? (string) $row['updated_at'] : '',
        );
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
            'status' => '',
            'statuses' => array(),
            'member_id' => 0,
            'search' => '',
            'limit' => 0,
            'offset' => 0,
            'orderby' => 'vote_count',
            'order' => 'DESC',
            'include_ids' => array(),
        );
        $args = wp_parse_args($args, $defaults);

        $builder = CrudQueryBuilder::for_table($table);

        $statuses = array();
        if (!empty($args['statuses']) && is_array($args['statuses'])) {
            foreach ($args['statuses'] as $candidate) {
                $normalized = self::normalize_status($candidate);
                if ($normalized !== '') {
                    $statuses[] = $normalized;
                }
            }
        } elseif (!empty($args['status'])) {
            $single = self::normalize_status($args['status']);
            if ($single !== '') {
                $statuses[] = $single;
            }
        }

        if (!empty($statuses)) {
            $builder->where_in_strings('status', $statuses, static function ($value) {
                return self::normalize_status($value);
            });
        }

        $memberId = (int) $args['member_id'];
        if ($memberId > 0) {
            $builder->where_equals_int('member_id', $memberId);
        }

        if (!empty($args['search'])) {
            $builder->where_like_any(array('title', 'content'), (string) $args['search']);
        }

        if (!empty($args['include_ids'])) {
            $builder->where_in_int('id', (array) $args['include_ids']);
        }

        $allowedOrderBy = array('created_at', 'updated_at', 'vote_count', 'title', 'id');
        $orderby = sanitize_key((string) $args['orderby']);
        if (!in_array($orderby, $allowedOrderBy, true)) {
            $orderby = 'created_at';
        }

        $order = strtoupper((string) $args['order']) === 'ASC' ? 'ASC' : 'DESC';
        $limit = (int) $args['limit'];
        $offset = max(0, (int) $args['offset']);

        list($sql, $params) = $builder->build_select('*', $orderby, $order, $limit, $offset);
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        $rows = $wpdb->get_results($sql, ARRAY_A);
        if (!is_array($rows) || empty($rows)) {
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

        $builder = CrudQueryBuilder::for_table($table);

        $status = isset($args['status']) ? self::normalize_status($args['status']) : '';
        if ($status !== '') {
            $builder->where_equals('status', $status, static function ($value) {
                return self::normalize_status($value);
            });
        }

        $memberId = isset($args['member_id']) ? (int) $args['member_id'] : 0;
        if ($memberId > 0) {
            $builder->where_equals_int('member_id', $memberId);
        }

        if (!empty($args['search'])) {
            $builder->where_like_any(array('title', 'content'), (string) $args['search']);
        }

        list($sql, $params) = $builder->build_count('*');
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        $result = $wpdb->get_var($sql);
        return $result ? (int) $result : 0;
    }

    /**
     * @param array<string,mixed>|null $data
     * @return int|WP_Error
     */
    public static function create($data)
    {
        if (!is_array($data)) {
            return new WP_Error('mj_member_idea_invalid_payload', __('Format de données invalide pour l\'idée.', 'mj-member'));
        }

        global $wpdb;
        $table = self::table_name();

        $memberId = isset($data['member_id']) ? (int) $data['member_id'] : 0;
        if ($memberId <= 0) {
            return new WP_Error('mj_member_idea_missing_member', __('Auteur invalide pour l\'idée.', 'mj-member'));
        }

        $title = isset($data['title']) ? sanitize_text_field((string) $data['title']) : '';
        if ($title !== '') {
            $title = mb_substr($title, 0, 180);
        }
        if ($title === '') {
            return new WP_Error('mj_member_idea_missing_title', __('Merci de saisir un titre.', 'mj-member'));
        }

        $content = isset($data['content']) ? sanitize_textarea_field((string) $data['content']) : '';
        if ($content === '') {
            return new WP_Error('mj_member_idea_missing_content', __('Merci de saisir une idée.', 'mj-member'));
        }

        if (function_exists('mb_strlen') && mb_strlen($content) > 1000) {
            $content = mb_substr($content, 0, 1000);
        } elseif (strlen($content) > 1000) {
            $content = substr($content, 0, 1000);
        }

        $status = isset($data['status']) ? self::normalize_status($data['status']) : self::STATUS_PUBLISHED;

        $insert = array(
            'member_id' => $memberId,
            'title' => $title,
            'content' => $content,
            'status' => $status,
            'vote_count' => 0,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        );
        $formats = array('%d', '%s', '%s', '%s', '%d', '%s', '%s');

        $result = $wpdb->insert($table, $insert, $formats);
        if ($result === false) {
            return new WP_Error('mj_member_idea_create_failed', __('Impossible d\'enregistrer l\'idée.', 'mj-member'));
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * @param int $id
     * @param array<string,mixed>|null $data
     * @return true|WP_Error
     */
    public static function update($id, $data)
    {
        $id = (int) $id;
        if ($id <= 0) {
            return new WP_Error('mj_member_idea_invalid_id', __('Identifiant d\'idée invalide.', 'mj-member'));
        }

        if (!is_array($data)) {
            return new WP_Error('mj_member_idea_invalid_payload', __('Format de données invalide pour l\'idée.', 'mj-member'));
        }

        global $wpdb;
        $table = self::table_name();

        $update = array();
        $formats = array();

        if (array_key_exists('title', $data)) {
            $title = sanitize_text_field((string) $data['title']);
            if ($title !== '') {
                $title = mb_substr($title, 0, 180);
            }
            if ($title === '') {
                return new WP_Error('mj_member_idea_missing_title', __('Merci de saisir un titre.', 'mj-member'));
            }
            $update['title'] = $title;
            $formats[] = '%s';
        }

        if (array_key_exists('content', $data)) {
            $content = sanitize_textarea_field((string) $data['content']);
            if (function_exists('mb_strlen') && mb_strlen($content) > 1000) {
                $content = mb_substr($content, 0, 1000);
            } elseif (strlen($content) > 1000) {
                $content = substr($content, 0, 1000);
            }
            $update['content'] = $content;
            $formats[] = '%s';
        }

        if (array_key_exists('status', $data)) {
            $update['status'] = self::normalize_status($data['status']);
            $formats[] = '%s';
        }

        if (empty($update)) {
            return true;
        }

        $update['updated_at'] = current_time('mysql');
        $formats[] = '%s';

        $result = $wpdb->update($table, $update, array('id' => $id), $formats, array('%d'));
        if ($result === false) {
            return new WP_Error('mj_member_idea_update_failed', __('Impossible de mettre à jour l\'idée.', 'mj-member'));
        }

        return true;
    }

    /**
     * @param int $id
     * @return true|WP_Error
     */
    public static function delete($id)
    {
        $id = (int) $id;
        if ($id <= 0) {
            return new WP_Error('mj_member_idea_invalid_id', __('Identifiant d\'idée invalide.', 'mj-member'));
        }

        global $wpdb;
        $table = self::table_name();

        MjIdeaVotes::delete_for_idea($id);

        $result = $wpdb->delete($table, array('id' => $id), array('%d'));
        if ($result === false) {
            return new WP_Error('mj_member_idea_delete_failed', __('Impossible de supprimer l\'idée.', 'mj-member'));
        }

        return true;
    }

    /**
     * @param int $id
     * @return array<string,mixed>|null
     */
    public static function get($id)
    {
        $id = (int) $id;
        if ($id <= 0) {
            return null;
        }

        global $wpdb;
        $table = self::table_name();

        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id), ARRAY_A);
        if (!is_array($row)) {
            return null;
        }

        return self::format_row($row);
    }

    /**
     * @param array<int,int> $ideaIds
     */
    public static function rebuild_vote_counts(array $ideaIds = array()): void
    {
        global $wpdb;
        $table = self::table_name();
        $votesTable = MjIdeaVotes::table_name_static();

        $filterSql = '';
        if (!empty($ideaIds)) {
            $ids = array_values(array_unique(array_filter(array_map('intval', $ideaIds), static function ($value) {
                return $value > 0;
            })));
            if (!empty($ids)) {
                $filterSql = ' WHERE i.id IN (' . implode(',', array_map('intval', $ids)) . ')';
            }
        }

        $wpdb->query("UPDATE {$table} AS i SET vote_count = (
            SELECT COUNT(*) FROM {$votesTable} AS v WHERE v.idea_id = i.id
        )" . $filterSql);
    }

    public static function increment_vote_count(int $ideaId, int $delta): void
    {
        $ideaId = (int) $ideaId;
        if ($ideaId <= 0 || $delta === 0) {
            return;
        }

        global $wpdb;
        $table = self::table_name();

        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET vote_count = CASE WHEN vote_count + %d < 0 THEN 0 ELSE vote_count + %d END WHERE id = %d",
            $delta,
            $delta,
            $ideaId
        ));
    }

    /**
     * @param array<string,mixed> $args
     * @return array<int,array<string,mixed>>
     */
    public static function get_with_votes(array $args = array(), int $viewerMemberId = 0, array $options = array()): array
    {
        $ideas = self::get_all($args);
        if (empty($ideas)) {
            return array();
        }

        $viewerRole = isset($options['viewer_role']) ? sanitize_key((string) $options['viewer_role']) : '';
        $viewerCanDelete = false;
        if ($viewerRole !== '' && class_exists(MjMembers::class)) {
            $viewerCanDelete = in_array($viewerRole, array(
                sanitize_key((string) MjRoles::ANIMATEUR),
                sanitize_key((string) MjRoles::COORDINATEUR),
            ), true);
        }

        $memberIds = array();
        foreach ($ideas as $idea) {
            if (isset($idea['member_id']) && $idea['member_id'] > 0) {
                $memberIds[$idea['member_id']] = $idea['member_id'];
            }
        }

        $memberMap = array();
        if (!empty($memberIds) && class_exists(MjMembers::class)) {
            global $wpdb;
            $membersTable = MjMembers::getTableName(MjMembers::TABLE_NAME);
            $placeholders = implode(',', array_fill(0, count($memberIds), '%d'));
            $sql = $wpdb->prepare(
                "SELECT id, first_name, last_name, role FROM {$membersTable} WHERE id IN ({$placeholders})",
                array_values($memberIds)
            );
            $rows = $wpdb->get_results($sql, ARRAY_A);
            if (is_array($rows)) {
                foreach ($rows as $row) {
                    $memberId = isset($row['id']) ? (int) $row['id'] : 0;
                    if ($memberId <= 0) {
                        continue;
                    }
                    $firstName = isset($row['first_name']) ? sanitize_text_field((string) $row['first_name']) : '';
                    $lastName = isset($row['last_name']) ? sanitize_text_field((string) $row['last_name']) : '';
                    $name = trim($firstName . ' ' . $lastName);
                    if ($name === '') {
                        $name = sprintf(__('Membre #%d', 'mj-member'), $memberId);
                    }
                    $memberMap[$memberId] = array(
                        'id' => $memberId,
                        'name' => $name,
                        'role' => isset($row['role']) ? sanitize_key((string) $row['role']) : '',
                    );
                }
            }
        }

        $viewerVotes = array();
        if ($viewerMemberId > 0) {
            $viewerVotes = MjIdeaVotes::get_member_vote_ids($viewerMemberId);
        }
        $viewerLookup = array();
        foreach ($viewerVotes as $ideaId) {
            $viewerLookup[(int) $ideaId] = true;
        }

        $payload = array();
        foreach ($ideas as $idea) {
            $ideaId = isset($idea['id']) ? (int) $idea['id'] : 0;
            if ($ideaId <= 0) {
                continue;
            }

            $authorId = isset($idea['member_id']) ? (int) $idea['member_id'] : 0;
            $author = $memberMap[$authorId] ?? array(
                'id' => $authorId,
                'name' => $authorId > 0 ? sprintf(__('Membre #%d', 'mj-member'), $authorId) : '',
                'role' => '',
            );

            $payload[] = array(
                'id' => $ideaId,
                'title' => $idea['title'],
                'content' => $idea['content'],
                'status' => $idea['status'],
                'voteCount' => isset($idea['vote_count']) ? (int) $idea['vote_count'] : 0,
                'createdAt' => $idea['created_at'],
                'updatedAt' => $idea['updated_at'],
                'author' => $author,
                'viewerHasVoted' => isset($viewerLookup[$ideaId]),
                'isOwner' => $authorId > 0 && $authorId === $viewerMemberId,
                'canDelete' => $viewerCanDelete,
            );
        }

        return $payload;
    }
}
