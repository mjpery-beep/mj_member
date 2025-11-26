<?php 

/*
Plugin Name: MJ Member
Plugin URI: https://mj-pery.be
Description: Gestion des membres avec table CRUD
Version: 2.11.0
Author: Simon
*/
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'MJ_MEMBER_VERSION', '2.11.0' );
define( 'MJ_MEMBER_SCHEMA_VERSION', '2.11.0' );
define( 'MJ_MEMBER_PATH', plugin_dir_path( __FILE__ ) );
define( 'MJ_MEMBER_URL', plugin_dir_url( __FILE__ ) );
define( 'MJ_MEMBER_CAPABILITY', 'mj_manage_members' );

if ( ! defined( 'MJ_MEMBER_PAYMENT_EXPIRATION_DAYS' ) ) {
    define( 'MJ_MEMBER_PAYMENT_EXPIRATION_DAYS', 365 );
}

// Plugin activation hook
require plugin_dir_path( __FILE__ ) . 'includes/classes/MjTools.php';
require plugin_dir_path( __FILE__ ) . 'includes/classes/crud/MjMembers_CRUD.php';
require plugin_dir_path( __FILE__ ) . 'includes/classes/MjPayments.php';
require plugin_dir_path( __FILE__ ) . 'includes/classes/MjSms.php';
require plugin_dir_path( __FILE__ ) . 'includes/classes/sms/MjSmsTwilio.php';
require plugin_dir_path( __FILE__ ) . 'includes/classes/crud/MjEvents_CRUD.php';
require plugin_dir_path( __FILE__ ) . 'includes/classes/crud/MjEventRegistrations.php';
require plugin_dir_path( __FILE__ ) . 'includes/classes/crud/MjEventLocations.php';
require plugin_dir_path( __FILE__ ) . 'includes/classes/crud/MjEventClosures.php';
require plugin_dir_path( __FILE__ ) . 'includes/classes/MjEventSchedule.php';
require plugin_dir_path( __FILE__ ) . 'includes/classes/MjEventGoogleCalendar.php';
require plugin_dir_path( __FILE__ ) . 'includes/classes/crud/MjEventAttendance.php';
require plugin_dir_path( __FILE__ ) . 'includes/classes/MjStripeConfig.php';
require plugin_dir_path( __FILE__ ) . 'includes/security.php'; // SÉCURITÉ
require plugin_dir_path( __FILE__ ) . 'includes/member_accounts.php';
require plugin_dir_path( __FILE__ ) . 'includes/dashboard.php';
require plugin_dir_path( __FILE__ ) . 'includes/events_public.php';
require plugin_dir_path( __FILE__ ) . 'includes/templates/elementor/login_component.php';
require plugin_dir_path( __FILE__ ) . 'includes/shortcode_inscription.php';
require plugin_dir_path( __FILE__ ) . 'includes/templates/elementor/shortcode_member_account.php';
require plugin_dir_path( __FILE__ ) . 'includes/event_closures_admin.php';
require plugin_dir_path( __FILE__ ) . 'includes/templates/elementor/animateur_account.php';
// Mail class and admin pages
require plugin_dir_path( __FILE__ ) . 'includes/classes/MjMail.php';
require plugin_dir_path( __FILE__ ) . 'includes/email_templates.php';
require plugin_dir_path( __FILE__ ) . 'includes/send_emails.php';
require plugin_dir_path( __FILE__ ) . 'includes/settings.php';
require plugin_dir_path( __FILE__ ) . 'includes/import_members.php';
require plugin_dir_path( __FILE__ ) . 'core/capabilities.php';
require plugin_dir_path( __FILE__ ) . 'core/assets.php';
require plugin_dir_path( __FILE__ ) . 'core/schema.php';
require plugin_dir_path( __FILE__ ) . 'core/ajax/admin/members.php';
require plugin_dir_path( __FILE__ ) . 'core/ajax/admin/payments.php';
require plugin_dir_path( __FILE__ ) . 'core/ajax/admin/emails.php';

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

