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
use Mj\Member\Classes\MjRoles;
use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Lightweight debug logging for Nextcloud media proxy.
 */
function mj_nc_debug_log(string $message, array $context = []): void
{
    if (!defined('WP_DEBUG_LOG') || !WP_DEBUG_LOG) {
        return;
    }

    if ($context !== []) {
        $encoded = wp_json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $message .= $encoded ? ' ' . $encoded : '';
    }

    error_log('[MJ Nextcloud Proxy] ' . $message);
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
 * Verify nonce and user permissions for Nextcloud media admin AJAX handlers.
 *
 * @return array|false
 */
function mj_nc_verify_request()
{
    if (function_exists('mj_regmgr_verify_request')) {
        return mj_regmgr_verify_request();
    }

    if (!isset($_POST['nonce']) || !wp_verify_nonce((string) $_POST['nonce'], 'mj-registration-manager')) {
        wp_send_json_error(['message' => __('Vérification de sécurité échouée.', 'mj-member')], 403);
        return false;
    }

    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => __('Vous devez être connecté.', 'mj-member')], 401);
        return false;
    }

    $member = MjMembers::getByWpUserId(get_current_user_id());
    if (!$member) {
        wp_send_json_error(['message' => __('Profil membre introuvable.', 'mj-member')], 403);
        return false;
    }

    $memberRole = isset($member->role) ? (string) $member->role : '';
    $allowedRoles = [MjRoles::ANIMATEUR, MjRoles::BENEVOLE, MjRoles::COORDINATEUR];
    if (!in_array($memberRole, $allowedRoles, true) && !current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Permissions insuffisantes.', 'mj-member')], 403);
        return false;
    }

    return [
        'member'          => $member,
        'member_id'       => isset($member->id) ? (int) $member->id : 0,
        'role'            => $memberRole,
        'is_coordinateur' => $memberRole === MjRoles::COORDINATEUR || current_user_can('manage_options'),
    ];
}

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
 * Normalize the requested file path from $_GET.
 */
function mj_nc_get_requested_file_path(): string
{
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

    return mj_nc_sanitize_relative_path($filePath);
}

/**
 * Build the remote WebDAV URL for a given relative path.
 */
function mj_nc_build_remote_file_url(string $filePath): string
{
    $davPath = mj_nc_encode_dav_path($filePath);
    return rtrim(Config::nextcloudUrl(), '/') . '/remote.php/dav/files/'
        . rawurlencode(Config::nextcloudUser()) . '/' . $davPath;
}

/**
 * Build candidate URLs for remote file requests (public URL, then optional internal fallback).
 *
 * @return array<int,string>
 */
function mj_nc_build_remote_file_candidate_urls(string $filePath): array
{
    $primaryUrl = mj_nc_build_remote_file_url($filePath);
    $urls = [$primaryUrl];

    $fallbackBase = '';
    if (defined('MJ_MEMBER_NEXTCLOUD_INTERNAL_URL')) {
        $fallbackBase = (string) constant('MJ_MEMBER_NEXTCLOUD_INTERNAL_URL');
    }

    $publicBase = rtrim(Config::nextcloudUrl(), '/');
    if (function_exists('apply_filters')) {
        $fallbackBase = (string) apply_filters('mj_member_nextcloud_internal_url', $fallbackBase, $publicBase);
    }

    $fallbackBase = rtrim(esc_url_raw(trim((string) $fallbackBase)), '/');
    if ($fallbackBase !== '' && $fallbackBase !== $publicBase && strpos($primaryUrl, $publicBase) === 0) {
        $suffix = substr($primaryUrl, strlen($publicBase));
        $urls[] = $fallbackBase . $suffix;
    }

    return array_values(array_unique(array_filter($urls, static function ($candidate) {
        return is_string($candidate) && $candidate !== '';
    })));
}

/**
 * Detect DNS resolution failures where fallback URL should be attempted.
 */
function mj_nc_is_dns_resolution_error(WP_Error $error): bool
{
    $message = strtolower((string) $error->get_error_message());

    return strpos($message, 'curl error 6') !== false
        || strpos($message, 'could not resolve host') !== false
        || strpos($message, 'getaddrinfo') !== false;
}

