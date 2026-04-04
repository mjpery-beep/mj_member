<?php
/**
 * Template Elementor - Liste de notifications
 *
 * @package MjMember
 *
 * @var string $widget_id
 * @var array<string,mixed> $config
 */

if (!defined('ABSPATH')) {
    exit;
}

$widget_unique_id = 'mj-notification-list-' . esc_attr($widget_id);
$config_json = wp_json_encode($config);
$initial_notifications = isset($config['initialNotifications']) && is_array($config['initialNotifications'])
    ? $config['initialNotifications']
    : array();

$type_emoji = static function ($type) {
    $icons = array(
        'event_registration_created' => '📅',
        'event_registration_cancelled' => '❌',
        'event_reminder' => '⏰',
        'event_new_published' => '🗓️',
        'payment_completed' => '💰',
        'payment_reminder' => '💳',
        'member_created' => '👤',
        'member_profile_updated' => '✏️',
        'profile_updated' => '✏️',
        'photo_uploaded' => '📷',
        'photo_approved' => '✅',
        'idea_published' => '💡',
        'idea_voted' => '👍',
        'trophy_earned' => '🏆',
        'badge_earned' => '🎖️',
        'criterion_earned' => '✅',
        'level_up' => '🚀',
        'action_awarded' => '✨',
        'avatar_applied' => '🎭',
        'attendance_recorded' => '⏱️',
        'message_received' => '💬',
        'todo_assigned' => '📋',
        'todo_note_added' => '📝',
        'todo_media_added' => '📎',
        'todo_completed' => '✅',
        'testimonial_approved' => '✅',
        'testimonial_rejected' => '❌',
        'testimonial_reaction' => '👍',
        'testimonial_comment' => '💬',
        'testimonial_comment_reply' => '↩️',
        'testimonial_new_pending' => '📝',
        'leave_request_created' => '⛵',
        'leave_request_approved' => '🏖️',
        'leave_request_rejected' => '🚫',
        'expense_created' => '🧾',
        'expense_reimbursed' => '💶',
        'expense_rejected' => '⛔',
        'mileage_created' => '🚗',
        'mileage_approved' => '✅',
        'mileage_reimbursed' => '💸',
        'employee_document_uploaded' => '📄',
        'post_published' => '📢',
        'info' => 'ℹ️',
    );

    return isset($icons[$type]) ? $icons[$type] : $icons['info'];
};
?>

<div id="<?php echo $widget_unique_id; ?>"
     class="mj-notification-list-widget"
     data-config='<?php echo esc_attr($config_json ? $config_json : '{}'); ?>'>

    <div class="mj-notification-list-widget__header">
        <h2 class="mj-notification-list-widget__title"><?php echo esc_html((string) ($config['panelTitle'] ?? __('Notifications', 'mj-member'))); ?></h2>
        <button type="button"
                class="mj-notification-list-widget__mark-all"
                data-notif-action="mark-all-read"
                <?php echo empty($config['showMarkAllRead']) ? 'style="display:none;"' : ''; ?>>
            <?php esc_html_e('Tout marquer comme lu', 'mj-member'); ?>
        </button>
    </div>

    <div class="mj-notification-list-widget__content" data-mj-notif-list>
        <?php if (!empty($initial_notifications)): ?>
            <div class="mj-header-notif-list">
                <?php foreach ($initial_notifications as $item):
                    $notif = isset($item['notification']) && is_array($item['notification']) ? $item['notification'] : array();
                    $is_unread = isset($item['recipient_status']) && $item['recipient_status'] === 'unread';
                    $title = isset($notif['title']) ? (string) $notif['title'] : '';
                    $excerpt = isset($notif['excerpt']) ? (string) $notif['excerpt'] : '';
                    $type = isset($notif['type']) ? (string) $notif['type'] : 'info';
                    $url = isset($notif['url']) ? (string) $notif['url'] : '';
                    $created_at = isset($notif['created_at']) ? (string) $notif['created_at'] : '';
                ?>
                    <div class="mj-header-notif-item<?php echo $is_unread ? ' mj-header-notif-item--unread' : ''; ?>"
                         data-recipient-id="<?php echo (int) ($item['recipient_id'] ?? 0); ?>">
                        <?php if ($url): ?>
                            <a href="<?php echo esc_url($url); ?>" class="mj-header-notif-item__bg-link" aria-label="<?php echo esc_attr($title); ?>"></a>
                        <?php endif; ?>
                        <div class="mj-header-notif-icon"><?php echo esc_html($type_emoji($type)); ?></div>
                        <div class="mj-header-notif-body">
                            <span class="mj-header-notif-title"><?php echo esc_html($title); ?></span>
                            <?php if ($excerpt): ?>
                                <p class="mj-header-notif-excerpt"><?php echo esc_html($excerpt); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="mj-header-notif-meta">
                            <?php if ($created_at !== ''): ?>
                                <time class="mj-header-notif-time"><?php echo esc_html($created_at); ?></time>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="mj-header-dropdown__empty">
                <p><?php echo esc_html((string) ($config['emptyMessage'] ?? __('Aucune notification pour le moment.', 'mj-member'))); ?></p>
            </div>
        <?php endif; ?>
    </div>

    <div class="mj-notification-list-widget__footer">
        <button type="button" class="mj-header-notif-delete-all" data-notif-action="archive-all">
            <?php esc_html_e('Tout supprimer', 'mj-member'); ?>
        </button>
    </div>
</div>
