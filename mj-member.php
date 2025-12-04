<?php 

/*
Plugin Name: MJ Member
Plugin URI: https://mj-pery.be
Description: Gestion des membres avec table CRUD
Version: 2.14.0
Author: Simon
*/
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/includes/core/Autoloader.php';
require_once __DIR__ . '/includes/core/Config.php';

use Mj\Member\Admin\AdminMenu;
use Mj\Member\Bootstrap;
use Mj\Member\Core\Autoloader;
use Mj\Member\Core\Config;

Config::bootstrap(__FILE__);

$basePath = Config::path();

Autoloader::register(
    array(
        'Mj\\Member\\Classes\\Crud\\' => $basePath . 'includes/classes/crud/',
        'Mj\\Member\\Classes\\Sms\\' => $basePath . 'includes/classes/sms/',
        'Mj\\Member\\Classes\\Table\\' => $basePath . 'includes/classes/table/',
        'Mj\\Member\\Classes\\' => $basePath . 'includes/classes/',
        'Mj\\Member\\Core\\' => $basePath . 'includes/core/',
        'Mj\\Member\\' => $basePath . 'includes/',
    ),
    array(
        'MjTools' => 'Mj\\Member\\Classes\\MjTools',
        'MjPayments' => 'Mj\\Member\\Classes\\MjPayments',
        'MjSms' => 'Mj\\Member\\Classes\\MjSms',
        'MjSmsTwilio' => 'Mj\\Member\\Classes\\Sms\\MjSmsTwilio',
        'MjMembers_CRUD' => 'Mj\\Member\\Classes\\Crud\\MjMembers_CRUD',
        'MjEvents_CRUD' => 'Mj\\Member\\Classes\\Crud\\MjEvents_CRUD',
        'MjEventRegistrations' => 'Mj\\Member\\Classes\\Crud\\MjEventRegistrations',
        'MjEventLocations' => 'Mj\\Member\\Classes\\Crud\\MjEventLocations',
        'MjEventClosures' => 'Mj\\Member\\Classes\\Crud\\MjEventClosures',
        'MjEventPhotos' => 'Mj\\Member\\Classes\\Crud\\MjEventPhotos',
        'MjContactMessages' => 'Mj\\Member\\Classes\\Crud\\MjContactMessages',
        'MjEventAttendance' => 'Mj\\Member\\Classes\\Crud\\MjEventAttendance',
        'MjEventAnimateurs' => 'Mj\\Member\\Classes\\Crud\\MjEventAnimateurs',
        'MjEventSchedule' => 'Mj\\Member\\Classes\\MjEventSchedule',
        'MjEventGoogleCalendar' => 'Mj\\Member\\Classes\\MjEventGoogleCalendar',
        'MjStripeConfig' => 'Mj\\Member\\Classes\\MjStripeConfig',
        'MjMemberBusinessCards' => 'Mj\\Member\\Classes\\MjMemberBusinessCards',
        'MjMail' => 'Mj\\Member\\Classes\\MjMail',
        'MjList_Table' => 'Mj\\Member\\Classes\\Table\\MjList_Table',
        'MjEmailLogs_List_Table' => 'Mj\\Member\\Classes\\Table\\MjEmailLogs_List_Table',
        'MjEvents_List_Table' => 'Mj\\Member\\Classes\\Table\\MjEvents_List_Table',
        'MjMembers_List_Table' => 'Mj\\Member\\Classes\\Table\\MjMembers_List_Table',
    )
);

Bootstrap::init();
AdminMenu::boot();

add_action('init', 'mj_member_load_textdomain');
function mj_member_load_textdomain() {
    load_plugin_textdomain('mj-member', false, dirname(plugin_basename(__FILE__)) . '/languages/');
}

if (class_exists('MjEventGoogleCalendar')) {
    MjEventGoogleCalendar::bootstrap();
}

// Hook d'activation du plugin
register_activation_hook(__FILE__, 'mj_install');

// SÉCURITÉ: Protéger les clés Stripe de l'accès public via l'API REST
add_filter('rest_pre_dispatch', 'mj_protect_stripe_keys', 10, 3);
function mj_protect_stripe_keys($result, $server, $request) {
    // Bloquer l'accès à l'endpoint options si on essaie d'accéder aux clés Stripe
    if (strpos($request->get_route(), '/wp/v2/settings') !== false) {
        $params = $request->get_json_params();
        if (isset($params['mj_stripe_secret_key']) || isset($params['mj_stripe_secret_key_encrypted'])) {
            return new WP_Error('forbidden', 'Accès refusé', array('status' => 403));
        }
    }
    return $result;
}

// Hook de désactivation du plugin
register_deactivation_hook(__FILE__, 'mj_uninstall');

/**
 * Enable debug display for administrators only.
 * This won't change WP_DEBUG constants (they are defined in wp-config.php),
 * but will enable PHP error display and reporting for users with manage_options.
 */
add_action('init', 'mj_enable_admin_debug', 1);
function mj_enable_admin_debug() {
    // Only attempt when WP user system is available
    if (!function_exists('is_user_logged_in')) return;

    if (is_user_logged_in() && current_user_can('manage_options')) {
        @ini_set('display_errors', 1);
        @ini_set('display_startup_errors', 1);
        error_reporting(E_ALL);

        // If WP_DEBUG_LOG isn't enabled we still ensure there's a log file in wp-content
        if (!defined('WP_DEBUG_LOG') || (defined('WP_DEBUG_LOG') && !WP_DEBUG_LOG)) {
            @ini_set('error_log', WP_CONTENT_DIR . '/debug.log');
        }

        // Show an admin notice so it's clear that debug is on for this user
        add_action('admin_notices', function() {
            echo '<div class="notice notice-info is-dismissible"><p><strong>Mode debug activé :</strong> affichage des erreurs activé pour l\'administrateur courant.</p></div>';
        });
    }
}

// Handle payment confirmation via GET token
add_action('init', 'mj_handle_payment_confirmation');
function mj_handle_payment_confirmation() {
    if (!empty($_GET['mj_payment_confirm'])) {
        $token = sanitize_text_field($_GET['mj_payment_confirm']);
        require_once plugin_dir_path(__FILE__) . 'includes/classes/MjPayments.php';
        $ok = MjPayments::confirm_payment_by_token($token);
        // Simple feedback page
        if ($ok) {
            wp_redirect(add_query_arg('mj_payment_status', 'ok', remove_query_arg('mj_payment_confirm')));
            exit;
        } else {
            wp_redirect(add_query_arg('mj_payment_status', 'error', remove_query_arg('mj_payment_confirm')));
            exit;
        }
    }
}

