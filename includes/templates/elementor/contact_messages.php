<?php

use Mj\Member\Core\AssetsManager;

if (!defined('ABSPATH')) {
    exit;
}

AssetsManager::requirePackage('contact-messages');

$template_data = isset($template_data) && is_array($template_data) ? $template_data : array();

$title = isset($template_data['title']) ? (string) $template_data['title'] : '';
$description = isset($template_data['description']) ? (string) $template_data['description'] : '';
$messages = isset($template_data['messages']) && is_array($template_data['messages']) ? $template_data['messages'] : array();
$has_permission = !empty($template_data['has_permission']);
$can_view = !empty($template_data['can_view']);
$can_moderate = !empty($template_data['can_moderate']);
$owner_view_mode = !empty($template_data['owner_view']);
$empty_text = isset($template_data['empty_text']) ? (string) $template_data['empty_text'] : '';
$restricted_text = isset($template_data['restricted_text']) ? (string) $template_data['restricted_text'] : '';
$view_all_url = isset($template_data['view_all_url']) ? (string) $template_data['view_all_url'] : '';
$is_preview = !empty($template_data['is_preview']);
$redirect_base = isset($template_data['redirect_base']) ? (string) $template_data['redirect_base'] : '';
$owner_reply_config = isset($template_data['owner_reply']) && is_array($template_data['owner_reply']) ? $template_data['owner_reply'] : array();
$owner_reply_enabled = !empty($owner_reply_config['enabled']);
$owner_reply_can_send = !empty($owner_reply_config['can_send']);
$owner_reply_ajax_url = isset($owner_reply_config['ajax_url']) ? (string) $owner_reply_config['ajax_url'] : '';
$owner_reply_nonce = isset($owner_reply_config['nonce']) ? (string) $owner_reply_config['nonce'] : '';
$owner_reply_sender_name = isset($owner_reply_config['sender_name']) ? (string) $owner_reply_config['sender_name'] : '';
$owner_reply_sender_email = isset($owner_reply_config['sender_email']) ? (string) $owner_reply_config['sender_email'] : '';
$owner_reply_member_id = isset($owner_reply_config['member_id']) ? (int) $owner_reply_config['member_id'] : 0;
$owner_reply_source = isset($owner_reply_config['source']) ? (string) $owner_reply_config['source'] : '';
$owner_reply_ready = $owner_reply_enabled && $owner_reply_can_send && $owner_reply_ajax_url !== '' && $owner_reply_nonce !== '';
$search_input_id = function_exists('wp_unique_id') ? wp_unique_id('mj-contact-messages-search-') : 'mj-contact-messages-search-' . uniqid();

