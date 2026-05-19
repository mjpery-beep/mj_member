<?php

if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Controls_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Modules\NestedElements\Base\Widget_Nested_Base;
use Elementor\Modules\NestedElements\Controls\Control_Nested_Repeater;
use Elementor\Plugin;
use Elementor\Repeater;
use Mj\Member\Core\AssetsManager;
use Mj\Member\Core\Config;

class Mj_Member_Elementor_Draggable_Modal_Icon_Widget extends Widget_Nested_Base {
    use Mj_Member_Elementor_Widget_Visibility;

    public function get_name() {
        return 'mj-member-draggable-modal-icon';
    }

    public function get_title() {
        return __('MJ - Icone draggable modal', 'mj-member');
    }

    public function get_icon() {
        return 'eicon-drag-n-drop';
    }

    public function get_categories() {
        return array('mj-member');
    }

    public function get_keywords() {
        return array('mj', 'modal', 'icone', 'draggable', 'container', 'popup');
    }

    public function get_style_depends() {
        return array('mj-member-components', 'mj-member-draggable-modal-icon');
    }

    public function get_script_depends() {
        return array('mj-member-draggable-modal-icon');
    }

    public function show_in_panel(): bool {
        return Plugin::$instance->experiments->is_feature_active('nested-elements', true);
    }

    protected function get_default_children_elements() {
        return array(
            array(
                'elType' => 'container',
                'settings' => array(
                    '_title' => __('Contenu modal', 'mj-member'),
                    'content_width' => 'full',
                ),
            ),
        );
    }

    protected function get_default_children_title() {
        return esc_html__('Contenu #%d', 'mj-member');
    }

    protected function get_default_repeater_title_setting_key() {
        return 'panel_title';
    }

    protected function get_default_children_placeholder_selector() {
        return '.mj-dmiw__modal-content';
    }

