<?php

use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_ajax_mj_documents_get_nc_member_creds', 'mj_documents_get_nc_member_creds_callback');

/**
 * Return the Nextcloud login + app-password for a given member.
 * Admin-only endpoint (requires Config::capability() or coordinateur role).
 */
function mj_documents_get_nc_member_creds_callback(): void
{
    // Capability check
    $isCoordinateur = function_exists('mj_member_is_coordinateur') && mj_member_is_coordinateur();
    if (!current_user_can(Config::capability()) && !$isCoordinateur) {
        wp_send_json_error(array('message' => __('Accès refusé.', 'mj-member')), 403);
        return;
    }

    // Nonce check
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'mj_documents_nc_switch')) {
        wp_send_json_error(array('message' => __('Nonce invalide.', 'mj-member')), 403);
        return;
    }

    // Member ID
    $memberId = isset($_POST['member_id']) ? (int) $_POST['member_id'] : 0;
    if ($memberId <= 0) {
        wp_send_json_error(array('message' => __('ID membre invalide.', 'mj-member')), 400);
        return;
    }

    global $wpdb;
    $table = class_exists('MjMembers')
        ? MjMembers::getTableName(MjMembers::TABLE_NAME)
        : $wpdb->prefix . 'mj_members';

    $columns = $wpdb->get_col("SHOW COLUMNS FROM {$table}");
    if (!is_array($columns) || !in_array('member_nextcloud_login', $columns, true)) {
        wp_send_json_error(array('message' => __('Les colonnes Nextcloud ne sont pas encore présentes dans la base. Lancez la migration du plugin.', 'mj-member')), 503);
        return;
    }

    $row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT member_nextcloud_login, member_nextcloud_password FROM {$table} WHERE id = %d LIMIT 1",
            $memberId
        ),
        ARRAY_A
    );

    if (!$row || empty($row['member_nextcloud_login'])) {
        wp_send_json_error(array('message' => __('Aucun compte Nextcloud pour ce membre.', 'mj-member')), 404);
        return;
    }

    wp_send_json_success(array(
        'login'    => (string) $row['member_nextcloud_login'],
        'password' => (string) ($row['member_nextcloud_password'] ?? ''),
    ));
}
