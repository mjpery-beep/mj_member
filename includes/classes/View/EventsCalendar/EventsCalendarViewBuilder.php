<?php

namespace Mj\Member\Classes\View\EventsCalendar;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Mj\Member\Classes\Crud\MjEventAnimateurs;
use Mj\Member\Classes\Crud\MjEventClosures;
use Mj\Member\Classes\Crud\MjEvents;
use Mj\Member\Classes\MjEventSchedule;
use Mj\Member\Classes\MjRoles;
use Mj\Member\Classes\View\Schedule\ScheduleDisplayHelper;

if (!defined('ABSPATH')) {
    exit;
}

final class EventsCalendarViewBuilder
{
    private array $settings;

    private DateTimeZone $timezone;

    private int $nowTimestamp;

    public function __construct(array $settings)
    {
        $this->settings = $settings;

        $timezone = wp_timezone();
        if (!($timezone instanceof DateTimeZone)) {
            $timezone = new DateTimeZone('UTC');
        }

        $this->timezone = $timezone;
        $this->nowTimestamp = (int) current_time('timestamp');
    }

    /**
     * Build the data model consumed by the Elementor calendar template.
     *
     * @return array<string,mixed>
     */
    public function build(): array
    {
        $statusFilter = $this->resolveStatusFilter();
        $typeFilter = $this->resolveTypeFilter();

        $monthsBefore = isset($this->settings['months_before']) ? max(0, (int) $this->settings['months_before']) : 0;
        $monthsAfter = isset($this->settings['months_after']) ? max(1, (int) $this->settings['months_after']) : 3;
        $totalMonths = max(1, $monthsBefore + $monthsAfter + 1);

        $currentMonth = $this->resolveCurrentMonth();
        $firstMonth = $currentMonth->modify('-' . $monthsBefore . ' months');
        $lastMonth = $firstMonth->modify('+' . ($totalMonths - 1) . ' months');

        $rangeStart = $firstMonth->setTime(0, 0, 0)->getTimestamp();
        $rangeEnd = $lastMonth->modify('last day of this month')->setTime(23, 59, 59)->getTimestamp();

        $highlightNext = !isset($this->settings['highlight_next_event']) || $this->settings['highlight_next_event'] === 'yes';
        $highlightClosureDays = isset($this->settings['highlight_closure_days']) && $this->settings['highlight_closure_days'] === 'yes';
        $hideClosureOccurrences = !isset($this->settings['hide_closure_occurrences']) || $this->settings['hide_closure_occurrences'] === 'yes';
        $showToolbarLeft = !isset($this->settings['show_toolbar_left']) || $this->settings['show_toolbar_left'] === 'yes';
        $showToolbarActions = !isset($this->settings['show_toolbar_actions']) || $this->settings['show_toolbar_actions'] === 'yes';

        $coverWidths = $this->normalizeCoverWidthSettings($this->settings);
        $emptyMessage = $this->resolveEmptyMessage();

        $events = $this->loadEvents($statusFilter, $typeFilter);
        $typeColors = $this->resolveTypeColors();

        if ($this->isElementorPreview() && empty($events)) {
            $events = $this->buildPreviewEvents($rangeStart, $typeColors);
        }

        $displayCurrentWeekOnly = isset($this->settings['current_week_only']) && $this->settings['current_week_only'] === 'yes';
        $weekContext = $this->buildWeekContext($displayCurrentWeekOnly);

        $occurrenceSince = wp_date('Y-m-d H:i:s', $rangeStart, $this->timezone);
        $occurrenceUntil = wp_date('Y-m-d H:i:s', $rangeEnd, $this->timezone);

        $closureDates = $this->loadClosureDates($highlightClosureDays, $hideClosureOccurrences, $rangeStart, $rangeEnd);

        $months = $this->initialiseMonths($firstMonth, $totalMonths);

        $calendarData = $this->populateCalendar(
            $events,
            $months,
            $typeColors,
            $highlightNext,
            $hideClosureOccurrences,
            $closureDates,
            $rangeStart,
            $rangeEnd,
            $occurrenceSince,
            $occurrenceUntil
        );

        if ($highlightClosureDays && !empty($closureDates)) {
            $calendarData = $this->injectClosureEvents($calendarData, $closureDates);
        }

        if ($calendarData['next_event_pointer'] !== null) {
            $pointer = $calendarData['next_event_pointer'];
            if (isset($calendarData['months'][$pointer['month_key']])) {
                $calendarData['months'][$pointer['month_key']]['has_next_event'] = true;
            }
        }

        return array(
            'title' => $this->resolveTitle(),
            'months' => $calendarData['months'],
            'month_keys' => array_keys($calendarData['months']),
            'available_type_filters' => $calendarData['available_type_filters'],
            'next_event_pointer' => $calendarData['next_event_pointer'],
            'restrict_mobile_to_week' => $weekContext['restrict_mobile_to_week'],
            'week_days_keys' => $weekContext['week_days_keys'],
            'display_current_week_only' => $displayCurrentWeekOnly,
            'show_toolbar_left' => $showToolbarLeft,
            'show_toolbar_actions' => $showToolbarActions,
            'empty_message' => $emptyMessage,
            'cover_width_settings' => $coverWidths,
            'timezone' => $this->timezone,
            'now_timestamp' => $this->nowTimestamp,
            'has_any_event' => $calendarData['has_any_event'],
            'count_labels' => $this->buildCountLabels(),
        );
    }

    /**
     * Resolve the translated count labels displayed in the toolbar.
     *
     * @return array<string,string>
     */
    private function buildCountLabels(): array
    {
        return array(
            'singular' => __('%d événement', 'mj-member'),
            'plural' => __('%d événements', 'mj-member'),
            'empty' => __('Aucun événement', 'mj-member'),
        );
    }

    /**
     * Retrieve the widget title, trimmed and sanitized.
     */
    private function resolveTitle(): string
    {
        if (empty($this->settings['title'])) {
            return '';
        }

        return sanitize_text_field((string) $this->settings['title']);
    }

    /**
     * Build the list of statuses to fetch from the repository.
     *
     * @return array<int,string>
     */
    private function resolveStatusFilter(): array
    {
        $filter = array();

        if (!empty($this->settings['statuses']) && is_array($this->settings['statuses'])) {
            foreach ($this->settings['statuses'] as $candidate) {
                $candidate = sanitize_key((string) $candidate);
                if ($candidate === '') {
                    continue;
                }
                $filter[$candidate] = $candidate;
            }
        }

        if (empty($filter)) {
            $filter = array(MjEvents::STATUS_ACTIVE);
        } else {
            $filter = array_values($filter);
        }

        return $filter;
    }

    /**
     * Build the targeted type filter based on Elementor controls.
     *
     * @return array<int,string>
     */
    private function resolveTypeFilter(): array
    {
        $filter = array();

        if (!empty($this->settings['types']) && is_array($this->settings['types'])) {
            foreach ($this->settings['types'] as $candidate) {
                $candidate = sanitize_key((string) $candidate);
                if ($candidate === '') {
                    continue;
                }
                $filter[$candidate] = $candidate;
            }
        }

        return array_values($filter);
    }

    /**
     * Resolve the current month used as reference for the calendar window.
     */
    private function resolveCurrentMonth(): DateTimeImmutable
    {
        try {
            return new DateTimeImmutable('first day of this month', $this->timezone);
        } catch (\Exception $exception) {
            return new DateTimeImmutable('first day of this month');
        }
    }

    /**
     * Normalize the cover width values coming from Elementor slider controls.
     *
     * @param array<string,mixed> $settings
     * @return array<string,int>
     */
    private function normalizeCoverWidthSettings(array $settings): array
    {
        $defaults = array(
            'desktop' => 120,
            'tablet' => 110,
            'mobile' => 90,
        );

        $normalized = array();

        foreach ($defaults as $key => $fallback) {
            $settingKey = 'cover_width_' . $key;
            $value = isset($settings[$settingKey]) ? $settings[$settingKey] : array();
            $normalized[$key] = $this->extractCoverWidthValue($value, $fallback);
        }

        return $normalized;
    }

