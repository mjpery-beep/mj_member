<?php
/**
 * Template: Event Schedule Widget
 * Affiche l'horaire d'un événement MJ et adapte le rendu selon le type de planification.
 */

if (!defined('ABSPATH')) {
    exit;
}

use Mj\Member\Classes\Crud\MjEvents;
use Mj\Member\Classes\MjEventSchedule;
use Mj\Member\Classes\View\Schedule\ScheduleDisplayHelper;

$event_id = isset($template_data['event_id']) ? (int) $template_data['event_id'] : 0;
$title = isset($template_data['title']) ? (string) $template_data['title'] : '';
$display_title = !empty($template_data['display_title']);
$max_occurrences = isset($template_data['max_occurrences']) ? max(1, (int) $template_data['max_occurrences']) : 20;
$show_past = !empty($template_data['show_past']);
$date_format_setting = isset($template_data['date_format']) ? (string) $template_data['date_format'] : 'full';
$time_format_setting = isset($template_data['time_format']) ? (string) $template_data['time_format'] : '24h';
$layout_mode_fallback = isset($template_data['layout_mode_fallback']) ? sanitize_key((string) $template_data['layout_mode_fallback']) : 'list';
$layout_mode_fixed = isset($template_data['layout_mode_fixed']) ? sanitize_key((string) $template_data['layout_mode_fixed']) : 'card';
$layout_mode_range = isset($template_data['layout_mode_range']) ? sanitize_key((string) $template_data['layout_mode_range']) : 'timeline';
$layout_mode_series = isset($template_data['layout_mode_series']) ? sanitize_key((string) $template_data['layout_mode_series']) : 'list';
$layout_mode_recurring = isset($template_data['layout_mode_recurring']) ? sanitize_key((string) $template_data['layout_mode_recurring']) : 'cards';
$show_icons = !empty($template_data['show_icons']);
$highlight_today = !empty($template_data['highlight_today']);
$show_next_occurrence_label = array_key_exists('show_next_occurrence_label', $template_data)
    ? (bool) $template_data['show_next_occurrence_label']
    : true;
$empty_message = isset($template_data['empty_message']) && $template_data['empty_message'] !== ''
    ? (string) $template_data['empty_message']
    : __('Aucun horaire disponible pour cet événement.', 'mj-member');
$show_register_button = !empty($template_data['show_register_button']);
$register_button_label = isset($template_data['register_button_label']) && $template_data['register_button_label'] !== ''
    ? (string) $template_data['register_button_label']
    : __('Inscription', 'mj-member');
$is_preview = !empty($template_data['is_preview']);

// Map Elementor display options to WordPress date/time formats.
$time_format_pattern = $time_format_setting === '12h' ? 'g:i A' : 'H:i';
$date_format_map = array(
    'full' => 'l j F Y',
    'short' => 'd/m/Y',
    'medium' => 'j M Y',
    'day_only' => 'j F',
);
$date_format_pattern = isset($date_format_map[$date_format_setting]) ? $date_format_map[$date_format_setting] : $date_format_map['full'];

$site_timezone = wp_timezone();
if (!($site_timezone instanceof \DateTimeZone)) {
    $site_timezone = new \DateTimeZone('UTC');
}

$format_time = static function (string $value) use ($time_format_pattern, $site_timezone): string {
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    try {
        $datetime = new \DateTimeImmutable($value, $site_timezone);
    } catch (\Exception $exception) {
        return '';
    }

    return $datetime->format($time_format_pattern);
};

$format_time_string = static function (string $value) use ($time_format_pattern, $site_timezone): string {
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $time_candidate = $value;
    if (preg_match('/^\d{1,2}:\d{2}$/', $time_candidate)) {
        $time_candidate .= ':00';
    }

    try {
        $datetime = new \DateTimeImmutable('1970-01-01 ' . $time_candidate, $site_timezone);
    } catch (\Exception $exception) {
        return '';
    }

    return $datetime->format($time_format_pattern);
};

$format_day = static function (string $value): string {
    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return '';
    }

    return wp_date('l', $timestamp);
};

$format_date_label = static function (string $value) use ($date_format_pattern): string {
    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return '';
    }

    return wp_date($date_format_pattern, $timestamp);
};

$format_time_for_display = static function (string $time) use ($format_time_string): string {
    return $format_time_string($time);
};

$blank_weekly_schedule = static function (bool $showDateRange = false): array {
    return array(
        'is_weekly' => false,
        'is_monthly' => false,
        'is_series' => false,
        'show_date_range' => $showDateRange,
        'monthly_label' => '',
        'time_range' => '',
        'days' => array(),
        'series_items' => array(),
    );
};

$sanitize_time_value = static function ($value): string {
    if (is_string($value)) {
        $candidate = trim($value);
    } elseif (is_numeric($value)) {
        $candidate = trim((string) $value);
    } else {
        return '';
    }

    if ($candidate === '') {
        return '';
    }

    if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $candidate)) {
        return $candidate;
    }

    if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d$/', $candidate)) {
        return substr($candidate, 0, 5);
    }

    return '';
};

$sanitize_date_value = static function ($value): string {
    if (!is_string($value)) {
        return '';
    }

    $candidate = trim($value);
    if ($candidate === '') {
        return '';
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $candidate)) {
        return '';
    }

    return $candidate;
};

