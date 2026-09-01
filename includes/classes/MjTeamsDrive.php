<?php

namespace Mj\Member\Classes;

use Mj\Member\Core\Config;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

final class MjTeamsDrive
{
    private const GRAPH_BASE = 'https://graph.microsoft.com/v1.0';
    private const TOKEN_TRANSIENT = 'mj_member_teams_graph_token';
    private const SIMPLE_UPLOAD_LIMIT = 4194304; // 4 MB
    private const UPLOAD_CHUNK_SIZE = 5242880; // 5 MB

    private string $accessToken;
    private string $driveId;

    private function __construct(string $accessToken, string $driveId)
    {
        $this->accessToken = $accessToken;
        $this->driveId = $driveId;
    }

    /**
     * @return self|WP_Error
     */
    public static function make()
    {
        if (!Config::teamsDocumentsIsReady()) {
            return new WP_Error('mj_teams_not_configured', __('La configuration Microsoft Teams est incomplète.', 'mj-member'));
        }

        $driveId = Config::teamsDriveId();
        if ($driveId === '') {
            return new WP_Error('mj_teams_missing_drive', __('Identifiant de drive Microsoft Teams manquant.', 'mj-member'));
        }

        $token = self::getAccessToken();
        if (\is_wp_error($token)) {
            return $token;
        }

        return new self($token, $driveId);
    }

    /**
     * @return array<string,mixed>|WP_Error
     */
    public function listFolder(string $folderId)
    {
        $targetId = trim($folderId);
        if ($targetId === '') {
            $targetId = Config::teamsRootItemId();
        }

        $useRoot = ($targetId === '' || $targetId === 'root');
        $item = $useRoot ? $this->getRootItem() : $this->getItem($targetId);
        if (\is_wp_error($item)) {
            return $item;
        }

        $itemId = isset($item['id']) ? (string) $item['id'] : '';
        if ($itemId === '') {
            return new WP_Error('mj_teams_missing_folder', __('Impossible de déterminer le dossier demandé.', 'mj-member'));
        }

        $childrenResponse = $this->request(
            'GET',
            sprintf('/drives/%s/items/%s/children', rawurlencode($this->driveId), rawurlencode($itemId)),
            array(
                'query' => array(
                    '$select' => 'id,name,folder,file,webUrl,lastModifiedDateTime,size,parentReference',
                    '$top' => 200,
                    '$orderby' => 'folder desc,name',
                ),
            )
        );

        if (\is_wp_error($childrenResponse)) {
            return $childrenResponse;
        }

        $items = array();
        if (isset($childrenResponse['value']) && is_array($childrenResponse['value'])) {
            foreach ($childrenResponse['value'] as $child) {
                if (!is_array($child)) {
                    continue;
                }

                $normalized = $this->normalizeDriveItem($child);
                if (!empty($normalized)) {
                    $items[] = $normalized;
                }
            }
        }

        return array(
            'folder' => $this->normalizeDriveItem($item),
            'breadcrumbs' => $this->buildBreadcrumbs($item),
            'items' => $items,
        );
    }

    /**
     * @return array<string,mixed>|WP_Error
     */
    public function rename(string $itemId, string $name)
    {
        $itemId = trim($itemId);
        $safeName = $this->sanitizeName($name);

        if ($itemId === '' || $safeName === '') {
            return new WP_Error('mj_teams_rename_invalid', __('Nom ou identifiant manquant pour le renommage.', 'mj-member'));
        }

        $response = $this->request(
            'PATCH',
            sprintf('/drives/%s/items/%s', rawurlencode($this->driveId), rawurlencode($itemId)),
            array(
                'body' => \wp_json_encode(array('name' => $safeName)),
                'headers' => array('Content-Type' => 'application/json'),
            )
        );

        if (\is_wp_error($response)) {
            return $response;
        }

        return $this->normalizeDriveItem($response);
    }

