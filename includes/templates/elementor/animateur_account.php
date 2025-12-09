<?php

use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

$pluginBasePath = Config::path();

if (!function_exists('mj_member_register_animateur_account_assets')) {
    function mj_member_register_animateur_account_assets() {
        $version = Config::version();
        $script_path = Config::path() . 'js/dist/animateur-account.js';
        $script_version = file_exists($script_path) ? (string) filemtime($script_path) : $version;

        wp_register_script(
            'mj-member-animateur-account',
            Config::url() . 'js/dist/animateur-account.js',
            array(), // No jQuery dependency - Preact is standalone
            $script_version,
            true
        );
    }
}

if (!function_exists('mj_member_get_attendance_status_labels')) {
    function mj_member_get_attendance_status_labels() {
        $labels = array(
            'none' => __('Non défini', 'mj-member'),
        );

        if (class_exists('MjEventAttendance')) {
            $labels[MjEventAttendance::STATUS_PRESENT] = __('Présent', 'mj-member');
            $labels[MjEventAttendance::STATUS_ABSENT] = __('Absent', 'mj-member');
            $labels[MjEventAttendance::STATUS_PENDING] = __('À confirmer', 'mj-member');
        } else {
            $labels['present'] = __('Présent', 'mj-member');
            $labels['absent'] = __('Absent', 'mj-member');
            $labels['pending'] = __('À confirmer', 'mj-member');
        }

        return $labels;
    }
}

if (!function_exists('mj_member_get_event_payment_status_labels')) {
    function mj_member_get_event_payment_status_labels() {
        return array(
            'unpaid' => __('À payer', 'mj-member'),
            'paid' => __('Payé', 'mj-member'),
        );
    }
}

if (!function_exists('mj_member_get_registration_status_labels')) {
    function mj_member_get_registration_status_labels() {
        if (class_exists('MjEventRegistrations')) {
            return MjEventRegistrations::get_status_labels();
        }

        return array(
            'en_attente' => __('En attente', 'mj-member'),
            'valide' => __('Validé', 'mj-member'),
            'annule' => __('Annulé', 'mj-member'),
            'liste_attente' => __("Liste d'attente", 'mj-member'),
        );
    }
}

if (!function_exists('mj_member_extract_initials')) {
    function mj_member_extract_initials($name) {
        $name = trim((string) $name);
        if ($name === '') {
            return '';
        }

        $parts = preg_split('/[\s\-]+/', $name);
        if (!$parts || !is_array($parts)) {
            $parts = array($name);
        }

        $initials = '';
        foreach ($parts as $part) {
            $part = trim((string) $part);
            if ($part === '') {
                continue;
            }

            if (function_exists('mb_substr')) {
                $initials .= mb_substr($part, 0, 1, 'UTF-8');
            } else {
                $initials .= substr($part, 0, 1);
            }

            $length = function_exists('mb_strlen') ? mb_strlen($initials, 'UTF-8') : strlen($initials);
            if ($length >= 2) {
                if ($length > 2) {
                    $initials = function_exists('mb_substr') ? mb_substr($initials, 0, 2, 'UTF-8') : substr($initials, 0, 2);
                }
                break;
            }
        }

        if ($initials === '') {
            $initials = function_exists('mb_substr') ? mb_substr($name, 0, 1, 'UTF-8') : substr($name, 0, 1);
        }

        if (function_exists('mb_strtoupper')) {
            return mb_strtoupper($initials, 'UTF-8');
        }

        return strtoupper($initials);
    }
}

if (!function_exists('mj_member_get_animateur_participant_avatar')) {
    function mj_member_get_animateur_participant_avatar($member_row, $registration, $display_name, $default_avatar, $wp_user = null) {
        $avatar_url = '';
        $avatar_id = 0;

        if ($member_row && !empty($member_row->photo_id)) {
            $photo_id = (int) $member_row->photo_id;
            $image = wp_get_attachment_image_src($photo_id, 'thumbnail');
            if ($image) {
                $avatar_url = esc_url_raw($image[0]);
                $avatar_id = $photo_id;
            }
        }

        if ($avatar_url === '' && is_array($default_avatar) && !empty($default_avatar['url'])) {
            $avatar_url = esc_url_raw((string) $default_avatar['url']);
            if (!empty($default_avatar['id'])) {
                $avatar_id = (int) $default_avatar['id'];
            }
        }

        if ($avatar_url === '' && $wp_user instanceof WP_User) {
            $avatar_url = esc_url_raw(get_avatar_url($wp_user->ID, array('size' => 96)));
        } elseif ($avatar_url === '' && $registration && !empty($registration->email)) {
            $avatar_url = esc_url_raw(get_avatar_url($registration->email, array('size' => 96)));
        }

        $initial_source = trim((string) $display_name);
        if ($initial_source === '' && $registration) {
            $registration_name = trim(sprintf(
                '%s %s',
                isset($registration->first_name) ? (string) $registration->first_name : '',
                isset($registration->last_name) ? (string) $registration->last_name : ''
            ));
            if ($registration_name !== '') {
                $initial_source = $registration_name;
            }
        }
        if ($initial_source === '' && $member_row) {
            $member_name = trim(sprintf(
                '%s %s',
                isset($member_row->first_name) ? (string) $member_row->first_name : '',
                isset($member_row->last_name) ? (string) $member_row->last_name : ''
            ));
            if ($member_name !== '') {
                $initial_source = $member_name;
            }
        }

        $initials = mj_member_extract_initials($initial_source);
        $alt_name = trim((string) $display_name);
        if ($alt_name === '' && $initial_source !== '') {
            $alt_name = $initial_source;
        }
        $alt = $alt_name !== '' ? sprintf(__('Photo de %s', 'mj-member'), $alt_name) : __('Photo du membre', 'mj-member');

        return array(
            'url' => $avatar_url,
            'id' => $avatar_id,
            'alt' => sanitize_text_field($alt),
            'initials' => $initials,
        );
    }
}

if (!function_exists('mj_member_get_animateur_visible_registration_statuses')) {
    function mj_member_get_animateur_visible_registration_statuses() {
        $defaults = array('valide', 'en_attente');

        if (class_exists('MjEventRegistrations')) {
            $defaults = array(
                MjEventRegistrations::STATUS_CONFIRMED,
                MjEventRegistrations::STATUS_PENDING,
            );
        }

        /**
         * Permet de filtrer les statuts d'inscription visibles dans le tableau animateur.
         *
         * @param array $defaults Liste des statuts conservés.
         */
        $visible = apply_filters('mj_member_animateur_visible_registration_statuses', $defaults);
        if (!is_array($visible)) {
            return $defaults;
        }

        $sanitized = array();
        foreach ($visible as $status) {
            $key = sanitize_key((string) $status);
            if ($key !== '') {
                $sanitized[] = $key;
            }
        }

        return !empty($sanitized) ? array_values(array_unique($sanitized)) : $defaults;
    }
}

if (!function_exists('mj_member_get_current_animateur_member')) {
    function mj_member_get_current_animateur_member() {
        if (!function_exists('mj_member_get_current_member')) {
            return null;
        }

        $member = mj_member_get_current_member();
        if (!$member || !is_object($member)) {
            return null;
        }

        $role = isset($member->role) ? sanitize_key((string) $member->role) : '';
        if (!class_exists('MjMembers_CRUD')) {
            return null;
        }

        return ($role === MjMembers_CRUD::ROLE_ANIMATEUR) ? $member : null;
    }
}

