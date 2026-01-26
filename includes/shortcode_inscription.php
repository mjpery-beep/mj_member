<?php
use Mj\Member\Classes\MjRoles;

if (!function_exists('mj_member_get_registration_type')) {
    function mj_member_get_registration_type() {
        $default = 'guardian';
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registration_type'])) {
            $type = sanitize_text_field(wp_unslash($_POST['registration_type']));
            return $type === MjRoles::JEUNE ? MjRoles::JEUNE : $default;
        }
        return $default;
    }
}

if (!function_exists('mj_member_send_registration_email')) {
    function mj_member_send_registration_email($member_id, $guardian = null) {
        if (!class_exists('MjMail') || !class_exists('MjMembers')) {
            return false;
        }

        $member = is_object($member_id) ? $member_id : MjMembers::getById((int) $member_id);
        if (!$member) {
            return false;
        }

        $context = array(
            'guardian' => $guardian,
            'include_guardian' => false,
        );
        $payment_info = class_exists('MjPayments') ? MjPayments::create_payment_record($member->id) : null;
        if ($payment_info) {
            $context['payment_link'] = !empty($payment_info['checkout_url']) ? $payment_info['checkout_url'] : (!empty($payment_info['confirm_url']) ? $payment_info['confirm_url'] : '');
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
        $member_email = isset($member->email) ? $member->email : (isset($member->jeune_email) ? $member->jeune_email : '');
        $member_email = sanitize_email($member_email);
        if (is_email($member_email)) {
            $context['recipients'] = array($member_email);
        } else {
            $context['recipients'] = array();
        }

        $sent = false;
        if (!empty($context['recipients'])) {
            $sent = (bool) MjMail::send_registration_notice($member, $context);
        }

        return array(
            'member' => $member,
            'guardian' => $guardian,
            'payment_link' => isset($context['payment_link']) ? $context['payment_link'] : '',
            'payment_qr_url' => isset($context['payment_qr_url']) ? $context['payment_qr_url'] : '',
            'payment_reference' => isset($context['payment_reference']) ? $context['payment_reference'] : '',
            'payment_amount' => isset($context['payment_amount']) ? $context['payment_amount'] : '',
            'payment_amount_numeric' => isset($context['payment_amount']) ? (float) str_replace(',', '.', (string) $context['payment_amount']) : 0.0,
            'email_sent' => $sent,
        );
    }
}

if (!function_exists('mj_member_send_guardian_registration_email')) {
    function mj_member_send_guardian_registration_email($guardian, array $children_notifications) {
        if (!class_exists('MjMail') || !$guardian || !is_object($guardian)) {
            return false;
        }

        $guardian_email = isset($guardian->email) ? sanitize_email($guardian->email) : '';
        if (!is_email($guardian_email)) {
            return false;
        }

        $children = array();
        foreach ($children_notifications as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $member = isset($entry['member']) && is_object($entry['member']) ? $entry['member'] : null;
            if (!$member) {
                continue;
            }

            $children[] = array(
                'member' => $member,
                'payment_link' => isset($entry['payment_link']) ? $entry['payment_link'] : '',
                'payment_qr_url' => isset($entry['payment_qr_url']) ? $entry['payment_qr_url'] : '',
                'payment_reference' => isset($entry['payment_reference']) ? $entry['payment_reference'] : '',
                'payment_amount' => isset($entry['payment_amount']) ? $entry['payment_amount'] : '',
                'payment_amount_numeric' => isset($entry['payment_amount_numeric']) ? (float) $entry['payment_amount_numeric'] : 0.0,
            );
        }

        if (empty($children)) {
            return false;
        }

        $context = array(
            'recipients' => array($guardian_email),
            'include_guardian' => false,
            'children' => $children,
        );

        return MjMail::send_guardian_registration_notice($guardian, $context);
    }
}

if (!function_exists('mj_process_frontend_inscription')) {
    function mj_process_frontend_inscription() {
        $registration_type = mj_member_get_registration_type();
        $requires_guardian = ($registration_type !== MjRoles::JEUNE);
        $min_password_length = (int) apply_filters('mj_member_min_password_length', 8);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['mj_frontend_nonce'])) {
            return null;
        }

        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mj_frontend_nonce'])), 'mj_frontend_form')) {
            return array('success' => false, 'message' => "La vérification de sécurité a échoué. Merci de réessayer.");
        }

        if (empty($_POST['rgpd_consent'])) {
            return array('success' => false, 'message' => "Vous devez accepter le traitement de vos données personnelles.");
        }

        $regulation_required = !empty($_POST['regulation_required']);
        if ($regulation_required && empty($_POST['regulation_consent'])) {
            return array('success' => false, 'message' => "Merci de lire et d'accepter le règlement intérieur avant de poursuivre.");
        }

        $guardian_login_raw = sanitize_text_field(wp_unslash($_POST['guardian_user_login'] ?? ''));
        $guardian = array(
            'first_name'  => sanitize_text_field(wp_unslash($_POST['guardian_first_name'] ?? '')),
            'last_name'   => sanitize_text_field(wp_unslash($_POST['guardian_last_name'] ?? '')),
            'email'       => sanitize_email(wp_unslash($_POST['guardian_email'] ?? '')),
            'phone'       => sanitize_text_field(wp_unslash($_POST['guardian_phone'] ?? '')),
            'address'     => sanitize_text_field(wp_unslash($_POST['guardian_address'] ?? '')),
            'city'        => sanitize_text_field(wp_unslash($_POST['guardian_city'] ?? '')),
            'postal_code' => sanitize_text_field(wp_unslash($_POST['guardian_postal_code'] ?? '')),
            'status'      => MjMembers::STATUS_ACTIVE,
            'user_login'  => $guardian_login_raw,
            'user_password' => (string) wp_unslash($_POST['guardian_user_password'] ?? ''),
            'user_password_confirm' => (string) wp_unslash($_POST['guardian_user_password_confirm'] ?? ''),
        );

        if ($requires_guardian) {
            if ($guardian['first_name'] === '' || $guardian['last_name'] === '' || $guardian['email'] === '') {
                return array('success' => false, 'message' => "Merci de renseigner le prénom, le nom et l'adresse email du tuteur.");
            }

            if (!is_email($guardian['email'])) {
                return array('success' => false, 'message' => "L'adresse email du tuteur n'est pas valide.");
            }

            $guardian_login_clean = $guardian['user_login'] !== '' ? sanitize_user($guardian['user_login'], true) : '';
            if ($guardian_login_clean === '') {
                return array('success' => false, 'message' => "Merci d'indiquer un identifiant de connexion pour le tuteur.");
            }

            if (username_exists($guardian_login_clean)) {
                return array('success' => false, 'message' => "Cet identifiant de tuteur est déjà utilisé. Merci d'en choisir un autre.");
            }

            if ($guardian['user_password'] === '' || $guardian['user_password_confirm'] === '') {
                return array('success' => false, 'message' => "Merci de choisir un mot de passe pour le compte du tuteur.");
            }

            if ($guardian['user_password'] !== $guardian['user_password_confirm']) {
                return array('success' => false, 'message' => "La confirmation du mot de passe du tuteur ne correspond pas.");
            }

            if (strlen($guardian['user_password']) < $min_password_length) {
                return array('success' => false, 'message' => sprintf("Le mot de passe du tuteur doit contenir au moins %d caractères.", $min_password_length));
            }

            $guardian_existing_user = get_user_by('email', $guardian['email']);
            if ($guardian_existing_user) {
                return array('success' => false, 'message' => "Un compte existe déjà avec cette adresse email. Utilisez la fonction mot de passe oublié ou contactez la MJ.");
            }

            $guardian['user_login'] = $guardian_login_clean;
        }

        $children = array();
        $raw_children = isset($_POST['jeunes']) && is_array($_POST['jeunes']) ? $_POST['jeunes'] : array();

        foreach ($raw_children as $raw_child) {
            if (!is_array($raw_child)) {
                continue;
            }

            $child = array(
                'first_name'          => sanitize_text_field(wp_unslash($raw_child['first_name'] ?? '')),
                'last_name'           => sanitize_text_field(wp_unslash($raw_child['last_name'] ?? '')),
                'birth_date'          => sanitize_text_field(wp_unslash($raw_child['birth_date'] ?? '')),
                'email'               => sanitize_email(wp_unslash($raw_child['email'] ?? '')),
                'phone'               => sanitize_text_field(wp_unslash($raw_child['phone'] ?? '')),
                'is_autonomous'       => !empty($raw_child['is_autonomous']) ? 1 : 0,
                'photo_usage_consent' => !empty($raw_child['photo_usage_consent']) ? 1 : 0,
                'user_login'          => sanitize_text_field(wp_unslash($raw_child['user_login'] ?? '')),
                'user_password'       => (string) wp_unslash($raw_child['user_password'] ?? ''),
                'user_password_confirm' => (string) wp_unslash($raw_child['user_password_confirm'] ?? ''),
                'notes'               => sanitize_textarea_field(wp_unslash($raw_child['notes'] ?? '')),
            );

            $is_empty = $child['first_name'] === '' && $child['last_name'] === '' && $child['birth_date'] === '' && $child['email'] === '';
            if ($is_empty) {
                continue;
            }

            if ($child['first_name'] === '' || $child['last_name'] === '' || $child['birth_date'] === '') {
                return array('success' => false, 'message' => "Chaque jeune doit avoir un prénom, un nom et une date de naissance.");
            }

            if ($registration_type === MjRoles::JEUNE) {
                if ($child['email'] === '' || !is_email($child['email'])) {
                    return array('success' => false, 'message' => "Merci de fournir une adresse email valide pour le jeune autonome.");
                }
            }

            $needs_account = ($registration_type === MjRoles::JEUNE) || !empty($child['is_autonomous']);
            $child_login_clean = $child['user_login'] !== '' ? sanitize_user($child['user_login'], true) : '';

            if ($needs_account) {
                if ($child_login_clean === '') {
                    return array('success' => false, 'message' => sprintf("Merci d'indiquer un identifiant de connexion pour %s %s.", $child['first_name'], $child['last_name']));
                }

                if (username_exists($child_login_clean)) {
                    return array('success' => false, 'message' => sprintf("L'identifiant choisi pour %s %s est déjà utilisé. Merci d'en choisir un autre.", $child['first_name'], $child['last_name']));
                }

                if ($child['user_password'] === '' || $child['user_password_confirm'] === '') {
                    return array('success' => false, 'message' => sprintf("Merci de définir un mot de passe pour le compte de %s %s.", $child['first_name'], $child['last_name']));
                }

                if ($child['user_password'] !== $child['user_password_confirm']) {
                    return array('success' => false, 'message' => sprintf("La confirmation du mot de passe de %s %s ne correspond pas.", $child['first_name'], $child['last_name']));
                }

                if (strlen($child['user_password']) < $min_password_length) {
                    return array('success' => false, 'message' => sprintf("Le mot de passe de %s %s doit contenir au moins %d caractères.", $child['first_name'], $child['last_name'], $min_password_length));
                }

                if ($child['email'] !== '' && get_user_by('email', $child['email'])) {
                    return array('success' => false, 'message' => sprintf("Un compte existe déjà avec l'adresse email %s. Utilisez la fonction mot de passe oublié.", $child['email']));
                }
            }

            $child['user_login'] = $child_login_clean;

            $children[] = $child;
        }

        if (empty($children)) {
            return array('success' => false, 'message' => "Ajoutez au moins un jeune avant de soumettre le formulaire.");
        }

        if ($registration_type === MjRoles::JEUNE) {
            $first = $children[0];
            $first['is_autonomous'] = 1;
            $children = array($first);
        }

        $guardian_account = array(
            'login' => ($requires_guardian && !empty($guardian['user_login'])) ? $guardian['user_login'] : '',
            'password' => ($requires_guardian && !empty($guardian['user_password'])) ? $guardian['user_password'] : '',
        );

        $guardian_was_existing = false;
        if ($requires_guardian && $guardian['email'] !== '') {
            $wpdb = MjMembers::getWpdb();
            $table_name = MjMembers::getTableName(MjMembers::TABLE_NAME);
            $existing_guardian_row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT id, wp_user_id FROM $table_name WHERE email = %s AND role = %s LIMIT 1",
                    $guardian['email'],
                    MjRoles::TUTEUR
                )
            );
            if ($existing_guardian_row) {
                $guardian_was_existing = true;
            }
        }

        $guardian_id = null;
        if ($requires_guardian) {
            $guardian_id = MjMembers::upsertGuardian($guardian);
            if (is_wp_error($guardian_id)) {
                return array('success' => false, 'message' => $guardian_id->get_error_message());
            }
            if (!$guardian_id) {
                return array('success' => false, 'message' => "Impossible d'enregistrer le tuteur. Vérifiez les informations et réessayez.");
            }
        }

        $created_member_ids = array();
        $created_members = array();

        foreach ($children as $child) {
            if ($child['email'] !== '') {
                $existing = MjMembers::search($child['email']);
                if (!empty($existing)) {
                    foreach ($existing as $record) {
                        if ((int) $record->id !== (int) $guardian_id && MjRoles::isJeune($record->role)) {
                            return array('success' => false, 'message' => sprintf("L'adresse email %s est déjà utilisée pour un jeune.", esc_html($child['email'])));
                        }
                    }
                }
            }

            $payload = array(
                'first_name'          => $child['first_name'],
                'last_name'           => $child['last_name'],
                'email'               => $child['email'],
                'phone'               => $child['phone'],
                'birth_date'          => $child['birth_date'],
                'role'                => MjRoles::JEUNE,
                'guardian_id'         => ($requires_guardian && empty($child['is_autonomous'])) ? $guardian_id : null,
                'is_autonomous'       => $registration_type === MjRoles::JEUNE ? 1 : ($child['is_autonomous'] ? 1 : 0),
                'requires_payment'    => 1,
                'photo_usage_consent' => $child['photo_usage_consent'],
                'notes'               => $child['notes'],
                'status'              => MjMembers::STATUS_ACTIVE,
            );

            $member_id = MjMembers::create($payload);
            if (is_wp_error($member_id) || !$member_id) {
                $error_message = is_wp_error($member_id) ? $member_id->get_error_message() : sprintf("Impossible d'enregistrer %s %s. Veuillez réessayer.", esc_html($child['first_name']), esc_html($child['last_name']));
                foreach ($created_member_ids as $created_id) {
                    MjMembers::delete($created_id);
                }

                return array('success' => false, 'message' => $error_message);
            }

            $created_member_ids[] = $member_id;
            $created_members[] = array(
                'id' => $member_id,
                'email' => $child['email'],
                'is_autonomous' => (int) $payload['is_autonomous'],
                'credentials' => (($registration_type === MjRoles::JEUNE) || !empty($payload['is_autonomous']))
                    ? array('user_login' => $child['user_login'], 'user_password' => $child['user_password'])
                    : null,
            );
        }

        $guardian_object = null;

        if ($requires_guardian && $guardian_id) {
            $guardian_object = MjMembers::getById($guardian_id);
            if ($guardian_object) {
                $guardian_account_result = mj_member_sync_member_user_account($guardian_object, array(
                    'role' => 'subscriber',
                    'send_notification' => false,
                    'user_login' => $guardian_account['login'],
                    'user_pass' => $guardian_account['password'],
                    'return_error' => true,
                ));

                if (is_wp_error($guardian_account_result)) {
                    foreach ($created_member_ids as $created_id) {
                        MjMembers::delete($created_id);
                    }

                    if (!$guardian_was_existing && $guardian_id) {
                        MjMembers::delete($guardian_id);
                    }

                    return array('success' => false, 'message' => $guardian_account_result->get_error_message());
                }

                $guardian_object = MjMembers::getById($guardian_id);
            }
        }

        foreach ($created_members as $index => $entry) {
            if (empty($entry['credentials'])) {
                continue;
            }

            $member_record = MjMembers::getById((int) $entry['id']);
            if (!$member_record) {
                continue;
            }

            $account_result = mj_member_sync_member_user_account($member_record, array(
                'role' => 'subscriber',
                'send_notification' => false,
                'user_login' => $entry['credentials']['user_login'],
                'user_pass' => $entry['credentials']['user_password'],
                'return_error' => true,
            ));

            if (is_wp_error($account_result)) {
                foreach ($created_member_ids as $created_id) {
                    MjMembers::delete($created_id);
                }

                if ($requires_guardian && !$guardian_was_existing && $guardian_id) {
                    MjMembers::delete($guardian_id);
                }

                return array('success' => false, 'message' => $account_result->get_error_message());
            }

            $created_members[$index]['account_created'] = true;
        }

        $notify_email = get_option('mj_notify_email') ?: get_option('admin_email');
        if (!empty($notify_email)) {
            $child_lines = array();
            $child_items_html = array();
            foreach ($children as $child) {
                $line = sprintf('- %s %s (%s)', $child['first_name'], $child['last_name'], $child['birth_date']);
                $child_lines[] = $line;
                $child_items_html[] = sprintf('<li>%s</li>', esc_html(sprintf('%s %s (%s)', $child['first_name'], $child['last_name'], $child['birth_date'])));
            }

            $subject = 'Nouvelle inscription via le site MJ Péry';
            if ($requires_guardian) {
                $body    = "Un tuteur vient d'inscrire un ou plusieurs jeunes:\n\n";
                $body   .= sprintf("Tuteur: %s %s (%s)\n", $guardian['first_name'], $guardian['last_name'], $guardian['email']);
                if ($guardian['phone'] !== '') {
                    $body .= sprintf("Téléphone: %s\n", $guardian['phone']);
                }
                if ($guardian['address'] !== '' || $guardian['city'] !== '' || $guardian['postal_code'] !== '') {
                    $body .= sprintf("Adresse: %s %s %s\n", $guardian['address'], $guardian['postal_code'], $guardian['city']);
                }
                $body .= "\nJeunes:\n" . implode("\n", $child_lines) . "\n";
            } else {
                $body  = "Un jeune majeur s'est inscrit via le site:\n\n";
                $body .= implode("\n", $child_lines) . "\n";
                if (!empty($children[0]['email'])) {
                    $body .= sprintf("Email: %s\n", $children[0]['email']);
                }
                if (!empty($children[0]['phone'])) {
                    $body .= sprintf("Téléphone: %s\n", $children[0]['phone']);
                }
            }

            $registration_label = $requires_guardian
                ? __('Inscription via un tuteur', 'mj-member')
                : __('Inscription directe d\'un membre majeur', 'mj-member');

            $guardian_summary_plain = '';
            $guardian_summary_html = '';
            if ($requires_guardian) {
                $guardian_name = trim($guardian['first_name'] . ' ' . $guardian['last_name']);
                $plain_parts = array();
                if ($guardian_name !== '') {
                    $plain_parts[] = $guardian_name;
                }
                if ($guardian['email'] !== '') {
                    $plain_parts[] = $guardian['email'];
                }
                if ($guardian['phone'] !== '') {
                    $plain_parts[] = $guardian['phone'];
                }
                $guardian_address_full = trim($guardian['address'] . ' ' . $guardian['postal_code'] . ' ' . $guardian['city']);
                if ($guardian_address_full !== '') {
                    $plain_parts[] = $guardian_address_full;
                }
                if (!empty($plain_parts)) {
                    $guardian_summary_plain = implode(' | ', array_filter($plain_parts));
                }

                $html_rows = array();
                if ($guardian_name !== '') {
                    $html_rows[] = '<strong>Nom :</strong> ' . esc_html($guardian_name);
                }
                if ($guardian['email'] !== '') {
                    $html_rows[] = '<strong>Email :</strong> ' . esc_html($guardian['email']);
                }
                if ($guardian['phone'] !== '') {
                    $html_rows[] = '<strong>Téléphone :</strong> ' . esc_html($guardian['phone']);
                }
                if ($guardian_address_full !== '') {
                    $html_rows[] = '<strong>Adresse :</strong> ' . esc_html($guardian_address_full);
                }
                if (!empty($html_rows)) {
                    $guardian_summary_html = '<p>' . implode('<br>', $html_rows) . '</p>';
                }
            }

            $member_contact_plain = '';
            $member_contact_html = '';
            if (!$requires_guardian && !empty($children[0])) {
                $member_contact_name = trim($children[0]['first_name'] . ' ' . $children[0]['last_name']);
                $member_plain_parts = array();
                if ($member_contact_name !== '') {
                    $member_plain_parts[] = $member_contact_name;
                }
                if (!empty($children[0]['email'])) {
                    $member_plain_parts[] = $children[0]['email'];
                }
                if (!empty($children[0]['phone'])) {
                    $member_plain_parts[] = $children[0]['phone'];
                }
                if (!empty($member_plain_parts)) {
                    $member_contact_plain = implode(' | ', array_filter($member_plain_parts));
                }

                $member_html_rows = array();
                if ($member_contact_name !== '') {
                    $member_html_rows[] = '<strong>Membre :</strong> ' . esc_html($member_contact_name);
                }
                if (!empty($children[0]['email'])) {
                    $member_html_rows[] = '<strong>Email :</strong> ' . esc_html($children[0]['email']);
                }
                if (!empty($children[0]['phone'])) {
                    $member_html_rows[] = '<strong>Téléphone :</strong> ' . esc_html($children[0]['phone']);
                }
                if (!empty($member_html_rows)) {
                    $member_contact_html = '<p>' . implode('<br>', $member_html_rows) . '</p>';
                }
            }

            $placeholders = array(
                '{{registration_type}}' => $requires_guardian ? 'guardian' : 'member',
                '{{registration_type_label}}' => $registration_label,
                '{{children_list}}' => implode("\n", $child_lines),
                '{{children_list_html}}' => !empty($child_items_html) ? '<ul>' . implode('', $child_items_html) . '</ul>' : '',
                '{{guardian_full_name}}' => $requires_guardian ? trim($guardian['first_name'] . ' ' . $guardian['last_name']) : '',
                '{{guardian_email}}' => $requires_guardian ? $guardian['email'] : '',
                '{{guardian_phone}}' => $requires_guardian ? $guardian['phone'] : '',
                '{{guardian_address}}' => $requires_guardian ? trim($guardian['address'] . ' ' . $guardian['postal_code'] . ' ' . $guardian['city']) : '',
                '{{guardian_summary_plain}}' => $guardian_summary_plain,
                '{{guardian_summary_html}}' => $guardian_summary_html,
                '{{member_email}}' => !$requires_guardian && !empty($children[0]['email']) ? $children[0]['email'] : '',
                '{{member_phone}}' => !$requires_guardian && !empty($children[0]['phone']) ? $children[0]['phone'] : '',
                '{{member_contact_plain}}' => $member_contact_plain,
                '{{member_contact_html}}' => $member_contact_html,
            );

            MjMail::send_notification_to_emails('registration_admin_notification', array($notify_email), array(
                'placeholders' => $placeholders,
                'fallback_subject' => $subject,
                'fallback_body' => $body,
                'content_type' => 'text/plain',
                'log_source' => 'registration_admin_notification',
            ));
        }

        $ack_recipients = array();
        if ($requires_guardian && !empty($guardian['email'])) {
            $ack_recipients[] = $guardian['email'];
        } elseif (!$requires_guardian && !empty($children[0]['email'])) {
            $ack_recipients[] = $children[0]['email'];
        }

        $ack_recipients = array_values(array_unique(array_filter($ack_recipients, 'is_email')));

        if (!empty($ack_recipients)) {
            $subject = 'Votre inscription a bien été envoyée';
            $fallback_body_guardian = "Bonjour,\n\nNous avons bien reçu la pré-inscription de votre/vos jeune(s). L'équipe de la MJ Péry reviendra vers vous rapidement.\n\nÀ très vite !";
            $fallback_body_member = "Bonjour,\n\nNous avons bien reçu votre demande d'inscription. L'équipe de la MJ Péry reviendra vers vous rapidement pour finaliser votre adhésion.\n\nÀ très vite !";

            foreach ($ack_recipients as $recipient) {
                $is_guardian = $requires_guardian;
                $body = $is_guardian ? $fallback_body_guardian : $fallback_body_member;
                $placeholders = array(
                    '{{audience}}' => $is_guardian ? 'guardian' : 'member',
                    '{{recipient_email}}' => $recipient,
                    '{{guardian_full_name}}' => $requires_guardian ? trim($guardian['first_name'] . ' ' . $guardian['last_name']) : '',
                );

                MjMail::send_notification_to_emails('registration_acknowledgement', array($recipient), array(
                    'placeholders' => $placeholders,
                    'fallback_subject' => $subject,
                    'fallback_body' => $body,
                    'content_type' => 'text/plain',
                    'log_source' => 'registration_acknowledgement',
                ));
            }
        }

        $child_notifications = array();
        foreach ($created_member_ids as $created_id) {
            $notification = mj_member_send_registration_email($created_id, $guardian_object);
            if (is_array($notification)) {
                $child_notifications[] = $notification;
            }
        }

        if ($guardian_object && !empty($child_notifications)) {
            mj_member_send_guardian_registration_email($guardian_object, $child_notifications);
        }

        return array('success' => true, 'message' => "Merci ! Votre demande a bien été enregistrée. Nous reviendrons vers vous très prochainement.");
    }
}

