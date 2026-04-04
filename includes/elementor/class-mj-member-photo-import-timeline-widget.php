<?php

use Elementor\Controls_Manager;
use Elementor\Widget_Base;
use Mj\Member\Classes\MjNextcloudPhotoImporter;
use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

class Mj_Member_Elementor_Photo_Import_Timeline_Widget extends Widget_Base
{
    use Mj_Member_Elementor_Widget_Visibility;

    public function get_name()
    {
        return 'mj-member-photo-import-timeline';
    }

    public function get_title()
    {
        return __('Timeline photos Nextcloud', 'mj-member');
    }

    public function get_icon()
    {
        return 'eicon-gallery-grid';
    }

    public function get_categories()
    {
        return array('mj-member');
    }

    public function get_keywords()
    {
        return array('photo', 'timeline', 'nextcloud', 'galerie', 'mj');
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
            'title',
            array(
                'label' => __('Titre', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Souvenirs en photos', 'mj-member'),
            )
        );

        $this->add_control(
            'subtitle',
            array(
                'label' => __('Sous-titre', 'mj-member'),
                'type' => Controls_Manager::TEXTAREA,
                'rows' => 3,
                'default' => __('Chronologie des photos importées depuis Nextcloud.', 'mj-member'),
            )
        );

        $this->add_control(
            'limit',
            array(
                'label' => __('Nombre max de photos', 'mj-member'),
                'type' => Controls_Manager::NUMBER,
                'min' => 1,
                'max' => 500,
                'default' => 120,
            )
        );

        $this->add_control(
            'empty_message',
            array(
                'label' => __('Message vide', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Aucune photo importée pour le moment.', 'mj-member'),
            )
        );

        $this->end_controls_section();

        $this->register_visibility_controls();
    }

    protected function render()
    {
        $settings = $this->get_settings_for_display();
        $template_path = trailingslashit(Config::path()) . 'includes/templates/elementor/photo_import_timeline.php';

        if (!file_exists($template_path)) {
            echo '<div class="mj-member-account-warning">' . esc_html__('Le template de timeline photo est introuvable.', 'mj-member') . '</div>';
            return;
        }

        $limit = isset($settings['limit']) ? max(1, (int) $settings['limit']) : 120;
        $is_preview = $this->is_elementor_preview_mode();

        $items = $is_preview
            ? MjNextcloudPhotoImporter::getPreviewTimelineItems()
            : MjNextcloudPhotoImporter::getTimelineItems($limit, 'desc');

        $template_data = array(
            'title' => isset($settings['title']) ? (string) $settings['title'] : '',
            'subtitle' => isset($settings['subtitle']) ? (string) $settings['subtitle'] : '',
            'empty_message' => isset($settings['empty_message']) ? (string) $settings['empty_message'] : '',
            'items' => $items,
            'is_preview' => $is_preview,
        );

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
