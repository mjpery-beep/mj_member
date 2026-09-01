<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can(MJ_MEMBER_CAPABILITY)) {
    wp_die('Acces refuse');
}

require_once plugin_dir_path(__FILE__) . 'classes/MjEvents_CRUD.php';
require_once plugin_dir_path(__FILE__) . 'classes/MjMembers_CRUD.php';
require_once plugin_dir_path(__FILE__) . 'classes/MjEventAnimateurs.php';
require_once plugin_dir_path(__FILE__) . 'classes/MjEventRegistrations.php';
require_once plugin_dir_path(__FILE__) . 'classes/MjEventLocations.php';

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

wp_enqueue_media();
if (function_exists('wp_enqueue_editor')) {
    wp_enqueue_editor();
}
wp_enqueue_script(
    'mj-admin-events',
    MJ_MEMBER_URL . 'includes/js/admin-events.js',
    array('jquery'),
    MJ_MEMBER_VERSION,
    true
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

$defaults = MjEvents_CRUD::get_default_values();
$form_values = $defaults;
$form_values['animateur_ids'] = array();

$timezone = wp_timezone();
$now_timestamp = current_time('timestamp');
$default_start = $now_timestamp + 21 * DAY_IN_SECONDS;
$default_end = $default_start + 2 * HOUR_IN_SECONDS;
$default_deadline = $default_start - 14 * DAY_IN_SECONDS;

$form_values['date_debut'] = wp_date('Y-m-d\TH:i', $default_start, $timezone);
$form_values['date_fin'] = wp_date('Y-m-d\TH:i', $default_end, $timezone);
$form_values['date_fin_inscription'] = wp_date('Y-m-d\TH:i', $default_deadline, $timezone);

if ($event) {
    $assigned_animateurs = class_exists('MjEventAnimateurs') ? MjEventAnimateurs::get_ids_by_event($event_id) : array();
    if (empty($assigned_animateurs) && $animateur_column_supported && isset($event->animateur_id) && (int) $event->animateur_id > 0) {
        $assigned_animateurs = array((int) $event->animateur_id);
    }

    $form_values = array_merge($form_values, array(
        'title' => $event->title,
        'status' => $event->status,
        'type' => $event->type,
        'cover_id' => (int) $event->cover_id,
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
}

$form_values['allow_guardian_registration'] = !empty($form_values['allow_guardian_registration']);

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

    $date_debut_input = isset($_POST['event_date_start']) ? sanitize_text_field($_POST['event_date_start']) : '';
    $date_fin_input = isset($_POST['event_date_end']) ? sanitize_text_field($_POST['event_date_end']) : '';
    $date_fin_inscription_input = isset($_POST['event_date_deadline']) ? sanitize_text_field($_POST['event_date_deadline']) : '';

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

    $date_fin_inscription = '';
    if ($date_fin_inscription_input !== '') {
        $date_fin_inscription = mj_member_parse_event_datetime($date_fin_inscription_input);
        if ($date_fin_inscription === '') {
            $errors[] = 'La date limite d inscription est invalide.';
        }
    } elseif ($date_debut !== '') {
        $timezone = wp_timezone();
        $deadline = date_create($date_debut, $timezone);
        if ($deadline instanceof DateTime) {
            $deadline->modify('-14 days');
            $date_fin_inscription = $deadline->format('Y-m-d H:i:s');
        }
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
    ));
    $form_values['animateur_ids'] = $animateur_ids_list;

    if (empty($errors)) {
        $payload = array(
            'title' => $title,
            'status' => $status,
            'type' => $type,
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
                    $form_values['cover_id'] = (int) $event->cover_id;
                    $form_values['location_id'] = (int) (isset($event->location_id) ? $event->location_id : 0);
                    $form_values['description'] = $event->description;
                    $form_values['age_min'] = (int) $event->age_min;
                    $form_values['age_max'] = (int) $event->age_max;
                    $form_values['date_debut'] = mj_member_format_event_datetime($event->date_debut);
                    $form_values['date_fin'] = mj_member_format_event_datetime($event->date_fin);
                    $form_values['date_fin_inscription'] = mj_member_format_event_datetime($event->date_fin_inscription);
                    $form_values['prix'] = number_format((float) $event->prix, 2, '.', '');
                    $form_values['allow_guardian_registration'] = !empty($event->allow_guardian_registration);
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
                    $form_values['location_id'] = (int) (isset($event->location_id) ? $event->location_id : 0);
                    $form_values['description'] = $event->description;
                    $form_values['prix'] = number_format((float) $event->prix, 2, '.', '');
                    $form_values['allow_guardian_registration'] = !empty($event->allow_guardian_registration);
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
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mj_event_registration_nonce'])) {
        $nonce_value = $_POST['mj_event_registration_nonce'];
        if (!wp_verify_nonce($nonce_value, 'mj_event_registration_action')) {
            $registration_errors[] = 'Verification de securite echouee.';
        } else {
            $registration_action = isset($_POST['registration_action']) ? sanitize_key($_POST['registration_action']) : '';
            $registration_selected_member = isset($_POST['registration_member_id']) ? (int) $_POST['registration_member_id'] : 0;
            $registration_notes_value = isset($_POST['registration_notes']) ? sanitize_textarea_field(wp_unslash($_POST['registration_notes'])) : '';

            switch ($registration_action) {
                case 'add':
                    $member_id = $registration_selected_member;
                    if ($member_id <= 0) {
                        $registration_errors[] = 'Selectionnez un membre pour l inscription.';
                        break;
                    }

                    $notes = $registration_notes_value;
                    $guardian_override = isset($_POST['registration_guardian_id']) ? (int) $_POST['registration_guardian_id'] : 0;

                    $create_args = array('notes' => $notes);
                    if ($guardian_override > 0) {
                        $create_args['guardian_id'] = $guardian_override;
                    }

                    $create_result = MjEventRegistrations::create($event_id, $member_id, $create_args);
                    if (is_wp_error($create_result)) {
                        $registration_errors[] = $create_result->get_error_message();
                    } else {
                        $registration_success = 'Inscription enregistree.';
                        $registration_selected_member = 0;
                        $registration_notes_value = '';
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

$back_url = add_query_arg(array('page' => 'mj_events'), admin_url('admin.php'));
$title_text = ($action === 'add') ? 'Ajouter un evenement' : 'Modifier l evenement';
?>

<div class="wrap">
    <div class="mj-event-form-container">
    <h2><?php echo esc_html($title_text); ?></h2>
    <p><a class="button" href="<?php echo esc_url($back_url); ?>">Retour a la liste</a></p>

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
                        <p class="description">Plusieurs animateurs peuvent etre selectionnes apres la migration des evenements (wp eval "mj_member_upgrade_to_2_3(\$GLOBALS['wpdb']);"). Pour l instant, seul le premier animateur choisi sera memorise; laissez vide pour aucun.</p>
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
                    <p class="description">Par defaut, seuls les jeunes et leurs dependants peuvent s'inscrire. Cochez pour ouvrir les inscriptions aux tuteurs.</p>
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
                <th scope="row"><label for="mj-event-date-start">Date de debut</label></th>
                <td>
                    <input type="datetime-local" id="mj-event-date-start" name="event_date_start" value="<?php echo esc_attr($form_values['date_debut']); ?>" required />
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="mj-event-date-end">Date de fin</label></th>
                <td>
                    <input type="datetime-local" id="mj-event-date-end" name="event_date_end" value="<?php echo esc_attr($form_values['date_fin']); ?>" required />
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
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($member_label); ?></strong><br />
                                    <span class="description"><?php echo esc_html(isset($member_role_labels[$registration_entry->role]) ? $member_role_labels[$registration_entry->role] : $registration_entry->role); ?></span>
                                </td>
                                <td><?php echo esc_html($guardian_label); ?></td>
                                <td><?php echo esc_html($status_label); ?></td>
                                <td><?php echo esc_html($date_display); ?></td>
                                <td><?php echo $registration_entry->notes !== null ? esc_html($registration_entry->notes) : '—'; ?></td>
                                <td>
                                    <div style="display:flex; gap:4px; flex-wrap:wrap;">
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
