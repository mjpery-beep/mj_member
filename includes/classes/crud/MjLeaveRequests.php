<?php

namespace Mj\Member\Classes\Crud;

use Mj\Member\Classes\MjTools;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * CRUD repository for leave requests (mj_leave_requests).
 */
class MjLeaveRequests extends MjTools implements CrudRepositoryInterface
{
    private const TABLE = 'mj_leave_requests';

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    /**
     * @return string
     */
    private static function table_name(): string
    {
        if (function_exists('mj_member_get_leave_requests_table_name')) {
            return mj_member_get_leave_requests_table_name();
        }

        return self::getTableName(self::TABLE);
    }

    /**
     * @return array<int,string>
     */
    public static function statuses(): array
    {
        return array(self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_REJECTED);
    }

    /**
     * Get status labels for JavaScript.
     *
     * @return array<string,string>
     */
    public static function get_status_labels(): array
    {
        return array(
            self::STATUS_PENDING => __('En attente', 'mj-member'),
            self::STATUS_APPROVED => __('Approuvée', 'mj-member'),
            self::STATUS_REJECTED => __('Refusée', 'mj-member'),
        );
    }

    /**
     * Normalize status value.
     *
     * @param string $status
     * @return string
     */
    private static function normalize_status(string $status): string
    {
        $status = strtolower(trim($status));

        return in_array($status, self::statuses(), true) ? $status : self::STATUS_PENDING;
    }

    /**
     * Get all leave requests.
     *
     * @param array<string,mixed> $args
     * @return array<int,object>
     */
    public static function get_all(array $args = array()): array
    {
        global $wpdb;
        $table = self::table_name();

        $defaults = array(
            'member_id' => 0,
            'type_id' => 0,
            'status' => '',
            'statuses' => array(),
            'year' => 0,
            'limit' => 0,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC',
        );
        $args = wp_parse_args($args, $defaults);

        $where = array();
        $params = array();

        if ((int) $args['member_id'] > 0) {
            $where[] = 'member_id = %d';
            $params[] = (int) $args['member_id'];
        }

        if ((int) $args['type_id'] > 0) {
            $where[] = 'type_id = %d';
            $params[] = (int) $args['type_id'];
        }

        if (!empty($args['statuses']) && is_array($args['statuses'])) {
            $placeholders = implode(', ', array_fill(0, count($args['statuses']), '%s'));
            $where[] = "status IN ({$placeholders})";
            foreach ($args['statuses'] as $s) {
                $params[] = self::normalize_status($s);
            }
        } elseif (!empty($args['status'])) {
            $where[] = 'status = %s';
            $params[] = self::normalize_status($args['status']);
        }

        if ((int) $args['year'] > 0) {
            $where[] = 'YEAR(created_at) = %d';
            $params[] = (int) $args['year'];
        }

        $orderby = in_array($args['orderby'], array('created_at', 'updated_at', 'status', 'id'), true) ? $args['orderby'] : 'created_at';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        $sql = "SELECT * FROM {$table}";
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= " ORDER BY {$orderby} {$order}";

        if ((int) $args['limit'] > 0) {
            $sql .= ' LIMIT ' . (int) $args['limit'];
            if ((int) $args['offset'] > 0) {
                $sql .= ' OFFSET ' . (int) $args['offset'];
            }
        }

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        $results = $wpdb->get_results($sql);

        return is_array($results) ? $results : array();
    }

    /**     * Count leave requests.
     *
     * @param array<string,mixed> $args
     * @return int
     */
    public static function count(array $args = array())
    {
        global $wpdb;
        $table = self::table_name();

        $where = array();
        $params = array();

        if (!empty($args['member_id'])) {
            $where[] = 'member_id = %d';
            $params[] = (int) $args['member_id'];
        }

        if (!empty($args['type_id'])) {
            $where[] = 'type_id = %d';
            $params[] = (int) $args['type_id'];
        }

        if (!empty($args['status'])) {
            $where[] = 'status = %s';
            $params[] = self::normalize_status($args['status']);
        }

        if (!empty($args['year'])) {
            $where[] = 'YEAR(created_at) = %d';
            $params[] = (int) $args['year'];
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

    /**     * Get leave requests for a specific member.
     *
     * @param int $member_id
     * @param array<string,mixed> $args
     * @return array<int,object>
     */
    public static function get_by_member(int $member_id, array $args = array()): array
    {
        $args['member_id'] = $member_id;

        return self::get_all($args);
    }

    /**
     * Get pending leave requests.
     *
     * @param array<string,mixed> $args
     * @return array<int,object>
     */
    public static function get_pending(array $args = array()): array
    {
        $args['status'] = self::STATUS_PENDING;

        return self::get_all($args);
    }

    /**
     * Get leave request by ID.
     *
     * @param int $id
     * @return object|null
     */
    public static function get_by_id(int $id): ?object
    {
        global $wpdb;
        $table = self::table_name();

        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));

        return $row ?: null;
    }

