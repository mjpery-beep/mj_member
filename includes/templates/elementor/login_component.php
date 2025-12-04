<?php

use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('mj_member_register_login_component_assets')) {
    function mj_member_register_login_component_assets() {
        $version = Config::version();
        $baseUrl = Config::url();
        $basePath = Config::path();

        $stylePath = $basePath . 'css/login-component.css';
        $scriptPath = $basePath . 'js/login-component.js';
        $blockScriptPath = $basePath . 'js/block-login-button.js';

        $styleVersion = file_exists($stylePath) ? (string) filemtime($stylePath) : $version;
        $scriptVersion = file_exists($scriptPath) ? (string) filemtime($scriptPath) : $version;
        $blockScriptVersion = file_exists($blockScriptPath) ? (string) filemtime($blockScriptPath) : $version;

        wp_register_style(
            'mj-member-login-component',
            $baseUrl . 'css/login-component.css',
            array(),
            $styleVersion
        );

        wp_register_script(
            'mj-member-login-component',
            $baseUrl . 'js/login-component.js',
            array(),
            $scriptVersion,
            true
        );

        if (function_exists('register_block_type')) {
            wp_register_script(
                'mj-member-login-block-editor',
                $baseUrl . 'js/block-login-button.js',
                array('wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-i18n'),
                $blockScriptVersion,
                true
            );

            $default_redirect = function_exists('mj_member_get_account_redirect')
                ? mj_member_get_account_redirect()
                : home_url('/mon-compte');

            wp_localize_script(
                'mj-member-login-block-editor',
                'mjMemberLoginDefaults',
                array(
                    'redirect' => esc_url($default_redirect),
                )
            );
        }
    }
    add_action('init', 'mj_member_register_login_component_assets', 5);
}

if (!function_exists('mj_member_enqueue_login_component_assets')) {
    function mj_member_enqueue_login_component_assets() {
        wp_enqueue_style('mj-member-login-component');
        wp_enqueue_script('mj-member-login-component');
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
        $default_account_section = array(
            'section' => 'profile',
        );
        $contact_capability = Config::contactCapability() ?: 'mj_manage_contact_messages';

        $default_links = array(
            'profile' => array(
                'label' => __('Mes données personnelles', 'mj-member'),
                'slug' => 'mon-compte',
                'query' => $default_account_section,
                'enabled' => true,
                'page_id' => 0,
                'visibility' => 'all',
                'editable_label' => true,
                'type' => 'standard',
            ),
            'photos' => array(
                'label' => __('Mes photos', 'mj-member'),
                'slug' => 'mes-photos',
                'query' => array('section' => 'photos'),
                'enabled' => true,
                'page_id' => 0,
                'visibility' => 'all',
                'editable_label' => true,
                'type' => 'standard',
            ),
            'registrations' => array(
                'label' => __('Mes inscriptions', 'mj-member'),
                'slug' => 'inscriptions',
                'query' => array('section' => 'registrations'),
                'enabled' => true,
                'page_id' => 0,
                'visibility' => 'all',
                'editable_label' => true,
                'type' => 'standard',
            ),
            'animateur_events' => array(
                'label' => __('Gestion des événements', 'mj-member'),
                'slug' => 'animateurs',
                'query' => array('section' => 'animateur_events'),
                'enabled' => true,
                'page_id' => 0,
                'visibility' => 'animateur',
                'editable_label' => true,
                'type' => 'standard',
            ),
            'animateur_members' => array(
                'label' => __('Gestion des membres', 'mj-member'),
                'slug' => 'animateurs',
                'query' => array('section' => 'animateur_members'),
                'enabled' => true,
                'page_id' => 0,
                'visibility' => 'animateur',
                'editable_label' => true,
                'type' => 'standard',
            ),
            'contact_messages' => array(
                'label' => __('Messages', 'mj-member'),
                'slug' => 'messages',
                'query' => array('section' => 'contact_messages'),
                'enabled' => true,
                'page_id' => 0,
                'visibility' => 'all',
                'editable_label' => true,
                'type' => 'standard',
                'requires_capability' => $contact_capability,
            ),
            'logout' => array(
                'label' => __('Déconnexion', 'mj-member'),
                'slug' => '',
                'query' => array(),
                'enabled' => true,
                'page_id' => 0,
                'visibility' => 'all',
                'editable_label' => false,
                'type' => 'logout',
            ),
        );

        return apply_filters('mj_member_login_component_default_account_links', $default_links);
    }
}

