<?php
/**
 * AJAX Handlers pour le widget cloche de notifications
 *
 * @package MjMember
 */

namespace Mj\Member\Core\Ajax\Front;

use Mj\Member\Core\Contracts\AjaxHandlerInterface;
use Mj\Member\Classes\Crud\MjMembers;
use Mj\Member\Classes\MjNotificationManager;

if (!defined('ABSPATH')) {
    exit;
}

final class NotificationBellController implements AjaxHandlerInterface {

    public function registerHooks(): void {
        add_action('wp_ajax_mj_member_notification_bell_fetch', [$this, 'fetch']);
        add_action('wp_ajax_mj_member_notification_bell_count', [$this, 'count']);
        add_action('wp_ajax_mj_member_notification_bell_mark_read', [$this, 'markRead']);
        add_action('wp_ajax_mj_member_notification_bell_mark_all_read', [$this, 'markAllRead']);
        add_action('wp_ajax_mj_member_notification_bell_archive', [$this, 'archive']);
        add_action('wp_ajax_mj_member_notification_bell_archive_all', [$this, 'archiveAll']);
    }

    /**
     * Récupère les notifications pour le panneau
     */
    public function fetch() {
        check_ajax_referer('mj-notification-bell', 'nonce');

        $member_id = isset($_POST['member_id']) ? absint($_POST['member_id']) : 0;

        // Vérifier que le membre appartient bien à l'utilisateur connecté
        if ($member_id <= 0 || !$this->userOwnsMember($member_id)) {
            wp_send_json_error(array('message' => __('Accès non autorisé.', 'mj-member')));
        }

        $notifications = array();
        $unread_count = 0;

        if (function_exists('mj_member_get_member_notifications_feed')) {
            $notifications = mj_member_get_member_notifications_feed($member_id, array(
                'limit' => 15,
            ));
        }

        if (function_exists('mj_member_get_member_unread_notifications_count')) {
            $unread_count = mj_member_get_member_unread_notifications_count($member_id);
        }

        wp_send_json_success(array(
            'notifications' => $notifications,
            'unread_count' => $unread_count,
        ));
    }

    /**
     * Récupère uniquement le nombre de non-lues (pour polling)
     */
    public function count() {
        check_ajax_referer('mj-notification-bell', 'nonce');

        $member_id = isset($_POST['member_id']) ? absint($_POST['member_id']) : 0;

        if ($member_id <= 0 || !$this->userOwnsMember($member_id)) {
            wp_send_json_error(array('message' => __('Accès non autorisé.', 'mj-member')));
        }

        $unread_count = 0;
        if (function_exists('mj_member_get_member_unread_notifications_count')) {
            $unread_count = mj_member_get_member_unread_notifications_count($member_id);
        }

        wp_send_json_success(array(
            'unread_count' => $unread_count,
        ));
    }

    /**
     * Marque une notification comme lue
     */
    public function markRead() {
        check_ajax_referer('mj-notification-bell', 'nonce');

        $recipient_id = isset($_POST['recipient_id']) ? absint($_POST['recipient_id']) : 0;

        if ($recipient_id <= 0) {
            wp_send_json_error(array('message' => __('ID de notification invalide.', 'mj-member')));
        }

        // Vérifier que le recipient appartient bien à l'utilisateur connecté
        if (!$this->userOwnsRecipient($recipient_id)) {
            wp_send_json_error(array('message' => __('Accès non autorisé.', 'mj-member')));
        }

        $result = MjNotificationManager::mark_recipient_status(array($recipient_id), 'read');

        if ($result !== false && !is_wp_error($result)) {
            wp_send_json_success(array('marked' => true));
        } else {
            wp_send_json_error(array('message' => __('Impossible de marquer comme lu.', 'mj-member')));
        }
    }

    /**
     * Marque toutes les notifications d'un membre comme lues
     */
    public function markAllRead() {
        check_ajax_referer('mj-notification-bell', 'nonce');

        $member_id = isset($_POST['member_id']) ? absint($_POST['member_id']) : 0;

        if ($member_id <= 0 || !$this->userOwnsMember($member_id)) {
            wp_send_json_error(array('message' => __('Accès non autorisé.', 'mj-member')));
        }

        if (function_exists('mj_member_mark_member_notifications_read')) {
            $result = mj_member_mark_member_notifications_read($member_id);
            if ($result !== false) {
                wp_send_json_success(array('marked' => $result));
            }
        }

        wp_send_json_error(array('message' => __('Impossible de marquer comme lu.', 'mj-member')));
    }

