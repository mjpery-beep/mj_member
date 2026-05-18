<?php

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Modules\NestedElements\Base\Widget_Nested_Base;
use Elementor\Modules\NestedElements\Controls\Control_Nested_Repeater;
use Elementor\Plugin;
use Elementor\Repeater;
use Mj\Member\Core\AssetsManager;

if (!defined('ABSPATH')) {
    exit;
}

class Mj_Member_Elementor_Dock_Tabs_Widget extends Widget_Nested_Base
{
    use Mj_Member_Elementor_Widget_Visibility;

    private $tab_item_settings = array();

    public function get_name()
    {
        return 'mj-member-dock-tabs';
    }

    public function get_title()
    {
        return __('MJ - Dock Tabs', 'mj-member');
    }

    public function get_icon()
    {
        return 'eicon-tabs';
    }

    public function get_categories()
    {
        return array('mj-member');
    }

    public function get_keywords()
    {
        return array('dock', 'tabs', 'onglets', 'fullscreen', 'mj');
    }

    public function get_style_depends()
    {
        return array('mj-member-dock-tabs');
    }

    public function get_script_depends()
    {
        return array('mj-member-dock-tabs');
    }

    public function show_in_panel(): bool
    {
        return Plugin::$instance->experiments->is_feature_active('nested-elements', true);
    }

    protected function get_default_children_elements()
    {
        return array(
            $this->tab_content_container(1),
            $this->tab_content_container(2),
            $this->tab_content_container(3),
        );
    }

    protected function get_default_repeater_title_setting_key()
    {
        return 'tab_title';
    }

    protected function get_default_children_title()
    {
        return esc_html__('Onglet #%d', 'mj-member');
    }

    protected function get_default_children_placeholder_selector()
    {
        return '.mj-dock-tabs__panels';
    }

    protected function tab_content_container(int $index)
    {
        return array(
            'elType' => 'container',
            'settings' => array(
                '_title' => sprintf(__('Onglet #%d', 'mj-member'), $index),
                'content_width' => 'full',
            ),
        );
    }

