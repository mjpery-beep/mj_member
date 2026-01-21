<?php

namespace Mj\Member\Classes\View\EventPage;

use DateTime;
use Mj\Member\Classes\Crud\MjEventAnimateurs;
use Mj\Member\Classes\Crud\MjEventAttendance;
use Mj\Member\Classes\Crud\MjEventLocations;
use Mj\Member\Classes\Crud\MjEventOccurrences;
use Mj\Member\Classes\Crud\MjEventRegistrations;
use Mj\Member\Classes\Crud\MjEvents;
use Mj\Member\Classes\Crud\MjMembers;
use Mj\Member\Classes\MjEventSchedule;
use Mj\Member\Classes\Value\EventData;
use Mj\Member\Classes\Value\EventLocationData;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * EventPageModel - Modèle de données pour la page événement
 * 
 * Centralise toutes les données métier normalisées nécessaires à l'affichage.
 * Utilise les classes CRUD existantes et expose une API propre.
 */
final class EventPageModel
{
    /**
     * @var array<string, mixed>
     */
    private array $context;

    /**
     * @var EventData|null
     */
    private ?EventData $event = null;

    /**
     * @var EventLocationData|null
     */
    private ?EventLocationData $location = null;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $data = null;

    /**
     * @param array<string, mixed> $context
     */
    public function __construct(array $context)
    {
        $this->context = $context;
        $this->hydrate();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        if ($this->data === null) {
            $this->hydrate();
        }

        return $this->data ?? array();
    }

    /**
     * Hydrate le modèle à partir du contexte
     */
    private function hydrate(): void
    {
        $this->hydrateEvent();
        $this->hydrateLocation();

        $eventArray = $this->event ? $this->event->toArray() : array();

        $this->data = array(
            'event' => $this->buildEventData($eventArray),
            'schedule' => $this->buildScheduleData($eventArray),
            'location' => $this->buildLocationData(),
            'registration' => $this->buildRegistrationData($eventArray),
            'animateurs' => $this->buildAnimateursData($eventArray),
            'photos' => $this->buildPhotosData($eventArray),
            'user' => $this->buildUserData(),
            'meta' => $this->buildMetaData($eventArray),
        );
    }

    /**
     * Hydrate l'événement depuis le contexte ou la BDD
     */
    private function hydrateEvent(): void
    {
        // Priorité au contexte s'il contient déjà un EventData ou un tableau event
        if (isset($this->context['event_data']) && $this->context['event_data'] instanceof EventData) {
            $this->event = $this->context['event_data'];
            return;
        }

        if (isset($this->context['event']) && is_array($this->context['event'])) {
            $this->event = EventData::fromArray($this->context['event']);
            return;
        }

        // Sinon charger depuis l'ID
        $eventId = $this->extractEventId();
        if ($eventId > 0) {
            $eventRow = MjEvents::find($eventId);
            if ($eventRow instanceof EventData) {
                $this->event = $eventRow;
            } elseif (is_object($eventRow) || is_array($eventRow)) {
                $this->event = EventData::fromRow($eventRow);
            }
        }
    }

    /**
     * Hydrate le lieu depuis l'événement
     */
    private function hydrateLocation(): void
    {
        if (isset($this->context['location']) && is_array($this->context['location'])) {
            $this->location = EventLocationData::fromArray($this->context['location']);
            return;
        }

        if ($this->event === null) {
            return;
        }

        $locationId = (int) $this->event->get('location_id', 0);
        if ($locationId <= 0) {
            return;
        }

        $locationRow = MjEventLocations::find($locationId);
        if ($locationRow instanceof EventLocationData) {
            $this->location = $locationRow;
        } elseif ($locationRow) {
            $this->location = EventLocationData::fromRow($locationRow);
        }
    }

    /**
     * @return int
     */
    private function extractEventId(): int
    {
        if (isset($this->context['event_id'])) {
            return (int) $this->context['event_id'];
        }

        if (isset($this->context['event']['id'])) {
            return (int) $this->context['event']['id'];
        }

        return 0;
    }

    /**
     * @param array<string, mixed> $eventArray
     * @return array<string, mixed>
     */
    private function buildEventData(array $eventArray): array
    {
        $coverId = isset($eventArray['cover_id']) ? (int) $eventArray['cover_id'] : 0;
        $coverUrl = '';
        $coverThumb = '';

        if ($coverId > 0) {
            $coverUrl = wp_get_attachment_image_url($coverId, 'large') ?: '';
            $coverThumb = wp_get_attachment_image_url($coverId, 'medium') ?: '';
        }

        // Fallback sur l'article lié
        if ($coverUrl === '' && isset($eventArray['article_id']) && (int) $eventArray['article_id'] > 0) {
            $articleThumbId = (int) get_post_thumbnail_id((int) $eventArray['article_id']);
            if ($articleThumbId > 0) {
                $coverUrl = wp_get_attachment_image_url($articleThumbId, 'large') ?: '';
                $coverThumb = wp_get_attachment_image_url($articleThumbId, 'medium') ?: '';
            }
        }

        $typeLabels = MjEvents::get_type_labels();
        $typeKey = isset($eventArray['type']) ? sanitize_key($eventArray['type']) : '';
        $typeLabel = isset($typeLabels[$typeKey]) ? $typeLabels[$typeKey] : ucfirst($typeKey);

        $statusLabels = MjEvents::get_status_labels();
        $statusKey = isset($eventArray['status']) ? sanitize_key($eventArray['status']) : '';
        $statusLabel = isset($statusLabels[$statusKey]) ? $statusLabels[$statusKey] : '';

        $articleId = isset($eventArray['article_id']) ? (int) $eventArray['article_id'] : 0;
        $articleUrl = $articleId > 0 ? get_permalink($articleId) : '';
        $articleTitle = $articleId > 0 ? get_the_title($articleId) : '';

        return array(
            'id' => isset($eventArray['id']) ? (int) $eventArray['id'] : 0,
            'title' => isset($eventArray['title']) ? (string) $eventArray['title'] : '',
            'slug' => isset($eventArray['slug']) ? (string) $eventArray['slug'] : '',
            'description' => isset($eventArray['description']) ? (string) $eventArray['description'] : '',
            'type' => $typeKey,
            'type_label' => $typeLabel,
            'status' => $statusKey,
            'status_label' => $statusLabel,
            'is_internal' => $typeKey === MjEvents::TYPE_INTERNE,
            'cover_url' => $coverUrl,
            'cover_thumb' => $coverThumb,
            'accent_color' => isset($eventArray['accent_color']) ? (string) $eventArray['accent_color'] : '',
            'article_id' => $articleId,
            'article_url' => $articleUrl ?: '',
            'article_title' => $articleTitle ?: '',
            'has_article' => $articleId > 0 && $articleUrl !== false,
        );
    }

