<?php

use Mj\Member\Classes\MjGoogleDrive;
use Mj\Member\Classes\MjNextcloud;
use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('mj_member_documents_user_has_access')) {
    function mj_member_documents_user_has_access(): bool
    {
        if (!is_user_logged_in()) {
            return false;
        }

        $capability = Config::documentsCapability();
        if ($capability === '') {
            return false;
        }

        return current_user_can($capability);
    }
}

if (!function_exists('mj_member_documents_is_configured')) {
    function mj_member_documents_is_configured(): bool
    {
        // Nextcloud takes priority, then Google Drive fallback
        if (Config::nextcloudIsReady()) {
            return true;
        }
        return Config::googleDriveIsReady() && Config::googleDriveRootFolderId() !== '';
    }
}

if (!function_exists('mj_member_documents_backend')) {
    /**
     * Determine which storage backend is active.
     *
     * @return 'nextcloud'|'google'|''
     */
    function mj_member_documents_backend(): string
    {
        if (Config::nextcloudIsReady()) {
            return 'nextcloud';
        }
        if (Config::googleDriveIsReady() && Config::googleDriveRootFolderId() !== '') {
            return 'google';
        }
        return '';
    }
}

if (!function_exists('mj_member_documents_require_access')) {
    function mj_member_documents_require_access(): void
    {
        if (!mj_member_documents_user_has_access()) {
            wp_send_json_error(
                array('message' => __('Accès refusé.', 'mj-member')),
                403
            );
        }
    }
}

if (!function_exists('mj_member_documents_require_configuration')) {
    function mj_member_documents_require_configuration(): void
    {
        if (!mj_member_documents_is_configured()) {
            wp_send_json_error(
                array('message' => __('Le stockage de documents n\'est pas configuré (Nextcloud ou Google Drive).', 'mj-member')),
                503
            );
        }
    }
}

if (!function_exists('mj_member_documents_verify_nonce')) {
    function mj_member_documents_verify_nonce(): void
    {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if ($nonce === '' || !wp_verify_nonce($nonce, 'mj_member_documents_widget')) {
            wp_send_json_error(
                array('message' => __('Action non autorisée (nonce invalide).', 'mj-member')),
                403
            );
        }
    }
}

if (!function_exists('mj_member_documents_normalize_files')) {
    /**
     * @param array<string,mixed> $input
     * @return array<int,array<string,mixed>>
     */
    function mj_member_documents_normalize_files(array $input): array
    {
        if (empty($input)) {
            return array();
        }

        $normalized = array();

        if (!isset($input['name'])) {
            return $normalized;
        }

        if (!is_array($input['name'])) {
            return array($input);
        }

        $count = count($input['name']);
        for ($index = 0; $index < $count; $index++) {
            $normalized[] = array(
                'name' => $input['name'][$index] ?? '',
                'type' => $input['type'][$index] ?? '',
                'tmp_name' => $input['tmp_name'][$index] ?? '',
                'error' => $input['error'][$index] ?? 0,
                'size' => $input['size'][$index] ?? 0,
            );
        }

        return $normalized;
    }
}

if (!function_exists('mj_member_documents_handle_list')) {
    function mj_member_documents_handle_list(): void
    {
        mj_member_documents_verify_nonce();
        mj_member_documents_require_access();
        mj_member_documents_require_configuration();

        $backend = mj_member_documents_backend();

        if ($backend === 'nextcloud') {
            $folderPath = isset($_POST['folderId']) ? sanitize_text_field(wp_unslash($_POST['folderId'])) : '';
            if ($folderPath === '') {
                $folderPath = Config::nextcloudRootFolder();
            }

            $nc = MjNextcloud::make();
            if (is_wp_error($nc)) {
                wp_send_json_error(array('message' => $nc->get_error_message()), 500);
            }

            $result = $nc->listFolder($folderPath);
            if (is_wp_error($result)) {
                wp_send_json_error(array('message' => $result->get_error_message()), 500);
            }

            wp_send_json_success($result);
        }

        // Fallback: Google Drive
        $folderId = isset($_POST['folderId']) ? sanitize_text_field(wp_unslash($_POST['folderId'])) : '';

        $drive = MjGoogleDrive::make();
        if (is_wp_error($drive)) {
            wp_send_json_error(array('message' => $drive->get_error_message()), 500);
        }

        $result = $drive->listFolder($folderId);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 500);
        }

        wp_send_json_success($result);
    }
}