    /**
     * Extract a single width from the Elementor slider payload.
     *
     * @param mixed $value
     */
    private function extractCoverWidthValue($value, int $fallback): int
    {
        $min = 10;
        $max = 500;

        if (is_array($value) && isset($value['size']) && is_numeric($value['size'])) {
            $candidate = (float) $value['size'];
            if ($candidate >= $min && $candidate <= $max) {
                return (int) round($candidate);
            }
        }

        if (is_numeric($value)) {
            $candidate = (float) $value;
            if ($candidate >= $min && $candidate <= $max) {
                return (int) round($candidate);
            }
        }

        return (int) $fallback;
    }

    /**
     * Resolve the empty state message displayed under the calendar.
     */
    private function resolveEmptyMessage(): string
    {
        $message = '';

        if (isset($this->settings['empty_message']) && is_string($this->settings['empty_message'])) {
            $message = trim($this->settings['empty_message']);
        }

        if ($message === '') {
            $message = __('Aucun événement disponible pour le moment.', 'mj-member');
        }

        return $message;
    }

    /**
     * Fetch events through the legacy helper to preserve backwards compatibility.
     *
     * @param array<int,string> $statusFilter
     * @param array<int,string> $typeFilter
     * @return array<int,array<string,mixed>>
     */
    private function loadEvents(array $statusFilter, array $typeFilter): array
    {
        if (!function_exists('mj_member_get_public_events')) {
            return array();
        }

        $events = mj_member_get_public_events(
            array(
                'statuses' => $statusFilter,
                'types' => $typeFilter,
                'limit' => 200,
                'order' => 'ASC',
                'orderby' => 'date_debut',
                'include_past' => true,
            )
        );

        return is_array($events) ? $events : array();
    }

    /**
     * Retrieve the color map for event types when available.
     *
     * @return array<string,string>
     */
    private function resolveTypeColors(): array
    {
        if (method_exists(MjEvents::class, 'get_type_colors')) {
            $colors = MjEvents::get_type_colors();
            return is_array($colors) ? $colors : array();
        }

        return array();
    }

    /**
     * Determine whether the widget is currently rendered inside the Elementor editor.
     */
    private function isElementorPreview(): bool
    {
        if (!class_exists('\\Elementor\\Plugin')) {
            return false;
        }

        $plugin = \Elementor\Plugin::instance();

        return isset($plugin->editor) && method_exists($plugin->editor, 'is_edit_mode')
            ? (bool) $plugin->editor->is_edit_mode()
            : false;
    }

    /**
     * Build the fallback preview dataset displayed in Elementor editor mode.
     *
     * @param array<string,string> $typeColors
     * @return array<int,array<string,mixed>>
     */
    private function buildPreviewEvents(int $rangeStart, array $typeColors): array
    {
        $defaultEvent = array(
            'id' => 1,
            'title' => __('Stage de découverte', 'mj-member'),
            'type' => 'stage',
            'status' => MjEvents::STATUS_ACTIVE,
            'date_debut' => wp_date('Y-m-d 10:00:00', $rangeStart + DAY_IN_SECONDS, $this->timezone),
            'date_fin' => wp_date('Y-m-d 16:00:00', $rangeStart + DAY_IN_SECONDS, $this->timezone),
            'schedule_mode' => 'single',
            'schedule_payload' => array(),
            'permalink' => home_url('/evenement/stage-de-decouverte'),
            'accent_color' => isset($typeColors['stage']) ? $this->normalizeHexColorValue($typeColors['stage']) : '',
        );

        $secondEvent = $defaultEvent;
        $secondEvent['id'] = 2;
        $secondEvent['title'] = __('Atelier numérique', 'mj-member');
        $secondEvent['type'] = 'atelier';
        $secondEvent['date_debut'] = wp_date('Y-m-d 18:00:00', $rangeStart + (int) (3 * DAY_IN_SECONDS), $this->timezone);
        $secondEvent['date_fin'] = wp_date('Y-m-d 20:00:00', $rangeStart + (int) (3 * DAY_IN_SECONDS), $this->timezone);
        $secondEvent['accent_color'] = isset($typeColors['atelier']) ? $this->normalizeHexColorValue($typeColors['atelier']) : '';
        $secondEvent['permalink'] = home_url('/evenement/atelier-numerique');

        return array($defaultEvent, $secondEvent);
    }

    /**
     * Compute the optional week restriction applied on mobile.
     *
     * @return array{week_days_keys:array<int,string>, restrict_mobile_to_week:array<string,bool>}
     */
    private function buildWeekContext(bool $displayCurrentWeekOnly): array
    {
        if (!$displayCurrentWeekOnly) {
            return array(
                'week_days_keys' => array(),
                'restrict_mobile_to_week' => array(),
            );
        }

        try {
            $reference = (new DateTimeImmutable('@' . $this->nowTimestamp))->setTimezone($this->timezone);
        } catch (\Exception $exception) {
            $reference = new DateTimeImmutable('now', $this->timezone);
        }

        try {
            $weekStart = $reference->modify('monday this week')->setTime(0, 0, 0);
        } catch (\Exception $exception) {
            $weekStart = $reference->setTime(0, 0, 0);
        }

        $weekDays = array();
        $pointer = $weekStart;

        for ($offset = 0; $offset < 7; $offset++) {
            $weekDays[] = $pointer->format('Y-m-d');
            $pointer = $pointer->modify('+1 day');
        }

        return array(
            'week_days_keys' => $weekDays,
            'restrict_mobile_to_week' => array_fill_keys($weekDays, true),
        );
    }

    /**
     * Load closure dates between the provided timestamps.
     *
     * @return array<string,array<string,mixed>>
     */
    private function loadClosureDates(bool $highlightClosureDays, bool $hideClosureOccurrences, int $rangeStart, int $rangeEnd): array
    {
        if ((!$highlightClosureDays && !$hideClosureOccurrences) || !method_exists(MjEventClosures::class, 'get_dates_map_between')) {
            return array();
        }

        $closureStart = wp_date('Y-m-d', $rangeStart, $this->timezone);
        $closureEnd = wp_date('Y-m-d', $rangeEnd, $this->timezone);

        $map = MjEventClosures::get_dates_map_between($closureStart, $closureEnd);

        return is_array($map) ? $map : array();
    }

    /**
     * Create the initial months skeleton that will later host day buckets.
     *
     * @return array<string,array<string,mixed>>
     */
    private function initialiseMonths(DateTimeImmutable $firstMonth, int $totalMonths): array
    {
        $months = array();

        for ($index = 0; $index < $totalMonths; $index++) {
            $monthPoint = $firstMonth->modify('+' . $index . ' month');
            $monthKey = $monthPoint->format('Y-m');

            $months[$monthKey] = array(
                'timestamp' => $monthPoint->getTimestamp(),
                'label' => wp_date('F Y', $monthPoint->getTimestamp(), $this->timezone),
                'days' => array(),
                'has_next_event' => false,
                'multi_events' => array(),
            );
        }

        return $months;
    }

    /**
     * Populate the calendar months with occurrences derived from events.
     *
     * @param array<int,array<string,mixed>> $events
     * @param array<string,array<string,mixed>> $months
     * @param array<string,string> $typeColors
     * @param array<string,array<string,mixed>> $closureDates
     * @return array{
     *     months:array<string,array<string,mixed>>,
     *     available_type_filters:array<string,array<string,string>>,
     *     next_event_pointer:?array<string,mixed>,
     *     has_any_event:bool
     * }
     */
    private function populateCalendar(
        array $events,
        array $months,
        array $typeColors,
        bool $highlightNext,
        bool $hideClosureOccurrences,
        array $closureDates,
        int $rangeStart,
        int $rangeEnd,
        string $occurrenceSince,
        string $occurrenceUntil
    ): array {
        $typeLabels = method_exists(MjEvents::class, 'get_type_labels') ? MjEvents::get_type_labels() : array();
        if (!is_array($typeLabels)) {
            $typeLabels = array();
        }

        $availableTypeFilters = array();
        $nextEventPointer = null;
        $hasAnyEvent = false;

        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }

