<?php

namespace Mj\Member\Classes\Crud;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * CRUD repository for testimonial comments.
 * 
 * @package MjMember
 */
class MjTestimonialComments {

    /**
     * @return string
     */
    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'mj_testimonial_comments';
    }

    /**
     * Add a comment to a testimonial.
     *
     * @param int $testimonial_id
     * @param int $member_id
     * @param string $content
     * @return int|false Insert ID or false on failure.
     */
    public static function add($testimonial_id, $member_id, $content) {
        global $wpdb;
        $table = self::get_table_name();

        $content = sanitize_textarea_field($content);
        if (empty($content)) {
            return false;
        }

        $result = $wpdb->insert(
            $table,
            array(
                'testimonial_id' => (int) $testimonial_id,
                'member_id' => (int) $member_id,
                'content' => $content,
                'created_at' => current_time('mysql'),
            ),
            array('%d', '%d', '%s', '%s')
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Get a single comment by ID.
     *
     * @param int $comment_id
     * @return object|null
     */
    public static function get($comment_id) {
        global $wpdb;
        $table = self::get_table_name();
        $members_table = MjMembers::getTableName(MjMembers::TABLE_NAME);

        return $wpdb->get_row($wpdb->prepare(
            "SELECT c.*, m.first_name, m.last_name
             FROM {$table} c
             LEFT JOIN {$members_table} m ON m.id = c.member_id
             WHERE c.id = %d",
            (int) $comment_id
        ));
    }

    /**
     * Get comments for a testimonial.
     *
     * @param int $testimonial_id
     * @param array $args
     * @return array<int,object>
     */
    public static function get_for_testimonial($testimonial_id, $args = array()) {
        global $wpdb;
        $table = self::get_table_name();
        $members_table = MjMembers::getTableName(MjMembers::TABLE_NAME);

        $defaults = array(
            'orderby' => 'created_at',
            'order' => 'ASC',
            'per_page' => 50,
            'page' => 1,
        );
        $args = wp_parse_args($args, $defaults);

        $offset = ($args['page'] - 1) * $args['per_page'];

        $order = strtoupper($args['order']) === 'DESC' ? 'DESC' : 'ASC';
        $orderby = in_array($args['orderby'], array('created_at', 'id'), true) 
            ? $args['orderby'] 
            : 'created_at';

        $query = $wpdb->prepare(
            "SELECT c.*, m.first_name, m.last_name
             FROM {$table} c
             LEFT JOIN {$members_table} m ON m.id = c.member_id
             WHERE c.testimonial_id = %d
             ORDER BY c.{$orderby} {$order}
             LIMIT %d OFFSET %d",
            (int) $testimonial_id,
            (int) $args['per_page'],
            (int) $offset
        );

        return $wpdb->get_results($query);
    }

    /**
     * Count comments for a testimonial.
     *
     * @param int $testimonial_id
     * @return int
     */
    public static function count_for_testimonial($testimonial_id) {
        global $wpdb;
        $table = self::get_table_name();

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE testimonial_id = %d",
            (int) $testimonial_id
        ));
    }

    /**
     * Update a comment.
     *
     * @param int $comment_id
     * @param string $content
     * @return bool
     */
    public static function update($comment_id, $content) {
        global $wpdb;
        $table = self::get_table_name();

        $content = sanitize_textarea_field($content);
        if (empty($content)) {
            return false;
        }

        $result = $wpdb->update(
            $table,
            array(
                'content' => $content,
                'updated_at' => current_time('mysql'),
            ),
            array('id' => (int) $comment_id),
            array('%s', '%s'),
            array('%d')
        );

        return $result !== false;
    }

    /**
     * Delete a comment.
     *
     * @param int $comment_id
     * @return bool
     */
    public static function delete($comment_id) {
        global $wpdb;
        $table = self::get_table_name();

        $result = $wpdb->delete(
            $table,
            array('id' => (int) $comment_id),
            array('%d')
        );

        return $result !== false;
    }

    /**
     * Delete all comments for a testimonial.
     *
     * @param int $testimonial_id
     * @return bool
     */
    public static function delete_for_testimonial($testimonial_id) {
        global $wpdb;
        $table = self::get_table_name();

        $result = $wpdb->delete(
            $table,
            array('testimonial_id' => (int) $testimonial_id),
            array('%d')
        );

        return $result !== false;
    }

    /**
     * Check if a member owns a comment.
     *
     * @param int $comment_id
     * @param int $member_id
     * @return bool
     */
    public static function is_owner($comment_id, $member_id) {
        global $wpdb;
        $table = self::get_table_name();

        $owner_id = $wpdb->get_var($wpdb->prepare(
            "SELECT member_id FROM {$table} WHERE id = %d",
            (int) $comment_id
        ));

        return (int) $owner_id === (int) $member_id;
    }

    /**
     * Format a comment for JSON response.
     *
     * @param object $comment
     * @return array
     */
    public static function format_for_json($comment) {
        $member_name = '';
        if (isset($comment->first_name)) {
            $member_name = $comment->first_name;
            if (isset($comment->last_name) && $comment->last_name) {
                $member_name .= ' ' . mb_substr($comment->last_name, 0, 1) . '.';
            }
        }

        $created_ago = '';
        if (isset($comment->created_at)) {
            $created_ago = human_time_diff(strtotime($comment->created_at), current_time('timestamp'));
        }

        return array(
            'id' => (int) $comment->id,
            'content' => wp_kses_post($comment->content),
            'member_id' => (int) $comment->member_id,
            'member_name' => $member_name,
            'created_at' => $comment->created_at,
            'created_ago' => $created_ago,
            'updated_at' => $comment->updated_at ?? null,
        );
    }
}
