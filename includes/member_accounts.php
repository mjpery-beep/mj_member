<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('mj_member_get_current_url')) {
    function mj_member_get_current_url() {
        $scheme = is_ssl() ? 'https' : 'http';
        $host = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '';
        $request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';

        if ($host === '') {
            return home_url('/');
        }

        $url = $scheme . '://' . $host . $request_uri;

        return esc_url_raw($url);
    }
}

if (!function_exists('mj_member_temp_allow_upload_cap')) {
    function mj_member_temp_allow_upload_cap($allcaps, $caps, $args) {
        if (!is_user_logged_in()) {
            return $allcaps;
        }

        if (empty($args) || !isset($args[0])) {
            return $allcaps;
        }

        if ($args[0] !== 'upload_files') {
            return $allcaps;
        }

        $user_id = isset($args[1]) ? (int) $args[1] : get_current_user_id();
        if ($user_id === get_current_user_id()) {
            $allcaps['upload_files'] = true;
        }

        return $allcaps;
    }
}

if (!function_exists('mj_member_generate_unique_username')) {
    function mj_member_generate_unique_username($member, $fallback = '') {
        $candidates = array();

        if (!empty($member->email)) {
            $local_part = sanitize_user(current(explode('@', $member->email)), true);
            if (!empty($local_part)) {
                $candidates[] = $local_part;
            }
        }

        $name_candidate = sanitize_user($member->first_name . '.' . $member->last_name, true);
        if (!empty($name_candidate)) {
            $candidates[] = $name_candidate;
        }

        if ($fallback !== '') {
            $candidates[] = sanitize_user($fallback, true);
        }

        $candidates[] = 'member' . (int) $member->id;

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

            if (!username_exists($login_candidate)) {
                return $login_candidate;
            }
        }

        $base = 'member' . (int) $member->id;
        $login_candidate = $base;
        $suffix = 1;
        while (username_exists($login_candidate)) {
            $login_candidate = $base . $suffix;
            $suffix++;
        }

        return $login_candidate;
    }
}

