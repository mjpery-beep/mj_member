<?php
/**
 * Events Calendar Widget Template
 * 
 * @var array $data Widget data from controller
 */

if (!defined('ABSPATH')) {
    exit;
}

$instance_id = $data['instance_id'] ?? '';
$settings = $data['settings'] ?? array();
$months = $data['months'] ?? array();
$has_any_event = $data['has_any_event'] ?? false;
$empty_message = $data['empty_message'] ?? '';
$sorted_filters = $data['sorted_filters'] ?? array();
$today_month_key = $data['today_month_key'] ?? '';
$preferred_index = $data['preferred_index'] ?? 0;
$next_event_pointer = $data['next_event_pointer'] ?? null;
$now_ts = $data['now_ts'] ?? current_time('timestamp');
$timezone = $data['timezone'] ?? wp_timezone();
$count_singular_label = $data['count_singular_label'] ?? __('%d événement', 'mj-member');
$count_plural_label = $data['count_plural_label'] ?? __('%d événements', 'mj-member');
$count_empty_label = $data['count_empty_label'] ?? __('Aucun événement', 'mj-member');

$month_keys = array_keys($months);
$initial_month_label = (!empty($month_keys) && isset($months[$month_keys[0]]['label'])) 
    ? $months[$month_keys[0]]['label'] 
    : '';

$week_days = array(
    __('Lun', 'mj-member'),
    __('Mar', 'mj-member'),
    __('Mer', 'mj-member'),
    __('Jeu', 'mj-member'),
    __('Ven', 'mj-member'),
    __('Sam', 'mj-member'),
    __('Dim', 'mj-member'),
);
?>