            $eventId = isset($event['id']) ? (int) $event['id'] : 0;
            if ($eventId <= 0) {
                continue;
            }

            $title = isset($event['title']) ? sanitize_text_field($event['title']) : '';
            $slug = isset($event['slug']) ? sanitize_title($event['slug']) : '';

            $permalink = '';
            if ($slug !== '') {
                $permalink = esc_url(home_url('/evenement/' . rawurlencode($slug)));
            }
            if ($permalink === '' && !empty($event['permalink'])) {
                $permalink = esc_url((string) $event['permalink']);
            }
            if ($permalink === '' && !empty($event['article_permalink'])) {
                $permalink = esc_url((string) $event['article_permalink']);
            }

            $coverModal = !empty($event['cover_url']) ? esc_url((string) $event['cover_url']) : '';
            if ($coverModal === '' && !empty($event['article_cover_url'])) {
                $coverModal = esc_url((string) $event['article_cover_url']);
            }
            $coverThumb = !empty($event['cover_thumb']) ? esc_url((string) $event['cover_thumb']) : $coverModal;
            if ($coverThumb === '' && !empty($event['article_cover_thumb'])) {
                $coverThumb = esc_url((string) $event['article_cover_thumb']);
            }
            $coverId = isset($event['cover_id']) ? (int) $event['cover_id'] : 0;
            $coverSources = $this->buildCoverSources($coverId, $coverModal, $coverThumb);
            $primaryCover = $coverThumb !== '' ? $coverThumb : (isset($coverSources['fallback']) ? $coverSources['fallback'] : '');

            $typeKey = isset($event['type']) ? sanitize_key($event['type']) : '';
            $typeLabel = isset($typeLabels[$typeKey]) ? $typeLabels[$typeKey] : '';
            if ($typeLabel === '' && $typeKey !== '') {
                $typeLabel = ucfirst($typeKey);
            }

            $eventTypeKey = $typeKey !== '' ? $typeKey : 'misc';
            if ($typeLabel === '' && $eventTypeKey === 'misc') {
                $typeLabel = __('Autre', 'mj-member');
            }

            if (!isset($availableTypeFilters[$eventTypeKey])) {
                $fallbackFilterLabel = $typeLabel !== '' ? $typeLabel : ucfirst(str_replace(array('_', '-'), ' ', $eventTypeKey));
                $availableTypeFilters[$eventTypeKey] = array('label' => $fallbackFilterLabel);
            }

            $palette = $this->buildEventPalette(isset($event['accent_color']) ? $event['accent_color'] : '', $eventTypeKey, $typeColors);

            $emojiValue = '';
            if (!empty($event['emoji']) && !is_array($event['emoji'])) {
                $emojiCandidate = sanitize_text_field((string) $event['emoji']);
                if ($emojiCandidate !== '') {
                    $emojiValue = function_exists('mb_substr') ? mb_substr($emojiCandidate, 0, 8) : substr($emojiCandidate, 0, 8);
                }
            }

            $priceValue = null;
            if (array_key_exists('price', $event) && $event['price'] !== null && $event['price'] !== '') {
                $numericPrice = is_numeric($event['price']) ? (float) $event['price'] : null;
                if ($numericPrice !== null) {
                    $priceValue = $numericPrice;
                }
            }

            $locationLabel = !empty($event['location']) ? sanitize_text_field((string) $event['location']) : '';

            $descriptionPreview = '';
            if (!empty($event['excerpt']) && !is_array($event['excerpt'])) {
                $descriptionPreview = wp_strip_all_tags((string) $event['excerpt']);
            } elseif (!empty($event['description']) && !is_array($event['description'])) {
                $descriptionPreview = wp_strip_all_tags((string) $event['description']);
            }
            if ($descriptionPreview !== '') {
                $descriptionPreview = wp_html_excerpt($descriptionPreview, 200, '...');
            }

            $ageMin = isset($event['age_min']) ? (int) $event['age_min'] : 0;
            $ageMax = isset($event['age_max']) ? (int) $event['age_max'] : 0;
            $ageRangeLabel = $this->formatAgeRangeLabel($ageMin, $ageMax);

            $isFreeParticipation = !empty($event['free_participation']) || !empty($event['is_free_participation']);
            $legacyRegistrationMode = isset($event['legacy_registration_mode']) ? sanitize_key((string) $event['legacy_registration_mode']) : '';
            $requiresValidation = !empty($event['requires_validation']);
            $registrationLabel = $this->buildRegistrationLabel($isFreeParticipation, $legacyRegistrationMode, $requiresValidation);

            $recurringSchedulePreview = $this->buildRecurringSchedulePreview($event);
            $scheduleMode = isset($recurringSchedulePreview['mode']) ? sanitize_key((string) $recurringSchedulePreview['mode']) : '';

            $recurrenceSummary = '';
            if (function_exists('mj_member_get_event_recurring_summary')) {
                $recurrenceSummary = (string) mj_member_get_event_recurring_summary($event);
            }
            if ($recurrenceSummary !== '') {
                $recurrenceSummary = sanitize_text_field($recurrenceSummary);
            }

            $animateurItems = $this->buildEventAnimateursPreview($eventId);

            $scheduleOccurrences = array();
            if (class_exists(MjEventSchedule::class)) {
                $scheduleOccurrences = MjEventSchedule::get_occurrences(
                    $event,
                    array(
                        'since' => $occurrenceSince,
                        'until' => $occurrenceUntil,
                        'include_past' => true,
                        'max' => 400,
                    )
                );

                if (empty($scheduleOccurrences)) {
                    $scheduleOccurrences = MjEventSchedule::build_all_occurrences($event);
                }
            }

            if (empty($scheduleOccurrences)) {
                $startRaw = '';
                if (!empty($event['start_date'])) {
                    $startRaw = (string) $event['start_date'];
                } elseif (!empty($event['date_debut'])) {
                    $startRaw = (string) $event['date_debut'];
                }

                if ($startRaw !== '') {
                    $endRaw = '';
                    if (!empty($event['end_date'])) {
                        $endRaw = (string) $event['end_date'];
                    } elseif (!empty($event['date_fin'])) {
                        $endRaw = (string) $event['date_fin'];
                    }

                    $startTsCandidate = strtotime($startRaw);
                    if ($startTsCandidate !== false) {
                        $endTsCandidate = $endRaw !== '' ? strtotime($endRaw) : false;
                        if ($endTsCandidate === false || $endTsCandidate <= $startTsCandidate) {
                            $endTsCandidate = $startTsCandidate + HOUR_IN_SECONDS;
                        }

                        $scheduleOccurrences[] = array(
                            'start' => wp_date('Y-m-d H:i:s', $startTsCandidate, $this->timezone),
                            'end' => wp_date('Y-m-d H:i:s', $endTsCandidate, $this->timezone),
                            'timestamp' => $startTsCandidate,
                        );
                    }
                }
            }

            $normalizedOccurrences = array();