if (!function_exists('mj_member_sync_member_user_account')) {
    function mj_member_sync_member_user_account($member, array $args = array()) {
        if (!class_exists('MjMembers_CRUD')) {
            return null;
        }

        if (is_numeric($member)) {
            $member = MjMembers_CRUD::getById((int) $member);
        }

        if (!$member || !is_object($member)) {
            return null;
        }

        $email = isset($member->email) ? sanitize_email($member->email) : '';
        if (!is_email($email)) {
            return null;
        }

        $defaults = array(
            'role' => apply_filters('mj_member_frontend_user_role', 'subscriber', $member),
            'send_notification' => true,
            'user_login' => '',
            'user_pass' => '',
            'return_error' => false,
            'min_password_length' => (int) apply_filters('mj_member_min_password_length', 8),
        );
        $args = wp_parse_args($args, $defaults);

        $should_return_error = !empty($args['return_error']);
        $min_password_length = max(4, (int) $args['min_password_length']);
        $raw_login = isset($args['user_login']) ? (string) $args['user_login'] : '';
        $requested_password = isset($args['user_pass']) ? (string) $args['user_pass'] : '';
        $sanitized_login = $raw_login !== '' ? sanitize_user($raw_login, true) : '';

        if ($raw_login !== '' && $sanitized_login === '') {
            if ($should_return_error) {
                return new WP_Error('invalid_username', __('Identifiant de connexion invalide.', 'mj-member'));
            }
            return null;
        }

        if ($requested_password !== '' && strlen($requested_password) < $min_password_length) {
            if ($should_return_error) {
                return new WP_Error('weak_password', sprintf(__('Le mot de passe doit contenir au moins %d caractères.', 'mj-member'), $min_password_length));
            }
            return null;
        }

        if (!empty($member->wp_user_id)) {
            $existing = get_user_by('id', (int) $member->wp_user_id);
            if ($existing) {
                if ($sanitized_login !== '' && strcasecmp($existing->user_login, $sanitized_login) !== 0) {
                    if ($should_return_error) {
                        return new WP_Error('cannot_change_username', __('Impossible de modifier l’identifiant d’un compte existant.', 'mj-member'));
                    }
                }

                $update_data = array('ID' => $existing->ID);

                if (!empty($member->first_name)) {
                    $update_data['first_name'] = $member->first_name;
                }
                if (!empty($member->last_name)) {
                    $update_data['last_name'] = $member->last_name;
                }
                if ($existing->user_email !== $email) {
                    $update_data['user_email'] = $email;
                }
                if ($requested_password !== '') {
                    $update_data['user_pass'] = $requested_password;
                }

                $updated = wp_update_user($update_data);
                if (is_wp_error($updated)) {
                    return $should_return_error ? $updated : null;
                }

                return (int) $existing->ID;
            }
        }

        $existing_by_email = get_user_by('email', $email);
        if ($existing_by_email) {
            if ($sanitized_login !== '' && strcasecmp($existing_by_email->user_login, $sanitized_login) !== 0) {
                if ($should_return_error) {
                    return new WP_Error('existing_user_email', __('Un compte existe déjà avec cette adresse email. Utilisez la récupération de mot de passe.', 'mj-member'));
                }
            }

            if ($requested_password !== '') {
                $updated = wp_update_user(array(
                    'ID' => $existing_by_email->ID,
                    'user_pass' => $requested_password,
                ));
                if (is_wp_error($updated)) {
                    return $should_return_error ? $updated : null;
                }
            }

            MjMembers_CRUD::update($member->id, array('wp_user_id' => $existing_by_email->ID));
            return (int) $existing_by_email->ID;
        }

        if ($sanitized_login !== '' && username_exists($sanitized_login)) {
            if ($should_return_error) {
                return new WP_Error('username_exists', __('Cet identifiant est déjà utilisé.', 'mj-member'));
            }
            return null;
        }

        $username = $sanitized_login !== '' ? $sanitized_login : mj_member_generate_unique_username($member);
        $password = $requested_password !== '' ? $requested_password : wp_generate_password(12, true, false);

        $user_id = wp_insert_user(array(
            'user_login' => $username,
            'user_pass' => $password,
            'user_email' => $email,
            'first_name' => $member->first_name,
            'last_name' => $member->last_name,
            'role' => $args['role'],
        ));

        if (is_wp_error($user_id)) {
            return $should_return_error ? $user_id : null;
        }

        MjMembers_CRUD::update($member->id, array('wp_user_id' => $user_id));

        $send_notification = apply_filters('mj_member_send_new_user_notification', (bool) $args['send_notification'], $member, $user_id);
        if ($send_notification && function_exists('wp_send_new_user_notifications')) {
            wp_send_new_user_notifications($user_id, 'user');
        }

        return (int) $user_id;
    }
}

if (!function_exists('mj_member_get_member_for_user')) {
    function mj_member_get_member_for_user($user_id) {
        if (!class_exists('MjMembers_CRUD')) {
            return null;
        }

        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            return null;
        }

        $member = MjMembers_CRUD::getByWpUserId($user_id);
        if ($member) {
            return $member;
        }

        $user = get_user_by('id', $user_id);
        if (!$user || empty($user->user_email)) {
            return null;
        }

        $fallback = MjMembers_CRUD::getByEmail($user->user_email);
        if ($fallback && empty($fallback->wp_user_id)) {
            MjMembers_CRUD::update($fallback->id, array('wp_user_id' => $user_id));
            $fallback->wp_user_id = $user_id;
        }

        return $fallback;
    }
}

if (!function_exists('mj_member_get_current_member')) {
    function mj_member_get_current_member() {
        if (!is_user_logged_in()) {
            return null;
        }

        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            return null;
        }

        return mj_member_get_member_for_user($user_id);
    }
}

if (!function_exists('mj_member_can_manage_children')) {
    function mj_member_can_manage_children($member) {
        if (!$member) {
            return false;
        }

        return isset($member->role) && $member->role === MjMembers_CRUD::ROLE_TUTEUR;
    }
}

if (!function_exists('mj_member_get_guardian_children')) {
    function mj_member_get_guardian_children($member) {
        if (!mj_member_can_manage_children($member)) {
            return array();
        }

        return MjMembers_CRUD::getChildrenForGuardian((int) $member->id);
    }
}

