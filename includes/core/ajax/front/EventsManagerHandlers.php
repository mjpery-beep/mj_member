<?php

namespace Mj\Member\Core\Ajax\Front;

use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handlers AJAX pour le widget de gestion des événements (front-end)
 */
class EventsManagerHandlers
{
    public static function init(): void
    {
        add_action('wp_ajax_mj_events_manager_list', [self::class, 'handleList']);
        add_action('wp_ajax_mj_events_manager_create', [self::class, 'handleCreate']);
        add_action('wp_ajax_mj_events_manager_update', [self::class, 'handleUpdate']);
        add_action('wp_ajax_mj_events_manager_delete', [self::class, 'handleDelete']);
    }

    /**
     * @param mixed $raw
     * @return array<string,mixed>
     */
    private static function decodeRegistrationPayload($raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }

        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    /**
     * @param mixed $value
     */
    private static function normalizeAttendanceFlag($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
        }

        return !empty($value);
    }

    /**
     * Liste les événements (AJAX)
     */
    public static function handleList(): void
    {
        if (!check_ajax_referer('mj-events-manager', 'nonce', false)) {
            wp_send_json_error(['message' => __('Nonce invalide.', 'mj-member')], 403);
        }

        if (!current_user_can(Config::capability())) {
            wp_send_json_error(['message' => __('Accès non autorisé.', 'mj-member')], 403);
        }

        if (!class_exists('MjEvents')) {
            require_once Config::path() . 'includes/classes/crud/MjEvents.php';
        }

        $show_past = !empty($_POST['show_past']) && $_POST['show_past'] === '1';

        try {
            $events = \MjEvents::getAll([
                'order_by' => 'start_date',
                'order' => 'DESC',
            ]);

            if (!$show_past) {
                $now = current_time('timestamp');
                $events = array_filter($events, function ($event) use ($now) {
                    if (!empty($event->start_date) && $event->start_date !== '0000-00-00 00:00:00') {
                        return strtotime($event->start_date) >= $now;
                    }
                    return true;
                });
            }

            $events = array_values($events);

            wp_send_json_success([
                'events' => $events,
                'total' => count($events),
            ]);
        } catch (\Exception $e) {
            error_log('EventsManager List Error: ' . $e->getMessage());
            wp_send_json_error(['message' => __('Impossible de charger les événements.', 'mj-member')], 500);
        }
    }

    /**
     * Crée un nouvel événement (AJAX)
     */
    public static function handleCreate(): void
    {
        if (!check_ajax_referer('mj-events-manager', 'nonce', false)) {
            wp_send_json_error(['message' => __('Nonce invalide.', 'mj-member')], 403);
        }

        if (!current_user_can(Config::capability())) {
            wp_send_json_error(['message' => __('Accès non autorisé.', 'mj-member')], 403);
        }

        if (!class_exists('MjEvents')) {
            require_once Config::path() . 'includes/classes/crud/MjEvents.php';
        }

        $title = !empty($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
        if (empty($title)) {
            wp_send_json_error(['message' => __('Le titre est requis.', 'mj-member')], 400);
        }

        $type = !empty($_POST['type']) ? sanitize_key($_POST['type']) : '';
        if (empty($type)) {
            wp_send_json_error(['message' => __('Le type est requis.', 'mj-member')], 400);
        }

        $status = !empty($_POST['status']) ? sanitize_key($_POST['status']) : 'draft';
        $description = !empty($_POST['description']) ? wp_kses_post($_POST['description']) : '';
        $start_date = !empty($_POST['start_date']) ? self::parseDateTime($_POST['start_date']) : '';
        $end_date = !empty($_POST['end_date']) ? self::parseDateTime($_POST['end_date']) : '';
        $price = isset($_POST['price']) ? floatval($_POST['price']) : 0;
        $capacity_total = isset($_POST['capacity_total']) ? intval($_POST['capacity_total']) : 0;
        $attendance_show_all_members = isset($_POST['attendance_show_all_members'])
            ? self::normalizeAttendanceFlag($_POST['attendance_show_all_members'])
            : false;

        // Schedule mode and payload
        $schedule_mode = !empty($_POST['schedule_mode']) ? sanitize_key($_POST['schedule_mode']) : 'fixed';
        $schedule_payload = '';
        $decoded = [];
        if (!empty($_POST['schedule_payload'])) {
            $decoded = json_decode(wp_unslash($_POST['schedule_payload']), true);
            if (is_array($decoded)) {
                $schedule_payload = wp_json_encode($decoded);
            }
        }

        // Calculate recurrence_until from payload if recurring
        $recurrence_until = '';
        if ($schedule_mode === 'recurring' && !empty($decoded['until'])) {
            $recurrence_until = sanitize_text_field($decoded['until']);
        }

        $registration_payload = [
            'attendance_show_all_members' => $attendance_show_all_members,
        ];

        try {
            $event_id = \MjEvents::create([
                'title' => $title,
                'type' => $type,
                'status' => $status,
                'description' => $description,
                'start_date' => $start_date,
                'end_date' => $end_date,
                'price' => $price,
                'capacity_total' => $capacity_total,
                'schedule_mode' => $schedule_mode,
                'schedule_payload' => $schedule_payload,
                'recurrence_until' => $recurrence_until,
                'registration_payload' => $registration_payload,
            ]);

            if (is_wp_error($event_id)) {
                throw new \Exception($event_id->get_error_message());
            }

            $event = \MjEvents::getById($event_id);

            wp_send_json_success([
                'message' => __('Événement créé avec succès.', 'mj-member'),
                'event' => $event,
            ]);
        } catch (\Exception $e) {
            error_log('EventsManager Create Error: ' . $e->getMessage());
            wp_send_json_error(['message' => __('Impossible de créer l\'événement.', 'mj-member')], 500);
        }
    }

    /**
     * Met à jour un événement (AJAX)
     */
    public static function handleUpdate(): void
    {
        if (!check_ajax_referer('mj-events-manager', 'nonce', false)) {
            wp_send_json_error(['message' => __('Nonce invalide.', 'mj-member')], 403);
        }

        if (!current_user_can(Config::capability())) {
            wp_send_json_error(['message' => __('Accès non autorisé.', 'mj-member')], 403);
        }

        if (!class_exists('MjEvents')) {
            require_once Config::path() . 'includes/classes/crud/MjEvents.php';
        }

        $event_id = !empty($_POST['event_id']) ? intval($_POST['event_id']) : 0;
        if ($event_id <= 0) {
            wp_send_json_error(['message' => __('ID événement invalide.', 'mj-member')], 400);
        }

        $event = \MjEvents::getById($event_id);
        if (!$event) {
            wp_send_json_error(['message' => __('Événement introuvable.', 'mj-member')], 404);
        }

        $data = [];

        $registration_payload = self::decodeRegistrationPayload(isset($event->registration_payload) ? $event->registration_payload : []);

        if (isset($_POST['title'])) {
            $title = sanitize_text_field($_POST['title']);
            if (empty($title)) {
                wp_send_json_error(['message' => __('Le titre ne peut pas être vide.', 'mj-member')], 400);
            }
            $data['title'] = $title;
        }

        if (isset($_POST['type'])) {
            $data['type'] = sanitize_key($_POST['type']);
        }

        if (isset($_POST['status'])) {
            $data['status'] = sanitize_key($_POST['status']);
        }

        if (isset($_POST['description'])) {
            $data['description'] = wp_kses_post($_POST['description']);
        }

        if (isset($_POST['start_date'])) {
            $data['start_date'] = self::parseDateTime($_POST['start_date']);
        }

        if (isset($_POST['end_date'])) {
            $data['end_date'] = self::parseDateTime($_POST['end_date']);
        }

        if (isset($_POST['price'])) {
            $data['price'] = floatval($_POST['price']);
        }

        if (isset($_POST['capacity_total'])) {
            $data['capacity_total'] = intval($_POST['capacity_total']);
        }

        // Schedule mode and payload
        if (isset($_POST['schedule_mode'])) {
            $data['schedule_mode'] = sanitize_key($_POST['schedule_mode']);
        }

        if (isset($_POST['schedule_payload'])) {
            $decoded = json_decode(wp_unslash($_POST['schedule_payload']), true);
            if (is_array($decoded)) {
                $data['schedule_payload'] = wp_json_encode($decoded);
                
                // Update recurrence_until from payload if recurring
                if (!empty($decoded['until'])) {
                    $data['recurrence_until'] = sanitize_text_field($decoded['until']);
                }
            }
        }

        if (isset($_POST['attendance_show_all_members'])) {
            $attendance_flag = self::normalizeAttendanceFlag($_POST['attendance_show_all_members']);
            $registration_payload['attendance_show_all_members'] = $attendance_flag;
            $data['registration_payload'] = $registration_payload;
        }

        try {
            $result = \MjEvents::update($event_id, $data);

            if (is_wp_error($result)) {
                throw new \Exception($result->get_error_message());
            }

            $updated_event = \MjEvents::getById($event_id);

            wp_send_json_success([
                'message' => __('Événement mis à jour avec succès.', 'mj-member'),
                'event' => $updated_event,
            ]);
        } catch (\Exception $e) {
            error_log('EventsManager Update Error: ' . $e->getMessage());
            wp_send_json_error(['message' => __('Impossible de mettre à jour l\'événement.', 'mj-member')], 500);
        }
    }

    /**
     * Supprime un événement (AJAX)
     */
    public static function handleDelete(): void
    {
        if (!check_ajax_referer('mj-events-manager', 'nonce', false)) {
            wp_send_json_error(['message' => __('Nonce invalide.', 'mj-member')], 403);
        }

        if (!current_user_can(Config::capability())) {
            wp_send_json_error(['message' => __('Accès non autorisé.', 'mj-member')], 403);
        }

        if (!class_exists('MjEvents')) {
            require_once Config::path() . 'includes/classes/crud/MjEvents.php';
        }

        $event_id = !empty($_POST['event_id']) ? intval($_POST['event_id']) : 0;
        if ($event_id <= 0) {
            wp_send_json_error(['message' => __('ID événement invalide.', 'mj-member')], 400);
        }

        $event = \MjEvents::getById($event_id);
        if (!$event) {
            wp_send_json_error(['message' => __('Événement introuvable.', 'mj-member')], 404);
        }

        try {
            $result = \MjEvents::delete($event_id);

            if (is_wp_error($result)) {
                throw new \Exception($result->get_error_message());
            }

            wp_send_json_success([
                'message' => __('Événement supprimé avec succès.', 'mj-member'),
            ]);
        } catch (\Exception $e) {
            error_log('EventsManager Delete Error: ' . $e->getMessage());
            wp_send_json_error(['message' => __('Impossible de supprimer l\'événement.', 'mj-member')], 500);
        }
    }

    /**
     * Parse et formate une date/heure
     *
     * @param string $value Valeur datetime-local (Y-m-d\TH:i)
     * @return string Format MySQL (Y-m-d H:i:s)
     */
    private static function parseDateTime(string $value): string
    {
        $value = trim($value);
        if (empty($value)) {
            return '';
        }

        try {
            $timezone = wp_timezone();
            $datetime = \DateTime::createFromFormat('Y-m-d\TH:i', $value, $timezone);
            
            if ($datetime instanceof \DateTime) {
                return $datetime->format('Y-m-d H:i:s');
            }

            $timestamp = strtotime($value);
            if ($timestamp) {
                $datetime = new \DateTime('@' . $timestamp);
                $datetime->setTimezone($timezone);
                return $datetime->format('Y-m-d H:i:s');
            }

            return '';
        } catch (\Exception $e) {
            error_log('EventsManager Parse DateTime Error: ' . $e->getMessage());
            return '';
        }
    }
}

// Initialiser les hooks AJAX
EventsManagerHandlers::init();
