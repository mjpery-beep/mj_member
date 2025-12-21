<?php
/**
 * AJAX handlers for Registration Manager widget
 * 
 * @package MjMember
 */

use Mj\Member\Classes\Crud\MjEvents;
use Mj\Member\Classes\Crud\MjEventRegistrations;
use Mj\Member\Classes\Crud\MjEventAttendance;
use Mj\Member\Classes\Crud\MjEventOccurrences;
use Mj\Member\Classes\Crud\MjEventAnimateurs;
use Mj\Member\Classes\Crud\MjEventPhotos;
use Mj\Member\Classes\Crud\MjContactMessages;
use Mj\Member\Classes\Crud\MjIdeas;
use Mj\Member\Classes\Crud\MjMembers;
use Mj\Member\Classes\MjEventSchedule;
use Mj\Member\Classes\MjRoles;
use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

// Register AJAX actions
add_action('wp_ajax_mj_regmgr_get_events', 'mj_regmgr_get_events');
add_action('wp_ajax_mj_regmgr_get_event_details', 'mj_regmgr_get_event_details');
add_action('wp_ajax_mj_regmgr_get_registrations', 'mj_regmgr_get_registrations');
add_action('wp_ajax_mj_regmgr_search_members', 'mj_regmgr_search_members');
add_action('wp_ajax_mj_regmgr_add_registration', 'mj_regmgr_add_registration');
add_action('wp_ajax_mj_regmgr_update_registration', 'mj_regmgr_update_registration');
add_action('wp_ajax_mj_regmgr_delete_registration', 'mj_regmgr_delete_registration');
add_action('wp_ajax_mj_regmgr_update_attendance', 'mj_regmgr_update_attendance');
add_action('wp_ajax_mj_regmgr_bulk_attendance', 'mj_regmgr_bulk_attendance');
add_action('wp_ajax_mj_regmgr_validate_payment', 'mj_regmgr_validate_payment');
add_action('wp_ajax_mj_regmgr_cancel_payment', 'mj_regmgr_cancel_payment');
add_action('wp_ajax_mj_regmgr_create_quick_member', 'mj_regmgr_create_quick_member');
add_action('wp_ajax_mj_regmgr_get_member_notes', 'mj_regmgr_get_member_notes');
add_action('wp_ajax_mj_regmgr_save_member_note', 'mj_regmgr_save_member_note');
add_action('wp_ajax_mj_regmgr_delete_member_note', 'mj_regmgr_delete_member_note');
add_action('wp_ajax_mj_regmgr_get_payment_qr', 'mj_regmgr_get_payment_qr');
add_action('wp_ajax_mj_regmgr_update_occurrences', 'mj_regmgr_update_occurrences');

// Members management actions
add_action('wp_ajax_mj_regmgr_get_members', 'mj_regmgr_get_members');
add_action('wp_ajax_mj_regmgr_get_member_details', 'mj_regmgr_get_member_details');
add_action('wp_ajax_mj_regmgr_update_member', 'mj_regmgr_update_member');
add_action('wp_ajax_mj_regmgr_get_member_registrations', 'mj_regmgr_get_member_registrations');
add_action('wp_ajax_mj_regmgr_mark_membership_paid', 'mj_regmgr_mark_membership_paid');
add_action('wp_ajax_mj_regmgr_create_membership_payment_link', 'mj_regmgr_create_membership_payment_link');
add_action('wp_ajax_mj_regmgr_update_member_idea', 'mj_regmgr_update_member_idea');
add_action('wp_ajax_mj_regmgr_update_member_photo', 'mj_regmgr_update_member_photo');
add_action('wp_ajax_mj_regmgr_delete_member_photo', 'mj_regmgr_delete_member_photo');
add_action('wp_ajax_mj_regmgr_delete_member_message', 'mj_regmgr_delete_member_message');

/**
 * Verify nonce and check user permissions
 * 
 * @return array|false Member data if authorized, false otherwise
 */
function mj_regmgr_verify_request() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mj-registration-manager')) {
        wp_send_json_error(array('message' => __('Vérification de sécurité échouée.', 'mj-member')), 403);
        return false;
    }

    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => __('Vous devez être connecté.', 'mj-member')), 401);
        return false;
    }

    $current_user_id = get_current_user_id();
    $member = MjMembers::getByWpUserId($current_user_id);

    if (!$member) {
        wp_send_json_error(array('message' => __('Profil membre introuvable.', 'mj-member')), 403);
        return false;
    }

    $member_role = isset($member->role) ? $member->role : '';
    $allowed_roles = array(MjRoles::ANIMATEUR, MjRoles::BENEVOLE, MjRoles::COORDINATEUR);

    if (!in_array($member_role, $allowed_roles, true) && !current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Permissions insuffisantes.', 'mj-member')), 403);
        return false;
    }

    return array(
        'member' => $member,
        'member_id' => isset($member->id) ? (int) $member->id : 0,
        'role' => $member_role,
        'is_coordinateur' => $member_role === MjRoles::COORDINATEUR || current_user_can('manage_options'),
    );
}

/**
 * Get member avatar URL
 * 
 * @param int $member_id Member ID
 * @return string Avatar URL or empty string
 */
function mj_regmgr_get_member_avatar_url($member_id) {
    $member = MjMembers::getById($member_id);
    if (!$member) {
        return '';
    }

    // Check if member has photo_id (primary) or avatar_id (fallback)
    $photo_id = isset($member->photo_id) ? (int) $member->photo_id : 0;
    if ($photo_id > 0) {
        $url = wp_get_attachment_image_url($photo_id, 'thumbnail');
        if ($url) {
            return $url;
        }
    }

    // Check avatar_id as fallback
    $avatar_id = isset($member->avatar_id) ? (int) $member->avatar_id : 0;
    if ($avatar_id > 0) {
        $url = wp_get_attachment_image_url($avatar_id, 'thumbnail');
        if ($url) {
            return $url;
        }
    }

    // Fallback to default avatar
    $default_avatar_id = (int) get_option('mj_login_default_avatar_id', 0);
    if ($default_avatar_id > 0) {
        $url = wp_get_attachment_image_url($default_avatar_id, 'thumbnail');
        if ($url) {
            return $url;
        }
    }

    return '';
}

/**
 * Normalize a boolean-like value coming from the frontend payload.
 *
 * @param mixed $value Raw value
 * @param bool $default Fallback when value cannot be interpreted
 * @return bool
 */
function mj_regmgr_to_bool($value, $default = false) {
    if (is_bool($value)) {
        return $value;
    }

    if (is_int($value) || is_float($value)) {
        return (int) $value === 1;
    }

    if (is_string($value)) {
        $normalized = strtolower(trim($value));
        if (in_array($normalized, array('1', 'true', 'yes', 'on'), true)) {
            return true;
        }
        if (in_array($normalized, array('0', 'false', 'no', 'off', ''), true)) {
            return false;
        }
    }

    return $default;
}

/**
 * Get events list
 */
function mj_regmgr_get_events() {
    $auth = mj_regmgr_verify_request();
    if (!$auth) return;

    $filter = isset($_POST['filter']) ? sanitize_key($_POST['filter']) : 'assigned';
    $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
    $page = isset($_POST['page']) ? max(1, (int) $_POST['page']) : 1;
    $per_page = isset($_POST['perPage']) ? max(5, min(100, (int) $_POST['perPage'])) : 20;

    $now = current_time('mysql');
    $events = array();
    $total = 0;

    // Pour le filtre "assigned", utiliser MjEventAnimateurs
    if ($filter === 'assigned' && !$auth['is_coordinateur']) {
        $assigned_args = array(
            'statuses' => array(MjEvents::STATUS_ACTIVE),
            'orderby' => 'date_debut',
            'order' => 'DESC',
        );
        $all_assigned = MjEventAnimateurs::get_events_for_member($auth['member_id'], $assigned_args);
        
        // Filtrer par recherche si nécessaire
        if ($search !== '') {
            $search_lower = mb_strtolower($search);
            $all_assigned = array_filter($all_assigned, function($event) use ($search_lower) {
                return mb_strpos(mb_strtolower($event->title), $search_lower) !== false;
            });
        }
        
        $total = count($all_assigned);
        $events = array_slice(array_values($all_assigned), ($page - 1) * $per_page, $per_page);
    } else {
        // Pour les autres filtres, utiliser MjEvents::get_all
        $args = array(
            'search' => $search,
            'orderby' => 'date_debut',
            'order' => 'DESC',
            'limit' => $per_page,
            'offset' => ($page - 1) * $per_page,
        );

        switch ($filter) {
            case 'assigned':
                // Coordinateur voit tout
                $args['statuses'] = array(MjEvents::STATUS_ACTIVE);
                break;

            case 'upcoming':
                $args['statuses'] = array(MjEvents::STATUS_ACTIVE);
                $args['after'] = $now;
                $args['order'] = 'ASC';
                break;

            case 'past':
                $args['statuses'] = array(MjEvents::STATUS_PAST, MjEvents::STATUS_ACTIVE);
                $args['before'] = $now;
                break;

            case 'draft':
                $args['statuses'] = array(MjEvents::STATUS_DRAFT);
                break;

            case 'internal':
                $args['types'] = array(MjEvents::TYPE_INTERNE);
                break;

            default: // all
                // No specific filters
                break;
        }

        $events = MjEvents::get_all($args);
        $total = MjEvents::count($args);
    }

    $type_labels = MjEvents::get_type_labels();
    $status_labels = MjEvents::get_status_labels();

    $events_data = array();
    foreach ($events as $event) {
        $registrations_count = MjEventRegistrations::count(array('event_id' => $event->id));
        
        $events_data[] = array(
            'id' => $event->id,
            'title' => $event->title,
            'type' => $event->type,
            'typeLabel' => isset($type_labels[$event->type]) ? $type_labels[$event->type] : $event->type,
            'status' => $event->status,
            'statusLabel' => isset($status_labels[$event->status]) ? $status_labels[$event->status] : $event->status,
            'dateDebut' => $event->date_debut,
            'dateFin' => $event->date_fin,
            'dateDebutFormatted' => mj_regmgr_format_date($event->date_debut),
            'dateFinFormatted' => mj_regmgr_format_date($event->date_fin),
            'coverId' => $event->cover_id,
            'coverUrl' => mj_regmgr_get_event_cover_url($event, 'thumbnail'),
            'accentColor' => $event->accent_color,
            'registrationsCount' => $registrations_count,
            'capacityTotal' => $event->capacity_total,
            'prix' => (float) $event->prix,
            'scheduleMode' => $event->schedule_mode ?? 'fixed',
            'occurrenceSelectionMode' => isset($event->occurrence_selection_mode) && $event->occurrence_selection_mode !== ''
                ? $event->occurrence_selection_mode
                : 'member_choice',
        );
    }

    wp_send_json_success(array(
        'events' => $events_data,
        'pagination' => array(
            'total' => $total,
            'page' => $page,
            'perPage' => $per_page,
            'totalPages' => ceil($total / $per_page),
        ),
    ));
}

