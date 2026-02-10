<?php

use Mj\Member\Classes\MjGoogleDrive;
use Mj\Member\Core\Config;

// Register TinyMCE table plugin from CDN
add_filter('mce_external_plugins', 'mj_member_add_tinymce_table_plugin');
function mj_member_add_tinymce_table_plugin($plugins) {
    $plugins['table'] = 'https://cdnjs.cloudflare.com/ajax/libs/tinymce/4.9.11/plugins/table/plugin.min.js';
    $plugins['code'] = 'https://cdnjs.cloudflare.com/ajax/libs/tinymce/4.9.11/plugins/code/plugin.min.js';
    return $plugins;
}

// Add lineheight button to TinyMCE (only on MJ settings page)
add_action('admin_print_footer_scripts', 'mj_member_tinymce_lineheight_inline_plugin', 99);
function mj_member_tinymce_lineheight_inline_plugin() {
    $screen = get_current_screen();
    if (!$screen || strpos($screen->id, 'mj_settings') === false) {
        return;
    }
    ?>
    <script type="text/javascript">
    (function() {
        if (typeof tinymce !== 'undefined' && !tinymce.PluginManager.get('lineheight')) {
            tinymce.PluginManager.add('lineheight', function(editor) {
                var lineHeights = ['1', '1.2', '1.4', '1.5', '1.6', '1.8', '2', '2.5', '3'];
                var menuItems = lineHeights.map(function(lh) {
                    return {
                        text: lh,
                        onclick: function() {
                            editor.formatter.toggle('lineheight', { value: lh });
                        }
                    };
                });
                editor.addButton('lineheightselect', {
                    type: 'menubutton',
                    text: 'Interligne',
                    icon: false,
                    menu: menuItems
                });
                editor.on('init', function() {
                    editor.formatter.register('lineheight', {
                        selector: 'p,h1,h2,h3,h4,h5,h6,td,th,li,div,span',
                        styles: { 'line-height': '%value' }
                    });
                });
            });
        }
    })();
    </script>
    <?php
}

