<?php
/**
 * Widget Elementor – Horaire de travail
 *
 * Affiche l'horaire de travail hebdomadaire des employés (animateurs & coordinateurs).
 *
 * @package MjMember
 */

if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Controls_Manager;
use Elementor\Widget_Base;
use Mj\Member\Core\Config;

class Mj_Member_Elementor_Work_Schedule_Widget extends Widget_Base
{
    use Mj_Member_Elementor_Widget_Visibility;

    public function get_name()
    {
        return 'mj-member-work-schedule';
    }

    public function get_title()
    {
        return __('MJ – Horaire de travail', 'mj-member');
    }

    public function get_icon()
    {
        return 'eicon-table';
    }

    public function get_categories()
    {
        return array('mj-member');
    }

    public function get_keywords()
    {
        return array('mj', 'horaire', 'travail', 'schedule', 'planning', 'employé', 'animateur');
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
                'label'       => __('Titre', 'mj-member'),
                'type'        => Controls_Manager::TEXT,
                'default'     => __('Horaire de travail', 'mj-member'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'intro_text',
            array(
                'label'       => __('Texte introductif', 'mj-member'),
                'type'        => Controls_Manager::TEXTAREA,
                'rows'        => 3,
                'placeholder' => __('Consultez les horaires de travail de l\'équipe.', 'mj-member'),
            )
        );

        $this->add_control(
            'show_breaks',
            array(
                'label'        => __('Afficher les pauses', 'mj-member'),
                'type'         => Controls_Manager::SWITCHER,
                'label_on'     => __('Oui', 'mj-member'),
                'label_off'    => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default'      => 'yes',
                'description'  => __('Affiche la durée des pauses dans l\'horaire.', 'mj-member'),
            )
        );

        $this->add_control(
            'show_totals',
            array(
                'label'        => __('Afficher les totaux', 'mj-member'),
                'type'         => Controls_Manager::SWITCHER,
                'label_on'     => __('Oui', 'mj-member'),
                'label_off'    => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default'      => 'yes',
                'description'  => __('Affiche le total d\'heures hebdomadaires par employé.', 'mj-member'),
            )
        );

        $this->end_controls_section();

        $this->register_visibility_controls();
    }

    protected function render()
    {
        $settings = $this->get_settings_for_display();
        $this->apply_visibility_to_wrapper($settings, 'mj-work-schedule');

        $template_path = Config::path() . 'includes/templates/elementor/work_schedule.php';
        if (file_exists($template_path)) {
            $title      = isset($settings['title']) ? sanitize_text_field($settings['title']) : '';
            $intro_text = isset($settings['intro_text']) ? wp_kses_post($settings['intro_text']) : '';
            $showBreaks = !empty($settings['show_breaks']) && $settings['show_breaks'] === 'yes';
            $showTotals = !empty($settings['show_totals']) && $settings['show_totals'] === 'yes';
            $widget     = $this;
            include $template_path;
        }
    }
}