if (!function_exists('mj_member_prepare_animateur_event_data')) {
    function mj_member_prepare_animateur_event_data($event, $member_id, $args = array()) {
        if (!$event || !is_object($event)) {
            return array();
        }

        $event_id = isset($event->id) ? (int) $event->id : 0;
        if ($event_id <= 0) {
            return array();
        }

        $member_id = (int) $member_id;

        $member_assigned = true;
        if (class_exists('MjEventAnimateurs')) {
            $member_assigned = MjEventAnimateurs::member_is_assigned($event_id, (int) $member_id);
        }

        static $type_labels = null;
        static $status_labels = null;
        static $location_cache = array();
        static $member_role_labels = null;

        if ($type_labels === null && method_exists('MjEvents_CRUD', 'get_type_labels')) {
            $type_labels = MjEvents_CRUD::get_type_labels();
        }

        if ($status_labels === null && method_exists('MjEvents_CRUD', 'get_status_labels')) {
            $status_labels = MjEvents_CRUD::get_status_labels();
        }

        if ($member_role_labels === null && class_exists('MjMembers_CRUD')) {
            $member_role_labels = MjMembers_CRUD::getRoleLabels();
        }

        $can_edit_members = function_exists('current_user_can') ? current_user_can(Config::capability()) : false;

        $defaults = array(
            'include_past_occurrences' => true,
            'occurrence_limit' => 50,
        );
        $args = wp_parse_args($args, $defaults);

        $now_timestamp = current_time('timestamp');
        $today_date = wp_date('Y-m-d', $now_timestamp);

        $occurrence_limit = max(1, (int) $args['occurrence_limit']);
        $include_past = !empty($args['include_past_occurrences']);

        $occurrences = array();
        $occurrences_raw = array();

        if (class_exists('MjEventSchedule')) {
            $occurrence_args = array(
                'max' => $occurrence_limit,
                'include_past' => $include_past,
            );
            $occurrences_raw = MjEventSchedule::get_occurrences($event, $occurrence_args);
        }

        if (empty($occurrences_raw)) {
            $start = !empty($event->date_debut) ? (string) $event->date_debut : '';
            $end = !empty($event->date_fin) ? (string) $event->date_fin : $start;

            if ($start !== '') {
                $timestamp = strtotime($start);
                $label_format = get_option('date_format', 'd/m/Y') . ' ' . get_option('time_format', 'H:i');
                $label = $timestamp ? wp_date($label_format, $timestamp) : $start;
                $occurrences_raw[] = array(
                    'start' => $start,
                    'end' => $end,
                    'label' => $label,
                    'timestamp' => $timestamp ?: 0,
                    'is_past' => $timestamp ? ($timestamp < current_time('timestamp')) : false,
                );
            }
        }

        $attendance_map = class_exists('MjEventAttendance') ? MjEventAttendance::get_map($event_id) : array();

        $occurrence_lookup = array();
        foreach ($occurrences_raw as $raw) {
            if (!is_array($raw)) {
                continue;
            }

            $start = isset($raw['start']) ? (string) $raw['start'] : '';
            if ($start === '') {
                continue;
            }

            $timestamp = isset($raw['timestamp']) ? (int) $raw['timestamp'] : strtotime($start);
            if ($timestamp === false) {
                $timestamp = 0;
            }

            $occurrence_lookup[$start] = count($occurrences);
            $occurrences[] = array(
                'start' => $start,
                'end' => isset($raw['end']) ? (string) $raw['end'] : $start,
                'label' => isset($raw['label']) ? sanitize_text_field((string) $raw['label']) : $start,
                'timestamp' => $timestamp ?: 0,
                'isPast' => !empty($raw['is_past']) || ($timestamp !== 0 && $timestamp < $now_timestamp),
                'isToday' => $timestamp ? (wp_date('Y-m-d', $timestamp) === $today_date) : false,
                'isNext' => false,
                'counts' => array(
                    'present' => 0,
                    'absent' => 0,
                    'pending' => 0,
                ),
            );
        }

        if (!empty($attendance_map)) {
            foreach ($attendance_map as $occurrence_start => $entries) {
                $occurrence_start = (string) $occurrence_start;
                if ($occurrence_start === '') {
                    continue;
                }

                if (!isset($occurrence_lookup[$occurrence_start])) {
                    $timestamp = strtotime($occurrence_start);
                    $label_format = get_option('date_format', 'd/m/Y') . ' ' . get_option('time_format', 'H:i');
                    $label = $timestamp ? wp_date($label_format, $timestamp) : $occurrence_start;

                    $occurrence_lookup[$occurrence_start] = count($occurrences);
                    $occurrences[] = array(
                        'start' => $occurrence_start,
                        'end' => $occurrence_start,
                        'label' => sanitize_text_field($label),
                        'timestamp' => $timestamp ?: 0,
                        'isPast' => $timestamp ? ($timestamp < $now_timestamp) : true,
                        'isToday' => $timestamp ? (wp_date('Y-m-d', $timestamp) === $today_date) : false,
                        'isNext' => false,
                        'counts' => array(
                            'present' => 0,
                            'absent' => 0,
                            'pending' => 0,
                        ),
                    );
                }
            }
        }

        if (!empty($occurrences)) {
            usort(
                $occurrences,
                static function ($a, $b) {
                    return (int) $a['timestamp'] <=> (int) $b['timestamp'];
                }
            );
        }

        $occurrence_lookup = array();
        foreach ($occurrences as $index => $occurrence) {
            $occurrence_lookup[$occurrence['start']] = $index;
        }

        $occurrence_participant_totals = array();
        foreach ($occurrence_lookup as $occurrence_start => $idx) {
            $occurrence_participant_totals[$occurrence_start] = 0;
        }

        $registrations = class_exists('MjEventRegistrations') ? MjEventRegistrations::get_by_event($event_id) : array();
        $registration_labels = mj_member_get_registration_status_labels();

        static $default_avatar_cache = null;
        if ($default_avatar_cache === null) {
            $default_avatar_cache = array(
                'id' => 0,
                'url' => '',
            );

            $default_id = (int) get_option('mj_login_default_avatar_id', 0);
            if ($default_id > 0) {
                $image = wp_get_attachment_image_src($default_id, 'thumbnail');
                if ($image) {
                    $default_avatar_cache['id'] = $default_id;
                    $default_avatar_cache['url'] = esc_url_raw($image[0]);
                }
            }
        }

        $participants = array();
        $participant_index = array();
        $participant_priority = array();

        $visible_statuses = mj_member_get_animateur_visible_registration_statuses();
        $status_priority = array();
        $priority_cursor = count($visible_statuses);
        foreach ($visible_statuses as $visible_status) {
            $key = sanitize_key((string) $visible_status);
            if ($key === '') {
                continue;
            }
            if (!isset($status_priority[$key])) {
                $status_priority[$key] = $priority_cursor;
                $priority_cursor--;
            }
        }

        static $member_cache = array();
        $participant_scope_lookup = array();
        $payments_available = class_exists('MjPayments');
        $price_value = isset($event->prix) ? (float) $event->prix : 0.0;
        $blocked_payment_statuses = array('annule', 'liste_attente', 'cancelled', 'waitlist');
        if (class_exists('MjEventRegistrations')) {
            $blocked_payment_statuses[] = sanitize_key((string) MjEventRegistrations::STATUS_CANCELLED);
            $blocked_payment_statuses[] = sanitize_key((string) MjEventRegistrations::STATUS_WAITLIST);
        }
        $blocked_payment_statuses = array_values(array_unique(array_filter($blocked_payment_statuses)));

        foreach ($registrations as $registration) {
            if (!is_object($registration)) {
                continue;
            }

            $member_id = isset($registration->member_id) ? (int) $registration->member_id : 0;
            if ($member_id <= 0) {
                continue;
            }

            $member_row = null;
            if (class_exists('MjMembers_CRUD')) {
                if (!isset($member_cache[$member_id])) {
                    $member_cache[$member_id] = MjMembers_CRUD::getById($member_id);
                }
                $member_row = $member_cache[$member_id];
            }

            $wp_user = null;
            if ($member_row && !empty($member_row->wp_user_id) && function_exists('get_user_by')) {
                $wp_user = get_user_by('id', (int) $member_row->wp_user_id);
            }

            $full_name = trim(sprintf(
                '%s %s',
                isset($registration->first_name) ? (string) $registration->first_name : '',
                isset($registration->last_name) ? (string) $registration->last_name : ''
            ));

            if ($full_name === '' && !empty($registration->nickname)) {
                $full_name = (string) $registration->nickname;
            }

            if ($full_name === '' && $member_row) {
                $candidate = trim(sprintf('%s %s', (string) ($member_row->first_name ?? ''), (string) ($member_row->last_name ?? '')));
                if ($candidate !== '') {
                    $full_name = $candidate;
                } elseif (!empty($member_row->nickname)) {
                    $full_name = (string) $member_row->nickname;
                } elseif (!empty($member_row->email)) {
                    $full_name = sanitize_email((string) $member_row->email);
                }
            }

            if ($full_name === '' && $wp_user && !empty($wp_user->display_name)) {
                $full_name = sanitize_text_field((string) $wp_user->display_name);
            }

            if ($full_name === '' && !empty($registration->email)) {
                $full_name = sanitize_email((string) $registration->email);
            }

            if ($full_name === '') {
                $full_name = sprintf(__('Membre #%d', 'mj-member'), $member_id);
            }

            $registration_status = isset($registration->statut) ? sanitize_key((string) $registration->statut) : '';
            $registration_label = isset($registration_labels[$registration_status]) ? $registration_labels[$registration_status] : $registration_status;

            $priority_value = isset($status_priority[$registration_status]) ? (int) $status_priority[$registration_status] : 0;
            if ($priority_value <= 0) {
                continue;
            }

            $phone = isset($registration->phone) ? sanitize_text_field((string) $registration->phone) : '';
            $sms_opt_in = !empty($registration->sms_opt_in);

            $guardian_name = '';
            if (!empty($registration->guardian_first_name) || !empty($registration->guardian_last_name)) {
                $guardian_name = trim(sprintf(
                    '%s %s',
                    (string) ($registration->guardian_first_name ?? ''),
                    (string) ($registration->guardian_last_name ?? '')
                ));
            }
            $guardian_phone = isset($registration->guardian_phone) ? sanitize_text_field((string) $registration->guardian_phone) : '';
            $guardian_sms_opt_in = !empty($registration->guardian_sms_opt_in);

            $payment_snapshot = array(
                'status' => 'unpaid',
                'status_label' => mj_member_get_event_payment_status_labels()['unpaid'],
                'method' => '',
                'recorded_at' => '',
                'recorded_at_label' => '',
                'recorded_by' => array('id' => 0, 'name' => ''),
            );

            if (class_exists('MjEventRegistrations') && method_exists('MjEventRegistrations', 'build_payment_snapshot')) {
                $snapshot = MjEventRegistrations::build_payment_snapshot($registration);
                if (is_array($snapshot)) {
                    $payment_snapshot = wp_parse_args($snapshot, $payment_snapshot);
                }
            }

            $default_avatar = apply_filters('mj_member_animateur_dashboard_default_avatar', $default_avatar_cache, $event, $member_id);
            if (!is_array($default_avatar)) {
                $default_avatar = $default_avatar_cache;
            }

            $avatar = mj_member_get_animateur_participant_avatar($member_row, $registration, $full_name, $default_avatar, $wp_user);

            $role_key = '';
            if ($member_row && isset($member_row->role)) {
                $role_key = sanitize_key((string) $member_row->role);
            } elseif (!empty($registration->role)) {
                $role_key = sanitize_key((string) $registration->role);
            }

            $role_label = ($role_key !== '' && is_array($member_role_labels) && isset($member_role_labels[$role_key]))
                ? $member_role_labels[$role_key]
                : ($role_key !== '' ? ucfirst($role_key) : '');

            $city = isset($registration->city) ? sanitize_text_field((string) $registration->city) : '';

            $age_years = null;
            $birth_reference = isset($registration->birth_date) ? (string) $registration->birth_date : '';
            if ($birth_reference !== '' && $birth_reference !== '0000-00-00') {
                try {
                    $birth_date_obj = new DateTime($birth_reference);
                    $today_reference = new DateTime('today');
                    $age_years = (int) $birth_date_obj->diff($today_reference)->y;
                } catch (Exception $exception) {
                    $age_years = null;
                }
            }

            $member_edit_url = '';
            if ($can_edit_members && $member_id > 0) {
                $member_edit_url = esc_url_raw(
                    add_query_arg(
                        array(
                            'page' => 'mj_members',
                            'action' => 'edit',
                            'id' => $member_id,
                        ),
                        admin_url('admin.php')
                    )
                );
            }

            $guardian_email = isset($registration->guardian_email) ? sanitize_email((string) $registration->guardian_email) : '';

            $scope_mode = 'all';
            $scope_occurrences = array();
            if (isset($registration->occurrence_assignments) && is_array($registration->occurrence_assignments)) {
                $candidate_scope = $registration->occurrence_assignments;
                $candidate_mode = isset($candidate_scope['mode']) ? sanitize_key((string) $candidate_scope['mode']) : '';
                if ($candidate_mode === 'custom' && !empty($candidate_scope['occurrences']) && is_array($candidate_scope['occurrences'])) {
                    $valid_scope = array();
                    foreach ($candidate_scope['occurrences'] as $occurrence_key) {
                        $occurrence_key = (string) $occurrence_key;
                        if ($occurrence_key === '') {
                            continue;
                        }
                        if (!isset($occurrence_lookup[$occurrence_key])) {
                            continue;
                        }
                        $valid_scope[$occurrence_key] = true;
                    }
                    if (!empty($valid_scope)) {
                        $scope_mode = 'custom';
                        $scope_occurrences = array_values(array_keys($valid_scope));
                    }
                }
            }

            $can_generate_payment_link = false;
            if ($payments_available && $price_value > 0) {
                $can_generate_payment_link = !in_array($registration_status, $blocked_payment_statuses, true);
            }

            $participant = array(
                'memberId' => $member_id,
                'registrationId' => isset($registration->id) ? (int) $registration->id : 0,
                'fullName' => $full_name,
                'email' => isset($registration->email) ? sanitize_email((string) $registration->email) : '',
                'phone' => $phone,
                'smsAllowed' => $sms_opt_in ? 1 : 0,
                'registrationStatus' => $registration_status,
                'registrationStatusLabel' => is_string($registration_label) ? $registration_label : '',
                'role' => $role_key,
                'roleLabel' => $role_label,
                'age' => ($age_years !== null && $age_years >= 0) ? (int) $age_years : null,
                'city' => $city,
                'adminEditUrl' => $member_edit_url,
                'attendance' => array(),
                'payment' => $payment_snapshot,
                'avatar' => $avatar,
                'guardian' => array(
                    'id' => isset($registration->guardian_id) ? (int) $registration->guardian_id : 0,
                    'name' => $guardian_name,
                    'phone' => $guardian_phone,
                    'email' => $guardian_email,
                    'smsAllowed' => $guardian_sms_opt_in ? 1 : 0,
                ),
                'occurrenceScope' => array(
                    'mode' => $scope_mode,
                    'occurrences' => $scope_occurrences,
                ),
                'canGeneratePaymentLink' => $can_generate_payment_link ? 1 : 0,
            );

            if (isset($participant_index[$member_id])) {
                if ($priority_value > $participant_priority[$member_id]) {
                    $index = (int) $participant_index[$member_id];
                    $participant_priority[$member_id] = $priority_value;
                    $participants[$index] = $participant;
                    $participant_scope_lookup[$member_id] = $participant['occurrenceScope'];
                }
                continue;
            }

            $participant_priority[$member_id] = $priority_value;
            $participants[] = $participant;
            $participant_index[$member_id] = count($participants) - 1;
            $participant_scope_lookup[$member_id] = $participant['occurrenceScope'];
        }

        if (!empty($occurrence_participant_totals)) {
            foreach ($participants as $participant_entry) {
                $scope = isset($participant_entry['occurrenceScope']) && is_array($participant_entry['occurrenceScope'])
                    ? $participant_entry['occurrenceScope']
                    : array('mode' => 'all', 'occurrences' => array());

                $scope_mode = isset($scope['mode']) ? sanitize_key((string) $scope['mode']) : 'all';
                if ($scope_mode === 'custom') {
                    if (!empty($scope['occurrences']) && is_array($scope['occurrences'])) {
                        foreach ($scope['occurrences'] as $scope_occurrence) {
                            $scope_occurrence = (string) $scope_occurrence;
                            if ($scope_occurrence === '') {
                                continue;
                            }
                            if (!isset($occurrence_participant_totals[$scope_occurrence])) {
                                continue;
                            }
                            $occurrence_participant_totals[$scope_occurrence]++;
                        }
                    }
                } else {
                    foreach ($occurrence_participant_totals as $occurrence_key => $current_total) {
                        $occurrence_participant_totals[$occurrence_key] = $current_total + 1;
                    }
                }
            }
        }

        if (!empty($attendance_map) && !empty($participants)) {
            foreach ($attendance_map as $occurrence_start => $entries) {
                $occurrence_start = (string) $occurrence_start;
                if ($occurrence_start === '') {
                    continue;
                }

                $occurrence_index = isset($occurrence_lookup[$occurrence_start]) ? (int) $occurrence_lookup[$occurrence_start] : null;

                foreach ($entries as $member_id => $entry) {
                    $member_id = (int) $member_id;
                    if (!isset($participant_index[$member_id])) {
                        continue;
                    }

                    $scope_for_member = isset($participant_scope_lookup[$member_id]) ? $participant_scope_lookup[$member_id] : array('mode' => 'all', 'occurrences' => array());
                    if (isset($scope_for_member['mode']) && $scope_for_member['mode'] === 'custom') {
                        $allowed_occurrences = isset($scope_for_member['occurrences']) && is_array($scope_for_member['occurrences']) ? $scope_for_member['occurrences'] : array();
                        if (!in_array($occurrence_start, $allowed_occurrences, true)) {
                            continue;
                        }
                    }

                    $normalized_status = '';
                    if (class_exists('MjEventAttendance')) {
                        $normalized_status = MjEventAttendance::normalize_status(isset($entry['status']) ? $entry['status'] : '');
                    } else {
                        $normalized_status = sanitize_key(isset($entry['status']) ? $entry['status'] : '');
                    }
                    if ($normalized_status === '') {
                        $normalized_status = 'pending';
                    }

                    $participants[$participant_index[$member_id]]['attendance'][$occurrence_start] = $normalized_status;

                    if ($occurrence_index !== null && isset($occurrences[$occurrence_index]['counts'])) {
                        if (!isset($occurrences[$occurrence_index]['counts'][$normalized_status])) {
                            $occurrences[$occurrence_index]['counts'][$normalized_status] = 0;
                        }
                        $occurrences[$occurrence_index]['counts'][$normalized_status]++;
                    }
                }
            }
        }

        if (!empty($participants)) {
            usort(
                $participants,
                static function ($a, $b) {
                    return strcmp(strtolower((string) $a['fullName']), strtolower((string) $b['fullName']));
                }
            );
        }

        $participants_count = count($participants);

        $occurrence_status_labels = mj_member_get_attendance_status_labels();
        if (!empty($occurrences)) {
            foreach ($occurrences as $index => $occurrence) {
                $present_count = isset($occurrence['counts']['present']) ? (int) $occurrence['counts']['present'] : 0;
                $absent_count = isset($occurrence['counts']['absent']) ? (int) $occurrence['counts']['absent'] : 0;
                $pending_count = isset($occurrence['counts']['pending']) ? (int) $occurrence['counts']['pending'] : 0;

                $occurrence_key = isset($occurrence['start']) ? (string) $occurrence['start'] : '';
                $assigned_total = ($occurrence_key !== '' && isset($occurrence_participant_totals[$occurrence_key]))
                    ? (int) $occurrence_participant_totals[$occurrence_key]
                    : $participants_count;

                $recomputed_pending = $assigned_total - $present_count - $absent_count;
                if ($recomputed_pending < 0) {
                    $recomputed_pending = 0;
                }

                if ($recomputed_pending > 0 || $pending_count < 0) {
                    $occurrences[$index]['counts']['pending'] = $recomputed_pending;
                    $pending_count = $recomputed_pending;
                }

                $summary_parts = array();
                foreach (array('present', 'absent', 'pending') as $status_key) {
                    $count_value = 0;
                    if ($status_key === 'present') {
                        $count_value = $present_count;
                    } elseif ($status_key === 'absent') {
                        $count_value = $absent_count;
                    } else {
                        $count_value = $pending_count;
                    }

                    if ($count_value <= 0) {
                        continue;
                    }

                    $label = isset($occurrence_status_labels[$status_key]) ? $occurrence_status_labels[$status_key] : ucfirst($status_key);
                    $summary_parts[] = sprintf('%s : %d', $label, $count_value);
                }

                $occurrences[$index]['summary'] = !empty($summary_parts)
                    ? implode(' • ', $summary_parts)
                    : __('Aucun pointage', 'mj-member');
            }
        }

        $location_label = '';
        $location_address = '';
        $location_cover_url = '';
        if (!empty($event->location_id) && class_exists('MjEventLocations')) {
            $location_id = (int) $event->location_id;
            if ($location_id > 0) {
                if (!isset($location_cache[$location_id])) {
                    $location_cache[$location_id] = MjEventLocations::find($location_id);
                }

                $location_row = $location_cache[$location_id];
                if ($location_row) {
                    $location_name = isset($location_row->name) ? sanitize_text_field((string) $location_row->name) : '';
                    $location_city = isset($location_row->city) ? sanitize_text_field((string) $location_row->city) : '';

                    if ($location_name !== '') {
                        $location_label = $location_name;
                        if ($location_city !== '') {
                            $location_label .= ' (' . $location_city . ')';
                        }
                    } elseif ($location_city !== '') {
                        $location_label = $location_city;
                    }

                    $location_address_raw = MjEventLocations::format_address($location_row);
                    if (!empty($location_address_raw)) {
                        $location_address = sanitize_text_field($location_address_raw);
                    }

                    if (!empty($location_row->cover_id)) {
                        $location_cover_id = (int) $location_row->cover_id;
                        if ($location_cover_id > 0) {
                            $cover_candidate = wp_get_attachment_image_src($location_cover_id, 'thumbnail');
                            if (!empty($cover_candidate[0])) {
                                $location_cover_url = esc_url_raw($cover_candidate[0]);
                            }
                        }
                    }
                }
            }
        }

        $article_id = isset($event->article_id) ? (int) $event->article_id : 0;
        $article_permalink = '';
        $article_cover_url = '';
        $article_cover_thumb_url = '';

        if ($article_id > 0) {
            $article_status = get_post_status($article_id);
            if ($article_status && $article_status !== 'trash') {
                $article_permalink_candidate = get_permalink($article_id);
                if (!empty($article_permalink_candidate)) {
                    $article_permalink = esc_url_raw($article_permalink_candidate);
                }

                $cover_sizes_main = array('large', 'medium', 'full');
                foreach ($cover_sizes_main as $size) {
                    $candidate = get_the_post_thumbnail_url($article_id, $size);
                    if (!empty($candidate)) {
                        $article_cover_url = esc_url_raw($candidate);
                        break;
                    }
                }

                $cover_sizes_thumb = array('medium', 'large', 'full');
                foreach ($cover_sizes_thumb as $size) {
                    $candidate_thumb = get_the_post_thumbnail_url($article_id, $size);
                    if (!empty($candidate_thumb)) {
                        $article_cover_thumb_url = esc_url_raw($candidate_thumb);
                        break;
                    }
                }

                if ($article_cover_thumb_url === '' && $article_cover_url !== '') {
                    $article_cover_thumb_url = $article_cover_url;
                }
            }
        }

        $cover_url = '';
        $cover_thumb_url = '';
        if (!empty($event->cover_id)) {
            $cover_id = (int) $event->cover_id;
            if ($cover_id > 0) {
                $cover_candidate = wp_get_attachment_image_src($cover_id, 'large');
                if (!empty($cover_candidate[0])) {
                    $cover_url = esc_url_raw($cover_candidate[0]);
                }

                $cover_thumb_candidate = wp_get_attachment_image_src($cover_id, 'medium');
                if (!empty($cover_thumb_candidate[0])) {
                    $cover_thumb_url = esc_url_raw($cover_thumb_candidate[0]);
                }
            }
        }

        if ($cover_url === '' && $cover_thumb_url !== '') {
            $cover_url = $cover_thumb_url;
        }

        if ($cover_url === '' && $article_cover_url !== '') {
            $cover_url = $article_cover_url;
        }

        if ($cover_thumb_url === '' && $article_cover_thumb_url !== '') {
            $cover_thumb_url = $article_cover_thumb_url;
        }

        if ($cover_url === '' && $location_cover_url !== '') {
            $cover_url = $location_cover_url;
        }

        $permalink = apply_filters('mj_member_event_permalink', '', $event);
        $permalink = apply_filters('mj_member_animateur_event_permalink', $permalink, $event);
        if (is_string($permalink) && $permalink !== '') {
            $permalink = esc_url_raw($permalink);
        } elseif ($article_permalink !== '') {
            $permalink = $article_permalink;
        } else {
            $permalink = '';
        }

        $event_type = isset($event->type) ? sanitize_key((string) $event->type) : '';
        $event_status = isset($event->status) ? sanitize_key((string) $event->status) : '';

        $type_label = ($event_type !== '' && isset($type_labels[$event_type])) ? $type_labels[$event_type] : ($event_type !== '' ? ucfirst($event_type) : '');
        $status_label = ($event_status !== '' && isset($status_labels[$event_status])) ? $status_labels[$event_status] : ($event_status !== '' ? ucfirst($event_status) : '');

        $price_value = isset($event->prix) ? (float) $event->prix : 0.0;
        $price_label = $price_value > 0
            ? sprintf('%s €', number_format_i18n($price_value, 2))
            : __('Gratuit', 'mj-member');

        $primary_date_label = '';
        if (!empty($occurrences)) {
            $primary_date_label = $occurrences[0]['label'];
        } elseif (!empty($event->date_debut)) {
            $start_timestamp = strtotime((string) $event->date_debut);
            if ($start_timestamp) {
                $primary_date_label = wp_date(get_option('date_format', 'd/m/Y H:i'), $start_timestamp);
            }
        }

        $participant_count_label = $participants_count > 0
            ? sprintf(_n('%d participant', '%d participants', $participants_count, 'mj-member'), $participants_count)
            : __('Aucun participant pour le moment', 'mj-member');

        $cover_alt = $location_label !== ''
            ? $location_label
            : (isset($event->title) && $event->title !== ''
                ? sanitize_text_field((string) $event->title)
                : sprintf(__('Événement #%d', 'mj-member'), $event_id));

        $occurrence_count = count($occurrences);

        $default_occurrence = '';
        $default_occurrence_label = '';
        if (!empty($occurrences)) {
            $today_index = null;
            $future_index = null;

            foreach ($occurrences as $index => $occurrence) {
                $occurrences[$index]['isNext'] = false;
                $timestamp = isset($occurrence['timestamp']) ? (int) $occurrence['timestamp'] : 0;

                if ($timestamp > 0) {
                    $occurrence_date = wp_date('Y-m-d', $timestamp);
                    if ($today_index === null && $occurrence_date === $today_date && $timestamp >= $now_timestamp) {
                        $today_index = $index;
                    }
                    if ($future_index === null && $timestamp > $now_timestamp) {
                        $future_index = $index;
                    }
                } elseif ($future_index === null && empty($occurrence['isPast'])) {
                    $future_index = $index;
                }
            }

            $selected_index = null;
            if ($today_index !== null) {
                $selected_index = $today_index;
            } elseif ($future_index !== null) {
                $selected_index = $future_index;
            } else {
                $selected_index = 0;
            }

            if (isset($occurrences[$selected_index])) {
                $occurrences[$selected_index]['isNext'] = true;
                $default_occurrence = $occurrences[$selected_index]['start'];
                $default_occurrence_label = $occurrences[$selected_index]['label'];
            }
        }

        $age_min = isset($event->age_min) ? (int) $event->age_min : 0;
        $age_max = isset($event->age_max) ? (int) $event->age_max : 0;
        $allow_guardian_registration = isset($event->allow_guardian_registration) ? (int) $event->allow_guardian_registration : 0;
        $capacity_total = isset($event->capacity_total) ? (int) $event->capacity_total : 0;
        $capacity_waitlist = isset($event->capacity_waitlist) ? (int) $event->capacity_waitlist : 0;

        $registration_deadline_raw = '';
        $registration_deadline_label = '';
        if (!empty($event->date_fin_inscription) && $event->date_fin_inscription !== '0000-00-00 00:00:00') {
            $registration_deadline_raw = sanitize_text_field((string) $event->date_fin_inscription);
            $deadline_timestamp = strtotime($registration_deadline_raw);
            if ($deadline_timestamp) {
                $deadline_format = trim(get_option('date_format', 'd/m/Y') . ' ' . get_option('time_format', 'H:i'));
                $registration_deadline_label = wp_date($deadline_format, $deadline_timestamp);
            }
        }

        $conditions = array();
        if ($age_min > 0 && $age_max > 0) {
            $conditions[] = sprintf(__('Âge requis : entre %1$d et %2$d ans.', 'mj-member'), $age_min, $age_max);
        } elseif ($age_min > 0) {
            $conditions[] = sprintf(__('Âge minimum : %d ans.', 'mj-member'), $age_min);
        } elseif ($age_max > 0) {
            $conditions[] = sprintf(__('Âge maximum : %d ans.', 'mj-member'), $age_max);
        }

        if ($allow_guardian_registration === 1) {
            $conditions[] = __('Les tuteurs peuvent inscrire leurs jeunes à cet événement.', 'mj-member');
        } else {
            $conditions[] = __('Inscription réservée aux membres (tuteurs non autorisés).', 'mj-member');
        }

        if ($capacity_total > 0) {
            $conditions[] = sprintf(_n('Capacité maximale : %d place.', 'Capacité maximale : %d places.', $capacity_total, 'mj-member'), $capacity_total);
        }

        if ($capacity_waitlist > 0) {
            $conditions[] = sprintf(_n('Liste d\'attente : %d place supplémentaire.', 'Liste d\'attente : %d places supplémentaires.', $capacity_waitlist, 'mj-member'), $capacity_waitlist);
        }

        if ($registration_deadline_label !== '') {
            $conditions[] = sprintf(__('Clôture des inscriptions : %s.', 'mj-member'), $registration_deadline_label);
        }

        if ($event_type === MjEvents_CRUD::TYPE_STAGE) {
            $conditions[] = __('Réservation : chaque inscription couvre l’ensemble des occurrences du stage.', 'mj-member');
        } elseif ($event_type === MjEvents_CRUD::TYPE_ATELIER) {
            $conditions[] = __('Réservation : seules les occurrences sélectionnées sont attribuées au membre.', 'mj-member');
        }

        $event_data = array(
            'id' => $event_id,
            'title' => isset($event->title) ? sanitize_text_field((string) $event->title) : sprintf(__('Événement #%d', 'mj-member'), $event_id),
            'status' => $event_status,
            'type' => $event_type,
            'start' => isset($event->date_debut) ? sanitize_text_field((string) $event->date_debut) : '',
            'end' => isset($event->date_fin) ? sanitize_text_field((string) $event->date_fin) : '',
            'occurrences' => $occurrences,
            'participants' => $participants,
            'defaultOccurrence' => $default_occurrence,
            'defaultOccurrenceLabel' => $default_occurrence_label,
            'permalink' => $permalink,
            'cover' => array(
                'url' => $cover_url,
                'thumb' => $cover_thumb_url,
                'alt' => $cover_alt,
            ),
            'meta' => array(
                'typeLabel' => $type_label,
                'statusLabel' => $status_label,
                'dateLabel' => $primary_date_label,
                'nextOccurrenceLabel' => $default_occurrence_label,
                'locationLabel' => $location_label,
                'locationAddress' => $location_address,
                'price' => $price_value,
                'priceLabel' => $price_label,
                'participantCount' => $participants_count,
                'participantCountLabel' => $participant_count_label,
            ),
            'price' => $price_value,
            'priceLabel' => $price_label,
            'locationLabel' => $location_label,
            'locationAddress' => $location_address,
            'locationCover' => $location_cover_url,
            'articleId' => $article_id,
            'articlePermalink' => $article_permalink,
            'counts' => array(
                'participants' => $participants_count,
                'occurrences' => $occurrence_count,
            ),
            'constraints' => array(
                'ageMin' => $age_min,
                'ageMax' => $age_max,
                'allowGuardianRegistration' => $allow_guardian_registration,
                'capacityTotal' => $capacity_total,
                'capacityWaitlist' => $capacity_waitlist,
                'registrationDeadline' => $registration_deadline_label,
                'registrationDeadlineRaw' => $registration_deadline_raw,
            ),
            'conditions' => $conditions,
            'participantsCount' => $participants_count,
            'occurrenceCount' => $occurrence_count,
            'isAssigned' => $member_assigned,
        );

        if (!empty($event->location_id)) {
            $event_data['locationId'] = (int) $event->location_id;
        }

        return $event_data;
    }
}

