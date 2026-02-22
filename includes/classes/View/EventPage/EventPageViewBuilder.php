<?php

namespace Mj\Member\Classes\View\EventPage;

use DateTime;
use Mj\Member\Classes\View\Schedule\ScheduleDisplayHelper;

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
        $inlineSchedule = $this->buildWeeklyScheduleFromOccurrences($schedule, false);
        $inlineScheduleDays = isset($inlineSchedule['days']) && is_array($inlineSchedule['days'])
            ? $inlineSchedule['days']
            : array();

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
            'schedule_component' => $scheduleComponent,
            'next_occurrence' => $nextOccurrenceData,
            'next_occurrence_label' => $nextOccurrenceLabel,
            'price_label' => isset($registration['price_display']) ? (string) $registration['price_display'] : '',
            'inline_schedule_days' => $inlineScheduleDays,
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
            return $fallback;
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

        $daysByIndex = array();

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

            if (isset($daysByIndex[$weekdayIndex])) {
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

            $startLabel = $startTimestamp > 0
                ? $this->formatTimeFromTimestamp($startTimestamp, $timezone)
                : '';
            $endLabel = $endTimestamp > 0
                ? $this->formatTimeFromTimestamp($endTimestamp, $timezone)
                : '';

            $timeRange = '';
            if ($startLabel !== '' && $endLabel !== '' && $endLabel !== $startLabel) {
                $timeRange = $startLabel . ' - ' . $endLabel;
            } elseif ($startLabel !== '') {
                $timeRange = $startLabel;
            } elseif ($endLabel !== '') {
                $timeRange = $endLabel;
            }

            $daysByIndex[$weekdayIndex] = array(
                'key' => $this->weekdayKeyFromIndex($weekdayIndex),
                'label' => $weekdayLabels[$weekdayIndex] ?? '',
                'start_time' => $startTimestamp > 0 ? wp_date('H:i', $startTimestamp, $timezone) : '',
                'end_time' => $endTimestamp > 0 ? wp_date('H:i', $endTimestamp, $timezone) : '',
                'start_formatted' => $startLabel,
                'end_formatted' => $endLabel,
                'time_range' => $timeRange,
            );
        }

        if (empty($daysByIndex)) {
            return array(
                'is_weekly' => false,
                'is_monthly' => false,
                'is_series' => false,
                'show_date_range' => $showDateRange,
                'days' => array(),
                'series_items' => array(),
            );
        }

        ksort($daysByIndex);

        return array(
            'is_weekly' => true,
            'is_monthly' => false,
            'is_series' => false,
            'show_date_range' => $showDateRange,
            'days' => array_values($daysByIndex),
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

        $label = $data['label'];
        if ($label === '') {
            $labelParts = array();
            if ($data['full_date'] !== '') {
                $labelParts[] = $data['full_date'];
            }

            $timeRange = '';
            if ($data['time_start'] !== '') {
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
