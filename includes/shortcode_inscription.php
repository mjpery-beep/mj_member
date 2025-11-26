<?php
if (!function_exists('mj_member_get_registration_type')) {
    function mj_member_get_registration_type() {
        $default = 'guardian';
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registration_type'])) {
            $type = sanitize_text_field(wp_unslash($_POST['registration_type']));
            return $type === 'jeune' ? 'jeune' : $default;
        }
        return $default;
    }
}

if (!function_exists('mj_member_send_registration_email')) {
    function mj_member_send_registration_email($member_id, $guardian = null) {
        if (!class_exists('MjMail') || !class_exists('MjMembers_CRUD')) {
            return false;
        }

        $member = is_object($member_id) ? $member_id : MjMembers_CRUD::getById((int) $member_id);
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
        $requires_guardian = ($registration_type !== 'jeune');
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

        $guardian_login_raw = sanitize_text_field(wp_unslash($_POST['guardian_user_login'] ?? ''));
        $guardian = array(
            'first_name'  => sanitize_text_field(wp_unslash($_POST['guardian_first_name'] ?? '')),
            'last_name'   => sanitize_text_field(wp_unslash($_POST['guardian_last_name'] ?? '')),
            'email'       => sanitize_email(wp_unslash($_POST['guardian_email'] ?? '')),
            'phone'       => sanitize_text_field(wp_unslash($_POST['guardian_phone'] ?? '')),
            'address'     => sanitize_text_field(wp_unslash($_POST['guardian_address'] ?? '')),
            'city'        => sanitize_text_field(wp_unslash($_POST['guardian_city'] ?? '')),
            'postal_code' => sanitize_text_field(wp_unslash($_POST['guardian_postal_code'] ?? '')),
            'status'      => MjMembers_CRUD::STATUS_ACTIVE,
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
                'user_password'       => (string) wp_unslash($raw_child['user_password'] ?? ''),
                'user_password_confirm' => (string) wp_unslash($raw_child['user_password_confirm'] ?? ''),
            );

            $is_empty = $child['first_name'] === '' && $child['last_name'] === '' && $child['birth_date'] === '' && $child['email'] === '';
            if ($is_empty) {
                continue;
            }

            if ($child['first_name'] === '' || $child['last_name'] === '' || $child['birth_date'] === '') {
                return array('success' => false, 'message' => "Chaque jeune doit avoir un prénom, un nom et une date de naissance.");
            }

            if ($registration_type === 'jeune') {
                if ($child['email'] === '' || !is_email($child['email'])) {
                    return array('success' => false, 'message' => "Merci de fournir une adresse email valide pour le jeune autonome.");
                }
            }

            $needs_account = ($registration_type === 'jeune') || !empty($child['is_autonomous']);
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

        if ($registration_type === 'jeune') {
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
            $wpdb = MjMembers_CRUD::getWpdb();
            $table_name = MjMembers_CRUD::getTableName(MjMembers_CRUD::TABLE_NAME);
            $existing_guardian_row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT id, wp_user_id FROM $table_name WHERE email = %s AND role = %s LIMIT 1",
                    $guardian['email'],
                    MjMembers_CRUD::ROLE_TUTEUR
                )
            );
            if ($existing_guardian_row) {
                $guardian_was_existing = true;
            }
        }

        $guardian_id = null;
        if ($requires_guardian) {
            $guardian_id = MjMembers_CRUD::upsertGuardian($guardian);
            if (!$guardian_id) {
                return array('success' => false, 'message' => "Impossible d'enregistrer le tuteur. Vérifiez les informations et réessayez.");
            }
        }

        $created_member_ids = array();
        $created_members = array();

        foreach ($children as $child) {
            if ($child['email'] !== '') {
                $existing = MjMembers_CRUD::search($child['email']);
                if (!empty($existing)) {
                    foreach ($existing as $record) {
                        if ((int) $record->id !== (int) $guardian_id && $record->role === MjMembers_CRUD::ROLE_JEUNE) {
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
                'role'                => MjMembers_CRUD::ROLE_JEUNE,
                'guardian_id'         => ($requires_guardian && empty($child['is_autonomous'])) ? $guardian_id : null,
                'is_autonomous'       => $registration_type === 'jeune' ? 1 : ($child['is_autonomous'] ? 1 : 0),
                'requires_payment'    => 1,
                'photo_usage_consent' => $child['photo_usage_consent'],
                'notes'               => $child['notes'],
                'status'              => MjMembers_CRUD::STATUS_ACTIVE,
            );

            $member_id = MjMembers_CRUD::create($payload);
            if (!$member_id) {
                foreach ($created_member_ids as $created_id) {
                    MjMembers_CRUD::delete($created_id);
                }

                return array('success' => false, 'message' => sprintf("Impossible d'enregistrer %s %s. Veuillez réessayer.", esc_html($child['first_name']), esc_html($child['last_name'])));
            }

            $created_member_ids[] = $member_id;
            $created_members[] = array(
                'id' => $member_id,
                'email' => $child['email'],
                'is_autonomous' => (int) $payload['is_autonomous'],
                'credentials' => (($registration_type === 'jeune') || !empty($payload['is_autonomous']))
                    ? array('user_login' => $child['user_login'], 'user_password' => $child['user_password'])
                    : null,
            );
        }

        $guardian_object = null;

        if ($requires_guardian && $guardian_id) {
            $guardian_object = MjMembers_CRUD::getById($guardian_id);
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
                        MjMembers_CRUD::delete($created_id);
                    }

                    if (!$guardian_was_existing && $guardian_id) {
                        MjMembers_CRUD::delete($guardian_id);
                    }

                    return array('success' => false, 'message' => $guardian_account_result->get_error_message());
                }

                $guardian_object = MjMembers_CRUD::getById($guardian_id);
            }
        }

        foreach ($created_members as $index => $entry) {
            if (empty($entry['credentials'])) {
                continue;
            }

            $member_record = MjMembers_CRUD::getById((int) $entry['id']);
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
                    MjMembers_CRUD::delete($created_id);
                }

                if ($requires_guardian && !$guardian_was_existing && $guardian_id) {
                    MjMembers_CRUD::delete($guardian_id);
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
                'is_autonomous'       => $registration_type === 'jeune' ? 1 : 0,
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
                'is_autonomous'       => ($registration_type === 'jeune') ? 1 : (!empty($raw_child['is_autonomous']) ? 1 : 0),
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
                'is_autonomous'       => $registration_type === 'jeune' ? 1 : 0,
                'photo_usage_consent' => 0,
                'notes'               => '',
                'user_login'          => '',
                'user_password'       => '',
                'user_password_confirm' => '',
            ));
        }

        if ($registration_type === 'jeune' && count($children) > 1) {
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

if (!function_exists('mj_inscription_shortcode')) {
    function mj_inscription_shortcode() {
        $result = mj_process_frontend_inscription();

        $registration_type = mj_member_get_registration_type();
        $guardian_values = mj_collect_guardian_form_values();
        $children_values = mj_collect_children_form_values();

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
        <div class="mj-inscription-container">
            <?php if ($result) : ?>
                <div class="mj-notice <?php echo !empty($result['success']) ? 'mj-notice--success' : 'mj-notice--error'; ?>">
                    <p><?php echo esc_html($result['message']); ?></p>
                </div>
            <?php endif; ?>

            <?php if (!$result || empty($result['success'])) : ?>
                <form method="post" class="mj-inscription-form" novalidate>
                    <?php wp_nonce_field('mj_frontend_form', 'mj_frontend_nonce'); ?>

                    <fieldset class="mj-fieldset" data-section="registration-type">
                        <legend>Type d'inscription</legend>
                        <label class="mj-radio">
                            <input type="radio" name="registration_type" value="guardian" <?php checked($registration_type, 'guardian'); ?> />
                            <span>Je suis un tuteur et j'inscris un ou plusieurs jeunes</span>
                        </label>
                        <label class="mj-radio">
                            <input type="radio" name="registration_type" value="jeune" <?php checked($registration_type, 'jeune'); ?> />
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
                                <?php echo mj_render_child_form_block($index, $values, $index > 0, $registration_type !== 'jeune'); ?>
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
                    </fieldset>

                    <div class="mj-form-actions">
                        <button type="submit" class="mj-button">Envoyer l'inscription</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>

        <script type="text/template" id="mj-child-template">
            <?php echo mj_render_child_form_block('__INDEX__', array(), true, $registration_type !== 'jeune'); ?>
        </script>

        <style>
            .mj-inscription-container {
                max-width: 920px;
                margin: 30px auto;
                padding: 20px;
                background: #f9f9f9;
                border-radius: 10px;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            }

            .mj-fieldset {
                border: none;
                margin: 0 0 30px;
                padding: 0 0 10px;
            }

            .mj-fieldset legend {
                font-size: 20px;
                font-weight: 600;
                color: #0073aa;
                margin-bottom: 20px;
            }

            .mj-field-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
                gap: 20px;
            }

            .mj-field-group {
                display: flex;
                flex-direction: column;
            }

            .mj-field-group--full {
                grid-column: 1 / -1;
            }

            .mj-field-group label {
                font-weight: 600;
                color: #333;
                margin-bottom: 6px;
            }

            .mj-field-group input,
            .mj-field-group textarea {
                padding: 10px 14px;
                border: 1px solid #d7d7d7;
                border-radius: 6px;
                font-size: 14px;
                font-family: inherit;
                transition: border-color 0.2s ease;
            }

            .mj-field-group input:focus,
            .mj-field-group textarea:focus {
                outline: none;
                border-color: #0073aa;
                box-shadow: 0 0 0 2px rgba(0, 115, 170, 0.15);
            }

            .mj-field-hint {
                font-size: 12px;
                color: #666;
                margin-top: 6px;
            }

            .mj-checkbox {
                display: flex;
                align-items: flex-start;
                gap: 10px;
                margin-bottom: 12px;
            }

            .mj-checkbox input {
                margin-top: 4px;
            }

            .mj-radio {
                display: flex;
                align-items: flex-start;
                gap: 10px;
                margin-bottom: 10px;
            }

            .mj-radio input {
                margin-top: 4px;
            }

            .mj-hidden {
                display: none !important;
            }

            #mj-children-wrapper {
                display: grid;
                gap: 20px;
            }

            .mj-child-card {
                background: #fff;
                border: 1px solid #e3e6ea;
                border-radius: 8px;
                padding: 18px;
                box-shadow: inset 0 0 0 1px rgba(0, 115, 170, 0.05);
            }

            .mj-child-card__header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                margin-bottom: 16px;
            }

            .mj-child-card__title {
                margin: 0;
                font-size: 18px;
                font-weight: 600;
                color: #333;
            }

            .mj-child-card__remove {
                border: none;
                background: transparent;
                color: #cc1d1d;
                font-size: 20px;
                cursor: pointer;
            }

            .mj-child-card__grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 18px;
                margin-bottom: 18px;
            }

            .mj-child-card__options {
                display: flex;
                flex-direction: column;
                gap: 10px;
                margin-bottom: 18px;
            }

            .mj-button {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                padding: 12px 24px;
                border-radius: 6px;
                border: none;
                background: #0073aa;
                color: #fff;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
                transition: background 0.2s ease, transform 0.2s ease;
            }

            .mj-button:hover {
                background: #005f8d;
                transform: translateY(-1px);
            }

            .mj-button:active {
                transform: translateY(0);
            }

            .mj-button--secondary {
                background: #f0f6fb;
                color: #0073aa;
                border: 1px dashed #aad0e6;
                margin-top: 12px;
            }

            .mj-button--secondary:hover {
                background: #e1eff8;
            }

            .mj-form-actions {
                text-align: right;
                margin-top: 30px;
            }

            .mj-notice {
                padding: 16px 20px;
                border-radius: 6px;
                margin-bottom: 24px;
                border-left: 4px solid;
            }

            .mj-notice--success {
                background: #e8f7ef;
                border-left-color: #1a7f37;
                color: #145126;
            }

            .mj-notice--error {
                background: #fcebea;
                border-left-color: #cc1d1d;
                color: #761b18;
            }

            @media (max-width: 640px) {
                .mj-form-actions {
                    text-align: center;
                }

                .mj-button {
                    width: 100%;
                }
            }
        </style>

        <script>
            (function () {
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
                if (checkedRadio && checkedRadio.value === 'jeune') {
                    currentType = 'jeune';
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
                    const mustBeAutonomous = type === 'jeune';
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

                    if (type === 'jeune') {
                        guardianFieldset.classList.add('mj-hidden');
                    } else {
                        guardianFieldset.classList.remove('mj-hidden');
                    }

                    guardianFields.forEach(function (field) {
                        if (type === 'jeune') {
                            field.dataset.previousDisabled = field.disabled ? '1' : '0';
                            field.disabled = true;
                        } else if (field.dataset.previousDisabled === '1') {
                            field.disabled = true;
                        } else {
                            field.disabled = false;
                        }

                        if (field.dataset.requiredIf === 'guardian') {
                            if (type === 'jeune') {
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
                            if (type === 'jeune') {
                                autonomousLabel.classList.add('mj-hidden');
                                autonomousInput.checked = true;
                            } else {
                                autonomousLabel.classList.remove('mj-hidden');
                            }
                        }

                        if (emailField) {
                            if (type === 'jeune') {
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
                        addButton.classList.toggle('mj-hidden', type === 'jeune');
                    }
                }

                function toggleChildHeaders(type) {
                    const hide = type === 'jeune';
                    wrapper.querySelectorAll('.mj-child-card__header').forEach(function (header) {
                        header.classList.toggle('mj-hidden', hide);
                    });
                }

                function applyRegistrationType(type) {
                    currentType = type === 'jeune' ? 'jeune' : 'guardian';
                    if (currentType === 'jeune') {
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
                        applyRegistrationType(radio.value === 'jeune' ? 'jeune' : 'guardian');
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

add_shortcode('mj_inscription', 'mj_inscription_shortcode');
