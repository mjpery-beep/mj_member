<?php

use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can(Config::capability())) {
    wp_die('Acces refuse');
}

$basePath = Config::path();
$baseUrl = Config::url();
$pluginVersion = Config::version();

require_once $basePath . 'includes/classes/crud/MjEvents_CRUD.php';
require_once $basePath . 'includes/classes/crud/MjMembers_CRUD.php';
require_once $basePath . 'includes/classes/crud/MjEventAnimateurs.php';
require_once $basePath . 'includes/classes/crud/MjEventRegistrations.php';
require_once $basePath . 'includes/classes/crud/MjEventLocations.php';
require_once $basePath . 'includes/classes/crud/MjEventAttendance.php';
require_once $basePath . 'includes/classes/MjEventSchedule.php';

if (!function_exists('mj_member_parse_event_datetime')) {
    function mj_member_parse_event_datetime($value) {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $timezone = wp_timezone();
        $datetime = DateTime::createFromFormat('Y-m-d\TH:i', $value, $timezone);
        if ($datetime instanceof DateTime) {
            return $datetime->format('Y-m-d H:i:s');
        }

        $timestamp = strtotime($value);
        if ($timestamp) {
            $datetime = new DateTime('@' . $timestamp);
            $datetime->setTimezone($timezone);
            return $datetime->format('Y-m-d H:i:s');
        }

        return '';
    }
}

if (!function_exists('mj_member_format_event_datetime')) {
    function mj_member_format_event_datetime($value) {
        if (empty($value) || $value === '0000-00-00 00:00:00') {
            return '';
        }

        $timestamp = strtotime($value);
        if (!$timestamp) {
            return '';
        }

        return wp_date('Y-m-d\TH:i', $timestamp);
    }
}

