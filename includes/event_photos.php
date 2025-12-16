<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('MjEventPhotos')) {
    require_once plugin_dir_path(__FILE__) . 'classes/crud/MjEventPhotos.php';
}

if (!function_exists('mj_member_event_photos_get_image_size_map')) {
    function mj_member_event_photos_get_image_size_map() {
        return array(
            'thumb' => 'mj-member-event-photo-thumb',
            'display' => 'mj-member-event-photo-large',
        );
    }
}

if (!function_exists('mj_member_event_photos_register_image_sizes')) {
    function mj_member_event_photos_register_image_sizes() {
        $sizes = mj_member_event_photos_get_image_size_map();

        add_image_size($sizes['thumb'], 480, 360, true);
        add_image_size($sizes['display'], 1600, 1200, false);
    }

    add_action('after_setup_theme', 'mj_member_event_photos_register_image_sizes');
}

if (!function_exists('mj_member_event_photos_filter_library_sizes')) {
    function mj_member_event_photos_filter_library_sizes($sizes) {
        $map = mj_member_event_photos_get_image_size_map();
        $sizes[$map['thumb']] = __('Vignette souvenir (MJ)', 'mj-member');
        $sizes[$map['display']] = __('Photo événement (MJ)', 'mj-member');

        return $sizes;
    }

    add_filter('image_size_names_choose', 'mj_member_event_photos_filter_library_sizes');
}

if (!function_exists('mj_member_event_photos_is_staff_member')) {
    /**
     * Détermine si le membre bénéficie des droits élargis sur les photos d'événement.
     *
     * @param object|null $member
     * @return bool
     */
    function mj_member_event_photos_is_staff_member($member) {
        if (!$member || !is_object($member)) {
            return false;
        }

        $role = isset($member->role) ? sanitize_key((string) $member->role) : '';
        if ($role === '') {
            return false;
        }

        $animateur_role = 'animateur';
        $coordinateur_role = 'coordinateur';

        if (class_exists('MjMembers')) {
            $animateur_role = sanitize_key((string) MjMembers::ROLE_ANIMATEUR);
            $coordinateur_role = sanitize_key((string) MjMembers::ROLE_COORDINATEUR);
        }

        $staff_roles = apply_filters('mj_member_event_photo_staff_roles', array($animateur_role, $coordinateur_role));
        if (!is_array($staff_roles)) {
            $staff_roles = array($animateur_role, $coordinateur_role);
        }

        $staff_roles = array_map('sanitize_key', $staff_roles);

        return in_array($role, $staff_roles, true);
    }
}

if (!function_exists('mj_member_event_photos_get_attachment_sources')) {
    function mj_member_event_photos_get_attachment_sources($attachment_id) {
        $attachment_id = (int) $attachment_id;
        if ($attachment_id <= 0) {
            return array(
                'thumb' => '',
                'display' => '',
                'full' => '',
            );
        }

        $map = mj_member_event_photos_get_image_size_map();

        $full = wp_get_attachment_image_src($attachment_id, 'full');
        $display = wp_get_attachment_image_src($attachment_id, $map['display']);
        $thumb = wp_get_attachment_image_src($attachment_id, $map['thumb']);

        if (!$display) {
            $display = wp_get_attachment_image_src($attachment_id, 'large');
        }

        if (!$thumb) {
            $thumb = wp_get_attachment_image_src($attachment_id, 'medium_large');
        }
        if (!$thumb) {
            $thumb = wp_get_attachment_image_src($attachment_id, 'medium');
        }

        $full_url = $full ? esc_url_raw($full[0]) : '';
        $display_url = $display ? esc_url_raw($display[0]) : $full_url;
        $thumb_url = $thumb ? esc_url_raw($thumb[0]) : $display_url;

        return array(
            'thumb' => $thumb_url,
            'display' => $display_url,
            'full' => $full_url !== '' ? $full_url : $display_url,
        );
    }
}