    /**
     * Archive une notification (la supprime de la vue utilisateur)
     */
    public function archive() {
        check_ajax_referer('mj-notification-bell', 'nonce');

        $recipient_id = isset($_POST['recipient_id']) ? absint($_POST['recipient_id']) : 0;

        if ($recipient_id <= 0) {
            wp_send_json_error(array('message' => __('ID de notification invalide.', 'mj-member')));
        }

        // Vérifier que le recipient appartient bien à l'utilisateur connecté
        if (!$this->userOwnsRecipient($recipient_id)) {
            wp_send_json_error(array('message' => __('Accès non autorisé.', 'mj-member')));
        }

        // Archiver en mettant le statut à 'archived'
        $result = MjNotificationManager::mark_recipient_status(array($recipient_id), 'archived');

        if ($result !== false && !is_wp_error($result)) {
            wp_send_json_success(array('archived' => true));
        } else {
            wp_send_json_error(array('message' => __('Impossible d\'archiver la notification.', 'mj-member')));
        }
    }

    /**
     * Archive toutes les notifications d'un membre
     */
    public function archiveAll() {
        check_ajax_referer('mj-notification-bell', 'nonce');

        $member_id = isset($_POST['member_id']) ? absint($_POST['member_id']) : 0;

        if ($member_id <= 0 || !$this->userOwnsMember($member_id)) {
            wp_send_json_error(array('message' => __('Accès non autorisé.', 'mj-member')));
        }

        global $wpdb;
        $recipients_table = mj_member_get_notification_recipients_table_name();

        // Récupérer tous les recipient_ids du membre qui ne sont pas déjà archivés
        $recipient_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$recipients_table} 
             WHERE member_id = %d AND status != 'archived'",
            $member_id
        ));

        if (empty($recipient_ids)) {
            wp_send_json_success(array('archived' => 0));
        }

        // Archiver toutes les notifications
        $result = MjNotificationManager::mark_recipient_status($recipient_ids, 'archived');

        if ($result !== false && !is_wp_error($result)) {
            wp_send_json_success(array('archived' => count($recipient_ids)));
        } else {
            wp_send_json_error(array('message' => __('Impossible d\'archiver les notifications.', 'mj-member')));
        }
    }

    /**
     * Vérifie si l'utilisateur connecté possède le membre spécifié
     */
    private function userOwnsMember($member_id) {
        if (!is_user_logged_in()) {
            return false;
        }

        $user_id = get_current_user_id();

        // Vérifier si c'est le membre principal de l'utilisateur
        if (function_exists('mj_member_get_current_member')) {
            $current_member = mj_member_get_current_member();
            if ($current_member && (int) $current_member->id === (int) $member_id) {
                return true;
            }
        }

        // Vérifier si c'est un enfant du tuteur
        if (function_exists('mj_member_get_guardian_for_member')) {
            $guardian = mj_member_get_guardian_for_member($member_id);
            if ($guardian && (int) $guardian->user_id === $user_id) {
                return true;
            }
        }

        // Fallback : vérifier directement dans la base
        global $wpdb;
        $members_table = MjMembers::getTableName(MjMembers::TABLE_NAME);
        $member_user_id = $wpdb->get_var($wpdb->prepare(
            "SELECT wp_user_id FROM {$members_table} WHERE id = %d",
            $member_id
        ));

        return $member_user_id && (int) $member_user_id === $user_id;
    }

    /**
     * Vérifie si l'utilisateur connecté possède le recipient spécifié
     */
    private function userOwnsRecipient($recipient_id) {
        if (!is_user_logged_in()) {
            return false;
        }

        $user_id = get_current_user_id();

        global $wpdb;
        $recipients_table = mj_member_get_notification_recipients_table_name();
        $members_table = MjMembers::getTableName(MjMembers::TABLE_NAME);

        // Vérifier que le recipient appartient à un membre de l'utilisateur
        // La table members utilise wp_user_id, la table recipients utilise user_id
        $owner_check = $wpdb->get_var($wpdb->prepare(
            "SELECT r.id FROM {$recipients_table} r
             LEFT JOIN {$members_table} m ON r.member_id = m.id
             WHERE r.id = %d AND (m.wp_user_id = %d OR r.user_id = %d)",
            $recipient_id,
            $user_id,
            $user_id
        ));

        return !empty($owner_check);
    }
}
