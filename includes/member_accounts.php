<?php

use Mj\Member\Core\Config;
use Mj\Member\Core\Logger;
use Mj\Member\Classes\MjRoles;
use Mj\Member\Classes\MjPayments;

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
        if (!class_exists('MjMembers')) {
            return null;
        }

        if (is_numeric($member)) {
            $member = MjMembers::getById((int) $member);
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

            $link_result = MjMembers::update($member->id, array('wp_user_id' => $existing_by_email->ID));
            if (is_wp_error($link_result)) {
                return $should_return_error ? $link_result : null;
            }
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

        $creation_link = MjMembers::update($member->id, array('wp_user_id' => $user_id));
        if (is_wp_error($creation_link)) {
            return $should_return_error ? $creation_link : null;
        }

        $send_notification = apply_filters('mj_member_send_new_user_notification', (bool) $args['send_notification'], $member, $user_id);
        if ($send_notification && function_exists('wp_send_new_user_notifications')) {
            wp_send_new_user_notifications($user_id, 'user');
        }

        return (int) $user_id;
    }
}

if (!function_exists('mj_member_get_member_for_user')) {
    function mj_member_get_member_for_user($user_id) {
        if (!class_exists('MjMembers')) {
            return null;
        }

        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            return null;
        }

        $member = MjMembers::getByWpUserId($user_id);
        if ($member) {
            return $member;
        }

        $user = get_user_by('id', $user_id);
        if (!$user || empty($user->user_email)) {
            return null;
        }

        $fallback = MjMembers::getByEmail($user->user_email);
        if ($fallback && empty($fallback->wp_user_id)) {
            $link = MjMembers::update($fallback->id, array('wp_user_id' => $user_id));
            if (!is_wp_error($link)) {
                $fallback->wp_user_id = $user_id;
            }
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
            /**
             * Permet de filtrer la capacité à gérer des jeunes associés.
             */
            return (bool) apply_filters('mj_member_can_manage_children', false, $member);
        }

        $role = '';
        $member_id = 0;

        if (is_object($member)) {
            if (isset($member->role)) {
                $role = sanitize_key((string) $member->role);
            }
            if (isset($member->id)) {
                $member_id = (int) $member->id;
            }
        } elseif (is_array($member)) {
            if (isset($member['role'])) {
                $role = sanitize_key((string) $member['role']);
            }
            if (isset($member['id'])) {
                $member_id = (int) $member['id'];
            }
        }

        $can_manage = MjRoles::isTuteur($role);

        if (!$can_manage && $member_id > 0 && class_exists('MjMembers')) {
            static $guardian_children_presence = array();

            if (!array_key_exists($member_id, $guardian_children_presence)) {
                $children = MjMembers::getChildrenForGuardian($member_id);
                $guardian_children_presence[$member_id] = !empty($children);
            }

            if (!empty($guardian_children_presence[$member_id])) {
                $can_manage = true;
            }
        }

        /**
         * Filtre permettant de personnaliser la capacité d'un membre à gérer des jeunes associés.
         *
         * @param bool               $can_manage Indique si le membre peut gérer des jeunes.
         * @param object|array<mixed> $member     Données du membre courant.
         */
        return (bool) apply_filters('mj_member_can_manage_children', $can_manage, $member);
    }
}

if (!function_exists('mj_member_get_guardian_children')) {
    function mj_member_get_guardian_children($member) {
        if (!mj_member_can_manage_children($member)) {
            return array();
        }

        return MjMembers::getChildrenForGuardian((int) $member->id);
    }
}

