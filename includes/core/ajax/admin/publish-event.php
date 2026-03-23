<?php
/**
 * Social Media Publishing Handler
 *
 * @package MjMember
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Build webhook payload for n8n publishing mode.
 *
 * @param object $event Event object.
 * @param string $message Message to publish.
 * @param string $event_url Event URL.
 * @param int    $user_id Current user id.
 * @return array<string,mixed>
 */
function mj_regmgr_build_n8n_publish_payload($event, $message, $event_url, $user_id) {
    return array(
        'source' => 'mj-member',
        'action' => 'publish_event',
        'message' => $message,
        'eventUrl' => $event_url,
        'site' => array(
            'name' => (string) get_bloginfo('name'),
            'url' => (string) home_url('/'),
        ),
        'event' => array(
            'id' => isset($event->id) ? (int) $event->id : 0,
            'title' => isset($event->title) ? (string) $event->title : '',
            'description' => isset($event->description) ? (string) $event->description : '',
            'date_start' => isset($event->date_start) ? (string) $event->date_start : '',
            'date_end' => isset($event->date_end) ? (string) $event->date_end : '',
            'event_page_url' => isset($event->event_page_url) ? (string) $event->event_page_url : '',
            'front_url' => isset($event->front_url) ? (string) $event->front_url : '',
            'location' => isset($event->location) ? (string) $event->location : '',
        ),
        'requestedBy' => array(
            'userId' => (int) $user_id,
            'displayName' => (string) wp_get_current_user()->display_name,
        ),
        'requestedAt' => gmdate('c'),
    );
}

/**
 * Publish through n8n webhook using HMAC signature.
 *
 * @param array<string,mixed> $payload Request payload.
 * @return array<string,mixed>|WP_Error
 */
function mj_regmgr_publish_event_via_n8n($payload) {
    $webhook_url = esc_url_raw((string) get_option('mj_social_n8n_webhook_url', ''));
    $secret = (string) get_option('mj_social_n8n_secret', '');

    if ($webhook_url === '') {
        return new WP_Error('n8n_missing_url', __('Le webhook n8n n\'est pas configuré.', 'mj-member'));
    }

    if ($secret === '') {
        return new WP_Error('n8n_missing_secret', __('Le secret n8n est requis pour sécuriser la publication.', 'mj-member'));
    }

    $body = wp_json_encode($payload);
    if (!is_string($body) || $body === '') {
        return new WP_Error('n8n_invalid_payload', __('Impossible de préparer la requête n8n.', 'mj-member'));
    }

    $timestamp = (string) time();
    $signature = hash_hmac('sha256', $timestamp . "\n" . $body, $secret);

    $response = wp_remote_post($webhook_url, array(
        'timeout' => 25,
        'headers' => array(
            'Content-Type' => 'application/json',
            'X-MJ-Timestamp' => $timestamp,
            'X-MJ-Signature' => 'sha256=' . $signature,
        ),
        'body' => $body,
    ));

    if (is_wp_error($response)) {
        return $response;
    }

    $status_code = (int) wp_remote_retrieve_response_code($response);
    $raw_body = (string) wp_remote_retrieve_body($response);
    $json = json_decode($raw_body, true);
    if (!is_array($json)) {
        $json = array();
    }

    if ($status_code < 200 || $status_code >= 300) {
        $error_message = isset($json['message']) && is_string($json['message'])
            ? $json['message']
            : __('Le workflow n8n a retourné une erreur.', 'mj-member');
        return new WP_Error('n8n_http_error', $error_message, array('status' => $status_code));
    }

    $message = isset($json['message']) && is_string($json['message'])
        ? $json['message']
        : __('Publication traitée via n8n.', 'mj-member');
    $post_id = isset($json['postId']) ? (string) $json['postId'] : '';

    return array(
        'message' => $message,
        'postId' => $post_id,
        'raw' => $json,
    );
}

/**
 * AJAX handler for publishing an event through n8n.
 */
function mj_regmgr_publish_event() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mj-registration-manager')) {
        wp_send_json_error(array('message' => __('Vérification de sécurité échouée.', 'mj-member')), 403);
        return;
    }

    $user_id = get_current_user_id();
    if (!$user_id) {
        wp_send_json_error(array('message' => __('Vous devez être connecté.', 'mj-member')), 403);
        return;
    }

    if (!current_user_can(\Mj\Member\Core\Config::capability())) {
        wp_send_json_error(array('message' => __('Permissions insuffisantes.', 'mj-member')), 403);
        return;
    }

    $event_id = isset($_POST['eventId']) ? absint($_POST['eventId']) : 0;
    if ($event_id <= 0) {
        wp_send_json_error(array('message' => __('ID événement invalide.', 'mj-member')), 400);
        return;
    }

    $n8n_enabled = get_option('mj_social_n8n_enabled', '0') === '1';
    if (!$n8n_enabled) {
        wp_send_json_error(array('message' => __('La publication via n8n n\'est pas activée.', 'mj-member')), 400);
        return;
    }

    $message = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';
    if ($message === '') {
        wp_send_json_error(array('message' => __('Le message ne peut pas être vide.', 'mj-member')), 400);
        return;
    }

    $event = \Mj\Member\Classes\Crud\MjEvents::find($event_id);
    if (!$event) {
        wp_send_json_error(array('message' => __('Événement introuvable.', 'mj-member')), 404);
        return;
    }

    $event_url = '';
    if (!empty($event->event_page_url)) {
        $event_url = esc_url_raw((string) $event->event_page_url);
    } elseif (!empty($event->front_url)) {
        $event_url = esc_url_raw((string) $event->front_url);
    }

    $payload = mj_regmgr_build_n8n_publish_payload($event, $message, $event_url, $user_id);
    $result = mj_regmgr_publish_event_via_n8n($payload);

    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()), 400);
        return;
    }

    wp_send_json_success(array(
        'message' => isset($result['message']) ? (string) $result['message'] : __('Publication réussie !', 'mj-member'),
        'postId' => isset($result['postId']) ? (string) $result['postId'] : '',
        'n8n' => true,
    ));
}

add_action('wp_ajax_mj_regmgr_publish_event', 'mj_regmgr_publish_event');
