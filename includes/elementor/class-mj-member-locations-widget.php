<?php

if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Controls_Manager;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;

class Mj_Member_Elementor_Locations_Widget extends Widget_Base {
    public function get_name() {
        return 'mj-member-locations';
    }

    public function get_title() {
        return __('Lieux MJ', 'mj-member');
    }

    public function get_icon() {
        return 'eicon-google-maps';
    }

    public function get_categories() {
        return array('general');
    }

    public function get_keywords() {
        return array('mj', 'map', 'locations', 'lieux', 'google maps');
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
                'default' => __('Nos lieux partenaires', 'mj-member'),
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
            'limit',
            array(
                'label' => __('Nombre maximum de lieux', 'mj-member'),
                'type' => Controls_Manager::NUMBER,
                'min' => 0,
                'max' => 50,
                'default' => 0,
            )
        );

        $this->add_control(
            'orderby',
            array(
                'label' => __('Trier par', 'mj-member'),
                'type' => Controls_Manager::SELECT,
                'default' => 'name',
                'options' => array(
                    'name' => __('Nom', 'mj-member'),
                    'city' => __('Ville', 'mj-member'),
                    'created_at' => __('Date de création', 'mj-member'),
                ),
            )
        );

        $this->add_control(
            'order',
            array(
                'label' => __('Ordre', 'mj-member'),
                'type' => Controls_Manager::SELECT,
                'default' => 'ASC',
                'options' => array(
                    'ASC' => __('Croissant', 'mj-member'),
                    'DESC' => __('Décroissant', 'mj-member'),
                ),
            )
        );

