<?php

if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;

class Mj_Member_Elementor_Registration_Widget extends Widget_Base {
    use Mj_Member_Elementor_Widget_Visibility;

    public function get_name() {
        return 'mj-member-registration-form';
    }

    public function get_title() {
        return __('Formulaire d\'inscription MJ', 'mj-member');
    }

    public function get_icon() {
        return 'eicon-form-horizontal';
    }

    public function get_categories() {
        return array('mj-member');
    }

    public function get_keywords() {
        return array('mj', 'inscription', 'registration', 'formulaire', 'member');
    }

    protected function register_controls() {
        $this->start_controls_section(
            'section_content',
            array(
                'label' => __('Contenu', 'mj-member'),
            )
        );

        $this->add_control(
            'show_title',
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
            'title_text',
            array(
                'label' => __('Titre', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Inscription MJ', 'mj-member'),
                'label_block' => true,
                'condition' => array('show_title' => 'yes'),
            )
        );

        $this->add_control(
            'title_image',
            array(
                'label' => __('Image du titre', 'mj-member'),
                'type' => Controls_Manager::MEDIA,
                'condition' => array('show_title' => 'yes'),
                'dynamic' => array('active' => true),
            )
        );

        $this->add_control(
            'title_image_position',
            array(
                'label' => __('Position de l\'image', 'mj-member'),
                'type' => Controls_Manager::SELECT,
                'options' => array(
                    'inline-right' => __('À droite du titre', 'mj-member'),
                    'inline-left' => __('À gauche du titre', 'mj-member'),
                    'above-center' => __('Au-dessus, centré', 'mj-member'),
                    'above-left' => __('Au-dessus, aligné à gauche', 'mj-member'),
                    'above-right' => __('Au-dessus, aligné à droite', 'mj-member'),
                ),
                'default' => 'inline-right',
                'condition' => array(
                    'show_title' => 'yes',
                    'title_image[id]!' => '',
                ),
            )
        );

        $this->add_control(
            'title_image_alt',
            array(
                'label' => __('Texte alternatif de l\'image', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'label_block' => true,
                'condition' => array('show_title' => 'yes'),
            )
        );

        $this->add_control(
            'logged_out_message',
            array(
                'label' => __('Message (visiteur)', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => '',
                'label_block' => true,
            )
        );

        $this->add_control(
            'logged_in_message',
            array(
                'label' => __('Message (connecté)', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Tu es déjà inscrit.', 'mj-member'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'require_regulation',
            array(
                'label' => __('Exiger l\'acceptation du règlement intérieur', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'no',
            )
        );

        $default_regulation_page = (int) get_option('mj_registration_regulation_page', 0);

        $this->add_control(
            'regulation_page_id',
            array(
                'label' => __('Page du règlement intérieur', 'mj-member'),
                'type' => Controls_Manager::SELECT,
                'options' => $this->get_regulation_page_options(),
                'default' => $default_regulation_page > 0 ? (string) $default_regulation_page : '',
                'condition' => array('require_regulation' => 'yes'),
            )
        );

        $this->add_control(
            'regulation_modal_title',
            array(
                'label' => __('Titre du popup', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Règlement intérieur', 'mj-member'),
                'label_block' => true,
                'condition' => array('require_regulation' => 'yes'),
            )
        );

        $this->add_control(
            'regulation_trigger_label',
            array(
                'label' => __('Libellé du lien', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Règlement d\'ordre intérieur', 'mj-member'),
                'label_block' => true,
                'condition' => array('require_regulation' => 'yes'),
            )
        );

        $this->add_control(
            'regulation_checkbox_label',
            array(
                'label' => __('Libellé de la case à cocher', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Je confirme avoir pris connaissance du %s.', 'mj-member'),
                'label_block' => true,
                'condition' => array('require_regulation' => 'yes'),
            )
        );

        $this->end_controls_section();

        $this->register_visibility_controls();

        $this->start_controls_section(
            'section_style_container',
            array(
                'label' => __('Bloc', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_responsive_control(
            'container_padding',
            array(
                'label' => __('Marge interne', 'mj-member'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => array('px', '%', 'em'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-inscription-container' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'container_max_width',
            array(
                'label' => __('Largeur max', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px', '%'),
                'range' => array(
                    'px' => array('min' => 300, 'max' => 1400),
                    '%' => array('min' => 10, 'max' => 100),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-inscription-container' => 'max-width: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'container_background_color',
            array(
                'label' => __('Fond', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-inscription-container' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_responsive_control(
            'container_border_radius',
            array(
                'label' => __('Rayon des angles', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array('px' => array('min' => 0, 'max' => 80)),
                'selectors' => array(
                    '{{WRAPPER}} .mj-inscription-container' => 'border-radius: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            array(
                'name' => 'container_border',
                'selector' => '{{WRAPPER}} .mj-inscription-container',
            )
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            array(
                'name' => 'container_shadow',
                'selector' => '{{WRAPPER}} .mj-inscription-container',
            )
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_title',
            array(
                'label' => __('Titre', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
                'condition' => array('show_title' => 'yes'),
            )
        );

        $this->add_control(
            'title_alignment',
            array(
                'label' => __('Alignement', 'mj-member'),
                'type' => Controls_Manager::CHOOSE,
                'options' => array(
                    'left' => array(
                        'title' => __('Gauche', 'mj-member'),
                        'icon' => 'eicon-text-align-left',
                    ),
                    'center' => array(
                        'title' => __('Centre', 'mj-member'),
                        'icon' => 'eicon-text-align-center',
                    ),
                    'right' => array(
                        'title' => __('Droite', 'mj-member'),
                        'icon' => 'eicon-text-align-right',
                    ),
                ),
                'default' => 'left',
                'selectors' => array(
                    '{{WRAPPER}} .mj-inscription-container__header' => 'justify-content: {{VALUE}};',
                ),
                'selectors_dictionary' => array(
                    'left' => 'flex-start',
                    'center' => 'center',
                    'right' => 'flex-end',
                ),
            )
        );

        $this->add_control(
            'title_color',
            array(
                'label' => __('Couleur du titre', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-inscription-container__title' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            array(
                'name' => 'title_typography',
                'selector' => '{{WRAPPER}} .mj-inscription-container__title',
            )
        );

        $this->add_responsive_control(
            'title_gap',
            array(
                'label' => __('Espacement', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array('px' => array('min' => 0, 'max' => 48)),
                'selectors' => array(
                    '{{WRAPPER}} .mj-inscription-container__header' => 'gap: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'title_spacing',
            array(
                'label' => __('Marge inférieure', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array('px' => array('min' => 0, 'max' => 80)),
                'selectors' => array(
                    '{{WRAPPER}} .mj-inscription-container__header' => 'margin-bottom: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'title_text_margin_top',
            array(
                'label' => __('Marge supérieure du texte', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array('px' => array('min' => 0, 'max' => 120)),
                'selectors' => array(
                    '{{WRAPPER}} .mj-inscription-container__title' => '--mj-title-margin-top: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'title_image_width',
            array(
                'label' => __('Taille de l\'image', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px', '%'),
                'range' => array(
                    'px' => array('min' => 20, 'max' => 300),
                    '%' => array('min' => 5, 'max' => 100),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-inscription-container__title-image img' => 'width: {{SIZE}}{{UNIT}};',
                ),
                'condition' => array('show_title' => 'yes'),
            )
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_message',
            array(
                'label' => __('Message introductif', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'intro_text_color',
            array(
                'label' => __('Couleur du texte', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-inscription-message' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            array(
                'name' => 'intro_typography',
                'selector' => '{{WRAPPER}} .mj-inscription-message',
            )
        );

        $this->add_responsive_control(
            'intro_alignment',
            array(
                'label' => __('Alignement', 'mj-member'),
                'type' => Controls_Manager::CHOOSE,
                'options' => array(
                    'left' => array('title' => __('Gauche', 'mj-member'), 'icon' => 'eicon-text-align-left'),
                    'center' => array('title' => __('Centre', 'mj-member'), 'icon' => 'eicon-text-align-center'),
                    'right' => array('title' => __('Droite', 'mj-member'), 'icon' => 'eicon-text-align-right'),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-inscription-message' => 'text-align: {{VALUE}};',
                ),
            )
        );

        $this->add_responsive_control(
            'intro_margin_bottom',
            array(
                'label' => __('Marge inférieure', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array('px' => array('min' => 0, 'max' => 80)),
                'selectors' => array(
                    '{{WRAPPER}} .mj-inscription-message' => 'margin-bottom: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_fields',
            array(
                'label' => __('Champs', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'field_label_color',
            array(
                'label' => __('Couleur des labels', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-field-group label' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            array(
                'name' => 'field_label_typography',
                'selector' => '{{WRAPPER}} .mj-field-group label',
            )
        );

        $this->add_control(
            'field_input_text_color',
            array(
                'label' => __('Texte des champs', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-field-group input, {{WRAPPER}} .mj-field-group textarea' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'field_input_background_color',
            array(
                'label' => __('Fond des champs', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-field-group input, {{WRAPPER}} .mj-field-group textarea' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'field_input_border_color',
            array(
                'label' => __('Bordure', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-field-group input, {{WRAPPER}} .mj-field-group textarea' => 'border-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'field_input_focus_border_color',
            array(
                'label' => __('Bordure (focus)', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-field-group input:focus, {{WRAPPER}} .mj-field-group textarea:focus' => 'border-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'field_input_focus_shadow_color',
            array(
                'label' => __('Halo (focus)', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-field-group input:focus, {{WRAPPER}} .mj-field-group textarea:focus' => 'box-shadow: 0 0 0 2px {{VALUE}};',
                ),
            )
        );

        $this->add_responsive_control(
            'field_input_border_radius',
            array(
                'label' => __('Rayon des champs', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array('px' => array('min' => 0, 'max' => 40)),
                'selectors' => array(
                    '{{WRAPPER}} .mj-field-group input, {{WRAPPER}} .mj-field-group textarea' => 'border-radius: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_buttons',
            array(
                'label' => __('Boutons', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            array(
                'name' => 'button_typography',
                'selector' => '{{WRAPPER}} .mj-button',
            )
        );

        $this->add_control(
            'button_text_color',
            array(
                'label' => __('Texte principal', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-button' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'button_background_color',
            array(
                'label' => __('Fond principal', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-button' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'button_hover_text_color',
            array(
                'label' => __('Texte principal (survol)', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-button:hover' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'button_hover_background_color',
            array(
                'label' => __('Fond principal (survol)', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-button:hover' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_responsive_control(
            'button_radius',
            array(
                'label' => __('Rayon des boutons', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array('px' => array('min' => 0, 'max' => 40)),
                'selectors' => array(
                    '{{WRAPPER}} .mj-button' => 'border-radius: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'button_secondary_text_color',
            array(
                'label' => __('Texte secondaire', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-button--secondary' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'button_secondary_background_color',
            array(
                'label' => __('Fond secondaire', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-button--secondary' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'button_secondary_border_color',
            array(
                'label' => __('Bordure secondaire', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-button--secondary' => 'border-color: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_notices',
            array(
                'label' => __('Messages système', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'notice_success_background',
            array(
                'label' => __('Fond succès', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-notice--success' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'notice_success_text_color',
            array(
                'label' => __('Texte succès', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-notice--success' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'notice_error_background',
            array(
                'label' => __('Fond erreur', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-notice--error' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'notice_error_text_color',
            array(
                'label' => __('Texte erreur', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-notice--error' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $this->apply_visibility_to_wrapper($settings, 'mj-member-registration');

        if (!function_exists('mj_member_render_registration_form')) {
            echo '<div class="mj-member-registration-warning">' . esc_html__('Le module d\'inscription MJ Member est introuvable.', 'mj-member') . '</div>';
            return;
        }

        $show_title = isset($settings['show_title']) && $settings['show_title'] === 'yes';
        $image_position = isset($settings['title_image_position']) ? (string) $settings['title_image_position'] : 'inline-right';
        $image_position_allowed = array('inline-right', 'inline-left', 'above-center', 'above-left', 'above-right');
        if (!in_array($image_position, $image_position_allowed, true)) {
            $image_position = 'inline-right';
        }
        $title_settings = array(
            'show' => $show_title,
            'text' => $show_title && !empty($settings['title_text']) ? $settings['title_text'] : '',
            'image_id' => ($show_title && !empty($settings['title_image']['id'])) ? (int) $settings['title_image']['id'] : 0,
            'image_url' => ($show_title && !empty($settings['title_image']['url'])) ? $settings['title_image']['url'] : '',
            'image_alt' => $show_title && !empty($settings['title_image_alt']) ? $settings['title_image_alt'] : '',
            'image_position' => $image_position,
            'margin_top' => '',
        );

        if ($show_title && isset($settings['title_text_margin_top']['size']) && $settings['title_text_margin_top']['size'] !== '') {
            $margin_unit = isset($settings['title_text_margin_top']['unit']) ? $settings['title_text_margin_top']['unit'] : 'px';
            $title_settings['margin_top'] = $settings['title_text_margin_top']['size'] . $margin_unit;
        }

        $require_regulation = isset($settings['require_regulation']) && $settings['require_regulation'] === 'yes';
        $regulation_page_id = $require_regulation && !empty($settings['regulation_page_id']) ? (int) $settings['regulation_page_id'] : 0;
        $regulation_url = '';
        $regulation_content = '';

        if ($regulation_page_id > 0) {
            $regulation_url = get_permalink($regulation_page_id);
            $page_post = get_post($regulation_page_id);
            if ($page_post instanceof \WP_Post && $page_post->post_status === 'publish') {
                $content = apply_filters('the_content', $page_post->post_content);
                if (is_string($content) && $content !== '') {
                    $regulation_content = wp_kses_post($content);
                }
            }
        }

        $regulation_enabled = $require_regulation && ($regulation_url !== '' || $regulation_content !== '');
        $regulation_data = array(
            'enabled' => $regulation_enabled,
            'page_id' => $regulation_enabled ? $regulation_page_id : 0,
            'url' => $regulation_enabled ? $regulation_url : '',
            'modal_title' => $regulation_enabled && !empty($settings['regulation_modal_title']) ? $settings['regulation_modal_title'] : __('Règlement intérieur', 'mj-member'),
            'trigger_label' => $regulation_enabled && !empty($settings['regulation_trigger_label']) ? $settings['regulation_trigger_label'] : __('Lire le règlement intérieur', 'mj-member'),
            'checkbox_label' => $regulation_enabled && !empty($settings['regulation_checkbox_label']) ? $settings['regulation_checkbox_label'] : __('Je confirme avoir lu et accepté le règlement intérieur.', 'mj-member'),
            'content' => $regulation_content,
        );

        $args = array(
            'message_logged_out' => isset($settings['logged_out_message']) ? $settings['logged_out_message'] : '',
            'message_logged_in' => isset($settings['logged_in_message']) ? $settings['logged_in_message'] : '',
            'title' => $title_settings,
            'regulation' => $regulation_data,
        );

        echo mj_member_render_registration_form($args);
    }

    /**
     * @return array<string,string>
     */
    protected function get_regulation_page_options() {
        $options = array('' => __('— Sélectionner —', 'mj-member'));
        $pages = get_pages(
            array(
                'sort_column' => 'post_title',
                'sort_order' => 'ASC',
                'post_status' => 'publish',
            )
        );

        if (!empty($pages)) {
            foreach ($pages as $page) {
                if (!($page instanceof \WP_Post)) {
                    continue;
                }

                $title = wp_strip_all_tags($page->post_title);
                if ($title === '') {
                    $title = sprintf(__('Page #%d', 'mj-member'), (int) $page->ID);
                }

                $options[$page->ID] = $title;
            }
        }

        return $options;
    }
}
