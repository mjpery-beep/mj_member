<?php
// Admin settings page for plugin
function mj_settings_page() {
    if (function_exists('wp_enqueue_media')) {
        wp_enqueue_media();
    }
    wp_enqueue_script('jquery');

    // Handle save
    if ((isset($_POST['mj_save_settings']) || isset($_POST['mj_events_google_sync_regenerate']) || isset($_POST['mj_events_google_sync_force'])) && check_admin_referer('mj_settings_nonce')) {
        $notices = array();
        $notify_email = isset($_POST['mj_notify_email']) ? sanitize_email($_POST['mj_notify_email']) : '';
        $smtp = array(
            'host' => sanitize_text_field($_POST['mj_smtp_host'] ?? ''),
            'port' => sanitize_text_field($_POST['mj_smtp_port'] ?? ''),
            'auth' => isset($_POST['mj_smtp_auth']) ? 1 : 0,
            'username' => sanitize_text_field($_POST['mj_smtp_user'] ?? ''),
            'password' => sanitize_text_field($_POST['mj_smtp_pass'] ?? ''),
            'secure' => sanitize_text_field($_POST['mj_smtp_secure'] ?? ''),
            'from_email' => sanitize_email($_POST['mj_smtp_from'] ?? ''),
            'from_name' => sanitize_text_field($_POST['mj_smtp_from_name'] ?? ''),
        );
        $stripe_publishable = isset($_POST['mj_stripe_publishable_key']) ? sanitize_text_field($_POST['mj_stripe_publishable_key']) : '';
        $stripe_secret = isset($_POST['mj_stripe_secret_key']) ? sanitize_text_field($_POST['mj_stripe_secret_key']) : '';
        $stripe_webhook_secret = isset($_POST['mj_stripe_webhook_secret']) ? sanitize_text_field($_POST['mj_stripe_webhook_secret']) : '';
        $stripe_test_publishable = isset($_POST['mj_stripe_test_publishable_key']) ? sanitize_text_field($_POST['mj_stripe_test_publishable_key']) : '';
        $stripe_test_secret = isset($_POST['mj_stripe_test_secret_key']) ? sanitize_text_field($_POST['mj_stripe_test_secret_key']) : '';
        $stripe_test_webhook_secret = isset($_POST['mj_stripe_test_webhook_secret']) ? sanitize_text_field($_POST['mj_stripe_test_webhook_secret']) : '';
        $stripe_test_mode = isset($_POST['mj_stripe_test_mode']) ? '1' : '0';
        $email_test_mode = isset($_POST['mj_email_test_mode']) ? '1' : '0';
        $sms_test_mode = isset($_POST['mj_sms_test_mode']) ? '1' : '0';
        $sms_provider_raw = isset($_POST['mj_sms_provider']) ? sanitize_key($_POST['mj_sms_provider']) : 'disabled';
        $sms_provider = in_array($sms_provider_raw, array('disabled', 'textbelt', 'twilio'), true) ? $sms_provider_raw : 'disabled';
        $sms_textbelt_api_key = isset($_POST['mj_sms_textbelt_api_key']) ? sanitize_text_field($_POST['mj_sms_textbelt_api_key']) : '';
        $sms_twilio_sid = isset($_POST['mj_sms_twilio_sid']) ? sanitize_text_field($_POST['mj_sms_twilio_sid']) : '';
        $sms_twilio_token = isset($_POST['mj_sms_twilio_token']) ? sanitize_text_field($_POST['mj_sms_twilio_token']) : '';
        $sms_twilio_from = isset($_POST['mj_sms_twilio_from']) ? sanitize_text_field($_POST['mj_sms_twilio_from']) : '';
        $stripe_success_page = isset($_POST['mj_stripe_success_page']) ? intval($_POST['mj_stripe_success_page']) : 0;
        $stripe_cancel_page = isset($_POST['mj_stripe_cancel_page']) ? intval($_POST['mj_stripe_cancel_page']) : 0;
        $registration_page = isset($_POST['mj_login_registration_page']) ? intval($_POST['mj_login_registration_page']) : 0;
        $default_avatar_id = isset($_POST['mj_login_default_avatar_id']) ? intval($_POST['mj_login_default_avatar_id']) : 0;
        $registration_page = isset($_POST['mj_login_registration_page']) ? intval($_POST['mj_login_registration_page']) : 0;
        
        update_option('mj_notify_email', $notify_email);
        update_option('mj_smtp_settings', $smtp);
        update_option('mj_stripe_publishable_key', $stripe_publishable);
        update_option('mj_stripe_test_publishable_key', $stripe_test_publishable);
        update_option('mj_stripe_test_mode', $stripe_test_mode);
        update_option('mj_email_test_mode', $email_test_mode);
        update_option('mj_sms_test_mode', $sms_test_mode);
        update_option('mj_sms_provider', $sms_provider);
        update_option('mj_sms_textbelt_api_key', $sms_textbelt_api_key);
        update_option('mj_sms_twilio_sid', $sms_twilio_sid);
        update_option('mj_sms_twilio_token', $sms_twilio_token);
        update_option('mj_sms_twilio_from', $sms_twilio_from);
        update_option('mj_stripe_success_page', $stripe_success_page > 0 ? $stripe_success_page : 0);
        update_option('mj_stripe_cancel_page', $stripe_cancel_page > 0 ? $stripe_cancel_page : 0);
        update_option('mj_login_registration_page', $registration_page > 0 ? $registration_page : 0);
        update_option('mj_login_default_avatar_id', $default_avatar_id > 0 ? $default_avatar_id : 0);
        update_option('mj_login_registration_page', $registration_page > 0 ? $registration_page : 0);

        $google_sync_enabled = isset($_POST['mj_events_google_sync_enabled']) ? '1' : '0';
        update_option('mj_events_google_sync_enabled', $google_sync_enabled);

        $google_calendar_id = isset($_POST['mj_events_google_calendar_id']) ? sanitize_text_field(wp_unslash($_POST['mj_events_google_calendar_id'])) : '';
        $google_access_token = isset($_POST['mj_events_google_access_token']) ? sanitize_textarea_field(wp_unslash($_POST['mj_events_google_access_token'])) : '';

        update_option('mj_events_google_calendar_id', $google_calendar_id);
        update_option('mj_events_google_access_token', $google_access_token);

        if (class_exists('MjEventGoogleCalendar')) {
            $google_sync_token = MjEventGoogleCalendar::get_current_token();

            if (isset($_POST['mj_events_google_sync_regenerate'])) {
                $google_sync_token = MjEventGoogleCalendar::regenerate_token();
            } else {
                $token_candidate = isset($_POST['mj_events_google_sync_token']) ? sanitize_text_field($_POST['mj_events_google_sync_token']) : '';
                if ($token_candidate !== '') {
                    $google_sync_token = MjEventGoogleCalendar::store_token($token_candidate);
                } elseif ($google_sync_token === '' && $google_sync_enabled === '1') {
                    $google_sync_token = MjEventGoogleCalendar::regenerate_token();
                }
            }

            if ($google_sync_enabled !== '1' && $google_sync_token === '') {
                update_option('mj_events_google_sync_token', '', false);
            }
        }

        $default_account_links = function_exists('mj_member_login_component_get_default_link_settings')
            ? mj_member_login_component_get_default_link_settings()
            : array();

        $submitted_account_links = isset($_POST['mj_account_links']) && is_array($_POST['mj_account_links'])
            ? wp_unslash($_POST['mj_account_links'])
            : array();

        $normalized_account_links = array();

        foreach ($default_account_links as $link_key => $link_defaults) {
            $raw_row = isset($submitted_account_links[$link_key]) && is_array($submitted_account_links[$link_key])
                ? $submitted_account_links[$link_key]
                : array();

            $enabled = isset($raw_row['enabled']) && (string) $raw_row['enabled'] === '1';
            $label = isset($raw_row['label']) ? sanitize_text_field($raw_row['label']) : '';
            $page_id = isset($raw_row['page_id']) ? (int) $raw_row['page_id'] : 0;

            $normalized_account_links[$link_key] = array(
                'enabled' => $enabled ? 1 : 0,
                'label' => (!empty($link_defaults['editable_label']) && $label !== '') ? $label : '',
                'page_id' => $page_id > 0 ? $page_id : 0,
            );
        }

        update_option('mj_account_links_settings', $normalized_account_links);

        // If a secret was provided, store it securely via MjStripeConfig
        if (!empty($stripe_secret) && class_exists('MjStripeConfig')) {
            MjStripeConfig::set_secret_key($stripe_secret, 'live');
        }
        if (!empty($stripe_webhook_secret) && class_exists('MjStripeConfig')) {
            MjStripeConfig::set_webhook_secret($stripe_webhook_secret, 'live');
        }
        if (!empty($stripe_test_secret) && class_exists('MjStripeConfig')) {
            MjStripeConfig::set_secret_key($stripe_test_secret, 'test');
        }
        if (!empty($stripe_test_webhook_secret) && class_exists('MjStripeConfig')) {
            MjStripeConfig::set_webhook_secret($stripe_test_webhook_secret, 'test');
        }
        // Annual fee
        $annual_fee = isset($_POST['mj_annual_fee']) ? sanitize_text_field($_POST['mj_annual_fee']) : '';
        if ($annual_fee !== '') {
            // store as float with 2 decimals
            $fee_val = number_format((float)$annual_fee, 2, '.', '');
            update_option('mj_annual_fee', $fee_val);
        }
        if (isset($_POST['mj_save_settings']) || isset($_POST['mj_events_google_sync_regenerate']) || isset($_POST['mj_events_google_sync_force'])) {
            $notices[] = array('type' => 'success', 'message' => '✅ Paramètres sauvegardés avec succès');
        }

        if (isset($_POST['mj_events_google_sync_force']) && class_exists('MjEventGoogleCalendar')) {
            $calendar_id_option = get_option('mj_events_google_calendar_id', '');
            $access_token_option = get_option('mj_events_google_access_token', '');

            if ($calendar_id_option === '' || $access_token_option === '') {
                $notices[] = array('type' => 'error', 'message' => '⚠️ Renseignez l’ID du calendrier et le jeton d’accès avant de lancer la synchronisation.');
            } else {
                $sync_args = array(
                    'timeout' => 25,
                );
                $sync_result = MjEventGoogleCalendar::sync_with_google_calendar($calendar_id_option, $access_token_option, $sync_args);

                if (is_wp_error($sync_result)) {
                    $notices[] = array('type' => 'error', 'message' => '❌ Synchronisation Google : ' . esc_html($sync_result->get_error_message()));
                } else {
                    $notices[] = array('type' => 'success', 'message' => sprintf('✅ Synchronisation Google effectuée — %d envoyés, %d ignorés.', (int) $sync_result['synced'], (int) $sync_result['skipped']));
                    if (!empty($sync_result['errors'])) {
                        $notices[] = array('type' => 'warning', 'message' => '⚠️ Détails : ' . esc_html(implode(' | ', array_map('wp_strip_all_tags', $sync_result['errors']))));
                    }
                }
            }
        }

        if (!empty($notices)) {
            foreach ($notices as $notice_entry) {
                $class = 'notice';
                switch ($notice_entry['type']) {
                    case 'error':
                        $class .= ' notice-error';
                        break;
                    case 'warning':
                        $class .= ' notice-warning';
                        break;
                    default:
                        $class .= ' notice-success';
                }
                printf('<div class="%s"><p>%s</p></div>', esc_attr($class), wp_kses_post($notice_entry['message']));
            }
        }
    }

    // Handle create/delete webhook actions
    if (isset($_POST['mj_create_webhook']) && check_admin_referer('mj_create_webhook_nonce') && current_user_can('manage_options')) {
        try {
            $requested_mode = isset($_POST['mj_webhook_mode']) && $_POST['mj_webhook_mode'] === 'test' ? 'test' : 'live';
            $target_url = home_url('/stripe-webhook.php');
            $res = MjStripeConfig::create_webhook_endpoint($target_url, array('checkout.session.completed','payment_intent.succeeded'), $requested_mode);
            $mode_label = isset($res['mode']) ? strtoupper($res['mode']) : strtoupper($requested_mode);
            echo '<div class="notice notice-success"><p>✅ Webhook ' . esc_html($mode_label) . ' créé (ID: ' . esc_html($res['id']) . '). Le signing secret a été stocké en toute sécurité.</p></div>';
        } catch (Exception $e) {
            echo '<div class="notice notice-error"><p>Erreur création webhook: ' . esc_html($e->getMessage()) . '</p></div>';
        }
    }

    $notify_email = get_option('mj_notify_email', '');
    $smtp = get_option('mj_smtp_settings', array());
    $stripe_publishable = get_option('mj_stripe_publishable_key', '');
    $stripe_secret_encrypted = get_option('mj_stripe_secret_key_encrypted', '');
    $stripe_secret_display = !empty($stripe_secret_encrypted) ? '•••••••• (configurée)' : '';
    $stripe_webhook_encrypted = get_option('mj_stripe_webhook_secret_encrypted', '');
    $stripe_webhook_display = !empty($stripe_webhook_encrypted) ? '•••••••• (configurée)' : '';

    $stripe_test_publishable = get_option('mj_stripe_test_publishable_key', '');
    $stripe_test_secret_encrypted = get_option('mj_stripe_test_secret_key_encrypted', '');
    $stripe_test_secret_display = !empty($stripe_test_secret_encrypted) ? '•••••••• (configurée)' : '';
    $stripe_test_webhook_encrypted = get_option('mj_stripe_test_webhook_secret_encrypted', '');
    $stripe_test_webhook_display = !empty($stripe_test_webhook_encrypted) ? '•••••••• (configurée)' : '';

    $stripe_test_secret_plain = '';
    $stripe_test_webhook_plain = '';
    if (class_exists('MjStripeConfig')) {
        $stripe_test_secret_plain = MjStripeConfig::get_admin_secret_key('test');
        $stripe_test_webhook_plain = MjStripeConfig::get_admin_webhook_secret('test');
    }

    $stripe_test_mode_enabled = get_option('mj_stripe_test_mode', '0') === '1';
    $email_test_mode_enabled = get_option('mj_email_test_mode', '0') === '1';
    $sms_test_mode_enabled = get_option('mj_sms_test_mode', '0') === '1';
    $sms_default_provider = 'twilio';
    $sms_provider = get_option('mj_sms_provider', $sms_default_provider);
    if (!in_array($sms_provider, array('disabled', 'textbelt', 'twilio'), true)) {
        $sms_provider = $sms_default_provider;
    }
    $sms_textbelt_api_key = get_option('mj_sms_textbelt_api_key', '');
    $sms_twilio_sid = get_option('mj_sms_twilio_sid', '');
    $sms_twilio_token = get_option('mj_sms_twilio_token', '');
    $sms_twilio_from = get_option('mj_sms_twilio_from', '');
    $stripe_success_page_id = (int) get_option('mj_stripe_success_page', 0);
    $stripe_cancel_page_id = (int) get_option('mj_stripe_cancel_page', 0);
    $stripe_success_redirect = get_option('mj_stripe_success_redirect', '');
    $stripe_cancel_redirect = get_option('mj_stripe_cancel_redirect', '');
    $annual_fee = get_option('mj_annual_fee', '2.00');
    $registration_page_id = (int) get_option('mj_login_registration_page', 0);
    $default_avatar_id = (int) get_option('mj_login_default_avatar_id', 0);
    $default_avatar_src = '';
    if ($default_avatar_id > 0) {
        $default_avatar_image = wp_get_attachment_image_src($default_avatar_id, 'thumbnail');
        if ($default_avatar_image) {
            $default_avatar_src = $default_avatar_image[0];
        }
    }

    $google_sync_enabled_flag = get_option('mj_events_google_sync_enabled', '0') === '1';
    $google_sync_token_display = '';
    $google_sync_feed_url = '';
    if (class_exists('MjEventGoogleCalendar')) {
        if ($google_sync_enabled_flag) {
            $google_sync_token_display = MjEventGoogleCalendar::get_token(true);
            if ($google_sync_token_display !== '') {
                $google_sync_feed_url = MjEventGoogleCalendar::get_feed_url(false);
            }
        } else {
            $google_sync_token_display = MjEventGoogleCalendar::get_current_token();
        }
    }

    $google_calendar_id_option = get_option('mj_events_google_calendar_id', '');
    $google_access_token_option = get_option('mj_events_google_access_token', '');

    $account_link_settings = function_exists('mj_member_login_component_get_account_link_settings')
        ? mj_member_login_component_get_account_link_settings()
        : array();
    $account_link_defaults = function_exists('mj_member_login_component_get_default_link_settings')
        ? mj_member_login_component_get_default_link_settings()
        : array();
    $account_base_default = function_exists('mj_member_get_account_redirect')
        ? mj_member_get_account_redirect()
        : home_url('/mon-compte');

    $is_live_configured = !empty($stripe_publishable) && !empty($stripe_secret_encrypted);
    $is_test_configured = !empty($stripe_test_publishable) && !empty($stripe_test_secret_encrypted);
    $stripe_configured = $stripe_test_mode_enabled ? $is_test_configured : $is_live_configured;

    $secure_mode = isset($smtp['secure']) ? strtolower($smtp['secure']) : '';
    if (!in_array($secure_mode, array('tls', 'ssl', ''), true)) {
        $secure_mode = '';
    }
    $secure_labels = array(
        'tls' => 'TLS / STARTTLS',
        'ssl' => 'SSL (SMTPS)',
        ''   => 'sans chiffrement explicite'
    );
    $secure_label = $secure_labels[$secure_mode];
    $recommended_ports = array(
        'tls' => '587',
        'ssl' => '465',
        ''   => '25'
    );
    $recommended_port = $recommended_ports[$secure_mode];
    ?>
    <div class="wrap">
        <h1>⚙️ Configuration MJ Péry</h1>
        
        <form method="post" id="mj-settings-form">
            <?php wp_nonce_field('mj_settings_nonce'); ?>

            <div class="mj-settings-tabs">
                <div class="mj-settings-tabs__nav" role="tablist">
                    <button type="button" class="mj-settings-tabs__nav-btn is-active" id="mj-tab-button-stripe" data-tab-target="stripe" role="tab" aria-controls="mj-tab-stripe" aria-selected="true">💳 Paiements Stripe</button>
                    <button type="button" class="mj-settings-tabs__nav-btn" id="mj-tab-button-calendar" data-tab-target="calendar" role="tab" aria-controls="mj-tab-calendar" aria-selected="false">📅 Agenda & Google</button>
                    <button type="button" class="mj-settings-tabs__nav-btn" id="mj-tab-button-account" data-tab-target="account" role="tab" aria-controls="mj-tab-account" aria-selected="false">👤 Espace membre</button>
                    <button type="button" class="mj-settings-tabs__nav-btn" id="mj-tab-button-messaging" data-tab-target="messaging" role="tab" aria-controls="mj-tab-messaging" aria-selected="false">✉️ Notifications &amp; envois</button>
                </div>

                <div class="mj-settings-tabs__panels">
                    <div id="mj-tab-stripe" class="mj-settings-tabs__panel is-active" data-tab="stripe" role="tabpanel" aria-labelledby="mj-tab-button-stripe" aria-hidden="false">
                        <div style="background: #f9f9f9; padding: 20px; margin: 20px 0; border-radius: 5px; border-left: 4px solid #0073aa;">
                            <h2 style="margin-top: 0;">💳 Stripe - Paiements en ligne</h2>
                            <p style="color: #666; font-size: 14px;">
                                Configurez vos clés Stripe pour activer les paiements en ligne et les QR codes de paiement.
                                <br><strong>Créez un compte Stripe gratuit sur <a href="https://stripe.com" target="_blank">stripe.com</a></strong>
                            </p>

                            <p style="margin-top:15px;">
                                <label>
                                    <input type="checkbox" name="mj_stripe_test_mode" value="1" <?php checked($stripe_test_mode_enabled); ?> />
                                    Activer le <strong>mode test Stripe</strong>
                                </label><br>
                                <small style="color:#999;">En mode test, le plugin utilise les clés <code>pk_test</code>/<code>sk_test</code> et aucun paiement réel n'est capturé.</small>
                            </p>

                            <div style="display:flex; flex-wrap:wrap; gap:20px; margin-top:20px;">
                                <div style="flex:1 1 280px; border:1px solid #e2e8f0; background:#fff; padding:15px; border-radius:6px;">
                                    <h3 style="margin-top:0;">🔴 Clés LIVE</h3>
                                    <p>
                                        <label><strong>Clé publique (pk_live_...)</strong></label><br>
                                        <input type="text" name="mj_stripe_publishable_key" value="<?php echo esc_attr($stripe_publishable); ?>" class="regular-text" placeholder="pk_live_...">
                                        <small style="color: #999;">Depuis Stripe &gt; Paramètres &gt; Clés API</small>
                                    </p>
                                    <p>
                                        <label><strong>Clé secrète (sk_live_...)</strong></label><br>
                                        <input type="password" name="mj_stripe_secret_key" value="" class="regular-text" placeholder="sk_live_...">
                                        <small style="color:#666; display:block; margin-top:6px;"><?php echo esc_html($stripe_secret_display); ?></small>
                                        <small style="color: #999;">Ne partagez jamais cette clé (production)</small>
                                    </p>
                                    <p>
                                        <label><strong>Webhook secret (whsec_...)</strong></label><br>
                                        <input type="password" name="mj_stripe_webhook_secret" value="" class="regular-text" placeholder="whsec_...">
                                        <small style="color:#666; display:block; margin-top:6px;"><?php echo esc_html($stripe_webhook_display); ?></small>
                                        <small style="color: #999;">Disponible dans Stripe &gt; Développeurs &gt; Webhooks</small>
                                    </p>
                                </div>

                                <div style="flex:1 1 280px; border:1px solid #e2e8f0; background:#fff; padding:15px; border-radius:6px;">
                                    <h3 style="margin-top:0;">🧪 Clés TEST</h3>
                                    <p>
                                        <label><strong>Clé publique test (pk_test_...)</strong></label><br>
                                        <input type="text" name="mj_stripe_test_publishable_key" value="<?php echo esc_attr($stripe_test_publishable); ?>" class="regular-text" placeholder="pk_test_...">
                                        <small style="color: #999;">Utilisée uniquement lorsque le mode test est activé</small>
                                    </p>
                                    <p>
                                        <label><strong>Clé secrète test (sk_test_...)</strong></label><br>
                                        <input type="<?php echo $stripe_test_mode_enabled ? 'text' : 'password'; ?>" name="mj_stripe_test_secret_key" value="<?php echo esc_attr($stripe_test_mode_enabled ? $stripe_test_secret_plain : ''); ?>" class="regular-text" placeholder="sk_test_...">
                                        <?php if ($stripe_test_mode_enabled && !empty($stripe_test_secret_plain)): ?>
                                            <small style="color:#2b6cb0; display:block; margin-top:6px;">Visible car le mode test est actif.</small>
                                        <?php else: ?>
                                            <small style="color:#666; display:block; margin-top:6px;"><?php echo esc_html($stripe_test_secret_display ?: 'Non configurée.'); ?></small>
                                        <?php endif; ?>
                                        <small style="color: #999;">Parfait pour effectuer des paiements de simulation</small>
                                    </p>
                                    <p>
                                        <label><strong>Webhook secret test (whsec_...)</strong></label><br>
                                        <input type="<?php echo $stripe_test_mode_enabled ? 'text' : 'password'; ?>" name="mj_stripe_test_webhook_secret" value="<?php echo esc_attr($stripe_test_mode_enabled ? $stripe_test_webhook_plain : ''); ?>" class="regular-text" placeholder="whsec_...">
                                        <?php if ($stripe_test_mode_enabled && !empty($stripe_test_webhook_plain)): ?>
                                            <small style="color:#2b6cb0; display:block; margin-top:6px;">Visible car le mode test est actif.</small>
                                        <?php else: ?>
                                            <small style="color:#666; display:block; margin-top:6px;"><?php echo esc_html($stripe_test_webhook_display ?: 'Non configuré.'); ?></small>
                                        <?php endif; ?>
                                        <small style="color: #999;">Depuis votre endpoint Stripe en mode test</small>
                                    </p>
                                </div>
                            </div>

                            <div style="margin-top:15px;">
                                <p style="margin:0 0 4px 0; color:#333;"><strong>Mode actif :</strong> <?php echo $stripe_test_mode_enabled ? 'TEST (simulateur Stripe)' : 'LIVE (production)'; ?></p>
                                <p style="margin:0 0 4px 0; color:<?php echo $is_live_configured ? '#2f855a' : '#d97706'; ?>;">LIVE : <?php echo $is_live_configured ? '✅ Clés configurées' : '⚠️ Clés manquantes'; ?></p>
                                <p style="margin:0; color:<?php echo $is_test_configured ? '#2f855a' : '#6b7280'; ?>;">TEST : <?php echo $is_test_configured ? '✅ Clés configurées' : 'ℹ️ Clés non configurées'; ?></p>
                            </div>
                            <div style="margin-top:16px; display:flex; flex-wrap:wrap; gap:20px;">
                                <div style="flex:1 1 260px;">
                                    <label for="mj-stripe-success-page" style="font-weight:600;">Page de redirection après paiement</label>
                                    <?php
                                    wp_dropdown_pages(array(
                                        'name' => 'mj_stripe_success_page',
                                        'id' => 'mj-stripe-success-page',
                                        'show_option_none' => '— Sélectionnez une page —',
                                        'option_none_value' => '0',
                                        'selected' => $stripe_success_page_id,
                                    ));
                                    ?>
                                    <small style="color:#666; display:block; margin-top:4px;">Laissez vide pour utiliser <?php echo esc_html(home_url('/inscit')); ?>.</small>
                                    <?php if ($stripe_success_page_id === 0 && $stripe_success_redirect !== '') : ?>
                                        <small style="color:#555; display:block; margin-top:4px;">URL personnalisée actuelle&nbsp;: <?php echo esc_html($stripe_success_redirect); ?></small>
                                    <?php endif; ?>
                                </div>
                                <div style="flex:1 1 260px;">
                                    <label for="mj-stripe-cancel-page" style="font-weight:600;">Page en cas d'annulation</label>
                                    <?php
                                    wp_dropdown_pages(array(
                                        'name' => 'mj_stripe_cancel_page',
                                        'id' => 'mj-stripe-cancel-page',
                                        'show_option_none' => '— Sélectionnez une page —',
                                        'option_none_value' => '0',
                                        'selected' => $stripe_cancel_page_id,
                                    ));
                                    ?>
                                    <small style="color:#666; display:block; margin-top:4px;">Laissez vide pour revenir à l'accueil.</small>
                                    <?php if ($stripe_cancel_page_id === 0 && $stripe_cancel_redirect !== '') : ?>
                                        <small style="color:#555; display:block; margin-top:4px;">URL personnalisée actuelle&nbsp;: <?php echo esc_html($stripe_cancel_redirect); ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <p>
                                <label><strong>Montant de la cotisation annuelle (€)</strong></label><br>
                                <input type="text" name="mj_annual_fee" value="<?php echo esc_attr($annual_fee); ?>" class="regular-text" placeholder="2.00">
                                <small style="color: #999;">Montant par défaut utilisé pour les demandes de paiement (format: 2.00)</small>
                            </p>

                            <?php if ($stripe_configured): ?>
                                <p style="color: green; font-weight: bold;">✅ Stripe <?php echo $stripe_test_mode_enabled ? 'TEST' : 'LIVE'; ?> est configuré - Les paiements <?php echo $stripe_test_mode_enabled ? 'de simulation' : 'réels'; ?> passent par Stripe Checkout.</p>
                            <?php else: ?>
                                <p style="color: orange; font-weight: bold;">⚠️ Stripe <?php echo $stripe_test_mode_enabled ? 'TEST' : 'LIVE'; ?> n'est pas entièrement configuré - le système local sera utilisé.</p>
                            <?php endif; ?>
                            <div style="margin-top:12px; padding:10px; border:1px dashed #ddd; background:#fff;">
                                <h3>Webhook Stripe</h3>
                                <p>Créez automatiquement un endpoint webhook dans Stripe pointant vers <code><?php echo esc_html(home_url('/stripe-webhook.php')); ?></code>.</p>
                                <?php
                                    $live_endpoint = get_option('mj_stripe_webhook_endpoint_id', '');
                                    $test_endpoint = get_option('mj_stripe_test_webhook_endpoint_id', '');
                                ?>
                                <p><strong>Endpoint LIVE :</strong> <?php echo $live_endpoint ? esc_html($live_endpoint) : '<em>aucun</em>'; ?></p>
                                <p><strong>Endpoint TEST :</strong> <?php echo $test_endpoint ? esc_html($test_endpoint) : '<em>aucun</em>'; ?></p>
                                <div style="margin-top:10px; display:flex; gap:10px; flex-wrap:wrap;">
                                    <button class="button button-primary" type="submit" form="mj-create-webhook-live-form" <?php echo $is_live_configured ? '' : 'disabled'; ?>>Créer endpoint LIVE</button>
                                    <button class="button button-secondary" type="submit" form="mj-create-webhook-test-form" <?php echo $is_test_configured ? '' : 'disabled'; ?>>Créer endpoint TEST</button>
                                </div>
                                <p style="margin-top:8px; color:#666;">Après création, copiez la valeur <code>whsec_...</code> si vous en avez besoin ou laissez le plugin la gérer (stockée chiffrée).</p>
                                <?php if (!$is_live_configured || !$is_test_configured): ?>
                                    <p style="margin-top:6px; color:#d97706; font-size:12px;">Configurez d'abord les clés Stripe correspondantes pour activer la création d'endpoint.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div id="mj-tab-calendar" class="mj-settings-tabs__panel" data-tab="calendar" role="tabpanel" aria-labelledby="mj-tab-button-calendar" aria-hidden="true">
                        <div style="background:#f1f5f9; padding:20px; margin:20px 0; border-radius:8px; border-left:4px solid #0ea5e9;">
                            <h2 style="margin-top:0;">📅 Synchronisation Google Agenda</h2>
                            <p style="color:#475569; font-size:14px;">Activez un flux ICS sécurisé pour partager automatiquement les événements MJ dans Google Agenda ou tout autre calendrier compatible.</p>

                            <p style="margin-top:15px;">
                                <label>
                                    <input type="checkbox" name="mj_events_google_sync_enabled" value="1" <?php checked($google_sync_enabled_flag); ?> />
                                    Activer l'export Google Agenda (flux ICS sécurisé)
                                </label><br>
                                <small style="color:#64748b;">Un lien privé est généré pour vos animateurs et partenaires. Ne le diffusez qu'aux personnes autorisées.</small>
                            </p>

                            <div style="display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end;">
                                <div style="flex:1 1 260px;">
                                    <label for="mj-events-google-sync-token" style="font-weight:600; display:block; margin-bottom:4px;">Jeton secret</label>
                                    <input type="text" id="mj-events-google-sync-token" name="mj_events_google_sync_token" value="<?php echo esc_attr($google_sync_token_display); ?>" class="regular-text" autocomplete="off" />
                                    <small style="color:#64748b; display:block; margin-top:4px;">Vous pouvez remplacer ce jeton manuellement ou cliquer sur « Régénérer » pour invalider l'ancien lien.</small>
                                </div>
                                <div>
                                    <button type="submit" class="button button-secondary" name="mj_events_google_sync_regenerate" value="1">🔁 Régénérer le jeton</button>
                                </div>
                            </div>

                            <?php if ($google_sync_feed_url !== '') : ?>
                                <div style="margin-top:14px; padding:12px; background:#ffffff; border:1px solid #dbeafe; border-radius:6px;">
                                    <p style="margin:0 0 8px 0; font-weight:600; color:#1d4ed8;">URL du flux ICS</p>
                                    <code style="display:block; padding:8px; background:#eff6ff; border-radius:4px; overflow:auto;"><?php echo esc_html($google_sync_feed_url); ?></code>
                                    <p style="margin:8px 0 0 0; color:#475569; font-size:13px;">Dans Google Agenda : « Autres agendas » → « À partir de l'URL » → collez ce lien, puis validez. Les mises à jour sont synchronisées automatiquement (rafraîchissement toutes les 3 à 6&nbsp;heures).</p>
                                </div>
                            <?php elseif ($google_sync_enabled_flag) : ?>
                                <p style="margin-top:14px; color:#b91c1c;">Le jeton n'a pas pu être généré. Enregistrez les paramètres pour créer un nouveau lien.</p>
                            <?php endif; ?>

                            <div style="margin-top:20px; padding:16px; background:#ffffff; border:1px solid #e2e8f0; border-radius:8px;">
                                <h3 style="margin-top:0; margin-bottom:10px; color:#0f172a;">🔐 Connexion API Google (synchronisation directe)</h3>
                                <p style="margin-top:0; color:#475569; font-size:13px;">Optionnel : indiquez un calendrier cible et un jeton OAuth&nbsp;2.0 disposant du scope <code>https://www.googleapis.com/auth/calendar</code>. Cela permet de pousser immédiatement les événements sans attendre le rafraîchissement du flux ICS.</p>

                                <div style="display:flex; flex-wrap:wrap; gap:16px;">
                                    <div style="flex:1 1 260px;">
                                        <label for="mj-events-google-calendar-id" style="font-weight:600; display:block; margin-bottom:4px;">ID du calendrier Google</label>
                                        <input type="text" id="mj-events-google-calendar-id" name="mj_events_google_calendar_id" value="<?php echo esc_attr($google_calendar_id_option); ?>" class="regular-text" placeholder="agenda@group.calendar.google.com" />
                                        <small style="color:#64748b; display:block; margin-top:4px;">Adresse e-mail du calendrier ou identifiant partagé.</small>
                                    </div>
                                    <div style="flex:1 1 320px;">
                                        <label for="mj-events-google-access-token" style="font-weight:600; display:block; margin-bottom:4px;">Jeton d'accès Google (Bearer)</label>
                                        <textarea id="mj-events-google-access-token" name="mj_events_google_access_token" rows="3" class="large-text code" placeholder="ya29.a0Af..."><?php echo esc_textarea($google_access_token_option); ?></textarea>
                                        <small style="color:#64748b; display:block; margin-top:4px;">Utilisez un jeton fraîchement généré (ou un compte de service).</small>
                                    </div>
                                </div>

                                <div style="margin-top:16px; display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                                    <button type="submit" name="mj_events_google_sync_force" value="1" class="button button-primary">🚀 Forcer la synchronisation Google</button>
                                    <span style="color:#475569; font-size:13px;">Met à jour immédiatement le calendrier cible en créant ou en remplaçant les événements correspondants.</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="mj-tab-account" class="mj-settings-tabs__panel" data-tab="account" role="tabpanel" aria-labelledby="mj-tab-button-account" aria-hidden="true">
                        <h2>🔐 Connexion &amp; espace membre</h2>
                        <p>
                            <label for="mj-login-registration-page">Page d'inscription à utiliser pour le lien "S'inscrire"</label><br>
                            <?php
                            wp_dropdown_pages(array(
                                'name' => 'mj_login_registration_page',
                                'id' => 'mj-login-registration-page',
                                'show_option_none' => '— Sélectionnez une page —',
                                'option_none_value' => '0',
                                'selected' => $registration_page_id,
                            ));
                            ?>
                            <small style="color:#666; display:block; margin-top:4px;">Cette page sera ouverte depuis le lien d'inscription du composant de connexion. Laissez vide pour utiliser <?php echo esc_html(home_url('/inscription')); ?>.</small>
                        </p>
                        <p>
                            <label for="mj-login-default-avatar-id">Image par défaut pour les membres sans photo</label><br>
                            <input type="hidden" name="mj_login_default_avatar_id" id="mj-login-default-avatar-id" value="<?php echo esc_attr($default_avatar_id); ?>" />
                            <div id="mj-login-default-avatar-preview" class="mj-login-avatar-preview">
                                <?php if (!empty($default_avatar_src)) : ?>
                                    <img src="<?php echo esc_url($default_avatar_src); ?>" alt="" />
                                <?php else : ?>
                                    <span class="mj-login-avatar-preview__placeholder">Aucune image sélectionnée</span>
                                <?php endif; ?>
                            </div>
                            <div class="mj-login-avatar-actions">
                                <button type="button" class="button" id="mj-login-default-avatar-select">Choisir une image</button>
                                <button type="button" class="button-secondary" id="mj-login-default-avatar-clear" <?php echo $default_avatar_id ? '' : 'style="display:none;"'; ?>>Retirer</button>
                            </div>
                            <small style="color:#666; display:block; margin-top:4px;">Cette image sera utilisée dans la fenêtre de compte si le membre n'a pas encore de photo.</small>
                        </p>

                        <div style="margin:24px 0; padding:20px; background:#f9fafb; border:1px solid #e5e7eb; border-radius:8px;">
                            <h3 style="margin:0 0 12px 0;">🔗 Liens &laquo;&nbsp;Mon compte&nbsp;&raquo;</h3>
                            <p style="margin:0 0 16px 0; color:#4b5563; font-size:14px;">Configurez ici l'ordre, les libellés et les pages cibles des actions proposées dans l'espace membre. Les boutons mis en avant sur Elementor utilisent automatiquement ces paramètres.</p>
                            <?php foreach ($account_link_settings as $link_key => $link_config) :
                                $default_label = isset($account_link_defaults[$link_key]['label']) ? $account_link_defaults[$link_key]['label'] : (isset($link_config['label']) ? $link_config['label'] : ucfirst($link_key));
                                $is_logout = isset($link_config['type']) && $link_config['type'] === 'logout';
                                $is_for_animateur = isset($link_config['visibility']) && $link_config['visibility'] === 'animateur';
                                $editable_label = !empty($link_config['editable_label']);
                                $current_label = isset($link_config['label']) ? $link_config['label'] : $default_label;
                                $page_id_value = isset($link_config['page_id']) ? (int) $link_config['page_id'] : 0;
                                $section_value = isset($link_config['query']['section']) ? sanitize_key($link_config['query']['section']) : sanitize_key($link_key);
                                $slug_value = isset($link_config['slug']) ? (string) $link_config['slug'] : '';
                                $field_prefix_base = sanitize_key($link_key);
                                if ($field_prefix_base === '') {
                                    $field_prefix_base = 'link_' . md5($link_key);
                                }
                                $field_prefix = 'mj-account-link-' . $field_prefix_base;
                                $dynamic_example = $slug_value !== '' ? home_url('/' . ltrim($slug_value, '/')) : $account_base_default;
                                if ($section_value !== '') {
                                    $dynamic_example = add_query_arg('section', $section_value, $dynamic_example);
                                }
                                $dynamic_example = esc_url($dynamic_example);
                                $is_enabled = !empty($link_config['enabled']);
                            ?>
                            <div style="background:#ffffff; border:1px solid #e5e7eb; border-radius:6px; padding:16px; margin-bottom:16px;">
                                <h4 style="margin:0 0 12px 0; font-size:16px;">Lien&nbsp;: <?php echo esc_html($default_label); ?></h4>
                                <p style="margin:0 0 12px 0;">
                                    <label for="<?php echo esc_attr($field_prefix . '-enabled'); ?>">
                                        <input type="hidden" name="mj_account_links[<?php echo esc_attr($link_key); ?>][enabled]" value="0" />
                                        <input type="checkbox" id="<?php echo esc_attr($field_prefix . '-enabled'); ?>" name="mj_account_links[<?php echo esc_attr($link_key); ?>][enabled]" value="1" <?php checked($is_enabled); ?> />
                                        Afficher ce lien dans l'espace membre
                                    </label>
                                </p>
                                <p style="margin:0 0 12px 0;">
                                    <label for="<?php echo esc_attr($field_prefix . '-label'); ?>">Libellé du lien</label><br>
                                    <input type="text" class="regular-text" id="<?php echo esc_attr($field_prefix . '-label'); ?>" name="mj_account_links[<?php echo esc_attr($link_key); ?>][label]" value="<?php echo esc_attr($current_label); ?>" <?php echo $editable_label ? '' : 'readonly'; ?> />
                                    <?php if (!$editable_label) : ?>
                                        <small style="display:block; margin-top:4px; color:#6b7280;">Le libellé de ce lien est fixe pour garantir la cohérence de l'interface.</small>
                                    <?php endif; ?>
                                </p>
                                <p style="margin:0 0 12px 0;">
                                    <label for="<?php echo esc_attr($field_prefix . '-page'); ?>">Page cible</label><br>
                                    <?php
                                    wp_dropdown_pages(array(
                                        'name' => 'mj_account_links[' . $link_key . '][page_id]',
                                        'id' => $field_prefix . '-page',
                                        'show_option_none' => '— Dynamique —',
                                        'option_none_value' => '0',
                                        'selected' => $page_id_value,
                                    ));
                                    ?>
                                    <small style="display:block; margin-top:4px; color:#6b7280;">Laissez &laquo;&nbsp;Dynamique&nbsp;&raquo; pour rediriger vers <code><?php echo esc_html($dynamic_example); ?></code>.</small>
                                </p>
                                <?php if ($is_for_animateur) : ?>
                                    <p style="margin:0 0 8px 0; color:#0f766e; font-size:13px;">Visible uniquement pour les membres ayant le rôle d'animateur.</p>
                                <?php endif; ?>
                                <?php if ($is_logout) : ?>
                                    <p style="margin:0; color:#6b7280; font-size:13px;">La déconnexion redirige vers l'accueil après fermeture de session.</p>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div id="mj-tab-messaging" class="mj-settings-tabs__panel" data-tab="messaging" role="tabpanel" aria-labelledby="mj-tab-button-messaging" aria-hidden="true">
                        <h2>📧 Notifications</h2>
                        <p>
                            <label>Email destinataire des nouvelles inscriptions</label><br>
                            <input type="email" name="mj_notify_email" value="<?php echo esc_attr($notify_email); ?>" class="regular-text">
                        </p>

                        <h2>📱 SMS</h2>
                        <?php if ($sms_test_mode_enabled) : ?>
                            <div style="margin-bottom:12px; padding:12px 14px; background:#ecfdf3; border-left:4px solid #38a169; border-radius:4px; color:#22543d;">
                                <strong><?php esc_html_e('Mode test SMS activé', 'mj-member'); ?></strong><br>
                                <?php esc_html_e('Les SMS sont simulés : aucun message ne sera transmis tant que ce mode reste activé.', 'mj-member'); ?>
                            </div>
                        <?php endif; ?>
                        <p style="margin:0 0 18px 0;">
                            <label>
                                <input type="checkbox" name="mj_sms_test_mode" value="1" <?php checked($sms_test_mode_enabled); ?> />
                                <?php esc_html_e("Activer le mode test SMS (aucun SMS n'est envoyé)", 'mj-member'); ?>
                            </label><br>
                            <small style="color:#666;">
                                <?php esc_html_e("En mode test, les SMS sont journalisés dans l'interface mais ne sont jamais transmis au fournisseur.", 'mj-member'); ?>
                            </small>
                        </p>
                        <div class="mj-settings-card mj-settings-card--sms" style="margin:0 0 32px 0; padding:16px; background:#fff; border:1px solid #e5e7eb; border-radius:6px;">
                            <h3 style="margin:0 0 12px 0;">📱 <?php esc_html_e('Service SMS', 'mj-member'); ?></h3>
                            <p style="margin:0 0 12px 0;">
                                <label for="mj-sms-provider"><strong><?php esc_html_e('Fournisseur SMS', 'mj-member'); ?></strong></label><br>
                                <select name="mj_sms_provider" id="mj-sms-provider">
                                    <option value="twilio" <?php selected($sms_provider, 'twilio'); ?>>Twilio (recommandé)</option>
                                    <option value="textbelt" <?php selected($sms_provider, 'textbelt'); ?>>Textbelt</option>
                                    <option value="disabled" <?php selected($sms_provider, 'disabled'); ?>><?php esc_html_e('Désactivé', 'mj-member'); ?></option>
                                </select><br>
                                <small style="color:#666;">
                                    <?php esc_html_e('Choisissez le fournisseur utilisé pour l’envoi des SMS. Twilio est activé par défaut et nécessite un SID, un jeton et un numéro émetteur.', 'mj-member'); ?>
                                </small>
                            </p>
                            <div class="mj-sms-provider-fields" data-sms-provider="twilio" <?php echo $sms_provider === 'twilio' ? '' : 'style="display:none;"'; ?>>
                                <p style="margin:0 0 12px 0;">
                                    <label for="mj-sms-twilio-sid"><?php esc_html_e('Account SID Twilio', 'mj-member'); ?></label><br>
                                    <input type="text" name="mj_sms_twilio_sid" id="mj-sms-twilio-sid" value="<?php echo esc_attr($sms_twilio_sid); ?>" class="regular-text" autocomplete="off" placeholder="ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx">
                                    <small style="color:#666; display:block; margin-top:4px;">
                                        <?php esc_html_e('Copiez le SID du compte trouvé dans le tableau de bord Twilio.', 'mj-member'); ?>
                                    </small>
                                </p>
                                <p style="margin:0 0 12px 0;">
                                    <label for="mj-sms-twilio-token"><?php esc_html_e('Auth Token Twilio', 'mj-member'); ?></label><br>
                                    <input type="password" name="mj_sms_twilio_token" id="mj-sms-twilio-token" value="<?php echo esc_attr($sms_twilio_token); ?>" class="regular-text" autocomplete="off" placeholder="••••••">
                                    <small style="color:#666; display:block; margin-top:4px;">
                                        <?php esc_html_e('Utilisez le jeton API principal ou un jeton secondaire disposant des droits SMS.', 'mj-member'); ?>
                                    </small>
                                </p>
                                <p style="margin:0;">
                                    <label for="mj-sms-twilio-from"><?php esc_html_e('Numéro Twilio expéditeur', 'mj-member'); ?></label><br>
                                    <input type="text" name="mj_sms_twilio_from" id="mj-sms-twilio-from" value="<?php echo esc_attr($sms_twilio_from); ?>" class="regular-text" autocomplete="off" placeholder="+324XXXXXXXX">
                                    <small style="color:#666; display:block; margin-top:4px;">
                                        <?php esc_html_e('Renseignez un numéro Twilio valide (format international, ex. +32412345678).', 'mj-member'); ?>
                                    </small>
                                </p>
                            </div>
                            <div class="mj-sms-provider-fields" data-sms-provider="textbelt" <?php echo $sms_provider === 'textbelt' ? '' : 'style="display:none;"'; ?>>
                                <p style="margin:0 0 12px 0;">
                                    <label for="mj-sms-textbelt-api-key"><?php esc_html_e('Clé API Textbelt', 'mj-member'); ?></label><br>
                                    <input type="text" name="mj_sms_textbelt_api_key" id="mj-sms-textbelt-api-key" value="<?php echo esc_attr($sms_textbelt_api_key); ?>" class="regular-text" autocomplete="off">
                                    <small style="color:#666; display:block; margin-top:4px;">
                                        <?php esc_html_e('Collez la clé fournie par Textbelt (exemple : key_live_xxxxxxxxx).', 'mj-member'); ?>
                                    </small>
                                </p>
                            </div>
                            <div style="margin-top:16px; padding:12px; background:#f8fafc; border:1px dashed #cbd5f5; border-radius:6px;">
                                <p style="margin:0 0 6px 0;"><strong><?php esc_html_e('Procédure Twilio', 'mj-member'); ?></strong></p>
                                <p style="margin:0 0 4px 0; color:#475569;">1. <?php esc_html_e('Créez un compte sur twilio.com et vérifiez votre numéro de contact.', 'mj-member'); ?></p>
                                <p style="margin:0 0 4px 0; color:#475569;">2. <?php esc_html_e('Achetez un numéro SMS compatible (menu Phone Numbers) et copiez le dans le champ « Numéro Twilio expéditeur ».', 'mj-member'); ?></p>
                                <p style="margin:0 0 8px 0; color:#475569;">3. <?php esc_html_e('Générez un Auth Token (Account > API Keys) puis collez le SID, le token et le numéro dans les champs ci-dessus.', 'mj-member'); ?></p>
                                <p style="margin:0; color:#334155;"><strong><?php esc_html_e('Tarifs indicatifs', 'mj-member'); ?></strong>&nbsp;: <?php esc_html_e('~1,00 € HT/mois pour le numéro + ~0,07 € HT par SMS envoyé en Belgique/France (vérifiez les tarifs Twilio pour votre pays).', 'mj-member'); ?></p>
                            </div>
                        </div>

                        <h2>📮 Configuration SMTP</h2>
                        <?php if ($email_test_mode_enabled) : ?>
                            <div style="margin-bottom:12px; padding:12px 14px; background:#fff8e5; border-left:4px solid #f0b429; border-radius:4px; color:#7a5200;">
                                <strong><?php esc_html_e('Mode test email activé', 'mj-member'); ?></strong><br>
                                <?php esc_html_e('Les envois sont simulés : aucun message ne quittera le serveur tant que ce mode reste activé.', 'mj-member'); ?>
                            </div>
                        <?php endif; ?>
                        <p style="margin:0 0 16px 0;">
                            <label>
                                <input type="checkbox" name="mj_email_test_mode" value="1" <?php checked($email_test_mode_enabled); ?> />
                                <?php esc_html_e("Activer le mode test email (aucun email n'est envoyé)", 'mj-member'); ?>
                            </label><br>
                            <small style="color:#666;">
                                <?php esc_html_e("En mode test, les emails sont préparés et visibles dans l'interface d'envoi mais ne sont pas transmis aux destinataires.", 'mj-member'); ?>
                            </small>
                        </p>
                        <p>
                            <label>Hôte SMTP</label><br>
                            <input type="text" name="mj_smtp_host" value="<?php echo esc_attr($smtp['host'] ?? ''); ?>" class="regular-text" placeholder="mail.example.com">
                        </p>
                        <p>
                            <label for="mj-smtp-port">Port</label><br>
                            <input type="text" name="mj_smtp_port" id="mj-smtp-port" value="<?php echo esc_attr($smtp['port'] ?? '587'); ?>" class="regular-text" placeholder="587" inputmode="numeric" list="mj-smtp-port-options">
                            <datalist id="mj-smtp-port-options">
                                <option value="587">587 — TLS / STARTTLS</option>
                                <option value="465">465 — SSL</option>
                                <option value="25">25 — Sans chiffrement</option>
                            </datalist>
                            <small id="mj-smtp-port-helper" class="description" data-port="<?php echo esc_attr($recommended_port); ?>">
                                Recommandé pour <span id="mj-smtp-port-helper-label"><?php echo esc_html($secure_label); ?></span> :
                                <strong class="mj-smtp-port-value"><?php echo esc_html($recommended_port); ?></strong>.
                                <button type="button" class="button-link" id="mj-smtp-port-apply">Utiliser cette valeur</button>
                            </small>
                        </p>
                        <p>
                            <label>
                                <input type="checkbox" name="mj_smtp_auth" <?php checked(!empty($smtp['auth'])); ?> />
                                Utiliser l'authentification SMTP
                            </label>
                        </p>
                        <p>
                            <label>Utilisateur SMTP</label><br>
                            <input type="text" name="mj_smtp_user" value="<?php echo esc_attr($smtp['username'] ?? ''); ?>" class="regular-text" placeholder="user@example.com">
                        </p>
                        <p>
                            <label>Mot de passe SMTP</label><br>
                            <input type="password" name="mj_smtp_pass" value="<?php echo esc_attr($smtp['password'] ?? ''); ?>" class="regular-text">
                        </p>
                        <p>
                            <label for="mj-smtp-secure">Sécurité</label><br>
                            <select name="mj_smtp_secure" id="mj-smtp-secure">
                                <option value="" data-port="25" data-label="sans chiffrement explicite" <?php selected($secure_mode, ''); ?>>Aucune (désactivée)</option>
                                <option value="tls" data-port="587" data-label="TLS / STARTTLS" <?php selected($secure_mode, 'tls'); ?>>TLS / STARTTLS</option>
                                <option value="ssl" data-port="465" data-label="SSL (SMTPS)" <?php selected($secure_mode, 'ssl'); ?>>SSL (SMTPS)</option>
                            </select>
                            <small class="description">Choisissez la méthode demandée par votre hébergeur. TLS (STARTTLS) est recommandée pour la plupart des serveurs modernes.</small>
                        </p>
                        <p>
                            <label>Email expéditeur</label><br>
                            <input type="email" name="mj_smtp_from" value="<?php echo esc_attr($smtp['from_email'] ?? ''); ?>" class="regular-text">
                        </p>
                        <p>
                            <label>Nom expéditeur</label><br>
                            <input type="text" name="mj_smtp_from_name" value="<?php echo esc_attr($smtp['from_name'] ?? ''); ?>" class="regular-text" placeholder="MJ Péry">
                        </p>
                    </div>
                </div>
            </div>

            <p style="margin-top: 30px;">
                <button class="button button-primary button-large" type="submit" name="mj_save_settings">💾 Enregistrer les paramètres</button>
            </p>
        </form>

        <form method="post" id="mj-create-webhook-live-form" style="display:none;">
            <?php wp_nonce_field('mj_create_webhook_nonce'); ?>
            <input type="hidden" name="mj_create_webhook" value="1" />
            <input type="hidden" name="mj_webhook_mode" value="live" />
        </form>
        <form method="post" id="mj-create-webhook-test-form" style="display:none;">
            <?php wp_nonce_field('mj_create_webhook_nonce'); ?>
            <input type="hidden" name="mj_create_webhook" value="1" />
            <input type="hidden" name="mj_webhook_mode" value="test" />
        </form>
        <style>
        .mj-settings-tabs__nav {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 20px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 8px;
        }
        .mj-settings-tabs__nav-btn {
            border: none;
            background: #f8fafc;
            color: #0f172a;
            padding: 8px 14px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s ease, color 0.2s ease;
        }
        .mj-settings-tabs__nav-btn.is-active {
            background: #0ea5e9;
            color: #ffffff;
            box-shadow: inset 0 -2px 0 rgba(14, 165, 233, 0.35);
        }
        .mj-settings-tabs__nav-btn:focus-visible {
            outline: 2px solid #0ea5e9;
            outline-offset: 2px;
        }
        .mj-settings-tabs__panel {
            display: none;
            padding-top: 24px;
        }
        .mj-settings-tabs__panel.is-active {
            display: block;
        }
        .mj-login-avatar-preview {
            width: 96px;
            height: 96px;
            border-radius: 50%;
            overflow: hidden;
            background: #f3f4f6;
            border: 1px dashed #d1d5db;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 10px 0;
        }
        .mj-login-avatar-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .mj-login-avatar-preview__placeholder {
            font-size: 12px;
            color: #6b7280;
            text-align: center;
            padding: 0 8px;
        }
        .mj-login-avatar-actions {
            display: flex;
            gap: 8px;
            align-items: center;
            margin-bottom: 6px;
        }
        </style>
        <script>
        (function () {
            var container = document.querySelector('.mj-settings-tabs');
            if (!container) {
                return;
            }

            var storageKey = 'mjMemberSettingsActiveTab';
            var navButtons = Array.prototype.slice.call(container.querySelectorAll('.mj-settings-tabs__nav-btn'));
            var panels = Array.prototype.slice.call(container.querySelectorAll('.mj-settings-tabs__panel'));

            if (!navButtons.length || !panels.length) {
                return;
            }

            function activate(tabName, skipPersist) {
                var matched = false;

                navButtons.forEach(function (btn) {
                    var isTarget = btn.getAttribute('data-tab-target') === tabName;
                    btn.classList.toggle('is-active', isTarget);
                    btn.setAttribute('aria-selected', isTarget ? 'true' : 'false');
                    if (isTarget) {
                        matched = true;
                    }
                });

                panels.forEach(function (panel) {
                    var isTarget = panel.getAttribute('data-tab') === tabName;
                    panel.classList.toggle('is-active', isTarget);
                    panel.setAttribute('aria-hidden', isTarget ? 'false' : 'true');
                });

                if (matched && !skipPersist && window.localStorage) {
                    try {
                        window.localStorage.setItem(storageKey, tabName);
                    } catch (error) {
                        // ignore storage errors
                    }
                }

                return matched;
            }

            navButtons.forEach(function (btn) {
                btn.addEventListener('click', function (event) {
                    event.preventDefault();
                    activate(btn.getAttribute('data-tab-target'));
                });
            });

            var initialTab = null;
            if (window.localStorage) {
                try {
                    initialTab = window.localStorage.getItem(storageKey);
                } catch (error) {
                    initialTab = null;
                }
            }

            if (!activate(initialTab, true) && navButtons[0]) {
                activate(navButtons[0].getAttribute('data-tab-target'), true);
            }
        })();
        (function () {
            var secureSelect = document.getElementById('mj-smtp-secure');
            var portInput = document.getElementById('mj-smtp-port');
            var helper = document.getElementById('mj-smtp-port-helper');
            var helperLabel = document.getElementById('mj-smtp-port-helper-label');
            var portValue = helper ? helper.querySelector('.mj-smtp-port-value') : null;
            var applyButton = document.getElementById('mj-smtp-port-apply');

            if (!secureSelect || !portInput || !helper || !helperLabel || !portValue || !applyButton) {
                return;
            }

            function refreshHelper() {
                var selected = secureSelect.options[secureSelect.selectedIndex];
                var recommendedPort = selected ? selected.getAttribute('data-port') : '';
                var label = selected ? selected.getAttribute('data-label') : '';

                helperLabel.textContent = label || 'votre configuration';

                if (recommendedPort) {
                    helper.dataset.port = recommendedPort;
                    portValue.textContent = recommendedPort;
                    applyButton.disabled = false;
                    applyButton.setAttribute('data-port', recommendedPort);
                } else {
                    helper.dataset.port = '';
                    portValue.textContent = '—';
                    applyButton.disabled = true;
                    applyButton.removeAttribute('data-port');
                }
            }

            secureSelect.addEventListener('change', refreshHelper);

            applyButton.addEventListener('click', function () {
                var port = this.getAttribute('data-port');
                if (port) {
                    portInput.value = port;
                    portInput.focus();
                }
            });

            refreshHelper();
        })();
        (function () {
            var providerSelect = document.getElementById('mj-sms-provider');
            var providerSections = document.querySelectorAll('.mj-sms-provider-fields');

            if (!providerSelect || !providerSections.length) {
                return;
            }

            function toggleProviderFields() {
                var activeProvider = providerSelect.value;
                Array.prototype.forEach.call(providerSections, function (section) {
                    var sectionProvider = section.getAttribute('data-sms-provider');
                    section.style.display = sectionProvider === activeProvider ? '' : 'none';
                });
            }

            providerSelect.addEventListener('change', toggleProviderFields);
            toggleProviderFields();
        })();
        (function ($) {
            var mediaFrame;
            var selectButton = $('#mj-login-default-avatar-select');
            var clearButton = $('#mj-login-default-avatar-clear');
            var preview = $('#mj-login-default-avatar-preview');
            var input = $('#mj-login-default-avatar-id');

            function renderPreview(url) {
                if (url) {
                    preview.html('<img src="' + url + '" alt="" />');
                    clearButton.show();
                } else {
                    preview.html('<span class="mj-login-avatar-preview__placeholder">Aucune image sélectionnée</span>');
                    clearButton.hide();
                }
            }

            selectButton.on('click', function (event) {
                event.preventDefault();

                if (mediaFrame) {
                    mediaFrame.open();
                    return;
                }

                mediaFrame = wp.media({
                    title: 'Choisir l\'image par défaut',
                    library: { type: 'image' },
                    button: { text: 'Utiliser cette image' },
                    multiple: false
                });

                mediaFrame.on('select', function () {
                    var attachment = mediaFrame.state().get('selection').first().toJSON();
                    var imageUrl = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
                    input.val(attachment.id);
                    renderPreview(imageUrl);
                });

                mediaFrame.open();
            });

            clearButton.on('click', function (event) {
                event.preventDefault();
                input.val('');
                renderPreview('');
            });
        })(jQuery);
        </script>
    </div>
    <?php
}

