<?php

namespace Mj\Member\Classes\Crud;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class MjContactMessages implements CrudRepositoryInterface {
    const STATUS_NEW = 'nouveau';
    const STATUS_IN_PROGRESS = 'en_cours';
    const STATUS_RESOLVED = 'resolu';
    const STATUS_ARCHIVED = 'archive';

    const TARGET_ANIMATEUR = 'animateur';
    const TARGET_COORDINATEUR = 'coordinateur';
    const TARGET_MEMBER = 'member';
    const TARGET_ALL = 'all';

    /**
     * @return string
     */
    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'mj_contact_messages';
    }

    /**
     * @return string
     */
    public static function get_recipients_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'mj_contact_message_recipients';
    }

    /**
     * @return bool
     */
    private static function recipients_table_exists() {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        global $wpdb;
        $table = self::get_recipients_table_name();
        $like = $wpdb->esc_like($table);
        $existing = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $like));
        $cached = ($existing === $table);

        return $cached;
    }

    /**
     * @return array<string,string>
     */
    public static function get_status_labels() {
        return array(
            self::STATUS_NEW => __('Nouveau', 'mj-member'),
            self::STATUS_IN_PROGRESS => __('En cours', 'mj-member'),
            self::STATUS_RESOLVED => __('Résolu', 'mj-member'),
            self::STATUS_ARCHIVED => __('Archivé', 'mj-member'),
        );
    }

    /**
     * @return array<string,string>
     */
    public static function get_target_labels() {
        return array(
            self::TARGET_ANIMATEUR => __('Animateur', 'mj-member'),
            self::TARGET_COORDINATEUR => __('Coordinateur', 'mj-member'),
            self::TARGET_MEMBER => __('Jeune', 'mj-member'),
            self::TARGET_ALL => __('Tous', 'mj-member'),
        );
    }

    /**
     * @return array<string,mixed>
     */
    public static function get_default_values() {
        return array(
            'sender_name' => '',
            'sender_email' => '',
            'subject' => '',
            'message' => '',
            'target_type' => self::TARGET_ALL,
            'target_reference' => null,
            'target_label' => '',
            'status' => self::STATUS_NEW,
            'is_read' => 0,
            'assigned_to' => null,
            'source_url' => '',
            'activity_log' => array(),
            'meta' => array(),
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
     * Construit la portée SQL des messages visibles pour un utilisateur donné.
     *
     * @param int   $user_id Identifiant utilisateur.
     * @param array $args    Options de portée (attribution, cibles, identité).
     * @param array $params  Paramètres accumulés pour $wpdb->prepare.
     *
     * @return string Clause WHERE (sans le mot-clé) ou chaîne vide si aucune portée.
     */
    private static function build_user_visibility_clause($user_id, array $args, array &$params) {
        global $wpdb;

        $defaults = array(
            'include_assigned' => true,
            'include_all_targets' => false,
            'extra_targets' => array(),
            'member_id' => 0,
            'sender_email' => '',
            'include_owner' => false,
        );

        $args = wp_parse_args($args, $defaults);

        $conditions = array();
        $params = array();
        $recipients_table = self::get_recipients_table_name();
        $recipients_table_exists = self::recipients_table_exists();

        $user_id = (int) $user_id;
        $member_id = (int) $args['member_id'];
        $sender_email = sanitize_email($args['sender_email']);

        if (!empty($args['include_owner'])) {
            $owner_clauses = array();
            $owner_params = array();

            if ($member_id > 0) {
                $owner_clauses[] = '(meta IS NOT NULL AND meta LIKE %s)';
                $owner_params[] = '%' . $wpdb->esc_like('"member_id":"' . $member_id . '"') . '%';
            }

            if ($sender_email !== '') {
                $owner_clauses[] = 'sender_email = %s';
                $owner_params[] = $sender_email;
            }

            if (!empty($owner_clauses)) {
                $conditions[] = '(' . implode(' OR ', $owner_clauses) . ')';
                $params = array_merge($params, $owner_params);
            }
        }

        $assignment_clauses = array();
        $assignment_params = array();

        if (!empty($args['include_assigned']) && $user_id > 0) {
            $assignment_clauses[] = 'assigned_to = %d';
            $assignment_params[] = $user_id;
        }

        if (!empty($args['include_all_targets'])) {
            $meta_all_pattern = sprintf('%%"recipient_keys":"%%%s%%"', $wpdb->esc_like(self::TARGET_ALL));
            if ($recipients_table_exists) {
                $assignment_clauses[] = '(target_type = %s OR EXISTS (SELECT 1 FROM ' . $recipients_table . ' r WHERE r.message_id = id AND r.recipient_type = %s) OR (meta IS NOT NULL AND meta LIKE %s))';
                $assignment_params[] = self::TARGET_ALL;
                $assignment_params[] = self::TARGET_ALL;
                $assignment_params[] = $meta_all_pattern;
            } else {
                $assignment_clauses[] = '(target_type = %s OR (meta IS NOT NULL AND meta LIKE %s))';
                $assignment_params[] = self::TARGET_ALL;
                $assignment_params[] = $meta_all_pattern;
            }
        }

        $extra_target_map = array();
        if (!empty($args['extra_targets']) && is_array($args['extra_targets'])) {
            foreach ($args['extra_targets'] as $spec) {
                if (!is_array($spec)) {
                    continue;
                }

                $type = isset($spec['type']) ? sanitize_key($spec['type']) : '';
                if ($type === '') {
                    continue;
                }

                $reference = null;
                if (array_key_exists('reference', $spec) && $spec['reference'] !== null) {
                    $reference = (int) $spec['reference'];
                    if ($reference < 0) {
                        $reference = 0;
                    }
                }

                if (!empty($args['include_all_targets']) && $type === self::TARGET_ALL && ($reference === null || $reference === 0)) {
                    continue;
                }

                $key = $type . '|' . ($reference === null ? 'null' : (string) $reference);
                if (isset($extra_target_map[$key])) {
                    continue;
                }

                $extra_target_map[$key] = array(
                    'type' => $type,
                    'reference' => $reference,
                );
            }
        }

        if (!empty($extra_target_map)) {
            foreach ($extra_target_map as $entry) {
                $target_type = $entry['type'];
                $target_reference = $entry['reference'];

                if ($target_reference !== null) {
                    $meta_pattern = sprintf('%%"recipient_keys":"%%%s%%"', $wpdb->esc_like($target_type . ':' . $target_reference));
                    if ($recipients_table_exists) {
                        $assignment_clauses[] = '((target_type = %s AND target_reference = %d) OR EXISTS (SELECT 1 FROM ' . $recipients_table . ' r WHERE r.message_id = id AND r.recipient_type = %s AND r.recipient_reference = %d) OR (meta IS NOT NULL AND meta LIKE %s))';
                        $assignment_params[] = $target_type;
                        $assignment_params[] = $target_reference;
                        $assignment_params[] = $target_type;
                        $assignment_params[] = $target_reference;
                        $assignment_params[] = $meta_pattern;
                    } else {
                        $assignment_clauses[] = '((target_type = %s AND target_reference = %d) OR (meta IS NOT NULL AND meta LIKE %s))';
                        $assignment_params[] = $target_type;
                        $assignment_params[] = $target_reference;
                        $assignment_params[] = $meta_pattern;
                    }
                } else {
                    $meta_pattern = sprintf('%%"recipient_keys":"%%%s%%"', $wpdb->esc_like($target_type));
                    if ($recipients_table_exists) {
                        $assignment_clauses[] = '(target_type = %s OR EXISTS (SELECT 1 FROM ' . $recipients_table . ' r WHERE r.message_id = id AND r.recipient_type = %s) OR (meta IS NOT NULL AND meta LIKE %s))';
                        $assignment_params[] = $target_type;
                        $assignment_params[] = $target_type;
                        $assignment_params[] = $meta_pattern;
                    } else {
                        $assignment_clauses[] = '(target_type = %s OR (meta IS NOT NULL AND meta LIKE %s))';
                        $assignment_params[] = $target_type;
                        $assignment_params[] = $meta_pattern;
                    }
                }
            }
        }

        if (!empty($assignment_clauses)) {
            $conditions[] = '(' . implode(' OR ', $assignment_clauses) . ')';
            $params = array_merge($params, $assignment_params);
        }

        if (empty($conditions)) {
            return '';
        }

        return '(' . implode(' OR ', $conditions) . ')';
    }

    /**
     * @param array<string,mixed> $data
     * @return int|WP_Error
     */
    public static function create($data) {
        if (!is_array($data)) {
            return new WP_Error('mj_contact_message_invalid_payload', __('Format de données invalide.', 'mj-member'));
        }
        global $wpdb;
        $table = self::get_table_name();

        $defaults = self::get_default_values();
        $payload = wp_parse_args($data, $defaults);

        $sender_name = sanitize_text_field($payload['sender_name']);
        $sender_email = sanitize_email($payload['sender_email']);
        $subject = sanitize_text_field($payload['subject']);
        $message = isset($payload['message']) ? wp_kses_post($payload['message']) : '';

        if ($sender_name === '' || $sender_email === '' || !is_email($sender_email) || $message === '') {
            return new WP_Error('mj_contact_message_invalid', __('Les informations du message sont incomplètes.', 'mj-member'));
        }

        $target = self::normalize_target(
            isset($payload['target_type']) ? (string) $payload['target_type'] : self::TARGET_ALL,
            isset($payload['target_reference']) ? $payload['target_reference'] : null,
            isset($payload['target_label']) ? $payload['target_label'] : ''
        );

        $status = self::sanitize_status(isset($payload['status']) ? (string) $payload['status'] : self::STATUS_NEW);
        $is_read = isset($payload['is_read']) ? (int) $payload['is_read'] : 0;
        $is_read = $is_read > 0 ? 1 : 0;
        $assigned_to = isset($payload['assigned_to']) ? (int) $payload['assigned_to'] : 0;
        $assigned_to = $assigned_to > 0 ? $assigned_to : 0;

        $source_url = isset($payload['source_url']) ? esc_url_raw($payload['source_url']) : '';
        $meta = isset($payload['meta']) ? self::sanitize_meta($payload['meta']) : array();

        $activity_entries = array();
        if (!empty($payload['activity_log']) && is_array($payload['activity_log'])) {
            $activity_entries = array_values(array_filter($payload['activity_log'], array(__CLASS__, 'is_valid_activity_entry')));
        }

        $created_at = isset($payload['created_at']) ? sanitize_text_field($payload['created_at']) : current_time('mysql');

        $insert_data = array(
            'sender_name' => $sender_name,
            'sender_email' => $sender_email,
            'subject' => $subject,
            'message' => $message,
            'target_type' => $target['type'],
            'target_reference' => $target['reference'],
            'target_label' => $target['label'],
            'status' => $status,
            'is_read' => $is_read,
            'assigned_to' => $assigned_to,
            'source_url' => $source_url,
            'activity_log' => !empty($activity_entries) ? wp_json_encode($activity_entries) : null,
            'meta' => !empty($meta) ? wp_json_encode($meta) : null,
            'created_at' => $created_at,
        );

        $formats = array('%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s');
        $result = $wpdb->insert($table, $insert_data, $formats);

        if ($result === false) {
            return new WP_Error('mj_contact_message_insert_failed', __('Impossible d’enregistrer le message.', 'mj-member'));
        }

        $message_id = (int) $wpdb->insert_id;
        self::record_activity($message_id, 'created', array(
            'note' => __('Message soumis via le formulaire public.', 'mj-member'),
            'user_id' => isset($payload['user_id']) ? (int) $payload['user_id'] : get_current_user_id(),
        ));

        return $message_id;
    }

    /**
     * @param int $message_id
     * @return object|null
     */
    public static function get($message_id) {
        global $wpdb;
        $table = self::get_table_name();
        $message_id = (int) $message_id;
        if ($message_id <= 0) {
            return null;
        }

        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $message_id));
        return $row ?: null;
    }

    /**
     * @param int $message_id
    * @param array<string,mixed> $data
    * @return true|WP_Error
     */
    public static function update($message_id, $data) {
        if (!is_array($data)) {
            return new WP_Error('mj_contact_message_invalid_payload', __('Format de données invalide.', 'mj-member'));
        }
        global $wpdb;
        $table = self::get_table_name();
        $message_id = (int) $message_id;
        if ($message_id <= 0) {
            return new WP_Error('mj_contact_message_invalid_id', __('Identifiant message invalide.', 'mj-member'));
        }

        $fields = array();
        $formats = array();

        if (array_key_exists('sender_name', $data)) {
            $fields['sender_name'] = sanitize_text_field($data['sender_name']);
            $formats[] = '%s';
        }

        if (array_key_exists('sender_email', $data)) {
            $email = sanitize_email($data['sender_email']);
            if ($email === '' || !is_email($email)) {
                return new WP_Error('mj_contact_message_invalid_email', __('Email invalide.', 'mj-member'));
            }
            $fields['sender_email'] = $email;
            $formats[] = '%s';
        }

        if (array_key_exists('subject', $data)) {
            $fields['subject'] = sanitize_text_field($data['subject']);
            $formats[] = '%s';
        }

        if (array_key_exists('message', $data)) {
            $fields['message'] = wp_kses_post($data['message']);
            $formats[] = '%s';
        }

        if (array_key_exists('status', $data)) {
            $fields['status'] = self::sanitize_status((string) $data['status']);
            $formats[] = '%s';
        }

        if (array_key_exists('is_read', $data)) {
            $fields['is_read'] = (int) $data['is_read'] > 0 ? 1 : 0;
            $formats[] = '%d';
        }

        if (array_key_exists('assigned_to', $data)) {
            $assigned = (int) $data['assigned_to'];
            $fields['assigned_to'] = $assigned > 0 ? $assigned : 0;
            $formats[] = '%d';
        }

        if (array_key_exists('target_type', $data) || array_key_exists('target_reference', $data) || array_key_exists('target_label', $data)) {
            $current = self::get($message_id);
            $current_type = $current && isset($current->target_type) ? $current->target_type : self::TARGET_ALL;
            $current_reference = $current && isset($current->target_reference) ? $current->target_reference : null;
            $current_label = $current && isset($current->target_label) ? $current->target_label : '';

            $target = self::normalize_target(
                array_key_exists('target_type', $data) ? $data['target_type'] : $current_type,
                array_key_exists('target_reference', $data) ? $data['target_reference'] : $current_reference,
                array_key_exists('target_label', $data) ? $data['target_label'] : $current_label
            );

            $fields['target_type'] = $target['type'];
            $fields['target_reference'] = $target['reference'];
            $fields['target_label'] = $target['label'];
            $formats[] = '%s';
            $formats[] = '%d';
            $formats[] = '%s';
        }

        if (array_key_exists('source_url', $data)) {
            $fields['source_url'] = esc_url_raw($data['source_url']);
            $formats[] = '%s';
        }

        if (array_key_exists('meta', $data)) {
            $meta = self::sanitize_meta($data['meta']);
            $fields['meta'] = !empty($meta) ? wp_json_encode($meta) : null;
            $formats[] = '%s';
        }

        if (array_key_exists('activity_log', $data)) {
            $entries = self::sanitize_activity_entries($data['activity_log']);
            $fields['activity_log'] = !empty($entries) ? wp_json_encode($entries) : null;
            $formats[] = '%s';
        }

        if (empty($fields)) {
            return true;
        }

        $result = $wpdb->update($table, $fields, array('id' => $message_id), $formats, array('%d'));
        if ($result === false) {
            return new WP_Error('mj_contact_message_update_failed', __('Mise à jour impossible.', 'mj-member'));
        }

        return true;
    }

    /**
     * @param array<string,mixed> $args
     * @return array<int,object>
     */
    public static function query(array $args = array()) {
        global $wpdb;
        $table = self::get_table_name();

        $defaults = array(
            'search' => '',
            'status' => '',
            'target_type' => '',
            'target_reference' => null,
            'assigned_to' => 0,
            'sender_email' => '',
            'member_id' => 0,
            'per_page' => 20,
            'paged' => 1,
            'order' => 'DESC',
            'orderby' => 'created_at',
            'date_start' => '',
            'date_end' => '',
            'read_state' => '',
        );
        $query_args = wp_parse_args($args, $defaults);

        $where = array('1=1');
        $params = array();

        $status = $query_args['status'] !== '' ? self::sanitize_status($query_args['status']) : '';
        if ($status !== '') {
            $where[] = 'status = %s';
            $params[] = $status;
        }

        $target_type = isset($query_args['target_type']) ? sanitize_key($query_args['target_type']) : '';
        $target_reference = null;
        $target_reference_provided = false;

        if (array_key_exists('target_reference', $query_args)) {
            $target_reference_raw = $query_args['target_reference'];
            if ($target_reference_raw !== null && $target_reference_raw !== '') {
                $target_reference = (int) $target_reference_raw;
                $target_reference_provided = true;
            }
        }

        if ($target_type !== '' && $target_reference_provided) {
            $meta_pattern = sprintf('%%"recipient_keys":"%%%s%%"', $wpdb->esc_like($target_type . ':' . $target_reference));
            $where[] = '((target_type = %s AND target_reference = %d) OR (meta IS NOT NULL AND meta LIKE %s))';
            $params[] = $target_type;
            $params[] = $target_reference;
            $params[] = $meta_pattern;
        } elseif ($target_type !== '') {
            $meta_pattern = sprintf('%%"recipient_keys":"%%%s%%"', $wpdb->esc_like($target_type));
            $where[] = '(target_type = %s OR (meta IS NOT NULL AND meta LIKE %s))';
            $params[] = $target_type;
            $params[] = $meta_pattern;
        } elseif ($target_reference_provided) {
            $where[] = 'target_reference = %d';
            $params[] = $target_reference;
        }

        $assigned_to = (int) $query_args['assigned_to'];
        if ($assigned_to > 0) {
            $where[] = 'assigned_to = %d';
            $params[] = $assigned_to;
        }

        $sender_email = sanitize_email($query_args['sender_email']);
        $member_id = (int) $query_args['member_id'];

        $owner_clauses = array();
        $owner_params = array();

        if ($member_id > 0) {
            $owner_clauses[] = '(meta IS NOT NULL AND meta LIKE %s)';
            $owner_params[] = '%' . $wpdb->esc_like('"member_id":"' . $member_id . '"') . '%';
        }

        if ($sender_email !== '') {
            $owner_clauses[] = 'sender_email = %s';
            $owner_params[] = $sender_email;
        }

        if (!empty($owner_clauses)) {
            $where[] = count($owner_clauses) > 1 ? '(' . implode(' OR ', $owner_clauses) . ')' : $owner_clauses[0];
            $params = array_merge($params, $owner_params);
        }

        $read_state = sanitize_key($query_args['read_state']);
        if ($read_state === 'unread') {
            $where[] = 'is_read = 0';
        } elseif ($read_state === 'read') {
            $where[] = 'is_read = 1';
        }

        $date_start = sanitize_text_field($query_args['date_start']);
        if ($date_start !== '') {
            $where[] = 'created_at >= %s';
            $params[] = $date_start;
        }

        $date_end = sanitize_text_field($query_args['date_end']);
        if ($date_end !== '') {
            $where[] = 'created_at <= %s';
            $params[] = $date_end;
        }

        $search = isset($query_args['search']) ? trim((string) $query_args['search']) : '';
        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where[] = '(sender_name LIKE %s OR sender_email LIKE %s OR subject LIKE %s OR message LIKE %s)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $order = strtoupper((string) $query_args['order']);
        $order = in_array($order, array('ASC', 'DESC'), true) ? $order : 'DESC';
        $allowed_orderby = array('created_at', 'updated_at', 'status');
        $orderby = in_array($query_args['orderby'], $allowed_orderby, true) ? $query_args['orderby'] : 'created_at';

        $per_page = max(1, (int) $query_args['per_page']);
        $paged = max(1, (int) $query_args['paged']);
        $offset = ($paged - 1) * $per_page;

        $where_sql = implode(' AND ', $where);

        $sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = $offset;

        $prepared = $wpdb->prepare($sql, $params);
        $results = $wpdb->get_results($prepared);

        return is_array($results) ? $results : array();
    }

    /**
     * Récupère les messages visibles par un utilisateur avec pagination consolidée.
     *
     * @param int   $user_id Identifiant WordPress.
     * @param array $args    Options de requête (pagination, états, filtres).
     *
     * @return array<int,object>
     */
    public static function query_for_user($user_id, array $args = array()) {
        global $wpdb;
        $table = self::get_table_name();

        $defaults = array(
            'per_page' => 50,
            'paged' => 1,
            'order' => 'DESC',
            'orderby' => 'created_at',
            'status' => '',
            'read_state' => '',
            'search' => '',
            'date_start' => '',
            'date_end' => '',
            'include_assigned' => true,
            'include_all_targets' => false,
            'extra_targets' => array(),
            'member_id' => 0,
            'sender_email' => '',
            'include_owner' => false,
        );

        $args = wp_parse_args($args, $defaults);

        $visibility_params = array();
        $visibility_clause = self::build_user_visibility_clause($user_id, $args, $visibility_params);

        if ($visibility_clause === '') {
            return array();
        }

        $where = array($visibility_clause);
        $params = $visibility_params;

        $status = $args['status'] !== '' ? self::sanitize_status($args['status']) : '';
        if ($status !== '') {
            $where[] = 'status = %s';
            $params[] = $status;
        }

        $read_state = sanitize_key($args['read_state']);
        if ($read_state === 'unread') {
            $where[] = 'is_read = 0';
        } elseif ($read_state === 'read') {
            $where[] = 'is_read = 1';
        }

        $date_start = sanitize_text_field($args['date_start']);
        if ($date_start !== '') {
            $where[] = 'created_at >= %s';
            $params[] = $date_start;
        }

        $date_end = sanitize_text_field($args['date_end']);
        if ($date_end !== '') {
            $where[] = 'created_at <= %s';
            $params[] = $date_end;
        }

        $search = isset($args['search']) ? trim((string) $args['search']) : '';
        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where[] = '(sender_name LIKE %s OR sender_email LIKE %s OR subject LIKE %s OR message LIKE %s)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $where_sql = implode(' AND ', $where);

        /**
         * Permet de filtrer la clause WHERE utilisée pour la requête consolidée utilisateur.
         *
         * @param string $where_sql Clause WHERE sans le mot-clé.
         * @param int    $user_id    Identifiant ciblé.
         * @param array  $args       Arguments de requête.
         */
        $where_sql = apply_filters('mj_member_contact_messages_user_query_where', $where_sql, $user_id, $args);

        /**
         * Permet de filtrer les paramètres de la requête consolidée utilisateur.
         *
         * @param array<int,mixed> $params Paramètres pour $wpdb->prepare.
         * @param int              $user_id Identifiant ciblé.
         * @param array            $args    Arguments de requête.
         */
        $params = apply_filters('mj_member_contact_messages_user_query_params', $params, $user_id, $args);

        $order = strtoupper((string) $args['order']);
        $order = in_array($order, array('ASC', 'DESC'), true) ? $order : 'DESC';
        $allowed_orderby = array('created_at', 'updated_at', 'status');
        $orderby = in_array($args['orderby'], $allowed_orderby, true) ? $args['orderby'] : 'created_at';

        $per_page = max(1, (int) $args['per_page']);
        $paged = max(1, (int) $args['paged']);
        $offset = ($paged - 1) * $per_page;

        $sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = $offset;

        $prepared = $wpdb->prepare($sql, $params);
        $results = $wpdb->get_results($prepared);

        return is_array($results) ? $results : array();
    }

    /**
     * @param array<string,mixed> $args
     * @return int
     */
    public static function count(array $args = array()) {
        global $wpdb;
        $table = self::get_table_name();

        $defaults = array(
            'search' => '',
            'status' => '',
            'target_type' => '',
            'target_reference' => null,
            'assigned_to' => 0,
            'sender_email' => '',
            'member_id' => 0,
            'date_start' => '',
            'date_end' => '',
            'read_state' => '',
        );
        $query_args = wp_parse_args($args, $defaults);

        $where = array('1=1');
        $params = array();

        $status = $query_args['status'] !== '' ? self::sanitize_status($query_args['status']) : '';
        if ($status !== '') {
            $where[] = 'status = %s';
            $params[] = $status;
        }

        $target_type = isset($query_args['target_type']) ? sanitize_key($query_args['target_type']) : '';
        $target_reference = null;
        $target_reference_provided = false;

        if (array_key_exists('target_reference', $query_args)) {
            $target_reference_raw = $query_args['target_reference'];
            if ($target_reference_raw !== null && $target_reference_raw !== '') {
                $target_reference = (int) $target_reference_raw;
                $target_reference_provided = true;
            }
        }

        if ($target_type !== '' && $target_reference_provided) {
            $meta_pattern = sprintf('%%"recipient_keys":"%%%s%%"', $wpdb->esc_like($target_type . ':' . $target_reference));
            $where[] = '((target_type = %s AND target_reference = %d) OR (meta IS NOT NULL AND meta LIKE %s))';
            $params[] = $target_type;
            $params[] = $target_reference;
            $params[] = $meta_pattern;
        } elseif ($target_type !== '') {
            $meta_pattern = sprintf('%%"recipient_keys":"%%%s%%"', $wpdb->esc_like($target_type));
            $where[] = '(target_type = %s OR (meta IS NOT NULL AND meta LIKE %s))';
            $params[] = $target_type;
            $params[] = $meta_pattern;
        } elseif ($target_reference_provided) {
            $where[] = 'target_reference = %d';
            $params[] = $target_reference;
        }

        $assigned_to = (int) $query_args['assigned_to'];
        if ($assigned_to > 0) {
            $where[] = 'assigned_to = %d';
            $params[] = $assigned_to;
        }

        $sender_email = sanitize_email($query_args['sender_email']);
        $member_id = (int) $query_args['member_id'];

        $owner_clauses = array();
        $owner_params = array();

        if ($member_id > 0) {
            $owner_clauses[] = '(meta IS NOT NULL AND meta LIKE %s)';
            $owner_params[] = '%' . $wpdb->esc_like('"member_id":"' . $member_id . '"') . '%';
        }

        if ($sender_email !== '') {
            $owner_clauses[] = 'sender_email = %s';
            $owner_params[] = $sender_email;
        }

        if (!empty($owner_clauses)) {
            $where[] = count($owner_clauses) > 1 ? '(' . implode(' OR ', $owner_clauses) . ')' : $owner_clauses[0];
            $params = array_merge($params, $owner_params);
        }

        $read_state = sanitize_key($query_args['read_state']);
        if ($read_state === 'unread') {
            $where[] = 'is_read = 0';
        } elseif ($read_state === 'read') {
            $where[] = 'is_read = 1';
        }

        $date_start = sanitize_text_field($query_args['date_start']);
        if ($date_start !== '') {
            $where[] = 'created_at >= %s';
            $params[] = $date_start;
        }

        $date_end = sanitize_text_field($query_args['date_end']);
        if ($date_end !== '') {
            $where[] = 'created_at <= %s';
            $params[] = $date_end;
        }

        $search = isset($query_args['search']) ? trim((string) $query_args['search']) : '';
        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where[] = '(sender_name LIKE %s OR sender_email LIKE %s OR subject LIKE %s OR message LIKE %s)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $where_sql = implode(' AND ', $where);
        $sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        $count = $wpdb->get_var($sql);
        return $count ? (int) $count : 0;
    }

    /**
     * Compte les messages visibles par un utilisateur dans la vue consolidée.
     *
     * @param int   $user_id Identifiant WordPress.
     * @param array $args    Filtres supplémentaires (état, recherche, cibles…).
     *
     * @return int
     */
    public static function count_for_user($user_id, array $args = array()) {
        global $wpdb;
        $table = self::get_table_name();

        $defaults = array(
            'status' => '',
            'read_state' => '',
            'search' => '',
            'date_start' => '',
            'date_end' => '',
            'include_assigned' => true,
            'include_all_targets' => false,
            'extra_targets' => array(),
            'member_id' => 0,
            'sender_email' => '',
            'include_owner' => false,
        );

        $args = wp_parse_args($args, $defaults);

        $visibility_params = array();
        $visibility_clause = self::build_user_visibility_clause($user_id, $args, $visibility_params);

        if ($visibility_clause === '') {
            return 0;
        }

        $where = array($visibility_clause);
        $params = $visibility_params;

        $status = $args['status'] !== '' ? self::sanitize_status($args['status']) : '';
        if ($status !== '') {
            $where[] = 'status = %s';
            $params[] = $status;
        }

        $read_state = sanitize_key($args['read_state']);
        if ($read_state === 'unread') {
            $where[] = 'is_read = 0';
        } elseif ($read_state === 'read') {
            $where[] = 'is_read = 1';
        }

        $date_start = sanitize_text_field($args['date_start']);
        if ($date_start !== '') {
            $where[] = 'created_at >= %s';
            $params[] = $date_start;
        }

        $date_end = sanitize_text_field($args['date_end']);
        if ($date_end !== '') {
            $where[] = 'created_at <= %s';
            $params[] = $date_end;
        }

        $search = isset($args['search']) ? trim((string) $args['search']) : '';
        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where[] = '(sender_name LIKE %s OR sender_email LIKE %s OR subject LIKE %s OR message LIKE %s)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $where_sql = implode(' AND ', $where);

        /**
         * Filtre la clause WHERE utilisée pour le comptage consolidé utilisateur.
         *
         * @param string $where_sql Clause WHERE sans le mot-clé.
         * @param int    $user_id    Identifiant utilisateur.
         * @param array  $args       Arguments fournis.
         */
        $where_sql = apply_filters('mj_member_contact_messages_user_count_where', $where_sql, $user_id, $args);

        /**
         * Filtre les paramètres utilisés pour le comptage consolidé utilisateur.
         *
         * @param array<int,mixed> $params Paramètres pour $wpdb->prepare.
         * @param int              $user_id Identifiant utilisateur.
         * @param array            $args    Arguments fournis.
         */
        $params = apply_filters('mj_member_contact_messages_user_count_params', $params, $user_id, $args);

        $sql = "SELECT COUNT(DISTINCT id) FROM {$table} WHERE {$where_sql}";
        $prepared = $wpdb->prepare($sql, $params);
        $count = $wpdb->get_var($prepared);

        return $count ? (int) $count : 0;
    }

    /**
     * Compte les messages non lus pertinents pour un utilisateur donné.
     *
     * @param int   $user_id
     * @param array $args
     *
     * @return int
     */
    public static function count_unread_for_user($user_id, array $args = array()) {
        global $wpdb;

        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            return 0;
        }

        $table = self::get_table_name();

        $defaults = array(
            'include_all_targets' => true,
            'extra_targets' => array(),
            'member_id' => 0,
            'sender_email' => '',
        );
        $args = wp_parse_args($args, $defaults);

        $member_id = isset($args['member_id']) ? (int) $args['member_id'] : 0;
        $sender_email = isset($args['sender_email']) ? sanitize_email($args['sender_email']) : '';
        $extra_targets = !empty($args['extra_targets']) && is_array($args['extra_targets']) ? $args['extra_targets'] : array();

        $visibility_args = array(
            'include_assigned' => true,
            'include_all_targets' => !empty($args['include_all_targets']),
            'extra_targets' => $extra_targets,
            'member_id' => $member_id,
            'sender_email' => $sender_email,
            'include_owner' => ($member_id > 0 || $sender_email !== ''),
        );

        $params = array();
        $visibility_clause = self::build_user_visibility_clause($user_id, $visibility_args, $params);

        if ($visibility_clause === '') {
            return 0;
        }

        $where = 'is_read = 0 AND ' . $visibility_clause;

        /**
         * Permet de filtrer les conditions utilisées pour le comptage des messages non lus.
         *
         * @param string $where_clause Clause WHERE (sans le mot-clé WHERE).
         * @param int    $user_id      Identifiant utilisateur ciblé.
         * @param array  $args         Arguments supplémentaires.
         */
        $where = apply_filters('mj_member_contact_messages_unread_where', $where, $user_id, $args);

        /**
         * Permet de filtrer les paramètres utilisés pour le comptage des messages non lus.
         *
         * @param array<int,mixed> $params Paramètres pour $wpdb->prepare.
         * @param int              $user_id Identifiant utilisateur ciblé.
         * @param array            $args   Arguments supplémentaires.
         */
        $params = apply_filters('mj_member_contact_messages_unread_params', $params, $user_id, $args);

        $sql = "SELECT COUNT(DISTINCT id) FROM {$table} WHERE {$where}";
        $prepared = $wpdb->prepare($sql, $params);
        $count = $wpdb->get_var($prepared);

        $count = $count ? (int) $count : 0;

        /**
         * Filtre le total de messages non lus retourné pour un utilisateur.
         *
         * @param int   $count  Nombre de messages non lus.
         * @param int   $user_id Identifiant utilisateur ciblé.
         * @param array $args   Arguments supplémentaires.
         */
        return (int) apply_filters('mj_member_contact_messages_unread_count', $count, $user_id, $args);
    }

    /**
     * @param int $message_id
     * @return true|WP_Error
     */
    public static function delete($message_id) {
        global $wpdb;
        $table = self::get_table_name();
        $message_id = (int) $message_id;
        if ($message_id <= 0) {
            return new WP_Error('mj_contact_message_invalid_id', __('Identifiant message invalide.', 'mj-member'));
        }

        $result = $wpdb->delete($table, array('id' => $message_id), array('%d'));
        if ($result === false) {
            return new WP_Error('mj_contact_message_delete_failed', __('Suppression impossible.', 'mj-member'));
        }

        return true;
    }

    /**
     * @param int $message_id
     * @param string $action
     * @param array<string,mixed> $context
    * @return true|WP_Error
     */
    public static function record_activity($message_id, $action, array $context = array()) {
        $message = self::get($message_id);
        if (!$message) {
            return false;
        }

        $entries = self::get_activity_entries($message);
        $entries[] = array(
            'action' => sanitize_key($action),
            'timestamp' => current_time('mysql'),
            'user_id' => isset($context['user_id']) ? (int) $context['user_id'] : get_current_user_id(),
            'note' => isset($context['note']) ? sanitize_text_field($context['note']) : '',
            'meta' => self::sanitize_activity_meta_collection(isset($context['meta']) ? $context['meta'] : array()),
        );

        return self::update($message_id, array('activity_log' => $entries)) === true;
    }

    /**
     * @param object $message
     * @return array<int,array<string,mixed>>
     */
    public static function get_activity_entries($message) {
        if (!$message || !isset($message->activity_log) || $message->activity_log === null || $message->activity_log === '') {
            return array();
        }

        $decoded = json_decode($message->activity_log, true);
        if (!is_array($decoded)) {
            return array();
        }

        $entries = array();
        foreach ($decoded as $entry) {
            if (!is_array($entry) || !self::is_valid_activity_entry($entry)) {
                continue;
            }
            $entries[] = array(
                'action' => sanitize_key(isset($entry['action']) ? $entry['action'] : ''),
                'timestamp' => sanitize_text_field(isset($entry['timestamp']) ? $entry['timestamp'] : ''),
                'user_id' => isset($entry['user_id']) ? (int) $entry['user_id'] : 0,
                'note' => sanitize_text_field(isset($entry['note']) ? $entry['note'] : ''),
                'meta' => self::sanitize_activity_meta_collection(isset($entry['meta']) ? $entry['meta'] : array()),
            );
        }

        return $entries;
    }

    /**
     * @param array<string,mixed> $entry
     * @return bool
     */
    private static function is_valid_activity_entry($entry) {
        if (!is_array($entry)) {
            return false;
        }

        return isset($entry['action'], $entry['timestamp']);
    }

    /**
     * @param mixed $entries
     * @return array<int,array<string,mixed>>
     */
    private static function sanitize_activity_entries($entries) {
        if (!is_array($entries)) {
            return array();
        }

        $sanitized = array();
        foreach ($entries as $entry) {
            if (!is_array($entry) || !self::is_valid_activity_entry($entry)) {
                continue;
            }

            $sanitized[] = array(
                'action' => sanitize_key(isset($entry['action']) ? $entry['action'] : ''),
                'timestamp' => sanitize_text_field(isset($entry['timestamp']) ? $entry['timestamp'] : ''),
                'user_id' => isset($entry['user_id']) ? (int) $entry['user_id'] : 0,
                'note' => sanitize_text_field(isset($entry['note']) ? $entry['note'] : ''),
                'meta' => self::sanitize_activity_meta_collection(isset($entry['meta']) ? $entry['meta'] : array()),
            );
        }

        return $sanitized;
    }

    /**
     * @param mixed $meta
     * @return array<string,string>
     */
    private static function sanitize_activity_meta_collection($meta) {
        if (!is_array($meta)) {
            return array();
        }

        $clean = array();
        foreach ($meta as $key => $value) {
            $clean_key = sanitize_key((string) $key);
            if ($clean_key === '' || !is_scalar($value)) {
                continue;
            }

            $sanitized_value = self::sanitize_activity_meta_value($clean_key, (string) $value);
            if ($sanitized_value !== null) {
                $clean[$clean_key] = $sanitized_value;
            }
        }

        return $clean;
    }

    /**
     * @param string $key
     * @param string $value
     * @return string|null
     */
    private static function sanitize_activity_meta_value($key, $value) {
        $rich_text_keys = array('body', 'reply_body', 'reply_body_html', 'message');

        if (in_array($key, $rich_text_keys, true)) {
            return wp_kses_post($value);
        }

        return sanitize_text_field($value);
    }

    /**
     * @param mixed $meta
     * @return array<string,mixed>
     */
    private static function sanitize_meta($meta) {
        if (!is_array($meta)) {
            return array();
        }

        $clean = array();
        foreach ($meta as $key => $value) {
            $clean_key = sanitize_key((string) $key);
            if ($clean_key === '' || !is_scalar($value)) {
                continue;
            }

            $sanitized_value = self::sanitize_activity_meta_value($clean_key, (string) $value);
            if ($sanitized_value !== null) {
                $clean[$clean_key] = $sanitized_value;
            }
        }

        return $clean;
    }

    /**
     * @param object|array|null $message
     * @return bool
     */
    public static function is_unread($message) {
        if (is_object($message) && isset($message->is_read)) {
            return (int) $message->is_read === 0;
        }

        if (is_array($message) && isset($message['is_read'])) {
            return (int) $message['is_read'] === 0;
        }

        return true;
    }

    /**
     * @param int $message_id
     * @return bool
     */
    public static function mark_as_read($message_id) {
        $message = self::get($message_id);
        if (!$message) {
            return false;
        }

        if (!self::is_unread($message)) {
            return true;
        }

        $result = self::update($message_id, array('is_read' => 1));
        if (is_wp_error($result) || !$result) {
            return false;
        }

        self::record_activity($message_id, 'marked_read', array(
            'note' => __('Message marqué comme lu.', 'mj-member'),
        ));

        return true;
    }

    /**
     * @param int $message_id
     * @return bool
     */
    public static function mark_as_unread($message_id) {
        $message = self::get($message_id);
        if (!$message) {
            return false;
        }

        if (self::is_unread($message)) {
            return true;
        }

        $result = self::update($message_id, array('is_read' => 0));
        if (is_wp_error($result) || !$result) {
            return false;
        }

        self::record_activity($message_id, 'marked_unread', array(
            'note' => __('Message marqué comme non lu.', 'mj-member'),
        ));

        return true;
    }

    /**
     * @param string $status
     * @return string
     */
    public static function sanitize_status($status) {
        $status = sanitize_key($status);
        $allowed = array_keys(self::get_status_labels());
        if (!in_array($status, $allowed, true)) {
            return self::STATUS_NEW;
        }
        return $status;
    }

    /**
     * @param mixed $type
     * @param mixed $reference
     * @param mixed $label
     * @return array<string,mixed>
     */
    private static function normalize_target($type, $reference, $label) {
        $type = sanitize_key((string) $type);
        if (!in_array($type, array(self::TARGET_ANIMATEUR, self::TARGET_COORDINATEUR, self::TARGET_MEMBER, self::TARGET_ALL), true)) {
            $type = self::TARGET_ALL;
        }

        $reference = is_numeric($reference) ? (int) $reference : 0;
        if ($reference <= 0) {
            $reference = 0;
        }

        $label = sanitize_text_field((string) $label);
        if ($label === '') {
            $target_labels = self::get_target_labels();
            if ($type === self::TARGET_ANIMATEUR && $reference) {
                $label = sprintf(__('Animateur #%d', 'mj-member'), $reference);
            } elseif ($type === self::TARGET_MEMBER && $reference) {
                $label = sprintf(__('Membre #%d', 'mj-member'), $reference);
            } elseif (isset($target_labels[$type])) {
                $label = $target_labels[$type];
            }
        }

        return array(
            'type' => $type,
            'reference' => $reference,
            'label' => $label,
        );
    }
}

class_alias(__NAMESPACE__ . '\\MjContactMessages', 'MjContactMessages');
