<?php

use Mj\Member\Classes\Crud\MjDocumentFolders;
use Mj\Member\Classes\Crud\MjDocuments;
use Mj\Member\Classes\Crud\MjMembers;
use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

const MJ_MEMBER_DOCUMENT_MANAGER_NONCE = 'mj_member_document_manager';

if (!function_exists('mj_member_documents_user_has_access')) {
    function mj_member_documents_user_has_access(): bool
    {
        if (!is_user_logged_in()) {
            return false;
        }

        if (current_user_can('manage_options')) {
            return true;
        }

        $capability = Config::documentsCapability();
        if ($capability !== '' && current_user_can($capability)) {
            return true;
        }

        if (!class_exists(MjMembers::class)) {
            return false;
        }

        $member = function_exists('mj_member_get_current_member') ? mj_member_get_current_member() : null;
        if (!$member || !is_object($member)) {
            return false;
        }

        $role = isset($member->role) ? sanitize_key((string) $member->role) : '';
        return $role === MjMembers::ROLE_ANIMATEUR;
    }
}

if (!function_exists('mj_member_documents_require_access_or_die')) {
    function mj_member_documents_require_access_or_die(): void
    {
        if (!mj_member_documents_user_has_access()) {
            wp_send_json_error(
                array(
                    'message' => __('Accès refusé.', 'mj-member'),
                ),
                403
            );
        }
    }
}

if (!function_exists('mj_member_documents_get_root_paths')) {
    /**
     * @return array{path:string,url:string}
     */
    function mj_member_documents_get_root_paths(): array
    {
        $uploads = wp_upload_dir();
        $baseDir = trailingslashit($uploads['basedir']) . 'mj-member/user-document';
        $baseUrl = trailingslashit($uploads['baseurl']) . 'mj-member/user-document';

        if (!is_dir($baseDir)) {
            wp_mkdir_p($baseDir);
        }

        return array(
            'path' => $baseDir,
            'url' => $baseUrl,
        );
    }
}

if (!function_exists('mj_member_documents_get_folder_directory')) {
    function mj_member_documents_get_folder_directory(int $folderId): string
    {
        $root = mj_member_documents_get_root_paths();
        if ($folderId <= 0) {
            return $root['path'];
        }

        $subdir = trailingslashit($root['path']) . 'folder-' . $folderId;
        if (!is_dir($subdir)) {
            wp_mkdir_p($subdir);
        }

        return $subdir;
    }
}

if (!function_exists('mj_member_documents_relative_path')) {
    function mj_member_documents_relative_path(string $absolutePath): string
    {
        $root = mj_member_documents_get_root_paths();
        $baseDir = $root['path'];
        $absolutePath = wp_normalize_path($absolutePath);
        $baseDir = wp_normalize_path($baseDir);

        if (strpos($absolutePath, $baseDir) === 0) {
            $relative = ltrim(substr($absolutePath, strlen($baseDir)), '/');
            return $relative;
        }

        return ltrim($absolutePath, '/');
    }
}

if (!function_exists('mj_member_documents_build_url')) {
    function mj_member_documents_build_url(string $relativePath): string
    {
        $relativePath = MjDocuments::sanitize_relative_path($relativePath);
        if ($relativePath === '') {
            return '';
        }

        $root = mj_member_documents_get_root_paths();
        $url = trailingslashit($root['url']) . $relativePath;
        return esc_url_raw($url);
    }
}

if (!function_exists('mj_member_documents_prepare_document_payload')) {
    /**
     * @param array<string,mixed> $document
     * @return array<string,mixed>
     */
    function mj_member_documents_prepare_document_payload(array $document): array
    {
        $payload = $document;
        $relative = isset($document['filename']) ? (string) $document['filename'] : '';
        $payload['url'] = mj_member_documents_build_url($relative);
        $payload['sizeLabel'] = isset($document['size']) ? size_format((int) $document['size']) : '';
        $payload['downloadName'] = isset($document['originalName']) && $document['originalName'] !== ''
            ? $document['originalName']
            : (isset($document['title']) ? $document['title'] : 'document');
        return $payload;
    }
}

