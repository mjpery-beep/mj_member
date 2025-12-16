<?php

use Mj\Member\Core\Config;
use Mj\Member\Classes\Crud\MjMembers;

if (!defined('ABSPATH')) {
    exit;
}

// Admin AJAX handlers for payment management.
add_action('wp_ajax_mj_admin_get_qr', 'mj_admin_get_qr_callback');
add_action('wp_ajax_mj_admin_get_payment_history', 'mj_admin_get_payment_history');
add_action('wp_ajax_mj_admin_delete_payment', 'mj_admin_delete_payment');
add_action('wp_ajax_mj_member_mark_paid', 'mj_member_mark_paid');
add_action('wp_ajax_mj_admin_generate_event_payment_link', 'mj_admin_generate_event_payment_link');

function mj_admin_generate_event_payment_link() {
    $nonce = isset($_POST['nonce']) ? sanitize_text_field((string) $_POST['nonce']) : '';
    if ($nonce === '' || !wp_verify_nonce($nonce, 'mj_admin_event_payment')) {
        wp_send_json_error(array('message' => __('Nonce invalide.', 'mj-member')), 403);
    }

    if (!current_user_can(Config::capability())) {
        wp_send_json_error(array('message' => __('Accès non autorisé.', 'mj-member')), 403);
    }

    $event_id = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;
    $registration_id = isset($_POST['registration_id']) ? (int) $_POST['registration_id'] : 0;
    if ($event_id <= 0 || $registration_id <= 0) {
        wp_send_json_error(array('message' => __('Données manquantes pour générer le paiement.', 'mj-member')), 400);
    }

    require_once Config::path() . 'includes/classes/crud/MjEvents.php';
    require_once Config::path() . 'includes/classes/crud/MjEventRegistrations.php';
    require_once Config::path() . 'includes/classes/MjPayments.php';

    $event = MjEvents::find($event_id);
    if (!$event) {
        wp_send_json_error(array('message' => __('Evenement introuvable.', 'mj-member')), 404);
    }

    $event_price = isset($event->prix) ? (float) $event->prix : 0.0;
    if ($event_price <= 0) {
        wp_send_json_error(array('message' => __('Cet événement ne nécessite pas de paiement.', 'mj-member')));
    }

    $registration = MjEventRegistrations::get($registration_id);
    if (!$registration || (int) $registration->event_id !== $event_id) {
        wp_send_json_error(array('message' => __('Inscription introuvable pour cet événement.', 'mj-member')), 404);
    }

    if ($registration->statut === MjEventRegistrations::STATUS_WAITLIST) {
        wp_send_json_error(array('message' => __('Inscription en liste d\'attente : aucun paiement requis.', 'mj-member')));
    }
    if ($registration->statut === MjEventRegistrations::STATUS_CANCELLED) {
        wp_send_json_error(array('message' => __('Inscription annulée : paiement non disponible.', 'mj-member')));
    }

    $member_id = isset($registration->member_id) ? (int) $registration->member_id : 0;
    if ($member_id <= 0) {
        wp_send_json_error(array('message' => __('Membre non défini pour cette inscription.', 'mj-member')));
    }

    if (!class_exists('MjPayments')) {
        wp_send_json_error(array('message' => __('Module de paiement indisponible.', 'mj-member')), 500);
    }

    $occurrence_scope = 'all';
    $occurrence_count = 1;
    $occurrence_list = array();
    if (class_exists('MjEventRegistrations') && method_exists('MjEventRegistrations', 'build_occurrence_summary')) {
        $occurrence_summary = MjEventRegistrations::build_occurrence_summary($registration);
        if (is_array($occurrence_summary)) {
            if (!empty($occurrence_summary['scope'])) {
                $occurrence_scope = sanitize_key((string) $occurrence_summary['scope']);
            }
            if (!empty($occurrence_summary['count'])) {
                $occurrence_count_candidate = (int) $occurrence_summary['count'];
                if ($occurrence_scope === 'custom' && $occurrence_count_candidate > 0) {
                    $occurrence_count = $occurrence_count_candidate;
                }
            }
            if (!empty($occurrence_summary['occurrences']) && is_array($occurrence_summary['occurrences'])) {
                foreach ($occurrence_summary['occurrences'] as $occurrence_entry) {
                    if (!is_array($occurrence_entry)) {
                        continue;
                    }
                    if (empty($occurrence_entry['start'])) {
                        continue;
                    }
                    $occurrence_list[] = sanitize_text_field((string) $occurrence_entry['start']);
                }
            }
        }
    }

    $payment = MjPayments::create_stripe_payment(
        $member_id,
        $event_price,
        array(
            'context' => 'event',
            'event_id' => $event_id,
            'registration_id' => $registration_id,
            'event' => $event,
            'payer_id' => get_current_user_id(),
            'occurrence_mode' => $occurrence_scope,
            'occurrence_count' => $occurrence_count,
            'occurrence_list' => $occurrence_list,
        )
    );

    if (!$payment || empty($payment['checkout_url'])) {
        wp_send_json_error(array('message' => __('Impossible de générer le lien de paiement.', 'mj-member')), 500);
    }

    $default_total = (float) $event_price * max(1, $occurrence_count);
    $amount_label = isset($payment['amount_label']) && $payment['amount_label'] !== ''
        ? $payment['amount_label']
        : number_format_i18n(isset($payment['amount_raw']) ? (float) $payment['amount_raw'] : $default_total, 2);
    $message = sprintf(
        __('Lien genere. Montant : %s EUR.', 'mj-member'),
        $amount_label
    );

    wp_send_json_success(array(
        'checkout_url' => esc_url_raw($payment['checkout_url']),
        'qr_url' => isset($payment['qr_url']) ? esc_url_raw($payment['qr_url']) : '',
        'amount' => $amount_label,
        'message' => $message,
        'occurrence_count' => $occurrence_count,
        'occurrence_mode' => $occurrence_scope,
    ));
}

