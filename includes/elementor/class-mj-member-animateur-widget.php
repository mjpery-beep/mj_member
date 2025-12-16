<?php

if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;

class Mj_Member_Elementor_Animateur_Widget extends Widget_Base {
    use Mj_Member_Elementor_Widget_Visibility;

    public function get_name() {
        return 'mj-member-animateur';
    }

    public function get_title() {
        return __('Animateur – Participants', 'mj-member');
    }

    public function get_icon() {
        return 'eicon-persons';
    }

    public function get_categories() {
        return array('general');
    }

    public function get_keywords() {
        return array('mj', 'member', 'animateur', 'participants', 'presence');
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
                'default' => __('Mes participants', 'mj-member'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'description',
            array(
                'label' => __('Description', 'mj-member'),
                'type' => Controls_Manager::TEXTAREA,
                'rows' => 3,
                'placeholder' => __('Liste des participants de mes événements.', 'mj-member'),
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
            'show_event_filter',
            array(
                'label' => __('Afficher le filtre des événements', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->add_control(
            'show_occurrence_filter',
            array(
                'label' => __('Afficher le filtre des occurrences', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->add_control(
            'show_attendance_actions',
            array(
                'label' => __('Afficher les actions de présence', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->add_control(
            'show_sms_block',
            array(
                'label' => __('Afficher la zone SMS', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->add_control(
            'show_view_all_button',
            array(
                'label' => __('Afficher le bouton "Voir tous les événements"', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->add_control(
            'view_all_button_behavior',
            array(
                'label' => __('Action du bouton', 'mj-member'),
                'type' => Controls_Manager::SELECT,
                'options' => array(
                    'toggle' => __('Afficher tous les événements', 'mj-member'),
                    'link' => __('Lier les événements à l’utilisateur courant', 'mj-member'),
                ),
                'default' => 'toggle',
                'condition' => array('show_view_all_button' => 'yes'),
            )
        );

        $this->add_control(
            'view_all_button_label',
            array(
                'label' => __('Texte du bouton', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Voir tous les événements', 'mj-member'),
                'label_block' => true,
                'condition' => array('show_view_all_button' => 'yes'),
            )
        );

        $this->add_control(
            'view_all_button_active_label',
            array(
                'label' => __('Texte lorsque tous les événements sont affichés', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Voir mes événements', 'mj-member'),
                'label_block' => true,
                'condition' => array(
                    'show_view_all_button' => 'yes',
                    'view_all_button_behavior' => 'toggle',
                ),
            )
        );

        $this->add_control(
            'view_all_button_url',
            array(
                'label' => __('Lien du bouton', 'mj-member'),
                'type' => Controls_Manager::URL,
                'placeholder' => __('https://example.com', 'mj-member'),
                'show_external' => true,
                'description' => __('Laisser vide pour utiliser le bouton comme bascule entre "mes événements" et "tous les événements".', 'mj-member'),
                'condition' => array(
                    'show_view_all_button' => 'yes',
                    'view_all_button_behavior' => 'link',
                ),
            )
        );

        $this->end_controls_section();

        $this->register_visibility_controls();

        $this->start_controls_section(
            'section_style_general',
            array(
                'label' => __('Style général', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'accent_color',
            array(
                'label' => __('Couleur d\'accent', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-animateur-dashboard' => '--mj-animateur-accent: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'dashboard_background_color',
            array(
                'label' => __('Fond du tableau', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-animateur-dashboard' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'title_color',
            array(
                'label' => __('Couleur du titre', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-animateur-dashboard__title' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            array(
                'name' => 'title_typography',
                'selector' => '{{WRAPPER}} .mj-animateur-dashboard__title',
            )
        );

        $this->add_control(
            'intro_color',
            array(
                'label' => __('Couleur du texte introductif', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-animateur-dashboard__intro' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            array(
                'name' => 'intro_typography',
                'selector' => '{{WRAPPER}} .mj-animateur-dashboard__intro',
            )
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_event_cards',
            array(
                'label' => __('Vignettes événements', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'event_card_background_color',
            array(
                'label' => __('Fond des vignettes', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-animateur-dashboard__event-card' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'event_card_radius',
            array(
                'label' => __('Arrondi des coins', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array(
                    'px' => array('min' => 0, 'max' => 40),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-animateur-dashboard__event-card' => 'border-radius: {{SIZE}}{{UNIT}};',
                    '{{WRAPPER}} .mj-animateur-dashboard__event-card-media' => 'border-radius: calc({{SIZE}}{{UNIT}} - 4px);',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            array(
                'name' => 'event_card_border',
                'selector' => '{{WRAPPER}} .mj-animateur-dashboard__event-card',
            )
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            array(
                'name' => 'event_card_shadow',
                'selector' => '{{WRAPPER}} .mj-animateur-dashboard__event-card',
            )
        );

        $this->add_control(
            'event_card_title_color',
            array(
                'label' => __('Couleur du titre', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-animateur-dashboard__event-card-title' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            array(
                'name' => 'event_card_title_typography',
                'selector' => '{{WRAPPER}} .mj-animateur-dashboard__event-card-title',
            )
        );

        $this->add_control(
            'event_card_meta_color',
            array(
                'label' => __('Couleur des métadonnées', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-animateur-dashboard__event-card-meta' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            array(
                'name' => 'event_card_meta_typography',
                'selector' => '{{WRAPPER}} .mj-animateur-dashboard__event-card-meta, {{WRAPPER}} .mj-animateur-dashboard__event-card-meta-item',
            )
        );

        $this->add_control(
            'event_card_link_color',
            array(
                'label' => __('Couleur du lien', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-animateur-dashboard__event-card-link' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'event_card_link_hover_color',
            array(
                'label' => __('Couleur du lien (survol)', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-animateur-dashboard__event-card-link:hover' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .mj-animateur-dashboard__event-card-link:focus' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'event_card_badge_background',
            array(
                'label' => __('Fond des badges', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-animateur-dashboard__event-card-badge' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'event_card_badge_color',
            array(
                'label' => __('Texte des badges', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-animateur-dashboard__event-card-badge' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_carousel',
            array(
                'label' => __('Navigation carrousel', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'carousel_nav_background',
            array(
                'label' => __('Fond des flèches', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-animateur-dashboard__event-nav' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'carousel_nav_color',
            array(
                'label' => __('Couleur des icônes', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-animateur-dashboard__event-nav' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'carousel_nav_border_color',
            array(
                'label' => __('Couleur de bordure', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-animateur-dashboard__event-nav' => 'border-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'carousel_nav_hover_background',
            array(
                'label' => __('Fond des flèches (survol)', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-animateur-dashboard__event-nav:hover:not(:disabled)' => 'background-color: {{VALUE}};',
                    '{{WRAPPER}} .mj-animateur-dashboard__event-nav:focus-visible' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'carousel_nav_hover_color',
            array(
                'label' => __('Couleur des icônes (survol)', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-animateur-dashboard__event-nav:hover:not(:disabled)' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .mj-animateur-dashboard__event-nav:focus-visible' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_agenda',
            array(
                'label' => __('Agenda', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'agenda_background_color',
            array(
                'label' => __('Fond du bloc', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-animateur-dashboard__agenda' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'agenda_border_color',
            array(
                'label' => __('Bordure du bloc', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-animateur-dashboard__agenda' => 'border-color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            array(
                'name' => 'agenda_item_border',
                'selector' => '{{WRAPPER}} .mj-animateur-dashboard__agenda-item',
            )
        );

        $this->add_control(
            'agenda_item_background',
            array(
                'label' => __('Fond des occurrences', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-animateur-dashboard__agenda-item' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'agenda_item_selected_background',
            array(
                'label' => __('Fond occurrence sélectionnée', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-animateur-dashboard__agenda-item.is-selected' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'agenda_item_label_color',
            array(
                'label' => __('Couleur du libellé', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-animateur-dashboard__agenda-item-label' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            array(
                'name' => 'agenda_item_label_typography',
                'selector' => '{{WRAPPER}} .mj-animateur-dashboard__agenda-item-label',
            )
        );

        $this->add_control(
            'agenda_item_summary_color',
            array(
                'label' => __('Couleur du résumé', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-animateur-dashboard__agenda-item-summary' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_table',
            array(
                'label' => __('Tableau des participants', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'table_header_background',
            array(
                'label' => __('Fond de l\'en-tête', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-animateur-dashboard__table thead th' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'table_header_color',
            array(
                'label' => __('Texte de l\'en-tête', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-animateur-dashboard__table thead th' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            array(
                'name' => 'table_header_typography',
                'selector' => '{{WRAPPER}} .mj-animateur-dashboard__table thead th',
            )
        );

        $this->add_control(
            'table_row_background',
            array(
                'label' => __('Fond des lignes', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-animateur-dashboard__table tbody tr' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'table_text_color',
            array(
                'label' => __('Couleur du texte', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-animateur-dashboard__table tbody td' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            array(
                'name' => 'table_text_typography',
                'selector' => '{{WRAPPER}} .mj-animateur-dashboard__table tbody td',
            )
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_participants',
            array(
                'label' => __('Participants', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'participant_name_color',
            array(
                'label' => __('Couleur du nom', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-animateur-dashboard__participant-name' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            array(
                'name' => 'participant_name_typography',
                'selector' => '{{WRAPPER}} .mj-animateur-dashboard__participant-name',
            )
        );

        $this->add_control(
            'participant_meta_color',
            array(
                'label' => __('Couleur des meta', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-animateur-dashboard__participant-meta' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .mj-animateur-dashboard__participant-meta-separator' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            array(
                'name' => 'participant_meta_typography',
                'selector' => '{{WRAPPER}} .mj-animateur-dashboard__participant-meta',
            )
        );

        $this->add_control(
            'participant_action_background',
            array(
                'label' => __('Fond des actions', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-animateur-dashboard__participant-action' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'participant_action_color',
            array(
                'label' => __('Texte des actions', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-animateur-dashboard__participant-action' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'participant_action_heading_color',
            array(
                'label' => __('Titre des actions', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-animateur-dashboard__participant-action strong' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            array(
                'name' => 'participant_action_typography',
                'selector' => '{{WRAPPER}} .mj-animateur-dashboard__participant-action',
            )
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_attendance',
            array(
                'label' => __('Présence', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'attendance_chip_background',
            array(
                'label' => __('Fond des boutons', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-animateur-dashboard__attendance-option' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'attendance_chip_text_color',
            array(
                'label' => __('Texte des boutons', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-animateur-dashboard__attendance-option' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'attendance_chip_border_color',
            array(
                'label' => __('Bordure des boutons', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-animateur-dashboard__attendance-option' => 'border-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'attendance_chip_active_background',
            array(
                'label' => __('Fond (actif)', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-animateur-dashboard__attendance-option.is-active' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'attendance_chip_active_text_color',
            array(
                'label' => __('Texte (actif)', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-animateur-dashboard__attendance-option.is-active' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'attendance_chip_active_border_color',
            array(
                'label' => __('Bordure (actif)', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-animateur-dashboard__attendance-option.is-active' => 'border-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'attendance_info_color',
            array(
                'label' => __('Texte info', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-animateur-dashboard__attendance-info' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_payment',
            array(
                'label' => __('Paiement', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'payment_toggle_background',
            array(
                'label' => __('Fond du bouton', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-animateur-dashboard__payment-toggle' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'payment_toggle_color',
            array(
                'label' => __('Texte du bouton', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-animateur-dashboard__payment-toggle' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'payment_toggle_border_color',
            array(
                'label' => __('Bordure du bouton', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-animateur-dashboard__payment-toggle' => 'border-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'payment_toggle_hover_background',
            array(
                'label' => __('Fond (survol)', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-animateur-dashboard__payment-toggle:hover:not(:disabled)' => 'background-color: {{VALUE}};',
                    '{{WRAPPER}} .mj-animateur-dashboard__payment-toggle:focus-visible' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'payment_toggle_paid_background',
            array(
                'label' => __('Fond (payé)', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-animateur-dashboard__payment-toggle.is-paid' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'payment_toggle_paid_color',
            array(
                'label' => __('Texte (payé)', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-animateur-dashboard__payment-toggle.is-paid' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'payment_status_color',
            array(
                'label' => __('Couleur du statut', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-animateur-dashboard__payment-status' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'payment_status_paid_color',
            array(
                'label' => __('Statut payé', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-animateur-dashboard__payment-control[data-status="paid"] .mj-animateur-dashboard__payment-status' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'payment_status_unpaid_color',
            array(
                'label' => __('Statut à payer', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-animateur-dashboard__payment-control[data-status="unpaid"] .mj-animateur-dashboard__payment-status' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'payment_info_color',
            array(
                'label' => __('Informations paiement', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-animateur-dashboard__payment-info' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_buttons',
            array(
                'label' => __('Boutons & actions', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'action_button_background',
            array(
                'label' => __('Fond des boutons', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-animateur-dashboard__button' => 'background-color: {{VALUE}}; border-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'action_button_color',
            array(
                'label' => __('Texte des boutons', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-animateur-dashboard__button' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'action_button_hover_background',
            array(
                'label' => __('Fond des boutons (survol)', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-animateur-dashboard__button:hover' => 'background-color: {{VALUE}};',
                    '{{WRAPPER}} .mj-animateur-dashboard__button:focus-visible' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'action_button_hover_color',
            array(
                'label' => __('Texte des boutons (survol)', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-animateur-dashboard__button:hover' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .mj-animateur-dashboard__button:focus-visible' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            array(
                'name' => 'action_button_typography',
                'selector' => '{{WRAPPER}} .mj-animateur-dashboard__button',
            )
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $this->apply_visibility_to_wrapper($settings, 'mj-animateur-dashboard');

        if (!function_exists('mj_member_render_animateur_component')) {
            echo '<div class="mj-member-account-warning">' . esc_html__('Le module MJ Member doit être actif pour utiliser ce widget.', 'mj-member') . '</div>';
            return;
        }
        $show_event_filter = !isset($settings['show_event_filter']) || $settings['show_event_filter'] === 'yes';
        $show_occurrence_filter = !isset($settings['show_occurrence_filter']) || $settings['show_occurrence_filter'] === 'yes';
        $show_attendance_actions = !isset($settings['show_attendance_actions']) || $settings['show_attendance_actions'] === 'yes';
        $show_sms_block = !isset($settings['show_sms_block']) || $settings['show_sms_block'] === 'yes';
        $accent_color = isset($settings['accent_color']) ? $settings['accent_color'] : '';

        $view_all_enabled = !isset($settings['show_view_all_button']) || $settings['show_view_all_button'] === 'yes';
        $view_all_behavior = isset($settings['view_all_button_behavior']) ? $settings['view_all_button_behavior'] : 'toggle';
        if ($view_all_behavior !== 'link') {
            $view_all_behavior = 'toggle';
        }

        $view_all_settings = isset($settings['view_all_button_url']) && is_array($settings['view_all_button_url']) ? $settings['view_all_button_url'] : array();
        $view_all_mode = $view_all_behavior === 'link' ? 'link' : 'toggle';
        $view_all_url = '';
        $view_all_is_external = false;

        if ($view_all_mode === 'link' && !empty($view_all_settings['url'])) {
            $view_all_url = $view_all_settings['url'];
            $view_all_is_external = !empty($view_all_settings['is_external']);
        } elseif ($view_all_mode === 'link') {
            $view_all_mode = 'toggle';
        }

        $options = array(
            'title' => isset($settings['title']) ? $settings['title'] : __('Mes participants', 'mj-member'),
            'description' => isset($settings['description']) ? $settings['description'] : '',
            'wrapper_class' => 'elementor-widget-' . sanitize_html_class($this->get_id()),
            'show_event_filter' => $show_event_filter,
            'show_occurrence_filter' => $show_occurrence_filter,
            'show_attendance_actions' => $show_attendance_actions,
            'show_sms_block' => $show_sms_block,
            'accent_color' => $accent_color,
            'cover_fallback' => 'article',
            'view_all' => array(
                'enabled' => $view_all_enabled,
                'label' => !empty($settings['view_all_button_label']) ? $settings['view_all_button_label'] : __('Voir tous les événements', 'mj-member'),
                'active_label' => !empty($settings['view_all_button_active_label']) ? $settings['view_all_button_active_label'] : __('Voir mes événements', 'mj-member'),
                'mode' => $view_all_mode,
                'url' => $view_all_url,
                'is_external' => $view_all_is_external,
            ),
            'is_elementor_preview' => false,
        );

        $is_elementor_preview = false;
        if (class_exists('\\Elementor\\Plugin') && isset(\Elementor\Plugin::$instance)) {
            $elementor_instance = \Elementor\Plugin::$instance;
            if ($elementor_instance && isset($elementor_instance->editor) && method_exists($elementor_instance->editor, 'is_edit_mode')) {
                $is_elementor_preview = (bool) $elementor_instance->editor->is_edit_mode();
            }
        }

        if ($is_elementor_preview) {
            $options['is_elementor_preview'] = true;
        }

        echo mj_member_render_animateur_component($options);
    }
}