if (!function_exists('mj_member_documents_prepare_response')) {
    /**
     * @return array<string,mixed>
     */
    function mj_member_documents_prepare_response(): array
    {
        $folders = MjDocumentFolders::get_all(array(
            'orderby' => 'position',
            'order' => 'ASC',
        ));
        $documents = MjDocuments::get_all(array(
            'orderby' => 'created_at',
            'order' => 'DESC',
        ));

        $documents = array_map('mj_member_documents_prepare_document_payload', $documents);

        $counts = array();
        foreach ($documents as $document) {
            $folderId = isset($document['folderId']) ? (int) $document['folderId'] : 0;
            if (!isset($counts[$folderId])) {
                $counts[$folderId] = 0;
            }
            $counts[$folderId] += 1;
        }

        foreach ($folders as &$folder) {
            $folderId = isset($folder['id']) ? (int) $folder['id'] : 0;
            $folder['documentCount'] = $counts[$folderId] ?? 0;
        }
        unset($folder);

        return array(
            'folders' => $folders,
            'documents' => $documents,
            'limits' => array(
                'maxUploadBytes' => wp_max_upload_size(),
                'allowedMimeTypes' => array_keys(get_allowed_mime_types()),
            ),
        );
    }
}

if (!function_exists('mj_member_document_manager_localize')) {
    function mj_member_document_manager_localize(): void
    {
        static $localized = false;
        if ($localized) {
            return;
        }

        if (!wp_script_is('mj-member-document-manager', 'enqueued')) {
            return;
        }

        $config = array(
            'ajaxUrl' => esc_url_raw(admin_url('admin-ajax.php')),
            'nonce' => wp_create_nonce(MJ_MEMBER_DOCUMENT_MANAGER_NONCE),
            'actions' => array(
                'fetch' => 'mj_member_documents_list',
                'createFolder' => 'mj_member_documents_folder_create',
                'updateFolder' => 'mj_member_documents_folder_update',
                'deleteFolder' => 'mj_member_documents_folder_delete',
                'upload' => 'mj_member_documents_upload',
                'updateDocument' => 'mj_member_documents_update',
                'deleteDocument' => 'mj_member_documents_delete',
            ),
            'i18n' => array(
                'loading' => __('Chargement des documents…', 'mj-member'),
                'empty' => __('Aucun document pour le moment.', 'mj-member'),
                'fetchError' => __('Impossible de récupérer les documents.', 'mj-member'),
                'createFolderError' => __('Impossible de créer le dossier.', 'mj-member'),
                'updateFolderError' => __('Impossible de mettre à jour le dossier.', 'mj-member'),
                'deleteFolderError' => __('Impossible de supprimer le dossier.', 'mj-member'),
                'uploadError' => __('Téléversement impossible.', 'mj-member'),
                'updateDocumentError' => __('Impossible de mettre à jour le document.', 'mj-member'),
                'deleteDocumentError' => __('Impossible de supprimer le document.', 'mj-member'),
                'confirmDeleteDocument' => __('Confirmez-vous la suppression de ce document ?', 'mj-member'),
                'confirmDeleteFolder' => __('Ce dossier doit être vide pour être supprimé. Confirmez-vous ?', 'mj-member'),
                'rootFolder' => __('Racine', 'mj-member'),
                'uploading' => __('Téléversement en cours…', 'mj-member'),
                'folderCreated' => __('Dossier créé.', 'mj-member'),
                'folderUpdated' => __('Dossier mis à jour.', 'mj-member'),
                'folderDeleted' => __('Dossier supprimé.', 'mj-member'),
                'documentUploaded' => __('Document ajouté.', 'mj-member'),
                'documentUpdated' => __('Document mis à jour.', 'mj-member'),
                'documentDeleted' => __('Document supprimé.', 'mj-member'),
            ),
            'icons' => mj_member_documents_available_icons(),
            'hasAccess' => mj_member_documents_user_has_access(),
        );

        wp_localize_script('mj-member-document-manager', 'mjMemberDocumentManager', $config);
        $localized = true;
    }
}

if (!function_exists('mj_member_documents_available_icons')) {
    /**
     * @return array<int,array{value:string,label:string}>
     */
    function mj_member_documents_available_icons(): array
    {
        $icons = array('folder', 'media-document', 'media-archive', 'media-spreadsheet', 'portfolio', 'images-alt2', 'welcome-learn-more', 'analytics', 'format-aside');
        $labels = array(
            'folder' => __('Dossier', 'mj-member'),
            'media-document' => __('Document', 'mj-member'),
            'media-archive' => __('Archive', 'mj-member'),
            'media-spreadsheet' => __('Tableur', 'mj-member'),
            'portfolio' => __('Portfolio', 'mj-member'),
            'images-alt2' => __('Galerie', 'mj-member'),
            'welcome-learn-more' => __('Ressource', 'mj-member'),
            'analytics' => __('Rapport', 'mj-member'),
            'format-aside' => __('Note', 'mj-member'),
        );

        $options = array();
        foreach ($icons as $icon) {
            $options[] = array(
                'value' => sanitize_key($icon),
                'label' => $labels[$icon] ?? ucfirst(str_replace('-', ' ', $icon)),
            );
        }

        return $options;
    }
}