if (!function_exists('mj_member_get_guardian_children_statuses')) {
    /**
     * @param object $guardian_member
     * @param array<string,mixed> $args
     * @return array<int,array<string,mixed>>
     */
    function mj_member_get_guardian_children_statuses($guardian_member, $args = array()) {
        if (!mj_member_can_manage_children($guardian_member)) {
            return array();
        }

        $children = mj_member_get_guardian_children($guardian_member);
        if (empty($children) || !is_array($children)) {
            return array();
        }

        $entries = array();
        foreach ($children as $child) {
            if (!$child || !is_object($child)) {
                continue;
            }

            $status = mj_member_get_membership_status($child, $args);

            $full_name = trim(sprintf('%s %s', (string) ($child->first_name ?? ''), (string) ($child->last_name ?? '')));
            if ($full_name === '') {
                $full_name = !empty($child->nickname) ? (string) $child->nickname : sprintf(__('Jeune #%d', 'mj-member'), (int) $child->id);
            }

            $requires_payment = !empty($status['requires_payment']) && in_array($status['status'], array('missing', 'expired', 'expiring'), true);

            $birth_date_value = '';
            if (!empty($child->birth_date) && $child->birth_date !== '0000-00-00') {
                $timestamp = strtotime((string) $child->birth_date);
                if ($timestamp) {
                    $birth_date_value = gmdate('Y-m-d', $timestamp);
                } else {
                    $birth_date_value = sanitize_text_field((string) $child->birth_date);
                }
            }

            $profile = array(
                'id' => isset($child->id) ? (int) $child->id : 0,
                'first_name' => isset($child->first_name) ? sanitize_text_field((string) $child->first_name) : '',
                'last_name' => isset($child->last_name) ? sanitize_text_field((string) $child->last_name) : '',
                'email' => isset($child->email) ? sanitize_email((string) $child->email) : '',
                'phone' => isset($child->phone) ? sanitize_text_field((string) $child->phone) : '',
                'birth_date' => $birth_date_value,
                'notes' => isset($child->notes) ? sanitize_textarea_field((string) $child->notes) : '',
                'is_autonomous' => !empty($child->is_autonomous) ? 1 : 0,
                'photo_usage_consent' => !empty($child->photo_usage_consent) ? 1 : 0,
                'full_name' => $full_name,
            );

            $entries[] = array(
                'id' => isset($child->id) ? (int) $child->id : 0,
                'full_name' => $full_name,
                'status' => isset($status['status']) ? sanitize_key($status['status']) : 'unknown',
                'status_label' => isset($status['status_label']) ? sanitize_text_field($status['status_label']) : __('Statut indisponible', 'mj-member'),
                'description' => isset($status['description']) ? sanitize_text_field($status['description']) : '',
                'last_payment_display' => isset($status['last_payment_display']) ? sanitize_text_field($status['last_payment_display']) : '',
                'expires_display' => isset($status['expires_display']) ? sanitize_text_field($status['expires_display']) : '',
                'amount' => isset($status['amount']) ? (float) $status['amount'] : 0.0,
                'requires_payment' => $requires_payment,
                'profile' => $profile,
            );
        }

        return $entries;
    }
}

if (!function_exists('mj_member_get_payment_timeline')) {
    function mj_member_get_payment_timeline($member_id, $limit = 10) {
        if (!class_exists('MjMembers')) {
            return array();
        }

        $member_id = (int) $member_id;
        if ($member_id <= 0) {
            return array();
        }

        $limit = max(1, (int) $limit);

        $wpdb = MjMembers::getWpdb();
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
            'expiration_days' => (int) apply_filters('mj_member_payment_expiration_days', Config::paymentExpirationDays(), $member),
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
        $description = '';
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

        try {
            $existing_payment = MjPayments::get_pending_membership_payment($member->id);

            if ($existing_payment && !empty($existing_payment->checkout_url)) {
                wp_safe_redirect(esc_url_raw($existing_payment->checkout_url));
                exit;
            }

            $payment = MjPayments::create_stripe_payment($member->id, $amount);
        } catch (Throwable $exception) {
            Logger::error('Membership payment form failure', array(
                'member_id' => isset($member->id) ? (int) $member->id : 0,
                'exception' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ), 'payments');
            wp_safe_redirect(add_query_arg('mj_member_payment_error', 'stripe', $redirect_to));
            exit;
        }

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

        try {
            $existing_payment = MjPayments::get_pending_membership_payment($member->id);
            if ($existing_payment && !empty($existing_payment->checkout_url)) {
                wp_send_json_success(array(
                    'redirect_url' => esc_url_raw($existing_payment->checkout_url),
                ));
            }

            $amount = (float) apply_filters('mj_member_membership_amount', (float) get_option('mj_annual_fee', '2.00'), $member);
            $payment = MjPayments::create_stripe_payment($member->id, $amount);
        } catch (Throwable $exception) {
            Logger::error('Membership payment ajax failure', array(
                'member_id' => isset($member->id) ? (int) $member->id : 0,
                'exception' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ), 'payments');
            wp_send_json_error(array('message' => __('Impossible de générer le lien de paiement.', 'mj-member')), 500);
        }

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

if (!function_exists('mj_member_ajax_create_child_payment_link')) {
    function mj_member_ajax_create_child_payment_link() {
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Vous devez être connecté pour gérer les cotisations des jeunes.', 'mj-member')), 403);
        }

        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'mj_member_create_child_payment_link')) {
            wp_send_json_error(array('message' => __('La vérification de sécurité a échoué. Merci de recharger la page.', 'mj-member')), 403);
        }

        $child_id = isset($_POST['child_id']) ? (int) $_POST['child_id'] : 0;
        if ($child_id <= 0) {
            wp_send_json_error(array('message' => __('Jeune invalide.', 'mj-member')), 400);
        }

        $guardian = mj_member_get_current_member();
        if (!$guardian || !mj_member_can_manage_children($guardian)) {
            wp_send_json_error(array('message' => __('Vous n’êtes pas autorisé à payer pour ce jeune.', 'mj-member')), 403);
        }

        $children = mj_member_get_guardian_children($guardian);
        $target_child = null;
        if (!empty($children)) {
            foreach ($children as $child) {
                if ($child && (int) $child->id === $child_id) {
                    $target_child = $child;
                    break;
                }
            }
        }

        if (!$target_child) {
            wp_send_json_error(array('message' => __('Ce jeune n’est pas lié à votre profil.', 'mj-member')), 403);
        }

        $status = mj_member_get_membership_status($target_child);
        if (empty($status['requires_payment']) || !in_array($status['status'], array('missing', 'expired', 'expiring'), true)) {
            wp_send_json_error(array('message' => __('La cotisation de ce jeune est déjà à jour.', 'mj-member')));
        }

        if (!class_exists('MjPayments')) {
            wp_send_json_error(array('message' => __('Le module de paiement n’est pas disponible.', 'mj-member')), 500);
        }

        $amount = (float) apply_filters('mj_member_child_membership_amount', (float) $status['amount'], $target_child, $guardian);
        $payment = MjPayments::create_stripe_payment((int) $target_child->id, $amount);

        if (!$payment || empty($payment['checkout_url'])) {
            wp_send_json_error(array('message' => __('Impossible de générer le lien de paiement pour ce jeune.', 'mj-member')), 500);
        }

        wp_send_json_success(array(
            'redirect_url' => esc_url_raw($payment['checkout_url']),
        ));
    }

    add_action('wp_ajax_mj_member_create_child_payment_link', 'mj_member_ajax_create_child_payment_link');
}

