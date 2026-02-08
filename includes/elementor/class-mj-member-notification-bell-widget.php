<?php
/**
 * Widget Elementor - Cloche de notifications
 *
 * Affiche une icône cloche avec un badge compteur des notifications non-lues
 * et un panneau déroulant avec la liste des notifications.
 *
 * @package MjMember
 */

if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Controls_Manager;
use Elementor\Widget_Base;
use Mj\Member\Core\AssetsManager;
use Mj\Member\Core\Config;
use Mj\Member\Classes\Crud\MjMembers;
use Mj\Member\Classes\MjNotificationManager;

class Mj_Member_Elementor_Notification_Bell_Widget extends Widget_Base {
    use Mj_Member_Elementor_Widget_Visibility;

    public function get_name() {
        return 'mj-member-notification-bell';
    }

    public function get_title() {
        return __('Cloche Notifications MJ', 'mj-member');
    }

    public function get_icon() {
        return 'eicon-bell';
    }

    public function get_categories() {
        return array('mj-member');
    }

    public function get_keywords() {
        return array('mj', 'notification', 'bell', 'cloche', 'alert', 'message');
    }

    protected function register_controls() {
        $this->start_controls_section(
            'section_content',
            array(
                'label' => __('Contenu', 'mj-member'),
            )
        );

        $this->add_control(
            'panel_title',
            array(
                'label' => __('Titre du panneau', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Notifications', 'mj-member'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'empty_message',
            array(
                'label' => __('Message si vide', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Aucune notification pour le moment.', 'mj-member'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'max_items',
            array(
                'label' => __('Nombre max affiché', 'mj-member'),
                'type' => Controls_Manager::NUMBER,
                'min' => 3,
                'max' => 20,
                'default' => 10,
            )
        );

        $this->add_control(
            'show_mark_all_read',
            array(
                'label' => __('Bouton "Tout marquer comme lu"', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
            )
        );

        $this->add_control(
            'auto_refresh',
            array(
                'label' => __('Rafraîchissement automatique', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
            )
        );

        $this->add_control(
            'refresh_interval',
            array(
                'label' => __('Intervalle (secondes)', 'mj-member'),
                'type' => Controls_Manager::NUMBER,
                'min' => 30,
                'max' => 300,
                'default' => 60,
                'condition' => array(
                    'auto_refresh' => 'yes',
                ),
            )
        );

        $this->end_controls_section();

        // Style section - Icône
        $this->start_controls_section(
            'section_style_bell',
            array(
                'label' => __('Icône', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'custom_icon',
            array(
                'label' => __('Icône personnalisée', 'mj-member'),
                'type' => Controls_Manager::MEDIA,
                'media_types' => array('image', 'svg'),
                'default' => array(
                    'url' => '',
                ),
                'description' => __('Laissez vide pour utiliser l\'icône cloche par défaut.', 'mj-member'),
            )
        );

        $this->add_control(
            'vertical_align',
            array(
                'label' => __('Alignement vertical', 'mj-member'),
                'type' => Controls_Manager::CHOOSE,
                'options' => array(
                    'flex-start' => array(
                        'title' => __('Haut', 'mj-member'),
                        'icon' => 'eicon-v-align-top',
                    ),
                    'center' => array(
                        'title' => __('Centre', 'mj-member'),
                        'icon' => 'eicon-v-align-middle',
                    ),
                    'flex-end' => array(
                        'title' => __('Bas', 'mj-member'),
                        'icon' => 'eicon-v-align-bottom',
                    ),
                ),
                'default' => 'center',
                'selectors' => array(
                    '{{WRAPPER}} .mj-notification-bell' => 'align-items: {{VALUE}};',
                ),
            )
        );

        $this->add_responsive_control(
            'icon_size',
            array(
                'label' => __('Taille', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array(
                    'px' => array(
                        'min' => 16,
                        'max' => 64,
                    ),
                ),
                'default' => array(
                    'unit' => 'px',
                    'size' => 26,
                ),
                'tablet_default' => array(
                    'unit' => 'px',
                    'size' => 24,
                ),
                'mobile_default' => array(
                    'unit' => 'px',
                    'size' => 22,
                ),
                'selectors' => array(
                    '{{WRAPPER}} .mj-notification-bell__icon' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                    '{{WRAPPER}} .mj-notification-bell__icon svg, {{WRAPPER}} .mj-notification-bell__icon img' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'icon_color',
            array(
                'label' => __('Couleur', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'default' => '#475569',
                'selectors' => array(
                    '{{WRAPPER}} .mj-notification-bell__icon' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .mj-notification-bell__icon svg' => 'fill: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'badge_bg_color',
            array(
                'label' => __('Couleur badge', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'default' => '#ef4444',
                'selectors' => array(
                    '{{WRAPPER}} .mj-notification-bell__badge' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'badge_text_color',
            array(
                'label' => __('Couleur texte badge', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => array(
                    '{{WRAPPER}} .mj-notification-bell__badge' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_section();

        $this->register_visibility_controls();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $this->apply_visibility_to_wrapper($settings, 'mj-notification-bell');

        $is_preview = function_exists('mj_member_login_component_is_preview_mode')
            ? mj_member_login_component_is_preview_mode()
            : (did_action('elementor/loaded') && isset(\Elementor\Plugin::$instance->editor) && \Elementor\Plugin::$instance->editor->is_edit_mode());

        $member = null;
        $member_id = 0;
        $unread_count = 0;
        $notifications = array();

        if (is_user_logged_in() && function_exists('mj_member_get_current_member')) {
            $member = mj_member_get_current_member();
            if ($member && isset($member->id)) {
                $member_id = (int) $member->id;
            }
        }

        if (!$member && !$is_preview) {
            // Ne rien afficher si pas connecté
            return;
        }

        // Données pour le preview Elementor - toujours afficher des données fictives
        if ($is_preview) {
            $unread_count = 3;
            $notifications = array(
                array(
                    'recipient_id' => 1,
                    'notification_id' => 1,
                    'recipient_status' => 'unread',
                    'notification' => array(
                        'title' => __('Inscription confirmée : Stage Vacances', 'mj-member'),
                        'excerpt' => __('Votre inscription a été enregistrée avec succès.', 'mj-member'),
                        'type' => 'event_registration_created',
                        'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours')),
                    ),
                ),
                array(
                    'recipient_id' => 2,
                    'notification_id' => 2,
                    'recipient_status' => 'unread',
                    'notification' => array(
                        'title' => __('Paiement confirmé', 'mj-member'),
                        'excerpt' => __('Votre paiement de 15,00 € a été confirmé.', 'mj-member'),
                        'type' => 'payment_completed',
                        'created_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
                    ),
                ),
                array(
                    'recipient_id' => 3,
                    'notification_id' => 3,
                    'recipient_status' => 'read',
                    'notification' => array(
                        'title' => __('Bienvenue à la MJ Péry !', 'mj-member'),
                        'excerpt' => __('Votre profil a été créé. Découvrez les activités !', 'mj-member'),
                        'type' => 'member_created',
                        'created_at' => date('Y-m-d H:i:s', strtotime('-3 days')),
                    ),
                ),
            );
        } elseif ($member_id > 0 && !$is_preview) {
            // Récupérer uniquement le count - les notifications seront chargées via AJAX à l'ouverture
            if (function_exists('mj_member_get_member_unread_notifications_count')) {
                $unread_count = mj_member_get_member_unread_notifications_count($member_id);
            }
        }

        $panel_title = isset($settings['panel_title']) ? $settings['panel_title'] : __('Notifications', 'mj-member');
        $empty_message = isset($settings['empty_message']) ? $settings['empty_message'] : __('Aucune notification pour le moment.', 'mj-member');
        $show_mark_all = !empty($settings['show_mark_all_read']) && $settings['show_mark_all_read'] === 'yes';
        $auto_refresh = !empty($settings['auto_refresh']) && $settings['auto_refresh'] === 'yes';
        $refresh_interval = isset($settings['refresh_interval']) ? (int) $settings['refresh_interval'] : 60;
        $custom_icon_url = !empty($settings['custom_icon']['url']) ? $settings['custom_icon']['url'] : '';

        AssetsManager::requirePackage('notification-bell');

        $config = array(
            'memberId' => $member_id,
            'unreadCount' => $unread_count,
            'autoRefresh' => $auto_refresh,
            'refreshInterval' => max(30, $refresh_interval) * 1000,
            'preview' => $is_preview,
        );

        $widget_id = $this->get_id();

        include Config::path() . 'includes/templates/elementor/notification_bell.php';
    }
}
