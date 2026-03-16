<?php

use Elementor\Controls_Manager;
use Elementor\Widget_Base;
use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

class Mj_Member_Elementor_Mileage_Widget extends Widget_Base
{
    use Mj_Member_Elementor_Widget_Visibility;

    public function get_name()
    {
        return 'mj-member-mileage';
    }

    public function get_title()
    {
        return __('MJ – Frais Kilométriques', 'mj-member');
    }

    public function get_icon()
    {
        return 'eicon-google-maps';
    }

    public function get_categories()
    {
        return array('mj-member');
    }

    public function get_keywords()
    {
        return array('mj', 'member', 'mileage', 'frais', 'kilométrique', 'trajet', 'remboursement');
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
                'default' => __('Frais Kilométriques', 'mj-member'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'intro_text',
            array(
                'label' => __('Texte introductif', 'mj-member'),
                'type' => Controls_Manager::TEXTAREA,
                'rows' => 3,
                'placeholder' => __('Gérez vos demandes de remboursement kilométrique.', 'mj-member'),
            )
        );

        $this->end_controls_section();

        $this->register_visibility_controls();
    }

    protected function render()
    {
        $settings = $this->get_settings_for_display();
        $this->apply_visibility_to_wrapper($settings, 'mj-mileage-widget');

        $title = isset($settings['title']) ? sanitize_text_field((string) $settings['title']) : '';
        $intro = isset($settings['intro_text']) ? wp_kses_post($settings['intro_text']) : '';

        $template = Config::path() . 'includes/templates/elementor/mileage.php';
        if (is_readable($template)) {
            $widget = $this;
            include $template;
        }
    }
}
