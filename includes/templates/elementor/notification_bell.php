<?php
/**
 * Template Elementor - Cloche de notifications
 *
 * @package MjMember
 * 
 * Variables disponibles :
 * @var array $settings  Configuration du widget Elementor
 * @var string $panel_title  Titre du panneau
 * @var string $empty_message  Message quand aucune notification
 * @var bool $show_mark_all  Afficher le bouton "Tout marquer comme lu"
 * @var int $unread_count  Nombre de notifications non-lues
 * @var array $notifications  Liste des notifications
 * @var array $config  Configuration JSON pour le JS
 * @var string $widget_id  ID unique du widget
 * @var bool $is_preview  Mode preview Elementor
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Retourne l'emoji selon le type de notification
 * @since 1.0.0
 */
function mj_notification_bell_get_type_emoji($type) {
    $icons = array(
        // Événements / Inscriptions
        'event_registration_created' => '📅',
        'event_registration_cancelled' => '❌',
        'event_reminder' => '⏰',
        'event_new_published' => '🗓️',

        // Paiements
        'payment_completed' => '💰',
        'payment_reminder' => '💳',

        // Membres
        'member_created' => '👤',
        'member_profile_updated' => '✏️',
        'profile_updated' => '✏️',

        // Photos
        'photo_uploaded' => '📷',
        'photo_approved' => '✅',

        // Idées
        'idea_published' => '💡',
        'idea_voted' => '👍',

        // Gamification
        'trophy_earned' => '🏆',
        'badge_earned' => '🎖️',
        'criterion_earned' => '✓',
        'level_up' => '🚀',

        // Avatar
        'avatar_applied' => '🎭',

        // Présence
        'attendance_recorded' => '⏱️',

        // Messages
        'message_received' => '💬',

        // Tâches (Todos)
        'todo_assigned' => '📋',
        'todo_note_added' => '📝',
        'todo_media_added' => '📎',
        'todo_completed' => '✅',

        // Témoignages
        'testimonial_approved' => '✅',
        'testimonial_rejected' => '❌',
        'testimonial_reaction' => '👍',
        'testimonial_comment' => '💬',
        'testimonial_comment_reply' => '↩️',
        'testimonial_new_pending' => '📝',

        // Défaut / Info
        'info' => 'ℹ️',
    );
    
    return isset($icons[$type]) ? $icons[$type] : $icons['info'];
}

$config_json = wp_json_encode($config);
$widget_unique_id = 'mj-notification-bell-' . esc_attr($widget_id);
?>

<style>
/* Styles de base - les propriétés modifiables par Elementor ne sont pas définies ici */
#<?php echo $widget_unique_id; ?> {
    position: relative;
    display: inline-flex;
    align-items: center;
}
#<?php echo $widget_unique_id; ?> .mj-notification-bell__trigger {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    padding: 0;
    background: transparent;
    border: none;
    border-radius: 50%;
    cursor: pointer;
}
#<?php echo $widget_unique_id; ?> .mj-notification-bell__icon {
    display: flex;
    align-items: center;
    justify-content: center;
}
#<?php echo $widget_unique_id; ?> .mj-notification-bell__icon svg {
    fill: currentColor;
}
#<?php echo $widget_unique_id; ?> .mj-notification-bell__badge {
    position: absolute;
    top: 2px;
    right: 2px;
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 18px;
    height: 18px;
    padding: 0 5px;
    font-size: 11px;
    font-weight: 600;
    line-height: 1;
    border-radius: 10px;
    box-sizing: border-box;
}
#<?php echo $widget_unique_id; ?> .mj-notification-bell__badge--hidden {
    display: none;
}
</style>

