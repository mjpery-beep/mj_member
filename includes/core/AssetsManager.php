<?php

namespace Mj\Member\Core;

use Mj\Member\Classes\Crud\MjMembers;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Centralise l'enregistrement et l'enqueue des assets du plugin.
 */
final class AssetsManager
{
    /** @var bool */
    private static $booted = false;

    /**
     * Initialise les hooks nécessaires au chargement des assets.
     */
    public static function bootstrap(): void
    {
        if (self::$booted) {
            return;
        }

        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueueAdminAssets'));
        add_action('init', array(__CLASS__, 'registerFrontAssets'), 8);

        self::$booted = true;
    }

    /**
     * Gère l'enqueue des assets back-office.
     *
     * @param string $hook Hook courant.
     * @return void
     */
    public static function enqueueAdminAssets($hook): void
    {
        $baseUrl = trailingslashit(Config::url());
        $basePath = trailingslashit(Config::path());

        wp_enqueue_style('custom-styles', $baseUrl . 'css/styles.css');

        $utilsPath = $basePath . 'js/utils.js';
        $utilsVersion = file_exists($utilsPath) ? filemtime($utilsPath) : Config::version();
        wp_enqueue_script('mj-member-utils', $baseUrl . 'js/utils.js', array('jquery'), $utilsVersion, true);

        $inlineEditPath = $basePath . 'js/inline-edit.js';
        $inlineEditVersion = file_exists($inlineEditPath) ? filemtime($inlineEditPath) : Config::version();
        wp_enqueue_script('mj-inline-edit', $baseUrl . 'js/inline-edit.js', array('jquery', 'mj-member-utils'), $inlineEditVersion, true);

        wp_enqueue_media();
        wp_enqueue_script('mj-photo-upload', $baseUrl . 'js/photo-upload.js', array('jquery'), Config::version(), true);

        wp_localize_script('mj-inline-edit', 'mjMembers', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mj_inline_edit_nonce'),
            'allowedRoles' => MjMembers::getAllowedRoles(),
            'roleLabels' => MjMembers::getRoleLabels(),
            'statusLabels' => array(
                MjMembers::STATUS_ACTIVE => 'Actif',
                MjMembers::STATUS_INACTIVE => 'Inactif',
            ),
            'photoConsentLabels' => array(
                '1' => 'Accepté',
                '0' => 'Refusé',
            ),
            'volunteerLabels' => array(
                'yes' => __('Bénévole', 'mj-member'),
                'no' => __('Non bénévole', 'mj-member'),
            ),
            'labels' => array(
                'paymentNone' => __('Aucun paiement', 'mj-member'),
                'paymentNotRequired' => __('Non concerné', 'mj-member'),
                'cancel' => __('Annuler', 'mj-member'),
            ),
        ));

