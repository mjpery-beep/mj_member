<?php

use Mj\Member\Classes\MjDatabaseBackup;
use Mj\Member\Core\Config;

require_once __DIR__ . '/settings/editor-tools.php';
require_once __DIR__ . '/settings/ajax-account-pages.php';

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
    if ((isset($_POST['mj_save_settings']) || isset($_POST['mj_events_google_sync_regenerate']) || isset($_POST['mj_events_google_sync_force']) || isset($_POST['mj_backup_run_now'])) && check_admin_referer('mj_settings_nonce')) {
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
        $whatsapp_twilio_from = isset($_POST['mj_whatsapp_twilio_from']) ? sanitize_text_field($_POST['mj_whatsapp_twilio_from']) : '';
        $social_facebook_page_url = isset($_POST['mj_social_facebook_page_url']) ? esc_url_raw(wp_unslash($_POST['mj_social_facebook_page_url'])) : '';
        $social_facebook_page_id = isset($_POST['mj_social_facebook_page_id']) ? sanitize_text_field($_POST['mj_social_facebook_page_id']) : '';
        $social_facebook_page_token = isset($_POST['mj_social_facebook_page_token']) ? sanitize_text_field($_POST['mj_social_facebook_page_token']) : '';
        $social_instagram_page_url = isset($_POST['mj_social_instagram_page_url']) ? esc_url_raw(wp_unslash($_POST['mj_social_instagram_page_url'])) : '';
        $social_instagram_business_id = isset($_POST['mj_social_instagram_business_id']) ? sanitize_text_field($_POST['mj_social_instagram_business_id']) : '';
        $social_instagram_access_token = isset($_POST['mj_social_instagram_access_token']) ? sanitize_text_field($_POST['mj_social_instagram_access_token']) : '';
        $social_whatsapp_group_url = isset($_POST['mj_social_whatsapp_group_url']) ? esc_url_raw(wp_unslash($_POST['mj_social_whatsapp_group_url'])) : '';
        $social_whatsapp_phone_number_id = isset($_POST['mj_social_whatsapp_phone_number_id']) ? sanitize_text_field($_POST['mj_social_whatsapp_phone_number_id']) : '';
        $social_whatsapp_business_id = isset($_POST['mj_social_whatsapp_business_id']) ? sanitize_text_field($_POST['mj_social_whatsapp_business_id']) : '';
        $social_whatsapp_access_token = isset($_POST['mj_social_whatsapp_access_token']) ? sanitize_text_field($_POST['mj_social_whatsapp_access_token']) : '';
        $social_n8n_enabled = isset($_POST['mj_social_n8n_enabled']) ? '1' : '0';
        $social_n8n_webhook_url = isset($_POST['mj_social_n8n_webhook_url']) ? esc_url_raw(wp_unslash($_POST['mj_social_n8n_webhook_url'])) : '';
        $social_n8n_secret = isset($_POST['mj_social_n8n_secret']) ? sanitize_text_field(wp_unslash($_POST['mj_social_n8n_secret'])) : '';
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
            $ai_description_prompt = isset($_POST['mj_ai_description_prompt']) ? sanitize_textarea_field(wp_unslash($_POST['mj_ai_description_prompt'])) : '';
            $ai_social_description_prompt = isset($_POST['mj_ai_social_description_prompt']) ? sanitize_textarea_field(wp_unslash($_POST['mj_ai_social_description_prompt'])) : '';
            $ai_regdoc_prompt      = isset($_POST['mj_ai_regdoc_prompt'])      ? sanitize_textarea_field(wp_unslash($_POST['mj_ai_regdoc_prompt']))      : '';
            $ai_sms_prompt         = isset($_POST['mj_ai_sms_prompt'])         ? sanitize_textarea_field(wp_unslash($_POST['mj_ai_sms_prompt']))         : '';
        $contact_default_signature = isset($_POST['mj_contact_default_signature']) ? wp_kses_post(wp_unslash($_POST['mj_contact_default_signature'])) : '';

        // --- Web Push VAPID settings ---
        $vapid_public_key = isset($_POST['mj_member_vapid_public_key']) ? sanitize_text_field(wp_unslash($_POST['mj_member_vapid_public_key'])) : '';
        $vapid_private_key = isset($_POST['mj_member_vapid_private_key']) ? sanitize_text_field(wp_unslash($_POST['mj_member_vapid_private_key'])) : '';
        $vapid_subject = isset($_POST['mj_member_vapid_subject']) ? sanitize_text_field(wp_unslash($_POST['mj_member_vapid_subject'])) : '';

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
        update_option('mj_whatsapp_twilio_from', $whatsapp_twilio_from);
        update_option('mj_social_facebook_page_url', $social_facebook_page_url);
        update_option('mj_social_facebook_page_id', $social_facebook_page_id);
        update_option('mj_social_facebook_page_token', $social_facebook_page_token);
        update_option('mj_social_instagram_page_url', $social_instagram_page_url);
        update_option('mj_social_instagram_business_id', $social_instagram_business_id);
        update_option('mj_social_instagram_access_token', $social_instagram_access_token);
        update_option('mj_social_whatsapp_group_url', $social_whatsapp_group_url);
        update_option('mj_social_whatsapp_phone_number_id', $social_whatsapp_phone_number_id);
        update_option('mj_social_whatsapp_business_id', $social_whatsapp_business_id);
        update_option('mj_social_whatsapp_access_token', $social_whatsapp_access_token);
        update_option('mj_social_n8n_enabled', $social_n8n_enabled);
        update_option('mj_social_n8n_webhook_url', $social_n8n_webhook_url);
        update_option('mj_social_n8n_secret', $social_n8n_secret);
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
            update_option('mj_member_ai_description_prompt', $ai_description_prompt);
            update_option('mj_member_ai_social_description_prompt', $ai_social_description_prompt);
            update_option('mj_member_ai_regdoc_prompt',      $ai_regdoc_prompt);
            update_option('mj_member_ai_sms_prompt',         $ai_sms_prompt);
        update_option('mj_member_contact_default_signature', $contact_default_signature);

        // Disabled widgets
        $disabled_widgets = isset($_POST['mj_member_disabled_widgets']) && is_array($_POST['mj_member_disabled_widgets'])
            ? array_map('sanitize_text_field', $_POST['mj_member_disabled_widgets'])
            : array();
        update_option('mj_member_disabled_widgets', $disabled_widgets);

        // Web Push VAPID
        update_option('mj_member_vapid_public_key', $vapid_public_key);
        update_option('mj_member_vapid_private_key', $vapid_private_key);
        update_option('mj_member_vapid_subject', $vapid_subject);

        // Mileage settings
        $mileage_cost = isset($_POST['mj_mileage_cost_per_km']) ? floatval($_POST['mj_mileage_cost_per_km']) : 0.4326;
        $mileage_disclaimer = isset($_POST['mj_mileage_disclaimer']) ? sanitize_textarea_field(wp_unslash($_POST['mj_mileage_disclaimer'])) : '';
        $mileage_default_origin = isset($_POST['mj_mileage_default_origin_id']) ? intval($_POST['mj_mileage_default_origin_id']) : 0;
        update_option('mj_mileage_cost_per_km', $mileage_cost);
        update_option('mj_mileage_disclaimer', $mileage_disclaimer);
        update_option('mj_mileage_default_origin_id', $mileage_default_origin);

        // Document d'inscription header/footer
        $regdoc_header = isset($_POST['mj_regdoc_header']) ? wp_kses_post(wp_unslash($_POST['mj_regdoc_header'])) : '';
        $regdoc_footer = isset($_POST['mj_regdoc_footer']) ? wp_kses_post(wp_unslash($_POST['mj_regdoc_footer'])) : '';
        update_option('mj_regdoc_header', $regdoc_header);
        update_option('mj_regdoc_footer', $regdoc_footer);

        // --- Nextcloud settings ---
        $nc_url = isset($_POST['mj_member_nextcloud_url'])
            ? sanitize_text_field(wp_unslash($_POST['mj_member_nextcloud_url']))
            : '';
        $nc_user = isset($_POST['mj_member_nextcloud_user'])
            ? sanitize_text_field(wp_unslash($_POST['mj_member_nextcloud_user']))
            : '';
        $nc_password = isset($_POST['mj_member_nextcloud_password'])
            ? trim((string) wp_unslash($_POST['mj_member_nextcloud_password']))
            : '';
        $nc_root_folder = isset($_POST['mj_member_nextcloud_root_folder'])
            ? sanitize_text_field(wp_unslash($_POST['mj_member_nextcloud_root_folder']))
            : '';
        $nc_groups = isset($_POST['mj_member_nextcloud_groups'])
            ? sanitize_textarea_field(wp_unslash($_POST['mj_member_nextcloud_groups']))
            : '';

        update_option('mj_member_nextcloud_url', $nc_url);
        update_option('mj_member_nextcloud_user', $nc_user);
        if ($nc_password !== '') {
            update_option('mj_member_nextcloud_password', $nc_password);
        }
        update_option('mj_member_nextcloud_root_folder', $nc_root_folder);
        update_option('mj_member_nextcloud_groups', $nc_groups);

        $backup_enabled = isset($_POST['mj_backup_enabled']) ? '1' : '0';
        $backup_frequency_raw = isset($_POST['mj_backup_frequency']) ? sanitize_key(wp_unslash($_POST['mj_backup_frequency'])) : 'daily';
        $backup_frequency = in_array($backup_frequency_raw, array('daily', 'twicedaily', 'weekly'), true)
            ? $backup_frequency_raw
            : 'daily';
        $backup_retention = isset($_POST['mj_backup_retention']) ? max(1, (int) $_POST['mj_backup_retention']) : 7;
        $backup_folder = isset($_POST['mj_backup_nextcloud_folder'])
            ? trim(sanitize_text_field(wp_unslash($_POST['mj_backup_nextcloud_folder'])), "/\\ \t\n\r\0\x0B")
            : 'backups/database';
        if ($backup_folder === '') {
            $backup_folder = 'backups/database';
        }

        update_option('mj_backup_enabled', $backup_enabled);
        update_option('mj_backup_frequency', $backup_frequency);
        update_option('mj_backup_retention', $backup_retention);
        update_option('mj_backup_nextcloud_folder', $backup_folder);

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

        // Traiter les liens par défaut
        foreach ($default_account_links as $link_key => $link_defaults) {
            $raw_row = isset($submitted_account_links[$link_key]) && is_array($submitted_account_links[$link_key])
                ? $submitted_account_links[$link_key]
                : array();

            $enabled = isset($raw_row['enabled']) && (string) $raw_row['enabled'] === '1';
            $label = isset($raw_row['label']) ? sanitize_text_field($raw_row['label']) : '';
            $link_description = isset($raw_row['description']) ? sanitize_text_field($raw_row['description']) : '';
            $page_id = isset($raw_row['page_id']) ? (int) $raw_row['page_id'] : 0;
            $icon_id = isset($raw_row['icon_id']) ? (int) $raw_row['icon_id'] : 0;
            $position = isset($raw_row['position']) ? (int) $raw_row['position'] : 999;
            $visibility = isset($raw_row['visibility']) ? sanitize_key($raw_row['visibility']) : 'all';

            // Sauvegarder le slug de la page pour permettre la portabilité entre sites
            $page_slug = '';
            if ($page_id > 0) {
                $page_post = get_post($page_id);
                if ($page_post && $page_post->post_type === 'page') {
                    $page_slug = $page_post->post_name;
                }
            }

            $notification_types = isset($raw_row['notification_types']) && is_array($raw_row['notification_types'])
                ? array_values(array_filter(array_map('sanitize_key', $raw_row['notification_types'])))
                : array();

            $normalized_account_links[$link_key] = array(
                'enabled' => $enabled ? 1 : 0,
                'label' => (!empty($link_defaults['editable_label']) && $label !== '') ? $label : '',
                'description' => $link_description,
                'page_id' => $page_id > 0 ? $page_id : 0,
                'page_slug' => $page_slug,
                'icon_id' => $icon_id > 0 ? $icon_id : 0,
                'position' => $position,
                'visibility' => $visibility,
                'notification_types' => $notification_types,
            );
        }

        // Traiter les sections personnalisées (custom_section_*)
        foreach ($submitted_account_links as $link_key => $raw_row) {
            // Ignorer les clés qui ont déjà été traitées
            if (isset($normalized_account_links[$link_key])) {
                continue;
            }

            // Traiter uniquement les sections personnalisées
            if (strpos($link_key, 'custom_section_') !== 0) {
                continue;
            }

            if (!is_array($raw_row)) {
                continue;
            }

            $enabled = isset($raw_row['enabled']) && (string) $raw_row['enabled'] === '1';
            $label = isset($raw_row['label']) ? sanitize_text_field($raw_row['label']) : '';
            $section_description = isset($raw_row['description']) ? sanitize_text_field($raw_row['description']) : '';
            $position = isset($raw_row['position']) ? (int) $raw_row['position'] : 999;
            $visibility = isset($raw_row['visibility']) ? sanitize_key($raw_row['visibility']) : 'all';

            if ($label === '') {
                continue;
            }

            $normalized_account_links[$link_key] = array(
                'enabled' => $enabled ? 1 : 0,
                'label' => $label,
                'description' => $section_description,
                'page_id' => 0,
                'page_slug' => '',
                'icon_id' => 0,
                'position' => $position,
                'type' => 'section_header',
                'visibility' => $visibility,
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
        if (isset($_POST['mj_save_settings']) || isset($_POST['mj_events_google_sync_regenerate']) || isset($_POST['mj_events_google_sync_force']) || isset($_POST['mj_backup_run_now'])) {
            $notices[] = array('type' => 'success', 'message' => '✅ Paramètres sauvegardés avec succès');
        }

        if (isset($_POST['mj_backup_run_now'])) {
            if (!class_exists(MjDatabaseBackup::class)) {
                $notices[] = array('type' => 'error', 'message' => '❌ Module de sauvegarde introuvable.');
            } else {
                $backup_result = MjDatabaseBackup::run();

                if (is_wp_error($backup_result)) {
                    $notices[] = array('type' => 'error', 'message' => '❌ Sauvegarde impossible : ' . esc_html($backup_result->get_error_message()));
                } else {
                    $backup_status = MjDatabaseBackup::getLastStatus();
                    $backup_filename = isset($backup_status['filename']) ? (string) $backup_status['filename'] : '';
                    $success_message = '✅ Sauvegarde exécutée avec succès.';
                    if ($backup_filename !== '') {
                        $success_message = sprintf('✅ Sauvegarde exécutée avec succès : %s', $backup_filename);
                    }
                    $notices[] = array('type' => 'success', 'message' => $success_message);
                }
            }
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
    $whatsapp_twilio_from = get_option('mj_whatsapp_twilio_from', '');
    $social_facebook_page_url = get_option('mj_social_facebook_page_url', '');
    $social_facebook_page_id = get_option('mj_social_facebook_page_id', '');
    $social_facebook_page_token = get_option('mj_social_facebook_page_token', '');
    $social_instagram_page_url = get_option('mj_social_instagram_page_url', '');
    $social_instagram_business_id = get_option('mj_social_instagram_business_id', '');
    $social_instagram_access_token = get_option('mj_social_instagram_access_token', '');
    $social_whatsapp_group_url = get_option('mj_social_whatsapp_group_url', '');
    $social_whatsapp_phone_number_id = get_option('mj_social_whatsapp_phone_number_id', '');
    $social_whatsapp_business_id = get_option('mj_social_whatsapp_business_id', '');
    $social_whatsapp_access_token = get_option('mj_social_whatsapp_access_token', '');
    $social_n8n_enabled = get_option('mj_social_n8n_enabled', '0') === '1';
    $social_n8n_webhook_url = get_option('mj_social_n8n_webhook_url', '');
    $social_n8n_secret = get_option('mj_social_n8n_secret', '');
    $social_publish_guide_content = '';
    $social_publish_guide_path = Config::path() . 'SOCIAL_PUBLISH_SETUP.md';
    if (is_readable($social_publish_guide_path)) {
        $social_publish_guide_raw = file_get_contents($social_publish_guide_path);
        if (is_string($social_publish_guide_raw)) {
            $social_publish_guide_content = trim($social_publish_guide_raw);
        }
    }
    $social_publish_guide_html = $social_publish_guide_content !== ''
        ? mj_member_render_markdown_help($social_publish_guide_content)
        : '';
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
        $ai_description_prompt_option = get_option('mj_member_ai_description_prompt', '');
        $ai_social_description_prompt_option = get_option('mj_member_ai_social_description_prompt', '');
        $ai_regdoc_prompt_option      = get_option('mj_member_ai_regdoc_prompt', '');
        $ai_sms_prompt_option         = get_option('mj_member_ai_sms_prompt', '');
    $contact_default_signature_option = get_option('mj_member_contact_default_signature', '');
    $vapid_public_key_option = get_option('mj_member_vapid_public_key', '');
    $vapid_private_key_option = get_option('mj_member_vapid_private_key', '');
    $vapid_subject_option = get_option('mj_member_vapid_subject', '');

    // Mileage settings
    $mileage_cost_option = get_option('mj_mileage_cost_per_km', 0.4326);
    $mileage_disclaimer_option = get_option('mj_mileage_disclaimer', 'Les déplacements de chez vous jusque la MJ sont déjà payés dans votre salaire.');
    $mileage_default_origin_option = (int) get_option('mj_mileage_default_origin_id', 0);
    $mileage_locations = array();
    if (class_exists('Mj\\Member\\Classes\\Crud\\MjEventLocations')) {
        $mileage_locations = \Mj\Member\Classes\Crud\MjEventLocations::get_all(array('orderby' => 'name', 'order' => 'ASC'));
    }

    if (!is_string($photo_grimlins_prompt_option) || $photo_grimlins_prompt_option === '') {
        $photo_grimlins_prompt_option = __('Transforme cette personne en version "Grimlins" fun et stylisée, avec un rendu illustratif détaillé, sans éléments effrayants.', 'mj-member');
    }

    // Nextcloud options
    $nc_url_option = get_option('mj_member_nextcloud_url', '');
    $nc_user_option = get_option('mj_member_nextcloud_user', '');
    $nc_password_option = get_option('mj_member_nextcloud_password', '');
    $nc_root_folder_option = get_option('mj_member_nextcloud_root_folder', '');
    $nc_groups_option = get_option('mj_member_nextcloud_groups', '');
    $nc_groups_resolved = Config::nextcloudGroups();
    $nc_is_ready = Config::nextcloudIsReady();
    $nc_missing_fields = array();
    if (trim((string) $nc_url_option) === '') {
        $nc_missing_fields[] = __('URL Nextcloud', 'mj-member');
    }
    if (trim((string) $nc_user_option) === '') {
        $nc_missing_fields[] = __('Utilisateur de service', 'mj-member');
    }
    if (trim((string) $nc_password_option) === '') {
        $nc_missing_fields[] = __('Mot de passe d\'application', 'mj-member');
    }

    $backup_enabled_option = get_option('mj_backup_enabled', '0') === '1';
    $backup_frequency_option = get_option('mj_backup_frequency', 'daily');
    if (!in_array($backup_frequency_option, array('daily', 'twicedaily', 'weekly'), true)) {
        $backup_frequency_option = 'daily';
    }
    $backup_retention_option = max(1, (int) get_option('mj_backup_retention', 7));
    $backup_folder_option = class_exists(MjDatabaseBackup::class)
        ? MjDatabaseBackup::getBackupFolder()
        : trim((string) get_option('mj_backup_nextcloud_folder', 'backups/database'), '/');
    if ($backup_folder_option === '') {
        $backup_folder_option = 'backups/database';
    }
    $backup_last_status = class_exists(MjDatabaseBackup::class) ? MjDatabaseBackup::getLastStatus() : array();
    $backup_last_run = class_exists(MjDatabaseBackup::class) ? MjDatabaseBackup::getLastRun() : 0;
    $backup_last_run_display = $backup_last_run > 0 ? wp_date('d/m/Y H:i:s', $backup_last_run) : 'Jamais';
    $backup_last_filename = isset($backup_last_status['filename']) ? (string) $backup_last_status['filename'] : '';
    $backup_last_message = isset($backup_last_status['message']) ? (string) $backup_last_status['message'] : 'Aucune exécution enregistrée.';
    $backup_last_success = !empty($backup_last_status['success']);
    $backup_table_count = class_exists(MjDatabaseBackup::class) ? count(MjDatabaseBackup::getMjTables()) : 0;

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

    // Préparer les types de notification groupés par catégorie pour le multi-select
    $notification_type_groups = array();
    $all_labels = array();
    if (class_exists('MjNotificationTypes')) {
        $all_labels = MjNotificationTypes::get_labels();
        $notification_type_groups = array(
            __('Événements', 'mj-member') => array(
                'event_registration_created', 'event_registration_cancelled',
                'event_reminder', 'event_new_published',
            ),
            __('Paiements', 'mj-member') => array(
                'payment_completed', 'payment_reminder',
            ),
            __('Tâches', 'mj-member') => array(
                'todo_assigned', 'todo_completed', 'todo_note_added', 'todo_media_added',
            ),
            __('Messages', 'mj-member') => array(
                'message_received',
            ),
            __('Membres', 'mj-member') => array(
                'member_created', 'member_profile_updated',
            ),
            __('Photos', 'mj-member') => array(
                'photo_uploaded', 'photo_approved',
            ),
            __('Idées', 'mj-member') => array(
                'idea_published', 'idea_voted',
            ),
            __('Témoignages', 'mj-member') => array(
                'testimonial_new_pending', 'testimonial_approved', 'testimonial_rejected',
                'testimonial_comment', 'testimonial_comment_reply', 'testimonial_reaction',
            ),
            __('Gamification', 'mj-member') => array(
                'trophy_earned', 'badge_earned', 'criterion_earned', 'level_up', 'action_awarded',
            ),
            __('Congés', 'mj-member') => array(
                'leave_request_created', 'leave_request_approved', 'leave_request_rejected',
            ),
            __('Notes de frais', 'mj-member') => array(
                'expense_created', 'expense_reimbursed', 'expense_rejected',
            ),
            __('Frais kilométriques', 'mj-member') => array(
                'mileage_created', 'mileage_approved', 'mileage_reimbursed',
            ),
            __('Documents employé', 'mj-member') => array(
                'employee_document_uploaded',
            ),
            __('Divers', 'mj-member') => array(
                'avatar_applied', 'attendance_recorded', 'post_published',
            ),
        );
        // Filtrer les types qui n'existent pas dans les labels
        foreach ($notification_type_groups as $group_label => $types) {
            $notification_type_groups[$group_label] = array_filter($types, function ($t) use ($all_labels) {
                return isset($all_labels[$t]);
            });
            if (empty($notification_type_groups[$group_label])) {
                unset($notification_type_groups[$group_label]);
            }
        }
    }

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

    $settings_template_path = __DIR__ . '/settings/page-template.php';
    if (is_readable($settings_template_path)) {
        include $settings_template_path;
        return;
    }

    echo '<div class="wrap">';
    echo '<h1>Configuration MJ Pery</h1>';
    echo '<div class="notice notice-error"><p>';
    echo 'Le template des parametres est introuvable: <code>' . esc_html($settings_template_path) . '</code>. '; 
    echo 'Deployez le fichier <code>includes/settings/page-template.php</code> pour restaurer cet ecran.';
    echo '</p></div>';
    echo '</div>';
}

