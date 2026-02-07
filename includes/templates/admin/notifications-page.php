<?php

use Mj\Member\Admin\Page\NotificationsPage;
use Mj\Member\Classes\Crud\MjNotifications;
use Mj\Member\Classes\Crud\MjNotificationRecipients;
use Mj\Member\Classes\Crud\MjMembers;
use Mj\Member\Classes\Table\MjNotifications_List_Table;
use Mj\Member\Classes\Value\MemberData;

if (!defined('ABSPATH')) {
    exit;
}

$mode = isset($view['mode']) ? (string) $view['mode'] : 'list';
$notice = isset($view['notice']) && is_array($view['notice']) ? $view['notice'] : array();
$typeLabels = isset($view['type_labels']) && is_array($view['type_labels']) ? $view['type_labels'] : array();
$statusLabels = isset($view['status_labels']) && is_array($view['status_labels']) ? $view['status_labels'] : array();
$table = isset($view['table']) && $view['table'] instanceof MjNotifications_List_Table ? $view['table'] : null;
$notification = isset($view['notification']) ? $view['notification'] : null;
$recipients = isset($view['recipients']) && is_array($view['recipients']) ? $view['recipients'] : array();

$pageSlug = NotificationsPage::slug();
$pageUrl = add_query_arg('page', $pageSlug, admin_url('admin.php'));

