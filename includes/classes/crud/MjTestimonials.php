<?php

namespace Mj\Member\Classes\Crud;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * CRUD repository for testimonials (témoignages).
 * 
 * Testimonials can include text, photos (attachment IDs), and video (attachment ID).
 * 
 * @package MjMember
 */
class MjTestimonials implements CrudRepositoryInterface {
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    /**
     * @return string
     */
    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'mj_testimonials';
    }

    /**
     * @return array<string,string>
     */
    public static function get_status_labels() {
        return array(
            self::STATUS_PENDING => __('En attente', 'mj-member'),
            self::STATUS_APPROVED => __('Validé', 'mj-member'),
            self::STATUS_REJECTED => __('Refusé', 'mj-member'),
        );
    }

    /**
     * Sanitize status value.
     *
     * @param string $status
     * @return string
     */
    public static function sanitize_status($status) {
        $valid = array(self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_REJECTED);
        return in_array($status, $valid, true) ? $status : self::STATUS_PENDING;
    }

    /**
     * @param array<string,mixed> $args
     * @return array<int,object>
     */
    public static function get_all(array $args = array()) {
        return self::query($args);
    }

    /**
     * Query testimonials with flexible filters.
     *
     * @param array<string,mixed> $args
     * @return array<int,object>
     */
    public static function query(array $args = array()) {
        global $wpdb;
        $table = self::get_table_name();
        $members_table = MjMembers::getTableName(MjMembers::TABLE_NAME);

        $defaults = array(
            'status' => null,
            'member_id' => null,
            'search' => '',
            'orderby' => 'created_at',
            'order' => 'DESC',
            'per_page' => 50,
            'page' => 1,
        );
        $args = wp_parse_args($args, $defaults);

        $where = array('1=1');
        $values = array();

        if ($args['status'] !== null && $args['status'] !== '') {
            $where[] = 't.status = %s';
            $values[] = self::sanitize_status($args['status']);
        }

        if ($args['member_id'] !== null) {
            $where[] = 't.member_id = %d';
            $values[] = (int) $args['member_id'];
        }

        if ($args['search'] !== '') {
            $like = '%' . $wpdb->esc_like($args['search']) . '%';
            $where[] = '(t.content LIKE %s OR m.first_name LIKE %s OR m.last_name LIKE %s)';
            $values[] = $like;
            $values[] = $like;
            $values[] = $like;
        }

        $allowed_orderby = array('id', 'created_at', 'reviewed_at', 'status');
        $orderby = in_array($args['orderby'], $allowed_orderby, true) ? $args['orderby'] : 'created_at';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        $per_page = max(1, (int) $args['per_page']);
        $page = max(1, (int) $args['page']);
        $offset = ($page - 1) * $per_page;

        $where_sql = implode(' AND ', $where);

        $sql = "SELECT t.*, m.first_name, m.last_name, m.email, m.photo_id AS member_photo_id
                FROM {$table} t
                LEFT JOIN {$members_table} m ON t.member_id = m.id
                WHERE {$where_sql}
                ORDER BY t.{$orderby} {$order}
                LIMIT %d OFFSET %d";

        $values[] = $per_page;
        $values[] = $offset;

        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, $values);
        }

        $results = $wpdb->get_results($sql);
        return is_array($results) ? $results : array();
    }

    /**
     * Count testimonials matching criteria.
     *
     * @param array<string,mixed> $args
     * @return int
     */
    public static function count(array $args = array()) {
        global $wpdb;
        $table = self::get_table_name();

        $where = array('1=1');
        $values = array();

        if (isset($args['status']) && $args['status'] !== null && $args['status'] !== '') {
            $where[] = 'status = %s';
            $values[] = self::sanitize_status($args['status']);
        }

        if (isset($args['member_id']) && $args['member_id'] !== null) {
            $where[] = 'member_id = %d';
            $values[] = (int) $args['member_id'];
        }

        $where_sql = implode(' AND ', $where);

        $sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";

        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, $values);
        }

        return (int) $wpdb->get_var($sql);
    }

    /**
     * Get a single testimonial by ID.
     *
     * @param int $id
     * @return object|null
     */
    public static function get_by_id($id) {
        global $wpdb;
        $table = self::get_table_name();
        $members_table = MjMembers::getTableName(MjMembers::TABLE_NAME);

        $sql = $wpdb->prepare(
            "SELECT t.*, m.first_name, m.last_name, m.email, m.photo_id AS member_photo_id
             FROM {$table} t
             LEFT JOIN {$members_table} m ON t.member_id = m.id
             WHERE t.id = %d",
            (int) $id
        );

        return $wpdb->get_row($sql);
    }

    /**
     * Create a new testimonial.
     *
     * @param array<string,mixed> $data
     * @return int|WP_Error
     */
    public static function create($data) {
        if (!is_array($data)) {
            return new WP_Error('mj_testimonial_invalid_payload', __('Format de données invalide.', 'mj-member'));
        }

        global $wpdb;
        $table = self::get_table_name();

        $defaults = array(
            'member_id' => 0,
            'content' => '',
            'photo_ids' => array(),
            'video_id' => null,
            'link_preview' => null,
            'status' => self::STATUS_PENDING,
            'rejection_reason' => null,
            'created_at' => current_time('mysql'),
            'reviewed_at' => null,
            'reviewed_by' => null,
        );
        $payload = wp_parse_args($data, $defaults);

        $member_id = isset($payload['member_id']) ? (int) $payload['member_id'] : 0;
        if ($member_id <= 0) {
            return new WP_Error('mj_testimonial_invalid_member', __('Membre invalide.', 'mj-member'));
        }

        $content = isset($payload['content']) ? wp_kses_post($payload['content']) : '';
        $photo_ids = isset($payload['photo_ids']) && is_array($payload['photo_ids'])
            ? array_map('intval', array_filter($payload['photo_ids']))
            : array();
        $video_id = isset($payload['video_id']) && (int) $payload['video_id'] > 0
            ? (int) $payload['video_id']
            : null;
        
        // Handle link preview (JSON object with url, title, description, image, and optional YouTube data)
        $link_preview = null;
        if (!empty($payload['link_preview'])) {
            if (is_string($payload['link_preview'])) {
                $decoded = json_decode(wp_unslash($payload['link_preview']), true);
                if (is_array($decoded) && !empty($decoded['url'])) {
                    $preview_data = array(
                        'url' => esc_url_raw($decoded['url']),
                        'title' => isset($decoded['title']) ? sanitize_text_field($decoded['title']) : '',
                        'description' => isset($decoded['description']) ? sanitize_text_field($decoded['description']) : '',
                        'image' => isset($decoded['image']) ? esc_url_raw($decoded['image']) : '',
                        'site_name' => isset($decoded['site_name']) ? sanitize_text_field($decoded['site_name']) : '',
                    );
                    // Preserve YouTube data if present
                    if (!empty($decoded['is_youtube'])) {
                        $preview_data['is_youtube'] = (bool) $decoded['is_youtube'];
                    }
                    if (!empty($decoded['youtube_id'])) {
                        $preview_data['youtube_id'] = sanitize_text_field($decoded['youtube_id']);
                    }
                    $link_preview = wp_json_encode($preview_data);
                }
            } elseif (is_array($payload['link_preview']) && !empty($payload['link_preview']['url'])) {
                $preview_data = array(
                    'url' => esc_url_raw($payload['link_preview']['url']),
                    'title' => isset($payload['link_preview']['title']) ? sanitize_text_field($payload['link_preview']['title']) : '',
                    'description' => isset($payload['link_preview']['description']) ? sanitize_text_field($payload['link_preview']['description']) : '',
                    'image' => isset($payload['link_preview']['image']) ? esc_url_raw($payload['link_preview']['image']) : '',
                    'site_name' => isset($payload['link_preview']['site_name']) ? sanitize_text_field($payload['link_preview']['site_name']) : '',
                );
                // Preserve YouTube data if present
                if (!empty($payload['link_preview']['is_youtube'])) {
                    $preview_data['is_youtube'] = (bool) $payload['link_preview']['is_youtube'];
                }
                if (!empty($payload['link_preview']['youtube_id'])) {
                    $preview_data['youtube_id'] = sanitize_text_field($payload['link_preview']['youtube_id']);
                }
                $link_preview = wp_json_encode($preview_data);
            }
        }

        // At least some content is required
        if (empty($content) && empty($photo_ids) && !$video_id) {
            return new WP_Error('mj_testimonial_empty', __('Le témoignage doit contenir du texte, des photos ou une vidéo.', 'mj-member'));
        }

        $status = self::sanitize_status(isset($payload['status']) ? (string) $payload['status'] : self::STATUS_PENDING);
        $rejection_reason = isset($payload['rejection_reason']) && $payload['rejection_reason'] !== ''
            ? sanitize_textarea_field($payload['rejection_reason'])
            : null;
        $created_at = isset($payload['created_at']) ? sanitize_text_field($payload['created_at']) : current_time('mysql');
        $reviewed_at = isset($payload['reviewed_at']) && $payload['reviewed_at'] !== ''
            ? sanitize_text_field($payload['reviewed_at'])
            : null;
        $reviewed_by = isset($payload['reviewed_by']) && (int) $payload['reviewed_by'] > 0
            ? (int) $payload['reviewed_by']
            : null;

        $insert_data = array(
            'member_id' => $member_id,
            'content' => $content,
            'photo_ids' => wp_json_encode($photo_ids),
            'video_id' => $video_id,
            'link_preview' => $link_preview,
            'status' => $status,
            'rejection_reason' => $rejection_reason,
            'created_at' => $created_at,
            'reviewed_at' => $reviewed_at,
            'reviewed_by' => $reviewed_by,
        );

        $formats = array('%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d');

        $result = $wpdb->insert($table, $insert_data, $formats);
        if ($result === false) {
            return new WP_Error('mj_testimonial_insert_failed', __('Impossible d\'enregistrer le témoignage.', 'mj-member'));
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * Update an existing testimonial.
     *
     * @param int $id
     * @param array<string,mixed> $data
     * @return true|WP_Error
     */
    public static function update($id, $data) {
        if (!is_array($data)) {
            return new WP_Error('mj_testimonial_invalid_payload', __('Format de données invalide.', 'mj-member'));
        }

        global $wpdb;
        $table = self::get_table_name();
        $id = (int) $id;

        if ($id <= 0) {
            return new WP_Error('mj_testimonial_invalid_id', __('Identifiant témoignage invalide.', 'mj-member'));
        }

        $fields = array();
        $formats = array();

        if (array_key_exists('content', $data)) {
            $fields['content'] = wp_kses_post($data['content']);
            $formats[] = '%s';
        }

        if (array_key_exists('photo_ids', $data)) {
            $photo_ids = is_array($data['photo_ids'])
                ? array_map('intval', array_filter($data['photo_ids']))
                : array();
            $fields['photo_ids'] = wp_json_encode($photo_ids);
            $formats[] = '%s';
        }

        if (array_key_exists('video_id', $data)) {
            $fields['video_id'] = (int) $data['video_id'] > 0 ? (int) $data['video_id'] : null;
            $formats[] = '%d';
        }

        if (array_key_exists('status', $data)) {
            $fields['status'] = self::sanitize_status((string) $data['status']);
            $formats[] = '%s';
        }

        if (array_key_exists('rejection_reason', $data)) {
            $reason = $data['rejection_reason'];
            $fields['rejection_reason'] = ($reason !== null && $reason !== '')
                ? sanitize_textarea_field($reason)
                : null;
            $formats[] = '%s';
        }

        if (array_key_exists('reviewed_at', $data)) {
            $fields['reviewed_at'] = $data['reviewed_at'] ? sanitize_text_field($data['reviewed_at']) : null;
            $formats[] = '%s';
        }

        if (array_key_exists('reviewed_by', $data)) {
            $reviewed_by = (int) $data['reviewed_by'];
            $fields['reviewed_by'] = $reviewed_by > 0 ? $reviewed_by : null;
            $formats[] = '%d';
        }

        if (array_key_exists('featured', $data)) {
            $fields['featured'] = !empty($data['featured']) ? 1 : 0;
            $formats[] = '%d';
        }

        if (empty($fields)) {
            return true;
        }

        $result = $wpdb->update($table, $fields, array('id' => $id), $formats, array('%d'));
        if ($result === false) {
            return new WP_Error('mj_testimonial_update_failed', __('Impossible de mettre à jour le témoignage.', 'mj-member'));
        }

        return true;
    }

    /**
     * Delete a testimonial.
     *
     * @param int $id
     * @return true|WP_Error
     */
    public static function delete($id) {
        global $wpdb;
        $table = self::get_table_name();
        $id = (int) $id;

        if ($id <= 0) {
            return new WP_Error('mj_testimonial_invalid_id', __('Identifiant témoignage invalide.', 'mj-member'));
        }

        $result = $wpdb->delete($table, array('id' => $id), array('%d'));
        if ($result === false) {
            return new WP_Error('mj_testimonial_delete_failed', __('Impossible de supprimer le témoignage.', 'mj-member'));
        }

        return true;
    }

    /**
     * Get testimonials for a specific member.
     *
     * @param int $member_id
     * @param array<string,mixed> $args
     * @return array<int,object>
     */
    public static function get_for_member($member_id, array $args = array()) {
        $args['member_id'] = (int) $member_id;
        return self::query($args);
    }

    /**
     * Count testimonials for a specific member.
     *
     * @param int $member_id
     * @param string|null $status
     * @return int
     */
    public static function count_for_member($member_id, $status = null) {
        $args = array('member_id' => (int) $member_id);
        if ($status !== null) {
            $args['status'] = $status;
        }
        return self::count($args);
    }

    /**
     * Get approved testimonials (for public display).
     *
     * @param array<string,mixed> $args
     * @return array<int,object>
     */
    public static function get_approved(array $args = array()) {
        $args['status'] = self::STATUS_APPROVED;
        return self::query($args);
    }

    /**
     * Approve a testimonial.
     *
     * @param int $id
     * @param int|null $reviewer_id
     * @return true|WP_Error
     */
    public static function approve($id, $reviewer_id = null) {
        return self::update($id, array(
            'status' => self::STATUS_APPROVED,
            'reviewed_at' => current_time('mysql'),
            'reviewed_by' => $reviewer_id > 0 ? $reviewer_id : get_current_user_id(),
            'rejection_reason' => null,
        ));
    }

    /**
     * Reject a testimonial.
     *
     * @param int $id
     * @param string $reason
     * @param int|null $reviewer_id
     * @return true|WP_Error
     */
    public static function reject($id, $reason = '', $reviewer_id = null) {
        return self::update($id, array(
            'status' => self::STATUS_REJECTED,
            'reviewed_at' => current_time('mysql'),
            'reviewed_by' => $reviewer_id > 0 ? $reviewer_id : get_current_user_id(),
            'rejection_reason' => $reason,
        ));
    }

    /**
     * Get featured testimonials (for homepage display).
     *
     * @param array<string,mixed> $args
     * @return array<int,object>
     */
    public static function get_featured(array $args = array()) {
        global $wpdb;
        $table = self::get_table_name();
        $members_table = MjMembers::getTableName();

        $defaults = array(
            'per_page' => 10,
            'page' => 1,
            'orderby' => 'created_at',
            'order' => 'DESC',
        );
        $args = wp_parse_args($args, $defaults);

        $per_page = max(1, (int) $args['per_page']);
        $page = max(1, (int) $args['page']);
        $offset = ($page - 1) * $per_page;

        $sql = $wpdb->prepare(
            "SELECT t.*, m.first_name, m.last_name, m.photo_id as member_photo_id
             FROM {$table} t
             LEFT JOIN {$members_table} m ON t.member_id = m.id
             WHERE t.status = %s AND t.featured = 1
             ORDER BY t.created_at DESC
             LIMIT %d OFFSET %d",
            self::STATUS_APPROVED,
            $per_page,
            $offset
        );

        return $wpdb->get_results($sql);
    }

    /**
     * Toggle featured status for a testimonial.
     *
     * @param int $id
     * @return array{featured:bool}|WP_Error
     */
    public static function toggle_featured($id) {
        global $wpdb;
        $table = self::get_table_name();
        $id = (int) $id;

        if ($id <= 0) {
            return new \WP_Error('mj_testimonial_invalid_id', __('Identifiant témoignage invalide.', 'mj-member'));
        }

        $testimonial = self::get_by_id($id);
        if (!$testimonial) {
            return new \WP_Error('mj_testimonial_not_found', __('Témoignage non trouvé.', 'mj-member'));
        }

        $new_featured = empty($testimonial->featured) ? 1 : 0;
        $result = self::update($id, array('featured' => $new_featured));

        if (is_wp_error($result)) {
            return $result;
        }

        return array('featured' => (bool) $new_featured);
    }

    /**
     * Parse photo_ids JSON field.
     *
     * @param object $testimonial
     * @return array<int>
     */
    public static function parse_photo_ids($testimonial) {
        if (!isset($testimonial->photo_ids)) {
            return array();
        }

        $raw = $testimonial->photo_ids;
        if (is_array($raw)) {
            return array_map('intval', $raw);
        }

        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return array_map('intval', $decoded);
            }
        }

        return array();
    }

    /**
     * Get photo URLs for a testimonial.
     *
     * @param object $testimonial
     * @param string $size
     * @return array<int,array{id:int,url:string,thumb:string}>
     */
    public static function get_photo_urls($testimonial, $size = 'medium') {
        $photo_ids = self::parse_photo_ids($testimonial);
        $photos = array();

        foreach ($photo_ids as $photo_id) {
            if ($photo_id <= 0) {
                continue;
            }

            $url = wp_get_attachment_image_url($photo_id, $size);
            $thumb = wp_get_attachment_image_url($photo_id, 'thumbnail');
            $full = wp_get_attachment_image_url($photo_id, 'full');

            if ($url) {
                $photos[] = array(
                    'id' => $photo_id,
                    'url' => $url,
                    'thumb' => $thumb ?: $url,
                    'full' => $full ?: $url,
                );
            }
        }

        return $photos;
    }

    /**
     * Get video URL for a testimonial.
     *
     * @param object $testimonial
     * @return array{id:int,url:string,poster:string}|null
     */
    public static function get_video_data($testimonial) {
        $video_id = isset($testimonial->video_id) ? (int) $testimonial->video_id : 0;
        if ($video_id <= 0) {
            return null;
        }

        $url = wp_get_attachment_url($video_id);
        if (!$url) {
            return null;
        }

        $poster = get_post_meta($video_id, '_wp_attachment_metadata', true);
        $poster_url = '';
        if (is_array($poster) && isset($poster['image']['sizes']['medium']['file'])) {
            $uploads = wp_upload_dir();
            $poster_url = $uploads['baseurl'] . '/' . dirname($poster['file']) . '/' . $poster['image']['sizes']['medium']['file'];
        }

        return array(
            'id' => $video_id,
            'url' => $url,
            'poster' => $poster_url,
        );
    }

    /**
     * Get link preview data for a testimonial.
     *
     * @param object $testimonial
     * @return array{url:string,title:string,description:string,image:string,site_name:string,is_youtube?:bool,youtube_id?:string}|null
     */
    public static function get_link_preview($testimonial) {
        if (empty($testimonial->link_preview)) {
            return null;
        }

        $data = json_decode($testimonial->link_preview, true);
        if (!is_array($data) || empty($data['url'])) {
            return null;
        }

        $preview = array(
            'url' => $data['url'] ?? '',
            'title' => $data['title'] ?? '',
            'description' => $data['description'] ?? '',
            'image' => $data['image'] ?? '',
            'site_name' => $data['site_name'] ?? '',
        );

        // Include YouTube data if present
        if (!empty($data['is_youtube'])) {
            $preview['is_youtube'] = (bool) $data['is_youtube'];
        }
        if (!empty($data['youtube_id'])) {
            $preview['youtube_id'] = $data['youtube_id'];
        }

        return $preview;
    }
}
