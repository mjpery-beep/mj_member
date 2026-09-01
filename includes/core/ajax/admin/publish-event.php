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
 * Save a social publication record to the database.
 *
 * @param int    $event_id    Event ID.
 * @param string $platform    Platform slug (facebook|instagram|whatsapp).
 * @param string $message     Published message.
 * @param string $status      'success' or 'error'.
 * @param string $post_id     API-returned post/message ID.
 * @param array  $api_response Raw API response data.
 * @param int    $user_id     WP user who triggered the publish.
 * @return int|false Inserted row ID or false on failure.
 */
function mj_regmgr_save_social_publication($event_id, $platform, $message, $status, $post_id, $api_response, $user_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'mj_social_publications';

    $result = $wpdb->insert(
        $table,
        array(
            'event_id'     => (int) $event_id,
            'platform'     => sanitize_key($platform),
            'message'      => (string) $message,
            'status'       => in_array($status, array('success', 'error'), true) ? $status : 'error',
            'post_id'      => $post_id !== '' ? (string) $post_id : null,
            'api_response' => wp_json_encode($api_response),
            'published_by' => (int) $user_id,
        ),
        array('%d', '%s', '%s', '%s', '%s', '%s', '%d')
    );

    return $result !== false ? (int) $wpdb->insert_id : false;
}

/**
 * Parse media items sent by the publish tab.
 *
 * @return array<int,array<string,mixed>>
 */
function mj_regmgr_parse_publish_media_items() {
    $raw = isset($_POST['mediaItems']) ? wp_unslash($_POST['mediaItems']) : null;
    $items = array();

    if (is_string($raw) && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $items = $decoded;
        }
    } elseif (is_array($raw)) {
        $items = $raw;
    }

    if (!is_array($items)) {
        return array();
    }

    $normalized = array();
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $url = isset($item['url']) ? esc_url_raw((string) $item['url']) : '';
        if ($url === '' || !wp_http_validate_url($url)) {
            continue;
        }

        $mime_type = isset($item['mimeType']) ? sanitize_text_field((string) $item['mimeType']) : '';
        $is_image = (isset($item['isImage']) && ($item['isImage'] === true || $item['isImage'] === '1' || $item['isImage'] === 1));
            $path = isset($item['path']) ? trim(str_replace('\\', '/', sanitize_text_field((string) $item['path'])), '/') : '';
            if (!$is_image && $mime_type !== '' && strpos($mime_type, 'image/') === 0) {
            $is_image = true;
        }

        $normalized[] = array(
            'source' => isset($item['source']) ? sanitize_key((string) $item['source']) : '',
            'sourceLabel' => isset($item['sourceLabel']) ? sanitize_text_field((string) $item['sourceLabel']) : '',
            'name' => isset($item['name']) ? sanitize_text_field((string) $item['name']) : '',
            'mimeType' => $mime_type,
            'isImage' => $is_image,
            'path' => $path,
            'url' => $url,
        );
    }

    return array_slice($normalized, 0, 20);
}

/**
 * @param array<int,array<string,mixed>> $media_items
 * @return array<int,string>
 */
function mj_regmgr_collect_publish_media_urls($media_items) {
    $urls = array();
    if (!is_array($media_items)) {
        return $urls;
    }

    foreach ($media_items as $item) {
        if (!is_array($item) || empty($item['url']) || !is_string($item['url'])) {
            continue;
        }
        $url = esc_url_raw($item['url']);
        if ($url !== '' && wp_http_validate_url($url) && !in_array($url, $urls, true)) {
            $urls[] = $url;
        }
    }

    return $urls;
}

/**
 * @param array<int,array<string,mixed>> $media_items
 * @return array<int,string>
 */
function mj_regmgr_collect_publish_image_media_urls($media_items) {
    $urls = array();
    if (!is_array($media_items)) {
        return $urls;
    }

    foreach ($media_items as $item) {
        if (!is_array($item) || empty($item['url']) || !is_string($item['url'])) {
            continue;
        }

        $is_image = !empty($item['isImage']) || (isset($item['mimeType']) && is_string($item['mimeType']) && strpos($item['mimeType'], 'image/') === 0);
        if (!$is_image) {
            continue;
        }

        $url = esc_url_raw($item['url']);
        if ($url !== '' && wp_http_validate_url($url) && !in_array($url, $urls, true)) {
            $urls[] = $url;
        }
    }

    return $urls;
}

