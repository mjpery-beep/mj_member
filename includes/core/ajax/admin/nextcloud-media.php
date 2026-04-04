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

/**
 * Encode a relative Nextcloud path for WebDAV URL usage.
 */
function mj_nc_encode_dav_path(string $path): string
{
    $path = trim(str_replace('\\', '/', $path), '/');
    if ($path === '') {
        return '';
    }

    $segments = array_values(array_filter(explode('/', $path), static function ($segment) {
        return $segment !== '';
    }));

    $encoded = array_map(static function ($segment) {
        return rawurlencode($segment);
    }, $segments);

    return implode('/', $encoded);
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
    $event = MjEvents::find($eventId);
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

/**
 * Sanitize a relative path fragment (no leading slash, no traversal).
 */
function mj_nc_sanitize_relative_path(string $path): string
{
    $path = wp_unslash($path);
    $path = str_replace('\\', '/', $path);
    $path = preg_replace('#/+#', '/', $path);
    $path = trim((string) $path, '/');

    if ($path === '') {
        return '';
    }

    $segments = array_filter(explode('/', $path), static function ($seg) {
        return $seg !== '' && $seg !== '.' && $seg !== '..';
    });

    $segments = array_map(static function ($seg) {
        return trim(sanitize_text_field((string) $seg));
    }, $segments);

    $segments = array_values(array_filter($segments, static function ($seg) {
        return $seg !== '';
    }));

    return implode('/', $segments);
}

/**
 * Resolve base folder path for a context and media type.
 *
 * @return string|WP_Error
 */
function mj_nc_resolve_base_folder(string $context, int $contextId, string $mediaType)
{
    if (!in_array($context, ['event', 'member'], true) || $contextId <= 0 || !in_array($mediaType, ['photos', 'documents'], true)) {
        return new WP_Error('mj_nc_invalid_params', __('Paramètres invalides.', 'mj-member'));
    }

    return $context === 'event'
        ? mj_nc_event_folder($contextId, $mediaType)
        : mj_nc_member_folder($contextId, $mediaType);
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

    $context   = sanitize_key($_POST['context'] ?? '');    // 'event' | 'member'
    $contextId = (int) ($_POST['context_id'] ?? 0);
    $mediaType = sanitize_key($_POST['media_type'] ?? ''); // 'photos' | 'documents'
    $subPath   = mj_nc_sanitize_relative_path((string) ($_POST['sub_path'] ?? ''));

    $baseFolder = mj_nc_resolve_base_folder($context, $contextId, $mediaType);
    if (is_wp_error($baseFolder)) {
        wp_send_json_error(['message' => $baseFolder->get_error_message()], 400);
        return;
    }

    $folderPath = $baseFolder;
    if ($subPath !== '') {
        $folderPath = rtrim($baseFolder, '/') . '/' . $subPath;
    }

    if (is_wp_error($folderPath)) {
        wp_send_json_error(['message' => $folderPath->get_error_message()], 404);
        return;
    }

    $nc    = MjNextcloud::make();
    if (is_wp_error($nc)) {
        wp_send_json_error(['message' => $nc->get_error_message()], 503);
        return;
    }

    $exists = $nc->folderExists($folderPath);
    if (is_wp_error($exists)) {
        wp_send_json_error(['message' => $exists->get_error_message()], 500);
        return;
    }

    if (!$exists) {
        wp_send_json_success([
            'items'        => [],
            'folderPath'   => $folderPath,
            'folderExists' => false,
        ]);
        return;
    }

    $items = $nc->listFolder($folderPath, false);
    if (is_wp_error($items)) {
        wp_send_json_error(['message' => $items->get_error_message()], 500);
        return;
    }

    wp_send_json_success(['items' => $items, 'folderPath' => $folderPath, 'folderExists' => true]);
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
    $subPath    = mj_nc_sanitize_relative_path((string) ($_POST['sub_path'] ?? ($_POST['sub_folder'] ?? '')));

    $baseFolder = mj_nc_resolve_base_folder($context, $contextId, $mediaType);
    if (is_wp_error($baseFolder)) {
        wp_send_json_error(['message' => $baseFolder->get_error_message()], 400);
        return;
    }

    if (!isset($_FILES['file']) || empty($_FILES['file']['name'])) {
        wp_send_json_error(['message' => __('Aucun fichier reçu.', 'mj-member')], 400);
        return;
    }

    $folderPath = $baseFolder;
    if ($subPath !== '') {
        $folderPath = rtrim($baseFolder, '/') . '/' . $subPath;
    }

    if (is_wp_error($folderPath)) {
        wp_send_json_error(['message' => $folderPath->get_error_message()], 404);
        return;
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
// Create folder
// -----------------------------------------------------------------------

add_action('wp_ajax_mj_regmgr_nc_create_folder', 'mj_regmgr_nc_create_folder');

function mj_regmgr_nc_create_folder()
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
    $subPath    = mj_nc_sanitize_relative_path((string) ($_POST['sub_path'] ?? ''));
    $folderName = trim(sanitize_text_field((string) ($_POST['folder_name'] ?? '')));

    $baseFolder = mj_nc_resolve_base_folder($context, $contextId, $mediaType);
    if (is_wp_error($baseFolder)) {
        wp_send_json_error(['message' => $baseFolder->get_error_message()], 400);
        return;
    }

    $folderPath = $baseFolder;
    if ($subPath !== '') {
        $folderPath = rtrim($baseFolder, '/') . '/' . $subPath;
    }
    if ($folderName !== '') {
        $folderPath = rtrim($folderPath, '/') . '/' . mj_nc_sanitize_relative_path($folderName);
    }

    $nc = MjNextcloud::make();
    if (is_wp_error($nc)) {
        wp_send_json_error(['message' => $nc->get_error_message()], 503);
        return;
    }

    $result = $nc->ensureFolder($folderPath);
    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()], 500);
        return;
    }

    wp_send_json_success(['created' => true, 'folderPath' => $folderPath]);
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
// Move file/folder
// -----------------------------------------------------------------------

