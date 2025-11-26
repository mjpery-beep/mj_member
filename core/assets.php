<?php

if (!defined('ABSPATH')) {
    exit;
}

function mj_custom_admin_styles($hook)
{
    wp_enqueue_style('custom-styles', MJ_MEMBER_URL . 'css/styles.css');

    $inline_edit_path = MJ_MEMBER_PATH . 'js/inline-edit.js';
    $inline_edit_version = file_exists($inline_edit_path) ? filemtime($inline_edit_path) : '1.0.0';
    wp_enqueue_script('mj-inline-edit', MJ_MEMBER_URL . 'js/inline-edit.js', array('jquery'), $inline_edit_version, true);
    wp_enqueue_media();
    wp_enqueue_script('mj-photo-upload', MJ_MEMBER_URL . 'js/photo-upload.js', array('jquery'), '1.0.0', true);
    wp_localize_script('mj-inline-edit', 'mjMembers', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('mj_inline_edit_nonce'),
        'allowedRoles' => MjMembers_CRUD::getAllowedRoles(),
        'roleLabels' => MjMembers_CRUD::getRoleLabels(),
        'statusLabels' => array(
            MjMembers_CRUD::STATUS_ACTIVE => 'Actif',
            MjMembers_CRUD::STATUS_INACTIVE => 'Inactif',
        ),
        'photoConsentLabels' => array(
            '1' => 'Accepté',
            '0' => 'Refusé',
        ),
        'labels' => array(
            'paymentNone' => __('Aucun paiement', 'mj-member'),
            'paymentNotRequired' => __('Non concerné', 'mj-member'),
        ),
    ));
    wp_localize_script('mj-photo-upload', 'mjPhotoUpload', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('mj_photo_upload_nonce')
    ));

    $payments_js_path = MJ_MEMBER_PATH . 'includes/js/admin-payments.js';
    $payments_js_version = file_exists($payments_js_path) ? filemtime($payments_js_path) : '1.0.1';
    wp_enqueue_script('mj-admin-payments', MJ_MEMBER_URL . 'includes/js/admin-payments.js', array('jquery'), $payments_js_version, true);
    wp_localize_script('mj-admin-payments', 'mjPayments', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('mj_admin_payments_nonce')
    ));

    $user_link_js_path = MJ_MEMBER_PATH . 'js/member-user-link.js';
    $user_link_js_version = file_exists($user_link_js_path) ? filemtime($user_link_js_path) : MJ_MEMBER_VERSION;
    wp_enqueue_script('mj-member-user-link', MJ_MEMBER_URL . 'js/member-user-link.js', array('jquery'), $user_link_js_version, true);

    $editable_roles = function_exists('get_editable_roles') ? get_editable_roles() : array();
    $roles_for_modal = array();
    foreach ($editable_roles as $role_key => $role_data) {
        $roles_for_modal[$role_key] = translate_user_role($role_data['name']);
    }

    wp_localize_script('mj-member-user-link', 'mjMemberUsers', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('mj_link_member_user'),
        'roles' => $roles_for_modal,
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

        $send_emails_css_path = MJ_MEMBER_PATH . 'css/admin-send-emails.css';
        $send_emails_css_version = file_exists($send_emails_css_path) ? filemtime($send_emails_css_path) : MJ_MEMBER_VERSION;
        wp_enqueue_style('mj-member-admin-send-emails', MJ_MEMBER_URL . 'css/admin-send-emails.css', array(), $send_emails_css_version);

        $send_emails_js_path = MJ_MEMBER_PATH . 'js/admin-send-emails.js';
        $send_emails_js_version = file_exists($send_emails_js_path) ? filemtime($send_emails_js_path) : MJ_MEMBER_VERSION;
        wp_enqueue_script('mj-member-admin-send-emails', MJ_MEMBER_URL . 'js/admin-send-emails.js', array('jquery'), $send_emails_js_version, true);

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
                'previewShow' => __('Voir le message', 'mj-member'),
                'previewHide' => __('Masquer le message', 'mj-member'),
                'previewSubjectLabel' => __('Sujet', 'mj-member'),
                'statusTestMode' => __('Mode test', 'mj-member'),
                'summaryTestMode' => __('Mode test actif : aucun email réel ne sera envoyé.', 'mj-member'),
            ),
        ));
    }
}
add_action('admin_enqueue_scripts', 'mj_custom_admin_styles');
