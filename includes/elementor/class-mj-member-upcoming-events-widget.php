<?php

if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Controls_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Utils;
use Elementor\Widget_Base;
use Mj\Member\Classes\Crud\MjEvents;
use Mj\Member\Core\AssetsManager;
use Mj\Member\Core\Config;

class Mj_Member_Elementor_Upcoming_Events_Widget extends Widget_Base {
    use Mj_Member_Elementor_Widget_Visibility;

    public function get_name() {
        return 'mj-member-upcoming-events';
    }

    public function get_title() {
        return __('Prochains événements MJ', 'mj-member');
    }

    public function get_icon() {
        return 'eicon-slider-push';
    }

    public function get_categories() {
        return array('mj-member');
    }

    public function get_keywords() {
        return array('mj', 'evenement', 'events', 'agenda', 'slider', 'liste');
    }

    public function get_script_depends() {
        return array('mj-member-upcoming-events');
    }

    protected function register_controls() {
        $event_types = method_exists('MjEvents', 'get_type_labels') ? MjEvents::get_type_labels() : array();

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
                'default' => __('Prochains événements', 'mj-member'),
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
            'layout',
            array(
                'label' => __('Disposition', 'mj-member'),
                'type' => Controls_Manager::SELECT,
                'options' => array(
                    'list' => __('Liste verticale', 'mj-member'),
                    'grid' => __('Grille', 'mj-member'),
                    'slider' => __('Slider', 'mj-member'),
                ),
                'default' => 'list',
            )
        );

        $this->add_control(
            'columns',
            array(
                'label' => __('Colonnes (grille)', 'mj-member'),
                'type' => Controls_Manager::SELECT,
                'options' => array(
                    '2' => '2',
                    '3' => '3',
                    '4' => '4',
                    '5' => '5',
                ),
                'default' => '3',
                'condition' => array('layout' => 'grid'),
            )
        );

        $this->add_control(
            'slides_per_view',
            array(
                'label' => __('Éléments visibles (slider)', 'mj-member'),
                'type' => Controls_Manager::NUMBER,
                'min' => 1,
                'max' => 5,
                'step' => 1,
                'default' => 1,
                'condition' => array('layout' => 'slider'),
            )
        );

        $this->add_control(
            'max_items',
            array(
                'label' => __('Nombre d’événements', 'mj-member'),
                'type' => Controls_Manager::NUMBER,
                'min' => 1,
                'max' => 12,
                'step' => 1,
                'default' => 4,
            )
        );

        $this->add_control(
            'order',
            array(
                'label' => __('Ordre', 'mj-member'),
                'type' => Controls_Manager::SELECT,
                'options' => array(
                    'ASC' => __('Du plus proche au plus lointain', 'mj-member'),
                    'DESC' => __('Inversé', 'mj-member'),
                ),
                'default' => 'ASC',
            )
        );

        $this->add_control(
            'orderby',
            array(
                'label' => __('Trier selon', 'mj-member'),
                'type' => Controls_Manager::SELECT,
                'options' => array(
                    'date_debut' => __('Date de début', 'mj-member'),
                    'date_fin' => __('Date de fin', 'mj-member'),
                    'updated_at' => __('Dernière mise à jour', 'mj-member'),
                ),
                'default' => 'date_debut',
            )
        );

        $this->add_control(
            'types',
            array(
                'label' => __('Filtrer par type', 'mj-member'),
                'type' => Controls_Manager::SELECT2,
                'multiple' => true,
                'label_block' => true,
                'options' => $event_types,
            )
        );

