<?php

use Elementor\Controls_Manager;
use Elementor\Widget_Base;
use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

class Mj_Member_Elementor_Idea_Box_Widget extends Widget_Base
{
    use Mj_Member_Elementor_Widget_Visibility;

    public function get_name()
    {
        return 'mj-member-idea-box';
    }

    public function get_title()
    {
        return __('MJ – Boîte à idées', 'mj-member');
    }

    public function get_icon()
    {
        return 'eicon-lightbulb';
    }

    public function get_categories()
    {
        return array('general');
    }

    public function get_keywords()
    {
        return array('mj', 'member', 'idea', 'idée', 'suggestion', 'boite', 'plus');
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
                'default' => __('Boîte à idées', 'mj-member'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'intro_text',
            array(
                'label' => __('Texte introductif', 'mj-member'),
                'type' => Controls_Manager::TEXTAREA,
                'rows' => 3,
                'placeholder' => __('Expliquez le fonctionnement de la boîte à idées.', 'mj-member'),
            )
        );

        $this->add_control(
            'allow_submission',
            array(
                'label' => __('Autoriser la soumission d’idées', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->end_controls_section();

        $this->register_visibility_controls();
    }

    protected function render()
    {
        $settings = $this->get_settings_for_display();
        $this->apply_visibility_to_wrapper($settings, 'mj-idea-box');

        $title = isset($settings['title']) ? sanitize_text_field((string) $settings['title']) : '';
        $intro = isset($settings['intro_text']) ? wp_kses_post($settings['intro_text']) : '';
        $allowSubmission = !isset($settings['allow_submission']) || $settings['allow_submission'] === 'yes';

        $template = Config::path() . 'includes/templates/elementor/idea_box.php';
        if (is_readable($template)) {
            $widget = $this;
            include $template;
        }
    }
}