if (!function_exists('mj_collect_guardian_form_values')) {
    function mj_collect_guardian_form_values() {
        $defaults = array(
            'first_name'  => '',
            'last_name'   => '',
            'email'       => '',
            'phone'       => '',
            'address'     => '',
            'city'        => '',
            'postal_code' => '',
            'user_login'  => '',
        );

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $defaults;
        }

        return array(
            'first_name'  => sanitize_text_field(wp_unslash($_POST['guardian_first_name'] ?? '')),
            'last_name'   => sanitize_text_field(wp_unslash($_POST['guardian_last_name'] ?? '')),
            'email'       => sanitize_email(wp_unslash($_POST['guardian_email'] ?? '')),
            'phone'       => sanitize_text_field(wp_unslash($_POST['guardian_phone'] ?? '')),
            'address'     => sanitize_text_field(wp_unslash($_POST['guardian_address'] ?? '')),
            'city'        => sanitize_text_field(wp_unslash($_POST['guardian_city'] ?? '')),
            'postal_code' => sanitize_text_field(wp_unslash($_POST['guardian_postal_code'] ?? '')),
            'user_login'  => sanitize_text_field(wp_unslash($_POST['guardian_user_login'] ?? '')),
        );
    }
}

if (!function_exists('mj_collect_children_form_values')) {
    function mj_collect_children_form_values() {
        $registration_type = mj_member_get_registration_type();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['jeunes']) || !is_array($_POST['jeunes'])) {
            return array(array(
                'first_name'          => '',
                'last_name'           => '',
                'birth_date'          => '',
                'email'               => '',
                'phone'               => '',
                'is_autonomous'       => $registration_type === MjRoles::JEUNE ? 1 : 0,
                'photo_usage_consent' => 0,
                'notes'               => '',
                'user_login'          => '',
                'user_password'       => '',
                'user_password_confirm' => '',
            ));
        }

        $children = array();
        foreach ($_POST['jeunes'] as $raw_child) {
            if (!is_array($raw_child)) {
                continue;
            }

            $children[] = array(
                'first_name'          => sanitize_text_field(wp_unslash($raw_child['first_name'] ?? '')),
                'last_name'           => sanitize_text_field(wp_unslash($raw_child['last_name'] ?? '')),
                'birth_date'          => sanitize_text_field(wp_unslash($raw_child['birth_date'] ?? '')),
                'email'               => sanitize_email(wp_unslash($raw_child['email'] ?? '')),
                'phone'               => sanitize_text_field(wp_unslash($raw_child['phone'] ?? '')),
                'is_autonomous'       => ($registration_type === MjRoles::JEUNE) ? 1 : (!empty($raw_child['is_autonomous']) ? 1 : 0),
                'photo_usage_consent' => !empty($raw_child['photo_usage_consent']) ? 1 : 0,
                'description_courte'  => sanitize_text_field(wp_unslash($raw_child['description_courte'] ?? '')),
                'description_longue'  => sanitize_textarea_field(wp_unslash($raw_child['description_longue'] ?? '')),
                'notes'               => sanitize_textarea_field(wp_unslash($raw_child['notes'] ?? '')),
                'user_login'          => sanitize_text_field(wp_unslash($raw_child['user_login'] ?? '')),
                'user_password'       => (string) wp_unslash($raw_child['user_password'] ?? ''),
                'user_password_confirm' => (string) wp_unslash($raw_child['user_password_confirm'] ?? ''),
            );
        }

        if (empty($children)) {
            return array(array(
                'first_name'          => '',
                'last_name'           => '',
                'birth_date'          => '',
                'email'               => '',
                'phone'               => '',
                'is_autonomous'       => $registration_type === MjRoles::JEUNE ? 1 : 0,
                'photo_usage_consent' => 0,
                'notes'               => '',
                'user_login'          => '',
                'user_password'       => '',
                'user_password_confirm' => '',
            ));
        }

        if ($registration_type === MjRoles::JEUNE && count($children) > 1) {
            $children = array(array_merge($children[0], array('is_autonomous' => 1)));
        }

        return $children;
    }
}

