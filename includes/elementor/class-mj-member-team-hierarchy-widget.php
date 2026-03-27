<?php

if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;
use Mj\Member\Core\Config;

class Mj_Member_Elementor_Team_Hierarchy_Widget extends Widget_Base {
    use Mj_Member_Elementor_Widget_Visibility;

    public function get_name() {
        return 'mj-member-team-hierarchy';
    }

    public function get_title() {
        return __('MJ - Organigramme equipe', 'mj-member');
    }

    public function get_icon() {
        return 'eicon-person';
    }

    public function get_categories() {
        return array('mj-member');
    }

    public function get_keywords() {
        return array('mj', 'member', 'equipe', 'organigramme', 'animateur', 'coordinateur', 'hierarchie');
    }

    public function get_style_depends() {
        return array('mj-member-components', 'mj-member-team-hierarchy');
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
                'default' => __('Notre equipe pedagogique', 'mj-member'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'subtitle',
            array(
                'label' => __('Sous-titre', 'mj-member'),
                'type' => Controls_Manager::TEXTAREA,
                'default' => __('Decouvrez la hierarchie des coordinateurs et animateurs, avec leurs roles, niveaux et coordonnees.', 'mj-member'),
                'rows' => 3,
            )
        );

        $this->add_control(
            'coordinators_label',
            array(
                'label' => __('Titre section coordinateurs', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Coordination', 'mj-member'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'animateurs_label',
            array(
                'label' => __('Titre section animateurs', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Equipe animation', 'mj-member'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'team_structure',
            array(
                'label' => __('Structure de l\'equipe', 'mj-member'),
                'type' => Controls_Manager::SELECT,
                'default' => 'hierarchy',
                'options' => array(
                    'hierarchy' => __('Hierarchie', 'mj-member'),
                    'flat' => __('Meme niveau pour toute l\'equipe', 'mj-member'),
                ),
            )
        );

        $this->add_control(
            'flat_label',
            array(
                'label' => __('Titre section vue simple', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Toute l\'equipe', 'mj-member'),
                'label_block' => true,
                'condition' => array(
                    'team_structure' => 'flat',
                ),
            )
        );

        $this->add_control(
            'show_coordinators',
            array(
                'label' => __('Afficher les coordinateurs', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->add_control(
            'show_coordinators_with_photo_only',
            array(
                'label' => __('Afficher uniquement les membres avec photo', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => '',
                'condition' => array(
                    'show_coordinators' => 'yes',
                ),
            )
        );

        $this->add_control(
            'show_animateurs',
            array(
                'label' => __('Afficher les animateurs', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->add_control(
            'show_animateurs_with_photo_only',
            array(
                'label' => __('Afficher uniquement les membres avec photo', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => '',
                'condition' => array(
                    'show_animateurs' => 'yes',
                ),
            )
        );

        $this->add_control(
            'show_jeunes',
            array(
                'label' => __('Afficher les jeunes', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => '',
            )
        );

        $this->add_control(
            'show_jeunes_with_photo_only',
            array(
                'label' => __('Afficher uniquement les membres avec photo', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => '',
                'condition' => array(
                    'show_jeunes' => 'yes',
                ),
            )
        );

        $this->add_control(
            'show_jeunes_xp_gt_1_only',
            array(
                'label' => __('Afficher uniquement les jeunes avec XP > 1', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => '',
                'condition' => array(
                    'show_jeunes' => 'yes',
                ),
            )
        );

        $this->add_control(
            'jeunes_label',
            array(
                'label' => __('Titre section jeunes', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Jeunes', 'mj-member'),
                'label_block' => true,
                'condition' => array(
                    'show_jeunes' => 'yes',
                ),
            )
        );

        $this->add_control(
            'max_jeunes',
            array(
                'label' => __('Max jeunes', 'mj-member'),
                'type' => Controls_Manager::NUMBER,
                'min' => 0,
                'max' => 300,
                'default' => 24,
                'description' => __('0 = illimite', 'mj-member'),
                'condition' => array(
                    'show_jeunes' => 'yes',
                ),
            )
        );

        $this->add_control(
            'member_cards_grid',
            array(
                'label' => __('Grille des cartes membres', 'mj-member'),
                'type' => Controls_Manager::SELECT,
                'default' => 'grid-3',
                'options' => array(
                    'grid-2' => __('2 colonnes', 'mj-member'),
                    'grid-3' => __('3 colonnes', 'mj-member'),
                    'grid-4' => __('4 colonnes', 'mj-member'),
                ),
            )
        );

        $this->add_control(
            'empty_message',
            array(
                'label' => __('Message si vide', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Aucun membre a afficher pour le moment.', 'mj-member'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'show_job_title',
            array(
                'label' => __('Afficher la fonction', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->add_control(
            'show_bio',
            array(
                'label' => __('Afficher la bio courte', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->add_control(
            'bio_max_lines',
            array(
                'label' => __('Nombre max de lignes pour la bio', 'mj-member'),
                'type' => Controls_Manager::NUMBER,
                'min' => 1,
                'max' => 10,
                'step' => 1,
                'default' => 3,
                'condition' => array(
                    'show_bio' => 'yes',
                ),
            )
        );

        $this->add_control(
            'show_contacts',
            array(
                'label' => __('Afficher telephone et email', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->add_control(
            'show_level',
            array(
                'label' => __('Afficher niveau et XP', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->add_control(
            'show_progress',
            array(
                'label' => __('Afficher progression XP', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
                'condition' => array(
                    'show_level' => 'yes',
                ),
            )
        );

        $this->add_control(
            'show_inactive',
            array(
                'label' => __('Inclure les membres inactifs', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => '',
            )
        );

        $this->add_control(
            'max_coordinators',
            array(
                'label' => __('Max coordinateurs', 'mj-member'),
                'type' => Controls_Manager::NUMBER,
                'min' => 0,
                'max' => 50,
                'default' => 6,
                'description' => __('0 = illimite', 'mj-member'),
            )
        );

        $this->add_control(
            'max_animateurs',
            array(
                'label' => __('Max animateurs', 'mj-member'),
                'type' => Controls_Manager::NUMBER,
                'min' => 0,
                'max' => 200,
                'default' => 18,
                'description' => __('0 = illimite', 'mj-member'),
            )
        );

        $this->add_control(
            'orderby',
            array(
                'label' => __('Trier par', 'mj-member'),
                'type' => Controls_Manager::SELECT,
                'default' => 'last_name',
                'options' => array(
                    'last_name' => __('Nom', 'mj-member'),
                    'first_name' => __('Prenom', 'mj-member'),
                    'xp_total' => __('XP total', 'mj-member'),
                    'date_inscription' => __('Date inscription', 'mj-member'),
                ),
            )
        );

        $this->add_control(
            'order',
            array(
                'label' => __('Ordre', 'mj-member'),
                'type' => Controls_Manager::SELECT,
                'default' => 'ASC',
                'options' => array(
                    'ASC' => __('Ascendant', 'mj-member'),
                    'DESC' => __('Descendant', 'mj-member'),
                ),
            )
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_layout',
            array(
                'label' => __('Style global', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'panel_bg_start',
            array(
                'label' => __('Fond gradient debut', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-team-hierarchy' => '--mj-team-bg-start: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'panel_bg_end',
            array(
                'label' => __('Fond gradient fin', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-team-hierarchy' => '--mj-team-bg-end: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'accent_color',
            array(
                'label' => __('Couleur accent', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-team-hierarchy' => '--mj-team-accent: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'text_color',
            array(
                'label' => __('Couleur texte principal', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-team-hierarchy' => '--mj-team-text: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'muted_text_color',
            array(
                'label' => __('Couleur texte secondaire', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-team-hierarchy' => '--mj-team-muted: {{VALUE}};',
                ),
            )
        );

        $this->add_responsive_control(
            'panel_padding',
            array(
                'label' => __('Padding externe', 'mj-member'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => array('px', '%', 'em', 'rem'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-team-hierarchy' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'panel_radius',
            array(
                'label' => __('Arrondi du conteneur', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array(
                    'px' => array('min' => 0, 'max' => 48),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-team-hierarchy' => 'border-radius: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            array(
                'name' => 'panel_border',
                'selector' => '{{WRAPPER}} .mj-team-hierarchy',
            )
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            array(
                'name' => 'panel_shadow',
                'selector' => '{{WRAPPER}} .mj-team-hierarchy',
            )
        );

        $this->add_control(
            'connector_color',
            array(
                'label' => __('Couleur des connecteurs', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-team-hierarchy' => '--mj-team-connector: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'connector_width',
            array(
                'label' => __('Epaisseur connecteurs', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array(
                    'px' => array('min' => 1, 'max' => 8),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-team-hierarchy' => '--mj-team-connector-width: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'cards_gap',
            array(
                'label' => __('Espace entre cartes', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array(
                    'px' => array('min' => 8, 'max' => 80),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-team-hierarchy' => '--mj-team-gap: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'center_member_cards',
            array(
                'label' => __('Centrer les cartes membres', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => '',
                'selectors' => array(
                    '{{WRAPPER}} .mj-team-hierarchy__grid' => 'justify-items: center;',
                    '{{WRAPPER}} .mj-team-hierarchy__card' => 'width: 100%; max-width: min(100%, 380px);',
                ),
            )
        );

        $this->add_responsive_control(
            'card_min_width',
            array(
                'label' => __('Largeur min carte', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array(
                    'px' => array('min' => 180, 'max' => 420),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-team-hierarchy' => '--mj-team-card-min-width: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_titles',
            array(
                'label' => __('Titres', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'main_title_color',
            array(
                'label' => __('Couleur titre principal', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-team-hierarchy__title' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            array(
                'name' => 'main_title_typography',
                'selector' => '{{WRAPPER}} .mj-team-hierarchy__title',
            )
        );

        $this->add_control(
            'subtitle_color',
            array(
                'label' => __('Couleur sous-titre', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-team-hierarchy__subtitle' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            array(
                'name' => 'subtitle_typography',
                'selector' => '{{WRAPPER}} .mj-team-hierarchy__subtitle',
            )
        );

        $this->add_control(
            'section_title_color',
            array(
                'label' => __('Couleur titres de section', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-team-hierarchy__section-title' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            array(
                'name' => 'section_title_typography',
                'selector' => '{{WRAPPER}} .mj-team-hierarchy__section-title',
            )
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_cards',
            array(
                'label' => __('Cartes membres', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'card_background_start',
            array(
                'label' => __('Fond carte debut degrade', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-team-hierarchy' => '--mj-team-card-bg-start: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'card_background',
            array(
                'label' => __('Fond carte fin degrade', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-team-hierarchy' => '--mj-team-card-bg: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'card_text_color',
            array(
                'label' => __('Texte carte', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-team-hierarchy' => '--mj-team-card-text: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'card_muted_color',
            array(
                'label' => __('Texte secondaire carte', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-team-hierarchy' => '--mj-team-card-muted: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'card_radius',
            array(
                'label' => __('Arrondi carte', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array(
                    'px' => array('min' => 0, 'max' => 40),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-team-hierarchy' => '--mj-team-card-radius: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            array(
                'name' => 'card_border',
                'selector' => '{{WRAPPER}} .mj-team-hierarchy__card',
            )
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            array(
                'name' => 'card_shadow',
                'selector' => '{{WRAPPER}} .mj-team-hierarchy__card',
            )
        );

        $this->add_control(
            'name_color',
            array(
                'label' => __('Couleur nom', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-team-hierarchy__name' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            array(
                'name' => 'name_typography',
                'selector' => '{{WRAPPER}} .mj-team-hierarchy__name',
            )
        );

        $this->add_control(
            'job_color',
            array(
                'label' => __('Couleur fonction', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-team-hierarchy__job' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            array(
                'name' => 'job_typography',
                'selector' => '{{WRAPPER}} .mj-team-hierarchy__job',
            )
        );

        $this->add_control(
            'bio_color',
            array(
                'label' => __('Couleur bio', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-team-hierarchy__bio' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            array(
                'name' => 'bio_typography',
                'selector' => '{{WRAPPER}} .mj-team-hierarchy__bio',
            )
        );

        $this->add_responsive_control(
            'bio_alignment',
            array(
                'label' => __('Alignement bio', 'mj-member'),
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
                    'justify' => array(
                        'title' => __('Justifie', 'mj-member'),
                        'icon' => 'eicon-text-align-justify',
                    ),
                ),
                'default' => 'center',
                'toggle' => true,
                'selectors' => array(
                    '{{WRAPPER}} .mj-team-hierarchy__bio' => 'text-align: {{VALUE}}; text-justify: inter-word;',
                ),
            )
        );

        $this->add_control(
            'badge_background',
            array(
                'label' => __('Fond badge role', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-team-hierarchy' => '--mj-team-badge-bg: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'badge_color',
            array(
                'label' => __('Texte badge role', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-team-hierarchy' => '--mj-team-badge-text: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            array(
                'name' => 'badge_typography',
                'selector' => '{{WRAPPER}} .mj-team-hierarchy__role-pill',
            )
        );

        $this->add_control(
            'stat_background',
            array(
                'label' => __('Fond niveau/xp', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-team-hierarchy' => '--mj-team-stat-bg: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'stat_color',
            array(
                'label' => __('Texte niveau/xp', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-team-hierarchy' => '--mj-team-stat-text: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            array(
                'name' => 'stat_typography',
                'selector' => '{{WRAPPER}} .mj-team-hierarchy__stat-chip',
            )
        );

        $this->add_responsive_control(
            'photo_size',
            array(
                'label' => __('Taille photo', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array(
                    'px' => array('min' => 40, 'max' => 220),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-team-hierarchy' => '--mj-team-photo-size: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'photo_radius',
            array(
                'label' => __('Arrondi photo', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px', '%'),
                'range' => array(
                    'px' => array('min' => 0, 'max' => 120),
                    '%' => array('min' => 0, 'max' => 50),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-team-hierarchy' => '--mj-team-photo-radius: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_contacts',
            array(
                'label' => __('Boutons contacts', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_responsive_control(
            'contact_icon_size',
            array(
                'label' => __('Taille icones', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array(
                    'px' => array('min' => 10, 'max' => 40),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-team-hierarchy' => '--mj-team-contact-icon-size: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'contact_button_size',
            array(
                'label' => __('Taille boutons', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array(
                    'px' => array('min' => 24, 'max' => 64),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-team-hierarchy' => '--mj-team-contact-size: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'contact_background',
            array(
                'label' => __('Fond bouton', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-team-hierarchy' => '--mj-team-contact-bg: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'contact_color',
            array(
                'label' => __('Couleur icone', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-team-hierarchy' => '--mj-team-contact-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'contact_hover_background',
            array(
                'label' => __('Fond bouton au survol', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-team-hierarchy' => '--mj-team-contact-bg-hover: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'contact_hover_color',
            array(
                'label' => __('Couleur icone au survol', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-team-hierarchy' => '--mj-team-contact-color-hover: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'contact_radius',
            array(
                'label' => __('Arrondi bouton contact', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px', '%'),
                'range' => array(
                    'px' => array('min' => 0, 'max' => 36),
                    '%' => array('min' => 0, 'max' => 50),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-team-hierarchy' => '--mj-team-contact-radius: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->end_controls_section();

        $this->register_visibility_controls();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $this->apply_visibility_to_wrapper($settings, 'mj-team-hierarchy');

        $template = Config::path() . 'includes/templates/elementor/team_hierarchy.php';
        if (is_readable($template)) {
            $widget = $this;
            include $template;
        }
    }
}
