<?php

if (!defined('ABSPATH')) {
    exit;
}


if (!function_exists('mj_member_ajax_update_event_assignments')) {
    function mj_member_ajax_update_event_assignments() {
        if (!wp_doing_ajax()) {
            return;
        }

        check_ajax_referer('mj-member-event-register', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(
                array('message' => __('Vous devez être connecté pour gérer vos inscriptions.', 'mj-member')),
                401
            );
        }

        $event_id = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;
        $member_id = isset($_POST['member_id']) ? (int) $_POST['member_id'] : 0;
        $registration_id = isset($_POST['registration_id']) ? (int) $_POST['registration_id'] : 0;

        if ($event_id <= 0 || $member_id <= 0) {
            wp_send_json_error(
                array('message' => __('Requête invalide. Veuillez réessayer.', 'mj-member')),
                400
            );
        }

        if (!class_exists('MjEvents_CRUD') || !class_exists('MjEventRegistrations') || !class_exists('MjEventAttendance')) {
            wp_send_json_error(
                array('message' => __('Le module événements est indisponible pour le moment.', 'mj-member')),
                500
            );
        }

        $event = MjEvents_CRUD::find($event_id);
        if (!$event) {
            wp_send_json_error(
                array('message' => __('Événement introuvable.', 'mj-member')),
                404
            );
        }

        $schedule_mode = isset($event->schedule_mode) ? sanitize_key((string) $event->schedule_mode) : 'fixed';
        if (!in_array($schedule_mode, array('fixed', 'range', 'recurring'), true)) {
            $schedule_mode = 'fixed';
        }
        if ($schedule_mode !== 'recurring') {
            wp_send_json_error(
                array('message' => __('Ce format ne propose pas de gestion des occurrences.', 'mj-member')),
                400
            );
        }

        $current_member = function_exists('mj_member_get_current_member') ? mj_member_get_current_member() : null;
        if (!$current_member || empty($current_member->id)) {
            wp_send_json_error(
                array('message' => __('Votre profil membre est introuvable. Contactez l’équipe MJ.', 'mj-member')),
                403
            );
        }

        $allowed_member_ids = array((int) $current_member->id);

        if (function_exists('mj_member_can_manage_children') && mj_member_can_manage_children($current_member)) {
            if (function_exists('mj_member_get_guardian_children')) {
                $children = mj_member_get_guardian_children($current_member);
                if (!empty($children) && is_array($children)) {
                    foreach ($children as $child) {
                        if (!$child || !isset($child->id)) {
                            continue;
                        }
                        $allowed_member_ids[] = (int) $child->id;
                    }
                }
            }
        } elseif (!empty($current_member->guardian_id)) {
            $allowed_member_ids[] = (int) $current_member->guardian_id;
        }

        if (!in_array($member_id, $allowed_member_ids, true)) {
            wp_send_json_error(
                array('message' => __('Vous ne pouvez pas gérer ce participant.', 'mj-member')),
                403
            );
        }

        $existing_registration = MjEventRegistrations::get_existing($event_id, $member_id);
        if (!$existing_registration || (isset($existing_registration->statut) && $existing_registration->statut === MjEventRegistrations::STATUS_CANCELLED)) {
            wp_send_json_error(
                array('message' => __('Aucune inscription active à mettre à jour.', 'mj-member')),
                404
            );
        }

        if ($registration_id > 0 && (int) $existing_registration->id !== $registration_id) {
            wp_send_json_error(
                array('message' => __('Inscription introuvable.', 'mj-member')),
                404
            );
        }

        $occurrence_selection = array();
        if (isset($_POST['occurrences'])) {
            $occurrences_raw = wp_unslash($_POST['occurrences']);
            $decoded_occurrences = json_decode($occurrences_raw, true);
            if (is_array($decoded_occurrences)) {
                foreach ($decoded_occurrences as $occurrence_entry) {
                    if (!is_string($occurrence_entry) && !is_numeric($occurrence_entry)) {
                        continue;
                    }

                    $normalized_value = sanitize_text_field((string) $occurrence_entry);
                    if ($normalized_value === '') {
                        continue;
                    }

                    $candidate_normalized = MjEventAttendance::normalize_occurrence($normalized_value);
                    if ($candidate_normalized === '') {
                        continue;
                    }

                    $occurrence_selection[$candidate_normalized] = $candidate_normalized;
                }
            }
        }

        $assignments_payload = array(
            'mode' => 'all',
            'occurrences' => array(),
        );

        if (!empty($occurrence_selection)) {
            $assignments_payload = array(
                'mode' => 'custom',
                'occurrences' => array_values($occurrence_selection),
            );
        }

        $update = MjEventAttendance::set_registration_assignments((int) $existing_registration->id, $assignments_payload);
        if (is_wp_error($update)) {
            wp_send_json_error(
                array('message' => $update->get_error_message()),
                500
            );
        }

        $updated_registration = MjEventRegistrations::get((int) $existing_registration->id);
        $current_assignments = $assignments_payload;
        if ($updated_registration) {
            $updated_assignments = MjEventAttendance::get_registration_assignments($updated_registration);
            if (is_array($updated_assignments) && !empty($updated_assignments)) {
                $current_assignments = $updated_assignments;
            }
        }

        wp_send_json_success(
            array(
                'message' => __('Occurrences mises à jour.', 'mj-member'),
                'assignments' => $current_assignments,
            )
        );
    }

    add_action('wp_ajax_mj_member_update_event_assignments', 'mj_member_ajax_update_event_assignments');
    add_action('wp_ajax_nopriv_mj_member_update_event_assignments', 'mj_member_ajax_update_event_assignments');
}
if (!class_exists('MjEventAnimateurs') && file_exists(MJ_MEMBER_PATH . 'includes/classes/crud/MjEventAnimateurs.php')) {
    require_once MJ_MEMBER_PATH . 'includes/classes/crud/MjEventAnimateurs.php';
}

if (!class_exists('MjEventLocations') && file_exists(MJ_MEMBER_PATH . 'includes/classes/crud/MjEventLocations.php')) {
    require_once MJ_MEMBER_PATH . 'includes/classes/crud/MjEventLocations.php';
}

if (!function_exists('mj_member_register_events_widget_assets')) {
    function mj_member_register_events_widget_assets() {
        $version = defined('MJ_MEMBER_VERSION') ? MJ_MEMBER_VERSION : '1.0.0';

        wp_register_script(
            'mj-member-events-widget',
            MJ_MEMBER_URL . 'js/events-widget.js',
            array(),
            $version,
            true
        );
    }
    add_action('init', 'mj_member_register_events_widget_assets', 8);
}

