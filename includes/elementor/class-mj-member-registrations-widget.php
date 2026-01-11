<?php

if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;
use Mj\Member\Core\AssetsManager;

class Mj_Member_Elementor_Registrations_Widget extends Widget_Base {
    use Mj_Member_Elementor_Widget_Visibility;

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
        return array('mj-member');
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

        $this->register_visibility_controls();

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
        $settings = $this->get_settings_for_display();
        $this->apply_visibility_to_wrapper($settings, 'mj-member-registrations');

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

        AssetsManager::requirePackage('registrations-widget');

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
                . '.mj-member-registrations{display:grid;gap:32px;color:#0f172a;}'
                . '.mj-member-registrations__header{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:18px;}'
                . '.mj-member-registrations__heading{display:grid;gap:6px;min-width:220px;}'
                . '.mj-member-registrations__title{margin:0;font-size:1.75rem;font-weight:700;color:#0f172a;}'
                . '.mj-member-registrations__subtitle{margin:0;font-size:0.95rem;color:#475569;}'
                . '.mj-member-registrations__tools{display:flex;align-items:center;gap:12px;flex-wrap:wrap;}'
                . '.mj-member-registrations__search{position:relative;}'
                . '.mj-member-registrations__search input{width:260px;max-width:100%;border:1px solid #cbd5f5;border-radius:999px;padding:10px 16px;font-size:0.92rem;color:#0f172a;background:#ffffff;box-shadow:0 12px 26px rgba(15,23,42,0.08);transition:border-color 0.2s ease,box-shadow 0.2s ease;}'
                . '.mj-member-registrations__search input:focus{outline:none;border-color:#2563eb;box-shadow:0 16px 32px rgba(37,99,235,0.18);}'
                . '.mj-member-registrations__filter{display:inline-flex;align-items:center;gap:8px;padding:10px 16px;border-radius:14px;border:1px solid #cbd5f5;background:#f8fafc;color:#1f2937;font-size:0.9rem;font-weight:600;cursor:pointer;box-shadow:0 10px 22px rgba(15,23,42,0.08);transition:background 0.2s ease,border-color 0.2s ease;}'
                . '.mj-member-registrations__filter:hover{background:#e2e8f0;border-color:#a8c1ff;}'
                . '.mj-member-registrations__filter:focus-visible{outline:2px solid #2563eb;outline-offset:2px;}'
                . '.mj-member-registrations__list{list-style:none;margin:0;padding:0;display:grid;gap:28px;}'
                . '.mj-member-registrations__item{background:#ffffff;border:1px solid #e2e8f0;border-radius:22px;box-shadow:0 22px 48px rgba(15,23,42,0.08);padding:30px;display:grid;gap:24px;}'
                . '.mj-member-registrations__layout{display:grid;grid-template-columns:minmax(0,1.6fr) minmax(320px,0.95fr);gap:32px;align-items:flex-start;}'
                . '.mj-member-registrations__event{display:flex;gap:24px;align-items:flex-start;}'
                . '.mj-member-registrations__media{flex:0 0 140px;height:140px;border-radius:18px;overflow:hidden;position:relative;background:linear-gradient(135deg,#1d4ed8,#2563eb);box-shadow:0 18px 36px rgba(37,99,235,0.22);}'
                . '.mj-member-registrations__media img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;}'
                . '.mj-member-registrations__info{display:grid;gap:16px;flex:1;}'
                . '.mj-member-registrations__meta{display:grid;gap:10px;}'
                . '.mj-member-registrations__name{margin:0;font-size:1.2rem;font-weight:700;color:#0f172a;}'
                . '.mj-member-registrations__name a{color:inherit;text-decoration:none;}'
                . '.mj-member-registrations__name a:hover{text-decoration:underline;}'
                . '.mj-member-registrations__badges{display:flex;flex-wrap:wrap;gap:10px;}'
                . '.mj-member-registrations__badge{padding:5px 14px;border-radius:999px;font-size:0.78rem;font-weight:600;background:#dbeafe;color:#1d4ed8;text-transform:uppercase;letter-spacing:0.05em;}'
                . '.mj-member-registrations__item.status-confirmed .mj-member-registrations__badge{background:#dcfce7;color:#166534;}'
                . '.mj-member-registrations__item.status-pending .mj-member-registrations__badge{background:#fef3c7;color:#92400e;}'
                . '.mj-member-registrations__item.status-cancelled .mj-member-registrations__badge{background:#fee2e2;color:#b91c1c;}'
                . '.mj-member-registrations__item.status-waitlist .mj-member-registrations__badge{background:#ede9fe;color:#5b21b6;}'
                . '.mj-member-registrations__meta-list{list-style:none;margin:0;padding:0;display:grid;gap:8px;font-size:0.95rem;color:#475569;}'
                . '.mj-member-registrations__meta-item{display:flex;gap:12px;}'
                . '.mj-member-registrations__meta-label{min-width:130px;font-weight:600;color:#1e293b;}'
                . '.mj-member-registrations__meta-value{flex:1;}'
                . '.mj-member-registrations__location-media{margin-top:18px;width:148px;max-width:100%;height:96px;border-radius:16px;overflow:hidden;box-shadow:0 16px 34px rgba(15,23,42,0.12);background:#e2e8f0;}'
                . '.mj-member-registrations__location-media img{width:100%;height:100%;object-fit:cover;}'
                . '.mj-member-registrations__notes{margin:0;padding:16px;border-radius:14px;background:#f8fafc;color:#1f2937;font-size:0.92rem;line-height:1.5;}'
                . '.mj-member-registrations__actions{display:flex;flex-wrap:wrap;gap:12px;}'
                . '.mj-member-registrations__action{display:inline-flex;align-items:center;gap:8px;padding:10px 18px;border-radius:14px;border:1px solid #1d4ed8;background:linear-gradient(135deg,#2563eb,#1d4ed8);color:#ffffff;font-size:0.92rem;font-weight:600;text-decoration:none;box-shadow:0 16px 34px rgba(37,99,235,0.24);transition:transform 0.2s ease,box-shadow 0.2s ease;}'
                . '.mj-member-registrations__action:hover{transform:translateY(-1px);box-shadow:0 22px 40px rgba(37,99,235,0.28);}'
                . '.mj-member-registrations__action:focus-visible{outline:2px solid #1d4ed8;outline-offset:2px;}'
                . '.mj-member-registrations__action-icon{font-size:1.1rem;line-height:1;}'
                . '.mj-member-registrations__calendar-card{display:grid;gap:18px;background:#f8fbff;border:1px solid #dbeafe;border-radius:20px;padding:20px;box-shadow:0 12px 26px rgba(15,23,42,0.08);}'
                . '.mj-member-registrations__calendar-top{display:flex;align-items:center;justify-content:space-between;gap:12px;}'
                . '.mj-member-registrations__calendar-meta{display:flex;align-items:center;gap:10px;flex-wrap:wrap;}'
                . '.mj-member-registrations__calendar-month{font-size:1.05rem;font-weight:700;color:#0f172a;}'
                . '.mj-member-registrations__calendar-nav{display:flex;align-items:center;gap:6px;}'
                . '.mj-member-registrations__calendar-nav button{width:34px;height:34px;border-radius:10px;border:1px solid #cbd5f5;background:#ffffff;color:#1d4ed8;font-weight:700;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:background 0.2s ease,border-color 0.2s ease,color 0.2s ease;}'
                . '.mj-member-registrations__calendar-nav button:hover{background:#e0ecff;border-color:#2563eb;color:#0f172a;}'
                . '.mj-member-registrations__calendar-nav button:focus-visible{outline:2px solid #2563eb;outline-offset:2px;}'
                . '.mj-member-registrations__manage-button{display:inline-flex;align-items:center;gap:8px;padding:9px 18px;border-radius:12px;border:1px solid #2563eb;background:linear-gradient(135deg,#2563eb,#1d4ed8);color:#ffffff;font-size:0.9rem;font-weight:600;cursor:pointer;box-shadow:0 18px 34px rgba(37,99,235,0.22);transition:transform 0.2s ease,box-shadow 0.2s ease;}'
                . '.mj-member-registrations__manage-button:hover{transform:translateY(-1px);box-shadow:0 24px 44px rgba(37,99,235,0.28);}'
                . '.mj-member-registrations__calendar-weekdays{display:grid;grid-template-columns:repeat(7,1fr);gap:6px;font-size:0.75rem;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;text-align:center;}'
                . '.mj-member-registrations__calendar-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:6px;}'
                . '.mj-member-registrations__calendar-cell{padding:12px 0;border-radius:12px;border:1px solid #e2e8f0;background:#ffffff;color:#0f172a;font-weight:600;text-align:center;box-shadow:0 4px 10px rgba(15,23,42,0.06);transition:background 0.2s ease,border-color 0.2s ease,color 0.2s ease;}'
                . '.mj-member-registrations__calendar-cell.is-muted{color:#94a3b8;background:#f1f5f9;border-style:dashed;}'
                . '.mj-member-registrations__calendar-cell.is-available{border-color:#93c5fd;background:#e8f2ff;color:#1d4ed8;}'
                . '.mj-member-registrations__calendar-cell.is-selected{background:#2563eb;border-color:#1d4ed8;color:#ffffff;box-shadow:0 18px 32px rgba(37,99,235,0.25);}'
                . '.mj-member-registrations__calendar-cell[data-today="1"]::after{content:"";position:absolute;bottom:8px;left:50%;width:6px;height:6px;border-radius:50%;background:#1d4ed8;transform:translateX(-50%);}'
                . '.mj-member-registrations__calendar-cell{position:relative;}'
                . '.mj-member-registrations__selection{border-top:1px solid #dbeafe;padding-top:14px;display:grid;gap:12px;}'
                . '.mj-member-registrations__selection-heading{display:grid;gap:4px;}'
                . '.mj-member-registrations__selection-title{font-size:0.95rem;font-weight:700;color:#1e293b;margin:0;}'
                . '.mj-member-registrations__selection-note{margin:0;font-size:0.82rem;color:#64748b;}'
                . '.mj-member-registrations__agenda-list{margin:0;padding-left:18px;list-style:disc;color:#1f2937;font-size:0.88rem;display:grid;gap:8px;}'
                . '.mj-member-registrations__agenda-item{line-height:1.4;}'
                . '.mj-member-registrations__agenda-empty{margin:0;font-size:0.9rem;color:#64748b;}'
                . '.mj-member-registrations__agenda-more{margin:0;font-size:0.82rem;color:#2563eb;font-weight:600;}'
                . '.mj-member-registrations__manager{display:grid;gap:16px;}'
                . '.mj-member-registrations__manager-panel{display:grid;gap:16px;padding:20px;border-radius:18px;border:1px solid #dbeafe;background:#ffffff;box-shadow:0 20px 44px rgba(15,23,42,0.12);}'
                . '.mj-member-registrations__manager-header{display:grid;gap:6px;}'
                . '.mj-member-registrations__manager-title{margin:0;font-size:1.05rem;font-weight:700;color:#0f172a;}'
                . '.mj-member-registrations__manager-help{margin:0;font-size:0.9rem;color:#475569;}'
                . '.mj-member-registrations__manager-form{display:grid;gap:16px;}'
                . '.mj-member-registrations__manager-list{display:grid;gap:10px;max-height:280px;overflow:auto;padding-right:4px;}'
                . '.mj-member-registrations__manager-option{display:grid;grid-template-columns:auto 1fr;align-items:center;gap:12px;padding:12px 14px;border:1px solid #e2e8f0;border-radius:14px;background:#f8fafc;transition:border-color 0.2s ease,background-color 0.2s ease;}'
                . '.mj-member-registrations__manager-option.is-past{opacity:0.6;}'
                . '.mj-member-registrations__manager-checkbox{position:absolute;opacity:0;width:18px;height:18px;}'
                . '.mj-member-registrations__manager-checkmark{width:18px;height:18px;border-radius:6px;border:2px solid #2563eb;background:#ffffff;display:inline-flex;align-items:center;justify-content:center;}'
                . '.mj-member-registrations__manager-option input:checked ~ .mj-member-registrations__manager-checkmark{background:linear-gradient(135deg,#2563eb,#1d4ed8);border-color:#1d4ed8;}'
                . '.mj-member-registrations__manager-option input:checked ~ .mj-member-registrations__manager-label{font-weight:600;color:#1d4ed8;}'
                . '.mj-member-registrations__manager-label{font-size:0.92rem;color:#1f2937;}'
                . '.mj-member-registrations__manager-actions{display:flex;justify-content:flex-end;gap:12px;flex-wrap:wrap;}'
                . '.mj-member-registrations__manager-cancel{padding:10px 18px;border-radius:12px;border:1px solid #cbd5f5;background:#f8fafc;color:#1f2937;font-size:0.88rem;font-weight:600;cursor:pointer;}'
                . '.mj-member-registrations__manager-submit{padding:10px 20px;border-radius:12px;border:1px solid #2563eb;background:linear-gradient(135deg,#2563eb,#1d4ed8);color:#ffffff;font-size:0.9rem;font-weight:600;cursor:pointer;box-shadow:0 16px 34px rgba(37,99,235,0.24);}'
                . '.mj-member-registrations__manager-submit:disabled{opacity:0.55;cursor:not-allowed;box-shadow:none;}'
                . '.mj-member-registrations__manager-feedback{margin:0;font-size:0.85rem;color:#475569;}'
                . '.mj-member-registrations__manager-feedback.is-error{color:#b91c1c;}'
                . '.mj-member-registrations__empty{margin:0;font-size:0.95rem;color:#6c757d;}'
                . '@media (max-width:1180px){.mj-member-registrations__layout{grid-template-columns:1fr;}.mj-member-registrations__calendar-card{order:2;}.mj-member-registrations__event{flex-direction:column;}.mj-member-registrations__media{width:100%;max-width:260px;height:auto;padding-bottom:56%;}}'
                . '@media (max-width:640px){.mj-member-registrations__item{padding:22px;}.mj-member-registrations__search input{width:100%;}.mj-member-registrations__calendar-grid,.mj-member-registrations__calendar-weekdays{gap:4px;}.mj-member-registrations__calendar-cell{padding:10px 0;}}'
                . '</style>';
        }

        echo '<div class="mj-member-registrations">';
        $heading_title = $title !== '' ? $title : __('Mes inscriptions', 'mj-member');
        echo '<div class="mj-member-registrations__header">';
        echo '<div class="mj-member-registrations__heading">';
        echo '<h3 class="mj-member-registrations__title">' . esc_html($heading_title) . '</h3>';
        echo '<p class="mj-member-registrations__subtitle">' . esc_html__('Suivez et ajustez vos occurrences à venir.', 'mj-member') . '</p>';
        echo '</div>';
        echo '<div class="mj-member-registrations__tools">';
        echo '<label class="mj-member-registrations__search">';
        echo '<span class="screen-reader-text">' . esc_html__('Rechercher une inscription', 'mj-member') . '</span>';
        echo '<input type="search" data-mj-registrations-search placeholder="' . esc_attr__('Rechercher une inscription…', 'mj-member') . '" autocomplete="off" />';
        echo '</label>';
        echo '<button type="button" class="mj-member-registrations__filter" data-mj-registrations-filter>' . esc_html__('Filtres', 'mj-member') . '</button>';
        echo '</div>';
        echo '</div>';

        if (empty($registrations)) {
            echo '<p class="mj-member-registrations__empty">' . esc_html($empty_message) . '</p>';
            echo '</div>';
            return;
        }

        echo '<ul class="mj-member-registrations__list">';
        foreach ($registrations as $entry) {
            $status_key = isset($entry['status']) ? sanitize_html_class($entry['status']) : 'pending';
            if ($status_key === '') {
                $status_key = 'pending';
            }

            $status_label = !empty($entry['status_label']) ? $entry['status_label'] : '';
            $badge_html = '';
            if ($show_status && $status_label !== '') {
                $badge_html = '<span class="mj-member-registrations__badge">' . esc_html($status_label) . '</span>';
            }

            $permalink = !empty($entry['permalink']) ? $entry['permalink'] : '';
            $title_markup = esc_html($entry['title']);
            if ($permalink !== '') {
                $title_markup = '<a href="' . esc_url($permalink) . '">' . $title_markup . '</a>';
            }

            $cover_url = '';
            $cover_alt = '';
            if (!empty($entry['cover']) && is_array($entry['cover'])) {
                if (!empty($entry['cover']['url'])) {
                    $cover_url = trim((string) $entry['cover']['url']);
                }
                if (!empty($entry['cover']['alt'])) {
                    $cover_alt = trim((string) $entry['cover']['alt']);
                }
            }
            if ($cover_alt === '' && !empty($entry['title'])) {
                $cover_alt = trim((string) $entry['title']);
            }
            if ($cover_alt !== '') {
                $cover_alt = wp_strip_all_tags($cover_alt);
            }

            $price_label = isset($entry['price_label']) ? trim((string) $entry['price_label']) : '';
            $payment_label = isset($entry['payment_status_label']) ? trim((string) $entry['payment_status_label']) : '';
            $is_free = !empty($entry['is_free']);
            $location_label_value = !empty($entry['location']) ? trim((string) $entry['location']) : '';
            $price_value_text = (!$is_free && $price_label !== '') ? $price_label : '';
            $location_media_url = '';
            $location_media_alt = '';
            if (!empty($entry['location_media']) && is_array($entry['location_media'])) {
                if (!empty($entry['location_media']['url'])) {
                    $location_media_url = esc_url($entry['location_media']['url']);
                }
                if (!empty($entry['location_media']['alt'])) {
                    $location_media_alt = sanitize_text_field($entry['location_media']['alt']);
                }
            }
            if ($location_media_alt === '' && $location_label_value !== '') {
                $location_media_alt = sanitize_text_field($location_label_value);
            }

            $meta_rows = array();
            if ($show_type && !empty($entry['type'])) {
                $meta_rows[] = array(
                    'label' => __('Type', 'mj-member'),
                    'value' => $entry['type'],
                );
            }

            $actions = !empty($entry['actions']) && is_array($entry['actions']) ? $entry['actions'] : array();
            if (empty($actions) && $permalink !== '') {
                $actions[] = array(
                    'url' => $permalink,
                    'label' => __('Voir l’événement', 'mj-member'),
                    'target' => '_self',
                );
            }

            $all_occurrences = (!empty($entry['occurrences']) && is_array($entry['occurrences'])) ? $entry['occurrences'] : array();
            $available_occurrences = (!empty($entry['available_occurrences']) && is_array($entry['available_occurrences'])) ? $entry['available_occurrences'] : array();
            $occurrence_payload = self::normalize_occurrence_payload($all_occurrences);
            $available_payload = self::normalize_occurrence_payload($available_occurrences);
            $calendar_slots = self::build_weekday_calendar_slots($available_occurrences, $all_occurrences);
            $display_limit = 8;
            $display_occurrences = array_slice($all_occurrences, 0, $display_limit);
            $occurrence_items = array();
            foreach ($display_occurrences as $occurrence) {
                if (!is_array($occurrence)) {
                    continue;
                }
                $formatted_occurrence = self::format_occurrence_entry($occurrence);
                if ($formatted_occurrence === '') {
                    continue;
                }
                $occurrence_items[] = $formatted_occurrence;
            }

            if ($location_label_value !== '') {
                $meta_rows[] = array(
                    'label' => __('Lieu', 'mj-member'),
                    'value' => $location_label_value,
                );
            }

            if ($price_value_text !== '') {
                $meta_rows[] = array(
                    'label' => __('Tarif', 'mj-member'),
                    'value' => $price_value_text,
                );
            }

            if (!$is_free && $payment_label !== '') {
                $meta_rows[] = array(
                    'label' => __('Paiement', 'mj-member'),
                    'value' => $payment_label,
                );
            }

            $displayed_count = count($occurrence_items);
            $stored_total = count($all_occurrences);
            $occurrence_count = isset($entry['occurrence_count']) ? (int) $entry['occurrence_count'] : 0;
            if ($occurrence_count <= 0) {
                $occurrence_count = max($displayed_count, $stored_total);
            }
            $remaining_count = max(0, $occurrence_count - $displayed_count);
            if ($stored_total > $display_limit) {
                $remaining_count = max($remaining_count, $stored_total - $display_limit);
            }

            $occurrence_scope = isset($entry['occurrence_scope']) ? sanitize_key((string) $entry['occurrence_scope']) : 'all';
            if ($occurrence_scope === '') {
                $occurrence_scope = 'all';
            }

            $agenda_title = __('Agenda', 'mj-member');
            if ($occurrence_scope === 'custom') {
                $agenda_title = __('Jours sélectionnés', 'mj-member');
            } elseif ($occurrence_scope === 'all') {
                $agenda_title = __('Occurrences de l’événement', 'mj-member');
            }

            $agenda_empty_message = '';
            if ($displayed_count === 0) {
                if ($occurrence_scope === 'all') {
                    $agenda_empty_message = __('Inscription valable pour toutes les occurrences de l’événement.', 'mj-member');
                } elseif ($occurrence_scope === 'custom') {
                    $agenda_empty_message = __('Aucune occurrence sélectionnée pour cette inscription.', 'mj-member');
                } else {
                    $agenda_empty_message = __('Agenda à confirmer.', 'mj-member');
                }
            }

            $event_id = isset($entry['event_id']) ? absint($entry['event_id']) : 0;
            $registration_id = isset($entry['registration_id']) ? absint($entry['registration_id']) : 0;
            $member_id = isset($entry['member_id']) ? absint($entry['member_id']) : (isset($member->id) ? absint($member->id) : 0);
            $guardian_id = isset($entry['guardian_id']) ? absint($entry['guardian_id']) : 0;
            $can_manage_occurrences_entry = !empty($entry['can_manage_occurrences']);

            $manager_config = array();
            if ($can_manage_occurrences_entry && !empty($available_payload) && $event_id > 0 && $member_id > 0) {
                $manager_config = array(
                    'eventId' => $event_id,
                    'memberId' => $member_id,
                    'registrationId' => $registration_id,
                    'occurrenceScope' => $occurrence_scope,
                    'occurrenceCount' => $occurrence_count,
                    'occurrences' => $occurrence_payload,
                    'availableOccurrences' => $available_payload,
                    'canManageOccurrences' => true,
                );

                if ($guardian_id > 0) {
                    $manager_config['guardianId'] = $guardian_id;
                }

                self::ensure_script_localized();
            }

            echo '<li class="mj-member-registrations__item ' . esc_attr('status-' . $status_key) . '" data-mj-registrations-item="1" data-event-id="' . esc_attr($event_id) . '" data-registration-id="' . esc_attr($registration_id) . '" data-member-id="' . esc_attr($member_id) . '">';
            echo '<div class="mj-member-registrations__layout">';

            echo '<div class="mj-member-registrations__event">';
            if ($cover_url !== '') {
                echo '<figure class="mj-member-registrations__media">';
                echo '<img src="' . esc_url($cover_url) . '" alt="' . esc_attr($cover_alt) . '" loading="lazy" decoding="async" />';
                echo '</figure>';
            }

            echo '<div class="mj-member-registrations__info">';
            echo '<div class="mj-member-registrations__meta">';
            echo '<h4 class="mj-member-registrations__name">' . $title_markup . '</h4>';
            if ($badge_html !== '') {
                echo '<div class="mj-member-registrations__badges">' . $badge_html . '</div>';
            }
            echo '</div>';

            if (!empty($meta_rows)) {
                echo '<ul class="mj-member-registrations__meta-list">';
                foreach ($meta_rows as $row) {
                    $label = isset($row['label']) ? $row['label'] : '';
                    $value = isset($row['value']) ? $row['value'] : '';
                    if ($label === '' || $value === '') {
                        continue;
                    }
                    echo '<li class="mj-member-registrations__meta-item">';
                    echo '<span class="mj-member-registrations__meta-label">' . esc_html($label) . '</span>';
                    echo '<span class="mj-member-registrations__meta-value">' . esc_html($value) . '</span>';
                    echo '</li>';
                }
                echo '</ul>';
            }

            if ($location_media_url !== '') {
                echo '<div class="mj-member-registrations__location-media">';
                echo '<img src="' . esc_url($location_media_url) . '" alt="' . esc_attr($location_media_alt) . '" loading="lazy" decoding="async" />';
                echo '</div>';
            }

            if (!empty($entry['notes'])) {
                echo '<div class="mj-member-registrations__notes">' . wp_kses_post($entry['notes']) . '</div>';
            }

            if (!empty($actions)) {
                echo '<div class="mj-member-registrations__actions">';
                foreach ($actions as $action) {
                    if (empty($action['url']) || empty($action['label'])) {
                        continue;
                    }
                    $target = !empty($action['target']) ? $action['target'] : '_self';
                    $rel_attr = ($target === '_blank') ? ' rel="noopener"' : '';
                    echo '<a class="mj-member-registrations__action" href="' . esc_url($action['url']) . '" target="' . esc_attr($target) . '"' . $rel_attr . '><span>' . esc_html($action['label']) . '</span><span class="mj-member-registrations__action-icon" aria-hidden="true">&rarr;</span></a>';
                }
                echo '</div>';
            }

            echo '</div>';

            if (!empty($manager_config)) {
                $config_json = wp_json_encode($manager_config);
                if (is_string($config_json) && $config_json !== '') {
                    $panel_id = function_exists('wp_unique_id') ? wp_unique_id('mj-registrations-panel-') : uniqid('mj-registrations-panel-');
                    $title_id = $panel_id . '-title';
                    $help_id = $panel_id . '-help';

                    echo '<div class="mj-member-registrations__manager" data-mj-registrations-entry data-manager-open="0" data-config="' . esc_attr($config_json) . '">';
                    echo '<button type="button" class="mj-member-registrations__manage-button" data-mj-registrations-manage aria-haspopup="dialog" aria-expanded="false" aria-controls="' . esc_attr($panel_id) . '">' . esc_html__('Gérer mes séances', 'mj-member') . '</button>';
                    echo '<div class="mj-member-registrations__manager-panel" id="' . esc_attr($panel_id) . '" data-mj-registrations-panel hidden tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="' . esc_attr($title_id) . '" aria-describedby="' . esc_attr($help_id) . '">';
                    echo '<div class="mj-member-registrations__manager-header">';
                    echo '<h5 class="mj-member-registrations__manager-title" id="' . esc_attr($title_id) . '" data-mj-registrations-panel-title>' . esc_html__('Choisis tes séances', 'mj-member') . '</h5>';
                    echo '<p class="mj-member-registrations__manager-help" id="' . esc_attr($help_id) . '" data-mj-registrations-panel-help>' . esc_html__('Sélectionne les séances auxquelles tu participes.', 'mj-member') . '</p>';
                    echo '</div>';
                    echo '<form class="mj-member-registrations__manager-form" data-mj-registrations-form novalidate>';
                    echo '<div class="mj-member-registrations__manager-list" data-mj-registrations-list></div>';
                    echo '<p class="mj-member-registrations__manager-feedback" data-mj-registrations-feedback role="alert"></p>';
                    echo '<div class="mj-member-registrations__manager-actions">';
                    echo '<button type="button" class="mj-member-registrations__manager-cancel" data-mj-registrations-cancel>' . esc_html__('Annuler', 'mj-member') . '</button>';
                    echo '<button type="submit" class="mj-member-registrations__manager-submit" data-mj-registrations-submit>' . esc_html__('Enregistrer', 'mj-member') . '</button>';
                    echo '</div>';
                    echo '</form>';
                    echo '</div>';
                    echo '</div>';
                }
            }

            echo '</div>';

            echo '<aside class="mj-member-registrations__calendar-card" data-mj-registrations-agenda data-occurrence-scope="' . esc_attr($occurrence_scope) . '">';
            echo '<div class="mj-member-registrations__calendar-top">';
            echo '<div class="mj-member-registrations__calendar-meta">';
            echo '<span class="mj-member-registrations__calendar-month" data-mj-registrations-agenda-title>' . esc_html($agenda_title) . '</span>';
            echo '</div>';
            echo '</div>';

            if (!empty($calendar_slots)) {
                echo '<div class="mj-member-registrations__calendar-weekdays" aria-hidden="true">';
                foreach ($calendar_slots as $slot) {
                    echo '<span>' . esc_html($slot['short_label']) . '</span>';
                }
                echo '</div>';
                echo '<div class="mj-member-registrations__calendar-grid" role="list">';
                foreach ($calendar_slots as $slot) {
                    $day_classes = array('mj-member-registrations__calendar-cell');
                    if (!empty($slot['available'])) {
                        $day_classes[] = 'is-available';
                    }
                    if (!empty($slot['selected'])) {
                        $day_classes[] = 'is-selected';
                    }
                    $aria_label = self::build_calendar_slot_label($slot);
                    $weekday_key = isset($slot['key']) ? sanitize_key((string) $slot['key']) : '';
                    $weekday_label = isset($slot['long_label']) ? (string) $slot['long_label'] : '';
                    echo '<span class="' . esc_attr(implode(' ', $day_classes)) . '" role="listitem" data-weekday="' . esc_attr($weekday_key) . '" data-weekday-label="' . esc_attr($weekday_label) . '" aria-label="' . esc_attr($aria_label) . '">' . esc_html($slot['short_label']) . '</span>';
                }
                echo '</div>';
            }

            echo '<div class="mj-member-registrations__selection">';
            echo '<div class="mj-member-registrations__selection-heading">';
            echo '<p class="mj-member-registrations__selection-title">' . esc_html__('Jours sélectionnés', 'mj-member') . '</p>';
            echo '<p class="mj-member-registrations__selection-note">' . esc_html__('Séances sélectionnées', 'mj-member') . '</p>';
            echo '</div>';

            $list_hidden_attr = $displayed_count > 0 ? '' : ' hidden';
            echo '<ul class="mj-member-registrations__agenda-list" data-mj-registrations-agenda-list' . $list_hidden_attr . '>';
            if ($displayed_count > 0) {
                foreach ($occurrence_items as $item_label) {
                    echo '<li class="mj-member-registrations__agenda-item">' . esc_html($item_label) . '</li>';
                }
            }
            echo '</ul>';

            $empty_hidden_attr = $displayed_count > 0 ? ' hidden' : '';
            echo '<p class="mj-member-registrations__agenda-empty" data-mj-registrations-agenda-empty' . $empty_hidden_attr . '>' . esc_html($agenda_empty_message) . '</p>';

            if ($remaining_count > 0) {
                $more_label = sprintf(_n('+ %d autre occurrence', '+ %d autres occurrences', $remaining_count, 'mj-member'), $remaining_count);
                echo '<p class="mj-member-registrations__agenda-more" data-mj-registrations-agenda-more>' . esc_html($more_label) . '</p>';
            } else {
                echo '<p class="mj-member-registrations__agenda-more" data-mj-registrations-agenda-more hidden></p>';
            }

            echo '</div>';
            echo '</aside>';

            echo '</div>';
            echo '</li>';
        }
        echo '</ul>';
        echo '</div>';
    }

    private static function ensure_script_localized() {
        static $localized = false;

        if ($localized) {
            return;
        }

        $handle = 'mj-member-registrations-widget';
        if (!wp_script_is($handle, 'registered')) {
            return;
        }

        $strings = array(
            'locale' => get_locale(),
            'calendarStateNone' => __('Aucune séance prévue', 'mj-member'),
            'calendarStateSelected' => __('Séance sélectionnée', 'mj-member'),
            'calendarStateAvailable' => __('Séance possible', 'mj-member'),
            'calendarDay' => __('Jour de séance', 'mj-member'),
            'agendaTitleDefault' => __('Agenda', 'mj-member'),
            'agendaTitleCustom' => __('Jours sélectionnés', 'mj-member'),
            'agendaTitleAll' => __('Occurrences de l’événement', 'mj-member'),
            'agendaEmptyUnknown' => __('Agenda à confirmer.', 'mj-member'),
            'agendaEmptyAll' => __('Inscription valable pour toutes les occurrences de l’événement.', 'mj-member'),
            'agendaEmptyCustom' => __('Aucune occurrence sélectionnée pour cette inscription.', 'mj-member'),
            'moreOne' => __('+ %d autre occurrence', 'mj-member'),
            'moreMany' => __('+ %d autres occurrences', 'mj-member'),
            'panelHelpAll' => __('Toutes les occurrences sont actuellement incluses.', 'mj-member'),
            'panelHelpCustom' => __('Sélectionne les séances auxquelles tu participes.', 'mj-member'),
            'panelTitle' => __('Choisis tes séances', 'mj-member'),
            'selectionRequired' => __('Merci de sélectionner au moins une occurrence.', 'mj-member'),
            'error' => __('Une erreur est survenue. Merci de réessayer.', 'mj-member'),
            'noChange' => __('Aucun changement détecté.', 'mj-member'),
            'loading' => __('En cours...', 'mj-member'),
            'success' => __('Occurrences mises à jour.', 'mj-member'),
            'submitLabel' => __('Enregistrer', 'mj-member'),
        );

        $data = array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mj-member-event-register'),
            'strings' => $strings,
        );

        wp_localize_script($handle, 'mjMemberRegistrationsWidget', $data);
        $localized = true;
    }

    /**
     * @param array<int,array<string,mixed>> $occurrences
     * @return array<int,array<string,string>>
     */
    private static function normalize_occurrence_payload(array $occurrences) {
        $normalized = array();

        foreach ($occurrences as $occurrence) {
            if (!is_array($occurrence)) {
                continue;
            }

            $start = isset($occurrence['start']) ? trim((string) $occurrence['start']) : '';
            $end = isset($occurrence['end']) ? trim((string) $occurrence['end']) : '';
            $label = isset($occurrence['label']) ? trim((string) $occurrence['label']) : '';

            if ($start === '' && $label === '') {
                continue;
            }

            $normalized[] = array(
                'start' => $start,
                'end' => $end,
                'label' => $label,
            );
        }

        return $normalized;
    }

    private static function format_date($value) {
        $timestamp = strtotime($value);
        if ($timestamp) {
            return date_i18n(get_option('date_format', 'd/m/Y'), $timestamp);
        }
        return $value;
    }

    private static function format_date_range($start, $end) {
        $start = trim((string) $start);
        $end = trim((string) $end);

        $start_label = $start !== '' ? self::format_date($start) : '';
        $end_label = $end !== '' ? self::format_date($end) : '';

        if ($start_label !== '' && $end_label !== '' && $start_label !== $end_label) {
            return $start_label . ' - ' . $end_label;
        }

        if ($end_label !== '' && $start_label === '') {
            return $end_label;
        }

        return $start_label;
    }

    private static function format_occurrence_entry($occurrence) {
        if (!is_array($occurrence)) {
            return '';
        }

        $start = isset($occurrence['start']) ? trim((string) $occurrence['start']) : '';
        $label = isset($occurrence['label']) ? trim((string) $occurrence['label']) : '';
        $end = isset($occurrence['end']) ? trim((string) $occurrence['end']) : '';

        if ($start !== '') {
            $start_ts = strtotime($start);
            $end_ts = $end !== '' ? strtotime($end) : false;

            if ($start_ts) {
                $weekday_source = function_exists('wp_date') ? wp_date('l', $start_ts) : date_i18n('l', $start_ts, false);
                $weekday_label = self::normalize_weekday_label($weekday_source);
                $start_time = self::format_time_label($start_ts);
                $end_time = ($end_ts !== false && $end_ts) ? self::format_time_label($end_ts) : '';

                if ($weekday_label !== '' && $start_time !== '' && $end_time !== '') {
                    return trim($weekday_label . ' ' . $start_time . ' - ' . $end_time);
                }

                if ($weekday_label !== '' && $start_time !== '') {
                    return trim($weekday_label . ' ' . $start_time);
                }

                if ($start_time !== '' && $end_time !== '') {
                    return $start_time . ' - ' . $end_time;
                }
            }
        }

        if ($label !== '') {
            return $label;
        }

        if ($start !== '') {
            return self::format_datetime($start);
        }

        return '';
    }

    private static function format_datetime($value) {
        $timestamp = strtotime($value);
        if ($timestamp) {
            $format = get_option('date_format', 'd/m/Y') . ' ' . get_option('time_format', 'H:i');
            return date_i18n($format, $timestamp);
        }
        return $value;
    }

    private static function format_time_label($timestamp) {
        if (function_exists('wp_date')) {
            $hours = wp_date('H', $timestamp);
            $minutes = wp_date('i', $timestamp);
        } else {
            $hours = date_i18n('H', $timestamp, false);
            $minutes = date_i18n('i', $timestamp, false);
        }

        $hours = ltrim($hours, '0');
        if ($hours === '') {
            $hours = '0';
        }

        if ($minutes === '00') {
            return $hours . 'h';
        }

        return $hours . 'h' . $minutes;
    }

    private static function normalize_weekday_label($value) {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        if (function_exists('mb_substr') && function_exists('mb_strtoupper')) {
            $first = mb_substr($value, 0, 1, 'UTF-8');
            $rest = mb_substr($value, 1, null, 'UTF-8');
            return mb_strtoupper($first, 'UTF-8') . $rest;
        }

        return ucfirst($value);
    }

    /**
     * @param array<int,array<string,mixed>> $availableOccurrences
     * @param array<int,array<string,mixed>> $selectedOccurrences
     * @return array<int,array<string,mixed>>
     */
    private static function build_weekday_calendar_slots(array $availableOccurrences, array $selectedOccurrences) {
        $availableSet = self::extract_weekday_set($availableOccurrences);
        $selectedSet = self::extract_weekday_set($selectedOccurrences);

        $definitions = array(
            array('key' => 'monday', 'short' => _x('Lu', 'weekday short label', 'mj-member'), 'long' => __('Lundi', 'mj-member')),
            array('key' => 'tuesday', 'short' => _x('Ma', 'weekday short label', 'mj-member'), 'long' => __('Mardi', 'mj-member')),
            array('key' => 'wednesday', 'short' => _x('Me', 'weekday short label', 'mj-member'), 'long' => __('Mercredi', 'mj-member')),
            array('key' => 'thursday', 'short' => _x('Je', 'weekday short label', 'mj-member'), 'long' => __('Jeudi', 'mj-member')),
            array('key' => 'friday', 'short' => _x('Ve', 'weekday short label', 'mj-member'), 'long' => __('Vendredi', 'mj-member')),
            array('key' => 'saturday', 'short' => _x('Sa', 'weekday short label', 'mj-member'), 'long' => __('Samedi', 'mj-member')),
            array('key' => 'sunday', 'short' => _x('Di', 'weekday short label', 'mj-member'), 'long' => __('Dimanche', 'mj-member')),
        );

        $slots = array();
        $hasAvailable = false;

        foreach ($definitions as $definition) {
            $key = $definition['key'];
            $isAvailable = isset($availableSet[$key]);
            $isSelected = isset($selectedSet[$key]);

            if ($isAvailable) {
                $hasAvailable = true;
            }

            $slots[] = array(
                'key' => $key,
                'short_label' => $definition['short'],
                'long_label' => $definition['long'],
                'available' => $isAvailable,
                'selected' => $isSelected,
            );
        }

        if (!$hasAvailable) {
            return array();
        }

        return $slots;
    }

    /**
     * @param array<int,array<string,mixed>> $occurrences
     * @return array<string,bool>
     */
    private static function extract_weekday_set(array $occurrences) {
        $set = array();
        foreach ($occurrences as $occurrence) {
            if (!is_array($occurrence) || empty($occurrence['start'])) {
                continue;
            }

            $timestamp = strtotime((string) $occurrence['start']);
            if (!$timestamp) {
                continue;
            }

            $dayValue = function_exists('wp_date') ? wp_date('N', $timestamp) : date_i18n('N', $timestamp, false);
            $dayIndex = (int) $dayValue;
            $dayKey = self::weekday_index_to_key($dayIndex);
            if ($dayKey === '') {
                continue;
            }

            $set[$dayKey] = true;
        }

        return $set;
    }

    private static function weekday_index_to_key($index) {
        switch ($index) {
            case 1:
                return 'monday';
            case 2:
                return 'tuesday';
            case 3:
                return 'wednesday';
            case 4:
                return 'thursday';
            case 5:
                return 'friday';
            case 6:
                return 'saturday';
            case 7:
                return 'sunday';
            default:
                return '';
        }
    }

    /**
     * @param array<string,mixed> $slot
     * @return string
     */
    private static function build_calendar_slot_label(array $slot) {
        $state = __('Aucune séance prévue', 'mj-member');
        if (!empty($slot['selected'])) {
            $state = __('Séance sélectionnée', 'mj-member');
        } elseif (!empty($slot['available'])) {
            $state = __('Séance possible', 'mj-member');
        }

        $long = isset($slot['long_label']) ? (string) $slot['long_label'] : '';
        if ($long === '') {
            $long = __('Jour de séance', 'mj-member');
        }

        return trim($long . ' — ' . $state);
    }
}
