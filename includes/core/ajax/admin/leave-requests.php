<?php
/**
 * AJAX handlers for Leave Requests (admin/coordinator).
 *
 * @package MJ_Member
 */

if (!defined('ABSPATH')) {
    exit;
}

use Mj\Member\Classes\Crud\MjLeaveRequests;
use Mj\Member\Classes\Crud\MjLeaveTypes;
use Mj\Member\Classes\Crud\MjMembers;
use Mj\Member\Classes\MjRoles;
use Mj\Member\Core\Config;

/**
 * Register admin AJAX actions for leave requests.
 *
 * @return void
 */
function mj_member_register_leave_requests_admin_ajax(): void
{
    add_action('wp_ajax_mj_leave_request_approve', 'mj_member_leave_request_approve_handler');
    add_action('wp_ajax_mj_leave_request_reject', 'mj_member_leave_request_reject_handler');
    add_action('wp_ajax_mj_leave_request_list', 'mj_member_leave_request_list_handler');
    add_action('wp_ajax_mj_leave_request_by_member', 'mj_member_leave_request_by_member_handler');
}
add_action('init', 'mj_member_register_leave_requests_admin_ajax');

/**
 * Check if current user is a coordinator.
 *
 * @return array|false Member data if coordinator, false otherwise.
 */
function mj_member_leave_requests_check_coordinator()
{
    $userId = get_current_user_id();
    if (!$userId) {
        return false;
    }

    $memberObj = MjMembers::getByWpUserId($userId);
    if (!$memberObj) {
        return false;
    }

    $member = $memberObj->toArray();

    if ($member['role'] !== MjRoles::COORDINATEUR) {
        return false;
    }

    return $member;
}

/**
 * Handle approving a leave request.
 *
 * @return void
 */
function mj_member_leave_request_approve_handler(): void
{
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'mj-leave-requests')) {
        wp_send_json_error(['message' => __('Sécurité échouée.', 'mj-member')], 403);
    }

    // Check coordinator access
    $coordinator = mj_member_leave_requests_check_coordinator();
    if (!$coordinator) {
        wp_send_json_error(['message' => __('Accès réservé aux coordinateurs.', 'mj-member')], 403);
    }

    $requestId = isset($_POST['request_id']) ? (int) $_POST['request_id'] : 0;

    // Get the request
    $request = MjLeaveRequests::get_by_id($requestId);
    if (!$request) {
        wp_send_json_error(['message' => __('Demande non trouvée.', 'mj-member')], 404);
    }

    // Can only approve pending requests
    if ($request->status !== MjLeaveRequests::STATUS_PENDING) {
        wp_send_json_error(['message' => __('Cette demande a déjà été traitée.', 'mj-member')], 400);
    }

    // Optional comment
    $comment = isset($_POST['comment']) ? sanitize_textarea_field(wp_unslash($_POST['comment'])) : '';

    // Approve the request
    $updated = MjLeaveRequests::approve($requestId, (int) $coordinator['id'], $comment);
    if (!$updated) {
        wp_send_json_error(['message' => __('Erreur lors de l\'approbation.', 'mj-member')], 500);
    }

    // Notify the member
    mj_member_notify_leave_request_reviewed($requestId, 'approved');

    // Return updated request
    $request = MjLeaveRequests::get_by_id($requestId);

    wp_send_json_success([
        'message' => __('Demande approuvée avec succès.', 'mj-member'),
        'request' => $request,
    ]);
}

/**
 * Handle rejecting a leave request.
 *
 * @return void
 */
function mj_member_leave_request_reject_handler(): void
{
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'mj-leave-requests')) {
        wp_send_json_error(['message' => __('Sécurité échouée.', 'mj-member')], 403);
    }

    // Check coordinator access
    $coordinator = mj_member_leave_requests_check_coordinator();
    if (!$coordinator) {
        wp_send_json_error(['message' => __('Accès réservé aux coordinateurs.', 'mj-member')], 403);
    }

    $requestId = isset($_POST['request_id']) ? (int) $_POST['request_id'] : 0;

    // Get the request
    $request = MjLeaveRequests::get_by_id($requestId);
    if (!$request) {
        wp_send_json_error(['message' => __('Demande non trouvée.', 'mj-member')], 404);
    }

    // Can only reject pending requests
    if ($request->status !== MjLeaveRequests::STATUS_PENDING) {
        wp_send_json_error(['message' => __('Cette demande a déjà été traitée.', 'mj-member')], 400);
    }

    // Comment is required for rejection
    $comment = isset($_POST['comment']) ? sanitize_textarea_field(wp_unslash($_POST['comment'])) : '';
    if (empty(trim($comment))) {
        wp_send_json_error(['message' => __('Veuillez indiquer un motif de refus.', 'mj-member')], 400);
    }

    // Reject the request
    $updated = MjLeaveRequests::reject($requestId, (int) $coordinator['id'], $comment);
    if (!$updated) {
        wp_send_json_error(['message' => __('Erreur lors du refus.', 'mj-member')], 500);
    }

    // Notify the member
    mj_member_notify_leave_request_reviewed($requestId, 'rejected');

    // Return updated request
    $request = MjLeaveRequests::get_by_id($requestId);

    wp_send_json_success([
        'message' => __('Demande refusée.', 'mj-member'),
        'request' => $request,
    ]);
}

/**
 * Handle listing leave requests (for coordinators).
 *
 * @return void
 */