/**
 * Allow forcing Streams transport for Nextcloud HTTP calls.
 */
function mj_nc_should_force_streams_transport(): bool
{
    $force = false;
    if (defined('MJ_MEMBER_NEXTCLOUD_FORCE_STREAMS')) {
        $force = (bool) constant('MJ_MEMBER_NEXTCLOUD_FORCE_STREAMS');
    }

    if (function_exists('apply_filters')) {
        $force = (bool) apply_filters('mj_member_nextcloud_force_streams', $force);
    }

    return $force;
}

/**
 * Execute an HTTP request and retry with Streams transport if cURL DNS resolution fails.
 *
 * @return array|WP_Error
 */
function mj_nc_http_request_with_transport_fallback(string $method, string $url, array $args = [])
{
    $requestArgs = array_merge($args, ['method' => strtoupper($method)]);
    if (mj_nc_should_force_streams_transport()) {
        $preferStreams = static function ($transports) {
            if (!is_array($transports) || $transports === []) {
                return $transports;
            }

            $ordered = [];
            if (in_array('streams', $transports, true)) {
                $ordered[] = 'streams';
            }

            foreach ($transports as $transport) {
                if (!in_array($transport, $ordered, true)) {
                    $ordered[] = $transport;
                }
            }

            return $ordered;
        };

        add_filter('http_api_transports', $preferStreams, 999);
        try {
            $streamResponse = wp_remote_request($url, $requestArgs);
        } finally {
            remove_filter('http_api_transports', $preferStreams, 999);
        }

        if (!is_wp_error($streamResponse)) {
            return $streamResponse;
        }
    }

    $response = wp_remote_request($url, $requestArgs);

    if (!is_wp_error($response) || !mj_nc_is_dns_resolution_error($response)) {
        return $response;
    }

    $preferStreams = static function ($transports) {
        if (!is_array($transports) || $transports === []) {
            return $transports;
        }

        $ordered = [];
        if (in_array('streams', $transports, true)) {
            $ordered[] = 'streams';
        }

        foreach ($transports as $transport) {
            if (!in_array($transport, $ordered, true)) {
                $ordered[] = $transport;
            }
        }

        return $ordered;
    };

    add_filter('http_api_transports', $preferStreams, 999);
    try {
        return wp_remote_request($url, $requestArgs);
    } finally {
        remove_filter('http_api_transports', $preferStreams, 999);
    }
}

/**
 * Download a Nextcloud file to a temporary file.
 *
 * @return array{tempFile:string,mimeType:string,httpCode:int}|WP_Error
 */
