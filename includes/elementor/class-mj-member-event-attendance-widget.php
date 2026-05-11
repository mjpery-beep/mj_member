<?php

if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Controls_Manager;

class Mj_Member_Elementor_Event_Attendance_Widget extends Mj_Member_Elementor_Registration_Manager_Widget {
    public function get_name() {
        return 'mj-member-event-attendance';
    }

    public function get_title() {
        return __('Feuille de présence événement', 'mj-member');
    }

    public function get_icon() {
        return 'eicon-check-circle';
    }

    public function get_keywords() {
        return array('mj', 'member', 'presence', 'attendance', 'evenement', 'gestionnaire');
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
                    . esc_html__('Ce widget réutilise exactement l’interface du widget Gestionnaire, fiche événement, onglet Présence.', 'mj-member')
                    . '<br>'
                    . esc_html__('L’événement configuré ici est ouvert directement sur l’onglet Présence.', 'mj-member')
                    . '</div>',
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
                    <span class="dashicons dashicons-yes-alt"></span>
                </div>
                <h3>{{{ settings.title || 'Feuille de présence' }}}</h3>
                <p><?php esc_html_e('Aperçu de la feuille de présence du widget gestionnaire.', 'mj-member'); ?></p>
            </div>
        </div>
        <?php
    }
}
