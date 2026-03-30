<?php
/**
 * AJAX handlers – Nextcloud media (photos & documents) for the Registration Manager.
 *
 * Paths:
 *   Events  : {rootFolder}/evenements/{event-slug}/{photos|documents}/
 *   Members : {rootFolder}/membres/{member-login}/{photos|documents}/
 *
 * @package MjMember
 */

use Mj\Member\Classes\MjNextcloud;
use Mj\Member\Classes\Crud\MjEvents;
use Mj\Member\Classes\Crud\MjMembers;
use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

// -----------------------------------------------------------------------
// Helpers
// -----------------------------------------------------------------------

/**
 * Build the Nextcloud folder path for an event + media type.
 *
 * @param  int    $eventId
 * @param  string $type  'photos' | 'documents'
 * @return string|WP_Error
 */
function mj_nc_event_folder(int $eventId, string $type)
{
    $event = MjEvents::findById($eventId);
    if (!$event) {
        return new WP_Error('mj_nc_not_found', __('Événement introuvable.', 'mj-member'));
    }

    $slug = sanitize_title((string) ($event->slug ?? $event->title ?? 'evenement-' . $eventId));
    if ($slug === '') {
        $slug = 'evenement-' . $eventId;
    }

    $root = rtrim(Config::nextcloudRootFolder(), '/');
    return ($root !== '' ? $root . '/' : '') . 'evenements/' . $slug . '/' . $type;
}

/**
 * Build the Nextcloud folder path for a member + media type.
 *
 * @param  int    $memberId
 * @param  string $type  'photos' | 'documents'
 * @return string|WP_Error
 */
function mj_nc_member_folder(int $memberId, string $type)
{
    $member = MjMembers::getById($memberId);
    if (!$member) {
        return new WP_Error('mj_nc_not_found', __('Membre introuvable.', 'mj-member'));
    }

    // Prefer WP user_login, fall back to display name or ID.
    $login = '';
    if (!empty($member->member_account_login)) {
        $login = sanitize_user((string) $member->member_account_login, true);
    }
    if ($login === '' && !empty($member->member_account_id)) {
        $wpUser = get_userdata((int) $member->member_account_id);
        if ($wpUser) {
            $login = sanitize_user($wpUser->user_login, true);
        }
    }
    if ($login === '') {
        $firstName = sanitize_title((string) ($member->first_name ?? ''));
        $lastName  = sanitize_title((string) ($member->last_name ?? ''));
        $login     = trim($firstName . '-' . $lastName, '-') ?: ('membre-' . $memberId);
    }

    $root = rtrim(Config::nextcloudRootFolder(), '/');
    return ($root !== '' ? $root . '/' : '') . 'membres/' . $login . '/' . $type;
}

// -----------------------------------------------------------------------
// List folder
// -----------------------------------------------------------------------

add_action('wp_ajax_mj_regmgr_nc_list', 'mj_regmgr_nc_list');

function mj_regmgr_nc_list()
{
    if (!mj_regmgr_verify_request()) {
        return;
    }

    if (!MjNextcloud::isAvailable()) {
        wp_send_json_error(['message' => __('Nextcloud non configuré.', 'mj-member')], 503);
        return;
    }

    $context   = sanitize_key($_POST['context'] ?? '');   // 'event' | 'member'
    $contextId = (int) ($_POST['context_id'] ?? 0);
    $mediaType = sanitize_key($_POST['media_type'] ?? ''); // 'photos' | 'documents'

    if (!in_array($context, ['event', 'member'], true) || $contextId <= 0 || !in_array($mediaType, ['photos', 'documents'], true)) {
        wp_send_json_error(['message' => __('Paramètres invalides.', 'mj-member')], 400);
        return;
    }

    $folderPath = $context === 'event'
        ? mj_nc_event_folder($contextId, $mediaType)
        : mj_nc_member_folder($contextId, $mediaType);

    if (is_wp_error($folderPath)) {
        wp_send_json_error(['message' => $folderPath->get_error_message()], 404);
        return;
    }

    $nc    = MjNextcloud::make();
    if (is_wp_error($nc)) {
        wp_send_json_error(['message' => $nc->get_error_message()], 503);
        return;
    }

    $items = $nc->listFolder($folderPath);
    if (is_wp_error($items)) {
        wp_send_json_error(['message' => $items->get_error_message()], 500);
        return;
    }

    wp_send_json_success(['items' => $items, 'folderPath' => $folderPath]);
}

// -----------------------------------------------------------------------
// Upload file
// -----------------------------------------------------------------------

add_action('wp_ajax_mj_regmgr_nc_upload', 'mj_regmgr_nc_upload');

