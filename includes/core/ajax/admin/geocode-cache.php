<?php

use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

// Admin AJAX handler to persist geocoded coordinates for member map.
add_action('wp_ajax_mj_save_geocode_result', 'mj_save_geocode_result_callback');

function mj_save_geocode_result_callback() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'mj_save_geocode_result')) {
        wp_send_json_error(array('message' => __('Vérification de sécurité échouée.', 'mj-member')), 403);
    }

    if (!current_user_can(Config::capability())) {
        wp_send_json_error(array('message' => __('Accès refusé.', 'mj-member')), 403);
    }

    $member_id = isset($_POST['member_id']) ? (int) $_POST['member_id'] : 0;
    $lat       = isset($_POST['lat'])       ? (float) $_POST['lat']       : null;
    $lng       = isset($_POST['lng'])       ? (float) $_POST['lng']       : null;
    $hash      = isset($_POST['hash'])      ? sanitize_text_field(wp_unslash($_POST['hash'])) : '';

    if ($member_id <= 0 || $lat === null || $lng === null || $hash === '') {
        wp_send_json_error(array('message' => __('Paramètres invalides.', 'mj-member')), 400);
    }

    $cache = get_option('mj_member_geocode_cache', array());
    if (!is_array($cache)) {
        $cache = array();
    }

    $cache[$member_id] = array(
        'lat'  => $lat,
        'lng'  => $lng,
        'hash' => $hash,
    );

    update_option('mj_member_geocode_cache', $cache, false);

    wp_send_json_success();
}
