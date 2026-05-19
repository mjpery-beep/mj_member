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

    public function get_style_depends()
    {
        return array('mj-member-player-widget');
    }

    public function get_script_depends()
    {
        return array('mj-member-player-widget');
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
            'header_title_text',
            array(
                'label' => __('Titre header', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => __('♪ JUKEBOX', 'mj-member'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'header_sub_text',
            array(
                'label' => __('Sous-titre header', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Selection musicale', 'mj-member'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'screen_label_text',
            array(
                'label' => __('Label écran', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => __('♪ EN LECTURE', 'mj-member'),
                'label_block' => true,
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

        $this->add_control(
            'layout_heading',
            array(
                'label' => __('Mise en page', 'mj-member'),
                'type' => Controls_Manager::HEADING,
                'separator' => 'before',
            )
        );

        $this->add_responsive_control(
            'widget_width',
            array(
                'label' => __('Largeur du widget', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px', '%', 'vw'),
                'range' => array(
                    'px' => array(
                        'min' => 280,
                        'max' => 1200,
                        'step' => 1,
                    ),
                    '%' => array(
                        'min' => 20,
                        'max' => 100,
                        'step' => 1,
                    ),
                    'vw' => array(
                        'min' => 20,
                        'max' => 100,
                        'step' => 1,
                    ),
                ),
                'default' => array(
                    'unit' => 'px',
                    'size' => 500,
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-player-widget' => 'width: min(100%, {{SIZE}}{{UNIT}}); max-width: {{SIZE}}{{UNIT}}; --mj-player-width: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'widget_height',
            array(
                'label' => __('Hauteur du widget', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px', 'vh'),
                'range' => array(
                    'px' => array(
                        'min' => 360,
                        'max' => 1400,
                        'step' => 1,
                    ),
                    'vh' => array(
                        'min' => 40,
                        'max' => 100,
                        'step' => 1,
                    ),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-player-widget .jb-machine' => 'height: {{SIZE}}{{UNIT}};',
                    '{{WRAPPER}} .mj-player-widget' => '--mj-player-height: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_theme',
            array(
                'label' => __('Apparence', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'appearance_preset',
            array(
                'label' => __('Preset visuel', 'mj-member'),
                'type' => Controls_Manager::SELECT,
                'default' => 'retro',
                'options' => array(
                    'retro' => __('Retro Jukebox', 'mj-member'),
                    'dark' => __('Dark Studio', 'mj-member'),
                    'cream' => __('Cream Vinyl', 'mj-member'),
                    'neon' => __('Neon Arcade', 'mj-member'),
                ),
                'prefix_class' => 'mj-player-theme--',
            )
        );

        $this->add_control(
            'show_header',
            array(
                'label' => __('Afficher le header', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'default' => 'yes',
                'selectors_dictionary' => array(
                    'yes' => 'flex',
                    '' => 'none',
                ),
                'selectors' => array(
                    '{{WRAPPER}} .jb-header' => 'display: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'machine_bg_start',
            array(
                'label' => __('Fond machine haut', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-player-widget' => '--jb-machine-bg-start: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'machine_bg_mid',
            array(
                'label' => __('Fond machine milieu', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-player-widget' => '--jb-machine-bg-mid: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'machine_bg_end',
            array(
                'label' => __('Fond machine bas', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-player-widget' => '--jb-machine-bg-end: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'color_text',
            array(
                'label' => __('Couleur texte principal', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-player-widget' => '--jb-text: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'color_muted',
            array(
                'label' => __('Couleur texte secondaire', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-player-widget' => '--jb-muted: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'color_chrome',
            array(
                'label' => __('Couleur chrome', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-player-widget' => '--jb-chrome: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'color_accent',
            array(
                'label' => __('Couleur accent (or)', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-player-widget' => '--jb-gold: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'color_accent_light',
            array(
                'label' => __('Couleur accent claire', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-player-widget' => '--jb-gold-l: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'color_playing',
            array(
                'label' => __('Couleur lecture active', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-player-widget' => '--jb-amber: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_header',
            array(
                'label' => __('Header', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'header_bg_start',
            array(
                'label' => __('Fond header haut', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-player-widget' => '--jb-header-bg-start: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'header_bg_end',
            array(
                'label' => __('Fond header bas', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-player-widget' => '--jb-header-bg-end: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'header_title_color',
            array(
                'label' => __('Couleur titre header', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .jb-header__logo' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'header_sub_color',
            array(
                'label' => __('Couleur sous-titre header', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .jb-header__sub' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_responsive_control(
            'header_padding',
            array(
                'label' => __('Padding header', 'mj-member'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => array('px', 'em', 'rem'),
                'selectors' => array(
                    '{{WRAPPER}} .jb-header' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_title',
            array(
                'label' => __('Titre du widget', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'widget_title_color',
            array(
                'label' => __('Couleur', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-player-widget__title' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_responsive_control(
            'widget_title_size',
            array(
                'label' => __('Taille', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px', 'rem'),
                'range' => array(
                    'px' => array('min' => 12, 'max' => 72),
                    'rem' => array('min' => 0.8, 'max' => 4, 'step' => 0.05),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-player-widget__title' => 'font-size: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_thumbs',
            array(
                'label' => __('Vignettes', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_responsive_control(
            'thumb_width',
            array(
                'label' => __('Largeur vignette', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array(
                    'px' => array('min' => 28, 'max' => 180),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-player-widget' => '--jb-thumb-w: {{SIZE}}px;',
                ),
            )
        );

        $this->add_responsive_control(
            'thumb_height',
            array(
                'label' => __('Hauteur vignette', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array(
                    'px' => array('min' => 22, 'max' => 140),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-player-widget' => '--jb-thumb-h: {{SIZE}}px;',
                ),
            )
        );

        $this->add_responsive_control(
            'thumb_radius',
            array(
                'label' => __('Arrondi vignette', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px', '%'),
                'range' => array(
                    'px' => array('min' => 0, 'max' => 40),
                    '%' => array('min' => 0, 'max' => 50),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-player-widget' => '--jb-thumb-radius: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'thumb_border_width',
            array(
                'label' => __('Bordure vignette', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array(
                    'px' => array('min' => 0, 'max' => 10),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-player-widget' => '--jb-thumb-border-width: {{SIZE}}px;',
                ),
            )
        );

        $this->add_control(
            'thumb_border_color',
            array(
                'label' => __('Couleur bordure vignette', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-player-widget' => '--jb-thumb-border-color: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_source_presets',
            array(
                'label' => __('Presets sources', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'source_presets_bg',
            array(
                'label' => __('Fond zone presets', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .jb-presets' => 'background: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'source_presets_label_color',
            array(
                'label' => __('Couleur titre presets', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .jb-presets__label' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'source_preset_btn_bg',
            array(
                'label' => __('Fond bouton preset', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .jb-preset__btn' => 'background: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'source_preset_btn_color',
            array(
                'label' => __('Couleur texte preset', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .jb-preset__btn' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'source_preset_btn_border_color',
            array(
                'label' => __('Couleur bordure preset', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .jb-preset__btn' => 'border-color: {{VALUE}};',
                ),
            )
        );

        $this->add_responsive_control(
            'source_preset_btn_radius',
            array(
                'label' => __('Arrondi preset', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px', '%'),
                'range' => array(
                    'px' => array('min' => 0, 'max' => 50),
                    '%' => array('min' => 0, 'max' => 50),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .jb-preset__btn' => 'border-radius: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->end_controls_section();

        $this->register_visibility_controls();
    }

    protected function render()
    {
        $settings = $this->get_settings_for_display();
        $this->apply_visibility_to_wrapper($settings, 'mj-player-widget');

        $readDimension = static function ($value, array $allowedUnits): string {
            if (!is_array($value)) {
                return '';
            }

            $size = isset($value['size']) ? (float) $value['size'] : 0.0;
            $unit = isset($value['unit']) ? strtolower((string) $value['unit']) : 'px';

            if ($size <= 0 || !in_array($unit, $allowedUnits, true)) {
                return '';
            }

            $sizeString = rtrim(rtrim(sprintf('%.4F', $size), '0'), '.');
            return $sizeString . $unit;
        };

        $title = isset($settings['title']) ? sanitize_text_field((string) $settings['title']) : '';
        $intro = isset($settings['intro_text']) ? wp_kses_post($settings['intro_text']) : '';
        $headerTitle = isset($settings['header_title_text']) ? sanitize_text_field((string) $settings['header_title_text']) : '';
        $headerSub = isset($settings['header_sub_text']) ? sanitize_text_field((string) $settings['header_sub_text']) : '';
        $screenLabel = isset($settings['screen_label_text']) ? sanitize_text_field((string) $settings['screen_label_text']) : '';
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

        $width = $readDimension(isset($settings['widget_width']) ? $settings['widget_width'] : null, array('px', '%', 'vw'));
        $height = $readDimension(isset($settings['widget_height']) ? $settings['widget_height'] : null, array('px', 'vh'));
        $rootStyleParts = array();

        if ($width !== '') {
            $rootStyleParts[] = '--mj-player-width: ' . $width;
            $rootStyleParts[] = 'width: min(100%, ' . $width . ')';
            $rootStyleParts[] = 'max-width: ' . $width;
        }

        if ($height !== '') {
            $rootStyleParts[] = '--mj-player-height: ' . $height;
        }

        $template_data = array(
            'title' => $title,
            'intro' => $intro,
            'root_style' => implode('; ', $rootStyleParts),
            'config' => array(
                'apiKey' => $apiKey,
                'defaultQuery' => $defaultQuery,
                'maxResults' => $maxResults,
                'autoplay' => $autoplay,
                'playlists' => $playlists,
                'headerTitle' => $headerTitle,
                'headerSub' => $headerSub,
                'screenLabel' => $screenLabel,
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
