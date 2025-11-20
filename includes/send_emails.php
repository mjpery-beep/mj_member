<?php
function mj_send_emails_page() {
    global $wpdb;

    $templates_table = $wpdb->prefix . 'mj_email_templates';
    $members_table   = $wpdb->prefix . 'mj_members';

    $templates = $wpdb->get_results("SELECT * FROM $templates_table ORDER BY id DESC");
    $members   = $wpdb->get_results("SELECT id, first_name, last_name, email FROM $members_table ORDER BY last_name ASC, first_name ASC");

    $selected_template    = !empty($templates) ? $templates[0] : null;
    $selected_template_id = $selected_template ? (string) $selected_template->id : '';
    $subject              = $selected_template ? sanitize_text_field($selected_template->subject ?? '') : '';
    $content              = $selected_template ? wp_kses_post($selected_template->content ?? '') : '';
    $sms_body             = $selected_template && isset($selected_template->sms_content) ? (string) $selected_template->sms_content : '';
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Envoyer des emails', 'mj-member'); ?></h1>
        <p class="mj-helper-text"><?php esc_html_e('Choisissez un modèle comme point de départ, personnalisez le contenu puis sélectionnez vos destinataires (un membre précis ou un segment complet). Les champs ci-dessous reflètent toujours le message qui sera envoyé.', 'mj-member'); ?></p>
        <?php if (MjMail::is_test_mode_enabled()) : ?>
            <div class="notice notice-warning" style="padding:1px 12px;">
                <p style="margin:8px 0;"><strong><?php esc_html_e('Mode test email activé', 'mj-member'); ?></strong> — <?php esc_html_e('Les envois sont simulés : aucun email ne partira tant que ce mode reste actif.', 'mj-member'); ?></p>
            </div>
        <?php endif; ?>

        <form method="post" class="mj-send-email-form" id="mj-send-email-form">
            <?php wp_nonce_field('mj_send_emails_nonce'); ?>

            <fieldset class="mj-fieldset">
                <legend><?php esc_html_e('Point de départ', 'mj-member'); ?></legend>
                <div class="mj-field-group">
                    <label for="mj-email-template"><?php esc_html_e('Template d’exemple', 'mj-member'); ?></label>
                    <select name="template_id" id="mj-email-template">
                        <option value=""><?php esc_html_e('Choisir un template', 'mj-member'); ?></option>
                        <?php foreach ($templates as $template) :
                            $option_value = (string) $template->id;
                            ?>
                            <option value="<?php echo esc_attr($option_value); ?>" <?php selected($selected_template_id, $option_value); ?>>
                                <?php echo esc_html($template->slug . ' — ' . $template->subject); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="mj-template-help"><?php esc_html_e('Les templates servent de base : chargez-les dans l’éditeur puis personnalisez le message avant l’envoi.', 'mj-member'); ?></p>
                </div>
                <div class="mj-template-loading mj-hidden" id="mj-template-loading" aria-hidden="true">
                    <span class="spinner is-active"></span>
                    <span><?php esc_html_e('Chargement du contenu…', 'mj-member'); ?></span>
                </div>
            </fieldset>

            <fieldset class="mj-fieldset">
                <legend><?php esc_html_e('Destinataires', 'mj-member'); ?></legend>
                <div class="mj-field-group">
                    <label for="mj-member-select"><?php esc_html_e('Envoyer à un membre précis', 'mj-member'); ?></label>
                    <select name="member_id" id="mj-member-select">
                        <option value=""><?php esc_html_e('— Sélectionner un membre —', 'mj-member'); ?></option>
                        <?php foreach ($members as $member) :
                            $label = mj_member_format_member_label($member);
                            ?>
                            <option value="<?php echo esc_attr($member->id); ?>"><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mj-field-group" id="mj-email-segments">
                    <label><?php esc_html_e('… ou choisir un segment complet', 'mj-member'); ?></label>
                    <div class="mj-choice-grid">
                        <label><input type="radio" name="target" value="all" checked> <?php esc_html_e('Tous les membres', 'mj-member'); ?></label>
                        <label><input type="radio" name="target" value="unpaid"> <?php esc_html_e('Membres n’ayant pas payé', 'mj-member'); ?></label>
                        <label><input type="radio" name="target" value="expired"> <?php esc_html_e('Cotisation expirée', 'mj-member'); ?></label>
                    </div>
                    <p class="mj-recipient-hint"><?php esc_html_e('La sélection d’un membre désactive automatiquement les segments pour éviter les erreurs.', 'mj-member'); ?></p>
                </div>
            </fieldset>

            <fieldset class="mj-fieldset">
                <legend><?php esc_html_e('Canaux d’envoi', 'mj-member'); ?></legend>
                <div class="mj-field-group mj-choice-grid">
                    <label>
                        <input type="checkbox" name="delivery_channels[]" value="email" id="mj-channel-email" checked>
                        <?php esc_html_e('Email', 'mj-member'); ?>
                    </label>
                    <label>
                        <input type="checkbox" name="delivery_channels[]" value="sms" id="mj-channel-sms">
                        <?php esc_html_e('SMS', 'mj-member'); ?>
                    </label>
                </div>
                <p class="mj-recipient-hint"><?php esc_html_e('Les SMS et newsletters respectent les préférences de chaque membre.', 'mj-member'); ?></p>
            </fieldset>

            <fieldset class="mj-fieldset">
                <legend><?php esc_html_e('Message', 'mj-member'); ?></legend>
                <div class="mj-field-group">
                    <label for="mj-email-subject"><?php esc_html_e('Sujet', 'mj-member'); ?></label>
                    <input type="text" name="mj_email_subject" id="mj-email-subject" value="<?php echo esc_attr($subject); ?>" required>
                </div>

                <div class="mj-field-group mj-email-editor-wrapper">
                    <label for="mj_email_content"><?php esc_html_e('Contenu', 'mj-member'); ?></label>
                    <?php
                    wp_editor(
                        $content,
                        'mj_email_content',
                        array(
                            'textarea_name' => 'mj_email_content',
                            'editor_height' => 340,
                            'media_buttons' => true,
                        )
                    );
                    ?>
                    <p class="mj-template-help"><?php esc_html_e('Placeholders disponibles (remplacés automatiquement) :', 'mj-member'); ?></p>
                    <ul class="mj-placeholder-list">
                        <li><code>{{member_first_name}}</code>, <code>{{member_last_name}}</code>, <code>{{member_full_name}}</code></li>
                        <li><code>{{member_email}}</code>, <code>{{member_phone}}</code></li>
                        <li><code>{{guardian_full_name}}</code>, <code>{{guardian_email}}</code>, <code>{{guardian_children_note}}</code></li>
                        <li><code>{{guardian_children_list}}</code>, <code>{{guardian_children_inline}}</code>, <code>{{guardian_children_count}}</code></li>
                        <li><code>{{payment_button}}</code>, <code>{{payment_link}}</code>, <code>{{payment_amount}}</code>, <code>{{cash_payment_note}}</code>, <code>{{payment_reference}}</code></li>
                        <li><code>{{today}}</code>, <code>{{site_name}}</code>, <code>{{site_url}}</code></li>
                    </ul>
                </div>
            </fieldset>

            <fieldset class="mj-fieldset mj-sms-fieldset mj-hidden" id="mj-sms-fieldset">
                <legend><?php esc_html_e('Message SMS', 'mj-member'); ?></legend>
                <div class="mj-field-group">
                    <label for="mj-sms-body"><?php esc_html_e('Contenu du SMS', 'mj-member'); ?></label>
                    <textarea name="mj_sms_body" id="mj-sms-body" rows="4" class="large-text" placeholder="<?php esc_attr_e('Message concis…', 'mj-member'); ?>"><?php echo esc_textarea($sms_body); ?></textarea>
                    <p class="mj-template-help"><?php esc_html_e('Placeholders utilisables :', 'mj-member'); ?> <code>{{member_first_name}}</code> <code>{{member_last_name}}</code> <code>{{site_name}}</code> <code>{{today}}</code></p>
                    <p class="mj-recipient-hint"><?php esc_html_e('Conseil : limitez-vous à 160 caractères. Les SMS sont envoyés uniquement aux membres ayant donné leur accord.', 'mj-member'); ?></p>
                </div>
            </fieldset>

            <p class="mj-actions">
                <button class="button button-primary" type="submit"><?php esc_html_e('Envoyer', 'mj-member'); ?></button>
            </p>
        </form>

        <div id="mj-email-progress" class="mj-email-progress mj-hidden" aria-live="polite">
            <h2><?php esc_html_e('Résultats de l’envoi', 'mj-member'); ?></h2>
            <div id="mj-email-progress-summary" class="mj-email-progress-summary"></div>
            <div id="mj-email-progress-log" class="mj-email-progress-log"></div>
        </div>
    </div>
    <?php
}

