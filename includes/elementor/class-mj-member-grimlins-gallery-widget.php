<?php

if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Controls_Manager;
use Elementor\Widget_Base;
use Mj\Member\Core\Config;

class Mj_Member_Elementor_Grimlins_Gallery_Widget extends Widget_Base {
    use Mj_Member_Elementor_Widget_Visibility;

    public function get_name() {
        return 'mj-member-grimlins-gallery';
    }

    public function get_title() {
        return __('Galerie Grimlins MJ', 'mj-member');
    }

    public function get_icon() {
        return 'eicon-gallery-group';
    }

    public function get_categories() {
        return array('general');
    }

    public function get_keywords() {
        return array('grimlins', 'galerie', 'avant', 'apres', 'mj');
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
                'default' => __('Galerie Grimlins', 'mj-member'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'description',
            array(
                'label' => __('Description', 'mj-member'),
                'type' => Controls_Manager::TEXTAREA,
                'rows' => 3,
                'default' => __('Retrouve chaque transformation avec la photo originale et le rendu Grimlins.', 'mj-member'),
            )
        );

        $this->add_control(
            'limit',
            array(
                'label' => __('Nombre maximum d’entrées', 'mj-member'),
                'type' => Controls_Manager::NUMBER,
                'min' => 0,
                'max' => 30,
                'default' => 10,
            )
        );

        $this->add_control(
            'order',
            array(
                'label' => __('Ordre d’affichage', 'mj-member'),
                'type' => Controls_Manager::SELECT,
                'default' => 'desc',
                'options' => array(
                    'desc' => __('Plus récentes en premier', 'mj-member'),
                    'asc' => __('Plus anciennes en premier', 'mj-member'),
                ),
            )
        );

        $this->add_control(
            'empty_message',
            array(
                'label' => __('Message lorsqu’aucune transformation n’est trouvée', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Aucune transformation Grimlins disponible pour le moment.', 'mj-member'),
                'label_block' => true,
            )
        );

        $this->end_controls_section();

        $this->register_visibility_controls();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $this->apply_visibility_to_wrapper($settings, 'mj-grimlins-gallery');

        $template_path = trailingslashit(Config::path()) . 'includes/templates/elementor/grimlins_gallery.php';
        if (!file_exists($template_path)) {
            echo '<div class="mj-member-account-warning">' . esc_html__('Le template de la galerie Grimlins est introuvable.', 'mj-member') . '</div>';
            return;
        }

        $is_preview = $this->is_elementor_preview_mode();
        $limit = isset($settings['limit']) ? max(0, (int) $settings['limit']) : 0;
        $order = isset($settings['order']) ? (string) $settings['order'] : 'desc';

        $template_data = array(
            'title' => isset($settings['title']) ? (string) $settings['title'] : '',
            'description' => isset($settings['description']) ? (string) $settings['description'] : '',
            'limit' => $limit,
            'order' => $order,
            'empty_message' => isset($settings['empty_message']) ? (string) $settings['empty_message'] : '',
            'is_preview' => $is_preview,
        );

        /**
         * @param array<string,mixed> $template_data
         * @param array<string,mixed> $settings
         * @param Mj_Member_Elementor_Grimlins_Gallery_Widget $widget
         */
        $template_data = apply_filters('mj_member_grimlins_gallery_template_data', $template_data, $settings, $this);

        include $template_path;
    }

    private function is_elementor_preview_mode(): bool {
        if (did_action('elementor/loaded')) {
            $elementor = \Elementor\Plugin::$instance;
            if ($elementor && isset($elementor->editor) && method_exists($elementor->editor, 'is_edit_mode')) {
                return (bool) $elementor->editor->is_edit_mode();
            }
        }

        return false;
    }
}
