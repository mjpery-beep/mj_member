<?php

use Elementor\Controls_Manager;
use Elementor\Widget_Base;
use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

class Mj_Member_Elementor_Employee_Documents_Widget extends Widget_Base
{
    use Mj_Member_Elementor_Widget_Visibility;

    public function get_name()
    {
        return 'mj-member-employee-documents';
    }

    public function get_title()
    {
        return __('MJ – Documents Employé', 'mj-member');
    }

    public function get_icon()
    {
        return 'eicon-folder-o';
    }

    public function get_categories()
    {
        return array('mj-member');
    }

    public function get_keywords()
    {
        return array('mj', 'member', 'employee', 'documents', 'fiche de paie', 'payslip', 'contrat', 'employé');
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
                'default' => __('Mes documents', 'mj-member'),
                'label_block' => true,
            )
        );

        $this->end_controls_section();

        $this->register_visibility_controls();
    }

    protected function render()
    {
        $settings = $this->get_settings_for_display();
        $this->apply_visibility_to_wrapper($settings, 'mj-employee-documents-widget');

        $title = isset($settings['title']) ? sanitize_text_field((string) $settings['title']) : '';

        $template = Config::path() . 'includes/templates/elementor/employee_documents.php';
        if (is_readable($template)) {
            $widget = $this;
            include $template;
        }
    }
}
