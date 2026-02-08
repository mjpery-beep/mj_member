<?php
/**
 * AJAX Handlers pour le widget cloche de notifications
 *
 * @package MjMember
 */

if (!defined('ABSPATH')) {
    exit;
}

use Mj\Member\Classes\Crud\MjMembers;
use Mj\Member\Classes\MjNotificationManager;

/**
 * Récupère les notifications pour le panneau
 */
add_action('wp_ajax_mj_member_notification_bell_fetch', 'mj_member_notification_bell_fetch_handler');
function mj_member_notification_bell_fetch_handler() {
    check_ajax_referer('mj-notification-bell', 'nonce');

    $member_id = isset($_POST['member_id']) ? absint($_POST['member_id']) : 0;

    // Vérifier que le membre appartient bien à l'utilisateur connecté
    if ($member_id <= 0 || !mj_member_notification_bell_user_owns_member($member_id)) {
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
add_action('wp_ajax_mj_member_notification_bell_count', 'mj_member_notification_bell_count_handler');
function mj_member_notification_bell_count_handler() {
    check_ajax_referer('mj-notification-bell', 'nonce');

    $member_id = isset($_POST['member_id']) ? absint($_POST['member_id']) : 0;

    if ($member_id <= 0 || !mj_member_notification_bell_user_owns_member($member_id)) {
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
add_action('wp_ajax_mj_member_notification_bell_mark_read', 'mj_member_notification_bell_mark_read_handler');
function mj_member_notification_bell_mark_read_handler() {
    check_ajax_referer('mj-notification-bell', 'nonce');

    $recipient_id = isset($_POST['recipient_id']) ? absint($_POST['recipient_id']) : 0;

    if ($recipient_id <= 0) {
        wp_send_json_error(array('message' => __('ID de notification invalide.', 'mj-member')));
    }

    // Vérifier que le recipient appartient bien à l'utilisateur connecté
    if (!mj_member_notification_bell_user_owns_recipient($recipient_id)) {
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
add_action('wp_ajax_mj_member_notification_bell_mark_all_read', 'mj_member_notification_bell_mark_all_read_handler');
function mj_member_notification_bell_mark_all_read_handler() {
    check_ajax_referer('mj-notification-bell', 'nonce');

    $member_id = isset($_POST['member_id']) ? absint($_POST['member_id']) : 0;

    if ($member_id <= 0 || !mj_member_notification_bell_user_owns_member($member_id)) {
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
add_action('wp_ajax_mj_member_notification_bell_archive', 'mj_member_notification_bell_archive_handler');
function mj_member_notification_bell_archive_handler() {
    check_ajax_referer('mj-notification-bell', 'nonce');

    $recipient_id = isset($_POST['recipient_id']) ? absint($_POST['recipient_id']) : 0;

    if ($recipient_id <= 0) {
        wp_send_json_error(array('message' => __('ID de notification invalide.', 'mj-member')));
    }

    // Vérifier que le recipient appartient bien à l'utilisateur connecté
    if (!mj_member_notification_bell_user_owns_recipient($recipient_id)) {
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
 * Vérifie si l'utilisateur connecté possède le membre spécifié
 */
function mj_member_notification_bell_user_owns_member($member_id) {
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
function mj_member_notification_bell_user_owns_recipient($recipient_id) {
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