if (!function_exists('mj_member_admin_normalize_hex_color')) {
    function mj_member_admin_normalize_hex_color($value) {
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
}

if (!function_exists('mj_member_fill_schedule_form_values')) {
    function mj_member_fill_schedule_form_values($event, array &$form_values, array $schedule_weekdays, array $schedule_month_ordinals) {
        if (!$event) {
            return;
        }

        $payload_value = array();
        if (!empty($event->schedule_payload)) {
            if (is_string($event->schedule_payload)) {
                $decoded_payload = json_decode($event->schedule_payload, true);
                if (is_array($decoded_payload)) {
                    $payload_value = $decoded_payload;
                }
            } elseif (is_array($event->schedule_payload)) {
                $payload_value = $event->schedule_payload;
            }
        }

        $schedule_mode = !empty($event->schedule_mode) ? sanitize_key($event->schedule_mode) : 'fixed';
        if (!in_array($schedule_mode, array('fixed', 'range', 'recurring'), true)) {
            $schedule_mode = 'fixed';
        }

        $form_values['schedule_mode'] = $schedule_mode;
        $form_values['schedule_payload'] = $payload_value;
        $form_values['recurrence_until'] = !empty($event->recurrence_until) ? wp_date('Y-m-d', strtotime($event->recurrence_until)) : '';

        $default_start_time = $form_values['date_debut'] !== '' ? substr($form_values['date_debut'], 11, 5) : '';
        $default_end_time = $form_values['date_fin'] !== '' ? substr($form_values['date_fin'], 11, 5) : '';
        $default_start_date = $form_values['date_debut'] !== '' ? substr($form_values['date_debut'], 0, 10) : '';

        $form_values['schedule_fixed_date'] = $default_start_date;
        $form_values['schedule_fixed_start_time'] = $default_start_time;
        $form_values['schedule_fixed_end_time'] = $default_end_time;
        $form_values['schedule_range_start'] = $form_values['date_debut'];
        $form_values['schedule_range_end'] = $form_values['date_fin'];

        if ($schedule_mode === 'recurring') {
            $form_values['schedule_recurring_frequency'] = isset($payload_value['frequency']) ? sanitize_key($payload_value['frequency']) : 'weekly';
            if (!in_array($form_values['schedule_recurring_frequency'], array('weekly', 'monthly'), true)) {
                $form_values['schedule_recurring_frequency'] = 'weekly';
            }

            $form_values['schedule_recurring_interval'] = isset($payload_value['interval']) ? max(1, (int) $payload_value['interval']) : 1;
            $form_values['schedule_recurring_weekdays'] = array();
            if (isset($payload_value['weekdays']) && is_array($payload_value['weekdays'])) {
                foreach ($payload_value['weekdays'] as $weekday_value) {
                    $weekday_value = sanitize_key($weekday_value);
                    if (isset($schedule_weekdays[$weekday_value])) {
                        $form_values['schedule_recurring_weekdays'][$weekday_value] = $weekday_value;
                    }
                }
                $form_values['schedule_recurring_weekdays'] = array_values($form_values['schedule_recurring_weekdays']);
            }

            $form_values['schedule_recurring_month_ordinal'] = isset($payload_value['ordinal']) ? sanitize_key($payload_value['ordinal']) : 'first';
            if (!isset($schedule_month_ordinals[$form_values['schedule_recurring_month_ordinal']])) {
                $form_values['schedule_recurring_month_ordinal'] = 'first';
            }

            $form_values['schedule_recurring_month_weekday'] = isset($payload_value['weekday']) ? sanitize_key($payload_value['weekday']) : 'saturday';
            if (!isset($schedule_weekdays[$form_values['schedule_recurring_month_weekday']])) {
                $form_values['schedule_recurring_month_weekday'] = 'saturday';
            }

            $form_values['schedule_recurring_start_date'] = $default_start_date;
            $form_values['schedule_recurring_start_time'] = isset($payload_value['start_time']) ? sanitize_text_field($payload_value['start_time']) : $default_start_time;
            $form_values['schedule_recurring_end_time'] = isset($payload_value['end_time']) ? sanitize_text_field($payload_value['end_time']) : $default_end_time;
            $form_values['schedule_range_start'] = '';
            $form_values['schedule_range_end'] = '';
        } else {
            $form_values['schedule_recurring_start_date'] = $default_start_date;
            $form_values['schedule_recurring_start_time'] = $default_start_time;
            $form_values['schedule_recurring_end_time'] = $default_end_time;
            $form_values['schedule_recurring_frequency'] = 'weekly';
            $form_values['schedule_recurring_interval'] = 1;
            $form_values['schedule_recurring_weekdays'] = array();
            $form_values['schedule_recurring_month_ordinal'] = 'first';
            $form_values['schedule_recurring_month_weekday'] = 'saturday';
        }

        if ($schedule_mode === 'fixed') {
            if (isset($payload_value['date'])) {
                $form_values['schedule_fixed_date'] = sanitize_text_field($payload_value['date']);
            }
            if (isset($payload_value['start_time'])) {
                $form_values['schedule_fixed_start_time'] = sanitize_text_field($payload_value['start_time']);
            }
            if (isset($payload_value['end_time'])) {
                $form_values['schedule_fixed_end_time'] = sanitize_text_field($payload_value['end_time']);
            }

            if ($form_values['date_debut'] !== '') {
                $form_values['schedule_fixed_date'] = substr($form_values['date_debut'], 0, 10);
                $form_values['schedule_fixed_start_time'] = substr($form_values['date_debut'], 11, 5);
            }
            if ($form_values['date_fin'] !== '') {
                $form_values['schedule_fixed_end_time'] = substr($form_values['date_fin'], 11, 5);
            }

            $form_values['schedule_range_start'] = '';
            $form_values['schedule_range_end'] = '';
        } elseif ($schedule_mode === 'range') {
            if ($form_values['date_debut'] !== '') {
                $form_values['schedule_range_start'] = $form_values['date_debut'];
            }
            if ($form_values['date_fin'] !== '') {
                $form_values['schedule_range_end'] = $form_values['date_fin'];
            }
            if (isset($payload_value['start'])) {
                $form_values['schedule_range_start'] = sanitize_text_field($payload_value['start']);
            }
            if (isset($payload_value['end'])) {
                $form_values['schedule_range_end'] = sanitize_text_field($payload_value['end']);
            }
        }
        if ($schedule_mode !== 'fixed') {
            if ($form_values['schedule_fixed_date'] === '') {
                $form_values['schedule_fixed_date'] = $default_start_date;
            }
            if ($form_values['schedule_fixed_start_time'] === '') {
                $form_values['schedule_fixed_start_time'] = $default_start_time;
            }
            if ($form_values['schedule_fixed_end_time'] === '') {
                $form_values['schedule_fixed_end_time'] = $default_end_time;
            }
        }
    }
}

if (!function_exists('mj_member_admin_extract_snapshot_meta')) {
    /**
     * @param array<string,mixed> $snapshot
     * @return array{occurrence_map:array<string,array<string,mixed>>,default_start:string,default_label:string}
     */
    function mj_member_admin_extract_snapshot_meta($snapshot) {
        $result = array(
            'occurrence_map' => array(),
            'default_start' => '',
            'default_label' => '',
        );

        if (!is_array($snapshot) || empty($snapshot)) {
            return $result;
        }

        $occurrence_map = array();
        if (!empty($snapshot['occurrences']) && is_array($snapshot['occurrences'])) {
            foreach ($snapshot['occurrences'] as $occurrence) {
                if (!is_array($occurrence)) {
                    continue;
                }

                $start_value = isset($occurrence['start']) ? (string) $occurrence['start'] : '';
                if ($start_value === '') {
                    continue;
                }

                $normalized_start = class_exists('MjEventAttendance')
                    ? MjEventAttendance::normalize_occurrence($start_value)
                    : $start_value;
                if ($normalized_start === '') {
                    continue;
                }

                $label_value = isset($occurrence['label']) ? sanitize_text_field((string) $occurrence['label']) : $start_value;
                $counts_value = array();
                if (isset($occurrence['counts']) && is_array($occurrence['counts'])) {
                    $counts_value = array(
                        'present' => isset($occurrence['counts']['present']) ? (int) $occurrence['counts']['present'] : 0,
                        'absent' => isset($occurrence['counts']['absent']) ? (int) $occurrence['counts']['absent'] : 0,
                        'pending' => isset($occurrence['counts']['pending']) ? (int) $occurrence['counts']['pending'] : 0,
                    );
                }

                $occurrence_map[$normalized_start] = array(
                    'label' => $label_value,
                    'counts' => $counts_value,
                    'start' => $start_value,
                );
            }
        }

        $default_start = '';
        if (!empty($snapshot['defaultOccurrence'])) {
            $default_start = class_exists('MjEventAttendance')
                ? MjEventAttendance::normalize_occurrence((string) $snapshot['defaultOccurrence'])
                : (string) $snapshot['defaultOccurrence'];
        }

        $default_label = isset($snapshot['defaultOccurrenceLabel'])
            ? sanitize_text_field((string) $snapshot['defaultOccurrenceLabel'])
            : '';

        if ($default_start === '' && !empty($occurrence_map)) {
            $first_key = array_key_first($occurrence_map);
            if ($first_key !== null) {
                $default_start = $first_key;
                if ($default_label === '' && isset($occurrence_map[$first_key]['label'])) {
                    $default_label = $occurrence_map[$first_key]['label'];
                }
            }
        } elseif ($default_start !== '' && isset($occurrence_map[$default_start]) && $default_label === '') {
            $default_label = $occurrence_map[$default_start]['label'];
        }

        $result['occurrence_map'] = $occurrence_map;
        $result['default_start'] = $default_start;
        $result['default_label'] = $default_label;

        return $result;
    }
}

wp_enqueue_media();
if (function_exists('wp_enqueue_editor')) {
    wp_enqueue_editor();
}
wp_enqueue_style('wp-color-picker');
wp_enqueue_style(
    'mj-admin-events',
    $baseUrl . 'includes/css/admin-events.css',
    array(),
    $pluginVersion
);
wp_enqueue_script('wp-color-picker');
wp_enqueue_script(
    'mj-admin-events',
    $baseUrl . 'includes/js/admin-events.js',
    array('jquery', 'wp-color-picker'),
    $pluginVersion,
    true
);
$type_color_palette = method_exists('MjEvents_CRUD', 'get_type_colors') ? MjEvents_CRUD::get_type_colors() : array();
$type_colors_payload = array();
if (!empty($type_color_palette) && is_array($type_color_palette)) {
    foreach ($type_color_palette as $palette_type => $palette_color) {
        $palette_type_key = sanitize_key((string) $palette_type);
        $sanitized_color = sanitize_hex_color($palette_color);
        if ($palette_type_key === '' || !is_string($sanitized_color) || $sanitized_color === '') {
            continue;
        }
        $type_colors_payload[$palette_type_key] = strtoupper(strlen($sanitized_color) === 4
            ? '#' . $sanitized_color[1] . $sanitized_color[1] . $sanitized_color[2] . $sanitized_color[2] . $sanitized_color[3] . $sanitized_color[3]
            : $sanitized_color
        );
    }
}
wp_localize_script(
    'mj-admin-events',
    'mjAdminEvents',
    array(
        'restRoot' => esc_url_raw(rest_url('wp/v2/')),
        'restNonce' => wp_create_nonce('wp_rest'),
        'perPage' => 50,
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'typeColors' => $type_colors_payload,
        'payment' => array(
            'nonce' => wp_create_nonce('mj_admin_event_payment'),
            'generating' => __('Generation du lien…', 'mj-member'),
            'linkLabel' => __('Ouvrir le lien de paiement', 'mj-member'),
            'success' => __('Lien de paiement genere.', 'mj-member'),
            'error' => __('Impossible de generer le lien de paiement. Merci de reessayer.', 'mj-member'),
            'amountLabel' => __('Montant : %s EUR', 'mj-member'),
        ),
        'i18n' => array(
            'loading' => __('Chargement…', 'mj-member'),
            'none' => __('Aucun article', 'mj-member'),
            'empty' => __('Aucun article disponible pour cette catégorie.', 'mj-member'),
            'error' => __('Erreur lors du chargement des articles.', 'mj-member'),
            'viewArticle' => __('Voir l’article sur le site', 'mj-member'),
            'noPreview' => __('Cet article ne comporte pas d’aperçu disponible.', 'mj-member'),
            'noImage' => __('Cet article ne possède pas d’image mise en avant.', 'mj-member'),
        ),
    )
);

$action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'add';
$event_id = isset($_GET['event']) ? (int) $_GET['event'] : 0;
$event = null;

if ($action === 'edit') {
    if ($event_id <= 0) {
        wp_die('Evenement introuvable');
    }

    $event = MjEvents_CRUD::find($event_id);
    if (!$event) {
        wp_die('Evenement introuvable');
    }
}

$status_labels = MjEvents_CRUD::get_status_labels();
$type_labels = MjEvents_CRUD::get_type_labels();
$registration_status_labels = MjEventRegistrations::get_status_labels();
$member_role_labels = MjMembers_CRUD::getRoleLabels();
$locations_for_select = MjEventLocations::get_all(array('orderby' => 'name'));
if (is_wp_error($locations_for_select)) {
    $locations_for_select = array();
}
$animateur_assignments_ready = class_exists('MjEventAnimateurs') ? MjEventAnimateurs::is_ready() : false;
$animateur_column_supported = function_exists('mj_member_column_exists') ? mj_member_column_exists(mj_member_get_events_table_name(), 'animateur_id') : false;
if (!$animateur_column_supported && $animateur_assignments_ready && function_exists('mj_member_column_exists')) {
    $animateur_column_supported = mj_member_column_exists(mj_member_get_events_table_name(), 'animateur_id');
}
$animateur_filters = array('role' => MjMembers_CRUD::ROLE_ANIMATEUR);
$animateurs_for_select = MjMembers_CRUD::getAll(0, 0, 'last_name', 'ASC', '', $animateur_filters);
if (!is_array($animateurs_for_select)) {
    $animateurs_for_select = array();
}
$available_animateur_ids = array();
$animateur_index = array();
foreach ($animateurs_for_select as $animateur_item) {
    if (!is_object($animateur_item) || !isset($animateur_item->id)) {
        continue;
    }
    $animateur_id_value = (int) $animateur_item->id;
    if ($animateur_id_value <= 0) {
        continue;
    }
    $available_animateur_ids[$animateur_id_value] = true;
    $animateur_index[$animateur_id_value] = $animateur_item;
}

$schedule_weekdays = array(
    'monday'    => __('Lundi', 'mj-member'),
    'tuesday'   => __('Mardi', 'mj-member'),
    'wednesday' => __('Mercredi', 'mj-member'),
    'thursday'  => __('Jeudi', 'mj-member'),
    'friday'    => __('Vendredi', 'mj-member'),
    'saturday'  => __('Samedi', 'mj-member'),
    'sunday'    => __('Dimanche', 'mj-member'),
);

$schedule_month_ordinals = array(
    'first'  => __('1er', 'mj-member'),
    'second' => __('2e', 'mj-member'),
    'third'  => __('3e', 'mj-member'),
    'fourth' => __('4e', 'mj-member'),
    'last'   => __('Dernier', 'mj-member'),
);

$defaults = MjEvents_CRUD::get_default_values();
$form_values = $defaults;
$form_values['accent_color'] = isset($defaults['accent_color']) ? $defaults['accent_color'] : '';
$form_values['animateur_ids'] = array();
$form_values['schedule_mode'] = isset($defaults['schedule_mode']) ? $defaults['schedule_mode'] : 'fixed';
$form_values['schedule_payload'] = array();
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
$form_values['article_id'] = isset($defaults['article_id']) ? (int) $defaults['article_id'] : 0;
$form_values['article_cat'] = 0;

$timezone = wp_timezone();
$now_timestamp = current_time('timestamp');
$default_start = $now_timestamp + 21 * DAY_IN_SECONDS;
$default_end = $default_start + 2 * HOUR_IN_SECONDS;

$form_values['date_debut'] = wp_date('Y-m-d\TH:i', $default_start, $timezone);
$form_values['date_fin'] = wp_date('Y-m-d\TH:i', $default_end, $timezone);
$form_values['date_fin_inscription'] = '';
$form_values['schedule_fixed_date'] = substr($form_values['date_debut'], 0, 10);
$form_values['schedule_fixed_start_time'] = substr($form_values['date_debut'], 11, 5);
$form_values['schedule_fixed_end_time'] = substr($form_values['date_fin'], 11, 5);
$form_values['schedule_range_start'] = $form_values['date_debut'];
$form_values['schedule_range_end'] = $form_values['date_fin'];

if ($event) {
    $accent_color_value = isset($event->accent_color) ? mj_member_admin_normalize_hex_color($event->accent_color) : '';

    $assigned_animateurs = class_exists('MjEventAnimateurs') ? MjEventAnimateurs::get_ids_by_event($event_id) : array();
    if (empty($assigned_animateurs) && $animateur_column_supported && isset($event->animateur_id) && (int) $event->animateur_id > 0) {
        $assigned_animateurs = array((int) $event->animateur_id);
    }

    $form_values = array_merge($form_values, array(
        'title' => $event->title,
        'status' => $event->status,
        'type' => $event->type,
        'accent_color' => $accent_color_value,
        'cover_id' => (int) $event->cover_id,
        'article_id' => isset($event->article_id) ? (int) $event->article_id : 0,
        'location_id' => (int) (isset($event->location_id) ? $event->location_id : 0),
        'allow_guardian_registration' => !empty($event->allow_guardian_registration) ? 1 : 0,
        'description' => $event->description,
        'age_min' => (int) $event->age_min,
        'age_max' => (int) $event->age_max,
        'date_debut' => mj_member_format_event_datetime($event->date_debut),
        'date_fin' => mj_member_format_event_datetime($event->date_fin),
        'date_fin_inscription' => mj_member_format_event_datetime($event->date_fin_inscription),
        'prix' => number_format((float) $event->prix, 2, '.', ''),
    ));
    $form_values['animateur_ids'] = $assigned_animateurs;
    $form_values['animateur_id'] = !empty($assigned_animateurs) ? (int) $assigned_animateurs[0] : 0;
    mj_member_fill_schedule_form_values($event, $form_values, $schedule_weekdays, $schedule_month_ordinals);
    $form_values['capacity_total'] = isset($event->capacity_total) ? (int) $event->capacity_total : 0;
    $form_values['capacity_waitlist'] = isset($event->capacity_waitlist) ? (int) $event->capacity_waitlist : 0;
    $form_values['capacity_notify_threshold'] = isset($event->capacity_notify_threshold) ? (int) $event->capacity_notify_threshold : 0;
    $capacity_notified_flag = !empty($event->capacity_notified);

    if (!empty($form_values['article_id'])) {
        $article_terms = get_the_category($form_values['article_id']);
        if (!empty($article_terms)) {
            $form_values['article_cat'] = (int) $article_terms[0]->term_id;
        }
    }
}

$form_values['allow_guardian_registration'] = !empty($form_values['allow_guardian_registration']);

$event_type_key = isset($form_values['type']) ? sanitize_key((string) $form_values['type']) : '';
$admin_event_snapshot = array();
$admin_occurrence_map = array();
$registration_default_occurrence = '';
$registration_default_occurrence_label = '';
$registration_selected_occurrence = '';
$registration_reset_selection = false;
$registration_event_type = $event_type_key;
$admin_event_summary_meta = '';
$admin_event_summary_conditions = array();
$admin_summary_counts = array('present' => 0, 'absent' => 0, 'pending' => 0);
$admin_summary_occurrence_label = '';
$admin_summary_total_participants = 0;
$admin_summary_waitlist_count = 0;
$admin_summary_participant_text = '';
$admin_summary_waitlist_text = '';
$admin_summary_attendance_text = '';
$admin_summary_status_labels = array(
    'present' => __('Présents', 'mj-member'),
    'absent' => __('Absents', 'mj-member'),
    'pending' => __('À marquer', 'mj-member'),
);
if (function_exists('mj_member_get_attendance_status_labels')) {
    $status_catalog = mj_member_get_attendance_status_labels();
    if (is_array($status_catalog)) {
        foreach ($admin_summary_status_labels as $status_key => $default_label) {
            if (isset($status_catalog[$status_key]) && $status_catalog[$status_key] !== '') {
                $admin_summary_status_labels[$status_key] = sanitize_text_field((string) $status_catalog[$status_key]);
            }
        }
    }
}

$errors = array();
$success_message = '';
$registration_errors = array();
$registration_success = '';
$registrations = array();
$members_for_select = array();
$registration_selected_member = 0;
$registration_notes_value = '';
$current_event_location = null;
$event_location_preview_html = '';
$capacity_counts = array('active' => 0, 'waitlist' => 0);
$capacity_notified_flag = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mj_event_nonce'])) {
    if (!wp_verify_nonce($_POST['mj_event_nonce'], 'mj_event_form')) {
        wp_die('Verification de securite echouee');
    }

    $title = isset($_POST['event_title']) ? sanitize_text_field($_POST['event_title']) : '';
    if ($title === '') {
        $errors[] = 'Le titre est obligatoire.';
    }

    $status = isset($_POST['event_status']) ? sanitize_key($_POST['event_status']) : MjEvents_CRUD::STATUS_DRAFT;
    if (!array_key_exists($status, $status_labels)) {
        $status = MjEvents_CRUD::STATUS_DRAFT;
    }

    $type = isset($_POST['event_type']) ? sanitize_key($_POST['event_type']) : MjEvents_CRUD::TYPE_STAGE;
    if (!array_key_exists($type, $type_labels)) {
        $type = MjEvents_CRUD::TYPE_STAGE;
    }

    $accent_color_input = '';
    if (isset($_POST['event_accent_color'])) {
        $accent_color_input = mj_member_admin_normalize_hex_color(wp_unslash((string) $_POST['event_accent_color']));
    }

    $cover_id = isset($_POST['event_cover_id']) ? (int) $_POST['event_cover_id'] : 0;

    $age_min = isset($_POST['event_age_min']) ? (int) $_POST['event_age_min'] : (int) $defaults['age_min'];
    $age_max = isset($_POST['event_age_max']) ? (int) $_POST['event_age_max'] : (int) $defaults['age_max'];
    if ($age_min < 0) {
        $age_min = 0;
    }
    if ($age_max < 0) {
        $age_max = 0;
    }
    if ($age_min > $age_max) {
        $errors[] = 'L age minimum doit etre inferieur ou egal a l age maximum.';
    }

    $schedule_mode_input = isset($_POST['event_schedule_mode']) ? sanitize_key($_POST['event_schedule_mode']) : $form_values['schedule_mode'];
    $allowed_schedule_modes = array('fixed', 'range', 'recurring');
    if (!in_array($schedule_mode_input, $allowed_schedule_modes, true)) {
        $schedule_mode_input = 'fixed';
    }

    $date_debut_input = isset($_POST['event_date_start']) ? sanitize_text_field($_POST['event_date_start']) : '';
    $date_fin_input = isset($_POST['event_date_end']) ? sanitize_text_field($_POST['event_date_end']) : '';
    $date_fin_inscription_field_present = array_key_exists('event_date_deadline', $_POST);
    $date_fin_inscription_input = $date_fin_inscription_field_present ? sanitize_text_field($_POST['event_date_deadline']) : '';

    $fixed_date_input = isset($_POST['event_fixed_date']) ? sanitize_text_field($_POST['event_fixed_date']) : '';
    $fixed_start_time_input = isset($_POST['event_fixed_start_time']) ? sanitize_text_field($_POST['event_fixed_start_time']) : '';
    $fixed_end_time_input = isset($_POST['event_fixed_end_time']) ? sanitize_text_field($_POST['event_fixed_end_time']) : '';

    $range_start_input = isset($_POST['event_range_start']) ? sanitize_text_field($_POST['event_range_start']) : '';
    $range_end_input = isset($_POST['event_range_end']) ? sanitize_text_field($_POST['event_range_end']) : '';

    $recurring_start_date = isset($_POST['event_recurring_start_date']) ? sanitize_text_field($_POST['event_recurring_start_date']) : '';
    $recurring_start_time = isset($_POST['event_recurring_start_time']) ? sanitize_text_field($_POST['event_recurring_start_time']) : '';
    $recurring_end_time = isset($_POST['event_recurring_end_time']) ? sanitize_text_field($_POST['event_recurring_end_time']) : '';
    $recurring_frequency = isset($_POST['event_recurring_frequency']) ? sanitize_key($_POST['event_recurring_frequency']) : 'weekly';
    $recurring_interval = isset($_POST['event_recurring_interval']) ? (int) $_POST['event_recurring_interval'] : 1;
    if ($recurring_interval <= 0) {
        $recurring_interval = 1;
    }
    $recurring_weekdays_input = isset($_POST['event_recurring_weekdays']) ? (array) $_POST['event_recurring_weekdays'] : array();
    $recurring_month_ordinal = isset($_POST['event_recurring_month_ordinal']) ? sanitize_key($_POST['event_recurring_month_ordinal']) : 'first';
    $recurring_month_weekday = isset($_POST['event_recurring_month_weekday']) ? sanitize_key($_POST['event_recurring_month_weekday']) : 'saturday';
    $recurrence_until_input = isset($_POST['event_recurring_until']) ? sanitize_text_field($_POST['event_recurring_until']) : '';

    $allowed_weekdays = array_keys($schedule_weekdays);
    if (!in_array($recurring_frequency, array('weekly', 'monthly'), true)) {
        $recurring_frequency = 'weekly';
    }
    if (!isset($schedule_month_ordinals[$recurring_month_ordinal])) {
        $recurring_month_ordinal = 'first';
    }
    if (!isset($schedule_weekdays[$recurring_month_weekday])) {
        $recurring_month_weekday = 'saturday';
    }

    $date_debut = '';
    $date_fin = '';
    $schedule_payload = array();
    $recurrence_until_value = '';
    $recurring_weekdays = array();

    if ($schedule_mode_input === 'recurring') {
        $start_datetime = null;
        $end_datetime = null;

        if ($recurring_start_date === '' || $recurring_start_time === '') {
            $errors[] = 'La date et l\'heure de debut de la recurrence sont obligatoires.';
        } else {
            $start_datetime = DateTime::createFromFormat('Y-m-d H:i', $recurring_start_date . ' ' . $recurring_start_time, $timezone);
            if (!$start_datetime) {
                $errors[] = 'La combinaison date/heure de debut de la recurrence est invalide.';
            }
        }

        if ($recurring_end_time === '') {
            $errors[] = 'L\'heure de fin est obligatoire pour une recurrence.';
        } elseif ($recurring_start_date !== '') {
            $end_datetime = DateTime::createFromFormat('Y-m-d H:i', $recurring_start_date . ' ' . $recurring_end_time, $timezone);
            if (!$end_datetime) {
                $errors[] = 'L\'heure de fin de la recurrence est invalide.';
            }
        }

        if ($start_datetime instanceof DateTime && $end_datetime instanceof DateTime) {
            if ($end_datetime <= $start_datetime) {
                $end_datetime->modify('+1 day');
            }
            $date_debut = $start_datetime->format('Y-m-d H:i:s');
            $date_fin = $end_datetime->format('Y-m-d H:i:s');
            $date_debut_input = $start_datetime->format('Y-m-d\TH:i');
            $date_fin_input = $end_datetime->format('Y-m-d\TH:i');
        }

        if ($recurring_frequency === 'weekly') {
            foreach ($recurring_weekdays_input as $weekday_entry) {
                $weekday_entry = sanitize_key($weekday_entry);
                if (in_array($weekday_entry, $allowed_weekdays, true)) {
                    $recurring_weekdays[$weekday_entry] = $weekday_entry;
                }
            }
            $recurring_weekdays = array_values($recurring_weekdays);
            if (empty($recurring_weekdays)) {
                $errors[] = 'Selectionnez au moins un jour pour la recurrence hebdomadaire.';
            }
            $schedule_payload = array(
                'mode' => 'recurring',
                'frequency' => 'weekly',
                'interval' => $recurring_interval,
                'weekdays' => $recurring_weekdays,
                'start_time' => $recurring_start_time,
                'end_time' => $recurring_end_time,
                'start_date' => $recurring_start_date,
            );
        } else {
            $schedule_payload = array(
                'mode' => 'recurring',
                'frequency' => 'monthly',
                'interval' => $recurring_interval,
                'ordinal' => $recurring_month_ordinal,
                'weekday' => $recurring_month_weekday,
                'start_time' => $recurring_start_time,
                'end_time' => $recurring_end_time,
                'start_date' => $recurring_start_date,
            );
        }

        if ($recurrence_until_input !== '') {
            $end_time_for_until = $recurring_end_time !== '' ? $recurring_end_time : '23:59';
            $until_datetime = DateTime::createFromFormat('Y-m-d H:i', $recurrence_until_input . ' ' . $end_time_for_until, $timezone);
            if (!$until_datetime) {
                $errors[] = 'La date de fin de recurrence est invalide.';
            } elseif ($start_datetime instanceof DateTime && $until_datetime < $start_datetime) {
                $errors[] = 'La date de fin de recurrence doit etre posterieure au premier evenement.';
            } else {
                $recurrence_until_value = $until_datetime->format('Y-m-d H:i:s');
            }
        }
    } else {
        if ($schedule_mode_input === 'fixed') {
            $fixed_start_datetime = null;
            $fixed_end_datetime = null;

            if ($fixed_date_input === '') {
                $errors[] = 'La date fixe est obligatoire.';
            }
            if ($fixed_start_time_input === '') {
                $errors[] = 'L heure de debut est obligatoire.';
            }
            if ($fixed_end_time_input === '') {
                $errors[] = 'L heure de fin est obligatoire.';
            }

            if ($fixed_date_input !== '' && $fixed_start_time_input !== '') {
                $fixed_start_datetime = DateTime::createFromFormat('Y-m-d H:i', $fixed_date_input . ' ' . $fixed_start_time_input, $timezone);
                if (!$fixed_start_datetime) {
                    $errors[] = 'La combinaison date/heure de debut est invalide.';
                }
            }

            if ($fixed_date_input !== '' && $fixed_end_time_input !== '') {
                $fixed_end_datetime = DateTime::createFromFormat('Y-m-d H:i', $fixed_date_input . ' ' . $fixed_end_time_input, $timezone);
                if (!$fixed_end_datetime) {
                    $errors[] = 'La combinaison date/heure de fin est invalide.';
                }
            }

            if ($fixed_start_datetime instanceof DateTime && $fixed_end_datetime instanceof DateTime) {
                if ($fixed_end_datetime <= $fixed_start_datetime) {
                    $errors[] = 'L heure de fin doit etre posterieure a l heure de debut.';
                } else {
                    $date_debut = $fixed_start_datetime->format('Y-m-d H:i:s');
                    $date_fin = $fixed_end_datetime->format('Y-m-d H:i:s');
                    $date_debut_input = $fixed_start_datetime->format('Y-m-d\TH:i');
                    $date_fin_input = $fixed_end_datetime->format('Y-m-d\TH:i');
                }
            }

            $schedule_payload = array(
                'mode' => 'fixed',
                'date' => $fixed_date_input,
                'start_time' => $fixed_start_time_input,
                'end_time' => $fixed_end_time_input,
            );
        } elseif ($schedule_mode_input === 'range') {
            $date_debut = mj_member_parse_event_datetime($range_start_input);
            $date_fin = mj_member_parse_event_datetime($range_end_input);

            if ($date_debut === '') {
                $errors[] = 'La date de debut de la plage est obligatoire.';
            }
            if ($date_fin === '') {
                $errors[] = 'La date de fin de la plage est obligatoire.';
            }
            if ($date_debut !== '' && $date_fin !== '') {
                if (strtotime($date_fin) < strtotime($date_debut)) {
                    $errors[] = 'La date de fin doit etre posterieure a la date de debut.';
                }
            }

            $date_debut_input = $range_start_input;
            $date_fin_input = $range_end_input;

            $schedule_payload = array(
                'mode' => 'range',
                'start' => $range_start_input,
                'end' => $range_end_input,
            );
        } else {
            $date_debut = mj_member_parse_event_datetime($date_debut_input);
            $date_fin = mj_member_parse_event_datetime($date_fin_input);

            if ($date_debut === '') {
                $errors[] = 'La date de debut est obligatoire.';
            }
            if ($date_fin === '') {
                $errors[] = 'La date de fin est obligatoire.';
            }
            if ($date_debut !== '' && $date_fin !== '') {
                if (strtotime($date_fin) < strtotime($date_debut)) {
                    $errors[] = 'La date de fin doit etre posterieure a la date de debut.';
                }
            }

            $schedule_payload = array(
                'mode' => 'fixed',
                'start_time' => ($date_debut_input !== '' ? substr($date_debut_input, 11, 5) : ''),
                'end_time' => ($date_fin_input !== '' ? substr($date_fin_input, 11, 5) : ''),
            );
        }
    }

    $date_fin_inscription = '';
    if ($date_fin_inscription_input !== '') {
        $date_fin_inscription = mj_member_parse_event_datetime($date_fin_inscription_input);
        if ($date_fin_inscription === '') {
            $errors[] = 'La date limite d inscription est invalide.';
        }
    } elseif (!$date_fin_inscription_field_present && $action === 'edit' && $event && !empty($event->date_fin_inscription)) {
        // Preserve the existing deadline when the admin does not submit a new value.
        $date_fin_inscription = $event->date_fin_inscription;
        $date_fin_inscription_input = mj_member_format_event_datetime($event->date_fin_inscription);
    }

    if ($date_fin_inscription !== '' && $date_debut !== '') {
        if (strtotime($date_fin_inscription) > strtotime($date_debut)) {
            $errors[] = 'La date limite d inscription doit etre avant la date de debut.';
        }
    }

    $price_input = isset($_POST['event_price']) ? sanitize_text_field($_POST['event_price']) : '0';
    $price_input = str_replace(',', '.', $price_input);
    $prix = number_format(max(0, (float) $price_input), 2, '.', '');

    $description = isset($_POST['event_description']) ? wp_kses_post(wp_unslash($_POST['event_description'])) : '';

    $location_id = isset($_POST['event_location_id']) ? (int) $_POST['event_location_id'] : 0;
    $location_check = null;
    if ($location_id > 0) {
        $location_check = MjEventLocations::find($location_id);
        if (!$location_check) {
            $errors[] = 'Le lieu selectionne est invalide.';
            $location_id = 0;
            $location_check = null;
        }
    }

    $capacity_total = isset($_POST['event_capacity_total']) ? (int) $_POST['event_capacity_total'] : (int) $form_values['capacity_total'];
    $capacity_waitlist = isset($_POST['event_capacity_waitlist']) ? (int) $_POST['event_capacity_waitlist'] : (int) $form_values['capacity_waitlist'];
    $capacity_notify_threshold = isset($_POST['event_capacity_notify_threshold']) ? (int) $_POST['event_capacity_notify_threshold'] : (int) $form_values['capacity_notify_threshold'];

    $capacity_total = max(0, $capacity_total);
    $capacity_waitlist = max(0, $capacity_waitlist);
    $capacity_notify_threshold = max(0, $capacity_notify_threshold);

    if ($capacity_total === 0) {
        if ($capacity_notify_threshold > 0) {
            $capacity_notify_threshold = 0;
        }
    } elseif ($capacity_notify_threshold >= $capacity_total) {
        $errors[] = "Le seuil d'alerte doit etre strictement inferieur au nombre de places.";
    }

    $animateur_ids_input = isset($_POST['event_animateur_ids']) ? (array) $_POST['event_animateur_ids'] : array();
    $animateur_ids = array();
    foreach ($animateur_ids_input as $candidate_id) {
        $candidate_id = (int) $candidate_id;
        if ($candidate_id <= 0) {
            continue;
        }

        if (!isset($available_animateur_ids[$candidate_id])) {
            $errors[] = 'Un animateur selectionne est invalide.';
            continue;
        }

        $animateur_ids[$candidate_id] = $candidate_id;
    }

    $animateur_ids_list = array_values($animateur_ids);
    $primary_animateur_id = !empty($animateur_ids_list) ? (int) $animateur_ids_list[0] : 0;
    $allow_guardian_registration = !empty($_POST['event_allow_guardian_registration']) ? 1 : 0;

    $form_values = array_merge($form_values, array(
        'title' => $title,
        'status' => $status,
        'type' => $type,
        'accent_color' => $accent_color_input,
        'cover_id' => $cover_id,
        'description' => $description,
        'age_min' => $age_min,
        'age_max' => $age_max,
        'date_debut' => $date_debut_input,
        'date_fin' => $date_fin_input,
        'date_fin_inscription' => $date_fin_inscription_input,
        'prix' => $prix,
        'location_id' => $location_id,
        'animateur_id' => $animateur_column_supported ? $primary_animateur_id : 0,
        'allow_guardian_registration' => ($allow_guardian_registration === 1),
        'schedule_mode' => $schedule_mode_input,
        'recurrence_until' => $recurrence_until_input,
        'schedule_recurring_start_date' => $recurring_start_date,
        'schedule_recurring_start_time' => $recurring_start_time,
        'schedule_recurring_end_time' => $recurring_end_time,
        'schedule_recurring_frequency' => $recurring_frequency,
        'schedule_recurring_interval' => $recurring_interval,
        'schedule_recurring_month_ordinal' => $recurring_month_ordinal,
        'schedule_recurring_month_weekday' => $recurring_month_weekday,
        'schedule_fixed_date' => $fixed_date_input,
        'schedule_fixed_start_time' => $fixed_start_time_input,
        'schedule_fixed_end_time' => $fixed_end_time_input,
        'schedule_range_start' => $range_start_input,
        'schedule_range_end' => $range_end_input,
        'capacity_total' => $capacity_total,
        'capacity_waitlist' => $capacity_waitlist,
        'capacity_notify_threshold' => $capacity_notify_threshold,
        'article_id' => isset($_POST['event_article_id']) ? (int)$_POST['event_article_id'] : 0,
        'article_cat' => isset($_POST['event_article_cat']) ? (int)$_POST['event_article_cat'] : 0,
    ));
    $form_values['animateur_ids'] = $animateur_ids_list;
    $form_values['schedule_recurring_weekdays'] = $recurring_weekdays;
    $form_values['schedule_payload'] = $schedule_payload;

    if (empty($errors)) {
        $capacity_notified_value = 0;
        if ($action === 'edit' && $event) {
            $prev_capacity_total = isset($event->capacity_total) ? (int) $event->capacity_total : 0;
            $prev_capacity_threshold = isset($event->capacity_notify_threshold) ? (int) $event->capacity_notify_threshold : 0;
            $prev_capacity_notified = !empty($event->capacity_notified) ? 1 : 0;
            if ($prev_capacity_total === $capacity_total && $prev_capacity_threshold === $capacity_notify_threshold) {
                $capacity_notified_value = $prev_capacity_notified;
            }
        }

        $payload = array(
            'title' => $title,
            'status' => $status,
            'type' => $type,
            'accent_color' => $accent_color_input,
            'cover_id' => $cover_id,
            'description' => $description,
            'age_min' => $age_min,
            'age_max' => $age_max,
            'date_debut' => $date_debut,
            'date_fin' => $date_fin,
            'date_fin_inscription' => $date_fin_inscription !== '' ? $date_fin_inscription : null,
            'prix' => $prix,
            'location_id' => $location_id,
            'animateur_id' => $animateur_column_supported ? $primary_animateur_id : null,
            'allow_guardian_registration' => $allow_guardian_registration,
            'schedule_mode' => $schedule_mode_input,
            'schedule_payload' => $schedule_payload,
            'recurrence_until' => $recurrence_until_value !== '' ? $recurrence_until_value : null,
            'capacity_total' => $capacity_total,
            'capacity_waitlist' => $capacity_waitlist,
            'capacity_notify_threshold' => $capacity_notify_threshold,
            'capacity_notified' => $capacity_notified_value,
            'article_id' => isset($_POST['event_article_id']) ? (int)$_POST['event_article_id'] : 0,
        );

        if ($action === 'add') {
            $new_id = MjEvents_CRUD::create($payload);
            if ($new_id) {
                $success_message = 'Evenement cree avec succes.';
                $action = 'edit';
                $event_id = $new_id;
                if (class_exists('MjEventAnimateurs')) {
                    MjEventAnimateurs::sync_for_event($event_id, $animateur_ids_list);
                }
                $event = MjEvents_CRUD::find($event_id);
                if ($event) {
                    $form_values['title'] = $event->title;
                    $form_values['status'] = $event->status;
                    $form_values['type'] = $event->type;
                    $form_values['accent_color'] = isset($event->accent_color) ? mj_member_admin_normalize_hex_color($event->accent_color) : '';
                    $form_values['cover_id'] = (int) $event->cover_id;
                    $form_values['article_id'] = isset($event->article_id) ? (int) $event->article_id : 0;
                    $form_values['location_id'] = (int) (isset($event->location_id) ? $event->location_id : 0);
                    $form_values['accent_color'] = isset($event->accent_color) ? mj_member_admin_normalize_hex_color($event->accent_color) : '';
                    $form_values['description'] = $event->description;
                    $form_values['age_min'] = (int) $event->age_min;
                    $form_values['age_max'] = (int) $event->age_max;
                    $form_values['date_debut'] = mj_member_format_event_datetime($event->date_debut);
                    $form_values['date_fin'] = mj_member_format_event_datetime($event->date_fin);
                    $form_values['date_fin_inscription'] = mj_member_format_event_datetime($event->date_fin_inscription);
                    $form_values['prix'] = number_format((float) $event->prix, 2, '.', '');
                    $form_values['allow_guardian_registration'] = !empty($event->allow_guardian_registration);
                    mj_member_fill_schedule_form_values($event, $form_values, $schedule_weekdays, $schedule_month_ordinals);
                    $form_values['capacity_total'] = isset($event->capacity_total) ? (int) $event->capacity_total : 0;
                    $form_values['capacity_waitlist'] = isset($event->capacity_waitlist) ? (int) $event->capacity_waitlist : 0;
                    $form_values['capacity_notify_threshold'] = isset($event->capacity_notify_threshold) ? (int) $event->capacity_notify_threshold : 0;
                    $capacity_notified_flag = !empty($event->capacity_notified);
                    if (!empty($form_values['article_id'])) {
                        $article_terms = get_the_category($form_values['article_id']);
                        if (!empty($article_terms)) {
                            $form_values['article_cat'] = (int) $article_terms[0]->term_id;
                        }
                    }
                }
                if (class_exists('MjEventAnimateurs')) {
                    $synced_ids = MjEventAnimateurs::get_ids_by_event($event_id);
                    $form_values['animateur_ids'] = $synced_ids;
                    $form_values['animateur_id'] = !empty($synced_ids) ? (int) $synced_ids[0] : 0;
                } else {
                    $form_values['animateur_ids'] = $animateur_ids_list;
                    $form_values['animateur_id'] = $primary_animateur_id;
                }
            } else {
                $errors[] = 'Erreur lors de la creation de l evenement.';
            }
        } else {
            $update_result = MjEvents_CRUD::update($event_id, $payload);
            if ($update_result) {
                $success_message = 'Evenement mis a jour avec succes.';
                if (class_exists('MjEventAnimateurs')) {
                    MjEventAnimateurs::sync_for_event($event_id, $animateur_ids_list);
                }
                $event = MjEvents_CRUD::find($event_id);
                if ($event) {
                    $form_values['date_debut'] = mj_member_format_event_datetime($event->date_debut);
                    $form_values['date_fin'] = mj_member_format_event_datetime($event->date_fin);
                    $form_values['date_fin_inscription'] = mj_member_format_event_datetime($event->date_fin_inscription);
                    $form_values['cover_id'] = (int) $event->cover_id;
                    $form_values['article_id'] = isset($event->article_id) ? (int) $event->article_id : 0;
                    $form_values['location_id'] = (int) (isset($event->location_id) ? $event->location_id : 0);
                    $form_values['description'] = $event->description;
                    $form_values['prix'] = number_format((float) $event->prix, 2, '.', '');
                    $form_values['allow_guardian_registration'] = !empty($event->allow_guardian_registration);
                    mj_member_fill_schedule_form_values($event, $form_values, $schedule_weekdays, $schedule_month_ordinals);
                    $form_values['capacity_total'] = isset($event->capacity_total) ? (int) $event->capacity_total : 0;
                    $form_values['capacity_waitlist'] = isset($event->capacity_waitlist) ? (int) $event->capacity_waitlist : 0;
                    $form_values['capacity_notify_threshold'] = isset($event->capacity_notify_threshold) ? (int) $event->capacity_notify_threshold : 0;
                    $capacity_notified_flag = !empty($event->capacity_notified);
                    if (!empty($form_values['article_id'])) {
                        $article_terms = get_the_category($form_values['article_id']);
                        if (!empty($article_terms)) {
                            $form_values['article_cat'] = (int) $article_terms[0]->term_id;
                        }
                    }
                }
                if (class_exists('MjEventAnimateurs')) {
                    $synced_ids = MjEventAnimateurs::get_ids_by_event($event_id);
                    $form_values['animateur_ids'] = $synced_ids;
                    $form_values['animateur_id'] = !empty($synced_ids) ? (int) $synced_ids[0] : 0;
                } else {
                    $form_values['animateur_ids'] = $animateur_ids_list;
                    $form_values['animateur_id'] = $primary_animateur_id;
                }
            } else {
                $errors[] = 'Erreur lors de la mise a jour de l evenement.';
            }
        }
    }
}

