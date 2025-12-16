<?php

namespace Mj\Member\Classes;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Mj\Member\Classes\Crud\MjEventClosures;
use Mj\Member\Classes\Crud\MjEvents;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class MjEventGoogleCalendar {
    const OPTION_ENABLED = 'mj_events_google_sync_enabled';
    const OPTION_TOKEN = 'mj_events_google_sync_token';
    const QUERY_PARAM = 'mj-member-google-calendar';
    const TOKEN_PARAM = 'token';

    /**
     * Initialise les hooks nécessaires.
     */
    public static function bootstrap() {
        add_action('template_redirect', array(__CLASS__, 'maybe_output_feed'), 0);
    }

    /**
     * Indique si la synchronisation est active.
     *
     * @return bool
     */
    public static function is_enabled() {
        return get_option(self::OPTION_ENABLED, '0') === '1';
    }

    /**
     * Retourne le jeton courant (sans création implicite).
     *
     * @return string
     */
    public static function get_current_token() {
        $token = get_option(self::OPTION_TOKEN, '');
        return is_string($token) ? $token : '';
    }

    /**
     * Retourne un jeton, en le générant au besoin.
     *
     * @param bool $create
     * @return string
     */
    public static function get_token($create = false) {
        $token = self::get_current_token();
        if ($token !== '' || !$create) {
            return $token;
        }

        return self::regenerate_token();
    }

    /**
     * Force la génération d'un nouveau jeton.
     *
     * @return string
     */
    public static function regenerate_token() {
        $token = self::generate_token();
        update_option(self::OPTION_TOKEN, $token, false);
        return $token;
    }

    /**
     * Enregistre un jeton fourni.
     *
     * @param string $candidate
     * @return string Jeton normalisé
     */
    public static function store_token($candidate) {
        $token = self::sanitize_token($candidate);
        update_option(self::OPTION_TOKEN, $token, false);
        return $token;
    }

    /**
     * Construit l'URL publique du flux ICS.
     *
     * @param bool $ensure_token
     * @return string
     */
    public static function get_feed_url($ensure_token = true) {
        $token = self::get_token($ensure_token);
        if ($token === '') {
            return '';
        }

        $args = array(
            self::QUERY_PARAM => '1',
            self::TOKEN_PARAM => $token,
        );
        return add_query_arg($args, home_url('/'));
    }



    /**
     * Il y a une erreur ici aussi 
     * @return void
     *     */
    public static function maybe_output_feed() {
        if (!isset($_GET[self::QUERY_PARAM])) {
            return;
        }

        if (!self::is_enabled()) {
            status_header(404);
            exit;
        }

        $provided = isset($_GET[self::TOKEN_PARAM])
            ? sanitize_text_field(wp_unslash($_GET[self::TOKEN_PARAM]))
            : '';

        $token = self::get_current_token();
        if ($token === '' || $provided !== $token) {
            status_header(403);
            echo 'Accès refusé';
            exit;
        }

        self::output_feed();
    }

    /**
     * Écrit le flux ICS et termine la requête.
     */
    private static function output_feed() {
        $content = self::build_feed();

        nocache_headers();
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="mj-member-evenements.ics"');
        header('Cache-Control: private, max-age=900');
        header('Pragma: public');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');

        echo $content;
        exit;
    }

    /**
     * Construit l'ensemble du flux ICS.
     *
     * @return string
     */
    private static function build_feed() {
        $feed_context = self::prepare_feed_context();
        if (is_wp_error($feed_context)) {
            return "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//MJ Member//Feed Disabled//FR\r\nEND:VCALENDAR\r\n";
        }

        $lines = array(
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//MJ Member//Google Calendar Sync//FR',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'REFRESH-INTERVAL;VALUE=DURATION:PT6H',
            'X-PUBLISHED-TTL:PT6H',
        );

        if (!empty($feed_context['calendar_name'])) {
            $lines[] = 'X-WR-CALNAME:' . self::escape_text($feed_context['calendar_name'] . ' — Événements');
        }
        if (!empty($feed_context['timezone_string'])) {
            $lines[] = 'X-WR-TIMEZONE:' . self::escape_text($feed_context['timezone_string']);
        }

        $entries = self::collect_entries(
            $feed_context['events'],
            $feed_context['since'],
            $feed_context['until'],
            $feed_context['since_ts'],
            $feed_context['until_ts'],
            isset($feed_context['closures']) ? $feed_context['closures'] : array()
        );

        $events_added = 0;
        foreach ($entries as $entry) {
            if (!isset($entry['event'], $entry['start'], $entry['end'], $entry['context'])) {
                continue;
            }

            if (self::append_vevent($lines, $entry['event'], $entry['start'], $entry['end'], $feed_context['now_utc'], $entry['context'])) {
                $events_added++;
            }
        }

        if ($events_added === 0) {
            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:' . self::build_placeholder_uid();
            $lines[] = 'DTSTAMP:' . $feed_context['now_utc']->format('Ymd\THis\Z');
            $lines[] = 'SUMMARY:' . self::escape_text('Agenda MJ Member — Aucun événement à venir');
            $lines[] = 'DTSTART:' . $feed_context['now_utc']->format('Ymd\THis\Z');
            $lines[] = 'DTEND:' . $feed_context['now_utc']->modify('+30 minutes')->format('Ymd\THis\Z');
            $lines[] = 'DESCRIPTION:' . self::escape_text('Aucun événement n\'est planifié pour le moment.');
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return self::implode_lines($lines);
    }

    /**
     * Prépare les données nécessaires à la construction du flux.
     *
     * @return array<string,mixed>|WP_Error
     */
    private static function prepare_feed_context() {
        if (!function_exists('mj_member_get_public_events')) {
            return new WP_Error('mj_member_missing_events_helper', __('Le module événements est indisponible.', 'mj-member'));
        }

        $events = mj_member_get_public_events(array(
            'statuses' => array(MjEvents::STATUS_ACTIVE),
            'limit' => 240,
            'order' => 'ASC',
            'orderby' => 'date_debut',
            'include_past' => true,
        ));

        $now_ts = current_time('timestamp');
        $now_utc = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $since = wp_date('Y-m-d H:i:s', $now_ts - DAY_IN_SECONDS);
        $until = wp_date('Y-m-d H:i:s', strtotime('+12 months', $now_ts));

        $since_dt = self::create_datetime($since);
        $until_dt = self::create_datetime($until);
        $since_ts = $since_dt ? $since_dt->getTimestamp() : ($now_ts - DAY_IN_SECONDS);
        $until_ts = $until_dt ? $until_dt->getTimestamp() : strtotime('+12 months', $now_ts);
        if (!is_int($until_ts) || $until_ts <= $since_ts) {
            $until_ts = $since_ts + (9 * MONTH_IN_SECONDS);
        }

        $closures = array();
        if (class_exists(MjEventClosures::class)) {
            $from_date = substr($since, 0, 10);
            $to_date = substr($until, 0, 10);
            $closures = MjEventClosures::get_all(array(
                'from' => $from_date,
                'to' => $to_date,
                'order' => 'ASC',
            ));
        }

        return array(
            'events' => $events,
            'since' => $since,
            'until' => $until,
            'since_ts' => $since_ts,
            'until_ts' => $until_ts,
            'now_utc' => $now_utc,
            'calendar_name' => get_bloginfo('name'),
            'timezone_string' => wp_timezone_string(),
            'closures' => $closures,
        );
    }

    /**
     * Construit une liste exploitable d'occurrences à exposer.
     *
     * @param array<int,mixed> $events
     * @param string $since
     * @param string $until
     * @param int $since_ts
     * @param int $until_ts
     * @return array<int,array<string,mixed>>
     */
    private static function collect_entries(array $events, $since, $until, $since_ts, $until_ts, array $closures = array()) {
        $entries = array();

        foreach ($events as $event) {
            if (!is_array($event)) {
                $event = (array) $event;
            }

            $schedule_event = self::normalize_event_for_schedule($event);
            $mode = isset($schedule_event['schedule_mode']) ? sanitize_key($schedule_event['schedule_mode']) : 'fixed';
            if (!in_array($mode, array('fixed', 'range', 'recurring'), true)) {
                $mode = 'fixed';
            }

            if ($mode === 'range') {
                $range_start_raw = isset($schedule_event['date_debut']) ? $schedule_event['date_debut'] : (isset($schedule_event['start_date']) ? $schedule_event['start_date'] : '');
                $range_end_raw = isset($schedule_event['date_fin']) ? $schedule_event['date_fin'] : (isset($schedule_event['end_date']) ? $schedule_event['end_date'] : '');
                $range_start = self::create_datetime($range_start_raw);
                $range_end = self::create_datetime($range_end_raw);

                if (!$range_start || !$range_end) {
                    continue;
                }
                if ($range_end->getTimestamp() < $since_ts) {
                    continue;
                }
                if ($range_start->getTimestamp() > $until_ts) {
                    continue;
                }

                $context = array(
                    'label' => sprintf(
                        __('Du %s au %s', 'mj-member'),
                        $range_start->format(get_option('date_format', 'd/m/Y')),
                        $range_end->format(get_option('date_format', 'd/m/Y'))
                    ),
                    'label_prefix' => __('Période', 'mj-member'),
                );

                $entries[] = array(
                    'event' => $event,
                    'start' => $range_start,
                    'end' => $range_end,
                    'context' => $context,
                );

                continue;
            }

            $occurrences = MjEventSchedule::get_occurrences($schedule_event, array(
                'max' => 180,
                'since' => $since,
                'until' => $until,
                'include_past' => false,
            ));

            if (empty($occurrences)) {
                continue;
            }

            foreach ($occurrences as $occurrence) {
                if (empty($occurrence['start'])) {
                    continue;
                }

                $start = self::create_datetime($occurrence['start']);
                $end = isset($occurrence['end']) ? self::create_datetime($occurrence['end']) : null;

                if (!$start) {
                    continue;
                }
                if ($start->getTimestamp() > $until_ts) {
                    continue;
                }
                if ($end && $end->getTimestamp() < $since_ts) {
                    continue;
                }

                if (!$end || $end <= $start) {
                    $end = $start->modify('+1 hour');
                }

                $context = array();
                if (!empty($occurrence['label'])) {
                    $context['label'] = sanitize_text_field($occurrence['label']);
                    $context['label_prefix'] = __('Créneau', 'mj-member');
                }

                $entries[] = array(
                    'event' => $event,
                    'start' => $start,
                    'end' => $end,
                    'context' => $context,
                );
            }
        }

        if (!empty($closures)) {
            foreach ($closures as $closure) {
                if (!is_object($closure)) {
                    continue;
                }

                $start_value = '';
                if (!empty($closure->start_date)) {
                    $start_value = (string) $closure->start_date;
                } elseif (!empty($closure->closure_date)) {
                    $start_value = (string) $closure->closure_date;
                }

                if ($start_value === '') {
                    continue;
                }

                $end_value = !empty($closure->end_date) ? (string) $closure->end_date : $start_value;

                $start = self::create_datetime($start_value . ' 00:00:00');
                if (!$start) {
                    continue;
                }

                $end_exclusive = self::create_datetime($end_value . ' 00:00:00');
                if ($end_exclusive instanceof DateTimeImmutable) {
                    $end_exclusive = $end_exclusive->modify('+1 day');
                } else {
                    $end_exclusive = $start->modify('+1 day');
                }

                $inclusive_end = self::create_datetime($end_value . ' 23:59:59');
                if (!$inclusive_end instanceof DateTimeImmutable) {
                    $inclusive_end = $end_exclusive->modify('-1 second');
                }

                if ($inclusive_end->getTimestamp() < $since_ts || $start->getTimestamp() > $until_ts) {
                    continue;
                }

                $description = isset($closure->description) ? sanitize_text_field($closure->description) : '';

                $cover_id = isset($closure->cover_id) ? (int) $closure->cover_id : 0;
                $cover_url = '';
                $cover_mime = '';
                if ($cover_id > 0) {
                    $candidate_url = '';
                    if (wp_attachment_is_image($cover_id)) {
                        $candidate_url = wp_get_attachment_image_url($cover_id, 'large');
                        if (!$candidate_url) {
                            $candidate_url = wp_get_attachment_url($cover_id);
                        }
                    } else {
                        $candidate_url = wp_get_attachment_url($cover_id);
                    }

                    if ($candidate_url) {
                        $cover_url = esc_url_raw($candidate_url);
                        $cover_mime = get_post_mime_type($cover_id);
                        if (!is_string($cover_mime)) {
                            $cover_mime = '';
                        }
                    }
                }

                if ($start_value === $end_value) {
                    $summary_detail = $description !== ''
                        ? $description
                        : wp_date(get_option('date_format', 'd/m/Y'), $start->getTimestamp());
                } else {
                    $summary_detail = sprintf(
                        __('Du %s au %s', 'mj-member'),
                        wp_date(get_option('date_format', 'd/m/Y'), $start->getTimestamp()),
                        wp_date(get_option('date_format', 'd/m/Y'), $inclusive_end->getTimestamp())
                    );
                    if ($description !== '') {
                        $summary_detail .= ' – ' . $description;
                    }
                }

                $entries[] = array(
                    'event' => array(
                        'id' => isset($closure->id) ? -abs((int) $closure->id) : 0,
                        'title' => 'MJ Fermée',
                        'description' => $description,
                        'force_color' => '#D32F2F',
                        'categories' => 'MJ Fermeture',
                        'closure_date' => sanitize_text_field($start_value),
                        'closure_start' => sanitize_text_field($start_value),
                        'closure_end' => sanitize_text_field($end_value),
                        'cover_id' => $cover_id,
                        'cover_url' => $cover_url,
                        'cover_mime' => $cover_mime,
                    ),
                    'start' => $start,
                    'end' => $end_exclusive,
                    'context' => array(
                        'closure' => true,
                        'closure_summary' => $summary_detail,
                        'closure_description' => $description,
                        'closure_range' => array(
                            'start' => $start_value,
                            'end' => $end_value,
                        ),
                        'all_day' => true,
                    ),
                );
            }
        }

        if (!empty($entries)) {
            usort(
                $entries,
                static function ($left, $right) {
                    $left_ts = $left['start'] instanceof DateTimeImmutable ? $left['start']->getTimestamp() : 0;
                    $right_ts = $right['start'] instanceof DateTimeImmutable ? $right['start']->getTimestamp() : 0;
                    if ($left_ts === $right_ts) {
                        return 0;
                    }
                    return ($left_ts < $right_ts) ? -1 : 1;
                }
            );
        }

        return $entries;
    }

    /**
     * Synchronise les événements via l'API Google Calendar.
     *
     * @param string $calendar_id
     * @param string $access_token
     * @param array<string,mixed> $args
     * @return array<string,mixed>|WP_Error
     */
    public static function sync_with_google_calendar($calendar_id, $access_token, $args = array()) {
        $calendar_id = trim((string) $calendar_id);
        $access_token = trim((string) $access_token);

        if ($calendar_id === '' || $access_token === '') {
            return new WP_Error('mj_member_google_sync_credentials', __('Identifiants Google Calendar manquants.', 'mj-member'));
        }

        if (!function_exists('wp_remote_post')) {
            return new WP_Error('mj_member_missing_http', __('Les fonctions HTTP de WordPress sont indisponibles.', 'mj-member'));
        }

        $feed_context = self::prepare_feed_context();
        if (is_wp_error($feed_context)) {
            return $feed_context;
        }

        $entries = self::collect_entries(
            $feed_context['events'],
            $feed_context['since'],
            $feed_context['until'],
            $feed_context['since_ts'],
            $feed_context['until_ts'],
            isset($feed_context['closures']) ? $feed_context['closures'] : array()
        );

        $max_events = isset($args['max_events']) ? max(1, (int) $args['max_events']) : 200;
        if ($max_events > 0 && count($entries) > $max_events) {
            $entries = array_slice($entries, 0, $max_events);
        }

        if (empty($entries)) {
            return array(
                'synced' => 0,
                'skipped' => 0,
                'errors' => array(),
            );
        }

        $dry_run = !empty($args['dry_run']);
        $endpoint_base = 'https://www.googleapis.com/calendar/v3/calendars/' . rawurlencode($calendar_id) . '/events';

        $result = array(
            'synced' => 0,
            'skipped' => 0,
            'errors' => array(),
        );

        foreach ($entries as $entry) {
            $payload = self::build_google_event_payload($entry, $feed_context);
            if (is_wp_error($payload)) {
                $result['errors'][] = $payload->get_error_message();
                $result['skipped']++;
                continue;
            }

            if ($dry_run) {
                $result['synced']++;
                continue;
            }

            $event_endpoint = $endpoint_base . '/' . rawurlencode($payload['id']);

            $response = wp_remote_request($event_endpoint, array(
                'method' => 'PUT',
                'headers' => array(
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type' => 'application/json',
                ),
                'timeout' => isset($args['timeout']) ? max(5, (int) $args['timeout']) : 20,
                'body' => wp_json_encode($payload),
            ));

            if (is_wp_error($response)) {
                $result['errors'][] = $response->get_error_message();
                $result['skipped']++;
                continue;
            }

            $status_code = (int) wp_remote_retrieve_response_code($response);
            if ($status_code >= 200 && $status_code < 300) {
                $result['synced']++;
            } else {
                $body = wp_remote_retrieve_body($response);
                $result['errors'][] = sprintf(__('Erreur Google (%d) : %s', 'mj-member'), $status_code, is_string($body) ? $body : '');
                $result['skipped']++;
            }
        }

        return $result;
    }

    /**
     * Normalise les données d'événement pour la génération d'occurrences.
     *
     * @param array<string,mixed> $event
     * @return array<string,mixed>
     */
    private static function normalize_event_for_schedule(array $event) {
        $normalized = $event;

        if (!isset($normalized['date_debut']) && !empty($event['start_date'])) {
            $normalized['date_debut'] = $event['start_date'];
        }
        if (!isset($normalized['date_fin']) && !empty($event['end_date'])) {
            $normalized['date_fin'] = $event['end_date'];
        }
        if (!isset($normalized['date_fin_inscription']) && !empty($event['deadline'])) {
            $normalized['date_fin_inscription'] = $event['deadline'];
        }

        if (!isset($normalized['schedule_payload']) && isset($event['payload'])) {
            $normalized['schedule_payload'] = $event['payload'];
        }

        if (isset($normalized['schedule_payload']) && !is_array($normalized['schedule_payload']) && $normalized['schedule_payload'] !== '') {
            $decoded = json_decode((string) $normalized['schedule_payload'], true);
            if (is_array($decoded)) {
                $normalized['schedule_payload'] = $decoded;
            }
        }

        return $normalized;
    }

    /**
     * Ajoute un VEVENT au flux.
     *
     * @param array<int,string> $lines
     * @param array<string,mixed> $event
     * @param DateTimeImmutable $start
     * @param DateTimeImmutable $end
     * @param DateTimeImmutable $now_utc
     * @param array<string,mixed> $context
     * @return bool
     */
    private static function append_vevent(array &$lines, array $event, DateTimeImmutable $start, DateTimeImmutable $end, DateTimeImmutable $now_utc, array $context = array()) {
        $all_day = !empty($context['all_day']);
        if ($end <= $start) {
            $end = $all_day ? $start->modify('+1 day') : $start->modify('+1 hour');
        }

        $summary = self::build_summary($event, $context);
        $location = self::build_location($event);
        $description = self::build_description($event, $context);
        $url = isset($event['permalink']) ? esc_url_raw($event['permalink']) : '';
        $attachment_url = isset($event['cover_url']) ? esc_url_raw((string) $event['cover_url']) : '';
        $attachment_mime = isset($event['cover_mime']) ? sanitize_text_field((string) $event['cover_mime']) : '';

        $categories = '';
        if (isset($event['categories'])) {
            if (is_array($event['categories'])) {
                $category_parts = array();
                foreach ($event['categories'] as $category_value) {
                    $category_value = sanitize_text_field($category_value);
                    if ($category_value !== '') {
                        $category_parts[] = $category_value;
                    }
                }
                if (!empty($category_parts)) {
                    $categories = implode(',', $category_parts);
                }
            } else {
                $categories = sanitize_text_field($event['categories']);
            }
        } elseif (isset($event['type'])) {
            $categories = sanitize_text_field($event['type']);
        }

        $color = self::resolve_event_color($event);

        $lines[] = 'BEGIN:VEVENT';
        $lines[] = 'UID:' . self::build_uid($event, $start);
        $lines[] = 'DTSTAMP:' . $now_utc->format('Ymd\THis\Z');
        if ($all_day) {
            $lines[] = 'DTSTART;VALUE=DATE:' . self::format_date($start);
            $lines[] = 'DTEND;VALUE=DATE:' . self::format_date($end);
            $lines[] = 'X-MICROSOFT-CDO-ALLDAYEVENT:TRUE';
        } else {
            $lines[] = 'DTSTART:' . self::format_datetime($start);
            $lines[] = 'DTEND:' . self::format_datetime($end);
        }

        if ($summary !== '') {
            $lines[] = 'SUMMARY:' . $summary;
        }
        if ($location !== '') {
            $lines[] = 'LOCATION:' . $location;
        }
        if ($description !== '') {
            $lines[] = 'DESCRIPTION:' . $description;
        }
        if ($url !== '') {
            $lines[] = 'URL:' . self::escape_text($url);
        }
        if ($categories !== '') {
            $lines[] = 'CATEGORIES:' . self::escape_text($categories);
        }
        if ($color !== '') {
            $lines[] = 'COLOR:' . strtoupper($color);
        }
        if ($attachment_url !== '') {
            $attach_line = 'ATTACH';
            if ($attachment_mime !== '') {
                $attach_line .= ';FMTTYPE=' . self::escape_text($attachment_mime);
            }
            $attach_line .= ':' . self::escape_text($attachment_url);
            $lines[] = $attach_line;
        }

        $lines[] = 'END:VEVENT';

        return true;
    }

    /**
     * Construit l'intitulé affiché dans le calendrier externe.
     *
     * @param array<string,mixed> $event
     * @param array<string,mixed> $context
     * @return string
     */
    private static function build_summary($event, $context = array()) {
        $label = self::build_summary_label($event, $context);
        return $label !== '' ? self::escape_text($label) : '';
    }

    /**
     * Produit un intitulé brut pour les événements exportés.
     *
     * @param array<string,mixed> $event
     * @param array<string,mixed> $context
     * @return string
     */
    private static function build_summary_label($event, $context = array()) {
        if (!empty($context['closure'])) {
            $detail = '';
            if (!empty($context['closure_summary'])) {
                $detail = sanitize_text_field($context['closure_summary']);
            }

            $detail = trim($detail);

            if ($detail === '') {
                list($range_start, $range_end) = self::resolve_closure_range($context, $event);
                if ($range_start !== '') {
                    $detail = self::build_closure_range_label($range_start, $range_end);
                }
            }

            if ($detail === '' && !empty($event['title'])) {
                $detail = trim((string) $event['title']);
            }

            if ($detail === '') {
                return 'Mj Fermée';
            }

            return 'Mj Fermée : ' . $detail;
        }

        $parts = array();

        if (!empty($event['title'])) {
            $parts[] = trim((string) $event['title']);
        }

        if (empty($parts) && !empty($context['label'])) {
            $label = sanitize_text_field($context['label']);
            if ($label !== '') {
                $parts[] = $label;
            }
        }

        $parts = array_unique(array_filter($parts, static function ($value) {
            return trim((string) $value) !== '';
        }));

        if (empty($parts)) {
            return '';
        }

        return implode(' — ', $parts);
    }

    /**
     * Construit la description textuelle.
     *
     * @param array<string,mixed> $event
     * @param array<string,mixed> $context
     * @return string
     */
    private static function build_description($event, $context = array()) {
        $sections = self::build_description_sections($event, $context);
        if (empty($sections)) {
            return '';
        }

        return self::escape_text(implode("\n\n", $sections));
    }

    /**
     * Retourne les différentes sections textuelles composant la description.
     *
     * @param array<string,mixed> $event
     * @param array<string,mixed> $context
     * @return array<int,string>
     */
    private static function build_description_sections($event, $context = array()) {
        $parts = array();

        if (!empty($context['closure'])) {
            $detail = '';
            if (!empty($context['closure_summary'])) {
                $detail = sanitize_text_field($context['closure_summary']);
            }

            $detail = trim($detail);
            if ($detail === '') {
                list($range_start, $range_end) = self::resolve_closure_range($context, $event);
                if ($range_start !== '') {
                    $detail = self::build_closure_range_label($range_start, $range_end);
                }
            }

            if ($detail !== '') {
                $parts[] = sprintf('Mj Fermée : %s', $detail);
            } else {
                $parts[] = 'Mj Fermée';
            }

            if (!empty($context['closure_description'])) {
                $parts[] = sanitize_textarea_field($context['closure_description']);
            }

            if (!empty($event['cover_url'])) {
                $parts[] = sprintf(__('Photo : %s', 'mj-member'), esc_url_raw((string) $event['cover_url']));
            }
        }

        if (!empty($context['label'])) {
            $label_prefix = isset($context['label_prefix']) ? sanitize_text_field($context['label_prefix']) : __('Créneau', 'mj-member');
            $label_value = sanitize_text_field($context['label']);
            $parts[] = sprintf('%s : %s', $label_prefix, $label_value);
        }

        if (!empty($event['type'])) {
            $parts[] = sprintf(__('Type : %s', 'mj-member'), sanitize_text_field($event['type']));
        }

        $age_min = isset($event['age_min']) ? (int) $event['age_min'] : 0;
        $age_max = isset($event['age_max']) ? (int) $event['age_max'] : 0;
        $age_context = '';
        if ($age_min > 0 && $age_max > 0) {
            $age_context = sprintf(__('%1$d - %2$d ans', 'mj-member'), $age_min, $age_max);
        } elseif ($age_min > 0) {
            $age_context = sprintf(__('À partir de %d ans', 'mj-member'), $age_min);
        } elseif ($age_max > 0) {
            $age_context = sprintf(__('Jusqu\'à %d ans', 'mj-member'), $age_max);
        }
        if ($age_context !== '') {
            $parts[] = sprintf(__('Public : %s', 'mj-member'), $age_context);
        }

        if (isset($event['price'])) {
            $price_value = (float) $event['price'];
            if ($price_value > 0) {
                $price = number_format_i18n($price_value, 2) . ' €';
                $parts[] = sprintf(__('Tarif : %s', 'mj-member'), $price);
            } elseif ($price_value === 0.0) {
                $parts[] = __('Tarif : Gratuit', 'mj-member');
            }
        }

        if (!empty($event['deadline']) && $event['deadline'] !== '0000-00-00 00:00:00') {
            $deadline_dt = self::create_datetime($event['deadline']);
            if ($deadline_dt) {
                $date_format = get_option('date_format', 'd/m/Y');
                $time_format = get_option('time_format', 'H:i');
                $deadline_label = $deadline_dt->format($date_format);
                $deadline_time = $deadline_dt->format($time_format);
                if ($deadline_time !== '' && $deadline_time !== '00:00' && $deadline_time !== '00:00:00') {
                    $deadline_label .= ' ' . $deadline_time;
                }
                $parts[] = sprintf(__('Fin des inscriptions : %s', 'mj-member'), $deadline_label);
            }
        }

        $location_fragments = array();
        if (!empty($event['location'])) {
            $location_fragments[] = sanitize_text_field($event['location']);
        }
        if (!empty($event['location_address'])) {
            $location_fragments[] = sanitize_text_field($event['location_address']);
        }
        if (!empty($event['location_description'])) {
            $location_fragments[] = sanitize_textarea_field($event['location_description']);
        }
        if (!empty($location_fragments)) {
            $parts[] = sprintf(__('Lieu : %s', 'mj-member'), implode(' — ', array_unique($location_fragments)));
        }
        if (!empty($event['location_map_link'])) {
            $parts[] = sprintf(__('Plan : %s', 'mj-member'), esc_url_raw($event['location_map_link']));
        }

        if (!empty($event['description'])) {
            $plain = wp_strip_all_tags($event['description']);
            $plain = preg_replace('/\s+/u', ' ', $plain);
            $plain = trim($plain);
            if ($plain !== '') {
                $parts[] = mb_substr($plain, 0, 900, 'UTF-8');
            }
        }

        if (!empty($event['permalink'])) {
            $parts[] = esc_url_raw($event['permalink']);
        }

        return array_values(array_filter($parts, static function ($value) {
            return trim((string) $value) !== '';
        }));
    }

    /**
     * Construit la représentation textuelle du lieu.
     *
     * @param array<string,mixed> $event
     * @return string
     */
    private static function build_location($event) {
        $location = self::build_location_text($event);
        if ($location === '') {
            return '';
        }

        return self::escape_text($location);
    }

    /**
     * Construit une version texte du lieu sans échappement ICS.
     *
     * @param array<string,mixed> $event
     * @return string
     */
    private static function build_location_text($event) {
        $chunks = array();
        if (!empty($event['location'])) {
            $chunks[] = sanitize_text_field($event['location']);
        }
        if (!empty($event['location_address'])) {
            $chunks[] = sanitize_text_field($event['location_address']);
        }
        if (!empty($event['location_description'])) {
            $chunks[] = wp_strip_all_tags($event['location_description']);
        }

        if (empty($chunks)) {
            return '';
        }

        return implode(' — ', array_unique(array_filter($chunks, static function ($value) {
            return trim((string) $value) !== '';
        })));
    }

    /**
     * Détermine la couleur hexadécimale associée à un événement.
     *
     * @param array<string,mixed> $event
     * @return string
     */
    private static function resolve_event_color($event) {
        $color = '';

        if (!empty($event['force_color'])) {
            $candidate = sanitize_hex_color($event['force_color']);
            if (is_string($candidate) && $candidate !== '') {
                $color = strtoupper($candidate);
            }
        }

        if ($color === '' && !empty($event['type']) && class_exists(MjEvents::class)) {
            $type_key = sanitize_key($event['type']);
            if ($type_key !== '') {
                if (method_exists('MjEvents', 'get_type_colors')) {
                    $type_colors = MjEvents::get_type_colors();
                    if (isset($type_colors[$type_key])) {
                        $candidate = sanitize_hex_color($type_colors[$type_key]);
                        if (is_string($candidate) && $candidate !== '') {
                            $color = strtoupper($candidate);
                        }
                    }
                }

                if ($color === '' && method_exists('MjEvents', 'get_default_color_for_type')) {
                    $candidate = sanitize_hex_color(MjEvents::get_default_color_for_type($type_key));
                    if (is_string($candidate) && $candidate !== '') {
                        $color = strtoupper($candidate);
                    }
                }
            }
        }

        if ($color === '') {
            return '';
        }

        if ($color[0] !== '#') {
            $color = '#' . ltrim($color, '#');
        }

        if (strlen($color) === 4) {
            $color = '#' . $color[1] . $color[1] . $color[2] . $color[2] . $color[3] . $color[3];
        }

        return strlen($color) === 7 ? strtoupper($color) : '';
    }

    /**
     * Détermine l'identifiant de couleur Google Calendar le plus proche.
     *
     * @param string $color
     * @return string
     */
    private static function map_color_to_google_id($color) {
        $color = strtoupper(ltrim((string) $color, '#'));
        if (strlen($color) !== 6) {
            return '';
        }

        static $google_palette = array(
            '7986CB' => '1',
            '33B679' => '2',
            '8E24AA' => '3',
            'E67C73' => '4',
            'F6C026' => '5',
            'F5511D' => '6',
            '039BE5' => '7',
            '616161' => '8',
            '3F51B5' => '9',
            '0B8043' => '10',
            'D60000' => '11',
        );

        if (isset($google_palette[$color])) {
            return $google_palette[$color];
        }

        $index = (abs(crc32($color)) % 11) + 1;
        return (string) $index;
    }

    /**
     * Construit le payload compatible Google Calendar pour un événement.
     *
     * @param array<string,mixed> $entry
     * @param array<string,mixed> $feed_context
     * @return array<string,mixed>|WP_Error
     */
    private static function build_google_event_payload(array $entry, array $feed_context) {
        if (empty($entry['event']) || empty($entry['start']) || empty($entry['end'])) {
            return new WP_Error('mj_member_google_payload_invalid', __('Occurrence d\'événement invalide.', 'mj-member'));
        }

        $event = is_array($entry['event']) ? $entry['event'] : (array) $entry['event'];
        $start = $entry['start'];
        $end = $entry['end'];
        $context = isset($entry['context']) && is_array($entry['context']) ? $entry['context'] : array();

        if (!$start instanceof DateTimeImmutable || !$end instanceof DateTimeImmutable) {
            return new WP_Error('mj_member_google_payload_datetime', __('Dates d\'événement introuvables.', 'mj-member'));
        }

        $summary = self::build_summary_label($event, $context);
        if ($summary === '') {
            $summary = __('Événement MJ Member', 'mj-member');
        }

        $description_parts = self::build_description_sections($event, $context);
        $description = implode("\n\n", $description_parts);
        $location = self::build_location_text($event);
        $color_hex = self::resolve_event_color($event);
        $google_color_id = self::map_color_to_google_id($color_hex);
        $all_day = !empty($context['all_day']);

        $source_url = isset($event['permalink']) ? esc_url_raw($event['permalink']) : home_url('/');
        $source_title = !empty($feed_context['calendar_name']) ? $feed_context['calendar_name'] : get_bloginfo('name');

        $payload = array(
            'id' => self::build_google_event_id($event, $start),
            'summary' => $summary,
            'start' => $all_day
                ? array('date' => $start->setTimezone(wp_timezone())->format('Y-m-d'))
                : array(
                    'dateTime' => $start->format(DateTimeInterface::ATOM),
                    'timeZone' => $start->getTimezone()->getName(),
                ),
            'end' => $all_day
                ? array('date' => $end->setTimezone(wp_timezone())->format('Y-m-d'))
                : array(
                    'dateTime' => $end->format(DateTimeInterface::ATOM),
                    'timeZone' => $end->getTimezone()->getName(),
                ),
            'source' => array(
                'title' => $source_title,
                'url' => $source_url,
            ),
        );

        if ($description !== '') {
            $payload['description'] = $description;
        }

        if ($location !== '') {
            $payload['location'] = $location;
        }

        if ($google_color_id !== '') {
            $payload['colorId'] = $google_color_id;
        }

        return $payload;
    }

    /**
     * Génère un identifiant stable compatible Google Calendar.
     *
     * @param array<string,mixed> $event
     * @param DateTimeImmutable $start
     * @return string
     */
    private static function build_google_event_id(array $event, DateTimeImmutable $start) {
        $event_id = isset($event['id']) ? (int) $event['id'] : 0;
        $timestamp = $start->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis');
        $base = 'mj-member-' . $event_id . '-' . $timestamp;
        $base = preg_replace('/[^a-zA-Z0-9_-]/', '', $base);

        if ($base === null || $base === '') {
            $base = 'mj-member-' . substr(md5($timestamp . wp_json_encode($event)), 0, 16);
        }

        return substr($base, 0, 1024);
    }

    /**
     * Construit un UID stable pour un événement.
     *
     * @param array<string,mixed> $event
     * @param DateTimeImmutable $start
     * @return string
     */
    private static function build_uid($event, DateTimeImmutable $start) {
        $event_id = isset($event['id']) ? (int) $event['id'] : 0;
        $host = wp_parse_url(home_url(), PHP_URL_HOST);
        if (empty($host)) {
            $host = 'mj-member.local';
        }

        $uid = 'mj-member-' . $event_id . '-' . $start->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis');
        return self::escape_text($uid . '@' . $host);
    }

    /**
     * UID placeholder lorsque qu'aucun événement n'est disponible.
     *
     * @return string
     */
    private static function build_placeholder_uid() {
        $host = wp_parse_url(home_url(), PHP_URL_HOST);
        if (empty($host)) {
            $host = 'mj-member.local';
        }
        return self::escape_text('mj-member-empty-' . gmdate('Ymd\THis') . '@' . $host);
    }

    /**
     * Convertit une DateTimeImmutable en format ICS.
     *
     * @param DateTimeImmutable $date
     * @return string
     */
    private static function format_datetime(DateTimeImmutable $date) {
        return $date->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
    }

    /**
     * Convertit une date en format ICS (journée entière).
     *
     * @param DateTimeImmutable $date
     * @return string
     */
    private static function format_date(DateTimeImmutable $date) {
        return $date->setTimezone(wp_timezone())->format('Ymd');
    }

    /**
     * Crée un objet DateTimeImmutable à partir d'une chaîne.
     *
     * @param mixed $value
     * @return DateTimeImmutable|null
     */
    private static function create_datetime($value) {
        $timezone = wp_timezone();

        if ($value instanceof DateTimeImmutable) {
            return $value->setTimezone($timezone);
        }

        if ($value instanceof DateTime) {
            $immutable = DateTimeImmutable::createFromMutable($value);
            return $immutable ? $immutable->setTimezone($timezone) : null;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $formats = array('Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d');
        foreach ($formats as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value, $timezone);
            if ($date instanceof DateTimeImmutable) {
                return $date;
            }
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }

        return (new DateTimeImmutable('@' . $timestamp))->setTimezone($timezone);
    }

    /**
     * Transforme une liste en flux ICS (pliage des lignes inclus).
     *
     * @param array<int,string> $lines
     * @return string
     */
    private static function implode_lines(array $lines) {
        $output = array();
        foreach ($lines as $line) {
            $output[] = self::fold_line((string) $line);
        }

        return implode("\r\n", $output) . "\r\n";
    }

    /**
     * @param array<string,mixed> $context
     * @param array<string,mixed> $event
     * @return array{0:string,1:string}
     */
    private static function resolve_closure_range($context, $event) {
        $start = '';
        $end = '';

        if (!empty($context['closure_range']) && is_array($context['closure_range'])) {
            $start = isset($context['closure_range']['start']) ? (string) $context['closure_range']['start'] : '';
            $end = isset($context['closure_range']['end']) ? (string) $context['closure_range']['end'] : '';
        } else {
            if (!empty($event['closure_start'])) {
                $start = (string) $event['closure_start'];
            } elseif (!empty($event['closure_date'])) {
                $start = (string) $event['closure_date'];
            }

            if (!empty($event['closure_end'])) {
                $end = (string) $event['closure_end'];
            }
        }

        if ($start !== '' && $end === '') {
            $end = $start;
        }

        return array($start, $end);
    }

    /**
     * @param string $start
     * @param string $end
     * @return string
     */
    private static function build_closure_range_label($start, $end) {
        $start = trim((string) $start);
        $end = trim((string) $end);

        if ($start === '') {
            return '';
        }

        $start_ts = strtotime($start);
        if ($start_ts === false) {
            return $start;
        }

        if ($end === '' || $end === $start) {
            return wp_date('d/m/Y', $start_ts);
        }

        $end_ts = strtotime($end);
        if ($end_ts === false) {
            return wp_date('d/m/Y', $start_ts);
        }

        return sprintf(
            __('Du %s au %s', 'mj-member'),
            wp_date('d/m/Y', $start_ts),
            wp_date('d/m/Y', $end_ts)
        );
    }

    /**
     * Applique l'échappement requis pour ICS.
     *
     * @param string $value
     * @return string
     */
    private static function escape_text($value) {
        $value = (string) $value;
        $value = str_replace('\\', '\\\\', $value);
        $value = str_replace(array("\r\n", "\n", "\r"), '\\n', $value);
        $value = str_replace(array(',', ';'), array('\\,', '\\;'), $value);
        return $value;
    }

    /**
     * Plie une ligne à 75 octets maximum comme requis par la RFC 5545.
     *
     * @param string $line
     * @return string
     */
    private static function fold_line($line) {
        $line = preg_replace("/(\r\n|\n|\r)/", '', (string) $line);
        if ($line === null) {
            $line = '';
        }

        $encoded = mb_convert_encoding($line, 'UTF-8', 'UTF-8');
        $length = strlen($encoded);
        if ($length <= 75) {
            return $line;
        }

        $result = '';
        $offset = 0;
        while ($length - $offset > 75) {
            $chunk = substr($encoded, $offset, 75);
            $result .= $chunk . "\r\n ";
            $offset += 75;
        }
        $result .= substr($encoded, $offset);

        return $result;
    }

    /**
     * Génère un jeton alphanumérique.
     *
     * @return string
     */
    private static function generate_token() {
        return wp_generate_password(48, false, false);
    }

    /**
     * Nettoie un jeton fourni.
     *
     * @param string $candidate
     * @return string
     */
    private static function sanitize_token($candidate) {
        $candidate = preg_replace('/[^a-zA-Z0-9]/', '', (string) $candidate);
        if ($candidate === null) {
            $candidate = '';
        }
        return substr($candidate, 0, 64);
    }
}

\class_alias(__NAMESPACE__ . '\\MjEventGoogleCalendar', 'MjEventGoogleCalendar');
