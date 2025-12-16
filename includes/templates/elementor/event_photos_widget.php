<?php

use Mj\Member\Core\AssetsManager;

if (!defined('ABSPATH')) {
    exit;
}

AssetsManager::requirePackage('event-photos');

$template_data = isset($template_data) && is_array($template_data) ? $template_data : array();

$title = isset($template_data['title']) ? (string) $template_data['title'] : '';
$description = isset($template_data['description']) ? (string) $template_data['description'] : '';
$events = isset($template_data['events']) && is_array($template_data['events']) ? $template_data['events'] : array();
$empty_message = isset($template_data['empty_message']) ? (string) $template_data['empty_message'] : __('Tu n’as pas encore participé à un événement. Inscris-toi pour partager tes souvenirs !', 'mj-member');
$notice = isset($template_data['notice']) && is_array($template_data['notice']) ? $template_data['notice'] : null;
$redirect_to = isset($template_data['redirect_to']) ? (string) $template_data['redirect_to'] : '';
$is_preview = !empty($template_data['is_preview']);

$header_preview_src = '';
$header_preview_status = '';

if (!empty($events)) {
    foreach ($events as $event_preview_candidate) {
        $candidate_previews = isset($event_preview_candidate['preview']) && is_array($event_preview_candidate['preview'])
            ? $event_preview_candidate['preview']
            : array();

        if (!empty($candidate_previews)) {
            foreach ($candidate_previews as $preview_item) {
                $candidate_thumb = isset($preview_item['thumb']) ? (string) $preview_item['thumb'] : '';
                $candidate_full = isset($preview_item['full']) && $preview_item['full'] !== ''
                    ? (string) $preview_item['full']
                    : (isset($preview_item['url']) ? (string) $preview_item['url'] : '');
                $candidate_src = $candidate_thumb !== '' ? $candidate_thumb : $candidate_full;

                if ($candidate_src !== '') {
                    $header_preview_src = $candidate_src;
                    $header_preview_status = isset($preview_item['status']) ? (string) $preview_item['status'] : '';
                    break 2;
                }
            }
        }

        $candidate_uploads = isset($event_preview_candidate['uploads']) && is_array($event_preview_candidate['uploads'])
            ? $event_preview_candidate['uploads']
            : array();

        if (!empty($candidate_uploads)) {
            foreach ($candidate_uploads as $upload_item) {
                $upload_thumb = isset($upload_item['thumb']) ? (string) $upload_item['thumb'] : '';
                $upload_full = isset($upload_item['full']) && $upload_item['full'] !== ''
                    ? (string) $upload_item['full']
                    : (isset($upload_item['url']) ? (string) $upload_item['url'] : '');
                $upload_src = $upload_thumb !== '' ? $upload_thumb : $upload_full;

                if ($upload_src !== '') {
                    $header_preview_src = $upload_src;
                    $header_preview_status = isset($upload_item['status']) ? (string) $upload_item['status'] : '';
                    break 2;
                }
            }
        }
    }
}

$header_illustration_classes = array('mj-event-photos-widget__header-illustration');
if ($header_preview_status !== '') {
    $header_illustration_classes[] = 'mj-event-photos-widget__header-illustration--' . sanitize_html_class($header_preview_status);
}

