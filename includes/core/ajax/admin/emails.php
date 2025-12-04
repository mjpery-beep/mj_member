<?php

use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

// Admin AJAX handlers dedicated to bulk and single email flows.
add_action('wp_ajax_mj_get_email_template', 'mj_member_get_email_template_callback');
add_action('wp_ajax_mj_member_prepare_email_send', 'mj_member_prepare_email_send_callback');
add_action('wp_ajax_mj_member_send_single_email', 'mj_member_send_single_email_callback');

function mj_member_get_email_template_callback() {
    $capability = Config::capability();

    if (!current_user_can($capability)) {
        wp_send_json_error(array('message' => __('Accès refusé.', 'mj-member')), 403);
    }

    check_ajax_referer('mj_send_emails', 'nonce');

    $template_id = isset($_POST['template_id']) ? sanitize_text_field(wp_unslash($_POST['template_id'])) : '';
    if ($template_id === '') {
        wp_send_json_error(array('message' => __('Template introuvable.', 'mj-member')));
    }

    $template = MjMail::get_template_by($template_id);
    if (!$template) {
        wp_send_json_error(array('message' => __('Template introuvable.', 'mj-member')));
    }

    $subject = isset($template->subject) ? $template->subject : (isset($template->sujet) ? $template->sujet : '');
    $content = isset($template->content) ? $template->content : (isset($template->text) ? $template->text : '');
    $sms_content = isset($template->sms_content) ? sanitize_textarea_field($template->sms_content) : '';

    wp_send_json_success(array(
        'subject' => $subject,
        'content' => $content,
        'sms_content' => $sms_content,
    ));
}

function mj_member_prepare_email_send_callback() {
    check_ajax_referer('mj_send_emails', 'nonce');

    $capability = Config::capability();

    if (!current_user_can($capability)) {
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
                $expiration_days = apply_filters('mj_member_payment_expiration_days', Config::paymentExpirationDays());
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
        $label     = mj_member_format_send_label($member);

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

function mj_member_send_single_email_callback() {
    check_ajax_referer('mj_send_emails', 'nonce');

    $capability = Config::capability();

    if (!current_user_can($capability)) {
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
    $label = mj_member_format_send_label($member);

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
        'preview' => null,
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
            $rendered_sms_body = '';
            if (!empty($sms_delivery['rendered_message'])) {
                $rendered_sms_body = (string) $sms_delivery['rendered_message'];
            } elseif ($sms_body !== '') {
                $rendered_sms_body = $sms_body;
            }
            if ($rendered_sms_body !== '') {
                $sms_result['preview'] = array(
                    'body' => $rendered_sms_body,
                    'raw' => $sms_body,
                    'testMode' => $sms_result['testMode'],
                );
            }

            if (!empty($sms_delivery['success'])) {
                $sms_result['status'] = 'sent';
                $sms_result['message'] = !empty($sms_delivery['message']) ? (string) $sms_delivery['message'] : __('SMS envoyé.', 'mj-member');
            } else {
                $sms_result['status'] = 'failed';
                $error_message = !empty($sms_delivery['error']) ? (string) $sms_delivery['error'] : __('Impossible d’envoyer le SMS.', 'mj-member');
                $sms_result['message'] = $error_message;
                $sms_result['errors'][] = $error_message;

                $error_details = array();
                if (!empty($sms_delivery['error_details']) && is_array($sms_delivery['error_details'])) {
                    $error_details = $sms_delivery['error_details'];
                } elseif (!empty($sms_delivery['errors']) && is_array($sms_delivery['errors'])) {
                    $error_details = $sms_delivery['errors'];
                }

                if (!empty($error_details)) {
                    foreach ($error_details as $detail) {
                        $detail = trim((string) $detail);
                        if ($detail === '') {
                            continue;
                        }
                        if (!in_array($detail, $sms_result['errors'], true)) {
                            $sms_result['errors'][] = $detail;
                        }
                    }
                }
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
    if (!empty($combined_errors)) {
        $combined_errors = array_values(array_unique(array_map('strval', $combined_errors)));
    }
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
        'smsPreview' => $sms_result['preview'],
        'testMode'  => ($email_result['testMode'] || $sms_result['testMode']),
        'channels'  => array(
            'email' => $email_result['status'],
            'sms' => $sms_result['status'],
        ),
    ));
}

function mj_member_format_send_label($member) {
    if (!is_object($member)) {
        return '';
    }

    $name_parts = array();
    if (!empty($member->last_name)) {
        $name_parts[] = (string) $member->last_name;
    }
    if (!empty($member->first_name)) {
        $name_parts[] = (string) $member->first_name;
    }

    if (!empty($name_parts)) {
        return trim(implode(' ', $name_parts));
    }

    $member_id = isset($member->id) ? (int) $member->id : 0;
    return $member_id > 0 ? sprintf(__('Membre #%d', 'mj-member'), $member_id) : __('Membre', 'mj-member');
}
