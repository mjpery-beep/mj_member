<?php
/**
 * Front-end AJAX handlers for event registration operations.
 *
 * @package MJ_Member
 * @subpackage Core\Ajax\Front
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX: Get event reservations for current user.
 *
 * Retrieves all active registrations for the current member and their dependents
 * for a specific event, including occurrence assignments and status information.
 *
 * POST Parameters:
 * - event_id (int): Event identifier
 * - nonce (string): Security token (mj-member-event-register)
 *
 * Response: JSON with reservations array
 */
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
            'empty_message' => __("Tu n'as pas encore de réservation pour cet événement.", 'mj-member'),
        );

        $response = mj_member_normalize_json_payload($response);

        wp_send_json_success($response);
    }
}

add_action('wp_ajax_mj_member_get_event_reservations', 'mj_member_ajax_get_event_reservations');

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
                array('message' => __("Votre profil membre est introuvable. Contactez l'équipe MJ.", 'mj-member')),
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
                        $payment_body = '<p>' . esc_html__("Ton inscription est bien enregistrée.", 'mj-member') . '</p>';
                        $payment_body .= '<p>' . sprintf(
                            esc_html__("Pour finaliser ta participation à « %s », règle le montant de %s € grâce au bouton ci-dessous :", 'mj-member'),
                            esc_html($event->title),
                            esc_html($amount_label)
                        ) . '</p>';
                        $payment_body .= '<p><a href="' . esc_url($payment_link) . '" target="_blank" rel="noopener" class="mj-button">' . esc_html__('Payer en ligne', 'mj-member') . '</a></p>';
                        $payment_body .= '<p>' . esc_html__("Si le bouton ne s'ouvre pas, copie ce lien dans ton navigateur :", 'mj-member') . '<br><a href="' . esc_url($payment_link) . '" target="_blank" rel="noopener">' . esc_html($payment_link) . '</a></p>';
                        if ($occurrence_lines !== '') {
                            $payment_body .= $occurrence_lines;
                        }
                        $payment_body .= '<p>' . esc_html__("Tu peux aussi régler en espèces auprès d'un animateur à l'accueil.", 'mj-member') . '</p>';

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
            $success_message = __("Inscription enregistrée sur liste d'attente. Nous vous informerons dès qu'une place se libère.", 'mj-member');
        } elseif ($payment_required && !$payment_error) {
            if ($payment_deferred) {
                if ($payment_email_error) {
                    $success_message = __("Inscription enregistrée, mais l'envoi de l'email de paiement a échoué. Merci de contacter l'équipe MJ.", 'mj-member');
                } else {
                    $success_message = __("Inscription enregistrée ! Tu recevras un email avec le lien de paiement très bientôt.", 'mj-member');
                }
            } else {
                $success_message = __('Inscription enregistrée ! Merci de finaliser le paiement sécurisé.', 'mj-member');
            }
        } elseif ($payment_required && $payment_error) {
            $success_message = __("Inscription enregistrée, mais la création du paiement a échoué. Merci de contacter l'équipe MJ pour finaliser le règlement.", 'mj-member');
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
            wp_send_json_success($response);
            return;
        }

        header('Content-Type: application/json; charset=UTF-8');
        echo $encoded;
        wp_die();
    }
}

add_action('wp_ajax_mj_member_register_event', 'mj_member_ajax_register_event');

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

        if ($event_id <= 0 || $member_id <= 0) {
            wp_send_json_error(
                array('message' => __('Requête invalide.', 'mj-member')),
                400
            );
        }

        if (!class_exists('MjEvents') || !class_exists('MjEventRegistrations') || !class_exists('MjMembers')) {
            wp_send_json_error(
                array('message' => __('Le module événements est indisponible pour le moment.', 'mj-member')),
                500
            );
        }

        $current_member = function_exists('mj_member_get_current_member') ? mj_member_get_current_member() : null;
        if (!$current_member || empty($current_member->id)) {
            wp_send_json_error(
                array('message' => __("Votre profil membre est introuvable. Contactez l'équipe MJ.", 'mj-member')),
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
                array('message' => __('Vous ne pouvez pas annuler cette inscription.', 'mj-member')),
                403
            );
        }

        $existing_registration = MjEventRegistrations::get_existing($event_id, $member_id);
        if (!$existing_registration || (isset($existing_registration->statut) && $existing_registration->statut === MjEventRegistrations::STATUS_CANCELLED)) {
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
}

add_action('wp_ajax_mj_member_unregister_event', 'mj_member_ajax_unregister_event');
