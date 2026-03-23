<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX : Export des pages Mon compte vers fichiers JSON.
 */
add_action('wp_ajax_mj_member_export_account_pages', 'mj_member_ajax_export_account_pages');
function mj_member_ajax_export_account_pages(): void {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('Permissions insuffisantes', 'mj-member'));
    }

    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'mj_member_export_pages')) {
        wp_send_json_error(__('Nonce invalide', 'mj-member'));
    }

    if (!class_exists('Mj\\Member\\Classes\\MjAccountPagesExport')) {
        require_once MJ_MEMBER_PATH . 'includes/classes/MjAccountPagesExport.php';
    }

    $result = \Mj\Member\Classes\MjAccountPagesExport::exportPages();

    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error(implode(', ', $result['errors']));
    }
}

/**
 * AJAX : Import des pages Mon compte depuis fichiers JSON.
 */
add_action('wp_ajax_mj_member_import_account_pages', 'mj_member_ajax_import_account_pages');
function mj_member_ajax_import_account_pages(): void {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('Permissions insuffisantes', 'mj-member'));
    }

    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'mj_member_import_pages')) {
        wp_send_json_error(__('Nonce invalide', 'mj-member'));
    }

    if (!class_exists('Mj\\Member\\Classes\\MjAccountPagesExport')) {
        require_once MJ_MEMBER_PATH . 'includes/classes/MjAccountPagesExport.php';
    }

    $result = \Mj\Member\Classes\MjAccountPagesExport::importPages(false);

    wp_send_json_success($result);
}