function mj_nc_fetch_remote_file_to_temp(string $filePath)
{
    $cached = mj_nc_try_get_cached_remote_file($filePath);
    if (is_wp_error($cached)) {
        return $cached;
    }
    if (is_array($cached)) {
        return $cached;
    }

    $candidateUrls = mj_nc_build_remote_file_candidate_urls($filePath);
    $lastError = null;

    foreach ($candidateUrls as $url) {
        $tempFile = wp_tempnam($filePath !== '' ? basename($filePath) : 'mj-nextcloud-download');
        if (!$tempFile) {
            return new WP_Error('mj_nc_temp_failed', __('Impossible de créer un fichier temporaire.', 'mj-member'));
        }

        $response = mj_nc_http_request_with_transport_fallback('GET', $url, [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode(Config::nextcloudUser() . ':' . Config::nextcloudPassword()),
            ],
            'timeout'    => 60,
            'stream'     => true,
            'filename'   => $tempFile,
            'decompress' => false,
        ]);

        if (is_wp_error($response)) {
            if (file_exists($tempFile)) {
                @unlink($tempFile);
            }

            mj_nc_debug_log('Remote request failed', [
                'path' => $filePath,
                'url' => $url,
                'error' => $response->get_error_message(),
            ]);

            $lastError = $response;
            if (!mj_nc_is_dns_resolution_error($response)) {
                return $response;
            }

            continue;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code >= 400) {
            if (file_exists($tempFile)) {
                @unlink($tempFile);
            }

            mj_nc_debug_log('Remote request returned HTTP error', [
                'path' => $filePath,
                'url' => $url,
                'httpCode' => $code,
                'location' => (string) wp_remote_retrieve_header($response, 'location'),
            ]);

            return new WP_Error('mj_nc_remote_http', sprintf(__('Fichier introuvable (HTTP %d).', 'mj-member'), $code), ['status' => $code]);
        }

        $mimeTypeHeader = (string) wp_remote_retrieve_header($response, 'content-type');
        $mimeTypeParts  = explode(';', $mimeTypeHeader);
        $mimeType       = trim($mimeTypeParts[0] ?? '');
        if ($mimeType === '') {
            $mimeType = 'application/octet-stream';
        }

        $fileSize = file_exists($tempFile) ? filesize($tempFile) : false;
        if ($fileSize === false || (int) $fileSize <= 0) {
            if (file_exists($tempFile)) {
                @unlink($tempFile);
            }

            mj_nc_debug_log('Downloaded file is empty', [
                'path' => $filePath,
                'url' => $url,
                'httpCode' => $code,
                'mimeType' => $mimeType,
            ]);

            $lastError = new WP_Error('mj_nc_empty_remote', __('Réponse vide reçue depuis Nextcloud.', 'mj-member'), ['status' => 502]);
            continue;
        }

        mj_nc_store_remote_file_cache($filePath, $tempFile, $mimeType);

        return [
            'tempFile' => $tempFile,
            'mimeType' => $mimeType,
            'httpCode' => $code,
        ];
    }

    if ($lastError instanceof WP_Error) {
        if (mj_nc_is_dns_resolution_error($lastError)) {
            return new WP_Error(
                'mj_nc_dns_unreachable',
                __('Le serveur Nextcloud est temporairement indisponible (résolution DNS). Réessayez dans quelques instants.', 'mj-member'),
                ['status' => 503]
            );
        }

        return $lastError;
    }

    return new WP_Error('mj_nc_remote_unreachable', __('Impossible de joindre Nextcloud.', 'mj-member'), ['status' => 502]);
}

/**
 * Return the directory used to cache generated thumbnails.
 *
 * @return array{dir:string,url:string}|WP_Error
 */
function mj_nc_get_thumbnail_cache_dir()
{
    $uploads = wp_upload_dir();
    if (!empty($uploads['error'])) {
        return new WP_Error('mj_nc_uploads_unavailable', (string) $uploads['error']);
    }

    $dir = trailingslashit($uploads['basedir']) . 'mj-member/nextcloud-thumbnails';
    $url = trailingslashit($uploads['baseurl']) . 'mj-member/nextcloud-thumbnails';

    if (!wp_mkdir_p($dir)) {
        return new WP_Error('mj_nc_thumb_cache_failed', __('Impossible de créer le cache de miniatures.', 'mj-member'));
    }

    return [
        'dir' => $dir,
        'url' => $url,
    ];
}

/**
 * Return the directory used to cache full-size Nextcloud image files.
 *
 * @return string|WP_Error
 */
function mj_nc_get_file_cache_dir()
{
    $uploads = wp_upload_dir();
    if (!empty($uploads['error'])) {
        return new WP_Error('mj_nc_uploads_unavailable', (string) $uploads['error']);
    }

    $dir = trailingslashit($uploads['basedir']) . 'mj-member/nextcloud-file-cache';
    if (!wp_mkdir_p($dir)) {
        return new WP_Error('mj_nc_file_cache_failed', __('Impossible de créer le cache de fichiers Nextcloud.', 'mj-member'));
    }

    return $dir;
}

/**
 * Cache TTL in seconds for full-size image files.
 */
function mj_nc_get_file_cache_ttl(): int
{
    $ttl = 600;
    if (defined('MJ_MEMBER_NEXTCLOUD_FILE_CACHE_TTL')) {
        $ttl = (int) constant('MJ_MEMBER_NEXTCLOUD_FILE_CACHE_TTL');
    }

    if (function_exists('apply_filters')) {
        $ttl = (int) apply_filters('mj_member_nextcloud_file_cache_ttl', $ttl);
    }

    return max(30, min(86400, $ttl));
}

/**
 * Compute cache file path for a remote file path.
 */
