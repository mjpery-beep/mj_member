<?php

if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Controls_Manager;
use Elementor\Widget_Base;
use Mj\Member\Core\Config;

class Mj_Member_Elementor_Hour_Encode_Widget extends Widget_Base {
    use Mj_Member_Elementor_Widget_Visibility;

    public function get_name() {
        return 'mj-member-hour-encode';
    }

    public function get_title() {
        return __('Encodage des heures MJ', 'mj-member');
    }

    public function get_icon() {
        return 'eicon-clock';
    }

    public function get_categories() {
        return array('mj-member');
    }

    public function get_keywords() {
        return array('mj', 'member', 'heures', 'planning', 'encode');
    }

    protected function register_controls() {
        $this->start_controls_section(
            'section_content',
            array(
                'label' => __('Contenu', 'mj-member'),
            )
        );

        $this->add_control(
            'intro_text',
            array(
                'label' => __('Texte introductif', 'mj-member'),
                'type' => Controls_Manager::TEXTAREA,
                'rows' => 3,
                'placeholder' => __('Présentez l\'outil d\'encodage des heures.', 'mj-member'),
            )
        );

        $this->end_controls_section();

        $this->register_visibility_controls();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $this->apply_visibility_to_wrapper($settings, 'mj-hour-encode');

        $intro_text = '';
        if (!empty($settings['intro_text'])) {
            $intro_text = wp_kses_post($settings['intro_text']);
        }

        $template_path = Config::path() . 'includes/templates/elementor/hour_encode.php';
        if (file_exists($template_path)) {
            $widget = $this;
            include $template_path;
        }
    }
}
