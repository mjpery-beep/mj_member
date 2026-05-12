<?php

if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Controls_Manager;
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
                    'left' => 'margin-left: 0; margin-right: auto;',
                    'center' => 'margin-left: auto; margin-right: auto;',
                    'right' => 'margin-left: auto; margin-right: 0;',
                    'stretch' => 'margin-left: 0; margin-right: 0;',
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-attkiosk' => '{{VALUE}}',
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
