<?php

use Mj\Member\Core\Config;
use Mj\Member\Classes\MjRoles;

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('mj_member_normalize_json_payload')) {
    function mj_member_normalize_json_payload($value) {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = mj_member_normalize_json_payload($item);
            }
            return $value;
        }

        if (is_object($value)) {
            return mj_member_normalize_json_payload((array) $value);
        }

        if (is_string($value)) {
            $clean = wp_check_invalid_utf8($value, true);
            if ($clean === '' && $value !== '') {
                if (function_exists('mb_convert_encoding')) {
                    $clean = @mb_convert_encoding($value, 'UTF-8', 'UTF-8');
                }
                if (!is_string($clean) || $clean === '') {
                    $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $value);
                }
                if (!is_string($clean) || $clean === '') {
                    $clean = '';
                }
            }

            $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $clean);
            if ($clean === null) {
                $clean = '';
            }

            return $clean;
        }

        return $value;
    }
}

    if (!function_exists('mj_member_user_can_view_internal_events')) {
        /**
         * Indique si l'utilisateur courant est autorisé à consulter les événements internes.
         *
         * @return bool
         */
        function mj_member_user_can_view_internal_events() {
            $member = null;

            if (is_user_logged_in() && function_exists('mj_member_get_current_member')) {
                $member = mj_member_get_current_member();
            }

            // Utiliser MjRoles si disponible, sinon fallback
            if (class_exists('Mj\\Member\\Classes\\MjRoles')) {
                $allowed_roles = \Mj\Member\Classes\MjRoles::getInternalEventViewerRoles();
            } elseif (class_exists('Mj\\Member\\Classes\\Crud\\MjMembers')) {
                $mj_members_class = 'Mj\\Member\\Classes\\Crud\\MjMembers';
                $allowed_roles = array(
                    sanitize_key((string) $mj_members_class::ROLE_COORDINATEUR),
                    sanitize_key((string) $mj_members_class::ROLE_ANIMATEUR),
                    sanitize_key((string) $mj_members_class::ROLE_BENEVOLE),
                );
            } else {
                // Fallback avec constantes MjRoles directes
                $allowed_roles = array(
                    \Mj\Member\Classes\MjRoles::COORDINATEUR,
                    \Mj\Member\Classes\MjRoles::ANIMATEUR,
                    \Mj\Member\Classes\MjRoles::BENEVOLE,
                );
            }

            $can_view = false;

            if ($member && is_object($member) && isset($member->role)) {
                $role = sanitize_key((string) $member->role);
                if ($role !== '' && in_array($role, $allowed_roles, true)) {
                    $can_view = true;
                }
            }

            if (!$can_view && function_exists('current_user_can')) {
                if (current_user_can('manage_options')) {
                    $can_view = true;
                } elseif (class_exists(Config::class) && current_user_can(Config::capability())) {
                    $can_view = true;
                }
            }

            return (bool) apply_filters('mj_member_can_view_internal_events', $can_view, $member);
        }
    }

