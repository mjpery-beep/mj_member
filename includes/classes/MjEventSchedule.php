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
            'ignore_persisted' => false,
        );
        $args = wp_parse_args($args, $defaults);

        $mode = isset($event->schedule_mode) ? sanitize_key($event->schedule_mode) : 'fixed';
        if (!in_array($mode, array('fixed', 'range', 'recurring', 'series'), true)) {
            $mode = 'fixed';
        }

        $occurrences = array();
        if (empty($args['ignore_persisted'])) {
            $occurrences = self::load_persisted_occurrences($event, $mode, $args);
        }
        if (empty($occurrences)) {
            $occurrences = self::build_occurrences_from_schedule($event, $mode, $args);
        }

        if (empty($occurrences)) {
            return array();
        }

        $filtered = self::filter_occurrences($occurrences, $args);
        if (empty($filtered)) {
            return array();
        }

        $max = max(1, (int) $args['max']);
        if (count($filtered) > $max) {
            $filtered = array_slice($filtered, 0, $max);
        }

        return array_values($filtered);
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
     * @param object $event
     * @return array<string,mixed>
     */
    private static function resolve_payload($event) {
        if (!isset($event->schedule_payload) || empty($event->schedule_payload)) {
            return array();
        }

        if (is_array($event->schedule_payload)) {
            return $event->schedule_payload;
        }

        $decoded = json_decode((string) $event->schedule_payload, true);
        return is_array($decoded) ? $decoded : array();
    }

    /**
     * @param object $event
     * @param string $mode
     * @param array<string,mixed> $args
     * @return array<int,array<string,mixed>>
     */
    private static function build_occurrences_from_schedule($event, $mode, array $args) {
        switch ($mode) {
            case 'range':
                return self::build_range_occurrences($event);
            case 'recurring':
                return self::build_recurring_occurrences($event, $args);
            case 'series':
                return self::build_series_occurrences($event);
            case 'fixed':
            default:
                return self::build_fixed_occurrence($event);
        }
    }

    /**
     * @param object $event
     * @return array<int,array<string,mixed>>
     */
    public static function build_all_occurrences($event) {
        $event = self::to_event_object($event);
        if (!$event) {
            return array();
        }

        $mode = isset($event->schedule_mode) ? sanitize_key($event->schedule_mode) : 'fixed';
        if (!in_array($mode, array('fixed', 'range', 'recurring', 'series'), true)) {
            $mode = 'fixed';
        }

        return self::build_occurrences_from_schedule($event, $mode, array());
    }

    /**
     * @param object $event
     * @param string $mode
     * @return array<int,array<string,mixed>>
     */
    private static function load_persisted_occurrences($event, $mode, array $args) {
        if (!isset($event->id) || !class_exists('Mj\\Member\\Classes\\Crud\\MjEventOccurrences')) {
            return array();
        }

        $event_id = (int) $event->id;
        if ($event_id <= 0) {
            return array();
        }

        if ($mode === 'recurring') {
            $payload = self::resolve_payload($event);
            if (self::has_weekday_time_overrides($payload)) {
                return array();
            }
        }

        $rows = MjEventOccurrences::get_for_event($event_id);
        if (empty($rows)) {
            return array();
        }

        $occurrences = array();
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $start_value = isset($row['start_at']) ? $row['start_at'] : '';
            $end_value = isset($row['end_at']) ? $row['end_at'] : '';
            $start = self::to_datetime($start_value);
            $end = self::to_datetime($end_value);
            if (!$start || !$end) {
                continue;
            }

            $source = isset($row['source']) ? sanitize_key((string) $row['source']) : $mode;
            if ($source === '') {
                $source = $mode;
            }

            $occurrences[] = self::format_occurrence($start, $end, $source);
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
     * @param object $event
     * @return array<int,array<string,mixed>>
     */
    private static function build_fixed_occurrence($event) {
        $start = self::to_datetime(isset($event->date_debut) ? $event->date_debut : '');
        if (!$start) {
            return array();
        }

        $end = self::to_datetime(isset($event->date_fin) ? $event->date_fin : '');
        if (!$end || $end <= $start) {
            $end = clone $start;
            $end->modify('+1 hour');
        }

        return array(self::format_occurrence($start, $end, 'fixed'));
    }

    /**
     * @param object $event
     * @return array<int,array<string,mixed>>
     */
    private static function build_range_occurrences($event) {
        $start = self::to_datetime(isset($event->date_debut) ? $event->date_debut : '');
        $end = self::to_datetime(isset($event->date_fin) ? $event->date_fin : '');

        if (!$start) {
            return array();
        }

        if (!$end || $end < $start) {
            $end = clone $start;
            $end->modify('+1 day');
        }

        $start_time = self::extract_time_parts($start);
        $end_time = self::extract_time_parts($end);

        $occurrences = array();
        $cursor = clone $start;
        $cursor->setTime(0, 0, 0);

        $limit = clone $end;
        $limit->setTime(0, 0, 0);

        while ($cursor <= $limit) {
            $occurrence_start = clone $cursor;
            $occurrence_start->setTime($start_time['hour'], $start_time['minute'], $start_time['second']);
            if ($occurrence_start < $start) {
                $occurrence_start = clone $start;
            }

            $occurrence_end = clone $cursor;
            $occurrence_end->setTime($end_time['hour'], $end_time['minute'], $end_time['second']);
            if ($occurrence_end <= $occurrence_start) {
                $occurrence_end = clone $occurrence_start;
                $occurrence_end->modify('+1 hour');
            }

            $occurrences[] = self::format_occurrence($occurrence_start, $occurrence_end, 'range');
            $cursor->modify('+1 day');
        }

        return $occurrences;
    }

    /**
     * @param object $event
     * @return array<int,array<string,mixed>>
     */
    private static function build_series_occurrences($event) {
        $payload = self::resolve_payload($event);
        if (empty($payload['items']) || !is_array($payload['items'])) {
            return array();
        }

        $timezone = wp_timezone();
        $occurrences = array();

        foreach ($payload['items'] as $item) {
            if (!is_array($item)) {
                continue;
            }

            $date_value = isset($item['date']) ? sanitize_text_field((string) $item['date']) : '';
            $start_time_value = isset($item['start_time']) ? sanitize_text_field((string) $item['start_time']) : '';
            $end_time_value = isset($item['end_time']) ? sanitize_text_field((string) $item['end_time']) : '';

            if ($date_value === '' || $start_time_value === '') {
                continue;
            }

            $start_candidate = DateTime::createFromFormat('Y-m-d H:i:s', $date_value . ' ' . self::ensure_time_format($start_time_value), $timezone);
            if (!$start_candidate instanceof DateTime) {
                $start_candidate = self::to_datetime($date_value . ' ' . self::ensure_time_format($start_time_value));
            }
            if (!$start_candidate instanceof DateTime) {
                continue;
            }

            $end_candidate = null;
            if ($end_time_value !== '') {
                $end_candidate = DateTime::createFromFormat('Y-m-d H:i:s', $date_value . ' ' . self::ensure_time_format($end_time_value), $timezone);
                if (!$end_candidate instanceof DateTime) {
                    $end_candidate = self::to_datetime($date_value . ' ' . self::ensure_time_format($end_time_value));
                }
            }
            if (!$end_candidate instanceof DateTime) {
                $end_candidate = clone $start_candidate;
                $end_candidate->modify('+1 hour');
            }

            if ($end_candidate <= $start_candidate) {
                continue;
            }

            $occurrences[] = self::format_occurrence($start_candidate, $end_candidate, 'series');
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
     * @param object $event
     * @param array<string,mixed> $args
     * @return array<int,array<string,mixed>>
     */
    private static function build_recurring_occurrences($event, $args) {
        $payload = self::resolve_payload($event);

        $start_date = isset($payload['start_date']) ? sanitize_text_field($payload['start_date']) : '';
        $start_time_raw = isset($payload['start_time']) ? sanitize_text_field($payload['start_time']) : '';
        $end_time_raw = isset($payload['end_time']) ? sanitize_text_field($payload['end_time']) : '';
        $frequency = isset($payload['frequency']) ? sanitize_key($payload['frequency']) : 'weekly';

        if ($frequency === 'weekly') {
            if ($start_time_raw === '') {
                $derived_start = self::resolve_weekday_time_value($payload, 'start');
                if ($derived_start !== null) {
                    $start_time_raw = $derived_start;
                }
            }

            if ($end_time_raw === '') {
                $derived_end = self::resolve_weekday_time_value($payload, 'end');
                if ($derived_end !== null) {
                    $end_time_raw = $derived_end;
                }
            }
        }

        $first_start = null;
        if ($start_date !== '' && $start_time_raw !== '') {
            $first_start = self::to_datetime($start_date . ' ' . self::ensure_time_format($start_time_raw));
        }

        if (!$first_start) {
            $first_start = self::to_datetime(isset($event->date_debut) ? $event->date_debut : '');
        }

        if (!$first_start) {
            return array();
        }

        $first_end = null;
        if ($start_date !== '' && $end_time_raw !== '') {
            $first_end = self::to_datetime($start_date . ' ' . self::ensure_time_format($end_time_raw));
        }
        if (!$first_end) {
            $first_end = self::to_datetime(isset($event->date_fin) ? $event->date_fin : '');
        }
        if (!$first_end || $first_end <= $first_start) {
            $first_end = clone $first_start;
            $first_end->modify('+1 hour');
        }

        $interval = isset($payload['interval']) ? max(1, (int) $payload['interval']) : 1;

        $limit = null;
        if (!empty($event->recurrence_until)) {
            $limit = self::to_datetime($event->recurrence_until);
        }
        if (!$limit) {
            $limit = clone $first_start;
            $limit->modify('+6 months');
        }

        $occurrences = array();
        if ($frequency === 'monthly') {
            $occurrences = self::build_monthly_occurrences($first_start, $first_end, $payload, $interval, $limit);
        } else {
            $occurrences = self::build_weekly_occurrences($first_start, $first_end, $payload, $interval, $limit);
        }

        $exceptions = self::normalize_recurrence_exceptions(isset($payload['exceptions']) ? $payload['exceptions'] : array());
        if (!empty($exceptions)) {
            foreach ($occurrences as &$occurrence) {
                if (!is_array($occurrence) || empty($occurrence['start'])) {
                    continue;
                }

                $start = (string) $occurrence['start'];
                $date = substr($start, 0, 10);
                if ($date === '' || !isset($exceptions[$date])) {
                    continue;
                }

                $reason = '';
                if (isset($exceptions[$date]['reason']) && is_string($exceptions[$date]['reason'])) {
                    $reason = $exceptions[$date]['reason'];
                }

                $occurrence['is_cancelled'] = true;
                $occurrence['status'] = 'cancelled';
                if ($reason !== '') {
                    $occurrence['cancellation_reason'] = $reason;
                }

                if (!isset($occurrence['meta']) || !is_array($occurrence['meta'])) {
                    $occurrence['meta'] = array();
                }
                $occurrence['meta']['status'] = 'cancelled';
                if ($reason !== '') {
                    $occurrence['meta']['cancellation_reason'] = $reason;
                }
            }
            unset($occurrence);
        }

        return $occurrences;
    }

    /**
     * @param DateTime $first_start
     * @param DateTime $first_end
     * @param array<string,mixed> $payload
     * @param int $interval
     * @param DateTime $limit
     * @return array<int,array<string,mixed>>
     */
    private static function build_weekly_occurrences($first_start, $first_end, $payload, $interval, $limit) {
        $weekdays = array();
        if (!empty($payload['weekdays']) && is_array($payload['weekdays'])) {
            foreach ($payload['weekdays'] as $weekday) {
                $weekday = sanitize_key($weekday);
                $weekday_num = self::weekday_to_number($weekday);
                if ($weekday_num !== null) {
                    $weekdays[$weekday_num] = $weekday_num;
                }
            }
        }
        if (empty($weekdays)) {
            $weekdays[self::weekday_to_number(strtolower($first_start->format('l')))] = self::weekday_to_number(strtolower($first_start->format('l')));
        }
        ksort($weekdays);

        $default_start_time = self::extract_time_parts($first_start);
        $default_end_time = self::extract_time_parts($first_end);
        $default_duration_seconds = max(3600, max(0, $first_end->getTimestamp() - $first_start->getTimestamp()));

        $weekday_time_overrides = array();
        if (!empty($payload['weekday_times']) && is_array($payload['weekday_times'])) {
            foreach ($payload['weekday_times'] as $weekday_key => $time_info) {
                $weekday_key = sanitize_key($weekday_key);
                $weekday_num = self::weekday_to_number($weekday_key);
                if ($weekday_num === null || !is_array($time_info)) {
                    continue;
                }

                $override = array();

                if (isset($time_info['start'])) {
                    $start_parts = self::time_string_to_parts($time_info['start']);
                    if ($start_parts !== null) {
                        $override['start'] = $start_parts;
                    }
                }

                if (isset($time_info['end'])) {
                    $end_parts = self::time_string_to_parts($time_info['end']);
                    if ($end_parts !== null) {
                        $override['end'] = $end_parts;
                    }
                }

                if (!empty($override)) {
                    $weekday_time_overrides[$weekday_num] = $override;
                }
            }
        }

        $start_of_week = clone $first_start;
        $start_of_week->setTime(0, 0, 0);
        $start_of_week->modify('monday this week');

        $occurrences = array();
        $week_offset = 0;

        while ($week_offset < 520) {
            $current_week = clone $start_of_week;
            if ($week_offset > 0) {
                $current_week->modify('+' . ($week_offset * $interval) . ' weeks');
            }

            foreach ($weekdays as $weekday_num) {
                $occurrence_start = clone $current_week;
                $occurrence_start->modify('+' . ($weekday_num - 1) . ' days');
                $start_parts = $default_start_time;
                if (isset($weekday_time_overrides[$weekday_num]['start'])) {
                    $start_parts = $weekday_time_overrides[$weekday_num]['start'];
                }

                $occurrence_start->setTime($start_parts['hour'], $start_parts['minute'], $start_parts['second']);

                if ($occurrence_start < $first_start) {
                    continue;
                }
                if ($occurrence_start > $limit) {
                    break 2;
                }

                $occurrence_end = clone $occurrence_start;
                $end_parts = $default_end_time;
                $has_end_override = false;
                if (isset($weekday_time_overrides[$weekday_num]['end'])) {
                    $end_parts = $weekday_time_overrides[$weekday_num]['end'];
                    $has_end_override = true;
                }

                $occurrence_end->setTime($end_parts['hour'], $end_parts['minute'], $end_parts['second']);
                if ($occurrence_end <= $occurrence_start) {
                    $occurrence_end = clone $occurrence_start;
                    if ($has_end_override) {
                        $occurrence_end->modify('+1 hour');
                    } else {
                        $occurrence_end->modify('+' . $default_duration_seconds . ' seconds');
                    }
                }

                $occurrences[] = self::format_occurrence($occurrence_start, $occurrence_end, 'recurring');
            }

            if (count($occurrences) > 300) {
                break;
            }

            $week_offset++;
        }

        return $occurrences;
    }

    /**
     * @param DateTime $first_start
     * @param DateTime $first_end
     * @param array<string,mixed> $payload
     * @param int $interval
     * @param DateTime $limit
     * @return array<int,array<string,mixed>>
     */
    private static function build_monthly_occurrences($first_start, $first_end, $payload, $interval, $limit) {
        $ordinal = isset($payload['ordinal']) ? sanitize_key($payload['ordinal']) : 'first';
        $weekday = isset($payload['weekday']) ? sanitize_key($payload['weekday']) : strtolower($first_start->format('l'));

        if (!in_array($ordinal, array('first', 'second', 'third', 'fourth', 'last'), true)) {
            $ordinal = 'first';
        }
        if (self::weekday_to_number($weekday) === null) {
            $weekday = strtolower($first_start->format('l'));
        }

        $start_time = self::extract_time_parts($first_start);
        $end_time = self::extract_time_parts($first_end);

        $cursor = clone $first_start;
        $cursor->setTime(0, 0, 0);
        $cursor->modify('first day of this month');

        $occurrences = array();
        $safety = 0;

        while ($cursor <= $limit && $safety < 120) {
            $occurrence_start = self::resolve_monthly_occurrence((clone $cursor), $ordinal, $weekday);
            if ($occurrence_start < $first_start) {
                $cursor->modify('+1 month');
                $cursor->modify('first day of this month');
                $safety++;
                continue;
            }

            if ($occurrence_start > $limit) {
                break;
            }

            $occurrence_start->setTime($start_time['hour'], $start_time['minute'], $start_time['second']);
            $occurrence_end = clone $occurrence_start;
            $occurrence_end->setTime($end_time['hour'], $end_time['minute'], $end_time['second']);
            if ($occurrence_end <= $occurrence_start) {
                $occurrence_end->modify('+1 hour');
            }

            $occurrences[] = self::format_occurrence($occurrence_start, $occurrence_end, 'recurring');

            $cursor->modify('+' . $interval . ' months');
            $cursor->modify('first day of this month');
            $safety++;
        }

        return $occurrences;
    }

    /**
     * @param mixed $value
     * @return array<string,array<string,string>>
     */
    private static function normalize_recurrence_exceptions($value) {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $value = $decoded;
            }
        }

        if (!is_array($value)) {
            return array();
        }

        $timezone = wp_timezone();
        $normalized = array();

        foreach ($value as $item) {
            if ($item === null) {
                continue;
            }

            if (is_object($item)) {
                $item = get_object_vars($item);
            }

            $date_raw = '';
            $reason_raw = '';

            if (is_array($item)) {
                if (isset($item['date'])) {
                    $date_raw = $item['date'];
                } elseif (isset($item[0])) {
                    $date_raw = $item[0];
                }
                if (isset($item['reason'])) {
                    $reason_raw = $item['reason'];
                }
            } else {
                $date_raw = $item;
            }

            if (!is_scalar($date_raw)) {
                continue;
            }

            $raw_date = sanitize_text_field((string) $date_raw);
            if ($raw_date === '') {
                continue;
            }

            $date = DateTime::createFromFormat('Y-m-d', $raw_date, $timezone);
            if (!$date instanceof DateTime) {
                continue;
            }

            $key = $date->format('Y-m-d');

            $reason_value = '';
            if (is_scalar($reason_raw)) {
                $reason_value = sanitize_text_field((string) $reason_raw);
                if ($reason_value !== '') {
                    $reason_value = wp_strip_all_tags($reason_value, false);
                    if ($reason_value !== '') {
                        if (function_exists('mb_substr')) {
                            $reason_value = mb_substr($reason_value, 0, 200);
                        } else {
                            $reason_value = substr($reason_value, 0, 200);
                        }
                        $reason_value = trim($reason_value);
                    }
                }
            }

            $entry = array('date' => $key);
            if ($reason_value !== '') {
                $entry['reason'] = $reason_value;
            }

            $normalized[$key] = $entry;

            if (count($normalized) >= 730) {
                break;
            }
        }

        return $normalized;
    }

    /**
     * @param DateTime $month_start
     * @param string $ordinal
     * @param string $weekday
     * @return DateTime
     */
    private static function resolve_monthly_occurrence($month_start, $ordinal, $weekday) {
        $month_start->setTime(0, 0, 0);
        $weekday = strtolower($weekday);

        switch ($ordinal) {
            case 'second':
                $month_start->modify('second ' . $weekday . ' of this month');
                break;
            case 'third':
                $month_start->modify('third ' . $weekday . ' of this month');
                break;
            case 'fourth':
                $month_start->modify('fourth ' . $weekday . ' of this month');
                break;
            case 'last':
                $month_start->modify('last ' . $weekday . ' of this month');
                break;
            case 'first':
            default:
                $month_start->modify('first ' . $weekday . ' of this month');
                break;
        }

        return $month_start;
    }

    /**
     * @param array<int,array<string,mixed>> $occurrences
     * @param array<string,mixed> $args
     * @return array<int,array<string,mixed>>
     */
    private static function filter_occurrences(array $occurrences, array $args) {
        $since_ts = self::to_timestamp($args['since']);
        $until_ts = self::to_timestamp($args['until']);
        $include_past = !empty($args['include_past']);

        $now_ts = current_time('timestamp');
        if (!$include_past) {
            $since_ts = max($since_ts !== null ? $since_ts : $now_ts, $now_ts);
        }

        $filtered = array();
        foreach ($occurrences as $occurrence) {
            if (!isset($occurrence['timestamp'])) {
                continue;
            }

            $start_ts = (int) $occurrence['timestamp'];
            if ($since_ts !== null && $start_ts < $since_ts) {
                continue;
            }
            if ($until_ts !== null && $start_ts > $until_ts) {
                continue;
            }

            $occurrence['is_past'] = ($start_ts < $now_ts);
            $filtered[] = $occurrence;
        }

        usort(
            $filtered,
            static function ($a, $b) {
                return (int) $a['timestamp'] <=> (int) $b['timestamp'];
            }
        );

        return $filtered;
    }

    /**
     * @param DateTime $start
     * @param DateTime $end
     * @return array<string,mixed>
     */
    private static function format_occurrence($start, $end, $source = '') {
        $start_ts = $start->getTimestamp();
        $end_ts = $end->getTimestamp();

        $date_format = get_option('date_format', 'd/m/Y');
        $time_format = get_option('time_format', 'H:i');

        $label = date_i18n($date_format, $start_ts) . ' - ' . date_i18n($time_format, $start_ts);
        if ($end_ts > $start_ts) {
            if (date_i18n('Ymd', $start_ts) === date_i18n('Ymd', $end_ts)) {
                $label .= ' -> ' . date_i18n($time_format, $end_ts);
            } else {
                $label .= ' -> ' . date_i18n($date_format, $end_ts);
            }
        }

        $occurrence = array(
            'start' => $start->format('Y-m-d H:i:s'),
            'end' => $end->format('Y-m-d H:i:s'),
            'label' => $label,
            'timestamp' => $start_ts,
            'is_past' => ($start_ts < current_time('timestamp')),
        );

        $source_value = is_string($source) ? sanitize_key($source) : '';
        if ($source_value !== '') {
            $occurrence['source'] = $source_value;
        }

        return $occurrence;
    }

    /**
     * @param mixed $value
     * @return DateTime|null
     */
    private static function to_datetime($value) {
        if ($value instanceof DateTimeInterface) {
            $clone = new DateTime('@' . $value->getTimestamp());
            $clone->setTimezone(wp_timezone());
            return $clone;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $timezone = wp_timezone();
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
     * @param DateTime $date
     * @return array<string,int>
     */
    private static function extract_time_parts($date) {
        return array(
            'hour' => (int) $date->format('H'),
            'minute' => (int) $date->format('i'),
            'second' => (int) $date->format('s'),
        );
    }

    /**
     * @param mixed $time
     * @return string
     */
    private static function ensure_time_format($time) {
        $time = trim((string) $time);
        if ($time === '') {
            return '00:00:00';
        }

        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) {
            return $time;
        }

        if (preg_match('/^\d{2}:\d{2}$/', $time)) {
            return $time . ':00';
        }

        return '00:00:00';
    }

    /**
     * @param mixed $time
     * @return array<string,int>|null
     */
    private static function time_string_to_parts($time) {
        $time = trim((string) $time);
        if ($time === '') {
            return null;
        }

        $normalized = self::ensure_time_format($time);
        $segments = explode(':', $normalized);
        if (count($segments) !== 3) {
            return null;
        }

        return array(
            'hour' => (int) $segments[0],
            'minute' => (int) $segments[1],
            'second' => (int) $segments[2],
        );
    }

    private static function resolve_weekday_time_value(array $payload, $field) {
        if (empty($payload['weekday_times']) || !is_array($payload['weekday_times'])) {
            return null;
        }

        $field = $field === 'end' ? 'end' : 'start';
        $weekday_times = $payload['weekday_times'];

        $preferred_order = array('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday');
        $lookup_keys = $field === 'start'
            ? array('start', 'start_time', 'startTime', 'from')
            : array('end', 'end_time', 'endTime', 'to');

        foreach ($preferred_order as $weekday_key) {
            if (!isset($weekday_times[$weekday_key]) || !is_array($weekday_times[$weekday_key])) {
                continue;
            }

            $candidate = self::extract_time_from_weekday_info($weekday_times[$weekday_key], $lookup_keys);
            if ($candidate !== null) {
                return sprintf('%02d:%02d', $candidate['hour'], $candidate['minute']);
            }
        }

        foreach ($weekday_times as $info) {
            if (!is_array($info)) {
                continue;
            }

            $candidate = self::extract_time_from_weekday_info($info, $lookup_keys);
            if ($candidate !== null) {
                return sprintf('%02d:%02d', $candidate['hour'], $candidate['minute']);
            }
        }

        return null;
    }

    private static function extract_time_from_weekday_info(array $info, array $keys) {
        foreach ($keys as $key) {
            if (!isset($info[$key])) {
                continue;
            }

            $raw = trim((string) $info[$key]);
            if ($raw === '') {
                continue;
            }

            $parts = self::time_string_to_parts($raw);
            if ($parts !== null) {
                return $parts;
            }
        }

        return null;
    }

    /**
     * @param string $weekday
     * @return int|null
     */
    private static function weekday_to_number($weekday) {
        $map = array(
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6,
            'sunday' => 7,
        );

        $weekday = strtolower($weekday);
        return isset($map[$weekday]) ? $map[$weekday] : null;
    }

    /**
     * @param array<string,mixed> $payload
     * @return bool
     */
    private static function has_weekday_time_overrides(array $payload) {
        if (empty($payload['weekday_times']) || !is_array($payload['weekday_times'])) {
            return false;
        }

        foreach ($payload['weekday_times'] as $time_info) {
            if (!is_array($time_info)) {
                continue;
            }

            $start = isset($time_info['start']) ? trim((string) $time_info['start']) : '';
            $end = isset($time_info['end']) ? trim((string) $time_info['end']) : '';
            if ($start !== '' || $end !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param mixed $value
     * @return int|null
     */
    private static function to_timestamp($value) {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->getTimestamp();
        }

        if ($value === 'now') {
            return current_time('timestamp');
        }

        $date = self::to_datetime($value);
        return $date ? $date->getTimestamp() : null;
    }
}

\class_alias(__NAMESPACE__ . '\\MjEventSchedule', 'MjEventSchedule');