if (!function_exists('mj_member_documents_handle_list')) {
    function mj_member_documents_handle_list(): void
    {
        check_ajax_referer(MJ_MEMBER_DOCUMENT_MANAGER_NONCE, 'nonce');
        mj_member_documents_require_access_or_die();

        $payload = mj_member_documents_prepare_response();
        wp_send_json_success($payload);
    }
    add_action('wp_ajax_mj_member_documents_list', 'mj_member_documents_handle_list');
}

if (!function_exists('mj_member_documents_handle_folder_create')) {
    function mj_member_documents_handle_folder_create(): void
    {
        check_ajax_referer(MJ_MEMBER_DOCUMENT_MANAGER_NONCE, 'nonce');
        mj_member_documents_require_access_or_die();

        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash((string) $_POST['name'])) : '';
        $parentId = isset($_POST['parent_id']) ? (int) $_POST['parent_id'] : 0;
        $color = isset($_POST['color']) ? sanitize_hex_color((string) $_POST['color']) : '';
        $icon = isset($_POST['icon']) ? sanitize_key((string) $_POST['icon']) : '';

        $result = MjDocumentFolders::create(array(
            'name' => $name,
            'parent_id' => $parentId,
            'color' => $color,
            'icon' => $icon,
        ));

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        $folderId = (int) $result;
        $folders = MjDocumentFolders::get_all(array('include_ids' => array($folderId)));
        $folder = !empty($folders) ? $folders[0] : array();
        wp_send_json_success(array(
            'folder' => $folder,
        ));
    }
    add_action('wp_ajax_mj_member_documents_folder_create', 'mj_member_documents_handle_folder_create');
}

if (!function_exists('mj_member_documents_handle_folder_update')) {
    function mj_member_documents_handle_folder_update(): void
    {
        check_ajax_referer(MJ_MEMBER_DOCUMENT_MANAGER_NONCE, 'nonce');
        mj_member_documents_require_access_or_die();

        $folderId = isset($_POST['folder_id']) ? (int) $_POST['folder_id'] : 0;
        $payload = array();

        if (isset($_POST['name'])) {
            $payload['name'] = sanitize_text_field(wp_unslash((string) $_POST['name']));
        }
        if (isset($_POST['parent_id'])) {
            $payload['parent_id'] = (int) $_POST['parent_id'];
        }
        if (isset($_POST['color'])) {
            $color = sanitize_hex_color((string) $_POST['color']);
            $payload['color'] = $color === null ? '' : $color;
        }
        if (isset($_POST['icon'])) {
            $payload['icon'] = sanitize_key((string) $_POST['icon']);
        }

        $result = MjDocumentFolders::update($folderId, $payload);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        $folders = MjDocumentFolders::get_all(array('include_ids' => array($folderId)));
        $folder = !empty($folders) ? $folders[0] : array();
        wp_send_json_success(array('folder' => $folder));
    }
    add_action('wp_ajax_mj_member_documents_folder_update', 'mj_member_documents_handle_folder_update');
}

if (!function_exists('mj_member_documents_handle_folder_delete')) {
    function mj_member_documents_handle_folder_delete(): void
    {
        check_ajax_referer(MJ_MEMBER_DOCUMENT_MANAGER_NONCE, 'nonce');
        mj_member_documents_require_access_or_die();

        $folderId = isset($_POST['folder_id']) ? (int) $_POST['folder_id'] : 0;
        $result = MjDocumentFolders::delete($folderId);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success();
    }
    add_action('wp_ajax_mj_member_documents_folder_delete', 'mj_member_documents_handle_folder_delete');
}