function mj_member_leave_request_list_handler(): void
{
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'mj-leave-requests')) {
        wp_send_json_error(['message' => __('Sécurité échouée.', 'mj-member')], 403);
    }

    // Check coordinator access
    $coordinator = mj_member_leave_requests_check_coordinator();
    if (!$coordinator) {
        wp_send_json_error(['message' => __('Accès réservé aux coordinateurs.', 'mj-member')], 403);
    }

    // Filters
    $status = isset($_POST['status']) ? sanitize_key($_POST['status']) : '';
    $memberId = isset($_POST['member_id']) ? (int) $_POST['member_id'] : 0;
    $year = isset($_POST['year']) ? (int) $_POST['year'] : (int) date('Y');

    $args = [];
    if ($status && in_array($status, [MjLeaveRequests::STATUS_PENDING, MjLeaveRequests::STATUS_APPROVED, MjLeaveRequests::STATUS_REJECTED], true)) {
        $args['status'] = $status;
    }
    if ($memberId > 0) {
        $args['member_id'] = $memberId;
    }
    if ($year > 0) {
        $args['year'] = $year;
    }

    $requests = MjLeaveRequests::get_all($args);

    // Enrich with member names
    $enriched = [];
    foreach ($requests as $request) {
        $memberObj = MjMembers::getById((int) $request->member_id);
        $member = $memberObj ? $memberObj->toArray() : null;
        $request->member_name = $member ? trim($member['first_name'] . ' ' . $member['last_name']) : __('Inconnu', 'mj-member');
        $enriched[] = $request;
    }

    wp_send_json_success([
        'requests' => $enriched,
        'total' => count($enriched),
    ]);
}

/**
 * Handle getting leave requests for a specific member (coordinator view).
 *
 * @return void
 */
function mj_member_leave_request_by_member_handler(): void
{
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'mj-leave-requests')) {
        wp_send_json_error(['message' => __('Sécurité échouée.', 'mj-member')], 403);
    }

    // Check coordinator access
    $coordinator = mj_member_leave_requests_check_coordinator();
    if (!$coordinator) {
        wp_send_json_error(['message' => __('Accès réservé aux coordinateurs.', 'mj-member')], 403);
    }

    $memberId = isset($_POST['member_id']) ? (int) $_POST['member_id'] : 0;
    if ($memberId <= 0) {
        wp_send_json_error(['message' => __('ID de membre invalide.', 'mj-member')], 400);
    }

    // Get member info
    $memberObj = MjMembers::getById($memberId);
    $member = $memberObj ? $memberObj->toArray() : null;
    if (!$member) {
        wp_send_json_error(['message' => __('Membre non trouvé.', 'mj-member')], 404);
    }

    // Get year parameter
    $year = isset($_POST['year']) ? (int) $_POST['year'] : (int) date('Y');
    $currentYear = (int) date('Y');
    if ($year < $currentYear - 5 || $year > $currentYear + 1) {
        $year = $currentYear;
    }

    // Get requests for selected year
    $requests = MjLeaveRequests::get_by_member($memberId, ['year' => $year]);
    // Enrich requests with type and member data so JS can display dates_array
    $requests = !empty($requests) ? MjLeaveRequests::enrich($requests) : [];

    // Get quotas
    $quotas = [
        'paid' => (int) ($member['leave_quota_paid'] ?? 0),
        'unpaid' => (int) ($member['leave_quota_unpaid'] ?? 0),
        'exceptional' => (int) ($member['leave_quota_exceptional'] ?? 0),
        'recovery' => (int) ($member['leave_quota_recovery'] ?? 0),
    ];

    // Get usage by type for selected year
    $types = MjLeaveTypes::get_active();
    $usage = [];
    foreach ($types as $type) {
        $usage[$type->slug] = MjLeaveRequests::get_days_used($memberId, (int) $type->id, $year);
    }

    wp_send_json_success([
        'member' => [
            'id' => $memberId,
            'name' => trim($member['first_name'] . ' ' . $member['last_name']),
            'role' => $member['role'],
        ],
        'quotas' => $quotas,
        'usage' => $usage,
        'year' => $year,
        'requests' => array_values($requests),
    ]);
}

/**
 * Notify member about leave request being reviewed.
 *
 * @param int    $requestId Request ID.
 * @param string $action    'approved' or 'rejected'.
 * @return void
 */
function mj_member_notify_leave_request_reviewed(int $requestId, string $action): void
{
    if (!function_exists('mj_member_record_notification')) {
        return;
    }

    $request = MjLeaveRequests::get_by_id($requestId);
    if (!$request) {
        return;
    }

    $type = MjLeaveTypes::get_by_id((int) $request->type_id);
    $typeName = $type ? $type->name : __('Congé', 'mj-member');

    $dates = json_decode($request->dates, true);
    $dateCount = is_array($dates) ? count($dates) : 0;

    if ($action === 'approved') {
        $title = __('Demande de congé approuvée', 'mj-member');
        $excerpt = sprintf(
            /* translators: 1: leave type, 2: number of days */
            __('Votre demande de %1$s (%2$d jour(s)) a été approuvée.', 'mj-member'),
            $typeName,
            $dateCount
        );
        $notifType = 'leave_request_approved';
    } else {
        $title = __('Demande de congé refusée', 'mj-member');
        $excerpt = sprintf(
            /* translators: 1: leave type, 2: number of days, 3: rejection reason */
            __('Votre demande de %1$s (%2$d jour(s)) a été refusée. Motif : %3$s', 'mj-member'),
            $typeName,
            $dateCount,
            $request->reviewer_comment ?? __('Non spécifié', 'mj-member')
        );
        $notifType = 'leave_request_rejected';
    }

    mj_member_record_notification(
        [
            'type' => $notifType,
            'title' => $title,
            'excerpt' => $excerpt,
            'url' => home_url('/ressources-humaine/'),
            'payload' => [
                'request_id' => $requestId,
                'action' => $action,
            ],
        ],
        [['member_id' => (int) $request->member_id]]
    );
}
