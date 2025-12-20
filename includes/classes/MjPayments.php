<?php

namespace Mj\Member\Classes;

use Mj\Member\Core\Logger;
use Mj\Member\Classes\Crud\MjMembers;
use Mj\Member\Classes\Crud\MjEvents;
use Mj\Member\Classes\Crud\MjEventAttendance;
use Mj\Member\Classes\Crud\MjEventRegistrations;

if (!defined('ABSPATH')) {
    exit;
}

class MjPayments extends MjTools {

    /**
     * Créer une session de paiement Stripe avec QR code
     * SÉCURITÉ: Ne retourne QUE les données sûres pour le frontend
     */
    public static function create_stripe_payment($member_id, $amount = null, array $options = array()) {
        global $wpdb;

        $member = MjMembers::getById($member_id);
        if (!$member) {
            return false;
        }

        if (is_null($amount)) {
            $amount = (float) get_option('mj_annual_fee', '2.00');
        } else {
            $amount = (float) $amount;
        }

        $context_type = isset($options['context']) ? sanitize_key($options['context']) : 'membership';
        if ($context_type === '') {
            $context_type = 'membership';
        }

        $event_id = isset($options['event_id']) ? (int) $options['event_id'] : 0;
        $registration_id = isset($options['registration_id']) ? (int) $options['registration_id'] : 0;
        $payer_id = isset($options['payer_id']) ? (int) $options['payer_id'] : 0;

        $occurrence_mode = isset($options['occurrence_mode']) ? sanitize_key($options['occurrence_mode']) : '';
        $occurrence_count = isset($options['occurrence_count']) ? (int) $options['occurrence_count'] : 1;
        if ($occurrence_count <= 0) {
            $occurrence_count = 1;
        }

        $occurrence_list = array();
        if (!empty($options['occurrence_list']) && is_array($options['occurrence_list'])) {
            foreach ($options['occurrence_list'] as $occurrence_entry) {
                $normalized = '';
                if (class_exists(MjEventAttendance::class)) {
                    $normalized = MjEventAttendance::normalize_occurrence($occurrence_entry);
                }
                if ($normalized === '' && !is_scalar($occurrence_entry)) {
                    continue;
                }
                if ($normalized === '') {
                    $normalized = sanitize_text_field((string) $occurrence_entry);
                }
                if ($normalized === '') {
                    continue;
                }
                $occurrence_list[$normalized] = $normalized;
            }
            if (!empty($occurrence_list)) {
                $occurrence_list = array_values($occurrence_list);
            }
        }

        if ($occurrence_mode === '') {
            $occurrence_mode = (!empty($occurrence_list) || $occurrence_count > 1) ? 'custom' : 'all';
        }

        $event = null;
        if ($context_type === 'event') {
            if (!empty($options['event']) && is_object($options['event'])) {
                $event = $options['event'];
            } elseif ($event_id > 0 && class_exists(MjEvents::class)) {
                $event = MjEvents::find($event_id);
            }
        }

        $member_name = self::format_member_name($member);
        $product_name = 'Cotisation annuelle MJ Péry';
        $product_description = 'Membership - ' . sanitize_text_field($member_name);

        if ($context_type === 'event' && $event) {
            $event_title = isset($event->title) ? sanitize_text_field($event->title) : 'Evenement';
            $product_name = 'Evenement MJ - ' . $event_title;
            $product_description = 'Inscription evenement #' . ($event_id > 0 ? $event_id : (isset($event->id) ? (int) $event->id : 0));
        }

        $metadata = array(
            'payment_context' => $context_type,
        );

        if ($context_type === 'event') {
            if ($event_id > 0) {
                $metadata['event_id'] = $event_id;
            } elseif ($event && !empty($event->id)) {
                $metadata['event_id'] = (int) $event->id;
            }
            if ($registration_id > 0) {
                $metadata['registration_id'] = $registration_id;
            }
        }

        $metadata['occurrence_mode'] = $occurrence_mode;
        $metadata['occurrence_count'] = $occurrence_count;
        if (!empty($occurrence_list)) {
            $metadata['occurrence_list'] = implode(',', $occurrence_list);
        }

        $total_amount = round($amount * $occurrence_count, 2);
        if ($total_amount <= 0) {
            $total_amount = round($amount, 2);
        }
        if ($total_amount <= 0) {
            $total_amount = 0.01;
        }

        $table = $wpdb->prefix . 'mj_payments';
        $token = wp_generate_password(24, false, false);
        $now = current_time('mysql');

        $checkout_session = self::create_checkout_session(
            $member,
            $total_amount,
            array(
                'product_name' => $product_name,
                'product_description' => $product_description,
                'metadata' => $metadata,
                'success_query' => ($context_type === 'event' && ($event_id > 0 || $registration_id > 0)) ? array(
                    'mj_event_id' => max(0, $event_id),
                    'mj_registration_id' => max(0, $registration_id),
                ) : array(),
                'cancel_query' => ($context_type === 'event' && ($event_id > 0 || $registration_id > 0)) ? array(
                    'mj_event_id' => max(0, $event_id),
                    'mj_registration_id' => max(0, $registration_id),
                ) : array(),
            )
        );

        if (!$checkout_session) {
            return false;
        }

        $wpdb->insert(
            $table,
            array(
                'member_id' => (int) $member_id,
                'payer_id' => $payer_id > 0 ? $payer_id : 0,
                'event_id' => $event_id > 0 ? $event_id : 0,
                'registration_id' => $registration_id > 0 ? $registration_id : 0,
                'amount' => number_format((float) $total_amount, 2, '.', ''),
                'status' => 'pending',
                'token' => $token,
                'external_ref' => $checkout_session['id'],
                'checkout_url' => $checkout_session['url'],
                'context' => $context_type,
                'created_at' => $now,
            ),
            array('%d', '%d', '%d', '%d', '%f', '%s', '%s', '%s', '%s', '%s')
        );

        $payment_id = (int) $wpdb->insert_id;
        if (!$payment_id) {
            return false;
        }

        $qr_url = self::generate_qr_code($checkout_session['url']);

        return array(
            'payment_id' => $payment_id,
            'token' => $token,
            'stripe_session_id' => $checkout_session['id'],
            'checkout_url' => $checkout_session['url'],
            'qr_url' => $qr_url,
            'amount' => number_format((float) $total_amount, 2),
            'amount_label' => number_format_i18n((float) $total_amount, 2),
            'amount_raw' => (float) $total_amount,
            'occurrence_mode' => $occurrence_mode,
            'occurrence_count' => $occurrence_count,
            'occurrence_list' => $occurrence_list,
        );
    }

