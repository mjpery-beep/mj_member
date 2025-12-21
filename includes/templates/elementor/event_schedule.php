<?php
/**
 * Template: Event Schedule Widget
 * Affiche l'horaire d'un �v�nement MJ et adapte le rendu selon le type de planification.
 */

if (!defined('ABSPATH')) {
    exit;
}

use Mj\Member\Classes\Crud\MjEvents;
use Mj\Member\Classes\MjEventSchedule;

$event_id = isset($template_data['event_id']) ? (int) $template_data['event_id'] : 0;
$title = isset($template_data['title']) ? (string) $template_data['title'] : '';
$display_title = !empty($template_data['display_title']);
$max_occurrences = isset($template_data['max_occurrences']) ? (int) $template_data['max_occurrences'] : 20;
$show_past = !empty($template_data['show_past']);
$date_format_setting = isset($template_data['date_format']) ? $template_data['date_format'] : 'full';
$time_format_setting = isset($template_data['time_format']) ? $template_data['time_format'] : '24h';
$layout_mode_fallback = isset($template_data['layout_mode_fallback']) ? sanitize_key($template_data['layout_mode_fallback']) : 'list';
$layout_mode_fixed = isset($template_data['layout_mode_fixed']) ? sanitize_key($template_data['layout_mode_fixed']) : 'card';
$layout_mode_range = isset($template_data['layout_mode_range']) ? sanitize_key($template_data['layout_mode_range']) : 'timeline';
$layout_mode_series = isset($template_data['layout_mode_series']) ? sanitize_key($template_data['layout_mode_series']) : 'list';
$layout_mode_recurring = isset($template_data['layout_mode_recurring']) ? sanitize_key($template_data['layout_mode_recurring']) : 'cards';
$show_icons = !empty($template_data['show_icons']);
$highlight_today = !empty($template_data['highlight_today']);
$empty_message = isset($template_data['empty_message']) ? $template_data['empty_message'] : __('Aucun horaire disponible.', 'mj-member');
$is_preview = !empty($template_data['is_preview']);

$format_time_string = function ($time_str) use ($time_format_setting) {
    $time_str = trim((string) $time_str);
    if ($time_str === '') {
        return '';
    }

    $timezone = function_exists('wp_timezone') ? wp_timezone() : null;
    $time_candidate = DateTime::createFromFormat('H:i:s', $time_str, $timezone);
    if (!$time_candidate instanceof DateTime) {
        $time_candidate = DateTime::createFromFormat('H:i', $time_str, $timezone);
    }

    if (!$time_candidate instanceof DateTime) {
        $timestamp = strtotime($time_str);
        if ($timestamp === false) {
            return '';
        }
    } else {
        $timestamp = $time_candidate->getTimestamp();
    }

    if ($time_format_setting === '12h') {
        return function_exists('wp_date') ? wp_date('g:i A', $timestamp) : gmdate('g:i A', $timestamp);
    }

    return function_exists('wp_date') ? wp_date('H:i', $timestamp) : gmdate('H:i', $timestamp);
};

$format_day = function ($datetime_str) {
    $timestamp = strtotime($datetime_str);
    if (!$timestamp) {
        return '';
    }

    return wp_date('l', $timestamp);
};

$format_date_label = function ($datetime_str) use ($date_format_setting) {
    $timestamp = strtotime($datetime_str);
    if (!$timestamp) {
        return '';
    }

    switch ($date_format_setting) {
        case 'short':
            return wp_date('d/m/Y', $timestamp);
        case 'medium':
            return wp_date('j M Y', $timestamp);
        case 'day_only':
            return wp_date('j F', $timestamp);
        case 'full':
        default:
            return wp_date('j F Y', $timestamp);
    }
};

$format_time = function ($datetime_str) use ($time_format_setting) {
    $timestamp = strtotime($datetime_str);
    if (!$timestamp) {
        return '';
    }

    if ($time_format_setting === '12h') {
        return wp_date('g:i A', $timestamp);
    }

    return wp_date('H:i', $timestamp);
};

// Prépare les données de l'événement pour couvrir EventData, tableaux ou objets simples.
$event = null;
$event_array = array();
$event_for_schedule = null;
$schedule_mode = 'unknown';
$occurrences = array();
$entries = array();
$is_recurring = false;
$recurring_slots = array();