    /**
     * @return array<string,mixed>|WP_Error
     */
    public function createFolder(string $parentId, string $name)
    {
        $parentId = trim($parentId);
        if ($parentId === '') {
            $parentId = Config::teamsRootItemId();
        }

        $safeName = $this->sanitizeName($name);
        if ($safeName === '') {
            return new WP_Error('mj_teams_folder_invalid', __('Nom de dossier invalide.', 'mj-member'));
        }

        $folderPayload = array(
            'name' => $safeName,
            'folder' => new \stdClass(),
            '@microsoft.graph.conflictBehavior' => 'rename',
        );

        $path = $parentId === '' || $parentId === 'root'
            ? sprintf('/drives/%s/root/children', rawurlencode($this->driveId))
            : sprintf('/drives/%s/items/%s/children', rawurlencode($this->driveId), rawurlencode($parentId));

        $response = $this->request(
            'POST',
            $path,
            array(
                'body' => \wp_json_encode($folderPayload),
                'headers' => array('Content-Type' => 'application/json'),
            )
        );

        if (\is_wp_error($response)) {
            return $response;
        }

        return $this->normalizeDriveItem($response);
    }

    /**
     * @return true|WP_Error
     */
    public function delete(string $itemId)
    {
        $itemId = trim($itemId);
        if ($itemId === '') {
            return new WP_Error('mj_teams_delete_invalid', __('Identifiant de l\'élément manquant.', 'mj-member'));
        }

        $response = $this->request(
            'DELETE',
            sprintf('/drives/%s/items/%s', rawurlencode($this->driveId), rawurlencode($itemId))
        );

        if (\is_wp_error($response)) {
            return $response;
        }

        return true;
    }

    /**
     * @param array<int,array<string,mixed>> $files
     * @return array<int,array<string,mixed>>|WP_Error
     */
    public function uploadFiles(string $parentId, array $files)
    {
        if (empty($files)) {
            return new WP_Error('mj_teams_no_files', __('Aucun fichier à téléverser.', 'mj-member'));
        }

        $uploaded = array();
        foreach ($files as $fileEntry) {
            $result = $this->uploadFile($parentId, $fileEntry);
            if (\is_wp_error($result)) {
                return $result;
            }

            if (!empty($result)) {
                $uploaded[] = $result;
            }
        }

        if (empty($uploaded)) {
            return new WP_Error('mj_teams_upload_empty', __('Le téléversement a échoué ou aucun fichier valide n\'a été fourni.', 'mj-member'));
        }

        return $uploaded;
    }

    /**
     * @return array<string,mixed>|WP_Error
     */
    private function getRootItem()
    {
        return $this->request(
            'GET',
            sprintf('/drives/%s/root', rawurlencode($this->driveId))
        );
    }

    /**
     * @return array<string,mixed>|WP_Error
     */
    private function getItem(string $itemId)
    {
        return $this->request(
            'GET',
            sprintf('/drives/%s/items/%s', rawurlencode($this->driveId), rawurlencode($itemId))
        );
    }

    /**
     * @param array<string,mixed> $fileEntry
     * @return array<string,mixed>|WP_Error
     */
    private function uploadFile(string $parentId, array $fileEntry)
    {
        $tmp = isset($fileEntry['tmp_name']) ? (string) $fileEntry['tmp_name'] : '';
        $name = isset($fileEntry['name']) ? (string) $fileEntry['name'] : '';
        $type = isset($fileEntry['type']) ? (string) $fileEntry['type'] : '';
        $size = isset($fileEntry['size']) ? (int) $fileEntry['size'] : 0;

        if ($tmp === '' || !is_readable($tmp)) {
            return new WP_Error('mj_teams_upload_tmp_missing', __('Fichier temporaire introuvable pour le téléversement.', 'mj-member'));
        }

        $safeName = $this->sanitizeName($name);
        if ($safeName === '') {
            $safeName = basename($tmp);
        }

        if ($size <= self::SIMPLE_UPLOAD_LIMIT) {
            return $this->simpleUpload($parentId, $tmp, $safeName, $type);
        }

        return $this->chunkedUpload($parentId, $tmp, $safeName, $size, $type);
    }