function mj_admin_get_qr_callback() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mj_admin_payments_nonce')) {
        wp_send_json_error('Nonce invalide');
    }
    if (!current_user_can(Config::capability())) {
        wp_send_json_error('Accès non autorisé');
    }
    $member_id = isset($_POST['member_id']) ? intval($_POST['member_id']) : 0;
    if ($member_id <= 0) {
        wp_send_json_error('Member missing');
    }
    require_once Config::path() . 'includes/classes/MjPayments.php';
    $info = MjPayments::create_payment_record($member_id);
    if (!$info) {
        wp_send_json_error('Erreur création paiement');
    }

    $safe_response = array(
        'payment_id' => isset($info['payment_id']) ? $info['payment_id'] : null,
        'stripe_session_id' => isset($info['stripe_session_id']) ? $info['stripe_session_id'] : null,
        'checkout_url' => isset($info['checkout_url']) ? $info['checkout_url'] : (isset($info['confirm_url']) ? $info['confirm_url'] : null),
        'qr_url' => isset($info['qr_url']) ? $info['qr_url'] : null,
        'amount' => isset($info['amount']) ? $info['amount'] : '2.00'
    );

    wp_send_json_success($safe_response);
}

function mj_admin_get_payment_history() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mj_admin_payments_nonce')) {
        wp_send_json_error('Nonce invalide.');
    }
    if (!current_user_can(Config::capability())) {
        wp_send_json_error('Permissions insuffisantes.');
    }

    $member_id = isset($_POST['member_id']) ? intval($_POST['member_id']) : 0;
    if ($member_id <= 0) {
        wp_send_json_error('ID membre invalide.');
    }

    $requested_filter = isset($_POST['payment_filter']) ? sanitize_key((string) $_POST['payment_filter']) : 'all';
    $allowed_filters = array('all', 'event', 'membership', 'other');
    if (!in_array($requested_filter, $allowed_filters, true)) {
        $requested_filter = 'all';
    }

    global $wpdb;
    $history_table = $wpdb->prefix . 'mj_payment_history';
    $payments_table = $wpdb->prefix . 'mj_payments';
    $events_table = $wpdb->prefix . 'mj_events';
    if (class_exists('MjEvents')) {
        $events_table = $wpdb->prefix . MjEvents::TABLE;
    }

    $entries = array();
    $seen_payment_ids = array();

    $history_rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT h.id AS history_id, h.member_id, h.payment_date, h.amount AS history_amount, h.method AS history_method, h.reference, h.payer_id,
                    p.id AS payment_id, p.amount AS payment_amount, p.status AS payment_status, p.context AS payment_context,
                    p.event_id, p.registration_id, p.external_ref, p.created_at AS payment_created_at, p.paid_at AS payment_paid_at,
                    p.payer_id AS payment_payer_id, e.title AS event_title
             FROM $history_table h
             LEFT JOIN $payments_table p
                ON p.member_id = h.member_id
               AND (p.external_ref = h.reference OR CAST(p.id AS CHAR) = h.reference)
             LEFT JOIN $events_table e ON e.id = p.event_id
             WHERE h.member_id = %d
             ORDER BY h.payment_date DESC",
            $member_id
        )
    );

    if ($history_rows) {
        foreach ($history_rows as $row) {
            $payment_id = isset($row->payment_id) ? (int) $row->payment_id : 0;
            if ($payment_id > 0) {
                $seen_payment_ids[$payment_id] = true;
            }

            $reference_source = $row->reference;
            if (!$reference_source && !empty($row->external_ref)) {
                $reference_source = $row->external_ref;
            }
            $reference = $reference_source ? sanitize_text_field($reference_source) : __('N/A', 'mj-member');

            $history_amount = isset($row->history_amount) ? (float) $row->history_amount : null;
            $payment_amount = isset($row->payment_amount) ? (float) $row->payment_amount : null;
            $amount_value = $history_amount !== null && $history_amount > 0 ? $history_amount : $payment_amount;
            if ($amount_value === null) {
                $amount_value = 0.0;
            }

            $date_source = '';
            if (!empty($row->payment_date)) {
                $date_source = $row->payment_date;
            } elseif (!empty($row->payment_paid_at)) {
                $date_source = $row->payment_paid_at;
            } elseif (!empty($row->payment_created_at)) {
                $date_source = $row->payment_created_at;
            }
            $timestamp = $date_source ? strtotime($date_source) : 0;
            $display_date = $timestamp ? date_i18n('d/m/Y H:i', $timestamp) : __('Inconnue', 'mj-member');

            $context_key = !empty($row->payment_context) ? sanitize_key($row->payment_context) : '';
            if ($context_key === '' && !empty($row->event_id)) {
                $context_key = 'event';
            }
            if ($context_key === '') {
                $context_key = 'other';
            }

            $event_id = !empty($row->event_id) ? (int) $row->event_id : 0;
            $event_title = !empty($row->event_title) ? sanitize_text_field($row->event_title) : '';

            $status_key = !empty($row->payment_status) ? sanitize_key($row->payment_status) : '';
            if ($status_key === '' && !empty($row->history_method)) {
                $status_key = sanitize_key($row->history_method);
            }
            if ($status_key === '') {
                $status_key = 'historic';
            }

            $method_label = !empty($row->history_method) ? sanitize_text_field($row->history_method) : '';
            if ($method_label === '') {
                if ($context_key === 'event' || $context_key === 'membership') {
                    $method_label = __('Stripe Checkout', 'mj-member');
                } else {
                    $method_label = __('Historique (manuel)', 'mj-member');
                }
            }

            $entries[] = array(
                'id' => 'history-' . (isset($row->history_id) ? (int) $row->history_id : 0),
                'origin' => 'history',
                'history_id' => isset($row->history_id) ? (int) $row->history_id : 0,
                'payment_id' => $payment_id ?: null,
                'date' => $display_date,
                'timestamp' => $timestamp,
                'amount' => number_format((float) $amount_value, 2),
                'reference' => $reference,
                'status' => $status_key,
                'status_label' => mj_format_payment_status($status_key),
                'method' => $method_label,
                'context' => $context_key,
                'context_label' => mj_format_payment_context_label($context_key, $event_title),
                'event' => $event_id ? array('id' => $event_id, 'title' => $event_title) : null,
                'is_event' => $context_key === 'event',
                'payer_id' => isset($row->payer_id) ? (int) $row->payer_id : (isset($row->payment_payer_id) ? (int) $row->payment_payer_id : 0),
            );
        }
    }

    $payments_rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT p.id AS payment_id, p.amount AS payment_amount, p.status AS payment_status, p.context AS payment_context,
                    p.event_id, p.registration_id, p.external_ref, p.created_at AS payment_created_at,
                    p.paid_at AS payment_paid_at, p.payer_id AS payment_payer_id,
                    e.title AS event_title
             FROM $payments_table p
             LEFT JOIN $events_table e ON e.id = p.event_id
             WHERE p.member_id = %d
             ORDER BY COALESCE(p.paid_at, p.created_at) DESC",
            $member_id
        )
    );

    if ($payments_rows) {
        foreach ($payments_rows as $row) {
            $payment_id = isset($row->payment_id) ? (int) $row->payment_id : 0;
            if ($payment_id > 0 && isset($seen_payment_ids[$payment_id])) {
                continue;
            }

            $context_key = !empty($row->payment_context) ? sanitize_key($row->payment_context) : '';
            if ($context_key === '' && !empty($row->event_id)) {
                $context_key = 'event';
            }
            if ($context_key === '') {
                $context_key = 'membership';
            }

            $event_id = !empty($row->event_id) ? (int) $row->event_id : 0;
            $event_title = !empty($row->event_title) ? sanitize_text_field($row->event_title) : '';

            $date_source = !empty($row->payment_paid_at) ? $row->payment_paid_at : $row->payment_created_at;
            $timestamp = $date_source ? strtotime($date_source) : 0;
            $display_date = $timestamp ? date_i18n('d/m/Y H:i', $timestamp) : __('Inconnue', 'mj-member');

            $status_key = !empty($row->payment_status) ? sanitize_key($row->payment_status) : 'historic';

            $method_label = ($context_key === 'event' || $context_key === 'membership')
                ? __('Stripe Checkout', 'mj-member')
                : __('Historique (manuel)', 'mj-member');

            $entries[] = array(
                'id' => 'payment-' . $payment_id,
                'origin' => 'payment',
                'history_id' => null,
                'payment_id' => $payment_id,
                'date' => $display_date,
                'timestamp' => $timestamp,
                'amount' => number_format((float) $row->payment_amount, 2),
                'reference' => !empty($row->external_ref) ? sanitize_text_field($row->external_ref) : __('N/A', 'mj-member'),
                'status' => $status_key,
                'status_label' => mj_format_payment_status($status_key),
                'method' => $method_label,
                'context' => $context_key,
                'context_label' => mj_format_payment_context_label($context_key, $event_title),
                'event' => $event_id ? array('id' => $event_id, 'title' => $event_title) : null,
                'is_event' => $context_key === 'event',
                'payer_id' => isset($row->payment_payer_id) ? (int) $row->payment_payer_id : 0,
            );
        }
    }

    if (!empty($entries)) {
        $filtered_entries = array();
        foreach ($entries as $entry) {
            $context = isset($entry['context']) ? $entry['context'] : 'other';
            if ($requested_filter === 'event' && $context !== 'event') {
                continue;
            }
            if ($requested_filter === 'membership' && $context !== 'membership') {
                continue;
            }
            if ($requested_filter === 'other' && in_array($context, array('event', 'membership'), true)) {
                continue;
            }
            $filtered_entries[] = $entry;
        }
        $entries = $filtered_entries;

        usort(
            $entries,
            static function ($a, $b) {
                $tsA = isset($a['timestamp']) ? (int) $a['timestamp'] : 0;
                $tsB = isset($b['timestamp']) ? (int) $b['timestamp'] : 0;
                if ($tsA === $tsB) {
                    return 0;
                }
                return ($tsA > $tsB) ? -1 : 1;
            }
        );
    }

    $filters = array(
        array('value' => 'all', 'label' => __('Tous', 'mj-member')),
        array('value' => 'event', 'label' => __('Événements', 'mj-member')),
        array('value' => 'membership', 'label' => __('Cotisations', 'mj-member')),
        array('value' => 'other', 'label' => __('Autres paiements', 'mj-member')),
    );

    wp_send_json_success(array(
        'payments' => array_values($entries),
        'can_delete' => current_user_can(Config::capability()),
        'filters' => $filters,
        'active_filter' => $requested_filter,
    ));
}

