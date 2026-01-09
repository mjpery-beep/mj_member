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

class Mj_Member_Elementor_Grim_Gif_Widget extends Widget_Base {
    use Mj_Member_Elementor_Widget_Visibility;

    public function get_name() {
        return 'mj-member-grim-gif';
    }

    public function get_title() {
        return __('Grimlins aléatoire', 'mj-member');
    }

    public function get_icon() {
        return 'eicon-animation-text';
    }

    public function get_categories() {
        return array('mj-member');
    }

    public function get_keywords() {
        return array('mj', 'grimlins', 'gif', 'random', 'illustration');
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
                'default' => __('Un Grimlins sauvage apparaît', 'mj-member'),
                'placeholder' => __('Titre du widget', 'mj-member'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'subtitle',
            array(
                'label' => __('Accroche', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => __('À chaque chargement, découvre un nouveau gif du repaire Grimlins.', 'mj-member'),
                'placeholder' => __('Texte optionnel sous le titre', 'mj-member'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'messages',
            array(
                'label' => __('Messages aléatoires', 'mj-member'),
                'type' => Controls_Manager::TEXTAREA,
                'placeholder' => __('Entrez une phrase par ligne', 'mj-member'),
                'rows' => 5,
            )
        );

        $this->add_control(
            'show_filename',
            array(
                'label' => __('Afficher le nom du fichier', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'no',
            )
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_container',
            array(
                'label' => __('Conteneur', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_responsive_control(
            'container_padding',
            array(
                'label' => __('Marge interne', 'mj-member'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => array('px', '%', 'em'),
                'default' => array(
                    'top' => '24',
                    'right' => '24',
                    'bottom' => '24',
                    'left' => '24',
                    'unit' => 'px',
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-grim-gif' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'container_border_radius',
            array(
                'label' => __('Rayon des angles', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px', '%'),
                'range' => array(
                    'px' => array('min' => 0, 'max' => 200),
                    '%' => array('min' => 0, 'max' => 100),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-grim-gif' => 'border-radius: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Background::get_type(),
            array(
                'name' => 'container_background',
                'label' => __('Arrière-plan', 'mj-member'),
                'types' => array('classic', 'gradient'),
                'selector' => '{{WRAPPER}} .mj-grim-gif',
            )
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            array(
                'name' => 'container_border',
                'selector' => '{{WRAPPER}} .mj-grim-gif',
            )
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            array(
                'name' => 'container_shadow',
                'selector' => '{{WRAPPER}} .mj-grim-gif',
            )
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_image',
            array(
                'label' => __('GIF', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_responsive_control(
            'image_width',
            array(
                'label' => __('Largeur', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px', '%', 'vw'),
                'range' => array(
                    'px' => array('min' => 50, 'max' => 800),
                    '%' => array('min' => 10, 'max' => 100),
                    'vw' => array('min' => 10, 'max' => 100),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-grim-gif__image' => 'width: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'image_border_radius',
            array(
                'label' => __('Rayon des angles', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px', '%'),
                'range' => array(
                    'px' => array('min' => 0, 'max' => 200),
                    '%' => array('min' => 0, 'max' => 100),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-grim-gif__image' => 'border-radius: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            array(
                'name' => 'image_shadow',
                'selector' => '{{WRAPPER}} .mj-grim-gif__image',
            )
        );

        $this->end_controls_section();

        $this->register_visibility_controls();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $this->apply_visibility_to_wrapper($settings, 'mj-grim-gif');

        $gif = $this->get_random_gif();
        if ($gif === null) {
            echo '<div class="mj-member-account-warning">' . esc_html__('Aucun GIF Grimlins disponible pour le moment.', 'mj-member') . '</div>';
            return;
        }

        $template_path = trailingslashit(Config::path()) . 'includes/templates/elementor/grim_gif.php';
        if (!file_exists($template_path)) {
            echo '<div class="mj-member-account-warning">' . esc_html__('Template du widget Grimlins introuvable.', 'mj-member') . '</div>';
            return;
        }

        $is_preview = $this->is_elementor_preview_mode();

        $template_data = array(
            'title' => isset($settings['title']) ? (string) $settings['title'] : '',
            'subtitle' => isset($settings['subtitle']) ? (string) $settings['subtitle'] : '',
            'gif_url' => $gif['url'],
            'gif_name' => $gif['name'],
            'show_filename' => !empty($settings['show_filename']) && $settings['show_filename'] === 'yes',
            'is_preview' => $is_preview,
            'message' => $this->pick_random_message($settings),
        );

        /**
         * Permet d'ajuster les données transmises au template Grim Gif.
         *
         * @param array<string,mixed> $template_data
         * @param array<string,mixed> $settings
         * @param Mj_Member_Elementor_Grim_Gif_Widget $widget
         */
        $template_data = apply_filters('mj_member_grim_gif_template_data', $template_data, $settings, $this);
        $template_data = is_array($template_data) ? $template_data : array();

        include $template_path;
    }

    /**
     * @return array<string,string>|null
     */
    private function get_random_gif(): ?array
    {
        $directory = trailingslashit(Config::path()) . 'grim-gif';
        if (!is_dir($directory)) {
            return null;
        }

        $allowedExtensions = array('gif', 'webp');
        $files = array();
        try {
            foreach (new DirectoryIterator($directory) as $fileInfo) {
                if (!$fileInfo->isFile()) {
                    continue;
                }

                $extension = strtolower($fileInfo->getExtension());
                if (!in_array($extension, $allowedExtensions, true)) {
                    continue;
                }

                $files[] = $fileInfo->getFilename();
            }
        } catch (Exception $exception) {
            return null;
        }

        if (empty($files)) {
            return null;
        }

        $selected = $files[array_rand($files)];
        $fileName = pathinfo($selected, PATHINFO_FILENAME);
        $readableName = trim(preg_replace('/[-_]+/', ' ', $fileName));
        if ($readableName === '') {
            $readableName = __('Grimlins', 'mj-member');
        }

        $url = trailingslashit(Config::url()) . 'grim-gif/' . rawurlencode($selected);

        /**
         * Permet d'ajuster l'URL finale du GIF Grimlins.
         *
         * @param string $url
         * @param string $fileName
         */
        $url = apply_filters('mj_member_grim_gif_url', $url, $selected);

        return array(
            'url' => (string) $url,
            'name' => $readableName,
        );
    }

    private function is_elementor_preview_mode(): bool
    {
        if (did_action('elementor/loaded')) {
            $elementor = \Elementor\Plugin::$instance ?? null;
            if ($elementor && isset($elementor->editor) && method_exists($elementor->editor, 'is_edit_mode')) {
                return (bool) $elementor->editor->is_edit_mode();
            }
        }

        return false;
    }

    /**
     * @param array<string,mixed> $settings
     */
    private function pick_random_message(array $settings): string
    {
        if (empty($settings['messages']) || !is_string($settings['messages'])) {
            return '';
        }

        $lines = preg_split('/\r?\n/', $settings['messages']);
        if (!is_array($lines) || empty($lines)) {
            return '';
        }

        $candidates = array();
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed !== '') {
                $candidates[] = $trimmed;
            }
        }

        if (empty($candidates)) {
            return '';
        }

        return (string) $candidates[array_rand($candidates)];
    }
}