// Ajouter le menu admin
add_action('admin_menu', 'mj_add_admin_menu');
function mj_add_admin_menu() {
    // Top level menu 'Mj Péry'
    add_menu_page(
        'Maison de Jeune',
        'Maison de Jeune',
        MJ_MEMBER_CAPABILITY,
        'mj_member',
        'mj_member_dashboard_page',
        'dashicons-admin-home',
        30
    );

    add_submenu_page(
        'mj_member',
        __('Tableau de bord', 'mj-member'),
        __('Tableau de bord', 'mj-member'),
        MJ_MEMBER_CAPABILITY,
        'mj_member_dashboard',
        'mj_member_dashboard_page'
    );

    remove_submenu_page('mj_member', 'mj_member');

    $members_hook = add_submenu_page('mj_member', 'Membres', 'Membres', MJ_MEMBER_CAPABILITY, 'mj_members', 'mj_members_page');
    add_action('load-' . $members_hook, 'mj_member_handle_members_actions');

    // Submenu: Événements / Stages
    add_submenu_page('mj_member', 'Événements', 'Événements', MJ_MEMBER_CAPABILITY, 'mj_events', 'mj_events_page');

    // Submenu: Lieux d'événements
    $locations_hook = add_submenu_page('mj_member', 'Lieux', 'Lieux', MJ_MEMBER_CAPABILITY, 'mj_locations', 'mj_event_locations_page');
    add_action('load-' . $locations_hook, 'mj_member_handle_locations_actions');

    // Submenu: Fermetures MJ
    add_submenu_page(
        'mj_member',
        __('Fermetures MJ', 'mj-member'),
        __('Fermetures MJ', 'mj-member'),
        MJ_MEMBER_CAPABILITY,
        'mj_closures',
        'mj_event_closures_page'
    );

    // Submenu: Template emails
    add_submenu_page('mj_member', 'Template emails', 'Template emails', MJ_MEMBER_CAPABILITY, 'mj_email_templates', 'mj_email_templates_page');

    // Submenu: Envoye email
    add_submenu_page('mj_member', 'Envoye email', 'Envoye email', MJ_MEMBER_CAPABILITY, 'mj_send_emails', 'mj_send_emails_page');

    // Submenu: Configuration
    add_submenu_page('mj_member', 'Configuration', 'Configuration', 'manage_options', 'mj_settings', 'mj_settings_page');

    add_submenu_page('mj_member', 'Import CSV membres', 'Import CSV', MJ_MEMBER_CAPABILITY, 'mj_member_import', 'mj_member_import_members_page');
}