    /**
     * @param array<string, mixed> $eventArray
     * @return array<string, mixed>
     */
    private function buildScheduleData(array $eventArray): array
    {
        $mode = isset($eventArray['schedule_mode']) ? (string) $eventArray['schedule_mode'] : 'single';
        $selectionMode = isset($eventArray['occurrence_selection_mode']) 
            ? (string) $eventArray['occurrence_selection_mode'] 
            : 'all';

        $dateDebut = isset($eventArray['date_debut']) ? (string) $eventArray['date_debut'] : '';
        $dateFin = isset($eventArray['date_fin']) ? (string) $eventArray['date_fin'] : '';

        $schedulePayload = array();
        if (isset($eventArray['schedule_payload'])) {
            $payloadRaw = $eventArray['schedule_payload'];
            if (is_string($payloadRaw) && $payloadRaw !== '') {
                $decodedPayload = json_decode($payloadRaw, true);
                if (is_array($decodedPayload)) {
                    $schedulePayload = $decodedPayload;
                }
            } elseif (is_array($payloadRaw)) {
                $schedulePayload = $payloadRaw;
            }
        }

        $occurrenceSummary = '';
        if (isset($schedulePayload['occurrence_summary']) && !is_array($schedulePayload['occurrence_summary'])) {
            $occurrenceSummary = trim((string) $schedulePayload['occurrence_summary']);
            if ($occurrenceSummary !== '') {
                $occurrenceSummary = wp_strip_all_tags($occurrenceSummary);
            }
        }

        // MjEventSchedule utilise des méthodes statiques
        $occurrences = MjEventSchedule::get_occurrences($eventArray);
        $occurrences = $this->filterActiveOccurrences($occurrences);
        $scheduleSummary = $occurrenceSummary !== ''
            ? $occurrenceSummary
            : $this->buildScheduleSummary($mode, count($occurrences));

        $now = current_time('timestamp');
        $timezone = $this->getSiteTimezone();
        $occurrenceItems = array();
        $dateFormatOption = get_option('date_format', 'd/m/Y');

        foreach ($occurrences as $occ) {
            $timestamp = isset($occ['timestamp']) ? (int) $occ['timestamp'] : 0;
            $isPast = $timestamp > 0 && $timestamp < $now;
            $isToday = $timestamp > 0 && date('Y-m-d', $timestamp) === date('Y-m-d', $now);

            // Générer le label si absent
            $label = $this->buildOccurrenceLabel($occ, $mode);
            if ($label === '' && $timestamp > 0) {
                $label = wp_date($dateFormatOption, $timestamp, $timezone);
            }

            // Extraire la date depuis start ou timestamp
            $dateStr = '';
            if (isset($occ['start']) && !empty($occ['start'])) {
                $dateStr = substr((string) $occ['start'], 0, 10); // Y-m-d
            } elseif ($timestamp > 0) {
                $dateStr = date('Y-m-d', $timestamp);
            }

            // Labels localisés pour l'affichage mini-agenda
            $dayName = $timestamp > 0 ? wp_date('D', $timestamp, $timezone) : '';
            $dayNum = $timestamp > 0 ? wp_date('d', $timestamp, $timezone) : '';
            $monthName = $timestamp > 0 ? wp_date('M', $timestamp, $timezone) : '';

            $occurrenceItems[] = array(
                'date' => $dateStr,
                'label' => $label,
                'start' => isset($occ['start']) ? (string) $occ['start'] : '',
                'end' => isset($occ['end']) ? (string) $occ['end'] : '',
                'timestamp' => $timestamp,
                'is_past' => $isPast,
                'is_today' => $isToday,
                'day_name' => $dayName,
                'day_num' => $dayNum,
                'month_name' => $monthName,
            );
        }

        $allowsSelection = in_array($selectionMode, array('choose', 'member_choice'), true) && count($occurrenceItems) > 1;

        // Ne montrer le calendrier que si mode récurrent/série ET plusieurs occurrences
        $showCalendar = in_array($mode, array('recurring', 'series'), true) && count($occurrenceItems) > 1;

        // Extraire les données de récurrence hebdomadaire depuis schedule_payload
        $weeklySchedule = $this->buildWeeklySchedule($eventArray);

        $nextOccurrence = $this->findNextOccurrence($occurrenceItems);
        $displayLabel = $this->buildDisplayLabel($dateDebut, $dateFin, $mode, $nextOccurrence, $occurrenceItems);

        return array(
            'mode' => $mode,
            'selection_mode' => $selectionMode,
            'allows_selection' => $allowsSelection,
            'date_debut' => $dateDebut,
            'date_fin' => $dateFin,
            'display_label' => $displayLabel,
            'schedule_summary' => $scheduleSummary,
            'occurrence_schedule_summary' => $occurrenceSummary,
            'occurrences' => $occurrenceItems,
            'has_multiple_occurrences' => $showCalendar,
            'next_occurrence' => $nextOccurrence,
            'weekly_schedule' => $weeklySchedule,
        );
    }

    /**
     * Construit le label d'affichage pour les dates
     *
     * @param string $dateDebut
    * @param string $dateFin
    * @param string $mode
    * @param array<string,mixed>|null $nextOccurrence
    * @param array<int,array<string,mixed>> $occurrenceItems
     * @return string
     */
    private function buildDisplayLabel(string $dateDebut, string $dateFin, string $mode, ?array $nextOccurrence, array $occurrenceItems): string
    {
        if ($mode === 'fixed') {
            $candidate = $nextOccurrence;
            if ($candidate === null && !empty($occurrenceItems)) {
                $candidate = $occurrenceItems[0];
            }

            if (is_array($candidate) && !empty($candidate['start'])) {
                $start = $this->parseEventDate((string) $candidate['start']);
                if ($start instanceof DateTime) {
                    $end = !empty($candidate['end']) ? $this->parseEventDate((string) $candidate['end']) : null;
                    if (!$end instanceof DateTime || $end <= $start) {
                        $end = (clone $start)->modify('+1 hour');
                    }

                    return $this->formatDateRangeLabel($start, $end, true);
                }
            }
        }

        $start = $this->parseEventDate($dateDebut);
        if (!$start instanceof DateTime) {
            return '';
        }

        $end = $this->parseEventDate($dateFin);
        if (!$end instanceof DateTime || $end <= $start) {
            $end = (clone $start)->modify('+1 hour');
        }

        return $this->formatDateRangeLabel($start, $end, false);
    }