    /**
     * @return array<string,mixed>|WP_Error
     */
    private function simpleUpload(string $parentId, string $filePath, string $fileName, string $mimeType)
    {
        $parentSegment = ($parentId === '' || $parentId === 'root')
            ? 'root'
            : sprintf('items/%s', rawurlencode($parentId));

        $encodedName = rawurlencode($fileName);
        $path = sprintf(
            '/drives/%s/%s:/%s:/content',
            rawurlencode($this->driveId),
            $parentSegment,
            $encodedName
        );

        $contents = file_get_contents($filePath);
        if ($contents === false) {
            return new WP_Error('mj_teams_upload_read_failed', __('Impossible de lire le fichier à téléverser.', 'mj-member'));
        }

        $response = $this->request(
            'PUT',
            $path,
            array(
                'body' => $contents,
                'headers' => array('Content-Type' => $mimeType !== '' ? $mimeType : 'application/octet-stream'),
                'timeout' => 120,
            )
        );

        if (\is_wp_error($response)) {
            return $response;
        }

        return $this->normalizeDriveItem($response);
    }

    /**
     * @return array<string,mixed>|WP_Error
     */
    private function chunkedUpload(string $parentId, string $filePath, string $fileName, int $fileSize, string $mimeType)
    {
        $parentSegment = ($parentId === '' || $parentId === 'root')
            ? 'root'
            : sprintf('items/%s', rawurlencode($parentId));

        $encodedName = rawurlencode($fileName);
        $path = sprintf(
            '/drives/%s/%s:/%s:/createUploadSession',
            rawurlencode($this->driveId),
            $parentSegment,
            $encodedName
        );

        $session = $this->request(
            'POST',
            $path,
            array(
                'body' => \wp_json_encode(array(
                    '@microsoft.graph.conflictBehavior' => 'rename',
                    'item' => array(
                        '@microsoft.graph.conflictBehavior' => 'rename',
                        'name' => $fileName,
                    ),
                )),
                'headers' => array('Content-Type' => 'application/json'),
            )
        );

        if (\is_wp_error($session)) {
            return $session;
        }

        if (!is_array($session) || !isset($session['uploadUrl'])) {
            return new WP_Error('mj_teams_upload_session', __('Création de session de téléversement Microsoft Graph échouée.', 'mj-member'));
        }

        $uploadUrl = (string) $session['uploadUrl'];
        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            return new WP_Error('mj_teams_upload_open', __('Impossible d\'ouvrir le fichier pour lecture.', 'mj-member'));
        }

        $offset = 0;
        $chunkSize = self::UPLOAD_CHUNK_SIZE;
        $lastResponse = null;

        while (!feof($handle)) {
            $chunk = fread($handle, $chunkSize);
            if ($chunk === false) {
                fclose($handle);
                return new WP_Error('mj_teams_upload_chunk', __('Lecture d\'un segment du fichier impossible.', 'mj-member'));
            }

            $length = strlen($chunk);
            if ($length === 0) {
                break;
            }

            $end = $offset + $length - 1;
            $headers = array(
                'Content-Length' => (string) $length,
                'Content-Range' => sprintf('bytes %d-%d/%d', $offset, $end, $fileSize),
                'Content-Type' => $mimeType !== '' ? $mimeType : 'application/octet-stream',
            );

            $response = \wp_remote_request($uploadUrl, array(
                'method' => 'PUT',
                'timeout' => 300,
                'body' => $chunk,
                'headers' => $headers,
            ));

            if (\is_wp_error($response)) {
                fclose($handle);
                return new WP_Error('mj_teams_upload_remote', $response->get_error_message());
            }

            $status = (int) \wp_remote_retrieve_response_code($response);
            $body = \wp_remote_retrieve_body($response);
            $offset += $length;

            if ($status === 200 || $status === 201) {
                fclose($handle);
                $decoded = json_decode($body, true);
                return $this->normalizeDriveItem(is_array($decoded) ? $decoded : array());
            }

            if ($status >= 400) {
                fclose($handle);
                $message = __('Le téléversement Microsoft Graph a échoué.', 'mj-member');
                $decodedError = json_decode($body, true);
                if (isset($decodedError['error']['message'])) {
                    $message = (string) $decodedError['error']['message'];
                }

                return new WP_Error('mj_teams_upload_failed', $message, array('status' => $status));
            }

            $lastResponse = $response;
        }