if (!function_exists('mj_member_login_component_get_account_link_settings')) {
    function mj_member_login_component_get_account_link_settings() {
        $defaults = mj_member_login_component_get_default_link_settings();
        $saved = get_option('mj_account_links_settings', array());

        if (!is_array($saved)) {
            $saved = array();
        }

        foreach ($defaults as $key => $config) {
            $saved_row = isset($saved[$key]) && is_array($saved[$key]) ? $saved[$key] : array();

            $defaults[$key]['enabled'] = isset($saved_row['enabled']) ? (bool) $saved_row['enabled'] : (!empty($config['enabled']));

            if (!empty($config['editable_label'])) {
                $label = isset($saved_row['label']) ? sanitize_text_field($saved_row['label']) : '';
                if ($label !== '') {
                    $defaults[$key]['label'] = $label;
                }
            }

            $page_id = isset($saved_row['page_id']) ? (int) $saved_row['page_id'] : 0;
            $defaults[$key]['page_id'] = $page_id > 0 ? $page_id : 0;
        }

        return $defaults;
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
        if (!class_exists('MjContactMessages')) {
            return 0;
        }

        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            $user_id = get_current_user_id();
        }

        if ($user_id <= 0) {
            return 0;
        }

        $args = wp_parse_args($overrides, array(
            'include_all_targets' => true,
            'extra_targets' => array(),
            'skip_capability_check' => false,
        ));

        $contact_capability = Config::contactCapability();
        if (!$args['skip_capability_check'] && $contact_capability !== '') {
            if (!user_can($user_id, $contact_capability)) {
                return 0;
            }
        }

        $count = (int) MjContactMessages::count_unread_for_user($user_id, $args);

        /**
         * Filtre le décompte des messages non lus affiché dans les liens de compte.
         *
         * @param int   $count   Nombre de messages non lus.
         * @param int   $user_id Identifiant utilisateur ciblé.
         * @param array $args    Arguments supplémentaires passés à la requête.
         */
        return (int) apply_filters('mj_member_account_links_unread_total', $count, $user_id, $args);
    }
}