/**
 * Get single event details with occurrences
 */
function mj_regmgr_get_event_details() {
    $auth = mj_regmgr_verify_request();
    if (!$auth) return;

    $event_id = isset($_POST['eventId']) ? (int) $_POST['eventId'] : 0;
    if ($event_id <= 0) {
        wp_send_json_error(array('message' => __('ID événement invalide.', 'mj-member')));
        return;
    }

    $event = MjEvents::find($event_id);
    if (!$event) {
        wp_send_json_error(array('message' => __('Événement introuvable.', 'mj-member')));
        return;
    }

    // Get occurrences
    $occurrences = array();
    $schedule_mode = $event->schedule_mode ?? 'fixed';
    
    if (class_exists('Mj\Member\Classes\MjEventSchedule')) {
        // Utiliser la méthode statique get_occurrences
        $raw_occurrences = MjEventSchedule::get_occurrences($event, array(
            'max' => 100,
            'include_past' => true,
        ));
        
        // Formater les occurrences pour le frontend
        foreach ($raw_occurrences as $occ) {
            $start = $occ['start'] ?? ($occ['date'] ?? '');
            $end = $occ['end'] ?? '';
            
            if (!empty($start)) {
                $occurrences[] = array(
                    'id' => $occ['id'] ?? md5($start),
                    'start' => $start,
                    'end' => $end,
                    'date' => substr($start, 0, 10),
                    'startFormatted' => mj_regmgr_format_date($start, true),
                    'endFormatted' => $end ? mj_regmgr_format_date($end, true) : '',
                );
            }
        }
    }
    
    // Fallback: si pas d'occurrences mais event avec date, créer une occurrence unique
    if (empty($occurrences) && !empty($event->date_debut)) {
        $occurrences[] = array(
            'id' => 'single_' . $event->id,
            'start' => $event->date_debut,
            'end' => $event->date_fin ?? '',
            'date' => substr($event->date_debut, 0, 10),
            'startFormatted' => mj_regmgr_format_date($event->date_debut, true),
            'endFormatted' => $event->date_fin ? mj_regmgr_format_date($event->date_fin, true) : '',
        );
    }

    // Get location
    $location = null;
    if (!empty($event->location_id) && class_exists('Mj\Member\Classes\Crud\MjEventLocations')) {
        $loc = \Mj\Member\Classes\Crud\MjEventLocations::find($event->location_id);
        if ($loc) {
            $location = array(
                'id' => $loc->id,
                'name' => $loc->name,
                'address' => $loc->address ?? '',
            );
        }
    }

    // Get animateurs
    $animateurs = array();
    if (class_exists('Mj\Member\Classes\Crud\MjEventAnimateurs')) {
        $animateurs_list = MjEventAnimateurs::get_members_by_event($event_id);
        foreach ($animateurs_list as $member) {
            if ($member) {
                $animateurs[] = array(
                    'id' => $member->id,
                    'name' => trim(($member->first_name ?? '') . ' ' . ($member->last_name ?? '')),
                    'role' => $member->role ?? '',
                );
            }
        }
    }

    $registrations_count = MjEventRegistrations::count(array('event_id' => $event_id));

    $type_labels = MjEvents::get_type_labels();
    $status_labels = MjEvents::get_status_labels();

    // Build front URL from article_id if available
    $front_url = '';
    if (!empty($event->article_id) && $event->article_id > 0) {
        $front_url = get_permalink($event->article_id);
    }

    wp_send_json_success(array(
        'event' => array(
            'id' => $event->id,
            'title' => $event->title,
            'slug' => $event->slug,
            'type' => $event->type,
            'typeLabel' => isset($type_labels[$event->type]) ? $type_labels[$event->type] : $event->type,
            'status' => $event->status,
            'statusLabel' => isset($status_labels[$event->status]) ? $status_labels[$event->status] : $event->status,
            'description' => $event->description,
            'dateDebut' => $event->date_debut,
            'dateFin' => $event->date_fin,
            'dateDebutFormatted' => mj_regmgr_format_date($event->date_debut, true),
            'dateFinFormatted' => mj_regmgr_format_date($event->date_fin, true),
            'dateFinInscription' => $event->date_fin_inscription,
            'coverId' => $event->cover_id,
            'coverUrl' => mj_regmgr_get_event_cover_url($event, 'medium'),
            'accentColor' => $event->accent_color,
            'prix' => (float) $event->prix,
            'freeParticipation' => !empty($event->free_participation),
            'requiresValidation' => !empty($event->requires_validation),
            'allowGuardianRegistration' => !empty($event->allow_guardian_registration),
            'ageMin' => (int) ($event->age_min ?? 0),
            'ageMax' => (int) ($event->age_max ?? 99),
            'capacityTotal' => (int) ($event->capacity_total ?? 0),
            'capacityWaitlist' => (int) ($event->capacity_waitlist ?? 0),
            'registrationsCount' => $registrations_count,
            'scheduleMode' => $schedule_mode,
            'occurrences' => $occurrences,
            'location' => $location,
            'animateurs' => $animateurs,
            'frontUrl' => $front_url ?: null,
            'articleId' => !empty($event->article_id) ? (int) $event->article_id : null,
            'occurrenceSelectionMode' => isset($event->occurrence_selection_mode) && $event->occurrence_selection_mode !== ''
                ? $event->occurrence_selection_mode
                : 'member_choice',
        ),
    ));
}

/**
 * Get registrations for an event
 */
function mj_regmgr_get_registrations() {
    $auth = mj_regmgr_verify_request();
    if (!$auth) return;

    $event_id = isset($_POST['eventId']) ? (int) $_POST['eventId'] : 0;
    if ($event_id <= 0) {
        wp_send_json_error(array('message' => __('ID événement invalide.', 'mj-member')));
        return;
    }

    $registrations = MjEventRegistrations::get_by_event($event_id);
    
    $data = array();
    foreach ($registrations as $reg) {
        $member = null;
        $guardian = null;
        
        if (!empty($reg->member_id)) {
            $member = MjMembers::getById($reg->member_id);
        }
        
        if (!empty($reg->guardian_id)) {
            $guardian = MjMembers::getById($reg->guardian_id);
        }

        // Calculate age
        $age = null;
        if ($member && !empty($member->birth_date)) {
            $birth = new DateTime($member->birth_date);
            $now = new DateTime();
            $age = $now->diff($birth)->y;
        }

        // Get subscription status
        $subscription_status = 'none';
        if ($member) {
            if (!empty($member->date_last_payement)) {
                $last_payment = new DateTime($member->date_last_payement);
                $expiry = clone $last_payment;
                $expiry->modify('+1 year');
                $subscription_status = $expiry > new DateTime() ? 'active' : 'expired';
            }
        }

        // Get attendance data
        $attendance = array();
        if (!empty($reg->attendance_payload)) {
            $payload = json_decode($reg->attendance_payload, true);
            if (is_array($payload) && isset($payload['occurrences'])) {
                $attendance = $payload['occurrences'];
            }
        }

        // Get assigned occurrences
        $assigned_occurrences = array();
        if (!empty($reg->selected_occurrences)) {
            $assigned_occurrences = json_decode($reg->selected_occurrences, true);
            if (!is_array($assigned_occurrences)) {
                $assigned_occurrences = array();
            }
        }

        // Count notes for this member
        $notes_count = 0;
        if (!empty($reg->member_id)) {
            global $wpdb;
            $notes_table = $wpdb->prefix . 'mj_member_notes';
            $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $notes_table));
            if ($table_exists) {
                $notes_count = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$notes_table} WHERE member_id = %d",
                    $reg->member_id
                ));
            }
        }

        $data[] = array(
            'id' => $reg->id,
            'eventId' => $reg->event_id,
            'memberId' => $reg->member_id,
            'guardianId' => $reg->guardian_id,
            'status' => $reg->statut,
            'statusLabel' => MjEventRegistrations::META_STATUS_LABELS[$reg->statut] ?? $reg->statut,
            'paymentStatus' => $reg->payment_status ?? 'unpaid',
            'paymentMethod' => $reg->payment_method ?? '',
            'paymentRecordedAt' => $reg->payment_recorded_at ?? '',
            'notes' => $reg->notes ?? '',
            'createdAt' => $reg->created_at,
            'createdAtFormatted' => mj_regmgr_format_date($reg->created_at, true),
            'member' => $member ? array(
                'id' => $member->id,
                'firstName' => $member->first_name,
                'lastName' => $member->last_name,
                'nickname' => $member->nickname ?? '',
                'email' => $member->email ?? '',
                'phone' => $member->phone ?? '',
                'role' => $member->role,
                'roleLabel' => MjRoles::getRoleLabel($member->role),
                'photoId' => $member->photo_id ?? 0,
                'photoUrl' => !empty($member->photo_id) ? wp_get_attachment_image_url($member->photo_id, 'thumbnail') : '',
                'age' => $age,
                'birthDate' => $member->birth_date ?? '',
                'subscriptionStatus' => $subscription_status,
                'whatsappOptIn' => isset($member->whatsapp_opt_in) ? ((int) $member->whatsapp_opt_in !== 0) : true,
            ) : null,
            'guardian' => $guardian ? array(
                'id' => $guardian->id,
                'firstName' => $guardian->first_name,
                'lastName' => $guardian->last_name,
                'email' => $guardian->email ?? '',
                'phone' => $guardian->phone ?? '',
                'whatsappOptIn' => isset($guardian->whatsapp_opt_in) ? ((int) $guardian->whatsapp_opt_in !== 0) : true,
            ) : null,
            'attendance' => $attendance,
            'assignedOccurrences' => $assigned_occurrences,
            'notesCount' => $notes_count,
        );
    }

    wp_send_json_success(array(
        'registrations' => $data,
    ));
}

