<?php
/**
 * Authentication helpers for Registration Manager AJAX endpoints.
 *
 * @package MjMember
 */

use Mj\Member\Classes\Crud\MjMembers;
use Mj\Member\Classes\MjRoles;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Verify nonce and check user permissions.
 *
 * @return array|false Member data if authorized, false otherwise
 */
function mj_regmgr_verify_request() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mj-registration-manager')) {
        wp_send_json_error(array('message' => __('Vérification de sécurité échouée.', 'mj-member')), 403);
        return false;
    }

    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => __('Vous devez être connecté.', 'mj-member')), 401);
        return false;
    }

    $current_user_id = get_current_user_id();
    $member = MjMembers::getByWpUserId($current_user_id);

    if (!$member) {
        wp_send_json_error(array('message' => __('Profil membre introuvable.', 'mj-member')), 403);
        return false;
    }

    $member_role = isset($member->role) ? $member->role : '';
    $allowed_roles = array(MjRoles::ANIMATEUR, MjRoles::BENEVOLE, MjRoles::COORDINATEUR);

    if (!in_array($member_role, $allowed_roles, true) && !current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Permissions insuffisantes.', 'mj-member')), 403);
        return false;
    }

    return array(
        'member' => $member,
        'member_id' => isset($member->id) ? (int) $member->id : 0,
        'role' => $member_role,
        'is_coordinateur' => $member_role === MjRoles::COORDINATEUR || current_user_can('manage_options'),
    );
}