if ($action === 'edit' && $event_id > 0 && !$event) {
    $event = MjEvents_CRUD::find($event_id);
}

if ($action === 'edit' && $event_id > 0 && $event) {
    $event_type_key = isset($event->type) ? sanitize_key((string) $event->type) : $event_type_key;

    if (!function_exists('mj_member_prepare_animateur_event_data')) {
        require_once $basePath . 'includes/templates/elementor/animateur_account.php';
    }

    $admin_event_snapshot = mj_member_prepare_animateur_event_data(
        $event,
        0,
        array(
            'include_past_occurrences' => true,
            'occurrence_limit' => 200,
        )
    );
    $occurrence_meta = mj_member_admin_extract_snapshot_meta($admin_event_snapshot);
    $admin_occurrence_map = $occurrence_meta['occurrence_map'];
    $registration_default_occurrence = $occurrence_meta['default_start'];
    $registration_default_occurrence_label = $occurrence_meta['default_label'];
    $registration_selected_occurrence = $registration_default_occurrence;

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mj_event_registration_nonce'])) {
        $nonce_value = $_POST['mj_event_registration_nonce'];
        if (!wp_verify_nonce($nonce_value, 'mj_event_registration_action')) {
            $registration_errors[] = 'Verification de securite echouee.';
        } else {
            $registration_action = isset($_POST['registration_action']) ? sanitize_key($_POST['registration_action']) : '';
            $registration_selected_member = isset($_POST['registration_member_id']) ? (int) $_POST['registration_member_id'] : 0;
            $registration_notes_value = isset($_POST['registration_notes']) ? sanitize_textarea_field(wp_unslash($_POST['registration_notes'])) : '';

            if (isset($_POST['registration_occurrence'])) {
                $raw_occurrence = sanitize_text_field((string) $_POST['registration_occurrence']);
                $normalized_occurrence = '';
                if ($raw_occurrence !== '') {
                    $normalized_occurrence = class_exists('MjEventAttendance')
                        ? MjEventAttendance::normalize_occurrence($raw_occurrence)
                        : $raw_occurrence;
                }

                if ($event_type_key === MjEvents_CRUD::TYPE_ATELIER) {
                    $registration_selected_occurrence = $normalized_occurrence !== '' ? $normalized_occurrence : '';
                } elseif ($normalized_occurrence !== '') {
                    $registration_selected_occurrence = $normalized_occurrence;
                }
            }

            switch ($registration_action) {
                case 'add':
                    $member_id = $registration_selected_member;
                    if ($member_id <= 0) {
                        $registration_errors[] = 'Selectionnez un membre pour l inscription.';
                        break;
                    }

                    $notes = $registration_notes_value;
                    $guardian_override = isset($_POST['registration_guardian_id']) ? (int) $_POST['registration_guardian_id'] : 0;

                    $occurrence_scope_mode = 'all';
                    $scope_occurrences = array();

                    if ($event_type_key === MjEvents_CRUD::TYPE_ATELIER) {
                        if (empty($admin_occurrence_map)) {
                            $registration_errors[] = __('Aucune occurrence n\'est disponible pour cet evenement.', 'mj-member');
                            break;
                        }

                        $scope_occurrence = $registration_selected_occurrence;
                        if ($scope_occurrence === '') {
                            $registration_errors[] = __('Selectionnez une occurrence avant d\'ajouter ce participant.', 'mj-member');
                            break;
                        }

                        if (!isset($admin_occurrence_map[$scope_occurrence])) {
                            $registration_errors[] = __('L occurrence selectionnee est invalide.', 'mj-member');
                            break;
                        }

                        $occurrence_scope_mode = 'custom';
                        $scope_occurrences = array($scope_occurrence);
                    }

                    $create_args = array('notes' => $notes);
                    if ($guardian_override > 0) {
                        $create_args['guardian_id'] = $guardian_override;
                    }

                    $create_result = MjEventRegistrations::create($event_id, $member_id, $create_args);
                    if (is_wp_error($create_result)) {
                        $registration_errors[] = $create_result->get_error_message();
                    } else {
                        $registration_id = (int) $create_result;
                        $assignment_result = true;

                        if (class_exists('MjEventAttendance')) {
                            $assignment_result = MjEventAttendance::set_registration_assignments(
                                $registration_id,
                                array(
                                    'mode' => $occurrence_scope_mode,
                                    'occurrences' => $scope_occurrences,
                                )
                            );
                        }

                        if (is_wp_error($assignment_result)) {
                            MjEventRegistrations::delete($registration_id);
                            $registration_errors[] = $assignment_result->get_error_message();
                        } else {
                            $registration_success = 'Inscription enregistree.';
                            $registration_selected_member = 0;
                            $registration_notes_value = '';
                            $registration_reset_selection = true;
                        }
                    }
                    break;

                case 'update_status':
                    $registration_id = isset($_POST['registration_id']) ? (int) $_POST['registration_id'] : 0;
                    $new_status = isset($_POST['registration_new_status']) ? sanitize_key($_POST['registration_new_status']) : '';
                    if ($registration_id <= 0 || $new_status === '') {
                        $registration_errors[] = 'Action invalide.';
                        break;
                    }

                    $registration = MjEventRegistrations::get($registration_id);
                    if (!$registration || (int) $registration->event_id !== $event_id) {
                        $registration_errors[] = 'Inscription introuvable.';
                        break;
                    }

                    if (!array_key_exists($new_status, $registration_status_labels)) {
                        $registration_errors[] = 'Statut de destination invalide.';
                        break;
                    }

                    if ($registration->statut === $new_status) {
                        $registration_success = 'Le statut est deja a jour.';
                        break;
                    }

                    $update_result = MjEventRegistrations::update_status($registration_id, $new_status);
                    if (is_wp_error($update_result)) {
                        $registration_errors[] = $update_result->get_error_message();
                    } else {
                        $registration_success = 'Statut mis a jour.';
                    }
                    break;

                case 'delete':
                    $registration_id = isset($_POST['registration_id']) ? (int) $_POST['registration_id'] : 0;
                    if ($registration_id <= 0) {
                        $registration_errors[] = 'Action invalide.';
                        break;
                    }

                    $registration = MjEventRegistrations::get($registration_id);
                    if (!$registration || (int) $registration->event_id !== $event_id) {
                        $registration_errors[] = 'Inscription introuvable.';
                        break;
                    }

                    $delete_result = MjEventRegistrations::delete($registration_id);
                    if (is_wp_error($delete_result)) {
                        $registration_errors[] = $delete_result->get_error_message();
                    } else {
                        $registration_success = 'Inscription supprimee.';
                    }
                    break;

                default:
                    $registration_errors[] = 'Action inconnue.';
                    break;
            }
        }
    }

    $registrations = MjEventRegistrations::get_by_event($event_id);
    $members_for_select = MjMembers_CRUD::getAll(0, 0, 'last_name', 'ASC');
    if (!is_array($members_for_select)) {
        $members_for_select = array();
    }
    if (!empty($registrations)) {
        foreach ($registrations as $registration_item) {
            $status_value = isset($registration_item->statut) ? sanitize_key($registration_item->statut) : '';
            if (in_array($status_value, array(MjEventRegistrations::STATUS_PENDING, MjEventRegistrations::STATUS_CONFIRMED), true)) {
                $capacity_counts['active']++;
            } elseif ($status_value === MjEventRegistrations::STATUS_WAITLIST) {
                $capacity_counts['waitlist']++;
            }
        }
    }

    $admin_event_snapshot = mj_member_prepare_animateur_event_data(
        $event,
        0,
        array(
            'include_past_occurrences' => true,
            'occurrence_limit' => 200,
        )
    );
    $occurrence_meta = mj_member_admin_extract_snapshot_meta($admin_event_snapshot);
    $admin_occurrence_map = $occurrence_meta['occurrence_map'];
    if ($registration_reset_selection || $registration_selected_occurrence === '' || !isset($admin_occurrence_map[$registration_selected_occurrence])) {
        $registration_selected_occurrence = $occurrence_meta['default_start'];
    }
    $registration_default_occurrence = $occurrence_meta['default_start'];
    $registration_default_occurrence_label = $occurrence_meta['default_label'];

    $admin_summary_total_participants = isset($admin_event_snapshot['participantsCount']) ? (int) $admin_event_snapshot['participantsCount'] : 0;

    $admin_event_summary_conditions = array();
    if (isset($admin_event_snapshot['conditions']) && is_array($admin_event_snapshot['conditions'])) {
        foreach ($admin_event_snapshot['conditions'] as $condition_entry) {
            $condition_entry = sanitize_text_field((string) $condition_entry);
            if ($condition_entry !== '') {
                $admin_event_summary_conditions[] = $condition_entry;
            }
        }
    }

    $admin_event_summary_meta = '';
    if (!empty($admin_event_snapshot['meta']) && is_array($admin_event_snapshot['meta'])) {
        $meta_parts = array();
        if (!empty($admin_event_snapshot['meta']['typeLabel'])) {
            $meta_parts[] = sanitize_text_field((string) $admin_event_snapshot['meta']['typeLabel']);
        }
        if (!empty($admin_event_snapshot['meta']['dateLabel'])) {
            $meta_parts[] = sanitize_text_field((string) $admin_event_snapshot['meta']['dateLabel']);
        }
        if (!empty($admin_event_snapshot['meta']['locationLabel'])) {
            $meta_parts[] = sanitize_text_field((string) $admin_event_snapshot['meta']['locationLabel']);
        }
        if (!empty($admin_event_snapshot['meta']['priceLabel'])) {
            $meta_parts[] = sanitize_text_field((string) $admin_event_snapshot['meta']['priceLabel']);
        }
        $meta_parts = array_filter($meta_parts);
        if (!empty($meta_parts)) {
            $admin_event_summary_meta = implode(' • ', $meta_parts);
        }
    }

    $admin_summary_occurrence_label = '';
    $counts_reference = array();
    if ($registration_selected_occurrence !== '' && isset($admin_occurrence_map[$registration_selected_occurrence])) {
        $admin_summary_occurrence_label = isset($admin_occurrence_map[$registration_selected_occurrence]['label'])
            ? $admin_occurrence_map[$registration_selected_occurrence]['label']
            : '';
        $counts_reference = isset($admin_occurrence_map[$registration_selected_occurrence]['counts'])
            ? $admin_occurrence_map[$registration_selected_occurrence]['counts']
            : array();
    } elseif ($registration_default_occurrence !== '' && isset($admin_occurrence_map[$registration_default_occurrence])) {
        $admin_summary_occurrence_label = isset($admin_occurrence_map[$registration_default_occurrence]['label'])
            ? $admin_occurrence_map[$registration_default_occurrence]['label']
            : '';
        $counts_reference = isset($admin_occurrence_map[$registration_default_occurrence]['counts'])
            ? $admin_occurrence_map[$registration_default_occurrence]['counts']
            : array();
    } elseif (!empty($admin_occurrence_map)) {
        $first_key = array_key_first($admin_occurrence_map);
        if ($first_key !== null) {
            $admin_summary_occurrence_label = isset($admin_occurrence_map[$first_key]['label']) ? $admin_occurrence_map[$first_key]['label'] : '';
            $counts_reference = isset($admin_occurrence_map[$first_key]['counts']) ? $admin_occurrence_map[$first_key]['counts'] : array();
        }
    }

    if ($admin_summary_occurrence_label === '' && $registration_default_occurrence_label !== '') {
        $admin_summary_occurrence_label = $registration_default_occurrence_label;
    }
    if ($admin_summary_occurrence_label === '' && !empty($admin_event_snapshot['meta']['nextOccurrenceLabel'])) {
        $admin_summary_occurrence_label = sanitize_text_field((string) $admin_event_snapshot['meta']['nextOccurrenceLabel']);
    }

    $present_count = isset($counts_reference['present']) ? (int) $counts_reference['present'] : 0;
    $absent_count = isset($counts_reference['absent']) ? (int) $counts_reference['absent'] : 0;
    $pending_count = isset($counts_reference['pending'])
        ? (int) $counts_reference['pending']
        : max(0, $admin_summary_total_participants - $present_count - $absent_count);

    $admin_summary_counts = array(
        'present' => $present_count,
        'absent' => $absent_count,
        'pending' => $pending_count,
    );
    $admin_summary_waitlist_count = (int) $capacity_counts['waitlist'];

    $capacity_total_limit = isset($form_values['capacity_total']) ? (int) $form_values['capacity_total'] : 0;
    if ($capacity_total_limit > 0) {
        $admin_summary_participant_text = sprintf(
            __('Participants inscrits : %1$d / %2$d', 'mj-member'),
            (int) $capacity_counts['active'],
            $capacity_total_limit
        );
    } else {
        $admin_summary_participant_text = sprintf(
            __('Participants inscrits : %d', 'mj-member'),
            (int) $capacity_counts['active']
        );
    }

    $capacity_waitlist_limit = isset($form_values['capacity_waitlist']) ? (int) $form_values['capacity_waitlist'] : 0;
    if ($capacity_waitlist_limit > 0) {
        $admin_summary_waitlist_text = sprintf(
            __('Liste d attente : %1$d / %2$d', 'mj-member'),
            $admin_summary_waitlist_count,
            $capacity_waitlist_limit
        );
    } elseif ($admin_summary_waitlist_count > 0) {
        $admin_summary_waitlist_text = sprintf(
            __('Liste d attente : %d', 'mj-member'),
            $admin_summary_waitlist_count
        );
    } else {
        $admin_summary_waitlist_text = '';
    }

    if ($admin_summary_occurrence_label !== '') {
        $admin_summary_attendance_text = sprintf(
            '%s : %d • %s : %d • %s : %d',
            $admin_summary_status_labels['present'],
            (int) $admin_summary_counts['present'],
            $admin_summary_status_labels['absent'],
            (int) $admin_summary_counts['absent'],
            $admin_summary_status_labels['pending'],
            (int) $admin_summary_counts['pending']
        );
    } else {
        $admin_summary_attendance_text = '';
    }
}