function mj_format_payment_status($status) {
    $status = strtolower(trim((string)$status));

    switch ($status) {
        case 'paid':
        case 'succeeded':
        case 'completed':
            return __('✅ Payé – paiement confirmé par Stripe/Webhook', 'mj-member');

        case 'pending':
        case 'requires_payment_method':
        case 'requires_action':
            return __('⏳ En attente – paiement créé, en cours de confirmation', 'mj-member');

        case 'canceled':
        case 'cancelled':
            return __('🚫 Annulé – paiement annulé ou expiré', 'mj-member');

        case 'failed':
        case 'requires_payment_method_failed':
            return __('❌ Échec – tentative de paiement refusée', 'mj-member');

        default:
            return __('ℹ️ Statut inconnu / historique importé', 'mj-member');
    }
}

function mj_format_payment_context_label($context, $event_title = '') {
    $context = sanitize_key((string) $context);
    $event_title = sanitize_text_field((string) $event_title);

    switch ($context) {
        case 'event':
            return $event_title !== ''
                ? sprintf(__('Paiement événement – %s', 'mj-member'), $event_title)
                : __('Paiement événement', 'mj-member');

        case 'membership':
            return __('Cotisation MJ', 'mj-member');

        case 'registration':
            return __('Inscription événement', 'mj-member');

        default:
            return __('Autre paiement', 'mj-member');
    }
}

