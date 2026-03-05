<?php

use Elementor\Controls_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Widget_Base;
use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

class Mj_Member_Elementor_Photo_Grimlins_Widget extends Widget_Base {
    use Mj_Member_Elementor_Widget_Visibility;

    public function get_name() {
        return 'mj-member-photo-grimlins';
    }

    public function get_title() {
        return __('Photo Grimlins MJ', 'mj-member');
    }

    public function get_icon() {
        return 'eicon-person';
    }

    public function get_categories() {
        return array('mj-member');
    }

    public function get_keywords() {
        return array('mj', 'photo', 'avatar', 'grimlins', 'ia');
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
                'default' => __('Transforme ta photo en Grimlins', 'mj-member'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'description',
            array(
                'label' => __('Description', 'mj-member'),
                'type' => Controls_Manager::TEXTAREA,
                'default' => __('Téléverse ta photo et laisse la magie opérer : le widget génère un avatar « Grimlins » amusant en quelques instants.', 'mj-member'),
                'rows' => 3,
            )
        );

        $this->add_control(
            'button_label',
            array(
                'label' => __('Libellé du bouton', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Générer mon Grimlins', 'mj-member'),
            )
        );

        $this->add_control(
            'members_only',
            array(
                'label' => __('Accessible uniquement pour les membres', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'no',
            )
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_mosaic',
            array(
                'label' => __('Mosaïque avant / après', 'mj-member'),
            )
        );

        $this->add_control(
            'mosaic_enabled',
            array(
                'label' => __('Afficher la mosaïque en fond', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->add_control(
            'mosaic_limit',
            array(
                'label' => __('Nombre de tuiles', 'mj-member'),
                'type' => Controls_Manager::NUMBER,
                'min' => 4,
                'max' => 24,
                'step' => 1,
                'default' => 12,
                'condition' => array(
                    'mosaic_enabled' => 'yes',
                ),
            )
        );

        $this->add_control(
            'mosaic_transition',
            array(
                'label' => __('Effet de transition', 'mj-member'),
                'type' => Controls_Manager::SELECT,
                'default' => 'flip',
                'options' => array(
                    'flip' => __('Rotation 3D', 'mj-member'),
                    'fade' => __('Fondu enchaîné', 'mj-member'),
                ),
                'condition' => array(
                    'mosaic_enabled' => 'yes',
                ),
            )
        );

        $this->end_controls_section();

        /* ── Style tab: Container contenu ── */

        $this->start_controls_section(
            'section_style_content',
            array(
                'label' => __('Container contenu', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_responsive_control(
            'content_width',
            array(
                'label' => __('Largeur', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px', '%'),
                'range' => array(
                    'px' => array('min' => 200, 'max' => 900),
                    '%' => array('min' => 40, 'max' => 100),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-photo-grimlins__content' => 'max-width: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'content_padding',
            array(
                'label' => __('Marge interne', 'mj-member'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => array('px', 'em', '%'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-photo-grimlins__content' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'content_border_radius',
            array(
                'label' => __('Rayon des angles', 'mj-member'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => array('px', '%'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-photo-grimlins__content' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Background::get_type(),
            array(
                'name' => 'content_background',
                'label' => __('Arrière-plan', 'mj-member'),
                'types' => array('classic', 'gradient'),
                'selector' => '{{WRAPPER}} .mj-photo-grimlins__content',
            )
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            array(
                'name' => 'content_border',
                'selector' => '{{WRAPPER}} .mj-photo-grimlins__content',
            )
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            array(
                'name' => 'content_shadow',
                'selector' => '{{WRAPPER}} .mj-photo-grimlins__content',
            )
        );

        $this->end_controls_section();

        $this->register_visibility_controls();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $this->apply_visibility_to_wrapper($settings, 'mj-photo-grimlins');

        $template_path = trailingslashit(Config::path()) . 'includes/templates/elementor/photo_grimlins.php';
        if (!file_exists($template_path)) {
            echo '<div class="mj-member-account-warning">' . esc_html__('Template du widget Photo Grimlins introuvable.', 'mj-member') . '</div>';
            return;
        }

        $is_preview = $this->is_elementor_preview_mode();

        if (!empty($settings['members_only']) && $settings['members_only'] === 'yes' && !is_user_logged_in() && !$is_preview) {
            echo '<div class="mj-member-account-warning">' . esc_html__('Cette fonctionnalité est réservée aux membres connectés.', 'mj-member') . '</div>';
            return;
        }

        $preview_svg = 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="512" height="512" viewBox="0 0 512 512"><defs><linearGradient id="g" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#a855f7"/><stop offset="100%" stop-color="#3b82f6"/></linearGradient></defs><rect width="512" height="512" fill="url(#g)"/><g fill="#fff" font-family="Arial, sans-serif" text-anchor="middle"><text x="50%" y="45%" font-size="42">Photo MJ</text><text x="50%" y="60%" font-size="28">(aperçu)</text></g></svg>');
        $result_svg = 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="512" height="512" viewBox="0 0 512 512"><defs><linearGradient id="r" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#22d3ee"/><stop offset="100%" stop-color="#14b8a6"/></linearGradient></defs><rect width="512" height="512" fill="url(#r)"/><circle cx="256" cy="210" r="120" fill="#0f172a" opacity="0.85"/><path d="M160 344c32-36 87-36 119 0 36-40 95-40 131 0-11 72-92 124-130 124-41 0-118-56-120-124z" fill="#0f172a" opacity="0.75"/><g fill="#fff" font-family="Arial, sans-serif" text-anchor="middle"><text x="50%" y="86%" font-size="32">Grimlins</text></g></svg>');

        $mosaic_enabled = !empty($settings['mosaic_enabled']) && $settings['mosaic_enabled'] === 'yes';
        $mosaic_limit = isset($settings['mosaic_limit']) ? max(4, min(24, (int) $settings['mosaic_limit'])) : 12;
        $mosaic_transition = isset($settings['mosaic_transition']) && in_array($settings['mosaic_transition'], array('flip', 'fade'), true)
            ? $settings['mosaic_transition']
            : 'flip';

        $mosaic_sessions = array();
        if ($mosaic_enabled) {
            if ($is_preview && function_exists('mj_member_grimlins_gallery_sample_data')) {
                $sample = mj_member_grimlins_gallery_sample_data();
                while (count($mosaic_sessions) < $mosaic_limit && !empty($sample)) {
                    $mosaic_sessions = array_merge($mosaic_sessions, $sample);
                }
                $mosaic_sessions = array_slice($mosaic_sessions, 0, $mosaic_limit);
            } elseif (function_exists('mj_member_grimlins_gallery_list_sessions')) {
                $mosaic_sessions = mj_member_grimlins_gallery_list_sessions(array(
                    'limit' => $mosaic_limit,
                    'order' => 'desc',
                ));
            }
        }

        $template_data = array(
            'title' => isset($settings['title']) ? (string) $settings['title'] : '',
            'description' => isset($settings['description']) ? (string) $settings['description'] : '',
            'button_label' => isset($settings['button_label']) ? (string) $settings['button_label'] : '',
            'is_preview' => $is_preview,
            'preview_image' => $is_preview ? $preview_svg : '',
            'result_image' => $is_preview ? $result_svg : '',
            'members_only' => (!empty($settings['members_only']) && $settings['members_only'] === 'yes'),
            'mosaic_enabled' => $mosaic_enabled,
            'mosaic_sessions' => $mosaic_sessions,
            'mosaic_transition' => $mosaic_transition,
        );

        /**
         * Permet aux modules d’adapter les données envoyées au template Photo Grimlins.
         *
         * @param array<string,mixed> $template_data
         * @param array<string,mixed> $settings
         * @param Mj_Member_Elementor_Photo_Grimlins_Widget $widget
         */
        $template_data = apply_filters('mj_member_photo_grimlins_template_data', $template_data, $settings, $this);
        $template_data = is_array($template_data) ? $template_data : array();
        $template_data['is_preview'] = !empty($template_data['is_preview']);

        include $template_path;
    }

    private function is_elementor_preview_mode(): bool
    {
        if (did_action('elementor/loaded')) {
            $elementor = \Elementor\Plugin::$instance;
            if ($elementor && isset($elementor->editor) && method_exists($elementor->editor, 'is_edit_mode')) {
                return (bool) $elementor->editor->is_edit_mode();
            }
        }

        return false;
    }
}