$to_bool = static function ($value, bool $default = false): bool {
    if (is_bool($value)) {
        return $value;
    }

    $filtered = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    if ($filtered !== null) {
        return (bool) $filtered;
    }

    return $default;
};

$decode_schedule_payload = static function ($payload): array {
    if (is_string($payload) && $payload !== '') {
        $decoded = json_decode($payload, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    if (is_array($payload)) {
        return $payload;
    }

    return array();
};

$sanitize_occurrence_generator_plan = static function (array $input) use ($sanitize_time_value, $sanitize_date_value, $to_bool): array {
    $knownKeys = array(
        'mode',
        'frequency',
        'startDate',
        'startDateISO',
        'endDate',
        'endDateISO',
        'startTime',
        'endTime',
        'days',
        'overrides',
        'timeOverrides',
        'monthlyOrdinal',
        'monthlyWeekday',
        'explicitStart',
        '_explicitStart',
        'version',
    );

    $hasKnown = false;
    foreach ($knownKeys as $key) {
        if (array_key_exists($key, $input)) {
            $hasKnown = true;
            break;
        }
    }

    if (!$hasKnown) {
        return array();
    }

    $weekdayKeys = array('mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun');

    $mode = isset($input['mode']) ? sanitize_key((string) $input['mode']) : '';
    if (!in_array($mode, array('weekly', 'monthly', 'range', 'custom'), true)) {
        $mode = 'weekly';
    }

    $frequency = isset($input['frequency']) ? sanitize_key((string) $input['frequency']) : '';
    if (!in_array($frequency, array('every_week', 'every_two_weeks'), true)) {
        $frequency = 'every_week';
    }

    $startDate = '';
    foreach (array('startDateISO', 'startDate') as $key) {
        if (!isset($input[$key])) {
            continue;
        }
        $candidate = $sanitize_date_value($input[$key]);
        if ($candidate !== '') {
            $startDate = $candidate;
            break;
        }
    }

    $endDate = '';
    foreach (array('endDateISO', 'endDate') as $key) {
        if (!isset($input[$key])) {
            continue;
        }
        $candidate = $sanitize_date_value($input[$key]);
        if ($candidate !== '') {
            $endDate = $candidate;
            break;
        }
    }

    $startTime = $sanitize_time_value(isset($input['startTime']) ? $input['startTime'] : '');
    $endTime = $sanitize_time_value(isset($input['endTime']) ? $input['endTime'] : '');

    $days = array();
    foreach ($weekdayKeys as $weekday) {
        $days[$weekday] = false;
    }

    if (isset($input['days']) && is_array($input['days'])) {
        $daysSource = $input['days'];
        if (array_values($daysSource) === $daysSource) {
            foreach ($daysSource as $value) {
                $weekday = sanitize_key((string) $value);
                if (isset($days[$weekday])) {
                    $days[$weekday] = true;
                }
            }
        } else {
            foreach ($daysSource as $weekday => $flag) {
                $weekdayKey = sanitize_key((string) $weekday);
                if (isset($days[$weekdayKey])) {
                    $days[$weekdayKey] = !empty($flag);
                }
            }
        }
    }

    $overrides = array();
    $overridesSource = array();
    if (isset($input['overrides']) && is_array($input['overrides'])) {
        $overridesSource = $input['overrides'];
    } elseif (isset($input['timeOverrides']) && is_array($input['timeOverrides'])) {
        $overridesSource = $input['timeOverrides'];
    }

    foreach ($overridesSource as $weekday => $times) {
        $weekdayKey = sanitize_key((string) $weekday);
        if (!isset($days[$weekdayKey]) || !is_array($times)) {
            continue;
        }

        $entry = array();
        if (isset($times['start'])) {
            $sanitized = $sanitize_time_value($times['start']);
            if ($sanitized !== '') {
                $entry['start'] = $sanitized;
            }
        }
        if (isset($times['end'])) {
            $sanitized = $sanitize_time_value($times['end']);
            if ($sanitized !== '') {
                $entry['end'] = $sanitized;
            }
        }

        if (!empty($entry)) {
            $overrides[$weekdayKey] = $entry;
        }
    }

    $monthlyOrdinal = isset($input['monthlyOrdinal']) ? sanitize_key((string) $input['monthlyOrdinal']) : '';
    if (!in_array($monthlyOrdinal, array('first', 'second', 'third', 'fourth', 'last'), true)) {
        $monthlyOrdinal = 'first';
    }

    $monthlyWeekday = isset($input['monthlyWeekday']) ? sanitize_key((string) $input['monthlyWeekday']) : '';
    if (!in_array($monthlyWeekday, $weekdayKeys, true)) {
        $monthlyWeekday = 'mon';
    }

    $explicitStart = false;
    foreach (array('explicitStart', '_explicitStart') as $flagKey) {
        if (array_key_exists($flagKey, $input)) {
            $explicitStart = $to_bool($input[$flagKey], $explicitStart);
        }
    }
    if ($startDate !== '') {
        $explicitStart = true;
    }

    return array(
        'mode' => $mode,
        'frequency' => $frequency,
        'start_date' => $startDate,
        'end_date' => $endDate,
        'start_time' => $startTime,
        'end_time' => $endTime,
        'days' => $days,
        'overrides' => $overrides,
        'monthly_ordinal' => $monthlyOrdinal,
        'monthly_weekday' => $monthlyWeekday,
        'explicit_start' => $explicitStart,
    );
};

$extract_occurrence_generator_plan = static function (array $payload) use ($sanitize_occurrence_generator_plan): array {
    $candidates = array();

    if (isset($payload['occurrence_generator']) && is_array($payload['occurrence_generator'])) {
        $candidates[] = $payload['occurrence_generator'];
    }

    if (isset($payload['occurrenceGenerator']) && is_array($payload['occurrenceGenerator'])) {
        $candidates[] = $payload['occurrenceGenerator'];
    }

    foreach ($candidates as $candidate) {
        $plan = $sanitize_occurrence_generator_plan($candidate);
        if (empty($plan)) {
            continue;
        }

        if (isset($payload['monthlyOrdinal'])) {
            $ordinal = sanitize_key((string) $payload['monthlyOrdinal']);
            if ($ordinal !== '') {
                $plan['monthly_ordinal'] = $ordinal;
            }
        }

        if (isset($payload['monthlyWeekday'])) {
            $weekday = sanitize_key((string) $payload['monthlyWeekday']);
            if ($weekday !== '') {
                $plan['monthly_weekday'] = $weekday;
            }
        }

        return $plan;
    }

    return array();
};

$build_weekly_schedule_from_generator = static function (array $plan, bool $showDateRange) use ($format_time_for_display, $blank_weekly_schedule): array {
    $weekdayLabels = array(
        'monday' => __('Lundi', 'mj-member'),
        'tuesday' => __('Mardi', 'mj-member'),
        'wednesday' => __('Mercredi', 'mj-member'),
        'thursday' => __('Jeudi', 'mj-member'),
        'friday' => __('Vendredi', 'mj-member'),
        'saturday' => __('Samedi', 'mj-member'),
        'sunday' => __('Dimanche', 'mj-member'),
    );

    $dayMap = array(
        'mon' => 'monday',
        'tue' => 'tuesday',
        'wed' => 'wednesday',
        'thu' => 'thursday',
        'fri' => 'friday',
        'sat' => 'saturday',
        'sun' => 'sunday',
    );

    $days = array();
    $planDays = isset($plan['days']) && is_array($plan['days']) ? $plan['days'] : array();
    $overrides = isset($plan['overrides']) && is_array($plan['overrides']) ? $plan['overrides'] : array();
    $defaultStart = isset($plan['start_time']) ? (string) $plan['start_time'] : '';
    $defaultEnd = isset($plan['end_time']) ? (string) $plan['end_time'] : '';

    foreach ($dayMap as $short => $long) {
        if (empty($planDays[$short])) {
            continue;
        }

        $startTime = $defaultStart;
        $endTime = $defaultEnd;

        if (isset($overrides[$short]) && is_array($overrides[$short])) {
            $override = $overrides[$short];
            if (isset($override['start']) && $override['start'] !== '') {
                $startTime = (string) $override['start'];
            }
            if (isset($override['end']) && $override['end'] !== '') {
                $endTime = (string) $override['end'];
            }
        }

        $startFormatted = $format_time_for_display($startTime);
        $endFormatted = $format_time_for_display($endTime);

        $timeRange = '';
        if ($startFormatted !== '' && $endFormatted !== '') {
            $timeRange = $startFormatted . ' - ' . $endFormatted;
        } elseif ($startFormatted !== '') {
            $timeRange = sprintf(__('à partir de %s', 'mj-member'), $startFormatted);
        } elseif ($endFormatted !== '') {
            $timeRange = $endFormatted;
        }

        $days[] = array(
            'key' => $long,
            'label' => isset($weekdayLabels[$long]) ? $weekdayLabels[$long] : $long,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'start_formatted' => $startFormatted,
            'end_formatted' => $endFormatted,
            'time_range' => $timeRange,
        );
    }

    if (empty($days)) {
        return $blank_weekly_schedule($showDateRange);
    }

    return array(
        'is_weekly' => true,
        'is_monthly' => false,
        'is_series' => false,
        'show_date_range' => $showDateRange,
        'monthly_label' => '',
        'time_range' => '',
        'days' => $days,
        'series_items' => array(),
    );
};

$build_monthly_schedule_from_generator = static function (array $plan, bool $showDateRange) use ($format_time_for_display, $blank_weekly_schedule): array {
    $weekdayLabels = array(
        'monday' => __('Lundi', 'mj-member'),
        'tuesday' => __('Mardi', 'mj-member'),
        'wednesday' => __('Mercredi', 'mj-member'),
        'thursday' => __('Jeudi', 'mj-member'),
        'friday' => __('Vendredi', 'mj-member'),
        'saturday' => __('Samedi', 'mj-member'),
        'sunday' => __('Dimanche', 'mj-member'),
    );

    $ordinalLabels = array(
        'first' => __('1er', 'mj-member'),
        'second' => __('2ème', 'mj-member'),
        'third' => __('3ème', 'mj-member'),
        'fourth' => __('4ème', 'mj-member'),
        'last' => __('Dernier', 'mj-member'),
    );

    $dayMap = array(
        'mon' => 'monday',
        'tue' => 'tuesday',
        'wed' => 'wednesday',
        'thu' => 'thursday',
        'fri' => 'friday',
        'sat' => 'saturday',
        'sun' => 'sunday',
    );

    $ordinalKey = isset($plan['monthly_ordinal']) ? (string) $plan['monthly_ordinal'] : 'first';
    $weekdayShort = isset($plan['monthly_weekday']) ? (string) $plan['monthly_weekday'] : 'mon';
    $weekdayKey = isset($dayMap[$weekdayShort]) ? $dayMap[$weekdayShort] : $weekdayShort;

    $ordinalLabel = isset($ordinalLabels[$ordinalKey]) ? $ordinalLabels[$ordinalKey] : $ordinalKey;
    $weekdayLabel = isset($weekdayLabels[$weekdayKey]) ? $weekdayLabels[$weekdayKey] : $weekdayKey;

    $defaultStart = isset($plan['start_time']) ? (string) $plan['start_time'] : '';
    $defaultEnd = isset($plan['end_time']) ? (string) $plan['end_time'] : '';

    $overrides = isset($plan['overrides']) && is_array($plan['overrides']) ? $plan['overrides'] : array();
    if (isset($overrides[$weekdayShort]) && is_array($overrides[$weekdayShort])) {
        $override = $overrides[$weekdayShort];
        if (isset($override['start']) && $override['start'] !== '') {
            $defaultStart = (string) $override['start'];
        }
        if (isset($override['end']) && $override['end'] !== '') {
            $defaultEnd = (string) $override['end'];
        }
    }

    $startFormatted = $format_time_for_display($defaultStart);
    $endFormatted = $format_time_for_display($defaultEnd);

    $timeRange = '';
    if ($startFormatted !== '' && $endFormatted !== '') {
        $timeRange = $startFormatted . ' - ' . $endFormatted;
    } elseif ($startFormatted !== '') {
        $timeRange = sprintf(__('à partir de %s', 'mj-member'), $startFormatted);
    } elseif ($endFormatted !== '') {
        $timeRange = $endFormatted;
    }

    $monthlyLabel = trim(implode(' ', array_filter(array($ordinalLabel, $weekdayLabel, __('du mois', 'mj-member')))));

    if ($monthlyLabel === '') {
        return $blank_weekly_schedule($showDateRange);
    }

    return array(
        'is_weekly' => false,
        'is_monthly' => true,
        'is_series' => false,
        'show_date_range' => $showDateRange,
        'monthly_label' => $monthlyLabel,
        'time_range' => $timeRange,
        'days' => array(),
        'series_items' => array(),
    );
};

$build_recurring_weekly_schedule = static function (array $payload, bool $showDateRange) use ($format_time_for_display, $blank_weekly_schedule): array {
    $weekday_labels = array(
        'monday' => __('Lundi', 'mj-member'),
        'tuesday' => __('Mardi', 'mj-member'),
        'wednesday' => __('Mercredi', 'mj-member'),
        'thursday' => __('Jeudi', 'mj-member'),
        'friday' => __('Vendredi', 'mj-member'),
        'saturday' => __('Samedi', 'mj-member'),
        'sunday' => __('Dimanche', 'mj-member'),
    );

    $weekdays = isset($payload['weekdays']) && is_array($payload['weekdays']) ? $payload['weekdays'] : array();
    $weekday_times = isset($payload['weekday_times']) && is_array($payload['weekday_times']) ? $payload['weekday_times'] : array();
    $default_start = isset($payload['start_time']) ? (string) $payload['start_time'] : '';
    $default_end = isset($payload['end_time']) ? (string) $payload['end_time'] : '';

    $days = array();
    foreach ($weekdays as $day_raw) {
        $day_key = sanitize_key((string) $day_raw);
        if (!isset($weekday_labels[$day_key])) {
            continue;
        }

        $start_time = $default_start;
        $end_time = $default_end;

        if (isset($weekday_times[$day_key]) && is_array($weekday_times[$day_key])) {
            $day_times = $weekday_times[$day_key];
            if (!empty($day_times['start'])) {
                $start_time = (string) $day_times['start'];
            }
            if (!empty($day_times['end'])) {
                $end_time = (string) $day_times['end'];
            }
        }

        $start_formatted = $format_time_for_display($start_time);
        $end_formatted = $format_time_for_display($end_time);

        $time_range = '';
        if ($start_formatted !== '' && $end_formatted !== '') {
            $time_range = $start_formatted . ' - ' . $end_formatted;
        } elseif ($start_formatted !== '') {
            $time_range = sprintf(__('à partir de %s', 'mj-member'), $start_formatted);
        } elseif ($end_formatted !== '') {
            $time_range = $end_formatted;
        }

        $days[] = array(
            'key' => $day_key,
            'label' => $weekday_labels[$day_key],
            'start_time' => $start_time,
            'end_time' => $end_time,
            'start_formatted' => $start_formatted,
            'end_formatted' => $end_formatted,
            'time_range' => $time_range,
        );
    }

    if (empty($days)) {
        return $blank_weekly_schedule($showDateRange);
    }

    return array(
        'is_weekly' => true,
        'is_monthly' => false,
        'is_series' => false,
        'show_date_range' => $showDateRange,
        'monthly_label' => '',
        'time_range' => '',
        'days' => $days,
        'series_items' => array(),
    );
};

$build_monthly_schedule_data = static function (array $payload, bool $showDateRange) use ($format_time_for_display, $blank_weekly_schedule): array {
    $weekday_labels = array(
        'monday' => __('Lundi', 'mj-member'),
        'tuesday' => __('Mardi', 'mj-member'),
        'wednesday' => __('Mercredi', 'mj-member'),
        'thursday' => __('Jeudi', 'mj-member'),
        'friday' => __('Vendredi', 'mj-member'),
        'saturday' => __('Samedi', 'mj-member'),
        'sunday' => __('Dimanche', 'mj-member'),
    );

    $ordinal_labels = array(
        'first' => __('1er', 'mj-member'),
        'second' => __('2ème', 'mj-member'),
        'third' => __('3ème', 'mj-member'),
        'fourth' => __('4ème', 'mj-member'),
        'last' => __('Dernier', 'mj-member'),
    );

    $ordinal_key = sanitize_key(isset($payload['ordinal']) ? (string) $payload['ordinal'] : '');
    $weekday_key = sanitize_key(isset($payload['weekday']) ? (string) $payload['weekday'] : '');

    $ordinal_label = isset($ordinal_labels[$ordinal_key]) ? $ordinal_labels[$ordinal_key] : $ordinal_key;
    $weekday_label = isset($weekday_labels[$weekday_key]) ? $weekday_labels[$weekday_key] : $weekday_key;

    $start_time = isset($payload['start_time']) ? (string) $payload['start_time'] : '';
    $end_time = isset($payload['end_time']) ? (string) $payload['end_time'] : '';

    $start_formatted = $format_time_for_display($start_time);
    $end_formatted = $format_time_for_display($end_time);

    $time_range = '';
    if ($start_formatted !== '' && $end_formatted !== '') {
        $time_range = $start_formatted . ' - ' . $end_formatted;
    } elseif ($start_formatted !== '') {
        $time_range = sprintf(__('à partir de %s', 'mj-member'), $start_formatted);
    } elseif ($end_formatted !== '') {
        $time_range = $end_formatted;
    }

    $monthly_label = trim(implode(' ', array_filter(array($ordinal_label, $weekday_label, __('du mois', 'mj-member')))));

    if ($monthly_label === '') {
        return $blank_weekly_schedule($showDateRange);
    }

    return array(
        'is_weekly' => false,
        'is_monthly' => true,
        'is_series' => false,
        'show_date_range' => $showDateRange,
        'monthly_label' => $monthly_label,
        'time_range' => $time_range,
        'days' => array(),
        'series_items' => array(),
    );
};

$build_series_schedule_data = static function (array $payload, bool $showDateRange) use ($format_time_for_display, $blank_weekly_schedule): array {
    $items = isset($payload['items']) && is_array($payload['items']) ? $payload['items'] : array();
    if (empty($items)) {
        return $blank_weekly_schedule($showDateRange);
    }

    $series_items = array();

    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $date_raw = isset($item['date']) ? (string) $item['date'] : '';
        if ($date_raw === '') {
            continue;
        }

        $timestamp = strtotime($date_raw);
        $date_formatted = $timestamp ? date_i18n('D j M', $timestamp) : $date_raw;

        $start_time = isset($item['start_time']) ? (string) $item['start_time'] : '';
        $end_time = isset($item['end_time']) ? (string) $item['end_time'] : '';

        $start_formatted = $format_time_for_display($start_time);
        $end_formatted = $format_time_for_display($end_time);

        $time_range = '';
        if ($start_formatted !== '' && $end_formatted !== '') {
            $time_range = $start_formatted . ' - ' . $end_formatted;
        } elseif ($start_formatted !== '') {
            $time_range = $start_formatted;
        } elseif ($end_formatted !== '') {
            $time_range = $end_formatted;
        }

        $series_items[] = array(
            'date' => $date_raw,
            'date_formatted' => $date_formatted,
            'start_time' => $start_time,
            'end_time' => $end_time,
            'time_range' => $time_range,
            'timestamp' => $timestamp ? $timestamp : 0,
        );
    }

    if (!empty($series_items)) {
        usort(
            $series_items,
            static function (array $left, array $right): int {
                $left_ts = isset($left['timestamp']) ? (int) $left['timestamp'] : 0;
                $right_ts = isset($right['timestamp']) ? (int) $right['timestamp'] : 0;
                return $left_ts <=> $right_ts;
            }
        );
    }

    if (empty($series_items)) {
        return $blank_weekly_schedule($showDateRange);
    }

    return array(
        'is_weekly' => false,
        'is_monthly' => false,
        'is_series' => true,
        'show_date_range' => $showDateRange,
        'monthly_label' => '',
        'time_range' => '',
        'days' => array(),
        'series_items' => $series_items,
    );
};

$event = null;
$event_array = array();
$event_for_schedule = null;

if ($event_id > 0 and class_exists(MjEvents::class)) {
    $event = MjEvents::find($event_id);

    if (is_object($event) and method_exists($event, 'toArray')) {
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

$get_event_value = static function ($key, $default = null) use ($event, $event_array) {
    if (!empty($event_array) and array_key_exists($key, $event_array)) {
        return $event_array[$key];
    }

    if ($event and isset($event->$key)) {
        return $event->$key;
    }

    if (is_array($event) and array_key_exists($key, $event)) {
        return $event[$key];
    }

    return $default;
};

$schedule_mode = 'unknown';
$schedule_payload_raw = array();
if ($event_for_schedule) {
    $schedule_mode_raw = $get_event_value('schedule_mode', 'fixed');
    $schedule_mode = $schedule_mode_raw !== null ? sanitize_key((string) $schedule_mode_raw) : 'fixed';
    if ($schedule_mode === '') {
        $schedule_mode = 'fixed';
    }

    $schedule_payload_raw = $get_event_value('schedule_payload', array());
}

$schedule_payload = $decode_schedule_payload($schedule_payload_raw);
$show_date_range = !empty($schedule_payload['show_date_range']);
$generator_plan = $extract_occurrence_generator_plan($schedule_payload);

$weekly_schedule = $blank_weekly_schedule($show_date_range);
if ($schedule_mode === 'series') {
    $weekly_schedule = $build_series_schedule_data($schedule_payload, $show_date_range);
} elseif ($schedule_mode === 'recurring') {
    if (!empty($generator_plan)) {
        $plan_mode = isset($generator_plan['mode']) ? (string) $generator_plan['mode'] : 'weekly';
        if ($plan_mode === 'monthly') {
            $weekly_schedule = $build_monthly_schedule_from_generator($generator_plan, $show_date_range);
        } else {
            $weekly_schedule = $build_weekly_schedule_from_generator($generator_plan, $show_date_range);
        }
    } else {
        $frequency = isset($schedule_payload['frequency']) ? (string) $schedule_payload['frequency'] : '';
        if ($frequency === 'monthly') {
            $weekly_schedule = $build_monthly_schedule_data($schedule_payload, $show_date_range);
        } else {
            $weekly_schedule = $build_recurring_weekly_schedule($schedule_payload, $show_date_range);
        }
    }
}

$schedule_summary = '';
$summary_keys = array('occurrence_schedule_summary', 'schedule_summary');
foreach ($summary_keys as $summary_key) {
    $candidate = $get_event_value($summary_key, '');
    if (is_string($candidate) and $candidate !== '') {
        $schedule_summary = (string) $candidate;
        break;
    }
}

$display_label = '';
$label_keys = array('display_date_label', 'display_label');
foreach ($label_keys as $label_key) {
    $candidate = $get_event_value($label_key, '');
    if (is_string($candidate) and $candidate !== '') {
        $display_label = (string) $candidate;
        break;
    }
}

$date_debut = (string) $get_event_value('date_debut', '');
$date_fin = (string) $get_event_value('date_fin', '');

$occurrence_args = array(
    'max' => $max_occurrences,
    'include_past' => (bool) $show_past,
);

$occurrences = array();

if ($event_for_schedule and class_exists(MjEventSchedule::class)) {
    $occurrences = MjEventSchedule::get_occurrences($event_for_schedule, $occurrence_args);

    if (empty($occurrences)) {
        $raw_occurrences = MjEventSchedule::build_all_occurrences($event_for_schedule);
        if (is_array($raw_occurrences) and !empty($raw_occurrences)) {
            $now_ts = current_time('timestamp');
            foreach ($raw_occurrences as $raw_occurrence) {
                if (!isset($raw_occurrence['start']) or !isset($raw_occurrence['timestamp'])) {
                    continue;
                }

                $timestamp = (int) $raw_occurrence['timestamp'];
                if (!$show_past and $timestamp < $now_ts) {
                    continue;
                }

                $raw_occurrence['is_past'] = ($timestamp < $now_ts);
                $occurrences[] = $raw_occurrence;
            }

            if (!empty($occurrences)) {
                usort(
                    $occurrences,
                    static function (array $left, array $right): int {
                        $left_ts = isset($left['timestamp']) ? (int) $left['timestamp'] : 0;
                        $right_ts = isset($right['timestamp']) ? (int) $right['timestamp'] : 0;
                        return $left_ts <=> $right_ts;
                    }
                );

                if ($max_occurrences > 0 and count($occurrences) > $max_occurrences) {
                    $occurrences = array_slice($occurrences, 0, $max_occurrences);
                }
            }
        }
    }
}

if ($is_preview and !$event_for_schedule) {
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

if (empty($occurrences) and $event_for_schedule) {
    $start_raw = (string) $get_event_value('date_debut', '');
    if ($start_raw !== '') {
        $end_raw = (string) $get_event_value('date_fin', '');
        $manual_start = strtotime($start_raw);
        if ($manual_start) {
            $manual_end = $end_raw !== '' ? strtotime($end_raw) : false;
            if ($manual_end === false or $manual_end <= $manual_start) {
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

$now_timestamp = current_time('timestamp');
foreach ($occurrences as &$occurrence) {
    if (!isset($occurrence['timestamp'])) {
        $start_candidate = isset($occurrence['start']) ? strtotime((string) $occurrence['start']) : false;
        $occurrence['timestamp'] = $start_candidate ? $start_candidate : 0;
    }

    if (!isset($occurrence['is_past'])) {
        $occurrence['is_past'] = ($occurrence['timestamp'] < $now_timestamp);
    }
}
unset($occurrence);

$next_occurrence = null;
foreach ($occurrences as $occurrence) {
    if ((int) $occurrence['timestamp'] >= $now_timestamp) {
        $next_occurrence = $occurrence;
        break;
    }
}
if ($next_occurrence === null and !empty($occurrences)) {
    $next_occurrence = $occurrences[0];
}

$next_occurrence_label = '';
if ($next_occurrence) {
    $label_date = $format_date_label(isset($next_occurrence['start']) ? (string) $next_occurrence['start'] : '');
    $label_start = $format_time(isset($next_occurrence['start']) ? (string) $next_occurrence['start'] : '');
    $label_end = $format_time(isset($next_occurrence['end']) ? (string) $next_occurrence['end'] : '');
    if ($label_date !== '') {
        if ($label_start !== '') {
            $next_occurrence_label = $label_date . ' · ' . $label_start;
            if ($label_end !== '' and $label_end !== $label_start) {
                $next_occurrence_label .= ' → ' . $label_end;
            }
        } else {
            $next_occurrence_label = $label_date;
        }
    }
}

$entries = array();
$today_key = wp_date('Y-m-d');
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
    $is_today_entry = ($highlight_today and $date_key !== '' and $date_key === $today_key);

    $entries[] = array(
        'day' => $start_day,
        'date' => $date_label,
        'start_time' => $start_time_label,
        'end_time' => $end_time_label,
        'is_past' => $is_past_entry,
        'is_today' => $is_today_entry,
    );
}

$has_recurring = ($schedule_mode === 'recurring') and (!empty($weekly_schedule['days']) or !empty($weekly_schedule['is_monthly']));
$has_series = ($schedule_mode === 'series') and !empty($weekly_schedule['series_items']);
$has_entries = !empty($entries);

$active_layout = $layout_mode_fallback;
if ($has_recurring) {
    $active_layout = $layout_mode_recurring;
} elseif ($has_series) {
    $active_layout = $layout_mode_series;
} elseif ($has_entries) {
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

$schedule_class = 'mj-event-schedule--mode-' . sanitize_html_class($schedule_mode !== '' ? $schedule_mode : 'unknown');
$layout_class = 'mj-event-schedule--layout-' . sanitize_html_class($active_layout !== '' ? $active_layout : 'list');

$wrapper_classes = array(
    'mj-event-schedule',
    $schedule_class,
    $layout_class,
);

if ($highlight_today) {
    $wrapper_classes[] = 'mj-event-schedule--highlight-today';
}
if ($show_icons) {
    $wrapper_classes[] = 'mj-event-schedule--show-icons';
}

$event_permalink = '';
if ($show_register_button) {
    if ($event_for_schedule) {
        $candidate_permalink = apply_filters('mj_member_event_permalink', '', $event_for_schedule);
        if (is_string($candidate_permalink) and $candidate_permalink !== '') {
            $event_permalink = $candidate_permalink;
        }
    }

    if ($event_permalink === '') {
        $article_permalink_candidate = $get_event_value('article_permalink', '');
        if (is_string($article_permalink_candidate) and $article_permalink_candidate !== '') {
            $event_permalink = $article_permalink_candidate;
        }
    }

    if ($event_permalink === '') {
        $article_id = (int) $get_event_value('article_id', 0);
        if ($article_id > 0) {
            $permalink_from_article = get_permalink($article_id);
            if (is_string($permalink_from_article) and $permalink_from_article !== '') {
                $event_permalink = $permalink_from_article;
            }
        }
    }

    if ($event_permalink === '' and $is_preview) {
        $event_permalink = home_url('/evenement/exemple');
    }

    if ($event_permalink !== '') {
        $event_permalink = esc_url_raw($event_permalink);
        $wrapper_classes[] = 'mj-event-schedule--has-cta';
    }
}

$wrapper_classes = array_values(array_filter(array_unique($wrapper_classes)));

$register_button_url = ($show_register_button and $event_permalink !== '') ? $event_permalink : '';

$schedule_data = array(
    'mode' => $schedule_mode,
    'schedule_summary' => $schedule_summary,
    'display_label' => $display_label,
    'date_debut' => $date_debut,
    'date_fin' => $date_fin,
    'occurrences' => $occurrences,
    'weekly_schedule' => $weekly_schedule,
);

if (!empty($next_occurrence)) {
    $schedule_data['next_occurrence'] = $next_occurrence;
}
if ($next_occurrence_label !== '') {
    $schedule_data['next_occurrence_label'] = $next_occurrence_label;
}

$extra_context = array(
    'title' => $title,
    'display_title' => $display_title,
    'empty_message' => $empty_message,
    'show_icons' => $show_icons,
    'highlight_today' => $highlight_today,
    'layout' => $active_layout,
    'entries' => $entries,
    'is_preview' => $is_preview,
    'has_event' => !empty($event_for_schedule),
    'show_next_occurrence_label' => $show_next_occurrence_label,
    'show_register_button' => $register_button_url !== '',
    'register_button' => array(
        'label' => $register_button_label,
        'url' => $register_button_url,
    ),
    'wrapper_classes' => $wrapper_classes,
    'strings' => array(
        'day' => __('Jour', 'mj-member'),
        'date' => __('Date', 'mj-member'),
        'time' => __('Heure', 'mj-member'),
        'start' => __('Début', 'mj-member'),
        'end' => __('Fin', 'mj-member'),
        'select_event' => __('Sélectionnez un événement dans les réglages du widget.', 'mj-member'),
    ),
);

if ($schedule_summary !== '') {
    $extra_context['summary'] = $schedule_summary;
}
if ($next_occurrence_label !== '') {
    $extra_context['next_occurrence_label'] = $next_occurrence_label;
}

$schedule_component = '';
if (class_exists(ScheduleDisplayHelper::class)) {
    $schedule_component = ScheduleDisplayHelper::render(
        $schedule_data,
        array(
            'variant' => 'event-schedule-widget',
            'extra_context' => $extra_context,
        )
    );
}

if ($schedule_component === '') {
    $schedule_component = sprintf(
        '<div class="%s"><p>%s</p></div>',
        esc_attr(implode(' ', $wrapper_classes)),
        esc_html($empty_message)
    );
}

echo $schedule_component;
?>
<style>
.mj-event-schedule {
    --mj-schedule-bg: transparent;
    --mj-schedule-item-bg: #f8f9fa;
    --mj-schedule-date-color: #333;
    --mj-schedule-time-color: #555;
    --mj-schedule-icon-color: #888;
    --mj-schedule-border-color: #e9ecef;
    --mj-schedule-past-opacity: 0.6;
    --mj-schedule-cta-bg: #2563eb;
    --mj-schedule-cta-color: #ffffff;
    --mj-schedule-cta-hover-bg: #1d4ed8;
    --mj-schedule-cta-shadow: rgba(37, 99, 235, 0.35);
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
    color: var(--mj-schedule-time-color);
}

.mj-event-schedule__time-text {
    font-weight: 500;
    color: var(--mj-schedule-time-color);
}

.mj-event-schedule__time-icon {
    display: inline-flex;
    color: var(--mj-schedule-icon-color);
}

.mj-event-schedule__time-separator {
    display: inline-flex;
    width: 1.75rem;
    height: 1px;
    margin: 0 0.5rem;
    background: var(--mj-schedule-border-color);
}

.mj-event-schedule__chips {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
}

.mj-event-schedule__chip {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0.85rem;
    border-radius: 999px;
    background: rgba(37, 99, 235, 0.1);
    color: #1d4ed8;
    font-weight: 600;
}

.mj-event-schedule__chip--today {
    background: rgba(16, 185, 129, 0.12);
    color: #0f766e;
}

.mj-event-schedule__chip-meta {
    font-size: 0.85rem;
    font-weight: 500;
}

.mj-event-schedule__chip-time {
    font-size: 0.85rem;
    font-weight: 500;
}

.mj-event-schedule__cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.mj-event-schedule__card {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    padding: 1rem;
    background: #ffffff;
    border-radius: 12px;
    border: 1px solid var(--mj-schedule-border-color);
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.mj-event-schedule__card:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 28px rgba(15, 23, 42, 0.1);
}

.mj-event-schedule__card--past {
    opacity: var(--mj-schedule-past-opacity);
}

.mj-event-schedule__card--today {
    border-color: rgba(37, 99, 235, 0.5);
    box-shadow: 0 12px 28px rgba(37, 99, 235, 0.15);
}

.mj-event-schedule__card-header {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.mj-event-schedule__timeline {
    position: relative;
    padding-left: 1.25rem;
    border-left: 2px solid rgba(148, 163, 184, 0.4);
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.mj-event-schedule__timeline-entry {
    position: relative;
    padding-left: 1rem;
}

.mj-event-schedule__timeline-entry--past {
    opacity: var(--mj-schedule-past-opacity);
}

.mj-event-schedule__timeline-entry--today {
    border-left: 3px solid #2563eb;
    margin-left: -1rem;
    padding-left: 2rem;
}

.mj-event-schedule__timeline-marker {
    position: absolute;
    left: -1.35rem;
    top: 0.35rem;
    width: 0.75rem;
    height: 0.75rem;
    border-radius: 999px;
    background: #2563eb;
    box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.2);
}

.mj-event-schedule__timeline-content {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    padding: 0.75rem 1rem;
    background: var(--mj-schedule-item-bg);
    border-radius: 10px;
    border: 1px solid var(--mj-schedule-border-color);
}

.mj-event-schedule__table-wrapper {
    overflow-x: auto;
}

.mj-event-schedule__table {
    width: 100%;
    border-collapse: collapse;
    background: #ffffff;
    border: 1px solid var(--mj-schedule-border-color);
    border-radius: 10px;
    overflow: hidden;
}

.mj-event-schedule__table th,
.mj-event-schedule__table td {
    padding: 0.75rem 1rem;
    text-align: left;
    border-bottom: 1px solid var(--mj-schedule-border-color);
}

.mj-event-schedule__table thead {
    background: rgba(37, 99, 235, 0.08);
    color: #1d4ed8;
}

.mj-event-schedule__table tbody tr:nth-child(even) {
    background: rgba(248, 249, 250, 0.6);
}

.mj-event-schedule__table tbody tr.is-past {
    opacity: var(--mj-schedule-past-opacity);
}

.mj-event-schedule__table tbody tr.is-today {
    background: rgba(16, 185, 129, 0.12);
}

.mj-event-schedule__cta {
    display: flex;
    justify-content: flex-start;
    margin-top: 1.25rem;
}

.mj-event-schedule__cta-button {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    background: var(--mj-schedule-cta-bg);
    color: var(--mj-schedule-cta-color);
    border-radius: 999px;
    font-weight: 600;
    text-decoration: none;
    box-shadow: 0 10px 30px var(--mj-schedule-cta-shadow);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.mj-event-schedule__cta-button:hover,
.mj-event-schedule__cta-button:focus {
    background: var(--mj-schedule-cta-hover-bg);
    transform: translateY(-1px);
    box-shadow: 0 14px 34px rgba(37, 99, 235, 0.35);
}

@media (max-width: 600px) {
    .mj-event-schedule__item,
    .mj-event-schedule__timeline-content,
    .mj-event-schedule__card {
        padding: 0.75rem 0.85rem;
    }

    .mj-event-schedule__timeline {
        border-left-width: 1px;
        padding-left: 1rem;
    }

    .mj-event-schedule__timeline-marker {
        left: -1.05rem;
    }

    .mj-event-schedule__table th,
    .mj-event-schedule__table td {
        padding: 0.6rem 0.75rem;
    }
}
</style>
