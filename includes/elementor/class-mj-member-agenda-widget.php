<?php
/**
 * Elementor widget: unified Agenda (Schedule-X based).
 *
 * @package MjMember
 */

if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;

class Mj_Member_Elementor_Agenda_Widget extends Widget_Base {
    use Mj_Member_Elementor_Widget_Visibility;

    public function get_name() {
        return 'mj-member-agenda';
    }

    public function get_title() {
        return __('Agenda unifié MJ', 'mj-member');
    }

    public function get_icon() {
        return 'eicon-calendar';
    }

    public function get_categories() {
        return array('mj-member');
    }

    public function get_keywords() {
        return array('mj', 'agenda', 'calendar', 'calendrier', 'planning', 'horaire', 'schedule');
    }

    public function get_style_depends() {
        return array('mj-member-agenda');
    }

    public function get_script_depends() {
        return array('mj-member-agenda-app');
    }

    /**
     * @return array<string,string>
     */
    private function event_status_options() {
        if (class_exists('MjEvents') && method_exists('MjEvents', 'get_status_labels')) {
            $labels = MjEvents::get_status_labels();
            if (is_array($labels) && !empty($labels)) {
                return $labels;
            }
        }

        return array(
            'actif' => __('Actif', 'mj-member'),
            'brouillon' => __('Brouillon', 'mj-member'),
            'archive' => __('Archivé', 'mj-member'),
        );
    }

    /**
     * @return array<string,string>
     */
    private function event_type_options() {
        if (class_exists('MjEvents') && method_exists('MjEvents', 'get_type_labels')) {
            $labels = MjEvents::get_type_labels();
            if (is_array($labels) && !empty($labels)) {
                return $labels;
            }
        }

        return array();
    }

