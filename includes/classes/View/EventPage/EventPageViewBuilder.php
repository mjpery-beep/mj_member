<?php

namespace Mj\Member\Classes\View\EventPage;

use DateTime;
use Mj\Member\Classes\View\Schedule\ScheduleDisplayHelper;
use Mj\Member\Core\TemplateEngine;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * EventPageViewBuilder - Construit les données de vue pour les templates Twig
 * 
 * Transforme le modèle métier en structures prêtes pour le rendu :
 * - page : attributs globaux de la page
 * - partials : données pour chaque section (hero, description, registration, etc.)
 * - assets : URLs et chemins pour ressources statiques
 */
final class EventPageViewBuilder
{
    /**
     * @var array<string, mixed>
     */
    private array $model;

    /**
     * @var array<string, mixed>
     */
    private array $context;

    /**
     * @param array<string, mixed> $model
     * @param array<string, mixed> $context
     */
    public function __construct(array $model, array $context = array())
    {
        $this->model = $model;
        $this->context = $context;
    }

    /**
     * @param array<string, mixed> $schedule
     */
    public static function renderInlineScheduleHtml(array $schedule): string
    {
        $builder = new self(array(), array());
        $weeklySchedule = $builder->resolveWeeklySchedule($schedule);
        $inlineSchedule = $builder->buildWeeklyScheduleFromOccurrences($schedule, false);
        $inlineScheduleDays = isset($inlineSchedule['days']) && is_array($inlineSchedule['days'])
            ? $inlineSchedule['days']
            : array();

        $isFromGenerator = !empty($weeklySchedule['from_generator']);
        $inlineScheduleCompact = $isFromGenerator
            ? $builder->buildInlineScheduleCompactLabel($schedule)
            : '';
        $inlineScheduleManual = !$isFromGenerator
            ? $builder->buildManualOccurrencesDisplay($schedule)
            : array();

        $scheduleComponent = '';
        if (!empty($schedule)) {
            $scheduleComponent = ScheduleDisplayHelper::render($schedule, array(
                'variant' => 'event-page',
            ));
        }

        try {
            return TemplateEngine::render('components/schedule/shared/event-page-inline.html.twig', array(
                'schedule_summary' => isset($schedule['schedule_summary']) ? (string) $schedule['schedule_summary'] : '',
                'display_label' => isset($schedule['display_label']) ? (string) $schedule['display_label'] : '',
                'weekly_schedule' => $weeklySchedule,
                'inline_days' => $inlineScheduleDays,
                'inline_schedule_compact' => $inlineScheduleCompact,
                'inline_schedule_manual' => $inlineScheduleManual,
                'next_occurrence' => isset($schedule['next_occurrence']) && is_array($schedule['next_occurrence'])
                    ? $schedule['next_occurrence']
                    : array(),
                'next_occurrence_label' => isset($schedule['next_occurrence_label']) ? (string) $schedule['next_occurrence_label'] : '',
                'schedule_component' => $scheduleComponent,
            ));
        } catch (\Throwable $throwable) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EventPageViewBuilder inline schedule render error: ' . $throwable->getMessage());
            }

