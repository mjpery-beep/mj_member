<?php

if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Controls_Manager;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Utils;
use Elementor\Widget_Base;
use Mj\Member\Core\AssetsManager;

class Mj_Member_Elementor_Events_Widget extends Widget_Base {
    use Mj_Member_Elementor_Widget_Visibility;

    public function get_name() {
        return 'mj-member-events-list';
    }

    public function get_title() {
        return __('Événements MJ', 'mj-member');
    }

    public function get_icon() {
        return 'eicon-post-list';
    }

    public function get_categories() {
        return array('mj-member');
    }

    public function get_keywords() {
        return array('mj', 'events', 'stages', 'agenda', 'calendar');
    }

    public function get_script_depends() {
        return array('mj-member-events-widget');
    }

    public function get_style_depends() {
        if (function_exists('mj_member_output_events_widget_styles')) {
            mj_member_output_events_widget_styles();
        }
        return array('mj-member-events-widget');
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
                'default' => __('Nos prochains événements', 'mj-member'),
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
            'article_filter_mode',
            array(
                'label' => __('Article associé', 'mj-member'),
                'type' => Controls_Manager::SELECT,
                'default' => 'any',
                'options' => array(
                    'any' => __('Tous les événements', 'mj-member'),
                    'with_article' => __('Uniquement avec article', 'mj-member'),
                    'without_article' => __('Sans article associé', 'mj-member'),
                ),
            )
        );

        $article_choices = function_exists('mj_member_get_event_article_choices') ? mj_member_get_event_article_choices() : array();
        $this->add_control(
            'article_ids',
            array(
                'label' => __('Limiter aux articles', 'mj-member'),
                'type' => Controls_Manager::SELECT2,
                'multiple' => true,
                'label_block' => true,
                'options' => $article_choices,
                'description' => __('Sélectionnez un ou plusieurs articles liés aux événements.', 'mj-member'),
                'condition' => array('article_filter_mode!' => 'without_article'),
            )
        );

        $this->add_control(
            'card_layout',
            array(
                'label' => __("Style d'affichage", 'mj-member'),
                'type' => Controls_Manager::SELECT,
                'options' => array(
                    'standard' => __('Carte visuelle', 'mj-member'),
                    'compact' => __('Liste compacte', 'mj-member'),
                    'horizontal' => __('Carte horizontale', 'mj-member'),
                ),
                'default' => 'standard',
            )
        );