add_action('wp_ajax_mj_regmgr_nc_move', 'mj_regmgr_nc_move');

function mj_regmgr_nc_move()
{
    if (!mj_regmgr_verify_request()) {
        return;
    }

    if (!MjNextcloud::isAvailable()) {
        wp_send_json_error(['message' => __('Nextcloud non configuré.', 'mj-member')], 503);
        return;
    }

    $filePath     = mj_nc_sanitize_relative_path((string) ($_POST['file_path'] ?? ''));
    $targetFolder = mj_nc_sanitize_relative_path((string) ($_POST['target_folder'] ?? ''));
    $newName      = trim(sanitize_text_field((string) ($_POST['new_name'] ?? '')));

    if ($filePath === '' || $targetFolder === '') {
        wp_send_json_error(['message' => __('Paramètres manquants.', 'mj-member')], 400);
        return;
    }

    $nc = MjNextcloud::make();
    if (is_wp_error($nc)) {
        wp_send_json_error(['message' => $nc->get_error_message()], 503);
        return;
    }

    $result = $nc->move($filePath, $targetFolder, $newName !== '' ? $newName : null);
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

    $filePathRaw = wp_unslash((string) ($_GET['path'] ?? ''));
    $filePath    = $filePathRaw;
    for ($i = 0; $i < 2; $i++) {
        if (!preg_match('/%[0-9A-Fa-f]{2}/', $filePath)) {
            break;
        }
        $decoded = rawurldecode($filePath);
        if ($decoded === $filePath) {
            break;
        }
        $filePath = $decoded;
    }
    $filePath = mj_nc_sanitize_relative_path($filePath);
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
    $davPath = mj_nc_encode_dav_path($filePath);
    $url     = rtrim(Config::nextcloudUrl(), '/') . '/remote.php/dav/files/'
        . rawurlencode(Config::nextcloudUser()) . '/' . $davPath;
    $tempFile = wp_tempnam($filePath !== '' ? basename($filePath) : 'mj-nextcloud-download');
    if (!$tempFile) {
        wp_die(__('Impossible de créer un fichier temporaire.', 'mj-member'), 500);
        return;
    }

    $options = [
        'headers' => [
            'Authorization' => 'Basic ' . base64_encode(Config::nextcloudUser() . ':' . Config::nextcloudPassword()),
        ],
        'timeout'    => 60,
        'stream'     => true,
        'filename'   => $tempFile,
        'decompress' => false,
    ];
    $response = wp_remote_get($url, $options);

    if (is_wp_error($response)) {
        if (file_exists($tempFile)) {
            @unlink($tempFile);
        }
        wp_die($response->get_error_message(), 500);
        return;
    }

    $code = wp_remote_retrieve_response_code($response);
    if ($code >= 400) {
        if (file_exists($tempFile)) {
            @unlink($tempFile);
        }
        wp_die(sprintf(__('Fichier introuvable (HTTP %d).', 'mj-member'), $code), $code);
        return;
    }

    $mimeTypeHeader = (string) wp_remote_retrieve_header($response, 'content-type');
    $mimeTypeParts  = explode(';', $mimeTypeHeader);
    $mimeType       = trim($mimeTypeParts[0] ?? '');
    if ($mimeType === '') {
        $mimeType = 'application/octet-stream';
    }
    $body     = file_exists($tempFile) ? file_get_contents($tempFile) : false;
    if (file_exists($tempFile)) {
        @unlink($tempFile);
    }
    if ($body === false) {
        wp_die(__('Impossible de lire le fichier temporaire téléchargé.', 'mj-member'), 500);
        return;
    }
    if ($body === '') {
        wp_die(__('Réponse vide reçue depuis Nextcloud.', 'mj-member'), 502);
        return;
    }
    $fileName = basename($filePath);

    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: inline; filename="' . esc_attr($fileName) . '"');
    header('Content-Length: ' . strlen($body));
    header('Cache-Control: private, no-cache');
    echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    exit;
}
