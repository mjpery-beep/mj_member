<?php

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Widget_Base;
use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

class Mj_Member_Elementor_Iframe_Widget extends Widget_Base {
    use Mj_Member_Elementor_Widget_Visibility;

    public function get_name() {
        return 'mj-member-iframe';
    }

    public function get_title() {
        return __('MJ - Iframe', 'mj-member');
    }

    public function get_icon() {
        return 'eicon-code';
    }

    public function get_categories() {
        return array('mj-member');
    }

    public function get_keywords() {
        return array('mj', 'iframe', 'embed', 'integration', 'externe', 'widget');
    }

    public function get_style_depends() {
        return array('mj-member-components', 'mj-member-iframe-widget');
    }

    protected function register_controls() {
        $this->start_controls_section(
            'section_content',
            array(
                'label' => __('Contenu iframe', 'mj-member'),
            )
        );

        $this->add_control(
            'iframe_src',
            array(
                'label' => __('URL iframe', 'mj-member'),
                'type' => Controls_Manager::URL,
                'placeholder' => 'https://example.com',
                'show_external' => false,
                'default' => array(
                    'url' => '',
                ),
                'description' => __('URL de la page a embarquer. Laissez vide si vous utilisez Srcdoc.', 'mj-member'),
            )
        );

        $this->add_control(
            'iframe_srcdoc',
            array(
                'label' => __('Srcdoc (HTML inline)', 'mj-member'),
                'type' => Controls_Manager::TEXTAREA,
                'rows' => 6,
                'placeholder' => '<!doctype html><html><body><h1>Demo</h1></body></html>',
                'description' => __('Permet d\'injecter du HTML directement dans l\'iframe.', 'mj-member'),
            )
        );

        $this->add_control(
            'iframe_title',
            array(
                'label' => __('Titre accessible', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Contenu integre', 'mj-member'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'iframe_name',
            array(
                'label' => __('Nom de frame', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'label_block' => true,
                'description' => __('Attribut name de l\'iframe.', 'mj-member'),
            )
        );

        $this->add_control(
            'loading',
            array(
                'label' => __('Loading', 'mj-member'),
                'type' => Controls_Manager::SELECT,
                'default' => 'lazy',
                'options' => array(
                    'lazy' => __('lazy (differe)', 'mj-member'),
                    'eager' => __('eager (immediat)', 'mj-member'),
                    'auto' => __('auto (attribut retire)', 'mj-member'),
                ),
            )
        );

        $this->add_control(
            'fetchpriority',
            array(
                'label' => __('Fetch priority', 'mj-member'),
                'type' => Controls_Manager::SELECT,
                'default' => 'auto',
                'options' => array(
                    'auto' => __('auto', 'mj-member'),
                    'high' => __('high', 'mj-member'),
                    'low' => __('low', 'mj-member'),
                ),
            )
        );

        $this->add_control(
            'referrerpolicy',
            array(
                'label' => __('Referrer policy', 'mj-member'),
                'type' => Controls_Manager::SELECT,
                'default' => 'strict-origin-when-cross-origin',
                'options' => array(
                    '' => __('Aucune', 'mj-member'),
                    'no-referrer' => 'no-referrer',
                    'no-referrer-when-downgrade' => 'no-referrer-when-downgrade',
                    'origin' => 'origin',
                    'origin-when-cross-origin' => 'origin-when-cross-origin',
                    'same-origin' => 'same-origin',
                    'strict-origin' => 'strict-origin',
                    'strict-origin-when-cross-origin' => 'strict-origin-when-cross-origin',
                    'unsafe-url' => 'unsafe-url',
                ),
            )
        );

        $this->add_control(
            'scrolling',
            array(
                'label' => __('Scrolling', 'mj-member'),
                'type' => Controls_Manager::SELECT,
                'default' => 'auto',
                'options' => array(
                    'auto' => __('auto (attribut retire)', 'mj-member'),
                    'yes' => __('yes', 'mj-member'),
                    'no' => __('no', 'mj-member'),
                ),
            )
        );

        $this->add_control(
            'allow_fullscreen',
            array(
                'label' => __('Autoriser plein ecran', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'no',
            )
        );

        $this->add_control(
            'allow_payment_request',
            array(
                'label' => __('Autoriser payment request', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'no',
            )
        );

        $this->add_control(
            'credentialless',
            array(
                'label' => __('Mode credentialless', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'no',
                'description' => __('Attribut experimental: charge l\'iframe sans credentials/cookies.', 'mj-member'),
            )
        );

        $this->add_control(
            'sandbox_tokens',
            array(
                'label' => __('Sandbox', 'mj-member'),
                'type' => Controls_Manager::SELECT2,
                'multiple' => true,
                'label_block' => true,
                'default' => array(),
                'options' => array(
                    'allow-downloads' => 'allow-downloads',
                    'allow-forms' => 'allow-forms',
                    'allow-modals' => 'allow-modals',
                    'allow-orientation-lock' => 'allow-orientation-lock',
                    'allow-pointer-lock' => 'allow-pointer-lock',
                    'allow-popups' => 'allow-popups',
                    'allow-popups-to-escape-sandbox' => 'allow-popups-to-escape-sandbox',
                    'allow-presentation' => 'allow-presentation',
                    'allow-same-origin' => 'allow-same-origin',
                    'allow-scripts' => 'allow-scripts',
                    'allow-storage-access-by-user-activation' => 'allow-storage-access-by-user-activation',
                    'allow-top-navigation' => 'allow-top-navigation',
                    'allow-top-navigation-by-user-activation' => 'allow-top-navigation-by-user-activation',
                ),
                'description' => __('Si vide, aucun attribut sandbox n\'est applique.', 'mj-member'),
            )
        );

        $this->add_control(
            'allow_features',
            array(
                'label' => __('Permissions (attribut allow)', 'mj-member'),
                'type' => Controls_Manager::SELECT2,
                'multiple' => true,
                'label_block' => true,
                'default' => array(),
                'options' => array(
                    'accelerometer' => 'accelerometer',
                    'autoplay' => 'autoplay',
                    'camera' => 'camera',
                    'clipboard-read' => 'clipboard-read',
                    'clipboard-write' => 'clipboard-write',
                    'display-capture' => 'display-capture',
                    'encrypted-media' => 'encrypted-media',
                    'fullscreen' => 'fullscreen',
                    'geolocation' => 'geolocation',
                    'gyroscope' => 'gyroscope',
                    'microphone' => 'microphone',
                    'midi' => 'midi',
                    'payment' => 'payment',
                    'picture-in-picture' => 'picture-in-picture',
                    'publickey-credentials-get' => 'publickey-credentials-get',
                    'screen-wake-lock' => 'screen-wake-lock',
                    'usb' => 'usb',
                    'web-share' => 'web-share',
                    'xr-spatial-tracking' => 'xr-spatial-tracking',
                ),
            )
        );

        $this->add_control(
            'allow_origin',
            array(
                'label' => __('Portee des permissions', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => '*',
                'description' => __('Ex: * ou https://domaine.tld. Vide pour omettre la portee.', 'mj-member'),
                'condition' => array(
                    'allow_features!' => array(),
                ),
            )
        );

        $this->add_control(
            'iframe_csp',
            array(
                'label' => __('CSP iframe', 'mj-member'),
                'type' => Controls_Manager::TEXTAREA,
                'rows' => 3,
                'description' => __('Attribut csp experimental (ex: default-src \'self\'; script-src \'none\';).', 'mj-member'),
            )
        );

        $this->add_control(
            'custom_attributes',
            array(
                'label' => __('Attributs personnalises', 'mj-member'),
                'type' => Controls_Manager::TEXTAREA,
                'rows' => 6,
                'description' => __('Un attribut par ligne: key=value ou key. Les attributs on* sont ignores.', 'mj-member'),
                'placeholder' => "data-theme=light\naria-label=Mon iframe\nallowtransparency",
            )
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_layout',
            array(
                'label' => __('Mise en page', 'mj-member'),
            )
        );

        $this->add_control(
            'size_mode',
            array(
                'label' => __('Mode de taille', 'mj-member'),
                'type' => Controls_Manager::SELECT,
                'default' => 'fixed',
                'options' => array(
                    'fixed' => __('Largeur/hauteur fixes', 'mj-member'),
                    'ratio' => __('Ratio responsive', 'mj-member'),
                    'fill' => __('Remplir la largeur', 'mj-member'),
                ),
            )
        );

        $this->add_responsive_control(
            'iframe_width',
            array(
                'label' => __('Largeur', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px', '%', 'vw'),
                'range' => array(
                    'px' => array('min' => 120, 'max' => 2400),
                    '%' => array('min' => 10, 'max' => 100),
                    'vw' => array('min' => 10, 'max' => 100),
                ),
                'default' => array('size' => 100, 'unit' => '%'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-iframe-widget' => '--mj-iframe-width: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'iframe_max_width',
            array(
                'label' => __('Largeur max', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px', '%', 'vw'),
                'range' => array(
                    'px' => array('min' => 120, 'max' => 2800),
                    '%' => array('min' => 10, 'max' => 100),
                    'vw' => array('min' => 10, 'max' => 100),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-iframe-widget' => '--mj-iframe-max-width: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'iframe_height',
            array(
                'label' => __('Hauteur', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px', 'vh'),
                'range' => array(
                    'px' => array('min' => 120, 'max' => 2200),
                    'vh' => array('min' => 20, 'max' => 100),
                ),
                'default' => array('size' => 620, 'unit' => 'px'),
                'condition' => array(
                    'size_mode' => 'fixed',
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-iframe-widget' => '--mj-iframe-height: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'iframe_min_height',
            array(
                'label' => __('Hauteur minimum', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px', 'vh'),
                'range' => array(
                    'px' => array('min' => 80, 'max' => 1800),
                    'vh' => array('min' => 10, 'max' => 100),
                ),
                'default' => array('size' => 420, 'unit' => 'px'),
                'condition' => array(
                    'size_mode' => 'fill',
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-iframe-widget' => '--mj-iframe-min-height: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'aspect_ratio',
            array(
                'label' => __('Ratio', 'mj-member'),
                'type' => Controls_Manager::SELECT,
                'default' => '16 / 9',
                'condition' => array(
                    'size_mode' => 'ratio',
                ),
                'options' => array(
                    '1 / 1' => '1:1',
                    '4 / 3' => '4:3',
                    '16 / 9' => '16:9',
                    '21 / 9' => '21:9',
                    '9 / 16' => '9:16',
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-iframe-widget' => '--mj-iframe-aspect-ratio: {{VALUE}};',
                ),
            )
        );

        $this->add_responsive_control(
            'iframe_align',
            array(
                'label' => __('Alignement horizontal', 'mj-member'),
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
                'default' => 'center',
            )
        );

        $this->add_control(
            'iframe_align_notice',
            array(
                'type' => Controls_Manager::RAW_HTML,
                'raw' => __('Astuce: l\'alignement est gere avec des marges automatiques. Utilisez la largeur max pour limiter l\'etirement.', 'mj-member'),
                'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
            )
        );

        $this->add_responsive_control(
            'iframe_border_radius',
            array(
                'label' => __('Rayon de bordure', 'mj-member'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => array('px', '%'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-iframe-widget' => '--mj-iframe-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'show_placeholder_when_empty',
            array(
                'label' => __('Afficher une aide si iframe vide', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_container',
            array(
                'label' => __('Style conteneur', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'container_bg',
            array(
                'label' => __('Fond', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-iframe-widget' => '--mj-iframe-bg: {{VALUE}};',
                ),
            )
        );

        if (class_exists('Elementor\\Group_Control_Border')) {
            $this->add_group_control(
                Group_Control_Border::get_type(),
                array(
                    'name' => 'container_border',
                    'selector' => '{{WRAPPER}} .mj-iframe-widget',
                )
            );
        }

        if (class_exists('Elementor\\Group_Control_Box_Shadow')) {
            $this->add_group_control(
                Group_Control_Box_Shadow::get_type(),
                array(
                    'name' => 'container_shadow',
                    'selector' => '{{WRAPPER}} .mj-iframe-widget',
                )
            );
        }

        $this->add_responsive_control(
            'container_padding',
            array(
                'label' => __('Padding', 'mj-member'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => array('px', 'em', '%'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-iframe-widget' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'container_margin',
            array(
                'label' => __('Marge externe', 'mj-member'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => array('px', 'em', '%'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-iframe-widget' => 'margin-top: {{TOP}}{{UNIT}}; margin-right: {{RIGHT}}{{UNIT}}; margin-bottom: {{BOTTOM}}{{UNIT}}; margin-left: {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->end_controls_section();

        $this->register_visibility_controls();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $this->apply_visibility_to_wrapper($settings, 'mj-iframe-widget');

        $src_setting = isset($settings['iframe_src']) && is_array($settings['iframe_src']) ? $settings['iframe_src'] : array();
        $src = isset($src_setting['url']) ? trim((string) $src_setting['url']) : '';
        $srcdoc = isset($settings['iframe_srcdoc']) ? (string) $settings['iframe_srcdoc'] : '';

        $title = isset($settings['iframe_title']) ? sanitize_text_field((string) $settings['iframe_title']) : '';
        if ($title === '') {
            $title = __('Contenu integre', 'mj-member');
        }

        $name = isset($settings['iframe_name']) ? sanitize_text_field((string) $settings['iframe_name']) : '';
        $loading = isset($settings['loading']) ? sanitize_text_field((string) $settings['loading']) : 'lazy';
        $fetchpriority = isset($settings['fetchpriority']) ? sanitize_text_field((string) $settings['fetchpriority']) : 'auto';
        $referrerpolicy = isset($settings['referrerpolicy']) ? sanitize_text_field((string) $settings['referrerpolicy']) : '';
        $scrolling = isset($settings['scrolling']) ? sanitize_text_field((string) $settings['scrolling']) : 'auto';
        $allow_fullscreen = isset($settings['allow_fullscreen']) && $settings['allow_fullscreen'] === 'yes';
        $allow_payment_request = isset($settings['allow_payment_request']) && $settings['allow_payment_request'] === 'yes';
        $credentialless = isset($settings['credentialless']) && $settings['credentialless'] === 'yes';

        $sandbox = self::sanitize_token_list(isset($settings['sandbox_tokens']) ? $settings['sandbox_tokens'] : array());
        $allow_features = self::sanitize_token_list(isset($settings['allow_features']) ? $settings['allow_features'] : array());
        $allow_origin = isset($settings['allow_origin']) ? trim((string) $settings['allow_origin']) : '*';
        $iframe_csp = isset($settings['iframe_csp']) ? trim((string) $settings['iframe_csp']) : '';

        $attributes = array(
            'class' => 'mj-iframe-widget__frame',
            'title' => $title,
        );

        if ($src !== '') {
            $attributes['src'] = esc_url_raw($src);
        }

        if ($srcdoc !== '') {
            $attributes['srcdoc'] = wp_kses_post($srcdoc);
        }

        if ($name !== '') {
            $attributes['name'] = $name;
        }

        if (in_array($loading, array('lazy', 'eager'), true)) {
            $attributes['loading'] = $loading;
        }

        if (in_array($fetchpriority, array('high', 'low'), true)) {
            $attributes['fetchpriority'] = $fetchpriority;
        }

        if ($referrerpolicy !== '') {
            $attributes['referrerpolicy'] = $referrerpolicy;
        }

        if (in_array($scrolling, array('yes', 'no'), true)) {
            $attributes['scrolling'] = $scrolling;
        }

        if (!empty($sandbox)) {
            $attributes['sandbox'] = implode(' ', $sandbox);
        }

        if (!empty($allow_features)) {
            $attributes['allow'] = self::build_allow_value($allow_features, $allow_origin);
        }

        if ($allow_fullscreen) {
            $attributes['allowfullscreen'] = true;
        }

        if ($allow_payment_request) {
            $attributes['allowpaymentrequest'] = true;
        }

        if ($credentialless) {
            $attributes['credentialless'] = true;
        }

        if ($iframe_csp !== '') {
            $attributes['csp'] = $iframe_csp;
        }

        $custom_attributes = isset($settings['custom_attributes']) ? (string) $settings['custom_attributes'] : '';
        $attributes = self::append_custom_attributes($attributes, $custom_attributes);

        $size_mode = isset($settings['size_mode']) ? sanitize_text_field((string) $settings['size_mode']) : 'fixed';
        if (!in_array($size_mode, array('fixed', 'ratio', 'fill'), true)) {
            $size_mode = 'fixed';
        }

        $align = isset($settings['iframe_align']) ? sanitize_text_field((string) $settings['iframe_align']) : 'center';
        if (!in_array($align, array('left', 'center', 'right'), true)) {
            $align = 'center';
        }

        $wrapper_classes = 'mj-iframe-widget mj-iframe-widget--mode-' . $size_mode . ' mj-iframe-widget--align-' . $align;

        $show_placeholder = isset($settings['show_placeholder_when_empty']) ? $settings['show_placeholder_when_empty'] === 'yes' : true;
        $has_frame_source = isset($attributes['src']) || isset($attributes['srcdoc']);
        $is_preview = function_exists('is_elementor_preview') && is_elementor_preview();

        $template_data = array(
            'wrapper_classes' => $wrapper_classes,
            'attributes' => $attributes,
            'show_placeholder' => $show_placeholder,
            'has_frame_source' => $has_frame_source,
            'placeholder_text' => __('Configurez une URL iframe ou un Srcdoc dans le panneau Elementor.', 'mj-member'),
            'is_preview' => $is_preview,
        );

        $template = Config::path() . 'includes/templates/elementor/iframe.php';
        if (is_readable($template)) {
            $widget = $this;
            include $template;
        }
    }

    private static function sanitize_token_list($tokens) {
        if (!is_array($tokens)) {
            return array();
        }

        $clean = array();
        foreach ($tokens as $token) {
            $value = strtolower(trim((string) $token));
            $value = preg_replace('/[^a-z0-9\-]/', '', $value);
            if ($value === '') {
                continue;
            }

            $clean[$value] = $value;
        }

        return array_values($clean);
    }

    private static function build_allow_value(array $features, $origin) {
        $origin = trim((string) $origin);
        $rules = array();

        foreach ($features as $feature) {
            $part = $feature;
            if ($origin !== '') {
                $part .= ' ' . $origin;
            }

            $rules[] = $part;
        }

        return implode('; ', $rules);
    }

    private static function append_custom_attributes(array $attributes, $raw) {
        $raw = (string) $raw;
        if ($raw === '') {
            return $attributes;
        }

        $reserved = array(
            'src',
            'srcdoc',
            'title',
            'class',
            'style',
            'sandbox',
            'allow',
            'loading',
            'fetchpriority',
            'referrerpolicy',
            'name',
        );

        $lines = preg_split('/\r\n|\r|\n/', $raw);
        if (!is_array($lines)) {
            return $attributes;
        }

        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }

            $parts = explode('=', $line, 2);
            $key = strtolower(trim((string) $parts[0]));
            $key = preg_replace('/[^a-z0-9_:\-]/', '', $key);

            if ($key === '' || strpos($key, 'on') === 0 || in_array($key, $reserved, true)) {
                continue;
            }

            if (count($parts) === 1) {
                $attributes[$key] = true;
                continue;
            }

            $value = trim((string) $parts[1]);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            if ($value === '') {
                $attributes[$key] = true;
            } else {
                $attributes[$key] = $value;
            }
        }

        return $attributes;
    }
}