?>
<div class="mj-event-photos-widget" data-mj-component="mj-event-photos">
    <div class="mj-event-photos-widget__header">
        <div class="mj-event-photos-widget__header-main">
            <?php if ($title !== '') : ?>
                <h2 class="mj-event-photos-widget__title"><?php echo esc_html($title); ?></h2>
            <?php endif; ?>

            <?php if ($description !== '') : ?>
                <p class="mj-event-photos-widget__description"><?php echo wp_kses_post($description); ?></p>
            <?php endif; ?>
        </div>
        <div class="<?php echo esc_attr(implode(' ', $header_illustration_classes)); ?>" aria-hidden="true">
            <?php if ($header_preview_src !== '') : ?>
                <img src="<?php echo esc_url($header_preview_src); ?>" alt="" loading="lazy" />
            <?php else : ?>
                <span>📷</span>
            <?php endif; ?>
        </div>
    </div>

    <div class="mj-event-photos-widget__body">
        <?php if ($notice) :
            $notice_type = isset($notice['type']) ? sanitize_html_class((string) $notice['type']) : 'info';
        ?>
            <div class="mj-event-photos-widget__notice is-<?php echo esc_attr($notice_type); ?>" role="alert">
                <?php echo esc_html($notice['message']); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($events)) : ?>
            <p class="mj-event-photos-widget__empty"><?php echo esc_html($empty_message); ?></p>
        <?php else : ?>
            <ul class="mj-event-photos-widget__list">
            <?php foreach ($events as $index => $event) :
                $event_id = isset($event['event_id']) ? (int) $event['event_id'] : (isset($event['id']) ? (int) $event['id'] : 0);
                $event_title = isset($event['title']) ? (string) $event['title'] : '';
                $event_date = isset($event['date_label']) ? (string) $event['date_label'] : (isset($event['date']) ? (string) $event['date'] : '');
                $event_permalink = isset($event['permalink']) ? (string) $event['permalink'] : '';
                $uploads = isset($event['uploads']) && is_array($event['uploads']) ? $event['uploads'] : array();
                $preview_items = isset($event['preview']) && is_array($event['preview']) ? $event['preview'] : array();
                $preview_remaining = isset($event['preview_remaining']) ? (int) $event['preview_remaining'] : 0;
                $counts = isset($event['counts']) && is_array($event['counts']) ? $event['counts'] : array();
                $pending_count = isset($counts['pending']) ? (int) $counts['pending'] : 0;
                $approved_count = isset($counts['approved']) ? (int) $counts['approved'] : 0;
                $rejected_count = isset($counts['rejected']) ? (int) $counts['rejected'] : 0;
                $uploaded_total = isset($counts['total']) ? (int) $counts['total'] : count($uploads);
                $has_pending = $pending_count > 0;
                $limit = isset($event['limit']) ? (int) $event['limit'] : 0;
                $is_unlimited = !empty($event['is_unlimited']);
                $used_slots = isset($event['used_slots']) ? (int) $event['used_slots'] : $uploaded_total;
                $remaining = $is_unlimited
                    ? null
                    : (isset($event['remaining']) ? max(0, (int) $event['remaining']) : ($limit > 0 ? max(0, $limit - $used_slots) : 0));
                $progress_percent = ($limit > 0 && $used_slots >= 0)
                    ? max(0, min(100, ($used_slots / max(1, $limit)) * 100))
                    : 0;
                $can_upload = !empty($event['can_upload']);
                $reason = isset($event['reason']) ? (string) $event['reason'] : '';
                $panel_open = !empty($event['is_open']);
                $form_id = 'mj-event-photos-form-' . ($event_id > 0 ? $event_id : $index);
                $summary_total_label = sprintf(_n('%d photo', '%d photos', $uploaded_total, 'mj-member'), $uploaded_total);
                ?>
                <li class="mj-event-photos-widget__item">
                    <details class="mj-event-photos-widget__panel" data-event-id="<?php echo esc_attr($event_id); ?>"<?php echo $panel_open ? ' open' : ''; ?>>
                        <summary class="mj-event-photos-widget__summary">
                            <div class="mj-event-photos-widget__summary-main">
                                <div class="mj-event-photos-widget__summary-event">
                                    <span class="mj-event-photos-widget__item-title"><?php echo esc_html($event_title); ?></span>
                                    <?php if ($event_date !== '') : ?>
                                        <span class="mj-event-photos-widget__item-date"><?php echo esc_html($event_date); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="mj-event-photos-widget__summary-meta">
                                    <span class="mj-event-photos-widget__summary-chip mj-event-photos-widget__summary-chip--primary"><?php echo esc_html($summary_total_label); ?></span>
                                    <div class="mj-event-photos-widget__summary-chips">
                                        <?php if ($pending_count > 0) : ?>
                                            <span class="mj-event-photos-widget__summary-chip mj-event-photos-widget__summary-chip--pending"><?php echo esc_html(sprintf(_n('%d en attente', '%d en attente', $pending_count, 'mj-member'), $pending_count)); ?></span>
                                        <?php endif; ?>
                                        <?php if ($approved_count > 0) : ?>
                                            <span class="mj-event-photos-widget__summary-chip mj-event-photos-widget__summary-chip--approved"><?php echo esc_html(sprintf(_n('%d validée', '%d validées', $approved_count, 'mj-member'), $approved_count)); ?></span>
                                        <?php endif; ?>
                                        <?php if ($rejected_count > 0) : ?>
                                            <span class="mj-event-photos-widget__summary-chip mj-event-photos-widget__summary-chip--rejected"><?php echo esc_html(sprintf(_n('%d refusée', '%d refusées', $rejected_count, 'mj-member'), $rejected_count)); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <span class="mj-event-photos-widget__summary-toggle" aria-hidden="true"></span>
                                </div>
                            </div>

                            <div class="mj-event-photos-widget__summary-previews">
                                <?php if (!empty($preview_items)) : ?>
                                    <?php foreach ($preview_items as $preview) :
                                        $preview_status = isset($preview['status']) ? sanitize_html_class((string) $preview['status']) : '';
                                        $preview_label = isset($preview['status_label']) ? (string) $preview['status_label'] : '';
                                        $preview_thumb = isset($preview['thumb']) ? (string) $preview['thumb'] : '';
                                        $preview_full = isset($preview['full']) && $preview['full'] !== '' ? (string) $preview['full'] : (isset($preview['url']) ? (string) $preview['url'] : '');
                                        $preview_src = $preview_thumb !== '' ? $preview_thumb : $preview_full;
                                        ?>
                                        <span class="mj-event-photos-widget__summary-preview<?php echo $preview_status !== '' ? ' mj-event-photos-widget__summary-preview--' . esc_attr($preview_status) : ''; ?>">
                                            <?php if ($preview_src !== '') : ?>
                                                <img src="<?php echo esc_url($preview_src); ?>" alt="" loading="lazy" />
                                            <?php else : ?>
                                                <span aria-hidden="true">📷</span>
                                            <?php endif; ?>
                                            <?php if ($preview_label !== '') : ?>
                                                <span class="mj-event-photos-widget__summary-preview-badge mj-event-photos-widget__summary-preview-badge--<?php echo esc_attr($preview_status); ?>"><?php echo esc_html($preview_label); ?></span>
                                            <?php endif; ?>
                                        </span>
                                    <?php endforeach; ?>
                                    <?php if ($preview_remaining > 0) : ?>
                                        <span class="mj-event-photos-widget__summary-more">
                                            <?php echo esc_html(sprintf(__('+%d', 'mj-member'), $preview_remaining)); ?>
                                        </span>
                                    <?php endif; ?>
                                <?php else : ?>
                                    <span class="mj-event-photos-widget__summary-empty"><?php esc_html_e('Aucune photo envoyée pour le moment.', 'mj-member'); ?></span>
                                <?php endif; ?>
                            </div>
                        </summary>

                        <div class="mj-event-photos-widget__content">
                            <?php if ($event_permalink !== '') : ?>
                                <div class="mj-event-photos-widget__content-actions">
                                    <a class="mj-event-photos-widget__item-link" href="<?php echo esc_url($event_permalink); ?>">
                                        <?php esc_html_e("Voir l’événement", 'mj-member'); ?>
                                    </a>
                                </div>
                            <?php endif; ?>

                            <div class="mj-event-photos-widget__stats">
                                <div class="mj-event-photos-widget__stats-header">
                                    <span class="mj-event-photos-widget__stats-title"><?php esc_html_e('Progression des envois', 'mj-member'); ?></span>
                                    <span class="mj-event-photos-widget__stats-count">
                                        <?php
                                        if ($is_unlimited) {
                                            echo esc_html(__('Envois illimités pour cet événement', 'mj-member'));
                                        } elseif ($limit > 0) {
                                            /* translators: 1: used slots, 2: total limit */
                                            echo esc_html(sprintf(__('%1$d / %2$d photos utilisées', 'mj-member'), $used_slots, $limit));
                                        } else {
                                            /* translators: %d: total uploaded photos */
                                            echo esc_html(sprintf(_n('%d photo envoyée', '%d photos envoyées', $uploaded_total, 'mj-member'), $uploaded_total));
                                        }
                                        ?>
                                    </span>
                                </div>

                                <div class="mj-event-photos-widget__chip-group" role="list">
                                    <?php if ($pending_count > 0) : ?>
                                        <span class="mj-event-photos-widget__chip mj-event-photos-widget__chip--pending" role="listitem">
                                            <?php echo esc_html(sprintf(_n('%d en attente', '%d en attente', $pending_count, 'mj-member'), $pending_count)); ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($approved_count > 0) : ?>
                                        <span class="mj-event-photos-widget__chip mj-event-photos-widget__chip--approved" role="listitem">
                                            <?php echo esc_html(sprintf(_n('%d validée', '%d validées', $approved_count, 'mj-member'), $approved_count)); ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($rejected_count > 0) : ?>
                                        <span class="mj-event-photos-widget__chip mj-event-photos-widget__chip--rejected" role="listitem">
                                            <?php echo esc_html(sprintf(_n('%d refusée', '%d refusées', $rejected_count, 'mj-member'), $rejected_count)); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <?php if ($limit > 0) : ?>
                                    <div class="mj-event-photos-widget__progress" role="presentation">
                                        <span class="screen-reader-text"><?php echo esc_html(sprintf(__('%1$d sur %2$d photos utilisées', 'mj-member'), $used_slots, $limit)); ?></span>
                                        <span class="mj-event-photos-widget__progress-bar" style="--mj-event-photos-progress: <?php echo esc_attr($progress_percent); ?>%;"></span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="mj-event-photos-widget__columns">
                                <section class="mj-event-photos-widget__column mj-event-photos-widget__column--uploads">
                                    <?php if (!empty($uploads)) : ?>
                                        <?php if ($has_pending) :
                                            $pending_notice = $pending_count === 1
                                                ? __('Ta photo envoyée est en attente de validation par l’équipe.', 'mj-member')
                                                : sprintf(__('Tes %d photos envoyées sont en attente de validation par l’équipe.', 'mj-member'), $pending_count);
                                        ?>
                                            <p class="mj-event-photos-widget__info" role="status"><?php echo esc_html($pending_notice); ?></p>
                                        <?php endif; ?>

                                        <ul class="mj-event-photos-widget__uploads-list" aria-label="<?php echo esc_attr__('Liste des photos envoyées', 'mj-member'); ?>">
                                            <?php foreach ($uploads as $upload) :
                                                $upload_status = isset($upload['status']) ? sanitize_html_class((string) $upload['status']) : 'pending';
                                                $upload_status_label = isset($upload['status_label']) ? (string) $upload['status_label'] : ucfirst($upload_status);
                                                $thumb = isset($upload['thumb']) ? (string) $upload['thumb'] : '';
                                                $full = isset($upload['full']) && $upload['full'] !== '' ? (string) $upload['full'] : (isset($upload['url']) ? (string) $upload['url'] : '');
                                                $caption = isset($upload['caption']) ? (string) $upload['caption'] : '';
                                                $created_at = isset($upload['created_at']) ? (string) $upload['created_at'] : '';
                                                $preview_src = $thumb !== '' ? $thumb : $full;
                                                ?>
                                                <li class="mj-event-photos-widget__upload-card mj-event-photos-widget__upload-card--<?php echo esc_attr($upload_status); ?>">
                                                    <figure class="mj-event-photos-widget__upload-figure">
                                                        <div class="mj-event-photos-widget__upload-thumb">
                                                            <?php if ($preview_src !== '') : ?>
                                                                <img src="<?php echo esc_url($preview_src); ?>" alt="" loading="lazy" />
                                                            <?php else : ?>
                                                                <span class="mj-event-photos-widget__upload-placeholder" aria-hidden="true">📷</span>
                                                            <?php endif; ?>
                                                            <span class="mj-event-photos-widget__upload-status-chip mj-event-photos-widget__upload-status-chip--<?php echo esc_attr($upload_status); ?>"><?php echo esc_html($upload_status_label); ?></span>
                                                        </div>
                                                        <figcaption class="mj-event-photos-widget__upload-details">
                                                            <?php if ($caption !== '') : ?>
                                                                <p class="mj-event-photos-widget__upload-caption"><?php echo esc_html($caption); ?></p>
                                                            <?php else : ?>
                                                                <p class="mj-event-photos-widget__upload-caption mj-event-photos-widget__upload-caption--muted"><?php esc_html_e('Sans légende', 'mj-member'); ?></p>
                                                            <?php endif; ?>
                                                            <div class="mj-event-photos-widget__upload-meta">
                                                                <?php if ($created_at !== '') : ?>
                                                                    <span class="mj-event-photos-widget__upload-date"><?php echo esc_html($created_at); ?></span>
                                                                <?php endif; ?>
                                                                <?php if ($full !== '') : ?>
                                                                    <a class="mj-event-photos-widget__upload-view" href="<?php echo esc_url($full); ?>" target="_blank" rel="noopener">
                                                                        <?php esc_html_e('Voir en grand', 'mj-member'); ?>
                                                                    </a>
                                                                <?php endif; ?>
                                                            </div>
                                                            <?php if ($upload_status === 'rejected' && !empty($upload['rejection_reason'])) : ?>
                                                                <p class="mj-event-photos-widget__upload-reason">
                                                                    <strong><?php esc_html_e('Motif', 'mj-member'); ?> :</strong>
                                                                    <?php echo esc_html($upload['rejection_reason']); ?>
                                                                </p>
                                                            <?php endif; ?>
                                                            <?php if (!$is_preview && !empty($upload['can_delete']) && !empty($upload['id'])) : ?>
                                                                <div class="mj-event-photos-widget__upload-actions">
                                                                    <form class="mj-event-photos-widget__delete-form" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" onsubmit="return confirm('<?php echo esc_js(__('Supprimer cette photo ?', 'mj-member')); ?>');">
                                                                        <?php wp_nonce_field('mj-member-event-photo-delete', 'mj_event_photo_delete_nonce'); ?>
                                                                        <input type="hidden" name="action" value="mj_member_delete_event_photo" />
                                                                        <input type="hidden" name="photo_id" value="<?php echo esc_attr((string) $upload['id']); ?>" />
                                                                        <input type="hidden" name="event_id" value="<?php echo esc_attr($event_id); ?>" />
                                                                        <?php if ($redirect_to !== '') : ?>
                                                                            <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_to); ?>" />
                                                                        <?php endif; ?>
                                                                        <button class="mj-event-photos-widget__upload-delete" type="submit">
                                                                            <?php esc_html_e('Supprimer', 'mj-member'); ?>
                                                                        </button>
                                                                    </form>
                                                                </div>
                                                            <?php endif; ?>
                                                        </figcaption>
                                                    </figure>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else : ?>
                                        <div class="mj-event-photos-widget__uploads-empty">
                                            <span class="mj-event-photos-widget__uploads-empty-icon" aria-hidden="true">✨</span>
                                            <p><?php esc_html_e('Tu n’as pas encore partagé de photo pour cet événement.', 'mj-member'); ?></p>
                                            <p class="mj-event-photos-widget__uploads-empty-hint"><?php esc_html_e('Choisis une image ci-contre pour raconter ton meilleur souvenir.', 'mj-member'); ?></p>
                                        </div>
                                    <?php endif; ?>
                                </section>

                                <section class="mj-event-photos-widget__column mj-event-photos-widget__column--form">
                                    <?php if ($can_upload) : ?>
                                        <form id="<?php echo esc_attr($form_id); ?>" class="mj-event-photos-widget__form" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" enctype="multipart/form-data">
                                            <?php wp_nonce_field('mj-member-event-photo', 'mj_event_photo_nonce'); ?>
                                            <input type="hidden" name="action" value="mj_member_submit_event_photo" />
                                            <input type="hidden" name="event_id" value="<?php echo esc_attr($event_id); ?>" />
                                            <?php if ($redirect_to !== '') : ?>
                                                <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_to); ?>" />
                                            <?php endif; ?>

                                            <div class="mj-event-photos-widget__form-card">
                                                <h4 class="mj-event-photos-widget__form-title"><?php esc_html_e('Partager une nouvelle photo', 'mj-member'); ?></h4>
                                                <p class="mj-event-photos-widget__form-hint"><?php esc_html_e('Formats acceptés : JPG, PNG, HEIC – 10 Mo maximum.', 'mj-member'); ?></p>

                                                <div class="mj-event-photos-widget__field">
                                                    <label class="mj-event-photos-widget__field-label" for="<?php echo esc_attr($form_id); ?>-file"><?php esc_html_e('Sélectionne ton image', 'mj-member'); ?></label>
                                                    <input class="mj-event-photos-widget__file-input" id="<?php echo esc_attr($form_id); ?>-file" type="file" name="event_photo_file" accept="image/*" required <?php echo $is_preview ? 'disabled' : ''; ?> />
                                                </div>

                                                <div class="mj-event-photos-widget__field">
                                                    <label class="mj-event-photos-widget__field-label" for="<?php echo esc_attr($form_id); ?>-caption"><?php esc_html_e('Ajoute une légende (optionnel)', 'mj-member'); ?></label>
                                                    <textarea class="mj-event-photos-widget__textarea" id="<?php echo esc_attr($form_id); ?>-caption" name="photo_caption" maxlength="180" placeholder="<?php echo esc_attr__('Exemple : Notre équipe après le match !', 'mj-member'); ?>" <?php echo $is_preview ? 'disabled' : ''; ?>></textarea>
                                                </div>

                                                <div class="mj-event-photos-widget__consent">
                                                    <label class="mj-event-photos-widget__consent-label" for="<?php echo esc_attr($form_id); ?>-consent">
                                                        <input class="mj-event-photos-widget__consent-checkbox" id="<?php echo esc_attr($form_id); ?>-consent" type="checkbox" name="mj_event_photo_consent" value="1" <?php echo $is_preview ? 'disabled' : 'required'; ?> />
                                                        <?php esc_html_e('Je confirme avoir l’autorisation des personnes visibles et j’accepte la publication selon les règles RGPD de la MJ.', 'mj-member'); ?>
                                                    </label>
                                                    <p class="mj-event-photos-widget__consent-hint"><?php esc_html_e('Ne partage pas d’image contenant d’autres personnes sans leur accord explicite.', 'mj-member'); ?></p>
                                                </div>

                                                <div class="mj-event-photos-widget__footer">
                                                    <p class="mj-event-photos-widget__remaining">
                                                        <?php if ($is_unlimited) : ?>
                                                            <?php esc_html_e('Tu peux ajouter autant de photos que nécessaire pour cet événement.', 'mj-member'); ?>
                                                        <?php else : ?>
                                                            <?php
                                                            /* Translators: %d is the number of uploads remaining. */
                                                            echo esc_html(sprintf(_n('Encore %d envoi possible pour cet événement.', 'Encore %d envois possibles pour cet événement.', $remaining, 'mj-member'), $remaining));
                                                            ?>
                                                        <?php endif; ?>
                                                    </p>

                                                    <button class="mj-event-photos-widget__submit" type="submit" <?php echo $is_preview ? 'disabled' : ''; ?> >
                                                        <?php esc_html_e('Envoyer ma photo', 'mj-member'); ?>
                                                    </button>
                                                </div>
                                            </div>
                                        </form>
                                    <?php elseif ($reason !== '') : ?>
                                        <div class="mj-event-photos-widget__reason">
                                            <p><?php echo esc_html($reason); ?></p>
                                            <?php if ($limit > 0) : ?>
                                                <p class="mj-event-photos-widget__reason-hint"><?php echo esc_html__('Supprime une photo existante si tu veux en proposer une nouvelle.', 'mj-member'); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </section>
                            </div>
                        </div>
                    </details>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
</div>