        fclose($handle);

        if ($lastResponse !== null) {
            $body = \wp_remote_retrieve_body($lastResponse);
            $decoded = json_decode($body, true);
            if (is_array($decoded) && isset($decoded['id'])) {
                return $this->normalizeDriveItem($decoded);
            }
        }

        return new WP_Error('mj_teams_upload_unknown', __('Réponse inattendue après le téléversement.', 'mj-member'));
    }

    /**
     * @param array<string,mixed> $item
     * @return array<int,array<string,string>>
     */
    private function buildBreadcrumbs(array $item): array
    {
        $breadcrumbs = array();
        $itemId = isset($item['id']) ? (string) $item['id'] : '';
        if ($itemId === '') {
            return $breadcrumbs;
        }

        $ancestors = $this->request(
            'GET',
            sprintf('/drives/%s/items/%s/ancestors', rawurlencode($this->driveId), rawurlencode($itemId)),
            array(
                'query' => array('$select' => 'id,name'),
            )
        );

        if (!\is_wp_error($ancestors) && isset($ancestors['value']) && is_array($ancestors['value'])) {
            foreach ($ancestors['value'] as $ancestor) {
                if (!is_array($ancestor) || !isset($ancestor['id'])) {
                    continue;
                }

                $breadcrumbs[] = array(
                    'id' => (string) $ancestor['id'],
                    'name' => $this->sanitizeName($ancestor['name'] ?? ''),
                );
            }
        }

        $breadcrumbs[] = array(
            'id' => $itemId,
            'name' => $this->sanitizeName($item['name'] ?? ''),
        );

        return $breadcrumbs;
    }

    /**
     * @param array<string,mixed> $item
     * @return array<string,mixed>
     */
    private function normalizeDriveItem(array $item): array
    {
        if (!isset($item['id'])) {
            return array();
        }

        $isFolder = isset($item['folder']);
        $mimeType = '';
        if (isset($item['file']['mimeType'])) {
            $mimeType = (string) $item['file']['mimeType'];
        } elseif ($isFolder) {
            $mimeType = 'application/vnd.microsoft.folder';
        }

        $parents = array();
        if (isset($item['parentReference']['id'])) {
            $parents[] = (string) $item['parentReference']['id'];
        }

        return array(
            'id' => (string) $item['id'],
            'name' => $this->sanitizeName($item['name'] ?? ''),
            'mimeType' => $mimeType,
            'type' => $isFolder ? 'folder' : 'file',
            'modifiedTime' => isset($item['lastModifiedDateTime']) ? (string) $item['lastModifiedDateTime'] : '',
            'size' => isset($item['size']) ? (int) $item['size'] : 0,
            'webViewLink' => isset($item['webUrl']) ? \esc_url_raw((string) $item['webUrl']) : '',
            'iconLink' => '',
            'parents' => $parents,
        );
    }

    /**
     * @param array<string,mixed> $options
     * @return array<string,mixed>|WP_Error
     */
    private function request(string $method, string $path, array $options = array())
    {
        $query = isset($options['query']) && is_array($options['query']) ? $options['query'] : array();
        $url = $this->buildUrl($path, $query);

        $headers = array(
            'Authorization' => 'Bearer ' . $this->accessToken,
            'Accept' => 'application/json',
        );

        if (isset($options['headers']) && is_array($options['headers'])) {
            $headers = array_merge($headers, $options['headers']);
        }

        $args = array(
            'method' => $method,
            'timeout' => isset($options['timeout']) ? (int) $options['timeout'] : 30,
            'headers' => $headers,
        );

        if (isset($options['body'])) {
            $args['body'] = $options['body'];
        }

        $response = \wp_remote_request($url, $args);
        if (\is_wp_error($response)) {
            return $response;
        }

        $status = (int) \wp_remote_retrieve_response_code($response);
        $body = \wp_remote_retrieve_body($response);

        if ($status >= 200 && $status < 300) {
            if ($status === 204 || $body === '') {
                return array('status' => $status);
            }

            $decoded = json_decode($body, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }

            return array('status' => $status);
        }

        $message = __('La requête Microsoft Graph a échoué.', 'mj-member');
        $decodedError = json_decode($body, true);
        if (is_array($decodedError) && isset($decodedError['error']['message'])) {
            $message = (string) $decodedError['error']['message'];
        }

        return new WP_Error('mj_teams_graph_error', $message, array('status' => $status));
    }

    /**
     * @param array<string,string> $query
     */
    private function buildUrl(string $path, array $query = array()): string
    {
        $url = rtrim(self::GRAPH_BASE, '/') . '/' . ltrim($path, '/');
        if (!empty($query)) {
            $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        return $url;
    }

    private function sanitizeName($name): string
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

    /**
     * @return string|WP_Error
     */
    private static function getAccessToken()
    {
        $cached = \get_transient(self::TOKEN_TRANSIENT);
        if (is_array($cached) && isset($cached['token'], $cached['expires']) && (int) $cached['expires'] > time() + 60) {
            return (string) $cached['token'];
        }

        $tenant = Config::teamsTenantId();
        $clientId = Config::teamsClientId();
        $clientSecret = Config::teamsClientSecret();

        if ($tenant === '' || $clientId === '' || $clientSecret === '') {
            return new WP_Error('mj_teams_missing_credentials', __('Identifiants Microsoft Teams incomplets.', 'mj-member'));
        }

        $tokenEndpoint = sprintf('https://login.microsoftonline.com/%s/oauth2/v2.0/token', rawurlencode($tenant));

        $response = \wp_remote_post(
            $tokenEndpoint,
            array(
                'body' => array(
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'scope' => 'https://graph.microsoft.com/.default',
                    'grant_type' => 'client_credentials',
                ),
                'timeout' => 20,
            )
        );

        if (\is_wp_error($response)) {
            return new WP_Error('mj_teams_token_request', $response->get_error_message());
        }

        $status = (int) \wp_remote_retrieve_response_code($response);
        $body = \wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        if ($status >= 400 || !is_array($data)) {
            $message = __('Impossible d\'obtenir un jeton Microsoft Graph.', 'mj-member');
            if (isset($data['error_description']) && is_string($data['error_description'])) {
                $message = $data['error_description'];
            } elseif (isset($data['error']['message'])) {
                $message = (string) $data['error']['message'];
            }

            return new WP_Error('mj_teams_token_invalid', $message, array('status' => $status));
        }

        if (empty($data['access_token'])) {
            return new WP_Error('mj_teams_token_missing', __('Jeton Microsoft Graph absent dans la réponse.', 'mj-member'));
        }

        $expiresIn = isset($data['expires_in']) ? (int) $data['expires_in'] : 3600;
        $token = (string) $data['access_token'];

        \set_transient(self::TOKEN_TRANSIENT, array(
            'token' => $token,
            'expires' => time() + max(60, $expiresIn - 60),
        ), $expiresIn);

        return $token;
    }
}
