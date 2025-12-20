<?php

namespace Mj\Member\Elementor;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

if (!defined('ABSPATH')) {
    exit;
}

class EventsManager extends Widget_Base
{
    public function get_name(): string
    {
        return 'mj-events-manager';
    }

    public function get_title(): string
    {
        return __('MJ Gestion Événements', 'mj-member');
    }

    public function get_icon(): string
    {
        return 'eicon-calendar';
    }

    public function get_categories(): array
    {
        return ['mj-member'];
    }

    protected function register_controls(): void
    {
        $this->start_controls_section(
            'section_content',
            [
                'label' => __('Contenu', 'mj-member'),
            ]
        );

        $this->add_control(
            'title',
            [
                'label' => __('Titre', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Gestion des événements', 'mj-member'),
            ]
        );

        $this->add_control(
            'show_past_events',
            [
                'label' => __('Afficher événements passés', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'no',
            ]
        );

        $this->add_control(
            'events_per_page',
            [
                'label' => __('Événements par page', 'mj-member'),
                'type' => Controls_Manager::NUMBER,
                'default' => 20,
                'min' => 5,
                'max' => 100,
            ]
        );

        $this->end_controls_section();
    }

    protected function render(): void
    {
        if (!\Elementor\Plugin::$instance->editor->is_edit_mode()) {
            if (!class_exists('Mj\\Member\\Core\\Config')) {
                echo '<p>' . esc_html__('Module MJ Member non disponible.', 'mj-member') . '</p>';
                return;
            }

            $capability = \Mj\Member\Core\Config::capability();
            if (!current_user_can($capability)) {
                echo '<p>' . esc_html__('Accès réservé aux animateurs et coordinateurs.', 'mj-member') . '</p>';
                return;
            }
        }

        $settings = $this->get_settings_for_display();
        $template_path = \Mj\Member\Core\Config::path() . 'includes/templates/elementor/events_manager.php';

        if (!file_exists($template_path)) {
            echo '<p>' . esc_html__('Template introuvable.', 'mj-member') . '</p>';
            return;
        }

        include $template_path;
    }
}

// Enregistrement du widget dans Elementor
if (!function_exists('mj_member_register_events_manager_widget')) {
    function mj_member_register_events_manager_widget($widgets_manager) {
        if (class_exists('Mj\Member\Elementor\EventsManager')) {
            $widgets_manager->register(new \Mj\Member\Elementor\EventsManager());
        }
    }
}

if (!function_exists('mj_member_bootstrap_events_manager_widget')) {
    function mj_member_bootstrap_events_manager_widget() {
        if (!did_action('elementor/loaded')) {
            return;
        }

        add_action('elementor/widgets/register', 'mj_member_register_events_manager_widget');
    }
    add_action('plugins_loaded', 'mj_member_bootstrap_events_manager_widget');
}
