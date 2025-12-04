<?php

use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('mj_member_get_elementor_widgets_map')) {
    function mj_member_get_elementor_widgets_map() {
        return array(
            'Mj_Member_Elementor_Login_Widget' => 'includes/elementor/class-mj-member-login-widget.php',
            'Mj_Member_Elementor_Profile_Widget' => 'includes/elementor/class-mj-member-profile-widget.php',
            'Mj_Member_Elementor_Account_Links_Widget' => 'includes/elementor/class-mj-member-account-links-widget.php',
            'Mj_Member_Elementor_Subscription_Widget' => 'includes/elementor/class-mj-member-subscription-widget.php',
            'Mj_Member_Elementor_Registrations_Widget' => 'includes/elementor/class-mj-member-registrations-widget.php',
            'Mj_Member_Elementor_Events_Widget' => 'includes/elementor/class-mj-member-events-widget.php',
            'Mj_Member_Elementor_Events_Calendar_Widget' => 'includes/elementor/class-mj-member-events-calendar-widget.php',
            'Mj_Member_Elementor_Locations_Widget' => 'includes/elementor/class-mj-member-locations-widget.php',
            'Mj_Member_Elementor_Animateur_Widget' => 'includes/elementor/class-mj-member-animateur-widget.php',
            'Mj_Member_Elementor_Notification_Preferences_Widget' => 'includes/elementor/class-mj-member-notification-preferences-widget.php',
            'Mj_Member_Elementor_Payment_Success_Widget' => 'includes/elementor/class-mj-member-payment-success-widget.php',
            'Mj_Member_Elementor_Contact_Form_Widget' => 'includes/elementor/class-mj-member-contact-form-widget.php',
            'Mj_Member_Elementor_Contact_Messages_Widget' => 'includes/elementor/class-mj-member-contact-messages-widget.php',
        );
    }
}

if (!function_exists('mj_member_load_elementor_widget_class')) {
    function mj_member_load_elementor_widget_class($class_name, $relative_path) {
        if (class_exists($class_name, false)) {
            return true;
        }

        $relative_path = ltrim($relative_path, '/\\');
        $absolute_path = Config::path() . $relative_path;
        if (file_exists($absolute_path)) {
            require_once $absolute_path;
        }

        return class_exists($class_name, false);
    }
}

if (!function_exists('mj_member_load_elementor_widgets')) {
    function mj_member_load_elementor_widgets(array $widgets_map) {
        $loaded = array();
        foreach ($widgets_map as $class_name => $relative_path) {
            $loaded[$class_name] = mj_member_load_elementor_widget_class($class_name, $relative_path);
        }

        return $loaded;
    }
}