if (!function_exists('mj_member_output_events_widget_styles')) {
    function mj_member_output_events_widget_styles() {
        static $printed = false;
        if ($printed) {
            return;
        }
        $printed = true;

        echo '<style>'
            . '.mj-member-events{display:flex;flex-direction:column;gap:24px;--mj-events-title-color:#0f172a;--mj-events-card-bg:#ffffff;--mj-events-border:#e3e6ea;--mj-events-border-soft:#e2e8f0;--mj-events-card-title:#0f172a;--mj-events-meta:#4b5563;--mj-events-excerpt:#475569;--mj-events-accent:#2563eb;--mj-events-accent-contrast:#ffffff;--mj-events-radius:14px;--mj-events-button-bg:#2563eb;--mj-events-button-hover:#1d4ed8;--mj-events-button-text:#ffffff;--mj-events-button-border:#2563eb;--mj-events-button-radius:999px;--mj-events-surface-soft:#f8fafc;}'
            . '.mj-member-events__title{margin:0;font-size:1.75rem;font-weight:700;color:var(--mj-events-title-color);}'
            . '.mj-member-events__grid{display:grid;gap:20px;}'
            . '.mj-member-events__grid.is-grid{grid-template-columns:repeat(auto-fit,minmax(240px,1fr));}'
            . '.mj-member-events__grid.is-list{grid-template-columns:1fr;}'
            . '.mj-member-events__item{border:1px solid var(--mj-events-border);border-radius:var(--mj-events-radius);overflow:hidden;background:var(--mj-events-card-bg);display:flex;flex-direction:column;transition:box-shadow 0.2s ease,transform 0.2s ease;}'
            . '.mj-member-events__item.layout-horizontal{flex-direction:row;}'
            . '.mj-member-events__item.layout-horizontal .mj-member-events__item-body{flex:1;}'
            . '.mj-member-events__item.layout-compact{border-radius:calc(var(--mj-events-radius) - 2px);}'
            . '.mj-member-events__item:hover{box-shadow:0 18px 40px rgba(15,23,42,0.12);transform:translateY(-2px);}'
            . '.mj-member-events__cover{position:relative;padding-bottom:56%;overflow:hidden;background:var(--mj-events-surface-soft);}'
            . '.mj-member-events__cover.ratio-4-3{padding-bottom:75%;}'
            . '.mj-member-events__cover.ratio-1-1{padding-bottom:100%;}'
            . '.mj-member-events__cover.ratio-auto{padding-bottom:0;min-height:200px;}'
            . '.mj-member-events__cover.is-horizontal{flex:0 0 280px;padding-bottom:0;min-height:220px;}'
            . '.mj-member-events__cover img{position:absolute;top:0;left:0;width:100%;height:100%;object-fit:cover;}'
            . '.mj-member-events__cover.is-horizontal img{position:static;height:100%;}'
            . '.mj-member-events__item-body{display:flex;flex-direction:column;gap:12px;padding:20px;}'
            . '.mj-member-events__item.layout-compact .mj-member-events__item-body{padding:16px;gap:8px;}'
            . '.mj-member-events__item.layout-compact .mj-member-events__meta{font-size:0.85rem;}'
            . '.mj-member-events__item-title{margin:0;font-size:1.1rem;font-weight:700;color:var(--mj-events-card-title);}'
            . '.mj-member-events__item-title a{text-decoration:none;color:inherit;}'
            . '.mj-member-events__item-title a:hover{color:var(--mj-events-accent);}'
            . '.mj-member-events__meta{font-size:0.9rem;color:var(--mj-events-meta);display:flex;flex-wrap:wrap;gap:8px;}'
            . '.mj-member-events__excerpt{margin:0;color:var(--mj-events-excerpt);font-size:0.95rem;line-height:1.5;}'
            . '.mj-member-events__detail-link{display:inline-flex;align-items:center;gap:6px;font-weight:600;color:var(--mj-events-accent);text-decoration:none;font-size:0.9rem;}'
            . '.mj-member-events__detail-link:hover,.mj-member-events__detail-link:focus{color:var(--mj-events-button-hover);text-decoration:underline;}'
            . '.mj-member-events__badge{display:inline-flex;align-items:center;gap:6px;background:var(--mj-events-accent);color:var(--mj-events-accent-contrast);font-weight:600;border-radius:999px;padding:4px 10px;font-size:0.75rem;text-transform:uppercase;letter-spacing:0.04em;}'
            . '.mj-member-events__price{font-weight:700;color:var(--mj-events-card-title);}'
            . '.mj-member-events__actions{margin-top:auto;display:flex;flex-direction:column;gap:12px;}'
            . '.mj-member-events__cta{display:inline-flex;align-items:center;justify-content:center;gap:8px;background:var(--mj-events-button-bg);color:var(--mj-events-button-text);border:1px solid var(--mj-events-button-border);border-radius:var(--mj-events-button-radius);padding:10px 18px;font-weight:600;cursor:pointer;transition:background 0.2s ease,transform 0.2s ease,box-shadow 0.2s ease,color 0.2s ease,border-color 0.2s ease;}'
            . '.mj-member-events__cta:hover{background:var(--mj-events-button-hover);transform:translateY(-1px);box-shadow:0 12px 32px rgba(37,99,235,0.25);}'
            . '.mj-member-events__cta:disabled,.mj-member-events__cta[aria-disabled="true"]{opacity:0.6;cursor:not-allowed;transform:none;box-shadow:none;}'
            . '.mj-member-events__cta.is-registered{background:#059669;border-color:#059669;}'
            . '.mj-member-events__cta.is-skin-outline{background:transparent;color:var(--mj-events-button-bg);border-color:var(--mj-events-button-bg);box-shadow:none;}'
            . '.mj-member-events__cta.is-skin-outline:hover{background:var(--mj-events-button-bg);color:var(--mj-events-button-text);}'
            . '.mj-member-events__cta.is-skin-text{background:transparent;border-color:transparent;padding:0;color:var(--mj-events-button-bg);box-shadow:none;}'
            . '.mj-member-events__cta.is-skin-text:hover{color:var(--mj-events-button-hover);box-shadow:none;transform:none;}'
            . '.mj-member-events__signup{display:none;border:1px solid var(--mj-events-border-soft);border-radius:12px;padding:16px;background:var(--mj-events-surface-soft);}'
            . '.mj-member-events__signup.is-open{display:block;}'
            . '.mj-member-events__signup-title{margin:0 0 12px;font-size:0.95rem;font-weight:600;color:var(--mj-events-card-title);}'
            . '.mj-member-events__signup-options{margin:0 0 16px;padding:0;list-style:none;display:flex;flex-direction:column;gap:12px;}'
            . '.mj-member-events__signup-option{margin:0;display:flex;align-items:center;gap:12px;}'
            . '.mj-member-events__signup-label{display:flex;align-items:center;gap:10px;font-weight:600;color:var(--mj-events-card-title);flex:1;}'
            . '.mj-member-events__signup-radio{width:18px;height:18px;}'
            . '.mj-member-events__signup-name{font-size:0.95rem;}'
            . '.mj-member-events__signup-option.is-registered .mj-member-events__signup-label{opacity:0.65;}'
            . '.mj-member-events__signup-controls{margin-left:auto;display:flex;align-items:center;gap:8px;}'
            . '.mj-member-events__signup-toggle{background:none;border:1px solid var(--mj-events-border-soft);border-radius:999px;padding:6px 14px;font-size:0.85rem;font-weight:600;color:#b91c1c;cursor:pointer;transition:background 0.2s ease,color 0.2s ease;}'
            . '.mj-member-events__signup-toggle:hover{background:rgba(185,28,28,0.08);color:#7f1d1d;}'
            . '.mj-member-events__signup-toggle:disabled{opacity:0.6;cursor:not-allowed;}'
            . '.mj-member-events__signup-status{font-size:0.8rem;color:#059669;font-weight:600;}'
            . '.mj-member-events__signup-empty{margin:0 0 12px;font-size:0.9rem;color:var(--mj-events-meta);}'
            . '.mj-member-events__signup-info{margin:0 0 12px;font-size:0.9rem;font-weight:600;color:var(--mj-events-card-title);}'
            . '.mj-member-events__signup-note{display:flex;flex-direction:column;gap:6px;margin-bottom:16px;}'
            . '.mj-member-events__signup-note label{font-size:0.9rem;font-weight:600;color:var(--mj-events-card-title);}'
            . '.mj-member-events__signup-note textarea{min-height:80px;border:1px solid var(--mj-events-border-soft);border-radius:10px;padding:10px 12px;font-size:0.95rem;resize:vertical;background:#ffffff;}'
            . '.mj-member-events__signup-note textarea:focus{outline:2px solid var(--mj-events-accent);outline-offset:2px;}'
            . '.mj-member-events__signup-actions{display:flex;align-items:center;gap:12px;}'
            . '.mj-member-events__signup-submit{display:inline-flex;align-items:center;gap:8px;background:#0f172a;color:#ffffff;border:none;border-radius:10px;padding:10px 18px;font-weight:600;cursor:pointer;transition:background 0.2s ease;}'
            . '.mj-member-events__signup-submit:hover{background:#1e293b;}'
            . '.mj-member-events__signup-submit:disabled{opacity:0.6;cursor:not-allowed;}'
            . '.mj-member-events__signup-cancel{background:none;border:none;color:var(--mj-events-meta);font-weight:600;cursor:pointer;text-decoration:underline;padding:0;}'
            . '.mj-member-events__signup-feedback{margin-top:12px;font-size:0.85rem;color:var(--mj-events-card-title);}'
            . '.mj-member-events__signup-feedback.is-error{color:#b91c1c;}'
            . '.mj-member-events__occurrence-next{margin:6px 0 0;font-size:0.92rem;font-weight:600;color:var(--mj-events-card-title);}'
            . '.mj-member-events__occurrences{margin:6px 0 0;padding:0;list-style:none;display:flex;flex-direction:column;gap:6px;font-size:0.88rem;color:var(--mj-events-meta);}'
            . '.mj-member-events__occurrence{display:flex;align-items:flex-start;gap:8px;}'
            . '.mj-member-events__occurrence-prefix{font-weight:600;color:var(--mj-events-card-title);}'
            . '.mj-member-events__occurrence-label{flex:1;}'
            . '.mj-member-events__occurrence.is-today .mj-member-events__occurrence-label{color:var(--mj-events-card-title);}'
            . '.mj-member-events__occurrence--more{font-style:italic;}'
            . '.mj-member-events__location-details{display:flex;gap:12px;align-items:flex-start;background:var(--mj-events-surface-soft);border-radius:12px;padding:12px 14px;color:var(--mj-events-card-title);}'
            . '.mj-member-events__location-thumb{flex:0 0 56px;width:56px;height:56px;border-radius:12px;object-fit:cover;border:1px solid var(--mj-events-border-soft);}'
            . '.mj-member-events__location-note{margin:0;font-size:0.9rem;line-height:1.5;color:var(--mj-events-meta);}'
            . '.mj-member-events__map{margin-top:16px;border-radius:12px;overflow:hidden;background:var(--mj-events-surface-soft);box-shadow:0 6px 16px rgba(15,23,42,0.08);}'
            . '.mj-member-events__map iframe{display:block;width:100%;height:220px;border:0;}'
            . '.mj-member-events__map-address{margin:12px 16px 0;font-size:0.9rem;color:var(--mj-events-card-title);font-weight:500;}'
            . '.mj-member-events__map-link{display:inline-block;margin:10px 16px 16px;font-size:0.85rem;font-weight:600;color:var(--mj-events-accent);text-decoration:none;}'
            . '.mj-member-events__map-link:hover{text-decoration:underline;}'
            . '.mj-member-events__registrations{margin-top:16px;padding:14px;border:1px solid var(--mj-events-border-soft);border-radius:12px;background:#eef2ff;color:var(--mj-events-card-title);}'
            . '.mj-member-events__registrations-title{margin:0 0 8px;font-size:0.95rem;font-weight:600;color:#1e293b;}'
            . '.mj-member-events__registrations-list{margin:0;padding-left:18px;list-style:disc;font-size:0.9rem;color:var(--mj-events-card-title);}'
            . '.mj-member-events__registrations-list li{margin-bottom:4px;}'
            . '.mj-member-events__registrations-status{font-size:0.75rem;font-weight:600;color:var(--mj-events-accent);margin-left:6px;text-transform:uppercase;}'
            . '.mj-member-events__registrations-empty{margin:0;font-size:0.9rem;color:var(--mj-events-meta);}'
            . '.mj-member-events__feedback{font-size:0.9rem;font-weight:600;color:#059669;}'
            . '.mj-member-events__feedback.is-error{color:#b91c1c;}'
            . '.mj-member-events__closed{font-size:0.95rem;font-weight:600;color:#ef4444;}'
            . '.mj-member-events__empty{margin:0;font-size:0.95rem;color:#6b7280;}'
            . '.mj-member-events__filtered-empty{margin:0;font-size:0.95rem;color:#6b7280;}'
            . '</style>';
    }
}

if (!function_exists('mj_member_ensure_events_widget_localized')) {
    function mj_member_ensure_events_widget_localized() {
        static $localized = false;
        if ($localized) {
            return;
        }

        wp_localize_script(
            'mj-member-events-widget',
            'mjMemberEventsWidget',
            array(
                'ajaxUrl' => esc_url_raw(admin_url('admin-ajax.php')),
                'nonce' => wp_create_nonce('mj-member-event-register'),
                'loginUrl' => esc_url_raw(wp_login_url()),
                'strings' => array(
                    'chooseParticipant' => __('Qui participera ?', 'mj-member'),
                    'confirm' => __('Confirmer l\'inscription', 'mj-member'),
                    'cancel' => __('Annuler', 'mj-member'),
                    'loginRequired' => __('Connectez-vous pour continuer.', 'mj-member'),
                    'selectParticipant' => __('Merci de sélectionner un participant.', 'mj-member'),
                    'genericError' => __('Une erreur est survenue. Merci de réessayer.', 'mj-member'),
                    'registered' => __('Inscription envoyée', 'mj-member'),
                    'success' => __('Inscription enregistrée !', 'mj-member'),
                    'closed' => __('Inscriptions clôturées', 'mj-member'),
                    'loading' => __('En cours...', 'mj-member'),
                    'noParticipant' => __("Aucun profil disponible pour l'instant.", 'mj-member'),
                    'alreadyRegistered' => __('Déjà inscrit', 'mj-member'),
                    'allRegistered' => __('Tous les profils sont déjà inscrits pour cet événement.', 'mj-member'),
                    'noteLabel' => __('Message pour l’équipe (optionnel)', 'mj-member'),
                    'notePlaceholder' => __('Précisez une remarque utile (allergies, arrivée tardive, etc.).', 'mj-member'),
                    'cta' => __("S'inscrire", 'mj-member'),
                    'unregister' => __('Se désinscrire', 'mj-member'),
                    'unregisterConfirm' => __('Annuler cette inscription ?', 'mj-member'),
                    'unregisterSuccess' => __('Inscription annulée.', 'mj-member'),
                    'unregisterError' => __('Impossible d\'annuler l\'inscription.', 'mj-member'),
                    'filterNoResult' => __('Aucun événement ne correspond à ce filtre.', 'mj-member'),
                    'locale' => get_locale(),
                    'occurrenceLegend' => __('Quelles dates ?', 'mj-member'),
                    'occurrenceHelp' => __('Cochez les occurrences auxquelles vous participerez.', 'mj-member'),
                    'occurrenceHelpRecurring' => __('Sélectionnez de nouvelles dates ou retirez une réservation.', 'mj-member'),
                    'occurrenceMissing' => __('Merci de sélectionner au moins une occurrence.', 'mj-member'),
                    'occurrencePast' => __('Passée', 'mj-member'),
                    'occurrenceEmpty' => __('Aucune occurrence disponible.', 'mj-member'),
                    'occurrenceRegisteredTitle' => __('Vos réservations', 'mj-member'),
                    'occurrenceAvailableTitle' => __('Autres dates disponibles', 'mj-member'),
                    'occurrenceRegisteredEmpty' => __('Aucune réservation active.', 'mj-member'),
                    'occurrenceAvailableEmpty' => __('Toutes les dates sont déjà réservées.', 'mj-member'),
                ),
            )
        );

        $localized = true;
    }
}

