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
            'show_price',
            array(
                'label' => __('Afficher le tarif', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->add_control(
            'hide_price_if_zero',
            array(
                'label' => __('Masquer si tarif à 0 €', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
                'condition' => array('show_price' => 'yes'),
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
                'name' => 'card_border',
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
        $show_price = isset($settings['show_price']) && $settings['show_price'] === 'yes';
        $hide_price_if_zero = isset($settings['hide_price_if_zero']) && $settings['hide_price_if_zero'] === 'yes';
        $layout = isset($settings['layout']) ? $settings['layout'] : 'grid';
        $card_layout = isset($settings['card_layout']) ? $settings['card_layout'] : 'standard';
        $allowed_card_layouts = array('standard', 'compact', 'horizontal');
        if (!in_array($card_layout, $allowed_card_layouts, true)) {
            $card_layout = 'standard';
        }

        $fallback_url = '';
        if (!empty($settings['fallback_image']['id'])) {
            $fallback_url = wp_get_attachment_image_url((int) $settings['fallback_image']['id'], 'large');
        }
        if (!$fallback_url && !empty($settings['fallback_image']['url'])) {
            $fallback_url = esc_url_raw($settings['fallback_image']['url']);
        }

        $type_labels = method_exists('MjEvents_CRUD', 'get_type_labels') ? MjEvents_CRUD::get_type_labels() : array();

        static $styles_printed = false;
        if (!$styles_printed) {
            $styles_printed = true;
            echo '<style>'
                . '.mj-member-events{display:flex;flex-direction:column;gap:24px;--mj-events-title-color:#0f172a;--mj-events-card-bg:#ffffff;--mj-events-border:#e3e6ea;--mj-events-border-soft:#e2e8f0;--mj-events-card-title:#0f172a;--mj-events-meta:#4b5563;--mj-events-excerpt:#475569;--mj-events-accent:#2563eb;--mj-events-accent-contrast:#ffffff;--mj-events-radius:14px;--mj-events-button-bg:#2563eb;--mj-events-button-hover:#1d4ed8;--mj-events-button-text:#ffffff;--mj-events-button-radius:999px;--mj-events-surface-soft:#f8fafc;}'
                . '.mj-member-events__title{margin:0;font-size:1.75rem;font-weight:700;color:var(--mj-events-title-color);}'
                . '.mj-member-events__grid{display:grid;gap:20px;}'
                . '.mj-member-events__grid.is-grid{grid-template-columns:repeat(auto-fit,minmax(240px,1fr));}'
                . '.mj-member-events__grid.is-list{grid-template-columns:1fr;}'
                . '.mj-member-events__item{border:1px solid var(--mj-events-border);border-radius:var(--mj-events-radius);overflow:hidden;background:var(--mj-events-card-bg);display:flex;flex-direction:column;transition:box-shadow 0.2s ease,transform 0.2s ease;}'
                . '.mj-member-events__item.layout-horizontal{flex-direction:row;}'
                . '.mj-member-events__item.layout-horizontal .mj-member-events__item-body{flex:1;}'
                . '.mj-member-events__item.layout-compact{border-radius:calc(var(--mj-events-radius) - 2px);}'
                . '.mj-member-events__item:hover{box-shadow:0 18px 40px rgba(15,23,42,0.12);transform:translateY(-2px);}'
                . '.mj-member-events__cover{position:relative;padding-bottom:56%;overflow:hidden;background:var(--mj-events-surface-soft);}'
                . '.mj-member-events__cover.is-horizontal{flex:0 0 280px;padding-bottom:0;min-height:220px;}'
                . '.mj-member-events__cover img{position:absolute;top:0;left:0;width:100%;height:100%;object-fit:cover;}'
                . '.mj-member-events__cover.is-horizontal img{position:static;height:100%;}'
                . '.mj-member-events__item-body{display:flex;flex-direction:column;gap:12px;padding:20px;}'
                . '.mj-member-events__item.layout-compact .mj-member-events__item-body{padding:16px;gap:8px;}'
                . '.mj-member-events__item.layout-compact .mj-member-events__meta{font-size:0.85rem;}'
                . '.mj-member-events__item-title{margin:0;font-size:1.1rem;font-weight:700;color:var(--mj-events-card-title);}'
                . '.mj-member-events__item-title a{text-decoration:none;color:inherit;}'
                . '.mj-member-events__item-title a:hover{color:var(--mj-events-accent);}'
                . '.mj-member-events__meta{font-size:0.9rem;color:var(--mj-events-meta);display:flex;flex-wrap:wrap;gap:8px;}'
                . '.mj-member-events__excerpt{margin:0;color:var(--mj-events-excerpt);font-size:0.95rem;line-height:1.5;}'
                . '.mj-member-events__badge{display:inline-flex;align-items:center;gap:6px;background:var(--mj-events-accent);color:var(--mj-events-accent-contrast);font-weight:600;border-radius:999px;padding:4px 10px;font-size:0.75rem;text-transform:uppercase;letter-spacing:0.04em;}'
                . '.mj-member-events__price{font-weight:700;color:var(--mj-events-card-title);}'
                . '.mj-member-events__actions{margin-top:auto;display:flex;flex-direction:column;gap:12px;}'
                . '.mj-member-events__cta{display:inline-flex;align-items:center;gap:8px;background:var(--mj-events-button-bg);color:var(--mj-events-button-text);border:none;border-radius:var(--mj-events-button-radius);padding:10px 18px;font-weight:600;cursor:pointer;transition:background 0.2s ease,transform 0.2s ease,box-shadow 0.2s ease;}'
                . '.mj-member-events__cta:hover{background:var(--mj-events-button-hover);transform:translateY(-1px);box-shadow:0 12px 32px rgba(37,99,235,0.25);}'
                . '.mj-member-events__cta:disabled,.mj-member-events__cta[aria-disabled="true"]{opacity:0.6;cursor:not-allowed;transform:none;box-shadow:none;}'
                . '.mj-member-events__cta.is-registered{background:#059669;}'
                . '.mj-member-events__signup{display:none;border:1px solid var(--mj-events-border-soft);border-radius:12px;padding:16px;background:var(--mj-events-surface-soft);}'
                . '.mj-member-events__signup.is-open{display:block;}'
                . '.mj-member-events__signup-title{margin:0 0 12px;font-size:0.95rem;font-weight:600;color:var(--mj-events-card-title);}'
                . '.mj-member-events__signup-options{margin:0 0 16px;padding:0;list-style:none;display:flex;flex-direction:column;gap:12px;}'
                . '.mj-member-events__signup-option{margin:0;display:flex;align-items:center;gap:12px;}'
                . '.mj-member-events__signup-label{display:flex;align-items:center;gap:10px;font-weight:600;color:var(--mj-events-card-title);flex:1;}'
                . '.mj-member-events__signup-radio{width:18px;height:18px;}'
                . '.mj-member-events__signup-name{font-size:0.95rem;}'
                . '.mj-member-events__signup-option.is-registered .mj-member-events__signup-label{opacity:0.65;}'
                . '.mj-member-events__signup-controls{margin-left:auto;display:flex;align-items:center;gap:8px;}'
                . '.mj-member-events__signup-toggle{background:none;border:1px solid var(--mj-events-border-soft);border-radius:999px;padding:6px 14px;font-size:0.85rem;font-weight:600;color:#b91c1c;cursor:pointer;transition:background 0.2s ease,color 0.2s ease;}'
                . '.mj-member-events__signup-toggle:hover{background:rgba(185,28,28,0.08);color:#7f1d1d;}'
                . '.mj-member-events__signup-toggle:disabled{opacity:0.6;cursor:not-allowed;}'
                . '.mj-member-events__signup-status{font-size:0.8rem;color:#059669;font-weight:600;}'
                . '.mj-member-events__signup-empty{margin:0 0 12px;font-size:0.9rem;color:var(--mj-events-meta);}'
                . '.mj-member-events__signup-info{margin:0 0 12px;font-size:0.9rem;font-weight:600;color:var(--mj-events-card-title);}'
                . '.mj-member-events__signup-note{display:flex;flex-direction:column;gap:6px;margin-bottom:16px;}'
                . '.mj-member-events__signup-note label{font-size:0.9rem;font-weight:600;color:var(--mj-events-card-title);}'
                . '.mj-member-events__signup-note textarea{min-height:80px;border:1px solid var(--mj-events-border-soft);border-radius:10px;padding:10px 12px;font-size:0.95rem;resize:vertical;background:#ffffff;}'
                . '.mj-member-events__signup-note textarea:focus{outline:2px solid var(--mj-events-accent);outline-offset:2px;}'
                . '.mj-member-events__signup-actions{display:flex;align-items:center;gap:12px;}'
                . '.mj-member-events__signup-submit{display:inline-flex;align-items:center;gap:8px;background:#0f172a;color:#ffffff;border:none;border-radius:10px;padding:10px 18px;font-weight:600;cursor:pointer;transition:background 0.2s ease;}'
                . '.mj-member-events__signup-submit:hover{background:#1e293b;}'
                . '.mj-member-events__signup-submit:disabled{opacity:0.6;cursor:not-allowed;}'
                . '.mj-member-events__signup-cancel{background:none;border:none;color:var(--mj-events-meta);font-weight:600;cursor:pointer;text-decoration:underline;padding:0;}'
                . '.mj-member-events__signup-feedback{margin-top:12px;font-size:0.85rem;color:var(--mj-events-card-title);}'
                . '.mj-member-events__signup-feedback.is-error{color:#b91c1c;}'
                . '.mj-member-events__location-details{display:flex;gap:12px;align-items:flex-start;background:var(--mj-events-surface-soft);border-radius:12px;padding:12px 14px;color:var(--mj-events-card-title);}'
                . '.mj-member-events__location-thumb{flex:0 0 56px;width:56px;height:56px;border-radius:12px;object-fit:cover;border:1px solid var(--mj-events-border-soft);}'
                . '.mj-member-events__location-note{margin:0;font-size:0.9rem;line-height:1.5;color:var(--mj-events-meta);}'
                . '.mj-member-events__map{margin-top:16px;border-radius:12px;overflow:hidden;background:var(--mj-events-surface-soft);box-shadow:0 6px 16px rgba(15,23,42,0.08);}'
                . '.mj-member-events__map iframe{display:block;width:100%;height:220px;border:0;}'
                . '.mj-member-events__map-address{margin:12px 16px 0;font-size:0.9rem;color:var(--mj-events-card-title);font-weight:500;}'
                . '.mj-member-events__map-link{display:inline-block;margin:10px 16px 16px;font-size:0.85rem;font-weight:600;color:var(--mj-events-accent);text-decoration:none;}'
                . '.mj-member-events__map-link:hover{text-decoration:underline;}'
                . '.mj-member-events__registrations{margin-top:16px;padding:14px;border:1px solid var(--mj-events-border-soft);border-radius:12px;background:#eef2ff;color:var(--mj-events-card-title);}'
                . '.mj-member-events__registrations-title{margin:0 0 8px;font-size:0.95rem;font-weight:600;color:#1e293b;}'
                . '.mj-member-events__registrations-list{margin:0;padding-left:18px;list-style:disc;font-size:0.9rem;color:var(--mj-events-card-title);}'
                . '.mj-member-events__registrations-list li{margin-bottom:4px;}'
                . '.mj-member-events__registrations-status{font-size:0.75rem;font-weight:600;color:var(--mj-events-accent);margin-left:6px;text-transform:uppercase;}'
                . '.mj-member-events__registrations-empty{margin:0;font-size:0.9rem;color:var(--mj-events-meta);}'
                . '.mj-member-events__feedback{font-size:0.9rem;font-weight:600;color:#059669;}'
                . '.mj-member-events__feedback.is-error{color:#b91c1c;}'
                . '.mj-member-events__closed{font-size:0.95rem;font-weight:600;color:#ef4444;}'
                . '.mj-member-events__empty{margin:0;font-size:0.95rem;color:#6b7280;}'
                . '</style>';
        }

        echo '<div class="mj-member-events">';
        if ($display_title && $title !== '') {
            echo '<h3 class="mj-member-events__title">' . esc_html($title) . '</h3>';
        }

        if (empty($events)) {
            echo '<p class="mj-member-events__empty">' . esc_html($empty_message) . '</p>';
            echo '</div>';
            return;
        }

        wp_enqueue_script('mj-member-events-widget');

        static $script_localized = false;
        if (!$script_localized) {
            $script_localized = true;
            wp_localize_script(
                'mj-member-events-widget',
                'mjMemberEventsWidget',
                array(
                    'ajaxUrl' => esc_url_raw(admin_url('admin-ajax.php')),
                    'nonce' => wp_create_nonce('mj-member-event-register'),
                    'loginUrl' => esc_url_raw(wp_login_url()),
                    'strings' => array(
                        'chooseParticipant' => __('Qui participera ?', 'mj-member'),
                        'confirm' => __('Confirmer l\'inscription', 'mj-member'),
                        'cancel' => __('Annuler', 'mj-member'),
                        'loginRequired' => __('Connectez-vous pour continuer.', 'mj-member'),
                        'selectParticipant' => __('Merci de sélectionner un participant.', 'mj-member'),
                        'genericError' => __('Une erreur est survenue. Merci de réessayer.', 'mj-member'),
                        'registered' => __('Inscription envoyée', 'mj-member'),
                        'success' => __('Inscription enregistrée !', 'mj-member'),
                        'closed' => __('Inscriptions clôturées', 'mj-member'),
                        'loading' => __('En cours...', 'mj-member'),
                        'noParticipant' => __("Aucun profil disponible pour l'instant.", 'mj-member'),
                        'alreadyRegistered' => __('Déjà inscrit', 'mj-member'),
                        'allRegistered' => __('Tous les profils sont déjà inscrits pour cet événement.', 'mj-member'),
                        'noteLabel' => __('Message pour l’équipe (optionnel)', 'mj-member'),
                        'notePlaceholder' => __('Précisez une remarque utile (allergies, arrivée tardive, etc.).', 'mj-member'),
                        'cta' => __("S'inscrire", 'mj-member'),
                        'unregister' => __('Se désinscrire', 'mj-member'),
                        'unregisterConfirm' => __('Annuler cette inscription ?', 'mj-member'),
                        'unregisterSuccess' => __('Inscription annulée.', 'mj-member'),
                        'unregisterError' => __('Impossible d\'annuler l\'inscription.', 'mj-member'),
                    ),
                )
            );
        }

        $grid_classes = array('mj-member-events__grid');
        $grid_classes[] = $layout === 'list' ? 'is-list' : 'is-grid';
        echo '<div class="' . esc_attr(implode(' ', $grid_classes)) . '">';

        foreach ($events as $event) {
            $cover_url = !empty($event['cover_url']) ? esc_url($event['cover_url']) : ($fallback_url ? esc_url($fallback_url) : '');
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

            $date_range = mj_member_format_event_datetime_range($event['start_date'], $event['end_date']);
            $permalink = !empty($event['permalink']) ? esc_url($event['permalink']) : '';
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

            $card_classes = array('mj-member-events__item', 'layout-' . $resolved_layout);
            $card_classes[] = $cover_url !== '' ? 'has-cover' : 'no-cover';
            $card_class_attr = implode(' ', array_map('sanitize_html_class', $card_classes));

            echo '<article class="' . esc_attr($card_class_attr) . '">';

            $should_show_cover = ($cover_url !== '') && $resolved_layout !== 'compact';
            if ($should_show_cover) {
                $cover_classes = array('mj-member-events__cover');
                if ($resolved_layout === 'horizontal') {
                    $cover_classes[] = 'is-horizontal';
                }
                $cover_attr = implode(' ', array_map('sanitize_html_class', $cover_classes));
                $image_alt = !empty($event['raw_location_name']) ? $event['raw_location_name'] : $event['title'];
                echo '<div class="' . esc_attr($cover_attr) . '">';
                echo '<img src="' . $cover_url . '" alt="' . esc_attr($image_alt) . '" loading="lazy" />';
                echo '</div>';
            }

            echo '<div class="mj-member-events__item-body">';

            if ($type_label !== '') {
                echo '<span class="mj-member-events__badge">' . esc_html($type_label) . '</span>';
            }

            if ($permalink) {
                echo '<h4 class="mj-member-events__item-title"><a href="' . $permalink . '">' . esc_html($event['title']) . '</a></h4>';
            } else {
                echo '<h4 class="mj-member-events__item-title">' . esc_html($event['title']) . '</h4>';
            }

            $meta_parts = array();
            if ($date_range !== '') {
                $meta_parts[] = $date_range;
            }
            if ($show_location && !empty($event['location'])) {
                $meta_parts[] = $event['location'];
            }
            if ($show_price) {
                $price_value = isset($event['price']) ? (float) $event['price'] : 0.0;
                $is_zero_price = abs($price_value) < 0.01;
                if (!$hide_price_if_zero || !$is_zero_price) {
                    $meta_parts[] = sprintf(__('Tarif : %s €', 'mj-member'), number_format_i18n($price_value, 2));
                }
            }

            if (!empty($meta_parts)) {
                echo '<div class="mj-member-events__meta">' . esc_html(implode(' • ', $meta_parts)) . '</div>';
            }

            $display_excerpt = $show_description && !empty($event['excerpt']);
            if ($display_excerpt && $resolved_layout === 'compact') {
                $display_excerpt = strlen($event['excerpt']) <= 180;
            }
            if ($display_excerpt) {
                echo '<p class="mj-member-events__excerpt">' . esc_html($event['excerpt']) . '</p>';
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

            echo '<div class="mj-member-events__actions">';
            if ($registration_open) {
                $button_label = __("S'inscrire", 'mj-member');
                $button_attrs = array(
                    'type' => 'button',
                    'class' => 'mj-member-events__cta',
                    'data-event-id' => (int) $event['id'],
                );
                $aria_label_text = $event['title'] !== ''
                    ? sprintf(__("S'inscrire à %s", 'mj-member'), $event['title'])
                    : __("S'inscrire à l'événement", 'mj-member');
                $button_attrs['aria-label'] = $aria_label_text;

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

                echo '<button ' . implode(' ', $attr_fragments) . '>' . esc_html($button_label) . '</button>';
            } else {
                echo '<span class="mj-member-events__closed">' . esc_html__('Inscriptions clôturées', 'mj-member') . '</span>';
            }
            echo '<div class="mj-member-events__signup" hidden></div>';
            echo '<div class="mj-member-events__feedback" aria-live="polite"></div>';
            echo '</div>';

            echo '</div>';
            echo '</article>';
        }

        echo '</div>';
        echo '</div>';
    }
}