if (!function_exists('mj_member_ajax_update_event_assignments')) {
    function mj_member_ajax_update_event_assignments() {
        if (!wp_doing_ajax()) {
            return;
        }

        check_ajax_referer('mj-member-event-register', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(
                array('message' => __('Connecte-toi pour gérer tes inscriptions.', 'mj-member')),
                401
            );
        }

        if (!class_exists('MjEvents') || !class_exists('MjEventRegistrations') || !class_exists('MjEventAttendance')) {
            wp_send_json_error(
                array('message' => __('Le module événements est indisponible pour le moment.', 'mj-member')),
                500
            );
        }

        $event_id = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;
        if ($event_id <= 0) {
            wp_send_json_error(
                array('message' => __('Événement introuvable.', 'mj-member')),
                400
            );
        }

        $event = MjEvents::find($event_id);
        if (!$event) {
            wp_send_json_error(
                array('message' => __('Événement introuvable.', 'mj-member')),
                404
            );
        }

        $member_id = isset($_POST['member_id']) ? (int) $_POST['member_id'] : 0;
        if ($member_id <= 0) {
            wp_send_json_error(
                array('message' => __('Participant invalide.', 'mj-member')),
                400
            );
        }

        $registration_id = isset($_POST['registration_id']) ? (int) $_POST['registration_id'] : 0;

        $occurrence_selection_mode = isset($event->occurrence_selection_mode) ? sanitize_key((string) $event->occurrence_selection_mode) : 'member_choice';
        if (!in_array($occurrence_selection_mode, array('member_choice', 'all_occurrences'), true)) {
            $occurrence_selection_mode = 'member_choice';
        }
        if ($occurrence_selection_mode === 'all_occurrences') {
            wp_send_json_error(
                array('message' => __('Ce format inscrit automatiquement chaque participant sur toutes les occurrences.', 'mj-member')),
                400
            );
        }


        $schedule_mode = isset($event->schedule_mode) ? sanitize_key((string) $event->schedule_mode) : 'fixed';
        if (!in_array($schedule_mode, array('fixed', 'range', 'recurring', 'series'), true)) {
            $schedule_mode = 'fixed';
        }
        if (!in_array($schedule_mode, array('recurring', 'series'), true)) {
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
if (!function_exists('mj_member_output_events_widget_styles')) {
    function mj_member_output_events_widget_styles() {
        static $printed = false;
        if ($printed) {
            return;
        }
        $printed = true;

        $handle = 'mj-member-events-widget';
        $base_url = trailingslashit(Config::url());
        $base_path = trailingslashit(Config::path());
        $relative = 'css/events-widget.css';
        $stylesheet_path = $base_path . $relative;
        $stylesheet_url = $base_url . $relative;

        if (!wp_style_is('mj-member-components', 'registered')) {
            $components_relative = 'css/styles.css';
            $components_path = $base_path . $components_relative;
            $components_url = $base_url . $components_relative;
            $components_version = file_exists($components_path) ? filemtime($components_path) : Config::version();
            wp_register_style('mj-member-components', $components_url, array(), $components_version);
        }

        if (file_exists($stylesheet_path) && is_readable($stylesheet_path)) {
            $version = filemtime($stylesheet_path) ?: Config::version();
            if (!wp_style_is($handle, 'registered')) {
                wp_register_style($handle, $stylesheet_url, array('mj-member-components'), $version);
            }

            wp_enqueue_style($handle);
            return;
        }

        if (!wp_style_is($handle, 'registered')) {
            wp_register_style($handle, false, array('mj-member-components'), Config::version());
        }

        $css = <<<'CSS'
    .mj-member-events{display:flex;flex-direction:column;gap:24px;color:var(--mj-events-text,#1f2937);background:var(--mj-events-surface,transparent);--mj-events-title-color:#0f172a;--mj-events-text:#1f2937;--mj-events-card-bg:#ffffff;--mj-events-border:#e2e8f0;--mj-events-border-soft:rgba(226,232,240,0.7);--mj-events-card-title:#0f172a;--mj-events-meta:#475569;--mj-events-excerpt:#475569;--mj-events-accent:#2563eb;--mj-events-accent-contrast:#ffffff;--mj-events-radius:18px;--mj-events-cover-min:220px;--mj-events-cover-radius:16px;--mj-events-surface-soft:rgba(248,250,252,0.92);--mj-events-grid-columns:3;--mj-events-wide-cover:320px;}
    .mj-member-events.is-wide{--mj-events-grid-columns:1;--mj-events-cover-min:260px;}
    .mj-member-events__title{margin:0;font-size:1.75rem;font-weight:700;color:var(--mj-events-title-color);}
    .mj-member-events__grid{display:grid;gap:20px;}
    .mj-member-events__grid.is-grid{grid-template-columns:repeat(var(--mj-events-grid-columns,3),minmax(0,1fr));}
    .mj-member-events__grid.is-list{grid-template-columns:1fr;}
    @media (max-width:1024px){.mj-member-events:not(.is-wide){--mj-events-grid-columns:2;}}
    @media (max-width:680px){.mj-member-events:not(.is-wide){--mj-events-grid-columns:1;}}
    .mj-member-events__item{position:relative;display:flex;flex-direction:column;border:1px solid var(--mj-events-border);border-radius:var(--mj-events-radius);overflow:hidden;background:var(--mj-events-card-bg);transition:box-shadow 0.25s ease,transform 0.25s ease;}
    .mj-member-events__item.layout-horizontal{flex-direction:row;}
    .mj-member-events.is-wide .mj-member-events__item{flex-direction:row;min-height:280px;}
    .mj-member-events__cover{position:relative;padding-bottom:56%;min-height:var(--mj-events-cover-min,220px);overflow:hidden;background:var(--mj-events-surface-soft);border-radius:var(--mj-events-cover-radius,var(--mj-events-radius)) var(--mj-events-cover-radius,var(--mj-events-radius)) 0 0;}
    .mj-member-events__cover.ratio-4-3{padding-bottom:75%;}
    .mj-member-events__cover.ratio-1-1{padding-bottom:100%;}
    .mj-member-events__cover.ratio-auto{padding-bottom:0;min-height:var(--mj-events-cover-min,220px);}
    .mj-member-events__cover.is-horizontal{flex:0 0 280px;padding-bottom:0;min-height:var(--mj-events-cover-min,220px);border-radius:var(--mj-events-cover-radius,var(--mj-events-radius)) 0 0 var(--mj-events-cover-radius,var(--mj-events-radius));}
    .mj-member-events.is-wide .mj-member-events__grid{gap:28px;}
    .mj-member-events.is-wide .mj-member-events__cover{flex:0 0 var(--mj-events-wide-cover,320px);padding-bottom:0;min-height:100%;border-radius:var(--mj-events-cover-radius,var(--mj-events-radius)) 0 0 var(--mj-events-cover-radius,var(--mj-events-radius));}
    .mj-member-events__cover-link{position:absolute;inset:0;display:block;}
    .mj-member-events__cover-link:focus-visible{outline:3px solid var(--mj-event-accent,var(--mj-events-accent));outline-offset:2px;border-radius:inherit;}
    .mj-member-events__cover img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;border-radius:inherit;}
    .mj-member-events.is-wide .mj-member-events__cover img{position:static;height:100%;}
    .mj-member-events__item.layout-horizontal .mj-member-events__cover img{position:static;}
    .mj-member-events__item-body{position:relative;display:flex;flex-direction:column;gap:14px;padding:22px;}
    .mj-member-events.is-wide .mj-member-events__item-body{padding:26px;gap:16px;}
    .mj-member-events__item.layout-compact .mj-member-events__item-body{padding:18px;gap:10px;}
    .mj-member-events__badge{display:inline-flex;align-items:center;gap:6px;padding:4px 12px;border-radius:999px;background:var(--mj-event-accent,var(--mj-events-accent));color:var(--mj-events-accent-contrast);font-size:0.78rem;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;}
    .mj-member-events__badge.is-overlay{position:absolute;top:14px;right:14px;box-shadow:0 12px 28px rgba(15,23,42,0.18);pointer-events:none;}
    .mj-member-events__item-title{margin:0;font-size:1.15rem;font-weight:700;color:var(--mj-events-card-title);}
    .mj-member-events__item-title a{text-decoration:none;color:inherit;font:inherit;}
    .mj-member-events__item-title a:hover{color:var(--mj-event-accent,var(--mj-events-accent));}
    .mj-member-events__recurring-summary{display:flex;flex-wrap:wrap;gap:6px 14px;margin:2px 0 0;font-size:0.92rem;color:var(--mj-events-meta);}
    .mj-member-events__recurring-heading{font-weight:700;color:var(--mj-events-card-title);}
    .mj-member-events__recurring-time{font-weight:500;color:var(--mj-events-meta);}
    .mj-member-events__excerpt{margin:0;font-size:0.95rem;line-height:1.56;color:var(--mj-events-excerpt);}
    .mj-member-events__price-chip{display:inline-flex;align-items:center;gap:8px;align-self:flex-start;padding:6px 12px;border-radius:999px;background:var(--mj-event-accent,var(--mj-events-accent));color:var(--mj-events-accent-contrast);font-size:0.9rem;font-weight:600;}
    .mj-member-events__price-chip-label{opacity:0.85;}
    .mj-member-events__location-card{display:flex;gap:14px;align-items:flex-start;padding:14px 16px;border:1px solid var(--mj-events-border-soft);border-radius:14px;background:var(--mj-events-surface-soft);}
    .mj-member-events__location-logo{width:64px;height:64px;flex:0 0 64px;border-radius:14px;object-fit:cover;border:1px solid var(--mj-events-border-soft);}
    .mj-member-events__location-content{display:flex;flex-direction:column;gap:4px;}
    .mj-member-events__location-name{margin:0;font-weight:700;color:var(--mj-events-card-title);}
    .mj-member-events__location-address{margin:0;font-size:0.9rem;color:var(--mj-events-meta);}
    .mj-member-events__location-note{margin:4px 0 0;font-size:0.88rem;color:var(--mj-events-meta);line-height:1.45;}
    .mj-member-events__occurrence-next{margin:6px 0 0;font-size:0.92rem;font-weight:600;color:var(--mj-events-card-title);}
    .mj-member-events__occurrences{margin:6px 0 0;padding:0;list-style:none;display:flex;flex-direction:column;gap:6px;font-size:0.88rem;color:var(--mj-events-meta);}
    .mj-member-events__occurrence{display:flex;align-items:flex-start;gap:8px;}
    .mj-member-events__occurrence-prefix{font-weight:600;color:var(--mj-events-card-title);}
    .mj-member-events__occurrence-label{flex:1;}
    .mj-member-events__occurrence.is-today .mj-member-events__occurrence-label{color:var(--mj-events-card-title);}
    .mj-member-events__occurrence--more{font-style:italic;}
    .mj-member-events__empty,.mj-member-events__filtered-empty{margin:0;font-size:0.95rem;color:#6b7280;}
    .mj-member-events__warning{margin:0;font-size:0.95rem;color:#b91c1c;}
    @media (max-width:780px){.mj-member-events__item.layout-horizontal{flex-direction:column;}.mj-member-events__cover.is-horizontal{width:100%;border-radius:var(--mj-events-cover-radius,var(--mj-events-radius)) var(--mj-events-cover-radius,var(--mj-events-radius)) 0 0;}.mj-member-events.is-wide .mj-member-events__cover{flex:1 1 auto;width:100%;min-height:var(--mj-events-cover-min,220px);}}
    CSS;

        wp_add_inline_style($handle, $css);
        wp_enqueue_style($handle);
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
                    'confirmationRequired' => __('Merci de confirmer ta participation en cochant la case.', 'mj-member'),
                    'confirmationRequiredPayment' => __('Merci de confirmer que tu finaliseras ton inscription et le paiement en cochant la case.', 'mj-member'),
                    'genericError' => __('Une erreur est survenue. Merci de réessayer.', 'mj-member'),
                    'registered' => __('Inscription envoyée', 'mj-member'),
                    'success' => __('Inscription enregistrée !', 'mj-member'),
                    'closed' => __('Inscriptions clôturées', 'mj-member'),
                    'loading' => __('En cours...', 'mj-member'),
                    'noParticipant' => __("Aucun profil disponible pour l'instant.", 'mj-member'),
                    'alreadyRegistered' => __('Déjà inscrit', 'mj-member'),
                    'ineligibleStatus' => __('Conditions non respectées', 'mj-member'),
                    'allRegistered' => __('Tous les profils sont déjà inscrits pour cet événement.', 'mj-member'),
                    'noEligibleParticipant' => __('Aucun profil éligible n’est disponible pour cette inscription.', 'mj-member'),
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
                    'occurrenceCalendarAllDay' => __('Toute la journée', 'mj-member'),
                    'occurrenceMissing' => __('Merci de sélectionner au moins une occurrence.', 'mj-member'),
                    'occurrencePast' => __('Passée', 'mj-member'),
                    'occurrenceEmpty' => __('Aucune occurrence disponible.', 'mj-member'),
                    'occurrenceRegisteredTitle' => __('Vos réservations', 'mj-member'),
                    'occurrenceAvailableTitle' => __('Autres dates disponibles', 'mj-member'),
                    'occurrenceRegisteredEmpty' => __('Aucune réservation active.', 'mj-member'),
                    'occurrenceAvailableEmpty' => __('Toutes les dates sont déjà réservées.', 'mj-member'),
                    'reservationsLoading' => __('Actualisation des réservations...', 'mj-member'),
                    'reservationsError' => __('Impossible de mettre à jour tes réservations. Merci de réessayer.', 'mj-member'),
                    'reservationsUpdated' => __('Tes réservations sont à jour.', 'mj-member'),
                    'occurrenceAutoAssigned' => __('Toutes les occurrences sont incluses automatiquement.', 'mj-member'),
                    'reservationUnknown' => __('Participant', 'mj-member'),
                    'reservationCreated' => __('Réservé le %s', 'mj-member'),
                    'paymentSummaryTitle' => __('Récapitulatif du paiement', 'mj-member'),
                    'paymentSummaryMessage' => __('Tu peux effectuer le paiement maintenant ou plus tard depuis ton espace membre ou en main propre auprès d’un animateur.', 'mj-member'),
                    'paymentSummaryAmountLabel' => __('Montant à régler', 'mj-member'),
                    'paymentSummaryPayNow' => __('Payer en ligne', 'mj-member'),
                    'paymentSummaryClose' => __('Fermer', 'mj-member'),
                ),
            )
        );

        $localized = true;
    }
}

if (!function_exists('mj_member_ensure_event_toggles_localized')) {
    function mj_member_ensure_event_toggles_localized() {
        static $localized = false;
        if ($localized) {
            return;
        }

        $status_labels = array();
        if (class_exists('MjEventRegistrations') && method_exists('MjEventRegistrations', 'get_status_labels')) {
            $status_labels = MjEventRegistrations::get_status_labels();
        }

        wp_localize_script(
            'mj-member-event-toggles',
            'mjMemberEventToggles',
            array(
                'ajaxUrl' => esc_url_raw(admin_url('admin-ajax.php')),
                'nonce' => wp_create_nonce('mj-member-event-register'),
                'loginUrl' => esc_url_raw(wp_login_url()),
                'statuses' => $status_labels,
                'strings' => array(
                    'loginRequired' => __('Connecte-toi pour gérer tes inscriptions.', 'mj-member'),
                    'successRegistered' => __('Inscription enregistrée. Tu recevras un email avec le paiement.', 'mj-member'),
                    'successUpdated' => __('Occurrences mises à jour.', 'mj-member'),
                    'successUnregistered' => __('Inscription annulée.', 'mj-member'),
                    'errorGeneric' => __('Une erreur est survenue. Merci de réessayer.', 'mj-member'),
                    'errorOccurrenceRequired' => __('Merci de sélectionner au moins une occurrence.', 'mj-member'),
                    'paymentEmailSent' => __('Un email contenant le lien de paiement arrive dans ta boîte mail.', 'mj-member'),
                    'paymentEmailError' => __('Inscription envoyée mais nous n’avons pas pu préparer l’email de paiement. Contacte la MJ.', 'mj-member'),
                    'occurrencePast' => __('Occurrence passée', 'mj-member'),
                    'pendingState' => __('En attente de confirmation', 'mj-member'),
                    'confirmedState' => __('Confirmée', 'mj-member'),
                    'waitlistState' => __('Liste d’attente', 'mj-member'),
                    'cancelledState' => __('Annulée', 'mj-member'),
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
            'requires_validation' => true,
            'cta_label' => apply_filters('mj_member_event_single_cta_label', __("S'inscrire", 'mj-member'), $event_data),
            'cta_registered_label' => apply_filters('mj_member_event_single_cta_registered_label', __('Déjà inscrit', 'mj-member'), $event_data),
            'config' => array(),
            'participants' => array(),
            'registered_count' => 0,
            'available_count' => 0,
            'all_registered' => false,
            'has_participants' => false,
            'needs_script' => false,
            'is_free_participation' => false,
            'free_participation_message' => '',
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
        $occurrence_catalog = array();
        $occurrence_lookup = array();
        $next_occurrence_ts = null;
        $has_future_occurrence = false;

        $schedule_mode = isset($event_data['schedule_mode']) ? sanitize_key((string) $event_data['schedule_mode']) : 'fixed';
        if (!in_array($schedule_mode, array('fixed', 'range', 'recurring', 'series'), true)) {
            $schedule_mode = 'fixed';
        }

        $occurrence_selection_mode = isset($event_data['occurrence_selection_mode']) ? sanitize_key((string) $event_data['occurrence_selection_mode']) : 'member_choice';
        if (!in_array($occurrence_selection_mode, array('member_choice', 'all_occurrences'), true)) {
            $occurrence_selection_mode = 'member_choice';
        }
        $allow_occurrence_selection = ($occurrence_selection_mode !== 'all_occurrences');

        if (!empty($event_data)) {
            $event_object = is_object($event_data) ? clone $event_data : (object) $event_data;

            if (class_exists('MjEventSchedule')) {
                $occurrence_limit = 120;
                $process_occurrence = static function ($occurrence_entry) use (&$occurrence_lookup, &$occurrence_catalog, &$next_occurrence_ts, &$has_future_occurrence, $now_ts) {
                    if (!is_array($occurrence_entry)) {
                        return;
                    }

                    $start_value = isset($occurrence_entry['start']) ? (string) $occurrence_entry['start'] : '';
                    if ($start_value === '') {
                        return;
                    }

                    $slug_value = isset($occurrence_entry['slug']) ? (string) $occurrence_entry['slug'] : $start_value;
                    $label_value = isset($occurrence_entry['label']) ? sanitize_text_field((string) $occurrence_entry['label']) : '';
                    $timestamp_value = isset($occurrence_entry['timestamp']) ? (int) $occurrence_entry['timestamp'] : strtotime($start_value);
                    if ($timestamp_value === false) {
                        $timestamp_value = 0;
                    }

                    $normalized_start = $start_value;
                    $normalized_slug = $slug_value;
                    if (class_exists('MjEventAttendance')) {
                        $normalized_candidate = MjEventAttendance::normalize_occurrence($start_value);
                        if ($normalized_candidate !== '') {
                            $normalized_start = $normalized_candidate;
                        }
                        $slug_candidate = MjEventAttendance::normalize_occurrence($slug_value);
                        if ($slug_candidate !== '') {
                            $normalized_slug = $slug_candidate;
                        }
                    }

                    $is_past = ($timestamp_value > 0 && $timestamp_value < $now_ts);
                    $is_today = ($timestamp_value > 0 && wp_date('Y-m-d', $timestamp_value) === wp_date('Y-m-d', $now_ts));

                    if ($next_occurrence_ts === null && !$is_past && $timestamp_value > 0) {
                        $next_occurrence_ts = $timestamp_value;
                    }

                    if ($timestamp_value > $now_ts) {
                        $has_future_occurrence = true;
                    }

                    $display_label = $label_value;
                    if ($display_label === '') {
                        if ($timestamp_value > 0) {
                            $display_label = date_i18n(get_option('date_format'), $timestamp_value) . ' - ' . date_i18n(get_option('time_format'), $timestamp_value);
                        } else {
                            $display_label = $start_value;
                        }
                    }

                    $occurrence_payload = array(
                        'start' => sanitize_text_field($start_value),
                        'slug' => sanitize_text_field($slug_value),
                        'label' => sanitize_text_field($display_label),
                        'timestamp' => $timestamp_value > 0 ? (int) $timestamp_value : 0,
                        'normalized' => sanitize_text_field($normalized_start),
                        'normalized_slug' => sanitize_text_field($normalized_slug),
                        'isPast' => $is_past,
                        'isToday' => $is_today,
                    );

                    if (isset($occurrence_entry['occurrence_id'])) {
                        $occurrence_payload['occurrence_id'] = (int) $occurrence_entry['occurrence_id'];
                    }
                    if (isset($occurrence_entry['id'])) {
                        $occurrence_payload['id'] = (int) $occurrence_entry['id'];
                    }

                    $selection_key = '';
                    foreach (array($normalized_start, $normalized_slug, $slug_value, $start_value) as $candidate_value) {
                        if (is_string($candidate_value)) {
                            $candidate_value = trim($candidate_value);
                        }
                        if (!is_string($candidate_value) || $candidate_value === '') {
                            continue;
                        }
                        $selection_key = $candidate_value;
                        break;
                    }

                    if ($selection_key === '') {
                        $selection_key = 'occ_' . md5($start_value . '|' . $timestamp_value . '|' . microtime(true));
                    }

                    $occurrence_lookup[$selection_key] = $occurrence_payload;

                    $catalog_keys = array(
                        $slug_value,
                        $normalized_slug,
                        $start_value,
                        $normalized_start,
                        sanitize_key($slug_value),
                        sanitize_key($normalized_slug),
                        sanitize_key($start_value),
                        sanitize_key($normalized_start),
                    );

                    $catalog_keys = array_values(
                        array_filter(
                            array_unique(
                                array_map(
                                    static function ($value) {
                                        return is_string($value) ? trim($value) : '';
                                    },
                                    $catalog_keys
                                )
                            )
                        )
                    );

                    foreach ($catalog_keys as $catalog_key) {
                        if ($catalog_key === '') {
                            continue;
                        }
                        $occurrence_catalog[$catalog_key] = $occurrence_payload;
                    }
                };

                $occurrences_raw = MjEventSchedule::get_occurrences(
                    $event_object,
                    array(
                        'max' => $occurrence_limit,
                        'include_past' => true,
                    )
                );

                if (!empty($occurrences_raw) && is_array($occurrences_raw)) {
                    foreach ($occurrences_raw as $occurrence_entry) {
                        $process_occurrence($occurrence_entry);
                    }
                }

                if ($allow_occurrence_selection && (!$has_future_occurrence || empty($occurrence_lookup))) {
                    $future_occurrences = MjEventSchedule::get_occurrences(
                        $event_object,
                        array(
                            'max' => $occurrence_limit,
                            'include_past' => false,
                        )
                    );

                    if (!empty($future_occurrences) && is_array($future_occurrences)) {
                        foreach ($future_occurrences as $occurrence_entry) {
                            $process_occurrence($occurrence_entry);
                        }
                    }
                }

                if (!empty($occurrence_lookup)) {
                    $occurrence_selection = array_values($occurrence_lookup);
                }
            }
        }

        $deadline_is_custom = ($deadline_raw !== '' && $deadline_raw !== '0000-00-00 00:00:00');
        if (!$registration_open && $closed_by_start && !$deadline_is_custom && $has_future_occurrence) {
            $registration_open = true;
            $closed_by_start = false;
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
        $requires_validation = array_key_exists('requires_validation', $event_data)
            ? !empty($event_data['requires_validation'])
            : true;

        $context['requires_validation'] = $requires_validation;
        $context['allow_guardian_registration'] = $allow_guardian_registration;
        $context['closed_by_start'] = $closed_by_start;

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

            $member_first_name = !empty($current_member->first_name) ? sanitize_text_field($current_member->first_name) : '';
            $member_last_name = !empty($current_member->last_name) ? sanitize_text_field($current_member->last_name) : '';
            $member_role = isset($current_member->role) ? sanitize_key($current_member->role) : 'member';
            $member_birth_date = '';
            if (!empty($current_member->birth_date) && $current_member->birth_date !== '0000-00-00') {
                $member_birth_date = sanitize_text_field((string) $current_member->birth_date);
            }

            $self_label = $member_display_name . ' (' . __('moi', 'mj-member') . ')';
            $participant_options[] = array(
                'id' => (int) $current_member->id,
                'label' => $self_label,
                'type' => $member_role,
                'role' => $member_role,
                'first_name' => $member_first_name,
                'last_name' => $member_last_name,
                'full_name' => $member_display_name,
                'birth_date' => $member_birth_date,
                'guardian_id' => isset($current_member->guardian_id) ? (int) $current_member->guardian_id : 0,
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

                    $child_first_name = !empty($child->first_name) ? sanitize_text_field($child->first_name) : '';
                    $child_last_name = !empty($child->last_name) ? sanitize_text_field($child->last_name) : '';
                    $child_role = isset($child->role) ? sanitize_key((string) $child->role) : 'child';
                    $child_birth_date = '';
                    if (!empty($child->birth_date) && $child->birth_date !== '0000-00-00') {
                        $child_birth_date = sanitize_text_field((string) $child->birth_date);
                    }

                    $participant_options[] = array(
                        'id' => (int) $child->id,
                        'label' => $child_label,
                        'type' => $child_role !== '' ? $child_role : 'child',
                        'role' => $child_role,
                        'first_name' => $child_first_name,
                        'last_name' => $child_last_name,
                        'full_name' => $child_label,
                        'birth_date' => $child_birth_date,
                        'guardian_id' => !empty($current_member->id) ? (int) $current_member->id : 0,
                        'isSelf' => false,
                    );
                }
            }
        }

        if (!empty($participant_options)) {
            $participant_options = array_values($participant_options);
        }

        $participants_source = $participant_options;

        $event_participants = array();
        $registered_count = 0;
        $available_count = 0;
        $ineligible_count = 0;

        $age_min = isset($event_data['age_min']) ? (int) $event_data['age_min'] : 0;
        $age_max = isset($event_data['age_max']) ? (int) $event_data['age_max'] : 0;
        $age_reference_available = ($start_ts && $start_ts > 0);
        $age_reference_timestamp = $age_reference_available ? (int) $start_ts : (int) $now_ts;
        if ($age_reference_timestamp <= 0) {
            $age_reference_timestamp = (int) $now_ts;
            $age_reference_available = false;
        }
        $timezone_string = function_exists('wp_timezone_string') ? (string) wp_timezone_string() : '';
        if ($timezone_string === '') {
            $timezone_string = 'UTC';
        }

        if (!empty($participants_source)) {
            foreach ($participants_source as $participant_option) {
                if (!is_array($participant_option)) {
                    $participant_option = (array) $participant_option;
                }

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
                $participant_label = isset($participant_option['label']) ? sanitize_text_field((string) $participant_option['label']) : '';
                $participant_first_name = isset($participant_option['first_name']) ? sanitize_text_field((string) $participant_option['first_name']) : '';
                $participant_last_name = isset($participant_option['last_name']) ? sanitize_text_field((string) $participant_option['last_name']) : '';
                $participant_full_name = isset($participant_option['full_name']) ? sanitize_text_field((string) $participant_option['full_name']) : ($participant_label !== '' ? $participant_label : '');
                $participant_role = isset($participant_option['role']) ? sanitize_key((string) $participant_option['role']) : '';
                if ($participant_role === '' && isset($participant_option['type'])) {
                    $participant_role = sanitize_key((string) $participant_option['type']);
                }
                $participant_birth_date = isset($participant_option['birth_date']) ? sanitize_text_field((string) $participant_option['birth_date']) : '';

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
                            if (!$allow_occurrence_selection) {
                                $participant_entry['occurrenceAssignments'] = array(
                                    'mode' => 'all',
                                    'occurrences' => array(),
                                );
                            }
                        }
                    }
                }

                $participant_entry['label'] = $participant_label !== '' ? $participant_label : (isset($participant_entry['label']) ? $participant_entry['label'] : '');
                $participant_entry['name'] = $participant_full_name !== '' ? $participant_full_name : $participant_entry['label'];
                $participant_entry['fullName'] = $participant_entry['name'];
                $participant_entry['first_name'] = $participant_first_name;
                $participant_entry['last_name'] = $participant_last_name;
                $participant_entry['firstName'] = $participant_first_name;
                $participant_entry['lastName'] = $participant_last_name;
                $participant_entry['role'] = $participant_role;
                $participant_entry['birth_date'] = $participant_birth_date;
                $participant_entry['birthDate'] = $participant_birth_date;

                $eligible = true;
                $ineligible_reasons = array();

                if (!$allow_guardian_registration && MjRoles::isTuteur($participant_role)) {
                    $eligible = false;
                    $ineligible_reasons[] = __('Rôle tuteur non autorisé pour cet événement.', 'mj-member');
                }

                $age_current = null;
                $birth_date_known = $participant_birth_date !== '' && $participant_birth_date !== '0000-00-00';
                if ($birth_date_known) {
                    try {
                        $birth_instance = new DateTime((string) $participant_birth_date);
                        $today_instance = new DateTime('today');
                        $age_current = (int) $birth_instance->diff($today_instance)->y;
                    } catch (Exception $exception) {
                        $age_current = null;
                    }
                }

                if ($age_min > 0 || $age_max > 0) {
                    if (!$birth_date_known || $age_current === null) {
                        $eligible = false;
                        $ineligible_reasons[] = __('Âge du membre indisponible.', 'mj-member');
                    } else {
                        if (!$age_reference_available) {
                            if ($age_min > 0 && $age_current < $age_min) {
                                $eligible = false;
                                $ineligible_reasons[] = sprintf(__('Âge inférieur au minimum (%d ans).', 'mj-member'), $age_min);
                            }
                            if ($age_max > 0 && $age_current > $age_max) {
                                $eligible = false;
                                $ineligible_reasons[] = sprintf(__('Âge supérieur au maximum (%d ans).', 'mj-member'), $age_max);
                            }
                        }

                        if ($age_reference_timestamp > 0 && $birth_date_known) {
                            try {
                                $reference_date = new DateTime('@' . $age_reference_timestamp);
                                $reference_date->setTimezone(new DateTimeZone($timezone_string));
                                $birth_reference = new DateTime((string) $participant_birth_date);
                                $age_at_event = (int) $birth_reference->diff($reference_date)->y;
                                if ($age_min > 0 && $age_at_event < $age_min) {
                                    $eligible = false;
                                    $ineligible_reasons[] = sprintf(__('Âge à la date de l\'événement inférieur au minimum (%d ans).', 'mj-member'), $age_min);
                                }
                                if ($age_max > 0 && $age_at_event > $age_max) {
                                    $eligible = false;
                                    $ineligible_reasons[] = sprintf(__('Âge à la date de l\'événement supérieur au maximum (%d ans).', 'mj-member'), $age_max);
                                }
                            } catch (Exception $exception) {
                                if ($age_reference_available) {
                                    if ($age_min > 0 && $age_current !== null && $age_current < $age_min) {
                                        $eligible = false;
                                        $ineligible_reasons[] = sprintf(__('Âge inférieur au minimum (%d ans).', 'mj-member'), $age_min);
                                    }
                                    if ($age_max > 0 && $age_current !== null && $age_current > $age_max) {
                                        $eligible = false;
                                        $ineligible_reasons[] = sprintf(__('Âge supérieur au maximum (%d ans).', 'mj-member'), $age_max);
                                    }
                                }
                            }
                        }
                    }
                }

                if (!empty($ineligible_reasons)) {
                    $ineligible_reasons = array_values(array_map('sanitize_text_field', array_unique($ineligible_reasons)));
                }

                $participant_entry['eligible'] = $eligible;
                $participant_entry['isEligible'] = $eligible ? 1 : 0;
                $participant_entry['ineligibleReasons'] = $ineligible_reasons;
                $participant_entry['ineligible_reasons'] = $ineligible_reasons;
                $participant_entry['age'] = $age_current !== null ? (int) $age_current : null;

                if (!$eligible) {
                    $ineligible_count++;
                }

                if ($participant_entry['isRegistered']) {
                    $registered_count++;
                } elseif ($eligible) {
                    $available_count++;
                }

                $event_participants[] = $participant_entry;
            }
        }

        $all_registered = !empty($event_participants) && $registered_count === count($event_participants);

        $non_interactive_modes = apply_filters(
            'mj_member_event_registration_non_interactive_modes',
            array('attendance', 'attendance_free', 'free_participation', 'free', 'open_access', 'no_registration', 'optional', 'none', 'libre', 'presence')
        );

        if (!is_array($non_interactive_modes)) {
            $non_interactive_modes = array('attendance', 'attendance_free', 'free_participation', 'free', 'open_access', 'no_registration', 'optional', 'none', 'libre', 'presence');
        }

        $non_interactive_modes = array_values(array_filter(array_map('sanitize_key', $non_interactive_modes), static function ($value) {
            return $value !== '';
        }));
        if (!in_array('free_participation', $non_interactive_modes, true)) {
            $non_interactive_modes[] = 'free_participation';
        }

        $is_free_participation = !empty($event_data['free_participation']) || !empty($event_data['is_free_participation']);

        $legacy_registration_mode = '';
        if (isset($event_data['registration_mode'])) {
            $legacy_registration_mode = sanitize_key((string) $event_data['registration_mode']);
        }
        if ($legacy_registration_mode === '') {
            $legacy_registration_mode = $is_free_participation ? 'free_participation' : 'participant';
        }

        if (!$is_free_participation && in_array($legacy_registration_mode, $non_interactive_modes, true)) {
            $is_free_participation = true;
        }

        $default_free_participation = __('La participation est libre : aucune inscription n\'est requise.', 'mj-member');
        $free_participation_message = '';
        if ($is_free_participation) {
            $filtered_message = apply_filters(
                'mj_member_event_free_participation_message',
                $default_free_participation,
                $event_data,
                $legacy_registration_mode,
                $is_free_participation
            );
            if (is_string($filtered_message)) {
                $free_participation_message = trim((string) $filtered_message);
            } else {
                $free_participation_message = $default_free_participation;
            }
            if ($free_participation_message === '') {
                $free_participation_message = $default_free_participation;
            }
        }

        $registration_config = array(
            'eventId' => $event_id,
            'eventTitle' => isset($event_data['title']) ? $event_data['title'] : '',
            'participants' => $event_participants,
            'allRegistered' => $all_registered,
            'hasParticipants' => !empty($event_participants),
            'hasAvailableParticipants' => ($available_count > 0),
            'hasIneligibleParticipants' => ($ineligible_count > 0),
            'ineligibleCount' => $ineligible_count,
            'noteMaxLength' => 400,
            'requiresValidation' => $requires_validation,
            'allowGuardianRegistration' => $allow_guardian_registration,
        );

        if ($deadline_ts) {
            $registration_config['deadline'] = gmdate('c', $deadline_ts);
        }

        $registration_config['isFreeParticipation'] = $is_free_participation;
        $registration_config['freeParticipation'] = $is_free_participation;
        if ($free_participation_message !== '') {
            $registration_config['freeParticipationMessage'] = $free_participation_message;
        }

        $total_participants = count($event_participants);
        $price_amount = isset($event_data['price']) ? (float) $event_data['price'] : 0.0;
        $price_label = isset($event_data['price_label']) ? $event_data['price_label'] : ($price_amount > 0
            ? sprintf(__('Tarif : %s €', 'mj-member'), number_format_i18n($price_amount, 2))
            : __('Tarif : Gratuit', 'mj-member'));

        $context['free_participation'] = $is_free_participation ? 1 : 0;
        $context['is_free_participation'] = $is_free_participation;
        $context['free_participation_message'] = $is_free_participation ? $free_participation_message : '';
        $context['participants'] = $event_participants;
        $context['registered_count'] = $registered_count;
        $context['available_count'] = $available_count;
        $context['ineligible_count'] = $ineligible_count;
        $context['has_ineligible'] = ($ineligible_count > 0);
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
            'ineligible' => $ineligible_count,
        );

        $capacity_state = array();
        if (class_exists('MjEventRegistrations') && method_exists('MjEventRegistrations', 'get_capacity_state')) {
            $capacity_state = MjEventRegistrations::get_capacity_state($event_id);
        }
        if (!empty($capacity_state)) {
            $context['capacity'] = $capacity_state;
            $registration_config['capacity'] = $capacity_state;
        }

        if (!$allow_occurrence_selection) {
            $occurrence_assignments = array(
                'mode' => 'all',
                'occurrences' => array(),
            );
        }
        $context['occurrences'] = $occurrence_selection;
        $context['has_occurrences'] = !empty($occurrence_selection);
        $context['assignments'] = $occurrence_assignments;
        $context['occurrence_selection_mode'] = $occurrence_selection_mode;
        $context['allow_occurrence_selection'] = $allow_occurrence_selection;

        $registration_config['occurrences'] = $occurrence_selection;
        $registration_config['assignments'] = $occurrence_assignments;
        $registration_config['scheduleMode'] = $schedule_mode;
        $registration_config['hasOccurrences'] = !empty($occurrence_selection);
        $registration_config['hasIneligibleParticipants'] = ($ineligible_count > 0);
        $registration_config['ineligibleCount'] = $ineligible_count;
        $registration_config['occurrenceSelectionMode'] = $occurrence_selection_mode;
        $registration_config['allowOccurrenceSelection'] = $allow_occurrence_selection;
        $registration_config['priceAmount'] = $price_amount;
        $registration_config['priceLabel'] = $price_label;
        $registration_config['paymentRequired'] = ($price_amount > 0);

        $context['config'] = $registration_config;
        $context['needs_script'] = $registration_open && ($context['requires_login'] || !empty($event_participants));
        if ($is_free_participation) {
            $context['needs_script'] = false;
        }

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
        // Route pour EventPage : /evenement/{slug}
        add_rewrite_tag('%mj_event_page_slug%', '([^&]+)');
        add_rewrite_rule('evenement/([^/]+)/?$', 'index.php?mj_event_page_slug=$matches[1]', 'top');
    }
    add_action('init', 'mj_member_register_event_routes', 12);
}

if (!function_exists('mj_member_event_query_vars')) {
    function mj_member_event_query_vars($vars) {
        if (!in_array('mj_event_page_slug', $vars, true)) {
            $vars[] = 'mj_event_page_slug';
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
            'statuses' => array(MjEvents::STATUS_ACTIVE),
            'types' => array(),
            'ids' => array(),
            'article_ids' => array(),
            'limit' => 6,
            'order' => 'DESC',
            'orderby' => 'date_debut',
            'include_past' => false,
            'now' => current_time('mysql'),
        );

        $args = wp_parse_args($args, $defaults);

        $include_past_events = !empty($args['include_past']) && $args['include_past'] !== 'no' && $args['include_past'] !== '0';
        $can_view_internal_events = mj_member_user_can_view_internal_events();

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
            $statuses = array(MjEvents::STATUS_ACTIVE);
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

        $article_ids = array();
        if (!empty($args['article_ids']) && is_array($args['article_ids'])) {
            foreach ($args['article_ids'] as $article_candidate) {
                $article_candidate = (int) $article_candidate;
                if ($article_candidate <= 0) {
                    continue;
                }
                $article_ids[$article_candidate] = $article_candidate;
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
        $supports_validation_toggle = function_exists('mj_member_column_exists') ? mj_member_column_exists($events_table, 'requires_validation') : false;
        $supports_free_participation = function_exists('mj_member_column_exists') ? mj_member_column_exists($events_table, 'free_participation') : false;
        $supports_registration_mode = !$supports_free_participation && function_exists('mj_member_column_exists') ? mj_member_column_exists($events_table, 'registration_mode') : false;
        $supports_emoji_column = function_exists('mj_member_column_exists') ? mj_member_column_exists($events_table, 'emoji') : false;
        if ($supports_guardian_toggle) {
            $select_fields[] = 'events.allow_guardian_registration';
        }
        if ($supports_validation_toggle) {
            $select_fields[] = 'events.requires_validation';
        }
        if ($supports_free_participation) {
            $select_fields[] = 'events.free_participation';
        }
        if ($supports_registration_mode) {
            $select_fields[] = 'events.registration_mode';
        }
        if ($supports_emoji_column) {
            $select_fields[] = 'events.emoji';
        }

        $default_free_registration_modes = array('attendance', 'attendance_free', 'free_participation', 'free', 'open_access', 'no_registration', 'optional', 'none', 'libre', 'presence');
        $non_interactive_registration_modes = apply_filters('mj_member_event_registration_non_interactive_modes', $default_free_registration_modes);
        if (!is_array($non_interactive_registration_modes)) {
            $non_interactive_registration_modes = $default_free_registration_modes;
        }
        $non_interactive_registration_modes = array_values(array_filter(array_map('sanitize_key', $non_interactive_registration_modes), static function ($value) {
            return $value !== '';
        }));
        if (!in_array('free_participation', $non_interactive_registration_modes, true)) {
            $non_interactive_registration_modes[] = 'free_participation';
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

        if (!empty($article_ids)) {
            $placeholders = implode(',', array_fill(0, count($article_ids), '%d'));
            $where_fragments[] = "events.article_id IN ({$placeholders})";
            foreach ($article_ids as $article_value) {
                $where_params[] = $article_value;
            }
        }

        if (!$can_view_internal_events) {
            $where_fragments[] = 'events.type <> %s';
            $where_params[] = MjEvents::TYPE_INTERNE;
        }

        $now_value = isset($args['now']) ? sanitize_text_field($args['now']) : current_time('mysql');
        if (!$include_past_events) {
            $normalized_date_fin = "CASE WHEN events.date_fin IS NULL OR CAST(events.date_fin AS CHAR) = '' OR CAST(events.date_fin AS CHAR) = '0000-00-00 00:00:00' THEN '9999-12-31 23:59:59' ELSE CAST(events.date_fin AS CHAR) END";
            $normalized_recurrence = "CASE WHEN events.recurrence_until IS NULL OR CAST(events.recurrence_until AS CHAR) = '' OR CAST(events.recurrence_until AS CHAR) = '0000-00-00 00:00:00' THEN '9999-12-31 23:59:59' ELSE CAST(events.recurrence_until AS CHAR) END";
            $where_fragments[] = "({$normalized_date_fin} >= %s OR (events.schedule_mode = %s AND {$normalized_recurrence} >= %s))";
            $where_params[] = $now_value;
            $where_params[] = 'recurring';
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

        $type_color_map = method_exists('MjEvents', 'get_type_colors') ? MjEvents::get_type_colors() : array();
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
            if (!in_array($schedule_mode, array('fixed', 'range', 'recurring', 'series'), true)) {
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

            $registration_is_free_participation = false;
            $legacy_registration_mode = '';
            if ($supports_free_participation && isset($row->free_participation)) {
                $registration_is_free_participation = !empty($row->free_participation);
            }

            if ($supports_registration_mode && isset($row->registration_mode)) {
                $registration_mode_candidate = sanitize_key((string) $row->registration_mode);
                if ($registration_mode_candidate !== '') {
                    $legacy_registration_mode = $registration_mode_candidate;
                }
                if (!$supports_free_participation) {
                    $registration_is_free_participation = in_array($registration_mode_candidate, $non_interactive_registration_modes, true);
                }
            }

            if ($legacy_registration_mode === '') {
                $legacy_registration_mode = $registration_is_free_participation ? 'free_participation' : 'participant';
            }

            if ($supports_free_participation && !$registration_is_free_participation && in_array($legacy_registration_mode, $non_interactive_registration_modes, true)) {
                $registration_is_free_participation = true;
            }

            if (!$supports_free_participation && !$supports_registration_mode) {
                $registration_is_free_participation = false;
            }

            if ($legacy_registration_mode === '') {
                $legacy_registration_mode = $registration_is_free_participation ? 'free_participation' : 'participant';
            }

            if (
                !$include_past_events
                && in_array($schedule_mode, array('recurring', 'series'), true)
                && class_exists('MjEventSchedule')
            ) {
                $date_start_raw = isset($row->date_debut) ? $row->date_debut : '';
                $date_end_raw = isset($row->date_fin) ? $row->date_fin : '';

                $occurrence_probe = array(
                    'schedule_mode' => $schedule_mode,
                    'schedule_payload' => $schedule_payload,
                    'date_debut' => $date_start_raw,
                    'date_fin' => $date_end_raw,
                    'start_date' => $date_start_raw,
                    'end_date' => $date_end_raw,
                    'recurrence_until' => $recurrence_until,
                );

                $future_occurrences = MjEventSchedule::get_occurrences(
                    $occurrence_probe,
                    array(
                        'max' => 1,
                        'include_past' => false,
                    )
                );

                if (empty($future_occurrences)) {
                    continue;
                }
            }

            $permalink = apply_filters('mj_member_event_permalink', '', $row);
            $slug_value = '';
            if (!empty($row->slug)) {
                $slug_value = sanitize_title($row->slug);
            }
            if ($slug_value === '' && isset($row->id)) {
                $slug_value = MjEvents::get_or_create_slug((int) $row->id);
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
                    } elseif (method_exists('MjEvents', 'get_default_color_for_type')) {
                        $accent_color = MjEvents::get_default_color_for_type($type_key_candidate);
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
                'emoji' => ($supports_emoji_column && isset($row->emoji)) ? sanitize_text_field($row->emoji) : '',
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
                'requires_validation' => ($supports_validation_toggle && isset($row->requires_validation)) ? (int) $row->requires_validation : 1,
                'free_participation' => $registration_is_free_participation ? 1 : 0,
                'is_free_participation' => $registration_is_free_participation,
                'legacy_registration_mode' => $legacy_registration_mode,
            );
        }

        if (!$can_view_internal_events) {
            $results = array_values(array_filter($results, static function ($event_entry) {
                if (!isset($event_entry['type'])) {
                    return true;
                }

                $type_value = sanitize_key((string) $event_entry['type']);
                return $type_value !== MjEvents::TYPE_INTERNE;
            }));
        }

        return $results;
    }
}

if (!function_exists('mj_member_get_upcoming_events')) {
    /**
     * Récupère la liste des prochains événements pour les affichages publics.
     *
     * @param array<string,mixed> $args
     * @return array<int,array<string,mixed>>
     */
    function mj_member_get_upcoming_events($args = array()) {
        if (!function_exists('mj_member_get_public_events')) {
            return array();
        }

        $defaults = array(
            'limit' => 6,
            'statuses' => array(MjEvents::STATUS_ACTIVE),
            'types' => array(),
            'order' => 'ASC',
            'orderby' => 'date_debut',
            'include_past' => false,
        );

        $args = wp_parse_args($args, $defaults);

        $args['include_past'] = false;

        $order_value = isset($args['order']) ? strtoupper((string) $args['order']) : 'ASC';
        $args['order'] = $order_value === 'DESC' ? 'DESC' : 'ASC';

        if (empty($args['orderby'])) {
            $args['orderby'] = 'date_debut';
        }

        /**
         * Filtre les arguments passés à la récupération des événements à venir.
         *
         * @param array<string,mixed> $args
         */
        $query_args = apply_filters('mj_member_upcoming_events_query_args', $args);

        $events = mj_member_get_public_events($query_args);

        /**
         * Filtre les événements à venir renvoyés au widget.
         *
         * @param array<int,array<string,mixed>> $events
         * @param array<string,mixed> $query_args
         */
        return apply_filters('mj_member_upcoming_events_results', $events, $query_args);
    }
}

if (!function_exists('mj_member_get_event_article_choices')) {
    /**
     * Retourne la liste des articles associés aux évènements.
     *
     * @return array<int,string>
     */
    function mj_member_get_event_article_choices() {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $cache = array();

        if (!function_exists('mj_member_get_events_table_name')) {
            return $cache;
        }

        global $wpdb;
        $events_table = mj_member_get_events_table_name();
        if (!$events_table) {
            return $cache;
        }

        $article_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT article_id FROM {$events_table} WHERE article_id IS NOT NULL AND article_id > 0 ORDER BY article_id DESC LIMIT %d",
                200
            )
        );

        if (empty($article_ids)) {
            return $cache;
        }

        foreach ($article_ids as $article_id) {
            $article_id = (int) $article_id;
            if ($article_id <= 0) {
                continue;
            }

            $post_object = get_post($article_id);
            if (!$post_object) {
                continue;
            }

            $status = get_post_status($post_object);
            if (!$status || $status === 'trash') {
                continue;
            }

            $title = get_the_title($post_object);
            if (!is_string($title) || $title === '') {
                $title = sprintf(__('Article #%d', 'mj-member'), $article_id);
            }

            $safe_title = sanitize_text_field($title);
            $cache[$article_id] = sprintf(__('%s (#%d)', 'mj-member'), $safe_title, $article_id);
        }

        return $cache;
    }
}

if (!function_exists('mj_member_get_event_weekday_labels')) {
    /**
     * @return array<string,string>
     */
    function mj_member_get_event_weekday_labels() {
        return array(
            'monday' => __('Lundi', 'mj-member'),
            'tuesday' => __('Mardi', 'mj-member'),
            'wednesday' => __('Mercredi', 'mj-member'),
            'thursday' => __('Jeudi', 'mj-member'),
            'friday' => __('Vendredi', 'mj-member'),
            'saturday' => __('Samedi', 'mj-member'),
            'sunday' => __('Dimanche', 'mj-member'),
        );
    }
}

if (!function_exists('mj_member_join_french_list')) {
    /**
     * @param array<int,string> $items
     * @return string
     */
    function mj_member_join_french_list(array $items) {
        $items = array_values(array_filter($items, static function ($value) {
            return $value !== '';
        }));
        $count = count($items);
        if ($count === 0) {
            return '';
        }
        if ($count === 1) {
            return $items[0];
        }
        if ($count === 2) {
            return $items[0] . ' et ' . $items[1];
        }

        $last = array_pop($items);
        return implode(', ', $items) . ' et ' . $last;
    }
}

if (!function_exists('mj_member_format_recurring_time_label')) {
    function mj_member_format_recurring_time_label($time_value) {
        $time_value = trim((string) $time_value);
        if ($time_value === '') {
            return '';
        }

        $time_candidates = array('H:i:s', 'H:i');
        $timezone = wp_timezone();
        foreach ($time_candidates as $format) {
            $date_object = DateTimeImmutable::createFromFormat($format, $time_value, $timezone);
            if ($date_object instanceof DateTimeImmutable) {
                return $date_object->format('H\hi');
            }
        }

        $timestamp = strtotime($time_value);
        if ($timestamp) {
            $date_object = new DateTimeImmutable('@' . $timestamp);
            $date_object = $date_object->setTimezone($timezone);
            return $date_object->format('H\hi');
        }

        return '';
    }
}

if (!function_exists('mj_member_get_event_recurring_summary')) {
    /**
     * Génère un résumé textuel pour une récurrence d'évènement.
     *
     * @param array<string,mixed>|object $event
     * @return string
     */
    function mj_member_get_event_recurring_summary($event) {
        if (is_array($event)) {
            $event = (object) $event;
        }

        if (!is_object($event)) {
            return '';
        }

        $mode = isset($event->schedule_mode) ? sanitize_key($event->schedule_mode) : '';
        if ($mode !== 'recurring') {
            return '';
        }

        $payload = array();
        if (isset($event->schedule_payload)) {
            if (is_array($event->schedule_payload)) {
                $payload = $event->schedule_payload;
            } elseif (is_string($event->schedule_payload) && $event->schedule_payload !== '') {
                $decoded = json_decode($event->schedule_payload, true);
                if (is_array($decoded)) {
                    $payload = $decoded;
                }
            }
        }

        $frequency = isset($payload['frequency']) ? sanitize_key($payload['frequency']) : 'weekly';
        if (!in_array($frequency, array('weekly', 'monthly'), true)) {
            $frequency = 'weekly';
        }

        $interval = isset($payload['interval']) ? (int) $payload['interval'] : 1;
        if ($interval <= 0) {
            $interval = 1;
        }

        $start_time_label = '';
        if (!empty($payload['start_time'])) {
            $start_time_label = mj_member_format_recurring_time_label($payload['start_time']);
        }
        if ($start_time_label === '' && !empty($event->start_date)) {
            $start_time_label = mj_member_format_recurring_time_label(substr($event->start_date, 11, 5));
        }

        $end_time_label = '';
        if (!empty($payload['end_time'])) {
            $end_time_label = mj_member_format_recurring_time_label($payload['end_time']);
        }
        if ($end_time_label === '' && !empty($event->end_date)) {
            $end_time_label = mj_member_format_recurring_time_label(substr($event->end_date, 11, 5));
        }

        $time_segment = '';
        if ($start_time_label !== '' && $end_time_label !== '') {
            $time_segment = sprintf(__('de %1$s à %2$s', 'mj-member'), $start_time_label, $end_time_label);
        } elseif ($start_time_label !== '') {
            $time_segment = sprintf(__('à %s', 'mj-member'), $start_time_label);
        } elseif ($end_time_label !== '') {
            $time_segment = sprintf(__('jusqu\'à %s', 'mj-member'), $end_time_label);
        }

        $weekday_labels = mj_member_get_event_weekday_labels();
        $weekday_numbers = array(1 => 'monday', 2 => 'tuesday', 3 => 'wednesday', 4 => 'thursday', 5 => 'friday', 6 => 'saturday', 7 => 'sunday');
        $weekday_values = array();

        if (!empty($payload['weekdays']) && is_array($payload['weekdays'])) {
            foreach ($payload['weekdays'] as $weekday_candidate) {
                $weekday_candidate = sanitize_key($weekday_candidate);
                if (isset($weekday_labels[$weekday_candidate])) {
                    $weekday_values[$weekday_candidate] = $weekday_candidate;
                }
            }
        }

        if (empty($weekday_values) && !empty($event->start_date)) {
            $timestamp = strtotime($event->start_date);
            if ($timestamp) {
                $weekday_number = (int) wp_date('N', $timestamp);
                if (isset($weekday_numbers[$weekday_number])) {
                    $weekday_values[$weekday_numbers[$weekday_number]] = $weekday_numbers[$weekday_number];
                }
            }
        }

        $weekday_values = array_values($weekday_values);

        $base_text = '';
        if ($frequency === 'weekly') {
            if (empty($weekday_values)) {
                return '';
            }

            $weekday_words = array();
            $weekday_plural_words = array();
            foreach ($weekday_values as $weekday_key) {
                $label = isset($weekday_labels[$weekday_key]) ? $weekday_labels[$weekday_key] : $weekday_key;
                $word = function_exists('mb_strtolower') ? mb_strtolower($label, 'UTF-8') : strtolower($label);
                $weekday_words[] = $word;
                $plural = $word;
                if (substr($plural, -1) !== 's') {
                    $plural .= 's';
                }
                $weekday_plural_words[] = $plural;
            }

            if ($interval === 1) {
                $base_text = sprintf(__('Tous les %s', 'mj-member'), mj_member_join_french_list($weekday_plural_words));
            } else {
                $base_text = sprintf(_n('Toutes les %d semaine', 'Toutes les %d semaines', $interval, 'mj-member'), $interval);
                $list_label = count($weekday_values) > 1
                    ? sprintf(__('les %s', 'mj-member'), mj_member_join_french_list($weekday_plural_words))
                    : sprintf(__('le %s', 'mj-member'), $weekday_words[0]);
                $base_text .= ', ' . $list_label;
            }
        } else {
            $ordinal_map = array(
                'first' => __('1er', 'mj-member'),
                'second' => __('2e', 'mj-member'),
                'third' => __('3e', 'mj-member'),
                'fourth' => __('4e', 'mj-member'),
                'last' => __('Dernier', 'mj-member'),
            );

            $ordinal_key = isset($payload['ordinal']) ? sanitize_key($payload['ordinal']) : 'first';
            if (!isset($ordinal_map[$ordinal_key])) {
                $ordinal_key = 'first';
            }
            $ordinal_label = $ordinal_map[$ordinal_key];

            $weekday_key = isset($payload['weekday']) ? sanitize_key($payload['weekday']) : '';
            if (!isset($weekday_labels[$weekday_key])) {
                $weekday_key = !empty($weekday_values) ? $weekday_values[0] : 'saturday';
            }

            $weekday_label = isset($weekday_labels[$weekday_key]) ? $weekday_labels[$weekday_key] : $weekday_key;
            $weekday_word = function_exists('mb_strtolower') ? mb_strtolower($weekday_label, 'UTF-8') : strtolower($weekday_label);
            $ordinal_word = function_exists('mb_strtolower') ? mb_strtolower($ordinal_label, 'UTF-8') : strtolower($ordinal_label);

            if ($interval === 1) {
                $base_text = sprintf(__('Chaque %1$s %2$s du mois', 'mj-member'), $ordinal_word, $weekday_word);
            } else {
                $base_text = sprintf(_n('Tous les %d mois', 'Tous les %d mois', $interval, 'mj-member'), $interval);
                $base_text .= ', ' . sprintf(__('le %1$s %2$s', 'mj-member'), $ordinal_word, $weekday_word);
            }
        }

        if ($base_text === '') {
            return '';
        }

        if ($time_segment !== '') {
            $base_text .= ' ' . $time_segment;
        }

        return trim($base_text);
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
            if ($start_ts === $end_ts) {
                $date_label = wp_date(get_option('date_format', 'd/m/Y'), $start_ts);
                $time_label = wp_date(get_option('time_format', 'H:i'), $start_ts);

                if ($time_label !== '') {
                    return sprintf(
                        '%s %s',
                        $date_label,
                        sprintf(__('à partir de %s', 'mj-member'), $time_label)
                    );
                }

                return wp_date(get_option('date_format', 'd/m/Y H:i'), $start_ts);
            }

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
            $date_label = wp_date(get_option('date_format', 'd/m/Y'), $start_ts);
            $time_label = wp_date(get_option('time_format', 'H:i'), $start_ts);

            if ($time_label !== '') {
                return sprintf(
                    '%s %s',
                    $date_label,
                    sprintf(__('à partir de %s', 'mj-member'), $time_label)
                );
            }

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

        return home_url('/evenement/' . rawurlencode($slug));
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
            $slug_reference = MjEvents::get_or_create_slug((int) $event['id']);
        } elseif ($slug_reference === '' && is_object($event) && isset($event->id)) {
            $slug_reference = MjEvents::get_or_create_slug((int) $event->id);
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
        $palette_reference = method_exists('MjEvents', 'get_type_colors') ? MjEvents::get_type_colors() : array();

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
        if (!class_exists('MjEvents')) {
            return null;
        }

        $event_record = MjEvents::find_by_slug($requested_slug);
        if (!$event_record) {
            return null;
        }

        $event_id = (int) $event_record->id;
        if ($event_id <= 0) {
            return null;
        }

        $status_labels = MjEvents::get_status_labels();
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
            $slug = MjEvents::get_or_create_slug($event_id);
            $event_data = array(
                'id' => $event_id,
                'title' => sanitize_text_field($event_record->title),
                'slug' => $slug,
                'status' => sanitize_key((string) $event_record->status),
                'type' => sanitize_key((string) $event_record->type),
                'accent_color' => mj_member_normalize_hex_color_value($event_record->accent_color),
                'start_date' => sanitize_text_field((string) $event_record->date_debut),
                'end_date' => sanitize_text_field((string) $event_record->date_fin),
                'deadline' => sanitize_text_field((string) $event_record->date_fin_inscription),
                'price' => (float) $event_record->prix,
                'age_min' => (int) $event_record->age_min,
                'age_max' => (int) $event_record->age_max,
                'description' => wp_kses_post((string) $event_record->description),
                'excerpt' => '',
                'cover_id' => (int) $event_record->cover_id,
                'cover_url' => '',
                'cover_thumb' => '',
                'article_id' => (int) $event_record->article_id,
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
                'requires_validation' => isset($event_record->requires_validation) ? (int) $event_record->requires_validation : 1,
                'free_participation' => isset($event_record->free_participation) ? (int) $event_record->free_participation : 0,
                'is_free_participation' => false,
                'free_participation_message' => '',
                'legacy_registration_mode' => isset($event_record->registration_mode) ? sanitize_key((string) $event_record->registration_mode) : '',
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
            $event_data['slug'] = sanitize_title(isset($event_data['slug']) ? $event_data['slug'] : MjEvents::get_or_create_slug($event_id));
            if (empty($event_data['permalink'])) {
                $event_data['permalink'] = mj_member_build_event_permalink($event_data['slug']);
            }
        }

        $legacy_registration_mode = isset($event_data['legacy_registration_mode']) ? sanitize_key((string) $event_data['legacy_registration_mode']) : '';
        if ($legacy_registration_mode === '') {
            $legacy_registration_mode = !empty($event_data['free_participation']) ? 'free_participation' : 'participant';
        }
        $event_data['legacy_registration_mode'] = $legacy_registration_mode;

        $event_data['registration_config'] = isset($event_data['config']) && is_array($event_data['config']) ? $event_data['config'] : array();

        if (!isset($non_interactive_registration_modes)) {
            $default_free_registration_modes = array('attendance', 'attendance_free', 'free_participation', 'free', 'open_access', 'no_registration', 'optional', 'none', 'libre', 'presence');
            $non_interactive_registration_modes = apply_filters('mj_member_event_registration_non_interactive_modes', $default_free_registration_modes);
            if (!is_array($non_interactive_registration_modes)) {
                $non_interactive_registration_modes = $default_free_registration_modes;
            }
            $non_interactive_registration_modes = array_values(array_filter(array_map('sanitize_key', $non_interactive_registration_modes), static function ($value) {
                return $value !== '';
            }));
            if (!in_array('free_participation', $non_interactive_registration_modes, true)) {
                $non_interactive_registration_modes[] = 'free_participation';
            }
        }

        $event_is_free_participation = !empty($event_data['free_participation']) || !empty($event_data['is_free_participation']);
        if (!$event_is_free_participation && in_array($legacy_registration_mode, $non_interactive_registration_modes, true)) {
            $event_is_free_participation = true;
        }

        $event_data['is_free_participation'] = $event_is_free_participation ? true : false;
        $event_data['free_participation'] = $event_is_free_participation ? 1 : 0;

        if ($event_is_free_participation && (empty($event_data['free_participation_message']) || !is_string($event_data['free_participation_message']))) {
            $default_free_participation_message = __('La participation est libre : aucune inscription n\'est requise.', 'mj-member');
            $filtered_free_participation_message = apply_filters('mj_member_event_free_participation_message', $default_free_participation_message, $event_data, $legacy_registration_mode);
            if (is_string($filtered_free_participation_message)) {
                $filtered_free_participation_message = trim($filtered_free_participation_message);
            } else {
                $filtered_free_participation_message = $default_free_participation_message;
            }
            if ($filtered_free_participation_message === '') {
                $filtered_free_participation_message = $default_free_participation_message;
            }
            $filtered_free_participation_message = sanitize_text_field($filtered_free_participation_message);
            $event_data['free_participation_message'] = $filtered_free_participation_message;
        } elseif (!$event_is_free_participation) {
            $event_data['free_participation_message'] = '';
        }

        $type_key = isset($event_data['type']) ? sanitize_key($event_data['type']) : '';
        $accent_color = isset($event_data['accent_color']) ? mj_member_normalize_hex_color_value($event_data['accent_color']) : '';
        if ($accent_color === '' && $type_key !== '') {
            $accent_color = mj_member_normalize_hex_color_value(MjEvents::get_default_color_for_type($type_key));
        }
        if ($accent_color === '') {
            $accent_color = '#2563EB';
        }
        $event_data['accent_color'] = $accent_color;
        $event_data['palette'] = mj_member_build_event_palette_data($accent_color, $type_key);

        $type_labels = MjEvents::get_type_labels();
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
                $role_labels = class_exists('MjMembers') ? MjMembers::getRoleLabels() : array();
                foreach ($animateur_rows as $index => $animateur_row) {
                    if (!is_object($animateur_row)) {
                        continue;
                    }

                    $member_id = isset($animateur_row->id) ? (int) $animateur_row->id : 0;
                    $first_name = isset($animateur_row->first_name) ? sanitize_text_field($animateur_row->first_name) : '';
                    $last_name = isset($animateur_row->last_name) ? sanitize_text_field($animateur_row->last_name) : '';
                    $full_name = trim($first_name . ' ' . $last_name);
                    if ($full_name === '') {
                        $full_name = $member_id > 0 ? sprintf(__('Membre #%d', 'mj-member'), $member_id) : \Mj\Member\Classes\MjRoles::getRoleLabel(\Mj\Member\Classes\MjRoles::ANIMATEUR);
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

                    $whatsapp_opt_in = true;
                    if (isset($animateur_row->whatsapp_opt_in)) {
                        $whatsapp_opt_in = ((int) $animateur_row->whatsapp_opt_in) !== 0;
                    }

                    $whatsapp_link = '';
                    if ($whatsapp_opt_in && $phone !== '') {
                        $whatsapp_number = preg_replace('/\D+/', '', $phone);
                        if ($whatsapp_number !== '' && strlen($whatsapp_number) >= 6) {
                            $whatsapp_link = esc_url_raw('https://wa.me/' . $whatsapp_number);
                        }
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
                        'whatsapp_opt_in' => $whatsapp_opt_in,
                        'whatsapp_link' => $whatsapp_link,
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

// -----------------------------------------------------------------------------
// EventPage (route /evenement/{slug})
// -----------------------------------------------------------------------------

// Pré-charge l'ID de l'événement pour la barre admin (s'exécute avant admin_bar_menu)
if (!function_exists('mj_member_event_page_preload_for_admin_bar')) {
    function mj_member_event_page_preload_for_admin_bar() {
        $slug = get_query_var('mj_event_page_slug');
        if ($slug === '' || $slug === null) {
            return;
        }

        // Charge l'événement pour avoir l'ID disponible pour la barre admin
        $event = \Mj\Member\Classes\Crud\MjEvents::find_by_slug($slug);
        if ($event && isset($event->id)) {
            $GLOBALS['mj_event_page_current'] = array(
                'id' => (int) $event->id,
                'title' => isset($event->title) ? $event->title : '',
            );
        }
    }
    add_action('template_redirect', 'mj_member_event_page_preload_for_admin_bar', 5);
}

if (!function_exists('mj_member_event_page_template_include')) {
    /**
     * Filtre template_include pour la route /evenement/{slug}.
     * Charge EventPageController et renvoie le template associé.
     */
    function mj_member_event_page_template_include($template) {
        $slug = get_query_var('mj_event_page_slug');
        if ($slug === '' || $slug === null) {
            return $template;
        }

        // Charge le controller et exécute la logique métier
        $controller_file = Config::path() . 'includes/classes/front/EventPageController.php';
        if (!file_exists($controller_file)) {
            return $template;
        }
        require_once $controller_file;

        // Récupère l'événement par slug (méthode statique)
        $event = \Mj\Member\Classes\Crud\MjEvents::find_by_slug($slug);

        if (!$event) {
            // Événement introuvable : 404
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            nocache_headers();
            return get_404_template();
        }

        // Prépare le contexte pour le controller
        $context = array(
            'event_id' => (int) $event->id,
            'event' => $event,
            'slug' => $slug,
        );

        // Le controller expose le payload dans une globale puis renvoie le template
        $controller = new \Mj\Member\Classes\Front\EventPageController($context);
        $result = $controller->build();

        if ($result === false) {
            // Erreur lors de la construction
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            nocache_headers();
            return get_404_template();
        }

        // Stocke le payload pour le template
        $GLOBALS['mj_event_page_payload'] = $result;

        return Config::path() . 'includes/templates/front/event-page/context.php';
    }

    add_filter('template_include', 'mj_member_event_page_template_include', 98);
}

if (!function_exists('mj_member_event_admin_bar_edit_link')) {
    function mj_member_event_admin_bar_edit_link($wp_admin_bar) {
        if (is_admin() || !is_admin_bar_showing() || !is_user_logged_in()) {
            return;
        }

        if (!current_user_can(Config::capability())) {
            return;
        }

        // Cherche l'ID de l'événement depuis les différentes sources possibles
        $event_id = 0;
        $event_title = '';

        // Source 1: pré-chargé par template_redirect (mj_event_page_current)
        if (isset($GLOBALS['mj_event_page_current']) && is_array($GLOBALS['mj_event_page_current'])) {
            $event_id = isset($GLOBALS['mj_event_page_current']['id']) ? (int) $GLOBALS['mj_event_page_current']['id'] : 0;
            $event_title = isset($GLOBALS['mj_event_page_current']['title']) ? wp_strip_all_tags($GLOBALS['mj_event_page_current']['title']) : '';
        }

        // Source 2: EventPageController payload (mj_event_page_payload)
        if ($event_id <= 0 && isset($GLOBALS['mj_event_page_payload']) && is_array($GLOBALS['mj_event_page_payload'])) {
            $payload = $GLOBALS['mj_event_page_payload'];
            if (isset($payload['event']['id'])) {
                $event_id = (int) $payload['event']['id'];
                $event_title = isset($payload['event']['title']) ? wp_strip_all_tags($payload['event']['title']) : '';
            }
        }

        if ($event_id <= 0) {
            return;
        }

        $edit_url = add_query_arg(
            array(
                'page' => 'mj_events',
                'action' => 'edit',
                'event' => $event_id,
            ),
            admin_url('admin.php')
        );

        $link_label = $event_title !== ''
            ? sprintf(__('Modifier « %s »', 'mj-member'), $event_title)
            : __('Modifier cet événement', 'mj-member');

        $wp_admin_bar->add_node(
            array(
                'id' => 'mj-member-edit-event',
                'parent' => false,
                'title' => esc_html($link_label),
                'href' => esc_url($edit_url),
                'meta' => array(
                    'class' => 'mj-member-edit-event',
                    'title' => esc_html__("Ouvrir l'édition de cet événement dans le tableau de bord", 'mj-member'),
                ),
            )
        );
    }

    add_action('admin_bar_menu', 'mj_member_event_admin_bar_edit_link', 90);
}

if (!function_exists('mj_member_ajax_get_event_reservations')) {
    function mj_member_ajax_get_event_reservations() {
        if (!wp_doing_ajax()) {
            return;
        }

        check_ajax_referer('mj-member-event-register', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(
                array('message' => __('Connecte-toi pour consulter tes réservations.', 'mj-member')),
                401
            );
        }

        $event_id = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;
        if ($event_id <= 0) {
            wp_send_json_error(
                array('message' => __('Événement introuvable.', 'mj-member')),
                400
            );
        }

        if (!class_exists('MjEvents')) {
            wp_send_json_error(
                array('message' => __('Le module événements est indisponible pour le moment.', 'mj-member')),
                500
            );
        }

        $event = MjEvents::find($event_id);
        if (!$event) {
            wp_send_json_error(
                array('message' => __('Événement introuvable.', 'mj-member')),
                404
            );
        }

        $slug = MjEvents::get_or_create_slug($event_id);
        $context = mj_member_prepare_event_page_context($slug);
        if (!$context || !is_array($context)) {
            wp_send_json_error(
                array('message' => __('Impossible de charger cet événement.', 'mj-member')),
                404
            );
        }

        $registration = isset($context['registration']) && is_array($context['registration']) ? $context['registration'] : array();
        $registration_participants = isset($registration['participants']) && is_array($registration['participants']) ? $registration['participants'] : array();

        $occurrence_catalog = array();
        if (!empty($registration['occurrences']) && is_array($registration['occurrences'])) {
            foreach ($registration['occurrences'] as $occurrence_entry) {
                if (is_array($occurrence_entry)) {
                    $entry = $occurrence_entry;
                } elseif (is_string($occurrence_entry) || is_numeric($occurrence_entry)) {
                    $scalar_value = sanitize_text_field((string) $occurrence_entry);
                    if ($scalar_value === '') {
                        continue;
                    }
                    $entry = array(
                        'slug' => $scalar_value,
                        'start' => $scalar_value,
                    );
                } else {
                    continue;
                }

                if (empty($entry['label'])) {
                    $label_source = '';
                    if (!empty($entry['start'])) {
                        $label_source = (string) $entry['start'];
                    } elseif (!empty($entry['slug'])) {
                        $label_source = (string) $entry['slug'];
                    }

                    if ($label_source !== '') {
                        $label_timestamp = strtotime($label_source);
                        if ($label_timestamp !== false) {
                            $entry['label'] = date_i18n(get_option('date_format'), $label_timestamp) . ' - ' . date_i18n(get_option('time_format'), $label_timestamp);
                        } else {
                            $entry['label'] = $label_source;
                        }
                    }
                }

                $catalog_keys = array();

                if (!empty($entry['slug'])) {
                    $slug_raw = (string) $entry['slug'];
                    $catalog_keys[] = $slug_raw;
                    $catalog_keys[] = sanitize_key($slug_raw);
                    if (class_exists('MjEventAttendance')) {
                        $normalized_slug = MjEventAttendance::normalize_occurrence($slug_raw);
                        if ($normalized_slug !== '') {
                            $catalog_keys[] = $normalized_slug;
                            $catalog_keys[] = sanitize_key($normalized_slug);
                        }
                    }
                }

                if (!empty($entry['start'])) {
                    $start_raw = (string) $entry['start'];
                    $catalog_keys[] = $start_raw;
                    if (class_exists('MjEventAttendance')) {
                        $normalized_start = MjEventAttendance::normalize_occurrence($start_raw);
                        if ($normalized_start !== '') {
                            $catalog_keys[] = $normalized_start;
                            $catalog_keys[] = sanitize_key($normalized_start);
                        }
                    }
                }

                $catalog_keys = array_unique(
                    array_filter(
                        array_map(
                            static function ($key) {
                                return is_string($key) ? trim($key) : '';
                            },
                            $catalog_keys
                        )
                    )
                );

                if (empty($catalog_keys)) {
                    continue;
                }

                foreach ($catalog_keys as $catalog_key) {
                    if ($catalog_key === '') {
                        continue;
                    }
                    $occurrence_catalog[$catalog_key] = $entry;
                }
            }
        }

        $current_member = function_exists('mj_member_get_current_member') ? mj_member_get_current_member() : null;
        $current_member_id = 0;
        if ($current_member && isset($current_member->id)) {
            $current_member_id = (int) $current_member->id;
        }

        $allowed_member_lookup = array();
        if ($current_member_id > 0) {
            $allowed_member_lookup[$current_member_id] = true;
        }

        if ($current_member && function_exists('mj_member_can_manage_children') && function_exists('mj_member_get_guardian_children') && mj_member_can_manage_children($current_member)) {
            $children = mj_member_get_guardian_children($current_member);
            if (!empty($children) && is_array($children)) {
                foreach ($children as $child_entry) {
                    $child_id = 0;
                    if (is_object($child_entry) && isset($child_entry->id)) {
                        $child_id = (int) $child_entry->id;
                    } elseif (is_array($child_entry) && isset($child_entry['id'])) {
                        $child_id = (int) $child_entry['id'];
                    }
                    if ($child_id > 0) {
                        $allowed_member_lookup[$child_id] = true;
                    }
                }
            }
        }

        $cancelled_status_key = '';
        if (class_exists('MjEventRegistrations') && defined('MjEventRegistrations::STATUS_CANCELLED')) {
            $cancelled_status_key = sanitize_key((string) MjEventRegistrations::STATUS_CANCELLED);
        }

        $status_labels = (class_exists('MjEventRegistrations') && method_exists('MjEventRegistrations', 'get_status_labels'))
            ? MjEventRegistrations::get_status_labels()
            : array();

        $sanitized_reservations = array();
        if (!empty($registration_participants)) {
            foreach ($registration_participants as $participant_entry) {
                if (!is_array($participant_entry)) {
                    continue;
                }

                $participant_member_id = 0;
                if (isset($participant_entry['member_id'])) {
                    $participant_member_id = (int) $participant_entry['member_id'];
                } elseif (isset($participant_entry['memberId'])) {
                    $participant_member_id = (int) $participant_entry['memberId'];
                } elseif (isset($participant_entry['id'])) {
                    $participant_member_id = (int) $participant_entry['id'];
                }

                $guardian_id = 0;
                if (isset($participant_entry['guardian_id'])) {
                    $guardian_id = (int) $participant_entry['guardian_id'];
                } elseif (isset($participant_entry['guardianId'])) {
                    $guardian_id = (int) $participant_entry['guardianId'];
                }

                $owns_participant = false;
                if (!empty($allowed_member_lookup)) {
                    if ($participant_member_id > 0 && isset($allowed_member_lookup[$participant_member_id])) {
                        $owns_participant = true;
                    } elseif ($guardian_id > 0 && isset($allowed_member_lookup[$guardian_id])) {
                        $owns_participant = true;
                    } elseif (!empty($participant_entry['isSelf']) && $current_member_id > 0) {
                        $owns_participant = true;
                    }
                } else {
                    $owns_participant = !empty($participant_entry['isSelf']);
                }

                if (!$owns_participant) {
                    continue;
                }

                $status_key = '';
                if (!empty($participant_entry['registrationStatus'])) {
                    $status_key = sanitize_key((string) $participant_entry['registrationStatus']);
                } elseif (!empty($participant_entry['status'])) {
                    $status_key = sanitize_key((string) $participant_entry['status']);
                } elseif (!empty($participant_entry['statut'])) {
                    $status_key = sanitize_key((string) $participant_entry['statut']);
                }

                if ($status_key !== '' && $cancelled_status_key !== '' && $status_key === $cancelled_status_key) {
                    continue;
                }

                $registration_id = 0;
                if (isset($participant_entry['registrationId'])) {
                    $registration_id = (int) $participant_entry['registrationId'];
                } elseif (isset($participant_entry['registration_id'])) {
                    $registration_id = (int) $participant_entry['registration_id'];
                }

                $is_registered = !empty($participant_entry['isRegistered']) || $registration_id > 0 || $status_key !== '';
                if (!$is_registered) {
                    continue;
                }

                $participant_name = isset($participant_entry['name']) ? trim((string) $participant_entry['name']) : '';
                if ($participant_name === '' && !empty($participant_entry['label'])) {
                    $participant_name = trim((string) $participant_entry['label']);
                }
                if ($participant_name === '' && !empty($participant_entry['fullName'])) {
                    $participant_name = trim((string) $participant_entry['fullName']);
                }
                if ($participant_name === '' && (!empty($participant_entry['first_name']) || !empty($participant_entry['last_name']))) {
                    $first_name = !empty($participant_entry['first_name']) ? trim((string) $participant_entry['first_name']) : '';
                    $last_name = !empty($participant_entry['last_name']) ? trim((string) $participant_entry['last_name']) : '';
                    $participant_name = trim($first_name . ' ' . $last_name);
                }
                if ($participant_name === '' && $participant_member_id > 0) {
                    $participant_name = sprintf(__('Participant #%d', 'mj-member'), $participant_member_id);
                }
                if ($participant_name === '') {
                    $participant_name = __('Participant', 'mj-member');
                }

                $status_label = '';
                if ($status_key !== '' && isset($status_labels[$status_key])) {
                    $status_label = $status_labels[$status_key];
                } elseif (!empty($participant_entry['registrationStatusLabel'])) {
                    $status_label = (string) $participant_entry['registrationStatusLabel'];
                } elseif (!empty($participant_entry['status_label'])) {
                    $status_label = (string) $participant_entry['status_label'];
                } elseif (!empty($participant_entry['statusLabel'])) {
                    $status_label = (string) $participant_entry['statusLabel'];
                } elseif ($status_key !== '') {
                    $status_label = ucfirst(str_replace('_', ' ', $status_key));
                }

                $status_class = $status_key !== '' ? 'is-status-' . sanitize_html_class($status_key) : '';

                $created_label = '';
                if (!empty($participant_entry['registrationCreatedAt'])) {
                    $created_raw = (string) $participant_entry['registrationCreatedAt'];
                    $timestamp = strtotime($created_raw);
                    if ($timestamp) {
                        $created_label = date_i18n(get_option('date_format'), $timestamp);
                    }
                } elseif (!empty($participant_entry['created_at'])) {
                    $created_raw_alt = (string) $participant_entry['created_at'];
                    $timestamp_alt = strtotime($created_raw_alt);
                    if ($timestamp_alt) {
                        $created_label = date_i18n(get_option('date_format'), $timestamp_alt);
                    }
                }

                $occurrence_texts = array();
                $assignments = array();
                if (isset($participant_entry['occurrenceAssignments']) && is_array($participant_entry['occurrenceAssignments'])) {
                    $assignments = $participant_entry['occurrenceAssignments'];
                } elseif (isset($participant_entry['occurrence_assignments']) && is_array($participant_entry['occurrence_assignments'])) {
                    $assignments = $participant_entry['occurrence_assignments'];
                }

                $assignments_mode = isset($assignments['mode']) ? sanitize_key((string) $assignments['mode']) : 'all';
                $assigned_values = isset($assignments['occurrences']) && is_array($assignments['occurrences']) ? $assignments['occurrences'] : array();

                if ($assignments_mode === 'custom' && !empty($assigned_values)) {
                    foreach ($assigned_values as $assigned_slug) {
                        $assigned_slug_raw = '';
                        if (is_string($assigned_slug) || is_numeric($assigned_slug)) {
                            $assigned_slug_raw = (string) $assigned_slug;
                        } elseif (is_array($assigned_slug) && isset($assigned_slug['slug'])) {
                            $assigned_slug_raw = (string) $assigned_slug['slug'];
                        }

                        if ($assigned_slug_raw === '') {
                            continue;
                        }

                        $lookup_keys = array($assigned_slug_raw, sanitize_key($assigned_slug_raw));
                        if (class_exists('MjEventAttendance')) {
                            $normalized_slug = MjEventAttendance::normalize_occurrence($assigned_slug_raw);
                            if ($normalized_slug !== '') {
                                $lookup_keys[] = $normalized_slug;
                                $lookup_keys[] = sanitize_key($normalized_slug);
                            }
                        }

                        $catalog_entry = null;
                        foreach ($lookup_keys as $lookup_key) {
                            if (!is_string($lookup_key) || $lookup_key === '') {
                                continue;
                            }
                            if (isset($occurrence_catalog[$lookup_key])) {
                                $catalog_entry = $occurrence_catalog[$lookup_key];
                                break;
                            }
                        }

                        if ($catalog_entry && !empty($catalog_entry['label'])) {
                            $occurrence_texts[] = sanitize_text_field((string) $catalog_entry['label']);
                            continue;
                        }

                        $fallback_source = '';
                        if ($catalog_entry && !empty($catalog_entry['start'])) {
                            $fallback_source = (string) $catalog_entry['start'];
                        } else {
                            $fallback_source = $assigned_slug_raw;
                        }

                        $fallback_label = '';
                        if ($fallback_source !== '') {
                            $fallback_timestamp = strtotime($fallback_source);
                            if ($fallback_timestamp !== false) {
                                $fallback_label = date_i18n(get_option('date_format'), $fallback_timestamp) . ' - ' . date_i18n(get_option('time_format'), $fallback_timestamp);
                            }
                        }

                        if ($fallback_label === '') {
                            $fallback_label = $assigned_slug_raw;
                        }

                        $occurrence_texts[] = sanitize_text_field($fallback_label);
                    }

                    if (!empty($occurrence_texts)) {
                        $occurrence_texts = array_values(array_unique($occurrence_texts));
                    }
                }

                if (empty($occurrence_texts)) {
                    if ($assignments_mode === 'custom') {
                        $occurrence_texts[] = __('Occurrences à confirmer', 'mj-member');
                    } else {
                        $occurrence_texts[] = __('Toutes les occurrences', 'mj-member');
                    }
                }

                $sanitized_reservations[] = array(
                    'name' => sanitize_text_field($participant_name),
                    'status_label' => sanitize_text_field($status_label),
                    'status_class' => $status_class,
                    'status_key' => $status_key,
                    'created_label' => $created_label,
                    'occurrences' => $occurrence_texts,
                    'member_id' => $participant_member_id,
                    'registration_id' => $registration_id,
                    'can_cancel' => $owns_participant && $registration_id > 0,
                );
            }
        }

        $response = array(
            'reservations' => $sanitized_reservations,
            'has_reservations' => !empty($sanitized_reservations),
            'empty_message' => __('Tu n\'as pas encore de réservation pour cet événement.', 'mj-member'),
        );

        $response = mj_member_normalize_json_payload($response);

        wp_send_json_success($response);
    }

    add_action('wp_ajax_mj_member_get_event_reservations', 'mj_member_ajax_get_event_reservations');
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

        if (!class_exists('MjEvents') || !class_exists('MjEventRegistrations') || !class_exists('MjMembers')) {
            wp_send_json_error(
                array('message' => __('Le module événements est indisponible pour le moment.', 'mj-member')),
                500
            );
        }

        $event = MjEvents::find($event_id);
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
        $raw_deadline = isset($event->date_fin_inscription) ? trim((string) $event->date_fin_inscription) : '';
        $has_custom_deadline = ($raw_deadline !== '' && $raw_deadline !== '0000-00-00 00:00:00');

        if ($has_custom_deadline) {
            $deadline_ts = strtotime($raw_deadline);
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

        if ($deadline_passed && $closed_by_start && !$has_custom_deadline && class_exists('MjEventSchedule')) {
            $upcoming_occurrences = MjEventSchedule::get_occurrences(
                $event,
                array(
                    'max' => 1,
                    'include_past' => false,
                )
            );

            if (!empty($upcoming_occurrences)) {
                $deadline_passed = false;
                $has_future_occurrence = true;
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

        $participant = MjMembers::getById($member_id);
        if (!$participant) {
            wp_send_json_error(
                array('message' => __('Profil membre introuvable.', 'mj-member')),
                404
            );
        }

        $payment_mode_raw = isset($_POST['payment_mode']) ? wp_unslash($_POST['payment_mode']) : '';
        $payment_mode = $payment_mode_raw !== '' ? sanitize_key($payment_mode_raw) : '';
        $payment_deferred = in_array($payment_mode, array('defer', 'email', 'delayed'), true);

        $note_input_present = array_key_exists('note', $_POST);
        $note_value = $note_input_present ? sanitize_textarea_field(wp_unslash($_POST['note'])) : '';
        if ($note_value !== '') {
            if (function_exists('mb_substr')) {
                $note_value = mb_substr($note_value, 0, 400);
            } else {
                $note_value = substr($note_value, 0, 400);
            }
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

        $existing_registration = MjEventRegistrations::get_existing($event_id, $member_id);
        if ($existing_registration && (!isset($existing_registration->statut) || $existing_registration->statut !== MjEventRegistrations::STATUS_CANCELLED)) {
            $desired_assignments = array(
                'mode' => !empty($occurrence_selection) ? 'custom' : 'all',
                'occurrences' => !empty($occurrence_selection) ? array_values($occurrence_selection) : array(),
            );

            $current_assignments = array('mode' => 'all', 'occurrences' => array());
            if (class_exists('MjEventAttendance')) {
                $current_assignments = MjEventAttendance::get_registration_assignments($existing_registration);
            }

            $current_mode = isset($current_assignments['mode']) ? sanitize_key((string) $current_assignments['mode']) : 'all';
            if ($current_mode !== 'custom') {
                $current_mode = 'all';
            }

            $current_occurrences = array();
            if (!empty($current_assignments['occurrences']) && is_array($current_assignments['occurrences'])) {
                foreach ($current_assignments['occurrences'] as $assignment_value) {
                    if (!is_string($assignment_value) && !is_numeric($assignment_value)) {
                        continue;
                    }
                    $normalized_assignment = sanitize_text_field((string) $assignment_value);
                    if ($normalized_assignment === '') {
                        continue;
                    }
                    $current_occurrences[$normalized_assignment] = $normalized_assignment;
                }
            }
            $current_occurrences = array_values($current_occurrences);
            sort($current_occurrences);

            $desired_occurrence_map = array();
            if (!empty($desired_assignments['occurrences'])) {
                foreach ($desired_assignments['occurrences'] as $desired_value) {
                    if (!is_string($desired_value) && !is_numeric($desired_value)) {
                        continue;
                    }
                    $normalized_desired = sanitize_text_field((string) $desired_value);
                    if ($normalized_desired === '') {
                        continue;
                    }
                    $desired_occurrence_map[$normalized_desired] = $normalized_desired;
                }
            }
            $desired_occurrences = array_values($desired_occurrence_map);
            sort($desired_occurrences);

            $assignments_changed = ($desired_assignments['mode'] !== $current_mode) || ($desired_occurrences !== $current_occurrences);
            $assignments_updated = false;
            $note_updated = false;
            $update_messages = array();

            if ($assignments_changed) {
                if (!class_exists('MjEventAttendance')) {
                    wp_send_json_error(
                        array('message' => __('Ce participant est déjà inscrit à cet événement.', 'mj-member')),
                        409
                    );
                }

                $assignment_result = MjEventAttendance::set_registration_assignments((int) $existing_registration->id, $desired_assignments);
                if (is_wp_error($assignment_result)) {
                    wp_send_json_error(
                        array('message' => $assignment_result->get_error_message()),
                        500
                    );
                }

                $assignments_updated = true;
                $update_messages[] = __('Occurrences mises à jour.', 'mj-member');
            }

            $existing_note = isset($existing_registration->notes) ? (string) $existing_registration->notes : '';
            if ($note_input_present && $existing_note !== $note_value) {
                $note_result = MjEventRegistrations::update((int) $existing_registration->id, array('notes' => $note_value));
                if (is_wp_error($note_result)) {
                    wp_send_json_error(
                        array('message' => $note_result->get_error_message()),
                        500
                    );
                }

                $note_updated = true;
                $update_messages[] = $note_value !== ''
                    ? __('Message mis à jour.', 'mj-member')
                    : __('Message supprimé.', 'mj-member');
            }

            if ($assignments_updated || $note_updated) {
                $latest_registration = MjEventRegistrations::get((int) $existing_registration->id);
                if (!$latest_registration) {
                    $latest_registration = $existing_registration;
                }

                $latest_assignments = class_exists('MjEventAttendance')
                    ? MjEventAttendance::get_registration_assignments($latest_registration)
                    : $desired_assignments;

                $latest_note = isset($latest_registration->notes) ? (string) $latest_registration->notes : '';

                if (empty($update_messages)) {
                    $update_messages[] = __('Inscription mise à jour.', 'mj-member');
                }

                $response_payload = array(
                    'message' => implode(' ', $update_messages),
                    'registration_id' => (int) $existing_registration->id,
                    'assignments' => $latest_assignments,
                    'note' => $latest_note,
                    'updated' => array(
                        'assignments' => $assignments_updated,
                        'note' => $note_updated,
                    ),
                );

                $response_payload = mj_member_normalize_json_payload($response_payload);

                wp_send_json_success($response_payload);
            }

            wp_send_json_error(
                array('message' => __('Ce participant est déjà inscrit à cet événement.', 'mj-member')),
                409
            );
        }

        $create_args = array();
        if ($guardian_id > 0) {
            $create_args['guardian_id'] = $guardian_id;
        }

        if ($note_value !== '') {
            $create_args['notes'] = $note_value;
        }

        if ($has_future_occurrence) {
            $create_args['allow_late_registration'] = true;
        }

        $registration_payload = $create_args;
        $registration_payload['event_id'] = $event_id;
        $registration_payload['member_id'] = $member_id;

        $result = MjEventRegistrations::create($registration_payload);

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
        $payment_email_sent = false;
        $payment_email_error = false;
        $payment_payload_response = null;

        $occurrence_mode = !empty($occurrence_selection) ? 'custom' : 'all';
        $occurrence_count = ($occurrence_mode === 'custom') ? count($occurrence_selection) : 1;
        if ($occurrence_count <= 0) {
            $occurrence_count = 1;
            $occurrence_mode = 'all';
        }
        $occurrence_list = !empty($occurrence_selection) ? array_values($occurrence_selection) : array();

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
                        'occurrence_mode' => $occurrence_mode,
                        'occurrence_count' => $occurrence_count,
                        'occurrence_list' => $occurrence_list,
                    )
                );

                if (!$payment_payload || empty($payment_payload['checkout_url'])) {
                    $payment_payload = null;
                    $payment_error = true;
                }
            } else {
                $payment_error = true;
            }

            if ($payment_payload && !$payment_error) {
                if ($payment_deferred) {
                    $payment_payload_response = null;

                    if (function_exists('mj_member_get_event_registrations_table_name')) {
                        global $wpdb;
                        $registrations_table = mj_member_get_event_registrations_table_name();
                        $wpdb->update(
                            $registrations_table,
                            array(
                                'payment_status' => 'unpaid',
                                'payment_method' => 'stripe_email',
                            ),
                            array('id' => (int) $result),
                            array('%s', '%s'),
                            array('%d')
                        );
                    }

                    if (class_exists('MjMail')) {
                        $amount_raw = isset($payment_payload['amount_raw']) ? (float) $payment_payload['amount_raw'] : ($event_price * max(1, $occurrence_count));
                        $amount_label = isset($payment_payload['amount_label']) && $payment_payload['amount_label'] !== ''
                            ? $payment_payload['amount_label']
                            : number_format_i18n($amount_raw, 2);

                        $occurrence_lines = '';
                        if (!empty($occurrence_list)) {
                            $occurrence_lines = '<p>' . esc_html__('Occurrences sélectionnées :', 'mj-member') . '</p><ul>';
                            foreach ($occurrence_list as $occurrence_value) {
                                $label = $occurrence_value;
                                $timestamp = strtotime($occurrence_value);
                                if ($timestamp) {
                                    $label = wp_date(get_option('date_format', 'd/m/Y') . ' ' . get_option('time_format', 'H:i'), $timestamp);
                                }
                                $occurrence_lines .= '<li>' . esc_html($label) . '</li>';
                            }
                            $occurrence_lines .= '</ul>';
                        }

                        $payment_link = $payment_payload['checkout_url'];
                        $payment_body = '<p>' . esc_html__('Ton inscription est bien enregistrée.', 'mj-member') . '</p>';
                        $payment_body .= '<p>' . sprintf(
                            esc_html__('Pour finaliser ta participation à « %s », règle le montant de %s € grâce au bouton ci-dessous :', 'mj-member'),
                            esc_html($event->title),
                            esc_html($amount_label)
                        ) . '</p>';
                        $payment_body .= '<p><a href="' . esc_url($payment_link) . '" target="_blank" rel="noopener" class="mj-button">' . esc_html__('Payer en ligne', 'mj-member') . '</a></p>';
                        $payment_body .= '<p>' . esc_html__('Si le bouton ne s’ouvre pas, copie ce lien dans ton navigateur :', 'mj-member') . '<br><a href="' . esc_url($payment_link) . '" target="_blank" rel="noopener">' . esc_html($payment_link) . '</a></p>';
                        if ($occurrence_lines !== '') {
                            $payment_body .= $occurrence_lines;
                        }
                        $payment_body .= '<p>' . esc_html__('Tu peux aussi régler en espèces auprès d’un animateur à l’accueil.', 'mj-member') . '</p>';

                        $mail_context = array(
                            'payment_link' => $payment_link,
                            'payment_qr_url' => isset($payment_payload['qr_url']) ? $payment_payload['qr_url'] : '',
                            'payment_amount' => $amount_raw,
                            'include_guardian' => true,
                            'event' => $event,
                            'registration_id' => (int) $result,
                            'occurrences' => $occurrence_list,
                        );

                        $payment_subject = sprintf(
                            esc_html__('Paiement pour %s', 'mj-member'),
                            esc_html($event->title)
                        );

                        $payment_email_sent = MjMail::send_custom_email(
                            $participant,
                            $payment_subject,
                            $payment_body,
                            array('context' => $mail_context)
                        );

                        if (!$payment_email_sent) {
                            $payment_email_error = true;
                        }
                    } else {
                        $payment_email_error = true;
                    }

                    if ($payment_email_error) {
                        error_log(sprintf('MJ Member: echec envoi email paiement pour event #%d inscription #%d', (int) $event_id, (int) $result));
                    }
                } else {
                    $payment_payload_response = $payment_payload;
                }
            }
        }

        if ($payment_required && $payment_error) {
            error_log(sprintf('MJ Member: echec creation paiement Stripe pour event #%d inscription #%d', (int) $event_id, (int) $result));
        }

        $success_message = __('Inscription enregistrée ! Nous reviendrons vers vous rapidement.', 'mj-member');
        if ($is_waitlist) {
            $success_message = __('Inscription enregistrée sur liste d\'attente. Nous vous informerons dès qu\'une place se libère.', 'mj-member');
        } elseif ($payment_required && !$payment_error) {
            if ($payment_deferred) {
                if ($payment_email_error) {
                    $success_message = __('Inscription enregistrée, mais l\'envoi de l\'email de paiement a échoué. Merci de contacter l\'équipe MJ.', 'mj-member');
                } else {
                    $success_message = __('Inscription enregistrée ! Tu recevras un email avec le lien de paiement très bientôt.', 'mj-member');
                }
            } else {
                $success_message = __('Inscription enregistrée ! Merci de finaliser le paiement sécurisé.', 'mj-member');
            }
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
            'payment_mode' => isset($payment_mode) ? $payment_mode : '',
            'payment_deferred' => isset($payment_deferred) ? $payment_deferred : false,
            'payment_email_sent' => $payment_email_sent,
            'payment_email_error' => $payment_email_error,
        );

        if ($payment_payload_response) {
            $amount_value = isset($payment_payload_response['amount_label']) && $payment_payload_response['amount_label'] !== ''
                ? $payment_payload_response['amount_label']
                : (isset($payment_payload_response['amount']) ? $payment_payload_response['amount'] : '');
            $response['payment'] = array(
                'checkout_url' => $payment_payload_response['checkout_url'],
                'qr_url' => isset($payment_payload_response['qr_url']) ? $payment_payload_response['qr_url'] : '',
                'amount' => $amount_value,
                'occurrence_mode' => $occurrence_mode,
                'occurrence_count' => $occurrence_count,
            );
        }

        $response = mj_member_normalize_json_payload($response);

        $json_flags = defined('JSON_INVALID_UTF8_SUBSTITUTE')
            ? JSON_INVALID_UTF8_SUBSTITUTE
            : 0;

        if (defined('JSON_UNESCAPED_UNICODE')) {
            $json_flags |= JSON_UNESCAPED_UNICODE;
        }

        $partial_flag = defined('JSON_PARTIAL_OUTPUT_ON_ERROR') ? JSON_PARTIAL_OUTPUT_ON_ERROR : 0;
        if ($partial_flag) {
            $json_flags |= $partial_flag;
        }

        $encoded = wp_json_encode(array('success' => true, 'data' => $response), $json_flags);

        if ($encoded === false && $partial_flag) {
            $response = mj_member_normalize_json_payload($response);
            $encoded = wp_json_encode(array('success' => true, 'data' => $response), $json_flags & ~$partial_flag);
        }

        if ($encoded === false) {
            $response = array('message' => __('Inscription enregistrée, mais un souci est survenu lors de la réponse.', 'mj-member'));
            $encoded = wp_json_encode(array('success' => true, 'data' => $response));
        }

        if (!headers_sent()) {
            nocache_headers();
            header('Content-Type: application/json; charset=utf-8');
        }

        echo (string) $encoded;
        wp_die('', '', array('response' => null));
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

        if (!class_exists('MjEvents') || !class_exists('MjEventRegistrations') || !class_exists('MjMembers')) {
            wp_send_json_error(
                array('message' => __('Le module événements est indisponible pour le moment.', 'mj-member')),
                500
            );
        }

        $event = MjEvents::find($event_id);
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

        $update = MjEventRegistrations::update(
            (int) $existing_registration->id,
            array('statut' => MjEventRegistrations::STATUS_CANCELLED)
        );
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