if (!function_exists('mj_member_build_event_registration_context')) {
    function mj_member_build_event_registration_context($event_data) {
        $context = array(
            'event_id' => isset($event_data['id']) ? (int) $event_data['id'] : 0,
            'is_open' => false,
            'requires_login' => !is_user_logged_in(),
            'cta_label' => apply_filters('mj_member_event_single_cta_label', __("S'inscrire", 'mj-member'), $event_data),
            'cta_registered_label' => apply_filters('mj_member_event_single_cta_registered_label', __('Déjà inscrit', 'mj-member'), $event_data),
            'payload' => array(),
            'participants' => array(),
            'registered_count' => 0,
            'available_count' => 0,
            'all_registered' => false,
            'has_participants' => false,
            'needs_script' => false,
        );

        $event_id = $context['event_id'];
        if ($event_id <= 0) {
            return $context;
        }

        $now_ts = current_time('timestamp');
        $deadline_raw = isset($event_data['deadline']) ? trim((string) $event_data['deadline']) : '';
        $deadline_ts = ($deadline_raw !== '' && $deadline_raw !== '0000-00-00 00:00:00') ? strtotime($deadline_raw) : false;
        $start_raw = isset($event_data['start_date']) ? trim((string) $event_data['start_date']) : '';
        $start_ts = $start_raw !== '' ? strtotime($start_raw) : false;

        $registration_open = true;
        $closed_by_start = false;
        if ($deadline_ts && $deadline_ts < $now_ts) {
            $registration_open = false;
        }
        if ($registration_open && $start_ts && $start_ts < $now_ts) {
            $registration_open = false;
            $closed_by_start = true;
        }
        $occurrence_selection = array();
        $occurrence_assignments = array(
            'mode' => 'all',
            'occurrences' => array(),
        );
        $next_occurrence_ts = null;

        $schedule_mode = isset($event_data['schedule_mode']) ? sanitize_key((string) $event_data['schedule_mode']) : 'fixed';
        if (!in_array($schedule_mode, array('fixed', 'range', 'recurring'), true)) {
            $schedule_mode = 'fixed';
        }

        if (!empty($event_data)) {
            $event_object = is_object($event_data) ? clone $event_data : (object) $event_data;

            if (class_exists('MjEventSchedule')) {
                $schedule_args = array(
                    'max' => 40,
                    'include_past' => true,
                );
                $occurrences_raw = MjEventSchedule::get_occurrences($event_object, $schedule_args);

                if (!empty($occurrences_raw) && is_array($occurrences_raw)) {
                    foreach ($occurrences_raw as $occurrence_entry) {
                        if (!is_array($occurrence_entry)) {
                            continue;
                        }

                        $start_value = isset($occurrence_entry['start']) ? (string) $occurrence_entry['start'] : '';
                        if ($start_value === '') {
                            continue;
                        }

                        $label_value = isset($occurrence_entry['label']) ? sanitize_text_field((string) $occurrence_entry['label']) : '';
                        $timestamp_value = isset($occurrence_entry['timestamp']) ? (int) $occurrence_entry['timestamp'] : strtotime($start_value);
                        if ($timestamp_value === false) {
                            $timestamp_value = 0;
                        }

                        $normalized_start = $start_value;
                        if (class_exists('MjEventAttendance')) {
                            $normalized_candidate = MjEventAttendance::normalize_occurrence($start_value);
                            if ($normalized_candidate !== '') {
                                $normalized_start = $normalized_candidate;
                            }
                        }

                        $is_past = ($timestamp_value !== 0 && $timestamp_value < $now_ts);
                        $is_today = ($timestamp_value !== 0 && wp_date('Y-m-d', $timestamp_value) === wp_date('Y-m-d', $now_ts));

                        if ($next_occurrence_ts === null && !$is_past) {
                            $next_occurrence_ts = $timestamp_value;
                        }

                        $occurrence_selection[] = array(
                            'start' => $normalized_start,
                            'label' => ($label_value !== '' ? $label_value : $normalized_start),
                            'timestamp' => $timestamp_value,
                            'isPast' => $is_past,
                            'isToday' => $is_today,
                        );
                    }

                    if (!empty($occurrence_selection)) {
                        usort($occurrence_selection, static function ($left, $right) {
                            $left_ts = isset($left['timestamp']) ? (int) $left['timestamp'] : 0;
                            $right_ts = isset($right['timestamp']) ? (int) $right['timestamp'] : 0;
                            if ($left_ts === $right_ts) {
                                return 0;
                            }
                            if ($left_ts === 0) {
                                return 1;
                            }
                            if ($right_ts === 0) {
                                return -1;
                            }
                            return $left_ts < $right_ts ? -1 : 1;
                        });

                        $occurrence_selection = array_slice($occurrence_selection, 0, 20);
                    }
                }
            }
        }

        if (!$registration_open && $closed_by_start && $next_occurrence_ts !== null && $next_occurrence_ts > $now_ts) {
            $registration_open = true;
        }

        if ($registration_open && $next_occurrence_ts !== null && $next_occurrence_ts <= $now_ts) {
            $registration_open = false;
        }

        $context['is_open'] = $registration_open;
        $context['schedule_mode'] = $schedule_mode;

        $deadline_label = '';
        if ($deadline_ts) {
            $deadline_label = wp_date(get_option('date_format', 'd/m/Y H:i'), $deadline_ts);
        }
        $context['deadline'] = $deadline_raw;
        $context['deadline_label'] = $deadline_label;
        $context['deadline_timestamp'] = $deadline_ts ? (int) $deadline_ts : 0;

        $allow_guardian_registration = !empty($event_data['allow_guardian_registration']);

        $current_member = (is_user_logged_in() && function_exists('mj_member_get_current_member')) ? mj_member_get_current_member() : null;
        $participant_options = array();

        if ($current_member && !empty($current_member->id)) {
            $member_name_parts = array();
            if (!empty($current_member->first_name)) {
                $member_name_parts[] = sanitize_text_field($current_member->first_name);
            }
            if (!empty($current_member->last_name)) {
                $member_name_parts[] = sanitize_text_field($current_member->last_name);
            }

            $member_display_name = trim(implode(' ', $member_name_parts));
            if ($member_display_name === '' && !empty($current_member->nickname)) {
                $member_display_name = sanitize_text_field($current_member->nickname);
            }
            if ($member_display_name === '') {
                $member_display_name = sprintf(__('Membre #%d', 'mj-member'), (int) $current_member->id);
            }

            $self_label = $member_display_name . ' (' . __('moi', 'mj-member') . ')';
            $participant_options[] = array(
                'id' => (int) $current_member->id,
                'label' => $self_label,
                'type' => isset($current_member->role) ? sanitize_key($current_member->role) : 'member',
                'isSelf' => true,
            );
        }

        if ($current_member && function_exists('mj_member_can_manage_children') && function_exists('mj_member_get_guardian_children') && mj_member_can_manage_children($current_member)) {
            $children = mj_member_get_guardian_children($current_member);
            if (!empty($children) && is_array($children)) {
                foreach ($children as $child) {
                    if (!$child || !isset($child->id)) {
                        continue;
                    }

                    $child_name_parts = array();
                    if (!empty($child->first_name)) {
                        $child_name_parts[] = sanitize_text_field($child->first_name);
                    }
                    if (!empty($child->last_name)) {
                        $child_name_parts[] = sanitize_text_field($child->last_name);
                    }

                    $child_label = trim(implode(' ', $child_name_parts));
                    if ($child_label === '' && !empty($child->nickname)) {
                        $child_label = sanitize_text_field($child->nickname);
                    }
                    if ($child_label === '') {
                        $child_label = sprintf(__('Jeune #%d', 'mj-member'), (int) $child->id);
                    }

                    $participant_options[] = array(
                        'id' => (int) $child->id,
                        'label' => $child_label,
                        'type' => 'child',
                        'isSelf' => false,
                    );
                }
            }
        }

        if (!empty($participant_options)) {
            $participant_options = array_values($participant_options);
        }

        $participants_source = $participant_options;
        if (!$allow_guardian_registration && !empty($participant_options)) {
            $participants_source = array();
            foreach ($participant_options as $participant_option) {
                $option_type = isset($participant_option['type']) ? sanitize_key($participant_option['type']) : '';
                $is_self = !empty($participant_option['isSelf']);
                if ($is_self && $option_type === MjMembers_CRUD::ROLE_TUTEUR) {
                    continue;
                }
                $participants_source[] = $participant_option;
            }
        }

        $event_participants = array();
        $registered_count = 0;
        $available_count = 0;

        if (!empty($participants_source)) {
            foreach ($participants_source as $participant_option) {
                $participant_entry = $participant_option;
                $participant_entry['isRegistered'] = false;
                $participant_entry['registrationId'] = 0;
                $participant_entry['registrationStatus'] = '';
                $participant_entry['registrationCreatedAt'] = '';
                $participant_entry['occurrenceAssignments'] = array(
                    'mode' => 'all',
                    'occurrences' => array(),
                );

                $participant_id = isset($participant_option['id']) ? (int) $participant_option['id'] : 0;

                if ($participant_id > 0 && class_exists('MjEventRegistrations')) {
                    $existing_registration = MjEventRegistrations::get_existing($event_id, $participant_id);
                    if ($existing_registration && (!isset($existing_registration->statut) || $existing_registration->statut !== MjEventRegistrations::STATUS_CANCELLED)) {
                        $participant_entry['isRegistered'] = true;
                        if (isset($existing_registration->id)) {
                            $participant_entry['registrationId'] = (int) $existing_registration->id;
                        }
                        if (!empty($existing_registration->statut)) {
                            $participant_entry['registrationStatus'] = sanitize_key($existing_registration->statut);
                        }
                        if (!empty($existing_registration->created_at)) {
                            $participant_entry['registrationCreatedAt'] = sanitize_text_field($existing_registration->created_at);
                        }
                        if (class_exists('MjEventAttendance')) {
                            $participant_entry['occurrenceAssignments'] = MjEventAttendance::get_registration_assignments($existing_registration);
                            if (isset($participant_entry['occurrenceAssignments']['mode']) && $participant_entry['occurrenceAssignments']['mode'] === 'custom' && !empty($participant_entry['occurrenceAssignments']['occurrences'])) {
                                $occurrence_assignments = $participant_entry['occurrenceAssignments'];
                            }
                        }
                    }
                }

                if ($participant_entry['isRegistered']) {
                    $registered_count++;
                } else {
                    $available_count++;
                }

                $event_participants[] = $participant_entry;
            }
        }

        $all_registered = !empty($event_participants) && $registered_count === count($event_participants);

        $payload = array(
            'eventId' => $event_id,
            'eventTitle' => isset($event_data['title']) ? $event_data['title'] : '',
            'participants' => $event_participants,
            'allRegistered' => $all_registered,
            'hasParticipants' => !empty($event_participants),
            'hasAvailableParticipants' => ($available_count > 0),
            'noteMaxLength' => 400,
        );

        if ($deadline_ts) {
            $payload['deadline'] = gmdate('c', $deadline_ts);
        }

        $total_participants = count($event_participants);
        $price_amount = isset($event_data['price']) ? (float) $event_data['price'] : 0.0;
        $price_label = isset($event_data['price_label']) ? $event_data['price_label'] : ($price_amount > 0
            ? sprintf(__('Tarif : %s €', 'mj-member'), number_format_i18n($price_amount, 2))
            : __('Tarif : Gratuit', 'mj-member'));

        $context['participants'] = $event_participants;
        $context['registered_count'] = $registered_count;
        $context['available_count'] = $available_count;
        $context['all_registered'] = $all_registered;
        $context['total_count'] = $total_participants;
        $context['has_participants'] = !empty($event_participants);
        $context['price_amount'] = $price_amount;
        $context['price_label'] = $price_label;
        $context['payment_required'] = $price_amount > 0;
        $context['summary'] = array(
            'total' => $total_participants,
            'registered' => $registered_count,
            'available' => $available_count,
        );
        $context['occurrences'] = $occurrence_selection;
        $context['has_occurrences'] = !empty($occurrence_selection);
        $context['assignments'] = $occurrence_assignments;
        $payload['occurrences'] = $occurrence_selection;
        $payload['assignments'] = $occurrence_assignments;
        $payload['scheduleMode'] = $schedule_mode;
        $payload['hasOccurrences'] = !empty($occurrence_selection);
        $context['payload'] = $payload;
        $context['needs_script'] = $registration_open && ($context['requires_login'] || !empty($event_participants));

        return $context;
    }
}

