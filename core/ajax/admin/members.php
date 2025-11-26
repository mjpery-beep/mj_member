<?php

if (!defined('ABSPATH')) {
    exit;
}

// Admin AJAX handlers related to member management.
add_action('wp_ajax_mj_inline_edit_member', 'mj_inline_edit_member_callback');
add_action('wp_ajax_mj_link_member_user', 'mj_link_member_user_callback');
add_action('wp_ajax_mj_upload_member_photo', 'mj_upload_member_photo_callback');

function mj_link_member_user_callback() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mj_link_member_user')) {
        wp_send_json_error(array('message' => __('Vérification de sécurité échouée.', 'mj-member')), 403);
    }

    if (!current_user_can(MJ_MEMBER_CAPABILITY) || (!current_user_can('create_users') && !current_user_can('promote_users'))) {
        wp_send_json_error(array('message' => __('Accès refusé.', 'mj-member')), 403);
    }

    $member_id = isset($_POST['member_id']) ? intval($_POST['member_id']) : 0;
    $manual_password = isset($_POST['manual_password']) ? wp_unslash($_POST['manual_password']) : '';
    $manual_login_raw = isset($_POST['manual_login']) ? wp_unslash($_POST['manual_login']) : '';
    $manual_login = sanitize_user($manual_login_raw, true);
    $target_role = isset($_POST['role']) ? sanitize_key($_POST['role']) : '';

    if ($manual_login_raw !== '' && $manual_login === '') {
        wp_send_json_error(array('message' => __('Identifiant fourni invalide.', 'mj-member')));
    }

    if ($manual_login !== '' && !validate_username($manual_login)) {
        wp_send_json_error(array('message' => __('Cet identifiant contient des caractères non autorisés.', 'mj-member')));
    }

    if ($member_id <= 0) {
        wp_send_json_error(array('message' => __('Identifiant membre manquant.', 'mj-member')));
    }

    $editable_roles = function_exists('get_editable_roles') ? get_editable_roles() : array();
    if (empty($target_role) || !isset($editable_roles[$target_role])) {
        wp_send_json_error(array('message' => __('Rôle sélectionné invalide.', 'mj-member')));
    }

    $member = MjMembers_CRUD::getById($member_id);
    if (!$member) {
        wp_send_json_error(array('message' => __('Membre introuvable.', 'mj-member')));
    }

    $existing_user = null;
    if (!empty($member->wp_user_id)) {
        $existing_user = get_user_by('id', (int) $member->wp_user_id);
    }
    if (!$existing_user && !empty($member->email)) {
        $existing_user = get_user_by('email', $member->email);
    }

    $user_created = false;
    $generated_password = '';
    $user_login = '';

    if ($existing_user) {
        if (!current_user_can('promote_user', $existing_user->ID)) {
            wp_send_json_error(array('message' => __('Vous n’avez pas les droits suffisants pour modifier ce compte utilisateur.', 'mj-member')), 403);
        }

        $desired_login = $existing_user->user_login;
        if ($manual_login !== '') {
            $existing_login_owner = username_exists($manual_login);
            if ($existing_login_owner && (int) $existing_login_owner !== (int) $existing_user->ID) {
                wp_send_json_error(array('message' => __('Cet identifiant est déjà utilisé par un autre compte.', 'mj-member')));
            }
            $desired_login = $manual_login;
        }

        $update_data = array(
            'ID'            => $existing_user->ID,
            'role'          => $target_role,
            'user_login'    => $desired_login,
            'user_nicename' => sanitize_title($desired_login),
        );
        if (!empty($member->first_name)) {
            $update_data['first_name'] = $member->first_name;
        }
        if (!empty($member->last_name)) {
            $update_data['last_name'] = $member->last_name;
        }

        $updated = wp_update_user($update_data);
        if (is_wp_error($updated)) {
            wp_send_json_error(array('message' => $updated->get_error_message()));
        }

        $user_id = $existing_user->ID;
        $user_login = $desired_login;
    } else {
        if (!current_user_can('create_users')) {
            wp_send_json_error(array('message' => __('Vous n’avez pas les droits pour créer des utilisateurs.', 'mj-member')), 403);
        }

        if ($manual_login !== '') {
            if (username_exists($manual_login)) {
                wp_send_json_error(array('message' => __('Cet identifiant est déjà utilisé par un autre compte.', 'mj-member')));
            }
            $user_login = $manual_login;
        } else {
            $candidates = array();
            if (!empty($member->email) && is_email($member->email)) {
                $email_login = sanitize_user(current(explode('@', $member->email)), true);
                if (!empty($email_login)) {
                    $candidates[] = $email_login;
                }
            }

            $name_login = sanitize_user(trim(($member->first_name ?? '') . '.' . ($member->last_name ?? '')), true);
            if (!empty($name_login)) {
                $candidates[] = $name_login;
            }

            $fallback_login = sanitize_user('member' . $member->id, true);
            $candidates[] = $fallback_login !== '' ? $fallback_login : 'member' . $member->id;

            foreach ($candidates as $candidate) {
                if ($candidate === '') {
                    continue;
                }
                $login_candidate = $candidate;
                $suffix = 1;
                while (username_exists($login_candidate)) {
                    $login_candidate = $candidate . $suffix;
                    $suffix++;
                }
                $user_login = $login_candidate;
                break;
            }

            if ($user_login === '') {
                $user_login = 'member' . $member->id;
                $suffix = 1;
                while (username_exists($user_login)) {
                    $user_login = 'member' . $member->id . '_' . $suffix;
                    $suffix++;
                }
            }
        }

        $manual_password = is_string($manual_password) ? trim($manual_password) : '';
        if ($manual_password !== '') {
            if (strlen($manual_password) < 8) {
                wp_send_json_error(array('message' => __('Le mot de passe doit contenir au moins 8 caractères.', 'mj-member')));
            }
            if (strlen($manual_password) > 128) {
                $manual_password = substr($manual_password, 0, 128);
            }
        }

        $generated_password = $manual_password !== '' ? $manual_password : wp_generate_password(12, true, false);
        $user_email = '';
        if (!empty($member->email) && is_email($member->email)) {
            $user_email = $member->email;
        } else {
            $user_email = sanitize_email($user_login . '@mj-member.local');
            if ($user_email === '') {
                $user_email = 'member' . $member->id . '@mj-member.local';
            }
        }
        $user_id = wp_insert_user(array(
            'user_login' => $user_login,
            'user_email' => $user_email,
            'user_pass' => $generated_password,
            'first_name' => $member->first_name,
            'last_name' => $member->last_name,
            'role' => $target_role,
        ));

        if (is_wp_error($user_id)) {
            wp_send_json_error(array('message' => $user_id->get_error_message()));
        }

        $user_created = true;
    }

    if (!$user_created && is_string($manual_password)) {
        $manual_password = trim($manual_password);
        if ($manual_password !== '') {
            if (strlen($manual_password) < 8) {
                wp_send_json_error(array('message' => __('Le mot de passe doit contenir au moins 8 caractères.', 'mj-member')));
            }
            if (strlen($manual_password) > 128) {
                $manual_password = substr($manual_password, 0, 128);
            }

            wp_set_password($manual_password, $existing_user ? $existing_user->ID : $user_id);
            $generated_password = $manual_password;
        }
    }

    $member_login_value = $manual_login !== '' ? $manual_login : ($user_login !== '' ? $user_login : ($existing_user ? $existing_user->user_login : ''));
    MjMembers_CRUD::update($member_id, array(
        'wp_user_id'           => $user_id,
        'member_account_login' => $member_login_value,
    ));

    $response = array(
        'user_id' => $user_id,
        'created' => $user_created,
        'message' => $user_created
            ? __('Compte WordPress créé et lié avec succès.', 'mj-member')
            : __('Compte WordPress mis à jour et lié avec succès.', 'mj-member'),
        'user_edit_url' => get_edit_user_link($user_id),
        'role' => $target_role,
        'role_label' => isset($editable_roles[$target_role]['name']) ? translate_user_role($editable_roles[$target_role]['name']) : $target_role,
    );

    $user_object = get_user_by('id', $user_id);
    if ($user_object) {
        $response['login'] = $user_object->user_login;
        $response['member_login'] = $member_login_value !== '' ? $member_login_value : $user_object->user_login;
        $response['user_email'] = $user_object->user_email;
    }
    $response['member_login'] = $response['member_login'] ?? $member_login_value;

    if (!empty($generated_password)) {
        $response['generated_password'] = $generated_password;
    }

    wp_send_json_success($response);
}

