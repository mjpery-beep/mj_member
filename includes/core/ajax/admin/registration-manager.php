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
use Mj\Member\Classes\Crud\MjEventVolunteers;
use Mj\Member\Classes\Crud\MjEventLocations;
use Mj\Member\Classes\Crud\MjEventPhotos;
use Mj\Member\Classes\Crud\MjContactMessages;
use Mj\Member\Classes\Crud\MjIdeas;
use Mj\Member\Classes\Crud\MjMembers;
use Mj\Member\Classes\Crud\MjBadges;
use Mj\Member\Classes\Crud\MjMemberBadges;
use Mj\Member\Classes\Crud\MjBadgeCriteria;
use Mj\Member\Classes\Crud\MjMemberBadgeCriteria;
use Mj\Member\Classes\Crud\MjMemberXp;
use Mj\Member\Classes\Crud\MjTrophies;
use Mj\Member\Classes\Crud\MjMemberTrophies;
use Mj\Member\Classes\Crud\MjLevels;
use Mj\Member\Classes\Forms\EventFormDataMapper;
use Mj\Member\Classes\Forms\EventFormOptionsBuilder;
use Mj\Member\Classes\MjEventSchedule;
use Mj\Member\Classes\MjRoles;
use Mj\Member\Classes\MjTrophyService;
use Mj\Member\Core\Config;
use Mj\Member\Classes\Value\EventLocationData;

if (!defined('ABSPATH')) {
    exit;
}

// Register AJAX actions
add_action('wp_ajax_mj_regmgr_get_events', 'mj_regmgr_get_events');
add_action('wp_ajax_mj_regmgr_get_event_details', 'mj_regmgr_get_event_details');
add_action('wp_ajax_mj_regmgr_get_event_editor', 'mj_regmgr_get_event_editor');
add_action('wp_ajax_mj_regmgr_update_event', 'mj_regmgr_update_event');
add_action('wp_ajax_mj_regmgr_create_event', 'mj_regmgr_create_event');
add_action('wp_ajax_mj_regmgr_delete_event', 'mj_regmgr_delete_event');
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
add_action('wp_ajax_mj_regmgr_save_event_occurrences', 'mj_regmgr_save_event_occurrences');
add_action('wp_ajax_mj_regmgr_get_location', 'mj_regmgr_get_location');
add_action('wp_ajax_mj_regmgr_save_location', 'mj_regmgr_save_location');

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
add_action('wp_ajax_mj_regmgr_capture_member_photo', 'mj_regmgr_capture_member_photo');
add_action('wp_ajax_mj_regmgr_delete_member_message', 'mj_regmgr_delete_member_message');
add_action('wp_ajax_mj_regmgr_reset_member_password', 'mj_regmgr_reset_member_password');
add_action('wp_ajax_mj_regmgr_delete_member', 'mj_regmgr_delete_member');
add_action('wp_ajax_mj_regmgr_sync_member_badge', 'mj_regmgr_sync_member_badge');
add_action('wp_ajax_mj_regmgr_adjust_member_xp', 'mj_regmgr_adjust_member_xp');
add_action('wp_ajax_mj_regmgr_toggle_member_trophy', 'mj_regmgr_toggle_member_trophy');

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
 * Retourne un emoji normalisé à partir d'un événement ou d'un tableau associatif.
 *
 * @param object|array|null $event Source contenant éventuellement une clé/propriété "emoji"
 * @return string Emoji nettoyé (16 caractères max) ou chaîne vide
 */
function mj_regmgr_get_event_emoji_value($event) {
    $raw = '';

    if (is_array($event) && isset($event['emoji'])) {
        $raw = $event['emoji'];
    } elseif (is_object($event) && isset($event->emoji)) {
        $raw = $event->emoji;
    }

    if (!is_scalar($raw)) {
        return '';
    }

    $candidate = wp_check_invalid_utf8((string) $raw);
    if ($candidate === '') {
        return '';
    }

    $candidate = wp_strip_all_tags($candidate, false);
    $candidate = preg_replace('/[\x00-\x1F\x7F]+/', '', $candidate);
    if (!is_string($candidate)) {
        return '';
    }

    $candidate = trim($candidate);
    if ($candidate === '') {
        return '';
    }

    if (function_exists('wp_html_excerpt')) {
        $candidate = wp_html_excerpt($candidate, 16, '');
    } elseif (function_exists('mb_substr')) {
        $candidate = mb_substr($candidate, 0, 16);
    } else {
        $candidate = substr($candidate, 0, 16);
    }

    return trim($candidate);
}

/**
 * Prépare les données d'un événement pour la sidebar.
 *
 * @param object|null $event
 * @param array<string,string>|null $type_labels
 * @param array<string,string>|null $status_labels
 * @return array<string,mixed>|null
 */
function mj_regmgr_build_event_sidebar_item($event, $type_labels = null, $status_labels = null) {
    if (!$event || !isset($event->id)) {
        return null;
    }

    if ($type_labels === null) {
        $type_labels = MjEvents::get_type_labels();
    }

    if ($status_labels === null) {
        $status_labels = MjEvents::get_status_labels();
    }

    $event_id = (int) $event->id;
    $type_key = isset($event->type) ? sanitize_key((string) $event->type) : '';
    $status_key = isset($event->status) ? sanitize_key((string) $event->status) : '';

    $schedule_mode = isset($event->schedule_mode) ? sanitize_key((string) $event->schedule_mode) : 'fixed';
    if ($schedule_mode === '') {
        $schedule_mode = 'fixed';
    }

    $occurrence_mode = isset($event->occurrence_selection_mode) ? sanitize_key((string) $event->occurrence_selection_mode) : 'member_choice';
    if (!in_array($occurrence_mode, array('member_choice', 'all_occurrences'), true)) {
        $occurrence_mode = 'member_choice';
    }

    $registrations_count = MjEventRegistrations::count(array('event_id' => $event_id));

    $schedule_info = mj_regmgr_build_event_schedule_info($event, $schedule_mode);

    $registration_payload = mj_regmgr_decode_json_field(isset($event->registration_payload) ? $event->registration_payload : array());
    $attendance_show_all_members = !empty($registration_payload['attendance_show_all_members']);
    if (!$attendance_show_all_members && isset($event->attendance_show_all_members)) {
        $attendance_show_all_members = !empty($event->attendance_show_all_members);
    }

    $emoji_value = mj_regmgr_get_event_emoji_value($event);

    return array(
        'id' => $event_id,
        'title' => isset($event->title) ? (string) $event->title : '',
        'emoji' => $emoji_value,
        'type' => $type_key,
        'typeLabel' => isset($type_labels[$type_key]) ? $type_labels[$type_key] : ($type_key !== '' ? $type_key : ''),
        'status' => $status_key,
        'statusLabel' => isset($status_labels[$status_key]) ? $status_labels[$status_key] : ($status_key !== '' ? $status_key : ''),
        'dateDebut' => isset($event->date_debut) ? (string) $event->date_debut : '',
        'dateFin' => isset($event->date_fin) ? (string) $event->date_fin : '',
        'dateDebutFormatted' => mj_regmgr_format_date(isset($event->date_debut) ? $event->date_debut : ''),
        'dateFinFormatted' => mj_regmgr_format_date(isset($event->date_fin) ? $event->date_fin : ''),
        'coverId' => isset($event->cover_id) ? (int) $event->cover_id : 0,
        'coverUrl' => mj_regmgr_get_event_cover_url($event, 'thumbnail'),
        'accentColor' => isset($event->accent_color) ? (string) $event->accent_color : '',
        'registrationsCount' => $registrations_count,
        'capacityTotal' => isset($event->capacity_total) ? (int) $event->capacity_total : 0,
        'prix' => isset($event->prix) ? (float) $event->prix : 0.0,
        'scheduleMode' => $schedule_mode,
        'scheduleSummary' => isset($schedule_info['summary']) ? $schedule_info['summary'] : '',
        'scheduleDetail' => isset($schedule_info['detail']) ? $schedule_info['detail'] : '',
        'freeParticipation' => !empty($event->free_participation),
        'occurrenceSelectionMode' => $occurrence_mode,
        'attendanceShowAllMembers' => $attendance_show_all_members,
    );
}

function mj_regmgr_format_datetime_compact($datetime_value) {
    if (!is_string($datetime_value) || $datetime_value === '') {
        return '';
    }
    $timestamp = strtotime($datetime_value);
    if ($timestamp === false) {
        return '';
    }
    return wp_date('d/m H:i', $timestamp);
}

function mj_regmgr_format_date_compact($datetime_value) {
    if (!is_string($datetime_value) || $datetime_value === '') {
        return '';
    }
    $timestamp = strtotime($datetime_value);
    if ($timestamp === false) {
        return '';
    }
    return wp_date('d/m', $timestamp);
}

function mj_regmgr_format_time_compact($datetime_value) {
    if (!is_string($datetime_value) || $datetime_value === '') {
        return '';
    }
    $timestamp = strtotime($datetime_value);
    if ($timestamp === false) {
        return '';
    }
    return wp_date('H:i', $timestamp);
}

function mj_regmgr_occurrence_status_from_front($status) {
    $candidate = sanitize_key((string) $status);
    switch ($candidate) {
        case 'confirmed':
            return MjEventOccurrences::STATUS_ACTIVE;
        case 'cancelled':
            return MjEventOccurrences::STATUS_ANNULE;
        case 'postponed':
            return MjEventOccurrences::STATUS_REPORTE;
        case 'planned':
        case 'pending':
        default:
            return MjEventOccurrences::STATUS_A_CONFIRMER;
    }
}

function mj_regmgr_occurrence_status_to_front($status) {
    $candidate = sanitize_key((string) $status);
    switch ($candidate) {
        case MjEventOccurrences::STATUS_ACTIVE:
            return 'confirmed';
        case MjEventOccurrences::STATUS_ANNULE:
            return 'cancelled';
        case MjEventOccurrences::STATUS_REPORTE:
            return 'planned';
        case MjEventOccurrences::STATUS_A_CONFIRMER:
        default:
            return 'planned';
    }
}

function mj_regmgr_sanitize_time_value($value) {
    if (!is_string($value)) {
        return '';
    }

    $trimmed = trim($value);
    if ($trimmed === '') {
        return '';
    }
    if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $trimmed)) {
        return $trimmed;
    }

    if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d$/', $trimmed)) {
        return substr($trimmed, 0, 5);
    }

    return '';
}

function mj_regmgr_sanitize_date_value($value) {
    if (!is_string($value)) {
        return '';
    }

    $trimmed = trim($value);
    if ($trimmed === '') {
        return '';
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $trimmed)) {
        return '';
    }

    return $trimmed;
}

function mj_regmgr_sanitize_occurrence_generator_plan($input) {
    if (!is_array($input)) {
        return array();
    }

    $known_keys = array(
        'mode',
        'frequency',
        'startDate',
        'startDateISO',
        'endDate',
        'endDateISO',
        'startTime',
        'endTime',
        'days',
        'overrides',
        'timeOverrides',
        'monthlyOrdinal',
        'monthlyWeekday',
        'explicitStart',
        '_explicitStart',
        'version',
    );

    $has_any = false;
    foreach ($known_keys as $key) {
        if (array_key_exists($key, $input)) {
            $has_any = true;
            break;
        }
    }

    if (!$has_any) {
        return array();
    }

    $weekday_keys = array('mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun');

    $mode = isset($input['mode']) ? sanitize_key($input['mode']) : '';
    if (!in_array($mode, array('weekly', 'monthly', 'range', 'custom'), true)) {
        $mode = 'weekly';
    }

    $frequency = isset($input['frequency']) ? sanitize_key($input['frequency']) : '';
    if (!in_array($frequency, array('every_week', 'every_two_weeks'), true)) {
        $frequency = 'every_week';
    }

    $start_candidates = array();
    if (isset($input['startDateISO'])) {
        $start_candidates[] = $input['startDateISO'];
    }
    if (isset($input['startDate'])) {
        $start_candidates[] = $input['startDate'];
    }

    $start_date = '';
    foreach ($start_candidates as $candidate) {
        $candidate = mj_regmgr_sanitize_date_value($candidate);
        if ($candidate !== '') {
            $start_date = $candidate;
            break;
        }
    }

    $end_candidates = array();
    if (isset($input['endDateISO'])) {
        $end_candidates[] = $input['endDateISO'];
    }
    if (isset($input['endDate'])) {
        $end_candidates[] = $input['endDate'];
    }

    $end_date = '';
    foreach ($end_candidates as $candidate) {
        $candidate = mj_regmgr_sanitize_date_value($candidate);
        if ($candidate !== '') {
            $end_date = $candidate;
            break;
        }
    }

    $start_time = isset($input['startTime']) ? mj_regmgr_sanitize_time_value($input['startTime']) : '';
    $end_time = isset($input['endTime']) ? mj_regmgr_sanitize_time_value($input['endTime']) : '';

    $days = array();
    $days_source = isset($input['days']) ? $input['days'] : array();
    foreach ($weekday_keys as $weekday) {
        $value = false;
        if (is_array($days_source)) {
            if (array_values($days_source) === $days_source) {
                $normalized_source = array_map('sanitize_key', $days_source);
                $value = in_array($weekday, $normalized_source, true);
            } elseif (array_key_exists($weekday, $days_source)) {
                $value = !empty($days_source[$weekday]);
            }
        }
        $days[$weekday] = $value ? true : false;
    }

    $overrides = array();
    $overrides_source = array();
    if (isset($input['overrides']) && is_array($input['overrides'])) {
        $overrides_source = $input['overrides'];
    } elseif (isset($input['timeOverrides']) && is_array($input['timeOverrides'])) {
        $overrides_source = $input['timeOverrides'];
    }

    foreach ($weekday_keys as $weekday) {
        if (!isset($overrides_source[$weekday]) || !is_array($overrides_source[$weekday])) {
            continue;
        }

        $entry = array();
        if (isset($overrides_source[$weekday]['start'])) {
            $override_start = mj_regmgr_sanitize_time_value($overrides_source[$weekday]['start']);
            if ($override_start !== '') {
                $entry['start'] = $override_start;
            }
        }
        if (isset($overrides_source[$weekday]['end'])) {
            $override_end = mj_regmgr_sanitize_time_value($overrides_source[$weekday]['end']);
            if ($override_end !== '') {
                $entry['end'] = $override_end;
            }
        }
        if (!empty($entry)) {
            $overrides[$weekday] = $entry;
        }
    }

    $monthly_ordinal = isset($input['monthlyOrdinal']) ? sanitize_key($input['monthlyOrdinal']) : '';
    if (!in_array($monthly_ordinal, array('first', 'second', 'third', 'fourth', 'last'), true)) {
        $monthly_ordinal = 'first';
    }

    $monthly_weekday = isset($input['monthlyWeekday']) ? sanitize_key($input['monthlyWeekday']) : '';
    if (!in_array($monthly_weekday, $weekday_keys, true)) {
        $monthly_weekday = 'mon';
    }

    $explicit_start = false;
    if (isset($input['explicitStart'])) {
        $explicit_start = mj_regmgr_to_bool($input['explicitStart'], false);
    } elseif (isset($input['_explicitStart'])) {
        $explicit_start = mj_regmgr_to_bool($input['_explicitStart'], false);
    }
    if ($start_date !== '') {
        $explicit_start = true;
    }

    return array(
        'version' => 'occurrence-editor',
        'mode' => $mode,
        'frequency' => $frequency,
        'startDate' => $start_date,
        'endDate' => $end_date,
        'startTime' => $start_time,
        'endTime' => $end_time,
        'days' => $days,
        'overrides' => $overrides,
        'monthlyOrdinal' => $monthly_ordinal,
        'monthlyWeekday' => $monthly_weekday,
        'explicitStart' => $explicit_start,
    );
}

function mj_regmgr_derive_generator_plan_from_schedule($schedule_payload) {
    if (!is_array($schedule_payload)) {
        return array();
    }

    $mode = isset($schedule_payload['mode']) ? sanitize_key($schedule_payload['mode']) : '';
    if ($mode !== 'recurring') {
        return array();
    }

    $weekday_keys = array('mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun');
    $days_map = array();
    foreach ($weekday_keys as $weekday_key) {
        $days_map[$weekday_key] = false;
    }

    if (isset($schedule_payload['weekdays']) && is_array($schedule_payload['weekdays'])) {
        foreach ($schedule_payload['weekdays'] as $weekday) {
            $weekday = sanitize_key($weekday);
            if (isset($days_map[$weekday])) {
                $days_map[$weekday] = true;
            }
        }
    }

    $weekday_times = array();
    if (isset($schedule_payload['weekday_times']) && is_array($schedule_payload['weekday_times'])) {
        foreach ($schedule_payload['weekday_times'] as $weekday => $time_info) {
            $weekday = sanitize_key($weekday);
            if (!isset($days_map[$weekday])) {
                $days_map[$weekday] = true;
            }
            if (is_array($time_info)) {
                $weekday_times[$weekday] = $time_info;
            }
        }
    }

    $base_start_time = isset($schedule_payload['start_time']) ? mj_regmgr_sanitize_time_value($schedule_payload['start_time']) : '';
    $base_end_time = isset($schedule_payload['end_time']) ? mj_regmgr_sanitize_time_value($schedule_payload['end_time']) : '';

    $overrides = array();
    foreach ($days_map as $weekday => $is_active) {
        if (!$is_active) {
            continue;
        }

        $specific = isset($weekday_times[$weekday]) && is_array($weekday_times[$weekday]) ? $weekday_times[$weekday] : array();
        $specific_start = isset($specific['start']) ? mj_regmgr_sanitize_time_value($specific['start']) : '';
        $specific_end = isset($specific['end']) ? mj_regmgr_sanitize_time_value($specific['end']) : '';

        if ($specific_start === '' && $base_start_time !== '') {
            $specific_start = $base_start_time;
        }
        if ($specific_end === '' && $base_end_time !== '') {
            $specific_end = $base_end_time;
        }

        if ($specific_start !== '' || $specific_end !== '') {
            $entry = array();
            if ($specific_start !== '') {
                $entry['start'] = $specific_start;
            }
            if ($specific_end !== '') {
                $entry['end'] = $specific_end;
            }
            $overrides[$weekday] = $entry;
        }
    }

    $start_date = '';
    if (isset($schedule_payload['start_date'])) {
        $start_date = mj_regmgr_sanitize_date_value($schedule_payload['start_date']);
    }

    $end_date = '';
    if (isset($schedule_payload['end_date'])) {
        $end_date = mj_regmgr_sanitize_date_value($schedule_payload['end_date']);
    }
    if ($end_date === '' && isset($schedule_payload['until'])) {
        $until_candidate = (string) $schedule_payload['until'];
        if ($until_candidate !== '') {
            $end_date = mj_regmgr_sanitize_date_value(substr($until_candidate, 0, 10));
        }
    }

    $frequency_source = isset($schedule_payload['frequency']) ? sanitize_key($schedule_payload['frequency']) : 'weekly';
    $interval = isset($schedule_payload['interval']) ? max(1, (int) $schedule_payload['interval']) : 1;

    $plan_mode = $frequency_source === 'monthly' ? 'monthly' : 'weekly';
    $plan_frequency = ($interval >= 2) ? 'every_two_weeks' : 'every_week';

    $plan = array(
        'version' => 'occurrence-editor',
        'mode' => $plan_mode,
        'frequency' => $plan_frequency,
        'startDate' => $start_date,
        'endDate' => $end_date,
        'startTime' => $base_start_time,
        'endTime' => $base_end_time,
        'days' => $days_map,
        'overrides' => $overrides,
        'explicitStart' => $start_date !== '',
    );

    if ($plan_mode === 'monthly') {
        $ordinal = isset($schedule_payload['ordinal']) ? sanitize_key($schedule_payload['ordinal']) : '';
        if ($ordinal !== '') {
            $plan['monthlyOrdinal'] = $ordinal;
        }
        $weekday = isset($schedule_payload['weekday']) ? sanitize_key($schedule_payload['weekday']) : '';
        if ($weekday !== '') {
            $plan['monthlyWeekday'] = $weekday;
            if (isset($plan['days'][$weekday])) {
                $plan['days'][$weekday] = true;
            }
        }
    }

    return mj_regmgr_sanitize_occurrence_generator_plan($plan);
}

