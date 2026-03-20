<?php

if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Controls_Manager;
use Elementor\Widget_Base;
use Mj\Member\Core\Config;

class Mj_Member_Elementor_Hours_Dashboard_Widget extends Widget_Base {
    use Mj_Member_Elementor_Widget_Visibility;

    public function get_name() {
        return 'mj-member-hours-dashboard';
    }

    public function get_title() {
        return __('Tableau de bord des heures MJ', 'mj-member');
    }

    public function get_icon() {
        return 'eicon-bar-chart';
    }

    public function get_categories() {
        return array('mj-member');
    }

    public function get_keywords() {
        return array('mj', 'member', 'heures', 'hours', 'dashboard', 'statistiques', 'stats', 'coordinateur');
    }

    protected function register_controls() {
        $this->start_controls_section(
            'section_content',
            array(
                'label' => __('Contenu', 'mj-member'),
            )
        );

        $this->add_control(
            'show_edit_tab',
            array(
                'label' => __('Afficher l\'onglet "Éditer les heures"', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->add_control(
            'only_current_member',
            array(
                'label' => __('N\'afficher que les données du membre connecté', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => '',
            )
        );

        $this->end_controls_section();

        $this->register_visibility_controls();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $this->apply_visibility_to_wrapper($settings, 'mj-hours-dashboard-widget');

        $template_path = Config::path() . 'includes/templates/elementor/hours_dashboard.php';
        if (file_exists($template_path)) {
            $widget = $this;
            include $template_path;
        }
    }
}
