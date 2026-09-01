<?php
if (!defined('ABSPATH')) {
    exit;
}

$photos_data = isset($photos_view) && is_array($photos_view)
    ? $photos_view
    : array();

$photo_notice_data = isset($photos_data['notice']) && is_array($photos_data['notice'])
    ? $photos_data['notice']
    : (isset($photo_notice) && is_array($photo_notice) ? $photo_notice : null);

$photo_has_items_value = array_key_exists('has_items', $photos_data)
    ? !empty($photos_data['has_items'])
    : !empty($photo_has_items);

$photo_can_upload_value = array_key_exists('can_upload', $photos_data)
    ? !empty($photos_data['can_upload'])
    : !empty($photo_can_upload);

if (!$photo_notice_data && !$photo_has_items_value && !$photo_can_upload_value) {
    return;
}

$photo_total_value = isset($photos_data['total'])
    ? (int) $photos_data['total']
    : (isset($photo_total) ? (int) $photo_total : 0);

$photo_items_list = isset($photos_data['items']) && is_array($photos_data['items'])
    ? $photos_data['items']
    : (isset($photo_items) && is_array($photo_items) ? $photo_items : array());

$photo_pending_data = isset($photos_data['pending']) && is_array($photos_data['pending'])
    ? $photos_data['pending']
    : array();

$photo_member_pending_has_value = array_key_exists('has', $photo_pending_data)
    ? !empty($photo_pending_data['has'])
    : !empty($photo_member_pending_has);

$photo_member_pending_count_value = isset($photo_pending_data['count'])
    ? (int) $photo_pending_data['count']
    : (isset($photo_member_pending_count) ? (int) $photo_member_pending_count : 0);

$photo_member_pending_items_list = isset($photo_pending_data['items']) && is_array($photo_pending_data['items'])
    ? $photo_pending_data['items']
    : (isset($photo_member_pending_items) && is_array($photo_member_pending_items) ? $photo_member_pending_items : array());

$photo_is_unlimited_value = array_key_exists('is_unlimited', $photos_data)
    ? !empty($photos_data['is_unlimited'])
    : !empty($photo_is_unlimited);

$photo_member_remaining_value = null;
if (array_key_exists('member_remaining', $photos_data)) {
    $photo_member_remaining_value = $photos_data['member_remaining'];
} elseif (isset($photo_member_remaining)) {
    $photo_member_remaining_value = $photo_member_remaining;
}
$photo_member_remaining_value = $photo_member_remaining_value === null
    ? null
    : max(0, (int) $photo_member_remaining_value);

$photo_reason_value = isset($photos_data['reason'])
    ? (string) $photos_data['reason']
    : (isset($photo_reason) ? (string) $photo_reason : '');

$event_title_value = isset($photos_data['event_title']) && $photos_data['event_title'] !== ''
    ? (string) $photos_data['event_title']
    : (isset($title) ? (string) $title : '');

