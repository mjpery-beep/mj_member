<?php

namespace Mj\Member\Admin\Page;

use Mj\Member\Admin\RequestGuard;
use Mj\Member\Classes\Crud\MjNotifications;
use Mj\Member\Classes\Crud\MjNotificationRecipients;
use Mj\Member\Classes\Table\MjNotifications_List_Table;
use Mj\Member\Core\Config;
use MjNotificationTypes;

if (!defined('ABSPATH')) {
    exit;
}

final class NotificationsPage
{
    public static function slug(): string
    {
        return 'mj_notifications';
    }

    public static function registerHooks(?string $hookSuffix): void
    {
        if ($hookSuffix === null || $hookSuffix === '') {
            return;
        }

        add_action('load-' . $hookSuffix, array(__CLASS__, 'handleLoad'));
    }

    public static function deleteNonceAction(int $notificationId): string
    {
        return 'mj_member_notification_delete_' . $notificationId;
    }

    public static function bulkNonceAction(): string
    {
        return 'bulk-mj_notifications';
    }

    public static function render(): void
    {
        $capability = Config::capability();
        if ($capability === '') {
            $capability = 'manage_options';
        }

        RequestGuard::ensureCapabilityOrDie($capability);

        $mode = self::determineMode();

        $view = array(
            'mode' => $mode,
            'notice' => self::extractNotice(),
            'type_labels' => self::getTypeLabels(),
            'status_labels' => self::getStatusLabels(),
            'statuses' => self::getStatuses(),
            'types' => self::getTypes(),
        );

        if ($mode === 'view') {
            $notificationId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
            $notification = $notificationId > 0 ? MjNotifications::get($notificationId) : null;
            if (!$notification) {
                self::redirectWithError(__('Notification introuvable.', 'mj-member'));
            }
            $view['notification'] = $notification;
            $view['recipients'] = MjNotificationRecipients::get_all(array(
                'notification_ids' => array($notificationId),
                'limit' => 100,
            ));
        }

        $template = Config::path() . 'includes/templates/admin/notifications-page.php';
        if (is_readable($template)) {
            $table = null;
            if ($mode === 'list') {
                $table = new MjNotifications_List_Table(array(
                    'type_labels' => $view['type_labels'],
                    'status_labels' => $view['status_labels'],
                ));
            }
            $view['table'] = $table;
            require $template;
        }
    }

    public static function handleLoad(): void
    {
        $capability = Config::capability();
        if ($capability === '') {
            $capability = 'manage_options';
        }

        if (!RequestGuard::ensureCapability($capability)) {
            return;
        }

        // Gérer les bulk actions (POST) avant le rendu
        self::handleBulkActions();

        // Gérer les actions individuelles (avec ID dans GET)
        $action = isset($_GET['action']) ? sanitize_key(wp_unslash((string) $_GET['action'])) : '';
        $hasId = isset($_GET['id']) && (int) $_GET['id'] > 0;

        if (!$hasId) {
            return;
        }

        if ($action === 'delete') {
            self::handleSingleDelete();
        }

        if ($action === 'archive') {
            self::handleSingleArchive();
        }
    }

    private static function handleBulkActions(): void
    {
        // Vérifier si c'est une requête POST avec une action bulk
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $action = '';
        if (isset($_POST['action']) && $_POST['action'] !== '-1') {
            $action = sanitize_key(wp_unslash((string) $_POST['action']));
        } elseif (isset($_POST['action2']) && $_POST['action2'] !== '-1') {
            $action = sanitize_key(wp_unslash((string) $_POST['action2']));
        }

        if (!in_array($action, array('delete', 'archive'), true)) {
            return;
        }

        $ids = isset($_POST['notification']) && is_array($_POST['notification'])
            ? array_map('intval', $_POST['notification'])
            : array();

        if (empty($ids)) {
            return;
        }

        // Vérifier le nonce
        $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash((string) $_POST['_wpnonce'])) : '';
        if (!wp_verify_nonce($nonce, 'bulk-mj_notifications')) {
            return;
        }

