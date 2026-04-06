<?php

namespace Mj\Member\Module {
    use Mj\Member\Core\Contracts\ModuleInterface;
    if (!defined('ABSPATH')) { exit; }

    final class DocumentsModule implements ModuleInterface {
        public function register(): void {
            add_action('wp_ajax_mj_member_documents_list', 'mj_member_documents_handle_list');
            add_action('wp_ajax_mj_member_documents_rename', 'mj_member_documents_handle_rename');
            add_action('wp_ajax_mj_member_documents_create_folder', 'mj_member_documents_handle_create_folder');
            add_action('wp_ajax_mj_member_documents_upload', 'mj_member_documents_handle_upload');
            add_action('wp_ajax_mj_member_documents_delete', 'mj_member_documents_handle_delete');
            add_action('wp_ajax_mj_member_documents_direct_edit', 'mj_member_documents_handle_direct_edit');
            add_action('wp_ajax_mj_member_documents_create_document', 'mj_member_documents_handle_create_document');
        }
    }
}

namespace {
    use Mj\Member\Classes\MjGoogleDrive;
    use Mj\Member\Classes\MjNextcloud;
    use Mj\Member\Core\Config;
    if (!defined('ABSPATH')) { exit; }

if (!function_exists('mj_member_documents_user_has_access')) {
    function mj_member_documents_user_has_access(): bool
    {
        if (!is_user_logged_in()) {
            return false;
        }

        // Extra guard: a JEUNE should never access the documents manager,
        // even if a capability was accidentally granted on the WP role.
        if (function_exists('mj_member_get_current_member') && class_exists('Mj\Member\Classes\MjRoles')) {
            $member = mj_member_get_current_member();
            $role = (is_object($member) && isset($member->role)) ? (string) $member->role : '';
            if ($role !== '' && \Mj\Member\Classes\MjRoles::isJeune($role)) {
                return false;
            }
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

if (!function_exists('mj_member_documents_get_current_member_nextcloud_credentials')) {
    /**
     * @return array{login:string,password:string}
     */
    function mj_member_documents_get_current_member_nextcloud_credentials(): array
    {
        if (!function_exists('mj_member_get_current_member')) {
            return array('login' => '', 'password' => '');
        }

        $member = mj_member_get_current_member();
        if (!is_object($member)) {
            return array('login' => '', 'password' => '');
        }

        $login = isset($member->member_nextcloud_login)
            ? sanitize_user((string) $member->member_nextcloud_login, true)
            : '';
        $password = isset($member->member_nextcloud_password)
            ? trim((string) $member->member_nextcloud_password)
            : '';

        return array(
            'login' => $login,
            'password' => $password,
        );
    }
}

if (!function_exists('mj_member_documents_make_nextcloud_client')) {
    /**
     * Prefer member-specific Nextcloud credentials when available.
     *
     * @return MjNextcloud|\WP_Error
     */
    function mj_member_documents_make_nextcloud_client()
    {
        $creds = mj_member_documents_get_current_member_nextcloud_credentials();
        if ($creds['login'] !== '' && $creds['password'] !== '') {
            return MjNextcloud::makeWithCredentials($creds['login'], $creds['password']);
        }

        // Fallback to the service account from plugin settings.
        return MjNextcloud::make();
    }
}

if (!function_exists('mj_member_documents_nextcloud_auth_mode')) {
    /**
     * @return 'member'|'service'
     */
    function mj_member_documents_nextcloud_auth_mode(): string
    {
        $creds = mj_member_documents_get_current_member_nextcloud_credentials();
        return ($creds['login'] !== '' && $creds['password'] !== '') ? 'member' : 'service';
    }
}

if (!function_exists('mj_member_documents_touch_nextcloud_last_connection')) {
    function mj_member_documents_touch_nextcloud_last_connection(): void
    {
        if (!function_exists('mj_member_get_current_member') || !class_exists('MjMembers')) {
            return;
        }

        $member = mj_member_get_current_member();
        if (!is_object($member) || empty($member->id)) {
            return;
        }

        global $wpdb;
        $table_name = MjMembers::getTableName(MjMembers::TABLE_NAME);
        $columns = $wpdb->get_col("SHOW COLUMNS FROM {$table_name}");
        if (!is_array($columns) || !in_array('nextcloud_last_connexion_date', $columns, true)) {
            return;
        }

        $wpdb->update(
            $table_name,
            array('nextcloud_last_connexion_date' => current_time('mysql')),
            array('id' => (int) $member->id),
            array('%s'),
            array('%d')
        );
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

            $nc = mj_member_documents_make_nextcloud_client();
            if (is_wp_error($nc)) {
                wp_send_json_error(array('message' => $nc->get_error_message()), 500);
            }

            $result = $nc->listFolder($folderPath);
            if (is_wp_error($result)) {
                wp_send_json_error(array('message' => $result->get_error_message()), 500);
            }

            mj_member_documents_touch_nextcloud_last_connection();

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
            $nc = mj_member_documents_make_nextcloud_client();
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
            $nc = mj_member_documents_make_nextcloud_client();
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
            $nc = mj_member_documents_make_nextcloud_client();
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

        $nc = mj_member_documents_make_nextcloud_client();
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

        $nc = mj_member_documents_make_nextcloud_client();
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

        $nc = mj_member_documents_make_nextcloud_client();
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
            'nextcloudAuthMode' => mj_member_documents_backend() === 'nextcloud'
                ? mj_member_documents_nextcloud_auth_mode()
                : '',
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

/* ------------------------------------------------------------------ *
 * Status bar helpers                                                  *
 * ------------------------------------------------------------------ */

if (!function_exists('mj_member_documents_get_nc_current_status')) {
    /**
     * Get Nextcloud connection status for the current logged-in member.
     *
     * `apiCredentialsValid` only reflects server-side API auth validity,
     * not browser iframe session state.
     *
    * @return array{login:string,lastConnection:string|null,authMode:string,apiCredentialsValid:bool,apiStatusMessage:string,browserSessionVerified:bool}
     */
    function mj_member_documents_get_nc_current_status(): array
    {
        $creds = mj_member_documents_get_current_member_nextcloud_credentials();
        $lastConnection = null;
        $apiCredentialsValid = false;
        $apiStatusMessage = '';

        if ($creds['login'] !== '' && $creds['password'] !== '') {
            $ncClient = mj_member_documents_make_nextcloud_client();
            if (is_wp_error($ncClient)) {
                $apiStatusMessage = $ncClient->get_error_message();
            } else {
                $validation = $ncClient->validateCurrentCredentials();
                $apiCredentialsValid = !empty($validation['valid']);
                if (!$apiCredentialsValid && !empty($validation['message'])) {
                    $apiStatusMessage = (string) $validation['message'];
                }
            }
        } elseif (Config::nextcloudIsReady()) {
            // Service account fallback (plugin-level credentials)
            $serviceClient = MjNextcloud::make();
            if (!is_wp_error($serviceClient)) {
                $validation = $serviceClient->validateCurrentCredentials();
                $apiCredentialsValid = !empty($validation['valid']);
                if (!$apiCredentialsValid && !empty($validation['message'])) {
                    $apiStatusMessage = (string) $validation['message'];
                }
            }
        }

        if (is_user_logged_in() && function_exists('mj_member_get_current_member')) {
            $member = mj_member_get_current_member();
            if (is_object($member) && !empty($member->nextcloud_last_connexion_date)) {
                $lastConnection = (string) $member->nextcloud_last_connexion_date;
            }
        }

        return array(
            'login'          => $creds['login'],
            'lastConnection' => $lastConnection,
            'authMode'       => ($creds['login'] !== '' && $creds['password'] !== '') ? 'member' : 'service',
            'apiCredentialsValid' => $apiCredentialsValid,
            'apiStatusMessage' => $apiStatusMessage,
            'browserSessionVerified' => false,
        );
    }
}

if (!function_exists('mj_member_documents_get_nc_members_list')) {
    /**
     * Return all members that have a Nextcloud login assigned.
     * Intended for admin member-switcher UI only.
     *
     * @return array<int,array{id:int,name:string,login:string,lastConnection:string|null}>
     */
    function mj_member_documents_get_nc_members_list(): array
    {
        global $wpdb;
        $table = MjMembers::getTableName(MjMembers::TABLE_NAME);

        // Older schemas may not yet have Nextcloud tracking columns.
        $columns = $wpdb->get_col("SHOW COLUMNS FROM {$table}");
        if (!is_array($columns) || !in_array('member_nextcloud_login', $columns, true)) {
            return array();
        }

        $firstNameColumn = in_array('first_name', $columns, true) ? 'first_name' : '';
        $lastNameColumn = in_array('last_name', $columns, true) ? 'last_name' : '';
        $hasLastConnection = in_array('nextcloud_last_connexion_date', $columns, true);

        $selectParts = array('id', 'member_nextcloud_login');
        if ($firstNameColumn !== '') {
            $selectParts[] = $firstNameColumn;
        }
        if ($lastNameColumn !== '') {
            $selectParts[] = $lastNameColumn;
        }
        if ($hasLastConnection) {
            $selectParts[] = 'nextcloud_last_connexion_date';
        }

        $orderParts = array();
        if ($lastNameColumn !== '') {
            $orderParts[] = $lastNameColumn . ' ASC';
        }
        if ($firstNameColumn !== '') {
            $orderParts[] = $firstNameColumn . ' ASC';
        }
        $orderParts[] = 'id ASC';

        $rows = $wpdb->get_results(
            sprintf(
                'SELECT %s FROM %s WHERE member_nextcloud_login IS NOT NULL AND member_nextcloud_login != "" ORDER BY %s LIMIT 300',
                implode(', ', $selectParts),
                $table,
                implode(', ', $orderParts)
            ),
            ARRAY_A
        );

        if (!is_array($rows)) {
            return array();
        }

        $list = array();
        foreach ($rows as $row) {
            $first = trim((string) ($row['first_name'] ?? ''));
            $last  = trim((string) ($row['last_name'] ?? ''));
            $name  = trim($first . ' ' . $last);
            if ($name === '') {
                $name = (string) $row['member_nextcloud_login'];
            }
            $list[] = array(
                'id'             => (int) $row['id'],
                'name'           => $name,
                'login'          => (string) $row['member_nextcloud_login'],
                'lastConnection' => isset($row['nextcloud_last_connexion_date']) && $row['nextcloud_last_connexion_date'] !== ''
                    ? (string) $row['nextcloud_last_connexion_date']
                    : null,
            );
        }

        return $list;
    }
}
} // end namespace {
