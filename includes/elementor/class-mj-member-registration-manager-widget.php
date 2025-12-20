<?php

if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;

class Mj_Member_Elementor_Registration_Manager_Widget extends Widget_Base {
    use Mj_Member_Elementor_Widget_Visibility;

    public function get_name() {
        return 'mj-member-registration-manager';
    }

    public function get_title() {
        return __('Gestionnaire d\'inscriptions', 'mj-member');
    }

    public function get_icon() {
        return 'eicon-table-of-contents';
    }

    public function get_categories() {
        return array('mj-member');
    }

    public function get_keywords() {
        return array('mj', 'member', 'registration', 'inscriptions', 'events', 'participants', 'presence', 'animateur');
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
                'default' => __('Gestion des inscriptions', 'mj-member'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'description',
            array(
                'label' => __('Description', 'mj-member'),
                'type' => Controls_Manager::TEXTAREA,
                'rows' => 3,
                'placeholder' => __('Gérez les inscriptions et la présence aux événements.', 'mj-member'),
            )
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_display',
            array(
                'label' => __('Affichage', 'mj-member'),
            )
        );

        $this->add_control(
            'show_all_events',
            array(
                'label' => __('Afficher tous les événements', 'mj-member'),
                'description' => __('Si désactivé, seuls les événements assignés à l\'utilisateur seront affichés.', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => '',
            )
        );

        $this->add_control(
            'show_past_events',
            array(
                'label' => __('Afficher les événements passés', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->add_control(
            'events_per_page',
            array(
                'label' => __('Événements par page', 'mj-member'),
                'type' => Controls_Manager::NUMBER,
                'default' => 20,
                'min' => 5,
                'max' => 100,
            )
        );

        $this->add_control(
            'default_filter',
            array(
                'label' => __('Filtre par défaut', 'mj-member'),
                'type' => Controls_Manager::SELECT,
                'default' => 'assigned',
                'options' => array(
                    'assigned' => __('Assignés', 'mj-member'),
                    'upcoming' => __('À venir', 'mj-member'),
                    'past' => __('Passés', 'mj-member'),
                    'draft' => __('Brouillons', 'mj-member'),
                    'internal' => __('Internes', 'mj-member'),
                    'all' => __('Tous', 'mj-member'),
                ),
            )
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_permissions',
            array(
                'label' => __('Permissions', 'mj-member'),
            )
        );

        $this->add_control(
            'allow_manual_payment',
            array(
                'label' => __('Autoriser la validation manuelle des paiements', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->add_control(
            'allow_delete_registration',
            array(
                'label' => __('Autoriser la suppression des inscriptions', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->add_control(
            'allow_create_member',
            array(
                'label' => __('Autoriser la création de membres', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->end_controls_section();

        // Section styles
        $this->start_controls_section(
            'section_style_container',
            array(
                'label' => __('Container', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'container_background',
            array(
                'label' => __('Couleur de fond', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => array(
                    '{{WRAPPER}} .mj-registration-manager' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_responsive_control(
            'container_padding',
            array(
                'label' => __('Padding', 'mj-member'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => array('px', 'em', '%'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-registration-manager' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            array(
                'name' => 'container_border',
                'selector' => '{{WRAPPER}} .mj-registration-manager',
            )
        );

        $this->add_control(
            'container_border_radius',
            array(
                'label' => __('Rayon de bordure', 'mj-member'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => array('px', '%'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-registration-manager' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            array(
                'name' => 'container_box_shadow',
                'selector' => '{{WRAPPER}} .mj-registration-manager',
            )
        );

        $this->end_controls_section();

        // Section styles titre
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
                'label' => __('Couleur', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'default' => '#1e293b',
                'selectors' => array(
                    '{{WRAPPER}} .mj-registration-manager__title' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            array(
                'name' => 'title_typography',
                'selector' => '{{WRAPPER}} .mj-registration-manager__title',
            )
        );

        $this->end_controls_section();

        // Section styles boutons
        $this->start_controls_section(
            'section_style_buttons',
            array(
                'label' => __('Boutons', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'button_primary_background',
            array(
                'label' => __('Fond bouton primaire', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'default' => '#3b82f6',
                'selectors' => array(
                    '{{WRAPPER}} .mj-btn--primary' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'button_primary_color',
            array(
                'label' => __('Couleur texte primaire', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => array(
                    '{{WRAPPER}} .mj-btn--primary' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_section();

        $this->register_visibility_controls();
    }

    protected function render() {
        include \Mj\Member\Core\Config::path() . 'includes/templates/elementor/registration_manager.php';
    }

    protected function content_template() {
        ?>
        <div class="mj-registration-manager mj-registration-manager--preview">
            <div class="mj-registration-manager__preview">
                <div class="mj-registration-manager__preview-icon">
                    <span class="dashicons dashicons-groups"></span>
                </div>
                <h3>{{{ settings.title }}}</h3>
                <p><?php esc_html_e('Aperçu du gestionnaire d\'inscriptions (visible uniquement en mode édition).', 'mj-member'); ?></p>
            </div>
        </div>
        <?php
    }
}
