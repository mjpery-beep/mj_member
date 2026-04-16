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

        // OCS meta was not parseable – fall back to HTTP status code only.
        if ($meta['statuscode'] === 0) {
            return $httpCode >= 200 && $httpCode < 400;
        }

        return false;
    }

    /**
     * Create a new Nextcloud user account.
     *
     * @param  string $userId      Nextcloud username.
     * @param  string $password    Initial password.
     * @param  string $displayName Display name (optional).
     * @param  string $email       Email address (optional).
     * @return true|WP_Error
     */
    public function createUser(string $userId, string $password, string $displayName = '', string $email = '')
    {
        $userId = sanitize_user($userId, true);
        if ($userId === '' || $password === '') {
            return new WP_Error('mj_nextcloud_create_user_invalid', __('Identifiant ou mot de passe invalide.', 'mj-member'));
        }

        $url = $this->baseUrl . '/ocs/v1.php/cloud/users';

        $body = http_build_query([
            'userid'      => $userId,
            'password'    => $password,
            'displayName' => $displayName,
            'email'       => $email,
        ]);

        $response = $this->request('POST', $url, [
            'headers' => [
                'Content-Type'   => 'application/x-www-form-urlencoded',
                'OCS-APIREQUEST' => 'true',
            ],
            'body' => $body,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $httpCode = wp_remote_retrieve_response_code($response);
        $meta     = $this->parseOcsMeta(wp_remote_retrieve_body($response));

        if ($meta['statuscode'] === 100) {
            return true;
        }

        $message = $meta['message'] !== ''
            ? $meta['message']
            : sprintf(__('Impossible de créer l\'utilisateur Nextcloud (HTTP %d, OCS %d).', 'mj-member'), (int) $httpCode, (int) $meta['statuscode']);

        return new WP_Error('mj_nextcloud_create_user_failed', $message);
    }

    /**
     * Update the password of an existing Nextcloud user.
     *
     * @param  string $userId   Nextcloud username.
     * @param  string $password New password.
     * @return true|WP_Error
     */
    public function setUserPassword(string $userId, string $password)
    {
        $userId = sanitize_user($userId, true);
        if ($userId === '' || $password === '') {
            return new WP_Error('mj_nextcloud_set_password_invalid', __('Identifiant ou mot de passe invalide.', 'mj-member'));
        }

        $url = $this->baseUrl . '/ocs/v1.php/cloud/users/' . rawurlencode($userId);

        $body = http_build_query([
            'key'   => 'password',
            'value' => $password,
        ]);

        $response = $this->request('PUT', $url, [
            'headers' => [
                'Content-Type'   => 'application/x-www-form-urlencoded',
                'OCS-APIREQUEST' => 'true',
            ],
            'body' => $body,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $httpCode = wp_remote_retrieve_response_code($response);
        $meta     = $this->parseOcsMeta(wp_remote_retrieve_body($response));

        if ($meta['statuscode'] === 100) {
            return true;
        }

        $message = $meta['message'] !== ''
            ? $meta['message']
            : sprintf(__('Impossible de mettre à jour le mot de passe Nextcloud (HTTP %d, OCS %d).', 'mj-member'), (int) $httpCode, (int) $meta['statuscode']);

        return new WP_Error('mj_nextcloud_set_password_failed', $message);
    }

    /**
     * Add a Nextcloud user to a group.
     *
     * @param  string $userId  Nextcloud username.
     * @param  string $groupId Group ID.
     * @return true|WP_Error
     */
    public function addUserToGroup(string $userId, string $groupId)
    {
        $userId  = sanitize_user($userId, true);
        $groupId = sanitize_text_field($groupId);
        if ($userId === '' || $groupId === '') {
            return new WP_Error('mj_nextcloud_group_invalid', __('Identifiant utilisateur ou groupe invalide.', 'mj-member'));
        }

        $url = $this->baseUrl . '/ocs/v1.php/cloud/users/' . rawurlencode($userId) . '/groups';

        $body = http_build_query(['groupid' => $groupId]);

        $response = $this->request('POST', $url, [
            'headers' => [
                'Content-Type'   => 'application/x-www-form-urlencoded',
                'OCS-APIREQUEST' => 'true',
            ],
            'body' => $body,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $httpCode = wp_remote_retrieve_response_code($response);
        $meta     = $this->parseOcsMeta(wp_remote_retrieve_body($response));

        if ($meta['statuscode'] === 100) {
            return true;
        }

        $message = $meta['message'] !== ''
            ? $meta['message']
            : sprintf(__('Impossible d\'ajouter l\'utilisateur au groupe "%s" (HTTP %d, OCS %d).', 'mj-member'), $groupId, (int) $httpCode, (int) $meta['statuscode']);

        return new WP_Error('mj_nextcloud_add_group_failed', $message);
    }

    /**
     * Set a user's avatar via the WebDAV avatars endpoint.
     *
     * @param  string $userId  Nextcloud username.
     * @param  string $content Raw image binary content.
     * @param  string $mime    MIME type (e.g. "image/jpeg").
     * @return true|WP_Error
     */
    public function setUserAvatar(string $userId, string $content, string $mime)
    {
        $userId = sanitize_user($userId, true);
        if ($userId === '' || $content === '') {
            return new WP_Error('mj_nextcloud_avatar_invalid', __('Données avatar invalides.', 'mj-member'));
        }

        $ext = $mime === 'image/png' ? 'png' : 'jpg';
        $url = $this->baseUrl . '/remote.php/dav/avatars/' . rawurlencode($userId) . '/avatar.' . $ext;

        $response = $this->request('PUT', $url, [
            'headers' => ['Content-Type' => $mime],
            'body'    => $content,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code >= 200 && $code < 300) {
            return true;
        }

        return new WP_Error(
            'mj_nextcloud_avatar_failed',
            sprintf(__('Impossible de définir l\'avatar Nextcloud (HTTP %d).', 'mj-member'), (int) $code)
        );
    }

    /**
     * Upload raw content to a folder (alias for uploadFile with binary content).
     *
     * @param  string $folderPath Destination folder.
     * @param  string $fileName   File name.
     * @param  string $content    Raw file content.
     * @param  string $mimeType   MIME type.
     * @return array|WP_Error
     */
    public function uploadContent(string $folderPath, string $fileName, string $content, string $mimeType)
    {
        return $this->uploadFile($folderPath, $fileName, $content, $mimeType);
    }
    /* ------------------------------------------------------------------
     * WebDAV – File operations
     * ----------------------------------------------------------------*/

    /**
     * Check whether a folder exists in Nextcloud.
     *
     * @param  string        $folderPath Path relative to the admin user root.
     * @return bool|WP_Error True if exists, false if not found.
     */
    public function folderExists(string $folderPath)
    {
        $folderPath = $this->sanitizePath($folderPath);
        $url        = $this->davUrl . ($folderPath !== '' ? '/' . ltrim($folderPath, '/') : '');

        $response = $this->request('PROPFIND', $url, [
            'headers' => [
                'Depth'        => '0',
                'Content-Type' => 'application/xml; charset=utf-8',
            ],
            'body' => '<?xml version="1.0"?><d:propfind xmlns:d="DAV:"><d:prop><d:resourcetype/></d:prop></d:propfind>',
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code === 404) {
            return false;
        }

        return $code >= 200 && $code < 400;
    }

    /**
     * List the contents of a folder.
     *
     * @param  string   $folderPath Path relative to the admin user root (no leading slash).
     * @param  bool     $autoCreate Create the folder if it doesn't exist.
     * @return array[]|WP_Error     Array of file/folder descriptors.
     */
    public function listFolder(string $folderPath = '', bool $autoCreate = true)
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

        // Folder doesn't exist yet.
        if ($code === 404) {
            if (!$autoCreate) {
                return new WP_Error('mj_nextcloud_not_found', __('Le dossier n\'existe pas.', 'mj-member'));
            }
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
            'thumbnailUrl' => strpos($mimeType, 'image/') === 0 ? $this->getThumbnailUrl($filePath, current_time('c') . '|' . (is_string($content) ? strlen($content) : 0)) : '',
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
     * Download a file content from Nextcloud.
     *
     * @param string $filePath Path relative to admin root.
     * @return string|WP_Error
     */
    public function downloadFile(string $filePath)
    {
        $filePath = $this->sanitizePath($filePath);
        if ($filePath === '') {
            return new WP_Error('mj_nextcloud_download_invalid', __('Chemin de fichier invalide.', 'mj-member'));
        }

        $url = $this->davUrl . '/' . ltrim($filePath, '/');
        $response = $this->request('GET', $url);
        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            return new WP_Error(
                'mj_nextcloud_download_failed',
                sprintf(__('Impossible de télécharger le fichier (HTTP %d).', 'mj-member'), (int) $code)
            );
        }

        return (string) wp_remote_retrieve_body($response);
    }

    /**
     * Download a file from Nextcloud directly to a local path (streamed).
     *
     * This avoids loading full binary content into PHP memory.
     *
     * @param string $filePath   Path relative to admin root.
     * @param string $targetPath Absolute local path where file must be written.
     * @return true|WP_Error
     */
    public function downloadFileToPath(string $filePath, string $targetPath)
    {
        $filePath = $this->sanitizePath($filePath);
        $targetPath = trim($targetPath);

        if ($filePath === '' || $targetPath === '') {
            return new WP_Error('mj_nextcloud_download_invalid', __('Chemin de fichier invalide.', 'mj-member'));
        }

        $targetDir = dirname($targetPath);
        if (!is_dir($targetDir) && !wp_mkdir_p($targetDir)) {
            return new WP_Error('mj_nextcloud_download_target_dir', __('Impossible de créer le dossier cible de téléchargement.', 'mj-member'));
        }

        $url = $this->davUrl . '/' . ltrim($filePath, '/');
        $response = $this->request('GET', $url, [
            'stream'   => true,
            'filename' => $targetPath,
            'timeout'  => 90,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            @unlink($targetPath);
            return new WP_Error(
                'mj_nextcloud_download_failed',
                sprintf(__('Impossible de télécharger le fichier (HTTP %d).', 'mj-member'), $code)
            );
        }

        if (!file_exists($targetPath) || filesize($targetPath) === 0) {
            return new WP_Error('mj_nextcloud_download_empty', __('Le fichier téléchargé est vide ou introuvable.', 'mj-member'));
        }

        return true;
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
     * Move a file or folder to another folder.
     *
     * @param  string      $itemPath     Current path relative to admin root.
     * @param  string      $targetFolder Destination folder path relative to admin root.
     * @param  string|null $newName      Optional new basename.
     * @return array|WP_Error
     */
    public function move(string $itemPath, string $targetFolder, ?string $newName = null)
    {
        $itemPath     = $this->sanitizePath($itemPath);
        $targetFolder = $this->sanitizePath($targetFolder);
        $baseName     = $newName !== null && $newName !== '' ? $this->sanitizeName($newName) : basename($itemPath);

        if ($itemPath === '' || $targetFolder === '' || $baseName === '') {
            return new WP_Error('mj_nextcloud_move_invalid', __('Paramètres de déplacement invalides.', 'mj-member'));
        }

        $ensure = $this->ensureFolder($targetFolder);
        if (is_wp_error($ensure)) {
            return $ensure;
        }

        $newPath = rtrim($targetFolder, '/') . '/' . $baseName;
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
            return new WP_Error('mj_nextcloud_move_failed', sprintf(
                __('Impossible de déplacer l\'élément (HTTP %d).', 'mj-member'),
                $code
            ));
        }

        return [
            'path'    => $newPath,
            'name'    => basename($newPath),
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
            'path'   => $filePath,
            'nonce'  => wp_create_nonce('mj-registration-manager'),
        ], admin_url('admin-ajax.php'));
    }

    /**
     * Build a cached thumbnail URL for an image file.
     */
    public function getThumbnailUrl(string $filePath, string $version = '', int $width = 480, int $height = 480): string
    {
        if ($this->baseUrl === '') {
            return '';
        }

        $filePath = $this->sanitizePath($filePath);
        $version  = $version !== '' ? md5($version) : md5($filePath);

        return add_query_arg([
            'action' => 'mj_regmgr_nc_thumbnail',
            'path'   => $filePath,
            'w'      => max(64, $width),
            'h'      => max(64, $height),
            'v'      => $version,
            'nonce'  => wp_create_nonce('mj-registration-manager'),
        ], admin_url('admin-ajax.php'));
    }

    /**
     * Build a wrapper URL that redirects Nextcloud links through the WordPress documents page.
     * The page at /mon-compte/documents/ will handle the ?link= parameter and load in iframe.
     *
     * Extracts the 'dir' parameter from Nextcloud URLs like /apps/files/?dir=/path
     * or uses the path directly if it's already just a path.
     */
    private function buildWrapperUrl(string $ncPath): string
    {
        $ncPath = ltrim((string) $ncPath, '/');

        // If this is a Nextcloud file viewer URL (apps/files/files/{id}?...), pass the full path
        if (strpos($ncPath, 'apps/files/files/') === 0) {
            return add_query_arg('link', '/' . $ncPath, home_url('/mon-compte/documents/'));
        }

        // If this looks like a Nextcloud file browser URL, extract the 'dir' parameter
        if (strpos($ncPath, 'apps/files/?dir=') !== false) {
            // Extract the dir parameter value
            if (preg_match('/\?dir=(.+)$/', $ncPath, $matches)) {
                $dirValue = $matches[1];
                // Remove any additional query params (shouldn't be there, but safe)
                if (strpos($dirValue, '&') !== false) {
                    $dirValue = substr($dirValue, 0, strpos($dirValue, '&'));
                }
                // URL decode the dir value
                $dirValue = urldecode($dirValue);
                $ncPath = $dirValue;
            }
        }
        
        if ($ncPath === '') {
            return home_url('/mon-compte/documents/');
        }
        
        return add_query_arg('link', '/' . ltrim($ncPath, '/'), home_url('/mon-compte/documents/'));
    }

    /**
     * Build URL to documents page with link parameter.
     *
     * @param string $ncPath Nextcloud path (can start with /docs or other roots).
     * @return string URL to documents page with ?link= parameter.
     */
    public function getDocumentsPageUrl(string $ncPath): string
    {
        return $this->buildWrapperUrl($ncPath);
    }

    /**
     * Build the Nextcloud web UI file URL (direct link in browser).
     * Redirects through the WordPress documents wrapper page.
     */
    public function getWebUrl(string $filePath): string
    {
        if ($this->baseUrl === '') {
            return '';
        }
        $ncPath = '/apps/files/?dir=/' . rawurlencode(ltrim(dirname($this->sanitizePath($filePath)), '/'));
        return $this->buildWrapperUrl($ncPath);
    }

    /**
     * Build the Nextcloud web UI folder URL.
     * Redirects through the WordPress documents wrapper page.
     */
    public function getFolderWebUrl(string $folderPath): string
    {
        if ($this->baseUrl === '') {
            return '';
        }

        $ncPath = '/apps/files/?dir=/' . rawurlencode(ltrim($this->sanitizePath($folderPath), '/'));
        return $this->buildWrapperUrl($ncPath);
    }

    /**
     * Build a direct file URL in Nextcloud web UI.
     * Redirects through the WordPress documents wrapper page.
     */
    public function getFileWebUrl(string $filePath, string $fileId = ''): string
    {
        if ($this->baseUrl === '') {
            return '';
        }

        $fileId = trim($fileId);
        if ($fileId !== '') {
            // Use the files app file viewer URL: apps/files/files/{id}?dir={parentDir}&openfile=true
            $dirPath = '/' . ltrim(dirname($this->sanitizePath($filePath)), '/');
            $ncPath = '/apps/files/files/' . rawurlencode($fileId) . '?dir=' . rawurlencode($dirPath) . '&openfile=true';
            return $this->buildWrapperUrl($ncPath);
        }

        return $this->getWebUrl($filePath);
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

        $candidateUrls = $this->buildRequestCandidateUrls($url);
        $lastError = null;
        $forceStreams = $this->shouldForceStreamsTransport();

        foreach ($candidateUrls as $attemptUrl) {
            $response = $forceStreams
                ? $this->requestViaStreamsTransport($attemptUrl, $requestArgs)
                : wp_remote_request($attemptUrl, $requestArgs);

            if (is_wp_error($response) && $forceStreams) {
                // Safety fallback if streams transport is unavailable in this environment.
                $response = wp_remote_request($attemptUrl, $requestArgs);
            }

            if (is_wp_error($response) && $this->isDnsResolutionError($response)) {
                $response = $this->requestViaStreamsTransport($attemptUrl, $requestArgs);
            }

            if (!is_wp_error($response)) {
                return $response;
            }

            $lastError = $response;

            // Retry only DNS resolution failures on alternative base URLs.
            if (!$this->isDnsResolutionError($response)) {
                break;
            }
        }

        if ($lastError instanceof WP_Error && $this->isDnsResolutionError($lastError)) {
            return new WP_Error(
                'mj_nextcloud_dns_unreachable',
                __('Le serveur Nextcloud est temporairement indisponible (résolution DNS). Réessayez dans quelques instants.', 'mj-member')
            );
        }

        return $lastError instanceof WP_Error ? $lastError : new WP_Error(
            'mj_nextcloud_request_failed',
            __('Impossible de contacter Nextcloud.', 'mj-member')
        );
    }

    /**
     * Allow forcing Streams transport for Nextcloud calls (useful when cURL DNS threads are saturated).
     */
    private function shouldForceStreamsTransport(): bool
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
     * Retry a request by preferring the Streams transport over cURL.
     *
     * @return array|WP_Error
     */
    private function requestViaStreamsTransport(string $url, array $requestArgs)
    {
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
     * Build candidate URLs for the same endpoint (public URL, then internal URL fallback).
     *
     * @return array<int,string>
     */
    private function buildRequestCandidateUrls(string $url): array
    {
        $urls = [trim($url)];
        $fallbackBase = $this->getInternalBaseUrlFallback();

        if ($fallbackBase === '' || strpos($url, $this->baseUrl) !== 0) {
            return $urls;
        }

        $suffix = substr($url, strlen($this->baseUrl));
        $fallbackUrl = rtrim($fallbackBase, '/') . $suffix;
        if ($fallbackUrl !== $url) {
            $urls[] = $fallbackUrl;
        }

        return array_values(array_unique(array_filter($urls, static function ($candidate) {
            return is_string($candidate) && $candidate !== '';
        })));
    }

    /**
     * Resolve optional Nextcloud internal base URL used for server-to-server requests.
     */
    private function getInternalBaseUrlFallback(): string
    {
        $fallback = '';
        if (defined('MJ_MEMBER_NEXTCLOUD_INTERNAL_URL')) {
            $fallback = (string) constant('MJ_MEMBER_NEXTCLOUD_INTERNAL_URL');
        }

        if (function_exists('apply_filters')) {
            $fallback = (string) apply_filters('mj_member_nextcloud_internal_url', $fallback, $this->baseUrl);
        }

        $fallback = trim((string) $fallback);
        if ($fallback === '') {
            return '';
        }

        return rtrim(esc_url_raw($fallback), '/');
    }

    /**
     * Detect DNS/cURL resolution failures eligible for fallback retry.
     */
    private function isDnsResolutionError(WP_Error $error): bool
    {
        $message = strtolower((string) $error->get_error_message());

        return strpos($message, 'curl error 6') !== false
            || strpos($message, 'could not resolve host') !== false
            || strpos($message, 'getaddrinfo') !== false;
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
        $doc  = new \DOMDocument();
        $ok   = $doc->loadXML($xml);
        libxml_use_internal_errors($prev);

        if (!$ok) {
            return $items;
        }

        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('d',  'DAV:');
        $xpath->registerNamespace('oc', 'http://owncloud.org/ns');

        $responses = $xpath->query('//d:response');
        if (!$responses || $responses->length === 0) {
            return $items;
        }

        // DAV root prefix (decoded, to compare against decoded hrefs).
        $davPrefix = '/remote.php/dav/files/' . rawurldecode(rawurlencode($this->user)) . '/';

        foreach ($responses as $resp) {
            $hrefNodes = $xpath->query('d:href', $resp);
            if (!$hrefNodes || $hrefNodes->length === 0) {
                continue;
            }

            $href         = trim($hrefNodes->item(0)->textContent);
            $decodedHref  = rawurldecode($href);
            $relativePath = ltrim(substr($decodedHref, strlen($davPrefix)), '/');

            // Skip the container folder itself.
            if (rtrim($relativePath, '/') === $basePath) {
                continue;
            }

            // Find the 200 propstat.
            $propstatNodes = $xpath->query('d:propstat[d:status[contains(.,\'200\')]]', $resp);
            if (!$propstatNodes || $propstatNodes->length === 0) {
                continue;
            }
            $propstat = $propstatNodes->item(0);

            $isDir    = $xpath->query('d:prop/d:resourcetype/d:collection', $propstat)->length > 0;
            $mimeNode = $xpath->query('d:prop/d:getcontenttype', $propstat)->item(0);
            $sizeNode = $xpath->query('d:prop/d:getcontentlength', $propstat)->item(0);
            $modNode  = $xpath->query('d:prop/d:getlastmodified', $propstat)->item(0);
            $idNode   = $xpath->query('d:prop/oc:fileid', $propstat)->item(0);

            $mimeType = $isDir
                ? 'httpd/unix-directory'
                : ($mimeNode ? trim($mimeNode->textContent) : 'application/octet-stream');
            $size     = $sizeNode ? (int) trim($sizeNode->textContent) : 0;
            $modified = $modNode  ? trim($modNode->textContent) : '';
            $fileId   = $idNode ? trim($idNode->textContent) : '';
            $name     = basename(rtrim($relativePath, '/'));

            if ($name === '') {
                continue;
            }

            $items[] = [
                'path'         => $relativePath,
                'name'         => $name,
                'type'         => $isDir ? 'folder' : 'file',
                'mimeType'     => $mimeType,
                'size'         => $size,
                'modifiedTime' => $modified,
                'id'           => $fileId,
                'fileId'       => $fileId,
                'downloadUrl'  => $isDir ? '' : $this->getDownloadUrl($relativePath),
                'thumbnailUrl' => (!$isDir && strpos($mimeType, 'image/') === 0) ? $this->getThumbnailUrl($relativePath, $modified . '|' . $size) : '',
                'webUrl'       => $isDir ? $this->getWebUrl($relativePath) : $this->getFileWebUrl($relativePath, $fileId),
                'editUrl'      => $isDir ? '' : $this->getFileWebUrl($relativePath, $fileId),
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
