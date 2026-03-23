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
        $response = $this->request('GET', $url, array(
            'headers' => array(
                'OCS-APIREQUEST' => 'true',
                'Accept' => 'application/xml',
            ),
        ));

        if (is_wp_error($response)) {
            return array(
                'valid' => false,
                'userId' => '',
                'message' => $response->get_error_message(),
                'httpCode' => 0,
            );
        }

        $httpCode = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $meta = $this->parseOcsMeta($body);

        if ($httpCode >= 200 && $httpCode < 400 && $meta['statuscode'] === 100) {
            $userId = $this->extractXmlValue($body, 'id');
            if ($userId === '') {
                $userId = $this->extractXmlValue($body, 'userid');
            }

            return array(
                'valid' => true,
                'userId' => sanitize_user($userId, true),
                'message' => '',
                'httpCode' => $httpCode,
            );
        }

        $message = $meta['message'] !== ''
            ? $meta['message']
            : sprintf(__('Authentification Nextcloud invalide (HTTP %d).', 'mj-member'), (int) $httpCode);

        return array(
            'valid' => false,
            'userId' => '',
            'message' => $message,
            'httpCode' => (int) $httpCode,
        );
    }

    /**
     * Build a client with explicit user credentials and the configured server URL.
     *
     * Useful when requests must be executed on behalf of a member account.
     *
     * @return self|WP_Error
     */
    public static function makeWithCredentials(string $user, string $password)
    {
        $url = Config::nextcloudUrl();
        $user = sanitize_user($user, true);
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
        $response = $this->request('GET', $url, array(
            'headers' => array(
                'OCS-APIREQUEST' => 'true',
            ),
        ));

        if (is_wp_error($response)) {
            return false;
        }

        $httpCode = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $meta = $this->parseOcsMeta($body);

        // OCS success statuscode is typically 100.
        if ($meta['statuscode'] === 100) {
            return true;
        }

        // Some instances may only expose HTTP-level errors.
        if ($httpCode === 200 && $meta['statuscode'] === 0) {
            return true;
        }

        return false;
    }

    /**
     * Create a Nextcloud user via OCS provisioning API.
     *
     * @return array{userId:string,displayName:string,email:string}|WP_Error
     */
    public function createUser(string $userId, string $password, string $displayName = '', string $email = '')
    {
        $userId = sanitize_user($userId, true);
        $password = trim($password);
        $displayName = sanitize_text_field($displayName);
        $email = sanitize_email($email);

        if ($userId === '') {
            return new WP_Error('mj_nextcloud_user_invalid', __('Identifiant Nextcloud invalide.', 'mj-member'));
        }

        if ($password === '') {
            return new WP_Error('mj_nextcloud_password_invalid', __('Mot de passe Nextcloud manquant.', 'mj-member'));
        }

        if ($this->userExists($userId)) {
            return new WP_Error('mj_nextcloud_user_exists', __('Cet identifiant Nextcloud existe déjà.', 'mj-member'));
        }

        $payload = array(
            'userid' => $userId,
            'password' => $password,
        );

        if ($displayName !== '') {
            $payload['displayName'] = $displayName;
        }

        if ($email !== '') {
            $payload['email'] = $email;
        }

        $url = $this->baseUrl . '/ocs/v1.php/cloud/users';
        $response = $this->request('POST', $url, array(
            'headers' => array(
                'OCS-APIREQUEST' => 'true',
                'Content-Type' => 'application/x-www-form-urlencoded',
            ),
            'body' => $payload,
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $httpCode = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $meta = $this->parseOcsMeta($body);

        if ($httpCode >= 400) {
            $message = $meta['message'] !== ''
                ? $meta['message']
                : sprintf(__('Impossible de créer le compte Nextcloud (HTTP %d).', 'mj-member'), $httpCode);
            return new WP_Error('mj_nextcloud_user_create_failed', $message);
        }

        if ($meta['statuscode'] !== 100) {
            $message = $meta['message'] !== ''
                ? $meta['message']
                : __('La création du compte Nextcloud a échoué.', 'mj-member');
            return new WP_Error('mj_nextcloud_user_create_failed', $message);
        }

        return array(
            'userId' => $userId,
            'displayName' => $displayName,
            'email' => $email,
        );
    }

    /**
     * Add a Nextcloud user to an existing group via OCS provisioning API.
     *
     * @return true|WP_Error
     */
    public function addUserToGroup(string $userId, string $groupId)
    {
        $userId = sanitize_user($userId, true);
        $groupId = trim(sanitize_text_field($groupId));

        if ($userId === '') {
            return new WP_Error('mj_nextcloud_user_invalid', __('Identifiant Nextcloud invalide.', 'mj-member'));
        }

        if ($groupId === '') {
            return new WP_Error('mj_nextcloud_group_invalid', __('Groupe Nextcloud invalide.', 'mj-member'));
        }

        $url = $this->baseUrl . '/ocs/v1.php/cloud/users/' . rawurlencode($userId) . '/groups';
        $response = $this->request('POST', $url, array(
            'headers' => array(
                'OCS-APIREQUEST' => 'true',
                'Content-Type' => 'application/x-www-form-urlencoded',
            ),
            'body' => array(
                'groupid' => $groupId,
            ),
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $httpCode = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $meta = $this->parseOcsMeta($body);

        if ($httpCode >= 400) {
            $message = $meta['message'] !== ''
                ? $meta['message']
                : sprintf(__('Impossible d\'ajouter l\'utilisateur au groupe "%s" (HTTP %d).', 'mj-member'), $groupId, $httpCode);
            return new WP_Error('mj_nextcloud_group_assign_failed', $message);
        }

        if ($meta['statuscode'] !== 100) {
            $message = $meta['message'] !== ''
                ? $meta['message']
                : sprintf(__('Impossible d\'ajouter l\'utilisateur au groupe "%s".', 'mj-member'), $groupId);
            return new WP_Error('mj_nextcloud_group_assign_failed', $message);
        }

        return true;
    }

    /**
     * Update password of an existing Nextcloud user via OCS provisioning API.
     *
     * @return true|WP_Error
     */
    public function setUserPassword(string $userId, string $password)
    {
        $userId = sanitize_user($userId, true);
        $password = trim($password);

        if ($userId === '') {
            return new WP_Error('mj_nextcloud_user_invalid', __('Identifiant Nextcloud invalide.', 'mj-member'));
        }

        if ($password === '') {
            return new WP_Error('mj_nextcloud_password_invalid', __('Mot de passe Nextcloud manquant.', 'mj-member'));
        }

        $url = $this->baseUrl . '/ocs/v1.php/cloud/users/' . rawurlencode($userId);
        $response = $this->request('PUT', $url, array(
            'headers' => array(
                'OCS-APIREQUEST' => 'true',
                'Content-Type' => 'application/x-www-form-urlencoded',
            ),
            'body' => array(
                'key' => 'password',
                'value' => $password,
            ),
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $httpCode = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $meta = $this->parseOcsMeta($body);

        if ($httpCode >= 400) {
            $message = $meta['message'] !== ''
                ? $meta['message']
                : sprintf(__('Impossible de mettre à jour le mot de passe Nextcloud (HTTP %d).', 'mj-member'), $httpCode);
            return new WP_Error('mj_nextcloud_password_update_failed', $message);
        }

        if ($meta['statuscode'] !== 100) {
            $message = $meta['message'] !== ''
                ? $meta['message']
                : __('Impossible de mettre à jour le mot de passe Nextcloud.', 'mj-member');
            return new WP_Error('mj_nextcloud_password_update_failed', $message);
        }

        return true;
    }

    /**
     * Update avatar of an existing Nextcloud user via OCS provisioning API.
     *
     * @return true|WP_Error
     */
    public function setUserAvatar(string $userId, string $avatarBinary, string $mimeType = 'image/png')
    {
        $userId = sanitize_user($userId, true);
        if ($userId === '') {
            return new WP_Error('mj_nextcloud_user_invalid', __('Identifiant Nextcloud invalide.', 'mj-member'));
        }

        if ($avatarBinary === '') {
            return new WP_Error('mj_nextcloud_avatar_invalid', __('Avatar Nextcloud invalide.', 'mj-member'));
        }

        $resolvedMime = trim($mimeType) !== '' ? trim($mimeType) : 'image/png';
        $endpoints = array(
            $this->baseUrl . '/ocs/v1.php/cloud/users/' . rawurlencode($userId) . '/avatar',
            $this->baseUrl . '/ocs/v2.php/cloud/users/' . rawurlencode($userId) . '/avatar',
        );
        $methods = array('POST', 'PUT');

        $lastHttpCode = 0;
        $lastMessage = '';
        $attemptLogs = array();

        $tryRequest = function (string $method, string $url, array $headers, string $body, string $modeLabel) use (&$lastHttpCode, &$lastMessage, &$attemptLogs) {
            $response = $this->request($method, $url, array(
                'headers' => $headers,
                'body' => $body,
            ));

            if (is_wp_error($response)) {
                $lastMessage = $response->get_error_message();
                $attemptLogs[] = $modeLabel . ' ' . $method . ' ' . $url . ' => WP_Error: ' . $lastMessage;
                return false;
            }

            $lastHttpCode = wp_remote_retrieve_response_code($response);
            $respBody = wp_remote_retrieve_body($response);
            $meta = $this->parseOcsMeta($respBody);
            if ($meta['message'] !== '') {
                $lastMessage = $meta['message'];
            }

             $attemptLogs[] = $modeLabel . ' ' . $method . ' ' . $url . ' => HTTP ' . $lastHttpCode
                . ($lastMessage !== '' ? (' (' . $lastMessage . ')') : '');

            // Some instances return OCS statuscode=100, others just HTTP 2xx.
            if ($lastHttpCode < 400 && ($meta['statuscode'] === 100 || $meta['statuscode'] === 0)) {
                return true;
            }

            return false;
        };

        foreach ($endpoints as $url) {
            foreach ($methods as $method) {
                if ($tryRequest($method, $url, array(
                    'OCS-APIREQUEST' => 'true',
                    'Content-Type' => $resolvedMime,
                ), $avatarBinary, 'raw')) {
                    return true;
                }
            }
        }

        $boundary = '----MJMemberBoundary' . wp_generate_password(12, false, false);
        $multipartBody = "--{$boundary}\r\n"
            . "Content-Disposition: form-data; name=\"files\"; filename=\"avatar\"\r\n"
            . "Content-Type: {$resolvedMime}\r\n\r\n"
            . $avatarBinary . "\r\n"
            . "--{$boundary}--\r\n";

        // Only try POST for multipart (PUT + multipart combination causes 429 rate limiting).
        foreach ($endpoints as $url) {
            if ($tryRequest('POST', $url, array(
                'OCS-APIREQUEST' => 'true',
                'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
            ), $multipartBody, 'multipart')) {
                return true;
            }
        }

        $baseMessage = $lastMessage !== ''
            ? $lastMessage
            : ($lastHttpCode > 0
                ? sprintf(__('Impossible de mettre à jour l\'avatar Nextcloud (HTTP %d).', 'mj-member'), $lastHttpCode)
                : __('Impossible de mettre à jour l\'avatar Nextcloud.', 'mj-member'));

        $debugSuffix = !empty($attemptLogs)
            ? ' [' . implode(' | ', array_slice($attemptLogs, -4)) . ']'
            : '';

        $message = $baseMessage . $debugSuffix;

        return new WP_Error('mj_nextcloud_avatar_update_failed', $message);
    }

    /* ------------------------------------------------------------------
     * List folder (PROPFIND depth 1)
     * ----------------------------------------------------------------*/

    /**
     * @param string $folderPath Relative path inside Nextcloud user root (empty = root).
     * @return array{folder:array,breadcrumbs:array,items:array}|WP_Error
     */
    public function listFolder(string $folderPath = '')
    {
        $folderPath = $this->sanitizePath($folderPath);
        $url = $this->davUrl . '/' . ltrim($folderPath, '/');

        $body = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<d:propfind xmlns:d="DAV:" xmlns:oc="http://owncloud.org/ns" xmlns:nc="http://nextcloud.org/ns">'
            . '<d:prop>'
            . '<d:getlastmodified/><d:getcontentlength/><d:getcontenttype/><d:resourcetype/>'
            . '<oc:fileid/><oc:size/><oc:permissions/>'
            . '</d:prop>'
            . '</d:propfind>';

        $response = $this->request('PROPFIND', $url, array(
            'headers' => array(
                'Depth'        => '1',
                'Content-Type' => 'application/xml; charset=utf-8',
            ),
            'body' => $body,
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 400) {
            return new WP_Error('mj_nextcloud_list_failed', sprintf(
                __('Nextcloud a répondu HTTP %d.', 'mj-member'),
                $code
            ));
        }

        $xml = wp_remote_retrieve_body($response);

        return $this->parsePropfind($xml, $folderPath);
    }

    /* ------------------------------------------------------------------
     * Create folder (MKCOL)
     * ----------------------------------------------------------------*/

    /**
     * @return array{id:string,name:string,type:string}|WP_Error
     */
    public function createFolder(string $parentPath, string $name)
    {
        $parentPath = $this->sanitizePath($parentPath);
        $safeName   = $this->sanitizeName($name);
        if ($safeName === '') {
            return new WP_Error('mj_nextcloud_folder_invalid', __('Nom de dossier manquant.', 'mj-member'));
        }

        $newPath = rtrim($parentPath, '/') . '/' . rawurlencode($safeName);
        $url     = $this->davUrl . '/' . ltrim($newPath, '/');

        $response = $this->request('MKCOL', $url);
        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 400) {
            return new WP_Error('mj_nextcloud_folder_failed', sprintf(
                __('Impossible de créer le dossier (HTTP %d).', 'mj-member'),
                $code
            ));
        }

        return array(
            'id'           => $newPath,
            'name'         => $safeName,
            'type'         => 'folder',
            'mimeType'     => 'httpd/unix-directory',
            'modifiedTime' => current_time('c'),
            'size'         => 0,
            'webViewLink'  => '',
            'iconLink'     => '',
            'parents'      => array($parentPath),
        );
    }

    /* ------------------------------------------------------------------
     * Rename / Move (MOVE)
     * ----------------------------------------------------------------*/

    /**
     * @return array{id:string,name:string}|WP_Error
     */
    public function rename(string $itemPath, string $newName)
    {
        $itemPath = $this->sanitizePath($itemPath);
        $safeName = $this->sanitizeName($newName);
        if ($itemPath === '' || $safeName === '') {
            return new WP_Error('mj_nextcloud_rename_invalid', __('Chemin ou nom manquant.', 'mj-member'));
        }

        $parentDir   = dirname($itemPath);
        $destination = rtrim($parentDir, '/') . '/' . rawurlencode($safeName);
        $srcUrl      = $this->davUrl . '/' . ltrim($itemPath, '/');
        $destUrl     = $this->davUrl . '/' . ltrim($destination, '/');

        $response = $this->request('MOVE', $srcUrl, array(
            'headers' => array(
                'Destination' => $destUrl,
                'Overwrite'   => 'F',
            ),
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 400) {
            return new WP_Error('mj_nextcloud_rename_failed', sprintf(
                __('Impossible de renommer (HTTP %d).', 'mj-member'),
                $code
            ));
        }

        return array(
            'id'           => $destination,
            'name'         => $safeName,
            'type'         => 'file',
            'mimeType'     => '',
            'modifiedTime' => current_time('c'),
            'size'         => 0,
            'webViewLink'  => '',
            'iconLink'     => '',
            'parents'      => array($parentDir),
        );
    }

    /* ------------------------------------------------------------------
     * Upload files (PUT)
     * ----------------------------------------------------------------*/

    /**
     * @param string                          $parentPath
     * @param array<int,array<string,mixed>>  $files      Normalised $_FILES entries.
     * @return array<int,array<string,mixed>>|WP_Error
     */
    public function uploadFiles(string $parentPath, array $files)
    {
        $parentPath = $this->sanitizePath($parentPath);
        if (empty($files)) {
            return new WP_Error('mj_nextcloud_no_files', __('Aucun fichier à téléverser.', 'mj-member'));
        }

        $uploaded = array();

        foreach ($files as $fileEntry) {
            $tmp  = isset($fileEntry['tmp_name']) ? (string) $fileEntry['tmp_name'] : '';
            $name = isset($fileEntry['name']) ? (string) $fileEntry['name'] : '';
            $type = isset($fileEntry['type']) ? (string) $fileEntry['type'] : 'application/octet-stream';

            if ($tmp === '' || !is_uploaded_file($tmp)) {
                continue;
            }

            $safeName = $this->sanitizeName($name);
            if ($safeName === '') {
                $safeName = basename($tmp);
            }

            $remotePath = rtrim($parentPath, '/') . '/' . rawurlencode($safeName);
            $url        = $this->davUrl . '/' . ltrim($remotePath, '/');

            $content = file_get_contents($tmp);
            if ($content === false) {
                continue;
            }

            $response = $this->request('PUT', $url, array(
                'headers' => array(
                    'Content-Type' => ($type !== '' ? $type : 'application/octet-stream'),
                ),
                'body' => $content,
            ));

            if (is_wp_error($response)) {
                return $response;
            }

            $code = wp_remote_retrieve_response_code($response);
            if ($code >= 400) {
                return new WP_Error('mj_nextcloud_upload_failed', sprintf(
                    __('Échec upload de "%s" (HTTP %d).', 'mj-member'),
                    $safeName,
                    $code
                ));
            }

            $uploaded[] = array(
                'id'           => $remotePath,
                'name'         => $safeName,
                'type'         => 'file',
                'mimeType'     => $type,
                'modifiedTime' => current_time('c'),
                'size'         => strlen($content),
                'webViewLink'  => $this->buildWebLink($remotePath),
                'iconLink'     => '',
                'parents'      => array($parentPath),
            );
        }

        if (empty($uploaded)) {
            return new WP_Error('mj_nextcloud_upload_empty', __('Aucun fichier n\'a pu être téléversé.', 'mj-member'));
        }

        return $uploaded;
    }

    /**
     * Upload raw content as a file to Nextcloud.
     *
     * @return array<string,mixed>|WP_Error
     */
    public function uploadContent(string $parentPath, string $fileName, string $content, string $mimeType = 'application/octet-stream')
    {
        $parentPath = $this->sanitizePath($parentPath);
        $safeName = $this->sanitizeName($fileName);

        if ($safeName === '') {
            return new WP_Error('mj_nextcloud_upload_invalid_name', __('Nom de fichier invalide.', 'mj-member'));
        }

        $remotePath = rtrim($parentPath, '/') . '/' . rawurlencode($safeName);
        $url = $this->davUrl . '/' . ltrim($remotePath, '/');

        $resolvedMime = $mimeType !== '' ? $mimeType : 'application/octet-stream';

        // 1) Try with provided MIME type.
        $response = $this->request('PUT', $url, array(
            'headers' => array(
                'Content-Type' => $resolvedMime,
            ),
            'body' => $content,
        ));

        if (!is_wp_error($response)) {
            $code = wp_remote_retrieve_response_code($response);

            // 2) Some reverse proxies / security layers reject specific image types with 415.
            if ($code === 415) {
                $response = $this->request('PUT', $url, array(
                    'headers' => array(
                        'Content-Type' => 'application/octet-stream',
                    ),
                    'body' => $content,
                ));

                if (!is_wp_error($response)) {
                    $code = wp_remote_retrieve_response_code($response);
                }
            }

            // 3) Last attempt without explicit Content-Type.
            if (!is_wp_error($response) && $code === 415) {
                $response = $this->request('PUT', $url, array(
                    'body' => $content,
                ));

                if (!is_wp_error($response)) {
                    $code = wp_remote_retrieve_response_code($response);
                }
            }
        }

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code >= 400) {
            return new WP_Error('mj_nextcloud_upload_failed', sprintf(
                __('Échec upload de "%s" (HTTP %d).', 'mj-member'),
                $safeName,
                $code
            ));
        }

        return array(
            'id' => $remotePath,
            'name' => $safeName,
            'type' => 'file',
            'mimeType' => $mimeType,
            'modifiedTime' => current_time('c'),
            'size' => strlen($content),
            'webViewLink' => $this->buildWebLink($remotePath),
            'iconLink' => '',
            'parents' => array($parentPath),
        );
    }

    /* ------------------------------------------------------------------
     * Delete (DELETE)
     * ----------------------------------------------------------------*/

    /**
     * @return true|WP_Error
     */
    public function delete(string $itemPath)
    {
        $itemPath = $this->sanitizePath($itemPath);
        if ($itemPath === '') {
            return new WP_Error('mj_nextcloud_delete_invalid', __('Chemin manquant.', 'mj-member'));
        }

        $url      = $this->davUrl . '/' . ltrim($itemPath, '/');
        $response = $this->request('DELETE', $url);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code >= 400) {
            return new WP_Error('mj_nextcloud_delete_failed', sprintf(
                __('Impossible de supprimer (HTTP %d).', 'mj-member'),
                $code
            ));
        }

        return true;
    }

    /* ------------------------------------------------------------------
     * Direct Editing – open in Collabora / OnlyOffice via OCS
     * ----------------------------------------------------------------*/

    /**
     * Request a one-time direct-editing URL from Nextcloud.
     *
     * Requires the Nextcloud "Direct Editing" API (available when Collabora
     * or OnlyOffice is installed via the Nextcloud apps).
     *
     * @param string $filePath Relative path inside Nextcloud root.
     * @return array{url:string}|WP_Error
     */
    public function getDirectEditUrl(string $filePath)
    {
        $filePath = $this->sanitizePath($filePath);
        if ($filePath === '') {
            return new WP_Error('mj_nextcloud_edit_invalid', __('Chemin du fichier manquant.', 'mj-member'));
        }

        $url = $this->baseUrl . '/ocs/v2.php/apps/files/api/v1/directEditing/open';

        $response = $this->request('POST', $url, array(
            'headers' => array(
                'OCS-APIREQUEST' => 'true',
                'Content-Type'   => 'application/x-www-form-urlencoded',
            ),
            'body' => array(
                'path'     => '/' . ltrim($filePath, '/'),
                'editorId' => '', // empty ⇒ Nextcloud picks the first available editor
            ),
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($code >= 400 || $body === '') {
            return new WP_Error('mj_nextcloud_edit_failed', sprintf(
                __('Impossible d\'ouvrir l\'éditeur (HTTP %d).', 'mj-member'),
                $code
            ));
        }

        $decoded = json_decode($body, true);
        $editUrl = $decoded['ocs']['data']['url'] ?? '';

        if ($editUrl === '') {
            // Fallback: try XML response
            $editUrl = $this->extractXmlValue($body, 'url');
        }

        if ($editUrl === '') {
            return new WP_Error(
                'mj_nextcloud_edit_no_url',
                __('Aucune URL d\'édition renvoyée. Vérifiez qu\'un éditeur (Collabora / OnlyOffice) est installé sur Nextcloud.', 'mj-member')
            );
        }

        return array('url' => $editUrl);
    }

    /* ------------------------------------------------------------------
     * Download URL (WebDAV direct)
     * ----------------------------------------------------------------*/

    /**
     * Build a direct download URL (requires user authentication via browser).
     *
     * @param string $filePath
     * @return string
     */
    public function getDownloadUrl(string $filePath): string
    {
        $filePath = $this->sanitizePath($filePath);
        return $this->baseUrl . '/remote.php/dav/files/'
            . rawurlencode($this->user)
            . '/' . ltrim($filePath, '/');
    }

    /**
     * Build a Nextcloud web UI link for a file.
     */
    public function buildWebLink(string $filePath): string
    {
        $filePath = $this->sanitizePath($filePath);
        $dir      = dirname($filePath);

        return $this->baseUrl . '/apps/files/?dir=/' . ltrim($dir, '/');
    }

    /* ------------------------------------------------------------------
     * Create new document from Nextcloud template
     * ----------------------------------------------------------------*/

    /**
     * Create an empty file on Nextcloud (PUT with empty body).
     *
     * @param string $parentPath Relative parent folder.
     * @param string $name       File name with extension (.docx, .xlsx, .pptx, .md)
     * @return array<string,mixed>|WP_Error
     */
    public function createDocument(string $parentPath, string $name)
    {
        $parentPath = $this->sanitizePath($parentPath);
        $safeName   = $this->sanitizeName($name);

        if ($safeName === '') {
            return new WP_Error('mj_nextcloud_doc_invalid', __('Nom de fichier manquant.', 'mj-member'));
        }

        $remotePath = rtrim($parentPath, '/') . '/' . rawurlencode($safeName);
        $url        = $this->davUrl . '/' . ltrim($remotePath, '/');

        // Create empty file
        $response = $this->request('PUT', $url, array(
            'headers' => array('Content-Type' => $this->guessMimeType($safeName)),
            'body'    => '',
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code >= 400) {
            return new WP_Error('mj_nextcloud_doc_failed', sprintf(
                __('Impossible de créer le document (HTTP %d).', 'mj-member'),
                $code
            ));
        }

        return array(
            'id'           => $remotePath,
            'name'         => $safeName,
            'type'         => 'file',
            'mimeType'     => $this->guessMimeType($safeName),
            'modifiedTime' => current_time('c'),
            'size'         => 0,
            'webViewLink'  => $this->buildWebLink($remotePath),
            'iconLink'     => '',
            'parents'      => array($parentPath),
        );
    }

    /* ------------------------------------------------------------------
     * HTTP helper
     * ----------------------------------------------------------------*/

    /**
     * @param string               $method
     * @param string               $url
     * @param array<string,mixed>  $args
     * @return array|WP_Error
     */
    private function request(string $method, string $url, array $args = array())
    {
        $defaults = array(
            'method'  => $method,
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($this->user . ':' . $this->password),
            ),
        );

        $merged = array_merge_recursive($defaults, $args);

        // Ensure Authorization header is not duplicated
        if (isset($args['headers']) && is_array($args['headers'])) {
            $merged['headers'] = array_merge($defaults['headers'], $args['headers']);
        }

        return wp_remote_request($url, $merged);
    }

    /* ------------------------------------------------------------------
     * PROPFIND XML parser
     * ----------------------------------------------------------------*/

    /**
     * Parse a PROPFIND multistatus response.
     *
     * @param string $xml
     * @param string $currentFolder
     * @return array{folder:array,breadcrumbs:array,items:array}
     */
    private function parsePropfind(string $xml, string $currentFolder): array
    {
        libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml);
        if ($doc === false) {
            return array('folder' => array(), 'breadcrumbs' => array(), 'items' => array());
        }

        $doc->registerXPathNamespace('d', 'DAV:');
        $doc->registerXPathNamespace('oc', 'http://owncloud.org/ns');

        $responses = $doc->xpath('//d:response');
        if (!is_array($responses)) {
            return array('folder' => array(), 'breadcrumbs' => array(), 'items' => array());
        }

        $folder = array();
        $items  = array();
        $userPath = '/remote.php/dav/files/' . rawurlencode($this->user) . '/';

        foreach ($responses as $resp) {
            $href = (string) $resp->xpath('d:href')[0];
            $props = $resp->xpath('d:propstat/d:prop');
            if (empty($props)) {
                continue;
            }
            $prop = $props[0];

            // Extract relative path from href
            $decodedHref = rawurldecode($href);
            $relativePath = '';
            $userPathPos = strpos($decodedHref, $userPath);
            if ($userPathPos !== false) {
                $relativePath = substr($decodedHref, $userPathPos + strlen($userPath));
            } else {
                $relativePath = ltrim($decodedHref, '/');
            }
            $relativePath = rtrim($relativePath, '/');

            $isDir = false;
            $resourceType = $prop->xpath('d:resourcetype/d:collection');
            if (!empty($resourceType)) {
                $isDir = true;
            }

            $contentLength = (string) ($prop->xpath('d:getcontentlength')[0] ?? '0');
            $contentType   = (string) ($prop->xpath('d:getcontenttype')[0] ?? '');
            $lastModified  = (string) ($prop->xpath('d:getlastmodified')[0] ?? '');
            $ocFileId      = (string) ($prop->xpath('oc:fileid')[0] ?? '');

            $name = basename($relativePath);

            $entry = array(
                'id'           => $relativePath,
                'name'         => $this->sanitizeName(rawurldecode($name)),
                'mimeType'     => $isDir ? 'httpd/unix-directory' : $contentType,
                'type'         => $isDir ? 'folder' : 'file',
                'modifiedTime' => $lastModified,
                'size'         => (int) $contentLength,
                'webViewLink'  => $isDir ? '' : $this->buildWebLink($relativePath),
                'iconLink'     => '',
                'parents'      => array(dirname($relativePath)),
                'fileId'       => $ocFileId,
            );

            // First entry is the folder itself
            if (empty($folder)) {
                $folder = $entry;
            } else {
                $items[] = $entry;
            }
        }

        // Sort: folders first, then files alphabetically
        usort($items, function ($a, $b) {
            if ($a['type'] !== $b['type']) {
                return $a['type'] === 'folder' ? -1 : 1;
            }
            return strnatcasecmp($a['name'], $b['name']);
        });

        return array(
            'folder'      => $folder,
            'breadcrumbs' => $this->buildBreadcrumbs($currentFolder),
            'items'       => $items,
        );
    }

    /* ------------------------------------------------------------------
     * Breadcrumbs
     * ----------------------------------------------------------------*/

    /**
     * @param string $folderPath
     * @return array<int,array{id:string,name:string}>
     */
    private function buildBreadcrumbs(string $folderPath): array
    {
        $rootPath = Config::nextcloudRootFolder();
        $crumbs   = array();

        $folderPath = trim($folderPath, '/');
        if ($folderPath === '') {
            return array(array(
                'id'   => '',
                'name' => __('Racine', 'mj-member'),
            ));
        }

        // Always start with root
        $crumbs[] = array(
            'id'   => $rootPath,
            'name' => __('Racine', 'mj-member'),
        );

        $parts       = explode('/', $folderPath);
        $rootParts   = $rootPath !== '' ? explode('/', trim($rootPath, '/')) : array();
        $accumulated = '';

        foreach ($parts as $i => $segment) {
            $accumulated .= ($accumulated !== '' ? '/' : '') . $segment;

            // Skip parts that are part of the root prefix
            if (isset($rootParts[$i]) && $rootParts[$i] === $segment) {
                continue;
            }

            $crumbs[] = array(
                'id'   => $accumulated,
                'name' => rawurldecode($segment),
            );
        }

        return $crumbs;
    }

    /* ------------------------------------------------------------------
     * Helpers
     * ----------------------------------------------------------------*/

    private function sanitizePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $path = preg_replace('#/+#', '/', $path) ?? $path;
        $path = trim($path, '/');

        // Prevent traversal
        $parts = explode('/', $path);
        $safe  = array();
        foreach ($parts as $part) {
            if ($part === '..' || $part === '.') {
                continue;
            }
            if ($part !== '') {
                $safe[] = $part;
            }
        }

        return implode('/', $safe);
    }

    private function sanitizeName(string $name): string
    {
        if (!is_string($name)) {
            return '';
        }

        $clean = trim($name);
        if ($clean === '') {
            return '';
        }

        return \sanitize_text_field($clean);
    }

    private function guessMimeType(string $filename): string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return match ($ext) {
            'docx'  => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xlsx'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'pptx'  => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'odt'   => 'application/vnd.oasis.opendocument.text',
            'ods'   => 'application/vnd.oasis.opendocument.spreadsheet',
            'odp'   => 'application/vnd.oasis.opendocument.presentation',
            'pdf'   => 'application/pdf',
            'txt'   => 'text/plain',
            'md'    => 'text/markdown',
            default => 'application/octet-stream',
        };
    }

    /**
     * Parse the OCS XML metadata block.
     *
     * @return array{statuscode:int,message:string}
     */
    private function parseOcsMeta(string $xml): array
    {
        $statusCode = 0;
        $message = '';

        if ($xml === '') {
            return array('statuscode' => $statusCode, 'message' => $message);
        }

        libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml);
        if ($doc === false) {
            return array('statuscode' => $statusCode, 'message' => $message);
        }

        $doc->registerXPathNamespace('ocs', 'http://open-collaboration-services.org/ns');

        $statusNodes = $doc->xpath('//ocs:meta/ocs:statuscode');
        if (is_array($statusNodes) && !empty($statusNodes)) {
            $statusCode = (int) $statusNodes[0];
        } else {
            $fallbackStatus = $doc->xpath('//statuscode');
            if (is_array($fallbackStatus) && !empty($fallbackStatus)) {
                $statusCode = (int) $fallbackStatus[0];
            }
        }

        $messageNodes = $doc->xpath('//ocs:meta/ocs:message');
        if (is_array($messageNodes) && !empty($messageNodes)) {
            $message = (string) $messageNodes[0];
        } else {
            $fallbackMessage = $doc->xpath('//message');
            if (is_array($fallbackMessage) && !empty($fallbackMessage)) {
                $message = (string) $fallbackMessage[0];
            }
        }

        return array(
            'statuscode' => $statusCode,
            'message' => trim($message),
        );
    }

    /**
     * Extract a value from an OCS XML response.
     */
    private function extractXmlValue(string $xml, string $tag): string
    {
        libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml);
        if ($doc === false) {
            return '';
        }

        $nodes = $doc->xpath('//' . $tag);
        if (!empty($nodes)) {
            return (string) $nodes[0];
        }

        return '';
    }
}