if ($event_id > 0 && class_exists(MjEvents::class)) {
    $event = MjEvents::find($event_id);

    if (is_object($event) && method_exists($event, 'toArray')) {
        $event_array = $event->toArray();
    } elseif (is_array($event)) {
        $event_array = $event;
    }

    if (!empty($event_array)) {
        $event_for_schedule = $event_array;
    } elseif ($event) {
        $event_for_schedule = $event;
    }
}

$occurrence_args = array();
$occurrence_args['max'] = $max_occurrences;
$occurrence_args['include_past'] = (bool) $show_past;

$get_event_value = static function ($key, $default = null) use ($event, $event_array) {
    if (!empty($event_array) && array_key_exists($key, $event_array)) {
        return $event_array[$key];
    }

    if ($event && isset($event->$key)) {
        return $event->$key;
    }

    if (is_array($event) && array_key_exists($key, $event)) {
        return $event[$key];
    }

    return $default;
};

if ($event_for_schedule) {
    $schedule_mode_raw = $get_event_value('schedule_mode', 'fixed');
    $schedule_mode = $schedule_mode_raw !== null ? sanitize_key((string) $schedule_mode_raw) : 'fixed';
    if ($schedule_mode === '') {
        $schedule_mode = 'fixed';
    }

    if ($schedule_mode === 'recurring') {
        $is_recurring = true;

        $schedule_payload = $get_event_value('schedule_payload', '');
        if (is_string($schedule_payload) && $schedule_payload !== '') {
            $schedule_payload = json_decode($schedule_payload, true);
        }
        if (!is_array($schedule_payload)) {
            $schedule_payload = array();
        }

        $weekday_labels = array(
            'monday' => __('Lundi', 'mj-member'),
            'tuesday' => __('Mardi', 'mj-member'),
            'wednesday' => __('Mercredi', 'mj-member'),
            'thursday' => __('Jeudi', 'mj-member'),
            'friday' => __('Vendredi', 'mj-member'),
            'saturday' => __('Samedi', 'mj-member'),
            'sunday' => __('Dimanche', 'mj-member'),
        );
        $weekday_order = array_keys($weekday_labels);

        $selected_weekdays = array();
        if (!empty($schedule_payload['weekdays']) && is_array($schedule_payload['weekdays'])) {
            foreach ($schedule_payload['weekdays'] as $weekday) {
                $weekday = strtolower(sanitize_key($weekday));
                if (isset($weekday_labels[$weekday])) {
                    $selected_weekdays[$weekday] = $weekday;
                }
            }
        }

        $weekday_times = array();
        if (!empty($schedule_payload['weekday_times']) && is_array($schedule_payload['weekday_times'])) {
            $weekday_times = $schedule_payload['weekday_times'];
        }

        foreach ($weekday_order as $weekday_key) {
            $day_label = $weekday_labels[$weekday_key];
            $start_formatted = '';
            $end_formatted = '';

            if (isset($weekday_times[$weekday_key]) && is_array($weekday_times[$weekday_key])) {
                $times = $weekday_times[$weekday_key];
                if (!empty($times['start'])) {
                    $start_formatted = $format_time_string($times['start']);
                }
                if (!empty($times['end'])) {
                    $end_formatted = $format_time_string($times['end']);
                }
            }

            if ($start_formatted === '' && !empty($schedule_payload['start_time'])) {
                $start_formatted = $format_time_string($schedule_payload['start_time']);
            }
            if ($end_formatted === '' && !empty($schedule_payload['end_time'])) {
                $end_formatted = $format_time_string($schedule_payload['end_time']);
            }

            if ($start_formatted !== '' || $end_formatted !== '') {
                $recurring_slots[] = array(
                    'day_label' => $day_label,
                    'start' => $start_formatted,
                    'end' => $end_formatted,
                );
                $selected_weekdays[$weekday_key] = $weekday_key;
            }
        }

        if (empty($recurring_slots)) {
            $is_recurring = false;
        }
    }

    if (!$is_recurring && class_exists(MjEventSchedule::class)) {
        $occurrences = MjEventSchedule::get_occurrences($event_for_schedule, $occurrence_args);

        if (empty($occurrences)) {
            $raw_occurrences = MjEventSchedule::build_all_occurrences($event_for_schedule);
            if (is_array($raw_occurrences) && !empty($raw_occurrences)) {
                $now_ts = current_time('timestamp');
                foreach ($raw_occurrences as $raw_occurrence) {
                    if (!isset($raw_occurrence['start']) || !isset($raw_occurrence['timestamp'])) {
                        continue;
                    }

                    $timestamp = (int) $raw_occurrence['timestamp'];
                    if (!$show_past && $timestamp < $now_ts) {

                        continue;
                    }

                    $raw_occurrence['is_past'] = ($timestamp < $now_ts);
                    $occurrences[] = $raw_occurrence;
                }

                if (!empty($occurrences)) {
                    usort(
                        $occurrences,
                        static function ($a, $b) {
                            return (int) $a['timestamp'] <=> (int) $b['timestamp'];
                        }
                    );

                    if ($max_occurrences > 0 && count($occurrences) > $max_occurrences) {
                        $occurrences = array_slice($occurrences, 0, $max_occurrences);
                    }
                }
            }
        }
    }
}

