<?php
/**
 * AJAX handlers for testimonials management.
 *
 * @package MjMember
 */

use Mj\Member\Classes\Crud\MjTestimonials;
use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX: Approve a testimonial.
 */
function mj_admin_testimonial_approve_handler() {
    check_ajax_referer('mj_admin_testimonial', '_wpnonce');

    if (!current_user_can(Config::capability())) {
        wp_send_json_error(__('Accès non autorisé.', 'mj-member'), 403);
    }

    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    if ($id <= 0) {
        wp_send_json_error(__('Identifiant invalide.', 'mj-member'), 400);
    }

    // Get testimonial to retrieve member_id before approving
    $testimonial = MjTestimonials::get_by_id($id);
    if (!$testimonial) {
        wp_send_json_error(__('Témoignage introuvable.', 'mj-member'), 404);
    }

    $result = MjTestimonials::approve($id);
    if (is_wp_error($result)) {
        wp_send_json_error($result->get_error_message(), 500);
    }

    // Trigger notification
    $member_id = isset($testimonial->member_id) ? (int) $testimonial->member_id : 0;
    if ($member_id > 0) {
        do_action('mj_member_testimonial_approved', $id, $member_id);
    }

    wp_send_json_success(array(
        'message' => __('Témoignage approuvé.', 'mj-member'),
        'id' => $id,
    ));
}
add_action('wp_ajax_mj_admin_testimonial_approve', 'mj_admin_testimonial_approve_handler');

/**
 * AJAX: Reject a testimonial.
 */
function mj_admin_testimonial_reject_handler() {
    check_ajax_referer('mj_admin_testimonial', '_wpnonce');

    if (!current_user_can(Config::capability())) {
        wp_send_json_error(__('Accès non autorisé.', 'mj-member'), 403);
    }

    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    if ($id <= 0) {
        wp_send_json_error(__('Identifiant invalide.', 'mj-member'), 400);
    }

    $reason = isset($_POST['reason']) ? sanitize_textarea_field(wp_unslash($_POST['reason'])) : '';

    // Get testimonial to retrieve member_id before rejecting
    $testimonial = MjTestimonials::get_by_id($id);
    if (!$testimonial) {
        wp_send_json_error(__('Témoignage introuvable.', 'mj-member'), 404);
    }

    $result = MjTestimonials::reject($id, $reason);
    if (is_wp_error($result)) {
        wp_send_json_error($result->get_error_message(), 500);
    }

    // Trigger notification
    $member_id = isset($testimonial->member_id) ? (int) $testimonial->member_id : 0;
    if ($member_id > 0) {
        do_action('mj_member_testimonial_rejected', $id, $member_id, $reason);
    }

    wp_send_json_success(array(
        'message' => __('Témoignage refusé.', 'mj-member'),
        'id' => $id,
    ));
}
add_action('wp_ajax_mj_admin_testimonial_reject', 'mj_admin_testimonial_reject_handler');

/**
 * AJAX: Delete a testimonial.
 */
function mj_admin_testimonial_delete_handler() {
    check_ajax_referer('mj_admin_testimonial', '_wpnonce');

    if (!current_user_can(Config::capability())) {
        wp_send_json_error(__('Accès non autorisé.', 'mj-member'), 403);
    }

    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    if ($id <= 0) {
        wp_send_json_error(__('Identifiant invalide.', 'mj-member'), 400);
    }

    $result = MjTestimonials::delete($id);
    if (is_wp_error($result)) {
        wp_send_json_error($result->get_error_message(), 500);
    }

    wp_send_json_success(array(
        'message' => __('Témoignage supprimé.', 'mj-member'),
        'id' => $id,
    ));
}
add_action('wp_ajax_mj_admin_testimonial_delete', 'mj_admin_testimonial_delete_handler');

/**
 * AJAX: Update a testimonial.
 */
