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

class Mj_Member_Elementor_Team_Directory_Widget extends Widget_Base {
    use Mj_Member_Elementor_Widget_Visibility;

    public function get_name() {
        return 'mj-member-team-directory';
    }

    public function get_title() {
        return __('Équipe MJ – Animateurs', 'mj-member');
    }

    public function get_icon() {
        return 'eicon-persons';
    }

    public function get_categories() {
        return array('general');
    }

    public function get_keywords() {
        return array('mj', 'member', 'animateur', 'coordinateur', 'equipe');
    }

    public function get_style_depends() {
        return array('mj-member-components');
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
                'default' => __('Rencontrez l\'équipe d\'animation', 'mj-member'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'description',
            array(
                'label' => __('Description', 'mj-member'),
                'type' => Controls_Manager::TEXTAREA,
                'default' => __('Animateurs et coordinateurs disponibles pour accompagner les jeunes.', 'mj-member'),
                'rows' => 3,
            )
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_query',
            array(
                'label' => __('Source', 'mj-member'),
            )
        );

        $this->add_control(
            'roles',
            array(
                'label' => __('Rôles affichés', 'mj-member'),
                'type' => Controls_Manager::SELECT2,
                'options' => array(
                    'animateur' => __('Animateurs', 'mj-member'),
                    'coordinateur' => __('Coordinateurs', 'mj-member'),
                ),
                'default' => array('animateur', 'coordinateur'),
                'multiple' => true,
                'label_block' => true,
            )
        );

        $this->add_control(
            'limit',
            array(
                'label' => __('Nombre maximum', 'mj-member'),
                'type' => Controls_Manager::NUMBER,
                'min' => 0,
                'step' => 1,
                'default' => 0,
                'description' => __('Laisser 0 pour afficher tous les membres.', 'mj-member'),
            )
        );

        $this->add_control(
            'orderby',
            array(
                'label' => __('Trier par', 'mj-member'),
                'type' => Controls_Manager::SELECT,
                'options' => array(
                    'last_name' => __('Nom de famille', 'mj-member'),
                    'first_name' => __('Prénom', 'mj-member'),
                    'nickname' => __('Surnom', 'mj-member'),
                    'date_inscription' => __('Date d\'inscription', 'mj-member'),
                    'created_at' => __('Date de création', 'mj-member'),
                ),
                'default' => 'last_name',
            )
        );

        $this->add_control(
            'order',
            array(
                'label' => __('Ordre', 'mj-member'),
                'type' => Controls_Manager::SELECT,
                'options' => array(
                    'ASC' => __('Croissant (A→Z)', 'mj-member'),
                    'DESC' => __('Décroissant (Z→A)', 'mj-member'),
                ),
                'default' => 'ASC',
            )
        );

        $this->add_control(
            'only_active',
            array(
                'label' => __('Afficher uniquement les membres actifs', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->end_controls_section();

        $this->register_visibility_controls();

        $this->start_controls_section(
            'section_style_surface',
            array(
                'label' => __('Conteneur', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'surface_background',
            array(
                'label' => __('Fond', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-team-directory' => '--mj-team-surface-bg: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'surface_border',
            array(
                'label' => __('Bordure', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-team-directory' => '--mj-team-surface-border: {{VALUE}};',
                ),
            )
        );

        $this->add_responsive_control(
            'surface_padding',
            array(
                'label' => __('Marge intérieure', 'mj-member'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => array('px', 'em', '%'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-team-directory__surface' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'surface_radius',
            array(
                'label' => __('Arrondi', 'mj-member'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => array('px', '%'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-team-directory__surface' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        if (class_exists('Elementor\\Group_Control_Box_Shadow')) {
            $this->add_group_control(
                Group_Control_Box_Shadow::get_type(),
                array(
                    'name' => 'surface_shadow',
                    'selector' => '{{WRAPPER}} .mj-team-directory__surface',
                )
            );
        }

        $this->add_control(
            'columns_tablet',
            array(
                'label' => __('Colonnes (tablette)', 'mj-member'),
                'type' => Controls_Manager::SELECT,
                'options' => array(
                    '1' => '1',
                    '2' => '2',
                ),
                'default' => '2',
                'selectors' => array(
                    '{{WRAPPER}} .mj-team-directory' => '--mj-team-columns-tablet: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'columns_desktop',
            array(
                'label' => __('Colonnes (desktop)', 'mj-member'),
                'type' => Controls_Manager::SELECT,
                'options' => array(
                    '2' => '2',
                    '3' => '3',
                    '4' => '4',
                ),
                'default' => '3',
                'selectors' => array(
                    '{{WRAPPER}} .mj-team-directory' => '--mj-team-columns-desktop: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_header',
            array(
                'label' => __('En-tête', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'title_color',
            array(
                'label' => __('Couleur du titre', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-team-directory__title' => 'color: {{VALUE}};',
                ),
            )
        );

        if (class_exists('Elementor\\Group_Control_Typography')) {
            $this->add_group_control(
                Group_Control_Typography::get_type(),
                array(
                    'name' => 'title_typography',
                    'selector' => '{{WRAPPER}} .mj-team-directory__title',
                )
            );
        }

        $this->add_control(
            'description_color',
            array(
                'label' => __('Couleur du texte', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-team-directory__description' => 'color: {{VALUE}};',
                ),
            )
        );

        if (class_exists('Elementor\\Group_Control_Typography')) {
            $this->add_group_control(
                Group_Control_Typography::get_type(),
                array(
                    'name' => 'description_typography',
                    'selector' => '{{WRAPPER}} .mj-team-directory__description',
                )
            );
        }

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_cards',
            array(
                'label' => __('Cartes', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'accent_color',
            array(
                'label' => __('Couleur d\'accent', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-team-directory' => '--mj-team-accent: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'card_background',
            array(
                'label' => __('Fond des cartes', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-team-directory' => '--mj-team-card-bg: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'card_border_tone',
            array(
                'label' => __('Bordure des cartes', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-team-directory' => '--mj-team-card-border: {{VALUE}};',
                ),
            )
        );

        if (class_exists('Elementor\\Group_Control_Border')) {
            $this->add_group_control(
                Group_Control_Border::get_type(),
                array(
                    'name' => 'card_border',
                    'selector' => '{{WRAPPER}} .mj-team-directory__card',
                )
            );
        }

        if (class_exists('Elementor\\Group_Control_Box_Shadow')) {
            $this->add_group_control(
                Group_Control_Box_Shadow::get_type(),
                array(
                    'name' => 'card_shadow',
                    'selector' => '{{WRAPPER}} .mj-team-directory__card',
                )
            );
        }

        $this->add_control(
            'name_color',
            array(
                'label' => __('Couleur du nom', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-team-directory__name' => 'color: {{VALUE}};',
                ),
            )
        );

        if (class_exists('Elementor\\Group_Control_Typography')) {
            $this->add_group_control(
                Group_Control_Typography::get_type(),
                array(
                    'name' => 'name_typography',
                    'selector' => '{{WRAPPER}} .mj-team-directory__name',
                )
            );
        }

        $this->add_control(
            'nickname_color',
            array(
                'label' => __('Couleur du surnom', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-team-directory__nickname' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'description_card_color',
            array(
                'label' => __('Couleur de la description', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-team-directory__bio' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'email_color',
            array(
                'label' => __('Couleur du lien email', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-team-directory__email' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_responsive_control(
            'grid_gap',
            array(
                'label' => __('Espacement', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px', 'em'),
                'range' => array(
                    'px' => array('min' => 0, 'max' => 80),
                    'em' => array('min' => 0, 'max' => 6),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-team-directory__grid' => 'gap: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->end_controls_section();
    }

    protected function render() {
        if (!function_exists('mj_member_get_team_directory_members')) {
            return;
        }

        $settings = $this->get_settings_for_display();
        $this->apply_visibility_to_wrapper($settings, 'mj-team-directory');

        $title = isset($settings['title']) ? $settings['title'] : '';
        $description = isset($settings['description']) ? $settings['description'] : '';

        $raw_roles = isset($settings['roles']) ? $settings['roles'] : array('animateur', 'coordinateur');
        if (!is_array($raw_roles)) {
            $raw_roles = array($raw_roles);
        }

        $roles = array();
        foreach ($raw_roles as $role) {
            $role_key = sanitize_key((string) $role);
            if ($role_key !== '') {
                $roles[] = $role_key;
            }
        }

        if (empty($roles)) {
            $roles = array('animateur', 'coordinateur');
        }

        $limit = isset($settings['limit']) ? (int) $settings['limit'] : 0;
        if ($limit < 0) {
            $limit = 0;
        }

        $orderby = isset($settings['orderby']) ? sanitize_key($settings['orderby']) : 'last_name';
        $order = isset($settings['order']) ? strtoupper((string) $settings['order']) : 'ASC';
        $only_active = !isset($settings['only_active']) || $settings['only_active'] === 'yes';

        $query_args = array(
            'roles' => $roles,
            'limit' => $limit,
            'orderby' => $orderby,
            'order' => $order,
            'include_inactive' => !$only_active,
        );

        $members = mj_member_get_team_directory_members($query_args);

        $is_preview = false;
        if (class_exists('Elementor\\Plugin')) {
            $elementor = Elementor\Plugin::instance();
            if (method_exists($elementor, 'editor') && $elementor->editor && method_exists($elementor->editor, 'is_edit_mode')) {
                $is_preview = $is_preview || (bool) $elementor->editor->is_edit_mode();
            }
            if (method_exists($elementor, 'preview') && $elementor->preview && method_exists($elementor->preview, 'is_preview_mode')) {
                $is_preview = $is_preview || (bool) $elementor->preview->is_preview_mode();
            }
        }

        if ($is_preview && empty($members) && function_exists('mj_member_get_team_directory_preview_members')) {
            $members = mj_member_get_team_directory_preview_members();
        }

        $role_labels = array();
        if (class_exists('MjMembers')) {
            $role_labels = MjMembers::getRoleLabels();
        }

        $selected_roles = array();
        foreach ($roles as $role_key) {
            if (isset($role_labels[$role_key])) {
                $selected_roles[] = sanitize_text_field((string) $role_labels[$role_key]);
            } elseif ($role_key !== '') {
                $selected_roles[] = sanitize_text_field(ucfirst(str_replace('_', ' ', $role_key)));
            }
        }

        $empty_message = __('Aucun membre disponible pour ce filtre.', 'mj-member');
        if (!empty($selected_roles)) {
            $joined = implode(' / ', $selected_roles);
            $empty_message = $only_active
                ? sprintf(__('Aucun %s actif pour le moment.', 'mj-member'), $joined)
                : sprintf(__('Aucun %s correspondant.', 'mj-member'), $joined);
        }

        $template_data = array(
            'title' => $title,
            'description' => $description,
            'members' => $members,
            'is_preview' => $is_preview,
            'empty_message' => $empty_message,
            'filters' => array(
                'roles' => $selected_roles,
                'orderby' => $orderby,
                'order' => $order,
                'limit' => $limit,
                'only_active' => $only_active,
            ),
        );

        $template_path = Config::path() . 'includes/templates/elementor/team_directory.php';
        if (file_exists($template_path)) {
            $widget = $this;
            include $template_path;
        }
    }
}