if (!function_exists('mj_member_ajax_update_child_profile')) {
    function mj_member_ajax_update_child_profile() {
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Vous devez être connecté pour modifier ce jeune.', 'mj-member')), 403);
        }

        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if ($nonce === '' || !wp_verify_nonce($nonce, 'mj_member_update_child_profile')) {
            wp_send_json_error(array('message' => __('La vérification de sécurité a échoué. Merci de réessayer.', 'mj-member')), 400);
        }

        $child_id = isset($_POST['child_id']) ? (int) $_POST['child_id'] : 0;
        if ($child_id <= 0) {
            wp_send_json_error(array('message' => __('Jeune introuvable.', 'mj-member')), 400);
        }

        $guardian = mj_member_get_current_member();
        if (!$guardian || !mj_member_can_manage_children($guardian)) {
            wp_send_json_error(array('message' => __('Accès refusé.', 'mj-member')), 403);
        }

        $children = mj_member_get_guardian_children($guardian);
        $target_child = null;
        if (!empty($children)) {
            foreach ($children as $child) {
                if ($child && (int) $child->id === $child_id) {
                    $target_child = $child;
                    break;
                }
            }
        }

        if (!$target_child) {
            wp_send_json_error(array('message' => __('Jeune introuvable ou non associé à votre compte.', 'mj-member')), 404);
        }

        $updates = array();
        $field_callbacks = array(
            'first_name' => 'sanitize_text_field',
            'last_name' => 'sanitize_text_field',
            'email' => 'sanitize_email',
            'phone' => 'sanitize_text_field',
            'birth_date' => 'sanitize_text_field',
        );

        if (class_exists('MjMembers') && MjMembers::hasColumn('notes')) {
            $field_callbacks['notes'] = 'sanitize_textarea_field';
        }

        foreach ($field_callbacks as $field => $callback) {
            if (array_key_exists($field, $_POST)) {
                $value = call_user_func($callback, wp_unslash((string) $_POST[$field]));
                $updates[$field] = $value;
            }
        }

        if (array_key_exists('first_name', $updates) && $updates['first_name'] === '') {
            wp_send_json_error(array('message' => __('Le prénom est obligatoire.', 'mj-member')), 400);
        }

        if (array_key_exists('last_name', $updates) && $updates['last_name'] === '') {
            wp_send_json_error(array('message' => __('Le nom de famille est obligatoire.', 'mj-member')), 400);
        }

        $updates['is_autonomous'] = !empty($_POST['is_autonomous']) ? 1 : 0;
        $updates['photo_usage_consent'] = !empty($_POST['photo_usage_consent']) ? 1 : 0;

        if (isset($updates['birth_date']) && $updates['birth_date'] === '') {
            $updates['birth_date'] = null;
        }

        if (isset($updates['email']) && $updates['email'] === '') {
            $updates['email'] = '';
        }

        $result = MjMembers::update($child_id, $updates);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 500);
        }

        $refreshed_child = null;
        $refreshed_children = mj_member_get_guardian_children_statuses($guardian);
        if (!empty($refreshed_children)) {
            foreach ($refreshed_children as $entry) {
                if ((int) $entry['id'] === $child_id) {
                    $refreshed_child = $entry;
                    break;
                }
            }
        }

        if (!$refreshed_child) {
            wp_send_json_error(array('message' => __('Les informations ont été mises à jour, mais le rechargement des données a échoué.', 'mj-member')), 500);
        }

        wp_send_json_success(array(
            'child' => $refreshed_child,
            'message' => __('Les informations du jeune ont été mises à jour.', 'mj-member'),
        ));
    }

    add_action('wp_ajax_mj_member_update_child_profile', 'mj_member_ajax_update_child_profile');
}