if (!function_exists('mj_member_prepare_event_occurrences_preview')) {
    /**
     * Prépare les occurrences futures d'un événement pour l'affichage public.
     *
     * @param array<string,mixed>|object $event
     * @param array<string,mixed>        $args
     * @return array<string,mixed>
     */
    function mj_member_prepare_event_occurrences_preview($event, $args = array()) {
        $defaults = array(
            'max' => 3,
            'include_past' => false,
            'fetch_limit' => 10,
        );

        $args = wp_parse_args($args, $defaults);

        $max = max(1, (int) $args['max']);
        $include_past = !empty($args['include_past']);
        $fetch_limit = isset($args['fetch_limit']) ? (int) $args['fetch_limit'] : 10;
        if ($fetch_limit < $max) {
            $fetch_limit = $max;
        }
        $fetch_limit = max($fetch_limit, $max);

        $event_object = null;
        if (is_object($event)) {
            $event_object = clone $event;
        } elseif (is_array($event) && !empty($event)) {
            $event_object = (object) $event;
        }

        if (!$event_object) {
            return array(
                'items' => array(),
                'next' => null,
                'remaining' => 0,
                'has_multiple' => false,
            );
        }

        if (!isset($event_object->date_debut) && isset($event_object->start_date)) {
            $event_object->date_debut = $event_object->start_date;
        }
        if (!isset($event_object->date_fin) && isset($event_object->end_date)) {
            $event_object->date_fin = $event_object->end_date;
        }
        if (!isset($event_object->date_fin_inscription) && isset($event_object->deadline)) {
            $event_object->date_fin_inscription = $event_object->deadline;
        }

        if (!isset($event_object->schedule_mode) && isset($event_object->mode)) {
            $event_object->schedule_mode = $event_object->mode;
        }
        if (!isset($event_object->schedule_payload) && isset($event_object->payload)) {
            $event_object->schedule_payload = $event_object->payload;
        }

        if (isset($event_object->schedule_payload) && !is_array($event_object->schedule_payload) && $event_object->schedule_payload !== '') {
            $decoded_payload = json_decode((string) $event_object->schedule_payload, true);
            if (is_array($decoded_payload)) {
                $event_object->schedule_payload = $decoded_payload;
            }
        }

        $occurrences_raw = array();
        if (class_exists('MjEventSchedule')) {
            $occurrence_args = array(
                'max' => max($fetch_limit, $max),
                'include_past' => $include_past,
            );
            $occurrences_raw = MjEventSchedule::get_occurrences($event_object, $occurrence_args);
        }

        if (empty($occurrences_raw)) {
            $start_fallback = isset($event_object->date_debut) ? (string) $event_object->date_debut : '';
            $end_fallback = isset($event_object->date_fin) ? (string) $event_object->date_fin : $start_fallback;

            if ($start_fallback !== '') {
                $timestamp = strtotime($start_fallback);
                $label_format = get_option('date_format', 'd/m/Y') . ' ' . get_option('time_format', 'H:i');
                $fallback_label = $timestamp ? wp_date($label_format, $timestamp) : $start_fallback;

                $occurrences_raw[] = array(
                    'start' => $start_fallback,
                    'end' => $end_fallback,
                    'label' => $fallback_label,
                    'timestamp' => $timestamp ?: 0,
                    'is_past' => $timestamp ? ($timestamp < current_time('timestamp')) : false,
                );
            }
        }

        if (empty($occurrences_raw)) {
            return array(
                'items' => array(),
                'next' => null,
                'remaining' => 0,
                'has_multiple' => false,
            );
        }

        $display_raw = array_slice($occurrences_raw, 0, $max);
        $remaining = max(0, count($occurrences_raw) - count($display_raw));

        $now_ts = current_time('timestamp');
        $today_slug = wp_date('Y-m-d', $now_ts);

        $items = array();
        $next = null;

        foreach ($display_raw as $index => $raw_occurrence) {
            if (!is_array($raw_occurrence)) {
                continue;
            }

            $start_value = isset($raw_occurrence['start']) ? (string) $raw_occurrence['start'] : '';
            if ($start_value === '') {
                continue;
            }

            $end_value = isset($raw_occurrence['end']) ? (string) $raw_occurrence['end'] : $start_value;
            $timestamp = isset($raw_occurrence['timestamp']) ? (int) $raw_occurrence['timestamp'] : strtotime($start_value);
            if ($timestamp === false) {
                $timestamp = 0;
            }

            $label_value = isset($raw_occurrence['label']) ? (string) $raw_occurrence['label'] : '';
            if ($label_value === '') {
                $label_value = mj_member_format_event_datetime_range($start_value, $end_value);
            }

            $is_past = isset($raw_occurrence['is_past']) ? (bool) $raw_occurrence['is_past'] : ($timestamp !== 0 && $timestamp < $now_ts);
            $is_today = $timestamp !== 0 ? (wp_date('Y-m-d', $timestamp) === $today_slug) : false;

            $item = array(
                'start' => $start_value,
                'end' => $end_value,
                'label' => sanitize_text_field($label_value),
                'timestamp' => $timestamp,
                'isPast' => $is_past,
                'isToday' => $is_today,
            );

            if ($next === null && !$is_past) {
                $next = $item;
            }

            if ($index === 0 && $next === null) {
                $next = $item;
            }

            $items[] = $item;
        }

        if ($next === null && !empty($items)) {
            $next = $items[0];
        }

        $has_multiple = (count($items) + $remaining) > 1;

        return array(
            'items' => $items,
            'next' => $next,
            'remaining' => $remaining,
            'has_multiple' => $has_multiple,
        );
    }
}

if (!function_exists('mj_member_register_event_routes')) {
    function mj_member_register_event_routes() {
        add_rewrite_tag('%mj_event_slug%', '([^&]+)');
        add_rewrite_rule('date/([^/]+)/?$', 'index.php?mj_event_slug=$matches[1]', 'top');
    }
    add_action('init', 'mj_member_register_event_routes', 12);
}

if (!function_exists('mj_member_event_query_vars')) {
    function mj_member_event_query_vars($vars) {
        if (!in_array('mj_event_slug', $vars, true)) {
            $vars[] = 'mj_event_slug';
        }

        return $vars;
    }
    add_filter('query_vars', 'mj_member_event_query_vars');
}

