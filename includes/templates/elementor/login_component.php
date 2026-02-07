<?php

use Mj\Member\Classes\Crud\MjMembers;
use Mj\Member\Classes\MjAccountLinks;
use Mj\Member\Core\AssetsManager;
use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('mj_member_enqueue_login_component_assets')) {
    function mj_member_enqueue_login_component_assets(): void {
        AssetsManager::requirePackage('login-component');
    }
}



if (!function_exists('mj_member_normalize_classes')) {
    function mj_member_normalize_classes($classes) {
        $sanitized = array();
        if (is_string($classes)) {
            $classes = preg_split('/\s+/', $classes);
        }
        if (!is_array($classes)) {
            return $sanitized;
        }
        foreach ($classes as $class) {
            $class = trim($class);
            if ($class === '') {
                continue;
            }
            $sanitized[] = sanitize_html_class($class);
        }
        return $sanitized;
    }
}

if (!function_exists('mj_member_login_component_allowed_icon_tags')) {
    function mj_member_login_component_allowed_icon_tags() {
        return array(
            'i' => array(
                'class' => true,
                'aria-hidden' => true,
                'data-icon' => true,
                'data-prefix' => true,
                'data-fa-i2svg' => true,
            ),
            'span' => array(
                'class' => true,
                'aria-hidden' => true,
            ),
            'svg' => array(
                'class' => true,
                'aria-hidden' => true,
                'xmlns' => true,
                'xmlns:xlink' => true,
                'width' => true,
                'height' => true,
                'viewBox' => true,
                'role' => true,
                'focusable' => true,
                'fill' => true,
                'stroke' => true,
                'stroke-width' => true,
                'stroke-linecap' => true,
                'stroke-linejoin' => true,
                'preserveAspectRatio' => true,
            ),
            'img' => array(
                'src' => true,
                'alt' => true,
                'class' => true,
                'loading' => true,
                'decoding' => true,
                'width' => true,
                'height' => true,
                'srcset' => true,
                'sizes' => true,
                'aria-hidden' => true,
            ),
            'path' => array(
                'd' => true,
                'fill' => true,
                'stroke' => true,
                'stroke-width' => true,
                'stroke-linecap' => true,
                'stroke-linejoin' => true,
            ),
            'polygon' => array(
                'points' => true,
                'fill' => true,
                'stroke' => true,
                'stroke-width' => true,
                'stroke-linecap' => true,
                'stroke-linejoin' => true,
            ),
            'polyline' => array(
                'points' => true,
                'fill' => true,
                'stroke' => true,
                'stroke-width' => true,
                'stroke-linecap' => true,
                'stroke-linejoin' => true,
            ),
            'line' => array(
                'x1' => true,
                'x2' => true,
                'y1' => true,
                'y2' => true,
                'stroke' => true,
                'stroke-width' => true,
                'stroke-linecap' => true,
                'stroke-linejoin' => true,
            ),
            'circle' => array(
                'cx' => true,
                'cy' => true,
                'r' => true,
                'fill' => true,
                'stroke' => true,
                'stroke-width' => true,
            ),
            'ellipse' => array(
                'cx' => true,
                'cy' => true,
                'rx' => true,
                'ry' => true,
                'fill' => true,
                'stroke' => true,
                'stroke-width' => true,
            ),
            'rect' => array(
                'x' => true,
                'y' => true,
                'width' => true,
                'height' => true,
                'rx' => true,
                'ry' => true,
                'fill' => true,
                'stroke' => true,
                'stroke-width' => true,
            ),
            'use' => array(
                'xlink:href' => true,
            ),
        );
    }
}

if (!function_exists('mj_member_login_component_truncate')) {
    function mj_member_login_component_truncate($value, $max_length = 32) {
        $value = (string) $value;
        $max_length = (int) $max_length;
        if ($value === '') {
            return '';
        }
        if ($max_length <= 0) {
            return $value;
        }
        $length = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
        if ($length <= $max_length) {
            return $value;
        }
        $slice_length = $max_length - 3;
        if ($slice_length < 1) {
            $slice_length = $max_length;
        }
        $slice = function_exists('mb_substr') ? mb_substr($value, 0, $slice_length) : substr($value, 0, $slice_length);
        return rtrim($slice) . '...';
    }
}