if (!function_exists('mj_member_get_all_events_summary')) {
    function mj_member_get_all_events_summary($args = array()) {
        if (!class_exists('MjEvents_CRUD')) {
            return array();
        }

        if (!function_exists('mj_member_get_events_table_name') || !function_exists('mj_member_table_exists')) {
            return array();
        }

        global $wpdb;
        $events_table = mj_member_get_events_table_name();
        if (!$events_table || !mj_member_table_exists($events_table)) {
            return array();
        }

        $defaults = array(
            'statuses' => array(),
            'limit' => 100,
            'assigned_event_ids' => array(),
        );
        $args = wp_parse_args($args, $defaults);

        $limit = isset($args['limit']) ? (int) $args['limit'] : 100;
        if ($limit <= 0) {
            $limit = 100;
        }
        $limit = min($limit, 500);

        $where_fragments = array();
        $where_params = array();

        if (!empty($args['statuses']) && is_array($args['statuses'])) {
            $statuses = array();
            foreach ($args['statuses'] as $status_candidate) {
                $status_candidate = sanitize_key($status_candidate);
                if ($status_candidate === '') {
                    continue;
                }
                $statuses[] = $status_candidate;
            }

            if (!empty($statuses)) {
                $placeholders = implode(',', array_fill(0, count($statuses), '%s'));
                $where_fragments[] = "status IN ({$placeholders})";
                $where_params = array_merge($where_params, $statuses);
            }
        }

        $sql = "SELECT id, title, status, type, date_debut, date_fin, cover_id, article_id, prix FROM {$events_table}";
        if (!empty($where_fragments)) {
            $sql .= ' WHERE ' . implode(' AND ', $where_fragments);
        }
        $sql .= ' ORDER BY date_debut ASC, id ASC';
        $sql .= ' LIMIT %d';
        $where_params[] = $limit;

        $prepared_sql = !empty($where_params) ? $wpdb->prepare($sql, ...$where_params) : $sql;
        $rows = $wpdb->get_results($prepared_sql);
        if (empty($rows)) {
            return array();
        }

        $assigned_lookup = array();
        if (!empty($args['assigned_event_ids']) && is_array($args['assigned_event_ids'])) {
            foreach ($args['assigned_event_ids'] as $assigned_id) {
                $assigned_id = (int) $assigned_id;
                if ($assigned_id > 0) {
                    $assigned_lookup[$assigned_id] = true;
                }
            }
        }

        $date_format = get_option('date_format', 'd/m/Y');
        static $summary_type_labels = null;
        static $summary_status_labels = null;

        if ($summary_type_labels === null && method_exists('MjEvents_CRUD', 'get_type_labels')) {
            $summary_type_labels = MjEvents_CRUD::get_type_labels();
        }

        if ($summary_status_labels === null && method_exists('MjEvents_CRUD', 'get_status_labels')) {
            $summary_status_labels = MjEvents_CRUD::get_status_labels();
        }

        $items = array();
        static $locations_cache = array();

        foreach ($rows as $row) {
            if (!is_object($row) || empty($row->id)) {
                continue;
            }

            $event_id = (int) $row->id;
            if ($event_id <= 0) {
                continue;
            }

            $start = isset($row->date_debut) ? (string) $row->date_debut : '';
            $date_label = '';
            if ($start !== '') {
                $timestamp = strtotime($start);
                if ($timestamp) {
                    $date_label = wp_date($date_format, $timestamp);
                } else {
                    $date_label = $start;
                }
            }

            $cover_url = '';
            if (!empty($row->cover_id)) {
                $cover_candidate = wp_get_attachment_image_src((int) $row->cover_id, 'medium');
                if (!empty($cover_candidate[0])) {
                    $cover_url = esc_url_raw($cover_candidate[0]);
                }
            }

            $article_id = isset($row->article_id) ? (int) $row->article_id : 0;
            $article_permalink = '';
            $article_cover_url = '';
            $location_cover_url = '';

            if ($article_id > 0) {
                $article_status = get_post_status($article_id);
                if ($article_status && $article_status !== 'trash') {
                    $article_permalink_candidate = get_permalink($article_id);
                    if (!empty($article_permalink_candidate)) {
                        $article_permalink = esc_url_raw($article_permalink_candidate);
                    }

                    $article_cover_url_candidate = '';
                    $cover_sizes = array('medium', 'large', 'full');
                    foreach ($cover_sizes as $size) {
                        $candidate = get_the_post_thumbnail_url($article_id, $size);
                        if (!empty($candidate)) {
                            $article_cover_url_candidate = $candidate;
                            break;
                        }
                    }

                    if ($article_cover_url_candidate !== '') {
                        $article_cover_url = esc_url_raw($article_cover_url_candidate);
                    }
                }
            }

            $type_key = isset($row->type) ? sanitize_key((string) $row->type) : '';
            $status_key = isset($row->status) ? sanitize_key((string) $row->status) : '';

            $type_label = ($type_key !== '' && isset($summary_type_labels[$type_key])) ? $summary_type_labels[$type_key] : ($type_key !== '' ? ucfirst($type_key) : '');
            $status_label = ($status_key !== '' && isset($summary_status_labels[$status_key])) ? $summary_status_labels[$status_key] : ($status_key !== '' ? ucfirst($status_key) : '');

            $price_value = isset($row->prix) ? (float) $row->prix : 0.0;
            $price_label = $price_value > 0
                ? sprintf('%s €', number_format_i18n($price_value, 2))
                : __('Gratuit', 'mj-member');

            if ($cover_url === '' && $article_cover_url !== '') {
                $cover_url = $article_cover_url;
            }

            if ($cover_url === '' && !empty($row->location_id) && class_exists('MjEventLocations')) {
                $location_id = (int) $row->location_id;
                if ($location_id > 0) {
                    if (!isset($locations_cache[$location_id])) {
                        $locations_cache[$location_id] = MjEventLocations::find($location_id);
                    }
                    $location_row = $locations_cache[$location_id];
                    if ($location_row && !empty($location_row->cover_id)) {
                        $location_cover_candidate = wp_get_attachment_image_src((int) $location_row->cover_id, 'medium');
                        if (!empty($location_cover_candidate[0])) {
                            $location_cover_url = esc_url_raw($location_cover_candidate[0]);
                        }
                    }
                }
            }

            if ($cover_url === '' && $location_cover_url !== '') {
                $cover_url = $location_cover_url;
            }

            $permalink = apply_filters('mj_member_event_permalink', '', $row);
            if (is_string($permalink) && $permalink !== '') {
                $permalink = esc_url_raw($permalink);
            } elseif ($article_permalink !== '') {
                $permalink = $article_permalink;
            } else {
                $permalink = '';
            }

            $items[] = array(
                'id' => $event_id,
                'title' => isset($row->title) ? sanitize_text_field((string) $row->title) : sprintf(__('Événement #%d', 'mj-member'), $event_id),
                'status' => isset($row->status) ? sanitize_key((string) $row->status) : '',
                'type' => isset($row->type) ? sanitize_key((string) $row->type) : '',
                'start' => $start,
                'end' => isset($row->date_fin) ? (string) $row->date_fin : '',
                'dateLabel' => $date_label,
                'assigned' => isset($assigned_lookup[$event_id]),
                'typeLabel' => $type_label,
                'statusLabel' => $status_label,
                'coverUrl' => $cover_url,
                'price' => $price_value,
                'priceLabel' => $price_label,
                'permalink' => $permalink,
                'articleId' => $article_id,
                'articlePermalink' => $article_permalink,
                'locationCoverUrl' => $location_cover_url,
            );
        }

        return $items;
    }
}

if (!function_exists('mj_member_get_animateur_events_data')) {
    function mj_member_get_animateur_events_data($member, $args = array()) {
        if (!$member || !is_object($member) || empty($member->id)) {
            return array('events' => array());
        }

        if (!class_exists('MjEventAnimateurs') || !class_exists('MjEvents_CRUD')) {
            return array('events' => array());
        }

        $defaults = array(
            'include_past_occurrences' => true,
            'occurrence_limit' => 50,
            'statuses' => array(),
            'include_all_events' => false,
            'view_all_statuses' => array(),
            'view_all_limit' => 100,
        );
        $args = wp_parse_args($args, $defaults);

        $events = MjEventAnimateurs::get_events_for_member(
            (int) $member->id,
            array(
                'statuses' => is_array($args['statuses']) ? $args['statuses'] : array(),
                'orderby' => 'date_debut',
                'order' => 'ASC',
            )
        );
        

        if (empty($events)) {
            $payload = array(
                'events' => array(),
            );

            if (!empty($args['include_all_events'])) {
                $payload['all_events'] = mj_member_get_all_events_summary(array(
                    'statuses' => is_array($args['view_all_statuses']) ? $args['view_all_statuses'] : array(),
                    'limit' => (int) $args['view_all_limit'],
                    'assigned_event_ids' => array(),
                ));
            }

            return $payload;
        }

        $prepared = array();
        foreach ($events as $event) {
            $prepared_event = mj_member_prepare_animateur_event_data($event, (int) $member->id, $args);
            if (!empty($prepared_event)) {
                $prepared[] = $prepared_event;
            }
        }

        $payload = array('events' => $prepared);

        if (!empty($args['include_all_events'])) {
            $assigned_ids = array();
            foreach ($prepared as $prepared_event) {
                if (isset($prepared_event['id'])) {
                    $assigned_ids[] = (int) $prepared_event['id'];
                }
            }

            $payload['all_events'] = mj_member_get_all_events_summary(array(
                'statuses' => is_array($args['view_all_statuses']) ? $args['view_all_statuses'] : array(),
                'limit' => (int) $args['view_all_limit'],
                'assigned_event_ids' => $assigned_ids,
            ));
        }

        return $payload;
    }
}

