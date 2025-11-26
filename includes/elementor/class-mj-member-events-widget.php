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

class Mj_Member_Elementor_Events_Widget extends Widget_Base {
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
        return array('general');
    }

    public function get_keywords() {
        return array('mj', 'events', 'stages', 'agenda', 'calendar');
    }

    public function get_script_depends() {
        return array('mj-member-events-widget');
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
                'default' => __('Nos prochains événements', 'mj-member'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'display_title',
            array(
                'label' => __('Afficher le titre du widget', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
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
            'max_items',
            array(
                'label' => __('Nombre maximum', 'mj-member'),
                'type' => Controls_Manager::NUMBER,
                'min' => 1,
                'max' => 50,
                'default' => 6,
            )
        );

        $this->add_control(
            'orderby',
            array(
                'label' => __('Trier par', 'mj-member'),
                'type' => Controls_Manager::SELECT,
                'options' => array(
                    'date_debut' => __('Date de début', 'mj-member'),
                    'date_fin' => __('Date de fin', 'mj-member'),
                    'created_at' => __('Date de création', 'mj-member'),
                    'updated_at' => __('Dernière mise à jour', 'mj-member'),
                ),
                'default' => 'date_debut',
            )
        );

        $this->add_control(
            'order',
            array(
                'label' => __('Ordre', 'mj-member'),
                'type' => Controls_Manager::SELECT,
                'options' => array(
                    'DESC' => __('Décroissant', 'mj-member'),
                    'ASC' => __('Croissant', 'mj-member'),
                ),
                'default' => 'DESC',
            )
        );

        $this->add_control(
            'layout',
            array(
                'label' => __('Disposition', 'mj-member'),
                'type' => Controls_Manager::CHOOSE,
                'options' => array(
                    'list' => array(
                        'title' => __('Liste', 'mj-member'),
                        'icon' => 'eicon-menu-bar',
                    ),
                    'grid' => array(
                        'title' => __('Grille', 'mj-member'),
                        'icon' => 'eicon-gallery-grid',
                    ),
                ),
                'default' => 'grid',
                'toggle' => false,
            )
        );

        $this->add_control(
            'card_title_tag',
            array(
                'label' => __('Balise du titre', 'mj-member'),
                'type' => Controls_Manager::SELECT,
                'options' => array(
                    'h2' => 'H2',
                    'h3' => 'H3',
                    'h4' => 'H4',
                    'h5' => 'H5',
                    'p' => __('Paragraphe', 'mj-member'),
                ),
                'default' => 'h4',
                'render_type' => 'template',
            )
        );

        $this->add_responsive_control(
            'grid_columns',
            array(
                'label' => __('Colonnes (mode grille)', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'range' => array(
                    'px' => array('min' => 1, 'max' => 6),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-events__grid.is-grid' => 'grid-template-columns: repeat({{SIZE}}, minmax(220px, 1fr));',
                ),
                'condition' => array('layout' => 'grid'),
            )
        );

        $this->add_responsive_control(
            'cards_gap',
            array(
                'label' => __('Espacement des cartes', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'range' => array(
                    'px' => array('min' => 0, 'max' => 64),
                ),
                'default' => array(
                    'size' => 20,
                    'unit' => 'px',
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-events__grid' => 'gap: {{SIZE}}{{UNIT}};',
                ),
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
            'show_map',
            array(
                'label' => __('Afficher la carte', 'mj-member'),
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

        $this->add_control(
            'show_cta',
            array(
                'label' => __("Afficher le bouton d'inscription", 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->add_control(
            'cta_label',
            array(
                'label' => __('Texte du bouton', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => __("S'inscrire", 'mj-member'),
                'label_block' => true,
                'condition' => array('show_cta' => 'yes'),
            )
        );

        $this->add_control(
            'cta_registered_label',
            array(
                'label' => __('Texte si déjà inscrit', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Déjà inscrit', 'mj-member'),
                'label_block' => true,
                'condition' => array('show_cta' => 'yes'),
            )
        );

        $this->add_control(
            'cta_skin',
            array(
                'label' => __('Style du bouton', 'mj-member'),
                'type' => Controls_Manager::SELECT,
                'options' => array(
                    'solid' => __('Plein', 'mj-member'),
                    'outline' => __('Contour', 'mj-member'),
                    'text' => __('Texte', 'mj-member'),
                ),
                'default' => 'solid',
                'condition' => array('show_cta' => 'yes'),
            )
        );

        $this->end_controls_section();

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
            'card_background',
            array(
                'label' => __('Couleur de fond', 'mj-member'),
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

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            array(
                'name' => 'card_shadow',
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
                'selector' => '{{WRAPPER}} .mj-member-events__item-title',
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
                'selector' => '{{WRAPPER}} .mj-member-events__meta',
            )
        );

        $this->add_control(
            'meta_color',
            array(
                'label' => __('Couleur des métadonnées', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'global' => array('default' => Global_Colors::COLOR_TEXT),
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-events' => '--mj-events-meta: {{VALUE}};',
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
            'section_style_button',
            array(
                'label' => __('Bouton', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            array(
                'name' => 'cta_typography',
                'label' => __('Typographie du bouton', 'mj-member'),
                'global' => array('default' => Global_Typography::TYPOGRAPHY_TEXT),
                'selector' => '{{WRAPPER}} .mj-member-events__cta',
            )
        );

        $this->add_control(
            'cta_background_color',
            array(
                'label' => __('Fond du bouton', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'global' => array('default' => Global_Colors::COLOR_ACCENT),
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-events' => '--mj-events-button-bg: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'cta_background_hover_color',
            array(
                'label' => __('Fond au survol', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'global' => array('default' => Global_Colors::COLOR_PRIMARY),
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-events' => '--mj-events-button-hover: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'cta_border_color',
            array(
                'label' => __('Contour du bouton', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'global' => array('default' => Global_Colors::COLOR_TEXT),
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-events' => '--mj-events-button-border: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'cta_text_color',
            array(
                'label' => __('Texte du bouton', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'global' => array('default' => Global_Colors::COLOR_TEXT),
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-events' => '--mj-events-button-text: {{VALUE}};',
                ),
            )
        );

        $this->add_responsive_control(
            'cta_alignment',
            array(
                'label' => __('Alignement du bouton', 'mj-member'),
                'type' => Controls_Manager::CHOOSE,
                'options' => array(
                    'flex-start' => array('title' => __('Gauche', 'mj-member'), 'icon' => 'eicon-text-align-left'),
                    'center' => array('title' => __('Centre', 'mj-member'), 'icon' => 'eicon-text-align-center'),
                    'flex-end' => array('title' => __('Droite', 'mj-member'), 'icon' => 'eicon-text-align-right'),
                    'stretch' => array('title' => __('Largeur totale', 'mj-member'), 'icon' => 'eicon-text-align-justify'),
                ),
                'default' => 'flex-start',
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-events__actions' => 'align-items: {{VALUE}};',
                    '{{WRAPPER}} .mj-member-events__cta' => 'align-self: {{VALUE}};',
                ),
                'condition' => array('show_cta' => 'yes'),
            )
        );

        $this->add_control(
            'cta_full_width',
            array(
                'label' => __('Bouton pleine largeur', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'no',
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-events__cta' => 'width: 100%;',
                ),
                'condition' => array('show_cta' => 'yes'),
            )
        );

        $this->add_responsive_control(
            'cta_radius',
            array(
                'label' => __('Arrondi du bouton', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'range' => array(
                    'px' => array('min' => 0, 'max' => 999),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-events' => '--mj-events-button-radius: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->end_controls_section();
    }

    protected function render() {
        if (!function_exists('mj_member_get_public_events')) {
            echo '<div class="mj-member-events__warning">' . esc_html__('Le module MJ Member doit être actif pour utiliser ce widget.', 'mj-member') . '</div>';
            return;
        }

        $settings = $this->get_settings_for_display();

        $statuses = isset($settings['statuses']) ? (array) $settings['statuses'] : array();
        $types = isset($settings['types']) ? (array) $settings['types'] : array();
        $limit = isset($settings['max_items']) ? (int) $settings['max_items'] : 6;
        $orderby = isset($settings['orderby']) ? $settings['orderby'] : 'date_debut';
        $order = isset($settings['order']) ? $settings['order'] : 'DESC';
        $include_past = isset($settings['include_past']) && $settings['include_past'] === 'yes';

        $events = mj_member_get_public_events(
            array(
                'statuses' => $statuses,
                'types' => $types,
                'limit' => $limit,
                'orderby' => $orderby,
                'order' => $order,
                'include_past' => $include_past,
            )
        );

        $now_timestamp = current_time('timestamp');
        $is_user_logged_in = is_user_logged_in();
        $current_member = ($is_user_logged_in && function_exists('mj_member_get_current_member')) ? mj_member_get_current_member() : null;

        $participant_options = array();
        if ($current_member && !empty($current_member->id)) {
            $member_name_parts = array();
            if (!empty($current_member->first_name)) {
                $member_name_parts[] = sanitize_text_field($current_member->first_name);
            }
            if (!empty($current_member->last_name)) {
                $member_name_parts[] = sanitize_text_field($current_member->last_name);
            }

            $member_display_name = trim(implode(' ', $member_name_parts));
            if ($member_display_name === '' && !empty($current_member->nickname)) {
                $member_display_name = sanitize_text_field($current_member->nickname);
            }
            if ($member_display_name === '') {
                $member_display_name = sprintf(__('Membre #%d', 'mj-member'), (int) $current_member->id);
            }

            $self_label = $member_display_name . ' (' . __('moi', 'mj-member') . ')';
            $participant_options[] = array(
                'id' => (int) $current_member->id,
                'label' => $self_label,
                'type' => isset($current_member->role) ? sanitize_key($current_member->role) : 'member',
                'isSelf' => true,
            );
        }

        if ($current_member && function_exists('mj_member_can_manage_children') && function_exists('mj_member_get_guardian_children') && mj_member_can_manage_children($current_member)) {
            $children = mj_member_get_guardian_children($current_member);
            if (!empty($children) && is_array($children)) {
                foreach ($children as $child) {
                    if (!$child || !isset($child->id)) {
                        continue;
                    }

                    $child_name_parts = array();
                    if (!empty($child->first_name)) {
                        $child_name_parts[] = sanitize_text_field($child->first_name);
                    }
                    if (!empty($child->last_name)) {
                        $child_name_parts[] = sanitize_text_field($child->last_name);
                    }

                    $child_label = trim(implode(' ', $child_name_parts));
                    if ($child_label === '' && !empty($child->nickname)) {
                        $child_label = sanitize_text_field($child->nickname);
                    }
                    if ($child_label === '') {
                        $child_label = sprintf(__('Jeune #%d', 'mj-member'), (int) $child->id);
                    }

                    $participant_options[] = array(
                        'id' => (int) $child->id,
                        'label' => $child_label,
                        'type' => 'child',
                        'isSelf' => false,
                    );
                }
            }
        }

        if (!empty($participant_options)) {
            $participant_options = array_values($participant_options);
        }

        $current_member_id = ($current_member && !empty($current_member->id)) ? (int) $current_member->id : 0;
        $viewer_is_animateur = ($current_member_id > 0 && isset($current_member->role) && sanitize_key($current_member->role) === MjMembers_CRUD::ROLE_ANIMATEUR);
        $registration_status_labels = array();
        if (class_exists('MjEventRegistrations') && method_exists('MjEventRegistrations', 'get_status_labels')) {
            $registration_status_labels = MjEventRegistrations::get_status_labels();
        }
        $animateur_tools_ready = class_exists('MjEventAnimateurs');

        $title = isset($settings['title']) ? $settings['title'] : '';
        $display_title = !isset($settings['display_title']) || $settings['display_title'] === 'yes';
        $empty_message = isset($settings['empty_message']) ? $settings['empty_message'] : __('Aucun événement disponible pour le moment.', 'mj-member');
        $show_description = isset($settings['show_description']) && $settings['show_description'] === 'yes';
        $show_location = isset($settings['show_location']) && $settings['show_location'] === 'yes';
        $show_map = isset($settings['show_map']) && $settings['show_map'] === 'yes';
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

        $show_cta = !isset($settings['show_cta']) || $settings['show_cta'] === 'yes';
        $cta_label = isset($settings['cta_label']) && $settings['cta_label'] !== '' ? sanitize_text_field((string) $settings['cta_label']) : __("S'inscrire", 'mj-member');
        $cta_registered_label = isset($settings['cta_registered_label']) && $settings['cta_registered_label'] !== '' ? sanitize_text_field((string) $settings['cta_registered_label']) : __('Déjà inscrit', 'mj-member');
        if ($cta_label === '') {
            $cta_label = __("S'inscrire", 'mj-member');
        }
        if ($cta_registered_label === '') {
            $cta_registered_label = __('Déjà inscrit', 'mj-member');
        }
        $cta_skin = isset($settings['cta_skin']) ? sanitize_key($settings['cta_skin']) : 'solid';
        $allowed_cta_skins = array('solid', 'outline', 'text');
        if (!in_array($cta_skin, $allowed_cta_skins, true)) {
            $cta_skin = 'solid';
        }

        $fallback_url = '';
        if (!empty($settings['fallback_image']['id'])) {
            $fallback_url = wp_get_attachment_image_url((int) $settings['fallback_image']['id'], 'large');
        }
        if (!$fallback_url && !empty($settings['fallback_image']['url'])) {
            $fallback_url = esc_url_raw($settings['fallback_image']['url']);
        }

        $type_labels = method_exists('MjEvents_CRUD', 'get_type_labels') ? MjEvents_CRUD::get_type_labels() : array();

        if (function_exists('mj_member_output_events_widget_styles')) {
            mj_member_output_events_widget_styles();
        }

        $instance_id = wp_unique_id('mj-member-events-');
        echo '<div class="mj-member-events" data-mj-events-root="' . esc_attr($instance_id) . '">';
        if ($display_title && $title !== '') {
            echo '<h3 class="mj-member-events__title">' . esc_html($title) . '</h3>';
        }

        if (empty($events)) {
            echo '<p class="mj-member-events__empty">' . esc_html($empty_message) . '</p>';
            echo '</div>';
            return;
        }

        wp_enqueue_script('mj-member-events-widget');
        if (function_exists('mj_member_ensure_events_widget_localized')) {
            mj_member_ensure_events_widget_localized();
        }

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
            $occurrence_next = isset($occurrence_preview['next']) && is_array($occurrence_preview['next'])
                ? $occurrence_preview['next']
                : null;
            $occurrence_remaining = isset($occurrence_preview['remaining']) ? (int) $occurrence_preview['remaining'] : 0;
            $event_has_multiple_occurrences = !empty($occurrence_preview['has_multiple']);
            $occurrence_next_label = ($occurrence_next && isset($occurrence_next['label'])) ? $occurrence_next['label'] : '';

            $date_range = '';
            if (!$event_has_multiple_occurrences) {
                $date_range = mj_member_format_event_datetime_range($event['start_date'], $event['end_date']);
            }
            $permalink = !empty($event['permalink']) ? esc_url($event['permalink']) : '';
            if ($permalink === '' && !empty($event['article_permalink'])) {
                $permalink = esc_url($event['article_permalink']);
            }
            $deadline_string = isset($event['deadline']) ? trim((string) $event['deadline']) : '';
            $deadline_ts = ($deadline_string !== '' && $deadline_string !== '0000-00-00 00:00:00') ? strtotime($deadline_string) : false;
            $start_ts = !empty($event['start_date']) ? strtotime($event['start_date']) : false;
            $registration_open = true;
            if ($deadline_ts && $deadline_ts < $now_timestamp) {
                $registration_open = false;
            }
            if ($registration_open && $start_ts && $start_ts < $now_timestamp) {
                $registration_open = false;
            }

            $allow_guardian_registration = !empty($event['allow_guardian_registration']);
            $participants_source = $participant_options;
            if (!$allow_guardian_registration && !empty($participant_options)) {
                $participants_source = array();
                foreach ($participant_options as $participant_option) {
                    $option_type = isset($participant_option['type']) ? sanitize_key($participant_option['type']) : '';
                    $is_self = !empty($participant_option['isSelf']);
                    if ($is_self && $option_type === MjMembers_CRUD::ROLE_TUTEUR) {
                        continue;
                    }
                    $participants_source[] = $participant_option;
                }
            }

            $event_participants = array();
            $registered_count = 0;
            $available_count = 0;
            if (!empty($participants_source)) {
                foreach ($participants_source as $participant_option) {
                    $participant_entry = $participant_option;
                    $participant_entry['isRegistered'] = false;
                    $participant_entry['registrationId'] = 0;
                    $participant_entry['registrationStatus'] = '';
                    $participant_entry['registrationCreatedAt'] = '';
                    $participant_id = isset($participant_option['id']) ? (int) $participant_option['id'] : 0;

                    if ($participant_id > 0 && class_exists('MjEventRegistrations')) {
                        $existing_registration = MjEventRegistrations::get_existing((int) $event['id'], $participant_id);
                        if ($existing_registration && (!isset($existing_registration->statut) || $existing_registration->statut !== MjEventRegistrations::STATUS_CANCELLED)) {
                            $participant_entry['isRegistered'] = true;
                            if (isset($existing_registration->id)) {
                                $participant_entry['registrationId'] = (int) $existing_registration->id;
                            }
                            if (!empty($existing_registration->statut)) {
                                $participant_entry['registrationStatus'] = sanitize_key($existing_registration->statut);
                            }
                            if (!empty($existing_registration->created_at)) {
                                $participant_entry['registrationCreatedAt'] = sanitize_text_field($existing_registration->created_at);
                            }
                        }
                    }

                    if ($participant_entry['isRegistered']) {
                        $registered_count++;
                    } else {
                        $available_count++;
                    }

                    $event_participants[] = $participant_entry;
                }
            }

            $all_participants_registered = !empty($event_participants) && $registered_count === count($event_participants);

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

            echo '<article ' . implode(' ', $article_attributes) . '>';

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
                echo '<img src="' . $cover_url . '" alt="' . esc_attr($image_alt) . '" loading="lazy" />';
                echo '</div>';
            }

            echo '<div class="mj-member-events__item-body">';

            if ($show_badge && $type_label !== '') {
                echo '<span class="mj-member-events__badge">' . esc_html($type_label) . '</span>';
            }

            $heading_tag = $card_title_tag === 'p' ? 'p' : $card_title_tag;
            $heading_open = '<' . $heading_tag . ' class="mj-member-events__item-title">';
            $heading_close = '</' . $heading_tag . '>';
            if ($permalink) {
                echo $heading_open . '<a href="' . $permalink . '">' . esc_html($event['title']) . '</a>' . $heading_close;
            } else {
                echo $heading_open . esc_html($event['title']) . $heading_close;
            }

            $meta_parts = array();
            if ($date_range !== '') {
                $meta_parts[] = $date_range;
            }
            if ($show_location && !empty($event['location'])) {
                $meta_parts[] = $event['location'];
            }
            if ($price_display_mode !== 'hide' && isset($event['price'])) {
                $price_value = (float) $event['price'];
                $is_zero_price = abs($price_value) < 0.01;
                if (!($price_display_mode === 'hide_zero' && $is_zero_price)) {
                    $price_string = number_format_i18n($price_value, 2) . ' €';
                    $prefix_trimmed = trim((string) $price_prefix);
                    if ($prefix_trimmed !== '') {
                        $price_string = $prefix_trimmed . ' ' . $price_string;
                    }
                    $meta_parts[] = $price_string;
                }
            }

            if (!empty($meta_parts)) {
                echo '<div class="mj-member-events__meta">' . esc_html(implode(' • ', $meta_parts)) . '</div>';
            }

            if ($event_has_multiple_occurrences && $occurrence_next_label !== '') {
                $next_prefix = (!empty($occurrence_next['isToday'])) ? __("Aujourd'hui :", 'mj-member') : __('Prochaine occurrence :', 'mj-member');
                echo '<p class="mj-member-events__occurrence-next">'
                    . '<span class="mj-member-events__occurrence-prefix">' . esc_html($next_prefix) . '</span>'
                    . '<span class="mj-member-events__occurrence-label">' . esc_html($occurrence_next_label) . '</span>'
                    . '</p>';

                $following_occurrences = array_slice($occurrence_items, 1);
                if (!empty($following_occurrences) || $occurrence_remaining > 0) {
                    echo '<ul class="mj-member-events__occurrences" aria-label="' . esc_attr__('Autres occurrences', 'mj-member') . '">';
                    foreach ($following_occurrences as $following_occurrence) {
                        if (!is_array($following_occurrence) || empty($following_occurrence['label'])) {
                            continue;
                        }
                        $following_label = $following_occurrence['label'];
                        $following_prefix = !empty($following_occurrence['isToday']) ? __("Aujourd'hui :", 'mj-member') : __('Ensuite :', 'mj-member');
                        $following_classes = array('mj-member-events__occurrence');
                        if (!empty($following_occurrence['isToday'])) {
                            $following_classes[] = 'is-today';
                        }
                        echo '<li class="' . esc_attr(implode(' ', array_map('sanitize_html_class', $following_classes))) . '">'
                            . '<span class="mj-member-events__occurrence-prefix">' . esc_html($following_prefix) . '</span>'
                            . '<span class="mj-member-events__occurrence-label">' . esc_html($following_label) . '</span>'
                            . '</li>';
                    }

                    if ($occurrence_remaining > 0) {
                        $remaining_label = sprintf(_n('+ %d autre date', '+ %d autres dates', $occurrence_remaining, 'mj-member'), $occurrence_remaining);
                        echo '<li class="mj-member-events__occurrence mj-member-events__occurrence--more">'
                            . '<span class="mj-member-events__occurrence-label">' . esc_html($remaining_label) . '</span>'
                            . '</li>';
                    }

                    echo '</ul>';
                }
            }

            $display_excerpt = $show_description && !empty($event['excerpt']);
            if ($display_excerpt && $resolved_layout === 'compact') {
                $display_excerpt = strlen($event['excerpt']) <= 180;
            }
            if ($display_excerpt) {
                echo '<p class="mj-member-events__excerpt">' . esc_html($event['excerpt']) . '</p>';
            }

            if ($permalink) {
                echo '<a class="mj-member-events__detail-link" href="' . $permalink . '">' . esc_html__('Détail de l\'événement', 'mj-member') . '</a>';
            }

            $location_detail_notes = isset($event['location_description']) ? trim((string) $event['location_description']) : '';
            $location_detail_cover = isset($event['location_cover']) ? esc_url($event['location_cover']) : '';
            if ($show_location && ($location_detail_notes !== '' || $location_detail_cover !== '')) {
                echo '<div class="mj-member-events__location-details">';
                if ($location_detail_cover !== '') {
                    $location_thumb_alt = $event['location'] !== '' ? $event['location'] : $event['title'];
                    echo '<img class="mj-member-events__location-thumb" src="' . $location_detail_cover . '" alt="' . esc_attr($location_thumb_alt) . '" loading="lazy" />';
                }
                if ($location_detail_notes !== '') {
                    $location_notes_html = nl2br(esc_html($location_detail_notes));
                    echo '<p class="mj-member-events__location-note">' . $location_notes_html . '</p>';
                }
                echo '</div>';
            }

            $map_embed_src = !empty($event['location_map']) ? esc_url($event['location_map']) : '';
            $map_link_url = !empty($event['location_map_link']) ? esc_url($event['location_map_link']) : '';
            $map_address = !empty($event['location_address']) ? $event['location_address'] : '';
            if ($show_map && $map_embed_src !== '') {
                $map_title = $event['title'] !== ''
                    ? sprintf(__('Localisation : %s', 'mj-member'), $event['title'])
                    : __('Localisation de l\'événement', 'mj-member');
                echo '<div class="mj-member-events__map">';
                echo '<iframe src="' . $map_embed_src . '" loading="lazy" allowfullscreen title="' . esc_attr($map_title) . '" referrerpolicy="no-referrer-when-downgrade"></iframe>';
                if ($map_address !== '') {
                    echo '<p class="mj-member-events__map-address">' . esc_html($map_address) . '</p>';
                }
                if ($map_link_url !== '') {
                    echo '<a class="mj-member-events__map-link" href="' . $map_link_url . '" target="_blank" rel="noopener">' . esc_html__('Ouvrir dans Google Maps', 'mj-member') . '</a>';
                }
                echo '</div>';
            }

            $animateur_registration_preview = array();
            $show_registrations_block = false;
            if ($viewer_is_animateur && $current_member_id > 0 && $animateur_tools_ready && class_exists('MjEventRegistrations')) {
                $assigned_ids = MjEventAnimateurs::get_ids_by_event((int) $event['id']);
                if (!empty($assigned_ids) && in_array($current_member_id, $assigned_ids, true)) {
                    $registrations_raw = MjEventRegistrations::get_by_event((int) $event['id']);
                    if (!empty($registrations_raw)) {
                        foreach ($registrations_raw as $registration_row) {
                            $status_key = isset($registration_row->statut) ? sanitize_key($registration_row->statut) : '';
                            if ($status_key === MjEventRegistrations::STATUS_CANCELLED) {
                                continue;
                            }

                            $name_parts = array();
                            if (!empty($registration_row->first_name)) {
                                $name_parts[] = sanitize_text_field($registration_row->first_name);
                            }
                            if (!empty($registration_row->last_name)) {
                                $name_parts[] = sanitize_text_field($registration_row->last_name);
                            }

                            $participant_label = trim(implode(' ', $name_parts));
                            if ($participant_label === '' && !empty($registration_row->member_id)) {
                                $participant_label = sprintf(__('Membre #%d', 'mj-member'), (int) $registration_row->member_id);
                            }

                            $status_label = isset($registration_status_labels[$status_key]) ? $registration_status_labels[$status_key] : '';

                            $animateur_registration_preview[] = array(
                                'label' => $participant_label,
                                'status' => $status_label,
                            );
                        }
                    }

                    $show_registrations_block = true;
                }
            }

            if ($show_registrations_block) {
                echo '<div class="mj-member-events__registrations">';
                echo '<p class="mj-member-events__registrations-title">' . esc_html__('Participants inscrits', 'mj-member') . '</p>';
                if (!empty($animateur_registration_preview)) {
                    echo '<ul class="mj-member-events__registrations-list">';
                    foreach ($animateur_registration_preview as $registration_entry) {
                        $status_html = '';
                        if (!empty($registration_entry['status'])) {
                            $status_html = '<span class="mj-member-events__registrations-status">' . esc_html($registration_entry['status']) . '</span>';
                        }
                        echo '<li>' . esc_html($registration_entry['label']) . $status_html . '</li>';
                    }
                    echo '</ul>';
                } else {
                    echo '<p class="mj-member-events__registrations-empty">' . esc_html__('Aucune inscription pour le moment.', 'mj-member') . '</p>';
                }
                echo '</div>';
            }

            $should_render_actions = ($show_cta && $registration_open) || !$registration_open;
            if ($should_render_actions) {
                echo '<div class="mj-member-events__actions">';
                if ($registration_open && $show_cta) {
                    $button_classes = array('mj-member-events__cta', 'is-skin-' . $cta_skin);
                    $button_attrs = array(
                        'type' => 'button',
                        'class' => implode(' ', array_map('sanitize_html_class', $button_classes)),
                        'data-event-id' => (int) $event['id'],
                        'data-cta-label' => $cta_label,
                        'data-cta-registered-label' => $cta_registered_label,
                    );

                    if ($event['title'] !== '') {
                        if ($cta_label === __("S'inscrire", 'mj-member')) {
                            $button_attrs['aria-label'] = sprintf(__("S'inscrire à %s", 'mj-member'), $event['title']);
                        } else {
                            $button_attrs['aria-label'] = $cta_label . ' – ' . $event['title'];
                        }
                    } else {
                        $button_attrs['aria-label'] = $cta_label;
                    }

                    if ($is_user_logged_in) {
                        $registration_payload = array(
                            'eventId' => (int) $event['id'],
                            'eventTitle' => $event['title'],
                            'participants' => $event_participants,
                            'allRegistered' => $all_participants_registered,
                            'hasParticipants' => !empty($event_participants),
                            'hasAvailableParticipants' => ($available_count > 0),
                            'noteMaxLength' => 400,
                        );
                        if ($deadline_ts) {
                            $registration_payload['deadline'] = gmdate('c', $deadline_ts);
                        }
                        $registration_payload_json = wp_json_encode($registration_payload);
                        if (!is_string($registration_payload_json)) {
                            $registration_payload_json = wp_json_encode(
                                array(
                                    'eventId' => (int) $event['id'],
                                    'participants' => array(),
                                )
                            );
                        }
                        if (!is_string($registration_payload_json)) {
                            $registration_payload_json = '{}';
                        }
                        $button_attrs['data-registration'] = $registration_payload_json;
                    } else {
                        $button_attrs['data-requires-login'] = '1';
                    }

                    $attr_fragments = array();
                    foreach ($button_attrs as $attr_key => $attr_value) {
                        if (!is_scalar($attr_value)) {
                            continue;
                        }
                        $attr_fragments[] = $attr_key . '="' . esc_attr((string) $attr_value) . '"';
                    }

                    echo '<button ' . implode(' ', $attr_fragments) . '>' . esc_html($cta_label) . '</button>';
                    echo '<div class="mj-member-events__signup" hidden></div>';
                    echo '<div class="mj-member-events__feedback" aria-live="polite"></div>';
                } elseif (!$registration_open) {
                    echo '<span class="mj-member-events__closed">' . esc_html__('Inscriptions clôturées', 'mj-member') . '</span>';
                }
                echo '</div>';
            }

            echo '</div>';
            echo '</article>';
        }

        echo '</div>';
        echo '<p class="mj-member-events__filtered-empty" hidden>' . esc_html__('Aucun événement ne correspond à ce filtre.', 'mj-member') . '</p>';
        echo '</div>';
    }
}
