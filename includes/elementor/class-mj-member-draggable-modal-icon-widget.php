<?php

if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Controls_Manager;
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

        $this->end_controls_section();

        $this->register_visibility_controls();
    }

    protected function render() {
        AssetsManager::requirePackage('draggable-modal-icon');

        $settings = $this->get_settings_for_display();
        $this->apply_visibility_to_wrapper($settings, 'mj-dmiw-widget');

        $modal_title = isset($settings['modal_title']) ? sanitize_text_field((string) $settings['modal_title']) : __('Contenu', 'mj-member');
        $close_on_overlay = !isset($settings['close_on_overlay']) || $settings['close_on_overlay'] === 'yes';
        $icon_url = isset($settings['trigger_icon']['url']) ? esc_url((string) $settings['trigger_icon']['url']) : '';
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
        <div class="mj-dmiw" data-mj-draggable-modal-widget="1" data-overlay-close="0">
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
                    <button type="button" class="mj-dmiw__close" aria-label="<?php echo esc_attr__('Fermer', 'mj-member'); ?>">&times;</button>
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
