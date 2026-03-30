<?php

namespace Mj\Member\Classes;

use Mj\Member\Core\Config;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Service Nextcloud – communique via WebDAV + OCS/Direct-Editing.
 *
 * Remplace MjGoogleDrive pour le widget Documents.
 */
final class MjNextcloud
{
    /** @var string */
    private string $baseUrl;

    /** @var string */
    private string $user;

    /** @var string */
    private string $password;

    /** @var string WebDAV endpoint */
    private string $davUrl;

    /**
     * @param string $baseUrl  https://cloud.example.com
     * @param string $user     Nextcloud username
     * @param string $password App-password
     */
    private function __construct(string $baseUrl, string $user, string $password)
    {
        $this->baseUrl  = rtrim($baseUrl, '/');
        $this->user     = $user;
        $this->password = $password;
        $this->davUrl   = $this->baseUrl . '/remote.php/dav/files/' . rawurlencode($this->user);
    }

    /* ------------------------------------------------------------------
     * Factory
     * ----------------------------------------------------------------*/

    /**
     * @return self|WP_Error
     */
    public static function make()
    {
        $url  = Config::nextcloudUrl();
        $user = Config::nextcloudUser();
        $pass = Config::nextcloudPassword();

        if ($url === '' || $user === '' || $pass === '') {
            return new WP_Error(
                'mj_nextcloud_not_configured',
                __('La connexion Nextcloud n\'est pas configurée.', 'mj-member')
            );
        }

        return new self($url, $user, $pass);
    }

    public static function isAvailable(): bool
    {
        return Config::nextcloudUrl() !== ''
            && Config::nextcloudUser() !== ''
            && Config::nextcloudPassword() !== '';
    }

    /**
     * Validate that current credentials are accepted by Nextcloud.
     *
     * @return array{valid:bool,userId:string,message:string,httpCode:int}
     */
    public function validateCurrentCredentials(): array
    {
        $url = $this->baseUrl . '/ocs/v1.php/cloud/user';
        $response = $this->request('GET', $url, [
            'headers' => [
                'OCS-APIREQUEST' => 'true',
                'Accept'         => 'application/xml',
            ],
        ]);

        if (is_wp_error($response)) {
            return [
                'valid'    => false,
                'userId'   => '',
                'message'  => $response->get_error_message(),
                'httpCode' => 0,
            ];
        }

        $httpCode = wp_remote_retrieve_response_code($response);
        $body     = wp_remote_retrieve_body($response);
        $meta     = $this->parseOcsMeta($body);

        if ($httpCode >= 200 && $httpCode < 400 && $meta['statuscode'] === 100) {
            $userId = $this->extractXmlValue($body, 'id');
            if ($userId === '') {
                $userId = $this->extractXmlValue($body, 'userid');
            }

            return [
                'valid'    => true,
                'userId'   => sanitize_user($userId, true),
                'message'  => '',
                'httpCode' => $httpCode,
            ];
        }

        $message = $meta['message'] !== ''
            ? $meta['message']
            : sprintf(__('Authentification Nextcloud invalide (HTTP %d).', 'mj-member'), (int) $httpCode);

        return [
            'valid'    => false,
            'userId'   => '',
            'message'  => $message,
            'httpCode' => (int) $httpCode,
        ];
    }

    /**
     * Build a client with explicit user credentials and the configured server URL.
     *
     * @return self|WP_Error
     */
    public static function makeWithCredentials(string $user, string $password)
    {
        $url      = Config::nextcloudUrl();
        $user     = sanitize_user($user, true);
        $password = trim($password);

        if ($url === '') {
            return new WP_Error(
                'mj_nextcloud_not_configured',
                __('L\'URL Nextcloud n\'est pas configurée.', 'mj-member')
            );
        }

        if ($user === '' || $password === '') {
            return new WP_Error(
                'mj_nextcloud_invalid_credentials',
                __('Identifiant ou mot de passe Nextcloud invalide.', 'mj-member')
            );
        }

        return new self($url, $user, $password);
    }

    /* ------------------------------------------------------------------
     * Users provisioning (OCS)
     * ----------------------------------------------------------------*/