if (!function_exists(function: 'mj_member_render_animateur_component')) {
    function mj_member_render_animateur_component($options = array()) {
        $defaults = array(
            'title' => __('Mes participants', 'mj-member'),
            'description' => '',
            'wrapper_class' => '',
            'show_event_filter' => true,
            'show_occurrence_filter' => true,
            'show_attendance_actions' => true,
            'show_sms_block' => true,
            'show_individual_messages' => true,
            'accent_color' => '',
            'cover_fallback' => 'article',
            'is_elementor_preview' => false,
            'view_all' => array(
                'enabled' => true,
                'label' => __('Voir tous les événements', 'mj-member'),
                'active_label' => __('Voir mes événements', 'mj-member'),
                'mode' => 'toggle',
                'url' => '',
                'is_external' => false,
                'statuses' => array(),
                'limit' => 100,
            ),
        );
        $settings = wp_parse_args($options, $defaults);

        $accent_color = '';
        if (!empty($settings['accent_color'])) {
            $sanitized_color = sanitize_hex_color($settings['accent_color']);
            if (is_string($sanitized_color)) {
                $accent_color = $sanitized_color;
            }
        }

        $show_event_filter = !empty($settings['show_event_filter']);
        $show_occurrence_filter = !empty($settings['show_occurrence_filter']);
        $show_attendance_actions = !empty($settings['show_attendance_actions']);
        $show_sms_block = !empty($settings['show_sms_block']);
        $show_individual_messages = array_key_exists('show_individual_messages', $settings) ? !empty($settings['show_individual_messages']) : true;
        $cover_fallback = isset($settings['cover_fallback']) ? sanitize_key($settings['cover_fallback']) : 'article';
        if ($cover_fallback !== 'article') {
            $cover_fallback = 'none';
        }
        $is_elementor_preview = !empty($settings['is_elementor_preview']);

        $view_all_defaults = array(
            'enabled' => true,
            'label' => __('Voir tous les événements', 'mj-member'),
            'active_label' => __('Voir mes événements', 'mj-member'),
            'mode' => 'toggle',
            'url' => '',
            'is_external' => false,
            'statuses' => array(),
            'limit' => 100,
        );
        $view_all = wp_parse_args(is_array($settings['view_all']) ? $settings['view_all'] : array(), $view_all_defaults);
        $view_all['enabled'] = !empty($view_all['enabled']);
        $view_all['label'] = isset($view_all['label']) && $view_all['label'] !== '' ? (string) $view_all['label'] : $view_all_defaults['label'];
        $view_all['active_label'] = isset($view_all['active_label']) && $view_all['active_label'] !== '' ? (string) $view_all['active_label'] : $view_all_defaults['active_label'];
        $view_all['mode'] = isset($view_all['mode']) && $view_all['mode'] === 'link' ? 'link' : 'toggle';
        $view_all['limit'] = isset($view_all['limit']) ? (int) $view_all['limit'] : (int) $view_all_defaults['limit'];
        if ($view_all['limit'] <= 0) {
            $view_all['limit'] = (int) $view_all_defaults['limit'];
        }
        $view_all['limit'] = min($view_all['limit'], 500);

        $view_all_statuses = array();
        if (!empty($view_all['statuses']) && is_array($view_all['statuses'])) {
            foreach ($view_all['statuses'] as $status_candidate) {
                $status_candidate = sanitize_key($status_candidate);
                if ($status_candidate !== '') {
                    $view_all_statuses[$status_candidate] = $status_candidate;
                }
            }
        }
        $view_all['statuses'] = array_values($view_all_statuses);

        $view_all['url'] = isset($view_all['url']) ? (string) $view_all['url'] : '';
        if ($view_all['mode'] === 'link' && $view_all['url'] !== '') {
            $view_all['url'] = esc_url_raw($view_all['url']);
            $view_all['is_external'] = !empty($view_all['is_external']);
        } else {
            $view_all['mode'] = 'toggle';
            $view_all['url'] = '';
            $view_all['is_external'] = false;
        }

        if (!$is_elementor_preview && !is_user_logged_in()) {
            return '<div class="mj-animateur-dashboard__warning">' . esc_html__('Vous devez être connecté pour accéder à cet espace.', 'mj-member') . '</div>';
        }

        $member = mj_member_get_current_animateur_member();
        
        
        if (!$member && !$is_elementor_preview) {
            return '<div class="mj-animateur-dashboard__warning">' . esc_html__('Cet espace est réservé aux animateurs.', 'mj-member') . '</div>';
        }

        $events = array();
        $all_events = array();

        if ($member) {
            $data = mj_member_get_animateur_events_data(
                $member,
                array(
                    'include_all_events' => $view_all['enabled'] && $view_all['mode'] === 'toggle',
                    'view_all_statuses' => $view_all['statuses'],
                    'view_all_limit' => $view_all['limit'],
                )
            );
            $events = isset($data['events']) && is_array($data['events']) ? $data['events'] : array();
            $all_events = isset($data['all_events']) && is_array($data['all_events']) ? $data['all_events'] : array();
        }

        if ($is_elementor_preview && empty($events) && class_exists('MjEvents_CRUD')) {
            $preview_summary = mj_member_get_all_events_summary(array(
                'statuses' => $view_all['statuses'],
                'limit' => $view_all['limit'],
                'assigned_event_ids' => array(),
            ));

            if (!empty($preview_summary)) {
                $preview_events = array();
                $preview_member_id = $member && !empty($member->id) ? (int) $member->id : 0;

                foreach ($preview_summary as $index => $summary_item) {
                    if (empty($summary_item['id'])) {
                        continue;
                    }

                    $summary_item['assigned'] = true;

                    $event_row = MjEvents_CRUD::find((int) $summary_item['id']);
                    if (!$event_row) {
                        $preview_summary[$index] = $summary_item;
                        continue;
                    }

                    $prepared_event = mj_member_prepare_animateur_event_data(
                        $event_row,
                        $preview_member_id,
                        array(
                            'include_past_occurrences' => true,
                            'occurrence_limit' => 20,
                        )
                    );

                    if (!empty($prepared_event)) {
                        $prepared_event['isAssigned'] = true;
                        $preview_events[] = $prepared_event;
                    }

                    $preview_summary[$index] = $summary_item;
                }

                if (!empty($preview_events)) {
                    $events = $preview_events;
                    if (empty($all_events)) {
                        $all_events = $preview_summary;
                    }
                }
            }
        }

        if ($cover_fallback === 'article' && (!empty($events) || !empty($all_events))) {
            $article_cover_cache = array();

            $resolve_article_cover = static function ($article_id) use (&$article_cover_cache) {
                $article_id = (int) $article_id;
                if ($article_id <= 0) {
                    return array('large' => '', 'medium' => '');
                }

                if (!isset($article_cover_cache[$article_id])) {
                    $large = get_the_post_thumbnail_url($article_id, 'large');
                    $medium = get_the_post_thumbnail_url($article_id, 'medium');

                    if (empty($medium) && !empty($large)) {
                        $medium = $large;
                    } elseif (empty($large) && !empty($medium)) {
                        $large = $medium;
                    }

                    $article_cover_cache[$article_id] = array(
                        'large' => !empty($large) ? esc_url_raw($large) : '',
                        'medium' => !empty($medium) ? esc_url_raw($medium) : '',
                    );
                }

                return $article_cover_cache[$article_id];
            $pending_photo_items = array();
            if (!empty($events) && class_exists('MjEventPhotos')) {
                $event_ids = array_map('intval', wp_list_pluck($events, 'id'));
                $pending_rows = MjEventPhotos::get_pending_for_events($event_ids, 30);

                if (!empty($pending_rows)) {
                    $event_cache = array();
                    $member_cache = array();

                    foreach ($pending_rows as $pending_row) {
                        $photo_id = isset($pending_row->id) ? (int) $pending_row->id : 0;
                        $event_id = isset($pending_row->event_id) ? (int) $pending_row->event_id : 0;
                        $member_id = isset($pending_row->member_id) ? (int) $pending_row->member_id : 0;
                        $attachment_id = isset($pending_row->attachment_id) ? (int) $pending_row->attachment_id : 0;

                        if ($photo_id <= 0 || $event_id <= 0 || $attachment_id <= 0) {
                            continue;
                        }

                        if (!isset($event_cache[$event_id]) && class_exists('MjEvents_CRUD')) {
                            $event_cache[$event_id] = MjEvents_CRUD::find($event_id);
                        }
                        $event_record = isset($event_cache[$event_id]) ? $event_cache[$event_id] : null;
                        $event_title = $event_record && !empty($event_record->title) ? (string) $event_record->title : sprintf(__('Événement #%d', 'mj-member'), $event_id);
                        $event_link = $event_record ? mj_member_get_event_public_link($event_record) : '';

                        if ($member_id > 0 && !isset($member_cache[$member_id]) && class_exists('MjMembers_CRUD')) {
                            $member_cache[$member_id] = MjMembers_CRUD::getById($member_id);
                        }

                        $member = isset($member_cache[$member_id]) ? $member_cache[$member_id] : null;
                        $member_label = $member ? mj_member_event_photos_format_member_name($member) : sprintf(__('Participant #%d', 'mj-member'), $member_id);

                        $thumb_src = wp_get_attachment_image_src($attachment_id, 'medium');
                        $full_src = wp_get_attachment_image_src($attachment_id, 'large');

                        $submitted_at = isset($pending_row->created_at) ? strtotime((string) $pending_row->created_at) : 0;
                        $submitted_label = $submitted_at ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $submitted_at) : '';

                        $pending_photo_items[] = array(
                            'id' => $photo_id,
                            'event_id' => $event_id,
                            'event_title' => $event_title,
                            'event_link' => $event_link,
                            'member_label' => $member_label,
                            'attachment_id' => $attachment_id,
                            'thumb' => $thumb_src ? esc_url($thumb_src[0]) : '',
                            'full' => $full_src ? esc_url($full_src[0]) : '',
                            'submitted' => $submitted_label,
                            'caption' => !empty($pending_row->caption) ? sanitize_text_field($pending_row->caption) : '',
                        );
                    }
                }
            }

            };

            if (!empty($events)) {
                foreach ($events as $event_index => &$event_entry) {
                    if (!is_array($event_entry)) {
                        continue;
                    }

                    $article_id = isset($event_entry['articleId']) ? (int) $event_entry['articleId'] : 0;
                    if ($article_id <= 0) {
                        continue;
                    }

                    $needs_cover = empty($event_entry['coverUrl']);
                    $needs_cover_array = !isset($event_entry['cover']) || !is_array($event_entry['cover']) || empty($event_entry['cover']['url']);
                    $needs_thumb = !isset($event_entry['cover']) || !is_array($event_entry['cover']) || empty($event_entry['cover']['thumb']);

                    if (!$needs_cover && !$needs_cover_array && !$needs_thumb) {
                        continue;
                    }

                    $covers = $resolve_article_cover($article_id);
                    if ($covers['large'] === '' && $covers['medium'] === '') {
                        continue;
                    }

                    if (!isset($event_entry['cover']) || !is_array($event_entry['cover'])) {
                        $event_entry['cover'] = array(
                            'url' => '',
                            'thumb' => '',
                            'alt' => '',
                        );
                    }

                    if ($needs_cover && $covers['large'] !== '') {
                        $event_entry['coverUrl'] = $covers['large'];
                    }

                    if ($needs_cover_array && $covers['large'] !== '') {
                        $event_entry['cover']['url'] = $covers['large'];
                    }

                    if ($needs_thumb && ($covers['medium'] !== '' || $covers['large'] !== '')) {
                        $event_entry['cover']['thumb'] = $covers['medium'] !== '' ? $covers['medium'] : $covers['large'];
                    }

                    if (empty($event_entry['cover']['alt']) && !empty($event_entry['title'])) {
                        $event_entry['cover']['alt'] = sanitize_text_field((string) $event_entry['title']);
                    }
                }
                unset($event_entry);
            }

            if (!empty($all_events)) {
                foreach ($all_events as $summary_index => &$summary_item) {
                    if (!is_array($summary_item)) {
                        continue;
                    }

                    if (!empty($summary_item['coverUrl'])) {
                        continue;
                    }

                    $article_id = isset($summary_item['articleId']) ? (int) $summary_item['articleId'] : 0;
                    if ($article_id <= 0) {
                        continue;
                    }

                    $covers = $resolve_article_cover($article_id);

                    if ($covers['medium'] !== '') {
                        $summary_item['coverUrl'] = $covers['medium'];
                    } elseif ($covers['large'] !== '') {
                        $summary_item['coverUrl'] = $covers['large'];
                    }
                }
                unset($summary_item);
            }
        }

        $style_handle = 'mj-member-animateur-dashboard';
        $style_path = Config::path() . 'css/styles.css';
        $style_version = file_exists($style_path) ? (string) filemtime($style_path) : Config::version();
        wp_enqueue_style(
            $style_handle,
            Config::url() . 'css/styles.css',
            array(),
            $style_version
        );

        mj_member_register_animateur_account_assets();
        wp_enqueue_script('mj-member-animateur-account');
        wp_localize_script(
            'mj-member-animateur-account',
            'MjMemberAnimateur',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mj_member_animateur'),
                'attendanceLabels' => mj_member_get_attendance_status_labels(),
                'actions' => array(
                    'event' => 'mj_member_animateur_get_event',
                    'claim' => 'mj_member_animateur_claim_event',
                    'release' => 'mj_member_animateur_release_event',
                    'attendance' => 'mj_member_animateur_save_attendance',
                    'sms' => 'mj_member_animateur_send_sms',
                    'payment' => 'mj_member_animateur_toggle_cash_payment',
                    'paymentLink' => 'mj_member_animateur_generate_payment_link',
                    'memberSearch' => 'mj_member_animateur_search_members',
                    'memberAdd' => 'mj_member_animateur_add_members',
                    'memberQuickCreate' => 'mj_member_animateur_quick_create_member',
                    'registrationRemove' => 'mj_member_animateur_remove_registration',
                ),
            )
        );

        $can_edit_members_cap = function_exists('current_user_can') ? current_user_can(Config::capability()) : false;
        $can_edit_members_cap = apply_filters('mj_member_animateur_can_edit_members', (bool) $can_edit_members_cap, $member, $settings);

        $can_remove_registrations_default = !$is_elementor_preview;
        if (!$member && !$can_edit_members_cap) {
            $can_remove_registrations_default = false;
        }
        $can_remove_registrations = apply_filters('mj_member_animateur_can_remove_registrations', $can_remove_registrations_default, $member, $settings);

        $member_picker_per_page = (int) apply_filters('mj_member_animateur_member_picker_per_page', 20);
        if ($member_picker_per_page <= 0) {
            $member_picker_per_page = 20;
        }

        $default_filter = !empty($events) ? 'assigned' : 'all';

        $quick_create_enabled = $can_edit_members_cap && !$is_elementor_preview;
        $quick_create_enabled = apply_filters('mj_member_animateur_quick_create_enabled', (bool) $quick_create_enabled, $member, $settings);

        $payment_link_enabled = class_exists('MjPayments');
        $payment_link_nonce = $payment_link_enabled ? wp_create_nonce('mj_member_animateur_payment_link') : '';

        $component_config = array(
            'events' => $events,
            'quickCreate' => array(
                'enabled' => (bool) $quick_create_enabled,
            ),
            'i18n' => array(
                'noParticipants' => __('Aucun participant pour cette sélection.', 'mj-member'),
                'attendanceSaved' => __('Présences enregistrées.', 'mj-member'),
                'attendanceError' => __("Impossible d'enregistrer les présences.", 'mj-member'),
                'attendanceUpdated' => __('Présence mise à jour.', 'mj-member'),
                'attendanceUpdateError' => __("Impossible de mettre à jour la présence.", 'mj-member'),
                'attendanceInfoPrefix' => __('Statut :', 'mj-member'),
                'attendancePending' => __('À confirmer', 'mj-member'),
                'paymentMarked' => __('Paiement confirmé.', 'mj-member'),
                'paymentReset' => __('Paiement réinitialisé.', 'mj-member'),
                'paymentError' => __("Impossible de mettre à jour le paiement.", 'mj-member'),
                'paymentMarkButton' => __('Marquer payé', 'mj-member'),
                'paymentUnmarkButton' => __('Annuler le paiement', 'mj-member'),
                'paymentLinkButton' => __('Lien de paiement', 'mj-member'),
                'paymentLinkGenerating' => __('Génération du lien...', 'mj-member'),
                'paymentLinkSuccess' => __('Lien prêt à être partagé.', 'mj-member'),
                'paymentLinkError' => __('Impossible de générer le lien de paiement.', 'mj-member'),
                'paymentLinkOpen' => __('Ouvrir', 'mj-member'),
                'paymentLinkAmount' => __('Montant : %s €', 'mj-member'),
                'paymentLinkQrAlt' => __('QR code du paiement', 'mj-member'),
                'paymentLinkModalTitle' => __('Lien de paiement', 'mj-member'),
                'paymentLinkModalClose' => __('Fermer', 'mj-member'),
                'paymentRecordedBy' => __('Noté par %s', 'mj-member'),
                'paymentRecordedAt' => __('le %s', 'mj-member'),
                'paymentMethodStripe' => __('Payé via Stripe', 'mj-member'),
                'paymentMethodCash' => __('Payé en main propre', 'mj-member'),
                'paymentMethodOther' => __('Paiement confirmé', 'mj-member'),
                'smsSuccess' => __('SMS envoyé.', 'mj-member'),
                'smsError' => __("Impossible d'envoyer le SMS.", 'mj-member'),
                'smsEmpty' => __("Veuillez saisir un message avant l'envoi.", 'mj-member'),
                'noSmsRecipients' => __('Aucun participant ne peut recevoir ce SMS.', 'mj-member'),
                'quickCreateTitle' => __('Créer un membre', 'mj-member'),
                'quickCreateDescription' => __('Encodez les informations essentielles du membre. En cas de saisie d\'une adresse email, un message lui sera envoyé pour créer son code.', 'mj-member'),
                'quickCreateFirstName' => __('Prénom', 'mj-member'),
                'quickCreateLastName' => __('Nom', 'mj-member'),
                'quickCreateBirthDate' => __('Date de naissance', 'mj-member'),
                'quickCreateBirthDateHint' => __('Requis pour vérifier l\'âge lors des réservations.', 'mj-member'),
                'quickCreateEmail' => __('Email (facultatif)', 'mj-member'),
                'quickCreateEmailHint' => __('Un email contenant le lien de création de code est envoyé si ce champ est renseigné.', 'mj-member'),
                'quickCreateSubmit' => __('Créer le membre', 'mj-member'),
                'quickCreateCancel' => __('Annuler', 'mj-member'),
                'quickCreateCloseLabel' => __('Fermer', 'mj-member'),
                'quickCreateSuccess' => __('Membre créé. Vous pouvez maintenant l\'ajouter aux réservations.', 'mj-member'),
                'quickCreateError' => __("Impossible de créer le membre.", 'mj-member'),
                'quickCreateFirstNameRequired' => __('Le prénom est obligatoire.', 'mj-member'),
                'quickCreateLastNameRequired' => __('Le nom est obligatoire.', 'mj-member'),
                'quickCreateBirthDateRequired' => __('La date de naissance est obligatoire.', 'mj-member'),
                'quickCreateInvalidBirthDate' => __('La date de naissance est invalide.', 'mj-member'),
                'quickCreateInvalidEmail' => __("L'adresse email n'est pas valide.", 'mj-member'),
                'quickCreateDuplicateEmail' => __('Un membre existe déjà avec cette adresse email.', 'mj-member'),
                'missingOccurrence' => __("Veuillez sélectionner une occurrence avant d'enregistrer.", 'mj-member'),
                'notAssigned' => __('Cet événement ne vous est pas attribué.', 'mj-member'),
                'agendaEmpty' => __('Aucune occurrence à afficher.', 'mj-member'),
                'agendaTodayBadge' => __("Aujourd'hui", 'mj-member'),
                'agendaNextBadge' => __('Prochaine', 'mj-member'),
                'eventCardUnassigned' => __('Non assigné', 'mj-member'),
                'eventCardAssigned' => __('Assigné', 'mj-member'),
                'eventCardParticipants' => __('Participants : %d', 'mj-member'),
                'eventCardViewLink' => __('Détails', 'mj-member'),
                'claimPrompt' => __('M\'attribuer cet événement', 'mj-member'),
                'claimSuccess' => __('Événement ajouté à vos événements.', 'mj-member'),
                'claimError' => __('Impossible d\'attribuer cet événement.', 'mj-member'),
                'releasePrompt' => __('Me désassigner de cet événement', 'mj-member'),
                'releaseSuccess' => __('Événement retiré de vos événements.', 'mj-member'),
                'releaseError' => __('Impossible de vous désassigner de cet événement.', 'mj-member'),
                'eventTabAll' => __('Tous', 'mj-member'),
                'eventTabAssigned' => __('Assignés à moi', 'mj-member'),
                'eventTabUpcoming' => __('À venir', 'mj-member'),
                'eventTabPast' => __('Passés', 'mj-member'),
                'eventTabDraft' => __('Brouillons', 'mj-member'),
                'eventTabEmpty' => __('Aucun événement trouvé', 'mj-member'),
                'eventNavPrev' => __('Événement précédent', 'mj-member'),
                'eventNavNext' => __('Événement suivant', 'mj-member'),
                'occurrenceNavPrev' => __('Occurrence précédente', 'mj-member'),
                'occurrenceNavNext' => __('Occurrence suivante', 'mj-member'),
                'eventLoadError' => __('Impossible de charger cet événement.', 'mj-member'),
                'messageToggleOpen' => __('Contacter', 'mj-member'),
                'messageToggleClose' => __('Fermer', 'mj-member'),
                'messagePlaceholder' => __('Votre message au participant', 'mj-member'),
                'messageSend' => __('Envoyer', 'mj-member'),
                'messageCancel' => __('Annuler', 'mj-member'),
                'messageSuccess' => __('SMS envoyé au participant.', 'mj-member'),
                'messageError' => __("Impossible d'envoyer le SMS au participant.", 'mj-member'),
                'messageNoRecipient' => __('Ce participant ne peut pas recevoir de SMS.', 'mj-member'),
                'eventEmpty' => __('Sélectionnez un événement pour afficher la liste des participants.', 'mj-member'),
                'memberPickerTitle' => __('Ajouter des participants', 'mj-member'),
                'memberPickerSearchPlaceholder' => __('Rechercher un membre...', 'mj-member'),
                'memberPickerEmpty' => __('Aucun membre trouvé.', 'mj-member'),
                'memberPickerLoading' => __('Chargement...', 'mj-member'),
                'memberPickerAlreadyAssigned' => __('Déjà inscrit', 'mj-member'),
                'memberPickerAssignedOtherOccurrence' => __('Inscrit sur une autre séance', 'mj-member'),
                'memberPickerConfirm' => __('Ajouter les membres sélectionnés', 'mj-member'),
                'memberPickerConfirmCount' => __('Ajouter (%d)', 'mj-member'),
                'memberPickerCancel' => __('Annuler', 'mj-member'),
                'memberPickerNoEvent' => __('Sélectionnez un événement avant d\'ajouter un participant.', 'mj-member'),
                'memberPickerSelectionEmpty' => __('Sélectionnez au moins un membre.', 'mj-member'),
                'memberPickerFetchError' => __('Impossible de charger la liste des membres.', 'mj-member'),
                'memberPickerSubmitError' => __("Impossible d'ajouter les membres sélectionnés.", 'mj-member'),
                'memberPickerSubmitPartial' => __("Certains membres n'ont pas pu être ajoutés.", 'mj-member'),
                'memberPickerSubmitSuccess' => __('Participants ajoutés.', 'mj-member'),
                'memberPickerLoadMore' => __('Charger plus', 'mj-member'),
                'memberPickerConditionsTitle' => __('Conditions de l\'événement', 'mj-member'),
                'memberPickerIneligible' => __('Conditions non respectées', 'mj-member'),
                'registrationRemoveLabel' => __('Supprimer la réservation', 'mj-member'),
                'registrationRemoveConfirm' => __('Voulez-vous retirer cette réservation ?', 'mj-member'),
                'registrationRemoveSuccess' => __('Réservation supprimée.', 'mj-member'),
                'registrationRemoveError' => __("Impossible de supprimer la réservation.", 'mj-member'),
                'registrationEditLabel' => __('Modifier la fiche', 'mj-member'),
                'participantEmailLabel' => __('Email', 'mj-member'),
                'participantPhoneLabel' => __('Téléphone', 'mj-member'),
                'participantGuardianLabel' => __('Tuteur', 'mj-member'),
                'participantAgeYears' => __('%d ans', 'mj-member'),
            ),
            'settings' => array(
                'attendance' => $show_attendance_actions,
                'sms' => $show_sms_block,
                'individualMessaging' => $show_individual_messages,
                'registrations' => array(
                    'canDelete' => (bool) $can_remove_registrations,
                    'canEdit' => (bool) $can_edit_members_cap,
                ),
            ),
            'filters' => array(
                'default' => $default_filter,
            ),
            'paymentLabels' => mj_member_get_event_payment_status_labels(),
            'paymentLink' => array(
                'enabled' => (bool) $payment_link_enabled,
                'nonce' => $payment_link_nonce,
            ),
            'viewAll' => array(
                'enabled' => $view_all['enabled'],
                'mode' => $view_all['mode'],
                'label' => $view_all['label'],
                'activeLabel' => $view_all['active_label'],
                'url' => $view_all['url'],
                'isExternal' => $view_all['is_external'],
            ),
            'memberPicker' => array(
                'perPage' => $member_picker_per_page,
            ),
            'assignedEventIds' => array_map('intval', wp_list_pluck($events, 'id')),
            'allEvents' => $all_events,
        );

        if (!empty($events)) {
            $component_config['defaultEvent'] = (int) $events[0]['id'];
        } elseif (!empty($all_events)) {
            $component_config['defaultEvent'] = (int) $all_events[0]['id'];
        }

        $wrapper_classes = array('mj-animateur-dashboard');
        if (!empty($settings['wrapper_class'])) {
            $additional = is_array($settings['wrapper_class']) ? $settings['wrapper_class'] : preg_split('/\s+/', (string) $settings['wrapper_class']);
            foreach ($additional as $class) {
                $class = trim((string) $class);
                if ($class !== '') {
                    $wrapper_classes[] = sanitize_html_class($class);
                }
            }
        }

        $config_attr = esc_attr(wp_json_encode($component_config));
        $has_assigned_events = !empty($events);
        $has_toggle_events = $view_all['enabled'] && $view_all['mode'] === 'toggle' && !empty($all_events);
        $render_dashboard = $has_assigned_events || $has_toggle_events;
        $selected_event = $has_assigned_events ? $events[0] : null;
        $occurrence_options = $has_assigned_events ? (isset($events[0]['occurrences']) ? $events[0]['occurrences'] : array()) : array();
        $selected_occurrence = '';
        if ($selected_event && !empty($selected_event['defaultOccurrence'])) {
            $selected_occurrence = (string) $selected_event['defaultOccurrence'];
        } elseif (!empty($occurrence_options)) {
            $selected_occurrence = (string) $occurrence_options[0]['start'];
        }

        $sms_field_id = sanitize_html_class('mj-animateur-sms-' . wp_rand(1000, 9999));
        $wrapper_style_attr = $accent_color !== '' ? ' style="--mj-animateur-accent:' . esc_attr($accent_color) . ';"' : '';
        $button_style_attr = $accent_color !== '' ? ' style="background-color:' . esc_attr($accent_color) . ';border-color:' . esc_attr($accent_color) . ';"' : '';
        $view_all_is_toggle = $view_all['enabled'] && $view_all['mode'] === 'toggle';
        $view_all_has_link = $view_all['enabled'] && $view_all['mode'] === 'link' && $view_all['url'] !== '';
        $view_all_link_attr = ($view_all_has_link && $view_all['is_external']) ? ' target="_blank" rel="noopener noreferrer"' : '';
        $view_all_button_attr = $button_style_attr;
        $view_all_toggle_attrs = $view_all_is_toggle ? ' data-role="toggle-view-all" data-label-default="' . esc_attr($view_all['label']) . '" data-label-active="' . esc_attr($view_all['active_label']) . '"' : '';

        $member_links = array();
        if (current_user_can(Config::capability())) {
            $member_links[] = array(
                'label' => __('Créer un membre', 'mj-member'),
                'url' => add_query_arg(
                    array(
                        'page' => 'mj_members',
                        'action' => 'add',
                    ),
                    admin_url('admin.php')
                ),
                'variant' => 'primary',
                'icon' => '+',
                'slug' => 'quick-member',
                'attributes' => $quick_create_enabled ? array('data-role' => 'quick-member-open') : array(),
            );
        }

        /**
         * Filtre permettant de personnaliser les liens d'action visibles dans le tableau de bord animateur.
         *
         * @param array $member_links Liens préparés par défaut.
         * @param array $settings     Paramètres du composant Elementor.
         */
        $member_links = apply_filters('mj_member_animateur_dashboard_links', $member_links, $settings);

        $member_links = array_values(array_filter($member_links, static function ($link) {
            if (!is_array($link)) {
                return false;
            }
            if (empty($link['label']) || empty($link['url'])) {
                return false;
            }
            return true;
        }));

        $render_member_toolbar = static function ($links) {
            if (empty($links) || !is_array($links)) {
                return '';
            }

            ob_start();
            ?>
            <div class="mj-animateur-dashboard__toolbar" data-role="toolbar">
                <?php foreach ($links as $link) : ?>
                    <?php
                    $href = isset($link['url']) ? esc_url($link['url']) : '';
                    $label = isset($link['label']) ? (string) $link['label'] : '';
                    if ($href === '' || $label === '') {
                        continue;
                    }

                    $variant = isset($link['variant']) ? sanitize_html_class((string) $link['variant']) : 'primary';
                    if ($variant === '') {
                        $variant = 'primary';
                    }

                    $slug = isset($link['slug']) ? sanitize_html_class((string) $link['slug']) : '';
                    $icon = isset($link['icon']) ? (string) $link['icon'] : '+';
                    if ($icon === '') {
                        $icon = '+';
                    }

                    $classes = array('mj-animateur-dashboard__cta', 'mj-animateur-dashboard__cta--' . $variant);
                    if ($slug !== '') {
                        $classes[] = 'mj-animateur-dashboard__cta--' . $slug;
                    }

                    $attr_chunks = array();
                    if (!empty($link['is_external'])) {
                        $attr_chunks[] = 'target="_blank"';
                        $attr_chunks[] = 'rel="noopener noreferrer"';
                    }

                    if (!empty($link['attributes']) && is_array($link['attributes'])) {
                        foreach ($link['attributes'] as $attr_name => $attr_value) {
                            $attr_name = is_string($attr_name) ? trim($attr_name) : '';
                            if ($attr_name === '' || !preg_match('/^[a-zA-Z0-9_\-:]+$/', $attr_name)) {
                                continue;
                            }

                            if (is_bool($attr_value)) {
                                if ($attr_value) {
                                    $attr_chunks[] = $attr_name;
                                }
                                continue;
                            }

                            $attr_chunks[] = $attr_name . '="' . esc_attr((string) $attr_value) . '"';
                        }
                    }

                    $attributes_html = !empty($attr_chunks) ? ' ' . implode(' ', $attr_chunks) : '';
                    ?>
                    <a class="<?php echo esc_attr(implode(' ', array_unique($classes))); ?>" href="<?php echo $href; ?>"<?php echo $attributes_html; ?>>
                        <span class="mj-animateur-dashboard__cta-icon" aria-hidden="true" data-icon="<?php echo esc_attr($icon); ?>"></span>
                        <span class="mj-animateur-dashboard__cta-label"><?php echo esc_html($label); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php
            return trim(ob_get_clean());
        };

        $member_toolbar_html = $render_member_toolbar($member_links);

        $member_picker_enabled = $has_assigned_events;
        $member_picker_button_disabled_attr = $member_picker_enabled ? '' : ' disabled';
        $member_picker_button_html = '<button type="button" class="mj-animateur-dashboard__cta" data-role="open-member-picker"' . $member_picker_button_disabled_attr . '>' .
            '<span class="mj-animateur-dashboard__cta-icon" aria-hidden="true" data-icon="+"></span>' .
            '<span class="mj-animateur-dashboard__cta-label">' . esc_html__('Ajouter une réservation', 'mj-member') . '</span>' .
        '</button>';

        $member_picker_title_id = sanitize_html_class('mj-member-picker-title-' . wp_rand(1000, 9999));
        $quick_member_title_id = sanitize_html_class('mj-quick-member-title-' . wp_rand(1000, 9999));
        $quick_member_desc_id = sanitize_html_class('mj-quick-member-desc-' . wp_rand(1000, 9999));

        ob_start();
        ?>
        <div class="<?php echo esc_attr(implode(' ', array_unique($wrapper_classes))); ?>"<?php echo $wrapper_style_attr; ?> data-config="<?php echo $config_attr; ?>">
            <div class="mj-animateur-dashboard__header">
                <?php if ($settings['title'] !== '') : ?>
                    <h2 class="mj-animateur-dashboard__title"><?php echo esc_html($settings['title']); ?></h2>
                <?php endif; ?>
                <?php if ($settings['description'] !== '') : ?>
                    <p class="mj-animateur-dashboard__intro"><?php echo wp_kses_post($settings['description']); ?></p>
                <?php endif; ?>
            </div>

            <?php if (!$render_dashboard) : ?>
                <?php if ($member_toolbar_html !== '') : ?>
                    <?php echo $member_toolbar_html; ?>
                <?php endif; ?>
                <div class="mj-animateur-dashboard__empty"><?php esc_html_e('Aucun événement ne vous est assigné pour le moment.', 'mj-member'); ?></div>
                <?php if ($view_all_has_link) : ?>
                    <div class="mj-animateur-dashboard__empty-actions">
                        <a class="mj-animateur-dashboard__button mj-animateur-dashboard__button--view-all" href="<?php echo esc_url($view_all['url']); ?>"<?php echo $view_all_link_attr; ?><?php echo $view_all_button_attr; ?>>
                            <?php echo esc_html($view_all['label']); ?>
                        </a>
                    </div>
                <?php endif; ?>
            <?php else : ?>
                <?php if (!$has_assigned_events) : ?>
                    <div class="mj-animateur-dashboard__notice mj-animateur-dashboard__notice--no-assignment"><?php esc_html_e('Aucun événement ne vous est assigné pour le moment.', 'mj-member'); ?></div>
                <?php endif; ?>

                <?php if (!empty($pending_photo_items)) :
                    $animateur_redirect = function_exists('mj_member_get_current_url') ? mj_member_get_current_url() : home_url('/');
                ?>
                <section class="mj-animateur-dashboard__card mj-animateur-dashboard__card--photos">
                    <div class="mj-animateur-dashboard__card-header">
                        <h3 class="mj-animateur-dashboard__card-title"><?php esc_html_e('Photos à valider', 'mj-member'); ?></h3>
                        <span class="mj-animateur-dashboard__chip"><?php echo esc_html(count($pending_photo_items)); ?></span>
                    </div>
                    <ul class="mj-animateur-dashboard__photo-list">
                        <?php foreach ($pending_photo_items as $photo_item) :
                            $photo_nonce = wp_create_nonce('mj-member-review-photo-' . $photo_item['id']);
                            $thumb = $photo_item['thumb'] !== '' ? $photo_item['thumb'] : $photo_item['full'];
                        ?>
                        <li class="mj-animateur-dashboard__photo-item">
                            <?php if ($thumb !== '') : ?>
                                <a class="mj-animateur-dashboard__photo-thumb" href="<?php echo esc_url($photo_item['full']); ?>" target="_blank" rel="noopener">
                                    <img src="<?php echo esc_url($thumb); ?>" alt="<?php echo esc_attr($photo_item['event_title']); ?>" loading="lazy" />
                                </a>
                            <?php endif; ?>
                            <div class="mj-animateur-dashboard__photo-content">
                                <p class="mj-animateur-dashboard__photo-meta">
                                    <strong><?php echo esc_html($photo_item['event_title']); ?></strong>
                                    <?php if ($photo_item['event_link'] !== '') : ?>
                                        <a href="<?php echo esc_url($photo_item['event_link']); ?>" target="_blank" rel="noopener">
                                            <?php esc_html_e('Voir la fiche', 'mj-member'); ?>
                                        </a>
                                    <?php endif; ?>
                                </p>
                                <p class="mj-animateur-dashboard__photo-meta mj-animateur-dashboard__photo-meta--secondary">
                                    <?php echo esc_html($photo_item['member_label']); ?><?php if ($photo_item['submitted'] !== '') : ?> · <?php echo esc_html($photo_item['submitted']); ?><?php endif; ?>
                                </p>
                                <?php if ($photo_item['caption'] !== '') : ?>
                                    <p class="mj-animateur-dashboard__photo-caption"><?php echo esc_html($photo_item['caption']); ?></p>
                                <?php endif; ?>
                                <div class="mj-animateur-dashboard__photo-actions">
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                        <input type="hidden" name="action" value="mj_member_review_event_photo" />
                                        <input type="hidden" name="decision" value="approve" />
                                        <input type="hidden" name="photo_id" value="<?php echo esc_attr($photo_item['id']); ?>" />
                                        <input type="hidden" name="redirect_to" value="<?php echo esc_attr($animateur_redirect); ?>" />
                                        <input type="hidden" name="source" value="animateur" />
                                        <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($photo_nonce); ?>" />
                                        <button type="submit" class="mj-animateur-dashboard__photo-button mj-animateur-dashboard__photo-button--approve"><?php esc_html_e('Valider', 'mj-member'); ?></button>
                                    </form>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                        <input type="hidden" name="action" value="mj_member_review_event_photo" />
                                        <input type="hidden" name="decision" value="reject" />
                                        <input type="hidden" name="photo_id" value="<?php echo esc_attr($photo_item['id']); ?>" />
                                        <input type="hidden" name="redirect_to" value="<?php echo esc_attr($animateur_redirect); ?>" />
                                        <input type="hidden" name="source" value="animateur" />
                                        <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($photo_nonce); ?>" />
                                        <input type="text" name="reason" placeholder="<?php esc_attr_e('Motif', 'mj-member'); ?>" />
                                        <button type="submit" class="mj-animateur-dashboard__photo-button mj-animateur-dashboard__photo-button--reject"><?php esc_html_e('Refuser', 'mj-member'); ?></button>
                                    </form>
                                </div>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
                <?php endif; ?>

                <?php if ($show_event_filter || $show_occurrence_filter || $view_all_has_link || $view_all_is_toggle) : ?>
                    <div class="mj-animateur-dashboard__filters">
                        <?php if ($show_event_filter) : ?>
                            <?php
                            $tab_labels = array(
                                'all' => __('Tous', 'mj-member'),
                                'assigned' => __('Assignés à moi', 'mj-member'),
                                'upcoming' => __('À venir', 'mj-member'),
                                'past' => __('Passés', 'mj-member'),
                                'draft' => __('Brouillons', 'mj-member'),
                            );
                            ?>
                            <div class="mj-animateur-dashboard__filter mj-animateur-dashboard__filter--event">
                                <div class="mj-animateur-dashboard__tabs" data-role="event-tabs" data-default-filter="<?php echo esc_attr($default_filter); ?>">
                                    <?php foreach ($tab_labels as $tab_key => $tab_label) : ?>
                                        <?php $is_active_tab = $default_filter === $tab_key; ?>
                                        <button type="button" class="mj-animateur-dashboard__tab<?php echo $is_active_tab ? ' is-active' : ''; ?>" data-filter="<?php echo esc_attr($tab_key); ?>" aria-pressed="<?php echo $is_active_tab ? 'true' : 'false'; ?>">
                                            <span class="mj-animateur-dashboard__tab-label"><?php echo esc_html($tab_label); ?></span>
                                            <span class="mj-animateur-dashboard__tab-count" data-role="tab-count">0</span>
                                        </button>
                                    <?php endforeach; ?>
                                </div>

                                <div class="mj-animateur-dashboard__event-carousel" data-role="event-carousel">
                                    <button type="button" class="mj-animateur-dashboard__event-nav mj-animateur-dashboard__event-nav--prev" data-role="event-nav-prev" aria-label="<?php echo esc_attr($component_config['i18n']['eventNavPrev']); ?>"><span aria-hidden="true">&#9664;</span></button>
                                    <div class="mj-animateur-dashboard__event-track" data-role="event-track" aria-live="polite">
                                        <div class="mj-animateur-dashboard__event-empty" data-role="event-empty">
                                            <?php echo esc_html($component_config['i18n']['eventEmpty']); ?>
                                        </div>
                                    </div>
                                    <button type="button" class="mj-animateur-dashboard__event-nav mj-animateur-dashboard__event-nav--next" data-role="event-nav-next" aria-label="<?php echo esc_attr($component_config['i18n']['eventNavNext']); ?>"><span aria-hidden="true">&#9654;</span></button>
                                </div>

                                <select class="mj-animateur-dashboard__select mj-animateur-dashboard__select--event mj-animateur-dashboard__select--ghost" aria-label="<?php esc_attr_e('Événement', 'mj-member'); ?>">
                                    <?php foreach ($events as $event) : ?>
                                        <option value="<?php echo esc_attr($event['id']); ?>"><?php echo esc_html($event['title']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>

                        <?php if ($show_occurrence_filter) : ?>
                            <div class="mj-animateur-dashboard__select-wrapper mj-animateur-dashboard__select-wrapper--occurrence">
                                
                                <select class="mj-animateur-dashboard__select mj-animateur-dashboard__select--occurrence mj-animateur-dashboard__select--ghost" aria-label="<?php esc_attr_e('Occurrence', 'mj-member'); ?>">
                                    <?php if (!empty($occurrence_options)) : ?>
                                        <?php foreach ($occurrence_options as $occurrence) : ?>
                                            <option value="<?php echo esc_attr($occurrence['start']); ?>" <?php selected($occurrence['start'], $selected_occurrence); ?>>
                                                <?php echo esc_html($occurrence['label']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php else : ?>
                                        <option value="">
                                            <?php esc_html_e('Aucune occurrence disponible', 'mj-member'); ?>
                                        </option>
                                    <?php endif; ?>
                                </select>
                            </div>
                        <?php endif; ?>

                        <?php if ($view_all_has_link) : ?>
                            <div class="mj-animateur-dashboard__filter mj-animateur-dashboard__filter--view-all">
                                <a class="mj-animateur-dashboard__button mj-animateur-dashboard__button--view-all" href="<?php echo esc_url($view_all['url']); ?>"<?php echo $view_all_link_attr; ?><?php echo $view_all_button_attr; ?>>
                                    <?php echo esc_html($view_all['label']); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="mj-animateur-dashboard__body">
                    <div class="mj-animateur-dashboard__event-details" data-role="event-details" hidden>
                        <div class="mj-animateur-dashboard__event-details-header">
                            <div class="mj-animateur-dashboard__event-details-titles">
                                <h3 class="mj-animateur-dashboard__event-details-title" data-role="event-detail-title"></h3>
                                <div class="mj-animateur-dashboard__event-details-meta" data-role="event-detail-meta"></div>
                            </div>
                            <?php if ($show_attendance_actions) : ?>
                                <div class="mj-animateur-dashboard__summary" data-role="summary">
                                    <span class="mj-animateur-dashboard__total" data-role="participant-total"></span>
                                    <span class="mj-animateur-dashboard__counts" data-role="attendance-counts"></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="mj-animateur-dashboard__event-details-conditions" data-role="event-detail-conditions-wrapper" hidden>
                            <div class="mj-animateur-dashboard__event-details-conditions-title"><?php esc_html_e('Conditions de l\'événement', 'mj-member'); ?></div>
                            <ul class="mj-animateur-dashboard__event-details-conditions-list" data-role="event-detail-conditions"></ul>
                        </div>
                    </div>

                    <?php if ($show_occurrence_filter) : ?>
                        <div class="mj-animateur-dashboard__agenda" data-role="occurrence-agenda">
                            <div class="mj-animateur-dashboard__agenda-header">
                                <span class="mj-animateur-dashboard__agenda-title"><?php esc_html_e('Occurrences', 'mj-member'); ?></span>
                                <div class="mj-animateur-dashboard__agenda-controls">
                                    <button type="button" class="mj-animateur-dashboard__agenda-nav mj-animateur-dashboard__agenda-nav--prev" data-role="agenda-nav-prev" aria-label="<?php echo esc_attr($component_config['i18n']['occurrenceNavPrev']); ?>"><span aria-hidden="true">&#9664;</span></button>
                                    <button type="button" class="mj-animateur-dashboard__agenda-nav mj-animateur-dashboard__agenda-nav--next" data-role="agenda-nav-next" aria-label="<?php echo esc_attr($component_config['i18n']['occurrenceNavNext']); ?>"><span aria-hidden="true">&#9654;</span></button>
                                </div>
                            </div>
                            <div class="mj-animateur-dashboard__agenda-track" data-role="agenda-track" aria-live="polite">
                                <div class="mj-animateur-dashboard__agenda-empty" data-role="agenda-empty">
                                    <?php echo esc_html($component_config['i18n']['agendaEmpty']); ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php
                    $summary_actions_chunks = array($member_picker_button_html);
                    if ($member_toolbar_html !== '') {
                        $summary_actions_chunks[] = $member_toolbar_html;
                    }
                    $has_summary_actions = !empty(array_filter($summary_actions_chunks));
                    if ($has_summary_actions) :
                        ?>
                        <div class="mj-animateur-dashboard__summary-bar">
                            <?php if (!empty(array_filter($summary_actions_chunks))) : ?>
                                <div class="mj-animateur-dashboard__summary-actions">
                                    <?php echo implode('', array_filter($summary_actions_chunks)); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="mj-animateur-dashboard__table-wrapper">
                        <table class="mj-animateur-dashboard__table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Participant', 'mj-member'); ?></th>
                                    <th><?php esc_html_e('Présence', 'mj-member'); ?></th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                        <div class="mj-animateur-dashboard__no-data" data-role="no-participants" style="display:none;">
                            <?php esc_html_e('Aucun participant pour cette sélection.', 'mj-member'); ?>
                        </div>
                    </div>

                    <div class="mj-animateur-dashboard__unassigned" data-role="unassigned-notice" style="display:none;">
                        <?php echo esc_html($component_config['i18n']['notAssigned']); ?>
                    </div>

                    <?php if ($show_attendance_actions) : ?>
                        <div class="mj-animateur-dashboard__actions" data-role="actions">
                            <span class="mj-animateur-dashboard__feedback" data-role="feedback"></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($show_sms_block) : ?>
                        <div class="mj-animateur-dashboard__sms" data-role="sms">
                            <label for="<?php echo esc_attr($sms_field_id); ?>" class="mj-animateur-dashboard__sms-label">
                                <?php esc_html_e('Envoyer un SMS aux participants', 'mj-member'); ?>
                            </label>
                            <textarea id="<?php echo esc_attr($sms_field_id); ?>" class="mj-animateur-dashboard__sms-message" rows="3" placeholder="<?php esc_attr_e('Votre message (160 caractères conseillés)', 'mj-member'); ?>"></textarea>
                            <div class="mj-animateur-dashboard__sms-actions">
                                <button type="button" class="mj-animateur-dashboard__button mj-animateur-dashboard__button--sms" data-action="send-sms"<?php echo $button_style_attr; ?>>
                                    <?php esc_html_e('Envoyer le SMS', 'mj-member'); ?>
                                </button>
                                <span class="mj-animateur-dashboard__sms-feedback" data-role="sms-feedback"></span>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="mj-animateur-dashboard__member-picker" data-role="member-picker" hidden aria-hidden="true">
                <div class="mj-animateur-dashboard__member-picker-overlay" data-role="member-picker-backdrop" tabindex="-1"></div>
                <div class="mj-animateur-dashboard__member-picker-dialog" role="dialog" aria-modal="true" aria-labelledby="<?php echo esc_attr($member_picker_title_id); ?>">
                    <div class="mj-animateur-dashboard__member-picker-header">
                        <h3 class="mj-animateur-dashboard__member-picker-title" id="<?php echo esc_attr($member_picker_title_id); ?>"><?php echo esc_html($component_config['i18n']['memberPickerTitle']); ?></h3>
                        <button type="button" class="mj-animateur-dashboard__member-picker-close" data-role="member-picker-close" aria-label="<?php esc_attr_e('Fermer', 'mj-member'); ?>">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="mj-animateur-dashboard__member-picker-search">
                        <input type="search" class="mj-animateur-dashboard__member-picker-search-input" data-role="member-picker-search" placeholder="<?php echo esc_attr($component_config['i18n']['memberPickerSearchPlaceholder']); ?>" autocomplete="off">
                    </div>
                    <div class="mj-animateur-dashboard__member-picker-body">
                        <div class="mj-animateur-dashboard__member-picker-loading" data-role="member-picker-loading" hidden><?php echo esc_html($component_config['i18n']['memberPickerLoading']); ?></div>
                        <div class="mj-animateur-dashboard__member-picker-empty" data-role="member-picker-empty" hidden><?php echo esc_html($component_config['i18n']['memberPickerEmpty']); ?></div>
                        <ul class="mj-animateur-dashboard__member-picker-list" data-role="member-picker-list"></ul>
                        <button type="button" class="mj-animateur-dashboard__member-picker-load-more" data-role="member-picker-load-more" hidden><?php echo esc_html($component_config['i18n']['memberPickerLoadMore']); ?></button>
                    </div>
                    <div class="mj-animateur-dashboard__member-picker-feedback" data-role="member-picker-feedback" aria-live="polite"></div>
                    <div class="mj-animateur-dashboard__member-picker-footer">
                        <button type="button" class="mj-animateur-dashboard__member-picker-cancel" data-role="member-picker-cancel"><?php echo esc_html($component_config['i18n']['memberPickerCancel']); ?></button>
                        <button type="button" class="mj-animateur-dashboard__member-picker-confirm" data-role="member-picker-confirm" disabled><?php echo esc_html($component_config['i18n']['memberPickerConfirm']); ?></button>
                    </div>
                </div>
            </div>
            <?php if ($quick_create_enabled) : ?>
                <div class="mj-animateur-dashboard__quick-member" data-role="quick-member-modal" hidden aria-hidden="true">
                    <div class="mj-animateur-dashboard__quick-member-overlay" data-role="quick-member-backdrop"></div>
                    <div class="mj-animateur-dashboard__quick-member-dialog" role="dialog" aria-modal="true" aria-labelledby="<?php echo esc_attr($quick_member_title_id); ?>" aria-describedby="<?php echo esc_attr($quick_member_desc_id); ?>">
                        <button type="button" class="mj-animateur-dashboard__quick-member-close" data-role="quick-member-close" aria-label="<?php echo esc_attr($component_config['i18n']['quickCreateCloseLabel']); ?>">
                            <span aria-hidden="true">&times;</span>
                        </button>
                        <h3 class="mj-animateur-dashboard__quick-member-title" id="<?php echo esc_attr($quick_member_title_id); ?>"><?php echo esc_html($component_config['i18n']['quickCreateTitle']); ?></h3>
                        <p class="mj-animateur-dashboard__quick-member-description" id="<?php echo esc_attr($quick_member_desc_id); ?>"><?php echo esc_html($component_config['i18n']['quickCreateDescription']); ?></p>
                        <form class="mj-animateur-dashboard__quick-member-form" data-role="quick-member-form" novalidate>
                            <div class="mj-animateur-dashboard__quick-member-field" data-role="quick-member-field" data-field="first_name">
                                <label class="mj-animateur-dashboard__quick-member-label" for="mj-quick-member-first-name"><?php echo esc_html($component_config['i18n']['quickCreateFirstName']); ?></label>
                                <input type="text" id="mj-quick-member-first-name" name="first_name" class="mj-animateur-dashboard__quick-member-input" autocomplete="given-name" required>
                            </div>
                            <div class="mj-animateur-dashboard__quick-member-field" data-role="quick-member-field" data-field="last_name">
                                <label class="mj-animateur-dashboard__quick-member-label" for="mj-quick-member-last-name"><?php echo esc_html($component_config['i18n']['quickCreateLastName']); ?></label>
                                <input type="text" id="mj-quick-member-last-name" name="last_name" class="mj-animateur-dashboard__quick-member-input" autocomplete="family-name" required>
                            </div>
                            <div class="mj-animateur-dashboard__quick-member-field" data-role="quick-member-field" data-field="birth_date">
                                <label class="mj-animateur-dashboard__quick-member-label" for="mj-quick-member-birth-date"><?php echo esc_html($component_config['i18n']['quickCreateBirthDate']); ?></label>
                                <input type="date" id="mj-quick-member-birth-date" name="birth_date" class="mj-animateur-dashboard__quick-member-input" autocomplete="bday" required>
                                <p class="mj-animateur-dashboard__quick-member-hint"><?php echo esc_html($component_config['i18n']['quickCreateBirthDateHint']); ?></p>
                            </div>
                            <div class="mj-animateur-dashboard__quick-member-field" data-role="quick-member-field" data-field="email">
                                <label class="mj-animateur-dashboard__quick-member-label" for="mj-quick-member-email"><?php echo esc_html($component_config['i18n']['quickCreateEmail']); ?></label>
                                <input type="email" id="mj-quick-member-email" name="email" class="mj-animateur-dashboard__quick-member-input" autocomplete="email" placeholder="exemple@domaine.be">
                                <p class="mj-animateur-dashboard__quick-member-hint"><?php echo esc_html($component_config['i18n']['quickCreateEmailHint']); ?></p>
                            </div>
                            <div class="mj-animateur-dashboard__quick-member-feedback" data-role="quick-member-feedback" aria-live="polite"></div>
                            <div class="mj-animateur-dashboard__quick-member-actions">
                                <button type="button" class="mj-animateur-dashboard__quick-member-button mj-animateur-dashboard__quick-member-button--secondary" data-role="quick-member-cancel"><?php echo esc_html($component_config['i18n']['quickCreateCancel']); ?></button>
                                <button type="submit" class="mj-animateur-dashboard__quick-member-button mj-animateur-dashboard__quick-member-button--primary" data-role="quick-member-submit"><?php echo esc_html($component_config['i18n']['quickCreateSubmit']); ?></button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}

if (!function_exists('mj_member_ajax_animateur_get_event')) {
    function mj_member_ajax_animateur_get_event() {
        check_ajax_referer('mj_member_animateur', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Accès refusé.', 'mj-member')), 401);
        }

        $member = mj_member_get_current_animateur_member();
        if (!$member) {
            wp_send_json_error(array('message' => __('Seuls les animateurs peuvent accéder à ces données.', 'mj-member')), 403);
        }

        $event_id = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;
        if ($event_id <= 0) {
            wp_send_json_error(array('message' => __('Événement invalide.', 'mj-member')), 400);
        }

        if (!class_exists('MjEvents_CRUD') || !class_exists('MjEventAnimateurs')) {
            wp_send_json_error(array('message' => __('Module événements indisponible.', 'mj-member')), 500);
        }

        if (!MjEventAnimateurs::member_is_assigned($event_id, (int) $member->id)) {
            wp_send_json_error(array('message' => __('Vous ne gérez pas cet événement.', 'mj-member')), 403);
        }

        $event = MjEvents_CRUD::find($event_id);
        if (!$event) {
            wp_send_json_error(array('message' => __('Événement introuvable.', 'mj-member')), 404);
        }

        $snapshot = mj_member_prepare_animateur_event_data($event, (int) $member->id);
        if (empty($snapshot)) {
            wp_send_json_error(array('message' => __("Impossible de charger les données de l'événement.", 'mj-member')), 500);
        }

        wp_send_json_success(array('event' => $snapshot));
    }
}

if (!function_exists('mj_member_ajax_animateur_claim_event')) {
    function mj_member_ajax_animateur_claim_event() {
        check_ajax_referer('mj_member_animateur', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Accès refusé.', 'mj-member')), 401);
        }

        if (!class_exists('MjEventAnimateurs') || !class_exists('MjEvents_CRUD')) {
            wp_send_json_error(array('message' => __('Module événements indisponible.', 'mj-member')), 500);
        }

        $member = mj_member_get_current_animateur_member();
        if (!$member) {
            wp_send_json_error(array('message' => __('Cet espace est réservé aux animateurs.', 'mj-member')), 403);
        }

        $event_id = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;
        if ($event_id <= 0) {
            wp_send_json_error(array('message' => __('Événement invalide.', 'mj-member')), 400);
        }

        $event = MjEvents_CRUD::find($event_id);
        if (!$event) {
            wp_send_json_error(array('message' => __('Événement introuvable.', 'mj-member')), 404);
        }

        $member_id = (int) $member->id;
        if ($member_id <= 0) {
            wp_send_json_error(array('message' => __('Profil animateur invalide.', 'mj-member')), 400);
        }

        if (MjEventAnimateurs::member_is_assigned($event_id, $member_id)) {
            $snapshot = mj_member_prepare_animateur_event_data($event, $member_id);
            if (empty($snapshot)) {
                wp_send_json_error(array('message' => __('Impossible de charger cet événement.', 'mj-member')), 500);
            }
            $snapshot['isAssigned'] = true;
            wp_send_json_success(array('event' => $snapshot));
        }

        $assigned_ids = MjEventAnimateurs::get_ids_by_event($event_id);
        $assigned_ids[] = $member_id;
        $assigned_ids = array_values(array_unique(array_map('intval', $assigned_ids)));

        MjEventAnimateurs::sync_for_event($event_id, $assigned_ids);

        $event = MjEvents_CRUD::find($event_id);
        if (!$event) {
            wp_send_json_error(array('message' => __('Événement introuvable après attribution.', 'mj-member')), 404);
        }

        $snapshot = mj_member_prepare_animateur_event_data($event, $member_id);
        if (empty($snapshot)) {
            wp_send_json_error(array('message' => __('Impossible de charger cet événement après attribution.', 'mj-member')), 500);
        }
        $snapshot['isAssigned'] = true;

        wp_send_json_success(array('event' => $snapshot));
    }
}

if (!function_exists('mj_member_ajax_animateur_release_event')) {
    function mj_member_ajax_animateur_release_event() {
        check_ajax_referer('mj_member_animateur', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Accès refusé.', 'mj-member')), 401);
        }

        if (!class_exists('MjEventAnimateurs') || !class_exists('MjEvents_CRUD')) {
            wp_send_json_error(array('message' => __('Module événements indisponible.', 'mj-member')), 500);
        }

        $member = mj_member_get_current_animateur_member();
        if (!$member) {
            wp_send_json_error(array('message' => __('Cet espace est réservé aux animateurs.', 'mj-member')), 403);
        }

        $event_id = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;
        if ($event_id <= 0) {
            wp_send_json_error(array('message' => __('Événement invalide.', 'mj-member')), 400);
        }

        $event = MjEvents_CRUD::find($event_id);
        if (!$event) {
            wp_send_json_error(array('message' => __('Événement introuvable.', 'mj-member')), 404);
        }

        $member_id = (int) $member->id;
        if ($member_id <= 0) {
            wp_send_json_error(array('message' => __('Profil animateur invalide.', 'mj-member')), 400);
        }

        if (MjEventAnimateurs::member_is_assigned($event_id, $member_id)) {
            $assigned_ids = MjEventAnimateurs::get_ids_by_event($event_id);
            $remaining = array();
            if (is_array($assigned_ids)) {
                foreach ($assigned_ids as $assigned_id) {
                    $assigned_id = (int) $assigned_id;
                    if ($assigned_id > 0 && $assigned_id !== $member_id) {
                        $remaining[] = $assigned_id;
                    }
                }
            }

            MjEventAnimateurs::sync_for_event($event_id, $remaining);

            $event = MjEvents_CRUD::find($event_id);
            if (!$event) {
                wp_send_json_error(array('message' => __('Événement introuvable après désattribution.', 'mj-member')), 404);
            }
        }

        $snapshot = mj_member_prepare_animateur_event_data($event, $member_id);
        if (empty($snapshot)) {
            $snapshot = array(
                'id' => $event_id,
                'title' => isset($event->title) ? (string) $event->title : '',
            );
        }
        $snapshot['isAssigned'] = false;

        wp_send_json_success(array('event' => $snapshot));
    }
}

if (!function_exists('mj_member_ajax_animateur_save_attendance')) {
    function mj_member_ajax_animateur_save_attendance() {
        check_ajax_referer('mj_member_animateur', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Accès refusé.', 'mj-member')), 401);
        }

        if (!class_exists('MjEventAttendance') || !class_exists('MjEventAnimateurs')) {
            wp_send_json_error(array('message' => __('Module de présence indisponible.', 'mj-member')), 500);
        }

        $member = mj_member_get_current_animateur_member();
        if (!$member) {
            wp_send_json_error(array('message' => __('Seuls les animateurs peuvent enregistrer les présences.', 'mj-member')), 403);
        }

        $event_id = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;
        $occurrence_start_raw = isset($_POST['occurrence_start']) ? wp_unslash((string) $_POST['occurrence_start']) : '';
        $occurrence_start = MjEventAttendance::normalize_occurrence($occurrence_start_raw);

        if ($event_id <= 0 || $occurrence_start === '') {
            wp_send_json_error(array('message' => __('Paramètres de présence invalides.', 'mj-member')), 400);
        }

        if (!MjEventAnimateurs::member_is_assigned($event_id, (int) $member->id)) {
            wp_send_json_error(array('message' => __('Vous ne gérez pas cet événement.', 'mj-member')), 403);
        }

        $entries_raw = isset($_POST['entries']) ? wp_unslash($_POST['entries']) : array();
        if (is_string($entries_raw)) {
            $decoded = json_decode($entries_raw, true);
            $entries = is_array($decoded) ? $decoded : array();
        } elseif (is_array($entries_raw)) {
            $entries = $entries_raw;
        } else {
            $entries = array();
        }

        $normalized_entries = array();
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $member_id = isset($entry['member_id']) ? (int) $entry['member_id'] : 0;
            if ($member_id <= 0) {
                continue;
            }
            $status = isset($entry['status']) ? sanitize_key((string) $entry['status']) : '';
            $registration_id = isset($entry['registration_id']) ? (int) $entry['registration_id'] : 0;

            $normalized_entries[] = array(
                'member_id' => $member_id,
                'status' => $status,
                'registration_id' => $registration_id,
            );
        }

        if (empty($normalized_entries)) {
            wp_send_json_error(array('message' => __('Aucune mise à jour à enregistrer.', 'mj-member')), 400);
        }

        $result = MjEventAttendance::bulk_record($event_id, $occurrence_start, $normalized_entries, get_current_user_id());
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 500);
        }

        $counts_raw = MjEventAttendance::get_counts($event_id, $occurrence_start);
        $counts = array(
            'present' => isset($counts_raw['present']) ? (int) $counts_raw['present'] : 0,
            'absent' => isset($counts_raw['absent']) ? (int) $counts_raw['absent'] : 0,
            'pending' => isset($counts_raw['pending']) ? (int) $counts_raw['pending'] : 0,
        );

        $visible_statuses = mj_member_get_animateur_visible_registration_statuses();
        $visible_lookup = array();
        foreach ($visible_statuses as $visible_status) {
            $visible_lookup[$visible_status] = true;
        }

        $total_participants = 0;
        $registrations_snapshot = MjEventRegistrations::get_by_event($event_id);
        foreach ($registrations_snapshot as $registration_row) {
            $registration_status = isset($registration_row->statut) ? sanitize_key((string) $registration_row->statut) : '';
            if (isset($visible_lookup[$registration_status])) {
                $total_participants++;
            }
        }

        $pending_total = $total_participants - $counts['present'] - $counts['absent'];
        if ($pending_total < 0) {
            $pending_total = 0;
        }
        $counts['pending'] = $pending_total;

        $response_entries = array();
        foreach ($normalized_entries as $entry) {
            $status = class_exists('MjEventAttendance') ? MjEventAttendance::normalize_status($entry['status']) : sanitize_key($entry['status']);
            if ($status === '') {
                $status = 'none';
            }
            $response_entries[] = array(
                'member_id' => $entry['member_id'],
                'status' => $status,
            );
        }

        wp_send_json_success(array(
            'counts' => $counts,
            'entries' => $response_entries,
            'occurrence' => $occurrence_start,
        ));
    }
}