function mj_regmgr_merge_generator_plans(array $primary, array $fallback) {
    if (empty($primary)) {
        return $fallback;
    }
    if (empty($fallback)) {
        return mj_regmgr_sanitize_occurrence_generator_plan($primary);
    }

    $merged = $primary;

    if (isset($fallback['days']) && is_array($fallback['days'])) {
        $merged_days = isset($merged['days']) && is_array($merged['days']) ? $merged['days'] : array();
        foreach ($fallback['days'] as $weekday => $flag) {
            if (!isset($merged_days[$weekday]) || $merged_days[$weekday] === false) {
                $merged_days[$weekday] = !empty($flag);
            }
        }
        $merged['days'] = $merged_days;
    }

    if (isset($fallback['overrides']) && is_array($fallback['overrides'])) {
        $merged_overrides = isset($merged['overrides']) && is_array($merged['overrides']) ? $merged['overrides'] : array();
        foreach ($fallback['overrides'] as $weekday => $override) {
            if (!is_array($override)) {
                continue;
            }

            if (!isset($merged_overrides[$weekday]) || !is_array($merged_overrides[$weekday]) || empty($merged_overrides[$weekday])) {
                $merged_overrides[$weekday] = $override;
                continue;
            }

            $current = $merged_overrides[$weekday];

            if ((!isset($current['start']) || $current['start'] === '') && isset($override['start']) && $override['start'] !== '') {
                $current['start'] = $override['start'];
            }

            if ((!isset($current['end']) || $current['end'] === '') && isset($override['end']) && $override['end'] !== '') {
                $current['end'] = $override['end'];
            }

            $merged_overrides[$weekday] = $current;
        }
        $merged['overrides'] = $merged_overrides;
    }

    $simple_keys = array('mode', 'frequency', 'startDate', 'endDate', 'startTime', 'endTime', 'monthlyOrdinal', 'monthlyWeekday');
    foreach ($simple_keys as $key) {
        if ((!isset($merged[$key]) || $merged[$key] === '' || $merged[$key] === null) && isset($fallback[$key])) {
            $merged[$key] = $fallback[$key];
        }
    }

    if (empty($merged['explicitStart']) && !empty($fallback['explicitStart'])) {
        $merged['explicitStart'] = true;
    }

    if (empty($merged['version'])) {
        $merged['version'] = 'occurrence-editor';
    }

    return mj_regmgr_sanitize_occurrence_generator_plan($merged);
}

function mj_regmgr_extract_occurrence_generator_from_payload($payload) {
    if (!is_array($payload)) {
        return array();
    }

    if (isset($payload['occurrence_generator']) && is_array($payload['occurrence_generator'])) {
        return mj_regmgr_sanitize_occurrence_generator_plan($payload['occurrence_generator']);
    }

    if (isset($payload['occurrenceGenerator']) && is_array($payload['occurrenceGenerator'])) {
        return mj_regmgr_sanitize_occurrence_generator_plan($payload['occurrenceGenerator']);
    }

    $derived = mj_regmgr_derive_generator_plan_from_schedule($payload);
    if (!empty($derived)) {
        return $derived;
    }

    return array();
}

function mj_regmgr_extract_occurrence_generator_from_event($event) {
    if (!$event || !isset($event->schedule_payload)) {
        return array();
    }

    $payload = mj_regmgr_decode_json_field($event->schedule_payload);
    if (!is_array($payload)) {
        return array();
    }

    return mj_regmgr_extract_occurrence_generator_from_payload($payload);
}

function mj_regmgr_schedule_payload_has_occurrence_entities($payload) {
    if (!is_array($payload)) {
        return false;
    }

    $collections = array();
    if (isset($payload['occurrences']) && is_array($payload['occurrences'])) {
        $collections[] = $payload['occurrences'];
    }
    if (isset($payload['items']) && is_array($payload['items'])) {
        $collections[] = $payload['items'];
    }

    foreach ($collections as $entries) {
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            foreach ($entry as $value) {
                if ($value !== null && $value !== '') {
                    return true;
                }
            }
        }
    }

    return false;
}

function mj_regmgr_should_allow_occurrence_fallback($event) {
    if (!$event) {
        return false;
    }

    $schedule_mode = '';
    if (is_object($event) && isset($event->schedule_mode)) {
        $schedule_mode = sanitize_key((string) $event->schedule_mode);
    } elseif (is_array($event) && isset($event['schedule_mode'])) {
        $schedule_mode = sanitize_key((string) $event['schedule_mode']);
    }

    if ($schedule_mode === 'series' || $schedule_mode === 'recurring') {
        return false;
    }

    $date_debut = isset($event->date_debut) ? (string) $event->date_debut : '';
    if ($date_debut === '') {
        return false;
    }

    $payload = mj_regmgr_decode_json_field(isset($event->schedule_payload) ? $event->schedule_payload : array());
    if (!is_array($payload)) {
        return true;
    }

    $mode = isset($payload['mode']) ? sanitize_key((string) $payload['mode']) : '';
    $version = isset($payload['version']) ? sanitize_key((string) $payload['version']) : '';

    if (($version === 'occurrence-editor' || $mode === 'series') && !mj_regmgr_schedule_payload_has_occurrence_entities($payload)) {
        return false;
    }

    return true;
}

function mj_regmgr_prepare_event_occurrence_rows($input) {
    $normalized = array(
        'rows' => array(),
        'stats' => array(
            'min_start' => null,
            'max_end' => null,
        ),
    );

    if (!is_array($input)) {
        return $normalized;
    }

    $timezone = function_exists('wp_timezone') ? wp_timezone() : new \DateTimeZone('UTC');

    foreach ($input as $item) {
        if (!is_array($item)) {
            continue;
        }

        $date = isset($item['date']) ? sanitize_text_field($item['date']) : '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            continue;
        }

        $start_time = isset($item['startTime']) ? sanitize_text_field($item['startTime']) : '';
        if (!preg_match('/^\d{2}:\d{2}$/', $start_time)) {
            $start_time = '09:00';
        }

        $end_time = isset($item['endTime']) ? sanitize_text_field($item['endTime']) : '';
        if (!preg_match('/^\d{2}:\d{2}$/', $end_time)) {
            $end_time = $start_time;
        }

        $start_string = $date . ' ' . $start_time . ':00';
        $end_string = $date . ' ' . $end_time . ':00';

        $start_dt = \DateTime::createFromFormat('Y-m-d H:i:s', $start_string, $timezone);
        if (!$start_dt instanceof \DateTime) {
            $timestamp = strtotime($start_string);
            if ($timestamp === false) {
                continue;
            }
            $start_dt = new \DateTime('@' . $timestamp);
            $start_dt->setTimezone($timezone);
        }

        $end_dt = \DateTime::createFromFormat('Y-m-d H:i:s', $end_string, $timezone);
        if (!$end_dt instanceof \DateTime) {
            $timestamp_end = strtotime($end_string);
            if ($timestamp_end === false) {
                $end_dt = clone $start_dt;
                $end_dt->modify('+1 hour');
            } else {
                $end_dt = new \DateTime('@' . $timestamp_end);
                $end_dt->setTimezone($timezone);
            }
        }

        if ($end_dt <= $start_dt) {
            $end_dt = clone $start_dt;
            $end_dt->modify('+1 hour');
        }

        $status = mj_regmgr_occurrence_status_from_front(isset($item['status']) ? $item['status'] : '');
        $source = isset($item['source']) ? sanitize_key((string) $item['source']) : '';
        if ($source === '') {
            $source = MjEventOccurrences::SOURCE_MANUAL;
        }

        $meta = array();
        if (!empty($item['reason'])) {
            $meta['reason'] = sanitize_text_field($item['reason']);
        }
        if (!empty($item['id'])) {
            $meta['client_id'] = sanitize_text_field((string) $item['id']);
        }
        if ($status !== '') {
            $meta['status'] = $status;
        }

        $start_formatted = $start_dt->format('Y-m-d H:i:s');
        $end_formatted = $end_dt->format('Y-m-d H:i:s');

        $row = array(
            'start' => $start_formatted,
            'end' => $end_formatted,
            'status' => $status,
            'source' => $source,
            'meta' => !empty($meta) ? $meta : null,
        );

        $normalized['rows'][] = $row;

        if ($normalized['stats']['min_start'] === null || strcmp($start_formatted, $normalized['stats']['min_start']) < 0) {
            $normalized['stats']['min_start'] = $start_formatted;
        }

        if ($normalized['stats']['max_end'] === null || strcmp($end_formatted, $normalized['stats']['max_end']) > 0) {
            $normalized['stats']['max_end'] = $end_formatted;
        }
    }

    if (!empty($normalized['rows'])) {
        usort(
            $normalized['rows'],
            static function ($left, $right) {
                return strcmp($left['start'], $right['start']);
            }
        );
    }

    return $normalized;
}

function mj_regmgr_format_event_occurrences_for_front($occurrences) {
    $formatted = array();
    if (!is_array($occurrences)) {
        return $formatted;
    }

    foreach ($occurrences as $occurrence) {
        if (!is_array($occurrence)) {
            continue;
        }

        $start = isset($occurrence['start']) ? (string) $occurrence['start'] : '';
        if ($start === '') {
            continue;
        }

        $end = isset($occurrence['end']) ? (string) $occurrence['end'] : '';
        $status = isset($occurrence['status']) ? mj_regmgr_occurrence_status_to_front($occurrence['status']) : 'planned';
        $reason = '';
        if (isset($occurrence['meta'])) {
            $meta = $occurrence['meta'];
            if (is_string($meta)) {
                $decoded_meta = json_decode($meta, true);
                if (is_array($decoded_meta)) {
                    $meta = $decoded_meta;
                }
            }
            if (is_array($meta) && isset($meta['reason'])) {
                $reason = sanitize_text_field((string) $meta['reason']);
            }
        }

        $id = isset($occurrence['id']) ? $occurrence['id'] : (isset($occurrence['timestamp']) ? $occurrence['timestamp'] : md5($start));
        $start_time = substr($start, 11, 5);
        $end_time = $end !== '' ? substr($end, 11, 5) : '';

        $formatted[] = array(
            'id' => $id !== null ? (string) $id : md5($start),
            'start' => $start,
            'end' => $end,
            'date' => substr($start, 0, 10),
            'startTime' => preg_match('/^\d{2}:\d{2}$/', $start_time) ? $start_time : '',
            'endTime' => preg_match('/^\d{2}:\d{2}$/', $end_time) ? $end_time : '',
            'status' => $status,
            'reason' => $reason,
            'startFormatted' => mj_regmgr_format_date($start, true),
            'endFormatted' => $end !== '' ? mj_regmgr_format_date($end, true) : '',
        );
    }

    return $formatted;
}

function mj_regmgr_find_next_occurrence(array $occurrences) {
    if (empty($occurrences)) {
        return null;
    }

    $now = current_time('timestamp');
    foreach ($occurrences as $occurrence) {
        if (!is_array($occurrence)) {
            continue;
        }
        if (!isset($occurrence['timestamp'])) {
            continue;
        }
        if ((int) $occurrence['timestamp'] >= $now) {
            return $occurrence;
        }
    }

    foreach ($occurrences as $occurrence) {
        if (is_array($occurrence)) {
            return $occurrence;
        }
    }

    return null;
}