// Admin settings page for plugin
function mj_settings_page() {
    if (function_exists('wp_enqueue_media')) {
        wp_enqueue_media();
    }
    wp_enqueue_script('jquery');

    if (function_exists('mj_member_account_menu_icon_enqueue_assets')) {
        mj_member_account_menu_icon_enqueue_assets();
    }

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
        $regulation_page = isset($_POST['mj_registration_regulation_page']) ? intval($_POST['mj_registration_regulation_page']) : 0;
        $default_avatar_id = isset($_POST['mj_login_default_avatar_id']) ? intval($_POST['mj_login_default_avatar_id']) : 0;
        $cards_background_id = isset($_POST['mj_cards_pdf_background_image_id']) ? intval($_POST['mj_cards_pdf_background_image_id']) : 0;
        $cards_background_back_id = isset($_POST['mj_cards_pdf_background_back_image_id']) ? intval($_POST['mj_cards_pdf_background_back_image_id']) : 0;
        $cards_double_sided = isset($_POST['mj_cards_pdf_double_sided']) ? '1' : '0';
        $registration_page = isset($_POST['mj_login_registration_page']) ? intval($_POST['mj_login_registration_page']) : 0;
        $openai_api_key = isset($_POST['mj_openai_api_key']) ? sanitize_text_field(wp_unslash($_POST['mj_openai_api_key'])) : '';
        $photo_grimlins_prompt = isset($_POST['mj_photo_grimlins_prompt']) ? sanitize_textarea_field(wp_unslash($_POST['mj_photo_grimlins_prompt'])) : '';
        
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
        update_option('mj_registration_regulation_page', $regulation_page > 0 ? $regulation_page : 0);
        update_option('mj_login_default_avatar_id', $default_avatar_id > 0 ? $default_avatar_id : 0);
        update_option('mj_cards_pdf_background_image_id', $cards_background_id > 0 ? $cards_background_id : 0);
        update_option('mj_cards_pdf_background_back_image_id', $cards_background_back_id > 0 ? $cards_background_back_id : 0);
        update_option('mj_cards_pdf_double_sided', $cards_double_sided);
        update_option('mj_login_registration_page', $registration_page > 0 ? $registration_page : 0);
        update_option('mj_member_openai_api_key', $openai_api_key);
        update_option('mj_member_photo_grimlins_prompt', $photo_grimlins_prompt);

        // Document d'inscription header/footer
        $regdoc_header = isset($_POST['mj_regdoc_header']) ? wp_kses_post(wp_unslash($_POST['mj_regdoc_header'])) : '';
        $regdoc_footer = isset($_POST['mj_regdoc_footer']) ? wp_kses_post(wp_unslash($_POST['mj_regdoc_footer'])) : '';
        update_option('mj_regdoc_header', $regdoc_header);
        update_option('mj_regdoc_footer', $regdoc_footer);

        $drive_root_folder = isset($_POST['mj_documents_google_root_folder_id'])
            ? sanitize_text_field(wp_unslash($_POST['mj_documents_google_root_folder_id']))
            : '';
        $drive_service_account_json = isset($_POST['mj_documents_google_service_account_json'])
            ? trim((string) wp_unslash($_POST['mj_documents_google_service_account_json']))
            : '';
        $drive_impersonated_user = isset($_POST['mj_documents_google_impersonate_user'])
            ? sanitize_email(wp_unslash($_POST['mj_documents_google_impersonate_user']))
            : '';

        $drive_credentials_valid = true;
        if ($drive_service_account_json !== '') {
            $decoded_drive_credentials = json_decode($drive_service_account_json, true);
            if (!is_array($decoded_drive_credentials) || empty($decoded_drive_credentials['client_email']) || empty($decoded_drive_credentials['private_key'])) {
                $notices[] = array(
                    'type' => 'error',
                    'message' => '[Erreur] Le JSON du compte de service Google Drive est invalide. Vérifiez la syntaxe et collez le fichier complet.',
                );
                $drive_credentials_valid = false;
            }
        }

        update_option('mj_documents_google_root_folder_id', $drive_root_folder);
        update_option('mj_documents_google_impersonate_user', $drive_impersonated_user);
        if ($drive_credentials_valid) {
            update_option('mj_documents_google_service_account_json', $drive_service_account_json);
        }

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
            $icon_id = isset($raw_row['icon_id']) ? (int) $raw_row['icon_id'] : 0;
            $position = isset($raw_row['position']) ? (int) $raw_row['position'] : 999;

            // Sauvegarder le slug de la page pour permettre la portabilité entre sites
            $page_slug = '';
            if ($page_id > 0) {
                $page_post = get_post($page_id);
                if ($page_post && $page_post->post_type === 'page') {
                    $page_slug = $page_post->post_name;
                }
            }

            $normalized_account_links[$link_key] = array(
                'enabled' => $enabled ? 1 : 0,
                'label' => (!empty($link_defaults['editable_label']) && $label !== '') ? $label : '',
                'page_id' => $page_id > 0 ? $page_id : 0,
                'page_slug' => $page_slug,
                'icon_id' => $icon_id > 0 ? $icon_id : 0,
                'position' => $position,
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

        $annual_fee_manual = isset($_POST['mj_annual_fee_manual']) ? sanitize_text_field($_POST['mj_annual_fee_manual']) : '';
        if ($annual_fee_manual !== '') {
            $manual_fee_val = number_format((float)$annual_fee_manual, 2, '.', '');
            update_option('mj_annual_fee_manual', $manual_fee_val);
        } else {
            update_option('mj_annual_fee_manual', '');
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
    $annual_fee_manual_option = get_option('mj_annual_fee_manual', '');
    $annual_fee_manual_display = $annual_fee_manual_option !== '' ? $annual_fee_manual_option : $annual_fee;
    $registration_page_id = (int) get_option('mj_login_registration_page', 0);
    $regulation_page_id = (int) get_option('mj_registration_regulation_page', 0);
    $default_avatar_id = (int) get_option('mj_login_default_avatar_id', 0);
    $default_avatar_src = '';
    if ($default_avatar_id > 0) {
        $default_avatar_image = wp_get_attachment_image_src($default_avatar_id, 'thumbnail');
        if ($default_avatar_image) {
            $default_avatar_src = $default_avatar_image[0];
        }
    }

    $cards_pdf_background_id = (int) get_option('mj_cards_pdf_background_image_id', 0);
    $cards_pdf_background_back_id = (int) get_option('mj_cards_pdf_background_back_image_id', 0);
    $cards_pdf_double_sided = get_option('mj_cards_pdf_double_sided', '0') === '1';
    $cards_pdf_background_src = '';
    if ($cards_pdf_background_id > 0) {
        $cards_pdf_background_image = wp_get_attachment_image_src($cards_pdf_background_id, 'medium');
        if ($cards_pdf_background_image) {
            $cards_pdf_background_src = $cards_pdf_background_image[0];
        } else {
            $fallback_background_url = wp_get_attachment_url($cards_pdf_background_id);
            if ($fallback_background_url) {
                $cards_pdf_background_src = $fallback_background_url;
            }
        }
    }

    $cards_pdf_background_back_src = '';
    if ($cards_pdf_background_back_id > 0) {
        $cards_pdf_background_back_image = wp_get_attachment_image_src($cards_pdf_background_back_id, 'medium');
        if ($cards_pdf_background_back_image) {
            $cards_pdf_background_back_src = $cards_pdf_background_back_image[0];
        } else {
            $fallback_background_back_url = wp_get_attachment_url($cards_pdf_background_back_id);
            if ($fallback_background_back_url) {
                $cards_pdf_background_back_src = $fallback_background_back_url;
            }
        }
    }

    $openai_api_key_option = get_option('mj_member_openai_api_key', '');
    $photo_grimlins_prompt_option = get_option('mj_member_photo_grimlins_prompt', '');
    if (!is_string($photo_grimlins_prompt_option) || $photo_grimlins_prompt_option === '') {
        $photo_grimlins_prompt_option = __('Transforme cette personne en version "Grimlins" fun et stylisée, avec un rendu illustratif détaillé, sans éléments effrayants.', 'mj-member');
    }

    $drive_root_folder_option = get_option('mj_documents_google_root_folder_id', '');
    $drive_service_account_option = get_option('mj_documents_google_service_account_json', '');
    $drive_impersonated_user_option = get_option('mj_documents_google_impersonate_user', '');
    $drive_sdk_available = MjGoogleDrive::isAvailable();
    $drive_configuration_ready = Config::googleDriveIsReady() && $drive_root_folder_option !== '' && $drive_sdk_available;

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
        
        <form method="post" id="mj-settings-form" novalidate>
            <?php wp_nonce_field('mj_settings_nonce'); ?>

            <div class="mj-settings-tabs">
                <div class="mj-settings-tabs__nav" role="tablist">
                    <button type="button" class="mj-settings-tabs__nav-btn is-active" id="mj-tab-button-stripe" data-tab-target="stripe" role="tab" aria-controls="mj-tab-stripe" aria-selected="true">💳 Paiements Stripe</button>
                    <button type="button" class="mj-settings-tabs__nav-btn" id="mj-tab-button-calendar" data-tab-target="calendar" role="tab" aria-controls="mj-tab-calendar" aria-selected="false">📅 Agenda & Google</button>
                    <button type="button" class="mj-settings-tabs__nav-btn" id="mj-tab-button-account" data-tab-target="account" role="tab" aria-controls="mj-tab-account" aria-selected="false">👤 Espace membre</button>
                    <button type="button" class="mj-settings-tabs__nav-btn" id="mj-tab-button-messaging" data-tab-target="messaging" role="tab" aria-controls="mj-tab-messaging" aria-selected="false">✉️ Notifications &amp; envois</button>
                    <button type="button" class="mj-settings-tabs__nav-btn" id="mj-tab-button-ai" data-tab-target="ai" role="tab" aria-controls="mj-tab-ai" aria-selected="false">🧠 IA &amp; médias</button>
                    <button type="button" class="mj-settings-tabs__nav-btn" id="mj-tab-button-regdoc" data-tab-target="regdoc" role="tab" aria-controls="mj-tab-regdoc" aria-selected="false">📄 Document d'inscription</button>
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

                            <div style="margin-top:20px; padding:16px; background:#ffffff; border:1px solid #e2e8f0; border-radius:8px;">
                                <h3 style="margin-top:0; margin-bottom:10px; color:#0f172a;">📁 Google Drive – Gestion des documents</h3>
                                <p style="margin-top:0; color:#475569; font-size:13px;">Ces paramètres alimentent le widget «&nbsp;Documents&nbsp;» et l'intégration Google Drive. Ils nécessitent la bibliothèque PHP <code>google/apiclient</code> (Google API Client) préinstallée sur votre hébergement.</p>

                                <?php if (!$drive_sdk_available) : ?>
                                    <p style="margin:0 0 12px 0; color:#b91c1c; font-weight:600;">
                                        <?php esc_html_e('Le SDK Google Drive n’est pas chargé. Installez le package composer "google/apiclient" et assurez-vous que vendor/autoload.php est inclus.', 'mj-member'); ?>
                                    </p>
                                <?php elseif ($drive_configuration_ready) : ?>
                                    <p style="margin:0 0 12px 0; color:#15803d; font-weight:600;">Configuration Google Drive opérationnelle.</p>
                                <?php else : ?>
                                    <p style="margin:0 0 12px 0; color:#b91c1c; font-weight:600;">Configuration Google Drive incomplète. Complétez les champs ci-dessous.</p>
                                <?php endif; ?>

                                <ol style="margin:0 0 16px 18px; color:#475569; font-size:13px;">
                                    <li>Dans Google Cloud Console : créez un projet, activez l'API Drive puis générez un <strong>compte de service</strong> (format JSON).</li>
                                    <li>Partagez le dossier Google Drive cible avec l'adresse e-mail du compte de service en lui accordant au minimum un accès «&nbsp;Contributeur&nbsp;».</li>
                                    <li>Copiez l'identifiant du dossier (la partie après <code>/folders/</code> dans l'URL) et collez-le ci-dessous.</li>
                                    <li>Collez le fichier JSON du compte de service (contenu complet) et, si nécessaire, indiquez une adresse e-mail à impersoner (compte Google Workspace autorisé).</li>
                                </ol>

                                <div style="display:flex; flex-wrap:wrap; gap:16px;">
                                    <div style="flex:1 1 260px;">
                                        <label for="mj-documents-google-root" style="font-weight:600; display:block; margin-bottom:4px;">ID du dossier racine</label>
                                        <input type="text" id="mj-documents-google-root" name="mj_documents_google_root_folder_id" value="<?php echo esc_attr($drive_root_folder_option); ?>" class="regular-text" placeholder="1AbCDeFGhijkLmNop" />
                                        <small style="color:#64748b; display:block; margin-top:4px;">Copiez l'identifiant figurant dans l'URL <code>https://drive.google.com/drive/folders/&lt;ID&gt;</code>.</small>
                                    </div>
                                    <div style="flex:1 1 260px;">
                                        <label for="mj-documents-google-impersonate" style="font-weight:600; display:block; margin-bottom:4px;">Utilisateur Google à impersoner (facultatif)</label>
                                        <input type="email" id="mj-documents-google-impersonate" name="mj_documents_google_impersonate_user" value="<?php echo esc_attr($drive_impersonated_user_option); ?>" class="regular-text" placeholder="animateur@votre-domaine.be" />
                                        <small style="color:#64748b; display:block; margin-top:4px;">Uniquement requis si votre Drive est géré par Google Workspace et que le compte de service doit agir au nom d'un utilisateur.</small>
                                    </div>
                                </div>

                                <div style="margin-top:16px;">
                                    <label for="mj-documents-google-json" style="font-weight:600; display:block; margin-bottom:4px;">JSON du compte de service</label>
                                    <textarea id="mj-documents-google-json" name="mj_documents_google_service_account_json" rows="8" class="large-text code" placeholder="{&#10;  &quot;type&quot;: &quot;service_account&quot;,&#10;  ...&#10;}"><?php echo esc_textarea($drive_service_account_option); ?></textarea>
                                    <small style="color:#64748b; display:block; margin-top:4px;">Collez le contenu complet du fichier <code>*.json</code> téléchargé depuis Google Cloud. Enregistré tel quel (sécurisé en base de données).</small>
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
                            <label for="mj-annual-fee" style="font-weight:600;">Montant de la cotisation annuelle (€) (paiement en ligne)</label><br>
                            <input type="text" id="mj-annual-fee" name="mj_annual_fee" value="<?php echo esc_attr($annual_fee); ?>" class="regular-text" placeholder="2.00" />
                            <small style="color:#666; display:block; margin-top:4px;">Montant par défaut utilisé pour les paiements en ligne (format : 2.00).</small>
                        </p>
                        <p>
                            <label for="mj-annual-fee-manual" style="font-weight:600;">Montant de la cotisation annuelle (€) (paiement en main propre)</label><br>
                            <input type="text" id="mj-annual-fee-manual" name="mj_annual_fee_manual" value="<?php echo esc_attr($annual_fee_manual_display); ?>" class="regular-text" placeholder="2.00" />
                            <small style="color:#666; display:block; margin-top:4px;">Montant utilisé pour le message "Remets [montant_cotisation]" lorsque l’on remet la cotisation à un animateur (format : 2.00). Laissez vide pour reprendre le montant en ligne.</small>
                        </p>
                        <p>
                            <label for="mj-registration-regulation-page">Page du règlement d'ordre intérieur</label><br>
                            <?php
                            wp_dropdown_pages(array(
                                'name' => 'mj_registration_regulation_page',
                                'id' => 'mj-registration-regulation-page',
                                'show_option_none' => '— Sélectionnez une page —',
                                'option_none_value' => '0',
                                'selected' => $regulation_page_id,
                            ));
                            ?>
                            <small style="color:#666; display:block; margin-top:4px;">Cette page sera proposée par défaut lors des inscriptions publiques et dans les widgets nécessitant la consultation du règlement.</small>
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
                        <p>
                            <label for="mj-card-background-id">Image de fond pour les cartes de visite PDF</label><br>
                            <input type="hidden" name="mj_cards_pdf_background_image_id" id="mj-card-background-id" value="<?php echo esc_attr($cards_pdf_background_id); ?>" />
                            <div id="mj-card-background-preview" class="mj-card-background-preview" style="margin:8px 0; max-width:260px; border:1px solid #e2e8f0; border-radius:8px; overflow:hidden; background:#f8fafc;">
                                <img src="<?php echo !empty($cards_pdf_background_src) ? esc_url($cards_pdf_background_src) : ''; ?>" alt="" style="display:<?php echo !empty($cards_pdf_background_src) ? 'block' : 'none'; ?>; width:100%; height:auto;" />
                                <span class="mj-card-background-preview__placeholder" style="display:<?php echo !empty($cards_pdf_background_src) ? 'none' : 'block'; ?>; padding:20px; text-align:center; color:#64748b; font-size:13px;">Aucune image sélectionnée</span>
                            </div>
                            <div class="mj-card-background-actions" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                                <button type="button" class="button" id="mj-card-background-select">Choisir une image</button>
                                <button type="button" class="button-secondary" id="mj-card-background-clear" <?php echo $cards_pdf_background_id ? '' : 'style="display:none;"'; ?>>Retirer</button>
                            </div>
                            <small style="color:#666; display:block; margin-top:4px;">Cette image sera proposée par défaut dans le module PDF des cartes de visite et appliquée en fond lors de la génération.</small>
                        </p>
                        <p style="margin-top:18px;">
                            <label for="mj-card-double-sided" style="display:flex; align-items:center; gap:8px;">
                                <input type="hidden" name="mj_cards_pdf_double_sided" value="0" />
                                <input type="checkbox" id="mj-card-double-sided" name="mj_cards_pdf_double_sided" value="1" <?php checked($cards_pdf_double_sided); ?> />
                                <span>Activer la génération recto/verso</span>
                            </label>
                            <small style="color:#666; display:block; margin-top:4px;">Lorsque cette option est cochée, chaque planche de cartes sera accompagnée d’une page verso.</small>
                        </p>
                        <div id="mj-card-back-background-wrapper" style="margin:16px 0; <?php echo $cards_pdf_double_sided ? '' : 'display:none;'; ?>">
                            <label for="mj-card-back-background-id" style="display:block; margin-bottom:6px;">Image de fond pour le verso</label>
                            <input type="hidden" name="mj_cards_pdf_background_back_image_id" id="mj-card-back-background-id" value="<?php echo esc_attr($cards_pdf_background_back_id); ?>" />
                            <div id="mj-card-back-background-preview" class="mj-card-background-preview" style="margin:8px 0; max-width:260px; border:1px solid #e2e8f0; border-radius:8px; overflow:hidden; background:#f1f5f9;">
                                <img src="<?php echo !empty($cards_pdf_background_back_src) ? esc_url($cards_pdf_background_back_src) : ''; ?>" alt="" style="display:<?php echo !empty($cards_pdf_background_back_src) ? 'block' : 'none'; ?>; width:100%; height:auto;" />
                                <span class="mj-card-back-background-preview__placeholder" style="display:<?php echo !empty($cards_pdf_background_back_src) ? 'none' : 'block'; ?>; padding:20px; text-align:center; color:#64748b; font-size:13px;">Aucune image sélectionnée pour le verso</span>
                            </div>
                            <div class="mj-card-background-actions" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                                <button type="button" class="button" id="mj-card-back-background-select">Choisir une image</button>
                                <button type="button" class="button-secondary" id="mj-card-back-background-clear" <?php echo $cards_pdf_background_back_id ? '' : 'style="display:none;"'; ?>>Retirer</button>
                            </div>
                            <small style="color:#666; display:block; margin-top:4px;">Définissez une image spécifique pour le verso. Si aucun visuel n’est sélectionné, le verso restera uni.</small>
                        </div>

                        <div style="margin:24px 0; padding:20px; background:#f9fafb; border:1px solid #e5e7eb; border-radius:8px;">
                            <h3 style="margin:0 0 12px 0;">🔗 Liens &laquo;&nbsp;Mon compte&nbsp;&raquo;</h3>
                            <p style="margin:0 0 16px 0; color:#4b5563; font-size:14px;">Configurez ici l'ordre, les libellés et les pages cibles des actions proposées dans l'espace membre. Les boutons mis en avant sur Elementor utilisent automatiquement ces paramètres.</p>
                            
                            <div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap; margin-bottom:16px; padding:12px; background:#fff; border:1px solid #e5e7eb; border-radius:6px;">
                                <button type="button" id="mj-export-pages-btn" class="button button-secondary" style="display:inline-flex; align-items:center; gap:6px;">
                                    <span class="dashicons dashicons-download" style="font-size:16px;"></span>
                                    <?php esc_html_e('Sauvegarder les pages', 'mj-member'); ?>
                                </button>
                                <button type="button" id="mj-import-pages-btn" class="button button-secondary" style="display:inline-flex; align-items:center; gap:6px;">
                                    <span class="dashicons dashicons-upload" style="font-size:16px;"></span>
                                    <?php esc_html_e('Restaurer les pages', 'mj-member'); ?>
                                </button>
                                <span id="mj-pages-export-status" style="font-size:12px; color:#6b7280;"></span>
                            </div>
                            
                            <p style="margin:0 0 16px 0; color:#6366f1; font-size:13px; display:flex; align-items:center; gap:6px;">
                                <span class="dashicons dashicons-move" style="font-size:16px;"></span>
                                Glissez-déposez les liens pour modifier leur ordre d'affichage.
                            </p>
                            <div id="mj-account-links-sortable" class="mj-account-links-sortable">
                            <?php
                            $link_position = 0;
                            foreach ($account_link_settings as $link_key => $link_config) :
                                $default_label = isset($account_link_defaults[$link_key]['label'])
                                    ? $account_link_defaults[$link_key]['label']
                                    : (isset($link_config['label']) ? $link_config['label'] : ucfirst(str_replace('_', ' ', $link_key)));
                                $is_logout = isset($link_config['type']) && $link_config['type'] === 'logout';
                                $animateur_role = class_exists('Mj\\Member\\Classes\\MjRoles') ? \Mj\Member\Classes\MjRoles::ANIMATEUR : 'animateur';
                                $is_for_animateur = isset($link_config['visibility']) && $link_config['visibility'] === $animateur_role;
                                $is_for_hours_team = isset($link_config['visibility']) && $link_config['visibility'] === 'hours_team';
                                $is_for_staff = isset($link_config['visibility']) && $link_config['visibility'] === 'staff';
                                $requires_capability = isset($link_config['requires_capability']) ? (string) $link_config['requires_capability'] : '';
                                $editable_label = !empty($link_config['editable_label']);
                                $current_label = isset($link_config['label']) ? $link_config['label'] : $default_label;
                                $page_id_value = isset($link_config['page_id']) ? (int) $link_config['page_id'] : 0;
                                $section_value = isset($link_config['query']['section']) ? sanitize_key($link_config['query']['section']) : sanitize_key($link_key);
                                $slug_value = isset($link_config['slug']) ? (string) $link_config['slug'] : '';
                                $icon_id_value = isset($link_config['icon_id']) ? (int) $link_config['icon_id'] : 0;
                                $icon_payload = array();
                                if ($icon_id_value > 0 && function_exists('mj_member_account_menu_build_icon_payload_from_attachment')) {
                                    $icon_payload = mj_member_account_menu_build_icon_payload_from_attachment($icon_id_value);
                                }
                                if (function_exists('mj_member_account_menu_sanitize_icon_payload')) {
                                    $icon_payload = mj_member_account_menu_sanitize_icon_payload($icon_payload);
                                }
                                $icon_preview_url = '';
                                if (!empty($icon_payload['preview_url'])) {
                                    $icon_preview_url = $icon_payload['preview_url'];
                                } elseif (!empty($icon_payload['url'])) {
                                    $icon_preview_url = $icon_payload['url'];
                                }
                                $icon_preview_markup = '<span class="mj-member-menu-icon-placeholder">' . esc_html__('Aucune image', 'mj-member') . '</span>';
                                if (!empty($icon_payload['html'])) {
                                    $icon_preview_markup = wp_kses_post($icon_payload['html']);
                                } elseif ($icon_preview_url !== '') {
                                    $icon_preview_markup = sprintf(
                                        '<img src="%1$s" alt="" class="mj-member-menu-icon-preview-image" />',
                                        esc_url($icon_preview_url)
                                    );
                                }
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
                                $current_position = isset($link_config['position']) ? (int) $link_config['position'] : $link_position;
                            ?>
                            <div class="mj-account-link-item" data-link-key="<?php echo esc_attr($link_key); ?>">
                                <input type="hidden" class="mj-account-link-position" name="mj_account_links[<?php echo esc_attr($link_key); ?>][position]" value="<?php echo esc_attr($current_position); ?>" />
                                <div class="mj-account-link-header">
                                    <span class="mj-account-link-handle dashicons dashicons-menu" title="Glisser pour réordonner"></span>
                                    <label class="mj-account-link-toggle">
                                        <input type="hidden" name="mj_account_links[<?php echo esc_attr($link_key); ?>][enabled]" value="0" />
                                        <input type="checkbox" id="<?php echo esc_attr($field_prefix . '-enabled'); ?>" name="mj_account_links[<?php echo esc_attr($link_key); ?>][enabled]" value="1" <?php checked($is_enabled); ?> />
                                    </label>
                                    <span class="mj-account-link-label<?php echo !$is_enabled ? ' is-disabled' : ''; ?>"><?php echo esc_html($current_label); ?></span>
                                    <?php if ($is_for_animateur) : ?>
                                        <span class="mj-account-link-badge mj-account-link-badge--animateur" title="Visible uniquement pour les animateurs">Anim.</span>
                                    <?php elseif ($is_for_staff) : ?>
                                        <span class="mj-account-link-badge mj-account-link-badge--staff" title="Visible pour les animateurs et coordinateurs">Staff</span>
                                    <?php elseif ($is_for_hours_team) : ?>
                                        <span class="mj-account-link-badge mj-account-link-badge--hours" title="Visible pour l'équipe heures">Équipe</span>
                                    <?php endif; ?>
                                    <?php if ($requires_capability) : ?>
                                        <span class="mj-account-link-badge mj-account-link-badge--cap" title="Requiert une capacité spécifique">🔒</span>
                                    <?php endif; ?>
                                    <span class="mj-account-link-position-badge">#<?php echo esc_html($current_position + 1); ?></span>
                                    <button type="button" class="mj-account-link-expand" aria-expanded="false" title="Modifier les options">
                                        <span class="dashicons dashicons-arrow-down-alt2"></span>
                                    </button>
                                </div>
                                <div class="mj-account-link-details" hidden>
                                    <div class="mj-account-link-details-grid">
                                        <div class="mj-account-link-field">
                                            <label for="<?php echo esc_attr($field_prefix . '-label'); ?>">Libellé</label>
                                            <input type="text" id="<?php echo esc_attr($field_prefix . '-label'); ?>" name="mj_account_links[<?php echo esc_attr($link_key); ?>][label]" value="<?php echo esc_attr($current_label); ?>" <?php echo $editable_label ? '' : 'readonly'; ?> />
                                        </div>
                                        <div class="mj-account-link-field">
                                            <label for="<?php echo esc_attr($field_prefix . '-page'); ?>">Page cible</label>
                                            <?php
                                            wp_dropdown_pages(array(
                                                'name' => 'mj_account_links[' . $link_key . '][page_id]',
                                                'id' => $field_prefix . '-page',
                                                'show_option_none' => '— Dynamique —',
                                                'option_none_value' => '0',
                                                'selected' => $page_id_value,
                                            ));
                                            ?>
                                        </div>
                                        <div class="mj-account-link-field mj-account-link-field--icon">
                                            <label>Icône</label>
                                            <div class="mj-member-menu-icon-control mj-member-menu-icon-control--compact" data-mj-member-menu-icon>
                                                <div class="mj-member-menu-icon-preview" data-image-url="<?php echo esc_attr($icon_preview_url); ?>">
                                                    <?php echo $icon_preview_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                                </div>
                                                <div class="mj-member-menu-icon-actions">
                                                    <input type="hidden" class="mj-member-menu-icon-input" name="mj_account_links[<?php echo esc_attr($link_key); ?>][icon_id]" value="<?php echo esc_attr((string) $icon_id_value); ?>" />
                                                    <button type="button" class="button button-small mj-member-menu-icon-select"><?php esc_html_e('Choisir', 'mj-member'); ?></button>
                                                    <button type="button" class="button-link-delete mj-member-menu-icon-remove"<?php echo $icon_id_value > 0 ? '' : ' style="display:none;"'; ?>>×</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php if ($is_logout) : ?>
                                        <p class="mj-account-link-note">La déconnexion redirige vers l'accueil.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php
                            $link_position++;
                            endforeach;
                            ?>
                            </div>
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

                    <div id="mj-tab-ai" class="mj-settings-tabs__panel" data-tab="ai" role="tabpanel" aria-labelledby="mj-tab-button-ai" aria-hidden="true">
                        <div style="background:#f8fafc; border-left:4px solid #7c3aed; padding:18px 20px; border-radius:10px; margin-bottom:24px;">
                            <h2 style="margin:0 0 8px 0;">🧠 Génération d'avatars Grimlins</h2>
                            <p style="margin:0; color:#475569;">
                                <?php esc_html_e('Configurez l’accès OpenAI pour permettre au widget Elementor « Photo Grimlins » de transformer les portraits en versions illustrées.', 'mj-member'); ?><br>
                                <?php esc_html_e('Laissez la clé vide pour désactiver la fonctionnalité. Vous pouvez aussi définir la constante MJ_MEMBER_OPENAI_API_KEY dans wp-config.php.', 'mj-member'); ?>
                            </p>
                        </div>

                        <p style="margin-bottom:18px;">
                            <label for="mj-openai-api-key"><strong><?php esc_html_e('Clé API OpenAI', 'mj-member'); ?></strong></label><br>
                            <input type="password" name="mj_openai_api_key" id="mj-openai-api-key" value="<?php echo esc_attr($openai_api_key_option); ?>" class="regular-text" autocomplete="off" placeholder="sk-...">
                            <small style="color:#6b7280; display:block; margin-top:4px;">
                                <?php esc_html_e('La clé doit disposer de l’accès au modèle « gpt-image-1 ». Elle est stockée dans la base WordPress (utilisez un gestionnaire de secrets pour la production).', 'mj-member'); ?>
                            </small>
                        </p>

                        <p style="margin-bottom:18px;">
                            <label for="mj-photo-grimlins-prompt"><strong><?php esc_html_e('Prompt de transformation', 'mj-member'); ?></strong></label><br>
                            <textarea name="mj_photo_grimlins_prompt" id="mj-photo-grimlins-prompt" rows="4" class="large-text" placeholder="<?php echo esc_attr__('Décris le rendu souhaité…', 'mj-member'); ?>"><?php echo esc_textarea($photo_grimlins_prompt_option); ?></textarea>
                            <small style="color:#6b7280; display:block; margin-top:4px;">
                                <?php esc_html_e('Personnalisez l’instruction envoyée à OpenAI. Le portrait initial est transmis en entrée et la sortie est générée au format PNG 1024×1024.', 'mj-member'); ?>
                            </small>
                        </p>

                        <div style="margin-top:24px; padding:16px; border:1px dashed #cbd5f5; border-radius:8px; background:#fff; color:#334155;">
                            <p style="margin:0 0 6px 0;"><strong><?php esc_html_e('Bonnes pratiques', 'mj-member'); ?></strong></p>
                            <ul style="margin:0 0 0 18px; padding:0; list-style:disc; color:#475569;">
                                <li><?php esc_html_e('Limitez le poids des images d’entrée à 5 Mo (format JPG/PNG/WebP).', 'mj-member'); ?></li>
                                <li><?php esc_html_e('Informez les membres que les avatars générés sont destinés à un usage ludique et peuvent différer du portrait original.', 'mj-member'); ?></li>
                                <li><?php esc_html_e('Supprimez régulièrement les fichiers temporaires si vous n’activez pas la suppression automatique côté serveur.', 'mj-member'); ?></li>
                            </ul>
                        </div>
                    </div>

                    <div id="mj-tab-regdoc" class="mj-settings-tabs__panel" data-tab="regdoc" role="tabpanel" aria-labelledby="mj-tab-button-regdoc" aria-hidden="true">
                        <div style="background:#f0fdf4; border-left:4px solid #22c55e; padding:18px 20px; border-radius:10px; margin-bottom:24px;">
                            <h2 style="margin:0 0 8px 0;">📄 Document d'inscription</h2>
                            <p style="margin:0; color:#475569;">
                                <?php esc_html_e('Configurez l\'en-tête et le pied de page par défaut pour les documents d\'inscription générés via le gestionnaire d\'événements.', 'mj-member'); ?><br>
                                <?php esc_html_e('Ces valeurs servent de base et peuvent être personnalisées par événement.', 'mj-member'); ?>
                            </p>
                        </div>

                        <div style="margin-bottom:24px;">
                            <label for="mj-regdoc-header"><strong><?php esc_html_e('En-tête du document', 'mj-member'); ?></strong></label>
                            <p style="color:#6b7280; font-size:13px; margin:4px 0 8px 0;">
                                <?php esc_html_e('Contenu affiché en haut de chaque document d\'inscription. Vous pouvez y placer un logo, le nom de l\'association, etc.', 'mj-member'); ?>
                            </p>
                            <?php
                            wp_editor(
                                get_option('mj_regdoc_header', ''),
                                'mj_regdoc_header',
                                array(
                                    'textarea_name' => 'mj_regdoc_header',
                                    'textarea_rows' => 8,
                                    'media_buttons' => true,
                                    'teeny' => false,
                                    'quicktags' => true,
                                    'tinymce' => array(
                                        'plugins' => 'table,lists,link,image,paste,wordpress,wplink,hr,code',
                                        'toolbar1' => 'formatselect,fontsizeselect,lineheightselect,bold,italic,underline,bullist,numlist,blockquote,alignleft,aligncenter,alignright,link,unlink,table,hr,fullscreen,code,wp_adv',
                                        'toolbar2' => 'strikethrough,forecolor,backcolor,pastetext,removeformat,charmap,outdent,indent,undo,redo,wp_help',
                                        'fontsize_formats' => '8px 10px 12px 14px 16px 18px 20px 24px 28px 32px 36px 48px 72px',
                                    ),
                                )
                            );
                            ?>
                        </div>

                        <div style="margin-bottom:24px;">
                            <label for="mj-regdoc-footer"><strong><?php esc_html_e('Pied de page du document', 'mj-member'); ?></strong></label>
                            <p style="color:#6b7280; font-size:13px; margin:4px 0 8px 0;">
                                <?php esc_html_e('Contenu affiché en bas de chaque document d\'inscription. Idéal pour les mentions légales, les coordonnées, etc.', 'mj-member'); ?>
                            </p>
                            <?php
                            wp_editor(
                                get_option('mj_regdoc_footer', ''),
                                'mj_regdoc_footer',
                                array(
                                    'textarea_name' => 'mj_regdoc_footer',
                                    'textarea_rows' => 8,
                                    'media_buttons' => true,
                                    'teeny' => false,
                                    'quicktags' => true,
                                    'tinymce' => array(
                                        'plugins' => 'table,lists,link,image,paste,wordpress,wplink,hr,code',
                                        'toolbar1' => 'formatselect,fontsizeselect,lineheightselect,bold,italic,underline,bullist,numlist,blockquote,alignleft,aligncenter,alignright,link,unlink,table,hr,fullscreen,code,wp_adv',
                                        'toolbar2' => 'strikethrough,forecolor,backcolor,pastetext,removeformat,charmap,outdent,indent,undo,redo,wp_help',
                                        'fontsize_formats' => '8px 10px 12px 14px 16px 18px 20px 24px 28px 32px 36px 48px 72px',
                                    ),
                                )
                            );
                            ?>
                        </div>

                        <div style="margin-top:24px; padding:16px; border:1px dashed #86efac; border-radius:8px; background:#fff; color:#334155;">
                            <p style="margin:0 0 6px 0;"><strong><?php esc_html_e('Variables disponibles', 'mj-member'); ?></strong></p>
                            <p style="margin:0 0 8px 0; color:#6b7280; font-size:13px;">
                                <?php esc_html_e('Vous pouvez utiliser ces variables dans l\'en-tête et le pied de page. Elles seront remplacées par les valeurs correspondantes lors de la génération.', 'mj-member'); ?>
                            </p>
                            <div style="display:flex; flex-wrap:wrap; gap:24px;">
                                <div>
                                    <p style="margin:0 0 6px 0; font-weight:600; font-size:13px;"><?php esc_html_e('Événement', 'mj-member'); ?></p>
                                    <ul style="margin:0 0 0 18px; padding:0; list-style:disc; color:#475569; font-family:monospace; font-size:12px;">
                                        <li>[event_name] — <?php esc_html_e('Nom de l\'événement', 'mj-member'); ?></li>
                                        <li>[event_type] — <?php esc_html_e('Type d\'événement', 'mj-member'); ?></li>
                                        <li>[event_status] — <?php esc_html_e('Statut', 'mj-member'); ?></li>
                                        <li>[event_date_start] — <?php esc_html_e('Date de début', 'mj-member'); ?></li>
                                        <li>[event_date_end] — <?php esc_html_e('Date de fin', 'mj-member'); ?></li>
                                        <li>[event_date_deadline] — <?php esc_html_e('Date limite d\'inscription', 'mj-member'); ?></li>
                                        <li>[event_price] — <?php esc_html_e('Tarif', 'mj-member'); ?></li>
                                        <li>[event_location] — <?php esc_html_e('Lieu', 'mj-member'); ?></li>
                                        <li>[event_location_address] — <?php esc_html_e('Adresse du lieu', 'mj-member'); ?></li>
                                        <li>[event_age_min] — <?php esc_html_e('Âge minimum', 'mj-member'); ?></li>
                                        <li>[event_age_max] — <?php esc_html_e('Âge maximum', 'mj-member'); ?></li>
                                        <li>[event_capacity] — <?php esc_html_e('Capacité totale', 'mj-member'); ?></li>
                                    </ul>
                                </div>
                                <div>
                                    <p style="margin:0 0 6px 0; font-weight:600; font-size:13px;"><?php esc_html_e('Membre', 'mj-member'); ?></p>
                                    <ul style="margin:0 0 0 18px; padding:0; list-style:disc; color:#475569; font-family:monospace; font-size:12px;">
                                        <li>[member_name] — <?php esc_html_e('Nom complet', 'mj-member'); ?></li>
                                        <li>[member_first_name] — <?php esc_html_e('Prénom', 'mj-member'); ?></li>
                                        <li>[member_last_name] — <?php esc_html_e('Nom de famille', 'mj-member'); ?></li>
                                        <li>[member_email] — <?php esc_html_e('Email', 'mj-member'); ?></li>
                                        <li>[member_phone] — <?php esc_html_e('Téléphone', 'mj-member'); ?></li>
                                        <li>[member_birth_date] — <?php esc_html_e('Date de naissance', 'mj-member'); ?></li>
                                        <li>[member_address] — <?php esc_html_e('Adresse complète', 'mj-member'); ?></li>
                                        <li>[member_address_line] — <?php esc_html_e('Rue', 'mj-member'); ?></li>
                                        <li>[member_postal_code] — <?php esc_html_e('Code postal', 'mj-member'); ?></li>
                                        <li>[member_city] — <?php esc_html_e('Ville', 'mj-member'); ?></li>
                                    </ul>
                                </div>
                                <div>
                                    <p style="margin:0 0 6px 0; font-weight:600; font-size:13px;"><?php esc_html_e('Tuteur', 'mj-member'); ?></p>
                                    <ul style="margin:0 0 0 18px; padding:0; list-style:disc; color:#475569; font-family:monospace; font-size:12px;">
                                        <li>[guardian_name] — <?php esc_html_e('Nom complet', 'mj-member'); ?></li>
                                        <li>[guardian_first_name] — <?php esc_html_e('Prénom', 'mj-member'); ?></li>
                                        <li>[guardian_last_name] — <?php esc_html_e('Nom de famille', 'mj-member'); ?></li>
                                        <li>[guardian_email] — <?php esc_html_e('Email', 'mj-member'); ?></li>
                                        <li>[guardian_phone] — <?php esc_html_e('Téléphone', 'mj-member'); ?></li>
                                        <li>[guardian_address] — <?php esc_html_e('Adresse complète', 'mj-member'); ?></li>
                                        <li>[guardian_address_line] — <?php esc_html_e('Rue', 'mj-member'); ?></li>
                                        <li>[guardian_postal_code] — <?php esc_html_e('Code postal', 'mj-member'); ?></li>
                                        <li>[guardian_city] — <?php esc_html_e('Ville', 'mj-member'); ?></li>
                                    </ul>
                                </div>
                                <div>
                                    <p style="margin:0 0 6px 0; font-weight:600; font-size:13px;"><?php esc_html_e('Site', 'mj-member'); ?></p>
                                    <ul style="margin:0 0 0 18px; padding:0; list-style:disc; color:#475569; font-family:monospace; font-size:12px;">
                                        <li>[site_name] — <?php esc_html_e('Nom du site', 'mj-member'); ?></li>
                                        <li>[site_url] — <?php esc_html_e('URL du site', 'mj-member'); ?></li>
                                        <li>[current_date] — <?php esc_html_e('Date actuelle', 'mj-member'); ?></li>
                                        <li>[current_year] — <?php esc_html_e('Année actuelle', 'mj-member'); ?></li>
                                    </ul>
                                </div>
                            </div>
                            <p style="margin:12px 0 0 0; color:#6b7280; font-size:12px; font-style:italic;">
                                <?php esc_html_e('Note : Les variables membre génèrent une page par inscrit lors du téléchargement.', 'mj-member'); ?>
                            </p>
                        </div>
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
        /* Styles pour le tri des liens Mon compte */
        .mj-account-links-sortable {
            min-height: 50px;
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .mj-account-link-item {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            transition: box-shadow 0.15s ease, border-color 0.15s ease;
            user-select: none;
        }
        .mj-account-link-item:hover {
            border-color: #a5b4fc;
        }
        .mj-account-link-item.is-dragging {
            opacity: 0.5;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.2);
            border-color: #6366f1;
            cursor: grabbing;
        }
        .mj-account-link-item.is-drag-over-top {
            position: relative;
        }
        .mj-account-link-item.is-drag-over-top::before {
            content: '';
            position: absolute;
            top: -3px;
            left: 0;
            right: 0;
            height: 4px;
            background: #6366f1;
            border-radius: 2px;
            box-shadow: 0 0 8px rgba(99, 102, 241, 0.5);
        }
        .mj-account-link-item.is-drag-over-bottom {
            position: relative;
        }
        .mj-account-link-item.is-drag-over-bottom::after {
            content: '';
            position: absolute;
            bottom: -3px;
            left: 0;
            right: 0;
            height: 4px;
            background: #6366f1;
            border-radius: 2px;
            box-shadow: 0 0 8px rgba(99, 102, 241, 0.5);
        }
        .mj-account-links-sortable.is-dragging-active .mj-account-link-item:not(.is-dragging) {
            cursor: pointer;
        }
        .mj-account-link-header {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 10px;
            cursor: grab;
        }
        .mj-account-link-handle {
            color: #9ca3af;
            font-size: 16px;
            opacity: 0.5;
            transition: opacity 0.15s ease;
        }
        .mj-account-link-item:hover .mj-account-link-handle {
            opacity: 1;
        }
        .mj-account-link-toggle {
            display: flex;
            align-items: center;
        }
        .mj-account-link-toggle input[type="checkbox"] {
            margin: 0;
        }
        .mj-account-link-label {
            flex: 1;
            font-size: 13px;
            font-weight: 500;
            color: #1f2937;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .mj-account-link-label.is-disabled {
            color: #9ca3af;
            text-decoration: line-through;
        }
        .mj-account-link-badge {
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 3px;
            font-weight: 500;
            white-space: nowrap;
        }
        .mj-account-link-badge--animateur {
            background: #d1fae5;
            color: #065f46;
        }
        .mj-account-link-badge--hours {
            background: #fef3c7;
            color: #92400e;
        }
        .mj-account-link-badge--cap {
            background: #ede9fe;
            color: #5b21b6;
        }
        .mj-account-link-position-badge {
            background: #e0e7ff;
            color: #4338ca;
            font-size: 10px;
            font-weight: 600;
            padding: 2px 6px;
            border-radius: 9999px;
            transition: background 0.15s ease;
        }
        .mj-account-link-expand {
            background: none;
            border: none;
            padding: 2px;
            cursor: pointer;
            color: #6b7280;
            display: flex;
            align-items: center;
            transition: transform 0.2s ease;
        }
        .mj-account-link-expand:hover {
            color: #4338ca;
        }
        .mj-account-link-expand[aria-expanded="true"] {
            transform: rotate(180deg);
        }
        .mj-account-link-expand .dashicons {
            font-size: 16px;
            width: 16px;
            height: 16px;
        }
        .mj-account-link-details {
            padding: 10px 12px 12px 36px;
            background: #f9fafb;
            border-top: 1px solid #e5e7eb;
        }
        .mj-account-link-details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 10px;
            align-items: end;
        }
        .mj-account-link-field label {
            display: block;
            font-size: 11px;
            font-weight: 500;
            color: #6b7280;
            margin-bottom: 3px;
        }
        .mj-account-link-field input[type="text"],
        .mj-account-link-field select {
            width: 100%;
            font-size: 12px;
            padding: 4px 8px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
        }
        .mj-account-link-field--icon {
            min-width: 100px;
        }
        .mj-member-menu-icon-control--compact {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .mj-member-menu-icon-control--compact .mj-member-menu-icon-preview {
            width: 28px;
            height: 28px;
            min-width: 28px;
            border-radius: 4px;
            overflow: hidden;
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .mj-member-menu-icon-control--compact .mj-member-menu-icon-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .mj-member-menu-icon-control--compact .mj-member-menu-icon-placeholder {
            font-size: 9px;
            color: #9ca3af;
        }
        .mj-member-menu-icon-control--compact .mj-member-menu-icon-actions {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .mj-member-menu-icon-control--compact .button-small {
            font-size: 11px;
            padding: 0 6px;
            min-height: 24px;
            line-height: 22px;
        }
        .mj-member-menu-icon-control--compact .button-link-delete {
            font-size: 14px;
            color: #dc2626;
            padding: 0 4px;
        }
        .mj-account-link-note {
            margin: 8px 0 0;
            font-size: 11px;
            color: #6b7280;
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
                    title: '<?php echo esc_js(__('Choisir l\'image par défaut', 'mj-member')); ?>',
                    library: { type: 'image' },
                    button: { text: '<?php echo esc_js(__('Utiliser cette image', 'mj-member')); ?>' },
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

            renderPreview(preview.find('img').length ? preview.find('img').attr('src') : '');

            var cardFrame;
            var cardSelectButton = $('#mj-card-background-select');
            var cardClearButton = $('#mj-card-background-clear');
            var cardPreview = $('#mj-card-background-preview');
            var cardInput = $('#mj-card-background-id');
            var cardImage = cardPreview.find('img');
            var cardPlaceholder = cardPreview.find('.mj-card-background-preview__placeholder');

            function renderCardPreview(url) {
                if (url) {
                    cardImage.attr('src', url).show();
                    cardPlaceholder.hide();
                    cardClearButton.show();
                } else {
                    cardImage.attr('src', '').hide();
                    cardPlaceholder.show();
                    cardClearButton.hide();
                }
            }

            cardSelectButton.on('click', function (event) {
                event.preventDefault();

                if (cardFrame) {
                    cardFrame.open();
                    return;
                }

                cardFrame = wp.media({
                    title: '<?php echo esc_js(__('Choisir une image de fond', 'mj-member')); ?>',
                    library: { type: 'image' },
                    button: { text: '<?php echo esc_js(__('Utiliser cette image', 'mj-member')); ?>' },
                    multiple: false
                });

                cardFrame.on('select', function () {
                    var attachment = cardFrame.state().get('selection').first().toJSON();
                    var imageUrl = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;
                    cardInput.val(attachment.id);
                    renderCardPreview(imageUrl);
                });

                cardFrame.open();
            });

            cardClearButton.on('click', function (event) {
                event.preventDefault();
                cardInput.val('');
                renderCardPreview('');
            });

            var cardDoubleCheckbox = $('#mj-card-double-sided');
            var cardBackWrapper = $('#mj-card-back-background-wrapper');

            function refreshCardBackVisibility() {
                if (!cardBackWrapper.length) {
                    return;
                }

                if (cardDoubleCheckbox.is(':checked')) {
                    cardBackWrapper.stop(true, true).slideDown(150);
                } else {
                    cardBackWrapper.stop(true, true).slideUp(150);
                }
            }

            cardDoubleCheckbox.on('change', refreshCardBackVisibility);

            var cardBackFrame;
            var cardBackSelectButton = $('#mj-card-back-background-select');
            var cardBackClearButton = $('#mj-card-back-background-clear');
            var cardBackPreview = $('#mj-card-back-background-preview');
            var cardBackInput = $('#mj-card-back-background-id');
            var cardBackImage = cardBackPreview.find('img');
            var cardBackPlaceholder = cardBackPreview.find('.mj-card-back-background-preview__placeholder');

            function renderCardBackPreview(url) {
                if (url) {
                    cardBackImage.attr('src', url).show();
                    cardBackPlaceholder.hide();
                    cardBackClearButton.show();
                } else {
                    cardBackImage.attr('src', '').hide();
                    cardBackPlaceholder.show();
                    cardBackClearButton.hide();
                }
            }

            cardBackSelectButton.on('click', function (event) {
                event.preventDefault();

                if (cardBackFrame) {
                    cardBackFrame.open();
                    return;
                }

                cardBackFrame = wp.media({
                    title: '<?php echo esc_js(__('Choisir une image de verso', 'mj-member')); ?>',
                    library: { type: 'image' },
                    button: { text: '<?php echo esc_js(__('Utiliser cette image', 'mj-member')); ?>' },
                    multiple: false
                });

                cardBackFrame.on('select', function () {
                    var attachment = cardBackFrame.state().get('selection').first().toJSON();
                    var imageUrl = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;
                    cardBackInput.val(attachment.id);
                    renderCardBackPreview(imageUrl);
                });

                cardBackFrame.open();
            });

            cardBackClearButton.on('click', function (event) {
                event.preventDefault();
                cardBackInput.val('');
                renderCardBackPreview('');
            });

            renderCardPreview(cardImage.length && cardImage.attr('src') ? cardImage.attr('src') : '');
            renderCardBackPreview(cardBackImage.length && cardBackImage.attr('src') ? cardBackImage.attr('src') : '');
            refreshCardBackVisibility();
        })(jQuery);

        // Drag-and-drop pour les liens Mon compte
        (function () {
            var sortableContainer = document.getElementById('mj-account-links-sortable');
            if (!sortableContainer) {
                return;
            }

            var draggedItem = null;
            var items = sortableContainer.querySelectorAll('.mj-account-link-item');

            function updatePositions() {
                var currentItems = sortableContainer.querySelectorAll('.mj-account-link-item');
                currentItems.forEach(function (item, index) {
                    var positionInput = item.querySelector('.mj-account-link-position');
                    var positionBadge = item.querySelector('.mj-account-link-position-badge');
                    if (positionInput) {
                        positionInput.value = index;
                    }
                    if (positionBadge) {
                        positionBadge.textContent = '#' + (index + 1);
                    }
                });
            }

            function clearDropIndicators() {
                sortableContainer.querySelectorAll('.mj-account-link-item').forEach(function (item) {
                    item.classList.remove('is-drag-over-top', 'is-drag-over-bottom');
                });
            }

            function handleDragStart(e) {
                draggedItem = this;
                this.classList.add('is-dragging');
                sortableContainer.classList.add('is-dragging-active');
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', this.dataset.linkKey);
            }

            function handleDragEnd(e) {
                this.classList.remove('is-dragging');
                sortableContainer.classList.remove('is-dragging-active');
                clearDropIndicators();
                draggedItem = null;
                updatePositions();
            }

            function handleDragOver(e) {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';

                if (!draggedItem || draggedItem === this) {
                    clearDropIndicators();
                    return false;
                }

                var rect = this.getBoundingClientRect();
                var midY = rect.top + rect.height / 2;
                var isTop = e.clientY < midY;

                clearDropIndicators();
                if (isTop) {
                    this.classList.add('is-drag-over-top');
                } else {
                    this.classList.add('is-drag-over-bottom');
                }

                return false;
            }

            function handleDragLeave(e) {
                // Ne retirer l'indicateur que si on quitte vraiment l'élément
                var rect = this.getBoundingClientRect();
                if (e.clientX < rect.left || e.clientX > rect.right || e.clientY < rect.top || e.clientY > rect.bottom) {
                    this.classList.remove('is-drag-over-top', 'is-drag-over-bottom');
                }
            }

            function handleDrop(e) {
                e.stopPropagation();
                e.preventDefault();

                if (draggedItem && draggedItem !== this) {
                    var rect = this.getBoundingClientRect();
                    var midY = rect.top + rect.height / 2;
                    var insertBefore = e.clientY < midY;

                    if (insertBefore) {
                        sortableContainer.insertBefore(draggedItem, this);
                    } else {
                        sortableContainer.insertBefore(draggedItem, this.nextSibling);
                    }
                }

                clearDropIndicators();
                return false;
            }

            items.forEach(function (item) {
                var header = item.querySelector('.mj-account-link-header');
                if (header) {
                    header.setAttribute('draggable', 'true');
                    header.addEventListener('dragstart', handleDragStart.bind(item), false);
                    header.addEventListener('dragend', handleDragEnd.bind(item), false);
                }
                item.addEventListener('dragover', handleDragOver, false);
                item.addEventListener('dragleave', handleDragLeave, false);
                item.addEventListener('drop', handleDrop, false);
            });

            // Accordéon expand/collapse
            sortableContainer.addEventListener('click', function (e) {
                var expandBtn = e.target.closest('.mj-account-link-expand');
                if (!expandBtn) return;

                e.preventDefault();
                e.stopPropagation();

                var item = expandBtn.closest('.mj-account-link-item');
                var details = item.querySelector('.mj-account-link-details');
                var isExpanded = expandBtn.getAttribute('aria-expanded') === 'true';

                expandBtn.setAttribute('aria-expanded', !isExpanded);
                if (isExpanded) {
                    details.hidden = true;
                } else {
                    details.hidden = false;
                }
            });

            // Mise à jour du label en temps réel
            sortableContainer.addEventListener('input', function (e) {
                if (e.target.matches('input[name*="[label]"]')) {
                    var item = e.target.closest('.mj-account-link-item');
                    var labelSpan = item.querySelector('.mj-account-link-label');
                    if (labelSpan) {
                        labelSpan.textContent = e.target.value || '(sans nom)';
                    }
                }
            });

            // Toggle enabled/disabled style
            sortableContainer.addEventListener('change', function (e) {
                if (e.target.matches('input[type="checkbox"][name*="[enabled]"]')) {
                    var item = e.target.closest('.mj-account-link-item');
                    var labelSpan = item.querySelector('.mj-account-link-label');
                    if (labelSpan) {
                        labelSpan.classList.toggle('is-disabled', !e.target.checked);
                    }
                }
            });

            // Initialize positions on load
            updatePositions();
        })();

        // Export/Import des pages Mon compte
        (function ($) {
            var exportBtn = document.getElementById('mj-export-pages-btn');
            var importBtn = document.getElementById('mj-import-pages-btn');
            var statusEl = document.getElementById('mj-pages-export-status');

            function setStatus(message, type) {
                if (!statusEl) return;
                statusEl.textContent = message;
                statusEl.style.color = type === 'error' ? '#dc2626' : (type === 'success' ? '#059669' : '#6b7280');
            }

            function setLoading(btn, loading) {
                if (!btn) return;
                btn.disabled = loading;
                btn.style.opacity = loading ? '0.6' : '1';
            }

            if (exportBtn) {
                exportBtn.addEventListener('click', function () {
                    setLoading(exportBtn, true);
                    setStatus('<?php echo esc_js(__('Sauvegarde en cours...', 'mj-member')); ?>', 'info');

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'mj_member_export_account_pages',
                            _wpnonce: '<?php echo esc_js(wp_create_nonce('mj_member_export_pages')); ?>'
                        },
                        success: function (response) {
                            setLoading(exportBtn, false);
                            if (response.success) {
                                var count = response.data.saved ? response.data.saved.length : 0;
                                setStatus('✓ ' + count + ' <?php echo esc_js(__('page(s) sauvegardée(s)', 'mj-member')); ?>', 'success');
                            } else {
                                setStatus('✗ ' + (response.data || '<?php echo esc_js(__('Erreur', 'mj-member')); ?>'), 'error');
                            }
                        },
                        error: function () {
                            setLoading(exportBtn, false);
                            setStatus('✗ <?php echo esc_js(__('Erreur de connexion', 'mj-member')); ?>', 'error');
                        }
                    });
                });
            }

            if (importBtn) {
                importBtn.addEventListener('click', function () {
                    if (!confirm('<?php echo esc_js(__('Créer les pages manquantes à partir des sauvegardes ?', 'mj-member')); ?>')) {
                        return;
                    }

                    setLoading(importBtn, true);
                    setStatus('<?php echo esc_js(__('Restauration en cours...', 'mj-member')); ?>', 'info');

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'mj_member_import_account_pages',
                            _wpnonce: '<?php echo esc_js(wp_create_nonce('mj_member_import_pages')); ?>'
                        },
                        success: function (response) {
                            setLoading(importBtn, false);
                            if (response.success) {
                                var created = response.data.created ? response.data.created.length : 0;
                                var skipped = response.data.skipped ? response.data.skipped.length : 0;
                                setStatus('✓ ' + created + ' <?php echo esc_js(__('créée(s)', 'mj-member')); ?>, ' + skipped + ' <?php echo esc_js(__('existante(s)', 'mj-member')); ?>', 'success');
                                if (created > 0) {
                                    setTimeout(function () { location.reload(); }, 1500);
                                }
                            } else {
                                setStatus('✗ ' + (response.data || '<?php echo esc_js(__('Erreur', 'mj-member')); ?>'), 'error');
                            }
                        },
                        error: function () {
                            setLoading(importBtn, false);
                            setStatus('✗ <?php echo esc_js(__('Erreur de connexion', 'mj-member')); ?>', 'error');
                        }
                    });
                });
            }
        })(jQuery);
        </script>
    </div>
    <?php
}

