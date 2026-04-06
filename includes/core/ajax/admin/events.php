<?php

namespace Mj\Member\Core\Ajax\Admin;

use Mj\Member\Classes\Crud\MjEvents;
use Mj\Member\Classes\Crud\MjEventOccurrences;
use Mj\Member\Classes\Table\MjEvents_List_Table;
use Mj\Member\Core\Config;
use Mj\Member\Core\Contracts\AjaxHandlerInterface;

if (!defined('ABSPATH')) {
    exit;
}

final class EventsController implements AjaxHandlerInterface
{
    public function registerHooks(): void
    {
        add_action('wp_ajax_mj_fetch_events_table', [$this, 'fetchEventsTable']);
        add_action('wp_ajax_mj_inline_edit_event', [$this, 'inlineEditEvent']);
        add_action('wp_ajax_mj_calendar_delete_occurrence', [$this, 'calendarDeleteOccurrence']);
    }

    public function fetchEventsTable(): void
    {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mj_events_list')) {
        wp_send_json_error(array('message' => __('Vérification de sécurité échouée.', 'mj-member')), 403);
    }

    $capability = Config::capability();

    if (!current_user_can($capability)) {
        wp_send_json_error(array('message' => __('Accès refusé.', 'mj-member')), 403);
    }

    $allowed_keys = array('paged', 'orderby', 'order', 'filter_status', 'filter_type', 's');
    $request = array();

    foreach ($allowed_keys as $key) {
        if (!isset($_POST[$key])) {
            continue;
        }

        $value = $_POST[$key];

        if (is_array($value)) {
            continue;
        }

        $request[$key] = wp_unslash($value);
    }

    $table = new MjEvents_List_Table();
    $table->setRequestArgs($request);
    $table->prepare_items();

    ob_start();
    $table->display();
    $table_html = ob_get_clean();

    $response = array(
        'table' => $table_html,
        'pagination' => array(
            'total_items' => $table->get_pagination_arg('total_items'),
            'total_pages' => $table->get_pagination_arg('total_pages'),
            'per_page'    => $table->get_pagination_arg('per_page'),
            'current_page' => $table->get_pagenum(),
        ),
        'filters' => array(
            'status' => isset($request['filter_status']) ? sanitize_key((string) $request['filter_status']) : '',
            'type'   => isset($request['filter_type']) ? sanitize_key((string) $request['filter_type']) : '',
            'search' => isset($request['s']) ? sanitize_text_field((string) $request['s']) : '',
        ),
    );