function mj_nc_get_file_cache_path(string $filePath): string
{
    $safePath = mj_nc_sanitize_relative_path($filePath);
    $ext = strtolower((string) pathinfo($safePath, PATHINFO_EXTENSION));
    $ext = preg_replace('/[^a-z0-9]+/', '', $ext);
    $suffix = $ext !== '' ? ('.' . $ext) : '.bin';

    return md5($safePath) . $suffix;
}

/**
 * Resolve MIME type from a local file path.
 */
function mj_nc_detect_local_mime_type(string $filePath): string
{
    $mimeType = '';
    if (function_exists('wp_get_image_mime')) {
        $mimeType = (string) wp_get_image_mime($filePath);
    }

    if ($mimeType === '' && function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mimeType = (string) finfo_file($finfo, $filePath);
            finfo_close($finfo);
        }
    }

    return $mimeType !== '' ? $mimeType : 'application/octet-stream';
}

/**
 * Try to hydrate a temp file from local cache.
 *
 * @return array{tempFile:string,mimeType:string,httpCode:int}|null|WP_Error
 */
function mj_nc_try_get_cached_remote_file(string $filePath)
{
    $cacheDir = mj_nc_get_file_cache_dir();
    if (is_wp_error($cacheDir)) {
        return $cacheDir;
    }

    $cacheFile = trailingslashit($cacheDir) . mj_nc_get_file_cache_path($filePath);
    if (!file_exists($cacheFile) || filesize($cacheFile) <= 0) {
        return null;
    }

    $ttl = mj_nc_get_file_cache_ttl();
    $mtime = (int) @filemtime($cacheFile);
    if ($mtime <= 0 || (time() - $mtime) > $ttl) {
        @unlink($cacheFile);
        return null;
    }

    $tempFile = wp_tempnam($filePath !== '' ? basename($filePath) : 'mj-nextcloud-download');
    if (!$tempFile) {
        return new WP_Error('mj_nc_temp_failed', __('Impossible de créer un fichier temporaire.', 'mj-member'));
    }

    if (!@copy($cacheFile, $tempFile) || !file_exists($tempFile) || filesize($tempFile) <= 0) {
        @unlink($tempFile);
        @unlink($cacheFile);
        return null;
    }

    return [
        'tempFile' => $tempFile,
        'mimeType' => mj_nc_detect_local_mime_type($tempFile),
        'httpCode' => 200,
    ];
}

/**
 * Persist downloaded image file to local cache.
 */
function mj_nc_store_remote_file_cache(string $filePath, string $tempFile, string $mimeType): void
{
    if (strpos($mimeType, 'image/') !== 0) {
        return;
    }

    $cacheDir = mj_nc_get_file_cache_dir();
    if (is_wp_error($cacheDir)) {
        return;
    }

    if (!file_exists($tempFile) || filesize($tempFile) <= 0) {
        return;
    }

    $cacheFile = trailingslashit($cacheDir) . mj_nc_get_file_cache_path($filePath);
    @copy($tempFile, $cacheFile);
}

/**
 * Invalidate local cache entry for a remote file path.
 */
function mj_nc_invalidate_remote_file_cache(string $filePath): void
{
    $safePath = mj_nc_sanitize_relative_path($filePath);
    if ($safePath === '') {
        return;
    }

    $cacheDir = mj_nc_get_file_cache_dir();
    if (is_wp_error($cacheDir)) {
        return;
    }

    $hash = md5($safePath);
    $matches = glob(trailingslashit($cacheDir) . $hash . '.*');
    if (!is_array($matches)) {
        return;
    }

    foreach ($matches as $cached) {
        if (is_string($cached) && $cached !== '') {
            @unlink($cached);
        }
    }
}

/**
 * Validate that an uploaded file is an actual image.
 *
 * @param array $file Uploaded file entry from $_FILES.
 */