if (!function_exists('mj_member_get_public_events')) {
    /**
     * Retrieve events for public/front-end displays.
     *
     * @param array<string,mixed> $args
     * @return array<int,array<string,mixed>>
     */
    function mj_member_get_public_events($args = array()) {
        $defaults = array(
            'statuses' => array(MjEvents_CRUD::STATUS_ACTIVE),
            'types' => array(),
            'ids' => array(),
            'limit' => 6,
            'order' => 'DESC',
            'orderby' => 'date_debut',
            'include_past' => false,
            'now' => current_time('mysql'),
        );

        $args = wp_parse_args($args, $defaults);

        $statuses = array();
        if (!empty($args['statuses']) && is_array($args['statuses'])) {
            foreach ($args['statuses'] as $status_candidate) {
                $status_candidate = sanitize_key($status_candidate);
                if ($status_candidate === '') {
                    continue;
                }
                $statuses[$status_candidate] = $status_candidate;
            }
        }
        if (empty($statuses)) {
            $statuses = array(MjEvents_CRUD::STATUS_ACTIVE);
        }

        $types = array();
        if (!empty($args['types']) && is_array($args['types'])) {
            foreach ($args['types'] as $type_candidate) {
                $type_candidate = sanitize_key($type_candidate);
                if ($type_candidate === '') {
                    continue;
                }
                $types[$type_candidate] = $type_candidate;
            }
        }

        $ids = array();
        if (!empty($args['ids']) && is_array($args['ids'])) {
            foreach ($args['ids'] as $id_candidate) {
                $id_candidate = (int) $id_candidate;
                if ($id_candidate <= 0) {
                    continue;
                }
                $ids[$id_candidate] = $id_candidate;
            }
        }

        $limit = isset($args['limit']) ? (int) $args['limit'] : 6;
        if ($limit <= 0) {
            $limit = 6;
        }
        if (!empty($ids)) {
            $limit = max($limit, count($ids));
        }
        $limit = min($limit, 100);

        $orderby_map = array(
            'date_debut' => 'events.date_debut',
            'date_fin' => 'events.date_fin',
            'created_at' => 'events.created_at',
            'updated_at' => 'events.updated_at',
        );
        $orderby_key = isset($args['orderby']) ? sanitize_key($args['orderby']) : 'date_debut';
        if (!isset($orderby_map[$orderby_key])) {
            $orderby_key = 'date_debut';
        }
        $order_sql = strtoupper(isset($args['order']) ? $args['order'] : 'DESC') === 'ASC' ? 'ASC' : 'DESC';

        global $wpdb;
        $events_table = mj_member_get_events_table_name();
        $locations_table = mj_member_get_event_locations_table_name();
        $has_locations = mj_member_table_exists($locations_table);

        $location_columns = array(
            'type' => false,
            'types' => false,
            'category' => false,
            'metadata' => false,
        );
        if ($has_locations && function_exists('mj_member_column_exists')) {
            static $location_column_cache = null;
            if ($location_column_cache === null) {
                $location_column_cache = array(
                    'type' => mj_member_column_exists($locations_table, 'type'),
                    'types' => mj_member_column_exists($locations_table, 'types'),
                    'category' => mj_member_column_exists($locations_table, 'category'),
                    'metadata' => mj_member_column_exists($locations_table, 'metadata'),
                );
            }
            $location_columns = $location_column_cache;
        }

        $select_fields = array(
            'events.id',
            'events.title',
            'events.slug',
            'events.status',
            'events.type',
            'events.accent_color',
            'events.cover_id',
            'events.article_id',
            'events.description',
            'events.date_debut',
            'events.date_fin',
            'events.date_fin_inscription',
            'events.prix',
            'events.age_min',
            'events.age_max',
            'events.created_at',
            'events.updated_at',
            'events.location_id',
            'events.schedule_mode',
            'events.schedule_payload',
            'events.recurrence_until',
        );

        $supports_guardian_toggle = function_exists('mj_member_column_exists') ? mj_member_column_exists($events_table, 'allow_guardian_registration') : false;
        if ($supports_guardian_toggle) {
            $select_fields[] = 'events.allow_guardian_registration';
        }

        $join_sql = '';
        if ($has_locations) {
            $select_fields[] = 'locations.name AS location_name';
            $select_fields[] = 'locations.city AS location_city';
            $select_fields[] = 'locations.address_line AS location_address';
            $select_fields[] = 'locations.postal_code AS location_postal_code';
            $select_fields[] = 'locations.country AS location_country';
            $select_fields[] = 'locations.latitude AS location_latitude';
            $select_fields[] = 'locations.longitude AS location_longitude';
            $select_fields[] = 'locations.map_query AS location_map_query';
            $select_fields[] = 'locations.notes AS location_notes';
            $select_fields[] = 'locations.cover_id AS location_cover_id';
            if (!empty($location_columns['type'])) {
                $select_fields[] = 'locations.type AS location_type';
            }
            if (!empty($location_columns['types'])) {
                $select_fields[] = 'locations.types AS location_types';
            }
            if (!empty($location_columns['category'])) {
                $select_fields[] = 'locations.category AS location_category';
            }
            if (!empty($location_columns['metadata'])) {
                $select_fields[] = 'locations.metadata AS location_metadata';
            }
            $join_sql = " LEFT JOIN {$locations_table} AS locations ON locations.id = events.location_id";
        }

        $where_fragments = array();
        $where_params = array();

        if (!empty($statuses)) {
            $placeholders = implode(',', array_fill(0, count($statuses), '%s'));
            $where_fragments[] = "events.status IN ({$placeholders})";
            foreach ($statuses as $status_value) {
                $where_params[] = $status_value;
            }
        }

        if (!empty($types)) {
            $placeholders = implode(',', array_fill(0, count($types), '%s'));
            $where_fragments[] = "events.type IN ({$placeholders})";
            foreach ($types as $type_value) {
                $where_params[] = $type_value;
            }
        }

        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '%d'));
            $where_fragments[] = "events.id IN ({$placeholders})";
            foreach ($ids as $id_value) {
                $where_params[] = $id_value;
            }
        }

        $now_value = isset($args['now']) ? sanitize_text_field($args['now']) : current_time('mysql');
        if (!$args['include_past']) {
            $where_fragments[] = 'events.date_fin >= %s';
            $where_params[] = $now_value;
        }

        $where_sql = '';
        if (!empty($where_fragments)) {
            $where_sql = 'WHERE ' . implode(" AND ", $where_fragments);
        }

        $query = "SELECT " . implode(', ', $select_fields) . " FROM {$events_table} AS events{$join_sql} {$where_sql} ORDER BY {$orderby_map[$orderby_key]} {$order_sql} LIMIT %d";
        $where_params[] = $limit;
        array_unshift($where_params, $query);
        $prepared = call_user_func_array(array($wpdb, 'prepare'), $where_params);
        $rows = $wpdb->get_results($prepared);

        if (empty($rows)) {
            return array();
        }

        $type_color_map = method_exists('MjEvents_CRUD', 'get_type_colors') ? MjEvents_CRUD::get_type_colors() : array();
        $results = array();
        foreach ($rows as $row) {
            $cover_id = isset($row->cover_id) ? (int) $row->cover_id : 0;
            $cover_url = '';
            $cover_thumb_url = '';
            if ($cover_id > 0) {
                $image = wp_get_attachment_image_src($cover_id, 'large');
                if (!empty($image[0])) {
                    $cover_url = $image[0];
                }
                $thumb_image = wp_get_attachment_image_src($cover_id, 'medium');
                if (!empty($thumb_image[0])) {
                    $cover_thumb_url = $thumb_image[0];
                }
            }

            $article_id = isset($row->article_id) ? (int) $row->article_id : 0;
            $article_permalink = '';
            $article_cover_url = '';
            $article_cover_thumb_url = '';

            if ($article_id > 0) {
                $article_status = get_post_status($article_id);
                if ($article_status && $article_status !== 'trash') {
                    $article_permalink_candidate = get_permalink($article_id);
                    if (!empty($article_permalink_candidate)) {
                        $article_permalink = esc_url_raw($article_permalink_candidate);
                    }

                    $article_cover_candidate = get_the_post_thumbnail_url($article_id, 'large');
                    if (!empty($article_cover_candidate)) {
                        $article_cover_url = esc_url_raw($article_cover_candidate);
                    }

                    $article_cover_thumb_candidate = get_the_post_thumbnail_url($article_id, 'medium');
                    if (!empty($article_cover_thumb_candidate)) {
                        $article_cover_thumb_url = esc_url_raw($article_cover_thumb_candidate);
                    } elseif ($article_cover_url !== '') {
                        $article_cover_thumb_url = $article_cover_url;
                    }
                }
            }

            $description = isset($row->description) ? wp_kses_post($row->description) : '';
            $excerpt = $description !== '' ? wp_trim_words(wp_strip_all_tags($description), 28, '&hellip;') : '';

            $schedule_mode = isset($row->schedule_mode) ? sanitize_key($row->schedule_mode) : 'fixed';
            if (!in_array($schedule_mode, array('fixed', 'range', 'recurring'), true)) {
                $schedule_mode = 'fixed';
            }

            $schedule_payload = array();
            if (isset($row->schedule_payload) && $row->schedule_payload !== null && $row->schedule_payload !== '') {
                if (is_array($row->schedule_payload)) {
                    $schedule_payload = $row->schedule_payload;
                } else {
                    $decoded_payload = json_decode((string) $row->schedule_payload, true);
                    if (is_array($decoded_payload)) {
                        $schedule_payload = $decoded_payload;
                    }
                }
            }

            $recurrence_until = isset($row->recurrence_until) ? sanitize_text_field($row->recurrence_until) : '';

            $permalink = apply_filters('mj_member_event_permalink', '', $row);
            $slug_value = '';
            if (!empty($row->slug)) {
                $slug_value = sanitize_title($row->slug);
            }
            if ($slug_value === '' && isset($row->id)) {
                $slug_value = MjEvents_CRUD::get_or_create_slug((int) $row->id);
            }
            $event_permalink = '';
            if ($slug_value !== '') {
                $event_permalink = mj_member_build_event_permalink($slug_value);
            }

            if ((empty($permalink) || !is_string($permalink)) && $event_permalink !== '') {
                $permalink = $event_permalink;
            }

            if ((empty($permalink) || !is_string($permalink)) && $article_permalink !== '') {
                $permalink = $article_permalink;
            }

            $location_label = '';
            if (!empty($row->location_name)) {
                $location_label = sanitize_text_field($row->location_name);
                if (!empty($row->location_city)) {
                    $location_label .= ' (' . sanitize_text_field($row->location_city) . ')';
                }
            }

            $location_address = '';
            $location_map_embed = '';
            $location_map_link = '';
            $location_notes = '';
            $location_cover_url = '';
            $location_cover_id = 0;
            if ($has_locations && !empty($row->location_id) && class_exists('MjEventLocations')) {
                $location_context = array(
                    'address_line' => isset($row->location_address) ? $row->location_address : '',
                    'postal_code' => isset($row->location_postal_code) ? $row->location_postal_code : '',
                    'city' => isset($row->location_city) ? $row->location_city : '',
                    'country' => isset($row->location_country) ? $row->location_country : '',
                    'latitude' => isset($row->location_latitude) ? $row->location_latitude : '',
                    'longitude' => isset($row->location_longitude) ? $row->location_longitude : '',
                    'map_query' => isset($row->location_map_query) ? $row->location_map_query : '',
                );

                $location_address_raw = MjEventLocations::format_address($location_context);
                if (!empty($location_address_raw)) {
                    $location_address = sanitize_text_field($location_address_raw);
                }

                $map_embed_candidate = MjEventLocations::build_map_embed_src($location_context);
                if (!empty($map_embed_candidate)) {
                    $location_map_embed = esc_url_raw($map_embed_candidate);
                    $location_map_link = $map_embed_candidate;
                    if (strpos($location_map_link, 'output=embed') !== false) {
                        $location_map_link = str_replace('&output=embed', '', $location_map_link);
                        $location_map_link = str_replace('?output=embed', '', $location_map_link);
                    }
                    $location_map_link = esc_url_raw($location_map_link);
                }

                if (!empty($row->location_notes)) {
                    $location_notes = sanitize_textarea_field($row->location_notes);
                }

                if (!empty($row->location_cover_id)) {
                    $location_cover_id = (int) $row->location_cover_id;
                    if ($location_cover_id > 0) {
                        $cover_image = wp_get_attachment_image_src($location_cover_id, 'thumbnail');
                        if (!empty($cover_image[0])) {
                            $location_cover_url = esc_url_raw($cover_image[0]);
                        }
                    }
                }
            }

            $location_type_entries = array();
            if (!empty($row->location_id) && method_exists('MjEventLocations', 'extract_types')) {
                $location_source = array(
                    'type' => isset($row->location_type) ? $row->location_type : '',
                    'types' => isset($row->location_types) ? $row->location_types : '',
                    'category' => isset($row->location_category) ? $row->location_category : '',
                    'notes' => isset($row->location_notes) ? $row->location_notes : '',
                );
                if (isset($row->location_metadata)) {
                    $location_source['metadata'] = $row->location_metadata;
                }

                $location_type_entries = MjEventLocations::extract_types($location_source);
            }

            $location_type_slugs = array();
            $location_type_labels = array();
            if (!empty($location_type_entries)) {
                foreach ($location_type_entries as $type_entry) {
                    if (!is_array($type_entry)) {
                        continue;
                    }
                    $type_slug = isset($type_entry['slug']) ? sanitize_title($type_entry['slug']) : '';
                    $type_label = isset($type_entry['label']) ? sanitize_text_field($type_entry['label']) : '';
                    if ($type_slug === '' || $type_label === '') {
                        continue;
                    }
                    if (!isset($location_type_labels[$type_slug])) {
                        $location_type_slugs[] = $type_slug;
                        $location_type_labels[$type_slug] = $type_label;
                    }
                }
            }

            if ($cover_url === '' && $article_cover_url !== '') {
                $cover_url = $article_cover_url;
            }
            if ($cover_thumb_url === '' && $article_cover_thumb_url !== '') {
                $cover_thumb_url = $article_cover_thumb_url;
            }

            $accent_color = '';
            if (isset($row->accent_color)) {
                $accent_candidate = sanitize_hex_color($row->accent_color);
                if (is_string($accent_candidate) && $accent_candidate !== '') {
                    $accent_color = strtoupper(strlen($accent_candidate) === 4
                        ? '#' . $accent_candidate[1] . $accent_candidate[1] . $accent_candidate[2] . $accent_candidate[2] . $accent_candidate[3] . $accent_candidate[3]
                        : $accent_candidate
                    );
                }
            }

            if ($accent_color === '') {
                $type_key_candidate = isset($row->type) ? sanitize_key((string) $row->type) : '';
                if ($type_key_candidate !== '') {
                    if (isset($type_color_map[$type_key_candidate])) {
                        $accent_color = strtoupper($type_color_map[$type_key_candidate]);
                    } elseif (method_exists('MjEvents_CRUD', 'get_default_color_for_type')) {
                        $accent_color = MjEvents_CRUD::get_default_color_for_type($type_key_candidate);
                    }
                }
            }

            $results[] = array(
                'id' => (int) $row->id,
                'title' => sanitize_text_field($row->title),
                'slug' => sanitize_title($slug_value),
                'status' => sanitize_key($row->status),
                'type' => sanitize_text_field($row->type),
                'accent_color' => $accent_color,
                'start_date' => sanitize_text_field($row->date_debut),
                'end_date' => sanitize_text_field($row->date_fin),
                'deadline' => sanitize_text_field($row->date_fin_inscription),
                'price' => isset($row->prix) ? (float) $row->prix : 0.0,
                'age_min' => isset($row->age_min) ? (int) $row->age_min : 0,
                'age_max' => isset($row->age_max) ? (int) $row->age_max : 0,
                'cover_id' => $cover_id,
                'cover_url' => $cover_url,
                'cover_thumb' => $cover_thumb_url !== '' ? esc_url_raw($cover_thumb_url) : $cover_url,
                'article_id' => $article_id,
                'article_permalink' => $article_permalink,
                'article_cover_url' => $article_cover_url,
                'article_cover_thumb' => $article_cover_thumb_url,
                'excerpt' => $excerpt,
                'description' => $description,
                'permalink' => esc_url_raw(is_string($permalink) ? $permalink : ''),
                'schedule_mode' => $schedule_mode,
                'schedule_payload' => $schedule_payload,
                'recurrence_until' => $recurrence_until,
                'location' => $location_label,
                'raw_location_name' => isset($row->location_name) ? sanitize_text_field($row->location_name) : '',
                'location_id' => isset($row->location_id) ? (int) $row->location_id : 0,
                'location_address' => $location_address,
                'location_map' => $location_map_embed,
                'location_map_link' => $location_map_link,
                'location_description' => $location_notes,
                'location_cover_id' => $location_cover_id,
                'location_cover' => $location_cover_url,
                'location_types' => $location_type_slugs,
                'location_type_labels' => $location_type_labels,
                'location_type_primary' => !empty($location_type_slugs) ? $location_type_slugs[0] : '',
                'location_type_primary_label' => !empty($location_type_slugs) && isset($location_type_labels[$location_type_slugs[0]]) ? $location_type_labels[$location_type_slugs[0]] : '',
                'allow_guardian_registration' => ($supports_guardian_toggle && isset($row->allow_guardian_registration)) ? (int) $row->allow_guardian_registration : 0,
            );
        }

        return $results;
    }
}

if (!function_exists('mj_member_format_event_datetime_range')) {
    /**
     * Format a human readable datetime range for events.
     *
     * @param string $start
     * @param string $end
     * @return string
     */
    function mj_member_format_event_datetime_range($start, $end) {
        $start = trim((string) $start);
        $end = trim((string) $end);

        if ($start === '' && $end === '') {
            return '';
        }

        $start_ts = $start !== '' ? strtotime($start) : false;
        $end_ts = $end !== '' ? strtotime($end) : false;

        if (!$start_ts && !$end_ts) {
            return '';
        }

        if ($start_ts && $end_ts) {
            if (wp_date('d/m/Y', $start_ts) === wp_date('d/m/Y', $end_ts)) {
                return sprintf(
                    '%s %s - %s',
                    wp_date(get_option('date_format', 'd/m/Y'), $start_ts),
                    wp_date(get_option('time_format', 'H:i'), $start_ts),
                    wp_date(get_option('time_format', 'H:i'), $end_ts)
                );
            }

            return sprintf(
                '%s &rarr; %s',
                wp_date(get_option('date_format', 'd/m/Y H:i'), $start_ts),
                wp_date(get_option('date_format', 'd/m/Y H:i'), $end_ts)
            );
        }

        if ($start_ts) {
            return wp_date(get_option('date_format', 'd/m/Y H:i'), $start_ts);
        }

        return wp_date(get_option('date_format', 'd/m/Y H:i'), $end_ts);
    }
}

