<?php

namespace Mj\Member\Classes\View\EventPage;

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

        return array(
            'class' => implode(' ', $classes),
            'style' => !empty($styleTokens) ? implode(';', $styleTokens) . ';' : '',
            'data_event_id' => isset($event['id']) ? (int) $event['id'] : 0,
            'title' => isset($event['title']) ? (string) $event['title'] : '',
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

        $weeklySchedule = isset($schedule['weekly_schedule']) && is_array($schedule['weekly_schedule'])
            ? $schedule['weekly_schedule']
            : array('is_weekly' => false, 'days' => array());

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
            'price_label' => isset($registration['price_display']) ? (string) $registration['price_display'] : '',
        );
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

        $isOpen = !empty($registration['is_open']);
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
            'user' => array(
                'is_logged_in' => !empty($user['is_logged_in']),
                'can_register' => !empty($user['can_register']),
            ),
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

        return array(
            'has_location' => !empty($location['has_location']),
            'name' => isset($location['name']) ? (string) $location['name'] : '',
            'address' => isset($location['address']) ? (string) $location['address'] : '',
            'cover_url' => isset($location['cover_url']) ? (string) $location['cover_url'] : '',
            'notes' => isset($location['notes']) ? (string) $location['notes'] : '',
            'map_embed' => isset($location['map_embed']) ? (string) $location['map_embed'] : '',
            'map_link' => isset($location['map_link']) ? (string) $location['map_link'] : '',
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

        $nextOccurrence = isset($schedule['next_occurrence']) && is_array($schedule['next_occurrence'])
            ? $schedule['next_occurrence']
            : null;

        // Construire les données enrichies pour la prochaine date
        $nextOccurrenceData = array(
            'label' => '',
            'day_name' => '',
            'day_num' => '',
            'month_name' => '',
            'year' => '',
            'time_start' => '',
            'time_end' => '',
            'full_date' => '',
        );
        
        if ($nextOccurrence !== null) {
            $nextOccurrenceData['label'] = isset($nextOccurrence['label']) ? (string) $nextOccurrence['label'] : '';
            $nextOccurrenceData['day_name'] = isset($nextOccurrence['day_name']) ? (string) $nextOccurrence['day_name'] : '';
            $nextOccurrenceData['day_num'] = isset($nextOccurrence['day_num']) ? (string) $nextOccurrence['day_num'] : '';
            $nextOccurrenceData['month_name'] = isset($nextOccurrence['month_name']) ? (string) $nextOccurrence['month_name'] : '';
            
            $timestamp = isset($nextOccurrence['timestamp']) ? (int) $nextOccurrence['timestamp'] : 0;
            if ($timestamp > 0) {
                $nextOccurrenceData['year'] = date_i18n('Y', $timestamp);
                $nextOccurrenceData['full_date'] = date_i18n('l j F Y', $timestamp);
            }
            
            // Extraire les heures depuis start/end
            if (!empty($nextOccurrence['start'])) {
                $startTime = strtotime((string) $nextOccurrence['start']);
                if ($startTime) {
                    $nextOccurrenceData['time_start'] = date_i18n('H:i', $startTime);
                }
            }
            if (!empty($nextOccurrence['end'])) {
                $endTime = strtotime((string) $nextOccurrence['end']);
                if ($endTime) {
                    $nextOccurrenceData['time_end'] = date_i18n('H:i', $endTime);
                }
            }
        }

        return array(
            'deadline_label' => isset($registration['deadline_label']) ? (string) $registration['deadline_label'] : '',
            'price_label' => isset($registration['price_display']) ? (string) $registration['price_display'] : '',
            'age_label' => isset($meta['age_label']) ? (string) $meta['age_label'] : '',
            'capacity_total' => isset($registration['capacity_total']) ? (int) $registration['capacity_total'] : 0,
            'capacity_remaining' => isset($registration['capacity_remaining']) ? $registration['capacity_remaining'] : null,
            'schedule_summary' => isset($schedule['schedule_summary']) ? (string) $schedule['schedule_summary'] : '',
            'display_label' => isset($schedule['display_label']) ? (string) $schedule['display_label'] : '',
            'next_occurrence' => $nextOccurrenceData,
            'next_occurrence_label' => $nextOccurrenceData['label'],
            'has_multiple_occurrences' => !empty($schedule['has_multiple_occurrences']),
            'occurrences' => isset($schedule['occurrences']) ? $schedule['occurrences'] : array(),
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
        );
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
