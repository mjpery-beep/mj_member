<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Ensure default roles have the custom capability used across the plugin.
 */
function mj_member_ensure_capabilities()
{
    if (!function_exists('get_role')) {
        return;
    }

    $roles = apply_filters('mj_member_capability_roles', array('administrator', 'editor'));

    foreach ($roles as $role_name) {
        $role = get_role($role_name);
        if ($role && !$role->has_cap(MJ_MEMBER_CAPABILITY)) {
            $role->add_cap(MJ_MEMBER_CAPABILITY);
        }
    }
}
add_action('init', 'mj_member_ensure_capabilities', 3);

/**
 * Remove the custom capability from default roles (used on deactivation).
 */
function mj_member_remove_capabilities()
{
    if (!function_exists('get_role')) {
        return;
    }

    $roles = apply_filters('mj_member_capability_roles', array('administrator', 'editor'));

    foreach ($roles as $role_name) {
        $role = get_role($role_name);
        if ($role && $role->has_cap(MJ_MEMBER_CAPABILITY)) {
            $role->remove_cap(MJ_MEMBER_CAPABILITY);
        }
    }
}

function mj_member_restrict_dashboard_access()
{
    if (!is_user_logged_in()) {
        return;
    }

    if (wp_doing_ajax() || (defined('DOING_CRON') && DOING_CRON)) {
        return;
    }

    $user = wp_get_current_user();
    if (empty($user) || 0 === (int) $user->ID) {
        return;
    }

    $allowed = user_can($user, 'manage_options') || user_can($user, MJ_MEMBER_CAPABILITY);
    $allowed = apply_filters('mj_member_allow_dashboard_access', $allowed, $user);

    if ($allowed) {
        return;
    }

    $redirect = '';
    if (function_exists('mj_member_get_account_redirect')) {
        $redirect = mj_member_get_account_redirect();
    }

    if ($redirect === '') {
        $redirect = home_url('/');
    }

    $redirect = apply_filters('mj_member_dashboard_redirect', $redirect, $user);

    wp_safe_redirect($redirect);
    exit;
}
add_action('admin_init', 'mj_member_restrict_dashboard_access', 99);

function mj_member_hide_admin_bar_for_members()
{
    if (!is_user_logged_in() || is_admin()) {
        return;
    }

    $user = wp_get_current_user();
    if (empty($user) || 0 === (int) $user->ID) {
        return;
    }

    if (user_can($user, 'manage_options') || user_can($user, MJ_MEMBER_CAPABILITY)) {
        return;
    }

    $show_bar = apply_filters('mj_member_show_admin_bar', false, $user);
    if ($show_bar) {
        return;
    }

    show_admin_bar(false);
}
add_action('after_setup_theme', 'mj_member_hide_admin_bar_for_members');