function mj_member_collect_email_targets($member, $include_guardian = false) {
    $emails = array();

    if (!is_object($member)) {
        return $emails;
    }

    $primary = isset($member->email) ? sanitize_email($member->email) : '';
    if ($primary && is_email($primary)) {
        $emails[] = $primary;
    }

    if ($include_guardian && !empty($member->guardian_id) && class_exists('MjMembers_CRUD')) {
        $guardian = MjMembers_CRUD::getById((int) $member->guardian_id);
        if ($guardian && !empty($guardian->email)) {
            $guardian_email = sanitize_email($guardian->email);
            if ($guardian_email && is_email($guardian_email)) {
                $emails[] = $guardian_email;
            }
        }
    }

    return array_values(array_unique($emails));
}

function mj_member_collect_sms_targets($member, array $context = array()) {
    if (!class_exists('MjSms')) {
        return array();
    }

    return MjSms::collect_targets($member, $context);
}

function mj_member_format_member_label($member) {
    if (!is_object($member)) {
        return '';
    }

    $label_parts = array();
    if (!empty($member->last_name) || !empty($member->first_name)) {
        $label_parts[] = trim($member->last_name . ' ' . $member->first_name);
    }
    if (!empty($member->email)) {
        $label_parts[] = $member->email;
    }

    if (!empty($label_parts)) {
        return implode(' — ', $label_parts);
    }

    $member_id = isset($member->id) ? (int) $member->id : 0;
    return $member_id > 0 ? sprintf(__('Membre #%d', 'mj-member'), $member_id) : __('Membre', 'mj-member');
}