/**
 * Search members for adding to event
 */
function mj_regmgr_search_members() {
    $auth = mj_regmgr_verify_request();
    if (!$auth) return;

    $event_id = isset($_POST['eventId']) ? (int) $_POST['eventId'] : 0;
    $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
    $age_range = isset($_POST['ageRange']) ? sanitize_text_field($_POST['ageRange']) : '';
    $subscription_filter = isset($_POST['subscriptionFilter']) ? sanitize_key($_POST['subscriptionFilter']) : '';
    $page = isset($_POST['page']) ? max(1, (int) $_POST['page']) : 1;
    $per_page = 50;

    // Get event for age restrictions
    $event = null;
    if ($event_id > 0) {
        $event = MjEvents::find($event_id);
    }

    // Get existing registrations for this event
    $existing_member_ids = array();
    if ($event_id > 0) {
        $existing_regs = MjEventRegistrations::get_by_event($event_id);
        foreach ($existing_regs as $reg) {
            if (!empty($reg->member_id)) {
                $existing_member_ids[] = (int) $reg->member_id;
            }
        }
    }

    $filters = array();
    
    // Age range filter
    if ($age_range !== '') {
        $filters['age_range'] = $age_range;
    }

    // Subscription filter
    if ($subscription_filter !== '') {
        $filters['subscription_status'] = $subscription_filter;
    }

    $members = MjMembers::get_all(array(
        'search' => $search,
        'filters' => $filters,
        'limit' => $per_page,
        'offset' => ($page - 1) * $per_page,
        'orderby' => 'last_name',
        'order' => 'ASC',
    ));

    $data = array();
    $now = new DateTime();

    foreach ($members as $member) {
        // Calculate age
        $age = null;
        if (!empty($member->birth_date)) {
            $birth = new DateTime($member->birth_date);
            $age = $now->diff($birth)->y;
        }

        // Check age restrictions
        $age_restriction = null;
        if ($event && $age !== null) {
            $age_min = (int) ($event->age_min ?? 0);
            $age_max = (int) ($event->age_max ?? 99);
            
            if ($age < $age_min) {
                $age_restriction = sprintf(__('Âge inférieur au minimum (%d ans).', 'mj-member'), $age_min);
            } elseif ($age > $age_max) {
                $age_restriction = sprintf(__('Âge supérieur au maximum (%d ans).', 'mj-member'), $age_max);
            }
        }

        // Check if tutor role allowed
        $role_restriction = null;
        if ($event && $member->role === MjRoles::TUTEUR && empty($event->allow_guardian_registration)) {
            $role_restriction = __('Rôle tuteur non autorisé pour cet événement.', 'mj-member');
        }

        // Check subscription status
        $subscription_status = 'none';
        if (!empty($member->date_last_payement)) {
            $last_payment = new DateTime($member->date_last_payement);
            $expiry = clone $last_payment;
            $expiry->modify('+1 year');
            $subscription_status = $expiry > $now ? 'active' : 'expired';
        }

        // Check if already registered
        $already_registered = in_array($member->id, $existing_member_ids, true);

        $data[] = array(
            'id' => $member->id,
            'firstName' => $member->first_name,
            'lastName' => $member->last_name,
            'nickname' => $member->nickname ?? '',
            'email' => $member->email ?? '',
            'role' => $member->role,
            'roleLabel' => MjRoles::getRoleLabel($member->role),
            'photoId' => $member->photo_id ?? 0,
            'photoUrl' => !empty($member->photo_id) ? wp_get_attachment_image_url($member->photo_id, 'thumbnail') : '',
            'age' => $age,
            'subscriptionStatus' => $subscription_status,
            'alreadyRegistered' => $already_registered,
            'ageRestriction' => $age_restriction,
            'roleRestriction' => $role_restriction,
        );
    }

    $total = MjMembers::count(array(
        'search' => $search,
        'filters' => $filters,
    ));

    wp_send_json_success(array(
        'members' => $data,
        'pagination' => array(
            'total' => $total,
            'page' => $page,
            'perPage' => $per_page,
            'totalPages' => ceil($total / $per_page),
        ),
    ));
}

/**
 * Add registration(s) to event
 */
function mj_regmgr_add_registration() {
    $auth = mj_regmgr_verify_request();
    if (!$auth) return;

    $event_id = isset($_POST['eventId']) ? (int) $_POST['eventId'] : 0;
    $member_ids = isset($_POST['memberIds']) ? array_map('intval', (array) $_POST['memberIds']) : array();
    $occurrences = isset($_POST['occurrences']) ? (array) $_POST['occurrences'] : array();

    if ($event_id <= 0) {
        wp_send_json_error(array('message' => __('ID événement invalide.', 'mj-member')));
        return;
    }

    if (empty($member_ids)) {
        wp_send_json_error(array('message' => __('Aucun membre sélectionné.', 'mj-member')));
        return;
    }

    $event = MjEvents::find($event_id);
    if (!$event) {
        wp_send_json_error(array('message' => __('Événement introuvable.', 'mj-member')));
        return;
    }

    $added = 0;
    $errors = array();

    foreach ($member_ids as $member_id) {
        // Check if already registered
        $existing = MjEventRegistrations::get_all(array(
            'event_id' => $event_id,
            'member_id' => $member_id,
        ));

        if (!empty($existing)) {
            $member = MjMembers::getById($member_id);
            $name = $member ? trim($member->first_name . ' ' . $member->last_name) : "ID $member_id";
            $errors[] = sprintf(__('%s est déjà inscrit.', 'mj-member'), $name);
            continue;
        }

        $data = array(
            'event_id' => $event_id,
            'member_id' => $member_id,
            'statut' => $event->requires_validation ? MjEventRegistrations::STATUS_PENDING : MjEventRegistrations::STATUS_CONFIRMED,
            'payment_status' => ((float) $event->prix > 0 && empty($event->free_participation)) ? 'unpaid' : 'paid',
        );

        if (!empty($occurrences)) {
            $data['selected_occurrences'] = wp_json_encode($occurrences);
        }

        $result = MjEventRegistrations::create($data);

        if (is_wp_error($result)) {
            $member = MjMembers::getById($member_id);
            $name = $member ? trim($member->first_name . ' ' . $member->last_name) : "ID $member_id";
            $errors[] = sprintf(__('Erreur pour %s: %s', 'mj-member'), $name, $result->get_error_message());
        } else {
            $added++;
        }
    }

    if ($added === 0 && !empty($errors)) {
        wp_send_json_error(array('message' => implode("\n", $errors)));
        return;
    }

    $message = sprintf(_n('%d inscription ajoutée.', '%d inscriptions ajoutées.', $added, 'mj-member'), $added);
    if (!empty($errors)) {
        $message .= "\n" . implode("\n", $errors);
    }

    wp_send_json_success(array(
        'message' => $message,
        'added' => $added,
    ));
}

/**
 * Update registration status
 */
function mj_regmgr_update_registration() {
    $auth = mj_regmgr_verify_request();
    if (!$auth) return;

    $registration_id = isset($_POST['registrationId']) ? (int) $_POST['registrationId'] : 0;
    $status = isset($_POST['status']) ? sanitize_key($_POST['status']) : '';

    if ($registration_id <= 0) {
        wp_send_json_error(array('message' => __('ID inscription invalide.', 'mj-member')));
        return;
    }

    $valid_statuses = array_keys(MjEventRegistrations::META_STATUS_LABELS);
    if (!in_array($status, $valid_statuses, true)) {
        wp_send_json_error(array('message' => __('Statut invalide.', 'mj-member')));
        return;
    }

    $result = MjEventRegistrations::update($registration_id, array('statut' => $status));

    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()));
        return;
    }

    wp_send_json_success(array(
        'message' => __('Inscription mise à jour.', 'mj-member'),
    ));
}

/**
 * Delete registration
 */
function mj_regmgr_delete_registration() {
    $auth = mj_regmgr_verify_request();
    if (!$auth) return;

    $registration_id = isset($_POST['registrationId']) ? (int) $_POST['registrationId'] : 0;

    if ($registration_id <= 0) {
        wp_send_json_error(array('message' => __('ID inscription invalide.', 'mj-member')));
        return;
    }

    $result = MjEventRegistrations::delete($registration_id);

    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()));
        return;
    }

    wp_send_json_success(array(
        'message' => __('Inscription supprimée.', 'mj-member'),
    ));
}

/**
 * Update attendance for a single registration/occurrence
 */