if (!function_exists('mj_member_ajax_animateur_toggle_cash_payment')) {
    function mj_member_ajax_animateur_toggle_cash_payment() {
        check_ajax_referer('mj_member_animateur', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Accès refusé.', 'mj-member')), 401);
        }

        if (!class_exists('MjEventRegistrations') || !class_exists('MjEventAnimateurs')) {
            wp_send_json_error(array('message' => __('Module finances indisponible.', 'mj-member')), 500);
        }

        $member = mj_member_get_current_animateur_member();
        if (!$member) {
            wp_send_json_error(array('message' => __('Seuls les animateurs peuvent modifier les paiements.', 'mj-member')), 403);
        }

        $event_id = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;
        $registration_id = isset($_POST['registration_id']) ? (int) $_POST['registration_id'] : 0;

        if ($event_id <= 0 || $registration_id <= 0) {
            wp_send_json_error(array('message' => __('Paramètres de paiement invalides.', 'mj-member')), 400);
        }

        if (!MjEventAnimateurs::member_is_assigned($event_id, (int) $member->id)) {
            wp_send_json_error(array('message' => __('Cet événement ne vous est pas attribué.', 'mj-member')), 403);
        }

        $registration = MjEventRegistrations::get($registration_id);
        if (!$registration || (int) $registration->event_id !== $event_id) {
            wp_send_json_error(array('message' => __('Inscription introuvable pour cet événement.', 'mj-member')), 404);
        }

        $payment_status = isset($registration->payment_status) ? sanitize_key((string) $registration->payment_status) : '';
        $payment_method = isset($registration->payment_method) ? sanitize_key((string) $registration->payment_method) : '';
        if ($payment_status === 'paid' && $payment_method !== '' && strpos($payment_method, 'stripe') === 0) {
            wp_send_json_error(array('message' => __('Ce paiement a été confirmé via Stripe et ne peut pas être annulé depuis cet écran.', 'mj-member')));
        }

        $user_id = get_current_user_id();
        $result = MjEventRegistrations::toggle_cash_payment($registration_id, $user_id);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array('payment' => $result));
    }
}

