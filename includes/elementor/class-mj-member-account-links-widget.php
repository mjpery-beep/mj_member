<?php

if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;
use Mj\Member\Core\AssetsManager;

class Mj_Member_Elementor_Account_Links_Widget extends Widget_Base {
    use Mj_Member_Elementor_Widget_Visibility;

    public function get_name() {
        return 'mj-member-account-links';
    }

    public function get_title() {
        return __('Liens Mon Compte MJ', 'mj-member');
    }

    public function get_icon() {
        return 'eicon-nav-menu';
    }

    public function get_categories() {
        return array('mj-member');
    }

    public function get_keywords() {
        return array('mj', 'member', 'account', 'menu', 'navigation');
    }

    protected function get_pages_control_options() {
        $options = array(
            '' => __('Dynamique (par défaut)', 'mj-member'),
        );

        $pages = get_pages(array(
            'sort_column' => 'post_title',
            'sort_order' => 'ASC',
            'post_status' => 'publish',
        ));

        if (!empty($pages)) {
            foreach ($pages as $page) {
                $options[(string) $page->ID] = $page->post_title;
            }
        }

        return $options;
    }

    protected function get_selected_page_url($value) {
        $page_id = (int) $value;
        if ($page_id <= 0) {
            return '';
        }

        $permalink = get_permalink($page_id);
        return $permalink ? esc_url_raw($permalink) : '';
    }

    protected function get_current_request_url() {
        if (function_exists('mj_member_get_current_url')) {
            $url = mj_member_get_current_url();
            if (is_string($url) && $url !== '') {
                return esc_url_raw($url);
            }
        }

        if (!empty($_SERVER['REQUEST_URI'])) {
            $request_uri = wp_unslash($_SERVER['REQUEST_URI']);
            return esc_url_raw(home_url($request_uri));
        }

        return '';
    }

    protected function normalize_url_for_compare($url) {
        if (!is_string($url) || $url === '') {
            return '';
        }

        $parts = wp_parse_url($url);
        if (!$parts) {
            return '';
        }

        $home_parts = wp_parse_url(home_url('/'));

        $scheme = isset($parts['scheme']) ? strtolower($parts['scheme']) : (isset($home_parts['scheme']) ? strtolower($home_parts['scheme']) : 'http');
        $host = isset($parts['host']) ? strtolower($parts['host']) : (isset($home_parts['host']) ? strtolower($home_parts['host']) : '');

        if ($host === '') {
            return '';
        }

        $port = '';
        if (isset($parts['port'])) {
            $port = ':' . $parts['port'];
        } elseif (isset($home_parts['port']) && !isset($parts['host'])) {
            $port = ':' . $home_parts['port'];
        }

        $path = '';
        if (isset($parts['path'])) {
            $path = $parts['path'];
        } elseif (!empty($home_parts['path'])) {
            $path = $home_parts['path'];
        }

        if ($path === '/') {
            $path = '';
        }

        $path = untrailingslashit($path);

        return $scheme . '://' . $host . $port . $path;
    }