    /**
     * Formate un libellé date/heure harmonisé
     */
    private function formatDateRangeLabel(DateTime $start, DateTime $end, bool $forceTime): string
    {
        $timezone = $this->getSiteTimezone();
        $dateFormat = 'l j F Y';
        $timeFormat = get_option('time_format', 'H:i');
        $startLabel = wp_date($dateFormat, $start->getTimestamp(), $timezone);

        if ($start->format('Y-m-d') === $end->format('Y-m-d')) {
            $startTime = wp_date($timeFormat, $start->getTimestamp(), $timezone);
            $endTime = wp_date($timeFormat, $end->getTimestamp(), $timezone);

            if (!$forceTime && $startTime === '00:00' && $endTime === '00:00') {
                return $startLabel;
            }

            if ($startTime === $endTime) {
                return $startLabel . ' · ' . $startTime;
            }

            return $startLabel . ' · ' . $startTime . ' → ' . $endTime;
        }

        return $startLabel . ' - ' . wp_date($dateFormat, $end->getTimestamp(), $timezone);
    }

    /**
     * Construit un libellé pour une occurrence
     *
     * @param array<string,mixed> $occ
     */
    private function buildOccurrenceLabel(array $occ, string $mode): string
    {
        $startRaw = isset($occ['start']) ? (string) $occ['start'] : '';
        $endRaw = isset($occ['end']) ? (string) $occ['end'] : '';

        $start = $this->parseEventDate($startRaw);
        if (!$start instanceof DateTime) {
            return isset($occ['label']) ? (string) $occ['label'] : '';
        }

        $end = $this->parseEventDate($endRaw);
        if (!$end instanceof DateTime || $end <= $start) {
            $end = (clone $start)->modify('+1 hour');
        }

        $forceTime = ($mode === 'fixed');
        return $this->formatDateRangeLabel($start, $end, $forceTime);
    }

    /**
     * @param array<int,array<string,mixed>> $occurrences
     * @return array<int,array<string,mixed>>
     */
    private function filterActiveOccurrences(array $occurrences): array
    {
        if (empty($occurrences)) {
            return array();
        }

        $filtered = array();

        foreach ($occurrences as $occurrence) {
            if (!is_array($occurrence)) {
                continue;
            }

            if ($this->isCancelledOccurrence($occurrence)) {
                continue;
            }

            $filtered[] = $occurrence;
        }

        return $filtered;
    }

