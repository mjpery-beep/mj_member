<?php

use Mj\Member\Core\Config;
use Mj\Member\Classes\MjRoles;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Ensure default roles have the custom capability used across the plugin.
 * 
 * Note: Les rôles ici sont des rôles WORDPRESS (pas MJ Member).
 * Ils doivent correspondre aux slugs des rôles WP créés.
 */
function mj_member_ensure_capabilities()
{
    if (!function_exists('get_role')) {
        return;
    }

    // Rôles WordPress qui auront accès à l'admin MJ Member
    $roles = class_exists(MjRoles::class) 
        ? MjRoles::getWordPressAdminRoles()
        : apply_filters('mj_member_capability_roles', array('administrator', MjRoles::ANIMATEUR, MjRoles::COORDINATEUR));
    
    $capability = Config::capability();
    $contactCapability = Config::contactCapability();
    $hoursCapability = Config::hoursCapability();
    $todosCapability = Config::todosCapability();
    $documentsCapability = Config::documentsCapability();
    $allRoleNames = function_exists('wp_roles') && wp_roles() ? array_keys(wp_roles()->role_names) : array();

    foreach ($roles as $role_name) {
        $role = get_role($role_name);
        if ($role && !$role->has_cap($capability)) {
            $role->add_cap($capability);
        }
        if ($contactCapability !== '' && $role && !$role->has_cap($contactCapability)) {
            $role->add_cap($contactCapability);
        }
    }

    // Ensure non-allowed roles do not keep the main capability
    if ($capability !== '' && !empty($allRoleNames)) {
        foreach ($allRoleNames as $role_name) {
            if (in_array($role_name, $roles, true)) {
                continue;
            }
            $role = get_role($role_name);
            if ($role && $role->has_cap($capability)) {
                $role->remove_cap($capability);
            }
        }
    }

    if ($hoursCapability !== '') {
        $hoursRoles = class_exists(MjRoles::class)
            ? MjRoles::getWordPressHoursRoles()
            : apply_filters('mj_member_hours_capability_roles', array('administrator', MjRoles::ANIMATEUR, MjRoles::COORDINATEUR));
        foreach ($hoursRoles as $role_name) {
            $role = get_role($role_name);
            if ($role && !$role->has_cap($hoursCapability)) {
                $role->add_cap($hoursCapability);
            }
        }

        // Remove hours capability from all other roles (e.g. jeune, benevole, subscriber)
        if (!empty($allRoleNames)) {
            foreach ($allRoleNames as $role_name) {
                if (in_array($role_name, $hoursRoles, true)) {
                    continue;
                }
                $role = get_role($role_name);
                if ($role && $role->has_cap($hoursCapability)) {
                    $role->remove_cap($hoursCapability);
                }
            }
        }
    }

    if ($todosCapability !== '') {
        $todoRoles = class_exists(MjRoles::class)
            ? MjRoles::getWordPressTodosRoles()
            : apply_filters('mj_member_todos_capability_roles', array('administrator', MjRoles::ANIMATEUR, MjRoles::COORDINATEUR));
        foreach ($todoRoles as $role_name) {
            $role = get_role($role_name);
            if ($role && !$role->has_cap($todosCapability)) {
                $role->add_cap($todosCapability);
            }
        }

        // Remove todos capability from all other roles
        if (!empty($allRoleNames)) {
            foreach ($allRoleNames as $role_name) {
                if (in_array($role_name, $todoRoles, true)) {
                    continue;
                }
                $role = get_role($role_name);
                if ($role && $role->has_cap($todosCapability)) {
                    $role->remove_cap($todosCapability);
                }
            }
        }
    }

    if ($documentsCapability !== '') {
        $documentRoles = class_exists(MjRoles::class)
            ? MjRoles::getWordPressDocumentsRoles()
            : apply_filters('mj_member_documents_capability_roles', array('administrator', MjRoles::ANIMATEUR));
        foreach ($documentRoles as $role_name) {
            $role = get_role($role_name);
            if ($role && !$role->has_cap($documentsCapability)) {
                $role->add_cap($documentsCapability);
            }
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

    $roles = class_exists(MjRoles::class)
        ? MjRoles::getWordPressAdminRoles()
        : apply_filters('mj_member_capability_roles', array('administrator', MjRoles::ANIMATEUR, MjRoles::COORDINATEUR));
    $capability = Config::capability();
    $contactCapability = Config::contactCapability();
    $hoursCapability = Config::hoursCapability();
    $todosCapability = Config::todosCapability();
    $documentsCapability = Config::documentsCapability();

    foreach ($roles as $role_name) {
        $role = get_role($role_name);
        if ($role && $role->has_cap($capability)) {
            $role->remove_cap($capability);
        }
        if ($contactCapability !== '' && $role && $role->has_cap($contactCapability)) {
            $role->remove_cap($contactCapability);
        }
    }

    if ($hoursCapability !== '') {
        $hoursRoles = class_exists(MjRoles::class)
            ? MjRoles::getWordPressHoursRoles()
            : apply_filters('mj_member_hours_capability_roles', array('administrator', MjRoles::ANIMATEUR, MjRoles::COORDINATEUR));
        foreach ($hoursRoles as $role_name) {
            $role = get_role($role_name);
            if ($role && $role->has_cap($hoursCapability)) {
                $role->remove_cap($hoursCapability);
            }
        }

        $legacyHoursRoles = array(MjRoles::BENEVOLE);
        foreach ($legacyHoursRoles as $role_name) {
            if (in_array($role_name, $hoursRoles, true)) {
                continue;
            }
            $role = get_role($role_name);
            if ($role && $role->has_cap($hoursCapability)) {
                $role->remove_cap($hoursCapability);
            }
        }
    }

    if ($todosCapability !== '') {
        $todoRoles = class_exists(MjRoles::class)
            ? MjRoles::getWordPressTodosRoles()
            : apply_filters('mj_member_todos_capability_roles', array('administrator', MjRoles::ANIMATEUR, MjRoles::COORDINATEUR));
        foreach ($todoRoles as $role_name) {
            $role = get_role($role_name);
            if ($role && $role->has_cap($todosCapability)) {
                $role->remove_cap($todosCapability);
            }
        }

        $legacyTodoRoles = array(MjRoles::BENEVOLE);
        foreach ($legacyTodoRoles as $role_name) {
            if (in_array($role_name, $todoRoles, true)) {
                continue;
            }
            $role = get_role($role_name);
            if ($role && $role->has_cap($todosCapability)) {
                $role->remove_cap($todosCapability);
            }
        }
    }

    if ($documentsCapability !== '') {
        $documentRoles = class_exists(MjRoles::class)
            ? MjRoles::getWordPressDocumentsRoles()
            : apply_filters('mj_member_documents_capability_roles', array('administrator', MjRoles::ANIMATEUR));
        foreach ($documentRoles as $role_name) {
            $role = get_role($role_name);
            if ($role && $role->has_cap($documentsCapability)) {
                $role->remove_cap($documentsCapability);
            }
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

    $capability = Config::capability();
    $contactCapability = Config::contactCapability();

    $allowed = user_can($user, 'manage_options') || user_can($user, $capability) || ($contactCapability !== '' && user_can($user, $contactCapability));
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

    $capability = Config::capability();
    $contactCapability = Config::contactCapability();

    if (user_can($user, 'manage_options') || user_can($user, $capability) || ($contactCapability !== '' && user_can($user, $contactCapability))) {
        return;
    }

    $show_bar = apply_filters('mj_member_show_admin_bar', false, $user);
    if ($show_bar) {
        return;
    }

    show_admin_bar(false);
}
add_action('after_setup_theme', 'mj_member_hide_admin_bar_for_members');