if (!function_exists('mj_member_ajax_update_notification_preferences')) {
    function mj_member_ajax_update_notification_preferences() {
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Vous devez être connecté pour modifier vos notifications.', 'mj-member')), 403);
        }

        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if ($nonce === '' || !wp_verify_nonce($nonce, 'mj_member_update_notification_preferences')) {
            wp_send_json_error(array('message' => __('La vérification de sécurité a échoué. Merci de réessayer.', 'mj-member')), 400);
        }

        $preferences_raw = isset($_POST['preferences']) ? wp_unslash($_POST['preferences']) : array();
        $preferences_data = array();

        if (is_array($preferences_raw)) {
            $preferences_data = $preferences_raw;
        } elseif (is_string($preferences_raw) && $preferences_raw !== '') {
            $decoded = json_decode($preferences_raw, true);
            if (is_array($decoded)) {
                $preferences_data = $decoded;
            }
        }

        $member = mj_member_get_current_member();
        if (!$member) {
            wp_send_json_error(array('message' => __('Votre profil MJ est introuvable.', 'mj-member')), 404);
        }

        $updated = MjMembers::updateNotificationPreferences($member->id, $preferences_data);
        if ($updated === false) {
            wp_send_json_error(array('message' => __('Impossible d’enregistrer vos préférences pour le moment.', 'mj-member')), 500);
        }

        $sms_opt_in = false;
        foreach ($updated as $key => $value) {
            if (strpos((string) $key, 'sms_') === 0 && !empty($value)) {
                $sms_opt_in = true;
                break;
            }
        }

        wp_send_json_success(array(
            'preferences' => $updated,
            'newsletter_opt_in' => !empty($updated['email_event_news']),
            'sms_opt_in' => $sms_opt_in,
            'message' => __('Vos préférences de notification ont été enregistrées.', 'mj-member'),
        ));
    }

    add_action('wp_ajax_mj_member_update_notification_preferences', 'mj_member_ajax_update_notification_preferences');
}

