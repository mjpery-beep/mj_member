<?php

namespace Mj\Member\Classes;

use DateTime;
use DateTimeInterface;
use Mj\Member\Classes\Crud\MjEventOccurrences;

if (!defined('ABSPATH')) {
    exit;
}

class MjEventSchedule {
    /**
     * @param object|array $event
     * @param array<string,mixed> $args
     * @return array<int,array<string,mixed>>
     */
    public static function get_occurrences($event, $args = array()) {
        $event = self::to_event_object($event);
        if (!$event) {
            return array();
        }

        $defaults = array(
            'max' => 50,
            'since' => null,
            'until' => null,
            'include_past' => true,
            'statuses' => array(
                MjEventOccurrences::STATUS_ACTIVE,
                MjEventOccurrences::STATUS_A_CONFIRMER,
                MjEventOccurrences::STATUS_REPORTE,
            ),
            'include_cancelled' => false,
        );
        $args = wp_parse_args($args, $defaults);

        $allowed_statuses = self::normalize_status_filter(
            isset($args['statuses']) ? $args['statuses'] : array(),
            !empty($args['include_cancelled'])
        );

        $rows = array();
        if (isset($event->id)) {
            $rows = MjEventOccurrences::get_for_event((int) $event->id);
        }

        $occurrences = self::map_rows_to_occurrences($rows, $allowed_statuses);
        if (!empty($occurrences)) {
            $occurrences = self::filter_occurrences($occurrences, $args);
        }

        if (empty($occurrences)) {
            $fallback = self::build_fallback_occurrence($event);
            if ($fallback !== null) {
                $occurrences = array($fallback);
            }
        }

        $max = max(1, (int) $args['max']);
        if (count($occurrences) > $max) {
            $occurrences = array_slice($occurrences, 0, $max);
        }

        return $occurrences;
    }

    /**
     * @param object|array $event
     * @return array<int,array<string,mixed>>
     */
    public static function build_all_occurrences($event) {
        return self::get_occurrences(
            $event,
            array(
                'max' => PHP_INT_MAX,
                'include_past' => true,
                'include_cancelled' => true,
                'statuses' => array(
                    MjEventOccurrences::STATUS_ACTIVE,
                    MjEventOccurrences::STATUS_A_CONFIRMER,
                    MjEventOccurrences::STATUS_REPORTE,
                    MjEventOccurrences::STATUS_ANNULE,
                ),
            )
        );
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @param array<string,bool> $allowed_statuses
     * @return array<int,array<string,mixed>>
     */
    private static function map_rows_to_occurrences(array $rows, array $allowed_statuses) {
        if (empty($rows)) {
            return array();
        }

        $occurrences = array();
        $now_ts = current_time('timestamp');

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $status = isset($row['status']) ? sanitize_key((string) $row['status']) : MjEventOccurrences::STATUS_ACTIVE;
            $meta = array();
            if (!empty($row['meta'])) {
                $decoded_meta = json_decode((string) $row['meta'], true);
                if (is_array($decoded_meta)) {
                    $meta = $decoded_meta;
                }
            }

            if ((!isset($row['status']) || $row['status'] === null) && isset($meta['status'])) {
                $meta_status = sanitize_key((string) $meta['status']);
                if ($meta_status !== '') {
                    switch ($meta_status) {
                        case 'confirmed':
                        case 'active':
                            $status = MjEventOccurrences::STATUS_ACTIVE;
                            break;
                        case 'cancelled':
                        case 'annule':
                            $status = MjEventOccurrences::STATUS_ANNULE;
                            break;
                        case 'postponed':
                        case 'reporte':
                            $status = MjEventOccurrences::STATUS_REPORTE;
                            break;
                        case 'planned':
                        case 'pending':
                        case 'a_confirmer':
                        default:
                            $status = MjEventOccurrences::STATUS_A_CONFIRMER;
                            break;
                    }
                }
            }

            if ($status === MjEventOccurrences::STATUS_SUPPRIME) {
                continue;
            }

            if (!isset($allowed_statuses[$status])) {
                continue;
            }

            $start = self::to_datetime(isset($row['start_at']) ? $row['start_at'] : '');
            $end = self::to_datetime(isset($row['end_at']) ? $row['end_at'] : '');
            if (!$start || !$end || $end <= $start) {
                continue;
            }

            $source = isset($row['source']) ? sanitize_key((string) $row['source']) : 'manual';
            if ($source === '') {
                $source = 'manual';
            }

            $occurrence = self::format_occurrence($start, $end, $source, $status, $now_ts);
            $occurrence['id'] = isset($row['id']) ? (int) $row['id'] : 0;
            $occurrence['event_id'] = isset($row['event_id']) ? (int) $row['event_id'] : 0;
            if (!empty($meta)) {
                $occurrence['meta'] = $meta;
            }

            $occurrences[] = $occurrence;
        }

        if (empty($occurrences)) {
            return array();
        }

        usort(
            $occurrences,
            static function ($left, $right) {
                return (int) $left['timestamp'] <=> (int) $right['timestamp'];
            }
        );

        return $occurrences;
    }