            foreach ($scheduleOccurrences as $occurrence) {
                if (!is_array($occurrence)) {
                    continue;
                }

                $occurrenceStartRaw = isset($occurrence['start']) ? (string) $occurrence['start'] : '';
                if ($occurrenceStartRaw === '') {
                    continue;
                }

                $occurrenceStartDt = $this->createDateTime($occurrenceStartRaw);
                $startTs = isset($occurrence['timestamp']) ? (int) $occurrence['timestamp'] : 0;

                if (!$occurrenceStartDt && $startTs > 0) {
                    $occurrenceStartDt = (new DateTimeImmutable('@' . $startTs))->setTimezone($this->timezone);
                }

                if ($occurrenceStartDt instanceof DateTimeImmutable && $startTs <= 0) {
                    $startTs = $occurrenceStartDt->getTimestamp();
                }

                if ($startTs <= 0) {
                    $fallbackTs = strtotime($occurrenceStartRaw);
                    if ($fallbackTs !== false) {
                        $startTs = $fallbackTs;
                        if (!$occurrenceStartDt) {
                            $occurrenceStartDt = (new DateTimeImmutable('@' . $fallbackTs))->setTimezone($this->timezone);
                        }
                    }
                }

                if ($startTs <= 0) {
                    continue;
                }

                if ($startTs < $rangeStart || $startTs > $rangeEnd) {
                    continue;
                }

                $normalizedStart = wp_date('Y-m-d H:i:s', $startTs, $this->timezone);
                $rawStartTs = $occurrenceStartDt instanceof DateTimeImmutable ? $occurrenceStartDt->getTimestamp() : $startTs;
                $startOffset = $rawStartTs - $startTs;
                $occurrenceStartDt = (new DateTimeImmutable('@' . $startTs))->setTimezone($this->timezone);

                $normalizedEntry = array(
                    'start' => $normalizedStart,
                    'timestamp' => $startTs,
                );

                if (isset($occurrence['end']) && (string) $occurrence['end'] !== '') {
                    $endDt = $this->createDateTime((string) $occurrence['end']);
                    $endTsCandidate = $endDt instanceof DateTimeImmutable ? $endDt->getTimestamp() : strtotime((string) $occurrence['end']);
                    if ($endTsCandidate !== false && $endTsCandidate !== null) {
                        if ($startOffset !== 0) {
                            $endTsCandidate -= $startOffset;
                        }

                        $endDt = (new DateTimeImmutable('@' . $endTsCandidate))->setTimezone($this->timezone);
                        $normalizedEntry['end'] = $endDt->format('Y-m-d H:i:s');
                    }
                }

                if (isset($occurrence['label']) && !is_array($occurrence['label'])) {
                    $normalizedEntry['label'] = (string) $occurrence['label'];
                }

                if (!empty($occurrence['is_cancelled'])) {
                    $normalizedEntry['is_cancelled'] = true;
                }
                if (!empty($occurrence['cancellation_reason']) && !is_array($occurrence['cancellation_reason'])) {
                    $normalizedEntry['cancellation_reason'] = sanitize_text_field((string) $occurrence['cancellation_reason']);
                }

                $normalizedOccurrences[] = $normalizedEntry;
            }

            if (empty($normalizedOccurrences)) {
                continue;
            }