    wp_send_json_success($response);
}

    public function inlineEditEvent(): void
    {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mj_inline_edit_nonce')) {
        wp_send_json_error(array('message' => __('Vérification de sécurité échouée', 'mj-member')));
    }

    $capability = Config::capability();

    if (!current_user_can($capability)) {
        wp_send_json_error(array('message' => __('Accès non autorisé', 'mj-member')));
    }

    if (!isset($_POST['event_id'], $_POST['field_name'])) {
        wp_send_json_error(array('message' => __('Données manquantes', 'mj-member')));
    }

    $event_id = intval($_POST['event_id']);
    $field_name = sanitize_text_field($_POST['field_name']);
    $field_value = isset($_POST['field_value']) ? wp_unslash($_POST['field_value']) : '';

    if ($event_id <= 0) {
        wp_send_json_error(array('message' => __('Identifiant événement invalide', 'mj-member')));
    }

    $allowed_fields = array(
        'title',
        'status',
        'type',
        'date_debut',
        'date_fin',
        'date_fin_inscription',
        'prix',
        'capacity_total',
        'capacity_waitlist',
    );

    if (!in_array($field_name, $allowed_fields, true)) {
        wp_send_json_error(array('message' => __('Champ non autorisé', 'mj-member')));
    }

    $event = MjEvents::getById($event_id);
    if (!$event) {
        wp_send_json_error(array('message' => __('Événement introuvable', 'mj-member')));
    }

    switch ($field_name) {
        case 'title':
            $field_value = sanitize_text_field($field_value);
            if ($field_value === '') {
                wp_send_json_error(array('message' => __('Le titre ne peut pas être vide', 'mj-member')));
            }
            break;
        case 'status':
            $field_value = sanitize_text_field($field_value);
            $allowed_statuses = array(MjEvents::STATUS_ACTIVE, MjEvents::STATUS_DRAFT, MjEvents::STATUS_PAST);
            if (!in_array($field_value, $allowed_statuses, true)) {
                wp_send_json_error(array('message' => __('Statut invalide', 'mj-member')));
            }
            break;
        case 'type':
            $field_value = sanitize_text_field($field_value);
            $allowed_types = array(
                MjEvents::TYPE_STAGE,
                MjEvents::TYPE_SOIREE,
                MjEvents::TYPE_SORTIE,
                MjEvents::TYPE_ATELIER,
                MjEvents::TYPE_INTERNE,
            );
            if (!in_array($field_value, $allowed_types, true)) {
                wp_send_json_error(array('message' => __('Type invalide', 'mj-member')));
            }
            break;
        case 'date_debut':
        case 'date_fin':
        case 'date_fin_inscription':
            $clean_date = sanitize_text_field($field_value);
            if ($clean_date === '') {
                $field_value = null;
                break;
            }

            $timestamp = strtotime($clean_date);
            if (!$timestamp) {
                wp_send_json_error(array('message' => __('Date invalide', 'mj-member')));
            }

            $field_value = gmdate('Y-m-d H:i:s', $timestamp);
            break;
        case 'prix':
            $field_value = floatval($field_value);
            if ($field_value < 0) {
                wp_send_json_error(array('message' => __('Le prix ne peut pas être négatif', 'mj-member')));
            }
            break;
        case 'capacity_total':
        case 'capacity_waitlist':
            $field_value = intval($field_value);
            if ($field_value < 0) {
                wp_send_json_error(array('message' => __('La capacité ne peut pas être négative', 'mj-member')));
            }
            break;
        default:
            $field_value = sanitize_text_field($field_value);
    }

    $data = array($field_name => $field_value);
    $result = MjEvents::update($event_id, $data);

    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()));
    }

    wp_send_json_success(array(
        'message' => __('Mise à jour réussie', 'mj-member'),
        'value' => $field_value
    ));
}

    /**
     * Delete (or soft-delete) a single event occurrence from the calendar.
     *
     * Expects:
     *  - nonce  : mj_calendar_delete_occurrence
     *  - event_id : int
     *  - start_ts : int (Unix timestamp of the occurrence start)
     */
    public function calendarDeleteOccurrence(): void
    {
    if (!check_ajax_referer('mj_calendar_delete_occurrence', 'nonce', false)) {
        wp_send_json_error(array('message' => __('Nonce invalide.', 'mj-member')));
        return;
    }

    // Only animateurs / coordinateurs / admins can delete occurrences
    if (!current_user_can(Config::capability())) {
        // Check if user is animateur or coordinateur via member role
        $member = null;
        if (class_exists(\Mj\Member\Classes\Crud\MjMembers::class)) {
            $member = \Mj\Member\Classes\Crud\MjMembers::getByWpUserId(get_current_user_id());
        }
        $role = is_array($member) && isset($member['role']) ? (string) $member['role'] : '';
        if (!in_array($role, array(
            \Mj\Member\Classes\Crud\MjRoles::COORDINATEUR,
            \Mj\Member\Classes\Crud\MjRoles::ANIMATEUR,
        ), true)) {
            wp_send_json_error(array('message' => __('Permissions insuffisantes.', 'mj-member')));
            return;
        }
    }

    $event_id = isset($_POST['event_id']) ? absint($_POST['event_id']) : 0;
    $start_ts = isset($_POST['start_ts']) ? absint($_POST['start_ts']) : 0;

    if ($event_id <= 0 || $start_ts <= 0) {
        wp_send_json_error(array('message' => __('Paramètres manquants.', 'mj-member')));
        return;
    }

    // Verify event exists
    $event = MjEvents::find($event_id);
    if (!$event) {
        wp_send_json_error(array('message' => __('Événement introuvable.', 'mj-member')));
        return;
    }

    // Try to find and delete the occurrence row by matching event_id + start_at
    $timezone = function_exists('wp_timezone') ? wp_timezone() : new \DateTimeZone('UTC');
    $start_dt = (new \DateTimeImmutable('@' . $start_ts))->setTimezone($timezone);
    $start_at = $start_dt->format('Y-m-d H:i:s');

    global $wpdb;
    $table = $wpdb->prefix . 'mj_event_date_occurrences';

    // Check if the table exists
    $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));

    if ($table_exists) {
        // Try exact match first
        $deleted = $wpdb->delete($table, array(
            'event_id' => $event_id,
            'start_at' => $start_at,
        ), array('%d', '%s'));

        // If no exact match, try within a 60-second window (timezone rounding)
        if ($deleted === 0) {
            $start_min = (new \DateTimeImmutable('@' . ($start_ts - 60)))->setTimezone($timezone)->format('Y-m-d H:i:s');
            $start_max = (new \DateTimeImmutable('@' . ($start_ts + 60)))->setTimezone($timezone)->format('Y-m-d H:i:s');
            $deleted = $wpdb->query($wpdb->prepare(
                "DELETE FROM {$table} WHERE event_id = %d AND start_at BETWEEN %s AND %s LIMIT 1",
                $event_id,
                $start_min,
                $start_max
            ));
        }

        if ($deleted > 0) {
            wp_send_json_success(array(
                'message' => __('Occurrence supprimée.', 'mj-member'),
            ));
            return;
        }
    }

    // If no occurrence row was found (single-date event or table missing),
    // report that the occurrence could not be found
    wp_send_json_error(array(
        'message' => __('Occurrence introuvable dans la base de données. S\'il s\'agit d\'un événement non-récurrent, supprimez-le via le gestionnaire.', 'mj-member'),
    ));
    }
}