if (!function_exists('mj_member_build_event_permalink')) {
    function mj_member_build_event_permalink($slug) {
        $slug = sanitize_title($slug);
        if ($slug === '') {
            return '';
        }

        return home_url('/date/' . rawurlencode($slug));
    }
}

if (!function_exists('mj_member_resolve_event_permalink')) {
    function mj_member_resolve_event_permalink($permalink, $event) {
        if (!empty($permalink)) {
            return $permalink;
        }

        $slug_reference = '';
        if (is_array($event) && isset($event['slug'])) {
            $slug_reference = $event['slug'];
        } elseif (is_object($event) && isset($event->slug)) {
            $slug_reference = $event->slug;
        }

        $slug_reference = sanitize_title($slug_reference);

        if ($slug_reference === '' && is_array($event) && isset($event['id'])) {
            $slug_reference = MjEvents_CRUD::get_or_create_slug((int) $event['id']);
        } elseif ($slug_reference === '' && is_object($event) && isset($event->id)) {
            $slug_reference = MjEvents_CRUD::get_or_create_slug((int) $event->id);
        }

        if ($slug_reference === '') {
            return '';
        }

        return mj_member_build_event_permalink($slug_reference);
    }

    add_filter('mj_member_event_permalink', 'mj_member_resolve_event_permalink', 10, 2);
}

if (!function_exists('mj_member_normalize_hex_color_value')) {
    function mj_member_normalize_hex_color_value($value) {
        $candidate = sanitize_hex_color($value);
        if (!is_string($candidate) || $candidate === '') {
            return '';
        }

        if (strlen($candidate) === 4) {
            $candidate = '#' . $candidate[1] . $candidate[1] . $candidate[2] . $candidate[2] . $candidate[3] . $candidate[3];
        }

        return strtoupper($candidate);
    }
}

if (!function_exists('mj_member_event_hex_to_rgb')) {
    function mj_member_event_hex_to_rgb($value) {
        $normalized = mj_member_normalize_hex_color_value($value);
        if ($normalized === '') {
            return null;
        }

        $normalized = ltrim($normalized, '#');
        if (strlen($normalized) !== 6) {
            return null;
        }

        return array(
            hexdec(substr($normalized, 0, 2)),
            hexdec(substr($normalized, 2, 2)),
            hexdec(substr($normalized, 4, 2)),
        );
    }
}

if (!function_exists('mj_member_mix_hex_colors')) {
    function mj_member_mix_hex_colors($base, $blend, $ratio) {
        $base_rgb = mj_member_event_hex_to_rgb($base);
        $blend_rgb = mj_member_event_hex_to_rgb($blend);
        if ($base_rgb === null || $blend_rgb === null) {
            return mj_member_normalize_hex_color_value($base);
        }

        $ratio = max(0.0, min(1.0, (float) $ratio));

        $mixed = array(
            (int) round($base_rgb[0] * (1 - $ratio) + $blend_rgb[0] * $ratio),
            (int) round($base_rgb[1] * (1 - $ratio) + $blend_rgb[1] * $ratio),
            (int) round($base_rgb[2] * (1 - $ratio) + $blend_rgb[2] * $ratio),
        );

        return sprintf('#%02X%02X%02X', $mixed[0], $mixed[1], $mixed[2]);
    }
}

if (!function_exists('mj_member_pick_contrast_color')) {
    function mj_member_pick_contrast_color($hex) {
        $rgb = mj_member_event_hex_to_rgb($hex);
        if ($rgb === null) {
            return '#FFFFFF';
        }

        $luminance = (0.2126 * $rgb[0]) + (0.7152 * $rgb[1]) + (0.0722 * $rgb[2]);

        return $luminance >= 150 ? '#0F172A' : '#FFFFFF';
    }
}

if (!function_exists('mj_member_build_event_palette_data')) {
    function mj_member_build_event_palette_data($accent_color, $type_key) {
        $palette_reference = method_exists('MjEvents_CRUD', 'get_type_colors') ? MjEvents_CRUD::get_type_colors() : array();

        $accent = mj_member_normalize_hex_color_value($accent_color);
        $type_key = sanitize_key($type_key);
        if ($accent === '' && $type_key !== '' && isset($palette_reference[$type_key])) {
            $accent = mj_member_normalize_hex_color_value($palette_reference[$type_key]);
        }

        if ($accent === '') {
            $accent = '#2563EB';
        }

        $contrast = mj_member_pick_contrast_color($accent);

        return array(
            'base' => $accent,
            'contrast' => $contrast,
            'surface' => mj_member_mix_hex_colors($accent, '#FFFFFF', 0.86),
            'border' => mj_member_mix_hex_colors($accent, '#FFFFFF', 0.7),
            'pill_bg' => mj_member_mix_hex_colors($accent, '#FFFFFF', 0.82),
            'pill_text' => $accent,
            'thumb_bg' => mj_member_mix_hex_colors($accent, '#FFFFFF', 0.9),
            'highlight' => mj_member_mix_hex_colors($accent, '#FFFFFF', 0.78),
            'range_bg' => mj_member_mix_hex_colors($accent, '#FFFFFF', 0.75),
            'range_border' => mj_member_mix_hex_colors($accent, '#FFFFFF', 0.55),
        );
    }
}

