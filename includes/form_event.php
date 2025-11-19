<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can(MJ_MEMBER_CAPABILITY)) {
    wp_die('Acces refuse');
}

require_once plugin_dir_path(__FILE__) . 'classes/MjEvents_CRUD.php';
require_once plugin_dir_path(__FILE__) . 'classes/MjMembers_CRUD.php';
require_once plugin_dir_path(__FILE__) . 'classes/MjEventRegistrations.php';

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

$defaults = MjEvents_CRUD::get_default_values();
$form_values = $defaults;

$timezone = wp_timezone();
$now_timestamp = current_time('timestamp');
$default_start = $now_timestamp + 21 * DAY_IN_SECONDS;
$default_end = $default_start + 2 * HOUR_IN_SECONDS;
$default_deadline = $default_start - 14 * DAY_IN_SECONDS;

$form_values['date_debut'] = wp_date('Y-m-d\TH:i', $default_start, $timezone);
$form_values['date_fin'] = wp_date('Y-m-d\TH:i', $default_end, $timezone);
$form_values['date_fin_inscription'] = wp_date('Y-m-d\TH:i', $default_deadline, $timezone);

if ($event) {
    $form_values = array_merge($form_values, array(
        'title' => $event->title,
        'status' => $event->status,
        'type' => $event->type,
        'cover_id' => (int) $event->cover_id,
        'description' => $event->description,
        'age_min' => (int) $event->age_min,
        'age_max' => (int) $event->age_max,
        'date_debut' => mj_member_format_event_datetime($event->date_debut),
        'date_fin' => mj_member_format_event_datetime($event->date_fin),
        'date_fin_inscription' => mj_member_format_event_datetime($event->date_fin_inscription),
        'prix' => number_format((float) $event->prix, 2, '.', ''),
    ));
}

$errors = array();
$success_message = '';
$registration_errors = array();
$registration_success = '';
$registrations = array();
$members_for_select = array();
$registration_selected_member = 0;
$registration_notes_value = '';

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

    $description = isset($_POST['event_description']) ? wp_kses_post($_POST['event_description']) : '';

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
    ));

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
        );

        if ($action === 'add') {
            $new_id = MjEvents_CRUD::create($payload);
            if ($new_id) {
                $success_message = 'Evenement cree avec succes.';
                $action = 'edit';
                $event_id = $new_id;
                $event = MjEvents_CRUD::find($event_id);
                if ($event) {
                    $form_values['title'] = $event->title;
                    $form_values['status'] = $event->status;
                    $form_values['type'] = $event->type;
                    $form_values['cover_id'] = (int) $event->cover_id;
                    $form_values['description'] = $event->description;
                    $form_values['age_min'] = (int) $event->age_min;
                    $form_values['age_max'] = (int) $event->age_max;
                    $form_values['date_debut'] = mj_member_format_event_datetime($event->date_debut);
                    $form_values['date_fin'] = mj_member_format_event_datetime($event->date_fin);
                    $form_values['date_fin_inscription'] = mj_member_format_event_datetime($event->date_fin_inscription);
                    $form_values['prix'] = number_format((float) $event->prix, 2, '.', '');
                }
            } else {
                $errors[] = 'Erreur lors de la creation de l evenement.';
            }
        } else {
            $update_result = MjEvents_CRUD::update($event_id, $payload);
            if ($update_result) {
                $success_message = 'Evenement mis a jour avec succes.';
                $event = MjEvents_CRUD::find($event_id);
                if ($event) {
                    $form_values['date_debut'] = mj_member_format_event_datetime($event->date_debut);
                    $form_values['date_fin'] = mj_member_format_event_datetime($event->date_fin);
                    $form_values['date_fin_inscription'] = mj_member_format_event_datetime($event->date_fin_inscription);
                    $form_values['cover_id'] = (int) $event->cover_id;
                    $form_values['description'] = $event->description;
                    $form_values['prix'] = number_format((float) $event->prix, 2, '.', '');
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
