<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('mj_member_register_events_widget_assets')) {
    function mj_member_register_events_widget_assets() {
        $version = defined('MJ_MEMBER_VERSION') ? MJ_MEMBER_VERSION : '1.0.0';

        wp_register_script(
            'mj-member-events-widget',
            MJ_MEMBER_URL . 'js/events-widget.js',
            array(),
            $version,
            true
        );
    }
    add_action('init', 'mj_member_register_events_widget_assets', 8);
}

if (!function_exists('mj_member_get_public_events')) {
    /**
     * Retrieve events for public/front-end displays.
     *
     * @param array<string,mixed> $args
     * @return array<int,array<string,mixed>>
     */
    function mj_member_get_public_events($args = array()) {
        $defaults = array(
            'statuses' => array(MjEvents_CRUD::STATUS_ACTIVE),
            'types' => array(),
            'limit' => 6,
            'order' => 'DESC',
            'orderby' => 'date_debut',
            'include_past' => false,
            'now' => current_time('mysql'),
        );

        $args = wp_parse_args($args, $defaults);

        $statuses = array();
        if (!empty($args['statuses']) && is_array($args['statuses'])) {
            foreach ($args['statuses'] as $status_candidate) {
                $status_candidate = sanitize_key($status_candidate);
                if ($status_candidate === '') {
                    continue;
                }
                $statuses[$status_candidate] = $status_candidate;
            }
        }
        if (empty($statuses)) {
            $statuses = array(MjEvents_CRUD::STATUS_ACTIVE);
        }

        $types = array();
        if (!empty($args['types']) && is_array($args['types'])) {
            foreach ($args['types'] as $type_candidate) {
                $type_candidate = sanitize_key($type_candidate);
                if ($type_candidate === '') {
                    continue;
                }
                $types[$type_candidate] = $type_candidate;
            }
        }

        $limit = isset($args['limit']) ? (int) $args['limit'] : 6;
        if ($limit <= 0) {
            $limit = 6;
        }
        $limit = min($limit, 100);

        $orderby_map = array(
            'date_debut' => 'events.date_debut',
            'date_fin' => 'events.date_fin',
            'created_at' => 'events.created_at',
            'updated_at' => 'events.updated_at',
        );
        $orderby_key = isset($args['orderby']) ? sanitize_key($args['orderby']) : 'date_debut';
        if (!isset($orderby_map[$orderby_key])) {
            $orderby_key = 'date_debut';
        }
        $order_sql = strtoupper(isset($args['order']) ? $args['order'] : 'DESC') === 'ASC' ? 'ASC' : 'DESC';

        global $wpdb;
        $events_table = mj_member_get_events_table_name();
        $locations_table = mj_member_get_event_locations_table_name();
        $has_locations = mj_member_table_exists($locations_table);

        $select_fields = array(
            'events.id',
            'events.title',
            'events.status',
            'events.type',
            'events.cover_id',
            'events.description',
            'events.date_debut',
            'events.date_fin',
            'events.date_fin_inscription',
            'events.prix',
            'events.created_at',
            'events.updated_at',
            'events.location_id',
        );

        $supports_guardian_toggle = function_exists('mj_member_column_exists') ? mj_member_column_exists($events_table, 'allow_guardian_registration') : false;
        if ($supports_guardian_toggle) {
            $select_fields[] = 'events.allow_guardian_registration';
        }

        $join_sql = '';
        if ($has_locations) {
            $select_fields[] = 'locations.name AS location_name';
            $select_fields[] = 'locations.city AS location_city';
            $select_fields[] = 'locations.address_line AS location_address';
            $select_fields[] = 'locations.postal_code AS location_postal_code';
            $select_fields[] = 'locations.country AS location_country';
            $select_fields[] = 'locations.latitude AS location_latitude';
            $select_fields[] = 'locations.longitude AS location_longitude';
            $select_fields[] = 'locations.map_query AS location_map_query';
            $select_fields[] = 'locations.notes AS location_notes';
            $select_fields[] = 'locations.cover_id AS location_cover_id';
            $join_sql = " LEFT JOIN {$locations_table} AS locations ON locations.id = events.location_id";
        }

        $where_fragments = array();
        $where_params = array();

        if (!empty($statuses)) {
            $placeholders = implode(',', array_fill(0, count($statuses), '%s'));
            $where_fragments[] = "events.status IN ({$placeholders})";
            foreach ($statuses as $status_value) {
                $where_params[] = $status_value;
            }
        }

        if (!empty($types)) {
            $placeholders = implode(',', array_fill(0, count($types), '%s'));
            $where_fragments[] = "events.type IN ({$placeholders})";
            foreach ($types as $type_value) {
                $where_params[] = $type_value;
            }
        }

        $now_value = isset($args['now']) ? sanitize_text_field($args['now']) : current_time('mysql');
        if (!$args['include_past']) {
            $where_fragments[] = 'events.date_fin >= %s';
            $where_params[] = $now_value;
        }

        $where_sql = '';
        if (!empty($where_fragments)) {
            $where_sql = 'WHERE ' . implode(" AND ", $where_fragments);
        }

        $query = "SELECT " . implode(', ', $select_fields) . " FROM {$events_table} AS events{$join_sql} {$where_sql} ORDER BY {$orderby_map[$orderby_key]} {$order_sql} LIMIT %d";
        $where_params[] = $limit;
        array_unshift($where_params, $query);
        $prepared = call_user_func_array(array($wpdb, 'prepare'), $where_params);
        $rows = $wpdb->get_results($prepared);

        if (empty($rows)) {
            return array();
        }

        $results = array();
        foreach ($rows as $row) {
            $cover_id = isset($row->cover_id) ? (int) $row->cover_id : 0;
            $cover_url = '';
            $cover_thumb_url = '';
            if ($cover_id > 0) {
                $image = wp_get_attachment_image_src($cover_id, 'large');
                if (!empty($image[0])) {
                    $cover_url = $image[0];
                }
                $thumb_image = wp_get_attachment_image_src($cover_id, 'medium');
                if (!empty($thumb_image[0])) {
                    $cover_thumb_url = $thumb_image[0];
                }
            }

            $description = isset($row->description) ? wp_kses_post($row->description) : '';
            $excerpt = $description !== '' ? wp_trim_words(wp_strip_all_tags($description), 28, '&hellip;') : '';

            $permalink = apply_filters('mj_member_event_permalink', '', $row);
            $location_label = '';
            if (!empty($row->location_name)) {
                $location_label = sanitize_text_field($row->location_name);
                if (!empty($row->location_city)) {
                    $location_label .= ' (' . sanitize_text_field($row->location_city) . ')';
                }
            }

            $location_address = '';
            $location_map_embed = '';
            $location_map_link = '';
            $location_notes = '';
            $location_cover_url = '';
            $location_cover_id = 0;
            if ($has_locations && !empty($row->location_id) && class_exists('MjEventLocations')) {
                $location_context = array(
                    'address_line' => isset($row->location_address) ? $row->location_address : '',
                    'postal_code' => isset($row->location_postal_code) ? $row->location_postal_code : '',
                    'city' => isset($row->location_city) ? $row->location_city : '',
                    'country' => isset($row->location_country) ? $row->location_country : '',
                    'latitude' => isset($row->location_latitude) ? $row->location_latitude : '',
                    'longitude' => isset($row->location_longitude) ? $row->location_longitude : '',
                    'map_query' => isset($row->location_map_query) ? $row->location_map_query : '',
                );

                $location_address_raw = MjEventLocations::format_address($location_context);
                if (!empty($location_address_raw)) {
                    $location_address = sanitize_text_field($location_address_raw);
                }

                $map_embed_candidate = MjEventLocations::build_map_embed_src($location_context);
                if (!empty($map_embed_candidate)) {
                    $location_map_embed = esc_url_raw($map_embed_candidate);
                    $location_map_link = $map_embed_candidate;
                    if (strpos($location_map_link, 'output=embed') !== false) {
                        $location_map_link = str_replace('&output=embed', '', $location_map_link);
                        $location_map_link = str_replace('?output=embed', '', $location_map_link);
                    }
                    $location_map_link = esc_url_raw($location_map_link);
                }

                if (!empty($row->location_notes)) {
                    $location_notes = sanitize_textarea_field($row->location_notes);
                }

                if (!empty($row->location_cover_id)) {
                    $location_cover_id = (int) $row->location_cover_id;
                    if ($location_cover_id > 0) {
                        $cover_image = wp_get_attachment_image_src($location_cover_id, 'thumbnail');
                        if (!empty($cover_image[0])) {
                            $location_cover_url = esc_url_raw($cover_image[0]);
                        }
                    }
                }
            }

            $results[] = array(
                'id' => (int) $row->id,
                'title' => sanitize_text_field($row->title),
                'status' => sanitize_key($row->status),
                'type' => sanitize_text_field($row->type),
                'start_date' => sanitize_text_field($row->date_debut),
                'end_date' => sanitize_text_field($row->date_fin),
                'deadline' => sanitize_text_field($row->date_fin_inscription),
                'price' => isset($row->prix) ? (float) $row->prix : 0.0,
                'cover_id' => $cover_id,
                'cover_url' => $cover_url,
                'cover_thumb' => $cover_thumb_url !== '' ? esc_url_raw($cover_thumb_url) : $cover_url,
                'excerpt' => $excerpt,
                'description' => $description,
                'permalink' => esc_url_raw($permalink),
                'location' => $location_label,
                'raw_location_name' => isset($row->location_name) ? sanitize_text_field($row->location_name) : '',
                'location_id' => isset($row->location_id) ? (int) $row->location_id : 0,
                'location_address' => $location_address,
                'location_map' => $location_map_embed,
                'location_map_link' => $location_map_link,
                'location_description' => $location_notes,
                'location_cover_id' => $location_cover_id,
                'location_cover' => $location_cover_url,
                'allow_guardian_registration' => ($supports_guardian_toggle && isset($row->allow_guardian_registration)) ? (int) $row->allow_guardian_registration : 0,
            );
        }

        return $results;
    }
}

