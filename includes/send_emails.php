<?php

use Mj\Member\Classes\MjRoles;

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
        <?php if (class_exists('MjSms') && MjSms::is_test_mode_enabled()) : ?>
            <div class="notice notice-success" style="padding:1px 12px;">
                <p style="margin:8px 0;"><strong><?php esc_html_e('Mode test SMS activé', 'mj-member'); ?></strong> — <?php esc_html_e('Les SMS sont simulés : aucun message ne sera envoyé tant que ce mode reste actif.', 'mj-member'); ?></p>
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
                        <label><input type="radio" name="target" value="no_wp_account"> <?php esc_html_e('Email sans compte WordPress lié', 'mj-member'); ?></label>
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
                        <li><code>{{member_email}}</code>, <code>{{member_phone}}</code>, <code>{{member_subscribe_url}}</code></li>
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
                <input type="hidden" name="test_mode" id="mj-email-test-mode" value="0">
                <button class="button button-primary" type="submit"><?php esc_html_e('Envoyer', 'mj-member'); ?></button>
                <button class="button" type="button" id="mj-send-email-test"><?php esc_html_e('Envoyer en mode test', 'mj-member'); ?></button>
            </p>
        </form>

        <div id="mj-email-progress" class="mj-email-progress mj-hidden" aria-live="polite">
            <div class="mj-email-progress-header" style="display:flex; align-items:center; justify-content:space-between; gap:12px;">
                <h2 style="margin:0;"><?php esc_html_e('Résultats de l’envoi', 'mj-member'); ?></h2>
                <button type="button" class="button mj-email-progress-stop mj-hidden" id="mj-email-stop">
                    <?php esc_html_e('Arrêter l’envoi', 'mj-member'); ?>
                </button>
            </div>
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

    if ($include_guardian && !empty($member->guardian_id) && class_exists('MjMembers')) {
        $guardian = MjMembers::getById((int) $member->guardian_id);
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
        if (!empty($member->role) && MjRoles::isTuteur($member->role)) {
            $guardian_source = $member;
        } elseif (!empty($member->guardian_id) && class_exists('MjMembers')) {
            $guardian_source = MjMembers::getById((int) $member->guardian_id);
        }

        if ($guardian_source && isset($guardian_source->id) && class_exists('MjMembers')) {
            $guardian_children = MjMembers::getChildrenForGuardian((int) $guardian_source->id);
            if (!empty($guardian_children)) {
                $context['guardian_children'] = $guardian_children;
            }
        }
    }

    return array($resolved_recipients, $context);
}