// Page admin
function mj_members_page() {
    ?>
    <div class="wrap">
        <h1>Gestion des Membres</h1>
        <?php
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        
        if ($action === 'add' || $action === 'edit') {
            require plugin_dir_path(__FILE__) . 'includes/forms/form_member.php';
        } else {
            require plugin_dir_path(__FILE__) . 'includes/table_members.php';
        }
        ?>
    </div>
    <?php
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

function mj_member_handle_locations_actions() {
    if (!current_user_can(MJ_MEMBER_CAPABILITY)) {
        return;
    }

    $action = isset($_REQUEST['action']) ? sanitize_key(wp_unslash($_REQUEST['action'])) : 'list';
    $location_id = isset($_REQUEST['location']) ? (int) $_REQUEST['location'] : 0;

    $state = array();

    if ($action === 'delete') {
        if ($location_id <= 0) {
            $state['errors'][]    = 'Identifiant de lieu invalide.';
            $state['force_action'] = 'list';
        } else {
            $nonce_action = 'mj_location_delete_' . $location_id;
            $nonce_value  = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
            if (!$nonce_value || !wp_verify_nonce($nonce_value, $nonce_action)) {
                $state['errors'][] = 'Verification de securite echouee.';
                $state['force_action'] = 'list';
            } else {
                $delete = MjEventLocations::delete($location_id);
                if (is_wp_error($delete)) {
                    $state['errors'][] = $delete->get_error_message();
                    $state['force_action'] = 'list';
                } else {
                    $redirect = add_query_arg(
                        array(
                            'page' => 'mj_locations',
                            'mj_locations_message' => rawurlencode('Lieu supprime.'),
                        ),
                        admin_url('admin.php')
                    );
                    wp_safe_redirect($redirect);
                    exit;
                }
            }
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mj_location_nonce'])) {
        $nonce_value = sanitize_text_field(wp_unslash($_POST['mj_location_nonce']));
        if (!wp_verify_nonce($nonce_value, 'mj_location_save')) {
            $state['form_errors'][] = 'Verification de securite echouee.';
        } else {
            $submitted = array(
                'name'         => isset($_POST['location_name']) ? sanitize_text_field(wp_unslash($_POST['location_name'])) : '',
                'slug'         => isset($_POST['location_slug']) ? sanitize_title(wp_unslash($_POST['location_slug'])) : '',
                'address_line' => isset($_POST['location_address']) ? sanitize_text_field(wp_unslash($_POST['location_address'])) : '',
                'postal_code'  => isset($_POST['location_postal_code']) ? sanitize_text_field(wp_unslash($_POST['location_postal_code'])) : '',
                'city'         => isset($_POST['location_city']) ? sanitize_text_field(wp_unslash($_POST['location_city'])) : '',
                'country'      => isset($_POST['location_country']) ? sanitize_text_field(wp_unslash($_POST['location_country'])) : '',
                'latitude'     => isset($_POST['location_latitude']) ? sanitize_text_field(wp_unslash($_POST['location_latitude'])) : '',
                'longitude'    => isset($_POST['location_longitude']) ? sanitize_text_field(wp_unslash($_POST['location_longitude'])) : '',
                'map_query'    => isset($_POST['location_map_query']) ? sanitize_text_field(wp_unslash($_POST['location_map_query'])) : '',
                'notes'        => isset($_POST['location_notes']) ? sanitize_textarea_field(wp_unslash($_POST['location_notes'])) : '',
            );

            $cover_id = isset($_POST['location_cover_id']) ? (int) $_POST['location_cover_id'] : 0;
            $submitted['cover_id'] = $cover_id;

            if ($submitted['name'] === '') {
                $state['form_errors'][] = 'Le nom du lieu est obligatoire.';
            }

            $form_action    = $action === 'edit' ? 'edit' : 'add';
            $target_location = $form_action === 'edit' ? (int) $location_id : 0;

            if ($form_action === 'edit' && $target_location <= 0) {
                $state['form_errors'][] = 'Edition impossible: identifiant invalide.';
            }

            if (empty($state['form_errors'])) {
                if ($form_action === 'edit' && $target_location > 0) {
                    $result = MjEventLocations::update($target_location, $submitted);
                    $success_message = 'Lieu mis a jour.';
                } else {
                    $result = MjEventLocations::create($submitted);
                    $success_message = 'Lieu cree avec succes.';
                }

                if (is_wp_error($result)) {
                    $state['form_errors'][] = $result->get_error_message();
                } else {
                    $redirect = add_query_arg(
                        array(
                            'page' => 'mj_locations',
                            'mj_locations_message' => rawurlencode($success_message),
                        ),
                        admin_url('admin.php')
                    );
                    wp_safe_redirect($redirect);
                    exit;
                }
            }

            if (!empty($state['form_errors'])) {
                $state['force_action'] = $form_action;
                $state['location_id']  = $target_location;
                $state['form_values']  = array_merge(MjEventLocations::get_default_values(), $submitted);
                $state['form_values']['cover_id'] = $submitted['cover_id'];
                $state['map_embed_url'] = MjEventLocations::build_map_embed_src($state['form_values']);
            }
        }
    }

    if (!empty($state)) {
        $GLOBALS['mj_member_locations_form_state'] = $state;
    } else {
        unset($GLOBALS['mj_member_locations_form_state']);
    }
}

function mj_events_page() {
    ?>
    <div class="wrap">
        <h1>Gestion des événements &amp; stages</h1>
        <?php
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';

        if ($action === 'add' || $action === 'edit') {
            require plugin_dir_path(__FILE__) . 'includes/forms/form_event.php';
        } else {
            require plugin_dir_path(__FILE__) . 'includes/table_events.php';
        }
        ?>
    </div>
    <?php
}

function mj_event_locations_page() {
    ?>
    <div class="wrap">
        <h1>Gestion des lieux</h1>
        <?php require plugin_dir_path(__FILE__) . 'includes/locations_page.php'; ?>
    </div>
    <?php
}

function mj_member_handle_members_actions() {
    if (!current_user_can(MJ_MEMBER_CAPABILITY)) {
        return;
    }

    $primary_action = isset($_REQUEST['action']) ? sanitize_key(wp_unslash($_REQUEST['action'])) : '';
    $secondary_action = isset($_REQUEST['action2']) ? sanitize_key(wp_unslash($_REQUEST['action2'])) : '';
    $action = $primary_action ?: $secondary_action;

    if ($action !== 'delete') {
        return;
    }

    $member_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    $nonce_value = isset($_GET['nonce']) ? sanitize_text_field(wp_unslash($_GET['nonce'])) : '';

    if ($member_id <= 0 || !$nonce_value || !wp_verify_nonce($nonce_value, 'mj_delete_nonce')) {
        return;
    }

    MjMembers_CRUD::delete($member_id);

    $redirect = add_query_arg(
        array(
            'page' => 'mj_members',
            'mj_member_notice' => 'deleted',
        ),
        admin_url('admin.php')
    );
    wp_safe_redirect($redirect);
    exit;
}