if (!function_exists('mj_member_event_photos_optimize_attachment')) {
    function mj_member_event_photos_optimize_attachment($attachment_id) {
        $attachment_id = (int) $attachment_id;
        if ($attachment_id <= 0) {
            return;
        }

        $mime = get_post_mime_type($attachment_id);
        if (!is_string($mime) || strpos($mime, 'image/') !== 0) {
            return;
        }

        $file_path = get_attached_file($attachment_id);
        if (!$file_path || !file_exists($file_path)) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';

        $max_width = (int) apply_filters('mj_member_event_photo_max_width', 1920, $attachment_id, $mime);
        $max_height = (int) apply_filters('mj_member_event_photo_max_height', 1920, $attachment_id, $mime);
        $quality = (int) apply_filters('mj_member_event_photo_quality', 85, $attachment_id, $mime);

        $quality_mimes = array('image/jpeg', 'image/jpg', 'image/webp');
        $quality_applicable = in_array($mime, $quality_mimes, true) && $quality > 0 && $quality < 100;

        $editor = wp_get_image_editor($file_path);
        $should_save = false;

        if (!is_wp_error($editor)) {
            $size = $editor->get_size();
            $width = isset($size['width']) ? (int) $size['width'] : 0;
            $height = isset($size['height']) ? (int) $size['height'] : 0;

            $needs_resize = false;
            if ($max_width > 0 && $width > $max_width) {
                $needs_resize = true;
            }
            if ($max_height > 0 && $height > $max_height) {
                $needs_resize = true;
            }

            if ($needs_resize) {
                $editor->resize($max_width > 0 ? $max_width : null, $max_height > 0 ? $max_height : null, false);
                $should_save = true;
            }

            if ($quality_applicable && method_exists($editor, 'set_quality')) {
                $editor->set_quality($quality);
                $should_save = true;
            }

            if ($should_save) {
                $saved = $editor->save($file_path);
                if (!is_wp_error($saved) && isset($saved['path']) && $saved['path'] !== '' && $saved['path'] !== $file_path) {
                    update_attached_file($attachment_id, $saved['path']);
                    $file_path = $saved['path'];
                }
            }
        }

        $metadata = wp_get_attachment_metadata($attachment_id);
        $size_map = mj_member_event_photos_get_image_size_map();
        $needs_regenerate = !is_array($metadata)
            || !isset($metadata['sizes'][$size_map['thumb']])
            || !isset($metadata['sizes'][$size_map['display']])
            || $should_save;

        if ($needs_regenerate) {
            $generated = wp_generate_attachment_metadata($attachment_id, $file_path);
            if (!is_wp_error($generated) && !empty($generated)) {
                wp_update_attachment_metadata($attachment_id, $generated);
            }
        }
    }
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
        $is_staff_member = mj_member_event_photos_is_staff_member($current_member);
        $limit_per_member = $is_staff_member ? 0 : (int) apply_filters('mj_member_event_photo_upload_limit', 3, $event_id, $current_member);
        if (!$is_staff_member && $limit_per_member <= 0) {
            $limit_per_member = 3;
        }

        $can_upload = false;
        $member_upload_count = 0;
        $member_registration_id = 0;
        $upload_reason = '';
        $member_registration = null;

        if ($current_member && isset($current_member->id)) {
            $member_upload_count = MjEventPhotos::count_for_member($event_id, (int) $current_member->id);
            $member_registration = mj_member_event_photos_get_member_registration($event_id, $current_member);
            if ($member_registration) {
                $member_registration_id = (int) $member_registration->id;
            }

            $can_upload = mj_member_event_photos_member_can_upload($event_record, $member_registration, $member_upload_count, $limit_per_member, $current_member);
            if (!$can_upload && !$is_staff_member) {
                if ($member_registration && $limit_per_member > 0 && $member_upload_count >= $limit_per_member) {
                    /* Translators: %d is the maximum number of photos allowed per participant. */
                    $upload_reason = sprintf(__('Limite atteinte : %d photo(s) déjà envoyée(s).', 'mj-member'), $limit_per_member);
                } elseif (!$member_registration) {
                    $upload_reason = __('Cette fonctionnalité est réservée aux participants confirmés.', 'mj-member');
                }
            }
        }

        $approved_photos = MjEventPhotos::get_for_event($event_id, array('status' => MjEventPhotos::STATUS_APPROVED));
        $photo_items = array();
        $member_pending_items = array();
        $member_pending_count = 0;

        if (!empty($approved_photos)) {
            foreach ($approved_photos as $photo_row) {
                $attachment_id = isset($photo_row->attachment_id) ? (int) $photo_row->attachment_id : 0;
                if ($attachment_id <= 0) {
                    continue;
                }

                $sources = mj_member_event_photos_get_attachment_sources($attachment_id);

                $photo_items[] = array(
                    'id' => (int) $photo_row->id,
                    'attachment_id' => $attachment_id,
                    'url' => $sources['display'],
                    'thumb' => $sources['thumb'],
                    'full' => $sources['full'],
                    'caption' => !empty($photo_row->caption) ? esc_html($photo_row->caption) : '',
                    'member_id' => isset($photo_row->member_id) ? (int) $photo_row->member_id : 0,
                );
            }
        }

        if ($current_member && isset($current_member->id)) {
            $pending_rows = MjEventPhotos::query(array(
                'event_id' => $event_id,
                'member_id' => (int) $current_member->id,
                'status' => MjEventPhotos::STATUS_PENDING,
                'per_page' => 12,
            ));

            if (!empty($pending_rows)) {
                foreach ($pending_rows as $pending_row) {
                    $attachment_id = isset($pending_row->attachment_id) ? (int) $pending_row->attachment_id : 0;
                    if ($attachment_id <= 0) {
                        continue;
                    }

                    $sources = function_exists('mj_member_event_photos_get_attachment_sources')
                        ? mj_member_event_photos_get_attachment_sources($attachment_id)
                        : array('thumb' => '', 'display' => '', 'full' => '');
                    $submitted_at = isset($pending_row->created_at) ? strtotime((string) $pending_row->created_at) : 0;
                    $submitted_label = $submitted_at ? date_i18n(get_option('date_format', 'd/m/Y'), $submitted_at) : '';

                    $member_pending_items[] = array(
                        'id' => isset($pending_row->id) ? (int) $pending_row->id : 0,
                        'thumb' => $sources['thumb'],
                        'full' => $sources['full'],
                        'display' => $sources['display'],
                        'caption' => !empty($pending_row->caption) ? esc_html($pending_row->caption) : '',
                        'submitted' => $submitted_label,
                    );
                }

                if (!empty($member_pending_items)) {
                    $member_pending_count = count($member_pending_items);
                }
            }
        }

        $context['photos'] = array(
            'items' => $photo_items,
            'count' => count($photo_items),
            'has_items' => !empty($photo_items),
            'can_upload' => $can_upload,
            'upload_limit' => $limit_per_member,
            'member_uploaded' => $member_upload_count,
            'member_remaining' => $is_staff_member ? null : max(0, $limit_per_member - $member_upload_count),
            'member_registration_id' => $member_registration_id,
            'reason' => $upload_reason,
            'is_unlimited' => $is_staff_member,
            'member_pending' => array(
                'items' => $member_pending_items,
                'count' => $member_pending_count,
                'has_items' => $member_pending_count > 0,
            ),
        );

        return $context;
    }
    add_filter('mj_member_event_page_context', 'mj_member_event_photos_extend_context', 20, 2);
}

