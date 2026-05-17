<?php

if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;

class Mj_Member_Elementor_Event_Attendance_Widget extends Widget_Base {
    use Mj_Member_Elementor_Widget_Visibility;

    public function get_name() {
        return 'mj-member-event-attendance';
    }

    public function get_title() {
        return __('Feuille de présence kiosque', 'mj-member');
    }

    public function get_icon() {
        return 'eicon-check-circle';
    }

    public function get_categories() {
        return array('mj-member');
    }

    public function get_keywords() {
        return array('mj', 'member', 'presence', 'attendance', 'kiosk', 'evenement');
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
                'default' => __('Feuille de présence', 'mj-member'),
                'label_block' => true,
            )
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_presence_defaults',
            array(
                'label' => __('Présence - Paramètres', 'mj-member'),
            )
        );

        $this->add_control(
            'default_event_id',
            array(
                'label' => __('ID événement par défaut', 'mj-member'),
                'type' => Controls_Manager::NUMBER,
                'min' => 0,
                'step' => 1,
                'default' => 0,
                'description' => __('0 = aucun. Saisissez l\'ID d\'un événement pour le présélectionner au chargement.', 'mj-member'),
            )
        );

        $this->add_control(
            'attendance_widget_hint',
            array(
                'type' => Controls_Manager::RAW_HTML,
                'raw' => '<div style="line-height:1.5;">'
                    . esc_html__('Widget kiosque dédié à la prise de présence tactile.', 'mj-member')
                    . '<br>'
                    . esc_html__('Interface simplifiée: recherche, sélecteur de séance, cartes membres et actions Présent/Absent.', 'mj-member')
                    . '</div>',
            )
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_layout',
            array(
                'label' => __('Mise en page', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_responsive_control(
            'kiosk_margin',
            array(
                'label' => __('Marge externe', 'mj-member'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => array('px', 'em', '%'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-attkiosk' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'kiosk_padding',
            array(
                'label' => __('Marge interne', 'mj-member'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => array('px', 'em', '%'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-attkiosk__shell' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'kiosk_width',
            array(
                'label' => __('Largeur du widget', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px', '%', 'vw'),
                'range' => array(
                    'px' => array(
                        'min' => 280,
                        'max' => 1920,
                    ),
                    '%' => array(
                        'min' => 20,
                        'max' => 100,
                    ),
                    'vw' => array(
                        'min' => 20,
                        'max' => 100,
                    ),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-attkiosk' => 'width: {{SIZE}}{{UNIT}} !important;',
                ),
            )
        );

        $this->add_responsive_control(
            'kiosk_max_width',
            array(
                'label' => __('Largeur max', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px', '%', 'vw'),
                'range' => array(
                    'px' => array(
                        'min' => 280,
                        'max' => 1920,
                    ),
                    '%' => array(
                        'min' => 20,
                        'max' => 100,
                    ),
                    'vw' => array(
                        'min' => 20,
                        'max' => 100,
                    ),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-attkiosk' => 'max-width: {{SIZE}}{{UNIT}} !important;',
                ),
            )
        );

        $this->add_responsive_control(
            'kiosk_align',
            array(
                'label' => __('Alignement horizontal', 'mj-member'),
                'type' => Controls_Manager::CHOOSE,
                'default' => 'stretch',
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
                        'title' => __('Étendu', 'mj-member'),
                        'icon' => 'eicon-h-align-stretch',
                    ),
                ),
                'selectors_dictionary' => array(
                    'left' => 'display: flex; justify-content: flex-start;',
                    'center' => 'display: flex; justify-content: center;',
                    'right' => 'display: flex; justify-content: flex-end;',
                    'stretch' => 'display: block;',
                ),
                'selectors' => array(
                    '{{WRAPPER}} .elementor-widget-container' => '{{VALUE}}',
                ),
            )
        );

        $this->add_responsive_control(
            'kiosk_min_height',
            array(
                'label' => __('Hauteur minimum', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px', 'vh'),
                'range' => array(
                    'px' => array(
                        'min' => 280,
                        'max' => 1400,
                    ),
                    'vh' => array(
                        'min' => 20,
                        'max' => 100,
                    ),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-attkiosk' => 'min-height: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'kiosk_internal_gap',
            array(
                'label' => __('Espacement interne', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px', 'em', 'rem'),
                'range' => array(
                    'px' => array(
                        'min' => 0,
                        'max' => 80,
                    ),
                    'em' => array(
                        'min' => 0,
                        'max' => 6,
                    ),
                    'rem' => array(
                        'min' => 0,
                        'max' => 6,
                    ),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-attkiosk__shell' => 'gap: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_shell',
            array(
                'label' => __('Conteneur', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'shell_background_color',
            array(
                'label' => __('Fond', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-attkiosk__shell' => 'background: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'shell_text_color',
            array(
                'label' => __('Couleur du texte', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-attkiosk' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            array(
                'name' => 'shell_border',
                'selector' => '{{WRAPPER}} .mj-attkiosk__shell',
            )
        );

        $this->add_responsive_control(
            'shell_border_radius',
            array(
                'label' => __('Rayon de bordure', 'mj-member'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => array('px', '%'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-attkiosk__shell' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            array(
                'name' => 'shell_shadow',
                'selector' => '{{WRAPPER}} .mj-attkiosk__shell',
            )
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_header',
            array(
                'label' => __('En-tête et titre', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'header_background_color',
            array(
                'label' => __('Fond en-tête', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-attkiosk__header' => 'background: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            array(
                'name' => 'header_border',
                'selector' => '{{WRAPPER}} .mj-attkiosk__header',
            )
        );

        $this->add_responsive_control(
            'header_padding',
            array(
                'label' => __('Padding en-tête', 'mj-member'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => array('px', 'em', '%'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-attkiosk__header' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'header_border_radius',
            array(
                'label' => __('Rayon en-tête', 'mj-member'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => array('px', '%'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-attkiosk__header' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'title_color',
            array(
                'label' => __('Couleur du titre', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-attkiosk__title' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            array(
                'name' => 'title_typography',
                'selector' => '{{WRAPPER}} .mj-attkiosk__title',
            )
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_search',
            array(
                'label' => __('Recherche', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'search_background_color',
            array(
                'label' => __('Fond champ', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-attkiosk__search' => 'background: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'search_text_color',
            array(
                'label' => __('Couleur texte champ', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-attkiosk__search' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'search_placeholder_color',
            array(
                'label' => __('Couleur placeholder', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-attkiosk__search::placeholder' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            array(
                'name' => 'search_typography',
                'selector' => '{{WRAPPER}} .mj-attkiosk__search',
            )
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            array(
                'name' => 'search_border',
                'selector' => '{{WRAPPER}} .mj-attkiosk__search',
            )
        );

        $this->add_responsive_control(
            'search_border_radius',
            array(
                'label' => __('Rayon champ', 'mj-member'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => array('px', '%'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-attkiosk__search' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'search_padding',
            array(
                'label' => __('Padding champ', 'mj-member'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => array('px', 'em', '%'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-attkiosk__search' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_days',
            array(
                'label' => __('Sélecteur de séance', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            array(
                'name' => 'day_button_typography',
                'selector' => '{{WRAPPER}} .mj-attkiosk__day-btn',
            )
        );

        $this->add_responsive_control(
            'day_button_padding',
            array(
                'label' => __('Padding bouton séance', 'mj-member'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => array('px', 'em', '%'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-attkiosk__day-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'day_button_radius',
            array(
                'label' => __('Rayon bouton séance', 'mj-member'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => array('px', '%'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-attkiosk__day-btn' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->start_controls_tabs('day_button_tabs');

        $this->start_controls_tab(
            'day_button_tab_normal',
            array(
                'label' => __('Normal', 'mj-member'),
            )
        );

        $this->add_control(
            'day_button_bg_normal',
            array(
                'label' => __('Fond', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-attkiosk__day-btn' => 'background: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'day_button_text_normal',
            array(
                'label' => __('Texte', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-attkiosk__day-btn' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'day_button_border_normal',
            array(
                'label' => __('Bordure', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-attkiosk__day-btn' => 'border-color: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_tab();

        $this->start_controls_tab(
            'day_button_tab_active',
            array(
                'label' => __('Actif', 'mj-member'),
            )
        );

        $this->add_control(
            'day_button_bg_active',
            array(
                'label' => __('Fond actif', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-attkiosk__day-btn.is-active' => 'background: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'day_button_text_active',
            array(
                'label' => __('Texte actif', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-attkiosk__day-btn.is-active' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'day_button_border_active',
            array(
                'label' => __('Bordure active', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-attkiosk__day-btn.is-active' => 'border-color: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_cards',
            array(
                'label' => __('Cartes membres', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'card_background_color',
            array(
                'label' => __('Fond carte', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-attkiosk__card' => 'background: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            array(
                'name' => 'card_border',
                'selector' => '{{WRAPPER}} .mj-attkiosk__card',
            )
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            array(
                'name' => 'card_shadow',
                'selector' => '{{WRAPPER}} .mj-attkiosk__card',
            )
        );

        $this->add_responsive_control(
            'card_border_radius',
            array(
                'label' => __('Rayon carte', 'mj-member'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => array('px', '%'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-attkiosk__card' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'card_padding',
            array(
                'label' => __('Padding carte', 'mj-member'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => array('px', 'em', '%'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-attkiosk__card' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            array(
                'name' => 'member_name_typography',
                'selector' => '{{WRAPPER}} .mj-attkiosk__name',
            )
        );

        $this->add_control(
            'member_name_color',
            array(
                'label' => __('Couleur nom membre', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-attkiosk__name' => 'color: {{VALUE}} !important;',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            array(
                'name' => 'member_meta_label_typography',
                'selector' => '{{WRAPPER}} .mj-attkiosk__meta-grid span',
            )
        );

        $this->add_control(
            'member_meta_label_color',
            array(
                'label' => __('Couleur labels méta', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-attkiosk__meta-grid span' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            array(
                'name' => 'member_meta_value_typography',
                'selector' => '{{WRAPPER}} .mj-attkiosk__meta-grid strong',
            )
        );

        $this->add_control(
            'member_meta_value_color',
            array(
                'label' => __('Couleur valeurs méta', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-attkiosk__meta-grid strong' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_actions',
            array(
                'label' => __('Bouton statut', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            array(
                'name' => 'status_button_typography',
                'selector' => '{{WRAPPER}} .mj-attkiosk__status-btn',
            )
        );

        $this->add_responsive_control(
            'status_button_radius',
            array(
                'label' => __('Rayon bouton statut', 'mj-member'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => array('px', '%'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-attkiosk__status-btn' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'status_button_padding',
            array(
                'label' => __('Padding bouton statut', 'mj-member'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => array('px', 'em', '%'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-attkiosk__status-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'status_present_bg',
            array(
                'label' => __('Fond - Présent', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-attkiosk__status-btn--present.is-present' => 'background: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'status_present_text',
            array(
                'label' => __('Texte - Présent', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-attkiosk__status-btn--present.is-present' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'status_present_border',
            array(
                'label' => __('Bordure - Présent', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-attkiosk__status-btn--present.is-present' => 'border-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'status_absent_bg',
            array(
                'label' => __('Fond - Absent', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-attkiosk__status-btn--present.is-absent' => 'background: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'status_absent_text',
            array(
                'label' => __('Texte - Absent', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-attkiosk__status-btn--present.is-absent' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'status_absent_border',
            array(
                'label' => __('Bordure - Absent', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-attkiosk__status-btn--present.is-absent' => 'border-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'status_undefined_bg',
            array(
                'label' => __('Fond - Non défini', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-attkiosk__status-btn--present.is-undefined' => 'background: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'status_undefined_text',
            array(
                'label' => __('Texte - Non défini', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-attkiosk__status-btn--present.is-undefined' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'status_undefined_border',
            array(
                'label' => __('Bordure - Non défini', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-attkiosk__status-btn--present.is-undefined' => 'border-color: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_section();

        $this->register_visibility_controls();
    }

    protected function render() {
        include \Mj\Member\Core\Config::path() . 'includes/templates/elementor/event_attendance_kiosk.php';
    }

    protected function content_template() {
        ?>
        <div class="mj-attkiosk mj-attkiosk--preview">
            <div class="mj-attkiosk__preview-box">
                <div class="mj-attkiosk__preview-icon">
                    <span class="dashicons dashicons-yes-alt"></span>
                </div>
                <h3>{{{ settings.title || 'Feuille de présence' }}}</h3>
                <p><?php esc_html_e('Aperçu du kiosque tactile de présence.', 'mj-member'); ?></p>
            </div>
        </div>
        <?php
    }
}
