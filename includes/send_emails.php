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

    if ($subject === '') {
        wp_send_json_error(array('message' => __('Le sujet est requis.', 'mj-member')));
    }

    if ($content === '') {
        wp_send_json_error(array('message' => __('Le contenu de l’email est requis.', 'mj-member')));
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
        $emails = mj_member_collect_email_targets($member, false);
        $label  = mj_member_format_member_label($member);
        $entry  = array(
            'member_id' => isset($member->id) ? (int) $member->id : 0,
            'label'     => $label,
            'emails'    => $emails,
        );

        if (empty($emails)) {
            $skipped_list[] = $entry;
        } else {
            $send_queue[] = $entry;
        }
    }

    if (empty($send_queue)) {
        wp_send_json_error(array(
            'message' => __('Aucun email valide trouvé pour les destinataires sélectionnés.', 'mj-member'),
            'skipped' => $skipped_list,
        ));
    }

    wp_send_json_success(array(
        'request' => array(
            'template_id'   => $template_identifier,
            'template_slug' => $selected_template && isset($selected_template->slug) ? $selected_template->slug : '',
            'subject'       => $subject,
            'content'       => $content,
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

    if ($member_id <= 0) {
        wp_send_json_error(array('message' => __('Identifiant membre manquant.', 'mj-member')));
    }

    if ($subject === '' || $content === '') {
        wp_send_json_error(array('message' => __('Le sujet et le contenu sont requis pour l’envoi.', 'mj-member')));
    }

    $member = MjMembers_CRUD::getById($member_id);
    if (!$member) {
        wp_send_json_error(array('message' => __('Membre introuvable.', 'mj-member')));
    }

    $selected_template = null;
    if ($template_identifier !== '') {
        $selected_template = MjMail::get_template_by($template_identifier);
    }

    list($resolved_recipients, $context) = mj_member_build_email_context($member, $selected_template);
    $label = mj_member_format_member_label($member);

    if (empty($resolved_recipients)) {
        wp_send_json_success(array(
            'member_id' => $member_id,
            'label'     => $label,
            'emails'    => array(),
            'status'    => 'skipped',
            'message'   => __('Aucune adresse email valide pour ce membre.', 'mj-member'),
        ));
    }

    $prepared = MjMail::prepare_custom_email($member, $subject, $content, $context);
    if ($prepared === false) {
        wp_send_json_success(array(
            'member_id' => $member_id,
            'label'     => $label,
            'emails'    => $resolved_recipients,
            'status'    => 'failed',
            'message'   => __('Impossible de préparer cet email.', 'mj-member'),
            'errors'    => array(),
        ));
    }

    $test_mode = !empty($prepared['test_mode']);
    $mail_failures = array();

    if ($test_mode) {
        do_action('mj_member_email_simulated', $member, $prepared, $context);
        $sent = true;
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
    }

    $preview = array(
        'subject' => $prepared['subject'],
        'html'    => $prepared['message_html'],
        'body'    => $prepared['body'],
        'testMode' => $test_mode,
    );

    if ($sent) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $log_recipients = implode(', ', $prepared['recipients']);
            if ($test_mode) {
                error_log(sprintf('[mj-member] Email simulation (test mode) for member #%d to %s (AJAX).', $member_id, $log_recipients));
            } else {
                error_log(sprintf('[mj-member] Email sent for member #%d to %s (AJAX).', $member_id, $log_recipients));
            }
        }

        wp_send_json_success(array(
            'member_id' => $member_id,
            'label'     => $label,
            'emails'    => $prepared['recipients'],
            'status'    => 'sent',
            'message'   => $test_mode ? __('Mode test actif : envoi simulé (aucun email sortant).', 'mj-member') : __('Email envoyé avec succès.', 'mj-member'),
            'preview'   => $preview,
            'testMode'  => $test_mode,
        ));
    }

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

    wp_send_json_success(array(
        'member_id' => $member_id,
        'label'     => $label,
        'emails'    => $prepared['recipients'],
        'status'    => 'failed',
        'message'   => __('Impossible d’envoyer cet email.', 'mj-member'),
        'errors'    => $failure_messages,
        'preview'   => $preview,
        'testMode'  => $test_mode,
    ));
}
