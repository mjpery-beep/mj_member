<?php

use Elementor\Controls_Manager;
use Elementor\Widget_Base;
use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

class Mj_Member_Elementor_Documents_Widget extends Widget_Base
{
    use Mj_Member_Elementor_Widget_Visibility;

    public function get_name()
    {
        return 'mj-member-documents';
    }

    public function get_title()
    {
        return __('MJ – Documents', 'mj-member');
    }

    public function get_icon()
    {
        return 'eicon-folder';
    }

    public function get_categories()
    {
        return array('mj-member');
    }

    public function get_keywords()
    {
        return array('documents', 'drive', 'fichiers', 'dossier', 'google', 'animateur');
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
                'default' => __('Documents partagés', 'mj-member'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'intro_text',
            array(
                'label' => __('Texte introductif', 'mj-member'),
                'type' => Controls_Manager::TEXTAREA,
                'rows' => 3,
                'placeholder' => __('Expliquez l’utilisation du gestionnaire de documents.', 'mj-member'),
            )
        );

        $this->add_control(
            'default_folder_id',
            array(
                'label' => __('ID du dossier par défaut', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'placeholder' => __('Laisser vide pour utiliser le dossier racine configuré', 'mj-member'),
                'label_block' => true,
            )
        );

        $this->end_controls_section();

        $this->register_visibility_controls();
    }

    protected function render()
    {
        $settings = $this->get_settings_for_display();
        $this->apply_visibility_to_wrapper($settings, 'mj-documents-widget');

        $title = isset($settings['title']) ? sanitize_text_field((string) $settings['title']) : '';
        $intro = isset($settings['intro_text']) ? (string) $settings['intro_text'] : '';
        $defaultFolderId = isset($settings['default_folder_id']) ? sanitize_text_field((string) $settings['default_folder_id']) : '';

        $template = Config::path() . 'includes/templates/elementor/documents_manager.php';
        if (is_readable($template)) {
            $widget = $this;
            include $template;
        }
    }
}