    /**
     * Calculate the number of days used for a specific member/type/year.
     *
     * @param int $member_id
     * @param int $type_id
     * @param int $year
     * @return int
     */
    public static function get_days_used(int $member_id, int $type_id, int $year = 0): int
    {
        if ($year === 0) {
            $year = (int) gmdate('Y');
        }

        $requests = self::get_all(array(
            'member_id' => $member_id,
            'type_id' => $type_id,
            'status' => self::STATUS_APPROVED,
            'year' => $year,
        ));

        $total_days = 0;
        foreach ($requests as $request) {
            $dates = json_decode($request->dates, true);
            if (is_array($dates)) {
                $total_days += count($dates);
            }
        }

        return $total_days;
    }

    /**
     * Get days used per type for a member.
     *
     * @param int $member_id
     * @param int $year
     * @return array<int,int>
     */
    public static function get_days_used_by_type(int $member_id, int $year = 0): array
    {
        if ($year === 0) {
            $year = (int) gmdate('Y');
        }

        $types = MjLeaveTypes::get_active();
        $result = array();

        foreach ($types as $type) {
            $result[(int) $type->id] = self::get_days_used($member_id, (int) $type->id, $year);
        }

        return $result;
    }

    /**
     * Create a new leave request.
     *
     * @param array<string,mixed> $data
     * @return int|WP_Error
     */
    public static function create($data)
    {
        global $wpdb;
        $table = self::table_name();

        if (empty($data['member_id'])) {
            return new WP_Error('mj_leave_request_invalid', __('Membre non spécifié.', 'mj-member'));
        }

        if (empty($data['type_id'])) {
            return new WP_Error('mj_leave_request_invalid', __('Type de congé non spécifié.', 'mj-member'));
        }

        $type = MjLeaveTypes::get_by_id((int) $data['type_id']);
        if (!$type) {
            return new WP_Error('mj_leave_request_invalid', __('Type de congé invalide.', 'mj-member'));
        }

        if (empty($data['dates'])) {
            return new WP_Error('mj_leave_request_invalid', __('Aucune date sélectionnée.', 'mj-member'));
        }

        $dates = is_array($data['dates']) ? $data['dates'] : json_decode($data['dates'], true);
        if (!is_array($dates) || empty($dates)) {
            return new WP_Error('mj_leave_request_invalid', __('Dates invalides.', 'mj-member'));
        }

        // Determine initial status based on type configuration
        $initial_status = MjLeaveTypes::requires_validation((int) $data['type_id'])
            ? self::STATUS_PENDING
            : self::STATUS_APPROVED;

        $insert = array(
            'member_id' => (int) $data['member_id'],
            'type_id' => (int) $data['type_id'],
            'status' => $initial_status,
            'dates' => wp_json_encode(array_values(array_unique($dates))),
            'reason' => isset($data['reason']) ? sanitize_textarea_field($data['reason']) : null,
            'certificate_file' => isset($data['certificate_file']) ? sanitize_file_name($data['certificate_file']) : null,
        );

        // If auto-approved, set reviewer info
        if ($initial_status === self::STATUS_APPROVED) {
            $insert['reviewed_at'] = current_time('mysql');
            $insert['reviewer_comment'] = __('Approbation automatique', 'mj-member');
        }

        $result = $wpdb->insert($table, $insert);

        if ($result === false) {
            return new WP_Error('mj_leave_request_create_failed', __('Impossible de créer la demande de congé.', 'mj-member'));
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * Update a leave request.
     *
     * @param int $id
     * @param array<string,mixed> $data
     * @return bool|WP_Error
     */
    public static function update($id, $data)
    {
        global $wpdb;
        $table = self::table_name();

        $existing = self::get_by_id($id);
        if (!$existing) {
            return new WP_Error('mj_leave_request_not_found', __('Demande de congé introuvable.', 'mj-member'));
        }

        $updates = array();

        if (isset($data['status'])) {
            $updates['status'] = self::normalize_status($data['status']);
        }
        if (isset($data['reason'])) {
            $updates['reason'] = sanitize_textarea_field($data['reason']);
        }
        if (isset($data['certificate_file'])) {
            $updates['certificate_file'] = sanitize_file_name($data['certificate_file']);
        }
        if (isset($data['reviewed_by'])) {
            $updates['reviewed_by'] = (int) $data['reviewed_by'];
        }
        if (isset($data['reviewed_at'])) {
            $updates['reviewed_at'] = $data['reviewed_at'];
        }
        if (isset($data['reviewer_comment'])) {
            $updates['reviewer_comment'] = sanitize_textarea_field($data['reviewer_comment']);
        }

        if (empty($updates)) {
            return true;
        }

        $result = $wpdb->update($table, $updates, array('id' => $id));

        if ($result === false) {
            return new WP_Error('mj_leave_request_update_failed', __('Impossible de mettre à jour la demande.', 'mj-member'));
        }

        return true;
    }

    /**
     * Approve a leave request.
     *
     * @param int $id
     * @param int $reviewer_id
     * @param string $comment
     * @return bool|WP_Error
     */
    public static function approve(int $id, int $reviewer_id, string $comment = '')
    {
        return self::update($id, array(
            'status' => self::STATUS_APPROVED,
            'reviewed_by' => $reviewer_id,
            'reviewed_at' => current_time('mysql'),
            'reviewer_comment' => $comment,
        ));
    }

    /**
     * Reject a leave request.
     *
     * @param int $id
     * @param int $reviewer_id
     * @param string $comment
     * @return bool|WP_Error
     */
    public static function reject(int $id, int $reviewer_id, string $comment = '')
    {
        return self::update($id, array(
            'status' => self::STATUS_REJECTED,
            'reviewed_by' => $reviewer_id,
            'reviewed_at' => current_time('mysql'),
            'reviewer_comment' => $comment,
        ));
    }

    /**
     * Cancel a leave request (member can cancel their own pending requests).
     *
     * @param int $id
     * @param int $member_id
     * @return bool|WP_Error
     */
    public static function cancel(int $id, int $member_id)
    {
        $request = self::get_by_id($id);

        if (!$request) {
            return new WP_Error('mj_leave_request_not_found', __('Demande de congé introuvable.', 'mj-member'));
        }

        if ((int) $request->member_id !== $member_id) {
            return new WP_Error('mj_leave_request_forbidden', __('Vous ne pouvez pas annuler cette demande.', 'mj-member'));
        }

        if ($request->status !== self::STATUS_PENDING) {
            return new WP_Error('mj_leave_request_not_pending', __('Seules les demandes en attente peuvent être annulées.', 'mj-member'));
        }

        return self::delete($id);
    }

    /**
     * Delete a leave request.
     *
     * @param int $id
     * @return bool|WP_Error
     */
    public static function delete($id)
    {
        global $wpdb;
        $table = self::table_name();

        $result = $wpdb->delete($table, array('id' => $id), array('%d'));

        if ($result === false) {
            return new WP_Error('mj_leave_request_delete_failed', __('Impossible de supprimer la demande.', 'mj-member'));
        }

        return true;
    }

    /**
     * Enrich requests with type and member data for display.
     *
     * @param array<int,object> $requests
     * @return array<int,object>
     */
    public static function enrich(array $requests): array
    {
        foreach ($requests as &$request) {
            // Parse dates
            $request->dates_array = json_decode($request->dates, true) ?: array();
            $request->days_count = count($request->dates_array);

            // Get type info
            $type = MjLeaveTypes::get_by_id((int) $request->type_id);
            $request->type_name = $type ? $type->name : __('Inconnu', 'mj-member');
            $request->type_slug = $type ? $type->slug : '';
            $request->type_color = $type ? $type->color : '#6366f1';

            // Get member info
            if (class_exists('Mj\\Member\\Classes\\Crud\\MjMembers')) {
                $member = MjMembers::getById((int) $request->member_id);
                $request->member_name = $member ? trim($member->first_name . ' ' . $member->last_name) : __('Membre inconnu', 'mj-member');
                // Get avatar URL from member's avatar_id (WordPress attachment)
                if ($member && !empty($member->avatar_id)) {
                    $avatar_src = wp_get_attachment_image_src((int) $member->avatar_id, 'thumbnail');
                    $request->member_avatar = $avatar_src ? $avatar_src[0] : '';
                } else {
                    // Fallback to Gravatar/WordPress default
                    $request->member_avatar = get_avatar_url(0, ['size' => 40, 'default' => 'mystery', 'force_default' => true]);
                }
            } else {
                $request->member_name = __('Membre #', 'mj-member') . $request->member_id;
                $request->member_avatar = '';
            }

            // Get reviewer info
            if (!empty($request->reviewed_by)) {
                if (class_exists('Mj\\Member\\Classes\\Crud\\MjMembers')) {
                    $reviewer = MjMembers::getById((int) $request->reviewed_by);
                    $request->reviewer_name = $reviewer ? trim($reviewer->first_name . ' ' . $reviewer->last_name) : '';
                } else {
                    $request->reviewer_name = '';
                }
            } else {
                $request->reviewer_name = '';
            }

            // Status label
            $labels = self::get_status_labels();
            $request->status_label = $labels[$request->status] ?? $request->status;
        }

        return $requests;
    }
}
