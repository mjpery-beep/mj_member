<?php

if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Icons_Manager;
use Elementor\Widget_Base;

class Mj_Member_Elementor_Account_Menu_Widget extends Widget_Base {
    use Mj_Member_Elementor_Widget_Visibility;

    public function get_name() {
        return 'mj-member-account-menu-mobile';
    }

    public function get_title() {
        return __('Menu Mon Compte (mobile)', 'mj-member');
    }

    public function get_icon() {
        return 'eicon-menu-card';
    }

    public function get_categories() {
        return array('general');
    }

    public function get_keywords() {
        return array('mj', 'member', 'menu', 'mobile', 'compte');
    }

    public function get_style_depends() {
        return array('mj-member-login-component');
    }

    public function get_script_depends() {
        return array('mj-member-login-component');
    }

    protected function register_controls() {
        $this->start_controls_section(
            'section_content',
            array(
                'label' => __('Contenu', 'mj-member'),
            )
        );

        $this->add_control(
            'menu_id',
            array(
                'label' => __('Menu WordPress', 'mj-member'),
                'type' => Controls_Manager::SELECT,
                'options' => $this->get_menu_options(),
                'default' => '',
                'label_block' => true,
                'description' => __('Choisissez le menu à afficher pour les versions mobile et desktop.', 'mj-member'),
            )
        );

        $this->add_control(
            'desktop_alignment',
            array(
                'label' => __('Alignement du menu', 'mj-member'),
                'type' => Controls_Manager::CHOOSE,
                'options' => array(
                    'left' => array(
                        'title' => __('Gauche', 'mj-member'),
                        'icon' => 'eicon-text-align-left',
                    ),
                    'center' => array(
                        'title' => __('Centre', 'mj-member'),
                        'icon' => 'eicon-text-align-center',
                    ),
                    'right' => array(
                        'title' => __('Droite', 'mj-member'),
                        'icon' => 'eicon-text-align-right',
                    ),
                ),
                'default' => 'right',
                'toggle' => true,
            )
        );

        $this->add_control(
            'show_submenus',
            array(
                'label' => __('Afficher les sous-menus', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
                'description' => __('Affiche les liens enfants pour les menus hiérarchiques.', 'mj-member'),
            )
        );

        $this->add_control(
            'button_label',
            array(
                'label' => __('Libellé du bouton', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Menu', 'mj-member'),
                'label_block' => true,
            )
        );

        $icon_control_added = false;
        if ($this->icons_manager_available()) {
            $this->add_control(
                'button_icon',
                array(
                    'label' => __('Icône', 'mj-member'),
                    'type' => Controls_Manager::ICON,
                    'fa4compatibility' => 'button_icon',
                )
            );
            $icon_control_added = true;
        }

        if (!$icon_control_added) {
            $this->add_control(
                'button_icon_class',
                array(
                    'label' => __('Classe CSS de l’icône', 'mj-member'),
                    'type' => Controls_Manager::TEXT,
                    'placeholder' => __('ex: fas fa-bars', 'mj-member'),
                    'label_block' => true,
                )
            );
        }

        $this->add_control(
            'button_icon_image',
            array(
                'label' => __('Image du bouton', 'mj-member'),
                'type' => Controls_Manager::MEDIA,
                'dynamic' => array('active' => true),
                'description' => __('Téléversez une image pour remplacer l’icône du bouton.', 'mj-member'),
            )
        );

        $this->add_control(
            'panel_title',
            array(
                'label' => __('Titre du panneau', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Mon espace MJ', 'mj-member'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'panel_description',
            array(
                'label' => __('Texte introductif', 'mj-member'),
                'type' => Controls_Manager::TEXTAREA,
                'rows' => 3,
                'placeholder' => __('Accédez rapidement à vos liens de compte.', 'mj-member'),
            )
        );

        $this->add_control(
            'mobile_only',
            array(
                'label' => __('Masquer le panneau modal sur desktop', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->add_control(
            'event_button_heading',
            array(
                'label' => __('Bouton Événement', 'mj-member'),
                'type' => Controls_Manager::HEADING,
                'separator' => 'before',
            )
        );

        $this->add_control(
            'event_button_label',
            array(
                'label' => __('Libellé du bouton', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Événements', 'mj-member'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'event_button_url',
            array(
                'label' => __('Lien de la page Événements', 'mj-member'),
                'type' => Controls_Manager::URL,
                'placeholder' => home_url('/evenements'),
                'show_external' => true,
                'dynamic' => array('active' => true),
            )
        );

        $this->add_control(
            'event_button_image',
            array(
                'label' => __('Image du bouton', 'mj-member'),
                'type' => Controls_Manager::MEDIA,
                'dynamic' => array('active' => true),
                'description' => __('Téléversez une image pour remplacer l’icône du bouton.', 'mj-member'),
            )
        );

        $this->end_controls_section();

        $this->register_visibility_controls();

        $this->start_controls_section(
            'section_style_button',
            array(
                'label' => __('Bouton', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        if (class_exists(Group_Control_Typography::class)) {
            $this->add_group_control(
                Group_Control_Typography::get_type(),
                array(
                    'name' => 'button_typography',
                    'selector' => '{{WRAPPER}} .mj-member-account-menu .mj-member-login-component__trigger-label',
                )
            );
        }

        $this->add_control(
            'account_menu_button_text_color',
            array(
                'label' => __('Couleur du texte', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-account-menu .mj-member-login-component__trigger' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_responsive_control(
            'account_menu_button_image_size',
            array(
                'label' => __('Taille de l’image', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px', 'rem'),
                'range' => array(
                    'px' => array('min' => 16, 'max' => 120, 'step' => 1),
                    'rem' => array('min' => 0.5, 'max' => 7, 'step' => 0.1),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-account-menu .mj-member-login-component__trigger-icon' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                ),
                'condition' => array(
                    'button_icon_image[url]!' => '',
                ),
            )
        );

        $this->add_control(
            'account_menu_button_background_color',
            array(
                'label' => __('Couleur de fond', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-account-menu .mj-member-login-component__trigger' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'account_menu_border_color',
            array(
                'label' => __('Couleur de bordure', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-account-menu .mj-member-login-component__trigger' => 'border-color: {{VALUE}};',
                ),
            )
        );

        $this->add_responsive_control(
            'account_menu_border_width',
            array(
                'label' => __('Épaisseur de bordure', 'mj-member'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => array('px'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-account-menu .mj-member-login-component__trigger' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'account_menu_button_border_radius',
            array(
                'label' => __('Rayon de bordure', 'mj-member'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => array('px', '%'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-account-menu .mj-member-login-component__trigger' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'account_menu_button_padding',
            array(
                'label' => __('Marge interne', 'mj-member'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => array('px', 'em'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-account-menu .mj-member-login-component__trigger' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        if (class_exists(Group_Control_Box_Shadow::class)) {
            $this->add_group_control(
                Group_Control_Box_Shadow::get_type(),
                array(
                    'name' => 'account_menu_button_shadow',
                    'selector' => '{{WRAPPER}} .mj-member-account-menu .mj-member-login-component__trigger',
                )
            );
        }

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_panel',
            array(
                'label' => __('Panneau', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'account_menu_panel_background_color',
            array(
                'label' => __('Fond du panneau', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-account-menu .mj-member-login-component__panel-inner' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'account_menu_panel_text_color',
            array(
                'label' => __('Couleur du texte', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-account-menu .mj-member-login-component__panel-inner' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .mj-member-account-menu .mj-member-login-component__account-link' => 'color: {{VALUE}};',
                ),
            )
        );

        if (class_exists(Group_Control_Typography::class)) {
            $this->add_group_control(
                Group_Control_Typography::get_type(),
                array(
                    'name' => 'account_menu_panel_title_typography',
                    'selector' => '{{WRAPPER}} .mj-member-account-menu .mj-member-login-component__title',
                )
            );

            $this->add_group_control(
                Group_Control_Typography::get_type(),
                array(
                    'name' => 'account_menu_modal_links_typography',
                    'selector' => '{{WRAPPER}} .mj-member-account-menu--layout-modal .mj-member-login-component__account-link',
                )
            );
        }

        $this->add_responsive_control(
            'account_menu_panel_padding',
            array(
                'label' => __('Marge interne', 'mj-member'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => array('px', 'em'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-account-menu .mj-member-login-component__panel-inner' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_desktop',
            array(
                'label' => __('Menu', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        if (class_exists(Group_Control_Typography::class)) {
            $this->add_group_control(
                Group_Control_Typography::get_type(),
                array(
                    'name' => 'desktop_menu_typography',
                    'selector' => '{{WRAPPER}} .mj-member-account-menu--layout-desktop .mj-member-account-menu__link',
                )
            );
        }

        $this->add_control(
            'desktop_link_color',
            array(
                'label' => __('Couleur des liens', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-account-menu--layout-desktop .mj-member-account-menu__link' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'desktop_link_hover_color',
            array(
                'label' => __('Couleur au survol', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-account-menu--layout-desktop .mj-member-account-menu__link:hover' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .mj-member-account-menu--layout-desktop .mj-member-account-menu__link:focus' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .mj-member-account-menu--layout-desktop .mj-member-account-menu__link:focus-visible' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_responsive_control(
            'desktop_menu_gap',
            array(
                'label' => __('Espacement des liens', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'range' => array(
                    'px' => array('min' => 0, 'max' => 80),
                ),
                'size_units' => array('px'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-account-menu--layout-desktop .mj-member-account-menu__list' => 'gap: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'desktop_link_padding',
            array(
                'label' => __('Marge interne des liens', 'mj-member'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => array('px', 'em'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-account-menu--layout-desktop .mj-member-account-menu__link' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'desktop_submenu_background',
            array(
                'label' => __('Fond du sous-menu', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-account-menu--layout-desktop .mj-member-account-menu__submenu' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'desktop_submenu_link_color',
            array(
                'label' => __('Couleur des liens du sous-menu', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-account-menu--layout-desktop .mj-member-account-menu__submenu .mj-member-account-menu__link' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'desktop_submenu_link_hover_color',
            array(
                'label' => __('Couleur au survol (sous-menu)', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-account-menu--layout-desktop .mj-member-account-menu__submenu .mj-member-account-menu__link:hover' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .mj-member-account-menu--layout-desktop .mj-member-account-menu__submenu .mj-member-account-menu__link:focus' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .mj-member-account-menu--layout-desktop .mj-member-account-menu__submenu .mj-member-account-menu__link:focus-visible' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_responsive_control(
            'desktop_submenu_padding',
            array(
                'label' => __('Marge interne du sous-menu', 'mj-member'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => array('px', 'em'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-account-menu--layout-desktop .mj-member-account-menu__submenu' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        if (class_exists(Group_Control_Box_Shadow::class)) {
            $this->add_group_control(
                Group_Control_Box_Shadow::get_type(),
                array(
                    'name' => 'desktop_submenu_shadow',
                    'selector' => '{{WRAPPER}} .mj-member-account-menu--layout-desktop .mj-member-account-menu__submenu',
                )
            );
        }

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_event_button',
            array(
                'label' => __('Bouton Événement', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        if (class_exists(Group_Control_Typography::class)) {
            $this->add_group_control(
                Group_Control_Typography::get_type(),
                array(
                    'name' => 'event_button_typography',
                    'selector' => '{{WRAPPER}} .mj-member-account-menu__event-button',
                )
            );
        }

        $this->add_control(
            'event_button_text_color',
            array(
                'label' => __('Couleur du texte', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-account-menu__event-button' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .mj-member-account-menu__event-button .mj-member-account-menu__event-button-icon-shape' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .mj-member-account-menu__event-button .mj-member-account-menu__event-button-icon-shape svg' => 'fill: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'event_button_background_color',
            array(
                'label' => __('Couleur de fond', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-account-menu__event-button' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'event_button_hover_background_color',
            array(
                'label' => __('Couleur de fond (hover)', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-account-menu__event-button:hover' => 'background-color: {{VALUE}};',
                    '{{WRAPPER}} .mj-member-account-menu__event-button:focus-visible' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'event_button_hover_text_color',
            array(
                'label' => __('Couleur du texte (hover)', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-account-menu__event-button:hover' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .mj-member-account-menu__event-button:focus-visible' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .mj-member-account-menu__event-button:hover .mj-member-account-menu__event-button-icon-shape' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .mj-member-account-menu__event-button:focus-visible .mj-member-account-menu__event-button-icon-shape' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .mj-member-account-menu__event-button:hover .mj-member-account-menu__event-button-icon-shape svg' => 'fill: {{VALUE}};',
                    '{{WRAPPER}} .mj-member-account-menu__event-button:focus-visible .mj-member-account-menu__event-button-icon-shape svg' => 'fill: {{VALUE}};',
                ),
            )
        );

        if (class_exists(Group_Control_Border::class)) {
            $this->add_group_control(
                Group_Control_Border::get_type(),
                array(
                    'name' => 'event_button_border',
                    'selector' => '{{WRAPPER}} .mj-member-account-menu__event-button',
                )
            );
        }

        $this->add_responsive_control(
            'event_button_border_radius',
            array(
                'label' => __('Rayon de bordure', 'mj-member'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => array('px', '%'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-account-menu__event-button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'event_button_padding',
            array(
                'label' => __('Marge interne', 'mj-member'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => array('px', 'em', 'rem'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-account-menu__event-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        if (class_exists(Group_Control_Box_Shadow::class)) {
            $this->add_group_control(
                Group_Control_Box_Shadow::get_type(),
                array(
                    'name' => 'event_button_shadow',
                    'selector' => '{{WRAPPER}} .mj-member-account-menu__event-button',
                )
            );
        }

        $this->add_responsive_control(
            'event_button_image_size',
            array(
                'label' => __('Taille de l’image', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px', 'rem'),
                'range' => array(
                    'px' => array('min' => 16, 'max' => 200),
                    'rem' => array('min' => 0.5, 'max' => 12),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-account-menu' => '--mj-member-account-menu-event-image-size: {{SIZE}}{{UNIT}};',
                ),
                'condition' => array(
                    'event_button_image[url]!' => '',
                ),
            )
        );

        $this->end_controls_section();
    }

    protected function render() {
        if (!function_exists('mj_member_render_account_menu_component') && !function_exists('mj_member_render_account_menu_mobile_component')) {
            echo '<div class="mj-member-account-warning">' . esc_html__('Le module MJ Member doit être actif pour utiliser ce widget.', 'mj-member') . '</div>';
            return;
        }

        $settings = $this->get_settings_for_display();
        $this->apply_visibility_to_wrapper($settings, 'mj-member-account-menu-widget');

        $mobile_only = !isset($settings['mobile_only']) || $settings['mobile_only'] === 'yes';
        $event_button = $this->prepare_event_button_settings($settings);

        $base_args = array(
            'menu_id' => isset($settings['menu_id']) ? (int) $settings['menu_id'] : 0,
            'button_label' => isset($settings['button_label']) ? $settings['button_label'] : '',
            'panel_title' => isset($settings['panel_title']) ? $settings['panel_title'] : '',
            'panel_description' => isset($settings['panel_description']) ? $settings['panel_description'] : '',
            'icon_html' => $this->build_button_icon_html($settings),
            'preview_items' => $this->get_preview_menu_items(),
            'desktop_alignment' => isset($settings['desktop_alignment']) ? $settings['desktop_alignment'] : 'right',
            'show_submenus' => !isset($settings['show_submenus']) || $settings['show_submenus'] !== 'no',
            'event_button' => $event_button,
        );

        if (function_exists('mj_member_render_account_menu_component')) {
            echo mj_member_render_account_menu_component(array_merge($base_args, array(
                'layout' => 'desktop',
                'mobile_only' => false,
            )));

            echo mj_member_render_account_menu_component(array_merge($base_args, array(
                'layout' => 'modal',
                'mobile_only' => $mobile_only,
            )));

        } else {
            echo mj_member_render_account_menu_mobile_component(array_merge($base_args, array(
                'mobile_only' => $mobile_only,
            )));
        }
    }

    /**
     * @return array<string,string>
     */
    private function get_menu_options() {
        $options = array(
            '' => __('— Sélectionner —', 'mj-member'),
        );

        $menus = wp_get_nav_menus();
        if (!empty($menus)) {
            foreach ($menus as $menu) {
                if (!is_object($menu) || empty($menu->term_id)) {
                    continue;
                }
                $options[(string) $menu->term_id] = $menu->name;
            }
        }

        return $options;
    }

    private function get_preview_menu_items() {
        return array(
            array('title' => __('Tableau de bord', 'mj-member'), 'url' => '#'),
            array('title' => __('Mes réservations', 'mj-member'), 'url' => '#'),
            array('title' => __('Mes informations', 'mj-member'), 'url' => '#'),
        );
    }

    private function prepare_event_button_settings(array $settings) {
        $label = '';
        if (isset($settings['event_button_label'])) {
            $label = sanitize_text_field((string) $settings['event_button_label']);
        }

        $url_data = array();
        if (isset($settings['event_button_url']) && is_array($settings['event_button_url'])) {
            $url_data = $settings['event_button_url'];
        }

        $url = '';
        if (!empty($url_data['url']) && is_string($url_data['url'])) {
            $url = esc_url_raw($url_data['url']);
        }

        $is_external = !empty($url_data['is_external']);
        $nofollow = !empty($url_data['nofollow']);

        $icon_type = 'icon';
        $icon_html = $this->build_event_button_icon_html($settings, $label, $icon_type);

        return array(
            'label' => $label,
            'url' => $url,
            'is_external' => $is_external,
            'nofollow' => $nofollow,
            'icon_html' => $icon_html,
            'icon_type' => $icon_type,
        );
    }

    private function build_button_icon_html($settings) {
        $media = $this->get_media_control_value($settings, 'button_icon_image');
        if ($media !== null) {
            $label = isset($settings['button_label']) ? sanitize_text_field((string) $settings['button_label']) : '';
            $alt_text = $media['alt'] !== '' ? $media['alt'] : $label;
            $alt_text = sanitize_text_field($alt_text);

            $attributes = array(
                'class' => 'mj-member-login-component__trigger-icon-image',
                'loading' => 'lazy',
                'decoding' => 'async',
                'aria-hidden' => 'true',
            );

            if ($alt_text !== '') {
                $attributes['alt'] = $alt_text;
            } else {
                $attributes['alt'] = '';
            }

            if ($media['id'] > 0 && function_exists('wp_get_attachment_image')) {
                $image_html = wp_get_attachment_image($media['id'], 'full', false, $attributes);
                if (is_string($image_html) && trim($image_html) !== '') {
                    return trim($image_html);
                }
            }

            if ($media['url'] !== '') {
                return sprintf(
                    '<img src="%1$s" alt="%2$s" class="mj-member-login-component__trigger-icon-image" loading="lazy" decoding="async" aria-hidden="true" />',
                    esc_url($media['url']),
                    esc_attr($alt_text)
                );
            }
        }

        return $this->build_icon_html(
            $settings,
            'button_icon',
            'button_icon_class',
            'mj-member-login-component__trigger-icon-shape'
        );
    }

    private function build_event_button_icon_html($settings, $label = '', &$icon_type = null) {
        if ($icon_type !== null) {
            $icon_type = 'icon';
        }

        $media = $this->get_media_control_value($settings, 'event_button_image');
        if ($media !== null) {
            if ($icon_type !== null) {
                $icon_type = 'image';
            }

            $alt = $media['alt'] !== '' ? $media['alt'] : $label;
            $alt = sanitize_text_field($alt);

            $attributes = array(
                'class' => 'mj-member-account-menu__event-button-image',
                'loading' => 'lazy',
            );

            if ($alt !== '') {
                $attributes['alt'] = $alt;
            } else {
                $attributes['alt'] = '';
            }

            if ($media['id'] > 0 && function_exists('wp_get_attachment_image')) {
                $image_html = wp_get_attachment_image($media['id'], 'full', false, $attributes);
                if (is_string($image_html) && trim($image_html) !== '') {
                    return $image_html;
                }
            }

            $src = esc_url($media['url']);
            if ($src !== '') {
                return sprintf(
                    '<img src="%1$s" alt="%2$s" class="mj-member-account-menu__event-button-image" loading="lazy" />',
                    $src,
                    esc_attr($alt)
                );
            }
        }

        $icon_html = $this->build_icon_html(
            $settings,
            'event_button_icon',
            'event_button_icon_class',
            'mj-member-account-menu__event-button-icon-shape'
        );

        if ($icon_html !== '') {
            return $icon_html;
        }

        if ($icon_type !== null) {
            $icon_type = 'icon';
        }

        return '<i class="mj-member-account-menu__event-button-icon-shape fas fa-calendar-alt" aria-hidden="true"></i>';
    }

    private function build_icon_html(array $settings, $icon_key, $icon_class_key, $base_class) {
        if ($this->icons_manager_available()) {
            $icon_setting = isset($settings[$icon_key]) ? $settings[$icon_key] : null;

            if (is_array($icon_setting) && !empty($icon_setting['value'])) {
                if (method_exists(Icons_Manager::class, 'enqueue_font')) {
                    Icons_Manager::enqueue_font($icon_setting);
                }

                ob_start();
                $rendered = Icons_Manager::render_icon(
                    $icon_setting,
                    array(
                        'aria-hidden' => 'true',
                        'class' => $base_class,
                    )
                );
                $icon_html = ob_get_clean();

                if (is_string($rendered) && trim($rendered) !== '') {
                    $icon_html = $rendered;
                }

                if (is_string($icon_html)) {
                    $icon_html = trim($icon_html);
                }

                if (!empty($icon_html)) {
                    if (strpos($icon_html, $base_class) === false) {
                        $replaced = 0;
                        $icon_html = preg_replace(
                            '/^(<\w+\s+[^>]*class=")([^">]*)"/i',
                            '$1$2 ' . $base_class . '"',
                            $icon_html,
                            1,
                            $replaced
                        );

                        if (empty($replaced)) {
                            $icon_html = preg_replace(
                                '/^(<\w+)/i',
                                '$1 class="' . $base_class . '"',
                                $icon_html,
                                1
                            );
                        }
                    }

                    if (strpos($icon_html, 'aria-hidden') === false) {
                        $icon_html = preg_replace(
                            '/^(<\w+\s+)/i',
                            '$1aria-hidden="true" ',
                            $icon_html,
                            1
                        );
                    }

                    return $icon_html;
                }
            } elseif (is_string($icon_setting) && trim($icon_setting) !== '') {
                return sprintf(
                    '<i class="%s %s" aria-hidden="true"></i>',
                    esc_attr($base_class),
                    esc_attr(trim($icon_setting))
                );
            }
        }

        $icon_class = isset($settings[$icon_class_key]) ? trim((string) $settings[$icon_class_key]) : '';
        if ($icon_class === '') {
            return '';
        }

        return sprintf(
            '<i class="%s %s" aria-hidden="true"></i>',
            esc_attr($base_class),
            esc_attr($icon_class)
        );
    }

    /**
     * @return array{id:int,url:string,alt:string}|null
     */
    private function get_media_control_value(array $settings, $key) {
        if (!isset($settings[$key]) || !is_array($settings[$key])) {
            return null;
        }

        $media = $settings[$key];
        $id = isset($media['id']) ? (int) $media['id'] : 0;

        $url = '';
        if (!empty($media['url']) && is_string($media['url'])) {
            $url = esc_url_raw($media['url']);
        } elseif ($id > 0 && function_exists('wp_get_attachment_url')) {
            $attachment_url = wp_get_attachment_url($id);
            if (is_string($attachment_url) && $attachment_url !== '') {
                $url = esc_url_raw($attachment_url);
            }
        }

        if ($url === '') {
            return null;
        }

        $alt = '';
        if (!empty($media['alt']) && is_string($media['alt'])) {
            $alt = sanitize_text_field($media['alt']);
        }

        if ($alt === '' && $id > 0 && function_exists('get_post_meta')) {
            $stored_alt = get_post_meta($id, '_wp_attachment_image_alt', true);
            if (is_string($stored_alt) && $stored_alt !== '') {
                $alt = sanitize_text_field($stored_alt);
            }
        }

        return array(
            'id' => $id,
            'url' => $url,
            'alt' => $alt,
        );
    }

    private function icons_manager_available() {
        return class_exists(Icons_Manager::class) && defined('Elementor\\Controls_Manager::ICON');
    }
}