if (!function_exists('mj_member_login_component_is_preview_mode')) {
    function mj_member_login_component_is_preview_mode() {
        $is_preview = false;

        if (defined('REST_REQUEST') && REST_REQUEST) {
            $context = isset($_REQUEST['context']) ? sanitize_text_field(wp_unslash($_REQUEST['context'])) : '';
            if ($context === 'edit') {
                $is_preview = true;
            }
        }

        if (!$is_preview && function_exists('is_customize_preview') && is_customize_preview()) {
            $is_preview = true;
        }

        if (!$is_preview && did_action('elementor/loaded')) {
            $elementor = \Elementor\Plugin::$instance ?? null;
            if ($elementor && isset($elementor->editor) && method_exists($elementor->editor, 'is_edit_mode') && $elementor->editor->is_edit_mode()) {
                $is_preview = true;
            }
        }

        if (!$is_preview && isset($_GET['elementor-preview'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $is_preview = true;
        }

        if (!$is_preview && is_admin() && !(function_exists('wp_doing_ajax') && wp_doing_ajax())) {
            $is_preview = true;
        }

        return (bool) apply_filters('mj_member_login_component_preview_mode', $is_preview);
    }
}

if (!function_exists('mj_member_login_component_resolve_account_link')) {
    function mj_member_login_component_resolve_account_link($path, $fallback, $query = array()) {
        $url = '';

        if ($path !== '') {
            $page = get_page_by_path(ltrim($path, '/'));
            if ($page) {
                $url = get_permalink($page);
            }
        }

        if ($url === '') {
            $url = $fallback;
        }

        if (!empty($query)) {
            $url = add_query_arg($query, $url);
        }

        return esc_url_raw($url);
    }
}

if (!function_exists('mj_member_login_component_get_default_link_settings')) {
    function mj_member_login_component_get_default_link_settings() {
        return MjAccountLinks::getDefaultSettings();
    }
}

if (!function_exists('mj_member_login_component_get_account_link_settings')) {
    function mj_member_login_component_get_account_link_settings() {
        return MjAccountLinks::getSettings();
    }
}

if (!function_exists('mj_member_login_component_get_unread_contact_message_count')) {
    /**
     * Retourne le nombre de messages de contact non lus pour un utilisateur.
     *
     * @param int   $user_id
     * @param array $overrides
     *
     * @return int
     */
    function mj_member_login_component_get_unread_contact_message_count($user_id = 0, array $overrides = array()) {
        return MjAccountLinks::getUnreadCount((int) $user_id, $overrides);
    }
}

if (!function_exists('mj_member_login_component_build_unread_target_specs')) {
    /**
     * Construit la liste des cibles additionnelles à prendre en compte pour le badge de notifications.
     *
     * @param int    $member_id
     * @param string $member_role
     *
     * @return array<int,array<string,int|string>>
     */
    function mj_member_login_component_build_unread_target_specs($member_id, $member_role) {
        return MjAccountLinks::buildUnreadTargets((int) $member_id, (string) $member_role);
    }
}

if (!function_exists('mj_member_login_component_collect_unread_counts')) {
    /**
     * Calcule le décompte des notifications (messages inclus) pour l'affichage du badge.
     *
     * @param WP_User|null $user
     * @param object|null  $member
     * @param array<string,mixed> $options
     *
     * @return array{contact:int,notifications:int,total:int}
     */
    function mj_member_login_component_collect_unread_counts($user = null, $member = null, array $options = array()) {
        $defaults = array(
            'member_id' => 0,
            'extra_targets' => array(),
            'preview_mode' => false,
        );
        $options = wp_parse_args($options, $defaults);

        $preview_mode = !empty($options['preview_mode']);
        $results = array(
            'contact' => 0,
            'notifications' => 0,
            'total' => 0,
        );

        if (!$user instanceof WP_User) {
            if ($preview_mode) {
                $contact_preview = (int) apply_filters('mj_member_contact_messages_preview_unread_total', 2, array('context' => 'login_component'));
                $notification_preview = (int) apply_filters('mj_member_notifications_preview_unread_total', 0, array('context' => 'login_component'));
                $results['contact'] = max(0, $contact_preview);
                $results['notifications'] = max(0, $notification_preview);
                $results['total'] = max(0, $results['contact'] + $results['notifications']);
            }

            /** @var array{contact:int,notifications:int,total:int} */
            return apply_filters('mj_member_login_component_unread_counts', $results, $user, $member, $options);
        }

        $member_id = isset($options['member_id']) ? (int) $options['member_id'] : 0;
        if ($member_id <= 0 && $member && isset($member->id)) {
            $member_id = (int) $member->id;
        }

        $extra_targets = array();
        if (!empty($options['extra_targets']) && is_array($options['extra_targets'])) {
            $extra_targets = $options['extra_targets'];
        }

        $contact_capability = Config::contactCapability();
        $user_can_contact = ($contact_capability === '' || user_can($user->ID, $contact_capability));
        $user_email = sanitize_email(isset($user->user_email) ? $user->user_email : '');
        $allow_owner_view = ($member_id > 0 || $user_email !== '');

        $contact_args = array();
        if (!empty($extra_targets)) {
            $contact_args['extra_targets'] = $extra_targets;
        }

        if (!$user_can_contact && $allow_owner_view) {
            $contact_args['skip_capability_check'] = true;
            if ($member_id > 0) {
                $contact_args['member_id'] = $member_id;
            }
            if ($user_email !== '') {
                $contact_args['sender_email'] = $user_email;
            }
        }

        $can_compute_contact = $user_can_contact || (!$user_can_contact && $allow_owner_view);

        if ($can_compute_contact && function_exists('mj_member_login_component_get_unread_contact_message_count')) {
            $results['contact'] = (int) mj_member_login_component_get_unread_contact_message_count($user->ID, $contact_args);
        }

        $notification_args = array();
        if ($can_compute_contact) {
            $notification_args['exclude_types'] = array('contact-message');
        }

        $notification_args = apply_filters(
            'mj_member_account_links_notification_query_args',
            $notification_args,
            $user,
            array(),
            array(
                'contact_unread' => $results['contact'],
                'can_compute_contact' => $can_compute_contact,
            )
        );

        if (function_exists('mj_member_get_user_unread_notifications_count')) {
            $results['notifications'] = (int) mj_member_get_user_unread_notifications_count($user->ID, $notification_args);
        }

        $results['total'] = max(0, (int) $results['contact'] + (int) $results['notifications']);

        $results['contact'] = max(0, (int) $results['contact']);
        $results['notifications'] = max(0, (int) $results['notifications']);

        /** @var array{contact:int,notifications:int,total:int} */
        return apply_filters('mj_member_login_component_unread_counts', $results, $user, $member, $options);
    }
}

if (!function_exists('mj_member_login_component_get_account_links')) {
    function mj_member_login_component_get_account_links($account_base, $args) {
        return MjAccountLinks::getLinks((string) $account_base, is_array($args) ? $args : array());
    }
}

if (!function_exists('mj_member_login_component_get_member_display_name')) {
    function mj_member_login_component_get_member_display_name($user = null, $member = null) {
        if (!$user && is_user_logged_in()) {
            $user = wp_get_current_user();
        }

        if (!$user instanceof WP_User) {
            return '';
        }

        if ($member === null && function_exists('mj_member_get_member_for_user')) {
            $member = mj_member_get_member_for_user($user->ID);
        }

        if ($member) {
            $first = isset($member->first_name) ? sanitize_text_field($member->first_name) : '';
            $last = isset($member->last_name) ? sanitize_text_field($member->last_name) : '';
            $full = trim($first . ' ' . $last);
            if ($full !== '') {
                return $full;
            }
        }

        $first_name = !empty($user->first_name) ? sanitize_text_field($user->first_name) : '';
        $last_name = !empty($user->last_name) ? sanitize_text_field($user->last_name) : '';
        $full_name = trim($first_name . ' ' . $last_name);
        if ($full_name !== '') {
            return $full_name;
        }

        if (!empty($user->display_name)) {
            return sanitize_text_field($user->display_name);
        }

        if (!empty($user->user_login)) {
            return sanitize_text_field($user->user_login);
        }

        return '';
    }
}

if (!function_exists('mj_member_login_component_get_member_avatar')) {
    function mj_member_login_component_get_member_avatar($user = null, $member = null) {
        if (!$user && is_user_logged_in()) {
            $user = wp_get_current_user();
        }

        if (!$user instanceof WP_User) {
            return array(
                'url' => '',
                'id' => 0,
            );
        }

        if ($member === null && function_exists('mj_member_get_member_for_user')) {
            $member = mj_member_get_member_for_user($user->ID);
        }

        $attachment_id = 0;
        $url = '';

        if ($member && !empty($member->photo_id)) {
            $attachment_id = (int) $member->photo_id;
            $image = wp_get_attachment_image_src($attachment_id, 'thumbnail');
            if ($image) {
                $url = $image[0];
            } else {
                $attachment_id = 0;
            }
        }

        if ($url === '') {
            $default_id = (int) apply_filters('mj_member_login_component_default_avatar_id', (int) get_option('mj_login_default_avatar_id', 0), $user, $member);
            if ($default_id > 0) {
                $image = wp_get_attachment_image_src($default_id, 'thumbnail');
                if ($image) {
                    $url = $image[0];
                    $attachment_id = $default_id;
                }
            }
        }

        if ($url === '') {
            $url = get_avatar_url($user->ID, array('size' => 96));
        }

        return array(
            'url' => $url !== '' ? esc_url($url) : '',
            'id' => $attachment_id,
        );
    }
}

if (!function_exists('mj_member_login_component_get_registration_url')) {
    function mj_member_login_component_get_registration_url() {
        $page_id = (int) get_option('mj_login_registration_page', 0);
        $url = '';

        if ($page_id > 0) {
            $permalink = get_permalink($page_id);
            if ($permalink) {
                $url = $permalink;
            }
        }

        if ($url === '') {
            $default_slug = ltrim(apply_filters('mj_member_login_registration_default_slug', 'inscription'), '/');
            $url = home_url('/' . $default_slug);
        }

        return esc_url_raw(apply_filters('mj_member_login_registration_url', $url, $page_id));
    }
}

if (!function_exists('mj_member_render_login_modal_component')) {
    function mj_member_render_login_modal_component($args = array()) {
        mj_member_enqueue_login_component_assets();

        $defaults = array(
            'button_label_logged_out' => __('Se connecter', 'mj-member'),
            'button_label_logged_in' => __('Accéder à mon compte', 'mj-member'),
            'modal_button_label' => __('Connexion', 'mj-member'),
            'modal_title' => __('Connexion à mon compte', 'mj-member'),
            'modal_description' => '',
            'account_modal_title' => '',
            'account_modal_description' => '',
            'redirect' => '',
            'alignment' => '',
            'extra_class' => '',
            'registration_link_label' => __('Pas encore de compte ? Inscrivez-vous', 'mj-member'),
            'button_icon_html' => '',
        );
        $args = wp_parse_args($args, $defaults);

        $legacy_account_title = __('Mon espace membre', 'mj-member');
        if (isset($args['account_modal_title']) && trim((string) $args['account_modal_title']) === $legacy_account_title) {
            $args['account_modal_title'] = '';
        }

        if ($args['redirect'] !== '') {
            $redirect_url = esc_url_raw($args['redirect']);
        } else {
            $redirect_url = function_exists('mj_member_get_account_redirect') ? mj_member_get_account_redirect($args) : home_url('/mon-compte');
        }
        $component_classes = array('mj-member-login-component');
        $allowed_alignments = array('left', 'center', 'right', 'stretch');

        if (!empty($args['alignment']) && in_array($args['alignment'], $allowed_alignments, true)) {
            $component_classes[] = 'mj-member-login-component--align-' . sanitize_html_class($args['alignment']);
        }

        $extra_classes = mj_member_normalize_classes($args['extra_class']);
        if (!empty($extra_classes)) {
            $component_classes = array_merge($component_classes, $extra_classes);
        }

        $preview_mode = mj_member_login_component_is_preview_mode();
        $is_logged_in = is_user_logged_in() && !$preview_mode;

        $account_button_label = $args['button_label_logged_in'] !== '' ? $args['button_label_logged_in'] : $defaults['button_label_logged_in'];

        $unread_counts = array(
            'contact' => 0,
            'notifications' => 0,
            'total' => 0,
        );

        $current_user = null;
        $member = null;
        $member_id = 0;
        $member_display_name = '';
        $member_role_key = '';
        $member_role_label = '';
        $member_avatar = array(
            'url' => '',
            'id' => 0,
        );
        $unread_extra_targets = array();

        if ($is_logged_in) {
            $component_classes[] = 'is-logged-in';
            $current_user = wp_get_current_user();
            if ($current_user instanceof WP_User && function_exists('mj_member_get_member_for_user')) {
                $member = mj_member_get_member_for_user($current_user->ID);
            }

            if ($member && isset($member->id)) {
                $member_id = (int) $member->id;
            }

            if ($member && class_exists('MjMembers') && MjMembers::hasField($member, 'role')) {
                $member_role_key = sanitize_key((string) MjMembers::getField($member, 'role', ''));
            }

            if ($member_role_key === '' && $current_user instanceof WP_User) {
                $primary_role = is_array($current_user->roles) && !empty($current_user->roles) ? (string) $current_user->roles[0] : '';
                if ($primary_role !== '') {
                    $member_role_key = sanitize_key($primary_role);
                }
            }

            if ($member_role_key !== '') {
                $component_classes[] = 'mj-member-login-component--role-' . sanitize_html_class($member_role_key);
            } else {
                $component_classes[] = 'mj-member-login-component--role-default';
            }

            if (class_exists('MjMembers')) {
                $role_labels = MjMembers::getRoleLabels();
                if (is_array($role_labels) && $member_role_key !== '' && isset($role_labels[$member_role_key])) {
                    $member_role_label = sanitize_text_field($role_labels[$member_role_key]);
                }
            }

            $unread_extra_targets = mj_member_login_component_build_unread_target_specs($member_id, $member_role_key);

            $unread_counts = mj_member_login_component_collect_unread_counts(
                $current_user,
                $member,
                array(
                    'member_id' => $member_id,
                    'extra_targets' => $unread_extra_targets,
                    'preview_mode' => false,
                )
            );

            $member_display_name = mj_member_login_component_get_member_display_name($current_user, $member);
            $member_avatar = mj_member_login_component_get_member_avatar($current_user, $member);
        }

        if ($preview_mode && !$is_logged_in) {
            $unread_counts = mj_member_login_component_collect_unread_counts(null, null, array(
                'preview_mode' => true,
            ));
        }

        static $instance_counter = 0;
        $instance_counter++;

        $form_slug = 'mj-member-login-component-' . $instance_counter;
        $modal_id = $form_slug . '-modal';
        $field_login_id = $form_slug . '-user';
        $field_pass_id = $form_slug . '-pass';
        $nonce_action = 'mj_member_login_component_' . $form_slug;

        $errors = array();
        $submitted_login = '';
        $remember_checked = false;
        $account_links = array();

        if (!$is_logged_in && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mj_member_login_component_form'])) {
            $target_form = sanitize_text_field(wp_unslash($_POST['mj_member_login_component_form']));
            if ($target_form === $form_slug) {
                $nonce_value = sanitize_text_field(wp_unslash($_POST['mj_member_login_nonce'] ?? ''));
                if (!wp_verify_nonce($nonce_value, $nonce_action)) {
                    $errors[] = __('La vérification de sécurité a échoué. Merci de réessayer.', 'mj-member');
                } else {
                    $submitted_login = sanitize_text_field(wp_unslash($_POST['log'] ?? ''));
                    $password = (string) wp_unslash($_POST['pwd'] ?? '');
                    $remember_checked = !empty($_POST['rememberme']);

                    if ($submitted_login === '' || $password === '') {
                        $errors[] = __('Merci de renseigner vos identifiants.', 'mj-member');
                    } else {
                        $creds = array(
                            'user_login' => $submitted_login,
                            'user_password' => $password,
                            'remember' => $remember_checked,
                        );

                        $user = wp_signon($creds, false);
                        if (is_wp_error($user)) {
                            $errors[] = $user->get_error_message();
                        } else {
                            $redirect_target = esc_url_raw(wp_unslash($_POST['redirect_to'] ?? ''));
                            if ($redirect_target === '') {
                                $redirect_target = $redirect_url;
                            }
                            $redirect_target = apply_filters('mj_member_login_redirect', $redirect_target, $user, $args);
                            wp_safe_redirect($redirect_target);
                            exit;
                        }
                    }
                }
            }
        }

        if ($is_logged_in) {
            $link_args = $args;
            $link_args['unread_contact_count'] = isset($unread_counts['total']) ? (int) $unread_counts['total'] : 0;
            $link_args['unread_breakdown'] = $unread_counts;
            $link_args['preview_mode'] = $preview_mode;
            $account_links = mj_member_login_component_get_account_links($redirect_url, $link_args);

            if (is_array($account_links)) {
                $badge_override = null;
                foreach ($account_links as $link_entry) {
                    if (!is_array($link_entry)) {
                        continue;
                    }
                    $link_key = isset($link_entry['key']) ? $link_entry['key'] : '';
                    if ($link_key === 'contact_messages') {
                        if (isset($link_entry['badge'])) {
                            $badge_override = max(0, (int) $link_entry['badge']);
                        }
                        break;
                    }
                }

                if ($badge_override !== null) {
                    $unread_counts['total'] = $badge_override;
                    if (!isset($unread_counts['contact']) || $unread_counts['contact'] > $badge_override) {
                        $unread_counts['contact'] = $badge_override;
                    }
                    $unread_counts['notifications'] = max(0, (int) $unread_counts['total'] - (int) $unread_counts['contact']);
                }
            }
        }
        
        $unread_total_count = isset($unread_counts['total']) ? max(0, (int) $unread_counts['total']) : 0;
        $unread_contact_only_count = isset($unread_counts['contact']) ? max(0, (int) $unread_counts['contact']) : 0;
        $unread_notification_count = isset($unread_counts['notifications']) ? max(0, (int) $unread_counts['notifications']) : 0;


        $registration_url = (!$is_logged_in && function_exists('mj_member_login_component_get_registration_url')) ? mj_member_login_component_get_registration_url() : '';
        $registration_label = $args['registration_link_label'] !== '' ? $args['registration_link_label'] : $defaults['registration_link_label'];
        if ($registration_url === '' || $registration_label === '') {
            $registration_label = '';
        } elseif ($registration_label !== '') {
            $registration_label = wp_strip_all_tags($registration_label);
            if ($registration_label === '') {
                $registration_url = '';
            }
        }
        if ($registration_label === '') {
            $registration_url = '';
        }

        if ($is_logged_in) {
            $raw_account_title = $args['account_modal_title'] !== '' ? $args['account_modal_title'] : $defaults['account_modal_title'];
            if ($member_display_name !== '') {
                $modal_title = strtr($raw_account_title, array(
                    '{{member_name}}' => $member_display_name,
                    '[member_name]' => $member_display_name,
                ));

                if ($modal_title === $raw_account_title && $args['account_modal_title'] === '') {
                    $modal_title = sprintf(__('Bienvenue %s', 'mj-member'), $member_display_name);
                }
            } else {
                $modal_title = $raw_account_title;
            }
        } else {
            $modal_title = $args['modal_title'] !== '' ? $args['modal_title'] : $defaults['modal_title'];
        }

        $modal_description = $is_logged_in ? $args['account_modal_description'] : $args['modal_description'];
        $button_label = $is_logged_in
            ? ($args['button_label_logged_in'] !== '' ? $args['button_label_logged_in'] : $defaults['button_label_logged_in'])
            : ($args['button_label_logged_out'] !== '' ? $args['button_label_logged_out'] : $defaults['button_label_logged_out']);

        $submit_label = $args['modal_button_label'] !== '' ? $args['modal_button_label'] : $defaults['modal_button_label'];
        $member_avatar_alt = $member_display_name !== '' ? sprintf(__('Avatar de %s', 'mj-member'), $member_display_name) : __('Avatar du membre', 'mj-member');

        $button_icon_html = '';
        if (!empty($args['button_icon_html'])) {
            $icon_raw = apply_filters('mj_member_login_component_button_icon_raw', trim((string) $args['button_icon_html']), $args, $is_logged_in);
            if ($icon_raw !== '') {
                $button_icon_html = wp_kses($icon_raw, mj_member_login_component_allowed_icon_tags());
            }
        }

        if ($button_icon_html !== '') {
            $button_icon_html = apply_filters('mj_member_login_component_button_icon_html', $button_icon_html, $args, $is_logged_in);
        }

        $account_button_image_html = '';
        if ($is_logged_in && !empty($args['account_image_html'])) {
            $image_raw = apply_filters('mj_member_login_component_account_image_raw', trim((string) $args['account_image_html']), $args);
            if ($image_raw !== '') {
                $account_button_image_html = wp_kses($image_raw, mj_member_login_component_allowed_icon_tags());
            }
            if ($account_button_image_html !== '') {
                $account_button_image_html = apply_filters('mj_member_login_component_account_image_html', $account_button_image_html, $args);
            }
        }

        $trigger_classes = array('mj-member-login-component__trigger');
        if (!$is_logged_in && $button_icon_html !== '') {
            $trigger_classes[] = 'mj-member-login-component__trigger--has-icon';
        }
        if ($is_logged_in) {
            $trigger_classes[] = 'mj-member-login-component__trigger--account';
        }

        $panel_should_open = (!$is_logged_in && !empty($errors));
        $panel_classes = array('mj-member-login-component__panel');
        if ($panel_should_open) {
            $panel_classes[] = 'is-active';
            $component_classes[] = 'is-open';
            $trigger_classes[] = 'is-active';
        }

        $wrapper_attributes = ' data-mj-member-login';
        $wrapper_attributes .= ' data-unread-total="' . esc_attr($unread_total_count) . '"';
        $wrapper_attributes .= ' data-unread-contact="' . esc_attr($unread_contact_only_count) . '"';
        $wrapper_attributes .= ' data-unread-notifications="' . esc_attr($unread_notification_count) . '"';
        if ($preview_mode) {
            $wrapper_attributes .= ' data-preview="1"';
        }
        if ($is_logged_in && $member_role_key !== '') {
            $wrapper_attributes .= ' data-member-role="' . esc_attr($member_role_key) . '"';
        }

        $member_initials = '';
        if ($is_logged_in && $member_avatar['url'] === '' && $member_display_name !== '') {
            $words = preg_split('/\s+/', $member_display_name);
            if (is_array($words) && !empty($words)) {
                $first = isset($words[0]) ? $words[0] : '';
                $second = isset($words[1]) ? $words[1] : '';
                $initials = '';
                if ($first !== '') {
                    $initials .= function_exists('mb_substr') ? mb_substr($first, 0, 1) : substr($first, 0, 1);
                }
                if ($second !== '') {
                    $initials .= function_exists('mb_substr') ? mb_substr($second, 0, 1) : substr($second, 0, 1);
                }
                if ($initials === '' && $first !== '') {
                    $initials = function_exists('mb_substr') ? mb_substr($first, 0, 1) : substr($first, 0, 1);
                }
                $member_initials = strtoupper($initials);
            }
        }

        $trigger_member_label = '';
        $trigger_aria_label = '';
        if ($is_logged_in) {
            $trigger_member_label = $member_display_name !== ''
                ? $member_display_name
                : __('Profil membre', 'mj-member');
            $max_trigger_label_length = (int) apply_filters('mj_member_login_component_trigger_name_max_length', 15, $args, $member_display_name);
            if ($trigger_member_label !== '') {
                $trigger_member_label = mj_member_login_component_truncate($trigger_member_label, $max_trigger_label_length);
            }
            $trigger_aria_label = $member_display_name !== ''
                ? sprintf(__('Accéder au profil de %s', 'mj-member'), $member_display_name)
                : __('Accéder à votre profil membre', 'mj-member');
            if ($unread_total_count > 0) {
                $trigger_aria_label .= ' ' . sprintf(_n('Vous avez %d notification non lue.', 'Vous avez %d notifications non lues.', $unread_total_count, 'mj-member'), $unread_total_count);
            }
        }

        ob_start();
        ?>
        <div class="<?php echo esc_attr(implode(' ', $component_classes)); ?>"<?php echo $wrapper_attributes; ?>>
            <button
                type="button"
                class="<?php echo esc_attr(implode(' ', $trigger_classes)); ?>"
                data-mj-login-trigger
                data-login-state="<?php echo esc_attr($is_logged_in ? 'logged-in' : 'logged-out'); ?>"<?php if ($is_logged_in && $redirect_url !== '') : ?> data-account-url="<?php echo esc_url($redirect_url); ?>"<?php endif; ?>
                data-target="<?php echo esc_attr($modal_id); ?>"
                aria-controls="<?php echo esc_attr($modal_id); ?>"
                aria-expanded="<?php echo $panel_should_open ? 'true' : 'false'; ?>"
                aria-haspopup="dialog"<?php if ($trigger_aria_label !== '') : ?> aria-label="<?php echo esc_attr($trigger_aria_label); ?>"<?php endif; ?>>
                <?php if ($is_logged_in) : ?>
                    <span class="mj-member-login-component__trigger-visual" aria-hidden="true">
                        <?php if ($account_button_image_html !== '') : ?>
                            <span class="mj-member-login-component__trigger-account-image-wrapper"><?php echo $account_button_image_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                        <?php else : ?>
                            <span class="mj-member-login-component__trigger-avatar">
                                <?php if ($member_avatar['url'] !== '') : ?>
                                    <img src="<?php echo esc_url($member_avatar['url']); ?>" alt="<?php echo esc_attr($member_avatar_alt); ?>" loading="lazy" />
                                <?php elseif ($member_initials !== '') : ?>
                                    <span class="mj-member-login-component__trigger-avatar-placeholder"><?php echo esc_html($member_initials); ?></span>
                                <?php else : ?>
                                    <span class="mj-member-login-component__trigger-avatar-placeholder" aria-hidden="true">?</span>
                                <?php endif; ?>
                            </span>
                        <?php endif; ?>
                    </span>
                    <span class="mj-member-login-component__trigger-info">
                        <span class="mj-member-login-component__trigger-label"><?php echo esc_html($account_button_label); ?></span>
                        <?php if ($trigger_member_label !== '') : ?>
                            <span class="mj-member-login-component__trigger-name"><?php echo esc_html($trigger_member_label); ?></span>
                        <?php endif; ?>
                    </span>
                <?php else : ?>
                    <?php if ($button_icon_html !== '') : ?>
                        <span class="mj-member-login-component__trigger-icon" aria-hidden="true"><?php echo $button_icon_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                    <?php endif; ?>
                    <span class="mj-member-login-component__trigger-label"><?php echo esc_html($button_label); ?></span>
                <?php endif; ?>
            </button>
            <div
                id="<?php echo esc_attr($modal_id); ?>"
                class="<?php echo esc_attr(implode(' ', $panel_classes)); ?>"
                data-mj-login-panel
                role="dialog"
                aria-modal="false"
                aria-labelledby="<?php echo esc_attr($modal_id); ?>-title"
                aria-hidden="<?php echo $panel_should_open ? 'false' : 'true'; ?>"
                tabindex="-1">
                <div class="mj-member-login-component__panel-arrow" aria-hidden="true"></div>
                <div class="mj-member-login-component__panel-inner" role="document">
                    <button type="button" class="mj-member-login-component__close" data-mj-login-close aria-label="<?php esc_attr_e('Fermer le panneau de connexion', 'mj-member'); ?>"></button>
                    <div class="mj-member-login-component__header">
                        <?php if (!empty($modal_description)) : ?>
                            <p class="mj-member-login-component__description"><?php echo esc_html($modal_description); ?></p>
                        <?php endif; ?>
                    </div>
                    <?php if ($is_logged_in) : ?>
                        <?php if ($member_display_name !== '' || $member_avatar['url'] !== '' || $member_initials !== '' || $member_role_label !== '') : ?>
                            <div class="mj-member-login-component__member">
                                <span class="mj-member-login-component__member-info">
                            
                                    <?php if ($member_role_label !== '') : ?>
                                        <span class="mj-member-login-component__member-role"><?php echo esc_html($member_role_label); ?></span>
                                    <?php endif; ?>
                                </span>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($account_links)) : ?>
                            <ul class="mj-member-login-component__account-list">
                                <?php foreach ($account_links as $link_entry) : ?>
                                    <?php
                                    $link_label = isset($link_entry['label']) ? $link_entry['label'] : '';
                                    $link_url = isset($link_entry['url']) ? $link_entry['url'] : '';
                                    if ($link_label === '' || $link_url === '') {
                                        continue;
                                    }
                                    $link_badge = isset($link_entry['badge']) ? (int) $link_entry['badge'] : 0;
                                    $link_icon_html = '';
                                    if (!empty($link_entry['icon']) && is_array($link_entry['icon']) && !empty($link_entry['icon']['html'])) {
                                        $link_icon_html = $link_entry['icon']['html'];
                                    }
                                    $link_classes = array('mj-member-login-component__account-link');
                                    if (!empty($link_entry['is_logout'])) {
                                        $link_classes[] = 'mj-member-login-component__account-link--logout';
                                    }
                                    ?>
                                    <li class="mj-member-login-component__account-item">
                                        <a class="<?php echo esc_attr(implode(' ', $link_classes)); ?>" href="<?php echo esc_url($link_url); ?>"<?php echo !empty($link_entry['is_logout']) ? ' rel="nofollow"' : ''; ?>>
                                            <span class="mj-member-login-component__account-main">
                                                <?php if ($link_icon_html !== '') : ?>
                                                    <span class="mj-member-account-menu__item-icon"><?php echo $link_icon_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                                                <?php endif; ?>
                                                <span class="mj-member-login-component__account-label"><?php echo esc_html($link_label); ?></span>
                                                <?php if ($link_badge > 0) : ?>
                                                    <?php
                                                    $badge_display = $link_badge > 99 ? '99+' : number_format_i18n($link_badge);
                                                    $badge_label = sprintf(_n('%d notification', '%d notifications', $link_badge, 'mj-member'), $link_badge);
                                                    ?>
                                                    <span class="mj-member-login-component__account-badge" aria-label="<?php echo esc_attr($badge_label); ?>"><?php echo esc_html($badge_display); ?></span>
                                                <?php endif; ?>
                                            </span>
                                            <span class="mj-member-login-component__account-icon" aria-hidden="true">&rsaquo;</span>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else : ?>
                            <p class="mj-member-login-component__empty"><?php esc_html_e('Aucun lien disponible pour le moment.', 'mj-member'); ?></p>
                        <?php endif; ?>
                    <?php else : ?>
                        <?php if (!empty($errors)) : ?>
                            <div class="mj-member-login-component__errors">
                                <?php foreach ($errors as $error) : ?>
                                    <p><?php echo wp_kses_post($error); ?></p>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <form method="post" class="mj-member-login-component__form">
                            <div class="mj-member-login-component__field">
                                <label for="<?php echo esc_attr($field_login_id); ?>"><?php esc_html_e('Adresse email ou identifiant', 'mj-member'); ?></label>
                                <input type="text" id="<?php echo esc_attr($field_login_id); ?>" name="log" value="<?php echo esc_attr($submitted_login); ?>" required />
                            </div>
                            <div class="mj-member-login-component__field">
                                <label for="<?php echo esc_attr($field_pass_id); ?>"><?php esc_html_e('Mot de passe', 'mj-member'); ?></label>
                                <input type="password" id="<?php echo esc_attr($field_pass_id); ?>" name="pwd" required />
                            </div>
                            <div class="mj-member-login-component__field mj-member-login-component__field--inline">
                                <label class="mj-member-login-component__remember">
                                    <input type="checkbox" name="rememberme" value="1" <?php checked($remember_checked); ?> />
                                    <span><?php esc_html_e('Se souvenir de moi', 'mj-member'); ?></span>
                                </label>
                                <a class="mj-member-login-component__link" href="<?php echo esc_url(wp_lostpassword_url()); ?>"><?php esc_html_e('Mot de passe oublié ?', 'mj-member'); ?></a>
                            </div>
                            <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_url); ?>" />
                            <input type="hidden" name="mj_member_login_component_form" value="<?php echo esc_attr($form_slug); ?>" />
                            <?php wp_nonce_field($nonce_action, 'mj_member_login_nonce'); ?>
                            <div class="mj-member-login-component__actions">
                                <button type="submit" class="mj-member-login-component__submit"><?php echo esc_html($submit_label); ?></button>
                            </div>
                        </form>
                        <?php if ($registration_label !== '' && $registration_url !== '') : ?>
                            <div class="mj-member-login-component__extras">
                                <a class="mj-member-login-component__link mj-member-login-component__link--register" href="<?php echo esc_url($registration_url); ?>">
                                    <?php echo esc_html($registration_label); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}

if (!function_exists('mj_member_render_login_block')) {
    function mj_member_render_login_block($attributes = array(), $content = '') {
        $args = array(
            'button_label_logged_out' => isset($attributes['loginLabel']) ? sanitize_text_field($attributes['loginLabel']) : '',
            'button_label_logged_in' => isset($attributes['accountLabel']) ? sanitize_text_field($attributes['accountLabel']) : '',
            'modal_button_label' => isset($attributes['modalButtonLabel']) ? sanitize_text_field($attributes['modalButtonLabel']) : '',
            'modal_title' => isset($attributes['modalTitle']) ? sanitize_text_field($attributes['modalTitle']) : '',
            'modal_description' => isset($attributes['modalDescription']) ? sanitize_textarea_field($attributes['modalDescription']) : '',
            'redirect' => isset($attributes['redirect']) ? esc_url_raw($attributes['redirect']) : '',
            'alignment' => isset($attributes['alignment']) ? sanitize_key($attributes['alignment']) : '',
            'extra_class' => isset($attributes['className']) ? $attributes['className'] : '',
            'registration_link_label' => isset($attributes['registrationLinkLabel']) ? sanitize_text_field($attributes['registrationLinkLabel']) : '',
        );

        return mj_member_render_login_modal_component($args);
    }
}

if (!function_exists('mj_member_register_login_component_block')) {
    function mj_member_register_login_component_block() {
        if (!function_exists('register_block_type')) {
            return;
        }

        register_block_type('mj-member/login-button', array(
            'attributes' => array(
                'loginLabel' => array(
                    'type' => 'string',
                    'default' => __('Se connecter', 'mj-member'),
                ),
                'accountLabel' => array(
                    'type' => 'string',
                    'default' => __('Accéder à mon compte', 'mj-member'),
                ),
                'modalTitle' => array(
                    'type' => 'string',
                    'default' => __('Connexion à mon compte', 'mj-member'),
                ),
                'modalDescription' => array(
                    'type' => 'string',
                    'default' => '',
                ),
                'modalButtonLabel' => array(
                    'type' => 'string',
                    'default' => __('Connexion', 'mj-member'),
                ),
                'redirect' => array(
                    'type' => 'string',
                    'default' => '',
                ),
                'alignment' => array(
                    'type' => 'string',
                    'default' => '',
                ),
            ),
            'render_callback' => 'mj_member_render_login_block',
            'editor_script' => 'mj-member-login-block-editor',
            'style' => 'mj-member-login-component',
            'editor_style' => 'mj-member-login-component',
        ));
    }
    add_action('init', 'mj_member_register_login_component_block');
}

if (!function_exists('mj_member_register_elementor_login_widget')) {
    function mj_member_register_elementor_login_widget($widgets_manager) {
        if (!class_exists('\\Elementor\\Widget_Base', false)) {
            return;
        }

        if (!function_exists('mj_member_get_elementor_widgets_map') || !function_exists('mj_member_load_elementor_widgets')) {
            return;
        }

        $widgets_map = mj_member_get_elementor_widgets_map();
        $loaded_widgets = mj_member_load_elementor_widgets($widgets_map);

        foreach ($widgets_map as $class_name => $relative_path) {
            if (empty($loaded_widgets[$class_name]) || !class_exists($class_name, false)) {
                continue;
            }

            if (is_subclass_of($class_name, 'Elementor\\Widget_Base')) {
                $widgets_manager->register(new $class_name());
            }
        }
    }
}

if (!function_exists('mj_member_bootstrap_elementor_login_widget')) {
    function mj_member_bootstrap_elementor_login_widget() {
        if (!did_action('elementor/loaded')) {
            return;
        }

        add_action('elementor/widgets/register', 'mj_member_register_elementor_login_widget');
        add_action('elementor/frontend/after_enqueue_scripts', 'mj_member_enqueue_login_component_assets');
        add_action('elementor/editor/after_enqueue_scripts', 'mj_member_enqueue_login_component_assets');
    }
    add_action('plugins_loaded', 'mj_member_bootstrap_elementor_login_widget');
}