if (!function_exists('mj_member_event_photos_get_notice_map')) {
    /**
     * @return array<string,array{type:string,message:string}>
     */
    function mj_member_event_photos_get_notice_map() {
        return array(
            'success' => array(
                'type' => 'success',
                'message' => __('Merci ! Ta photo a bien été envoyée et attend la validation de l’équipe.', 'mj-member'),
            ),
            'success_auto' => array(
                'type' => 'success',
                'message' => __('Merci ! Ta photo est déjà en ligne, pense à prévenir les participants.', 'mj-member'),
            ),
            'nonce' => array(
                'type' => 'error',
                'message' => __('La vérification de sécurité a échoué. Recharge la page puis réessaie.', 'mj-member'),
            ),
            'login' => array(
                'type' => 'error',
                'message' => __('Connecte-toi pour partager une photo.', 'mj-member'),
            ),
            'invalid' => array(
                'type' => 'error',
                'message' => __('Choisis une image et ajoute une description avant d’envoyer.', 'mj-member'),
            ),
            'missing' => array(
                'type' => 'error',
                'message' => __('Événement introuvable. Merci de réessayer depuis la page de l’événement.', 'mj-member'),
            ),
            'limit' => array(
                'type' => 'warning',
                'message' => __('Limite atteinte : tu as déjà envoyé le nombre maximum de photos pour cet événement.', 'mj-member'),
            ),
            'not_registered' => array(
                'type' => 'error',
                'message' => __('Seuls les participants confirmés peuvent partager des photos.', 'mj-member'),
            ),
            'type' => array(
                'type' => 'error',
                'message' => __('Format de fichier non autorisé. Utilise une image JPG, PNG, GIF, WebP ou HEIC.', 'mj-member'),
            ),
            'upload_error' => array(
                'type' => 'error',
                'message' => __('Téléversement impossible pour le moment. Vérifie la taille du fichier et réessaie.', 'mj-member'),
            ),
            'store' => array(
                'type' => 'error',
                'message' => __('La photo n’a pas pu être enregistrée. Contacte l’équipe si le problème persiste.', 'mj-member'),
            ),
            'consent' => array(
                'type' => 'error',
                'message' => __('Confirme que tu disposes des autorisations nécessaires pour partager cette photo.', 'mj-member'),
            ),
            'profile' => array(
                'type' => 'error',
                'message' => __('Ton profil MJ est introuvable. Contacte l’équipe pour regulariser la situation.', 'mj-member'),
            ),
            'unavailable' => array(
                'type' => 'error',
                'message' => __('Le module photo est momentanément indisponible. Merci de réessayer plus tard.', 'mj-member'),
            ),
            'deleted' => array(
                'type' => 'success',
                'message' => __('Ta photo a bien été supprimée.', 'mj-member'),
            ),
            'delete_denied' => array(
                'type' => 'error',
                'message' => __('Suppression impossible pour cette photo.', 'mj-member'),
            ),
            'delete_failed' => array(
                'type' => 'error',
                'message' => __('La suppression de la photo a échoué. Réessaie dans un instant.', 'mj-member'),
            ),
        );
    }
}

