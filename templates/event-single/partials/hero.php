<?php
if (!defined('ABSPATH')) {
    exit;
}

$hero_data = isset($hero) && is_array($hero) ? $hero : array();

$hero_type_label = isset($hero_data['type_label'])
    ? (string) $hero_data['type_label']
    : (isset($type_label) ? (string) $type_label : '');

$hero_schedule = isset($hero_data['schedule_summary'])
    ? (string) $hero_data['schedule_summary']
    : (isset($occurrence_schedule_summary) ? (string) $occurrence_schedule_summary : '');

$hero_status_label = isset($hero_data['status_label'])
    ? (string) $hero_data['status_label']
    : (isset($status_label) ? (string) $status_label : '');

$hero_status_key = isset($hero_data['status_key'])
    ? (string) $hero_data['status_key']
    : (isset($status_key) ? (string) $status_key : '');

$hero_active_status = isset($hero_data['active_status'])
    ? (string) $hero_data['active_status']
    : (isset($active_status) ? (string) $active_status : '');

$hero_title = isset($hero_data['title'])
    ? (string) $hero_data['title']
    : (isset($event_title_display) ? (string) $event_title_display : '');

$hero_date = isset($hero_data['date_label'])
    ? (string) $hero_data['date_label']
    : (isset($display_date_label) ? (string) $display_date_label : '');

$hero_cover_url = isset($hero_data['cover_url'])
    ? (string) $hero_data['cover_url']
    : (isset($cover_thumb) ? (string) $cover_thumb : '');

$hero_cover_alt = isset($hero_data['cover_alt'])
    ? (string) $hero_data['cover_alt']
    : (isset($title) ? (string) $title : $hero_title);
?>
<section class="mj-member-event-single__hero">
    <div class="mj-member-event-single__badges">
        <?php if ($hero_type_label !== '') : ?>
        <span class="mj-member-event-single__badge"><?php echo esc_html($hero_type_label); ?></span>
        <?php endif; ?>
        <?php if ($hero_schedule !== '') : ?>
        <span class="mj-member-event-single__badge mj-member-event-single__badge--schedule"><?php echo esc_html($hero_schedule); ?></span>
        <?php endif; ?>
        <?php if ($hero_status_label !== '' && $hero_status_key !== $hero_active_status) : ?>
        <span class="mj-member-event-single__badge mj-member-event-single__status"><?php echo esc_html($hero_status_label); ?></span>
        <?php endif; ?>
    </div>
    <h1 class="mj-member-event-single__title"><?php echo esc_html($hero_title); ?></h1>
    <?php if ($hero_date !== '') : ?>
    <p class="mj-member-event-single__date"><?php echo esc_html($hero_date); ?></p>
    <?php endif; ?>
    <?php if ($hero_cover_url !== '') : ?>
    <div class="mj-member-event-single__hero-cover">
        <img src="<?php echo esc_url($hero_cover_url); ?>" alt="<?php echo esc_attr($hero_cover_alt); ?>" loading="lazy" />
    </div>
    <?php endif; ?>
</section>
