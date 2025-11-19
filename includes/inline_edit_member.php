<?php

// Gestion de l'édition inline
if (isset($_POST['mj_inline_edit_nonce']) && wp_verify_nonce($_POST['mj_inline_edit_nonce'], 'mj_inline_edit_form')) {
    if (isset($_POST['member_id']) && isset($_POST['field_name'])) {
        $member_id = intval($_POST['member_id']);
        $field_name = sanitize_text_field($_POST['field_name']);
        $field_value = isset($_POST['field_value']) ? wp_unslash($_POST['field_value']) : '';

        $allowed_fields = array(
            'first_name',
            'last_name',
            'email',
            'phone',
            'role',
            'status',
            'date_last_payement',
            'requires_payment',
            'photo_usage_consent'
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
            case 'phone':
                $field_value = sanitize_text_field($field_value);
                break;
            case 'status':
                $field_value = sanitize_text_field($field_value);
                if (!in_array($field_value, array('active', 'inactive'), true)) {
                    wp_send_json_error(array('message' => 'Statut invalide'));
                }
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
            case 'date_last_payement':
                $field_value = sanitize_text_field($field_value);
                break;
            case 'requires_payment':
            case 'photo_usage_consent':
                $field_value = !empty($field_value) ? 1 : 0;
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

wp_send_json_error(array('message' => 'Erreur de mise à jour'));
