<?php

use Elementor\Controls_Manager;
use Elementor\Widget_Base;
use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

class Mj_Member_Elementor_Todo_Widget extends Widget_Base
{
    use Mj_Member_Elementor_Widget_Visibility;

    public function get_name()
    {
        return 'mj-member-todo';
    }

    public function get_title()
    {
        return __('MJ – Todos', 'mj-member');
    }

    public function get_icon()
    {
        return 'eicon-check-circle-o';
    }

    public function get_categories()
    {
        return array('mj-member');
    }

    public function get_keywords()
    {
        return array('mj', 'member', 'todo', 'tâche', 'liste', 'checklist');
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
                'default' => __('Mes tâches', 'mj-member'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'intro_text',
            array(
                'label' => __('Texte introductif', 'mj-member'),
                'type' => Controls_Manager::TEXTAREA,
                'rows' => 3,
                'placeholder' => __('Présentez le gestionnaire de tâches.', 'mj-member'),
            )
        );

        $this->add_control(
            'show_completed',
            array(
                'label' => __('Afficher les tâches terminées par défaut', 'mj-member'),
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
        $this->apply_visibility_to_wrapper($settings, 'mj-todo-widget');

        $title = isset($settings['title']) ? sanitize_text_field((string) $settings['title']) : '';
        $intro = isset($settings['intro_text']) ? wp_kses_post($settings['intro_text']) : '';
        $showCompleted = isset($settings['show_completed']) ? $settings['show_completed'] === 'yes' : true;

        $template = Config::path() . 'includes/templates/elementor/todo.php';
        if (is_readable($template)) {
            $widget = $this;
            include $template;
        }
    }
}
