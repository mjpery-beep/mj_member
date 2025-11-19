<?php
class MjPayments extends MjTools {

    /**
     * Créer une session de paiement Stripe avec QR code
     * SÉCURITÉ: Ne retourne QUE les données sûres pour le frontend
     */
    public static function create_stripe_payment($member_id, $amount = null) {
        global $wpdb;
        
        // Charger le membre
        $member = MjMembers_CRUD::getById($member_id);
        if (!$member) {
            return false;
        }
        
        // Determine amount from configuration if not provided
        if (is_null($amount)) {
            $amount = (float)get_option('mj_annual_fee', '2.00');
        } else {
            $amount = (float)$amount;
        }
        $table = $wpdb->prefix . 'mj_payments';
        $token = wp_generate_password(24, false, false);
        $now = current_time('mysql');
        
        // Créer une session Stripe Checkout (la clé secrète est gérée en interne)
        $checkout_session = self::create_checkout_session($member, $amount);
        if (!$checkout_session) {
            return false;
        }
        
        // Enregistrer le paiement en base de données
        $wpdb->insert($table, array(
            'member_id' => intval($member_id),
            'amount' => number_format((float)$amount, 2, '.', ''),
            'status' => 'pending',
            'token' => $token,
            'external_ref' => $checkout_session['id'], // Session ID de Stripe
            'created_at' => $now
        ), array('%d', '%f', '%s', '%s', '%s', '%s'));
        
        $payment_id = $wpdb->insert_id;
        if (!$payment_id) {
            return false;
        }
        
        // Générer le QR code avec l'URL de la session Stripe
        $qr_url = self::generate_qr_code($checkout_session['url']);
        
        // SÉCURITÉ: Retourner SEULEMENT les champs sûrs pour le frontend
        return array(
            'payment_id' => $payment_id,
            'token' => $token,
            'stripe_session_id' => $checkout_session['id'],
            'checkout_url' => $checkout_session['url'],
            'qr_url' => $qr_url,
            'amount' => number_format((float)$amount, 2)
            // NOTE: Ne jamais inclure mj_stripe_secret_key ou toute information sensible
        );
    }
    

