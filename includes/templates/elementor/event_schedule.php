<?php
/**
 * Template: Event Schedule Widget
 * Affiche l'horaire (occurrences) d'un événement MJ.
 *
 * Variables disponibles via $template_data :
 * - event_id (int)
 * - title (string)
 * - display_title (bool)
 * - max_occurrences (int)
 * - show_past (bool)
 * - date_format (string) : 'full', 'short', 'medium', 'day_only'
 * - time_format (string) : '24h', '12h'
 * - layout (string) : 'list', 'grid', 'compact'
 * - empty_message (string)
 * - is_preview (bool)
 */

if (!defined('ABSPATH')) {
    exit;
}

use Mj\Member\Classes\Crud\MjEvents;
use Mj\Member\Classes\MjEventSchedule;

$event_id = isset($template_data['event_id']) ? (int) $template_data['event_id'] : 0;
$title = isset($template_data['title']) ? $template_data['title'] : '';
$display_title = !empty($template_data['display_title']);
$max_occurrences = isset($template_data['max_occurrences']) ? (int) $template_data['max_occurrences'] : 20;
$show_past = !empty($template_data['show_past']);
$date_format_setting = isset($template_data['date_format']) ? $template_data['date_format'] : 'full';
$time_format_setting = isset($template_data['time_format']) ? $template_data['time_format'] : '24h';
$layout = isset($template_data['layout']) ? $template_data['layout'] : 'list';
$empty_message = isset($template_data['empty_message']) ? $template_data['empty_message'] : __('Aucun horaire disponible.', 'mj-member');
$is_preview = !empty($template_data['is_preview']);

// Récupérer l'événement
$event = null;
$occurrences = array();
$is_recurring = false;
$recurring_weekdays = array();
$recurring_start_time = '';
$recurring_end_time = '';

if ($event_id > 0 && class_exists('Mj\\Member\\Classes\\Crud\\MjEvents')) {
    $event = MjEvents::find($event_id);
}

// Vérifier si l'événement est en mode récurrent hebdomadaire
if ($event) {
    $schedule_mode = isset($event->schedule_mode) ? $event->schedule_mode : 'fixed';
    
    if ($schedule_mode === 'recurring') {
        $is_recurring = true;
        
        // Récupérer le payload
        $schedule_payload = isset($event->schedule_payload) ? $event->schedule_payload : '';
        if (is_string($schedule_payload) && $schedule_payload !== '') {
            $schedule_payload = json_decode($schedule_payload, true);
        }
        if (!is_array($schedule_payload)) {
            $schedule_payload = array();
        }
        
        // Récupérer les jours configurés
        if (!empty($schedule_payload['weekdays']) && is_array($schedule_payload['weekdays'])) {
            $weekday_labels = array(
                'monday' => __('Lundi', 'mj-member'),
                'tuesday' => __('Mardi', 'mj-member'),
                'wednesday' => __('Mercredi', 'mj-member'),
                'thursday' => __('Jeudi', 'mj-member'),
                'friday' => __('Vendredi', 'mj-member'),
                'saturday' => __('Samedi', 'mj-member'),
                'sunday' => __('Dimanche', 'mj-member'),
            );
            
            foreach ($schedule_payload['weekdays'] as $weekday) {
                $weekday = strtolower(sanitize_key($weekday));
                if (isset($weekday_labels[$weekday])) {
                    $recurring_weekdays[] = $weekday_labels[$weekday];
                }
            }
        }
        
        // Récupérer la plage horaire
        $recurring_start_time = isset($schedule_payload['start_time']) ? $schedule_payload['start_time'] : '';
        $recurring_end_time = isset($schedule_payload['end_time']) ? $schedule_payload['end_time'] : '';
        
        // Formater les heures
        if ($recurring_start_time !== '') {
            $start_ts = strtotime('2000-01-01 ' . $recurring_start_time);
            if ($start_ts) {
                $recurring_start_time = ($time_format_setting === '12h') 
                    ? date('g:i A', $start_ts) 
                    : date('H:i', $start_ts);
            }
        }
        if ($recurring_end_time !== '') {
            $end_ts = strtotime('2000-01-01 ' . $recurring_end_time);
            if ($end_ts) {
                $recurring_end_time = ($time_format_setting === '12h') 
                    ? date('g:i A', $end_ts) 
                    : date('H:i', $end_ts);
            }
        }
    }
}