$manage_locations_url = add_query_arg(array('page' => 'mj_locations'), admin_url('admin.php'));
$current_event_location = null;
$event_location_map_src = '';
$event_location_address = '';
if (!empty($form_values['location_id'])) {
    $current_event_location = MjEventLocations::find((int) $form_values['location_id']);
    if ($current_event_location) {
        $event_location_map_src = MjEventLocations::build_map_embed_src($current_event_location);
        $event_location_address = MjEventLocations::format_address($current_event_location);
    }
}

$animateur_fallback_cache = array();
$assigned_animateurs_display = array();
if (!empty($form_values['animateur_ids']) && is_array($form_values['animateur_ids'])) {
    foreach ($form_values['animateur_ids'] as $assigned_candidate) {
        $assigned_id = (int) $assigned_candidate;
        if ($assigned_id <= 0 || isset($assigned_animateurs_display[$assigned_id])) {
            continue;
        }
        if (isset($animateur_index[$assigned_id])) {
            $assigned_member = $animateur_index[$assigned_id];
        } else {
            if (!isset($animateur_fallback_cache[$assigned_id])) {
                $animateur_fallback_cache[$assigned_id] = MjMembers_CRUD::getById($assigned_id);
            }
            $assigned_member = $animateur_fallback_cache[$assigned_id];
        }
        if ($assigned_member) {
            $assigned_animateurs_display[$assigned_id] = $assigned_member;
        }
    }
}

