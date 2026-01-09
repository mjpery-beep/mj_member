<?php

use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

$visibility_trait = Config::path() . 'includes/elementor/trait-mj-member-widget-visibility.php';
if (file_exists($visibility_trait)) {
    require_once $visibility_trait;
}

if (!function_exists('mj_member_get_elementor_widgets_map')) {
    function mj_member_get_elementor_widgets_map() {
        return array(
            'Mj_Member_Elementor_Login_Widget' => 'includes/elementor/class-mj-member-login-widget.php',
            'Mj_Member_Elementor_Profile_Widget' => 'includes/elementor/class-mj-member-profile-widget.php',
            'Mj_Member_Elementor_Account_Links_Widget' => 'includes/elementor/class-mj-member-account-links-widget.php',
            'Mj_Member_Elementor_Account_Menu_Widget' => 'includes/elementor/class-mj-member-account-menu-widget.php',
            'Mj_Member_Elementor_Subscription_Widget' => 'includes/elementor/class-mj-member-subscription-widget.php',
            'Mj_Member_Elementor_Registration_Widget' => 'includes/elementor/class-mj-member-registration-widget.php',
            'Mj_Member_Elementor_Registrations_Widget' => 'includes/elementor/class-mj-member-registrations-widget.php',
            'Mj_Member_Elementor_Event_Photos_Widget' => 'includes/elementor/class-mj-member-event-photos-widget.php',
            'Mj_Member_Elementor_Events_Widget' => 'includes/elementor/class-mj-member-events-widget.php',
            'Mj_Member_Elementor_Events_Calendar_Widget' => 'includes/elementor/class-mj-member-events-calendar-widget.php',
            'Mj_Member_Elementor_Upcoming_Events_Widget' => 'includes/elementor/class-mj-member-upcoming-events-widget.php',
            'Mj_Member_Elementor_Payments_Widget' => 'includes/elementor/class-mj-member-payments-widget.php',
            'Mj_Member_Elementor_Hour_Encode_Widget' => 'includes/elementor/class-mj-member-hour-encode-widget.php',
            'Mj_Member_Elementor_Locations_Widget' => 'includes/elementor/class-mj-member-locations-widget.php',
            'Mj_Member_Elementor_Animateur_Widget' => 'includes/elementor/class-mj-member-animateur-widget.php',
            'Mj_Member_Elementor_Notification_Preferences_Widget' => 'includes/elementor/class-mj-member-notification-preferences-widget.php',
            'Mj_Member_Elementor_Payment_Success_Widget' => 'includes/elementor/class-mj-member-payment-success-widget.php',
            'Mj_Member_Elementor_Contact_Form_Widget' => 'includes/elementor/class-mj-member-contact-form-widget.php',
            'Mj_Member_Elementor_Contact_Messages_Widget' => 'includes/elementor/class-mj-member-contact-messages-widget.php',
            'Mj_Member_Elementor_Photo_Grimlins_Widget' => 'includes/elementor/class-mj-member-photo-grimlins-widget.php',
            'Mj_Member_Elementor_Grimlins_Gallery_Widget' => 'includes/elementor/class-mj-member-grimlins-gallery-widget.php',
            'Mj_Member_Elementor_Grim_Gif_Widget' => 'includes/elementor/class-mj-member-grim-gif-widget.php',
            'Mj_Member_Elementor_Todo_Widget' => 'includes/elementor/class-mj-member-todo-widget.php',
            'Mj_Member_Elementor_Documents_Widget' => 'includes/elementor/class-mj-member-documents-widget.php',
            'Mj_Member_Elementor_Idea_Box_Widget' => 'includes/elementor/class-mj-member-idea-box-widget.php',
            'Mj\Member\Elementor\EventsManager' => 'includes/elementor/EventsManager.php',
            'Mj_Member_Elementor_Registration_Manager_Widget' => 'includes/elementor/class-mj-member-registration-manager-widget.php',
            'Mj_Member_Elementor_Event_Schedule_Widget' => 'includes/elementor/class-mj-member-event-schedule-widget.php',
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

// Register custom Elementor category
add_action('elementor/elements/categories_registered', function($elements_manager) {
    $elements_manager->add_category(
        'mj-member',
        [
            'title' => __('Maison de Jeunes', 'mj-member'),
            'icon' => 'eicon-site-logo',
        ]
    );
});
