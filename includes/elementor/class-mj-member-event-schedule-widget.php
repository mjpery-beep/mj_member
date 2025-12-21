<?php

if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;
use Mj\Member\Classes\Crud\MjEvents;
use Mj\Member\Core\Config;

class Mj_Member_Elementor_Event_Schedule_Widget extends Widget_Base {
    use Mj_Member_Elementor_Widget_Visibility;

    public function get_name() {
        return 'mj-member-event-schedule';
    }

    public function get_title() {
        return __('Horaire événement MJ', 'mj-member');
    }

    public function get_icon() {
        return 'eicon-clock-o';
    }

    public function get_categories() {
        return array('mj-member');
    }

    public function get_keywords() {
        return array('mj', 'horaire', 'schedule', 'event', 'heure', 'date', 'calendrier');
    }

    protected function register_controls() {
        $this->register_content_controls();
        $this->register_style_controls();
        $this->register_visibility_controls();
    }

    private function register_content_controls() {
        $this->start_controls_section(
            'section_content_main',
            array(
                'label' => __('Contenu', 'mj-member'),
            )
        );

        $event_options = $this->get_events_options();

        $this->add_control(
            'event_id',
            array(
                'label' => __('Événement', 'mj-member'),
                'type' => Controls_Manager::SELECT2,
                'label_block' => true,
                'options' => $event_options,
                'default' => '',
                'description' => __('Sélectionnez l\'événement dont vous souhaitez afficher l\'horaire.', 'mj-member'),
            )
        );

        $this->add_control(
            'title',
            array(
                'label' => __('Titre personnalisé', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Horaires', 'mj-member'),
                'placeholder' => __('Titre affiché au-dessus du planning', 'mj-member'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'display_title',
            array(
                'label' => __('Afficher le titre', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->add_control(
            'max_occurrences',
            array(
                'label' => __('Nombre maximum d\'occurrences', 'mj-member'),
                'type' => Controls_Manager::NUMBER,
                'min' => 1,
                'max' => 200,
                'default' => 20,
                'description' => __('Limite le nombre de créneaux affichés pour les planifications longues.', 'mj-member'),
            )
        );

        $this->add_control(
            'show_past',
            array(
                'label' => __('Inclure les créneaux passés', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => '',
            )
        );

        $this->add_control(
            'date_format',
            array(
                'label' => __('Format de date', 'mj-member'),
                'type' => Controls_Manager::SELECT,
                'options' => array(
                    'full' => __('Complet (20 janvier 2025)', 'mj-member'),
                    'short' => __('Court (20/01/2025)', 'mj-member'),
                    'medium' => __('Moyen (20 jan. 2025)', 'mj-member'),
                    'day_only' => __('Jour & mois (20 janvier)', 'mj-member'),
                ),
                'default' => 'full',
            )
        );

        $this->add_control(
            'time_format',
            array(
                'label' => __('Format d\'heure', 'mj-member'),
                'type' => Controls_Manager::SELECT,
                'options' => array(
                    '24h' => __('24h (14:30)', 'mj-member'),
                    '12h' => __('12h (2:30 PM)', 'mj-member'),
                ),
                'default' => '24h',
            )
        );

        $this->add_control(
            'show_icons',
            array(
                'label' => __('Afficher les pictogrammes', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->add_control(
            'highlight_today',
            array(
                'label' => __('Mettre en avant la date du jour', 'mj-member'),
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
                'label' => __('Message si aucun horaire', 'mj-member'),
                'type' => Controls_Manager::TEXTAREA,
                'default' => __('Aucun horaire disponible pour cet événement.', 'mj-member'),
                'rows' => 2,
            )
        );

        $this->end_controls_section();

        $non_recurring_layout_options = array(
            'list' => __('Liste verticale', 'mj-member'),
            'timeline' => __('Timeline verticale', 'mj-member'),
            'card' => __('Cartes détaillées', 'mj-member'),
            'chips' => __('Pastilles compactes', 'mj-member'),
            'table' => __('Tableau récapitulatif', 'mj-member'),
        );

        $recurring_layout_options = array(
            'cards' => __('Cartes par jour', 'mj-member'),
            'table' => __('Tableau hebdomadaire', 'mj-member'),
            'chips' => __('Pastilles compactes', 'mj-member'),
        );

        $this->start_controls_section(
            'section_content_layouts',
            array(
                'label' => __('Affichages par planification', 'mj-member'),
            )
        );

        $this->add_control(
            'layout_mode_fallback',
            array(
                'label' => __('Affichage par défaut', 'mj-member'),
                'type' => Controls_Manager::SELECT,
                'options' => $non_recurring_layout_options,
                'default' => 'list',
                'description' => __('Utilisé lorsque le type de planification ne peut pas être déterminé.', 'mj-member'),
            )
        );

        $this->add_control(
            'layout_mode_fixed',
            array(
                'label' => __('Événement à date fixe', 'mj-member'),
                'type' => Controls_Manager::SELECT,
                'options' => $non_recurring_layout_options,
                'default' => 'card',
            )
        );

        $this->add_control(
            'layout_mode_range',
            array(
                'label' => __('Plage de dates (multi-jours)', 'mj-member'),
                'type' => Controls_Manager::SELECT,
                'options' => $non_recurring_layout_options,
                'default' => 'timeline',
            )
        );

        $this->add_control(
            'layout_mode_series',
            array(
                'label' => __('Série de dates personnalisées', 'mj-member'),
                'type' => Controls_Manager::SELECT,
                'options' => $non_recurring_layout_options,
                'default' => 'list',
            )
        );

        $this->add_control(
            'layout_mode_recurring',
            array(
                'label' => __('Occurence récurrente (hebdomadaire)', 'mj-member'),
                'type' => Controls_Manager::SELECT,
                'options' => $recurring_layout_options,
                'default' => 'cards',
            )
        );

        $this->end_controls_section();
    }

    private function register_style_controls() {
        // Conteneur principal
        $this->start_controls_section(
            'section_style_container',
            array(
                'label' => __('Conteneur', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'container_background',
            array(
                'label' => __('Arrière-plan', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-event-schedule' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_responsive_control(
            'container_padding',
            array(
                'label' => __('Marge interne', 'mj-member'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => array('px', 'em', '%'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-event-schedule' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            array(
                'name' => 'container_border',
                'selector' => '{{WRAPPER}} .mj-event-schedule',
            )
        );

        $this->add_control(
            'container_border_radius',
            array(
                'label' => __('Rayon de bordure', 'mj-member'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => array('px', '%'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-event-schedule' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            array(
                'name' => 'container_box_shadow',
                'selector' => '{{WRAPPER}} .mj-event-schedule',
            )
        );

        $this->end_controls_section();

        // Titre
        $this->start_controls_section(
            'section_style_title',
            array(
                'label' => __('Titre', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
                'condition' => array('display_title' => 'yes'),
            )
        );

        $this->add_control(
            'title_color',
            array(
                'label' => __('Couleur', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-event-schedule__title' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            array(
                'name' => 'title_typography',
                'selector' => '{{WRAPPER}} .mj-event-schedule__title',
            )
        );

        $this->add_responsive_control(
            'title_margin',
            array(
                'label' => __('Marge', 'mj-member'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => array('px', 'em'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-event-schedule__title' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->end_controls_section();

        // Créneaux standard (liste / cartes / timeline)
        $this->start_controls_section(
            'section_style_entries',
            array(
                'label' => __('Créneaux (liste & cartes)', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $entry_selector = '{{WRAPPER}} .mj-event-schedule__item, {{WRAPPER}} .mj-event-schedule__card';
        $this->add_control(
            'entry_background',
            array(
                'label' => __('Arrière-plan', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    $entry_selector => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'entry_text_color',
            array(
                'label' => __('Texte principal', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-event-schedule__day-text, {{WRAPPER}} .mj-event-schedule__time-text' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            array(
                'name' => 'entry_day_typo',
                'label' => __('Typographie jour', 'mj-member'),
                'selector' => '{{WRAPPER}} .mj-event-schedule__day-text',
            )
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            array(
                'name' => 'entry_time_typo',
                'label' => __('Typographie heure', 'mj-member'),
                'selector' => '{{WRAPPER}} .mj-event-schedule__time-text',
            )
        );

        $this->add_responsive_control(
            'entry_padding',
            array(
                'label' => __('Marge interne', 'mj-member'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => array('px', 'em'),
                'selectors' => array(
                    $entry_selector => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'entry_spacing',
            array(
                'label' => __('Espacement vertical', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px', 'em'),
                'range' => array(
                    'px' => array('min' => 0, 'max' => 60),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-event-schedule__list > * + *' => 'margin-top: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            array(
                'name' => 'entry_border',
                'selector' => $entry_selector,
            )
        );

        $this->add_control(
            'entry_border_radius',
            array(
                'label' => __('Angles arrondis', 'mj-member'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => array('px', '%'),
                'selectors' => array(
                    $entry_selector => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'entry_today_highlight',
            array(
                'label' => __('Couleur de mise en avant du jour J', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-event-schedule__item--today, {{WRAPPER}} .mj-event-schedule__card--today, {{WRAPPER}} .mj-event-schedule__chip--today' => 'box-shadow: 0 0 0 2px {{VALUE}};',
                ),
            )
        );

        $this->end_controls_section();

        // Pastilles & tableau
        $this->start_controls_section(
            'section_style_compact',
            array(
                'label' => __('Pastilles & tableau', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'chip_background',
            array(
                'label' => __('Arrière-plan des pastilles', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-event-schedule__chip' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'chip_text_color',
            array(
                'label' => __('Texte des pastilles', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-event-schedule__chip' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'table_header_background',
            array(
                'label' => __('En-tête du tableau', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-event-schedule__table thead th' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'table_border_color',
            array(
                'label' => __('Bordures du tableau', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-event-schedule__table, {{WRAPPER}} .mj-event-schedule__table th, {{WRAPPER}} .mj-event-schedule__table td' => 'border-color: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_section();

        // Récurrence hebdomadaire
        $this->start_controls_section(
            'section_style_recurring',
            array(
                'label' => __('Créneaux récurrents', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'recurring_item_background',
            array(
                'label' => __('Arrière-plan', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-event-schedule__recurring-item' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'recurring_day_color',
            array(
                'label' => __('Couleur du jour', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-event-schedule__recurring-day' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            array(
                'name' => 'recurring_day_typo',
                'selector' => '{{WRAPPER}} .mj-event-schedule__recurring-day',
            )
        );

        $this->add_control(
            'recurring_time_color',
            array(
                'label' => __('Couleur des heures', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-event-schedule__recurring-time' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_section();
    }

    private function get_events_options() {
        $options = array('' => __('— Sélectionner un événement —', 'mj-member'));

        if (!class_exists('Mj\\Member\\Classes\\Crud\\MjEvents')) {
            return $options;
        }

        $events = MjEvents::get_all(array(
            'statuses' => array('actif', 'brouillon'),
            'orderby' => 'date_debut',
            'order' => 'DESC',
            'limit' => 200,
        ));

        foreach ($events as $event) {
            $event_id = isset($event->id) ? (int) $event->id : 0;
            $title = isset($event->title) ? $event->title : __('(Sans titre)', 'mj-member');
            $date_debut = isset($event->date_debut) ? $event->date_debut : '';
            
            $label = $title;
            if ($date_debut !== '') {
                $formatted_date = wp_date('d/m/Y', strtotime($date_debut));
                $label = sprintf('%s (%s)', $title, $formatted_date);
            }

            $options[$event_id] = $label;
        }

        return $options;
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $this->apply_visibility_to_wrapper($settings, 'mj-event-schedule-widget');

        $template_path = trailingslashit(Config::path()) . 'includes/templates/elementor/event_schedule.php';
        if (!file_exists($template_path)) {
            echo '<div class="mj-member-account-warning">' . esc_html__('Le template du widget horaire est introuvable.', 'mj-member') . '</div>';
            return;
        }

        $event_id = isset($settings['event_id']) ? (int) $settings['event_id'] : 0;
        $title = isset($settings['title']) ? (string) $settings['title'] : '';
        $display_title = isset($settings['display_title']) && $settings['display_title'] === 'yes';
        $max_occurrences = isset($settings['max_occurrences']) ? max(1, (int) $settings['max_occurrences']) : 20;
        $show_past = isset($settings['show_past']) && $settings['show_past'] === 'yes';
        $date_format = isset($settings['date_format']) ? $settings['date_format'] : 'full';
        $time_format = isset($settings['time_format']) ? $settings['time_format'] : '24h';
        $layout_mode_fallback = isset($settings['layout_mode_fallback']) ? $settings['layout_mode_fallback'] : 'list';
        $layout_mode_fixed = isset($settings['layout_mode_fixed']) ? $settings['layout_mode_fixed'] : 'card';
        $layout_mode_range = isset($settings['layout_mode_range']) ? $settings['layout_mode_range'] : 'timeline';
        $layout_mode_series = isset($settings['layout_mode_series']) ? $settings['layout_mode_series'] : 'list';
        $layout_mode_recurring = isset($settings['layout_mode_recurring']) ? $settings['layout_mode_recurring'] : 'cards';
        $show_icons = !isset($settings['show_icons']) || $settings['show_icons'] === 'yes';
        $highlight_today = !isset($settings['highlight_today']) || $settings['highlight_today'] === 'yes';
        $empty_message = isset($settings['empty_message']) ? (string) $settings['empty_message'] : '';

        $is_preview = $this->is_elementor_preview_mode();

        $template_data = array(
            'event_id' => $event_id,
            'title' => $title,
            'display_title' => $display_title,
            'max_occurrences' => $max_occurrences,
            'show_past' => $show_past,
            'date_format' => $date_format,
            'time_format' => $time_format,
            'layout_mode_fallback' => $layout_mode_fallback,
            'layout_mode_fixed' => $layout_mode_fixed,
            'layout_mode_range' => $layout_mode_range,
            'layout_mode_series' => $layout_mode_series,
            'layout_mode_recurring' => $layout_mode_recurring,
            'show_icons' => $show_icons,
            'highlight_today' => $highlight_today,
            'empty_message' => $empty_message,
            'is_preview' => $is_preview,
        );

        $template_data = apply_filters('mj_member_event_schedule_widget_template_data', $template_data, $settings, $this);

        include $template_path;
    }

    private function is_elementor_preview_mode() {
        if (did_action('elementor/loaded')) {
            $elementor = \Elementor\Plugin::$instance;
            if ($elementor && isset($elementor->editor) && method_exists($elementor->editor, 'is_edit_mode')) {
                return (bool) $elementor->editor->is_edit_mode();
            }
        }

        return false;
    }
}