<div id="<?php echo $widget_unique_id; ?>" 
     class="mj-notification-bell" 
     data-config='<?php echo esc_attr($config_json); ?>'>
    
    <!-- Bouton cloche -->
    <button type="button" 
            class="mj-notification-bell__trigger" 
            aria-label="<?php esc_attr_e('Notifications', 'mj-member'); ?>"
            aria-expanded="false"
            aria-haspopup="true">
        <span class="mj-notification-bell__icon">
            <?php if (!empty($custom_icon_url)): ?>
                <img src="<?php echo esc_url($custom_icon_url); ?>" alt="" aria-hidden="true" />
            <?php else: ?>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M5.25 9a6.75 6.75 0 0 1 13.5 0v.75c0 2.123.8 4.057 2.118 5.52a.75.75 0 0 1-.297 1.206c-1.544.57-3.16.99-4.831 1.243a3.75 3.75 0 1 1-7.48 0 24.585 24.585 0 0 1-4.831-1.244.75.75 0 0 1-.298-1.205A8.217 8.217 0 0 0 5.25 9.75V9Zm4.502 8.9a2.25 2.25 0 1 0 4.496 0 25.057 25.057 0 0 1-4.496 0Z" clip-rule="evenodd" />
                </svg>
            <?php endif; ?>
        </span>
        <span class="mj-notification-bell__badge <?php echo $unread_count > 0 ? '' : 'mj-notification-bell__badge--hidden'; ?>" 
              aria-live="polite">
            <span class="mj-notification-bell__count"><?php echo $unread_count > 99 ? '99+' : (int) $unread_count; ?></span>
        </span>
    </button>