function mj_member_prepare_email_send_callback() {
    check_ajax_referer('mj_send_emails', 'nonce');

    if (!current_user_can(MJ_MEMBER_CAPABILITY)) {
        wp_send_json_error(array('message' => __('Accès refusé.', 'mj-member')), 403);
    }

    global $wpdb;

    $template_identifier = sanitize_text_field(wp_unslash($_POST['template_id'] ?? ''));
    $selected_member_id  = isset($_POST['member_id']) ? intval($_POST['member_id']) : 0;
    $selected_target     = isset($_POST['target']) ? sanitize_key($_POST['target']) : 'all';
    $subject             = sanitize_text_field(wp_unslash($_POST['mj_email_subject'] ?? ''));
    $content             = wp_kses_post(wp_unslash($_POST['mj_email_content'] ?? ''));
    $channels_input      = isset($_POST['delivery_channels']) ? (array) $_POST['delivery_channels'] : array('email');
    $channels_input      = array_map('sanitize_key', $channels_input);
    $channels_input      = array_values(array_unique(array_filter($channels_input)));
    $send_email          = in_array('email', $channels_input, true);
    $send_sms            = in_array('sms', $channels_input, true);
    $sms_body_raw        = isset($_POST['mj_sms_body']) ? wp_unslash($_POST['mj_sms_body']) : '';
    $sms_body            = $send_sms ? sanitize_textarea_field($sms_body_raw) : '';

    if (!$send_email && !$send_sms) {
        wp_send_json_error(array('message' => __('Sélectionnez au moins un canal d’envoi (email ou SMS).', 'mj-member')));
    }

    if ($send_email && $subject === '') {
        wp_send_json_error(array('message' => __('Le sujet est requis pour l’envoi par email.', 'mj-member')));
    }

    if ($send_email && $content === '') {
        wp_send_json_error(array('message' => __('Le contenu de l’email est requis.', 'mj-member')));
    }

    if ($send_sms && $sms_body === '') {
        wp_send_json_error(array('message' => __('Le contenu du SMS est requis.', 'mj-member')));
    }

    $selected_template = null;
    if ($template_identifier !== '') {
        $selected_template = MjMail::get_template_by($template_identifier);
    }

    $members_table = $wpdb->prefix . 'mj_members';
    $recipients    = array();

    $allowed_targets = array('all', 'unpaid', 'expired');
    if ($selected_member_id > 0) {
        $selected_target = '';
    } elseif (!in_array($selected_target, $allowed_targets, true)) {
        wp_send_json_error(array('message' => __('Sélectionnez un segment valide.', 'mj-member')));
    }

    if ($selected_member_id > 0) {
        $member = MjMembers_CRUD::getById($selected_member_id);
        if (!$member) {
            wp_send_json_error(array('message' => __('Impossible de trouver le membre sélectionné.', 'mj-member')));
        }
        $recipients[] = $member;
    } else {
        switch ($selected_target) {
            case 'unpaid':
                $recipients = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM $members_table WHERE requires_payment = 1 AND (date_last_payement IS NULL OR date_last_payement = %s OR CAST(date_last_payement AS CHAR) = %s)",
                    '0000-00-00 00:00:00',
                    ''
                ));
                break;
            case 'expired':
                $expiration_days = apply_filters('mj_member_payment_expiration_days', MJ_MEMBER_PAYMENT_EXPIRATION_DAYS);
                $expiration_days = max(1, (int) $expiration_days);
                $cutoff_timestamp = current_time('timestamp') - ($expiration_days * DAY_IN_SECONDS);
                $cutoff_date      = gmdate('Y-m-d H:i:s', $cutoff_timestamp);
                $recipients       = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM $members_table WHERE requires_payment = 1 AND date_last_payement IS NOT NULL AND date_last_payement <> %s AND CAST(date_last_payement AS CHAR) <> %s AND date_last_payement < %s",
                    '0000-00-00 00:00:00',
                    '',
                    $cutoff_date
                ));
                break;
            case 'all':
            default:
                $recipients = $wpdb->get_results("SELECT * FROM $members_table");
                break;
        }
    }

    if (empty($recipients)) {
        wp_send_json_error(array('message' => __('Aucun destinataire ne correspond à votre sélection.', 'mj-member')));
    }

    $send_queue   = array();
    $skipped_list = array();

    foreach ($recipients as $member) {
        $member_id = isset($member->id) ? (int) $member->id : 0;
        $label     = mj_member_format_member_label($member);

        $emails = $send_email ? mj_member_collect_email_targets($member, false) : array();
        $phones = $send_sms ? mj_member_collect_sms_targets($member) : array();

        $newsletter_allowed = true;
        if ($send_email && property_exists($member, 'newsletter_opt_in')) {
            $newsletter_allowed = (int) $member->newsletter_opt_in === 1;
        }

        $sms_allowed = true;
        if ($send_sms && class_exists('MjSms')) {
            $sms_allowed = MjSms::is_allowed($member);
        }

        $email_ready = $send_email && $newsletter_allowed && !empty($emails);
        $sms_ready = $send_sms && $sms_allowed && !empty($phones);

        $channel_notes = array('email' => '', 'sms' => '');
        if ($send_email) {
            if (!$newsletter_allowed) {
                $channel_notes['email'] = __('Email ignoré : consentement newsletter retiré.', 'mj-member');
            } elseif (empty($emails)) {
                $channel_notes['email'] = __('Email ignoré : aucune adresse email valide.', 'mj-member');
            }
        }
        if ($send_sms) {
            if (!$sms_allowed) {
                $channel_notes['sms'] = __('SMS ignoré : ce membre a refusé ce canal.', 'mj-member');
            } elseif (empty($phones)) {
                $channel_notes['sms'] = __('SMS ignoré : aucun numéro de téléphone valide.', 'mj-member');
            }
        }

        if (!$email_ready && !$sms_ready) {
            $reason = trim(implode(' ', array_filter($channel_notes)));
            $skipped_list[] = array(
                'member_id' => $member_id,
                'label' => $label,
                'emails' => $emails,
                'phones' => $phones,
                'reason' => $reason !== '' ? $reason : __('Aucun canal disponible pour ce membre.', 'mj-member'),
            );
            continue;
        }

        $send_queue[] = array(
            'member_id' => $member_id,
            'label' => $label,
            'emails' => $emails,
            'phones' => $phones,
            'channels' => array(
                'email' => $email_ready ? 1 : 0,
                'sms' => $sms_ready ? 1 : 0,
            ),
            'notes' => $channel_notes,
        );
    }

    if (empty($send_queue)) {
        wp_send_json_error(array(
            'message' => __('Aucun canal valide trouvé pour les destinataires sélectionnés.', 'mj-member'),
            'skipped' => $skipped_list,
        ));
    }

    wp_send_json_success(array(
        'request' => array(
            'template_id'   => $template_identifier,
            'template_slug' => $selected_template && isset($selected_template->slug) ? $selected_template->slug : '',
            'subject'       => $subject,
            'content'       => $content,
            'channels'      => array(
                'email' => $send_email ? 1 : 0,
                'sms' => $send_sms ? 1 : 0,
            ),
            'sms_body'      => $sms_body,
        ),
        'sendQueue' => $send_queue,
        'skipped'   => $skipped_list,
        'testModeEnabled' => MjMail::is_test_mode_enabled(),
    ));
}