    /**
     * @param array<int,array<string,mixed>> $occurrences
     * @param array<string,mixed> $args
     * @return array<int,array<string,mixed>>
     */
    private static function filter_occurrences(array $occurrences, array $args) {
        $since_ts = self::normalize_boundary(isset($args['since']) ? $args['since'] : null);
        $until_ts = self::normalize_boundary(isset($args['until']) ? $args['until'] : null);
        $include_past = !empty($args['include_past']);
        $now_ts = current_time('timestamp');

        $filtered = array();
        foreach ($occurrences as $occurrence) {
            $start_ts = isset($occurrence['timestamp']) ? (int) $occurrence['timestamp'] : 0;
            if ($start_ts <= 0) {
                continue;
            }

            if ($since_ts !== null && $start_ts < $since_ts) {
                continue;
            }

            if ($until_ts !== null && $start_ts > $until_ts) {
                continue;
            }

            if (!$include_past && $start_ts < $now_ts) {
                continue;
            }

            $occurrence['is_past'] = ($start_ts < $now_ts);
            $filtered[] = $occurrence;
        }

        return $filtered;
    }

    /**
     * @param object $event
     * @return array<string,mixed>|null
     */
    private static function build_fallback_occurrence($event) {
        $schedule_payload = array();
        if (isset($event->schedule_payload)) {
            if (is_array($event->schedule_payload)) {
                $schedule_payload = $event->schedule_payload;
            } else {
                $decoded_payload = json_decode((string) $event->schedule_payload, true);
                if (is_array($decoded_payload)) {
                    $schedule_payload = $decoded_payload;
                }
            }
        }

        if (!empty($schedule_payload)) {
            $mode = isset($schedule_payload['mode']) ? sanitize_key((string) $schedule_payload['mode']) : '';
            $version = isset($schedule_payload['version']) ? sanitize_key((string) $schedule_payload['version']) : '';

            $has_occurrence_entries = false;
            if (isset($schedule_payload['occurrences']) && is_array($schedule_payload['occurrences'])) {
                foreach ($schedule_payload['occurrences'] as $entry) {
                    if (is_array($entry) && !empty($entry)) {
                        $has_occurrence_entries = true;
                        break;
                    }
                }
            }

            $has_series_items = false;
            if (isset($schedule_payload['items']) && is_array($schedule_payload['items'])) {
                foreach ($schedule_payload['items'] as $entry) {
                    if (is_array($entry) && !empty($entry)) {
                        $has_series_items = true;
                        break;
                    }
                }
            }

            if (($version === 'occurrence-editor' || $mode === 'series') && !$has_occurrence_entries && !$has_series_items) {
                return null;
            }
        }

        $start = self::to_datetime(isset($event->date_debut) ? $event->date_debut : '');
        if (!$start) {
            return null;
        }

        $end = self::to_datetime(isset($event->date_fin) ? $event->date_fin : '');
        if (!$end || $end <= $start) {
            $end = clone $start;
            $end->modify('+1 hour');
        }

        return self::format_occurrence(
            $start,
            $end,
            'fallback',
            MjEventOccurrences::STATUS_ACTIVE,
            current_time('timestamp')
        );
    }

