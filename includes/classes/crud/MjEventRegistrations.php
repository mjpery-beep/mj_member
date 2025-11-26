<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . 'MjMembers_CRUD.php';
require_once plugin_dir_path(__FILE__) . 'MjEventAnimateurs.php';

class MjEventRegistrations {
    /** @var array<string,mixed> */
    private static $last_creation_context = array();

    /** @var array<string,array<string,bool>> */
    private static $members_table_columns = array();

    /**
     * @param string $table_name
     * @return array<string,bool>
     */
    private static function get_members_table_columns($table_name) {
        $cache_key = (string) $table_name;
        if (isset(self::$members_table_columns[$cache_key])) {
            return self::$members_table_columns[$cache_key];
        }

        global $wpdb;
        $sanitized = preg_replace('/[^A-Za-z0-9_]/', '', $cache_key);
        if ($sanitized === '') {
            self::$members_table_columns[$cache_key] = array();
            return self::$members_table_columns[$cache_key];
        }

        $columns = $wpdb->get_col("SHOW COLUMNS FROM `{$sanitized}`");
        $map = array();
        if (is_array($columns)) {
            foreach ($columns as $column_name) {
                $column_name = strtolower((string) $column_name);
                if ($column_name !== '') {
                    $map[$column_name] = true;
                }
            }
        }

        self::$members_table_columns[$cache_key] = $map;
        return $map;
    }

    /**
     * @param string $table_name
     * @return string
     */
    private static function build_member_select_fields($table_name) {
        $available = self::get_members_table_columns($table_name);
        $desired = array(
            'id' => '0',
            'first_name' => "''",
            'last_name' => "''",
            'nickname' => "''",
            'role' => "''",
            'birth_date' => 'NULL',
            'email' => "''",
            'phone' => "''",
            'sms_opt_in' => '0',
            'guardian_id' => '0',
            'is_autonomous' => 'NULL',
        );

        $fields = array();
        foreach ($desired as $column => $default_sql) {
            $key = strtolower($column);
            if (isset($available[$key])) {
                $fields[] = $column;
            } else {
                $fields[] = $default_sql . ' AS ' . $column;
            }
        }

        return implode(', ', $fields);
    }

    const STATUS_PENDING = 'en_attente';
    const STATUS_CONFIRMED = 'valide';
    const STATUS_CANCELLED = 'annule';
    const STATUS_WAITLIST = 'liste_attente';

    const META_STATUS_LABELS = array(
        self::STATUS_PENDING => 'En attente',
        self::STATUS_CONFIRMED => 'Valide',
        self::STATUS_CANCELLED => 'Annule',
        self::STATUS_WAITLIST => 'Liste d\'attente',
    );

    /**
     * @return array<string, string>
     */
    public static function get_status_labels() {
        return self::META_STATUS_LABELS;
    }

    /**
     * @return array<string,string>
     */
    public static function get_payment_status_labels() {
        return array(
            'unpaid' => __('À payer', 'mj-member'),
            'paid' => __('Payé', 'mj-member'),
        );
    }

    /**
     * @return array<string,mixed>
     */
    public static function get_last_creation_context() {
        return self::$last_creation_context;
    }