function mj_regmgr_build_event_schedule_info($event, $mode = '') {
    if (is_object($event)) {
        if (method_exists($event, 'toArray')) {
            $event = $event->toArray();
        } else {
            $event = get_object_vars($event);
        }
    }
    if (!is_array($event)) {
        $event = array();
    }

    $schedule_mode = $mode !== '' ? sanitize_key((string) $mode) : '';
    if ($schedule_mode === '' && isset($event['schedule_mode'])) {
        $schedule_mode = sanitize_key((string) $event['schedule_mode']);
    }
    if ($schedule_mode === '') {
        $schedule_mode = 'fixed';
    }

    $summary = '';
    $detail_parts = array();

    $start_raw = isset($event['date_debut']) ? (string) $event['date_debut'] : '';
    $end_raw = isset($event['date_fin']) ? (string) $event['date_fin'] : '';
    $schedule_payload = array();
    if (isset($event['schedule_payload'])) {
        $schedule_payload = mj_regmgr_decode_json_field($event['schedule_payload']);
    }

    switch ($schedule_mode) {
        case 'range':
            $summary = __('Période continue', 'mj-member');
            $start_date = mj_regmgr_format_date_compact($start_raw);
            $end_date = mj_regmgr_format_date_compact($end_raw);
            if ($start_date !== '' && $end_date !== '') {
                $detail_parts[] = $start_date . ' → ' . $end_date;
            } elseif ($start_date !== '') {
                $detail_parts[] = $start_date;
            }
            $start_time = mj_regmgr_format_time_compact($start_raw);
            $end_time = mj_regmgr_format_time_compact($end_raw);
            if ($start_time !== '' && $end_time !== '') {
                $detail_parts[] = $start_time . ' → ' . $end_time;
            } elseif ($start_time !== '') {
                $detail_parts[] = $start_time;
            }
            break;

        case 'recurring':
        case 'series':
            $summary = $schedule_mode === 'recurring'
                ? __('Récurrence', 'mj-member')
                : __('Série personnalisée', 'mj-member');

            $occurrences = array();
            if (class_exists(MjEventSchedule::class)) {
                $occurrences = MjEventSchedule::build_all_occurrences($event);
            }
            $occurrence_count = is_array($occurrences) ? count($occurrences) : 0;
            $weekday_summary = '';

            if ($schedule_mode === 'recurring') {
                $frequency = isset($schedule_payload['frequency']) ? sanitize_key((string) $schedule_payload['frequency']) : 'weekly';
                if ($frequency === '') {
                    $frequency = 'weekly';
                }

                if ($frequency === 'weekly') {
                    $weekday_labels = mj_regmgr_get_schedule_weekdays();
                    $weekday_keys = array();

                    if (isset($schedule_payload['weekdays']) && is_array($schedule_payload['weekdays'])) {
                        $weekday_keys = $schedule_payload['weekdays'];
                    }

                    if (empty($weekday_keys) && isset($schedule_payload['weekday_times']) && is_array($schedule_payload['weekday_times'])) {
                        $weekday_keys = array_keys($schedule_payload['weekday_times']);
                    }

                    if (!empty($weekday_keys)) {
                        $weekday_keys = array_values(array_unique(array_map('sanitize_key', $weekday_keys)));
                        $ordered_keys = array();
                        $week_order = array('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday');
                        foreach ($week_order as $weekday_key) {
                            if (in_array($weekday_key, $weekday_keys, true)) {
                                $ordered_keys[] = $weekday_key;
                            }
                        }

                        $weekday_names = array();
                        foreach ($ordered_keys as $weekday_key) {
                            if (isset($weekday_labels[$weekday_key])) {
                                $weekday_names[] = $weekday_labels[$weekday_key];
                            }
                        }

                        if (!empty($weekday_names)) {
                            $weekday_summary = implode(', ', $weekday_names);
                        }
                    }
                } elseif ($frequency === 'monthly') {
                    $weekday_labels = mj_regmgr_get_schedule_weekdays();
                    $ordinal_labels = mj_regmgr_get_schedule_month_ordinals();

                    $ordinal = isset($schedule_payload['ordinal']) ? sanitize_key((string) $schedule_payload['ordinal']) : '';
                    $weekday_key = isset($schedule_payload['weekday']) ? sanitize_key((string) $schedule_payload['weekday']) : '';

                    if (isset($ordinal_labels[$ordinal]) && isset($weekday_labels[$weekday_key])) {
                        $weekday_summary = trim($ordinal_labels[$ordinal] . ' ' . $weekday_labels[$weekday_key]);
                    }
                }
            }

            if ($schedule_mode === 'recurring' && $occurrence_count > 0) {
                $detail_parts[] = sprintf(_n('%d séance', '%d séances', $occurrence_count, 'mj-member'), $occurrence_count);
            } elseif ($schedule_mode === 'series' && $occurrence_count > 0) {
                $detail_parts[] = sprintf(_n('%d date', '%d dates', $occurrence_count, 'mj-member'), $occurrence_count);
            }

            if (!empty($weekday_summary)) {
                $summary = sprintf(__('Récurrence · %s', 'mj-member'), $weekday_summary);
            }

            $next_occurrence = mj_regmgr_find_next_occurrence(is_array($occurrences) ? $occurrences : array());
            if ($next_occurrence && !empty($next_occurrence['start'])) {
                $detail_parts[] = sprintf(__('Prochaine : %s', 'mj-member'), mj_regmgr_format_datetime_compact($next_occurrence['start']));
            } elseif ($start_raw !== '') {
                $detail_parts[] = mj_regmgr_format_datetime_compact($start_raw);
            }
            break;

        case 'fixed':
        default:
            $summary = __('Date unique', 'mj-member');
            $start_compact = mj_regmgr_format_datetime_compact($start_raw);
            if ($start_compact !== '') {
                $detail = $start_compact;
                $end_compact_time = '';
                $end_date = mj_regmgr_format_date_compact($end_raw);
                $start_date = mj_regmgr_format_date_compact($start_raw);
                if ($end_date !== '' && $start_date !== '' && $end_date === $start_date) {
                    $end_compact_time = mj_regmgr_format_time_compact($end_raw);
                } else {
                    $end_compact_time = mj_regmgr_format_datetime_compact($end_raw);
                }
                if ($end_compact_time !== '') {
                    $detail .= ' → ' . $end_compact_time;
                }
                $detail_parts[] = $detail;
            }
            break;
    }

    if (is_array($schedule_payload) && isset($schedule_payload['occurrence_summary'])) {
        $custom_summary = trim((string) $schedule_payload['occurrence_summary']);
        if ($custom_summary !== '') {
            $summary = $custom_summary;
        }
    }

    return array(
        'summary' => $summary,
        'detail' => implode(' · ', array_filter($detail_parts)),
    );
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
    $role_filter = isset($_POST['role']) ? sanitize_key($_POST['role']) : '';
    $per_page = isset($_POST['perPage']) ? max(5, min(100, (int) $_POST['perPage'])) : 20;

    $now = current_time('mysql');
    $events = array();
    $total = 0;

    // Pour le filtre "assigned", utiliser MjEventAnimateurs
    if ($filter === 'assigned') {
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
        $formatted = mj_regmgr_build_event_sidebar_item($event, $type_labels, $status_labels);
        if ($formatted !== null) {
            $events_data[] = $formatted;
        }
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
    $schedule_mode = isset($event->schedule_mode) && $event->schedule_mode !== ''
        ? sanitize_key((string) $event->schedule_mode)
        : 'fixed';

    if (class_exists(MjEventSchedule::class)) {
        $raw_occurrences = MjEventSchedule::get_occurrences(
            $event,
            array(
                'max' => 100,
                'include_past' => true,
                'include_cancelled' => true,
            )
        );
        $occurrences = mj_regmgr_format_event_occurrences_for_front($raw_occurrences);
    }

    // Fallback: si pas d'occurrences mais event avec date, créer une occurrence unique
    if (empty($occurrences) && mj_regmgr_should_allow_occurrence_fallback($event)) {
        $fallback_end = isset($event->date_fin) ? (string) $event->date_fin : '';
        $occurrences[] = array(
            'id' => 'single_' . $event->id,
            'start' => (string) $event->date_debut,
            'end' => $fallback_end,
            'date' => substr((string) $event->date_debut, 0, 10),
            'startTime' => substr((string) $event->date_debut, 11, 5),
            'endTime' => $fallback_end !== '' ? substr($fallback_end, 11, 5) : '',
            'status' => 'planned',
            'reason' => '',
            'startFormatted' => mj_regmgr_format_date((string) $event->date_debut, true),
            'endFormatted' => $fallback_end !== '' ? mj_regmgr_format_date($fallback_end, true) : '',
        );
    }

    $schedule_info = mj_regmgr_build_event_schedule_info($event, $schedule_mode);
    $occurrence_generator_plan = mj_regmgr_extract_occurrence_generator_from_event($event);

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

    $event_page_url = apply_filters('mj_member_event_permalink', '', $event);

    $registration_payload = mj_regmgr_decode_json_field(isset($event->registration_payload) ? $event->registration_payload : array());
    $attendance_show_all_members = !empty($registration_payload['attendance_show_all_members']);
    if (!$attendance_show_all_members && isset($event->attendance_show_all_members)) {
        $attendance_show_all_members = !empty($event->attendance_show_all_members);
    }

    $event_emoji = mj_regmgr_get_event_emoji_value($event);

    wp_send_json_success(array(
        'event' => array(
            'id' => $event->id,
            'title' => $event->title,
            'slug' => $event->slug,
            'type' => $event->type,
            'emoji' => $event_emoji,
            'typeLabel' => isset($type_labels[$event->type]) ? $type_labels[$event->type] : $event->type,
            'status' => $event->status,
            'statusLabel' => isset($status_labels[$event->status]) ? $status_labels[$event->status] : $event->status,
            'description' => $event->description,
            'registrationDocument' => isset($event->registration_document) ? $event->registration_document : '',
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
            'scheduleSummary' => isset($schedule_info['summary']) ? $schedule_info['summary'] : '',
            'scheduleDetail' => isset($schedule_info['detail']) ? $schedule_info['detail'] : '',
            'occurrences' => $occurrences,
            'occurrenceGenerator' => $occurrence_generator_plan,
            'location' => $location,
            'animateurs' => $animateurs,
            'frontUrl' => $front_url ?: null,
            'eventPageUrl' => !empty($event_page_url) ? $event_page_url : null,
            'articleId' => !empty($event->article_id) ? (int) $event->article_id : null,
            'occurrenceSelectionMode' => isset($event->occurrence_selection_mode) && $event->occurrence_selection_mode !== ''
                ? $event->occurrence_selection_mode
                : 'member_choice',
            'attendanceShowAllMembers' => $attendance_show_all_members,
        ),
    ));
}

function mj_regmgr_get_event_editor() {
    $auth = mj_regmgr_verify_request();
    if (!$auth) {
        return;
    }

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

    if (!$auth['is_coordinateur'] && !MjEventAnimateurs::member_is_assigned($event_id, $auth['member_id'])) {
        wp_send_json_error(array('message' => __('Permissions insuffisantes pour modifier cet événement.', 'mj-member')), 403);
        return;
    }

    $schedule_weekdays = mj_regmgr_get_schedule_weekdays();
    $schedule_month_ordinals = mj_regmgr_get_schedule_month_ordinals();

    $form_values = mj_regmgr_prepare_event_form_values($event, $schedule_weekdays, $schedule_month_ordinals);
    $references = mj_regmgr_collect_event_editor_assets($event, $form_values);

    $status_labels = MjEvents::get_status_labels();
    $type_labels = MjEvents::get_type_labels();
    $type_colors = MjEvents::get_type_colors();

    $event_form_options = EventFormOptionsBuilder::build(array(
        'status_labels' => $status_labels,
        'type_labels' => $type_labels,
        'type_colors' => $type_colors,
        'current_type' => isset($form_values['type']) ? $form_values['type'] : '',
        'accent_default_color' => isset($form_values['accent_color']) ? $form_values['accent_color'] : '',
        'article_categories' => $references['article_categories'],
        'articles' => $references['articles'],
        'locations' => $references['locations'],
        'animateurs' => $references['animateurs'],
        'volunteers' => $references['volunteers'],
        'schedule_weekdays' => $schedule_weekdays,
        'schedule_month_ordinals' => $schedule_month_ordinals,
    ));

    $form_defaults = EventFormDataMapper::fromValues($form_values);

    $meta = array(
        'scheduleMode' => isset($form_values['schedule_mode']) ? $form_values['schedule_mode'] : 'fixed',
        'schedulePayload' => isset($form_values['schedule_payload']) ? $form_values['schedule_payload'] : array(),
        'scheduleWeekdayTimes' => isset($form_values['schedule_weekday_times']) ? $form_values['schedule_weekday_times'] : array(),
        'scheduleExceptions' => isset($form_values['schedule_exceptions']) ? array_values($form_values['schedule_exceptions']) : array(),
        'scheduleShowDateRange' => !empty($form_values['schedule_show_date_range']),
        'registrationPayload' => isset($form_values['registration_payload']) ? $form_values['registration_payload'] : array(),
    );

    wp_send_json_success(array(
        'event' => mj_regmgr_serialize_event_summary($event),
        'form' => array(
            'values' => $form_defaults,
            'options' => $event_form_options,
            'meta' => $meta,
        ),
    ));
}

function mj_regmgr_update_event() {
    $auth = mj_regmgr_verify_request();
    if (!$auth) {
        return;
    }

    $decoded_json = null;
    $event_id = isset($_POST['eventId']) ? (int) $_POST['eventId'] : 0;

    $content_type = isset($_SERVER['CONTENT_TYPE']) ? strtolower((string) $_SERVER['CONTENT_TYPE']) : '';
    if (strpos($content_type, 'application/json') !== false) {
        $raw_body = file_get_contents('php://input');
        $decoded_candidate = json_decode($raw_body, true);
        if (is_array($decoded_candidate)) {
            $decoded_json = $decoded_candidate;
            if ($event_id <= 0 && isset($decoded_json['eventId'])) {
                $event_id = (int) $decoded_json['eventId'];
            }
            if (!isset($_POST['form']) && isset($decoded_json['form']) && is_array($decoded_json['form'])) {
                $_POST['form'] = $decoded_json['form'];
            }
            if (!isset($_POST['meta']) && isset($decoded_json['meta']) && is_array($decoded_json['meta'])) {
                $_POST['meta'] = $decoded_json['meta'];
            }
        }
    }

    if ($event_id <= 0) {
        wp_send_json_error(array('message' => __('ID événement invalide.', 'mj-member')));
        return;
    }

    $event = MjEvents::find($event_id);
    if (!$event) {
        wp_send_json_error(array('message' => __('Événement introuvable.', 'mj-member')));
        return;
    }

    if (!$auth['is_coordinateur'] && !MjEventAnimateurs::member_is_assigned($event_id, $auth['member_id'])) {
        wp_send_json_error(array('message' => __('Permissions insuffisantes pour modifier cet événement.', 'mj-member')), 403);
        return;
    }

    $schedule_weekdays = mj_regmgr_get_schedule_weekdays();
    $schedule_month_ordinals = mj_regmgr_get_schedule_month_ordinals();

    $form_values = mj_regmgr_prepare_event_form_values($event, $schedule_weekdays, $schedule_month_ordinals);
    $references = mj_regmgr_collect_event_editor_assets($event, $form_values);

    $form_input = array();
    if (isset($_POST['form']) && is_array($_POST['form'])) {
        $form_input = wp_unslash($_POST['form']);
    } else {
        foreach ($_POST as $key => $value) {
            if (!is_string($key) || strpos($key, 'event_') !== 0) {
                continue;
            }
            $form_input[$key] = is_array($value) ? wp_unslash($value) : wp_unslash((string) $value);
        }
    }

    $meta = array();
    if (isset($_POST['meta']) && is_array($_POST['meta'])) {
        $meta = wp_unslash($_POST['meta']);
    } elseif (is_array($decoded_json) && isset($decoded_json['meta']) && is_array($decoded_json['meta'])) {
        $meta = $decoded_json['meta'];
    }

    if (!empty($form_input)) {
        $form_values = EventFormDataMapper::mergeIntoValues($form_values, $form_input);
    }

    if (isset($meta['scheduleWeekdayTimes'])) {
        $form_values['schedule_weekday_times'] = mj_regmgr_sanitize_weekday_times($meta['scheduleWeekdayTimes'], $schedule_weekdays);
    }
    if (isset($meta['scheduleShowDateRange'])) {
        $form_values['schedule_show_date_range'] = mj_regmgr_to_bool($meta['scheduleShowDateRange'], false);
    }
    if (isset($meta['scheduleExceptions'])) {
        $form_values['schedule_exceptions'] = mj_regmgr_sanitize_recurrence_exceptions($meta['scheduleExceptions']);
    }
    if (isset($meta['registrationPayload'])) {
        if (is_array($meta['registrationPayload'])) {
            $form_values['registration_payload'] = $meta['registrationPayload'];
        } else {
            $form_values['registration_payload'] = mj_regmgr_decode_json_field($meta['registrationPayload']);
        }
    }

    $errors = array();
    $build = mj_regmgr_build_event_update_payload($event, $form_values, $meta, $references, $schedule_weekdays, $schedule_month_ordinals, $errors);

    if (!empty($errors)) {
        wp_send_json_error(array('errors' => $errors));
        return;
    }

    if (empty($build) || empty($build['payload']) || !is_array($build['payload'])) {
        wp_send_json_error(array('message' => __('Données de mise à jour invalides.', 'mj-member')));
        return;
    }

    $update_result = MjEvents::update($event_id, $build['payload']);
    if (is_wp_error($update_result)) {
        wp_send_json_error(array('message' => $update_result->get_error_message()));
        return;
    }

    $animateur_ids = isset($build['animateur_ids']) && is_array($build['animateur_ids']) ? $build['animateur_ids'] : array();
    $volunteer_ids = isset($build['volunteer_ids']) && is_array($build['volunteer_ids']) ? $build['volunteer_ids'] : array();

    if (class_exists(MjEventAnimateurs::class)) {
        MjEventAnimateurs::sync_for_event($event_id, $animateur_ids);
    }
    if (class_exists(MjEventVolunteers::class)) {
        MjEventVolunteers::sync_for_event($event_id, $volunteer_ids);
    }

    $updated_event = MjEvents::find($event_id);
    if (!$updated_event) {
        wp_send_json_success(array('message' => __('Événement mis à jour.', 'mj-member')));
        return;
    }

    $updated_form_values = mj_regmgr_prepare_event_form_values($updated_event, $schedule_weekdays, $schedule_month_ordinals);
    $updated_references = mj_regmgr_collect_event_editor_assets($updated_event, $updated_form_values);

    $status_labels = MjEvents::get_status_labels();
    $type_labels = MjEvents::get_type_labels();
    $type_colors = MjEvents::get_type_colors();

    $updated_options = EventFormOptionsBuilder::build(array(
        'status_labels' => $status_labels,
        'type_labels' => $type_labels,
        'type_colors' => $type_colors,
        'current_type' => isset($updated_form_values['type']) ? $updated_form_values['type'] : '',
        'accent_default_color' => isset($updated_form_values['accent_color']) ? $updated_form_values['accent_color'] : '',
        'article_categories' => $updated_references['article_categories'],
        'articles' => $updated_references['articles'],
        'locations' => $updated_references['locations'],
        'animateurs' => $updated_references['animateurs'],
        'volunteers' => $updated_references['volunteers'],
        'schedule_weekdays' => $schedule_weekdays,
        'schedule_month_ordinals' => $schedule_month_ordinals,
    ));

    $response_meta = array(
        'scheduleMode' => isset($updated_form_values['schedule_mode']) ? $updated_form_values['schedule_mode'] : 'fixed',
        'schedulePayload' => isset($updated_form_values['schedule_payload']) ? $updated_form_values['schedule_payload'] : array(),
        'scheduleWeekdayTimes' => isset($updated_form_values['schedule_weekday_times']) ? $updated_form_values['schedule_weekday_times'] : array(),
        'scheduleExceptions' => isset($updated_form_values['schedule_exceptions']) ? array_values($updated_form_values['schedule_exceptions']) : array(),
        'scheduleShowDateRange' => !empty($updated_form_values['schedule_show_date_range']),
        'registrationPayload' => isset($updated_form_values['registration_payload']) ? $updated_form_values['registration_payload'] : array(),
    );

    wp_send_json_success(array(
        'message' => __('Événement mis à jour.', 'mj-member'),
        'event' => mj_regmgr_serialize_event_summary($updated_event),
        'form' => array(
            'values' => EventFormDataMapper::fromValues($updated_form_values),
            'options' => $updated_options,
            'meta' => $response_meta,
        ),
    ));
}

/**
 * Create a new draft event
 */
function mj_regmgr_create_event() {
    $auth = mj_regmgr_verify_request();
    if (!$auth) {
        return;
    }

    if (!current_user_can(Config::capability())) {
        wp_send_json_error(array('message' => __('Permissions insuffisantes pour créer un événement.', 'mj-member')), 403);
        return;
    }

    $raw_title = isset($_POST['title']) ? wp_unslash($_POST['title']) : '';
    $title = sanitize_text_field($raw_title);
    $title = trim($title);

    if ($title === '') {
        wp_send_json_error(array('message' => __('Le titre est requis.', 'mj-member')), 400);
        return;
    }

    $type = isset($_POST['type']) ? sanitize_key(wp_unslash($_POST['type'])) : '';
    $type_labels = MjEvents::get_type_labels();
    if ($type === '' || !isset($type_labels[$type])) {
        $type = MjEvents::TYPE_STAGE;
    }

    $defaults = MjEvents::get_default_values();
    $defaults['title'] = $title;
    $defaults['type'] = $type;
    $defaults['status'] = MjEvents::STATUS_DRAFT;
    $defaults['accent_color'] = MjEvents::get_default_color_for_type($type);

    if (!empty($auth['member_id'])) {
        $defaults['animateur_id'] = (int) $auth['member_id'];
    }

    $event_id = MjEvents::create($defaults);
    if (is_wp_error($event_id)) {
        wp_send_json_error(array('message' => $event_id->get_error_message()), 500);
        return;
    }

    $member_id = isset($auth['member_id']) ? (int) $auth['member_id'] : 0;
    if ($member_id > 0 && class_exists(MjEventAnimateurs::class)) {
        MjEventAnimateurs::sync_for_event($event_id, array($member_id));
    }

    $event = MjEvents::find($event_id);
    if (!$event) {
        wp_send_json_error(array('message' => __('Événement introuvable après création.', 'mj-member')), 500);
        return;
    }

    $status_labels = MjEvents::get_status_labels();
    $event_data = mj_regmgr_build_event_sidebar_item($event, $type_labels, $status_labels);

    wp_send_json_success(array(
        'event' => $event_data,
        'message' => __('Événement brouillon créé. Complétez les informations avant publication.', 'mj-member'),
    ));
}

/**
 * Delete an event and its dependencies
 */
function mj_regmgr_delete_event() {
    $auth = mj_regmgr_verify_request();
    if (!$auth) {
        return;
    }

    $event_id = isset($_POST['eventId']) ? (int) $_POST['eventId'] : 0;
    if ($event_id <= 0) {
        wp_send_json_error(array('message' => __('ID événement invalide.', 'mj-member')));
        return;
    }

    if (!$auth['is_coordinateur'] && !current_user_can(Config::capability())) {
        wp_send_json_error(array('message' => __('Permissions insuffisantes pour supprimer cet événement.', 'mj-member')), 403);
        return;
    }

    $event = MjEvents::find($event_id);
    if (!$event) {
        wp_send_json_error(array('message' => __('Événement introuvable.', 'mj-member')), 404);
        return;
    }

    $result = MjEvents::delete($event_id);
    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()), 500);
        return;
    }

    wp_send_json_success(array(
        'eventId' => $event_id,
        'message' => __('Événement supprimé.', 'mj-member'),
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

    $event = MjEvents::find($event_id);
    $registration_payload = $event ? mj_regmgr_decode_json_field(isset($event->registration_payload) ? $event->registration_payload : array()) : array();
    $attendance_show_all_members = !empty($registration_payload['attendance_show_all_members']);
    if (!$attendance_show_all_members && $event && isset($event->attendance_show_all_members)) {
        $attendance_show_all_members = !empty($event->attendance_show_all_members);
    }

    $registrations = MjEventRegistrations::get_by_event($event_id);
    
    $data = array();
    $attendance_members = array();
    $existing_member_ids = array();
    $now = new DateTime();

    $build_member_payload = function ($member) use ($now) {
        if (!$member) {
            return null;
        }

        $member_id = isset($member->id) ? (int) $member->id : 0;
        if ($member_id <= 0) {
            return null;
        }

        $birth_date = isset($member->birth_date) ? $member->birth_date : null;
        $age = null;
        if (!empty($birth_date) && $birth_date !== '0000-00-00') {
            try {
                $birth = new DateTime($birth_date);
                $age = $now->diff($birth)->y;
            } catch (\Exception $e) {
                $age = null;
            }
        }

        $subscription_status = 'none';
        $last_payment_raw = isset($member->date_last_payement) ? $member->date_last_payement : '';
        if (!empty($last_payment_raw) && $last_payment_raw !== '0000-00-00 00:00:00') {
            try {
                $last_payment = new DateTime($last_payment_raw);
                $expiry = clone $last_payment;
                $expiry->modify('+1 year');
                $subscription_status = $expiry > $now ? 'active' : 'expired';
            } catch (\Exception $e) {
                $subscription_status = 'none';
            }
        }

        $role = isset($member->role) ? (string) $member->role : '';
        $photo_id = isset($member->photo_id) ? (int) $member->photo_id : 0;
        $photo_url = $photo_id > 0 ? wp_get_attachment_image_url($photo_id, 'thumbnail') : '';

        return array(
            'id' => $member_id,
            'firstName' => isset($member->first_name) ? (string) $member->first_name : '',
            'lastName' => isset($member->last_name) ? (string) $member->last_name : '',
            'nickname' => isset($member->nickname) ? (string) $member->nickname : '',
            'email' => isset($member->email) ? (string) $member->email : '',
            'phone' => isset($member->phone) ? (string) $member->phone : '',
            'role' => $role,
            'roleLabel' => MjRoles::getRoleLabel($role),
            'photoId' => $photo_id,
            'photoUrl' => $photo_url ?: '',
            'age' => $age,
            'birthDate' => $birth_date ?: '',
            'subscriptionStatus' => $subscription_status,
            'whatsappOptIn' => isset($member->whatsapp_opt_in) ? ((int) $member->whatsapp_opt_in !== 0) : true,
            'isVolunteer' => !empty($member->is_volunteer),
        );
    };

    foreach ($registrations as $reg) {
        $member = null;
        $guardian = null;
        
        if (!empty($reg->member_id)) {
            $member = MjMembers::getById($reg->member_id);
        }
        
        if (!empty($reg->guardian_id)) {
            $guardian = MjMembers::getById($reg->guardian_id);
        }

        $member_payload = $build_member_payload($member);
        if ($member_payload && isset($member_payload['id'])) {
            $existing_member_ids[(int) $member_payload['id']] = true;
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

        $guardian_payload = null;
        if ($guardian) {
            $guardian_payload = array(
                'id' => $guardian->id,
                'firstName' => $guardian->first_name,
                'lastName' => $guardian->last_name,
                'email' => isset($guardian->email) ? $guardian->email : '',
                'phone' => isset($guardian->phone) ? $guardian->phone : '',
                'whatsappOptIn' => isset($guardian->whatsapp_opt_in) ? ((int) $guardian->whatsapp_opt_in !== 0) : true,
            );
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
            'member' => $member_payload,
            'guardian' => $guardian_payload,
            'attendance' => $attendance,
            'occurrences' => $assigned_occurrences,
            'assignedOccurrences' => $assigned_occurrences,
            'notesCount' => $notes_count,
        );
    }

    if ($attendance_show_all_members) {
        $all_members = MjMembers::get_all(array(
            'limit' => 0,
            'orderby' => 'last_name',
            'order' => 'ASC',
        ));

        foreach ($all_members as $member) {
            $member_payload = $build_member_payload($member);
            if (!$member_payload) {
                continue;
            }

            $member_id = isset($member_payload['id']) ? (int) $member_payload['id'] : 0;
            if ($member_id <= 0 || isset($existing_member_ids[$member_id])) {
                continue;
            }

            if (isset($member->status) && $member->status !== MjMembers::STATUS_ACTIVE) {
                continue;
            }

            $attendance_members[] = $member_payload;
        }
    }

    wp_send_json_success(array(
        'registrations' => $data,
        'attendanceMembers' => $attendance_members,
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

    if ($role_filter !== '') {
        $filters['role'] = $role_filter;
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
            'guardianId' => isset($member->guardian_id) ? (int) $member->guardian_id : 0,
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
function mj_regmgr_event_allows_attendance_without_registration($event) {
    if (!$event) {
        return false;
    }

    $registration_payload = mj_regmgr_decode_json_field(isset($event->registration_payload) ? $event->registration_payload : array());
    $attendance_show_all_members = !empty($registration_payload['attendance_show_all_members']);
    if (!$attendance_show_all_members && isset($event->attendance_show_all_members)) {
        $attendance_show_all_members = !empty($event->attendance_show_all_members);
    }

    return $attendance_show_all_members;
}

/**
 * Ensure a lightweight registration exists so attendance can be recorded.
 *
 * @param object $event
 * @param int    $member_id
 * @return int|WP_Error
 */
function mj_regmgr_ensure_attendance_registration($event, $member_id) {
    $event_id = isset($event->id) ? (int) $event->id : 0;
    $member_id = (int) $member_id;

    if ($event_id <= 0 || $member_id <= 0) {
        return new WP_Error('mj_regmgr_attendance_invalid_args', __('Paramètres de présence invalides.', 'mj-member'));
    }

    $existing = MjEventRegistrations::get_existing($event_id, $member_id);
    if ($existing && isset($existing->id)) {
        return (int) $existing->id;
    }

    if (!function_exists('mj_member_get_event_registrations_table_name')) {
        return new WP_Error('mj_regmgr_attendance_missing_table', __('Table des inscriptions introuvable.', 'mj-member'));
    }

    $table = mj_member_get_event_registrations_table_name();
    if (!$table) {
        return new WP_Error('mj_regmgr_attendance_missing_table', __('Table des inscriptions introuvable.', 'mj-member'));
    }

    $guardian_id = null;
    $member = MjMembers::getById($member_id);
    if ($member && !empty($member->guardian_id)) {
        $guardian_id = (int) $member->guardian_id;
    }

    $now = current_time('mysql');

    $insert = array(
        'event_id' => $event_id,
        'member_id' => $member_id,
    );
    $formats = array('%d', '%d');

    if ($guardian_id) {
        $insert['guardian_id'] = $guardian_id;
        $formats[] = '%d';
    }

    $insert['statut'] = MjEventRegistrations::STATUS_CONFIRMED;
    $formats[] = '%s';

    $insert['created_at'] = $now;
    $formats[] = '%s';

    global $wpdb;
    $result = $wpdb->insert($table, $insert, $formats);
    if ($result === false) {
        return new WP_Error('mj_regmgr_attendance_insert_failed', __('Impossible de créer une inscription automatique.', 'mj-member'));
    }

    return (int) $wpdb->insert_id;
}

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
        $error_code = $result->get_error_code();

        if ($error_code === 'mj_event_attendance_missing_registration') {
            if ($status === '') {
                wp_send_json_success(array(
                    'message' => __('Présence mise à jour.', 'mj-member'),
                ));
                return;
            }

            $event = MjEvents::find($event_id);
            if ($event && mj_regmgr_event_allows_attendance_without_registration($event)) {
                $registration_id = mj_regmgr_ensure_attendance_registration($event, $member_id);
                if (is_wp_error($registration_id)) {
                    wp_send_json_error(array('message' => $registration_id->get_error_message()));
                    return;
                }

                $result = MjEventAttendance::record($event_id, $member_id, $occurrence, $status, array(
                    'recorded_by' => $auth['member_id'],
                    'registration_id' => $registration_id,
                ));
            }
        }

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
            return;
        }
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

    $event = MjEvents::find($event_id);
    $allow_virtual_registrations = mj_regmgr_event_allows_attendance_without_registration($event);

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
            $error_code = $result->get_error_code();

            if ($error_code === 'mj_event_attendance_missing_registration') {
                if ($status === '') {
                    $success++;
                    continue;
                }

                if ($allow_virtual_registrations && $event) {
                    $registration_id = mj_regmgr_ensure_attendance_registration($event, $member_id);
                    if (is_wp_error($registration_id)) {
                        $errors++;
                        continue;
                    }

                    $result = MjEventAttendance::record($event_id, $member_id, $occurrence, $status, array(
                        'recorded_by' => $auth['member_id'],
                        'registration_id' => $registration_id,
                    ));
                }
            }

            if (is_wp_error($result)) {
                $errors++;
                continue;
            }
        }

        $success++;
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
    $guardian_id = isset($_POST['guardianId']) ? (int) $_POST['guardianId'] : 0;

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

    if ($guardian_id > 0) {
        $guardian = MjMembers::getById($guardian_id);
        if (!$guardian || $guardian->role !== MjRoles::TUTEUR) {
            wp_send_json_error(array('message' => __('Le tuteur spécifié est introuvable ou invalide.', 'mj-member')));
            return;
        }

        if ($role !== MjRoles::JEUNE) {
            $role = MjRoles::JEUNE;
            $data['role'] = $role;
        }

        $data['guardian_id'] = $guardian_id;
        $data['is_autonomous'] = 0;
    }

    // Ajouter la date de naissance si fournie
    if ($birth_date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $birth_date)) {
        $data['birth_date'] = $birth_date;
    }

    if ($email !== '') {
        // Check if email already exists
        $existing = MjMembers::getByEmail($email);
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
            'guardianId' => $guardian_id > 0 ? $guardian_id : ($member->guardian_id ?? 0),
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

function mj_regmgr_get_schedule_weekdays() {
    return array(
        'monday'    => __('Lundi', 'mj-member'),
        'tuesday'   => __('Mardi', 'mj-member'),
        'wednesday' => __('Mercredi', 'mj-member'),
        'thursday'  => __('Jeudi', 'mj-member'),
        'friday'    => __('Vendredi', 'mj-member'),
        'saturday'  => __('Samedi', 'mj-member'),
        'sunday'    => __('Dimanche', 'mj-member'),
    );
}

function mj_regmgr_get_schedule_month_ordinals() {
    return array(
        'first'  => __('1er', 'mj-member'),
        'second' => __('2e', 'mj-member'),
        'third'  => __('3e', 'mj-member'),
        'fourth' => __('4e', 'mj-member'),
        'last'   => __('Dernier', 'mj-member'),
    );
}

function mj_regmgr_normalize_hex_color($value) {
    if (is_string($value)) {
        $candidate = trim($value);
    } elseif (is_object($value) && method_exists($value, '__toString')) {
        $candidate = trim((string) $value);
    } else {
        $candidate = trim((string) $value);
    }

    if ($candidate === '') {
        return '';
    }

    if ($candidate[0] !== '#') {
        $candidate = '#' . $candidate;
    }

    $sanitized = sanitize_hex_color($candidate);
    if (!is_string($sanitized) || $sanitized === '') {
        return '';
    }

    $sanitized = strtoupper($sanitized);
    if (strlen($sanitized) === 4) {
        return '#' . $sanitized[1] . $sanitized[1] . $sanitized[2] . $sanitized[2] . $sanitized[3] . $sanitized[3];
    }

    return $sanitized;
}

function mj_regmgr_decode_json_field($value) {
    if (is_array($value)) {
        return $value;
    }

    if (is_string($value) && $value !== '') {
        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    return array();
}

function mj_regmgr_format_event_datetime($value) {
    if (empty($value) || $value === '0000-00-00 00:00:00') {
        return '';
    }

    $timezone = wp_timezone();

    if ($value instanceof \DateTimeInterface) {
        $datetime = new DateTime($value->format('Y-m-d H:i:s'), $timezone);
    } else {
        $datetime = date_create((string) $value, $timezone);
    }

    if (!$datetime) {
        return '';
    }

    $datetime->setTimezone($timezone);

    return $datetime->format('Y-m-d\TH:i');
}

function mj_regmgr_parse_event_datetime($value) {
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    $normalized = str_replace('T', ' ', $value);
    $timezone = wp_timezone();

    $formats = array('Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d');
    foreach ($formats as $format) {
        $datetime = DateTime::createFromFormat($format, $normalized, $timezone);
        if ($datetime instanceof DateTime) {
            if ($format === 'Y-m-d') {
                $datetime->setTime(0, 0, 0);
            }
            return $datetime->format('Y-m-d H:i:s');
        }
    }

    $timestamp = strtotime($normalized);
    if ($timestamp === false) {
        return '';
    }

    return wp_date('Y-m-d H:i:s', $timestamp, $timezone);
}

function mj_regmgr_parse_recurrence_until($value, $end_time, \DateTimeZone $timezone) {
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    $time_part = trim((string) $end_time);
    if ($time_part === '') {
        $time_part = '23:59';
    }

    $datetime = DateTime::createFromFormat('Y-m-d H:i', $value . ' ' . $time_part, $timezone);
    if ($datetime instanceof DateTime) {
        return $datetime->format('Y-m-d H:i:s');
    }

    $datetime = DateTime::createFromFormat('Y-m-d', $value, $timezone);
    if ($datetime instanceof DateTime) {
        $datetime->setTime(23, 59, 0);
        return $datetime->format('Y-m-d H:i:s');
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return '';
    }

    return wp_date('Y-m-d H:i:s', $timestamp, $timezone);
}

function mj_regmgr_events_supports_primary_animateur() {
    static $supported = null;

    if ($supported !== null) {
        return $supported;
    }

    if (!function_exists('mj_member_column_exists')) {
        $supported = false;
        return $supported;
    }

    $table = mj_member_get_events_table_name();
    $supported = mj_member_column_exists($table, 'animateur_id');

    return $supported;
}

function mj_regmgr_prepare_event_form_values($event, array $schedule_weekdays, array $schedule_month_ordinals) {
    $defaults = MjEvents::get_default_values();

    $form_values = $defaults;
    $form_values['accent_color'] = isset($defaults['accent_color']) ? $defaults['accent_color'] : '';
    $form_values['emoji'] = isset($defaults['emoji']) ? $defaults['emoji'] : '';
    $form_values['animateur_ids'] = array();
    $form_values['volunteer_ids'] = array();
    $form_values['schedule_mode'] = isset($defaults['schedule_mode']) ? $defaults['schedule_mode'] : 'fixed';
    $form_values['schedule_payload'] = array();
    $form_values['schedule_series_items'] = array();
    $form_values['schedule_show_date_range'] = false;
    $form_values['schedule_weekday_times'] = array();
    $form_values['schedule_exceptions'] = array();
    $form_values['occurrence_selection_mode'] = isset($defaults['occurrence_selection_mode']) ? $defaults['occurrence_selection_mode'] : 'member_choice';
    $form_values['recurrence_until'] = '';
    $form_values['schedule_recurring_start_date'] = '';
    $form_values['schedule_recurring_start_time'] = '';
    $form_values['schedule_recurring_end_time'] = '';
    $form_values['schedule_recurring_frequency'] = 'weekly';
    $form_values['schedule_recurring_interval'] = 1;
    $form_values['schedule_recurring_weekdays'] = array();
    $form_values['schedule_recurring_month_ordinal'] = 'first';
    $form_values['schedule_recurring_month_weekday'] = 'saturday';
    $form_values['schedule_fixed_date'] = '';
    $form_values['schedule_fixed_start_time'] = '';
    $form_values['schedule_fixed_end_time'] = '';
    $form_values['schedule_range_start'] = '';
    $form_values['schedule_range_end'] = '';
    $form_values['article_cat'] = 0;
    $form_values['registration_payload'] = array();
    $form_values['registration_is_free_participation'] = !empty($defaults['free_participation']);
    $form_values['free_participation'] = !empty($defaults['free_participation']);
    $form_values['attendance_show_all_members'] = false;

    if ($event) {
        $accent_color = mj_regmgr_normalize_hex_color(isset($event->accent_color) ? $event->accent_color : '');
        $occurrence_mode = isset($event->occurrence_selection_mode) ? sanitize_key((string) $event->occurrence_selection_mode) : 'member_choice';
        if (!in_array($occurrence_mode, array('member_choice', 'all_occurrences'), true)) {
            $occurrence_mode = 'member_choice';
        }

        $raw_emoji = isset($event->emoji) ? (string) $event->emoji : '';
        $sanitized_emoji = $raw_emoji;
        if ($sanitized_emoji === '' && $raw_emoji !== '') {
            $sanitized_emoji = $raw_emoji;
        }

        $form_values = array_merge($form_values, array(
            'title' => isset($event->title) ? (string) $event->title : '',
            'status' => isset($event->status) ? (string) $event->status : $form_values['status'],
            'type' => isset($event->type) ? (string) $event->type : $form_values['type'],
            'accent_color' => $accent_color,
            'emoji' => $sanitized_emoji,
            'cover_id' => isset($event->cover_id) ? (int) $event->cover_id : 0,
            'article_id' => isset($event->article_id) ? (int) $event->article_id : 0,
            'location_id' => isset($event->location_id) ? (int) $event->location_id : 0,
            'allow_guardian_registration' => !empty($event->allow_guardian_registration),
            'requires_validation' => isset($event->requires_validation) ? !empty($event->requires_validation) : true,
            'description' => isset($event->description) ? (string) $event->description : '',
            'age_min' => isset($event->age_min) ? (int) $event->age_min : (int) $form_values['age_min'],
            'age_max' => isset($event->age_max) ? (int) $event->age_max : (int) $form_values['age_max'],
            'date_debut' => mj_regmgr_format_event_datetime(isset($event->date_debut) ? $event->date_debut : ''),
            'date_fin' => mj_regmgr_format_event_datetime(isset($event->date_fin) ? $event->date_fin : ''),
            'date_fin_inscription' => mj_regmgr_format_event_datetime(isset($event->date_fin_inscription) ? $event->date_fin_inscription : ''),
            'prix' => number_format(isset($event->prix) ? (float) $event->prix : 0.0, 2, '.', ''),
            'schedule_mode' => isset($event->schedule_mode) ? sanitize_key((string) $event->schedule_mode) : 'fixed',
            'occurrence_selection_mode' => $occurrence_mode,
            'recurrence_until' => (!empty($event->recurrence_until) && strtotime($event->recurrence_until)) ? date_i18n('Y-m-d', strtotime($event->recurrence_until)) : '',
            'capacity_total' => isset($event->capacity_total) ? (int) $event->capacity_total : 0,
            'capacity_waitlist' => isset($event->capacity_waitlist) ? (int) $event->capacity_waitlist : 0,
            'capacity_notify_threshold' => isset($event->capacity_notify_threshold) ? (int) $event->capacity_notify_threshold : 0,
            'occurrenceGenerator' => mj_regmgr_extract_occurrence_generator_from_event($event),
            'free_participation' => !empty($event->free_participation),
            'registration_is_free_participation' => !empty($event->free_participation),
        ));

        $form_values['registration_payload'] = mj_regmgr_decode_json_field(isset($event->registration_payload) ? $event->registration_payload : array());
        $form_values['attendance_show_all_members'] = !empty($form_values['registration_payload']['attendance_show_all_members']);
        if (!$form_values['attendance_show_all_members'] && isset($event->attendance_show_all_members)) {
            $form_values['attendance_show_all_members'] = !empty($event->attendance_show_all_members);
        }

        $animateur_ids = class_exists(MjEventAnimateurs::class) ? MjEventAnimateurs::get_ids_by_event((int) $event->id) : array();
        if (empty($animateur_ids) && isset($event->animateur_id) && (int) $event->animateur_id > 0) {
            $animateur_ids = array((int) $event->animateur_id);
        }
        $form_values['animateur_ids'] = array_values(array_unique(array_map('intval', $animateur_ids)));
        $form_values['animateur_id'] = !empty($form_values['animateur_ids']) ? (int) $form_values['animateur_ids'][0] : 0;

        $volunteer_ids = class_exists(MjEventVolunteers::class) ? MjEventVolunteers::get_ids_by_event((int) $event->id) : array();
        $form_values['volunteer_ids'] = array_values(array_unique(array_map('intval', $volunteer_ids)));

        $form_values = mj_regmgr_fill_schedule_values($event, $form_values, $schedule_weekdays, $schedule_month_ordinals);

    } else {
        $timezone = wp_timezone();
        $now = current_time('timestamp');
        $default_start = $now + 21 * DAY_IN_SECONDS;
        $default_end = $default_start + 2 * HOUR_IN_SECONDS;

        $form_values['date_debut'] = wp_date('Y-m-d\TH:i', $default_start, $timezone);
        $form_values['date_fin'] = wp_date('Y-m-d\TH:i', $default_end, $timezone);
        $form_values['schedule_fixed_date'] = substr($form_values['date_debut'], 0, 10);
        $form_values['schedule_fixed_start_time'] = substr($form_values['date_debut'], 11, 5);
        $form_values['schedule_fixed_end_time'] = substr($form_values['date_fin'], 11, 5);
        $form_values['schedule_range_start'] = $form_values['date_debut'];
        $form_values['schedule_range_end'] = $form_values['date_fin'];
    }

    return $form_values;
}

function mj_regmgr_fill_schedule_values($event, array $form_values, array $schedule_weekdays, array $schedule_month_ordinals) {
    $payload = array();
    if (isset($event->schedule_payload)) {
        $payload = mj_regmgr_decode_json_field($event->schedule_payload);
    }

    $schedule_mode = isset($form_values['schedule_mode']) ? sanitize_key((string) $form_values['schedule_mode']) : 'fixed';
    if (!in_array($schedule_mode, array('fixed', 'range', 'recurring', 'series'), true)) {
        $schedule_mode = 'fixed';
    }
    $form_values['schedule_mode'] = $schedule_mode;
    $form_values['schedule_payload'] = $payload;
    $form_values['schedule_series_items'] = array();
    $form_values['schedule_weekday_times'] = array();
    $form_values['schedule_show_date_range'] = false;

    $default_start_time = $form_values['date_debut'] !== '' ? substr($form_values['date_debut'], 11, 5) : '';
    $default_end_time = $form_values['date_fin'] !== '' ? substr($form_values['date_fin'], 11, 5) : '';
    $default_date = $form_values['date_debut'] !== '' ? substr($form_values['date_debut'], 0, 10) : '';

    $form_values['schedule_fixed_date'] = $default_date;
    $form_values['schedule_fixed_start_time'] = $default_start_time;
    $form_values['schedule_fixed_end_time'] = $default_end_time;
    $form_values['schedule_range_start'] = $form_values['date_debut'];
    $form_values['schedule_range_end'] = $form_values['date_fin'];

    if ($schedule_mode === 'recurring') {
        $frequency = isset($payload['frequency']) ? sanitize_key($payload['frequency']) : 'weekly';
        if (!in_array($frequency, array('weekly', 'monthly'), true)) {
            $frequency = 'weekly';
        }

        $form_values['schedule_recurring_frequency'] = $frequency;
        $form_values['schedule_recurring_interval'] = isset($payload['interval']) ? max(1, (int) $payload['interval']) : 1;
        $form_values['schedule_recurring_start_date'] = isset($payload['start_date']) ? (string) $payload['start_date'] : $default_date;
        $form_values['schedule_recurring_start_time'] = isset($payload['start_time']) ? (string) $payload['start_time'] : $default_start_time;
        $form_values['schedule_recurring_end_time'] = isset($payload['end_time']) ? (string) $payload['end_time'] : $default_end_time;
        $form_values['schedule_show_date_range'] = !empty($payload['show_date_range']);
        $form_values['schedule_exceptions'] = mj_regmgr_sanitize_recurrence_exceptions(isset($payload['exceptions']) ? $payload['exceptions'] : array());

        if (!isset($payload['until']) || !is_string($payload['until']) || $payload['until'] === '') {
            $until_source = isset($form_values['recurrence_until']) ? (string) $form_values['recurrence_until'] : '';
            if ($until_source !== '') {
                $timezone = wp_timezone();
                $until_value = mj_regmgr_parse_recurrence_until($until_source, $form_values['schedule_recurring_end_time'], $timezone);
                if ($until_value !== '') {
                    $payload['until'] = $until_value;
                    $form_values['schedule_payload'] = $payload;
                }
            }
        } else {
            $form_values['schedule_payload'] = $payload;
        }

        if ($frequency === 'weekly') {
            $weekdays = array();
            if (isset($payload['weekdays']) && is_array($payload['weekdays'])) {
                foreach ($payload['weekdays'] as $weekday) {
                    $weekday = sanitize_key($weekday);
                    if (isset($schedule_weekdays[$weekday])) {
                        $weekdays[$weekday] = $weekday;
                    }
                }
            }
            $form_values['schedule_recurring_weekdays'] = array_values($weekdays);

            $weekday_times = array();
            if (isset($payload['weekday_times']) && is_array($payload['weekday_times'])) {
                foreach ($payload['weekday_times'] as $weekday_key => $time_info) {
                    $weekday_key = sanitize_key($weekday_key);
                    if (!isset($schedule_weekdays[$weekday_key]) || !is_array($time_info)) {
                        continue;
                    }
                    $weekday_times[$weekday_key] = array(
                        'start' => isset($time_info['start']) ? (string) $time_info['start'] : '',
                        'end' => isset($time_info['end']) ? (string) $time_info['end'] : '',
                    );
                }
            }
            $form_values['schedule_weekday_times'] = $weekday_times;
            $form_values['schedule_recurring_month_ordinal'] = 'first';
            $form_values['schedule_recurring_month_weekday'] = 'saturday';
        } else {
            $ordinal = isset($payload['ordinal']) ? sanitize_key($payload['ordinal']) : 'first';
            if (!isset($schedule_month_ordinals[$ordinal])) {
                $ordinal = 'first';
            }
            $weekday = isset($payload['weekday']) ? sanitize_key($payload['weekday']) : 'saturday';
            if (!isset($schedule_weekdays[$weekday])) {
                $weekday = 'saturday';
            }

            $form_values['schedule_recurring_weekdays'] = array();
            $form_values['schedule_recurring_month_ordinal'] = $ordinal;
            $form_values['schedule_recurring_month_weekday'] = $weekday;
            $form_values['schedule_weekday_times'] = array();
        }

        $form_values['schedule_range_start'] = '';
        $form_values['schedule_range_end'] = '';
    } elseif ($schedule_mode === 'range') {
        $form_values['schedule_range_start'] = isset($payload['start']) ? (string) $payload['start'] : $form_values['date_debut'];
        $form_values['schedule_range_end'] = isset($payload['end']) ? (string) $payload['end'] : $form_values['date_fin'];
        $form_values['schedule_exceptions'] = array();
    } elseif ($schedule_mode === 'series') {
        $series_items = array();
        if (isset($payload['items']) && is_array($payload['items'])) {
            foreach ($payload['items'] as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $date = isset($item['date']) ? (string) $item['date'] : '';
                $start_time = isset($item['start_time']) ? (string) $item['start_time'] : '';
                $end_time = isset($item['end_time']) ? (string) $item['end_time'] : '';
                if ($date === '' || $start_time === '') {
                    continue;
                }
                $series_items[] = array(
                    'date' => $date,
                    'start_time' => $start_time,
                    'end_time' => $end_time,
                );
            }
        }
        $form_values['schedule_series_items'] = $series_items;
        $form_values['schedule_range_start'] = '';
        $form_values['schedule_range_end'] = '';
        $form_values['schedule_exceptions'] = array();
    }

    if (!empty($form_values['date_debut'])) {
        $form_values['schedule_fixed_date'] = substr($form_values['date_debut'], 0, 10);
        $form_values['schedule_fixed_start_time'] = substr($form_values['date_debut'], 11, 5);
    }
    if (!empty($form_values['date_fin'])) {
        $form_values['schedule_fixed_end_time'] = substr($form_values['date_fin'], 11, 5);
    }

    return $form_values;
}

function mj_regmgr_collect_event_editor_assets($event, array &$form_values) {
    $article_categories = get_categories(array('hide_empty' => false));
    if (!is_array($article_categories)) {
        $article_categories = array();
    }

    $selected_cat = isset($form_values['article_cat']) ? (int) $form_values['article_cat'] : 0;
    if ($selected_cat <= 0 && !empty($form_values['article_id'])) {
        $article_terms = get_the_category((int) $form_values['article_id']);
        if (!empty($article_terms)) {
            $selected_cat = (int) $article_terms[0]->term_id;
        }
    }
    if ($selected_cat <= 0 && !empty($article_categories)) {
        $selected_cat = (int) $article_categories[0]->term_id;
    }
    $form_values['article_cat'] = $selected_cat;

    $article_args = array(
        'numberposts' => 50,
        'post_status' => 'publish',
        'orderby' => 'date',
        'order' => 'DESC',
    );
    if ($selected_cat > 0) {
        $article_args['cat'] = $selected_cat;
    }
    $articles = get_posts($article_args);
    if (!is_array($articles)) {
        $articles = array();
    }
    if (!empty($form_values['article_id'])) {
        $article_id = (int) $form_values['article_id'];
        $found = false;
        foreach ($articles as $article) {
            if (!is_object($article) || !isset($article->ID)) {
                continue;
            }
            if ((int) $article->ID === $article_id) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            $current_article = get_post($article_id);
            if ($current_article && $current_article->post_status === 'publish') {
                array_unshift($articles, $current_article);
            }
        }
    }

    $locations = class_exists(MjEventLocations::class) ? MjEventLocations::get_all(array('orderby' => 'name', 'order' => 'ASC')) : array();
    if (!is_array($locations)) {
        $locations = array();
    }
    $location_ids = array();
    foreach ($locations as $location) {
        if (is_object($location) && isset($location->id)) {
            $location_ids[(int) $location->id] = true;
        }
    }

    $animateur_filters = array('role' => MjRoles::ANIMATEUR);
    $animateurs = MjMembers::getAll(0, 0, 'last_name', 'ASC', '', $animateur_filters);
    if (!is_array($animateurs)) {
        $animateurs = array();
    }
    $available_animateur_ids = array();
    foreach ($animateurs as $animateur) {
        if (is_object($animateur) && isset($animateur->id)) {
            $available_animateur_ids[(int) $animateur->id] = true;
        }
    }

    $volunteer_filters = array('is_volunteer' => 1);
    $volunteers = MjMembers::getAll(0, 0, 'last_name', 'ASC', '', $volunteer_filters);
    if (!is_array($volunteers)) {
        $volunteers = array();
    }
    $available_volunteer_ids = array();
    foreach ($volunteers as $volunteer) {
        if (is_object($volunteer) && isset($volunteer->id)) {
            $available_volunteer_ids[(int) $volunteer->id] = true;
        }
    }

    return array(
        'article_categories' => $article_categories,
        'articles' => $articles,
        'locations' => $locations,
        'animateurs' => $animateurs,
        'volunteers' => $volunteers,
        'available_animateur_ids' => $available_animateur_ids,
        'available_volunteer_ids' => $available_volunteer_ids,
        'location_ids' => $location_ids,
        'animateur_assignments_ready' => class_exists(MjEventAnimateurs::class) ? MjEventAnimateurs::is_ready() : false,
        'volunteer_assignments_ready' => class_exists(MjEventVolunteers::class) ? MjEventVolunteers::is_ready() : false,
        'animateur_column_supported' => mj_regmgr_events_supports_primary_animateur(),
    );
}

function mj_regmgr_user_can_manage_locations($auth) {
    if (current_user_can(Config::capability())) {
        return true;
    }
    if (is_array($auth) && !empty($auth['is_coordinateur'])) {
        return true;
    }
    return false;
}

function mj_regmgr_build_location_lookup_query(array $location) {
    if (!empty($location['map_query'])) {
        return (string) $location['map_query'];
    }

    $latitude = isset($location['latitude']) && $location['latitude'] !== null
        ? trim((string) $location['latitude'])
        : '';
    $longitude = isset($location['longitude']) && $location['longitude'] !== null
        ? trim((string) $location['longitude'])
        : '';

    if ($latitude !== '' && $longitude !== '') {
        return $latitude . ',' . $longitude;
    }

    $parts = array();
    if (!empty($location['address_line'])) {
        $parts[] = (string) $location['address_line'];
    }
    if (!empty($location['postal_code'])) {
        $parts[] = (string) $location['postal_code'];
    }
    if (!empty($location['city'])) {
        $parts[] = (string) $location['city'];
    }
    if (!empty($location['country'])) {
        $parts[] = (string) $location['country'];
    }

    if (empty($parts)) {
        return '';
    }

    return implode(', ', $parts);
}

function mj_regmgr_build_location_map_preview_url(array $location) {
    $query = mj_regmgr_build_location_lookup_query($location);
    if ($query === '') {
        return '';
    }

    return 'https://maps.google.com/maps?q=' . rawurlencode($query) . '&output=embed';
}

function mj_regmgr_build_location_map_link(array $location) {
    $query = mj_regmgr_build_location_lookup_query($location);
    if ($query === '') {
        return '';
    }

    return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($query);
}

function mj_regmgr_format_location_payload($location) {
    if ($location instanceof EventLocationData) {
        $location_data = $location->toArray();
    } elseif (is_object($location)) {
        $location_data = get_object_vars($location);
    } else {
        $location_data = (array) $location;
    }

    $id = isset($location_data['id']) ? (int) $location_data['id'] : 0;
    $name = isset($location_data['name']) ? sanitize_text_field((string) $location_data['name']) : '';
    $address = isset($location_data['address_line']) ? sanitize_text_field((string) $location_data['address_line']) : '';
    $postal_code = isset($location_data['postal_code']) ? sanitize_text_field((string) $location_data['postal_code']) : '';
    $city = isset($location_data['city']) ? sanitize_text_field((string) $location_data['city']) : '';
    $country = isset($location_data['country']) ? sanitize_text_field((string) $location_data['country']) : '';
    $icon = isset($location_data['icon']) ? sanitize_text_field((string) $location_data['icon']) : '';
    $cover_id = isset($location_data['cover_id']) ? (int) $location_data['cover_id'] : 0;
    $cover_url = '';
    if ($cover_id > 0 && function_exists('wp_get_attachment_image_url')) {
        $cover_candidate = wp_get_attachment_image_url($cover_id, 'medium');
        if (is_string($cover_candidate)) {
            $cover_url = $cover_candidate;
        }
    }
    $cover_admin_url = '';
    if ($id > 0) {
        $cover_admin_url = add_query_arg(
            array(
                'page' => 'mj_locations',
                'action' => 'edit',
                'location' => $id,
            ),
            admin_url('admin.php')
        );
    }
    $map_query = isset($location_data['map_query']) ? sanitize_text_field((string) $location_data['map_query']) : '';
    $latitude = isset($location_data['latitude']) && $location_data['latitude'] !== null ? trim((string) $location_data['latitude']) : '';
    $longitude = isset($location_data['longitude']) && $location_data['longitude'] !== null ? trim((string) $location_data['longitude']) : '';
    $notes = isset($location_data['notes']) ? sanitize_textarea_field((string) $location_data['notes']) : '';

    $normalized = array(
        'id' => $id,
        'name' => $name,
        'address_line' => $address,
        'postal_code' => $postal_code,
        'city' => $city,
        'country' => $country,
        'icon' => $icon,
        'cover_id' => $cover_id,
        'map_query' => $map_query,
        'latitude' => $latitude,
        'longitude' => $longitude,
        'notes' => $notes,
    );

    $map_embed = class_exists(MjEventLocations::class) ? MjEventLocations::build_map_embed_src($location_data) : '';
    $formatted_address = class_exists(MjEventLocations::class) ? MjEventLocations::format_address($location_data) : '';
    $map_preview = $map_embed !== '' ? $map_embed : mj_regmgr_build_location_map_preview_url($normalized);
    $map_link = mj_regmgr_build_location_map_link($normalized);

    $label = $name;
    if ($label === '' && $id > 0) {
        /* translators: %d: location identifier */
        $label = sprintf(__('Lieu #%d', 'mj-member'), $id);
    }
    if ($city !== '') {
        $label .= $label !== '' ? ' (' . $city . ')' : $city;
    }

    $option = null;
    if ($id > 0) {
        $option = array(
            'id' => $id,
            'label' => $label,
            'attributes' => array(
                'data-address' => $formatted_address ? $formatted_address : '',
                'data-map' => $map_preview,
                'data-notes' => $notes,
                'data-city' => $city,
                'data-country' => $country,
                'data-icon' => $icon,
                'data-cover-id' => $cover_id > 0 ? (string) $cover_id : '',
                'data-cover-src' => $cover_url !== '' ? esc_url_raw($cover_url) : '',
                'data-cover-admin' => $cover_admin_url !== '' ? esc_url_raw($cover_admin_url) : '',
            ),
        );
    }

    $normalized['formattedAddress'] = $formatted_address ? $formatted_address : '';
    $normalized['mapEmbed'] = $map_preview;
    $normalized['mapLink'] = $map_link;
    $normalized['coverId'] = $cover_id;
    $normalized['coverUrl'] = $cover_url !== '' ? esc_url_raw($cover_url) : '';
    $normalized['coverAdminUrl'] = $cover_admin_url !== '' ? esc_url_raw($cover_admin_url) : '';
    $normalized['cover_url'] = $cover_url !== '' ? esc_url_raw($cover_url) : '';
    $normalized['cover_admin_url'] = $cover_admin_url !== '' ? esc_url_raw($cover_admin_url) : '';

    return array(
        'location' => $normalized,
        'option' => $option,
    );
}

function mj_regmgr_get_location() {
    $auth = mj_regmgr_verify_request();
    if (!$auth) {
        return;
    }

    if (!mj_regmgr_user_can_manage_locations($auth)) {
        wp_send_json_error(array('message' => __('Permissions insuffisantes pour gérer les lieux.', 'mj-member')), 403);
        return;
    }

    if (!class_exists(MjEventLocations::class)) {
        wp_send_json_error(array('message' => __('Gestion des lieux indisponible.', 'mj-member')), 500);
        return;
    }

    $location_id = isset($_POST['locationId']) ? (int) $_POST['locationId'] : 0;

    if ($location_id > 0) {
        $location = MjEventLocations::find($location_id);
        if (!$location) {
            wp_send_json_error(array('message' => __('Lieu introuvable.', 'mj-member')), 404);
            return;
        }
        $payload = mj_regmgr_format_location_payload($location);
    } else {
        $defaults = MjEventLocations::get_default_values();
        $defaults['id'] = 0;
        $payload = mj_regmgr_format_location_payload($defaults);
    }

    wp_send_json_success($payload);
}

function mj_regmgr_save_location() {
    $auth = mj_regmgr_verify_request();
    if (!$auth) {
        return;
    }

    if (!mj_regmgr_user_can_manage_locations($auth)) {
        wp_send_json_error(array('message' => __('Permissions insuffisantes pour gérer les lieux.', 'mj-member')), 403);
        return;
    }

    if (!class_exists(MjEventLocations::class)) {
        wp_send_json_error(array('message' => __('Gestion des lieux indisponible.', 'mj-member')), 500);
        return;
    }

    $location_id = isset($_POST['locationId']) ? (int) $_POST['locationId'] : 0;
    $raw_data = isset($_POST['data']) ? wp_unslash((string) $_POST['data']) : '';
    $payload = json_decode($raw_data, true);
    if (!is_array($payload)) {
        wp_send_json_error(array('message' => __('Format de données invalide.', 'mj-member')), 400);
        return;
    }

    $allowed_fields = array('name', 'slug', 'address_line', 'postal_code', 'city', 'country', 'icon', 'cover_id', 'map_query', 'latitude', 'longitude', 'notes');
    $data = array();
    foreach ($allowed_fields as $field) {
        if (array_key_exists($field, $payload)) {
            $data[$field] = $payload[$field];
        }
    }

    if ($location_id > 0) {
        $result = MjEventLocations::update($location_id, $data);
    } else {
        $result = MjEventLocations::create($data);
    }

    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()), 400);
        return;
    }

    $new_id = $location_id > 0 ? $location_id : (int) $result;
    $location = MjEventLocations::find($new_id);
    if (!$location) {
        wp_send_json_error(array('message' => __('Lieu introuvable après enregistrement.', 'mj-member')), 500);
        return;
    }

    $payload = mj_regmgr_format_location_payload($location);
    $message = $location_id > 0 ? __('Lieu mis à jour.', 'mj-member') : __('Lieu créé.', 'mj-member');

    wp_send_json_success(array(
        'message' => $message,
        'location' => $payload['location'],
        'option' => $payload['option'],
    ));
}

function mj_regmgr_sanitize_weekday_times($weekday_times, array $schedule_weekdays) {
    $sanitized = array();

    if (!is_array($weekday_times)) {
        return $sanitized;
    }

    foreach ($weekday_times as $key => $time_info) {
        $key = sanitize_key($key);
        if (!isset($schedule_weekdays[$key]) || !is_array($time_info)) {
            continue;
        }

        $sanitized[$key] = array(
            'start' => isset($time_info['start']) ? sanitize_text_field($time_info['start']) : '',
            'end' => isset($time_info['end']) ? sanitize_text_field($time_info['end']) : '',
        );
    }

    return $sanitized;
}

function mj_regmgr_sanitize_recurrence_exceptions($value) {
    if (is_string($value) && $value !== '') {
        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            $value = $decoded;
        }
    }

    if ($value instanceof \Traversable) {
        $value = iterator_to_array($value);
    }

    if (!is_array($value)) {
        return array();
    }

    $timezone = wp_timezone();
    $normalized = array();

    foreach ($value as $item) {
        if ($item === null) {
            continue;
        }

        $candidate = $item;
        if (is_object($candidate)) {
            $candidate = get_object_vars($candidate);
        }

        $date_raw = '';
        $reason_raw = '';

        if (is_array($candidate)) {
            if (isset($candidate['date'])) {
                $date_raw = $candidate['date'];
            } elseif (isset($candidate[0])) {
                $date_raw = $candidate[0];
            }
            if (isset($candidate['reason'])) {
                $reason_raw = $candidate['reason'];
            }
        } else {
            $date_raw = $candidate;
        }

        if (!is_scalar($date_raw)) {
            continue;
        }

        $raw_date = sanitize_text_field((string) $date_raw);
        if ($raw_date === '') {
            continue;
        }

        $date = \DateTime::createFromFormat('Y-m-d', $raw_date, $timezone);
        if (!$date instanceof \DateTime) {
            continue;
        }

        $key = $date->format('Y-m-d');

        $reason_value = '';
        if (is_scalar($reason_raw)) {
            $reason_value = sanitize_text_field((string) $reason_raw);
            if ($reason_value !== '') {
                $reason_value = wp_strip_all_tags($reason_value, false);
                if ($reason_value !== '') {
                    if (function_exists('mb_substr')) {
                        $reason_value = mb_substr($reason_value, 0, 200);
                    } else {
                        $reason_value = substr($reason_value, 0, 200);
                    }
                    $reason_value = trim($reason_value);
                }
            }
        }

        $entry = array('date' => $key);
        if ($reason_value !== '') {
            $entry['reason'] = $reason_value;
        }

        $normalized[$key] = $entry;

        if (count($normalized) >= 365) {
            break;
        }
    }

    return array_values($normalized);
}

/**
 * @param array<string,mixed> $form_values
 * @param array<string,mixed> $meta
 * @return array<int,array<string,string>>
 */
function mj_regmgr_resolve_schedule_exceptions(array $form_values, array $meta) {
    if (array_key_exists('schedule_exceptions', $form_values)) {
        return mj_regmgr_sanitize_recurrence_exceptions($form_values['schedule_exceptions']);
    }

    if (isset($meta['scheduleExceptions'])) {
        $from_meta = mj_regmgr_sanitize_recurrence_exceptions($meta['scheduleExceptions']);
        if (!empty($from_meta)) {
            return $from_meta;
        }
    }

    if (
        isset($meta['schedulePayload'])
        && is_array($meta['schedulePayload'])
        && array_key_exists('exceptions', $meta['schedulePayload'])
    ) {
        return mj_regmgr_sanitize_recurrence_exceptions($meta['schedulePayload']['exceptions']);
    }

    return array();
}
/**
 * Builds and validates the payload for updating or creating an event based on form values.
 */
function mj_regmgr_build_event_update_payload($event, array $form_values, array $meta, array $references, array $schedule_weekdays, array $schedule_month_ordinals, array &$errors) {
    $errors = array();

    $existing_generator_plan = mj_regmgr_extract_occurrence_generator_from_event($event);

    $submitted_generator_plan = array();
    $generator_plan_provided = false;

    if (isset($meta['occurrenceGenerator'])) {
        $generator_plan_provided = true;
        $submitted_generator_plan = mj_regmgr_sanitize_occurrence_generator_plan($meta['occurrenceGenerator']);
    } elseif (isset($meta['schedulePayload']) && is_array($meta['schedulePayload'])) {
        $payload_plan = mj_regmgr_extract_occurrence_generator_from_payload($meta['schedulePayload']);
        if (!empty($payload_plan) || array_key_exists('occurrence_generator', $meta['schedulePayload']) || array_key_exists('occurrenceGenerator', $meta['schedulePayload'])) {
            $generator_plan_provided = true;
            $submitted_generator_plan = $payload_plan;
        }
    } elseif (isset($form_values['occurrenceGenerator']) && is_array($form_values['occurrenceGenerator'])) {
        $generator_plan_provided = true;
        $submitted_generator_plan = mj_regmgr_sanitize_occurrence_generator_plan($form_values['occurrenceGenerator']);
    } elseif (isset($form_values['schedule_payload']) && is_array($form_values['schedule_payload'])) {
        if (array_key_exists('occurrence_generator', $form_values['schedule_payload']) || array_key_exists('occurrenceGenerator', $form_values['schedule_payload'])) {
            $payload_plan = mj_regmgr_extract_occurrence_generator_from_payload($form_values['schedule_payload']);
            if (!empty($payload_plan)) {
                $submitted_generator_plan = $payload_plan;
            }
        }
    }

    $title = isset($form_values['title']) ? sanitize_text_field((string) $form_values['title']) : '';
    if ($title === '') {
        $errors[] = __('Le titre est obligatoire.', 'mj-member');
    }

    $status_labels = MjEvents::get_status_labels();
    $status = isset($form_values['status']) ? sanitize_key((string) $form_values['status']) : MjEvents::STATUS_DRAFT;
    if (!isset($status_labels[$status])) {
        $errors[] = __('Le statut sélectionné est invalide.', 'mj-member');
        $status = MjEvents::STATUS_DRAFT;
    }

    $type_labels = MjEvents::get_type_labels();
    $type = isset($form_values['type']) ? sanitize_key((string) $form_values['type']) : MjEvents::TYPE_STAGE;
    if (!isset($type_labels[$type])) {
        $errors[] = __('Le type sélectionné est invalide.', 'mj-member');
        $type = MjEvents::TYPE_STAGE;
    }

    $accent_color = isset($form_values['accent_color']) ? mj_regmgr_normalize_hex_color($form_values['accent_color']) : '';
    $cover_id = isset($form_values['cover_id']) ? (int) $form_values['cover_id'] : 0;
    $description = isset($form_values['description']) ? wp_kses_post($form_values['description']) : '';

    $age_min = isset($form_values['age_min']) ? (int) $form_values['age_min'] : 0;
    $age_max = isset($form_values['age_max']) ? (int) $form_values['age_max'] : 0;
    if ($age_min < 0) {
        $age_min = 0;
    }
    if ($age_max < 0) {
        $age_max = 0;
    }
    if ($age_min > $age_max) {
        $errors[] = __('L\'âge minimum doit être inférieur ou égal à l\'âge maximum.', 'mj-member');
    }

    $capacity_total = isset($form_values['capacity_total']) ? max(0, (int) $form_values['capacity_total']) : 0;
    $capacity_waitlist = isset($form_values['capacity_waitlist']) ? max(0, (int) $form_values['capacity_waitlist']) : 0;
    $capacity_notify_threshold = isset($form_values['capacity_notify_threshold']) ? max(0, (int) $form_values['capacity_notify_threshold']) : 0;
    if ($capacity_total === 0 && $capacity_notify_threshold > 0) {
        $capacity_notify_threshold = 0;
    }
    if ($capacity_total > 0 && $capacity_notify_threshold >= $capacity_total) {
        $errors[] = __('Le seuil d\'alerte doit être inférieur au nombre total de places.', 'mj-member');
    }

    $price = isset($form_values['prix']) ? round((float) $form_values['prix'], 2) : 0.0;

    $schedule_mode = isset($form_values['schedule_mode']) ? sanitize_key((string) $form_values['schedule_mode']) : 'fixed';
    if (!in_array($schedule_mode, array('fixed', 'range', 'recurring', 'series'), true)) {
        $schedule_mode = 'fixed';
    }

    $timezone = wp_timezone();

    $date_debut = '';
    $date_fin = '';
    $schedule_payload = array();
    $series_items_clean = array();
    $recurrence_until_value = '';


    if ($date_debut === '' && !empty($form_values['date_debut'])) {
        $parsed = mj_regmgr_parse_event_datetime($form_values['date_debut']);
        if ($parsed !== '') {
            $date_debut = $parsed;
        }
    }
    if ($date_fin === '' && !empty($form_values['date_fin'])) {
        $parsed = mj_regmgr_parse_event_datetime($form_values['date_fin']);
        if ($parsed !== '') {
            $date_fin = $parsed;
        }
    }

    $date_fin_inscription_raw = isset($form_values['date_fin_inscription']) ? $form_values['date_fin_inscription'] : '';
    $date_fin_inscription = $date_fin_inscription_raw !== '' ? mj_regmgr_parse_event_datetime($date_fin_inscription_raw) : '';

    $location_id = isset($form_values['location_id']) ? (int) $form_values['location_id'] : 0;
    if ($location_id > 0 && (!isset($references['location_ids'][$location_id]) || !$references['location_ids'][$location_id])) {
        $location_id = 0;
    }

    $animateur_ids = array();
    if (isset($form_values['animateur_ids']) && is_array($form_values['animateur_ids'])) {
        foreach ($form_values['animateur_ids'] as $animateur_candidate) {
            $animateur_candidate = (int) $animateur_candidate;
            if ($animateur_candidate <= 0) {
                continue;
            }
            if (!empty($references['available_animateur_ids']) && !isset($references['available_animateur_ids'][$animateur_candidate])) {
                $errors[] = __('Un animateur sélectionné est invalide.', 'mj-member');
                continue;
            }
            $animateur_ids[$animateur_candidate] = $animateur_candidate;
        }
    }
    $animateur_ids = array_values($animateur_ids);

    $volunteer_ids = array();
    if (isset($form_values['volunteer_ids']) && is_array($form_values['volunteer_ids'])) {
        foreach ($form_values['volunteer_ids'] as $volunteer_candidate) {
            $volunteer_candidate = (int) $volunteer_candidate;
            if ($volunteer_candidate <= 0) {
                continue;
            }
            if (!empty($references['available_volunteer_ids']) && !isset($references['available_volunteer_ids'][$volunteer_candidate])) {
                $errors[] = __('Un bénévole sélectionné est invalide.', 'mj-member');
                continue;
            }
            $volunteer_ids[$volunteer_candidate] = $volunteer_candidate;
        }
    }
    $volunteer_ids = array_values($volunteer_ids);

    $primary_animateur_id = !empty($animateur_ids) ? (int) $animateur_ids[0] : 0;

    $allow_guardian_registration = !empty($form_values['allow_guardian_registration']);
    $requires_validation = array_key_exists('requires_validation', $form_values) ? !empty($form_values['requires_validation']) : true;
    $free_participation = !empty($form_values['registration_is_free_participation']) || !empty($form_values['free_participation']);

    $occurrence_selection_mode = isset($form_values['occurrence_selection_mode']) ? sanitize_key((string) $form_values['occurrence_selection_mode']) : 'member_choice';
    if (!in_array($occurrence_selection_mode, array('member_choice', 'all_occurrences'), true)) {
        $occurrence_selection_mode = 'member_choice';
    }

    $article_id = isset($form_values['article_id']) ? (int) $form_values['article_id'] : 0;

    $registration_payload = isset($form_values['registration_payload']) ? $form_values['registration_payload'] : array();
    if (!is_array($registration_payload)) {
        $registration_payload = mj_regmgr_decode_json_field($registration_payload);
    }

    $previous_capacity_total = isset($event->capacity_total) ? (int) $event->capacity_total : 0;
    $previous_capacity_threshold = isset($event->capacity_notify_threshold) ? (int) $event->capacity_notify_threshold : 0;
    $previous_capacity_notified = !empty($event->capacity_notified) ? 1 : 0;

    $capacity_notified_value = ($previous_capacity_total === $capacity_total && $previous_capacity_threshold === $capacity_notify_threshold) ? $previous_capacity_notified : 0;


    $derived_generator_plan = mj_regmgr_derive_generator_plan_from_schedule($schedule_payload);
    if (!empty($derived_generator_plan)) {
        if (!empty($submitted_generator_plan)) {
            $submitted_generator_plan = mj_regmgr_merge_generator_plans($submitted_generator_plan, $derived_generator_plan);
        } elseif (!empty($existing_generator_plan)) {
            $existing_generator_plan = mj_regmgr_merge_generator_plans($existing_generator_plan, $derived_generator_plan);
        } else {
            $submitted_generator_plan = $derived_generator_plan;
            $generator_plan_provided = true;
        }
    }

    if (!is_array($schedule_payload)) {
        $schedule_payload = array();
    }

    if (!empty($submitted_generator_plan)) {
        $schedule_payload['occurrence_generator'] = $submitted_generator_plan;
    } elseif ($generator_plan_provided) {
        unset($schedule_payload['occurrence_generator'], $schedule_payload['occurrenceGenerator']);
    } elseif (!isset($schedule_payload['occurrence_generator']) || !is_array($schedule_payload['occurrence_generator'])) {
        if (!empty($existing_generator_plan)) {
            $schedule_payload['occurrence_generator'] = $existing_generator_plan;
        }
    }

    $payload = array(
        'title' => $title,
        'status' => $status,
        'type' => $type,
        'accent_color' => $accent_color,
        'emoji' => isset($form_values['emoji']) ? sanitize_text_field((string) $form_values['emoji']) : '',
        'cover_id' => $cover_id,
        'description' => $description,
        'age_min' => $age_min,
        'age_max' => $age_max,
        'date_fin_inscription' => $date_fin_inscription !== '' ? $date_fin_inscription : null,
        'prix' => $price,
        'location_id' => $location_id,
        'animateur_id' => !empty($references['animateur_column_supported']) ? ($primary_animateur_id > 0 ? $primary_animateur_id : null) : null,
        'allow_guardian_registration' => $allow_guardian_registration ? 1 : 0,
        'requires_validation' => $requires_validation ? 1 : 0,
        'schedule_mode' => $schedule_mode,
        'schedule_payload' => $schedule_payload,
        'occurrence_selection_mode' => $occurrence_selection_mode,
        'free_participation' => $free_participation ? 1 : 0,
        'recurrence_until' => $recurrence_until_value !== '' ? $recurrence_until_value : null,
        'capacity_total' => $capacity_total,
        'capacity_waitlist' => $capacity_waitlist,
        'capacity_notify_threshold' => $capacity_notify_threshold,
        'capacity_notified' => $capacity_notified_value,
        'article_id' => $article_id,
        'registration_payload' => $registration_payload,
    );

    if (!empty($errors)) {
        return array(
            'payload' => array(),
            'animateur_ids' => array(),
            'volunteer_ids' => array(),
            'values' => $form_values,
        );
    }

    $form_values['schedule_payload'] = $schedule_payload;
    $form_values['schedule_series_items'] = $series_items_clean;
    $form_values['date_debut'] = mj_regmgr_format_event_datetime($date_debut);
    $form_values['date_fin'] = mj_regmgr_format_event_datetime($date_fin);
    $form_values['date_fin_inscription'] = mj_regmgr_format_event_datetime($date_fin_inscription);
    $form_values['recurrence_until'] = $recurrence_until_value !== '' ? date_i18n('Y-m-d', strtotime($recurrence_until_value)) : '';

    return array(
        'payload' => $payload,
        'animateur_ids' => $animateur_ids,
        'volunteer_ids' => $volunteer_ids,
        'values' => $form_values,
    );
}

function mj_regmgr_serialize_event_summary($event) {
    if (!$event) {
        return array();
    }

    $type_labels = MjEvents::get_type_labels();
    $status_labels = MjEvents::get_status_labels();

    $schedule_mode = isset($event->schedule_mode) ? sanitize_key((string) $event->schedule_mode) : 'fixed';
    if ($schedule_mode === '') {
        $schedule_mode = 'fixed';
    }
    $schedule_info = mj_regmgr_build_event_schedule_info($event, $schedule_mode);

    return array(
        'id' => isset($event->id) ? (int) $event->id : 0,
        'title' => isset($event->title) ? (string) $event->title : '',
        'slug' => isset($event->slug) ? (string) $event->slug : '',
        'status' => isset($event->status) ? (string) $event->status : '',
        'statusLabel' => isset($event->status) && isset($status_labels[$event->status]) ? $status_labels[$event->status] : (isset($event->status) ? $event->status : ''),
        'type' => isset($event->type) ? (string) $event->type : '',
        'typeLabel' => isset($event->type) && isset($type_labels[$event->type]) ? $type_labels[$event->type] : (isset($event->type) ? $event->type : ''),
        'dateDebut' => isset($event->date_debut) ? (string) $event->date_debut : '',
        'dateFin' => isset($event->date_fin) ? (string) $event->date_fin : '',
        'occurrenceSelectionMode' => isset($event->occurrence_selection_mode) && $event->occurrence_selection_mode !== '' ? (string) $event->occurrence_selection_mode : 'member_choice',
        'accentColor' => isset($event->accent_color) ? (string) $event->accent_color : '',
        'articleId' => isset($event->article_id) ? (int) $event->article_id : 0,
        'coverId' => isset($event->cover_id) ? (int) $event->cover_id : 0,
        'coverUrl' => mj_regmgr_get_event_cover_url($event, 'medium'),
        'capacityTotal' => isset($event->capacity_total) ? (int) $event->capacity_total : 0,
        'capacityWaitlist' => isset($event->capacity_waitlist) ? (int) $event->capacity_waitlist : 0,
        'prix' => isset($event->prix) ? (float) $event->prix : 0.0,
        'occurrenceGenerator' => mj_regmgr_extract_occurrence_generator_from_event($event),
        'scheduleMode' => $schedule_mode,
        'scheduleSummary' => isset($schedule_info['summary']) ? $schedule_info['summary'] : '',
        'scheduleDetail' => isset($schedule_info['detail']) ? $schedule_info['detail'] : '',
        'occurrenceScheduleSummary' => isset($schedule_info['summary']) ? $schedule_info['summary'] : '',
        'occurrence_schedule_summary' => isset($schedule_info['summary']) ? $schedule_info['summary'] : '',
    );
}

function mj_regmgr_save_event_occurrences() {
    $auth = mj_regmgr_verify_request();
    if (!$auth) {
        return;
    }

    $event_id = isset($_POST['eventId']) ? (int) $_POST['eventId'] : 0;
    if ($event_id <= 0) {
        wp_send_json_error(array('message' => __('ID événement invalide.', 'mj-member')), 400);
        return;
    }

    $event = MjEvents::find($event_id);
    if (!$event) {
        wp_send_json_error(array('message' => __('Événement introuvable.', 'mj-member')), 404);
        return;
    }

    $can_manage = current_user_can(Config::capability()) || !empty($auth['is_coordinateur']);
    if (!$can_manage) {
        if (!class_exists(MjEventAnimateurs::class) || !MjEventAnimateurs::member_is_assigned($event_id, $auth['member_id'])) {
            wp_send_json_error(array('message' => __('Permissions insuffisantes pour modifier les occurrences de cet événement.', 'mj-member')), 403);
            return;
        }
    }

    $existing_schedule_payload = array();
    if (!empty($event->schedule_payload)) {
        $decoded_payload = mj_regmgr_decode_json_field($event->schedule_payload);
        if (is_array($decoded_payload)) {
            $existing_schedule_payload = $decoded_payload;
        }
    }
    $existing_generator_plan = mj_regmgr_extract_occurrence_generator_from_payload($existing_schedule_payload);

    $submitted_schedule_summary = '';
    if (isset($_POST['scheduleSummary'])) {
        $submitted_schedule_summary = trim((string) wp_unslash($_POST['scheduleSummary']));
        if ($submitted_schedule_summary !== '' && function_exists('mb_substr')) {
            $submitted_schedule_summary = mb_substr($submitted_schedule_summary, 0, 600);
        } else {
            $submitted_schedule_summary = substr($submitted_schedule_summary, 0, 600);
        }
        $submitted_schedule_summary = wp_strip_all_tags($submitted_schedule_summary);
    }

    $generator_plan_input = array();
    $generator_plan_provided = false;
    if (isset($_POST['generatorPlan'])) {
        $generator_plan_provided = true;
        $plan_candidate = wp_unslash($_POST['generatorPlan']);
        if (is_string($plan_candidate)) {
            $decoded_plan = json_decode($plan_candidate, true);
            if (is_array($decoded_plan)) {
                $generator_plan_input = $decoded_plan;
            }
        } elseif (is_array($plan_candidate)) {
            $generator_plan_input = $plan_candidate;
        }
    }

    $sanitized_generator_plan = array();
    if ($generator_plan_provided) {
        $sanitized_generator_plan = mj_regmgr_sanitize_occurrence_generator_plan($generator_plan_input);
    }

    $raw_occurrences = array();
    if (isset($_POST['occurrences'])) {
        $payload = $_POST['occurrences'];
        if (is_string($payload)) {
            $decoded = json_decode(wp_unslash($payload), true);
            if (is_array($decoded)) {
                $raw_occurrences = $decoded;
            }
        } elseif (is_array($payload)) {
            foreach ($payload as $value) {
                if (is_array($value)) {
                    $raw_occurrences[] = $value;
                } elseif (is_string($value)) {
                    $decoded_value = json_decode(wp_unslash($value), true);
                    if (is_array($decoded_value)) {
                        $raw_occurrences[] = $decoded_value;
                    }
                }
            }
        }
    }

    if (empty($raw_occurrences)) {
        $prefixed_candidates = array();
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'occurrences[') === 0) {
                $prefixed_candidates[] = $value;
            }
        }
        if (!empty($prefixed_candidates)) {
            foreach ($prefixed_candidates as $candidate) {
                if (is_string($candidate)) {
                    $decoded_candidate = json_decode(wp_unslash($candidate), true);
                    if (is_array($decoded_candidate)) {
                        $raw_occurrences[] = $decoded_candidate;
                    }
                } elseif (is_array($candidate)) {
                    $raw_occurrences[] = $candidate;
                }
            }
        }
    }

    $normalized = mj_regmgr_prepare_event_occurrence_rows($raw_occurrences);

    $schedule_mode = isset($event->schedule_mode) && $event->schedule_mode !== ''
        ? sanitize_key((string) $event->schedule_mode)
        : 'fixed';

    $schedule_payload_update = null;
    if (!empty($normalized['rows'])) {
        if ($schedule_mode === 'fixed') {
            $min_start = $normalized['stats']['min_start'];
            $max_end = $normalized['stats']['max_end'];
            if ($min_start !== null && $max_end !== null) {
                $schedule_payload_update = array(
                    'mode' => 'fixed',
                    'version' => 'occurrence-editor',
                    'date' => substr($min_start, 0, 10),
                    'start_time' => substr($min_start, 11, 5),
                    'end_time' => substr($max_end, 11, 5),
                );
            }
        } elseif ($schedule_mode === 'series') {
            $occurrence_payload_items = array();
            $series_items = array();
            foreach ($normalized['rows'] as $row_item) {
                $occurrence_payload_items[] = $row_item;
                $series_items[] = array(
                    'date' => substr($row_item['start'], 0, 10),
                    'start_time' => substr($row_item['start'], 11, 5),
                    'end_time' => substr($row_item['end'], 11, 5),
                    'status' => isset($row_item['status']) ? $row_item['status'] : MjEventOccurrences::STATUS_ACTIVE,
                );
            }
            $schedule_payload_update = array(
                'mode' => 'series',
                'version' => 'occurrence-editor',
                'occurrences' => $occurrence_payload_items,
                'items' => $series_items,
            );
        }
    } else {
        if ($schedule_mode === 'fixed') {
            $schedule_payload_update = array(
                'mode' => 'fixed',
                'version' => 'occurrence-editor',
            );
        } elseif ($schedule_mode === 'series') {
            $schedule_payload_update = array(
                'mode' => 'series',
                'version' => 'occurrence-editor',
                'occurrences' => array(),
                'items' => array(),
            );
        }
    }

    if ($schedule_payload_update === null) {
        $existing_payload = $existing_schedule_payload;
        if (!isset($existing_payload['mode']) && $schedule_mode !== '') {
            $existing_payload['mode'] = $schedule_mode;
        }
        $existing_payload['occurrence_summary'] = $submitted_schedule_summary;
        if (!isset($existing_payload['version']) || $existing_payload['version'] === '') {
            $existing_payload['version'] = 'occurrence-editor';
        }
        $schedule_payload_update = $existing_payload;
    } else {
        $schedule_payload_update['occurrence_summary'] = $submitted_schedule_summary;
        if (!isset($schedule_payload_update['version']) || $schedule_payload_update['version'] === '') {
            $schedule_payload_update['version'] = 'occurrence-editor';
        }
    }

    if ($generator_plan_provided) {
        $schedule_payload_update['occurrence_generator'] = $sanitized_generator_plan;
    } elseif (!empty($existing_generator_plan)) {
        $schedule_payload_update['occurrence_generator'] = $existing_generator_plan;
    }

    $updates = array();
    if (!empty($normalized['rows'])) {
        if (!empty($normalized['stats']['min_start'])) {
            $updates['date_debut'] = $normalized['stats']['min_start'];
        }
        if (!empty($normalized['stats']['max_end'])) {
            $updates['date_fin'] = $normalized['stats']['max_end'];
        }
    } else {
        $updates['date_debut'] = null;
        $updates['date_fin'] = null;
    }

    if ($schedule_payload_update !== null) {
        $updates['schedule_payload'] = $schedule_payload_update;
    }

    if (!empty($updates)) {
        $update_result = MjEvents::update($event_id, $updates);
        if (is_wp_error($update_result)) {
            wp_send_json_error(array('message' => $update_result->get_error_message()), 500);
            return;
        }
    }

    MjEventOccurrences::replace_for_event($event_id, $normalized['rows']);

    $refreshed_event = MjEvents::find($event_id);
    if (!$refreshed_event) {
        $refreshed_event = $event;
    }

    $schedule_mode = isset($refreshed_event->schedule_mode) && $refreshed_event->schedule_mode !== ''
        ? sanitize_key((string) $refreshed_event->schedule_mode)
        : 'fixed';
    $schedule_info = mj_regmgr_build_event_schedule_info($refreshed_event, $schedule_mode);

    $occurrence_rows = class_exists(MjEventSchedule::class)
        ? MjEventSchedule::build_all_occurrences($refreshed_event)
        : array();
    $occurrence_payload = mj_regmgr_format_event_occurrences_for_front($occurrence_rows);

    if (empty($occurrence_payload) && mj_regmgr_should_allow_occurrence_fallback($refreshed_event)) {
        $fallback_end = isset($refreshed_event->date_fin) ? (string) $refreshed_event->date_fin : '';
        $occurrence_payload[] = array(
            'id' => 'event-' . $refreshed_event->id,
            'start' => (string) $refreshed_event->date_debut,
            'end' => $fallback_end,
            'date' => substr((string) $refreshed_event->date_debut, 0, 10),
            'startTime' => substr((string) $refreshed_event->date_debut, 11, 5),
            'endTime' => $fallback_end !== '' ? substr($fallback_end, 11, 5) : '',
            'status' => 'planned',
            'reason' => '',
            'startFormatted' => mj_regmgr_format_date((string) $refreshed_event->date_debut, true),
            'endFormatted' => $fallback_end !== '' ? mj_regmgr_format_date($fallback_end, true) : '',
        );
    }

    $response_event = array(
        'id' => isset($refreshed_event->id) ? (int) $refreshed_event->id : $event_id,
        'occurrences' => $occurrence_payload,
        'dateDebut' => isset($refreshed_event->date_debut) ? (string) $refreshed_event->date_debut : '',
        'dateFin' => isset($refreshed_event->date_fin) ? (string) $refreshed_event->date_fin : '',
        'dateDebutFormatted' => mj_regmgr_format_date(isset($refreshed_event->date_debut) ? $refreshed_event->date_debut : '', true),
        'dateFinFormatted' => mj_regmgr_format_date(isset($refreshed_event->date_fin) ? $refreshed_event->date_fin : '', true),
        'scheduleSummary' => isset($schedule_info['summary']) ? $schedule_info['summary'] : '',
        'scheduleDetail' => isset($schedule_info['detail']) ? $schedule_info['detail'] : '',
        'occurrenceScheduleSummary' => $submitted_schedule_summary !== ''
            ? $submitted_schedule_summary
            : (isset($schedule_info['summary']) ? $schedule_info['summary'] : ''),
        'occurrenceGenerator' => mj_regmgr_extract_occurrence_generator_from_event($refreshed_event),
    );

    if (!empty($normalized['stats']['min_start'])) {
        $response_event['dateDebut'] = $normalized['stats']['min_start'];
        $response_event['dateDebutFormatted'] = mj_regmgr_format_date($normalized['stats']['min_start'], true);
    }
    if (!empty($normalized['stats']['max_end'])) {
        $response_event['dateFin'] = $normalized['stats']['max_end'];
        $response_event['dateFinFormatted'] = mj_regmgr_format_date($normalized['stats']['max_end'], true);
    }

    if (!empty($normalized['stats']['min_start']) || !empty($normalized['stats']['max_end'])) {
        $refreshed_event = $refreshed_event ? $refreshed_event->with(array(
            'date_debut' => !empty($normalized['stats']['min_start']) ? $normalized['stats']['min_start'] : $refreshed_event->date_debut,
            'date_fin' => !empty($normalized['stats']['max_end']) ? $normalized['stats']['max_end'] : $refreshed_event->date_fin,
        )) : $refreshed_event;
    }

    $event_summary = mj_regmgr_serialize_event_summary($refreshed_event);

    wp_send_json_success(array(
        'message' => __('Occurrences mises à jour.', 'mj-member'),
        'event' => $response_event,
        'eventSummary' => $event_summary,
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
 * Backward compatibility wrapper for legacy hooks expecting this function.
 */
function mj_regmgr_create_notes_table_if_not_exists() {
    mj_regmgr_ensure_notes_table();
}

/**
 * Get members list with filtering and pagination
 */
function mj_regmgr_get_members() {
    $current_member = mj_regmgr_verify_request();
    if (!$current_member) return;

    $filter = isset($_POST['filter']) ? sanitize_text_field($_POST['filter']) : 'all';
    $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
    $sort = isset($_POST['sort']) ? sanitize_text_field($_POST['sort']) : 'name';
    $page = isset($_POST['page']) ? absint($_POST['page']) : 1;
    $per_page = isset($_POST['perPage']) ? absint($_POST['perPage']) : 20;

    // Déterminer le tri
    $orderby = 'last_name';
    $order = 'ASC';
    switch ($sort) {
        case 'registration_date':
            $orderby = 'created_at';
            $order = 'DESC';
            break;
        case 'membership_date':
            $orderby = 'date_last_payement';
            $order = 'DESC';
            break;
        case 'name':
        default:
            $orderby = 'last_name';
            $order = 'ASC';
            break;
    }

    // Build filters array
    $filters = array();

    if ($filter === 'membership_due') {
        $filters['payment'] = 'due';
    }

    if ($filter !== 'all') {
        $normalized_filter = MjRoles::normalize($filter);
        if (in_array($normalized_filter, array('jeune', 'animateur', 'tuteur', 'benevole', 'coordinateur'), true)) {
            $filters['role'] = $normalized_filter;
        }
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
        'orderby' => $orderby,
        'order' => $order,
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
            $last_payment = !empty($member->date_last_payement) ? $member->date_last_payement : null;
            $last_payment_year = null;

            if ($last_payment && $last_payment !== '0000-00-00 00:00:00') {
                $timestamp = strtotime($last_payment);
                if ($timestamp) {
                    $last_payment_year = (int) date('Y', $timestamp);
                }
            }

            $effective_year = $membership_year;
            if (empty($effective_year) && $last_payment_year) {
                $effective_year = $last_payment_year;
            }

            if ($effective_year >= $current_year) {
                $membership_status = 'paid';
            } elseif ($effective_year > 0) {
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
            'isVolunteer' => !empty($member->is_volunteer),
            'xpTotal' => isset($member->xp_total) ? (int) $member->xp_total : 0,
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
 * Build level progression payload for a member.
 *
 * @param int $xp_total
 * @return array<string,mixed>
 */
function mj_regmgr_get_member_level_progression($xp_total) {
    $xp_total = (int) $xp_total;
    $progression = MjLevels::get_progression($xp_total);

    $current_level = isset($progression['current_level']) ? $progression['current_level'] : null;
    $next_level = isset($progression['next_level']) ? $progression['next_level'] : null;

    $current_level_image = '';
    if ($current_level && !empty($current_level['image_id'])) {
        $current_level_image = wp_get_attachment_image_url((int) $current_level['image_id'], 'thumbnail');
        if (!$current_level_image) {
            $current_level_image = wp_get_attachment_url((int) $current_level['image_id']);
        }
    }

    $next_level_image = '';
    if ($next_level && !empty($next_level['image_id'])) {
        $next_level_image = wp_get_attachment_image_url((int) $next_level['image_id'], 'thumbnail');
        if (!$next_level_image) {
            $next_level_image = wp_get_attachment_url((int) $next_level['image_id']);
        }
    }

    return array(
        'currentLevel' => $current_level ? array(
            'id' => (int) $current_level['id'],
            'levelNumber' => (int) $current_level['level_number'],
            'title' => isset($current_level['title']) ? (string) $current_level['title'] : '',
            'description' => isset($current_level['description']) ? (string) $current_level['description'] : '',
            'xpThreshold' => (int) $current_level['xp_threshold'],
            'xpReward' => isset($current_level['xp_reward']) ? (int) $current_level['xp_reward'] : 0,
            'imageUrl' => $current_level_image ?: '',
        ) : null,
        'nextLevel' => $next_level ? array(
            'id' => (int) $next_level['id'],
            'levelNumber' => (int) $next_level['level_number'],
            'title' => isset($next_level['title']) ? (string) $next_level['title'] : '',
            'description' => isset($next_level['description']) ? (string) $next_level['description'] : '',
            'xpThreshold' => (int) $next_level['xp_threshold'],
            'xpReward' => isset($next_level['xp_reward']) ? (int) $next_level['xp_reward'] : 0,
            'imageUrl' => $next_level_image ?: '',
        ) : null,
        'xpCurrent' => $xp_total,
        'xpForNext' => isset($progression['xp_for_next']) ? (int) $progression['xp_for_next'] : 0,
        'xpProgress' => isset($progression['xp_progress']) ? (int) $progression['xp_progress'] : 0,
        'xpRemaining' => isset($progression['xp_remaining']) ? (int) $progression['xp_remaining'] : 0,
        'progressPercent' => isset($progression['progress_percent']) ? (int) $progression['progress_percent'] : 0,
        'isMaxLevel' => isset($progression['is_max_level']) ? (bool) $progression['is_max_level'] : false,
    );
}

/**
 * Build badges payload for a member within the registration manager.
 *
 * @param int $member_id
 * @return array<int,array<string,mixed>>
 */
function mj_regmgr_get_member_badges_payload($member_id) {
    $member_id = (int) $member_id;
    if ($member_id <= 0) {
        return array();
    }

    $badges = MjBadges::get_all(array(
        'status' => MjBadges::STATUS_ACTIVE,
        'orderby' => 'display_order',
        'order' => 'ASC',
    ));

    if (empty($badges)) {
        return array();
    }

    $assignments = MjMemberBadges::get_all(array(
        'member_id' => $member_id,
    ));

    $assignment_map = array();
    if (!empty($assignments)) {
        foreach ($assignments as $assignment) {
            $assignment_badge_id = isset($assignment['badge_id']) ? (int) $assignment['badge_id'] : 0;
            if ($assignment_badge_id > 0) {
                $assignment_map[$assignment_badge_id] = $assignment;
            }
        }
    }

    $payload = array();
    foreach ($badges as $badge) {
        $entry = mj_regmgr_prepare_member_badge_entry($badge, $member_id, $assignment_map);
        if ($entry !== null) {
            $payload[] = $entry;
        }
    }

    return $payload;
}

/**
 * Prepare a badge entry payload for a member.
 *
 * @param array<string,mixed>|null $badge
 * @param int $member_id
 * @param array<int,array<string,mixed>>|null $assignment_map
 * @return array<string,mixed>|null
 */
function mj_regmgr_prepare_member_badge_entry($badge, $member_id, $assignment_map = null) {
    if (!is_array($badge) || empty($badge['id'])) {
        return null;
    }

    $badge_id = (int) $badge['id'];
    if ($badge_id <= 0) {
        return null;
    }

    if ($assignment_map === null) {
        $assignment_rows = MjMemberBadges::get_all(array(
            'member_id' => $member_id,
            'badge_id' => $badge_id,
            'limit' => 1,
        ));
        $assignment_map = array();
        if (!empty($assignment_rows)) {
            $assignment_map[$badge_id] = $assignment_rows[0];
        }
    }

    $assignment = isset($assignment_map[$badge_id]) ? $assignment_map[$badge_id] : null;
    $assignment_status = '';
    $awarded_at = '';
    $revoked_at = '';

    $image_id = isset($badge['image_id']) ? (int) $badge['image_id'] : 0;
    $image_url = '';
    if ($image_id > 0) {
        $image_url = wp_get_attachment_image_url($image_id, 'medium');
        if (!$image_url) {
            $image_url = wp_get_attachment_url($image_id);
        }
    }

    if (is_array($assignment)) {
        $assignment_status = isset($assignment['status']) ? (string) $assignment['status'] : '';
        $awarded_at = isset($assignment['awarded_at']) ? (string) $assignment['awarded_at'] : '';
        $revoked_at = isset($assignment['revoked_at']) ? (string) $assignment['revoked_at'] : '';
    }

    $criteria_records = array();
    if (!empty($badge['criteria_records']) && is_array($badge['criteria_records'])) {
        foreach ($badge['criteria_records'] as $record) {
            if (empty($record['id'])) {
                continue;
            }
            if (!empty($record['status']) && $record['status'] === MjBadgeCriteria::STATUS_ARCHIVED) {
                continue;
            }
            $criteria_records[] = $record;
        }
    }

    $awards = MjMemberBadgeCriteria::get_for_member_badge($member_id, $badge_id);
    $awarded_map = array();
    if (!empty($awards)) {
        foreach ($awards as $award_row) {
            $criterion_id = isset($award_row['criterion_id']) ? (int) $award_row['criterion_id'] : 0;
            if ($criterion_id <= 0) {
                continue;
            }
            $awarded_map[$criterion_id] = isset($award_row['status']) ? (string) $award_row['status'] : MjMemberBadgeCriteria::STATUS_AWARDED;
        }
    }

    $criteria = array();
    $awardable_total = 0;
    $awarded_count = 0;

    foreach ($criteria_records as $record) {
        $criterion_id = isset($record['id']) ? (int) $record['id'] : 0;
        $can_toggle = $criterion_id > 0;

        $status = 'pending';
        $awarded = false;

        if ($can_toggle && isset($awarded_map[$criterion_id])) {
            $candidate_status = $awarded_map[$criterion_id];
            if ($candidate_status === MjMemberBadgeCriteria::STATUS_AWARDED) {
                $status = 'awarded';
                $awarded = true;
                $awarded_count++;
            } elseif ($candidate_status === MjMemberBadgeCriteria::STATUS_REVOKED) {
                $status = 'revoked';
            }
        }

        if ($can_toggle) {
            $awardable_total++;
        }

        $criteria[] = array(
            'id' => $criterion_id,
            'label' => isset($record['label']) ? (string) $record['label'] : '',
            'description' => isset($record['description']) ? (string) $record['description'] : '',
            'awarded' => $awarded,
            'status' => $status,
            'canToggle' => $can_toggle,
        );
    }

    if (empty($criteria) && !empty($badge['criteria']) && is_array($badge['criteria'])) {
        foreach ($badge['criteria'] as $label_raw) {
            $label = trim((string) $label_raw);
            if ($label === '') {
                continue;
            }
            $criteria[] = array(
                'id' => 0,
                'label' => $label,
                'description' => '',
                'awarded' => false,
                'status' => 'pending',
                'canToggle' => false,
            );
        }
    }

    $progress = 0;
    if ($awardable_total > 0) {
        $progress = (int) round(($awarded_count / $awardable_total) * 100);
    } elseif ($assignment_status === MjMemberBadges::STATUS_AWARDED) {
        $progress = 100;
    }

    if ($progress < 0) {
        $progress = 0;
    } elseif ($progress > 100) {
        $progress = 100;
    }

    return array(
        'id' => $badge_id,
        'label' => isset($badge['label']) ? (string) $badge['label'] : '',
        'summary' => isset($badge['summary']) ? (string) $badge['summary'] : '',
        'description' => isset($badge['description']) ? (string) $badge['description'] : '',
        'icon' => isset($badge['icon']) ? (string) $badge['icon'] : '',
        'imageId' => $image_id,
        'imageUrl' => $image_url,
        'status' => $assignment_status,
        'awardedAt' => $awarded_at,
        'revokedAt' => $revoked_at,
        'totalCriteria' => $awardable_total,
        'awardedCount' => $awarded_count,
        'progressPercent' => $progress,
        'criteria' => $criteria,
    );
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
        'hasLinkedAccount' => !empty($memberData->wp_user_id),
        'accountLogin' => isset($memberData->member_account_login) ? (string) $memberData->member_account_login : '',
        'accountEmail' => '',
        'accountRole' => '',
        'accountRoleLabel' => '',
        'accountEditUrl' => '',
        'cardClaimUrl' => '',
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
        'xpTotal' => isset($memberData->xp_total) ? (int) $memberData->xp_total : 0,
        'guardian' => null,
    );

    if ($member['hasLinkedAccount']) {
        $user_object = get_user_by('id', (int) $member['userId']);
        if ($user_object) {
            $member['accountEmail'] = $user_object->user_email ?? '';
            if ($member['accountLogin'] === '') {
                $member['accountLogin'] = $user_object->user_login;
            }

            if (!empty($user_object->roles) && is_array($user_object->roles)) {
                $primary_role = reset($user_object->roles);
                if (is_string($primary_role) && $primary_role !== '') {
                    $member['accountRole'] = $primary_role;
                    $role_object = get_role($primary_role);
                    if ($role_object && isset($role_object->name)) {
                        $member['accountRoleLabel'] = translate_user_role($role_object->name);
                    } else {
                        $member['accountRoleLabel'] = ucfirst(str_replace('_', ' ', $primary_role));
                    }
                }
            }

            $member['accountEditUrl'] = get_edit_user_link($user_object->ID);
        }
    }

    if (function_exists('mj_member_get_card_claim_url')) {
        $member['cardClaimUrl'] = mj_member_get_card_claim_url((int) $memberData->id);
    }

    // Add guardian info if exists
    if (!empty($memberData->guardian_id)) {
        $guardian = MjMembers::getById((int) $memberData->guardian_id);
        if ($guardian) {
            $guardian_role = $guardian->role ?? '';
            $guardian_role_label = '';

            if ($guardian_role !== '' && isset($role_labels[$guardian_role])) {
                $guardian_role_label = $role_labels[$guardian_role];
            } elseif ($guardian_role !== '') {
                $guardian_role_label = ucfirst($guardian_role);
            }

            $member['guardianName'] = trim(($guardian->first_name ?? '') . ' ' . ($guardian->last_name ?? ''));
            $member['guardian'] = array(
                'id' => (int) $guardian->id,
                'firstName' => $guardian->first_name ?? '',
                'lastName' => $guardian->last_name ?? '',
                'avatarUrl' => mj_regmgr_get_member_avatar_url((int) $guardian->id),
                'role' => $guardian_role,
                'roleLabel' => $guardian_role_label,
                'email' => $guardian->email ?? '',
                'phone' => $guardian->phone ?? '',
            );
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
            $child_last_payment = isset($child->date_last_payement) ? $child->date_last_payement : null;
            $child_last_payment_year = null;

            if ($child_last_payment && $child_last_payment !== '0000-00-00 00:00:00') {
                $timestamp = strtotime($child_last_payment);
                if ($timestamp) {
                    $child_last_payment_year = (int) date('Y', $timestamp);
                }
            }

            $child_effective_year = $child_membership_year;
            if (empty($child_effective_year) && $child_last_payment_year) {
                $child_effective_year = $child_last_payment_year;
            }

            $child_membership_status = 'not_required';
            if ($child_requires_payment) {
                if ($child_effective_year >= $current_year) {
                    $child_membership_status = 'paid';
                } elseif ($child_effective_year > 0) {
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

    $member['badges'] = mj_regmgr_get_member_badges_payload($member_id);
    $member['trophies'] = mj_regmgr_get_member_trophies_payload($member_id);

    // Ajouter les informations de niveau
    $member['levelProgression'] = mj_regmgr_get_member_level_progression($member['xpTotal']);

    wp_send_json_success(array('member' => $member));
}

/**
 * Synchronize badge criteria for a member
 */
function mj_regmgr_sync_member_badge() {
    $current_member = mj_regmgr_verify_request();
    if (!$current_member) return;

    $member_id = isset($_POST['memberId']) ? absint($_POST['memberId']) : 0;
    $badge_id = isset($_POST['badgeId']) ? absint($_POST['badgeId']) : 0;

    if ($member_id <= 0 || $badge_id <= 0) {
        wp_send_json_error(array('message' => __('Paramètres invalides.', 'mj-member')));
        return;
    }

    $target_member = MjMembers::getById($member_id);
    if (!$target_member) {
        wp_send_json_error(array('message' => __('Membre introuvable.', 'mj-member')));
        return;
    }

    $badge = MjBadges::get($badge_id);
    if (!$badge || (isset($badge['status']) && $badge['status'] === MjBadges::STATUS_ARCHIVED)) {
        wp_send_json_error(array('message' => __('Badge introuvable ou archivé.', 'mj-member')));
        return;
    }

    $raw_ids = array();
    if (isset($_POST['criterionIds'])) {
        $raw_ids = $_POST['criterionIds'];
        if (is_string($raw_ids)) {
            $decoded = json_decode(stripslashes($raw_ids), true);
            $raw_ids = is_array($decoded) ? $decoded : array();
        }
    }

    $criterion_ids = array();
    if (is_array($raw_ids)) {
        foreach ($raw_ids as $value) {
            $criterion_ids[] = (int) $value;
        }
    }

    $criterion_ids = array_values(array_filter($criterion_ids, static function ($id) {
        return $id > 0;
    }));

    if (!empty($criterion_ids)) {
        $criterion_ids = MjBadgeCriteria::filter_ids_for_badge($badge_id, $criterion_ids);
    }

    // Determine badge completion state BEFORE sync
    $was_complete = mj_regmgr_is_badge_complete($member_id, $badge_id);

    $awarded_by = get_current_user_id();

    $sync = MjMemberBadgeCriteria::sync_awards($member_id, $badge_id, $criterion_ids, $awarded_by);
    if (is_wp_error($sync)) {
        wp_send_json_error(array('message' => $sync->get_error_message()));
        return;
    }

    // Determine badge completion state AFTER sync
    $is_complete = mj_regmgr_is_badge_complete($member_id, $badge_id);

    // Award or revoke XP for badge completion
    if ($is_complete && !$was_complete) {
        // Badge just became complete - award 100 XP
        MjMemberXp::awardForBadgeCompletion($member_id);
    } elseif (!$is_complete && $was_complete) {
        // Badge was complete but no longer is - revoke 100 XP
        MjMemberXp::revokeForBadgeCompletion($member_id);
    }

    $status = empty($criterion_ids) ? MjMemberBadges::STATUS_REVOKED : MjMemberBadges::STATUS_AWARDED;
    $assignment = MjMemberBadges::create(array(
        'member_id' => $member_id,
        'badge_id' => $badge_id,
        'status' => $status,
        'awarded_by_user_id' => $awarded_by,
    ));

    if (is_wp_error($assignment)) {
        wp_send_json_error(array('message' => $assignment->get_error_message()));
        return;
    }

    $badge_payload = mj_regmgr_prepare_member_badge_entry($badge, $member_id);

    // Include updated XP and level progression in response
    $updated_member = MjMembers::getById($member_id);
    $xp_total = isset($updated_member->xp_total) ? (int) $updated_member->xp_total : 0;
    $level_progression = mj_regmgr_get_member_level_progression($xp_total);

    wp_send_json_success(array(
        'message' => __('Progression du badge mise à jour.', 'mj-member'),
        'badge' => $badge_payload,
        'xpTotal' => $xp_total,
        'levelProgression' => $level_progression,
    ));
}

/**
 * Check if a badge is complete for a given member.
 *
 * A badge is complete when all toggleable criteria are awarded.
 *
 * @param int $member_id Member ID.
 * @param int $badge_id Badge ID.
 * @return bool True if badge is complete.
 */
function mj_regmgr_is_badge_complete($member_id, $badge_id) {
    $criteria_records = MjBadgeCriteria::get_for_badge($badge_id);
    if (empty($criteria_records)) {
        return false;
    }

    // Filter out archived criteria
    $active_criteria = array_filter($criteria_records, static function ($record) {
        return empty($record['status']) || $record['status'] !== MjBadgeCriteria::STATUS_ARCHIVED;
    });

    $awardable_total = count($active_criteria);
    if ($awardable_total === 0) {
        return false;
    }

    $awards = MjMemberBadgeCriteria::get_for_member_badge($member_id, $badge_id);
    $awarded_count = 0;

    if (!empty($awards)) {
        foreach ($awards as $award_row) {
            $status = isset($award_row['status']) ? (string) $award_row['status'] : '';
            if ($status === MjMemberBadgeCriteria::STATUS_AWARDED) {
                $awarded_count++;
            }
        }
    }

    return $awarded_count >= $awardable_total;
}

/**
 * Adjust member XP manually (add or remove).
 */
function mj_regmgr_adjust_member_xp() {
    $current_member = mj_regmgr_verify_request();
    if (!$current_member) {
        return;
    }

    $member_id = isset($_POST['memberId']) ? absint($_POST['memberId']) : 0;
    $amount = isset($_POST['amount']) ? (int) $_POST['amount'] : 0;

    if ($member_id <= 0) {
        wp_send_json_error(array('message' => __('ID du membre manquant.', 'mj-member')));
        return;
    }

    if ($amount === 0) {
        wp_send_json_error(array('message' => __('Montant XP invalide.', 'mj-member')));
        return;
    }

    $member = MjMembers::getById($member_id);
    if (!$member) {
        wp_send_json_error(array('message' => __('Membre introuvable.', 'mj-member')));
        return;
    }

    if ($amount > 0) {
        $result = MjMemberXp::add($member_id, $amount);
    } else {
        $result = MjMemberXp::subtract($member_id, abs($amount));
    }

    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()));
        return;
    }

    $action_label = $amount > 0 ? 'ajoutés' : 'retirés';
    $abs_amount = abs($amount);
    $level_progression = mj_regmgr_get_member_level_progression($result);

    wp_send_json_success(array(
        'message' => sprintf(__('%d XP %s.', 'mj-member'), $abs_amount, $action_label),
        'xpTotal' => $result,
        'levelProgression' => $level_progression,
    ));
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
    if (array_key_exists('guardianId', $data)) {
        $raw_guardian_id = (int) $data['guardianId'];
        if ($raw_guardian_id > 0) {
            $guardian = MjMembers::getById($raw_guardian_id);
            if (!$guardian || $guardian->role !== MjRoles::TUTEUR) {
                wp_send_json_error(array('message' => __('Le tuteur spécifié est introuvable ou invalide.', 'mj-member')));
                return;
            }
            $update_data['guardian_id'] = $raw_guardian_id;
        } else {
            $update_data['guardian_id'] = null;
        }
    }
    if (array_key_exists('photoId', $data)) {
        $raw_photo_id = (int) $data['photoId'];
        if ($raw_photo_id > 0) {
            if (!current_user_can('upload_files')) {
                wp_send_json_error(array('message' => __('Vous ne pouvez pas gérer la médiathèque.', 'mj-member')));
                return;
            }

            $attachment = get_post($raw_photo_id);
            if (!$attachment || 'attachment' !== $attachment->post_type) {
                wp_send_json_error(array('message' => __('Fichier de média introuvable.', 'mj-member')));
                return;
            }
            if (!wp_attachment_is_image($raw_photo_id)) {
                wp_send_json_error(array('message' => __('Le fichier sélectionné n\'est pas une image.', 'mj-member')));
                return;
            }

            $update_data['photo_id'] = $raw_photo_id;
        } else {
            $update_data['photo_id'] = 0;
        }
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

    $response = array(
        'message' => __('Membre mis à jour avec succès.', 'mj-member'),
    );

    if (array_key_exists('photo_id', $update_data)) {
        $response['photoId'] = (int) $update_data['photo_id'];
        $response['avatarUrl'] = mj_regmgr_get_member_avatar_url($member_id);
    }

    if (array_key_exists('guardian_id', $update_data)) {
        $response['guardianId'] = $update_data['guardian_id'] ? (int) $update_data['guardian_id'] : 0;
    }

    wp_send_json_success($response);
}

/**
 * Delete a member from the directory
 */
function mj_regmgr_delete_member() {
    $auth = mj_regmgr_verify_request();
    if (!$auth) {
        return;
    }

    $member_id = isset($_POST['memberId']) ? absint($_POST['memberId']) : 0;
    if ($member_id <= 0) {
        wp_send_json_error(array('message' => __('Identifiant de membre invalide.', 'mj-member')));
        return;
    }

    $can_delete = !empty($auth['is_coordinateur']) || current_user_can(Config::capability());
    if (!$can_delete) {
        wp_send_json_error(
            array('message' => __('Vous ne pouvez pas supprimer ce membre.', 'mj-member')),
            403
        );
        return;
    }

    $current_member_id = isset($auth['member_id']) ? (int) $auth['member_id'] : 0;
    if ($current_member_id === $member_id) {
        wp_send_json_error(array('message' => __('Vous ne pouvez pas supprimer votre propre fiche depuis cet écran.', 'mj-member')));
        return;
    }

    $member = MjMembers::getById($member_id);
    if (!$member) {
        wp_send_json_error(array('message' => __('Membre introuvable.', 'mj-member')));
        return;
    }

    $result = MjMembers::delete($member_id);
    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()));
        return;
    }

    wp_send_json_success(array(
        'message' => __('Membre supprimé.', 'mj-member'),
        'memberId' => $member_id,
    ));
}

/**
 * Trigger a password reset email for a member's linked WordPress account
 */
function mj_regmgr_reset_member_password() {
    $auth = mj_regmgr_verify_request();
    if (!$auth) {
        return;
    }

    $member_id = isset($_POST['memberId']) ? absint($_POST['memberId']) : 0;
    if ($member_id <= 0) {
        wp_send_json_error(array('message' => __('Identifiant de membre invalide.', 'mj-member')));
        return;
    }

    $member = MjMembers::getById($member_id);
    if (!$member) {
        wp_send_json_error(array('message' => __('Membre introuvable.', 'mj-member')));
        return;
    }

    $user_id = isset($member->wp_user_id) ? (int) $member->wp_user_id : 0;
    if ($user_id <= 0) {
        wp_send_json_error(array('message' => __('Aucun compte WordPress n\'est associé à ce membre.', 'mj-member')));
        return;
    }

    $user = get_user_by('id', $user_id);
    if (!$user) {
        wp_send_json_error(array('message' => __('Compte utilisateur introuvable.', 'mj-member')));
        return;
    }

    if (!apply_filters('allow_password_reset', true, $user->ID)) {
        wp_send_json_error(array('message' => __('La réinitialisation du mot de passe est désactivée pour ce compte.', 'mj-member')));
        return;
    }

    $reset = retrieve_password($user->user_login);
    if (is_wp_error($reset)) {
        wp_send_json_error(array('message' => $reset->get_error_message()));
        return;
    }

    wp_send_json_success(array(
        'message' => __('Un email de réinitialisation a été envoyé au membre.', 'mj-member'),
        'email' => $user->user_email,
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
 * Capture and assign a new member photo via direct upload
 */
function mj_regmgr_capture_member_photo() {
    $auth = mj_regmgr_verify_request();
    if (!$auth) {
        return;
    }

    if (!current_user_can('upload_files')) {
        wp_send_json_error(array('message' => __('Vous ne pouvez pas gérer la médiathèque.', 'mj-member')));
        return;
    }

    $member_id = isset($_POST['memberId']) ? (int) $_POST['memberId'] : 0;
    if ($member_id <= 0) {
        wp_send_json_error(array('message' => __('Identifiant de membre invalide.', 'mj-member')));
        return;
    }

    $member = MjMembers::getById($member_id);
    if (!$member) {
        wp_send_json_error(array('message' => __('Membre introuvable.', 'mj-member')));
        return;
    }

    $can_change = !empty($auth['is_coordinateur']) || current_user_can(Config::capability());
    if (!$can_change) {
        wp_send_json_error(array('message' => __('Accès refusé pour mettre à jour cette photo.', 'mj-member')), 403);
        return;
    }

    if (!isset($_FILES['photo'])) {
        wp_send_json_error(array('message' => __('Aucune photo reçue.', 'mj-member')));
        return;
    }

    $file = $_FILES['photo'];
    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        wp_send_json_error(array('message' => __('Le fichier envoyé est invalide.', 'mj-member')));
        return;
    }

    $file_check = wp_check_filetype_and_ext($file['tmp_name'], $file['name']);
    if (empty($file_check['type']) || strpos($file_check['type'], 'image/') !== 0) {
        wp_send_json_error(array('message' => __('Le fichier sélectionné n\'est pas une image.', 'mj-member')));
        return;
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $upload_overrides = array(
        'test_form' => false,
        'mimes' => array(
            'jpg|jpeg|jpe' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
        ),
    );

    $uploaded = wp_handle_upload($file, $upload_overrides);
    if (isset($uploaded['error'])) {
        wp_send_json_error(array('message' => $uploaded['error']));
        return;
    }

    $filename = $uploaded['file'];
    $attachment = array(
        'guid' => $uploaded['url'],
        'post_mime_type' => $file_check['type'],
        'post_title' => sanitize_text_field(pathinfo($filename, PATHINFO_FILENAME)),
        'post_content' => '',
        'post_status' => 'inherit',
    );

    $attachment_id = wp_insert_attachment($attachment, $filename);
    if (is_wp_error($attachment_id)) {
        if (file_exists($filename)) {
            wp_delete_file($filename);
        }
        wp_send_json_error(array('message' => $attachment_id->get_error_message()));
        return;
    }

    $metadata = wp_generate_attachment_metadata($attachment_id, $filename);
    if (!is_wp_error($metadata)) {
        wp_update_attachment_metadata($attachment_id, $metadata);
    }

    $update = MjMembers::update($member_id, array('photo_id' => $attachment_id));
    if (is_wp_error($update)) {
        wp_delete_attachment($attachment_id, true);
        wp_send_json_error(array('message' => $update->get_error_message()));
        return;
    }

    $avatar_url = mj_regmgr_get_member_avatar_url($member_id);

    wp_send_json_success(array(
        'message' => __('Photo de profil mise à jour.', 'mj-member'),
        'photoId' => $attachment_id,
        'avatarUrl' => $avatar_url,
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

    $events_cache = array();
    $event_cover_cache = array();
    $occurrence_cache = array();
    $now_timestamp = current_time('timestamp');

    $format_occurrence_label = static function ($start_value, $end_value = null) {
        $start_value = (string) $start_value;
        if ($start_value === '') {
            return '';
        }

        $start_ts = strtotime($start_value);
        if ($start_ts === false) {
            return $start_value;
        }

        $date_format = get_option('date_format', 'd/m/Y');
        $time_format = get_option('time_format', 'H:i');

        $label = date_i18n($date_format, $start_ts) . ' - ' . date_i18n($time_format, $start_ts);

        if ($end_value) {
            $end_ts = strtotime((string) $end_value);
            if ($end_ts !== false && $end_ts > $start_ts) {
                if (date_i18n('Ymd', $start_ts) === date_i18n('Ymd', $end_ts)) {
                    $label .= ' -> ' . date_i18n($time_format, $end_ts);
                } else {
                    $label .= ' -> ' . date_i18n($date_format, $end_ts);
                }
            }
        }

        return $label;
    };

    $registrations = array();
    foreach ($registrations_data as $reg) {
        if (!$reg) {
            continue;
        }

        $event_id = isset($reg->event_id) ? (int) $reg->event_id : 0;

        if ($event_id > 0 && !array_key_exists($event_id, $events_cache)) {
            $event = MjEvents::find($event_id);
            $events_cache[$event_id] = $event ? $event : null;
        }

        $event = ($event_id > 0 && isset($events_cache[$event_id])) ? $events_cache[$event_id] : null;

        if ($event_id > 0 && !array_key_exists($event_id, $event_cover_cache)) {
            $cover_payload = array(
                'id' => 0,
                'url' => '',
                'alt' => '',
            );

            if ($event && !empty($event->cover_id)) {
                $cover_id = (int) $event->cover_id;
                if ($cover_id > 0) {
                    $cover_payload['id'] = $cover_id;

                    $cover_source = wp_get_attachment_image_src($cover_id, 'large');
                    if ($cover_source) {
                        $cover_payload['url'] = esc_url_raw($cover_source[0]);
                    } else {
                        $cover_fallback = wp_get_attachment_image_src($cover_id, 'medium');
                        if ($cover_fallback) {
                            $cover_payload['url'] = esc_url_raw($cover_fallback[0]);
                        }
                    }

                    $alt_meta = get_post_meta($cover_id, '_wp_attachment_image_alt', true);
                    if (is_string($alt_meta) && trim($alt_meta) !== '') {
                        $cover_payload['alt'] = sanitize_text_field($alt_meta);
                    } elseif ($event && !empty($event->title)) {
                        $cover_payload['alt'] = sanitize_text_field((string) $event->title);
                    }
                }
            }

            if ($cover_payload['url'] === '' && $event && !empty($event->article_id)) {
                $article_id = (int) $event->article_id;
                if ($article_id > 0) {
                    $article_thumb_id = get_post_thumbnail_id($article_id);
                    if ($article_thumb_id) {
                        $cover_source = wp_get_attachment_image_src($article_thumb_id, 'large');
                        if ($cover_source) {
                            $cover_payload['id'] = (int) $article_thumb_id;
                            $cover_payload['url'] = esc_url_raw($cover_source[0]);
                        } else {
                            $cover_fallback = wp_get_attachment_image_src($article_thumb_id, 'medium');
                            if ($cover_fallback) {
                                $cover_payload['id'] = (int) $article_thumb_id;
                                $cover_payload['url'] = esc_url_raw($cover_fallback[0]);
                            }
                        }

                        if ($cover_payload['alt'] === '') {
                            $alt_meta = get_post_meta($article_thumb_id, '_wp_attachment_image_alt', true);
                            if (is_string($alt_meta) && trim($alt_meta) !== '') {
                                $cover_payload['alt'] = sanitize_text_field($alt_meta);
                            } elseif ($event && !empty($event->title)) {
                                $cover_payload['alt'] = sanitize_text_field((string) $event->title);
                            }
                        }
                    }
                }
            }

            $event_cover_cache[$event_id] = $cover_payload;
        }

        if (!array_key_exists($event_id, $occurrence_cache)) {
            $event_occurrences = array();

            $event = ($event_id > 0 && isset($events_cache[$event_id])) ? $events_cache[$event_id] : null;

            if ($event && class_exists(MjEventSchedule::class)) {
                $occurrences = MjEventSchedule::get_occurrences($event, array(
                    'include_past' => true,
                    'max' => 200,
                ));

                if (is_array($occurrences)) {
                    foreach ($occurrences as $occurrence) {
                        if (!is_array($occurrence)) {
                            continue;
                        }

                        $start_value = isset($occurrence['start']) ? $occurrence['start'] : '';
                        $normalized_start = MjEventAttendance::normalize_occurrence($start_value);
                        if ($normalized_start === '') {
                            continue;
                        }

                        $end_value = isset($occurrence['end']) ? (string) $occurrence['end'] : null;
                        $label_value = isset($occurrence['label']) ? (string) $occurrence['label'] : $format_occurrence_label($normalized_start, $end_value);
                        $is_past = isset($occurrence['is_past']) ? (bool) $occurrence['is_past'] : (strtotime($normalized_start) < $now_timestamp);

                        $event_occurrences[$normalized_start] = array(
                            'start' => $normalized_start,
                            'end' => $end_value,
                            'label' => $label_value,
                            'isPast' => $is_past,
                        );
                    }
                }
            }

            $occurrence_cache[$event_id] = $event_occurrences;
        }

        $event_cover = isset($event_cover_cache[$event_id]) ? $event_cover_cache[$event_id] : array('id' => 0, 'url' => '', 'alt' => '');
        $event = ($event_id > 0 && isset($events_cache[$event_id])) ? $events_cache[$event_id] : null;
        $event_title = $event ? $event->title : __('Événement supprimé', 'mj-member');

        $event_date = null;
        if ($event) {
            if (!empty($event->start_date)) {
                $event_date = $event->start_date;
            } elseif (!empty($event->date_debut)) {
                $event_date = $event->date_debut;
            }
        }

        $assignments_raw = MjEventAttendance::get_registration_assignments($reg);
        $assignment_mode = isset($assignments_raw['mode']) ? sanitize_key((string) $assignments_raw['mode']) : 'all';
        if ($assignment_mode !== 'custom') {
            $assignment_mode = 'all';
        }

        $assigned_occurrences = array();
        if ($assignment_mode === 'custom' && !empty($assignments_raw['occurrences']) && is_array($assignments_raw['occurrences'])) {
            foreach ($assignments_raw['occurrences'] as $candidate) {
                $normalized_candidate = MjEventAttendance::normalize_occurrence($candidate);
                if ($normalized_candidate === '' || in_array($normalized_candidate, $assigned_occurrences, true)) {
                    continue;
                }
                $assigned_occurrences[] = $normalized_candidate;
            }
        }

        $event_occurrence_map = ($event_id > 0 && isset($occurrence_cache[$event_id])) ? $occurrence_cache[$event_id] : array();

        $sessions = array();
        if ($assignment_mode === 'custom' && !empty($assigned_occurrences)) {
            foreach ($assigned_occurrences as $occurrence_key) {
                if (isset($event_occurrence_map[$occurrence_key])) {
                    $sessions[] = $event_occurrence_map[$occurrence_key];
                } else {
                    $sessions[] = array(
                        'start' => $occurrence_key,
                        'end' => null,
                        'label' => $format_occurrence_label($occurrence_key),
                        'isPast' => (strtotime($occurrence_key) < $now_timestamp),
                    );
                }
            }
        } elseif (!empty($event_occurrence_map)) {
            $sessions = array_values($event_occurrence_map);
        }

        $status_key = isset($reg->statut) ? (string) $reg->statut : (isset($reg->status) ? (string) $reg->status : 'en_attente');
        if ($status_key === '') {
            $status_key = 'en_attente';
        }

        $registrations[] = array(
            'id' => isset($reg->id) ? (int) $reg->id : 0,
            'eventId' => $event_id,
            'eventTitle' => $event_title,
            'eventCover' => $event_cover,
            'status' => $status_key,
            'statusLabel' => isset($status_labels[$status_key]) ? $status_labels[$status_key] : $status_key,
            'createdAt' => isset($reg->created_at) ? $reg->created_at : null,
            'eventDate' => $event_date,
            'occurrenceAssignments' => array(
                'mode' => $assignment_mode,
                'occurrences' => $assigned_occurrences,
            ),
            'occurrenceDetails' => $sessions,
            'coversAllOccurrences' => ($assignment_mode !== 'custom'),
            'totalOccurrences' => count($event_occurrence_map),
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

    // Attribuer le trophée "Cotisation réglée"
    MjTrophyService::assignBySlug($member_id, MjTrophyService::MEMBERSHIP_PAID, array(
        'notes' => sprintf(__('Cotisation %d - %s', 'mj-member'), $year, $method_label),
    ));

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

/**
 * Build trophies payload for a member within the registration manager.
 *
 * @param int $member_id
 * @return array<int,array<string,mixed>>
 */
function mj_regmgr_get_member_trophies_payload($member_id) {
    $member_id = (int) $member_id;
    if ($member_id <= 0) {
        return array();
    }

    $trophies = MjTrophies::get_all(array(
        'status' => MjTrophies::STATUS_ACTIVE,
        'orderby' => 'display_order',
        'order' => 'ASC',
    ));

    if (empty($trophies)) {
        return array();
    }

    // Get member's trophy assignments
    $assignments = MjMemberTrophies::get_all(array(
        'member_id' => $member_id,
    ));

    $assignment_map = array();
    if (!empty($assignments)) {
        foreach ($assignments as $assignment) {
            $trophy_id = isset($assignment['trophy_id']) ? (int) $assignment['trophy_id'] : 0;
            if ($trophy_id > 0) {
                $assignment_map[$trophy_id] = $assignment;
            }
        }
    }

    $payload = array();
    foreach ($trophies as $trophy) {
        $trophy_id = isset($trophy['id']) ? (int) $trophy['id'] : 0;
        if ($trophy_id <= 0) {
            continue;
        }

        $assignment = isset($assignment_map[$trophy_id]) ? $assignment_map[$trophy_id] : null;
        $assignment_status = '';
        $awarded_at = '';

        if (is_array($assignment)) {
            $assignment_status = isset($assignment['status']) ? (string) $assignment['status'] : '';
            $awarded_at = isset($assignment['awarded_at']) ? (string) $assignment['awarded_at'] : '';
        }

        $image_id = isset($trophy['image_id']) ? (int) $trophy['image_id'] : 0;
        $image_url = '';
        if ($image_id > 0) {
            $image_url = wp_get_attachment_image_url($image_id, 'medium');
            if (!$image_url) {
                $image_url = wp_get_attachment_url($image_id);
            }
        }

        $is_auto = !empty($trophy['auto_mode']);
        $is_awarded = $assignment_status === MjMemberTrophies::STATUS_AWARDED;

        $payload[] = array(
            'id' => $trophy_id,
            'title' => isset($trophy['title']) ? (string) $trophy['title'] : '',
            'description' => isset($trophy['description']) ? (string) $trophy['description'] : '',
            'xp' => isset($trophy['xp']) ? (int) $trophy['xp'] : 0,
            'imageId' => $image_id,
            'imageUrl' => $image_url,
            'autoMode' => $is_auto,
            'awarded' => $is_awarded,
            'awardedAt' => $awarded_at,
            'canToggle' => !$is_auto,
        );
    }

    return $payload;
}

/**
 * Toggle a trophy for a member (manual trophies only)
 */
function mj_regmgr_toggle_member_trophy() {
    $current_member = mj_regmgr_verify_request();
    if (!$current_member) return;

    $member_id = isset($_POST['memberId']) ? absint($_POST['memberId']) : 0;
    $trophy_id = isset($_POST['trophyId']) ? absint($_POST['trophyId']) : 0;
    $awarded = isset($_POST['awarded']) && $_POST['awarded'] === 'true';

    if ($member_id <= 0 || $trophy_id <= 0) {
        wp_send_json_error(array('message' => __('Paramètres invalides.', 'mj-member')));
        return;
    }

    $target_member = MjMembers::getById($member_id);
    if (!$target_member) {
        wp_send_json_error(array('message' => __('Membre introuvable.', 'mj-member')));
        return;
    }

    $trophy = MjTrophies::get($trophy_id);
    if (!$trophy || (isset($trophy['status']) && $trophy['status'] === MjTrophies::STATUS_ARCHIVED)) {
        wp_send_json_error(array('message' => __('Trophée introuvable ou archivé.', 'mj-member')));
        return;
    }

    // Check if trophy is manual (not auto_mode)
    if (!empty($trophy['auto_mode'])) {
        wp_send_json_error(array('message' => __('Ce trophée est automatique et ne peut pas être attribué manuellement.', 'mj-member')));
        return;
    }

    if ($awarded) {
        $result = MjMemberTrophies::award($member_id, $trophy_id);
    } else {
        $result = MjMemberTrophies::revoke($member_id, $trophy_id);
    }

    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()));
        return;
    }

    // Build updated trophy entry
    $updated_trophy = mj_regmgr_get_member_trophies_payload($member_id);
    $updated_entry = null;
    foreach ($updated_trophy as $entry) {
        if ((int) $entry['id'] === $trophy_id) {
            $updated_entry = $entry;
            break;
        }
    }

    wp_send_json_success(array(
        'trophy' => $updated_entry,
        'message' => $awarded
            ? __('Trophée attribué.', 'mj-member')
            : __('Trophée retiré.', 'mj-member'),
    ));
}