function mj_inline_edit_member_callback() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mj_inline_edit_nonce')) {
        wp_send_json_error(array('message' => 'Vérification de sécurité échouée'));
    }

    if (!current_user_can(MJ_MEMBER_CAPABILITY)) {
        wp_send_json_error(array('message' => 'Accès non autorisé'));
    }

    if (isset($_POST['member_id']) && isset($_POST['field_name'])) {
        $member_id = intval($_POST['member_id']);
        $field_name = sanitize_text_field($_POST['field_name']);
        $field_value = isset($_POST['field_value']) ? wp_unslash($_POST['field_value']) : '';

        if ($member_id <= 0) {
            wp_send_json_error(array('message' => 'Identifiant membre invalide'));
        }

        $allowed_fields = array(
            'first_name',
            'last_name',
            'email',
            'phone',
            'birth_date',
            'role',
            'status',
            'date_last_payement',
            'requires_payment',
            'photo_usage_consent',
            'photo_id'
        );

        if (!in_array($field_name, $allowed_fields, true)) {
            wp_send_json_error(array('message' => 'Champ non autorisé'));
        }

        $member = MjMembers_CRUD::getById($member_id);
        if (!$member) {
            wp_send_json_error(array('message' => 'Membre introuvable'));
        }

        switch ($field_name) {
            case 'email':
                $raw_email = trim((string) $field_value);
                $sanitized_email = sanitize_email($field_value);

                if ($sanitized_email === '') {
                    if ($raw_email === '' && $member->role === MjMembers_CRUD::ROLE_JEUNE) {
                        $field_value = null;
                        break;
                    }

                    wp_send_json_error(array('message' => 'Email invalide'));
                }

                if (!is_email($sanitized_email)) {
                    wp_send_json_error(array('message' => 'Email invalide'));
                }

                $field_value = $sanitized_email;
                break;
            case 'first_name':
            case 'last_name':
                $field_value = sanitize_text_field($field_value);
                if ($field_value === '') {
                    wp_send_json_error(array('message' => 'Valeur invalide'));
                }
                break;
            case 'phone':
                $field_value = sanitize_text_field($field_value);
                break;
            case 'birth_date':
                $clean_date = sanitize_text_field($field_value);
                if ($clean_date === '') {
                    $field_value = null;
                    break;
                }

                $timestamp = strtotime($clean_date);
                if (!$timestamp) {
                    wp_send_json_error(array('message' => 'Date invalide'));
                }

                $field_value = gmdate('Y-m-d', $timestamp);
                break;
            case 'role':
                $field_value = sanitize_text_field($field_value);
                $allowed_roles = MjMembers_CRUD::getAllowedRoles();
                if (!in_array($field_value, $allowed_roles, true)) {
                    wp_send_json_error(array('message' => 'Rôle invalide'));
                }
                if ($field_value !== MjMembers_CRUD::ROLE_JEUNE && empty($member->email)) {
                    wp_send_json_error(array('message' => 'Ajoutez un email avant de changer le rôle.'));
                }
                break;
            case 'status':
                $field_value = sanitize_text_field($field_value);
                if (!in_array($field_value, array(MjMembers_CRUD::STATUS_ACTIVE, MjMembers_CRUD::STATUS_INACTIVE), true)) {
                    wp_send_json_error(array('message' => 'Statut invalide'));
                }
                break;
            case 'date_last_payement':
                $field_value = sanitize_text_field($field_value);
                break;
            case 'requires_payment':
                $field_value = (!empty($field_value) && $field_value !== '0' && strtolower($field_value) !== 'false') ? 1 : 0;
                break;
            case 'photo_usage_consent':
                $normalized = strtolower(trim($field_value));
                if (in_array($normalized, array('accepté', 'accepte', 'oui', 'yes', '1', 'true'), true)) {
                    $field_value = 1;
                } elseif (in_array($normalized, array('refusé', 'refuse', 'non', 'no', '0', 'false'), true)) {
                    $field_value = 0;
                } else {
                    $field_value = !empty($field_value) ? 1 : 0;
                }
                break;
            case 'photo_id':
                $field_value = intval($field_value);
                if ($field_value <= 0) {
                    $field_value = null;
                }
                break;
            default:
                $field_value = sanitize_text_field($field_value);
        }

        $data = array($field_name => $field_value);
        $result = MjMembers_CRUD::update($member_id, $data);

        if ($result !== false) {
            wp_send_json_success(array(
                'message' => 'Mise à jour réussie',
                'value' => $field_value
            ));
        } else {
            wp_send_json_error(array('message' => 'Erreur lors de la mise à jour'));
        }
    } else {
        wp_send_json_error(array('message' => 'Données manquantes'));
    }
}

