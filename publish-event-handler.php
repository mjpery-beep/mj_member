<?php
/**
 * Handler for direct event publishing to social media
 * This is a temporary file to add the function before integrating it
 */

if (!defined('ABSPATH')) {
    exit;
}

use Mj\Member\Classes\Crud\MjEvents;
use Mj\Member\Classes\MjSocialMediaPublisher;
use Mj\Member\Core\Config;

/**
 * AJAX handler for publishing an event to social media platforms.
 * 
 * POST params:
 *   - eventId   (int)    – ID of the event to publish
 *   - platform  (string) – 'facebook', 'instagram', or 'whatsapp'
 *   - message   (string) – Custom message/description to publish
 */
function mj_regmgr_publish_event() {
    // Verify request and check nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mj-registration-manager')) {
        wp_send_json_error(array('message' => __('Vérification de sécurité échouée.', 'mj-member')), 403);
        return;
    }

    // Get current user and verify permissions
    $user_id = get_current_user_id();
    if (!$user_id) {
        wp_send_json_error(array('message' => __('Vous devez être connecté.', 'mj-member')), 403);
        return;
    }

    // Check if user has capability to manage events
    if (!current_user_can(Config::capability())) {
        wp_send_json_error(array('message' => __('Permissions insuffisantes.', 'mj-member')), 403);
        return;
    }

    // Get and validate event ID
    $event_id = isset($_POST['eventId']) ? absint($_POST['eventId']) : 0;
    if ($event_id <= 0) {
        wp_send_json_error(array('message' => __('ID événement invalide.', 'mj-member')), 400);
        return;
    }

    // Get platform
    $platform = isset($_POST['platform']) ? sanitize_key($_POST['platform']) : '';
    if (!in_array($platform, array('facebook', 'instagram', 'whatsapp'), true)) {
        wp_send_json_error(array('message' => __('Plateforme invalide.', 'mj-member')), 400);
        return;
    }

    // Get message
    $message = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';
    if (empty($message)) {
        wp_send_json_error(array('message' => __('Le message ne peut pas être vide.', 'mj-member')), 400);
        return;
    }

    // Get event
    $event = MjEvents::find($event_id);
    if (!$event) {
        wp_send_json_error(array('message' => __('Événement introuvable.', 'mj-member')), 404);
        return;
    }

    // Get event share URL
    $event_url = '';
    if (!empty($event->event_page_url)) {
        $event_url = esc_url_raw((string) $event->event_page_url);
    } elseif (!empty($event->front_url)) {
        $event_url = esc_url_raw((string) $event->front_url);
    }

    // Initialize social media publisher
    $publisher = new MjSocialMediaPublisher();

    // Publish to selected platform
    switch ($platform) {
        case 'facebook':
            $result = $publisher->publishToFacebook($message, $event_url);
            break;
        
        case 'instagram':
            $result = $publisher->publishToInstagram($message, $event_url);
            break;
        
        case 'whatsapp':
            // For WhatsApp, we need the group ID
            $whatsapp_group_id = (string) get_option('mj_social_whatsapp_group_id', '');
            if (empty($whatsapp_group_id)) {
                wp_send_json_error(array('message' => __('WhatsApp n\'est pas configuré correctement.', 'mj-member')), 400);
                return;
            }
            
            // Combine message with event URL
            $whatsapp_message = $message;
            if (!empty($event_url)) {
                $whatsapp_message .= "\n\n" . $event_url;
            }
            
            $result = $publisher->publishToWhatsApp($whatsapp_group_id, $whatsapp_message);
            break;
        
        default:
            wp_send_json_error(array('message' => __('Plateforme non supportée.', 'mj-member')), 400);
            return;
    }

    // Handle result
    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()), 400);
        return;
    }

    // Success
    wp_send_json_success(array(
        'message' => $result['message'] ?? __('Publication réussie !', 'mj-member'),
        'platform' => $platform,
        'postId' => $result['postId'] ?? '',
    ));
}

// Register the AJAX handler
add_action('wp_ajax_mj_regmgr_publish_event', 'mj_regmgr_publish_event');