    /**
     * Créer une session Stripe Checkout
     */
    private static function create_checkout_session($member, $amount = null, array $options = array()) {
        if (is_null($amount)) {
            $amount = (float) get_option('mj_annual_fee', '2.00');
        } else {
            $amount = (float) $amount;
        }
        $secret_key = self::get_secret_key_safely();
        if (empty($secret_key)) {
            Logger::error('Stripe secret key missing', array(
                'member_id' => isset($member->id) ? (int) $member->id : 0,
            ), 'payments');
            return false;
        }

        $member_name = self::format_member_name($member);
        $guardian = self::get_guardian_member($member);

        $member_email = (!empty($member->email) && is_email($member->email)) ? $member->email : null;
        $guardian_email = ($guardian && !empty($guardian->email) && is_email($guardian->email)) ? $guardian->email : null;
        $primary_email = $member_email ?: $guardian_email;

        $product_name = isset($options['product_name']) ? sanitize_text_field($options['product_name']) : 'Cotisation annuelle MJ Péry';
        $product_description = isset($options['product_description']) ? sanitize_text_field($options['product_description']) : 'Membership - ' . sanitize_text_field($member_name);

        $extra_metadata = (!empty($options['metadata']) && is_array($options['metadata'])) ? $options['metadata'] : array();
        $context_logged = isset($extra_metadata['payment_context']) ? $extra_metadata['payment_context'] : 'membership';

        $success_page_id = (int) get_option('mj_stripe_success_page', 0);
        $success_base = '';
        if ($success_page_id > 0) {
            $page_link = get_permalink($success_page_id);
            if (!empty($page_link)) {
                $success_base = $page_link;
            }
        }
        if ($success_base === '') {
            $legacy_success = trim((string) get_option('mj_stripe_success_redirect', ''));
            $success_base = $legacy_success !== '' ? $legacy_success : home_url('/inscit');
        }
        $success_base = apply_filters('mj_member_stripe_success_redirect_base', $success_base, $member, $amount);
        $success_base_clean = esc_url_raw($success_base);

        $success_params = array(
            'stripe_success' => '1',
            'session_id' => '{CHECKOUT_SESSION_ID}',
        );
        if (!empty($options['success_query']) && is_array($options['success_query'])) {
            foreach ($options['success_query'] as $key => $value) {
                if ($value === null || $value === '') {
                    continue;
                }
                $success_params[sanitize_key($key)] = $value;
            }
        }
        $success_url = add_query_arg($success_params, $success_base_clean);
        $success_url = str_replace(array('%7B', '%7D'), array('{', '}'), $success_url);

        $cancel_page_id = (int) get_option('mj_stripe_cancel_page', 0);
        $cancel_base = '';
        if ($cancel_page_id > 0) {
            $cancel_link = get_permalink($cancel_page_id);
            if (!empty($cancel_link)) {
                $cancel_base = $cancel_link;
            }
        }
        if ($cancel_base === '') {
            $legacy_cancel = trim((string) get_option('mj_stripe_cancel_redirect', ''));
            $cancel_base = $legacy_cancel !== '' ? $legacy_cancel : home_url('/');
        }
        $cancel_base = apply_filters('mj_member_stripe_cancel_redirect_base', $cancel_base, $member, $amount);
        $cancel_base_clean = esc_url_raw($cancel_base);

        $cancel_params = array(
            'stripe_cancel' => '1',
            'session_id' => '{CHECKOUT_SESSION_ID}',
        );
        if (!empty($options['cancel_query']) && is_array($options['cancel_query'])) {
            foreach ($options['cancel_query'] as $key => $value) {
                if ($value === null || $value === '') {
                    continue;
                }
                $cancel_params[sanitize_key($key)] = $value;
            }
        }
        $cancel_url = add_query_arg($cancel_params, $cancel_base_clean);
        $cancel_url = str_replace(array('%7B', '%7D'), array('{', '}'), $cancel_url);

        $data = array(
            'payment_method_types[]' => 'card',
            'line_items[0][price_data][currency]' => 'eur',
            'line_items[0][price_data][unit_amount]' => intval($amount * 100),
            'line_items[0][price_data][product_data][name]' => $product_name,
            'line_items[0][price_data][product_data][description]' => $product_description,
            'line_items[0][quantity]' => '1',
            'mode' => 'payment',
            'success_url' => $success_url,
            'cancel_url' => $cancel_url,
            'metadata[member_id]' => (int) $member->id,
            'metadata[member_name]' => $member_name,
            'metadata[member_role]' => $member->role,
        );

        if ($primary_email) {
            $data['customer_email'] = $primary_email;
        }

        if ($guardian && $guardian_email) {
            $data['metadata[guardian_id]'] = (int) $guardian->id;
            $data['metadata[guardian_email]'] = $guardian_email;
        }

        if (!empty($extra_metadata)) {
            foreach ($extra_metadata as $meta_key => $meta_value) {
                $meta_key = sanitize_key($meta_key);
                if ($meta_key === '' || isset($data['metadata[' . $meta_key . ']'])) {
                    continue;
                }
                if (is_scalar($meta_value)) {
                    $data['metadata[' . $meta_key . ']'] = (string) $meta_value;
                } else {
                    $data['metadata[' . $meta_key . ']'] = wp_json_encode($meta_value);
                }
            }
        }

        $response = self::stripe_api_call('POST', 'https://api.stripe.com/v1/checkout/sessions', $data, $secret_key);

        if (!$response || isset($response['error'])) {
            Logger::error('Stripe API error during checkout session creation', array(
                'member_id' => (int) $member->id,
                'amount' => number_format((float) $amount, 2, '.', ''),
                'response' => $response,
                'context' => $context_logged,
            ), 'payments');
            self::log_stripe_event('checkout.session.create.error', array(
                'member_id' => (int) $member->id,
                'amount' => number_format((float) $amount, 2, '.', ''),
                'error' => isset($response['error']) ? $response['error'] : 'unknown_error',
                'context' => $context_logged,
            ));
            return false;
        }

        self::log_stripe_event('checkout.session.created', array(
            'member_id' => (int) $member->id,
            'amount' => number_format((float) $amount, 2, '.', ''),
            'session_id' => isset($response['id']) ? $response['id'] : '',
            'success_redirect' => $success_base_clean,
            'cancel_redirect' => $cancel_base_clean,
            'context' => $context_logged,
        ));

        return array(
            'id' => $response['id'],
            'url' => $response['url'],
        );
    }
    
