<?php
if (!defined('ABSPATH')) {
    exit;
}

$location_data = isset($location_view) && is_array($location_view)
    ? $location_view
    : array();

$location_has_card_value = array_key_exists('has_card', $location_data)
    ? !empty($location_data['has_card'])
    : !empty($location_has_card);

if (!$location_has_card_value) {
    return;
}

$location_title = isset($location_data['title'])
    ? (string) $location_data['title']
    : (isset($location_display_title) ? (string) $location_display_title : '');

$location_address = isset($location_data['address'])
    ? (string) $location_data['address']
    : (isset($location_address_display) ? (string) $location_address_display : '');

$location_cover = isset($location_data['cover_url'])
    ? (string) $location_data['cover_url']
    : (isset($location_display_cover) ? (string) $location_display_cover : '');

$location_cover_alt = isset($location_data['cover_alt']) && $location_data['cover_alt'] !== ''
    ? (string) $location_data['cover_alt']
    : ($location_title !== '' ? $location_title : __('Lieu de l\'evenement', 'mj-member'));

$location_types_list = array();
if (isset($location_data['types']) && is_array($location_data['types'])) {
    $location_types_list = array_values(array_filter(array_map('strval', $location_data['types'])));
} elseif (isset($location_types) && is_array($location_types)) {
    $location_types_list = array_values(array_filter(array_map('strval', $location_types)));
}

$location_description_content = isset($location_data['description_html'])
    ? (string) $location_data['description_html']
    : (isset($location_description_html) ? (string) $location_description_html : '');

$location_notes_content = isset($location_data['notes_html'])
    ? (string) $location_data['notes_html']
    : (isset($location_notes_html) ? (string) $location_notes_html : '');

$location_map_embed = isset($location_data['map_embed'])
    ? (string) $location_data['map_embed']
    : (isset($location_display_map) ? (string) $location_display_map : '');

$location_map_link = isset($location_data['map_link'])
    ? (string) $location_data['map_link']
    : (isset($location_display_map_link) ? (string) $location_display_map_link : '');
?>
<section class="mj-member-event-single__card mj-member-event-single__location">
    <h2><?php echo esc_html__('Lieu', 'mj-member'); ?></h2>
    <?php if ($location_cover !== '') : ?>
    <div class="mj-member-event-single__location-header">
        <span class="mj-member-event-single__location-cover">
            <img src="<?php echo esc_url($location_cover); ?>" alt="<?php echo esc_attr($location_cover_alt); ?>" loading="lazy" />
        </span>
        <div>
            <?php if ($location_title !== '') : ?>
            <p class="mj-member-event-single__location-title"><?php echo esc_html($location_title); ?></p>
            <?php endif; ?>
            <?php if ($location_address !== '') : ?>
            <p class="mj-member-event-single__location-address"><?php echo esc_html($location_address); ?></p>
            <?php endif; ?>
        </div>
    </div>
    <?php elseif ($location_title !== '' || $location_address !== '') : ?>
    <div class="mj-member-event-single__location-header" style="gap:8px;">
        <div>
            <?php if ($location_title !== '') : ?>
            <p class="mj-member-event-single__location-title"><?php echo esc_html($location_title); ?></p>
            <?php endif; ?>
            <?php if ($location_address !== '') : ?>
            <p class="mj-member-event-single__location-address"><?php echo esc_html($location_address); ?></p>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($location_types_list)) : ?>
    <div class="mj-member-event-single__location-types">
        <?php foreach ($location_types_list as $location_type_label) : ?>
        <span class="mj-member-event-single__location-type"><?php echo esc_html($location_type_label); ?></span>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($location_description_content !== '') : ?>
    <p class="mj-member-event-single__location-notes"><?php echo $location_description_content; ?></p>
    <?php endif; ?>
    <?php if ($location_notes_content !== '') : ?>
    <p class="mj-member-event-single__location-notes"><?php echo $location_notes_content; ?></p>
    <?php endif; ?>

    <?php if ($location_map_embed !== '') : ?>
    <div class="mj-member-event-single__location-map">
        <iframe src="<?php echo esc_url($location_map_embed); ?>" loading="lazy" allowfullscreen referrerpolicy="no-referrer-when-downgrade"></iframe>
        <?php if ($location_map_link !== '') : ?>
        <div class="mj-member-event-single__location-map-actions">
            <a class="mj-member-event-single__location-map-link" href="<?php echo esc_url($location_map_link); ?>" target="_blank" rel="noopener"><?php echo esc_html__('Ouvrir dans Google Maps', 'mj-member'); ?></a>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</section>