<?php if (empty($is_preview)): ?>
    <!-- Panneau déroulant -->
    <div class="mj-notification-bell__panel" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr($panel_title); ?>">
        
        <!-- En-tête -->
        <div class="mj-notification-bell__header">
            <h2 class="mj-notification-bell__title"><?php echo esc_html($panel_title); ?></h2>
            <div class="mj-notification-bell__header-actions">
                <?php if ($show_mark_all): ?>
                <button type="button" 
                        class="mj-notification-bell__mark-all" 
                        title="<?php esc_attr_e('Tout marquer comme lu', 'mj-member'); ?>"
                        <?php echo $unread_count === 0 ? 'disabled' : ''; ?>>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                    </svg>
                    <span class="screen-reader-text"><?php esc_html_e('Tout marquer comme lu', 'mj-member'); ?></span>
                </button>
                <?php endif; ?>
                <button type="button" class="mj-notification-bell__close" aria-label="<?php esc_attr_e('Fermer', 'mj-member'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Liste des notifications -->
        <div class="mj-notification-bell__list-container">
            <div class="mj-notification-bell__list" role="list">
                <?php if (empty($notifications)): ?>
                    <div class="mj-notification-bell__empty">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="mj-notification-bell__empty-icon">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0M3.124 7.5A8.969 8.969 0 0 1 5.292 3m13.416 0a8.969 8.969 0 0 1 2.168 4.5" />
                        </svg>
                        <p class="mj-notification-bell__empty-text"><?php echo esc_html($empty_message); ?></p>
                    </div>
                <?php else: ?>
                    <?php foreach ($notifications as $item): 
                        $notif = isset($item['notification']) ? $item['notification'] : array();
                        $recipient_id = isset($item['recipient_id']) ? (int) $item['recipient_id'] : 0;
                        $is_unread = isset($item['recipient_status']) && $item['recipient_status'] === 'unread';
                        $title = isset($notif['title']) ? $notif['title'] : '';
                        $excerpt = isset($notif['excerpt']) ? $notif['excerpt'] : '';
                        $type = isset($notif['type']) ? $notif['type'] : 'info';
                        $url = isset($notif['url']) ? $notif['url'] : '';
                        $created_at = isset($notif['created_at']) ? $notif['created_at'] : '';
                        
                        // Calcul du temps relatif
                        $time_ago = '';
                        if ($created_at) {
                            $diff = time() - strtotime($created_at);
                            if ($diff < 60) {
                                $time_ago = __('à l\'instant', 'mj-member');
                            } elseif ($diff < 3600) {
                                $mins = floor($diff / 60);
                                $time_ago = sprintf(_n('il y a %d min', 'il y a %d min', $mins, 'mj-member'), $mins);
                            } elseif ($diff < 86400) {
                                $hours = floor($diff / 3600);
                                $time_ago = sprintf(_n('il y a %d h', 'il y a %d h', $hours, 'mj-member'), $hours);
                            } elseif ($diff < 604800) {
                                $days = floor($diff / 86400);
                                $time_ago = sprintf(_n('il y a %d j', 'il y a %d j', $days, 'mj-member'), $days);
                            } else {
                                $time_ago = date_i18n(get_option('date_format'), strtotime($created_at));
                            }
                        }

                        // Icône selon le type
                        $icon_class = 'mj-notification-bell__item-icon--' . esc_attr($type);
                    ?>
                        <div class="mj-notification-bell__item <?php echo $is_unread ? 'mj-notification-bell__item--unread' : ''; ?><?php echo $url ? ' mj-notification-bell__item--clickable' : ''; ?>" 
                             role="listitem"
                             data-recipient-id="<?php echo $recipient_id; ?>"
                             data-notification-id="<?php echo isset($item['notification_id']) ? (int) $item['notification_id'] : 0; ?>"
                             <?php if ($url): ?>data-url="<?php echo esc_url($url); ?>"<?php endif; ?>>
                            
                            <?php if ($url): ?>
                            <a href="<?php echo esc_url($url); ?>" class="mj-notification-bell__item-link" aria-label="<?php echo esc_attr($title); ?>"></a>
                            <?php endif; ?>
                            
                            <div class="mj-notification-bell__item-icon <?php echo $icon_class; ?>" title="<?php echo esc_attr($type); ?>">
                                <?php echo mj_notification_bell_get_type_emoji($type); ?>
                                <small style="font-size:9px;display:block;color:#666;"><?php echo esc_html($type); ?></small>
                            </div>
                            
                            <div class="mj-notification-bell__item-content">
                                <span class="mj-notification-bell__item-title">
                                    <?php echo esc_html($title); ?>
                                </span>
                                
                                <?php if ($excerpt): ?>
                                    <p class="mj-notification-bell__item-excerpt"><?php echo esc_html($excerpt); ?></p>
                                <?php endif; ?>
                                
                                <time class="mj-notification-bell__item-time" datetime="<?php echo esc_attr($created_at); ?>">
                                    <?php echo esc_html($time_ago); ?>
                                </time>
                            </div>
                            
                            <?php if ($url): ?>
                            <div class="mj-notification-bell__item-chevron" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M8.22 5.22a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L11.94 10 8.22 6.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <?php endif; ?>
                            
                            <div class="mj-notification-bell__item-actions">
                                <?php if ($is_unread): ?>
                                <button type="button" 
                                        class="mj-notification-bell__item-action mj-notification-bell__item-mark-read" 
                                        data-recipient-id="<?php echo $recipient_id; ?>"
                                        title="<?php esc_attr_e('Marquer comme lu', 'mj-member'); ?>">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                                <?php endif; ?>
                                <button type="button" 
                                        class="mj-notification-bell__item-action mj-notification-bell__item-archive" 
                                        data-recipient-id="<?php echo $recipient_id; ?>"
                                        title="<?php esc_attr_e('Supprimer', 'mj-member'); ?>">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.519.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 4.193V3.75A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4ZM8.58 7.72a.75.75 0 0 0-1.5.06l.3 7.5a.75.75 0 1 0 1.5-.06l-.3-7.5Zm4.34.06a.75.75 0 1 0-1.5-.06l-.3 7.5a.75.75 0 1 0 1.5.06l.3-7.5Z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Loader -->
        <div class="mj-notification-bell__loader" aria-hidden="true">
            <svg class="mj-notification-bell__spinner" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        </div>
    </div>

    <!-- Overlay pour fermer en cliquant dehors -->
    <div class="mj-notification-bell__overlay" aria-hidden="true"></div>
<?php endif; ?>
</div>
