<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('MjEventPhotos')) {
    require_once plugin_dir_path(__FILE__) . 'classes/crud/MjEventPhotos.php';
}

if (!function_exists('mj_member_event_photos_extend_context')) {
    function mj_member_event_photos_extend_context($context, $slug) {
        if (!is_array($context) || empty($context['event']) || !class_exists('MjEventPhotos')) {
            return $context;
        }

        $event_data = $context['event'];
        $event_id = isset($event_data['id']) ? (int) $event_data['id'] : 0;
        if ($event_id <= 0) {
            return $context;
        }

        $event_record = isset($context['record']) ? $context['record'] : null;
        $current_member = function_exists('mj_member_get_current_member') ? mj_member_get_current_member() : null;
        $limit_per_member = (int) apply_filters('mj_member_event_photo_upload_limit', 3, $event_id, $current_member);
        if ($limit_per_member <= 0) {
            $limit_per_member = 3;
        }

        $can_upload = false;
        $member_upload_count = 0;
        $member_registration_id = 0;
        $upload_reason = '';

        if ($current_member && isset($current_member->id)) {
            $member_upload_count = MjEventPhotos::count_for_member($event_id, (int) $current_member->id);
            $member_registration = mj_member_event_photos_get_member_registration($event_id, $current_member);
            if ($member_registration) {
                $member_registration_id = (int) $member_registration->id;
            }

            $can_upload = mj_member_event_photos_member_can_upload($event_record, $member_registration, $member_upload_count, $limit_per_member);
            if (!$can_upload && $member_registration && $member_upload_count >= $limit_per_member) {
                /* Translators: %d is the maximum number of photos allowed per participant. */
                $upload_reason = sprintf(__('Limite atteinte : %d photo(s) déjà envoyée(s).', 'mj-member'), $limit_per_member);
            } elseif (!$member_registration) {
                $upload_reason = __('Cette fonctionnalité est réservée aux participants confirmés.', 'mj-member');
            }
        }

        $approved_photos = MjEventPhotos::get_for_event($event_id, array('status' => MjEventPhotos::STATUS_APPROVED));
        $photo_items = array();

        if (!empty($approved_photos)) {
            foreach ($approved_photos as $photo_row) {
                $attachment_id = isset($photo_row->attachment_id) ? (int) $photo_row->attachment_id : 0;
                if ($attachment_id <= 0) {
                    continue;
                }

                $image_src = wp_get_attachment_image_src($attachment_id, 'large');
                $thumb_src = wp_get_attachment_image_src($attachment_id, 'medium');

                $photo_items[] = array(
                    'id' => (int) $photo_row->id,
                    'attachment_id' => $attachment_id,
                    'url' => $image_src ? esc_url_raw($image_src[0]) : '',
                    'thumb' => $thumb_src ? esc_url_raw($thumb_src[0]) : '',
                    'caption' => !empty($photo_row->caption) ? esc_html($photo_row->caption) : '',
                    'member_id' => isset($photo_row->member_id) ? (int) $photo_row->member_id : 0,
                );
            }
        }

        $context['photos'] = array(
            'items' => $photo_items,
            'count' => count($photo_items),
            'has_items' => !empty($photo_items),
            'can_upload' => $can_upload,
            'upload_limit' => $limit_per_member,
            'member_uploaded' => $member_upload_count,
            'member_remaining' => max(0, $limit_per_member - $member_upload_count),
            'member_registration_id' => $member_registration_id,
            'reason' => $upload_reason,
        );

        return $context;
    }
    add_filter('mj_member_event_page_context', 'mj_member_event_photos_extend_context', 20, 2);
}

if (!function_exists('mj_member_event_photos_member_can_upload')) {
    function mj_member_event_photos_member_can_upload($event_record, $registration, $current_count, $limit) {
        if (!$registration || !is_object($registration)) {
            return false;
        }

        $status = isset($registration->statut) ? sanitize_key((string) $registration->statut) : '';
        $allowed_statuses = array(
            MjEventRegistrations::STATUS_CONFIRMED,
            MjEventRegistrations::STATUS_PENDING,
        );
        if (!in_array($status, $allowed_statuses, true)) {
            return false;
        }

        if ($current_count >= $limit) {
            return false;
        }

        if (!$event_record) {
            return true;
        }

        $start = isset($event_record->date_debut) ? strtotime((string) $event_record->date_debut) : 0;
        if ($start && $start > current_time('timestamp')) {
            return false;
        }

        return true;
    }
}

if (!function_exists('mj_member_event_photos_get_member_registration')) {
    function mj_member_event_photos_get_member_registration($event_id, $member) {
        if (!class_exists('MjEventRegistrations')) {
            return null;
        }

        $member_id = isset($member->id) ? (int) $member->id : 0;
        if ($member_id <= 0) {
            return null;
        }

        $registration = MjEventRegistrations::get_existing($event_id, $member_id);
        if ($registration && isset($registration->statut)) {
            $status = sanitize_key((string) $registration->statut);
            $allowed_statuses = array(
                MjEventRegistrations::STATUS_CONFIRMED,
                MjEventRegistrations::STATUS_PENDING,
            );
            if (in_array($status, $allowed_statuses, true)) {
                return $registration;
            }
        }

        return null;
    }
}