    /**
     * @param mixed $statuses
     * @param bool $include_cancelled
     * @return array<string,bool>
     */
    private static function normalize_status_filter($statuses, $include_cancelled) {
        $normalized = array();

        if (is_array($statuses)) {
            foreach ($statuses as $status) {
                $status = sanitize_key((string) $status);
                if ($status !== '' && $status !== MjEventOccurrences::STATUS_SUPPRIME) {
                    $normalized[$status] = true;
                }
            }
        }

        if ($include_cancelled) {
            $normalized[MjEventOccurrences::STATUS_ANNULE] = true;
        }

        if (empty($normalized)) {
            $normalized = array(
                MjEventOccurrences::STATUS_ACTIVE => true,
                MjEventOccurrences::STATUS_A_CONFIRMER => true,
                MjEventOccurrences::STATUS_REPORTE => true,
            );
        }

        return $normalized;
    }

    /**
     * @param object|array|null $event
     * @return object|null
     */
    private static function to_event_object($event) {
        if (is_object($event)) {
            return $event;
        }

        if (is_array($event) && !empty($event)) {
            return (object) $event;
        }

        return null;
    }

    /**
     * @param mixed $value
     * @return DateTime|null
     */
    private static function to_datetime($value) {
        if ($value instanceof DateTimeInterface) {
            $instance = new DateTime('@' . $value->getTimestamp());
            $instance->setTimezone(wp_timezone());
            return $instance;
        }

        $candidate = trim((string) $value);
        if ($candidate === '') {
            return null;
        }

        $timezone = wp_timezone();
        $parsed = DateTime::createFromFormat('Y-m-d H:i:s', $candidate, $timezone);
        if ($parsed instanceof DateTime) {
            return $parsed;
        }

        $timestamp = strtotime($candidate);
        if ($timestamp === false) {
            return null;
        }

        $datetime = new DateTime('@' . $timestamp);
        $datetime->setTimezone($timezone);

        return $datetime;
    }

    /**
     * @param mixed $value
     * @return int|null
     */
    private static function normalize_boundary($value) {
        if ($value === null) {
            return null;
        }

        $date = self::to_datetime($value);
        return $date ? $date->getTimestamp() : null;
    }

    /**
     * @param DateTime $start
     * @param DateTime $end
     * @param string $source
     * @param string $status
     * @param int $now_ts
     * @return array<string,mixed>
     */
    private static function format_occurrence(DateTime $start, DateTime $end, $source, $status, $now_ts) {
        $start_ts = $start->getTimestamp();
        $end_ts = $end->getTimestamp();

        $date_format = get_option('date_format', 'd/m/Y');
        $time_format = get_option('time_format', 'H:i');

        $label = wp_date($date_format, $start_ts) . ' - ' . wp_date($time_format, $start_ts);
        if ($end_ts > $start_ts) {
            if (wp_date('Ymd', $start_ts) === wp_date('Ymd', $end_ts)) {
                $label .= ' -> ' . wp_date($time_format, $end_ts);
            } else {
                $label .= ' -> ' . wp_date($date_format, $end_ts);
            }
        }

        return array(
            'start' => $start->format('Y-m-d H:i:s'),
            'end' => $end->format('Y-m-d H:i:s'),
            'timestamp' => $start_ts,
            'duration' => max(0, $end_ts - $start_ts),
            'label' => $label,
            'source' => sanitize_key((string) $source),
            'status' => sanitize_key((string) $status),
            'is_past' => ($start_ts < $now_ts),
        );
    }
}