function mj_admin_testimonial_update_handler() {
    check_ajax_referer('mj_admin_testimonial', '_wpnonce');

    if (!current_user_can(Config::capability())) {
        wp_send_json_error(__('Accès non autorisé.', 'mj-member'), 403);
    }

    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    if ($id <= 0) {
        wp_send_json_error(__('Identifiant invalide.', 'mj-member'), 400);
    }

    $data = array();

    if (isset($_POST['status'])) {
        $data['status'] = sanitize_key($_POST['status']);
    }

    if (isset($_POST['content'])) {
        $data['content'] = wp_kses_post(wp_unslash($_POST['content']));
    }

    if (isset($_POST['rejection_reason'])) {
        $data['rejection_reason'] = sanitize_textarea_field(wp_unslash($_POST['rejection_reason']));
    }

    if (empty($data)) {
        wp_send_json_error(__('Aucune donnée à mettre à jour.', 'mj-member'), 400);
    }

    $result = MjTestimonials::update($id, $data);
    if (is_wp_error($result)) {
        wp_send_json_error($result->get_error_message(), 500);
    }

    wp_send_json_success(array(
        'message' => __('Témoignage mis à jour.', 'mj-member'),
        'id' => $id,
    ));
}
add_action('wp_ajax_mj_admin_testimonial_update', 'mj_admin_testimonial_update_handler');

/**
 * AJAX: Get testimonials for a member (for registration manager).
 */
function mj_admin_testimonial_get_for_member_handler() {
    check_ajax_referer('mj-registration-manager', '_wpnonce');

    if (!current_user_can(Config::capability())) {
        wp_send_json_error(__('Accès non autorisé.', 'mj-member'), 403);
    }

    $member_id = isset($_POST['member_id']) ? (int) $_POST['member_id'] : 0;
    if ($member_id <= 0) {
        wp_send_json_error(__('Membre invalide.', 'mj-member'), 400);
    }

    $testimonials = MjTestimonials::get_for_member($member_id, array(
        'per_page' => 50,
        'orderby' => 'created_at',
        'order' => 'DESC',
    ));

    $status_labels = MjTestimonials::get_status_labels();
    $items = array();

    foreach ($testimonials as $t) {
        $photos = MjTestimonials::get_photo_urls($t, 'medium');
        $video = MjTestimonials::get_video_data($t);
        $status_key = isset($t->status) ? $t->status : 'pending';

        $items[] = array(
            'id' => (int) $t->id,
            'content' => isset($t->content) ? $t->content : '',
            'photos' => $photos,
            'video' => $video,
            'status' => $status_key,
            'statusLabel' => isset($status_labels[$status_key]) ? $status_labels[$status_key] : $status_key,
            'createdAt' => isset($t->created_at) ? $t->created_at : '',
            'reviewedAt' => isset($t->reviewed_at) ? $t->reviewed_at : null,
            'rejectionReason' => isset($t->rejection_reason) ? $t->rejection_reason : null,
        );
    }

    wp_send_json_success(array(
        'testimonials' => $items,
        'count' => count($items),
    ));
}
add_action('wp_ajax_mj_admin_testimonial_get_for_member', 'mj_admin_testimonial_get_for_member_handler');

/**
 * AJAX: Toggle featured status for a testimonial.
 */
function mj_admin_testimonial_toggle_featured_handler() {
    check_ajax_referer('mj_admin_testimonial', '_wpnonce');

    if (!current_user_can(Config::capability())) {
        wp_send_json_error(__('Accès non autorisé.', 'mj-member'), 403);
    }

    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    if ($id <= 0) {
        wp_send_json_error(__('Identifiant invalide.', 'mj-member'), 400);
    }

    $result = MjTestimonials::toggle_featured($id);
    if (is_wp_error($result)) {
        wp_send_json_error($result->get_error_message(), 500);
    }

    wp_send_json_success(array(
        'message' => $result['featured'] 
            ? __('Témoignage affiché sur la page d\'accueil.', 'mj-member')
            : __('Témoignage retiré de la page d\'accueil.', 'mj-member'),
        'id' => $id,
        'featured' => $result['featured'],
    ));
}
add_action('wp_ajax_mj_admin_testimonial_toggle_featured', 'mj_admin_testimonial_toggle_featured_handler');