function mj_nc_uploaded_file_is_image(array $file): bool
{
    $tmpName = isset($file['tmp_name']) ? (string) $file['tmp_name'] : '';
    $name    = isset($file['name']) ? (string) $file['name'] : '';

    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        return false;
    }

    $checkedMime = (string) (wp_check_filetype_and_ext($tmpName, $name)['type'] ?? '');
    if ($checkedMime !== '' && strpos($checkedMime, 'image/') === 0) {
        return true;
    }

    if (function_exists('wp_get_image_mime')) {
        $imageMime = (string) wp_get_image_mime($tmpName);
        if ($imageMime !== '' && strpos($imageMime, 'image/') === 0) {
            return true;
        }
    }

    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $detectedMime = (string) finfo_file($finfo, $tmpName);
            finfo_close($finfo);
            if ($detectedMime !== '' && strpos($detectedMime, 'image/') === 0) {
                return true;
            }
        }
    }

    return false;
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
    if (!mj_nc_verify_request()) {
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
            'folderWebUrl' => $nc->getFolderWebUrl($folderPath),
            'folderExists' => false,
        ]);
        return;
    }

    $items = $nc->listFolder($folderPath, false);
    if (is_wp_error($items)) {
        wp_send_json_error(['message' => $items->get_error_message()], 500);
        return;
    }

    wp_send_json_success([
        'items'        => $items,
        'folderPath'   => $folderPath,
        'folderWebUrl' => $nc->getFolderWebUrl($folderPath),
        'folderExists' => true,
    ]);
}

// -----------------------------------------------------------------------
// Upload file
// -----------------------------------------------------------------------

add_action('wp_ajax_mj_regmgr_nc_upload', 'mj_regmgr_nc_upload');

