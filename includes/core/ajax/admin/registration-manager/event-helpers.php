<?php
/**
 * Shared helpers for Registration Manager event flows.
 *
 * @package MjMember
 */

use Mj\Member\Classes\Crud\MjEvents;
use Mj\Member\Classes\Crud\MjEventRegistrations;
use Mj\Member\Classes\Crud\MjEventAnimateurs;
use Mj\Member\Classes\Crud\MjEventVolunteers;
use Mj\Member\Classes\Crud\MjEventLocations;
use Mj\Member\Classes\Crud\MjMembers;
use Mj\Member\Classes\MjEventSchedule;
use Mj\Member\Classes\MjRoles;
use Mj\Member\Core\Config;
use Mj\Member\Classes\Value\EventLocationData;

if (!defined('ABSPATH')) {
    exit;
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

function mj_regmgr_sanitize_emoji($value) {
    if (is_object($value) && method_exists($value, '__toString')) {
        $value = (string) $value;
    }

    if (!is_scalar($value)) {
        return '';
    }

    $candidate = wp_check_invalid_utf8((string) $value);
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

    $excerpt = wp_html_excerpt($candidate, 16, '');
    $excerpt = trim(is_string($excerpt) ? $excerpt : '');

    if ($excerpt === '' && $candidate !== '') {
        return $candidate;
    }

    return $excerpt;
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
            if (empty($weekdays) && isset($payload['weekday_times']) && is_array($payload['weekday_times'])) {
                foreach (array_keys($payload['weekday_times']) as $weekday_key) {
                    $weekday_key = sanitize_key($weekday_key);
                    if (isset($schedule_weekdays[$weekday_key])) {
                        $weekdays[$weekday_key] = $weekday_key;
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