        $this->add_control(
            'enable_type_filter',
            array(
                'label' => __('Activer le filtre par type', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->add_control(
            'filter_all_label',
            array(
                'label' => __('Libellé pour "Tous"', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Tous les lieux', 'mj-member'),
                'label_block' => true,
                'condition' => array('enable_type_filter' => 'yes'),
            )
        );

        $this->add_control(
            'filter_sync_mode',
            array(
                'label' => __('Filtrer les lieux', 'mj-member'),
                'type' => Controls_Manager::SELECT,
                'default' => 'locations_and_events',
                'options' => array(
                    'locations_and_events' => __('Lieux et événements', 'mj-member'),
                    'events_only' => __('Uniquement les événements', 'mj-member'),
                ),
                'condition' => array('enable_type_filter' => 'yes'),
            )
        );

        $this->add_control(
            'linked_events_selector',
            array(
                'label' => __('Sélecteur du widget événements', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'label_block' => true,
                'placeholder' => '.elementor-widget-mj-member-events-list',
                'description' => __('Optionnel : sélecteur CSS du widget événements à filtrer. Laissez vide pour cibler tous les widgets MJ Events de la page.', 'mj-member'),
                'condition' => array('enable_type_filter' => 'yes'),
            )
        );

        $this->add_control(
            'layout',
            array(
                'label' => __('Mode d\'affichage', 'mj-member'),
                'type' => Controls_Manager::SELECT,
                'options' => array(
                    'map' => __('Carte interactive', 'mj-member'),
                    'grid' => __('Grille', 'mj-member'),
                    'slider' => __('Slider horizontal', 'mj-member'),
                ),
                'default' => 'map',
            )
        );

        $this->add_control(
            'show_notes',
            array(
                'label' => __('Afficher les infos pratiques', 'mj-member'),
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
                'label' => __('Afficher la carte Google Maps', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
                'condition' => array('layout' => 'map'),
            )
        );

        $this->add_responsive_control(
            'map_height',
            array(
                'label' => __('Hauteur de la carte', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'range' => array(
                    'px' => array('min' => 240, 'max' => 720),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-locations__map iframe' => 'height: {{SIZE}}{{UNIT}};',
                ),
                'condition' => array('layout' => 'map', 'show_map' => 'yes'),
            )
        );

        $this->add_responsive_control(
            'grid_columns',
            array(
                'label' => __('Colonnes (grille)', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'range' => array(
                    'px' => array('min' => 1, 'max' => 4),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-locations__grid' => 'grid-template-columns: repeat({{SIZE}}, minmax(220px, 1fr));',
                ),
                'condition' => array('layout' => 'grid'),
            )
        );

        $this->add_responsive_control(
            'slider_card_width',
            array(
                'label' => __('Largeur des cartes (slider)', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'range' => array(
                    'px' => array('min' => 180, 'max' => 480),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-locations__slider-card' => 'flex-basis: {{SIZE}}{{UNIT}};',
                ),
                'condition' => array('layout' => 'slider'),
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
                    '{{WRAPPER}} .mj-member-locations__title' => 'text-align: {{VALUE}};',
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
                    '{{WRAPPER}} .mj-member-locations' => '--mj-locations-title: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            array(
                'name' => 'title_typography',
                'label' => __('Typographie du titre', 'mj-member'),
                'global' => array('default' => Global_Typography::TYPOGRAPHY_PRIMARY),
                'selector' => '{{WRAPPER}} .mj-member-locations__title',
            )
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_colors',
            array(
                'label' => __('Couleurs', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'accent_color',
            array(
                'label' => __('Couleur d\'accent', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'global' => array('default' => Global_Colors::COLOR_ACCENT),
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-locations' => '--mj-locations-accent: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'surface_color',
            array(
                'label' => __('Fond des encadrés', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'global' => array('default' => Global_Colors::COLOR_SECONDARY),
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-locations' => '--mj-locations-surface: {{VALUE}};',
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
                    '{{WRAPPER}} .mj-member-locations' => '--mj-locations-border: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'text_color',
            array(
                'label' => __('Couleur du texte', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'global' => array('default' => Global_Colors::COLOR_TEXT),
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-locations' => '--mj-locations-text: {{VALUE}};',
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
                'name' => 'list_typography',
                'label' => __('Liste des lieux', 'mj-member'),
                'global' => array('default' => Global_Typography::TYPOGRAPHY_TEXT),
                'selector' => '{{WRAPPER}} .mj-member-locations__item-label',
            )
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            array(
                'name' => 'detail_typography',
                'label' => __('Détails', 'mj-member'),
                'global' => array('default' => Global_Typography::TYPOGRAPHY_TEXT),
                'selector' => '{{WRAPPER}} .mj-member-locations__detail',
            )
        );

        $this->end_controls_section();
    }

    protected function render() {
        if (!class_exists('MjEventLocations')) {
            echo '<div class="mj-member-locations__warning">' . esc_html__('Le module des lieux MJ doit être actif pour utiliser ce widget.', 'mj-member') . '</div>';
            return;
        }

        $settings = $this->get_settings_for_display();

        $title = isset($settings['title']) ? trim((string) $settings['title']) : '';
        $display_title = !isset($settings['display_title']) || $settings['display_title'] === 'yes';
        $limit = isset($settings['limit']) ? (int) $settings['limit'] : 0;
        $orderby = isset($settings['orderby']) ? sanitize_key($settings['orderby']) : 'name';
        $order = isset($settings['order']) ? sanitize_text_field($settings['order']) : 'ASC';
        $layout = isset($settings['layout']) ? sanitize_key($settings['layout']) : 'map';
        $allowed_layouts = array('map', 'grid', 'slider');
        if (!in_array($layout, $allowed_layouts, true)) {
            $layout = 'map';
        }

        $show_notes = !isset($settings['show_notes']) || $settings['show_notes'] === 'yes';
        $show_map = ($layout === 'map') && (!isset($settings['show_map']) || $settings['show_map'] === 'yes');

        $enable_type_filter = !isset($settings['enable_type_filter']) || $settings['enable_type_filter'] === 'yes';
        $filter_sync_mode = isset($settings['filter_sync_mode']) ? sanitize_key($settings['filter_sync_mode']) : 'locations_and_events';
        $allowed_filter_modes = array('locations_and_events', 'events_only');
        if (!in_array($filter_sync_mode, $allowed_filter_modes, true)) {
            $filter_sync_mode = 'locations_and_events';
        }

        $filter_all_label = isset($settings['filter_all_label']) && $settings['filter_all_label'] !== ''
            ? sanitize_text_field((string) $settings['filter_all_label'])
            : __('Tous les lieux', 'mj-member');

        $linked_events_selector = isset($settings['linked_events_selector']) ? trim((string) $settings['linked_events_selector']) : '';

        $locations = MjEventLocations::get_all(
            array(
                'orderby' => $orderby,
                'order' => $order,
            )
        );

        if (!empty($limit) && $limit > 0) {
            $locations = array_slice($locations, 0, $limit);
        }

        if (empty($locations)) {
            echo '<div class="mj-member-locations__empty">' . esc_html__('Aucun lieu partenaire n\'est disponible pour le moment.', 'mj-member') . '</div>';
            return;
        }

        $processed = array();
        $unique_types = array();
        foreach ($locations as $location) {
            $location = (array) $location;

            $cover_id = isset($location['cover_id']) ? (int) $location['cover_id'] : 0;
            $cover_full = '';
            $cover_thumb = '';
            if ($cover_id > 0) {
                $cover_full = wp_get_attachment_image_url($cover_id, 'large');
                $cover_thumb = wp_get_attachment_image_url($cover_id, 'thumbnail');
            }

            if (!$cover_thumb && $cover_full) {
                $cover_thumb = $cover_full;
            }

            $address_parts = array();
            if (!empty($location['address'])) {
                $address_parts[] = sanitize_text_field($location['address']);
            }

            $city_parts = array();
            if (!empty($location['postal_code'])) {
                $city_parts[] = sanitize_text_field($location['postal_code']);
            }
            if (!empty($location['city'])) {
                $city_parts[] = sanitize_text_field($location['city']);
            }
            if (!empty($city_parts)) {
                $address_parts[] = implode(' ', $city_parts);
            }

            if (!empty($location['country'])) {
                $address_parts[] = sanitize_text_field($location['country']);
            }

            $address = implode(', ', $address_parts);

            $notes_raw = isset($location['notes']) ? (string) $location['notes'] : '';
            $notes_html = $notes_raw !== '' ? wpautop(wp_kses_post($notes_raw)) : '';

            $map_src = MjEventLocations::build_map_embed_src($location);
            $map_link = '';
            if ($map_src !== '') {
                $map_link = str_replace('&output=embed', '', $map_src);
            }

            $type_entries = method_exists('MjEventLocations', 'extract_types') ? MjEventLocations::extract_types($location) : array();
            $type_slugs = array();
            $type_labels = array();
            if (!empty($type_entries)) {
                foreach ($type_entries as $type_entry) {
                    if (!is_array($type_entry)) {
                        continue;
                    }
                    $type_slug = isset($type_entry['slug']) ? sanitize_title($type_entry['slug']) : '';
                    $type_label = isset($type_entry['label']) ? sanitize_text_field($type_entry['label']) : '';
                    if ($type_slug === '' || $type_label === '') {
                        continue;
                    }
                    $type_slugs[] = $type_slug;
                    $type_labels[$type_slug] = $type_label;
                    if (!isset($unique_types[$type_slug])) {
                        $unique_types[$type_slug] = $type_label;
                    }
                }
                $type_slugs = array_values(array_unique($type_slugs));
            }

            $primary_type = '';
            $primary_type_label = '';
            if (!empty($type_slugs)) {
                $primary_type = $type_slugs[0];
                $primary_type_label = isset($type_labels[$primary_type]) ? $type_labels[$primary_type] : '';
            }

            $processed[] = array(
                'id' => isset($location['id']) ? (int) $location['id'] : (count($processed) + 1),
                'name' => isset($location['name']) ? sanitize_text_field($location['name']) : __('Lieu MJ', 'mj-member'),
                'city' => isset($location['city']) ? sanitize_text_field($location['city']) : '',
                'address' => $address,
                'notes' => $show_notes ? $notes_html : '',
                'cover' => $cover_full ? esc_url($cover_full) : '',
                'thumb' => $cover_thumb ? esc_url($cover_thumb) : '',
                'map' => $show_map && $map_src !== '' ? esc_url($map_src) : '',
                'map_link' => $show_map && $map_link !== '' ? esc_url_raw($map_link) : '',
                'type_slugs' => $type_slugs,
                'type_labels' => $type_labels,
                'primary_type' => $primary_type,
                'primary_type_label' => $primary_type_label,
            );
        }

        if (!empty($unique_types)) {
            asort($unique_types, SORT_NATURAL | SORT_FLAG_CASE);
        }

        $instance_id = wp_unique_id('mj-member-locations-');
        $root_classes = array('mj-member-locations', 'mj-member-locations--layout-' . $layout);

        static $styles_printed = false;
        if (!$styles_printed) {
            $styles_printed = true;
            $styles = <<<'CSS'
<style>
.mj-member-locations{display:flex;flex-direction:column;gap:24px;--mj-locations-title:#0f172a;--mj-locations-text:#1f2937;--mj-locations-accent:#2563eb;--mj-locations-surface:#f8fafc;--mj-locations-border:#e2e8f0;--mj-locations-radius:16px;}
.mj-member-locations__title{margin:0;font-size:1.6rem;font-weight:700;color:var(--mj-locations-title);}
.mj-member-locations__filters{display:flex;flex-wrap:wrap;gap:12px;align-items:center;}
.mj-member-locations__filter{border:1px solid var(--mj-locations-border);background:#ffffff;color:var(--mj-locations-text);border-radius:999px;padding:8px 16px;font-weight:600;cursor:pointer;transition:background 0.2s ease,border-color 0.2s ease,color 0.2s ease;}
.mj-member-locations__filter:hover{border-color:var(--mj-locations-accent);}
.mj-member-locations__filter.is-active{background:var(--mj-locations-accent);border-color:var(--mj-locations-accent);color:#ffffff;}
.mj-member-locations--layout-map .mj-member-locations__layout{display:grid;gap:24px;grid-template-columns:minmax(0,1fr);}
.mj-member-locations__panel{display:flex;flex-direction:column;gap:16px;background:var(--mj-locations-surface);border:1px solid var(--mj-locations-border);border-radius:var(--mj-locations-radius);padding:20px;}
.mj-member-locations__map{position:relative;border-radius:12px;overflow:hidden;background:#dbeafe;}
.mj-member-locations__map iframe{display:block;width:100%;height:320px;border:0;}
.mj-member-locations__map-badge{position:absolute;left:12px;top:12px;border-radius:12px;overflow:hidden;width:56px;height:56px;box-shadow:0 8px 20px rgba(15,23,42,0.2);background:#ffffff;display:flex;align-items:center;justify-content:center;}
.mj-member-locations__map-badge img{display:block;width:100%;height:100%;object-fit:cover;}
.mj-member-locations__detail{display:flex;flex-direction:column;gap:12px;color:var(--mj-locations-text);}
.mj-member-locations__detail-header{display:flex;gap:12px;align-items:center;}
.mj-member-locations__detail-cover{flex:0 0 72px;width:72px;height:72px;border-radius:14px;overflow:hidden;background:#e2e8f0;}
.mj-member-locations__detail-cover img{display:block;width:100%;height:100%;object-fit:cover;}
.mj-member-locations__detail-name{margin:0;font-size:1.2rem;font-weight:700;color:var(--mj-locations-title);}
.mj-member-locations__detail-type{margin:0;font-size:0.85rem;font-weight:600;color:var(--mj-locations-accent);}
.mj-member-locations__detail-address{margin:0;font-size:0.95rem;color:var(--mj-locations-text);}
.mj-member-locations__detail-notes{font-size:0.95rem;line-height:1.6;color:var(--mj-locations-text);}
.mj-member-locations__detail-notes p{margin:0 0 10px;}
.mj-member-locations__actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:4px;}
.mj-member-locations__link{display:inline-flex;align-items:center;gap:8px;padding:10px 18px;border-radius:999px;background:var(--mj-locations-accent);color:#ffffff;font-weight:600;text-decoration:none;}
.mj-member-locations__link:hover{opacity:0.9;}
.mj-member-locations__list{display:flex;flex-direction:column;gap:8px;}
.mj-member-locations__item{display:flex;gap:12px;align-items:center;border:1px solid transparent;border-radius:12px;padding:12px;background:transparent;cursor:pointer;transition:border-color 0.2s ease,background 0.2s ease;}
.mj-member-locations__item:hover{background:rgba(37,99,235,0.08);}
.mj-member-locations__item.is-active{border-color:var(--mj-locations-accent);background:rgba(37,99,235,0.12);}
.mj-member-locations__item-thumb{flex:0 0 48px;width:48px;height:48px;border-radius:12px;overflow:hidden;background:#e2e8f0;}
.mj-member-locations__item-thumb img{display:block;width:100%;height:100%;object-fit:cover;}
.mj-member-locations__item-label{display:flex;flex-direction:column;gap:2px;text-align:left;}
.mj-member-locations__item-name{font-weight:600;color:var(--mj-locations-text);}
.mj-member-locations__item-city{font-size:0.85rem;color:#475569;}
.mj-member-locations__grid{display:grid;gap:20px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));}
.mj-member-locations__slider{display:flex;gap:16px;overflow-x:auto;padding-bottom:6px;scroll-snap-type:x mandatory;}
.mj-member-locations__slider::-webkit-scrollbar{height:6px;}
.mj-member-locations__slider::-webkit-scrollbar-thumb{background:rgba(15,23,42,0.2);border-radius:999px;}
.mj-member-locations__card{display:flex;flex-direction:column;gap:12px;background:var(--mj-locations-surface);border:1px solid var(--mj-locations-border);border-radius:var(--mj-locations-radius);padding:18px;box-shadow:0 10px 26px rgba(15,23,42,0.08);}
.mj-member-locations__card-cover{width:100%;border-radius:14px;overflow:hidden;background:#e2e8f0;}
.mj-member-locations__card-cover img{display:block;width:100%;height:100%;object-fit:cover;}
.mj-member-locations__card-name{margin:0;font-size:1.1rem;font-weight:700;color:var(--mj-locations-title);}
.mj-member-locations__card-meta{font-size:0.9rem;color:var(--mj-locations-text);margin:0;}
.mj-member-locations__card-notes{font-size:0.9rem;color:var(--mj-locations-text);margin:0;}
.mj-member-locations__card-actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:auto;}
.mj-member-locations__card-link{display:inline-flex;align-items:center;gap:8px;padding:8px 16px;border-radius:999px;background:var(--mj-locations-accent);color:#ffffff;font-weight:600;text-decoration:none;}
.mj-member-locations__card-link:hover{opacity:0.9;}
.mj-member-locations__slider-card{scroll-snap-align:start;flex:0 0 260px;}
.mj-member-locations__empty{margin:0;font-size:0.95rem;color:#6b7280;}
@media (min-width:960px){.mj-member-locations--layout-map .mj-member-locations__layout{grid-template-columns:1.2fr 0.8fr;}}
</style>
CSS;
            echo $styles;
        }

        $attributes = array('data-locations-instance="' . esc_attr($instance_id) . '"');
        if ($layout === 'map') {
            $payload = wp_json_encode($processed);
            if (!is_string($payload)) {
                $payload = '[]';
            }
            $attributes[] = 'data-locations="' . esc_attr($payload) . '"';
        }

        if ($enable_type_filter && !empty($unique_types)) {
            $attributes[] = 'data-filter-enabled="1"';
            $attributes[] = 'data-filter-sync="' . esc_attr($filter_sync_mode) . '"';
        }

        if ($linked_events_selector !== '') {
            $attributes[] = 'data-linked-events="' . esc_attr($linked_events_selector) . '"';
        }

        $attributes_str = '';
        if (!empty($attributes)) {
            $attributes_str = ' ' . implode(' ', $attributes);
        }

        echo '<div class="' . esc_attr(implode(' ', $root_classes)) . '" id="' . esc_attr($instance_id) . '"' . $attributes_str . '>';
        if ($display_title && $title !== '') {
            echo '<h3 class="mj-member-locations__title">' . esc_html($title) . '</h3>';
        }

        if ($enable_type_filter && !empty($unique_types)) {
            echo '<div class="mj-member-locations__filters" data-location-filters>';
            echo '<button type="button" class="mj-member-locations__filter is-active" data-location-filter="" aria-pressed="true">' . esc_html($filter_all_label) . '</button>';
            foreach ($unique_types as $type_slug => $type_label) {
                echo '<button type="button" class="mj-member-locations__filter" data-location-filter="' . esc_attr($type_slug) . '" aria-pressed="false">' . esc_html($type_label) . '</button>';
            }
            echo '</div>';
        }

        if ($layout === 'map') {
            $primary = $processed[0];
            echo '<div class="mj-member-locations__layout">';
            echo '<div class="mj-member-locations__panel">';
            echo '<div class="mj-member-locations__map" data-locations-map>';
            if ($show_map && $primary['map'] !== '') {
                echo '<iframe src="' . esc_url($primary['map']) . '" loading="lazy" allowfullscreen title="' . esc_attr($primary['name']) . '" referrerpolicy="no-referrer-when-downgrade"></iframe>';
            }
            echo '<div class="mj-member-locations__map-badge" data-locations-map-badge' . ($primary['thumb'] === '' ? ' hidden' : '') . '>';
            if ($primary['thumb'] !== '') {
                echo '<img src="' . esc_url($primary['thumb']) . '" alt="' . esc_attr($primary['name']) . '">';
            }
            echo '</div>';
            echo '</div>';

            echo '<div class="mj-member-locations__detail" data-locations-detail>';
            echo '<div class="mj-member-locations__detail-header">';
            echo '<div class="mj-member-locations__detail-cover" data-locations-detail-cover' . ($primary['thumb'] === '' ? ' hidden' : '') . '>';
            if ($primary['thumb'] !== '') {
                echo '<img src="' . esc_url($primary['thumb']) . '" alt="' . esc_attr($primary['name']) . '">';
            }
            echo '</div>';
            echo '<div>';
            echo '<h4 class="mj-member-locations__detail-name" data-locations-detail-name>' . esc_html($primary['name']) . '</h4>';
            if ($primary['primary_type_label'] !== '') {
                echo '<p class="mj-member-locations__detail-type" data-locations-detail-type>' . esc_html($primary['primary_type_label']) . '</p>';
            } else {
                echo '<p class="mj-member-locations__detail-type" data-locations-detail-type hidden></p>';
            }
            if ($primary['city'] !== '') {
                echo '<p class="mj-member-locations__detail-address" data-locations-detail-city>' . esc_html($primary['city']) . '</p>';
            } else {
                echo '<p class="mj-member-locations__detail-address" data-locations-detail-city hidden></p>';
            }
            if ($primary['address'] !== '') {
                echo '<p class="mj-member-locations__detail-address" data-locations-detail-address>' . esc_html($primary['address']) . '</p>';
            } else {
                echo '<p class="mj-member-locations__detail-address" data-locations-detail-address hidden></p>';
            }
            echo '</div>';
            echo '</div>';

            if ($primary['notes'] !== '') {
                echo '<div class="mj-member-locations__detail-notes" data-locations-detail-notes>' . $primary['notes'] . '</div>';
            } else {
                echo '<div class="mj-member-locations__detail-notes" data-locations-detail-notes hidden></div>';
            }


            echo '</div>';
            echo '</div>';

            echo '<div class="mj-member-locations__panel">';
            echo '<div class="mj-member-locations__list" data-locations-list>';
            foreach ($processed as $index => $location) {
                $is_active = $index === 0 ? ' is-active' : '';
                $types_attr = '';
                if (!empty($location['type_slugs'])) {
                    $types_attr = ' data-location-types="' . esc_attr(implode(',', $location['type_slugs'])) . '"';
                    if (!empty($location['primary_type'])) {
                        $types_attr .= ' data-location-type="' . esc_attr($location['primary_type']) . '"';
                    }
                }
                echo '<button type="button" class="mj-member-locations__item' . $is_active . '" data-location-id="' . esc_attr($location['id']) . '"' . $types_attr . '>';
                echo '<span class="mj-member-locations__item-thumb" data-locations-item-thumb>';
                if ($location['thumb'] !== '') {
                    echo '<img src="' . esc_url($location['thumb']) . '" alt="' . esc_attr($location['name']) . '">';
                }
                echo '</span>';
                echo '<span class="mj-member-locations__item-label">';
                echo '<span class="mj-member-locations__item-name">' . esc_html($location['name']) . '</span>';
                if ($location['city'] !== '') {
                    echo '<span class="mj-member-locations__item-city">' . esc_html($location['city']) . '</span>';
                }
                echo '</span>';
                echo '</button>';
            }
            echo '</div>';
            echo '</div>';
            echo '</div>';
        } elseif ($layout === 'grid') {
            echo '<div class="mj-member-locations__grid">';
            foreach ($processed as $location) {
                $card_types_attr = '';
                if (!empty($location['type_slugs'])) {
                    $card_types_attr = ' data-location-types="' . esc_attr(implode(',', $location['type_slugs'])) . '"';
                }
                echo '<article class="mj-member-locations__card"' . $card_types_attr . '>';
                if ($location['cover'] !== '') {
                    echo '<div class="mj-member-locations__card-cover"><img src="' . esc_url($location['cover']) . '" alt="' . esc_attr($location['name']) . '"></div>';
                }
                echo '<h4 class="mj-member-locations__card-name">' . esc_html($location['name']) . '</h4>';
                if ($location['city'] !== '') {
                    echo '<p class="mj-member-locations__card-meta">' . esc_html($location['city']) . '</p>';
                }
                if ($location['address'] !== '') {
                    echo '<p class="mj-member-locations__card-meta">' . esc_html($location['address']) . '</p>';
                }
                if ($location['notes'] !== '') {
                    echo '<div class="mj-member-locations__card-notes">' . $location['notes'] . '</div>';
                }
                
                echo '</article>';
            }
            echo '</div>';
        } else {
            echo '<div class="mj-member-locations__slider">';
            foreach ($processed as $location) {
                $card_types_attr = '';
                if (!empty($location['type_slugs'])) {
                    $card_types_attr = ' data-location-types="' . esc_attr(implode(',', $location['type_slugs'])) . '"';
                }
                echo '<article class="mj-member-locations__card mj-member-locations__slider-card"' . $card_types_attr . '>';
                if ($location['cover'] !== '') {
                    echo '<div class="mj-member-locations__card-cover"><img src="' . esc_url($location['cover']) . '" alt="' . esc_attr($location['name']) . '"></div>';
                }
                echo '<h4 class="mj-member-locations__card-name">' . esc_html($location['name']) . '</h4>';
                if ($location['city'] !== '') {
                    echo '<p class="mj-member-locations__card-meta">' . esc_html($location['city']) . '</p>';
                }
                if ($location['address'] !== '') {
                    echo '<p class="mj-member-locations__card-meta">' . esc_html($location['address']) . '</p>';
                }
                if ($location['notes'] !== '') {
                    echo '<div class="mj-member-locations__card-notes">' . $location['notes'] . '</div>';
                }

                echo '</article>';
            }
            echo '</div>';
        }

        echo '</div>';

        static $script_printed = false;
        if (!$script_printed) {
            $script_printed = true;
            $script = <<<'JS'
<script>
(function(){
    function ready(fn){
        if(document.readyState === 'loading'){
            document.addEventListener('DOMContentLoaded', fn);
        }else{
            fn();
        }
    }

    function parseTypesAttr(attr){
        if(!attr){
            return [];
        }
        return attr.split(',').map(function(item){
            return item.trim();
        }).filter(function(value){
            return value.length > 0;
        });
    }

    function updateDetail(nodes, entry){
        if(!entry){
            return;
        }

        if(nodes.detail){
            nodes.detail.hidden = false;
        }

        if(nodes.name){
            nodes.name.textContent = entry.name || '';
        }

        if(nodes.type){
            if(entry.primary_type_label){
                nodes.type.textContent = entry.primary_type_label;
                nodes.type.hidden = false;
            }else{
                nodes.type.hidden = true;
            }
        }

        if(nodes.city){
            if(entry.city){
                nodes.city.textContent = entry.city;
                nodes.city.hidden = false;
            }else{
                nodes.city.hidden = true;
            }
        }

        if(nodes.address){
            if(entry.address){
                nodes.address.textContent = entry.address;
                nodes.address.hidden = false;
            }else{
                nodes.address.hidden = true;
            }
        }

        if(nodes.notes){
            if(entry.notes){
                nodes.notes.innerHTML = entry.notes;
                nodes.notes.hidden = false;
            }else{
                nodes.notes.hidden = true;
            }
        }

        if(nodes.cover){
            if(entry.thumb){
                nodes.cover.innerHTML = '<img src="' + entry.thumb + '" alt="' + (entry.name || '') + '">';
                nodes.cover.hidden = false;
            }else{
                nodes.cover.innerHTML = '';
                nodes.cover.hidden = true;
            }
        }

        if(nodes.badge){
            if(entry.thumb){
                nodes.badge.innerHTML = '<img src="' + entry.thumb + '" alt="' + (entry.name || '') + '">';
                nodes.badge.hidden = false;
            }else{
                nodes.badge.innerHTML = '';
                nodes.badge.hidden = true;
            }
        }

        if(nodes.mapWrapper){
            if(nodes.mapFrame && entry.map){
                nodes.mapFrame.src = entry.map;
                nodes.mapFrame.title = entry.name || '';
                nodes.mapWrapper.hidden = false;
            }else if(nodes.mapFrame){
                nodes.mapFrame.removeAttribute('src');
                nodes.mapWrapper.hidden = true;
            }else if(!entry.map){
                nodes.mapWrapper.hidden = true;
            }
        }

        if(nodes.actions){
            if(nodes.mapLink && entry.map_link){
                nodes.mapLink.href = entry.map_link;
                nodes.actions.hidden = false;
            }else{
                nodes.actions.hidden = true;
            }
        }
    }

    function hydrate(root){
        var raw = root.getAttribute('data-locations');
        if(!raw){
            return;
        }
        var entries;
        try{
            entries = JSON.parse(raw);
        }catch(e){
            entries = [];
        }
        if(!Array.isArray(entries) || !entries.length){
            return;
        }

        var mapWrapper = root.querySelector('[data-locations-map]');
        var mapFrame = mapWrapper ? mapWrapper.querySelector('iframe') : null;
        var nodes = {
            name: root.querySelector('[data-locations-detail-name]'),
            city: root.querySelector('[data-locations-detail-city]'),
            address: root.querySelector('[data-locations-detail-address]'),
            notes: root.querySelector('[data-locations-detail-notes]'),
            cover: root.querySelector('[data-locations-detail-cover]'),
            badge: root.querySelector('[data-locations-map-badge]'),
            actions: root.querySelector('[data-locations-detail-actions]'),
            mapLink: root.querySelector('[data-locations-detail-actions] a'),
            mapWrapper: mapWrapper,
            mapFrame: mapFrame,
            type: root.querySelector('[data-locations-detail-type]'),
            detail: root.querySelector('[data-locations-detail]')
        };

        var buttons = Array.prototype.slice.call(root.querySelectorAll('[data-location-id]'));
        if(!buttons.length){
            return;
        }

        var selected = entries[0];
        updateDetail(nodes, selected);

        buttons.forEach(function(button){
            button.addEventListener('click', function(){
                var id = parseInt(button.getAttribute('data-location-id'), 10);
                var target = entries.find(function(entry){
                    return entry.id === id;
                });
                if(!target){
                    return;
                }
                buttons.forEach(function(btn){
                    btn.classList.toggle('is-active', btn === button);
                });
                updateDetail(nodes, target);
            });
        });
    }

    function dispatchEventsFilter(root, slug){
        var detail = {
            type: slug || '',
            instanceId: root.getAttribute('data-locations-instance') || '',
            selector: root.getAttribute('data-linked-events') || ''
        };
        window.dispatchEvent(new CustomEvent('mjMemberEvents:filterByLocationType', { detail: detail }));
    }

    function applyLocationTypeFilter(root, slug){
        var syncMode = root.getAttribute('data-filter-sync') || 'locations_and_events';
        var shouldFilterLocations = syncMode !== 'events_only';
        var normalizedSlug = slug ? slug.toString().trim() : '';
        if(shouldFilterLocations){
            var nodes = Array.prototype.slice.call(root.querySelectorAll('[data-location-types]'));
            var firstVisibleButton = null;
            nodes.forEach(function(node){
                var types = parseTypesAttr(node.getAttribute('data-location-types'));
                var matches = !normalizedSlug || types.indexOf(normalizedSlug) !== -1;
                if(matches){
                    node.removeAttribute('hidden');
                    node.classList.remove('is-filtered-out');
                    if(!firstVisibleButton && node.matches('[data-location-id]')){
                        firstVisibleButton = node;
                    }
                }else{
                    node.setAttribute('hidden', 'hidden');
                    node.classList.add('is-filtered-out');
                    if(node.matches('[data-location-id]')){
                        node.classList.remove('is-active');
                    }
                }
            });

            if(root.classList.contains('mj-member-locations--layout-map')){
                var detailNode = root.querySelector('[data-locations-detail]');
                var mapWrapper = root.querySelector('[data-locations-map]');
                var activeButton = root.querySelector('.mj-member-locations__item.is-active:not([hidden])');
                if(activeButton){
                    activeButton.click();
                }else if(firstVisibleButton){
                    if(detailNode){
                        detailNode.hidden = false;
                    }
                    if(mapWrapper){
                        mapWrapper.hidden = false;
                    }
                    firstVisibleButton.click();
                }else{
                    if(detailNode){
                        detailNode.hidden = true;
                    }
                    if(mapWrapper){
                        mapWrapper.hidden = true;
                    }
                }
            }
        }
        dispatchEventsFilter(root, normalizedSlug);
    }

    function initTypeFilters(root){
        if(!root || root.getAttribute('data-filter-enabled') !== '1'){
            return;
        }
        var controls = Array.prototype.slice.call(root.querySelectorAll('[data-location-filter]'));
        if(!controls.length){
            return;
        }

        controls.forEach(function(control){
            control.addEventListener('click', function(){
                if(control.classList.contains('is-active')){
                    return;
                }
                controls.forEach(function(button){
                    var isActive = button === control;
                    button.classList.toggle('is-active', isActive);
                    if(button.hasAttribute('aria-pressed')){
                        button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                    }
                });
                var slug = control.getAttribute('data-location-filter') || '';
                applyLocationTypeFilter(root, slug);
            });
        });
    }

    ready(function(){
        var widgets = Array.prototype.slice.call(document.querySelectorAll('.mj-member-locations'));
        widgets.forEach(function(root){
            initTypeFilters(root);
        });
        document.querySelectorAll('[data-locations]').forEach(hydrate);
        widgets.forEach(function(root){
            if(root.getAttribute('data-filter-enabled') === '1'){
                var activeControl = root.querySelector('[data-location-filter].is-active');
                if(activeControl){
                    var initialSlug = activeControl.getAttribute('data-location-filter') || '';
                    if(initialSlug){
                        applyLocationTypeFilter(root, initialSlug);
                    }
                }
            }
        });
    });
})();
</script>
JS;
            echo $script;
        }
    }
}
