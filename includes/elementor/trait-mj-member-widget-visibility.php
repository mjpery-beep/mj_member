<?php

if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Controls_Manager;

trait Mj_Member_Elementor_Widget_Visibility {
    protected function register_visibility_controls() {
        /*
        $this->start_controls_section(
            'section_visibility',
            array(
                'label' => __('Visibilité', 'mj-member'),
            )
        );

        $this->add_control(
            'show_on_tablet',
            array(
                'label' => __('Afficher sur tablette', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->add_control(
            'show_on_mobile',
            array(
                'label' => __('Afficher sur mobile', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->add_control(
            'visibility_notice',
            array(
                'type' => Controls_Manager::RAW_HTML,
                'raw' => '<em>' . esc_html__('Ces options permettent de masquer le widget selon les points de rupture Elementor (tablette ≤ 1024px, mobile ≤ 767px).', 'mj-member') . '</em>',
                'content_classes' => 'elementor-panel-alert',
            )
        );

        $this->end_controls_section();
        */
    }

    protected function apply_visibility_to_wrapper(array $settings, $widget_slug = '') {
        /*
        $show_on_tablet = !isset($settings['show_on_tablet']) || $settings['show_on_tablet'] === 'yes';
        $show_on_mobile = !isset($settings['show_on_mobile']) || $settings['show_on_mobile'] === 'yes';

        if (!$show_on_tablet) {
            $this->add_render_attribute('_wrapper', 'class', 'mj-member-widget--hide-tablet');
            if ($widget_slug !== '') {
                $this->add_render_attribute('_wrapper', 'class', $widget_slug . '--hide-tablet');
            }
        }

        if (!$show_on_mobile) {
            $this->add_render_attribute('_wrapper', 'class', 'mj-member-widget--hide-mobile');
            if ($widget_slug !== '') {
                $this->add_render_attribute('_wrapper', 'class', $widget_slug . '--hide-mobile');
            }
        }
        */
    }
}