if ($event) {
    $_GET['event'] = $event_id;
    $_GET['action'] = 'edit';
}

$form_action_url = add_query_arg(
    array(
        'page' => 'mj_events',
        'action' => ($action === 'edit' && $event_id > 0) ? 'edit' : 'add',
    ),
    admin_url('admin.php')
);

if ($action === 'edit' && $event_id > 0) {
    $form_action_url = add_query_arg('event', $event_id, $form_action_url);
}

$current_type_key = isset($form_values['type']) ? sanitize_key((string) $form_values['type']) : '';
$default_accent_color = '';
if ($current_type_key !== '' && isset($type_colors_payload[$current_type_key])) {
    $default_accent_color = $type_colors_payload[$current_type_key];
} elseif (method_exists('MjEvents_CRUD', 'get_default_color_for_type')) {
    $default_candidate = MjEvents_CRUD::get_default_color_for_type($current_type_key);
    $default_accent_color = mj_member_admin_normalize_hex_color($default_candidate);
}
$default_accent_label = $default_accent_color !== '' ? $default_accent_color : '—';
$default_accent_swatch_style = 'display:none;width:18px;height:18px;border-radius:50%;border:1px solid #cbd5f5;vertical-align:middle;margin-right:8px;';
if ($default_accent_color !== '') {
    $default_accent_swatch_style = 'display:inline-block;width:18px;height:18px;border-radius:50%;border:1px solid #cbd5f5;vertical-align:middle;margin-right:8px;background:' . $default_accent_color . ';';
}

if ($success_message !== '') {
    echo '<div class="notice notice-success"><p>' . esc_html($success_message) . '</p></div>';
}

if (!empty($errors)) {
    foreach ($errors as $error) {
        echo '<div class="notice notice-error"><p>' . esc_html($error) . '</p></div>';
    }
}

if ($registration_success !== '') {
    echo '<div class="notice notice-success"><p>' . esc_html($registration_success) . '</p></div>';
}

if (!empty($registration_errors)) {
    foreach ($registration_errors as $registration_error) {
        echo '<div class="notice notice-error"><p>' . esc_html($registration_error) . '</p></div>';
    }
}

$cover_preview = '';
if (!empty($form_values['cover_id'])) {
    $image = wp_get_attachment_image_src((int) $form_values['cover_id'], 'medium');
    if (!empty($image[0])) {
        $cover_preview = '<img src="' . esc_url($image[0]) . '" alt="" style="max-width:240px;height:auto;" />';
    }
}

$article_categories = get_categories(array('hide_empty' => false));
$article_category_ids = !empty($article_categories) ? array_map('intval', wp_list_pluck($article_categories, 'term_id')) : array();
$selected_article_cat = isset($form_values['article_cat']) ? (int) $form_values['article_cat'] : 0;
if ($selected_article_cat <= 0) {
    foreach ($article_categories as $cat_candidate) {
        if (strtolower($cat_candidate->name) === 'ateliers') {
            $selected_article_cat = (int) $cat_candidate->term_id;
            break;
        }
    }
}
if ($selected_article_cat > 0 && !in_array($selected_article_cat, $article_category_ids, true)) {
    $selected_article_cat = !empty($article_category_ids) ? (int) $article_category_ids[0] : 0;
}

$form_values['article_cat'] = $selected_article_cat;

$article_query_args = array(
    'numberposts' => 50,
    'post_status' => 'publish',
    'orderby' => 'date',
    'order' => 'DESC',
);
if ($selected_article_cat > 0) {
    $article_query_args['cat'] = $selected_article_cat;
}
$articles_for_select = get_posts($article_query_args);
$articles_for_select = is_array($articles_for_select) ? $articles_for_select : array();
$articles_index = array();
foreach ($articles_for_select as $article_item) {
    if (!is_object($article_item) || !isset($article_item->ID)) {
        continue;
    }
    $articles_index[(int) $article_item->ID] = true;
}
if (!empty($form_values['article_id']) && empty($articles_index[(int) $form_values['article_id']])) {
    $current_article = get_post((int) $form_values['article_id']);
    if ($current_article && $current_article->post_status === 'publish') {
        array_unshift($articles_for_select, $current_article);
    }
}

$article_preview_html = '';
if (!empty($form_values['article_id'])) {
    $article_url = get_permalink((int) $form_values['article_id']);
    if ($article_url) {
        $article_preview_html .= '<p><a href="' . esc_url($article_url) . '" target="_blank" rel="noopener noreferrer">Voir l\'article sur le site</a></p>';
    }
    $article_thumb = get_the_post_thumbnail((int) $form_values['article_id'], 'medium');
    if ($article_thumb) {
        $article_preview_html .= '<div class="mj-event-article-thumb">' . $article_thumb . '</div>';
    }
}

$back_url = add_query_arg(array('page' => 'mj_events'), admin_url('admin.php'));
$title_text = ($action === 'add') ? 'Ajouter un evenement' : 'Modifier l evenement';
?>