function mj_regmgr_update_attendance() {
    $auth = mj_regmgr_verify_request();
    if (!$auth) return;

    $event_id = isset($_POST['eventId']) ? (int) $_POST['eventId'] : 0;
    $member_id = isset($_POST['memberId']) ? (int) $_POST['memberId'] : 0;
    $occurrence = isset($_POST['occurrence']) ? sanitize_text_field($_POST['occurrence']) : '';
    $status = isset($_POST['status']) ? sanitize_key($_POST['status']) : '';

    if ($event_id <= 0 || $member_id <= 0) {
        wp_send_json_error(array('message' => __('Paramètres invalides.', 'mj-member')));
        return;
    }

    $valid_statuses = array(
        MjEventAttendance::STATUS_PRESENT,
        MjEventAttendance::STATUS_ABSENT,
        MjEventAttendance::STATUS_PENDING,
        '', // Allow clearing
    );

    if (!in_array($status, $valid_statuses, true)) {
        wp_send_json_error(array('message' => __('Statut invalide.', 'mj-member')));
        return;
    }

    $result = MjEventAttendance::record($event_id, $member_id, $occurrence, $status, array(
        'recorded_by' => $auth['member_id'],
    ));

    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()));
        return;
    }

    wp_send_json_success(array(
        'message' => __('Présence mise à jour.', 'mj-member'),
    ));
}

/**
 * Bulk update attendance for multiple members
 */
function mj_regmgr_bulk_attendance() {
    $auth = mj_regmgr_verify_request();
    if (!$auth) return;

    $event_id = isset($_POST['eventId']) ? (int) $_POST['eventId'] : 0;
    $occurrence = isset($_POST['occurrence']) ? sanitize_text_field($_POST['occurrence']) : '';
    
    // Handle updates - may come as JSON string or array
    $updates_raw = isset($_POST['updates']) ? $_POST['updates'] : array();
    if (is_string($updates_raw)) {
        $updates = json_decode(stripslashes($updates_raw), true);
        if (!is_array($updates)) {
            $updates = array();
        }
    } else {
        $updates = (array) $updates_raw;
    }

    if ($event_id <= 0) {
        wp_send_json_error(array('message' => __('ID événement invalide.', 'mj-member')));
        return;
    }

    if (empty($updates)) {
        wp_send_json_error(array('message' => __('Aucune mise à jour fournie.', 'mj-member')));
        return;
    }

    $success = 0;
    $errors = 0;

    foreach ($updates as $update) {
        $member_id = isset($update['memberId']) ? (int) $update['memberId'] : 0;
        $status = isset($update['status']) ? sanitize_key($update['status']) : '';

        if ($member_id <= 0) {
            $errors++;
            continue;
        }

        $result = MjEventAttendance::record($event_id, $member_id, $occurrence, $status, array(
            'recorded_by' => $auth['member_id'],
        ));

        if (is_wp_error($result)) {
            $errors++;
        } else {
            $success++;
        }
    }

    if ($success === 0 && $errors > 0) {
        wp_send_json_error(array('message' => __('Échec de la mise à jour des présences.', 'mj-member')));
        return;
    }

    wp_send_json_success(array(
        'message' => sprintf(__('%d présence(s) mise(s) à jour.', 'mj-member'), $success),
        'success' => $success,
        'errors' => $errors,
    ));
}

/**
 * Validate payment manually
 */
function mj_regmgr_validate_payment() {
    $auth = mj_regmgr_verify_request();
    if (!$auth) return;

    $registration_id = isset($_POST['registrationId']) ? (int) $_POST['registrationId'] : 0;
    $payment_method = isset($_POST['paymentMethod']) ? sanitize_text_field($_POST['paymentMethod']) : 'manual';

    if ($registration_id <= 0) {
        wp_send_json_error(array('message' => __('ID inscription invalide.', 'mj-member')));
        return;
    }

    $result = MjEventRegistrations::update($registration_id, array(
        'status' => 'valide',
        'payment_status' => 'paid',
        'payment_method' => $payment_method,
        'payment_recorded_at' => current_time('mysql'),
        'payment_recorded_by' => $auth['member_id'],
    ));

    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()));
        return;
    }

    wp_send_json_success(array(
        'message' => __('Paiement validé.', 'mj-member'),
    ));
}

/**
 * Cancel payment (set back to unpaid)
 */
function mj_regmgr_cancel_payment() {
    $auth = mj_regmgr_verify_request();
    if (!$auth) return;

    $registration_id = isset($_POST['registrationId']) ? (int) $_POST['registrationId'] : 0;

    if ($registration_id <= 0) {
        wp_send_json_error(array('message' => __('ID inscription invalide.', 'mj-member')));
        return;
    }

    $result = MjEventRegistrations::update($registration_id, array(
        'payment_status' => 'unpaid',
        'payment_method' => null,
        'payment_recorded_at' => null,
        'payment_recorded_by' => null,
    ));

    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()));
        return;
    }

    wp_send_json_success(array(
        'message' => __('Paiement annulé.', 'mj-member'),
    ));
}

/**
 * Create a quick member (minimal data)
 */
function mj_regmgr_create_quick_member() {
    $auth = mj_regmgr_verify_request();
    if (!$auth) return;

    $first_name = isset($_POST['firstName']) ? sanitize_text_field($_POST['firstName']) : '';
    $last_name = isset($_POST['lastName']) ? sanitize_text_field($_POST['lastName']) : '';
    $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
    $role = isset($_POST['role']) ? sanitize_key($_POST['role']) : MjRoles::JEUNE;
    $birth_date = isset($_POST['birthDate']) ? sanitize_text_field($_POST['birthDate']) : '';

    if ($first_name === '' || $last_name === '') {
        wp_send_json_error(array('message' => __('Prénom et nom sont requis.', 'mj-member')));
        return;
    }

    $data = array(
        'first_name' => $first_name,
        'last_name' => $last_name,
        'role' => $role,
        'status' => MjMembers::STATUS_ACTIVE,
    );

    // Ajouter la date de naissance si fournie
    if ($birth_date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $birth_date)) {
        $data['birth_date'] = $birth_date;
    }

    if ($email !== '') {
        // Check if email already exists
        $existing = MjMembers::get_by_email($email);
        if ($existing) {
            wp_send_json_error(array('message' => __('Un membre avec cet email existe déjà.', 'mj-member')));
            return;
        }
        $data['email'] = $email;
    }

    $result = MjMembers::create($data);

    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()));
        return;
    }

    $member = MjMembers::getById($result);

    wp_send_json_success(array(
        'message' => __('Membre créé avec succès.', 'mj-member'),
        'member' => array(
            'id' => $member->id,
            'firstName' => $member->first_name,
            'lastName' => $member->last_name,
            'email' => $member->email ?? '',
            'role' => $member->role,
            'roleLabel' => MjRoles::getRoleLabel($member->role),
        ),
    ));
}

/**
 * Get member notes
 */
function mj_regmgr_get_member_notes() {
    $auth = mj_regmgr_verify_request();
    if (!$auth) return;

    $member_id = isset($_POST['memberId']) ? (int) $_POST['memberId'] : 0;

    if ($member_id <= 0) {
        wp_send_json_error(array('message' => __('ID membre invalide.', 'mj-member')));
        return;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'mj_member_notes';
    $members_table = $wpdb->prefix . 'mj_members';

    // Check if table exists
    $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
    if (!$table_exists) {
        // Return empty if table doesn't exist yet
        wp_send_json_success(array('notes' => array()));
        return;
    }

    $notes = $wpdb->get_results($wpdb->prepare(
        "SELECT n.*, m.first_name AS author_first_name, m.last_name AS author_last_name 
         FROM {$table} n
         LEFT JOIN {$members_table} m ON n.author_id = m.id
         WHERE n.member_id = %d
         ORDER BY n.created_at DESC",
        $member_id
    ));

    $data = array();
    foreach ($notes as $note) {
        $data[] = array(
            'id' => $note->id,
            'content' => $note->content,
            'authorId' => $note->author_id,
            'authorName' => trim(($note->author_first_name ?? '') . ' ' . ($note->author_last_name ?? '')),
            'createdAt' => $note->created_at,
            'createdAtFormatted' => mj_regmgr_format_date($note->created_at, true),
            'canEdit' => (int) $note->author_id === $auth['member_id'] || $auth['is_coordinateur'],
        );
    }

    wp_send_json_success(array('notes' => $data));
}

/**
 * Save member note
 */
function mj_regmgr_save_member_note() {
    $auth = mj_regmgr_verify_request();
    if (!$auth) return;

    $member_id = isset($_POST['memberId']) ? (int) $_POST['memberId'] : 0;
    $note_id = isset($_POST['noteId']) ? (int) $_POST['noteId'] : 0;
    $content = isset($_POST['content']) ? sanitize_textarea_field($_POST['content']) : '';

    if ($member_id <= 0) {
        wp_send_json_error(array('message' => __('ID membre invalide.', 'mj-member')));
        return;
    }

    if ($content === '') {
        wp_send_json_error(array('message' => __('Le contenu de la note est requis.', 'mj-member')));
        return;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'mj_member_notes';

    // Ensure table exists
    mj_regmgr_ensure_notes_table();

    if ($note_id > 0) {
        // Update existing note
        $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $note_id));
        if (!$existing) {
            wp_send_json_error(array('message' => __('Note introuvable.', 'mj-member')));
            return;
        }

        // Check permission
        if ((int) $existing->author_id !== $auth['member_id'] && !$auth['is_coordinateur']) {
            wp_send_json_error(array('message' => __('Vous ne pouvez modifier que vos propres notes.', 'mj-member')));
            return;
        }

        $wpdb->update(
            $table,
            array('content' => $content, 'updated_at' => current_time('mysql')),
            array('id' => $note_id),
            array('%s', '%s'),
            array('%d')
        );

        $message = __('Note mise à jour.', 'mj-member');
    } else {
        // Create new note
        $wpdb->insert(
            $table,
            array(
                'member_id' => $member_id,
                'author_id' => $auth['member_id'],
                'content' => $content,
                'created_at' => current_time('mysql'),
            ),
            array('%d', '%d', '%s', '%s')
        );

        $note_id = $wpdb->insert_id;
        $message = __('Note ajoutée.', 'mj-member');
    }

    wp_send_json_success(array(
        'message' => $message,
        'noteId' => $note_id,
    ));
}