        wp_localize_script('mj-member-utils', 'mjEventsList', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mj_events_list'),
            'inlineEditNonce' => wp_create_nonce('mj_inline_edit_nonce'),
            'statusLabels' => array(
                'actif' => __('Actif', 'mj-member'),
                'brouillon' => __('Brouillon', 'mj-member'),
                'passe' => __('Passé', 'mj-member'),
            ),
            'typeLabels' => array(
                'stage' => __('Stage', 'mj-member'),
                'soiree' => __('Soirée', 'mj-member'),
                'sortie' => __('Sortie', 'mj-member'),
                'atelier' => __('Atelier', 'mj-member'),
            ),
            'labels' => array(
                'updateSuccess' => __('Mis à jour avec succès', 'mj-member'),
                'updateError' => __('Erreur lors de la mise à jour', 'mj-member'),
                'ajaxError' => __('Erreur de communication avec le serveur', 'mj-member'),
            ),
        ));

        $adminEventsTablePath = $basePath . 'js/admin-events-table.js';
        $adminEventsTableVersion = file_exists($adminEventsTablePath) ? filemtime($adminEventsTablePath) : Config::version();
        wp_enqueue_script('mj-admin-events-table', $baseUrl . 'js/admin-events-table.js', array('jquery', 'mj-member-utils'), $adminEventsTableVersion, true);

        wp_localize_script('mj-photo-upload', 'mjPhotoUpload', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mj_photo_upload_nonce'),
        ));

        $paymentsScriptPath = $basePath . 'includes/js/admin-payments.js';
        $paymentsScriptVersion = file_exists($paymentsScriptPath) ? filemtime($paymentsScriptPath) : Config::version();
        wp_enqueue_script('mj-admin-payments', $baseUrl . 'includes/js/admin-payments.js', array('jquery'), $paymentsScriptVersion, true);

        wp_localize_script('mj-admin-payments', 'mjPayments', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mj_admin_payments_nonce'),
        ));

        $userLinkPath = $basePath . 'js/member-user-link.js';
        $userLinkVersion = file_exists($userLinkPath) ? filemtime($userLinkPath) : Config::version();
        wp_enqueue_script('mj-member-user-link', $baseUrl . 'js/member-user-link.js', array('jquery', 'mj-member-utils'), $userLinkVersion, true);

        $editableRoles = function_exists('get_editable_roles') ? get_editable_roles() : array();
        $rolesForModal = array();
        foreach ($editableRoles as $roleKey => $roleData) {
            $rolesForModal[$roleKey] = translate_user_role($roleData['name']);
        }

        wp_localize_script('mj-member-user-link', 'mjMemberUsers', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mj_link_member_user'),
            'roles' => $rolesForModal,
            'i18n' => array(
                'titleCreate' => __('Créer un compte WordPress', 'mj-member'),
                'titleUpdate' => __('Mettre à jour le compte WordPress', 'mj-member'),
                'submitCreate' => __('Créer le compte', 'mj-member'),
                'submitUpdate' => __('Mettre à jour', 'mj-member'),
                'cancel' => __('Annuler', 'mj-member'),
                'passwordLabel' => __('Mot de passe généré :', 'mj-member'),
                'suggestedPasswordLabel' => __('Mot de passe suggéré :', 'mj-member'),
                'copyLabel' => __('Copier', 'mj-member'),
                'copySuccess' => __('Mot de passe copié dans le presse-papiers.', 'mj-member'),
                'roleLabel' => __('Rôle WordPress attribué', 'mj-member'),
                'roleTitleTemplate' => __('Rôle WordPress : %s', 'mj-member'),
                'accountPasswordLabel' => __('Mot de passe du compte WordPress', 'mj-member'),
                'accountPasswordHint' => __('Laissez vide pour générer un mot de passe automatique ou utilisez la suggestion sécurisée (7 caractères).', 'mj-member'),
                'suggestPassword' => __('Suggérer un mot de passe', 'mj-member'),
                'passwordSuggested' => __('Mot de passe suggéré et rempli.', 'mj-member'),
                'detailsLabel' => __('Détails', 'mj-member'),
                'accountLoginLabel' => __('Identifiant du compte WordPress', 'mj-member'),
                'accountLoginPlaceholder' => __('ex : prenom.nom', 'mj-member'),
                'accountLoginHint' => __('Choisissez un identifiant unique (lettres, chiffres, points et tirets). Laissez vide pour proposer automatiquement un identifiant.', 'mj-member'),
                'chooseRolePlaceholder' => __('Sélectionnez un rôle…', 'mj-member'),
                'successLinked' => __('Le compte WordPress est maintenant lié.', 'mj-member'),
                'errorGeneric' => __('Une erreur est survenue. Merci de réessayer.', 'mj-member'),
            ),
        ));

        if (is_string($hook) && strpos($hook, 'mj_send_emails') !== false) {
            wp_enqueue_editor();

            $sendEmailsCssPath = $basePath . 'css/admin-send-emails.css';
            $sendEmailsCssVersion = file_exists($sendEmailsCssPath) ? filemtime($sendEmailsCssPath) : Config::version();
            wp_enqueue_style('mj-member-admin-send-emails', $baseUrl . 'css/admin-send-emails.css', array(), $sendEmailsCssVersion);

            $sendEmailsJsPath = $basePath . 'js/admin-send-emails.js';
            $sendEmailsJsVersion = file_exists($sendEmailsJsPath) ? filemtime($sendEmailsJsPath) : Config::version();
            wp_enqueue_script('mj-member-admin-send-emails', $baseUrl . 'js/admin-send-emails.js', array('jquery'), $sendEmailsJsVersion, true);

            wp_localize_script('mj-member-admin-send-emails', 'mjSendEmails', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mj_send_emails'),
                'errorLoadTemplate' => __('Impossible de charger le template. Merci de réessayer.', 'mj-member'),
                'i18n' => array(
                    'prepareError' => __('Impossible de préparer l’envoi. Merci de réessayer.', 'mj-member'),
                    'sendError' => __('Une erreur est survenue pendant l’envoi.', 'mj-member'),
                    'logPending' => __('Envoi en cours…', 'mj-member'),
                    'logSent' => __('Envoyé', 'mj-member'),
                    'logFailed' => __('Échec', 'mj-member'),
                    'logSkipped' => __('Ignoré', 'mj-member'),
                    'skippedNoEmail' => __('Aucune adresse email valide pour ce membre.', 'mj-member'),
                    'summary' => __('Récapitulatif : %1$s envoyé(s), %2$s échec(s), %3$s ignoré(s).', 'mj-member'),
                    'finished' => __('Envoi terminé.', 'mj-member'),
                    'skippedTitle' => __('Destinataires ignorés (sans email) :', 'mj-member'),
                    'logEmailsLabel' => __('Email(s)', 'mj-member'),
                    'logPhonesLabel' => __('SMS', 'mj-member'),
                    'smsInlineLabel' => __('Message SMS', 'mj-member'),
                    'logErrorDetailsLabel' => __('Détails', 'mj-member'),
                    'previewShow' => __('Voir le message', 'mj-member'),
                    'previewHide' => __('Masquer le message', 'mj-member'),
                    'previewSubjectLabel' => __('Sujet', 'mj-member'),
                    'previewSmsLabel' => __('Aperçu SMS', 'mj-member'),
                    'previewSmsRenderedLabel' => __('SMS final', 'mj-member'),
                    'previewSmsRawLabel' => __('Texte saisi', 'mj-member'),
                    'statusTestMode' => __('Mode test', 'mj-member'),
                    'summaryTestMode' => __('Mode test actif : aucun email réel ne sera envoyé.', 'mj-member'),
                ),
            ));
        }
    }

    /**
     * Enregistre les assets front-office (scripts & styles).
     *
     * @return void
     */
    public static function registerFrontAssets(): void
    {
        self::registerStyle('mj-member-components', 'css/styles.css');
        wp_register_style('mj-member-events-widget-inline', false, array('mj-member-components'), Config::version());
        self::registerStyle('mj-member-event-page', 'css/event-page.css', array('mj-member-components'));
        self::registerScript('mj-member-utils', 'js/utils.js', array('jquery'));
        self::registerScript('mj-member-contact-form', 'js/contact-form.js', array('jquery'));
        self::registerScript('mj-member-contact-messages', 'js/contact-messages.js', array('jquery', 'mj-member-utils'));

        wp_localize_script('mj-member-contact-messages', 'mjMemberContactMessages', array(
            'i18n' => array(
                'required' => __('Merci de saisir un message.', 'mj-member'),
                'genericError' => __('Une erreur est survenue. Merci de réessayer.', 'mj-member'),
                'success' => __('Votre réponse a bien été envoyée.', 'mj-member'),
                'sending' => __('Envoi…', 'mj-member'),
                'submit' => __('Envoyer', 'mj-member'),
                'configError' => __('Configuration invalide pour la réponse rapide.', 'mj-member'),
                'ownerHeading' => __('Votre réponse', 'mj-member'),
                'teamHeading' => __('Réponse de l’équipe MJ', 'mj-member'),
                'badgeUnread' => __('Non lu', 'mj-member'),
            ),
        ));

        self::registerScript('mj-member-events-widget', 'js/events-widget.js', array('mj-member-utils'));
        self::registerScript('mj-member-event-page-app', 'js/event-page/app.js', array('mj-member-utils', 'mj-member-preact-hooks'));
        self::registerScript('mj-member-event-toggles', 'js/event-toggles.js');
        self::registerScript('mj-member-animateur-account', 'js/animateur-account.js', array('jquery', 'mj-member-utils'));
        self::registerScript('mj-member-events-calendar', 'js/elementor/events-calendar.js', array('mj-member-utils'));
        self::registerStyle('mj-member-events-calendar', 'css/events-calendar.css', array('mj-member-components'));
        self::registerScript('mj-member-upcoming-events', 'js/elementor/upcoming-events.js', array('mj-member-utils'));
        self::registerScript('mj-member-hour-encode', 'js/elementor/hour-encode.js', array('mj-member-utils'));
        self::registerScript('mj-member-photo-grimlins', 'js/elementor/photo-grimlins.js', array('mj-member-utils'));
        self::registerScript('mj-member-grimlins-gallery', 'js/elementor/grimlins-gallery.js', array('mj-member-utils'));
        self::registerScript('mj-member-idea-box', 'js/elementor/idea-box.js', array('mj-member-utils'));
        self::registerScript('mj-member-documents-manager', 'js/elementor/documents-manager.js', array('mj-member-utils'));
        self::registerScript('mj-member-payments-overview', 'js/elementor/payments-overview.js', array('mj-member-utils'));
        self::registerStyle('mj-member-payments-overview', 'css/payments-overview.css', array('mj-member-components'));
        self::registerScript('mj-member-events-manager', 'js/elementor/events-manager.js', array('mj-member-utils'));
        self::registerStyle('mj-member-event-form', 'css/event-form.css');
        self::registerStyle('mj-member-events-manager', 'css/events-manager.css', array('mj-member-components', 'mj-member-event-form'));
        wp_register_script(
            'mj-member-preact',
            'https://unpkg.com/preact@10.19.3/dist/preact.min.js',
            array(),
            '10.19.3',
            true
        );

        wp_register_script(
            'mj-member-preact-hooks',
            'https://unpkg.com/preact@10.19.3/hooks/dist/hooks.umd.js',
            array('mj-member-preact'),
            '10.19.3',
            true
        );
        self::registerScript('mj-member-todo-widget', 'js/elementor/todo-widget.js', array('mj-member-utils', 'mj-member-preact-hooks', 'media-editor'));

        self::registerStyle('mj-member-login-component', 'css/login-component.css');
        self::registerScript('mj-member-login-component', 'js/login-component.js');
        self::registerStyle('mj-member-hour-encode', 'css/hour-encode.css', array('mj-member-components'));
        self::registerStyle('mj-member-photo-grimlins', 'css/photo-grimlins.css', array('mj-member-components'));
        self::registerStyle('mj-member-grimlins-gallery', 'css/grimlins-gallery.css', array('mj-member-components'));
        self::registerStyle('mj-member-todo-widget', 'css/todo-widget.css', array('mj-member-components'));
        self::registerStyle('mj-member-idea-box', 'css/idea-box.css', array('mj-member-components'));
        self::registerStyle('mj-member-documents-manager', 'css/documents-manager.css', array('mj-member-components'));

        if (function_exists('register_block_type')) {
            self::registerScript(
                'mj-member-login-block-editor',
                'js/block-login-button.js',
                array('wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-i18n')
            );

            $defaultRedirect = function_exists('mj_member_get_account_redirect')
                ? mj_member_get_account_redirect()
                : home_url('/mon-compte');

            wp_localize_script(
                'mj-member-login-block-editor',
                'mjMemberLoginDefaults',
                array(
                    'redirect' => esc_url($defaultRedirect),
                )
            );
        }

        self::registerStyle('mj-member-notification-preferences', 'css/notification-preferences.css');
        self::registerScript('mj-member-notification-preferences', 'js/notification-preferences.js');

        // Registration Manager Widget
        self::registerStyle('mj-member-registration-manager', 'css/registration-manager.css', array('mj-member-components'));
        self::registerScript('mj-member-regmgr-services', 'js/registration-manager/services.js', array('mj-member-utils'));
        self::registerScript('mj-member-regmgr-hooks', 'js/registration-manager/hooks.js', array('mj-member-preact-hooks'));
        self::registerScript('mj-member-regmgr-events', 'js/registration-manager/events.js', array('mj-member-regmgr-hooks'));
        self::registerScript('mj-member-regmgr-registrations', 'js/registration-manager/registrations.js', array('mj-member-regmgr-hooks'));
        self::registerScript('mj-member-regmgr-attendance', 'js/registration-manager/attendance.js', array('mj-member-regmgr-hooks'));
        self::registerScript('mj-member-regmgr-members', 'js/registration-manager/members.js', array('mj-member-regmgr-registrations'));
        self::registerScript('mj-member-regmgr-event-editor', 'js/registration-manager/event-editor.js', array('mj-member-regmgr-hooks'));
        self::registerScript('mj-member-regmgr-modals', 'js/registration-manager/modals.js', array('mj-member-regmgr-hooks'));
        self::registerScript('mj-member-regmgr-app', 'js/registration-manager/app.js', array(
            'mj-member-regmgr-services',
            'mj-member-regmgr-hooks',
            'mj-member-regmgr-events',
            'mj-member-regmgr-registrations',
            'mj-member-regmgr-attendance',
            'mj-member-regmgr-members',
            'mj-member-regmgr-event-editor',
            'mj-member-regmgr-modals',
        ));
    }

    /**
     * Enqueue un package d’assets déclaré pour une interface donnée.
     *
     * @param string               $package Identifiant du package.
     * @param array<string, mixed> $context Données supplémentaires.
     * @return void
     */
    public static function requirePackage(string $package, array $context = array()): void
    {
        $package = strtolower($package);

        switch ($package) {
            case 'login-component':
                wp_enqueue_style('mj-member-login-component');
                wp_enqueue_script('mj-member-login-component');
                break;

            case 'contact-form':
                wp_enqueue_style('mj-member-components');
                wp_enqueue_script('mj-member-contact-form');
                break;

            case 'contact-messages':
                wp_enqueue_style('mj-member-components');
                wp_enqueue_script('mj-member-contact-messages');
                break;

            case 'events-widget':
                wp_enqueue_style('mj-member-components');
                if (function_exists('mj_member_output_events_widget_styles')) {
                    mj_member_output_events_widget_styles();
                }
                wp_enqueue_script('mj-member-events-widget');
                if (function_exists('mj_member_ensure_events_widget_localized')) {
                    mj_member_ensure_events_widget_localized();
                }
                break;

            case 'events-calendar':
                wp_enqueue_style('mj-member-events-calendar');
                wp_enqueue_script('mj-member-events-calendar');
                break;

            case 'animateur-account':
                wp_enqueue_style('mj-member-components');
                wp_enqueue_script('mj-member-animateur-account');
                break;

            case 'upcoming-events':
                wp_enqueue_style('mj-member-components');
                wp_enqueue_script('mj-member-upcoming-events');
                break;

            case 'hour-encode':
                wp_enqueue_style('mj-member-components');
                wp_enqueue_style('mj-member-hour-encode');
                wp_enqueue_script('mj-member-hour-encode');
                break;

            case 'photo-grimlins':
                wp_enqueue_style('mj-member-components');
                wp_enqueue_style('mj-member-photo-grimlins');
                wp_enqueue_script('mj-member-photo-grimlins');
                if (function_exists('mj_member_photo_grimlins_localize')) {
                    mj_member_photo_grimlins_localize();
                }
                break;

            case 'grimlins-gallery':
                wp_enqueue_style('mj-member-components');
                wp_enqueue_style('mj-member-grimlins-gallery');
                wp_enqueue_script('mj-member-grimlins-gallery');
                if (function_exists('mj_member_grimlins_gallery_localize')) {
                    mj_member_grimlins_gallery_localize();
                }
                break;

            case 'todo-widget':
                wp_enqueue_style('mj-member-components');
                wp_enqueue_style('mj-member-todo-widget');
                if (function_exists('wp_enqueue_media')) {
                    wp_enqueue_media();
                }
                wp_enqueue_script('mj-member-preact');
                wp_enqueue_script('mj-member-preact-hooks');
                wp_enqueue_script('mj-member-todo-widget');
                if (function_exists('mj_member_todo_widget_localize')) {
                    mj_member_todo_widget_localize();
                }
                break;

            case 'idea-box':
                wp_enqueue_style('mj-member-components');
                wp_enqueue_style('mj-member-idea-box');
                wp_enqueue_script('mj-member-idea-box');
                if (function_exists('mj_member_idea_box_localize')) {
                    mj_member_idea_box_localize();
                }
                break;

            case 'documents-manager':
                wp_enqueue_style('mj-member-components');
                wp_enqueue_style('mj-member-documents-manager');
                wp_enqueue_script('mj-member-documents-manager');
                if (function_exists('mj_member_documents_localize')) {
                    mj_member_documents_localize();
                }
                break;

            case 'payments-overview':
                wp_enqueue_style('mj-member-components');
                wp_enqueue_style('mj-member-payments-overview');
                wp_enqueue_script('mj-member-payments-overview');
                break;

            case 'events-manager':
                wp_enqueue_style('mj-member-components');
                wp_enqueue_style('mj-member-events-manager');
                wp_enqueue_script('mj-member-events-manager');
                break;

            case 'member-account':
                wp_enqueue_style('mj-member-components');
                break;

            case 'event-photos':
                wp_enqueue_style('mj-member-components');
                break;

            case 'event-page':
                wp_enqueue_style('mj-member-components');
                wp_enqueue_style('mj-member-event-page');
                wp_enqueue_script('mj-member-preact');
                wp_enqueue_script('mj-member-preact-hooks');
                wp_enqueue_script('mj-member-event-page-app');
                break;

            case 'notification-preferences':
                wp_enqueue_style('mj-member-notification-preferences');
                wp_enqueue_script('mj-member-notification-preferences');
                break;

            case 'registration-manager':
                wp_enqueue_style('mj-member-components');
                wp_enqueue_style('mj-member-registration-manager');
                if (function_exists('wp_enqueue_media')) {
                    wp_enqueue_media();
                }
                if (function_exists('wp_enqueue_editor')) {
                    wp_enqueue_editor();
                }
                wp_enqueue_script('mj-member-preact');
                wp_enqueue_script('mj-member-preact-hooks');
                wp_enqueue_script('mj-member-regmgr-services');
                wp_enqueue_script('mj-member-regmgr-hooks');
                wp_enqueue_script('mj-member-regmgr-events');
                wp_enqueue_script('mj-member-regmgr-registrations');
                wp_enqueue_script('mj-member-regmgr-attendance');
                wp_enqueue_script('mj-member-regmgr-members');
                wp_enqueue_script('mj-member-regmgr-event-editor');
                wp_enqueue_script('mj-member-regmgr-modals');
                wp_enqueue_script('mj-member-regmgr-app');
                break;

            default:
                /**
                 * Permet aux extensions d’ajouter leurs propres packages via un hook.
                 */
                do_action('mj_member_assets_require_package', $package, $context);
        }
    }

    /**
     * Enregistre un script (helpers internes).
     *
     * @param string $handle Handle du script.
     * @param string $relativePath Chemin relatif depuis la racine du plugin.
     * @param array<int,string> $deps Dépendances éventuelles.
     * @param bool $inFooter Chargement en pied de page (par défaut true).
     * @return void
     */
    private static function registerScript(string $handle, string $relativePath, array $deps = array(), bool $inFooter = true): void
    {
        $baseUrl = trailingslashit(Config::url());
        $basePath = trailingslashit(Config::path());

        $relative = ltrim($relativePath, '/');
        $absolutePath = $basePath . $relative;
        $src = $baseUrl . $relative;
        $version = file_exists($absolutePath) ? (string) filemtime($absolutePath) : Config::version();

        wp_register_script($handle, $src, $deps, $version, $inFooter);
    }

    /**
     * Enregistre une feuille de style.
     *
     * @param string $handle Handle du style.
     * @param string $relativePath Chemin relatif depuis la racine du plugin.
     * @param array<int,string> $deps Dépendances éventuelles.
     * @return void
     */
    private static function registerStyle(string $handle, string $relativePath, array $deps = array()): void
    {
        $baseUrl = trailingslashit(Config::url());
        $basePath = trailingslashit(Config::path());

        $relative = ltrim($relativePath, '/');
        $absolutePath = $basePath . $relative;
        $src = $baseUrl . $relative;
        $version = file_exists($absolutePath) ? (string) filemtime($absolutePath) : Config::version();

        wp_register_style($handle, $src, $deps, $version);
    }
}