function mj_member_build_email_context($member, $selected_template = null) {
    $include_guardian = false;

    $resolved_recipients = mj_member_collect_email_targets($member, $include_guardian);
    if (empty($resolved_recipients)) {
        return array($resolved_recipients, array());
    }

    $context = array(
        'recipients' => $resolved_recipients,
    );

    $template_slug = '';
    if (is_object($selected_template) && !empty($selected_template->slug)) {
        $template_slug = $selected_template->slug;
    } elseif (is_string($selected_template)) {
        $template_slug = $selected_template;
    }

    if ($template_slug && class_exists('MjPayments') && in_array($template_slug, array('member_registration', 'payment_reminder'), true)) {
        $payment_info = MjPayments::create_payment_record($member->id);
        if ($payment_info) {
            if (!empty($payment_info['checkout_url'])) {
                $context['payment_link'] = $payment_info['checkout_url'];
            } elseif (!empty($payment_info['confirm_url'])) {
                $context['payment_link'] = $payment_info['confirm_url'];
            }
            if (!empty($payment_info['qr_url'])) {
                $context['payment_qr_url'] = $payment_info['qr_url'];
            }
            if (!empty($payment_info['token'])) {
                $context['payment_reference'] = $payment_info['token'];
            }
            if (isset($payment_info['amount'])) {
                $context['payment_amount'] = $payment_info['amount'];
            }
        }
    }

    if ($template_slug === 'payment_reminder' && !empty($member->date_last_payement) && $member->date_last_payement !== '0000-00-00 00:00:00') {
        $context['payment_last_date'] = $member->date_last_payement;
    }

    if (empty($context['payment_amount'])) {
        $context['payment_amount'] = get_option('mj_annual_fee', '0.00');
    }

    if (!isset($context['guardian_children']) || !is_array($context['guardian_children'])) {
        $guardian_source = null;
        if (!empty($member->role) && $member->role === MjMembers_CRUD::ROLE_TUTEUR) {
            $guardian_source = $member;
        } elseif (!empty($member->guardian_id) && class_exists('MjMembers_CRUD')) {
            $guardian_source = MjMembers_CRUD::getById((int) $member->guardian_id);
        }

        if ($guardian_source && isset($guardian_source->id) && class_exists('MjMembers_CRUD')) {
            $guardian_children = MjMembers_CRUD::getChildrenForGuardian((int) $guardian_source->id);
            if (!empty($guardian_children)) {
                $context['guardian_children'] = $guardian_children;
            }
        }
    }

    return array($resolved_recipients, $context);
}