/**
 * Delete member note
 */
function mj_regmgr_delete_member_note() {
    $auth = mj_regmgr_verify_request();
    if (!$auth) return;

    $note_id = isset($_POST['noteId']) ? (int) $_POST['noteId'] : 0;

    if ($note_id <= 0) {
        wp_send_json_error(array('message' => __('ID note invalide.', 'mj-member')));
        return;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'mj_member_notes';

    $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $note_id));
    if (!$existing) {
        wp_send_json_error(array('message' => __('Note introuvable.', 'mj-member')));
        return;
    }

    // Check permission
    if ((int) $existing->author_id !== $auth['member_id'] && !$auth['is_coordinateur']) {
        wp_send_json_error(array('message' => __('Vous ne pouvez supprimer que vos propres notes.', 'mj-member')));
        return;
    }

    $wpdb->delete($table, array('id' => $note_id), array('%d'));

    wp_send_json_success(array(
        'message' => __('Note supprimée.', 'mj-member'),
    ));
}

/**
 * Get payment QR code URL
 */
function mj_regmgr_get_payment_qr() {
    $auth = mj_regmgr_verify_request();
    if (!$auth) return;

    $registration_id = isset($_POST['registrationId']) ? (int) $_POST['registrationId'] : 0;

    if ($registration_id <= 0) {
        wp_send_json_error(array('message' => __('ID inscription invalide.', 'mj-member')));
        return;
    }

    $registration = MjEventRegistrations::get($registration_id);
    if (!$registration) {
        wp_send_json_error(array('message' => __('Inscription introuvable.', 'mj-member')));
        return;
    }

    $event = MjEvents::find($registration->event_id);
    if (!$event) {
        wp_send_json_error(array('message' => __('Événement introuvable.', 'mj-member')));
        return;
    }

    $amount = (float) $event->prix;
    
    // Check if there's already a pending Stripe payment for this registration
    $existing_payment = MjPayments::get_pending_payment_for_registration($registration_id);
    
    if ($existing_payment && !empty($existing_payment->checkout_url)) {
        // Use existing Stripe checkout URL
        $checkout_url = $existing_payment->checkout_url;
        $qr_url = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . rawurlencode($checkout_url);
        
        wp_send_json_success(array(
            'qrUrl' => $qr_url,
            'paymentUrl' => $checkout_url,
            'amount' => $amount,
            'eventTitle' => $event->title,
        ));
        return;
    }
    
    // Create a new Stripe payment session
    if (class_exists(MjPayments::class) && class_exists('MjStripeConfig') && \MjStripeConfig::is_configured()) {
        $payment_result = MjPayments::create_stripe_payment(
            $registration->member_id,
            $amount,
            array(
                'context' => 'event',
                'event_id' => $event->id,
                'registration_id' => $registration_id,
                'event' => $event,
            )
        );
        
        if ($payment_result && !empty($payment_result['checkout_url'])) {
            wp_send_json_success(array(
                'qrUrl' => $payment_result['qr_url'] ?? ('https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . rawurlencode($payment_result['checkout_url'])),
                'paymentUrl' => $payment_result['checkout_url'],
                'amount' => $amount,
                'eventTitle' => $event->title,
            ));
            return;
        }
    }
    
    // Fallback: return error if Stripe is not configured
    wp_send_json_error(array(
        'message' => __('Le paiement Stripe n\'est pas configuré. Veuillez contacter l\'administrateur.', 'mj-member'),
    ));
}

/**
 * Update selected occurrences for a registration
 */
function mj_regmgr_update_occurrences() {
    $auth = mj_regmgr_verify_request();
    if (!$auth) return;

    $registration_id = isset($_POST['registrationId']) ? (int) $_POST['registrationId'] : 0;
    $occurrences = isset($_POST['occurrences']) ? (array) $_POST['occurrences'] : array();

    if ($registration_id <= 0) {
        wp_send_json_error(array('message' => __('ID inscription invalide.', 'mj-member')));
        return;
    }

    // Sanitize occurrences
    $sanitized = array();
    foreach ($occurrences as $occ) {
        $sanitized[] = sanitize_text_field($occ);
    }

    $result = MjEventRegistrations::update($registration_id, array(
        'selected_occurrences' => wp_json_encode($sanitized),
    ));

    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()));
        return;
    }

    wp_send_json_success(array(
        'message' => __('Séances mises à jour.', 'mj-member'),
    ));
}

/**
 * Helper: Format date
 */
function mj_regmgr_format_date($date, $with_time = false) {
    if (empty($date)) {
        return '';
    }

    $format = $with_time ? 'd/m/Y H:i' : 'd/m/Y';
    $timestamp = strtotime($date);

    if (!$timestamp) {
        return $date;
    }

    return date_i18n($format, $timestamp);
}

/**
 * Helper: Get event cover URL
 * Falls back to linked article's featured image if no direct cover
 */
function mj_regmgr_get_event_cover_url($event, $size = 'thumbnail') {
    // First try direct cover_id
    if (!empty($event->cover_id) && $event->cover_id > 0) {
        $url = wp_get_attachment_image_url($event->cover_id, $size);
        if ($url) {
            return $url;
        }
    }
    
    // Fallback to linked article's featured image
    if (!empty($event->article_id) && $event->article_id > 0) {
        $thumbnail_id = get_post_thumbnail_id($event->article_id);
        if ($thumbnail_id) {
            $url = wp_get_attachment_image_url($thumbnail_id, $size);
            if ($url) {
                return $url;
            }
        }
    }
    
    return '';
}

/**
 * Ensure member notes table exists
 */
