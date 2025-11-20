<?php

if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Controls_Manager;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;

class Mj_Member_Elementor_Events_Calendar_Widget extends Widget_Base {
    public function get_name() {
        return 'mj-member-events-calendar';
    }

    public function get_title() {
        return __('Calendrier des événements MJ', 'mj-member');
    }

    public function get_icon() {
        return 'eicon-calendar';
    }

    public function get_categories() {
        return array('general');
    }

    public function get_keywords() {
        return array('mj', 'events', 'calendar', 'agenda', 'planning');
    }

    protected function register_controls() {
        $status_options = method_exists('MjEvents_CRUD', 'get_status_labels') ? MjEvents_CRUD::get_status_labels() : array(
            'actif' => __('Actif', 'mj-member'),
            'brouillon' => __('Brouillon', 'mj-member'),
            'passe' => __('Passé', 'mj-member'),
        );
        $type_options = method_exists('MjEvents_CRUD', 'get_type_labels') ? MjEvents_CRUD::get_type_labels() : array(
            'stage' => __('Stage', 'mj-member'),
            'soiree' => __('Soirée', 'mj-member'),
            'sortie' => __('Sortie', 'mj-member'),
        );

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
                'default' => __('Calendrier des événements', 'mj-member'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'statuses',
            array(
                'label' => __('Statuts à afficher', 'mj-member'),
                'type' => Controls_Manager::SELECT2,
                'multiple' => true,
                'label_block' => true,
                'default' => array('actif'),
                'options' => $status_options,
            )
        );

        $this->add_control(
            'types',
            array(
                'label' => __("Types d'événement", 'mj-member'),
                'type' => Controls_Manager::SELECT2,
                'multiple' => true,
                'label_block' => true,
                'options' => $type_options,
            )
        );

        $this->add_control(
            'months_before',
            array(
                'label' => __('Mois précédents', 'mj-member'),
                'type' => Controls_Manager::NUMBER,
                'min' => 0,
                'max' => 6,
                'step' => 1,
                'default' => 0,
            )
        );

        $this->add_control(
            'months_after',
            array(
                'label' => __('Mois à venir', 'mj-member'),
                'type' => Controls_Manager::NUMBER,
                'min' => 1,
                'max' => 12,
                'step' => 1,
                'default' => 3,
            )
        );

        $this->add_control(
            'highlight_next_event',
            array(
                'label' => __('Mettre en avant le prochain événement', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->add_control(
            'empty_message',
            array(
                'label' => __('Message si aucun événement', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Aucun événement prévu pour cette période.', 'mj-member'),
                'label_block' => true,
            )
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_layout',
            array(
                'label' => __('Apparence', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'nav_background_color',
            array(
                'label' => __('Fond du bandeau', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'global' => array('default' => Global_Colors::COLOR_PRIMARY),
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-events-calendar' => '--mj-events-calendar-nav-bg: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'nav_text_color',
            array(
                'label' => __('Texte du bandeau', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'global' => array('default' => Global_Colors::COLOR_TEXT),
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-events-calendar' => '--mj-events-calendar-nav-text: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'accent_color',
            array(
                'label' => __('Couleur d’accent', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'global' => array('default' => Global_Colors::COLOR_ACCENT),
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-events-calendar' => '--mj-events-calendar-accent: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'surface_color',
            array(
                'label' => __('Fond des cartes', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'global' => array('default' => Global_Colors::COLOR_SECONDARY),
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-events-calendar' => '--mj-events-calendar-surface: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'border_color',
            array(
                'label' => __('Couleur des bordures', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'global' => array('default' => Global_Colors::COLOR_TEXT),
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-events-calendar' => '--mj-events-calendar-border: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'event_background_color',
            array(
                'label' => __('Fond des événements', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'global' => array('default' => Global_Colors::COLOR_SECONDARY),
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-events-calendar' => '--mj-events-calendar-event-bg: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'event_text_color',
            array(
                'label' => __('Texte des événements', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'global' => array('default' => Global_Colors::COLOR_TEXT),
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-events-calendar' => '--mj-events-calendar-event-text: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'event_time_color',
            array(
                'label' => __('Couleur de l’horaire', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'global' => array('default' => Global_Colors::COLOR_TEXT),
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-events-calendar' => '--mj-events-calendar-event-time: {{VALUE}};',
                ),
            )
        );

        $this->add_responsive_control(
            'calendar_radius',
            array(
                'label' => __('Arrondi global', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'range' => array(
                    'px' => array('min' => 0, 'max' => 40),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-events-calendar' => '--mj-events-calendar-radius: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_typography',
            array(
                'label' => __('Typographie', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            array(
                'name' => 'calendar_title_typography',
                'label' => __('Titre du widget', 'mj-member'),
                'global' => array('default' => Global_Typography::TYPOGRAPHY_PRIMARY),
                'selector' => '{{WRAPPER}} .mj-member-events-calendar__title',
            )
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            array(
                'name' => 'calendar_heading_typography',
                'label' => __('Titre du mois', 'mj-member'),
                'global' => array('default' => Global_Typography::TYPOGRAPHY_PRIMARY),
                'selector' => '{{WRAPPER}} .mj-member-events-calendar__month-heading',
            )
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            array(
                'name' => 'calendar_day_typography',
                'label' => __('Numéro du jour', 'mj-member'),
                'global' => array('default' => Global_Typography::TYPOGRAPHY_TEXT),
                'selector' => '{{WRAPPER}} .mj-member-events-calendar__day-number',
            )
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            array(
                'name' => 'calendar_event_typography',
                'label' => __('Intitulé de l’événement', 'mj-member'),
                'global' => array('default' => Global_Typography::TYPOGRAPHY_TEXT),
                'selector' => '{{WRAPPER}} .mj-member-events-calendar__event-title',
            )
        );

        $this->end_controls_section();
    }

    protected function render() {
        if (!function_exists('mj_member_get_public_events')) {
            echo '<div class="mj-member-events-calendar__warning">' . esc_html__('Le module MJ Member doit être actif pour utiliser ce widget.', 'mj-member') . '</div>';
            return;
        }

        $settings = $this->get_settings_for_display();

        $statuses = array();
        if (!empty($settings['statuses']) && is_array($settings['statuses'])) {
            foreach ($settings['statuses'] as $status_candidate) {
                $status_candidate = sanitize_key($status_candidate);
                if ($status_candidate === '') {
                    continue;
                }
                $statuses[] = $status_candidate;
            }
        }

        $types = array();
        if (!empty($settings['types']) && is_array($settings['types'])) {
            foreach ($settings['types'] as $type_candidate) {
                $type_candidate = sanitize_key($type_candidate);
                if ($type_candidate === '') {
                    continue;
                }
                $types[] = $type_candidate;
            }
        }

        $months_before = isset($settings['months_before']) ? (int) $settings['months_before'] : 0;
        if ($months_before < 0) {
            $months_before = 0;
        }

        $months_after = isset($settings['months_after']) ? (int) $settings['months_after'] : 3;
        if ($months_after < 1) {
            $months_after = 1;
        }

        $total_months = $months_before + $months_after + 1;

        $highlight_next = isset($settings['highlight_next_event']) ? $settings['highlight_next_event'] === 'yes' : true;
        $empty_message = isset($settings['empty_message']) ? sanitize_text_field($settings['empty_message']) : '';

        $timezone = wp_timezone();
        $current_month = new \DateTimeImmutable('first day of this month', $timezone);
        $first_month = $current_month->modify('-' . $months_before . ' month');
        $last_month = $current_month->modify('+' . $months_after . ' month');

        $range_start = $first_month->getTimestamp();
        $range_end = $last_month->modify('last day of this month')->setTime(23, 59, 59)->getTimestamp();

        $query_limit = max(20, min(200, $total_months * 20));
        $events = mj_member_get_public_events(
            array(
                'statuses' => $statuses,
                'types' => $types,
                'limit' => $query_limit,
                'order' => 'ASC',
                'orderby' => 'date_debut',
                'include_past' => true,
            )
        );

        $months = array();
        for ($i = 0; $i < $total_months; $i++) {
            $month_point = $first_month->modify('+' . $i . ' month');
            $month_key = $month_point->format('Y-m');
            $months[$month_key] = array(
                'timestamp' => $month_point->getTimestamp(),
                'label' => wp_date('F Y', $month_point->getTimestamp(), $timezone),
                'days' => array(),
                'has_next_event' => false,
            );
        }

        $type_labels_map = method_exists('MjEvents_CRUD', 'get_type_labels') ? MjEvents_CRUD::get_type_labels() : array();

        $now_ts = current_time('timestamp');
        $next_event_pointer = null;
        $modal_events = array();

        foreach ($events as $event) {
            $start_ts = !empty($event['start_date']) ? strtotime($event['start_date']) : false;
            $end_ts = !empty($event['end_date']) ? strtotime($event['end_date']) : false;

            if (!$start_ts && !$end_ts) {
                continue;
            }

            if (!$start_ts) {
                $start_ts = $end_ts;
            }
            if (!$end_ts) {
                $end_ts = $start_ts;
            }

            if ($end_ts < $range_start || $start_ts > $range_end) {
                continue;
            }

            $month_key = wp_date('Y-m', $start_ts, $timezone);
            if (!isset($months[$month_key])) {
                continue;
            }

            $event_id = isset($event['id']) ? (int) $event['id'] : 0;
            if ($event_id <= 0) {
                continue;
            }

            $day_key = wp_date('Y-m-d', $start_ts, $timezone);
            if (!isset($months[$month_key]['days'][$day_key])) {
                $months[$month_key]['days'][$day_key] = array();
            }

            $title = isset($event['title']) ? sanitize_text_field($event['title']) : '';
            $permalink = !empty($event['permalink']) ? esc_url($event['permalink']) : '';
            $time_label = $start_ts ? wp_date(get_option('time_format', 'H:i'), $start_ts) : '';
            $cover_modal = !empty($event['cover_url']) ? esc_url($event['cover_url']) : '';
            $cover_thumb = !empty($event['cover_thumb']) ? esc_url($event['cover_thumb']) : $cover_modal;
            $location_label = isset($event['location']) ? sanitize_text_field($event['location']) : '';
            $address_label = isset($event['location_address']) ? sanitize_text_field($event['location_address']) : '';
            $location_notes = isset($event['location_description']) ? sanitize_textarea_field($event['location_description']) : '';
            $map_embed = !empty($event['location_map']) ? esc_url($event['location_map']) : '';
            $map_link = !empty($event['location_map_link']) ? esc_url($event['location_map_link']) : '';

            $type_key = isset($event['type']) ? sanitize_key($event['type']) : '';
            $type_label = isset($type_labels_map[$type_key]) ? $type_labels_map[$type_key] : ($type_key !== '' ? ucfirst($type_key) : '');

            $months[$month_key]['days'][$day_key][] = array(
                'id' => $event_id,
                'title' => $title,
                'time' => $time_label,
                'cover' => $cover_thumb,
                'type_label' => $type_label,
                'start_ts' => $start_ts,
            );

            if (!isset($modal_events[$event_id])) {
                $date_label = function_exists('mj_member_format_event_datetime_range')
                    ? wp_strip_all_tags(mj_member_format_event_datetime_range($event['start_date'], $event['end_date']))
                    : wp_date(get_option('date_format', 'd/m/Y H:i'), $start_ts, $timezone);

                $price_label = '';
                if (isset($event['price']) && (float) $event['price'] > 0) {
                    $price_label = sprintf(__('Tarif : %s €', 'mj-member'), number_format_i18n((float) $event['price'], 2));
                }

                $description_html = '';
                if (!empty($event['description'])) {
                    $description_html = wpautop(wp_kses_post($event['description']));
                }

                $modal_events[$event_id] = array(
                    'id' => $event_id,
                    'title' => $title,
                    'date' => $date_label,
                    'time' => $time_label,
                    'permalink' => $permalink,
                    'cover' => $cover_modal,
                    'location' => $location_label,
                    'address' => $address_label,
                    'location_description' => $location_notes,
                    'map' => $map_embed,
                    'map_link' => $map_link,
                    'description' => $description_html,
                    'price_label' => $price_label,
                    'type_label' => $type_label,
                );
            }

            if ($highlight_next && $start_ts >= $now_ts) {
                if ($next_event_pointer === null || $start_ts < $next_event_pointer['start_ts']) {
                    $next_event_pointer = array(
                        'month_key' => $month_key,
                        'day_key' => $day_key,
                        'event_id' => $event_id,
                        'start_ts' => $start_ts,
                    );
                }
            }
        }

        if ($next_event_pointer !== null && isset($months[$next_event_pointer['month_key']])) {
            $months[$next_event_pointer['month_key']]['has_next_event'] = true;
        }

        $instance_id = wp_unique_id('mj-member-events-calendar-');
        $event_payload_json = !empty($modal_events) ? wp_json_encode(array_values($modal_events)) : '[]';
        if (!is_string($event_payload_json)) {
            $event_payload_json = '[]';
        }

        static $styles_printed = false;
        if (!$styles_printed) {
            $styles_printed = true;
            echo '<style>'
                . '.mj-member-events-calendar{display:flex;flex-direction:column;gap:20px;--mj-events-calendar-nav-bg:#0f172a;--mj-events-calendar-nav-text:#ffffff;--mj-events-calendar-accent:#2563eb;--mj-events-calendar-surface:#ffffff;--mj-events-calendar-border:#e2e8f0;--mj-events-calendar-event-bg:#f8fafc;--mj-events-calendar-event-text:#1f2937;--mj-events-calendar-event-time:#475569;--mj-events-calendar-modal-bg:rgba(15,23,42,0.72);--mj-events-calendar-radius:14px;}'
                . '.mj-member-events-calendar__title{margin:0;font-size:1.6rem;font-weight:700;color:var(--mj-events-calendar-nav-bg);}'
                . '.mj-member-events-calendar__nav{display:flex;align-items:center;justify-content:space-between;gap:12px;background:var(--mj-events-calendar-nav-bg);border-radius:calc(var(--mj-events-calendar-radius));padding:12px 16px;color:var(--mj-events-calendar-nav-text);}'
                . '.mj-member-events-calendar__nav-button{background:rgba(255,255,255,0.12);border:none;border-radius:999px;color:var(--mj-events-calendar-nav-text);padding:8px 16px;font-weight:600;cursor:pointer;transition:background 0.2s ease,transform 0.2s ease;}'
                . '.mj-member-events-calendar__nav-button:hover{background:rgba(255,255,255,0.26);transform:translateY(-1px);}'
                . '.mj-member-events-calendar__nav-button:disabled{opacity:0.45;cursor:not-allowed;transform:none;}'
                . '.mj-member-events-calendar__month-label{font-size:1rem;font-weight:600;}'
                . '.mj-member-events-calendar__months{display:flex;flex-direction:column;gap:24px;}'
                . '.mj-member-events-calendar__month{display:none;flex-direction:column;gap:16px;}'
                . '.mj-member-events-calendar__month.is-active{display:flex;}'
                . '.mj-member-events-calendar__month.is-next-event .mj-member-events-calendar__month-heading{color:var(--mj-events-calendar-accent);}'
                . '.mj-member-events-calendar__month-heading{margin:0;font-size:1.2rem;font-weight:700;color:var(--mj-events-calendar-nav-bg);}'
                . '.mj-member-events-calendar__table-wrapper{overflow:auto;border-radius:calc(var(--mj-events-calendar-radius));box-shadow:0 10px 26px rgba(15,23,42,0.1);}'
                . '.mj-member-events-calendar__table{width:100%;min-width:640px;border-collapse:collapse;background:var(--mj-events-calendar-surface);border-radius:calc(var(--mj-events-calendar-radius));overflow:hidden;}'
                . '.mj-member-events-calendar__table thead{background:#f1f5f9;color:var(--mj-events-calendar-nav-bg);font-weight:600;text-transform:uppercase;font-size:0.75rem;letter-spacing:0.06em;}'
                . '.mj-member-events-calendar__table th,.mj-member-events-calendar__table td{width:14.28%;padding:12px;border:1px solid var(--mj-events-calendar-border);vertical-align:top;}'
                . '.mj-member-events-calendar__day{display:flex;flex-direction:column;gap:8px;min-height:110px;padding:6px;border-radius:12px;background:transparent;transition:background 0.2s ease;}'
                . '.mj-member-events-calendar__day.has-events{background:rgba(37,99,235,0.05);}'
                . '.mj-member-events-calendar__day-number{font-size:0.95rem;font-weight:700;color:var(--mj-events-calendar-nav-bg);}'
                . '.mj-member-events-calendar__day.is-today{background:rgba(37,99,235,0.08);}'
                . '.mj-member-events-calendar__events{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:8px;}'
                . '.mj-member-events-calendar__event{background:var(--mj-events-calendar-event-bg);border-radius:12px;padding:6px 8px;display:flex;flex-direction:column;gap:6px;border:1px solid transparent;}'
                . '.mj-member-events-calendar__event.is-next{border-color:var(--mj-events-calendar-accent);background:rgba(37,99,235,0.12);}'
                . '.mj-member-events-calendar__event-trigger{display:flex;align-items:center;gap:10px;background:none;border:none;padding:0;margin:0;text-align:left;width:100%;cursor:pointer;color:var(--mj-events-calendar-event-text);}'
                . '.mj-member-events-calendar__event-thumb{width:44px;height:44px;border-radius:10px;overflow:hidden;flex-shrink:0;background:#e2e8f0;}'
                . '.mj-member-events-calendar__event-thumb img{display:block;width:100%;height:100%;object-fit:cover;}'
                . '.mj-member-events-calendar__event-copy{display:flex;flex-direction:column;gap:2px;flex:1;min-width:0;}'
                . '.mj-member-events-calendar__event-title{font-size:0.9rem;font-weight:600;color:var(--mj-events-calendar-event-text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}'
                . '.mj-member-events-calendar__event-meta{display:flex;align-items:center;gap:6px;font-size:0.75rem;color:var(--mj-events-calendar-event-time);}'
                . '.mj-member-events-calendar__event-type{display:inline-flex;align-items:center;gap:4px;font-size:0.7rem;font-weight:600;padding:2px 8px;border-radius:999px;background:rgba(37,99,235,0.18);color:var(--mj-events-calendar-accent);text-transform:uppercase;letter-spacing:0.05em;}'
                . '.mj-member-events-calendar__empty{margin:0;font-size:0.95rem;color:#475569;}'
                . '.mj-member-events-calendar__modal{position:fixed;inset:0;display:none;align-items:center;justify-content:center;z-index:9999;}'
                . '.mj-member-events-calendar__modal.is-open{display:flex;}'
                . '.mj-member-events-calendar__modal-backdrop{position:absolute;inset:0;background:var(--mj-events-calendar-modal-bg);}'
                . '.mj-member-events-calendar__modal-dialog{position:relative;z-index:2;background:#ffffff;border-radius:18px;max-width:720px;width:90%;box-shadow:0 24px 60px rgba(15,23,42,0.3);overflow:hidden;display:flex;flex-direction:column;}'
                . '.mj-member-events-calendar__modal-close{position:absolute;top:12px;right:12px;border:none;background:rgba(15,23,42,0.08);color:#0f172a;border-radius:999px;width:36px;height:36px;font-size:1.2rem;font-weight:700;cursor:pointer;}'
                . '.mj-member-events-calendar__modal-body{display:flex;flex-direction:column;}'
                . '.mj-member-events-calendar__modal-cover{width:100%;max-height:260px;overflow:hidden;background:#f1f5f9;display:none;}'
                . '.mj-member-events-calendar__modal-cover img{display:block;width:100%;height:100%;object-fit:cover;}'
                . '.mj-member-events-calendar__modal-content{display:flex;flex-direction:column;gap:12px;padding:24px;}'
                . '.mj-member-events-calendar__modal-date{margin:0;font-size:0.95rem;color:#475569;}'
                . '.mj-member-events-calendar__modal-meta{margin:0;font-size:0.9rem;color:#475569;}'
                . '.mj-member-events-calendar__modal-price{margin:0;font-size:0.95rem;font-weight:600;color:var(--mj-events-calendar-accent);display:none;}'
                . '.mj-member-events-calendar__modal-description{font-size:0.95rem;color:#1f2937;line-height:1.6;}'
                . '.mj-member-events-calendar__modal-description p{margin:0 0 10px;}'
                . '.mj-member-events-calendar__modal-notes{margin:0;font-size:0.9rem;color:#334155;font-style:italic;display:none;}'
                . '.mj-member-events-calendar__modal-map{width:100%;height:240px;border-radius:16px;overflow:hidden;display:none;}'
                . '.mj-member-events-calendar__modal-map iframe{width:100%;height:100%;border:0;}'
                . '.mj-member-events-calendar__modal-map-link{display:none;font-size:0.85rem;font-weight:600;color:var(--mj-events-calendar-accent);text-decoration:none;}'
                . '.mj-member-events-calendar__modal-map-link:hover{text-decoration:underline;}'
                . '.mj-member-events-calendar__modal-actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:4px;}'
                . '.mj-member-events-calendar__modal-cta{display:inline-flex;align-items:center;justify-content:center;background:var(--mj-events-calendar-accent);color:#ffffff;font-weight:600;border-radius:12px;padding:10px 18px;text-decoration:none;}'
                . '.mj-member-events-calendar__modal-cta:hover{opacity:0.9;}'
                . '.mj-member-events-calendar__modal-secondary{background:none;border:1px solid var(--mj-events-calendar-border);color:var(--mj-events-calendar-event-text);border-radius:12px;padding:8px 16px;font-weight:600;cursor:pointer;}'
                . '.mj-member-events-calendar.has-open-calendar-modal{pointer-events:none;}'
                . '.mj-member-events-calendar.has-open-calendar-modal .mj-member-events-calendar__modal{pointer-events:auto;}'
                . '@media (max-width:782px){.mj-member-events-calendar__nav{flex-direction:column;align-items:flex-start;}.mj-member-events-calendar__table{min-width:520px;}.mj-member-events-calendar__modal-dialog{width:94%;}.mj-member-events-calendar__modal-content{padding:20px;}}'
                . '</style>';
        }

        $preferred_index = -1;
        $month_keys = array_keys($months);
        $has_multiple_months = count($month_keys) > 1;

        echo '<div class="mj-member-events-calendar" id="' . esc_attr($instance_id) . '" data-calendar-events="' . esc_attr($event_payload_json) . '" data-calendar-preferred="0">';

        if (!empty($settings['title'])) {
            echo '<h3 class="mj-member-events-calendar__title">' . esc_html($settings['title']) . '</h3>';
        }

        if ($has_multiple_months) {
            echo '<div class="mj-member-events-calendar__nav">';
            echo '<button type="button" class="mj-member-events-calendar__nav-button" data-calendar-nav="prev" aria-label="' . esc_attr__('Mois précédent', 'mj-member') . '">&larr;</button>';
            echo '<span class="mj-member-events-calendar__month-label" data-calendar-active-label></span>';
            echo '<button type="button" class="mj-member-events-calendar__nav-button" data-calendar-nav="next" aria-label="' . esc_attr__('Mois suivant', 'mj-member') . '">&rarr;</button>';
            echo '</div>';
        }

        echo '<div class="mj-member-events-calendar__months">';

        $total_events = 0;
        $month_index = 0;
        foreach ($months as $month_key => $month_data) {
            $month_classes = array('mj-member-events-calendar__month');
            if ($month_data['has_next_event']) {
                $month_classes[] = 'is-next-event';
                if ($preferred_index === -1) {
                    $preferred_index = $month_index;
                }
            }
            if ($month_index === 0) {
                $month_classes[] = 'is-active';
            }

            $first_day_week_index = (int) wp_date('N', $month_data['timestamp'], $timezone);
            $days_in_month = (int) wp_date('t', $month_data['timestamp'], $timezone);
            $cell_count = (int) ceil(($first_day_week_index - 1 + $days_in_month) / 7) * 7;

            echo '<section class="' . esc_attr(implode(' ', $month_classes)) . '" data-calendar-month="' . esc_attr($month_key) . '" data-calendar-label="' . esc_attr($month_data['label']) . '">';
            echo '<h4 class="mj-member-events-calendar__month-heading">' . esc_html($month_data['label']) . '</h4>';
            echo '<div class="mj-member-events-calendar__table-wrapper">';
            echo '<table class="mj-member-events-calendar__table">';
            echo '<thead><tr>';
            $week_days = array(
                __('Lun', 'mj-member'),
                __('Mar', 'mj-member'),
                __('Mer', 'mj-member'),
                __('Jeu', 'mj-member'),
                __('Ven', 'mj-member'),
                __('Sam', 'mj-member'),
                __('Dim', 'mj-member'),
            );
            foreach ($week_days as $day_label) {
                echo '<th scope="col">' . esc_html($day_label) . '</th>';
            }
            echo '</tr></thead>';
            echo '<tbody><tr>';

            $day_number = 1;
            $current_row_cells = 0;
            $month_prefix = wp_date('Y-m', $month_data['timestamp'], $timezone);

            for ($cell = 0; $cell < $cell_count; $cell++) {
                $is_padding = $cell < ($first_day_week_index - 1) || $day_number > $days_in_month;
                if ($is_padding) {
                    echo '<td></td>';
                } else {
                    $day_key = $month_prefix . '-' . str_pad((string) $day_number, 2, '0', STR_PAD_LEFT);
                    $events_for_day = isset($month_data['days'][$day_key]) ? $month_data['days'][$day_key] : array();
                    if (!empty($events_for_day)) {
                        usort(
                            $events_for_day,
                            static function ($a, $b) {
                                return $a['start_ts'] <=> $b['start_ts'];
                            }
                        );
                    }

                    $total_events += count($events_for_day);
                    $day_classes = array('mj-member-events-calendar__day');
                    if (!empty($events_for_day)) {
                        $day_classes[] = 'has-events';
                    }
                    $today_key = wp_date('Y-m-d', $now_ts, $timezone);
                    if ($day_key === $today_key) {
                        $day_classes[] = 'is-today';
                    }
                    if ($next_event_pointer && $next_event_pointer['day_key'] === $day_key && $month_data['has_next_event']) {
                        $day_classes[] = 'is-next';
                    }

                    echo '<td>';
                    echo '<div class="' . esc_attr(implode(' ', $day_classes)) . '">';
                    echo '<span class="mj-member-events-calendar__day-number">' . esc_html($day_number) . '</span>';

                    if (!empty($events_for_day)) {
                        echo '<ul class="mj-member-events-calendar__events">';
                        foreach ($events_for_day as $event_entry) {
                            $event_classes = array('mj-member-events-calendar__event');
                            if ($next_event_pointer && $next_event_pointer['event_id'] === $event_entry['id']) {
                                $event_classes[] = 'is-next';
                            }

                            echo '<li class="' . esc_attr(implode(' ', $event_classes)) . '">';
                            echo '<button type="button" class="mj-member-events-calendar__event-trigger" data-calendar-event-trigger="' . esc_attr((string) $event_entry['id']) . '">';
                            if (!empty($event_entry['cover'])) {
                                echo '<span class="mj-member-events-calendar__event-thumb"><img src="' . esc_url($event_entry['cover']) . '" alt="' . esc_attr($event_entry['title']) . '" loading="lazy" /></span>';
                            }
                            echo '<span class="mj-member-events-calendar__event-copy">';
                            if (!empty($event_entry['type_label'])) {
                                echo '<span class="mj-member-events-calendar__event-type">' . esc_html($event_entry['type_label']) . '</span>';
                            }
                            echo '<span class="mj-member-events-calendar__event-title">' . esc_html($event_entry['title']) . '</span>';
                            if (!empty($event_entry['time'])) {
                                echo '<span class="mj-member-events-calendar__event-meta">' . esc_html($event_entry['time']) . '</span>';
                            }
                            echo '</span>';
                            echo '</button>';
                            echo '</li>';
                        }
                        echo '</ul>';
                    }

                    echo '</div>';
                    echo '</td>';

                    $day_number++;
                }

                $current_row_cells++;
                if ($current_row_cells === 7 && $cell !== $cell_count - 1) {
                    echo '</tr><tr>';
                    $current_row_cells = 0;
                }
            }

            echo '</tr></tbody>';
            echo '</table>';
            echo '</div>';
            echo '</section>';

            $month_index++;
        }

        echo '</div>';

        if ($total_events === 0 && $empty_message !== '') {
            echo '<p class="mj-member-events-calendar__empty">' . esc_html($empty_message) . '</p>';
        }

        echo '<div class="mj-member-events-calendar__modal" data-calendar-modal hidden>';
        echo '<div class="mj-member-events-calendar__modal-backdrop" data-calendar-dismiss></div>';
        echo '<div class="mj-member-events-calendar__modal-dialog" role="dialog" aria-modal="true" aria-labelledby="' . esc_attr($instance_id) . '-modal-title">';
        echo '<button type="button" class="mj-member-events-calendar__modal-close" data-calendar-dismiss aria-label="' . esc_attr__('Fermer', 'mj-member') . '">&times;</button>';
        echo '<div class="mj-member-events-calendar__modal-body">';
        echo '<div class="mj-member-events-calendar__modal-cover" data-calendar-modal-cover></div>';
        echo '<div class="mj-member-events-calendar__modal-content">';
        echo '<h4 id="' . esc_attr($instance_id) . '-modal-title" data-calendar-modal-title></h4>';
        echo '<p class="mj-member-events-calendar__modal-date" data-calendar-modal-date></p>';
        echo '<p class="mj-member-events-calendar__modal-meta" data-calendar-modal-meta></p>';
        echo '<p class="mj-member-events-calendar__modal-price" data-calendar-modal-price></p>';
        echo '<div class="mj-member-events-calendar__modal-description" data-calendar-modal-description></div>';
        echo '<p class="mj-member-events-calendar__modal-notes" data-calendar-modal-notes></p>';
        echo '<div class="mj-member-events-calendar__modal-map" data-calendar-modal-map></div>';
        echo '<a class="mj-member-events-calendar__modal-map-link" data-calendar-modal-map-link target="_blank" rel="noopener">' . esc_html__('Ouvrir dans Google Maps', 'mj-member') . '</a>';
        echo '<div class="mj-member-events-calendar__modal-actions">';
        echo '<a class="mj-member-events-calendar__modal-cta" data-calendar-modal-link target="_blank" rel="noopener">' . esc_html__('S\'inscrire', 'mj-member') . '</a>';
        echo '<button type="button" class="mj-member-events-calendar__modal-secondary" data-calendar-dismiss>' . esc_html__('Fermer', 'mj-member') . '</button>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';

        echo '</div>';

        $preferred_index = ($preferred_index >= 0) ? $preferred_index : 0;

        $instance_config = array(
            'preferredIndex' => $preferred_index,
            'translations' => array(
                'noDescription' => __('Description disponible prochainement.', 'mj-member'),
            ),
        );

        static $script_printed = false;
        if (!$script_printed) {
            $script_printed = true;
            $script = <<<'JS'
(function(){
    function ready(fn){
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    function parseEvents(root){
        var store = {};
        var raw = root.getAttribute('data-calendar-events');
        if (!raw) {
            return store;
        }
        try {
            var list = JSON.parse(raw);
            if (Array.isArray(list)) {
                list.forEach(function(item){
                    if (!item || typeof item.id === 'undefined') {
                        return;
                    }
                    store[String(item.id)] = item;
                });
            }
        } catch (error) {
        }
        return store;
    }

    function initCalendar(root, config){
        if (!root) {
            return;
        }

        var months = Array.prototype.slice.call(root.querySelectorAll('[data-calendar-month]'));
        if (!months.length) {
            return;
        }

        var prev = root.querySelector('[data-calendar-nav="prev"]');
        var next = root.querySelector('[data-calendar-nav="next"]');
        var label = root.querySelector('[data-calendar-active-label]');
        var activeIndex = 0;
        var preferred = parseInt(root.getAttribute('data-calendar-preferred'), 10);
        if (!isNaN(preferred) && preferred >= 0 && preferred < months.length) {
            activeIndex = preferred;
        } else if (config && typeof config.preferredIndex === 'number' && config.preferredIndex >= 0 && config.preferredIndex < months.length) {
            activeIndex = config.preferredIndex;
        }

        function sync(){
            months.forEach(function(month, idx){
                if (idx === activeIndex) {
                    month.classList.add('is-active');
                } else {
                    month.classList.remove('is-active');
                }
            });
            if (label && months[activeIndex]) {
                label.textContent = months[activeIndex].getAttribute('data-calendar-label') || '';
            }
            if (prev) {
                prev.disabled = activeIndex === 0;
            }
            if (next) {
                next.disabled = activeIndex === months.length - 1;
            }
        }

        if (prev) {
            prev.addEventListener('click', function(){
                if (activeIndex > 0) {
                    activeIndex -= 1;
                    sync();
                }
            });
        }

        if (next) {
            next.addEventListener('click', function(){
                if (activeIndex < months.length - 1) {
                    activeIndex += 1;
                    sync();
                }
            });
        }

        sync();

        var eventsMap = parseEvents(root);
        var modal = root.querySelector('[data-calendar-modal]');
        if (!modal || !Object.keys(eventsMap).length) {
            return;
        }

        var translations = config && config.translations ? config.translations : {};
        var fallbackDescription = translations.noDescription || '';

        var closeControls = Array.prototype.slice.call(modal.querySelectorAll('[data-calendar-dismiss]'));
        var modalTitle = modal.querySelector('[data-calendar-modal-title]');
        var modalDate = modal.querySelector('[data-calendar-modal-date]');
        var modalMeta = modal.querySelector('[data-calendar-modal-meta]');
        var modalDescription = modal.querySelector('[data-calendar-modal-description]');
        var modalNotes = modal.querySelector('[data-calendar-modal-notes]');
        var modalCover = modal.querySelector('[data-calendar-modal-cover]');
        var modalMap = modal.querySelector('[data-calendar-modal-map]');
        var modalMapLink = modal.querySelector('[data-calendar-modal-map-link]');
        var modalPrice = modal.querySelector('[data-calendar-modal-price]');
        var modalLink = modal.querySelector('[data-calendar-modal-link]');
        var lastTrigger = null;

        function clearNode(node){
            if (node) {
                node.innerHTML = '';
            }
        }

        function closeModal(){
            modal.classList.remove('is-open');
            modal.setAttribute('hidden', 'hidden');
            root.classList.remove('has-open-calendar-modal');
            if (lastTrigger) {
                lastTrigger.focus();
                lastTrigger = null;
            }
        }

        closeControls.forEach(function(control){
            control.addEventListener('click', function(){
                closeModal();
            });
        });

        modal.addEventListener('click', function(evt){
            if (evt.target === modal) {
                closeModal();
            }
        });

        document.addEventListener('keydown', function(evt){
            if (evt.key === 'Escape' && modal.classList.contains('is-open')) {
                closeModal();
            }
        });

        root.addEventListener('click', function(evt){
            var trigger = evt.target.closest('[data-calendar-event-trigger]');
            if (!trigger) {
                return;
            }
            evt.preventDefault();
            var eventId = trigger.getAttribute('data-calendar-event-trigger');
            if (!eventId || !eventsMap[eventId]) {
                return;
            }

            var payload = eventsMap[eventId];
            lastTrigger = trigger;

            if (modalTitle) {
                modalTitle.textContent = payload.title || '';
            }
            if (modalDate) {
                modalDate.textContent = payload.date || '';
            }
            if (modalMeta) {
                var metaParts = [];
                if (payload.type_label) {
                    metaParts.push(payload.type_label);
                }
                if (payload.location) {
                    metaParts.push(payload.location);
                }
                if (payload.address) {
                    metaParts.push(payload.address);
                }
                modalMeta.textContent = metaParts.join(' · ');
            }
            if (modalPrice) {
                if (payload.price_label) {
                    modalPrice.textContent = payload.price_label;
                    modalPrice.style.display = 'block';
                } else {
                    modalPrice.textContent = '';
                    modalPrice.style.display = 'none';
                }
            }
            if (modalDescription) {
                if (payload.description) {
                    modalDescription.innerHTML = payload.description;
                } else if (fallbackDescription) {
                    modalDescription.textContent = fallbackDescription;
                } else {
                    modalDescription.textContent = '';
                }
            }
            if (modalNotes) {
                if (payload.location_description) {
                    modalNotes.textContent = payload.location_description;
                    modalNotes.style.display = 'block';
                } else {
                    modalNotes.textContent = '';
                    modalNotes.style.display = 'none';
                }
            }

            clearNode(modalCover);
            if (modalCover && payload.cover) {
                var img = document.createElement('img');
                img.src = payload.cover;
                img.alt = payload.title || '';
                img.loading = 'lazy';
                modalCover.appendChild(img);
                modalCover.style.display = 'block';
            } else if (modalCover) {
                modalCover.style.display = 'none';
            }

            clearNode(modalMap);
            if (modalMap) {
                if (payload.map && (payload.map.indexOf('https://') === 0 || payload.map.indexOf('http://') === 0)) {
                    var iframe = document.createElement('iframe');
                    iframe.src = payload.map;
                    iframe.loading = 'lazy';
                    iframe.allowFullscreen = true;
                    iframe.referrerPolicy = 'no-referrer-when-downgrade';
                    modalMap.appendChild(iframe);
                    modalMap.style.display = 'block';
                } else {
                    modalMap.style.display = 'none';
                }
            }

            if (modalMapLink) {
                if (payload.map_link) {
                    modalMapLink.href = payload.map_link;
                    modalMapLink.style.display = 'inline-flex';
                } else {
                    modalMapLink.removeAttribute('href');
                    modalMapLink.style.display = 'none';
                }
            }

            if (modalLink) {
                if (payload.permalink) {
                    modalLink.href = payload.permalink;
                    modalLink.removeAttribute('hidden');
                } else {
                    modalLink.href = '#';
                    modalLink.setAttribute('hidden', 'hidden');
                }
            }

            modal.removeAttribute('hidden');
            modal.classList.add('is-open');
            root.classList.add('has-open-calendar-modal');

            var focusTarget = modal.querySelector('.mj-member-events-calendar__modal-close');
            if (focusTarget) {
                focusTarget.focus();
            }
        });
    }

    function drainQueue(){
        if (!window.mjMemberEventsCalendarQueue) {
            return;
        }
        while (window.mjMemberEventsCalendarQueue.length) {
            var item = window.mjMemberEventsCalendarQueue.shift();
            if (!item || !item.id) {
                continue;
            }
            var root = document.getElementById(item.id);
            if (root && item.config && typeof item.config.preferredIndex === 'number') {
                root.setAttribute('data-calendar-preferred', String(item.config.preferredIndex));
            }
            initCalendar(root, item.config || {});
        }
    }

    ready(drainQueue);
})();
JS;
            echo '<script>' . $script . '</script>';
        }

        echo '<script>window.mjMemberEventsCalendarQueue = window.mjMemberEventsCalendarQueue || [];window.mjMemberEventsCalendarQueue.push({id:' . wp_json_encode($instance_id) . ',config:' . wp_json_encode($instance_config) . '});</script>';
    }
}