if (!function_exists('mj_member_ajax_animateur_generate_payment_link')) {
    function mj_member_ajax_animateur_generate_payment_link() {
        check_ajax_referer('mj_member_animateur_payment_link', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Accès refusé.', 'mj-member')), 401);
        }

        if (!class_exists('MjEventAnimateurs') || !class_exists('MjEventRegistrations') || !class_exists('MjEvents_CRUD') || !class_exists('MjPayments')) {
            wp_send_json_error(array('message' => __('Module de paiement indisponible.', 'mj-member')), 500);
        }

        $member = mj_member_get_current_animateur_member();
        if (!$member) {
            wp_send_json_error(array('message' => __('Cet espace est réservé aux animateurs.', 'mj-member')), 403);
        }

        $event_id = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;
        $registration_id = isset($_POST['registration_id']) ? (int) $_POST['registration_id'] : 0;
        if ($event_id <= 0 || $registration_id <= 0) {
            wp_send_json_error(array('message' => __('Paramètres de paiement invalides.', 'mj-member')), 400);
        }

        if (!MjEventAnimateurs::member_is_assigned($event_id, (int) $member->id)) {
            wp_send_json_error(array('message' => __('Cet événement ne vous est pas attribué.', 'mj-member')), 403);
        }

        $event = MjEvents_CRUD::find($event_id);
        if (!$event) {
            wp_send_json_error(array('message' => __('Événement introuvable.', 'mj-member')), 404);
        }

        $event_price = isset($event->prix) ? (float) $event->prix : 0.0;
        if ($event_price <= 0) {
            wp_send_json_error(array('message' => __('Cet événement ne nécessite pas de paiement.', 'mj-member')));
        }

        $registration = MjEventRegistrations::get($registration_id);
        if (!$registration || (int) $registration->event_id !== $event_id) {
            wp_send_json_error(array('message' => __('Inscription introuvable pour cet événement.', 'mj-member')), 404);
        }

        $registration_status = isset($registration->statut) ? sanitize_key((string) $registration->statut) : '';
        $blocked_statuses = array('annule', 'liste_attente', 'cancelled', 'waitlist');
        $blocked_statuses[] = sanitize_key((string) MjEventRegistrations::STATUS_CANCELLED);
        $blocked_statuses[] = sanitize_key((string) MjEventRegistrations::STATUS_WAITLIST);
        $blocked_statuses = array_values(array_unique(array_filter($blocked_statuses)));
        if (in_array($registration_status, $blocked_statuses, true)) {
            wp_send_json_error(array('message' => __('Cette inscription ne peut pas générer de paiement.', 'mj-member')));
        }

        $payment_status = isset($registration->payment_status) ? sanitize_key((string) $registration->payment_status) : '';
        if ($payment_status === 'paid') {
            wp_send_json_error(array('message' => __('Paiement déjà confirmé.', 'mj-member')));
        }

        $member_id = isset($registration->member_id) ? (int) $registration->member_id : 0;
        if ($member_id <= 0) {
            wp_send_json_error(array('message' => __('Membre introuvable pour cette inscription.', 'mj-member')));
        }

        $occurrence_scope = 'all';
        $occurrence_count = 1;
        $occurrence_list = array();

        if (class_exists('MjEventRegistrations') && method_exists('MjEventRegistrations', 'build_occurrence_summary')) {
            $occurrence_summary = MjEventRegistrations::build_occurrence_summary($registration);
            if (is_array($occurrence_summary)) {
                if (!empty($occurrence_summary['scope'])) {
                    $occurrence_scope = sanitize_key((string) $occurrence_summary['scope']);
                }
                if (!empty($occurrence_summary['count'])) {
                    $candidate_count = (int) $occurrence_summary['count'];
                    if ($occurrence_scope === 'custom' && $candidate_count > 0) {
                        $occurrence_count = $candidate_count;
                    }
                }
                if (!empty($occurrence_summary['occurrences']) && is_array($occurrence_summary['occurrences'])) {
                    foreach ($occurrence_summary['occurrences'] as $occurrence_entry) {
                        if (!is_array($occurrence_entry) || empty($occurrence_entry['start'])) {
                            continue;
                        }
                        $occurrence_list[] = sanitize_text_field((string) $occurrence_entry['start']);
                    }
                }
            }
        }

        $payment = MjPayments::create_stripe_payment(
            $member_id,
            $event_price,
            array(
                'context' => 'event',
                'event_id' => $event_id,
                'registration_id' => $registration_id,
                'event' => $event,
                'payer_id' => get_current_user_id(),
                'initiator' => 'animateur',
                'occurrence_mode' => $occurrence_scope,
                'occurrence_count' => $occurrence_count,
                'occurrence_list' => $occurrence_list,
            )
        );

        if (!$payment || empty($payment['checkout_url'])) {
            wp_send_json_error(array('message' => __('Impossible de générer le lien de paiement.', 'mj-member')), 500);
        }

        $default_total = (float) $event_price * max(1, $occurrence_count);
        $amount_label = isset($payment['amount_label']) && $payment['amount_label'] !== ''
            ? $payment['amount_label']
            : number_format_i18n(isset($payment['amount_raw']) ? (float) $payment['amount_raw'] : $default_total, 2);
        $message = sprintf(__('Lien généré. Montant : %s EUR.', 'mj-member'), $amount_label);

        wp_send_json_success(array(
            'checkout_url' => esc_url_raw($payment['checkout_url']),
            'qr_url' => isset($payment['qr_url']) ? esc_url_raw($payment['qr_url']) : '',
            'amount' => $amount_label,
            'message' => $message,
            'occurrence_count' => $occurrence_count,
            'occurrence_mode' => $occurrence_scope,
        ));
    }
}

