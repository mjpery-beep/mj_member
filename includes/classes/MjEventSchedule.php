<?php

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
        );
        $args = wp_parse_args($args, $defaults);

        $mode = isset($event->schedule_mode) ? sanitize_key($event->schedule_mode) : 'fixed';
        if (!in_array($mode, array('fixed', 'range', 'recurring'), true)) {
            $mode = 'fixed';
        }

        switch ($mode) {
            case 'range':
                $occurrences = self::build_range_occurrences($event);
                break;
            case 'recurring':
                $occurrences = self::build_recurring_occurrences($event, $args);
                break;
            case 'fixed':
            default:
                $occurrences = self::build_fixed_occurrence($event);
                break;
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

        return array(self::format_occurrence($start, $end));
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

            $occurrences[] = self::format_occurrence($occurrence_start, $occurrence_end);
            $cursor->modify('+1 day');
        }

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

        $frequency = isset($payload['frequency']) ? sanitize_key($payload['frequency']) : 'weekly';
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

        $start_time = self::extract_time_parts($first_start);
        $end_time = self::extract_time_parts($first_end);

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
                $occurrence_start->setTime($start_time['hour'], $start_time['minute'], $start_time['second']);

                if ($occurrence_start < $first_start) {
                    continue;
                }
                if ($occurrence_start > $limit) {
                    break 2;
                }

                $occurrence_end = clone $occurrence_start;
                $occurrence_end->setTime($end_time['hour'], $end_time['minute'], $end_time['second']);
                if ($occurrence_end <= $occurrence_start) {
                    $occurrence_end->modify('+1 hour');
                }

                $occurrences[] = self::format_occurrence($occurrence_start, $occurrence_end);
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

            $occurrences[] = self::format_occurrence($occurrence_start, $occurrence_end);

            $cursor->modify('+' . $interval . ' months');
            $cursor->modify('first day of this month');
            $safety++;
        }

        return $occurrences;
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
    private static function format_occurrence($start, $end) {
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

        return array(
            'start' => $start->format('Y-m-d H:i:s'),
            'end' => $end->format('Y-m-d H:i:s'),
            'label' => $label,
            'timestamp' => $start_ts,
            'is_past' => ($start_ts < current_time('timestamp')),
        );
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