if (!function_exists('mj_member_collect_member_registration_entries')) {
    /**
     * Alimente le flux "Mes réservations" pour la page Mon Compte.
     *
     * @param array<int,array<string,mixed>> $registrations
     * @param int                             $member_id
     * @param array<string,mixed>             $args
     * @return array<int,array<string,mixed>>
     */
    function mj_member_collect_member_registration_entries($registrations, $member_id, $args = array()) {
        $member_id = (int) $member_id;
        if ($member_id <= 0) {
            return is_array($registrations) ? $registrations : array();
        }

        if (!class_exists('MjEventRegistrations') || !function_exists('mj_member_get_event_registrations_table_name') || !function_exists('mj_member_get_events_table_name')) {
            return is_array($registrations) ? $registrations : array();
        }

        if (class_exists('MjEventAttendance')) {
            MjEventAttendance::get_table_name();
        }

        $result_entries = array();
        $base_entries = is_array($registrations) ? $registrations : array();

        $limit = isset($args['limit']) ? max(1, (int) $args['limit']) : 10;
        $upcoming_only = !empty($args['upcoming_only']);

        $status_aliases = array(
            'pending' => MjEventRegistrations::STATUS_PENDING,
            'en_attente' => MjEventRegistrations::STATUS_PENDING,
            'confirmed' => MjEventRegistrations::STATUS_CONFIRMED,
            'valide' => MjEventRegistrations::STATUS_CONFIRMED,
            'cancelled' => MjEventRegistrations::STATUS_CANCELLED,
            'annule' => MjEventRegistrations::STATUS_CANCELLED,
            'waitlist' => MjEventRegistrations::STATUS_WAITLIST,
            'liste_attente' => MjEventRegistrations::STATUS_WAITLIST,
        );

        $requested_statuses = array();
        if (!empty($args['statuses']) && is_array($args['statuses'])) {
            foreach ($args['statuses'] as $candidate) {
                $key = sanitize_key((string) $candidate);
                if ($key === '') {
                    continue;
                }
                if (isset($status_aliases[$key])) {
                    $requested_statuses[$status_aliases[$key]] = true;
                } elseif (in_array($key, $status_aliases, true)) {
                    $requested_statuses[$key] = true;
                }
            }
        }

        $registrations_table = mj_member_get_event_registrations_table_name();
        $events_table = mj_member_get_events_table_name();
        if ($registrations_table === '' || $events_table === '') {
            return $base_entries;
        }

        if (function_exists('mj_member_table_exists')) {
            if (!mj_member_table_exists($registrations_table) || !mj_member_table_exists($events_table)) {
                return $base_entries;
            }
        }

        $location_join = '';
        $location_fields = array();
        if (function_exists('mj_member_get_event_locations_table_name') && function_exists('mj_member_table_exists')) {
            $locations_table = mj_member_get_event_locations_table_name();
            if ($locations_table !== '' && mj_member_table_exists($locations_table)) {
                $location_join = " LEFT JOIN {$locations_table} AS loc ON loc.id = events.location_id";
                $location_fields[] = 'loc.name AS location_name';
                $location_fields[] = 'loc.city AS location_city';
            }
        }

        $select_fields = array(
            'regs.id',
            'regs.event_id',
            'regs.member_id',
            'regs.guardian_id',
            'regs.statut',
            'regs.notes',
            'regs.created_at',
            'regs.payment_status',
            'regs.payment_method',
            'regs.payment_recorded_at',
            'events.title',
            'events.slug',
            'events.type',
            'events.status AS event_status',
            'events.date_debut',
            'events.date_fin',
            'events.schedule_mode',
            'events.schedule_payload',
            'events.recurrence_until',
            'events.article_id',
            'events.prix',
        );

        if (!empty($location_fields)) {
            $select_fields = array_merge($select_fields, $location_fields);
        }

        $where_clauses = array('regs.member_id = %d');
        $query_params = array($member_id);

        if (!empty($requested_statuses)) {
            $placeholders = implode(', ', array_fill(0, count($requested_statuses), '%s'));
            $where_clauses[] = "regs.statut IN ({$placeholders})";
            $query_params = array_merge($query_params, array_keys($requested_statuses));
        }

        $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
        $fetch_limit = max($limit * 3, $limit + 5, 25);

        global $wpdb;
        $sql = sprintf(
            'SELECT %s FROM %s AS regs INNER JOIN %s AS events ON events.id = regs.event_id%s %s ORDER BY regs.created_at DESC LIMIT %%d',
            implode(', ', $select_fields),
            $registrations_table,
            $events_table,
            $location_join,
            $where_sql
        );

        $query_params[] = $fetch_limit;
        array_unshift($query_params, $sql);
        $prepared_query = call_user_func_array(array($wpdb, 'prepare'), $query_params);
        $rows = $wpdb->get_results($prepared_query);
        if (empty($rows)) {
            return $base_entries;
        }

        $now = current_time('timestamp');
        $status_labels = MjEventRegistrations::get_status_labels();
        $payment_labels = MjEventRegistrations::get_payment_status_labels();
        $type_labels = method_exists('MjMembers', 'get_type_labels') ? MjMembers::get_type_labels() : array();
        $status_output_map = array(
            MjEventRegistrations::STATUS_PENDING => 'pending',
            MjEventRegistrations::STATUS_CONFIRMED => 'confirmed',
            MjEventRegistrations::STATUS_CANCELLED => 'cancelled',
            MjEventRegistrations::STATUS_WAITLIST => 'waitlist',
        );

        foreach ($rows as $row) {
            if (count($result_entries) >= $limit) {
                break;
            }

            $db_status = isset($row->statut) ? sanitize_key((string) $row->statut) : MjEventRegistrations::STATUS_PENDING;
            if ($db_status === '') {
                $db_status = MjEventRegistrations::STATUS_PENDING;
            }

            $output_status = isset($status_output_map[$db_status]) ? $status_output_map[$db_status] : 'pending';
            $status_label = isset($status_labels[$db_status]) ? $status_labels[$db_status] : $db_status;

            $start_raw = isset($row->date_debut) ? (string) $row->date_debut : '';
            $end_raw = isset($row->date_fin) ? (string) $row->date_fin : '';
            $start_ts = ($start_raw !== '' && $start_raw !== '0000-00-00 00:00:00') ? strtotime($start_raw) : 0;
            $end_ts = ($end_raw !== '' && $end_raw !== '0000-00-00 00:00:00') ? strtotime($end_raw) : 0;

            if ($upcoming_only) {
                $has_future_slot = false;
                if ($end_ts && $end_ts >= $now) {
                    $has_future_slot = true;
                } elseif ($start_ts && $start_ts >= $now) {
                    $has_future_slot = true;
                } elseif (class_exists('MjEventSchedule')) {
                    $future_occurrences = MjEventSchedule::get_occurrences($row, array('max' => 1, 'include_past' => false));
                    if (!empty($future_occurrences)) {
                        $has_future_slot = true;
                        if ($start_ts === 0 && isset($future_occurrences[0]['start'])) {
                            $probe = strtotime((string) $future_occurrences[0]['start']);
                            if ($probe) {
                                $start_ts = $probe;
                                $start_raw = date('Y-m-d H:i:s', $start_ts);
                            }
                        }
                        if ($end_ts === 0 && isset($future_occurrences[0]['end'])) {
                            $probe_end = strtotime((string) $future_occurrences[0]['end']);
                            if ($probe_end) {
                                $end_ts = $probe_end;
                                $end_raw = date('Y-m-d H:i:s', $end_ts);
                            }
                        }
                    }
                }

                if (!$has_future_slot) {
                    continue;
                }
            }

            $permalink = apply_filters('mj_member_event_permalink', '', $row);
            $actions = array();
            if ($permalink !== '') {
                $actions[] = array(
                    'label' => esc_html__('Voir l’événement', 'mj-member'),
                    'url' => esc_url($permalink),
                    'target' => '_self',
                );
            }

            $type_value = isset($row->type) ? sanitize_key((string) $row->type) : '';
            $type_label = isset($type_labels[$type_value]) ? $type_labels[$type_value] : $type_value;

            $location_label = '';
            if (!empty($row->location_name)) {
                $location_label = sanitize_text_field((string) $row->location_name);
                if (!empty($row->location_city)) {
                    $location_label .= ' (' . sanitize_text_field((string) $row->location_city) . ')';
                }
            }

            $payment_status_raw = isset($row->payment_status) ? sanitize_key((string) $row->payment_status) : 'unpaid';
            if ($payment_status_raw === '') {
                $payment_status_raw = 'unpaid';
            }
            $payment_status_label = isset($payment_labels[$payment_status_raw]) ? $payment_labels[$payment_status_raw] : $payment_status_raw;

            $notes_segments = array();
            if ($location_label !== '') {
                $notes_segments[] = '<strong>' . esc_html__('Lieu', 'mj-member') . '</strong> : ' . esc_html($location_label);
            }
            if ($payment_status_label !== '') {
                $notes_segments[] = '<strong>' . esc_html__('Paiement', 'mj-member') . '</strong> : ' . esc_html($payment_status_label);
            }

            if (method_exists('MjEventRegistrations', 'build_occurrence_summary')) {
                $occurrence_summary = MjEventRegistrations::build_occurrence_summary($row);
                if (!empty($occurrence_summary['occurrences']) && is_array($occurrence_summary['occurrences'])) {
                    $occurrence_labels = array();
                    foreach ($occurrence_summary['occurrences'] as $occurrence) {
                        if (empty($occurrence['label'])) {
                            continue;
                        }
                        $occurrence_labels[] = sanitize_text_field((string) $occurrence['label']);
                    }
                    if (!empty($occurrence_labels)) {
                        $notes_segments[] = '<strong>' . esc_html__('Occurrences', 'mj-member') . '</strong> : ' . esc_html(implode(', ', $occurrence_labels));
                    }
                }
            }

            if (!empty($row->notes)) {
                $notes_segments[] = '<strong>' . esc_html__('Note', 'mj-member') . '</strong> : ' . esc_html(wp_strip_all_tags((string) $row->notes));
            }

            $notes_html = '';
            if (!empty($notes_segments)) {
                $notes_html = '<p>' . implode('</p><p>', $notes_segments) . '</p>';
            }

            $result_entries[] = array(
                'id' => isset($row->id) ? (int) $row->id : 0,
                'title' => isset($row->title) ? sanitize_text_field((string) $row->title) : __('Événement MJ', 'mj-member'),
                'status' => $output_status,
                'status_label' => sanitize_text_field($status_label),
                'type' => $type_label !== '' ? sanitize_text_field($type_label) : '',
                'start_date' => $start_raw,
                'end_date' => $end_raw,
                'location' => $location_label,
                'actions' => $actions,
                'notes' => $notes_html,
            );
        }

        if (empty($result_entries)) {
            return $base_entries;
        }

        $combined = array_merge($base_entries, $result_entries);
        if ($limit > 0 && count($combined) > $limit) {
            $combined = array_slice($combined, 0, $limit);
        }

        return $combined;
    }

    add_filter('mj_member_member_registrations', 'mj_member_collect_member_registration_entries', 10, 3);
}