    protected function register_controls()
    {
        $this->start_controls_section(
            'section_content',
            array(
                'label' => __('Contenu', 'mj-member'),
            )
        );

        $this->add_control(
            'dock_position',
            array(
                'label' => __('Position du dock', 'mj-member'),
                'type' => Controls_Manager::SELECT,
                'default' => 'bottom',
                'options' => array(
                    'top' => __('Haut', 'mj-member'),
                    'right' => __('Droite', 'mj-member'),
                    'bottom' => __('Bas', 'mj-member'),
                    'left' => __('Gauche', 'mj-member'),
                ),
            )
        );

        $this->add_control(
            'default_tab_index',
            array(
                'label' => __('Onglet actif au chargement', 'mj-member'),
                'type' => Controls_Manager::NUMBER,
                'min' => 1,
                'step' => 1,
                'default' => 1,
            )
        );

        $this->add_control(
            'dock_offset',
            array(
                'label' => __('Distance du bord (px)', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array(
                    'px' => array(
                        'min' => 0,
                        'max' => 80,
                    ),
                ),
                'default' => array(
                    'size' => 24,
                    'unit' => 'px',
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-dock-tabs' => '--mj-dock-tabs-offset: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'dock_icon_spacing',
            array(
                'label' => __('Espacement entre les icones', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array(
                    'px' => array(
                        'min' => 0,
                        'max' => 40,
                    ),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-dock-tabs' => '--mj-dock-tabs-icon-gap: {{SIZE}}{{UNIT}};',
                    '{{WRAPPER}} .mj-dock-tabs__dock' => 'gap: {{SIZE}}{{UNIT}};',
                    '{{WRAPPER}} .mj-dock-tabs--top .mj-dock-tabs__tab + .mj-dock-tabs__tab, {{WRAPPER}} .mj-dock-tabs--bottom .mj-dock-tabs__tab + .mj-dock-tabs__tab' => 'margin-left: {{SIZE}}{{UNIT}};',
                    '{{WRAPPER}} .mj-dock-tabs--left .mj-dock-tabs__tab + .mj-dock-tabs__tab, {{WRAPPER}} .mj-dock-tabs--right .mj-dock-tabs__tab + .mj-dock-tabs__tab' => 'margin-top: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'transition_duration',
            array(
                'label' => __('Durée de transition (ms)', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('ms'),
                'range' => array(
                    'ms' => array(
                        'min' => 120,
                        'max' => 1200,
                    ),
                ),
                'default' => array(
                    'size' => 360,
                    'unit' => 'ms',
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-dock-tabs' => '--mj-dock-tabs-transition: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'show_tab_label',
            array(
                'label' => __('Afficher le label', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->add_control(
            'dock_fullscreen',
            array(
                'label' => __('Plein ecran', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => '',
                'description' => __('Masque #site-header et retire les espacements internes pour un rendu 100% plein ecran.', 'mj-member'),
            )
        );

        $this->add_control(
            'dock_magnify_effect',
            array(
                'label' => __('Effet grossissement type macOS', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->add_control(
            'dock_marker_mode',
            array(
                'label' => __('Marqueur icone', 'mj-member'),
                'type' => Controls_Manager::SELECT,
                'default' => 'none',
                'options' => array(
                    'plus-minus' => __('Plus/Minus', 'mj-member'),
                    'none' => __('Aucun', 'mj-member'),
                ),
            )
        );

        $this->add_control(
            'dock_magnify_strength',
            array(
                'label' => __('Multiplicateur grossissement', 'mj-member'),
                'type' => Controls_Manager::NUMBER,
                'default' => 1,
                'min' => 0.5,
                'max' => 2,
                'step' => 0.05,
                'condition' => array(
                    'dock_magnify_effect' => 'yes',
                ),
            )
        );

        $this->add_control(
            'dock_magnify_far_scale',
            array(
                'label' => __('Echelle voisin 2 (x)', 'mj-member'),
                'type' => Controls_Manager::NUMBER,
                'default' => 1.1,
                'min' => 1,
                'max' => 1.8,
                'step' => 0.01,
                'condition' => array(
                    'dock_magnify_effect' => 'yes',
                ),
            )
        );

        $this->add_control(
            'dock_magnify_near_scale',
            array(
                'label' => __('Echelle voisin 1 (x)', 'mj-member'),
                'type' => Controls_Manager::NUMBER,
                'default' => 1.2,
                'min' => 1,
                'max' => 2,
                'step' => 0.01,
                'condition' => array(
                    'dock_magnify_effect' => 'yes',
                ),
            )
        );

        $this->add_control(
            'dock_magnify_center_scale',
            array(
                'label' => __('Echelle centre (x)', 'mj-member'),
                'type' => Controls_Manager::NUMBER,
                'default' => 1.5,
                'min' => 1,
                'max' => 2.5,
                'step' => 0.01,
                'condition' => array(
                    'dock_magnify_effect' => 'yes',
                ),
            )
        );

        $this->add_control(
            'dock_magnify_near_lift',
            array(
                'label' => __('Lift voisin 1 (px)', 'mj-member'),
                'type' => Controls_Manager::NUMBER,
                'default' => 6,
                'min' => 0,
                'max' => 24,
                'step' => 1,
                'condition' => array(
                    'dock_magnify_effect' => 'yes',
                ),
            )
        );

        $this->add_control(
            'dock_magnify_center_lift',
            array(
                'label' => __('Lift centre (px)', 'mj-member'),
                'type' => Controls_Manager::NUMBER,
                'default' => 10,
                'min' => 0,
                'max' => 28,
                'step' => 1,
                'condition' => array(
                    'dock_magnify_effect' => 'yes',
                ),
            )
        );

        $repeater = new Repeater();

        $repeater->add_control(
            'tab_title',
            array(
                'label' => __('Titre', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Onglet', 'mj-member'),
                'label_block' => true,
            )
        );

        $repeater->add_control(
            'tab_icon',
            array(
                'label' => __('Icone (image)', 'mj-member'),
                'type' => Controls_Manager::MEDIA,
                'media_types' => array('image'),
            )
        );

        $repeater->add_control(
            'tab_background_type',
            array(
                'label' => __('Type de background', 'mj-member'),
                'type' => Controls_Manager::SELECT,
                'default' => 'default',
                'options' => array(
                    'default' => __('Defaut du widget', 'mj-member'),
                    'solid' => __('Couleur unie', 'mj-member'),
                    'gradient' => __('Degrade', 'mj-member'),
                    'mosaic' => __('Mosaic Grimlins', 'mj-member'),
                ),
            )
        );

        $repeater->add_control(
            'tab_bg_solid_color',
            array(
                'label' => __('Couleur de fond', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'default' => '#111827',
                'condition' => array(
                    'tab_background_type' => 'solid',
                ),
            )
        );

        $repeater->add_control(
            'tab_bg_gradient_angle',
            array(
                'label' => __('Angle du degrade', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('deg'),
                'range' => array(
                    'deg' => array(
                        'min' => 0,
                        'max' => 360,
                        'step' => 1,
                    ),
                ),
                'default' => array(
                    'size' => 135,
                    'unit' => 'deg',
                ),
                'condition' => array(
                    'tab_background_type' => 'gradient',
                ),
            )
        );

        $repeater->add_control(
            'tab_bg_gradient_color_1',
            array(
                'label' => __('Degrade couleur 1', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'default' => '#0b1220',
                'condition' => array(
                    'tab_background_type' => 'gradient',
                ),
            )
        );

        $repeater->add_control(
            'tab_bg_gradient_stop_1',
            array(
                'label' => __('Position couleur 1 (%)', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('%'),
                'range' => array(
                    '%' => array(
                        'min' => 0,
                        'max' => 100,
                        'step' => 1,
                    ),
                ),
                'default' => array(
                    'size' => 0,
                    'unit' => '%',
                ),
                'condition' => array(
                    'tab_background_type' => 'gradient',
                ),
            )
        );

        $repeater->add_control(
            'tab_bg_gradient_color_2',
            array(
                'label' => __('Degrade couleur 2', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'default' => '#111827',
                'condition' => array(
                    'tab_background_type' => 'gradient',
                ),
            )
        );

        $repeater->add_control(
            'tab_bg_gradient_stop_2',
            array(
                'label' => __('Position couleur 2 (%)', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('%'),
                'range' => array(
                    '%' => array(
                        'min' => 0,
                        'max' => 100,
                        'step' => 1,
                    ),
                ),
                'default' => array(
                    'size' => 55,
                    'unit' => '%',
                ),
                'condition' => array(
                    'tab_background_type' => 'gradient',
                ),
            )
        );

        $repeater->add_control(
            'tab_bg_gradient_color_3',
            array(
                'label' => __('Degrade couleur 3', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'default' => '#1f2937',
                'condition' => array(
                    'tab_background_type' => 'gradient',
                ),
            )
        );

        $repeater->add_control(
            'tab_bg_gradient_stop_3',
            array(
                'label' => __('Position couleur 3 (%)', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('%'),
                'range' => array(
                    '%' => array(
                        'min' => 0,
                        'max' => 100,
                        'step' => 1,
                    ),
                ),
                'default' => array(
                    'size' => 100,
                    'unit' => '%',
                ),
                'condition' => array(
                    'tab_background_type' => 'gradient',
                ),
            )
        );

        $repeater->add_control(
            'tab_bg_mosaic_limit',
            array(
                'label' => __('Mosaic: nombre de tuiles', 'mj-member'),
                'type' => Controls_Manager::NUMBER,
                'min' => 4,
                'max' => 80,
                'step' => 1,
                'default' => 12,
                'condition' => array(
                    'tab_background_type' => 'mosaic',
                ),
            )
        );

        $repeater->add_control(
            'tab_bg_mosaic_transition',
            array(
                'label' => __('Mosaic: transition', 'mj-member'),
                'type' => Controls_Manager::SELECT,
                'default' => 'hover',
                'options' => array(
                    'hover' => __('Avatar seul (original au survol)', 'mj-member'),
                    'flip' => __('Rotation 3D', 'mj-member'),
                    'fade' => __('Fondu enchaine', 'mj-member'),
                ),
                'condition' => array(
                    'tab_background_type' => 'mosaic',
                ),
            )
        );

        $repeater->add_control(
            'tab_bg_mosaic_speed',
            array(
                'label' => __('Mosaic: vitesse (s)', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'range' => array(
                    'px' => array(
                        'min' => 1,
                        'max' => 20,
                        'step' => 0.5,
                    ),
                ),
                'default' => array(
                    'size' => 5,
                    'unit' => 'px',
                ),
                'condition' => array(
                    'tab_background_type' => 'mosaic',
                    'tab_bg_mosaic_transition!' => 'hover',
                ),
            )
        );

        $this->add_control(
            'tabs',
            array(
                'label' => __('Onglets', 'mj-member'),
                'type' => Control_Nested_Repeater::CONTROL_TYPE,
                'fields' => $repeater->get_controls(),
                'default' => array(
                    array(
                        'tab_title' => __('Accueil', 'mj-member'),
                    ),
                    array(
                        'tab_title' => __('Services', 'mj-member'),
                    ),
                    array(
                        'tab_title' => __('Contact', 'mj-member'),
                    ),
                ),
                'title_field' => '{{{ tab_title }}}',
                'button_text' => __('Ajouter un onglet', 'mj-member'),
            )
        );

        $this->add_control(
            'tabs_help',
            array(
                'type' => Controls_Manager::RAW_HTML,
                'raw' => __('Chaque onglet possede son propre container dans le Navigator Elementor. Deposez vos widgets directement dans ce container.', 'mj-member'),
                'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
            )
        );

        $this->end_controls_section();

        $this->register_visibility_controls();

        $this->start_controls_section(
            'section_style',
            array(
                'label' => __('Style', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'panel_background',
            array(
                'label' => __('Fond des ecrans', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-dock-tabs__panel[data-bg-type="default"]' => 'background: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'dock_background',
            array(
                'label' => __('Fond du dock', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-dock-tabs__dock' => 'background-color: {{VALUE}};',
                    '{{WRAPPER}} .mj-dock-tabs--codepen' => '--mj-dock-cp-bg: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'dock_style_heading',
            array(
                'label' => __('Dock (conteneur)', 'mj-member'),
                'type' => Controls_Manager::HEADING,
                'separator' => 'before',
            )
        );

        $this->add_responsive_control(
            'dock_cp_height',
            array(
                'label' => __('Hauteur du dock', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array(
                    'px' => array(
                        'min' => 40,
                        'max' => 120,
                    ),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-dock-tabs--codepen' => '--mj-dock-cp-height: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'dock_cp_padding',
            array(
                'label' => __('Padding du dock', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array(
                    'px' => array(
                        'min' => 0,
                        'max' => 16,
                    ),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-dock-tabs--codepen' => '--mj-dock-cp-padding: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'dock_cp_radius',
            array(
                'label' => __('Arrondi du dock', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array(
                    'px' => array(
                        'min' => 0,
                        'max' => 40,
                    ),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-dock-tabs--codepen' => '--mj-dock-cp-radius: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'dock_cp_glass_bg',
            array(
                'label' => __('Fond verre', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-dock-tabs--codepen' => '--mj-dock-cp-bg: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'dock_cp_border_color',
            array(
                'label' => __('Couleur bordure', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-dock-tabs--codepen' => '--mj-dock-cp-border-color: {{VALUE}};',
                ),
            )
        );

        $this->add_responsive_control(
            'dock_cp_border_width',
            array(
                'label' => __('Epaisseur bordure', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array(
                    'px' => array(
                        'min' => 0,
                        'max' => 4,
                        'step' => 0.1,
                    ),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-dock-tabs--codepen' => '--mj-dock-cp-border-width: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'dock_cp_blur',
            array(
                'label' => __('Flou verre', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array(
                    'px' => array(
                        'min' => 0,
                        'max' => 40,
                    ),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-dock-tabs--codepen' => '--mj-dock-cp-blur: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'dock_items_heading',
            array(
                'label' => __('Items et icones', 'mj-member'),
                'type' => Controls_Manager::HEADING,
                'separator' => 'before',
            )
        );

        $this->add_responsive_control(
            'dock_cp_item_size',
            array(
                'label' => __('Taille item/icône', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array(
                    'px' => array(
                        'min' => 28,
                        'max' => 96,
                    ),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-dock-tabs--codepen' => '--mj-dock-cp-item-size: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'dock_cp_hover_margin',
            array(
                'label' => __('Ecartement au hover', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array(
                    'px' => array(
                        'min' => 0,
                        'max' => 30,
                    ),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-dock-tabs--codepen' => '--mj-dock-cp-hover-gap: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'dock_tooltip_heading',
            array(
                'label' => __('Tooltip et indicateur', 'mj-member'),
                'type' => Controls_Manager::HEADING,
                'separator' => 'before',
            )
        );

        $this->add_control(
            'dock_cp_tooltip_bg',
            array(
                'label' => __('Fond tooltip', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-dock-tabs--codepen' => '--mj-dock-cp-tooltip-bg: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'dock_cp_tooltip_text',
            array(
                'label' => __('Texte tooltip', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-dock-tabs--codepen' => '--mj-dock-cp-tooltip-text: {{VALUE}};',
                ),
            )
        );

        $this->add_responsive_control(
            'dock_cp_tooltip_radius',
            array(
                'label' => __('Arrondi tooltip', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array(
                    'px' => array(
                        'min' => 0,
                        'max' => 20,
                    ),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-dock-tabs--codepen' => '--mj-dock-cp-tooltip-radius: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'dock_cp_dot_color',
            array(
                'label' => __('Couleur indicateur actif', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-dock-tabs--codepen' => '--mj-dock-cp-dot-color: {{VALUE}};',
                ),
            )
        );

        $this->add_responsive_control(
            'dock_cp_dot_size',
            array(
                'label' => __('Taille indicateur actif', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array(
                    'px' => array(
                        'min' => 2,
                        'max' => 12,
                    ),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-dock-tabs--codepen' => '--mj-dock-cp-dot-size: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'tab_border_radius',
            array(
                'label' => __('Arrondi onglet', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array(
                    'px' => array(
                        'min' => 0,
                        'max' => 50,
                    ),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-dock-tabs__tab' => 'border-radius: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'tab_padding',
            array(
                'label' => __('Padding onglet', 'mj-member'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => array('px', '%'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-dock-tabs__tab' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'tab_icon_size',
            array(
                'label' => __('Taille icone', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array(
                    'px' => array(
                        'min' => 16,
                        'max' => 96,
                    ),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-dock-tabs__icon' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'tab_gap',
            array(
                'label' => __('Espacement entre onglets', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array(
                    'px' => array(
                        'min' => 0,
                        'max' => 40,
                    ),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-dock-tabs__dock' => 'gap: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            array(
                'name' => 'tab_label_typography',
                'label' => __('Typographie label', 'mj-member'),
                'selector' => '{{WRAPPER}} .mj-dock-tabs__label',
            )
        );

        $this->add_control(
            'tab_states_heading',
            array(
                'label' => __('Etats des onglets', 'mj-member'),
                'type' => Controls_Manager::HEADING,
                'separator' => 'before',
            )
        );

        $this->start_controls_tabs('tab_style_states_tabs');

        $this->start_controls_tab(
            'tab_style_state_normal',
            array(
                'label' => __('Normal', 'mj-member'),
            )
        );

        $this->add_control(
            'tab_normal_background',
            array(
                'label' => __('Fond', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-dock-tabs__tab' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'tab_normal_text_color',
            array(
                'label' => __('Couleur texte', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-dock-tabs__tab' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_responsive_control(
            'tab_normal_icon_size',
            array(
                'label' => __('Taille icone', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array(
                    'px' => array(
                        'min' => 16,
                        'max' => 96,
                    ),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-dock-tabs__tab .mj-dock-tabs__icon' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            array(
                'name' => 'tab_normal_border',
                'selector' => '{{WRAPPER}} .mj-dock-tabs__tab',
            )
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            array(
                'name' => 'tab_normal_shadow',
                'selector' => '{{WRAPPER}} .mj-dock-tabs__tab',
            )
        );

        $this->end_controls_tab();

        $this->start_controls_tab(
            'tab_style_state_hover',
            array(
                'label' => __('Hover', 'mj-member'),
            )
        );

        $this->add_control(
            'tab_hover_background',
            array(
                'label' => __('Fond', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-dock-tabs__tab:hover' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'tab_hover_text_color',
            array(
                'label' => __('Couleur texte', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-dock-tabs__tab:hover' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_responsive_control(
            'tab_hover_icon_size',
            array(
                'label' => __('Taille icone', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array(
                    'px' => array(
                        'min' => 16,
                        'max' => 96,
                    ),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-dock-tabs__tab:hover .mj-dock-tabs__icon' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            array(
                'name' => 'tab_hover_border',
                'selector' => '{{WRAPPER}} .mj-dock-tabs__tab:hover',
            )
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            array(
                'name' => 'tab_hover_shadow',
                'selector' => '{{WRAPPER}} .mj-dock-tabs__tab:hover',
            )
        );

        $this->end_controls_tab();

        $this->start_controls_tab(
            'tab_style_state_active',
            array(
                'label' => __('Actif', 'mj-member'),
            )
        );

        $this->add_control(
            'tab_active_background',
            array(
                'label' => __('Fond', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-dock-tabs__tab.is-active' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'tab_active_text_color',
            array(
                'label' => __('Couleur texte', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-dock-tabs__tab.is-active' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_responsive_control(
            'tab_active_icon_size',
            array(
                'label' => __('Taille icone', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array(
                    'px' => array(
                        'min' => 16,
                        'max' => 96,
                    ),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-dock-tabs__tab.is-active .mj-dock-tabs__icon' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            array(
                'name' => 'tab_active_border',
                'selector' => '{{WRAPPER}} .mj-dock-tabs__tab.is-active',
            )
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            array(
                'name' => 'tab_active_shadow',
                'selector' => '{{WRAPPER}} .mj-dock-tabs__tab.is-active',
            )
        );

        $this->end_controls_tab();

        $this->start_controls_tab(
            'tab_style_state_focus',
            array(
                'label' => __('Focus', 'mj-member'),
            )
        );

        $this->add_control(
            'tab_focus_background',
            array(
                'label' => __('Fond', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-dock-tabs__tab:focus-visible' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'tab_focus_text_color',
            array(
                'label' => __('Couleur texte', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-dock-tabs__tab:focus-visible' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_responsive_control(
            'tab_focus_icon_size',
            array(
                'label' => __('Taille icone', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array(
                    'px' => array(
                        'min' => 16,
                        'max' => 96,
                    ),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-dock-tabs__tab:focus-visible .mj-dock-tabs__icon' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'tab_focus_outline_color',
            array(
                'label' => __('Couleur contour', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-dock-tabs__tab:focus-visible' => 'outline-color: {{VALUE}};',
                ),
            )
        );

        $this->add_responsive_control(
            'tab_focus_outline_width',
            array(
                'label' => __('Epaisseur contour', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array(
                    'px' => array(
                        'min' => 0,
                        'max' => 8,
                    ),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-dock-tabs__tab:focus-visible' => 'outline-width: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'tab_focus_outline_offset',
            array(
                'label' => __('Decalage contour', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array(
                    'px' => array(
                        'min' => 0,
                        'max' => 12,
                    ),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-dock-tabs__tab:focus-visible' => 'outline-offset: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            array(
                'name' => 'tab_focus_shadow',
                'selector' => '{{WRAPPER}} .mj-dock-tabs__tab:focus-visible',
            )
        );

        $this->end_controls_tab();
        $this->end_controls_tabs();

        $this->end_controls_section();
    }

    protected function render()
    {
        if ($this->is_elementor_save_request()) {
            // Keep Elementor save requests lightweight to avoid admin-ajax failures.
            echo '<div class="mj-dock-tabs mj-dock-tabs--saving" aria-hidden="true"></div>';
            return;
        }

        AssetsManager::requirePackage('dock-tabs');

        $settings = $this->get_settings_for_display();
        $this->apply_visibility_to_wrapper($settings, 'mj-dock-tabs-widget');

        $tabs = isset($settings['tabs']) && is_array($settings['tabs']) ? $settings['tabs'] : array();
        if (empty($tabs)) {
            return;
        }

        $default_tab_index = isset($settings['default_tab_index']) ? (int) $settings['default_tab_index'] : 1;
        if ($default_tab_index < 1) {
            $default_tab_index = 1;
        }

        $default_tab_index = min($default_tab_index, count($tabs));

        $dock_position = isset($settings['dock_position']) ? sanitize_key((string) $settings['dock_position']) : 'bottom';
        if (!in_array($dock_position, array('top', 'right', 'bottom', 'left'), true)) {
            $dock_position = 'bottom';
        }

        $show_tab_label = !isset($settings['show_tab_label']) || 'yes' === $settings['show_tab_label'];
        $is_fullscreen = isset($settings['dock_fullscreen']) && 'yes' === $settings['dock_fullscreen'];
        $has_magnify = !isset($settings['dock_magnify_effect']) || 'yes' === $settings['dock_magnify_effect'];
        $marker_mode = isset($settings['dock_marker_mode']) ? sanitize_key((string) $settings['dock_marker_mode']) : 'none';
        if (!in_array($marker_mode, array('plus-minus', 'none'), true)) {
            $marker_mode = 'none';
        }

        $magnify_strength = isset($settings['dock_magnify_strength']) ? (float) $settings['dock_magnify_strength'] : 1.0;
        if ($magnify_strength < 0.5) {
            $magnify_strength = 0.5;
        } elseif ($magnify_strength > 2.0) {
            $magnify_strength = 2.0;
        }

        $magnify_far_scale = isset($settings['dock_magnify_far_scale']) ? (float) $settings['dock_magnify_far_scale'] : 1.1;
        if ($magnify_far_scale < 1.0) {
            $magnify_far_scale = 1.0;
        } elseif ($magnify_far_scale > 1.8) {
            $magnify_far_scale = 1.8;
        }

        $magnify_near_scale = isset($settings['dock_magnify_near_scale']) ? (float) $settings['dock_magnify_near_scale'] : 1.2;
        if ($magnify_near_scale < 1.0) {
            $magnify_near_scale = 1.0;
        } elseif ($magnify_near_scale > 2.0) {
            $magnify_near_scale = 2.0;
        }

        $magnify_center_scale = isset($settings['dock_magnify_center_scale']) ? (float) $settings['dock_magnify_center_scale'] : 1.5;
        if ($magnify_center_scale < 1.0) {
            $magnify_center_scale = 1.0;
        } elseif ($magnify_center_scale > 2.5) {
            $magnify_center_scale = 2.5;
        }

        $magnify_near_lift = isset($settings['dock_magnify_near_lift']) ? (int) $settings['dock_magnify_near_lift'] : 6;
        if ($magnify_near_lift < 0) {
            $magnify_near_lift = 0;
        } elseif ($magnify_near_lift > 24) {
            $magnify_near_lift = 24;
        }

        $magnify_center_lift = isset($settings['dock_magnify_center_lift']) ? (int) $settings['dock_magnify_center_lift'] : 10;
        if ($magnify_center_lift < 0) {
            $magnify_center_lift = 0;
        } elseif ($magnify_center_lift > 28) {
            $magnify_center_lift = 28;
        }

        $widget_number = $this->get_id_int();
        $this->tab_item_settings = array();

        $root_classes = array(
            'mj-dock-tabs',
            'mj-dock-tabs--codepen',
            'mj-dock-tabs--' . $dock_position,
        );

        if (!$show_tab_label) {
            $root_classes[] = 'mj-dock-tabs--hide-label';
        }

        if ($is_fullscreen) {
            $root_classes[] = 'mj-dock-tabs--fullscreen';
        }

        if (!$has_magnify) {
            $root_classes[] = 'mj-dock-tabs--magnify-off';
        }

        if ('none' === $marker_mode) {
            $root_classes[] = 'mj-dock-tabs--marker-none';
        }

        echo '<div class="' . esc_attr(implode(' ', array_map('sanitize_html_class', $root_classes))) . '" data-mj-dock-tabs="1" data-default-index="' . esc_attr((string) $default_tab_index) . '" data-dock-position="' . esc_attr($dock_position) . '" data-fullscreen="' . ($is_fullscreen ? '1' : '0') . '" data-magnify="' . ($has_magnify ? '1' : '0') . '" data-magnify-strength="' . esc_attr((string) $magnify_strength) . '" data-magnify-far-scale="' . esc_attr((string) $magnify_far_scale) . '" data-magnify-near-scale="' . esc_attr((string) $magnify_near_scale) . '" data-magnify-center-scale="' . esc_attr((string) $magnify_center_scale) . '" data-magnify-near-lift="' . esc_attr((string) $magnify_near_lift) . '" data-magnify-center-lift="' . esc_attr((string) $magnify_center_lift) . '">';
        echo '<div class="mj-dock-tabs__panels">';

        foreach ($tabs as $index => $item) {
            $tab_count = $index + 1;
            $tab_key = 'mj-dock-tab-' . $widget_number . '-' . $tab_count;
            $this->tab_item_settings[$index] = array(
                'index' => $index,
                'tab_count' => $tab_count,
                'default_tab_index' => $default_tab_index,
                'tab_id' => 'mj-dock-tab-btn-' . $tab_key,
                'container_id' => 'mj-dock-tab-panel-' . $tab_key,
                'background' => $this->build_tab_background_config(is_array($item) ? $item : array()),
            );
        }

        // Render children
        $children = $this->get_children();
        foreach ($children as $index => $child) {
            if (isset($this->tab_item_settings[$index])) {
                $item_settings = $this->tab_item_settings[$index];
                $this->print_child($index, $item_settings);
            }
        }

        echo '</div>';
        echo '<div class="mj-dock-tabs__dock" role="tablist" aria-label="' . esc_attr__('Navigation dock', 'mj-member') . '">';

        foreach ($tabs as $index => $item) {
            if (!isset($this->tab_item_settings[$index])) {
                continue;
            }

            $item_settings = $this->tab_item_settings[$index];
            $is_active = $item_settings['tab_count'] === $default_tab_index;
            $title = isset($item['tab_title']) ? sanitize_text_field((string) $item['tab_title']) : sprintf(__('Onglet %d', 'mj-member'), $item_settings['tab_count']);
            if ($title === '') {
                $title = sprintf(__('Onglet %d', 'mj-member'), $item_settings['tab_count']);
            }

            $icon_url = '';
            $icon_alt = $title;
            if (isset($item['tab_icon']) && is_array($item['tab_icon']) && !empty($item['tab_icon']['url'])) {
                $icon_url = esc_url((string) $item['tab_icon']['url']);
                if (!empty($item['tab_icon']['id'])) {
                    $attachment_alt = get_post_meta((int) $item['tab_icon']['id'], '_wp_attachment_image_alt', true);
                    if (is_string($attachment_alt) && $attachment_alt !== '') {
                        $icon_alt = sanitize_text_field($attachment_alt);
                    }
                }
            }

            echo '<button type="button" id="' . esc_attr($item_settings['tab_id']) . '" class="mj-dock-tabs__tab' . ($is_active ? ' is-active' : '') . '" data-tab-target="' . esc_attr((string) $item_settings['tab_count']) . '" role="tab" aria-controls="' . esc_attr($item_settings['container_id']) . '" aria-selected="' . ($is_active ? 'true' : 'false') . '" tabindex="' . ($is_active ? '0' : '-1') . '">';
            echo '<span class="mj-dock-tabs__icon" aria-hidden="true">';
            if ($icon_url !== '') {
                echo '<img src="' . $icon_url . '" alt="' . esc_attr($icon_alt) . '" loading="lazy" />';
            } else {
                $initial = function_exists('mb_substr') ? mb_substr($title, 0, 1, 'UTF-8') : substr($title, 0, 1);
                echo '<span class="mj-dock-tabs__icon-fallback">' . esc_html($initial) . '</span>';
            }
            echo '</span>';
            echo '<span class="mj-dock-tabs__label">' . esc_html($title) . '</span>';
            echo '</button>';
        }

        echo '</div>';
        echo '</div>';
    }

    public function print_child($index, $item_settings = array())
    {
        $children = $this->get_children();
        $child_ids = array();

        foreach ($children as $child) {
            $child_ids[] = $child->get_id();
        }

        $add_attribute_to_container = function ($should_render, $container) use ($item_settings, $child_ids) {
            if (in_array($container->get_id(), $child_ids, true)) {
                $this->add_attributes_to_container($container, $item_settings);
            }

            return $should_render;
        };

        add_filter('elementor/frontend/container/should_render', $add_attribute_to_container, 10, 3);
        if (isset($children[$index])) {
            $children[$index]->print_element();
        }
        remove_filter('elementor/frontend/container/should_render', $add_attribute_to_container);
    }

    protected function add_attributes_to_container($container, $item_settings)
    {
        $background = isset($item_settings['background']) && is_array($item_settings['background'])
            ? $item_settings['background']
            : array('type' => 'default');

        $attributes = array(
            'id' => $item_settings['container_id'],
            'role' => 'tabpanel',
            'aria-labelledby' => $item_settings['tab_id'],
            'class' => array('mj-dock-tabs__panel', 'mj-dock-tabs__panel-content'),
            'data-tab-panel' => $item_settings['tab_count'],
        );

        if (!empty($background['panel_style'])) {
            $attributes['style'] = $background['panel_style'];
        }

        if (isset($background['type'])) {
            $attributes['data-bg-type'] = (string) $background['type'];
        }

        if (!empty($background['mosaic_json'])) {
            $attributes['class'][] = 'mj-dock-tabs__panel--mosaic';
            $attributes['data-mosaic-config'] = $background['mosaic_json'];
        }

        $default_tab_index = isset($item_settings['default_tab_index']) ? (int) $item_settings['default_tab_index'] : 1;
        if ((int) $item_settings['tab_count'] === $default_tab_index) {
            $attributes['class'][] = 'is-active';
        } else {
            $attributes['hidden'] = 'hidden';
        }

        $container->add_render_attribute('_wrapper', $attributes);
    }

    protected function content_template_single_repeater_item()
    {
        ?>
        <#
        const tabIndex = view.collection.length,
            elementUid = view.getIDInt().toString(),
            item = data,
            defaultTabIndex = Math.max(1, parseInt(settings.default_tab_index, 10) || 1);
        #>
        <?php $this->content_template_single_item('{{ tabIndex }}', '{{ item }}', '{{ elementUid }}', '{{ defaultTabIndex }}'); ?>
        <?php
    }

    protected function content_template()
    {
        ?>
        <#
        const elementUid = view.getIDInt().toString(),
            dockPosition = settings.dock_position || 'bottom',
            defaultTabIndex = Math.max(1, parseInt(settings.default_tab_index, 10) || 1),
            isFullscreen = 'yes' === settings.dock_fullscreen,
            hasMagnify = typeof settings.dock_magnify_effect === 'undefined' || 'yes' === settings.dock_magnify_effect,
            markerMode = settings.dock_marker_mode || 'none',
            magnifyStrengthRaw = parseFloat(settings.dock_magnify_strength),
            magnifyFarScaleRaw = parseFloat(settings.dock_magnify_far_scale),
            magnifyNearScaleRaw = parseFloat(settings.dock_magnify_near_scale),
            magnifyCenterScaleRaw = parseFloat(settings.dock_magnify_center_scale),
            magnifyNearLiftRaw = parseInt(settings.dock_magnify_near_lift, 10),
            magnifyCenterLiftRaw = parseInt(settings.dock_magnify_center_lift, 10),
            magnifyStrength = isFinite(magnifyStrengthRaw) ? Math.max(0.5, Math.min(2, magnifyStrengthRaw)) : 1,
            magnifyFarScale = isFinite(magnifyFarScaleRaw) ? Math.max(1, Math.min(1.8, magnifyFarScaleRaw)) : 1.1,
            magnifyNearScale = isFinite(magnifyNearScaleRaw) ? Math.max(1, Math.min(2, magnifyNearScaleRaw)) : 1.2,
            magnifyCenterScale = isFinite(magnifyCenterScaleRaw) ? Math.max(1, Math.min(2.5, magnifyCenterScaleRaw)) : 1.5,
            magnifyNearLift = isFinite(magnifyNearLiftRaw) ? Math.max(0, Math.min(24, magnifyNearLiftRaw)) : 6,
            magnifyCenterLift = isFinite(magnifyCenterLiftRaw) ? Math.max(0, Math.min(28, magnifyCenterLiftRaw)) : 10,
            showTabLabel = typeof settings.show_tab_label === 'undefined' || 'yes' === settings.show_tab_label,
            rootClassBase = 'mj-dock-tabs mj-dock-tabs--codepen mj-dock-tabs--' + dockPosition,
            rootClassWithLabel = showTabLabel ? rootClassBase : rootClassBase + ' mj-dock-tabs--hide-label',
            rootClassWithFullscreen = isFullscreen ? rootClassWithLabel + ' mj-dock-tabs--fullscreen' : rootClassWithLabel,
            rootClassWithMagnify = hasMagnify ? rootClassWithFullscreen : rootClassWithFullscreen + ' mj-dock-tabs--magnify-off',
            rootClass = markerMode === 'none' ? rootClassWithMagnify + ' mj-dock-tabs--marker-none' : rootClassWithMagnify;
        #>
        <div class="{{ rootClass }}" data-mj-dock-tabs="1" data-default-index="{{ defaultTabIndex }}" data-dock-position="{{ dockPosition }}" data-fullscreen="{{ isFullscreen ? '1' : '0' }}" data-magnify="{{ hasMagnify ? '1' : '0' }}" data-magnify-strength="{{ magnifyStrength }}" data-magnify-far-scale="{{ magnifyFarScale }}" data-magnify-near-scale="{{ magnifyNearScale }}" data-magnify-center-scale="{{ magnifyCenterScale }}" data-magnify-near-lift="{{ magnifyNearLift }}" data-magnify-center-lift="{{ magnifyCenterLift }}">
            <div class="mj-dock-tabs__panels"></div>
            <# if ( settings.tabs ) { #>
            <div class="mj-dock-tabs__dock" role="tablist" aria-label="<?php echo esc_attr__('Navigation dock', 'mj-member'); ?>">
                <# _.each( settings.tabs, function( item, index ) {
                    const tabIndex = index;
                #>
                <?php $this->content_template_single_item('{{ tabIndex }}', '{{ item }}', '{{ elementUid }}', '{{ defaultTabIndex }}'); ?>
                <# } ); #>
            </div>
            <# } #>
        </div>
        <?php
    }

    private function content_template_single_item($tab_index, $item, $element_uid, $default_index)
    {
        ?>
        <#
        const tabCount = tabIndex + 1,
            isActive = tabCount === defaultTabIndex,
            tabBtnId = 'mj-dock-tab-btn-' + elementUid + '-' + tabCount,
            panelId = 'mj-dock-tab-panel-' + elementUid + '-' + tabCount;

        view.addRenderAttribute('mj-dock-tab-button', {
            'id': tabBtnId,
            'class': [ 'mj-dock-tabs__tab', isActive ? 'is-active' : '' ],
            'data-tab-target': tabCount,
            'role': 'tab',
            'aria-controls': panelId,
            'aria-selected': isActive ? 'true' : 'false',
            'tabindex': isActive ? '0' : '-1',
        }, null, true);

        view.addRenderAttribute('mj-dock-tab-title', {
            'class': [ 'mj-dock-tabs__label' ],
            'data-binding-type': 'repeater-item',
            'data-binding-repeater-name': 'tabs',
            'data-binding-setting': [ 'tab_title' ],
            'data-binding-index': tabCount,
            'data-binding-config': JSON.stringify({
                'tab_title': {
                    editType: 'text',
                },
            }),
        }, null, true);
        #>
        <button type="button" {{{ view.getRenderAttributeString('mj-dock-tab-button') }}}>
            <span class="mj-dock-tabs__icon" aria-hidden="true">
                <# if ( item.tab_icon && item.tab_icon.url ) { #>
                <img src="{{ item.tab_icon.url }}" alt="{{ item.tab_title || 'Onglet' }}" loading="lazy" />
                <# } else { #>
                <span class="mj-dock-tabs__icon-fallback">{{{ ( item.tab_title || 'O' ).charAt(0) }}}</span>
                <# } #>
            </span>
            <span {{{ view.getRenderAttributeString('mj-dock-tab-title') }}}>{{{ item.tab_title || 'Onglet' }}}</span>
        </button>
        <?php
    }

    protected function get_initial_config(): array
    {
        return array_merge(parent::get_initial_config(), array(
            'support_improved_repeaters' => true,
            'support_nesting' => true,
            'target_container' => array('.mj-dock-tabs__dock'),
            'node' => 'button',
        ));
    }

    /**
     * Detect Elementor builder save requests (admin-ajax).
     */
    private function is_elementor_save_request(): bool
    {
        if (!(defined('DOING_AJAX') && DOING_AJAX)) {
            return false;
        }

        $action = isset($_REQUEST['action']) ? sanitize_key((string) $_REQUEST['action']) : '';
        if ($action !== 'elementor_ajax') {
            return false;
        }

        $actions = $_REQUEST['actions'] ?? null;
        if (is_array($actions)) {
            $encoded = wp_json_encode($actions);
            return is_string($encoded) && strpos($encoded, 'save_builder') !== false;
        }

        $actions_payload = isset($_REQUEST['actions']) ? wp_unslash((string) $_REQUEST['actions']) : '';
        if ($actions_payload === '') {
            return false;
        }

        return strpos($actions_payload, 'save_builder') !== false;
    }

    private function build_tab_background_config(array $item): array
    {
        $type = isset($item['tab_background_type']) ? sanitize_key((string) $item['tab_background_type']) : 'default';
        if (!in_array($type, array('default', 'solid', 'gradient', 'mosaic'), true)) {
            $type = 'default';
        }

        $panel_style = '';
        $mosaic_json = '';

        if ('solid' === $type) {
            $solid_color = isset($item['tab_bg_solid_color']) ? sanitize_hex_color((string) $item['tab_bg_solid_color']) : '';
            if ($solid_color) {
                $panel_style = '--mj-dock-panel-background: ' . $solid_color . '; background: ' . $solid_color . ';';
            }
        } elseif ('gradient' === $type) {
            $angle = isset($item['tab_bg_gradient_angle']['size']) ? (int) $item['tab_bg_gradient_angle']['size'] : 135;
            if ($angle < 0 || $angle > 360) {
                $angle = 135;
            }

            $c1 = isset($item['tab_bg_gradient_color_1']) ? sanitize_hex_color((string) $item['tab_bg_gradient_color_1']) : '#0b1220';
            $c2 = isset($item['tab_bg_gradient_color_2']) ? sanitize_hex_color((string) $item['tab_bg_gradient_color_2']) : '#111827';
            $c3 = isset($item['tab_bg_gradient_color_3']) ? sanitize_hex_color((string) $item['tab_bg_gradient_color_3']) : '#1f2937';

            $s1 = isset($item['tab_bg_gradient_stop_1']['size']) ? (int) $item['tab_bg_gradient_stop_1']['size'] : 0;
            $s2 = isset($item['tab_bg_gradient_stop_2']['size']) ? (int) $item['tab_bg_gradient_stop_2']['size'] : 55;
            $s3 = isset($item['tab_bg_gradient_stop_3']['size']) ? (int) $item['tab_bg_gradient_stop_3']['size'] : 100;

            $s1 = max(0, min(100, $s1));
            $s2 = max(0, min(100, $s2));
            $s3 = max(0, min(100, $s3));

            $gradient = 'linear-gradient(' . $angle . 'deg, ' . $c1 . ' ' . $s1 . '%, ' . $c2 . ' ' . $s2 . '%, ' . $c3 . ' ' . $s3 . '%)';
            $panel_style = '--mj-dock-panel-background: ' . $gradient . '; background: ' . $gradient . ';';
        } elseif ('mosaic' === $type) {
            $mosaic_limit = isset($item['tab_bg_mosaic_limit']) ? max(4, min(80, (int) $item['tab_bg_mosaic_limit'])) : 12;
            $mosaic_transition = isset($item['tab_bg_mosaic_transition']) ? sanitize_key((string) $item['tab_bg_mosaic_transition']) : 'hover';
            if (!in_array($mosaic_transition, array('hover', 'flip', 'fade'), true)) {
                $mosaic_transition = 'hover';
            }

            $mosaic_speed = isset($item['tab_bg_mosaic_speed']['size']) ? (float) $item['tab_bg_mosaic_speed']['size'] : 5.0;
            if ($mosaic_speed < 1) {
                $mosaic_speed = 1;
            }
            if ($mosaic_speed > 20) {
                $mosaic_speed = 20;
            }

            $mosaic_sessions = array();
            if (function_exists('mj_member_grimlins_gallery_list_sessions')) {
                $mosaic_sessions = mj_member_grimlins_gallery_list_sessions(array(
                    'limit' => $mosaic_limit,
                    'order' => 'desc',
                ));
            } elseif (function_exists('mj_member_grimlins_gallery_sample_data')) {
                $mosaic_sessions = mj_member_grimlins_gallery_sample_data();
                if (is_array($mosaic_sessions) && count($mosaic_sessions) > $mosaic_limit) {
                    $mosaic_sessions = array_slice($mosaic_sessions, 0, $mosaic_limit);
                }
            }

            $tiles = array();
            if (is_array($mosaic_sessions)) {
                foreach ($mosaic_sessions as $session) {
                    if (!is_array($session)) {
                        continue;
                    }
                    $before = isset($session['original_url']) ? esc_url_raw((string) $session['original_url']) : '';
                    $after = isset($session['result_url']) ? esc_url_raw((string) $session['result_url']) : '';
                    if ('' === $before && '' === $after) {
                        continue;
                    }
                    $tiles[] = array(
                        'before' => $before,
                        'after' => $after,
                    );
                    if (count($tiles) >= $mosaic_limit) {
                        break;
                    }
                }
            }

            if (!empty($tiles)) {
                $mosaic_json = wp_json_encode(array(
                    'transition' => $mosaic_transition,
                    'speed' => $mosaic_speed,
                    'tiles' => $tiles,
                ));
                $panel_style = '--mj-dock-panel-background: transparent; background: transparent;';
            } else {
                $type = 'default';
            }
        }

        return array(
            'type' => $type,
            'panel_style' => $panel_style,
            'mosaic_json' => is_string($mosaic_json) ? $mosaic_json : '',
        );
    }
}
