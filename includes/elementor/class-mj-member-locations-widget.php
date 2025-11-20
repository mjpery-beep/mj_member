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
                'condition' => array('show_map' => 'yes'),
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
        $show_notes = !isset($settings['show_notes']) || $settings['show_notes'] === 'yes';
        $show_map = !isset($settings['show_map']) || $settings['show_map'] === 'yes';

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

            $map_src = MjEventLocations::build_map_embed_src($location);
            $map_link = '';
            if ($map_src !== '') {
                $map_link = str_replace('&output=embed', '', $map_src);
                $map_link = str_replace('?output=embed', '', $map_link);
            }

            $address = MjEventLocations::format_address($location);
            $notes_raw = isset($location['notes']) ? trim((string) $location['notes']) : '';
            $notes_html = $notes_raw !== '' ? wpautop(esc_html($notes_raw)) : '';

            $processed[] = array(
                'id' => isset($location['id']) ? (int) $location['id'] : count($processed) + 1,
                'name' => isset($location['name']) ? sanitize_text_field($location['name']) : __('Lieu MJ', 'mj-member'),
                'city' => isset($location['city']) ? sanitize_text_field($location['city']) : '',
                'address' => $address,
                'notes' => $show_notes ? $notes_html : '',
                'cover' => $cover_full ? esc_url($cover_full) : '',
                'thumb' => $cover_thumb ? esc_url($cover_thumb) : '',
                'map' => $show_map ? esc_url($map_src) : '',
                'map_link' => $show_map && $map_link !== '' ? esc_url($map_link) : '',
            );
        }

        $primary = $processed[0];
        $payload = wp_json_encode($processed);
        if (!is_string($payload)) {
            $payload = '[]';
        }

        $instance_id = wp_unique_id('mj-member-locations-');

        static $styles_printed = false;
        if (!$styles_printed) {
            $styles_printed = true;
            echo '<style>'
                . '.mj-member-locations{display:flex;flex-direction:column;gap:24px;--mj-locations-title:#0f172a;--mj-locations-text:#1f2937;--mj-locations-accent:#2563eb;--mj-locations-surface:#f8fafc;--mj-locations-border:#e2e8f0;--mj-locations-radius:16px;}'
                . '.mj-member-locations__title{margin:0;font-size:1.6rem;font-weight:700;color:var(--mj-locations-title);}'
                . '.mj-member-locations__layout{display:grid;gap:24px;grid-template-columns:minmax(0,1fr);}'
                . '.mj-member-locations__panel{display:flex;flex-direction:column;gap:16px;background:var(--mj-locations-surface);border:1px solid var(--mj-locations-border);border-radius:var(--mj-locations-radius);padding:20px;}'
                . '.mj-member-locations__map{position:relative;border-radius:12px;overflow:hidden;background:#dbeafe;}'
                . '.mj-member-locations__map iframe{display:block;width:100%;height:320px;border:0;}'
                . '.mj-member-locations__detail{display:flex;flex-direction:column;gap:12px;color:var(--mj-locations-text);}'
                . '.mj-member-locations__detail-header{display:flex;gap:12px;align-items:center;}'
                . '.mj-member-locations__detail-cover{flex:0 0 72px;width:72px;height:72px;border-radius:14px;overflow:hidden;background:#e2e8f0;}'
                . '.mj-member-locations__detail-cover img{display:block;width:100%;height:100%;object-fit:cover;}'
                . '.mj-member-locations__detail-name{margin:0;font-size:1.2rem;font-weight:700;color:var(--mj-locations-title);}'
                . '.mj-member-locations__detail-address{margin:0;font-size:0.95rem;color:var(--mj-locations-text);}'
                . '.mj-member-locations__detail-notes{font-size:0.95rem;line-height:1.6;color:var(--mj-locations-text);}'
                . '.mj-member-locations__detail-notes p{margin:0 0 10px;}'
                . '.mj-member-locations__actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:4px;}'
                . '.mj-member-locations__link{display:inline-flex;align-items:center;gap:8px;padding:10px 18px;border-radius:999px;background:var(--mj-locations-accent);color:#ffffff;font-weight:600;text-decoration:none;}'
                . '.mj-member-locations__link:hover{opacity:0.9;}'
                . '.mj-member-locations__list{display:flex;flex-direction:column;gap:8px;}'
                . '.mj-member-locations__item{display:flex;gap:12px;align-items:center;border:1px solid transparent;border-radius:12px;padding:12px;background:transparent;cursor:pointer;transition:border-color 0.2s ease,background 0.2s ease;}'
                . '.mj-member-locations__item:hover{background:rgba(37,99,235,0.08);}'
                . '.mj-member-locations__item.is-active{border-color:var(--mj-locations-accent);background:rgba(37,99,235,0.12);}'
                . '.mj-member-locations__item-thumb{flex:0 0 48px;width:48px;height:48px;border-radius:12px;overflow:hidden;background:#e2e8f0;}'
                . '.mj-member-locations__item-thumb img{display:block;width:100%;height:100%;object-fit:cover;}'
                . '.mj-member-locations__item-label{display:flex;flex-direction:column;gap:2px;text-align:left;}'
                . '.mj-member-locations__item-name{font-weight:600;color:var(--mj-locations-text);}'
                . '.mj-member-locations__item-city{font-size:0.85rem;color:#475569;}'
                . '.mj-member-locations__empty{margin:0;font-size:0.95rem;color:#6b7280;}'
                . '@media (min-width:960px){.mj-member-locations__layout{grid-template-columns:1.2fr 0.8fr;}}'
                . '</style>';
        }

        echo '<div class="mj-member-locations" id="' . esc_attr($instance_id) . '" data-locations="' . esc_attr($payload) . '">';
        if ($display_title && $title !== '') {
            echo '<h3 class="mj-member-locations__title">' . esc_html($title) . '</h3>';
        }

        echo '<div class="mj-member-locations__layout">';
        echo '<div class="mj-member-locations__panel">';
        if ($show_map && $primary['map'] !== '') {
            echo '<div class="mj-member-locations__map" data-locations-map><iframe src="' . esc_url($primary['map']) . '" loading="lazy" allowfullscreen title="' . esc_attr($primary['name']) . '" referrerpolicy="no-referrer-when-downgrade"></iframe></div>';
        } else {
            echo '<div class="mj-member-locations__map" data-locations-map hidden></div>';
        }

        echo '<div class="mj-member-locations__detail" data-locations-detail>';
        echo '<div class="mj-member-locations__detail-header">';
        if ($primary['thumb'] !== '') {
            echo '<span class="mj-member-locations__detail-cover" data-locations-detail-cover><img src="' . esc_url($primary['thumb']) . '" alt="' . esc_attr($primary['name']) . '" loading="lazy"></span>';
        } else {
            echo '<span class="mj-member-locations__detail-cover" data-locations-detail-cover hidden></span>';
        }
        echo '<div>';
        echo '<h4 class="mj-member-locations__detail-name" data-locations-detail-name>' . esc_html($primary['name']) . '</h4>';
        if ($primary['city'] !== '') {
            echo '<p class="mj-member-locations__detail-address" data-locations-detail-city>' . esc_html($primary['city']) . '</p>';
        } else {
            echo '<p class="mj-member-locations__detail-address" data-locations-detail-city hidden></p>';
        }
        echo '</div>';
        echo '</div>';

        if ($primary['address'] !== '') {
            echo '<p class="mj-member-locations__detail-address" data-locations-detail-address>' . esc_html($primary['address']) . '</p>';
        } else {
            echo '<p class="mj-member-locations__detail-address" data-locations-detail-address hidden></p>';
        }

        if ($primary['notes'] !== '') {
            echo '<div class="mj-member-locations__detail-notes" data-locations-detail-notes>' . $primary['notes'] . '</div>';
        } else {
            echo '<div class="mj-member-locations__detail-notes" data-locations-detail-notes hidden></div>';
        }

        echo '<div class="mj-member-locations__actions">';
        if ($primary['map_link'] !== '') {
            echo '<a class="mj-member-locations__link" data-locations-detail-link href="' . esc_url($primary['map_link']) . '" target="_blank" rel="noopener">' . esc_html__('Ouvrir dans Google Maps', 'mj-member') . '</a>';
        } else {
            echo '<a class="mj-member-locations__link" data-locations-detail-link href="#" hidden></a>';
        }
        echo '</div>';

        echo '</div>';
        echo '</div>';

        echo '<div class="mj-member-locations__panel">';
        echo '<div class="mj-member-locations__list" role="list">';
        foreach ($processed as $index => $location) {
            $is_active = $index === 0;
            $item_classes = array('mj-member-locations__item');
            if ($is_active) {
                $item_classes[] = 'is-active';
            }
            echo '<button type="button" class="' . esc_attr(implode(' ', $item_classes)) . '" data-location-trigger="' . esc_attr((string) $location['id']) . '" role="listitem">';
            if ($location['thumb'] !== '') {
                echo '<span class="mj-member-locations__item-thumb"><img src="' . esc_url($location['thumb']) . '" alt="' . esc_attr($location['name']) . '" loading="lazy"></span>';
            } else {
                echo '<span class="mj-member-locations__item-thumb" aria-hidden="true"></span>';
            }
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

        echo '</div>';

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

    function parseLocations(root){
        var raw = root.getAttribute('data-locations');
        var store = {};
        if (!raw) {
            return store;
        }
        try {
            var list = JSON.parse(raw);
            if (Array.isArray(list)) {
                list.forEach(function(entry){
                    if (!entry || typeof entry.id === 'undefined') {
                        return;
                    }
                    store[String(entry.id)] = entry;
                });
            }
        } catch (error) {}
        return store;
    }

    function updateDetail(context, payload){
        if (!payload) {
            return;
        }
        var mapWrapper = context.map;
        if (mapWrapper) {
            if (payload.map) {
                if (!mapWrapper.firstChild) {
                    var iframe = document.createElement('iframe');
                    iframe.loading = 'lazy';
                    iframe.allowFullscreen = true;
                    iframe.referrerPolicy = 'no-referrer-when-downgrade';
                    mapWrapper.appendChild(iframe);
                }
                mapWrapper.firstChild.src = payload.map;
                mapWrapper.firstChild.title = payload.name || '';
                mapWrapper.removeAttribute('hidden');
            } else {
                mapWrapper.setAttribute('hidden', 'hidden');
            }
        }

        if (context.name) {
            context.name.textContent = payload.name || '';
        }
        if (context.city) {
            if (payload.city) {
                context.city.textContent = payload.city;
                context.city.removeAttribute('hidden');
            } else {
                context.city.textContent = '';
                context.city.setAttribute('hidden', 'hidden');
            }
        }
        if (context.address) {
            if (payload.address) {
                context.address.textContent = payload.address;
                context.address.removeAttribute('hidden');
            } else {
                context.address.textContent = '';
                context.address.setAttribute('hidden', 'hidden');
            }
        }
        if (context.notes) {
            if (payload.notes) {
                context.notes.innerHTML = payload.notes;
                context.notes.removeAttribute('hidden');
            } else {
                context.notes.textContent = '';
                context.notes.setAttribute('hidden', 'hidden');
            }
        }
        if (context.cover) {
            if (payload.thumb) {
                context.cover.innerHTML = '';
                var coverImg = document.createElement('img');
                coverImg.src = payload.thumb;
                coverImg.alt = payload.name || '';
                coverImg.loading = 'lazy';
                context.cover.appendChild(coverImg);
                context.cover.removeAttribute('hidden');
            } else {
                context.cover.innerHTML = '';
                context.cover.setAttribute('hidden', 'hidden');
            }
        }
        if (context.link) {
            if (payload.map_link) {
                context.link.href = payload.map_link;
                context.link.textContent = context.link.dataset.label || context.link.textContent || '';
                context.link.removeAttribute('hidden');
            } else {
                context.link.href = '#';
                context.link.setAttribute('hidden', 'hidden');
            }
        }
    }

    function initWidget(root){
        if (!root) {
            return;
        }
        var locations = parseLocations(root);
        if (!Object.keys(locations).length) {
            return;
        }
        var mapWrapper = root.querySelector('[data-locations-map]');
        var detail = {
            map: mapWrapper,
            cover: root.querySelector('[data-locations-detail-cover]'),
            name: root.querySelector('[data-locations-detail-name]'),
            city: root.querySelector('[data-locations-detail-city]'),
            address: root.querySelector('[data-locations-detail-address]'),
            notes: root.querySelector('[data-locations-detail-notes]'),
            link: root.querySelector('[data-locations-detail-link]')
        };
        if (detail.link) {
            detail.link.dataset.label = detail.link.textContent;
        }

        var triggers = Array.prototype.slice.call(root.querySelectorAll('[data-location-trigger]'));
        var activeId = triggers.length ? triggers[0].getAttribute('data-location-trigger') : null;

        if (activeId && locations[activeId]) {
            updateDetail(detail, locations[activeId]);
        }

        root.addEventListener('click', function(evt){
            var trigger = evt.target.closest('[data-location-trigger]');
            if (!trigger) {
                return;
            }
            evt.preventDefault();
            var locationId = trigger.getAttribute('data-location-trigger');
            if (!locationId || !locations[locationId]) {
                return;
            }
            triggers.forEach(function(button){
                if (button === trigger) {
                    button.classList.add('is-active');
                } else {
                    button.classList.remove('is-active');
                }
            });
            updateDetail(detail, locations[locationId]);
        });
    }

    function drainQueue(){
        if (!window.mjMemberLocationsQueue) {
            return;
        }
        while (window.mjMemberLocationsQueue.length) {
            var id = window.mjMemberLocationsQueue.shift();
            var root = document.getElementById(id);
            if (root) {
                initWidget(root);
            }
        }
    }

    ready(drainQueue);
})();
JS;
            echo '<script>' . $script . '</script>';
        }

        echo '<script>window.mjMemberLocationsQueue = window.mjMemberLocationsQueue || [];window.mjMemberLocationsQueue.push(' . wp_json_encode($instance_id) . ');</script>';
    }
}