if (!function_exists('mj_member_documents_handle_rename')) {
    function mj_member_documents_handle_rename(): void
    {
        mj_member_documents_verify_nonce();
        mj_member_documents_require_access();
        mj_member_documents_require_configuration();

        $itemId = isset($_POST['itemId']) ? sanitize_text_field(wp_unslash($_POST['itemId'])) : '';
        $name = isset($_POST['name']) ? wp_unslash($_POST['name']) : '';
        $name = is_string($name) ? wp_strip_all_tags($name) : '';

        if ($itemId === '' || $name === '') {
            wp_send_json_error(array('message' => __('Merci de fournir un nom valide.', 'mj-member')), 400);
        }

        $backend = mj_member_documents_backend();

        if ($backend === 'nextcloud') {
            $nc = MjNextcloud::make();
            if (is_wp_error($nc)) {
                wp_send_json_error(array('message' => $nc->get_error_message()), 500);
            }
            $result = $nc->rename($itemId, $name);
            if (is_wp_error($result)) {
                wp_send_json_error(array('message' => $result->get_error_message()), 500);
            }
            wp_send_json_success($result);
        }

        // Fallback: Google Drive
        $drive = MjGoogleDrive::make();
        if (is_wp_error($drive)) {
            wp_send_json_error(array('message' => $drive->get_error_message()), 500);
        }

        $result = $drive->rename($itemId, $name);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 500);
        }

        wp_send_json_success($result);
    }
}

if (!function_exists('mj_member_documents_handle_create_folder')) {
    function mj_member_documents_handle_create_folder(): void
    {
        mj_member_documents_verify_nonce();
        mj_member_documents_require_access();
        mj_member_documents_require_configuration();

        $parentId = isset($_POST['parentId']) ? sanitize_text_field(wp_unslash($_POST['parentId'])) : '';
        $name = isset($_POST['name']) ? wp_unslash($_POST['name']) : '';
        $name = is_string($name) ? wp_strip_all_tags($name) : '';

        if ($name === '') {
            wp_send_json_error(array('message' => __('Merci d\'indiquer un nom de dossier.', 'mj-member')), 400);
        }

        $backend = mj_member_documents_backend();

        if ($backend === 'nextcloud') {
            if ($parentId === '') {
                $parentId = Config::nextcloudRootFolder();
            }
            $nc = MjNextcloud::make();
            if (is_wp_error($nc)) {
                wp_send_json_error(array('message' => $nc->get_error_message()), 500);
            }
            $result = $nc->createFolder($parentId, $name);
            if (is_wp_error($result)) {
                wp_send_json_error(array('message' => $result->get_error_message()), 500);
            }
            wp_send_json_success($result);
        }

        // Fallback: Google Drive
        $drive = MjGoogleDrive::make();
        if (is_wp_error($drive)) {
            wp_send_json_error(array('message' => $drive->get_error_message()), 500);
        }

        $result = $drive->createFolder($parentId, $name);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 500);
        }

        wp_send_json_success($result);
    }
}

if (!function_exists('mj_member_documents_handle_upload')) {
    function mj_member_documents_handle_upload(): void
    {
        mj_member_documents_verify_nonce();
        mj_member_documents_require_access();
        mj_member_documents_require_configuration();

        $parentId = isset($_POST['parentId']) ? sanitize_text_field(wp_unslash($_POST['parentId'])) : '';

        $fileBatches = array();
        if (isset($_FILES['files'])) {
            $fileBatches = mj_member_documents_normalize_files($_FILES['files']);
        } elseif (isset($_FILES['file'])) {
            $single = mj_member_documents_normalize_files($_FILES['file']);
            $fileBatches = array_merge($fileBatches, $single);
        }

        if (empty($fileBatches)) {
            wp_send_json_error(array('message' => __('Aucun fichier reçu.', 'mj-member')), 400);
        }

        // Filter out files with upload errors.
        $validFiles = array();
        foreach ($fileBatches as $fileEntry) {
            $errorCode = isset($fileEntry['error']) ? (int) $fileEntry['error'] : 0;
            if ($errorCode === UPLOAD_ERR_OK) {
                $validFiles[] = $fileEntry;
                continue;
            }

            if ($errorCode === UPLOAD_ERR_INI_SIZE || $errorCode === UPLOAD_ERR_FORM_SIZE) {
                wp_send_json_error(
                    array('message' => __('Fichier trop volumineux.', 'mj-member')),
                    400
                );
            }
        }

        if (empty($validFiles)) {
            wp_send_json_error(array('message' => __('Aucun fichier valide à téléverser.', 'mj-member')), 400);
        }

        $backend = mj_member_documents_backend();

        if ($backend === 'nextcloud') {
            if ($parentId === '') {
                $parentId = Config::nextcloudRootFolder();
            }
            $nc = MjNextcloud::make();
            if (is_wp_error($nc)) {
                wp_send_json_error(array('message' => $nc->get_error_message()), 500);
            }
            $result = $nc->uploadFiles($parentId, $validFiles);
            if (is_wp_error($result)) {
                wp_send_json_error(array('message' => $result->get_error_message()), 500);
            }
            wp_send_json_success(array('items' => $result));
        }

        // Fallback: Google Drive
        $drive = MjGoogleDrive::make();
        if (is_wp_error($drive)) {
            wp_send_json_error(array('message' => $drive->get_error_message()), 500);
        }

        $result = $drive->uploadFiles($parentId, $validFiles);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 500);
        }

        wp_send_json_success(array('items' => $result));
    }
}

