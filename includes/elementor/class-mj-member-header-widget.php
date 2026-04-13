<?php
/**
 * Widget Elementor - Header MJ Complet
 *
 * Header tout-en-un : logo, menu, agenda, gestionnaire, Nextcloud,
 * notifications et compte/login. Entièrement configurable via Elementor.
 *
 * @package MjMember
 */

if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Controls_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Repeater;
use Elementor\Widget_Base;
use Mj\Member\Classes\Crud\MjEvents;
use Mj\Member\Classes\Crud\MjMembers;
use Mj\Member\Classes\MjAccountLinks;
use Mj\Member\Classes\MjRoles;
use Mj\Member\Core\AssetsManager;
use Mj\Member\Core\Config;

class Mj_Member_Elementor_Header_Widget extends Widget_Base {
    use Mj_Member_Elementor_Widget_Visibility;

    public function get_name() {
        return 'mj-member-header';
    }

    public function get_title() {
        return __('Header MJ Complet', 'mj-member');
    }

    public function get_icon() {
        return 'eicon-header';
    }

    public function get_categories() {
        return array('mj-member');
    }

    public function get_keywords() {
        return array('mj', 'header', 'menu', 'navigation', 'logo', 'login', 'notification', 'nextcloud', 'agenda');
    }

    public function get_style_depends() {
        return array('mj-member-header-widget');
    }

