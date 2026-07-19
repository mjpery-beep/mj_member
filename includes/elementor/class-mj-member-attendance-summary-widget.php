<?php
/**
 * Widget Elementor - Recapitulatif des presences
 *
 * @package MjMember
 */

if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Controls_Manager;
use Elementor\Widget_Base;
use Mj\Member\Core\Config;

class Mj_Member_Elementor_Attendance_Summary_Widget extends Widget_Base
{
    use Mj_Member_Elementor_Widget_Visibility;

    public function get_name()
    {
        return 'mj-member-attendance-summary';
    }

    public function get_title()
    {
        return __('MJ - Recap presences', 'mj-member');
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
        return array('mj', 'presence', 'attendance', 'recap', 'evenements', 'participants');
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
                'default' => __('Recapitulatif des presences', 'mj-member'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'max_events',
            array(
                'label' => __('Nombre maximum d\'evenements', 'mj-member'),
                'type' => Controls_Manager::NUMBER,
                'min' => 1,
                'max' => 50,
                'default' => 10,
            )
        );

        $this->add_control(
            'include_draft',
            array(
                'label' => __('Inclure les brouillons', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'no',
            )
        );

        $this->add_control(
            'show_completion',
            array(
                'label' => __('Afficher le taux de pointage', 'mj-member'),
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
        $this->apply_visibility_to_wrapper($settings, 'mj-attendance-summary-widget');

        $template_path = Config::path() . 'includes/templates/elementor/attendance_summary.php';
        if (!file_exists($template_path)) {
            return;
        }

        $title = isset($settings['title']) ? sanitize_text_field((string) $settings['title']) : '';
        $max_events = isset($settings['max_events']) ? max(1, (int) $settings['max_events']) : 10;
        $include_draft = !empty($settings['include_draft']) && $settings['include_draft'] === 'yes';
        $show_completion = !empty($settings['show_completion']) && $settings['show_completion'] === 'yes';

        include $template_path;
    }
}