if (!function_exists('mj_member_event_photos_get_notice')) {
    /**
     * @param string $code
     * @return array{type:string,message:string}|null
     */
    function mj_member_event_photos_get_notice($code) {
        $code = sanitize_key((string) $code);
        if ($code === '') {
            return null;
        }

        $map = mj_member_event_photos_get_notice_map();
        return isset($map[$code]) ? $map[$code] : null;
    }
}

if (!function_exists('mj_member_event_photos_get_member_upload_context')) {
    /**
     * Prépare les données nécessaires au widget front "mes photos d’événement".
     *
     * @param object|null $member
     * @param array<string,mixed> $args
     * @return array<string,mixed>
     */
    function mj_member_event_photos_get_member_upload_context($member, $args = array()) {
        $defaults = array(
            'limit' => 6,
            'preview' => false,
        );
        $args = wp_parse_args($args, $defaults);

        $events = array();
        $member_id = (is_object($member) && isset($member->id)) ? (int) $member->id : 0;

        if (!$args['preview']) {
            if ($member_id <= 0 || !class_exists('MjEventPhotos') || !class_exists('MjEventRegistrations') || !class_exists('MjEvents')) {
                return array(
                    'events' => array(),
                    'has_events' => false,
                );
            }
        }

        if ($args['preview'] && $member_id <= 0) {
            $events[] = array(
                'event_id' => 101,
                'title' => __('Stage graffiti – juillet', 'mj-member'),
                'date_label' => __('Du 14 au 18 juillet 2025', 'mj-member'),
                'permalink' => '#',
                'remaining' => 2,
                'limit' => 3,
                'can_upload' => true,
                'reason' => '',
                'uploads' => array(
                    array(
                        'id' => 5001,
                        'status' => 'pending',
                        'status_label' => __('En attente', 'mj-member'),
                        'thumb' => '',
                        'url' => '#',
                        'caption' => __('Tag final du groupe', 'mj-member'),
                        'created_at' => __('il y a 2 jours', 'mj-member'),
                    ),
                ),
            );

            $events[] = array(
                'event_id' => 102,
                'title' => __('Sortie Paintball', 'mj-member'),
                'date_label' => __('3 mai 2025', 'mj-member'),
                'permalink' => '#',
                'remaining' => 0,
                'limit' => 3,
                'can_upload' => false,
                'reason' => __('Tu as déjà partagé le maximum de photos pour cet événement.', 'mj-member'),
                'uploads' => array(),
            );

            return array(
                'events' => $events,
                'has_events' => !empty($events),
            );
        }

        $status_labels = MjEventPhotos::get_status_labels();
        $is_staff_member = mj_member_event_photos_is_staff_member($member);
        $limit_setting = max(1, (int) $args['limit']);

        if ($is_staff_member) {
            $events = array();
            $events_seen = array();
            $combined_events = array();

            $query_args = array(
                'limit' => $limit_setting,
                'order' => 'DESC',
                'orderby' => 'date_debut',
                'statuses' => array(MjEvents::STATUS_ACTIVE, MjEvents::STATUS_PAST),
            );

            $primary_events = MjEvents::get_all($query_args);
            if (!empty($primary_events)) {
                foreach ($primary_events as $event_obj) {
                    if (!is_object($event_obj)) {
                        continue;
                    }
                    $event_id = method_exists($event_obj, 'get') ? (int) $event_obj->get('id', 0) : (isset($event_obj->id) ? (int) $event_obj->id : 0);
                    if ($event_id <= 0 || isset($events_seen[$event_id])) {
                        continue;
                    }
                    $events_seen[$event_id] = true;
                    $combined_events[] = $event_obj;
                }
            }

            $member_upload_rows = MjEventPhotos::query(array(
                'member_id' => $member_id,
                'per_page' => 50,
                'paged' => 1,
                'order' => 'DESC',
                'orderby' => 'created_at',
            ));

            if (!empty($member_upload_rows)) {
                foreach ($member_upload_rows as $upload_row) {
                    $event_id = isset($upload_row->event_id) ? (int) $upload_row->event_id : 0;
                    if ($event_id <= 0 || isset($events_seen[$event_id])) {
                        continue;
                    }
                    $event_obj = MjEvents::find($event_id);
                    if ($event_obj) {
                        $events_seen[$event_id] = true;
                        $combined_events[] = $event_obj;
                    }
                }
            }

            $get_event_start = static function ($event_obj) {
                $date_value = '';
                if (is_object($event_obj)) {
                    if (method_exists($event_obj, 'get')) {
                        $date_value = (string) $event_obj->get('date_debut', '');
                    } elseif (isset($event_obj->date_debut)) {
                        $date_value = (string) $event_obj->date_debut;
                    }
                }
                $timestamp = $date_value !== '' ? strtotime($date_value) : 0;
                return $timestamp ? $timestamp : 0;
            };

            if (count($combined_events) > 1) {
                usort($combined_events, static function ($a, $b) use ($get_event_start) {
                    $a_start = $get_event_start($a);
                    $b_start = $get_event_start($b);
                    if ($a_start === $b_start) {
                        return 0;
                    }
                    return ($b_start <=> $a_start);
                });
            }

            $slots_remaining = $limit_setting;
            foreach ($combined_events as $event_obj) {
                $event_id = 0;
                if (is_object($event_obj)) {
                    if (method_exists($event_obj, 'get')) {
                        $event_id = (int) $event_obj->get('id', 0);
                    } elseif (isset($event_obj->id)) {
                        $event_id = (int) $event_obj->id;
                    }
                }
                if ($event_id <= 0) {
                    continue;
                }

                $event_array = method_exists($event_obj, 'toArray') ? $event_obj->toArray() : (array) $event_obj;
                $event_record = (object) $event_array;

                $member_upload_count = MjEventPhotos::count_for_member($event_id, $member_id);
                $can_upload = mj_member_event_photos_member_can_upload($event_record, null, $member_upload_count, 0, $member);

                $member_photos = MjEventPhotos::query(array(
                    'event_id' => $event_id,
                    'member_id' => $member_id,
                    'per_page' => 20,
                    'paged' => 1,
                    'order' => 'DESC',
                ));

                $photo_entries = array();
                $status_counts = array(
                    'pending' => 0,
                    'approved' => 0,
                    'rejected' => 0,
                );
                if (!empty($member_photos)) {
                    foreach ($member_photos as $photo_row) {
                        $attachment_id = isset($photo_row->attachment_id) ? (int) $photo_row->attachment_id : 0;
                        if ($attachment_id <= 0) {
                            continue;
                        }

                        $sources = mj_member_event_photos_get_attachment_sources($attachment_id);
                        $status = isset($photo_row->status) ? sanitize_key((string) $photo_row->status) : MjEventPhotos::STATUS_PENDING;
                        $rejection_reason = isset($photo_row->rejection_reason) ? sanitize_text_field((string) $photo_row->rejection_reason) : '';

                        if (isset($status_counts[$status])) {
                            $status_counts[$status]++;
                        }

                        $created_at_raw = isset($photo_row->created_at) ? strtotime((string) $photo_row->created_at) : false;
                        $created_at_label = $created_at_raw ? sprintf(__('Envoyée le %s', 'mj-member'), date_i18n(get_option('date_format', 'd/m/Y'), $created_at_raw)) : '';

                        $photo_entries[] = array(
                            'id' => isset($photo_row->id) ? (int) $photo_row->id : 0,
                            'status' => $status,
                            'status_label' => isset($status_labels[$status]) ? $status_labels[$status] : ucfirst($status),
                            'thumb' => $sources['thumb'],
                            'url' => $sources['display'],
                            'full' => $sources['full'],
                            'caption' => !empty($photo_row->caption) ? esc_html($photo_row->caption) : '',
                            'created_at' => $created_at_label,
                            'rejection_reason' => $rejection_reason,
                            'can_delete' => empty($args['preview']),
                        );
                    }
                }

                $has_existing_uploads = $member_upload_count > 0;
                if (!$has_existing_uploads && $slots_remaining <= 0) {
                    continue;
                }

                if (!$has_existing_uploads && $slots_remaining > 0) {
                    $slots_remaining--;
                }

                $events[] = array(
                    'event_id' => $event_id,
                    'title' => isset($event_record->title) ? sanitize_text_field((string) $event_record->title) : sprintf(__('Événement #%d', 'mj-member'), $event_id),
                    'date_label' => mj_member_format_event_datetime_range(
                        isset($event_record->date_debut) ? (string) $event_record->date_debut : '',
                        isset($event_record->date_fin) ? (string) $event_record->date_fin : ''
                    ),
                    'permalink' => apply_filters('mj_member_event_permalink', '', $event_record),
                    'remaining' => null,
                    'limit' => 0,
                    'can_upload' => $can_upload,
                    'reason' => '',
                    'uploads' => $photo_entries,
                    'is_unlimited' => true,
                    'counts' => array(
                        'total' => $member_upload_count,
                        'pending' => $status_counts['pending'],
                        'approved' => $status_counts['approved'],
                        'rejected' => $status_counts['rejected'],
                    ),
                );
            }

            return array(
                'events' => $events,
                'has_events' => !empty($events),
            );
        }

        $statuses = array(
            MjEventRegistrations::STATUS_CONFIRMED,
            MjEventRegistrations::STATUS_PENDING,
        );

        $registrations = MjEventRegistrations::get_all(array(
            'member_id' => $member_id,
            'statuses' => $statuses,
            'order' => 'DESC',
            'orderby' => 'created_at',
            'limit' => max(20, $limit_setting * 3),
        ));

        if (empty($registrations)) {
            return array(
                'events' => array(),
                'has_events' => false,
            );
        }

        $events_map = array();
        foreach ($registrations as $registration) {
            $event_id = (int) $registration->get('event_id', 0);
            if ($event_id <= 0 || isset($events_map[$event_id])) {
                continue;
            }
            $events_map[$event_id] = $registration;
        }

        if (empty($events_map)) {
            return array(
                'events' => array(),
                'has_events' => false,
            );
        }

        foreach ($events_map as $event_id => $registration) {
            $event_data = MjEvents::find($event_id);
            if (!$event_data) {
                continue;
            }

            $event_array = method_exists($event_data, 'toArray') ? $event_data->toArray() : (array) $event_data;
            $event_record = (object) $event_array;

            $limit_per_member = (int) apply_filters('mj_member_event_photo_upload_limit', 3, $event_id, $member);
            if ($limit_per_member <= 0) {
                $limit_per_member = 3;
            }

            $member_upload_count = MjEventPhotos::count_for_member($event_id, $member_id);
            $can_upload = mj_member_event_photos_member_can_upload($event_record, $registration, $member_upload_count, $limit_per_member, $member);
            $reason = '';
            if (!$can_upload) {
                if ($limit_per_member > 0 && $member_upload_count >= $limit_per_member) {
                    /* Translators: %d is the maximum number of photos allowed per event. */
                    $reason = sprintf(__('Limite atteinte : %d photo(s) déjà partagée(s) pour cet événement.', 'mj-member'), $limit_per_member);
                } else {
                    $reason = __('Tu pourras ajouter des photos une fois l’événement terminé et ton inscription confirmée.', 'mj-member');
                }
            }

            $remaining = max(0, $limit_per_member - $member_upload_count);

            $member_photos = MjEventPhotos::query(array(
                'event_id' => $event_id,
                'member_id' => $member_id,
                'per_page' => 20,
                'paged' => 1,
                'order' => 'DESC',
            ));

            $photo_entries = array();
            if (!empty($member_photos)) {
                foreach ($member_photos as $photo_row) {
                    $attachment_id = isset($photo_row->attachment_id) ? (int) $photo_row->attachment_id : 0;
                    if ($attachment_id <= 0) {
                        continue;
                    }

                    $sources = mj_member_event_photos_get_attachment_sources($attachment_id);
                    $status = isset($photo_row->status) ? sanitize_key((string) $photo_row->status) : MjEventPhotos::STATUS_PENDING;
                    $rejection_reason = isset($photo_row->rejection_reason) ? sanitize_text_field((string) $photo_row->rejection_reason) : '';

                    $created_at_raw = isset($photo_row->created_at) ? strtotime((string) $photo_row->created_at) : false;
                    $created_at_label = $created_at_raw ? sprintf(__('Envoyée le %s', 'mj-member'), date_i18n(get_option('date_format', 'd/m/Y'), $created_at_raw)) : '';

                    $photo_entries[] = array(
                        'id' => isset($photo_row->id) ? (int) $photo_row->id : 0,
                        'status' => $status,
                        'status_label' => isset($status_labels[$status]) ? $status_labels[$status] : ucfirst($status),
                        'thumb' => $sources['thumb'],
                        'url' => $sources['display'],
                        'full' => $sources['full'],
                        'caption' => !empty($photo_row->caption) ? esc_html($photo_row->caption) : '',
                        'created_at' => $created_at_label,
                        'rejection_reason' => $rejection_reason,
                        'can_delete' => empty($args['preview']),
                    );
                }
            }

            $events[] = array(
                'event_id' => $event_id,
                'title' => isset($event_record->title) ? sanitize_text_field((string) $event_record->title) : sprintf(__('Événement #%d', 'mj-member'), $event_id),
                'date_label' => mj_member_format_event_datetime_range(
                    isset($event_record->date_debut) ? (string) $event_record->date_debut : '',
                    isset($event_record->date_fin) ? (string) $event_record->date_fin : ''
                ),
                'permalink' => apply_filters('mj_member_event_permalink', '', $event_record),
                'remaining' => $remaining,
                'limit' => $limit_per_member,
                'can_upload' => $can_upload,
                'reason' => $reason,
                'uploads' => $photo_entries,
                'is_unlimited' => false,
            );

            if (count($events) >= $limit_setting) {
                break;
            }
        }

        return array(
            'events' => $events,
            'has_events' => !empty($events),
        );
    }
}

