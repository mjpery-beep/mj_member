<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('mj_member_register_login_component_assets')) {
    function mj_member_register_login_component_assets() {
        $version = defined('MJ_MEMBER_VERSION') ? MJ_MEMBER_VERSION : '1.0.0';

        wp_register_style(
            'mj-member-login-component',
            MJ_MEMBER_URL . 'css/login-component.css',
            array(),
            $version
        );

        wp_register_script(
            'mj-member-login-component',
            MJ_MEMBER_URL . 'js/login-component.js',
            array(),
            $version,
            true
        );

        if (function_exists('register_block_type')) {
            wp_register_script(
                'mj-member-login-block-editor',
                MJ_MEMBER_URL . 'js/block-login-button.js',
                array('wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-i18n'),
                $version,
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

if (!function_exists('mj_member_login_component_get_account_links')) {
    function mj_member_login_component_get_account_links($account_base, $args) {
        $account_base = esc_url_raw($account_base);
        $logout_redirect = apply_filters('mj_member_login_component_logout_redirect', home_url('/'), $args);

        $links = array(
            array(
                'key' => 'stages',
                'label' => __('Mes inscriptions stages', 'mj-member'),
                'url' => mj_member_login_component_resolve_account_link('mes-inscriptions-stages', $account_base, array('section' => 'stages')),
            ),
            array(
                'key' => 'profile',
                'label' => __('Mes données personnelles', 'mj-member'),
                'url' => mj_member_login_component_resolve_account_link('mes-donnees-personnelles', $account_base, array('section' => 'profile')),
            ),
            array(
                'key' => 'events',
                'label' => __('Mes inscriptions événements', 'mj-member'),
                'url' => mj_member_login_component_resolve_account_link('mes-inscriptions-evenements', $account_base, array('section' => 'events')),
            ),
            array(
                'key' => 'logout',
                'label' => __('Déconnexion', 'mj-member'),
                'url' => esc_url_raw(wp_logout_url($logout_redirect)),
                'is_logout' => true,
            ),
        );

        $current_user = is_user_logged_in() ? wp_get_current_user() : null;

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
            );
        }

        if (empty($sanitized)) {
            $sanitized[] = array(
                'key' => 'account',
                'label' => __('Mon compte', 'mj-member'),
                'url' => esc_url($account_base),
                'is_logout' => false,
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

        $current_user = null;
        $member = null;
        $member_display_name = '';
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
            $member_display_name = mj_member_login_component_get_member_display_name($current_user, $member);
            $member_avatar = mj_member_login_component_get_member_avatar($current_user, $member);
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
            $account_links = mj_member_login_component_get_account_links($redirect_url, $args);
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

        $modal_classes = array('mj-member-login-component__modal');
        $modal_should_open = !$is_logged_in && !empty($errors);
        if ($modal_should_open) {
            $modal_classes[] = 'is-active';
        }

        ob_start();
        ?>
        <div class="<?php echo esc_attr(implode(' ', $component_classes)); ?>" data-mj-member-login<?php echo $preview_mode ? ' data-preview="1"' : ''; ?>>
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
                    <?php if ($is_logged_in) : ?>
                        <?php if (!empty($member_avatar['url']) || $member_display_name !== '') : ?>
                            <div class="mj-member-login-component__member">
                                <?php if (!empty($member_avatar['url'])) : ?>
                                    <span class="mj-member-login-component__member-avatar">
                                        <img src="<?php echo esc_url($member_avatar['url']); ?>" alt="<?php echo esc_attr($member_avatar_alt); ?>" />
                                    </span>
                                <?php endif; ?>
                                <?php if ($member_display_name !== '') : ?>
                                    <span class="mj-member-login-component__member-info">
                                        <span class="mj-member-login-component__member-name"><?php echo esc_html($member_display_name); ?></span>
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <ul class="mj-member-login-component__account-list">
                            <?php foreach ($account_links as $link) : ?>
                                <li class="mj-member-login-component__account-item">
                                    <a class="mj-member-login-component__account-link" href="<?php echo esc_url($link['url']); ?>">
                                        <span class="mj-member-login-component__account-label"><?php echo esc_html($link['label']); ?></span>
                                        <span class="mj-member-login-component__account-icon" aria-hidden="true">&rsaquo;</span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
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

        require_once MJ_MEMBER_PATH . 'includes/elementor/class-mj-member-login-widget.php';
        if (!class_exists('Mj_Member_Elementor_Profile_Widget', false)) {
            $profile_widget = MJ_MEMBER_PATH . 'includes/elementor/class-mj-member-profile-widget.php';
            if (file_exists($profile_widget)) {
                require_once $profile_widget;
            }
        }

        if (!class_exists('Mj_Member_Elementor_Subscription_Widget', false)) {
            $subscription_widget = MJ_MEMBER_PATH . 'includes/elementor/class-mj-member-subscription-widget.php';
            if (file_exists($subscription_widget)) {
                require_once $subscription_widget;
            }
        }

        if (!class_exists('Mj_Member_Elementor_Registrations_Widget', false)) {
            $registrations_widget = MJ_MEMBER_PATH . 'includes/elementor/class-mj-member-registrations-widget.php';
            if (file_exists($registrations_widget)) {
                require_once $registrations_widget;
            }
        }

        if (!class_exists('Mj_Member_Elementor_Events_Widget', false)) {
            $events_widget = MJ_MEMBER_PATH . 'includes/elementor/class-mj-member-events-widget.php';
            if (file_exists($events_widget)) {
                require_once $events_widget;
            }
        }

        if (!class_exists('Mj_Member_Elementor_Events_Calendar_Widget', false)) {
            $events_calendar_widget = MJ_MEMBER_PATH . 'includes/elementor/class-mj-member-events-calendar-widget.php';
            if (file_exists($events_calendar_widget)) {
                require_once $events_calendar_widget;
            }
        }

        if (!class_exists('Mj_Member_Elementor_Locations_Widget', false)) {
            $locations_widget = MJ_MEMBER_PATH . 'includes/elementor/class-mj-member-locations-widget.php';
            if (file_exists($locations_widget)) {
                require_once $locations_widget;
            }
        }

        if (class_exists('Mj_Member_Elementor_Login_Widget')) {
            $widgets_manager->register(new \Mj_Member_Elementor_Login_Widget());
        }

        if (class_exists('Mj_Member_Elementor_Profile_Widget')) {
            $widgets_manager->register(new \Mj_Member_Elementor_Profile_Widget());
        }

        if (class_exists('Mj_Member_Elementor_Subscription_Widget')) {
            $widgets_manager->register(new \Mj_Member_Elementor_Subscription_Widget());
        }

        if (class_exists('Mj_Member_Elementor_Registrations_Widget')) {
            $widgets_manager->register(new \Mj_Member_Elementor_Registrations_Widget());
        }

        if (class_exists('Mj_Member_Elementor_Events_Widget')) {
            $widgets_manager->register(new \Mj_Member_Elementor_Events_Widget());
        }

        if (class_exists('Mj_Member_Elementor_Events_Calendar_Widget')) {
            $widgets_manager->register(new \Mj_Member_Elementor_Events_Calendar_Widget());
        }

        if (class_exists('Mj_Member_Elementor_Locations_Widget')) {
            $widgets_manager->register(new \Mj_Member_Elementor_Locations_Widget());
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