// --- Gestion des liens QR des cartes de visite ---

if (!isset($GLOBALS['mj_member_card_claim_state']) || !is_array($GLOBALS['mj_member_card_claim_state'])) {
    $GLOBALS['mj_member_card_claim_state'] = array();
}

if (!function_exists('mj_member_get_card_claim_base_url')) {
    function mj_member_get_card_claim_base_url() {
        $registration_url = '';

        if (function_exists('mj_member_login_component_get_registration_url')) {
            $registration_url = mj_member_login_component_get_registration_url();
        } else {
            $page_id = (int) get_option('mj_login_registration_page', 0);
            if ($page_id > 0) {
                $permalink = get_permalink($page_id);
                if (!empty($permalink)) {
                    $registration_url = $permalink;
                }
            }

            if ($registration_url === '') {
                $default_slug = ltrim(apply_filters('mj_member_login_registration_default_slug', 'inscription'), '/');
                $registration_url = home_url('/' . $default_slug);
            }
        }

        if ($registration_url === '') {
            $registration_url = home_url('/mon-compte/');
        }

        $default = $registration_url;
        $filtered = apply_filters('mj_member_card_claim_base_url', $default);

        $final_url = $filtered !== '' ? $filtered : $default;

        return esc_url_raw($final_url);
    }
}

