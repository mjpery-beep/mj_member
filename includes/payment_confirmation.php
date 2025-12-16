<?php

use Mj\Member\Classes\MjPayments;

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('mj_handle_payment_confirmation')) {
    function mj_handle_payment_confirmation(): void
    {
        if (empty($_GET['mj_payment_confirm'])) {
            return;
        }

        $token = isset($_GET['mj_payment_confirm']) ? sanitize_text_field(wp_unslash($_GET['mj_payment_confirm'])) : '';
        if ($token === '') {
            return;
        }

        if (!class_exists(MjPayments::class)) {
            require_once plugin_dir_path(__FILE__) . 'classes/MjPayments.php';
        }

        $ok = MjPayments::confirm_payment_by_token($token);

        $base_url = remove_query_arg('mj_payment_confirm');
        $status_param = $ok ? 'ok' : 'error';
        $redirect_url = add_query_arg('mj_payment_status', $status_param, $base_url);

        wp_safe_redirect($redirect_url);

        $should_exit = apply_filters('mj_member_payment_confirmation_should_exit', true, $redirect_url, $ok);
        if ($should_exit) {
            exit;
        }
    }

    add_action('init', 'mj_handle_payment_confirmation');
}
