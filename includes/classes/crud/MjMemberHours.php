<?php

namespace Mj\Member\Classes\Crud;

use Mj\Member\Classes\MjTools;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class MjMemberHours extends MjTools implements CrudRepositoryInterface {
    private const TABLE = 'mj_member_hours';
    public const PROJECT_EMPTY_KEY = '__mj_member_hours_project_none__';

    /**
     * @return string
     */
    private static function table_name() {
        return self::getTableName(self::TABLE);
    }

    /**
     * @return string
     */
    public static function tableName() {
        return self::table_name();
    }

    /**
     * @return string
     */
    private static function projects_table(): string
    {
        if (function_exists('mj_member_get_todo_projects_table_name')) {
            return mj_member_get_todo_projects_table_name();
        }

        return self::getTableName('mj_projects');
    }

    /**
     * @param array<string,mixed> $args
     * @return array<int,array<string,mixed>>
     */
    public static function get_all(array $args = array()) {
        global $wpdb;
        $table = self::table_name();

        $defaults = array(
            'member_id' => 0,
            'member_ids' => array(),
            'recorded_by' => 0,
            'task_keys' => array(),
            'search' => '',
            'date_from' => '',
            'date_to' => '',
            'project' => null,
            'project_id' => 0,
            'orderby' => 'activity_date',
            'order' => 'DESC',
            'limit' => 0,
            'offset' => 0,
        );
        $args = wp_parse_args($args, $defaults);

        $builder = CrudQueryBuilder::for_table($table);

        $memberIds = array();
        if (!empty($args['member_id'])) {
            $memberIds[] = (int) $args['member_id'];
        }
        if (!empty($args['member_ids'])) {
            $memberIds = array_merge($memberIds, (array) $args['member_ids']);
        }
        if (!empty($memberIds)) {
            $builder->where_in_int('member_id', $memberIds);
        }

        if (!empty($args['recorded_by'])) {
            $builder->where_equals_int('recorded_by', (int) $args['recorded_by']);
        }

        if (!empty($args['task_keys'])) {
            $builder->where_in_strings('task_key', (array) $args['task_keys'], 'sanitize_key');
        }

        $dateFrom = self::normalize_date($args['date_from']);
        if ($dateFrom !== '') {
            $builder->where_compare('activity_date', '>=', $dateFrom, '%s');
        }

        $dateTo = self::normalize_date($args['date_to']);
        if ($dateTo !== '') {
            $builder->where_compare('activity_date', '<=', $dateTo, '%s');
        }

        $projectId = self::normalize_project_id($args['project_id'] ?? 0);
        if ($projectId > 0) {
            $builder->where_equals_int('project_id', $projectId);
        } else {
            $projectFilter = self::normalize_project_filter($args['project'] ?? null);
            if ($projectFilter !== null) {
                if ($projectFilter === self::PROJECT_EMPTY_KEY) {
                    $builder->where_raw('(notes IS NULL OR notes = "")');
                } else {
                    $builder->where_equals('notes', $projectFilter, 'sanitize_text_field');
                }
            }
        }

        if (!empty($args['search'])) {
            $builder->where_like_any(array('task_label', 'notes'), $args['search']);
        }

        $allowedOrderBy = array('activity_date', 'created_at', 'updated_at', 'duration_minutes');
        $orderby = sanitize_key($args['orderby']);
        if (!in_array($orderby, $allowedOrderBy, true)) {
            $orderby = 'activity_date';
        }

        $order = strtoupper((string) $args['order']) === 'ASC' ? 'ASC' : 'DESC';
        $limit = (int) $args['limit'];
        $offset = max(0, (int) $args['offset']);

        list($sql, $params) = $builder->build_select('*', $orderby, $order, $limit, $offset);
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        $rows = $wpdb->get_results($sql);
        if (!is_array($rows) || empty($rows)) {
            return array();
        }

        return array_map(array(__CLASS__, 'format_row'), $rows);
    }

    /**
     * @param array<string,mixed> $args
     * @return int
     */
    public static function count(array $args = array()) {
        global $wpdb;
        $table = self::table_name();

        $defaults = array(
            'member_id' => 0,
            'member_ids' => array(),
            'recorded_by' => 0,
            'task_keys' => array(),
            'search' => '',
            'date_from' => '',
            'date_to' => '',
            'project' => null,
            'project_id' => 0,
        );
        $args = wp_parse_args($args, $defaults);

        $builder = CrudQueryBuilder::for_table($table);

        $memberIds = array();
        if (!empty($args['member_id'])) {
            $memberIds[] = (int) $args['member_id'];
        }
        if (!empty($args['member_ids'])) {
            $memberIds = array_merge($memberIds, (array) $args['member_ids']);
        }
        if (!empty($memberIds)) {
            $builder->where_in_int('member_id', $memberIds);
        }

        if (!empty($args['recorded_by'])) {
            $builder->where_equals_int('recorded_by', (int) $args['recorded_by']);
        }

        if (!empty($args['task_keys'])) {
            $builder->where_in_strings('task_key', (array) $args['task_keys'], 'sanitize_key');
        }

        $dateFrom = self::normalize_date($args['date_from']);
        if ($dateFrom !== '') {
            $builder->where_compare('activity_date', '>=', $dateFrom, '%s');
        }

        $dateTo = self::normalize_date($args['date_to']);
        if ($dateTo !== '') {
            $builder->where_compare('activity_date', '<=', $dateTo, '%s');
        }

        $projectId = self::normalize_project_id($args['project_id'] ?? 0);
        if ($projectId > 0) {
            $builder->where_equals_int('project_id', $projectId);
        } else {
            $projectFilter = self::normalize_project_filter($args['project'] ?? null);
            if ($projectFilter !== null) {
                if ($projectFilter === self::PROJECT_EMPTY_KEY) {
                    $builder->where_raw('(notes IS NULL OR notes = "")');
                } else {
                    $builder->where_equals('notes', $projectFilter, 'sanitize_text_field');
                }
            }
        }

        if (!empty($args['search'])) {
            $builder->where_like_any(array('task_label', 'notes'), $args['search']);
        }

        list($sql, $params) = $builder->build_count('*');
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        $result = $wpdb->get_var($sql);
        return $result ? (int) $result : 0;
    }

    /**
     * @param int $id
     * @return array<string,mixed>|null
     */
    public static function get($id) {
        $id = (int) $id;
        if ($id <= 0) {
            return null;
        }

        global $wpdb;
        $table = self::table_name();

        $sql = $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id);
        $row = $wpdb->get_row($sql);
        if (!$row) {
            return null;
        }

        return self::format_row($row);
    }

    /**
     * @param array<string,mixed> $data
     * @return int|WP_Error
     */
    public static function create($data) {
        if (!is_array($data)) {
            return new WP_Error('mj_member_hours_invalid_payload', __('Format de données invalide.', 'mj-member'));
        }

        global $wpdb;
        $table = self::table_name();

        $memberId = isset($data['member_id']) ? (int) $data['member_id'] : 0;
        if ($memberId <= 0) {
            return new WP_Error('mj_member_hours_invalid_member', __('Membre invalide.', 'mj-member'));
        }

        $taskLabel = isset($data['task_label']) ? sanitize_text_field($data['task_label']) : '';
        if ($taskLabel === '') {
            return new WP_Error('mj_member_hours_missing_task', __('Intitulé de tâche requis.', 'mj-member'));
        }

        $activityDate = self::normalize_date(isset($data['activity_date']) ? $data['activity_date'] : '');
        if ($activityDate === '') {
            return new WP_Error('mj_member_hours_invalid_date', __('Date invalide.', 'mj-member'));
        }

        $startTime = isset($data['start_time']) ? self::normalize_time($data['start_time']) : '';
        $endTime = isset($data['end_time']) ? self::normalize_time($data['end_time']) : '';

        if (($startTime !== '' && $endTime === '') || ($startTime === '' && $endTime !== '')) {
            return new WP_Error('mj_member_hours_incomplete_schedule', __('Renseignez une heure de début et une heure de fin.', 'mj-member'));
        }

        $duration = isset($data['duration_minutes']) ? (int) $data['duration_minutes'] : 0;

        if ($startTime !== '' && $endTime !== '') {
            $duration = self::calculate_duration_minutes($startTime, $endTime);
            if ($duration <= 0) {
                return new WP_Error('mj_member_hours_invalid_schedule', __('L’heure de fin doit être postérieure à l’heure de début.', 'mj-member'));
            }
        }

        if ($duration <= 0) {
            return new WP_Error('mj_member_hours_invalid_duration', __('Durée invalide.', 'mj-member'));
        }

        $taskKey = isset($data['task_key']) ? sanitize_key($data['task_key']) : '';
        $taskKey = $taskKey !== '' ? $taskKey : null;

        $notes = isset($data['notes']) ? sanitize_textarea_field($data['notes']) : '';
        $notes = $notes !== '' ? $notes : null;

        $recordedBy = isset($data['recorded_by']) ? (int) $data['recorded_by'] : get_current_user_id();
        $recordedBy = $recordedBy > 0 ? $recordedBy : 0;

        $createdAt = isset($data['created_at']) ? self::normalize_datetime($data['created_at']) : current_time('mysql');

        $insertData = array(
            'member_id' => $memberId,
            'recorded_by' => $recordedBy,
            'task_label' => $taskLabel,
            'activity_date' => $activityDate,
            'duration_minutes' => $duration,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        );

        $formats = array('%d', '%d', '%s', '%s', '%d', '%s', '%s');

        if ($startTime !== '') {
            $insertData['start_time'] = $startTime;
            $formats[] = '%s';
        }

        if ($endTime !== '') {
            $insertData['end_time'] = $endTime;
            $formats[] = '%s';
        }

        if ($taskKey !== null) {
            $insertData['task_key'] = $taskKey;
            $formats[] = '%s';
        }

        if ($notes !== null) {
            $insertData['notes'] = $notes;
            $formats[] = '%s';
        }

        $result = $wpdb->insert($table, $insertData, $formats);
        if ($result === false) {
            return new WP_Error('mj_member_hours_insert_failed', __('Impossible d’enregistrer les heures.', 'mj-member'));
        }

        $insertId = (int) $wpdb->insert_id;
        $record = self::get($insertId);

        if (is_array($record)) {
            /**
             * @param array<string,mixed> $record
             */
            do_action('mj_member_hours_after_create', $insertId, $record);
            self::dispatchChangeEvent('create', $insertId, $record);
        } else {
            do_action('mj_member_hours_after_create', $insertId, null);
            self::dispatchChangeEvent('create', $insertId, array(
                'member_id' => $memberId,
                'activity_date' => $activityDate,
                'duration_minutes' => $duration,
            ));
        }

        return $insertId;
    }

    /**
     * @param int $id
     * @param array<string,mixed> $data
     * @return true|WP_Error
     */
    public static function update($id, $data) {
        $id = (int) $id;
        if ($id <= 0) {
            return new WP_Error('mj_member_hours_invalid_id', __('Identifiant invalide.', 'mj-member'));
        }

        if (!is_array($data)) {
            return new WP_Error('mj_member_hours_invalid_payload', __('Format de données invalide.', 'mj-member'));
        }

        global $wpdb;
        $table = self::table_name();

        $fields = array();
        $formats = array();

        if (array_key_exists('member_id', $data)) {
            $memberId = (int) $data['member_id'];
            if ($memberId <= 0) {
                return new WP_Error('mj_member_hours_invalid_member', __('Membre invalide.', 'mj-member'));
            }
            $fields['member_id'] = $memberId;
            $formats[] = '%d';
        }

        $durationFromSchedule = false;

        $hasStart = array_key_exists('start_time', $data);
        $hasEnd = array_key_exists('end_time', $data);

        if ($hasStart || $hasEnd) {
            $startTime = self::normalize_time($data['start_time'] ?? '');
            $endTime = self::normalize_time($data['end_time'] ?? '');

            if ($startTime === '' || $endTime === '') {
                return new WP_Error('mj_member_hours_incomplete_schedule', __('Renseignez une heure de début et une heure de fin.', 'mj-member'));
            }

            $duration = self::calculate_duration_minutes($startTime, $endTime);
            if ($duration <= 0) {
                return new WP_Error('mj_member_hours_invalid_schedule', __('L’heure de fin doit être postérieure à l’heure de début.', 'mj-member'));
            }

            $fields['start_time'] = $startTime;
            $formats[] = '%s';
            $fields['end_time'] = $endTime;
            $formats[] = '%s';
            $fields['duration_minutes'] = $duration;
            $formats[] = '%d';
            $durationFromSchedule = true;
        }

        if (array_key_exists('task_label', $data)) {
            $taskLabel = sanitize_text_field($data['task_label']);
            if ($taskLabel === '') {
                return new WP_Error('mj_member_hours_missing_task', __('Intitulé de tâche requis.', 'mj-member'));
            }
            $fields['task_label'] = $taskLabel;
            $formats[] = '%s';
        }

        if (array_key_exists('task_key', $data)) {
            $taskKey = sanitize_key($data['task_key']);
            $fields['task_key'] = $taskKey;
            $formats[] = '%s';
        }

        if (array_key_exists('activity_date', $data)) {
            $activityDate = self::normalize_date($data['activity_date']);
            if ($activityDate === '') {
                return new WP_Error('mj_member_hours_invalid_date', __('Date invalide.', 'mj-member'));
            }
            $fields['activity_date'] = $activityDate;
            $formats[] = '%s';
        }

        if (array_key_exists('duration_minutes', $data) && !$durationFromSchedule) {
            $duration = (int) $data['duration_minutes'];
            if ($duration <= 0) {
                return new WP_Error('mj_member_hours_invalid_duration', __('Durée invalide.', 'mj-member'));
            }
            $fields['duration_minutes'] = $duration;
            $formats[] = '%d';
        }

        if (array_key_exists('notes', $data)) {
            $notes = sanitize_textarea_field($data['notes']);
            $fields['notes'] = $notes;
            $formats[] = '%s';
        }

        if (array_key_exists('recorded_by', $data)) {
            $recordedBy = (int) $data['recorded_by'];
            $fields['recorded_by'] = $recordedBy > 0 ? $recordedBy : 0;
            $formats[] = '%d';
        }

        if (empty($fields)) {
            return true;
        }

        $fields['updated_at'] = current_time('mysql');
        $formats[] = '%s';

        $result = $wpdb->update($table, $fields, array('id' => $id), $formats, array('%d'));
        if ($result === false) {
            return new WP_Error('mj_member_hours_update_failed', __('Impossible de mettre à jour les heures.', 'mj-member'));
        }

        $record = self::get($id);
        if (is_array($record)) {
            /**
             * @param array<string,mixed> $record
             */
            do_action('mj_member_hours_after_update', $id, $record);
            self::dispatchChangeEvent('update', $id, $record);
        } else {
            do_action('mj_member_hours_after_update', $id, null);
            self::dispatchChangeEvent('update', $id, array(
                'member_id' => isset($fields['member_id']) ? (int) $fields['member_id'] : 0,
                'activity_date' => isset($fields['activity_date']) ? (string) $fields['activity_date'] : '',
            ));
        }

        return true;
    }

    /**
     * @param int $id
     * @return true|WP_Error
     */
    public static function delete($id) {
        $id = (int) $id;
        if ($id <= 0) {
            return new WP_Error('mj_member_hours_invalid_id', __('Identifiant invalide.', 'mj-member'));
        }

        global $wpdb;
        $table = self::table_name();

        $existing = self::get($id);
        $deleted = $wpdb->delete($table, array('id' => $id), array('%d'));
        if ($deleted === false) {
            return new WP_Error('mj_member_hours_delete_failed', __('Suppression impossible.', 'mj-member'));
        }

        /**
         * @param array<string,mixed>|null $existing
         */
        do_action('mj_member_hours_after_delete', $id, $existing);

        if (is_array($existing)) {
            self::dispatchChangeEvent('delete', $id, $existing);
        } else {
            self::dispatchChangeEvent('delete', $id, array());
        }

        return true;
    }

    /**
     * @param object $row
     * @return array<string,mixed>
     */
    private static function format_row($row) {
        return array(
            'id' => isset($row->id) ? (int) $row->id : 0,
            'member_id' => isset($row->member_id) ? (int) $row->member_id : 0,
            'recorded_by' => isset($row->recorded_by) && (int) $row->recorded_by > 0 ? (int) $row->recorded_by : null,
            'project_id' => isset($row->project_id) && (int) $row->project_id > 0 ? (int) $row->project_id : null,
            'task_key' => isset($row->task_key) && $row->task_key !== '' ? (string) $row->task_key : null,
            'task_label' => isset($row->task_label) ? (string) $row->task_label : '',
            'activity_date' => isset($row->activity_date) ? (string) $row->activity_date : '',
            'start_time' => isset($row->start_time) && $row->start_time !== null ? (string) $row->start_time : null,
            'end_time' => isset($row->end_time) && $row->end_time !== null ? (string) $row->end_time : null,
            'duration_minutes' => isset($row->duration_minutes) ? (int) $row->duration_minutes : 0,
            'notes' => isset($row->notes) && $row->notes !== '' ? (string) $row->notes : null,
            'created_at' => isset($row->created_at) ? (string) $row->created_at : '',
            'updated_at' => isset($row->updated_at) ? (string) $row->updated_at : '',
        );
    }

    private static function normalize_date($value) {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        $value = is_string($value) ? trim($value) : '';
        if ($value === '') {
            return '';
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return '';
        }

        return gmdate('Y-m-d', $timestamp);
    }

    private static function normalize_datetime($value) {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        $value = is_string($value) ? trim($value) : '';
        if ($value === '') {
            return current_time('mysql');
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return current_time('mysql');
        }

        return gmdate('Y-m-d H:i:s', $timestamp);
    }

    private static function normalize_time($value) {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('H:i:s');
        }

        $value = is_string($value) ? trim(str_replace(',', ':', (string) $value)) : '';
        if ($value === '') {
            return '';
        }

        if (!preg_match('/^(\d{1,2}):(\d{1,2})(?::(\d{1,2}))?$/', $value, $matches)) {
            return '';
        }

        $hour = (int) $matches[1];
        $minute = (int) $matches[2];
        $second = isset($matches[3]) ? (int) $matches[3] : 0;

        if ($hour === 24 && $minute === 0 && $second === 0) {
            return '24:00:00';
        }

        if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59 || $second < 0 || $second > 59) {
            return '';
        }

        return sprintf('%02d:%02d:%02d', $hour, $minute, $second);
    }

    private static function normalize_project_id($value): int
    {
        if ($value === null) {
            return 0;
        }

        if (is_int($value) || is_float($value)) {
            $id = (int) $value;
            return $id > 0 ? $id : 0;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return 0;
            }

            if (ctype_digit($trimmed)) {
                $id = (int) $trimmed;
                return $id > 0 ? $id : 0;
            }

            $numeric = preg_replace('/[^0-9]/', '', $trimmed);
            if ($numeric !== '' && ctype_digit($numeric)) {
                $id = (int) $numeric;
                return $id > 0 ? $id : 0;
            }
        }

        return 0;
    }

    private static function normalize_project_filter($value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!is_scalar($value)) {
            return null;
        }

        $raw = trim((string) $value);
        if ($raw === '' || strcasecmp($raw, '__all__') === 0) {
            return null;
        }

        if (strcasecmp($raw, self::PROJECT_EMPTY_KEY) === 0) {
            return self::PROJECT_EMPTY_KEY;
        }

        $sanitized = sanitize_text_field($raw);
        if ($sanitized === '') {
            return null;
        }

        return $sanitized;
    }

    private static function normalize_project_label($value): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        $raw = trim((string) $value);
        if ($raw === '' || strcasecmp($raw, self::PROJECT_EMPTY_KEY) === 0) {
            return '';
        }

        return sanitize_text_field($raw);
    }

    private static function time_to_seconds(string $time): int
    {
        $parts = explode(':', $time);
        $hour = isset($parts[0]) ? (int) $parts[0] : 0;
        $minute = isset($parts[1]) ? (int) $parts[1] : 0;
        $second = isset($parts[2]) ? (int) $parts[2] : 0;

        return ($hour * HOUR_IN_SECONDS) + ($minute * MINUTE_IN_SECONDS) + $second;
    }

    private static function calculate_duration_minutes(string $startTime, string $endTime): int
    {
        $startSeconds = self::time_to_seconds($startTime);
        $endSeconds = self::time_to_seconds($endTime);

        $delta = $endSeconds - $startSeconds;
        if ($delta <= 0 && self::is_midnight_time($endTime) && !self::is_midnight_time($startTime)) {
            $delta += DAY_IN_SECONDS;
        }

        if ($delta <= 0) {
            return 0;
        }

        return (int) round($delta / 60);
    }

    private static function is_midnight_time(string $time): bool
    {
        return $time === '00:00:00' || $time === '24:00:00';
    }

    private static function format_time_value($value) {
        $value = is_string($value) ? trim($value) : '';
        if ($value === '') {
            return null;
        }

        $timestamp = strtotime('1970-01-01 ' . $value);
        if ($timestamp === false) {
            return null;
        }

        return gmdate('H:i', $timestamp);
    }

    /**
     * @param array<string,mixed>|null $context
     */
    private static function dispatchChangeEvent(string $event, int $recordId, ?array $context = null): void
    {
        $context = is_array($context) ? $context : array();

        $memberId = isset($context['member_id']) ? (int) $context['member_id'] : 0;
        $activityDate = isset($context['activity_date']) ? (string) $context['activity_date'] : '';
        $duration = isset($context['duration_minutes']) ? (int) $context['duration_minutes'] : 0;

        $payload = array_merge($context, array(
            'event' => $event,
            'record_id' => $recordId,
            'member_id' => $memberId,
            'activity_date' => $activityDate,
            'duration_minutes' => $duration,
        ));

        /**
         * Déclenché après chaque modification sur les heures encodées.
         *
         * @param array<string,mixed> $payload
         */
        do_action('mj_member_hours_after_change', $payload);
    }

    /**
     * @param array<string,mixed> $args
     * @return array<int,array<string,mixed>>
     */
    public static function get_weekly_totals(array $args = array()) {
        global $wpdb;
        $table = self::table_name();

        $defaults = array(
            'member_id' => 0,
            'member_ids' => array(),
            'weeks' => 6,
            'date_from' => '',
            'limit' => 0,
            'project' => null,
            'project_id' => 0,
        );

        $args = wp_parse_args($args, $defaults);

        $weeks = max(1, (int) $args['weeks']);
        $limit = max(0, (int) $args['limit']);

        $dateFrom = self::normalize_date($args['date_from']);
        if ($dateFrom === '') {
            $timezone = wp_timezone();
            try {
                $monday = new \DateTimeImmutable('monday this week', $timezone);
                $offset = $weeks - 1;
                if ($offset > 0) {
                    $monday = $monday->modify('-' . $offset . ' weeks');
                }
                $dateFrom = $monday->format('Y-m-d');
            } catch (\Exception $exception) {
                $dateFrom = gmdate('Y-m-d', strtotime('-' . $weeks . ' weeks'));
            }
        }

        $conditions = array('activity_date >= %s');
        $params = array($dateFrom);

        $memberIds = array();
        if (!empty($args['member_id'])) {
            $memberIds[] = (int) $args['member_id'];
        }
        if (!empty($args['member_ids'])) {
            $memberIds = array_merge($memberIds, (array) $args['member_ids']);
        }

        if (!empty($memberIds)) {
            $memberIds = array_map('intval', array_unique($memberIds));
            if (!empty($memberIds)) {
                $placeholders = implode(',', array_fill(0, count($memberIds), '%d'));
                $conditions[] = "member_id IN ({$placeholders})";
                $params = array_merge($params, $memberIds);
            }
        }

        $projectId = self::normalize_project_id($args['project_id'] ?? 0);
        if ($projectId > 0) {
            $conditions[] = 'project_id = %d';
            $params[] = $projectId;
        } else {
            $projectFilter = self::normalize_project_filter($args['project'] ?? null);
            if ($projectFilter !== null) {
                if ($projectFilter === self::PROJECT_EMPTY_KEY) {
                    $conditions[] = '(notes IS NULL OR notes = "")';
                } else {
                    $conditions[] = 'notes = %s';
                    $params[] = $projectFilter;
                }
            }
        }

        $whereSql = '';
        if (!empty($conditions)) {
            $whereSql = 'WHERE ' . implode(' AND ', $conditions);
        }

        $sql = "SELECT member_id,
                       YEARWEEK(activity_date, 1) AS week_key,
                       MIN(activity_date) AS week_start,
                       MAX(activity_date) AS week_end,
                       SUM(duration_minutes) AS total_minutes
                FROM {$table}
                {$whereSql}
                GROUP BY member_id, week_key
                HAVING SUM(duration_minutes) > 0
                ORDER BY week_key DESC, total_minutes DESC";

        if ($limit > 0) {
            $sql .= ' LIMIT %d';
            $params[] = $limit;
        }

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        $rows = $wpdb->get_results($sql);
        if (!is_array($rows) || empty($rows)) {
            return array();
        }

        return array_map(static function ($row) {
            return array(
                'member_id' => isset($row->member_id) ? (int) $row->member_id : 0,
                'week_key' => isset($row->week_key) ? (int) $row->week_key : 0,
                'week_start' => isset($row->week_start) ? (string) $row->week_start : '',
                'week_end' => isset($row->week_end) ? (string) $row->week_end : '',
                'total_minutes' => isset($row->total_minutes) ? (int) $row->total_minutes : 0,
            );
        }, $rows);
    }

    /**
     * @param array<string,mixed> $args
     * @return array<int,array<string,int>>
     */
    public static function get_member_totals(array $args = array()): array
    {
        global $wpdb;
        $table = self::table_name();

        $defaults = array(
            'member_ids' => array(),
            'project' => null,
            'project_id' => 0,
            'limit' => 0,
        );

        $args = wp_parse_args($args, $defaults);

        $conditions = array('member_id > 0');
        $params = array();

        $memberIds = array();
        if (!empty($args['member_ids'])) {
            $memberIds = array_map('intval', (array) $args['member_ids']);
            $memberIds = array_values(array_unique(array_filter($memberIds, static function ($value) {
                return $value > 0;
            })));
        }

        if (!empty($memberIds)) {
            $placeholders = implode(',', array_fill(0, count($memberIds), '%d'));
            $conditions[] = "member_id IN ({$placeholders})";
            $params = array_merge($params, $memberIds);
        }

        $projectId = self::normalize_project_id($args['project_id'] ?? 0);
        if ($projectId > 0) {
            $conditions[] = 'project_id = %d';
            $params[] = $projectId;
        } else {
            $projectFilter = self::normalize_project_filter($args['project'] ?? null);
            if ($projectFilter !== null) {
                if ($projectFilter === self::PROJECT_EMPTY_KEY) {
                    $conditions[] = '(notes IS NULL OR notes = "")';
                } else {
                    $conditions[] = 'notes = %s';
                    $params[] = $projectFilter;
                }
            }
        }

        $whereSql = implode(' AND ', $conditions);

        $sql = "SELECT member_id,
                       SUM(duration_minutes) AS total_minutes,
                       COUNT(*) AS entries
                FROM {$table}
                WHERE {$whereSql}
                GROUP BY member_id
                HAVING SUM(duration_minutes) > 0
                ORDER BY total_minutes DESC";

        $limit = isset($args['limit']) ? (int) $args['limit'] : 0;
        if ($limit > 0) {
            $sql .= ' LIMIT %d';
            $params[] = $limit;
        }

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        $rows = $wpdb->get_results($sql);
        if (!is_array($rows) || empty($rows)) {
            return array();
        }

        return array_map(static function ($row) {
            return array(
                'member_id' => isset($row->member_id) ? (int) $row->member_id : 0,
                'total_minutes' => isset($row->total_minutes) ? (int) $row->total_minutes : 0,
                'entries' => isset($row->entries) ? (int) $row->entries : 0,
            );
        }, $rows);
    }

    /**
     * @param array<string,mixed> $args
     * @return array<int,array<string,mixed>>
     */
    public static function get_project_totals(array $args = array()): array
    {
        global $wpdb;
        $table = self::table_name();

        $defaults = array(
            'member_id' => 0,
            'member_ids' => array(),
            'limit' => 0,
        );

        $args = wp_parse_args($args, $defaults);

        $hoursAlias = 'h';
        $projectsTable = self::projects_table();

        $conditions = array('1=1');
        $params = array();

        if (!empty($args['member_id'])) {
            $memberId = (int) $args['member_id'];
            if ($memberId > 0) {
                $conditions[] = "{$hoursAlias}.member_id = %d";
                $params[] = $memberId;
            }
        }

        if (!empty($args['member_ids'])) {
            $memberIds = array_map('intval', (array) $args['member_ids']);
            $memberIds = array_values(array_unique(array_filter($memberIds, static function ($value) {
                return $value > 0;
            })));

            if (!empty($memberIds)) {
                $placeholders = implode(',', array_fill(0, count($memberIds), '%d'));
                $conditions[] = "{$hoursAlias}.member_id IN ({$placeholders})";
                $params = array_merge($params, $memberIds);
            }
        }

        $whereSql = implode(' AND ', $conditions);

        $projectLabelExpr = "CASE WHEN {$hoursAlias}.project_id > 0 THEN COALESCE(NULLIF(TRIM(p.title), ''), '') ELSE COALESCE(NULLIF(TRIM({$hoursAlias}.notes), ''), '') END";
        $projectColorExpr = "CASE WHEN {$hoursAlias}.project_id > 0 THEN COALESCE(p.color, '') ELSE '' END";

        $sql = "SELECT COALESCE({$hoursAlias}.project_id, 0) AS project_id,
                       {$projectLabelExpr} AS project_label,
                       {$projectColorExpr} AS project_color,
                       SUM({$hoursAlias}.duration_minutes) AS total_minutes,
                       COUNT(*) AS entries
                FROM {$table} AS {$hoursAlias}
                LEFT JOIN {$projectsTable} AS p ON p.id = {$hoursAlias}.project_id
                WHERE {$whereSql}
                GROUP BY COALESCE({$hoursAlias}.project_id, 0), {$projectLabelExpr}, {$projectColorExpr}
                HAVING SUM({$hoursAlias}.duration_minutes) > 0
                ORDER BY total_minutes DESC, project_label ASC";

        $limit = isset($args['limit']) ? (int) $args['limit'] : 0;
        if ($limit > 0) {
            $sql .= ' LIMIT %d';
            $params[] = $limit;
        }

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        $rows = $wpdb->get_results($sql);
        if (!is_array($rows) || empty($rows)) {
            return array();
        }

        return array_map(static function ($row) {
            $label = isset($row->project_label) ? sanitize_text_field((string) $row->project_label) : '';
            $color = isset($row->project_color) ? sanitize_hex_color((string) $row->project_color) : '';

            if (!is_string($color)) {
                $color = '';
            }

            return array(
                'project_id' => isset($row->project_id) ? (int) $row->project_id : 0,
                'project_label' => $label,
                'project_color' => $color,
                'total_minutes' => isset($row->total_minutes) ? (int) $row->total_minutes : 0,
                'entries' => isset($row->entries) ? (int) $row->entries : 0,
            );
        }, $rows);
    }

    /**
     * @param string $oldLabel
     * @param string $newLabel
     * @param array<string,mixed> $scope
     * @return int|WP_Error
     */
    public static function bulkRenameTask(string $oldLabel, string $newLabel, array $scope = array())
    {
        global $wpdb;

        $from = sanitize_text_field($oldLabel);
        $to = sanitize_text_field($newLabel);

        if ($from === '' || $to === '') {
            return new WP_Error('mj_member_hours_invalid_task_label', __('Le libellé de tâche est invalide.', 'mj-member'));
        }

        $table = self::table_name();
        $newKey = sanitize_title($to);
        $oldKey = sanitize_title($from);
        $now = current_time('mysql');
        $collation = self::resolve_unicode_collation();

        $setParts = array('task_label = %s', 'task_key = %s', 'updated_at = %s');
        $whereParts = array();
        $params = array($to, $newKey, $now);

        $labelConditions = array('task_label = %s');
        $params[] = $from;
        if ($oldKey !== '') {
            $labelConditions[] = 'task_key = %s';
            $params[] = $oldKey;
        }
        if ($collation !== '') {
            $labelConditions[] = 'task_label COLLATE ' . $collation . ' = %s';
            $params[] = $from;
        }
        $whereParts[] = '(' . implode(' OR ', $labelConditions) . ')';

        if (!empty($scope['member_id'])) {
            $memberId = (int) $scope['member_id'];
            if ($memberId > 0) {
                $whereParts[] = 'member_id = %d';
                $params[] = $memberId;
            }
        }

        $sql = 'UPDATE ' . $table . ' SET ' . implode(', ', $setParts) . ' WHERE ' . implode(' AND ', $whereParts);
        $prepared = $wpdb->prepare($sql, $params);
        $result = $wpdb->query($prepared);

        if ($result === false) {
            return new WP_Error('mj_member_hours_rename_task_failed', __('Impossible de renommer la tâche.', 'mj-member'));
        }

        if ((int) $result === 0) {
            $binaryWhere = array('BINARY task_label = %s');
            $binaryParams = array($to, $newKey, $now, $from);
            if (!empty($scope['member_id'])) {
                $memberId = (int) $scope['member_id'];
                if ($memberId > 0) {
                    $binaryWhere[] = 'member_id = %d';
                    $binaryParams[] = $memberId;
                }
            }
            $binarySql = 'UPDATE ' . $table . ' SET ' . implode(', ', $setParts) . ' WHERE ' . implode(' AND ', $binaryWhere);
            $binaryPrepared = $wpdb->prepare($binarySql, $binaryParams);
            $result = $wpdb->query($binaryPrepared);
            if ($result === false) {
                return new WP_Error('mj_member_hours_rename_task_failed', __('Impossible de renommer la tâche.', 'mj-member'));
            }
        }

        return (int) $result;
    }

    private static function resolve_unicode_collation(): string
    {
        global $wpdb;

        if (!empty($wpdb->collate)) {
            return (string) $wpdb->collate;
        }

        if (!empty($wpdb->charset)) {
            $charset = strtolower((string) $wpdb->charset);
            if ($charset === 'utf8mb4') {
                return 'utf8mb4_unicode_ci';
            }
            if ($charset === 'utf8') {
                return 'utf8_unicode_ci';
            }
        }

        return 'utf8mb4_unicode_ci';
    }

    /**
     * @param string $oldLabel
     * @param string $newLabel
     * @param array<string,mixed> $scope
     * @return int|WP_Error
     */
    public static function bulkRenameProject(string $oldLabel, string $newLabel, array $scope = array())
    {
        global $wpdb;

        $from = self::normalize_project_label($oldLabel);
        $to = self::normalize_project_label($newLabel);

        if ($from === '' || $to === '') {
            return new WP_Error('mj_member_hours_invalid_project_label', __('Le libellé de projet est invalide.', 'mj-member'));
        }

        $table = self::table_name();
        $fields = array(
            'notes' => $to,
            'updated_at' => current_time('mysql'),
        );
        $fieldFormats = array('%s', '%s');

        $where = array('notes' => $from);
        $whereFormats = array('%s');

        if (!empty($scope['member_id'])) {
            $memberId = (int) $scope['member_id'];
            if ($memberId > 0) {
                $where['member_id'] = $memberId;
                $whereFormats[] = '%d';
            }
        }

        $updated = $wpdb->update($table, $fields, $where, $fieldFormats, $whereFormats);
        if ($updated === false) {
            return new WP_Error('mj_member_hours_rename_project_failed', __('Impossible de renommer le projet.', 'mj-member'));
        }

        return (int) $updated;
    }

    /**
     * Déplace toutes les entrées d'une tâche vers un autre projet.
     *
     * @param string $taskLabel
     * @param string $sourceProject
     * @param string $targetProject
     * @param array<string,mixed> $scope
     * @return int|WP_Error
     */
    public static function bulkMoveTaskToProject(string $taskLabel, string $sourceProject, string $targetProject, array $scope = array())
    {
        global $wpdb;

        $task = sanitize_text_field($taskLabel);
        $from = self::normalize_project_label($sourceProject);
        $to = self::normalize_project_label($targetProject);

        if ($task === '') {
            return new WP_Error('mj_member_hours_invalid_task_label', __('Le libellé de tâche est invalide.', 'mj-member'));
        }

        if ($to === '') {
            return new WP_Error('mj_member_hours_invalid_project_label', __('Le projet cible est invalide.', 'mj-member'));
        }

        $table = self::table_name();
        $taskKey = sanitize_title($task);
        $collation = self::resolve_unicode_collation();
        $now = current_time('mysql');

        $setParts = array('notes = %s', 'updated_at = %s');
        $params = array($to, $now);

        $taskConditions = array('task_label = %s');
        $params[] = $task;
        if ($taskKey !== '') {
            $taskConditions[] = 'task_key = %s';
            $params[] = $taskKey;
        }
        if ($collation !== '') {
            $taskConditions[] = 'task_label COLLATE ' . $collation . ' = %s';
            $params[] = $task;
        }

        $whereParts = array('(' . implode(' OR ', $taskConditions) . ')');

        if ($from === '') {
            $whereParts[] = "(notes IS NULL OR notes = '')";
        } else {
            $whereParts[] = 'notes = %s';
            $params[] = $from;
        }

        if (!empty($scope['member_id'])) {
            $memberId = (int) $scope['member_id'];
            if ($memberId > 0) {
                $whereParts[] = 'member_id = %d';
                $params[] = $memberId;
            }
        }

        $sql = 'UPDATE ' . $table . ' SET ' . implode(', ', $setParts) . ' WHERE ' . implode(' AND ', $whereParts);
        $prepared = $wpdb->prepare($sql, $params);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[mj-member][hours] bulkMoveTaskToProject SQL: ' . $prepared);
        }

        $result = $wpdb->query($prepared);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[mj-member][hours] bulkMoveTaskToProject result: ' . var_export($result, true) . ' last_error: ' . $wpdb->last_error);
        }

        if ($result === false) {
            return new WP_Error('mj_member_hours_move_task_failed', __('Impossible de déplacer la tâche.', 'mj-member'));
        }

        return (int) $result;
    }

    /**
     * Retourne les totaux d'heures par membre.
     *
     * @param array<string,mixed> $args
     * @return array<int,array<string,mixed>>
     */
    public static function get_totals_by_member(array $args = array()): array
    {
        global $wpdb;

        $table = self::table_name();

        $conditions = array('1 = 1');
        $params = array();

        if (!empty($args['member_ids']) && is_array($args['member_ids'])) {
            $memberIds = array_values(array_unique(array_filter(array_map('intval', $args['member_ids']), static function ($value) {
                return $value > 0;
            })));

            if (!empty($memberIds)) {
                $placeholders = implode(',', array_fill(0, count($memberIds), '%d'));
                $conditions[] = "member_id IN ({$placeholders})";
                $params = array_merge($params, $memberIds);
            }
        }

        $whereSql = implode(' AND ', $conditions);

        $sql = "SELECT member_id,
                       SUM(duration_minutes) AS total_minutes,
                       COUNT(*) AS entries
                FROM {$table}
                WHERE {$whereSql}
                GROUP BY member_id
                HAVING SUM(duration_minutes) > 0
                ORDER BY total_minutes DESC";

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        $rows = $wpdb->get_results($sql);
        if (!is_array($rows) || empty($rows)) {
            return array();
        }

        return array_map(static function ($row): array {
            return array(
                'member_id' => isset($row->member_id) ? (int) $row->member_id : 0,
                'total_minutes' => isset($row->total_minutes) ? (int) $row->total_minutes : 0,
                'entries' => isset($row->entries) ? (int) $row->entries : 0,
            );
        }, $rows);
    }

    /**
     * Retourne les totaux d'heures par membre et par projet.
     *
     * @param array<string,mixed> $args
     * @return array<int,array<string,mixed>>
     */
    public static function get_member_project_totals(array $args = array()): array
    {
        global $wpdb;

        $table = self::table_name();
        $hoursAlias = 'h';
        $projectsTable = self::projects_table();

        $conditions = array('1 = 1');
        $params = array();

        if (!empty($args['member_ids']) && is_array($args['member_ids'])) {
            $memberIds = array_values(array_unique(array_filter(array_map('intval', $args['member_ids']), static function ($value) {
                return $value > 0;
            })));

            if (!empty($memberIds)) {
                $placeholders = implode(',', array_fill(0, count($memberIds), '%d'));
                $conditions[] = "{$hoursAlias}.member_id IN ({$placeholders})";
                $params = array_merge($params, $memberIds);
            }
        }

        $whereSql = implode(' AND ', $conditions);

        $projectLabelExpr = "CASE WHEN {$hoursAlias}.project_id > 0 THEN COALESCE(NULLIF(TRIM(p.title), ''), '') ELSE COALESCE(NULLIF(TRIM({$hoursAlias}.notes), ''), '') END";
        $projectColorExpr = "CASE WHEN {$hoursAlias}.project_id > 0 THEN COALESCE(p.color, '') ELSE '' END";

        $sql = "SELECT {$hoursAlias}.member_id,
                       COALESCE({$hoursAlias}.project_id, 0) AS project_id,
                       {$projectLabelExpr} AS project_label,
                       {$projectColorExpr} AS project_color,
                       SUM({$hoursAlias}.duration_minutes) AS total_minutes,
                       COUNT(*) AS entries
                FROM {$table} AS {$hoursAlias}
                LEFT JOIN {$projectsTable} AS p ON p.id = {$hoursAlias}.project_id
                WHERE {$whereSql}
                GROUP BY {$hoursAlias}.member_id, COALESCE({$hoursAlias}.project_id, 0), {$projectLabelExpr}, {$projectColorExpr}
                HAVING SUM({$hoursAlias}.duration_minutes) > 0
                ORDER BY {$hoursAlias}.member_id ASC, total_minutes DESC";

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        $rows = $wpdb->get_results($sql);
        if (!is_array($rows) || empty($rows)) {
            return array();
        }

        return array_map(static function ($row): array {
            $label = isset($row->project_label) ? sanitize_text_field((string) $row->project_label) : '';
            $color = isset($row->project_color) ? sanitize_hex_color((string) $row->project_color) : '';

            if (!is_string($color)) {
                $color = '';
            }

            return array(
                'member_id' => isset($row->member_id) ? (int) $row->member_id : 0,
                'project_id' => isset($row->project_id) ? (int) $row->project_id : 0,
                'project_label' => $label,
                'project_color' => $color,
                'total_minutes' => isset($row->total_minutes) ? (int) $row->total_minutes : 0,
                'entries' => isset($row->entries) ? (int) $row->entries : 0,
            );
        }, $rows);
    }

    /**
     * Retourne les totaux d'heures par mois.
     *
     * @param array<string,mixed> $args
     * @return array<int,array<string,mixed>>
     */
    public static function get_monthly_totals(array $args = array()): array
    {
        global $wpdb;

        $table = self::table_name();

        $defaults = array(
            'member_id' => 0,
            'member_ids' => array(),
            'date_from' => '',
            'date_to' => '',
            'limit' => 12,
            'group_by_member' => false,
        );

        $args = wp_parse_args($args, $defaults);

        $conditions = array('1 = 1');
        $params = array();
        $groupByMember = !empty($args['group_by_member']);

        if (!empty($args['member_id'])) {
            $memberId = (int) $args['member_id'];
            if ($memberId > 0) {
                $conditions[] = 'member_id = %d';
                $params[] = $memberId;
            }
        }

        if (!empty($args['member_ids'])) {
            $memberIds = array_map('intval', (array) $args['member_ids']);
            $memberIds = array_values(array_unique(array_filter($memberIds, static function ($value) {
                return $value > 0;
            })));

            if (!empty($memberIds)) {
                $placeholders = implode(',', array_fill(0, count($memberIds), '%d'));
                $conditions[] = "member_id IN ({$placeholders})";
                $params = array_merge($params, $memberIds);
            }
        }

        $dateFrom = self::normalize_date($args['date_from'] ?? '');
        if ($dateFrom !== '') {
            $conditions[] = 'activity_date >= %s';
            $params[] = $dateFrom;
        }

        $dateTo = self::normalize_date($args['date_to'] ?? '');
        if ($dateTo !== '') {
            $conditions[] = 'activity_date <= %s';
            $params[] = $dateTo;
        }

        $whereSql = implode(' AND ', $conditions);

        $selectParts = array();
        if ($groupByMember) {
            $selectParts[] = 'member_id';
        }
        $selectParts[] = "DATE_FORMAT(activity_date, '%Y-%m-01') AS period_start";
        $selectParts[] = 'SUM(duration_minutes) AS total_minutes';
        $selectParts[] = 'COUNT(*) AS entries';

        $groupByParts = array('period_start');
        if ($groupByMember) {
            array_unshift($groupByParts, 'member_id');
        }

        $orderByParts = array('period_start DESC');
        if ($groupByMember) {
            array_unshift($orderByParts, 'member_id ASC');
        }

        $sql = 'SELECT ' . implode(', ', $selectParts) . "
                FROM {$table}
                WHERE {$whereSql}
                GROUP BY " . implode(', ', $groupByParts) . "
                HAVING SUM(duration_minutes) > 0
                ORDER BY " . implode(', ', $orderByParts);

        $limit = isset($args['limit']) ? (int) $args['limit'] : 0;
        if (!$groupByMember && $limit > 0) {
            $sql .= ' LIMIT %d';
            $params[] = $limit;
        }

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        $rows = $wpdb->get_results($sql);
        if (!is_array($rows) || empty($rows)) {
            return array();
        }

        return array_map(static function ($row) use ($groupByMember): array {
            $formatted = array(
                'period_start' => isset($row->period_start) ? (string) $row->period_start : '',
                'total_minutes' => isset($row->total_minutes) ? (int) $row->total_minutes : 0,
                'entries' => isset($row->entries) ? (int) $row->entries : 0,
            );

            if ($groupByMember) {
                $formatted['member_id'] = isset($row->member_id) ? (int) $row->member_id : 0;
            }

            return $formatted;
        }, $rows);
    }

    /**
     * Retourne les totaux d'heures par semaine ISO.
     *
     * @param array<string,mixed> $args
     * @return array<int,array<string,mixed>>
     */
    public static function get_weekly_totals_summary(array $args = array()): array
    {
        global $wpdb;

        $table = self::table_name();

        $defaults = array(
            'member_id' => 0,
            'member_ids' => array(),
            'date_from' => '',
            'date_to' => '',
            'limit' => 12,
            'group_by_member' => false,
        );

        $args = wp_parse_args($args, $defaults);

        $conditions = array('1 = 1');
        $params = array();

        if (!empty($args['member_id'])) {
            $memberId = (int) $args['member_id'];
            if ($memberId > 0) {
                $conditions[] = 'member_id = %d';
                $params[] = $memberId;
            }
        }

        if (!empty($args['member_ids'])) {
            $memberIds = array_map('intval', (array) $args['member_ids']);
            $memberIds = array_values(array_unique(array_filter($memberIds, static function ($value) {
                return $value > 0;
            })));

            if (!empty($memberIds)) {
                $placeholders = implode(',', array_fill(0, count($memberIds), '%d'));
                $conditions[] = "member_id IN ({$placeholders})";
                $params = array_merge($params, $memberIds);
            }
        }

        $dateFrom = self::normalize_date($args['date_from'] ?? '');
        if ($dateFrom !== '') {
            $conditions[] = 'activity_date >= %s';
            $params[] = $dateFrom;
        }

        $dateTo = self::normalize_date($args['date_to'] ?? '');
        if ($dateTo !== '') {
            $conditions[] = 'activity_date <= %s';
            $params[] = $dateTo;
        }

        $whereSql = implode(' AND ', $conditions);

        $groupByMember = !empty($args['group_by_member']);

        $selectParts = array();
        if ($groupByMember) {
            $selectParts[] = 'member_id';
        }

        $selectParts[] = "DATE_FORMAT(activity_date, '%x') AS iso_year";
        $selectParts[] = "DATE_FORMAT(activity_date, '%v') AS iso_week";
        $selectParts[] = 'SUM(duration_minutes) AS total_minutes';
        $selectParts[] = 'COUNT(*) AS entries';

        $groupByParts = array('iso_year', 'iso_week');
        if ($groupByMember) {
            array_unshift($groupByParts, 'member_id');
        }

        $orderByParts = array('iso_year DESC', 'iso_week DESC');
        if ($groupByMember) {
            array_unshift($orderByParts, 'member_id ASC');
        }

        $sql = 'SELECT ' . implode(', ', $selectParts) . "
                FROM {$table}
                WHERE {$whereSql}
                GROUP BY " . implode(', ', $groupByParts) . "
                HAVING SUM(duration_minutes) > 0
                ORDER BY " . implode(', ', $orderByParts);

        $limit = isset($args['limit']) ? (int) $args['limit'] : 0;
        if (!$groupByMember && $limit > 0) {
            $sql .= ' LIMIT %d';
            $params[] = $limit;
        }

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        $rows = $wpdb->get_results($sql);
        if (!is_array($rows) || empty($rows)) {
            return array();
        }

        return array_map(static function ($row) use ($groupByMember): array {
            $formatted = array(
                'iso_year' => isset($row->iso_year) ? (int) $row->iso_year : 0,
                'iso_week' => isset($row->iso_week) ? (int) $row->iso_week : 0,
                'total_minutes' => isset($row->total_minutes) ? (int) $row->total_minutes : 0,
                'entries' => isset($row->entries) ? (int) $row->entries : 0,
            );

            if ($groupByMember) {
                $formatted['member_id'] = isset($row->member_id) ? (int) $row->member_id : 0;
            }

            return $formatted;
        }, $rows);
    }
}