function mj_regmgr_ensure_notes_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'mj_member_notes';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS {$table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        member_id bigint(20) unsigned NOT NULL,
        author_id bigint(20) unsigned NOT NULL,
        content text NOT NULL,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT NULL,
        PRIMARY KEY (id),
        KEY member_id (member_id),
        KEY author_id (author_id)
    ) {$charset_collate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

/**
 * Get members list with filtering and pagination
 */
function mj_regmgr_get_members() {
    $current_member = mj_regmgr_verify_request();
    if (!$current_member) return;

    $filter = isset($_POST['filter']) ? sanitize_text_field($_POST['filter']) : 'all';
    $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
    $page = isset($_POST['page']) ? absint($_POST['page']) : 1;
    $per_page = isset($_POST['perPage']) ? absint($_POST['perPage']) : 20;

    // Build filters array
    $filters = array();
    if ($filter !== 'all' && in_array($filter, array('jeune', 'animateur', 'tuteur', 'benevole', 'coordinateur'))) {
        $filters['role'] = $filter;
    }

    // Get total count
    $total = MjMembers::count(array(
        'search' => $search,
        'filters' => $filters,
    ));

    // Get members
    $offset = ($page - 1) * $per_page;
    $member_objects = MjMembers::get_all(array(
        'limit' => $per_page,
        'offset' => $offset,
        'orderby' => 'last_name',
        'order' => 'ASC',
        'search' => $search,
        'filters' => $filters,
    ));

    $current_year = (int) date('Y');
    $members = array();
    foreach ($member_objects as $member) {
        // Check if member requires payment
        $requires_payment = !empty($member->requires_payment) ? true : false;
        
        // Check membership status
        $membership_year = !empty($member->membership_paid_year) ? (int) $member->membership_paid_year : 0;
        $membership_status = 'not_required'; // Default: no payment needed
        
        if ($requires_payment) {
            if ($membership_year >= $current_year) {
                $membership_status = 'paid';
            } elseif ($membership_year > 0) {
                $membership_status = 'expired';
            } else {
                $membership_status = 'unpaid';
            }
        }

        $members[] = array(
            'id' => (int) $member->id,
            'firstName' => $member->first_name ?? '',
            'lastName' => $member->last_name ?? '',
            'email' => $member->email ?? '',
            'phone' => $member->phone ?? '',
            'role' => $member->role ?? 'jeune',
            'birthDate' => $member->birth_date ?? null,
            'avatarUrl' => mj_regmgr_get_member_avatar_url((int) $member->id),
            'requiresPayment' => $requires_payment,
            'membershipStatus' => $membership_status,
            'membershipYear' => $membership_year > 0 ? $membership_year : null,
        );
    }

    wp_send_json_success(array(
        'members' => $members,
        'pagination' => array(
            'page' => $page,
            'totalPages' => $total > 0 ? ceil($total / $per_page) : 1,
            'total' => $total,
        ),
    ));
}

/**
 * Get member details
 */
function mj_regmgr_get_member_details() {
    $current_member = mj_regmgr_verify_request();
    if (!$current_member) return;

    $member_id = isset($_POST['memberId']) ? absint($_POST['memberId']) : 0;
    if (!$member_id) {
        wp_send_json_error(array('message' => __('ID du membre manquant.', 'mj-member')));
        return;
    }

    $memberData = MjMembers::getById($member_id);

    if (!$memberData) {
        wp_send_json_error(array('message' => __('Membre non trouvé.', 'mj-member')));
        return;
    }

    // Build address string
    $address = '';
    $address_parts = array_filter(array(
        $memberData->address ?? '',
        $memberData->postal_code ?? '',
        $memberData->city ?? '',
    ));
    if (!empty($address_parts)) {
        $address = implode(' ', $address_parts);
    }

    // Check if member requires payment
    $current_year = (int) date('Y');
    $requires_payment = !empty($memberData->requires_payment) ? true : false;
    $membership_year = !empty($memberData->membership_paid_year) ? (int) $memberData->membership_paid_year : 0;
    $membership_status = 'not_required';
    
    if ($requires_payment) {
        if ($membership_year >= $current_year) {
            $membership_status = 'paid';
        } elseif ($membership_year > 0) {
            $membership_status = 'expired';
        } else {
            $membership_status = 'unpaid';
        }
    }

    // Get role label
    $role_labels = array(
        'jeune' => __('Jeune', 'mj-member'),
        'animateur' => __('Animateur', 'mj-member'),
        'tuteur' => __('Tuteur', 'mj-member'),
        'benevole' => __('Bénévole', 'mj-member'),
        'coordinateur' => __('Coordinateur', 'mj-member'),
    );
    $role = $memberData->role ?? 'jeune';

    $member = array(
        'id' => (int) $memberData->id,
        'firstName' => $memberData->first_name ?? '',
        'lastName' => $memberData->last_name ?? '',
        'nickname' => $memberData->nickname ?? '',
        'email' => $memberData->email ?? '',
        'phone' => $memberData->phone ?? '',
        'role' => $role,
        'roleLabel' => $role_labels[$role] ?? ucfirst($role),
        'birthDate' => $memberData->birth_date ?? null,
        'address' => $address,
        'addressLine' => $memberData->address ?? '',
        'city' => $memberData->city ?? '',
        'postalCode' => $memberData->postal_code ?? '',
        'avatarUrl' => mj_regmgr_get_member_avatar_url((int) $memberData->id),
        'userId' => $memberData->wp_user_id ?? null,
        'createdAt' => $memberData->created_at ?? null,
        'dateInscription' => $memberData->date_inscription ?? null,
        'status' => $memberData->status ?? 'active',
        // Cotisation
        'requiresPayment' => $requires_payment,
        'membershipStatus' => $membership_status,
        'membershipYear' => $membership_year > 0 ? $membership_year : null,
        'membershipNumber' => $memberData->membership_number ?? null,
        // Autres infos
        'isAutonomous' => !empty($memberData->is_autonomous),
        'isVolunteer' => !empty($memberData->is_volunteer),
        'guardianId' => $memberData->guardian_id ?? null,
        'descriptionShort' => $memberData->description_courte ?? '',
        'descriptionLong' => $memberData->description_longue ?? '',
        'newsletterOptIn' => isset($memberData->newsletter_opt_in) ? !empty($memberData->newsletter_opt_in) : true,
        'smsOptIn' => isset($memberData->sms_opt_in) ? !empty($memberData->sms_opt_in) : true,
        'whatsappOptIn' => isset($memberData->whatsapp_opt_in) ? !empty($memberData->whatsapp_opt_in) : true,
        'photoUsageConsent' => (bool) $memberData->get('photo_usage_consent', 0),
        'photoId' => $memberData->get('photo_id', null),
    );

    // Add guardian info if exists
    if (!empty($memberData->guardian_id)) {
        $guardian = MjMembers::getById((int) $memberData->guardian_id);
        if ($guardian) {
            $member['guardianName'] = trim(($guardian->first_name ?? '') . ' ' . ($guardian->last_name ?? ''));
        }
    }

    // Add children info if member is a guardian (tuteur)
    $children = MjMembers::getChildrenForGuardian($member_id);
    if (!empty($children)) {
        $member['children'] = array();
        foreach ($children as $child) {
            // Get child membership status
            $child_requires_payment = !empty($child->requires_payment) ? true : false;
            $child_membership_year = !empty($child->membership_paid_year) ? (int) $child->membership_paid_year : 0;
            $child_membership_status = 'not_required';
            
            if ($child_requires_payment) {
                if ($child_membership_year >= $current_year) {
                    $child_membership_status = 'paid';
                } elseif ($child_membership_year > 0) {
                    $child_membership_status = 'expired';
                } else {
                    $child_membership_status = 'unpaid';
                }
            }

            $child_role = $child->role ?? 'jeune';

            $member['children'][] = array(
                'id' => (int) $child->id,
                'firstName' => $child->first_name ?? '',
                'lastName' => $child->last_name ?? '',
                'birthDate' => $child->birth_date ?? null,
                'role' => $child_role,
                'roleLabel' => $role_labels[$child_role] ?? ucfirst($child_role),
                'avatarUrl' => mj_regmgr_get_member_avatar_url((int) $child->id),
                'requiresPayment' => $child_requires_payment,
                'membershipStatus' => $child_membership_status,
                'membershipYear' => $child_membership_year > 0 ? $child_membership_year : null,
            );
        }
    }

    // Collect latest approved event photos for this member
    $photo_status_labels = MjEventPhotos::get_status_labels();

    $photos = MjEventPhotos::query(array(
        'member_id' => $member_id,
        'status' => MjEventPhotos::STATUS_APPROVED,
        'per_page' => 12,
        'paged' => 1,
        'orderby' => 'created_at',
        'order' => 'DESC',
    ));

    if (!empty($photos)) {
        $member['photos'] = array();
        foreach ($photos as $photo) {
            $attachment_id = isset($photo->attachment_id) ? (int) $photo->attachment_id : 0;
            if ($attachment_id <= 0) {
                continue;
            }

            $thumbnail = wp_get_attachment_image_url($attachment_id, 'medium');
            if (!$thumbnail) {
                $thumbnail = wp_get_attachment_url($attachment_id);
            }

            $full = wp_get_attachment_image_url($attachment_id, 'large');
            if (!$full) {
                $full = wp_get_attachment_url($attachment_id);
            }

            $eventTitle = '';
            if (!empty($photo->event_id)) {
                $event = MjEvents::find((int) $photo->event_id);
                if ($event) {
                    $eventTitle = $event->title ?? '';
                }
            }

            $status_key = isset($photo->status) ? (string) $photo->status : MjEventPhotos::STATUS_APPROVED;

            $member['photos'][] = array(
                'id' => (int) $photo->id,
                'eventId' => isset($photo->event_id) ? (int) $photo->event_id : 0,
                'eventTitle' => $eventTitle,
                'caption' => isset($photo->caption) ? (string) $photo->caption : '',
                'thumbnailUrl' => $thumbnail ?: '',
                'fullUrl' => $full ?: $thumbnail ?: '',
                'createdAt' => isset($photo->created_at) ? (string) $photo->created_at : '',
                'status' => $status_key,
                'statusLabel' => isset($photo_status_labels[$status_key]) ? (string) $photo_status_labels[$status_key] : $status_key,
            );
        }
    }

    // Fetch idea suggestions created by the member
    $ideas = MjIdeas::get_all(array(
        'member_id' => $member_id,
        'statuses' => array(MjIdeas::STATUS_PUBLISHED, MjIdeas::STATUS_ARCHIVED),
        'limit' => 25,
        'orderby' => 'created_at',
        'order' => 'DESC',
    ));

    if (!empty($ideas)) {
        $member['ideas'] = array();
        foreach ($ideas as $idea) {
            $member['ideas'][] = array(
                'id' => isset($idea['id']) ? (int) $idea['id'] : 0,
                'title' => isset($idea['title']) ? (string) $idea['title'] : '',
                'content' => isset($idea['content']) ? (string) $idea['content'] : '',
                'status' => isset($idea['status']) ? (string) $idea['status'] : '',
                'voteCount' => isset($idea['vote_count']) ? (int) $idea['vote_count'] : 0,
                'createdAt' => isset($idea['created_at']) ? (string) $idea['created_at'] : '',
            );
        }
    }

    // Retrieve latest contact messages linked to the member
    $messages = MjContactMessages::get_all(array(
        'member_id' => $member_id,
        'per_page' => 20,
        'paged' => 1,
        'orderby' => 'created_at',
        'order' => 'DESC',
    ));

    if (!empty($messages)) {
        $member['messages'] = array();
        foreach ($messages as $message) {
            $activity_log = array();
            if (!empty($message->activity_log)) {
                $decoded = json_decode($message->activity_log, true);
                if (is_array($decoded)) {
                    $activity_log = $decoded;
                }
            }

            $member['messages'][] = array(
                'id' => (int) $message->id,
                'senderName' => isset($message->sender_name) ? (string) $message->sender_name : '',
                'senderEmail' => isset($message->sender_email) ? (string) $message->sender_email : '',
                'subject' => isset($message->subject) ? (string) $message->subject : '',
                'message' => isset($message->message) ? wp_kses_post($message->message) : '',
                'status' => isset($message->status) ? (string) $message->status : '',
                'isRead' => !empty($message->is_read),
                'assignedTo' => isset($message->assigned_to) ? (int) $message->assigned_to : 0,
                'targetType' => isset($message->target_type) ? (string) $message->target_type : '',
                'createdAt' => isset($message->created_at) ? (string) $message->created_at : '',
                'activityLog' => $activity_log,
            );
        }
    }

    wp_send_json_success(array('member' => $member));
}

/**
 * Update member information
 */
function mj_regmgr_update_member() {
    $current_member = mj_regmgr_verify_request();
    if (!$current_member) return;

    $member_id = isset($_POST['memberId']) ? absint($_POST['memberId']) : 0;
    if (!$member_id) {
        wp_send_json_error(array('message' => __('ID du membre manquant.', 'mj-member')));
        return;
    }

    // Parse data
    $data = isset($_POST['data']) ? $_POST['data'] : array();
    if (is_string($data)) {
        $data = json_decode(stripslashes($data), true);
    }

    if (empty($data)) {
        wp_send_json_error(array('message' => __('Données manquantes.', 'mj-member')));
        return;
    }

    // Build update data with correct column names
    $update_data = array();

    if (isset($data['firstName'])) {
        $update_data['first_name'] = sanitize_text_field($data['firstName']);
    }
    if (isset($data['lastName'])) {
        $update_data['last_name'] = sanitize_text_field($data['lastName']);
    }
    if (isset($data['email'])) {
        $update_data['email'] = sanitize_email($data['email']);
    }
    if (isset($data['phone'])) {
        $update_data['phone'] = sanitize_text_field($data['phone']);
    }
    if (isset($data['birthDate'])) {
        $update_data['birth_date'] = sanitize_text_field($data['birthDate']);
    }
    if (array_key_exists('nickname', $data)) {
        $update_data['nickname'] = sanitize_text_field($data['nickname']);
    }
    if (array_key_exists('addressLine', $data)) {
        $update_data['address'] = sanitize_text_field($data['addressLine']);
    }
    if (array_key_exists('city', $data)) {
        $update_data['city'] = sanitize_text_field($data['city']);
    }
    if (array_key_exists('postalCode', $data)) {
        $update_data['postal_code'] = sanitize_text_field($data['postalCode']);
    }
    if (array_key_exists('isVolunteer', $data)) {
        $update_data['is_volunteer'] = mj_regmgr_to_bool($data['isVolunteer']) ? 1 : 0;
    }
    if (array_key_exists('isAutonomous', $data)) {
        $update_data['is_autonomous'] = mj_regmgr_to_bool($data['isAutonomous']) ? 1 : 0;
    }
    if (array_key_exists('descriptionShort', $data)) {
        $update_data['description_courte'] = wp_kses_post($data['descriptionShort']);
    }
    if (array_key_exists('descriptionLong', $data)) {
        $update_data['description_longue'] = wp_kses_post($data['descriptionLong']);
    }
    if (array_key_exists('newsletterOptIn', $data)) {
        $update_data['newsletter_opt_in'] = mj_regmgr_to_bool($data['newsletterOptIn'], true) ? 1 : 0;
    }
    if (array_key_exists('smsOptIn', $data)) {
        $update_data['sms_opt_in'] = mj_regmgr_to_bool($data['smsOptIn'], true) ? 1 : 0;
    }
    if (array_key_exists('whatsappOptIn', $data)) {
        $update_data['whatsapp_opt_in'] = mj_regmgr_to_bool($data['whatsappOptIn'], true) ? 1 : 0;
    }
    if (array_key_exists('photoUsageConsent', $data)) {
        $update_data['photo_usage_consent'] = mj_regmgr_to_bool($data['photoUsageConsent']) ? 1 : 0;
    }

    if (empty($update_data)) {
        wp_send_json_error(array('message' => __('Aucune donnée à mettre à jour.', 'mj-member')));
        return;
    }

    $result = MjMembers::update($member_id, $update_data);

    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()));
        return;
    }

    wp_send_json_success(array(
        'message' => __('Membre mis à jour avec succès.', 'mj-member'),
    ));
}

