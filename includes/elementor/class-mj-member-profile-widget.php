<?php

if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;

class Mj_Member_Elementor_Profile_Widget extends Widget_Base {
    use Mj_Member_Elementor_Widget_Visibility;

    public function get_name() {
        return 'mj-member-profile';
    }

    public function get_title() {
        return __('Mon profil MJ', 'mj-member');
    }

    public function get_icon() {
        return 'eicon-user-circle-o';
    }

    public function get_categories() {
        return array('general');
    }

    public function get_keywords() {
        return array('mj', 'member', 'profil', 'account');
    }

    protected function register_controls() {
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
                'default' => __('Mes informations', 'mj-member'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'description',
            array(
                'label' => __('Description', 'mj-member'),
                'type' => Controls_Manager::TEXTAREA,
                'rows' => 3,
                'placeholder' => __('Mettez à jour vos informations personnelles.', 'mj-member'),
            )
        );

        $this->add_control(
            'button_text',
            array(
                'label' => __('Texte du bouton', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Enregistrer', 'mj-member'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'success_message',
            array(
                'label' => __('Message de succès', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Vos informations ont été mises à jour.', 'mj-member'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'show_children',
            array(
                'label' => __('Afficher les jeunes associés', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'no',
            )
        );

        $this->add_control(
            'show_payments',
            array(
                'label' => __('Afficher l\'historique des paiements', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'no',
            )
        );

        $this->add_control(
            'payment_limit',
            array(
                'label' => __('Nombre de paiements affichés', 'mj-member'),
                'type' => Controls_Manager::NUMBER,
                'min' => 1,
                'max' => 50,
                'default' => 10,
                'condition' => array('show_payments' => 'yes'),
            )
        );

        $this->end_controls_section();

        $this->register_visibility_controls();

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
                    '{{WRAPPER}} .mj-member-account__title' => 'color: {{VALUE}};',
                ),
            )
        );

        if (class_exists('Elementor\\Group_Control_Typography')) {
            $this->add_group_control(
                Group_Control_Typography::get_type(),
                array(
                    'name' => 'title_typography',
                    'selector' => '{{WRAPPER}} .mj-member-account__title',
                )
            );
        }

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
                    '{{WRAPPER}} .mj-member-button' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'button_background',
            array(
                'label' => __('Couleur de fond', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-button' => 'background-color: {{VALUE}}; border-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'button_background_hover',
            array(
                'label' => __('Fond au survol', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-button:hover' => 'background-color: {{VALUE}}; border-color: {{VALUE}};',
                ),
            )
        );

        if (class_exists('Elementor\\Group_Control_Border')) {
            $this->add_group_control(
                Group_Control_Border::get_type(),
                array(
                    'name' => 'button_border',
                    'selector' => '{{WRAPPER}} .mj-member-button',
                )
            );
        }

        if (class_exists('Elementor\\Group_Control_Box_Shadow')) {
            $this->add_group_control(
                Group_Control_Box_Shadow::get_type(),
                array(
                    'name' => 'button_shadow',
                    'selector' => '{{WRAPPER}} .mj-member-button',
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
                    '{{WRAPPER}} .mj-member-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $this->apply_visibility_to_wrapper($settings, 'mj-member-account');

        if (!function_exists('mj_member_render_account_component')) {
            echo '<div class="mj-member-account-warning">' . esc_html__('Le module MJ Member doit être actif pour utiliser ce widget.', 'mj-member') . '</div>';
            return;
        }

        $options = array(
            'title' => isset($settings['title']) ? $settings['title'] : '',
            'description' => isset($settings['description']) ? $settings['description'] : '',
            'submit_label' => isset($settings['button_text']) ? $settings['button_text'] : __('Enregistrer', 'mj-member'),
            'success_message' => isset($settings['success_message']) ? $settings['success_message'] : __('Vos informations ont été mises à jour.', 'mj-member'),
            'show_children' => isset($settings['show_children']) && $settings['show_children'] === 'yes',
            'show_payments' => isset($settings['show_payments']) && $settings['show_payments'] === 'yes',
            'payment_limit' => isset($settings['payment_limit']) ? max(1, (int) $settings['payment_limit']) : 10,
            'form_id' => $this->get_id(),
            'context' => 'elementor',
            'show_membership' => false,
        );

        echo mj_member_render_account_component($options);
    }
}
