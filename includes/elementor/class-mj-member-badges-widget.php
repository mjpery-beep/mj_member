<?php

use Elementor\Controls_Manager;
use Elementor\Widget_Base;
use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

class Mj_Member_Elementor_Badges_Widget extends Widget_Base
{
    use Mj_Member_Elementor_Widget_Visibility;

    public function get_name()
    {
        return 'mj-member-badges-overview';
    }

    public function get_title()
    {
        return __('MJ – Badges', 'mj-member');
    }

    public function get_icon()
    {
        return 'eicon-star-circle';
    }

    public function get_categories()
    {
        return array('mj-member');
    }

    public function get_keywords()
    {
        return array('mj', 'member', 'badge', 'badges', 'recompenses', 'progression');
    }

    protected function register_controls()
    {
        $this->start_controls_section(
            'content_section',
            array(
                'label' => __('Contenu', 'mj-member'),
            )
        );

        $this->add_control(
            'title',
            array(
                'label' => __('Titre', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Mes badges', 'mj-member'),
                'placeholder' => __('Titre principal du widget', 'mj-member'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'subtitle',
            array(
                'label' => __('Texte d\'introduction', 'mj-member'),
                'type' => Controls_Manager::TEXTAREA,
                'rows' => 3,
                'placeholder' => __('Présentez la manière d\'obtenir les badges.', 'mj-member'),
            )
        );

        $this->add_control(
            'show_summary_cards',
            array(
                'label' => __('Afficher le résumé', 'mj-member'),
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
        $this->apply_visibility_to_wrapper($settings, 'mj-badges-overview');

        $template = Config::path() . 'includes/templates/elementor/badges_overview.php';
        if (!is_readable($template)) {
            echo '<div class="mj-member-account-warning">' . esc_html__('Template badges indisponible.', 'mj-member') . '</div>';
            return;
        }

        $title = isset($settings['title']) ? (string) $settings['title'] : '';
        $subtitle = isset($settings['subtitle']) ? $settings['subtitle'] : '';
        $showSummary = isset($settings['show_summary_cards']) ? $settings['show_summary_cards'] === 'yes' : true;

        $widget = $this;
        include $template;
    }
}