if (!function_exists('mj_member_login_component_get_account_links')) {
    function mj_member_login_component_get_account_links($account_base, $args) {
        $account_base = esc_url_raw($account_base);
        $logout_redirect = apply_filters('mj_member_login_component_logout_redirect', home_url('/'), $args);

        $current_user = is_user_logged_in() ? wp_get_current_user() : null;
        $current_member = null;
        $is_animateur = false;

        if ($current_user instanceof WP_User && function_exists('mj_member_get_member_for_user')) {
            $current_member = mj_member_get_member_for_user($current_user->ID);
        }

        if (!$current_member && function_exists('mj_member_get_current_member')) {
            $current_member = mj_member_get_current_member();
        }

        $current_member_id = 0;
        if ($current_member && isset($current_member->id)) {
            $current_member_id = (int) $current_member->id;
        }

        if ($current_member && isset($current_member->role)) {
            $member_role = sanitize_key((string) $current_member->role);
            $animateur_role = 'animateur';
            if (class_exists('MjMembers_CRUD')) {
                $animateur_role = sanitize_key((string) MjMembers_CRUD::ROLE_ANIMATEUR);
            }

            $is_animateur = ($member_role === $animateur_role);
        }

        $current_user_email = ($current_user instanceof WP_User && !empty($current_user->user_email))
            ? sanitize_email($current_user->user_email)
            : '';
        $allow_contact_owner_view = $current_member_id > 0 || $current_user_email !== '';

        $configured_links = mj_member_login_component_get_account_link_settings();
        $preview_mode = !empty($args['preview_mode']);
        if (!$preview_mode && function_exists('mj_member_login_component_is_preview_mode')) {
            $preview_mode = mj_member_login_component_is_preview_mode();
        }

        $contact_capability = Config::contactCapability();
        $unread_contact_count = array_key_exists('unread_contact_count', $args) ? (int) $args['unread_contact_count'] : null;

        if ($unread_contact_count === null) {
            if ($current_user instanceof WP_User) {
                $user_has_capability = ($contact_capability === '' || current_user_can($contact_capability));
                $should_allow_owner_count = $allow_contact_owner_view && !$user_has_capability && $contact_capability !== '';

                if ($user_has_capability || $preview_mode || $should_allow_owner_count) {
                    if (function_exists('mj_member_login_component_get_unread_contact_message_count')) {
                        $helper_args = array();

                        if ($preview_mode || $should_allow_owner_count) {
                            $helper_args['skip_capability_check'] = true;
                        }

                        if ($should_allow_owner_count) {
                            if ($current_member_id > 0) {
                                $helper_args['member_id'] = $current_member_id;
                            }

                            if ($current_user_email !== '') {
                                $helper_args['sender_email'] = $current_user_email;
                            }
                        }

                        $unread_contact_count = mj_member_login_component_get_unread_contact_message_count($current_user->ID, $helper_args);
                    } else {
                        $unread_contact_count = 0;
                    }
                } else {
                    $unread_contact_count = 0;
                }
            } elseif ($preview_mode) {
                $unread_contact_count = (int) apply_filters('mj_member_contact_messages_preview_unread_total', 2, $args);
            } else {
                $unread_contact_count = 0;
            }
        }

        $unread_contact_count = max(0, (int) $unread_contact_count);

        $links = array();

        foreach ($configured_links as $key => $config) {
            $enabled = isset($config['enabled']) ? (bool) $config['enabled'] : true;
            if (!$enabled) {
                continue;
            }

            $required_capability = isset($config['requires_capability']) ? (string) $config['requires_capability'] : '';
            if ($required_capability !== '') {
                $has_capability = current_user_can($required_capability);
                $owner_override = ($key === 'contact_messages' && $allow_contact_owner_view);

                if (!$preview_mode && !$has_capability && !$owner_override) {
                    continue;
                }
            }

            $visibility = isset($config['visibility']) ? $config['visibility'] : 'all';
            if ($visibility === 'animateur' && !$is_animateur) {
                continue;
            }

            $label = isset($config['label']) ? trim((string) $config['label']) : '';
            if ($label === '') {
                continue;
            }

            $type = isset($config['type']) ? $config['type'] : 'standard';

            $badge = 0;

            if ($type === 'logout') {
                $links[] = array(
                    'key' => sanitize_key($key),
                    'label' => $label,
                    'url' => esc_url_raw(wp_logout_url($logout_redirect)),
                    'is_logout' => true,
                    'badge' => 0,
                );
                continue;
            }

            $page_id = isset($config['page_id']) ? (int) $config['page_id'] : 0;
            $query = isset($config['query']) && is_array($config['query']) ? $config['query'] : array();

            $normalized_query = array();
            foreach ($query as $query_key => $query_value) {
                $normalized_key = sanitize_key((string) $query_key);
                if ($normalized_key === '' || (!is_string($query_value) && !is_numeric($query_value))) {
                    continue;
                }
                $normalized_query[$normalized_key] = is_string($query_value)
                    ? sanitize_text_field($query_value)
                    : $query_value;
            }

            if (!isset($normalized_query['section'])) {
                $section_key = sanitize_key($key);
                if ($section_key !== '') {
                    $normalized_query['section'] = $section_key;
                }
            }

            $url = '';
            if ($page_id > 0) {
                $permalink = get_permalink($page_id);
                if (!empty($permalink)) {
                    $url = esc_url_raw(add_query_arg($normalized_query, $permalink));
                }
            }

            if ($url === '') {
                $slug = isset($config['slug']) ? (string) $config['slug'] : '';
                $url = mj_member_login_component_resolve_account_link($slug, $account_base, $normalized_query);
            }

            if ($key === 'contact_messages') {
                $badge = $unread_contact_count;
                if ($badge > 0) {
                    $base_label = trim(preg_replace('/\s*\(\d+\)\s*$/', '', $label));
                    if ($base_label === '') {
                        $base_label = $label;
                    }
                    $label = sprintf('%s (%d)', $base_label, $badge);
                }
            }

            $links[] = array(
                'key' => sanitize_key($key),
                'label' => $label,
                'url' => $url,
                'is_logout' => false,
                'badge' => $badge,
            );
        }

        $links = apply_filters('mj_member_login_component_account_links', $links, $current_user, $args, $account_base);

        $sanitized = array();
        foreach ($links as $link) {
            if (empty($link['label']) || empty($link['url'])) {
                continue;
            }
            $sanitized[] = array(
                'key' => isset($link['key']) ? sanitize_key($link['key']) : '',
                'label' => wp_strip_all_tags($link['label']),
                'url' => esc_url($link['url']),
                'is_logout' => !empty($link['is_logout']),
                'badge' => isset($link['badge']) ? (int) $link['badge'] : 0,
            );
        }

        if (empty($sanitized)) {
            $sanitized[] = array(
                'key' => 'account',
                'label' => __('Mon compte', 'mj-member'),
                'url' => esc_url($account_base),
                'is_logout' => false,
                'badge' => 0,
            );
        }

        return $sanitized;
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
            'account_modal_title' => __('Mon espace membre', 'mj-member'),
            'account_modal_description' => '',
            'redirect' => '',
            'alignment' => '',
            'extra_class' => '',
            'registration_link_label' => __('Pas encore de compte ? Inscrivez-vous', 'mj-member'),
            'button_icon_html' => '',
        );
        $args = wp_parse_args($args, $defaults);

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

        $contact_capability = Config::contactCapability();
        $unread_contact_count = 0;
        $account_button_label = $args['button_label_logged_in'] !== '' ? $args['button_label_logged_in'] : $defaults['button_label_logged_in'];

        $current_user = null;
        $member = null;
        $member_display_name = '';
        $member_role_key = '';
        $member_role_label = '';
        $member_avatar = array(
            'url' => '',
            'id' => 0,
        );

        if ($is_logged_in) {
            $component_classes[] = 'is-logged-in';
            $current_user = wp_get_current_user();
            if ($current_user instanceof WP_User && function_exists('mj_member_get_member_for_user')) {
                $member = mj_member_get_member_for_user($current_user->ID);
            }

            if ($current_user instanceof WP_User) {
                $should_check_messages = ($contact_capability === '' || current_user_can($contact_capability));
                if ($should_check_messages && function_exists('mj_member_login_component_get_unread_contact_message_count')) {
                    $unread_contact_count = (int) mj_member_login_component_get_unread_contact_message_count($current_user->ID);
                }
            }

            if ($member && property_exists($member, 'role')) {
                $member_role_key = sanitize_key((string) $member->role);
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

            if (class_exists('MjMembers_CRUD')) {
                $role_labels = MjMembers_CRUD::getRoleLabels();
                if (is_array($role_labels) && $member_role_key !== '' && isset($role_labels[$member_role_key])) {
                    $member_role_label = sanitize_text_field($role_labels[$member_role_key]);
                }
            }

            $member_display_name = mj_member_login_component_get_member_display_name($current_user, $member);
            $member_avatar = mj_member_login_component_get_member_avatar($current_user, $member);
        }

        if ($preview_mode && !$is_logged_in) {
            $unread_contact_count = (int) apply_filters('mj_member_contact_messages_preview_unread_total', 2, array('context' => 'login_component'));
        }

        if ($unread_contact_count > 0) {
            $base_label = trim(preg_replace('/\s*\(\d+\)\s*$/', '', $account_button_label));
            if ($base_label === '') {
                $base_label = $account_button_label;
            }
            $account_button_label = sprintf('%s (%d)', $base_label, $unread_contact_count);
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
            $link_args['unread_contact_count'] = $unread_contact_count;
            $link_args['preview_mode'] = $preview_mode;
            $account_links = mj_member_login_component_get_account_links($redirect_url, $link_args);
        }
        

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

        $trigger_classes = array('mj-member-login-component__trigger');
        if ($button_icon_html !== '') {
            $trigger_classes[] = 'mj-member-login-component__trigger--has-icon';
        }
        if ($is_logged_in) {
            $trigger_classes[] = 'mj-member-login-component__trigger--account';
        }

        $modal_classes = array('mj-member-login-component__modal');
        $modal_should_open = !$is_logged_in && !empty($errors);
        if ($modal_should_open) {
            $modal_classes[] = 'is-active';
        }

        $wrapper_attributes = ' data-mj-member-login';
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

        ob_start();
        ?>
        <div class="<?php echo esc_attr(implode(' ', $component_classes)); ?>"<?php echo $wrapper_attributes; ?>>
            <?php if ($is_logged_in) : ?>
                <?php
                $logged_in_label = $member_display_name !== ''
                    ? $member_display_name
                    : __('Profil membre', 'mj-member');
                $logged_in_aria = $member_display_name !== ''
                    ? sprintf(__('Accéder au profil de %s', 'mj-member'), $member_display_name)
                    : __('Accéder à votre profil membre', 'mj-member');
                if ($unread_contact_count > 0) {
                    $logged_in_aria .= ' ' . sprintf(_n('Vous avez %d message non lu.', 'Vous avez %d messages non lus.', $unread_contact_count, 'mj-member'), $unread_contact_count);
                }
                ?>
                <a class="<?php echo esc_attr(implode(' ', $trigger_classes)); ?>" href="<?php echo esc_url($redirect_url); ?>" aria-label="<?php echo esc_attr($logged_in_aria); ?>">
                    <span class="mj-member-login-component__trigger-visual" aria-hidden="true">
                        <span class="mj-member-login-component__trigger-avatar">
                            <?php if ($member_avatar['url'] !== '') : ?>
                                <img src="<?php echo esc_url($member_avatar['url']); ?>" alt="<?php echo esc_attr($member_avatar_alt); ?>" loading="lazy" />
                            <?php elseif ($member_initials !== '') : ?>
                                <span class="mj-member-login-component__trigger-avatar-placeholder"><?php echo esc_html($member_initials); ?></span>
                            <?php else : ?>
                                <span class="mj-member-login-component__trigger-avatar-placeholder" aria-hidden="true">?</span>
                            <?php endif; ?>
                        </span>
                    </span>
                    <span class="mj-member-login-component__trigger-info">
                        <span class="mj-member-login-component__trigger-label"><?php echo esc_html($account_button_label); ?></span>
                        <?php if ($logged_in_label !== '') : ?>
                            <span class="mj-member-login-component__trigger-name"><?php echo esc_html($logged_in_label); ?></span>
                        <?php endif; ?>
                    </span>
                </a>
            <?php else : ?>
                <button type="button" class="<?php echo esc_attr(implode(' ', $trigger_classes)); ?>" data-mj-login-trigger data-target="<?php echo esc_attr($modal_id); ?>">
                    <?php if ($button_icon_html !== '') : ?>
                        <span class="mj-member-login-component__trigger-icon" aria-hidden="true"><?php echo $button_icon_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                    <?php endif; ?>
                    <span class="mj-member-login-component__trigger-label"><?php echo esc_html($button_label); ?></span>
                </button>
                <div id="<?php echo esc_attr($modal_id); ?>" class="<?php echo esc_attr(implode(' ', $modal_classes)); ?>" aria-hidden="<?php echo $modal_should_open ? 'false' : 'true'; ?>" role="dialog" aria-labelledby="<?php echo esc_attr($modal_id); ?>-title">
                    <div class="mj-member-login-component__backdrop" data-mj-login-close></div>
                    <div class="mj-member-login-component__dialog" role="document">
                        <button type="button" class="mj-member-login-component__close" data-mj-login-close aria-label="<?php esc_attr_e('Fermer la fenêtre de connexion', 'mj-member'); ?>">
                            <span aria-hidden="true">&times;</span>
                        </button>
                        <div class="mj-member-login-component__header">
                            <h3 id="<?php echo esc_attr($modal_id); ?>-title" class="mj-member-login-component__title"><?php echo esc_html($modal_title); ?></h3>
                            <?php if (!empty($modal_description)) : ?>
                                <p class="mj-member-login-component__description"><?php echo esc_html($modal_description); ?></p>
                            <?php endif; ?>
                        </div>
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
                    </div>
                </div>
            <?php endif; ?>
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