$notice_key = isset($_GET['mj_contact_notice']) ? sanitize_key(wp_unslash($_GET['mj_contact_notice'])) : '';
$notice_message = '';
$notice_type = 'success';
$notice_detail = '';
if ($notice_key !== '') {
    switch ($notice_key) {
        case 'read-updated':
            $notice_message = __('État de lecture mis à jour.', 'mj-member');
            break;
        case 'reply-sent':
            $notice_message = __('Réponse envoyée à l’expéditeur.', 'mj-member');
            break;
        case 'reply-error':
            $notice_message = __('Impossible d’envoyer la réponse. Vérifiez le contenu et l’adresse email.', 'mj-member');
            $notice_type = 'error';
            break;
        case 'reply-error-nonce':
            $notice_message = __('Votre session a expiré. Rafraîchissez la page avant de réessayer.', 'mj-member');
            $notice_type = 'error';
            break;
        case 'reply-error-email':
            $notice_message = __('Adresse email de l’expéditeur invalide. Réponse impossible.', 'mj-member');
            $notice_type = 'error';
            break;
        case 'reply-error-content':
            $notice_message = __('Le sujet et le message sont requis pour envoyer une réponse.', 'mj-member');
            $notice_type = 'error';
            break;
        case 'reply-error-missing':
            $notice_message = __('Ce message n’est plus disponible ou ne vous est plus assigné.', 'mj-member');
            $notice_type = 'error';
            break;
        case 'reply-error-mail':
            $notice_message = __('L’envoi de l’email a échoué. Merci de réessayer ou de contacter un administrateur.', 'mj-member');
            $notice_type = 'error';
            if (function_exists('mj_member_contact_messages_consume_mail_error')) {
                $mail_error = mj_member_contact_messages_consume_mail_error();
                if (is_array($mail_error) && !empty($mail_error['message'])) {
                    $notice_detail = sprintf(__('Détail : %s', 'mj-member'), $mail_error['message']);
                }
            }
            break;
        case 'deleted':
            $notice_message = __('Message supprimé.', 'mj-member');
            break;
        case 'delete-error':
            $notice_message = __('Impossible de supprimer le message.', 'mj-member');
            $notice_type = 'error';
            break;
        case 'read-error':
            $notice_message = __('Impossible de mettre à jour l’état de lecture.', 'mj-member');
            $notice_type = 'error';
            break;
        case 'archived':
            $notice_message = __('Message archivé.', 'mj-member');
            break;
        case 'restored':
            $notice_message = __('Message restauré.', 'mj-member');
            break;
        case 'archive-error':
            $notice_message = __('Impossible d’archiver le message.', 'mj-member');
            $notice_type = 'error';
            break;
        default:
            $notice_message = '';
    }
}

if ($notice_message !== '' && $notice_detail !== '') {
    $notice_message = trim($notice_message . ' ' . $notice_detail);
}

