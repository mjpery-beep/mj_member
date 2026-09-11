<?php

if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;
use Mj\Member\Classes\MjAccountLinks;
use Mj\Member\Core\AssetsManager;

class Mj_Member_Elementor_Account_Links_Slider_Widget extends Widget_Base {
    use Mj_Member_Elementor_Widget_Visibility;

    public function get_name() {
        return 'mj-member-account-links-slider';
    }

    public function get_title() {
        return __('Slider Liens Mon Compte MJ', 'mj-member');
    }

    public function get_icon() {
        return 'eicon-slider-push';
    }

    public function get_categories() {
        return array('mj-member');
    }

    public function get_keywords() {
        return array('mj', 'member', 'slider', 'carousel', 'account', 'compte');
    }

    public function get_style_depends() {
        return array('mj-member-account-links-slider');
    }

    public function get_script_depends() {
        return array('mj-member-account-links-slider');
    }

    protected function register_controls() {
        $default_account_url = function_exists('mj_member_get_account_redirect')
            ? mj_member_get_account_redirect()
            : home_url('/mon-compte');

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
                'label_block' => true,
            )
        );

        $this->add_control(
            'intro',
            array(
                'label' => __('Texte introductif', 'mj-member'),
                'type' => Controls_Manager::TEXTAREA,
                'rows' => 3,
            )
        );

        $this->add_control(
            'links_notice',
            array(
                'type' => Controls_Manager::RAW_HTML,
                'raw' => '<em>' . esc_html__('Les puces affichées correspondent aux liens actifs (icône, capture d’écran, description) configurés dans MJ Member > Configuration > Liens Mon compte.', 'mj-member') . '</em>',
                'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
            )
        );

        $this->add_control(
            'account_base_url',
            array(
                'label' => __('URL de la page "Mon compte"', 'mj-member'),
                'type' => Controls_Manager::URL,
                'label_block' => true,
                'placeholder' => $default_account_url,
                'default' => array(
                    'url' => $default_account_url,
                ),
            )
        );

        $this->add_control(
            'only_with_screenshot',
            array(
                'label' => __('Uniquement les liens avec capture d’écran', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => '',
                'description' => __('Masque les liens qui n’ont pas de capture d’écran configurée.', 'mj-member'),
            )
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_slider_settings',
            array(
                'label' => __('Réglages du slider', 'mj-member'),
            )
        );

        $this->add_control(
            'show_arrows',
            array(
                'label' => __('Afficher les flèches', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->add_control(
            'show_bullets',
            array(
                'label' => __('Afficher les puces (icônes)', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->add_control(
            'autoplay',
            array(
                'label' => __('Défilement automatique', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => '',
            )
        );

        $this->add_control(
            'autoplay_delay',
            array(
                'label' => __('Délai (ms)', 'mj-member'),
                'type' => Controls_Manager::NUMBER,
                'min' => 1500,
                'max' => 20000,
                'step' => 500,
                'default' => 5000,
                'condition' => array('autoplay' => 'yes'),
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

        $this->add_control(
            'container_background',
            array(
                'label' => __('Couleur de fond', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-account-links-slider' => 'background-color: {{VALUE}};',
                ),
            )
        );

        if (class_exists(Group_Control_Border::class)) {
            $this->add_group_control(
                Group_Control_Border::get_type(),
                array(
                    'name' => 'container_border',
                    'selector' => '{{WRAPPER}} .mj-member-account-links-slider',
                )
            );
        }

        if (class_exists(Group_Control_Box_Shadow::class)) {
            $this->add_group_control(
                Group_Control_Box_Shadow::get_type(),
                array(
                    'name' => 'container_shadow',
                    'selector' => '{{WRAPPER}} .mj-member-account-links-slider',
                )
            );
        }

        $this->add_responsive_control(
            'container_padding',
            array(
                'label' => __('Marge interne', 'mj-member'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => array('px', 'em', '%'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-account-links-slider' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'media_height',
            array(
                'label' => __('Hauteur de la capture d’écran', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px', 'vh'),
                'range' => array(
                    'px' => array('min' => 120, 'max' => 800),
                    'vh' => array('min' => 10, 'max' => 90),
                ),
                'default' => array('unit' => 'px', 'size' => 360),
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-account-links-slider__media' => 'height: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_content',
            array(
                'label' => __('Titre & description', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'title_color',
            array(
                'label' => __('Couleur du titre', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-account-links-slider__title' => 'color: {{VALUE}};',
                ),
            )
        );

        if (class_exists(Group_Control_Typography::class)) {
            $this->add_group_control(
                Group_Control_Typography::get_type(),
                array(
                    'name' => 'title_typography',
                    'selector' => '{{WRAPPER}} .mj-member-account-links-slider__title',
                )
            );
        }

        $this->add_control(
            'slide_label_color',
            array(
                'label' => __('Couleur du libellé de la puce active', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-account-links-slider__label' => 'color: {{VALUE}};',
                ),
            )
        );

        if (class_exists(Group_Control_Typography::class)) {
            $this->add_group_control(
                Group_Control_Typography::get_type(),
                array(
                    'name' => 'slide_label_typography',
                    'selector' => '{{WRAPPER}} .mj-member-account-links-slider__label',
                )
            );
        }

        $this->add_control(
            'slide_description_color',
            array(
                'label' => __('Couleur de la description', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-account-links-slider__description' => 'color: {{VALUE}};',
                ),
            )
        );

        if (class_exists(Group_Control_Typography::class)) {
            $this->add_group_control(
                Group_Control_Typography::get_type(),
                array(
                    'name' => 'slide_description_typography',
                    'selector' => '{{WRAPPER}} .mj-member-account-links-slider__description',
                )
            );
        }

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_bullets',
            array(
                'label' => __('Puces (icônes)', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_responsive_control(
            'bullet_size',
            array(
                'label' => __('Taille des puces', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array(
                    'px' => array('min' => 24, 'max' => 96),
                ),
                'default' => array('unit' => 'px', 'size' => 44),
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-account-links-slider__bullet' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'bullet_background_color',
            array(
                'label' => __('Fond', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-account-links-slider__bullet' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'bullet_active_background_color',
            array(
                'label' => __('Fond (puce active)', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-account-links-slider__bullet.is-active' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'bullet_border_color',
            array(
                'label' => __('Bordure (puce active)', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-account-links-slider__bullet.is-active' => 'border-color: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_arrows',
            array(
                'label' => __('Flèches', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
                'condition' => array('show_arrows' => 'yes'),
            )
        );

        $this->add_control(
            'arrow_color',
            array(
                'label' => __('Couleur', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-account-links-slider__arrow' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'arrow_background_color',
            array(
                'label' => __('Fond', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-account-links-slider__arrow' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_section();
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function get_slides(array $settings, bool $preview_mode) {
        if (!class_exists(MjAccountLinks::class)) {
            return array();
        }

        $default_account_url = function_exists('mj_member_get_account_redirect')
            ? mj_member_get_account_redirect()
            : home_url('/mon-compte');

        $account_base_setting = isset($settings['account_base_url']['url']) ? $settings['account_base_url']['url'] : '';
        $account_base = $account_base_setting !== '' ? esc_url_raw($account_base_setting) : $default_account_url;

        $links = MjAccountLinks::getLinks($account_base, array(
            'context' => 'elementor_account_links_slider_widget',
            'widget_id' => $this->get_id(),
            'preview_mode' => $preview_mode,
        ));

        $only_with_screenshot = isset($settings['only_with_screenshot']) && $settings['only_with_screenshot'] === 'yes';

        $slides = array();
        foreach ($links as $link) {
            if (!is_array($link) || empty($link['label']) || empty($link['url'])) {
                continue;
            }

            $screenshot = isset($link['screenshot']) && is_array($link['screenshot']) ? $link['screenshot'] : array();
            $screenshot_url = !empty($screenshot['url']) ? esc_url($screenshot['url']) : '';

            if ($only_with_screenshot && $screenshot_url === '') {
                continue;
            }

            $icon_html = '';
            if (!empty($link['icon']) && is_array($link['icon']) && !empty($link['icon']['html'])) {
                $icon_html = $link['icon']['html'];
            }

            $slides[] = array(
                'key' => isset($link['key']) ? sanitize_key($link['key']) : '',
                'label' => wp_strip_all_tags($link['label']),
                'description' => isset($link['description']) ? trim((string) $link['description']) : '',
                'url' => esc_url($link['url']),
                'icon_html' => $icon_html,
                'screenshot_url' => $screenshot_url,
                'screenshot_alt' => !empty($screenshot['alt']) ? $screenshot['alt'] : wp_strip_all_tags($link['label']),
            );
        }

        return $slides;
    }

    protected function render() {
        if (!class_exists(MjAccountLinks::class)) {
            echo '<div class="mj-member-account-warning">' . esc_html__('Le module MJ Member doit être actif pour utiliser ce widget.', 'mj-member') . '</div>';
            return;
        }

        $settings = $this->get_settings_for_display();
        $this->apply_visibility_to_wrapper($settings, 'mj-member-account-links-slider');

        AssetsManager::requirePackage('account-links-slider');

        $preview_mode = function_exists('mj_member_login_component_is_preview_mode')
            ? mj_member_login_component_is_preview_mode()
            : false;

        if (!is_user_logged_in() && !$preview_mode) {
            return;
        }

        $slides = $this->get_slides($settings, $preview_mode);

        if (empty($slides)) {
            if ($preview_mode) {
                echo '<div class="mj-member-account-warning">' . esc_html__('Aucun lien "Mon compte" actif à afficher. Configurez des liens (icône, capture d’écran) dans MJ Member > Configuration.', 'mj-member') . '</div>';
            }
            return;
        }

        $show_arrows = !isset($settings['show_arrows']) || $settings['show_arrows'] === 'yes';
        $show_bullets = !isset($settings['show_bullets']) || $settings['show_bullets'] === 'yes';
        $autoplay = isset($settings['autoplay']) && $settings['autoplay'] === 'yes';
        $autoplay_delay = isset($settings['autoplay_delay']) ? max(1500, (int) $settings['autoplay_delay']) : 5000;

        $wrapper_attrs = array(
            'class="mj-member-account-links-slider"',
            'data-autoplay="' . ($autoplay ? '1' : '0') . '"',
            'data-autoplay-delay="' . esc_attr((string) $autoplay_delay) . '"',
        );

        echo '<div ' . implode(' ', $wrapper_attrs) . '>';

        if (!empty($settings['title'])) {
            echo '<h2 class="mj-member-account-links-slider__title">' . esc_html($settings['title']) . '</h2>';
        }

        if (!empty($settings['intro'])) {
            echo '<p class="mj-member-account-links-slider__intro">' . wp_kses_post($settings['intro']) . '</p>';
        }

        echo '<div class="mj-member-account-links-slider__stage">';
        echo '<div class="mj-member-account-links-slider__viewport">';
        echo '<div class="mj-member-account-links-slider__track">';

        foreach ($slides as $index => $slide) {
            $slide_classes = array('mj-member-account-links-slider__slide');
            if ($index === 0) {
                $slide_classes[] = 'is-active';
            }
            echo '<div class="' . esc_attr(implode(' ', $slide_classes)) . '" data-slide-index="' . esc_attr((string) $index) . '">';

            echo '<a class="mj-member-account-links-slider__media" href="' . $slide['url'] . '">';
            if ($slide['screenshot_url'] !== '') {
                echo '<img src="' . $slide['screenshot_url'] . '" alt="' . esc_attr($slide['screenshot_alt']) . '" loading="lazy" decoding="async" />';
            } else {
                echo '<span class="mj-member-account-links-slider__media-placeholder">';
                if ($slide['icon_html'] !== '') {
                    echo $slide['icon_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                }
                echo '</span>';
            }
            echo '</a>';

            echo '<div class="mj-member-account-links-slider__content">';
            echo '<a class="mj-member-account-links-slider__link" href="' . $slide['url'] . '">';
            echo '<span class="mj-member-account-links-slider__label">' . esc_html($slide['label']) . '</span>';
            echo '</a>';
            if ($slide['description'] !== '') {
                echo '<p class="mj-member-account-links-slider__description">' . esc_html($slide['description']) . '</p>';
            }
            echo '</div>';

            echo '</div>';
        }

        echo '</div>'; // track
        echo '</div>'; // viewport

        if ($show_arrows && count($slides) > 1) {
            echo '<button type="button" class="mj-member-account-links-slider__arrow mj-member-account-links-slider__arrow--prev" aria-label="' . esc_attr__('Précédent', 'mj-member') . '"><span aria-hidden="true">&lsaquo;</span></button>';
            echo '<button type="button" class="mj-member-account-links-slider__arrow mj-member-account-links-slider__arrow--next" aria-label="' . esc_attr__('Suivant', 'mj-member') . '"><span aria-hidden="true">&rsaquo;</span></button>';
        }

        echo '</div>'; // stage

        if ($show_bullets && count($slides) > 1) {
            echo '<div class="mj-member-account-links-slider__bullets" role="tablist">';
            foreach ($slides as $index => $slide) {
                $bullet_classes = array('mj-member-account-links-slider__bullet');
                if ($index === 0) {
                    $bullet_classes[] = 'is-active';
                }
                echo '<button type="button" class="' . esc_attr(implode(' ', $bullet_classes)) . '" data-slide-index="' . esc_attr((string) $index) . '" role="tab" aria-label="' . esc_attr($slide['label']) . '" title="' . esc_attr($slide['label']) . '">';
                if ($slide['icon_html'] !== '') {
                    echo $slide['icon_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                } else {
                    echo '<span class="mj-member-account-links-slider__bullet-dot" aria-hidden="true"></span>';
                }
                echo '</button>';
            }
            echo '</div>';
        }

        echo '</div>'; // wrapper
    }
}