/**
 * Update a member idea
 */
function mj_regmgr_update_member_idea() {
    $auth = mj_regmgr_verify_request();
    if (!$auth) {
        return;
    }

    $idea_id = isset($_POST['ideaId']) ? (int) $_POST['ideaId'] : 0;
    $member_id = isset($_POST['memberId']) ? (int) $_POST['memberId'] : 0;

    if ($idea_id <= 0 || $member_id <= 0) {
        wp_send_json_error(array('message' => __('Identifiant d\'idée ou de membre invalide.', 'mj-member')));
        return;
    }

    $payload = isset($_POST['data']) ? $_POST['data'] : array();
    if (is_string($payload)) {
        $decoded = json_decode(stripslashes($payload), true);
        $payload = is_array($decoded) ? $decoded : array();
    }

    if (empty($payload) || !is_array($payload)) {
        wp_send_json_error(array('message' => __('Aucune donnée fournie pour la mise à jour.', 'mj-member')));
        return;
    }

    $idea = MjIdeas::get($idea_id);
    if (!$idea) {
        wp_send_json_error(array('message' => __('Idée introuvable.', 'mj-member')));
        return;
    }

    if ((int) ($idea['member_id'] ?? 0) !== $member_id) {
        wp_send_json_error(array('message' => __('Cette idée n\'est pas associée à ce membre.', 'mj-member')));
        return;
    }

    $update = array();

    if (array_key_exists('title', $payload)) {
        $update['title'] = sanitize_text_field(wp_unslash((string) $payload['title']));
    }

    if (array_key_exists('content', $payload)) {
        $update['content'] = sanitize_textarea_field(wp_unslash((string) $payload['content']));
    }

    if (array_key_exists('status', $payload)) {
        $status = sanitize_key($payload['status']);
        if (!in_array($status, MjIdeas::statuses(), true)) {
            wp_send_json_error(array('message' => __('Statut d\'idée invalide.', 'mj-member')));
            return;
        }
        $update['status'] = $status;
    }

    if (empty($update)) {
        wp_send_json_error(array('message' => __('Aucune donnée à mettre à jour.', 'mj-member')));
        return;
    }

    $result = MjIdeas::update($idea_id, $update);
    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()));
        return;
    }

    $updated = MjIdeas::get($idea_id);
    if (!$updated) {
        wp_send_json_success(array(
            'message' => __('Idée mise à jour.', 'mj-member'),
        ));
        return;
    }

    $response = array(
        'id' => (int) ($updated['id'] ?? $idea_id),
        'title' => isset($updated['title']) ? (string) $updated['title'] : '',
        'content' => isset($updated['content']) ? (string) $updated['content'] : '',
        'status' => isset($updated['status']) ? (string) $updated['status'] : MjIdeas::STATUS_PUBLISHED,
        'voteCount' => isset($updated['vote_count']) ? (int) $updated['vote_count'] : 0,
        'createdAt' => isset($updated['created_at']) ? (string) $updated['created_at'] : '',
        'updatedAt' => isset($updated['updated_at']) ? (string) $updated['updated_at'] : '',
    );

    wp_send_json_success(array(
        'message' => __('Idée mise à jour.', 'mj-member'),
        'idea' => $response,
    ));
}

/**
 * Update a member photo (caption or status)
 */
function mj_regmgr_update_member_photo() {
    $auth = mj_regmgr_verify_request();
    if (!$auth) {
        return;
    }

    $photo_id = isset($_POST['photoId']) ? (int) $_POST['photoId'] : 0;
    $member_id = isset($_POST['memberId']) ? (int) $_POST['memberId'] : 0;

    if ($photo_id <= 0 || $member_id <= 0) {
        wp_send_json_error(array('message' => __('Identifiant de photo ou de membre invalide.', 'mj-member')));
        return;
    }

    $payload = isset($_POST['data']) ? $_POST['data'] : array();
    if (is_string($payload)) {
        $decoded = json_decode(stripslashes($payload), true);
        $payload = is_array($decoded) ? $decoded : array();
    }

    if (empty($payload) || !is_array($payload)) {
        wp_send_json_error(array('message' => __('Aucune donnée fournie pour la mise à jour.', 'mj-member')));
        return;
    }

    $photo = MjEventPhotos::get($photo_id);
    if (!$photo) {
        wp_send_json_error(array('message' => __('Photo introuvable.', 'mj-member')));
        return;
    }

    if ((int) ($photo->member_id ?? 0) !== $member_id) {
        wp_send_json_error(array('message' => __('Cette photo n\'est pas associée à ce membre.', 'mj-member')));
        return;
    }

    $update = array();

    if (array_key_exists('caption', $payload)) {
        $caption = sanitize_textarea_field(wp_unslash((string) $payload['caption']));
        $update['caption'] = $caption;
    }

    if (array_key_exists('status', $payload)) {
        $status = sanitize_key($payload['status']);
        $status_labels = MjEventPhotos::get_status_labels();
        if (!array_key_exists($status, $status_labels)) {
            wp_send_json_error(array('message' => __('Statut photo invalide.', 'mj-member')));
            return;
        }
        $update['status'] = $status;
        $update['reviewed_at'] = current_time('mysql');
        $update['reviewed_by'] = get_current_user_id();
    }

    if (empty($update)) {
        wp_send_json_error(array('message' => __('Aucune donnée à mettre à jour.', 'mj-member')));
        return;
    }

    $result = MjEventPhotos::update($photo_id, $update);
    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()));
        return;
    }

    $updated = MjEventPhotos::get($photo_id);

    $photo_payload = array('id' => $photo_id);
    if ($updated) {
        $attachment_id = isset($updated->attachment_id) ? (int) $updated->attachment_id : 0;
        $thumbnail = $attachment_id > 0 ? wp_get_attachment_image_url($attachment_id, 'medium') : '';
        if (!$thumbnail && $attachment_id > 0) {
            $thumbnail = wp_get_attachment_url($attachment_id);
        }
        $full = $attachment_id > 0 ? wp_get_attachment_image_url($attachment_id, 'large') : '';
        if (!$full && $attachment_id > 0) {
            $full = wp_get_attachment_url($attachment_id);
        }

        $event_title = '';
        if (!empty($updated->event_id)) {
            $event = MjEvents::find((int) $updated->event_id);
            if ($event) {
                $event_title = isset($event->title) ? (string) $event->title : '';
            }
        }

        $status_labels = MjEventPhotos::get_status_labels();
        $status_key = isset($updated->status) ? (string) $updated->status : MjEventPhotos::STATUS_APPROVED;

        $photo_payload = array(
            'id' => (int) $updated->id,
            'eventId' => isset($updated->event_id) ? (int) $updated->event_id : 0,
            'eventTitle' => $event_title,
            'caption' => isset($updated->caption) ? (string) $updated->caption : '',
            'status' => $status_key,
            'statusLabel' => isset($status_labels[$status_key]) ? (string) $status_labels[$status_key] : $status_key,
            'thumbnailUrl' => $thumbnail ?: '',
            'fullUrl' => $full ?: $thumbnail ?: '',
            'createdAt' => isset($updated->created_at) ? (string) $updated->created_at : '',
        );
    }

    wp_send_json_success(array(
        'message' => __('Photo mise à jour.', 'mj-member'),
        'photo' => $photo_payload,
    ));
}

