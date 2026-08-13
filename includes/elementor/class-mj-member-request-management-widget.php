<?php

if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Controls_Manager;
use Elementor\Widget_Base;
use Mj\Member\Core\Config;

class Mj_Member_Elementor_Request_Management_Widget extends Widget_Base
{
    use Mj_Member_Elementor_Widget_Visibility;

    public function get_name()
    {
        return 'mj-member-request-management';
    }

    public function get_title()
    {
        return __('MJ – Gestion des demandes', 'mj-member');
    }

    public function get_icon()
    {
        return 'eicon-calendar';
    }

    public function get_categories()
    {
        return array('mj-member');
    }

    public function get_keywords()
    {
        return array('mj', 'member', 'demande', 'demandes', 'gestion', 'agenda', 'animateur');
    }

    protected function register_controls()
    {
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
                'default' => __('Mes demandes', 'mj-member'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'intro_text',
            array(
                'label' => __('Texte introductif', 'mj-member'),
                'type' => Controls_Manager::TEXTAREA,
                'rows' => 3,
                'placeholder' => __('Suivez vos demandes, leur visibilité et leur statut.', 'mj-member'),
            )
        );

        $this->add_control(
            'show_quotas',
            array(
                'label' => __('Afficher les quotas', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
                'description' => __('Affiche les quotas et le suivi des jours utilisés.', 'mj-member'),
            )
        );

        $this->end_controls_section();

        $this->register_visibility_controls();
    }

    protected function render()
    {
        $settings = $this->get_settings_for_display();
        $this->apply_visibility_to_wrapper($settings, 'mj-request-management-widget');

        $title = isset($settings['title']) ? sanitize_text_field((string) $settings['title']) : '';
        $intro = isset($settings['intro_text']) ? wp_kses_post($settings['intro_text']) : '';
        $showQuotas = isset($settings['show_quotas']) ? $settings['show_quotas'] === 'yes' : true;

        $template = Config::path() . 'includes/templates/elementor/request_management.php';
        if (is_readable($template)) {
            $widget = $this;
            include $template;
        }
    }
}