/**
 * @param array<int,array<string,mixed>> $media_items
 * @return array<int,string>
 */
function mj_regmgr_collect_publish_non_image_media_urls($media_items) {
    $urls = array();
    if (!is_array($media_items)) {
        return $urls;
    }

    foreach ($media_items as $item) {
        if (!is_array($item) || empty($item['url']) || !is_string($item['url'])) {
            continue;
        }

        $is_image = !empty($item['isImage']) || (isset($item['mimeType']) && is_string($item['mimeType']) && strpos($item['mimeType'], 'image/') === 0);
        if ($is_image) {
            continue;
        }

        $url = esc_url_raw($item['url']);
        if ($url !== '' && wp_http_validate_url($url) && !in_array($url, $urls, true)) {
            $urls[] = $url;
        }
    }

    return $urls;
}

/**
 * Resolve media URL for social APIs.
 * For Nextcloud photos, generate a temporary signed public proxy URL so Meta can fetch the binary.
 *
 * @param array<string,mixed> $item
 * @return string
 */
function mj_regmgr_resolve_publish_media_item_url($item) {
    if (!is_array($item)) {
        return '';
    }

    $url = (isset($item['url']) && is_string($item['url'])) ? esc_url_raw($item['url']) : '';
    if ($url !== '' && !wp_http_validate_url($url)) {
        $url = '';
    }

    $source = isset($item['source']) ? sanitize_key((string) $item['source']) : '';
    if ($source !== 'nextcloud_photo') {
        return $url;
    }

    $path = isset($item['path']) ? trim(str_replace('\\', '/', sanitize_text_field((string) $item['path'])), '/') : '';
    if ($path !== '' && function_exists('mj_nc_build_signed_public_download_url')) {
        $signed = mj_nc_build_signed_public_download_url($path, 900);
        if (is_string($signed) && $signed !== '' && wp_http_validate_url($signed)) {
            return $signed;
        }
    }

    return $url;
}

/**
 * Build a final message body with optional event link and selected media links.
 *
 * @param string            $message Base message.
 * @param array<int,string> $media_urls Selected media URLs.
 * @param string            $event_url Event URL.
 * @return string
 */
function mj_regmgr_build_publish_message_with_links($message, $media_urls, $event_url = '') {
    $parts = array();
    $message = trim((string) $message);
    if ($message !== '') {
        $parts[] = $message;
    }

    $event_url = trim((string) $event_url);
    if ($event_url !== '') {
        $parts[] = $event_url;
    }

    if (is_array($media_urls) && !empty($media_urls)) {
        $unique_media = array();
        foreach ($media_urls as $url) {
            $candidate = trim((string) $url);
            if ($candidate === '' || in_array($candidate, $unique_media, true)) {
                continue;
            }
            $unique_media[] = $candidate;
        }
        if (!empty($unique_media)) {
            $parts[] = implode("\n", $unique_media);
        }
    }

    return trim(implode("\n\n", $parts));
}

/**
 * Pick first image URL from selected media items.
 *
 * @param array<int,array<string,mixed>> $media_items
 * @param string                         $fallback
 * @return string
 */
function mj_regmgr_pick_instagram_image_url($media_items, $fallback = '') {
    if (is_array($media_items)) {
        foreach ($media_items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $is_image = !empty($item['isImage']) || (isset($item['mimeType']) && is_string($item['mimeType']) && strpos($item['mimeType'], 'image/') === 0);
            if (!$is_image) {
                continue;
            }
            $url = mj_regmgr_resolve_publish_media_item_url($item);
            if ($url !== '' && wp_http_validate_url($url)) {
                return $url;
            }
        }
    }

    $fallback = esc_url_raw((string) $fallback);
    return ($fallback !== '' && wp_http_validate_url($fallback)) ? $fallback : '';
}

/**
 * AJAX handler for publishing an event directly to Facebook, Instagram, or WhatsApp.
 *
 * POST params:
 *   eventId    (int)    – ID of the event.
 *   platform   (string) – 'facebook', 'instagram', or 'whatsapp'.
 *   message    (string) – Message/caption to publish.
 *   imageUrl   (string) – Optional image URL for Instagram.
 *   mediaItems (array)  – Optional selected media objects from the UI.
 */