?>
<div class="mj-contact-messages" data-mj-component="mj-contact-messages">
    <div class="mj-contact-messages__surface">
        <?php if ($title !== '') : ?>
            <h2 class="mj-contact-messages__title"><?php echo esc_html($title); ?></h2>
        <?php endif; ?>

        <?php if ($description !== '') : ?>
            <p class="mj-contact-messages__description"><?php echo esc_html($description); ?></p>
        <?php endif; ?>

        <?php if ($notice_message !== '') : ?>
            <div class="mj-contact-messages__flash mj-contact-messages__flash--<?php echo esc_attr($notice_type); ?>">
                <?php echo esc_html($notice_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($can_view) : ?>
            <div class="mj-contact-messages__toolbar" data-mj-element="toolbar">
                <div class="mj-contact-messages__filters" role="group" aria-label="<?php esc_attr_e('Filtrer les messages', 'mj-member'); ?>">
                    <button type="button" class="mj-contact-messages__filter-button is-active" data-filter="all" aria-pressed="true"><?php esc_html_e('Tous', 'mj-member'); ?></button>
                    <button type="button" class="mj-contact-messages__filter-button" data-filter="unread" aria-pressed="false"><?php esc_html_e('Non lus', 'mj-member'); ?></button>
                    <button type="button" class="mj-contact-messages__filter-button" data-filter="archived" aria-pressed="false"><?php esc_html_e('Archivés', 'mj-member'); ?></button>
                </div>
                <div class="mj-contact-messages__search">
                    <label class="screen-reader-text" for="<?php echo esc_attr($search_input_id); ?>"><?php esc_html_e('Rechercher dans les messages', 'mj-member'); ?></label>
                    <input type="search" id="<?php echo esc_attr($search_input_id); ?>" class="mj-contact-messages__search-input" placeholder="<?php esc_attr_e('Rechercher un message…', 'mj-member'); ?>" autocomplete="off">
                </div>
            </div>
        <?php endif; ?>

        <?php if (!$can_view) : ?>
            <div class="mj-contact-messages__notice">
                <?php echo esc_html($restricted_text); ?>
            </div>
        <?php elseif (empty($messages)) : ?>
            <div class="mj-contact-messages__empty">
                <?php echo esc_html($empty_text); ?>
            </div>
        <?php else : ?>
            <ul class="mj-contact-messages__list">
                <?php foreach ($messages as $message) :
                    $subject = isset($message['subject']) ? (string) $message['subject'] : '';
                    $status_key = isset($message['status_key']) ? sanitize_key((string) $message['status_key']) : '';
                    $status_label = isset($message['status_label']) ? (string) $message['status_label'] : '';
                    $target_label = isset($message['target_label']) ? (string) $message['target_label'] : '';
                    $sender_name = isset($message['sender_name']) ? (string) $message['sender_name'] : '';
                    $sender_email = isset($message['sender_email']) ? (string) $message['sender_email'] : '';
                    $date_human = isset($message['date_human']) ? (string) $message['date_human'] : '';
                    $time_human = isset($message['time_human']) ? (string) $message['time_human'] : '';
                    $excerpt = isset($message['excerpt']) ? (string) $message['excerpt'] : '';
                    $view_url = isset($message['view_url']) ? (string) $message['view_url'] : '';
                    $is_unread = !empty($message['is_unread']);
                    $message_id = isset($message['id']) ? (int) $message['id'] : 0;
                    $toggle_nonce = isset($message['toggle_nonce']) ? (string) $message['toggle_nonce'] : '';
                    $reply_nonce = isset($message['reply_nonce']) ? (string) $message['reply_nonce'] : '';
                    $reply_subject = isset($message['reply_subject']) ? (string) $message['reply_subject'] : '';
                    $sender_has_email = $sender_email !== '' && is_email($sender_email);
                    $toggle_state = $is_unread ? 'read' : 'unread';
                    $mark_read_label = __('Marquer comme lu', 'mj-member');
                    $mark_unread_label = __('Marquer comme non lu', 'mj-member');
                    $toggle_action_label = $is_unread ? $mark_read_label : $mark_unread_label;
                    $state_indicator_class = $is_unread ? ' is-unread' : ' is-read';
                    $toggle_action_class = $toggle_state === 'read' ? ' is-action-read' : ' is-action-unread';
                    $is_archived = !empty($message['is_archived']);
                    $archive_nonce = isset($message['archive_nonce']) ? (string) $message['archive_nonce'] : '';
                    $delete_nonce = isset($message['delete_nonce']) ? (string) $message['delete_nonce'] : '';
                    $search_terms = isset($message['search_terms']) ? (string) $message['search_terms'] : '';
                    $item_classes = 'mj-contact-messages__item';
                    if ($is_unread) {
                        $item_classes .= ' is-unread';
                    }
                    if ($is_archived) {
                        $item_classes .= ' is-archived';
                    }

                    $sender_parts = array();
                    if ($sender_name !== '') {
                        $sender_parts[] = $sender_name;
                    }
                    if ($sender_email !== '') {
                        $sender_parts[] = $sender_email;
                    }
                    $sender_display = implode(' | ', $sender_parts);

                    $datetime_parts = array();
                    if ($date_human !== '') {
                        $datetime_parts[] = $date_human;
                    }
                    if ($time_human !== '') {
                        $datetime_parts[] = $time_human;
                    }
                    $datetime_display = implode(' | ', $datetime_parts);

                    $message_can_moderate = !empty($message['can_moderate']);
                    $message_owner_view = !empty($message['owner_view']) || $owner_view_mode;
                    $full_message_raw = isset($message['full_message_raw']) ? (string) $message['full_message_raw'] : '';
                    $activity_entries = isset($message['activity']) && is_array($message['activity']) ? $message['activity'] : array();
                    if (!empty($activity_entries)) {
                        $activity_entries = array_values(array_filter($activity_entries, static function ($entry) {
                            if (!is_array($entry)) {
                                return false;
                            }
                            $activity_action = isset($entry['action']) ? (string) $entry['action'] : '';
                            return !in_array($activity_action, array('marked_read', 'marked_unread', 'status_changed', 'created'), true);
                        }));
                    }
                    $recipient_choice = isset($message['recipient_choice']) ? (string) $message['recipient_choice'] : '';
                    $quick_reply_subject = isset($message['quick_reply_subject']) ? (string) $message['quick_reply_subject'] : '';
                    $quick_reply_available = $message_owner_view && $owner_reply_ready && $recipient_choice !== '';
                    $panel_open = false;
                    ?>
                    <li class="<?php echo esc_attr($item_classes); ?>" data-message-id="<?php echo esc_attr($message_id); ?>" data-status-key="<?php echo esc_attr($status_key); ?>" data-is-unread="<?php echo $is_unread ? '1' : '0'; ?>" data-is-archived="<?php echo $is_archived ? '1' : '0'; ?>" data-search="<?php echo esc_attr($search_terms); ?>">
                        <details class="mj-contact-messages__panel" data-message-id="<?php echo esc_attr($message_id); ?>"<?php echo $panel_open ? ' open' : ''; ?>>
                            <summary class="mj-contact-messages__summary">
                                <div class="mj-contact-messages__summary-main">
                                    <div class="mj-contact-messages__summary-heading">
                                        <span class="mj-contact-messages__subject"><?php echo esc_html($subject); ?></span>
                                        <?php if ($status_label !== '') : ?>
                                            <span class="mj-contact-messages__status<?php echo $status_key !== '' ? ' mj-contact-messages__status--' . esc_attr($status_key) : ''; ?>"><?php echo esc_html($status_label); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="mj-contact-messages__summary-meta">
                                        <?php if ($target_label !== '') : ?>
                                            <span class="mj-contact-messages__summary-chip mj-contact-messages__summary-chip--target"><?php echo esc_html($target_label); ?></span>
                                        <?php endif; ?>
                                        <?php if ($sender_display !== '') : ?>
                                            <span class="mj-contact-messages__summary-chip mj-contact-messages__summary-chip--sender"><?php echo esc_html($sender_display); ?></span>
                                        <?php endif; ?>
                                        <?php if ($datetime_display !== '') : ?>
                                            <span class="mj-contact-messages__summary-chip mj-contact-messages__summary-chip--date"><?php echo esc_html($datetime_display); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="mj-contact-messages__summary-actions">
                                    <?php if ($message_can_moderate && $toggle_nonce !== '' && $message_id > 0) : ?>
                                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="mj-contact-messages__toggle-form" data-message-id="<?php echo esc_attr($message_id); ?>">
                                            <input type="hidden" name="action" value="mj_member_toggle_contact_message_read">
                                            <input type="hidden" name="message_id" value="<?php echo esc_attr($message_id); ?>">
                                            <input type="hidden" name="target_state" value="<?php echo esc_attr($toggle_state); ?>">
                                            <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_base); ?>">
                                            <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($toggle_nonce); ?>">
                                            <button type="submit" class="mj-contact-messages__toggle-button<?php echo esc_attr($toggle_action_class); ?>" data-target-state="<?php echo esc_attr($toggle_state); ?>" data-current-state="<?php echo $is_unread ? 'unread' : 'read'; ?>" data-label-read="<?php echo esc_attr($mark_read_label); ?>" data-label-unread="<?php echo esc_attr($mark_unread_label); ?>" title="<?php echo esc_attr($toggle_action_label); ?>" aria-label="<?php echo esc_attr($toggle_action_label); ?>">
                                                <span class="mj-contact-messages__state-indicator<?php echo esc_attr($state_indicator_class); ?>" aria-hidden="true"></span>
                                                <span class="mj-contact-messages__toggle-text"><?php echo esc_html($toggle_action_label); ?></span>
                                            </button>
                                        </form>
                                    <?php elseif ($message_owner_view) : ?>
                                        <span class="mj-contact-messages__state-pill<?php echo $is_unread ? ' is-unread' : ' is-read'; ?>">
                                            <span class="mj-contact-messages__state-indicator<?php echo esc_attr($state_indicator_class); ?>" aria-hidden="true"></span>
                                            <span class="mj-contact-messages__state-text"><?php echo $is_unread ? esc_html__('Non lu', 'mj-member') : esc_html__('Lu', 'mj-member'); ?></span>
                                        </span>
                                    <?php endif; ?>
                                    <span class="mj-contact-messages__summary-toggle" aria-hidden="true"></span>
                                </div>
                            </summary>

                            <div class="mj-contact-messages__panel-body">
                                <?php if (($message_owner_view || $message_can_moderate) && $full_message_raw !== '') : ?>
                                    <div class="mj-contact-messages__content">
                                        <?php echo wp_kses_post(wpautop($full_message_raw)); ?>
                                    </div>
                                <?php elseif ($excerpt !== '') : ?>
                                    <p class="mj-contact-messages__excerpt"><?php echo esc_html($excerpt); ?></p>
                                <?php endif; ?>

                                <?php if (!empty($activity_entries)) : ?>
                                    <ul class="mj-contact-messages__activity">
                                        <?php foreach ($activity_entries as $entry) :
                                            $activity_action = isset($entry['action']) ? (string) $entry['action'] : '';
                                            $activity_note = isset($entry['note']) ? (string) $entry['note'] : '';
                                            $activity_time = isset($entry['time_human']) ? (string) $entry['time_human'] : '';
                                            $entry_meta = isset($entry['meta']) && is_array($entry['meta']) ? $entry['meta'] : array();
                                            $activity_body = isset($entry_meta['body']) ? (string) $entry_meta['body'] : '';
                                            $activity_author = isset($entry_meta['author_name']) ? (string) $entry_meta['author_name'] : '';
                                            $activity_heading = '';

                                            if ($activity_action === 'reply_sent') {
                                                $activity_heading = __('Réponse de l’équipe MJ', 'mj-member');
                                            } elseif ($activity_action === 'reply_owner') {
                                                $activity_heading = __('Votre réponse', 'mj-member');
                                            }
                                            ?>
                                            <li class="mj-contact-messages__activity-item">
                                                <div class="mj-contact-messages__activity-header">
                                                    <?php if ($activity_time !== '') : ?>
                                                        <span class="mj-contact-messages__activity-time"><?php echo esc_html($activity_time); ?></span>
                                                    <?php endif; ?>
                                                    <?php if ($activity_heading !== '') : ?>
                                                        <span class="mj-contact-messages__activity-heading"><?php echo esc_html($activity_heading); ?></span>
                                                    <?php elseif ($activity_note !== '') : ?>
                                                        <span class="mj-contact-messages__activity-note"><?php echo esc_html($activity_note); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if ($activity_heading !== '' && $activity_note !== '') : ?>
                                                    <div class="mj-contact-messages__activity-note"><?php echo esc_html($activity_note); ?></div>
                                                <?php endif; ?>
                                                <?php if ($activity_author !== '') : ?>
                                                    <div class="mj-contact-messages__activity-author"><?php echo esc_html($activity_author); ?></div>
                                                <?php endif; ?>
                                                <?php if ($activity_body !== '') : ?>
                                                    <div class="mj-contact-messages__activity-body"><?php echo wp_kses_post(wpautop($activity_body)); ?></div>
                                                <?php endif; ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>

                                <?php if ($quick_reply_available) : ?>
                                    <details class="mj-contact-messages__quick-reply" data-message-id="<?php echo esc_attr($message_id); ?>">
                                        <summary>
                                            <span class="mj-contact-messages__quick-reply-icon" aria-hidden="true"></span>
                                            <span><?php esc_html_e('Répondre à ce message', 'mj-member'); ?></span>
                                        </summary>
                                        <div class="mj-contact-messages__form-shell">
                                            <form class="mj-contact-messages__quick-reply-form"
                                                data-ajax-url="<?php echo esc_url($owner_reply_ajax_url); ?>"
                                                data-nonce="<?php echo esc_attr($owner_reply_nonce); ?>"
                                                data-recipient="<?php echo esc_attr($recipient_choice); ?>"
                                                data-subject="<?php echo esc_attr($quick_reply_subject); ?>"
                                                data-parent-id="<?php echo esc_attr($message_id); ?>"
                                                data-sender-name="<?php echo esc_attr($owner_reply_sender_name); ?>"
                                                data-sender-email="<?php echo esc_attr($owner_reply_sender_email); ?>"
                                                data-member-id="<?php echo esc_attr($owner_reply_member_id); ?>"
                                                data-source="<?php echo esc_attr($owner_reply_source); ?>"
                                            >
                                                <div class="mj-contact-messages__field">
                                                    <label for="mj-contact-quick-reply-body-<?php echo esc_attr($message_id); ?>"><?php esc_html_e('Votre réponse', 'mj-member'); ?></label>
                                                    <textarea id="mj-contact-quick-reply-body-<?php echo esc_attr($message_id); ?>" name="reply_body" rows="4" required placeholder="<?php esc_attr_e('Écrivez votre réponse...', 'mj-member'); ?>"></textarea>
                                                </div>
                                                <div class="mj-contact-messages__quick-reply-controls">
                                                    <button type="submit" class="mj-contact-messages__action-btn mj-contact-messages__action-btn--reply"><?php esc_html_e('Envoyer', 'mj-member'); ?></button>
                                                    <span class="mj-contact-messages__quick-reply-feedback" aria-live="polite"></span>
                                                </div>
                                            </form>
                                        </div>
                                    </details>
                                <?php elseif ($message_owner_view && $owner_reply_enabled && !$owner_reply_can_send && !$is_preview) : ?>
                                    <p class="mj-contact-messages__quick-reply-hint"><?php esc_html_e('Ajoutez une adresse email à votre profil pour répondre depuis cet espace.', 'mj-member'); ?></p>
                                <?php endif; ?>

                                <?php if ($message_can_moderate || $is_preview) : ?>
                                    <div class="mj-contact-messages__actions">
                                        <?php if ($is_preview && !$message_can_moderate) : ?>
                                            <span class="mj-contact-messages__link mj-contact-messages__link--preview"><?php esc_html_e('Action de prévisualisation', 'mj-member'); ?></span>
                                        <?php endif; ?>

                                        <?php if ($message_can_moderate && $sender_has_email && $reply_nonce !== '' && $message_id > 0) : ?>
                                            <details class="mj-contact-messages__reply" data-message-id="<?php echo esc_attr($message_id); ?>">
                                                <summary>
                                                    <span class="mj-contact-messages__quick-reply-icon" aria-hidden="true"></span>
                                                    <span><?php esc_html_e('Répondre', 'mj-member'); ?></span>
                                                </summary>
                                                <div class="mj-contact-messages__form-shell">
                                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="mj-contact-messages__reply-form">
                                                        <input type="hidden" name="action" value="mj_member_reply_contact_message">
                                                        <input type="hidden" name="message_id" value="<?php echo esc_attr($message_id); ?>">
                                                        <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_base); ?>">
                                                        <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($reply_nonce); ?>">
                                                        <div class="mj-contact-messages__form-row">
                                                            <div class="mj-contact-messages__field">
                                                                <label for="mj-contact-reply-subject-<?php echo esc_attr($message_id); ?>"><?php esc_html_e('Sujet', 'mj-member'); ?></label>
                                                                <input type="text" id="mj-contact-reply-subject-<?php echo esc_attr($message_id); ?>" name="reply_subject" value="<?php echo esc_attr($reply_subject); ?>" required>
                                                            </div>
                                                        </div>
                                                        <div class="mj-contact-messages__form-row">
                                                            <div class="mj-contact-messages__field">
                                                                <label for="mj-contact-reply-body-<?php echo esc_attr($message_id); ?>"><?php esc_html_e('Message', 'mj-member'); ?></label>
                                                                <textarea id="mj-contact-reply-body-<?php echo esc_attr($message_id); ?>" name="reply_body" rows="5" required placeholder="<?php esc_attr_e('Saisissez votre réponse pour le destinataire…', 'mj-member'); ?>"></textarea>
                                                            </div>
                                                        </div>
                                                        <div class="mj-contact-messages__form-row mj-contact-messages__form-row--inline">
                                                            <label class="mj-contact-messages__checkbox">
                                                                <input type="checkbox" name="reply_copy" value="1">
                                                                <span><?php esc_html_e('M’envoyer une copie', 'mj-member'); ?></span>
                                                            </label>
                                                        </div>
                                                        <div class="mj-contact-messages__reply-actions">
                                                            <button type="submit" class="mj-contact-messages__action-btn mj-contact-messages__action-btn--reply"><?php esc_html_e('Envoyer la réponse', 'mj-member'); ?></button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </details>
                                        <?php elseif ($message_can_moderate && !$sender_has_email) : ?>
                                            <span class="mj-contact-messages__link mj-contact-messages__link--preview"><?php esc_html_e('Adresse email manquante', 'mj-member'); ?></span>
                                        <?php endif; ?>

                                        <?php if ($message_can_moderate && $message_id > 0 && !$is_archived && $archive_nonce !== '') : ?>
                                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="mj-contact-messages__action-form mj-contact-messages__archive-form">
                                                <input type="hidden" name="action" value="mj_member_archive_contact_message">
                                                <input type="hidden" name="message_id" value="<?php echo esc_attr($message_id); ?>">
                                                <input type="hidden" name="target_state" value="archive">
                                                <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_base); ?>">
                                                <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($archive_nonce); ?>">
                                                <button type="submit" class="mj-contact-messages__action-btn mj-contact-messages__action-btn--archive"><?php esc_html_e('Archiver', 'mj-member'); ?></button>
                                            </form>
                                        <?php elseif ($message_can_moderate && $is_archived) : ?>
                                            <span class="mj-contact-messages__archive-label"><?php esc_html_e('Archivé', 'mj-member'); ?></span>
                                        <?php endif; ?>

                                        <?php if ($message_can_moderate && $message_id > 0 && $delete_nonce !== '') : ?>
                                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="mj-contact-messages__action-form mj-contact-messages__delete-form" data-confirm="<?php echo esc_attr(__('Voulez-vous vraiment supprimer ce message ?', 'mj-member')); ?>">
                                                <input type="hidden" name="action" value="mj_member_delete_contact_message">
                                                <input type="hidden" name="message_id" value="<?php echo esc_attr($message_id); ?>">
                                                <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_base); ?>">
                                                <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($delete_nonce); ?>">
                                                <button type="submit" class="mj-contact-messages__action-btn mj-contact-messages__action-btn--delete"><?php esc_html_e('Supprimer', 'mj-member'); ?></button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if ($message_can_moderate && $view_url !== '' && $view_url !== '#') : ?>
                                            <a class="mj-contact-messages__link mj-contact-messages__link--admin" href="<?php echo esc_url($view_url); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Admin', 'mj-member'); ?></a>
                                        <?php elseif ($is_preview && $view_url === '#') : ?>
                                            <span class="mj-contact-messages__link mj-contact-messages__link--preview"><?php esc_html_e('Prévisualisation', 'mj-member'); ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </details>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="mj-contact-messages__filter-empty" data-mj-element="filter-empty" aria-live="polite" hidden>
                <?php esc_html_e('Aucun message ne correspond à ces critères.', 'mj-member'); ?>
            </div>
        <?php endif; ?>

        <?php if ($can_moderate && $view_all_url !== '') : ?>
            <div class="mj-contact-messages__footer">
                <a class="mj-contact-messages__view-all" href="<?php echo esc_url($view_all_url); ?>" target="_blank" rel="noopener noreferrer">
                    <?php esc_html_e('Voir tous les messages dans l\'admin', 'mj-member'); ?>
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>