    protected function register_controls() {
        $this->start_controls_section(
            'section_content',
            array(
                'label' => __('Contenu', 'mj-member'),
            )
        );

        $this->add_control(
            'trigger_icon',
            array(
                'label' => __('Icone du trigger', 'mj-member'),
                'type' => Controls_Manager::MEDIA,
                'media_types' => array('image', 'svg'),
                'description' => __('Choisissez l\'icone a deplacer et a cliquer/taper.', 'mj-member'),
            )
        );

        $this->add_control(
            'modal_title',
            array(
                'label' => __('Titre de la modal', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Contenu', 'mj-member'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'show_icon_in_header',
            array(
                'label' => __('Afficher l\'icône dans le header', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => '',
                'description' => __('Affiche l\'icône du trigger à gauche du titre dans le header de la modale.', 'mj-member'),
            )
        );

        $this->add_control(
            'modal_shortcode',
            array(
                'label' => __('Shortcode du contenu modal', 'mj-member'),
                'type' => Controls_Manager::TEXTAREA,
                'rows' => 3,
                'placeholder' => '[elementor-template id="123"]',
                'description' => __('Ce shortcode est rendu directement dans la modal (prioritaire sur les contenus enfants).', 'mj-member'),
            )
        );

        $this->add_control(
            'close_on_overlay',
            array(
                'label' => __('Fermeture au clic sur le fond', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $repeater = new Repeater();

        $repeater->add_control(
            'panel_title',
            array(
                'label' => __('Titre du contenu', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Contenu modal', 'mj-member'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'modal_panels',
            array(
                'label' => __('Contenus de la modal', 'mj-member'),
                'type' => Control_Nested_Repeater::CONTROL_TYPE,
                'fields' => $repeater->get_controls(),
                'default' => array(
                    array(
                        'panel_title' => __('Contenu modal', 'mj-member'),
                    ),
                ),
                'title_field' => '{{{ panel_title }}}',
                'button_text' => __('Ajouter un contenu', 'mj-member'),
            )
        );

        $this->add_control(
            'modal_panels_help',
            array(
                'type' => Controls_Manager::RAW_HTML,
                'raw' => __('Ajoutez vos widgets dans le container enfant visible dans le Navigator Elementor sous ce widget.', 'mj-member'),
                'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
            )
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_layout',
            array(
                'label' => __('Mise en page', 'mj-member'),
            )
        );

        $this->add_responsive_control(
            'start_x',
            array(
                'label' => __('Position initiale X', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px', 'vw', '%'),
                'range' => array(
                    'px' => array('min' => 0, 'max' => 2000),
                    'vw' => array('min' => 0, 'max' => 100),
                    '%' => array('min' => 0, 'max' => 100),
                ),
                'default' => array('size' => 24, 'unit' => 'px'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-dmiw' => '--mj-dmiw-start-x: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'start_y',
            array(
                'label' => __('Position initiale Y', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px', 'vh', '%'),
                'range' => array(
                    'px' => array('min' => 0, 'max' => 2000),
                    'vh' => array('min' => 0, 'max' => 100),
                    '%' => array('min' => 0, 'max' => 100),
                ),
                'default' => array('size' => 24, 'unit' => 'px'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-dmiw' => '--mj-dmiw-start-y: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'modal_width',
            array(
                'label' => __('Largeur modal', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px', '%', 'vw'),
                'range' => array(
                    'px' => array('min' => 280, 'max' => 1800),
                    '%' => array('min' => 30, 'max' => 100),
                    'vw' => array('min' => 30, 'max' => 100),
                ),
                'default' => array('size' => 920, 'unit' => 'px'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-dmiw' => '--mj-dmiw-modal-width: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'modal_height',
            array(
                'label' => __('Hauteur modal', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px', '%', 'vh'),
                'range' => array(
                    'px' => array('min' => 220, 'max' => 1200),
                    '%' => array('min' => 30, 'max' => 100),
                    'vh' => array('min' => 30, 'max' => 100),
                ),
                'default' => array('size' => 80, 'unit' => 'vh'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-dmiw' => '--mj-dmiw-modal-height: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'modal_fit_content',
            array(
                'label' => __('Ajuster la modal au contenu', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => '',
                'description' => __('Quand active, la largeur et la hauteur suivent le contenu (dans les limites max de l\'ecran).', 'mj-member'),
            )
        );

        $this->add_control(
            'modal_resizable',
            array(
                'label' => __('Modale etirable', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => '',
                'description' => __('Affiche une poignee native en bas a droite pour redimensionner la modal.', 'mj-member'),
            )
        );

        $this->add_control(
            'close_button_type',
            array(
                'label' => __('Type bouton fermer', 'mj-member'),
                'type' => Controls_Manager::SELECT,
                'default' => 'icon',
                'options' => array(
                    'icon' => __('Icone simple', 'mj-member'),
                    'filled' => __('Bouton plein', 'mj-member'),
                    'outline' => __('Bouton contour', 'mj-member'),
                    'text' => __('Texte', 'mj-member'),
                ),
            )
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_icon',
            array(
                'label' => __('Style icone', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_responsive_control(
            'icon_size',
            array(
                'label' => __('Taille', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array(
                    'px' => array('min' => 28, 'max' => 140),
                ),
                'default' => array('size' => 56, 'unit' => 'px'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-dmiw__icon-button' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'icon_padding',
            array(
                'label' => __('Padding icone', 'mj-member'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => array('px', '%'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-dmiw__icon-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'icon_background_color',
            array(
                'label' => __('Fond', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'default' => '#2563eb',
                'selectors' => array(
                    '{{WRAPPER}} .mj-dmiw__icon-button' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'icon_color',
            array(
                'label' => __('Couleur icone', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => array(
                    '{{WRAPPER}} .mj-dmiw__icon-button' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .mj-dmiw__icon-button svg' => 'fill: {{VALUE}};',
                ),
            )
        );

        $this->add_responsive_control(
            'icon_radius',
            array(
                'label' => __('Rayon', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px', '%'),
                'range' => array(
                    'px' => array('min' => 0, 'max' => 80),
                    '%' => array('min' => 0, 'max' => 50),
                ),
                'default' => array('size' => 18, 'unit' => 'px'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-dmiw__icon-button' => 'border-radius: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            array(
                'name' => 'icon_border',
                'selector' => '{{WRAPPER}} .mj-dmiw__icon-button',
            )
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            array(
                'name' => 'icon_shadow',
                'selector' => '{{WRAPPER}} .mj-dmiw__icon-button',
            )
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_modal',
            array(
                'label' => __('Style modal', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'modal_container_style_heading',
            array(
                'label' => __('Container modal', 'mj-member'),
                'type' => Controls_Manager::HEADING,
            )
        );

        $this->add_control(
            'modal_background_color',
            array(
                'label' => __('Fond modal', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => array(
                    '{{WRAPPER}} .mj-dmiw__modal' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'modal_overlay_color',
            array(
                'label' => __('Fond arriere-plan', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'default' => 'rgba(15, 23, 42, 0.45)',
                'selectors' => array(
                    '{{WRAPPER}} .mj-dmiw__overlay' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            array(
                'name' => 'modal_border',
                'selector' => '{{WRAPPER}} .mj-dmiw__modal',
            )
        );

        $this->add_responsive_control(
            'modal_radius',
            array(
                'label' => __('Rayon des coins', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array(
                    'px' => array('min' => 0, 'max' => 48),
                ),
                'default' => array('size' => 18, 'unit' => 'px'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-dmiw__modal' => 'border-radius: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            array(
                'name' => 'modal_shadow',
                'selector' => '{{WRAPPER}} .mj-dmiw__modal',
            )
        );

        $this->add_control(
            'modal_title_style_heading',
            array(
                'label' => __('Titre', 'mj-member'),
                'type' => Controls_Manager::HEADING,
                'separator' => 'before',
            )
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            array(
                'name' => 'modal_title_typography',
                'selector' => '{{WRAPPER}} .mj-dmiw__modal-title',
            )
        );

        $this->add_control(
            'modal_title_color',
            array(
                'label' => __('Couleur titre', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'default' => '#0f172a',
                'selectors' => array(
                    '{{WRAPPER}} .mj-dmiw__modal-title' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'modal_header_style_heading',
            array(
                'label' => __('Header', 'mj-member'),
                'type' => Controls_Manager::HEADING,
                'separator' => 'before',
            )
        );

        $this->add_responsive_control(
            'modal_header_padding',
            array(
                'label' => __('Padding header', 'mj-member'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => array('px', '%', 'em', 'rem'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-dmiw__modal-header' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'modal_header_min_height',
            array(
                'label' => __('Hauteur minimum header', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px', 'vh'),
                'range' => array(
                    'px' => array('min' => 0, 'max' => 260),
                    'vh' => array('min' => 0, 'max' => 40),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-dmiw__modal-header' => 'min-height: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'modal_header_gap',
            array(
                'label' => __('Espacement header', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array(
                    'px' => array('min' => 0, 'max' => 64),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-dmiw__modal-header' => 'gap: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'modal_header_align_items',
            array(
                'label' => __('Alignement vertical', 'mj-member'),
                'type' => Controls_Manager::CHOOSE,
                'options' => array(
                    'flex-start' => array(
                        'title' => __('Haut', 'mj-member'),
                        'icon' => 'eicon-v-align-top',
                    ),
                    'center' => array(
                        'title' => __('Centre', 'mj-member'),
                        'icon' => 'eicon-v-align-middle',
                    ),
                    'flex-end' => array(
                        'title' => __('Bas', 'mj-member'),
                        'icon' => 'eicon-v-align-bottom',
                    ),
                ),
                'default' => 'center',
                'selectors' => array(
                    '{{WRAPPER}} .mj-dmiw__modal-header' => 'align-items: {{VALUE}};',
                ),
            )
        );

        $this->add_responsive_control(
            'modal_header_justify_content',
            array(
                'label' => __('Alignement horizontal', 'mj-member'),
                'type' => Controls_Manager::CHOOSE,
                'options' => array(
                    'flex-start' => array(
                        'title' => __('Debut', 'mj-member'),
                        'icon' => 'eicon-h-align-left',
                    ),
                    'space-between' => array(
                        'title' => __('Espace', 'mj-member'),
                        'icon' => 'eicon-h-align-stretch',
                    ),
                    'flex-end' => array(
                        'title' => __('Fin', 'mj-member'),
                        'icon' => 'eicon-h-align-right',
                    ),
                ),
                'default' => 'space-between',
                'selectors' => array(
                    '{{WRAPPER}} .mj-dmiw__modal-header' => 'justify-content: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'modal_header_background_heading',
            array(
                'label' => __('Fond header', 'mj-member'),
                'type' => Controls_Manager::HEADING,
            )
        );

        $this->add_group_control(
            Group_Control_Background::get_type(),
            array(
                'name' => 'modal_header_background',
                'types' => array('classic', 'gradient'),
                'selector' => '{{WRAPPER}} .mj-dmiw__modal-header',
            )
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            array(
                'name' => 'modal_header_border',
                'selector' => '{{WRAPPER}} .mj-dmiw__modal-header',
            )
        );

        $this->add_responsive_control(
            'modal_header_radius',
            array(
                'label' => __('Rayon header', 'mj-member'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => array('px', '%'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-dmiw__modal-header' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            array(
                'name' => 'modal_header_shadow',
                'selector' => '{{WRAPPER}} .mj-dmiw__modal-header',
            )
        );

        $this->add_control(
            'modal_title_background_color',
            array(
                'label' => __('Fond titre', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-dmiw__modal-title' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_responsive_control(
            'modal_title_padding',
            array(
                'label' => __('Padding titre', 'mj-member'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => array('px', '%', 'em', 'rem'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-dmiw__modal-title' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'modal_title_radius',
            array(
                'label' => __('Rayon titre', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px', '%'),
                'range' => array(
                    'px' => array('min' => 0, 'max' => 40),
                    '%' => array('min' => 0, 'max' => 50),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-dmiw__modal-title' => 'border-radius: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'close_button_style_heading',
            array(
                'label' => __('Bouton fermer', 'mj-member'),
                'type' => Controls_Manager::HEADING,
                'separator' => 'before',
            )
        );

        $this->add_responsive_control(
            'close_button_size',
            array(
                'label' => __('Taille bouton fermer', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array(
                    'px' => array('min' => 22, 'max' => 56),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-dmiw__close' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'close_button_color',
            array(
                'label' => __('Couleur bouton fermer', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-dmiw__close' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'close_button_background_color',
            array(
                'label' => __('Fond bouton fermer', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-dmiw__close' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            array(
                'name' => 'close_button_border',
                'selector' => '{{WRAPPER}} .mj-dmiw__close',
            )
        );

        $this->add_responsive_control(
            'close_button_radius',
            array(
                'label' => __('Rayon bouton fermer', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px', '%'),
                'range' => array(
                    'px' => array('min' => 0, 'max' => 40),
                    '%' => array('min' => 0, 'max' => 50),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-dmiw__close' => 'border-radius: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->end_controls_section();

        $this->register_visibility_controls();
    }

    protected function render() {
        AssetsManager::requirePackage('draggable-modal-icon');

        $settings = $this->get_settings_for_display();
        $this->apply_visibility_to_wrapper($settings, 'mj-dmiw-widget');

        $modal_title = isset($settings['modal_title']) ? sanitize_text_field((string) $settings['modal_title']) : __('Contenu', 'mj-member');
        $close_on_overlay = !isset($settings['close_on_overlay']) || $settings['close_on_overlay'] === 'yes';
        $modal_fit_content = isset($settings['modal_fit_content']) && $settings['modal_fit_content'] === 'yes';
        $modal_resizable = isset($settings['modal_resizable']) && $settings['modal_resizable'] === 'yes';
        $close_button_type = isset($settings['close_button_type']) ? sanitize_key((string) $settings['close_button_type']) : 'icon';
        if (!in_array($close_button_type, array('icon', 'filled', 'outline', 'text'), true)) {
            $close_button_type = 'icon';
        }
        $icon_url = isset($settings['trigger_icon']['url']) ? esc_url((string) $settings['trigger_icon']['url']) : '';
        $show_icon_in_header = isset($settings['show_icon_in_header']) && $settings['show_icon_in_header'] === 'yes';
        $modal_shortcode = isset($settings['modal_shortcode']) ? trim((string) $settings['modal_shortcode']) : '';
        $modal_shortcode_html = '';
        if ($modal_shortcode !== '') {
            $modal_shortcode_html = do_shortcode($modal_shortcode);
        }

        $children = $this->get_children();
        $is_preview = function_exists('is_elementor_preview') && is_elementor_preview();

        $widget = $this;
        include Config::path() . 'includes/templates/elementor/draggable_modal_icon.php';
    }

    protected function content_template() {
        ?>
        <div
            class="mj-dmiw"
            data-mj-draggable-modal-widget="1"
            data-overlay-close="0"
            data-modal-fit-content="{{ settings.modal_fit_content === 'yes' ? '1' : '0' }}"
            data-modal-resizable="{{ settings.modal_resizable === 'yes' ? '1' : '0' }}"
            data-close-button-type="{{ settings.close_button_type || 'icon' }}"
        >
            <button type="button" class="mj-dmiw__icon-button" aria-label="<?php echo esc_attr__('Ouvrir la modal', 'mj-member'); ?>">
                <span class="mj-dmiw__icon" aria-hidden="true">
                    <# if ( settings.trigger_icon && settings.trigger_icon.url ) { #>
                        <img src="{{ settings.trigger_icon.url }}" alt="" loading="lazy" />
                    <# } else { #>
                        <svg viewBox="0 0 24 24" role="img" focusable="false" aria-hidden="true">
                            <path d="M12 2a10 10 0 1 0 10 10A10.011 10.011 0 0 0 12 2Zm4.65 12.35a1 1 0 0 1-1.41 1.41L11 11.52V7a1 1 0 0 1 2 0v3.69Z"></path>
                        </svg>
                    <# } #>
                </span>
            </button>
            <div class="mj-dmiw__overlay" hidden></div>
            <section class="mj-dmiw__modal" role="dialog" aria-modal="true" hidden>
                <header class="mj-dmiw__modal-header" data-modal-drag-handle="1">
                    <h3 class="mj-dmiw__modal-title">{{ settings.modal_title || '<?php echo esc_js(__('Contenu', 'mj-member')); ?>' }}</h3>
                    <# if ( ( settings.close_button_type || 'icon' ) === 'text' ) { #>
                        <button type="button" class="mj-dmiw__close" aria-label="<?php echo esc_attr__('Fermer', 'mj-member'); ?>"><?php echo esc_html__('Fermer', 'mj-member'); ?></button>
                    <# } else { #>
                        <button type="button" class="mj-dmiw__close" aria-label="<?php echo esc_attr__('Fermer', 'mj-member'); ?>">&times;</button>
                    <# } #>
                </header>
                <div class="mj-dmiw__modal-content">
                    <# if ( settings.modal_panels ) { #>
                        <# _.each( settings.modal_panels, function( item ) { #>
                            <?php $this->content_template_single_repeater_item(); ?>
                        <# } ); #>
                    <# } #>
                </div>
            </section>
        </div>
        <?php
    }

    protected function content_template_single_repeater_item() {
        ?>
        <div class="mj-dmiw__placeholder">{{{ item.panel_title || '<?php echo esc_js(__('Contenu modal', 'mj-member')); ?>' }}}</div>
        <?php
    }

    public function print_child($index, $item_settings = array()) {
        $children = $this->get_children();
        if (!isset($children[$index])) {
            return;
        }

        $children[$index]->print_element();
    }

    protected function get_initial_config(): array {
        return array_merge(parent::get_initial_config(), array(
            'support_improved_repeaters' => true,
            'support_nesting' => true,
            'target_container' => array('.mj-dmiw__modal-content'),
            'node' => 'div',
        ));
    }
}
