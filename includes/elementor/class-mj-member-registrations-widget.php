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
                . '.mj-member-registrations{display:grid;gap:24px;}'
                . '.mj-member-registrations__title{margin:0;font-size:1.6rem;font-weight:700;color:#0f172a;}'
                . '.mj-member-registrations__list{list-style:none;margin:0;padding:0;display:grid;gap:24px;}'
                . '.mj-member-registrations__item{border:1px solid #e2e8f0;border-radius:14px;padding:26px;background:#ffffff;box-shadow:0 12px 28px rgba(15,23,42,0.08);}'
                . '.mj-member-registrations__layout{display:grid;grid-template-columns:minmax(0,1.4fr) minmax(0,1fr);gap:28px;align-items:start;}'
                . '.mj-member-registrations__header{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;margin-bottom:18px;}'
                . '.mj-member-registrations__name{margin:0;font-size:1.15rem;font-weight:700;color:#0f172a;}'
                . '.mj-member-registrations__name a{color:inherit;text-decoration:none;}'
                . '.mj-member-registrations__name a:hover{text-decoration:underline;}'
                . '.mj-member-registrations__badge{display:inline-flex;align-items:center;padding:5px 12px;border-radius:999px;font-size:0.78rem;font-weight:600;background:#e0f2fe;color:#0369a1;white-space:nowrap;}'
                . '.mj-member-registrations__item.status-confirmed .mj-member-registrations__badge{background:#dcfce7;color:#166534;}'
                . '.mj-member-registrations__item.status-pending .mj-member-registrations__badge{background:#fef3c7;color:#92400e;}'
                . '.mj-member-registrations__item.status-cancelled .mj-member-registrations__badge{background:#fee2e2;color:#b91c1c;}'
                . '.mj-member-registrations__item.status-waitlist .mj-member-registrations__badge{background:#ede9fe;color:#5b21b6;}'
                . '.mj-member-registrations__meta-list{list-style:none;margin:0;padding:0;display:grid;gap:10px;}'
                . '.mj-member-registrations__meta-item{display:flex;gap:10px;align-items:flex-start;color:#334155;font-size:0.92rem;}'
                . '.mj-member-registrations__meta-label{min-width:120px;font-weight:600;color:#1e293b;}'
                . '.mj-member-registrations__meta-value{flex:1;word-break:break-word;}'
                . '.mj-member-registrations__notes{margin-top:18px;padding:14px;border-radius:10px;background:#f8fafc;color:#1f2937;font-size:0.92rem;}'
                . '.mj-member-registrations__actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:20px;}'
                . '.mj-member-registrations__action{display:inline-flex;align-items:center;justify-content:center;padding:9px 18px;border-radius:8px;border:1px solid #2563eb;background:#2563eb;color:#ffffff;font-size:0.92rem;font-weight:600;text-decoration:none;transition:background 0.2s ease,transform 0.2s ease;}'
                . '.mj-member-registrations__action:hover{background:#1d4ed8;border-color:#1d4ed8;transform:translateY(-1px);}'
                . '.mj-member-registrations__agenda{border:1px solid #dbeafe;border-radius:12px;background:#f5f9ff;padding:20px;}'
                . '.mj-member-registrations__agenda-title{margin:0 0 12px;font-size:1rem;font-weight:700;color:#0f172a;}'
                . '.mj-member-registrations__agenda-list{margin:0;padding-left:18px;list-style:disc;color:#1f2937;font-size:0.9rem;display:grid;gap:8px;}'
                . '.mj-member-registrations__agenda-item{line-height:1.5;}'
                . '.mj-member-registrations__agenda-empty{margin:0;font-size:0.9rem;color:#475569;}'
                . '.mj-member-registrations__agenda-more{margin:12px 0 0;font-size:0.85rem;color:#334155;}'
                . '.mj-member-registrations__empty{margin:0;font-size:0.95rem;color:#6c757d;}'
                . '@media (max-width: 960px){.mj-member-registrations__layout{grid-template-columns:1fr;}.mj-member-registrations__agenda{order:2;}.mj-member-registrations__details{order:1;}}'
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

            $meta_rows = array();
            if ($show_type && !empty($entry['type'])) {
                $meta_rows[] = array(
                    'label' => __('Type', 'mj-member'),
                    'value' => $entry['type'],
                );
            }

            if ($show_dates) {
                $date_range = self::format_date_range(isset($entry['start_date']) ? $entry['start_date'] : '', isset($entry['end_date']) ? $entry['end_date'] : '');
                if ($date_range !== '') {
                    $meta_rows[] = array(
                        'label' => __('Dates', 'mj-member'),
                        'value' => $date_range,
                    );
                }
            }

            if (!empty($entry['location'])) {
                $meta_rows[] = array(
                    'label' => __('Lieu', 'mj-member'),
                    'value' => $entry['location'],
                );
            }

            if (!empty($entry['payment_status_label'])) {
                $meta_rows[] = array(
                    'label' => __('Paiement', 'mj-member'),
                    'value' => $entry['payment_status_label'],
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

            echo '<li class="mj-member-registrations__item ' . esc_attr('status-' . $status_key) . '">';
            echo '<div class="mj-member-registrations__layout">';

            echo '<div class="mj-member-registrations__details">';
            echo '<div class="mj-member-registrations__header">';
            echo '<h4 class="mj-member-registrations__name">' . $title_markup . '</h4>';
            if ($badge_html !== '') {
                echo $badge_html;
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
                    echo '<a class="mj-member-registrations__action" href="' . esc_url($action['url']) . '" target="' . esc_attr($target) . '">' . esc_html($action['label']) . '</a>';
                }
                echo '</div>';
            }

            echo '</div>';

            echo '<div class="mj-member-registrations__agenda">';
            echo '<p class="mj-member-registrations__agenda-title">' . esc_html($agenda_title) . '</p>';

            if ($displayed_count > 0) {
                echo '<ul class="mj-member-registrations__agenda-list">';
                foreach ($occurrence_items as $item_label) {
                    echo '<li class="mj-member-registrations__agenda-item">' . esc_html($item_label) . '</li>';
                }
                echo '</ul>';
            } elseif ($agenda_empty_message !== '') {
                echo '<p class="mj-member-registrations__agenda-empty">' . esc_html($agenda_empty_message) . '</p>';
            }

            if ($remaining_count > 0) {
                $more_label = sprintf(_n('+ %d autre occurrence', '+ %d autres occurrences', $remaining_count, 'mj-member'), $remaining_count);
                echo '<p class="mj-member-registrations__agenda-more">' . esc_html($more_label) . '</p>';
            }

            echo '</div>';

            echo '</div>';
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

    private static function format_date_range($start, $end) {
        $start = trim((string) $start);
        $end = trim((string) $end);

        $start_label = $start !== '' ? self::format_date($start) : '';
        $end_label = $end !== '' ? self::format_date($end) : '';

        if ($start_label !== '' && $end_label !== '' && $start_label !== $end_label) {
            return $start_label . ' → ' . $end_label;
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

        $label = isset($occurrence['label']) ? trim((string) $occurrence['label']) : '';
        if ($label !== '') {
            return $label;
        }

        $start = isset($occurrence['start']) ? trim((string) $occurrence['start']) : '';
        if ($start === '') {
            return '';
        }

        return self::format_datetime($start);
    }

    private static function format_datetime($value) {
        $timestamp = strtotime($value);
        if ($timestamp) {
            $format = get_option('date_format', 'd/m/Y') . ' ' . get_option('time_format', 'H:i');
            return date_i18n($format, $timestamp);
        }
        return $value;
    }
}