            return '';
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        return array(
            'page' => $this->buildPage(),
            'partials' => $this->buildPartials(),
            'assets' => $this->buildAssets(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPage(): array
    {
        $event = isset($this->model['event']) && is_array($this->model['event'])
            ? $this->model['event']
            : array();

        $user = isset($this->model['user']) && is_array($this->model['user'])
            ? $this->model['user']
            : array();

        $accentColor = isset($event['accent_color']) ? (string) $event['accent_color'] : '';

        $cssVars = array();
        if ($accentColor !== '') {
            $cssVars['--mj-event-accent'] = $accentColor;
            $cssVars['--mj-event-contrast'] = $this->getContrastColor($accentColor);
        }

        $styleTokens = array();
        foreach ($cssVars as $var => $value) {
            $styleTokens[] = $var . ':' . esc_attr($value);
        }

        $classes = array('mj-event-page');
        if (!empty($event['type'])) {
            $classes[] = 'mj-event-page--type-' . sanitize_html_class($event['type']);
        }
        if (!empty($event['status'])) {
            $classes[] = 'mj-event-page--status-' . sanitize_html_class($event['status']);
        }

        $canEdit = !empty($user['is_animateur']);
        $eventId = isset($event['id']) ? (int) $event['id'] : 0;

        $editUrl = '';
        if ($canEdit && $eventId > 0) {
            $editUrl = add_query_arg(
                'event',
                $eventId,
                home_url('/mon-compte/gestionnaire/')
            );
        }

        return array(
            'class' => implode(' ', $classes),
            'style' => !empty($styleTokens) ? implode(';', $styleTokens) . ';' : '',
            'data_event_id' => isset($event['id']) ? (int) $event['id'] : 0,
            'title' => isset($event['title']) ? (string) $event['title'] : '',
            'can_edit' => $canEdit && $editUrl !== '',
            'edit_url' => $editUrl,
        );
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function buildPartials(): array
    {
        return array(
            'hero' => $this->buildHeroPartial(),
            'description' => $this->buildDescriptionPartial(),
            'registration' => $this->buildRegistrationPartial(),
            'location' => $this->buildLocationPartial(),
            'sidebar' => $this->buildSidebarPartial(),
            'animateurs' => $this->buildAnimateursPartial(),
            'photos' => $this->buildPhotosPartial(),
            'testimonials' => $this->buildTestimonialsPartial(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildHeroPartial(): array
    {
        $event = isset($this->model['event']) && is_array($this->model['event'])
            ? $this->model['event']
            : array();

        $schedule = isset($this->model['schedule']) && is_array($this->model['schedule'])
            ? $this->model['schedule']
            : array();

        $registration = isset($this->model['registration']) && is_array($this->model['registration'])
            ? $this->model['registration']
            : array();

        $scheduleComponent = '';
        if (!empty($schedule)) {
            $scheduleComponent = ScheduleDisplayHelper::render($schedule, array(
                'variant' => 'event-page',
            ));
        }

        $nextOccurrenceDetails = $this->buildNextOccurrenceDetails($schedule);
        $nextOccurrenceData = $nextOccurrenceDetails['data'];
        $nextOccurrenceLabel = $nextOccurrenceDetails['label'];

        $weeklySchedule = $this->resolveWeeklySchedule($schedule);
        $inlineScheduleHtml = self::renderInlineScheduleHtml($schedule);

        return array(
            'title' => isset($event['title']) ? (string) $event['title'] : '',
            'type_label' => isset($event['type_label']) ? (string) $event['type_label'] : '',
            'status_label' => isset($event['status_label']) ? (string) $event['status_label'] : '',
            'status' => isset($event['status']) ? (string) $event['status'] : '',
            'is_active' => ($event['status'] ?? '') === 'actif',
            'cover_url' => isset($event['cover_url']) ? (string) $event['cover_url'] : '',
            'cover_thumb' => isset($event['cover_thumb']) ? (string) $event['cover_thumb'] : '',
            'schedule_summary' => isset($schedule['schedule_summary']) ? (string) $schedule['schedule_summary'] : '',
            'display_label' => isset($schedule['display_label']) ? (string) $schedule['display_label'] : '',
            'weekly_schedule' => $weeklySchedule,
            'inline_schedule_html' => $inlineScheduleHtml,
            'schedule_component' => $scheduleComponent,
            'next_occurrence' => $nextOccurrenceData,
            'next_occurrence_label' => $nextOccurrenceLabel,
            'price_label' => isset($registration['price_display']) ? (string) $registration['price_display'] : '',
        );
    }

    /**
     * @param array<string, mixed> $schedule
     * @return array<string, mixed>
     */
    private function resolveWeeklySchedule(array $schedule): array
    {
        $default = array(
            'is_weekly' => false,
            'is_monthly' => false,
            'is_series' => false,
            'from_generator' => false,
            'show_date_range' => false,
            'days' => array(),
            'series_items' => array(),
        );

        $weeklySchedule = isset($schedule['weekly_schedule']) && is_array($schedule['weekly_schedule'])
            ? array_merge($default, $schedule['weekly_schedule'])
            : $default;

        if (!empty($weeklySchedule['days'])) {
            return $weeklySchedule;
        }

        $fallback = $this->buildWeeklyScheduleFromOccurrences($schedule, (bool) $weeklySchedule['show_date_range']);
        if (!empty($fallback['days'])) {
            return array_merge($weeklySchedule, $fallback, array(
                'from_generator' => !empty($weeklySchedule['from_generator']),
            ));
        }

        return $weeklySchedule;
    }

    /**
     * @param array<string, mixed> $schedule
     * @return array<string, mixed>
     */
    private function buildWeeklyScheduleFromOccurrences(array $schedule, bool $showDateRange): array
    {
        $occurrences = isset($schedule['occurrences']) && is_array($schedule['occurrences'])
            ? $schedule['occurrences']
            : array();

        if (empty($occurrences)) {
            return array(
                'is_weekly' => false,
                'is_monthly' => false,
                'is_series' => false,
                'show_date_range' => $showDateRange,
                'days' => array(),
                'series_items' => array(),
            );
        }

        $timezone = wp_timezone();
        if (!($timezone instanceof \DateTimeZone)) {
            $timezone = new \DateTimeZone('UTC');
        }

        $weekdayLabels = array(
            1 => __('Lundi', 'mj-member'),
            2 => __('Mardi', 'mj-member'),
            3 => __('Mercredi', 'mj-member'),
            4 => __('Jeudi', 'mj-member'),
            5 => __('Vendredi', 'mj-member'),
            6 => __('Samedi', 'mj-member'),
            7 => __('Dimanche', 'mj-member'),
        );

        // Group occurrences by date (not weekday) to capture all occurrences on the same day
        $daysByDate = array();

        foreach ($occurrences as $occurrence) {
            if (!is_array($occurrence)) {
                continue;
            }

            $timestamp = isset($occurrence['timestamp']) ? (int) $occurrence['timestamp'] : 0;
            if ($timestamp <= 0 && !empty($occurrence['start'])) {
                $parsed = $this->parseOccurrenceTimestamp((string) $occurrence['start']);
                if ($parsed !== null) {
                    $timestamp = $parsed;
                }
            }

            if ($timestamp <= 0) {
                continue;
            }

            $weekdayIndex = (int) wp_date('N', $timestamp, $timezone);
            if ($weekdayIndex < 1 || $weekdayIndex > 7) {
                continue;
            }

            // Use date key (YYYY-MM-DD) instead of weekday to group all occurrences of the same day
            $dateKey = wp_date('Y-m-d', $timestamp, $timezone);

            // Initialize date group if not exists
            if (!isset($daysByDate[$dateKey])) {
                $daysByDate[$dateKey] = array(
                    'timestamp' => $timestamp,
                    'weekdayIndex' => $weekdayIndex,
                    'key' => $this->weekdayKeyFromIndex($weekdayIndex),
                    'label' => $weekdayLabels[$weekdayIndex] ?? '',
                    'time_ranges' => array(),
                );
            }

            // Collect all time ranges for this date
            $startTimestamp = $timestamp;
            $endTimestamp = 0;

            if (!empty($occurrence['end'])) {
                $parsedEnd = $this->parseOccurrenceTimestamp((string) $occurrence['end']);
                if ($parsedEnd !== null) {
                    $endTimestamp = $parsedEnd;
                }
            }

            $startLabel = $startTimestamp > 0
                ? $this->formatTimeFromTimestamp($startTimestamp, $timezone)
                : '';
            $endLabel = $endTimestamp > 0
                ? $this->formatTimeFromTimestamp($endTimestamp, $timezone)
                : '';
            $startRaw = $startTimestamp > 0 ? wp_date('H:i', $startTimestamp, $timezone) : '';
            $endRaw = $endTimestamp > 0 ? wp_date('H:i', $endTimestamp, $timezone) : '';

            $timeRange = '';
            if ($this->isAllDayTimeRange($startRaw, $endRaw, $startTimestamp, $endTimestamp)) {
                $timeRange = __('Toute la journée', 'mj-member');
            } elseif ($startLabel !== '' && $endLabel !== '' && $endLabel !== $startLabel) {
                $timeRange = $startLabel . ' - ' . $endLabel;
            } elseif ($startLabel !== '') {
                $timeRange = $startLabel;
            } elseif ($endLabel !== '') {
                $timeRange = $endLabel;
            }

            if ($timeRange !== '') {
                $daysByDate[$dateKey]['time_ranges'][] = $timeRange;
            }

            // Store first occurrence data for backward compatibility
            if (empty($daysByDate[$dateKey]['start_time'])) {
                $daysByDate[$dateKey]['start_time'] = $startTimestamp > 0 ? wp_date('H:i', $startTimestamp, $timezone) : '';
                $daysByDate[$dateKey]['end_time'] = $endTimestamp > 0 ? wp_date('H:i', $endTimestamp, $timezone) : '';
                $daysByDate[$dateKey]['start_formatted'] = $startLabel;
                $daysByDate[$dateKey]['end_formatted'] = $endLabel;
            }
        }

        if (empty($daysByDate)) {
            return array(
                'is_weekly' => false,
                'is_monthly' => false,
                'is_series' => false,
                'show_date_range' => $showDateRange,
                'days' => array(),
                'series_items' => array(),
            );
        }

        // Sort by timestamp and build final days array
        uasort($daysByDate, function ($a, $b) {
            return ($a['timestamp'] ?? 0) <=> ($b['timestamp'] ?? 0);
        });

        $finalDays = array();
        foreach ($daysByDate as $dateKey => $dateData) {
            $timeRange = !empty($dateData['time_ranges'])
                ? implode(' + ', array_unique($dateData['time_ranges']))
                : '';

            $timestamp = $dateData['timestamp'] ?? 0;
            $dayNum = $timestamp > 0 ? wp_date('d', $timestamp, $timezone) : '';
            $monthName = $timestamp > 0 ? wp_date('M', $timestamp, $timezone) : '';

            $finalDays[] = array(
                'key' => $dateData['key'],
                'label' => $dateData['label'],
                'day_num' => $dayNum,
                'month_name' => $monthName,
                'start_time' => $dateData['start_time'] ?? '',
                'end_time' => $dateData['end_time'] ?? '',
                'start_formatted' => $dateData['start_formatted'] ?? '',
                'end_formatted' => $dateData['end_formatted'] ?? '',
                'time_range' => $timeRange,
            );
        }

        return array(
            'is_weekly' => true,
            'is_monthly' => false,
            'is_series' => false,
            'show_date_range' => $showDateRange,
            'days' => $finalDays,
            'series_items' => array(),
        );
    }

    private function weekdayKeyFromIndex(int $index): string
    {
        $map = array(
            1 => 'monday',
            2 => 'tuesday',
            3 => 'wednesday',
            4 => 'thursday',
            5 => 'friday',
            6 => 'saturday',
            7 => 'sunday',
        );

        return $map[$index] ?? 'monday';
    }

    /**
     * Build manual occurrences display for non-generator events
     * Returns array of items with format: "Samedi 18 Avril ⌚ 10h - 16h"
     *
     * @param array<string, mixed> $schedule
     * @return array<int, array<string, string>>
     */
    private function buildManualOccurrencesDisplay(array $schedule): array
    {
        $occurrences = isset($schedule['occurrences']) && is_array($schedule['occurrences'])
            ? $schedule['occurrences']
            : array();

        if (empty($occurrences)) {
            return array();
        }

        $timezone = wp_timezone();
        if (!($timezone instanceof \DateTimeZone)) {
            $timezone = new \DateTimeZone('UTC');
        }

        $weekdayLabels = array(
            1 => __('Lundi', 'mj-member'),
            2 => __('Mardi', 'mj-member'),
            3 => __('Mercredi', 'mj-member'),
            4 => __('Jeudi', 'mj-member'),
            5 => __('Vendredi', 'mj-member'),
            6 => __('Samedi', 'mj-member'),
            7 => __('Dimanche', 'mj-member'),
        );

        $monthLabels = array(
            1 => __('Janvier', 'mj-member'),
            2 => __('Février', 'mj-member'),
            3 => __('Mars', 'mj-member'),
            4 => __('Avril', 'mj-member'),
            5 => __('Mai', 'mj-member'),
            6 => __('Juin', 'mj-member'),
            7 => __('Juillet', 'mj-member'),
            8 => __('Août', 'mj-member'),
            9 => __('Septembre', 'mj-member'),
            10 => __('Octobre', 'mj-member'),
            11 => __('Novembre', 'mj-member'),
            12 => __('Décembre', 'mj-member'),
        );

        $items = array();
        $seenDates = array();

        foreach ($occurrences as $occurrence) {
            if (!is_array($occurrence)) {
                continue;
            }

            $timestamp = isset($occurrence['timestamp']) ? (int) $occurrence['timestamp'] : 0;
            if ($timestamp <= 0 && !empty($occurrence['start'])) {
                $parsed = $this->parseOccurrenceTimestamp((string) $occurrence['start']);
                if ($parsed !== null) {
                    $timestamp = $parsed;
                }
            }

            if ($timestamp <= 0) {
                continue;
            }

            // Skip duplicate dates
            $dateKey = wp_date('Y-m-d', $timestamp, $timezone);
            if (isset($seenDates[$dateKey])) {
                continue;
            }
            $seenDates[$dateKey] = true;

            $weekdayIndex = (int) wp_date('N', $timestamp, $timezone);
            if ($weekdayIndex < 1 || $weekdayIndex > 7) {
                continue;
            }

            $weekdayLabel = $weekdayLabels[$weekdayIndex] ?? '';
            $dayOfMonth = (int) wp_date('j', $timestamp, $timezone);
            $monthIndex = (int) wp_date('n', $timestamp, $timezone);
            $monthLabel = $monthLabels[$monthIndex] ?? '';

            // Parse times
            $endTimestamp = 0;
            if (!empty($occurrence['end'])) {
                $parsedEnd = $this->parseOccurrenceTimestamp((string) $occurrence['end']);
                if ($parsedEnd !== null) {
                    $endTimestamp = $parsedEnd;
                }
            }

            $startRaw = wp_date('H:i', $timestamp, $timezone);
            $endRaw = $endTimestamp > 0 ? wp_date('H:i', $endTimestamp, $timezone) : '';

            // Check if all-day
            if ($this->isAllDayTimeRange($startRaw, $endRaw, $timestamp, $endTimestamp)) {
                $timeRange = __('Toute la journée', 'mj-member');
                $separator = ' : ';
            } else {
                // Format times
                $startLabel = $this->formatTimeFromTimestamp($timestamp, $timezone);
                $endLabel = $endTimestamp > 0 ? $this->formatTimeFromTimestamp($endTimestamp, $timezone) : '';

                if ($startLabel !== '' && $endLabel !== '' && $endLabel !== $startLabel) {
                    $timeRange = $startLabel . ' - ' . $endLabel;
                } elseif ($startLabel !== '') {
                    $timeRange = $startLabel;
                } elseif ($endLabel !== '') {
                    $timeRange = $endLabel;
                } else {
                    $timeRange = '';
                }
                $separator = ' ⌚ ';
            }

            // Build display
            $dateLabel = sprintf('%s %d %s', $weekdayLabel, $dayOfMonth, $monthLabel);

            if ($timeRange !== '') {
                $displayText = $dateLabel . $separator . $timeRange;
            } else {
                $displayText = $dateLabel;
            }

            $items[] = array(
                'date' => $dateLabel,
                'time_range' => $timeRange,
                'display' => $displayText,
            );
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $schedule
     */
    private function buildInlineScheduleCompactLabel(array $schedule): string
    {
        $occurrences = isset($schedule['occurrences']) && is_array($schedule['occurrences'])
            ? $schedule['occurrences']
            : array();

        if (count($occurrences) < 2) {
            return '';
        }

        $timezone = wp_timezone();
        if (!($timezone instanceof \DateTimeZone)) {
            $timezone = new \DateTimeZone('UTC');
        }

        $weekdayLabels = array(
            1 => __('Lundi', 'mj-member'),
            2 => __('Mardi', 'mj-member'),
            3 => __('Mercredi', 'mj-member'),
            4 => __('Jeudi', 'mj-member'),
            5 => __('Vendredi', 'mj-member'),
            6 => __('Samedi', 'mj-member'),
            7 => __('Dimanche', 'mj-member'),
        );

        $timeRangesByWeekday = array();

        foreach ($occurrences as $occurrence) {
            if (!is_array($occurrence)) {
                continue;
            }

            $timestamp = isset($occurrence['timestamp']) ? (int) $occurrence['timestamp'] : 0;
            if ($timestamp <= 0 && !empty($occurrence['start'])) {
                $parsed = $this->parseOccurrenceTimestamp((string) $occurrence['start']);
                if ($parsed !== null) {
                    $timestamp = $parsed;
                }
            }

            if ($timestamp <= 0) {
                continue;
            }

            $weekdayIndex = (int) wp_date('N', $timestamp, $timezone);
            if ($weekdayIndex < 1 || $weekdayIndex > 7) {
                continue;
            }

            $startTimestamp = $timestamp;
            $endTimestamp = 0;
            if (!empty($occurrence['end'])) {
                $parsedEnd = $this->parseOccurrenceTimestamp((string) $occurrence['end']);
                if ($parsedEnd !== null) {
                    $endTimestamp = $parsedEnd;
                }
            }

            $startLabel = $startTimestamp > 0 ? $this->formatTimeFromTimestamp($startTimestamp, $timezone) : '';
            $endLabel = $endTimestamp > 0 ? $this->formatTimeFromTimestamp($endTimestamp, $timezone) : '';
            $startRaw = $startTimestamp > 0 ? wp_date('H:i', $startTimestamp, $timezone) : '';
            $endRaw = $endTimestamp > 0 ? wp_date('H:i', $endTimestamp, $timezone) : '';

            $timeRange = '';
            if ($this->isAllDayTimeRange($startRaw, $endRaw, $startTimestamp, $endTimestamp)) {
                $timeRange = __('Toute la journée', 'mj-member');
            } elseif ($startLabel !== '' && $endLabel !== '' && $endLabel !== $startLabel) {
                $timeRange = $startLabel . ' - ' . $endLabel;
            } elseif ($startLabel !== '') {
                $timeRange = $startLabel;
            } elseif ($endLabel !== '') {
                $timeRange = $endLabel;
            }

            if (!isset($timeRangesByWeekday[$weekdayIndex])) {
                $timeRangesByWeekday[$weekdayIndex] = array();
            }

            if ($timeRange !== '' && !in_array($timeRange, $timeRangesByWeekday[$weekdayIndex], true)) {
                $timeRangesByWeekday[$weekdayIndex][] = $timeRange;
            }
        }

        if (count($timeRangesByWeekday) < 2) {
            return '';
        }

        ksort($timeRangesByWeekday);

        $weekdayNames = array();
        $weekdaySignatures = array();
        foreach ($timeRangesByWeekday as $weekdayIndex => $ranges) {
            $weekdayLabel = $weekdayLabels[$weekdayIndex] ?? '';
            if ($weekdayLabel === '') {
                continue;
            }

            $weekdayNames[] = $weekdayLabel;
            $normalizedRanges = array_values(array_filter(array_map('strval', $ranges), static function ($value) {
                return trim($value) !== '';
            }));
            $signature = implode(' / ', array_unique($normalizedRanges));
            $weekdaySignatures[$weekdayIndex] = $signature;
        }

        if (empty($weekdayNames)) {
            return '';
        }

        // If all weekdays share exactly the same time range, keep the short sentence.
        $uniqueSignatures = array_values(array_unique(array_values($weekdaySignatures)));
        if (count($uniqueSignatures) === 1 && trim((string) $uniqueSignatures[0]) !== '') {
            $styledNames = array_map(static function (string $n) { return '<span class="mj-event-page__schedule-inline-day">' . esc_html($n) . '</span>'; }, $weekdayNames);
            $weekdaysLabel = $this->formatNaturalList($styledNames);
            $timeLabel = $this->normalizeTimeRangeForSentence((string) $uniqueSignatures[0]);
            return sprintf(__('Tous les %1$s ⌚ %2$s', 'mj-member'), $weekdaysLabel, esc_html($timeLabel));
        }

        // Otherwise, group weekdays by time range and build a sentence per group.
        $weekdaysBySignature = array();
        foreach ($weekdaySignatures as $weekdayIndex => $signature) {
            $key = trim((string) $signature);
            if (!isset($weekdaysBySignature[$key])) {
                $weekdaysBySignature[$key] = array();
            }
            $weekdaysBySignature[$key][] = $weekdayLabels[$weekdayIndex] ?? '';
        }

        $clauses = array();
        foreach ($weekdaysBySignature as $signature => $names) {
            $names = array_values(array_filter($names, static function ($value) {
                return is_string($value) && trim($value) !== '';
            }));
            if (empty($names)) {
                continue;
            }
            $timeLabel = $this->normalizeTimeRangeForSentence((string) $signature);
            $clauses[] = $this->buildCompactWeekdayClause($names, $timeLabel);
        }

        if (empty($clauses)) {
            $styledNames = array_map(static function (string $n) { return '<span class="mj-event-page__schedule-inline-day">' . esc_html($n) . '</span>'; }, $weekdayNames);
            return sprintf(__('Tous les %s', 'mj-member'), $this->formatNaturalList($styledNames));
        }

        $first = (string) array_shift($clauses);
        if (str_starts_with($first, 'les ')) {
            $sentence = 'Tous ' . $first;
        } elseif (str_starts_with($first, 'le ')) {
            $sentence = ucfirst($first);
        } else {
            $sentence = $first;
        }

        if (!empty($clauses)) {
            $sentence .= ' et ' . implode(' et ', $clauses);
        }

        return $sentence;
    }

    /**
     * @param array<int, string> $weekdayNames
     */
    private function buildCompactWeekdayClause(array $weekdayNames, string $timeLabel): string
    {
        $styledNames = array_map(static function (string $n) { return '<span class="mj-event-page__schedule-inline-day">' . esc_html($n) . '</span>'; }, $weekdayNames);
        $namesLabel = $this->formatNaturalList($styledNames);
        if ($namesLabel === '') {
            return '';
        }

        $prefix = count($weekdayNames) > 1 ? 'les ' : 'le ';
        if ($timeLabel === '') {
            return $prefix . $namesLabel;
        }

        return sprintf('%1$s%2$s ⌚ %3$s', $prefix, $namesLabel, esc_html($timeLabel));
    }

    private function normalizeTimeRangeForSentence(string $timeRange): string
    {
        $value = trim($timeRange);
        if ($value === '') {
            return '';
        }
        return str_replace(' - ', ' a ', $value);
    }

    /**
     * @param array<int, string> $items
     */
    private function formatNaturalList(array $items): string
    {
        $values = array_values(array_filter($items, static function ($item) {
            return is_string($item) && trim($item) !== '';
        }));

        $count = count($values);
        if ($count === 0) {
            return '';
        }
        if ($count === 1) {
            return (string) $values[0];
        }
        if ($count === 2) {
            return (string) $values[0] . ' et ' . (string) $values[1];
        }

        $last = (string) array_pop($values);
        return implode(', ', $values) . ' et ' . $last;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDescriptionPartial(): array
    {
        $event = isset($this->model['event']) && is_array($this->model['event'])
            ? $this->model['event']
            : array();

        $description = isset($event['description']) ? (string) $event['description'] : '';
        $descriptionHtml = $description !== '' ? wpautop($description) : '';

        return array(
            'content' => $description,
            'content_html' => $descriptionHtml,
            'has_article' => !empty($event['has_article']),
            'article_url' => isset($event['article_url']) ? (string) $event['article_url'] : '',
            'article_title' => isset($event['article_title']) ? (string) $event['article_title'] : '',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRegistrationPartial(): array
    {
        $registration = isset($this->model['registration']) && is_array($this->model['registration'])
            ? $this->model['registration']
            : array();

        $user = isset($this->model['user']) && is_array($this->model['user'])
            ? $this->model['user']
            : array();

        $event = isset($this->model['event']) && is_array($this->model['event'])
            ? $this->model['event']
            : array();

        $schedule = isset($this->model['schedule']) && is_array($this->model['schedule'])
            ? $this->model['schedule']
            : array();

        $mode = isset($schedule['mode']) ? (string) $schedule['mode'] : '';
        $occurrences = isset($schedule['occurrences']) && is_array($schedule['occurrences'])
            ? $schedule['occurrences']
            : array();

        $hasFutureOccurrence = false;
        $now = current_time('timestamp');

        foreach ($occurrences as $occurrence) {
            $timestamp = isset($occurrence['timestamp']) ? (int) $occurrence['timestamp'] : 0;
            if ($timestamp >= $now) {
                $hasFutureOccurrence = true;
                break;
            }
        }

        $isFixedPast = $mode === 'fixed' && !$hasFutureOccurrence && !empty($occurrences);

        $isOpen = !empty($registration['is_open']) && !$isFixedPast;
        $isFreeParticipation = !empty($registration['is_free_participation']);
        $requiresLogin = !empty($registration['requires_login']);
        $hasReservations = !empty($registration['has_reservations']);

        // Déterminer le CTA principal
        $ctaLabel = __("S'inscrire", 'mj-member');
        $ctaAction = 'register';

        if ($hasReservations) {
            $ctaLabel = __('Mettre à jour mon inscription', 'mj-member');
            $ctaAction = 'update';
        }

        // Configuration JSON pour le composant Preact
        $configJson = wp_json_encode(array(
            'eventId' => isset($event['id']) ? (int) $event['id'] : 0,
            'isOpen' => $isOpen,
            'isFreeParticipation' => $isFreeParticipation,
            'requiresValidation' => !empty($registration['requires_validation']),
            'paymentRequired' => !empty($registration['payment_required']),
            'price' => isset($registration['price']) ? (float) $registration['price'] : 0,
            'priceDisplay' => isset($registration['price_display']) ? (string) $registration['price_display'] : '',
            'allowsSelection' => !empty($schedule['allows_selection']),
            'hasMultipleOccurrences' => !empty($schedule['has_multiple_occurrences']),
            'participants' => isset($registration['participants']) ? $registration['participants'] : array(),
            'userReservations' => isset($registration['user_reservations']) ? $registration['user_reservations'] : array(),
        ));

        return array(
            'is_open' => $isOpen,
            'is_free_participation' => $isFreeParticipation,
            'free_participation_message' => __('Participation libre : aucune inscription n\'est requise.', 'mj-member'),
            'requires_login' => $requiresLogin,
            'requires_validation' => !empty($registration['requires_validation']),
            'payment_required' => !empty($registration['payment_required']),
            'price_display' => isset($registration['price_display']) ? (string) $registration['price_display'] : '',
            'capacity_total' => isset($registration['capacity_total']) ? (int) $registration['capacity_total'] : 0,
            'capacity_remaining' => isset($registration['capacity_remaining']) ? $registration['capacity_remaining'] : null,
            'has_reservations' => $hasReservations,
            'reservations' => isset($registration['user_reservations']) ? $registration['user_reservations'] : array(),
            'participants' => isset($registration['participants']) ? $registration['participants'] : array(),
            'cta_label' => $ctaLabel,
            'cta_action' => $ctaAction,
            'config_json' => $configJson,
            'allows_selection' => !empty($schedule['allows_selection']),
            'has_multiple_occurrences' => !empty($schedule['has_multiple_occurrences']),
            'is_past' => $isFixedPast,
            'user' => array(
                'is_logged_in' => !empty($user['is_logged_in']),
                'can_register' => !empty($user['can_register']),
            ),
            'signup_url' => function_exists('mj_member_login_component_get_registration_url') 
                ? mj_member_login_component_get_registration_url() 
                : home_url('/inscription'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildLocationPartial(): array
    {
        $location = isset($this->model['location']) && is_array($this->model['location'])
            ? $this->model['location']
            : array();

        $locationLinks = isset($location['location_links']) && is_array($location['location_links'])
            ? $location['location_links']
            : array();

        return array(
            'has_location' => !empty($location['has_location']),
            'name' => isset($location['name']) ? (string) $location['name'] : '',
            'address' => isset($location['address']) ? (string) $location['address'] : '',
            'cover_url' => isset($location['cover_url']) ? (string) $location['cover_url'] : '',
            'notes' => isset($location['notes']) ? (string) $location['notes'] : '',
            'map_embed' => isset($location['map_embed']) ? (string) $location['map_embed'] : '',
            'map_link' => isset($location['map_link']) ? (string) $location['map_link'] : '',
            'location_links' => $locationLinks,
            'has_multiple_locations' => count($locationLinks) > 0,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSidebarPartial(): array
    {
        $registration = isset($this->model['registration']) && is_array($this->model['registration'])
            ? $this->model['registration']
            : array();

        $schedule = isset($this->model['schedule']) && is_array($this->model['schedule'])
            ? $this->model['schedule']
            : array();

        $meta = isset($this->model['meta']) && is_array($this->model['meta'])
            ? $this->model['meta']
            : array();

        $nextOccurrenceDetails = $this->buildNextOccurrenceDetails($schedule);
        $nextOccurrenceData = $nextOccurrenceDetails['data'];
        $nextOccurrenceLabel = $nextOccurrenceDetails['label'];

        return array(
            'deadline_label' => isset($registration['deadline_label']) ? (string) $registration['deadline_label'] : '',
            'price_label' => isset($registration['price_display']) ? (string) $registration['price_display'] : '',
            'age_label' => isset($meta['age_label']) ? (string) $meta['age_label'] : '',
            'capacity_total' => isset($registration['capacity_total']) ? (int) $registration['capacity_total'] : 0,
            'capacity_remaining' => isset($registration['capacity_remaining']) ? $registration['capacity_remaining'] : null,
            'schedule_summary' => isset($schedule['schedule_summary']) ? (string) $schedule['schedule_summary'] : '',
            'display_label' => isset($schedule['display_label']) ? (string) $schedule['display_label'] : '',
            'next_occurrence' => $nextOccurrenceData,
            'next_occurrence_label' => $nextOccurrenceLabel,
            'has_multiple_occurrences' => !empty($schedule['has_multiple_occurrences']),
            'occurrences' => isset($schedule['occurrences']) ? $schedule['occurrences'] : array(),
        );
    }

    /**
     * Prépare les données d'affichage pour la prochaine occurrence.
     *
     * @param array<string, mixed> $schedule
     * @return array{data: array<string, string>, label: string}
     */
    private function buildNextOccurrenceDetails(array $schedule): array
    {
        $data = array(
            'label' => '',
            'day_name' => '',
            'day_num' => '',
            'month_name' => '',
            'year' => '',
            'time_start' => '',
            'time_end' => '',
            'full_date' => '',
            'is_all_day' => false,
            'all_day_label' => __('Toute la journée', 'mj-member'),
        );

        $label = '';

        $nextOccurrence = isset($schedule['next_occurrence']) && is_array($schedule['next_occurrence'])
            ? $schedule['next_occurrence']
            : null;

        if ($nextOccurrence === null) {
            return array(
                'data' => $data,
                'label' => $label,
            );
        }

        $timezone = wp_timezone();
        if (!($timezone instanceof \DateTimeZone)) {
            $timezone = new \DateTimeZone('UTC');
        }

        $data['label'] = isset($nextOccurrence['label']) ? (string) $nextOccurrence['label'] : '';
        $data['day_name'] = isset($nextOccurrence['day_name']) ? (string) $nextOccurrence['day_name'] : '';
        $data['day_num'] = isset($nextOccurrence['day_num']) ? (string) $nextOccurrence['day_num'] : '';
        $data['month_name'] = isset($nextOccurrence['month_name']) ? (string) $nextOccurrence['month_name'] : '';
        $data['full_date'] = isset($nextOccurrence['full_date']) ? (string) $nextOccurrence['full_date'] : '';

        $timestamp = isset($nextOccurrence['timestamp']) ? (int) $nextOccurrence['timestamp'] : 0;
        if ($timestamp > 0) {
            $data['year'] = wp_date('Y', $timestamp, $timezone);
            if ($data['full_date'] === '') {
                $data['full_date'] = wp_date('l j F Y', $timestamp, $timezone);
            }
            if ($data['day_name'] === '') {
                $data['day_name'] = wp_date('D', $timestamp, $timezone);
            }
            if ($data['day_num'] === '') {
                $data['day_num'] = wp_date('d', $timestamp, $timezone);
            }
            if ($data['month_name'] === '') {
                $data['month_name'] = wp_date('M', $timestamp, $timezone);
            }
        }

        $occurrenceStartTimestamp = $timestamp;

        if (!empty($nextOccurrence['start']) && $occurrenceStartTimestamp <= 0) {
            $parsedStart = $this->parseOccurrenceTimestamp((string) $nextOccurrence['start']);
            if ($parsedStart !== null) {
                $occurrenceStartTimestamp = $parsedStart;
            }
        }

        if ($occurrenceStartTimestamp > 0) {
            $data['time_start'] = $this->formatTimeFromTimestamp($occurrenceStartTimestamp, $timezone);
        }

        if (!empty($nextOccurrence['end'])) {
            $parsedEnd = $this->parseOccurrenceTimestamp((string) $nextOccurrence['end']);
            if ($parsedEnd !== null) {
                $data['time_end'] = $this->formatTimeFromTimestamp($parsedEnd, $timezone);
            }
        }

        $rawStartTime = $this->extractTimePart(isset($nextOccurrence['start']) ? (string) $nextOccurrence['start'] : '');
        $rawEndTime = $this->extractTimePart(isset($nextOccurrence['end']) ? (string) $nextOccurrence['end'] : '');
        $explicitAllDay = false;
        if (array_key_exists('is_all_day', $nextOccurrence)) {
            $explicitAllDay = filter_var($nextOccurrence['is_all_day'], FILTER_VALIDATE_BOOLEAN);
        } elseif (array_key_exists('isAllDay', $nextOccurrence)) {
            $explicitAllDay = filter_var($nextOccurrence['isAllDay'], FILTER_VALIDATE_BOOLEAN);
        }
        $startTimestampForAllDay = null;
        $endTimestampForAllDay = null;
        if (!empty($nextOccurrence['start'])) {
            $parsed = $this->parseOccurrenceTimestamp((string) $nextOccurrence['start']);
            if ($parsed !== null) {
                $startTimestampForAllDay = $parsed;
            }
        }
        if (!empty($nextOccurrence['end'])) {
            $parsed = $this->parseOccurrenceTimestamp((string) $nextOccurrence['end']);
            if ($parsed !== null) {
                $endTimestampForAllDay = $parsed;
            }
        }
        $data['is_all_day'] = $explicitAllDay || $this->isAllDayTimeRange($rawStartTime, $rawEndTime, $startTimestampForAllDay, $endTimestampForAllDay);
        if ($data['is_all_day']) {
            $data['time_start'] = '';
            $data['time_end'] = '';
        }

        $label = $data['label'];
        if ($label === '') {
            $labelParts = array();
            if ($data['full_date'] !== '') {
                $labelParts[] = $data['full_date'];
            }

            $timeRange = '';
            if (!empty($data['is_all_day'])) {
                $timeRange = $data['all_day_label'];
            } elseif ($data['time_start'] !== '') {
                $timeRange = $data['time_start'];
                if ($data['time_end'] !== '' && $data['time_end'] !== $data['time_start']) {
                    $timeRange .= ' - ' . $data['time_end'];
                }
            }

            if ($timeRange !== '') {
                $labelParts[] = $timeRange;
            }

            if (!empty($labelParts)) {
                $label = implode(' - ', $labelParts);
            }
        }

        if (class_exists(ScheduleDisplayHelper::class)) {
            $mode = isset($schedule['mode']) ? (string) $schedule['mode'] : 'fixed';
            $helperLabel = ScheduleDisplayHelper::buildCalendarLabel(
                array('schedule_mode' => $mode),
                isset($schedule['occurrences']) && is_array($schedule['occurrences']) ? $schedule['occurrences'] : array(),
                array(
                    'now' => current_time('timestamp'),
                    'timezone' => $timezone,
                    'fallback_label' => $label,
                    'variant' => 'event-page',
                )
            );
            if (is_string($helperLabel) && trim($helperLabel) !== '') {
                $label = trim($helperLabel);
            }
        }

        return array(
            'data' => $data,
            'label' => $label,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAnimateursPartial(): array
    {
        $animateurs = isset($this->model['animateurs']) && is_array($this->model['animateurs'])
            ? $this->model['animateurs']
            : array();

        return array(
            'count' => isset($animateurs['count']) ? (int) $animateurs['count'] : 0,
            'items' => isset($animateurs['items']) && is_array($animateurs['items'])
                ? $animateurs['items']
                : array(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPhotosPartial(): array
    {
        $photos = isset($this->model['photos']) && is_array($this->model['photos'])
            ? $this->model['photos']
            : array();

        return array(
            'has_photos' => !empty($photos['has_photos']),
            'can_upload' => !empty($photos['can_upload']),
            'items' => isset($photos['items']) && is_array($photos['items'])
                ? $photos['items']
                : array(),
            'total' => isset($photos['total']) ? (int) $photos['total'] : 0,
            'pending_count' => isset($photos['pending_count']) ? (int) $photos['pending_count'] : 0,
            'upload_nonce' => isset($photos['upload_nonce']) ? (string) $photos['upload_nonce'] : '',
            'event_id' => isset($photos['event_id']) ? (int) $photos['event_id'] : 0,
            'admin_post_url' => isset($photos['admin_post_url']) ? (string) $photos['admin_post_url'] : '',
            'member_remaining' => isset($photos['member_remaining']) ? (int) $photos['member_remaining'] : 0,
            'is_unlimited' => !empty($photos['is_unlimited']),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildTestimonialsPartial(): array
    {
        $testimonials = isset($this->model['testimonials']) && is_array($this->model['testimonials'])
            ? $this->model['testimonials']
            : array();

        $items = isset($testimonials['items']) && is_array($testimonials['items'])
            ? $testimonials['items']
            : array();

        $total = isset($testimonials['total']) ? (int) $testimonials['total'] : 0;

        $allUrl = home_url('/mon-compte/temoignages/');

        // Form data for testimonial submission
        $user = isset($this->model['user']) && is_array($this->model['user'])
            ? $this->model['user']
            : array();

        $event = isset($this->model['event']) && is_array($this->model['event'])
            ? $this->model['event']
            : array();

        $isLoggedIn = !empty($user['is_logged_in']);
        $eventSlug = isset($event['slug']) ? (string) $event['slug'] : '';
        $eventTitle = isset($event['title']) ? (string) $event['title'] : '';

        return array(
            'items' => $items,
            'total' => $total,
            'all_url' => $allUrl,
            'allow_submission' => true,
            'is_logged_in' => $isLoggedIn,
            'event_slug' => $eventSlug,
            'event_title' => $eventTitle,
            'max_photos' => 5,
            'allow_video' => true,
        );
    }

    /**
     * Convertit une chaîne datetime en timestamp en respectant le fuseau WordPress
     *
     * @param string $value
     * @return int|null
     */
    private function parseOccurrenceTimestamp(string $value): ?int
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $timezone = wp_timezone();
        if (!($timezone instanceof \DateTimeZone)) {
            $timezone = new \DateTimeZone('UTC');
        }
        $formats = array('Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d');

        foreach ($formats as $format) {
            $date = DateTime::createFromFormat($format, $value, $timezone);
            if ($date instanceof DateTime) {
                return $date->getTimestamp();
            }
        }

        $timestamp = strtotime($value);
        return $timestamp !== false ? $timestamp : null;
    }

    private function formatTimeFromTimestamp(int $timestamp, ?\DateTimeZone $timezone = null): string
    {
        if (!($timezone instanceof \DateTimeZone)) {
            $timezone = wp_timezone();
            if (!($timezone instanceof \DateTimeZone)) {
                $timezone = new \DateTimeZone('UTC');
            }
        }

        $time = wp_date('H:i', $timestamp, $timezone);
        return $this->formatTimeForDisplay($time);
    }

    private function formatTimeForDisplay(string $time): string
    {
        $time = trim($time);
        if ($time === '' || strpos($time, ':') === false) {
            return $time;
        }

        $parts = explode(':', $time);
        $hours = isset($parts[0]) ? ltrim($parts[0], '0') : '';
        $minutes = isset($parts[1]) ? $parts[1] : '00';

        if ($hours === '') {
            $hours = '0';
        }

        if ($hours === '0' && $minutes === '00') {
            return '';
        }

        if ($minutes === '00') {
            return $hours . 'h';
        }

        return $hours . 'h' . $minutes;
    }

    private function extractTimePart(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (preg_match('/(\d{2}:\d{2})/', $value, $match)) {
            return $match[1];
        }

        return '';
    }

    private function isAllDayTimeRange(string $startTime, string $endTime, ?int $startTimestamp = null, ?int $endTimestamp = null): bool
    {
        if ($startTime !== '00:00') {
            return false;
        }

        if (in_array($endTime, array('23:59', '23:59:59', '24:00'), true)) {
            return true;
        }

        if ($endTime === '00:00') {
            if ($startTimestamp !== null && $endTimestamp !== null) {
                return $endTimestamp > $startTimestamp;
            }
            return true;
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAssets(): array
    {
        $baseUrl = defined('MJ_MEMBER_URL') ? MJ_MEMBER_URL : '';

        return array(
            'base_url' => $baseUrl,
            'images_url' => $baseUrl . 'images/',
            'icons' => array(
                'whatsapp' => $baseUrl . 'images/whatsapp-icon.png',
                'calendar' => $baseUrl . 'images/calendar-icon.svg',
                'location' => $baseUrl . 'images/location-icon.svg',
            ),
        );
    }

    /**
     * Calcule une couleur contrastée (noir ou blanc) pour le texte
     *
     * @param string $hexColor
     * @return string
     */
    private function getContrastColor(string $hexColor): string
    {
        $hex = ltrim($hexColor, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        if (strlen($hex) !== 6) {
            return '#ffffff';
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        // Calcul de luminance relative
        $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;

        return $luminance > 0.5 ? '#000000' : '#ffffff';
    }
}