function mj_admin_delete_payment() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mj_admin_payments_nonce')) {
        wp_send_json_error('Nonce invalide.');
    }
    if (!current_user_can(Config::capability())) {
        wp_send_json_error('Permissions insuffisantes.');
    }

    $member_id = isset($_POST['member_id']) ? intval($_POST['member_id']) : 0;
    $history_id = isset($_POST['history_id']) ? intval($_POST['history_id']) : 0;
    $payment_id = isset($_POST['payment_id']) ? intval($_POST['payment_id']) : 0;

    if (!$member_id || (!$history_id && !$payment_id)) {
        wp_send_json_error('Données invalides.');
    }

    global $wpdb;
    $history_table = $wpdb->prefix . 'mj_payment_history';
    $payments_table = $wpdb->prefix . 'mj_payments';

    $deleted = false;

    if ($history_id) {
        $deleted = (false !== $wpdb->delete($history_table, array('id' => $history_id, 'member_id' => $member_id), array('%d', '%d')));
    }

    if ($payment_id) {
        $deleted_payment = (false !== $wpdb->delete($payments_table, array('id' => $payment_id, 'member_id' => $member_id), array('%d', '%d')));
        $deleted = $deleted || $deleted_payment;
    }

    if (!$deleted) {
        wp_send_json_error('Aucune ligne supprimée.');
    }

    wp_send_json_success();
}