            foreach ($normalizedOccurrences as $occurrence) {
                $occurrenceStartRaw = isset($occurrence['start']) ? (string) $occurrence['start'] : '';
                if ($occurrenceStartRaw === '') {
                    continue;
                }

                $occurrenceStartDt = $this->createDateTime($occurrenceStartRaw);
                $startTs = isset($occurrence['timestamp']) ? (int) $occurrence['timestamp'] : 0;

                if (!$occurrenceStartDt && $startTs > 0) {
                    $occurrenceStartDt = (new DateTimeImmutable('@' . $startTs))->setTimezone($this->timezone);
                }

                if ($occurrenceStartDt instanceof DateTimeImmutable && $startTs <= 0) {
                    $startTs = $occurrenceStartDt->getTimestamp();
                }

                if ($startTs <= 0) {
                    $fallbackTs = strtotime($occurrenceStartRaw);
                    if ($fallbackTs !== false) {
                        $startTs = $fallbackTs;
                        if (!$occurrenceStartDt) {
                            $occurrenceStartDt = (new DateTimeImmutable('@' . $fallbackTs))->setTimezone($this->timezone);
                        }
                    }
                }

                if ($startTs <= 0) {
                    continue;
                }

                if (!$occurrenceStartDt) {
                    $occurrenceStartDt = (new DateTimeImmutable('@' . $startTs))->setTimezone($this->timezone);
                } else {
                    $occurrenceStartDt = (new DateTimeImmutable('@' . $startTs))->setTimezone($this->timezone);
                }

                if ($startTs < $rangeStart || $startTs > $rangeEnd) {
                    continue;
                }

                $monthKey = wp_date('Y-m', $startTs, $this->timezone);
                if (!isset($months[$monthKey])) {
                    continue;
                }

                $dayKey = wp_date('Y-m-d', $startTs, $this->timezone);
                if ($hideClosureOccurrences && isset($closureDates[$dayKey])) {
                    continue;
                }

                $this->ensureDayBucket($months, $monthKey, $dayKey);

                $occurrenceEndDt = null;
                $occurrenceEndTs = $startTs;
                if (!empty($occurrence['end'])) {
                    $occurrenceEndDt = $this->createDateTime((string) $occurrence['end']);
                    if (!$occurrenceEndDt) {
                        $endFallbackTs = strtotime((string) $occurrence['end']);
                        if ($endFallbackTs !== false) {
                            $occurrenceEndDt = (new DateTimeImmutable('@' . $endFallbackTs))->setTimezone($this->timezone);
                        }
                    }
                    if ($occurrenceEndDt) {
                        $occurrenceEndTs = $occurrenceEndDt->getTimestamp();
                    }
                }

                $timeLabel = $this->formatOccurrenceTimeLabel($occurrenceStartDt, $occurrenceEndDt);
                $occurrenceKey = $eventId . ':' . $startTs;

                $occurrenceContext = $occurrence;
                if (!isset($occurrenceContext['timestamp'])) {
                    $occurrenceContext['timestamp'] = $startTs;
                }
                if (!isset($occurrenceContext['start']) || $occurrenceContext['start'] === '') {
                    $occurrenceContext['start'] = $occurrenceStartDt->format('Y-m-d H:i:s');
                }

                $scheduleLabel = ScheduleDisplayHelper::buildCalendarLabel(
                    $event,
                    array($occurrenceContext),
                    array(
                        'now' => $startTs,
                        'timezone' => $this->timezone,
                        'variant' => 'event-schedule-calendar',
                        'fallback_label' => $timeLabel,
                        'extra_context' => array(
                            'next_occurrence_label' => $timeLabel,
                        ),
                    )
                );

                if ($scheduleLabel === '') {
                    $scheduleLabel = $timeLabel;
                }

                $occurrenceIsCancelled = !empty($occurrence['is_cancelled']);
                $occurrenceCancellationReason = '';
                if ($occurrenceIsCancelled) {
                    if (isset($occurrence['cancellation_reason']) && !is_array($occurrence['cancellation_reason'])) {
                        $occurrenceCancellationReason = trim((string) $occurrence['cancellation_reason']);
                    }
                    if ($occurrenceCancellationReason === '') {
                        continue;
                    }
                }

                $months[$monthKey]['days'][$dayKey]['events'][] = array(
                    'id' => $occurrenceKey,
                    'title' => $title,
                    'emoji' => $emojiValue,
                    'time' => $timeLabel,
                    'schedule_label' => $scheduleLabel,
                    'cover' => $primaryCover,
                    'cover_full' => $coverModal,
                    'cover_sources' => $coverSources,
                    'type_label' => $typeLabel,
                    'type_key' => $eventTypeKey,
                    'start_ts' => $startTs,
                    'price' => $priceValue,
                    'location_label' => $locationLabel,
                    'description_excerpt' => $descriptionPreview,
                    'age_min' => $ageMin,
                    'age_max' => $ageMax,
                    'age_label' => $ageRangeLabel !== '' ? sanitize_text_field($ageRangeLabel) : '',
                    'is_free_participation' => $isFreeParticipation,
                    'legacy_registration_mode' => $legacyRegistrationMode,
                    'requires_validation' => $requiresValidation,
                    'registration_label' => $registrationLabel !== '' ? sanitize_text_field($registrationLabel) : '',
                    'recurrence_summary' => $recurrenceSummary,
                    'animateurs' => $animateurItems,
                    'palette' => $palette,
                    'permalink' => $permalink,
                    'accent_color' => isset($palette['base']) ? $palette['base'] : '',
                    'schedule_mode' => $scheduleMode,
                    'recurring_schedule_preview' => $recurringSchedulePreview,
                    'is_cancelled' => $occurrenceIsCancelled,
                    'cancellation_reason' => $occurrenceCancellationReason,
                );

                $hasAnyEvent = true;

                if ($occurrenceEndTs > $startTs) {
                    $spanStartDay = $occurrenceStartDt->setTime(0, 0, 0);
                    $spanEndDay = $occurrenceEndDt ? $occurrenceEndDt->setTime(0, 0, 0) : $spanStartDay;
                    if ($spanEndDay->getTimestamp() < $spanStartDay->getTimestamp()) {
                        $spanEndDay = $spanStartDay;
                    }

                    $monthPointer = new DateTimeImmutable($spanStartDay->format('Y-m-01 00:00:00'), $this->timezone);
                    $endMonthPointer = new DateTimeImmutable($spanEndDay->format('Y-m-01 00:00:00'), $this->timezone);

                    while ($monthPointer->getTimestamp() <= $endMonthPointer->getTimestamp()) {
                        $segmentMonthKey = $monthPointer->format('Y-m');
                        if (isset($months[$segmentMonthKey])) {
                            $monthFirstDay = $monthPointer;
                            $monthLastDay = $monthPointer->modify('last day of this month');

                            if ($monthLastDay->getTimestamp() >= $spanStartDay->getTimestamp() && $monthFirstDay->getTimestamp() <= $spanEndDay->getTimestamp()) {
                                $segmentStartDt = $spanStartDay->getTimestamp() > $monthFirstDay->getTimestamp() ? $spanStartDay : $monthFirstDay;
                                $segmentEndDt = $spanEndDay->getTimestamp() < $monthLastDay->getTimestamp() ? $spanEndDay : $monthLastDay;

                                $segmentStartKey = $segmentStartDt->format('Y-m-d');
                                $segmentEndKey = $segmentEndDt->format('Y-m-d');

                                $months[$segmentMonthKey]['multi_events'][] = array(
                                    'event_key' => $occurrenceKey,
                                    'title' => $title,
                                    'type_label' => $typeLabel,
                                    'start_day' => $segmentStartKey,
                                    'end_day' => $segmentEndKey,
                                    'start_ts' => $startTs,
                                    'cover' => $primaryCover,
                                    'permalink' => $permalink,
                                    'palette' => $palette,
                                    'is_cancelled' => $occurrenceIsCancelled,
                                    'cancellation_reason' => $occurrenceCancellationReason,
                                );

                                $marker = $segmentStartDt;
                                while ($marker->getTimestamp() <= $segmentEndDt->getTimestamp()) {
                                    $markerKey = $marker->format('Y-m-d');
                                    $this->ensureDayBucket($months, $segmentMonthKey, $markerKey);
                                    $months[$segmentMonthKey]['days'][$markerKey]['has_multi'] = true;
                                    $marker = $marker->modify('+1 day');
                                }
                            }
                        }

                        $monthPointer = $monthPointer->modify('first day of next month');
                    }
                }

                if ($highlightNext && $startTs >= $this->nowTimestamp) {
                    if ($nextEventPointer === null || $startTs < $nextEventPointer['start_ts']) {
                        $nextEventPointer = array(
                            'month_key' => $monthKey,
                            'day_key' => $dayKey,
                            'event_key' => $occurrenceKey,
                            'start_ts' => $startTs,
                        );
                    }
                }
            }
        }

        return array(
            'months' => $months,
            'available_type_filters' => $availableTypeFilters,
            'next_event_pointer' => $nextEventPointer,
            'has_any_event' => $hasAnyEvent,
        );
    }

    /**
     * Promote closure days into the calendar structure when required.
     *
     * @param array{
     *     months:array<string,array<string,mixed>>,
     *     available_type_filters:array<string,array<string,string>>,
     *     next_event_pointer:?array<string,mixed>,
     *     has_any_event:bool
     * } $calendarData
     * @param array<string,array<string,mixed>> $closureDates
     * @return array{
     *     months:array<string,array<string,mixed>>,
     *     available_type_filters:array<string,array<string,string>>,
     *     next_event_pointer:?array<string,mixed>,
     *     has_any_event:bool
     * }
     */
    private function injectClosureEvents(array $calendarData, array $closureDates): array
    {
        $months = $calendarData['months'];
        $availableTypeFilters = $calendarData['available_type_filters'];
        $hasAnyEvent = $calendarData['has_any_event'];

        foreach ($closureDates as $closureDate => $closureDetails) {
            $monthKey = substr($closureDate, 0, 7);
            if (!isset($months[$monthKey])) {
                continue;
            }

            $this->ensureDayBucket($months, $monthKey, $closureDate);

            $coverThumb = '';
            $coverFull = '';
            $closureCoverId = isset($closureDetails['cover_id']) ? (int) $closureDetails['cover_id'] : 0;
            if (is_array($closureDetails)) {
                if (!empty($closureDetails['cover_thumb'])) {
                    $coverThumb = esc_url((string) $closureDetails['cover_thumb']);
                }
                if (!empty($closureDetails['cover_full'])) {
                    $coverFull = esc_url((string) $closureDetails['cover_full']);
                } elseif ($coverThumb !== '') {
                    $coverFull = $coverThumb;
                }
            }

            $closureSources = $this->buildCoverSources($closureCoverId, $coverFull, $coverThumb);
            $closurePrimaryCover = $coverThumb !== '' ? $coverThumb : (isset($closureSources['fallback']) ? $closureSources['fallback'] : '');

            $description = '';
            if (is_array($closureDetails) && !empty($closureDetails['description'])) {
                $description = sanitize_text_field((string) $closureDetails['description']);
            }

            $closureTitle = __('MJ fermée', 'mj-member');
            $closureTimeLabel = $description !== '' ? $description : '';
            $closureEventId = 'closure:' . $closureDate;
            $closurePalette = $this->buildClosurePalette();
            $closureTimestamp = strtotime($closureDate . ' 00:00:00');
            if ($closureTimestamp === false) {
                $closureTimestamp = $this->nowTimestamp;
            }

            $eventListReference = &$months[$monthKey]['days'][$closureDate]['events'];
            $existsAlready = false;
            foreach ($eventListReference as $existingEntry) {
                if (isset($existingEntry['id']) && $existingEntry['id'] === $closureEventId) {
                    $existsAlready = true;
                    break;
                }
            }

            if (!$existsAlready) {
                $eventListReference[] = array(
                    'id' => $closureEventId,
                    'title' => $closureTitle,
                    'time' => $closureTimeLabel,
                    'schedule_label' => $closureTimeLabel,
                    'cover' => $closurePrimaryCover,
                    'cover_sources' => $closureSources,
                    'cover_full' => $coverFull,
                    'type_label' => __('Fermeture', 'mj-member'),
                    'type_key' => 'closure',
                    'start_ts' => $closureTimestamp,
                    'is_closure' => true,
                    'palette' => $closurePalette,
                    'permalink' => '',
                    'accent_color' => isset($closurePalette['base']) ? $closurePalette['base'] : '',
                    'schedule_mode' => 'closure',
                    'recurring_schedule_preview' => array(),
                );
            }
            unset($eventListReference);

            $months[$monthKey]['days'][$closureDate]['is_closure'] = true;
            $hasAnyEvent = true;
        }

        if (!isset($availableTypeFilters['closure'])) {
            $availableTypeFilters['closure'] = array(
                'label' => __('Fermeture', 'mj-member'),
            );
        }

        $calendarData['months'] = $months;
        $calendarData['available_type_filters'] = $availableTypeFilters;
        $calendarData['has_any_event'] = $hasAnyEvent;

        return $calendarData;
    }

    /**
     * Convert a database datetime string into an immutable instance.
     */
    private function createDateTime(?string $value): ?DateTimeImmutable
    {
        $value = is_string($value) ? trim($value) : '';
        if ($value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value, $this->timezone);
        } catch (\Exception $exception) {
            return null;
        }
    }

    /**
     * Ensure the calendar contains a day bucket for the provided month/day.
     */
    private function ensureDayBucket(array &$months, string $monthKey, string $dayKey): void
    {
        if (!isset($months[$monthKey])) {
            return;
        }

        if (!isset($months[$monthKey]['days'][$dayKey]) || !is_array($months[$monthKey]['days'][$dayKey])) {
            $months[$monthKey]['days'][$dayKey] = array(
                'events' => array(),
                'has_multi' => false,
            );
            return;
        }

        if (!isset($months[$monthKey]['days'][$dayKey]['events']) || !is_array($months[$monthKey]['days'][$dayKey]['events'])) {
            $months[$monthKey]['days'][$dayKey]['events'] = array();
        }

        if (!array_key_exists('has_multi', $months[$monthKey]['days'][$dayKey])) {
            $months[$monthKey]['days'][$dayKey]['has_multi'] = false;
        }
    }

    /**
     * Format the age range label exposed in tooltips.
     */
    private function formatAgeRangeLabel(int $min, int $max): string
    {
        if ($min <= 0 && $max <= 0) {
            return '';
        }

        if ($min > 0 && $max > 0) {
            if ($min === $max) {
                return sprintf(__('%d ans', 'mj-member'), $min);
            }

            return sprintf(__('De %1$d à %2$d ans', 'mj-member'), $min, $max);
        }

        if ($min > 0) {
            return sprintf(__('Dès %d ans', 'mj-member'), $min);
        }

        return sprintf(__('Jusqu\'à %d ans', 'mj-member'), $max);
    }

    /**
     * Build a registration summary label for the tooltip.
     */
    private function buildRegistrationLabel(bool $isFreeParticipation, string $mode, bool $requiresValidation): string
    {
        if ($isFreeParticipation) {
            $label = __('Participation libre', 'mj-member');

            if ($requiresValidation) {
                $label .= ' — ' . __('Validation requise', 'mj-member');
            }

            return $label;
        }

        $mode = sanitize_key($mode);

        $modeLabels = array(
            'participant' => __('Inscription des jeunes', 'mj-member'),
            'guardian' => __('Inscription via responsables', 'mj-member'),
            'volunteer' => __('Inscription réservée à l\'équipe', 'mj-member'),
            'staff' => __('Réservé à l\'équipe', 'mj-member'),
            'internal' => __('Réservé aux membres internes', 'mj-member'),
            'application' => __('Sur candidature', 'mj-member'),
            'pre_registration' => __('Pré-inscription', 'mj-member'),
            'ticket' => __('Billetterie', 'mj-member'),
            'form' => __('Formulaire externe', 'mj-member'),
            'email' => __('Inscription par email', 'mj-member'),
            'external' => __('Inscription externe', 'mj-member'),
        );

        if ($mode !== '' && isset($modeLabels[$mode])) {
            $label = $modeLabels[$mode];
        } elseif ($mode !== '') {
            $friendly = ucwords(str_replace(array('_', '-'), ' ', $mode));
            $label = sprintf(__('Inscription (%s)', 'mj-member'), $friendly);
        } else {
            $label = __('Inscription en ligne', 'mj-member');
        }

        if ($requiresValidation) {
            $label .= ' — ' . __('Validation requise', 'mj-member');
        }

        return $label;
    }

    /**
     * Build animateur preview data (avatars + initials) for a given event.
     *
     * @return array<int,array<string,mixed>>
     */
    private function buildEventAnimateursPreview(int $eventId): array
    {
        if ($eventId <= 0) {
            return array();
        }

        static $cache = array();
        if (isset($cache[$eventId])) {
            return $cache[$eventId];
        }

        if (!class_exists(MjEventAnimateurs::class)) {
            $cache[$eventId] = array();
            return $cache[$eventId];
        }

        $rows = MjEventAnimateurs::get_members_by_event($eventId);
        if (empty($rows)) {
            $cache[$eventId] = array();
            return $cache[$eventId];
        }

        $items = array();

        foreach ($rows as $index => $row) {
            if (!is_object($row)) {
                continue;
            }

            $memberId = isset($row->id) ? (int) $row->id : 0;
            $firstName = isset($row->first_name) ? sanitize_text_field((string) $row->first_name) : '';
            $lastName = isset($row->last_name) ? sanitize_text_field((string) $row->last_name) : '';

            $fullName = trim($firstName . ' ' . $lastName);
            if ($fullName === '' && isset($row->nickname)) {
                $fullName = sanitize_text_field((string) $row->nickname);
            }
            if ($fullName === '' && $memberId > 0) {
                $fullName = sprintf(__('Membre #%d', 'mj-member'), $memberId);
            }
            $fullName = sanitize_text_field($fullName);

            $roleKey = isset($row->role) ? sanitize_key((string) $row->role) : '';
            $roleLabel = '';
            if ($roleKey !== '' && class_exists(MjRoles::class)) {
                $roleLabel = MjRoles::getRoleLabel($roleKey);
            }
            if ($roleLabel !== '') {
                $roleLabel = sanitize_text_field($roleLabel);
            }

            $avatarUrl = '';
            if (!empty($row->photo_id) && function_exists('wp_get_attachment_image_src')) {
                $photoId = (int) $row->photo_id;
                if ($photoId > 0) {
                    $photo = wp_get_attachment_image_src($photoId, 'thumbnail');
                    if (is_array($photo) && !empty($photo[0])) {
                        $avatarUrl = esc_url_raw($photo[0]);
                    }
                }
            }

            if ($avatarUrl === '' && !empty($row->wp_user_id) && function_exists('get_avatar_url')) {
                $avatarUrl = esc_url_raw(get_avatar_url((int) $row->wp_user_id, array('size' => 96)));
            }

            if ($avatarUrl === '' && !empty($row->email) && is_email($row->email) && function_exists('get_avatar_url')) {
                $avatarUrl = esc_url_raw(get_avatar_url($row->email, array('size' => 96)));
            }

            $initialsSource = $fullName !== '' ? $fullName : trim($firstName . ' ' . $lastName);
            $initials = $this->buildMemberInitials($initialsSource);

            $items[] = array(
                'id' => $memberId,
                'name' => $fullName,
                'role' => $roleKey,
                'role_label' => $roleLabel,
                'avatar' => $avatarUrl,
                'initials' => $initials !== '' ? sanitize_text_field($initials) : '',
                'is_primary' => $index === 0,
            );

            if (count($items) >= 6) {
                break;
            }
        }

        $cache[$eventId] = $items;

        return $cache[$eventId];
    }

    /**
     * Extract two-letter initials from a name.
     */
    private function buildMemberInitials(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return '';
        }

        $parts = preg_split('/[\s\-]+/u', $name);
        if (!is_array($parts) || empty($parts)) {
            $parts = array($name);
        }

        $initials = '';
        foreach ($parts as $part) {
            $part = trim((string) $part);
            if ($part === '') {
                continue;
            }

            $initials .= function_exists('mb_substr') ? mb_substr($part, 0, 1) : substr($part, 0, 1);
            $length = function_exists('mb_strlen') ? mb_strlen($initials) : strlen($initials);
            if ($length >= 2) {
                if ($length > 2) {
                    $initials = function_exists('mb_substr') ? mb_substr($initials, 0, 2) : substr($initials, 0, 2);
                }
                break;
            }
        }

        if ($initials === '' && $name !== '') {
            $initials = function_exists('mb_substr') ? mb_substr($name, 0, 1) : substr($name, 0, 1);
        }

        return function_exists('mb_strtoupper') ? mb_strtoupper($initials) : strtoupper($initials);
    }

    /**
     * Build recurring schedule preview metadata.
     *
     * @param array<string,mixed>|object $event
     * @return array<string,mixed>
     */
    private function buildRecurringSchedulePreview($event): array
    {
        $result = array('mode' => '', 'entries' => array());

        if (is_object($event)) {
            $event = get_object_vars($event);
        }
        if (!is_array($event)) {
            return $result;
        }

        $mode = isset($event['schedule_mode']) ? sanitize_key((string) $event['schedule_mode']) : '';
        if ($mode === '') {
            $mode = 'fixed';
        }
        $result['mode'] = $mode;

        if ($mode !== 'recurring') {
            return $result;
        }

        $payload = array();
        if (isset($event['schedule_payload'])) {
            if (is_array($event['schedule_payload'])) {
                $payload = $event['schedule_payload'];
            } elseif (is_string($event['schedule_payload']) && $event['schedule_payload'] !== '') {
                $decoded = json_decode($event['schedule_payload'], true);
                if (is_array($decoded)) {
                    $payload = $decoded;
                }
            }
        }

        if (empty($payload) || !is_array($payload)) {
            return $result;
        }

        $frequency = isset($payload['frequency']) ? sanitize_key((string) $payload['frequency']) : 'weekly';
        $weekdayLabels = array(
            'monday' => __('Lundi', 'mj-member'),
            'tuesday' => __('Mardi', 'mj-member'),
            'wednesday' => __('Mercredi', 'mj-member'),
            'thursday' => __('Jeudi', 'mj-member'),
            'friday' => __('Vendredi', 'mj-member'),
            'saturday' => __('Samedi', 'mj-member'),
            'sunday' => __('Dimanche', 'mj-member'),
        );

        if ($frequency === 'weekly') {
            $weekdayOrder = array(
                'monday' => 1,
                'tuesday' => 2,
                'wednesday' => 3,
                'thursday' => 4,
                'friday' => 5,
                'saturday' => 6,
                'sunday' => 7,
            );

            $weekdays = array();
            if (!empty($payload['weekdays']) && is_array($payload['weekdays'])) {
                foreach ($payload['weekdays'] as $weekdayCandidate) {
                    $weekdayKey = sanitize_key((string) $weekdayCandidate);
                    if ($weekdayKey !== '' && isset($weekdayOrder[$weekdayKey])) {
                        $weekdays[$weekdayKey] = $weekdayKey;
                    }
                }
            }

            if (empty($weekdays) && !empty($payload['weekday_times']) && is_array($payload['weekday_times'])) {
                foreach ($payload['weekday_times'] as $weekdayKey => $timeInfo) {
                    $weekdayCandidate = sanitize_key((string) $weekdayKey);
                    if ($weekdayCandidate !== '' && isset($weekdayOrder[$weekdayCandidate])) {
                        $weekdays[$weekdayCandidate] = $weekdayCandidate;
                    }
                }
            }

            if (empty($weekdays)) {
                return $result;
            }

            uksort($weekdays, static function ($left, $right) use ($weekdayOrder) {
                return $weekdayOrder[$left] <=> $weekdayOrder[$right];
            });

            $defaultStart = isset($payload['start_time']) ? (string) $payload['start_time'] : '';
            $defaultEnd = isset($payload['end_time']) ? (string) $payload['end_time'] : '';
            $weekdayTimes = isset($payload['weekday_times']) && is_array($payload['weekday_times']) ? $payload['weekday_times'] : array();

            $timeGroups = array();

            foreach (array_keys($weekdays) as $weekdayKey) {
                $startRaw = $defaultStart;
                $endRaw = $defaultEnd;

                if (isset($weekdayTimes[$weekdayKey]) && is_array($weekdayTimes[$weekdayKey])) {
                    $dayTimes = $weekdayTimes[$weekdayKey];
                    if (!empty($dayTimes['start'])) {
                        $startRaw = (string) $dayTimes['start'];
                    }
                    if (!empty($dayTimes['end'])) {
                        $endRaw = (string) $dayTimes['end'];
                    }
                }

                $startFormatted = $this->formatScheduleTimeForPreview($startRaw);
                $endFormatted = $this->formatScheduleTimeForPreview($endRaw);

                $timeRange = '';
                if ($startFormatted !== '' && $endFormatted !== '' && $startFormatted !== $endFormatted) {
                    $timeRange = $startFormatted . ' → ' . $endFormatted;
                } elseif ($startFormatted !== '') {
                    $timeRange = sprintf(__('À partir de %s', 'mj-member'), $startFormatted);
                } elseif ($endFormatted !== '') {
                    $timeRange = $endFormatted;
                }

                if ($timeRange === '') {
                    continue;
                }

                $label = isset($weekdayLabels[$weekdayKey]) ? $weekdayLabels[$weekdayKey] : ucfirst($weekdayKey);
                $label = sanitize_text_field($label);

                if (!isset($timeGroups[$timeRange])) {
                    $timeGroups[$timeRange] = array(
                        'time' => $timeRange,
                        'days' => array(),
                    );
                }

                $timeGroups[$timeRange]['days'][] = $label;
            }

            foreach ($timeGroups as $groupEntry) {
                if (empty($groupEntry['days'])) {
                    continue;
                }

                $daysLabel = $this->formatScheduleDaysLabel($groupEntry['days']);
                $timeLabel = isset($groupEntry['time']) ? (string) $groupEntry['time'] : '';

                if ($daysLabel === '' && $timeLabel === '') {
                    continue;
                }

                $result['entries'][] = array(
                    'label' => $daysLabel,
                    'time' => sanitize_text_field($timeLabel),
                );

                if (count($result['entries']) >= 6) {
                    break;
                }
            }

            return $result;
        }

        if ($frequency === 'monthly') {
            $ordinalLabels = array(
                'first' => __('1er', 'mj-member'),
                'second' => __('2ème', 'mj-member'),
                'third' => __('3ème', 'mj-member'),
                'fourth' => __('4ème', 'mj-member'),
                'last' => __('Dernier', 'mj-member'),
            );

            $ordinal = isset($payload['ordinal']) ? sanitize_key((string) $payload['ordinal']) : '';
            $weekday = isset($payload['weekday']) ? sanitize_key((string) $payload['weekday']) : '';

            $ordinalLabel = isset($ordinalLabels[$ordinal]) ? $ordinalLabels[$ordinal] : $ordinal;
            $weekdayLabel = isset($weekdayLabels[$weekday]) ? $weekdayLabels[$weekday] : $weekday;

            $summary = trim(implode(' ', array_filter(array($ordinalLabel, $weekdayLabel, __('du mois', 'mj-member')))));

            $startFormatted = $this->formatScheduleTimeForPreview(isset($payload['start_time']) ? (string) $payload['start_time'] : '');
            $endFormatted = $this->formatScheduleTimeForPreview(isset($payload['end_time']) ? (string) $payload['end_time'] : '');

            $timeRange = '';
            if ($startFormatted !== '' && $endFormatted !== '' && $startFormatted !== $endFormatted) {
                $timeRange = $startFormatted . ' → ' . $endFormatted;
            } elseif ($startFormatted !== '') {
                $timeRange = sprintf(__('À partir de %s', 'mj-member'), $startFormatted);
            } elseif ($endFormatted !== '') {
                $timeRange = $endFormatted;
            }

            if ($summary !== '') {
                $result['entries'][] = array(
                    'label' => sanitize_text_field($summary),
                    'time' => sanitize_text_field($timeRange),
                );
            }

            return $result;
        }

        return $result;
    }

    /**
     * Format a schedule time for preview.
     */
    private function formatScheduleTimeForPreview(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (preg_match('/^\d{1,2}:\d{2}$/', $value)) {
            $value .= ':00';
        } elseif (!preg_match('/^\d{1,2}:\d{2}:\d{2}$/', $value)) {
            return '';
        }

        try {
            $datetime = new DateTimeImmutable('1970-01-01 ' . $value, $this->timezone);
        } catch (\Exception $exception) {
            return '';
        }

        $timeFormat = get_option('time_format', 'H:i');
        return wp_date($timeFormat, $datetime->getTimestamp(), $this->timezone);
    }

    /**
     * Humanize a list of weekday labels.
     *
     * @param array<int,string> $days
     */
    private function formatScheduleDaysLabel(array $days): string
    {
        $days = array_values(array_filter(array_map('trim', $days)));
        if (empty($days)) {
            return '';
        }

        if (count($days) === 1) {
            return sanitize_text_field($days[0]);
        }

        if (count($days) === 2) {
            return sanitize_text_field($days[0] . ' ' . __('et', 'mj-member') . ' ' . $days[1]);
        }

        $last = array_pop($days);
        $joined = implode(', ', $days) . ' ' . __('et', 'mj-member') . ' ' . $last;

        return sanitize_text_field($joined);
    }

    /**
     * Format the occurrence time label.
     */
    private function formatOccurrenceTimeLabel(DateTimeImmutable $start, ?DateTimeImmutable $end = null): string
    {
        $timeFormat = get_option('time_format', 'H:i');
        $startLabel = $this->normalizeTimeLabel(wp_date($timeFormat, $start->getTimestamp(), $this->timezone));

        if ($startLabel === '') {
            return '';
        }

        if ($end instanceof DateTimeImmutable) {
            $endLabel = $this->normalizeTimeLabel(wp_date($timeFormat, $end->getTimestamp(), $this->timezone));
            if ($endLabel !== '' && $endLabel !== $startLabel) {
                return $startLabel . ' → ' . $endLabel;
            }
        }

        return sprintf(__('À partir de %s', 'mj-member'), $startLabel);
    }

    /**
     * Normalize a time label while keeping localisation artefacts in check.
     */
    private function normalizeTimeLabel(string $label): string
    {
        $label = trim($label);
        if ($label === '') {
            return '';
        }

        $label = preg_replace('/\s+/u', ' ', $label) ?? '';
        $label = preg_replace('/\s*min$/u', '', $label) ?? '';
        $label = preg_replace('/\s*h\s*/u', 'h', $label) ?? '';

        return trim($label);
    }

    /**
     * Normalize a hex color to the #RRGGBB format.
     */
    private function normalizeHexColorValue($value): string
    {
        $candidate = sanitize_hex_color($value);
        if (!is_string($candidate) || $candidate === '') {
            return '';
        }

        if (strlen($candidate) === 4) {
            $candidate = '#' . $candidate[1] . $candidate[1] . $candidate[2] . $candidate[2] . $candidate[3] . $candidate[3];
        }

        return strtoupper($candidate);
    }

    /**
     * Build the palette associated with an event.
     *
     * @param array<string,string> $typeColors
     * @return array<string,string>
     */
    private function buildEventPalette($accentColor, string $typeKey, array $typeColors): array
    {
        $accent = $this->normalizeHexColorValue($accentColor);
        if ($accent === '' && $typeKey !== '' && isset($typeColors[$typeKey])) {
            $accent = $this->normalizeHexColorValue($typeColors[$typeKey]);
        }

        if ($accent === '') {
            $accent = '#2563EB';
        }

        $contrast = $this->pickContrastColor($accent);

        return array(
            'base' => $accent,
            'contrast' => $contrast,
            'surface' => $this->mixHexColors($accent, '#FFFFFF', 0.86),
            'border' => $this->mixHexColors($accent, '#FFFFFF', 0.7),
            'pill_bg' => $this->mixHexColors($accent, '#FFFFFF', 0.82),
            'pill_text' => $accent,
            'thumb_bg' => $this->mixHexColors($accent, '#FFFFFF', 0.9),
            'highlight' => $this->mixHexColors($accent, '#FFFFFF', 0.78),
            'range_bg' => $this->mixHexColors($accent, '#FFFFFF', 0.75),
            'range_border' => $this->mixHexColors($accent, '#FFFFFF', 0.55),
        );
    }

    /**
     * Predefined palette for closure entries.
     */
    private function buildClosurePalette(): array
    {
        return $this->buildEventPalette('#EF4444', 'closure', array('closure' => '#EF4444'));
    }

    /**
     * Build responsive cover sources depending on the available media.
     *
     * @return array<string,string>
     */
    private function buildCoverSources(int $coverId, string $fallbackLarge = '', string $fallbackMedium = ''): array
    {
        $sanitizeUrl = static function ($value) {
            $value = is_string($value) ? trim($value) : '';
            if ($value === '') {
                return '';
            }

            return esc_url_raw($value);
        };

        $fallbackLarge = $sanitizeUrl($fallbackLarge);
        $fallbackMedium = $sanitizeUrl($fallbackMedium);

        $sources = array(
            'desktop' => '',
            'tablet' => '',
            'mobile' => '',
            'fallback' => '',
        );

        if ($coverId > 0 && function_exists('wp_attachment_is_image') && wp_attachment_is_image($coverId)) {
            $desktop = wp_get_attachment_image_src($coverId, 'large');
            $tablet = wp_get_attachment_image_src($coverId, 'medium_large');
            if (!is_array($tablet)) {
                $tablet = wp_get_attachment_image_src($coverId, 'medium');
            }
            $mobile = wp_get_attachment_image_src($coverId, 'medium');
            if (!is_array($mobile)) {
                $mobile = wp_get_attachment_image_src($coverId, 'thumbnail');
            }
            $thumbnail = wp_get_attachment_image_src($coverId, 'thumbnail');

            if (is_array($desktop) && !empty($desktop[0])) {
                $sources['desktop'] = esc_url_raw($desktop[0]);
            }
            if (is_array($tablet) && !empty($tablet[0])) {
                $sources['tablet'] = esc_url_raw($tablet[0]);
            }
            if (is_array($mobile) && !empty($mobile[0])) {
                $sources['mobile'] = esc_url_raw($mobile[0]);
            } elseif (is_array($thumbnail) && !empty($thumbnail[0])) {
                $sources['mobile'] = esc_url_raw($thumbnail[0]);
            }
        }

        $fallback = $fallbackLarge !== '' ? $fallbackLarge : $fallbackMedium;
        if ($sources['desktop'] === '') {
            $sources['desktop'] = $fallback;
        }
        if ($sources['tablet'] === '') {
            $sources['tablet'] = $fallback !== '' ? $fallback : $sources['desktop'];
        }
        if ($sources['mobile'] === '') {
            $sources['mobile'] = $fallbackMedium !== '' ? $fallbackMedium : ($fallback !== '' ? $fallback : $sources['tablet']);
        }

        if ($sources['fallback'] === '' && $fallback !== '') {
            $sources['fallback'] = $fallback;
        } elseif ($sources['fallback'] === '') {
            $sources['fallback'] = $sources['desktop'] !== '' ? $sources['desktop'] : ($sources['tablet'] !== '' ? $sources['tablet'] : $sources['mobile']);
        }

        foreach ($sources as $key => $value) {
            if ($value === '') {
                continue;
            }
            $sources[$key] = esc_url($value);
        }

        return $sources;
    }

    /**
     * Convert an hex color to RGB components.
     *
     * @return array<int,int>|null
     */
    private function hexToRgb(string $value): ?array
    {
        $normalized = $this->normalizeHexColorValue($value);
        if ($normalized === '') {
            return null;
        }

        $normalized = ltrim($normalized, '#');
        if (strlen($normalized) !== 6) {
            return null;
        }

        return array(
            hexdec(substr($normalized, 0, 2)),
            hexdec(substr($normalized, 2, 2)),
            hexdec(substr($normalized, 4, 2)),
        );
    }

    /**
     * Blend two colors together.
     */
    private function mixHexColors(string $base, string $blend, float $ratio): string
    {
        $baseRgb = $this->hexToRgb($base);
        $blendRgb = $this->hexToRgb($blend);
        if ($baseRgb === null || $blendRgb === null) {
            return $this->normalizeHexColorValue($base);
        }

        $ratio = max(0.0, min(1.0, $ratio));

        $mixed = array(
            (int) round($baseRgb[0] * (1 - $ratio) + $blendRgb[0] * $ratio),
            (int) round($baseRgb[1] * (1 - $ratio) + $blendRgb[1] * $ratio),
            (int) round($baseRgb[2] * (1 - $ratio) + $blendRgb[2] * $ratio),
        );

        return sprintf('#%02X%02X%02X', $mixed[0], $mixed[1], $mixed[2]);
    }

    /**
     * Choose an accessible contrast color (dark or light) for the given accent.
     */
    private function pickContrastColor(string $hex): string
    {
        $rgb = $this->hexToRgb($hex);
        if ($rgb === null) {
            return '#FFFFFF';
        }

        $luminance = (0.2126 * $rgb[0]) + (0.7152 * $rgb[1]) + (0.0722 * $rgb[2]);

        return $luminance >= 150 ? '#0F172A' : '#FFFFFF';
    }
}