if (!function_exists('mj_member_sanitize_card_key')) {
    function mj_member_sanitize_card_key($value) {
        if (!is_string($value)) {
            if (is_scalar($value)) {
                $value = (string) $value;
            } else {
                return '';
            }
        }

        $value = strtolower(sanitize_text_field(wp_unslash($value)));
        if ($value === '') {
            return '';
        }

        $value = preg_replace('/[^a-z0-9]/', '', $value);
        if (!is_string($value)) {
            return '';
        }

        return substr($value, 0, 64);
    }
}

if (!function_exists('mj_member_get_card_claim_state')) {
    function mj_member_get_card_claim_state() {
        return is_array($GLOBALS['mj_member_card_claim_state']) ? $GLOBALS['mj_member_card_claim_state'] : array();
    }
}

if (!function_exists('mj_member_set_card_claim_state')) {
    function mj_member_set_card_claim_state($state) {
        if (!is_array($state)) {
            $state = array();
        }
        $GLOBALS['mj_member_card_claim_state'] = $state;
    }
}

if (!function_exists('mj_member_get_card_key_from_request')) {
    function mj_member_get_card_key_from_request() {
        $key = '';
        if (isset($_POST['mj_card_key'])) {
            $key = mj_member_sanitize_card_key($_POST['mj_card_key']);
        } elseif (isset($_GET['mj_card'])) {
            $key = mj_member_sanitize_card_key($_GET['mj_card']);
        }

        return $key;
    }
}

if (!function_exists('mj_member_is_card_claim_request')) {
    function mj_member_is_card_claim_request() {
        if (is_admin()) {
            return false;
        }

        if (defined('REST_REQUEST') && REST_REQUEST) {
            return false;
        }

        if ('cli' === PHP_SAPI) {
            return false;
        }

        $key = mj_member_get_card_key_from_request();
        return $key !== '';
    }
}

if (!function_exists('mj_member_get_member_by_card_key')) {
    function mj_member_get_member_by_card_key($key) {
        if (!class_exists('MjMembers')) {
            return null;
        }

        return MjMembers::getByCardAccessKey($key);
    }
}

if (!function_exists('mj_member_get_card_login_url')) {
    function mj_member_get_card_login_url($member, $card_key = '') {
        $redirect = function_exists('mj_member_get_account_redirect')
            ? mj_member_get_account_redirect()
            : home_url('/mon-compte/');

        $login_url = wp_login_url($redirect);

        return apply_filters('mj_member_card_claim_login_url', $login_url, $member, $card_key);
    }
}

if (!function_exists('mj_member_get_card_claim_url')) {
    function mj_member_get_card_claim_url($member_id) {
        $member_id = (int) $member_id;
        if ($member_id <= 0 || !class_exists('MjMembers')) {
            return '';
        }

        $key = MjMembers::ensureCardAccessKey($member_id);
        if ($key === '') {
            return '';
        }

        $base = mj_member_get_card_claim_base_url();
        $url = add_query_arg('mj_card', rawurlencode($key), $base);

        return apply_filters('mj_member_card_claim_url', $url, $member_id, $key);
    }
}