if (!function_exists('mj_member_format_event_datetime_range')) {
    /**
     * Format a human readable datetime range for events.
     *
     * @param string $start
     * @param string $end
     * @return string
     */
    function mj_member_format_event_datetime_range($start, $end) {
        $start = trim((string) $start);
        $end = trim((string) $end);

        if ($start === '' && $end === '') {
            return '';
        }

        $start_ts = $start !== '' ? strtotime($start) : false;
        $end_ts = $end !== '' ? strtotime($end) : false;

        if (!$start_ts && !$end_ts) {
            return '';
        }

        if ($start_ts && $end_ts) {
            if (wp_date('d/m/Y', $start_ts) === wp_date('d/m/Y', $end_ts)) {
                return sprintf(
                    '%s %s - %s',
                    wp_date(get_option('date_format', 'd/m/Y'), $start_ts),
                    wp_date(get_option('time_format', 'H:i'), $start_ts),
                    wp_date(get_option('time_format', 'H:i'), $end_ts)
                );
            }

            return sprintf(
                '%s &rarr; %s',
                wp_date(get_option('date_format', 'd/m/Y H:i'), $start_ts),
                wp_date(get_option('date_format', 'd/m/Y H:i'), $end_ts)
            );
        }

        if ($start_ts) {
            return wp_date(get_option('date_format', 'd/m/Y H:i'), $start_ts);
        }

        return wp_date(get_option('date_format', 'd/m/Y H:i'), $end_ts);
    }
}