function mj_member_mark_paid() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mj_admin_payments_nonce')) {
        wp_send_json_error(array('message' => __('Nonce invalide.', 'mj-member')), 403);
    }

    if (!current_user_can(Config::capability())) {
        wp_send_json_error(array('message' => __('Permissions insuffisantes.', 'mj-member')), 403);
    }

    $member_id = isset($_POST['member_id']) ? intval($_POST['member_id']) : 0;
    if ($member_id <= 0) {
        wp_send_json_error(array('message' => __('ID membre invalide.', 'mj-member')));
    }

    $member = MjMembers::getById($member_id);
    if (!$member) {
        wp_send_json_error(array('message' => __('Membre introuvable.', 'mj-member')));
    }

    $admin_user_id = get_current_user_id();

    $now = current_time('mysql');

    $update_payload = array(
        'date_last_payement' => $now,
        'status' => MjMembers::STATUS_ACTIVE,
    );

    $updated = MjMembers::update($member_id, $update_payload);
    if (is_wp_error($updated)) {
        wp_send_json_error(array('message' => $updated->get_error_message()));
    }

    global $wpdb;
    $history_table = $wpdb->prefix . 'mj_payment_history';
    $table_like = $wpdb->esc_like($history_table);
    $table_check = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_like));
    if ($table_check === $history_table) {
        $amount = (float) apply_filters('mj_member_membership_amount', (float) get_option('mj_annual_fee', '2.00'), $member);
        $amount_formatted = number_format($amount, 2, '.', '');
        $reference = 'manual-' . wp_generate_password(8, false, false);
        $history_data = array(
            'member_id' => $member_id,
            'amount' => $amount_formatted,
            'payment_date' => $now,
            'method' => 'manual_admin',
            'reference' => $reference,
        );
        $history_format = array('%d', '%f', '%s', '%s', '%s');

        if ($admin_user_id > 0) {
            $history_data = array(
                'member_id' => $member_id,
                'payer_id' => $admin_user_id,
                'amount' => $amount_formatted,
                'payment_date' => $now,
                'method' => 'manual_admin',
                'reference' => $reference,
            );
            $history_format = array('%d', '%d', '%f', '%s', '%s', '%s');
        }

        $wpdb->insert(
            $history_table,
            $history_data,
            $history_format
        );
    }

    $updated_member = MjMembers::getById($member_id);
    $date_display = ($updated_member && !empty($updated_member->date_last_payement)) ? wp_date('d/m/Y', strtotime($updated_member->date_last_payement)) : '';
    $status_label = ($updated_member && $updated_member->status === MjMembers::STATUS_ACTIVE) ? __('Actif', 'mj-member') : __('Inactif', 'mj-member');

    $admin_name = '';
    if ($admin_user_id > 0) {
        $user_obj = get_userdata($admin_user_id);
        if ($user_obj) {
            $admin_name = $user_obj->display_name ?: $user_obj->user_login;
        }
    }

    $response = array(
        'message' => __('Cotisation enregistrée.', 'mj-member'),
        'date_last_payement' => $date_display,
        'status_label' => $status_label,
        'recorded_by' => array(
            'id' => $admin_user_id,
            'name' => $admin_name,
        ),
    );

    if ($updated_member && function_exists('mj_member_get_membership_status')) {
        $status_info = mj_member_get_membership_status($updated_member);
        if (is_array($status_info)) {
            $response['membership_status'] = $status_info['status_label'];
            $response['membership_status_key'] = $status_info['status'];
        }
    }

    wp_send_json_success($response);
}