if (!function_exists('mj_member_ajax_animateur_send_sms')) {
    function mj_member_ajax_animateur_send_sms() {
        check_ajax_referer('mj_member_animateur', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Accès refusé.', 'mj-member')), 401);
        }

        if (!class_exists('MjSms') || !class_exists('MjEventAnimateurs') || !class_exists('MjEventRegistrations')) {
            wp_send_json_error(array('message' => __('Module SMS indisponible.', 'mj-member')), 500);
        }

        $member = mj_member_get_current_animateur_member();
        if (!$member) {
            wp_send_json_error(array('message' => __('Seuls les animateurs peuvent envoyer ce SMS.', 'mj-member')), 403);
        }

        $event_id = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;
        if ($event_id <= 0) {
            wp_send_json_error(array('message' => __('Événement invalide.', 'mj-member')), 400);
        }

        if (!MjEventAnimateurs::member_is_assigned($event_id, (int) $member->id)) {
            wp_send_json_error(array('message' => __('Vous ne gérez pas cet événement.', 'mj-member')), 403);
        }

        $message_raw = isset($_POST['message']) ? wp_unslash((string) $_POST['message']) : '';
        $message = sanitize_textarea_field($message_raw);
        if ($message === '') {
            wp_send_json_error(array('message' => __("Veuillez saisir un message avant l'envoi.", 'mj-member')), 400);
        }

        $member_ids_raw = isset($_POST['member_ids']) ? wp_unslash($_POST['member_ids']) : array();
        if (is_string($member_ids_raw)) {
            $decoded = json_decode($member_ids_raw, true);
            $member_ids = is_array($decoded) ? $decoded : array();
        } elseif (is_array($member_ids_raw)) {
            $member_ids = $member_ids_raw;
        } else {
            $member_ids = array();
        }

        $member_filter = array();
        foreach ($member_ids as $member_id) {
            $member_id = (int) $member_id;
            if ($member_id > 0) {
                $member_filter[$member_id] = true;
            }
        }

        $registrations = MjEventRegistrations::get_by_event($event_id);
        if (empty($registrations)) {
            wp_send_json_error(array('message' => __('Aucun participant inscrit.', 'mj-member')));
        }

        $event = class_exists('MjEvents_CRUD') ? MjEvents_CRUD::find($event_id) : null;
        $context_event = array(
            'id' => $event ? (int) $event->id : $event_id,
            'title' => $event && !empty($event->title) ? sanitize_text_field((string) $event->title) : sprintf(__('Événement #%d', 'mj-member'), $event_id),
        );

        $success = 0;
        $failed = 0;
        $skipped = 0;
        $errors = array();

        foreach ($registrations as $registration) {
            if (!is_object($registration)) {
                continue;
            }

            $member_id = isset($registration->member_id) ? (int) $registration->member_id : 0;
            if ($member_id <= 0) {
                continue;
            }

            if (!empty($member_filter) && !isset($member_filter[$member_id])) {
                continue;
            }

            $sms_allowed = !empty($registration->sms_opt_in);
            $phone = isset($registration->phone) ? sanitize_text_field((string) $registration->phone) : '';

            if (!$sms_allowed || $phone === '') {
                $skipped++;
                continue;
            }

            $member_stub = (object) array(
                'id' => $member_id,
                'first_name' => isset($registration->first_name) ? (string) $registration->first_name : '',
                'last_name' => isset($registration->last_name) ? (string) $registration->last_name : '',
                'phone' => $phone,
                'email' => isset($registration->email) ? (string) $registration->email : '',
                'sms_opt_in' => 1,
            );

            $context = array(
                'event' => $context_event,
            );

            if (!empty($registration->guardian_phone) && !empty($registration->guardian_sms_opt_in)) {
                $context['extra_numbers'] = array(sanitize_text_field((string) $registration->guardian_phone));
            }

            $result = MjSms::send_to_member($member_stub, $message, $context);
            if (!empty($result['success'])) {
                $success++;
            } else {
                $failed++;
                if (!empty($result['error'])) {
                    $errors[] = sprintf('%s: %s', $member_stub->first_name . ' ' . $member_stub->last_name, $result['error']);
                }
            }
        }

        if ($success === 0 && $failed === 0) {
            wp_send_json_error(array('message' => __('Aucun participant ne peut recevoir ce SMS.', 'mj-member')));
        }

        $message_text = '';
        if ($success > 0 && $failed === 0) {
            $message_text = sprintf(_n('%d SMS envoyé.', '%d SMS envoyés.', $success, 'mj-member'), $success);
        } elseif ($success > 0 && $failed > 0) {
            $message_text = sprintf(__('SMS envoyé à %1$d participant(s), %2$d échec(s).', 'mj-member'), $success, $failed);
        } else {
            $message_text = __("Impossible d'envoyer le SMS.", 'mj-member');
        }

        wp_send_json_success(array(
            'summary' => array(
                'sent' => $success,
                'failed' => $failed,
                'skipped' => $skipped,
            ),
            'message' => $message_text,
            'errors' => $errors,
        ));
    }
}

if (!function_exists('mj_member_ajax_animateur_search_members')) {
    function mj_member_ajax_animateur_search_members() {
        check_ajax_referer('mj_member_animateur', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Accès refusé.', 'mj-member')), 401);
        }

        if (!class_exists('MjMembers_CRUD') || !class_exists('MjEventRegistrations') || !class_exists('MjEventAnimateurs')) {
            wp_send_json_error(array('message' => __('Données membres indisponibles.', 'mj-member')), 500);
        }

        $member = mj_member_get_current_animateur_member();
        if (!$member) {
            wp_send_json_error(array('message' => __('Cet espace est réservé aux animateurs.', 'mj-member')), 403);
        }

        $event_id = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;
        if ($event_id <= 0) {
            wp_send_json_error(array('message' => __('Événement invalide.', 'mj-member')), 400);
        }

        if (!MjEventAnimateurs::member_is_assigned($event_id, (int) $member->id)) {
            wp_send_json_error(array('message' => __('Cet événement ne vous est pas attribué.', 'mj-member')), 403);
        }

        $search = isset($_POST['search']) ? sanitize_text_field((string) wp_unslash($_POST['search'])) : '';
        $page = isset($_POST['page']) ? (int) $_POST['page'] : 1;
        if ($page <= 0) {
            $page = 1;
        }

        $default_per_page = (int) apply_filters('mj_member_animateur_member_picker_per_page', 20);
        if ($default_per_page <= 0) {
            $default_per_page = 20;
        }

        $requested_per_page = isset($_POST['per_page']) ? (int) $_POST['per_page'] : $default_per_page;
        $max_per_page = (int) apply_filters('mj_member_animateur_member_picker_max_per_page', 50, $member, $event_id);
        if ($max_per_page <= 0) {
            $max_per_page = 50;
        }

        $per_page = max(1, min($requested_per_page, $max_per_page));
        $offset = ($page - 1) * $per_page;

        $members_raw = MjMembers_CRUD::getAll($per_page + 1, $offset, 'last_name', 'ASC', $search, array());
        if (!is_array($members_raw)) {
            $members_raw = array();
        }

        $has_more = count($members_raw) > $per_page;
        if ($has_more) {
            $members_raw = array_slice($members_raw, 0, $per_page);
        }

        $event_row = class_exists('MjEvents_CRUD') ? MjEvents_CRUD::find($event_id) : null;
        $event_type = '';
        if ($event_row && isset($event_row->type)) {
            $event_type = sanitize_key((string) $event_row->type);
        }

        $current_occurrence = '';
        if (isset($_POST['occurrence'])) {
            $raw_occurrence = wp_unslash($_POST['occurrence']);
            if (is_string($raw_occurrence) && $raw_occurrence !== '') {
                if (class_exists('MjEventAttendance')) {
                    $normalized_occurrence = MjEventAttendance::normalize_occurrence($raw_occurrence);
                    if ($normalized_occurrence !== '') {
                        $current_occurrence = $normalized_occurrence;
                    }
                } else {
                    $current_occurrence = sanitize_text_field($raw_occurrence);
                }
            }
        }

        $existing_registrations = MjEventRegistrations::get_by_event($event_id);
        $assigned_map = array();
        if (!empty($existing_registrations)) {
            foreach ($existing_registrations as $registration_row) {
                if (empty($registration_row->member_id)) {
                    continue;
                }

                $member_key = (int) $registration_row->member_id;
                if ($member_key <= 0) {
                    continue;
                }

                $assignments = array(
                    'mode' => 'all',
                    'occurrences' => array(),
                );

                if (isset($registration_row->occurrence_assignments) && is_array($registration_row->occurrence_assignments)) {
                    $scope = $registration_row->occurrence_assignments;
                    $scope_mode = isset($scope['mode']) ? sanitize_key((string) $scope['mode']) : 'all';
                    $scope_occurrences = array();
                    if (!empty($scope['occurrences']) && is_array($scope['occurrences'])) {
                        foreach ($scope['occurrences'] as $scope_item) {
                            $normalized_scope = class_exists('MjEventAttendance') ? MjEventAttendance::normalize_occurrence($scope_item) : sanitize_text_field((string) $scope_item);
                            if ($normalized_scope !== '' && !in_array($normalized_scope, $scope_occurrences, true)) {
                                $scope_occurrences[] = $normalized_scope;
                            }
                        }
                    }

                    if ($scope_mode === 'custom' && !empty($scope_occurrences)) {
                        $assignments['mode'] = 'custom';
                        $assignments['occurrences'] = $scope_occurrences;
                    }
                }

                $covers_current = true;
                if ($event_type === MjEvents_CRUD::TYPE_ATELIER) {
                    if ($current_occurrence !== '' && class_exists('MjEventAttendance')) {
                        $covers_current = MjEventAttendance::assignments_cover_occurrence($assignments, $current_occurrence);
                    } elseif ($assignments['mode'] === 'custom') {
                        $covers_current = false;
                    }
                }

                $assigned_map[$member_key] = array(
                    'covers_current' => $covers_current,
                    'assignments' => $assignments,
                );
            }
        }
        $default_avatar = apply_filters('mj_member_animateur_dashboard_default_avatar', array('id' => 0, 'url' => ''), $event_row, 0);
        if (!is_array($default_avatar)) {
            $default_avatar = array('id' => 0, 'url' => '');
        }

        $age_min = 0;
        $age_max = 0;
        $allow_guardian_registration = 0;
        $capacity_total = 0;
        $capacity_waitlist = 0;
        $registration_deadline = '';
        if ($event_row) {
            if (isset($event_row->age_min)) {
                $age_min = (int) $event_row->age_min;
            }
            if (isset($event_row->age_max)) {
                $age_max = (int) $event_row->age_max;
            }
            if (isset($event_row->allow_guardian_registration)) {
                $allow_guardian_registration = (int) $event_row->allow_guardian_registration;
            }
            if (isset($event_row->capacity_total)) {
                $capacity_total = (int) $event_row->capacity_total;
            }
            if (isset($event_row->capacity_waitlist)) {
                $capacity_waitlist = (int) $event_row->capacity_waitlist;
            }
            if (!empty($event_row->date_fin_inscription) && $event_row->date_fin_inscription !== '0000-00-00 00:00:00') {
                $registration_deadline = sanitize_text_field((string) $event_row->date_fin_inscription);
            }
        }
        $age_reference_timestamp = !empty($event_row->date_debut) ? strtotime((string) $event_row->date_debut) : current_time('timestamp');
        if (!$age_reference_timestamp) {
            $age_reference_timestamp = current_time('timestamp');
        }

        $role_labels = MjMembers_CRUD::getRoleLabels();
        $payload_members = array();

        foreach ($members_raw as $member_row) {
            if (!is_object($member_row) || empty($member_row->id)) {
                continue;
            }

            $member_id = (int) $member_row->id;
            if ($member_id <= 0) {
                continue;
            }

            $first_name = isset($member_row->first_name) ? (string) $member_row->first_name : '';
            $last_name = isset($member_row->last_name) ? (string) $member_row->last_name : '';
            $nickname = isset($member_row->nickname) ? (string) $member_row->nickname : '';
            $display_name = trim(sprintf('%s %s', $first_name, $last_name));
            if ($display_name === '' && $nickname !== '') {
                $display_name = $nickname;
            }
            if ($display_name === '' && !empty($member_row->email)) {
                $display_name = sanitize_email((string) $member_row->email);
            }
            if ($display_name === '') {
                $display_name = sprintf(__('Membre #%d', 'mj-member'), $member_id);
            }

            $role_key = isset($member_row->role) ? sanitize_key((string) $member_row->role) : '';
            $role_label = ($role_key !== '' && isset($role_labels[$role_key])) ? $role_labels[$role_key] : ($role_key !== '' ? ucfirst($role_key) : '');

            $age = null;
            if (!empty($member_row->birth_date) && $member_row->birth_date !== '0000-00-00') {
                try {
                    $birth = new DateTime((string) $member_row->birth_date);
                    $today = new DateTime('today');
                    $age = (int) $birth->diff($today)->y;
                } catch (Exception $exception) {
                    $age = null;
                }
            }

            $avatar = mj_member_get_animateur_participant_avatar($member_row, null, $display_name, $default_avatar, null);

            $eligible = true;
            $ineligible_reasons = array();

            if ($allow_guardian_registration !== 1 && $role_key === MjMembers_CRUD::ROLE_TUTEUR) {
                $eligible = false;
                $ineligible_reasons[] = __('Rôle tuteur non autorisé pour cet événement.', 'mj-member');
            }

            if ($age_min > 0 || $age_max > 0) {
                if ($age === null && !empty($member_row->birth_date) && $member_row->birth_date !== '0000-00-00') {
                    // Age already attempted but failed; consider unknown.
                    $eligible = false;
                    $ineligible_reasons[] = __('Âge du membre indisponible.', 'mj-member');
                } elseif ($age === null) {
                    $eligible = false;
                    $ineligible_reasons[] = __('Âge du membre indisponible.', 'mj-member');
                } else {
                    if ($age_min > 0 && $age < $age_min) {
                        $eligible = false;
                        $ineligible_reasons[] = sprintf(__('Âge inférieur au minimum (%d ans).', 'mj-member'), $age_min);
                    }
                    if ($age_max > 0 && $age > $age_max) {
                        $eligible = false;
                        $ineligible_reasons[] = sprintf(__('Âge supérieur au maximum (%d ans).', 'mj-member'), $age_max);
                    }
                }
            }

            if (!empty($member_row->birth_date) && $member_row->birth_date !== '0000-00-00' && ($age_min > 0 || $age_max > 0)) {
                // Recalculate more accurately based on reference if needed.
                try {
                    $birth_ref = new DateTime((string) $member_row->birth_date);
                    $reference_date = new DateTime('@' . $age_reference_timestamp);
                    $timezone_string = function_exists('wp_timezone_string') ? (string) wp_timezone_string() : '';
                    if ($timezone_string === '') {
                        $timezone_string = 'UTC';
                    }
                    $reference_date->setTimezone(new DateTimeZone($timezone_string));
                    $calculated_age = (int) $birth_ref->diff($reference_date)->y;
                    if ($age_min > 0 && $calculated_age < $age_min) {
                        $eligible = false;
                        $ineligible_reasons[] = sprintf(__('Âge à la date de l\'événement inférieur au minimum (%d ans).', 'mj-member'), $age_min);
                    }
                    if ($age_max > 0 && $calculated_age > $age_max) {
                        $eligible = false;
                        $ineligible_reasons[] = sprintf(__('Âge à la date de l\'événement supérieur au maximum (%d ans).', 'mj-member'), $age_max);
                    }
                } catch (Exception $exception) {
                    // Ignore detailed calculation errors.
                }
            }

            $ineligible_reasons = array_values(array_filter(array_map('sanitize_text_field', $ineligible_reasons)));

            $payload_members[] = array(
                'id' => $member_id,
                'fullName' => $display_name,
                'eligible' => $eligible,
                'ineligibleReasons' => $ineligible_reasons,
                'firstName' => $first_name,
                'lastName' => $last_name,
                'role' => $role_key,
                'roleLabel' => $role_label,
                'age' => $age,
                'city' => isset($member_row->city) ? sanitize_text_field((string) $member_row->city) : '',
                'email' => isset($member_row->email) ? sanitize_email((string) $member_row->email) : '',
                'phone' => isset($member_row->phone) ? sanitize_text_field((string) $member_row->phone) : '',
                'avatar' => $avatar,
                'alreadyAssigned' => (isset($assigned_map[$member_id]) && !empty($assigned_map[$member_id]['covers_current'])) ? 1 : 0,
                'assignedOtherOccurrence' => ($event_type === MjEvents_CRUD::TYPE_ATELIER && isset($assigned_map[$member_id]) && empty($assigned_map[$member_id]['covers_current'])) ? 1 : 0,
                'occurrenceScope' => isset($assigned_map[$member_id]['assignments']) ? $assigned_map[$member_id]['assignments'] : array('mode' => 'all', 'occurrences' => array()),
            );
        }

        $total_count = MjMembers_CRUD::countAll($search, array());

        wp_send_json_success(array(
            'members' => $payload_members,
            'page' => $page,
            'perPage' => $per_page,
            'hasMore' => $has_more ? 1 : 0,
            'total' => $total_count,
        ));
    }
}