    protected function register_controls() {
        $layer_options = array(
            'event_occurrences' => __('Occurrences d\'événement', 'mj-member'),
            'worked_hours' => __('Heures prestées', 'mj-member'),
            'todos' => __('Tâches', 'mj-member'),
            'leave_requests' => __('Congés', 'mj-member'),
            'work_schedules' => __('Horaires de travail', 'mj-member'),
            'requests' => __('Demandes', 'mj-member'),
            'internal_notes' => __('Notes internes', 'mj-member'),
        );

        $view_options = array(
            'month-grid' => __('Mois', 'mj-member'),
            'week' => __('Semaine', 'mj-member'),
            'day' => __('Jour', 'mj-member'),
            'month-agenda' => __('Agenda du mois', 'mj-member'),
            'list' => __('Liste', 'mj-member'),
        );

        $hour_options = array();
        for ($h = 0; $h <= 23; $h++) {
            $hour_options[sprintf('%02d', $h)] = sprintf('%02dh', $h);
        }

        /* ---------------------------------------------------------------- Contenu */
        $this->start_controls_section('section_content', array('label' => __('Contenu', 'mj-member')));

        $this->add_control('title', array(
            'label' => __('Titre', 'mj-member'),
            'type' => Controls_Manager::TEXT,
            'default' => __('Agenda', 'mj-member'),
            'label_block' => true,
        ));

        $this->add_control('intro', array(
            'label' => __('Texte d\'introduction', 'mj-member'),
            'type' => Controls_Manager::TEXTAREA,
            'rows' => 2,
        ));

        $this->add_control('data_layers', array(
            'label' => __('Couches affichées', 'mj-member'),
            'type' => Controls_Manager::SELECT2,
            'multiple' => true,
            'label_block' => true,
            'options' => $layer_options,
            'default' => array_keys($layer_options),
            'description' => __('Les accès réels restent filtrés par la matrice ACL (MJ Member → Agenda / Accès).', 'mj-member'),
        ));

        $this->add_control('event_statuses', array(
            'label' => __('Statuts d\'événement', 'mj-member'),
            'type' => Controls_Manager::SELECT2,
            'multiple' => true,
            'label_block' => true,
            'options' => $this->event_status_options(),
            'default' => array('actif'),
        ));

        $this->add_control('event_types', array(
            'label' => __('Types d\'événement', 'mj-member'),
            'type' => Controls_Manager::SELECT2,
            'multiple' => true,
            'label_block' => true,
            'options' => $this->event_type_options(),
        ));

        $this->add_control('range_months_before', array(
            'label' => __('Mois affichés dans le passé', 'mj-member'),
            'type' => Controls_Manager::NUMBER,
            'min' => 0,
            'max' => 6,
            'default' => 1,
        ));

        $this->add_control('range_months_after', array(
            'label' => __('Mois affichés dans le futur', 'mj-member'),
            'type' => Controls_Manager::NUMBER,
            'min' => 1,
            'max' => 12,
            'default' => 3,
        ));

        $this->add_control('member_scope', array(
            'label' => __('Périmètre membres', 'mj-member'),
            'type' => Controls_Manager::SELECT,
            'default' => 'team',
            'options' => array(
                'self' => __('Mes éléments uniquement', 'mj-member'),
                'team' => __('Équipe (animateurs + coordinateurs)', 'mj-member'),
                'all' => __('Tous', 'mj-member'),
            ),
            'description' => __('Borné par l\'autorisation « voir les éléments des autres ».', 'mj-member'),
        ));

        $this->end_controls_section();

        /* ---------------------------------------------------------------- Vues */
        $this->start_controls_section('section_views', array('label' => __('Vues', 'mj-member')));

        $this->add_control('available_views', array(
            'label' => __('Vues disponibles', 'mj-member'),
            'type' => Controls_Manager::SELECT2,
            'multiple' => true,
            'label_block' => true,
            'options' => $view_options,
            'default' => array('month-grid', 'week', 'day', 'list'),
        ));

        $this->add_control('default_view', array(
            'label' => __('Vue par défaut', 'mj-member'),
            'type' => Controls_Manager::SELECT,
            'default' => 'month-grid',
            'options' => $view_options,
        ));

        $this->add_control('default_view_mobile', array(
            'label' => __('Vue par défaut (mobile)', 'mj-member'),
            'type' => Controls_Manager::SELECT,
            'default' => 'month-agenda',
            'options' => $view_options,
        ));

        $this->add_control('first_day_of_week', array(
            'label' => __('Premier jour de la semaine', 'mj-member'),
            'type' => Controls_Manager::SELECT,
            'default' => 'monday',
            'options' => array(
                'monday' => __('Lundi', 'mj-member'),
                'sunday' => __('Dimanche', 'mj-member'),
            ),
        ));

        $this->add_control('day_start_hour', array(
            'label' => __('Heure de début (grille)', 'mj-member'),
            'type' => Controls_Manager::SELECT,
            'default' => '07',
            'options' => $hour_options,
        ));

        $this->add_control('day_end_hour', array(
            'label' => __('Heure de fin (grille)', 'mj-member'),
            'type' => Controls_Manager::SELECT,
            'default' => '23',
            'options' => $hour_options,
        ));

        $this->add_control('show_weekends', array(
            'label' => __('Afficher les week-ends', 'mj-member'),
            'type' => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default' => 'yes',
        ));

        $this->add_control('show_current_time_indicator', array(
            'label' => __('Indicateur d\'heure courante', 'mj-member'),
            'type' => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default' => 'yes',
        ));

        $this->end_controls_section();

        /* ---------------------------------------------------------------- Interactions */
        $this->start_controls_section('section_interactions', array('label' => __('Interactions', 'mj-member')));

        $this->add_control('enable_drag_drop', array(
            'label' => __('Glisser-déposer', 'mj-member'),
            'type' => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default' => 'yes',
        ));

        $this->add_control('enable_resize', array(
            'label' => __('Redimensionnement', 'mj-member'),
            'type' => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default' => 'yes',
        ));

        $this->add_control('enable_click_create', array(
            'label' => __('Créer au clic sur un jour/créneau', 'mj-member'),
            'type' => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default' => 'yes',
        ));

        $this->add_control('confirm_before_move', array(
            'label' => __('Confirmer avant un déplacement', 'mj-member'),
            'type' => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default' => 'yes',
        ));

        $this->end_controls_section();

        /* ---------------------------------------------------------------- Apparence */
        $this->start_controls_section('section_appearance', array('label' => __('Apparence des couches', 'mj-member')));

        $palette = array(
            'color_event' => array(__('Événements', 'mj-member'), '#6366f1'),
            'color_worked_hours' => array(__('Heures prestées', 'mj-member'), '#0ea5e9'),
            'color_todo' => array(__('Tâches', 'mj-member'), '#8b5cf6'),
            'color_leave' => array(__('Congés', 'mj-member'), '#22c55e'),
            'color_work_schedule' => array(__('Horaires de travail', 'mj-member'), '#94a3b8'),
            'color_request' => array(__('Demandes', 'mj-member'), '#f97316'),
            'color_internal_note' => array(__('Notes internes', 'mj-member'), '#eab308'),
        );
        foreach ($palette as $key => $meta) {
            $this->add_control($key, array(
                'label' => $meta[0],
                'type' => Controls_Manager::COLOR,
                'default' => $meta[1],
            ));
        }

        $this->add_control('highlight_closures', array(
            'label' => __('Surligner les fermetures MJ', 'mj-member'),
            'type' => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default' => 'yes',
        ));

        $this->add_control('color_closure', array(
            'label' => __('Couleur des fermetures', 'mj-member'),
            'type' => Controls_Manager::COLOR,
            'default' => '#ef4444',
            'condition' => array('highlight_closures' => 'yes'),
        ));

        $this->end_controls_section();

        /* ---------------------------------------------------------------- Barre d'outils */
        $this->start_controls_section('section_toolbar', array('label' => __('Barre d\'outils', 'mj-member')));

        foreach (array(
            'show_toolbar' => __('Afficher la barre d\'outils', 'mj-member'),
            'show_view_switcher' => __('Sélecteur de vue', 'mj-member'),
            'show_today_button' => __('Bouton « Aujourd\'hui »', 'mj-member'),
            'show_filters' => __('Filtres de couches', 'mj-member'),
            'show_legend' => __('Légende', 'mj-member'),
            'show_add_button' => __('Bouton « Ajouter »', 'mj-member'),
        ) as $key => $label) {
            $this->add_control($key, array(
                'label' => $label,
                'type' => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default' => 'yes',
            ));
        }

        $this->add_control('sticky_toolbar', array(
            'label' => __('Barre d\'outils collante', 'mj-member'),
            'type' => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default' => '',
        ));

        $this->add_control('calendar_height', array(
            'label' => __('Hauteur du calendrier', 'mj-member'),
            'type' => Controls_Manager::SELECT,
            'default' => 'auto',
            'options' => array(
                'auto' => __('Automatique', 'mj-member'),
                'fixed' => __('Fixe', 'mj-member'),
            ),
        ));

        $this->add_control('fixed_height', array(
            'label' => __('Hauteur fixe (px)', 'mj-member'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => array('px'),
            'range' => array('px' => array('min' => 320, 'max' => 1400)),
            'default' => array('size' => 720, 'unit' => 'px'),
            'condition' => array('calendar_height' => 'fixed'),
        ));

        $this->end_controls_section();

        /* ---------------------------------------------------------------- Style */
        $this->start_controls_section('section_style_container', array(
            'label' => __('Conteneur', 'mj-member'),
            'tab' => Controls_Manager::TAB_STYLE,
        ));

        $this->add_control('container_background', array(
            'label' => __('Couleur de fond', 'mj-member'),
            'type' => Controls_Manager::COLOR,
            'selectors' => array('{{WRAPPER}} .mj-agenda' => 'background-color: {{VALUE}};'),
        ));

        $this->add_responsive_control('container_padding', array(
            'label' => __('Padding', 'mj-member'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => array('px', 'em', '%'),
            'selectors' => array(
                '{{WRAPPER}} .mj-agenda' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ),
        ));

        $this->add_group_control(Group_Control_Border::get_type(), array(
            'name' => 'container_border',
            'selector' => '{{WRAPPER}} .mj-agenda',
        ));

        $this->add_control('container_radius', array(
            'label' => __('Rayon de bordure', 'mj-member'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => array('px'),
            'range' => array('px' => array('min' => 0, 'max' => 40)),
            'selectors' => array('{{WRAPPER}} .mj-agenda' => 'border-radius: {{SIZE}}{{UNIT}};'),
        ));

        $this->add_group_control(Group_Control_Box_Shadow::get_type(), array(
            'name' => 'container_shadow',
            'selector' => '{{WRAPPER}} .mj-agenda',
        ));

        $this->add_group_control(Group_Control_Typography::get_type(), array(
            'name' => 'title_typography',
            'selector' => '{{WRAPPER}} .mj-agenda__title',
        ));

        $this->end_controls_section();

        $this->register_visibility_controls();
    }

    protected function render() {
        include \Mj\Member\Core\Config::path() . 'includes/templates/elementor/agenda.php';
    }

    protected function content_template() {
        ?>
        <div class="mj-agenda mj-agenda--preview">
            <div class="mj-agenda__preview">
                <span class="dashicons dashicons-calendar-alt"></span>
                <h3>{{{ settings.title }}}</h3>
                <p><?php esc_html_e('Aperçu de l\'agenda unifié (rendu complet sur la page publiée).', 'mj-member'); ?></p>
            </div>
        </div>
        <?php
    }
}
