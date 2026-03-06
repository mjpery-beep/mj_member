<?php

use Elementor\Controls_Manager;
use Elementor\Widget_Base;
use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

class Mj_Member_Elementor_Payslip_Upload_Widget extends Widget_Base
{
    use Mj_Member_Elementor_Widget_Visibility;

    public function get_name()
    {
        return 'mj-member-payslip-upload';
    }

    public function get_title()
    {
        return __('MJ – Upload Fiches de paie', 'mj-member');
    }

    public function get_icon()
    {
        return 'eicon-upload';
    }

    public function get_categories()
    {
        return array('mj-member');
    }

    public function get_keywords()
    {
        return array('mj', 'member', 'payslip', 'upload', 'fiche de paie', 'coordinateur', 'bulk');
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
                'default' => __('Ajouter des fiches de paie', 'mj-member'),
                'label_block' => true,
            )
        );

        $this->end_controls_section();

        $this->register_visibility_controls();
    }

    protected function render()
    {
        $settings = $this->get_settings_for_display();
        $this->apply_visibility_to_wrapper($settings, 'mj-payslip-upload-widget');

        $title = isset($settings['title']) ? sanitize_text_field((string) $settings['title']) : '';

        $template = Config::path() . 'includes/templates/elementor/payslip_upload.php';
        if (is_readable($template)) {
            $widget = $this;
            include $template;
        }
    }
}