function mj_regmgr_nc_upload()
{
    if (!mj_nc_verify_request()) {
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

    if ($mediaType === 'photos' && !mj_nc_uploaded_file_is_image($_FILES['file'])) {
        wp_send_json_error(['message' => __('Seules les images sont autorisées dans l’onglet Photos.', 'mj-member')], 400);
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

    if (is_array($result) && !empty($result['path'])) {
        mj_nc_invalidate_remote_file_cache((string) $result['path']);
    }

    wp_send_json_success(['file' => $result]);
}

// -----------------------------------------------------------------------
// Create folder
// -----------------------------------------------------------------------

add_action('wp_ajax_mj_regmgr_nc_create_folder', 'mj_regmgr_nc_create_folder');

function mj_regmgr_nc_create_folder()
{
    if (!mj_nc_verify_request()) {
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
    if (!mj_nc_verify_request()) {
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

    mj_nc_invalidate_remote_file_cache($filePath);

    wp_send_json_success(['deleted' => true]);
}

// -----------------------------------------------------------------------
// Rename file
// -----------------------------------------------------------------------

add_action('wp_ajax_mj_regmgr_nc_rename', 'mj_regmgr_nc_rename');

function mj_regmgr_nc_rename()
{
    if (!mj_nc_verify_request()) {
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

    mj_nc_invalidate_remote_file_cache($filePath);
    if (is_array($result) && !empty($result['path'])) {
        mj_nc_invalidate_remote_file_cache((string) $result['path']);
    }

    wp_send_json_success(['file' => $result]);
}

// -----------------------------------------------------------------------
// Move file/folder
// -----------------------------------------------------------------------

add_action('wp_ajax_mj_regmgr_nc_move', 'mj_regmgr_nc_move');

function mj_regmgr_nc_move()
{
    if (!mj_nc_verify_request()) {
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

    mj_nc_invalidate_remote_file_cache($filePath);
    if (is_array($result) && !empty($result['path'])) {
        mj_nc_invalidate_remote_file_cache((string) $result['path']);
    }

    wp_send_json_success(['file' => $result]);
}

// -----------------------------------------------------------------------
// Download / proxy file
// -----------------------------------------------------------------------

add_action('wp_ajax_mj_regmgr_nc_download', 'mj_regmgr_nc_download');
add_action('wp_ajax_mj_regmgr_nc_thumbnail', 'mj_regmgr_nc_thumbnail');
add_action('wp_ajax_mj_regmgr_nc_public_download', 'mj_regmgr_nc_public_download');
add_action('wp_ajax_nopriv_mj_regmgr_nc_public_download', 'mj_regmgr_nc_public_download');

/**
 * Restrict public signed download URLs to known media roots.
 */
function mj_nc_is_allowed_public_path(string $filePath): bool
{
    $filePath = mj_nc_sanitize_relative_path($filePath);
    if ($filePath === '') {
        return false;
    }

    $root = trim(str_replace('\\', '/', (string) Config::nextcloudRootFolder()), '/');
    $prefix = $root !== '' ? ($root . '/') : '';

    return strpos($filePath, $prefix . 'evenements/') === 0 || strpos($filePath, $prefix . 'membres/') === 0;
}

/**
 * Build a temporary signed public proxy URL for a Nextcloud file.
 */
function mj_nc_build_signed_public_download_url(string $filePath, int $ttlSeconds = 600): string
{
    $safePath = mj_nc_sanitize_relative_path($filePath);
    if ($safePath === '' || !mj_nc_is_allowed_public_path($safePath)) {
        return '';
    }

    $ttl = max(60, min(3600, $ttlSeconds));
    $expires = time() + $ttl;
    $signature = hash_hmac('sha256', $safePath . '|' . $expires, wp_salt('auth'));

    return add_query_arg([
        'action' => 'mj_regmgr_nc_public_download',
        'path' => rawurlencode($safePath),
        'expires' => (string) $expires,
        'sig' => $signature,
    ], admin_url('admin-ajax.php'));
}

/**
 * Validate temporary signed public proxy URL for Nextcloud file.
 */
function mj_nc_validate_public_download_signature(string $filePath, int $expires, string $signature): bool
{
    if ($filePath === '' || $expires <= 0 || $signature === '') {
        return false;
    }

    if ($expires < (time() - 15)) {
        return false;
    }

    if (!mj_nc_is_allowed_public_path($filePath)) {
        return false;
    }

    $expected = hash_hmac('sha256', $filePath . '|' . $expires, wp_salt('auth'));
    return hash_equals($expected, $signature);
}

/**
 * Stream a Nextcloud file through this server.
 */
function mj_regmgr_nc_stream_file(string $filePath, bool $publicCache = false): void
{
    if (!MjNextcloud::isAvailable()) {
        wp_die(__('Nextcloud non configuré.', 'mj-member'), '', ['response' => 503]);
        return;
    }

    $nc = MjNextcloud::make();
    if (is_wp_error($nc)) {
        wp_die($nc->get_error_message(), '', ['response' => 503]);
        return;
    }

    $download = mj_nc_fetch_remote_file_to_temp($filePath);
    if (is_wp_error($download)) {
        $status = (int) ($download->get_error_data()['status'] ?? 500);
        wp_die($download->get_error_message(), '', ['response' => $status > 0 ? $status : 500]);
        return;
    }

    $tempFile = $download['tempFile'];
    $mimeType = $download['mimeType'];
    $code     = $download['httpCode'];
    $fileSize = file_exists($tempFile) ? filesize($tempFile) : false;
    $fileName = basename($filePath);

    mj_nc_debug_log('Streaming proxied file', [
        'path' => $filePath,
        'httpCode' => $code,
        'mimeType' => $mimeType,
        'bytes' => (int) $fileSize,
        'public' => $publicCache,
    ]);

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    status_header(200);
    if ($publicCache) {
        header('Cache-Control: public, max-age=300');
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 300) . ' GMT');
    } else {
        nocache_headers();
        header('Cache-Control: private, no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
    }

    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: inline; filename="' . esc_attr($fileName) . '"');
    header('Content-Length: ' . (string) $fileSize);
    header('X-MJ-NC-Code: ' . (string) $code);
    header('X-MJ-NC-Bytes: ' . (string) $fileSize);

    $streamed = readfile($tempFile);
    if (file_exists($tempFile)) {
        @unlink($tempFile);
    }
    if ($streamed === false) {
        mj_nc_debug_log('readfile failed while streaming response', [
            'path' => $filePath,
            'tempFile' => $tempFile,
            'bytes' => (int) $fileSize,
        ]);
    }
    exit;
}

function mj_regmgr_nc_thumbnail()
{
    if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'mj-registration-manager')) {
        wp_die(__('Accès refusé.', 'mj-member'), '', ['response' => 403]);
        return;
    }

    if (!current_user_can(Config::capability())) {
        wp_die(__('Accès refusé.', 'mj-member'), '', ['response' => 403]);
        return;
    }

    $filePath = mj_nc_get_requested_file_path();
    if ($filePath === '') {
        wp_die(__('Chemin manquant.', 'mj-member'), '', ['response' => 400]);
        return;
    }

    if (!MjNextcloud::isAvailable()) {
        wp_die(__('Nextcloud non configuré.', 'mj-member'), '', ['response' => 503]);
        return;
    }

    $width   = max(64, min(1024, (int) ($_GET['w'] ?? 480)));
    $height  = max(64, min(1024, (int) ($_GET['h'] ?? 480)));
    $version = sanitize_key((string) ($_GET['v'] ?? md5($filePath)));

    $cache = mj_nc_get_thumbnail_cache_dir();
    if (is_wp_error($cache)) {
        wp_die($cache->get_error_message(), '', ['response' => 500]);
        return;
    }

    $cacheKey  = md5($filePath . '|' . $version . '|' . $width . 'x' . $height);
    $cacheFile = trailingslashit($cache['dir']) . $cacheKey . '.jpg';

    if (file_exists($cacheFile) && filesize($cacheFile) > 0) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        status_header(200);
        header('Content-Type: image/jpeg');
        header('Content-Length: ' . (string) filesize($cacheFile));
        header('Cache-Control: private, max-age=86400');
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + DAY_IN_SECONDS) . ' GMT');
        readfile($cacheFile);
        exit;
    }

    $download = mj_nc_fetch_remote_file_to_temp($filePath);
    if (is_wp_error($download)) {
        $status = (int) ($download->get_error_data()['status'] ?? 500);
        wp_die($download->get_error_message(), '', ['response' => $status > 0 ? $status : 500]);
        return;
    }

    require_once ABSPATH . 'wp-admin/includes/image.php';
    $editor = wp_get_image_editor($download['tempFile']);
    if (is_wp_error($editor)) {
        @unlink($download['tempFile']);
        wp_die($editor->get_error_message(), '', ['response' => 500]);
        return;
    }

    $editor->resize($width, $height, true);
    $saved = $editor->save($cacheFile, 'image/jpeg');
    @unlink($download['tempFile']);

    if (is_wp_error($saved) || !file_exists($cacheFile)) {
        $message = is_wp_error($saved) ? $saved->get_error_message() : __('Impossible de générer la miniature.', 'mj-member');
        wp_die($message, '', ['response' => 500]);
        return;
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    status_header(200);
    header('Content-Type: image/jpeg');
    header('Content-Length: ' . (string) filesize($cacheFile));
    header('Cache-Control: private, max-age=86400');
    header('Expires: ' . gmdate('D, d M Y H:i:s', time() + DAY_IN_SECONDS) . ' GMT');
    readfile($cacheFile);
    exit;
}

function mj_regmgr_nc_download()
{
    if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'mj-registration-manager')) {
        wp_die(__('Accès refusé.', 'mj-member'), '', ['response' => 403]);
        return;
    }

    if (!current_user_can(Config::capability())) {
        wp_die(__('Accès refusé.', 'mj-member'), '', ['response' => 403]);
        return;
    }

    $filePath = mj_nc_get_requested_file_path();
    if ($filePath === '') {
        wp_die(__('Chemin manquant.', 'mj-member'), '', ['response' => 400]);
        return;
    }

    mj_regmgr_nc_stream_file($filePath, false);
}

function mj_regmgr_nc_public_download()
{
    $filePath = mj_nc_get_requested_file_path();
    if ($filePath === '') {
        wp_die(__('Chemin manquant.', 'mj-member'), '', ['response' => 400]);
        return;
    }

    $expires = isset($_GET['expires']) ? (int) $_GET['expires'] : 0;
    $signature = isset($_GET['sig']) ? sanitize_text_field((string) $_GET['sig']) : '';

    if (!mj_nc_validate_public_download_signature($filePath, $expires, $signature)) {
        wp_die(__('Signature invalide ou expirée.', 'mj-member'), '', ['response' => 403]);
        return;
    }

    mj_regmgr_nc_stream_file($filePath, true);
}
