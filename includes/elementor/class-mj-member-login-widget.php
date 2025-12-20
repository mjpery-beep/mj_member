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

class Mj_Member_Elementor_Login_Widget extends Widget_Base {
    use Mj_Member_Elementor_Widget_Visibility;

    public function get_name() {
        return 'mj-member-login-button';
    }

    public function get_title() {
        return __('Bouton Connexion MJ', 'mj-member');
    }

    public function get_icon() {
        return 'eicon-lock-user';
    }

    public function get_categories() {
        return array('mj-member');
    }

    public function get_keywords() {
        return array('login', 'connexion', 'mj', 'member');
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
            'login_button_text',
            array(
                'label' => __('Texte du bouton (déconnecté)', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Se connecter', 'mj-member'),
                'placeholder' => __('Se connecter', 'mj-member'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'account_button_text',
            array(
                'label' => __('Texte du bouton (connecté)', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Accéder à mon compte', 'mj-member'),
                'placeholder' => __('Accéder à mon compte', 'mj-member'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'modal_title',
            array(
                'label' => __('Titre de la fenêtre', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Connexion à mon compte', 'mj-member'),
                'placeholder' => __('Connexion à mon compte', 'mj-member'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'modal_description',
            array(
                'label' => __('Texte d\'introduction', 'mj-member'),
                'type' => Controls_Manager::TEXTAREA,
                'rows' => 3,
                'placeholder' => __('Entrez vos identifiants pour accéder à votre espace.', 'mj-member'),
            )
        );

        $this->add_control(
            'account_modal_title',
            array(
                'label' => __('Titre du menu (connecté)', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => '',
                'placeholder' => __('Ex : Bienvenue {{member_name}}', 'mj-member'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'account_modal_description',
            array(
                'label' => __('Texte du menu (connecté)', 'mj-member'),
                'type' => Controls_Manager::TEXTAREA,
                'rows' => 3,
                'placeholder' => __('Retrouvez vos inscriptions et vos informations personnelles.', 'mj-member'),
            )
        );

        $this->add_control(
            'modal_submit_text',
            array(
                'label' => __('Texte du bouton de connexion', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Connexion', 'mj-member'),
                'placeholder' => __('Connexion', 'mj-member'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'registration_link_text',
            array(
                'label' => __('Texte du lien d\'inscription', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Pas encore de compte ? Inscrivez-vous', 'mj-member'),
                'placeholder' => __('Pas encore de compte ? Inscrivez-vous', 'mj-member'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'redirect_url',
            array(
                'label' => __('Lien "Mon compte"', 'mj-member'),
                'type' => Controls_Manager::URL,
                'placeholder' => home_url('/mon-compte'),
                'show_external' => false,
                'dynamic' => array('active' => true),
            )
        );

        $this->add_control(
            'button_alignment',
            array(
                'label' => __('Alignement du bouton', 'mj-member'),
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
                    'stretch' => array(
                        'title' => __('Pleine largeur', 'mj-member'),
                        'icon' => 'eicon-text-align-justify',
                    ),
                ),
                'toggle' => true,
                'default' => '',
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

        if ($this->icons_manager_available()) {
            $this->add_control(
                'button_icon',
                array(
                    'label' => __('Icône du bouton (mode "Connexion")', 'mj-member'),
                    'type' => Controls_Manager::ICON,
                    'fa4compatibility' => 'button_icon',
                    'default' => array(
                        'value' => 'eicon-lock-user',
                        'library' => 'eicons',
                    ),
                )
            );
        } else {
            $this->add_control(
                'button_icon_class',
                array(
                    'label' => __('Classe CSS de l\'icône (mode "Connexion")', 'mj-member'),
                    'type' => Controls_Manager::TEXT,
                    'placeholder' => 'eicon-lock-user',
                    'default' => 'eicon-lock-user',
                    'description' => __('Utilisez une classe d\'icône Elementor ou Font Awesome (ex. eicon-user, fas fa-user). Laissez vide pour masquer l\'icône.', 'mj-member'),
                )
            );
        }

        $this->add_control(
            'login_button_image',
            array(
                'label' => __('Image du bouton (mode "Connexion")', 'mj-member'),
                'type' => Controls_Manager::MEDIA,
                'description' => __('Sélectionnez une image pour remplacer l\'icône. Laissez vide pour conserver l\'icône.', 'mj-member'),
            )
        );

        $this->add_control(
            'account_button_image',
            array(
                'label' => __('Image du bouton (mode "Mon compte")', 'mj-member'),
                'type' => Controls_Manager::MEDIA,
                'description' => __('Affiche une image à la place de l’avatar lorsque l’utilisateur est connecté.', 'mj-member'),
            )
        );

        $this->start_controls_tabs('tabs_button_modes');

        $this->start_controls_tab(
            'tab_button_login_mode',
            array(
                'label' => __('Mode "Connexion"', 'mj-member'),
            )
        );

        $this->add_control(
            'login_button_text_color',
            array(
                'label' => __('Couleur du texte', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-login-component__trigger[data-login-state="logged-out"]' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .mj-member-login-component__submit' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_responsive_control(
            'login_button_image_display_size',
            array(
                'label' => __('Taille de l\'image', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px', 'rem'),
                'range' => array(
                    'px' => array('min' => 16, 'max' => 96, 'step' => 1),
                    'rem' => array('min' => 0.5, 'max' => 6, 'step' => 0.1),
                ),
                'default' => array('size' => 28, 'unit' => 'px'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-login-component' => '--mj-member-login-trigger-icon-size: {{SIZE}}{{UNIT}};',
                ),
                'condition' => array(
                    'login_button_image[url]!' => '',
                ),
            )
        );

        $this->add_responsive_control(
            'account_button_image_display_size',
            array(
                'label' => __('Taille de l\'image (mode "Mon compte")', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px', 'rem'),
                'range' => array(
                    'px' => array('min' => 20, 'max' => 160, 'step' => 1),
                    'rem' => array('min' => 1, 'max' => 8, 'step' => 0.1),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-login-component' => '--mj-member-login-trigger-account-image-size: {{SIZE}}{{UNIT}};',
                ),
                'condition' => array(
                    'account_button_image[url]!' => '',
                ),
            )
        );

        $this->add_control(
            'login_button_icon_color',
            array(
                'label' => __('Couleur de l\'icône', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-login-component__trigger[data-login-state="logged-out"] .mj-member-login-component__trigger-icon' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .mj-member-login-component__trigger[data-login-state="logged-out"] .mj-member-login-component__trigger-icon svg' => 'fill: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'login_button_background_color',
            array(
                'label' => __('Couleur de fond', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-login-component__trigger[data-login-state="logged-out"]' => 'background-color: {{VALUE}}; border-color: {{VALUE}};',
                    '{{WRAPPER}} .mj-member-login-component__submit' => 'background-color: {{VALUE}}; border-color: {{VALUE}};',
                    '{{WRAPPER}} .mj-member-login-component__trigger[data-login-state="logged-out"] .mj-member-login-component__trigger-icon svg' => 'fill: currentColor;',
                ),
            )
        );

        $this->add_control(
            'login_button_background_color_hover',
            array(
                'label' => __('Fond au survol', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-login-component__trigger[data-login-state="logged-out"]:hover' => 'background-color: {{VALUE}}; border-color: {{VALUE}};',
                    '{{WRAPPER}} .mj-member-login-component__submit:hover' => 'background-color: {{VALUE}}; border-color: {{VALUE}};',
                    '{{WRAPPER}} .mj-member-login-component__trigger[data-login-state="logged-out"]:hover .mj-member-login-component__trigger-icon svg' => 'fill: currentColor;',
                ),
            )
        );

        $this->add_control(
            'login_button_transition_duration',
            array(
                'label' => __('Durée de la transition (s)', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('s'),
                'range' => array(
                    's' => array('min' => 0, 'max' => 2, 'step' => 0.05),
                ),
                'default' => array('size' => 0.25, 'unit' => 's'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-login-component__trigger[data-login-state="logged-out"]' => 'transition: background-color {{SIZE}}{{UNIT}} ease, border-color {{SIZE}}{{UNIT}} ease, transform {{SIZE}}{{UNIT}} ease;',
                    '{{WRAPPER}} .mj-member-login-component__submit' => 'transition: background-color {{SIZE}}{{UNIT}} ease, border-color {{SIZE}}{{UNIT}} ease, transform {{SIZE}}{{UNIT}} ease;',
                ),
            )
        );

        $this->add_responsive_control(
            'login_button_hover_translate',
            array(
                'label' => __('Effet au survol (décalage Y)', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array(
                    'px' => array('min' => -20, 'max' => 20, 'step' => 1),
                ),
                'default' => array('size' => -2, 'unit' => 'px'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-login-component__trigger[data-login-state="logged-out"]:hover' => 'transform: translateY({{SIZE}}{{UNIT}});',
                    '{{WRAPPER}} .mj-member-login-component__submit:hover' => 'transform: translateY({{SIZE}}{{UNIT}});',
                ),
            )
        );

        if (class_exists('Elementor\\Group_Control_Typography')) {
            $this->add_group_control(
                Group_Control_Typography::get_type(),
                array(
                    'name' => 'login_button_typography',
                    'selector' => '{{WRAPPER}} .mj-member-login-component__trigger[data-login-state="logged-out"], {{WRAPPER}} .mj-member-login-component__submit',
                )
            );
        }

        if (class_exists('Elementor\\Group_Control_Border')) {
            $this->add_group_control(
                Group_Control_Border::get_type(),
                array(
                    'name' => 'login_button_border',
                    'selector' => '{{WRAPPER}} .mj-member-login-component__trigger[data-login-state="logged-out"], {{WRAPPER}} .mj-member-login-component__submit',
                )
            );
        }

        if (class_exists('Elementor\\Group_Control_Box_Shadow')) {
            $this->add_group_control(
                Group_Control_Box_Shadow::get_type(),
                array(
                    'name' => 'login_button_box_shadow',
                    'selector' => '{{WRAPPER}} .mj-member-login-component__trigger[data-login-state="logged-out"], {{WRAPPER}} .mj-member-login-component__submit',
                )
            );
        }

        $this->add_responsive_control(
            'login_button_padding',
            array(
                'label' => __('Marge interne', 'mj-member'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => array('px', 'em', '%'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-login-component__trigger[data-login-state="logged-out"]' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                    '{{WRAPPER}} .mj-member-login-component__submit' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'login_button_border_radius',
            array(
                'label' => __('Rayon de bordure', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px', '%'),
                'range' => array(
                    'px' => array('min' => 0, 'max' => 100, 'step' => 1),
                    '%' => array('min' => 0, 'max' => 100),
                ),
                'default' => array('size' => 50, 'unit' => 'px'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-login-component__trigger[data-login-state="logged-out"]' => 'border-radius: {{SIZE}}{{UNIT}};',
                    '{{WRAPPER}} .mj-member-login-component__submit' => 'border-radius: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->end_controls_tab();

        $this->start_controls_tab(
            'tab_button_account_mode',
            array(
                'label' => __('Mode "Mon compte"', 'mj-member'),
            )
        );

        $this->add_control(
            'account_button_text_color',
            array(
                'label' => __('Couleur du texte', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-login-component__trigger[data-login-state="logged-in"]' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .mj-member-login-component__trigger[data-login-state="logged-in"] .mj-member-login-component__trigger-label' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .mj-member-login-component__trigger[data-login-state="logged-in"] .mj-member-login-component__trigger-name' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'account_button_background_color',
            array(
                'label' => __('Couleur de fond', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-login-component__trigger[data-login-state="logged-in"]' => 'background-color: {{VALUE}}; border-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'account_button_background_color_hover',
            array(
                'label' => __('Fond au survol', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-login-component__trigger[data-login-state="logged-in"]:hover' => 'background-color: {{VALUE}}; border-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'account_button_transition_duration',
            array(
                'label' => __('Durée de la transition (s)', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('s'),
                'range' => array(
                    's' => array('min' => 0, 'max' => 2, 'step' => 0.05),
                ),
                'default' => array('size' => 0.25, 'unit' => 's'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-login-component__trigger[data-login-state="logged-in"]' => 'transition: background-color {{SIZE}}{{UNIT}} ease, border-color {{SIZE}}{{UNIT}} ease, transform {{SIZE}}{{UNIT}} ease;',
                ),
            )
        );

        $this->add_responsive_control(
            'account_button_hover_translate',
            array(
                'label' => __('Effet au survol (décalage Y)', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array(
                    'px' => array('min' => -20, 'max' => 20, 'step' => 1),
                ),
                'default' => array('size' => -2, 'unit' => 'px'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-login-component__trigger[data-login-state="logged-in"]:hover' => 'transform: translateY({{SIZE}}{{UNIT}});',
                ),
            )
        );

        if (class_exists('Elementor\\Group_Control_Typography')) {
            $this->add_group_control(
                Group_Control_Typography::get_type(),
                array(
                    'name' => 'account_button_typography',
                    'selector' => '{{WRAPPER}} .mj-member-login-component__trigger[data-login-state="logged-in"]',
                )
            );
        }

        if (class_exists('Elementor\\Group_Control_Border')) {
            $this->add_group_control(
                Group_Control_Border::get_type(),
                array(
                    'name' => 'account_button_border',
                    'selector' => '{{WRAPPER}} .mj-member-login-component__trigger[data-login-state="logged-in"]',
                )
            );
        }

        if (class_exists('Elementor\\Group_Control_Box_Shadow')) {
            $this->add_group_control(
                Group_Control_Box_Shadow::get_type(),
                array(
                    'name' => 'account_button_box_shadow',
                    'selector' => '{{WRAPPER}} .mj-member-login-component__trigger[data-login-state="logged-in"]',
                )
            );
        }

        $this->add_responsive_control(
            'account_button_padding',
            array(
                'label' => __('Marge interne', 'mj-member'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => array('px', 'em', '%'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-login-component__trigger[data-login-state="logged-in"]' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'account_button_border_radius',
            array(
                'label' => __('Rayon de bordure', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px', '%'),
                'range' => array(
                    'px' => array('min' => 0, 'max' => 100, 'step' => 1),
                    '%' => array('min' => 0, 'max' => 100),
                ),
                'default' => array('size' => 50, 'unit' => 'px'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-login-component__trigger[data-login-state="logged-in"]' => 'border-radius: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_avatar',
            array(
                'label' => __('Avatar', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_responsive_control(
            'avatar_size',
            array(
                'label' => __('Taille de l\'avatar', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px', 'em', 'rem'),
                'default' => array(
                    'size' => 48,
                    'unit' => 'px',
                ),
                'range' => array(
                    'px' => array('min' => 24, 'max' => 160),
                    'em' => array('min' => 1.5, 'max' => 10),
                    'rem' => array('min' => 1.5, 'max' => 10),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-login-component__trigger-visual' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}}; padding: calc({{SIZE}}{{UNIT}} * 0.08);',
                    '{{WRAPPER}} .mj-member-login-component__trigger-avatar' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                    '{{WRAPPER}} .mj-member-login-component__trigger-avatar img' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                    '{{WRAPPER}} .mj-member-login-component__trigger-avatar-placeholder' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}}; line-height: {{SIZE}}{{UNIT}};',
                    '{{WRAPPER}} .mj-member-login-component__member-avatar' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                    '{{WRAPPER}} .mj-member-login-component__member-avatar img' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                    '{{WRAPPER}} .mj-member-account-links__avatar-placeholder' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}}; line-height: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_modal',
            array(
                'label' => __('Fenêtre', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'modal_background',
            array(
                'label' => __('Fond de la fenêtre', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-login-component__dialog' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'modal_text_color',
            array(
                'label' => __('Couleur du texte', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-login-component__dialog' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'modal_backdrop_color',
            array(
                'label' => __('Fond de l\'arrière-plan', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-login-component__backdrop' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'modal_title_color',
            array(
                'label' => __('Couleur du titre', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-login-component__title' => 'color: {{VALUE}};',
                ),
            )
        );

        if (class_exists('Elementor\\Group_Control_Typography')) {
            $this->add_group_control(
                Group_Control_Typography::get_type(),
                array(
                    'name' => 'modal_title_typography',
                    'selector' => '{{WRAPPER}} .mj-member-login-component__title',
                )
            );
        }

        $this->add_control(
            'modal_description_color',
            array(
                'label' => __('Couleur du texte d\'introduction', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-login-component__description' => 'color: {{VALUE}};',
                ),
            )
        );

        if (class_exists('Elementor\\Group_Control_Typography')) {
            $this->add_group_control(
                Group_Control_Typography::get_type(),
                array(
                    'name' => 'modal_description_typography',
                    'selector' => '{{WRAPPER}} .mj-member-login-component__description',
                )
            );
        }

        $this->add_control(
            'modal_label_color',
            array(
                'label' => __('Couleur des labels', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-login-component__field label' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'modal_input_background',
            array(
                'label' => __('Fond des champs', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-login-component__field input' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'modal_input_border_color',
            array(
                'label' => __('Bordure des champs', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-login-component__field input' => 'border-color: {{VALUE}};',
                ),
            )
        );

        $this->add_responsive_control(
            'modal_input_border_radius',
            array(
                'label' => __('Rayon des champs', 'mj-member'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => array('px', '%'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-login-component__field input' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'modal_link_color',
            array(
                'label' => __('Couleur des liens', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-login-component__link' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'modal_close_color',
            array(
                'label' => __('Couleur du bouton fermer', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-login-component__close' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_responsive_control(
            'modal_padding',
            array(
                'label' => __('Marge interne de la fenêtre', 'mj-member'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => array('px', 'em', '%'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-login-component__dialog' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'modal_border_radius',
            array(
                'label' => __('Rayon de la fenêtre', 'mj-member'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => array('px', '%'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-login-component__dialog' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        if (class_exists('Elementor\\Group_Control_Box_Shadow')) {
            $this->add_group_control(
                Group_Control_Box_Shadow::get_type(),
                array(
                    'name' => 'modal_box_shadow',
                    'selector' => '{{WRAPPER}} .mj-member-login-component__dialog',
                )
            );
        }

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $this->apply_visibility_to_wrapper($settings, 'mj-member-login-component');

        $redirect = '';
        if (!empty($settings['redirect_url']['url'])) {
            $redirect = $settings['redirect_url']['url'];
        }

        $alignment = isset($settings['button_alignment']) ? $settings['button_alignment'] : '';
        $allowed_alignments = array('left', 'center', 'right', 'stretch');
        if (!in_array($alignment, $allowed_alignments, true)) {
            $alignment = '';
        }

        $button_icon_html = $this->build_button_icon_html($settings);
        $account_image_html = $this->build_account_button_image_html($settings);

        $args = array(
            'button_label_logged_out' => isset($settings['login_button_text']) ? $settings['login_button_text'] : '',
            'button_label_logged_in' => isset($settings['account_button_text']) ? $settings['account_button_text'] : '',
            'modal_button_label' => isset($settings['modal_submit_text']) ? $settings['modal_submit_text'] : '',
            'modal_title' => isset($settings['modal_title']) ? $settings['modal_title'] : '',
            'modal_description' => isset($settings['modal_description']) ? $settings['modal_description'] : '',
            'account_modal_title' => isset($settings['account_modal_title']) ? $settings['account_modal_title'] : '',
            'account_modal_description' => isset($settings['account_modal_description']) ? $settings['account_modal_description'] : '',
            'redirect' => $redirect,
            'alignment' => $alignment,
            'registration_link_label' => isset($settings['registration_link_text']) ? $settings['registration_link_text'] : '',
            'button_icon_html' => $button_icon_html,
            'account_image_html' => $account_image_html,
        );

        echo mj_member_render_login_modal_component($args);
    }
    
    private function build_button_icon_html($settings) {
        $image_html = $this->build_button_image_html($settings);
        if ($image_html !== '') {
            return $image_html;
        }

        if ($this->icons_manager_available()) {
            $icon_setting = isset($settings['button_icon']) ? $settings['button_icon'] : null;

            if (is_array($icon_setting) && !empty($icon_setting['value'])) {
                if (method_exists(Icons_Manager::class, 'enqueue_font')) {
                    Icons_Manager::enqueue_font($icon_setting);
                }

                ob_start();
                $rendered = Icons_Manager::render_icon(
                    $icon_setting,
                    array(
                        'aria-hidden' => 'true',
                        'class' => 'mj-member-login-component__trigger-icon-shape',
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
                    if (strpos($icon_html, 'mj-member-login-component__trigger-icon-shape') === false) {
                        $replaced = 0;
                        $icon_html = preg_replace(
                            '/^(<\w+\s+[^>]*class=")(.*?)"/i',
                            '$1$2 mj-member-login-component__trigger-icon-shape"',
                            $icon_html,
                            1,
                            $replaced
                        );

                        if (empty($replaced)) {
                            $icon_html = preg_replace(
                                '/^(<\w+)/i',
                                '$1 class="mj-member-login-component__trigger-icon-shape"',
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
                    '<i class="mj-member-login-component__trigger-icon-shape %s" aria-hidden="true"></i>',
                    esc_attr(trim($icon_setting))
                );
            }
        }

        $icon_class = isset($settings['button_icon_class']) ? trim((string) $settings['button_icon_class']) : '';
        if ($icon_class === '') {
            return '';
        }

        return sprintf(
            '<i class="mj-member-login-component__trigger-icon-shape %s" aria-hidden="true"></i>',
            esc_attr($icon_class)
        );
    }

    private function build_button_image_html($settings) {
        if (empty($settings['login_button_image']) || !is_array($settings['login_button_image'])) {
            return '';
        }

        $image_setting = $settings['login_button_image'];
        $image_id = isset($image_setting['id']) ? (int) $image_setting['id'] : 0;
        $image_url = '';
        $alt_text = '';

        if ($image_id > 0) {
            $image_url = wp_get_attachment_image_url($image_id, 'full');
            $stored_alt = get_post_meta($image_id, '_wp_attachment_image_alt', true);
            if (is_string($stored_alt)) {
                $alt_text = trim($stored_alt);
            }
        }

        if ($image_url === '' && !empty($image_setting['url'])) {
            $image_url = $image_setting['url'];
        }

        if ($image_url === '') {
            return '';
        }

        $alt_attribute = $alt_text !== '' ? $alt_text : '';
        $attributes = array(
            'class' => 'mj-member-login-component__trigger-icon-image',
            'loading' => 'lazy',
            'decoding' => 'async',
            'aria-hidden' => 'true',
        );

        if ($alt_attribute !== '') {
            $attributes['alt'] = $alt_attribute;
        } else {
            $attributes['alt'] = '';
        }

        if ($image_id > 0) {
            $image_html = wp_get_attachment_image($image_id, 'full', false, $attributes);
            if (is_string($image_html) && trim($image_html) !== '') {
                return trim($image_html);
            }
        }

        return sprintf(
            '<img src="%s" alt="%s" class="mj-member-login-component__trigger-icon-image" loading="lazy" decoding="async" aria-hidden="true" />',
            esc_url($image_url),
            esc_attr($alt_attribute)
        );
    }

    private function build_account_button_image_html($settings) {
        if (empty($settings['account_button_image']) || !is_array($settings['account_button_image'])) {
            return '';
        }

        $image_setting = $settings['account_button_image'];
        $image_id = isset($image_setting['id']) ? (int) $image_setting['id'] : 0;
        $image_url = '';
        $alt_text = '';

        if ($image_id > 0) {
            $image_url = wp_get_attachment_image_url($image_id, 'full');
            $stored_alt = get_post_meta($image_id, '_wp_attachment_image_alt', true);
            if (is_string($stored_alt)) {
                $alt_text = trim($stored_alt);
            }
        }

        if ($image_url === '' && !empty($image_setting['url'])) {
            $image_url = $image_setting['url'];
        }

        if ($image_url === '') {
            return '';
        }

        $attributes = array(
            'class' => 'mj-member-login-component__trigger-account-image',
            'loading' => 'lazy',
            'decoding' => 'async',
            'aria-hidden' => 'true',
        );

        if ($alt_text !== '') {
            $attributes['alt'] = $alt_text;
        } else {
            $attributes['alt'] = '';
        }

        if ($image_id > 0) {
            $image_html = wp_get_attachment_image($image_id, 'full', false, $attributes);
            if (is_string($image_html) && trim($image_html) !== '') {
                return trim($image_html);
            }
        }

        return sprintf(
            '<img src="%s" alt="%s" class="mj-member-login-component__trigger-account-image" loading="lazy" decoding="async" aria-hidden="true" />',
            esc_url($image_url),
            esc_attr($alt_text)
        );
    }

    private function icons_manager_available() {
        return class_exists(Icons_Manager::class) && defined('Elementor\\Controls_Manager::ICON');
    }
}