/**
 * AJAX : Export des pages Mon compte vers fichiers JSON.
 */
add_action('wp_ajax_mj_member_export_account_pages', 'mj_member_ajax_export_account_pages');
function mj_member_ajax_export_account_pages(): void {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('Permissions insuffisantes', 'mj-member'));
    }

    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'mj_member_export_pages')) {
        wp_send_json_error(__('Nonce invalide', 'mj-member'));
    }

    if (!class_exists('Mj\\Member\\Classes\\MjAccountPagesExport')) {
        require_once MJ_MEMBER_PATH . 'includes/classes/MjAccountPagesExport.php';
    }

    $result = \Mj\Member\Classes\MjAccountPagesExport::exportPages();

    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error(implode(', ', $result['errors']));
    }
}

/**
 * AJAX : Import des pages Mon compte depuis fichiers JSON.
 */
add_action('wp_ajax_mj_member_import_account_pages', 'mj_member_ajax_import_account_pages');
function mj_member_ajax_import_account_pages(): void {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('Permissions insuffisantes', 'mj-member'));
    }

    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'mj_member_import_pages')) {
        wp_send_json_error(__('Nonce invalide', 'mj-member'));
    }

    if (!class_exists('Mj\\Member\\Classes\\MjAccountPagesExport')) {
        require_once MJ_MEMBER_PATH . 'includes/classes/MjAccountPagesExport.php';
    }

    $result = \Mj\Member\Classes\MjAccountPagesExport::importPages(false);

    wp_send_json_success($result);
}