<div class="mj-events-calendar" 
     id="<?php echo esc_attr($instance_id); ?>" 
     data-calendar-preferred="<?php echo esc_attr($preferred_index); ?>" 
     data-calendar-today="<?php echo esc_attr($today_month_key); ?>"
     data-calendar-count-singular="<?php echo esc_attr($count_singular_label); ?>"
     data-calendar-count-plural="<?php echo esc_attr($count_plural_label); ?>"
     data-calendar-count-empty="<?php echo esc_attr($count_empty_label); ?>">

    <?php // Header with title and navigation ?>
    <div class="mj-events-calendar__header">
        <?php if (!empty($settings['title'])): ?>
            <h3 class="mj-events-calendar__title"><?php echo esc_html($settings['title']); ?></h3>
        <?php endif; ?>
        
        <nav class="mj-events-calendar__nav" aria-label="<?php esc_attr_e('Navigation du calendrier', 'mj-member'); ?>">
            <button type="button" 
                    class="mj-events-calendar__nav-btn" 
                    data-calendar-nav="prev" 
                    aria-label="<?php esc_attr_e('Mois précédent', 'mj-member'); ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="15 18 9 12 15 6"></polyline>
                </svg>
            </button>
            <span class="mj-events-calendar__month-label" data-calendar-active-label aria-live="polite" aria-atomic="true">
                <?php echo esc_html($initial_month_label); ?>
            </span>
            <button type="button" 
                    class="mj-events-calendar__nav-btn" 
                    data-calendar-nav="next" 
                    aria-label="<?php esc_attr_e('Mois suivant', 'mj-member'); ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
            </button>
        </nav>
    </div>

    <?php // Toolbar with filters and today button ?>
    <?php if (!empty($sorted_filters) || true): ?>
    <div class="mj-events-calendar__toolbar">
        <?php if (!empty($sorted_filters)): ?>
        <div class="mj-events-calendar__filters" role="group" aria-label="<?php esc_attr_e('Filtrer par type', 'mj-member'); ?>">
            <?php foreach ($sorted_filters as $filter_key => $filter_meta): 
                $filter_label = isset($filter_meta['label']) && $filter_meta['label'] !== '' 
                    ? (string) $filter_meta['label'] 
                    : ucfirst((string) $filter_key);
            ?>
            <label class="mj-events-calendar__filter">
                <input type="checkbox" value="<?php echo esc_attr($filter_key); ?>" data-calendar-filter checked />
                <span class="mj-events-calendar__filter-dot"></span>
                <span><?php echo esc_html($filter_label); ?></span>
            </label>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <button type="button" class="mj-events-calendar__today-btn" data-calendar-action="today">
            <?php esc_html_e('Aujourd\'hui', 'mj-member'); ?>
        </button>
    </div>
    <?php endif; ?>

    <?php // Months container ?>
    <div class="mj-events-calendar__months">
        <?php 
        $month_index = 0;
        foreach ($months as $month_key => $month_data): 
            $month_classes = array('mj-events-calendar__month');
            if ($month_data['has_next_event']) {
                $month_classes[] = 'is-next-event';
            }
            if ($month_index === $preferred_index) {
                $month_classes[] = 'is-active';
            }

            $first_day_week_index = (int) wp_date('N', $month_data['timestamp'], $timezone);
            $days_in_month = (int) wp_date('t', $month_data['timestamp'], $timezone);
            $month_prefix = wp_date('Y-m', $month_data['timestamp'], $timezone);
            $today_key = wp_date('Y-m-d', $now_ts, $timezone);
        ?>
        <section class="<?php echo esc_attr(implode(' ', $month_classes)); ?>" 
                 data-calendar-month="<?php echo esc_attr($month_key); ?>" 
                 data-calendar-label="<?php echo esc_attr($month_data['label']); ?>">
            
            <?php // Desktop Grid ?>
            <div class="mj-events-calendar__grid">
                <div class="mj-events-calendar__weekdays">
                    <?php foreach ($week_days as $day_label): ?>
                    <span class="mj-events-calendar__weekday"><?php echo esc_html($day_label); ?></span>
                    <?php endforeach; ?>
                </div>
                
                <div class="mj-events-calendar__days">
                    <?php 
                    // Padding cells before first day
                    for ($pad = 1; $pad < $first_day_week_index; $pad++): ?>
                    <div class="mj-events-calendar__day mj-events-calendar__day--padding"></div>
                    <?php endfor; ?>
                    
                    <?php 
                    // Actual days
                    for ($day_number = 1; $day_number <= $days_in_month; $day_number++):
                        $day_key = $month_prefix . '-' . str_pad((string) $day_number, 2, '0', STR_PAD_LEFT);
                        $day_bucket = isset($month_data['days'][$day_key]) ? $month_data['days'][$day_key] : array();
                        $events_for_day = isset($day_bucket['events']) ? $day_bucket['events'] : array();
                        $is_closure = !empty($day_bucket['is_closure']);
                        
                        $day_classes = array('mj-events-calendar__day');
                        if ($day_key === $today_key) {
                            $day_classes[] = 'mj-events-calendar__day--today';
                        }
                        if ($is_closure) {
                            $day_classes[] = 'mj-events-calendar__day--closure';
                        }
                        if (!empty($events_for_day)) {
                            $day_classes[] = 'has-events';
                        }
                        
                        // Sort events by start time
                        if (!empty($events_for_day)) {
                            usort($events_for_day, function($a, $b) {
                                return (int) ($a['start_ts'] ?? 0) <=> (int) ($b['start_ts'] ?? 0);
                            });
                        }
                    ?>
                    <div class="<?php echo esc_attr(implode(' ', $day_classes)); ?>" data-calendar-day="<?php echo esc_attr($day_key); ?>">
                        <span class="mj-events-calendar__day-num"><?php echo esc_html($day_number); ?></span>
                        
                        <?php if (!empty($events_for_day)): ?>
                        <div class="mj-events-calendar__events">
                            <?php foreach ($events_for_day as $event_entry): 
                                if (!is_array($event_entry) || !isset($event_entry['id'])) continue;
                                
                                $event_classes = array('mj-events-calendar__event');
                                $event_is_multi = !empty($event_entry['is_multi']);
                                $event_is_multi_head = !empty($event_entry['is_multi_head']);
                                $event_is_multi_tail = !empty($event_entry['is_multi_tail']);
                                $event_is_closure = !empty($event_entry['is_closure']);
                                
                                if ($event_is_multi) {
                                    $event_classes[] = 'mj-events-calendar__event--multi';
                                    if ($event_is_multi_head) {
                                        $event_classes[] = 'mj-events-calendar__event--multi-head';
                                    }
                                    if ($event_is_multi_tail) {
                                        $event_classes[] = 'mj-events-calendar__event--multi-tail';
                                    }
                                }
                                if ($event_is_closure) {
                                    $event_classes[] = 'mj-events-calendar__event--closure';
                                }
                                if ($next_event_pointer && $next_event_pointer['event_key'] === $event_entry['id']) {
                                    $event_classes[] = 'mj-events-calendar__event--next';
                                }
                                
                                $event_permalink = isset($event_entry['permalink']) ? (string) $event_entry['permalink'] : '';
                                $event_type_key = isset($event_entry['type_key']) ? sanitize_key((string) $event_entry['type_key']) : 'misc';
                                $event_range_key = isset($event_entry['range_key']) && $event_entry['range_key'] !== '' 
                                    ? (string) $event_entry['range_key'] 
                                    : (string) $event_entry['id'];
                                
                                // Build inline style from palette
                                $style_parts = array();
                                if (!empty($event_entry['palette']['base'])) {
                                    $style_parts[] = '--event-accent:' . $event_entry['palette']['base'];
                                }
                                if (!empty($event_entry['palette']['surface'])) {
                                    $style_parts[] = '--event-bg:' . $event_entry['palette']['surface'];
                                }
                                $style_attr = !empty($style_parts) ? ' style="' . esc_attr(implode(';', $style_parts)) . '"' : '';
                                
                                // Skip rendering content for multi-day middle segments
                                if ($event_is_multi && !$event_is_multi_head && !$event_is_multi_tail) {
                                    continue;
                                }
                            ?>
                            <?php if ($event_is_closure || $event_permalink === ''): ?>
                            <div class="<?php echo esc_attr(implode(' ', $event_classes)); ?>"
                                 data-calendar-type="<?php echo esc_attr($event_type_key); ?>"
                                 <?php if ($event_is_multi): ?>data-calendar-range="<?php echo esc_attr($event_range_key); ?>"<?php endif; ?>
                                 <?php echo $style_attr; ?>>
                            <?php else: ?>
                            <a href="<?php echo esc_url($event_permalink); ?>" 
                               class="<?php echo esc_attr(implode(' ', $event_classes)); ?>"
                               data-calendar-type="<?php echo esc_attr($event_type_key); ?>"
                               <?php if ($event_is_multi): ?>data-calendar-range="<?php echo esc_attr($event_range_key); ?>"<?php endif; ?>
                               <?php echo $style_attr; ?>>
                            <?php endif; ?>
                                <span class="mj-events-calendar__event-title"><?php echo esc_html($event_entry['title']); ?></span>
                                <?php if (!empty($event_entry['time']) && !$event_is_multi): ?>
                                <span class="mj-events-calendar__event-time"><?php echo esc_html($event_entry['time']); ?></span>
                                <?php endif; ?>
                            <?php if ($event_is_closure || $event_permalink === ''): ?>
                            </div>
                            <?php else: ?>
                            </a>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endfor; ?>
                    
                    <?php 
                    // Padding cells after last day
                    $total_cells = $first_day_week_index - 1 + $days_in_month;
                    $remaining = (7 - ($total_cells % 7)) % 7;
                    for ($pad = 0; $pad < $remaining; $pad++): ?>
                    <div class="mj-events-calendar__day mj-events-calendar__day--padding"></div>
                    <?php endfor; ?>
                </div>
            </div>
            
            <?php // Mobile List ?>
            <div class="mj-events-calendar__list" data-calendar-mobile>
                <?php 
                for ($day_number = 1; $day_number <= $days_in_month; $day_number++):
                    $day_key = $month_prefix . '-' . str_pad((string) $day_number, 2, '0', STR_PAD_LEFT);
                    $day_bucket = isset($month_data['days'][$day_key]) ? $month_data['days'][$day_key] : array();
                    $events_for_day = isset($day_bucket['events']) ? $day_bucket['events'] : array();
                    
                    if (empty($events_for_day)) continue;
                    
                    // Sort events
                    usort($events_for_day, function($a, $b) {
                        return (int) ($a['start_ts'] ?? 0) <=> (int) ($b['start_ts'] ?? 0);
                    });
                    
                    $day_timestamp = strtotime($day_key . ' 00:00:00');
                    $day_label = wp_date('l j F', $day_timestamp, $timezone);
                    $events_count = count($events_for_day);
                    $count_label = sprintf(_n('%d événement', '%d événements', $events_count, 'mj-member'), $events_count);
                    
                    $list_day_classes = array('mj-events-calendar__list-day');
                    if ($day_key === $today_key) {
                        $list_day_classes[] = 'mj-events-calendar__list-day--today';
                    }
                ?>
                <details class="<?php echo esc_attr(implode(' ', $list_day_classes)); ?>" 
                         data-calendar-day="<?php echo esc_attr($day_key); ?>" 
                         open>
                    <summary class="mj-events-calendar__list-header">
                        <span class="mj-events-calendar__list-date"><?php echo esc_html($day_label); ?></span>
                        <span class="mj-events-calendar__list-count" data-calendar-day-count><?php echo esc_html($count_label); ?></span>
                    </summary>
                    
                    <div class="mj-events-calendar__list-events">
                        <?php foreach ($events_for_day as $event_entry): 
                            if (!is_array($event_entry) || !isset($event_entry['id'])) continue;
                            
                            $event_is_closure = !empty($event_entry['is_closure']);
                            $event_permalink = isset($event_entry['permalink']) ? (string) $event_entry['permalink'] : '';
                            $event_type_key = isset($event_entry['type_key']) ? sanitize_key((string) $event_entry['type_key']) : 'misc';
                            
                            $list_event_classes = array('mj-events-calendar__list-event');
                            if ($event_is_closure) {
                                $list_event_classes[] = 'mj-events-calendar__list-event--closure';
                            }
                            
                            // Build inline style from palette
                            $style_parts = array();
                            if (!empty($event_entry['palette']['base'])) {
                                $style_parts[] = '--event-accent:' . $event_entry['palette']['base'];
                            }
                            if (!empty($event_entry['palette']['surface'])) {
                                $style_parts[] = '--event-bg:' . $event_entry['palette']['surface'];
                            }
                            if (!empty($event_entry['palette']['pill_bg'])) {
                                $style_parts[] = '--event-pill-bg:' . $event_entry['palette']['pill_bg'];
                            }
                            if (!empty($event_entry['palette']['pill_text'])) {
                                $style_parts[] = '--event-pill-text:' . $event_entry['palette']['pill_text'];
                            }
                            $style_attr = !empty($style_parts) ? ' style="' . esc_attr(implode(';', $style_parts)) . '"' : '';
                            
                            $tag = ($event_is_closure || $event_permalink === '') ? 'div' : 'a';
                            $href_attr = $tag === 'a' ? ' href="' . esc_url($event_permalink) . '"' : '';
                        ?>
                        <<?php echo $tag; ?> class="<?php echo esc_attr(implode(' ', $list_event_classes)); ?>"
                              data-calendar-type="<?php echo esc_attr($event_type_key); ?>"
                              <?php echo $href_attr; ?>
                              <?php echo $style_attr; ?>>
                            <?php if (!empty($event_entry['cover'])): ?>
                            <div class="mj-events-calendar__list-thumb">
                                <img src="<?php echo esc_url($event_entry['cover']); ?>" 
                                     alt="<?php echo esc_attr($event_entry['title']); ?>" 
                                     loading="lazy" />
                            </div>
                            <?php endif; ?>
                            <div class="mj-events-calendar__list-content">
                                <?php if (!empty($event_entry['type_label'])): ?>
                                <span class="mj-events-calendar__list-type"><?php echo esc_html($event_entry['type_label']); ?></span>
                                <?php endif; ?>
                                <span class="mj-events-calendar__list-title"><?php echo esc_html($event_entry['title']); ?></span>
                                <?php if (!empty($event_entry['time'])): ?>
                                <span class="mj-events-calendar__list-time"><?php echo esc_html($event_entry['time']); ?></span>
                                <?php endif; ?>
                            </div>
                        </<?php echo $tag; ?>>
                        <?php endforeach; ?>
                    </div>
                </details>
                <?php endfor; ?>
            </div>
        </section>
        <?php 
        $month_index++;
        endforeach; 
        ?>
    </div>

    <?php if (!$has_any_event && $empty_message !== ''): ?>
    <p class="mj-events-calendar__empty"><?php echo esc_html($empty_message); ?></p>
    <?php endif; ?>
</div>

<script>
window.mjMemberEventsCalendarQueue = window.mjMemberEventsCalendarQueue || [];
window.mjMemberEventsCalendarQueue.push({
    id: <?php echo wp_json_encode($instance_id); ?>,
    config: <?php echo wp_json_encode(array(
        'preferredIndex' => $preferred_index,
        'todayMonth' => $today_month_key,
    )); ?>
});
</script>