function mj_regmgr_nc_upload()
{
    if (!mj_regmgr_verify_request()) {
        return;
    }

    if (!MjNextcloud::isAvailable()) {
        wp_send_json_error(['message' => __('Nextcloud non configuré.', 'mj-member')], 503);
        return;
    }

    $context    = sanitize_key($_POST['context'] ?? '');
    $contextId  = (int) ($_POST['context_id'] ?? 0);
    $mediaType  = sanitize_key($_POST['media_type'] ?? '');
    $subFolder  = sanitize_text_field($_POST['sub_folder'] ?? ''); // optional sub-folder

    if (!in_array($context, ['event', 'member'], true) || $contextId <= 0 || !in_array($mediaType, ['photos', 'documents'], true)) {
        wp_send_json_error(['message' => __('Paramètres invalides.', 'mj-member')], 400);
        return;
    }

    if (!isset($_FILES['file']) || empty($_FILES['file']['name'])) {
        wp_send_json_error(['message' => __('Aucun fichier reçu.', 'mj-member')], 400);
        return;
    }

    $folderPath = $context === 'event'
        ? mj_nc_event_folder($contextId, $mediaType)
        : mj_nc_member_folder($contextId, $mediaType);

    if (is_wp_error($folderPath)) {
        wp_send_json_error(['message' => $folderPath->get_error_message()], 404);
        return;
    }

    // Append optional sub-folder.
    if ($subFolder !== '') {
        $folderPath = rtrim($folderPath, '/') . '/' . sanitize_title($subFolder);
    }

    $nc = MjNextcloud::make();
    if (is_wp_error($nc)) {
        wp_send_json_error(['message' => $nc->get_error_message()], 503);
        return;
    }

    $result = $nc->uploadFromFiles($folderPath, $_FILES['file']);
    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()], 500);
        return;
    }

    wp_send_json_success(['file' => $result]);
}

// -----------------------------------------------------------------------
// Delete file
// -----------------------------------------------------------------------

add_action('wp_ajax_mj_regmgr_nc_delete', 'mj_regmgr_nc_delete');

function mj_regmgr_nc_delete()
{
    if (!mj_regmgr_verify_request()) {
        return;
    }

    if (!MjNextcloud::isAvailable()) {
        wp_send_json_error(['message' => __('Nextcloud non configuré.', 'mj-member')], 503);
        return;
    }

    $filePath = sanitize_text_field($_POST['file_path'] ?? '');
    if ($filePath === '') {
        wp_send_json_error(['message' => __('Chemin du fichier manquant.', 'mj-member')], 400);
        return;
    }

    $nc = MjNextcloud::make();
    if (is_wp_error($nc)) {
        wp_send_json_error(['message' => $nc->get_error_message()], 503);
        return;
    }

    $result = $nc->delete($filePath);
    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()], 500);
        return;
    }

    wp_send_json_success(['deleted' => true]);
}

// -----------------------------------------------------------------------
// Rename file
// -----------------------------------------------------------------------

add_action('wp_ajax_mj_regmgr_nc_rename', 'mj_regmgr_nc_rename');

function mj_regmgr_nc_rename()
{
    if (!mj_regmgr_verify_request()) {
        return;
    }

    if (!MjNextcloud::isAvailable()) {
        wp_send_json_error(['message' => __('Nextcloud non configuré.', 'mj-member')], 503);
        return;
    }

    $filePath = sanitize_text_field($_POST['file_path'] ?? '');
    $newName  = sanitize_text_field($_POST['new_name'] ?? '');

    if ($filePath === '' || $newName === '') {
        wp_send_json_error(['message' => __('Paramètres manquants.', 'mj-member')], 400);
        return;
    }

    $nc = MjNextcloud::make();
    if (is_wp_error($nc)) {
        wp_send_json_error(['message' => $nc->get_error_message()], 503);
        return;
    }

    $result = $nc->rename($filePath, $newName);
    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()], 500);
        return;
    }

    wp_send_json_success(['file' => $result]);
}

// -----------------------------------------------------------------------
// Download / proxy file
// -----------------------------------------------------------------------

add_action('wp_ajax_mj_regmgr_nc_download', 'mj_regmgr_nc_download');

function mj_regmgr_nc_download()
{
    if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'mj-registration-manager')) {
        wp_die(__('Accès refusé.', 'mj-member'), 403);
        return;
    }

    if (!current_user_can(Config::capability())) {
        wp_die(__('Accès refusé.', 'mj-member'), 403);
        return;
    }

    $filePath = sanitize_text_field(rawurldecode($_GET['path'] ?? ''));
    if ($filePath === '') {
        wp_die(__('Chemin manquant.', 'mj-member'), 400);
        return;
    }

    if (!MjNextcloud::isAvailable()) {
        wp_die(__('Nextcloud non configuré.', 'mj-member'), 503);
        return;
    }

    $nc = MjNextcloud::make();
    if (is_wp_error($nc)) {
        wp_die($nc->get_error_message(), 503);
        return;
    }

    // Proxy the file through WordPress to avoid exposing credentials.
    $url     = rtrim(Config::nextcloudUrl(), '/') . '/remote.php/dav/files/'
        . rawurlencode(Config::nextcloudUser()) . '/' . ltrim($filePath, '/');
    $options = [
        'headers' => [
            'Authorization' => 'Basic ' . base64_encode(Config::nextcloudUser() . ':' . Config::nextcloudPassword()),
        ],
        'timeout' => 60,
    ];
    $response = wp_remote_get($url, $options);

    if (is_wp_error($response)) {
        wp_die($response->get_error_message(), 500);
        return;
    }

    $code = wp_remote_retrieve_response_code($response);
    if ($code >= 400) {
        wp_die(sprintf(__('Fichier introuvable (HTTP %d).', 'mj-member'), $code), $code);
        return;
    }

    $mimeType = wp_remote_retrieve_header($response, 'content-type') ?: 'application/octet-stream';
    $body     = wp_remote_retrieve_body($response);
    $fileName = basename($filePath);

    header('Content-Type: ' . sanitize_mime_type($mimeType));
    header('Content-Disposition: inline; filename="' . esc_attr($fileName) . '"');
    header('Content-Length: ' . strlen($body));
    header('Cache-Control: private, no-cache');
    echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    exit;
}