    /**
     * Check whether a Nextcloud user exists.
     */
    public function userExists(string $userId): bool
    {
        $userId = sanitize_user($userId, true);
        if ($userId === '') {
            return false;
        }

        $url = $this->baseUrl . '/ocs/v1.php/cloud/users/' . rawurlencode($userId);
        $response = $this->request('GET', $url, [
            'headers' => ['OCS-APIREQUEST' => 'true'],
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $httpCode = wp_remote_retrieve_response_code($response);
        $body     = wp_remote_retrieve_body($response);
        $meta     = $this->parseOcsMeta($body);

        if ($meta['statuscode'] === 100) {
            return true;
        }

        // Some instances may only expose HTTP-level errors.
        return $httpCode >= 200 && $httpCode < 400;
    }

    /* ------------------------------------------------------------------
     * WebDAV – File operations
     * ----------------------------------------------------------------*/

    /**
     * List the contents of a folder. Creates the folder if it doesn't exist.
     *
     * @param  string   $folderPath Path relative to the admin user root (no leading slash).
     * @return array[]|WP_Error     Array of file/folder descriptors.
     */
    public function listFolder(string $folderPath = '')
    {
        $folderPath = $this->sanitizePath($folderPath);
        $url        = $this->davUrl . ($folderPath !== '' ? '/' . ltrim($folderPath, '/') : '');

        $body = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<d:propfind xmlns:d="DAV:" xmlns:oc="http://owncloud.org/ns">'
            . '<d:prop>'
            . '<d:getlastmodified/><d:getcontentlength/><d:getcontenttype/><d:resourcetype/>'
            . '<oc:fileid/><oc:size/>'
            . '</d:prop>'
            . '</d:propfind>';

        $response = $this->request('PROPFIND', $url, [
            'headers' => [
                'Depth'        => '1',
                'Content-Type' => 'application/xml; charset=utf-8',
            ],
            'body' => $body,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);

        // Folder doesn't exist yet – create it then return empty list.
        if ($code === 404) {
            $created = $this->ensureFolder($folderPath);
            if (is_wp_error($created)) {
                return $created;
            }
            return [];
        }

        if ($code < 200 || $code >= 400) {
            return new WP_Error('mj_nextcloud_list_failed', sprintf(
                __('Nextcloud a répondu HTTP %d.', 'mj-member'),
                $code
            ));
        }

        return $this->parsePropfind(wp_remote_retrieve_body($response), $folderPath);
    }

    /**
     * Ensure that all segments of $path exist, creating intermediate folders as needed.
     *
     * @return true|WP_Error
     */
    public function ensureFolder(string $path)
    {
        $path     = $this->sanitizePath($path);
        $segments = array_filter(explode('/', $path));
        $current  = '';

        foreach ($segments as $segment) {
            $current .= ($current !== '' ? '/' : '') . $segment;
            $url      = $this->davUrl . '/' . $current;

            // Check existence first.
            $check     = $this->request('PROPFIND', $url, [
                'headers' => ['Depth' => '0', 'Content-Type' => 'application/xml; charset=utf-8'],
                'body'    => '<?xml version="1.0"?><d:propfind xmlns:d="DAV:"><d:prop><d:resourcetype/></d:prop></d:propfind>',
            ]);
            $checkCode = is_wp_error($check) ? 0 : wp_remote_retrieve_response_code($check);

            if ($checkCode >= 200 && $checkCode < 400) {
                continue; // Already exists.
            }

            // Create the folder.
            $mkcol     = $this->request('MKCOL', $url);
            $mkolCode  = is_wp_error($mkcol) ? 0 : wp_remote_retrieve_response_code($mkcol);

            // 405 = Method Not Allowed often means it already exists (race condition).
            if (!is_wp_error($mkcol) && ($mkolCode < 400 || $mkolCode === 405)) {
                continue;
            }

            return new WP_Error('mj_nextcloud_folder_failed', sprintf(
                __('Impossible de créer le dossier "%s" (HTTP %d).', 'mj-member'),
                $current,
                $mkolCode
            ));
        }

        return true;
    }

    /**
     * Upload a single file to Nextcloud, creating the parent folder if needed.
     *
     * @param  string          $folderPath  Destination folder (relative to admin root).
     * @param  string          $fileName    Target filename (will be sanitized).
     * @param  string|resource $content     Raw file content.
     * @param  string          $mimeType    MIME type.
     * @return array|WP_Error  File descriptor on success.
     */
    public function uploadFile(string $folderPath, string $fileName, $content, string $mimeType)
    {
        $folderPath = $this->sanitizePath($folderPath);
        $fileName   = $this->sanitizeName($fileName);
        if ($fileName === '') {
            return new WP_Error('mj_nextcloud_upload_invalid', __('Nom de fichier invalide.', 'mj-member'));
        }

        // Ensure parent folder exists.
        $ensure = $this->ensureFolder($folderPath);
        if (is_wp_error($ensure)) {
            return $ensure;
        }

        $filePath = rtrim($folderPath, '/') . '/' . $fileName;
        $url      = $this->davUrl . '/' . ltrim($filePath, '/');

        $response = $this->request('PUT', $url, [
            'headers' => ['Content-Type' => $mimeType],
            'body'    => $content,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code >= 400) {
            return new WP_Error('mj_nextcloud_upload_failed', sprintf(
                __('Échec de l\'envoi du fichier (HTTP %d).', 'mj-member'),
                $code
            ));
        }

        return [
            'path'         => $filePath,
            'name'         => $fileName,
            'type'         => 'file',
            'mimeType'     => $mimeType,
            'size'         => is_string($content) ? strlen($content) : 0,
            'modifiedTime' => current_time('c'),
            'downloadUrl'  => $this->getDownloadUrl($filePath),
        ];
    }

    /**
     * Upload a file from a $_FILES entry.
     *
     * @param  string $folderPath Destination folder.
     * @param  array  $file       Single entry from $_FILES.
     * @return array|WP_Error
     */
    public function uploadFromFiles(string $folderPath, array $file)
    {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return new WP_Error('mj_nextcloud_upload_invalid', __('Fichier uploadé invalide.', 'mj-member'));
        }

        $content = file_get_contents($file['tmp_name']);
        if ($content === false) {
            return new WP_Error('mj_nextcloud_upload_read', __('Impossible de lire le fichier temporaire.', 'mj-member'));
        }

        $mimeType = $file['type'] ?? 'application/octet-stream';
        $fileName = $file['name'] ?? 'fichier';

        return $this->uploadFile($folderPath, $fileName, $content, $mimeType);
    }

    /**
     * Delete a file or folder at the given path.
     *
     * @param  string $itemPath Path relative to admin root.
     * @return true|WP_Error
     */
    public function delete(string $itemPath)
    {
        $itemPath = $this->sanitizePath($itemPath);
        $url      = $this->davUrl . '/' . ltrim($itemPath, '/');

        $response = $this->request('DELETE', $url);
        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code >= 400 && $code !== 404) {
            return new WP_Error('mj_nextcloud_delete_failed', sprintf(
                __('Impossible de supprimer le fichier (HTTP %d).', 'mj-member'),
                $code
            ));
        }

        return true;
    }

    /**
     * Rename (MOVE) a file within the same folder.
     *
     * @param  string $itemPath Current path relative to admin root.
     * @param  string $newName  New filename (basename only).
     * @return array|WP_Error   Updated descriptor on success.
     */
    public function rename(string $itemPath, string $newName)
    {
        $itemPath = $this->sanitizePath($itemPath);
        $newName  = $this->sanitizeName($newName);
        if ($newName === '') {
            return new WP_Error('mj_nextcloud_rename_invalid', __('Nouveau nom invalide.', 'mj-member'));
        }

        $dir     = ltrim(dirname($itemPath), '/');
        $newPath = ($dir !== '' && $dir !== '.') ? $dir . '/' . $newName : $newName;
        $srcUrl  = $this->davUrl . '/' . ltrim($itemPath, '/');
        $dstUrl  = $this->davUrl . '/' . ltrim($newPath, '/');

        $response = $this->request('MOVE', $srcUrl, [
            'headers' => [
                'Destination' => $dstUrl,
                'Overwrite'   => 'F',
            ],
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code >= 400) {
            return new WP_Error('mj_nextcloud_rename_failed', sprintf(
                __('Impossible de renommer le fichier (HTTP %d).', 'mj-member'),
                $code
            ));
        }

        return [
            'path'    => $newPath,
            'name'    => $newName,
            'oldPath' => $itemPath,
        ];
    }

    /**
     * Build a download URL for a file (uses WebDAV endpoint with basic auth embedded – admin only).
     */
    public function getDownloadUrl(string $filePath): string
    {
        if ($this->baseUrl === '') {
            return '';
        }
        $filePath = $this->sanitizePath($filePath);
        // Return an AJAX URL instead of embedding credentials.
        return add_query_arg([
            'action' => 'mj_regmgr_nc_download',
            'path'   => rawurlencode($filePath),
            'nonce'  => wp_create_nonce('mj-registration-manager'),
        ], admin_url('admin-ajax.php'));
    }

    /**
     * Build the Nextcloud web UI file URL (direct link in browser).
     */
    public function getWebUrl(string $filePath): string
    {
        if ($this->baseUrl === '') {
            return '';
        }
        return $this->baseUrl . '/apps/files/?dir=/' . rawurlencode(ltrim(dirname($this->sanitizePath($filePath)), '/'));
    }

    /* ------------------------------------------------------------------
     * Private helpers
     * ----------------------------------------------------------------*/

    /**
     * Send an HTTP request with basic auth to Nextcloud.
     *
     * @param  string $method HTTP verb.
     * @param  string $url    Full URL.
     * @param  array  $args   wp_remote_request args (merged with defaults).
     * @return array|WP_Error
     */
    private function request(string $method, string $url, array $args = [])
    {
        $headers = array_merge(
            [
                'Authorization'  => 'Basic ' . base64_encode($this->user . ':' . $this->password),
                'OCS-APIREQUEST' => 'true',
            ],
            $args['headers'] ?? []
        );

        $requestArgs = array_merge($args, [
            'method'    => strtoupper($method),
            'headers'   => $headers,
            'timeout'   => 30,
            'sslverify' => true,
        ]);

        return wp_remote_request($url, $requestArgs);
    }

    /**
     * Parse a WebDAV PROPFIND multi-status XML response.
     *
     * @param  string $xml      Raw XML body.
     * @param  string $basePath Folder path used in the request (to skip the folder itself).
     * @return array[]
     */
    private function parsePropfind(string $xml, string $basePath = ''): array
    {
        $items    = [];
        $basePath = $this->sanitizePath($basePath);

        if ($xml === '') {
            return $items;
        }

        $prev = libxml_use_internal_errors(true);
        $doc  = simplexml_load_string($xml);
        libxml_use_internal_errors($prev);

        if ($doc === false) {
            return $items;
        }

        $doc->registerXPathNamespace('d',  'DAV:');
        $doc->registerXPathNamespace('oc', 'http://owncloud.org/ns');
        $responses = $doc->xpath('//d:response') ?: [];

        // Admin user's DAV root prefix to strip from hrefs.
        $davPrefix = '/remote.php/dav/files/' . rawurlencode($this->user) . '/';

        foreach ($responses as $resp) {
            $resp->registerXPathNamespace('d', 'DAV:');

            $href         = trim((string) ($resp->xpath('d:href')[0] ?? ''));
            $decodedHref  = rawurldecode($href);
            $relativePath = ltrim(substr($decodedHref, strlen($davPrefix)), '/');

            // Skip the container folder itself.
            if (rtrim($relativePath, '/') === $basePath) {
                continue;
            }

            $propstat = $resp->xpath('d:propstat[d:status[contains(text(),"200")]]')[0] ?? null;
            if (!$propstat) {
                continue;
            }
            $propstat->registerXPathNamespace('d', 'DAV:');

            $resourceType = $propstat->xpath('d:prop/d:resourcetype/d:collection') ?? [];
            $isDir        = count($resourceType) > 0;
            $mimeType     = $isDir
                ? 'httpd/unix-directory'
                : trim((string) ($propstat->xpath('d:prop/d:getcontenttype')[0] ?? 'application/octet-stream'));
            $size         = (int) trim((string) ($propstat->xpath('d:prop/d:getcontentlength')[0] ?? '0'));
            $modified     = trim((string) ($propstat->xpath('d:prop/d:getlastmodified')[0] ?? ''));
            $name         = basename(rtrim($relativePath, '/'));

            $items[] = [
                'path'         => $relativePath,
                'name'         => $name,
                'type'         => $isDir ? 'folder' : 'file',
                'mimeType'     => $mimeType,
                'size'         => $size,
                'modifiedTime' => $modified,
                'downloadUrl'  => $isDir ? '' : $this->getDownloadUrl($relativePath),
            ];
        }

        return $items;
    }

    /**
     * Parse OCS API <meta> block.
     *
     * @return array{statuscode:int,status:string,message:string}
     */
    private function parseOcsMeta(string $xml): array
    {
        $default = ['statuscode' => 0, 'status' => '', 'message' => ''];
        if ($xml === '') {
            return $default;
        }

        $prev = libxml_use_internal_errors(true);
        $doc  = simplexml_load_string($xml);
        libxml_use_internal_errors($prev);

        if ($doc === false) {
            return $default;
        }

        $meta = $doc->meta ?? null;
        if (!$meta) {
            return $default;
        }

        return [
            'statuscode' => (int) ($meta->statuscode ?? 0),
            'status'     => trim((string) ($meta->status ?? '')),
            'message'    => trim((string) ($meta->message ?? '')),
        ];
    }

    /**
     * Extract a simple text value from XML by tag name.
     */
    private function extractXmlValue(string $xml, string $tag): string
    {
        if ($xml === '' || $tag === '') {
            return '';
        }
        if (preg_match('#<' . preg_quote($tag, '#') . '>(.*?)</' . preg_quote($tag, '#') . '>#s', $xml, $m)) {
            return trim(html_entity_decode($m[1], ENT_XML1));
        }
        return '';
    }

    /**
     * Sanitize a folder/file path: normalize slashes, strip relative traversals.
     */
    private function sanitizePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $path = preg_replace('#/+#', '/', $path);
        $path = trim($path, '/');
        // Prevent directory traversal.
        $parts  = explode('/', $path);
        $safe   = [];
        foreach ($parts as $part) {
            if ($part === '..' || $part === '.') {
                continue;
            }
            $safe[] = $part;
        }
        return implode('/', $safe);
    }

    /**
     * Sanitize a single filename.
     */
    private function sanitizeName(string $name): string
    {
        $name = basename($name);
        // Allow unicode letters/digits, spaces, dots, dashes, underscores.
        $name = preg_replace('/[^\p{L}\p{N}\s._\-]/u', '_', $name);
        $name = trim($name);
        return $name;
    }
}
