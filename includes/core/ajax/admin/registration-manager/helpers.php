<?php
/**
 * Shared helper functions for Registration Manager AJAX endpoints.
 *
 * @package MjMember
 */

use Mj\Member\Classes\Crud\MjEventAnimateurs;
use Mj\Member\Classes\Crud\MjEventLocations;
use Mj\Member\Classes\Crud\MjEventRegistrations;
use Mj\Member\Classes\Crud\MjEventVolunteers;
use Mj\Member\Classes\Crud\MjEventOccurrences;
use Mj\Member\Classes\Crud\MjEvents;
use Mj\Member\Classes\Crud\MjMembers;
use Mj\Member\Classes\MjEventSchedule;
use Mj\Member\Classes\MjRoles;
use Mj\Member\Core\Config;
use Mj\Member\Classes\Value\EventLocationData;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get member avatar URL.
 *
 * @param int $member_id Member ID
 * @return string Avatar URL or empty string
 */
function mj_regmgr_get_member_avatar_url($member_id) {
    $member = MjMembers::getById($member_id);
    if (!$member) {
        return '';
    }

    $photo_id = isset($member->photo_id) ? (int) $member->photo_id : 0;
    if ($photo_id > 0) {
        $url = wp_get_attachment_image_url($photo_id, 'thumbnail');
        if ($url) {
            return $url;
        }
    }

    $avatar_id = isset($member->avatar_id) ? (int) $member->avatar_id : 0;
    if ($avatar_id > 0) {
        $url = wp_get_attachment_image_url($avatar_id, 'thumbnail');
        if ($url) {
            return $url;
        }
    }

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

    return array(
        'id' => $event_id,
        'title' => isset($event->title) ? (string) $event->title : '',
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

    return array(
        'summary' => $summary,
        'detail' => implode(' · ', array_filter($detail_parts)),
    );
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

    if ($value instanceof DateTimeInterface) {
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

function mj_regmgr_parse_recurrence_until($value, $end_time, DateTimeZone $timezone) {
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
            'registration_document' => isset($event->registration_document) ? (string) $event->registration_document : '',
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

    $animateur_filters = array('roles' => array(MjRoles::ANIMATEUR, MjRoles::COORDINATEUR));
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

function mj_regmgr_build_event_update_payload($event, array $form_values, array $meta, array $references, array $schedule_weekdays, array $schedule_month_ordinals, array &$errors) {
    $errors = array();

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
    $registration_document = isset($form_values['registration_document']) ? wp_kses_post($form_values['registration_document']) : '';

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

    $existing_schedule_payload = array();
    if (!empty($event->schedule_payload)) {
        if (is_array($event->schedule_payload)) {
            $existing_schedule_payload = $event->schedule_payload;
        } else {
            $decoded_existing = json_decode((string) $event->schedule_payload, true);
            if (is_array($decoded_existing)) {
                $existing_schedule_payload = $decoded_existing;
            }
        }
    }

    $incoming_schedule_payload = array();
    if (isset($form_values['schedule_payload'])) {
        if (is_array($form_values['schedule_payload'])) {
            $incoming_schedule_payload = $form_values['schedule_payload'];
        } else {
            $decoded_incoming = json_decode((string) $form_values['schedule_payload'], true);
            if (is_array($decoded_incoming)) {
                $incoming_schedule_payload = $decoded_incoming;
            }
        }
    } elseif (isset($meta['schedulePayload'])) {
        if (is_array($meta['schedulePayload'])) {
            $incoming_schedule_payload = $meta['schedulePayload'];
        } else {
            $decoded_meta_payload = json_decode((string) $meta['schedulePayload'], true);
            if (is_array($decoded_meta_payload)) {
                $incoming_schedule_payload = $decoded_meta_payload;
            }
        }
    }

    $schedule_payload_candidate = !empty($incoming_schedule_payload) ? $incoming_schedule_payload : $existing_schedule_payload;
    $schedule_payload_version = isset($schedule_payload_candidate['version']) ? sanitize_key((string) $schedule_payload_candidate['version']) : '';

    $has_occurrence_entities = false;
    if (!empty($schedule_payload_candidate)) {
        if (function_exists('mj_regmgr_schedule_payload_has_occurrence_entities')) {
            $has_occurrence_entities = mj_regmgr_schedule_payload_has_occurrence_entities($schedule_payload_candidate);
        } else {
            foreach (array('occurrences', 'items') as $collection_key) {
                if (!isset($schedule_payload_candidate[$collection_key]) || !is_array($schedule_payload_candidate[$collection_key])) {
                    continue;
                }
                foreach ($schedule_payload_candidate[$collection_key] as $entry) {
                    if (!is_array($entry)) {
                        continue;
                    }
                    foreach ($entry as $value) {
                        if ($value !== null && $value !== '') {
                            $has_occurrence_entities = true;
                            break 3;
                        }
                    }
                }
            }
        }
    }

    $occurrence_editor_active = ($schedule_payload_version === 'occurrence-editor') || $has_occurrence_entities;

    $event_occurrence_rows = array();
    $event_id = isset($event->id) ? (int) $event->id : 0;
    if (!$occurrence_editor_active && $event_id > 0 && class_exists(MjEventOccurrences::class)) {
        $event_occurrence_rows = MjEventOccurrences::get_for_event($event_id);
        if (!empty($event_occurrence_rows)) {
            $occurrence_editor_active = true;
        }
    }

    if ($occurrence_editor_active && empty($event_occurrence_rows) && $event_id > 0 && class_exists(MjEventOccurrences::class)) {
        $event_occurrence_rows = MjEventOccurrences::get_for_event($event_id);
    }

    if ($occurrence_editor_active) {
        $schedule_payload = $schedule_payload_candidate;

        if (isset($schedule_payload['mode'])) {
            $candidate_mode = sanitize_key((string) $schedule_payload['mode']);
            if (in_array($candidate_mode, array('fixed', 'range', 'recurring', 'series'), true)) {
                $schedule_mode = $candidate_mode;
            }
        }

        $computed_start = null;
        $computed_end = null;

        if (isset($schedule_payload['occurrences']) && is_array($schedule_payload['occurrences'])) {
            foreach ($schedule_payload['occurrences'] as $occurrence) {
                if (!is_array($occurrence)) {
                    continue;
                }

                $start_candidate = isset($occurrence['start']) ? trim((string) $occurrence['start']) : '';
                $end_candidate = isset($occurrence['end']) ? trim((string) $occurrence['end']) : '';

                if ($start_candidate === '' || $end_candidate === '') {
                    continue;
                }

                if ($computed_start === null || strcmp($start_candidate, $computed_start) < 0) {
                    $computed_start = $start_candidate;
                }
                if ($computed_end === null || strcmp($end_candidate, $computed_end) > 0) {
                    $computed_end = $end_candidate;
                }
            }
        }

        if ($computed_start === null && isset($schedule_payload['items']) && is_array($schedule_payload['items'])) {
            foreach ($schedule_payload['items'] as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $date_value = isset($item['date']) ? sanitize_text_field((string) $item['date']) : '';
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_value)) {
                    continue;
                }

                $start_time = isset($item['start_time']) ? sanitize_text_field((string) $item['start_time']) : '';
                $end_time = isset($item['end_time']) ? sanitize_text_field((string) $item['end_time']) : '';

                if ($start_time === '') {
                    continue;
                }

                $start_candidate = $date_value . ' ' . $start_time . ':00';
                $end_candidate = $date_value . ' ' . ($end_time !== '' ? $end_time : $start_time) . ':00';

                if ($computed_start === null || strcmp($start_candidate, $computed_start) < 0) {
                    $computed_start = $start_candidate;
                }
                if ($computed_end === null || strcmp($end_candidate, $computed_end) > 0) {
                    $computed_end = $end_candidate;
                }
            }
        }

        if ($computed_start === null && !empty($event_occurrence_rows)) {
            foreach ($event_occurrence_rows as $occurrence_row) {
                if (!is_array($occurrence_row)) {
                    continue;
                }

                $row_start = isset($occurrence_row['start_at']) ? trim((string) $occurrence_row['start_at']) : '';
                $row_end = isset($occurrence_row['end_at']) ? trim((string) $occurrence_row['end_at']) : '';

                if ($row_start === '' || $row_end === '') {
                    continue;
                }

                if ($computed_start === null || strcmp($row_start, $computed_start) < 0) {
                    $computed_start = $row_start;
                }
                if ($computed_end === null || strcmp($row_end, $computed_end) > 0) {
                    $computed_end = $row_end;
                }
            }
        }

        if ($computed_start !== null) {
            $date_debut = $computed_start;
        }

        if ($computed_end !== null) {
            $date_fin = $computed_end;
        }

        if ($date_debut === '' && isset($event->date_debut) && $event->date_debut !== '') {
            $date_debut = (string) $event->date_debut;
        }

        if ($date_fin === '' && isset($event->date_fin) && $event->date_fin !== '') {
            $date_fin = (string) $event->date_fin;
        }

        if ($recurrence_until_value === '' && isset($event->recurrence_until) && $event->recurrence_until !== '') {
            $recurrence_until_value = (string) $event->recurrence_until;
        }

        if (isset($schedule_payload['items']) && is_array($schedule_payload['items'])) {
            foreach ($schedule_payload['items'] as $series_item) {
                if (!is_array($series_item)) {
                    continue;
                }

                $series_items_clean[] = array(
                    'date' => isset($series_item['date']) ? sanitize_text_field((string) $series_item['date']) : '',
                    'start_time' => isset($series_item['start_time']) ? sanitize_text_field((string) $series_item['start_time']) : '',
                    'end_time' => isset($series_item['end_time']) ? sanitize_text_field((string) $series_item['end_time']) : '',
                );
            }
        }

        if (empty($schedule_payload) && !empty($existing_schedule_payload)) {
            $schedule_payload = $existing_schedule_payload;
        }

        if (empty($schedule_payload) && !empty($event_occurrence_rows)) {
            $occurrence_items = array();
            foreach ($event_occurrence_rows as $occurrence_row) {
                if (!is_array($occurrence_row)) {
                    continue;
                }

                $row_start = isset($occurrence_row['start_at']) ? trim((string) $occurrence_row['start_at']) : '';
                $row_end = isset($occurrence_row['end_at']) ? trim((string) $occurrence_row['end_at']) : '';

                if ($row_start === '' || $row_end === '') {
                    continue;
                }

                $occurrence_items[] = array(
                    'start' => $row_start,
                    'end' => $row_end,
                    'status' => isset($occurrence_row['status']) ? sanitize_key((string) $occurrence_row['status']) : '',
                );
            }

            if (!empty($occurrence_items)) {
                $schedule_payload = array(
                    'version' => 'occurrence-editor',
                    'mode' => $schedule_mode,
                    'occurrences' => $occurrence_items,
                );
            }
        }

        if (!isset($schedule_payload['mode']) || $schedule_payload['mode'] === '') {
            $schedule_payload['mode'] = $schedule_mode;
        }
    } else {
        if ($schedule_mode === 'fixed') {
            $fixed_date = isset($form_values['schedule_fixed_date']) ? sanitize_text_field($form_values['schedule_fixed_date']) : '';
            $fixed_start = isset($form_values['schedule_fixed_start_time']) ? sanitize_text_field($form_values['schedule_fixed_start_time']) : '';
            $fixed_end = isset($form_values['schedule_fixed_end_time']) ? sanitize_text_field($form_values['schedule_fixed_end_time']) : '';
            $date_debut_raw = isset($form_values['date_debut']) ? $form_values['date_debut'] : '';
            $date_fin_raw = isset($form_values['date_fin']) ? $form_values['date_fin'] : '';

            $start_datetime = null;
            $end_datetime = null;

            if ($fixed_date !== '' && $fixed_start !== '') {
                $start_datetime = DateTime::createFromFormat('Y-m-d H:i', $fixed_date . ' ' . $fixed_start, $timezone);
            }

            if (!$start_datetime && $date_debut_raw !== '') {
                $parsed_start = mj_regmgr_parse_event_datetime($date_debut_raw);
                if ($parsed_start !== '') {
                    $start_datetime = DateTime::createFromFormat('Y-m-d H:i:s', $parsed_start, $timezone);
                    if ($start_datetime instanceof DateTime) {
                        if ($fixed_date === '') {
                            $fixed_date = $start_datetime->format('Y-m-d');
                        }
                        if ($fixed_start === '') {
                            $fixed_start = $start_datetime->format('H:i');
                        }
                    }
                }
            }

            $has_fixed_inputs = ($fixed_date !== '' || $fixed_start !== '' || $fixed_end !== '');
            $allow_empty_schedule = ($status === MjEvents::STATUS_DRAFT);


            if ($fixed_end !== '' && $fixed_date !== '') {
                $end_datetime = DateTime::createFromFormat('Y-m-d H:i', $fixed_date . ' ' . $fixed_end, $timezone);
            }

            if (!$end_datetime && $date_fin_raw !== '') {
                $parsed_end = mj_regmgr_parse_event_datetime($date_fin_raw);
                if ($parsed_end !== '') {
                    $end_datetime = DateTime::createFromFormat('Y-m-d H:i:s', $parsed_end, $timezone);
                    if ($end_datetime instanceof DateTime && $fixed_end === '') {
                        $fixed_end = $end_datetime->format('H:i');
                    }
                }
            }

            $end_provided = ($fixed_end !== '' || $date_fin_raw !== '');

            if ($start_datetime instanceof DateTime) {
                if (!($end_datetime instanceof DateTime)) {
                    $end_datetime = clone $start_datetime;
                }

                if ($end_provided && $end_datetime <= $start_datetime) {
                    $errors[] = __('L\'heure de fin doit être postérieure à l\'heure de début.', 'mj-member');
                } else {
                    $date_debut = $start_datetime->format('Y-m-d H:i:s');
                    $date_fin = $end_datetime->format('Y-m-d H:i:s');
                }
            } elseif ($allow_empty_schedule) {
                $date_debut = '';
                $date_fin = '';
            }

            $schedule_payload = array(
                'mode' => 'fixed',
                'date' => $fixed_date,
                'start_time' => $fixed_start,
                'end_time' => $fixed_end,
            );
        } elseif ($schedule_mode === 'range') {
            $range_start_raw = isset($form_values['schedule_range_start']) ? $form_values['schedule_range_start'] : '';
            $range_end_raw = isset($form_values['schedule_range_end']) ? $form_values['schedule_range_end'] : '';

            $range_start = mj_regmgr_parse_event_datetime($range_start_raw);
            $range_end = mj_regmgr_parse_event_datetime($range_end_raw);

            if ($range_start === '' || $range_end === '') {
                $errors[] = __('Les dates de début et de fin de la plage sont obligatoires.', 'mj-member');
            } elseif (strtotime($range_end) < strtotime($range_start)) {
                $errors[] = __('La date de fin doit être postérieure à la date de début.', 'mj-member');
            } else {
                $date_debut = $range_start;
                $date_fin = $range_end;
            }

            $schedule_payload = array(
                'mode' => 'range',
                'start' => (string) $range_start_raw,
                'end' => (string) $range_end_raw,
            );
        } elseif ($schedule_mode === 'recurring') {
            $recurring_start_date = isset($form_values['schedule_recurring_start_date']) ? sanitize_text_field($form_values['schedule_recurring_start_date']) : '';
            $recurring_start_time = isset($form_values['schedule_recurring_start_time']) ? sanitize_text_field($form_values['schedule_recurring_start_time']) : '';
            $recurring_end_time = isset($form_values['schedule_recurring_end_time']) ? sanitize_text_field($form_values['schedule_recurring_end_time']) : '';

            if ($recurring_start_date === '' || $recurring_start_time === '') {
                $errors[] = __('La date et l\'heure de début de la récurrence sont obligatoires.', 'mj-member');
            } else {
                $start_datetime = DateTime::createFromFormat('Y-m-d H:i', $recurring_start_date . ' ' . $recurring_start_time, $timezone);
                if (!$start_datetime) {
                    $errors[] = __('La date de début de la récurrence est invalide.', 'mj-member');
                } else {
                    $end_datetime = DateTime::createFromFormat('Y-m-d H:i', $recurring_start_date . ' ' . $recurring_end_time, $timezone);
                    if (!$end_datetime) {
                        $errors[] = __('L\'heure de fin de la récurrence est invalide.', 'mj-member');
                    } else {
                        if ($end_datetime <= $start_datetime) {
                            $end_datetime->modify('+1 day');
                        }
                        $date_debut = $start_datetime->format('Y-m-d H:i:s');
                        $date_fin = $end_datetime->format('Y-m-d H:i:s');
                    }
                }
            }

            $frequency = isset($form_values['schedule_recurring_frequency']) ? sanitize_key((string) $form_values['schedule_recurring_frequency']) : 'weekly';
            if (!in_array($frequency, array('weekly', 'monthly'), true)) {
                $frequency = 'weekly';
            }
            $interval = isset($form_values['schedule_recurring_interval']) ? max(1, (int) $form_values['schedule_recurring_interval']) : 1;

            if ($frequency === 'weekly') {
                $weekdays = array();
                if (isset($form_values['schedule_recurring_weekdays']) && is_array($form_values['schedule_recurring_weekdays'])) {
                    foreach ($form_values['schedule_recurring_weekdays'] as $weekday) {
                        $weekday = sanitize_key($weekday);
                        if (isset($schedule_weekdays[$weekday])) {
                            $weekdays[$weekday] = $weekday;
                        }
                    }
                }
                if (empty($weekdays)) {
                    $errors[] = __('Sélectionnez au moins un jour pour la récurrence hebdomadaire.', 'mj-member');
                }

                $weekday_times = isset($form_values['schedule_weekday_times']) ? mj_regmgr_sanitize_weekday_times($form_values['schedule_weekday_times'], $schedule_weekdays) : array();
                $show_date_range = !empty($form_values['schedule_show_date_range']);

                $schedule_payload = array(
                    'mode' => 'recurring',
                    'frequency' => 'weekly',
                    'interval' => $interval,
                    'weekdays' => array_values($weekdays),
                    'weekday_times' => $weekday_times,
                    'start_time' => $recurring_start_time,
                    'end_time' => $recurring_end_time,
                    'start_date' => $recurring_start_date,
                    'show_date_range' => $show_date_range,
                );
            } else {
                $ordinal = isset($form_values['schedule_recurring_month_ordinal']) ? sanitize_key((string) $form_values['schedule_recurring_month_ordinal']) : 'first';
                if (!isset($schedule_month_ordinals[$ordinal])) {
                    $ordinal = 'first';
                }
                $weekday = isset($form_values['schedule_recurring_month_weekday']) ? sanitize_key((string) $form_values['schedule_recurring_month_weekday']) : 'saturday';
                if (!isset($schedule_weekdays[$weekday])) {
                    $weekday = 'saturday';
                }
                $show_date_range = !empty($form_values['schedule_show_date_range']);

                $schedule_payload = array(
                    'mode' => 'recurring',
                    'frequency' => 'monthly',
                    'interval' => $interval,
                    'ordinal' => $ordinal,
                    'weekday' => $weekday,
                    'start_time' => $recurring_start_time,
                    'end_time' => $recurring_end_time,
                    'start_date' => $recurring_start_date,
                    'show_date_range' => $show_date_range,
                );
            }

            $recurrence_until_value = mj_regmgr_parse_recurrence_until(isset($form_values['recurrence_until']) ? $form_values['recurrence_until'] : '', $recurring_end_time, $timezone);
        } elseif ($schedule_mode === 'series') {
            $series = isset($form_values['schedule_series_items']) && is_array($form_values['schedule_series_items']) ? $form_values['schedule_series_items'] : array();
            $earliest = null;
            $latest = null;

            foreach ($series as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $date_value = isset($item['date']) ? sanitize_text_field($item['date']) : '';
                $start_value = isset($item['start_time']) ? sanitize_text_field($item['start_time']) : '';
                $end_value = isset($item['end_time']) ? sanitize_text_field($item['end_time']) : '';

                if ($date_value === '' || $start_value === '') {
                    continue;
                }

                $start_datetime = DateTime::createFromFormat('Y-m-d H:i', $date_value . ' ' . $start_value, $timezone);
                if (!$start_datetime) {
                    continue;
                }
                $end_datetime = null;
                if ($end_value !== '') {
                    $end_datetime = DateTime::createFromFormat('Y-m-d H:i', $date_value . ' ' . $end_value, $timezone);
                }
                if (!$end_datetime || $end_datetime <= $start_datetime) {
                    $end_datetime = clone $start_datetime;
                    $end_datetime->modify('+1 hour');
                }

                $series_items_clean[] = array(
                    'date' => $date_value,
                    'start_time' => $start_value,
                    'end_time' => $end_value,
                );

                if (!$earliest || $start_datetime < $earliest) {
                    $earliest = clone $start_datetime;
                }
                if (!$latest || $end_datetime > $latest) {
                    $latest = clone $end_datetime;
                }
            }

            if (empty($series_items_clean)) {
                $errors[] = __('Ajoutez au moins une date valide pour la série.', 'mj-member');
            } else {
                $date_debut = $earliest->format('Y-m-d H:i:s');
                $date_fin = $latest->format('Y-m-d H:i:s');
            }

            $schedule_payload = array(
                'mode' => 'series',
                'items' => $series_items_clean,
            );
        }
    }

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

    $payload = array(
        'title' => $title,
        'status' => $status,
        'type' => $type,
        'accent_color' => $accent_color,
        'emoji' => isset($form_values['emoji']) ? sanitize_text_field((string) $form_values['emoji']) : '',
        'cover_id' => $cover_id,
        'description' => $description,
        'registration_document' => $registration_document,
        'age_min' => $age_min,
        'age_max' => $age_max,
        'date_debut' => $date_debut,
        'date_fin' => $date_fin,
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
        'scheduleMode' => $schedule_mode,
        'scheduleSummary' => isset($schedule_info['summary']) ? $schedule_info['summary'] : '',
        'scheduleDetail' => isset($schedule_info['detail']) ? $schedule_info['detail'] : '',
        'registrationDocument' => isset($event->registration_document) ? (string) $event->registration_document : '',
    );
}

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

function mj_regmgr_get_event_cover_url($event, $size = 'thumbnail') {
    if (!empty($event->cover_id) && $event->cover_id > 0) {
        $url = wp_get_attachment_image_url($event->cover_id, $size);
        if ($url) {
            return $url;
        }
    }

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

function mj_regmgr_create_notes_table_if_not_exists() {
    mj_regmgr_ensure_notes_table();
}