if (!function_exists('mj_render_child_form_block')) {
    function mj_render_child_form_block($index, array $values, $allow_remove = true, $show_header = true) {
        $defaults = array(
            'first_name'          => '',
            'last_name'           => '',
            'birth_date'          => '',
            'email'               => '',
            'phone'               => '',
            'is_autonomous'       => 0,
            'photo_usage_consent' => 0,
            'notes'               => '',
            'user_login'          => '',
        );

        $values = wp_parse_args($values, $defaults);
        $index_attr = esc_attr($index);
        $is_autonomous = (int) $values['is_autonomous'] === 1;

        $header_classes = 'mj-child-card__header' . ($show_header ? '' : ' mj-hidden');
        $account_classes = 'mj-child-card__account' . ($is_autonomous ? '' : ' mj-hidden');

        ob_start();
        ?>
        <div class="mj-child-card" data-child-index="<?php echo $index_attr; ?>">
            <div class="<?php echo esc_attr($header_classes); ?>">
                <h4 class="mj-child-card__title">Jeune <span class="mj-child-card__number"></span></h4>
                <?php if ($allow_remove) : ?>
                    <button type="button" class="mj-child-card__remove" aria-label="Retirer ce jeune">×</button>
                <?php endif; ?>
            </div>
            <div class="mj-child-card__grid">
                <div class="mj-field-group">
                    <label for="mj_child_<?php echo $index_attr; ?>_first_name">Prénom *</label>
                    <input type="text" id="mj_child_<?php echo $index_attr; ?>_first_name" name="jeunes[<?php echo $index_attr; ?>][first_name]" value="<?php echo esc_attr($values['first_name']); ?>" required />
                </div>
                <div class="mj-field-group">
                    <label for="mj_child_<?php echo $index_attr; ?>_last_name">Nom *</label>
                    <input type="text" id="mj_child_<?php echo $index_attr; ?>_last_name" name="jeunes[<?php echo $index_attr; ?>][last_name]" value="<?php echo esc_attr($values['last_name']); ?>" required />
                </div>
                <div class="mj-field-group">
                    <label for="mj_child_<?php echo $index_attr; ?>_birth_date">Date de naissance *</label>
                    <input type="date" id="mj_child_<?php echo $index_attr; ?>_birth_date" name="jeunes[<?php echo $index_attr; ?>][birth_date]" value="<?php echo esc_attr($values['birth_date']); ?>" required />
                </div>
                <div class="mj-field-group">
                    <label for="mj_child_<?php echo $index_attr; ?>_email">Email (optionnel)</label>
                    <input type="email" id="mj_child_<?php echo $index_attr; ?>_email" name="jeunes[<?php echo $index_attr; ?>][email]" value="<?php echo esc_attr($values['email']); ?>" />
                </div>
                <div class="mj-field-group">
                    <label for="mj_child_<?php echo $index_attr; ?>_phone">Téléphone (optionnel)</label>
                    <input type="tel" id="mj_child_<?php echo $index_attr; ?>_phone" name="jeunes[<?php echo $index_attr; ?>][phone]" value="<?php echo esc_attr($values['phone']); ?>" />
                </div>
            </div>
            <div class="mj-child-card__options">
                <label class="mj-checkbox mj-child-autonomous-toggle">
                    <input type="checkbox" name="jeunes[<?php echo $index_attr; ?>][is_autonomous]" <?php checked($is_autonomous); ?> />
                    <span>Jeune majeur ou autonome (pas de tuteur rattaché)</span>
                </label>
                <label class="mj-checkbox">
                    <input type="checkbox" name="jeunes[<?php echo $index_attr; ?>][photo_usage_consent]" <?php checked((int) $values['photo_usage_consent'], 1); ?> />
                    <span>Autorisation de l'utilisation d'images</span>
                </label>
            </div>
            <div class="mj-child-card__grid">
                <div class="mj-field-group mj-field-group--full">
                    <label for="mj_child_<?php echo $index_attr; ?>_notes">Informations complémentaires (allergies, santé, etc.)</label>
                    <textarea id="mj_child_<?php echo $index_attr; ?>_notes" name="jeunes[<?php echo $index_attr; ?>][notes]" rows="3"><?php echo esc_textarea($values['notes']); ?></textarea>
                </div>
            </div>
            <div class="<?php echo esc_attr($account_classes); ?>" data-autonomous-section="1">
                <h5>Accès du jeune</h5>
                <div class="mj-field-group">
                    <label for="mj_child_<?php echo $index_attr; ?>_user_login">Identifiant *</label>
                    <input type="text" id="mj_child_<?php echo $index_attr; ?>_user_login" name="jeunes[<?php echo $index_attr; ?>][user_login]" value="<?php echo esc_attr($values['user_login']); ?>" autocomplete="username" required />
                </div>
                <div class="mj-field-group">
                    <label for="mj_child_<?php echo $index_attr; ?>_user_password">Mot de passe *</label>
                    <input type="password" id="mj_child_<?php echo $index_attr; ?>_user_password" name="jeunes[<?php echo $index_attr; ?>][user_password]" autocomplete="new-password" required />
                </div>
                <div class="mj-field-group">
                    <label for="mj_child_<?php echo $index_attr; ?>_user_password_confirm">Confirmer le mot de passe *</label>
                    <input type="password" id="mj_child_<?php echo $index_attr; ?>_user_password_confirm" name="jeunes[<?php echo $index_attr; ?>][user_password_confirm]" autocomplete="new-password" required />
                </div>
                <p class="mj-field-hint">Minimum <?php echo (int) apply_filters('mj_member_min_password_length', 8); ?> caractères.</p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

if (!function_exists('mj_member_render_registration_form')) {
    function mj_member_render_registration_form($args = array()) {
        $args = is_array($args) ? $args : array();
        $default_regulation_page_id = (int) get_option('mj_registration_regulation_page', 0);
        $default_regulation_url = $default_regulation_page_id > 0 ? get_permalink($default_regulation_page_id) : '';

        $args = wp_parse_args($args, array(
            'message_logged_out' => '',
            'message_logged_in' => __('Tu es déjà inscrit.', 'mj-member'),
            'title' => array(
                'show' => true,
                'text' => __('Inscription MJ', 'mj-member'),
                'image_id' => 0,
                'image_url' => '',
                'image_alt' => '',
                'image_position' => 'inline-right',
                'margin_top' => '',
            ),
            'regulation' => array(
                'enabled' => $default_regulation_page_id > 0,
                'page_id' => $default_regulation_page_id,
                'url' => $default_regulation_url,
                'modal_title' => __('Règlement intérieur', 'mj-member'),
                'trigger_label' => __('Règlement d\'ordre intérieur', 'mj-member'),
                'checkbox_label' => __('Je confirme avoir pris connaissance du %s.', 'mj-member'),
                'content' => '',
            ),
        ));

        $title_defaults = array(
            'show' => true,
            'text' => __('Inscription MJ', 'mj-member'),
            'image_id' => 0,
            'image_url' => '',
            'image_alt' => '',
            'image_position' => 'inline-right',
            'margin_top' => '',
        );

        $title_settings = is_array($args['title']) ? $args['title'] : array();
        $title_settings = wp_parse_args($title_settings, $title_defaults);
        $allowed_title_image_positions = array('inline-right', 'inline-left', 'above-center', 'above-right');
        $allowed_title_image_positions[] = 'above-left';
        $title_image_position = isset($title_settings['image_position']) ? (string) $title_settings['image_position'] : 'inline-right';
        if (!in_array($title_image_position, $allowed_title_image_positions, true)) {
            $title_image_position = 'inline-right';
        }
        $title_margin_top = isset($title_settings['margin_top']) ? (string) $title_settings['margin_top'] : '';
        $args['title'] = array(
            'show' => !empty($title_settings['show']),
            'text' => isset($title_settings['text']) ? (string) $title_settings['text'] : $title_defaults['text'],
            'image_id' => isset($title_settings['image_id']) ? (int) $title_settings['image_id'] : 0,
            'image_url' => isset($title_settings['image_url']) ? (string) $title_settings['image_url'] : '',
            'image_alt' => isset($title_settings['image_alt']) ? (string) $title_settings['image_alt'] : '',
            'image_position' => $title_image_position,
            'margin_top' => $title_margin_top,
        );

        $regulation_defaults = array(
            'enabled' => false,
            'page_id' => 0,
            'url' => '',
            'modal_title' => __('Règlement intérieur', 'mj-member'),
            'trigger_label' => __('Lire le règlement intérieur', 'mj-member'),
            'checkbox_label' => __('Je confirme avoir lu et accepté le règlement intérieur.', 'mj-member'),
            'content' => '',
        );

        $regulation_settings = is_array($args['regulation']) ? $args['regulation'] : array();
        $regulation_settings = wp_parse_args($regulation_settings, $regulation_defaults);
        $args['regulation'] = array(
            'enabled' => !empty($regulation_settings['enabled']),
            'page_id' => isset($regulation_settings['page_id']) ? (int) $regulation_settings['page_id'] : 0,
            'url' => isset($regulation_settings['url']) ? (string) $regulation_settings['url'] : '',
            'modal_title' => isset($regulation_settings['modal_title']) ? (string) $regulation_settings['modal_title'] : $regulation_defaults['modal_title'],
            'trigger_label' => isset($regulation_settings['trigger_label']) ? (string) $regulation_settings['trigger_label'] : $regulation_defaults['trigger_label'],
            'checkbox_label' => isset($regulation_settings['checkbox_label']) ? (string) $regulation_settings['checkbox_label'] : $regulation_defaults['checkbox_label'],
            'content' => isset($regulation_settings['content']) ? (string) $regulation_settings['content'] : '',
        );

        if ($args['regulation']['page_id'] > 0) {
            if ($args['regulation']['url'] === '') {
                $args['regulation']['url'] = get_permalink($args['regulation']['page_id']);
            }

            if ($args['regulation']['content'] === '') {
                $regulation_post = get_post($args['regulation']['page_id']);
                if ($regulation_post instanceof \WP_Post && $regulation_post->post_status === 'publish') {
                    $regulation_content = apply_filters('the_content', $regulation_post->post_content);
                    if (is_string($regulation_content) && $regulation_content !== '') {
                        $args['regulation']['content'] = wp_kses_post($regulation_content);
                    }
                }
            }

            if (!empty($args['regulation']['url']) || $args['regulation']['content'] !== '') {
                $args['regulation']['enabled'] = true;
            }
        }

        $current_member = (is_user_logged_in() && function_exists('mj_member_get_current_member')) ? mj_member_get_current_member() : null;
        $already_registered = $current_member && !is_wp_error($current_member);

        $result = null;
        if (!$already_registered) {
            $result = mj_process_frontend_inscription();
        }

        $registration_type = mj_member_get_registration_type();
        $guardian_values = mj_collect_guardian_form_values();
        $children_values = mj_collect_children_form_values();

        $show_form = !$already_registered;
        if ($show_form && $result && !empty($result['success']) && !is_user_logged_in()) {
            $show_form = false;
        }

        if ($result && !empty($result['success'])) {
            $registration_type = 'guardian';
            $guardian_values = array(
                'first_name'  => '',
                'last_name'   => '',
                'email'       => '',
                'phone'       => '',
                'address'     => '',
                'city'        => '',
                'postal_code' => '',
                'user_login'  => '',
            );
            $children_values = array(array(
                'first_name'          => '',
                'last_name'           => '',
                'birth_date'          => '',
                'email'               => '',
                'phone'               => '',
                'is_autonomous'       => 0,
                'photo_usage_consent' => 0,
                'notes'               => '',
                'user_login'          => '',
                'user_password'       => '',
                'user_password_confirm' => '',
            ));
        }

        ob_start();
        ?>
        <?php
        $intro_message = $already_registered
            ? $args['message_logged_in']
            : (is_user_logged_in() ? $args['message_logged_in'] : $args['message_logged_out']);

        if ($intro_message !== '') {
            $normalized_intro = trim(wp_strip_all_tags($intro_message));
            if ($normalized_intro !== '') {
                $normalized_intro_lower = function_exists('mb_strtolower') ? mb_strtolower($normalized_intro, 'UTF-8') : strtolower($normalized_intro);
                if (strpos($normalized_intro_lower, 'valide tes données et ajoute un mot de passe') !== false) {
                    $intro_message = '';
                }
            }
        }
        ?>
        <div class="mj-inscription-container">
            <?php if (!empty($args['title']['show']) && (!empty($args['title']['text']) || !empty($args['title']['image_url']))) : ?>
                <?php
                $title_image_position = isset($args['title']['image_position']) ? (string) $args['title']['image_position'] : 'inline-right';
                if (!in_array($title_image_position, array('inline-right', 'inline-left', 'above-center', 'above-left', 'above-right'), true)) {
                    $title_image_position = 'inline-right';
                }
                $title_has_image = !empty($args['title']['image_url']);
                $title_header_classes = array('mj-inscription-container__header');
                if ($title_has_image) {
                    $title_header_classes[] = 'mj-inscription-container__header--has-image';
                    $title_header_classes[] = 'mj-inscription-container__header--image-' . $title_image_position;
                    if (in_array($title_image_position, array('above-left', 'above-center', 'above-right'), true)) {
                        $title_header_classes[] = 'mj-inscription-container__header--stack';
                    }
                }
                $render_image_first = $title_has_image && in_array($title_image_position, array('above-left', 'above-center', 'above-right'), true);
                $header_class_attr = implode(' ', array_map('sanitize_html_class', $title_header_classes));
                ?>
                <div class="<?php echo esc_attr($header_class_attr); ?>">
                    <?php if ($title_has_image && $render_image_first) :
                        $title_image_alt = $args['title']['image_alt'] !== '' ? $args['title']['image_alt'] : (isset($args['title']['text']) ? $args['title']['text'] : '');
                    ?>
                        <div class="mj-inscription-container__title-image">
                            <img src="<?php echo esc_url($args['title']['image_url']); ?>" alt="<?php echo esc_attr($title_image_alt); ?>" />
                        </div>
                    <?php endif; ?>
                    <?php
                    $title_style_attr = '';
                    if (!empty($args['title']['margin_top'])) {
                        $raw_margin_top = (string) $args['title']['margin_top'];
                        $normalized_margin_top = '';
                        if (is_numeric($raw_margin_top)) {
                            $normalized_margin_top = $raw_margin_top . 'px';
                        } elseif (preg_match('/^-?\d+(?:\.\d+)?(px|rem|em|%)$/', $raw_margin_top)) {
                            $normalized_margin_top = $raw_margin_top;
                        }
                        if ($normalized_margin_top !== '') {
                            $title_style_attr = ' style="--mj-title-margin-top: ' . esc_attr($normalized_margin_top) . '"';
                        }
                    }
                    ?>
                    <?php if (!empty($args['title']['text'])) : ?>
                        <h2 class="mj-inscription-container__title"<?php echo $title_style_attr; ?>><?php echo esc_html($args['title']['text']); ?></h2>
                    <?php endif; ?>
                    <?php if ($title_has_image && !$render_image_first) :
                        $title_image_alt = $args['title']['image_alt'] !== '' ? $args['title']['image_alt'] : (isset($args['title']['text']) ? $args['title']['text'] : '');
                    ?>
                        <div class="mj-inscription-container__title-image">
                            <img src="<?php echo esc_url($args['title']['image_url']); ?>" alt="<?php echo esc_attr($title_image_alt); ?>" />
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <?php if ($intro_message !== '') :
                $message_classes = array('mj-inscription-message');
                if (!$show_form) {
                    $message_classes[] = 'mj-inscription-message--info';
                }
                $allowed_intro_html = wp_kses_allowed_html('post');
                if (function_exists('mj_member_login_component_allowed_icon_tags')) {
                    $icon_tags = mj_member_login_component_allowed_icon_tags();
                    foreach ($icon_tags as $tag_name => $attributes) {
                        if (!isset($allowed_intro_html[$tag_name])) {
                            $allowed_intro_html[$tag_name] = $attributes;
                            continue;
                        }
                        $allowed_intro_html[$tag_name] = array_merge($allowed_intro_html[$tag_name], $attributes);
                    }
                } else {
                    $fallback_icon_tags = array(
                        'svg' => array(
                            'class' => true,
                            'xmlns' => true,
                            'width' => true,
                            'height' => true,
                            'viewBox' => true,
                            'fill' => true,
                            'stroke' => true,
                            'stroke-width' => true,
                            'stroke-linecap' => true,
                            'stroke-linejoin' => true,
                            'aria-hidden' => true,
                            'focusable' => true,
                            'role' => true,
                            'preserveAspectRatio' => true,
                        ),
                        'path' => array(
                            'd' => true,
                            'fill' => true,
                            'stroke' => true,
                            'stroke-width' => true,
                            'stroke-linecap' => true,
                            'stroke-linejoin' => true,
                        ),
                        'g' => array(
                            'class' => true,
                            'fill' => true,
                            'stroke' => true,
                            'stroke-width' => true,
                            'stroke-linecap' => true,
                            'stroke-linejoin' => true,
                            'transform' => true,
                            'opacity' => true,
                        ),
                        'polygon' => array(
                            'points' => true,
                            'fill' => true,
                            'stroke' => true,
                            'stroke-width' => true,
                            'stroke-linecap' => true,
                            'stroke-linejoin' => true,
                        ),
                        'polyline' => array(
                            'points' => true,
                            'fill' => true,
                            'stroke' => true,
                            'stroke-width' => true,
                            'stroke-linecap' => true,
                            'stroke-linejoin' => true,
                        ),
                        'line' => array(
                            'x1' => true,
                            'x2' => true,
                            'y1' => true,
                            'y2' => true,
                            'stroke' => true,
                            'stroke-width' => true,
                            'stroke-linecap' => true,
                            'stroke-linejoin' => true,
                        ),
                        'circle' => array(
                            'cx' => true,
                            'cy' => true,
                            'r' => true,
                            'fill' => true,
                            'stroke' => true,
                            'stroke-width' => true,
                        ),
                        'ellipse' => array(
                            'cx' => true,
                            'cy' => true,
                            'rx' => true,
                            'ry' => true,
                            'fill' => true,
                            'stroke' => true,
                            'stroke-width' => true,
                        ),
                        'rect' => array(
                            'x' => true,
                            'y' => true,
                            'width' => true,
                            'height' => true,
                            'rx' => true,
                            'ry' => true,
                            'fill' => true,
                            'stroke' => true,
                            'stroke-width' => true,
                        ),
                        'use' => array(
                            'xlink:href' => true,
                        ),
                    );
                    foreach ($fallback_icon_tags as $tag_name => $attributes) {
                        $allowed_intro_html[$tag_name] = array_merge(
                            isset($allowed_intro_html[$tag_name]) ? $allowed_intro_html[$tag_name] : array(),
                            $attributes
                        );
                    }
                }
                $allowed_intro_html['button'] = array_merge(
                    isset($allowed_intro_html['button']) ? $allowed_intro_html['button'] : array(),
                    array(
                        'type' => true,
                        'class' => true,
                        'data-mj-login-trigger' => true,
                        'data-target' => true,
                        'aria-label' => true,
                        'aria-expanded' => true,
                    )
                );
                $allowed_intro_html['a'] = array_merge(
                    isset($allowed_intro_html['a']) ? $allowed_intro_html['a'] : array(),
                    array(
                        'class' => true,
                        'href' => true,
                        'target' => true,
                        'rel' => true,
                        'data-mj-login-trigger' => true,
                        'data-target' => true,
                        'aria-label' => true,
                    )
                );
                $allowed_intro_html['span'] = array_merge(
                    isset($allowed_intro_html['span']) ? $allowed_intro_html['span'] : array(),
                    array(
                        'class' => true,
                        'aria-hidden' => true,
                        'data-role' => true,
                    )
                );
                $allowed_intro_html['div'] = array_merge(
                    isset($allowed_intro_html['div']) ? $allowed_intro_html['div'] : array(),
                    array(
                        'class' => true,
                        'data-role' => true,
                    )
                );
                $message_class_attr = implode(' ', array_map('sanitize_html_class', $message_classes));
                ?>
                <div class="<?php echo esc_attr($message_class_attr); ?>">
                    <?php echo wp_kses($intro_message, $allowed_intro_html); ?>
                </div>
            <?php endif; ?>
            <?php if ($result) : ?>
                <div class="mj-notice <?php echo !empty($result['success']) ? 'mj-notice--success' : 'mj-notice--error'; ?>">
                    <p><?php echo esc_html($result['message']); ?></p>
                </div>
            <?php endif; ?>

            <?php if ($show_form) : ?>
                <form method="post" class="mj-inscription-form" novalidate>
                    <?php wp_nonce_field('mj_frontend_form', 'mj_frontend_nonce'); ?>

                    <fieldset class="mj-fieldset" data-section="registration-type">
                        <legend>Type d'inscription</legend>
                        <label class="mj-radio">
                            <input type="radio" name="registration_type" value="guardian" <?php checked($registration_type, 'guardian'); ?> />
                            <span>Je suis un tuteur et j'inscris un ou plusieurs jeunes</span>
                        </label>
                        <label class="mj-radio">
                            <input type="radio" name="registration_type" value="<?php echo esc_attr(MjRoles::JEUNE); ?>" <?php checked($registration_type, MjRoles::JEUNE); ?> />
                            <span>Je suis un jeune majeur et je m'inscris moi-même</span>
                        </label>
                        <p class="mj-field-hint">Choisissez cette option si vous avez 18 ans ou plus et que vous n'avez pas de tuteur.</p>
                    </fieldset>

                    <fieldset class="mj-fieldset" data-section="guardian">
                        <legend>Informations du tuteur</legend>
                        <div class="mj-field-grid">
                            <div class="mj-field-group">
                                <label for="guardian_first_name">Prénom *</label>
                                <input type="text" id="guardian_first_name" name="guardian_first_name" value="<?php echo esc_attr($guardian_values['first_name']); ?>" required data-required-if="guardian" />
                            </div>
                            <div class="mj-field-group">
                                <label for="guardian_last_name">Nom *</label>
                                <input type="text" id="guardian_last_name" name="guardian_last_name" value="<?php echo esc_attr($guardian_values['last_name']); ?>" required data-required-if="guardian" />
                            </div>
                            <div class="mj-field-group">
                                <label for="guardian_email">Email *</label>
                                <input type="email" id="guardian_email" name="guardian_email" value="<?php echo esc_attr($guardian_values['email']); ?>" required data-required-if="guardian" />
                            </div>
                            <div class="mj-field-group">
                                <label for="guardian_phone">Téléphone</label>
                                <input type="tel" id="guardian_phone" name="guardian_phone" value="<?php echo esc_attr($guardian_values['phone']); ?>" />
                            </div>
                            <div class="mj-field-group mj-field-group--full">
                                <label for="guardian_address">Adresse</label>
                                <input type="text" id="guardian_address" name="guardian_address" value="<?php echo esc_attr($guardian_values['address']); ?>" />
                            </div>
                            <div class="mj-field-group">
                                <label for="guardian_postal_code">Code postal</label>
                                <input type="text" id="guardian_postal_code" name="guardian_postal_code" value="<?php echo esc_attr($guardian_values['postal_code']); ?>" />
                            </div>
                            <div class="mj-field-group">
                                <label for="guardian_city">Ville</label>
                                <input type="text" id="guardian_city" name="guardian_city" value="<?php echo esc_attr($guardian_values['city']); ?>" />
                            </div>
                            <div class="mj-field-group">
                                <label for="guardian_user_login">Identifiant de connexion *</label>
                                <input type="text" id="guardian_user_login" name="guardian_user_login" value="<?php echo esc_attr($guardian_values['user_login']); ?>" required data-required-if="guardian" autocomplete="username" />
                            </div>
                            <div class="mj-field-group">
                                <label for="guardian_user_password">Mot de passe *</label>
                                <input type="password" id="guardian_user_password" name="guardian_user_password" required data-required-if="guardian" autocomplete="new-password" />
                            </div>
                            <div class="mj-field-group">
                                <label for="guardian_user_password_confirm">Confirmer le mot de passe *</label>
                                <input type="password" id="guardian_user_password_confirm" name="guardian_user_password_confirm" required data-required-if="guardian" autocomplete="new-password" />
                                <p class="mj-field-hint">Minimum <?php echo (int) apply_filters('mj_member_min_password_length', 8); ?> caractères.</p>
                            </div>
                        </div>
                    </fieldset>

                    <fieldset class="mj-fieldset">
                        <legend>Jeunes à inscrire</legend>
                        <div id="mj-children-wrapper">
                            <?php foreach ($children_values as $index => $values) : ?>
                                <?php echo mj_render_child_form_block($index, $values, $index > 0, $registration_type !== MjRoles::JEUNE); ?>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="mj-button mj-button--secondary" id="mj-add-child">+ Ajouter un jeune</button>
                    </fieldset>

                    <fieldset class="mj-fieldset">
                        <legend>Consentements</legend>
                        <label class="mj-checkbox">
                            <input type="checkbox" name="rgpd_consent" required <?php checked(!empty($_POST['rgpd_consent'])); ?> />
                            <span>J'autorise l'utilisation de mes données personnelles dans le cadre de la gestion des activités de la MJ Péry.</span>
                        </label>
                        <p class="mj-field-hint">Vous pourrez demander la rectification ou la suppression de vos données à tout moment.</p>
                        <?php if (!empty($args['regulation']['enabled'])) :
                            $regulation_link_text_raw = !empty($args['regulation']['trigger_label']) ? (string) $args['regulation']['trigger_label'] : __('Règlement d\'ordre intérieur', 'mj-member');
                            $regulation_link_text = wp_strip_all_tags($regulation_link_text_raw, true);
                            $regulation_checkbox_label_raw = isset($args['regulation']['checkbox_label']) ? (string) $args['regulation']['checkbox_label'] : __('Je confirme avoir pris connaissance du %s.', 'mj-member');
                            $regulation_checkbox_label = wp_strip_all_tags($regulation_checkbox_label_raw, true);

                            $regulation_link_markup = '';
                            if (!empty($args['regulation']['content'])) {
                                $regulation_link_markup = '<button type="button" class="mj-link-button" data-mj-regulation-open>' . esc_html($regulation_link_text) . '</button>';
                            } elseif (!empty($args['regulation']['url'])) {
                                $regulation_link_markup = '<a class="mj-link-button" href="' . esc_url($args['regulation']['url']) . '" target="_blank" rel="noopener noreferrer">' . esc_html($regulation_link_text) . '</a>';
                            }

                            $regulation_checkbox_content = $regulation_checkbox_label;
                            if ($regulation_link_markup !== '') {
                                if (strpos($regulation_checkbox_label, '%s') !== false) {
                                    $regulation_checkbox_content = sprintf($regulation_checkbox_label, $regulation_link_markup);
                                } else {
                                    $regulation_checkbox_content = $regulation_checkbox_label . ' ' . $regulation_link_markup;
                                }
                            }

                            $regulation_allowed_label_html = array(
                                'a' => array(
                                    'href' => array(),
                                    'target' => array(),
                                    'rel' => array(),
                                    'class' => array(),
                                ),
                                'button' => array(
                                    'type' => array(),
                                    'class' => array(),
                                    'data-mj-regulation-open' => array(),
                                ),
                                'strong' => array(),
                                'em' => array(),
                                'span' => array('class' => array()),
                            );
                            ?>
                            <div class="mj-regulation">
                                <label class="mj-checkbox mj-regulation__consent">
                                    <input type="checkbox" name="regulation_consent" value="1" <?php checked(!empty($_POST['regulation_consent'])); ?> required />
                                    <span><?php echo wp_kses($regulation_checkbox_content, $regulation_allowed_label_html); ?></span>
                                </label>
                            </div>
                            <input type="hidden" name="regulation_required" value="1" />
                        <?php endif; ?>
                    </fieldset>

                    <div class="mj-form-actions">
                        <button type="submit" class="mj-button">Envoyer l'inscription</button>
                    </div>
                </form>
            <?php endif; ?>
            <?php if ($show_form && !empty($args['regulation']['enabled']) && !empty($args['regulation']['content'])) : ?>
                <div class="mj-modal-backdrop" data-mj-regulation-backdrop hidden></div>
                <div class="mj-modal" id="mj-regulation-modal" role="dialog" aria-modal="true" aria-labelledby="mj-regulation-title" hidden>
                    <div class="mj-modal__dialog">
                        <div class="mj-modal__header">
                            <h3 class="mj-modal__title" id="mj-regulation-title"><?php echo esc_html($args['regulation']['modal_title']); ?></h3>
                            <button type="button" class="mj-modal__close" data-mj-regulation-close aria-label="<?php echo esc_attr__('Fermer', 'mj-member'); ?>">&times;</button>
                        </div>
                        <div class="mj-modal__content">
                            <?php echo $args['regulation']['content']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <script type="text/template" id="mj-child-template">
            <?php echo mj_render_child_form_block('__INDEX__', array(), true, $registration_type !== MjRoles::JEUNE); ?>
        </script>

        <style>
            .mj-inscription-container {
                --mj-accent: #2563eb;
                --mj-accent-soft: rgba(37, 99, 235, 0.12);
                --mj-accent-dark: #1d4ed8;
                --mj-border: #d0daf0;
                --mj-muted: #64748b;
                --mj-bg: #f8fafc;
                max-width: 960px;
                margin: 32px auto;
                padding: 32px;
                background: linear-gradient(135deg, #ffffff 0%, #eff6ff 100%);
                border-radius: 20px;
                box-shadow: 0 26px 70px rgba(15, 23, 42, 0.12);
            }

            .mj-inscription-form {
                background: #ffffff;
                border-radius: 18px;
                padding: 30px;
                border: 1px solid rgba(208, 218, 240, 0.75);
                box-shadow: 0 22px 48px rgba(15, 23, 42, 0.08);
            }

            .mj-inscription-message {
                font-size: 18px;
                font-weight: 600;
                color: #0f172a;
                margin-bottom: 24px;
                padding: 18px 22px;
                border-radius: 14px;
                background: rgba(37, 99, 235, 0.08);
                border: 1px solid rgba(37, 99, 235, 0.16);
                text-align: center;
            }

            .mj-inscription-message > :last-child {
                margin-bottom: 0;
            }

            .mj-inscription-message--info {
                background: rgba(100, 116, 139, 0.14);
                border-color: rgba(100, 116, 139, 0.22);
                color: #1f2937;
            }

            .mj-inscription-container__header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 20px;
                margin-bottom: 28px;
            }

            .mj-inscription-container__title {
                margin: 0;
                padding-top: var(--mj-title-margin-top, 0);
                font-size: 36px;
                font-weight: 800;
                color: #0f172a;
                letter-spacing: -0.02em;
            }

            .mj-inscription-container__title strong {
                color: var(--mj-accent-dark);
            }

            .mj-inscription-container__title-image {
                flex-shrink: 0;
                display: block;
                line-height: 0;
                padding: 0;
                background: none;
                border-radius: 0;
                box-shadow: none;
            }

            .mj-inscription-container__title-image img {
                display: block;
                height: auto;
                max-width: 100%;
            }

            .mj-inscription-container__header--has-image {
                flex-wrap: wrap;
            }

            .mj-inscription-container__header--image-inline-left .mj-inscription-container__title {
                order: 2;
            }

            .mj-inscription-container__header--image-inline-left .mj-inscription-container__title-image {
                order: 1;
            }

            .mj-inscription-container__header--stack {
                display: block;
                position: relative;
            }

            .mj-inscription-container__header--stack::after {
                content: '';
                display: block;
                clear: both;
            }

            .mj-inscription-container__header--stack .mj-inscription-container__title-image {
                margin-bottom: 0;
            }

            .mj-inscription-container__header--image-above-left .mj-inscription-container__title-image {
                float: left;
                margin-right: 16px;
            }

            .mj-inscription-container__header--image-above-center .mj-inscription-container__title-image {
                float: none;
                margin-left: auto;
                margin-right: auto;
                display: table;
            }

            .mj-inscription-container__header--image-above-right .mj-inscription-container__title-image {
                float: right;
                margin-left: 16px;
            }

            .mj-fieldset {
                border: none;
                margin: 0 0 34px;
                padding: 28px 28px 10px;
                border-radius: 18px;
                background: linear-gradient(180deg, rgba(248, 250, 252, 0.92) 0%, rgba(248, 250, 252, 0.55) 100%);
                border: 1px solid rgba(148, 163, 184, 0.28);
                box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.5);
            }

            .mj-fieldset legend {
                font-size: 22px;
                font-weight: 700;
                color: var(--mj-accent-dark);
                margin-bottom: 20px;
                letter-spacing: -0.01em;
            }

            .mj-field-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
                gap: 22px 30px;
            }

            .mj-field-group {
                display: flex;
                flex-direction: column;
                gap: 10px;
            }

            .mj-field-group--full {
                grid-column: 1 / -1;
            }

            .mj-field-group label {
                font-weight: 600;
                color: #0f172a;
                margin-bottom: 0;
                font-size: 15px;
            }

            .mj-field-group input,
            .mj-field-group textarea {
                padding: 12px 16px;
                border: 1px solid rgba(148, 163, 184, 0.55);
                border-radius: 12px;
                font-size: 15px;
                font-family: inherit;
                transition: border-color 0.2s ease, box-shadow 0.2s ease;
                background: rgba(255, 255, 255, 0.95);
                color: #0f172a;
                box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
            }

            .mj-field-group textarea {
                min-height: 120px;
            }

            .mj-field-group input:focus,
            .mj-field-group textarea:focus {
                outline: none;
                border-color: var(--mj-accent);
                box-shadow: 0 0 0 4px var(--mj-accent-soft);
            }

            .mj-field-group input::placeholder,
            .mj-field-group textarea::placeholder {
                color: rgba(100, 116, 139, 0.7);
            }

            .mj-field-hint {
                font-size: 13px;
                color: var(--mj-muted);
                margin-top: -4px;
            }

            .mj-checkbox {
                display: flex;
                align-items: flex-start;
                gap: 12px;
                margin-bottom: 14px;
                font-size: 15px;
                color: #0f172a;
            }

            .mj-checkbox input {
                margin-top: 3px;
                width: 20px;
                height: 20px;
                border-radius: 6px;
                border: 1px solid rgba(148, 163, 184, 0.7);
                background: #fff;
                -webkit-appearance: none;
                appearance: none;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                transition: all 0.2s ease;
            }

            .mj-checkbox input:focus-visible {
                outline: 2px solid var(--mj-accent);
                outline-offset: 2px;
            }

            .mj-checkbox input:checked {
                border-color: var(--mj-accent);
                background: var(--mj-accent);
                box-shadow: 0 6px 18px rgba(37, 99, 235, 0.24);
                color: #fff;
            }

            .mj-checkbox input:checked::after {
                content: '\2713';
                font-size: 13px;
                line-height: 1;
            }

            .mj-radio {
                display: flex;
                align-items: flex-start;
                gap: 12px;
                margin-bottom: 14px;
                padding: 12px 16px;
                background: rgba(248, 250, 252, 0.8);
                border-radius: 14px;
                border: 1px solid rgba(148, 163, 184, 0.32);
                transition: border-color 0.2s ease, background 0.2s ease;
            }

            .mj-radio input {
                margin-top: 3px;
            }

            .mj-radio:hover {
                border-color: var(--mj-accent);
                background: rgba(37, 99, 235, 0.08);
            }

            .mj-hidden {
                display: none !important;
            }

            #mj-children-wrapper {
                display: grid;
                gap: 24px;
            }

            .mj-child-card {
                background: rgba(255, 255, 255, 0.96);
                border: 1px solid rgba(148, 163, 184, 0.28);
                border-radius: 16px;
                padding: 24px;
                box-shadow: 0 20px 40px rgba(15, 23, 42, 0.14);
            }

            .mj-child-card__header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                margin-bottom: 18px;
            }

            .mj-child-card__title {
                margin: 0;
                font-size: 20px;
                font-weight: 700;
                color: #0f172a;
            }

            .mj-child-card__remove {
                border: none;
                background: transparent;
                color: #ef4444;
                font-size: 22px;
                cursor: pointer;
                transition: transform 0.2s ease, color 0.2s ease;
            }

            .mj-child-card__remove:hover {
                color: #b91c1c;
                transform: scale(1.08);
            }

            .mj-child-card__grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 22px;
                margin-bottom: 20px;
            }

            .mj-child-card__options {
                display: flex;
                flex-direction: column;
                gap: 12px;
                margin-bottom: 20px;
            }

            .mj-button {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 10px;
                padding: 14px 28px;
                border-radius: 12px;
                border: none;
                background: var(--mj-accent);
                color: #fff;
                font-size: 16px;
                font-weight: 700;
                cursor: pointer;
                transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
                box-shadow: 0 22px 40px rgba(37, 99, 235, 0.28);
            }

            .mj-button:hover {
                background: var(--mj-accent-dark);
                transform: translateY(-2px);
                box-shadow: 0 26px 46px rgba(37, 99, 235, 0.34);
            }

            .mj-button:active {
                transform: translateY(0);
                box-shadow: 0 18px 34px rgba(37, 99, 235, 0.22);
            }

            .mj-button--secondary {
                background: rgba(37, 99, 235, 0.08);
                color: var(--mj-accent-dark);
                border: 1px dashed rgba(37, 99, 235, 0.36);
                margin-top: 16px;
                box-shadow: none;
            }

            .mj-button--secondary:hover {
                background: rgba(37, 99, 235, 0.14);
                border-color: rgba(37, 99, 235, 0.52);
            }

            .mj-regulation {
                margin-top: 20px;
                display: flex;
                flex-direction: column;
                gap: 16px;
                padding-top: 12px;
                border-top: 1px dashed rgba(148, 163, 184, 0.4);
            }

            .mj-regulation__consent {
                align-items: center;
            }

            .mj-regulation__consent span {
                flex: 1;
            }

            .mj-modal-backdrop[hidden],
            .mj-modal[hidden] {
                display: none !important;
            }

            .mj-link-button {
                background: none;
                border: none;
                padding: 0;
                color: var(--mj-accent-dark);
                text-decoration: none;
                font: inherit;
                cursor: pointer;
                position: relative;
                font-weight: 600;
            }

            .mj-link-button::after {
                content: '';
                position: absolute;
                left: 0;
                right: 0;
                bottom: -2px;
                height: 2px;
                background: currentColor;
                opacity: 0.4;
                transition: opacity 0.2s ease, transform 0.2s ease;
                transform: scaleX(0.6);
            }

            .mj-link-button:hover,
            .mj-link-button:focus {
                color: var(--mj-accent);
                outline: none;
            }

            .mj-link-button:hover::after,
            .mj-link-button:focus::after {
                opacity: 1;
                transform: scaleX(1);
            }

            .mj-modal-backdrop {
                position: fixed;
                inset: 0;
                background: rgba(15, 23, 42, 0.55);
                z-index: 9997;
                backdrop-filter: blur(4px);
            }

            .mj-modal {
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                width: min(92vw, 760px);
                max-height: 90vh;
                z-index: 9998;
                background: #ffffff;
                border-radius: 18px;
                box-shadow: 0 28px 70px rgba(15, 23, 42, 0.28);
                overflow: hidden;
            }

            .mj-modal__dialog {
                display: flex;
                flex-direction: column;
                max-height: 90vh;
            }

            .mj-modal__header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 22px 26px;
                border-bottom: 1px solid rgba(208, 218, 240, 0.7);
            }

            .mj-modal__title {
                margin: 0;
                font-size: 24px;
                font-weight: 700;
                color: #0f172a;
            }

            .mj-modal__close {
                border: none;
                background: transparent;
                font-size: 28px;
                line-height: 1;
                cursor: pointer;
                color: #64748b;
                transition: color 0.2s ease, transform 0.2s ease;
            }

            .mj-modal__close:hover {
                color: #0f172a;
                transform: scale(1.05);
            }

            .mj-modal__content {
                padding: 22px 26px;
                overflow-y: auto;
            }

            body.mj-modal-open {
                overflow: hidden;
            }

            .mj-form-actions {
                text-align: right;
                margin-top: 34px;
            }

            .mj-notice {
                padding: 18px 24px;
                border-radius: 12px;
                margin-bottom: 26px;
                border-left: 5px solid;
                box-shadow: 0 18px 34px rgba(15, 23, 42, 0.12);
            }

            .mj-notice--success {
                background: rgba(16, 185, 129, 0.14);
                border-left-color: #059669;
                color: #065f46;
            }

            .mj-notice--error {
                background: rgba(248, 113, 113, 0.18);
                border-left-color: #dc2626;
                color: #7f1d1d;
            }

            @media (max-width: 1024px) {
                .mj-inscription-container {
                    padding: 26px;
                }

                .mj-inscription-form {
                    padding: 26px;
                }
            }

            @media (max-width: 640px) {
                .mj-inscription-container {
                    padding: 22px;
                }

                .mj-inscription-form {
                    padding: 22px;
                }

                .mj-inscription-container__title {
                    font-size: 30px;
                }

                .mj-form-actions {
                    text-align: center;
                }

                .mj-button {
                    width: 100%;
                }

                .mj-regulation {
                    gap: 12px;
                }
            }
        </style>

        <script>
            (function () {
                // Constante rôle jeune injectée depuis PHP
                const ROLE_JEUNE = '<?php echo esc_js(MjRoles::JEUNE); ?>';

                const regulationModal = document.getElementById('mj-regulation-modal');
                const regulationBackdrop = document.querySelector('[data-mj-regulation-backdrop]');
                const regulationTriggers = document.querySelectorAll('[data-mj-regulation-open]');
                const regulationCloseButtons = regulationModal ? regulationModal.querySelectorAll('[data-mj-regulation-close]') : [];

                function openRegulationModal(event) {
                    if (event) {
                        event.preventDefault();
                    }

                    if (!regulationModal || !regulationBackdrop) {
                        return;
                    }

                    regulationModal.hidden = false;
                    regulationBackdrop.hidden = false;
                    document.body.classList.add('mj-modal-open');

                    const focusTarget = regulationModal.querySelector('[data-mj-regulation-close]');
                    if (focusTarget) {
                        focusTarget.focus();
                    }
                }

                function closeRegulationModal() {
                    if (!regulationModal || !regulationBackdrop) {
                        return;
                    }

                    regulationModal.hidden = true;
                    regulationBackdrop.hidden = true;
                    document.body.classList.remove('mj-modal-open');
                }

                regulationTriggers.forEach(function (trigger) {
                    trigger.addEventListener('click', openRegulationModal);
                });

                regulationCloseButtons.forEach(function (button) {
                    button.addEventListener('click', function (event) {
                        event.preventDefault();
                        closeRegulationModal();
                    });
                });

                if (regulationBackdrop) {
                    regulationBackdrop.addEventListener('click', function (event) {
                        event.preventDefault();
                        closeRegulationModal();
                    });
                }

                if (regulationModal) {
                    document.addEventListener('keyup', function (event) {
                        if (event.key === 'Escape' && !regulationModal.hidden) {
                            closeRegulationModal();
                        }
                    });
                }

                const wrapper = document.getElementById('mj-children-wrapper');
                if (!wrapper) {
                    return;
                }

                const template = document.getElementById('mj-child-template');
                const addButton = document.getElementById('mj-add-child');
                const guardianFieldset = document.querySelector('[data-section="guardian"]');
                const registrationRadios = document.querySelectorAll('input[name="registration_type"]');
                const guardianFields = guardianFieldset ? Array.from(guardianFieldset.querySelectorAll('input, textarea, select')) : [];

                guardianFields.forEach(function (field) {
                    if (field.dataset.requiredIf === 'guardian' && field.required) {
                        field.dataset.guardianRequired = '1';
                    }
                });

                let currentType = 'guardian';
                const checkedRadio = document.querySelector('input[name="registration_type"]:checked');
                if (checkedRadio && checkedRadio.value === ROLE_JEUNE) {
                    currentType = ROLE_JEUNE;
                }

                function refreshChildNumbers() {
                    const cards = wrapper.querySelectorAll('.mj-child-card');
                    cards.forEach(function (card, index) {
                        card.dataset.childIndex = String(index);
                        const numberElement = card.querySelector('.mj-child-card__number');
                        if (numberElement) {
                            numberElement.textContent = index + 1;
                        }

                        card.querySelectorAll('input, textarea').forEach(function (field) {
                            if (field.name && field.name.indexOf('jeunes[') === 0) {
                                field.name = field.name.replace(/jeunes\[[^\]]+\]/, 'jeunes[' + index + ']');
                            }

                            if (field.id && field.id.indexOf('mj_child_') === 0) {
                                field.id = field.id.replace(/mj_child_(?:__INDEX__|\d+)_/, 'mj_child_' + index + '_');
                            }
                        });

                        card.querySelectorAll('label').forEach(function (label) {
                            if (label.htmlFor && label.htmlFor.indexOf('mj_child_') === 0) {
                                label.htmlFor = label.htmlFor.replace(/mj_child_(?:__INDEX__|\d+)_/, 'mj_child_' + index + '_');
                            }
                        });
                    });
                }

                function updateChildAccountSection(card, type) {
                    const accountSection = card.querySelector('.mj-child-card__account');
                    const accountInputs = accountSection ? accountSection.querySelectorAll('input') : [];
                    const autonomousCheckbox = card.querySelector('.mj-child-autonomous-toggle input[type="checkbox"]');
                    const mustBeAutonomous = type === ROLE_JEUNE;
                    const isAutonomous = mustBeAutonomous ? true : (autonomousCheckbox ? autonomousCheckbox.checked : false);

                    if (autonomousCheckbox && mustBeAutonomous && !autonomousCheckbox.checked) {
                        autonomousCheckbox.checked = true;
                    }

                    if (!accountSection) {
                        return;
                    }

                    if (isAutonomous) {
                        accountSection.classList.remove('mj-hidden');
                    } else {
                        accountSection.classList.add('mj-hidden');
                    }

                    accountInputs.forEach(function (field) {
                        field.disabled = !isAutonomous;
                        if (isAutonomous) {
                            field.setAttribute('required', 'required');
                        } else {
                            field.removeAttribute('required');
                        }
                    });
                }

                function toggleGuardianFields(type) {
                    if (!guardianFieldset) {
                        return;
                    }

                    if (type === ROLE_JEUNE) {
                        guardianFieldset.classList.add('mj-hidden');
                    } else {
                        guardianFieldset.classList.remove('mj-hidden');
                    }

                    guardianFields.forEach(function (field) {
                        if (type === ROLE_JEUNE) {
                            field.dataset.previousDisabled = field.disabled ? '1' : '0';
                            field.disabled = true;
                        } else if (field.dataset.previousDisabled === '1') {
                            field.disabled = true;
                        } else {
                            field.disabled = false;
                        }

                        if (field.dataset.requiredIf === 'guardian') {
                            if (type === ROLE_JEUNE) {
                                field.dataset.previousRequired = field.required ? '1' : '0';
                                field.required = false;
                            } else if (field.dataset.guardianRequired === '1') {
                                field.required = true;
                            }
                        }
                    });
                }

                function toggleChildOptions(type) {
                    const cards = wrapper.querySelectorAll('.mj-child-card');
                    cards.forEach(function (card) {
                        const autonomousLabel = card.querySelector('.mj-child-autonomous-toggle');
                        const autonomousInput = autonomousLabel ? autonomousLabel.querySelector('input[type="checkbox"]') : null;
                        const emailField = card.querySelector('input[type="email"]');
                        if (autonomousLabel && autonomousInput) {
                            if (type === ROLE_JEUNE) {
                                autonomousLabel.classList.add('mj-hidden');
                                autonomousInput.checked = true;
                            } else {
                                autonomousLabel.classList.remove('mj-hidden');
                            }
                        }

                        if (emailField) {
                            if (type === ROLE_JEUNE) {
                                emailField.required = true;
                                emailField.setAttribute('required', 'required');
                            } else {
                                emailField.required = false;
                                emailField.removeAttribute('required');
                            }
                        }

                        updateChildAccountSection(card, type);
                    });

                    if (addButton) {
                        addButton.classList.toggle('mj-hidden', type === ROLE_JEUNE);
                    }
                }

                function toggleChildHeaders(type) {
                    const hide = type === ROLE_JEUNE;
                    wrapper.querySelectorAll('.mj-child-card__header').forEach(function (header) {
                        header.classList.toggle('mj-hidden', hide);
                    });
                }

                function applyRegistrationType(type) {
                    currentType = type === ROLE_JEUNE ? ROLE_JEUNE : 'guardian';
                    if (currentType === ROLE_JEUNE) {
                        const cards = wrapper.querySelectorAll('.mj-child-card');
                        cards.forEach(function (card, index) {
                            if (index > 0) {
                                card.remove();
                            }
                        });
                        refreshChildNumbers();
                    }
                    toggleGuardianFields(currentType);
                    toggleChildOptions(currentType);
                    toggleChildHeaders(currentType);
                }

                registrationRadios.forEach(function (radio) {
                    radio.addEventListener('change', function () {
                        applyRegistrationType(radio.value === ROLE_JEUNE ? ROLE_JEUNE : 'guardian');
                    });
                });

                function addChildCard() {
                    if (!template) {
                        return;
                    }

                    const nextIndex = wrapper.querySelectorAll('.mj-child-card').length;
                    const html = template.innerHTML.replace(/__INDEX__/g, String(nextIndex));
                    const container = document.createElement('div');
                    container.innerHTML = html.trim();
                    const card = container.firstElementChild;

                    if (card) {
                        wrapper.appendChild(card);
                        refreshChildNumbers();
                        toggleChildOptions(currentType);
                        toggleChildHeaders(currentType);
                        updateChildAccountSection(card, currentType);
                    }
                }

                wrapper.addEventListener('click', function (event) {
                    const target = event.target;
                    if (target.classList.contains('mj-child-card__remove')) {
                        event.preventDefault();
                        const card = target.closest('.mj-child-card');
                        if (!card) {
                            return;
                        }

                        if (wrapper.querySelectorAll('.mj-child-card').length <= 1) {
                            return;
                        }

                        card.remove();
                        refreshChildNumbers();
                        toggleChildOptions(currentType);
                        toggleChildHeaders(currentType);
                    }
                });

                wrapper.addEventListener('change', function (event) {
                    const target = event.target;
                    if (target && target.matches('.mj-child-autonomous-toggle input[type="checkbox"]')) {
                        const card = target.closest('.mj-child-card');
                        if (card) {
                            updateChildAccountSection(card, currentType);
                        }
                    }
                });

                if (addButton) {
                    addButton.addEventListener('click', function (event) {
                        event.preventDefault();
                        addChildCard();
                    });
                }

                refreshChildNumbers();
                applyRegistrationType(currentType);
            })();
        </script>
        <?php

        return ob_get_clean();
    }
}