if (!function_exists('mj_member_prepare_event_page_context')) {
    function mj_member_prepare_event_page_context($requested_slug) {
        if (!class_exists('MjEvents_CRUD')) {
            return null;
        }

        $event_record = MjEvents_CRUD::find_by_slug($requested_slug);
        if (!$event_record || !isset($event_record->id)) {
            return null;
        }

        $event_id = (int) $event_record->id;
        if ($event_id <= 0) {
            return null;
        }

        $status_labels = MjEvents_CRUD::get_status_labels();
        $status_keys = array_keys($status_labels);

        $public_event_list = mj_member_get_public_events(
            array(
                'ids' => array($event_id),
                'statuses' => $status_keys,
                'include_past' => true,
                'limit' => 1,
            )
        );

        $event_data = !empty($public_event_list) ? $public_event_list[0] : array();

        if (empty($event_data)) {
            $slug = MjEvents_CRUD::get_or_create_slug($event_id);
            $event_data = array(
                'id' => $event_id,
                'title' => isset($event_record->title) ? sanitize_text_field($event_record->title) : '',
                'slug' => $slug,
                'status' => isset($event_record->status) ? sanitize_key($event_record->status) : '',
                'type' => isset($event_record->type) ? sanitize_key($event_record->type) : '',
                'accent_color' => mj_member_normalize_hex_color_value(isset($event_record->accent_color) ? $event_record->accent_color : ''),
                'start_date' => isset($event_record->date_debut) ? sanitize_text_field($event_record->date_debut) : '',
                'end_date' => isset($event_record->date_fin) ? sanitize_text_field($event_record->date_fin) : '',
                'deadline' => isset($event_record->date_fin_inscription) ? sanitize_text_field($event_record->date_fin_inscription) : '',
                'price' => isset($event_record->prix) ? (float) $event_record->prix : 0.0,
                'age_min' => isset($event_record->age_min) ? (int) $event_record->age_min : 0,
                'age_max' => isset($event_record->age_max) ? (int) $event_record->age_max : 0,
                'description' => isset($event_record->description) ? wp_kses_post($event_record->description) : '',
                'excerpt' => '',
                'cover_id' => isset($event_record->cover_id) ? (int) $event_record->cover_id : 0,
                'cover_url' => '',
                'cover_thumb' => '',
                'article_id' => isset($event_record->article_id) ? (int) $event_record->article_id : 0,
                'article_permalink' => '',
                'article_cover_url' => '',
                'article_cover_thumb' => '',
                'permalink' => mj_member_build_event_permalink($slug),
                'schedule_mode' => isset($event_record->schedule_mode) ? sanitize_key($event_record->schedule_mode) : 'fixed',
                'schedule_payload' => array(),
                'location' => '',
                'raw_location_name' => '',
                'location_id' => isset($event_record->location_id) ? (int) $event_record->location_id : 0,
                'location_address' => '',
                'location_map' => '',
                'location_map_link' => '',
                'location_description' => '',
                'location_cover_id' => 0,
                'location_cover' => '',
                'location_types' => array(),
                'location_type_labels' => array(),
                'location_type_primary' => '',
                'location_type_primary_label' => '',
                'allow_guardian_registration' => isset($event_record->allow_guardian_registration) ? (int) $event_record->allow_guardian_registration : 0,
            );

            if ($event_data['cover_id'] > 0) {
                $cover = wp_get_attachment_image_src($event_data['cover_id'], 'large');
                if (!empty($cover[0])) {
                    $event_data['cover_url'] = esc_url_raw($cover[0]);
                }
                $thumb = wp_get_attachment_image_src($event_data['cover_id'], 'medium');
                if (!empty($thumb[0])) {
                    $event_data['cover_thumb'] = esc_url_raw($thumb[0]);
                }
            }

            if ($event_data['article_id'] > 0) {
                $article_status = get_post_status($event_data['article_id']);
                if ($article_status && $article_status !== 'trash') {
                    $permalink_candidate = get_permalink($event_data['article_id']);
                    if (!empty($permalink_candidate)) {
                        $event_data['article_permalink'] = esc_url_raw($permalink_candidate);
                    }
                    $article_cover = get_the_post_thumbnail_url($event_data['article_id'], 'large');
                    if (!empty($article_cover)) {
                        $event_data['article_cover_url'] = esc_url_raw($article_cover);
                    }
                    $article_cover_thumb = get_the_post_thumbnail_url($event_data['article_id'], 'medium');
                    if (!empty($article_cover_thumb)) {
                        $event_data['article_cover_thumb'] = esc_url_raw($article_cover_thumb);
                    }
                }
            }
        } else {
            $event_data['slug'] = sanitize_title(isset($event_data['slug']) ? $event_data['slug'] : MjEvents_CRUD::get_or_create_slug($event_id));
            if (empty($event_data['permalink'])) {
                $event_data['permalink'] = mj_member_build_event_permalink($event_data['slug']);
            }
        }

        $type_key = isset($event_data['type']) ? sanitize_key($event_data['type']) : '';
        $accent_color = isset($event_data['accent_color']) ? mj_member_normalize_hex_color_value($event_data['accent_color']) : '';
        if ($accent_color === '' && $type_key !== '') {
            $accent_color = mj_member_normalize_hex_color_value(MjEvents_CRUD::get_default_color_for_type($type_key));
        }
        if ($accent_color === '') {
            $accent_color = '#2563EB';
        }
        $event_data['accent_color'] = $accent_color;
        $event_data['palette'] = mj_member_build_event_palette_data($accent_color, $type_key);

        $type_labels = MjEvents_CRUD::get_type_labels();
        $event_data['type_label'] = isset($type_labels[$type_key]) ? $type_labels[$type_key] : ($type_key !== '' ? ucfirst($type_key) : '');

        $event_data['date_label'] = mj_member_format_event_datetime_range(
            isset($event_data['start_date']) ? $event_data['start_date'] : '',
            isset($event_data['end_date']) ? $event_data['end_date'] : ''
        );

        $event_data['deadline_label'] = '';
        $deadline_raw = isset($event_data['deadline']) ? (string) $event_data['deadline'] : '';
        if ($deadline_raw !== '' && $deadline_raw !== '0000-00-00 00:00:00') {
            $deadline_ts = strtotime($deadline_raw);
            if ($deadline_ts) {
                $event_data['deadline_label'] = wp_date(get_option('date_format', 'd/m/Y H:i'), $deadline_ts);
            }
        }

        $price_value = isset($event_data['price']) ? (float) $event_data['price'] : 0.0;
        $event_data['price_label'] = $price_value > 0
            ? sprintf(__('Tarif : %s €', 'mj-member'), number_format_i18n($price_value, 2))
            : __('Tarif : Gratuit', 'mj-member');

        $age_min = isset($event_data['age_min']) ? (int) $event_data['age_min'] : 0;
        $age_max = isset($event_data['age_max']) ? (int) $event_data['age_max'] : 0;
        $event_data['age_label'] = '';
        if ($age_min > 0 || $age_max > 0) {
            if ($age_min > 0 && $age_max > 0) {
                $event_data['age_label'] = sprintf(__('Âges : %d - %d ans', 'mj-member'), $age_min, $age_max);
            } elseif ($age_min > 0) {
                $event_data['age_label'] = sprintf(__('À partir de %d ans', 'mj-member'), $age_min);
            } else {
                $event_data['age_label'] = sprintf(__('Jusqu’à %d ans', 'mj-member'), $age_max);
            }
        }

        $registration_url = apply_filters('mj_member_event_registration_url', '', $event_data);
        if ($registration_url === '') {
            if (!empty($event_data['article_permalink'])) {
                $registration_url = $event_data['article_permalink'];
            } else {
                $registration_url = apply_filters('mj_member_default_registration_url', home_url('/mon-compte'), $event_data);
            }
        }
        $event_data['registration_url'] = esc_url_raw($registration_url);

        $event_data['has_map'] = !empty($event_data['location_map']);
        $event_data['has_location'] = !empty($event_data['location']) || !empty($event_data['location_address']);

        $location_details = array(
            'name' => $event_data['raw_location_name'] !== '' ? $event_data['raw_location_name'] : $event_data['location'],
            'label' => $event_data['location'],
            'address' => $event_data['location_address'],
            'address_line' => '',
            'postal_code' => '',
            'city' => '',
            'country' => '',
            'notes' => $event_data['location_description'],
            'description' => $event_data['location_description'],
            'map' => $event_data['location_map'],
            'map_link' => $event_data['location_map_link'],
            'cover' => $event_data['location_cover'],
            'cover_id' => $event_data['location_cover_id'],
            'types' => array_values($event_data['location_type_labels']),
            'type_labels' => $event_data['location_type_labels'],
        );

        if (!empty($event_data['location_id']) && class_exists('MjEventLocations')) {
            $location_row = MjEventLocations::find((int) $event_data['location_id']);
            if ($location_row) {
                $location_array = (array) $location_row;
                if (!empty($location_array['name'])) {
                    $location_details['name'] = sanitize_text_field($location_array['name']);
                    if ($location_details['label'] === '') {
                        $location_details['label'] = $location_details['name'];
                    }
                }
                if (!empty($location_array['address_line'])) {
                    $location_details['address_line'] = sanitize_text_field($location_array['address_line']);
                }
                if (!empty($location_array['postal_code'])) {
                    $location_details['postal_code'] = sanitize_text_field($location_array['postal_code']);
                }
                if (!empty($location_array['city'])) {
                    $location_details['city'] = sanitize_text_field($location_array['city']);
                }
                if (!empty($location_array['country'])) {
                    $location_details['country'] = sanitize_text_field($location_array['country']);
                }
                if (!empty($location_array['notes'])) {
                    $location_details['notes'] = sanitize_textarea_field($location_array['notes']);
                }

                if ($location_details['address'] === '') {
                    $formatted_address = MjEventLocations::format_address($location_array);
                    if (!empty($formatted_address)) {
                        $location_details['address'] = sanitize_text_field($formatted_address);
                    }
                }

                if ($location_details['map'] === '') {
                    $map_embed = MjEventLocations::build_map_embed_src($location_array);
                    if (!empty($map_embed)) {
                        $location_details['map'] = esc_url_raw($map_embed);
                        $map_link = $map_embed;
                        if (strpos($map_link, 'output=embed') !== false) {
                            $map_link = str_replace(array('&output=embed', '?output=embed'), '', $map_link);
                        }
                        $location_details['map_link'] = esc_url_raw($map_link);
                    }
                }

                if ($location_details['cover'] === '' && !empty($location_array['cover_id'])) {
                    $cover_id = (int) $location_array['cover_id'];
                    if ($cover_id > 0) {
                        $cover_image = wp_get_attachment_image_src($cover_id, 'medium');
                        if (!empty($cover_image[0])) {
                            $location_details['cover'] = esc_url_raw($cover_image[0]);
                        }
                    }
                }

                if (empty($location_details['types']) && method_exists('MjEventLocations', 'extract_types')) {
                    $extracted_types = MjEventLocations::extract_types($location_array);
                    if (!empty($extracted_types)) {
                        $type_labels = array();
                        foreach ($extracted_types as $type_entry) {
                            if (!is_array($type_entry)) {
                                continue;
                            }
                            $type_label = isset($type_entry['label']) ? sanitize_text_field($type_entry['label']) : '';
                            if ($type_label === '') {
                                continue;
                            }
                            $type_labels[] = $type_label;
                        }
                        if (!empty($type_labels)) {
                            $location_details['types'] = array_values(array_unique($type_labels));
                        }
                    }
                }
            }
        }

        $location_details['address_components'] = array(
            'address_line' => $location_details['address_line'],
            'postal_code' => $location_details['postal_code'],
            'city' => $location_details['city'],
            'country' => $location_details['country'],
        );

        $registration_context = function_exists('mj_member_build_event_registration_context')
            ? mj_member_build_event_registration_context($event_data)
            : array();

        $animateur_items = array();
        if (class_exists('MjEventAnimateurs')) {
            $animateur_rows = MjEventAnimateurs::get_members_by_event($event_id);
            if (!empty($animateur_rows)) {
                $role_labels = class_exists('MjMembers_CRUD') ? MjMembers_CRUD::getRoleLabels() : array();
                foreach ($animateur_rows as $index => $animateur_row) {
                    if (!is_object($animateur_row)) {
                        continue;
                    }

                    $member_id = isset($animateur_row->id) ? (int) $animateur_row->id : 0;
                    $first_name = isset($animateur_row->first_name) ? sanitize_text_field($animateur_row->first_name) : '';
                    $last_name = isset($animateur_row->last_name) ? sanitize_text_field($animateur_row->last_name) : '';
                    $full_name = trim($first_name . ' ' . $last_name);
                    if ($full_name === '') {
                        $full_name = $member_id > 0 ? sprintf(__('Membre #%d', 'mj-member'), $member_id) : __('Animateur', 'mj-member');
                    }

                    $role_key = isset($animateur_row->role) ? sanitize_key($animateur_row->role) : '';
                    $role_label = ($role_key !== '' && isset($role_labels[$role_key]))
                        ? $role_labels[$role_key]
                        : ($role_key !== '' ? ucfirst($role_key) : '');

                    $email = '';
                    if (!empty($animateur_row->email) && is_email($animateur_row->email)) {
                        $email = sanitize_email($animateur_row->email);
                    }

                    $phone = '';
                    if (!empty($animateur_row->phone)) {
                        $phone = sanitize_text_field($animateur_row->phone);
                    }

                    $bio = '';
                    if (!empty($animateur_row->description_courte)) {
                        $bio = sanitize_textarea_field($animateur_row->description_courte);
                    }

                    $avatar_url = '';
                    if (!empty($animateur_row->photo_id)) {
                        $photo_id = (int) $animateur_row->photo_id;
                        if ($photo_id > 0) {
                            $photo = wp_get_attachment_image_src($photo_id, 'medium');
                            if (!empty($photo[0])) {
                                $avatar_url = esc_url_raw($photo[0]);
                            }
                        }
                    }

                    if ($avatar_url === '' && !empty($animateur_row->wp_user_id)) {
                        $avatar_url = esc_url_raw(get_avatar_url((int) $animateur_row->wp_user_id, array('size' => 256)));
                    }

                    if ($avatar_url === '' && $email !== '') {
                        $avatar_url = esc_url_raw(get_avatar_url($email, array('size' => 256)));
                    }

                    $initials = '';
                    if ($first_name !== '') {
                        $initials .= function_exists('mb_substr') ? mb_substr($first_name, 0, 1) : substr($first_name, 0, 1);
                    }
                    if ($last_name !== '') {
                        $initials .= function_exists('mb_substr') ? mb_substr($last_name, 0, 1) : substr($last_name, 0, 1);
                    }
                    if ($initials === '' && $full_name !== '') {
                        $initials = function_exists('mb_substr') ? mb_substr($full_name, 0, 1) : substr($full_name, 0, 1);
                    }
                    if (function_exists('mb_strtoupper')) {
                        $initials = mb_strtoupper($initials);
                    } else {
                        $initials = strtoupper($initials);
                    }

                    $animateur_items[] = array(
                        'id' => $member_id,
                        'full_name' => $full_name,
                        'first_name' => $first_name,
                        'last_name' => $last_name,
                        'role' => $role_key,
                        'role_label' => $role_label,
                        'email' => $email,
                        'phone' => $phone,
                        'bio' => $bio,
                        'avatar_url' => $avatar_url,
                        'has_avatar' => $avatar_url !== '',
                        'initials' => $initials,
                        'is_primary' => ($index === 0),
                        'avatar_alt' => sprintf(__('Portrait de %s', 'mj-member'), $full_name),
                    );
                }
            }
        }

        $animateurs_context = array(
            'items' => $animateur_items,
            'count' => count($animateur_items),
            'has_items' => !empty($animateur_items),
        );

        $context = array(
            'event' => $event_data,
            'record' => $event_record,
            'registration' => $registration_context,
            'location' => $location_details,
            'animateurs' => $animateurs_context,
        );

        return apply_filters('mj_member_event_page_context', $context, $requested_slug);
    }
}

if (!function_exists('mj_member_event_template_include')) {
    function mj_member_event_template_include($template) {
        $slug = get_query_var('mj_event_slug');
        if ($slug === '' || $slug === null) {
            return $template;
        }

        $context = mj_member_prepare_event_page_context($slug);
        if (!$context) {
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            $fallback = get_404_template();
            return $fallback ? $fallback : $template;
        }

        $GLOBALS['mj_member_event_context'] = $context;

        $theme_template = locate_template(array('mj-member/event-single.php'));
        if (!empty($theme_template)) {
            return $theme_template;
        }

        return MJ_MEMBER_PATH . 'templates/event-single.php';
    }

    add_filter('template_include', 'mj_member_event_template_include', 99);
}