if (!function_exists('mj_member_get_payment_timeline')) {
    function mj_member_get_payment_timeline($member_id, $limit = 10) {
        if (!class_exists('MjMembers_CRUD')) {
            return array();
        }

        $member_id = (int) $member_id;
        if ($member_id <= 0) {
            return array();
        }

        $limit = max(1, (int) $limit);

        $wpdb = MjMembers_CRUD::getWpdb();
        $history_table = $wpdb->prefix . 'mj_payment_history';
        $payments_table = $wpdb->prefix . 'mj_payments';

        $entries = array();

        $history_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT payment_date, amount, method, reference FROM $history_table WHERE member_id = %d ORDER BY payment_date DESC LIMIT %d",
                $member_id,
                $limit
            )
        );

        if ($history_rows) {
            foreach ($history_rows as $row) {
                $date_string = $row->payment_date ? date_i18n(get_option('date_format', 'd/m/Y'), strtotime($row->payment_date)) : __('Date inconnue', 'mj-member');
                $label = !empty($row->method) ? sanitize_text_field($row->method) : __('Paiement enregistré', 'mj-member');
                $entries[] = array(
                    'date' => $date_string,
                    'amount' => number_format((float) $row->amount, 2, ',', ' '),
                    'status_label' => $label,
                    'reference' => !empty($row->reference) ? sanitize_text_field($row->reference) : '',
                );
            }

            return $entries;
        }

        $payment_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT COALESCE(paid_at, created_at) AS date_ref, amount, status, external_ref FROM $payments_table WHERE member_id = %d ORDER BY created_at DESC LIMIT %d",
                $member_id,
                $limit
            )
        );

        if ($payment_rows) {
            foreach ($payment_rows as $row) {
                $date_string = $row->date_ref ? date_i18n(get_option('date_format', 'd/m/Y'), strtotime($row->date_ref)) : __('Date inconnue', 'mj-member');
                $status = !empty($row->status) ? sanitize_text_field($row->status) : '';
                $status_label = function_exists('mj_format_payment_status') ? mj_format_payment_status($status) : $status;
                $entries[] = array(
                    'date' => $date_string,
                    'amount' => number_format((float) $row->amount, 2, ',', ' '),
                    'status_label' => $status_label,
                    'reference' => !empty($row->external_ref) ? sanitize_text_field($row->external_ref) : '',
                );
            }
        }

        return $entries;
    }
}

