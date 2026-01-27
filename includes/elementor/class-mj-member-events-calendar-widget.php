<?php

if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Controls_Manager;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;
use Mj\Member\Core\AssetsManager;
use Mj\Member\Core\Config;
use Mj\Member\Classes\Crud\MjEventAnimateurs;
use Mj\Member\Classes\MjEventSchedule;
use Mj\Member\Classes\MjRoles;
use Mj\Member\Classes\View\Schedule\ScheduleDisplayHelper;

class Mj_Member_Elementor_Events_Calendar_Widget extends Widget_Base {
    use Mj_Member_Elementor_Widget_Visibility;

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
        return array('mj-member');
    }

    public function get_keywords() {
        return array('mj', 'events', 'calendar', 'agenda', 'planning');
    }

    public function get_style_depends() {
        return array('mj-member-events-calendar');
    }

    public function get_script_depends() {
        return array('mj-member-events-calendar');
    }

    protected function register_controls() {
        $status_options = method_exists('MjEvents', 'get_status_labels') ? MjEvents::get_status_labels() : array(
            'actif' => __('Actif', 'mj-member'),
            'brouillon' => __('Brouillon', 'mj-member'),
            'passe' => __('Passé', 'mj-member'),
        );
        $type_options = method_exists('MjEvents', 'get_type_labels') ? MjEvents::get_type_labels() : array(
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
            'highlight_closure_days',
            array(
                'label' => __('Afficher les jours de fermeture', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->add_control(
            'hide_closure_occurrences',
            array(
                'label' => __('Masquer les événements les jours de fermeture', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
                'description' => __('Supprime les occurrences (notamment récurrentes) quand la MJ est fermée.', 'mj-member'),
            )
        );

        $this->add_control(
            'show_toolbar_left',
            array(
                'label' => __('Afficher la navigation du calendrier', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->add_control(
            'show_toolbar_actions',
            array(
                'label' => __('Afficher les filtres et le bouton "Aujourd\'hui"', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->add_control(
            'current_week_only',
            array(
                'label' => __('Afficher uniquement la semaine courante', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => '',
                'description' => __('Réduit l’affichage au calendrier de la semaine en cours.', 'mj-member'),
            )
        );

        $this->add_control(
            'week_display_mode',
            array(
                'label' => __('Semaine à afficher', 'mj-member'),
                'type' => Controls_Manager::SELECT,
                'default' => 'current',
                'options' => array(
                    'current' => __('Semaine courante', 'mj-member'),
                    'previous' => __('Semaine précédente', 'mj-member'),
                    'next' => __('Semaine prochaine', 'mj-member'),
                    'custom' => __('Semaine spécifique', 'mj-member'),
                ),
                'condition' => array(
                    'current_week_only' => 'yes',
                ),
            )
        );

        $this->add_control(
            'week_custom_reference',
            array(
                'label' => __('Date de référence', 'mj-member'),
                'type' => Controls_Manager::DATE_TIME,
                'picker_options' => array(
                    'enableTime' => false,
                ),
                'condition' => array(
                    'current_week_only' => 'yes',
                    'week_display_mode' => 'custom',
                ),
                'description' => __('Choisissez une date incluse dans la semaine désirée.', 'mj-member'),
            )
        );

        $this->add_control(
            'cover_width_desktop',
            array(
                'label' => __('Largeur image (desktop)', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array(
                    'px' => array('min' => 10, 'max' => 500),
                ),
                'default' => array('size' => 120, 'unit' => 'px'),
                'render_type' => 'ui',
            )
        );

        $this->add_control(
            'cover_width_tablet',
            array(
                'label' => __('Largeur image (tablette)', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array(
                    'px' => array('min' => 10, 'max' => 500),
                ),
                'default' => array('size' => 110, 'unit' => 'px'),
                'render_type' => 'ui',
            )
        );

        $this->add_control(
            'cover_width_mobile',
            array(
                'label' => __('Largeur image (mobile)', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array(
                    'px' => array('min' => 10, 'max' => 500),
                ),
                'default' => array('size' => 90, 'unit' => 'px'),
                'render_type' => 'ui',
            )
        );

        $this->end_controls_section();

        $this->register_visibility_controls();

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
                'name' => 'calendar_month_typography',
                'label' => __('Nom du mois', 'mj-member'),
                'selector' => '{{WRAPPER}} .mj-member-events-calendar__month-heading',
            )
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            array(
                'name' => 'calendar_day_typography',
                'label' => __('Levés de jours', 'mj-member'),
                'selector' => '{{WRAPPER}} .mj-member-events-calendar__day-number',
            )
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            array(
                'name' => 'calendar_event_title_typography',
                'label' => __('Titre d’événement', 'mj-member'),
                'selector' => '{{WRAPPER}} .mj-member-events-calendar__event-title',
            )
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            array(
                'name' => 'calendar_event_meta_typography',
                'label' => __('Métadonnées', 'mj-member'),
                'selector' => '{{WRAPPER}} .mj-member-events-calendar__event-meta',
            )
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $this->apply_visibility_to_wrapper($settings, 'mj-member-events-calendar');

        $status_filter = array();
        if (!empty($settings['statuses']) && is_array($settings['statuses'])) {
            foreach ($settings['statuses'] as $status_candidate) {
                $status_candidate = sanitize_key((string) $status_candidate);
                if ($status_candidate === '') {
                    continue;
                }
                $status_filter[$status_candidate] = $status_candidate;
            }
        }
        if (empty($status_filter)) {
            $status_filter = array(MjEvents::STATUS_ACTIVE);
        } else {
            $status_filter = array_values($status_filter);
        }

        $type_filter = array();
        if (!empty($settings['types']) && is_array($settings['types'])) {
            foreach ($settings['types'] as $type_candidate) {
                $type_candidate = sanitize_key((string) $type_candidate);
                if ($type_candidate === '') {
                    continue;
                }
                $type_filter[$type_candidate] = $type_candidate;
            }
        }
        $type_filter = array_values($type_filter);

        $months_before = isset($settings['months_before']) ? max(0, (int) $settings['months_before']) : 0;
        $months_after = isset($settings['months_after']) ? max(1, (int) $settings['months_after']) : 3;
        $total_months = max(1, $months_before + $months_after + 1);

        $timezone = wp_timezone();
        if (!($timezone instanceof \DateTimeZone)) {
            $timezone = new \DateTimeZone('UTC');
        }

        try {
            $current_month = new \DateTimeImmutable('first day of this month', $timezone);
        } catch (\Exception $exception) {
            $current_month = new \DateTimeImmutable('first day of this month');
        }

        $first_month = $current_month->modify('-' . $months_before . ' months');
        $last_month = $first_month->modify('+' . ($total_months - 1) . ' months');

        $range_start_dt = $first_month->setTime(0, 0, 0);
        $range_end_dt = $last_month->modify('last day of this month')->setTime(23, 59, 59);

        $range_start = $range_start_dt->getTimestamp();
        $range_end = $range_end_dt->getTimestamp();

        $highlight_next = !isset($settings['highlight_next_event']) || $settings['highlight_next_event'] === 'yes';
        $highlight_closure_days = isset($settings['highlight_closure_days']) && $settings['highlight_closure_days'] === 'yes';
        $hide_closure_occurrences = !isset($settings['hide_closure_occurrences']) || $settings['hide_closure_occurrences'] === 'yes';
        $show_toolbar_left = !isset($settings['show_toolbar_left']) || $settings['show_toolbar_left'] === 'yes';
        $show_toolbar_actions = !isset($settings['show_toolbar_actions']) || $settings['show_toolbar_actions'] === 'yes';

        $cover_width_settings = self::normalize_cover_width_settings($settings);

        $empty_message = '';
        if (isset($settings['empty_message']) && is_string($settings['empty_message'])) {
            $empty_message = trim($settings['empty_message']);
        }
        if ($empty_message === '') {
            $empty_message = __('Aucun événement disponible pour le moment.', 'mj-member');
        }

        $events = array();
        if (function_exists('mj_member_get_public_events')) {
            $events = mj_member_get_public_events(
                array(
                    'statuses' => $status_filter,
                    'types' => $type_filter,
                    'limit' => 200,
                    'order' => 'ASC',
                    'orderby' => 'date_debut',
                    'include_past' => true,
                )
            );
        }

        $type_colors_map = method_exists('MjEvents', 'get_type_colors') ? MjEvents::get_type_colors() : array();
        $is_elementor_preview = false;
        if (class_exists('\Elementor\Plugin')) {
            $elementor_plugin = \Elementor\Plugin::instance();
            if (isset($elementor_plugin->editor) && method_exists($elementor_plugin->editor, 'is_edit_mode')) {
                $is_elementor_preview = $elementor_plugin->editor->is_edit_mode();
            }
        }

        if ($is_elementor_preview && empty($events)) {
            $default_event = array(
                'id' => 1,
                'title' => __('Stage de découverte', 'mj-member'),
                'type' => 'stage',
                'status' => 'actif',
                'date_debut' => wp_date('Y-m-d 10:00:00', $range_start + DAY_IN_SECONDS, $timezone),
                'date_fin' => wp_date('Y-m-d 16:00:00', $range_start + DAY_IN_SECONDS, $timezone),
                'schedule_mode' => 'single',
                'schedule_payload' => array(),
                'permalink' => home_url('/evenement/stage-de-decouverte'),
                'accent_color' => isset($type_colors_map['stage']) ? self::normalize_hex_color_value($type_colors_map['stage']) : '',
            );

            $second_event = $default_event;
            $second_event['id'] = 2;
            $second_event['title'] = __('Atelier numérique', 'mj-member');
            $second_event['type'] = 'atelier';
            $second_event['date_debut'] = wp_date('Y-m-d 18:00:00', $range_start + (int) (3 * DAY_IN_SECONDS), $timezone);
            $second_event['date_fin'] = wp_date('Y-m-d 20:00:00', $range_start + (int) (3 * DAY_IN_SECONDS), $timezone);
            $second_event['accent_color'] = isset($type_colors_map['atelier']) ? self::normalize_hex_color_value($type_colors_map['atelier']) : '';
            $second_event['permalink'] = home_url('/evenement/atelier-numerique');

            $events = array($default_event, $second_event);
        }

        $now_ts = current_time('timestamp');
        $display_current_week_only = isset($settings['current_week_only']) && $settings['current_week_only'] === 'yes';
        $week_days_keys = array();
        $restrict_mobile_to_week = array();
        if ($display_current_week_only) {
            $week_display_mode = isset($settings['week_display_mode']) ? sanitize_key((string) $settings['week_display_mode']) : 'current';
            if (!in_array($week_display_mode, array('current', 'previous', 'next', 'custom'), true)) {
                $week_display_mode = 'current';
            }

            $week_custom_reference_raw = '';
            if (isset($settings['week_custom_reference']) && is_string($settings['week_custom_reference'])) {
                $candidate_reference = sanitize_text_field($settings['week_custom_reference']);
                if (preg_match('/^\d{4}-\d{2}-\d{2}(?:\s\d{2}:\d{2}(?::\d{2})?)?$/', $candidate_reference)) {
                    $week_custom_reference_raw = $candidate_reference;
                }
            }

            try {
                $week_reference_dt = new \DateTimeImmutable('@' . $now_ts);
                $week_reference_dt = $week_reference_dt->setTimezone($timezone);
            } catch (\Exception $exception) {
                $week_reference_dt = null;
            }

            if (!($week_reference_dt instanceof \DateTimeImmutable)) {
                $week_reference_dt = new \DateTimeImmutable('now', $timezone);
            }

            if ($week_reference_dt instanceof \DateTimeImmutable) {
                if ($week_display_mode === 'custom' && $week_custom_reference_raw !== '') {
                    try {
                        $custom_reference_dt = new \DateTimeImmutable($week_custom_reference_raw, $timezone);
                        $week_reference_dt = $custom_reference_dt;
                    } catch (\Exception $exception) {
                        // Keep fallback reference when custom date parsing fails.
                    }
                } else {
                    $modifier_map = array(
                        'previous' => '-1 week',
                        'next' => '+1 week',
                    );
                    if (isset($modifier_map[$week_display_mode])) {
                        try {
                            $week_reference_dt = $week_reference_dt->modify($modifier_map[$week_display_mode]);
                        } catch (\Exception $exception) {
                            // Keep fallback reference when modification fails.
                        }
                    }
                }
            }

            try {
                $week_start_dt = $week_reference_dt->modify('monday this week');
            } catch (\Exception $exception) {
                $week_start_dt = null;
            }

            if (!($week_start_dt instanceof \DateTimeImmutable)) {
                $week_start_dt = $week_reference_dt;
            }

            $week_start_dt = $week_start_dt->setTime(0, 0, 0);

            $week_pointer_dt = $week_start_dt;
            for ($week_day_offset = 0; $week_day_offset < 7; $week_day_offset++) {
                if ($week_pointer_dt instanceof \DateTimeImmutable) {
                    $week_days_keys[] = $week_pointer_dt->format('Y-m-d');
                    $week_pointer_dt = $week_pointer_dt->modify('+1 day');
                }
            }
        }

        $occurrence_since = wp_date('Y-m-d H:i:s', $range_start, $timezone);
        $occurrence_until = wp_date('Y-m-d H:i:s', $range_end, $timezone);

        $closure_dates = array();
        if (($highlight_closure_days || $hide_closure_occurrences) && class_exists('MjEventClosures') && method_exists('MjEventClosures', 'get_dates_map_between')) {
            $closure_start = wp_date('Y-m-d', $range_start, $timezone);
            $closure_end = wp_date('Y-m-d', $range_end, $timezone);
            $closure_map = MjEventClosures::get_dates_map_between($closure_start, $closure_end);
            if (is_array($closure_map)) {
                $closure_dates = $closure_map;
            }
        }

        $months = array();
        for ($i = 0; $i < $total_months; $i++) {
            $month_point = $first_month->modify('+' . $i . ' month');
            $month_key = $month_point->format('Y-m');
            $months[$month_key] = array(
                'timestamp' => $month_point->getTimestamp(),
                'label' => wp_date('F Y', $month_point->getTimestamp(), $timezone),
                'days' => array(),
                'has_next_event' => false,
                'multi_events' => array(),
            );
        }

        $type_labels_map = method_exists('MjEvents', 'get_type_labels') ? MjEvents::get_type_labels() : array();
        $available_type_filters = array();

        $next_event_pointer = null;

        $create_datetime = static function ($value) use ($timezone) {
            $value = is_string($value) ? trim($value) : '';
            if ($value === '') {
                return null;
            }

            try {
                return new \DateTimeImmutable($value, $timezone);
            } catch (\Exception $exception) {
                return null;
            }
        };

        $ensure_day_bucket = static function (&$months_map, $month_key, $day_key) {
            if (!isset($months_map[$month_key])) {
                return;
            }

            if (!isset($months_map[$month_key]['days'][$day_key]) || !is_array($months_map[$month_key]['days'][$day_key])) {
                $months_map[$month_key]['days'][$day_key] = array(
                    'events' => array(),
                    'has_multi' => false,
                );
                return;
            }

            if (!isset($months_map[$month_key]['days'][$day_key]['events']) || !is_array($months_map[$month_key]['days'][$day_key]['events'])) {
                $months_map[$month_key]['days'][$day_key]['events'] = array();
            }

            if (!array_key_exists('has_multi', $months_map[$month_key]['days'][$day_key])) {
                $months_map[$month_key]['days'][$day_key]['has_multi'] = false;
            }
        };

        $calendar_start_day_dt = $create_datetime(wp_date('Y-m-d', $range_start, $timezone));
        if (!$calendar_start_day_dt) {
            $calendar_start_day_dt = (new \DateTimeImmutable('@' . $range_start))->setTimezone($timezone)->setTime(0, 0, 0);
        } else {
            $calendar_start_day_dt = $calendar_start_day_dt->setTime(0, 0, 0);
        }

        $calendar_end_day_dt = $create_datetime(wp_date('Y-m-d', $range_end, $timezone));
        if (!$calendar_end_day_dt) {
            $calendar_end_day_dt = (new \DateTimeImmutable('@' . $range_end))->setTimezone($timezone)->setTime(0, 0, 0);
        } else {
            $calendar_end_day_dt = $calendar_end_day_dt->setTime(0, 0, 0);
        }

        $has_any_event = false;

        foreach ($events as $event) {
            $event_id = isset($event['id']) ? (int) $event['id'] : 0;
            if ($event_id <= 0) {
                continue;
            }
            if(isset($_GET['DEBUG']) && $event_id === 37)
            {   
                var_dump("start ", $event['schedule_payload']['items'][0]['start_time']);                 
                var_dump("end ", $event['schedule_payload']['items'][0]['end_time']);    
                /**
                 * ici l'heure est correct
                 * string(6) "start " string(5) "12:00" string(4) "end " string(5) "17:00" 
                 */
            }
            $title = isset($event['title']) ? sanitize_text_field($event['title']) : '';
            $slug = isset($event['slug']) ? sanitize_title($event['slug']) : '';
            $permalink = '';
            if ($slug !== '') {
                $permalink = esc_url(home_url('/evenement/' . rawurlencode($slug)));
            }
            if ($permalink === '' && !empty($event['permalink'])) {
                $permalink = esc_url($event['permalink']);
            }
            if ($permalink === '' && !empty($event['article_permalink'])) {
                $permalink = esc_url($event['article_permalink']);
            }

            $cover_modal = !empty($event['cover_url']) ? esc_url($event['cover_url']) : '';
            if ($cover_modal === '' && !empty($event['article_cover_url'])) {
                $cover_modal = esc_url($event['article_cover_url']);
            }
            $cover_thumb = !empty($event['cover_thumb']) ? esc_url($event['cover_thumb']) : $cover_modal;
            if ($cover_thumb === '' && !empty($event['article_cover_thumb'])) {
                $cover_thumb = esc_url($event['article_cover_thumb']);
            }
            $cover_id = isset($event['cover_id']) ? (int) $event['cover_id'] : 0;
            $cover_sources = self::build_cover_sources($cover_id, $cover_modal, $cover_thumb);
            $primary_cover = $cover_thumb;
            if (($primary_cover === '' || $primary_cover === false) && !empty($cover_sources['fallback'])) {
                $primary_cover = $cover_sources['fallback'];
            }

            $type_key = isset($event['type']) ? sanitize_key($event['type']) : '';
            $type_label = isset($type_labels_map[$type_key]) ? $type_labels_map[$type_key] : '';
            if ($type_label === '' && $type_key !== '') {
                $type_label = ucfirst($type_key);
            }

            $event_type_key = $type_key !== '' ? $type_key : 'misc';
            if ($type_label === '' && $event_type_key === 'misc') {
                $type_label = __('Autre', 'mj-member');
            }

            if (!isset($available_type_filters[$event_type_key])) {
                $fallback_filter_label = $type_label !== '' ? $type_label : ucfirst(str_replace(array('_', '-'), ' ', $event_type_key));
                $available_type_filters[$event_type_key] = array(
                    'label' => $fallback_filter_label,
                );
            }

            $palette = self::build_event_palette(isset($event['accent_color']) ? $event['accent_color'] : '', $event_type_key, $type_colors_map);

            $emoji_value = '';
            if (!empty($event['emoji']) && !is_array($event['emoji'])) {
                $emoji_candidate = sanitize_text_field((string) $event['emoji']);
                if ($emoji_candidate !== '') {
                    if (function_exists('mb_substr')) {
                        $emoji_candidate = mb_substr($emoji_candidate, 0, 8);
                    } else {
                        $emoji_candidate = substr($emoji_candidate, 0, 8);
                    }
                    $emoji_value = $emoji_candidate;
                }
            }

            $price_value = null;
            if (array_key_exists('price', $event) && $event['price'] !== null && $event['price'] !== '') {
                $numeric_price = is_numeric($event['price']) ? (float) $event['price'] : null;
                if ($numeric_price !== null) {
                    $price_value = $numeric_price;
                }
            }

            $location_label = '';
            if (!empty($event['location'])) {
                $location_label = sanitize_text_field((string) $event['location']);
            }

            $description_preview = '';
            if (!empty($event['excerpt']) && !is_array($event['excerpt'])) {
                $description_preview = wp_strip_all_tags((string) $event['excerpt']);
            } elseif (!empty($event['description']) && !is_array($event['description'])) {
                $description_preview = wp_strip_all_tags((string) $event['description']);
            }
            if ($description_preview !== '') {
                $description_preview = wp_html_excerpt($description_preview, 200, '...');
            }

            $age_min = isset($event['age_min']) ? (int) $event['age_min'] : 0;
            $age_max = isset($event['age_max']) ? (int) $event['age_max'] : 0;
            $age_range_label = self::format_age_range_label($age_min, $age_max);

            $is_free_participation = !empty($event['free_participation']) || !empty($event['is_free_participation']);
            $legacy_registration_mode = isset($event['legacy_registration_mode']) ? sanitize_key((string) $event['legacy_registration_mode']) : '';
            $requires_validation = !empty($event['requires_validation']);
            $registration_label = self::build_registration_label($is_free_participation, $legacy_registration_mode, $requires_validation);

            $recurring_schedule_preview = self::build_recurring_schedule_preview($event);
            $schedule_mode = isset($recurring_schedule_preview['mode']) ? sanitize_key((string) $recurring_schedule_preview['mode']) : '';

            $recurrence_summary = '';
            if (function_exists('mj_member_get_event_recurring_summary')) {
                $recurrence_summary = (string) mj_member_get_event_recurring_summary($event);
            }
            if ($recurrence_summary !== '') {
                $recurrence_summary = sanitize_text_field($recurrence_summary);
            }

            $animateur_items = self::build_event_animateurs_preview($event_id);

            $schedule_occurrences = array();
            if (class_exists(MjEventSchedule::class)) {
                $schedule_occurrences = MjEventSchedule::get_occurrences(
                    $event,
                    array(
                        'since' => $occurrence_since,
                        'until' => $occurrence_until,
                        'include_past' => true,
                        'max' => 400,
                    )
                );

                if (empty($schedule_occurrences)) {
                    $schedule_occurrences = MjEventSchedule::build_all_occurrences($event);
                }
            }

            if (empty($schedule_occurrences)) {
                $start_raw = '';
                if (!empty($event['start_date'])) {
                    $start_raw = (string) $event['start_date'];
                } elseif (!empty($event['date_debut'])) {
                    $start_raw = (string) $event['date_debut'];
                }

                if ($start_raw !== '') {
                    $end_raw = '';
                    if (!empty($event['end_date'])) {
                        $end_raw = (string) $event['end_date'];
                    } elseif (!empty($event['date_fin'])) {
                        $end_raw = (string) $event['date_fin'];
                    }

                    $start_ts_candidate = strtotime($start_raw);
                    if ($start_ts_candidate !== false) {
                        $end_ts_candidate = $end_raw !== '' ? strtotime($end_raw) : false;
                        if ($end_ts_candidate === false || $end_ts_candidate <= $start_ts_candidate) {
                            $end_ts_candidate = $start_ts_candidate + HOUR_IN_SECONDS;
                        }
                        if(isset($_GET['DEBUG']) && $event_id === 37)
                        {   
                            var_dump("Start/end ts ", $start_ts_candidate, $end_ts_candidate);                 
                            /**
                             * l'id 37 ne rentre pas ici
                             * 
                             */
                        }
                        $schedule_occurrences[] = array(
                            'start' => wp_date('Y-m-d H:i:s', $start_ts_candidate, $timezone),
                            'end' => wp_date('Y-m-d H:i:s', $end_ts_candidate, $timezone),
                            'timestamp' => $start_ts_candidate,
                        );
                    }
                }
            }

            $normalized_occurrences = array();
            foreach ($schedule_occurrences as $occurrence) {
                if (!is_array($occurrence)) {
                    continue;
                }
                if(isset($_GET['DEBUG']) && $event_id === 37)
                {   
                    var_dump("OC1", $occurrence);                 
                    /**
                     * ICI L'heure est déjà incorrecte (13h au lieu de 12h; 18h au lieu de 17h)
                     * array(4) { ["start"]=> string(19) "2026-01-14 13:00:00" ["timestamp"]=> int(1768392000) ["end"]=> string(19) "2026-01-14 18:00:00" ["label"]=> string(44) "14 janvier 2026 - 12 h 00 min -> 17 h 00 min" } object(DateTimeImmutable)#4672 (3) { ["date"]=> string(26) "2026-01-14 13:00:00.000000" ["timezone_type"]=> int(3) ["timezone"]=> string(15) "Europe/Brussels" } object(DateTimeImmutable)#4166 (3) { ["date"]=> string(26) "2026-01-14 18:00:00.000000" ["timezone_type"]=> int(3) ["timezone"]=> string(15) "Europe/Brussels" }
                     */
                    
                }
                $occurrence_start_raw = isset($occurrence['start']) ? (string) $occurrence['start'] : '';
                if ($occurrence_start_raw === '') {
                    continue;
                }

                $occurrence_start_dt = $create_datetime($occurrence_start_raw);
                $start_ts = isset($occurrence['timestamp']) ? (int) $occurrence['timestamp'] : 0;

                if (!$occurrence_start_dt && $start_ts > 0) {
                    $occurrence_start_dt = (new \DateTimeImmutable('@' . $start_ts))->setTimezone($timezone);
                }
                if ($occurrence_start_dt instanceof \DateTimeImmutable && $start_ts <= 0) {
                    $start_ts = $occurrence_start_dt->getTimestamp();
                }

                if ($start_ts <= 0) {
                    $fallback_ts = strtotime($occurrence_start_raw);
                    if ($fallback_ts !== false) {
                        $start_ts = $fallback_ts;
                        if (!$occurrence_start_dt) {
                            $occurrence_start_dt = (new \DateTimeImmutable('@' . $fallback_ts))->setTimezone($timezone);
                        }
                    }
                }

                if ($start_ts <= 0) {
                    continue;
                }

                if ($start_ts < $range_start || $start_ts > $range_end) {
                    continue;
                }

                $normalized_start = wp_date('Y-m-d H:i:s', $start_ts, $timezone);
                $raw_start_ts = $occurrence_start_dt instanceof \DateTimeImmutable ? $occurrence_start_dt->getTimestamp() : $start_ts;
                $start_offset = $raw_start_ts - $start_ts;
                $occurrence_start_dt = (new \DateTimeImmutable('@' . $start_ts))->setTimezone($timezone);

                $normalized_entry = array(
                    'start' => $normalized_start,
                    'timestamp' => $start_ts,
                );

                if (isset($occurrence['end']) && (string) $occurrence['end'] !== '') {
                    $end_dt = $create_datetime((string) $occurrence['end']);
                    $end_ts_candidate = $end_dt instanceof \DateTimeImmutable ? $end_dt->getTimestamp() : strtotime((string) $occurrence['end']);
                    if ($end_ts_candidate !== false && $end_ts_candidate !== null) {
                        if ($start_offset !== 0) {
                            $end_ts_candidate -= $start_offset;
                        }
                        $end_dt = (new \DateTimeImmutable('@' . $end_ts_candidate))->setTimezone($timezone);
                        $normalized_entry['end'] = $end_dt->format('Y-m-d H:i:s');
                    }
                }

                if (isset($occurrence['label']) && !is_array($occurrence['label'])) {
                    $normalized_entry['label'] = (string) $occurrence['label'];
                }

                if (!empty($occurrence['is_cancelled'])) {
                    $normalized_entry['is_cancelled'] = true;
                }
                if (!empty($occurrence['cancellation_reason']) && !is_array($occurrence['cancellation_reason'])) {
                    $normalized_entry['cancellation_reason'] = sanitize_text_field((string) $occurrence['cancellation_reason']);
                }

                $normalized_occurrences[] = $normalized_entry;
            }

            if (empty($normalized_occurrences)) {
                continue;
            }

            foreach ($normalized_occurrences as $occurrence) {
                $occurrence_start_raw = isset($occurrence['start']) ? (string) $occurrence['start'] : '';
                if ($occurrence_start_raw === '') {
                    continue;
                }

                $occurrence_start_dt = $create_datetime($occurrence_start_raw);
                $start_ts = isset($occurrence['timestamp']) ? (int) $occurrence['timestamp'] : 0;
                if(isset($_GET['DEBUG']) && $event_id === 37)
                {   
                    var_dump("OC2", $occurrence);                 
                    /**
                     * ICI L'heure est déjà incorrecte (13h au lieu de 12h; 18h au lieu de 17h)
                     * array(4) { ["start"]=> string(19) "2026-01-14 13:00:00" ["timestamp"]=> int(1768392000) ["end"]=> string(19) "2026-01-14 18:00:00" ["label"]=> string(44) "14 janvier 2026 - 12 h 00 min -> 17 h 00 min" } object(DateTimeImmutable)#4672 (3) { ["date"]=> string(26) "2026-01-14 13:00:00.000000" ["timezone_type"]=> int(3) ["timezone"]=> string(15) "Europe/Brussels" } object(DateTimeImmutable)#4166 (3) { ["date"]=> string(26) "2026-01-14 18:00:00.000000" ["timezone_type"]=> int(3) ["timezone"]=> string(15) "Europe/Brussels" }
                     */
                    
                }
                if (!$occurrence_start_dt && $start_ts > 0) {
                    $occurrence_start_dt = (new \DateTimeImmutable('@' . $start_ts))->setTimezone($timezone);
                }
                if ($occurrence_start_dt instanceof \DateTimeImmutable && $start_ts <= 0) {
                    $start_ts = $occurrence_start_dt->getTimestamp();
                }
                if ($start_ts <= 0) {
                    $fallback_ts = strtotime($occurrence_start_raw);
                    if ($fallback_ts !== false) {
                        $start_ts = $fallback_ts;
                        if (!$occurrence_start_dt) {
                            $occurrence_start_dt = (new \DateTimeImmutable('@' . $fallback_ts))->setTimezone($timezone);
                        }
                    }
                }
                if ($start_ts <= 0) {
                    continue;
                }
                if (!$occurrence_start_dt) {
                    $occurrence_start_dt = (new \DateTimeImmutable('@' . $start_ts))->setTimezone($timezone);
                }
                $occurrence_start_dt = (new \DateTimeImmutable('@' . $start_ts))->setTimezone($timezone);

                if ($start_ts < $range_start || $start_ts > $range_end) {
                    continue;
                }

                $month_key = wp_date('Y-m', $start_ts, $timezone);
                if (!isset($months[$month_key])) {
                    continue;
                }

                $day_key = wp_date('Y-m-d', $start_ts, $timezone);
                if ($hide_closure_occurrences && isset($closure_dates[$day_key])) {
                    continue;
                }

                $ensure_day_bucket($months, $month_key, $day_key);

                $occurrence_end_dt = null;
                $occurrence_end_ts = $start_ts;
                if (!empty($occurrence['end'])) {
                    $occurrence_end_dt = $create_datetime((string) $occurrence['end']);
                    if (!$occurrence_end_dt) {
                        $end_fallback_ts = strtotime((string) $occurrence['end']);
                        if ($end_fallback_ts !== false) {
                            $occurrence_end_dt = (new \DateTimeImmutable('@' . $end_fallback_ts))->setTimezone($timezone);
                        }
                    }
                    if ($occurrence_end_dt) {
                        $occurrence_end_ts = $occurrence_end_dt->getTimestamp();
                    }
                }
                if(isset($_GET['DEBUG']) && $event_id === 37)
                {                    
                    /**
                     * ICI L'heure est déjà incorrecte (13h au lieu de 12h; 18h au lieu de 17h)
                     */
                    var_dump($occurrence_start_dt, $occurrence_end_dt);
                }
                $time_label = self::format_occurrence_time_label($occurrence_start_dt, $occurrence_end_dt);
                $occurrence_key = $event_id . ':' . $start_ts;

                $occurrence_context = $occurrence;
                if (!isset($occurrence_context['timestamp'])) {
                    $occurrence_context['timestamp'] = $start_ts;
                }
                if (!isset($occurrence_context['start']) || $occurrence_context['start'] === '') {
                    $occurrence_context['start'] = $occurrence_start_dt->format('Y-m-d H:i:s');
                }

                $schedule_label = ScheduleDisplayHelper::buildCalendarLabel(
                    $event,
                    array($occurrence_context),
                    array(
                        'now' => $start_ts,
                        'timezone' => $timezone,
                        'variant' => 'event-schedule-calendar',
                        'fallback_label' => $time_label,
                        'extra_context' => array(
                            'next_occurrence_label' => $time_label,
                        ),
                    )
                );

                if ($schedule_label === '') {
                    $schedule_label = $time_label;
                }

                $occurrence_is_cancelled = !empty($occurrence['is_cancelled']);
                $occurrence_cancellation_reason = '';
                if ($occurrence_is_cancelled) {
                    if (isset($occurrence['cancellation_reason']) && !is_array($occurrence['cancellation_reason'])) {
                        $occurrence_cancellation_reason = trim((string) $occurrence['cancellation_reason']);
                    }
                    if ($occurrence_cancellation_reason === '') {
                        continue;
                    }
                }

                $months[$month_key]['days'][$day_key]['events'][] = array(
                    'id' => $occurrence_key,
                    'title' => $title,
                    'emoji' => $emoji_value,
                    'time' => $time_label,
                    'schedule_label' => $schedule_label,
                    'cover' => $primary_cover,
                    'cover_full' => $cover_modal,
                    'cover_sources' => $cover_sources,
                    'type_label' => $type_label,
                    'type_key' => $event_type_key,
                    'start_ts' => $start_ts,
                    'price' => $price_value,
                    'location_label' => $location_label,
                    'description_excerpt' => $description_preview,
                    'age_min' => $age_min,
                    'age_max' => $age_max,
                    'age_label' => $age_range_label !== '' ? sanitize_text_field($age_range_label) : '',
                    'is_free_participation' => $is_free_participation,
                    'legacy_registration_mode' => $legacy_registration_mode,
                    'requires_validation' => $requires_validation,
                    'registration_label' => $registration_label !== '' ? sanitize_text_field($registration_label) : '',
                    'recurrence_summary' => $recurrence_summary,
                    'animateurs' => $animateur_items,
                    'palette' => $palette,
                    'permalink' => $permalink,
                    'accent_color' => isset($palette['base']) ? $palette['base'] : '',
                    'schedule_mode' => $schedule_mode,
                    'recurring_schedule_preview' => $recurring_schedule_preview,
                    'is_cancelled' => $occurrence_is_cancelled,
                    'cancellation_reason' => $occurrence_cancellation_reason,
                );

                $has_any_event = true;

                if ($occurrence_end_ts > $start_ts) {
                    $span_start_day = $occurrence_start_dt->setTime(0, 0, 0);
                    $span_end_day = $occurrence_end_dt ? $occurrence_end_dt->setTime(0, 0, 0) : $span_start_day;
                    if ($span_end_day->getTimestamp() < $span_start_day->getTimestamp()) {
                        $span_end_day = $span_start_day;
                    }

                    $month_pointer = new \DateTimeImmutable($span_start_day->format('Y-m-01 00:00:00'), $timezone);
                    $end_month_pointer = new \DateTimeImmutable($span_end_day->format('Y-m-01 00:00:00'), $timezone);

                    while ($month_pointer->getTimestamp() <= $end_month_pointer->getTimestamp()) {
                        $segment_month_key = $month_pointer->format('Y-m');
                        if (isset($months[$segment_month_key])) {
                            $month_first_day = $month_pointer;
                            $month_last_day = $month_pointer->modify('last day of this month');

                            if ($month_last_day->getTimestamp() >= $span_start_day->getTimestamp() && $month_first_day->getTimestamp() <= $span_end_day->getTimestamp()) {
                                $segment_start_dt = $span_start_day->getTimestamp() > $month_first_day->getTimestamp() ? $span_start_day : $month_first_day;
                                $segment_end_dt = $span_end_day->getTimestamp() < $month_last_day->getTimestamp() ? $span_end_day : $month_last_day;

                                $segment_start_key = $segment_start_dt->format('Y-m-d');
                                $segment_end_key = $segment_end_dt->format('Y-m-d');

                                $months[$segment_month_key]['multi_events'][] = array(
                                    'event_key' => $occurrence_key,
                                    'title' => $title,
                                    'type_label' => $type_label,
                                    'start_day' => $segment_start_key,
                                    'end_day' => $segment_end_key,
                                    'start_ts' => $start_ts,
                                    'cover' => $primary_cover,
                                    'permalink' => $permalink,
                                    'palette' => $palette,
                                    'is_cancelled' => $occurrence_is_cancelled,
                                    'cancellation_reason' => $occurrence_cancellation_reason,
                                );

                                $marker = $segment_start_dt;
                                while ($marker->getTimestamp() <= $segment_end_dt->getTimestamp()) {
                                    $marker_key = $marker->format('Y-m-d');
                                    $ensure_day_bucket($months, $segment_month_key, $marker_key);
                                    $months[$segment_month_key]['days'][$marker_key]['has_multi'] = true;
                                    $marker = $marker->modify('+1 day');
                                }
                            }
                        }

                        $month_pointer = $month_pointer->modify('first day of next month');
                    }
                }

                if ($highlight_next && $start_ts >= $now_ts) {
                    if ($next_event_pointer === null || $start_ts < $next_event_pointer['start_ts']) {
                        $next_event_pointer = array(
                            'month_key' => $month_key,
                            'day_key' => $day_key,
                            'event_key' => $occurrence_key,
                            'start_ts' => $start_ts,
                        );
                    }
                }
            }
        }

        if ($highlight_closure_days && !empty($closure_dates)) {
            foreach ($closure_dates as $closure_date => $closure_details) {
                $month_key = substr($closure_date, 0, 7);
                if (!isset($months[$month_key])) {
                    continue;
                }

                $ensure_day_bucket($months, $month_key, $closure_date);

                $cover_thumb = '';
                $cover_full = '';
                $closure_cover_id = isset($closure_details['cover_id']) ? (int) $closure_details['cover_id'] : 0;
                if (is_array($closure_details)) {
                    if (!empty($closure_details['cover_thumb'])) {
                        $cover_thumb = esc_url($closure_details['cover_thumb']);
                    }
                    if (!empty($closure_details['cover_full'])) {
                        $cover_full = esc_url($closure_details['cover_full']);
                    } elseif ($cover_thumb !== '') {
                        $cover_full = $cover_thumb;
                    }
                }
                $closure_sources = self::build_cover_sources($closure_cover_id, $cover_full, $cover_thumb);
                $closure_primary_cover = $cover_thumb;
                if (($closure_primary_cover === '' || $closure_primary_cover === false) && !empty($closure_sources['fallback'])) {
                    $closure_primary_cover = $closure_sources['fallback'];
                }

                $description = '';
                if (is_array($closure_details) && !empty($closure_details['description'])) {
                    $description = sanitize_text_field((string) $closure_details['description']);
                }

                $closure_title = __('MJ fermée', 'mj-member');
                $closure_time_label = $description !== '' ? $description : '';
                $closure_event_id = 'closure:' . $closure_date;
                $closure_palette = self::build_closure_palette();
                $closure_timestamp = strtotime($closure_date . ' 00:00:00');
                if ($closure_timestamp === false) {
                    $closure_timestamp = current_time('timestamp');
                }

                $event_list_reference = &$months[$month_key]['days'][$closure_date]['events'];
                $exists_already = false;
                foreach ($event_list_reference as $existing_entry) {
                    if (isset($existing_entry['id']) && $existing_entry['id'] === $closure_event_id) {
                        $exists_already = true;
                        break;
                    }
                }

                if (!$exists_already) {
                    $event_list_reference[] = array(
                        'id' => $closure_event_id,
                        'title' => $closure_title,
                        'time' => $closure_time_label,
                        'schedule_label' => $closure_time_label,
                        'cover' => $closure_primary_cover,
                        'cover_sources' => $closure_sources,
                        'cover_full' => $cover_full,
                        'type_label' => __('Fermeture', 'mj-member'),
                        'type_key' => 'closure',
                        'start_ts' => $closure_timestamp,
                        'is_closure' => true,
                        'palette' => $closure_palette,
                        'permalink' => '',
                        'accent_color' => isset($closure_palette['base']) ? $closure_palette['base'] : '',
                        'schedule_mode' => 'closure',
                        'recurring_schedule_preview' => array(),
                    );
                }
                unset($event_list_reference);

                $months[$month_key]['days'][$closure_date]['is_closure'] = true;
                $has_any_event = true;
            }

            if (!isset($available_type_filters['closure'])) {
                $available_type_filters['closure'] = array(
                    'label' => __('Fermeture', 'mj-member'),
                );
            }
        }

        if ($next_event_pointer !== null && isset($months[$next_event_pointer['month_key']])) {
            $months[$next_event_pointer['month_key']]['has_next_event'] = true;
        }

        $instance_id = wp_unique_id('mj-member-events-calendar-');

        // Charger les assets (CSS externe + JS)
        AssetsManager::requirePackage('events-calendar');

        $cover_width_settings = self::normalize_cover_width_settings($settings);
        $instance_thumb_styles = self::build_cover_width_style_block($instance_id, $cover_width_settings);
        if ($instance_thumb_styles !== '') {
            echo '<style>' . $instance_thumb_styles . '</style>';
        }

        $preferred_index = -1;
        $month_keys = array_keys($months);
        $has_multiple_months = count($month_keys) > 1;
        $today_month_key = wp_date('Y-m', $now_ts, $timezone);
        $initial_month_label = (!empty($month_keys) && isset($months[$month_keys[0]]) && isset($months[$month_keys[0]]['label'])) ? $months[$month_keys[0]]['label'] : '';
        $count_singular_label = __('%d événement', 'mj-member');
        $count_plural_label = __('%d événements', 'mj-member');
        $count_empty_label = __('Aucun événement', 'mj-member');
        $sorted_filters = $available_type_filters;
        if (!empty($sorted_filters)) {
            uasort(
                $sorted_filters,
                static function ($a, $b) {
                    $label_a = isset($a['label']) ? (string) $a['label'] : '';
                    $label_b = isset($b['label']) ? (string) $b['label'] : '';
                    return strcasecmp($label_a, $label_b);
                }
            );
        }

        $preferred_attribute_value = $preferred_index >= 0 ? (string) $preferred_index : '0';
        $calendar_attributes = array(
            'class="mj-member-events-calendar"',
            'id="' . esc_attr($instance_id) . '"',
            'data-calendar-preferred="' . esc_attr($preferred_attribute_value) . '"',
            'data-calendar-today="' . esc_attr($today_month_key) . '"',
            'data-calendar-count-singular="' . esc_attr($count_singular_label) . '"',
            'data-calendar-count-plural="' . esc_attr($count_plural_label) . '"',
            'data-calendar-count-empty="' . esc_attr($count_empty_label) . '"',
        );

        if ($display_current_week_only && !empty($week_days_keys)) {
            $restrict_mobile_to_week = array_fill_keys($week_days_keys, true);
            $calendar_attributes[] = 'data-calendar-week-only="1"';
            $calendar_attributes[] = 'data-calendar-week-days="' . esc_attr(implode(',', $week_days_keys)) . '"';
            $calendar_attributes[] = 'data-calendar-week-start="' . esc_attr($week_days_keys[0]) . '"';
        } else {
            $calendar_attributes[] = 'data-calendar-week-only="0"';
        }

        echo '<div ' . implode(' ', $calendar_attributes) . '>';

        if (!empty($settings['title'])) {
            echo '<h3 class="mj-member-events-calendar__title">' . esc_html($settings['title']) . '</h3>';
        }

        if ($show_toolbar_left || $show_toolbar_actions) {
            echo '<div class="mj-member-events-calendar__toolbar">';
            if ($show_toolbar_left) {
                echo '<div class="mj-member-events-calendar__toolbar-left">';
                echo '<div class="mj-member-events-calendar__nav-group">';
                echo '<button type="button" class="mj-member-events-calendar__nav-button" data-calendar-nav="prev" aria-label="' . esc_attr__('Mois précédent', 'mj-member') . '"><span aria-hidden="true">&lsaquo;</span></button>';
                echo '<span class="mj-member-events-calendar__month-chip" data-calendar-active-label aria-live="polite" aria-atomic="true">' . esc_html($initial_month_label) . '</span>';
                echo '<button type="button" class="mj-member-events-calendar__nav-button" data-calendar-nav="next" aria-label="' . esc_attr__('Mois suivant', 'mj-member') . '"><span aria-hidden="true">&rsaquo;</span></button>';
                echo '</div>';
                echo '</div>';
            }
            if ($show_toolbar_actions) {
                echo '<div class="mj-member-events-calendar__toolbar-actions">';
                if (!empty($sorted_filters)) {
                    echo '<div class="mj-member-events-calendar__filters" role="group" aria-label="' . esc_attr__('Filtrer par type d’événement', 'mj-member') . '">';
                    foreach ($sorted_filters as $filter_key => $filter_meta) {
                        $filter_label = isset($filter_meta['label']) && $filter_meta['label'] !== '' ? (string) $filter_meta['label'] : ucfirst((string) $filter_key);
                        echo '<label class="mj-member-events-calendar__filter">';
                        echo '<input type="checkbox" value="' . esc_attr($filter_key) . '" data-calendar-filter checked />';
                        echo '<span>' . esc_html($filter_label) . '</span>';
                        echo '</label>';
                    }
                    echo '</div>';
                }
                echo '<button type="button" class="mj-member-events-calendar__today-button" data-calendar-action="today">' . esc_html__('Aujourd\'hui', 'mj-member') . '</button>';
                echo '</div>';
            }
            echo '</div>';
        }

        echo '<div class="mj-member-events-calendar__months">';

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
            //echo '<h4 class="mj-member-events-calendar__month-heading">' . esc_html($month_data['label']) . '</h4>';
            echo '<div class="mj-member-events-calendar__grid-wrapper">';
            $week_days = array(
                __('Lun', 'mj-member'),
                __('Mar', 'mj-member'),
                __('Mer', 'mj-member'),
                __('Jeu', 'mj-member'),
                __('Ven', 'mj-member'),
                __('Sam', 'mj-member'),
                __('Dim', 'mj-member'),
            );
            echo '<div class="mj-member-events-calendar__weekday-row">';
            foreach ($week_days as $day_label) {
                echo '<span class="mj-member-events-calendar__weekday">' . esc_html($day_label) . '</span>';
            }
            echo '</div>';
            echo '<div class="mj-member-events-calendar__weeks">';

            $weeks = array();
            $week_index_pointer = -1;
            $weekday_pointer = 0;
            $day_number = 1;
            $day_positions = array();
            $month_prefix = wp_date('Y-m', $month_data['timestamp'], $timezone);
            $day_list_entries = array();

            for ($cell = 0; $cell < $cell_count; $cell++) {
                if ($weekday_pointer === 0) {
                    $week_index_pointer++;
                    $weeks[$week_index_pointer] = array(
                        'cells' => array(),
                        'multi_segments' => array(),
                        'multi_layer_count' => 0,
                    );
                }

                $is_padding = $cell < ($first_day_week_index - 1) || $day_number > $days_in_month;
                if ($is_padding) {
                    $weeks[$week_index_pointer]['cells'][] = array('is_padding' => true);
                } else {
                    $day_key = $month_prefix . '-' . str_pad((string) $day_number, 2, '0', STR_PAD_LEFT);
                    $day_bucket = isset($month_data['days'][$day_key]) && is_array($month_data['days'][$day_key])
                        ? $month_data['days'][$day_key]
                        : array();
                    $events_for_day = isset($day_bucket['events']) && is_array($day_bucket['events']) ? $day_bucket['events'] : array();
                    $has_multi = !empty($day_bucket['has_multi']);

                    $weeks[$week_index_pointer]['cells'][] = array(
                        'is_padding' => false,
                        'day_number' => $day_number,
                        'day_key' => $day_key,
                        'events' => $events_for_day,
                        'has_multi' => $has_multi,
                        'is_closure' => !empty($month_data['days'][$day_key]['is_closure']),
                    );

                    $day_positions[$day_key] = array(
                        'week' => $week_index_pointer,
                        'col' => $weekday_pointer,
                    );

                    if (empty($restrict_mobile_to_week) || isset($restrict_mobile_to_week[$day_key])) {
                        $day_list_entries[] = array(
                            'day_number' => $day_number,
                            'day_key' => $day_key,
                            'events' => $events_for_day,
                            'is_closure' => !empty($month_data['days'][$day_key]['is_closure']),
                        );
                    }

                    $day_number++;
                }

                $weekday_pointer++;
                if ($weekday_pointer === 7) {
                    $weekday_pointer = 0;
                }
            }

            if (!empty($month_data['multi_events'])) {
                foreach ($month_data['multi_events'] as $multi_event) {
                    $segment_start_dt = $create_datetime($multi_event['start_day']);
                    $segment_end_dt = $create_datetime($multi_event['end_day']);
                    if (!$segment_start_dt || !$segment_end_dt) {
                        continue;
                    }

                    $segment_start_dt = $segment_start_dt->setTime(0, 0, 0);
                    $segment_end_dt = $segment_end_dt->setTime(0, 0, 0);
                    if ($segment_start_dt->getTimestamp() > $segment_end_dt->getTimestamp()) {
                        continue;
                    }

                    $segment_cursor = $segment_start_dt;
                    while ($segment_cursor->getTimestamp() <= $segment_end_dt->getTimestamp()) {
                        $segment_day_key = $segment_cursor->format('Y-m-d');
                        if (!isset($day_positions[$segment_day_key])) {
                            $segment_cursor = $segment_cursor->modify('+1 day');
                            continue;
                        }

                        $position = $day_positions[$segment_day_key];
                        $week_slot = $position['week'];
                        $start_column = $position['col'];

                        $remaining_total_days = (int) floor(($segment_end_dt->getTimestamp() - $segment_cursor->getTimestamp()) / DAY_IN_SECONDS) + 1;
                        if ($remaining_total_days <= 0) {
                            break;
                        }

                        $remaining_in_week = 7 - $start_column;
                        $span_days = min($remaining_total_days, $remaining_in_week);
                        if ($span_days <= 0) {
                            break;
                        }

                        $weeks[$week_slot]['multi_segments'][] = array(
                            'event_key' => $multi_event['event_key'],
                            'title' => $multi_event['title'],
                            'type_label' => $multi_event['type_label'],
                            'start_col' => $start_column,
                            'span' => $span_days,
                            'start_day' => $segment_day_key,
                            'start_ts' => isset($multi_event['start_ts']) ? (int) $multi_event['start_ts'] : $segment_cursor->getTimestamp(),
                            'cover' => isset($multi_event['cover']) ? $multi_event['cover'] : '',
                            'permalink' => isset($multi_event['permalink']) ? $multi_event['permalink'] : '',
                            'palette' => isset($multi_event['palette']) && is_array($multi_event['palette']) ? $multi_event['palette'] : array(),
                            'is_cancelled' => !empty($multi_event['is_cancelled']),
                            'cancellation_reason' => !empty($multi_event['cancellation_reason']) ? (string) $multi_event['cancellation_reason'] : '',
                        );

                        $segment_cursor = $segment_cursor->modify('+' . $span_days . ' day');
                    }
                }
            }

            foreach ($weeks as &$week_reference) {
                if (empty($week_reference['multi_segments'])) {
                    $week_reference['multi_layer_count'] = 0;
                    continue;
                }

                usort(
                    $week_reference['multi_segments'],
                    static function ($a, $b) {
                        if ($a['start_col'] === $b['start_col']) {
                            return $a['span'] <=> $b['span'];
                        }

                        return $a['start_col'] <=> $b['start_col'];
                    }
                );

                $layers = array();
                foreach ($week_reference['multi_segments'] as &$segment_entry) {
                    $placed = false;
                    foreach ($layers as $layer_index => $layer_end_column) {
                        if ($segment_entry['start_col'] >= $layer_end_column) {
                            $segment_entry['row'] = $layer_index;
                            $layers[$layer_index] = $segment_entry['start_col'] + $segment_entry['span'];
                            $placed = true;
                            break;
                        }
                    }

                    if (!$placed) {
                        $segment_entry['row'] = count($layers);
                        $layers[] = $segment_entry['start_col'] + $segment_entry['span'];
                    }
                }
                unset($segment_entry);

                $week_reference['multi_layer_count'] = count($layers);
            }
            unset($week_reference);

            foreach ($weeks as $week_entry) {
                echo '<div class="mj-member-events-calendar__week">';
                echo '<div class="mj-member-events-calendar__week-days">';
                foreach ($week_entry['cells'] as $cell_entry) {
                    if (!empty($cell_entry['is_padding'])) {
                        echo '<div class="mj-member-events-calendar__day-cell is-padding"></div>';
                        continue;
                    }

                    $day_key = $cell_entry['day_key'];
                    $events_for_day = isset($cell_entry['events']) ? $cell_entry['events'] : array();
                    if (!empty($events_for_day)) {
                        usort(
                            $events_for_day,
                            static function ($a, $b) {
                                return (int) $a['start_ts'] <=> (int) $b['start_ts'];
                            }
                        );
                    }

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
                    if (!empty($cell_entry['is_closure'])) {
                        $day_classes[] = 'is-closure';
                    }
                    $day_data_attr = ' data-calendar-day="' . esc_attr($day_key) . '"';

                    echo '<div class="mj-member-events-calendar__day-cell">';
                    echo '<div class="' . esc_attr(implode(' ', $day_classes)) . '"' . $day_data_attr . '>';
                    echo '<span class="mj-member-events-calendar__day-number">' . esc_html($cell_entry['day_number']) . '</span>';

                    if (!empty($events_for_day)) {
                        echo '<ul class="mj-member-events-calendar__events">';
                        foreach ($events_for_day as $event_entry) {
                            if (!is_array($event_entry) || !isset($event_entry['id'])) {
                                continue;
                            }

                            $event_classes = array('mj-member-events-calendar__event');
                            if (!empty($event_entry['is_closure'])) {
                                $event_classes[] = 'is-closure';
                            }
                            if (!empty($event_entry['is_cancelled'])) {
                                $event_classes[] = 'is-cancelled';
                            }
                            if ($next_event_pointer && $next_event_pointer['event_key'] === $event_entry['id']) {
                                $event_classes[] = 'is-next';
                            }

                            $style_attribute = self::build_event_style_attribute($event_entry);
                            $event_permalink = isset($event_entry['permalink']) ? (string) $event_entry['permalink'] : '';
                            $event_href = $event_permalink !== '' ? $event_permalink : '#';
                            $event_type_key = isset($event_entry['type_key']) ? sanitize_key((string) $event_entry['type_key']) : '';
                            if ($event_type_key === '') {
                                $event_type_key = 'misc';
                            }
                            $event_is_closure = !empty($event_entry['is_closure']);
                            $is_known_type = isset($available_type_filters[$event_type_key]) || $event_type_key === 'closure';
                            $type_attributes = ' data-calendar-type-item="1" data-calendar-type="' . esc_attr($event_type_key) . '" data-calendar-type-known="' . ($is_known_type ? '1' : '0') . '"';
                            echo '<li class="' . esc_attr(implode(' ', $event_classes)) . '"' . $style_attribute . $type_attributes . '>';
                            $trigger_attributes = ' class="mj-member-events-calendar__event-trigger"';
                            if ($event_is_closure) {
                                echo '<div' . $trigger_attributes . '>';
                            } else {
                                echo '<a' . $trigger_attributes . ' href="' . esc_url($event_href) . '">';
                            }
                            $has_type_label = !empty($event_entry['type_label']);
                            $event_badges_markup = self::build_event_badges_markup($event_entry, 'grid');
                            $event_emoji = '';
                            if (isset($event_entry['emoji']) && $event_entry['emoji'] !== '') {
                                $event_emoji = (string) $event_entry['emoji'];
                                if ($event_emoji !== '' && function_exists('mb_substr')) {
                                    $event_emoji = mb_substr($event_emoji, 0, 8);
                                } elseif ($event_emoji !== '') {
                                    $event_emoji = substr($event_emoji, 0, 8);
                                }
                            }

                            echo '<span class="mj-member-events-calendar__event-copy">';
                            echo '<span class="mj-member-events-calendar__event-title">';
                            if ($event_emoji !== '') {
                                echo '<span class="mj-member-events-calendar__event-emoji">' . esc_html($event_emoji) . '</span>';
                            }
                            echo '<span class="mj-member-events-calendar__event-title-text">' . esc_html($event_entry['title']) . '</span>';
                            echo '</span>';
                            $meta_label = '';
                            if (isset($event_entry['schedule_label']) && $event_entry['schedule_label'] !== '') {
                                $meta_label = (string) $event_entry['schedule_label'];
                            } elseif (!empty($event_entry['time'])) {
                                
         
                                $meta_label = (string) $event_entry['time'];
                            }
                            if ($meta_label !== '') {
                                echo '<span class="mj-member-events-calendar__event-meta">' . esc_html($meta_label) . '</span>';
                            }
                            if (!empty($event_entry['is_cancelled'])) {
                                $cancellation_reason = isset($event_entry['cancellation_reason']) ? trim((string) $event_entry['cancellation_reason']) : '';
                                echo '<span class="mj-member-events-calendar__event-cancellation">';
                                echo '<span class="mj-member-events-calendar__event-cancellation-label">' . esc_html__('Annulé', 'mj-member') . '</span>';
                                if ($cancellation_reason !== '') {
                                    echo '<span class="mj-member-events-calendar__event-cancellation-reason">' . esc_html($cancellation_reason) . '</span>';
                                }
                                echo '</span>';
                            }
                            echo '</span>';

                            if ($event_badges_markup !== '') {
                                echo $event_badges_markup;
                            }

                            if ($has_type_label) {
                                echo '<span class="mj-member-events-calendar__event-type mj-member-events-calendar__event-type--border">' . esc_html($event_entry['type_label']) . '</span>';
                            }

                            $preview_cover = '';
                            if (!empty($event_entry['cover_full'])) {
                                $preview_cover = (string) $event_entry['cover_full'];
                            } elseif (!empty($event_entry['cover'])) {
                                $preview_cover = (string) $event_entry['cover'];
                            } elseif (!empty($event_entry['cover_sources']) && is_array($event_entry['cover_sources']) && !empty($event_entry['cover_sources']['fallback'])) {
                                $preview_cover = (string) $event_entry['cover_sources']['fallback'];
                            }

                            $preview_schedule = '';
                            if (!empty($event_entry['schedule_label'])) {
                                $preview_schedule = (string) $event_entry['schedule_label'];
                            } elseif (!empty($event_entry['time'])) {
                                $preview_schedule = (string) $event_entry['time'];
                            }

                            $preview_location = !empty($event_entry['location_label']) ? (string) $event_entry['location_label'] : '';
                            $preview_description = !empty($event_entry['description_excerpt']) ? (string) $event_entry['description_excerpt'] : '';

                            $preview_age = (!$event_is_closure && !empty($event_entry['age_label'])) ? (string) $event_entry['age_label'] : '';
                            $preview_recurrence = (!$event_is_closure && !empty($event_entry['recurrence_summary'])) ? (string) $event_entry['recurrence_summary'] : '';
                            $preview_registration = (!$event_is_closure && !empty($event_entry['registration_label'])) ? (string) $event_entry['registration_label'] : '';
                            $preview_animateurs = (!$event_is_closure && !empty($event_entry['animateurs']) && is_array($event_entry['animateurs'])) ? $event_entry['animateurs'] : array();

                            $preview_recurring_schedule_entries = array();
                            if (!$event_is_closure && isset($event_entry['recurring_schedule_preview']) && is_array($event_entry['recurring_schedule_preview'])) {
                                $preview_schedule_meta = $event_entry['recurring_schedule_preview'];
                                if (isset($preview_schedule_meta['mode']) && (string) $preview_schedule_meta['mode'] === 'recurring' && !empty($preview_schedule_meta['entries']) && is_array($preview_schedule_meta['entries'])) {
                                    foreach ($preview_schedule_meta['entries'] as $schedule_entry) {
                                        if (!is_array($schedule_entry)) {
                                            continue;
                                        }

                                        $day_label = isset($schedule_entry['label']) ? (string) $schedule_entry['label'] : '';
                                        $time_value = isset($schedule_entry['time']) ? (string) $schedule_entry['time'] : '';

                                        if ($day_label === '' && $time_value === '') {
                                            continue;
                                        }

                                        $preview_recurring_schedule_entries[] = array(
                                            'label' => $day_label,
                                            'time' => $time_value,
                                        );

                                        if (count($preview_recurring_schedule_entries) >= 6) {
                                            break;
                                        }
                                    }
                                }
                            }

                            $has_preview_recurring_schedule = !empty($preview_recurring_schedule_entries);

                            $price_label = '';
                            if (!$event_is_closure && array_key_exists('price', $event_entry) && $event_entry['price'] !== null && $event_entry['price'] !== '') {
                                $price_value = (float) $event_entry['price'];
                                if ($price_value <= 0) {
                                    $price_label = __('Gratuit', 'mj-member');
                                } else {
                                    $price_label = sprintf(__('%s €', 'mj-member'), number_format_i18n($price_value, 2));
                                }
                            }

                            if ($event_is_closure && $preview_schedule === '') {
                                $preview_schedule = __('Fermeture exceptionnelle', 'mj-member');
                            }
                            if ($event_is_closure && $preview_description === '') {
                                $preview_description = __('La Maison des Jeunes est fermée sur cette date.', 'mj-member');
                            }

                            $has_preview_animateurs = !$event_is_closure && !empty($preview_animateurs);

                            if ($preview_cover !== '' || $price_label !== '' || $preview_schedule !== '' || $preview_location !== '' || $preview_description !== '' || $preview_age !== '' || $preview_recurrence !== '' || $preview_registration !== '' || $has_preview_animateurs) {
                                echo '<div class="mj-member-events-calendar__event-preview" aria-hidden="true">';
                                echo '<div class="mj-member-events-calendar__event-preview-content">';
                                if ($preview_cover !== '' || $has_preview_animateurs || $preview_registration !== '' || $preview_age !== '') {
                                    echo '<div class="mj-member-events-calendar__event-preview-side">';
                                    if ($preview_cover !== '') {
                                        echo '<div class="mj-member-events-calendar__event-preview-cover"><img src="' . esc_url($preview_cover) . '" alt="' . esc_attr($event_entry['title']) . '" loading="lazy" /></div>';
                                    }
                                    if ($preview_registration !== '') {
                                        echo '<div class="mj-member-events-calendar__event-preview-line mj-member-events-calendar__event-preview-line--side"><span class="mj-member-events-calendar__event-preview-label">' . esc_html__('Inscriptions', 'mj-member') . '</span><span class="mj-member-events-calendar__event-preview-value">' . esc_html($preview_registration) . '</span></div>';
                                    }
                                    if ($preview_age !== '') {
                                        echo '<div class="mj-member-events-calendar__event-preview-line mj-member-events-calendar__event-preview-line--side"><span class="mj-member-events-calendar__event-preview-label">' . esc_html__('Âges', 'mj-member') . '</span><span class="mj-member-events-calendar__event-preview-value">' . esc_html($preview_age) . '</span></div>';
                                    }
                                    if ($has_preview_animateurs) {
                                        echo '<div class="mj-member-events-calendar__event-preview-animateurs">';
                                        echo '<span class="mj-member-events-calendar__event-preview-label">' . esc_html__('Animateurs', 'mj-member') . '</span>';
                                        echo '<span class="mj-member-events-calendar__event-preview-value">';
                                        echo '<span class="mj-member-events-calendar__event-animateurs">';
                                        $animateur_limit = 4;
                                        $animateur_total = count($preview_animateurs);
                                        $animateur_subset = array_slice($preview_animateurs, 0, $animateur_limit);
                                        foreach ($animateur_subset as $animateur_item) {
                                            if (!is_array($animateur_item)) {
                                                continue;
                                            }

                                            $animateur_name = isset($animateur_item['name']) ? (string) $animateur_item['name'] : '';
                                            $animateur_role = isset($animateur_item['role_label']) ? (string) $animateur_item['role_label'] : '';
                                            $animateur_avatar = !empty($animateur_item['avatar']) ? (string) $animateur_item['avatar'] : '';
                                            $animateur_initials = isset($animateur_item['initials']) ? (string) $animateur_item['initials'] : '';
                                            $animateur_is_primary = !empty($animateur_item['is_primary']);

                                            $animateur_classes = array('mj-member-events-calendar__event-animateur');
                                            if ($animateur_avatar !== '') {
                                                $animateur_classes[] = 'has-avatar';
                                            }
                                            if ($animateur_is_primary) {
                                                $animateur_classes[] = 'is-primary';
                                            }

                                            $animateur_title = $animateur_name;
                                            if ($animateur_title === '' && $animateur_initials !== '') {
                                                $animateur_title = $animateur_initials;
                                            }
                                            if ($animateur_role !== '') {
                                                $animateur_title = $animateur_title !== '' ? $animateur_title . ' — ' . $animateur_role : $animateur_role;
                                            }

                                            $animateur_alt = $animateur_name !== '' ? sprintf(__('Portrait de %s', 'mj-member'), $animateur_name) : __('Portrait de l\'animateur', 'mj-member');

                                            echo '<span class="' . esc_attr(implode(' ', $animateur_classes)) . '" title="' . esc_attr($animateur_title) . '">';
                                            if ($animateur_avatar !== '') {
                                                echo '<img src="' . esc_url($animateur_avatar) . '" alt="' . esc_attr($animateur_alt) . '" loading="lazy" />';
                                            } elseif ($animateur_initials !== '') {
                                                echo '<span class="mj-member-events-calendar__event-animateur-initials" aria-hidden="true">' . esc_html($animateur_initials) . '</span>';
                                            } else {
                                                echo '<span class="mj-member-events-calendar__event-animateur-initials" aria-hidden="true">?</span>';
                                            }
                                            echo '</span>';
                                        }

                                        if ($animateur_total > $animateur_limit) {
                                            $animateur_remaining = $animateur_total - $animateur_limit;
                                            echo '<span class="mj-member-events-calendar__event-animateur mj-member-events-calendar__event-animateur--more">+' . esc_html((string) $animateur_remaining) . '</span>';
                                        }

                                        echo '</span>';
                                        echo '</span>';
                                        echo '</div>';
                                    }
                                    echo '</div>';
                                }

                                echo '<div class="mj-member-events-calendar__event-preview-body">';
                                if (!empty($event_entry['is_cancelled'])) {
                                    $preview_cancellation_reason = isset($event_entry['cancellation_reason']) ? trim((string) $event_entry['cancellation_reason']) : '';
                                    echo '<div class="mj-member-events-calendar__event-preview-cancellation">';
                                    echo '<span class="mj-member-events-calendar__event-preview-cancellation-label">' . esc_html__('Événement annulé', 'mj-member') . '</span>';
                                    if ($preview_cancellation_reason !== '') {
                                        echo '<span class="mj-member-events-calendar__event-preview-cancellation-reason">' . esc_html($preview_cancellation_reason) . '</span>';
                                    }
                                    echo '</div>';
                                }
                                if ($price_label !== '') {
                                    echo '<div class="mj-member-events-calendar__event-preview-line mj-member-events-calendar__event-preview-price"><span class="mj-member-events-calendar__event-preview-label">' . esc_html__('Tarif', 'mj-member') . '</span><span class="mj-member-events-calendar__event-preview-value">' . esc_html($price_label) . '</span></div>';
                                }
                                if ($preview_schedule !== '') {
                                    if ($event_is_closure) {
                                        echo '<div class="mj-member-events-calendar__event-preview-line"><span class="mj-member-events-calendar__event-preview-value">' . esc_html($preview_schedule) . '</span></div>';
                                    } elseif (!$has_preview_recurring_schedule) {
                                        echo '<div class="mj-member-events-calendar__event-preview-line"><span class="mj-member-events-calendar__event-preview-label">' . esc_html__('Plage horaire', 'mj-member') . '</span><span class="mj-member-events-calendar__event-preview-value">' . esc_html($preview_schedule) . '</span></div>';
                                    }
                                }
                                if (!empty($preview_recurring_schedule_entries)) {
                                    echo '<div class="mj-member-events-calendar__event-preview-recurring">';
                                    echo '<span class="mj-member-events-calendar__event-preview-label">' . esc_html__('Horaires par jour', 'mj-member') . '</span>';
                                    echo '<ul class="mj-member-events-calendar__event-preview-recurring-list">';
                                    foreach ($preview_recurring_schedule_entries as $recurring_entry) {
                                        $recurring_day = isset($recurring_entry['label']) ? (string) $recurring_entry['label'] : '';
                                        $recurring_time = isset($recurring_entry['time']) ? (string) $recurring_entry['time'] : '';
                                        if ($recurring_day === '' && $recurring_time === '') {
                                            continue;
                                        }

                                        echo '<li class="mj-member-events-calendar__event-preview-recurring-item">';
                                        if ($recurring_day !== '') {
                                            echo '<span class="mj-member-events-calendar__event-preview-recurring-day">' . esc_html($recurring_day) . '</span>';
                                        }
                                        if ($recurring_time !== '') {
                                            echo '<span class="mj-member-events-calendar__event-preview-recurring-time">' . esc_html($recurring_time) . '</span>';
                                        }
                                        echo '</li>';
                                    }
                                    echo '</ul>';
                                    echo '</div>';
                                }
                                if ($preview_location !== '') {
                                    echo '<div class="mj-member-events-calendar__event-preview-line"><span class="mj-member-events-calendar__event-preview-label">' . esc_html__('Lieu', 'mj-member') . '</span><span class="mj-member-events-calendar__event-preview-value">' . esc_html($preview_location) . '</span></div>';
                                }
                                if ($preview_description !== '') {
                                    echo '<p class="mj-member-events-calendar__event-preview-description">' . esc_html($preview_description) . '</p>';
                                }
                                echo '</div>';
                                echo '</div>';
                                echo '</div>';
                            }
                            if ($event_is_closure) {
                                echo '</div>';
                            } else {
                                echo '</a>';
                            }
                            echo '</li>';
                        }
                        echo '</ul>';
                    }

                    echo '</div>';
                    echo '</div>';
                }
                echo '</div>';

                echo '</div>';
            }

            echo '</div>';
            echo '</div>';

            if (!empty($day_list_entries)) {
                echo '<div class="mj-member-events-calendar__mobile-list" data-calendar-mobile>';
                foreach ($day_list_entries as $day_entry) {
                    $day_key = isset($day_entry['day_key']) ? (string) $day_entry['day_key'] : '';
                    $mobile_events = isset($day_entry['events']) && is_array($day_entry['events']) ? $day_entry['events'] : array();
                    if (empty($mobile_events)) {
                        continue;
                    }

                    usort(
                        $mobile_events,
                        static function ($a, $b) {
                            return (int) (isset($a['start_ts']) ? $a['start_ts'] : 0) <=> (int) (isset($b['start_ts']) ? $b['start_ts'] : 0);
                        }
                    );

                    $mobile_day_classes = array('mj-member-events-calendar__mobile-day');
                    if (!empty($day_entry['is_closure'])) {
                        $mobile_day_classes[] = 'is-closure';
                    }
                    if ($day_key === wp_date('Y-m-d', $now_ts, $timezone)) {
                        $mobile_day_classes[] = 'is-today';
                    }

                    $day_timestamp = strtotime($day_key . ' 00:00:00');
                    if ($day_timestamp === false) {
                        $day_timestamp = $now_ts;
                    }
                    $day_label = wp_date('l j F', $day_timestamp, $timezone);
                    $events_count = count($mobile_events);
                    $count_label = sprintf(_n('%d événement', '%d événements', $events_count, 'mj-member'), $events_count);

                    echo '<details class="' . esc_attr(implode(' ', $mobile_day_classes)) . '" data-calendar-day="' . esc_attr($day_key) . '" open>';
                    echo '<summary class="mj-member-events-calendar__mobile-summary">';
                    echo '<span>' . esc_html($day_label) . '</span>';
                    echo '<span class="mj-member-events-calendar__mobile-count" data-calendar-day-count>' . esc_html($count_label) . '</span>';
                    echo '</summary>';

                    echo '<ul class="mj-member-events-calendar__mobile-events">';
                    foreach ($mobile_events as $mobile_event) {
                        if (!is_array($mobile_event) || !isset($mobile_event['id'])) {
                            continue;
                        }

                        $event_type_key = isset($mobile_event['type_key']) ? sanitize_key((string) $mobile_event['type_key']) : '';
                        if ($event_type_key === '') {
                            $event_type_key = 'misc';
                        }
                        $is_known_type = isset($available_type_filters[$event_type_key]) || $event_type_key === 'closure';
                        $mobile_classes = array('mj-member-events-calendar__mobile-event');
                        $mobile_is_closure = !empty($mobile_event['is_closure']);
                        if ($mobile_is_closure) {
                            $mobile_classes[] = 'is-closure';
                        }
                        if (!empty($mobile_event['is_cancelled'])) {
                            $mobile_classes[] = 'is-cancelled';
                        }

                        $mobile_style = self::build_event_style_attribute($mobile_event);
                        $mobile_cover_sources = array();
                        if (isset($mobile_event['cover_sources']) && is_array($mobile_event['cover_sources'])) {
                            $mobile_cover_sources = $mobile_event['cover_sources'];
                        }
                        $mobile_fallback_cover = isset($mobile_event['cover']) ? (string) $mobile_event['cover'] : '';
                        if ($mobile_fallback_cover === '' && isset($mobile_cover_sources['fallback']) && $mobile_cover_sources['fallback'] !== '') {
                            $mobile_fallback_cover = $mobile_cover_sources['fallback'];
                        }
                        $has_mobile_cover = $mobile_fallback_cover !== '';

                        $mobile_permalink = isset($mobile_event['permalink']) ? (string) $mobile_event['permalink'] : '';
                        $mobile_tag = ($mobile_is_closure || $mobile_permalink === '') ? 'div' : 'a';
                        $mobile_attributes = ' class="mj-member-events-calendar__mobile-link' . ($mobile_is_closure ? ' is-static' : '') . '"';
                        if ($mobile_tag === 'a') {
                            $mobile_attributes .= ' href="' . esc_url($mobile_permalink) . '"';
                        }

                        echo '<li class="' . esc_attr(implode(' ', $mobile_classes)) . '"' . $mobile_style . ' data-calendar-type-item="1" data-calendar-type="' . esc_attr($event_type_key) . '" data-calendar-type-known="' . ($is_known_type ? '1' : '0') . '">';

                        echo '<' . $mobile_tag . $mobile_attributes . '>';

                        if ($has_mobile_cover) {
                            echo '<span class="mj-member-events-calendar__event-thumb mj-member-events-calendar__event-thumb--mobile">';
                            echo '<picture>';
                            if (!empty($mobile_cover_sources['desktop'])) {
                                echo '<source media="(min-width: 901px)" srcset="' . esc_url($mobile_cover_sources['desktop']) . '" />';
                            }
                            if (!empty($mobile_cover_sources['tablet'])) {
                                echo '<source media="(min-width: 641px)" srcset="' . esc_url($mobile_cover_sources['tablet']) . '" />';
                            }
                            if (!empty($mobile_cover_sources['mobile'])) {
                                echo '<source media="(max-width: 640px)" srcset="' . esc_url($mobile_cover_sources['mobile']) . '" />';
                            }
                            echo '<img src="' . esc_url($mobile_fallback_cover) . '" alt="' . esc_attr($mobile_event['title']) . '" loading="lazy" />';
                            echo '</picture>';
                            echo '</span>';
                        }

                        echo '<div class="mj-member-events-calendar__mobile-body">';
                        if (!empty($mobile_event['type_label'])) {
                            echo '<span class="mj-member-events-calendar__mobile-pill">' . esc_html($mobile_event['type_label']) . '</span>';
                        }
                        $mobile_emoji = '';
                        if (isset($mobile_event['emoji']) && $mobile_event['emoji'] !== '') {
                            $mobile_emoji = (string) $mobile_event['emoji'];
                            if ($mobile_emoji !== '' && function_exists('mb_substr')) {
                                $mobile_emoji = mb_substr($mobile_emoji, 0, 8);
                            } elseif ($mobile_emoji !== '') {
                                $mobile_emoji = substr($mobile_emoji, 0, 8);
                            }
                        }
                        echo '<span class="mj-member-events-calendar__mobile-title">';
                        if ($mobile_emoji !== '') {
                            echo '<span class="mj-member-events-calendar__event-emoji">' . esc_html($mobile_emoji) . '</span>';
                        }
                        echo '<span class="mj-member-events-calendar__mobile-title-text">' . esc_html($mobile_event['title']) . '</span>';
                        echo '</span>';
                        $mobile_meta = '';
                        if (isset($mobile_event['schedule_label']) && $mobile_event['schedule_label'] !== '') {
                            $mobile_meta = (string) $mobile_event['schedule_label'];
                        } elseif (!empty($mobile_event['time'])) {
                            $mobile_meta = (string) $mobile_event['time'];
                        }
                        $mobile_badges_markup = self::build_event_badges_markup($mobile_event, 'mobile');
                        if ($mobile_meta !== '') {
                            echo '<span class="mj-member-events-calendar__mobile-meta">' . esc_html($mobile_meta) . '</span>';
                        }
                        if ($mobile_badges_markup !== '') {
                            echo $mobile_badges_markup;
                        }
                        if (!empty($mobile_event['is_cancelled'])) {
                            $mobile_cancellation_reason = isset($mobile_event['cancellation_reason']) ? trim((string) $mobile_event['cancellation_reason']) : '';
                            echo '<span class="mj-member-events-calendar__mobile-cancellation">';
                            echo '<span class="mj-member-events-calendar__mobile-cancellation-label">' . esc_html__('Annulé', 'mj-member') . '</span>';
                            if ($mobile_cancellation_reason !== '') {
                                echo '<span class="mj-member-events-calendar__mobile-cancellation-reason">' . esc_html($mobile_cancellation_reason) . '</span>';
                            }
                            echo '</span>';
                        }
                        echo '</div>';
                        echo '</' . $mobile_tag . '>';

                        echo '</li>';
                    }
                    echo '</ul>';

                    echo '</details>';
                }
                echo '</div>';
            }

            echo '</section>';

            $month_index++;
        }

        echo '</div>';

        if (!$has_any_event && $empty_message !== '') {
            echo '<p class="mj-member-events-calendar__empty">' . esc_html($empty_message) . '</p>';
        }

        echo '</div>';

        $preferred_index = ($preferred_index >= 0) ? $preferred_index : 0;

        $instance_config = array(
            'preferredIndex' => $preferred_index,
            'todayMonth' => $today_month_key,
        );


        echo '<script>window.mjMemberEventsCalendarQueue = window.mjMemberEventsCalendarQueue || [];window.mjMemberEventsCalendarQueue.push({id:' . wp_json_encode($instance_id) . ',config:' . wp_json_encode($instance_config) . '});</script>';
    }

    /**
     * Format the age range label displayed in the preview tooltip.
     */
    private static function format_age_range_label($min, $max) {
        $min = (int) $min;
        $max = (int) $max;

        if ($min <= 0 && $max <= 0) {
            return '';
        }

        if ($min > 0 && $max > 0) {
            if ($min === $max) {
                return sprintf(__('%d ans', 'mj-member'), $min);
            }

            return sprintf(__('De %1$d à %2$d ans', 'mj-member'), $min, $max);
        }

        if ($min > 0) {
            return sprintf(__('Dès %d ans', 'mj-member'), $min);
        }

        return sprintf(__('Jusqu\'à %d ans', 'mj-member'), $max);
    }

    /**
     * Build a short registration summary label for the preview tooltip.
     */
    private static function build_registration_label($is_free_participation, $mode, $requires_validation) {
        if ($is_free_participation) {
            $label = __('Participation libre', 'mj-member');

            if ($requires_validation) {
                $label .= ' — ' . __('Validation requise', 'mj-member');
            }

            return $label;
        }

        $mode = sanitize_key($mode);

        $mode_labels = array(
            'participant' => __('Inscription des jeunes', 'mj-member'),
            'guardian' => __('Inscription via responsables', 'mj-member'),
            'volunteer' => __('Inscription réservée à l\'équipe', 'mj-member'),
            'staff' => __('Réservé à l\'équipe', 'mj-member'),
            'internal' => __('Réservé aux membres internes', 'mj-member'),
            'application' => __('Sur candidature', 'mj-member'),
            'pre_registration' => __('Pré-inscription', 'mj-member'),
            'ticket' => __('Billetterie', 'mj-member'),
            'form' => __('Formulaire externe', 'mj-member'),
            'email' => __('Inscription par email', 'mj-member'),
            'external' => __('Inscription externe', 'mj-member'),
        );

        if ($mode !== '' && isset($mode_labels[$mode])) {
            $label = $mode_labels[$mode];
        } elseif ($mode !== '') {
            $friendly = ucwords(str_replace(array('_', '-'), ' ', $mode));
            $label = sprintf(__('Inscription (%s)', 'mj-member'), $friendly);
        } else {
            $label = __('Inscription en ligne', 'mj-member');
        }

        if ($requires_validation) {
            $label .= ' — ' . __('Validation requise', 'mj-member');
        }

        return $label;
    }

    /**
     * Build badge markup for calendar event entries.
     */
    private static function build_event_badges_markup($event_entry, $context = 'grid') {
        if (!is_array($event_entry)) {
            return '';
        }

        if (!empty($event_entry['is_closure'])) {
            return '';
        }

        $badges = array();

        if (!empty($event_entry['is_free_participation'])) {
            $badges[] = array(
                'label' => __('Participation libre', 'mj-member'),
                'modifier' => 'free',
            );
        }

        if (!empty($event_entry['requires_validation'])) {
            $badges[] = array(
                'label' => __('Validation requise', 'mj-member'),
                'modifier' => 'validation',
            );
        }

        if (empty($badges)) {
            return '';
        }

        $wrapper_classes = array('mj-member-events-calendar__badges');
        if ($context === 'mobile') {
            $wrapper_classes[] = 'mj-member-events-calendar__badges--mobile';
        } else {
            $wrapper_classes[] = 'mj-member-events-calendar__badges--grid';
        }

        $badge_markup_parts = array();

        foreach ($badges as $badge) {
            $label = isset($badge['label']) ? (string) $badge['label'] : '';
            if ($label === '') {
                continue;
            }

            $badge_classes = array('mj-member-events-calendar__badge');
            $modifier = isset($badge['modifier']) ? (string) $badge['modifier'] : '';
            if ($modifier !== '') {
                if (function_exists('sanitize_html_class')) {
                    $modifier = sanitize_html_class($modifier);
                }
                if ($modifier !== '') {
                    $badge_classes[] = 'mj-member-events-calendar__badge--' . $modifier;
                }
            }

            $badge_markup_parts[] = '<span class="' . esc_attr(implode(' ', $badge_classes)) . '">' . esc_html($label) . '</span>';
        }

        if (empty($badge_markup_parts)) {
            return '';
        }

        return '<span class="' . esc_attr(implode(' ', $wrapper_classes)) . '">' . implode('', $badge_markup_parts) . '</span>';
    }

    /**
     * Build animateur preview data (avatars + initials) for a given event.
     *
     * @param int $event_id
     * @return array<int,array<string,mixed>>
     */
    private static function build_event_animateurs_preview($event_id) {
        $event_id = (int) $event_id;
        if ($event_id <= 0) {
            return array();
        }

        static $cache = array();
        if (isset($cache[$event_id])) {
            return $cache[$event_id];
        }

        if (!class_exists(MjEventAnimateurs::class)) {
            $cache[$event_id] = array();
            return $cache[$event_id];
        }

        $rows = MjEventAnimateurs::get_members_by_event($event_id);
        if (empty($rows)) {
            $cache[$event_id] = array();
            return $cache[$event_id];
        }

        $items = array();

        foreach ($rows as $index => $row) {
            if (!is_object($row)) {
                continue;
            }

            $member_id = isset($row->id) ? (int) $row->id : 0;
            $first_name = isset($row->first_name) ? sanitize_text_field((string) $row->first_name) : '';
            $last_name = isset($row->last_name) ? sanitize_text_field((string) $row->last_name) : '';

            $full_name = trim($first_name . ' ' . $last_name);
            if ($full_name === '' && isset($row->nickname)) {
                $full_name = sanitize_text_field((string) $row->nickname);
            }
            if ($full_name === '' && $member_id > 0) {
                $full_name = sprintf(__('Membre #%d', 'mj-member'), $member_id);
            }
            $full_name = sanitize_text_field($full_name);

            $role_key = isset($row->role) ? sanitize_key((string) $row->role) : '';
            $role_label = '';
            if ($role_key !== '' && class_exists(MjRoles::class)) {
                $role_label = MjRoles::getRoleLabel($role_key);
            }
            if ($role_label !== '') {
                $role_label = sanitize_text_field($role_label);
            }

            $avatar_url = '';
            if (!empty($row->photo_id) && function_exists('wp_get_attachment_image_src')) {
                $photo_id = (int) $row->photo_id;
                if ($photo_id > 0) {
                    $photo = wp_get_attachment_image_src($photo_id, 'thumbnail');
                    if (is_array($photo) && !empty($photo[0])) {
                        $avatar_url = esc_url_raw($photo[0]);
                    }
                }
            }

            if ($avatar_url === '' && !empty($row->wp_user_id) && function_exists('get_avatar_url')) {
                $avatar_url = esc_url_raw(get_avatar_url((int) $row->wp_user_id, array('size' => 96)));
            }

            if ($avatar_url === '' && !empty($row->email) && is_email($row->email) && function_exists('get_avatar_url')) {
                $avatar_url = esc_url_raw(get_avatar_url($row->email, array('size' => 96)));
            }

            $initials_source = $full_name !== '' ? $full_name : trim($first_name . ' ' . $last_name);
            $initials = self::build_member_initials($initials_source);
            if ($initials !== '') {
                $initials = sanitize_text_field($initials);
            }

            $items[] = array(
                'id' => $member_id,
                'name' => $full_name,
                'role' => $role_key,
                'role_label' => $role_label,
                'avatar' => $avatar_url,
                'initials' => $initials,
                'is_primary' => $index === 0,
            );

            if (count($items) >= 6) {
                break;
            }
        }

        $cache[$event_id] = $items;

        return $cache[$event_id];
    }

    /**
     * Extract two-letter initials from a name.
     */
    private static function build_member_initials($name) {
        $name = is_string($name) ? trim($name) : '';
        if ($name === '') {
            return '';
        }

        $parts = preg_split('/[\s\-]+/u', $name);
        if (!is_array($parts) || empty($parts)) {
            $parts = array($name);
        }

        $initials = '';
        foreach ($parts as $part) {
            $part = trim((string) $part);
            if ($part === '') {
                continue;
            }

            if (function_exists('mb_substr')) {
                $initials .= mb_substr($part, 0, 1);
            } else {
                $initials .= substr($part, 0, 1);
            }

            $length = function_exists('mb_strlen') ? mb_strlen($initials) : strlen($initials);
            if ($length >= 2) {
                if ($length > 2) {
                    $initials = function_exists('mb_substr') ? mb_substr($initials, 0, 2) : substr($initials, 0, 2);
                }
                break;
            }
        }

        if ($initials === '' && $name !== '') {
            if (function_exists('mb_substr')) {
                $initials = mb_substr($name, 0, 1);
            } else {
                $initials = substr($name, 0, 1);
            }
        }

        if (function_exists('mb_strtoupper')) {
            return mb_strtoupper($initials);
        }

        return strtoupper($initials);
    }

    private static function build_recurring_schedule_preview($event) {
        $result = array(
            'mode' => '',
            'entries' => array(),
        );

        if (is_object($event)) {
            $event = get_object_vars($event);
        }
        if (!is_array($event)) {
            return $result;
        }

        $mode = isset($event['schedule_mode']) ? sanitize_key((string) $event['schedule_mode']) : '';
        if ($mode === '') {
            $mode = 'fixed';
        }
        $result['mode'] = $mode;

        if ($mode !== 'recurring') {
            return $result;
        }

        $payload = array();
        if (isset($event['schedule_payload'])) {
            if (is_array($event['schedule_payload'])) {
                $payload = $event['schedule_payload'];
            } elseif (is_string($event['schedule_payload']) && $event['schedule_payload'] !== '') {
                $decoded = json_decode($event['schedule_payload'], true);
                if (is_array($decoded)) {
                    $payload = $decoded;
                }
            }
        }

        if (empty($payload) || !is_array($payload)) {
            return $result;
        }

        $frequency = isset($payload['frequency']) ? sanitize_key((string) $payload['frequency']) : 'weekly';
        $weekday_labels = array(
            'monday' => __('Lundi', 'mj-member'),
            'tuesday' => __('Mardi', 'mj-member'),
            'wednesday' => __('Mercredi', 'mj-member'),
            'thursday' => __('Jeudi', 'mj-member'),
            'friday' => __('Vendredi', 'mj-member'),
            'saturday' => __('Samedi', 'mj-member'),
            'sunday' => __('Dimanche', 'mj-member'),
        );

        if ($frequency === 'weekly') {
            $weekday_order = array(
                'monday' => 1,
                'tuesday' => 2,
                'wednesday' => 3,
                'thursday' => 4,
                'friday' => 5,
                'saturday' => 6,
                'sunday' => 7,
            );

            $weekdays = array();
            if (!empty($payload['weekdays']) && is_array($payload['weekdays'])) {
                foreach ($payload['weekdays'] as $weekday_candidate) {
                    $weekday_key = sanitize_key((string) $weekday_candidate);
                    if ($weekday_key !== '' && isset($weekday_order[$weekday_key])) {
                        $weekdays[$weekday_key] = $weekday_key;
                    }
                }
            }

            if (empty($weekdays) && !empty($payload['weekday_times']) && is_array($payload['weekday_times'])) {
                foreach ($payload['weekday_times'] as $weekday_key => $time_info) {
                    $weekday_candidate = sanitize_key((string) $weekday_key);
                    if ($weekday_candidate !== '' && isset($weekday_order[$weekday_candidate])) {
                        $weekdays[$weekday_candidate] = $weekday_candidate;
                    }
                }
            }

            if (empty($weekdays)) {
                return $result;
            }

            uksort(
                $weekdays,
                static function ($left, $right) use ($weekday_order) {
                    return $weekday_order[$left] <=> $weekday_order[$right];
                }
            );

            $default_start = isset($payload['start_time']) ? (string) $payload['start_time'] : '';
            $default_end = isset($payload['end_time']) ? (string) $payload['end_time'] : '';
            $weekday_times = isset($payload['weekday_times']) && is_array($payload['weekday_times']) ? $payload['weekday_times'] : array();

            $time_groups = array();

            foreach (array_keys($weekdays) as $weekday_key) {
                $start_raw = $default_start;
                $end_raw = $default_end;

                if (isset($weekday_times[$weekday_key]) && is_array($weekday_times[$weekday_key])) {
                    $day_times = $weekday_times[$weekday_key];
                    if (!empty($day_times['start'])) {
                        $start_raw = (string) $day_times['start'];
                    }
                    if (!empty($day_times['end'])) {
                        $end_raw = (string) $day_times['end'];
                    }
                }

                $start_formatted = self::format_schedule_time_for_preview($start_raw);
                $end_formatted = self::format_schedule_time_for_preview($end_raw);

                $time_range = '';
                if ($start_formatted !== '' && $end_formatted !== '' && $start_formatted !== $end_formatted) {
                    $time_range = $start_formatted . ' → ' . $end_formatted;
                } elseif ($start_formatted !== '') {
                    $time_range = sprintf(__('À partir de %s', 'mj-member'), $start_formatted);
                } elseif ($end_formatted !== '') {
                    $time_range = $end_formatted;
                }

                if ($time_range === '') {
                    continue;
                }

                $label = isset($weekday_labels[$weekday_key]) ? $weekday_labels[$weekday_key] : ucfirst($weekday_key);
                $label = sanitize_text_field($label);

                if (!isset($time_groups[$time_range])) {
                    $time_groups[$time_range] = array(
                        'time' => $time_range,
                        'days' => array(),
                    );
                }

                $time_groups[$time_range]['days'][] = $label;
            }

            foreach ($time_groups as $group_entry) {
                if (empty($group_entry['days'])) {
                    continue;
                }

                $days_label = self::format_schedule_days_label($group_entry['days']);
                $time_label = isset($group_entry['time']) ? (string) $group_entry['time'] : '';

                if ($days_label === '' && $time_label === '') {
                    continue;
                }

                $result['entries'][] = array(
                    'label' => $days_label,
                    'time' => sanitize_text_field($time_label),
                );

                if (count($result['entries']) >= 6) {
                    break;
                }
            }

            return $result;
        }

        if ($frequency === 'monthly') {
            $ordinal_labels = array(
                'first' => __('1er', 'mj-member'),
                'second' => __('2ème', 'mj-member'),
                'third' => __('3ème', 'mj-member'),
                'fourth' => __('4ème', 'mj-member'),
                'last' => __('Dernier', 'mj-member'),
            );

            $ordinal = isset($payload['ordinal']) ? sanitize_key((string) $payload['ordinal']) : '';
            $weekday = isset($payload['weekday']) ? sanitize_key((string) $payload['weekday']) : '';

            $ordinal_label = isset($ordinal_labels[$ordinal]) ? $ordinal_labels[$ordinal] : $ordinal;
            $weekday_label = isset($weekday_labels[$weekday]) ? $weekday_labels[$weekday] : $weekday;

            $summary = trim(implode(' ', array_filter(array($ordinal_label, $weekday_label, __('du mois', 'mj-member')))));

            $start_formatted = self::format_schedule_time_for_preview(isset($payload['start_time']) ? $payload['start_time'] : '');
            $end_formatted = self::format_schedule_time_for_preview(isset($payload['end_time']) ? $payload['end_time'] : '');

            $time_range = '';
            if ($start_formatted !== '' && $end_formatted !== '' && $start_formatted !== $end_formatted) {
                $time_range = $start_formatted . ' → ' . $end_formatted;
            } elseif ($start_formatted !== '') {
                $time_range = sprintf(__('À partir de %s', 'mj-member'), $start_formatted);
            } elseif ($end_formatted !== '') {
                $time_range = $end_formatted;
            }

            if ($summary !== '') {
                $result['entries'][] = array(
                    'label' => sanitize_text_field($summary),
                    'time' => sanitize_text_field($time_range),
                );
            }

            return $result;
        }

        return $result;
    }

    private static function format_schedule_time_for_preview($value) {
        $value = is_string($value) ? trim($value) : '';
        if ($value === '') {
            return '';
        }

        if (preg_match('/^\d{1,2}:\d{2}$/', $value)) {
            $value .= ':00';
        } elseif (!preg_match('/^\d{1,2}:\d{2}:\d{2}$/', $value)) {
            return '';
        }

        $timezone = wp_timezone();
        if (!($timezone instanceof \DateTimeZone)) {
            $timezone = new \DateTimeZone('UTC');
        }

        try {
            $datetime = new \DateTimeImmutable('1970-01-01 ' . $value, $timezone);
        } catch (\Exception $exception) {
            return '';
        }

        $time_format = get_option('time_format', 'H:i');
        return wp_date($time_format, $datetime->getTimestamp(), $timezone);
    }

    private static function format_schedule_days_label(array $days) {
        $days = array_values(array_filter(array_map('trim', $days))); 
        if (empty($days)) {
            return '';
        }

        if (count($days) === 1) {
            return sanitize_text_field($days[0]);
        }

        if (count($days) === 2) {
            return sanitize_text_field($days[0] . ' ' . __('et', 'mj-member') . ' ' . $days[1]);
        }

        $last = array_pop($days);
        $joined = implode(', ', $days) . ' ' . __('et', 'mj-member') . ' ' . $last;

        return sanitize_text_field($joined);
    }

    /**
     * Formate le libellé horaire d'une occurrence en respectant le fuseau WP.
     */
    private static function format_occurrence_time_label(\DateTimeImmutable $start, ?\DateTimeImmutable $end = null) {
        $timezone = wp_timezone();
        if (!($timezone instanceof \DateTimeZone)) {
            $timezone = new \DateTimeZone('UTC');
        }

        $time_format = get_option('time_format', 'H:i');
        $start_label = self::normalize_time_label(wp_date($time_format, $start->getTimestamp(), $timezone));

        if ($start_label === '') {
            return '';
        }

        if ($end instanceof \DateTimeImmutable) {
            $end_label = self::normalize_time_label(wp_date($time_format, $end->getTimestamp(), $timezone));
            if ($end_label !== '' && $end_label !== $start_label) {
                return $start_label . ' → ' . $end_label;
            }
        }

        return sprintf(__('À partir de %s', 'mj-member'), $start_label);
    }

    private static function normalize_time_label($label) {
        $label = is_string($label) ? trim($label) : '';
        if ($label === '') {
            return '';
        }

        $label = preg_replace('/\s+/u', ' ', $label);
        if ($label === null) {
            $label = '';
        }

        $label = preg_replace('/\s*min$/u', '', $label);
        if ($label === null) {
            $label = '';
        }

        $label = preg_replace('/\s*h\s*/u', 'h', $label);
        if ($label === null) {
            $label = '';
        }

        return trim($label);
    }

    /**
     * Normalize a hex color value to the #RRGGBB format.
     */
    private static function normalize_hex_color_value($value) {
        $candidate = sanitize_hex_color($value);
        if (!is_string($candidate) || $candidate === '') {
            return '';
        }

        if (strlen($candidate) === 4) {
            $candidate = '#' . $candidate[1] . $candidate[1] . $candidate[2] . $candidate[2] . $candidate[3] . $candidate[3];
        }

        return strtoupper($candidate);
    }

    /**
     * Build a derived palette for an event.
     *
     * @param string $accent_color
     * @param string $type_key
     * @param array<string,string> $type_colors_map
     * @return array<string,string>
     */
    private static function build_event_palette($accent_color, $type_key, $type_colors_map) {
        $accent = self::normalize_hex_color_value($accent_color);
        if ($accent === '' && $type_key !== '' && isset($type_colors_map[$type_key])) {
            $accent = self::normalize_hex_color_value($type_colors_map[$type_key]);
        }

        if ($accent === '') {
            $accent = '#2563EB';
        }

        $contrast = self::pick_contrast_color($accent);

        return array(
            'base' => $accent,
            'contrast' => $contrast,
            'surface' => self::mix_hex_colors($accent, '#FFFFFF', 0.86),
            'border' => self::mix_hex_colors($accent, '#FFFFFF', 0.7),
            'pill_bg' => self::mix_hex_colors($accent, '#FFFFFF', 0.82),
            'pill_text' => $accent,
            'thumb_bg' => self::mix_hex_colors($accent, '#FFFFFF', 0.9),
            'highlight' => self::mix_hex_colors($accent, '#FFFFFF', 0.78),
            'range_bg' => self::mix_hex_colors($accent, '#FFFFFF', 0.75),
            'range_border' => self::mix_hex_colors($accent, '#FFFFFF', 0.55),
        );
    }

    /**
     * Palette dédiée aux jours de fermeture.
     *
     * @return array<string,string>
     */
    private static function build_closure_palette() {
        return self::build_event_palette('#EF4444', 'closure', array('closure' => '#EF4444'));
    }

    /**
     * Convert a hex color to RGB components.
     *
     * @param string $value
     * @return array<int,int>|null
     */
    private static function hex_to_rgb($value) {
        $normalized = self::normalize_hex_color_value($value);
        if ($normalized === '') {
            return null;
        }

        $normalized = ltrim($normalized, '#');
        if (strlen($normalized) !== 6) {
            return null;
        }

        return array(
            hexdec(substr($normalized, 0, 2)),
            hexdec(substr($normalized, 2, 2)),
            hexdec(substr($normalized, 4, 2)),
        );
    }

    /**
     * Blend two colors together.
     *
     * @param string $base
     * @param string $blend
     * @param float $ratio
     * @return string
     */
    private static function mix_hex_colors($base, $blend, $ratio) {
        $base_rgb = self::hex_to_rgb($base);
        $blend_rgb = self::hex_to_rgb($blend);
        if ($base_rgb === null || $blend_rgb === null) {
            return self::normalize_hex_color_value($base);
        }

        $ratio = max(0.0, min(1.0, (float) $ratio));

        $mixed = array(
            (int) round($base_rgb[0] * (1 - $ratio) + $blend_rgb[0] * $ratio),
            (int) round($base_rgb[1] * (1 - $ratio) + $blend_rgb[1] * $ratio),
            (int) round($base_rgb[2] * (1 - $ratio) + $blend_rgb[2] * $ratio),
        );

        return sprintf('#%02X%02X%02X', $mixed[0], $mixed[1], $mixed[2]);
    }

    /**
     * Choose an accessible contrast color (dark or light) for the given accent.
     */
    private static function pick_contrast_color($hex) {
        $rgb = self::hex_to_rgb($hex);
        if ($rgb === null) {
            return '#FFFFFF';
        }

        $luminance = (0.2126 * $rgb[0]) + (0.7152 * $rgb[1]) + (0.0722 * $rgb[2]);

        return $luminance >= 150 ? '#0F172A' : '#FFFFFF';
    }

    /**
     * Normalize cover width settings coming from Elementor sliders.
     *
     * @param array<string,mixed> $settings
     * @return array<string,int>
     */
    private static function normalize_cover_width_settings($settings) {
        $defaults = array(
            'desktop' => 120,
            'tablet' => 110,
            'mobile' => 90,
        );

        $normalized = array();

        foreach ($defaults as $key => $fallback) {
            $setting_key = 'cover_width_' . $key;
            $value = isset($settings[$setting_key]) ? $settings[$setting_key] : array();
            $normalized[$key] = self::extract_cover_width_value($value, $fallback);
        }

        return $normalized;
    }

    /**
     * Extract a sanitized width value from an Elementor slider control.
     *
     * @param mixed $value
     * @param int $fallback
     * @return int
     */
    private static function extract_cover_width_value($value, $fallback) {
        $min = 10;
        $max = 500;

        if (is_array($value)) {
            if (isset($value['size']) && is_numeric($value['size'])) {
                $candidate = (float) $value['size'];
                if ($candidate >= $min && $candidate <= $max) {
                    return (int) round($candidate);
                }
            }
        } elseif (is_numeric($value)) {
            $candidate = (float) $value;
            if ($candidate >= $min && $candidate <= $max) {
                return (int) round($candidate);
            }
        }

        return (int) $fallback;
    }

    /**
     * Build the CSS rules that apply the chosen cover widths per breakpoint.
     *
     * @param string $instance_id
     * @param array<string,int> $widths
     * @return string
     */
    private static function build_cover_width_style_block($instance_id, $widths) {
        if (!is_string($instance_id) || $instance_id === '' || !is_array($widths)) {
            return '';
        }

        $normalized_id = preg_replace('/[^a-zA-Z0-9_-]/', '', $instance_id);
        if ($normalized_id === '') {
            return '';
        }

        $desktop = isset($widths['desktop']) ? (int) $widths['desktop'] : 120;
        $tablet = isset($widths['tablet']) ? (int) $widths['tablet'] : 110;
        $mobile = isset($widths['mobile']) ? (int) $widths['mobile'] : 90;

        $desktop = min(500, max(10, $desktop));
        $tablet = min(500, max(10, $tablet));
        $mobile = min(500, max(10, $mobile));

        $rules = array();
        $rules[] = sprintf('#%1$s .mj-member-events-calendar__event-thumb{width:%2$dpx;height:%2$dpx;}', $normalized_id, $desktop);
        $rules[] = sprintf('#%1$s .mj-member-events-calendar__event-thumb img{width:100%%;height:100%%;object-fit:cover;}', $normalized_id);
        $rules[] = sprintf('@media (max-width: 900px){#%1$s .mj-member-events-calendar__event-thumb{width:%2$dpx;height:%2$dpx;}}', $normalized_id, $tablet);
        $rules[] = sprintf('@media (max-width: 640px){#%1$s .mj-member-events-calendar__event-thumb{width:%2$dpx;height:%2$dpx;}}', $normalized_id, $mobile);

        return implode('', $rules);
    }

    /**
     * Build responsive cover sources depending on the current device mode.
     *
     * @param int $cover_id
     * @param string $fallback_large
     * @param string $fallback_medium
     * @return array<string,string>
     */
    private static function build_cover_sources($cover_id, $fallback_large = '', $fallback_medium = '') {
        $cover_id = (int) $cover_id;

        $sanitize_url = static function ($value) {
            $value = is_string($value) ? trim($value) : '';
            if ($value === '') {
                return '';
            }

            return esc_url_raw($value);
        };

        $fallback_large = $sanitize_url($fallback_large);
        $fallback_medium = $sanitize_url($fallback_medium);

        $sources = array(
            'desktop' => '',
            'tablet' => '',
            'mobile' => '',
            'fallback' => '',
        );

        if ($cover_id > 0 && function_exists('wp_attachment_is_image') && wp_attachment_is_image($cover_id)) {
            $desktop = wp_get_attachment_image_src($cover_id, 'large');
            $tablet = wp_get_attachment_image_src($cover_id, 'medium_large');
            if (!is_array($tablet)) {
                $tablet = wp_get_attachment_image_src($cover_id, 'medium');
            }
            $mobile = wp_get_attachment_image_src($cover_id, 'medium');
            if (!is_array($mobile)) {
                $mobile = wp_get_attachment_image_src($cover_id, 'thumbnail');
            }
            $thumbnail = wp_get_attachment_image_src($cover_id, 'thumbnail');

            if (is_array($desktop) && !empty($desktop[0])) {
                $sources['desktop'] = esc_url_raw($desktop[0]);
            }
            if (is_array($tablet) && !empty($tablet[0])) {
                $sources['tablet'] = esc_url_raw($tablet[0]);
            }
            if (is_array($mobile) && !empty($mobile[0])) {
                $sources['mobile'] = esc_url_raw($mobile[0]);
            } elseif (is_array($thumbnail) && !empty($thumbnail[0])) {
                $sources['mobile'] = esc_url_raw($thumbnail[0]);
            }
        }

        $fallback = $fallback_large !== '' ? $fallback_large : $fallback_medium;
        if ($sources['desktop'] === '') {
            $sources['desktop'] = $fallback;
        }
        if ($sources['tablet'] === '') {
            $sources['tablet'] = $fallback !== '' ? $fallback : $sources['desktop'];
        }
        if ($sources['mobile'] === '') {
            $sources['mobile'] = $fallback_medium !== '' ? $fallback_medium : ($fallback !== '' ? $fallback : $sources['tablet']);
        }

        if ($sources['fallback'] === '' && $fallback !== '') {
            $sources['fallback'] = $fallback;
        } elseif ($sources['fallback'] === '') {
            $sources['fallback'] = $sources['desktop'] !== '' ? $sources['desktop'] : ($sources['tablet'] !== '' ? $sources['tablet'] : $sources['mobile']);
        }

        foreach ($sources as $key => $value) {
            if ($value === '') {
                continue;
            }
            $sources[$key] = esc_url($value);
        }

        return $sources;
    }

    /**
     * Build inline CSS variables for an event entry.
     *
     * @param array<string,mixed> $event_entry
     * @return string
     */
    private static function build_event_style_attribute($event_entry) {
        if (!is_array($event_entry) || empty($event_entry['palette']) || !is_array($event_entry['palette'])) {
            return '';
        }

        $palette = $event_entry['palette'];
        $map = array(
            '--mj-event-accent' => 'base',
            '--mj-event-highlight' => 'highlight',
            '--mj-event-surface' => 'surface',
            '--mj-event-border' => 'border',
            '--mj-event-pill-bg' => 'pill_bg',
            '--mj-event-pill-text' => 'pill_text',
            '--mj-event-thumb-bg' => 'thumb_bg',
            '--mj-event-range-bg' => 'range_bg',
            '--mj-event-range-border' => 'range_border',
        );

        $styles = array();
        foreach ($map as $css_var => $palette_key) {
            if (isset($palette[$palette_key]) && $palette[$palette_key] !== '') {
                $styles[] = $css_var . ':' . $palette[$palette_key];
            }
        }

        if (empty($styles)) {
            return '';
        }

        return ' style="' . esc_attr(implode(';', $styles)) . '"';
    }
}