    public function get_script_depends() {
        return array('mj-member-header-widget');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function get_menu_options(): array {
        $menus = wp_get_nav_menus();
        $options = array('' => __('— Choisir un menu —', 'mj-member'));
        if (!empty($menus)) {
            foreach ($menus as $menu) {
                $options[$menu->term_id] = esc_html($menu->name);
            }
        }
        return $options;
    }

    // -------------------------------------------------------------------------
    // Controls
    // -------------------------------------------------------------------------

    protected function register_controls() {

        // =====================================================================
        // TAB CONTENT
        // =====================================================================

        // --- Section : Mise en page ---
        $this->start_controls_section('section_layout', array(
            'label' => __('Mise en page', 'mj-member'),
        ));

        $this->add_control('sticky', array(
            'label'        => __('Header collant (sticky)', 'mj-member'),
            'type'         => Controls_Manager::SWITCHER,
            'label_on'     => __('Oui', 'mj-member'),
            'label_off'    => __('Non', 'mj-member'),
            'return_value' => 'yes',
            'default'      => 'yes',
        ));

        $this->add_responsive_control('header_height', array(
            'label'       => __('Hauteur du header', 'mj-member'),
            'type'        => Controls_Manager::SLIDER,
            'size_units'  => array('px'),
            'range'       => array('px' => array('min' => 40, 'max' => 140)),
            'default'     => array('unit' => 'px', 'size' => 70),
            'selectors'   => array(
                '{{WRAPPER}} .mj-header'        => '--mj-header-height: {{SIZE}}{{UNIT}}; --mj-header-stuck-height: {{SIZE}}{{UNIT}};',
                '{{WRAPPER}} .mj-header__inner' => 'height: {{SIZE}}{{UNIT}};',
            ),
        ));

        $this->add_responsive_control('sticky_height', array(
            'label'       => __('Hauteur quand collé', 'mj-member'),
            'type'        => Controls_Manager::SLIDER,
            'size_units'  => array('px'),
            'range'       => array('px' => array('min' => 40, 'max' => 120)),
            'default'     => array('unit' => 'px', 'size' => 56),
            'condition'   => array('sticky' => 'yes'),
            'selectors'   => array(
                '{{WRAPPER}} .mj-header'                          => '--mj-header-stuck-height: {{SIZE}}{{UNIT}};',
                '{{WRAPPER}} .mj-header--stuck .mj-header__inner' => 'height: {{SIZE}}{{UNIT}};',
            ),
        ));

        $this->add_responsive_control('content_width', array(
            'label'      => __('Largeur du contenu', 'mj-member'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => array('px', '%', 'vw'),
            'range'      => array(
                'px' => array('min' => 200, 'max' => 2400, 'step' => 10),
                '%'  => array('min' => 10,  'max' => 100),
                'vw' => array('min' => 10,  'max' => 100),
            ),
            'selectors'  => array(
                '{{WRAPPER}} .mj-header__inner' => 'max-width: {{SIZE}}{{UNIT}}; width: 100%;',
            ),
        ));

        $this->add_responsive_control('content_align', array(
            'label'     => __('Alignement du contenu', 'mj-member'),
            'type'      => Controls_Manager::CHOOSE,
            'options'   => array(
                'flex-start' => array(
                    'title' => __('Gauche', 'mj-member'),
                    'icon'  => 'eicon-h-align-left',
                ),
                'center'     => array(
                    'title' => __('Centre', 'mj-member'),
                    'icon'  => 'eicon-h-align-center',
                ),
                'flex-end'   => array(
                    'title' => __('Droite', 'mj-member'),
                    'icon'  => 'eicon-h-align-right',
                ),
            ),
            'selectors' => array(
                '{{WRAPPER}} .mj-header' => 'justify-content: {{VALUE}};',
            ),
        ));

        $this->end_controls_section();

        // --- Section : Ordre des éléments ---
        $this->start_controls_section('section_order', array(
            'label'       => __('Ordre des éléments', 'mj-member'),
            'description' => __('Définissez la position (1 = gauche, valeurs élevées = droite).', 'mj-member'),
        ));

        $items = array(
            'logo'          => __('Logo', 'mj-member'),
            'nav'           => __('Menu principal', 'mj-member'),
            'spacer'        => __('Espace flexible (push)', 'mj-member'),
            'agenda'        => __('Agenda', 'mj-member'),
            'gestionnaire'  => __('Gestionnaire', 'mj-member'),
            'nextcloud'     => __('Nextcloud', 'mj-member'),
            'notifications' => __('Notifications', 'mj-member'),
            'account'       => __('Compte / Login', 'mj-member'),
        );
        $defaults = array(
            'logo'          => 1,
            'nav'           => 2,
            'spacer'        => 3,
            'agenda'        => 4,
            'gestionnaire'  => 5,
            'nextcloud'     => 6,
            'notifications' => 7,
            'account'       => 8,
        );

        foreach ($items as $key => $label) {
            $this->add_control('order_' . $key, array(
                'label'   => $label,
                'type'    => Controls_Manager::NUMBER,
                'min'     => 1,
                'max'     => 20,
                'default' => $defaults[$key],
            ));
        }

        $this->end_controls_section();

        // --- Section : Logo ---
        $this->start_controls_section('section_logo', array(
            'label' => __('Logo', 'mj-member'),
        ));

        $this->add_control('logo_enabled', array(
            'label'        => __('Afficher le logo', 'mj-member'),
            'type'         => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default'      => 'yes',
        ));

        $this->add_control('logo_type', array(
            'label'     => __('Source du logo', 'mj-member'),
            'type'      => Controls_Manager::SELECT,
            'options'   => array(
                'site'   => __('Logo du site (WordPress)', 'mj-member'),
                'custom' => __('Image personnalisée', 'mj-member'),
            ),
            'default'   => 'site',
            'condition' => array('logo_enabled' => 'yes'),
        ));

        $this->add_control('custom_logo', array(
            'label'       => __('Logo personnalisé', 'mj-member'),
            'type'        => Controls_Manager::MEDIA,
            'media_types' => array('image', 'svg'),
            'condition'   => array(
                'logo_enabled' => 'yes',
                'logo_type'    => 'custom',
            ),
        ));

        $this->add_control('logo_link', array(
            'label'         => __('Lien du logo', 'mj-member'),
            'type'          => Controls_Manager::URL,
            'placeholder'   => home_url('/'),
            'default'       => array('url' => home_url('/')),
            'condition'     => array('logo_enabled' => 'yes'),
        ));

        $this->end_controls_section();

        // --- Section : Menu principal ---
        $this->start_controls_section('section_nav', array(
            'label' => __('Menu principal', 'mj-member'),
        ));

        $this->add_control('nav_enabled', array(
            'label'        => __('Afficher le menu', 'mj-member'),
            'type'         => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default'      => 'yes',
        ));

        $this->add_control('nav_menu_id', array(
            'label'       => __('Menu WordPress', 'mj-member'),
            'type'        => Controls_Manager::SELECT,
            'options'     => $this->get_menu_options(),
            'default'     => '',
            'label_block' => true,
            'condition'   => array('nav_enabled' => 'yes'),
        ));

        $this->add_control('nav_show_submenus', array(
            'label'        => __('Afficher les sous-menus', 'mj-member'),
            'type'         => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default'      => 'yes',
            'condition'    => array('nav_enabled' => 'yes'),
        ));

        $this->end_controls_section();

        // --- Section : Agenda ---
        $this->start_controls_section('section_agenda', array(
            'label' => __('Agenda', 'mj-member'),
        ));

        $this->add_control('agenda_enabled', array(
            'label'        => __('Afficher l\'icône Agenda', 'mj-member'),
            'type'         => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default'      => 'yes',
        ));

        $this->add_control('agenda_url', array(
            'label'       => __('URL de l\'agenda', 'mj-member'),
            'type'        => Controls_Manager::URL,
            'placeholder' => home_url('/agenda'),
            'condition'   => array('agenda_enabled' => 'yes'),
        ));

        $this->add_control('agenda_label', array(
            'label'     => __('Libellé (infobulle)', 'mj-member'),
            'type'      => Controls_Manager::TEXT,
            'default'   => __('Agenda', 'mj-member'),
            'condition' => array('agenda_enabled' => 'yes'),
        ));

        $this->add_control('agenda_custom_icon', array(
            'label'       => __('Icône personnalisée', 'mj-member'),
            'type'        => Controls_Manager::MEDIA,
            'media_types' => array('image', 'svg'),
            'description' => __('Laissez vide pour utiliser l\'icône calendrier par défaut.', 'mj-member'),
            'condition'   => array('agenda_enabled' => 'yes'),
        ));

        $this->add_control('agenda_view_mode', array(
            'label'     => __('Mode d\'affichage', 'mj-member'),
            'type'      => Controls_Manager::SELECT,
            'options'   => array(
                'liste'      => __('Liste d\'événements', 'mj-member'),
                'calendrier' => __('Calendrier mensuel', 'mj-member'),
            ),
            'default'   => 'liste',
            'condition' => array('agenda_enabled' => 'yes'),
        ));

        $this->add_control('agenda_preview_enabled', array(
            'label'        => __('Aperçu des événements', 'mj-member'),
            'type'         => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default'      => 'yes',
            'condition'    => array('agenda_enabled' => 'yes', 'agenda_view_mode' => 'liste'),
        ));

        $this->add_control('agenda_limit', array(
            'label'     => __('Nombre d\'événements dans l\'aperçu', 'mj-member'),
            'type'      => Controls_Manager::NUMBER,
            'min'       => 1,
            'max'       => 10,
            'default'   => 5,
            'condition' => array('agenda_enabled' => 'yes', 'agenda_view_mode' => 'liste', 'agenda_preview_enabled' => 'yes'),
        ));

        $this->add_control('agenda_calendar_months_after', array(
            'label'     => __('Nombre de mois à afficher', 'mj-member'),
            'type'      => Controls_Manager::NUMBER,
            'min'       => 1,
            'max'       => 6,
            'default'   => 2,
            'condition' => array('agenda_enabled' => 'yes', 'agenda_view_mode' => 'calendrier'),
        ));

        $this->end_controls_section();

        // --- Section : Gestionnaire ---
        $this->start_controls_section('section_gestionnaire', array(
            'label' => __('Gestionnaire', 'mj-member'),
        ));

        $this->add_control('gestionnaire_enabled', array(
            'label'        => __('Afficher l\'icône Gestionnaire', 'mj-member'),
            'type'         => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default'      => 'yes',
        ));

        $this->add_control('gestionnaire_url', array(
            'label'       => __('URL du gestionnaire', 'mj-member'),
            'type'        => Controls_Manager::URL,
            'placeholder' => home_url('/gestionnaire'),
            'condition'   => array('gestionnaire_enabled' => 'yes'),
        ));

        $this->add_control('gestionnaire_label', array(
            'label'     => __('Libellé (infobulle)', 'mj-member'),
            'type'      => Controls_Manager::TEXT,
            'default'   => __('Gestionnaire', 'mj-member'),
            'condition' => array('gestionnaire_enabled' => 'yes'),
        ));

        $this->add_control('gestionnaire_custom_icon', array(
            'label'       => __('Icône personnalisée', 'mj-member'),
            'type'        => Controls_Manager::MEDIA,
            'media_types' => array('image', 'svg'),
            'condition'   => array('gestionnaire_enabled' => 'yes'),
        ));

        $this->add_control('gestionnaire_favorites_note', array(
            'type'            => Controls_Manager::RAW_HTML,
            'raw'             => '<div style="background:#f0f4ff;border-left:3px solid #6366f1;padding:8px 10px;border-radius:4px;font-size:12px;line-height:1.5;">'
                               . '⭐ <strong>' . __('Favoris dynamiques', 'mj-member') . '</strong><br>'
                               . __('Les membres et événements mis en favoris dans le gestionnaire s\'affichent automatiquement ici pour l\'utilisateur connecté.', 'mj-member')
                               . '</div>',
            'content_classes' => 'elementor-panel-alert',
            'condition'       => array('gestionnaire_enabled' => 'yes'),
        ));

        $this->end_controls_section();

        // --- Section : Nextcloud ---
        $this->start_controls_section('section_nextcloud', array(
            'label' => __('Nextcloud', 'mj-member'),
        ));

        $this->add_control('nextcloud_enabled', array(
            'label'        => __('Afficher l\'icône Nextcloud', 'mj-member'),
            'type'         => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default'      => 'yes',
        ));

        $this->add_control('nextcloud_url', array(
            'label'       => __('URL Nextcloud (base)', 'mj-member'),
            'type'        => Controls_Manager::URL,
            'placeholder' => 'https://nextcloud.example.com',
            'condition'   => array('nextcloud_enabled' => 'yes'),
        ));

$this->add_control('nextcloud_label', array(
            'label'     => __('Libellé (infobulle)', 'mj-member'),
            'type'      => Controls_Manager::TEXT,
            'default'   => __('Nextcloud', 'mj-member'),
            'condition' => array('nextcloud_enabled' => 'yes'),
        ));

        $this->add_control('nextcloud_custom_icon', array(
            'label'       => __('Icône personnalisée', 'mj-member'),
            'type'        => Controls_Manager::MEDIA,
            'media_types' => array('image', 'svg'),
            'condition'   => array('nextcloud_enabled' => 'yes'),
        ));

        $this->add_control('nextcloud_page_url', array(
            'label'       => __('Lien interne (page documents)', 'mj-member'),
            'description' => __('URL de la page WordPress contenant le widget documents. L\'URL de base Nextcloud ci-dessus est utilisée pour les appels API.', 'mj-member'),
            'type'        => Controls_Manager::URL,
            'placeholder' => home_url('/documents'),
            'condition'   => array('nextcloud_enabled' => 'yes'),
        ));

        $this->end_controls_section();

        // --- Section : Notifications ---
        $this->start_controls_section('section_notifications', array(
            'label' => __('Notifications', 'mj-member'),
        ));

        $this->add_control('notifications_enabled', array(
            'label'        => __('Afficher l\'icône Notifications', 'mj-member'),
            'type'         => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default'      => 'yes',
        ));

        $this->add_control('notifications_label', array(
            'label'     => __('Libellé (infobulle)', 'mj-member'),
            'type'      => Controls_Manager::TEXT,
            'default'   => __('Notifications', 'mj-member'),
            'condition' => array('notifications_enabled' => 'yes'),
        ));

        $this->add_control('notifications_custom_icon', array(
            'label'       => __('Icône personnalisée', 'mj-member'),
            'type'        => Controls_Manager::MEDIA,
            'media_types' => array('image', 'svg'),
            'condition'   => array('notifications_enabled' => 'yes'),
        ));

        $this->add_control('notifications_max_items', array(
            'label'     => __('Nombre max de notifications', 'mj-member'),
            'type'      => Controls_Manager::NUMBER,
            'min'       => 3,
            'max'       => 20,
            'default'   => 10,
            'condition' => array('notifications_enabled' => 'yes'),
        ));

        $this->add_control('notifications_auto_refresh', array(
            'label'        => __('Rafraîchissement auto du badge', 'mj-member'),
            'type'         => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default'      => 'yes',
            'condition'    => array('notifications_enabled' => 'yes'),
        ));

        $this->add_control('notifications_refresh_interval', array(
            'label'     => __('Intervalle (secondes)', 'mj-member'),
            'type'      => Controls_Manager::NUMBER,
            'min'       => 30,
            'max'       => 300,
            'default'   => 60,
            'condition' => array(
                'notifications_enabled'      => 'yes',
                'notifications_auto_refresh' => 'yes',
            ),
        ));

        $this->end_controls_section();

        // --- Section : Compte / Login ---
        $this->start_controls_section('section_account', array(
            'label' => __('Compte / Login', 'mj-member'),
        ));

        $this->add_control('account_enabled', array(
            'label'        => __('Afficher l\'icône Compte', 'mj-member'),
            'type'         => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default'      => 'yes',
        ));

        $this->add_control('account_custom_icon', array(
            'label'       => __('Icône personnalisée', 'mj-member'),
            'type'        => Controls_Manager::MEDIA,
            'media_types' => array('image', 'svg'),
            'condition'   => array('account_enabled' => 'yes'),
        ));

        $this->add_control('account_label_connected', array(
            'label'     => __('Infobulle (connecté)', 'mj-member'),
            'type'      => Controls_Manager::TEXT,
            'default'   => __('Mon compte', 'mj-member'),
            'condition' => array('account_enabled' => 'yes'),
        ));

        $this->add_control('account_label_disconnected', array(
            'label'     => __('Infobulle (déconnecté)', 'mj-member'),
            'type'      => Controls_Manager::TEXT,
            'default'   => __('Se connecter', 'mj-member'),
            'condition' => array('account_enabled' => 'yes'),
        ));

        $this->add_control('login_redirect_url', array(
            'label'       => __('Redirection après connexion', 'mj-member'),
            'type'        => Controls_Manager::URL,
            'placeholder' => home_url('/mon-compte'),
            'condition'   => array('account_enabled' => 'yes'),
        ));

        $this->add_control('account_links_note', array(
            'type'            => Controls_Manager::RAW_HTML,
            'raw'             => '<div style="background:#f0f4ff;border-left:3px solid #6366f1;padding:8px 10px;border-radius:4px;font-size:12px;line-height:1.5;">'
                               . '🔗 <strong>' . __('Liens Mon compte', 'mj-member') . '</strong><br>'
                               . __('Les liens sont configurés dans', 'mj-member') . ' <strong>MJ Member &gt; Paramètres &gt; 🔗 Liens « Mon compte »</strong>. '
                               . __('Ils tiennent compte des rôles et permissions de l\'utilisateur.', 'mj-member')
                               . '</div>',
            'content_classes' => 'elementor-panel-alert',
            'condition'       => array('account_enabled' => 'yes'),
        ));

        $this->add_control('account_logout_text', array(
            'label'     => __('Texte du lien de déconnexion', 'mj-member'),
            'type'      => Controls_Manager::TEXT,
            'default'   => __('Se déconnecter', 'mj-member'),
            'condition' => array('account_enabled' => 'yes'),
        ));

        $this->add_control('account_logout_url', array(
            'label'       => __('URL de déconnexion (laisser vide = auto)', 'mj-member'),
            'type'        => Controls_Manager::URL,
            'placeholder' => __('Auto (wp_logout_url)', 'mj-member'),
            'condition'   => array('account_enabled' => 'yes'),
        ));

        $this->add_control('account_register_url', array(
            'label'       => __('URL du bouton d\'inscription', 'mj-member'),
            'type'        => Controls_Manager::URL,
            'placeholder' => home_url('/mon-compte/inscription'),
            'condition'   => array('account_enabled' => 'yes'),
        ));

        $this->add_control('account_register_label', array(
            'label'     => __('Texte du bouton d\'inscription', 'mj-member'),
            'type'      => Controls_Manager::TEXT,
            'default'   => __('S\'inscrire', 'mj-member'),
            'condition' => array('account_enabled' => 'yes', 'account_register_url[url]!' => ''),
        ));

        $this->end_controls_section();

        // =====================================================================
        // TAB STYLE
        // =====================================================================

        // --- Section : En-tête ---
        $this->start_controls_section('style_header', array(
            'label' => __('En-tête', 'mj-member'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ));

        $this->add_control('header_bg_color', array(
            'label'     => __('Couleur de fond', 'mj-member'),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#ffffff',
            'selectors' => array(
                '{{WRAPPER}} .mj-header' => 'background-color: {{VALUE}};',
            ),
        ));

        $this->add_group_control(Group_Control_Background::get_type(), array(
            'name'     => 'header_background',
            'label'    => __('Arrière-plan', 'mj-member'),
            'types'    => array('classic', 'gradient', 'video'),
            'selector' => '{{WRAPPER}} .mj-header',
        ));

        $this->add_control('header_stuck_bg_color', array(
            'label'     => __('Couleur de fond (collé)', 'mj-member'),
            'type'      => Controls_Manager::COLOR,
            'condition' => array('sticky' => 'yes'),
            'selectors' => array(
                '{{WRAPPER}} .mj-header--stuck' => 'background-color: {{VALUE}};',
            ),
        ));

        $this->add_group_control(Group_Control_Background::get_type(), array(
            'name'      => 'header_stuck_background',
            'label'     => __('Arrière-plan (collé)', 'mj-member'),
            'types'     => array('classic', 'gradient'),
            'selector'  => '{{WRAPPER}} .mj-header--stuck',
            'condition' => array('sticky' => 'yes'),
        ));

        $this->add_responsive_control('header_padding', array(
            'label'      => __('Padding horizontal', 'mj-member'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => array('px'),
            'range'      => array('px' => array('min' => 0, 'max' => 80)),
            'default'    => array('unit' => 'px', 'size' => 20),
            'selectors'  => array(
                '{{WRAPPER}} .mj-header__inner' => 'padding-left: {{SIZE}}{{UNIT}}; padding-right: {{SIZE}}{{UNIT}};',
            ),
        ));

        $this->add_control('header_z_index', array(
            'label'     => __('Z-index', 'mj-member'),
            'type'      => Controls_Manager::NUMBER,
            'default'   => 100,
            'selectors' => array(
                '{{WRAPPER}} .mj-header' => 'z-index: {{VALUE}};',
            ),
        ));

        $this->add_group_control(Group_Control_Box_Shadow::get_type(), array(
            'name'     => 'header_box_shadow',
            'selector' => '{{WRAPPER}} .mj-header',
        ));

        $this->add_group_control(Group_Control_Border::get_type(), array(
            'name'     => 'header_border_bottom',
            'selector' => '{{WRAPPER}} .mj-header',
        ));

        $this->end_controls_section();

        // --- Section : Logo ---
        $this->start_controls_section('style_logo', array(
            'label' => __('Logo', 'mj-member'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ));

        $this->add_responsive_control('logo_max_height', array(
            'label'      => __('Hauteur max du logo', 'mj-member'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => array('px'),
            'range'      => array('px' => array('min' => 20, 'max' => 120)),
            'default'    => array('unit' => 'px', 'size' => 48),
            'selectors'  => array(
                '{{WRAPPER}} .mj-header__logo img' => 'max-height: {{SIZE}}{{UNIT}};',
            ),
        ));

        $this->end_controls_section();

        // --- Section : Menu ---
        $this->start_controls_section('style_nav', array(
            'label' => __('Menu principal', 'mj-member'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ));

        $this->add_control('nav_color', array(
            'label'     => __('Couleur des liens', 'mj-member'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .mj-header__nav a' => 'color: {{VALUE}};',
            ),
        ));

        $this->add_control('nav_hover_color', array(
            'label'     => __('Couleur au survol', 'mj-member'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .mj-header__nav a:hover' => 'color: {{VALUE}};',
                '{{WRAPPER}} .mj-header__nav .current-menu-item > a' => 'color: {{VALUE}};',
            ),
        ));

        $this->add_control('nav_hover_bg', array(
            'label'     => __('Fond au survol', 'mj-member'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .mj-header__nav a:hover' => 'background-color: {{VALUE}};',
            ),
        ));

        $this->add_group_control(Group_Control_Typography::get_type(), array(
            'name'     => 'nav_typography',
            'selector' => '{{WRAPPER}} .mj-header__nav a',
        ));

        $this->add_responsive_control('nav_item_gap', array(
            'label'      => __('Espace entre les items', 'mj-member'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => array('px'),
            'range'      => array('px' => array('min' => 0, 'max' => 32)),
            'default'    => array('unit' => 'px', 'size' => 4),
            'selectors'  => array(
                '{{WRAPPER}} .mj-header__nav ul' => 'gap: {{SIZE}}{{UNIT}};',
            ),
        ));

        $this->end_controls_section();

        // --- Section : Icônes d'action ---
        $this->start_controls_section('style_icons', array(
            'label' => __('Icônes d\'action', 'mj-member'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ));

        $this->add_responsive_control('icon_size', array(
            'label'      => __('Taille des icônes (surcharge)', 'mj-member'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => array('px'),
            'range'      => array('px' => array('min' => 14, 'max' => 48)),
            'selectors'  => array(
                '{{WRAPPER}} .mj-header' => '--mj-icon-size: {{SIZE}}{{UNIT}};',
            ),
        ));

        $this->add_responsive_control('icon_btn_size', array(
            'label'      => __('Taille du bouton icône (surcharge)', 'mj-member'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => array('px'),
            'range'      => array('px' => array('min' => 28, 'max' => 64)),
            'selectors'  => array(
                '{{WRAPPER}} .mj-header' => '--mj-trigger-size: {{SIZE}}{{UNIT}};',
            ),
        ));

        $this->add_control('icon_color', array(
            'label'     => __('Couleur', 'mj-member'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .mj-header__trigger' => 'color: {{VALUE}};',
                '{{WRAPPER}} .mj-header__trigger svg' => 'fill: {{VALUE}};',
            ),
        ));

        $this->add_control('icon_hover_color', array(
            'label'     => __('Couleur au survol / actif', 'mj-member'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .mj-header__trigger:hover' => 'color: {{VALUE}};',
                '{{WRAPPER}} .mj-header__trigger[aria-expanded="true"]' => 'color: {{VALUE}};',
                '{{WRAPPER}} .mj-header__trigger:hover svg' => 'fill: {{VALUE}};',
                '{{WRAPPER}} .mj-header__trigger[aria-expanded="true"] svg' => 'fill: {{VALUE}};',
            ),
        ));

        $this->add_control('icon_bg', array(
            'label'     => __('Fond du bouton', 'mj-member'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .mj-header__trigger' => 'background-color: {{VALUE}};',
            ),
        ));

        $this->add_control('icon_hover_bg', array(
            'label'     => __('Fond au survol / actif', 'mj-member'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .mj-header__trigger:hover' => 'background-color: {{VALUE}};',
                '{{WRAPPER}} .mj-header__trigger[aria-expanded="true"]' => 'background-color: {{VALUE}};',
            ),
        ));

        $this->add_responsive_control('icon_border_radius', array(
            'label'      => __('Rayon (border-radius)', 'mj-member'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => array('px', '%'),
            'range'      => array('px' => array('min' => 0, 'max' => 50)),
            'default'    => array('unit' => 'px', 'size' => 8),
            'selectors'  => array(
                '{{WRAPPER}} .mj-header__trigger' => 'border-radius: {{SIZE}}{{UNIT}};',
            ),
        ));

        $this->add_control('badge_bg_color', array(
            'label'     => __('Couleur fond badge', 'mj-member'),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#ef4444',
            'selectors' => array(
                '{{WRAPPER}} .mj-header__trigger-badge' => 'background-color: {{VALUE}};',
            ),
        ));

        $this->add_control('badge_text_color', array(
            'label'     => __('Couleur texte badge', 'mj-member'),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#ffffff',
            'selectors' => array(
                '{{WRAPPER}} .mj-header__trigger-badge' => 'color: {{VALUE}};',
            ),
        ));

        $this->end_controls_section();

        // --- Section : Dropdowns ---
        $this->start_controls_section('style_dropdown', array(
            'label' => __('Dropdowns (onglets)', 'mj-member'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ));

        $this->add_responsive_control('dropdown_width', array(
            'label'      => __('Largeur', 'mj-member'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => array('px'),
            'range'      => array('px' => array('min' => 200, 'max' => 600)),
            'default'    => array('unit' => 'px', 'size' => 320),
            'selectors'  => array(
                '{{WRAPPER}} .mj-header-dropdown' => 'width: {{SIZE}}{{UNIT}};',
            ),
        ));

        $this->add_responsive_control('dropdown_max_height', array(
            'label'      => __('Hauteur max', 'mj-member'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => array('px'),
            'range'      => array('px' => array('min' => 150, 'max' => 700)),
            'default'    => array('unit' => 'px', 'size' => 480),
            'selectors'  => array(
                '{{WRAPPER}} .mj-header-dropdown' => 'max-height: {{SIZE}}{{UNIT}};',
            ),
        ));

        $this->add_control('dropdown_bg_color', array(
            'label'     => __('Couleur de fond', 'mj-member'),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#ffffff',
            'selectors' => array(
                '{{WRAPPER}} .mj-header-dropdown' => 'background-color: {{VALUE}};',
            ),
        ));

        $this->add_responsive_control('dropdown_radius', array(
            'label'      => __('Arrondi (border-radius)', 'mj-member'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => array('px'),
            'range'      => array('px' => array('min' => 0, 'max' => 32)),
            'default'    => array('unit' => 'px', 'size' => 12),
            'selectors'  => array(
                '{{WRAPPER}} .mj-header-dropdown' => 'border-radius: {{SIZE}}{{UNIT}};',
            ),
        ));

        $this->add_group_control(Group_Control_Box_Shadow::get_type(), array(
            'name'     => 'dropdown_shadow',
            'selector' => '{{WRAPPER}} .mj-header-dropdown',
        ));

        $this->add_group_control(Group_Control_Border::get_type(), array(
            'name'     => 'dropdown_border',
            'selector' => '{{WRAPPER}} .mj-header-dropdown',
        ));

        $this->add_control('dropdown_header_bg', array(
            'label'     => __('Fond de l\'entête du dropdown', 'mj-member'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .mj-header-dropdown__header' => 'background-color: {{VALUE}};',
            ),
        ));

        $this->add_control('dropdown_header_text', array(
            'label'     => __('Couleur titre du dropdown', 'mj-member'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .mj-header-dropdown__title' => 'color: {{VALUE}};',
            ),
        ));

        $this->end_controls_section();

        $this->register_visibility_controls();
    }

    // -------------------------------------------------------------------------
    // Render
    // -------------------------------------------------------------------------

    protected function render() {
        $settings = $this->get_settings_for_display();
        $this->apply_visibility_to_wrapper($settings, 'mj-header');

        $is_preview = function_exists('mj_member_login_component_is_preview_mode')
            ? mj_member_login_component_is_preview_mode()
            : (
                (did_action('elementor/loaded') && isset(\Elementor\Plugin::$instance->editor) && \Elementor\Plugin::$instance->editor->is_edit_mode())
                || isset($_GET['elementor-preview']) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            );

        // Member / user data
        $is_logged_in = is_user_logged_in();
        $member       = null;
        $member_id    = 0;
        $unread_count = 0;

        if ($is_logged_in && function_exists('mj_member_get_current_member')) {
            $member = mj_member_get_current_member();
            if ($member && isset($member->id)) {
                $member_id = (int) $member->id;
            }
        }

        if ($member_id > 0 && !$is_preview) {
            if (function_exists('mj_member_get_member_unread_notifications_count')) {
                $unread_count = mj_member_get_member_unread_notifications_count($member_id);
            }
        } elseif ($is_preview) {
            $is_logged_in = true; // show logged-in state in preview
            $member_id    = 1;
            $unread_count = 3;
        }

        // Sticky class
        $sticky        = !empty($settings['sticky']) && $settings['sticky'] === 'yes';
        $sticky_class  = $sticky ? 'mj-header--sticky' : '';

        // Build ordered items list
        $item_keys = array('logo', 'nav', 'spacer', 'agenda', 'gestionnaire', 'nextcloud', 'notifications', 'account');
        $ordered_items = array();
        foreach ($item_keys as $key) {
            $order = isset($settings['order_' . $key]) ? (int) $settings['order_' . $key] : 10;
            $ordered_items[] = array('key' => $key, 'order' => $order);
        }
        usort($ordered_items, function ($a, $b) { return $a['order'] - $b['order']; });

        // Logo
        $logo_enabled   = !empty($settings['logo_enabled']) && $settings['logo_enabled'] === 'yes';
        $logo_type      = $settings['logo_type'] ?? 'site';
        $logo_url       = '';
        if ($logo_type === 'site') {
            $logo_id = get_theme_mod('custom_logo');
            if ($logo_id) {
                $logo_img = wp_get_attachment_image_src($logo_id, 'full');
                $logo_url = $logo_img ? $logo_img[0] : '';
            }
        } elseif (!empty($settings['custom_logo']['url'])) {
            $logo_url = $settings['custom_logo']['url'];
        }
        $logo_link = !empty($settings['logo_link']['url']) ? $settings['logo_link']['url'] : home_url('/');

        // Nav menu
        $nav_enabled      = !empty($settings['nav_enabled']) && $settings['nav_enabled'] === 'yes';
        $nav_menu_id      = !empty($settings['nav_menu_id']) ? (int) $settings['nav_menu_id'] : 0;
        $nav_show_sub     = !empty($settings['nav_show_submenus']) && $settings['nav_show_submenus'] === 'yes';

        // Agenda
        $agenda_enabled          = !empty($settings['agenda_enabled']) && $settings['agenda_enabled'] === 'yes';
        $agenda_url              = !empty($settings['agenda_url']['url']) ? $settings['agenda_url']['url'] : '#';
        $agenda_label            = $settings['agenda_label'] ?? __('Agenda', 'mj-member');
        $agenda_custom_icon      = $settings['agenda_custom_icon']['url'] ?? '';
        $agenda_view_mode        = $settings['agenda_view_mode'] ?? 'liste';
        $agenda_preview_enabled  = !empty($settings['agenda_preview_enabled']) && $settings['agenda_preview_enabled'] === 'yes';
        $agenda_limit            = isset($settings['agenda_limit']) ? (int) $settings['agenda_limit'] : 5;
        $agenda_calendar_months  = isset($settings['agenda_calendar_months_after']) ? max(1, (int) $settings['agenda_calendar_months_after']) : 2;

        // Gestionnaire — visible only for users with mj_manage_members capability (Animateurs, Coordinateurs)
        $gest_enabled     = !empty($settings['gestionnaire_enabled']) && $settings['gestionnaire_enabled'] === 'yes'
                            && ($is_preview || current_user_can(Config::capability()));
        $gest_url         = !empty($settings['gestionnaire_url']['url']) ? $settings['gestionnaire_url']['url'] : home_url('/gestion-evenement/');
        $gest_label       = $settings['gestionnaire_label'] ?? __('Gestionnaire', 'mj-member');
        $gest_custom_icon = $settings['gestionnaire_custom_icon']['url'] ?? '';

        // Load gestionnaire favorites from user meta (like account_menu_mobile)
        $gest_fav_member_items = array();
        $gest_fav_event_items  = array();

        $wp_user_id = get_current_user_id();
        if (!$is_preview && $wp_user_id > 0) {
            $raw_fav_members = get_user_meta($wp_user_id, '_mj_regmgr_fav_members', true);
            $raw_fav_events  = get_user_meta($wp_user_id, '_mj_regmgr_fav_events', true);

            $fav_member_ids = is_array($raw_fav_members)
                ? array_values(array_unique(array_filter(array_map('intval', $raw_fav_members), static function ($id) { return $id > 0; })))
                : array();
            $fav_event_ids = is_array($raw_fav_events)
                ? array_values(array_unique(array_filter(array_map('intval', $raw_fav_events), static function ($id) { return $id > 0; })))
                : array();

            foreach (array_slice($fav_member_ids, 0, 8) as $fav_id) {
                $m = MjMembers::getById($fav_id);
                if (!$m) continue;
                $first = trim((string) $m->get('first_name', ''));
                $last  = trim((string) $m->get('last_name', ''));
                $label = trim($first . ' ' . $last) ?: ((string) $m->get('nickname', '')) ?: sprintf(__('Membre #%d', 'mj-member'), $fav_id);
                $avatar_url = '';
                $avatar_id  = (int) $m->get('photo_id', 0);
                if ($avatar_id > 0) {
                    $candidate = wp_get_attachment_image_url($avatar_id, 'thumbnail');
                    if (is_string($candidate) && $candidate !== '') {
                        $avatar_url = esc_url_raw($candidate);
                    }
                }
                $parts    = is_array(preg_split('/\s+/', $label)) ? preg_split('/\s+/', $label) : array();
                $initials = '';
                foreach ($parts as $part) {
                    $part = trim((string) $part);
                    if ($part !== '') { $initials .= strtoupper(substr($part, 0, 1)); }
                    if (strlen($initials) >= 2) break;
                }
                if ($initials === '') $initials = 'M';
                $gest_fav_member_items[] = array(
                    'label'      => $label,
                    'url'        => add_query_arg(array('main-tab' => 'member', 'member' => $fav_id), $gest_url),
                    'avatar_url' => $avatar_url,
                    'initials'   => $initials,
                    'tab_urls'   => array(
                        'information'   => add_query_arg(array('main-tab' => 'member', 'member' => $fav_id, 'tab' => 'information'), $gest_url),
                        'dyndata'       => add_query_arg(array('main-tab' => 'member', 'member' => $fav_id, 'tab' => 'dyndata'), $gest_url),
                        'membership'    => add_query_arg(array('main-tab' => 'member', 'member' => $fav_id, 'tab' => 'membership'), $gest_url),
                        'badges'        => add_query_arg(array('main-tab' => 'member', 'member' => $fav_id, 'tab' => 'badges'), $gest_url),
                        'photos'        => add_query_arg(array('main-tab' => 'member', 'member' => $fav_id, 'tab' => 'photos'), $gest_url),
                        'ideas'         => add_query_arg(array('main-tab' => 'member', 'member' => $fav_id, 'tab' => 'ideas'), $gest_url),
                        'messages'      => add_query_arg(array('main-tab' => 'member', 'member' => $fav_id, 'tab' => 'messages'), $gest_url),
                        'notifications' => add_query_arg(array('main-tab' => 'member', 'member' => $fav_id, 'tab' => 'notifications'), $gest_url),
                        'testimonials'  => add_query_arg(array('main-tab' => 'member', 'member' => $fav_id, 'tab' => 'testimonials'), $gest_url),
                        'notes'         => add_query_arg(array('main-tab' => 'member', 'member' => $fav_id, 'tab' => 'notes'), $gest_url),
                        'history'       => add_query_arg(array('main-tab' => 'member', 'member' => $fav_id, 'tab' => 'history'), $gest_url),
                    ),
                );
            }

            foreach (array_slice($fav_event_ids, 0, 8) as $fav_id) {
                $e = MjEvents::find($fav_id);
                if (!$e) continue;
                $label = trim((string) $e->get('title', '')) ?: sprintf(__('Événement #%d', 'mj-member'), $fav_id);
                $emoji = trim((string) $e->get('emoji', '')) ?: '📅';
                $event_page_url = '';
                $article_id = (int) $e->get('article_id', 0);
                if ($article_id > 0) {
                    $permalink = get_permalink($article_id);
                    if (is_string($permalink) && $permalink !== '') {
                        $event_page_url = $permalink;
                    }
                }

                $filtered_event_page_url = apply_filters('mj_member_event_permalink', '', $e);
                if (is_string($filtered_event_page_url) && $filtered_event_page_url !== '') {
                    $event_page_url = $filtered_event_page_url;
                }

                if ($event_page_url === '') {
                    $slug = trim((string) $e->get('slug', ''));
                    if ($slug !== '') {
                        $event_page_url = home_url('/evenement/' . rawurlencode($slug) . '/');
                    } else {
                        $event_page_url = home_url('/evenement/?id=' . $fav_id);
                    }
                }
                $gest_fav_event_items[] = array(
                    'label'    => $label,
                    'url'      => add_query_arg(array('main-tab' => 'event', 'event_id' => $fav_id), $gest_url),
                    'emoji'    => $emoji,
                    'tab_urls' => array(
                        'event-page'       => $event_page_url,
                        'registrations'    => add_query_arg(array('main-tab' => 'event', 'event_id' => $fav_id, 'tab' => 'registrations'), $gest_url),
                        'attendance'       => add_query_arg(array('main-tab' => 'event', 'event_id' => $fav_id, 'tab' => 'attendance'), $gest_url),
                        'description'      => add_query_arg(array('main-tab' => 'event', 'event_id' => $fav_id, 'tab' => 'description'), $gest_url),
                        'regdoc'           => add_query_arg(array('main-tab' => 'event', 'event_id' => $fav_id, 'tab' => 'regdoc'), $gest_url),
                        'publish'          => add_query_arg(array('main-tab' => 'event', 'event_id' => $fav_id, 'tab' => 'publish'), $gest_url),
                        'details'          => add_query_arg(array('main-tab' => 'event', 'event_id' => $fav_id, 'tab' => 'details'), $gest_url),
                        'photos'           => add_query_arg(array('main-tab' => 'event', 'event_id' => $fav_id, 'tab' => 'photos'), $gest_url),
                        'documents'        => add_query_arg(array('main-tab' => 'event', 'event_id' => $fav_id, 'tab' => 'documents'), $gest_url),
                        'occurrence-encoder' => add_query_arg(array('main-tab' => 'event', 'event_id' => $fav_id, 'tab' => 'occurrence-encoder'), $gest_url),
                        'editor'           => add_query_arg(array('main-tab' => 'event', 'event_id' => $fav_id, 'tab' => 'editor'), $gest_url),
                    ),
                );
            }
        }

        if ($is_preview && empty($gest_fav_member_items) && empty($gest_fav_event_items)) {
            $gest_fav_member_items = array(
                array('label' => __('Membre exemple 1', 'mj-member'), 'url' => '#', 'avatar_url' => '', 'initials' => 'ME', 'tab_urls' => array('information' => '#', 'dyndata' => '#', 'membership' => '#', 'badges' => '#', 'photos' => '#', 'ideas' => '#', 'messages' => '#', 'notifications' => '#', 'testimonials' => '#', 'notes' => '#', 'history' => '#')),
                array('label' => __('Membre exemple 2', 'mj-member'), 'url' => '#', 'avatar_url' => '', 'initials' => 'ME', 'tab_urls' => array('information' => '#', 'dyndata' => '#', 'membership' => '#', 'badges' => '#', 'photos' => '#', 'ideas' => '#', 'messages' => '#', 'notifications' => '#', 'testimonials' => '#', 'notes' => '#', 'history' => '#')),
            );
            $gest_fav_event_items = array(
                array('label' => __('Événement exemple 1', 'mj-member'), 'url' => '#', 'emoji' => '🎯', 'tab_urls' => array('event-page' => '#', 'registrations' => '#', 'attendance' => '#', 'description' => '#', 'regdoc' => '#', 'publish' => '#', 'details' => '#', 'photos' => '#', 'documents' => '#', 'occurrence-encoder' => '#', 'editor' => '#')),
                array('label' => __('Événement exemple 2', 'mj-member'), 'url' => '#', 'emoji' => '🎨', 'tab_urls' => array('event-page' => '#', 'registrations' => '#', 'attendance' => '#', 'description' => '#', 'regdoc' => '#', 'publish' => '#', 'details' => '#', 'photos' => '#', 'documents' => '#', 'occurrence-encoder' => '#', 'editor' => '#')),
            );
        }

        // Nextcloud — visible seulement si l'utilisateur a un login Nextcloud dans la table membres
        $nc_enabled     = !empty($settings['nextcloud_enabled']) && $settings['nextcloud_enabled'] === 'yes';
        if ($nc_enabled && !$is_preview) {
            $nc_member_login = $member ? trim((string) $member->get('member_nextcloud_login', '')) : '';
            if ($nc_member_login === '') {
                $nc_enabled = false;
            }
        }
        $nc_url         = !empty($settings['nextcloud_url']['url']) ? $settings['nextcloud_url']['url'] : '';
        $nc_page_url    = !empty($settings['nextcloud_page_url']['url']) ? $settings['nextcloud_page_url']['url'] : '';
        $nc_label       = $settings['nextcloud_label'] ?? __('Nextcloud', 'mj-member');
        $nc_custom_icon = $settings['nextcloud_custom_icon']['url'] ?? '';
        $nc_login       = $member ? trim((string) $member->get('member_nextcloud_login', '')) : '';
        $nc_password    = $member ? trim((string) $member->get('member_nextcloud_password', '')) : '';

        // Notifications
        $notif_enabled   = !empty($settings['notifications_enabled']) && $settings['notifications_enabled'] === 'yes';
        $notif_label     = $settings['notifications_label'] ?? __('Notifications', 'mj-member');
        $notif_icon      = $settings['notifications_custom_icon']['url'] ?? '';
        $notif_max       = isset($settings['notifications_max_items']) ? (int) $settings['notifications_max_items'] : 10;
        $notif_auto_ref  = !empty($settings['notifications_auto_refresh']) && $settings['notifications_auto_refresh'] === 'yes';
        $notif_interval  = isset($settings['notifications_refresh_interval']) ? (int) $settings['notifications_refresh_interval'] : 60;

        // Account
        $acc_enabled    = !empty($settings['account_enabled']) && $settings['account_enabled'] === 'yes';
        $acc_icon       = $settings['account_custom_icon']['url'] ?? '';
        $acc_label_in   = $settings['account_label_connected'] ?? __('Mon compte', 'mj-member');
        $acc_label_out  = $settings['account_label_disconnected'] ?? __('Se connecter', 'mj-member');
        $acc_logout_txt = $settings['account_logout_text'] ?? __('Se déconnecter', 'mj-member');
        $acc_logout_url = !empty($settings['account_logout_url']['url'])
            ? $settings['account_logout_url']['url']
            : wp_logout_url(home_url('/'));
        $login_redirect    = !empty($settings['login_redirect_url']['url'])
            ? $settings['login_redirect_url']['url']
            : home_url('/mon-compte');
        $acc_register_url   = !empty($settings['account_register_url']['url'])
            ? $settings['account_register_url']['url']
            : '';
        $acc_register_label = !empty($settings['account_register_label'])
            ? $settings['account_register_label']
            : __('S\'inscrire', 'mj-member');

        // Account links — sections depuis la config plugin (MJ Member > Paramètres > Liens Mon compte)
        $acc_link_sections = array();
        if (function_exists('mj_member_login_component_get_account_links_with_sections')) {
            $acc_link_sections = mj_member_login_component_get_account_links_with_sections(
                $login_redirect,
                array('preview_mode' => $is_preview)
            );
            // Résoudre l'URL de déconnexion dans les sections
            foreach ($acc_link_sections as &$_section) {
                if (empty($_section['links'])) continue;
                foreach ($_section['links'] as &$_lnk) {
                    if (!empty($_lnk['is_logout'])) {
                        $_lnk['url'] = esc_url(wp_logout_url($login_redirect));
                    }
                }
                unset($_lnk);
            }
            unset($_section);
        } else {
            // Fallback : liens plats si la fonction n'est pas encore chargée
            $flat = MjAccountLinks::getLinks($login_redirect, array('preview_mode' => $is_preview));
            if (!empty($flat)) {
                $acc_link_sections = array(array('key' => 'default', 'label' => '', 'links' => $flat));
            }
        }

        // Avatar de l'utilisateur courant — même logique que le widget Login
        $acc_avatar_url = '';
        if ($is_logged_in && !$is_preview && function_exists('mj_member_login_component_get_member_avatar')) {
            $avatar_data    = mj_member_login_component_get_member_avatar(wp_get_current_user(), $member);
            $acc_avatar_url = $avatar_data['url'] ?? '';
        }

        // Rôle de l'utilisateur pour l'en-tête du panneau Mon Compte
        $acc_member_role_label = '';
        if ($is_logged_in && !$is_preview && $member && isset($member->role) && $member->role !== '') {
            $acc_member_role_label = strtoupper(MjRoles::getRoleLabel(sanitize_key((string) $member->role)));
        } elseif ($is_preview) {
            $acc_member_role_label = strtoupper(MjRoles::getRoleLabel(MjRoles::ANIMATEUR));
        }

        AssetsManager::requirePackage('header-widget');
        if ($agenda_enabled && $agenda_view_mode === 'calendrier') {
            AssetsManager::requirePackage('events-calendar');
        }

        $widget_id = $this->get_id();

        $config = array(
            'ajaxUrl'              => admin_url('admin-ajax.php'),
            'headerNonce'          => wp_create_nonce('mj-header-widget'),
            'notifNonce'           => wp_create_nonce('mj-notification-bell'),
            'loginNonce'           => wp_create_nonce('mj-member-event-register'),
            'memberId'             => $member_id,
            'sticky'               => $sticky,
            'scrollOffset'         => 20,
            'notifMaxItems'        => $notif_max,
            'notifAutoRefresh'     => $notif_auto_ref,
            'notifRefreshInterval' => $notif_interval * 1000,
            'agendaLimit'          => $agenda_limit,
            'ncUrl'                => esc_url($nc_url),
            'ncPageUrl'            => esc_url($nc_page_url),
            'ncLogin'              => esc_html($nc_login),
            'ncPassword'           => $nc_password,
            'loginRedirect'        => esc_url($login_redirect),
            'loginBtnText'         => esc_html($acc_label_out),
            'isPreview'            => $is_preview,
            'isLoggedIn'           => $is_logged_in,
        );

        // DEBUG TEMPORAIRE — supprimer après diagnostic
        echo '<!-- MJ_HEADER_DEBUG'
            . ' is_preview=' . ($is_preview ? '1' : '0')
            . ' is_logged_in=' . ($is_logged_in ? '1' : '0')
            . ' member_id=' . $member_id
            . ' acc_icon=' . ($acc_icon ? htmlspecialchars($acc_icon) : 'empty')
            . ' acc_avatar_url=' . ($acc_avatar_url ?: 'empty')
            . ' photo_id=' . ($member ? (int) $member->get('photo_id', 0) : 'no_member')
            . ' -->';

        include Config::path() . 'includes/templates/elementor/header_widget.php';
    }
}
