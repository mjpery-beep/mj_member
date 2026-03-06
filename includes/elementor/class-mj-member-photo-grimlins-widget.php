<?php

use Elementor\Controls_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
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

        /* ── Mosaïque ── */

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
                'max' => 80,
                'step' => 1,
                'default' => 12,
                'condition' => array('mosaic_enabled' => 'yes'),
            )
        );

        $this->add_control(
            'mosaic_transition',
            array(
                'label' => __('Effet de transition', 'mj-member'),
                'type' => Controls_Manager::SELECT,
                'default' => 'hover',
                'options' => array(
                    'hover' => __('Avatar seul (original au survol)', 'mj-member'),
                    'flip' => __('Rotation 3D', 'mj-member'),
                    'fade' => __('Fondu enchaîné', 'mj-member'),
                ),
                'condition' => array('mosaic_enabled' => 'yes'),
            )
        );

        $this->add_control(
            'mosaic_speed',
            array(
                'label' => __('Vitesse de transition (s)', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'range' => array(
                    'px' => array('min' => 1, 'max' => 20, 'step' => 0.5),
                ),
                'default' => array('size' => 5, 'unit' => 'px'),
                'condition' => array(
                    'mosaic_enabled' => 'yes',
                    'mosaic_transition!' => 'hover',
                ),
                'description' => __('Durée en secondes de chaque cycle avant/après.', 'mj-member'),
            )
        );

        $this->add_responsive_control(
            'mosaic_columns',
            array(
                'label' => __('Colonnes', 'mj-member'),
                'type' => Controls_Manager::NUMBER,
                'min' => 1,
                'max' => 10,
                'step' => 1,
                'default' => 5,
                'tablet_default' => 4,
                'mobile_default' => 3,
                'condition' => array('mosaic_enabled' => 'yes'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-photo-grimlins__mosaic' => '--mosaic-cols: {{VALUE}};',
                ),
                'description' => __('Nombre de colonnes d\'images dans la mosaïque.', 'mj-member'),
            )
        );

        $this->add_responsive_control(
            'mosaic_rows',
            array(
                'label' => __('Lignes', 'mj-member'),
                'type' => Controls_Manager::NUMBER,
                'min' => 1,
                'max' => 8,
                'step' => 1,
                'default' => 2,
                'tablet_default' => 2,
                'mobile_default' => 2,
                'condition' => array('mosaic_enabled' => 'yes'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-photo-grimlins' => '--mosaic-rows: {{VALUE}};',
                ),
                'description' => __('Nombre minimum de lignes visibles dans la mosaïque.', 'mj-member'),
            )
        );

        $this->end_controls_section();

        /* ── Appel à l'inscription ── */

        $this->start_controls_section(
            'section_cta_register',
            array(
                'label' => __('Bouton « Devenir membre »', 'mj-member'),
            )
        );

        $this->add_control(
            'cta_register_enabled',
            array(
                'label' => __('Afficher le bouton', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'no',
                'description' => __('Ajoute un bouton « Utiliser cet avatar pour devenir membre » à côté du téléchargement.', 'mj-member'),
            )
        );

        $this->add_control(
            'cta_register_label',
            array(
                'label' => __('Libellé du bouton', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Utiliser cet avatar pour devenir membre', 'mj-member'),
                'label_block' => true,
                'condition' => array('cta_register_enabled' => 'yes'),
            )
        );

        $this->add_control(
            'cta_register_url',
            array(
                'label' => __('URL de la page d\'inscription', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => '/mon-compte/inscription',
                'label_block' => true,
                'condition' => array('cta_register_enabled' => 'yes'),
                'description' => __('Le chemin ou l\'URL vers la page contenant le widget d\'inscription.', 'mj-member'),
            )
        );

        $this->end_controls_section();

        /* ── Affichage ── */

        $this->start_controls_section(
            'section_display',
            array(
                'label' => __('Affichage', 'mj-member'),
            )
        );

        $this->add_control(
            'fullscreen',
            array(
                'label' => __('Plein écran', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'no',
                'description' => __('Le widget occupe toute la fenêtre navigateur.', 'mj-member'),
            )
        );

        $this->end_controls_section();

        /* ── Style : Container contenu ── */

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
                    '%'  => array('min' => 40, 'max' => 100),
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

        /* ── Style : Couleurs du texte ── */

        $this->start_controls_section(
            'section_style_text',
            array(
                'label' => __('Couleurs du texte', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'title_color',
            array(
                'label' => __('Couleur du titre', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-photo-grimlins__title' => 'color: {{VALUE}} !important;',
                ),
            )
        );

        $this->add_control(
            'description_color',
            array(
                'label' => __('Couleur de la description', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-photo-grimlins__description' => 'color: {{VALUE}} !important;',
                ),
            )
        );

        $this->add_control(
            'dropzone_color',
            array(
                'label' => __('Couleur du texte dropzone', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-photo-grimlins__dropzone' => 'color: {{VALUE}} !important;',
                    '{{WRAPPER}} .mj-photo-grimlins__dropzone p' => 'color: {{VALUE}} !important;',
                    '{{WRAPPER}} .mj-photo-grimlins__dropzone strong' => 'color: {{VALUE}} !important;',
                    '{{WRAPPER}} .mj-photo-grimlins__dropzone span' => 'color: {{VALUE}} !important;',
                ),
            )
        );

        $this->end_controls_section();

        /* ── Style : Bouton « Devenir membre » ── */

        $this->start_controls_section(
            'section_style_cta_register',
            array(
                'label' => __('Bouton « Devenir membre »', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
                'condition' => array('cta_register_enabled' => 'yes'),
            )
        );

        if (class_exists('Elementor\\Group_Control_Typography')) {
            $this->add_group_control(
                Group_Control_Typography::get_type(),
                array(
                    'name' => 'cta_register_typography',
                    'selector' => '{{WRAPPER}} .mj-photo-grimlins__cta-register',
                )
            );
        }

        $this->add_responsive_control(
            'cta_register_padding',
            array(
                'label' => __('Marge interne', 'mj-member'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => array('px', 'em', '%'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-photo-grimlins__cta-register' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'cta_register_border_radius',
            array(
                'label' => __('Rayon des angles', 'mj-member'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => array('px', '%'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-photo-grimlins__cta-register' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->start_controls_tabs('cta_register_tabs');

        /* ── Normal ── */
        $this->start_controls_tab(
            'cta_register_tab_normal',
            array('label' => __('Normal', 'mj-member'))
        );

        $this->add_control(
            'cta_register_text_color',
            array(
                'label' => __('Couleur du texte', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-photo-grimlins__cta-register' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Background::get_type(),
            array(
                'name' => 'cta_register_background',
                'label' => __('Arrière-plan', 'mj-member'),
                'types' => array('classic', 'gradient'),
                'exclude' => array('image'),
                'selector' => '{{WRAPPER}} .mj-photo-grimlins__cta-register',
            )
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            array(
                'name' => 'cta_register_border',
                'selector' => '{{WRAPPER}} .mj-photo-grimlins__cta-register',
            )
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            array(
                'name' => 'cta_register_shadow',
                'selector' => '{{WRAPPER}} .mj-photo-grimlins__cta-register',
            )
        );

        $this->end_controls_tab();

        /* ── Hover ── */
        $this->start_controls_tab(
            'cta_register_tab_hover',
            array('label' => __('Hover', 'mj-member'))
        );

        $this->add_control(
            'cta_register_text_color_hover',
            array(
                'label' => __('Couleur du texte', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-photo-grimlins__cta-register:hover, {{WRAPPER}} .mj-photo-grimlins__cta-register:focus-visible' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Background::get_type(),
            array(
                'name' => 'cta_register_background_hover',
                'label' => __('Arrière-plan', 'mj-member'),
                'types' => array('classic', 'gradient'),
                'exclude' => array('image'),
                'selector' => '{{WRAPPER}} .mj-photo-grimlins__cta-register:hover, {{WRAPPER}} .mj-photo-grimlins__cta-register:focus-visible',
            )
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            array(
                'name' => 'cta_register_border_hover',
                'selector' => '{{WRAPPER}} .mj-photo-grimlins__cta-register:hover, {{WRAPPER}} .mj-photo-grimlins__cta-register:focus-visible',
            )
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            array(
                'name' => 'cta_register_shadow_hover',
                'selector' => '{{WRAPPER}} .mj-photo-grimlins__cta-register:hover, {{WRAPPER}} .mj-photo-grimlins__cta-register:focus-visible',
            )
        );

        $this->add_control(
            'cta_register_hover_transition',
            array(
                'label' => __('Durée de transition (ms)', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'range' => array(
                    'px' => array('min' => 0, 'max' => 1000, 'step' => 50),
                ),
                'default' => array('size' => 200, 'unit' => 'px'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-photo-grimlins__cta-register' => 'transition-duration: {{SIZE}}ms;',
                ),
            )
        );

        $this->end_controls_tab();
        $this->end_controls_tabs();

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
        $mosaic_limit = isset($settings['mosaic_limit']) ? max(4, min(80, (int) $settings['mosaic_limit'])) : 12;
        $mosaic_transition = isset($settings['mosaic_transition']) && in_array($settings['mosaic_transition'], array('flip', 'fade', 'hover'), true)
            ? $settings['mosaic_transition']
            : 'hover';
        $mosaic_speed = isset($settings['mosaic_speed']['size']) ? (float) $settings['mosaic_speed']['size'] : 5;
        $fullscreen = !empty($settings['fullscreen']) && $settings['fullscreen'] === 'yes';

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

        $cta_register_enabled = !empty($settings['cta_register_enabled']) && $settings['cta_register_enabled'] === 'yes';
        $cta_register_label = isset($settings['cta_register_label']) ? (string) $settings['cta_register_label'] : __('Utiliser cet avatar pour devenir membre', 'mj-member');
        $cta_register_url = isset($settings['cta_register_url']) ? (string) $settings['cta_register_url'] : '/mon-compte/inscription';

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
            'mosaic_speed' => $mosaic_speed,
            'fullscreen' => $fullscreen,
            'cta_register_enabled' => $cta_register_enabled,
            'cta_register_label' => $cta_register_label,
            'cta_register_url' => $cta_register_url,
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