    /**
     * Récupérer la clé secrète de manière sécurisée
     */
    private static function get_secret_key_safely() {
        // Ne JAMAIS retourner la clé secrète au JavaScript/frontend
        // Ne l'utiliser que côté serveur PHP
        if (!class_exists('MjStripeConfig')) return false;
        // MjStripeConfig gère l'initialisation et le déchiffrage en toute sécurité
        return MjStripeConfig::get_secret_key();
    }
    
    /**
     * Faire un appel à l'API Stripe via cURL
     */
    private static function stripe_api_call($method, $url, $data, $secret_key) {
        $ch = curl_init();
        
        curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_USERPWD => $secret_key . ':',
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/x-www-form-urlencoded'
            ),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_TIMEOUT => 30
        ));
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_errno = curl_errno($ch);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $curl_errno) {
            self::log_stripe_event('stripe.api.error', array(
                'stage' => 'curl_exec',
                'curl_errno' => $curl_errno,
                'curl_error' => $curl_error
            ));
            return false;
        }

        $decoded = json_decode($response, true);

        if ($http_code !== 200 && $http_code !== 201) {
            $message = '';
            if (is_array($decoded) && isset($decoded['error']['message'])) {
                $message = $decoded['error']['message'];
            } elseif (is_string($response)) {
                $message = substr($response, 0, 400);
            }
            self::log_stripe_event('stripe.api.error', array(
                'stage' => 'http_response',
                'http_code' => $http_code,
                'message' => $message
            ));
            return false;
        }

        if (!is_array($decoded)) {
            self::log_stripe_event('stripe.api.error', array(
                'stage' => 'json_decode',
                'http_code' => $http_code
            ));
            return false;
        }

        return $decoded;
    }
    
    /**
     * Générer un QR code via un service public.
     */
    private static function generate_qr_code($url) {
        $qr_text = rawurlencode($url);
        return "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data={$qr_text}&format=png&ecc=M&margin=0";
    }

    /**
     * Annuler tous les paiements en attente pour une inscription donnée.
     * Utilisé lors de la modification d'inscription quand le prix change.
     * 
     * @param int $registration_id L'ID de l'inscription
     * @return int Nombre de paiements annulés
     */
    public static function cancel_pending_payments_for_registration($registration_id) {
        global $wpdb;
        
        $registration_id = (int) $registration_id;
        if ($registration_id <= 0) {
            return 0;
        }
        
        $table = $wpdb->prefix . 'mj_payments';
        
        // Mettre à jour les paiements en 'pending' vers 'cancelled'
        $updated = $wpdb->update(
            $table,
            array('status' => 'cancelled'),
            array(
                'registration_id' => $registration_id,
                'status' => 'pending',
            ),
            array('%s'),
            array('%d', '%s')
        );
        
        return $updated !== false ? $updated : 0;
    }

    /**
     * Récupérer le paiement en attente pour une inscription donnée.
     * 
     * @param int $registration_id L'ID de l'inscription
     * @return object|null Le paiement en attente ou null
     */
    public static function get_pending_payment_for_registration($registration_id) {
        global $wpdb;
        
        $registration_id = (int) $registration_id;
        if ($registration_id <= 0) {
            return null;
        }
        
        $table = $wpdb->prefix . 'mj_payments';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE registration_id = %d AND status = 'pending' ORDER BY id DESC LIMIT 1",
            $registration_id
        ));
    }
    
    /**
     * Créer un paiement avec enregistrement local (ancienne méthode - QR code simple)
     */
    public static function create_payment_record($member_id, $amount = null) {
        if (is_null($amount)) {
            $amount = (float)get_option('mj_annual_fee', '2.00');
        } else {
            $amount = (float)$amount;
        }
        // Si Stripe est configuré, l'utiliser via la config sécurisée
        if (class_exists('MjStripeConfig') && MjStripeConfig::is_configured()) {
            return self::create_stripe_payment($member_id, $amount);
        }
        
        // Sinon, fallback à la méthode simple
        global $wpdb;
        $table = $wpdb->prefix . 'mj_payments';

        $token = wp_generate_password(24, false, false);
        $now = current_time('mysql');

        $wpdb->insert($table, array(
            'member_id' => intval($member_id),
            'amount' => number_format((float)$amount, 2, '.', ''),
            'status' => 'pending',
            'token' => $token,
            'created_at' => $now
        ), array('%d','%f','%s','%s','%s'));

        $payment_id = $wpdb->insert_id;
        if (!$payment_id) return false;

        $member = MjMembers::getById($member_id);
        if (!$member) return false;

        $confirm_url = add_query_arg(array('mj_payment_confirm' => $token), site_url('/'));
        $qr_url = self::generate_qr_code($confirm_url);

        return array(
            'payment_id' => $payment_id,
            'token' => $token,
            'confirm_url' => $confirm_url,
            'qr_url' => $qr_url,
            'amount' => number_format((float)$amount,2)
        );
    }

    /**
     * Créer et envoyer une demande de paiement avec QR code (ancienne méthode)
     */
    public static function create_and_send_payment_request($member_id, $amount = null) {
        if (is_null($amount)) {
            $amount = (float)get_option('mj_annual_fee', '2.00');
        } else {
            $amount = (float)$amount;
        }
        global $wpdb;
        $table = $wpdb->prefix . 'mj_payments';

        $token = wp_generate_password(24, false, false);
        $now = current_time('mysql');

        $wpdb->insert($table, array(
            'member_id' => intval($member_id),
            'amount' => number_format((float)$amount, 2, '.', ''),
            'status' => 'pending',
            'token' => $token,
            'created_at' => $now
        ), array('%d','%f','%s','%s','%s'));

        $payment_id = $wpdb->insert_id;
        if (!$payment_id) return false;

        // Load member
        $member = MjMembers::getById($member_id);
        if (!$member) return false;

        // Si Stripe est configuré, générer une session Stripe via la config sécurisée
        if (class_exists('MjStripeConfig') && MjStripeConfig::is_configured()) {
            $payment_info = self::create_stripe_payment($member_id, $amount);
            if (!$payment_info) return false;
            
            $checkout_url = $payment_info['checkout_url'];
            $qr_url = $payment_info['qr_url'];
        } else {
            // Sinon, utiliser la méthode simple
            $confirm_url = add_query_arg(array('mj_payment_confirm' => $token), site_url('/'));
            $qr_url = self::generate_qr_code($confirm_url);
            $checkout_url = $confirm_url;
        }

        // Préparer le contenu de l'email
        $member_name = self::format_member_name($member);
        $subject = 'Demande de paiement - MJ Péry';
        $html = '<p>Bonjour ' . esc_html($member_name) . ',</p>';
        $html .= '<p>Merci de régler la cotisation annuelle de <strong>' . number_format((float)$amount,2) . ' €</strong>.</p>';
        $html .= '<p>Scannez le QR-code ci-dessous pour confirmer le paiement :</p>';
        $html .= '<p><a href="' . esc_url($checkout_url) . '"><img src="' . esc_url($qr_url) . '" alt="QR code paiement" style="max-width:300px;border:0"></a></p>';
        $html .= '<p>Si vous préférez, vous pouvez aussi confirmer le paiement en cliquant sur ce lien : <a href="' . esc_url($checkout_url) . '">' . esc_url($checkout_url) . '</a></p>';
        $html .= '<p>Merci,<br/>MJ Péry</p>';

        // Envoyer à la fois au jeune et au tuteur s'ils sont disponibles
        $recipients = self::collect_contact_emails($member);
        if (empty($recipients)) {
            return false;
        }

        $mail_context = array(
            'recipients' => $recipients,
            'include_guardian' => true,
            'payment_link' => $checkout_url,
            'payment_qr_url' => $qr_url,
            'payment_amount' => $amount,
        );

        $qr_block = '';
        if (!empty($qr_url)) {
            $qr_block = '<p><img src="' . esc_url($qr_url) . '" alt="QR code de paiement" style="max-width:260px;border:0" /></p>';
        }

        $sent = MjMail::send_notification_to_emails('payment_request', $recipients, array(
            'member' => $member,
            'context' => $mail_context,
            'placeholders' => array(
                '{{checkout_url}}' => $checkout_url,
                '{{payment_checkout_url}}' => $checkout_url,
                '{{payment_qr_url}}' => $qr_url,
                '{{payment_qr_block}}' => $qr_block,
            ),
            'fallback_subject' => $subject,
            'fallback_body' => $html,
            'content_type' => 'text/html',
            'wrap_html' => true,
            'log_source' => 'payment_request',
        ));

        return $sent ? $payment_id : false;
    }

    /**
     * Confirmer un paiement via token (ancienne méthode)
     */
    public static function confirm_payment_by_token($token) {
        global $wpdb;
        $table = $wpdb->prefix . 'mj_payments';
        $hist_table = $wpdb->prefix . 'mj_payment_history';

        $payment = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE token = %s", $token));
        if (!$payment) return false;
        if ($payment->status === 'paid') return true; // already paid

        $now = current_time('mysql');
        $updated = $wpdb->update($table, array('status' => 'paid', 'paid_at' => $now), array('id' => $payment->id), array('%s','%s'), array('%d'));
        if ($updated === false) return false;

        // Insert in history
        $wpdb->insert($hist_table, array(
            'member_id' => intval($payment->member_id),
            'amount' => $payment->amount,
            'payment_date' => $now,
            'method' => 'qr_confirm',
            'reference' => $payment->external_ref
        ), array('%d','%f','%s','%s','%s'));

        // Update member last payment and status
        $members_table = $wpdb->prefix . 'mj_members';
        $wpdb->update($members_table, array('date_last_payement' => $now, 'status' => 'active'), array('id' => intval($payment->member_id)), array('%s','%s'), array('%d'));

        if (class_exists(MjMembers::class) && class_exists('MjMail')) {
            $member = MjMembers::getById((int) $payment->member_id);
            if ($member) {
                $context = array(
                    'payment_amount' => $payment->amount,
                    'payment_date' => $now,
                    'recipients' => self::collect_contact_emails($member),
                );
                MjMail::send_payment_confirmation($member, $context);
            }
        }

        return true;
    }

    private static function format_member_name($member) {
        $first = isset($member->first_name) ? sanitize_text_field($member->first_name) : '';
        $last  = isset($member->last_name) ? sanitize_text_field($member->last_name) : '';

        $name = trim($first . ' ' . $last);
        if ($name !== '') {
            return $name;
        }

        if (!empty($member->email) && is_email($member->email)) {
            return $member->email;
        }

        return 'Membre';
    }

    private static function get_guardian_member($member) {
        if (empty($member->guardian_id)) {
            return null;
        }

        static $cache = array();
        $guardian_id = (int) $member->guardian_id;
        if ($guardian_id <= 0) {
            return null;
        }

        if (!array_key_exists($guardian_id, $cache)) {
            $cache[$guardian_id] = MjMembers::getById($guardian_id);
        }

        return $cache[$guardian_id] ?: null;
    }

    private static function normalize_datetime_value($value) {
        if ($value instanceof DateTimeInterface) {
            return $value->getTimestamp();
        }

        $raw = (string) $value;
        if ($raw === '' || $raw === '0000-00-00 00:00:00') {
            return null;
        }

        $timestamp = strtotime($raw);
        return $timestamp ?: null;
    }

    private static function format_context_label($context) {
        switch ($context) {
            case 'event':
            case 'event_registration':
                return __('Inscription événement', 'mj-member');
            case 'membership':
            case 'membership_renewal':
                return __('Cotisation MJ Péry', 'mj-member');
            case 'donation':
                return __('Don MJ Péry', 'mj-member');
            default:
                $context = str_replace('_', ' ', (string) $context);
                return ucfirst($context);
        }
    }

    private static function format_status_label($status) {
        switch ($status) {
            case 'paid':
            case 'succeeded':
            case 'completed':
                return __('Paiement confirmé', 'mj-member');
            case 'pending':
            case 'requires_payment_method':
            case 'requires_action':
                return __('Paiement en cours de confirmation', 'mj-member');
            case 'canceled':
            case 'cancelled':
                return __('Paiement annulé', 'mj-member');
            case 'failed':
            case 'requires_payment_method_failed':
                return __('Paiement échoué', 'mj-member');
            default:
                return __('Statut en attente de confirmation', 'mj-member');
        }
    }

    private static function get_currency_symbol($payment_row) {
        $default_symbol = '€';
        return apply_filters('mj_member_payment_currency_symbol', $default_symbol, $payment_row);
    }

    /**
     * Retourne un résumé prêt pour l'affichage côté frontend après un retour Stripe.
     *
     * @param string $session_id
     * @param array<string,string> $args
     * @return array<string,mixed>|null
     */
    public static function get_payment_summary_by_session($session_id, $args = array()) {
        if (!is_string($session_id)) {
            return null;
        }

        $session_id = sanitize_text_field($session_id);
        if ($session_id === '') {
            return null;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'mj_payments';

        $payment = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE external_ref = %s", $session_id));
        if (!$payment) {
            return null;
        }

        $amount = isset($payment->amount) ? (float) $payment->amount : 0.0;
        $status = isset($payment->status) ? sanitize_key($payment->status) : 'pending';
        $context = isset($payment->context) ? sanitize_key($payment->context) : 'membership';

        $date_format = isset($args['date_format']) && $args['date_format'] !== '' ? $args['date_format'] : get_option('date_format', 'd/m/Y');
        $time_format = isset($args['time_format']) && $args['time_format'] !== '' ? $args['time_format'] : get_option('time_format', 'H:i');

        $created_ts = self::normalize_datetime_value(isset($payment->created_at) ? $payment->created_at : null);
        $paid_ts = self::normalize_datetime_value(isset($payment->paid_at) ? $payment->paid_at : null);
        $reference_ts = $paid_ts ?: $created_ts;

        $date_display = $reference_ts ? wp_date($date_format, $reference_ts) : '';
        $time_display = $reference_ts ? wp_date($time_format, $reference_ts) : '';
        if ($date_display !== '' && $time_display !== '') {
            $datetime_display = sprintf(__('Le %1$s à %2$s', 'mj-member'), $date_display, $time_display);
        } else {
            $datetime_display = $date_display !== '' ? $date_display : $time_display;
        }

        $member_data = array(
            'id' => isset($payment->member_id) ? (int) $payment->member_id : 0,
            'name' => '',
            'role' => '',
            'email' => '',
        );

        if ($member_data['id'] > 0 && class_exists(MjMembers::class)) {
            $member_obj = MjMembers::getById($member_data['id']);
            if ($member_obj) {
                $member_data['name'] = self::format_member_name($member_obj);
                $member_data['role'] = isset($member_obj->role) ? sanitize_key($member_obj->role) : '';
                $member_email = isset($member_obj->email) ? sanitize_email($member_obj->email) : '';
                if ($member_email !== '') {
                    $member_data['email'] = $member_email;
                }
            }
        }

        $payer_data = null;
        if (!empty($payment->payer_id) && class_exists(MjMembers::class)) {
            $payer_obj = MjMembers::getById((int) $payment->payer_id);
            if ($payer_obj) {
                $payer_email = isset($payer_obj->email) ? sanitize_email($payer_obj->email) : '';
                $payer_data = array(
                    'id' => (int) $payment->payer_id,
                    'name' => self::format_member_name($payer_obj),
                    'email' => $payer_email,
                );
            }
        }

        $event_data = null;
        if (!empty($payment->event_id) && class_exists(MjEvents::class)) {
            $event_obj = MjEvents::find((int) $payment->event_id);
            if ($event_obj) {
                $event_start_ts = self::normalize_datetime_value(isset($event_obj->date_debut) ? $event_obj->date_debut : null);
                $event_end_ts = self::normalize_datetime_value(isset($event_obj->date_fin) ? $event_obj->date_fin : null);

                $event_date_range = '';
                if ($event_start_ts && $event_end_ts) {
                    $same_day = wp_date('Y-m-d', $event_start_ts) === wp_date('Y-m-d', $event_end_ts);
                    if ($same_day) {
                        $event_date_range = sprintf(__('Le %s', 'mj-member'), wp_date($date_format, $event_start_ts));
                    } else {
                        $event_date_range = sprintf(
                            __('Du %1$s au %2$s', 'mj-member'),
                            wp_date($date_format, $event_start_ts),
                            wp_date($date_format, $event_end_ts)
                        );
                    }
                } elseif ($event_start_ts) {
                    $event_date_range = sprintf(__('Le %s', 'mj-member'), wp_date($date_format, $event_start_ts));
                } elseif ($event_end_ts) {
                    $event_date_range = sprintf(__('Le %s', 'mj-member'), wp_date($date_format, $event_end_ts));
                }

                $event_data = array(
                    'id' => (int) $payment->event_id,
                    'title' => isset($event_obj->title) ? sanitize_text_field($event_obj->title) : '',
                    'start_timestamp' => $event_start_ts,
                    'end_timestamp' => $event_end_ts,
                    'date_range' => $event_date_range,
                );
            }
        }

        $registration_data = null;
        if (!empty($payment->registration_id) && class_exists(MjEventRegistrations::class)) {
            $registration_obj = MjEventRegistrations::get((int) $payment->registration_id);
            if ($registration_obj) {
                $status_key = isset($registration_obj->statut) ? sanitize_key($registration_obj->statut) : '';
                $status_label = '';
                if (method_exists(MjEventRegistrations::class, 'get_status_labels')) {
                    $labels = MjEventRegistrations::get_status_labels();
                    if (isset($labels[$status_key])) {
                        $status_label = sanitize_text_field($labels[$status_key]);
                    }
                }

                $registration_data = array(
                    'id' => (int) $payment->registration_id,
                    'status' => $status_key,
                    'status_label' => $status_label,
                );
            }
        }

        $currency_symbol = self::get_currency_symbol($payment);
        $amount_display = number_format_i18n($amount, 2);
        if ($currency_symbol !== '') {
            $amount_display .= ' ' . $currency_symbol;
        }

        $summary = array(
            'session_id' => $session_id,
            'status' => $status,
            'status_label' => self::format_status_label($status),
            'is_paid' => in_array($status, array('paid', 'succeeded', 'completed'), true),
            'amount' => $amount,
            'amount_display' => $amount_display,
            'currency_symbol' => $currency_symbol,
            'context' => $context,
            'context_label' => self::format_context_label($context),
            'created_timestamp' => $created_ts,
            'created_at' => $created_ts ? wp_date('c', $created_ts) : null,
            'paid_timestamp' => $paid_ts,
            'paid_at' => $paid_ts ? wp_date('c', $paid_ts) : null,
            'date_display' => $date_display,
            'time_display' => $time_display,
            'datetime_display' => $datetime_display,
            'member' => $member_data,
            'payer' => $payer_data,
            'event' => $event_data,
            'registration' => $registration_data,
            'event_id' => isset($payment->event_id) ? (int) $payment->event_id : 0,
            'registration_id' => isset($payment->registration_id) ? (int) $payment->registration_id : 0,
        );

        return apply_filters('mj_member_payment_success_summary', $summary, $payment, $session_id);
    }

    public static function collect_contact_emails($member) {
        $emails = array();

        if (!empty($member->email) && is_email($member->email)) {
            $emails[] = $member->email;
        }

        $guardian = self::get_guardian_member($member);
        if ($guardian && !empty($guardian->email) && is_email($guardian->email)) {
            $emails[] = $guardian->email;
        }

        return array_values(array_unique(array_filter($emails)));
    }

    /**
     * Hook WordPress pour detecter les retours Stripe (success / cancel)
     */
    public static function bootstrap() {
        add_action('init', array(__CLASS__, 'maybe_handle_stripe_redirect'), 1);
    }

    /**
     * Journaliser les redirections success / cancel en provenance de Stripe Checkout.
     */
    public static function maybe_handle_stripe_redirect() {
        if (function_exists('wp_doing_ajax') && wp_doing_ajax()) {
            return;
        }

        $has_success = isset($_GET['stripe_success']);
        $has_cancel = isset($_GET['stripe_cancel']);

        if (!$has_success && !$has_cancel) {
            return;
        }

        $session_id = '';
        if (isset($_GET['session_id'])) {
            $raw_session = wp_unslash($_GET['session_id']);
            $session_id = sanitize_text_field($raw_session);
        }

        $payload = array(
            'session_id' => $session_id !== '' ? $session_id : null,
            'user_id' => is_user_logged_in() ? get_current_user_id() : 0,
        );

        if ($has_success) {
            self::log_stripe_event('redirect.success', $payload);
        }

        if ($has_cancel) {
            self::log_stripe_event('redirect.cancel', $payload);
        }
    }

    /**
     * Ecrit un evenement Stripe dans un journal dedie cote serveur.
     */
    public static function log_stripe_event($event, $context = array()) {
        if (!is_array($context)) {
            $context = array('message' => (string) $context);
        }

        $context = array_filter($context, function ($value) {
            return $value !== null;
        });

        if (class_exists('MjStripeConfig')) {
            $context['mode'] = MjStripeConfig::is_test_mode() ? 'test' : 'live';
        }

        $context['event'] = $event;

        Logger::info('stripe.' . $event, $context, 'stripe');
    }
}

MjPayments::bootstrap();

\class_alias(__NAMESPACE__ . '\\MjPayments', 'MjPayments');