if (!function_exists('mj_member_get_membership_status')) {
    function mj_member_get_membership_status($member, $args = array()) {
        if (!$member) {
            return array(
                'requires_payment' => false,
                'status' => 'guest',
                'status_label' => __('Profil introuvable', 'mj-member'),
                'description' => __('Nous ne parvenons pas à retrouver votre fiche membre.', 'mj-member'),
                'last_payment_display' => '',
                'expires_display' => '',
                'days_remaining' => null,
                'amount' => (float) 0,
            );
        }

        $defaults = array(
            'now' => current_time('timestamp'),
            'expiration_days' => (int) apply_filters('mj_member_payment_expiration_days', MJ_MEMBER_PAYMENT_EXPIRATION_DAYS, $member),
            'expiring_threshold' => (int) apply_filters('mj_member_membership_expiring_threshold_days', 30, $member),
            'annual_fee' => (float) apply_filters('mj_member_membership_amount', (float) get_option('mj_annual_fee', '2.00'), $member),
        );

        $args = wp_parse_args($args, $defaults);

        $requires_payment = !empty($member->requires_payment);
        $last_payment_raw = !empty($member->date_last_payement) && $member->date_last_payement !== '0000-00-00 00:00:00'
            ? $member->date_last_payement
            : '';

        $status = 'not_required';
        $status_label = __('Cotisation non requise', 'mj-member');
        $description = __('Vous n’avez pas de cotisation à régler pour le moment.', 'mj-member');
        $last_payment_display = '';
        $expires_display = '';
        $days_remaining = null;

        if ($requires_payment) {
            $status = 'missing';
            $status_label = __('Cotisation à régler', 'mj-member');
            $description = __('Aucun paiement enregistré à ce jour.', 'mj-member');

            if ($last_payment_raw !== '') {
                $timestamp = strtotime($last_payment_raw);
                if ($timestamp) {
                    $last_payment_display = wp_date(get_option('date_format', 'd/m/Y'), $timestamp);

                    $expiration_days = max(1, (int) $args['expiration_days']);
                    $expiry_timestamp = strtotime('+' . $expiration_days . ' days', $timestamp);
                    $days_remaining = (int) floor(($expiry_timestamp - $args['now']) / DAY_IN_SECONDS);

                    if ($expiry_timestamp <= $args['now']) {
                        $status = 'expired';
                        $status_label = __('Cotisation expirée', 'mj-member');
                        $description = __('Votre adhésion est expirée. Merci de renouveler votre cotisation.', 'mj-member');
                        $expires_display = wp_date(get_option('date_format', 'd/m/Y'), $expiry_timestamp);
                    } elseif ($days_remaining <= max(1, (int) $args['expiring_threshold'])) {
                        $status = 'expiring';
                        $status_label = __('Renouvellement recommandé', 'mj-member');
                        $description = sprintf(
                            __('Votre cotisation se termine bientôt (dans %d jours).', 'mj-member'),
                            max(0, $days_remaining)
                        );
                        $expires_display = wp_date(get_option('date_format', 'd/m/Y'), $expiry_timestamp);
                    } else {
                        $status = 'active';
                        $status_label = __('Cotisation à jour', 'mj-member');
                        $description = __('Merci ! Votre adhésion est active.', 'mj-member');
                        $expires_display = wp_date(get_option('date_format', 'd/m/Y'), $expiry_timestamp);
                    }
                }
            }
        }

        return array(
            'requires_payment' => (bool) $requires_payment,
            'status' => $status,
            'status_label' => $status_label,
            'description' => $description,
            'last_payment_raw' => $last_payment_raw,
            'last_payment_display' => $last_payment_display,
            'expires_display' => $expires_display,
            'days_remaining' => $days_remaining,
            'amount' => (float) $args['annual_fee'],
        );
    }
}

if (!function_exists('mj_member_get_member_registrations')) {
    function mj_member_get_member_registrations($member_id, $args = array()) {
        $member_id = (int) $member_id;
        if ($member_id <= 0) {
            return array();
        }

        $defaults = array(
            'limit' => 10,
            'upcoming_only' => false,
            'statuses' => array(),
        );
        $args = wp_parse_args($args, $defaults);

        $registrations = apply_filters('mj_member_member_registrations', array(), $member_id, $args);
        if (empty($registrations) || !is_array($registrations)) {
            return array();
        }

        $sanitized = array();
        foreach ($registrations as $registration) {
            if (!is_array($registration)) {
                continue;
            }

            $title = isset($registration['title']) ? sanitize_text_field($registration['title']) : '';
            if ($title === '') {
                continue;
            }

            $status = isset($registration['status']) ? sanitize_key($registration['status']) : 'pending';
            $status_label = isset($registration['status_label']) ? sanitize_text_field($registration['status_label']) : '';
            if ($status_label === '') {
                switch ($status) {
                    case 'confirmed':
                        $status_label = __('Confirmée', 'mj-member');
                        break;
                    case 'cancelled':
                        $status_label = __('Annulée', 'mj-member');
                        break;
                    case 'waitlist':
                        $status_label = __('Liste d’attente', 'mj-member');
                        break;
                    default:
                        $status_label = __('En attente', 'mj-member');
                        break;
                }
            }

            $start_date = isset($registration['start_date']) ? sanitize_text_field($registration['start_date']) : '';
            $end_date = isset($registration['end_date']) ? sanitize_text_field($registration['end_date']) : '';
            $type = isset($registration['type']) ? sanitize_text_field($registration['type']) : '';
            $location = isset($registration['location']) ? sanitize_text_field($registration['location']) : '';
            $actions = array();
            if (!empty($registration['actions']) && is_array($registration['actions'])) {
                foreach ($registration['actions'] as $action) {
                    if (empty($action['label']) || empty($action['url'])) {
                        continue;
                    }
                    $actions[] = array(
                        'label' => sanitize_text_field($action['label']),
                        'url' => esc_url($action['url']),
                        'target' => empty($action['target']) ? '_self' : sanitize_key($action['target']),
                    );
                }
            }

            $sanitized[] = array(
                'id' => isset($registration['id']) ? (int) $registration['id'] : 0,
                'title' => $title,
                'status' => $status,
                'status_label' => $status_label,
                'type' => $type,
                'start_date' => $start_date,
                'end_date' => $end_date,
                'location' => $location,
                'actions' => $actions,
                'notes' => isset($registration['notes']) ? wp_kses_post($registration['notes']) : '',
            );

            if ((int) $args['limit'] > 0 && count($sanitized) >= (int) $args['limit']) {
                break;
            }
        }

        return $sanitized;
    }
}