if ($is_preview && !$event_for_schedule) {
    $schedule_mode = 'preview';
    $dummy_date = new DateTime('next monday');
    for ($i = 0; $i < min(5, max(1, $max_occurrences)); $i++) {
        $start = clone $dummy_date;
        $start->modify("+{$i} days");
        $start->setTime(14, 0, 0);
        $end = clone $start;
        $end->setTime(17, 0, 0);
        $occurrences[] = array(
            'start' => $start->format('Y-m-d H:i:s'),
            'end' => $end->format('Y-m-d H:i:s'),
            'timestamp' => $start->getTimestamp(),
            'is_past' => false,
        );
    }
}


if (empty($occurrences) && !$is_recurring && $event_for_schedule) {
    $start_raw = (string) $get_event_value('date_debut', '');
    if ($start_raw !== '') {
        $end_raw = (string) $get_event_value('date_fin', '');
        $manual_start = strtotime($start_raw);
        if ($manual_start) {
            $manual_end = $end_raw !== '' ? strtotime($end_raw) : false;
            if ($manual_end === false || $manual_end <= $manual_start) {
                $manual_end = $manual_start + HOUR_IN_SECONDS;
            }

            $occurrences[] = array(
                'start' => date('Y-m-d H:i:s', $manual_start),
                'end' => date('Y-m-d H:i:s', $manual_end),
                'timestamp' => $manual_start,
                'is_past' => ($manual_start < current_time('timestamp')),
            );
        }
    }
}
$today_key = wp_date('Y-m-d');
if (!empty($occurrences)) {
    foreach ($occurrences as $occurrence) {
        $start_str = isset($occurrence['start']) ? (string) $occurrence['start'] : '';
        if ($start_str === '') {
            continue;
        }

        $end_str = isset($occurrence['end']) ? (string) $occurrence['end'] : '';
        $start_day = $format_day($start_str);
        $date_label = $format_date_label($start_str);
        $start_time_label = $format_time($start_str);
        $end_time_label = $end_str !== '' ? $format_time($end_str) : '';

        $start_timestamp = strtotime($start_str);
        $date_key = $start_timestamp ? wp_date('Y-m-d', $start_timestamp) : '';
        $is_past_entry = !empty($occurrence['is_past']);
        $is_today_entry = ($highlight_today && $date_key !== '' && $date_key === $today_key);

        $entries[] = array(
            'day' => ucfirst($start_day),
            'date' => $date_label,
            'start_time' => $start_time_label,
            'end_time' => $end_time_label,
            'is_past' => $is_past_entry,
            'is_today' => $is_today_entry,
        );
    }
}

$active_layout = $layout_mode_fallback;
if ($is_recurring && !empty($recurring_slots)) {
    $active_layout = $layout_mode_recurring;
} elseif (!empty($entries)) {
    switch ($schedule_mode) {
        case 'fixed':
            $active_layout = $layout_mode_fixed;
            break;
        case 'range':
            $active_layout = $layout_mode_range;
            break;
        case 'series':
        case 'custom':
            $active_layout = $layout_mode_series;
            break;
        default:
            $active_layout = $layout_mode_fallback;
            break;
    }
}

$active_layout = sanitize_key($active_layout);
$schedule_class = 'mj-event-schedule--mode-' . sanitize_html_class($schedule_mode ?: 'unknown');
$layout_class = 'mj-event-schedule--layout-' . sanitize_html_class($active_layout ?: 'list');
$wrapper_classes = array('mj-event-schedule', $schedule_class, $layout_class);

