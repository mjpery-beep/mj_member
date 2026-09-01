<?php

use Elementor\Controls_Manager;
use Elementor\Widget_Base;
use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

class Mj_Member_Elementor_Teams_Documents_Widget extends Widget_Base
{
    use Mj_Member_Elementor_Widget_Visibility;

    public function get_name()
    {
        return 'mj-member-teams-documents';
    }

    public function get_title()
    {
        return __('MJ – Documents Teams', 'mj-member');
    }

    public function get_icon()
    {
        return 'eicon-cloud-upload';
    }

    public function get_categories()
    {
        return array('general');
    }

    public function get_keywords()
    {
        return array('documents', 'teams', 'sharepoint', 'fichiers', 'drive');
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
                'default' => __('Documents Teams', 'mj-member'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'intro_text',
            array(
                'label' => __('Texte introductif', 'mj-member'),
                'type' => Controls_Manager::TEXTAREA,
                'rows' => 3,
                'placeholder' => __('Expliquez le fonctionnement de l’espace documents Teams.', 'mj-member'),
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

        $this->add_control(
            'allow_upload',
            array(
                'label' => __('Autoriser le téléversement', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->add_control(
            'allow_create_folder',
            array(
                'label' => __('Autoriser la création de dossier', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->add_control(
            'allow_rename',
            array(
                'label' => __('Autoriser le renommage', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->add_control(
            'allow_delete',
            array(
                'label' => __('Autoriser la suppression', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
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
        $allowUpload = isset($settings['allow_upload']) ? $settings['allow_upload'] === 'yes' : true;
        $allowCreateFolder = isset($settings['allow_create_folder']) ? $settings['allow_create_folder'] === 'yes' : true;
        $allowRename = isset($settings['allow_rename']) ? $settings['allow_rename'] === 'yes' : true;
        $allowDelete = isset($settings['allow_delete']) ? $settings['allow_delete'] === 'yes' : true;

        $template = Config::path() . 'includes/templates/elementor/teams_documents.php';
        if (is_readable($template)) {
            $widget = $this;
            include $template;
        }
    }
}
