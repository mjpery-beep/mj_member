<?php

if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;

class Mj_Member_Elementor_Registrations_Widget extends Widget_Base {
    public function get_name() {
        return 'mj-member-registrations';
    }

    public function get_title() {
        return __('Mes inscriptions MJ', 'mj-member');
    }

    public function get_icon() {
        return 'eicon-calendar';
    }

    public function get_categories() {
        return array('general');
    }

    public function get_keywords() {
        return array('mj', 'inscription', 'events', 'stages');
    }

    protected function register_controls() {
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
                'default' => __('Mes inscriptions', 'mj-member'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'limit',
            array(
                'label' => __("Nombre maximum d'inscriptions", 'mj-member'),
                'type' => Controls_Manager::NUMBER,
                'min' => 1,
                'max' => 50,
                'default' => 10,
            )
        );

        $this->add_control(
            'upcoming_only',
            array(
                'label' => __('Afficher uniquement les événements à venir', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'no',
            )
        );

        $this->add_control(
            'show_status',
            array(
                'label' => __('Afficher le statut', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->add_control(
            'show_dates',
            array(
                'label' => __('Afficher les dates', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->add_control(
            'show_type',
            array(
                'label' => __('Afficher le type', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'no',
            )
        );

        $this->add_control(
            'empty_message',
            array(
                'label' => __("Message lorsqu'il n'y a aucune inscription", 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => __("Vous n'avez pas encore d'inscription active.", 'mj-member'),
                'label_block' => true,
            )
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_card',
            array(
                'label' => __('Carte', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'card_background',
            array(
                'label' => __('Couleur de fond', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-registrations__item' => 'background-color: {{VALUE}};',
                ),
            )
        );

        if (class_exists('Elementor\\Group_Control_Border')) {
            $this->add_group_control(
                Group_Control_Border::get_type(),
                array(
                    'name' => 'card_border',
                    'selector' => '{{WRAPPER}} .mj-member-registrations__item',
                )
            );
        }

        if (class_exists('Elementor\\Group_Control_Box_Shadow')) {
            $this->add_group_control(
                Group_Control_Box_Shadow::get_type(),
                array(
                    'name' => 'card_shadow',
                    'selector' => '{{WRAPPER}} .mj-member-registrations__item',
                )
            );
        }

        $this->add_responsive_control(
            'card_padding',
            array(
                'label' => __('Marge interne', 'mj-member'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => array('px', 'em', '%'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-registrations__item' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_badge',
            array(
                'label' => __('Badge', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'badge_text_color',
            array(
                'label' => __('Couleur du texte', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-registrations__badge' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'badge_background',
            array(
                'label' => __('Couleur de fond', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-registrations__badge' => 'background-color: {{VALUE}};',
                ),
                'condition' => array('show_status' => 'yes'),
            )
        );

        $this->end_controls_section();
    }

    protected function render() {
        if (!function_exists('mj_member_get_member_registrations')) {
            echo '<div class="mj-member-account-warning">' . esc_html__('Le module MJ Member doit être actif pour utiliser ce widget.', 'mj-member') . '</div>';
            return;
        }

        if (!is_user_logged_in()) {
            $login_url = wp_login_url(mj_member_get_current_url());
            echo '<div class="mj-member-account-warning">' . esc_html__('Connectez-vous pour consulter vos inscriptions.', 'mj-member') . ' <a href="' . esc_url($login_url) . '">' . esc_html__('Se connecter', 'mj-member') . '</a></div>';
            return;
        }

        $member = mj_member_get_current_member();
        if (!$member) {
            echo '<div class="mj-member-account-warning">' . esc_html__('Aucun profil MJ associé à votre compte.', 'mj-member') . '</div>';
            return;
        }

        $settings = $this->get_settings_for_display();
        $limit = isset($settings['limit']) ? max(1, (int) $settings['limit']) : 10;
        $registrations = mj_member_get_member_registrations(
            $member->id,
            array(
                'limit' => $limit,
                'upcoming_only' => isset($settings['upcoming_only']) && $settings['upcoming_only'] === 'yes',
            )
        );

        $show_status = isset($settings['show_status']) && $settings['show_status'] === 'yes';
        $show_dates = isset($settings['show_dates']) && $settings['show_dates'] === 'yes';
        $show_type = isset($settings['show_type']) && $settings['show_type'] === 'yes';
        $title = isset($settings['title']) ? $settings['title'] : '';
        $empty_message = isset($settings['empty_message']) ? $settings['empty_message'] : __("Vous n'avez pas encore d'inscription active.", 'mj-member');

        static $styles_printed = false;
        if (!$styles_printed) {
            $styles_printed = true;
            echo '<style>'
                . '.mj-member-registrations{display:grid;gap:20px;}'
                . '.mj-member-registrations__title{margin:0;font-size:1.4rem;}'
                . '.mj-member-registrations__list{list-style:none;margin:0;padding:0;display:grid;gap:18px;}'
                . '.mj-member-registrations__item{border:1px solid #e3e6ea;border-radius:10px;padding:20px;background:#fff;box-shadow:0 8px 20px rgba(0,0,0,0.05);}'
                . '.mj-member-registrations__head{display:flex;justify-content:space-between;gap:12px;align-items:center;margin-bottom:8px;}'
                . '.mj-member-registrations__name{font-weight:700;font-size:1.05rem;margin:0;}'
                . '.mj-member-registrations__badge{display:inline-flex;align-items:center;padding:4px 10px;border-radius:999px;font-size:0.78rem;font-weight:600;background:#edf2ff;color:#2f5dff;}'
                . '.mj-member-registrations__item.status-cancelled .mj-member-registrations__badge{background:#ffecec;color:#d63638;}'
                . '.mj-member-registrations__item.status-waitlist .mj-member-registrations__badge{background:#fff2d6;color:#a05c00;}'
                . '.mj-member-registrations__meta{font-size:0.92rem;color:#555;margin:6px 0;}'
                . '.mj-member-registrations__notes{margin-top:12px;font-size:0.92rem;color:#495057;}'
                . '.mj-member-registrations__actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:14px;}'
                . '.mj-member-registrations__actions a{display:inline-flex;align-items:center;justify-content:center;padding:8px 16px;border-radius:6px;border:1px solid #0073aa;background:#0073aa;color:#fff;font-size:0.9rem;font-weight:600;text-decoration:none;transition:background 0.2s ease,transform 0.2s ease;}'
                . '.mj-member-registrations__actions a:hover{background:#005f8d;border-color:#005f8d;transform:translateY(-1px);}'
                . '.mj-member-registrations__empty{margin:0;font-size:0.95rem;color:#6c757d;}'
                . '</style>';
        }

        echo '<div class="mj-member-registrations">';
        if ($title !== '') {
            echo '<h3 class="mj-member-registrations__title">' . esc_html($title) . '</h3>';
        }

        if (empty($registrations)) {
            echo '<p class="mj-member-registrations__empty">' . esc_html($empty_message) . '</p>';
            echo '</div>';
            return;
        }

        echo '<ul class="mj-member-registrations__list">';
        foreach ($registrations as $entry) {
            $status_class = 'status-' . sanitize_html_class(isset($entry['status']) ? $entry['status'] : 'pending');
            echo '<li class="mj-member-registrations__item ' . esc_attr($status_class) . '">';
            echo '<div class="mj-member-registrations__head">';
            echo '<p class="mj-member-registrations__name">' . esc_html($entry['title']) . '</p>';
            if ($show_status && !empty($entry['status_label'])) {
                echo '<span class="mj-member-registrations__badge">' . esc_html($entry['status_label']) . '</span>';
            }
            echo '</div>';

            if ($show_type && !empty($entry['type'])) {
                echo '<p class="mj-member-registrations__meta">' . esc_html(sprintf(__('Type : %s', 'mj-member'), $entry['type'])) . '</p>';
            }

            if ($show_dates && (!empty($entry['start_date']) || !empty($entry['end_date']))) {
                $dates = '';
                if (!empty($entry['start_date'])) {
                    $dates .= self::format_date($entry['start_date']);
                }
                if (!empty($entry['end_date'])) {
                    $dates .= $dates !== '' ? ' → ' : '';
                    $dates .= self::format_date($entry['end_date']);
                }
                if ($dates !== '') {
                    echo '<p class="mj-member-registrations__meta">' . esc_html(sprintf(__('Dates : %s', 'mj-member'), $dates)) . '</p>';
                }
            }

            if (!empty($entry['notes'])) {
                echo '<div class="mj-member-registrations__notes">' . wp_kses_post($entry['notes']) . '</div>';
            }

            if (!empty($entry['actions'])) {
                echo '<div class="mj-member-registrations__actions">';
                foreach ($entry['actions'] as $action) {
                    $target = !empty($action['target']) ? $action['target'] : '_self';
                    echo '<a href="' . esc_url($action['url']) . '" target="' . esc_attr($target) . '">' . esc_html($action['label']) . '</a>';
                }
                echo '</div>';
            }

            echo '</li>';
        }
        echo '</ul>';
        echo '</div>';
    }

    private static function format_date($value) {
        $timestamp = strtotime($value);
        if ($timestamp) {
            return date_i18n(get_option('date_format', 'd/m/Y'), $timestamp);
        }
        return $value;
    }
}
