<?php
// Admin settings page for plugin
function mj_settings_page() {
    if (function_exists('wp_enqueue_media')) {
        wp_enqueue_media();
    }
    wp_enqueue_script('jquery');

    // Handle save
    if (isset($_POST['mj_save_settings']) && check_admin_referer('mj_settings_nonce')) {
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
        update_option('mj_stripe_success_page', $stripe_success_page > 0 ? $stripe_success_page : 0);
        update_option('mj_stripe_cancel_page', $stripe_cancel_page > 0 ? $stripe_cancel_page : 0);
        update_option('mj_login_registration_page', $registration_page > 0 ? $registration_page : 0);
        update_option('mj_login_default_avatar_id', $default_avatar_id > 0 ? $default_avatar_id : 0);
        update_option('mj_login_registration_page', $registration_page > 0 ? $registration_page : 0);
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
        echo '<div class="notice notice-success"><p>✅ Paramètres sauvegardés avec succès</p></div>';
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
            
            <!-- Section STRIPE -->
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
            
            <h2>🔐 Connexion & espace membre</h2>
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

            <!-- Section NOTIFICATIONS -->
            <h2>📧 Notifications</h2>
            <p>
                <label>Email destinataire des nouvelles inscriptions</label><br>
                <input type="email" name="mj_notify_email" value="<?php echo esc_attr($notify_email); ?>" class="regular-text">
            </p>
            
            <!-- Section SMTP -->
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

