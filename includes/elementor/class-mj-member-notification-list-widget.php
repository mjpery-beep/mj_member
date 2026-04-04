<?php
/**
 * Widget Elementor - Liste de notifications
 *
 * Affiche les notifications sous forme de liste avec filtre par type
 * et actions marquer lu / supprimer.
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

class Mj_Member_Elementor_Notification_List_Widget extends Widget_Base {
    use Mj_Member_Elementor_Widget_Visibility;

    private function get_notification_type_options() {
        return array(
            'event_registration_created' => __('Inscription evenement creee', 'mj-member'),
            'event_registration_cancelled' => __('Inscription evenement annulee', 'mj-member'),
            'event_reminder' => __('Rappel evenement', 'mj-member'),
            'event_new_published' => __('Nouvel evenement publie', 'mj-member'),
            'payment_completed' => __('Paiement confirme', 'mj-member'),
            'payment_reminder' => __('Rappel paiement', 'mj-member'),
            'member_created' => __('Membre cree', 'mj-member'),
            'member_profile_updated' => __('Profil membre mis a jour', 'mj-member'),
            'profile_updated' => __('Profil mis a jour', 'mj-member'),
            'photo_uploaded' => __('Photo envoyee', 'mj-member'),
            'photo_approved' => __('Photo approuvee', 'mj-member'),
            'idea_published' => __('Idee publiee', 'mj-member'),
            'idea_voted' => __('Vote sur idee', 'mj-member'),
            'trophy_earned' => __('Trophee obtenu', 'mj-member'),
            'badge_earned' => __('Badge obtenu', 'mj-member'),
            'criterion_earned' => __('Critere valide', 'mj-member'),
            'level_up' => __('Niveau superieur', 'mj-member'),
            'action_awarded' => __('Action recompensee', 'mj-member'),
            'avatar_applied' => __('Avatar applique', 'mj-member'),
            'attendance_recorded' => __('Presence enregistree', 'mj-member'),
            'message_received' => __('Message recu', 'mj-member'),
            'todo_assigned' => __('Todo assigne', 'mj-member'),
            'todo_note_added' => __('Note todo ajoutee', 'mj-member'),
            'todo_media_added' => __('Media todo ajoute', 'mj-member'),
            'todo_completed' => __('Todo termine', 'mj-member'),
            'testimonial_approved' => __('Temoignage approuve', 'mj-member'),
            'testimonial_rejected' => __('Temoignage refuse', 'mj-member'),
            'testimonial_reaction' => __('Reaction temoignage', 'mj-member'),
            'testimonial_comment' => __('Commentaire temoignage', 'mj-member'),
            'testimonial_comment_reply' => __('Reponse commentaire temoignage', 'mj-member'),
            'testimonial_new_pending' => __('Nouveau temoignage en attente', 'mj-member'),
            'leave_request_created' => __('Demande de conge creee', 'mj-member'),
            'leave_request_approved' => __('Demande de conge approuvee', 'mj-member'),
            'leave_request_rejected' => __('Demande de conge rejetee', 'mj-member'),
            'expense_created' => __('Note de frais creee', 'mj-member'),
            'expense_reimbursed' => __('Note de frais remboursee', 'mj-member'),
            'expense_rejected' => __('Note de frais rejetee', 'mj-member'),
            'mileage_created' => __('Frais kilometrique cree', 'mj-member'),
            'mileage_approved' => __('Frais kilometrique approuve', 'mj-member'),
            'mileage_reimbursed' => __('Frais kilometrique rembourse', 'mj-member'),
            'employee_document_uploaded' => __('Document employe depose', 'mj-member'),
            'post_published' => __('Publication publiee', 'mj-member'),
            'info' => __('Information', 'mj-member'),
        );
    }

    private function normalize_allowed_types($raw_types) {
        if (!is_array($raw_types)) {
            return array();
        }

        $available_types = array_keys($this->get_notification_type_options());
        $normalized = array();

        foreach ($raw_types as $type) {
            $type = sanitize_key((string) $type);
            if ($type === '' || !in_array($type, $available_types, true)) {
                continue;
            }
            $normalized[$type] = true;
        }

        return array_keys($normalized);
    }

    private function filter_notifications_by_types(array $notifications, array $allowed_types) {
        if (empty($allowed_types)) {
            return $notifications;
        }

        $allowed_map = array_fill_keys($allowed_types, true);

        return array_values(array_filter($notifications, static function ($item) use ($allowed_map) {
            $notification = isset($item['notification']) && is_array($item['notification']) ? $item['notification'] : array();
            $type = isset($notification['type']) ? sanitize_key((string) $notification['type']) : 'info';

            return isset($allowed_map[$type]);
        }));
    }

    public function get_name() {
        return 'mj-member-notification-list';
    }

    public function get_title() {
        return __('Liste Notifications MJ', 'mj-member');
    }

    public function get_icon() {
        return 'eicon-post-list';
    }

    public function get_categories() {
        return array('mj-member');
    }

    public function get_keywords() {
        return array('mj', 'notifications', 'liste', 'filtres', 'alertes');
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
                'label' => __('Titre', 'mj-member'),
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
            'allowed_types',
            array(
                'label' => __('Types de notifications a afficher', 'mj-member'),
                'type' => Controls_Manager::SELECT2,
                'multiple' => true,
                'label_block' => true,
                'options' => $this->get_notification_type_options(),
                'description' => __('Laissez vide pour afficher tous les types.', 'mj-member'),
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
                'label' => __('Rafraichissement automatique', 'mj-member'),
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

        $this->register_visibility_controls();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $this->apply_visibility_to_wrapper($settings, 'mj-notification-list-widget');

        $is_preview = function_exists('mj_member_login_component_is_preview_mode')
            ? mj_member_login_component_is_preview_mode()
            : (did_action('elementor/loaded') && isset(\Elementor\Plugin::$instance->editor) && \Elementor\Plugin::$instance->editor->is_edit_mode());

        $member = null;
        $member_id = 0;
        $notifications = array();
        $unread_count = 0;

        if (is_user_logged_in() && function_exists('mj_member_get_current_member')) {
            $member = mj_member_get_current_member();
            if ($member && isset($member->id)) {
                $member_id = (int) $member->id;
            }
        }

        if (!$member && !$is_preview) {
            return;
        }

        if ($is_preview) {
            $notifications = array(
                array(
                    'recipient_id' => 1,
                    'notification_id' => 1,
                    'recipient_status' => 'unread',
                    'notification' => array(
                        'title' => __('Inscription confirmée : Stage Vacances', 'mj-member'),
                        'excerpt' => __('Votre inscription a été validée.', 'mj-member'),
                        'type' => 'event_registration_created',
                        'created_at' => date('Y-m-d H:i:s', strtotime('-15 minutes')),
                    ),
                ),
                array(
                    'recipient_id' => 2,
                    'notification_id' => 2,
                    'recipient_status' => 'unread',
                    'notification' => array(
                        'title' => __('Paiement confirmé', 'mj-member'),
                        'excerpt' => __('Votre paiement de 15,00 EUR a été enregistré.', 'mj-member'),
                        'type' => 'payment_completed',
                        'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours')),
                    ),
                ),
                array(
                    'recipient_id' => 3,
                    'notification_id' => 3,
                    'recipient_status' => 'read',
                    'notification' => array(
                        'title' => __('Nouveau todo assigné', 'mj-member'),
                        'excerpt' => __('Une nouvelle tâche vous a été attribuée.', 'mj-member'),
                        'type' => 'todo_assigned',
                        'created_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
                    ),
                ),
            );
            $unread_count = 2;
        } elseif ($member_id > 0 && function_exists('mj_member_get_member_unread_notifications_count')) {
            $unread_count = (int) mj_member_get_member_unread_notifications_count($member_id);
        }

        $panel_title = isset($settings['panel_title']) ? $settings['panel_title'] : __('Notifications', 'mj-member');
        $empty_message = isset($settings['empty_message']) ? $settings['empty_message'] : __('Aucune notification pour le moment.', 'mj-member');
        $allowed_types = $this->normalize_allowed_types(isset($settings['allowed_types']) ? $settings['allowed_types'] : array());
        $show_mark_all = !empty($settings['show_mark_all_read']) && $settings['show_mark_all_read'] === 'yes';
        $auto_refresh = !empty($settings['auto_refresh']) && $settings['auto_refresh'] === 'yes';
        $refresh_interval = isset($settings['refresh_interval']) ? (int) $settings['refresh_interval'] : 60;

        if (!empty($notifications)) {
            $notifications = $this->filter_notifications_by_types($notifications, $allowed_types);
            if (empty($notifications)) {
                $unread_count = 0;
            } else {
                $unread_count = count(array_filter($notifications, static function ($item) {
                    return isset($item['recipient_status']) && $item['recipient_status'] === 'unread';
                }));
            }
        }

        AssetsManager::requirePackage('notification-list');

        $config = array(
            'memberId' => $member_id,
            'preview' => $is_preview,
            'hideWhenEmpty' => true,
            'panelTitle' => $panel_title,
            'emptyMessage' => $empty_message,
            'showMarkAllRead' => $show_mark_all,
            'autoRefresh' => $auto_refresh,
            'refreshInterval' => max(30, $refresh_interval) * 1000,
            'initialUnreadCount' => $unread_count,
            'initialNotifications' => $notifications,
            'allowedTypes' => $allowed_types,
        );

        $widget_id = $this->get_id();

        include Config::path() . 'includes/templates/elementor/notification_list.php';
    }
}