if (!function_exists('mj_member_ajax_animateur_remove_registration')) {
    function mj_member_ajax_animateur_remove_registration() {
        check_ajax_referer('mj_member_animateur', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Accès refusé.', 'mj-member')), 401);
        }

        if (!class_exists('MjEventRegistrations') || !class_exists('MjEventAnimateurs') || !class_exists('MjEvents_CRUD')) {
            wp_send_json_error(array('message' => __('Module événements indisponible.', 'mj-member')), 500);
        }

        $member = mj_member_get_current_animateur_member();
        $can_manage_members = function_exists('current_user_can') ? current_user_can(Config::capability()) : false;

        if (!$member && !$can_manage_members) {
            wp_send_json_error(array('message' => __('Cet espace est réservé aux animateurs.', 'mj-member')), 403);
        }

        $event_id = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;
        $registration_id = isset($_POST['registration_id']) ? (int) $_POST['registration_id'] : 0;

        if ($event_id <= 0 || $registration_id <= 0) {
            wp_send_json_error(array('message' => __('Paramètres invalides.', 'mj-member')), 400);
        }

        $registration = MjEventRegistrations::get($registration_id);
        if (!$registration) {
            wp_send_json_error(array('message' => __('Inscription introuvable.', 'mj-member')), 404);
        }

        if ((int) $registration->event_id !== $event_id) {
            wp_send_json_error(array('message' => __('Cette inscription ne correspond pas à cet événement.', 'mj-member')), 400);
        }

        $actor_member_id = $member && !empty($member->id) ? (int) $member->id : 0;
        $is_assigned = $actor_member_id > 0 && MjEventAnimateurs::member_is_assigned($event_id, $actor_member_id);

        if (!$is_assigned && !$can_manage_members) {
            wp_send_json_error(array('message' => __('Cet événement ne vous est pas attribué.', 'mj-member')), 403);
        }

        $delete_result = MjEventRegistrations::delete($registration_id);
        if (is_wp_error($delete_result)) {
            wp_send_json_error(array('message' => $delete_result->get_error_message()), 500);
        }

        $snapshot = array();
        $event_row = MjEvents_CRUD::find($event_id);
        if ($event_row && $actor_member_id > 0) {
            $snapshot = mj_member_prepare_animateur_event_data($event_row, $actor_member_id);
        }

        wp_send_json_success(array(
            'event' => $snapshot,
            'removed' => array(
                'registrationId' => $registration_id,
                'memberId' => isset($registration->member_id) ? (int) $registration->member_id : 0,
            ),
        ));
    }
}

if (!function_exists('mj_member_ajax_animateur_quick_create_member')) {
    function mj_member_ajax_animateur_quick_create_member() {
        check_ajax_referer('mj_member_animateur', 'nonce');

        if (!current_user_can(Config::capability())) {
            wp_send_json_error(
                array(
                    'message' => __('Vous n\'avez pas l\'autorisation de créer un membre.', 'mj-member'),
                    'code' => 'forbidden',
                ),
                403
            );
        }

        if (!class_exists('MjMembers_CRUD')) {
            wp_send_json_error(
                array(
                    'message' => __('Le module de gestion des membres est indisponible.', 'mj-member'),
                    'code' => 'missing_dependency',
                ),
                500
            );
        }

        $first_name = isset($_POST['first_name']) ? sanitize_text_field(wp_unslash((string) $_POST['first_name'])) : '';
        $last_name = isset($_POST['last_name']) ? sanitize_text_field(wp_unslash((string) $_POST['last_name'])) : '';
        $email_raw = isset($_POST['email']) ? wp_unslash((string) $_POST['email']) : '';
        $email = trim((string) $email_raw);
        $birth_raw = isset($_POST['birth_date']) ? wp_unslash((string) $_POST['birth_date']) : '';
        $birth_input = trim((string) $birth_raw);

        if ($first_name === '') {
            wp_send_json_error(array(
                'message' => __('Le prénom est obligatoire.', 'mj-member'),
                'code' => 'missing_first_name',
                'field' => 'first_name',
            ));
        }

        if ($last_name === '') {
            wp_send_json_error(array(
                'message' => __('Le nom est obligatoire.', 'mj-member'),
                'code' => 'missing_last_name',
                'field' => 'last_name',
            ));
        }

        $email_sanitized = '';
        if ($email !== '') {
            $email_sanitized = sanitize_email($email);
            if ($email_sanitized === '' || !is_email($email_sanitized)) {
                wp_send_json_error(array(
                    'message' => __('L\'adresse email n\'est pas valide.', 'mj-member'),
                    'code' => 'invalid_email',
                    'field' => 'email',
                ));
            }

            $existing_member = MjMembers_CRUD::getByEmail($email_sanitized);
            if ($existing_member) {
                wp_send_json_error(array(
                    'message' => __('Un membre existe déjà avec cette adresse email.', 'mj-member'),
                    'code' => 'duplicate_email',
                    'field' => 'email',
                ));
            }
        }

        if ($birth_input === '') {
            wp_send_json_error(array(
                'message' => __('La date de naissance est obligatoire.', 'mj-member'),
                'code' => 'missing_birth_date',
                'field' => 'birth_date',
            ));
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $birth_input)) {
            wp_send_json_error(array(
                'message' => __('La date de naissance est invalide.', 'mj-member'),
                'code' => 'invalid_birth_date',
                'field' => 'birth_date',
            ));
        }

        $birth_timestamp = strtotime($birth_input);
        if (!$birth_timestamp) {
            wp_send_json_error(array(
                'message' => __('La date de naissance est invalide.', 'mj-member'),
                'code' => 'invalid_birth_date',
                'field' => 'birth_date',
            ));
        }

        $birth_sanitized = gmdate('Y-m-d', $birth_timestamp);
        $today_gmt = gmdate('Y-m-d');
        if ($birth_sanitized > $today_gmt) {
            wp_send_json_error(array(
                'message' => __('La date de naissance est invalide.', 'mj-member'),
                'code' => 'invalid_birth_date_future',
                'field' => 'birth_date',
            ));
        }

        $filter_payload = array(
            'first_name' => $first_name,
            'last_name' => $last_name,
            'email' => $email_sanitized,
            'birth_date' => $birth_sanitized,
        );

        $default_role = MjMembers_CRUD::ROLE_JEUNE;
        $filtered_role = apply_filters('mj_member_quick_create_member_role', $default_role, $filter_payload, get_current_user_id());
        $allowed_roles = method_exists('MjMembers_CRUD', 'getAllowedRoles') ? MjMembers_CRUD::getAllowedRoles() : array($default_role);
        if (!in_array($filtered_role, $allowed_roles, true)) {
            $filtered_role = $default_role;
        }

        $create_data = array(
            'first_name' => $first_name,
            'last_name' => $last_name,
            'email' => $email_sanitized !== '' ? $email_sanitized : null,
            'role' => $filtered_role,
            'status' => MjMembers_CRUD::STATUS_ACTIVE,
            'birth_date' => $birth_sanitized,
        );

        $member_id = MjMembers_CRUD::create($create_data);
        if (!$member_id) {
            wp_send_json_error(array(
                'message' => __('Impossible de créer le membre.', 'mj-member'),
                'code' => 'creation_failed',
            ));
        }

        $member = MjMembers_CRUD::getById($member_id);
        if (!$member) {
            wp_send_json_error(array(
                'message' => __('Le membre a été créé mais les données sont indisponibles.', 'mj-member'),
                'code' => 'member_missing',
            ));
        }

        $email_sent = false;
        $email_error_message = '';
        if ($email_sanitized !== '') {
            $sync_result = mj_member_sync_member_user_account($member, array(
                'send_notification' => true,
                'return_error' => true,
            ));

            if (is_wp_error($sync_result)) {
                $email_error_message = $sync_result->get_error_message();
            } elseif ($sync_result) {
                $email_sent = true;
            }
        }

        $response_member = array(
            'id' => (int) $member_id,
            'first_name' => isset($member->first_name) ? sanitize_text_field((string) $member->first_name) : $first_name,
            'last_name' => isset($member->last_name) ? sanitize_text_field((string) $member->last_name) : $last_name,
            'email' => $email_sanitized,
            'birth_date' => $birth_sanitized,
            'role' => $filtered_role,
        );

        $message = $email_sent
            ? __('Membre créé et invitation envoyée par email.', 'mj-member')
            : __('Membre créé.', 'mj-member');

        if ($email_error_message !== '') {
            $message .= ' ' . $email_error_message;
        }

        do_action('mj_member_quick_member_created', (int) $member_id, $member, array(
            'email_sent' => $email_sent,
            'email_error' => $email_error_message,
        ));

        wp_send_json_success(array(
            'member' => $response_member,
            'emailSent' => $email_sent,
            'message' => $message,
        ));
    }
}

if (!function_exists('mj_member_ajax_animateur_add_members')) {
    function mj_member_ajax_animateur_add_members() {
        check_ajax_referer('mj_member_animateur', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Accès refusé.', 'mj-member')), 401);
        }

        if (!class_exists('MjEventRegistrations') || !class_exists('MjEventAnimateurs') || !class_exists('MjEvents_CRUD')) {
            wp_send_json_error(array('message' => __('Module événements indisponible.', 'mj-member')), 500);
        }

        $member = mj_member_get_current_animateur_member();
        if (!$member) {
            wp_send_json_error(array('message' => __('Cet espace est réservé aux animateurs.', 'mj-member')), 403);
        }

        $event_id = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;
        if ($event_id <= 0) {
            wp_send_json_error(array('message' => __('Événement invalide.', 'mj-member')), 400);
        }

        if (!MjEventAnimateurs::member_is_assigned($event_id, (int) $member->id)) {
            wp_send_json_error(array('message' => __('Cet événement ne vous est pas attribué.', 'mj-member')), 403);
        }

        $event_row = MjEvents_CRUD::find($event_id);
        if (!$event_row) {
            wp_send_json_error(array('message' => __('Événement introuvable.', 'mj-member')), 404);
        }

        $event_type = isset($event_row->type) ? sanitize_key((string) $event_row->type) : '';

        $available_occurrence_map = array();
        if (class_exists('MjEventSchedule')) {
            $occurrence_candidates = MjEventSchedule::get_occurrences($event_row, array(
                'max' => 200,
                'include_past' => true,
            ));

            if (is_array($occurrence_candidates)) {
                foreach ($occurrence_candidates as $occurrence_candidate) {
                    if (!is_array($occurrence_candidate)) {
                        continue;
                    }
                    $start_value = isset($occurrence_candidate['start']) ? (string) $occurrence_candidate['start'] : '';
                    $normalized_start = MjEventAttendance::normalize_occurrence($start_value);
                    if ($normalized_start !== '') {
                        $available_occurrence_map[$normalized_start] = true;
                    }
                }
            }
        }

        if (empty($available_occurrence_map)) {
            $fallback_start = !empty($event_row->date_debut) ? MjEventAttendance::normalize_occurrence($event_row->date_debut) : '';
            if ($fallback_start !== '') {
                $available_occurrence_map[$fallback_start] = true;
            }
        }

        $scope_payload = array();
        if (isset($_POST['occurrence_scope'])) {
            $raw_scope = wp_unslash($_POST['occurrence_scope']);
            if (is_string($raw_scope) && $raw_scope !== '') {
                $decoded_scope = json_decode($raw_scope, true);
                if (is_array($decoded_scope)) {
                    $scope_payload = $decoded_scope;
                }
            }
        }

        $scope_mode = 'all';
        $scope_occurrences = array();
        if ($event_type === MjEvents_CRUD::TYPE_ATELIER) {
            $candidate_map = array();
            if (!empty($scope_payload['occurrences']) && is_array($scope_payload['occurrences'])) {
                foreach ($scope_payload['occurrences'] as $candidate_occurrence) {
                    $normalized_candidate = MjEventAttendance::normalize_occurrence($candidate_occurrence);
                    if ($normalized_candidate !== '' && isset($available_occurrence_map[$normalized_candidate])) {
                        $candidate_map[$normalized_candidate] = true;
                    }
                }
            }

            if (empty($candidate_map)) {
                wp_send_json_error(array(
                    'message' => __('Sélectionnez une occurrence valide avant d\'ajouter un participant.', 'mj-member'),
                    'code' => 'missing_occurrence',
                ), 400);
            }

            $scope_mode = 'custom';
            $scope_occurrences = array_slice(array_values(array_keys($candidate_map)), 0, 1);
        }

        $member_ids_raw = isset($_POST['member_ids']) ? wp_unslash($_POST['member_ids']) : array();
        if (is_string($member_ids_raw)) {
            $decoded = json_decode($member_ids_raw, true);
            $member_ids_raw = is_array($decoded) ? $decoded : array();
        }
        if (!is_array($member_ids_raw)) {
            $member_ids_raw = array();
        }

        $member_ids = array();
        foreach ($member_ids_raw as $candidate) {
            $candidate_id = (int) $candidate;
            if ($candidate_id > 0) {
                $member_ids[$candidate_id] = true;
            }
        }
        $member_ids = array_values(array_keys($member_ids));

        if (empty($member_ids)) {
            wp_send_json_error(array('message' => __('Sélectionnez au moins un membre.', 'mj-member')), 400);
        }

        $existing_registrations = MjEventRegistrations::get_by_event($event_id);
        $assigned_map = array();
        $existing_registrations_by_member = array();
        if (!empty($existing_registrations)) {
            foreach ($existing_registrations as $registration_row) {
                if (empty($registration_row->member_id)) {
                    continue;
                }
                $member_key = (int) $registration_row->member_id;
                $assigned_map[$member_key] = true;
                $existing_registrations_by_member[$member_key] = $registration_row;
            }
        }

        $added = array();
        $already = array();
        $errors = array();

        foreach ($member_ids as $member_id) {
            if (isset($existing_registrations_by_member[$member_id])) {
                $registration_row = $existing_registrations_by_member[$member_id];

                if ($event_type === MjEvents_CRUD::TYPE_ATELIER && $scope_mode === 'custom') {
                    $merged_map = array();
                    if (isset($registration_row->occurrence_assignments) && is_array($registration_row->occurrence_assignments)) {
                        $current_scope = $registration_row->occurrence_assignments;
                        $current_mode = isset($current_scope['mode']) ? sanitize_key((string) $current_scope['mode']) : '';
                        if ($current_mode === 'custom' && !empty($current_scope['occurrences']) && is_array($current_scope['occurrences'])) {
                            foreach ($current_scope['occurrences'] as $existing_occurrence) {
                                $normalized_existing = MjEventAttendance::normalize_occurrence($existing_occurrence);
                                if ($normalized_existing !== '' && isset($available_occurrence_map[$normalized_existing])) {
                                    $merged_map[$normalized_existing] = true;
                                }
                            }
                        }
                    }

                    foreach ($scope_occurrences as $new_occurrence) {
                        $merged_map[$new_occurrence] = true;
                    }

                    $merged_occurrences = array_values(array_keys($merged_map));

                    if (!empty($merged_occurrences)) {
                        $assignment_result = MjEventAttendance::set_registration_assignments((int) $registration_row->id, array(
                            'mode' => 'custom',
                            'occurrences' => $merged_occurrences,
                        ));

                        if (is_wp_error($assignment_result)) {
                            $errors[] = array(
                                'memberId' => $member_id,
                                'code' => $assignment_result->get_error_code(),
                                'message' => $assignment_result->get_error_message(),
                            );
                        } else {
                            $added[] = $member_id;
                        }
                    } else {
                        $already[] = $member_id;
                    }
                } else {
                    $already[] = $member_id;
                }

                continue;
            }

            $result = MjEventRegistrations::create(
                $event_id,
                $member_id,
                array(
                    'allow_late_registration' => true,
                    'send_notifications' => false,
                )
            );
            if (is_wp_error($result)) {
                $errors[] = array(
                    'memberId' => $member_id,
                    'code' => $result->get_error_code(),
                    'message' => $result->get_error_message(),
                );
                continue;
            }

            $registration_id = (int) $result;
            $added[] = $member_id;

            if (class_exists('MjEventAttendance')) {
                $assignment_payload = array('mode' => $scope_mode);
                if ($scope_mode === 'custom') {
                    $assignment_payload['occurrences'] = $scope_occurrences;
                }

                $assignment_update = MjEventAttendance::set_registration_assignments($registration_id, $assignment_payload);
                if (is_wp_error($assignment_update)) {
                    $errors[] = array(
                        'memberId' => $member_id,
                        'code' => $assignment_update->get_error_code(),
                        'message' => $assignment_update->get_error_message(),
                    );
                }
            }
        }

        $snapshot = mj_member_prepare_animateur_event_data($event_row, (int) $member->id);

        $message_key = 'memberPickerSubmitSuccess';
        if (!empty($errors) && empty($added)) {
            $message_key = 'memberPickerSubmitError';
        } elseif (!empty($errors) && !empty($added)) {
            $message_key = 'memberPickerSubmitPartial';
        }

        $response = array(
            'added' => $added,
            'alreadyAssigned' => $already,
            'errors' => $errors,
            'event' => $snapshot,
            'messageKey' => $message_key,
        );

        wp_send_json_success($response);
    }
}

add_action('wp_ajax_mj_member_animateur_get_event', 'mj_member_ajax_animateur_get_event');
add_action('wp_ajax_mj_member_animateur_claim_event', 'mj_member_ajax_animateur_claim_event');
add_action('wp_ajax_mj_member_animateur_release_event', 'mj_member_ajax_animateur_release_event');
add_action('wp_ajax_mj_member_animateur_save_attendance', 'mj_member_ajax_animateur_save_attendance');
add_action('wp_ajax_mj_member_animateur_send_sms', 'mj_member_ajax_animateur_send_sms');
add_action('wp_ajax_mj_member_animateur_generate_payment_link', 'mj_member_ajax_animateur_generate_payment_link');
add_action('wp_ajax_mj_member_animateur_toggle_cash_payment', 'mj_member_ajax_animateur_toggle_cash_payment');
add_action('wp_ajax_mj_member_animateur_search_members', 'mj_member_ajax_animateur_search_members');
add_action('wp_ajax_mj_member_animateur_quick_create_member', 'mj_member_ajax_animateur_quick_create_member');
add_action('wp_ajax_mj_member_animateur_add_members', 'mj_member_ajax_animateur_add_members');
add_action('wp_ajax_mj_member_animateur_remove_registration', 'mj_member_ajax_animateur_remove_registration');
