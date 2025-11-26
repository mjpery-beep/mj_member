<?php

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

    if (!current_user_can(MJ_MEMBER_CAPABILITY)) {
        wp_send_json_error(array('message' => __('Accès non autorisé.', 'mj-member')), 403);
    }

    $event_id = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;
    $registration_id = isset($_POST['registration_id']) ? (int) $_POST['registration_id'] : 0;
    if ($event_id <= 0 || $registration_id <= 0) {
        wp_send_json_error(array('message' => __('Données manquantes pour générer le paiement.', 'mj-member')), 400);
    }

    require_once MJ_MEMBER_PATH . 'includes/classes/crud/MjEvents_CRUD.php';
    require_once MJ_MEMBER_PATH . 'includes/classes/crud/MjEventRegistrations.php';
    require_once MJ_MEMBER_PATH . 'includes/classes/MjPayments.php';

    $event = MjEvents_CRUD::find($event_id);
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

    $payment = MjPayments::create_stripe_payment(
        $member_id,
        $event_price,
        array(
            'context' => 'event',
            'event_id' => $event_id,
            'registration_id' => $registration_id,
            'event' => $event,
            'payer_id' => get_current_user_id(),
        )
    );

    if (!$payment || empty($payment['checkout_url'])) {
        wp_send_json_error(array('message' => __('Impossible de générer le lien de paiement.', 'mj-member')), 500);
    }

    $amount_label = number_format((float) $event_price, 2, ',', ' ');
    $message = sprintf(
        __('Lien genere. Montant : %s EUR.', 'mj-member'),
        $amount_label
    );

    wp_send_json_success(array(
        'checkout_url' => esc_url_raw($payment['checkout_url']),
        'qr_url' => isset($payment['qr_url']) ? esc_url_raw($payment['qr_url']) : '',
        'amount' => $amount_label,
        'message' => $message,
    ));
}

function mj_admin_get_qr_callback() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mj_admin_payments_nonce')) {
        wp_send_json_error('Nonce invalide');
    }
    if (!current_user_can(MJ_MEMBER_CAPABILITY)) {
        wp_send_json_error('Accès non autorisé');
    }
    $member_id = isset($_POST['member_id']) ? intval($_POST['member_id']) : 0;
    if ($member_id <= 0) {
        wp_send_json_error('Member missing');
    }
    require_once MJ_MEMBER_PATH . 'includes/classes/MjPayments.php';
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
    if (!current_user_can(MJ_MEMBER_CAPABILITY)) {
        wp_send_json_error('Permissions insuffisantes.');
    }

    $member_id = isset($_POST['member_id']) ? intval($_POST['member_id']) : 0;
    if (!$member_id) {
        wp_send_json_error('ID membre invalide.');
    }

    global $wpdb;
    $history_table = $wpdb->prefix . 'mj_payment_history';
    $payments_table = $wpdb->prefix . 'mj_payments';

    $history = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT h.id AS history_id, h.payment_date, h.amount, h.method, h.reference, h.payer_id, p.id AS payment_id
             FROM $history_table h
             LEFT JOIN $payments_table p ON (p.external_ref = h.reference OR CAST(p.id AS CHAR) = h.reference) AND p.member_id = h.member_id
             WHERE h.member_id = %d
             ORDER BY h.payment_date DESC",
            $member_id
        )
    );

    $entries = array();

    if ($history) {
        foreach ($history as $row) {
            $entries[] = array(
                'date' => $row->payment_date ? date_i18n('d/m/Y H:i', strtotime($row->payment_date)) : __('Inconnue', 'mj-member'),
                'amount' => number_format((float)$row->amount, 2),
                'reference' => $row->reference ? sanitize_text_field($row->reference) : __('N/A', 'mj-member'),
                'method' => $row->method ? sanitize_text_field($row->method) : __('Inconnue', 'mj-member'),
                'status' => $row->payment_id ? sanitize_text_field($row->method) : '',
                'status_label' => $row->payment_id ? mj_format_payment_status($row->method) : __('ℹ️ Entrée historique (hors suivi Stripe)', 'mj-member'),
                'history_id' => isset($row->history_id) ? intval($row->history_id) : 0,
                'payment_id' => $row->payment_id ? intval($row->payment_id) : null,
                'payer_id' => isset($row->payer_id) ? (int) $row->payer_id : 0,
            );
        }
    } else {
        $payments = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id AS payment_id, COALESCE(paid_at, created_at) AS date_ref, amount, external_ref, status, payer_id
                 FROM $payments_table
                 WHERE member_id = %d
                 ORDER BY created_at DESC",
                $member_id
            )
        );

        if ($payments) {
            foreach ($payments as $row) {
                $entries[] = array(
                    'date' => $row->date_ref ? date_i18n('d/m/Y H:i', strtotime($row->date_ref)) : __('Inconnue', 'mj-member'),
                    'amount' => number_format((float)$row->amount, 2),
                    'reference' => $row->external_ref ? sanitize_text_field($row->external_ref) : __('N/A', 'mj-member'),
                    'status' => sanitize_text_field($row->status),
                    'status_label' => mj_format_payment_status($row->status),
                    'method' => __('Enregistré', 'mj-member'),
                    'history_id' => null,
                    'payment_id' => isset($row->payment_id) ? intval($row->payment_id) : 0,
                    'payer_id' => isset($row->payer_id) ? (int) $row->payer_id : 0,
                );
            }
        }
    }

    wp_send_json_success([
        'payments' => $entries,
        'can_delete' => current_user_can(MJ_MEMBER_CAPABILITY)
    ]);
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

function mj_admin_delete_payment() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mj_admin_payments_nonce')) {
        wp_send_json_error('Nonce invalide.');
    }
    if (!current_user_can(MJ_MEMBER_CAPABILITY)) {
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

    if (!current_user_can(MJ_MEMBER_CAPABILITY)) {
        wp_send_json_error(array('message' => __('Permissions insuffisantes.', 'mj-member')), 403);
    }

    $member_id = isset($_POST['member_id']) ? intval($_POST['member_id']) : 0;
    if ($member_id <= 0) {
        wp_send_json_error(array('message' => __('ID membre invalide.', 'mj-member')));
    }

    $member = MjMembers_CRUD::getById($member_id);
    if (!$member) {
        wp_send_json_error(array('message' => __('Membre introuvable.', 'mj-member')));
    }

    $admin_user_id = get_current_user_id();

    $now = current_time('mysql');

    $update_payload = array(
        'date_last_payement' => $now,
        'status' => MjMembers_CRUD::STATUS_ACTIVE,
    );

    $updated = MjMembers_CRUD::update($member_id, $update_payload);
    if ($updated === false) {
        wp_send_json_error(array('message' => __('Impossible de mettre à jour la fiche membre.', 'mj-member')));
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

    $updated_member = MjMembers_CRUD::getById($member_id);
    $date_display = ($updated_member && !empty($updated_member->date_last_payement)) ? wp_date('d/m/Y', strtotime($updated_member->date_last_payement)) : '';
    $status_label = ($updated_member && $updated_member->status === MjMembers_CRUD::STATUS_ACTIVE) ? __('Actif', 'mj-member') : __('Inactif', 'mj-member');

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