if (!function_exists('mj_member_handle_payment_link_request')) {
    function mj_member_handle_payment_link_request() {
        if (!is_user_logged_in()) {
            wp_safe_redirect(home_url('/')); // Pas connecté, retour accueil
            exit;
        }

        $redirect_to = isset($_POST['redirect_to']) ? esc_url_raw(wp_unslash($_POST['redirect_to'])) : '';
        if ($redirect_to === '') {
            $redirect_to = mj_member_get_current_url();
        }

        $member = mj_member_get_current_member();
        if (!$member) {
            wp_safe_redirect(add_query_arg('mj_member_payment_error', 'member', $redirect_to));
            exit;
        }

        $nonce = isset($_POST['mj_member_payment_link_nonce']) ? sanitize_text_field(wp_unslash($_POST['mj_member_payment_link_nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'mj_member_generate_payment_link')) {
            wp_safe_redirect(add_query_arg('mj_member_payment_error', 'nonce', $redirect_to));
            exit;
        }

        $amount = (float) apply_filters('mj_member_membership_amount', (float) get_option('mj_annual_fee', '2.00'), $member);

        if (!class_exists('MjPayments')) {
            wp_safe_redirect(add_query_arg('mj_member_payment_error', 'missing_class', $redirect_to));
            exit;
        }

        $payment = MjPayments::create_stripe_payment($member->id, $amount);
        if ($payment && !empty($payment['checkout_url'])) {
            wp_safe_redirect(esc_url_raw($payment['checkout_url']));
            exit;
        }

        wp_safe_redirect(add_query_arg('mj_member_payment_error', 'stripe', $redirect_to));
        exit;
    }

    add_action('admin_post_mj_member_generate_payment_link', 'mj_member_handle_payment_link_request');
    add_action('admin_post_nopriv_mj_member_generate_payment_link', 'mj_member_handle_payment_link_request');
}

if (!function_exists('mj_member_ajax_create_payment_link')) {
    function mj_member_ajax_create_payment_link() {
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Vous devez être connecté pour renouveler votre cotisation.', 'mj-member')), 403);
        }

        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'mj_member_create_payment_link')) {
            wp_send_json_error(array('message' => __('La vérification de sécurité a échoué. Merci de recharger la page.', 'mj-member')), 403);
        }

        $member = mj_member_get_current_member();
        if (!$member) {
            wp_send_json_error(array('message' => __('Votre profil MJ est introuvable.', 'mj-member')), 404);
        }

        $status = mj_member_get_membership_status($member);
        if (empty($status['requires_payment'])) {
            wp_send_json_error(array('message' => __('Aucune cotisation n\'est requise pour le moment.', 'mj-member')));
        }

        if (!class_exists('MjPayments')) {
            wp_send_json_error(array('message' => __('Le module de paiement n\'est pas disponible.', 'mj-member')), 500);
        }

        $amount = (float) apply_filters('mj_member_membership_amount', (float) get_option('mj_annual_fee', '2.00'), $member);
        $payment = MjPayments::create_stripe_payment($member->id, $amount);

        if (!$payment || empty($payment['checkout_url'])) {
            wp_send_json_error(array('message' => __('Impossible de générer le lien de paiement.', 'mj-member')), 500);
        }

        wp_send_json_success(array(
            'redirect_url' => esc_url_raw($payment['checkout_url']),
        ));
    }

    add_action('wp_ajax_mj_member_create_payment_link', 'mj_member_ajax_create_payment_link');
    add_action('wp_ajax_nopriv_mj_member_create_payment_link', 'mj_member_ajax_create_payment_link');
}
