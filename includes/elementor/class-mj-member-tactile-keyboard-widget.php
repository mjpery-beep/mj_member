<?php

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

if (!defined('ABSPATH')) {
    exit;
}

class Mj_Member_Elementor_Tactile_Keyboard_Widget extends Widget_Base {
    use Mj_Member_Elementor_Widget_Visibility;

    public function get_name() {
        return 'mj-member-tactile-keyboard';
    }

    public function get_title() {
        return __('MJ - Tactile keyboard', 'mj-member');
    }

    public function get_icon() {
        return 'eicon-editor-list-ul';
    }

    public function get_categories() {
        return array('mj-member');
    }

    public function get_keywords() {
        return array('mj', 'clavier', 'keyboard', 'tactile', 'emoji', 'numpad');
    }

    public function get_style_depends() {
        return array('mj-member-components', 'mj-member-tactile-keyboard');
    }

    public function get_script_depends() {
        return array('mj-member-tactile-keyboard');
    }

    protected function register_controls() {
        $this->start_controls_section(
            'section_content',
            array(
                'label' => __('Configuration du clavier', 'mj-member'),
            )
        );

        $this->add_control(
            'keyboard_layout',
            array(
                'label' => __('Type de clavier', 'mj-member'),
                'type' => Controls_Manager::SELECT,
                'default' => 'belgium',
                'options' => array(
                    'belgium' => __('Belgique (AZERTY)', 'mj-member'),
                    'france' => __('France (AZERTY)', 'mj-member'),
                    'us' => __('US (QWERTY)', 'mj-member'),
                ),
            )
        );

        $this->add_control(
            'keyboard_mode',
            array(
                'label' => __('Mode du widget', 'mj-member'),
                'type' => Controls_Manager::SELECT,
                'default' => 'keyboard',
                'options' => array(
                    'keyboard' => __('Clavier seul', 'mj-member'),
                    'toggle' => __('Clavier + selecteur d\'emoji', 'mj-member'),
                    'emoji' => __('Selecteur d\'emoji seul', 'mj-member'),
                ),
            )
        );

        $this->add_control(
            'show_numeric_row',
            array(
                'label' => __('Afficher la ligne numerique', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'no',
            )
        );

        $this->add_control(
            'show_numpad',
            array(
                'label' => __('Afficher le pave numerique', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'no',
            )
        );

        $this->add_control(
            'show_function_keys',
            array(
                'label' => __('Afficher F1 et F2', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'no',
            )
        );

        $this->add_control(
            'show_arrows',
            array(
                'label' => __('Afficher les fleches', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'no',
            )
        );

        $this->add_control(
            'show_demo_input',
            array(
                'label' => __('Afficher une zone de saisie integree', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
                'description' => __('Le clavier ecrit sinon dans le dernier champ texte actif de la page.', 'mj-member'),
            )
        );

        $this->add_control(
            'input_label',
            array(
                'label' => __('Label de la zone de saisie', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Zone de saisie', 'mj-member'),
                'label_block' => true,
                'condition' => array(
                    'show_demo_input' => 'yes',
                ),
            )
        );

        $this->add_control(
            'input_placeholder',
            array(
                'label' => __('Placeholder', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Touchez les touches pour ecrire ici.', 'mj-member'),
                'label_block' => true,
                'condition' => array(
                    'show_demo_input' => 'yes',
                ),
            )
        );

        $this->end_controls_section();

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
                'label' => __('Fond du panneau', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-tactile-keyboard' => '--mj-tactile-bg: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'accent_color',
            array(
                'label' => __('Couleur accent', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-tactile-keyboard' => '--mj-tactile-accent: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'text_color',
            array(
                'label' => __('Couleur du texte', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-tactile-keyboard' => '--mj-tactile-text: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'key_radius',
            array(
                'label' => __('Rayon des touches', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array(
                    'px' => array(
                        'min' => 8,
                        'max' => 32,
                    ),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-tactile-keyboard' => '--mj-tactile-radius: {{SIZE}}px;',
                ),
            )
        );

        $this->add_control(
            'key_gap',
            array(
                'label' => __('Espace entre les touches', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array(
                    'px' => array(
                        'min' => 4,
                        'max' => 20,
                    ),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-tactile-keyboard' => '--mj-tactile-gap: {{SIZE}}px;',
                ),
            )
        );

        $this->end_controls_section();

        $this->register_visibility_controls();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $this->apply_visibility_to_wrapper($settings, 'mj-tactile-keyboard');

        $layout = isset($settings['keyboard_layout']) ? sanitize_key((string) $settings['keyboard_layout']) : 'belgium';
        if (!isset(self::get_layout_labels()[$layout])) {
            $layout = 'belgium';
        }

        $mode = isset($settings['keyboard_mode']) ? sanitize_key((string) $settings['keyboard_mode']) : 'keyboard';
        if (!in_array($mode, array('keyboard', 'toggle', 'emoji'), true)) {
            $mode = 'keyboard';
        }

        $template_data = array(
            'wrapper_classes' => 'mj-tactile-keyboard mj-tactile-keyboard--mode-' . $mode,
            'config_json' => wp_json_encode(array(
                'layout' => $layout,
                'mode' => $mode,
                'showNumericRow' => self::is_enabled($settings, 'show_numeric_row'),
                'showNumpad' => self::is_enabled($settings, 'show_numpad'),
                'showFunctionKeys' => self::is_enabled($settings, 'show_function_keys'),
                'showArrows' => self::is_enabled($settings, 'show_arrows'),
                'showDemoInput' => self::is_enabled($settings, 'show_demo_input'),
            )),
            'show_demo_input' => self::is_enabled($settings, 'show_demo_input'),
            'input_label' => isset($settings['input_label']) ? sanitize_text_field((string) $settings['input_label']) : __('Zone de saisie', 'mj-member'),
            'input_placeholder' => isset($settings['input_placeholder']) ? sanitize_text_field((string) $settings['input_placeholder']) : __('Touchez les touches pour ecrire ici.', 'mj-member'),
            'layout_label' => self::get_layout_labels()[$layout],
            'mode_label' => self::get_mode_label($mode),
            'is_preview' => function_exists('is_elementor_preview') && is_elementor_preview(),
        );

        $template = Mj\Member\Core\Config::path() . 'includes/templates/elementor/tactile_keyboard.php';
        if (is_readable($template)) {
            $widget = $this;
            include $template;
        }
    }

    private static function is_enabled(array $settings, $key) {
        return isset($settings[$key]) && $settings[$key] === 'yes';
    }

    private static function get_layout_labels() {
        return array(
            'belgium' => __('Belgique', 'mj-member'),
            'france' => __('France', 'mj-member'),
            'us' => __('US', 'mj-member'),
        );
    }

    private static function get_mode_label($mode) {
        switch ($mode) {
            case 'emoji':
                return __('Emojis', 'mj-member');
            case 'toggle':
                return __('Clavier + emojis', 'mj-member');
            default:
                return __('Clavier', 'mj-member');
        }
    }
}