<?php 

/*
Plugin Name: MJ Member
Plugin URI: https://mj-pery.be
Description: Gestion centralisée des membres, événements, inscriptions, paiements et communications de la MJ de Pery.
Version: 2.21.0
Requires at least: 6.4
Tested up to: 6.4
Requires PHP: 7.4
Author: Simon
Author URI: https://github.com/mjpery-beep/mj_member
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: mj-member
Domain Path: /languages
Update URI: false

*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/includes/core/Autoloader.php';
require_once __DIR__ . '/includes/core/Config.php';

use Mj\Member\Admin\AdminMenu;
use Mj\Member\Admin\PostNotifyMetaBox;
use Mj\Member\Bootstrap;
use Mj\Member\Core\Autoloader;
use Mj\Member\Core\Config;

Config::bootstrap(__FILE__);

// Charge l'autoloader Composer du plugin ou global s'il est installé (ex: /www/vendor).
if (defined('ABSPATH')) {
    $pluginAutoload = __DIR__ . '/vendor/autoload.php';
    $globalAutoload = trailingslashit(ABSPATH) . 'vendor/autoload.php';

    if (is_readable($pluginAutoload)) {
        require_once $pluginAutoload;
    }

    if (!class_exists('Twig\\Environment') && is_readable($globalAutoload)) {
        require_once $globalAutoload;
    }
}

$basePath = Config::path();

Autoloader::register(
    array(
        'Mj\\Member\\Classes\\Crud\\' => $basePath . 'includes/classes/crud/',
        'Mj\\Member\\Classes\\Sms\\' => $basePath . 'includes/classes/sms/',
        'Mj\\Member\\Classes\\Table\\' => $basePath . 'includes/classes/table/',
        'Mj\\Member\\Classes\\Forms\\' => $basePath . 'includes/classes/forms/',
        'Mj\\Member\\Classes\\Value\\' => $basePath . 'includes/classes/value/',
        'Mj\\Member\\Classes\\' => $basePath . 'includes/classes/',
        'Mj\\Member\\Core\\' => $basePath . 'includes/core/',
        'Mj\\Member\\' => $basePath . 'includes/',
    ),
    array(
        'MjTools' => 'Mj\\Member\\Classes\\MjTools',
        'MjPayments' => 'Mj\\Member\\Classes\\MjPayments',
        'MjSms' => 'Mj\\Member\\Classes\\MjSms',
        'MjSmsTwilio' => 'Mj\\Member\\Classes\\Sms\\MjSmsTwilio',
        'MjMembers' => 'Mj\\Member\\Classes\\Crud\\MjMembers',
        'MjEvents' => 'Mj\\Member\\Classes\\Crud\\MjEvents',
        'MjEventRegistrations' => 'Mj\\Member\\Classes\\Crud\\MjEventRegistrations',
        'MjEventLocations' => 'Mj\\Member\\Classes\\Crud\\MjEventLocations',
        'MjEventClosures' => 'Mj\\Member\\Classes\\Crud\\MjEventClosures',
        'MjEventPhotos' => 'Mj\\Member\\Classes\\Crud\\MjEventPhotos',
        'MjContactMessages' => 'Mj\\Member\\Classes\\Crud\\MjContactMessages',
        'MjEventAttendance' => 'Mj\\Member\\Classes\\Crud\\MjEventAttendance',
        'MjEventAnimateurs' => 'Mj\\Member\\Classes\\Crud\\MjEventAnimateurs',
        'MjEventVolunteers' => 'Mj\\Member\\Classes\\Crud\\MjEventVolunteers',
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
PostNotifyMetaBox::boot();

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