if (!function_exists('mj_member_ajax_register_event')) {
    function mj_member_ajax_register_event() {
        if (!wp_doing_ajax()) {
            return;
        }

        check_ajax_referer('mj-member-event-register', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(
                array('message' => __('Vous devez être connecté pour vous inscrire à cet événement.', 'mj-member')),
                401
            );
        }

        $event_id = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;
        $member_id = isset($_POST['member_id']) ? (int) $_POST['member_id'] : 0;

        if ($event_id <= 0 || $member_id <= 0) {
            wp_send_json_error(
                array('message' => __('Requête invalide. Veuillez réessayer.', 'mj-member')),
                400
            );
        }

        if (!class_exists('MjEvents_CRUD') || !class_exists('MjEventRegistrations') || !class_exists('MjMembers_CRUD')) {
            wp_send_json_error(
                array('message' => __('Le module événements est indisponible pour le moment.', 'mj-member')),
                500
            );
        }

        $event = MjEvents_CRUD::find($event_id);
        if (!$event) {
            wp_send_json_error(
                array('message' => __('Événement introuvable.', 'mj-member')),
                404
            );
        }

        $now = current_time('timestamp');
        $deadline_passed = false;
        $closed_by_start = false;
        $has_future_occurrence = false;
        if (!empty($event->date_fin_inscription) && $event->date_fin_inscription !== '0000-00-00 00:00:00') {
            $deadline_ts = strtotime($event->date_fin_inscription);
            if ($deadline_ts && $now > $deadline_ts) {
                $deadline_passed = true;
            }
        }

        if (!$deadline_passed && !empty($event->date_debut) && $event->date_debut !== '0000-00-00 00:00:00') {
            $start_ts = strtotime($event->date_debut);
            if ($start_ts && $now > $start_ts) {
                $deadline_passed = true;
                $closed_by_start = true;
            }
        }

        if ($deadline_passed && $closed_by_start && class_exists('MjEventSchedule')) {
            $schedule_args = array(
                'max' => 40,
                'include_past' => true,
            );
            $occurrence_list = MjEventSchedule::get_occurrences($event, $schedule_args);
            if (!empty($occurrence_list) && is_array($occurrence_list)) {
                foreach ($occurrence_list as $occurrence_entry) {
                    if (!is_array($occurrence_entry)) {
                        continue;
                    }
                    $occurrence_timestamp = 0;
                    if (isset($occurrence_entry['timestamp'])) {
                        $occurrence_timestamp = (int) $occurrence_entry['timestamp'];
                    }
                    if ($occurrence_timestamp <= 0 && !empty($occurrence_entry['start'])) {
                        $occurrence_timestamp = strtotime((string) $occurrence_entry['start']);
                    }
                    if ($occurrence_timestamp && $occurrence_timestamp > $now) {
                        $deadline_passed = false;
                        $has_future_occurrence = true;
                        break;
                    }
                }
            }
        }

        if ($deadline_passed) {
            wp_send_json_error(
                array('message' => __('Les inscriptions sont clôturées pour cet événement.', 'mj-member')),
                409
            );
        }

        $current_member = function_exists('mj_member_get_current_member') ? mj_member_get_current_member() : null;
        if (!$current_member || empty($current_member->id)) {
            wp_send_json_error(
                array('message' => __('Votre profil membre est introuvable. Contactez l’équipe MJ.', 'mj-member')),
                403
            );
        }

        $allowed_member_ids = array((int) $current_member->id);
        $guardian_id = 0;

        if (function_exists('mj_member_can_manage_children') && mj_member_can_manage_children($current_member)) {
            $guardian_id = (int) $current_member->id;
            if (function_exists('mj_member_get_guardian_children')) {
                $children = mj_member_get_guardian_children($current_member);
                if (!empty($children) && is_array($children)) {
                    foreach ($children as $child) {
                        if (!$child || !isset($child->id)) {
                            continue;
                        }
                        $allowed_member_ids[] = (int) $child->id;
                    }
                }
            }
        } elseif (!empty($current_member->guardian_id)) {
            $guardian_id = (int) $current_member->guardian_id;
        }

        if (!in_array($member_id, $allowed_member_ids, true)) {
            wp_send_json_error(
                array('message' => __('Vous ne pouvez pas inscrire ce participant.', 'mj-member')),
                403
            );
        }

        $participant = MjMembers_CRUD::getById($member_id);
        if (!$participant) {
            wp_send_json_error(
                array('message' => __('Profil membre introuvable.', 'mj-member')),
                404
            );
        }

        $existing_registration = MjEventRegistrations::get_existing($event_id, $member_id);
        if ($existing_registration && (!isset($existing_registration->statut) || $existing_registration->statut !== MjEventRegistrations::STATUS_CANCELLED)) {
            wp_send_json_error(
                array('message' => __('Ce participant est déjà inscrit à cet événement.', 'mj-member')),
                409
            );
        }

        $create_args = array();
        if ($guardian_id > 0) {
            $create_args['guardian_id'] = $guardian_id;
        }

        $note_value = isset($_POST['note']) ? sanitize_textarea_field(wp_unslash($_POST['note'])) : '';
        if ($note_value !== '') {
            if (function_exists('mb_substr')) {
                $note_value = mb_substr($note_value, 0, 400);
            } else {
                $note_value = substr($note_value, 0, 400);
            }
            $create_args['notes'] = $note_value;
        }

        $occurrence_selection = array();
        if (isset($_POST['occurrences'])) {
            $occurrences_raw = wp_unslash($_POST['occurrences']);
            $decoded_occurrences = json_decode($occurrences_raw, true);
            if (is_array($decoded_occurrences)) {
                foreach ($decoded_occurrences as $occurrence_entry) {
                    if (!is_string($occurrence_entry) && !is_numeric($occurrence_entry)) {
                        continue;
                    }

                    $normalized_value = sanitize_text_field((string) $occurrence_entry);
                    if ($normalized_value === '') {
                        continue;
                    }

                    if (class_exists('MjEventAttendance')) {
                        $candidate_normalized = MjEventAttendance::normalize_occurrence($normalized_value);
                        if ($candidate_normalized === '') {
                            continue;
                        }
                        $normalized_value = $candidate_normalized;
                    }

                    $occurrence_selection[$normalized_value] = $normalized_value;
                }
            }
        }

        if ($has_future_occurrence) {
            $create_args['allow_late_registration'] = true;
        }

        $result = MjEventRegistrations::create($event_id, $member_id, $create_args);

        if (is_wp_error($result)) {
            wp_send_json_error(
                array('message' => $result->get_error_message()),
                400
            );
        }

        $registration_context = array();
        if (method_exists('MjEventRegistrations', 'get_last_creation_context')) {
            $registration_context = MjEventRegistrations::get_last_creation_context();
        }

        $is_waitlist = !empty($registration_context['is_waitlist']);
        $event_price = isset($event->prix) ? (float) $event->prix : 0.0;
        $payment_required = !$is_waitlist && $event_price > 0;
        $payment_payload = null;
        $payment_error = false;

        if ($payment_required) {
            if (class_exists('MjPayments')) {
                $payment_payload = MjPayments::create_stripe_payment(
                    $member_id,
                    $event_price,
                    array(
                        'context' => 'event',
                        'event_id' => (int) $event->id,
                        'registration_id' => (int) $result,
                        'payer_id' => (!empty($current_member->id) ? (int) $current_member->id : 0),
                        'event' => $event,
                    )
                );

                if (!$payment_payload || empty($payment_payload['checkout_url'])) {
                    $payment_payload = null;
                    $payment_error = true;
                }
            } else {
                $payment_error = true;
            }
        }

        if ($payment_required && $payment_error) {
            error_log(sprintf('MJ Member: echec creation paiement Stripe pour event #%d inscription #%d', (int) $event_id, (int) $result));
        }

        $success_message = __('Inscription enregistrée ! Nous reviendrons vers vous rapidement.', 'mj-member');
        if ($is_waitlist) {
            $success_message = __('Inscription enregistrée sur liste d\'attente. Nous vous informerons dès qu\'une place se libère.', 'mj-member');
        } elseif ($payment_required && !$payment_error) {
            $success_message = __('Inscription enregistrée ! Merci de finaliser le paiement sécurisé.', 'mj-member');
        } elseif ($payment_required && $payment_error) {
            $success_message = __('Inscription enregistrée, mais la création du paiement a échoué. Merci de contacter l\'équipe MJ pour finaliser le règlement.', 'mj-member');
        }

        do_action('mj_member_event_registration_created', $result, $event_id, $member_id, $current_member);

        if (!empty($occurrence_selection) && class_exists('MjEventAttendance')) {
            $assignment_result = MjEventAttendance::set_registration_assignments(
                (int) $result,
                array(
                    'mode' => 'custom',
                    'occurrences' => array_values($occurrence_selection),
                )
            );

            if (is_wp_error($assignment_result)) {
                error_log(sprintf('MJ Member: echec affectation occurrences pour inscription #%d (%s)', (int) $result, $assignment_result->get_error_message()));
            }
        }

        $response = array(
            'message' => $success_message,
            'registration_id' => (int) $result,
            'is_waitlist' => $is_waitlist,
            'payment_required' => $payment_required,
            'payment_error' => $payment_error,
        );

        if ($payment_payload) {
            $response['payment'] = array(
                'checkout_url' => $payment_payload['checkout_url'],
                'qr_url' => isset($payment_payload['qr_url']) ? $payment_payload['qr_url'] : '',
                'amount' => isset($payment_payload['amount']) ? $payment_payload['amount'] : '',
            );
        }

        wp_send_json_success($response);
    }

    add_action('wp_ajax_mj_member_register_event', 'mj_member_ajax_register_event');
    add_action('wp_ajax_nopriv_mj_member_register_event', 'mj_member_ajax_register_event');
}

if (!function_exists('mj_member_ajax_unregister_event')) {
    function mj_member_ajax_unregister_event() {
        if (!wp_doing_ajax()) {
            return;
        }

        check_ajax_referer('mj-member-event-register', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(
                array('message' => __('Vous devez être connecté pour gérer vos inscriptions.', 'mj-member')),
                401
            );
        }

        $event_id = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;
        $member_id = isset($_POST['member_id']) ? (int) $_POST['member_id'] : 0;
        $registration_id = isset($_POST['registration_id']) ? (int) $_POST['registration_id'] : 0;

        if ($event_id <= 0 || $member_id <= 0) {
            wp_send_json_error(
                array('message' => __('Requête invalide. Veuillez réessayer.', 'mj-member')),
                400
            );
        }

        if (!class_exists('MjEvents_CRUD') || !class_exists('MjEventRegistrations') || !class_exists('MjMembers_CRUD')) {
            wp_send_json_error(
                array('message' => __('Le module événements est indisponible pour le moment.', 'mj-member')),
                500
            );
        }

        $event = MjEvents_CRUD::find($event_id);
        if (!$event) {
            wp_send_json_error(
                array('message' => __('Événement introuvable.', 'mj-member')),
                404
            );
        }

        $current_member = function_exists('mj_member_get_current_member') ? mj_member_get_current_member() : null;
        if (!$current_member || empty($current_member->id)) {
            wp_send_json_error(
                array('message' => __('Votre profil membre est introuvable. Contactez l’équipe MJ.', 'mj-member')),
                403
            );
        }

        $allowed_member_ids = array((int) $current_member->id);

        if (function_exists('mj_member_can_manage_children') && mj_member_can_manage_children($current_member)) {
            if (function_exists('mj_member_get_guardian_children')) {
                $children = mj_member_get_guardian_children($current_member);
                if (!empty($children) && is_array($children)) {
                    foreach ($children as $child) {
                        if (!$child || !isset($child->id)) {
                            continue;
                        }
                        $allowed_member_ids[] = (int) $child->id;
                    }
                }
            }
        } elseif (!empty($current_member->guardian_id)) {
            $allowed_member_ids[] = (int) $current_member->guardian_id;
        }

        if (!in_array($member_id, $allowed_member_ids, true)) {
            wp_send_json_error(
                array('message' => __('Vous ne pouvez pas gérer ce participant.', 'mj-member')),
                403
            );
        }

        $existing_registration = MjEventRegistrations::get_existing($event_id, $member_id);
        if (!$existing_registration || (isset($existing_registration->statut) && $existing_registration->statut === MjEventRegistrations::STATUS_CANCELLED)) {
            wp_send_json_error(
                array('message' => __('Aucune inscription active à annuler.', 'mj-member')),
                404
            );
        }

        if ($registration_id > 0 && (int) $existing_registration->id !== $registration_id) {
            wp_send_json_error(
                array('message' => __('Inscription introuvable.', 'mj-member')),
                404
            );
        }

        $update = MjEventRegistrations::update_status((int) $existing_registration->id, MjEventRegistrations::STATUS_CANCELLED);
        if (is_wp_error($update)) {
            wp_send_json_error(
                array('message' => $update->get_error_message()),
                500
            );
        }

        do_action('mj_member_event_registration_cancelled', (int) $existing_registration->id, $event_id, $member_id, $current_member);

        wp_send_json_success(
            array('message' => __('Inscription annulée.', 'mj-member'))
        );
    }

    add_action('wp_ajax_mj_member_unregister_event', 'mj_member_ajax_unregister_event');
}