<div class="wrap">
    <div class="mj-event-form-container">
    <h2><?php echo esc_html($title_text); ?></h2>
    <p><a class="button" href="<?php echo esc_url($back_url); ?>">Retour a la liste</a></p>

    <?php if ($action === 'edit' && $event_id > 0 && $event) : ?>
        <div class="mj-admin-event-summary">
            <div class="mj-admin-event-summary__header">
                <div class="mj-admin-event-summary__title-wrapper">
                    <h3 class="mj-admin-event-summary__title"><?php echo esc_html(!empty($admin_event_snapshot['title']) ? $admin_event_snapshot['title'] : $form_values['title']); ?></h3>
                    <?php if ($admin_event_summary_meta !== '') : ?>
                        <div class="mj-admin-event-summary__meta"><?php echo esc_html($admin_event_summary_meta); ?></div>
                    <?php endif; ?>
                </div>
                <div class="mj-admin-event-summary__stats">
                    <?php if (!empty($admin_summary_participant_text)) : ?>
                        <span class="mj-admin-event-summary__stat"><?php echo esc_html($admin_summary_participant_text); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($admin_summary_waitlist_text)) : ?>
                        <span class="mj-admin-event-summary__stat"><?php echo esc_html($admin_summary_waitlist_text); ?></span>
                    <?php endif; ?>
                    <?php if ($admin_summary_occurrence_label !== '') : ?>
                        <span class="mj-admin-event-summary__stat"><?php echo esc_html(sprintf(__('Occurrence suivie : %s', 'mj-member'), $admin_summary_occurrence_label)); ?></span>
                        <?php if (!empty($admin_summary_attendance_text)) : ?>
                            <span class="mj-admin-event-summary__stat"><?php echo esc_html($admin_summary_attendance_text); ?></span>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php if (!empty($admin_event_summary_conditions)) : ?>
                <div class="mj-admin-event-summary__conditions">
                    <div class="mj-admin-event-summary__conditions-title"><?php esc_html_e('Conditions de l evenement', 'mj-member'); ?></div>
                    <ul class="mj-admin-event-summary__conditions-list">
                        <?php foreach ($admin_event_summary_conditions as $condition_item) : ?>
                            <li><?php echo esc_html($condition_item); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <form method="post" class="mj-event-form" action="<?php echo esc_url($form_action_url); ?>">
        <?php wp_nonce_field('mj_event_form', 'mj_event_nonce'); ?>

        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="mj-event-title">Titre</label></th>
                <td>
                    <input type="text" id="mj-event-title" name="event_title" class="regular-text" value="<?php echo esc_attr($form_values['title']); ?>" required />
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="mj-event-status">Statut</label></th>
                <td>
                    <select id="mj-event-status" name="event_status">
                        <?php foreach ($status_labels as $status_key => $status_label) : ?>
                            <option value="<?php echo esc_attr($status_key); ?>" <?php selected($form_values['status'], $status_key); ?>><?php echo esc_html($status_label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="mj-event-type">Type</label></th>
                <td>
                    <select id="mj-event-type" name="event_type">
                        <?php foreach ($type_labels as $type_key => $type_label) : ?>
                            <option value="<?php echo esc_attr($type_key); ?>" <?php selected($form_values['type'], $type_key); ?>><?php echo esc_html($type_label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="mj-event-accent-color">Couleur pastel</label></th>
                <td>
                    <input type="text" id="mj-event-accent-color" name="event_accent_color" class="regular-text" value="<?php echo esc_attr($form_values['accent_color']); ?>" data-default-color="<?php echo esc_attr($default_accent_color); ?>" autocomplete="off" />
                    <div class="mj-event-color-hint" style="margin-top:6px;display:flex;align-items:center;gap:8px;">
                        <span id="mj-event-accent-default-swatch" style="<?php echo esc_attr($default_accent_swatch_style); ?>"></span>
                        <span id="mj-event-accent-default-label" class="description" style="margin:0;"><?php echo esc_html($default_accent_label); ?></span>
                    </div>
                    <p class="description">Sélectionnez une couleur d’accent pour cet événement. Laissez vide pour reprendre la couleur par défaut du type.</p>
                </td>
            </tr>
            <tr>
                <th scope="row">Article lié</th>
                <td>
                    <?php if (!empty($article_categories)) : ?>
                        <label for="mj-event-article-cat">Catégorie&nbsp;:</label>
                        <select id="mj-event-article-cat" name="event_article_cat" style="min-width:180px;">
                            <?php foreach ($article_categories as $category_item) : ?>
                                <option value="<?php echo esc_attr($category_item->term_id); ?>" <?php selected($selected_article_cat, $category_item->term_id); ?>><?php echo esc_html($category_item->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <br />
                        <label for="mj-event-article-id">Article&nbsp;:</label>
                        <select id="mj-event-article-id" name="event_article_id" style="min-width:260px;">
                            <option value="0">Aucun article</option>
                            <?php foreach ($articles_for_select as $post_item) : ?>
                                <?php
                                $post_id = isset($post_item->ID) ? (int) $post_item->ID : 0;
                                if ($post_id <= 0) {
                                    continue;
                                }
                                $post_link = get_permalink($post_id);
                                $thumb_id = get_post_thumbnail_id($post_id);
                                $thumb_src = $thumb_id ? wp_get_attachment_image_url($thumb_id, 'medium') : '';
                                ?>
                                <option value="<?php echo esc_attr($post_id); ?>" data-link="<?php echo esc_attr($post_link ? $post_link : ''); ?>" data-image-id="<?php echo esc_attr($thumb_id ? (int) $thumb_id : 0); ?>" data-image-src="<?php echo esc_attr($thumb_src ? $thumb_src : ''); ?>" <?php selected(isset($form_values['article_id']) ? (int) $form_values['article_id'] : 0, $post_id); ?>><?php echo esc_html(get_the_title($post_item)); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div id="mj-event-article-preview" style="margin-top:10px;<?php echo !empty($article_preview_html) ? '' : 'display:none;'; ?>">
                            <?php echo !empty($article_preview_html) ? wp_kses_post($article_preview_html) : ''; ?>
                        </div>
                        <button type="button" class="button" id="mj-event-article-image" style="margin-top:8px;<?php echo (int) $form_values['article_id'] > 0 ? '' : 'display:none;'; ?>">Utiliser l'image de l'article</button>
                        <p class="description">Sélectionnez un article publié pour le relier à cet événement.</p>
                    <?php else : ?>
                        <p>Aucune catégorie d'articles disponible. Créez des articles depuis l'éditeur WordPress pour activer cette option.</p>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th scope="row">Visuel</th>
                <td>
                    <div id="mj-event-cover-preview" style="margin-bottom:10px;">
                        <?php echo $cover_preview !== '' ? $cover_preview : '<span>Aucun visuel selectionne.</span>'; ?>
                    </div>
                    <input type="hidden" id="mj-event-cover-id" name="event_cover_id" value="<?php echo esc_attr((int) $form_values['cover_id']); ?>" />
                    <button type="button" class="button" id="mj-event-cover-select">Choisir une image</button>
                    <button type="button" class="button" id="mj-event-cover-remove">Retirer</button>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="mj-event-location">Lieu</label></th>
                <td>
                    <select id="mj-event-location" name="event_location_id" style="min-width:260px;">
                        <option value="0">Aucun lieu defini</option>
                        <?php foreach ($locations_for_select as $location_item) : ?>
                            <?php
                            $location_data = (array) $location_item;
                            $location_id_option = isset($location_data['id']) ? (int) $location_data['id'] : 0;
                            if ($location_id_option <= 0) {
                                continue;
                            }
                            $address_option = MjEventLocations::format_address($location_data);
                            $map_option = MjEventLocations::build_map_embed_src($location_data);
                            $notes_option = isset($location_data['notes']) ? $location_data['notes'] : '';
                            $city_option = isset($location_data['city']) ? $location_data['city'] : '';
                            $label_text = isset($location_data['name']) ? $location_data['name'] : 'Lieu #' . $location_id_option;
                            if ($city_option !== '') {
                                $label_text .= ' (' . $city_option . ')';
                            }
                            $option_attributes = sprintf(
                                ' data-address="%s" data-map="%s" data-notes="%s" data-city="%s" data-country="%s"',
                                esc_attr($address_option),
                                esc_attr($map_option),
                                esc_attr($notes_option),
                                esc_attr($city_option),
                                esc_attr(isset($location_data['country']) ? $location_data['country'] : '')
                            );
                            ?>
                            <option value="<?php echo esc_attr($location_id_option); ?>" <?php selected((int) $form_values['location_id'], $location_id_option); echo $option_attributes; ?>>
                                <?php echo esc_html($label_text); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">Administrez les lieux depuis <a href="<?php echo esc_url($manage_locations_url); ?>" target="_blank" rel="noopener noreferrer">la page des lieux</a>.</p>
                    <div id="mj-event-location-preview" class="mj-event-location-preview" style="margin-top:12px;">
                        <?php if ($current_event_location) : ?>
                            <strong><?php echo esc_html($current_event_location->name); ?></strong><br />
                            <?php if ($event_location_address !== '') : ?>
                                <span><?php echo esc_html($event_location_address); ?></span><br />
                            <?php endif; ?>
                            <?php if (!empty($current_event_location->notes)) : ?>
                                <span class="description">Notes: <?php echo esc_html($current_event_location->notes); ?></span><br />
                            <?php endif; ?>
                            <?php if ($event_location_map_src !== '') : ?>
                                <div class="mj-event-location-map" style="margin-top:10px; max-width:520px;">
                                    <iframe src="<?php echo esc_url($event_location_map_src); ?>" width="520" height="260" style="border:0;" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                                </div>
                            <?php endif; ?>
                        <?php else : ?>
                            <p class="description">Choisissez un lieu pour afficher un apercu.</p>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="mj-event-animateur">Animateurs referents</label></th>
                <td>
                    <select id="mj-event-animateur" name="event_animateur_ids[]" style="min-width:260px;" multiple="multiple"<?php echo $animateur_assignments_ready ? '' : ' data-info="legacy-mode"'; ?>>
                        <?php foreach ($animateurs_for_select as $animateur_item) : ?>
                            <?php
                            if (!is_object($animateur_item)) {
                                continue;
                            }
                            $animateur_id_option = isset($animateur_item->id) ? (int) $animateur_item->id : 0;
                            if ($animateur_id_option <= 0) {
                                continue;
                            }
                            $first_name = isset($animateur_item->first_name) ? $animateur_item->first_name : '';
                            $last_name = isset($animateur_item->last_name) ? $animateur_item->last_name : '';
                            $display_name = trim($first_name . ' ' . $last_name);
                            if ($display_name === '') {
                                $display_name = 'Animateur #' . $animateur_id_option;
                            }
                            $email_attr = '';
                            if (!empty($animateur_item->email) && is_email($animateur_item->email)) {
                                $email_attr = ' data-email="' . esc_attr($animateur_item->email) . '"';
                            }
                            $is_selected = in_array($animateur_id_option, $form_values['animateur_ids'], true);
                            ?>
                            <option value="<?php echo esc_attr($animateur_id_option); ?>" <?php selected($is_selected, true, true); echo $email_attr; ?>>
                                <?php echo esc_html($display_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($animateur_assignments_ready) : ?>
                        <p class="description">Selectionnez un ou plusieurs animateurs referents (laissez vide si aucun); chacun recevra une notification lors des nouvelles inscriptions.</p>
                    <?php else : ?>
                        <p class="description">Plusieurs animateurs peuvent etre selectionnes apres la migration des evenements (wp eval "mj_member_upgrade_to_2_3($GLOBALS['wpdb']);"). Pour l instant, seul le premier animateur choisi sera memorise; laissez vide pour aucun.</p>
                    <?php endif; ?>
                    <?php if (!empty($assigned_animateurs_display)) : ?>
                        <div class="mj-event-animateur-list" style="margin-top:8px;">
                            <strong>Animateur(s) assigne(s)</strong>
                            <ul style="margin:6px 0 0 18px;">
                                <?php foreach ($assigned_animateurs_display as $assigned_id => $assigned_member) : ?>
                                    <?php
                                    $assigned_first = isset($assigned_member->first_name) ? $assigned_member->first_name : '';
                                    $assigned_last = isset($assigned_member->last_name) ? $assigned_member->last_name : '';
                                    $assigned_name = trim($assigned_first . ' ' . $assigned_last);
                                    if ($assigned_name === '') {
                                        $assigned_name = 'Membre #' . $assigned_id;
                                    }
                                    $assigned_email = (!empty($assigned_member->email) && is_email($assigned_member->email)) ? $assigned_member->email : '';
                                    $member_edit_url = add_query_arg(
                                        array(
                                            'page' => 'mj_members',
                                            'action' => 'edit',
                                            'member' => $assigned_id,
                                        ),
                                        admin_url('admin.php')
                                    );
                                    ?>
                                    <li>
                                        <a href="<?php echo esc_url($member_edit_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($assigned_name); ?></a>
                                        <?php if ($assigned_email !== '') : ?>
                                            — <a href="mailto:<?php echo esc_attr($assigned_email); ?>"><?php echo esc_html($assigned_email); ?></a>
                                        <?php else : ?>
                                            — <span class="description">Email manquant</span>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="mj-event-allow-guardian">Autoriser les tuteurs</label></th>
                <td>
                    <label for="mj-event-allow-guardian">
                        <input type="checkbox" id="mj-event-allow-guardian" name="event_allow_guardian_registration" value="1" <?php checked(!empty($form_values['allow_guardian_registration']), true); ?> />
                        Les tuteurs peuvent s'inscrire eux-memes a cet evenement
                    </label>
                    <p class="description">Par defaut, seuls les jeunes peuvent s'inscrire. Cochez pour ouvrir les inscriptions aux tuteurs.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="mj-event-capacity-total">Capacite</label></th>
                <td>
                    <div class="mj-capacity-inline">
                        <label for="mj-event-capacity-total">Places max</label>
                        <input type="number" id="mj-event-capacity-total" name="event_capacity_total" min="0" value="<?php echo esc_attr((int) $form_values['capacity_total']); ?>" style="width:90px;" />
                        <label for="mj-event-capacity-waitlist" style="margin-left:12px;">Liste d'attente</label>
                        <input type="number" id="mj-event-capacity-waitlist" name="event_capacity_waitlist" min="0" value="<?php echo esc_attr((int) $form_values['capacity_waitlist']); ?>" style="width:90px;" />
                        <label for="mj-event-capacity-threshold" style="margin-left:12px;">Seuil d'alerte</label>
                        <input type="number" id="mj-event-capacity-threshold" name="event_capacity_notify_threshold" min="0" value="<?php echo esc_attr((int) $form_values['capacity_notify_threshold']); ?>" style="width:90px;" />
                    </div>
                    <p class="description">Laisser 0 pour ne pas limiter les inscriptions ni activer d'alerte.</p>
                    <p class="description">Un email est envoye quand les places restantes sont inferieures ou egales au seuil.</p>
                    <?php if ($action === 'edit' && $event_id > 0) : ?>
                        <p class="description">
                            Inscriptions actives : <?php echo esc_html($capacity_counts['active']); ?><?php if ((int) $form_values['capacity_total'] > 0) : ?> / <?php echo esc_html((int) $form_values['capacity_total']); ?><?php endif; ?>
                        </p>
                        <?php if ((int) $form_values['capacity_waitlist'] > 0) : ?>
                            <p class="description">Liste d'attente : <?php echo esc_html($capacity_counts['waitlist']); ?> / <?php echo esc_html((int) $form_values['capacity_waitlist']); ?></p>
                        <?php else : ?>
                            <p class="description">Liste d'attente : <?php echo esc_html($capacity_counts['waitlist']); ?></p>
                        <?php endif; ?>
                        <?php if ($capacity_notified_flag) : ?>
                            <p class="description">Le seuil courant a deja declenche une notification.</p>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th scope="row">Tranche d ages</th>
                <td>
                    <label for="mj-event-age-min">Minimum</label>
                    <input type="number" id="mj-event-age-min" name="event_age_min" min="0" max="120" value="<?php echo esc_attr((int) $form_values['age_min']); ?>" style="width:70px;" />
                    <label for="mj-event-age-max" style="margin-left:12px;">Maximum</label>
                    <input type="number" id="mj-event-age-max" name="event_age_max" min="0" max="120" value="<?php echo esc_attr((int) $form_values['age_max']); ?>" style="width:70px;" />
                </td>
            </tr>
            <tr>
                <th scope="row">Planification</th>
                <td>
                    <fieldset class="mj-event-schedule-mode">
                        <legend class="screen-reader-text">Mode de planification</legend>
                        <label class="mj-event-schedule-mode__option">
                            <input type="radio" name="event_schedule_mode" value="fixed" <?php checked($form_values['schedule_mode'], 'fixed'); ?> />
                            Date fixe (debut et fin le meme jour)
                        </label>
                        <label class="mj-event-schedule-mode__option">
                            <input type="radio" name="event_schedule_mode" value="range" <?php checked($form_values['schedule_mode'], 'range'); ?> />
                            Plage de dates (plusieurs jours consecutifs)
                        </label>
                        <label class="mj-event-schedule-mode__option">
                            <input type="radio" name="event_schedule_mode" value="recurring" <?php checked($form_values['schedule_mode'], 'recurring'); ?> />
                            Recurrence (hebdomadaire ou mensuelle)
                        </label>
                    </fieldset>
                    <p class="description">Choisissez la facon de planifier l evenement; les sections ci-dessous s adaptent au mode selectionne.</p>
                    <input type="hidden" id="mj-event-date-start" name="event_date_start" value="<?php echo esc_attr($form_values['date_debut']); ?>" />
                    <input type="hidden" id="mj-event-date-end" name="event_date_end" value="<?php echo esc_attr($form_values['date_fin']); ?>" />
                </td>
            </tr>
            <tr class="mj-schedule-section<?php echo $form_values['schedule_mode'] === 'fixed' ? ' is-active' : ''; ?>" data-schedule-mode="fixed">
                <th scope="row">Date fixe</th>
                <td>
                    <div class="mj-schedule-card">
                        <strong>Jour et horaire</strong>
                        <div class="mj-schedule-inline">
                            <label for="mj-event-fixed-date">Jour</label>
                            <input type="date" id="mj-event-fixed-date" name="event_fixed_date" value="<?php echo esc_attr($form_values['schedule_fixed_date']); ?>" />
                            <label for="mj-event-fixed-start-time">Debut</label>
                            <input type="time" id="mj-event-fixed-start-time" name="event_fixed_start_time" value="<?php echo esc_attr($form_values['schedule_fixed_start_time']); ?>" />
                            <label for="mj-event-fixed-end-time">Fin</label>
                            <input type="time" id="mj-event-fixed-end-time" name="event_fixed_end_time" value="<?php echo esc_attr($form_values['schedule_fixed_end_time']); ?>" />
                        </div>
                        <p class="description">Utilisez cette option pour un evenement sur une seule journee avec un creneau horaire.</p>
                    </div>
                </td>
            </tr>
            <tr class="mj-schedule-section<?php echo $form_values['schedule_mode'] === 'range' ? ' is-active' : ''; ?>" data-schedule-mode="range">
                <th scope="row">Plage de dates</th>
                <td>
                    <div class="mj-schedule-card">
                        <strong>Intervalle</strong>
                        <div class="mj-schedule-inline">
                            <label for="mj-event-range-start">Debut</label>
                            <input type="datetime-local" id="mj-event-range-start" name="event_range_start" value="<?php echo esc_attr($form_values['schedule_range_start']); ?>" />
                            <label for="mj-event-range-end">Fin</label>
                            <input type="datetime-local" id="mj-event-range-end" name="event_range_end" value="<?php echo esc_attr($form_values['schedule_range_end']); ?>" />
                        </div>
                        <p class="description">Choisissez cette option pour un evenement etale sur plusieurs jours.</p>
                    </div>
                </td>
            </tr>
            <tr class="mj-schedule-section<?php echo $form_values['schedule_mode'] === 'recurring' ? ' is-active' : ''; ?>" data-schedule-mode="recurring">
                <th scope="row">Recurrence</th>
                <td>
                    <div class="mj-schedule-card">
                        <strong>Premiere occurrence</strong>
                        <div class="mj-schedule-inline">
                            <label for="mj-event-recurring-start-date">Jour</label>
                            <input type="date" id="mj-event-recurring-start-date" name="event_recurring_start_date" value="<?php echo esc_attr($form_values['schedule_recurring_start_date']); ?>" />
                            <label for="mj-event-recurring-start-time">Debut</label>
                            <input type="time" id="mj-event-recurring-start-time" name="event_recurring_start_time" value="<?php echo esc_attr($form_values['schedule_recurring_start_time']); ?>" />
                            <label for="mj-event-recurring-end-time">Fin</label>
                            <input type="time" id="mj-event-recurring-end-time" name="event_recurring_end_time" value="<?php echo esc_attr($form_values['schedule_recurring_end_time']); ?>" />
                        </div>

                        <strong>Frequence</strong>
                        <div class="mj-schedule-inline">
                            <label for="mj-event-recurring-frequency" class="screen-reader-text">Frequence</label>
                            <select id="mj-event-recurring-frequency" name="event_recurring_frequency">
                                <option value="weekly"<?php selected($form_values['schedule_recurring_frequency'], 'weekly'); ?>>Hebdomadaire</option>
                                <option value="monthly"<?php selected($form_values['schedule_recurring_frequency'], 'monthly'); ?>>Mensuelle</option>
                            </select>
                            <label for="mj-event-recurring-interval">Toutes les</label>
                            <input type="number" id="mj-event-recurring-interval" name="event_recurring_interval" min="1" value="<?php echo esc_attr((int) $form_values['schedule_recurring_interval']); ?>" style="width:70px;" />
                            <span>semaine(s) / mois</span>
                        </div>

                        <div class="mj-recurring-section mj-recurring-weekly" style="margin-bottom:10px;">
                            <strong>Jours concernes</strong>
                            <div class="mj-recurring-weekdays" style="margin-top:6px;">
                                <?php foreach ($schedule_weekdays as $weekday_key => $weekday_label) : ?>
                                    <?php $weekday_checked = in_array($weekday_key, $form_values['schedule_recurring_weekdays'], true); ?>
                                    <label style="display:inline-block; margin-right:12px;">
                                        <input type="checkbox" name="event_recurring_weekdays[]" value="<?php echo esc_attr($weekday_key); ?>" <?php checked($weekday_checked, true); ?> />
                                        <?php echo esc_html($weekday_label); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <p class="description">Choisissez au moins un jour de la semaine.</p>
                        </div>

                        <div class="mj-recurring-section mj-recurring-monthly" style="margin-bottom:10px;">
                            <strong>Periodicite mensuelle</strong>
                            <div class="mj-schedule-inline">
                                <label for="mj-event-recurring-month-ordinal" class="screen-reader-text">Occurrence dans le mois</label>
                                <select id="mj-event-recurring-month-ordinal" name="event_recurring_month_ordinal">
                                    <?php foreach ($schedule_month_ordinals as $ordinal_key => $ordinal_label) : ?>
                                        <option value="<?php echo esc_attr($ordinal_key); ?>"<?php selected($form_values['schedule_recurring_month_ordinal'], $ordinal_key); ?>><?php echo esc_html($ordinal_label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="mj-event-recurring-month-weekday" class="screen-reader-text">Jour cible</label>
                                <select id="mj-event-recurring-month-weekday" name="event_recurring_month_weekday">
                                    <?php foreach ($schedule_weekdays as $weekday_key => $weekday_label) : ?>
                                        <option value="<?php echo esc_attr($weekday_key); ?>"<?php selected($form_values['schedule_recurring_month_weekday'], $weekday_key); ?>><?php echo esc_html($weekday_label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <p class="description">Exemple : "1er samedi".</p>
                        </div>

                        <strong>Fin de la recurrence</strong>
                        <div class="mj-schedule-inline">
                            <label for="mj-event-recurring-until" class="screen-reader-text">Fin de la recurrence</label>
                            <input type="date" id="mj-event-recurring-until" name="event_recurring_until" value="<?php echo esc_attr($form_values['recurrence_until']); ?>" />
                            <span class="description">(optionnel)</span>
                        </div>
                        <p class="description">Laisser vide pour poursuivre la recurrence sans date de fin.</p>
                    </div>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="mj-event-date-deadline">Date limite d inscription</label></th>
                <td>
                    <input type="datetime-local" id="mj-event-date-deadline" name="event_date_deadline" value="<?php echo esc_attr($form_values['date_fin_inscription']); ?>" />
                    <p class="description">Laisser vide pour utiliser la date par defaut (14 jours avant le debut).</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="mj-event-price">Tarif</label></th>
                <td>
                    <input type="number" id="mj-event-price" name="event_price" step="0.01" min="0" value="<?php echo esc_attr($form_values['prix']); ?>" /> €
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="mj-event-description">Description detaillee</label></th>
                <td>
                    <?php
                    wp_editor(
                        $form_values['description'],
                        'mj_event_description',
                        array(
                            'textarea_name' => 'event_description',
                            'media_buttons' => true,
                            'textarea_rows' => 12,
                            'quicktags' => true,
                        )
                    );
                    ?>
                </td>
            </tr>
        </table>

        <?php submit_button($action === 'add' ? 'Enregistrer l evenement' : 'Mettre a jour l evenement'); ?>
    </form>

    <?php if ($action === 'edit' && $event_id > 0 && $event) : ?>
        <?php
        $active_member_ids = array();
        foreach ($registrations as $registration_item) {
            if (empty($registration_item->member_id)) {
                continue;
            }

            if ($registration_item->statut !== MjEventRegistrations::STATUS_CANCELLED) {
                $active_member_ids[(int) $registration_item->member_id] = true;
            }
        }
        $guardian_cache = array();
        $event_price_amount = ($event && isset($event->prix)) ? (float) $event->prix : 0.0;
        ?>

        <hr />

        <div class="mj-event-registrations">
            <h2>Inscriptions</h2>
            <form method="post" class="mj-event-registration-form">
                <?php wp_nonce_field('mj_event_registration_action', 'mj_event_registration_nonce'); ?>
                <input type="hidden" name="registration_action" value="add" />

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="mj-event-registration-member">Participant</label></th>
                        <td>
                            <select id="mj-event-registration-member" name="registration_member_id" required style="min-width:260px;">
                                <option value="">Choisir un membre</option>
                                <?php foreach ($members_for_select as $member_option) : ?>
                                    <?php
                                    if (isset($member_option->status) && $member_option->status !== MjMembers_CRUD::STATUS_ACTIVE) {
                                        continue;
                                    }
                                    $option_id = isset($member_option->id) ? (int) $member_option->id : 0;
                                    if ($option_id <= 0) {
                                        continue;
                                    }
                                    $option_label = trim(($member_option->last_name ?? '') . ' ' . ($member_option->first_name ?? ''));
                                    if ($option_label === '') {
                                        $option_label = 'Membre #' . $option_id;
                                    }
                                    $role_key = isset($member_option->role) ? $member_option->role : '';
                                    $role_label = isset($member_role_labels[$role_key]) ? $member_role_labels[$role_key] : $role_key;
                                    $already = isset($active_member_ids[$option_id]);
                                    $note = $already ? ' (deja inscrit)' : '';
                                    ?>
                                    <option value="<?php echo esc_attr($option_id); ?>" <?php selected($registration_selected_member, $option_id); ?> <?php echo $already ? 'disabled' : ''; ?>>
                                        <?php echo esc_html($option_label . ' - ' . $role_label . $note); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Les membres deja inscrits sont desactives dans la liste.</p>
                        </td>
                    </tr>
                    <?php if ($event_type_key === MjEvents_CRUD::TYPE_ATELIER) : ?>
                    <tr>
                        <th scope="row"><label for="mj-event-registration-occurrence">Occurrence</label></th>
                        <td>
                            <?php if (!empty($admin_occurrence_map)) : ?>
                                <select id="mj-event-registration-occurrence" name="registration_occurrence" required style="min-width:260px;">
                                    <option value=""><?php esc_html_e('Choisir une occurrence', 'mj-member'); ?></option>
                                    <?php foreach ($admin_occurrence_map as $occurrence_key => $occurrence_meta) : ?>
                                        <?php
                                        $option_label = isset($occurrence_meta['label']) ? $occurrence_meta['label'] : $occurrence_key;
                                        $counts = isset($occurrence_meta['counts']) && is_array($occurrence_meta['counts']) ? $occurrence_meta['counts'] : array();
                                        $present = isset($counts['present']) ? (int) $counts['present'] : 0;
                                        $absent = isset($counts['absent']) ? (int) $counts['absent'] : 0;
                                        $pending = isset($counts['pending']) ? (int) $counts['pending'] : 0;
                                        $summary = sprintf(
                                            '%s : %d • %s : %d • %s : %d',
                                            $admin_summary_status_labels['present'],
                                            $present,
                                            $admin_summary_status_labels['absent'],
                                            $absent,
                                            $admin_summary_status_labels['pending'],
                                            $pending
                                        );
                                        ?>
                                        <option value="<?php echo esc_attr($occurrence_key); ?>" <?php selected($registration_selected_occurrence, $occurrence_key); ?>>
                                            <?php echo esc_html($option_label . ' - ' . $summary); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description"><?php esc_html_e('Selectionnez la seance a attribuer a ce participant.', 'mj-member'); ?></p>
                            <?php else : ?>
                                <p class="description"><?php esc_html_e('Aucune occurrence n est disponible. Enregistrez ou mettez a jour le planning.', 'mj-member'); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php elseif ($event_type_key === MjEvents_CRUD::TYPE_STAGE) : ?>
                    <tr>
                        <th scope="row">Occurrences</th>
                        <td>
                            <p class="description"><?php esc_html_e('Chaque inscription couvre toutes les occurrences planifiees du stage. Selectionnez une occurrence ci-dessous pour suivre les compteurs journaliers avant de pointer les presences.', 'mj-member'); ?></p>
                            <?php if (!empty($admin_occurrence_map)) : ?>
                                <ul class="mj-admin-event-occurrence-list">
                                    <?php foreach ($admin_occurrence_map as $occurrence_key => $occurrence_meta) : ?>
                                        <?php
                                        $label = isset($occurrence_meta['label']) ? $occurrence_meta['label'] : $occurrence_key;
                                        $counts = isset($occurrence_meta['counts']) && is_array($occurrence_meta['counts']) ? $occurrence_meta['counts'] : array();
                                        $summary_bits = array();
                                        $present = isset($counts['present']) ? (int) $counts['present'] : 0;
                                        $absent = isset($counts['absent']) ? (int) $counts['absent'] : 0;
                                        $pending = isset($counts['pending']) ? (int) $counts['pending'] : 0;
                                        if ($present > 0) {
                                            $summary_bits[] = sprintf('%s : %d', $admin_summary_status_labels['present'], $present);
                                        }
                                        if ($absent > 0) {
                                            $summary_bits[] = sprintf('%s : %d', $admin_summary_status_labels['absent'], $absent);
                                        }
                                        if ($pending > 0) {
                                            $summary_bits[] = sprintf('%s : %d', $admin_summary_status_labels['pending'], $pending);
                                        }
                                        $summary_text = !empty($summary_bits) ? implode(' • ', $summary_bits) : '';
                                        ?>
                                        <li>
                                            <label class="mj-admin-event-occurrence-option">
                                                <input type="radio" name="registration_occurrence" value="<?php echo esc_attr($occurrence_key); ?>" <?php checked($registration_selected_occurrence, $occurrence_key); ?> />
                                                <span class="mj-admin-event-occurrence-option__label"><?php echo esc_html($label); ?></span>
                                                <?php if ($summary_text !== '') : ?>
                                                    <span class="mj-admin-event-occurrence-option__meta"><?php echo esc_html($summary_text); ?></span>
                                                <?php endif; ?>
                                            </label>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else : ?>
                                <p class="description"><?php esc_html_e('Aucune occurrence n est encore definie pour ce stage. Enregistrez ou mettez a jour le planning.', 'mj-member'); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php else : ?>
                    <tr>
                        <th scope="row">Occurrences</th>
                        <td>
                            <?php if (!empty($admin_occurrence_map)) : ?>
                                <p class="description"><?php esc_html_e('Selectionnez une occurrence pour visualiser les compteurs de presence.', 'mj-member'); ?></p>
                                <ul class="mj-admin-event-occurrence-list">
                                    <?php foreach ($admin_occurrence_map as $occurrence_key => $occurrence_meta) : ?>
                                        <?php
                                        $label = isset($occurrence_meta['label']) ? $occurrence_meta['label'] : $occurrence_key;
                                        $counts = isset($occurrence_meta['counts']) && is_array($occurrence_meta['counts']) ? $occurrence_meta['counts'] : array();
                                        $summary_bits = array();
                                        $present = isset($counts['present']) ? (int) $counts['present'] : 0;
                                        $absent = isset($counts['absent']) ? (int) $counts['absent'] : 0;
                                        $pending = isset($counts['pending']) ? (int) $counts['pending'] : 0;
                                        if ($present > 0) {
                                            $summary_bits[] = sprintf('%s : %d', $admin_summary_status_labels['present'], $present);
                                        }
                                        if ($absent > 0) {
                                            $summary_bits[] = sprintf('%s : %d', $admin_summary_status_labels['absent'], $absent);
                                        }
                                        if ($pending > 0) {
                                            $summary_bits[] = sprintf('%s : %d', $admin_summary_status_labels['pending'], $pending);
                                        }
                                        $summary_text = !empty($summary_bits) ? implode(' • ', $summary_bits) : '';
                                        ?>
                                        <li>
                                            <label class="mj-admin-event-occurrence-option">
                                                <input type="radio" name="registration_occurrence" value="<?php echo esc_attr($occurrence_key); ?>" <?php checked($registration_selected_occurrence, $occurrence_key); ?> />
                                                <span class="mj-admin-event-occurrence-option__label"><?php echo esc_html($label); ?></span>
                                                <?php if ($summary_text !== '') : ?>
                                                    <span class="mj-admin-event-occurrence-option__meta"><?php echo esc_html($summary_text); ?></span>
                                                <?php endif; ?>
                                            </label>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else : ?>
                                <p class="description"><?php esc_html_e('Aucune occurrence n est disponible. Enregistrez ou mettez a jour le planning.', 'mj-member'); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th scope="row"><label for="mj-event-registration-notes">Notes internes</label></th>
                        <td>
                            <textarea id="mj-event-registration-notes" name="registration_notes" rows="3" cols="50"><?php echo esc_textarea($registration_notes_value); ?></textarea>
                            <p class="description">Ces notes ne sont pas envoyees par email.</p>
                        </td>
                    </tr>
                </table>

                <?php submit_button('Ajouter cette inscription', 'primary', 'submit', false); ?>
            </form>

            <h3>Participants actuels</h3>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>Participant</th>
                        <th>Tuteur</th>
                        <th>Statut</th>
                        <th>Date</th>
                        <th>Notes</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($registrations)) : ?>
                        <tr>
                            <td colspan="6">Aucune inscription enregistree pour cet evenement.</td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($registrations as $registration_entry) : ?>
                            <?php
                            $member_label = trim(($registration_entry->last_name ?? '') . ' ' . ($registration_entry->first_name ?? ''));
                            if ($member_label === '') {
                                $member_label = 'Membre #' . (int) $registration_entry->member_id;
                            }
                            $guardian_label = '—';
                            if (!empty($registration_entry->guardian_id)) {
                                $guardian_id = (int) $registration_entry->guardian_id;
                                if (!isset($guardian_cache[$guardian_id])) {
                                    $guardian_cache[$guardian_id] = MjMembers_CRUD::getById($guardian_id);
                                }
                                $guardian_member = $guardian_cache[$guardian_id];
                                if ($guardian_member) {
                                    $guardian_label = trim(($guardian_member->last_name ?? '') . ' ' . ($guardian_member->first_name ?? ''));
                                    if ($guardian_label === '') {
                                        $guardian_label = 'Tuteur #' . $guardian_id;
                                    }
                                }
                            }
                            $status_key = $registration_entry->statut;
                            $status_label = isset($registration_status_labels[$status_key]) ? $registration_status_labels[$status_key] : $status_key;
                            $date_display = !empty($registration_entry->created_at) ? wp_date('d/m/Y H:i', strtotime($registration_entry->created_at)) : '';

                            $assignment_scope = isset($registration_entry->occurrence_assignments) && is_array($registration_entry->occurrence_assignments)
                                ? $registration_entry->occurrence_assignments
                                : array('mode' => 'all', 'occurrences' => array());

                            $assignment_text = '';
                            if ($event_type_key === MjEvents_CRUD::TYPE_ATELIER) {
                                if (isset($assignment_scope['mode']) && $assignment_scope['mode'] === 'custom' && !empty($assignment_scope['occurrences'])) {
                                    $scope_labels = array();
                                    foreach ($assignment_scope['occurrences'] as $scope_occurrence) {
                                        $normalized_scope = class_exists('MjEventAttendance')
                                            ? MjEventAttendance::normalize_occurrence($scope_occurrence)
                                            : (string) $scope_occurrence;
                                        if ($normalized_scope !== '' && isset($admin_occurrence_map[$normalized_scope]['label'])) {
                                            $scope_labels[] = $admin_occurrence_map[$normalized_scope]['label'];
                                        } elseif ($normalized_scope !== '') {
                                            $scope_labels[] = $normalized_scope;
                                        }
                                    }
                                    $scope_labels = array_unique(array_filter(array_map('sanitize_text_field', $scope_labels)));
                                    if (!empty($scope_labels)) {
                                        $assignment_text = sprintf(__('Occurrence assignee : %s', 'mj-member'), implode(', ', $scope_labels));
                                    }
                                } else {
                                    $assignment_text = __('Occurrence assignee : toutes les seances', 'mj-member');
                                }
                            } else {
                                $assignment_text = __('Reservation : toutes les occurrences', 'mj-member');
                            }
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($member_label); ?></strong><br />
                                    <span class="description"><?php echo esc_html(isset($member_role_labels[$registration_entry->role]) ? $member_role_labels[$registration_entry->role] : $registration_entry->role); ?></span>
                                    <?php if ($assignment_text !== '') : ?>
                                        <span class="mj-event-registration-assignment"><?php echo esc_html($assignment_text); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($guardian_label); ?></td>
                                <td><?php echo esc_html($status_label); ?></td>
                                <td><?php echo esc_html($date_display); ?></td>
                                <td><?php echo $registration_entry->notes !== null ? esc_html($registration_entry->notes) : '—'; ?></td>
                                <td>
                                    <div style="display:flex; gap:4px; flex-wrap:wrap;">
                                        <?php if ($event_price_amount > 0 && isset($registration_entry->statut) && $registration_entry->statut !== MjEventRegistrations::STATUS_WAITLIST && $registration_entry->statut !== MjEventRegistrations::STATUS_CANCELLED) : ?>
                                            <button type="button" class="button button-small mj-event-payment-link" data-event-id="<?php echo esc_attr((int) $event_id); ?>" data-registration-id="<?php echo esc_attr((int) $registration_entry->id); ?>" data-member-id="<?php echo esc_attr((int) $registration_entry->member_id); ?>"><?php esc_html_e('Lien de paiement', 'mj-member'); ?></button>
                                        <?php endif; ?>
                                        <?php foreach ($registration_status_labels as $status_value => $status_title) : ?>
                                            <?php if ($status_value === $registration_entry->statut) { continue; } ?>
                                            <form method="post" style="display:inline;">
                                                <?php wp_nonce_field('mj_event_registration_action', 'mj_event_registration_nonce'); ?>
                                                <input type="hidden" name="registration_action" value="update_status" />
                                                <input type="hidden" name="registration_id" value="<?php echo esc_attr((int) $registration_entry->id); ?>" />
                                                <input type="hidden" name="registration_new_status" value="<?php echo esc_attr($status_value); ?>" />
                                                <button type="submit" class="button button-small"><?php echo esc_html($status_title); ?></button>
                                            </form>
                                        <?php endforeach; ?>
                                        <form method="post" style="display:inline;">
                                            <?php wp_nonce_field('mj_event_registration_action', 'mj_event_registration_nonce'); ?>
                                            <input type="hidden" name="registration_action" value="delete" />
                                            <input type="hidden" name="registration_id" value="<?php echo esc_attr((int) $registration_entry->id); ?>" />
                                            <button type="submit" class="button button-small" onclick="return confirm('Supprimer cette inscription ?');">Supprimer</button>
                                        </form>
                                    </div>
                                    <div class="mj-event-payment-output" data-registration-id="<?php echo esc_attr((int) $registration_entry->id); ?>"></div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    </div>
</div>