    /**
     * Créer une session Stripe Checkout
     */
    private static function create_checkout_session($member, $amount = null) {
        if (is_null($amount)) {
            $amount = (float)get_option('mj_annual_fee', '2.00');
        } else {
            $amount = (float)$amount;
        }
        $secret_key = self::get_secret_key_safely();
        if (empty($secret_key)) {
            error_log('MjPayments: Clé secrète Stripe manquante');
            return false;
        }
        
        $member_name = self::format_member_name($member);
        $guardian = self::get_guardian_member($member);

        $member_email = (!empty($member->email) && is_email($member->email)) ? $member->email : null;
        $guardian_email = ($guardian && !empty($guardian->email) && is_email($guardian->email)) ? $guardian->email : null;
        $primary_email = $member_email ?: $guardian_email;

        // Construire les données pour la requête Stripe
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
        $success_url = add_query_arg(
            array(
                'stripe_success' => '1',
                'session_id' => '{CHECKOUT_SESSION_ID}',
            ),
            $success_base_clean
        );
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
        $cancel_url = add_query_arg(
            array(
                'stripe_cancel' => '1',
                'session_id' => '{CHECKOUT_SESSION_ID}',
            ),
            $cancel_base_clean
        );
        $cancel_url = str_replace(array('%7B', '%7D'), array('{', '}'), $cancel_url);

        $data = array(
            'payment_method_types[]' => 'card',
            'line_items[0][price_data][currency]' => 'eur',
            'line_items[0][price_data][unit_amount]' => intval($amount * 100), // Convertir en centimes
            'line_items[0][price_data][product_data][name]' => 'Cotisation annuelle MJ Péry',
            'line_items[0][price_data][product_data][description]' => 'Membership - ' . sanitize_text_field($member_name),
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
        
        // Faire l'appel API à Stripe
        $response = self::stripe_api_call('POST', 'https://api.stripe.com/v1/checkout/sessions', $data, $secret_key);
        
        if (!$response || isset($response['error'])) {
            error_log('MjPayments: Erreur API Stripe - ' . json_encode($response));
            self::log_stripe_event('checkout.session.create.error', array(
                'member_id' => (int) $member->id,
                'amount' => number_format((float) $amount, 2, '.', ''),
                'error' => isset($response['error']) ? $response['error'] : 'unknown_error'
            ));
            return false;
        }
        
        self::log_stripe_event('checkout.session.created', array(
            'member_id' => (int) $member->id,
            'amount' => number_format((float) $amount, 2, '.', ''),
            'session_id' => isset($response['id']) ? $response['id'] : '',
            'success_redirect' => $success_base_clean,
            'cancel_redirect' => $cancel_base_clean
        ));
        
        return array(
            'id' => $response['id'],
            'url' => $response['url']
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
     * Générer un QR code via l'API Google ou Stripe
     */
    private static function generate_qr_code($url) {
        // Utiliser l'API Google Chart pour générer un QR code
        // Stripe fournit aussi des QR codes mais nous utilisons Google pour la simplicité
        $qr_text = rawurlencode($url);
        return "https://chart.googleapis.com/chart?cht=qr&chs=300x300&chl={$qr_text}&chld=L|1";
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

        $member = MjMembers_CRUD::getById($member_id);
        if (!$member) return false;

        $confirm_url = add_query_arg(array('mj_payment_confirm' => $token), site_url('/'));
        $qr_text = rawurlencode($confirm_url);
        /**
         * @TODO utiliser l'api de Stripe pour générer le QR code
         */
        $qr_url = "https://chart.googleapis.com/chart?cht=qr&chs=300x300&chl={$qr_text}&chld=L|1";

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
        $member = MjMembers_CRUD::getById($member_id);
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
            $qr_text = rawurlencode($confirm_url);
            $qr_url = "https://chart.googleapis.com/chart?cht=qr&chs=300x300&chl={$qr_text}&chld=L|1";
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

        $message = MjMail::getContainer($html);

        // Envoyer à la fois au jeune et au tuteur s'ils sont disponibles
        $to = self::collect_contact_emails($member);

        $headers = array('Content-Type: text/html; charset=UTF-8');

        if (empty($to)) {
            return false;
        }

        $all_ok = true;
        foreach ($to as $recipient) {
            if (!wp_mail($recipient, $subject, $message, $headers)) {
                $all_ok = false;
            }
        }

        return $all_ok ? $payment_id : false;
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

        if (class_exists('MjMembers_CRUD') && class_exists('MjMail')) {
            $member = MjMembers_CRUD::getById((int) $payment->member_id);
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
            $cache[$guardian_id] = MjMembers_CRUD::getById($guardian_id);
        }

        return $cache[$guardian_id] ?: null;
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
        if (!function_exists('wp_upload_dir')) {
            return;
        }

        if (!is_array($context)) {
            $context = array('message' => (string) $context);
        }

        $context = array_filter($context, function ($value) {
            return $value !== null;
        });

        if (class_exists('MjStripeConfig')) {
            $context['mode'] = MjStripeConfig::is_test_mode() ? 'test' : 'live';
        }

        $uploads = wp_upload_dir();
        if (!empty($uploads['error'])) {
            error_log('MjPayments: upload dir indisponible pour la journalisation - ' . $uploads['error']);
            return;
        }

        $log_dir = trailingslashit($uploads['basedir']) . 'mj-member';
        if (!wp_mkdir_p($log_dir)) {
            error_log('MjPayments: impossible de creer le repertoire de log ' . $log_dir);
            return;
        }

        $log_file = trailingslashit($log_dir) . 'stripe-events.log';
        $entry = array(
            'timestamp' => current_time('mysql'),
            'event' => $event,
            'data' => $context,
        );

        $json = wp_json_encode($entry, JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            $json = json_encode($entry);
        }
        if ($json === false) {
            $json = '{"timestamp":"' . current_time('mysql') . '","event":"' . $event . '"}';
        }

        file_put_contents($log_file, $json . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}

MjPayments::bootstrap();

