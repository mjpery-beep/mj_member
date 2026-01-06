<?php

namespace Mj\Member\Classes\View\Schedule;

use DateTime;
use DateTimeZone;
use Mj\Member\Core\TemplateEngine;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Helper de rendu pour les plannings d'événements.
 *
 * Centralise le mapping des différents modes (date fixe, plage, récurrence, série)
 * vers des templates Twig dédiés. Permet de réutiliser un affichage cohérent
 * sur le front et l'admin en exposant un point d'entrée unique.
 */
final class ScheduleDisplayHelper
{
    private const DEFAULT_VARIANT = 'default';

    /**
     * @var array<string,string>
     */
    private const DEFAULT_TEMPLATE_MAP = array(
        'fixed' => 'fixed.html.twig',
        'range' => 'range.html.twig',
        'recurring_weekly' => 'recurring-weekly.html.twig',
        'recurring_monthly' => 'recurring-monthly.html.twig',
        'series' => 'series.html.twig',
        'fallback' => 'fallback.html.twig',
    );

    /**
     * Rend l'affichage du planning.
     *
     * @param array<string,mixed> $schedule Données de planning (mode, dates, weekly_schedule...).
     * @param array<string,mixed> $options  Options de rendu (variant, template_map, extra_context).
     */
    public static function render(array $schedule, array $options = array()): string
    {
        $variant = self::sanitizeVariant($options['variant'] ?? self::DEFAULT_VARIANT);
        $type = self::detectType($schedule);
        $templateCandidates = self::collectTemplates($type, $variant, $options['template_map'] ?? array());
        $context = self::buildContext($schedule, $variant, $options);

        foreach ($templateCandidates as $template) {
            try {
                return TemplateEngine::render($template, $context);
            } catch (LoaderError | RuntimeError | SyntaxError $twigError) {
                continue;
            } catch (\Throwable $throwable) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('ScheduleDisplayHelper render error: ' . $throwable->getMessage());
                }
                break;
            }
        }

        return '';
    }

    private static function sanitizeVariant($value): string
    {
        $key = sanitize_key((string) $value);
        return $key !== '' ? $key : self::DEFAULT_VARIANT;
    }

    /**
     * @param array<string,mixed> $schedule
     */
    private static function detectType(array $schedule): string
    {
        $mode = sanitize_key(isset($schedule['mode']) ? (string) $schedule['mode'] : '');
        $weekly = isset($schedule['weekly_schedule']) && is_array($schedule['weekly_schedule'])
            ? $schedule['weekly_schedule']
            : array();

        if ($mode === 'recurring') {
            if (!empty($weekly['is_weekly'])) {
                return 'recurring_weekly';
            }
            if (!empty($weekly['is_monthly'])) {
                return 'recurring_monthly';
            }
            if (!empty($weekly['is_series'])) {
                return 'series';
            }
            return 'recurring_weekly';
        }

        if ($mode === 'series') {
            return 'series';
        }

        if ($mode === 'range') {
            return 'range';
        }

        if ($mode === 'fixed') {
            return 'fixed';
        }

        return 'fallback';
    }

    /**
     * @param array<string,string> $customMap
     * @return array<int,string>
     */
    private static function collectTemplates(string $type, string $variant, array $customMap): array
    {
        $candidates = array();

        if (isset($customMap[$type])) {
            $customCandidate = trim((string) $customMap[$type]);
            if ($customCandidate !== '') {
                $candidates[] = $customCandidate;
            }
        }

        $defaultName = self::DEFAULT_TEMPLATE_MAP[$type] ?? self::DEFAULT_TEMPLATE_MAP['fallback'];
        $variantCandidate = sprintf('components/schedule/%s/%s', $variant, $defaultName);
        $candidates[] = $variantCandidate;

        if ($variant !== self::DEFAULT_VARIANT) {
            $candidates[] = sprintf('components/schedule/%s/%s', self::DEFAULT_VARIANT, $defaultName);
        }

        if (!in_array('components/schedule/' . self::DEFAULT_VARIANT . '/' . self::DEFAULT_TEMPLATE_MAP['fallback'], $candidates, true)) {
            $candidates[] = 'components/schedule/' . self::DEFAULT_VARIANT . '/' . self::DEFAULT_TEMPLATE_MAP['fallback'];
        }

        return array_values(array_unique($candidates));
    }

    /**
     * @param array<string,mixed> $schedule
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    private static function buildContext(array $schedule, string $variant, array $options): array
    {
        $summary = self::normalizeText($schedule['schedule_summary'] ?? '');
        $primaryLabel = self::normalizeText($schedule['display_label'] ?? '');
        $dateRangeLabel = self::formatDateRangeLabel($schedule['date_debut'] ?? '', $schedule['date_fin'] ?? '');

        $weekly = self::normalizeWeeklySchedule($schedule['weekly_schedule'] ?? array());
        $nextOccurrenceLabel = self::resolveNextOccurrenceLabel($schedule);
        $occurrenceList = self::extractOccurrenceLabels($schedule['occurrences'] ?? array());

        $context = array(
            'mode' => sanitize_key(isset($schedule['mode']) ? (string) $schedule['mode'] : ''),
            'variant' => $variant,
            'summary' => $summary,
            'primary_label' => $primaryLabel,
            'date_range_label' => $dateRangeLabel,
            'weekly' => $weekly,
            'next_occurrence_label' => $nextOccurrenceLabel,
            'occurrence_list' => $occurrenceList,
        );

        if (!empty($options['extra_context']) && is_array($options['extra_context'])) {
            $context = array_merge($context, $options['extra_context']);
        }

        return $context;
    }

    /**
     * Construit un libellé court pour les événements du calendrier Elementor.
     *
     * @param array<string,mixed>|object|null $event   Données de l'événement.
     * @param array<int,array<string,mixed>>   $occurrences Occurrences connues (start, end, timestamp...).
     * @param array<string,mixed>              $options     now (timestamp), timezone (DateTimeZone), fallback_label, variant.
     */
    public static function buildCalendarLabel($event, array $occurrences, array $options = array()): string
    {
        $eventData = self::normalizeEventData($event);

        $nowTs = isset($options['now']) ? (int) $options['now'] : current_time('timestamp');
        $timezone = isset($options['timezone']) && $options['timezone'] instanceof DateTimeZone
            ? $options['timezone']
            : wp_timezone();
        $fallbackLabel = isset($options['fallback_label']) ? trim((string) $options['fallback_label']) : '';
        $variant = isset($options['variant']) ? sanitize_key((string) $options['variant']) : 'event-schedule-calendar';

        $mode = isset($eventData['schedule_mode']) ? sanitize_key((string) $eventData['schedule_mode']) : 'fixed';
        if ($mode === '') {
            $mode = 'fixed';
        }

        $scheduleSummary = '';
        foreach (array('occurrence_schedule_summary', 'schedule_summary') as $summaryKey) {
            if (isset($eventData[$summaryKey]) && !is_array($eventData[$summaryKey])) {
                $candidate = trim((string) $eventData[$summaryKey]);
                if ($candidate !== '') {
                    $scheduleSummary = $candidate;
                    break;
                }
            }
        }

        $displayLabel = '';
        foreach (array('display_date_label', 'display_label') as $labelKey) {
            if (isset($eventData[$labelKey]) && !is_array($eventData[$labelKey])) {
                $candidate = trim((string) $eventData[$labelKey]);
                if ($candidate !== '') {
                    $displayLabel = $candidate;
                    break;
                }
            }
        }

        $dateDebut = isset($eventData['date_debut']) && !is_array($eventData['date_debut']) ? (string) $eventData['date_debut'] : '';
        $dateFin = isset($eventData['date_fin']) && !is_array($eventData['date_fin']) ? (string) $eventData['date_fin'] : '';

        $normalizedOccurrences = array();
        foreach ($occurrences as $occurrence) {
            if (!is_array($occurrence)) {
                continue;
            }

            $startValue = isset($occurrence['start']) ? trim((string) $occurrence['start']) : '';
            if ($startValue === '') {
                continue;
            }

            $timestamp = isset($occurrence['timestamp']) ? (int) $occurrence['timestamp'] : 0;
            if ($timestamp <= 0) {
                $timestamp = strtotime($startValue);
            }
            if ($timestamp <= 0) {
                continue;
            }

            $entry = array(
                'start' => $startValue,
                'timestamp' => $timestamp,
                'is_past' => ($timestamp < $nowTs),
            );

            if (isset($occurrence['end']) && $occurrence['end'] !== '') {
                $entry['end'] = (string) $occurrence['end'];
            }
            if (isset($occurrence['label']) && !is_array($occurrence['label'])) {
                $entry['label'] = (string) $occurrence['label'];
            }

            $normalizedOccurrences[] = $entry;
        }

        if (!empty($normalizedOccurrences)) {
            usort(
                $normalizedOccurrences,
                static function (array $left, array $right): int {
                    return (int) $left['timestamp'] <=> (int) $right['timestamp'];
                }
            );
        }

        $nextOccurrence = null;
        foreach ($normalizedOccurrences as $entry) {
            if ((int) $entry['timestamp'] >= $nowTs) {
                $nextOccurrence = $entry;
                break;
            }
        }
        if ($nextOccurrence === null && !empty($normalizedOccurrences)) {
            $nextOccurrence = $normalizedOccurrences[0];
        }

        if ($fallbackLabel === '' && $nextOccurrence !== null) {
            $startDt = self::parseDateTime($nextOccurrence['start']);
            $endDt = null;
            if (isset($nextOccurrence['end'])) {
                $endDt = self::parseDateTime($nextOccurrence['end']);
            }
            if ($startDt instanceof DateTime) {
                $fallbackLabel = self::formatTimeRangeLabel($startDt, $endDt);
            }
        }

        $schedule = array(
            'mode' => $mode,
            'schedule_summary' => $scheduleSummary,
            'display_label' => $displayLabel,
            'date_debut' => $dateDebut,
            'date_fin' => $dateFin,
            'occurrences' => $normalizedOccurrences,
            'weekly_schedule' => array(),
        );

        if ($nextOccurrence !== null) {
            $schedule['next_occurrence'] = $nextOccurrence;
        }

        if ($fallbackLabel !== '') {
            $schedule['next_occurrence_label'] = $fallbackLabel;
        }

        $extraContext = array('fallback_label' => $fallbackLabel);
        if (!empty($options['extra_context']) && is_array($options['extra_context'])) {
            $extraContext = array_merge($extraContext, $options['extra_context']);
        }

        $rendered = self::render(
            $schedule,
            array(
                'variant' => $variant,
                'extra_context' => $extraContext,
            )
        );

        $label = trim(wp_strip_all_tags($rendered));
        if ($label === '' && $fallbackLabel !== '') {
            $label = $fallbackLabel;
        }

        return $label;
    }

    /**
     * @param array<string,mixed> $schedule
     */
    private static function resolveNextOccurrenceLabel(array $schedule): string
    {
        if (!empty($schedule['next_occurrence_label'])) {
            return self::normalizeText($schedule['next_occurrence_label']);
        }

        if (empty($schedule['next_occurrence']) || !is_array($schedule['next_occurrence'])) {
            return '';
        }

        $next = $schedule['next_occurrence'];
        if (!empty($next['label'])) {
            return self::normalizeText($next['label']);
        }

        $fullDate = self::normalizeText($next['full_date'] ?? '');
        $timeStart = self::normalizeText($next['time_start'] ?? '');
        $timeEnd = self::normalizeText($next['time_end'] ?? '');

        if ($fullDate === '') {
            if (!empty($next['start'])) {
                $fullDate = self::formatDateLabel((string) $next['start']);
            }
        }

        if ($fullDate === '') {
            return '';
        }

        if ($timeStart !== '') {
            if ($timeEnd !== '' && $timeEnd !== $timeStart) {
                return sprintf('%s · %s → %s', $fullDate, $timeStart, $timeEnd);
            }
            return sprintf('%s · %s', $fullDate, $timeStart);
        }

        return $fullDate;
    }

    /**
     * @param array<int,array<string,mixed>> $occurrences
     * @return array<int,string>
     */
    private static function extractOccurrenceLabels(array $occurrences): array
    {
        $labels = array();
        foreach ($occurrences as $occurrence) {
            if (!is_array($occurrence)) {
                continue;
            }

            if (count($labels) >= 5) {
                break;
            }

            if (!empty($occurrence['label'])) {
                $labels[] = self::normalizeText($occurrence['label']);
                continue;
            }

            if (!empty($occurrence['start'])) {
                $labels[] = self::formatDateLabel((string) $occurrence['start']);
            }
        }

        return array_values(array_filter($labels));
    }

    /**
     * @param array<string,mixed> $weekly
     * @return array<string,mixed>
     */
    private static function normalizeWeeklySchedule($weekly): array
    {
        if (!is_array($weekly)) {
            $weekly = array();
        }

        $days = array();
        if (!empty($weekly['days']) && is_array($weekly['days'])) {
            foreach ($weekly['days'] as $day) {
                if (!is_array($day)) {
                    continue;
                }

                $label = self::normalizeText($day['label'] ?? '');
                $timeRange = self::normalizeText($day['time_range'] ?? '');
                if ($label === '' && $timeRange === '') {
                    continue;
                }

                $days[] = array(
                    'label' => $label,
                    'time_range' => $timeRange,
                );
            }
        }

        $seriesItems = array();
        if (!empty($weekly['series_items']) && is_array($weekly['series_items'])) {
            foreach ($weekly['series_items'] as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $dateFormatted = self::normalizeText($item['date_formatted'] ?? '');
                $timeRange = self::normalizeText($item['time_range'] ?? '');
                if ($dateFormatted === '' && $timeRange === '') {
                    continue;
                }

                $seriesItems[] = array(
                    'date_formatted' => $dateFormatted,
                    'time_range' => $timeRange,
                );
            }
        }

        return array(
            'is_weekly' => !empty($weekly['is_weekly']),
            'is_monthly' => !empty($weekly['is_monthly']),
            'is_series' => !empty($weekly['is_series']),
            'show_date_range' => !empty($weekly['show_date_range']),
            'monthly_label' => self::normalizeText($weekly['monthly_label'] ?? ''),
            'time_range' => self::normalizeText($weekly['time_range'] ?? ''),
            'days' => $days,
            'series_items' => $seriesItems,
        );
    }

    /**
     * @param array<string,mixed>|object|null $event
     * @return array<string,mixed>
     */
    private static function normalizeEventData($event): array
    {
        if (is_array($event)) {
            return $event;
        }

        if (is_object($event)) {
            return get_object_vars($event);
        }

        return array();
    }

    private static function normalizeText($value): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        $text = wp_strip_all_tags((string) $value);
        return trim($text);
    }

    private static function formatDateLabel(string $value): string
    {
        $date = self::parseDateTime($value);
        if (!$date instanceof DateTime) {
            return '';
        }

        return date_i18n('l j F Y', $date->getTimestamp());
    }

    private static function formatDateRangeLabel(string $start, string $end): string
    {
        $startDate = self::parseDateTime($start);
        if (!$startDate instanceof DateTime) {
            return '';
        }

        $endDate = self::parseDateTime($end);
        if (!$endDate instanceof DateTime || $endDate <= $startDate) {
            $endDate = clone $startDate;
            $endDate->modify('+1 hour');
        }

        $timeFormat = get_option('time_format', 'H:i');
        $startLabel = date_i18n('l j F Y', $startDate->getTimestamp());
        $endLabel = date_i18n('l j F Y', $endDate->getTimestamp());

        if ($startDate->format('Y-m-d') === $endDate->format('Y-m-d')) {
            $startTime = date_i18n($timeFormat, $startDate->getTimestamp());
            $endTime = date_i18n($timeFormat, $endDate->getTimestamp());

            if ($startTime === $endTime) {
                if ($startTime === '00:00') {
                    return $startLabel;
                }
                return sprintf('%s · %s', $startLabel, $startTime);
            }

            return sprintf('%s · %s → %s', $startLabel, $startTime, $endTime);
        }

        return sprintf('%s → %s', $startLabel, $endLabel);
    }

    private static function formatTimeRangeLabel(DateTime $start, ?DateTime $end = null): string
    {
        $timeFormat = get_option('time_format', 'H:i');
        $startLabel = self::normalizeText(date_i18n($timeFormat, $start->getTimestamp()));

        if ($startLabel === '') {
            return '';
        }

        if ($end instanceof DateTime) {
            $endLabel = self::normalizeText(date_i18n($timeFormat, $end->getTimestamp()));
            if ($endLabel !== '' && $endLabel !== $startLabel) {
                return $startLabel . ' → ' . $endLabel;
            }
        }

        return sprintf(__('À partir de %s', 'mj-member'), $startLabel);
    }

    private static function parseDateTime(string $value): ?DateTime
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $timezone = wp_timezone();
        $formats = array('Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d');

        foreach ($formats as $format) {
            $candidate = DateTime::createFromFormat($format, $value, $timezone);
            if ($candidate instanceof DateTime) {
                return $candidate;
            }
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }

        $date = new DateTime('@' . $timestamp);
        $date->setTimezone($timezone);

        return $date;
    }
}