/**
 * Delete a member photo
 */
function mj_regmgr_delete_member_photo() {
    $auth = mj_regmgr_verify_request();
    if (!$auth) {
        return;
    }

    $photo_id = isset($_POST['photoId']) ? (int) $_POST['photoId'] : 0;
    $member_id = isset($_POST['memberId']) ? (int) $_POST['memberId'] : 0;

    if ($photo_id <= 0 || $member_id <= 0) {
        wp_send_json_error(array('message' => __('Identifiant de photo ou de membre invalide.', 'mj-member')));
        return;
    }

    $photo = MjEventPhotos::get($photo_id);
    if (!$photo) {
        wp_send_json_error(array('message' => __('Photo introuvable.', 'mj-member')));
        return;
    }

    if ((int) ($photo->member_id ?? 0) !== $member_id) {
        wp_send_json_error(array('message' => __('Cette photo n\'est pas associée à ce membre.', 'mj-member')));
        return;
    }

    $result = MjEventPhotos::delete($photo_id);
    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()));
        return;
    }

    wp_send_json_success(array(
        'message' => __('Photo supprimée.', 'mj-member'),
        'photoId' => $photo_id,
    ));
}

/**
 * Delete a member contact message
 */
function mj_regmgr_delete_member_message() {
    $auth = mj_regmgr_verify_request();
    if (!$auth) {
        return;
    }

    $message_id = isset($_POST['messageId']) ? (int) $_POST['messageId'] : 0;
    $member_id = isset($_POST['memberId']) ? (int) $_POST['memberId'] : 0;

    if ($message_id <= 0 || $member_id <= 0) {
        wp_send_json_error(array('message' => __('Identifiant de message ou de membre invalide.', 'mj-member')));
        return;
    }

    $message = MjContactMessages::get($message_id);
    if (!$message) {
        wp_send_json_error(array('message' => __('Message introuvable.', 'mj-member')));
        return;
    }

    $member = MjMembers::getById($member_id);
    if (!$member) {
        wp_send_json_error(array('message' => __('Membre introuvable.', 'mj-member')));
        return;
    }

    $linked_member_id = 0;
    if (!empty($message->meta)) {
        $meta = json_decode($message->meta, true);
        if (is_array($meta)) {
            if (isset($meta['member_id'])) {
                $linked_member_id = (int) $meta['member_id'];
            } elseif (isset($meta['member']['id'])) {
                $linked_member_id = (int) $meta['member']['id'];
            }
        }
        if ($linked_member_id === 0 && is_string($message->meta)) {
            if (preg_match('/"member_id"\s*:\s*"?(\d+)/', $message->meta, $matches)) {
                $linked_member_id = (int) $matches[1];
            }
        }
    }

    $target_reference = isset($message->target_reference) ? (int) $message->target_reference : 0;
    $sender_email = isset($message->sender_email) ? (string) $message->sender_email : '';
    $member_email = isset($member->email) ? (string) $member->email : '';

    $belongs_to_member = false;
    if ($linked_member_id === $member_id) {
        $belongs_to_member = true;
    } elseif ($target_reference === $member_id) {
        $belongs_to_member = true;
    } elseif ($member_email !== '' && $sender_email !== '' && strcasecmp($member_email, $sender_email) === 0) {
        $belongs_to_member = true;
    }

    if (!$belongs_to_member) {
        wp_send_json_error(array('message' => __('Ce message n\'est pas associé à ce membre.', 'mj-member')));
        return;
    }

    MjContactMessages::record_activity($message_id, 'deleted', array(
        'user_id' => get_current_user_id(),
    ));

    $result = MjContactMessages::delete($message_id);
    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()));
        return;
    }

    wp_send_json_success(array(
        'message' => __('Message supprimé.', 'mj-member'),
        'messageId' => $message_id,
    ));
}

/**
 * Get member's registration history
 */
function mj_regmgr_get_member_registrations() {
    $current_member = mj_regmgr_verify_request();
    if (!$current_member) return;

    $member_id = isset($_POST['memberId']) ? absint($_POST['memberId']) : 0;
    if (!$member_id) {
        wp_send_json_error(array('message' => __('ID du membre manquant.', 'mj-member')));
        return;
    }

    // Get registrations for this member
    $registrations_data = MjEventRegistrations::get_all(array(
        'member_id' => $member_id,
        'limit' => 50,
        'orderby' => 'created_at',
        'order' => 'DESC',
    ));

    $status_labels = MjEventRegistrations::get_status_labels();

    $registrations = array();
    foreach ($registrations_data as $reg) {
        $event = MjEvents::find((int) $reg->event_id);
        $event_title = $event ? $event->title : __('Événement supprimé', 'mj-member');
        $event_date = $event ? $event->start_date : null;

        $registrations[] = array(
            'id' => (int) $reg->id,
            'eventId' => (int) $reg->event_id,
            'eventTitle' => $event_title,
            'status' => $reg->statut ?? $reg->status ?? 'en_attente',
            'statusLabel' => $status_labels[$reg->statut ?? $reg->status ?? 'en_attente'] ?? ($reg->statut ?? $reg->status ?? 'en_attente'),
            'createdAt' => $reg->created_at ?? null,
            'eventDate' => $event_date,
        );
    }

    wp_send_json_success(array('registrations' => $registrations));
}

/**
 * Mark member's membership as paid
 */
function mj_regmgr_mark_membership_paid() {
    $current_member = mj_regmgr_verify_request();
    if (!$current_member) return;

    $member_id = isset($_POST['memberId']) ? absint($_POST['memberId']) : 0;
    $payment_method = isset($_POST['paymentMethod']) ? sanitize_text_field($_POST['paymentMethod']) : 'cash';
    $year = isset($_POST['year']) ? absint($_POST['year']) : (int) date('Y');

    if (!$member_id) {
        wp_send_json_error(array('message' => __('ID du membre manquant.', 'mj-member')));
        return;
    }

    // Validate year
    $current_year = (int) date('Y');
    if ($year < $current_year - 1 || $year > $current_year + 1) {
        wp_send_json_error(array('message' => __('Année invalide.', 'mj-member')));
        return;
    }

    // Update member's membership paid year
    $update_data = array(
        'membership_paid_year' => (string) $year,
        'date_last_payement' => current_time('mysql'),
    );

    $result = MjMembers::update($member_id, $update_data);

    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()));
        return;
    }

    // Log the payment (optional - add a note)
    $payment_method_labels = array(
        'cash' => __('espèces', 'mj-member'),
        'check' => __('chèque', 'mj-member'),
        'transfer' => __('virement', 'mj-member'),
        'card' => __('carte bancaire', 'mj-member'),
    );

    $author = $current_member['member'];
    $author_name = trim(($author->first_name ?? '') . ' ' . ($author->last_name ?? ''));
    $method_label = $payment_method_labels[$payment_method] ?? $payment_method;

    // Create a note for the payment
    mj_regmgr_create_notes_table_if_not_exists();

    global $wpdb;
    $notes_table = $wpdb->prefix . 'mj_member_notes';

    $wpdb->insert($notes_table, array(
        'member_id' => $member_id,
        'author_id' => $current_member['member_id'],
        'content' => sprintf(
            __('Cotisation %d payée (%s) - enregistré par %s', 'mj-member'),
            $year,
            $method_label,
            $author_name
        ),
        'created_at' => current_time('mysql'),
    ), array('%d', '%d', '%s', '%s'));

    wp_send_json_success(array(
        'message' => sprintf(__('Cotisation %d enregistrée avec succès.', 'mj-member'), $year),
        'membershipYear' => $year,
        'membershipStatus' => 'paid',
    ));
}

/**
 * Create a Stripe payment link for membership
 */
function mj_regmgr_create_membership_payment_link() {
    $current_member = mj_regmgr_verify_request();
    if (!$current_member) return;

    $member_id = isset($_POST['memberId']) ? absint($_POST['memberId']) : 0;

    if (!$member_id) {
        wp_send_json_error(array('message' => __('ID du membre manquant.', 'mj-member')));
        return;
    }

    // Get member data
    $member = MjMembers::getById($member_id);
    if (!$member) {
        wp_send_json_error(array('message' => __('Membre introuvable.', 'mj-member')));
        return;
    }

    // Check if MjPayments class exists
    if (!class_exists('Mj\Member\Classes\MjPayments')) {
        wp_send_json_error(array('message' => __('Module de paiement non disponible.', 'mj-member')));
        return;
    }

    // Get the membership amount
    $amount = (float) apply_filters('mj_member_membership_amount', (float) get_option('mj_annual_fee', '2.00'), $member);

    if ($amount <= 0) {
        wp_send_json_error(array('message' => __('Montant de cotisation invalide.', 'mj-member')));
        return;
    }

    // Create Stripe payment
    try {
        $payment = \Mj\Member\Classes\MjPayments::create_stripe_payment($member_id, $amount);

        if (!$payment || empty($payment['checkout_url'])) {
            wp_send_json_error(array('message' => __('Impossible de créer le lien de paiement Stripe.', 'mj-member')));
            return;
        }

        wp_send_json_success(array(
            'checkoutUrl' => $payment['checkout_url'],
            'qrUrl' => $payment['qr_url'] ?? null,
            'paymentId' => $payment['payment_id'] ?? null,
            'amount' => $amount,
        ));
    } catch (Exception $e) {
        wp_send_json_error(array('message' => $e->getMessage()));
    }
}
