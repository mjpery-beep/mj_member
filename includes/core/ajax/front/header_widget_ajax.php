<?php
/**
 * AJAX Handlers pour le widget Header MJ
 *
 * - mj_header_upcoming_events : renvoie les N prochains événements publiés
 *
 * @package MjMember
 */

namespace Mj\Member\Core\Ajax\Front;

use Mj\Member\Core\Contracts\AjaxHandlerInterface;
use Mj\Member\Classes\Crud\MjEvents;
use Mj\Member\Classes\Crud\MjMembers;
use Mj\Member\Classes\MjNotificationManager;

if (!defined('ABSPATH')) {
    exit;
}

final class HeaderWidgetController implements AjaxHandlerInterface
{
    public function registerHooks(): void
    {
        add_action('wp_ajax_mj_header_upcoming_events', [$this, 'headerUpcomingEvents']);
        add_action('wp_ajax_nopriv_mj_header_upcoming_events', [$this, 'headerUpcomingEvents']);
        add_action('wp_ajax_mj_header_archive_link_notifications', [$this, 'headerArchiveLinkNotifications']);
    }

    /**
     * Récupère les prochains événements pour l'aperçu Agenda du header.
     * Accessible aux utilisateurs connectés et déconnectés.
     */
    public function headerUpcomingEvents(): void
    {
        check_ajax_referer('mj-header-widget', 'nonce');

        $limit = isset($_POST['limit']) ? max(1, min(10, (int) $_POST['limit'])) : 5;

        $events = MjEvents::get_all(array(
            'statuses' => array('published'),
            'after'    => current_time('Y-m-d H:i:s'),
            'orderby'  => 'date_debut',
            'order'    => 'ASC',
            'limit'    => $limit,
        ));

        $result = array();
        foreach ($events as $event) {
            $result[] = array(
                'id'          => (int) $event->id,
                'title'       => (string) $event->title,
                'date_debut'  => (string) $event->date_debut,
                'date_fin'    => (string) $event->date_fin,
                'type'        => (string) $event->type,
                'accent_color'=> (string) ($event->accent_color ?: ''),
                'emoji'       => (string) ($event->emoji ?: ''),
                'slug'        => (string) ($event->slug ?: ''),
                'url'         => $event->slug ? get_home_url(null, '/evenement/' . $event->slug) : '',
            );
        }

        wp_send_json_success($result);
    }

    /**
     * Archive toutes les notifications d'un lien "Mon compte" en filtrant par types.
     */
    public function headerArchiveLinkNotifications(): void
    {
        check_ajax_referer('mj-notification-bell', 'nonce');

        $member_id = isset($_POST['member_id']) ? absint($_POST['member_id']) : 0;
        if ($member_id <= 0 || !$this->userOwnsMember($member_id)) {
            wp_send_json_error(array('message' => __('Accès non autorisé.', 'mj-member')));
        }

        $types_raw = isset($_POST['types']) ? wp_unslash($_POST['types']) : array();
        $types = array();

        if (is_string($types_raw) && $types_raw !== '') {
            $decoded = json_decode($types_raw, true);
            if (is_array($decoded)) {
                $types_raw = $decoded;
            } else {
                $types_raw = preg_split('/\s*,\s*/', $types_raw) ?: array();
            }
        }

        if (is_array($types_raw)) {
            $types = array_values(array_filter(array_map('sanitize_key', $types_raw)));
        }

        if (empty($types)) {
            wp_send_json_error(array('message' => __('Types de notifications invalides.', 'mj-member')));
        }

        global $wpdb;
        $recipients_table = mj_member_get_notification_recipients_table_name();
        $notifications_table = mj_member_get_notifications_table_name();

        $placeholders = implode(', ', array_fill(0, count($types), '%s'));
        $params = array_merge(array($member_id), $types);

        $sql = $wpdb->prepare(
            "SELECT r.id
             FROM {$recipients_table} r
             INNER JOIN {$notifications_table} n ON n.id = r.notification_id
             WHERE r.member_id = %d
               AND r.status != 'archived'
               AND n.type IN ({$placeholders})",
            ...$params
        );

        $recipient_ids = $wpdb->get_col($sql);
        if (empty($recipient_ids)) {
            wp_send_json_success(array('archived' => 0));
        }

        $recipient_ids = array_values(array_filter(array_map('absint', $recipient_ids)));
        $result = MjNotificationManager::mark_recipient_status($recipient_ids, 'archived');

        if ($result !== false && !is_wp_error($result)) {
            wp_send_json_success(array('archived' => count($recipient_ids)));
        }

        wp_send_json_error(array('message' => __('Impossible d\'archiver les notifications du lien.', 'mj-member')));
    }

    /**
     * Vérifie si l'utilisateur connecté possède le membre spécifié.
     */
    private function userOwnsMember(int $member_id): bool
    {
        if (function_exists('mj_member_notification_bell_user_owns_member')) {
            return (bool) mj_member_notification_bell_user_owns_member($member_id);
        }

        if (!is_user_logged_in()) {
            return false;
        }

        $user_id = get_current_user_id();

        if (function_exists('mj_member_get_current_member')) {
            $current_member = mj_member_get_current_member();
            if ($current_member && (int) $current_member->id === (int) $member_id) {
                return true;
            }
        }

        if (function_exists('mj_member_get_guardian_for_member')) {
            $guardian = mj_member_get_guardian_for_member($member_id);
            if ($guardian && (int) $guardian->user_id === $user_id) {
                return true;
            }
        }

        global $wpdb;
        $members_table = MjMembers::getTableName(MjMembers::TABLE_NAME);
        $member_user_id = $wpdb->get_var($wpdb->prepare(
            "SELECT wp_user_id FROM {$members_table} WHERE id = %d",
            $member_id
        ));

        return $member_user_id && (int) $member_user_id === $user_id;
    }
}