?>

<div class="<?php echo esc_attr(implode(' ', $wrapper_classes)); ?>">
    <?php if ($display_title && $title !== '') : ?>
        <h3 class="mj-event-schedule__title"><?php echo esc_html($title); ?></h3>
    <?php endif; ?>

    <?php if ($is_recurring && !empty($recurring_slots)) : ?>
        <?php if ($active_layout === 'table') : ?>
            <div class="mj-event-schedule__table-wrapper">
                <table class="mj-event-schedule__table mj-event-schedule__table--recurring">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Jour', 'mj-member'); ?></th>
                            <th><?php esc_html_e('D�but', 'mj-member'); ?></th>
                            <th><?php esc_html_e('Fin', 'mj-member'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recurring_slots as $slot) : ?>
                            <tr>
                                <td><?php echo esc_html($slot['day_label']); ?></td>
                                <td><?php echo esc_html($slot['start']); ?></td>
                                <td><?php echo esc_html($slot['end']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif ($active_layout === 'chips') : ?>
            <div class="mj-event-schedule__chips">
                <?php foreach ($recurring_slots as $slot) : ?>
                    <span class="mj-event-schedule__chip">
                        <strong><?php echo esc_html($slot['day_label']); ?></strong>
                        <?php if ($slot['start'] !== '' || $slot['end'] !== '') : ?>
                            <span class="mj-event-schedule__chip-time">
                                <?php echo esc_html($slot['start']); ?>
                                <?php if ($slot['end'] !== '' && $slot['end'] !== $slot['start']) : ?>
                                    <span class="mj-event-schedule__time-separator"></span>
                                    <?php echo esc_html($slot['end']); ?>
                                <?php endif; ?>
                            </span>
                        <?php endif; ?>
                    </span>
                <?php endforeach; ?>
            </div>
        <?php else : ?>
            <div class="mj-event-schedule__recurring">
                <?php foreach ($recurring_slots as $slot) : ?>
                    <div class="mj-event-schedule__recurring-item">
                        <span class="mj-event-schedule__recurring-day"><?php echo esc_html($slot['day_label']); ?></span>
                        <?php if ($slot['start'] !== '' || $slot['end'] !== '') : ?>
                            <div class="mj-event-schedule__recurring-time">
                                <?php if ($show_icons) : ?>
                                    <span class="mj-event-schedule__time-icon" aria-hidden="true">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <circle cx="12" cy="12" r="10"></circle>
                                            <polyline points="12 6 12 12 16 14"></polyline>
                                        </svg>
                                    </span>
                                <?php endif; ?>
                                <span class="mj-event-schedule__time-text">
                                    <?php echo esc_html($slot['start']); ?>
                                    <?php if ($slot['end'] !== '' && $slot['end'] !== $slot['start']) : ?>
                                        <span class="mj-event-schedule__time-separator"></span>
                                        <?php echo esc_html($slot['end']); ?>
                                    <?php endif; ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php elseif (empty($entries)) : ?>
        <div class="mj-event-schedule__empty">
            <?php if ($is_preview && $event_id <= 0) : ?>
                <p class="mj-event-schedule__empty-preview">
                    <?php esc_html_e('S�lectionnez un �v�nement dans les r�glages du widget.', 'mj-member'); ?>
                </p>
            <?php else : ?>
                <p><?php echo esc_html($empty_message); ?></p>
            <?php endif; ?>
        </div>
    <?php else : ?>
        <?php if ($active_layout === 'table') : ?>
            <div class="mj-event-schedule__table-wrapper">
                <table class="mj-event-schedule__table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Jour', 'mj-member'); ?></th>
                            <th><?php esc_html_e('Date', 'mj-member'); ?></th>
                            <th><?php esc_html_e('Heure', 'mj-member'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($entries as $entry) : ?>
                            <tr class="<?php echo $entry['is_past'] ? 'is-past' : ''; ?><?php echo $entry['is_today'] ? ' is-today' : ''; ?>">
                                <td><?php echo esc_html($entry['day']); ?></td>
                                <td><?php echo esc_html($entry['date']); ?></td>
                                <td>
                                    <?php echo esc_html($entry['start_time']); ?>
                                    <?php if ($entry['end_time'] !== '' && $entry['end_time'] !== $entry['start_time']) : ?>
                                        <span class="mj-event-schedule__time-separator"></span>
                                        <?php echo esc_html($entry['end_time']); ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif ($active_layout === 'chips') : ?>
            <div class="mj-event-schedule__chips">
                <?php foreach ($entries as $entry) :
                    $chip_classes = array('mj-event-schedule__chip');
                    if ($entry['is_today']) {
                        $chip_classes[] = 'mj-event-schedule__chip--today';
                    }
                ?>
                    <span class="<?php echo esc_attr(implode(' ', $chip_classes)); ?>">
                        <strong><?php echo esc_html($entry['day']); ?></strong>
                        <span class="mj-event-schedule__chip-meta"><?php echo esc_html($entry['date']); ?></span>
                        <span class="mj-event-schedule__chip-time">
                            <?php echo esc_html($entry['start_time']); ?>
                            <?php if ($entry['end_time'] !== '' && $entry['end_time'] !== $entry['start_time']) : ?>
                                <span class="mj-event-schedule__time-separator"></span>
                                <?php echo esc_html($entry['end_time']); ?>
                            <?php endif; ?>
                        </span>
                    </span>
                <?php endforeach; ?>
            </div>
        <?php elseif ($active_layout === 'card') : ?>
            <div class="mj-event-schedule__cards">
                <?php foreach ($entries as $entry) :
                    $card_classes = array('mj-event-schedule__card');
                    if ($entry['is_past']) {
                        $card_classes[] = 'mj-event-schedule__card--past';
                    }
                    if ($entry['is_today']) {
                        $card_classes[] = 'mj-event-schedule__card--today';
                    }
                ?>
                    <article class="<?php echo esc_attr(implode(' ', $card_classes)); ?>">
                        <header class="mj-event-schedule__card-header">
                            <span class="mj-event-schedule__day-text"><?php echo esc_html($entry['day']); ?></span>
                            <span class="mj-event-schedule__date-text"><?php echo esc_html($entry['date']); ?></span>
                        </header>
                        <div class="mj-event-schedule__card-time">
                            <?php if ($show_icons) : ?>
                                <span class="mj-event-schedule__time-icon" aria-hidden="true">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="12" cy="12" r="10"></circle>
                                        <polyline points="12 6 12 12 16 14"></polyline>
                                    </svg>
                                </span>
                            <?php endif; ?>
                            <span class="mj-event-schedule__time-text">
                                <?php echo esc_html($entry['start_time']); ?>
                                <?php if ($entry['end_time'] !== '' && $entry['end_time'] !== $entry['start_time']) : ?>
                                    <span class="mj-event-schedule__time-separator"></span>
                                    <?php echo esc_html($entry['end_time']); ?>
                                <?php endif; ?>
                            </span>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php elseif ($active_layout === 'timeline') : ?>
            <div class="mj-event-schedule__timeline">
                <?php foreach ($entries as $entry) :
                    $timeline_classes = array('mj-event-schedule__timeline-entry');
                    if ($entry['is_past']) {
                        $timeline_classes[] = 'mj-event-schedule__timeline-entry--past';
                    }
                    if ($entry['is_today']) {
                        $timeline_classes[] = 'mj-event-schedule__timeline-entry--today';
                    }
                ?>
                    <div class="<?php echo esc_attr(implode(' ', $timeline_classes)); ?>">
                        <div class="mj-event-schedule__timeline-marker" aria-hidden="true"></div>
                        <div class="mj-event-schedule__timeline-content">
                            <div class="mj-event-schedule__day">
                                <span class="mj-event-schedule__day-text"><?php echo esc_html($entry['day']); ?></span>
                                <span class="mj-event-schedule__date-text"><?php echo esc_html($entry['date']); ?></span>
                            </div>
                            <div class="mj-event-schedule__time">
                                <?php if ($show_icons) : ?>
                                    <span class="mj-event-schedule__time-icon" aria-hidden="true">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <circle cx="12" cy="12" r="10"></circle>
                                            <polyline points="12 6 12 12 16 14"></polyline>
                                        </svg>
                                    </span>
                                <?php endif; ?>
                                <span class="mj-event-schedule__time-text">
                                    <?php echo esc_html($entry['start_time']); ?>
                                    <?php if ($entry['end_time'] !== '' && $entry['end_time'] !== $entry['start_time']) : ?>
                                        <span class="mj-event-schedule__time-separator"></span>
                                        <?php echo esc_html($entry['end_time']); ?>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else : ?>
            <div class="mj-event-schedule__list">
                <?php foreach ($entries as $entry) :
                    $item_classes = array('mj-event-schedule__item');
                    if ($entry['is_past']) {
                        $item_classes[] = 'mj-event-schedule__item--past';
                    }
                    if ($entry['is_today']) {
                        $item_classes[] = 'mj-event-schedule__item--today';
                    }
                ?>
                    <div class="<?php echo esc_attr(implode(' ', $item_classes)); ?>">
                        <div class="mj-event-schedule__day">
                            <span class="mj-event-schedule__day-text"><?php echo esc_html($entry['day']); ?></span>
                            <span class="mj-event-schedule__date-text"><?php echo esc_html($entry['date']); ?></span>
                        </div>
                        <div class="mj-event-schedule__time">
                            <?php if ($show_icons) : ?>
                                <span class="mj-event-schedule__time-icon" aria-hidden="true">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="12" cy="12" r="10"></circle>
                                        <polyline points="12 6 12 12 16 14"></polyline>
                                    </svg>
                                </span>
                            <?php endif; ?>
                            <span class="mj-event-schedule__time-text">
                                <?php echo esc_html($entry['start_time']); ?>
                                <?php if ($entry['end_time'] !== '' && $entry['end_time'] !== $entry['start_time']) : ?>
                                    <span class="mj-event-schedule__time-separator"></span>
                                    <?php echo esc_html($entry['end_time']); ?>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<style>
.mj-event-schedule {
    --mj-schedule-bg: transparent;
    --mj-schedule-item-bg: #f8f9fa;
    --mj-schedule-date-color: #333;
    --mj-schedule-time-color: #555;
    --mj-schedule-icon-color: #888;
    --mj-schedule-border-color: #e9ecef;
    --mj-schedule-past-opacity: 0.6;
}

.mj-event-schedule__title {
    margin: 0 0 1rem;
    font-size: 1.25rem;
    font-weight: 600;
}

.mj-event-schedule__empty {
    padding: 1rem;
    text-align: center;
    color: #6c757d;
    background: var(--mj-schedule-item-bg);
    border-radius: 8px;
}

.mj-event-schedule__empty-preview {
    font-style: italic;
}

.mj-event-schedule__recurring {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    padding: 1rem;
    background: var(--mj-schedule-item-bg);
    border-radius: 10px;
    border: 1px solid var(--mj-schedule-border-color);
}

.mj-event-schedule__recurring-item {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.75rem;
    padding: 0.85rem 1rem;
    background: rgba(255, 255, 255, 0.9);
    border-radius: 10px;
    border: 1px solid rgba(21, 101, 192, 0.18);
}

.mj-event-schedule__recurring-day {
    display: inline-flex;
    align-items: center;
    padding: 0.375rem 1rem;
    background: #e3f2fd;
    color: #1565c0;
    border-radius: 999px;
    font-weight: 600;
    font-size: 0.9rem;
}

.mj-event-schedule__recurring-time {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--mj-schedule-time-color);
    font-size: 1rem;
}

.mj-event-schedule__list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.mj-event-schedule__item {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    gap: 0.75rem 1rem;
    padding: 0.85rem 1.1rem;
    background: var(--mj-schedule-item-bg);
    border-radius: 10px;
    border: 1px solid var(--mj-schedule-border-color);
    transition: box-shadow 0.2s ease;
}

.mj-event-schedule__item:hover {
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
}

.mj-event-schedule__item--past {
    opacity: var(--mj-schedule-past-opacity);
}

.mj-event-schedule__item--today {
    background: #eef6ff;
}

.mj-event-schedule__day,
.mj-event-schedule__time {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.mj-event-schedule__day-text {
    font-weight: 600;
    color: var(--mj-schedule-date-color);
}

.mj-event-schedule__date-text {
    font-size: 0.85rem;
    color: #6c757d;
}

.mj-event-schedule__time {
    color: var(--mj-schedule-time-color);
}

.mj-event-schedule__time-icon {
    display: inline-flex;
    align-items: center;
    color: var(--mj-schedule-icon-color);
}

.mj-event-schedule__time-separator {
    margin: 0 0.25rem;
}

.mj-event-schedule__cards {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 1rem;
}

.mj-event-schedule__card {
    background: var(--mj-schedule-item-bg);
    border-radius: 14px;
    border: 1px solid var(--mj-schedule-border-color);
    padding: 1.2rem;
    display: flex;
    flex-direction: column;
    gap: 0.8rem;
    transition: box-shadow 0.2s ease, transform 0.2s ease;
}

.mj-event-schedule__card:hover {
    box-shadow: 0 12px 28px rgba(0, 0, 0, 0.08);
    transform: translateY(-2px);
}

.mj-event-schedule__card--past {
    opacity: var(--mj-schedule-past-opacity);
}

.mj-event-schedule__card--today {
    box-shadow: 0 0 0 2px rgba(33, 150, 243, 0.25);
}

.mj-event-schedule__card-header {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.mj-event-schedule__card-time {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    color: var(--mj-schedule-time-color);
}

.mj-event-schedule__timeline {
    position: relative;
    padding-left: 1.75rem;
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.mj-event-schedule__timeline::before {
    content: '';
    position: absolute;
    left: 0.6rem;
    top: 0.5rem;
    bottom: 0.5rem;
    width: 2px;
    background: rgba(33, 150, 243, 0.2);
}

.mj-event-schedule__timeline-entry {
    position: relative;
    background: var(--mj-schedule-item-bg);
    border: 1px solid var(--mj-schedule-border-color);
    border-radius: 10px;
    padding: 0.85rem 1.1rem;
    transition: box-shadow 0.2s ease;
}

.mj-event-schedule__timeline-entry:hover {
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
}

.mj-event-schedule__timeline-entry--past {
    opacity: var(--mj-schedule-past-opacity);
}

.mj-event-schedule__timeline-entry--today {
    box-shadow: 0 0 0 2px rgba(33, 150, 243, 0.25);
}

.mj-event-schedule__timeline-marker {
    position: absolute;
    left: -1.25rem;
    top: 1rem;
    width: 10px;
    height: 10px;
    background: #fff;
    border: 3px solid #2196f3;
    border-radius: 50%;
}

.mj-event-schedule__timeline-content {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

.mj-event-schedule__chips {
    display: flex;
    flex-wrap: wrap;
    gap: 0.6rem;
}

.mj-event-schedule__chip {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.5rem 0.9rem;
    background: #eef2ff;
    color: #2f3e9e;
    border-radius: 20px;
    font-size: 0.9rem;
    border: 1px solid rgba(47, 62, 158, 0.15);
}

.mj-event-schedule__chip--today {
    border-color: rgba(33, 150, 243, 0.45);
    box-shadow: 0 0 0 2px rgba(33, 150, 243, 0.15);
}

.mj-event-schedule__chip-meta {
    font-size: 0.8rem;
    color: rgba(47, 62, 158, 0.75);
}

.mj-event-schedule__chip-time {
    display: inline-flex;
    alignments: center;
    gap: 0.25rem;
}

.mj-event-schedule__table-wrapper {
    overflow-x: auto;
}

.mj-event-schedule__table {
    width: 100%;
    border-collapse: collapse;
    border: 1px solid var(--mj-schedule-border-color);
    background: #fff;
    border-radius: 10px;
    overflow: hidden;
}

.mj-event-schedule__table thead th {
    background: #f1f5fb;
    color: #2f3e9e;
    text-align: left;
    padding: 0.75rem 1rem;
    font-weight: 600;
}

.mj-event-schedule__table tbody td {
    padding: 0.75rem 1rem;
    border-top: 1px solid var(--mj-schedule-border-color);
}

.mj-event-schedule__table tbody tr.is-past {
    opacity: var(--mj-schedule-past-opacity);
}

.mj-event-schedule__table tbody tr.is-today {
    background: #eef6ff;
}

.mj-event-schedule__table--recurring thead th {
    text-align: center;
}

.mj-event-schedule__table--recurring tbody td {
    text-align: center;
}

@media (max-width: 600px) {
    .mj-event-schedule__cards {
        grid-template-columns: 1fr;
    }

    .mj-event-schedule__timeline::before {
        left: 0.4rem;
    }

    .mj-event-schedule__timeline-entry {
        padding-left: 1rem;
    }

    .mj-event-schedule__timeline-marker {
        left: -1.1rem;
    }
}
</style>
