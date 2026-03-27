<?php
use Mj\Member\Classes\MjRoles;
use Mj\Member\Classes\Crud\MjDynamicFields;
use Mj\Member\Classes\Crud\MjDynamicFieldValues;

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
            return array('success' => false, 'message' => "Vous devez accepter le traitement de vos données personnelles.", 'error_fields' => array('rgpd_consent'));
        }

        $regulation_required = !empty($_POST['regulation_required']);
        if ($regulation_required && empty($_POST['regulation_consent'])) {
            return array('success' => false, 'message' => "Merci de lire et d'accepter le règlement intérieur avant de poursuivre.", 'error_fields' => array('regulation_consent'));
        }

        $guardian = array(
            'first_name'  => sanitize_text_field(wp_unslash($_POST['guardian_first_name'] ?? '')),
            'last_name'   => sanitize_text_field(wp_unslash($_POST['guardian_last_name'] ?? '')),
            'email'       => sanitize_email(wp_unslash($_POST['guardian_email'] ?? '')),
            'phone'       => sanitize_text_field(wp_unslash($_POST['guardian_phone'] ?? '')),
            'phone_secondary' => sanitize_text_field(wp_unslash($_POST['guardian_phone_secondary'] ?? '')),
            'address'     => sanitize_text_field(wp_unslash($_POST['guardian_address'] ?? '')),
            'city'        => sanitize_text_field(wp_unslash($_POST['guardian_city'] ?? '')),
            'postal_code' => sanitize_text_field(wp_unslash($_POST['guardian_postal_code'] ?? '')),
            'status'      => MjMembers::STATUS_ACTIVE,
            'user_login'  => '',
            'user_password' => (string) wp_unslash($_POST['guardian_user_password'] ?? ''),
            'user_password_confirm' => (string) wp_unslash($_POST['guardian_user_password_confirm'] ?? ''),
        );

        if ($requires_guardian) {
            $guardian_missing = array();
            if ($guardian['first_name'] === '') { $guardian_missing[] = 'guardian_first_name'; }
            if ($guardian['last_name']  === '') { $guardian_missing[] = 'guardian_last_name'; }
            if ($guardian['email']      === '') { $guardian_missing[] = 'guardian_email'; }
            if (!empty($guardian_missing)) {
                return array('success' => false, 'message' => "Merci de renseigner le prénom, le nom et l'adresse email du tuteur.", 'error_fields' => $guardian_missing);
            }

            if (!is_email($guardian['email'])) {
                return array('success' => false, 'message' => "L'adresse email du tuteur n'est pas valide.", 'error_fields' => array('guardian_email'));
            }

            $guardian_login_clean = sanitize_user($guardian['email'], true);
            if ($guardian_login_clean === '') {
                return array('success' => false, 'message' => "L'adresse email du tuteur ne peut pas être utilisée comme identifiant. Merci d'utiliser une autre adresse.", 'error_fields' => array('guardian_email'));
            }

            if (username_exists($guardian_login_clean)) {
                return array('success' => false, 'message' => "Un compte existe déjà avec cette adresse email. Utilisez la fonction mot de passe oublié ou contactez la MJ.", 'error_fields' => array('guardian_email'));
            }

            if ($guardian['user_password'] === '' || $guardian['user_password_confirm'] === '') {
                return array('success' => false, 'message' => "Merci de choisir un mot de passe pour le compte du tuteur.", 'error_fields' => array('guardian_user_password', 'guardian_user_password_confirm'));
            }

            if ($guardian['user_password'] !== $guardian['user_password_confirm']) {
                return array('success' => false, 'message' => "La confirmation du mot de passe du tuteur ne correspond pas.", 'error_fields' => array('guardian_user_password', 'guardian_user_password_confirm'));
            }

            if (strlen($guardian['user_password']) < $min_password_length) {
                return array('success' => false, 'message' => sprintf("Le mot de passe du tuteur doit contenir au moins %d caractères.", $min_password_length), 'error_fields' => array('guardian_user_password'));
            }

            $guardian_existing_user = get_user_by('email', $guardian['email']);
            if ($guardian_existing_user) {
                return array('success' => false, 'message' => "Un compte existe déjà avec cette adresse email. Utilisez la fonction mot de passe oublié ou contactez la MJ.", 'error_fields' => array('guardian_email'));
            }

            $guardian['user_login'] = $guardian_login_clean;
        }

        $children = array();
        $raw_children = isset($_POST['jeunes']) && is_array($_POST['jeunes']) ? $_POST['jeunes'] : array();
        if ($registration_type === MjRoles::JEUNE && empty($raw_children)) {
            $fallback_child_keys = array('first_name', 'last_name', 'birth_date', 'email', 'phone', 'user_login', 'user_password', 'user_password_confirm');
            $fallback_child = array();
            foreach ($fallback_child_keys as $fallback_key) {
                $fallback_child[$fallback_key] = wp_unslash($_POST['jeunes'][0][$fallback_key] ?? '');
            }
            if (implode('', $fallback_child) !== '') {
                $fallback_child['is_autonomous'] = 1;
                $raw_children = array($fallback_child);
            }
        }

        foreach ($raw_children as $child_index => $raw_child) {
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
                'user_login'          => sanitize_user(wp_unslash($raw_child['user_login'] ?? ''), true),
                'user_password'       => (string) wp_unslash($raw_child['user_password'] ?? ''),
                'user_password_confirm' => (string) wp_unslash($raw_child['user_password_confirm'] ?? ''),
                'notes'               => sanitize_textarea_field(wp_unslash($raw_child['notes'] ?? '')),
            );

            // Collect dynamic field values
            $child_dynfields = array();
            foreach (array_keys($raw_child) as $raw_key) {
                if (strpos($raw_key, 'dynfield_') === 0 && substr($raw_key, -6) !== '_other') {
                    $field_id = (int) substr($raw_key, 9);
                    if ($field_id > 0) {
                        $raw_val = $raw_child[$raw_key];
                        $other_key = $raw_key . '_other';
                        if (is_array($raw_val)) {
                            // checklist: array of values
                            $sanitized = array_map('sanitize_text_field', array_map('wp_unslash', $raw_val));
                            // replace __other placeholder with actual text
                            if (in_array('__other', $sanitized, true) && isset($raw_child[$other_key]) && trim($raw_child[$other_key]) !== '') {
                                $sanitized = array_map(function ($v) use ($raw_child, $other_key) {
                                    return $v === '__other' ? '__other:' . sanitize_text_field(wp_unslash($raw_child[$other_key])) : $v;
                                }, $sanitized);
                            } else {
                                $sanitized = array_filter($sanitized, function ($v) { return $v !== '__other'; });
                            }
                            $child_dynfields[$field_id] = wp_json_encode(array_values($sanitized));
                        } else {
                            $val = sanitize_text_field(wp_unslash($raw_val));
                            // handle __other for dropdown/radio
                            if ($val === '__other' && isset($raw_child[$other_key]) && trim($raw_child[$other_key]) !== '') {
                                $val = '__other:' . sanitize_text_field(wp_unslash($raw_child[$other_key]));
                            } elseif ($val === '__other') {
                                $val = '';
                            }
                            $child_dynfields[$field_id] = $val;
                        }
                    }
                }
            }
            $child['_dynfields'] = $child_dynfields;

            // Ignore parasite/empty child rows that don't contain the base identity fields.
            $has_identity_seed = $child['first_name'] !== '' || $child['last_name'] !== '' || $child['birth_date'] !== '';
            if (!$has_identity_seed) {
                continue;
            }

            $missing_identity_fields = array();
            if ($child['first_name'] === '') {
                $missing_identity_fields['first_name'] = 'le prénom';
            }
            if ($child['last_name'] === '') {
                $missing_identity_fields['last_name'] = 'le nom';
            }
            if ($child['birth_date'] === '') {
                $missing_identity_fields['birth_date'] = 'la date de naissance';
            }

            if (!empty($missing_identity_fields)) {
                $labels = array_values($missing_identity_fields);
                if (count($labels) === 1) {
                    $identity_message = sprintf('Merci de renseigner %s du jeune.', $labels[0]);
                } elseif (count($labels) === 2) {
                    $identity_message = sprintf('Merci de renseigner %s et %s du jeune.', $labels[0], $labels[1]);
                } else {
                    $identity_message = 'Merci de renseigner le prénom, le nom et la date de naissance du jeune.';
                }

                $error_fields = array();
                foreach (array_keys($missing_identity_fields) as $missing_key) {
                    $error_fields[] = sprintf('jeunes[%s][%s]', (string) $child_index, $missing_key);
                }

                return array(
                    'success' => false,
                    'message' => $identity_message,
                    'error_fields' => $error_fields,
                );
            }

            $needs_account = ($registration_type === MjRoles::JEUNE) || !empty($child['is_autonomous']);
            $child_login_clean = '';

            if ($needs_account) {
                // user_login is required — submitted from the form, already sanitized by the collect function
                $child_login_clean = $child['user_login'];
                if ($child_login_clean === '' && $child['email'] !== '') {
                    $child_login_clean = sanitize_user($child['email'], true);
                }
                if ($child_login_clean === '') {
                    return array('success' => false, 'message' => sprintf("Merci de renseigner un identifiant de connexion pour %s %s.", $child['first_name'], $child['last_name']), 'error_fields' => array(sprintf('jeunes[%s][user_login]', (string) $child_index)));
                }

                if (username_exists($child_login_clean)) {
                    return array('success' => false, 'message' => sprintf("L'identifiant de connexion de %s %s est déjà utilisé. Merci d'en choisir un autre.", $child['first_name'], $child['last_name']), 'error_fields' => array(sprintf('jeunes[%s][user_login]', (string) $child_index)));
                }

                if ($child['user_password'] === '' || $child['user_password_confirm'] === '') {
                    return array('success' => false, 'message' => sprintf("Merci de définir un mot de passe pour le compte de %s %s.", $child['first_name'], $child['last_name']), 'error_fields' => array(sprintf('jeunes[%s][user_password]', (string) $child_index), sprintf('jeunes[%s][user_password_confirm]', (string) $child_index)));
                }

                if ($child['user_password'] !== $child['user_password_confirm']) {
                    return array('success' => false, 'message' => sprintf("La confirmation du mot de passe de %s %s ne correspond pas.", $child['first_name'], $child['last_name']), 'error_fields' => array(sprintf('jeunes[%s][user_password]', (string) $child_index), sprintf('jeunes[%s][user_password_confirm]', (string) $child_index)));
                }

                if (strlen($child['user_password']) < $min_password_length) {
                    return array('success' => false, 'message' => sprintf("Le mot de passe de %s %s doit contenir au moins %d caractères.", $child['first_name'], $child['last_name'], $min_password_length), 'error_fields' => array(sprintf('jeunes[%s][user_password]', (string) $child_index)));
                }

                if ($child['email'] !== '' && get_user_by('email', $child['email'])) {
                    return array('success' => false, 'message' => sprintf("Un compte existe déjà avec l'adresse email %s. Utilisez la fonction mot de passe oublié.", $child['email']), 'error_fields' => array(sprintf('jeunes[%s][email]', (string) $child_index)));
                }
            }

            $child['user_login'] = $child_login_clean;

            // Validate required dynamic fields
            $dyn_reg_fields_for_validation = MjDynamicFields::getRegistrationFields();
            foreach ($dyn_reg_fields_for_validation as $df_check) {
                if ($df_check->field_type === 'title') continue; // section headers have no value
                if ((int) $df_check->is_required) {
                    $submitted_val = $child_dynfields[(int) $df_check->id] ?? '';
                    // For checklist, check the JSON array is non-empty
                    if ($df_check->field_type === 'checklist') {
                        $arr = json_decode($submitted_val, true);
                        $is_empty_val = !is_array($arr) || count($arr) === 0;
                    } else {
                        $is_empty_val = $submitted_val === '';
                    }
                    if ($is_empty_val) {
                        return array('success' => false, 'message' => sprintf(
                            'Le champ « %s » est obligatoire pour %s %s.',
                            esc_html($df_check->title),
                            esc_html($child['first_name']),
                            esc_html($child['last_name'])
                        ));
                    }
                }
            }

            $children[] = $child;
        }

        if (empty($children)) {
            if ($registration_type === MjRoles::JEUNE) {
                return array(
                    'success' => false,
                    'message' => "Merci de renseigner le prénom, le nom et la date de naissance du jeune.",
                    'error_fields' => array(
                        'jeunes[0][first_name]',
                        'jeunes[0][last_name]',
                        'jeunes[0][birth_date]',
                    ),
                );
            }

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

            // Save dynamic field values for this member
            if (!empty($child['_dynfields'])) {
                MjDynamicFieldValues::saveBulk($member_id, $child['_dynfields']);
            }

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
                if (!empty($guardian['phone_secondary'])) {
                    $body .= sprintf("Téléphone secondaire: %s\n", $guardian['phone_secondary']);
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
                if (!empty($guardian['phone_secondary'])) {
                    $plain_parts[] = $guardian['phone_secondary'];
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
                if (!empty($guardian['phone_secondary'])) {
                    $html_rows[] = '<strong>Téléphone secondaire :</strong> ' . esc_html($guardian['phone_secondary']);
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
                '{{guardian_phone_secondary}}' => $requires_guardian ? ($guardian['phone_secondary'] ?? '') : '',
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

        // ── Link Grimlins avatar to newly created members ──
        $grimlins_avatar_id = isset($_POST['grimlins_avatar_id']) ? absint($_POST['grimlins_avatar_id']) : 0;
        if ($grimlins_avatar_id > 0 && !empty($created_member_ids)) {
            $attachment = get_post($grimlins_avatar_id);
            $has_grimlins_flag = $attachment && (bool) get_post_meta($grimlins_avatar_id, '_mj_member_photo_grimlins', true);
            if ($has_grimlins_flag && class_exists(MjMembers::class)) {
                // Assign to the first created member (primary child or autonomous user)
                $target_member_id = (int) $created_member_ids[0];
                MjMembers::update($target_member_id, array('photo_id' => $grimlins_avatar_id));
                update_post_meta($grimlins_avatar_id, '_mj_member_photo_grimlins_member', $target_member_id);
                do_action('mj_member_grimlins_avatar_applied_on_registration', $target_member_id, $grimlins_avatar_id);
            }
        }

        // Determine the WordPress user to auto-login after registration
        $login_user_id = 0;
        if ($requires_guardian && $guardian_object && !empty($guardian_object->wp_user_id)) {
            $login_user_id = (int) $guardian_object->wp_user_id;
        } elseif (!$requires_guardian && !empty($created_member_ids)) {
            $first_auto_member = MjMembers::getById((int) $created_member_ids[0]);
            if ($first_auto_member && !empty($first_auto_member->wp_user_id)) {
                $login_user_id = (int) $first_auto_member->wp_user_id;
            }
        }

        return array('success' => true, 'message' => "Merci ! Votre demande a bien été enregistrée. Nous reviendrons vers vous très prochainement.", 'login_user_id' => $login_user_id);
    }
}

if (!function_exists('mj_collect_guardian_form_values')) {
    function mj_collect_guardian_form_values() {
        $defaults = array(
            'first_name'  => '',
            'last_name'   => '',
            'email'       => '',
            'phone'       => '',
            'phone_secondary' => '',
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
            'phone_secondary' => sanitize_text_field(wp_unslash($_POST['guardian_phone_secondary'] ?? '')),
            'address'     => sanitize_text_field(wp_unslash($_POST['guardian_address'] ?? '')),
            'city'        => sanitize_text_field(wp_unslash($_POST['guardian_city'] ?? '')),
            'postal_code' => sanitize_text_field(wp_unslash($_POST['guardian_postal_code'] ?? '')),
            'user_login'  => '',
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
                'user_login'          => sanitize_user(wp_unslash($raw_child['user_login'] ?? ''), true),
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
    function mj_render_child_form_block($index, array $values, $allow_remove = true, $show_header = true, array $complementary_options = array()) {
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

        $complementary_defaults = array(
            'toggle_label' => __('Données complémentaires', 'mj-member'),
            'helper_text' => __('Ces informations sont facultatives et pourront être complétées ou modifiées plus tard depuis votre espace membre.', 'mj-member'),
            'icon_url' => '',
            'icon_alt' => __('Attention', 'mj-member'),
        );

        $complementary_options = wp_parse_args($complementary_options, $complementary_defaults);

        $values = wp_parse_args($values, $defaults);
        $index_attr = esc_attr($index);
        $is_autonomous = (int) $values['is_autonomous'] === 1;

        $header_classes = 'mj-child-card__header' . ($show_header ? '' : ' mj-hidden');
        $account_classes = 'mj-child-card__account' . ($is_autonomous ? '' : ' mj-hidden');
        $complementary_panel_id = 'mj_child_' . $index_attr . '_complementary_panel';

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
                    <input type="email" id="mj_child_<?php echo $index_attr; ?>_email" name="jeunes[<?php echo $index_attr; ?>][email]" value="<?php echo esc_attr($values['email']); ?>" data-child-email-field />
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
            
            <?php
            // Dynamic fields (registration)
            $dyn_reg_fields = MjDynamicFields::getRegistrationFields();
            if (!empty($dyn_reg_fields)) :
            ?>
            <div class="mj-child-card__complementary" data-complementary-container>
                <div class="mj-child-complementary-header">
                    <button
                        type="button"
                        class="mj-button mj-button--secondary mj-child-complementary-toggle"
                        data-complementary-toggle
                        aria-expanded="false"
                        aria-controls="<?php echo esc_attr($complementary_panel_id); ?>"
                    >
                        <?php echo esc_html($complementary_options['toggle_label']); ?>
                    </button>
                    <p class="mj-field-hint mj-child-complementary-hint">
                        <?php if (!empty($complementary_options['icon_url'])) : ?>
                            <img src="<?php echo esc_url($complementary_options['icon_url']); ?>" alt="<?php echo esc_attr($complementary_options['icon_alt']); ?>" loading="lazy" />
                        <?php endif; ?>
                        <span class="mj-child-complementary-hint__content">
                            <span class="mj-child-complementary-hint__title"><?php esc_html_e('Information', 'mj-member'); ?></span>
                            <span class="mj-child-complementary-hint__text"><?php echo esc_html($complementary_options['helper_text']); ?></span>
                        </span>
                    </p>
                </div>
                <div class="mj-child-card__complementary-panel mj-hidden" id="<?php echo esc_attr($complementary_panel_id); ?>" data-complementary-panel>
            <div class="mj-child-card__grid mj-child-card__dynfields">
                <?php foreach ($dyn_reg_fields as $df) :
                    $df_id    = (int) $df->id;
                    $df_slug  = esc_attr($df->slug);
                    $df_name  = 'jeunes[' . $index_attr . '][dynfield_' . $df_id . ']';
                    $df_html  = 'mj_child_' . $index_attr . '_dynfield_' . $df_id;
                    $df_req   = (int) $df->is_required;
                    $df_val   = isset($values['dynfield_' . $df_id]) ? $values['dynfield_' . $df_id] : '';
                    $df_opts  = MjDynamicFields::decodeOptions($df->options_list);
                    $df_label = esc_html($df->title) . ($df_req ? ' *' : '');
                    $df_desc  = $df->description ? '<small class="mj-field-hint">' . esc_html($df->description) . '</small>' : '';

                    // Section title — close grid, render heading, reopen grid
                    if ($df->field_type === 'title') : ?>
                        </div>
                        <h5 class="mj-dynfield-section-title"><?php echo esc_html($df->title); ?></h5>
                        <?php if ($df->description) : ?>
                            <p class="mj-dynfield-section-desc"><?php echo esc_html($df->description); ?></p>
                        <?php endif; ?>
                        <div class="mj-child-card__grid mj-child-card__dynfields">
                    <?php continue; endif; ?>
                <?php
                    // Decode checklist/other value helpers
                    $df_allow_other = (int) ($df->allow_other ?? 0);
                    $df_other_label = ($df->other_label ?? '') !== '' ? $df->other_label : 'Autre';
                    $df_is_other    = false;
                    $df_other_text  = '';
                    $df_checked_arr = array();

                    if ($df->field_type === 'checklist') {
                        $df_checked_arr = is_string($df_val) && $df_val !== '' ? json_decode($df_val, true) : array();
                        if (!is_array($df_checked_arr)) $df_checked_arr = array();
                        // extract __other: entry
                        foreach ($df_checked_arr as $ck => $cv) {
                            if (strpos($cv, '__other:') === 0) {
                                $df_other_text = substr($cv, 8);
                                $df_is_other = true;
                                unset($df_checked_arr[$ck]);
                                break;
                            }
                        }
                    } elseif (in_array($df->field_type, array('dropdown', 'radio'), true) && $df_allow_other) {
                        if (strpos($df_val, '__other:') === 0) {
                            $df_other_text = substr($df_val, 8);
                            $df_is_other = true;
                        }
                    }
                ?>
                <div class="mj-field-group mj-field-group--dyn<?php echo in_array($df->field_type, array('textarea', 'checklist'), true) ? ' mj-field-group--full' : ''; ?>">
                    <?php if ($df->field_type === 'text') : ?>
                        <label for="<?php echo $df_html; ?>"><?php echo $df_label; ?></label>
                        <?php echo $df_desc; ?>
                        <input type="text" id="<?php echo $df_html; ?>" name="<?php echo $df_name; ?>" value="<?php echo esc_attr($df_val); ?>" <?php echo $df_req ? 'required' : ''; ?> />
                    <?php elseif ($df->field_type === 'textarea') : ?>
                        <label for="<?php echo $df_html; ?>"><?php echo $df_label; ?></label>
                        <?php echo $df_desc; ?>
                        <textarea id="<?php echo $df_html; ?>" name="<?php echo $df_name; ?>" rows="3" <?php echo $df_req ? 'required' : ''; ?>><?php echo esc_textarea($df_val); ?></textarea>
                    <?php elseif ($df->field_type === 'dropdown') : ?>
                        <label for="<?php echo $df_html; ?>"><?php echo $df_label; ?></label>
                        <?php echo $df_desc; ?>
                        <select id="<?php echo $df_html; ?>" name="<?php echo $df_name; ?>" <?php echo $df_req ? 'required' : ''; ?> <?php echo $df_allow_other ? 'data-dynfield-other="1"' : ''; ?>>
                            <option value="">— Sélectionnez —</option>
                            <?php foreach ($df_opts as $opt) : ?>
                                <option value="<?php echo esc_attr($opt); ?>" <?php selected(!$df_is_other ? $df_val : '', $opt); ?>><?php echo esc_html($opt); ?></option>
                            <?php endforeach; ?>
                            <?php if ($df_allow_other) : ?>
                                <option value="__other" <?php selected($df_is_other); ?>><?php echo esc_html($df_other_label); ?>…</option>
                            <?php endif; ?>
                        </select>
                        <?php if ($df_allow_other) : ?>
                            <input type="text" class="mj-dynfield-other-input" name="<?php echo $df_name; ?>_other" value="<?php echo esc_attr($df_other_text); ?>" placeholder="Précisez…" style="<?php echo $df_is_other ? '' : 'display:none;'; ?> margin-top:6px;" />
                        <?php endif; ?>
                    <?php elseif ($df->field_type === 'radio') : ?>
                        <fieldset>
                            <legend><?php echo $df_label; ?></legend>
                            <?php echo $df_desc; ?>
                            <?php foreach ($df_opts as $oi => $opt) : ?>
                                <label class="mj-radio">
                                    <input type="radio" name="<?php echo $df_name; ?>" value="<?php echo esc_attr($opt); ?>" <?php checked(!$df_is_other ? $df_val : '', $opt); ?> <?php echo ($df_req && $oi === 0 && !$df_allow_other) ? 'required' : ''; ?> />
                                    <span><?php echo esc_html($opt); ?></span>
                                </label>
                            <?php endforeach; ?>
                            <?php if ($df_allow_other) : ?>
                                <label class="mj-radio">
                                    <input type="radio" name="<?php echo $df_name; ?>" value="__other" <?php checked($df_is_other); ?> <?php echo ($df_req && empty($df_opts)) ? 'required' : ''; ?> />
                                    <span><?php echo esc_html($df_other_label); ?></span>
                                </label>
                                <input type="text" class="mj-dynfield-other-input" name="<?php echo $df_name; ?>_other" value="<?php echo esc_attr($df_other_text); ?>" placeholder="Précisez…" style="<?php echo $df_is_other ? '' : 'display:none;'; ?> margin-top:4px;" />
                            <?php endif; ?>
                        </fieldset>
                    <?php elseif ($df->field_type === 'checklist') : ?>
                        <fieldset>
                            <legend><?php echo $df_label; ?></legend>
                            <?php echo $df_desc; ?>
                            <?php foreach ($df_opts as $opt) : ?>
                                <label class="mj-checkbox">
                                    <input type="checkbox" name="<?php echo $df_name; ?>[]" value="<?php echo esc_attr($opt); ?>" <?php checked(in_array($opt, $df_checked_arr, true)); ?> />
                                    <span><?php echo esc_html($opt); ?></span>
                                </label>
                            <?php endforeach; ?>
                            <?php if ($df_allow_other) : ?>
                                <label class="mj-checkbox">
                                    <input type="checkbox" name="<?php echo $df_name; ?>[]" value="__other" <?php checked($df_is_other); ?> />
                                    <span><?php echo esc_html($df_other_label); ?></span>
                                </label>
                                <input type="text" class="mj-dynfield-other-input" name="<?php echo $df_name; ?>_other" value="<?php echo esc_attr($df_other_text); ?>" placeholder="Précisez…" style="<?php echo $df_is_other ? '' : 'display:none;'; ?> margin-top:4px;" />
                            <?php endif; ?>
                        </fieldset>
                    <?php elseif ($df->field_type === 'checkbox') : ?>
                        <label class="mj-checkbox">
                            <input type="checkbox" name="<?php echo $df_name; ?>" value="1" <?php checked($df_val, '1'); ?> <?php echo $df_req ? 'required' : ''; ?> />
                            <span><?php echo $df_label; ?></span>
                        </label>
                        <?php echo $df_desc; ?>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
                </div>
            </div>
            <?php endif; ?>
            <div class="<?php echo esc_attr($account_classes); ?>" data-autonomous-section="1">
                <h5>Accès du jeune</h5>
                <div class="mj-field-group">
                    <label for="mj_child_<?php echo $index_attr; ?>_user_login">Identifiant de connexion *</label>
                    <input type="text" id="mj_child_<?php echo $index_attr; ?>_user_login" name="jeunes[<?php echo $index_attr; ?>][user_login]" value="<?php echo esc_attr($values['user_login']); ?>" required autocomplete="username" data-child-login-input />
                    <p class="mj-field-hint">Cet identifiant vous servira à vous connecter à votre espace personnel sur la plateforme.</p>
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

if (!function_exists('mj_ajax_inscription_handler')) {
    function mj_ajax_inscription_handler() {
        $result = mj_process_frontend_inscription();

        if ($result === null) {
            wp_send_json_error(array(
                'message' => __('Requête invalide.', 'mj-member'),
            ));
        }

        if (!empty($result['success'])) {
            $did_login = false;

            // Resolve the WP user to auto-login: look up directly by login or email so we
            // are not dependent on the wp_user_id column being refreshed in the same request.
            $registration_type = sanitize_text_field(wp_unslash($_POST['registration_type'] ?? ''));
            $login_user = null;

            if ($registration_type === MjRoles::JEUNE) {
                // Jeune autonome: find by child user_login submitted in the form.
                $raw_children = isset($_POST['jeunes']) && is_array($_POST['jeunes']) ? $_POST['jeunes'] : array();
                foreach ($raw_children as $raw_child) {
                    if (!is_array($raw_child)) {
                        continue;
                    }
                    $candidate_login = sanitize_user(wp_unslash($raw_child['user_login'] ?? ''), true);
                    if ($candidate_login !== '') {
                        $login_user = get_user_by('login', $candidate_login);
                        break;
                    }
                }
            } else {
                // Tuteur: find by guardian email (used as user_email on WP account creation).
                $guardian_email = sanitize_email(wp_unslash($_POST['guardian_email'] ?? ''));
                if ($guardian_email !== '') {
                    $login_user = get_user_by('email', $guardian_email);
                }
            }

            // Last-resort fallback: use the user ID returned by the registration process.
            if (!($login_user instanceof \WP_User) && !empty($result['login_user_id'])) {
                $login_user = get_user_by('ID', (int) $result['login_user_id']);
            }

            if ($login_user instanceof \WP_User) {
                wp_clear_auth_cookie();
                wp_set_current_user($login_user->ID);
                wp_set_auth_cookie($login_user->ID, true);
                do_action('wp_login', $login_user->user_login, $login_user);
                $did_login = true;
            }

            $result['did_login'] = $did_login ? 1 : 0;
        }

        unset($result['login_user_id']);

        if (!empty($result['success'])) {
            wp_send_json_success($result);
        }

        wp_send_json_error($result);
    }
}

add_action('wp_ajax_nopriv_mj_member_ajax_register', 'mj_ajax_inscription_handler');
add_action('wp_ajax_mj_member_ajax_register', 'mj_ajax_inscription_handler');

if (!function_exists('mj_member_render_registration_form')) {
    function mj_member_render_registration_form($args = array()) {
        $args = is_array($args) ? $args : array();

        // ── Grimlins avatar passed via query param or sessionStorage ──
        $grimlins_avatar_id = 0;
        $grimlins_avatar_url = '';
        if (!empty($_GET['grimlins_avatar'])) {
            $grimlins_avatar_id = absint($_GET['grimlins_avatar']);
            if ($grimlins_avatar_id > 0) {
                $src = wp_get_attachment_image_url($grimlins_avatar_id, 'medium');
                $grimlins_avatar_url = $src ? $src : '';
                if ($grimlins_avatar_url === '') {
                    $grimlins_avatar_id = 0;
                }
            }
        } elseif (!empty($_GET['grimlins_avatar_url'])) {
            $grimlins_avatar_url = esc_url_raw(wp_unslash($_GET['grimlins_avatar_url']));
        }

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
            'registration_options' => array(
                'guardian' => array(
                    'image_id' => 0,
                    'image_url' => '',
                    'image_alt' => '',
                ),
                'member' => array(
                    'image_id' => 0,
                    'image_url' => '',
                    'image_alt' => '',
                ),
            ),
            'complementary_data' => array(
                'icon_id' => 0,
                'icon_url' => '',
                'icon_alt' => '',
            ),
            'account_url' => home_url('/mon-compte'),
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

        // Handle login tab title settings
        $login_title_defaults = array(
            'show' => false,
            'text' => __('Se connecter', 'mj-member'),
            'image_id' => 0,
            'image_url' => '',
            'image_alt' => '',
            'image_position' => 'inline-right',
        );

        $login_title_settings = is_array($args['login_title'] ?? null) ? $args['login_title'] : array();
        $login_title_settings = wp_parse_args($login_title_settings, $login_title_defaults);
        $login_title_image_position = isset($login_title_settings['image_position']) ? (string) $login_title_settings['image_position'] : 'inline-right';
        if (!in_array($login_title_image_position, $allowed_title_image_positions, true)) {
            $login_title_image_position = 'inline-right';
        }
        $args['login_title'] = array(
            'show' => !empty($login_title_settings['show']),
            'text' => isset($login_title_settings['text']) ? (string) $login_title_settings['text'] : $login_title_defaults['text'],
            'image_id' => isset($login_title_settings['image_id']) ? (int) $login_title_settings['image_id'] : 0,
            'image_url' => isset($login_title_settings['image_url']) ? (string) $login_title_settings['image_url'] : '',
            'image_alt' => isset($login_title_settings['image_alt']) ? (string) $login_title_settings['image_alt'] : '',
            'image_position' => $login_title_image_position,
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

        $registration_option_defaults = array(
            'image_id' => 0,
            'image_url' => '',
            'image_alt' => '',
        );
        $registration_options_defaults = array(
            'guardian' => $registration_option_defaults,
            'member' => $registration_option_defaults,
        );
        $registration_options_settings = is_array($args['registration_options'] ?? null) ? $args['registration_options'] : array();
        $registration_options_settings = wp_parse_args($registration_options_settings, $registration_options_defaults);

        $normalized_registration_options = array();
        foreach (array('guardian', 'member') as $option_key) {
            $option_settings = is_array($registration_options_settings[$option_key] ?? null) ? $registration_options_settings[$option_key] : array();
            $option_settings = wp_parse_args($option_settings, $registration_option_defaults);
            $normalized_registration_options[$option_key] = array(
                'image_id' => isset($option_settings['image_id']) ? (int) $option_settings['image_id'] : 0,
                'image_url' => isset($option_settings['image_url']) ? esc_url_raw((string) $option_settings['image_url']) : '',
                'image_alt' => isset($option_settings['image_alt']) ? sanitize_text_field((string) $option_settings['image_alt']) : '',
            );
        }
        $args['registration_options'] = $normalized_registration_options;

        $complementary_data_defaults = array(
            'icon_id' => 0,
            'icon_url' => '',
            'icon_alt' => '',
        );
        $complementary_data_settings = is_array($args['complementary_data'] ?? null) ? $args['complementary_data'] : array();
        $complementary_data_settings = wp_parse_args($complementary_data_settings, $complementary_data_defaults);
        $args['complementary_data'] = array(
            'icon_id' => isset($complementary_data_settings['icon_id']) ? (int) $complementary_data_settings['icon_id'] : 0,
            'icon_url' => isset($complementary_data_settings['icon_url']) ? esc_url_raw((string) $complementary_data_settings['icon_url']) : '',
            'icon_alt' => isset($complementary_data_settings['icon_alt']) ? sanitize_text_field((string) $complementary_data_settings['icon_alt']) : '',
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
        $registration_type_selected = ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registration_type']));
        $guardian_values = mj_collect_guardian_form_values();
        $children_values = mj_collect_children_form_values();

        $show_form = !$already_registered;
        if ($show_form && $result && !empty($result['success']) && !is_user_logged_in()) {
            $show_form = false;
        }

        if ($result && !empty($result['success'])) {
            $registration_type = 'guardian';
            $registration_type_selected = false;
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

        $guardian_option_image_url = (string) ($args['registration_options']['guardian']['image_url'] ?? '');
        $guardian_option_image_alt = (string) ($args['registration_options']['guardian']['image_alt'] ?? '');
        if ($guardian_option_image_alt === '') {
            $guardian_option_image_alt = __('Je suis un tuteur', 'mj-member');
        }

        $member_option_image_url = (string) ($args['registration_options']['member']['image_url'] ?? '');
        $member_option_image_alt = (string) ($args['registration_options']['member']['image_alt'] ?? '');
        if ($member_option_image_alt === '') {
            $member_option_image_alt = __('Je suis un jeune autonome', 'mj-member');
        }

        $complementary_icon_url = (string) ($args['complementary_data']['icon_url'] ?? '');
        $complementary_icon_alt = (string) ($args['complementary_data']['icon_alt'] ?? '');
        if ($complementary_icon_alt === '') {
            $complementary_icon_alt = __('Information', 'mj-member');
        }
        $complementary_form_options = array(
            'toggle_label' => __('Données complémentaires', 'mj-member'),
            'helper_text' => __('Ces informations sont facultatives et pourront être complétées ou modifiées plus tard depuis votre espace membre.', 'mj-member'),
            'icon_url' => $complementary_icon_url,
            'icon_alt' => $complementary_icon_alt,
        );

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

        // Determine initial active tab from URL parameter
        $initial_tab = 'register';
        if (isset($_GET['tab'])) {
            $tab_param = sanitize_text_field(wp_unslash($_GET['tab']));
            if (in_array($tab_param, array('register', 'login'), true)) {
                $initial_tab = $tab_param;
            }
        }
        ?>
        <div class="mj-inscription-container">
            <!-- Tabs Navigation -->
            <div class="mj-inscription-tabs-nav">
                <button type="button" class="mj-inscription-tab-button <?php echo $initial_tab === 'register' ? 'mj-inscription-tab-button--active' : ''; ?>" data-tab="register" aria-selected="<?php echo $initial_tab === 'register' ? 'true' : 'false'; ?>">
                    <?php esc_html_e('Devenir membre', 'mj-member'); ?>
                </button>
                <button type="button" class="mj-inscription-tab-button <?php echo $initial_tab === 'login' ? 'mj-inscription-tab-button--active' : ''; ?>" data-tab="login" aria-selected="<?php echo $initial_tab === 'login' ? 'true' : 'false'; ?>">
                    <?php esc_html_e('Se connecter', 'mj-member'); ?>
                </button>
            </div>
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
                <!-- Registration Tab -->
                <div class="mj-inscription-tab-content <?php echo $initial_tab === 'register' ? 'mj-inscription-tab-content--active' : ''; ?>" data-tab-panel="register" role="tabpanel">
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
                <?php if ($grimlins_avatar_url !== '') : ?>
                    <div class="mj-inscription-grimlins-avatar">
                        <img src="<?php echo esc_url($grimlins_avatar_url); ?>" alt="<?php esc_attr_e('Ton avatar Grimlins', 'mj-member'); ?>" class="mj-inscription-grimlins-avatar__img" />
                        <p class="mj-inscription-grimlins-avatar__label"><?php esc_html_e('Ton avatar Grimlins sera lié à ton compte après l\'inscription.', 'mj-member'); ?></p>
                    </div>
                <?php endif; ?>
                <form method="post" class="mj-inscription-form" novalidate
                    data-ajax-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
                    data-ajax-action="mj_member_ajax_register"
                    data-account-url="<?php echo esc_url($args['account_url'] ?? home_url('/mon-compte')); ?>">
                    <?php wp_nonce_field('mj_frontend_form', 'mj_frontend_nonce'); ?>
                    <?php if ($grimlins_avatar_id > 0) : ?>
                        <input type="hidden" name="grimlins_avatar_id" value="<?php echo esc_attr($grimlins_avatar_id); ?>" />
                    <?php endif; ?>
                    <?php if ($grimlins_avatar_url !== '') : ?>
                        <input type="hidden" name="grimlins_avatar_url" value="<?php echo esc_url($grimlins_avatar_url); ?>" />
                    <?php endif; ?>

                    <fieldset class="mj-fieldset" data-section="registration-type">
                        <legend>Type d'inscription</legend>
                        <div class="mj-registration-type-selector" data-registration-selector>
                            <button
                                type="button"
                                class="mj-registration-type-card<?php echo ($registration_type_selected && $registration_type !== MjRoles::JEUNE) ? ' mj-registration-type-card--active' : ''; ?>"
                                data-registration-type-value="guardian"
                                aria-pressed="<?php echo ($registration_type_selected && $registration_type !== MjRoles::JEUNE) ? 'true' : 'false'; ?>"
                            >
                                <?php if ($guardian_option_image_url !== '') : ?>
                                    <span class="mj-registration-type-card__media">
                                        <img src="<?php echo esc_url($guardian_option_image_url); ?>" alt="<?php echo esc_attr($guardian_option_image_alt); ?>" loading="lazy" />
                                    </span>
                                <?php endif; ?>
                                <span class="mj-registration-type-card__content">
                                    <span class="mj-registration-type-card__title">Je suis un tuteur</span>
                                    <span class="mj-registration-type-card__description">J'inscris un ou plusieurs jeunes.</span>
                                </span>
                            </button>

                            <button
                                type="button"
                                class="mj-registration-type-card<?php echo ($registration_type_selected && $registration_type === MjRoles::JEUNE) ? ' mj-registration-type-card--active' : ''; ?>"
                                data-registration-type-value="<?php echo esc_attr(MjRoles::JEUNE); ?>"
                                aria-pressed="<?php echo ($registration_type_selected && $registration_type === MjRoles::JEUNE) ? 'true' : 'false'; ?>"
                            >
                                <?php if ($member_option_image_url !== '') : ?>
                                    <span class="mj-registration-type-card__media">
                                        <img src="<?php echo esc_url($member_option_image_url); ?>" alt="<?php echo esc_attr($member_option_image_alt); ?>" loading="lazy" />
                                    </span>
                                <?php endif; ?>
                                <span class="mj-registration-type-card__content">
                                    <span class="mj-registration-type-card__title">Je suis un jeune autonome</span>
                                    <span class="mj-registration-type-card__description">Je m'inscris moi-même.</span>
                                </span>
                            </button>
                        </div>
                        <input type="hidden" name="registration_type" value="<?php echo $registration_type_selected ? esc_attr($registration_type) : ''; ?>" data-registration-type-input />
                        <p class="mj-field-hint mj-registration-type-selector__hint" data-registration-type-hint>Choisissez cette option si vous avez 18 ans ou plus et que vous n'avez pas de tuteur.</p>
                    </fieldset>

                    <div class="mj-registration-form-panels<?php echo $registration_type_selected ? ' mj-registration-form-panels--active' : ''; ?>" data-registration-form-panels>
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
                                <input type="email" id="guardian_email" name="guardian_email" value="<?php echo esc_attr($guardian_values['email']); ?>" required data-required-if="guardian" autocomplete="email" />
                                <p class="mj-field-hint">Cette adresse email sera utilisée comme identifiant de connexion du tuteur.</p>
                            </div>
                            <div class="mj-field-group">
                                <label for="guardian_phone">Téléphone</label>
                                <input type="tel" id="guardian_phone" name="guardian_phone" value="<?php echo esc_attr($guardian_values['phone']); ?>" />
                            </div>
                            <div class="mj-field-group">
                                <label for="guardian_phone_secondary">Téléphone secondaire</label>
                                <input type="tel" id="guardian_phone_secondary" name="guardian_phone_secondary" value="<?php echo esc_attr($guardian_values['phone_secondary']); ?>" />
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
                                <?php echo mj_render_child_form_block($index, $values, $index > 0, $registration_type !== MjRoles::JEUNE, $complementary_form_options); ?>
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

                    </div>

                    <div class="mj-form-actions">
                        <button type="submit" class="mj-button">Envoyer l'inscription</button>
                    </div>
                </form>
                </div>

                <!-- Login Tab -->
                <div class="mj-inscription-tab-content <?php echo $initial_tab === 'login' ? 'mj-inscription-tab-content--active' : ''; ?>" data-tab-panel="login" role="tabpanel">
                    <?php if (!empty($args['login_title']['show']) && (!empty($args['login_title']['text']) || !empty($args['login_title']['image_url']))) : ?>
                        <?php
                        $login_title_image_position = isset($args['login_title']['image_position']) ? (string) $args['login_title']['image_position'] : 'inline-right';
                        if (!in_array($login_title_image_position, array('inline-right', 'inline-left', 'above-center', 'above-left', 'above-right'), true)) {
                            $login_title_image_position = 'inline-right';
                        }
                        $login_title_has_image = !empty($args['login_title']['image_url']);
                        $login_title_header_classes = array('mj-inscription-container__header');
                        if ($login_title_has_image) {
                            $login_title_header_classes[] = 'mj-inscription-container__header--has-image';
                            $login_title_header_classes[] = 'mj-inscription-container__header--image-' . $login_title_image_position;
                            if (in_array($login_title_image_position, array('above-left', 'above-center', 'above-right'), true)) {
                                $login_title_header_classes[] = 'mj-inscription-container__header--stack';
                            }
                        }
                        $login_render_image_first = $login_title_has_image && in_array($login_title_image_position, array('above-left', 'above-center', 'above-right'), true);
                        $login_header_class_attr = implode(' ', array_map('sanitize_html_class', $login_title_header_classes));
                        ?>
                        <div class="<?php echo esc_attr($login_header_class_attr); ?>">
                            <?php if ($login_title_has_image && $login_render_image_first) :
                                $login_title_image_alt = $args['login_title']['image_alt'] !== '' ? $args['login_title']['image_alt'] : (isset($args['login_title']['text']) ? $args['login_title']['text'] : '');
                            ?>
                                <div class="mj-inscription-container__title-image">
                                    <img src="<?php echo esc_url($args['login_title']['image_url']); ?>" alt="<?php echo esc_attr($login_title_image_alt); ?>" />
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($args['login_title']['text'])) : ?>
                                <h2 class="mj-inscription-container__title"><?php echo esc_html($args['login_title']['text']); ?></h2>
                            <?php endif; ?>
                            <?php if ($login_title_has_image && !$login_render_image_first) :
                                $login_title_image_alt = $args['login_title']['image_alt'] !== '' ? $args['login_title']['image_alt'] : (isset($args['login_title']['text']) ? $args['login_title']['text'] : '');
                            ?>
                                <div class="mj-inscription-container__title-image">
                                    <img src="<?php echo esc_url($args['login_title']['image_url']); ?>" alt="<?php echo esc_attr($login_title_image_alt); ?>" />
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <form method="post" action="<?php echo esc_url(wp_login_url(home_url('/mon-compte'))); ?>" class="mj-inscription-login-form" novalidate>
                        <div class="mj-field-group">
                            <label for="user_login"><?php esc_html_e('Adresse email ou identifiant', 'mj-member'); ?></label>
                            <input type="text" id="user_login" name="log" required />
                        </div>
                        <div class="mj-field-group">
                            <label for="user_pass"><?php esc_html_e('Mot de passe', 'mj-member'); ?></label>
                            <input type="password" id="user_pass" name="pwd" required />
                        </div>
                        <div class="mj-field-group mj-field-group--remember-row">
                            <label class="mj-checkbox mj-checkbox--remember">
                                <input type="checkbox" name="rememberme" value="forever" id="rememberme" />
                                <span class="mj-checkbox__label"><?php esc_html_e('Se souvenir de moi', 'mj-member'); ?></span>
                            </label>
                            <a class="mj-lost-password-link" href="<?php echo esc_url(wp_lostpassword_url()); ?>"><?php esc_html_e('Mot de passe oublié ?', 'mj-member'); ?></a>
                        </div>
                        <div class="mj-form-actions">
                            <button type="submit" class="mj-button"><?php esc_html_e('Se connecter', 'mj-member'); ?></button>
                        </div>
                    </form>
                </div>
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
            <?php echo mj_render_child_form_block('__INDEX__', array(), true, $registration_type !== MjRoles::JEUNE, $complementary_form_options); ?>
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

            .mj-inscription-grimlins-avatar {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 10px;
                margin-bottom: 24px;
                padding: 20px;
                border-radius: 16px;
                background: linear-gradient(135deg, rgba(124, 58, 237, 0.08), rgba(167, 139, 250, 0.12));
                border: 1px solid rgba(124, 58, 237, 0.18);
            }

            .mj-inscription-grimlins-avatar__img {
                width: 120px;
                height: 120px;
                border-radius: 50%;
                object-fit: cover;
                border: 3px solid rgba(124, 58, 237, 0.3);
                box-shadow: 0 4px 16px rgba(124, 58, 237, 0.15);
            }

            .mj-inscription-grimlins-avatar__label {
                margin: 0;
                font-size: 14px;
                font-weight: 500;
                color: #6d28d9;
                text-align: center;
            }

            .mj-inscription-tab-content .mj-inscription-form {
                margin: 0;
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

            .mj-registration-type-selector {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 16px;
            }

            .mj-registration-type-card {
                display: flex;
                flex-direction: column;
                gap: 12px;
                align-items: stretch;
                width: 100%;
                text-align: left;
                padding: 14px;
                border-radius: 14px;
                border: 1px solid rgba(148, 163, 184, 0.35);
                background: #ffffff;
                color: #0f172a;
                cursor: pointer;
                transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease, background-color 0.2s ease;
            }

            .mj-registration-type-card:hover {
                transform: translateY(-2px);
                border-color: rgba(37, 99, 235, 0.45);
                box-shadow: 0 14px 28px rgba(15, 23, 42, 0.1);
            }

            .mj-registration-type-card:focus-visible {
                outline: 2px solid var(--mj-accent);
                outline-offset: 2px;
            }

            .mj-registration-type-card__media {
                display: flex;
                align-items: center;
                justify-content: center;
                overflow: hidden;
                border-radius: 10px;
                background: rgba(248, 250, 252, 0.9);
                min-height: 110px;
                aspect-ratio: 16 / 9;
                padding: 8px;
            }

            .mj-registration-type-card__media img {
                display: block;
                width: 100%;
                height: 100%;
                object-fit: contain;
                object-position: center;
            }

            .mj-registration-type-card__content {
                display: flex;
                flex-direction: column;
                gap: 4px;
            }

            .mj-registration-type-card__title {
                font-size: 16px;
                font-weight: 700;
                line-height: 1.25;
            }

            .mj-registration-type-card__description {
                font-size: 14px;
                color: #475569;
                line-height: 1.4;
            }

            .mj-registration-type-card--active,
            .mj-registration-type-card[aria-pressed="true"] {
                border-color: var(--mj-accent);
                background: linear-gradient(165deg, rgba(37, 99, 235, 0.12) 0%, rgba(37, 99, 235, 0.04) 100%);
                box-shadow: 0 16px 28px rgba(37, 99, 235, 0.18);
            }

            .mj-registration-type-card--active .mj-registration-type-card__title,
            .mj-registration-type-card[aria-pressed="true"] .mj-registration-type-card__title {
                color: var(--mj-accent-dark);
            }

            .mj-registration-type-selector__hint {
                margin-top: 14px;
            }

            .mj-registration-type-selector__hint.mj-registration-type-selector__hint--error {
                color: #b91c1c;
                font-weight: 600;
            }

            .mj-registration-form-panels {
                display: none;
            }

            .mj-registration-form-panels.mj-registration-form-panels--active {
                display: block;
            }

            .mj-registration-form-panels + .mj-form-actions {
                display: none;
            }

            .mj-registration-form-panels.mj-registration-form-panels--active + .mj-form-actions {
                display: block;
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

            /* Dynamic-field section titles */
            .mj-dynfield-section-title {
                font-size: 16px;
                font-weight: 700;
                color: #0f172a;
                margin: 8px 0 2px;
                padding-bottom: 4px;
                border-bottom: 2px solid var(--mj-accent, #3b82f6);
            }

            .mj-dynfield-section-desc {
                font-size: 13px;
                color: #6b7280;
                margin: 0 0 6px;
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

            /* ── Dynamic-field group improvements ── */
            .mj-child-card__dynfields {
                gap: 16px 24px;
            }

            .mj-field-group--dyn {
                margin-bottom: 10px;
                background: rgba(248, 250, 252, 0.8);
                border: 1px solid rgba(148, 163, 184, 0.2);
                border-radius: 12px;
                padding: 16px;
                gap: 6px;
                transition: border-color 0.2s ease, box-shadow 0.2s ease;
            }

            .mj-field-group--dyn:hover {
                border-color: rgba(148, 163, 184, 0.35);
            }

            .mj-field-group--dyn:focus-within {
                border-color: var(--mj-accent);
                box-shadow: 0 0 0 3px var(--mj-accent-soft);
            }

            .mj-field-group--dyn > label:first-child,
            .mj-field-group--dyn > fieldset > legend {
                font-weight: 600;
                font-size: 13.5px;
                color: #0f172a;
                margin-bottom: 0;
            }

            .mj-field-group--dyn > .mj-field-hint,
            .mj-field-group--dyn > fieldset > .mj-field-hint {
                display: block;
                font-size: 12.5px;
                line-height: 1.4;
                color: #64748b;
                margin: 0 0 4px;
            }

            .mj-field-group--dyn > fieldset {
                border: none;
                padding: 0;
                margin: 0;
            }

            .mj-field-group--dyn > fieldset > legend {
                padding: 0;
                margin-bottom: 2px;
            }

            .mj-field-group--dyn .mj-checkbox span,
            .mj-field-group--dyn .mj-radio span {
                font-size: 14px;
                font-weight: 400;
                color: #334155;
            }

            .mj-field-group--dyn .mj-checkbox {
                font-size: 14px;
            }

            .mj-field-group--remember-row {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 16px;
                margin: 0;
            }

            .mj-checkbox {
                display: flex;
                align-items: center;
                gap: 12px;
                margin-bottom: 0;
                font-size: 15px;
                color: #0f172a;
                flex: 1;
            }

            .mj-checkbox input {
                margin-top: 0;
                width: 24px;
                height: 24px;
                border-radius: 8px;
                border: 2px solid rgba(148, 163, 184, 0.8);
                background: #fff;
                -webkit-appearance: none;
                appearance: none;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                transition: all 0.2s ease;
                cursor: pointer;
                flex-shrink: 0;
            }

            .mj-checkbox input:hover:not(:checked) {
                border-color: var(--mj-accent);
                background: rgba(37, 99, 235, 0.04);
            }

            .mj-checkbox input:focus-visible {
                outline: 2px solid var(--mj-accent);
                outline-offset: 2px;
                border-color: var(--mj-accent);
            }

            .mj-checkbox input:checked {
                border-color: var(--mj-accent);
                background: var(--mj-accent);
                box-shadow: 0 6px 18px rgba(37, 99, 235, 0.24);
                color: #fff;
            }

            .mj-checkbox input:checked::after {
                content: '\2713';
                font-size: 15px;
                font-weight: bold;
                line-height: 1;
            }

            .mj-checkbox__label {
                cursor: pointer;
                font-weight: 500;
                user-select: none;
            }

            .mj-checkbox--remember {
                margin-bottom: 0;
            }

            @media (max-width: 768px) {
                .mj-field-group--remember-row {
                    flex-direction: column;
                    align-items: flex-start;
                    gap: 12px;
                }

                .mj-lost-password-link {
                    align-self: flex-start;
                }
            }

            .mj-radio {
                display: flex;
                align-items: flex-start;
                gap: 10px;
                margin-bottom: 6px;
                padding: 8px 12px;
                background: rgba(248, 250, 252, 0.8);
                border-radius: 10px;
                border: 1px solid rgba(148, 163, 184, 0.25);
                transition: border-color 0.2s ease, background 0.2s ease;
                cursor: pointer;
            }

            .mj-radio span {
                font-size: 14px;
                font-weight: 400;
                color: #334155;
            }

            .mj-radio input {
                margin-top: 3px;
            }

            .mj-radio:hover {
                border-color: var(--mj-accent);
                background: rgba(37, 99, 235, 0.06);
            }

            /* Dropdown / select styling inside dynamic fields */
            .mj-field-group--dyn select {
                padding: 10px 14px;
                border: 1px solid rgba(148, 163, 184, 0.55);
                border-radius: 12px;
                font-size: 14px;
                font-family: inherit;
                background: rgba(255, 255, 255, 0.95) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath d='M6 8L1 3h10z' fill='%2364748b'/%3E%3C/svg%3E") no-repeat right 14px center;
                background-size: 12px;
                appearance: none;
                -webkit-appearance: none;
                color: #0f172a;
                cursor: pointer;
                box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
                transition: border-color 0.2s ease, box-shadow 0.2s ease;
            }

            .mj-field-group--dyn select:focus {
                outline: none;
                border-color: var(--mj-accent);
                box-shadow: 0 0 0 4px var(--mj-accent-soft);
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

            .mj-child-card__dynfields {
                grid-template-columns: 1fr;
            }

            .mj-dynfield-other-input {
                display: block;
                width: 100%;
                max-width: 400px;
                padding: 6px 10px;
                font-size: 14px;
                border: 1px solid #d1d5db;
                border-radius: 6px;
                box-sizing: border-box;
                margin-top: 6px;
            }

            .mj-child-card__options {
                display: flex;
                flex-direction: column;
                gap: 12px;
                margin-bottom: 20px;
            }

            .mj-child-card__complementary {
                position: relative;
                margin-bottom: 20px;
            }

            .mj-child-complementary-header {
                display: flex;
                align-items: center;
                gap: 14px;
                flex-wrap: nowrap;
            }
            .mj-child-complementary-toggle:focus {
                color: var(--mj-accent-dark);
            }
            .mj-child-complementary-toggle {
                margin-top: 0;
                width: auto;
                border-radius: 12px;
                transition: background 0.2s ease, border-color 0.2s ease, border-radius 0.2s ease, box-shadow 0.2s ease;
            }

            .mj-child-complementary-toggle[aria-expanded="true"] {
                background: #eff6ff;
                border-color: rgba(37, 99, 235, 0.48);
                border-bottom-color: transparent;
                border-bottom-left-radius: 0;
                border-bottom-right-radius: 0;
                box-shadow: 0 -2px 0 rgba(37, 99, 235, 0.1);
            }

            .mj-child-complementary-hint {
                display: flex;
                align-items: flex-start;
                gap: 8px;
                margin-top: 0;
                margin-bottom: 0;
                padding: 0 2px;
                transition: color 0.2s ease, opacity 0.2s ease, transform 0.2s ease;
            }

            .mj-child-complementary-hint__content {
                display: flex;
                flex-direction: column;
                gap: 2px;
            }

            .mj-child-complementary-hint__title {
                font-weight: 700;
                color: #1e3a8a;
                line-height: 1.2;
            }

            .mj-child-complementary-hint__text {
                line-height: 1.4;
            }

            .mj-child-complementary-hint img {
                width: 28px;
                height: 28px;
                object-fit: contain;
                flex: 0 0 auto;
            }

            .mj-child-card__complementary-panel {
                border: 1px solid rgba(37, 99, 235, 0.28);
                background: linear-gradient(180deg, #f8fbff 0%, #ffffff 100%);
                border-radius: 0 14px 14px 14px;
                padding: 14px 14px 2px;
                box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.7), 0 10px 24px rgba(29, 78, 216, 0.08);
            }

            .mj-child-complementary-toggle[aria-expanded="true"] + .mj-child-complementary-hint {
                color: #1d4ed8;
                opacity: 0;
                visibility: hidden;
                transform: translateY(-2px);
            }

            .mj-child-complementary-toggle[aria-expanded="true"] ~ .mj-child-card__complementary-panel {
                margin-top: 0;
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
                color: var(--mj-accent);
                background: rgba(37, 99, 235, 0.14);
                border-color: rgba(37, 99, 235, 0.52);
                /** no box shadow */
                box-shadow: none;
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

                .mj-registration-type-selector {
                    grid-template-columns: 1fr;
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

            /* Tabs Styles */
            .mj-inscription-tabs-nav {
                display: flex;
                gap: 12px;
                margin-bottom: 32px;
                background: linear-gradient(135deg, rgba(255, 255, 255, 0.5) 0%, rgba(239, 246, 255, 0.5) 100%);
                padding: 8px;
                border-radius: 16px;
                border: 1px solid rgba(208, 218, 240, 0.5);
                box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
            }

            .mj-inscription-tab-button {
                flex: 1;
                padding: 14px 20px;
                border: 1px solid transparent;
                background: transparent;
                color: var(--mj-muted);
                font-weight: 500;
                font-size: 15px;
                cursor: pointer;
                position: relative;
                transition: all 0.3s ease;
                border-radius: 12px;
                text-align: center;
            }

            .mj-inscription-tab-button:hover {
                color: #1f2937;
                background: rgba(255, 255, 255, 0.6);
                border-color: rgba(208, 218, 240, 0.3);
            }

            .mj-inscription-tab-button--active,
            .mj-inscription-tab-button[aria-selected="true"] {
                color: #ffffff;
                background: linear-gradient(135deg, var(--mj-accent) 0%, var(--mj-accent-dark) 100%);
                border-color: var(--mj-accent);
                font-weight: 600;
                box-shadow: 0 8px 16px rgba(37, 99, 235, 0.3);
            }

            .mj-inscription-tab-content {
                display: none;
                opacity: 0;
                transition: opacity 0.3s ease;
            }

            .mj-inscription-tab-content--active {
                display: block;
                opacity: 1;
            }

            .mj-inscription-login-form {
                display: grid;
                gap: 18px;
                background: #ffffff;
                border-radius: 18px;
                padding: 30px;
                border: 1px solid rgba(208, 218, 240, 0.75);
                box-shadow: 0 22px 48px rgba(15, 23, 42, 0.08);
                max-width: 450px;
                margin: 0 auto;
            }

            .mj-inscription-login-form .mj-field-group {
                margin: 0;
            }

            .mj-inscription-login-form .mj-field-group label {
                display: block;
                font-weight: 600;
                font-size: 14px;
                margin-bottom: 8px;
                color: #1f2937;
            }

            .mj-inscription-login-form .mj-field-group input {
                width: 100%;
                padding: 10px 12px;
                border-radius: 8px;
                border: 1px solid var(--mj-border);
                
                transition: border-color 0.2s ease, box-shadow 0.2s ease;
                font-size: 14px;
            }

            .mj-inscription-login-form .mj-field-group input:focus {
                border-color: var(--mj-accent);
                box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
                outline: none;
                
            }

            .mj-inscription-login-form .mj-field-group--inline {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
                flex-wrap: wrap;
            }

            .mj-inscription-login-form .mj-checkbox {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                font-weight: 500;
                font-size: 14px;
                cursor: pointer;
                margin: 0;
            }

            .mj-inscription-login-form .mj-checkbox input {
                width: auto;
                margin: 0;
            }

            .mj-inscription-login-form .mj-lost-password-link {
                color: var(--mj-accent);
                text-decoration: none;
                font-weight: 500;
                font-size: 14px;
            }

            .mj-inscription-login-form .mj-lost-password-link:hover {
                text-decoration: underline;
            }

            .mj-inscription-login-form .mj-form-actions {
                margin-top: 12px;
            }

            .mj-inscription-login-form .mj-button {
                width: 100%;
                padding: 12px 24px;
                font-size: 15px;
                font-weight: 600;
            }

            @media (max-width: 768px) {
                .mj-inscription-tabs-nav {
                    gap: 8px;
                    padding: 6px;
                    margin-bottom: 24px;
                }

                .mj-inscription-tab-button {
                    padding: 12px 12px;
                    font-size: 14px;
                }

                .mj-inscription-tab-button--active,
                .mj-inscription-tab-button[aria-selected="true"] {
                    box-shadow: 0 6px 12px rgba(37, 99, 235, 0.2);
                }

                .mj-inscription-login-form {
                    padding: 24px;
                }
            }
                @media (max-width: 768px) {
                    .mj-inscription-tabs-nav {
                        gap: 8px;
                        padding: 6px;
                        margin-bottom: 24px;
                    }

                    .mj-inscription-tab-button {
                        padding: 12px 12px;
                        font-size: 14px;
                    }

                    .mj-inscription-tab-button--active,
                    .mj-inscription-tab-button[aria-selected="true"] {
                        box-shadow: 0 6px 12px rgba(37, 99, 235, 0.2);
                    }

                    .mj-inscription-login-form {
                        padding: 24px;
                    }
                }

                /* ── Registration success overlay ───────────────────────────── */
                .mj-reg-success-overlay {
                    position: fixed;
                    inset: 0;
                    background: rgba(15, 23, 42, 0.72);
                    backdrop-filter: blur(6px);
                    -webkit-backdrop-filter: blur(6px);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    z-index: 99999;
                    animation: mjRegFadeIn 0.3s ease;
                }
                @keyframes mjRegFadeIn {
                    from { opacity: 0; }
                    to   { opacity: 1; }
                }
                .mj-reg-success-card {
                    background: #ffffff;
                    border-radius: 24px;
                    padding: 48px 40px 40px;
                    max-width: 460px;
                    width: calc(100% - 32px);
                    text-align: center;
                    box-shadow: 0 24px 64px rgba(0, 0, 0, 0.22);
                    animation: mjRegSlideUp 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
                }
                @keyframes mjRegSlideUp {
                    from { transform: translateY(40px) scale(0.95); opacity: 0; }
                    to   { transform: translateY(0)     scale(1);    opacity: 1; }
                }
                .mj-reg-success-check {
                    width: 68px;
                    height: 68px;
                    background: linear-gradient(135deg, #22c55e, #16a34a);
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin: 0 auto 24px;
                    box-shadow: 0 8px 28px rgba(34, 197, 94, 0.4);
                }
                .mj-reg-success-card h2 {
                    font-size: 1.45rem;
                    font-weight: 700;
                    color: #0f172a;
                    margin: 0 0 12px;
                }
                .mj-reg-success-card p {
                    color: #64748b;
                    font-size: 0.95rem;
                    line-height: 1.65;
                    margin: 0 0 32px;
                }
                .mj-reg-success-countdown {
                    position: relative;
                    width: 88px;
                    height: 88px;
                    margin: 0 auto 28px;
                }
                .mj-reg-success-ring {
                    width: 88px;
                    height: 88px;
                    transform: rotate(-90deg);
                }
                .mj-reg-success-ring__track {
                    fill: none;
                    stroke: #e2e8f0;
                    stroke-width: 5;
                }
                .mj-reg-success-ring__fill {
                    fill: none;
                    stroke: #3b82f6;
                    stroke-width: 5;
                    stroke-linecap: round;
                    transition: stroke-dashoffset 0.95s linear;
                }
                .mj-reg-success-countdown__number {
                    position: absolute;
                    inset: 0;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 1.75rem;
                    font-weight: 700;
                    color: #1e40af;
                }
                .mj-reg-success__go-btn {
                    display: inline-flex;
                    align-items: center;
                    gap: 8px;
                    padding: 13px 30px;
                    background: linear-gradient(135deg, #3b82f6, #2563eb);
                    color: #ffffff;
                    border-radius: 50px;
                    font-weight: 600;
                    font-size: 0.95rem;
                    text-decoration: none;
                    border: none;
                    cursor: pointer;
                    box-shadow: 0 4px 14px rgba(37, 99, 235, 0.45);
                    transition: transform 0.15s ease, box-shadow 0.15s ease;
                }
                .mj-reg-success__go-btn:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 8px 22px rgba(37, 99, 235, 0.55);
                }
                /* Spinner on submit button while loading */
                .mj-inscription-form button[aria-busy="true"] {
                    opacity: 0.7;
                    cursor: not-allowed;
                    pointer-events: none;
                }
                .mj-field-error {
                    border-color: #dc2626 !important;
                    background: #fff5f5 !important;
                    box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.18) !important;
                }
                .mj-field-group--error > label,
                .mj-field-group--error > legend {
                    color: #dc2626;
                    font-weight: 600;
                }
                .mj-checkbox--error {
                    background: #fff5f5;
                    border: 1.5px solid #dc2626;
                    border-radius: 8px;
                    padding: 10px 12px;
                    box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.12);
                }
                .mj-checkbox--error > span {
                    color: #dc2626;
                    font-weight: 600;
                }
                .mj-checkbox--error > input[type="checkbox"] {
                    accent-color: #dc2626;
                    outline: 2px solid #dc2626;
                    outline-offset: 2px;
                }
                /* ── Toast d'erreur centré ─────────────────────── */
                .mj-error-toast {
                    position: fixed;
                    top: 28px;
                    left: 50%;
                    transform: translateX(-50%);
                    z-index: 999999;
                    background: #dc2626;
                    color: #fff;
                    padding: 14px 28px;
                    border-radius: 12px;
                    font-size: 0.95rem;
                    font-weight: 500;
                    line-height: 1.5;
                    box-shadow: 0 8px 32px rgba(220, 38, 38, 0.38);
                    max-width: min(480px, calc(100vw - 32px));
                    text-align: center;
                    pointer-events: none;
                    animation: mjToastIn 0.3s cubic-bezier(0.34, 1.56, 0.64, 1) both;
                }
                .mj-error-toast--out {
                    animation: mjToastOut 0.4s ease forwards;
                }
                @keyframes mjToastIn {
                    from { opacity: 0; transform: translateX(-50%) translateY(-20px); }
                    to   { opacity: 1; transform: translateX(-50%) translateY(0); }
                }
                @keyframes mjToastOut {
                    from { opacity: 1; transform: translateX(-50%) translateY(0); }
                    to   { opacity: 0; transform: translateX(-50%) translateY(-20px); }
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
                const registrationForm = document.querySelector('.mj-inscription-form');
                const guardianFieldset = document.querySelector('[data-section="guardian"]');
                const registrationTypeButtons = document.querySelectorAll('[data-registration-type-value]');
                const registrationTypeInput = document.querySelector('[data-registration-type-input]');
                const registrationTypeHint = document.querySelector('[data-registration-type-hint]');
                const registrationFormPanels = document.querySelector('[data-registration-form-panels]');
                const guardianFields = guardianFieldset ? Array.from(guardianFieldset.querySelectorAll('input, textarea, select')) : [];
                let currentType = registrationTypeInput ? (registrationTypeInput.value || '') : '';
                    // ── Show AJAX error as centered toast + highlight fields ──────────
                    function mjClearErrorHighlights() {
                        if (!registrationForm) { return; }
                        registrationForm.querySelectorAll('.mj-field-error').forEach(function (f) {
                            f.classList.remove('mj-field-error');
                            f.removeAttribute('aria-invalid');
                        });
                        registrationForm.querySelectorAll('.mj-field-group--error').forEach(function (g) {
                            g.classList.remove('mj-field-group--error');
                        });
                        registrationForm.querySelectorAll('.mj-checkbox--error').forEach(function (c) {
                            c.classList.remove('mj-checkbox--error');
                        });
                    }

                    function mjShowAjaxError(message, submitButton, errorFields) {
                        // Remove existing toast
                        var existingToast = document.querySelector('.mj-error-toast');
                        if (existingToast) { existingToast.remove(); }

                        // Remove legacy inline notice (backward compat)
                        var existing = registrationForm ? registrationForm.querySelector('.mj-ajax-error-notice') : null;
                        if (existing) { existing.remove(); }

                        // Clear previous highlights
                        mjClearErrorHighlights();

                        // Show toast centered on screen
                        var toast = document.createElement('div');
                        toast.className = 'mj-error-toast';
                        toast.textContent = message;
                        document.body.appendChild(toast);

                        var toastTimer = setTimeout(function () {
                            toast.classList.add('mj-error-toast--out');
                            setTimeout(function () { if (toast.parentNode) { toast.remove(); } }, 420);
                        }, 5000);

                        // Highlight error fields + their parent group/checkbox
                        if (registrationForm && Array.isArray(errorFields) && errorFields.length > 0) {
                            var firstField = null;
                            errorFields.forEach(function (fieldName) {
                                if (typeof fieldName !== 'string' || fieldName === '') { return; }
                                var safeName = fieldName.replace(/\\/g, '\\\\').replace(/"/g, '\\"');
                                var field = registrationForm.querySelector('[name="' + safeName + '"]');
                                if (field) {
                                    field.classList.add('mj-field-error');
                                    field.setAttribute('aria-invalid', 'true');
                                    var group = field.closest('.mj-field-group');
                                    if (group) { group.classList.add('mj-field-group--error'); }
                                    var checkbox = field.closest('.mj-checkbox');
                                    if (checkbox) { checkbox.classList.add('mj-checkbox--error'); }
                                    if (!firstField) { firstField = field; }
                                }
                            });
                            if (firstField && typeof firstField.focus === 'function') {
                                firstField.focus();
                            }
                        }

                        if (submitButton) {
                            submitButton.disabled = false;
                            submitButton.removeAttribute('aria-busy');
                        }
                    }

                    // ── Clear error highlight when user interacts with a field ────────
                    if (registrationForm) {
                        registrationForm.addEventListener('input', function (e) {
                            var field = e.target;
                            if (!field) { return; }
                            if (field.classList.contains('mj-field-error')) {
                                field.classList.remove('mj-field-error');
                                field.removeAttribute('aria-invalid');
                                var group = field.closest('.mj-field-group');
                                if (group) { group.classList.remove('mj-field-group--error'); }
                            }
                        });
                        registrationForm.addEventListener('change', function (e) {
                            var field = e.target;
                            if (!field) { return; }
                            // For checkboxes the error class sits on the label.mj-checkbox wrapper
                            var checkbox = field.closest('.mj-checkbox');
                            if (checkbox && checkbox.classList.contains('mj-checkbox--error')) {
                                checkbox.classList.remove('mj-checkbox--error');
                                field.removeAttribute('aria-invalid');
                            }
                            // Also handle regular inputs that fire 'change'
                            if (field.classList.contains('mj-field-error')) {
                                field.classList.remove('mj-field-error');
                                field.removeAttribute('aria-invalid');
                                var group = field.closest('.mj-field-group');
                                if (group) { group.classList.remove('mj-field-group--error'); }
                            }
                        });
                    }

                    // ── Registration success overlay with countdown ────────────────────
                    function mjShowRegistrationSuccess(accountUrl, didLogin) {
                        var DURATION = 5;
                        var radius = 19;
                        var circumference = +(2 * Math.PI * radius).toFixed(2);
                        var successMsg = didLogin
                            ? 'Merci\u00a0! Votre demande a bien \u00e9t\u00e9 enregistr\u00e9e. Vous \u00eates maintenant connect\u00e9 et serez redirig\u00e9 vers votre espace membre automatiquement.'
                            : 'Merci\u00a0! Votre demande a bien \u00e9t\u00e9 enregistr\u00e9e. Rendez-vous sur la page de connexion pour acc\u00e9der \u00e0 votre espace membre.';

                        var overlay = document.createElement('div');
                        overlay.className = 'mj-reg-success-overlay';
                        overlay.setAttribute('role', 'alert');
                        overlay.innerHTML =
                            '<div class="mj-reg-success-card">' +
                            '<div class="mj-reg-success-check">' +
                            '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>' +
                            '</div><h2>Inscription\u00a0confirm\u00e9e\u00a0!</h2>' +
                            '<p>' + successMsg + '</p>' +
                            '<div class="mj-reg-success-countdown" aria-hidden="true">' +
                            '<svg class="mj-reg-success-ring" viewBox="0 0 44 44">' +
                            '<circle class="mj-reg-success-ring__track" cx="22" cy="22" r="' + radius + '"/>' +
                            '<circle class="mj-reg-success-ring__fill" cx="22" cy="22" r="' + radius + '" stroke-dasharray="' + circumference + '" stroke-dashoffset="0"/>' +
                            '</svg>' +
                            '<strong class="mj-reg-success-countdown__number">' + DURATION + '</strong>' +
                            '</div>' +
                            '<button type="button" class="mj-reg-success__go-btn">Acc\u00e9der maintenant \u2192</button>' +
                            '</div>';

                        document.body.appendChild(overlay);

                        var ringFill   = overlay.querySelector('.mj-reg-success-ring__fill');
                        var countdownEl = overlay.querySelector('.mj-reg-success-countdown__number');
                        var goBtn      = overlay.querySelector('.mj-reg-success__go-btn');

                        function buildRedirect() {
                            var base = accountUrl || window.location.href;
                            return base + (base.indexOf('?') !== -1 ? '&' : '?') + 'new_subscription=1';
                        }
                        function doRedirect() { window.location.href = buildRedirect(); }

                        var secondsLeft = DURATION;
                        var interval = setInterval(function () {
                            secondsLeft--;
                            if (countdownEl) { countdownEl.textContent = secondsLeft; }
                            if (ringFill) {
                                ringFill.style.strokeDashoffset = String(+(circumference * (DURATION - secondsLeft) / DURATION).toFixed(2));
                            }
                            if (secondsLeft <= 0) { clearInterval(interval); doRedirect(); }
                        }, 1000);

                        if (goBtn) { goBtn.addEventListener('click', function () { clearInterval(interval); doRedirect(); }); }
                    }

                    // ── AJAX submit ────────────────────────────────────────────────────
                    if (registrationForm) {
                        registrationForm.addEventListener('submit', function (event) {
                            event.preventDefault();

                            if (!registrationTypeInput || registrationTypeInput.value === '') {
                                setRegistrationPanelsVisibility(false);
                                if (registrationTypeHint) {
                                    registrationTypeHint.classList.add('mj-registration-type-selector__hint--error');
                                }
                                return;
                            }

                            var ajaxUrl    = registrationForm.getAttribute('data-ajax-url') || '';
                            var ajaxAction = registrationForm.getAttribute('data-ajax-action') || '';
                            var accountUrl = registrationForm.getAttribute('data-account-url') || '';

                            if (!ajaxUrl || !ajaxAction) { registrationForm.submit(); return; }

                            var submitButton = registrationForm.querySelector('[type="submit"]');
                            if (submitButton) { submitButton.disabled = true; submitButton.setAttribute('aria-busy', 'true'); }

                            var prevToast = document.querySelector('.mj-error-toast');
                            if (prevToast) { prevToast.remove(); }
                            var prevErr = registrationForm.querySelector('.mj-ajax-error-notice');
                            if (prevErr) { prevErr.remove(); }
                            mjClearErrorHighlights();

                            // Keep child field names/indexes in sync and ensure all child fields are serialized.
                            refreshChildNumbers();
                            wrapper.querySelectorAll('.mj-child-card').forEach(function (card) {
                                card.querySelectorAll('input, textarea, select').forEach(function (field) {
                                    if (field.name && field.name.indexOf('jeunes[') === 0) {
                                        field.disabled = false;
                                    }
                                });
                            });

                            var formData = new FormData(registrationForm);
                            formData.set('action', ajaxAction);

                            fetch(ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' })
                            .then(function (r) { return r.json(); })
                            .then(function (data) {
                                if (data && data.success) {
                                    var didLogin = data.data && data.data.did_login ? 1 : 0;
                                    mjShowRegistrationSuccess(accountUrl, didLogin);
                                } else {
                                    var msg = (data && data.data && data.data.message)
                                        ? data.data.message
                                        : 'Une erreur est survenue. Merci de r\u00e9essayer.';
                                    var errFields = (data && data.data && Array.isArray(data.data.error_fields))
                                        ? data.data.error_fields
                                        : [];
                                    mjShowAjaxError(msg, submitButton, errFields);
                                }
                            })
                            .catch(function () {
                                mjShowAjaxError('Une erreur r\u00e9seau est survenue. Merci de v\u00e9rifier votre connexion.', submitButton, []);
                            });
                        });
                    }

                function updateRegistrationTypeCards(type) {
                    registrationTypeButtons.forEach(function (button) {
                        const buttonType = button.getAttribute('data-registration-type-value');
                        const isActive = buttonType === type;
                        button.classList.toggle('mj-registration-type-card--active', isActive);
                        button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                    });
                }

                function setRegistrationPanelsVisibility(isVisible) {
                    if (!registrationFormPanels) {
                        return;
                    }

                    registrationFormPanels.classList.toggle('mj-registration-form-panels--active', !!isVisible);
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
                    if (registrationTypeInput) {
                        registrationTypeInput.value = currentType;
                    }

                    if (registrationTypeHint) {
                        registrationTypeHint.classList.remove('mj-registration-type-selector__hint--error');
                    }

                    updateRegistrationTypeCards(currentType);
                    setRegistrationPanelsVisibility(true);

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

                registrationTypeButtons.forEach(function (button) {
                    button.addEventListener('click', function (event) {
                        event.preventDefault();
                        const nextType = button.getAttribute('data-registration-type-value');
                        if (!nextType) {
                            return;
                        }

                        applyRegistrationType(nextType);
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
                    const complementaryToggle = target.closest('[data-complementary-toggle]');
                    if (complementaryToggle) {
                        event.preventDefault();
                        const card = complementaryToggle.closest('.mj-child-card');
                        if (!card) {
                            return;
                        }

                        const panel = card.querySelector('[data-complementary-panel]');
                        if (!panel) {
                            return;
                        }

                        const isOpen = !panel.classList.contains('mj-hidden');
                        panel.classList.toggle('mj-hidden', isOpen);
                        complementaryToggle.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
                        return;
                    }

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

                function normalizeLoginPart(value) {
                    return String(value || '')
                        .toLowerCase()
                        .normalize('NFD')
                        .replace(/[\u0300-\u036f]/g, '')
                        .replace(/[^a-z0-9]+/g, '-')
                        .replace(/^-+|-+$/g, '');
                }

                function buildAutoLogin(card) {
                    if (!card) return '';
                    const emailInput = card.querySelector('[data-child-email-field]');
                    const emailValue = String(emailInput ? emailInput.value : '').trim();
                    if (emailValue !== '') {
                        const atIndex = emailValue.indexOf('@');
                        const nickname = atIndex > 0 ? emailValue.slice(0, atIndex) : emailValue;
                        const emailPart = normalizeLoginPart(nickname);
                        if (emailPart !== '') {
                            return emailPart;
                        }
                    }

                    const firstNameInput = card.querySelector('input[name*="[first_name]"]');
                    const lastNameInput = card.querySelector('input[name*="[last_name]"]');
                    const firstPart = normalizeLoginPart(firstNameInput ? firstNameInput.value : '');
                    const lastPart = normalizeLoginPart(lastNameInput ? lastNameInput.value : '');

                    if (firstPart && lastPart) {
                        return firstPart + '-' + lastPart;
                    }
                    return firstPart || lastPart || '';
                }

                wrapper.addEventListener('input', function (event) {
                    const target = event.target;

                    if (target && (target.name && (target.name.indexOf('[first_name]') !== -1 || target.name.indexOf('[last_name]') !== -1) || target.hasAttribute('data-child-email-field'))) {
                        const card = target.closest('.mj-child-card');
                        if (!card) return;
                        const loginInput = card.querySelector('[data-child-login-input]');
                        const autoLogin = buildAutoLogin(card);
                        if (loginInput && !loginInput.dataset.userModified) {
                            loginInput.value = autoLogin;
                        }
                    }

                    if (target && target.hasAttribute('data-child-login-input')) {
                        const card = target.closest('.mj-child-card');
                        const autoLogin = buildAutoLogin(card);
                        if (autoLogin !== '' && target.value === autoLogin) {
                            delete target.dataset.userModified;
                        } else {
                            target.dataset.userModified = '1';
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
                if (currentType === ROLE_JEUNE || currentType === 'guardian') {
                    applyRegistrationType(currentType);
                } else {
                    updateRegistrationTypeCards('');
                    setRegistrationPanelsVisibility(false);
                }

                // Initialize tabs
                const tabButtons = document.querySelectorAll('[data-tab]');
                const tabPanels = document.querySelectorAll('[data-tab-panel]');

                tabButtons.forEach(function (button) {
                    button.addEventListener('click', function (event) {
                        event.preventDefault();
                        const tabName = button.getAttribute('data-tab');
                        if (!tabName) {
                            return;
                        }

                        // Deactivate all tabs
                        tabButtons.forEach(function (btn) {
                            btn.classList.remove('mj-inscription-tab-button--active');
                            btn.setAttribute('aria-selected', 'false');
                        });

                        tabPanels.forEach(function (panel) {
                            panel.classList.remove('mj-inscription-tab-content--active');
                        });

                        // Activate selected tab
                        button.classList.add('mj-inscription-tab-button--active');
                        button.setAttribute('aria-selected', 'true');

                        const targetPanel = document.querySelector('[data-tab-panel="' + tabName + '"]');
                        if (targetPanel) {
                            targetPanel.classList.add('mj-inscription-tab-content--active');
                        }
                    });
                });

                // ── "Autre" toggle for dynamic fields (dropdown / radio / checklist) ──
                function initDynfieldOtherToggles(scope) {
                    // Dropdown: show/hide text input when __other is selected
                    scope.querySelectorAll('select[data-dynfield-other="1"]').forEach(function (sel) {
                        var otherInput = sel.parentElement.querySelector('.mj-dynfield-other-input');
                        if (!otherInput) return;
                        sel.addEventListener('change', function () {
                            otherInput.style.display = sel.value === '__other' ? '' : 'none';
                            if (sel.value !== '__other') otherInput.value = '';
                        });
                    });

                    // Radio / Checklist: show/hide text input when __other option is toggled
                    scope.querySelectorAll('input[value="__other"]').forEach(function (otherEl) {
                        var container = otherEl.closest('fieldset') || otherEl.parentElement;
                        var otherInput = container ? container.querySelector('.mj-dynfield-other-input') : null;
                        if (!otherInput) return;

                        if (otherEl.type === 'radio') {
                            // Listen on all radios in the same name group
                            var radios = scope.querySelectorAll('input[type="radio"][name="' + otherEl.name + '"]');
                            radios.forEach(function (r) {
                                r.addEventListener('change', function () {
                                    var isOther = otherEl.checked;
                                    otherInput.style.display = isOther ? '' : 'none';
                                    if (!isOther) otherInput.value = '';
                                });
                            });
                        } else if (otherEl.type === 'checkbox') {
                            otherEl.addEventListener('change', function () {
                                otherInput.style.display = otherEl.checked ? '' : 'none';
                                if (!otherEl.checked) otherInput.value = '';
                            });
                        }
                    });
                }
                initDynfieldOtherToggles(document);

                // Re-init when a new child block is cloned
                var childContainer = document.getElementById('mj-child-fields-container');
                if (childContainer && typeof MutationObserver !== 'undefined') {
                    new MutationObserver(function (mutations) {
                        mutations.forEach(function (m) {
                            m.addedNodes.forEach(function (node) {
                                if (node.nodeType === 1) initDynfieldOtherToggles(node);
                            });
                        });
                    }).observe(childContainer, { childList: true });
                }

                // ── Grimlins avatar: sessionStorage fallback ──
                (function () {
                    try {
                        var stored = sessionStorage.getItem('mj_grimlins_avatar');
                        if (!stored) return;
                        // Clear immediately so subsequent page loads don't reuse it
                        sessionStorage.removeItem('mj_grimlins_avatar');

                        // If PHP already rendered the avatar via query params, nothing to do
                        var existingBanner = document.querySelector('.mj-inscription-grimlins-avatar');
                        if (existingBanner) return;

                        var data = JSON.parse(stored);
                        if (!data || (!data.attachmentId && !data.url)) return;

                        // Build avatar banner from JS
                        var banner = document.createElement('div');
                        banner.className = 'mj-inscription-grimlins-avatar';

                        var img = document.createElement('img');
                        img.className = 'mj-inscription-grimlins-avatar__img';
                        img.alt = 'Ton avatar Grimlins';
                        img.src = data.url || '';
                        banner.appendChild(img);

                        var label = document.createElement('p');
                        label.className = 'mj-inscription-grimlins-avatar__label';
                        label.textContent = 'Ton avatar Grimlins sera lié à ton compte après l\'inscription.';
                        banner.appendChild(label);

                        var form = document.querySelector('.mj-inscription-form');
                        if (form && form.parentNode) {
                            form.parentNode.insertBefore(banner, form);
                        }

                        // Inject hidden field inside form
                        if (data.attachmentId && form) {
                            var hidden = document.createElement('input');
                            hidden.type = 'hidden';
                            hidden.name = 'grimlins_avatar_id';
                            hidden.value = String(data.attachmentId);
                            form.insertBefore(hidden, form.firstChild);
                        }
                    } catch (e) {
                        // Ignore sessionStorage errors
                    }
                })();
            })();
        </script>
        <?php

        return ob_get_clean();
    }
}