function mj_upload_member_photo_callback() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mj_photo_upload_nonce')) {
        wp_send_json_error(array('message' => 'Vérification de sécurité échouée'));
    }

    if (!current_user_can(MJ_MEMBER_CAPABILITY)) {
        wp_send_json_error(array('message' => 'Accès non autorisé'));
    }

    if (!isset($_POST['member_id']) || !isset($_POST['attachment_id'])) {
        wp_send_json_error(array('message' => 'Données manquantes'));
    }

    $member_id = intval($_POST['member_id']);
    $attachment_id = intval($_POST['attachment_id']);

    $member = MjMembers_CRUD::getById($member_id);
    if (!$member) {
        wp_send_json_error(array('message' => 'Membre introuvable'));
    }

    if (!get_post($attachment_id)) {
        wp_send_json_error(array('message' => 'Pièce jointe introuvable'));
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'mj_members';

    $result = $wpdb->update(
        $table_name,
        array('photo_id' => $attachment_id),
        array('id' => $member_id),
        array('%d'),
        array('%d')
    );

    if ($result !== false) {
        $image_url = wp_get_attachment_image_src($attachment_id, 'thumbnail');
        wp_send_json_success(array(
            'attachment_id' => $attachment_id,
            'image_url' => $image_url[0]
        ));
    } else {
        wp_send_json_error(array('message' => 'Erreur lors de la sauvegarde de la photo: ' . $wpdb->last_error));
    }
}