        $this->add_control(
            'show_type_badge',
            array(
                'label' => __('Afficher le type', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
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
            'show_excerpt',
            array(
                'label' => __('Afficher l’extrait', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'no',
            )
        );

        $this->add_control(
            'excerpt_length',
            array(
                'label' => __('Longueur de l’extrait (mots)', 'mj-member'),
                'type' => Controls_Manager::NUMBER,
                'min' => 5,
                'max' => 60,
                'default' => 20,
                'condition' => array('show_excerpt' => 'yes'),
            )
        );

        $this->add_control(
            'price_display',
            array(
                'label' => __('Affichage du tarif', 'mj-member'),
                'type' => Controls_Manager::SELECT,
                'options' => array(
                    'auto' => __('Automatique (masque si 0 €)', 'mj-member'),
                    'show' => __('Toujours afficher', 'mj-member'),
                    'hide' => __('Masquer', 'mj-member'),
                ),
                'default' => 'auto',
            )
        );

        $this->add_control(
            'price_prefix',
            array(
                'label' => __('Préfixe du tarif', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Tarif', 'mj-member'),
                'placeholder' => __('Tarif', 'mj-member'),
                'condition' => array('price_display!' => 'hide'),
            )
        );

        $this->add_control(
            'empty_message',
            array(
                'label' => __('Message si aucun événement', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Aucun événement prévu pour le moment.', 'mj-member'),
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
            'show_view_more',
            array(
                'label' => __('Afficher un bouton « Voir plus »', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->add_control(
            'view_more_label',
            array(
                'label' => __('Texte du bouton', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Voir tous les événements', 'mj-member'),
                'label_block' => true,
                'condition' => array('show_view_more' => 'yes'),
            )
        );

        $this->add_control(
            'view_more_url',
            array(
                'label' => __('Lien du bouton', 'mj-member'),
                'type' => Controls_Manager::URL,
                'dynamic' => array('active' => true),
                'condition' => array('show_view_more' => 'yes'),
            )
        );

        $this->add_control(
            'view_more_alignment',
            array(
                'label' => __('Alignement du bouton', 'mj-member'),
                'type' => Controls_Manager::CHOOSE,
                'options' => array(
                    'left' => array('title' => __('Gauche', 'mj-member'), 'icon' => 'eicon-text-align-left'),
                    'center' => array('title' => __('Centre', 'mj-member'), 'icon' => 'eicon-text-align-center'),
                    'right' => array('title' => __('Droite', 'mj-member'), 'icon' => 'eicon-text-align-right'),
                ),
                'default' => 'center',
                'condition' => array('show_view_more' => 'yes'),
            )
        );

        $this->add_control(
            'slider_autoplay',
            array(
                'label' => __('Lecture automatique', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'no',
                'condition' => array('layout' => 'slider'),
            )
        );

        $this->add_control(
            'slider_autoplay_delay',
            array(
                'label' => __('Intervalle (secondes)', 'mj-member'),
                'type' => Controls_Manager::NUMBER,
                'min' => 2,
                'max' => 20,
                'step' => 1,
                'default' => 6,
                'condition' => array(
                    'layout' => 'slider',
                    'slider_autoplay' => 'yes',
                ),
            )
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_contact_icons',
            array(
                'label' => __('Icônes de contact', 'mj-member'),
            )
        );

        $this->add_control(
            'show_contact_icons',
            array(
                'label' => __('Afficher les icônes de contact', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->add_control(
            'contact_whatsapp_url',
            array(
                'label' => __('Lien WhatsApp', 'mj-member'),
                'type' => Controls_Manager::URL,
                'placeholder' => __('https://wa.me/32470123456', 'mj-member'),
                'dynamic' => array('active' => true),
                'condition' => array('show_contact_icons' => 'yes'),
            )
        );

        $this->add_control(
            'contact_whatsapp_label',
            array(
                'label' => __('Libellé WhatsApp', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => __('WhatsApp', 'mj-member'),
                'label_block' => true,
                'condition' => array('show_contact_icons' => 'yes'),
            )
        );

        $this->add_control(
            'contact_email_address',
            array(
                'label' => __('Adresse e-mail', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'placeholder' => __('contact@exemple.be', 'mj-member'),
                'label_block' => true,
                'condition' => array('show_contact_icons' => 'yes'),
            )
        );

        $this->add_control(
            'contact_email_label',
            array(
                'label' => __('Libellé e-mail', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Envoyer un mail', 'mj-member'),
                'label_block' => true,
                'condition' => array('show_contact_icons' => 'yes'),
            )
        );

        $this->add_control(
            'contact_icons_note',
            array(
                'type' => Controls_Manager::RAW_HTML,
                'raw' => __('Vous pouvez laisser un champ vide pour masquer l’icône correspondante.', 'mj-member'),
                'content_classes' => 'elementor-descriptor',
                'condition' => array('show_contact_icons' => 'yes'),
            )
        );

        $this->end_controls_section();

        $this->register_visibility_controls();

        $this->start_controls_section(
            'section_style_cards',
            array(
                'label' => __('Cartes', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'card_background',
            array(
                'label' => __('Fond', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-upcoming-events__item' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'card_text_color',
            array(
                'label' => __('Texte', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-upcoming-events__item' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .mj-upcoming-events__meta, {{WRAPPER}} .mj-upcoming-events__price' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            array(
                'name' => 'card_title_typography',
                'label' => __('Titre', 'mj-member'),
                'selector' => '{{WRAPPER}} .mj-upcoming-events__item-title',
            )
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            array(
                'name' => 'card_border',
                'selector' => '{{WRAPPER}} .mj-upcoming-events__item',
            )
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            array(
                'name' => 'card_shadow',
                'selector' => '{{WRAPPER}} .mj-upcoming-events__item',
            )
        );

        $this->add_group_control(
            Group_Control_Background::get_type(),
            array(
                'name' => 'badge_background',
                'label' => __('Fond du badge', 'mj-member'),
                'selector' => '{{WRAPPER}} .mj-upcoming-events__badge',
                'types' => array('classic', 'gradient'),
            )
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_button',
            array(
                'label' => __('Bouton « Voir plus »', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
                'condition' => array('show_view_more' => 'yes'),
            )
        );

        $this->add_control(
            'button_text_color',
            array(
                'label' => __('Texte', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-upcoming-events__view-more' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'button_background_color',
            array(
                'label' => __('Fond', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-upcoming-events__view-more' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            array(
                'name' => 'button_border',
                'selector' => '{{WRAPPER}} .mj-upcoming-events__view-more',
            )
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            array(
                'name' => 'button_shadow',
                'selector' => '{{WRAPPER}} .mj-upcoming-events__view-more',
            )
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $this->apply_visibility_to_wrapper($settings, 'mj-member-upcoming-events-widget');

        $layout = isset($settings['layout']) ? sanitize_key($settings['layout']) : 'list';
        if (!in_array($layout, array('list', 'grid', 'slider'), true)) {
            $layout = 'list';
        }

        $max_items = isset($settings['max_items']) ? (int) $settings['max_items'] : 4;
        if ($max_items <= 0) {
            $max_items = 4;
        }
        $max_items = min($max_items, 20);

        $order = isset($settings['order']) ? strtoupper((string) $settings['order']) : 'ASC';
        if (!in_array($order, array('ASC', 'DESC'), true)) {
            $order = 'ASC';
        }

        $allowed_orderby = array('date_debut', 'date_fin', 'updated_at');
        $orderby = isset($settings['orderby']) ? sanitize_key($settings['orderby']) : 'date_debut';
        if (!in_array($orderby, $allowed_orderby, true)) {
            $orderby = 'date_debut';
        }

        $type_filters = array();
        if (!empty($settings['types']) && is_array($settings['types'])) {
            foreach ($settings['types'] as $type_candidate) {
                $type_candidate = sanitize_key($type_candidate);
                if ($type_candidate === '') {
                    continue;
                }
                $type_filters[$type_candidate] = $type_candidate;
            }
        }

        $query_args = array(
            'limit' => $max_items,
            'order' => $order,
            'orderby' => $orderby,
        );

        if (!empty($type_filters)) {
            $query_args['types'] = array_values($type_filters);
        }

        $events = function_exists('mj_member_get_upcoming_events')
            ? mj_member_get_upcoming_events($query_args)
            : array();

        $type_labels = method_exists('MjEvents', 'get_type_labels') ? MjEvents::get_type_labels() : array();

        $fallback_image = '';
        if (!empty($settings['fallback_image']['id'])) {
            $fallback_image = wp_get_attachment_image_url((int) $settings['fallback_image']['id'], 'large');
        }
        if (!$fallback_image && !empty($settings['fallback_image']['url'])) {
            $fallback_image = esc_url_raw($settings['fallback_image']['url']);
        }

        $show_cover = !isset($settings['show_cover']) || $settings['show_cover'] === 'yes';
        $show_location = !isset($settings['show_location']) || $settings['show_location'] === 'yes';
        $show_type_badge = !isset($settings['show_type_badge']) || $settings['show_type_badge'] === 'yes';
        $show_excerpt = isset($settings['show_excerpt']) && $settings['show_excerpt'] === 'yes';
        $excerpt_length = isset($settings['excerpt_length']) ? (int) $settings['excerpt_length'] : 20;
        if ($excerpt_length <= 0) {
            $excerpt_length = 20;
        }

        $price_display = isset($settings['price_display']) ? sanitize_key($settings['price_display']) : 'auto';
        if (!in_array($price_display, array('auto', 'show', 'hide'), true)) {
            $price_display = 'auto';
        }
        $price_prefix = isset($settings['price_prefix']) ? sanitize_text_field((string) $settings['price_prefix']) : __('Tarif', 'mj-member');

        $is_preview = $this->is_elementor_preview_mode();
        if ($is_preview && empty($events)) {
            $events = $this->build_preview_events($max_items, $type_labels);
        }

        $prepared_events = array();
        $time_format = get_option('time_format', 'H:i');
        if (!empty($events)) {
            foreach ($events as $event) {
                if (!is_array($event)) {
                    continue;
                }

                $title = isset($event['title']) ? $event['title'] : '';
                if ($title === '') {
                    continue;
                }

                $permalink = '';
                if (!empty($event['permalink'])) {
                    $permalink = esc_url($event['permalink']);
                } elseif (!empty($event['article_permalink'])) {
                    $permalink = esc_url($event['article_permalink']);
                }

                $cover_url = '';
                if (!empty($event['cover_url'])) {
                    $cover_url = esc_url($event['cover_url']);
                } elseif (!empty($event['article_cover_url'])) {
                    $cover_url = esc_url($event['article_cover_url']);
                } elseif ($fallback_image) {
                    $cover_url = esc_url($fallback_image);
                }
                if (!$show_cover) {
                    $cover_url = '';
                }

                $start_date = isset($event['start_date']) ? $event['start_date'] : '';
                $end_date = isset($event['end_date']) ? $event['end_date'] : '';
                $start_timestamp = $start_date !== '' ? strtotime($start_date) : false;
                $end_timestamp = $end_date !== '' ? strtotime($end_date) : false;

                $date_label = $this->format_event_date_label($start_timestamp, $end_timestamp);
                if ($date_label === '' && function_exists('mj_member_format_event_datetime_range')) {
                    $fallback_label = mj_member_format_event_datetime_range($start_date, $end_date);
                    if (is_string($fallback_label)) {
                        $date_label = $fallback_label;
                    }
                }

                $time_label = $this->format_event_time_label($start_timestamp, $end_timestamp, $time_format);

                $location_label = '';
                if ($show_location) {
                    if (!empty($event['raw_location_name'])) {
                        $location_label = $event['raw_location_name'];
                    } elseif (!empty($event['location'])) {
                        $location_label = $event['location'];
                    }
                    $location_label = sanitize_text_field($location_label);
                }

                $location_address = '';
                if ($show_location && !empty($event['location_address'])) {
                    $location_address_candidate = sanitize_text_field((string) $event['location_address']);
                    if (is_string($location_address_candidate) && $location_address_candidate !== '') {
                        $location_address = $location_address_candidate;
                    }
                }

                $location_initials = '';
                if ($location_label !== '') {
                    $trimmed_location = trim($location_label);
                    if ($trimmed_location !== '') {
                        if (function_exists('mb_substr')) {
                            $location_initials = mb_substr($trimmed_location, 0, 1, 'UTF-8');
                        } else {
                            $location_initials = substr($trimmed_location, 0, 1);
                        }
                        if ($location_initials !== '') {
                            if (function_exists('mb_strtoupper')) {
                                $location_initials = mb_strtoupper($location_initials, 'UTF-8');
                            } else {
                                $location_initials = strtoupper($location_initials);
                            }
                        }
                    }
                }

                $type_label = '';
                $type_key = isset($event['type']) ? sanitize_key((string) $event['type']) : '';
                if ($type_key !== '') {
                    if (isset($type_labels[$type_key])) {
                        $type_label = sanitize_text_field($type_labels[$type_key]);
                    } else {
                        $type_label = ucfirst($type_key);
                    }
                }
                if (!$show_type_badge) {
                    $type_label = '';
                }

                $accent_color = '';
                if (!empty($event['accent_color'])) {
                    $accent_candidate = $event['accent_color'];
                    if (function_exists('mj_member_normalize_hex_color_value')) {
                        $accent_candidate = mj_member_normalize_hex_color_value($accent_candidate);
                    } else {
                        $accent_candidate = sanitize_hex_color($accent_candidate);
                    }
                    if (is_string($accent_candidate) && $accent_candidate !== '') {
                        $accent_color = strtoupper($accent_candidate);
                    }
                }

                $accent_overlay = '';
                if ($accent_color !== '') {
                    $accent_overlay_candidate = $this->build_accent_overlay_color($accent_color);
                    if ($accent_overlay_candidate !== '') {
                        $accent_overlay = $accent_overlay_candidate;
                    }
                }

                $price_raw = isset($event['price']) ? (float) $event['price'] : 0.0;
                $display_price = false;
                if ($price_display === 'show') {
                    $display_price = true;
                } elseif ($price_display === 'auto') {
                    $display_price = $price_raw > 0;
                }

                $price_label = '';
                if ($display_price) {
                    $formatted_price = number_format_i18n($price_raw, ($price_raw === (float) (int) $price_raw) ? 0 : 2);
                    if ($formatted_price !== '') {
                        $price_label = trim($price_prefix) !== ''
                            ? sprintf('%s : %s €', $price_prefix, $formatted_price)
                            : sprintf('%s €', $formatted_price);
                    }
                }

                $excerpt_value = '';
                if ($show_excerpt && !empty($event['excerpt'])) {
                    $excerpt_value = wp_trim_words(wp_strip_all_tags((string) $event['excerpt']), $excerpt_length, '...');
                }

                $prepared_events[] = array(
                    'id' => isset($event['id']) ? (int) $event['id'] : 0,
                    'title' => sanitize_text_field($title),
                    'permalink' => $permalink,
                    'cover_url' => $cover_url,
                    'date_label' => $date_label !== '' ? sanitize_text_field($date_label) : '',
                    'time_label' => $time_label !== '' ? sanitize_text_field($time_label) : '',
                    'location_label' => $location_label !== '' ? sanitize_text_field($location_label) : '',
                    'location_address' => $location_address !== '' ? sanitize_text_field($location_address) : '',
                    'location_initials' => $location_initials !== '' ? sanitize_text_field($location_initials) : '',
                    'type_label' => $type_label !== '' ? sanitize_text_field($type_label) : '',
                    'accent_color' => $accent_color,
                    'accent_overlay' => $accent_overlay !== '' ? sanitize_text_field($accent_overlay) : '',
                    'price_label' => $price_label !== '' ? sanitize_text_field($price_label) : '',
                    'excerpt' => $excerpt_value !== '' ? sanitize_text_field($excerpt_value) : '',
                );
            }
        }

        $empty_message = isset($settings['empty_message']) ? $settings['empty_message'] : '';
        if ($empty_message === '') {
            $empty_message = __('Aucun événement prévu pour le moment.', 'mj-member');
        }

        $view_more_enabled = isset($settings['show_view_more']) && $settings['show_view_more'] === 'yes';
        $view_more_label = isset($settings['view_more_label']) ? $settings['view_more_label'] : '';
        if ($view_more_label === '') {
            $view_more_label = __('Voir tous les événements', 'mj-member');
        }
        $view_more_url = '';
        $view_more_target = '';
        $view_more_rel = '';
        if ($view_more_enabled && !empty($settings['view_more_url']['url'])) {
            $view_more_url = esc_url($settings['view_more_url']['url']);
            if (!empty($settings['view_more_url']['is_external'])) {
                $view_more_target = '_blank';
            }
            if (!empty($settings['view_more_url']['nofollow'])) {
                $view_more_rel = trim('nofollow noopener');
            } elseif ($view_more_target === '_blank') {
                $view_more_rel = 'noopener';
            }
        }

        $view_more_alignment = isset($settings['view_more_alignment']) ? sanitize_key($settings['view_more_alignment']) : 'center';
        if (!in_array($view_more_alignment, array('left', 'center', 'right'), true)) {
            $view_more_alignment = 'center';
        }

        $columns = isset($settings['columns']) ? (int) $settings['columns'] : 3;
        if ($columns < 2) {
            $columns = 2;
        }
        $columns = min($columns, 5);

        $slides_per_view = isset($settings['slides_per_view']) ? (int) $settings['slides_per_view'] : 1;
        if ($slides_per_view <= 0) {
            $slides_per_view = 1;
        }
        $slides_per_view = min($slides_per_view, 5);

        $autoplay = isset($settings['slider_autoplay']) && $settings['slider_autoplay'] === 'yes';
        $autoplay_delay = isset($settings['slider_autoplay_delay']) ? (int) $settings['slider_autoplay_delay'] : 6;
        if ($autoplay_delay <= 0) {
            $autoplay_delay = 6;
        }

        $contact_icons_enabled = isset($settings['show_contact_icons']) && $settings['show_contact_icons'] === 'yes';
        $contact_links = array(
            'enabled' => false,
            'whatsapp' => array('url' => '', 'label' => '', 'target' => '', 'rel' => ''),
            'email' => array('url' => '', 'label' => '', 'target' => '', 'rel' => ''),
        );

        if ($contact_icons_enabled) {
            $whatsapp_url = '';
            $whatsapp_label = isset($settings['contact_whatsapp_label']) ? sanitize_text_field((string) $settings['contact_whatsapp_label']) : __('WhatsApp', 'mj-member');
            $whatsapp_target = '';
            $whatsapp_rel = '';
            if (!empty($settings['contact_whatsapp_url']['url'])) {
                $whatsapp_url_candidate = esc_url_raw($settings['contact_whatsapp_url']['url']);
                if (is_string($whatsapp_url_candidate) && $whatsapp_url_candidate !== '') {
                    $whatsapp_url = $whatsapp_url_candidate;
                }
            }
            if (!empty($settings['contact_whatsapp_url']['is_external'])) {
                $whatsapp_target = '_blank';
            }
            if (!empty($settings['contact_whatsapp_url']['nofollow'])) {
                $whatsapp_rel = trim('nofollow noopener');
            } elseif ($whatsapp_target === '_blank') {
                $whatsapp_rel = 'noopener';
            }

            $email_url = '';
            $email_label = isset($settings['contact_email_label']) ? sanitize_text_field((string) $settings['contact_email_label']) : __('Envoyer un mail', 'mj-member');
            if (!empty($settings['contact_email_address'])) {
                $email_candidate = sanitize_email((string) $settings['contact_email_address']);
                if (is_string($email_candidate) && $email_candidate !== '') {
                    $email_url = 'mailto:' . $email_candidate;
                }
            }

            if ($whatsapp_url !== '') {
                $contact_links['whatsapp']['url'] = $whatsapp_url;
                $contact_links['whatsapp']['label'] = $whatsapp_label !== '' ? $whatsapp_label : __('WhatsApp', 'mj-member');
                $contact_links['whatsapp']['target'] = $whatsapp_target;
                $contact_links['whatsapp']['rel'] = $whatsapp_rel;
            }

            if ($email_url !== '') {
                $contact_links['email']['url'] = $email_url;
                $contact_links['email']['label'] = $email_label !== '' ? $email_label : __('Envoyer un mail', 'mj-member');
                $contact_links['email']['target'] = '';
                $contact_links['email']['rel'] = '';
            }

            if ($contact_links['whatsapp']['url'] !== '' || $contact_links['email']['url'] !== '') {
                $contact_links['enabled'] = true;
            }
        }

        $instance_id = wp_unique_id('mj-upcoming-events-');

        AssetsManager::requirePackage('upcoming-events');

        $template_path = Config::path() . 'includes/templates/elementor/upcoming_events.php';
        if (!file_exists($template_path)) {
            echo '<div class="mj-upcoming-events__warning">' . esc_html__('Le template du widget est introuvable.', 'mj-member') . '</div>';
            return;
        }

        $template_data = array(
            'instance_id' => $instance_id,
            'layout' => $layout,
            'title' => isset($settings['title']) ? $settings['title'] : '',
            'display_title' => !isset($settings['display_title']) || $settings['display_title'] === 'yes',
            'events' => $prepared_events,
            'empty_message' => $empty_message,
            'columns' => $columns,
            'slides_per_view' => $slides_per_view,
            'autoplay' => $autoplay,
            'autoplay_delay' => $autoplay_delay,
            'view_more' => array(
                'enabled' => $view_more_enabled && $view_more_url !== '',
                'label' => $view_more_label,
                'url' => $view_more_url,
                'target' => $view_more_target,
                'rel' => $view_more_rel,
                'alignment' => $view_more_alignment,
            ),
            'contact_links' => $contact_links,
        );

        include $template_path;
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

    private function format_event_date_label($start_timestamp, $end_timestamp) {
        $date_format = get_option('date_format', 'd/m/Y');

        if ($start_timestamp && $end_timestamp) {
            if (wp_date('Y-m-d', $start_timestamp) === wp_date('Y-m-d', $end_timestamp)) {
                return wp_date($date_format, $start_timestamp);
            }

            return sprintf(
                '%s - %s',
                wp_date($date_format, $start_timestamp),
                wp_date($date_format, $end_timestamp)
            );
        }

        if ($start_timestamp) {
            return wp_date($date_format, $start_timestamp);
        }

        if ($end_timestamp) {
            return wp_date($date_format, $end_timestamp);
        }

        return '';
    }

    private function format_event_time_label($start_timestamp, $end_timestamp, $time_format) {
        if (!is_string($time_format) || $time_format === '') {
            $time_format = get_option('time_format', 'H:i');
        }

        $start_time = '';
        if ($start_timestamp) {
            $start_time = wp_date($time_format, $start_timestamp);
        }

        $end_time = '';
        if ($end_timestamp) {
            $end_time = wp_date($time_format, $end_timestamp);
        }

        if ($start_time !== '' && $end_time !== '' && $start_timestamp && $end_timestamp) {
            if (wp_date('Y-m-d', $start_timestamp) === wp_date('Y-m-d', $end_timestamp)) {
                if ($start_time === $end_time) {
                    return sprintf(__('À partir de %s', 'mj-member'), $start_time);
                }

                return sprintf('%s - %s', $start_time, $end_time);
            }
        }

        if ($start_time !== '') {
            return sprintf(__('À partir de %s', 'mj-member'), $start_time);
        }

        if ($end_time !== '') {
            return sprintf(__('Jusqu\'à %s', 'mj-member'), $end_time);
        }

        return '';
    }

    private function build_accent_overlay_color($hex_color, $alpha = 0.28) {
        if (!is_string($hex_color) || trim($hex_color) === '') {
            return '';
        }

        $normalized = sanitize_hex_color($hex_color);
        if (!is_string($normalized) || $normalized === '') {
            return '';
        }

        $normalized = ltrim($normalized, '#');
        if (strlen($normalized) === 3) {
            $normalized = $normalized[0] . $normalized[0]
                . $normalized[1] . $normalized[1]
                . $normalized[2] . $normalized[2];
        }

        if (strlen($normalized) !== 6) {
            return '';
        }

        $red = hexdec(substr($normalized, 0, 2));
        $green = hexdec(substr($normalized, 2, 2));
        $blue = hexdec(substr($normalized, 4, 2));

        $alpha_value = max(0.05, min(1.0, (float) $alpha));

        return sprintf('rgba(%d, %d, %d, %.2F)', $red, $green, $blue, $alpha_value);
    }

    private function build_preview_events($limit, $type_labels) {
        $preview = array();
        $now = current_time('timestamp');
        $types = array_keys($type_labels);
        if (empty($types)) {
            $types = array('stage', 'atelier', 'sortie');
        }
        $type_colors = method_exists('MjEvents', 'get_type_colors') ? MjEvents::get_type_colors() : array();

        for ($i = 0; $i < $limit; $i++) {
            $start = $now + ($i + 1) * DAY_IN_SECONDS;
            $end = $start + 2 * HOUR_IN_SECONDS;
            $type_key = $types[$i % count($types)];
            $accent_color = isset($type_colors[$type_key]) ? $type_colors[$type_key] : '#2563EB';
            $preview[] = array(
                'id' => $i + 1,
                'title' => sprintf(__('Événement démo %d', 'mj-member'), $i + 1),
                'permalink' => home_url('/evenements/demo-' . ($i + 1)),
                'cover_url' => '',
                'start_date' => wp_date('Y-m-d H:i:s', $start),
                'end_date' => wp_date('Y-m-d H:i:s', $end),
                'type' => $type_key,
                'price' => $i % 2 === 0 ? 15.0 : 0.0,
                'location' => __('Maison des Jeunes', 'mj-member'),
                'excerpt' => __('Description de démonstration pour l’aperçu Elementor.', 'mj-member'),
                'accent_color' => $accent_color,
                'accent_overlay' => $this->build_accent_overlay_color($accent_color),
            );
        }

        return $preview;
    }
}
