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
        return array('general');
    }

    public function get_keywords() {
        return array('mj', 'events', 'calendar', 'agenda', 'planning');
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
                'permalink' => home_url('/evenements/stage-de-decouverte'),
                'accent_color' => isset($type_colors_map['stage']) ? self::normalize_hex_color_value($type_colors_map['stage']) : '',
            );

            $second_event = $default_event;
            $second_event['id'] = 2;
            $second_event['title'] = __('Atelier numérique', 'mj-member');
            $second_event['type'] = 'atelier';
            $second_event['date_debut'] = wp_date('Y-m-d 18:00:00', $range_start + (int) (3 * DAY_IN_SECONDS), $timezone);
            $second_event['date_fin'] = wp_date('Y-m-d 20:00:00', $range_start + (int) (3 * DAY_IN_SECONDS), $timezone);
            $second_event['accent_color'] = isset($type_colors_map['atelier']) ? self::normalize_hex_color_value($type_colors_map['atelier']) : '';
            $second_event['permalink'] = home_url('/evenements/atelier-numerique');

            $events = array($default_event, $second_event);
        }

        $now_ts = current_time('timestamp');

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

            $title = isset($event['title']) ? sanitize_text_field($event['title']) : '';
            $permalink = !empty($event['permalink']) ? esc_url($event['permalink']) : '';
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
            $location_label = isset($event['location']) ? sanitize_text_field($event['location']) : '';
            $address_label = isset($event['location_address']) ? sanitize_text_field($event['location_address']) : '';
            $location_notes = isset($event['location_description']) ? sanitize_textarea_field($event['location_description']) : '';
            $map_embed = !empty($event['location_map']) ? esc_url($event['location_map']) : '';
            $map_link = !empty($event['location_map_link']) ? esc_url($event['location_map_link']) : '';

            $type_key = isset($event['type']) ? sanitize_key($event['type']) : '';
            $type_label = isset($type_labels_map[$type_key]) ? $type_labels_map[$type_key] : '';
            if ($type_label === '' && $type_key !== '') {
                $type_label = ucfirst($type_key);
            }
            $event_type_key = $type_key !== '' ? $type_key : 'misc';
            if ($type_key !== '') {
                $available_type_filters[$type_key] = array(
                    'label' => $type_label !== '' ? $type_label : ucfirst($type_key),
                );
            }

            $event_accent_color = isset($event['accent_color']) ? self::normalize_hex_color_value($event['accent_color']) : '';
            $palette = self::build_event_palette($event_accent_color, $type_key, $type_colors_map);

            $price_label = '';
            if (isset($event['price']) && (float) $event['price'] > 0) {
                $price_label = sprintf(__('Tarif : %s €', 'mj-member'), number_format_i18n((float) $event['price'], 2));
            }

            $description_html = '';
            if (!empty($event['description'])) {
                $description_html = wpautop(wp_kses_post($event['description']));
            }

            $occurrence_args = array(
                'max' => 240,
                'include_past' => true,
                'since' => $occurrence_since,
                'until' => $occurrence_until,
            );

            $occurrences = array();
            if (class_exists('MjEventSchedule')) {
                $occurrences = MjEventSchedule::get_occurrences($event, $occurrence_args);
            }

            if (empty($occurrences)) {
                $start_fallback = !empty($event['start_date']) ? (string) $event['start_date'] : '';
                if ($start_fallback !== '') {
                    $end_fallback = !empty($event['end_date']) ? (string) $event['end_date'] : $start_fallback;
                    $fallback_timestamp = strtotime($start_fallback);
                    if ($fallback_timestamp !== false) {
                        $occurrences[] = array(
                            'start' => $start_fallback,
                            'end' => $end_fallback,
                            'timestamp' => $fallback_timestamp,
                        );
                    }
                }
            }

            $event_start_raw = !empty($event['start_date']) ? (string) $event['start_date'] : '';
            $event_end_raw = !empty($event['end_date']) ? (string) $event['end_date'] : $event_start_raw;

            $event_start_dt = $create_datetime($event_start_raw);
            $event_end_dt = $create_datetime($event_end_raw);
            if ($event_start_dt && $event_end_dt && $event_end_dt < $event_start_dt) {
                $event_end_dt = $event_start_dt;
            }

            $event_start_day_dt = $event_start_dt ? $event_start_dt->setTime(0, 0, 0) : null;
            $event_end_day_dt = $event_end_dt ? $event_end_dt->setTime(0, 0, 0) : $event_start_day_dt;

            $is_multi_day = false;
            if ($event_start_day_dt && $event_end_day_dt) {
                $difference_interval = $event_start_day_dt->diff($event_end_day_dt);
                if ($difference_interval && $difference_interval->days !== false && (int) $difference_interval->days >= 1) {
                    $is_multi_day = true;
                }
            }

            if ($is_multi_day && $event_start_day_dt && $event_end_day_dt) {
                $clamped_start_ts = max($event_start_day_dt->getTimestamp(), $calendar_start_day_dt->getTimestamp());
                $clamped_end_ts = min($event_end_day_dt->getTimestamp(), $calendar_end_day_dt->getTimestamp());

                if ($clamped_start_ts <= $clamped_end_ts) {
                    $clamped_start_day_dt = (new \DateTimeImmutable('@' . $clamped_start_ts))->setTimezone($timezone)->setTime(0, 0, 0);
                    $clamped_end_day_dt = (new \DateTimeImmutable('@' . $clamped_end_ts))->setTimezone($timezone)->setTime(0, 0, 0);
                    $multi_event_key = 'multi:' . $event_id;
                    $event_head_day_key = $clamped_start_day_dt->format('Y-m-d');
                    $event_tail_day_key = $clamped_end_day_dt->format('Y-m-d');

                    $segment_cursor = $clamped_start_day_dt;
                    while ($segment_cursor->getTimestamp() <= $clamped_end_day_dt->getTimestamp()) {
                        $segment_month_key = $segment_cursor->format('Y-m');
                        if (!isset($months[$segment_month_key])) {
                            $segment_cursor = $segment_cursor->modify('first day of next month')->setTime(0, 0, 0);
                            continue;
                        }

                        $month_anchor = $segment_cursor->format('Y-m-01');
                        $month_end_dt = (new \DateTimeImmutable($month_anchor, $timezone))->modify('last day of this month')->setTime(0, 0, 0);
                        if ($month_end_dt->getTimestamp() > $clamped_end_day_dt->getTimestamp()) {
                            $month_end_dt = $clamped_end_day_dt;
                        }

                        $segment_start_day_key = $segment_cursor->format('Y-m-d');
                        $segment_end_day_key = $month_end_dt->format('Y-m-d');

                        $day_iterator = $segment_cursor;
                        while ($day_iterator->getTimestamp() <= $month_end_dt->getTimestamp()) {
                            $day_key = $day_iterator->format('Y-m-d');
                            $ensure_day_bucket($months, $segment_month_key, $day_key);
                            $months[$segment_month_key]['days'][$day_key]['has_multi'] = true;

                            $event_list_reference = &$months[$segment_month_key]['days'][$day_key]['events'];
                            $already_listed = false;
                            foreach ($event_list_reference as $existing_event_entry) {
                                if (isset($existing_event_entry['id']) && $existing_event_entry['id'] === $multi_event_key) {
                                    $already_listed = true;
                                    break;
                                }
                            }
                            if (!$already_listed) {
                                $is_head = ($day_key === $event_head_day_key);
                                $is_tail = ($day_key === $event_tail_day_key);
                                $event_list_reference[] = array(
                                    'id' => $multi_event_key,
                                    'title' => $title,
                                    'time' => $is_head ? __('Toute la journée', 'mj-member') : '',
                                    'cover' => $is_head ? $primary_cover : '',
                                    'cover_sources' => $is_head ? $cover_sources : array(),
                                    'type_label' => $is_head ? $type_label : '',
                                    'type_key' => $event_type_key,
                                    'start_ts' => $day_iterator->getTimestamp(),
                                    'is_multi' => true,
                                    'is_multi_head' => $is_head,
                                    'is_multi_tail' => $is_tail,
                                    'is_multi_middle' => (!$is_head && !$is_tail),
                                    'range_key' => $multi_event_key,
                                    'palette' => $palette,
                                    'permalink' => $permalink,
                                    'accent_color' => isset($palette['base']) ? $palette['base'] : '',
                                );
                            }
                            unset($event_list_reference);

                            $day_iterator = $day_iterator->modify('+1 day');
                        }

                        $months[$segment_month_key]['multi_events'][] = array(
                            'event_key' => $multi_event_key,
                            'title' => $title,
                            'type_label' => $type_label,
                            'type_key' => $event_type_key,
                            'start_day' => $segment_start_day_key,
                            'end_day' => $segment_end_day_key,
                            'start_ts' => $event_start_dt ? $event_start_dt->getTimestamp() : $clamped_start_day_dt->getTimestamp(),
                            'cover' => $primary_cover,
                            'cover_sources' => $cover_sources,
                            'permalink' => $permalink,
                            'palette' => $palette,
                        );

                        $has_any_event = true;

                        $segment_cursor = $month_end_dt->modify('+1 day')->setTime(0, 0, 0);
                    }

                    $pointer_ts = $clamped_start_day_dt->getTimestamp();
                    if ($highlight_next && $pointer_ts >= $now_ts) {
                        $highlight_month_key = wp_date('Y-m', $pointer_ts, $timezone);
                        if (isset($months[$highlight_month_key])) {
                            if ($next_event_pointer === null || $pointer_ts < $next_event_pointer['start_ts']) {
                                $next_event_pointer = array(
                                    'month_key' => $highlight_month_key,
                                    'day_key' => $clamped_start_day_dt->format('Y-m-d'),
                                    'event_key' => $multi_event_key,
                                    'start_ts' => $pointer_ts,
                                );
                            }
                        }
                    }
                }

                continue;
            }

            foreach ($occurrences as $occurrence) {
                if (!is_array($occurrence)) {
                    continue;
                }

                $occurrence_start_raw = isset($occurrence['start']) ? (string) $occurrence['start'] : '';
                if ($occurrence_start_raw === '') {
                    continue;
                }

                $start_ts = isset($occurrence['timestamp']) ? (int) $occurrence['timestamp'] : strtotime($occurrence_start_raw);
                if (!$start_ts) {
                    continue;
                }

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

                $time_label = wp_date(get_option('time_format', 'H:i'), $start_ts, $timezone);
                $occurrence_end_raw = isset($occurrence['end']) ? (string) $occurrence['end'] : '';
                $end_ts_candidate = $occurrence_end_raw !== '' ? strtotime($occurrence_end_raw) : false;
                if ($time_label !== '') {
                    if ($occurrence_end_raw === '' || $end_ts_candidate === false || $end_ts_candidate === $start_ts) {
                        $time_label = sprintf(__('à partir de %s', 'mj-member'), $time_label);
                    }
                }
                if ($occurrence_end_raw === '') {
                    $occurrence_end_raw = $occurrence_start_raw;
                }
                $occurrence_key = $event_id . ':' . $start_ts;

                $months[$month_key]['days'][$day_key]['events'][] = array(
                    'id' => $occurrence_key,
                    'title' => $title,
                    'time' => $time_label,
                    'cover' => $primary_cover,
                    'cover_sources' => $cover_sources,
                    'type_label' => $type_label,
                    'type_key' => $event_type_key,
                    'start_ts' => $start_ts,
                    'palette' => $palette,
                    'permalink' => $permalink,
                    'accent_color' => isset($palette['base']) ? $palette['base'] : '',
                );

                $has_any_event = true;

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
        static $styles_printed = false;
        if (!$styles_printed) {
            $styles_printed = true;
            echo '<style>'
                . '.mj-member-events-calendar{display:flex;flex-direction:column;gap:20px;--mj-events-calendar-nav-bg:#0f172a;--mj-events-calendar-nav-text:#ffffff;--mj-events-calendar-accent:#2563eb;--mj-events-calendar-surface:#ffffff;--mj-events-calendar-border:#e2e8f0;--mj-events-calendar-event-bg:#f8fafc;--mj-events-calendar-event-text:#1f2937;--mj-events-calendar-event-time:#475569;--mj-events-calendar-radius:14px;}'
                . '.mj-member-events-calendar__title{margin:0;font-size:1.6rem;font-weight:700;color:var(--mj-events-calendar-nav-bg);}'
                . '.mj-member-events-calendar__toolbar{display:flex;align-items:center;justify-content:space-between;gap:16px;background:var(--mj-events-calendar-surface);border:1px solid var(--mj-events-calendar-border);border-radius:calc(var(--mj-events-calendar-radius));padding:16px;box-shadow:0 12px 28px rgba(15,23,42,0.06);}'
                . '.mj-member-events-calendar button:hover,.mj-member-events-calendar button:focus{background:inherit;color:inherit;text-decoration:none;}'
                . '.mj-member-events-calendar__toolbar-left{display:flex;align-items:center;gap:16px;flex-wrap:wrap;}'
                . '.mj-member-events-calendar__nav-group{display:inline-flex;align-items:center;gap:12px;background:var(--mj-events-calendar-nav-bg);color:var(--mj-events-calendar-nav-text);border-radius:999px;padding:6px;}'
                . '.mj-member-events-calendar__nav-button{display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border:none;border-radius:999px;background:rgba(255,255,255,0.14);color:inherit;font-size:1.2rem;font-weight:600;cursor:pointer;transition:background 0.2s ease,transform 0.2s ease;}'
                . '.mj-member-events-calendar__nav-button:hover,.mj-member-events-calendar__nav-button:focus{background:rgba(255,255,255,0.28);transform:translateY(-1px);}'
                . '.mj-member-events-calendar__nav-button:disabled{opacity:0.45;cursor:not-allowed;background:rgba(255,255,255,0.12);transform:none;}'
                . '.mj-member-events-calendar__month-chip{display:inline-flex;align-items:center;gap:10px;background:#ffffff;color:var(--mj-events-calendar-nav-bg);border-radius:999px;padding:6px 16px;font-weight:600;font-size:0.95rem;min-width:140px;justify-content:center;}'
                . '.mj-member-events-calendar__toolbar-actions{display:flex;align-items:center;gap:12px;flex-wrap:wrap;}'
                . '.mj-member-events-calendar__filters{display:flex;align-items:center;gap:10px;flex-wrap:wrap;}'
                . '.mj-member-events-calendar__filter{display:inline-flex;align-items:center;gap:8px;padding:6px 12px;border-radius:999px;background:#f1f5f9;color:var(--mj-events-calendar-nav-bg);font-size:0.78rem;font-weight:600;}'
                . '.mj-member-events-calendar__filter input{margin:0;}'
                . '.mj-member-events-calendar__today-button{display:inline-flex;align-items:center;justify-content:center;padding:9px 22px;border-radius:12px;border:1px solid var(--mj-events-calendar-border);background:#ffffff;color:var(--mj-events-calendar-nav-bg);font-weight:600;cursor:pointer;transition:background 0.2s ease,box-shadow 0.2s ease;}'
                . '.mj-member-events-calendar__today-button:hover,.mj-member-events-calendar__today-button:focus{background:#f8fafc;box-shadow:0 4px 12px rgba(15,23,42,0.08);}'
                . '.mj-member-events-calendar__today-button:disabled{opacity:0.6;cursor:not-allowed;box-shadow:none;}'
                . '.mj-member-events-calendar__months{display:flex;flex-direction:column;gap:24px;}'
                . '.mj-member-events-calendar__month{display:none;flex-direction:column;gap:16px;}'
                . '.mj-member-events-calendar__month.is-active{display:flex;}'
                . '.mj-member-events-calendar__month.is-next-event .mj-member-events-calendar__month-heading{color:var(--mj-events-calendar-accent);}'
                . '.mj-member-events-calendar__month-heading{margin:0;font-size:1.2rem;font-weight:700;color:var(--mj-events-calendar-nav-bg);}'
                . '.mj-member-events-calendar__grid-wrapper{overflow:auto;border-radius:calc(var(--mj-events-calendar-radius));box-shadow:0 10px 26px rgba(15,23,42,0.1);}'
                . '.mj-member-events-calendar__weekday-row{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));background:#f1f5f9;color:var(--mj-events-calendar-nav-bg);font-weight:600;text-transform:uppercase;font-size:0.75rem;letter-spacing:0.06em;border:1px solid var(--mj-events-calendar-border);border-bottom:none;border-radius:15px 15px 0 0;overflow:hidden;}'
                . '.mj-member-events-calendar__weekday{padding:12px;border-right:1px solid var(--mj-events-calendar-border);text-align:center;}'
                . '.mj-member-events-calendar__weekday:last-child{border-right:none;}'
                . '.mj-member-events-calendar__weeks{display:flex;flex-direction:column;background:var(--mj-events-calendar-surface);border:1px solid var(--mj-events-calendar-border);border-top:none;border-radius:0 0 calc(var(--mj-events-calendar-radius)) calc(var(--mj-events-calendar-radius));overflow:hidden;min-width:640px;}'
                . '.mj-member-events-calendar__week{display:flex;flex-direction:column;border-top:1px solid var(--mj-events-calendar-border);}'
                . '.mj-member-events-calendar__week:first-child{border-top:none;}'
                . '.mj-member-events-calendar__week-days{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));background:var(--mj-events-calendar-surface);}'
                . '.mj-member-events-calendar__day-cell{min-height:110px;border-right:1px solid var(--mj-events-calendar-border);border-bottom:1px solid var(--mj-events-calendar-border);padding:0;}'
                . '.mj-member-events-calendar__day-cell:nth-child(7n){border-right:none;}'
                . '.mj-member-events-calendar__week:last-child .mj-member-events-calendar__day-cell{border-bottom:none;}'
                . '.mj-member-events-calendar__day-cell.is-padding{background:#f8fafc;}'
                . '.mj-member-events-calendar__day{display:flex;flex-direction:column;gap:8px;min-height:110px;padding:6px;border-radius:12px;background:transparent;transition:background 0.2s ease;}'
                . '.mj-member-events-calendar__day.has-events{background:#f4f7fe;}'
                . '.mj-member-events-calendar__day-number{font-size:0.95rem;font-weight:700;color:var(--mj-events-calendar-nav-bg);}'
                . '.mj-member-events-calendar__day.is-today{background:#edf3fd;}'
                . '.mj-member-events-calendar__day.is-closure{background:#fff7f7;}'
                . '.mj-member-events-calendar__day.is-closure .mj-member-events-calendar__day-number{color:#b91c1c;}'
                . '.mj-member-events-calendar__day.is-filtered-empty{opacity:0.65;}'
                . '.mj-member-events-calendar__events{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:8px;}'
                . '.mj-member-events-calendar__event{margin-top: 0px; background:var(--mj-event-surface,var(--mj-events-calendar-event-bg));border-radius:12px;padding:0;display:flex;flex-direction:column;gap:6px;border:1px solid var(--mj-event-border,transparent);transition:background 0.2s ease,border-color 0.2s ease,box-shadow 0.2s ease;position:relative;overflow:visible;}'
                . '.mj-member-events-calendar__event.is-next{border-color:var(--mj-event-accent,var(--mj-events-calendar-accent));background:var(--mj-event-highlight,#e5ecfd);}'
                . '.mj-member-events-calendar__event.is-multi{margin:0;margin-left:-6px;margin-right:-6px;padding:0;border-radius:0;background:var(--mj-event-range-bg,#dce6fc);border:1px solid var(--mj-event-range-border,#c2d3f9);min-height:64px;display:flex;align-items:center;}'
                . '.mj-member-events-calendar__event.is-multi-head{border-top-left-radius:12px;border-bottom-left-radius:12px;margin-right:-100%;position:relative;z-index:9999;}'
                . '.mj-member-events-calendar__event.is-multi-tail{border-top-right-radius:12px;border-bottom-right-radius:12px;}'
                . '.mj-member-events-calendar__event.is-multi-middle{border-radius:0;}'
                . '.mj-member-events-calendar__event.is-multi:not(.is-multi-head){border-left:none;}'
                . '.mj-member-events-calendar__event.is-multi:not(.is-multi-tail){border:0;}'
                . '.mj-member-events-calendar__event-trigger{display:flex;align-items:center;gap:10px;background:none;border:0;padding:6px 8px;margin:0;text-align:left;width:100%;height:100%;cursor:pointer;color:var(--mj-events-calendar-event-text);text-decoration:none;}'
                . '.mj-member-events-calendar__event:not(.is-multi) .mj-member-events-calendar__event-trigger{flex-direction:column;align-items:flex-start;padding:18px 18px 18px;gap:14px;}'
                . '.mj-member-events-calendar__event-trigger:hover,.mj-member-events-calendar__event-trigger:focus{background:none;color:var(--mj-events-calendar-event-text);text-decoration:none;}'
                . '.mj-member-events-calendar__event.is-multi .mj-member-events-calendar__event-trigger{width:100%;height:100%;padding:0 12px;}'
                . '.mj-member-events-calendar__event.is-multi:not(.is-multi-head) .mj-member-events-calendar__event-trigger{justify-content:center;}'
                . '.mj-member-events-calendar__event-thumb{width:60px;height:60px;border-radius:10px;overflow:hidden;flex-shrink:0; display:block;}'
                . '.mj-member-events-calendar__event:not(.is-multi) .mj-member-events-calendar__event-thumb{margin-right:0;margin-bottom:0px;}'
                . '.mj-member-events-calendar__event-thumb img{display:block;width:100%;height:100%;object-fit:cover;}'
                . '.mj-member-events-calendar__event-copy{display:flex;flex-direction:column;gap:4px;flex:1;min-width:0;line-height:1.2;text-decoration:none;}'
                . '.mj-member-events-calendar__event:not(.is-multi) .mj-member-events-calendar__event-copy{padding-top: 0px;width:100%;}'
                . '.mj-member-events-calendar__event-trigger{transition:transform 0.2s ease;text-decoration:none;}'
                . '.mj-member-events-calendar__event-trigger:hover{transform:translateY(-1px);}'
                . '.mj-member-events-calendar__event.is-closure .mj-member-events-calendar__event-trigger{cursor:default;}'
                . '.mj-member-events-calendar__event.is-closure .mj-member-events-calendar__event-trigger:hover{transform:none;}'
                . '.mj-member-events-calendar__event-title{ border: 0; background: none; padding:0; font-size:0.82rem;font-weight:600;color:var(--mj-events-calendar-event-text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}'
                . '.mj-member-events-calendar__event:not(.is-multi) .mj-member-events-calendar__event-title{position:absolute;top:1px;left:22px;transform:translateX(-18px); border-radius:0px; padding:5px 16px;box-shadow:0 10px 20px rgba(15,23,42,0.15);z-index:5;max-width:calc(100% - 0px); border: 0; background: none; padding:0;}'
                . '.mj-member-events-calendar__event-meta{display:flex;align-items:center;gap:6px;font-size:0.7rem;color:var(--mj-events-calendar-event-time);}'
                . '.mj-member-events-calendar__event-continuation{display:block;width:100%;height:100%;min-height:12px;border-radius:999px;background:none;border:0;}'
                . '.mj-member-events-calendar__event-type{display:inline-flex;align-items:center;gap:4px;font-size:0.66rem;font-weight:600;padding:2px 8px;border-radius:999px;background:var(--mj-event-pill-bg,#d8e3fb);color:var(--mj-event-pill-text,var(--mj-events-calendar-accent));text-transform:uppercase;letter-spacing:0.05em;width:fit-content;}'
                . '.mj-member-events-calendar__event.is-range-hover{background:var(--mj-event-range-bg,#c2d3f9);border-color:var(--mj-event-accent,var(--mj-events-calendar-accent));box-shadow:0 0 0 2px var(--mj-event-pill-bg,#d8e3fb);}'
                . '.mj-member-events-calendar__multi-event.is-range-hover{transform:translateY(-1px);}'
                . '.mj-member-events-calendar__event[data-calendar-type="closure"]{background:#fee2e2;border-color:#fecaca;}'
                . '.mj-member-events-calendar__event[data-calendar-type="closure"] .mj-member-events-calendar__event-type{background:#ffe4e6;color:#be123c;}'
                . '.mj-member-events-calendar__event.is-filtered-out{display:none!important;}'
                . '.mj-member-events-calendar__empty{margin:0;font-size:0.95rem;color:#475569;}'
                . '.mj-member-events-calendar__mobile-list{display:none;flex-direction:column;gap:12px;}'
                . '.mj-member-events-calendar__mobile-day{border:1px solid var(--mj-events-calendar-border);border-radius:12px;background:#ffffff;overflow:hidden;}'
                . '.mj-member-events-calendar__mobile-day.is-filtered-empty{display:none;}'
                . '.mj-member-events-calendar__mobile-summary{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 16px;font-weight:600;color:var(--mj-events-calendar-nav-bg);cursor:pointer;}'
                . '.mj-member-events-calendar__mobile-summary::-webkit-details-marker{display:none;}'
                . '.mj-member-events-calendar__mobile-day[open] .mj-member-events-calendar__mobile-summary{background:#edf2ff;}'
                . '.mj-member-events-calendar__mobile-count{font-size:0.75rem;color:var(--mj-events-calendar-event-time);font-weight:500;}'
                . '.mj-member-events-calendar__mobile-events{list-style:none;margin:0;padding:0 16px 16px;display:flex;flex-direction:column;gap:8px;}'
                . '.mj-member-events-calendar__mobile-event{background:var(--mj-event-surface,#f8fafc);border-radius:12px;border:1px solid var(--mj-event-border,transparent);overflow:hidden;transition:transform 0.2s ease;}'
                . '.mj-member-events-calendar__mobile-event:hover{transform:translateY(-1px);}'
                . '.mj-member-events-calendar__mobile-link{display:flex;gap:12px;align-items:flex-start;padding:10px 12px;color:var(--mj-events-calendar-event-text);text-decoration:none;width:100%;height:100%;}'
                . '.mj-member-events-calendar__mobile-link:hover,.mj-member-events-calendar__mobile-link:focus{color:var(--mj-events-calendar-event-text);text-decoration:none;}'
                . '.mj-member-events-calendar__mobile-link.is-static{cursor:default;}'
                . '.mj-member-events-calendar__mobile-event.is-filtered-out{display:none!important;}'
                . '.mj-member-events-calendar__mobile-pill{display:inline-flex;align-items:center;gap:4px;font-size:0.66rem;font-weight:600;padding:2px 8px;border-radius:999px;background:var(--mj-event-pill-bg,#d8e3fb);color:var(--mj-event-pill-text,var(--mj-events-calendar-accent));text-transform:uppercase;letter-spacing:0.05em;width:fit-content;}'
                . '.mj-member-events-calendar__mobile-title{font-size:0.9rem;font-weight:600;color:var(--mj-events-calendar-event-text);}'
                . '.mj-member-events-calendar__mobile-meta{font-size:0.8rem;color:var(--mj-events-calendar-event-time);}'
                . '.mj-member-events-calendar__mobile-event[data-calendar-type="closure"]{background:#fee2e2;border-color:#fecaca;}'
                . '.mj-member-events-calendar__mobile-event[data-calendar-type="closure"] .mj-member-events-calendar__mobile-pill{background:#ffe4e6;color:#be123c;}'
                . '.mj-member-events-calendar__mobile-body{display:flex;flex-direction:column;gap:4px;flex:1;min-width:0;}'
                . '.mj-member-events-calendar__event-thumb--mobile{flex-shrink:0;border-radius:10px;overflow:hidden;}'
                . '@media (max-width:900px){.mj-member-events-calendar__event:not(.is-multi) .mj-member-events-calendar__event-trigger{padding:16px 16px 18px;gap:12px;}.mj-member-events-calendar__event:not(.is-multi) .mj-member-events-calendar__event-thumb{margin-bottom:0px;}.mj-member-events-calendar__event:not(.is-multi) .mj-member-events-calendar__event-title{ font-size:0.74rem;top:1px;left:18px;transform:translateX(-12px);max-width:calc(100% - 28px);}}
@media (max-width:640px){.mj-member-events-calendar__toolbar{flex-direction:column;align-items:stretch;gap:14px;}.mj-member-events-calendar__toolbar-left{width:100%;justify-content:space-between;gap:12px;}.mj-member-events-calendar__nav-group{width:100%;justify-content:space-between;padding:6px 12px;}.mj-member-events-calendar__nav-button{width:32px;height:32px;}.mj-member-events-calendar__toolbar-actions{width:100%;flex-direction:column;align-items:stretch;gap:12px;}.mj-member-events-calendar__filters{width:100%;flex-direction:column;align-items:stretch;}.mj-member-events-calendar__filter{width:100%;justify-content:flex-start;}.mj-member-events-calendar__today-button{width:100%;}.mj-member-events-calendar__grid-wrapper{display:none;}.mj-member-events-calendar__mobile-list{display:flex;}}'
                . '</style>';
        }

        $instance_thumb_styles = self::build_cover_width_style_block($instance_id, $cover_width_settings);
        if ($instance_thumb_styles !== '') {
            echo '<style>' . $instance_thumb_styles . '</style>';
        }

            AssetsManager::requirePackage('events-calendar');

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

        echo '<div class="mj-member-events-calendar" id="' . esc_attr($instance_id) . '" data-calendar-preferred="0" data-calendar-today="' . esc_attr($today_month_key) . '" data-calendar-count-singular="' . esc_attr($count_singular_label) . '" data-calendar-count-plural="' . esc_attr($count_plural_label) . '" data-calendar-count-empty="' . esc_attr($count_empty_label) . '">';

        if (!empty($settings['title'])) {
            echo '<h3 class="mj-member-events-calendar__title">' . esc_html($settings['title']) . '</h3>';
        }

        echo '<div class="mj-member-events-calendar__toolbar">';
        echo '<div class="mj-member-events-calendar__toolbar-left">';
        echo '<div class="mj-member-events-calendar__nav-group">';
        echo '<button type="button" class="mj-member-events-calendar__nav-button" data-calendar-nav="prev" aria-label="' . esc_attr__('Mois précédent', 'mj-member') . '"><span aria-hidden="true">&lsaquo;</span></button>';
        echo '<span class="mj-member-events-calendar__month-chip" data-calendar-active-label aria-live="polite" aria-atomic="true">' . esc_html($initial_month_label) . '</span>';
        echo '<button type="button" class="mj-member-events-calendar__nav-button" data-calendar-nav="next" aria-label="' . esc_attr__('Mois suivant', 'mj-member') . '"><span aria-hidden="true">&rsaquo;</span></button>';
        echo '</div>';
        echo '</div>';
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
        echo '</div>';

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

                    $day_list_entries[] = array(
                        'day_number' => $day_number,
                        'day_key' => $day_key,
                        'events' => $events_for_day,
                        'is_closure' => !empty($month_data['days'][$day_key]['is_closure']),
                    );

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
                    if (!empty($events_for_day) || !empty($cell_entry['has_multi'])) {
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
                            $event_is_multi = !empty($event_entry['is_multi']);
                            $event_is_multi_head = !empty($event_entry['is_multi_head']);
                            $event_is_multi_tail = !empty($event_entry['is_multi_tail']);
                            if ($event_is_multi) {
                                $event_classes[] = 'is-multi';
                                if ($event_is_multi_head) {
                                    $event_classes[] = 'is-multi-head';
                                }
                                if ($event_is_multi_tail) {
                                    $event_classes[] = 'is-multi-tail';
                                }
                                if (!$event_is_multi_head && !$event_is_multi_tail) {
                                    $event_classes[] = 'is-multi-middle';
                                }
                            }
                            if (!empty($event_entry['is_closure'])) {
                                $event_classes[] = 'is-closure';
                            }
                            if ($next_event_pointer && $next_event_pointer['event_key'] === $event_entry['id']) {
                                $event_classes[] = 'is-next';
                            }

                            $event_range_key = isset($event_entry['range_key']) && $event_entry['range_key'] !== '' ? (string) $event_entry['range_key'] : (string) $event_entry['id'];
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
                            $trigger_attributes = ' class="mj-member-events-calendar__event-trigger"' . ($event_is_multi ? ' data-calendar-range="' . esc_attr($event_range_key) . '"' : '');
                            if ($event_is_closure) {
                                echo '<div' . $trigger_attributes . '>';
                            } else {
                                echo '<a' . $trigger_attributes . ' href="' . esc_url($event_href) . '">';
                            }
                            if (!$event_is_multi || $event_is_multi_head) {
                                if (!empty($event_entry['cover'])) {
                                    $cover_sources = array();
                                    if (isset($event_entry['cover_sources']) && is_array($event_entry['cover_sources'])) {
                                        $cover_sources = $event_entry['cover_sources'];
                                    }
                                    $fallback_cover = $event_entry['cover'];
                                    if ($fallback_cover === '' && isset($cover_sources['fallback']) && $cover_sources['fallback'] !== '') {
                                        $fallback_cover = $cover_sources['fallback'];
                                    }
                                    echo '<span class="mj-member-events-calendar__event-thumb">';
                                    echo '<picture>';
                                    if (!empty($cover_sources['desktop'])) {
                                        echo '<source media="(min-width: 901px)" srcset="' . esc_url($cover_sources['desktop']) . '" />';
                                    }
                                    if (!empty($cover_sources['tablet'])) {
                                        echo '<source media="(min-width: 641px)" srcset="' . esc_url($cover_sources['tablet']) . '" />';
                                    }
                                    if (!empty($cover_sources['mobile'])) {
                                        echo '<source media="(max-width: 640px)" srcset="' . esc_url($cover_sources['mobile']) . '" />';
                                    }
                                    echo '<img src="' . esc_url($fallback_cover) . '" alt="' . esc_attr($event_entry['title']) . '" loading="lazy" />';
                                    echo '</picture>';
                                    echo '</span>';
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
                            } else {
                                echo '<span class="mj-member-events-calendar__event-continuation" aria-hidden="true"></span>';
                            }
                            if ($event_is_multi && !$event_is_multi_head) {
                                echo '<span class="screen-reader-text">' . esc_html(sprintf(__('Suite de %s', 'mj-member'), $event_entry['title'])) . '</span>';
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
                        echo '<span class="mj-member-events-calendar__mobile-title">' . esc_html($mobile_event['title']) . '</span>';
                        if (!empty($mobile_event['time'])) {
                            echo '<span class="mj-member-events-calendar__mobile-meta">' . esc_html($mobile_event['time']) . '</span>';
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