if (!function_exists('mj_member_documents_handle_upload')) {
    function mj_member_documents_handle_upload(): void
    {
        check_ajax_referer(MJ_MEMBER_DOCUMENT_MANAGER_NONCE, 'nonce');
        mj_member_documents_require_access_or_die();

        if (!isset($_FILES['file'])) {
            wp_send_json_error(array('message' => __('Aucun fichier reçu.', 'mj-member')));
        }

        $folderId = isset($_POST['folder_id']) ? (int) $_POST['folder_id'] : 0;
        if (!function_exists('wp_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $root = mj_member_documents_get_root_paths();
        $subdir = $folderId > 0 ? '/folder-' . $folderId : '';

        $override = static function ($dirs) use ($root, $subdir) {
            $path = trailingslashit($root['path']) . ltrim($subdir, '/');
            $url = trailingslashit($root['url']) . ltrim($subdir, '/');

            if (!is_dir($path)) {
                wp_mkdir_p($path);
            }

            return array(
                'path' => $path,
                'url' => $url,
                'subdir' => $subdir,
                'basedir' => $root['path'],
                'baseurl' => $root['url'],
                'error' => false,
            );
        };

        add_filter('upload_dir', $override, 50);
        $uploaded = wp_handle_upload($_FILES['file'], array('test_form' => false));
        remove_filter('upload_dir', $override, 50);

        if (isset($uploaded['error'])) {
            wp_send_json_error(array('message' => $uploaded['error']));
        }

        $filePath = isset($uploaded['file']) ? (string) $uploaded['file'] : '';
        if ($filePath === '') {
            wp_send_json_error(array('message' => __('Téléversement invalide.', 'mj-member')));
        }

        $relativePath = mj_member_documents_relative_path($filePath);
        $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash((string) $_POST['title'])) : '';
        if ($title === '') {
            $title = pathinfo($filePath, PATHINFO_FILENAME);
        }

        $result = MjDocuments::create(array(
            'folder_id' => $folderId,
            'title' => $title,
            'filename' => $relativePath,
            'original_name' => isset($_FILES['file']['name']) ? sanitize_text_field((string) $_FILES['file']['name']) : $title,
            'mime_type' => isset($uploaded['type']) ? sanitize_text_field((string) $uploaded['type']) : '',
            'size_bytes' => isset($_FILES['file']['size']) ? (int) $_FILES['file']['size'] : filesize($filePath),
        ));

        if (is_wp_error($result)) {
            @unlink($filePath);
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        $document = MjDocuments::get((int) $result);
        if (!$document) {
            wp_send_json_error(array('message' => __('Document introuvable après enregistrement.', 'mj-member')));
        }

        wp_send_json_success(array('document' => mj_member_documents_prepare_document_payload($document)));
    }
    add_action('wp_ajax_mj_member_documents_upload', 'mj_member_documents_handle_upload');
}

if (!function_exists('mj_member_documents_handle_update')) {
    function mj_member_documents_handle_update(): void
    {
        check_ajax_referer(MJ_MEMBER_DOCUMENT_MANAGER_NONCE, 'nonce');
        mj_member_documents_require_access_or_die();

        $documentId = isset($_POST['document_id']) ? (int) $_POST['document_id'] : 0;
        $payload = array();

        if (isset($_POST['title'])) {
            $payload['title'] = sanitize_text_field(wp_unslash((string) $_POST['title']));
        }
        if (isset($_POST['folder_id'])) {
            $payload['folder_id'] = (int) $_POST['folder_id'];
        }
        if (isset($_POST['status'])) {
            $payload['status'] = sanitize_key((string) $_POST['status']);
        }

        $result = MjDocuments::update($documentId, $payload);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        $document = MjDocuments::get($documentId);
        if (!$document) {
            wp_send_json_error(array('message' => __('Document introuvable.', 'mj-member')));
        }

        wp_send_json_success(array('document' => mj_member_documents_prepare_document_payload($document)));
    }
    add_action('wp_ajax_mj_member_documents_update', 'mj_member_documents_handle_update');
}

if (!function_exists('mj_member_documents_handle_delete')) {
    function mj_member_documents_handle_delete(): void
    {
        check_ajax_referer(MJ_MEMBER_DOCUMENT_MANAGER_NONCE, 'nonce');
        mj_member_documents_require_access_or_die();

        $documentId = isset($_POST['document_id']) ? (int) $_POST['document_id'] : 0;
        $document = MjDocuments::get($documentId);
        if (!$document) {
            wp_send_json_error(array('message' => __('Document introuvable.', 'mj-member')));
        }

        $relative = isset($document['filename']) ? (string) $document['filename'] : '';
        $absolute = '';
        if ($relative !== '') {
            $root = mj_member_documents_get_root_paths();
            $absolute = trailingslashit($root['path']) . $relative;
        }

        $result = MjDocuments::delete($documentId);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        if ($absolute !== '' && is_file($absolute)) {
            @unlink($absolute);
        }

        wp_send_json_success();
    }
    add_action('wp_ajax_mj_member_documents_delete', 'mj_member_documents_handle_delete');
}