function mj_regmgr_publish_event_direct() {
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

    $platform = isset($_POST['platform']) ? sanitize_key($_POST['platform']) : '';
    if (!in_array($platform, array('facebook', 'instagram', 'whatsapp'), true)) {
        wp_send_json_error(array('message' => __('Plateforme invalide.', 'mj-member')), 400);
        return;
    }

    $message = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';
    if ($message === '') {
        wp_send_json_error(array('message' => __('Le message ne peut pas être vide.', 'mj-member')), 400);
        return;
    }

    $image_url = isset($_POST['imageUrl']) ? esc_url_raw(wp_unslash($_POST['imageUrl'])) : '';
    $media_items = mj_regmgr_parse_publish_media_items();
    $media_urls = mj_regmgr_collect_publish_media_urls($media_items);
    $image_media_urls = array();
    $non_image_media_urls = array();
    if (is_array($media_items)) {
        foreach ($media_items as $media_item) {
            if (!is_array($media_item)) {
                continue;
            }

            $candidate_url = mj_regmgr_resolve_publish_media_item_url($media_item);
            if ($candidate_url === '' || !wp_http_validate_url($candidate_url)) {
                continue;
            }

            $is_image = !empty($media_item['isImage']) || (isset($media_item['mimeType']) && is_string($media_item['mimeType']) && strpos($media_item['mimeType'], 'image/') === 0);

            if ($is_image) {
                if (!in_array($candidate_url, $image_media_urls, true)) {
                    $image_media_urls[] = $candidate_url;
                }
            } else {
                if (!in_array($candidate_url, $non_image_media_urls, true)) {
                    $non_image_media_urls[] = $candidate_url;
                }
            }
        }
    }
    $selected_image_url = mj_regmgr_pick_instagram_image_url($media_items, $image_url);

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
    if ($event_url === '') {
        $event_url = isset($_POST['eventUrl']) ? esc_url_raw(wp_unslash((string) $_POST['eventUrl'])) : '';
        if ($event_url !== '' && !wp_http_validate_url($event_url)) {
            $event_url = '';
        }
    }

    $logs = array();
    $publisher = new \Mj\Member\Classes\MjSocialMediaPublisher();

    $logs[] = array(
        'type' => 'info',
        'text' => sprintf(__('Début de la publication sur %s…', 'mj-member'), ucfirst($platform)),
        'time' => gmdate('H:i:s'),
    );

    if (!empty($media_urls)) {
        $logs[] = array(
            'type' => 'info',
            'text' => sprintf(__('%d média(s) sélectionné(s) seront ajoutés à la publication.', 'mj-member'), count($media_urls)),
            'time' => gmdate('H:i:s'),
        );
    }

    $published_message = $message;

    switch ($platform) {
        case 'facebook':
            $logs[] = array(
                'type' => 'info',
                'text' => sprintf(__('Envoi vers l\'API Graph Facebook (page %s)…', 'mj-member'), get_option('mj_social_facebook_page_id', '?')),
                'time' => gmdate('H:i:s'),
            );
            $published_message = mj_regmgr_build_publish_message_with_links($message, $non_image_media_urls);
            $result = $publisher->publishToFacebook($published_message, $event_url, $image_media_urls);
            break;

        case 'instagram':
            $logs[] = array(
                'type' => 'info',
                'text' => sprintf(__('Envoi vers l\'API Instagram Business (compte %s)…', 'mj-member'), get_option('mj_social_instagram_business_id', '?')),
                'time' => gmdate('H:i:s'),
            );
            $published_message = mj_regmgr_build_publish_message_with_links($message, $media_urls);
            $result = $publisher->publishToInstagram($published_message, $event_url, $selected_image_url);
            break;

        case 'whatsapp':
            $group_url = (string) get_option('mj_social_whatsapp_group_url', '');
            $group_id  = (string) get_option('mj_social_whatsapp_phone_number_id', '');
            $logs[] = array(
                'type' => 'info',
                'text' => sprintf(__('Envoi via l\'API WhatsApp Business (Phone Number ID : %s)…', 'mj-member'), $group_id !== '' ? $group_id : '?'),
                'time' => gmdate('H:i:s'),
            );
            $published_message = mj_regmgr_build_publish_message_with_links($message, $media_urls, $event_url);
            $result = $publisher->publishToWhatsApp($group_id, $published_message);
            break;

        default:
            wp_send_json_error(array('message' => __('Plateforme non gérée.', 'mj-member')), 400);
            return;
    }

    if (is_wp_error($result)) {
        $error_msg     = $result->get_error_message();
        $error_data    = $result->get_error_data();
        $token_expired = is_array($error_data) && !empty($error_data['tokenExpired']);
        $perm_error    = is_array($error_data) && !empty($error_data['permError']);

        $logs[] = array(
            'type' => 'error',
            'text' => sprintf(__('Erreur API : %s', 'mj-member'), $error_msg),
            'time' => gmdate('H:i:s'),
        );
        if (is_array($error_data) && isset($error_data['status'])) {
            $logs[] = array(
                'type' => 'error',
                'text' => sprintf(__('Code HTTP retourné : %d', 'mj-member'), (int) $error_data['status']),
                'time' => gmdate('H:i:s'),
            );
        }
        if ($token_expired) {
            $logs[] = array(
                'type' => 'warn',
                'text' => __('→ Renouvelez le token dans Paramètres → Publier sur les réseaux.', 'mj-member'),
                'time' => gmdate('H:i:s'),
            );
        }
        if ($perm_error) {
            $logs[] = array(
                'type' => 'warn',
                'text' => __('→ Utilisez un Page Access Token (pas un User Token) avec pages_read_engagement + pages_manage_posts. Générez-le via Graph API Explorer.', 'mj-member'),
                'time' => gmdate('H:i:s'),
            );
        }

        mj_regmgr_save_social_publication(
            $event_id,
            $platform,
            $published_message,
            'error',
            '',
            array('error' => $error_msg, 'data' => $error_data),
            $user_id
        );

        wp_send_json_error(array(
            'message'      => $error_msg,
            'logs'         => $logs,
            'tokenExpired' => $token_expired,
            'permError'    => $perm_error,
        ), 200);
        return;
    }

    $post_id     = isset($result['postId']) ? (string) $result['postId'] : '';
    $success_msg = isset($result['message']) ? (string) $result['message'] : __('Publication réussie !', 'mj-member');

    $logs[] = array(
        'type' => 'success',
        'text' => $success_msg . ($post_id !== '' ? sprintf(' (ID : %s)', $post_id) : ''),
        'time' => gmdate('H:i:s'),
    );

    $saved_id = mj_regmgr_save_social_publication(
        $event_id,
        $platform,
        $published_message,
        'success',
        $post_id,
        $result,
        $user_id
    );

    if ($saved_id !== false) {
        $logs[] = array(
            'type' => 'info',
            'text' => sprintf(__('Publication enregistrée dans l\'historique (entrée #%d).', 'mj-member'), $saved_id),
            'time' => gmdate('H:i:s'),
        );
    }

    wp_send_json_success(array(
        'message'    => $success_msg,
        'postId'     => $post_id,
        'platform'   => $platform,
        'logs'       => $logs,
        'mediaCount' => count($media_urls),
        'savedId'    => $saved_id !== false ? $saved_id : null,
    ));
}