        $this->add_control(
            'wide_mode',
            array(
                'label' => __('Mode large (1 carte par ligne)', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'no',
                'description' => __('Étend la carte avec une grande vignette et force une présentation sur une seule colonne.', 'mj-member'),
            )
        );

        $this->add_control(
            'show_cover',
            array(
                'label' => __('Afficher la vignette', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->add_control(
            'cover_ratio',
            array(
                'label' => __('Ratio de la vignette', 'mj-member'),
                'type' => Controls_Manager::SELECT,
                'options' => array(
                    '16-9' => '16:9',
                    '4-3' => '4:3',
                    '1-1' => '1:1',
                    'auto' => __('Automatique', 'mj-member'),
                ),
                'default' => '16-9',
                'condition' => array('show_cover' => 'yes'),
            )
        );

        $this->add_control(
            'include_past',
            array(
                'label' => __('Inclure les événements passés', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'no',
            )
        );

        $this->add_control(
            'show_badge',
            array(
                'label' => __('Afficher le badge de type', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->add_control(
            'show_description',
            array(
                'label' => __("Afficher l'aperçu", 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->add_control(
            'show_next_dates',
            array(
                'label' => __('Afficher les prochaines dates', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
                'description' => __('Affiche un résumé des prochaines occurrences lorsque l’événement est récurrent.', 'mj-member'),
            )
        );

        $this->add_control(
            'show_location',
            array(
                'label' => __('Afficher le lieu', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->add_control(
            'price_display_mode',
            array(
                'label' => __('Tarif', 'mj-member'),
                'type' => Controls_Manager::SELECT,
                'options' => array(
                    'show' => __('Afficher', 'mj-member'),
                    'hide_zero' => __('Afficher sauf si 0 €', 'mj-member'),
                    'hide' => __('Masquer', 'mj-member'),
                ),
                'default' => 'show',
            )
        );

        $this->add_control(
            'show_price',
            array(
                'label' => __('Afficher le tarif (hérité)', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
                'condition' => array('price_display_mode' => ''),
            )
        );

        $this->add_control(
            'hide_price_if_zero',
            array(
                'label' => __('Masquer si tarif à 0 € (hérité)', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
                'condition' => array('price_display_mode' => ''),
            )
        );

        $this->add_control(
            'price_prefix',
            array(
                'label' => __('Préfixe du tarif', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'placeholder' => __('Tarif :', 'mj-member'),
                'default' => __('Tarif :', 'mj-member'),
                'condition' => array('price_display_mode' => array('show', 'hide_zero')),
            )
        );

        $this->add_control(
            'empty_message',
            array(
                'label' => __('Message si aucun événement', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Aucun événement disponible pour le moment.', 'mj-member'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'fallback_image',
            array(
                'label' => __('Image par défaut', 'mj-member'),
                'type' => Controls_Manager::MEDIA,
                'default' => array(
                    'url' => Utils::get_placeholder_image_src(),
                ),
            )
        );

        $this->end_controls_section();

        $this->register_visibility_controls();

        $this->start_controls_section(
            'section_style_header',
            array(
                'label' => __('En-tête', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'title_alignment',
            array(
                'label' => __('Alignement du titre', 'mj-member'),
                'type' => Controls_Manager::CHOOSE,
                'options' => array(
                    'left' => array('title' => __('Gauche', 'mj-member'), 'icon' => 'eicon-text-align-left'),
                    'center' => array('title' => __('Centre', 'mj-member'), 'icon' => 'eicon-text-align-center'),
                    'right' => array('title' => __('Droite', 'mj-member'), 'icon' => 'eicon-text-align-right'),
                ),
                'default' => 'left',
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-events__title' => 'text-align: {{VALUE}};',
                ),
                'condition' => array('display_title' => 'yes'),
            )
        );

        $this->add_control(
            'title_color',
            array(
                'label' => __('Couleur du titre', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'global' => array('default' => Global_Colors::COLOR_PRIMARY),
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-events' => '--mj-events-title-color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            array(
                'name' => 'widget_title_typography',
                'label' => __('Typographie du titre', 'mj-member'),
                'global' => array('default' => Global_Typography::TYPOGRAPHY_PRIMARY),
                'selector' => '{{WRAPPER}} .mj-member-events__title',
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
            'widget_background',
            array(
                'label' => __('Fond du widget', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-events' => '--mj-events-surface: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'card_background',
            array(
                'label' => __('Couleur de fond des cartes', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'global' => array('default' => Global_Colors::COLOR_SECONDARY),
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-events' => '--mj-events-card-bg: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'card_border_color',
            array(
                'label' => __('Couleur de bordure', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'global' => array('default' => Global_Colors::COLOR_TEXT),
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-events' => '--mj-events-border: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            array(
                'name' => 'card_border_box',
                'selector' => '{{WRAPPER}} .mj-member-events__item',
            )
        );

        $this->add_responsive_control(
            'card_padding',
            array(
                'label' => __('Marge interne', 'mj-member'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => array('px', 'em', '%'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-events__item-body' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'card_radius',
            array(
                'label' => __('Arrondi des cartes', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'range' => array(
                    'px' => array('min' => 0, 'max' => 48),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-events' => '--mj-events-radius: {{SIZE}}{{UNIT}};',
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
                    '{{WRAPPER}} .mj-member-events' => '--mj-events-accent: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'accent_contrast_color',
            array(
                'label' => __('Texte sur accent', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'global' => array('default' => Global_Colors::COLOR_TEXT),
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-events' => '--mj-events-accent-contrast: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_typography',
            array(
                'label' => __('Texte', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            array(
                'name' => 'title_typography',
                'label' => __('Titre de la carte', 'mj-member'),
                'global' => array('default' => Global_Typography::TYPOGRAPHY_PRIMARY),
                'selector' => '{{WRAPPER}} .mj-member-events__item-title, {{WRAPPER}} .mj-member-events__item-title a',
            )
        );

        $this->add_control(
            'card_title_color',
            array(
                'label' => __('Couleur du titre', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'global' => array('default' => Global_Colors::COLOR_TEXT),
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-events' => '--mj-events-card-title: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            array(
                'name' => 'meta_typography',
                'label' => __('Métadonnées', 'mj-member'),
                'global' => array('default' => Global_Typography::TYPOGRAPHY_TEXT),
                'selector' => '{{WRAPPER}} .mj-member-events__recurring-summary',
            )
        );

        $this->add_control(
            'content_color',
            array(
                'label' => __('Couleur du texte', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-events' => '--mj-events-text: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            array(
                'name' => 'excerpt_typography',
                'label' => __('Résumé', 'mj-member'),
                'global' => array('default' => Global_Typography::TYPOGRAPHY_TEXT),
                'selector' => '{{WRAPPER}} .mj-member-events__excerpt',
                'condition' => array('show_description' => 'yes'),
            )
        );

        $this->add_control(
            'excerpt_color',
            array(
                'label' => __("Couleur de l'aperçu", 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'global' => array('default' => Global_Colors::COLOR_TEXT),
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-events' => '--mj-events-excerpt: {{VALUE}};',
                ),
                'condition' => array('show_description' => 'yes'),
            )
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_media',
            array(
                'label' => __('Visuel', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_responsive_control(
            'cover_min_height',
            array(
                'label' => __('Hauteur minimale', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'range' => array(
                    'px' => array('min' => 120, 'max' => 420),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-events' => '--mj-events-cover-min: {{SIZE}}{{UNIT}};',
                ),
                'condition' => array('show_cover' => 'yes'),
            )
        );

        $this->add_responsive_control(
            'cover_radius',
            array(
                'label' => __('Arrondi de la vignette', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'range' => array(
                    'px' => array('min' => 0, 'max' => 48),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-events' => '--mj-events-cover-radius: {{SIZE}}{{UNIT}};',
                ),
                'condition' => array('show_cover' => 'yes'),
            )
        );

        $this->end_controls_section();

    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $this->apply_visibility_to_wrapper($settings, 'mj-member-events-widget');
        $is_preview = $this->is_elementor_preview_mode();

        if (!function_exists('mj_member_get_public_events')) {
            echo '<div class="mj-member-events__warning">' . esc_html__('Le module MJ Member doit être actif pour utiliser ce widget.', 'mj-member') . '</div>';
            return;
        }

        $statuses = isset($settings['statuses']) ? (array) $settings['statuses'] : array();
        $types = isset($settings['types']) ? (array) $settings['types'] : array();
        $limit = isset($settings['max_items']) ? (int) $settings['max_items'] : 6;
        $orderby = isset($settings['orderby']) ? $settings['orderby'] : 'date_debut';
        $order = isset($settings['order']) ? $settings['order'] : 'DESC';
        $include_past = isset($settings['include_past']) && $settings['include_past'] === 'yes';
        $article_filter_mode = isset($settings['article_filter_mode']) ? sanitize_key($settings['article_filter_mode']) : 'any';
        if (!in_array($article_filter_mode, array('any', 'with_article', 'without_article'), true)) {
            $article_filter_mode = 'any';
        }

        $article_ids_setting = isset($settings['article_ids']) ? (array) $settings['article_ids'] : array();
        $article_ids = array();
        foreach ($article_ids_setting as $article_candidate) {
            $article_candidate = (int) $article_candidate;
            if ($article_candidate <= 0) {
                continue;
            }
            $article_ids[$article_candidate] = $article_candidate;
        }
        $article_ids = array_values($article_ids);
        if ($article_filter_mode === 'without_article') {
            $article_ids = array();
        }

        $query_arguments = array(
            'statuses' => $statuses,
            'types' => $types,
            'limit' => $limit,
            'orderby' => $orderby,
            'order' => $order,
            'include_past' => $include_past,
        );

        if (!empty($article_ids)) {
            $query_arguments['article_ids'] = $article_ids;
        }

        $events = mj_member_get_public_events($query_arguments);

        if ($is_preview && empty($events)) {
            $events = $this->build_preview_events(
                $settings,
                array(
                    'limit' => $limit,
                    'statuses' => $statuses,
                    'types' => $types,
                    'article_filter_mode' => $article_filter_mode,
                    'article_ids' => $article_ids,
                    'include_past' => $include_past,
                )
            );
        }

        if ($article_filter_mode !== 'any' && !empty($events)) {
            $events = array_values(
                array_filter(
                    $events,
                    static function ($event) use ($article_filter_mode) {
                        if (!is_array($event)) {
                            return false;
                        }

                        $has_article = !empty($event['article_id']) || !empty($event['article_permalink']);
                        if ($article_filter_mode === 'with_article') {
                            return $has_article;
                        }

                        return !$has_article;
                    }
                )
            );
        }

        $wide_mode = isset($settings['wide_mode']) && $settings['wide_mode'] === 'yes';

        $title = isset($settings['title']) ? $settings['title'] : '';
        $display_title = !isset($settings['display_title']) || $settings['display_title'] === 'yes';
        $empty_message = isset($settings['empty_message']) ? $settings['empty_message'] : __('Aucun événement disponible pour le moment.', 'mj-member');
        $show_description = isset($settings['show_description']) && $settings['show_description'] === 'yes';
        $show_next_dates = !isset($settings['show_next_dates']) || $settings['show_next_dates'] === 'yes';
        $show_location = isset($settings['show_location']) && $settings['show_location'] === 'yes';
        $layout = isset($settings['layout']) ? $settings['layout'] : 'grid';
        $card_layout = isset($settings['card_layout']) ? $settings['card_layout'] : 'standard';
        $allowed_card_layouts = array('standard', 'compact', 'horizontal');
        if (!in_array($card_layout, $allowed_card_layouts, true)) {
            $card_layout = 'standard';
        }

        $card_title_tag = isset($settings['card_title_tag']) ? strtolower((string) $settings['card_title_tag']) : 'h4';
        $allowed_title_tags = array('h2', 'h3', 'h4', 'h5', 'p');
        if (!in_array($card_title_tag, $allowed_title_tags, true)) {
            $card_title_tag = 'h4';
        }

        $show_cover = !isset($settings['show_cover']) || $settings['show_cover'] === 'yes';
        $cover_ratio = isset($settings['cover_ratio']) ? sanitize_key($settings['cover_ratio']) : '16-9';
        $allowed_cover_ratios = array('16-9', '4-3', '1-1', 'auto');
        if (!in_array($cover_ratio, $allowed_cover_ratios, true)) {
            $cover_ratio = '16-9';
        }

        $show_badge = !isset($settings['show_badge']) || $settings['show_badge'] === 'yes';

        $price_display_mode = isset($settings['price_display_mode']) ? sanitize_key($settings['price_display_mode']) : '';
        $allowed_price_modes = array('show', 'hide_zero', 'hide');
        $legacy_show_price = !isset($settings['show_price']) || $settings['show_price'] === 'yes';
        $legacy_hide_zero = !isset($settings['hide_price_if_zero']) || $settings['hide_price_if_zero'] === 'yes';
        if (!in_array($price_display_mode, $allowed_price_modes, true)) {
            if ($legacy_show_price) {
                $price_display_mode = $legacy_hide_zero ? 'hide_zero' : 'show';
            } else {
                $price_display_mode = 'hide';
            }
        }

        $price_prefix = isset($settings['price_prefix']) ? sanitize_text_field((string) $settings['price_prefix']) : __('Tarif :', 'mj-member');

        $fallback_url = '';
        if (!empty($settings['fallback_image']['id'])) {
            $fallback_url = wp_get_attachment_image_url((int) $settings['fallback_image']['id'], 'large');
        }
        if (!$fallback_url && !empty($settings['fallback_image']['url'])) {
            $fallback_url = esc_url_raw($settings['fallback_image']['url']);
        }

        $type_labels = method_exists('MjEvents', 'get_type_labels') ? MjEvents::get_type_labels() : array();

        $instance_id = wp_unique_id('mj-member-events-');
        $root_classes = array('mj-member-events');
        if ($wide_mode) {
            $root_classes[] = 'is-wide';
        }

        echo '<div class="' . esc_attr(implode(' ', $root_classes)) . '" data-mj-events-root="' . esc_attr($instance_id) . '">';
        if ($display_title && $title !== '') {
            echo '<h3 class="mj-member-events__title">' . esc_html($title) . '</h3>';
        }

        if (empty($events)) {
            echo '<p class="mj-member-events__empty">' . esc_html($empty_message) . '</p>';
            echo '</div>';
            return;
        }

        AssetsManager::requirePackage('events-widget', array('instance' => $instance_id));

        $grid_classes = array('mj-member-events__grid');
        $grid_classes[] = $layout === 'list' ? 'is-list' : 'is-grid';
        echo '<div class="' . esc_attr(implode(' ', $grid_classes)) . '">';

        foreach ($events as $event) {
            $cover_url = '';
            if (!empty($event['cover_url'])) {
                $cover_url = esc_url($event['cover_url']);
            } elseif (!empty($event['article_cover_url'])) {
                $cover_url = esc_url($event['article_cover_url']);
            } elseif ($fallback_url) {
                $cover_url = esc_url($fallback_url);
            }
            $resolved_layout = $card_layout;
            if ($resolved_layout === 'horizontal' && $cover_url === '') {
                $resolved_layout = 'standard';
            }

            $type_label = '';
            if (!empty($event['type']) && isset($type_labels[$event['type']])) {
                $type_label = $type_labels[$event['type']];
            } elseif (!empty($event['type'])) {
                $type_label = ucfirst($event['type']);
            }

            $occurrence_preview = function_exists('mj_member_prepare_event_occurrences_preview')
                ? mj_member_prepare_event_occurrences_preview(
                    $event,
                    array(
                        'max' => 4,
                        'include_past' => false,
                        'fetch_limit' => 12,
                    )
                )
                : array(
                    'items' => array(),
                    'next' => null,
                    'remaining' => 0,
                    'has_multiple' => false,
                );

            $occurrence_items = isset($occurrence_preview['items']) && is_array($occurrence_preview['items'])
                ? $occurrence_preview['items']
                : array();
            $occurrence_remaining = isset($occurrence_preview['remaining']) ? (int) $occurrence_preview['remaining'] : 0;
            $event_has_multiple_occurrences = !empty($occurrence_preview['has_multiple']);

            $recurring_summary_raw = function_exists('mj_member_get_event_recurring_summary')
                ? mj_member_get_event_recurring_summary($event)
                : '';
            $recurring_summary_text = '';
            $recurring_summary_time = '';
            if (is_array($recurring_summary_raw)) {
                if (!empty($recurring_summary_raw['summary'])) {
                    $recurring_summary_text = (string) $recurring_summary_raw['summary'];
                }
                if (!empty($recurring_summary_raw['time'])) {
                    $recurring_summary_time = (string) $recurring_summary_raw['time'];
                }
            } elseif (is_string($recurring_summary_raw)) {
                $recurring_summary_text = $recurring_summary_raw;
            }

            $date_range = '';
            if (!$event_has_multiple_occurrences) {
                $date_range = mj_member_format_event_datetime_range($event['start_date'], $event['end_date']);
            }
            $permalink = !empty($event['permalink']) ? esc_url($event['permalink']) : '';
            if ($permalink === '' && !empty($event['article_permalink'])) {
                $permalink = esc_url($event['article_permalink']);
            }

            $should_show_cover = $show_cover && $cover_url !== '' && $resolved_layout !== 'compact';

            $card_classes = array('mj-member-events__item', 'layout-' . $resolved_layout);
            $card_classes[] = $should_show_cover ? 'has-cover' : 'no-cover';
            $card_class_attr = implode(' ', array_map('sanitize_html_class', $card_classes));
            $location_type_slugs = array();
            if (!empty($event['location_types']) && is_array($event['location_types'])) {
                foreach ($event['location_types'] as $type_slug) {
                    $normalized_slug = sanitize_title($type_slug);
                    if ($normalized_slug === '') {
                        continue;
                    }
                    $location_type_slugs[] = $normalized_slug;
                }
                if (!empty($location_type_slugs)) {
                    $location_type_slugs = array_values(array_unique($location_type_slugs));
                }
            }

            $article_attributes = array('class="' . esc_attr($card_class_attr) . '"');
            if (!empty($location_type_slugs)) {
                $article_attributes[] = 'data-location-types="' . esc_attr(implode(',', $location_type_slugs)) . '"';
            }
            $article_id_value = isset($event['article_id']) ? (int) $event['article_id'] : 0;
            if ($article_id_value > 0) {
                $article_attributes[] = 'data-article-id="' . $article_id_value . '"';
            }

            $accent_color_raw = isset($event['accent_color']) ? (string) $event['accent_color'] : '';
            $accent_color_value = '';
            if ($accent_color_raw !== '') {
                if (function_exists('mj_member_normalize_hex_color_value')) {
                    $accent_color_value = mj_member_normalize_hex_color_value($accent_color_raw);
                } else {
                    $accent_candidate = sanitize_hex_color($accent_color_raw);
                    if (is_string($accent_candidate) && $accent_candidate !== '') {
                        $accent_color_value = strtoupper($accent_candidate);
                    }
                }
            }
            if ($accent_color_value !== '') {
                $article_attributes[] = 'style="--mj-event-accent: ' . esc_attr($accent_color_value) . '"';
            }

            echo '<article ' . implode(' ', $article_attributes) . '>';

            $badge_label = '';
            $badge_inline = '';
            $badge_overlay = '';
            if ($show_badge && $type_label !== '') {
                $badge_label = esc_html($type_label);
                $badge_inline = '<span class="mj-member-events__badge">' . $badge_label . '</span>';
                $badge_overlay = '<span class="mj-member-events__badge is-overlay">' . $badge_label . '</span>';
            }

            if ($should_show_cover) {
                $cover_classes = array('mj-member-events__cover');
                if ($resolved_layout === 'horizontal') {
                    $cover_classes[] = 'is-horizontal';
                } else {
                    if ($cover_ratio !== '16-9') {
                        $cover_classes[] = 'ratio-' . $cover_ratio;
                    }
                }
                $cover_attr = implode(' ', array_map('sanitize_html_class', $cover_classes));
                $image_alt = !empty($event['raw_location_name']) ? $event['raw_location_name'] : $event['title'];
                echo '<div class="' . esc_attr($cover_attr) . '">';
                if ($permalink) {
                    echo '<a class="mj-member-events__cover-link" href="' . $permalink . '">';
                    echo '<img src="' . $cover_url . '" alt="' . esc_attr($image_alt) . '" loading="lazy" />';
                    echo '</a>';
                } else {
                    echo '<img src="' . $cover_url . '" alt="' . esc_attr($image_alt) . '" loading="lazy" />';
                }
                if ($badge_overlay !== '') {
                    echo $badge_overlay;
                    $badge_inline = '';
                }
                echo '</div>';
            }

            echo '<div class="mj-member-events__item-body">';

            if ($badge_inline !== '') {
                echo $badge_inline;
            }

            $heading_tag = $card_title_tag === 'p' ? 'p' : $card_title_tag;
            $heading_open = '<' . $heading_tag . ' class="mj-member-events__item-title">';
            $heading_close = '</' . $heading_tag . '>';
            if ($permalink) {
                echo $heading_open . '<a href="' . $permalink . '">' . esc_html($event['title']) . '</a>' . $heading_close;
            } else {
                echo $heading_open . esc_html($event['title']) . $heading_close;
            }

            if ($recurring_summary_text !== '' || $recurring_summary_time !== '') {
                echo '<div class="mj-member-events__recurring-summary">';
                if ($recurring_summary_text !== '') {
                    echo '<span class="mj-member-events__recurring-heading">' . esc_html($recurring_summary_text) . '</span>';
                }
                if ($recurring_summary_time !== '') {
                    echo '<span class="mj-member-events__recurring-time">' . esc_html($recurring_summary_time) . '</span>';
                }
                echo '</div>';
            }

            $price_value = isset($event['price']) ? (float) $event['price'] : 0.0;
            $price_positive = $price_value > 0;
            $should_display_price = ($price_display_mode !== 'hide') && $price_positive;
            if ($price_display_mode === 'hide_zero' && !$price_positive) {
                $should_display_price = false;
            }
            if ($should_display_price) {
                $price_amount = number_format_i18n($price_value, 2) . ' €';
                $price_label = trim((string) $price_prefix);
                if ($price_label === '') {
                    $price_label = __('Tarif', 'mj-member');
                }
                echo '<div class="mj-member-events__price-chip">'
                    . '<span class="mj-member-events__price-chip-label">' . esc_html($price_label) . '</span>'
                    . '<span class="mj-member-events__price-chip-value">' . esc_html($price_amount) . '</span>'
                    . '</div>';
            }

            $dates_display = array();
            if (!empty($occurrence_items)) {
                foreach ($occurrence_items as $index => $occurrence_item) {
                    if ($index >= 3) {
                        break;
                    }
                    if (!is_array($occurrence_item)) {
                        continue;
                    }

                    $timestamp_candidate = isset($occurrence_item['timestamp']) ? (int) $occurrence_item['timestamp'] : 0;
                    if ($timestamp_candidate <= 0 && !empty($occurrence_item['start'])) {
                        $timestamp_candidate = strtotime((string) $occurrence_item['start']);
                    }

                    if ($timestamp_candidate > 0) {
                        $dates_display[] = wp_date(get_option('date_format', 'd/m/Y'), $timestamp_candidate);
                        continue;
                    }

                    if (!empty($occurrence_item['label'])) {
                        $label_candidate = preg_replace('/\s+\d{1,2}(?:[:h]\d{2})?.*/u', '', (string) $occurrence_item['label']);
                        $label_candidate = trim((string) $label_candidate);
                        if ($label_candidate === '') {
                            $label_candidate = (string) $occurrence_item['label'];
                        }
                        $dates_display[] = sanitize_text_field($label_candidate);
                    }
                }
            }

            $dates_display = array_values(array_unique(array_filter($dates_display, 'strlen')));
            $occurrence_line = '';
            if (!empty($dates_display)) {
                $dates_label = implode(', ', $dates_display);
                $total_known = count($occurrence_items) + max(0, $occurrence_remaining);
                $remaining_count = max(0, $total_known - count($dates_display));
                if ($remaining_count > 0) {
                    $dates_label .= ' ' . sprintf(_n('+ %d autre date', '+ %d autres dates', $remaining_count, 'mj-member'), $remaining_count);
                }
                $occurrence_line = $dates_label;
            }

            if ($show_next_dates && $event_has_multiple_occurrences && $occurrence_line !== '') {
                echo '<p class="mj-member-events__occurrence-next">'
                    . '<span class="mj-member-events__occurrence-prefix">' . esc_html__('Prochaines dates :', 'mj-member') . '</span>'
                    . '<span class="mj-member-events__occurrence-label">' . esc_html($occurrence_line) . '</span>'
                    . '</p>';
            }

            $display_excerpt = $show_description && !empty($event['excerpt']);
            if ($display_excerpt && $resolved_layout === 'compact') {
                $display_excerpt = strlen($event['excerpt']) <= 180;
            }
            if ($display_excerpt) {
                echo '<p class="mj-member-events__excerpt">' . esc_html($event['excerpt']) . '</p>';
            }

            $location_detail_notes = isset($event['location_description']) ? trim((string) $event['location_description']) : '';
            $location_logo = isset($event['location_cover']) ? esc_url($event['location_cover']) : '';
            $location_address = isset($event['location_address']) ? trim((string) $event['location_address']) : '';
            $location_name = isset($event['raw_location_name']) ? trim((string) $event['raw_location_name']) : '';
            if ($location_name === '' && !empty($event['location'])) {
                $location_name = (string) $event['location'];
            }

            if ($show_location && ($location_logo !== '' || $location_name !== '' || $location_address !== '' || $location_detail_notes !== '')) {
                echo '<div class="mj-member-events__location-card">';
                if ($location_logo !== '') {
                    $location_logo_alt = $location_name !== '' ? $location_name : $event['title'];
                    echo '<img class="mj-member-events__location-logo" src="' . $location_logo . '" alt="' . esc_attr($location_logo_alt) . '" loading="lazy" />';
                }

                echo '<div class="mj-member-events__location-content">';
                if ($location_name !== '') {
                    echo '<p class="mj-member-events__location-name">' . esc_html($location_name) . '</p>';
                }
                if ($location_address !== '') {
                    echo '<p class="mj-member-events__location-address">' . esc_html($location_address) . '</p>';
                }
                if ($location_detail_notes !== '') {
                    $location_notes_html = nl2br(esc_html($location_detail_notes));
                    echo '<p class="mj-member-events__location-note">' . $location_notes_html . '</p>';
                }
                echo '</div>';
                echo '</div>';
            }

            echo '</div>';
            echo '</article>';
        }

        echo '</div>';
        echo '<p class="mj-member-events__filtered-empty" hidden>' . esc_html__('Aucun événement ne correspond à ce filtre.', 'mj-member') . '</p>';
        echo '</div>';
    }

    private function is_elementor_preview_mode() {
        if (!did_action('elementor/loaded')) {
            return false;
        }

        $elementor = \Elementor\Plugin::$instance ?? null;
        if ($elementor && isset($elementor->editor) && method_exists($elementor->editor, 'is_edit_mode')) {
            return (bool) $elementor->editor->is_edit_mode();
        }

        return false;
    }

    private function build_preview_events($settings, $context = array()) {
        $limit = isset($context['limit']) ? (int) $context['limit'] : 3;
        if ($limit <= 0) {
            $limit = 3;
        }

        $cover_url = '';
        if (!empty($settings['fallback_image']['id'])) {
            $cover_candidate = wp_get_attachment_image_url((int) $settings['fallback_image']['id'], 'large');
            if (is_string($cover_candidate) && $cover_candidate !== '') {
                $cover_url = $cover_candidate;
            }
        }
        if ($cover_url === '' && !empty($settings['fallback_image']['url'])) {
            $cover_candidate = esc_url_raw($settings['fallback_image']['url']);
            if (is_string($cover_candidate) && $cover_candidate !== '') {
                $cover_url = $cover_candidate;
            }
        }
        if ($cover_url === '' && method_exists(Utils::class, 'get_placeholder_image_src')) {
            $cover_url = Utils::get_placeholder_image_src();
        }

        $status_value = 'actif';
        if (!empty($context['statuses']) && is_array($context['statuses'])) {
            $status_candidate = sanitize_key((string) reset($context['statuses']));
            if ($status_candidate !== '') {
                $status_value = $status_candidate;
            }
        }

        $type_filters = array();
        if (!empty($context['types']) && is_array($context['types'])) {
            foreach ($context['types'] as $type_candidate) {
                $type_key = sanitize_key((string) $type_candidate);
                if ($type_key === '') {
                    continue;
                }
                $type_filters[$type_key] = $type_key;
            }
        }
        if (empty($type_filters)) {
            $type_filters = array('stage', 'sortie', 'soiree');
        }

        $article_mode = isset($context['article_filter_mode']) ? sanitize_key((string) $context['article_filter_mode']) : 'any';
        if (!in_array($article_mode, array('any', 'with_article', 'without_article'), true)) {
            $article_mode = 'any';
        }

        $article_ids = array();
        if (!empty($context['article_ids']) && is_array($context['article_ids'])) {
            foreach ($context['article_ids'] as $article_candidate) {
                $article_id = (int) $article_candidate;
                if ($article_id > 0) {
                    $article_ids[] = $article_id;
                }
            }
        }

        $now = current_time('timestamp');

        $type_presets = array(
            'stage' => array(
                'title' => __('Stage multi-activités', 'mj-member'),
                'excerpt' => __('Une semaine sportive et ludique pour les 12-17 ans.', 'mj-member'),
                'location' => __('Maison des Jeunes', 'mj-member'),
                'address' => __('12 rue des Arts, Mons', 'mj-member'),
                'note' => __('Accueil dès 9h, collation fournie.', 'mj-member'),
                'accent' => '#2563EB',
            ),
            'sortie' => array(
                'title' => __('Sortie nature guidée', 'mj-member'),
                'excerpt' => __('Découverte des sentiers et sensibilisation à la faune locale.', 'mj-member'),
                'location' => __('Parc du Loup', 'mj-member'),
                'address' => __('Chemin des Bruyères 5, Jemappes', 'mj-member'),
                'note' => __('Prévoir des chaussures de marche et une gourde.', 'mj-member'),
                'accent' => '#059669',
            ),
            'soiree' => array(
                'title' => __('Soirée jeux collaboratifs', 'mj-member'),
                'excerpt' => __('Une soirée conviviale autour de jeux coopératifs et musicaux.', 'mj-member'),
                'location' => __('Espace Agora', 'mj-member'),
                'address' => __('Place du Marché 8, Mons', 'mj-member'),
                'note' => __('Snacks partagés et boissons disponibles sur place.', 'mj-member'),
                'accent' => '#7C3AED',
            ),
        );

        $events = array();
        $type_keys = array_values($type_filters);
        $type_count = count($type_keys);
        if ($type_count === 0) {
            $type_keys = array('stage');
            $type_count = 1;
        }

        for ($index = 0; $index < $limit; $index++) {
            $type_key = $type_keys[$index % $type_count];
            $preset = isset($type_presets[$type_key]) ? $type_presets[$type_key] : array(
                'title' => sprintf(__('Événement %d', 'mj-member'), $index + 1),
                'excerpt' => __('Aperçu des activités proposées par la MJ.', 'mj-member'),
                'location' => __('Maison des Jeunes', 'mj-member'),
                'address' => __('Rue Principale 1, Mons', 'mj-member'),
                'note' => '',
                'accent' => '#2563EB',
            );

            $start_time = $now + (($index + 1) * DAY_IN_SECONDS * 3);
            $end_time = $start_time + (3 * HOUR_IN_SECONDS);
            $start_date = wp_date('Y-m-d H:i:s', $start_time);
            $end_date = wp_date('Y-m-d H:i:s', $end_time);

            $slug_seed = sanitize_title($preset['title']);
            $permalink = '#';
            if (function_exists('home_url')) {
                $permalink = home_url('/evenements/' . $slug_seed . '-demo');
            }

            $article_id_value = 0;
            $article_permalink = '';
            if ($article_mode === 'with_article' || (!empty($article_ids))) {
                if (!empty($article_ids)) {
                    $article_id_value = $article_ids[$index % count($article_ids)];
                } else {
                    $article_id_value = 9800 + $index;
                }
                $article_permalink = $permalink !== '' ? $permalink : '#';
            } elseif ($article_mode === 'any' && ($index % 2 === 0)) {
                $article_id_value = 9700 + $index;
                $article_permalink = $permalink !== '' ? $permalink : '#';
            }

            if ($article_mode === 'without_article') {
                $article_id_value = 0;
                $article_permalink = '';
            }

            $events[] = array(
                'id' => 9500 + $index,
                'status' => $status_value,
                'type' => $type_key,
                'title' => $preset['title'],
                'permalink' => $permalink,
                'article_permalink' => $article_permalink,
                'article_id' => $article_id_value,
                'cover_url' => $cover_url,
                'article_cover_url' => $cover_url,
                'excerpt' => $preset['excerpt'],
                'location' => $preset['location'],
                'raw_location_name' => $preset['location'],
                'location_address' => $preset['address'],
                'location_description' => $preset['note'],
                'location_types' => array($type_key),
                'location_cover' => '',
                'price' => 12 + ($index * 4),
                'start_date' => $start_date,
                'end_date' => $end_date,
                'date_debut' => $start_date,
                'date_fin' => $end_date,
                'schedule_mode' => 'single',
                'schedule_payload' => array(),
                'accent_color' => isset($preset['accent']) ? $preset['accent'] : '#2563EB',
            );
        }

        return $events;
    }
}
