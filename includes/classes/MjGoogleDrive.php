<?php

namespace Mj\Member\Classes;

use Mj\Member\Core\Config;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

final class MjGoogleDrive
{
    /**
     * @var object Google\Service\Drive instance.
     */
    private $service;

    private function __construct($service)
    {
        $this->service = $service;
    }

    public static function isAvailable(): bool
    {
        return class_exists('\\Google\\Client') && class_exists('\\Google\\Service\\Drive');
    }

    /**
     * @return self|WP_Error
     */
    public static function make()
    {
        if (!self::isAvailable()) {
            return new WP_Error('mj_documents_missing_sdk', __('Le SDK Google Drive n\'est pas disponible. Installez "google/apiclient" via Composer.', 'mj-member'));
        }

        if (!Config::googleDriveIsReady()) {
            return new WP_Error('mj_documents_not_configured', __('La configuration Google Drive est incomplète.', 'mj-member'));
        }

        $json = Config::googleDriveServiceAccountJson();
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return new WP_Error('mj_documents_invalid_credentials', __('Identifiants Google Drive invalides.', 'mj-member'));
        }

        try {
            $client = new \Google\Client();
            $client->setApplicationName('MJ Member Documents');
            $client->setScopes(array(\Google\Service\Drive::DRIVE));
            $client->setAccessType('offline');
            $client->setAuthConfig($decoded);

            $impersonate = Config::googleDriveImpersonatedUser();
            if ($impersonate !== '') {
                $client->setSubject($impersonate);
            }

            $service = new \Google\Service\Drive($client);

            return new self($service);
        } catch (\Exception $exception) {
            return new WP_Error('mj_documents_client_error', $exception->getMessage());
        }
    }

    /**
     * @return array<string,mixed>|WP_Error
     */
    public function listFolder(string $folderId)
    {
        $folderId = trim($folderId);
        if ($folderId === '') {
            $folderId = Config::googleDriveRootFolderId();
        }

        if ($folderId === '') {
            return new WP_Error('mj_documents_missing_root', __('Aucun dossier Google Drive racine n\'a été défini.', 'mj-member'));
        }

        try {
            $meta = $this->service->files->get($folderId, array(
                'fields' => 'id,name,parents,mimeType,webViewLink',
                'supportsAllDrives' => true,
            ));
        } catch (\Exception $exception) {
            return new WP_Error('mj_documents_missing_folder', $exception->getMessage());
        }

        try {
            $response = $this->service->files->listFiles(array(
                'q' => sprintf('\'%s\' in parents and trashed = false', addcslashes($folderId, '\'\\')),
                'fields' => 'files(id,name,mimeType,modifiedTime,size,webViewLink,iconLink,parents)',
                'orderBy' => 'folder,name',
                'supportsAllDrives' => true,
                'includeItemsFromAllDrives' => true,
                'pageSize' => 200,
            ));
        } catch (\Exception $exception) {
            return new WP_Error('mj_documents_list_failed', $exception->getMessage());
        }

        $files = method_exists($response, 'getFiles') ? $response->getFiles() : array();
        $items = array();
        if (is_array($files)) {
            foreach ($files as $file) {
                $normalized = $this->normalizeDriveFile($file);
                if (!empty($normalized)) {
                    $items[] = $normalized;
                }
            }
        }

        return array(
            'folder' => $this->normalizeDriveFile($meta),
            'breadcrumbs' => $this->buildBreadcrumbs($meta),
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
            return new WP_Error('mj_documents_rename_invalid', __('Nom ou identifiant manquant pour le renommage.', 'mj-member'));
        }

        if (!class_exists('\\Google\\Service\\Drive\\DriveFile')) {
            return new WP_Error('mj_documents_missing_sdk', __('La classe Google DriveFile est introuvable.', 'mj-member'));
        }

        $metadata = new \Google\Service\Drive\DriveFile(array('name' => $safeName));

        try {
            $updated = $this->service->files->update($itemId, $metadata, array(
                'fields' => 'id,name,mimeType,modifiedTime,size,webViewLink,iconLink,parents',
                'supportsAllDrives' => true,
            ));
        } catch (\Exception $exception) {
            return new WP_Error('mj_documents_rename_failed', $exception->getMessage());
        }

        return $this->normalizeDriveFile($updated);
    }

    /**
     * @return array<string,mixed>|WP_Error
     */
    public function createFolder(string $parentId, string $name)
    {
        $parentId = trim($parentId);
        if ($parentId === '') {
            $parentId = Config::googleDriveRootFolderId();
        }

        $safeName = $this->sanitizeName($name);
        if ($parentId === '' || $safeName === '') {
            return new WP_Error('mj_documents_folder_invalid', __('Nom ou dossier parent manquant.', 'mj-member'));
        }

        if (!class_exists('\\Google\\Service\\Drive\\DriveFile')) {
            return new WP_Error('mj_documents_missing_sdk', __('La classe Google DriveFile est introuvable.', 'mj-member'));
        }

        $metadata = new \Google\Service\Drive\DriveFile(array(
            'name' => $safeName,
            'mimeType' => 'application/vnd.google-apps.folder',
            'parents' => array($parentId),
        ));

        try {
            $created = $this->service->files->create($metadata, array(
                'fields' => 'id,name,mimeType,modifiedTime,size,webViewLink,iconLink,parents',
                'supportsAllDrives' => true,
            ));
        } catch (\Exception $exception) {
            return new WP_Error('mj_documents_folder_failed', $exception->getMessage());
        }

        return $this->normalizeDriveFile($created);
    }

    /**
     * @return array<int,array<string,mixed>>|WP_Error
     */
    public function uploadFiles(string $parentId, array $files)
    {
        $parentId = trim($parentId);
        if ($parentId === '') {
            $parentId = Config::googleDriveRootFolderId();
        }

        if ($parentId === '') {
            return new WP_Error('mj_documents_missing_root', __('Aucun dossier cible spécifié pour l\'upload.', 'mj-member'));
        }

        if (empty($files)) {
            return new WP_Error('mj_documents_no_files', __('Aucun fichier à téléverser.', 'mj-member'));
        }

        if (!class_exists('\\Google\\Service\\Drive\\DriveFile')) {
            return new WP_Error('mj_documents_missing_sdk', __('La classe Google DriveFile est introuvable.', 'mj-member'));
        }

        $uploaded = array();

        foreach ($files as $fileEntry) {
            $tmp = isset($fileEntry['tmp_name']) ? (string) $fileEntry['tmp_name'] : '';
            $name = isset($fileEntry['name']) ? (string) $fileEntry['name'] : '';
            $type = isset($fileEntry['type']) ? (string) $fileEntry['type'] : 'application/octet-stream';

            if ($tmp === '' || !is_uploaded_file($tmp)) {
                continue;
            }

            $safeName = $this->sanitizeName($name);
            if ($safeName === '') {
                $safeName = basename($tmp);
            }

            $metadata = new \Google\Service\Drive\DriveFile(array(
                'name' => $safeName,
                'parents' => array($parentId),
            ));

            try {
                $content = file_get_contents($tmp);
                if ($content === false) {
                    continue;
                }

                $created = $this->service->files->create(
                    $metadata,
                    array(
                        'data' => $content,
                        'mimeType' => $type !== '' ? $type : 'application/octet-stream',
                        'uploadType' => 'multipart',
                        'fields' => 'id,name,mimeType,modifiedTime,size,webViewLink,iconLink,parents',
                        'supportsAllDrives' => true,
                    )
                );
            } catch (\Exception $exception) {
                return new WP_Error('mj_documents_upload_failed', $exception->getMessage());
            }

            $normalized = $this->normalizeDriveFile($created);
            if (!empty($normalized)) {
                $uploaded[] = $normalized;
            }
        }

        if (empty($uploaded)) {
            return new WP_Error('mj_documents_upload_empty', __('Le téléversement a échoué ou aucun fichier valide n\'a été fourni.', 'mj-member'));
        }

        return $uploaded;
    }

    /**
     * @param mixed $file
     * @return array<string,mixed>
     */
    private function normalizeDriveFile($file): array
    {
        if (!is_object($file) || !method_exists($file, 'getId')) {
            return array();
        }

        $mimeType = method_exists($file, 'getMimeType') ? (string) $file->getMimeType() : '';
        $isFolder = strpos($mimeType, 'application/vnd.google-apps.folder') === 0;

        $sizeRaw = method_exists($file, 'getSize') ? $file->getSize() : 0;
        $size = is_numeric($sizeRaw) ? (int) $sizeRaw : 0;

        $parents = method_exists($file, 'getParents') ? (array) $file->getParents() : array();

        $modified = method_exists($file, 'getModifiedTime') ? (string) $file->getModifiedTime() : '';
        $name = method_exists($file, 'getName') ? (string) $file->getName() : '';

        return array(
            'id' => (string) $file->getId(),
            'name' => $this->sanitizeName($name),
            'mimeType' => $mimeType,
            'type' => $isFolder ? 'folder' : 'file',
            'modifiedTime' => $modified,
            'size' => $size,
            'webViewLink' => method_exists($file, 'getWebViewLink') ? esc_url_raw((string) $file->getWebViewLink()) : '',
            'iconLink' => method_exists($file, 'getIconLink') ? esc_url_raw((string) $file->getIconLink()) : '',
            'parents' => $parents,
        );
    }

    /**
     * @param mixed $file
     * @return array<int,array<string,string>>
     */
    private function buildBreadcrumbs($file): array
    {
        $breadcrumbs = array();
        if (!is_object($file) || !method_exists($file, 'getId')) {
            return $breadcrumbs;
        }

        $rootId = Config::googleDriveRootFolderId();
        $current = $file;
        $safeGuard = 0;

        while (is_object($current) && method_exists($current, 'getId')) {
            $breadcrumbs[] = array(
                'id' => (string) $current->getId(),
                'name' => $this->sanitizeName(method_exists($current, 'getName') ? (string) $current->getName() : ''),
            );

            $parents = method_exists($current, 'getParents') ? (array) $current->getParents() : array();
            $parentId = isset($parents[0]) ? (string) $parents[0] : '';

            if ($parentId === '' || ($rootId !== '' && $current->getId() === $rootId)) {
                break;
            }

            try {
                $current = $this->service->files->get($parentId, array(
                    'fields' => 'id,name,parents',
                    'supportsAllDrives' => true,
                ));
            } catch (\Exception $exception) {
                break;
            }

            $safeGuard++;
            if ($safeGuard > 10) {
                break;
            }
        }

        return array_reverse($breadcrumbs);
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
}