add_action('wp_ajax_mj_regmgr_publish_event_direct', 'mj_regmgr_publish_event_direct');

/**
 * AJAX handler to retrieve social publications for a given event.
 *
 * POST params:
 *   eventId (int) – ID of the event.
 */
function mj_regmgr_get_social_publications() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mj-registration-manager')) {
        wp_send_json_error(array('message' => __('Vérification de sécurité échouée.', 'mj-member')), 403);
        return;
    }

    if (!get_current_user_id()) {
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

    global $wpdb;
    $table = $wpdb->prefix . 'mj_social_publications';

    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT id, platform, message, status, post_id, api_response, published_by, published_at
         FROM {$table}
         WHERE event_id = %d
         ORDER BY published_at DESC
         LIMIT 50",
        $event_id
    ), ARRAY_A);

    if (!is_array($rows)) {
        $rows = array();
    }

    $publications = array();
    foreach ($rows as $row) {
        $user_name = '';
        if (!empty($row['published_by'])) {
            $u = get_userdata((int) $row['published_by']);
            $user_name = $u ? $u->display_name : '';
        }
        $publications[] = array(
            'id'           => (int) $row['id'],
            'platform'     => (string) $row['platform'],
            'message'      => (string) $row['message'],
            'status'       => (string) $row['status'],
            'postId'       => (string) $row['post_id'],
            'apiResponse'  => $row['api_response'] ? json_decode($row['api_response'], true) : null,
            'publishedBy'  => $user_name,
            'publishedAt'  => (string) $row['published_at'],
        );
    }

    wp_send_json_success(array('publications' => $publications));
}

add_action('wp_ajax_mj_regmgr_get_social_publications', 'mj_regmgr_get_social_publications');