add_action('wp_ajax_mj_member_documents_list', 'mj_member_documents_handle_list');
add_action('wp_ajax_mj_member_documents_rename', 'mj_member_documents_handle_rename');
add_action('wp_ajax_mj_member_documents_create_folder', 'mj_member_documents_handle_create_folder');
add_action('wp_ajax_mj_member_documents_upload', 'mj_member_documents_handle_upload');

/* ------------------------------------------------------------------ *
 * Nextcloud-specific AJAX endpoints                                  *
 * ------------------------------------------------------------------ */

if (!function_exists('mj_member_documents_handle_delete')) {
    function mj_member_documents_handle_delete(): void
    {
        mj_member_documents_verify_nonce();
        mj_member_documents_require_access();
        mj_member_documents_require_configuration();

        $itemId = isset($_POST['itemId']) ? sanitize_text_field(wp_unslash($_POST['itemId'])) : '';
        if ($itemId === '') {
            wp_send_json_error(array('message' => __('Identifiant manquant.', 'mj-member')), 400);
        }

        $backend = mj_member_documents_backend();
        if ($backend !== 'nextcloud') {
            wp_send_json_error(array('message' => __('Suppression non disponible avec ce backend.', 'mj-member')), 400);
        }

        $nc = MjNextcloud::make();
        if (is_wp_error($nc)) {
            wp_send_json_error(array('message' => $nc->get_error_message()), 500);
        }

        $result = $nc->delete($itemId);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 500);
        }

        wp_send_json_success(array('deleted' => $itemId));
    }
}

if (!function_exists('mj_member_documents_handle_direct_edit')) {
    /**
     * Return a one-time direct-editing URL for Collabora / OnlyOffice.
     */
    function mj_member_documents_handle_direct_edit(): void
    {
        mj_member_documents_verify_nonce();
        mj_member_documents_require_access();
        mj_member_documents_require_configuration();

        $filePath = isset($_POST['filePath']) ? sanitize_text_field(wp_unslash($_POST['filePath'])) : '';
        if ($filePath === '') {
            wp_send_json_error(array('message' => __('Chemin du fichier manquant.', 'mj-member')), 400);
        }

        $backend = mj_member_documents_backend();
        if ($backend !== 'nextcloud') {
            wp_send_json_error(array('message' => __('L\'édition directe n\'est disponible qu\'avec Nextcloud.', 'mj-member')), 400);
        }

        $nc = MjNextcloud::make();
        if (is_wp_error($nc)) {
            wp_send_json_error(array('message' => $nc->get_error_message()), 500);
        }

        $result = $nc->getDirectEditUrl($filePath);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 500);
        }

        wp_send_json_success($result);
    }
}

if (!function_exists('mj_member_documents_handle_create_document')) {
    /**
     * Create a new empty document (docx/xlsx/pptx/odt/etc.) on Nextcloud.
     */
    function mj_member_documents_handle_create_document(): void
    {
        mj_member_documents_verify_nonce();
        mj_member_documents_require_access();
        mj_member_documents_require_configuration();

        $parentId = isset($_POST['parentId']) ? sanitize_text_field(wp_unslash($_POST['parentId'])) : '';
        $name = isset($_POST['name']) ? wp_unslash($_POST['name']) : '';
        $name = is_string($name) ? wp_strip_all_tags($name) : '';

        if ($name === '') {
            wp_send_json_error(array('message' => __('Nom du document manquant.', 'mj-member')), 400);
        }

        $backend = mj_member_documents_backend();
        if ($backend !== 'nextcloud') {
            wp_send_json_error(array('message' => __('Création de document non disponible avec ce backend.', 'mj-member')), 400);
        }

        if ($parentId === '') {
            $parentId = Config::nextcloudRootFolder();
        }

        $nc = MjNextcloud::make();
        if (is_wp_error($nc)) {
            wp_send_json_error(array('message' => $nc->get_error_message()), 500);
        }

        $result = $nc->createDocument($parentId, $name);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 500);
        }

        wp_send_json_success($result);
    }
}