?>
<div class="wrap mj-member-notifications-admin">
    <h1 class="wp-heading-inline">
        <?php if ($mode === 'view') : ?>
            <?php esc_html_e('Détail de la notification', 'mj-member'); ?>
        <?php else : ?>
            <?php esc_html_e('Gestion des notifications', 'mj-member'); ?>
        <?php endif; ?>
    </h1>

    <?php if ($mode === 'view') : ?>
        <a href="<?php echo esc_url($pageUrl); ?>" class="page-title-action">
            <?php esc_html_e('← Retour à la liste', 'mj-member'); ?>
        </a>
    <?php endif; ?>

    <hr class="wp-header-end">

    <?php if (!empty($notice) && !empty($notice['message'])) : ?>
        <?php $noticeClass = $notice['type'] === 'error' ? 'notice-error' : 'notice-success'; ?>
        <div class="notice <?php echo esc_attr($noticeClass); ?> is-dismissible">
            <p><?php echo esc_html((string) $notice['message']); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($mode === 'list' && $table instanceof MjNotifications_List_Table) : ?>
        <?php $table->prepare_items(); ?>

        <?php $table->views(); ?>

        <form method="post">
            <input type="hidden" name="page" value="<?php echo esc_attr($pageSlug); ?>">
            <?php $table->search_box(__('Rechercher des notifications', 'mj-member'), 'mj-member-notifications'); ?>
            <?php $table->display(); ?>
        </form>

    <?php elseif ($mode === 'view' && $notification) : ?>
        <?php
        $notificationId = isset($notification->id) ? (int) $notification->id : 0;
        $notificationTitle = isset($notification->title) ? (string) $notification->title : '';
        $notificationExcerpt = isset($notification->excerpt) ? (string) $notification->excerpt : '';
        $notificationType = isset($notification->type) ? (string) $notification->type : '';
        $notificationStatus = isset($notification->status) ? (string) $notification->status : '';
        $notificationPriority = isset($notification->priority) ? (int) $notification->priority : 0;
        $notificationUrl = isset($notification->url) ? (string) $notification->url : '';
        $notificationContext = isset($notification->context) ? (string) $notification->context : '';
        $notificationSource = isset($notification->source) ? (string) $notification->source : '';
        $notificationCreatedAt = isset($notification->created_at) ? (string) $notification->created_at : '';
        $notificationExpiresAt = isset($notification->expires_at) ? (string) $notification->expires_at : '';
        $notificationPayload = isset($notification->payload) && is_array($notification->payload) ? $notification->payload : array();
        $notificationUid = isset($notification->uid) ? (string) $notification->uid : '';

        $typeLabel = isset($typeLabels[$notificationType]) ? $typeLabels[$notificationType] : $notificationType;
        $statusLabel = isset($statusLabels[$notificationStatus]) ? $statusLabels[$notificationStatus] : $notificationStatus;
        ?>

        <div class="mj-notification-detail" style="display: flex; gap: 24px; margin-top: 24px;">
            <!-- Colonne principale -->
            <div class="mj-notification-detail__main" style="flex: 1; max-width: 600px;">
                <div class="card">
                    <h2 style="margin-top:0;"><?php echo esc_html($notificationTitle); ?></h2>
                    
                    <?php if ($notificationExcerpt !== '') : ?>
                        <div class="mj-notification-detail__excerpt" style="margin-bottom: 16px; padding: 12px; background: #f9f9f9; border-radius: 4px;">
                            <p style="margin: 0;"><?php echo esc_html($notificationExcerpt); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if ($notificationUrl !== '') : ?>
                        <p>
                            <strong><?php esc_html_e('Lien :', 'mj-member'); ?></strong><br>
                            <a href="<?php echo esc_url($notificationUrl); ?>" target="_blank">
                                <?php echo esc_html($notificationUrl); ?>
                            </a>
                        </p>
                    <?php endif; ?>

                    <?php if (!empty($notificationPayload)) : ?>
                        <details style="margin-top: 16px;">
                            <summary style="cursor: pointer; font-weight: 600;">
                                <?php esc_html_e('Payload (données)', 'mj-member'); ?>
                            </summary>
                            <pre style="background: #f0f0f0; padding: 12px; overflow: auto; max-height: 300px; margin-top: 8px; border-radius: 4px;"><?php echo esc_html(json_encode($notificationPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
                        </details>
                    <?php endif; ?>
                </div>

                <!-- Actions -->
                <div class="card" style="margin-top: 16px;">
                    <h3 style="margin-top:0;"><?php esc_html_e('Actions', 'mj-member'); ?></h3>
                    <p>
                        <?php if ($notificationStatus !== MjNotifications::STATUS_ARCHIVED) : ?>
                            <?php
                            $archiveUrl = wp_nonce_url(
                                add_query_arg(
                                    array(
                                        'page'   => $pageSlug,
                                        'action' => 'archive',
                                        'id'     => $notificationId,
                                    ),
                                    admin_url('admin.php')
                                ),
                                'mj_member_notification_archive_' . $notificationId
                            );
                            ?>
                            <a href="<?php echo esc_url($archiveUrl); ?>" class="button">
                                <?php esc_html_e('Archiver', 'mj-member'); ?>
                            </a>
                        <?php endif; ?>

                        <?php
                        $deleteUrl = wp_nonce_url(
                            add_query_arg(
                                array(
                                    'page'   => $pageSlug,
                                    'action' => 'delete',
                                    'id'     => $notificationId,
                                ),
                                admin_url('admin.php')
                            ),
                            NotificationsPage::deleteNonceAction($notificationId)
                        );
                        ?>
                        <a href="<?php echo esc_url($deleteUrl); ?>" class="button button-link-delete" onclick="return confirm('<?php echo esc_js(__('Supprimer cette notification et tous ses destinataires ?', 'mj-member')); ?>');">
                            <?php esc_html_e('Supprimer', 'mj-member'); ?>
                        </a>
                    </p>
                </div>
            </div>

            <!-- Colonne méta -->
            <div class="mj-notification-detail__meta" style="width: 320px;">
                <div class="card">
                    <h3 style="margin-top:0;"><?php esc_html_e('Informations', 'mj-member'); ?></h3>
                    
                    <table class="widefat striped" style="border: 0;">
                        <tbody>
                            <tr>
                                <th style="width: 40%;"><?php esc_html_e('ID', 'mj-member'); ?></th>
                                <td><code><?php echo esc_html((string) $notificationId); ?></code></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('UID', 'mj-member'); ?></th>
                                <td><code style="font-size: 11px;"><?php echo esc_html($notificationUid); ?></code></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Type', 'mj-member'); ?></th>
                                <td>
                                    <span class="mj-notification-type mj-notification-type--<?php echo esc_attr(sanitize_html_class($notificationType)); ?>">
                                        <?php echo esc_html($typeLabel); ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Statut', 'mj-member'); ?></th>
                                <td>
                                    <?php
                                    $statusClass = 'mj-notification-status';
                                    if ($notificationStatus === MjNotifications::STATUS_PUBLISHED) {
                                        $statusClass .= ' mj-notification-status--published';
                                    } elseif ($notificationStatus === MjNotifications::STATUS_ARCHIVED) {
                                        $statusClass .= ' mj-notification-status--archived';
                                    } elseif ($notificationStatus === MjNotifications::STATUS_DRAFT) {
                                        $statusClass .= ' mj-notification-status--draft';
                                    }
                                    ?>
                                    <span class="<?php echo esc_attr($statusClass); ?>">
                                        <?php echo esc_html($statusLabel); ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Priorité', 'mj-member'); ?></th>
                                <td><?php echo esc_html((string) $notificationPriority); ?></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Contexte', 'mj-member'); ?></th>
                                <td><?php echo $notificationContext !== '' ? esc_html($notificationContext) : '<span class="description">—</span>'; ?></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Source', 'mj-member'); ?></th>
                                <td><?php echo $notificationSource !== '' ? esc_html($notificationSource) : '<span class="description">—</span>'; ?></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Créée le', 'mj-member'); ?></th>
                                <td>
                                    <?php
                                    if ($notificationCreatedAt !== '') {
                                        $timestamp = strtotime($notificationCreatedAt);
                                        if ($timestamp !== false) {
                                            $format = get_option('date_format', 'Y-m-d') . ' ' . get_option('time_format', 'H:i');
                                            echo esc_html(wp_date($format, $timestamp));
                                        } else {
                                            echo esc_html($notificationCreatedAt);
                                        }
                                    } else {
                                        echo '<span class="description">—</span>';
                                    }
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Expire le', 'mj-member'); ?></th>
                                <td>
                                    <?php
                                    if ($notificationExpiresAt !== '' && $notificationExpiresAt !== '0000-00-00 00:00:00') {
                                        $timestamp = strtotime($notificationExpiresAt);
                                        if ($timestamp !== false) {
                                            $format = get_option('date_format', 'Y-m-d') . ' ' . get_option('time_format', 'H:i');
                                            $isExpired = $timestamp < current_time('timestamp', 1);
                                            echo '<span class="' . ($isExpired ? 'mj-notification-expired' : '') . '">' . esc_html(wp_date($format, $timestamp)) . '</span>';
                                            if ($isExpired) {
                                                echo ' <span class="mj-notification-expired-badge">' . esc_html__('(Expirée)', 'mj-member') . '</span>';
                                            }
                                        } else {
                                            echo esc_html($notificationExpiresAt);
                                        }
                                    } else {
                                        echo '<span class="description">' . esc_html__('Jamais', 'mj-member') . '</span>';
                                    }
                                    ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Destinataires -->
                <div class="card" style="margin-top: 16px;">
                    <h3 style="margin-top:0;">
                        <?php esc_html_e('Destinataires', 'mj-member'); ?>
                        <span class="count" style="font-weight: normal; color: #666;">(<?php echo esc_html((string) count($recipients)); ?>)</span>
                    </h3>

                    <?php if (empty($recipients)) : ?>
                        <p class="description"><?php esc_html_e('Aucun destinataire.', 'mj-member'); ?></p>
                    <?php else : ?>
                        <table class="widefat striped" style="border: 0;">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Membre', 'mj-member'); ?></th>
                                    <th><?php esc_html_e('Statut', 'mj-member'); ?></th>
                                    <th><?php esc_html_e('Lu le', 'mj-member'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recipients as $recipient) : ?>
                                    <?php
                                    $recipientId = isset($recipient->id) ? (int) $recipient->id : 0;
                                    $memberId = isset($recipient->member_id) ? (int) $recipient->member_id : 0;
                                    $userId = isset($recipient->user_id) ? (int) $recipient->user_id : 0;
                                    $role = isset($recipient->role) ? (string) $recipient->role : '';
                                    $recipientStatus = isset($recipient->status) ? (string) $recipient->status : '';
                                    $readAt = isset($recipient->read_at) ? (string) $recipient->read_at : '';

                                    $memberName = '';
                                    if ($memberId > 0 && class_exists(MjMembers::class)) {
                                        $member = MjMembers::getById($memberId);
                                        if ($member instanceof MemberData) {
                                            $memberName = trim(sprintf('%s %s', (string) $member->get('first_name', ''), (string) $member->get('last_name', '')));
                                        }
                                    }
                                    if ($memberName === '' && $userId > 0) {
                                        $user = get_user_by('id', $userId);
                                        if ($user) {
                                            $memberName = $user->display_name;
                                        }
                                    }
                                    if ($memberName === '' && $role !== '') {
                                        $memberName = sprintf(__('Rôle : %s', 'mj-member'), $role);
                                    }
                                    if ($memberName === '') {
                                        $memberName = sprintf(__('Destinataire #%d', 'mj-member'), $recipientId);
                                    }

                                    $recipientStatusLabel = $recipientStatus === MjNotificationRecipients::STATUS_READ 
                                        ? __('Lu', 'mj-member') 
                                        : __('Non lu', 'mj-member');
                                    $recipientStatusClass = $recipientStatus === MjNotificationRecipients::STATUS_READ 
                                        ? 'mj-recipient-status--read' 
                                        : 'mj-recipient-status--unread';
                                    ?>
                                    <tr>
                                        <td><?php echo esc_html($memberName); ?></td>
                                        <td>
                                            <span class="<?php echo esc_attr($recipientStatusClass); ?>">
                                                <?php echo esc_html($recipientStatusLabel); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            if ($readAt !== '' && $readAt !== '0000-00-00 00:00:00') {
                                                $timestamp = strtotime($readAt);
                                                if ($timestamp !== false) {
                                                    $format = get_option('date_format', 'Y-m-d') . ' ' . get_option('time_format', 'H:i');
                                                    echo esc_html(wp_date($format, $timestamp));
                                                } else {
                                                    echo esc_html($readAt);
                                                }
                                            } else {
                                                echo '<span class="description">—</span>';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    <?php else : ?>
        <p class="description"><?php esc_html_e('Mode non reconnu.', 'mj-member'); ?></p>
    <?php endif; ?>
</div>

<style>
.mj-member-notifications-admin .card {
    padding: 16px 20px;
    background: #fff;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.mj-notification-status {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 500;
}

.mj-notification-status--published {
    background: #d4edda;
    color: #155724;
}

.mj-notification-status--archived {
    background: #e2e3e5;
    color: #6c757d;
}

.mj-notification-status--draft {
    background: #fff3cd;
    color: #856404;
}

.mj-notification-type {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 12px;
    background: #e7f3ff;
    color: #0073aa;
}

.mj-notification-priority--urgent {
    color: #dc3545;
    font-weight: 600;
}

.mj-notification-priority--high {
    color: #fd7e14;
}

.mj-notification-priority--normal {
    color: #28a745;
}

.mj-notification-priority--low {
    color: #6c757d;
}

.mj-notification-expired {
    color: #dc3545;
}

.mj-notification-expired-badge {
    display: inline-block;
    padding: 1px 6px;
    background: #dc3545;
    color: #fff;
    font-size: 11px;
    border-radius: 3px;
}

.mj-recipient-status--read {
    color: #28a745;
}

.mj-recipient-status--unread {
    color: #dc3545;
}

.mj-member-notifications-admin .button-link-delete {
    color: #a00;
}

.mj-member-notifications-admin .button-link-delete:hover {
    color: #dc3545;
}

@media screen and (max-width: 960px) {
    .mj-notification-detail {
        flex-direction: column !important;
    }
    
    .mj-notification-detail__meta {
        width: 100% !important;
    }
}
</style>
