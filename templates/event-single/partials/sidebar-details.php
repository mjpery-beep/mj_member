<?php
if (!defined('ABSPATH')) {
    exit;
}

$sidebar_data = isset($sidebar_view) && is_array($sidebar_view)
    ? $sidebar_view
    : array();

$deadline_value = isset($sidebar_data['deadline_label'])
    ? (string) $sidebar_data['deadline_label']
    : (isset($registration_deadline_label) && $registration_deadline_label !== ''
        ? (string) $registration_deadline_label
        : (isset($deadline_label) ? (string) $deadline_label : '')
    );

$audience_value = isset($sidebar_data['audience_label'])
    ? (string) $sidebar_data['audience_label']
    : (isset($age_label) ? (string) $age_label : '');

$price_value = isset($sidebar_data['price_label'])
    ? (string) $sidebar_data['price_label']
    : (isset($registration_price_label) ? (string) $registration_price_label : '');

$capacity_total_value = isset($sidebar_data['capacity_total'])
    ? (int) $sidebar_data['capacity_total']
    : (isset($event_capacity_total) ? (int) $event_capacity_total : 0);

$next_occurrence_label = isset($sidebar_data['next_label'])
    ? (string) $sidebar_data['next_label']
    : (isset($occurrence_next_label) ? (string) $occurrence_next_label : '');

$occurrence_items = array();
if (isset($sidebar_data['occurrence_items']) && is_array($sidebar_data['occurrence_items'])) {
    $occurrence_items = $sidebar_data['occurrence_items'];
} elseif (isset($occurrence_items) && is_array($occurrence_items)) {
    $occurrence_items = $occurrence_items;
}

if ($next_occurrence_label === '' && !empty($occurrence_items)) {
    $next_candidate = isset($occurrence_items[0]['label']) ? (string) $occurrence_items[0]['label'] : '';
    if ($next_candidate !== '') {
        $next_occurrence_label = $next_candidate;
    }
}

$other_occurrence_labels = array();
if (!empty($occurrence_items)) {
    foreach ($occurrence_items as $index => $occurrence_entry) {
        if (!is_array($occurrence_entry)) {
            continue;
        }
        $label_candidate = isset($occurrence_entry['label']) ? (string) $occurrence_entry['label'] : '';
        if ($label_candidate === '') {
            continue;
        }
        if ($index === 0 && $next_occurrence_label !== '' && $label_candidate === $next_occurrence_label) {
            continue;
        }
        $other_occurrence_labels[] = sanitize_text_field($label_candidate);
    }
}

$occurrence_remaining_count = isset($sidebar_data['remaining_count'])
    ? (int) $sidebar_data['remaining_count']
    : (isset($occurrence_remaining) ? (int) $occurrence_remaining : 0);

$has_multiple_occurrences = isset($sidebar_data['has_multiple_occurrences'])
    ? !empty($sidebar_data['has_multiple_occurrences'])
    : (!empty($event_has_multiple_occurrences));

$occurrence_stage_label = isset($sidebar_data['stage_label'])
    ? (string) $sidebar_data['stage_label']
    : (isset($occurrence_stage_period_label) ? (string) $occurrence_stage_period_label : '');

$occurrence_stage_time = isset($sidebar_data['stage_time_range'])
    ? (string) $sidebar_data['stage_time_range']
    : (isset($occurrence_stage_time_range) ? (string) $occurrence_stage_time_range : '');

$other_occurrence_summary = '';
if (!empty($other_occurrence_labels)) {
    $other_occurrence_summary = implode(', ', array_map('sanitize_text_field', $other_occurrence_labels));
}

$remaining_summary = '';
if ($occurrence_remaining_count > 0) {
    $remaining_summary = sprintf(
        _n('%d créneau supplémentaire', '%d créneaux supplémentaires', $occurrence_remaining_count, 'mj-member'),
        $occurrence_remaining_count
    );
}
?>
<section class="mj-member-event-single__card mj-member-event-single__details">
    <?php if ($deadline_value !== '') : ?>
    <div class="mj-member-event-single__detail">
        <span class="mj-member-event-single__detail-label"><?php echo esc_html__("Inscriptions jusqu'au", 'mj-member'); ?></span>
        <span class="mj-member-event-single__detail-value"><?php echo esc_html($deadline_value); ?></span>
    </div>
    <?php endif; ?>
    <?php if ($audience_value !== '') : ?>
    <div class="mj-member-event-single__detail">
        <span class="mj-member-event-single__detail-label"><?php echo esc_html__('Public', 'mj-member'); ?></span>
        <span class="mj-member-event-single__detail-value"><?php echo esc_html($audience_value); ?></span>
    </div>
    <?php endif; ?>
    <?php if ($price_value !== '') : ?>
    <div class="mj-member-event-single__detail">
        <span class="mj-member-event-single__detail-label"><?php echo esc_html__('Tarif indicatif', 'mj-member'); ?></span>
        <span class="mj-member-event-single__detail-value"><?php echo esc_html($price_value); ?></span>
    </div>
    <?php endif; ?>
    <?php if ($next_occurrence_label !== '') : ?>
    <div class="mj-member-event-single__detail">
        <span class="mj-member-event-single__detail-label"><?php echo esc_html__('Prochain créneau', 'mj-member'); ?></span>
        <span class="mj-member-event-single__detail-value"><?php echo esc_html($next_occurrence_label); ?></span>
    </div>
    <?php endif; ?>
    <?php if ($other_occurrence_summary !== '' || $remaining_summary !== '') : ?>
    <div class="mj-member-event-single__detail">
        <span class="mj-member-event-single__detail-label"><?php echo esc_html__('Autres dates', 'mj-member'); ?></span>
        <span class="mj-member-event-single__detail-value">
            <?php if ($other_occurrence_summary !== '') : ?>
                <?php echo esc_html($other_occurrence_summary); ?><?php if ($remaining_summary !== '') : ?> &bull; <?php endif; ?>
            <?php endif; ?>
            <?php if ($remaining_summary !== '') : ?>
                <?php echo esc_html($remaining_summary); ?>
            <?php endif; ?>
        </span>
    </div>
    <?php endif; ?>
    <?php if ($occurrence_stage_label !== '') : ?>
    <div class="mj-member-event-single__detail">
        <span class="mj-member-event-single__detail-label"><?php echo esc_html__('Période', 'mj-member'); ?></span>
        <span class="mj-member-event-single__detail-value"><?php echo esc_html($occurrence_stage_label); ?></span>
    </div>
    <?php endif; ?>
    <?php if ($occurrence_stage_time !== '') : ?>
    <div class="mj-member-event-single__detail">
        <span class="mj-member-event-single__detail-label"><?php echo esc_html__('Horaires', 'mj-member'); ?></span>
        <span class="mj-member-event-single__detail-value"><?php echo esc_html($occurrence_stage_time); ?></span>
    </div>
    <?php endif; ?>
    <?php if ($capacity_total_value > 0 && !$has_multiple_occurrences) : ?>
    <div class="mj-member-event-single__detail">
        <span class="mj-member-event-single__detail-label"><?php echo esc_html__('Places max', 'mj-member'); ?></span>
        <span class="mj-member-event-single__detail-value"><?php echo esc_html(sprintf(_n('%d place', '%d places', $capacity_total_value, 'mj-member'), $capacity_total_value)); ?></span>
    </div>
    <?php endif; ?>
    <div class="mj-member-event-single__details-enhancements" data-mj-event-sidebar-enhancements></div>
</section>