if (!function_exists('mj_member_ajax_register_event')) {
    function mj_member_ajax_register_event() {
        if (!wp_doing_ajax()) {
            return;
        }

        check_ajax_referer('mj-member-event-register', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(
                array('message' => __('Vous devez être connecté pour vous inscrire à cet événement.', 'mj-member')),
                401
            );
        }

        $event_id = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;
        $member_id = isset($_POST['member_id']) ? (int) $_POST['member_id'] : 0;

        if ($event_id <= 0 || $member_id <= 0) {
            wp_send_json_error(
                array('message' => __('Requête invalide. Veuillez réessayer.', 'mj-member')),
                400
            );
        }

        if (!class_exists('MjEvents_CRUD') || !class_exists('MjEventRegistrations') || !class_exists('MjMembers_CRUD')) {
            wp_send_json_error(
                array('message' => __('Le module événements est indisponible pour le moment.', 'mj-member')),
                500
            );
        }

        $event = MjEvents_CRUD::find($event_id);
        if (!$event) {
            wp_send_json_error(
                array('message' => __('Événement introuvable.', 'mj-member')),
                404
            );
        }

        $now = current_time('timestamp');
        $deadline_passed = false;
        if (!empty($event->date_fin_inscription) && $event->date_fin_inscription !== '0000-00-00 00:00:00') {
            $deadline_ts = strtotime($event->date_fin_inscription);
            if ($deadline_ts && $now > $deadline_ts) {
                $deadline_passed = true;
            }
        }

        if (!$deadline_passed && !empty($event->date_debut) && $event->date_debut !== '0000-00-00 00:00:00') {
            $start_ts = strtotime($event->date_debut);
            if ($start_ts && $now > $start_ts) {
                $deadline_passed = true;
            }
        }

        if ($deadline_passed) {
            wp_send_json_error(
                array('message' => __('Les inscriptions sont clôturées pour cet événement.', 'mj-member')),
                409
            );
        }

        $current_member = function_exists('mj_member_get_current_member') ? mj_member_get_current_member() : null;
        if (!$current_member || empty($current_member->id)) {
            wp_send_json_error(
                array('message' => __('Votre profil membre est introuvable. Contactez l’équipe MJ.', 'mj-member')),
                403
            );
        }

        $allowed_member_ids = array((int) $current_member->id);
        $guardian_id = 0;

        if (function_exists('mj_member_can_manage_children') && mj_member_can_manage_children($current_member)) {
            $guardian_id = (int) $current_member->id;
            if (function_exists('mj_member_get_guardian_children')) {
                $children = mj_member_get_guardian_children($current_member);
                if (!empty($children) && is_array($children)) {
                    foreach ($children as $child) {
                        if (!$child || !isset($child->id)) {
                            continue;
                        }
                        $allowed_member_ids[] = (int) $child->id;
                    }
                }
            }
        } elseif (!empty($current_member->guardian_id)) {
            $guardian_id = (int) $current_member->guardian_id;
        }

        if (!in_array($member_id, $allowed_member_ids, true)) {
            wp_send_json_error(
                array('message' => __('Vous ne pouvez pas inscrire ce participant.', 'mj-member')),
                403
            );
        }

        $participant = MjMembers_CRUD::getById($member_id);
        if (!$participant) {
            wp_send_json_error(
                array('message' => __('Profil membre introuvable.', 'mj-member')),
                404
            );
        }

        $existing_registration = MjEventRegistrations::get_existing($event_id, $member_id);
        if ($existing_registration && (!isset($existing_registration->statut) || $existing_registration->statut !== MjEventRegistrations::STATUS_CANCELLED)) {
            wp_send_json_error(
                array('message' => __('Ce participant est déjà inscrit à cet événement.', 'mj-member')),
                409
            );
        }

        $create_args = array();
        if ($guardian_id > 0) {
            $create_args['guardian_id'] = $guardian_id;
        }

        $note_value = isset($_POST['note']) ? sanitize_textarea_field(wp_unslash($_POST['note'])) : '';
        if ($note_value !== '') {
            if (function_exists('mb_substr')) {
                $note_value = mb_substr($note_value, 0, 400);
            } else {
                $note_value = substr($note_value, 0, 400);
            }
            $create_args['notes'] = $note_value;
        }

        $result = MjEventRegistrations::create($event_id, $member_id, $create_args);

        if (is_wp_error($result)) {
            wp_send_json_error(
                array('message' => $result->get_error_message()),
                400
            );
        }

        do_action('mj_member_event_registration_created', $result, $event_id, $member_id, $current_member);

        wp_send_json_success(
            array(
                'message' => __('Inscription enregistrée ! Nous reviendrons vers vous rapidement.', 'mj-member'),
                'registration_id' => (int) $result,
            )
        );
    }

    add_action('wp_ajax_mj_member_register_event', 'mj_member_ajax_register_event');
    add_action('wp_ajax_nopriv_mj_member_register_event', 'mj_member_ajax_register_event');
}