if (!function_exists('mj_member_maybe_process_card_claim_form')) {
    function mj_member_maybe_process_card_claim_form() {
        if (!mj_member_is_card_claim_request()) {
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $state = array(
            'errors' => array(),
            'values' => array(),
        );

        $nonce = isset($_POST['mj_member_card_claim_nonce'])
            ? sanitize_text_field(wp_unslash($_POST['mj_member_card_claim_nonce']))
            : '';

        if (!wp_verify_nonce($nonce, 'mj_member_card_claim')) {
            $state['errors'][] = __('La vérification de sécurité a échoué. Merci de réessayer.', 'mj-member');
            mj_member_set_card_claim_state($state);
            return;
        }

        $key = mj_member_get_card_key_from_request();
        if ($key === '') {
            $state['errors'][] = __('Ce lien n’est plus valide.', 'mj-member');
            mj_member_set_card_claim_state($state);
            return;
        }

        $member = mj_member_get_member_by_card_key($key);
        if (!$member) {
            $state['errors'][] = __('Nous ne retrouvons pas ce membre. Contactez l’équipe MJ pour obtenir une nouvelle carte.', 'mj-member');
            mj_member_set_card_claim_state($state);
            return;
        }

        if (!empty($member->wp_user_id)) {
            $login_url = mj_member_get_card_login_url($member, $key);
            wp_safe_redirect($login_url);
            exit;
        }

        $email = isset($_POST['mj_card_email']) ? sanitize_email(wp_unslash($_POST['mj_card_email'])) : '';
        $phone = isset($_POST['mj_card_phone']) ? sanitize_text_field(wp_unslash($_POST['mj_card_phone'])) : '';
        $password = isset($_POST['mj_card_password']) ? (string) wp_unslash($_POST['mj_card_password']) : '';
        $password_confirm = isset($_POST['mj_card_password_confirm']) ? (string) wp_unslash($_POST['mj_card_password_confirm']) : '';

        if ($email === '' && !empty($member->email)) {
            $email = sanitize_email((string) $member->email);
        }

        if ($email === '' || !is_email($email)) {
            $state['errors'][] = __('Merci de renseigner une adresse email valide. Elle servira d’identifiant.', 'mj-member');
        }

        $min_length = (int) apply_filters('mj_member_min_password_length', 8, $member);
        if ($password === '' || strlen($password) < $min_length) {
            $state['errors'][] = sprintf(
                __('Le mot de passe doit contenir au moins %d caractères.', 'mj-member'),
                $min_length
            );
        }

        if ($password !== $password_confirm) {
            $state['errors'][] = __('Les mots de passe ne correspondent pas.', 'mj-member');
        }

        $state['values'] = array(
            'email' => $email,
            'phone' => $phone,
        );

        if (!empty($state['errors'])) {
            mj_member_set_card_claim_state($state);
            return;
        }

        $updates = array('email' => $email);
        if ($phone !== '') {
            $updates['phone'] = $phone;
        }

        $update_result = MjMembers::update($member->id, $updates);
        if (is_wp_error($update_result)) {
            $state['errors'][] = $update_result->get_error_message();
            mj_member_set_card_claim_state($state);
            return;
        }

        $sync_result = mj_member_sync_member_user_account($member->id, array(
            'user_pass' => $password,
            'send_notification' => false,
            'return_error' => true,
            'min_password_length' => $min_length,
        ));

        if (is_wp_error($sync_result)) {
            $state['errors'][] = $sync_result->get_error_message();
            mj_member_set_card_claim_state($state);
            return;
        }

        $user_id = (int) $sync_result;
        if ($user_id > 0) {
            $user = get_user_by('id', $user_id);
            if ($user && !is_wp_error($user)) {
                wp_set_current_user($user_id, $user->user_login);
                wp_set_auth_cookie($user_id, false);
                do_action('wp_login', $user->user_login, $user);
            }
        }

        $redirect = function_exists('mj_member_get_account_redirect')
            ? mj_member_get_account_redirect()
            : home_url('/mon-compte/');

        $redirect = apply_filters('mj_member_card_claim_redirect', $redirect, $member, $user_id);

        wp_safe_redirect($redirect);
        exit;
    }

    add_action('init', 'mj_member_maybe_process_card_claim_form');
}

if (!function_exists('mj_member_maybe_override_card_template')) {
    function mj_member_maybe_override_card_template($template) {
        if (!mj_member_is_card_claim_request()) {
            return $template;
        }

        $key = mj_member_get_card_key_from_request();
        if ($key === '') {
            return $template;
        }

        $member = mj_member_get_member_by_card_key($key);

        if ($member && !empty($member->wp_user_id)) {
            $login_url = mj_member_get_card_login_url($member, $key);
            wp_safe_redirect($login_url);
            exit;
        }

        $state = mj_member_get_card_claim_state();
        if (!$member) {
            $message = __('Ce lien n’est plus valide ou a déjà été utilisé.', 'mj-member');
            if (empty($state['errors']) || !in_array($message, $state['errors'], true)) {
                $state['errors'][] = $message;
            }
        }

        mj_member_set_card_claim_state($state);

        $template_path = \Mj\Member\Core\Config::path() . 'includes/templates/card_claim.php';
        if (!file_exists($template_path)) {
            return $template;
        }

        global $mj_member_card_claim_context;
        $mj_member_card_claim_context = array(
            'member' => $member,
            'card_key' => $key,
            'state' => mj_member_get_card_claim_state(),
            'base_url' => mj_member_get_card_claim_base_url(),
        );

        return $template_path;
    }

    add_filter('template_include', 'mj_member_maybe_override_card_template', 80);
}
