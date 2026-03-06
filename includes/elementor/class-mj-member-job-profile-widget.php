<?php
/**
 * Widget Elementor – Profil de fonction
 *
 * Affiche le profil de fonction de l'employé connecté
 * (titre, régime de travail, financement, description).
 *
 * @package MjMember
 */

if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Controls_Manager;
use Elementor\Widget_Base;
use Mj\Member\Core\Config;

class Mj_Member_Elementor_Job_Profile_Widget extends Widget_Base
{
    use Mj_Member_Elementor_Widget_Visibility;

    public function get_name()
    {
        return 'mj-member-job-profile';
    }

    public function get_title()
    {
        return __('MJ – Profil de fonction', 'mj-member');
    }

    public function get_icon()
    {
        return 'eicon-person';
    }

    public function get_categories()
    {
        return array('mj-member');
    }

    public function get_keywords()
    {
        return array('mj', 'member', 'job', 'profil', 'fonction', 'employé', 'poste');
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
                'default'     => __('Mon profil de fonction', 'mj-member'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'empty_message',
            array(
                'label'       => __('Message si aucun profil', 'mj-member'),
                'type'        => Controls_Manager::TEXT,
                'default'     => __('Aucun profil de fonction défini.', 'mj-member'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'show_description',
            array(
                'label'        => __('Afficher la description', 'mj-member'),
                'type'         => Controls_Manager::SWITCHER,
                'label_on'     => __('Oui', 'mj-member'),
                'label_off'    => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default'      => 'yes',
            )
        );

        $this->add_control(
            'show_funding',
            array(
                'label'        => __('Afficher le financement', 'mj-member'),
                'type'         => Controls_Manager::SWITCHER,
                'label_on'     => __('Oui', 'mj-member'),
                'label_off'    => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default'      => 'yes',
            )
        );

        $this->end_controls_section();

        $this->register_visibility_controls();
    }

    protected function render()
    {
        $settings = $this->get_settings_for_display();
        $this->apply_visibility_to_wrapper($settings, 'mj-job-profile');

        $template = Config::path() . 'includes/templates/elementor/job_profile.php';
        if (is_readable($template)) {
            $title           = isset($settings['title']) ? sanitize_text_field((string) $settings['title']) : '';
            $emptyMessage    = isset($settings['empty_message']) ? sanitize_text_field((string) $settings['empty_message']) : '';
            $showDescription = !empty($settings['show_description']) && $settings['show_description'] === 'yes';
            $showFunding     = !empty($settings['show_funding']) && $settings['show_funding'] === 'yes';
            $widget          = $this;
            include $template;
        }
    }
}