add_action('wp_ajax_mj_member_documents_delete', 'mj_member_documents_handle_delete');
add_action('wp_ajax_mj_member_documents_direct_edit', 'mj_member_documents_handle_direct_edit');
add_action('wp_ajax_mj_member_documents_create_document', 'mj_member_documents_handle_create_document');

if (!function_exists('mj_member_documents_localize')) {
    function mj_member_documents_localize(): void
    {
        static $localized = false;
        if ($localized) {
            return;
        }

        if (!wp_script_is('mj-member-documents-manager', 'enqueued')) {
            return;
        }

        $config = array(
            'ajaxUrl' => esc_url_raw(admin_url('admin-ajax.php')),
            'nonce' => wp_create_nonce('mj_member_documents_widget'),
            'backend' => mj_member_documents_backend(),
            'actions' => array(
                'list' => 'mj_member_documents_list',
                'rename' => 'mj_member_documents_rename',
                'createFolder' => 'mj_member_documents_create_folder',
                'upload' => 'mj_member_documents_upload',
                'delete' => 'mj_member_documents_delete',
                'directEdit' => 'mj_member_documents_direct_edit',
                'createDocument' => 'mj_member_documents_create_document',
            ),
            'hasAccess' => mj_member_documents_user_has_access(),
            'isConfigured' => mj_member_documents_is_configured(),
            'rootFolderId' => mj_member_documents_backend() === 'nextcloud'
                ? Config::nextcloudRootFolder()
                : Config::googleDriveRootFolderId(),
            'maxUploadSize' => wp_max_upload_size(),
            'i18n' => array(
                'loading' => __('Chargement des documents...', 'mj-member'),
                'empty' => __('Aucun fichier dans ce dossier.', 'mj-member'),
                'open' => __('Ouvrir', 'mj-member'),
                'edit' => __('Modifier', 'mj-member'),
                'rename' => __('Renommer', 'mj-member'),
                'delete' => __('Supprimer', 'mj-member'),
                'download' => __('Télécharger', 'mj-member'),
                'renamePrompt' => __('Nouveau nom du fichier/dossier :', 'mj-member'),
                'createFolderPrompt' => __('Nom du nouveau dossier :', 'mj-member'),
                'createFolder' => __('Nouveau dossier', 'mj-member'),
                'createDocument' => __('Nouveau document', 'mj-member'),
                'createDocumentPrompt' => __('Nom du document (ex: rapport.docx) :', 'mj-member'),
                'upload' => __('Téléverser', 'mj-member'),
                'uploadInProgress' => __('Televersement en cours...', 'mj-member'),
                'noAccess' => __('Vous n\'avez pas les droits pour accéder à ces documents.', 'mj-member'),
                'notConfigured' => __('Le stockage de documents n\'est pas encore configuré.', 'mj-member'),
                'errorGeneric' => __('Une erreur est survenue. Merci de réessayer.', 'mj-member'),
                'breadcrumbRoot' => __('Dossier racine', 'mj-member'),
                'confirmDelete' => __('Supprimer « %s » ? Cette action est irréversible.', 'mj-member'),
                'editingTitle' => __('Édition du document', 'mj-member'),
                'closeEditor' => __('Fermer l\'éditeur', 'mj-member'),
                'editorNotAvailable' => __('L\'éditeur n\'est pas disponible. Vérifiez que Collabora ou OnlyOffice est installé sur Nextcloud.', 'mj-member'),
                'newDocx' => __('Document texte (.docx)', 'mj-member'),
                'newXlsx' => __('Tableur (.xlsx)', 'mj-member'),
                'newPptx' => __('Présentation (.pptx)', 'mj-member'),
                'newOdt' => __('Document texte (.odt)', 'mj-member'),
                'newMd' => __('Note Markdown (.md)', 'mj-member'),
            ),
        );

        /**
         * Permet de filtrer la configuration transmise au widget documents.
         *
         * @param array<string,mixed> $config
         */
        $config = apply_filters('mj_member_documents_widget_config', $config);

        wp_localize_script('mj-member-documents-manager', 'mjMemberDocuments', $config);
        $localized = true;
    }
}