if (!function_exists('mj_member_ajax_unregister_event')) {
    function mj_member_ajax_unregister_event() {
        if (!wp_doing_ajax()) {
            return;
        }

        check_ajax_referer('mj-member-event-register', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(
                array('message' => __('Vous devez être connecté pour gérer vos inscriptions.', 'mj-member')),
                401
            );
        }

        $event_id = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;
        $member_id = isset($_POST['member_id']) ? (int) $_POST['member_id'] : 0;
        $registration_id = isset($_POST['registration_id']) ? (int) $_POST['registration_id'] : 0;

        if ($event_id <= 0 || $member_id <= 0) {
            wp_send_json_error(
                array('message' => __('Requête invalide. Veuillez réessayer.', 'mj-member')),
                400
            );
        }

        if (!class_exists('MjEvents_CRUD') || !class_exists('MjEventRegistrations') || !class_exists('MjMembers_CRUD')) {
            wp_send_json_error(
                array('message' => __('Le module événements est indisponible pour le moment.', 'mj-member')),
                500
            );
        }

        $event = MjEvents_CRUD::find($event_id);
        if (!$event) {
            wp_send_json_error(
                array('message' => __('Événement introuvable.', 'mj-member')),
                404
            );
        }

        $current_member = function_exists('mj_member_get_current_member') ? mj_member_get_current_member() : null;
        if (!$current_member || empty($current_member->id)) {
            wp_send_json_error(
                array('message' => __('Votre profil membre est introuvable. Contactez l’équipe MJ.', 'mj-member')),
                403
            );
        }

        $allowed_member_ids = array((int) $current_member->id);

        if (function_exists('mj_member_can_manage_children') && mj_member_can_manage_children($current_member)) {
            if (function_exists('mj_member_get_guardian_children')) {
                $children = mj_member_get_guardian_children($current_member);
                if (!empty($children) && is_array($children)) {
                    foreach ($children as $child) {
                        if (!$child || !isset($child->id)) {
                            continue;
                        }
                        $allowed_member_ids[] = (int) $child->id;
                    }
                }
            }
        } elseif (!empty($current_member->guardian_id)) {
            $allowed_member_ids[] = (int) $current_member->guardian_id;
        }

        if (!in_array($member_id, $allowed_member_ids, true)) {
            wp_send_json_error(
                array('message' => __('Vous ne pouvez pas gérer ce participant.', 'mj-member')),
                403
            );
        }

        $existing_registration = MjEventRegistrations::get_existing($event_id, $member_id);
        if (!$existing_registration || (isset($existing_registration->statut) && $existing_registration->statut === MjEventRegistrations::STATUS_CANCELLED)) {
            wp_send_json_error(
                array('message' => __('Aucune inscription active à annuler.', 'mj-member')),
                404
            );
        }

        if ($registration_id > 0 && (int) $existing_registration->id !== $registration_id) {
            wp_send_json_error(
                array('message' => __('Inscription introuvable.', 'mj-member')),
                404
            );
        }

        $update = MjEventRegistrations::update_status((int) $existing_registration->id, MjEventRegistrations::STATUS_CANCELLED);
        if (is_wp_error($update)) {
            wp_send_json_error(
                array('message' => $update->get_error_message()),
                500
            );
        }

        do_action('mj_member_event_registration_cancelled', (int) $existing_registration->id, $event_id, $member_id, $current_member);

        wp_send_json_success(
            array('message' => __('Inscription annulée.', 'mj-member'))
        );
    }

    add_action('wp_ajax_mj_member_unregister_event', 'mj_member_ajax_unregister_event');
}