        switch ($action) {
            case 'delete':
                self::bulkDelete($ids);
                self::redirectWithMessage('notifications_deleted');
                break;

            case 'archive':
                self::bulkArchive($ids);
                self::redirectWithMessage('notifications_archived');
                break;
        }
    }

    /**
     * @param array<int,int> $ids
     */
    private static function bulkDelete(array $ids): void
    {
        foreach ($ids as $id) {
            if ($id <= 0) {
                continue;
            }

            self::deleteRecipients($id);
            MjNotifications::delete($id);
        }
    }

    /**
     * @param array<int,int> $ids
     */
    private static function bulkArchive(array $ids): void
    {
        foreach ($ids as $id) {
            if ($id <= 0) {
                continue;
            }

            MjNotifications::update($id, array('status' => MjNotifications::STATUS_ARCHIVED));
        }
    }

    private static function handleSingleDelete(): void
    {
        $notificationId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        $nonce = isset($_GET['_wpnonce']) ? (string) $_GET['_wpnonce'] : '';

        if ($notificationId <= 0 || !wp_verify_nonce($nonce, self::deleteNonceAction($notificationId))) {
            wp_die(esc_html__('Action non autorisée.', 'mj-member'));
        }

        // Supprimer d'abord les destinataires
        self::deleteRecipients($notificationId);

        $result = MjNotifications::delete($notificationId);
        if (is_wp_error($result)) {
            self::redirectWithError($result->get_error_message());
        }

        self::redirectWithMessage('notification_deleted');
    }

    private static function handleSingleArchive(): void
    {
        $notificationId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        $nonce = isset($_GET['_wpnonce']) ? (string) $_GET['_wpnonce'] : '';

        if ($notificationId <= 0 || !wp_verify_nonce($nonce, 'mj_member_notification_archive_' . $notificationId)) {
            wp_die(esc_html__('Action non autorisée.', 'mj-member'));
        }

        $result = MjNotifications::update($notificationId, array('status' => MjNotifications::STATUS_ARCHIVED));
        if (is_wp_error($result)) {
            self::redirectWithError($result->get_error_message());
        }

        self::redirectWithMessage('notification_archived');
    }

    private static function deleteRecipients(int $notificationId): void
    {
        $recipients = MjNotificationRecipients::get_all(array(
            'notification_ids' => array($notificationId),
            'limit' => 1000,
        ));

        foreach ($recipients as $recipient) {
            if (isset($recipient->id)) {
                MjNotificationRecipients::delete((int) $recipient->id);
            }
        }
    }

    private static function determineMode(): string
    {
        $action = isset($_GET['action']) ? sanitize_key(wp_unslash((string) $_GET['action'])) : '';

        if ($action === 'view') {
            return 'view';
        }

        return 'list';
    }

    /**
     * @return array<string,string>
     */
    public static function getStatusLabels(): array
    {
        return array(
            MjNotifications::STATUS_DRAFT => __('Brouillon', 'mj-member'),
            MjNotifications::STATUS_PUBLISHED => __('Publié', 'mj-member'),
            MjNotifications::STATUS_ARCHIVED => __('Archivé', 'mj-member'),
        );
    }

    /**
     * @return array<int,string>
     */
    public static function getStatuses(): array
    {
        return array(
            MjNotifications::STATUS_DRAFT,
            MjNotifications::STATUS_PUBLISHED,
            MjNotifications::STATUS_ARCHIVED,
        );
    }

    /**
     * @return array<string,string>
     */
    public static function getTypeLabels(): array
    {
        if (class_exists('MjNotificationTypes') && method_exists('MjNotificationTypes', 'get_labels')) {
            return MjNotificationTypes::get_labels();
        }

        return array();
    }

    /**
     * @return array<int,string>
     */
    public static function getTypes(): array
    {
        if (!class_exists('MjNotificationTypes')) {
            return array();
        }

        $reflection = new \ReflectionClass('MjNotificationTypes');
        $constants = $reflection->getConstants();

        return array_values($constants);
    }

    /**
     * @return array<string,string>
     */
    private static function extractNotice(): array
    {
        if (!isset($_GET['mj_notification_notice'])) {
            return array();
        }

        $type = sanitize_key((string) wp_unslash($_GET['mj_notification_notice']));
        if ($type === '') {
            return array();
        }

        $messages = array(
            'notification_deleted' => __('Notification supprimée.', 'mj-member'),
            'notification_archived' => __('Notification archivée.', 'mj-member'),
            'notifications_deleted' => __('Notifications supprimées.', 'mj-member'),
            'notifications_archived' => __('Notifications archivées.', 'mj-member'),
            'notification_updated' => __('Notification mise à jour.', 'mj-member'),
        );

        if ($type === 'error') {
            $rawMessage = isset($_GET['mj_notification_error']) ? (string) $_GET['mj_notification_error'] : '';
            $decoded = $rawMessage !== '' ? rawurldecode($rawMessage) : '';
            $message = $decoded !== '' ? sanitize_text_field(wp_unslash($decoded)) : __('Une erreur est survenue.', 'mj-member');
            return array(
                'type' => 'error',
                'message' => $message,
            );
        }

        if (!isset($messages[$type])) {
            return array();
        }

        return array(
            'type' => 'success',
            'message' => $messages[$type],
        );
    }

    public static function redirectWithMessage(string $type, ?string $message = null): void
    {
        $args = array(
            'page' => self::slug(),
            'mj_notification_notice' => $type,
        );

        if ($type === 'error' && $message !== null) {
            $args['mj_notification_error'] = rawurlencode($message);
        }

        $target = add_query_arg($args, admin_url('admin.php'));
        wp_safe_redirect($target);
        exit;
    }

    public static function redirectWithError(string $message): void
    {
        self::redirectWithMessage('error', $message);
    }
}
