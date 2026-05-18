<?php

use Elementor\Controls_Manager;
use Elementor\Widget_Base;
use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

class Mj_Member_Elementor_Player_Widget extends Widget_Base
{
    use Mj_Member_Elementor_Widget_Visibility;

    public function get_name()
    {
        return 'mj-member-player';
    }

    public function get_title()
    {
        return __('MJ - Player YouTube', 'mj-member');
    }

    public function get_icon()
    {
        return 'eicon-play';
    }

    public function get_categories()
    {
        return array('mj-member');
    }

    public function get_keywords()
    {
        return array('mj', 'player', 'youtube', 'music', 'audio', 'playlist');
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
                'default' => __('Player YouTube', 'mj-member'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'intro_text',
            array(
                'label' => __('Texte introductif', 'mj-member'),
                'type' => Controls_Manager::TEXTAREA,
                'rows' => 3,
                'placeholder' => __('Cherche un morceau sur YouTube et écoute-le en audio.', 'mj-member'),
            )
        );

        $this->add_control(
            'youtube_api_key',
            array(
                'label' => __('Clé API YouTube Data v3', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'label_block' => true,
                'description' => __('Clé exposée côté navigateur: utilisez une clé restreinte à votre domaine.', 'mj-member'),
            )
        );

        $this->add_control(
            'default_query',
            array(
                'label' => __('Recherche par défaut', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => __('lofi hip hop', 'mj-member'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'max_results',
            array(
                'label' => __('Nombre de résultats', 'mj-member'),
                'type' => Controls_Manager::NUMBER,
                'min' => 3,
                'max' => 25,
                'default' => 8,
            )
        );

        $this->add_control(
            'autoplay',
            array(
                'label' => __('Lecture automatique', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => '',
            )
        );

        $this->add_control(
            'playlists_raw',
            array(
                'label' => __('Playlists prédéfinies (une par ligne)', 'mj-member'),
                'type' => Controls_Manager::TEXTAREA,
                'rows' => 6,
                'placeholder' => "Lo-fi Focus|PLxxxxxxxxxxxx\nHits FR|PLyyyyyyyyyyyy",
                'description' => __('Format: Nom|PlaylistID YouTube', 'mj-member'),
            )
        );

        $this->end_controls_section();

        $this->register_visibility_controls();
    }

    protected function render()
    {
        $settings = $this->get_settings_for_display();
        $this->apply_visibility_to_wrapper($settings, 'mj-player-widget');

        $title = isset($settings['title']) ? sanitize_text_field((string) $settings['title']) : '';
        $intro = isset($settings['intro_text']) ? wp_kses_post($settings['intro_text']) : '';
        $apiKey = isset($settings['youtube_api_key']) ? sanitize_text_field((string) $settings['youtube_api_key']) : '';
        $defaultQuery = isset($settings['default_query']) ? sanitize_text_field((string) $settings['default_query']) : '';

        $maxResults = isset($settings['max_results']) ? (int) $settings['max_results'] : 8;
        if ($maxResults < 3) {
            $maxResults = 3;
        } elseif ($maxResults > 25) {
            $maxResults = 25;
        }

        $autoplay = isset($settings['autoplay']) && $settings['autoplay'] === 'yes';

        $playlists = array();
        $playlistsRaw = isset($settings['playlists_raw']) ? (string) $settings['playlists_raw'] : '';
        if ($playlistsRaw !== '') {
            $lines = preg_split('/\r\n|\r|\n/', $playlistsRaw);
            if (is_array($lines)) {
                foreach ($lines as $line) {
                    $line = trim((string) $line);
                    if ($line === '') {
                        continue;
                    }

                    $parts = explode('|', $line, 2);
                    $name = isset($parts[0]) ? sanitize_text_field(trim((string) $parts[0])) : '';
                    $playlistId = isset($parts[1]) ? preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $parts[1]) : '';

                    if ($name === '' || $playlistId === '') {
                        continue;
                    }

                    $playlists[] = array(
                        'name' => $name,
                        'playlistId' => $playlistId,
                    );
                }
            }
        }

        $isPreview = function_exists('is_elementor_preview') && is_elementor_preview();
        if ($isPreview && empty($playlists)) {
            $playlists = array(
                array(
                    'name' => __('Focus Lo-fi', 'mj-member'),
                    'playlistId' => 'PLWz5rJ2EKKc9CBxr3BVjPTPoDPLdPIFCE',
                ),
                array(
                    'name' => __('Top Pop', 'mj-member'),
                    'playlistId' => 'PL4fGSI1pDJn7bF4v8VxW4xv3fV8N0Vx2Y',
                ),
            );
        }

        $template_data = array(
            'title' => $title,
            'intro' => $intro,
            'config' => array(
                'apiKey' => $apiKey,
                'defaultQuery' => $defaultQuery,
                'maxResults' => $maxResults,
                'autoplay' => $autoplay,
                'playlists' => $playlists,
                'preview' => $isPreview,
            ),
        );

        $template = Config::path() . 'includes/templates/elementor/player.php';
        if (is_readable($template)) {
            $widget = $this;
            include $template;
        }
    }
}