if (!function_exists('mj_member_event_photos_submission_handler')) {
    function mj_member_event_photos_submission_handler() {
        $redirect = isset($_POST['redirect_to']) ? esc_url_raw($_POST['redirect_to']) : home_url('/');
        $redirect = $redirect !== '' ? $redirect : home_url('/');

        $redirect_with_notice = function ($code) use ($redirect) {
            $target = add_query_arg('mj_event_photo', urlencode($code), $redirect);
            wp_safe_redirect($target);
            exit;
        };

        if (!isset($_POST['mj_event_photo_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mj_event_photo_nonce'])), 'mj-member-event-photo')) {
            $redirect_with_notice('nonce');
        }

        if (!is_user_logged_in()) {
            $redirect_with_notice('login');
        }

        $event_id = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;
        $caption = isset($_POST['photo_caption']) ? sanitize_text_field(wp_unslash($_POST['photo_caption'])) : '';

        if ($event_id <= 0 || empty($_FILES['event_photo_file'])) {
            $redirect_with_notice('invalid');
        }

        if (!class_exists('MjEventPhotos') || !class_exists('MjEventRegistrations') || !class_exists('MjEvents_CRUD')) {
            $redirect_with_notice('unavailable');
        }

        $current_member = function_exists('mj_member_get_current_member') ? mj_member_get_current_member() : null;
        if (!$current_member || empty($current_member->id)) {
            $redirect_with_notice('profile');
        }

        $event = MjEvents_CRUD::find($event_id);
        if (!$event) {
            $redirect_with_notice('missing');
        }

        $limit = (int) apply_filters('mj_member_event_photo_upload_limit', 3, $event_id, $current_member);
        if ($limit <= 0) {
            $limit = 3;
        }

        $current_count = MjEventPhotos::count_for_member($event_id, (int) $current_member->id);
        if ($current_count >= $limit) {
            $redirect_with_notice('limit');
        }

        $registration = mj_member_event_photos_get_member_registration($event_id, $current_member);
        if (!$registration) {
            $redirect_with_notice('not_registered');
        }

        if (!isset($_FILES['event_photo_file']) || !is_array($_FILES['event_photo_file'])) {
            $redirect_with_notice('invalid');
        }

        $file = $_FILES['event_photo_file'];
        if (!empty($file['error'])) {
            $redirect_with_notice('upload_error');
        }

        $permitted_types = array('jpg', 'jpeg', 'png', 'gif', 'webp', 'heic');
        $type = wp_check_filetype_and_ext($file['tmp_name'], $file['name']);
        if (empty($type['ext']) || !in_array(strtolower($type['ext']), $permitted_types, true)) {
            $redirect_with_notice('type');
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $upload_filter = function ($dirs) {
            $subdir = '/mj-member/event-photos';
            $dirs['subdir'] .= $subdir;
            $dirs['path'] = $dirs['basedir'] . $dirs['subdir'];
            $dirs['url'] = $dirs['baseurl'] . $dirs['subdir'];
            return $dirs;
        };

        add_filter('upload_dir', $upload_filter);
        add_filter('user_has_cap', 'mj_member_temp_allow_upload_cap', 10, 3);

        $attachment_id = media_handle_upload('event_photo_file', 0, array(
            'post_title' => sprintf(__('Photo %1$s – %2$s', 'mj-member'), sanitize_text_field($event->title), mj_member_event_photos_format_member_name($current_member)),
            'post_content' => '',
            'post_excerpt' => $caption,
        ));

        remove_filter('user_has_cap', 'mj_member_temp_allow_upload_cap', 10);
        remove_filter('upload_dir', $upload_filter);

        if (is_wp_error($attachment_id)) {
            $redirect_with_notice('upload_error');
        }

        $insert = MjEventPhotos::create(array(
            'event_id' => $event_id,
            'member_id' => (int) $current_member->id,
            'registration_id' => isset($registration->id) ? (int) $registration->id : 0,
            'attachment_id' => (int) $attachment_id,
            'caption' => $caption,
            'status' => MjEventPhotos::STATUS_PENDING,
        ));

        if (is_wp_error($insert)) {
            wp_delete_attachment($attachment_id, true);
            $redirect_with_notice('store');
        }

        if ($caption !== '') {
            wp_update_post(array(
                'ID' => (int) $attachment_id,
                'post_excerpt' => $caption,
            ));
        }

        $redirect_with_notice('success');
    }

    add_action('admin_post_mj_member_submit_event_photo', 'mj_member_event_photos_submission_handler');
    add_action('admin_post_nopriv_mj_member_submit_event_photo', 'mj_member_event_photos_submission_handler');
}

if (!function_exists('mj_member_event_photos_format_member_name')) {
    function mj_member_event_photos_format_member_name($member) {
        if (!$member) {
            return __('Participant', 'mj-member');
        }

        $parts = array();
        if (!empty($member->first_name)) {
            $parts[] = sanitize_text_field($member->first_name);
        }
        if (!empty($member->last_name)) {
            $parts[] = sanitize_text_field($member->last_name);
        }

        if (!empty($parts)) {
            return trim(implode(' ', $parts));
        }

        return isset($member->email) ? sanitize_text_field($member->email) : __('Participant', 'mj-member');
    }
}