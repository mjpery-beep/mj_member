<?php

namespace Mj\Member\Classes\Crud;

use DateTime;
use Mj\Member\Classes\MjEventSchedule;
use Mj\Member\Classes\MjMail;
use Mj\Member\Classes\MjRoles;
use Mj\Member\Classes\Value\EventRegistrationData;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class MjEventRegistrations implements CrudRepositoryInterface {
    const TABLE = 'mj_event_registrations';
    /** @var array<string,mixed> */
    private static $last_creation_context = array();

    /** @var array<string,array<string,bool>> */
    private static $members_table_columns = array();

    /**
     * @return string
     */
    private static function table_name() {
        return mj_member_get_event_registrations_table_name();
    }

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
     * @param array<string,mixed> $args
     * @return array<int,EventRegistrationData>
     */
    public static function get_all(array $args = array()) {
        global $wpdb;
        $table = self::table_name();

        $defaults = array(
            'ids' => array(),
            'exclude_ids' => array(),
            'event_ids' => array(),
            'event_id' => 0,
            'member_ids' => array(),
            'member_id' => 0,
            'guardian_ids' => array(),
            'statuses' => array(),
            'payment_statuses' => array(),
            'search' => '',
            'orderby' => 'created_at',
            'order' => 'DESC',
            'limit' => 0,
            'offset' => 0,
        );
        $args = wp_parse_args($args, $defaults);

        if (!empty($args['event_id'])) {
            $args['event_ids'][] = (int) $args['event_id'];
        }
        if (!empty($args['member_id'])) {
            $args['member_ids'][] = (int) $args['member_id'];
        }

        $builder = CrudQueryBuilder::for_table($table);
        self::apply_common_filters($builder, $args);

        $allowed_orderby = array('created_at', 'updated_at', 'event_id', 'member_id', 'statut', 'payment_status', 'payment_recorded_at');
        $orderby = sanitize_key($args['orderby']);
        if (!in_array($orderby, $allowed_orderby, true)) {
            $orderby = 'created_at';
        }

        $order = strtoupper((string) $args['order']) === 'ASC' ? 'ASC' : 'DESC';

        $limit = (int) $args['limit'];
        $offset = max(0, (int) $args['offset']);
        list($sql, $params) = $builder->build_select('*', $orderby, $order, $limit, $offset);

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        $results = $wpdb->get_results($sql);
        if (!is_array($results) || empty($results)) {
            return array();
        }

        return self::hydrate_registrations($results);
    }

    /**
     * @param array<string,mixed> $args
     * @return int
     */
    public static function count(array $args = array()) {
        global $wpdb;
        $table = self::table_name();

        $defaults = array(
            'ids' => array(),
            'exclude_ids' => array(),
            'event_ids' => array(),
            'event_id' => 0,
            'member_ids' => array(),
            'member_id' => 0,
            'guardian_ids' => array(),
            'statuses' => array(),
            'payment_statuses' => array(),
            'search' => '',
        );
        $args = wp_parse_args($args, $defaults);

        if (!empty($args['event_id'])) {
            $args['event_ids'][] = (int) $args['event_id'];
        }
        if (!empty($args['member_id'])) {
            $args['member_ids'][] = (int) $args['member_id'];
        }

        $builder = CrudQueryBuilder::for_table($table);
        self::apply_common_filters($builder, $args);

        list($sql, $params) = $builder->build_count('*');

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        $count = $wpdb->get_var($sql);
        return $count ? (int) $count : 0;
    }

    /**
     * @param int $event_id
     * @return array<int,EventRegistrationData>
     */
    public static function get_by_event($event_id) {
        $event_id = (int) $event_id;
        if ($event_id <= 0) {
            return array();
        }

        return self::get_all(array(
            'event_ids' => array($event_id),
            'orderby' => 'created_at',
            'order' => 'DESC',
        ));
    }

    /**
     * @param CrudQueryBuilder $builder
     * @param array<string,mixed> $args
     * @return void
     */
    private static function apply_common_filters(CrudQueryBuilder $builder, array $args) {
        $builder
            ->where_in_int('id', (array) $args['ids'])
            ->where_not_in_int('id', (array) $args['exclude_ids'])
            ->where_in_int('event_id', (array) $args['event_ids'])
            ->where_in_int('member_id', (array) $args['member_ids'])
            ->where_in_int('guardian_id', (array) $args['guardian_ids'])
            ->where_in_strings('statut', (array) $args['statuses'], 'sanitize_key')
            ->where_in_strings('payment_status', (array) $args['payment_statuses'], 'sanitize_key');

        $search = isset($args['search']) ? trim((string) $args['search']) : '';
        if ($search !== '') {
            $builder->where_like_any(array('notes', 'payment_method', 'payment_reference'), $search);
        }
    }

    /**
     * @param array<int,object> $registrations
     * @return array<int,EventRegistrationData>
     */
    private static function hydrate_registrations(array $registrations) {
        if (empty($registrations)) {
            return array();
        }

        $normalized = array();
        $member_ids = array();
        $guardian_ids = array();

        foreach ($registrations as $registration_row) {
            if (!is_object($registration_row)) {
                $registration_row = (object) $registration_row;
            }

            $data = get_object_vars($registration_row);

            if (class_exists('MjEventAttendance')) {
                MjEventAttendance::get_table_name();
                $assignments = MjEventAttendance::get_registration_assignments($registration_row);
            } else {
                $assignments = array(
                    'mode' => 'all',
                    'occurrences' => array(),
                );
            }

            $data['occurrence_assignments'] = is_array($assignments)
                ? $assignments
                : array('mode' => 'all', 'occurrences' => array());

            $member_id = isset($data['member_id']) ? (int) $data['member_id'] : 0;
            if ($member_id > 0) {
                $member_ids[$member_id] = true;
            }

            $guardian_id = isset($data['guardian_id']) ? (int) $data['guardian_id'] : 0;
            if ($guardian_id > 0) {
                $guardian_ids[$guardian_id] = true;
            }

            $normalized[] = $data;
        }

        $members_index = array();
        $all_member_ids = array_keys($member_ids + $guardian_ids);
        if (!empty($all_member_ids)) {
            $members_table = MjMembers::getTableName(MjMembers::TABLE_NAME);
            $can_query_members = true;
            if (function_exists('mj_member_table_exists')) {
                $can_query_members = mj_member_table_exists($members_table);
            }

            if ($can_query_members) {
                global $wpdb;
                $placeholders = implode(',', array_fill(0, count($all_member_ids), '%d'));
                $member_fields = self::build_member_select_fields($members_table);
                $member_sql = "SELECT {$member_fields} FROM {$members_table} WHERE id IN ({$placeholders})";
                $params = array_merge(array($member_sql), $all_member_ids);
                $prepared_members = call_user_func_array(array($wpdb, 'prepare'), $params);
                $members = $wpdb->get_results($prepared_members);
                if (is_array($members)) {
                    foreach ($members as $member) {
                        $members_index[(int) $member->id] = get_object_vars($member);
                    }
                }
            }
        }

        foreach ($normalized as &$data) {
            $member_id = isset($data['member_id']) ? (int) $data['member_id'] : 0;
            $guardian_id = isset($data['guardian_id']) ? (int) $data['guardian_id'] : 0;

            $member = ($member_id > 0 && isset($members_index[$member_id])) ? $members_index[$member_id] : null;
            if ($member) {
                $data['first_name'] = isset($member['first_name']) ? $member['first_name'] : '';
                $data['last_name'] = isset($member['last_name']) ? $member['last_name'] : '';
                $data['nickname'] = isset($member['nickname']) ? $member['nickname'] : '';
                $data['role'] = isset($member['role']) ? $member['role'] : '';
                $data['birth_date'] = isset($member['birth_date']) ? $member['birth_date'] : null;
                $data['email'] = isset($member['email']) ? $member['email'] : '';
                $data['phone'] = isset($member['phone']) ? $member['phone'] : '';
                $data['sms_opt_in'] = !empty($member['sms_opt_in']);
                $data['is_autonomous'] = array_key_exists('is_autonomous', $member) ? $member['is_autonomous'] : null;

                if (empty($data['guardian_id']) && !empty($member['guardian_id'])) {
                    $candidate_guardian = (int) $member['guardian_id'];
                    if ($candidate_guardian > 0) {
                        $data['guardian_id'] = $candidate_guardian;
                        $guardian_id = $candidate_guardian;
                    }
                }
            } else {
                $data['first_name'] = isset($data['first_name']) ? $data['first_name'] : '';
                $data['last_name'] = isset($data['last_name']) ? $data['last_name'] : '';
                $data['nickname'] = isset($data['nickname']) ? $data['nickname'] : '';
                $data['role'] = isset($data['role']) ? $data['role'] : '';
                if (!array_key_exists('birth_date', $data)) {
                    $data['birth_date'] = null;
                }
                $data['email'] = isset($data['email']) ? $data['email'] : '';
                $data['phone'] = isset($data['phone']) ? $data['phone'] : '';
                if (!array_key_exists('sms_opt_in', $data)) {
                    $data['sms_opt_in'] = false;
                }
                if (!array_key_exists('is_autonomous', $data)) {
                    $data['is_autonomous'] = null;
                }
            }

            if (!isset($data['role']) || $data['role'] === null) {
                $data['role'] = '';
            }

            $guardian = ($guardian_id > 0 && isset($members_index[$guardian_id])) ? $members_index[$guardian_id] : null;
            if ($guardian) {
                $data['guardian_first_name'] = isset($guardian['first_name']) ? $guardian['first_name'] : '';
                $data['guardian_last_name'] = isset($guardian['last_name']) ? $guardian['last_name'] : '';
                $data['guardian_phone'] = isset($guardian['phone']) ? $guardian['phone'] : '';
                $data['guardian_email'] = isset($guardian['email']) ? $guardian['email'] : '';
                $data['guardian_sms_opt_in'] = !empty($guardian['sms_opt_in']);
            } else {
                $data['guardian_first_name'] = isset($data['guardian_first_name']) ? $data['guardian_first_name'] : '';
                $data['guardian_last_name'] = isset($data['guardian_last_name']) ? $data['guardian_last_name'] : '';
                $data['guardian_phone'] = isset($data['guardian_phone']) ? $data['guardian_phone'] : '';
                $data['guardian_email'] = isset($data['guardian_email']) ? $data['guardian_email'] : '';
                if (!array_key_exists('guardian_sms_opt_in', $data)) {
                    $data['guardian_sms_opt_in'] = 0;
                }
            }
        }
        unset($data);

        $hydrated = array();
        foreach ($normalized as $entry) {
            $hydrated[] = EventRegistrationData::fromArray($entry);
        }

        return $hydrated;
    }

    /**
     * @param int $registration_id
     * @return EventRegistrationData|null
     */
    public static function get($registration_id) {
        global $wpdb;
        $table = self::table_name();

        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", (int) $registration_id));
        $hydrated = self::hydrate_registrations($row ? array($row) : array());

        return isset($hydrated[0]) ? $hydrated[0] : null;
    }

    /**
     * @param int $event_id
     * @param int $member_id
     * @return EventRegistrationData|null
     */
    public static function get_existing($event_id, $member_id) {
        global $wpdb;
        $table = self::table_name();

        $sql = "SELECT * FROM {$table} WHERE event_id = %d AND member_id = %d ORDER BY created_at DESC LIMIT 1";
        $row = $wpdb->get_row($wpdb->prepare($sql, (int) $event_id, (int) $member_id));
        $hydrated = self::hydrate_registrations($row ? array($row) : array());

        return isset($hydrated[0]) ? $hydrated[0] : null;
    }

    /**
     * @param array<string,mixed> $data
     * @return int|WP_Error
     */
    public static function create($data) {
        self::$last_creation_context = array();

        if (!is_array($data)) {
            return new WP_Error('mj_event_registration_invalid_payload', 'Format de donnees invalide pour l\'inscription.');
        }

        $event_id = isset($data['event_id']) ? (int) $data['event_id'] : 0;
        $member_id = isset($data['member_id']) ? (int) $data['member_id'] : 0;

        if ($event_id <= 0 || $member_id <= 0) {
            return new WP_Error('mj_event_registration_invalid_ids', 'Evenement ou membre invalide.');
        }

        $send_notifications = true;
        if (array_key_exists('send_notifications', $data)) {
            $send_notifications = !empty($data['send_notifications']);
        } elseif (array_key_exists('notify', $data)) {
            $send_notifications = !empty($data['notify']);
        }

        $event = MjEvents::find($event_id);
        if (!$event) {
            return new WP_Error('mj_event_registration_missing_event', 'Evenement introuvable.');
        }

        $member = MjMembers::getById($member_id);
        if (!$member) {
            return new WP_Error('mj_event_registration_missing_member', 'Membre introuvable.');
        }

        $validation = self::validate_registration($event, $member, $event_id, $member_id, $data);
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

        $requires_validation = true;
        if (isset($event->requires_validation)) {
            $requires_validation = (int) $event->requires_validation !== 0;
        }

        if ($registration_status === self::STATUS_PENDING && !$requires_validation) {
            $registration_status = self::STATUS_CONFIRMED;
        }

        $guardian_id = isset($data['guardian_id']) ? (int) $data['guardian_id'] : 0;
        if ($guardian_id <= 0 && isset($member->guardian_id)) {
            $guardian_id = (int) $member->guardian_id;
        }
        if ($guardian_id <= 0) {
            $guardian_id = null;
        }

        $notes = isset($data['notes']) ? sanitize_textarea_field($data['notes']) : '';

        global $wpdb;
        $table = self::table_name();

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

        self::sync_capacity_counters($event_id);

        return $registration_id;
    }

    /**
     * @param int $registration_id
     * @param array<string,mixed> $data
     * @return true|WP_Error
     */
    public static function update($registration_id, $data) {
        $registration_id = (int) $registration_id;
        if ($registration_id <= 0) {
            return new WP_Error('mj_event_registration_invalid_id', 'Inscription invalide.');
        }

        if (!is_array($data)) {
            return new WP_Error('mj_event_registration_invalid_payload', 'Format de donnees invalide pour l\'inscription.');
        }

        $status_result = null;
        if (array_key_exists('statut', $data)) {
            $status_result = self::update_status($registration_id, $data['statut']);
            unset($data['statut']);
        } elseif (array_key_exists('status', $data)) {
            $status_result = self::update_status($registration_id, $data['status']);
            unset($data['status']);
        }

        if (is_wp_error($status_result)) {
            return $status_result;
        }

        $updates = array();
        $formats = array();

        if (array_key_exists('guardian_id', $data)) {
            $guardian_id = (int) $data['guardian_id'];
            $updates['guardian_id'] = $guardian_id > 0 ? $guardian_id : null;
            $formats[] = '%d';
        }

        if (array_key_exists('notes', $data)) {
            $notes = sanitize_textarea_field((string) $data['notes']);
            $updates['notes'] = ($notes !== '') ? $notes : null;
            $formats[] = '%s';
        }

        if (array_key_exists('payment_status', $data)) {
            $payment_status = sanitize_key((string) $data['payment_status']);
            $updates['payment_status'] = $payment_status !== '' ? $payment_status : null;
            $formats[] = '%s';
        }

        if (array_key_exists('payment_method', $data)) {
            $payment_method = sanitize_key((string) $data['payment_method']);
            $updates['payment_method'] = $payment_method !== '' ? $payment_method : null;
            $formats[] = '%s';
        }

        if (array_key_exists('payment_recorded_at', $data)) {
            $recorded_at = sanitize_text_field((string) $data['payment_recorded_at']);
            $updates['payment_recorded_at'] = $recorded_at !== '' ? $recorded_at : null;
            $formats[] = '%s';
        }

        if (array_key_exists('payment_recorded_by', $data)) {
            $recorded_by = (int) $data['payment_recorded_by'];
            $updates['payment_recorded_by'] = $recorded_by > 0 ? $recorded_by : null;
            $formats[] = '%d';
        }

        if (array_key_exists('payment_reference', $data)) {
            $reference = sanitize_text_field((string) $data['payment_reference']);
            $updates['payment_reference'] = $reference !== '' ? $reference : null;
            $formats[] = '%s';
        }

        if (empty($updates)) {
            return $status_result === null ? true : $status_result;
        }

        global $wpdb;
        $table = self::table_name();
        $result = $wpdb->update($table, $updates, array('id' => $registration_id), $formats, array('%d'));

        if ($result === false) {
            return new WP_Error('mj_event_registration_update_failed', 'Impossible de mettre a jour l\'inscription.');
        }

        return true;
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
        $table = self::table_name();
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
        $table = self::table_name();
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
        $table = self::table_name();
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
     * @param object|array $registration
     * @return array<string,mixed>
     */
    public static function build_occurrence_summary($registration) {
        $summary = array(
            'scope' => 'all',
            'count' => 0,
            'occurrences' => array(),
            'all_occurrences' => array(),
        );

        if (!$registration || !class_exists('MjEventAttendance')) {
            return $summary;
        }

        $assignments = MjEventAttendance::get_registration_assignments($registration);
        $scope = isset($assignments['mode']) ? sanitize_key((string) $assignments['mode']) : 'all';
        if ($scope === '') {
            $scope = 'all';
        }

        $event_id = 0;
        if (is_object($registration) && isset($registration->event_id)) {
            $event_id = (int) $registration->event_id;
        } elseif (is_array($registration) && isset($registration['event_id'])) {
            $event_id = (int) $registration['event_id'];
        }

        $occurrence_map = array();
        if ($event_id > 0 && class_exists('MjEvents') && class_exists('MjEventSchedule')) {
            static $event_occurrence_cache = array();
            if (isset($event_occurrence_cache[$event_id])) {
                $occurrence_map = $event_occurrence_cache[$event_id];
            } else {
                $event_object = MjEvents::find($event_id);
                if ($event_object) {
                    $occurrences = MjEventSchedule::get_occurrences($event_object, array(
                        'max' => 300,
                        'include_past' => true,
                    ));
                    if (!empty($occurrences)) {
                        foreach ($occurrences as $occurrence_entry) {
                            if (!is_array($occurrence_entry) || empty($occurrence_entry['start'])) {
                                continue;
                            }
                            $normalized_start = MjEventAttendance::normalize_occurrence($occurrence_entry['start']);
                            if ($normalized_start === '') {
                                continue;
                            }
                            $label = isset($occurrence_entry['label']) ? sanitize_text_field((string) $occurrence_entry['label']) : self::format_occurrence_label($normalized_start);
                            $end_value = '';
                            if (!empty($occurrence_entry['end'])) {
                                $end_value = sanitize_text_field((string) $occurrence_entry['end']);
                            }
                            $occurrence_map[$normalized_start] = array(
                                'start' => $normalized_start,
                                'end' => $end_value,
                                'label' => $label,
                            );
                        }
                    }
                }
                $event_occurrence_cache[$event_id] = $occurrence_map;
            }
        }

        $occurrence_list = array();
        $all_occurrence_list = array();
        if (!empty($occurrence_map)) {
            foreach ($occurrence_map as $normalized => $data) {
                $entry = is_array($data) ? $data : array('label' => $data, 'start' => $normalized, 'end' => '');
                $label = isset($entry['label']) && $entry['label'] !== '' ? $entry['label'] : self::format_occurrence_label($normalized);
                $end_value = isset($entry['end']) ? sanitize_text_field((string) $entry['end']) : '';
                $all_occurrence_list[] = array(
                    'start' => $normalized,
                    'end' => $end_value,
                    'label' => sanitize_text_field((string) $label),
                );
            }
        }

        if ($scope === 'custom' && !empty($assignments['occurrences']) && is_array($assignments['occurrences'])) {
            $unique = array();
            foreach ($assignments['occurrences'] as $occurrence_entry) {
                $normalized = MjEventAttendance::normalize_occurrence($occurrence_entry);
                if ($normalized === '') {
                    continue;
                }
                $unique[$normalized] = true;
            }
            if (!empty($unique)) {
                foreach (array_keys($unique) as $normalized) {
                    $entry = isset($occurrence_map[$normalized]) && is_array($occurrence_map[$normalized])
                        ? $occurrence_map[$normalized]
                        : null;
                    $label = ($entry && !empty($entry['label'])) ? $entry['label'] : self::format_occurrence_label($normalized);
                    $label = sanitize_text_field((string) $label);
                    $end_value = ($entry && !empty($entry['end'])) ? sanitize_text_field((string) $entry['end']) : '';
                    $occurrence_list[] = array(
                        'start' => $normalized,
                        'end' => $end_value,
                        'label' => $label,
                    );
                }
            }
        } elseif ($scope === 'all' && !empty($all_occurrence_list)) {
            $occurrence_list = $all_occurrence_list;
        }

        if (count($occurrence_list) > 50) {
            $occurrence_list = array_slice($occurrence_list, 0, 50);
        }

        $summary['scope'] = $scope;
        $summary['occurrences'] = $occurrence_list;
        $summary['all_occurrences'] = $all_occurrence_list;
        if ($scope === 'custom') {
            $summary['count'] = count($occurrence_list);
        } elseif ($scope === 'all') {
            $summary['count'] = !empty($occurrence_map) ? count($occurrence_map) : count($occurrence_list);
        }

        return $summary;
    }

    /**
     * @param string $value
     * @return string
     */
    private static function format_occurrence_label($value) {
        $timestamp = strtotime($value);
        if (!$timestamp) {
            return sanitize_text_field((string) $value);
        }

        $date_format = get_option('date_format', 'd/m/Y');
        $time_format = get_option('time_format', 'H:i');

        return wp_date($date_format . ' ' . $time_format, $timestamp);
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

        $occurrence_summary = self::build_occurrence_summary($registration);
        if (!is_array($occurrence_summary)) {
            $occurrence_summary = array(
                'scope' => 'all',
                'count' => 0,
                'occurrences' => array(),
            );
        }

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
            'occurrence_scope' => isset($occurrence_summary['scope']) ? sanitize_key((string) $occurrence_summary['scope']) : 'all',
            'occurrence_count' => isset($occurrence_summary['count']) ? (int) $occurrence_summary['count'] : 0,
            'occurrence_details' => self::sanitize_occurrence_details(isset($occurrence_summary['occurrences']) ? $occurrence_summary['occurrences'] : array()),
            'available_occurrences' => self::sanitize_occurrence_details(isset($occurrence_summary['all_occurrences']) ? $occurrence_summary['all_occurrences'] : array()),
        );
    }

    /**
     * @param array<int,array<string,string>> $occurrences
     * @return array<int,array<string,string>>
     */
    private static function sanitize_occurrence_details($occurrences) {
        if (!is_array($occurrences)) {
            return array();
        }

        $sanitized = array();
        foreach ($occurrences as $occurrence) {
            if (!is_array($occurrence)) {
                continue;
            }
            $start = isset($occurrence['start']) ? sanitize_text_field((string) $occurrence['start']) : '';
            $label = isset($occurrence['label']) ? sanitize_text_field((string) $occurrence['label']) : '';
            $end = isset($occurrence['end']) ? sanitize_text_field((string) $occurrence['end']) : '';
            if ($start === '' && $label === '') {
                continue;
            }
            $sanitized[] = array(
                'start' => $start,
                'end' => $end,
                'label' => $label !== '' ? $label : self::format_occurrence_label($start),
            );
        }

        return $sanitized;
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
        $table = self::table_name();
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
        $event_start = isset($event->date_debut) ? strtotime($event->date_debut) : false;
        $allow_late_registration = !empty($args['allow_late_registration']);
        $deadline = self::resolve_deadline($event);
        $has_custom_deadline = self::event_has_custom_deadline($event);

        if (!$allow_late_registration) {
            if ($deadline && $now <= $deadline) {
                $allow_late_registration = true;
            } elseif (!$has_custom_deadline && self::event_has_future_occurrence($event, $now)) {
                $allow_late_registration = true;
            }
        }

        if (!$allow_late_registration && $event_start && $now > $event_start) {
            return new WP_Error('mj_event_registration_event_started', 'L evenement a deja commence.');
        }

        if (!$allow_late_registration && $deadline && $now > $deadline) {
            return new WP_Error('mj_event_registration_deadline', 'La date limite est depassee.');
        }

        $allow_guardian_registration = 0;
        if (isset($event->allow_guardian_registration)) {
            $allow_guardian_registration = (int) $event->allow_guardian_registration;
        }
        $member_role = isset($member->role) ? sanitize_key($member->role) : '';
        if ($allow_guardian_registration !== 1 && MjRoles::isTuteur($member_role)) {
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
                return $start;
            }
        }

        return null;
    }

    /**
     * @param object $event
     * @return bool
     */
    private static function event_has_custom_deadline($event) {
        if (!isset($event->date_fin_inscription)) {
            return false;
        }

        $raw = trim((string) $event->date_fin_inscription);
        return $raw !== '' && $raw !== '0000-00-00 00:00:00';
    }

    /**
     * @param object $event
     * @param int|null $current_timestamp
     * @return bool
     */
    private static function event_has_future_occurrence($event, $current_timestamp = null) {
        if (!class_exists(MjEventSchedule::class)) {
            return false;
        }

        $reference = ($current_timestamp !== null) ? (int) $current_timestamp : current_time('timestamp');

        $occurrences = MjEventSchedule::get_occurrences(
            $event,
            array(
                'max' => 3,
                'include_past' => false,
            )
        );

        if (empty($occurrences) || !is_array($occurrences)) {
            return false;
        }

        foreach ($occurrences as $occurrence) {
            if (!is_array($occurrence)) {
                continue;
            }

            $timestamp = isset($occurrence['timestamp']) ? (int) $occurrence['timestamp'] : 0;
            if ($timestamp <= 0 && !empty($occurrence['start'])) {
                $timestamp = strtotime((string) $occurrence['start']);
            }

            if ($timestamp && $timestamp >= $reference) {
                return true;
            }
        }

        return false;
    }

    /**
     * Retourne l'état de la capacité pour un événement donné.
     *
     * @param int $event_id
     * @return array<string,int|null|bool>
     */
    public static function get_capacity_state($event_id) {
        $event_id = (int) $event_id;

        $default = array(
            'capacity_total' => null,
            'waitlist_total' => null,
            'active_count' => 0,
            'waitlist_count' => 0,
            'remaining' => null,
            'waitlist_remaining' => null,
            'waitlist_enabled' => false,
        );

        if ($event_id <= 0) {
            return $default;
        }

        $event = MjEvents::find($event_id);
        if (!$event) {
            return $default;
        }

        $capacity_total = isset($event->capacity_total) ? (int) $event->capacity_total : 0;
        $waitlist_limit = isset($event->capacity_waitlist) ? (int) $event->capacity_waitlist : 0;

        $active_count = self::count_active_registrations($event_id);
        $waitlist_count = self::count_waitlist_registrations($event_id);

        $remaining = $capacity_total > 0 ? max(0, $capacity_total - $active_count) : null;
        $waitlist_remaining = $waitlist_limit > 0 ? max(0, $waitlist_limit - $waitlist_count) : null;

        return array(
            'capacity_total' => $capacity_total > 0 ? $capacity_total : null,
            'waitlist_total' => $waitlist_limit > 0 ? $waitlist_limit : null,
            'active_count' => $active_count,
            'waitlist_count' => $waitlist_count,
            'remaining' => $remaining,
            'waitlist_remaining' => $waitlist_remaining,
            'waitlist_enabled' => $waitlist_limit > 0,
        );
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
        $table = self::table_name();
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
        $table = self::table_name();
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
                MjEvents::update((int) $event->id, array('capacity_notified' => 0));
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

        $remaining_slots = max(0, $capacity_total - $active_after);
        $message_lines[] = 'Capacite totale: ' . $capacity_total;
        $message_lines[] = 'Inscriptions actives: ' . $active_after;
        $message_lines[] = 'Places restantes estimees: ' . $remaining_slots;
        if (!empty($event->capacity_waitlist)) {
            $message_lines[] = 'Limite liste d\'attente: ' . (int) $event->capacity_waitlist;
        }

        $admin_url = '';
        if (!empty($event->id)) {
            $admin_url = add_query_arg(
                array('page' => 'mj_events', 'action' => 'edit', 'event' => (int) $event->id),
                admin_url('admin.php')
            );
        }

        $message_lines[] = '';
        $message_lines[] = 'Pensee: verifier la liste d\'attente et preparer une communication au besoin.';
        if ($admin_url !== '') {
            $message_lines[] = 'Gestion: ' . $admin_url;
        }
        $message_lines[] = '';
        $message_lines[] = '— MJ Member';

        $details_list = array();
        if ($event_dates !== '') {
            $details_list[] = '<li>Dates : ' . esc_html($event_dates) . '</li>';
        }
        $details_list[] = '<li>Capacite totale : ' . esc_html((string) $capacity_total) . '</li>';
        $details_list[] = '<li>Inscriptions confirmees : ' . esc_html((string) $active_after) . '</li>';
        $details_list[] = '<li>Places restantes estimees : ' . esc_html((string) $remaining_slots) . '</li>';
        if ($threshold > 0) {
            $details_list[] = '<li>Seuil d\'alerte : ' . esc_html((string) $threshold) . '</li>';
        }

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
            $placeholders = array(
                '{{event_title}}' => $event->title,
                '{{event_dates}}' => $event_dates,
                '{{capacity_total}}' => $capacity_total,
                '{{active_registrations}}' => $active_after,
                '{{remaining_slots}}' => $remaining_slots,
                '{{threshold}}' => $threshold,
                '{{event_admin_url}}' => $admin_url !== '' ? esc_url($admin_url) : '',
                '{{event_admin_link}}' => $admin_url !== '' ? '<p><a href="' . esc_url($admin_url) . '">' . esc_html__('Ouvrir la fiche événement', 'mj-member') . '</a></p>' : '',
                '{{event_details_list}}' => !empty($details_list) ? '<ul>' . implode('', $details_list) . '</ul>' : '',
            );

            MjMail::send_notification_to_emails('event_capacity_alert', $recipients, array(
                'placeholders' => $placeholders,
                'fallback_subject' => $subject,
                'fallback_body' => implode("\n", $message_lines),
                'content_type' => 'text/plain',
                'log_source' => 'event_capacity_alert',
            ));
        }

        MjEvents::update((int) $event->id, array('capacity_notified' => 1));
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

        $event = MjEvents::find($event_id);
        if (!$event) {
            return;
        }

        $capacity_total = isset($event->capacity_total) ? (int) $event->capacity_total : 0;
        if ($capacity_total <= 0) {
            if (!empty($event->capacity_notified)) {
                MjEvents::update($event_id, array('capacity_notified' => 0));
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
                MjEvents::update($event_id, array('capacity_notified' => 0));
            }
        } elseif (!empty($event->capacity_notified)) {
            MjEvents::update($event_id, array('capacity_notified' => 0));
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

        $event = MjEvents::find($event_id);
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
        $table = self::table_name();

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

        $member = MjMembers::getById((int) $registration->member_id);
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
     * @param EventRegistrationData|null $registration
     * @param object $event
     * @param object $member
     * @param int|null $guardian_id
     * @return void
     */
    private static function notify_registration_created(?EventRegistrationData $registration, $event, $member, $guardian_id, $context = array()) {
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
            $guardian = MjMembers::getById($guardian_id);
        }

        $animateur_members = array();
        if (class_exists('MjEventAnimateurs')) {
            $animateur_members = MjEventAnimateurs::get_members_by_event((int) $event->id);
        }
        if (empty($animateur_members) && !empty($event->animateur_id)) {
            $legacy_animateur = MjMembers::getById((int) $event->animateur_id);
            if ($legacy_animateur) {
                $animateur_members[] = $legacy_animateur;
            }
        }

        $volunteer_members = array();
        if (class_exists('MjEventVolunteers')) {
            $volunteer_members = MjEventVolunteers::get_members_by_event((int) $event->id);
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

        if (!empty($volunteer_members)) {
            $message_lines[] = '';
            if (count($volunteer_members) === 1) {
                $first_volunteer = reset($volunteer_members);
                if ($first_volunteer) {
                    $message_lines[] = 'Benevole referent: ' . self::format_member_summary($first_volunteer);
                }
            } else {
                $message_lines[] = 'Benevoles referents:';
                foreach ($volunteer_members as $volunteer_member) {
                    $message_lines[] = ' - ' . self::format_member_summary($volunteer_member);
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
        $sent_addresses = array();

        $recipient = '';
        if ($guardian && !empty($guardian->email) && is_email($guardian->email)) {
            $recipient = $guardian->email;
        } elseif (!empty($member->email) && is_email($member->email)) {
            $recipient = $member->email;
        }

        $placeholders = array(
            '{{event_title}}' => $event->title,
            '{{event_dates}}' => $event_dates,
            '{{participant_name}}' => $member_name,
            '{{registration_status}}' => $status_label,
            '{{registration_created_at}}' => wp_date('d/m/Y H:i', strtotime($registration->created_at)),
            '{{registration_notes}}' => !empty($registration->notes) ? $registration->notes : '',
            '{{is_waitlist}}' => $is_waitlist ? '1' : '0',
            '{{is_promotion}}' => $is_promotion ? '1' : '0',
            '{{animateurs_list}}' => self::format_animateurs_list($animateur_members),
            '{{admin_url}}' => !empty($event->id) ? add_query_arg(
                array(
                    'page' => 'mj_events',
                    'action' => 'edit',
                    'event' => (int) $event->id,
                ),
                admin_url('admin.php')
            ) : '',
        );

        if ($recipient !== '') {
            MjMail::send_notification_to_emails('event_registration_notice', array($recipient), array(
                'member' => $member,
                'context' => array(
                    'guardian' => $guardian,
                    'recipients' => array($recipient),
                    'include_guardian' => false,
                ),
                'placeholders' => array_merge($placeholders, array('{{audience}}' => 'member_or_guardian')),
                'fallback_subject' => $subject,
                'fallback_body' => $body,
                'content_type' => 'text/plain',
                'log_source' => 'event_registration_notice',
            ));
            $sent_addresses[strtolower($recipient)] = true;
        }

        $notify_email = get_option('mj_notify_email', '');
        if ($notify_email !== '' && is_email($notify_email)) {
            $normalized_notify = strtolower($notify_email);
            if (!isset($sent_addresses[$normalized_notify])) {
                MjMail::send_notification_to_emails('event_registration_notice', array($notify_email), array(
                    'member' => $member,
                    'context' => array('guardian' => $guardian),
                    'placeholders' => array_merge($placeholders, array('{{audience}}' => 'admin')),
                    'fallback_subject' => $subject,
                    'fallback_body' => $body,
                    'content_type' => 'text/plain',
                    'log_source' => 'event_registration_notice',
                ));
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

                $animateur_placeholders = array_merge($placeholders, array(
                    '{{audience}}' => \Mj\Member\Classes\MjRoles::ANIMATEUR,
                ));

                MjMail::send_notification_to_emails('event_registration_notice_animateur', array($animateur_member->email), array(
                    'member' => $member,
                    'context' => array(
                        'guardian' => $guardian,
                        'recipients' => array($animateur_member->email),
                        'include_guardian' => false,
                    ),
                    'placeholders' => $animateur_placeholders,
                    'fallback_subject' => $subject,
                    'fallback_body' => $animateur_body,
                    'content_type' => 'text/plain',
                    'log_source' => 'event_registration_notice',
                ));

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

    private static function format_animateurs_list($animateur_members) {
        if (empty($animateur_members) || !is_array($animateur_members)) {
            return '';
        }

        $items = array();
        foreach ($animateur_members as $animateur_member) {
            if (!is_object($animateur_member)) {
                continue;
            }
            $items[] = self::format_member_summary($animateur_member);
        }

        $items = array_values(array_filter(array_map('trim', $items)));
        return implode(', ', $items);
    }
}

\class_alias(__NAMESPACE__ . '\\MjEventRegistrations', 'MjEventRegistrations');