    /**
     * @param array<string,mixed> $occurrence
     */
    private function isCancelledOccurrence(array $occurrence): bool
    {
        if (!empty($occurrence['is_cancelled'])) {
            return true;
        }

        $statusCandidates = array();

        if (isset($occurrence['status'])) {
            $statusCandidates[] = $occurrence['status'];
        }

        if (isset($occurrence['state'])) {
            $statusCandidates[] = $occurrence['state'];
        }

        if (isset($occurrence['meta']) && is_array($occurrence['meta'])) {
            if (isset($occurrence['meta']['status'])) {
                $statusCandidates[] = $occurrence['meta']['status'];
            }

            if (isset($occurrence['meta']['state'])) {
                $statusCandidates[] = $occurrence['meta']['state'];
            }

            if (!empty($occurrence['meta']['is_cancelled'])) {
                return true;
            }

            if (!empty($occurrence['meta']['cancelled'])) {
                return true;
            }

            if (!empty($occurrence['meta']['excluded']) || !empty($occurrence['meta']['exclude'])) {
                return true;
            }
        }

        foreach ($statusCandidates as $statusCandidate) {
            $statusKey = sanitize_key((string) $statusCandidate);
            if ($statusKey === '') {
                continue;
            }

            if (in_array($statusKey, array('cancelled', 'canceled', 'annule', 'annulee', 'excluded', 'exclude', 'skipped', 'skip'), true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Retourne le fuseau horaire configuré dans WordPress.
     */
    private function getSiteTimezone(): \DateTimeZone
    {
        $timezone = wp_timezone();
        if (!($timezone instanceof \DateTimeZone)) {
            $timezone = new \DateTimeZone('UTC');
        }

        return $timezone;
    }

    /**
     * Retourne une DateTime basée sur le fuseau WordPress
     */
    private function parseEventDate(string $value): ?DateTime
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $timezone = $this->getSiteTimezone();
        $formats = array('Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d');

        foreach ($formats as $format) {
            $date = DateTime::createFromFormat($format, $value, $timezone);
            if ($date instanceof DateTime) {
                return $date;
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

    /**
     * Construit le résumé du planning
     *
     * @param string $mode
     * @param int $occurrenceCount
     * @return string
     */
    private function buildScheduleSummary(string $mode, int $occurrenceCount): string
    {
        switch ($mode) {
            case 'recurring':
                return sprintf(_n('%d séance', '%d séances', $occurrenceCount, 'mj-member'), $occurrenceCount);
            case 'range':
                return __('Période continue', 'mj-member');
            case 'series':
                return sprintf(_n('%d date', '%d dates', $occurrenceCount, 'mj-member'), $occurrenceCount);
            default:
                return __('Date unique', 'mj-member');
        }
    }

    /**
     * Construit les données de planning hebdomadaire avec plages horaires par jour
     *
     * @param array<string, mixed> $eventArray
     * @return array<string, mixed>
     */
    private function buildWeeklySchedule(array $eventArray): array
    {
        $mode = isset($eventArray['schedule_mode']) ? (string) $eventArray['schedule_mode'] : '';
        
        // Décoder le payload
        $payloadRaw = isset($eventArray['schedule_payload']) ? $eventArray['schedule_payload'] : '';
        $payload = array();
        
        if (is_string($payloadRaw) && $payloadRaw !== '') {
            $decoded = json_decode($payloadRaw, true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        } elseif (is_array($payloadRaw)) {
            $payload = $payloadRaw;
        }

        $generatorPlan = $this->extractOccurrenceGeneratorPlan($payload);
        // Option d'affichage des dates de début/fin (true = masquer)
        $showDateRange = isset($payload['show_date_range']) ? !empty($payload['show_date_range']) : false;

        // Série de dates personnalisées
        if ($mode === 'series') {
            return $this->buildSeriesSchedule($payload, $showDateRange);
        }
        
        // Seulement pour les récurrences
        if ($mode !== 'recurring') {
            return $this->buildEmptyWeeklySchedule($showDateRange);
        }

        if (!empty($generatorPlan)) {
            $planMode = isset($generatorPlan['mode']) ? (string) $generatorPlan['mode'] : 'weekly';

            if ($planMode === 'monthly') {
                return $this->buildMonthlyScheduleFromGenerator($generatorPlan, $showDateRange);
            }

            return $this->buildWeeklyScheduleFromGenerator($generatorPlan, $showDateRange);
        }

        $frequency = isset($payload['frequency']) ? (string) $payload['frequency'] : '';

        // Labels des jours de la semaine
        $weekdayLabels = array(
            'monday' => __('Lundi', 'mj-member'),
            'tuesday' => __('Mardi', 'mj-member'),
            'wednesday' => __('Mercredi', 'mj-member'),
            'thursday' => __('Jeudi', 'mj-member'),
            'friday' => __('Vendredi', 'mj-member'),
            'saturday' => __('Samedi', 'mj-member'),
            'sunday' => __('Dimanche', 'mj-member'),
        );

        // Récurrence mensuelle
        if ($frequency === 'monthly') {
            $ordinal = isset($payload['ordinal']) ? (string) $payload['ordinal'] : '';
            $weekday = isset($payload['weekday']) ? (string) $payload['weekday'] : '';
            $startTime = isset($payload['start_time']) ? (string) $payload['start_time'] : '';
            $endTime = isset($payload['end_time']) ? (string) $payload['end_time'] : '';

            $ordinalLabels = array(
                'first' => __('1er', 'mj-member'),
                'second' => __('2ème', 'mj-member'),
                'third' => __('3ème', 'mj-member'),
                'fourth' => __('4ème', 'mj-member'),
                'last' => __('Dernier', 'mj-member'),
            );

            $ordinalLabel = isset($ordinalLabels[$ordinal]) ? $ordinalLabels[$ordinal] : $ordinal;
            $weekdayLabel = isset($weekdayLabels[$weekday]) ? $weekdayLabels[$weekday] : $weekday;

            $startFormatted = $this->formatTimeForDisplay($startTime);
            $endFormatted = $this->formatTimeForDisplay($endTime);

            $timeRange = '';
            if ($startFormatted !== '' && $endFormatted !== '') {
                $timeRange = $startFormatted . ' - ' . $endFormatted;
            } elseif ($startFormatted !== '') {
                $timeRange = __('à partir de', 'mj-member') . ' ' . $startFormatted;
            }

            // Ex: "1er Samedi du mois"
            $monthlyLabel = sprintf('%s %s %s', $ordinalLabel, $weekdayLabel, __('du mois', 'mj-member'));

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
        }
        
        // Seulement pour les récurrences hebdomadaires
        if ($frequency !== 'weekly') {
            return $this->buildEmptyWeeklySchedule($showDateRange);
        }

        $weekdays = isset($payload['weekdays']) && is_array($payload['weekdays']) ? $payload['weekdays'] : array();
        $weekdayTimes = isset($payload['weekday_times']) && is_array($payload['weekday_times']) ? $payload['weekday_times'] : array();
        $defaultStartTime = isset($payload['start_time']) ? (string) $payload['start_time'] : '';
        $defaultEndTime = isset($payload['end_time']) ? (string) $payload['end_time'] : '';

        $days = array();
        foreach ($weekdays as $dayKey) {
            $dayKey = sanitize_key($dayKey);
            if (!isset($weekdayLabels[$dayKey])) {
                continue;
            }

            // Récupérer les heures spécifiques ou utiliser les valeurs par défaut
            $startTime = '';
            $endTime = '';

            if (isset($weekdayTimes[$dayKey])) {
                $dayTimes = $weekdayTimes[$dayKey];
                $startTime = isset($dayTimes['start']) && $dayTimes['start'] !== '' 
                    ? (string) $dayTimes['start'] 
                    : $defaultStartTime;
                $endTime = isset($dayTimes['end']) && $dayTimes['end'] !== '' 
                    ? (string) $dayTimes['end'] 
                    : $defaultEndTime;
            } else {
                $startTime = $defaultStartTime;
                $endTime = $defaultEndTime;
            }

            // Formater les heures pour l'affichage (HH:MM -> HHhMM)
            $startFormatted = $this->formatTimeForDisplay($startTime);
            $endFormatted = $this->formatTimeForDisplay($endTime);

            $timeRange = '';
            if ($startFormatted !== '' && $endFormatted !== '') {
                $timeRange = $startFormatted . ' - ' . $endFormatted;
            } elseif ($startFormatted !== '') {
                $timeRange = __('à partir de', 'mj-member') . ' ' . $startFormatted;
            }

            $days[] = array(
                'key' => $dayKey,
                'label' => $weekdayLabels[$dayKey],
                'start_time' => $startTime,
                'end_time' => $endTime,
                'start_formatted' => $startFormatted,
                'end_formatted' => $endFormatted,
                'time_range' => $timeRange,
            );
        }

        return array(
            'is_weekly' => count($days) > 0,
            'is_monthly' => false,
            'is_series' => false,
            'show_date_range' => $showDateRange,
            'days' => $days,
            'series_items' => array(),
        );
    }

    private function buildEmptyWeeklySchedule(bool $showDateRange): array
    {
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
    }

    /**
     * @param array<string,mixed> $plan
     */
    private function buildWeeklyScheduleFromGenerator(array $plan, bool $showDateRange): array
    {
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

            $startFormatted = $this->formatTimeForDisplay($startTime);
            $endFormatted = $this->formatTimeForDisplay($endTime);

            $timeRange = '';
            if ($startFormatted !== '' && $endFormatted !== '') {
                $timeRange = $startFormatted . ' - ' . $endFormatted;
            } elseif ($startFormatted !== '') {
                $timeRange = __('à partir de', 'mj-member') . ' ' . $startFormatted;
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
            return $this->buildEmptyWeeklySchedule($showDateRange);
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
    }

    /**
     * @param array<string,mixed> $plan
     */
    private function buildMonthlyScheduleFromGenerator(array $plan, bool $showDateRange): array
    {
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

        $startFormatted = $this->formatTimeForDisplay($defaultStart);
        $endFormatted = $this->formatTimeForDisplay($defaultEnd);

        $timeRange = '';
        if ($startFormatted !== '' && $endFormatted !== '') {
            $timeRange = $startFormatted . ' - ' . $endFormatted;
        } elseif ($startFormatted !== '') {
            $timeRange = __('à partir de', 'mj-member') . ' ' . $startFormatted;
        } elseif ($endFormatted !== '') {
            $timeRange = $endFormatted;
        }

        $monthlyLabel = trim(implode(' ', array_filter(array($ordinalLabel, $weekdayLabel, __('du mois', 'mj-member')))));

        if ($monthlyLabel === '') {
            return $this->buildEmptyWeeklySchedule($showDateRange);
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
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function extractOccurrenceGeneratorPlan(array $payload): array
    {
        $candidates = array();

        if (isset($payload['occurrence_generator']) && is_array($payload['occurrence_generator'])) {
            $candidates[] = $payload['occurrence_generator'];
        }

        if (isset($payload['occurrenceGenerator']) && is_array($payload['occurrenceGenerator'])) {
            $candidates[] = $payload['occurrenceGenerator'];
        }

        foreach ($candidates as $candidate) {
            $plan = $this->sanitizeOccurrenceGeneratorPlan($candidate);
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
    }

    /**
     * @param array<string,mixed> $input
     */
    private function sanitizeOccurrenceGeneratorPlan(array $input): array
    {
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
            $candidate = $this->sanitizeDateValue($input[$key]);
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
            $candidate = $this->sanitizeDateValue($input[$key]);
            if ($candidate !== '') {
                $endDate = $candidate;
                break;
            }
        }

        $startTime = $this->sanitizeTimeValue(isset($input['startTime']) ? $input['startTime'] : '');
        $endTime = $this->sanitizeTimeValue(isset($input['endTime']) ? $input['endTime'] : '');

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
                $sanitized = $this->sanitizeTimeValue($times['start']);
                if ($sanitized !== '') {
                    $entry['start'] = $sanitized;
                }
            }
            if (isset($times['end'])) {
                $sanitized = $this->sanitizeTimeValue($times['end']);
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
                $explicitStart = $this->toBool($input[$flagKey], $explicitStart);
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
    }

    private function sanitizeTimeValue($value): string
    {
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
    }

    private function sanitizeDateValue($value): string
    {
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
    }

    private function toBool($value, bool $default = false): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $filtered = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($filtered !== null) {
            return $filtered;
        }

        return $default;
    }

    /**
     * Construit les données de planning pour une série de dates
     *
     * @param array<string, mixed> $payload
     * @param bool $showDateRange
     * @return array<string, mixed>
     */
    private function buildSeriesSchedule(array $payload, bool $showDateRange): array
    {
        $items = isset($payload['items']) && is_array($payload['items']) ? $payload['items'] : array();
        
        if (empty($items)) {
            return array(
                'is_weekly' => false,
                'is_monthly' => false,
                'is_series' => false,
                'show_date_range' => $showDateRange,
                'days' => array(),
                'series_items' => array(),
            );
        }

        $seriesItems = array();
        $timezone = $this->getSiteTimezone();
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $dateStr = isset($item['date']) ? (string) $item['date'] : '';
            $startTime = isset($item['start_time']) ? (string) $item['start_time'] : '';
            $endTime = isset($item['end_time']) ? (string) $item['end_time'] : '';

            if (empty($dateStr)) {
                continue;
            }

            // Formater la date
            $timestamp = strtotime($dateStr);
            $dateFormatted = $timestamp ? wp_date('D j M', $timestamp, $timezone) : $dateStr;

            // Formater les heures
            $startFormatted = $this->formatTimeForDisplay($startTime);
            $endFormatted = $this->formatTimeForDisplay($endTime);

            $timeRange = '';
            if ($startFormatted !== '' && $endFormatted !== '') {
                $timeRange = $startFormatted . ' - ' . $endFormatted;
            } elseif ($startFormatted !== '') {
                $timeRange = __('à partir de', 'mj-member') . ' ' . $startFormatted;
            }

            $seriesItems[] = array(
                'date' => $dateStr,
                'date_formatted' => $dateFormatted,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'time_range' => $timeRange,
                'timestamp' => $timestamp,
            );
        }

        // Trier par date
        usort($seriesItems, function($a, $b) {
            return ($a['timestamp'] ?? 0) - ($b['timestamp'] ?? 0);
        });

        return array(
            'is_weekly' => false,
            'is_monthly' => false,
            'is_series' => count($seriesItems) > 0,
            'show_date_range' => $showDateRange,
            'days' => array(),
            'series_items' => $seriesItems,
        );
    }

    /**
     * Formate une heure HH:MM en format lisible (ex: 14:30 -> 14h30)
     *
     * @param string $time
     * @return string
     */
    private function formatTimeForDisplay(string $time): string
    {
        if ($time === '' || strpos($time, ':') === false) {
            return '';
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

    /**
     * @param array<array<string, mixed>> $occurrences
     * @return array<string, mixed>|null
     */
    private function findNextOccurrence(array $occurrences): ?array
    {
        $now = current_time('timestamp');

        foreach ($occurrences as $occ) {
            $timestamp = isset($occ['timestamp']) ? (int) $occ['timestamp'] : 0;
            if ($timestamp >= $now) {
                return $occ;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildLocationData(): array
    {
        if ($this->location === null) {
            return array(
                'has_location' => false,
            );
        }

        $locationArray = $this->location->toArray();

        $addressParts = array_filter(array(
            isset($locationArray['address_line']) ? (string) $locationArray['address_line'] : '',
            trim(sprintf(
                '%s %s',
                isset($locationArray['postal_code']) ? (string) $locationArray['postal_code'] : '',
                isset($locationArray['city']) ? (string) $locationArray['city'] : ''
            )),
        ));

        $address = implode(', ', $addressParts);

        $coverId = isset($locationArray['cover_id']) ? (int) $locationArray['cover_id'] : 0;
        $coverUrl = '';
        if ($coverId > 0) {
            $coverUrl = wp_get_attachment_image_url($coverId, 'medium') ?: '';
        }

        $mapEmbed = MjEventLocations::build_map_embed_src($locationArray);
        $mapLink = '';
        if (!empty($locationArray['map_query'])) {
            $mapLink = 'https://maps.google.com/?q=' . rawurlencode((string) $locationArray['map_query']);
        } elseif ($address !== '') {
            $mapLink = 'https://maps.google.com/?q=' . rawurlencode($address);
        }

        return array(
            'has_location' => true,
            'id' => isset($locationArray['id']) ? (int) $locationArray['id'] : 0,
            'name' => isset($locationArray['name']) ? (string) $locationArray['name'] : '',
            'address' => $address,
            'address_line' => isset($locationArray['address_line']) ? (string) $locationArray['address_line'] : '',
            'postal_code' => isset($locationArray['postal_code']) ? (string) $locationArray['postal_code'] : '',
            'city' => isset($locationArray['city']) ? (string) $locationArray['city'] : '',
            'country' => isset($locationArray['country']) ? (string) $locationArray['country'] : '',
            'cover_url' => $coverUrl,
            'notes' => isset($locationArray['notes']) ? (string) $locationArray['notes'] : '',
            'map_embed' => $mapEmbed ?: '',
            'map_link' => $mapLink,
        );
    }

    /**
     * @param array<string, mixed> $eventArray
     * @return array<string, mixed>
     */
    private function buildRegistrationData(array $eventArray): array
    {
        $eventId = isset($eventArray['id']) ? (int) $eventArray['id'] : 0;

        $isFreeParticipation = !empty($eventArray['free_participation']);
        $requiresValidation = !empty($eventArray['requires_validation']);
        $allowGuardian = !empty($eventArray['allow_guardian_registration']);

        $price = isset($eventArray['prix']) ? (float) $eventArray['prix'] : 0;
        $priceDisplay = $price > 0 ? number_format_i18n($price, 2) . ' €' : __('Gratuit', 'mj-member');

        $capacityTotal = isset($eventArray['capacity_total']) ? (int) $eventArray['capacity_total'] : 0;
        $capacityWaitlist = isset($eventArray['capacity_waitlist']) ? (int) $eventArray['capacity_waitlist'] : 0;

        // Calculer les places restantes
        $registeredCount = 0;
        if ($eventId > 0) {
            $registeredCount = MjEventRegistrations::count(array(
                'event_id' => $eventId,
                'statuses' => array(
                    MjEventRegistrations::STATUS_CONFIRMED,
                    MjEventRegistrations::STATUS_PENDING,
                ),
            ));
        }

        $capacityRemaining = $capacityTotal > 0 ? max(0, $capacityTotal - $registeredCount) : null;

        // Vérifier si les inscriptions sont ouvertes
        $isOpen = $this->checkRegistrationOpen($eventArray, $capacityRemaining);

        // Vérifier si l'événement est passé
        $isPast = $this->checkEventIsPast($eventArray);

        // Participants disponibles pour l'utilisateur courant
        $participants = $this->buildParticipants($eventArray);
        $userReservations = $this->buildUserReservations($eventId, $participants);

        $deadlineRaw = $this->resolveRegistrationDeadline($eventArray);
        $deadlineLabel = '';
        if ($deadlineRaw !== '') {
            $deadlineTs = strtotime($deadlineRaw);
            if ($deadlineTs) {
                $deadlineLabel = wp_date(get_option('date_format', 'd/m/Y'), $deadlineTs, $this->getSiteTimezone());
            }
        }

        return array(
            'is_open' => $isOpen,
            'is_past' => $isPast,
            'is_free_participation' => $isFreeParticipation,
            'requires_validation' => $requiresValidation,
            'requires_login' => !is_user_logged_in(),
            'allow_guardian' => $allowGuardian,
            'payment_required' => $price > 0,
            'price' => $price,
            'price_display' => $priceDisplay,
            'capacity_total' => $capacityTotal,
            'capacity_waitlist' => $capacityWaitlist,
            'capacity_remaining' => $capacityRemaining,
            'registered_count' => $registeredCount,
            'deadline' => $deadlineRaw,
            'deadline_label' => $deadlineLabel,
            'participants' => $participants,
            'user_reservations' => $userReservations,
            'has_reservations' => !empty($userReservations),
        );
    }

    /**
     * Resolve the registration deadline using stored value.
     *
     * @param array<string, mixed> $eventArray
     * @return string
     */
    private function resolveRegistrationDeadline(array $eventArray): string
    {
        $stored = isset($eventArray['date_fin_inscription']) ? (string) $eventArray['date_fin_inscription'] : '';
        return $stored;
    }

    /**
     * Vérifie si l'événement est entièrement passé
     *
     * @param array<string, mixed> $eventArray
     * @return bool
     */
    private function checkEventIsPast(array $eventArray): bool
    {
        $now = current_time('timestamp');

        // Utiliser date_fin si disponible, sinon date_debut
        $endDate = !empty($eventArray['date_fin']) ? (string) $eventArray['date_fin'] : '';
        $startDate = !empty($eventArray['date_debut']) ? (string) $eventArray['date_debut'] : '';

        $referenceDate = $endDate ?: $startDate;
        if (empty($referenceDate)) {
            return false;
        }

        $timestamp = strtotime($referenceDate);
        if ($timestamp === false) {
            return false;
        }

        // Considérer l'événement passé à la fin du jour de référence
        $endOfDay = strtotime(date('Y-m-d', $timestamp) . ' 23:59:59');

        return $endOfDay < $now;
    }

    /**
     * @param array<string, mixed> $eventArray
     * @param int|null $capacityRemaining
     * @return bool
     */
    private function checkRegistrationOpen(array $eventArray, ?int $capacityRemaining): bool
    {
        $status = isset($eventArray['status']) ? (string) $eventArray['status'] : '';
        if ($status !== MjEvents::STATUS_ACTIVE) {
            return false;
        }

        // Vérifier la date limite
        $deadlineRaw = $this->resolveRegistrationDeadline($eventArray);
        if ($deadlineRaw !== '') {
            $deadlineTs = strtotime($deadlineRaw);
            if ($deadlineTs && $deadlineTs < current_time('timestamp')) {
                return false;
            }
        }

        // Vérifier la capacité
        if ($capacityRemaining !== null && $capacityRemaining <= 0) {
            return false;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $eventArray
     * @return array<array<string, mixed>>
     */
    private function buildParticipants(array $eventArray): array
    {
        if (!is_user_logged_in()) {
            return array();
        }

        $currentMember = function_exists('mj_member_get_current_member')
            ? mj_member_get_current_member()
            : null;

        if (!$currentMember) {
            return array();
        }

        $participants = array();
        $currentMemberId = isset($currentMember->id) ? (int) $currentMember->id : 0;

        // Vérifier si le membre courant est un tuteur avec des enfants
        $isTuteur = function_exists('mj_member_can_manage_children') 
            && mj_member_can_manage_children($currentMember);
        
        $children = array();
        if ($isTuteur && function_exists('mj_member_get_guardian_children')) {
            $children = mj_member_get_guardian_children($currentMember);
        }

        // Si tuteur avec enfants : afficher les enfants (et le tuteur si allow_guardian_registration)
        // Sinon : afficher le membre courant
        if ($isTuteur && !empty($children)) {
            // Ajouter le tuteur lui-même seulement si autorisé
            if (!empty($eventArray['allow_guardian_registration'])) {
                $participants[] = array(
                    'id' => $currentMemberId,
                    'name' => isset($currentMember->first_name) ? (string) $currentMember->first_name : '',
                    'full_name' => trim(sprintf(
                        '%s %s',
                        isset($currentMember->first_name) ? (string) $currentMember->first_name : '',
                        isset($currentMember->last_name) ? (string) $currentMember->last_name : ''
                    )),
                    'is_primary' => true,
                    'is_guardian' => false,
                );
            }

            // Ajouter les enfants
            foreach ($children as $child) {
                $participants[] = array(
                    'id' => isset($child->id) ? (int) $child->id : 0,
                    'name' => isset($child->first_name) ? (string) $child->first_name : '',
                    'full_name' => trim(sprintf(
                        '%s %s',
                        isset($child->first_name) ? (string) $child->first_name : '',
                        isset($child->last_name) ? (string) $child->last_name : ''
                    )),
                    'is_primary' => false,
                    'is_child' => true,
                );
            }
        } else {
            // Membre standard (pas tuteur) : s'inscrire lui-même
            $participants[] = array(
                'id' => $currentMemberId,
                'name' => isset($currentMember->first_name) ? (string) $currentMember->first_name : '',
                'full_name' => trim(sprintf(
                    '%s %s',
                    isset($currentMember->first_name) ? (string) $currentMember->first_name : '',
                    isset($currentMember->last_name) ? (string) $currentMember->last_name : ''
                )),
                'is_primary' => true,
                'is_guardian' => false,
            );
        }

        return $participants;
    }

    /**
     * @param int $eventId
     * @param array<array<string, mixed>> $participants Liste des participants disponibles
     * @return array<array<string, mixed>>
     */
    private function buildUserReservations(int $eventId, array $participants = array()): array
    {
        if ($eventId <= 0 || !is_user_logged_in()) {
            return array();
        }

        $currentMember = function_exists('mj_member_get_current_member')
            ? mj_member_get_current_member()
            : null;

        if (!$currentMember) {
            return array();
        }

        // Récupérer les IDs de tous les participants (tuteur + enfants)
        $memberIds = array();
        if (!empty($participants)) {
            foreach ($participants as $p) {
                if (!empty($p['id'])) {
                    $memberIds[] = (int) $p['id'];
                }
            }
        }

        // Fallback si pas de participants
        if (empty($memberIds)) {
            $currentMemberId = isset($currentMember->id) ? (int) $currentMember->id : 0;
            $memberIds = array($currentMemberId);
        }

        $registrations = MjEventRegistrations::get_all(array(
            'event_id' => $eventId,
            'member_ids' => $memberIds,
        ));

        // DEBUG: Log pour voir ce qui est récupéré
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EventPageModel::buildUserReservations - eventId: ' . $eventId);
            error_log('EventPageModel::buildUserReservations - memberIds: ' . wp_json_encode($memberIds));
            error_log('EventPageModel::buildUserReservations - registrations count: ' . count($registrations));
            if (!empty($registrations)) {
                foreach ($registrations as $r) {
                    error_log('EventPageModel::buildUserReservations - reg: ' . wp_json_encode(is_object($r) ? get_object_vars($r) : $r));
                }
            }
        }

        $reservations = array();
        foreach ($registrations as $reg) {
            // EventRegistrationData est un value object avec des getters magiques
            // On utilise toArray() si disponible, sinon on accède via les propriétés
            if (method_exists($reg, 'toArray')) {
                $regArray = $reg->toArray();
            } elseif (is_object($reg)) {
                $regArray = array(
                    'id' => isset($reg->id) ? $reg->id : 0,
                    'member_id' => isset($reg->member_id) ? $reg->member_id : 0,
                    'statut' => isset($reg->statut) ? $reg->statut : '',
                    'created_at' => isset($reg->created_at) ? $reg->created_at : '',
                    'occurrence_assignments' => isset($reg->occurrence_assignments) ? $reg->occurrence_assignments : array(),
                );
            } else {
                $regArray = (array) $reg;
            }

            // Le champ est "statut" en français dans la BDD
            $status = isset($regArray['statut']) ? (string) $regArray['statut'] : '';
            
            // Récupérer les occurrences assignées depuis les données hydratées
            // et les convertir en timestamps pour correspondre au format du calendrier JS
            $occurrenceIds = array();
            if (!empty($regArray['occurrence_assignments']['occurrences'])) {
                foreach ($regArray['occurrence_assignments']['occurrences'] as $occDate) {
                    $ts = strtotime($occDate);
                    if ($ts) {
                        $occurrenceIds[] = $ts;
                    }
                }
            }

            $reservations[] = array(
                'id' => isset($regArray['id']) ? (int) $regArray['id'] : 0,
                'member_id' => isset($regArray['member_id']) ? (int) $regArray['member_id'] : 0,
                'status' => $status,
                'occurrence_ids' => $occurrenceIds,
                'created_at' => isset($regArray['created_at']) ? (string) $regArray['created_at'] : '',
            );
        }

        return $reservations;
    }

    /**
     * @param array<string, mixed> $eventArray
     * @return array<string, mixed>
     */
    private function buildAnimateursData(array $eventArray): array
    {
        $eventId = isset($eventArray['id']) ? (int) $eventArray['id'] : 0;

        if ($eventId <= 0) {
            return array(
                'count' => 0,
                'items' => array(),
            );
        }

        // get_members_by_event retourne directement les objets membres (pas les liaisons)
        $members = MjEventAnimateurs::get_members_by_event($eventId);
        $items = array();

        foreach ($members as $member) {
            $memberArray = is_object($member) ? get_object_vars($member) : (array) $member;
            
            // L'id du membre est dans 'id', pas 'member_id'
            $memberId = isset($memberArray['id']) ? (int) $memberArray['id'] : 0;

            $avatarId = isset($memberArray['photo_id']) ? (int) $memberArray['photo_id'] : 0;
            $avatarUrl = $avatarId > 0 ? wp_get_attachment_image_url($avatarId, 'medium') : '';

            $firstName = isset($memberArray['first_name']) ? (string) $memberArray['first_name'] : '';
            $lastName = isset($memberArray['last_name']) ? (string) $memberArray['last_name'] : '';
            $name = trim($firstName . ' ' . $lastName);

            $initials = '';
            if ($firstName !== '' && $lastName !== '') {
                $initials = mb_strtoupper(
                    mb_substr($firstName, 0, 1) .
                    mb_substr($lastName, 0, 1)
                );
            }

            $email = isset($memberArray['email']) ? sanitize_email((string) $memberArray['email']) : '';
            $phone = isset($memberArray['phone']) ? (string) $memberArray['phone'] : '';

            $whatsappLink = '';
            if ($phone !== '') {
                $phoneClean = preg_replace('/[^0-9+]/', '', $phone);
                if ($phoneClean) {
                    $whatsappLink = 'https://wa.me/' . ltrim($phoneClean, '+');
                }
            }

            // Pour l'instant on considère tous comme animateurs
            // TODO: récupérer is_primary depuis la table de liaison si besoin
            $roleLabel = \Mj\Member\Classes\MjRoles::getRoleLabel(\Mj\Member\Classes\MjRoles::ANIMATEUR);

            $items[] = array(
                'member_id' => $memberId,
                'name' => $name,
                'initials' => $initials,
                'avatar_url' => $avatarUrl ?: '',
                'email' => $email,
                'phone' => $phone,
                'whatsapp_link' => $whatsappLink,
                'role_label' => $roleLabel,
                'is_primary' => false,
                'is_volunteer' => false,
            );
        }

        return array(
            'count' => count($items),
            'items' => $items,
        );
    }

    /**
     * @param array<string, mixed> $eventArray
     * @return array<string, mixed>
     */
    private function buildPhotosData(array $eventArray): array
    {
        $eventId = isset($eventArray['id']) ? (int) $eventArray['id'] : 0;

        // Pour l'instant, la galerie de souvenirs n'est pas encore implémentée
        // Désactivé pour éviter confusion utilisateur
        return array(
            'has_photos' => false,
            'can_upload' => false, // TODO: activer quand la fonctionnalité sera prête
            'items' => array(),
            'total' => 0,
            'pending_count' => 0,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildUserData(): array
    {
        $isLoggedIn = is_user_logged_in();
        $currentMember = $isLoggedIn && function_exists('mj_member_get_current_member')
            ? mj_member_get_current_member()
            : null;

        $memberId = 0;
        $canRegister = false;
        $isAnimateur = false;
        $isVolunteer = false;

        if ($currentMember) {
            $memberId = isset($currentMember->id) ? (int) $currentMember->id : 0;
            $canRegister = true;

            // Vérifier les rôles avec MjRoles
            $role = isset($currentMember->role) ? (string) $currentMember->role : '';
            $isAnimateur = class_exists('Mj\\Member\\Classes\\MjRoles') 
                ? \Mj\Member\Classes\MjRoles::isAnimateurOrCoordinateur($role)
                : in_array($role, array(\Mj\Member\Classes\MjRoles::ANIMATEUR, \Mj\Member\Classes\MjRoles::COORDINATEUR), true);
            $isVolunteer = !empty($currentMember->is_volunteer);
        }

        return array(
            'is_logged_in' => $isLoggedIn,
            'member_id' => $memberId,
            'can_register' => $canRegister,
            'is_animateur' => $isAnimateur,
            'is_volunteer' => $isVolunteer,
        );
    }

    /**
     * @param array<string, mixed> $eventArray
     * @return array<string, mixed>
     */
    private function buildMetaData(array $eventArray): array
    {
        $ageMin = isset($eventArray['age_min']) ? (int) $eventArray['age_min'] : 0;
        $ageMax = isset($eventArray['age_max']) ? (int) $eventArray['age_max'] : 0;

        $ageLabel = '';
        if ($ageMin > 0 && $ageMax > 0) {
            $ageLabel = sprintf(__('%d à %d ans', 'mj-member'), $ageMin, $ageMax);
        } elseif ($ageMin > 0) {
            $ageLabel = sprintf(__('À partir de %d ans', 'mj-member'), $ageMin);
        } elseif ($ageMax > 0) {
            $ageLabel = sprintf(__('Jusqu\'à %d ans', 'mj-member'), $ageMax);
        }

        return array(
            'age_min' => $ageMin,
            'age_max' => $ageMax,
            'age_label' => $ageLabel,
            'created_at' => isset($eventArray['created_at']) ? (string) $eventArray['created_at'] : '',
            'updated_at' => isset($eventArray['updated_at']) ? (string) $eventArray['updated_at'] : '',
        );
    }

    /**
     * @return EventData|null
     */
    public function getEvent(): ?EventData
    {
        return $this->event;
    }

    /**
     * @return EventLocationData|null
     */
    public function getLocation(): ?EventLocationData
    {
        return $this->location;
    }
}