$photo_notice = $photo_notice_data;
$photo_has_items = $photo_has_items_value;
$photo_can_upload = $photo_can_upload_value;
$photo_total = $photo_total_value;
$photo_items = $photo_items_list;
$photo_member_pending_has = $photo_member_pending_has_value;
$photo_member_pending_count = $photo_member_pending_count_value;
$photo_member_pending_items = $photo_member_pending_items_list;
$photo_is_unlimited = $photo_is_unlimited_value;
$photo_member_remaining = $photo_member_remaining_value === null ? 0 : $photo_member_remaining_value;
$photo_reason = $photo_reason_value;
$photos_event_title = $event_title_value;
?>
<section class="mj-member-event-single__card mj-member-event-single__photos">
    <div class="mj-member-event-single__photos-header">
        <h2><?php echo esc_html__('Souvenirs partagés', 'mj-member'); ?></h2>
        <?php if ($photo_total > 0) : ?>
        <span class="mj-member-event-single__photo-count"><?php echo esc_html(sprintf(_n('%d photo validée', '%d photos validées', $photo_total, 'mj-member'), $photo_total)); ?></span>
        <?php endif; ?>
    </div>

    <?php if ($photo_notice) :
        $notice_type = isset($photo_notice['type']) ? sanitize_html_class((string) $photo_notice['type']) : 'info';
    ?>
    <p class="mj-member-event-single__photo-notice is-<?php echo esc_attr($notice_type); ?>"><?php echo esc_html($photo_notice['message']); ?></p>
    <?php endif; ?>

    <?php if ($photo_member_pending_has) :
        $pending_message = $photo_member_pending_count === 1
            ? __('Ta photo ci-dessous est en attente de validation par l’équipe.', 'mj-member')
            : sprintf(__('Tes %d photos ci-dessous sont en attente de validation par l’équipe.', 'mj-member'), $photo_member_pending_count);
    ?>
    <div class="mj-member-event-single__photo-pending" role="status">
        <p class="mj-member-event-single__photo-pending-message"><?php echo esc_html($pending_message); ?></p>
        <ul class="mj-member-event-single__photo-pending-list">
            <?php foreach ($photo_member_pending_items as $pending_entry) :
                $pending_thumb = isset($pending_entry['thumb']) ? $pending_entry['thumb'] : '';
                $pending_display = isset($pending_entry['display']) ? $pending_entry['display'] : '';
                $pending_full = isset($pending_entry['full']) && $pending_entry['full'] !== '' ? $pending_entry['full'] : $pending_display;
                $pending_caption = isset($pending_entry['caption']) ? $pending_entry['caption'] : '';
                $pending_date = isset($pending_entry['submitted']) ? $pending_entry['submitted'] : '';
                $pending_alt = $pending_caption !== ''
                    ? $pending_caption
                    : ($pending_date !== '' ? sprintf(__('Photo envoyée le %s', 'mj-member'), $pending_date) : __('Photo en attente de validation', 'mj-member'));
            ?>
            <li class="mj-member-event-single__photo-pending-item">
                <?php if ($pending_thumb !== '') : ?>
                <div class="mj-member-event-single__photo-pending-thumb">
                    <img src="<?php echo esc_url($pending_thumb); ?>" alt="<?php echo esc_attr($pending_alt); ?>" loading="lazy" decoding="async" />
                </div>
                <?php endif; ?>
                <div class="mj-member-event-single__photo-pending-meta">
                    <?php if ($pending_caption !== '') : ?>
                    <p class="mj-member-event-single__photo-pending-caption"><?php echo esc_html($pending_caption); ?></p>
                    <?php endif; ?>
                    <?php if ($pending_date !== '') : ?>
                    <p class="mj-member-event-single__photo-pending-date"><?php echo esc_html(sprintf(__('Envoyée le %s', 'mj-member'), $pending_date)); ?></p>
                    <?php endif; ?>
                    <?php if ($pending_full !== '') : ?>
                    <div class="mj-member-event-single__photo-pending-meta-actions">
                        <a class="mj-member-event-single__photo-pending-open" href="<?php echo esc_url($pending_full); ?>" target="_blank" rel="noopener">
                            <?php echo esc_html__('Voir l’image', 'mj-member'); ?>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <?php if ($photo_has_items) : ?>
    <div class="mj-member-event-single__photo-grid">
        <?php foreach ($photo_items as $photo_entry) :
            $photo_full = isset($photo_entry['full']) ? $photo_entry['full'] : '';
            $photo_display = isset($photo_entry['url']) ? $photo_entry['url'] : '';
            $photo_url = $photo_full !== '' ? $photo_full : $photo_display;
            $photo_thumb = isset($photo_entry['thumb']) && $photo_entry['thumb'] !== '' ? $photo_entry['thumb'] : ($photo_display !== '' ? $photo_display : $photo_url);
            $photo_caption = isset($photo_entry['caption']) ? $photo_entry['caption'] : '';
            if ($photo_thumb === '') {
                continue;
            }
            $photo_alt = $photo_caption !== '' ? $photo_caption : $photos_event_title;
        ?>
        <figure>
            <div class="mj-member-event-single__photo">
                <?php if ($photo_url !== '') : ?>
                <a href="<?php echo esc_url($photo_url); ?>" target="_blank" rel="noopener">
                    <img src="<?php echo esc_url($photo_thumb); ?>" alt="<?php echo esc_attr($photo_alt); ?>" loading="lazy" decoding="async" />
                </a>
                <?php else : ?>
                <img src="<?php echo esc_url($photo_thumb); ?>" alt="<?php echo esc_attr($photo_alt); ?>" loading="lazy" decoding="async" />
                <?php endif; ?>
            </div>
            <?php if ($photo_caption !== '') : ?>
            <figcaption class="mj-member-event-single__photo-caption"><?php echo esc_html($photo_caption); ?></figcaption>
            <?php endif; ?>
        </figure>
        <?php endforeach; ?>
    </div>
    <?php else : ?>
    <p class="mj-member-event-single__photo-empty"><?php echo esc_html__('Aucune photo validée pour l’instant. Partage les tiennes !', 'mj-member'); ?></p>
    <?php endif; ?>

    <?php if ($photo_can_upload) :
        $photo_redirect = function_exists('mj_member_get_current_url') ? mj_member_get_current_url() : get_permalink();
        $photo_redirect = esc_url($photo_redirect);
    ?>
    <form class="mj-member-event-single__photo-upload" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" enctype="multipart/form-data">
        <?php wp_nonce_field('mj-member-event-photo', 'mj_event_photo_nonce'); ?>
        <input type="hidden" name="action" value="mj_member_submit_event_photo" />
        <input type="hidden" name="event_id" value="<?php echo esc_attr((int) $event['id']); ?>" />
        <input type="hidden" name="redirect_to" value="<?php echo esc_attr($photo_redirect); ?>" />

        <label for="mj-member-event-photo-file"><?php echo esc_html__('Ajoute ta photo', 'mj-member'); ?></label>
        <input id="mj-member-event-photo-file" type="file" name="event_photo_file" accept="image/*" required />

        <label for="mj-member-event-photo-caption"><?php echo esc_html__('Décris ton souvenir (optionnel)', 'mj-member'); ?></label>
        <textarea id="mj-member-event-photo-caption" name="photo_caption" maxlength="180" placeholder="<?php echo esc_attr__('Exemple : Soirée jeu du vendredi !', 'mj-member'); ?>"></textarea>

        <label class="mj-member-event-single__photo-consent" for="mj-member-event-photo-consent">
            <input id="mj-member-event-photo-consent" type="checkbox" name="mj_event_photo_consent" value="1" required />
            <?php echo esc_html__('Je garantis avoir l’autorisation des personnes présentes et j’accepte la diffusion conforme aux règles RGPD de la MJ.', 'mj-member'); ?>
        </label>
        <p class="mj-member-event-single__photo-consent-hint"><?php echo esc_html__('Partage uniquement des photos dont chaque personne a donné son accord.', 'mj-member'); ?></p>

        <p class="mj-member-event-single__photo-note">
            <?php if ($photo_is_unlimited) : ?>
                <?php esc_html_e('Tu peux partager un nombre illimité de photos pour cet événement.', 'mj-member'); ?>
            <?php else : ?>
                <?php echo esc_html(sprintf(_n('Il te reste %d envoi pour cet événement.', 'Il te reste %d envois pour cet événement.', $photo_member_remaining, 'mj-member'), $photo_member_remaining)); ?>
            <?php endif; ?>
        </p>

        <button type="submit"><?php echo esc_html__('Envoyer ma photo', 'mj-member'); ?></button>
    </form>
    <?php elseif ($photo_reason !== '') : ?>
    <p class="mj-member-event-single__photo-empty"><?php echo esc_html($photo_reason); ?></p>
    <?php endif; ?>
</section>
