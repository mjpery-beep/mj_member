<?php

namespace Mj\Member\Classes\Crud;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class MjEventPhotos implements CrudRepositoryInterface {
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    /**
     * @return string
     */
    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'mj_event_photos';
    }

    /**
     * @return array<string,string>
     */
    public static function get_status_labels() {
        return array(
            self::STATUS_PENDING => __('En attente', 'mj-member'),
            self::STATUS_APPROVED => __('Validée', 'mj-member'),
            self::STATUS_REJECTED => __('Refusée', 'mj-member'),
        );
    }

    /**
     * @param array<string,mixed> $args
     * @return array<int,object>
     */
    public static function get_all(array $args = array()) {
        return self::query($args);
    }

    /**
     * @param array<string,mixed> $data
     * @return int|WP_Error
     */
    public static function create($data) {
        if (!is_array($data)) {
            return new WP_Error('mj_event_photo_invalid_payload', __('Format de données invalide.', 'mj-member'));
        }
        global $wpdb;
        $table = self::get_table_name();

        $defaults = array(
            'event_id' => 0,
            'registration_id' => null,
            'member_id' => 0,
            'attachment_id' => 0,
            'caption' => '',
            'status' => self::STATUS_PENDING,
            'rejection_reason' => null,
            'created_at' => current_time('mysql'),
            'reviewed_at' => null,
            'reviewed_by' => null,
        );
        $payload = wp_parse_args($data, $defaults);

        $event_id = isset($payload['event_id']) ? (int) $payload['event_id'] : 0;
        $member_id = isset($payload['member_id']) ? (int) $payload['member_id'] : 0;
        $attachment_id = isset($payload['attachment_id']) ? (int) $payload['attachment_id'] : 0;

        if ($event_id <= 0 || $member_id <= 0 || $attachment_id <= 0) {
            return new WP_Error('mj_event_photo_invalid_data', __('Données photo invalides.', 'mj-member'));
        }

        $registration_id = isset($payload['registration_id']) ? (int) $payload['registration_id'] : 0;
        $caption = isset($payload['caption']) ? wp_strip_all_tags($payload['caption']) : '';
        $status = self::sanitize_status(isset($payload['status']) ? (string) $payload['status'] : self::STATUS_PENDING);
        $rejection_reason = isset($payload['rejection_reason']) ? sanitize_text_field($payload['rejection_reason']) : null;
        $created_at = isset($payload['created_at']) ? sanitize_text_field($payload['created_at']) : current_time('mysql');
        $reviewed_at = isset($payload['reviewed_at']) ? sanitize_text_field($payload['reviewed_at']) : null;
        $reviewed_by = isset($payload['reviewed_by']) ? (int) $payload['reviewed_by'] : null;

        $insert_data = array(
            'event_id' => $event_id,
            'registration_id' => $registration_id > 0 ? $registration_id : null,
            'member_id' => $member_id,
            'attachment_id' => $attachment_id,
            'caption' => $caption !== '' ? $caption : null,
            'status' => $status,
            'rejection_reason' => $rejection_reason,
            'created_at' => $created_at,
            'reviewed_at' => $reviewed_at,
            'reviewed_by' => $reviewed_by > 0 ? $reviewed_by : null,
        );

        $formats = array('%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%d');

        $result = $wpdb->insert($table, $insert_data, $formats);
        if ($result === false) {
            return new WP_Error('mj_event_photo_insert_failed', __('Impossible d’enregistrer la photo.', 'mj-member'));
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * @param int $photo_id
     * @param array<string,mixed> $data
    * @return true|WP_Error
     */
    public static function update($photo_id, $data) {
        if (!is_array($data)) {
            return new WP_Error('mj_event_photo_invalid_payload', __('Format de données invalide.', 'mj-member'));
        }
        global $wpdb;
        $table = self::get_table_name();
        $photo_id = (int) $photo_id;
        if ($photo_id <= 0) {
            return new WP_Error('mj_event_photo_invalid_id', __('Identifiant photo invalide.', 'mj-member'));
        }

        $fields = array();
        $formats = array();

        if (array_key_exists('status', $data)) {
            $fields['status'] = self::sanitize_status((string) $data['status']);
            $formats[] = '%s';
        }

        if (array_key_exists('rejection_reason', $data)) {
            $reason = $data['rejection_reason'];
            $fields['rejection_reason'] = ($reason !== null && $reason !== '') ? sanitize_text_field($reason) : null;
            $formats[] = '%s';
        }

        if (array_key_exists('caption', $data)) {
            $caption = $data['caption'];
            $fields['caption'] = ($caption !== null && $caption !== '') ? sanitize_text_field($caption) : null;
            $formats[] = '%s';
        }

        if (array_key_exists('reviewed_at', $data)) {
            $fields['reviewed_at'] = $data['reviewed_at'] ? sanitize_text_field($data['reviewed_at']) : null;
            $formats[] = '%s';
        }

        if (array_key_exists('reviewed_by', $data)) {
            $reviewed_by = (int) $data['reviewed_by'];
            $fields['reviewed_by'] = $reviewed_by > 0 ? $reviewed_by : null;
            $formats[] = '%d';
        }

        if (empty($fields)) {
            return true;
        }

        $result = $wpdb->update($table, $fields, array('id' => $photo_id), $formats, array('%d'));
        if ($result === false) {
            return new WP_Error('mj_event_photo_update_failed', __('Mise à jour impossible.', 'mj-member'));
        }

        return true;
    }

    /**
     * @param int $photo_id
     * @param string $status
     * @param array<string,mixed> $args
    * @return true|WP_Error
     */
    public static function update_status($photo_id, $status, array $args = array()) {
        $reviewed_at = isset($args['reviewed_at']) ? sanitize_text_field($args['reviewed_at']) : current_time('mysql');
        $reviewed_by = isset($args['reviewed_by']) ? (int) $args['reviewed_by'] : get_current_user_id();
        $rejection_reason = isset($args['rejection_reason']) ? sanitize_text_field($args['rejection_reason']) : null;

        // Récupérer la photo avant mise à jour pour avoir l'ancien statut
        $photo = self::get($photo_id);
        $old_status = $photo ? ($photo->status ?? '') : '';

        $result = self::update($photo_id, array(
            'status' => $status,
            'reviewed_at' => $reviewed_at,
            'reviewed_by' => $reviewed_by > 0 ? $reviewed_by : null,
            'rejection_reason' => ($status === self::STATUS_REJECTED) ? $rejection_reason : null,
        ));

        // Déclencher l'action si la mise à jour a réussi
        if ($result === true && $photo && $old_status !== $status) {
            /**
             * Déclenché après changement de statut d'une photo.
             *
             * @param int    $photo_id    ID de la photo
             * @param string $new_status  Nouveau statut (approved, rejected, pending)
             * @param string $old_status  Ancien statut
             * @param object $photo       Objet photo (avant mise à jour)
             */
            do_action('mj_member_event_photo_status_changed', (int) $photo_id, $status, $old_status, $photo);
        }

        return $result;
    }

    /**
     * @param int $photo_id
     * @return object|null
     */
    public static function get($photo_id) {
        global $wpdb;
        $table = self::get_table_name();
        $photo_id = (int) $photo_id;
        if ($photo_id <= 0) {
            return null;
        }

        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $photo_id));
    }

    /**
     * @param int $event_id
     * @param array<string,mixed> $args
     * @return array<int,object>
     */
    public static function get_for_event($event_id, array $args = array()) {
        $defaults = array(
            'status' => self::STATUS_APPROVED,
            'limit' => 0,
            'order' => 'DESC',
        );
        $query_args = wp_parse_args($args, $defaults);

        $status = isset($query_args['status']) && $query_args['status'] !== ''
            ? self::sanitize_status($query_args['status'])
            : '';
        $limit = isset($query_args['limit']) ? (int) $query_args['limit'] : 0;
        $order = strtoupper(isset($query_args['order']) ? (string) $query_args['order'] : 'DESC');
        $order = in_array($order, array('ASC', 'DESC'), true) ? $order : 'DESC';

        global $wpdb;
        $table = self::get_table_name();
        $event_id = (int) $event_id;
        if ($event_id <= 0) {
            return array();
        }

        $where = $wpdb->prepare('WHERE event_id = %d', $event_id);
        if ($status !== '') {
            $where .= $wpdb->prepare(' AND status = %s', $status);
        }

        $sql = "SELECT * FROM {$table} {$where} ORDER BY created_at {$order}";
        if ($limit > 0) {
            $sql .= $wpdb->prepare(' LIMIT %d', $limit);
        }

        $results = $wpdb->get_results($sql);
        return is_array($results) ? $results : array();
    }

    /**
    * @param array<string,mixed> $args
    * @return array<int,object>
    */
    public static function query(array $args = array()) {
        global $wpdb;
        $table = self::get_table_name();

        $defaults = array(
            'status' => '',
            'event_id' => 0,
            'member_id' => 0,
            'per_page' => 50,
            'paged' => 1,
            'order' => 'DESC',
            'orderby' => 'created_at',
        );
        $query_args = wp_parse_args($args, $defaults);

        // Support array or single status
        $statuses = array();
        if (is_array($query_args['status'])) {
            foreach ($query_args['status'] as $s) {
                $sanitized = self::sanitize_status((string) $s);
                if ($sanitized !== '') {
                    $statuses[] = $sanitized;
                }
            }
        } elseif ($query_args['status'] !== '') {
            $sanitized = self::sanitize_status($query_args['status']);
            if ($sanitized !== '') {
                $statuses[] = $sanitized;
            }
        }

        $event_id = (int) $query_args['event_id'];
        $member_id = (int) $query_args['member_id'];
        $per_page = max(1, (int) $query_args['per_page']);
        $paged = max(1, (int) $query_args['paged']);
        $order = strtoupper((string) $query_args['order']);
        $order = in_array($order, array('ASC', 'DESC'), true) ? $order : 'DESC';
        $orderby = in_array($query_args['orderby'], array('created_at', 'status'), true) ? $query_args['orderby'] : 'created_at';

        $where_parts = array('1=1');
        $params = array();

        if (!empty($statuses)) {
            $placeholders = implode(', ', array_fill(0, count($statuses), '%s'));
            $where_parts[] = "status IN ({$placeholders})";
            $params = array_merge($params, $statuses);
        }
        if ($event_id > 0) {
            $where_parts[] = 'event_id = %d';
            $params[] = $event_id;
        }
        if ($member_id > 0) {
            $where_parts[] = 'member_id = %d';
            $params[] = $member_id;
        }

        $where_sql = implode(' AND ', $where_parts);

        $offset = ($paged - 1) * $per_page;

        $sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = $offset;

        $prepared = $wpdb->prepare($sql, $params);
        $results = $wpdb->get_results($prepared);

        return is_array($results) ? $results : array();
    }

    /**
     * @param array<int,int|string> $event_ids
     * @param int $limit
     * @return array<int,object>
     */
    public static function get_pending_for_events(array $event_ids, $limit = 20) {
        global $wpdb;
        $table = self::get_table_name();

        $event_ids = array_values(array_filter(array_map('intval', $event_ids), static function ($value) {
            return $value > 0;
        }));

        if (empty($event_ids)) {
            return array();
        }

        $limit = max(1, (int) $limit);

        $placeholders = implode(',', array_fill(0, count($event_ids), '%d'));
        $params = $event_ids;
        $params[] = self::STATUS_PENDING;
        $params[] = $limit;

        $sql = "SELECT * FROM {$table} WHERE event_id IN ({$placeholders}) AND status = %s ORDER BY created_at ASC LIMIT %d";
        array_unshift($params, $sql);

        $prepared = call_user_func_array(array($wpdb, 'prepare'), $params);
        $results = $wpdb->get_results($prepared);

        return is_array($results) ? $results : array();
    }

    /**
     * @param int $event_id
     * @param int $member_id
     * @param string|null $status
     * @return int
     */
    public static function count_for_member($event_id, $member_id, $status = null) {
        global $wpdb;
        $table = self::get_table_name();
        $event_id = (int) $event_id;
        $member_id = (int) $member_id;
        if ($event_id <= 0 || $member_id <= 0) {
            return 0;
        }

        $where = $wpdb->prepare('event_id = %d AND member_id = %d', $event_id, $member_id);
        if ($status !== null && $status !== '') {
            $status = self::sanitize_status($status);
            $where .= $wpdb->prepare(' AND status = %s', $status);
        }

        $sql = "SELECT COUNT(*) FROM {$table} WHERE {$where}";
        $count = $wpdb->get_var($sql);

        return $count ? (int) $count : 0;
    }

    /**
     * @param array<string,mixed> $args
     * @return int
     */
    public static function count(array $args = array()) {
        global $wpdb;
        $table = self::get_table_name();

        $defaults = array(
            'status' => '',
            'event_id' => 0,
            'member_id' => 0,
        );
        $query_args = wp_parse_args($args, $defaults);

        $status = $query_args['status'] !== '' ? self::sanitize_status($query_args['status']) : '';
        $event_id = (int) $query_args['event_id'];
        $member_id = (int) $query_args['member_id'];

        $where_parts = array('1=1');
        $params = array();

        if ($status !== '') {
            $where_parts[] = 'status = %s';
            $params[] = $status;
        }

        if ($event_id > 0) {
            $where_parts[] = 'event_id = %d';
            $params[] = $event_id;
        }

        if ($member_id > 0) {
            $where_parts[] = 'member_id = %d';
            $params[] = $member_id;
        }

        $where_sql = implode(' AND ', $where_parts);

        $sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        $count = $wpdb->get_var($sql);
        return $count ? (int) $count : 0;
    }

    /**
     * @param int $photo_id
     * @return true|WP_Error
     */
    public static function delete($photo_id) {
        global $wpdb;
        $table = self::get_table_name();
        $photo_id = (int) $photo_id;
        if ($photo_id <= 0) {
            return new WP_Error('mj_event_photo_invalid_id', __('Identifiant photo invalide.', 'mj-member'));
        }

        $result = $wpdb->delete($table, array('id' => $photo_id), array('%d'));
        if ($result === false) {
            return new WP_Error('mj_event_photo_delete_failed', __('Suppression impossible.', 'mj-member'));
        }

        return true;
    }

    /**
     * @param string $status
     * @return string
     */
    public static function sanitize_status($status) {
        $status = sanitize_key($status);
        if (!in_array($status, array(self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_REJECTED), true)) {
            return self::STATUS_PENDING;
        }
        return $status;
    }
}

class_alias(__NAMESPACE__ . '\\MjEventPhotos', 'MjEventPhotos');