// En mode preview sans événement, générer des données factices
if ($is_preview && !$event) {
    $dummy_date = new DateTime('next monday');
    $occurrences = array();
    for ($i = 0; $i < min(5, $max_occurrences); $i++) {
        $start = clone $dummy_date;
        $start->modify("+{$i} days");
        $start->setTime(14, 0, 0);
        $end = clone $start;
        $end->setTime(17, 0, 0);
        $occurrences[] = array(
            'date' => $start->format('Y-m-d'),
            'start' => $start->format('Y-m-d H:i:s'),
            'end' => $end->format('Y-m-d H:i:s'),
            'timestamp' => $start->getTimestamp(),
            'start_time' => '14:00',
            'end_time' => '17:00',
        );
    }
} elseif ($event && !$is_recurring && class_exists('Mj\\Member\\Classes\\MjEventSchedule')) {
    // Récupérer les occurrences réelles (seulement si pas récurrent)
    $occurrence_args = array(
        'max' => $max_occurrences,
        'include_past' => $show_past,
    );
    $occurrences = MjEventSchedule::get_occurrences($event, $occurrence_args);
}

// Fonctions de formatage
$format_day = function($datetime_str) {
    $timestamp = strtotime($datetime_str);
    if (!$timestamp) {
        return '';
    }
    // Retourne uniquement le nom du jour (Lundi, Mardi, etc.)
    return wp_date('l', $timestamp);
};

$format_time = function($datetime_str) use ($time_format_setting) {
    $timestamp = strtotime($datetime_str);
    if (!$timestamp) {
        return '';
    }

    if ($time_format_setting === '12h') {
        return wp_date('g:i A', $timestamp);
    }

    return wp_date('H:i', $timestamp);
};

$layout_class = 'mj-event-schedule--' . esc_attr($layout);
?>

<div class="mj-event-schedule <?php echo $layout_class; ?>">
    <?php if ($display_title && $title !== '') : ?>
        <h3 class="mj-event-schedule__title"><?php echo esc_html($title); ?></h3>
    <?php endif; ?>

    <?php if ($is_recurring && !empty($recurring_weekdays)) : ?>
        <!-- Affichage simplifié pour événement récurrent hebdomadaire -->
        <div class="mj-event-schedule__recurring">
            <div class="mj-event-schedule__recurring-days">
                <?php foreach ($recurring_weekdays as $day_name) : ?>
                    <span class="mj-event-schedule__recurring-day"><?php echo esc_html($day_name); ?></span>
                <?php endforeach; ?>
            </div>
            <?php if ($recurring_start_time !== '' || $recurring_end_time !== '') : ?>
                <div class="mj-event-schedule__recurring-time">
                    <span class="mj-event-schedule__time-icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                    </span>
                    <span class="mj-event-schedule__time-text">
                        <?php echo esc_html($recurring_start_time); ?>
                        <?php if ($recurring_end_time !== '' && $recurring_end_time !== $recurring_start_time) : ?>
                            <span class="mj-event-schedule__time-separator">—</span>
                            <?php echo esc_html($recurring_end_time); ?>
                        <?php endif; ?>
                    </span>
                </div>
            <?php endif; ?>
        </div>
    <?php elseif (empty($occurrences)) : ?>
        <div class="mj-event-schedule__empty">
            <?php if ($is_preview && $event_id <= 0) : ?>
                <p class="mj-event-schedule__empty-preview">
                    <?php esc_html_e('Sélectionnez un événement dans les réglages du widget.', 'mj-member'); ?>
                </p>
            <?php else : ?>
                <p><?php echo esc_html($empty_message); ?></p>
            <?php endif; ?>
        </div>
    <?php else : ?>
        <div class="mj-event-schedule__list">
            <?php foreach ($occurrences as $occurrence) :
                $start_str = isset($occurrence['start']) ? $occurrence['start'] : '';
                $end_str = isset($occurrence['end']) ? $occurrence['end'] : '';
                
                $day_display = $format_day($start_str);
                $start_time_display = $format_time($start_str);
                $end_time_display = $format_time($end_str);

                // Vérifier si c'est passé
                $is_past = false;
                if ($start_str !== '') {
                    $start_timestamp = strtotime($start_str);
                    $is_past = $start_timestamp < time();
                }
            ?>
                <div class="mj-event-schedule__item<?php echo $is_past ? ' mj-event-schedule__item--past' : ''; ?>">
                    <div class="mj-event-schedule__day">
                        <span class="mj-event-schedule__day-text"><?php echo esc_html(ucfirst($day_display)); ?></span>
                    </div>
                    <div class="mj-event-schedule__time">
                        <span class="mj-event-schedule__time-icon">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"></circle>
                                <polyline points="12 6 12 12 16 14"></polyline>
                            </svg>
                        </span>
                        <span class="mj-event-schedule__time-text">
                            <?php echo esc_html($start_time_display); ?>
                            <?php if ($end_time_display !== '' && $end_time_display !== $start_time_display) : ?>
                                <span class="mj-event-schedule__time-separator">—</span>
                                <?php echo esc_html($end_time_display); ?>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