function mj_member_send_single_email_callback() {
    check_ajax_referer('mj_send_emails', 'nonce');

    if (!current_user_can(MJ_MEMBER_CAPABILITY)) {
        wp_send_json_error(array('message' => __('Accès refusé.', 'mj-member')), 403);
    }

    $member_id           = isset($_POST['member_id']) ? intval($_POST['member_id']) : 0;
    $subject             = sanitize_text_field(wp_unslash($_POST['subject'] ?? ''));
    $content             = wp_kses_post(wp_unslash($_POST['content'] ?? ''));
    $template_identifier = sanitize_text_field(wp_unslash($_POST['template_id'] ?? ''));
    $channels_param      = isset($_POST['channels']) && is_array($_POST['channels']) ? $_POST['channels'] : array();
    $send_email          = !empty($channels_param['email']);
    $send_sms            = !empty($channels_param['sms']);
    $sms_body_raw        = isset($_POST['sms_body']) ? wp_unslash($_POST['sms_body']) : '';
    $sms_body            = $send_sms ? sanitize_textarea_field($sms_body_raw) : '';

    if ($member_id <= 0) {
        wp_send_json_error(array('message' => __('Identifiant membre manquant.', 'mj-member')));
    }

    if (!$send_email && !$send_sms) {
        wp_send_json_error(array('message' => __('Sélectionnez au moins un canal d’envoi.', 'mj-member')));
    }

    if ($send_email && ($subject === '' || $content === '')) {
        wp_send_json_error(array('message' => __('Le sujet et le contenu sont requis pour envoyer un email.', 'mj-member')));
    }

    if ($send_sms && $sms_body === '') {
        wp_send_json_error(array('message' => __('Le contenu du SMS est requis.', 'mj-member')));
    }

    $member = MjMembers_CRUD::getById($member_id);
    if (!$member) {
        wp_send_json_error(array('message' => __('Membre introuvable.', 'mj-member')));
    }

    $selected_template = null;
    if ($template_identifier !== '') {
        $selected_template = MjMail::get_template_by($template_identifier);
    }

    $template_id = 0;
    $template_slug = '';
    if (is_object($selected_template)) {
        if (!empty($selected_template->id)) {
            $template_id = (int) $selected_template->id;
        }
        if (!empty($selected_template->slug)) {
            $template_slug = (string) $selected_template->slug;
        }
    }
    if ($template_slug === '' && $template_identifier !== '') {
        $template_slug = $template_identifier;
    }

    list($resolved_recipients, $context) = mj_member_build_email_context($member, $selected_template);
    $label = mj_member_format_member_label($member);

    $email_result = array(
        'status' => $send_email ? 'pending' : 'disabled',
        'message' => '',
        'errors' => array(),
        'recipients' => array(),
        'preview' => null,
        'testMode' => false,
    );
    $sms_result = array(
        'status' => $send_sms ? 'pending' : 'disabled',
        'message' => '',
        'errors' => array(),
        'phones' => array(),
        'testMode' => false,
    );

    $context_request = array('request' => array('template_id' => $template_identifier, 'member_id' => $member_id));

    if ($send_email) {
        $newsletter_allowed = !property_exists($member, 'newsletter_opt_in') || (int) $member->newsletter_opt_in === 1;

        if (!$newsletter_allowed) {
            $email_result['status'] = 'skipped';
            $email_result['message'] = __('Email ignoré : consentement newsletter retiré.', 'mj-member');
            $email_result['recipients'] = $resolved_recipients;
            MjMail::log_email_event(array(
                'member_id' => $member_id,
                'template_id' => $template_id,
                'template_slug' => $template_slug,
                'subject' => $subject,
                'recipients' => array(),
                'status' => 'skipped',
                'is_test_mode' => false,
                'error_message' => 'newsletter_opt_out',
                'context' => $context_request,
                'source' => 'ajax_send_email',
            ));
        } elseif (empty($resolved_recipients)) {
            $email_result['status'] = 'skipped';
            $email_result['message'] = __('Aucune adresse email valide pour ce membre.', 'mj-member');
            MjMail::log_email_event(array(
                'member_id' => $member_id,
                'template_id' => $template_id,
                'template_slug' => $template_slug,
                'subject' => $subject,
                'recipients' => array(),
                'status' => 'skipped',
                'is_test_mode' => false,
                'error_message' => 'no_valid_recipients',
                'context' => $context_request,
                'source' => 'ajax_send_email',
            ));
        } else {
            $prepared = MjMail::prepare_custom_email($member, $subject, $content, $context);
            if ($prepared === false) {
                $email_result['status'] = 'failed';
                $email_result['message'] = __('Impossible de préparer cet email.', 'mj-member');
                $email_result['errors'][] = __('Impossible de préparer cet email.', 'mj-member');
                $email_result['recipients'] = $resolved_recipients;
                MjMail::log_email_event(array(
                    'member_id' => $member_id,
                    'template_id' => $template_id,
                    'template_slug' => $template_slug,
                    'subject' => $subject,
                    'recipients' => $resolved_recipients,
                    'status' => 'failed',
                    'is_test_mode' => false,
                    'error_message' => 'prepare_custom_email returned false.',
                    'context' => $context_request,
                    'source' => 'ajax_send_email',
                ));
            } else {
                $email_result['recipients'] = $prepared['recipients'];
                $email_result['preview'] = array(
                    'subject' => $prepared['subject'],
                    'html' => $prepared['message_html'],
                    'body' => $prepared['body'],
                    'testMode' => !empty($prepared['test_mode']),
                );
                $email_result['testMode'] = !empty($prepared['test_mode']);

                $mail_failures = array();
                if (!empty($prepared['test_mode'])) {
                    do_action('mj_member_email_simulated', $member, $prepared, $context);
                    $email_result['status'] = 'sent';
                    $email_result['message'] = __('Mode test actif : envoi simulé (aucun email sortant).', 'mj-member');
                    MjMail::log_email_event(array(
                        'member_id' => $member_id,
                        'template_id' => $template_id,
                        'template_slug' => $template_slug,
                        'subject' => $prepared['subject'],
                        'recipients' => $prepared['recipients'],
                        'status' => 'simulated',
                        'is_test_mode' => true,
                        'body_html' => isset($prepared['message_html']) ? $prepared['message_html'] : '',
                        'body_plain' => isset($prepared['body_plain']) ? $prepared['body_plain'] : (isset($prepared['body']) ? $prepared['body'] : ''),
                        'headers' => isset($prepared['headers']) ? $prepared['headers'] : array(),
                        'context' => $context,
                        'source' => 'ajax_send_email',
                    ));
                } else {
                    $failure_listener = function ($wp_error) use (&$mail_failures) {
                        if (is_wp_error($wp_error)) {
                            $mail_failures[] = $wp_error;
                        }
                    };
                    add_action('wp_mail_failed', $failure_listener, 10, 1);

                    $sent = true;
                    foreach ($prepared['recipients'] as $recipient) {
                        $result = wp_mail($recipient, $prepared['subject'], $prepared['message_html'], $prepared['headers']);
                        if (!$result) {
                            $sent = false;
                        }
                    }

                    remove_action('wp_mail_failed', $failure_listener, 10);

                    if ($sent) {
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            error_log(sprintf('[mj-member] Email sent for member #%d to %s (AJAX).', $member_id, implode(', ', $prepared['recipients'])));
                        }

                        $email_result['status'] = 'sent';
                        $email_result['message'] = __('Email envoyé avec succès.', 'mj-member');
                        MjMail::log_email_event(array(
                            'member_id' => $member_id,
                            'template_id' => $template_id,
                            'template_slug' => $template_slug,
                            'subject' => $prepared['subject'],
                            'recipients' => $prepared['recipients'],
                            'status' => 'sent',
                            'is_test_mode' => false,
                            'body_html' => isset($prepared['message_html']) ? $prepared['message_html'] : '',
                            'body_plain' => isset($prepared['body_plain']) ? $prepared['body_plain'] : (isset($prepared['body']) ? $prepared['body'] : ''),
                            'headers' => isset($prepared['headers']) ? $prepared['headers'] : array(),
                            'context' => $context,
                            'source' => 'ajax_send_email',
                        ));
                    } else {
                        $failure_messages = array();
                        foreach ($mail_failures as $failure) {
                            $failure_messages[] = $failure->get_error_message();
                        }
                        $failure_messages = array_filter(array_unique($failure_messages));

                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            error_log(sprintf('[mj-member] Email failed for member #%d to %s (AJAX).', $member_id, implode(', ', $prepared['recipients'])));
                            if (!empty($failure_messages)) {
                                error_log('[mj-member] Failure details: ' . implode(' | ', $failure_messages));
                            }
                        }

                        $email_result['status'] = 'failed';
                        $email_result['message'] = __('Impossible d’envoyer cet email.', 'mj-member');
                        $email_result['errors'] = $failure_messages;

                        MjMail::log_email_event(array(
                            'member_id' => $member_id,
                            'template_id' => $template_id,
                            'template_slug' => $template_slug,
                            'subject' => $prepared['subject'],
                            'recipients' => $prepared['recipients'],
                            'status' => 'failed',
                            'is_test_mode' => false,
                            'error_message' => implode(' | ', $failure_messages),
                            'body_html' => isset($prepared['message_html']) ? $prepared['message_html'] : '',
                            'body_plain' => isset($prepared['body_plain']) ? $prepared['body_plain'] : (isset($prepared['body']) ? $prepared['body'] : ''),
                            'headers' => isset($prepared['headers']) ? $prepared['headers'] : array(),
                            'context' => $context,
                            'source' => 'ajax_send_email',
                        ));
                    }
                }
            }
        }
    }

    if ($send_sms) {
        $sms_allowed = !property_exists($member, 'sms_opt_in') || (int) $member->sms_opt_in === 1;
        if (!$sms_allowed) {
            $sms_result['status'] = 'skipped';
            $sms_result['message'] = __('SMS ignoré : consentement SMS retiré.', 'mj-member');
        } else {
        $sms_context = $context;
        $sms_context['channel'] = 'sms';
        $sms_context['template_id'] = $template_id;
        $sms_context['template_slug'] = $template_slug;

        $sms_delivery = class_exists('MjSms')
            ? MjSms::send_to_member($member, $sms_body, $sms_context)
            : array('success' => false, 'phones' => array(), 'test_mode' => false, 'message' => '', 'error' => __('Service SMS indisponible.', 'mj-member'));

        $sms_result['phones'] = isset($sms_delivery['phones']) ? (array) $sms_delivery['phones'] : array();
        $sms_result['testMode'] = !empty($sms_delivery['test_mode']);

        if (!empty($sms_delivery['success'])) {
            $sms_result['status'] = 'sent';
            $sms_result['message'] = !empty($sms_delivery['message']) ? (string) $sms_delivery['message'] : __('SMS envoyé.', 'mj-member');
        } else {
            $sms_result['status'] = 'failed';
            $error_message = !empty($sms_delivery['error']) ? (string) $sms_delivery['error'] : __('Impossible d’envoyer le SMS.', 'mj-member');
            $sms_result['message'] = $error_message;
            $sms_result['errors'][] = $error_message;
        }
        }
    }

    $channel_statuses = array();
    if ($send_email) {
        $channel_statuses[] = $email_result['status'];
    }
    if ($send_sms) {
        $channel_statuses[] = $sms_result['status'];
    }

    $overall_status = 'skipped';
    if (in_array('failed', $channel_statuses, true)) {
        $overall_status = 'failed';
    } elseif (in_array('sent', $channel_statuses, true)) {
        $overall_status = 'sent';
    } elseif (in_array('skipped', $channel_statuses, true)) {
        $overall_status = 'skipped';
    }

    $combined_errors = array_merge($email_result['errors'], $sms_result['errors']);
    $combined_message = trim(implode(' ', array_filter(array($email_result['message'], $sms_result['message']))));
    if ($combined_message === '' && $overall_status === 'sent') {
        $combined_message = __('Communication envoyée.', 'mj-member');
    }
    if ($combined_message === '' && $overall_status === 'skipped') {
        $combined_message = __('Aucun canal n’a été envoyé pour ce membre.', 'mj-member');
    }

    wp_send_json_success(array(
        'member_id' => $member_id,
        'label'     => $label,
        'emails'    => $email_result['recipients'],
        'phones'    => $sms_result['phones'],
        'status'    => $overall_status,
        'message'   => $combined_message,
        'errors'    => $combined_errors,
        'preview'   => $email_result['preview'],
        'testMode'  => ($email_result['testMode'] || $sms_result['testMode']),
        'channels'  => array(
            'email' => $email_result['status'],
            'sms' => $sms_result['status'],
        ),
    ));
}