    protected function register_controls() {
        $default_account_url = function_exists('mj_member_get_account_redirect')
            ? mj_member_get_account_redirect()
            : home_url('/mon-compte');

        $this->start_controls_section(
            'section_content',
            array(
                'label' => __('Contenu', 'mj-member'),
            )
        );

        $this->add_control(
            'title',
            array(
                'label' => __('Titre', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Mon espace MJ', 'mj-member'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'intro',
            array(
                'label' => __('Texte introductif', 'mj-member'),
                'type' => Controls_Manager::TEXTAREA,
                'rows' => 3,
                'placeholder' => __('Accédez rapidement à vos actions principales.', 'mj-member'),
            )
        );

        $this->add_control(
            'links_notice',
            array(
                'type' => Controls_Manager::RAW_HTML,
                'raw' => '<em>' . esc_html__('Les liens affichés sont configurés dans MJ Member > Configuration.', 'mj-member') . '</em>',
                'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
            )
        );

        $this->add_control(
            'show_greeting',
            array(
                'label' => __('Afficher le message de bienvenue', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->add_control(
            'greeting_text',
            array(
                'label' => __('Message de bienvenue', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Bienvenue', 'mj-member'),
                'label_block' => true,
                'condition' => array('show_greeting' => 'yes'),
            )
        );

        $this->add_control(
            'show_avatar',
            array(
                'label' => __('Afficher l\'avatar du membre', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->add_control(
            'account_base_url',
            array(
                'label' => __('URL de la page "Mon compte"', 'mj-member'),
                'type' => Controls_Manager::URL,
                'label_block' => true,
                'placeholder' => $default_account_url,
                'default' => array(
                    'url' => $default_account_url,
                ),
            )
        );

        $this->end_controls_section();

        $this->register_visibility_controls();

        $this->start_controls_section(
            'section_style_container',
            array(
                'label' => __('Bloc', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'container_background',
            array(
                'label' => __('Couleur de fond', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-account-links' => 'background-color: {{VALUE}};',
                ),
            )
        );

        if (class_exists('Elementor\\Group_Control_Border')) {
            $this->add_group_control(
                Group_Control_Border::get_type(),
                array(
                    'name' => 'container_border',
                    'selector' => '{{WRAPPER}} .mj-member-account-links',
                )
            );
        }

        if (class_exists('Elementor\\Group_Control_Box_Shadow')) {
            $this->add_group_control(
                Group_Control_Box_Shadow::get_type(),
                array(
                    'name' => 'container_shadow',
                    'selector' => '{{WRAPPER}} .mj-member-account-links',
                )
            );
        }

        $this->add_responsive_control(
            'container_padding',
            array(
                'label' => __('Marge interne', 'mj-member'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => array('px', 'em', '%'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-account-links' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'container_gap',
            array(
                'label' => __('Espacement interne', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array(
                    'px' => array('min' => 0, 'max' => 64),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-account-links' => 'gap: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_title',
            array(
                'label' => __('Titre', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'title_color',
            array(
                'label' => __('Couleur du titre', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-account-links__title' => 'color: {{VALUE}};',
                ),
            )
        );

        if (class_exists('Elementor\\Group_Control_Typography')) {
            $this->add_group_control(
                Group_Control_Typography::get_type(),
                array(
                    'name' => 'title_typography',
                    'selector' => '{{WRAPPER}} .mj-member-account-links__title',
                )
            );
        }

        $this->add_responsive_control(
            'title_spacing',
            array(
                'label' => __('Marge inférieure', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array(
                    'px' => array('min' => 0, 'max' => 60),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-account-links__title' => 'margin-bottom: {{SIZE}}{{UNIT}};',
                ),
                'condition' => array('title!' => ''),
            )
        );

        $this->add_control(
            'intro_color',
            array(
                'label' => __('Couleur du texte introductif', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-account-links__intro' => 'color: {{VALUE}};',
                ),
            )
        );

        if (class_exists('Elementor\\Group_Control_Typography')) {
            $this->add_group_control(
                Group_Control_Typography::get_type(),
                array(
                    'name' => 'intro_typography',
                    'selector' => '{{WRAPPER}} .mj-member-account-links__intro',
                )
            );
        }

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_links',
            array(
                'label' => __('Liens', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        if (class_exists('Elementor\\Group_Control_Typography')) {
            $this->add_group_control(
                Group_Control_Typography::get_type(),
                array(
                    'name' => 'links_typography',
                    'selector' => '{{WRAPPER}} .mj-member-account-links .mj-member-login-component__account-link',
                )
            );
        }

        $this->add_control(
            'link_text_color',
            array(
                'label' => __('Couleur du texte', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-account-links .mj-member-login-component__account-link' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'link_background_color',
            array(
                'label' => __('Couleur de fond', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-account-links .mj-member-login-component__account-link' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'link_border_color',
            array(
                'label' => __('Couleur de bordure', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-account-links .mj-member-login-component__account-link' => 'border-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'link_icon_color',
            array(
                'label' => __('Couleur de l\'icône', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-account-links .mj-member-login-component__account-icon' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'link_hover_text_color',
            array(
                'label' => __('Couleur du texte (survol)', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-account-links .mj-member-login-component__account-link:hover,
                    {{WRAPPER}} .mj-member-account-links .mj-member-login-component__account-link:focus-visible' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'link_hover_background_color',
            array(
                'label' => __('Couleur de fond (survol)', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-account-links .mj-member-login-component__account-link:hover,
                    {{WRAPPER}} .mj-member-account-links .mj-member-login-component__account-link:focus-visible' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'link_hover_border_color',
            array(
                'label' => __('Couleur de bordure (survol)', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-account-links .mj-member-login-component__account-link:hover,
                    {{WRAPPER}} .mj-member-account-links .mj-member-login-component__account-link:focus-visible' => 'border-color: {{VALUE}};',
                ),
            )
        );

        $this->add_responsive_control(
            'links_gap',
            array(
                'label' => __('Espacement entre les liens', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array(
                    'px' => array('min' => 0, 'max' => 48),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-account-links .mj-member-login-component__account-list' => 'gap: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->end_controls_section();
    }

    protected function render() {
        if (!function_exists('mj_member_login_component_get_account_links')) {
            echo '<div class="mj-member-account-warning">' . esc_html__('Le module MJ Member doit être actif pour utiliser ce widget.', 'mj-member') . '</div>';
            return;
        }

        $settings = $this->get_settings_for_display();
    $this->apply_visibility_to_wrapper($settings, 'mj-member-account-links');

        AssetsManager::requirePackage('login-component');

        $preview_mode = function_exists('mj_member_login_component_is_preview_mode')
            ? mj_member_login_component_is_preview_mode()
            : false;

        $is_logged_in = is_user_logged_in();

        $default_account_url = function_exists('mj_member_get_account_redirect')
            ? mj_member_get_account_redirect()
            : home_url('/mon-compte');

        $account_base_setting = isset($settings['account_base_url']['url']) ? $settings['account_base_url']['url'] : '';
        $account_base = $account_base_setting !== '' ? esc_url_raw($account_base_setting) : $default_account_url;

        if (!$is_logged_in && !$preview_mode) {
            $redirect_to = function_exists('mj_member_get_current_url') ? mj_member_get_current_url() : $account_base;
            $login_url = wp_login_url($redirect_to);
            echo '<div class="mj-member-account-warning">' . esc_html__('Connectez-vous pour afficher vos liens "Mon compte".', 'mj-member') . ' <a href="' . esc_url($login_url) . '">' . esc_html__('Se connecter', 'mj-member') . '</a></div>';
            return;
        }

        $member = null;
        if ($is_logged_in) {
            if (function_exists('mj_member_get_current_member')) {
                $member = mj_member_get_current_member();
            }

            if (!$member && !$preview_mode) {
                echo '<div class="mj-member-account-warning">' . esc_html__('Aucun profil MJ n’est associé à votre compte.', 'mj-member') . '</div>';
                return;
            }
        }

        $args = array(
            'context' => 'elementor_account_links_widget',
            'widget_id' => $this->get_id(),
            'preview_mode' => $preview_mode,
        );

        $links = mj_member_login_component_get_account_links($account_base, $args);

        foreach ($links as &$link) {
            $key = isset($link['key']) ? sanitize_key($link['key']) : '';
            if ($key === 'logout') {
                $logout_destination = $account_base !== '' ? $account_base : esc_url_raw(home_url('/'));
                $link['url'] = esc_url(wp_logout_url($logout_destination));
            }
        }
        unset($link);

        if (empty($links)) {
            echo '<div class="mj-member-account-warning">' . esc_html__('Aucun lien n’est disponible pour le moment.', 'mj-member') . '</div>';
            return;
        }

        $current_section = '';
        if (isset($_GET['section'])) {
            $current_section = sanitize_key(wp_unslash($_GET['section']));
        }

        $current_url = $this->get_current_request_url();
        $normalized_current_url = $this->normalize_url_for_compare($current_url);

        if ($preview_mode && $current_section === '' && !empty($links)) {
            $first_link = reset($links);
            if (is_array($first_link) && !empty($first_link['key'])) {
                $current_section = sanitize_key($first_link['key']);
            }
        }

        $display_name = '';
        $avatar_url = '';
        $show_avatar = isset($settings['show_avatar']) && $settings['show_avatar'] === 'yes';
        $show_greeting = isset($settings['show_greeting']) && $settings['show_greeting'] === 'yes';

        if ($preview_mode && !$is_logged_in) {
            $display_name = __('Jeanne Exemple', 'mj-member');
        } elseif ($is_logged_in) {
            if (function_exists('mj_member_login_component_get_member_display_name')) {
                $display_name = mj_member_login_component_get_member_display_name();
            }

            if ($display_name === '') {
                $user = wp_get_current_user();
                if ($user instanceof WP_User) {
                    $display_name = sanitize_text_field($user->display_name !== '' ? $user->display_name : $user->user_login);
                }
            }

            if ($show_avatar && function_exists('mj_member_login_component_get_member_avatar')) {
                $avatar = mj_member_login_component_get_member_avatar();
                if (!empty($avatar['url'])) {
                    $avatar_url = esc_url($avatar['url']);
                }
            }
        }

        $avatar_placeholder = 'MJ';
        if ($display_name !== '') {
            $parts = preg_split('/[\s-]+/', $display_name);
            $initials = '';
            if (is_array($parts)) {
                foreach ($parts as $part) {
                    $part = trim($part);
                    if ($part === '') {
                        continue;
                    }
                    $initial = function_exists('mb_substr') ? mb_substr($part, 0, 1) : substr($part, 0, 1);
                    if ($initial === '') {
                        continue;
                    }
                    $initials .= $initial;
                    if (strlen($initials) >= 2) {
                        break;
                    }
                }
            }
            if ($initials !== '') {
                $avatar_placeholder = function_exists('mb_strtoupper') ? mb_strtoupper($initials) : strtoupper($initials);
            }
        }

        $greeting_template = isset($settings['greeting_text']) && $settings['greeting_text'] !== ''
            ? $settings['greeting_text']
            : __('Bienvenue', 'mj-member');
        $greeting_message = $greeting_template;
        if ($display_name !== '') {
            $greeting_message = strtr($greeting_message, array(
                '{{member_name}}' => $display_name,
                '[member_name]' => $display_name,
            ));
            if ($greeting_message === $greeting_template) {
                $greeting_message = trim($greeting_message . ' ' . $display_name);
            }
        }

        $member_role_key = '';
        if ($preview_mode && !$is_logged_in) {
            // Utiliser MjRoles pour le mode aperçu
            $member_role_key = class_exists('Mj\\Member\\Classes\\MjRoles') 
                ? \Mj\Member\Classes\MjRoles::ANIMATEUR 
                : 'animateur';
        } elseif ($member && isset($member->role) && $member->role !== '') {
            $member_role_key = sanitize_key((string) $member->role);
        }

        $role_class = 'mj-member-login-component--role-default';
        if ($member_role_key !== '') {
            $role_class = 'mj-member-login-component--role-' . sanitize_html_class($member_role_key);
        }

        $wrapper_classes = array('mj-member-account-links', $role_class);

        $show_on_tablet = !isset($settings['show_on_tablet']) || $settings['show_on_tablet'] === 'yes';
        $show_on_mobile = !isset($settings['show_on_mobile']) || $settings['show_on_mobile'] === 'yes';

        if (!$show_on_tablet) {
            $wrapper_classes[] = 'mj-member-account-links--hide-tablet';
        }

        if (!$show_on_mobile) {
            $wrapper_classes[] = 'mj-member-account-links--hide-mobile';
        }

        $wrapper_attributes = array('class="' . esc_attr(implode(' ', $wrapper_classes)) . '"');
        if ($preview_mode) {
            $wrapper_attributes[] = 'data-preview="1"';
        }

        echo '<div ' . implode(' ', $wrapper_attributes) . '>';

        if (!empty($settings['title'])) {
            echo '<h2 class="mj-member-account-links__title">' . esc_html($settings['title']) . '</h2>';
        }

        if (!empty($settings['intro'])) {
            echo '<p class="mj-member-account-links__intro">' . wp_kses_post($settings['intro']) . '</p>';
        }

        if (($show_avatar || $show_greeting) && ($display_name !== '' || $show_avatar)) {
            echo '<div class="mj-member-login-component__member">';
            if ($show_avatar) {
                echo '<span class="mj-member-login-component__member-avatar">';
                if ($avatar_url !== '') {
                    echo '<img src="' . esc_url($avatar_url) . '" alt="' . esc_attr__('Avatar du membre', 'mj-member') . '" />';
                } else {
                    echo '<span class="mj-member-account-links__avatar-placeholder" aria-hidden="true">' . esc_html($avatar_placeholder) . '</span>';
                }
                echo '</span>';
            }

            echo '<span class="mj-member-login-component__member-info">';
            if ($show_greeting) {
                echo '<span class="mj-member-account-links__greeting">' . esc_html($greeting_message) . '</span>';
            }
   
            echo '</span>';
            echo '</div>';
        }

        echo '<ul class="mj-member-login-component__account-list">';
        foreach ($links as $link) {
            $url = isset($link['url']) ? esc_url($link['url']) : '#';
            $label = isset($link['label']) ? $link['label'] : '';
            $key = isset($link['key']) ? sanitize_key($link['key']) : '';
            $badge = isset($link['badge']) ? (int) $link['badge'] : 0;
            $icon_html = '';
            if (!empty($link['icon']) && is_array($link['icon']) && !empty($link['icon']['html'])) {
                $icon_html = $link['icon']['html'];
            }
            if ($label === '' || $url === '') {
                continue;
            }
            $label = wp_strip_all_tags($label);
            if ($key === 'contact_messages') {
                $clean_label = trim(preg_replace('/\s*\(\d+\+?\)\s*$/', '', $label));
                if ($clean_label !== '') {
                    $label = $clean_label;
                }
            }
            $is_current = false;
            if ($current_section !== '' && $key !== '' && $current_section === $key) {
                $is_current = true;
            } elseif ($normalized_current_url !== '') {
                $link_normalized = $this->normalize_url_for_compare($url);
                if ($link_normalized !== '' && $link_normalized === $normalized_current_url) {
                    $is_current = true;
                }
            }

            $item_classes = array('mj-member-login-component__account-item');
            $link_classes = array('mj-member-login-component__account-link');

            if ($is_current) {
                $item_classes[] = 'is-current';
                $link_classes[] = 'is-current';
            }

            if (!empty($link['is_logout'])) {
                $link_classes[] = 'mj-member-login-component__account-link--logout';
            }

            echo '<li class="' . esc_attr(implode(' ', $item_classes)) . '">';
            $link_attributes = ' href="' . $url . '"';
            if ($is_current) {
                $link_attributes .= ' aria-current="page"';
            }
            if (!empty($link['is_logout'])) {
                $link_attributes .= ' rel="nofollow"';
            }
            echo '<a class="' . esc_attr(implode(' ', $link_classes)) . '"' . $link_attributes . '>';
            echo '<span class="mj-member-login-component__account-main">';
            if ($icon_html !== '') {
                echo '<span class="mj-member-account-menu__item-icon">' . $icon_html . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }
            echo '<span class="mj-member-login-component__account-label">' . esc_html($label) . '</span>';
            if ($badge > 0) {
                $badge_display = $badge > 99 ? '99+' : number_format_i18n($badge);
                $badge_label = sprintf(_n('%d notification', '%d notifications', $badge, 'mj-member'), $badge);
                echo '<span class="mj-member-login-component__account-badge" aria-label="' . esc_attr($badge_label) . '">' . esc_html($badge_display) . '</span>';
            }
            echo '</span>';
            echo '<span class="mj-member-login-component__account-icon" aria-hidden="true">&rsaquo;</span>';
            echo '</a>';
            echo '</li>';
        }
        echo '</ul>';

        echo '</div>';
    }
}
