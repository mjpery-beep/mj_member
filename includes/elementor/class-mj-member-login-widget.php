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
        return array('general');
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
                'default' => __('Mon espace membre', 'mj-member'),
                'placeholder' => __('Mon espace membre', 'mj-member'),
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

        $this->start_controls_section(
            'section_style_button',
            array(
                'label' => __('Bouton', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'button_text_color',
            array(
                'label' => __('Couleur du texte', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-login-component__trigger' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .mj-member-login-component__submit' => 'color: {{VALUE}};',
                ),
            )
        );

        if ($this->icons_manager_available()) {
            $this->add_control(
                'button_icon',
                array(
                    'label' => __('Icône du bouton', 'mj-member'),
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
                    'label' => __('Classe CSS de l\'icône', 'mj-member'),
                    'type' => Controls_Manager::TEXT,
                    'placeholder' => 'eicon-lock-user',
                    'default' => 'eicon-lock-user',
                    'description' => __('Utilisez une classe d\'icône Elementor ou Font Awesome (ex. eicon-user, fas fa-user). Laissez vide pour masquer l\'icône.', 'mj-member'),
                )
            );
        }

        $this->add_control(
            'button_icon_color',
            array(
                'label' => __('Couleur de l\'icône', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-login-component__trigger-icon' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .mj-member-login-component__trigger-icon svg' => 'fill: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'button_background_color',
            array(
                'label' => __('Couleur de fond', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-login-component__trigger' => 'background-color: {{VALUE}}; border-color: {{VALUE}};',
                    '{{WRAPPER}} .mj-member-login-component__submit' => 'background-color: {{VALUE}}; border-color: {{VALUE}};',
                    '{{WRAPPER}} .mj-member-login-component__trigger-icon svg' => 'fill: currentColor;',
                ),
            )
        );

        $this->add_control(
            'button_background_color_hover',
            array(
                'label' => __('Fond au survol', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-login-component__trigger:hover' => 'background-color: {{VALUE}}; border-color: {{VALUE}};',
                    '{{WRAPPER}} .mj-member-login-component__submit:hover' => 'background-color: {{VALUE}}; border-color: {{VALUE}};',
                    '{{WRAPPER}} .mj-member-login-component__trigger:hover .mj-member-login-component__trigger-icon svg' => 'fill: currentColor;',
                ),
            )
        );

        if (class_exists('Elementor\\Group_Control_Typography')) {
            $this->add_group_control(
                Group_Control_Typography::get_type(),
                array(
                    'name' => 'button_typography',
                    'selector' => '{{WRAPPER}} .mj-member-login-component__trigger, {{WRAPPER}} .mj-member-login-component__submit',
                )
            );
        }

        if (class_exists('Elementor\\Group_Control_Border')) {
            $this->add_group_control(
                Group_Control_Border::get_type(),
                array(
                    'name' => 'button_border',
                    'selector' => '{{WRAPPER}} .mj-member-login-component__trigger, {{WRAPPER}} .mj-member-login-component__submit',
                )
            );
        }

        if (class_exists('Elementor\\Group_Control_Box_Shadow')) {
            $this->add_group_control(
                Group_Control_Box_Shadow::get_type(),
                array(
                    'name' => 'button_box_shadow',
                    'selector' => '{{WRAPPER}} .mj-member-login-component__trigger, {{WRAPPER}} .mj-member-login-component__submit',
                )
            );
        }

        $this->add_responsive_control(
            'button_padding',
            array(
                'label' => __('Marge interne', 'mj-member'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => array('px', 'em', '%'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-login-component__trigger' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                    '{{WRAPPER}} .mj-member-login-component__submit' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'button_border_radius',
            array(
                'label' => __('Rayon de bordure', 'mj-member'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => array('px', '%'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-login-component__trigger' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                    '{{WRAPPER}} .mj-member-login-component__submit' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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
        );

        echo mj_member_render_login_modal_component($args);
    }
    
    private function build_button_icon_html($settings) {
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

    private function icons_manager_available() {
        return class_exists(Icons_Manager::class) && defined('Elementor\\Controls_Manager::ICON');
    }
}