    /**
     * @param int $event_id
     * @return array<int, object>
     */
    public static function get_by_event($event_id) {
        $event_id = (int) $event_id;
        if ($event_id <= 0) {
            return array();
        }

        if (class_exists('MjEventAttendance')) {
            MjEventAttendance::get_table_name();
        }

        global $wpdb;
        $table = mj_member_get_event_registrations_table_name();

        $registrations = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE event_id = %d ORDER BY created_at DESC",
                $event_id
            )
        );

        if (empty($registrations)) {
            return array();
        }

        if (class_exists('MjEventAttendance')) {
            foreach ($registrations as $registration_row) {
                $registration_row->occurrence_assignments = MjEventAttendance::get_registration_assignments($registration_row);
            }
        } else {
            foreach ($registrations as $registration_row) {
                $registration_row->occurrence_assignments = array(
                    'mode' => 'all',
                    'occurrences' => array(),
                );
            }
        }

        $member_ids = array();
        $guardian_ids = array();
        foreach ($registrations as $registration_row) {
            if (!empty($registration_row->member_id)) {
                $member_ids[(int) $registration_row->member_id] = true;
            }
            if (!empty($registration_row->guardian_id)) {
                $guardian_ids[(int) $registration_row->guardian_id] = true;
            }
        }

        $members_index = array();
        $all_member_ids = array_keys($member_ids + $guardian_ids);
        if (!empty($all_member_ids)) {
            $members_table = MjMembers_CRUD::getTableName(MjMembers_CRUD::TABLE_NAME);
            $can_query_members = true;
            if (function_exists('mj_member_table_exists')) {
                $can_query_members = mj_member_table_exists($members_table);
            }

            if ($can_query_members) {
                $placeholders = implode(',', array_fill(0, count($all_member_ids), '%d'));
                $member_fields = self::build_member_select_fields($members_table);
                $member_sql = "SELECT {$member_fields} FROM {$members_table} WHERE id IN ({$placeholders})";
                $params = array_merge(array($member_sql), $all_member_ids);
                $prepared_members = call_user_func_array(array($wpdb, 'prepare'), $params);
                $members = $wpdb->get_results($prepared_members);
                if (is_array($members)) {
                    foreach ($members as $member) {
                        $members_index[(int) $member->id] = $member;
                    }
                }
            }
        }

        foreach ($registrations as $registration_row) {
            $member_id = isset($registration_row->member_id) ? (int) $registration_row->member_id : 0;
            $guardian_id = isset($registration_row->guardian_id) ? (int) $registration_row->guardian_id : 0;

            $member = ($member_id > 0 && isset($members_index[$member_id])) ? $members_index[$member_id] : null;
            if ($member) {
                $registration_row->first_name = $member->first_name;
                $registration_row->last_name = $member->last_name;
                $registration_row->nickname = isset($member->nickname) ? $member->nickname : '';
                $registration_row->role = isset($member->role) ? $member->role : '';
                $registration_row->birth_date = isset($member->birth_date) ? $member->birth_date : null;
                $registration_row->email = isset($member->email) ? $member->email : '';
                $registration_row->phone = isset($member->phone) ? $member->phone : '';
                $registration_row->sms_opt_in = !empty($member->sms_opt_in);
                $registration_row->is_autonomous = isset($member->is_autonomous) ? $member->is_autonomous : null;
                if (empty($registration_row->guardian_id) && !empty($member->guardian_id)) {
                    $registration_row->guardian_id = (int) $member->guardian_id;
                    if ($registration_row->guardian_id > 0) {
                        $guardian_id = (int) $registration_row->guardian_id;
                    }
                }
            } else {
                if (!isset($registration_row->first_name)) {
                    $registration_row->first_name = '';
                }
                if (!isset($registration_row->last_name)) {
                    $registration_row->last_name = '';
                }
                if (!isset($registration_row->nickname)) {
                    $registration_row->nickname = '';
                }
            }

            if (!isset($registration_row->role) || $registration_row->role === null) {
                $registration_row->role = '';
            }

            $guardian = ($guardian_id > 0 && isset($members_index[$guardian_id])) ? $members_index[$guardian_id] : null;
            if ($guardian) {
                $registration_row->guardian_first_name = $guardian->first_name;
                $registration_row->guardian_last_name = $guardian->last_name;
                $registration_row->guardian_phone = isset($guardian->phone) ? $guardian->phone : '';
                $registration_row->guardian_email = isset($guardian->email) ? $guardian->email : '';
                $registration_row->guardian_sms_opt_in = !empty($guardian->sms_opt_in);
            } else {
                if (!isset($registration_row->guardian_first_name)) {
                    $registration_row->guardian_first_name = '';
                }
                if (!isset($registration_row->guardian_last_name)) {
                    $registration_row->guardian_last_name = '';
                }
                if (!isset($registration_row->guardian_phone)) {
                    $registration_row->guardian_phone = '';
                }
                if (!isset($registration_row->guardian_email)) {
                    $registration_row->guardian_email = '';
                }
                if (!isset($registration_row->guardian_sms_opt_in)) {
                    $registration_row->guardian_sms_opt_in = 0;
                }
            }
        }

        return $registrations;
    }

    /**
     * @param int $registration_id
     * @return object|null
     */
    public static function get($registration_id) {
        global $wpdb;
        $table = mj_member_get_event_registrations_table_name();

        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", (int) $registration_id));
    }

    /**
     * @param int $event_id
     * @param int $member_id
     * @return object|null
     */
    public static function get_existing($event_id, $member_id) {
        global $wpdb;
        $table = mj_member_get_event_registrations_table_name();

        $sql = "SELECT * FROM {$table} WHERE event_id = %d AND member_id = %d ORDER BY created_at DESC LIMIT 1";
        return $wpdb->get_row($wpdb->prepare($sql, (int) $event_id, (int) $member_id));
    }

    /**
     * @param int $event_id
     * @param int $member_id
     * @param array<string, mixed> $args
     * @return int|WP_Error
     */
    public static function create($event_id, $member_id, array $args = array()) {
        self::$last_creation_context = array();

        $event_id = (int) $event_id;
        $member_id = (int) $member_id;

        if ($event_id <= 0 || $member_id <= 0) {
            return new WP_Error('mj_event_registration_invalid_ids', 'Evenement ou membre invalide.');
        }

        $send_notifications = true;
        if (array_key_exists('send_notifications', $args)) {
            $send_notifications = !empty($args['send_notifications']);
        } elseif (array_key_exists('notify', $args)) {
            $send_notifications = !empty($args['notify']);
        }

        $event = MjEvents_CRUD::find($event_id);
        if (!$event) {
            return new WP_Error('mj_event_registration_missing_event', 'Evenement introuvable.');
        }

        $member = MjMembers_CRUD::getById($member_id);
        if (!$member) {
            return new WP_Error('mj_event_registration_missing_member', 'Membre introuvable.');
        }

        $validation = self::validate_registration($event, $member, $event_id, $member_id, $args);
        if (is_wp_error($validation)) {
            return $validation;
        }

        $capacity_total = isset($event->capacity_total) ? (int) $event->capacity_total : 0;
        $waitlist_limit = isset($event->capacity_waitlist) ? (int) $event->capacity_waitlist : 0;
        $registration_status = self::STATUS_PENDING;
        $active_before = 0;
        $waitlist_before = 0;

        if ($capacity_total > 0) {
            $active_before = self::count_active_registrations($event_id);
            if ($active_before >= $capacity_total) {
                if ($waitlist_limit > 0) {
                    $waitlist_before = self::count_waitlist_registrations($event_id);
                    if ($waitlist_before >= $waitlist_limit) {
                        return new WP_Error('mj_event_registration_full', 'Cet evenement est complet et la liste d\'attente est fermee.');
                    }
                    $registration_status = self::STATUS_WAITLIST;
                } else {
                    return new WP_Error('mj_event_registration_full', 'Cet evenement est complet.');
                }
            }
        }

        $guardian_id = isset($args['guardian_id']) ? (int) $args['guardian_id'] : 0;
        if ($guardian_id <= 0 && isset($member->guardian_id)) {
            $guardian_id = (int) $member->guardian_id;
        }
        if ($guardian_id <= 0) {
            $guardian_id = null;
        }

        $notes = isset($args['notes']) ? sanitize_textarea_field($args['notes']) : '';

        global $wpdb;
        $table = mj_member_get_event_registrations_table_name();

        $insert = array(
            'event_id' => $event_id,
            'member_id' => $member_id,
            'guardian_id' => $guardian_id,
            'statut' => $registration_status,
            'notes' => ($notes !== '') ? $notes : null,
            'created_at' => current_time('mysql'),
        );

        $result = $wpdb->insert($table, $insert, array('%d', '%d', '%d', '%s', '%s', '%s'));
        if ($result === false) {
            return new WP_Error('mj_event_registration_insert_failed', 'Impossible de creer cette inscription.');
        }

        $registration_id = (int) $wpdb->insert_id;
        $registration = self::get($registration_id);

        self::$last_creation_context = array(
            'registration_id' => $registration_id,
            'status' => $registration_status,
            'is_waitlist' => ($registration_status === self::STATUS_WAITLIST),
            'capacity_total' => $capacity_total,
            'waitlist_limit' => $waitlist_limit,
            'active_before' => $active_before,
            'waitlist_before' => $waitlist_before,
            'notifications_sent' => $send_notifications,
        );

        if ($send_notifications) {
            self::notify_registration_created(
                $registration,
                $event,
                $member,
                $guardian_id,
                array('is_waitlist' => ($registration_status === self::STATUS_WAITLIST))
            );
        }

        if ($registration_status !== self::STATUS_WAITLIST) {
            self::maybe_trigger_capacity_notification($event, $active_before + 1);
        }

        return $registration_id;
    }

    /**
     * @param int $registration_id
     * @param string $new_status
     * @return true|WP_Error
     */
    public static function update_status($registration_id, $new_status) {
        $registration_id = (int) $registration_id;
        if ($registration_id <= 0) {
            return new WP_Error('mj_event_registration_invalid_id', 'Inscription invalide.');
        }

        $new_status = sanitize_key($new_status);
        if (!array_key_exists($new_status, self::META_STATUS_LABELS)) {
            return new WP_Error('mj_event_registration_bad_status', 'Statut inconnu.');
        }

        $registration = self::get($registration_id);
        if (!$registration) {
            return new WP_Error('mj_event_registration_missing', 'Inscription introuvable.');
        }

        if (isset($registration->statut) && $registration->statut === $new_status) {
            return true;
        }

        $event_id = isset($registration->event_id) ? (int) $registration->event_id : 0;

        global $wpdb;
        $table = mj_member_get_event_registrations_table_name();
        $updated = $wpdb->update($table, array('statut' => $new_status), array('id' => $registration_id), array('%s'), array('%d'));

        if ($updated === false) {
            return new WP_Error('mj_event_registration_update_failed', 'Impossible de mettre a jour l inscription.');
        }

        if ($event_id > 0) {
            if ($new_status === self::STATUS_CANCELLED) {
                self::maybe_promote_waitlist($event_id);
            }
            self::sync_capacity_counters($event_id);
        }

        return true;
    }

    /**
     * @param int $registration_id
     * @param int $user_id
     * @return array<string,mixed>|WP_Error
     */
    public static function toggle_cash_payment($registration_id, $user_id = 0) {
        $registration_id = (int) $registration_id;
        if ($registration_id <= 0) {
            return new WP_Error('mj_event_payment_invalid_id', __('Inscription invalide.', 'mj-member'));
        }

        if (class_exists('MjEventAttendance')) {
            MjEventAttendance::get_table_name();
        }

        $registration = self::get($registration_id);
        if (!$registration) {
            return new WP_Error('mj_event_payment_missing', __('Inscription introuvable.', 'mj-member'));
        }

        global $wpdb;
        $table = mj_member_get_event_registrations_table_name();
        $current_status = isset($registration->payment_status) ? sanitize_key((string) $registration->payment_status) : 'unpaid';
        $current_method = isset($registration->payment_method) ? sanitize_key((string) $registration->payment_method) : '';

        $is_already_cash = ($current_status === 'paid' && $current_method === 'cash');
        $now = current_time('mysql');

        if ($is_already_cash) {
            $updated = $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table} SET payment_status = %s, payment_method = NULL, payment_recorded_at = NULL, payment_recorded_by = NULL WHERE id = %d",
                    'unpaid',
                    $registration_id
                )
            );
        } else {
            if ($user_id > 0) {
                $updated = $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$table} SET payment_status = %s, payment_method = %s, payment_recorded_at = %s, payment_recorded_by = %d WHERE id = %d",
                        'paid',
                        'cash',
                        $now,
                        (int) $user_id,
                        $registration_id
                    )
                );
            } else {
                $updated = $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$table} SET payment_status = %s, payment_method = %s, payment_recorded_at = %s, payment_recorded_by = NULL WHERE id = %d",
                        'paid',
                        'cash',
                        $now,
                        $registration_id
                    )
                );
            }
        }

        if ($updated === false) {
            return new WP_Error('mj_event_payment_update_failed', __('Impossible de mettre à jour le paiement.', 'mj-member'));
        }

        $updated_registration = self::get($registration_id);
        if (!$updated_registration) {
            return new WP_Error('mj_event_payment_refresh_failed', __('Impossible de recharger le paiement.', 'mj-member'));
        }

        return self::build_payment_snapshot($updated_registration);
    }

    /**
     * Marquer une inscription comme payée suite à un retour externe (Stripe, etc.).
     *
     * @param int   $registration_id
     * @param array $args            Données complémentaires : method, recorded_at, recorded_by, force.
     * @return array<string,mixed>|WP_Error
     */
    public static function apply_external_payment($registration_id, array $args = array()) {
        $registration_id = (int) $registration_id;
        if ($registration_id <= 0) {
            return new WP_Error('mj_event_payment_invalid_id', __('Inscription invalide.', 'mj-member'));
        }

        if (class_exists('MjEventAttendance')) {
            MjEventAttendance::get_table_name();
        }

        $registration = self::get($registration_id);
        if (!$registration) {
            return new WP_Error('mj_event_payment_missing', __('Inscription introuvable.', 'mj-member'));
        }

        $method = isset($args['method']) ? sanitize_key((string) $args['method']) : 'stripe_checkout';
        if ($method === '') {
            $method = 'stripe_checkout';
        }

        $force = !empty($args['force']);

        $current_status = isset($registration->payment_status) ? sanitize_key((string) $registration->payment_status) : 'unpaid';
        $current_method = isset($registration->payment_method) ? sanitize_key((string) $registration->payment_method) : '';

        if (!$force && $current_status === 'paid' && $current_method === $method) {
            return self::build_payment_snapshot($registration);
        }

        global $wpdb;
        $table = mj_member_get_event_registrations_table_name();
        $recorded_at = isset($args['recorded_at']) && $args['recorded_at'] !== ''
            ? sanitize_text_field((string) $args['recorded_at'])
            : current_time('mysql');
        $recorded_by = isset($args['recorded_by']) ? (int) $args['recorded_by'] : 0;

        $sql = "UPDATE {$table} SET payment_status = %s, payment_method = %s, payment_recorded_at = %s, payment_recorded_by = ";
        $params = array('paid', $method, $recorded_at);

        if ($recorded_by > 0) {
            $sql .= '%d';
            $params[] = $recorded_by;
        } else {
            $sql .= 'NULL';
        }

        $sql .= ' WHERE id = %d';
        $params[] = $registration_id;

        $updated = $wpdb->query($wpdb->prepare($sql, $params));
        if ($updated === false) {
            return new WP_Error('mj_event_payment_update_failed', __('Impossible de mettre à jour le paiement.', 'mj-member'));
        }

        $updated_registration = self::get($registration_id);
        if (!$updated_registration) {
            return new WP_Error('mj_event_payment_refresh_failed', __('Impossible de recharger le paiement.', 'mj-member'));
        }

        /**
         * Action déclenchée lorsqu'un paiement externe est appliqué à une inscription.
         *
         * @param int    $registration_id
         * @param object $updated_registration
         * @param array  $args
         */
        do_action('mj_member_event_registration_payment_confirmed', $registration_id, $updated_registration, $args);

        return self::build_payment_snapshot($updated_registration);
    }

    /**
     * @param object $registration
     * @return array<string,mixed>
     */
    public static function build_payment_snapshot($registration) {
        $status = isset($registration->payment_status) ? sanitize_key((string) $registration->payment_status) : 'unpaid';
        if ($status === '') {
            $status = 'unpaid';
        }

        $method = isset($registration->payment_method) ? sanitize_key((string) $registration->payment_method) : '';

        $recorded_at = isset($registration->payment_recorded_at) ? (string) $registration->payment_recorded_at : '';
        $recorded_at_label = '';
        if ($recorded_at !== '') {
            $timestamp = strtotime($recorded_at);
            if ($timestamp) {
                $format = get_option('date_format', 'd/m/Y') . ' ' . get_option('time_format', 'H:i');
                $recorded_at_label = wp_date($format, $timestamp);
            } else {
                $recorded_at_label = $recorded_at;
            }
        }

        $recorded_by_id = isset($registration->payment_recorded_by) ? (int) $registration->payment_recorded_by : 0;
        $recorded_by_name = '';
        if ($recorded_by_id > 0) {
            $user = get_userdata($recorded_by_id);
            if ($user) {
                $recorded_by_name = $user->display_name ? $user->display_name : $user->user_login;
            }
        }

        $labels = self::get_payment_status_labels();
        $status_label = isset($labels[$status]) ? $labels[$status] : $status;

        return array(
            'status' => $status,
            'status_label' => $status_label,
            'method' => $method,
            'recorded_at' => $recorded_at,
            'recorded_at_label' => $recorded_at_label,
            'recorded_by' => array(
                'id' => $recorded_by_id,
                'name' => $recorded_by_name,
            ),
        );
    }

    /**
     * @param int $registration_id
     * @return true|WP_Error
     */
    public static function delete($registration_id) {
        $registration_id = (int) $registration_id;
        if ($registration_id <= 0) {
            return new WP_Error('mj_event_registration_invalid_id', 'Inscription invalide.');
        }

        $registration = self::get($registration_id);
        if (!$registration) {
            return new WP_Error('mj_event_registration_missing', 'Inscription introuvable.');
        }

        $event_id = isset($registration->event_id) ? (int) $registration->event_id : 0;

        global $wpdb;
        $table = mj_member_get_event_registrations_table_name();
        $deleted = $wpdb->delete($table, array('id' => $registration_id), array('%d'));

        if ($deleted === false) {
            return new WP_Error('mj_event_registration_delete_failed', 'Suppression impossible.');
        }

        if ($event_id > 0) {
            self::maybe_promote_waitlist($event_id);
            self::sync_capacity_counters($event_id);
        }

        return true;
    }

    /**
     * @param object $event
     * @param object $member
     * @param int $event_id
     * @param int $member_id
     * @param array<string, mixed> $args
     * @return true|WP_Error
     */
    private static function validate_registration($event, $member, $event_id, $member_id, array $args) {
        $existing = self::get_existing($event_id, $member_id);
        if ($existing && $existing->statut !== self::STATUS_CANCELLED) {
            return new WP_Error('mj_event_registration_duplicate', 'Ce membre est deja inscrit a cet evenement.');
        }

        $now = current_time('timestamp');
        $event_start = strtotime($event->date_debut);
        $allow_late_registration = !empty($args['allow_late_registration']);

        if (!$allow_late_registration && $event_start && $now > $event_start) {
            return new WP_Error('mj_event_registration_event_started', 'L evenement a deja commence.');
        }

        $deadline = self::resolve_deadline($event);
        if (!$allow_late_registration && $deadline && $now > $deadline) {
            return new WP_Error('mj_event_registration_deadline', 'La date limite est depassee.');
        }

        $allow_guardian_registration = 0;
        if (isset($event->allow_guardian_registration)) {
            $allow_guardian_registration = (int) $event->allow_guardian_registration;
        }
        $member_role = isset($member->role) ? sanitize_key($member->role) : '';
        if ($allow_guardian_registration !== 1 && $member_role === MjMembers_CRUD::ROLE_TUTEUR) {
            return new WP_Error('mj_event_registration_guardian_blocked', 'Les tuteurs ne peuvent pas s\'inscrire a cet evenement.');
        }

        $age_validation = self::validate_age($event, $member);
        if (is_wp_error($age_validation)) {
            return $age_validation;
        }

        return true;
    }

    /**
     * @param object $event
     * @return int|null
     */
    private static function resolve_deadline($event) {
        if (!empty($event->date_fin_inscription) && $event->date_fin_inscription !== '0000-00-00 00:00:00') {
            $timestamp = strtotime($event->date_fin_inscription);
            return $timestamp ?: null;
        }

        if (!empty($event->date_debut)) {
            $start = strtotime($event->date_debut);
            if ($start) {
                return $start - (14 * DAY_IN_SECONDS);
            }
        }

        return null;
    }

    /**
     * @param object $event
     * @param object $member
     * @return true|WP_Error
     */
    private static function validate_age($event, $member) {
        $age_min = isset($event->age_min) ? (int) $event->age_min : 0;
        $age_max = isset($event->age_max) ? (int) $event->age_max : 0;

        if ($age_min <= 0 && $age_max <= 0) {
            return true;
        }

        if (empty($member->birth_date) || $member->birth_date === '0000-00-00') {
            return new WP_Error('mj_event_registration_missing_birth_date', 'La date de naissance du membre est inconnue.');
        }

        $reference = !empty($event->date_debut) ? strtotime($event->date_debut) : current_time('timestamp');
        if (!$reference) {
            $reference = current_time('timestamp');
        }

        $age = self::calculate_age($member->birth_date, $reference);
        if ($age_min > 0 && $age < $age_min) {
            return new WP_Error('mj_event_registration_age_min', 'Le membre est trop jeune pour cet evenement.');
        }
        if ($age_max > 0 && $age > $age_max) {
            return new WP_Error('mj_event_registration_age_max', 'Le membre est trop age pour cet evenement.');
        }

        return true;
    }

    /**
     * @param string $birth_date
     * @param int $reference
     * @return int
     */
    private static function calculate_age($birth_date, $reference) {
        $birth_timestamp = strtotime($birth_date);
        if (!$birth_timestamp) {
            return 0;
        }

        $birth = new DateTime('@' . $birth_timestamp);
        $reference_date = new DateTime('@' . $reference);
        $interval = $birth->diff($reference_date);

        return (int) $interval->y;
    }

    /**
     * @param int $event_id
     * @return int
     */
    private static function count_active_registrations($event_id) {
        return self::count_registrations_by_status($event_id, array(self::STATUS_PENDING, self::STATUS_CONFIRMED));
    }

    /**
     * @param int $event_id
     * @return int
     */
    private static function count_waitlist_registrations($event_id) {
        return self::count_registrations_by_status($event_id, array(self::STATUS_WAITLIST));
    }

    /**
     * @param int $event_id
     * @param array<int,string> $statuses
     * @return int
     */
    private static function count_registrations_by_status($event_id, array $statuses) {
        $event_id = (int) $event_id;
        if ($event_id <= 0 || empty($statuses)) {
            return 0;
        }

        $normalized = array();
        foreach ($statuses as $status) {
            $status = sanitize_key($status);
            if ($status !== '') {
                $normalized[] = $status;
            }
        }

        if (empty($normalized)) {
            return 0;
        }

        global $wpdb;
        $table = mj_member_get_event_registrations_table_name();
        $placeholders = implode(',', array_fill(0, count($normalized), '%s'));
        $params = array_merge(array($event_id), $normalized);

        $sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE event_id = %d AND statut IN ({$placeholders})",
            ...$params
        );

        $count = $wpdb->get_var($sql);
        return $count ? (int) $count : 0;
    }

    /**
     * @param int $event_id
     * @param int $limit
     * @return array<int,object>
     */
    private static function get_waitlist_entries($event_id, $limit = 1) {
        $event_id = (int) $event_id;
        if ($event_id <= 0) {
            return array();
        }

        global $wpdb;
        $table = mj_member_get_event_registrations_table_name();
        $limit = max(1, (int) $limit);

        $sql = $wpdb->prepare(
            "SELECT * FROM {$table} WHERE event_id = %d AND statut = %s ORDER BY created_at ASC, id ASC LIMIT {$limit}",
            $event_id,
            self::STATUS_WAITLIST
        );

        return $wpdb->get_results($sql);
    }

    /**
     * @param object $event
     * @param int $active_after
     * @return void
     */
    private static function maybe_trigger_capacity_notification($event, $active_after) {
        if (!$event || empty($event->id)) {
            return;
        }

        $capacity_total = isset($event->capacity_total) ? (int) $event->capacity_total : 0;
        $threshold = isset($event->capacity_notify_threshold) ? (int) $event->capacity_notify_threshold : 0;

        if ($capacity_total <= 0 || $threshold <= 0) {
            return;
        }

        $remaining = max(0, $capacity_total - (int) $active_after);
        if ($remaining > $threshold) {
            if (!empty($event->capacity_notified)) {
                MjEvents_CRUD::update((int) $event->id, array('capacity_notified' => 0));
                $event->capacity_notified = 0;
            }
            return;
        }

        if (!empty($event->capacity_notified)) {
            return;
        }

        $subject = 'Seuil de capacite atteint: ' . $event->title;
        $message_lines = array(
            'Bonjour,',
            '',
            'Le seuil de notification pour l\'evenement suivant est atteint:',
            'Evenement: ' . $event->title,
        );

        $event_dates = self::format_event_dates($event);
        if ($event_dates !== '') {
            $message_lines[] = 'Dates: ' . $event_dates;
        }

        $message_lines[] = 'Capacite totale: ' . $capacity_total;
        $message_lines[] = 'Inscriptions actives: ' . $active_after;
        $message_lines[] = 'Places restantes estimees: ' . max(0, $capacity_total - $active_after);
        if (!empty($event->capacity_waitlist)) {
            $message_lines[] = 'Limite liste d\'attente: ' . (int) $event->capacity_waitlist;
        }

        $message_lines[] = '';
        $message_lines[] = 'Pensee: verifier la liste d\'attente et preparer une communication au besoin.';
        if (!empty($event->id)) {
            $message_lines[] = 'Gestion: ' . add_query_arg(
                array('page' => 'mj_events', 'action' => 'edit', 'event' => (int) $event->id),
                admin_url('admin.php')
            );
        }
        $message_lines[] = '';
        $message_lines[] = '— MJ Member';

        $body = implode("\n", $message_lines);
        $headers = array('Content-Type: text/plain; charset=UTF-8');

        $recipients = array();
        $notify_email = get_option('mj_notify_email', '');
        if ($notify_email && is_email($notify_email)) {
            $recipients[] = $notify_email;
        }

        $admin_email = get_option('admin_email');
        if ($admin_email && is_email($admin_email)) {
            $recipients[] = $admin_email;
        }

        $recipients = array_values(array_unique(array_filter($recipients)));
        if (!empty($recipients)) {
            foreach ($recipients as $recipient) {
                wp_mail($recipient, $subject, $body, $headers);
            }
        }

        MjEvents_CRUD::update((int) $event->id, array('capacity_notified' => 1));
        $event->capacity_notified = 1;
    }

    /**
     * @param int $event_id
     * @return void
     */
    private static function sync_capacity_counters($event_id) {
        $event_id = (int) $event_id;
        if ($event_id <= 0) {
            return;
        }

        $event = MjEvents_CRUD::find($event_id);
        if (!$event) {
            return;
        }

        $capacity_total = isset($event->capacity_total) ? (int) $event->capacity_total : 0;
        if ($capacity_total <= 0) {
            if (!empty($event->capacity_notified)) {
                MjEvents_CRUD::update($event_id, array('capacity_notified' => 0));
            }
            return;
        }

        $active_count = self::count_active_registrations($event_id);
        $threshold = isset($event->capacity_notify_threshold) ? (int) $event->capacity_notify_threshold : 0;

        if ($threshold > 0) {
            $remaining = max(0, $capacity_total - $active_count);
            if ($remaining <= $threshold) {
                self::maybe_trigger_capacity_notification($event, $active_count);
            } elseif (!empty($event->capacity_notified)) {
                MjEvents_CRUD::update($event_id, array('capacity_notified' => 0));
            }
        } elseif (!empty($event->capacity_notified)) {
            MjEvents_CRUD::update($event_id, array('capacity_notified' => 0));
        }
    }

    /**
     * @param int $event_id
     * @return void
     */
    private static function maybe_promote_waitlist($event_id) {
        $event_id = (int) $event_id;
        if ($event_id <= 0) {
            return;
        }

        $event = MjEvents_CRUD::find($event_id);
        if (!$event) {
            return;
        }

        $capacity_total = isset($event->capacity_total) ? (int) $event->capacity_total : 0;
        if ($capacity_total <= 0) {
            return;
        }

        $active_count = self::count_active_registrations($event_id);
        if ($active_count >= $capacity_total) {
            return;
        }

        $waitlist_entries = self::get_waitlist_entries($event_id, 1);
        if (empty($waitlist_entries)) {
            return;
        }

        $candidate = $waitlist_entries[0];
        global $wpdb;
        $table = mj_member_get_event_registrations_table_name();

        $updated = $wpdb->update(
            $table,
            array('statut' => self::STATUS_PENDING),
            array('id' => (int) $candidate->id),
            array('%s'),
            array('%d')
        );

        if ($updated === false) {
            return;
        }

        $registration = self::get((int) $candidate->id);
        if (!$registration) {
            return;
        }

        $member = MjMembers_CRUD::getById((int) $registration->member_id);
        if (!$member) {
            return;
        }

        self::notify_registration_created(
            $registration,
            $event,
            $member,
            isset($registration->guardian_id) ? (int) $registration->guardian_id : null,
            array('promotion' => true)
        );

        self::maybe_trigger_capacity_notification($event, $active_count + 1);
    }

    /**
     * @param object|null $registration
     * @param object $event
     * @param object $member
     * @param int|null $guardian_id
     * @return void
     */
    private static function notify_registration_created($registration, $event, $member, $guardian_id, $context = array()) {
        if (!$registration) {
            return;
        }

        if (!is_array($context)) {
            $context = array();
        }

        $is_waitlist = !empty($context['is_waitlist']);
        $is_promotion = !empty($context['promotion']);

        $guardian = null;
        if ($guardian_id) {
            $guardian = MjMembers_CRUD::getById($guardian_id);
        }

        $animateur_members = array();
        if (class_exists('MjEventAnimateurs')) {
            $animateur_members = MjEventAnimateurs::get_members_by_event((int) $event->id);
        }
        if (empty($animateur_members) && !empty($event->animateur_id)) {
            $legacy_animateur = MjMembers_CRUD::getById((int) $event->animateur_id);
            if ($legacy_animateur) {
                $animateur_members[] = $legacy_animateur;
            }
        }

        if ($is_promotion) {
            $subject = 'Inscription confirmee (liste d\'attente): ' . $event->title;
        } elseif ($is_waitlist) {
            $subject = 'Nouvelle inscription (liste d\'attente): ' . $event->title;
        } else {
            $subject = 'Nouvelle inscription: ' . $event->title;
        }
        $event_dates = self::format_event_dates($event);
        $member_first = isset($member->first_name) ? $member->first_name : '';
        $member_last = isset($member->last_name) ? $member->last_name : '';
        $member_name = trim($member_first . ' ' . $member_last);
        if ($member_name === '') {
            $member_name = 'Membre #' . $member->id;
        }

        $message_lines = array(
            'Bonjour,',
            '',
            'Une nouvelle inscription a ete enregistree.',
            'Evenement: ' . $event->title,
            'Participant: ' . $member_name,
        );

        if ($event_dates !== '') {
            $message_lines[] = 'Dates: ' . $event_dates;
        }

        $status_label = isset(self::META_STATUS_LABELS[$registration->statut]) ? self::META_STATUS_LABELS[$registration->statut] : $registration->statut;
        $message_lines[] = 'Statut: ' . $status_label;
        $message_lines[] = 'Enregistre le: ' . wp_date('d/m/Y H:i', strtotime($registration->created_at));

        if ($is_waitlist) {
            $message_lines[] = '';
            $message_lines[] = 'Note: cette inscription est placee sur la liste d\'attente.';
        }

        if ($is_promotion) {
            $message_lines[] = '';
            $message_lines[] = 'Note: ce participant vient de quitter la liste d\'attente.';
        }

        if (!empty($registration->notes)) {
            $message_lines[] = '';
            $message_lines[] = 'Notes: ' . $registration->notes;
        }

        if (!empty($animateur_members)) {
            $message_lines[] = '';
            if (count($animateur_members) === 1) {
                $first_animateur = reset($animateur_members);
                if ($first_animateur) {
                    $message_lines[] = 'Animateur referent: ' . self::format_member_summary($first_animateur);
                }
            } else {
                $message_lines[] = 'Animateurs referents:';
                foreach ($animateur_members as $animateur_member) {
                    $message_lines[] = ' - ' . self::format_member_summary($animateur_member);
                }
            }
        }

        $message_lines[] = '';
        $message_lines[] = 'Bien a vous,';
        $message_lines[] = get_bloginfo('name');

        $body = implode("\n", $message_lines);
        $animateur_body = $body;
        if (!empty($event->id)) {
            $event_admin_url = add_query_arg(
                array(
                    'page' => 'mj_events',
                    'action' => 'edit',
                    'event' => (int) $event->id,
                ),
                admin_url('admin.php')
            );
            $animateur_body .= "\n\nGestion de l evenement: " . $event_admin_url;
        }
        $headers = array('Content-Type: text/plain; charset=UTF-8');
        $sent_addresses = array();

        $recipient = '';
        if ($guardian && !empty($guardian->email) && is_email($guardian->email)) {
            $recipient = $guardian->email;
        } elseif (!empty($member->email) && is_email($member->email)) {
            $recipient = $member->email;
        }

        if ($recipient !== '') {
            wp_mail($recipient, $subject, $body, $headers);
            $sent_addresses[strtolower($recipient)] = true;
        }

        $notify_email = get_option('mj_notify_email', '');
        if ($notify_email !== '' && is_email($notify_email)) {
            $normalized_notify = strtolower($notify_email);
            if (!isset($sent_addresses[$normalized_notify])) {
                wp_mail($notify_email, $subject, $body, $headers);
                $sent_addresses[$normalized_notify] = true;
            }
        }

        if (!empty($animateur_members)) {
            foreach ($animateur_members as $animateur_member) {
                if (empty($animateur_member->email) || !is_email($animateur_member->email)) {
                    continue;
                }
                $animateur_email_norm = strtolower($animateur_member->email);
                if (isset($sent_addresses[$animateur_email_norm])) {
                    continue;
                }
                wp_mail($animateur_member->email, $subject, $animateur_body, $headers);
                $sent_addresses[$animateur_email_norm] = true;
            }
        }
    }

    /**
     * @param object $event
     * @return string
     */
    private static function format_event_dates($event) {
        if (empty($event->date_debut) || empty($event->date_fin)) {
            return '';
        }

        $start = strtotime($event->date_debut);
        $end = strtotime($event->date_fin);
        if (!$start || !$end) {
            return '';
        }

        $start_str = wp_date('d/m/Y H:i', $start);
        $end_str = wp_date('d/m/Y H:i', $end);

        if (wp_date('d/m/Y', $start) === wp_date('d/m/Y', $end)) {
            return $start_str . ' - ' . wp_date('H:i', $end);
        }

        return $start_str . ' -> ' . $end_str;
    }

    private static function format_member_summary($member) {
        $first = isset($member->first_name) ? $member->first_name : '';
        $last = isset($member->last_name) ? $member->last_name : '';
        $name = trim($first . ' ' . $last);
        if ($name === '') {
            $name = 'Animateur #' . (isset($member->id) ? (int) $member->id : 0);
        }

        if (!empty($member->email) && is_email($member->email)) {
            $name .= ' (' . $member->email . ')';
        }

        return $name;
    }
}