.mj-event-schedule {
    --mj-schedule-bg: transparent;
    --mj-schedule-item-bg: #f8f9fa;
    --mj-schedule-date-color: #333;
    --mj-schedule-time-color: #666;
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

/* Affichage récurrent hebdomadaire */
.mj-event-schedule__recurring {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    padding: 1rem;
    background: var(--mj-schedule-item-bg);
    border-radius: 8px;
    border: 1px solid var(--mj-schedule-border-color);
}

.mj-event-schedule__recurring-days {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.mj-event-schedule__recurring-day {
    display: inline-block;
    padding: 0.375rem 0.75rem;
    background: #e3f2fd;
    color: #1565c0;
    border-radius: 20px;
    font-weight: 500;
    font-size: 0.9rem;
}

.mj-event-schedule__recurring-time {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--mj-schedule-time-color);
    font-size: 1.1rem;
}

.mj-event-schedule__list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.mj-event-schedule__item {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem 1.5rem;
    padding: 0.875rem 1rem;
    background: var(--mj-schedule-item-bg);
    border-radius: 8px;
    border: 1px solid var(--mj-schedule-border-color);
    transition: box-shadow 0.2s ease;
}

.mj-event-schedule__item:hover {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

.mj-event-schedule__item--past {
    opacity: var(--mj-schedule-past-opacity);
}

.mj-event-schedule__day,
.mj-event-schedule__time {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.mj-event-schedule__day {
    color: var(--mj-schedule-date-color);
    font-weight: 500;
}

.mj-event-schedule__time {
    color: var(--mj-schedule-time-color);
}

.mj-event-schedule__time-icon {
    display: flex;
    align-items: center;
    color: var(--mj-schedule-icon-color);
}

.mj-event-schedule__time-separator {
    margin: 0 0.25rem;
}

/* Layout: Grid */
.mj-event-schedule--grid .mj-event-schedule__list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 1rem;
}

.mj-event-schedule--grid .mj-event-schedule__item {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.5rem;
}

/* Layout: Compact */
.mj-event-schedule--compact .mj-event-schedule__list {
    gap: 0.25rem;
}

.mj-event-schedule--compact .mj-event-schedule__item {
    padding: 0.5rem 0.75rem;
    border-radius: 4px;
    font-size: 0.875rem;
}

.mj-event-schedule--compact .mj-event-schedule__date-icon,
.mj-event-schedule--compact .mj-event-schedule__time-icon {
    display: none;
}

/* Responsive */
@media (max-width: 480px) {
    .mj-event-schedule__item {
        flex-direction: column;
        gap: 0.375rem;
    }
}
</style>