if (!function_exists('mj_member_event_photos_member_can_upload')) {
    function mj_member_event_photos_member_can_upload($event_record, $registration, $current_count, $limit, $member = null) {
        if (mj_member_event_photos_is_staff_member($member)) {
            return true;
        }

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

        if ($limit > 0 && $current_count >= $limit) {
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

        if (!class_exists('MjEventPhotos') || !class_exists('MjEventRegistrations') || !class_exists('MjEvents')) {
            $redirect_with_notice('unavailable');
        }

        $current_member = function_exists('mj_member_get_current_member') ? mj_member_get_current_member() : null;
        if (!$current_member || empty($current_member->id)) {
            $redirect_with_notice('profile');
        }

        $is_staff_member = mj_member_event_photos_is_staff_member($current_member);

        $event = MjEvents::find($event_id);
        if (!$event) {
            $redirect_with_notice('missing');
        }

        $limit = $is_staff_member ? 0 : (int) apply_filters('mj_member_event_photo_upload_limit', 3, $event_id, $current_member);
        if (!$is_staff_member && $limit <= 0) {
            $limit = 3;
        }

        $current_count = MjEventPhotos::count_for_member($event_id, (int) $current_member->id);
        if (!$is_staff_member && $limit > 0 && $current_count >= $limit) {
            $redirect_with_notice('limit');
        }

        $registration = mj_member_event_photos_get_member_registration($event_id, $current_member);
        if (!$registration && !$is_staff_member) {
            $redirect_with_notice('not_registered');
        }

        if (!isset($_FILES['event_photo_file']) || !is_array($_FILES['event_photo_file'])) {
            $redirect_with_notice('invalid');
        }

        $consent_value = isset($_POST['mj_event_photo_consent']) ? sanitize_text_field(wp_unslash($_POST['mj_event_photo_consent'])) : '';
        if ($consent_value !== '1') {
            $redirect_with_notice('consent');
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

        if (function_exists('mj_member_event_photos_optimize_attachment')) {
            mj_member_event_photos_optimize_attachment((int) $attachment_id);
        }

        $consent_version = apply_filters('mj_member_event_photo_consent_version', '2025-12-05');
        update_post_meta((int) $attachment_id, '_mj_member_photo_consent', array(
            'member_id' => (int) $current_member->id,
            'wp_user_id' => get_current_user_id(),
            'accepted_at' => current_time('mysql'),
            'version' => is_string($consent_version) ? $consent_version : '2025-12-05',
        ));

        $member_role = isset($current_member->role) ? sanitize_key((string) $current_member->role) : '';
        $auto_approve_roles = apply_filters('mj_member_event_photo_auto_approve_roles', array('animateur', 'coordinateur'));
        if (!is_array($auto_approve_roles)) {
            $auto_approve_roles = array('animateur', 'coordinateur');
        }
        $auto_approve_roles = array_map('sanitize_key', $auto_approve_roles);
        $should_auto_approve = $member_role !== '' && in_array($member_role, $auto_approve_roles, true);

        $photo_payload = array(
            'event_id' => $event_id,
            'member_id' => (int) $current_member->id,
            'registration_id' => ($registration && isset($registration->id)) ? (int) $registration->id : 0,
            'attachment_id' => (int) $attachment_id,
            'caption' => $caption,
            'status' => $should_auto_approve ? MjEventPhotos::STATUS_APPROVED : MjEventPhotos::STATUS_PENDING,
        );

        if ($should_auto_approve) {
            $photo_payload['reviewed_at'] = current_time('mysql');
            $reviewed_by = get_current_user_id();
            if ($reviewed_by > 0) {
                $photo_payload['reviewed_by'] = $reviewed_by;
            }
        }

        $insert = MjEventPhotos::create($photo_payload);

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

        $redirect_with_notice($should_auto_approve ? 'success_auto' : 'success');
    }

    add_action('admin_post_mj_member_submit_event_photo', 'mj_member_event_photos_submission_handler');
    add_action('admin_post_nopriv_mj_member_submit_event_photo', 'mj_member_event_photos_submission_handler');
}

if (!function_exists('mj_member_event_photos_delete_handler')) {
    function mj_member_event_photos_delete_handler() {
        $redirect = isset($_POST['redirect_to']) ? esc_url_raw($_POST['redirect_to']) : home_url('/');
        $redirect = $redirect !== '' ? $redirect : home_url('/');

        $redirect_with_notice = function ($code) use ($redirect) {
            $target = add_query_arg('mj_event_photo', urlencode($code), $redirect);
            wp_safe_redirect($target);
            exit;
        };

        if (!isset($_POST['mj_event_photo_delete_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mj_event_photo_delete_nonce'])), 'mj-member-event-photo-delete')) {
            $redirect_with_notice('nonce');
        }

        if (!is_user_logged_in()) {
            $redirect_with_notice('login');
        }

        if (!class_exists('MjEventPhotos')) {
            $redirect_with_notice('unavailable');
        }

        $photo_id = isset($_POST['photo_id']) ? (int) $_POST['photo_id'] : 0;
        $event_id = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;

        if ($photo_id <= 0 || $event_id <= 0) {
            $redirect_with_notice('invalid');
        }

        $member = function_exists('mj_member_get_current_member') ? mj_member_get_current_member() : null;
        if (!$member || empty($member->id)) {
            $redirect_with_notice('profile');
        }

        $photo = MjEventPhotos::get($photo_id);
        if (!$photo) {
            $redirect_with_notice('delete_denied');
        }

        if ((int) $photo->member_id !== (int) $member->id || (int) $photo->event_id !== $event_id) {
            $redirect_with_notice('delete_denied');
        }

        $attachment_id = isset($photo->attachment_id) ? (int) $photo->attachment_id : 0;

        $deleted = MjEventPhotos::delete($photo_id);
        if (is_wp_error($deleted)) {
            $redirect_with_notice('delete_failed');
        }

        if ($attachment_id > 0) {
            if (!function_exists('wp_delete_attachment')) {
                require_once ABSPATH . 'wp-admin/includes/post.php';
            }
            wp_delete_attachment($attachment_id, true);
        }

        $redirect_with_notice('deleted');
    }

    add_action('admin_post_mj_member_delete_event_photo', 'mj_member_event_photos_delete_handler');
    add_action('admin_post_nopriv_mj_member_delete_event_photo', 'mj_member_event_photos_delete_handler');
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