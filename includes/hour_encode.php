<?php

use Mj\Member\Classes\Crud\MjEventAnimateurs;
use Mj\Member\Classes\Crud\MjEventClosures;
use Mj\Member\Classes\Crud\MjEventVolunteers;
use Mj\Member\Classes\Crud\MjEvents;
use Mj\Member\Classes\Crud\MjMemberHours;
use Mj\Member\Classes\Crud\MjMembers;
use Mj\Member\Classes\MjEventSchedule;
use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_ajax_mj_member_hour_encode_week', 'mj_member_ajax_hour_encode_week');
add_action('wp_ajax_mj_member_hour_encode_create', 'mj_member_ajax_hour_encode_create');
add_action('wp_ajax_mj_member_hour_encode_update', 'mj_member_ajax_hour_encode_update');
add_action('wp_ajax_mj_member_hour_encode_delete', 'mj_member_ajax_hour_encode_delete');
add_action('wp_ajax_mj_member_hour_encode_rename_project', 'mj_member_ajax_hour_encode_rename_project');
add_action('wp_ajax_mj_member_hour_encode_rename_task', 'mj_member_ajax_hour_encode_rename_task');

/**
 * Retourne les événements planifiés pour la semaine demandée.
 */
function mj_member_ajax_hour_encode_week() {
    check_ajax_referer('mj-member-hour-encode', 'nonce');

    $capability = Config::hoursCapability();
    if ($capability === '') {
        $capability = Config::capability();
    }

    if (!current_user_can($capability)) {
        wp_send_json_error(array('message' => __('Accès refusé.', 'mj-member')), 403);
    }

    $userId = get_current_user_id();
    if ($userId <= 0) {
        wp_send_json_error(array('message' => __('Utilisateur non authentifié.', 'mj-member')), 401);
    }

    $memberRow = MjMembers::getByWpUserId($userId);
    if (!$memberRow || empty($memberRow->id)) {
        wp_send_json_error(array('message' => __('Impossible de déterminer le membre associé.', 'mj-member')), 400);
    }

    $memberId = (int) $memberRow->id;
    if ($memberId <= 0) {
        wp_send_json_error(array('message' => __('Profil membre invalide.', 'mj-member')), 400);
    }

    $weekParam = isset($_POST['week']) ? sanitize_text_field(wp_unslash($_POST['week'])) : '';
    $timezone = wp_timezone();
    $weekStart = mj_member_hour_encode_resolve_week_start($weekParam, $timezone);
    $weekEnd = $weekStart->add(new DateInterval('P6D'))->setTime(23, 59, 59);

    list($entries, $projects) = mj_member_hour_encode_collect_entries_for_week($memberId, $weekStart, $weekEnd, $timezone);
    $projectCatalog = mj_member_hour_encode_collect_project_catalog($memberId);
    $projectTotals = mj_member_hour_encode_collect_project_totals($memberId, $timezone);

    $closures = mj_member_hour_encode_collect_closures($weekStart, $weekEnd);
    $closureOccurrences = !empty($closures)
        ? mj_member_hour_encode_build_closure_occurrences($closures, $weekStart, $weekEnd, $timezone)
        : array();

    $eventIds = mj_member_hour_encode_collect_event_ids($memberId);
    $events = mj_member_hour_encode_fetch_events($eventIds);

    $since = $weekStart->format('Y-m-d H:i:s');
    $until = $weekEnd->format('Y-m-d H:i:s');
    $weekStartTimestamp = $weekStart->getTimestamp();
    $weekEndTimestamp = $weekEnd->getTimestamp();

    $occurrences = !empty($events)
        ? mj_member_hour_encode_build_occurrences($events, $since, $until, $weekStartTimestamp, $weekEndTimestamp)
        : array();

    if (!empty($closureOccurrences)) {
        $occurrences = array_merge($occurrences, $closureOccurrences);
    }
    if (!empty($occurrences)) {
        usort(
            $occurrences,
            static function ($a, $b) {
                return strcmp($a['start'], $b['start']);
            }
        );
    }

    /**
     * Permet de modifier les événements renvoyés au widget d'encodage des heures.
     *
     * @param array<int,array<string,mixed>> $occurrences
     * @param array<string,mixed>            $context
     */
    $occurrences = apply_filters(
        'mj_member_hour_encode_week_events',
        $occurrences,
        array(
            'member_id' => $memberId,
            'week_start' => $weekStart,
            'week_end' => $weekEnd,
        )
    );

    wp_send_json_success(mj_member_hour_encode_build_week_response($weekStart, $weekEnd, $occurrences, $entries, $projects, $projectCatalog, $projectTotals));
}

function mj_member_ajax_hour_encode_create() {
    check_ajax_referer('mj-member-hour-encode', 'nonce');

    $capability = Config::hoursCapability();
    if ($capability === '') {
        $capability = Config::capability();
    }

    if (!current_user_can($capability)) {
        wp_send_json_error(array('message' => __('Accès refusé.', 'mj-member')), 403);
    }

    $userId = get_current_user_id();
    if ($userId <= 0) {
        wp_send_json_error(array('message' => __('Utilisateur non authentifié.', 'mj-member')), 401);
    }

    if (defined('WP_DEBUG') && WP_DEBUG) {
        $rawPayload = array(
            'task' => isset($_POST['task']) ? sanitize_text_field(wp_unslash($_POST['task'])) : '',
            'project' => isset($_POST['project']) ? sanitize_text_field(wp_unslash($_POST['project'])) : '',
            'day' => isset($_POST['day']) ? sanitize_text_field(wp_unslash($_POST['day'])) : '',
            'start' => isset($_POST['start']) ? sanitize_text_field(wp_unslash($_POST['start'])) : '',
            'end' => isset($_POST['end']) ? sanitize_text_field(wp_unslash($_POST['end'])) : '',
        );
        error_log('[mj-member][hour-encode] create request: ' . wp_json_encode($rawPayload));
    }

    $memberRow = MjMembers::getByWpUserId($userId);
    if (!$memberRow || empty($memberRow->id)) {
        wp_send_json_error(array('message' => __('Impossible de déterminer le membre associé.', 'mj-member')), 400);
    }

    $memberId = (int) $memberRow->id;
    if ($memberId <= 0) {
        wp_send_json_error(array('message' => __('Profil membre invalide.', 'mj-member')), 400);
    }

    if (!class_exists(MjMemberHours::class)) {
        wp_send_json_error(array('message' => __('Enregistrement des heures indisponible.', 'mj-member')), 500);
    }

    $taskLabel = isset($_POST['task']) ? sanitize_text_field(wp_unslash($_POST['task'])) : '';
    $projectLabel = isset($_POST['project']) ? sanitize_text_field(wp_unslash($_POST['project'])) : '';
    $dayIso = isset($_POST['day']) ? sanitize_text_field(wp_unslash($_POST['day'])) : '';
    $startValue = isset($_POST['start']) ? sanitize_text_field(wp_unslash($_POST['start'])) : '';
    $endValue = isset($_POST['end']) ? sanitize_text_field(wp_unslash($_POST['end'])) : '';

    if ($taskLabel === '') {
        wp_send_json_error(array('message' => __('Saisissez un intitulé de tâche.', 'mj-member')));
    }

    $dayIso = mj_member_hour_encode_validate_day_iso($dayIso);
    if ($dayIso === '') {
        wp_send_json_error(array('message' => __('Date invalide.', 'mj-member')));
    }

    $startTime = mj_member_hour_encode_normalize_time_value($startValue);
    $endTime = mj_member_hour_encode_normalize_time_value($endValue);

    if ($startTime === '' || $endTime === '') {
        wp_send_json_error(array('message' => __('Renseignez une heure de début et une heure de fin.', 'mj-member')));
    }

    $timezone = wp_timezone();
    $startDateTime = mj_member_hour_encode_combine_datetime($dayIso, $startTime, $timezone);
    $endDateTime = mj_member_hour_encode_combine_datetime($dayIso, $endTime, $timezone);

    if (!$startDateTime || !$endDateTime || $endDateTime <= $startDateTime) {
        wp_send_json_error(array('message' => __('L’heure de fin doit être postérieure à l’heure de début.', 'mj-member')));
    }

    $durationMinutes = (int) round(($endDateTime->getTimestamp() - $startDateTime->getTimestamp()) / 60);
    if ($durationMinutes <= 0) {
        wp_send_json_error(array('message' => __('Durée invalide.', 'mj-member')));
    }

    $taskKey = sanitize_title($taskLabel);

    $payload = array(
        'member_id' => $memberId,
        'task_label' => $taskLabel,
        'task_key' => $taskKey !== '' ? $taskKey : null,
        'activity_date' => $dayIso,
        'start_time' => $startTime,
        'end_time' => $endTime,
        'duration_minutes' => $durationMinutes,
        'notes' => $projectLabel !== '' ? $projectLabel : null,
        'recorded_by' => $userId,
    );

    $result = MjMemberHours::create($payload);
    if (is_wp_error($result)) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[mj-member][hour-encode] create error: ' . $result->get_error_code() . ' – ' . $result->get_error_message());
        }
        wp_send_json_error(array('message' => $result->get_error_message()));
    }

    $record = MjMemberHours::get($result);

    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[mj-member][hour-encode] create success: entry #' . (int) $result);
    }

    $entry = null;
    if (is_array($record)) {
        $entry = mj_member_hour_encode_format_hour_entry($record, $timezone);
    }

    $weekStart = mj_member_hour_encode_resolve_week_start($dayIso, $timezone);
    $weekEnd = $weekStart->add(new DateInterval('P6D'))->setTime(23, 59, 59);

    list($entries, $projects) = mj_member_hour_encode_collect_entries_for_week($memberId, $weekStart, $weekEnd, $timezone);
    $projectCatalog = mj_member_hour_encode_collect_project_catalog($memberId);
    $projectTotals = mj_member_hour_encode_collect_project_totals($memberId, $timezone);

    $response = array(
        'week' => array(
            'start' => $weekStart->format('Y-m-d'),
            'end' => $weekEnd->format('Y-m-d'),
        ),
        'entries' => $entries,
        'projects' => $projects,
        'projectCatalog' => $projectCatalog,
        'projectTotals' => $projectTotals,
    );

    if ($entry) {
        $response['entry'] = $entry;
    }

    wp_send_json_success($response);
}

function mj_member_ajax_hour_encode_rename_project() {
    check_ajax_referer('mj-member-hour-encode', 'nonce');

    $capability = Config::hoursCapability();
    if ($capability === '') {
        $capability = Config::capability();
    }

    if (!current_user_can($capability)) {
        wp_send_json_error(array('message' => __('Accès refusé.', 'mj-member')), 403);
    }

    $userId = get_current_user_id();
    if ($userId <= 0) {
        wp_send_json_error(array('message' => __('Utilisateur non authentifié.', 'mj-member')), 401);
    }

    if (!class_exists(MjMembers::class) || !class_exists(MjMemberHours::class)) {
        wp_send_json_error(array('message' => __('Fonctionnalité indisponible.', 'mj-member')), 500);
    }

    $memberRow = MjMembers::getByWpUserId($userId);
    if (!$memberRow || empty($memberRow->id)) {
        wp_send_json_error(array('message' => __('Impossible de déterminer le membre associé.', 'mj-member')), 400);
    }

    $memberId = (int) $memberRow->id;
    if ($memberId <= 0) {
        wp_send_json_error(array('message' => __('Profil membre invalide.', 'mj-member')), 400);
    }

    $projectKey = isset($_POST['project_key']) ? sanitize_text_field(wp_unslash((string) $_POST['project_key'])) : '';
    $oldLabel = isset($_POST['old_label']) ? sanitize_text_field(wp_unslash((string) $_POST['old_label'])) : '';
    $newLabel = isset($_POST['new_label']) ? sanitize_text_field(wp_unslash((string) $_POST['new_label'])) : '';

    if ($newLabel === '') {
        wp_send_json_error(array('message' => __('Le libellé fourni est invalide.', 'mj-member')));
    }

    if (
        strcasecmp($projectKey, MjMemberHours::PROJECT_EMPTY_KEY) === 0
        || strcasecmp($projectKey, '__mj_hour_encode_no_project__') === 0
    ) {
        $oldLabel = '';
    }

    if (strcasecmp($oldLabel, $newLabel) === 0) {
        wp_send_json_success(array('updated' => 0));
    }

    $result = MjMemberHours::bulkRenameProject($oldLabel, $newLabel, array('member_id' => $memberId));
    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()));
    }

    wp_send_json_success(array('updated' => (int) $result));
}

function mj_member_ajax_hour_encode_rename_task() {
    check_ajax_referer('mj-member-hour-encode', 'nonce');

    $capability = Config::hoursCapability();
    if ($capability === '') {
        $capability = Config::capability();
    }

    if (!current_user_can($capability)) {
        wp_send_json_error(array('message' => __('Accès refusé.', 'mj-member')), 403);
    }

    $userId = get_current_user_id();
    if ($userId <= 0) {
        wp_send_json_error(array('message' => __('Utilisateur non authentifié.', 'mj-member')), 401);
    }

    if (!class_exists(MjMembers::class) || !class_exists(MjMemberHours::class)) {
        wp_send_json_error(array('message' => __('Fonctionnalité indisponible.', 'mj-member')), 500);
    }

    $memberRow = MjMembers::getByWpUserId($userId);
    if (!$memberRow || empty($memberRow->id)) {
        wp_send_json_error(array('message' => __('Impossible de déterminer le membre associé.', 'mj-member')), 400);
    }

    $memberId = (int) $memberRow->id;
    if ($memberId <= 0) {
        wp_send_json_error(array('message' => __('Profil membre invalide.', 'mj-member')), 400);
    }

    $oldLabel = isset($_POST['old_label']) ? sanitize_text_field(wp_unslash((string) $_POST['old_label'])) : '';
    $newLabel = isset($_POST['new_label']) ? sanitize_text_field(wp_unslash((string) $_POST['new_label'])) : '';

    if ($oldLabel === '' || $newLabel === '') {
        wp_send_json_error(array('message' => __('Le libellé de tâche est invalide.', 'mj-member')));
    }

    if (strcasecmp($oldLabel, $newLabel) === 0) {
        wp_send_json_success(array('updated' => 0));
    }

    $result = MjMemberHours::bulkRenameTask($oldLabel, $newLabel, array('member_id' => $memberId));
    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()));
    }

    wp_send_json_success(array('updated' => (int) $result));
}

function mj_member_ajax_hour_encode_update() {
    check_ajax_referer('mj-member-hour-encode', 'nonce');

    $capability = Config::hoursCapability();
    if ($capability === '') {
        $capability = Config::capability();
    }

    if (!current_user_can($capability)) {
        wp_send_json_error(array('message' => __('Accès refusé.', 'mj-member')), 403);
    }

    $userId = get_current_user_id();
    if ($userId <= 0) {
        wp_send_json_error(array('message' => __('Utilisateur non authentifié.', 'mj-member')), 401);
    }

    $memberRow = MjMembers::getByWpUserId($userId);
    if (!$memberRow || empty($memberRow->id)) {
        wp_send_json_error(array('message' => __('Impossible de déterminer le membre associé.', 'mj-member')), 400);
    }

    $memberId = (int) $memberRow->id;
    if ($memberId <= 0) {
        wp_send_json_error(array('message' => __('Profil membre invalide.', 'mj-member')), 400);
    }

    if (!class_exists(MjMemberHours::class)) {
        wp_send_json_error(array('message' => __('Enregistrement des heures indisponible.', 'mj-member')), 500);
    }

    $entryId = isset($_POST['entry_id']) ? (int) $_POST['entry_id'] : 0;
    if ($entryId <= 0) {
        wp_send_json_error(array('message' => __('Identifiant de plage invalide.', 'mj-member')));
    }

    $existing = mj_member_hour_encode_get_entry_for_member($memberId, $entryId);
    if (!$existing) {
        wp_send_json_error(array('message' => __('Plage introuvable.', 'mj-member')), 404);
    }

    $taskLabel = isset($_POST['task']) ? sanitize_text_field(wp_unslash($_POST['task'])) : '';
    $projectLabel = isset($_POST['project']) ? sanitize_text_field(wp_unslash($_POST['project'])) : '';
    $dayIso = isset($_POST['day']) ? sanitize_text_field(wp_unslash($_POST['day'])) : (isset($existing['activity_date']) ? (string) $existing['activity_date'] : '');
    $startValue = isset($_POST['start']) ? sanitize_text_field(wp_unslash($_POST['start'])) : '';
    $endValue = isset($_POST['end']) ? sanitize_text_field(wp_unslash($_POST['end'])) : '';

    if ($taskLabel === '') {
        wp_send_json_error(array('message' => __('Saisissez un intitulé de tâche.', 'mj-member')));
    }

    $dayIso = mj_member_hour_encode_validate_day_iso($dayIso);
    if ($dayIso === '') {
        wp_send_json_error(array('message' => __('Date invalide.', 'mj-member')));
    }

    $startTime = mj_member_hour_encode_normalize_time_value($startValue !== '' ? $startValue : (isset($existing['start_time']) ? (string) $existing['start_time'] : ''));
    $endTime = mj_member_hour_encode_normalize_time_value($endValue !== '' ? $endValue : (isset($existing['end_time']) ? (string) $existing['end_time'] : ''));

    if ($startTime === '' || $endTime === '') {
        wp_send_json_error(array('message' => __('Renseignez une heure de début et une heure de fin.', 'mj-member')));
    }

    $timezone = wp_timezone();
    $startDateTime = mj_member_hour_encode_combine_datetime($dayIso, $startTime, $timezone);
    $endDateTime = mj_member_hour_encode_combine_datetime($dayIso, $endTime, $timezone);

    if (!$startDateTime || !$endDateTime || $endDateTime <= $startDateTime) {
        wp_send_json_error(array('message' => __('L’heure de fin doit être postérieure à l’heure de début.', 'mj-member')));
    }

    $durationMinutes = (int) round(($endDateTime->getTimestamp() - $startDateTime->getTimestamp()) / 60);
    if ($durationMinutes <= 0) {
        wp_send_json_error(array('message' => __('Durée invalide.', 'mj-member')));
    }

    $taskKey = sanitize_title($taskLabel);

    $payload = array(
        'task_label' => $taskLabel,
        'task_key' => $taskKey !== '' ? $taskKey : null,
        'activity_date' => $dayIso,
        'start_time' => $startTime,
        'end_time' => $endTime,
        'duration_minutes' => $durationMinutes,
        'notes' => $projectLabel,
    );

    $result = MjMemberHours::update($entryId, $payload);
    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()));
    }

    $record = MjMemberHours::get($entryId);

    $entry = null;
    if (is_array($record)) {
        $entry = mj_member_hour_encode_format_hour_entry($record, $timezone);
    }

    $weekStart = mj_member_hour_encode_resolve_week_start($dayIso, $timezone);
    $weekEnd = $weekStart->add(new DateInterval('P6D'))->setTime(23, 59, 59);

    list($entries, $projects) = mj_member_hour_encode_collect_entries_for_week($memberId, $weekStart, $weekEnd, $timezone);
    $projectCatalog = mj_member_hour_encode_collect_project_catalog($memberId);
    $projectTotals = mj_member_hour_encode_collect_project_totals($memberId, $timezone);

    $response = array(
        'week' => array(
            'start' => $weekStart->format('Y-m-d'),
            'end' => $weekEnd->format('Y-m-d'),
        ),
        'entries' => $entries,
        'projects' => $projects,
        'projectCatalog' => $projectCatalog,
        'projectTotals' => $projectTotals,
        'entry' => $entry,
    );

    wp_send_json_success($response);
}

function mj_member_ajax_hour_encode_delete() {
    check_ajax_referer('mj-member-hour-encode', 'nonce');

    $capability = Config::hoursCapability();
    if ($capability === '') {
        $capability = Config::capability();
    }

    if (!current_user_can($capability)) {
        wp_send_json_error(array('message' => __('Accès refusé.', 'mj-member')), 403);
    }

    $userId = get_current_user_id();
    if ($userId <= 0) {
        wp_send_json_error(array('message' => __('Utilisateur non authentifié.', 'mj-member')), 401);
    }

    $memberRow = MjMembers::getByWpUserId($userId);
    if (!$memberRow || empty($memberRow->id)) {
        wp_send_json_error(array('message' => __('Impossible de déterminer le membre associé.', 'mj-member')), 400);
    }

    $memberId = (int) $memberRow->id;
    if ($memberId <= 0) {
        wp_send_json_error(array('message' => __('Profil membre invalide.', 'mj-member')), 400);
    }

    if (!class_exists(MjMemberHours::class)) {
        wp_send_json_error(array('message' => __('Enregistrement des heures indisponible.', 'mj-member')), 500);
    }

    $entryId = isset($_POST['entry_id']) ? (int) $_POST['entry_id'] : 0;
    if ($entryId <= 0) {
        wp_send_json_error(array('message' => __('Identifiant de plage invalide.', 'mj-member')));
    }

    $existing = mj_member_hour_encode_get_entry_for_member($memberId, $entryId);
    if (!$existing) {
        wp_send_json_error(array('message' => __('Plage introuvable.', 'mj-member')), 404);
    }

    $activityDate = isset($existing['activity_date']) ? (string) $existing['activity_date'] : '';
    $timezone = wp_timezone();
    $weekStart = mj_member_hour_encode_resolve_week_start($activityDate !== '' ? $activityDate : 'now', $timezone);
    $weekEnd = $weekStart->add(new DateInterval('P6D'))->setTime(23, 59, 59);

    $result = MjMemberHours::delete($entryId);
    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()));
    }

    list($entries, $projects) = mj_member_hour_encode_collect_entries_for_week($memberId, $weekStart, $weekEnd, $timezone);
    $projectCatalog = mj_member_hour_encode_collect_project_catalog($memberId);
    $projectTotals = mj_member_hour_encode_collect_project_totals($memberId, $timezone);

    $response = array(
        'week' => array(
            'start' => $weekStart->format('Y-m-d'),
            'end' => $weekEnd->format('Y-m-d'),
        ),
        'entries' => $entries,
        'projects' => $projects,
        'projectCatalog' => $projectCatalog,
        'projectTotals' => $projectTotals,
        'deleted' => $entryId,
    );

    wp_send_json_success($response);
}

/**
 * @return DateTimeImmutable
 */
function mj_member_hour_encode_resolve_week_start($weekParam, DateTimeZone $timezone) {
    try {
        if (is_string($weekParam) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $weekParam)) {
            $candidate = new DateTimeImmutable($weekParam, $timezone);
        } else {
            $candidate = new DateTimeImmutable('now', $timezone);
        }
    } catch (Exception $exception) {
        $candidate = new DateTimeImmutable('now', $timezone);
    }

    $candidate = $candidate->setTime(0, 0, 0);
    if ($candidate->format('N') !== '1') {
        $candidate = $candidate->modify('monday this week');
    }

    return $candidate->setTime(0, 0, 0);
}

/**
 * @param int $memberId
 * @return array<int,int>
 */
function mj_member_hour_encode_collect_event_ids($memberId) {
    $ids = array();

    if (class_exists(MjEventAnimateurs::class)) {
        $rows = MjEventAnimateurs::get_events_for_member($memberId, array(
            'statuses' => array(MjEvents::STATUS_ACTIVE),
            'orderby' => 'date_debut',
            'order' => 'ASC',
        ));
        foreach ($rows as $row) {
            if (!isset($row->id)) {
                continue;
            }
            $ids[(int) $row->id] = (int) $row->id;
        }
    }

    if (class_exists(MjEventVolunteers::class)) {
        $rows = MjEventVolunteers::get_events_for_member($memberId, array(
            'statuses' => array(MjEvents::STATUS_ACTIVE),
            'orderby' => 'date_debut',
            'order' => 'ASC',
        ));
        foreach ($rows as $row) {
            if (!isset($row->id)) {
                continue;
            }
            $ids[(int) $row->id] = (int) $row->id;
        }
    }

    return array_values($ids);
}

/**
 * @param array<int,int> $eventIds
 * @return array<int,array<string,mixed>>
 */
function mj_member_hour_encode_fetch_events(array $eventIds) {
    if (empty($eventIds) || !function_exists('mj_member_get_public_events')) {
        return array();
    }

    $payload = mj_member_get_public_events(array(
        'ids' => $eventIds,
        'include_past' => true,
        'limit' => count($eventIds),
        'orderby' => 'date_debut',
        'order' => 'ASC',
    ));

    $indexed = array();
    foreach ($payload as $event) {
        if (!isset($event['id'])) {
            continue;
        }
        $indexed[(int) $event['id']] = $event;
    }

    $results = array();
    foreach ($eventIds as $eventId) {
        if (isset($indexed[$eventId])) {
            $results[] = $indexed[$eventId];
        }
    }

    return $results;
}

/**
 * @return array<int,object|array>
 */
function mj_member_hour_encode_collect_closures(DateTimeImmutable $weekStart, DateTimeImmutable $weekEnd) {
    if (!class_exists(MjEventClosures::class)) {
        return array();
    }

    $closures = MjEventClosures::get_all(array(
        'from' => $weekStart->format('Y-m-d'),
        'to' => $weekEnd->format('Y-m-d'),
        'order' => 'ASC',
    ));

    if (!is_array($closures) || empty($closures)) {
        return array();
    }

    return $closures;
}

/**
 * @param array<int,object|array> $closures
 * @return array<int,array<string,mixed>>
 */
function mj_member_hour_encode_build_closure_occurrences(array $closures, DateTimeImmutable $weekStart, DateTimeImmutable $weekEnd, DateTimeZone $timezone) {
    if (empty($closures)) {
        return array();
    }

    $occurrences = array();
    $defaultTitle = __('Fermeture', 'mj-member');
    $defaultLocation = __('MJ fermée', 'mj-member');
    $baseColor = '#d1435b';

    foreach ($closures as $closure) {
        $id = 0;
        $startRaw = '';
        $endRaw = '';
        $description = '';

        if (is_object($closure)) {
            $id = isset($closure->id) ? (int) $closure->id : 0;
            if (!empty($closure->start_date)) {
                $startRaw = (string) $closure->start_date;
            } elseif (!empty($closure->closure_date)) {
                $startRaw = (string) $closure->closure_date;
            }
            if (!empty($closure->end_date)) {
                $endRaw = (string) $closure->end_date;
            }
            if (!empty($closure->description)) {
                $description = (string) $closure->description;
            }
        } elseif (is_array($closure)) {
            $id = isset($closure['id']) ? (int) $closure['id'] : 0;
            if (!empty($closure['start_date'])) {
                $startRaw = (string) $closure['start_date'];
            } elseif (!empty($closure['closure_date'])) {
                $startRaw = (string) $closure['closure_date'];
            }
            if (!empty($closure['end_date'])) {
                $endRaw = (string) $closure['end_date'];
            }
            if (!empty($closure['description'])) {
                $description = (string) $closure['description'];
            }
        } else {
            continue;
        }

        if ($startRaw === '') {
            continue;
        }

        $startDate = DateTimeImmutable::createFromFormat('Y-m-d', $startRaw, $timezone);
        if (!$startDate instanceof DateTimeImmutable) {
            try {
                $startDate = new DateTimeImmutable($startRaw, $timezone);
            } catch (Exception $exception) {
                $startDate = false;
            }
        }

        if (!$startDate instanceof DateTimeImmutable) {
            continue;
        }

        $endDate = $endRaw !== ''
            ? DateTimeImmutable::createFromFormat('Y-m-d', $endRaw, $timezone)
            : false;
        if (!$endDate instanceof DateTimeImmutable) {
            if ($endRaw !== '') {
                try {
                    $endDate = new DateTimeImmutable($endRaw, $timezone);
                } catch (Exception $exception) {
                    $endDate = false;
                }
            }
        }

        if (!$endDate instanceof DateTimeImmutable || $endDate < $startDate) {
            $endDate = $startDate;
        }

        if ($endDate < $weekStart || $startDate > $weekEnd) {
            continue;
        }

        $clampedStart = $startDate < $weekStart ? $weekStart : $startDate;
        $clampedEnd = $endDate > $weekEnd ? $weekEnd : $endDate;

        $startDateTime = $clampedStart->setTime(0, 0, 0);
        $endDateTime = $clampedEnd->setTime(23, 59, 59);

        $title = $description !== '' ? sanitize_text_field($description) : $defaultTitle;
        $accentCandidate = apply_filters('mj_member_hour_encode_closure_color', $baseColor, $closure);
        $accentColor = is_string($accentCandidate) ? sanitize_hex_color($accentCandidate) : '';
        if (empty($accentColor)) {
            $accentColor = $baseColor;
        }

        $eventId = $id > 0 ? 'closure-' . $id : 'closure-' . substr(md5($startDateTime->format('Y-m-d')), 0, 8);
        $occurrenceId = $eventId . '-' . $startDateTime->format('Ymd');

        $occurrence = array(
            'eventId' => $eventId,
            'occurrenceId' => $occurrenceId,
            'title' => $title,
            'start' => $startDateTime->format(DATE_ATOM),
            'end' => $endDateTime->format(DATE_ATOM),
            'location' => $defaultLocation,
            'accentColor' => $accentColor,
            'type' => 'closure',
            'typeLabel' => $defaultTitle,
            'permalink' => '',
        );

        /**
         * Permet de modifier une occurrence de fermeture renvoyée au widget d'encodage des heures.
         *
         * @param array<string,mixed>      $occurrence
         * @param object|array             $closure
         * @param DateTimeImmutable        $weekStart
         * @param DateTimeImmutable        $weekEnd
         */
        $occurrence = apply_filters(
            'mj_member_hour_encode_closure_occurrence',
            $occurrence,
            $closure,
            $weekStart,
            $weekEnd
        );

        if (is_array($occurrence)) {
            $occurrences[] = $occurrence;
        }
    }

    return $occurrences;
}

/**
 * @param array<int,array<string,mixed>> $events
 * @return array<int,array<string,mixed>>
 */
function mj_member_hour_encode_build_occurrences(array $events, $since, $until, $weekStartTimestamp, $weekEndTimestamp) {
    if (empty($events)) {
        return array();
    }

    $occurrences = array();

    foreach ($events as $event) {
        if (empty($event['id']) || empty($event['title'])) {
            continue;
        }

        $eventId = (int) $event['id'];
        $eventPayload = $event;
        $eventPayload['date_debut'] = isset($event['start_date']) ? $event['start_date'] : '';
        $eventPayload['date_fin'] = isset($event['end_date']) ? $event['end_date'] : '';

        $entries = array();
        if (class_exists(MjEventSchedule::class)) {
            $entries = MjEventSchedule::get_occurrences(
                $eventPayload,
                array(
                    'include_past' => true,
                    'since' => $since,
                    'until' => $until,
                    'max' => 20,
                )
            );
        }

        if (empty($entries)) {
            $fallback = mj_member_hour_encode_fallback_occurrence($eventPayload, $weekStartTimestamp, $weekEndTimestamp);
            if (!empty($fallback)) {
                $entries[] = $fallback;
            }
        }

        if (empty($entries)) {
            continue;
        }

        foreach ($entries as $entry) {
            if (empty($entry['start']) || empty($entry['end'])) {
                continue;
            }

            $startIso = mj_member_hour_encode_to_iso($entry['start']);
            $endIso = mj_member_hour_encode_to_iso($entry['end']);
            if ($startIso === '' || $endIso === '') {
                continue;
            }

            $occurrences[] = array(
                'eventId' => $eventId,
                'occurrenceId' => $eventId . '-' . substr(md5($entry['start'] . $entry['end']), 0, 8),
                'title' => sanitize_text_field($event['title']),
                'start' => $startIso,
                'end' => $endIso,
                'location' => isset($event['location']) ? sanitize_text_field($event['location']) : '',
                'accentColor' => isset($event['accent_color']) ? (sanitize_hex_color($event['accent_color']) ?: '') : '',
                'type' => isset($event['type']) ? sanitize_key($event['type']) : '',
                'typeLabel' => isset($event['type_label']) ? sanitize_text_field($event['type_label']) : '',
                'permalink' => isset($event['permalink']) ? esc_url_raw($event['permalink']) : '',
            );
        }
    }

    return $occurrences;
}

/**
 * @param array<string,mixed> $event
 * @return array<string,mixed>
 */
function mj_member_hour_encode_fallback_occurrence(array $event, $weekStartTimestamp, $weekEndTimestamp) {
    $startRaw = isset($event['date_debut']) ? $event['date_debut'] : '';
    $endRaw = isset($event['date_fin']) ? $event['date_fin'] : '';

    if ($startRaw === '') {
        return array();
    }

    $startTimestamp = strtotime($startRaw);
    if ($startTimestamp === false) {
        return array();
    }

    $endTimestamp = $endRaw !== '' ? strtotime($endRaw) : false;
    if ($endTimestamp === false || $endTimestamp <= $startTimestamp) {
        $endTimestamp = $startTimestamp + HOUR_IN_SECONDS;
    }

    if ($endTimestamp < $weekStartTimestamp || $startTimestamp > $weekEndTimestamp) {
        return array();
    }

    return array(
        'start' => date('Y-m-d H:i:s', $startTimestamp),
        'end' => date('Y-m-d H:i:s', $endTimestamp),
        'timestamp' => $startTimestamp,
    );
}

function mj_member_hour_encode_to_iso($value) {
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return '';
    }

    return function_exists('wp_date') ? wp_date('c', $timestamp) : date('c', $timestamp);
}

function mj_member_hour_encode_get_entry_for_member($memberId, $entryId) {
    if (!class_exists(MjMemberHours::class)) {
        return null;
    }

    $entry = MjMemberHours::get($entryId);
    if (!is_array($entry)) {
        return null;
    }

    if (!isset($entry['member_id']) || (int) $entry['member_id'] !== (int) $memberId) {
        return null;
    }

    return $entry;
}

function mj_member_hour_encode_collect_entries_for_week($memberId, DateTimeImmutable $weekStart, DateTimeImmutable $weekEnd, DateTimeZone $timezone) {
    if (!class_exists(MjMemberHours::class)) {
        return array(array(), array());
    }

    $rows = MjMemberHours::get_all(array(
        'member_id' => $memberId,
        'date_from' => $weekStart->format('Y-m-d'),
        'date_to' => $weekEnd->format('Y-m-d'),
        'orderby' => 'activity_date',
        'order' => 'ASC',
        'limit' => 400,
    ));

    if (!is_array($rows)) {
        $rows = array();
    }

    $entries = array();

    foreach ($rows as $record) {
        if (!is_array($record)) {
            continue;
        }
        $entry = mj_member_hour_encode_format_hour_entry($record, $timezone);
        if (empty($entry)) {
            continue;
        }
        $entries[] = $entry;
    }

    /**
     * Permet d’ajuster les entrées envoyées au widget d’encodage des heures.
     *
     * @param array<int,array<string,mixed>> $entries
     * @param array<string,mixed>            $context
     */
    $entries = apply_filters(
        'mj_member_hour_encode_week_entries',
        $entries,
        array(
            'member_id' => $memberId,
            'week_start' => $weekStart,
            'week_end' => $weekEnd,
        )
    );

    if (!is_array($entries)) {
        $entries = array();
    }

    $projects = array();
    foreach ($entries as $entry) {
        if (!is_array($entry) || empty($entry['project'])) {
            continue;
        }
        $project = sanitize_text_field((string) $entry['project']);
        if ($project !== '') {
            $projects[$project] = $project;
        }
    }

    return array(array_values($entries), array_values($projects));
}

function mj_member_hour_encode_collect_project_catalog($memberId) {
    if (!class_exists(MjMemberHours::class)) {
        return array();
    }

    $memberId = (int) $memberId;
    if ($memberId <= 0) {
        return array();
    }

    global $wpdb;
    $table = MjMemberHours::tableName();
    $sql = $wpdb->prepare("SELECT DISTINCT notes FROM {$table} WHERE member_id = %d AND notes IS NOT NULL AND notes <> '' ORDER BY notes ASC", $memberId);
    $rows = $wpdb->get_col($sql);
    if (!is_array($rows) || empty($rows)) {
        return array();
    }

    $projects = array();
    foreach ($rows as $label) {
        $sanitized = sanitize_text_field((string) $label);
        if ($sanitized !== '') {
            $projects[$sanitized] = $sanitized;
        }
    }

    return array_values($projects);
}

function mj_member_hour_encode_collect_project_totals($memberId, DateTimeZone $timezone) {
    if (!class_exists(MjMemberHours::class)) {
        return array();
    }

    $memberId = (int) $memberId;
    if ($memberId <= 0) {
        return array();
    }

    $rows = MjMemberHours::get_all(array(
        'member_id' => $memberId,
        'orderby' => 'activity_date',
        'order' => 'ASC',
        'limit' => 0,
    ));

    if (!is_array($rows) || empty($rows)) {
        return array();
    }

    $projects = array();

    foreach ($rows as $record) {
        if (!is_array($record)) {
            continue;
        }

        $minutes = isset($record['duration_minutes']) ? (int) $record['duration_minutes'] : 0;
        if ($minutes <= 0) {
            continue;
        }

        $projectLabel = isset($record['notes']) ? trim((string) $record['notes']) : '';
        $projectKey = $projectLabel !== '' ? $projectLabel : MjMemberHours::PROJECT_EMPTY_KEY;

        if (!isset($projects[$projectKey])) {
            $projects[$projectKey] = array(
                'project' => $projectLabel,
                'total_minutes' => 0,
                'months' => array(),
                'years' => array(),
                'weeks' => array(),
                'tasks' => array(),
            );
        }

        $projects[$projectKey]['total_minutes'] += $minutes;

        $taskLabel = isset($record['task_label']) ? sanitize_text_field((string) $record['task_label']) : '';
        if ($taskLabel !== '') {
            if (!isset($projects[$projectKey]['tasks'][$taskLabel])) {
                $projects[$projectKey]['tasks'][$taskLabel] = 0;
            }
            $projects[$projectKey]['tasks'][$taskLabel] += $minutes;
        }

        $activityDate = isset($record['activity_date']) ? (string) $record['activity_date'] : '';
        if ($activityDate === '') {
            continue;
        }

        $dateObject = DateTimeImmutable::createFromFormat('Y-m-d', $activityDate, $timezone);
        if (!$dateObject) {
            continue;
        }

        $yearKey = $dateObject->format('Y');
        $monthKey = $dateObject->format('Y-m');

        if ($yearKey !== '') {
            if (!isset($projects[$projectKey]['years'][$yearKey])) {
                $projects[$projectKey]['years'][$yearKey] = 0;
            }
            $projects[$projectKey]['years'][$yearKey] += $minutes;
        }

        if ($monthKey !== '') {
            if (!isset($projects[$projectKey]['months'][$monthKey])) {
                $projects[$projectKey]['months'][$monthKey] = 0;
            }
            $projects[$projectKey]['months'][$monthKey] += $minutes;
        }

        if (!isset($projects[$projectKey]['weeks']) || !is_array($projects[$projectKey]['weeks'])) {
            $projects[$projectKey]['weeks'] = array();
        }

        $dayOfWeek = (int) $dateObject->format('N');
        $daysToMonday = max(0, $dayOfWeek - 1);
        $weekStart = $daysToMonday > 0
            ? $dateObject->sub(new DateInterval('P' . $daysToMonday . 'D'))
            : $dateObject;
        $weekKey = $weekStart->format('Y-m-d');
        if ($weekKey !== '') {
            if (!isset($projects[$projectKey]['weeks'][$weekKey])) {
                $projects[$projectKey]['weeks'][$weekKey] = 0;
            }
            $projects[$projectKey]['weeks'][$weekKey] += $minutes;
        }
    }

    return array_values($projects);
}

function mj_member_hour_encode_build_week_response(DateTimeImmutable $weekStart, DateTimeImmutable $weekEnd, array $events, array $entries, array $projects, array $projectCatalog = array(), array $projectTotals = array()) {
    return array(
        'week' => array(
            'start' => $weekStart->format('Y-m-d'),
            'end' => $weekEnd->format('Y-m-d'),
        ),
        'events' => $events,
        'entries' => $entries,
        'projects' => $projects,
        'projectCatalog' => $projectCatalog,
        'projectTotals' => $projectTotals,
    );
}

function mj_member_hour_encode_validate_day_iso($value) {
    $value = is_string($value) ? trim($value) : '';
    if ($value === '') {
        return '';
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return '';
    }
    return $value;
}

function mj_member_hour_encode_normalize_time_value($value) {
    $value = is_string($value) ? trim(str_replace(',', ':', $value)) : '';
    if ($value === '') {
        return '';
    }
    if (!preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $value, $matches)) {
        return '';
    }
    $hour = (int) $matches[1];
    $minute = (int) $matches[2];
    $second = isset($matches[3]) ? (int) $matches[3] : 0;
    if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59 || $second < 0 || $second > 59) {
        return '';
    }
    return sprintf('%02d:%02d:%02d', $hour, $minute, $second);
}

function mj_member_hour_encode_combine_datetime($dayIso, $timeValue, DateTimeZone $timezone) {
    $dayIso = mj_member_hour_encode_validate_day_iso($dayIso);
    if ($dayIso === '') {
        return null;
    }

    $timeValue = is_string($timeValue) ? trim($timeValue) : '';
    if ($timeValue === '') {
        return null;
    }

    if (strlen($timeValue) === 5) {
        $timeValue .= ':00';
    }

    try {
        $dateTime = new DateTimeImmutable($dayIso . ' ' . $timeValue, $timezone);
    } catch (Exception $exception) {
        $dateTime = false;
    }

    if (!$dateTime instanceof DateTimeImmutable) {
        $dateTime = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $dayIso . ' ' . $timeValue, $timezone);
    }

    if ($dateTime instanceof DateTimeImmutable) {
        return $dateTime;
    }

    return null;
}

function mj_member_hour_encode_format_hour_entry(array $record, DateTimeZone $timezone) {
    $taskLabel = isset($record['task_label']) ? sanitize_text_field((string) $record['task_label']) : '';
    if ($taskLabel === '') {
        return null;
    }

    $activityDate = isset($record['activity_date']) ? (string) $record['activity_date'] : '';
    if (mj_member_hour_encode_validate_day_iso($activityDate) === '') {
        return null;
    }

    $startTime = isset($record['start_time']) && $record['start_time'] !== null ? (string) $record['start_time'] : '';
    $endTime = isset($record['end_time']) && $record['end_time'] !== null ? (string) $record['end_time'] : '';
    $duration = isset($record['duration_minutes']) ? (int) $record['duration_minutes'] : 0;

    $start = $startTime !== '' ? mj_member_hour_encode_combine_datetime($activityDate, $startTime, $timezone) : null;
    $end = $endTime !== '' ? mj_member_hour_encode_combine_datetime($activityDate, $endTime, $timezone) : null;

    if (!$start && $end && $duration > 0) {
        $start = $end->sub(new DateInterval('PT' . $duration . 'M'));
    }

    if ($start && !$end && $duration > 0) {
        $end = $start->add(new DateInterval('PT' . $duration . 'M'));
    }

    if (!$start || !$end || $end <= $start) {
        return null;
    }

    $project = '';
    if (isset($record['notes']) && $record['notes'] !== null && $record['notes'] !== '') {
        $project = sanitize_text_field((string) $record['notes']);
    }

    $rawColor = apply_filters('mj_member_hour_encode_entry_color', '#2a55ff', $record);
    $color = is_string($rawColor) ? sanitize_hex_color($rawColor) : '';
    if (!$color) {
        $color = '#2a55ff';
    }

    $id = isset($record['id']) ? (int) $record['id'] : 0;
    $duration = (int) round(($end->getTimestamp() - $start->getTimestamp()) / 60);
    if ($duration < 0) {
        $duration = 0;
    }
    $startTimeValue = isset($record['start_time']) && is_string($record['start_time']) ? $record['start_time'] : $start->format('H:i:s');
    $endTimeValue = isset($record['end_time']) && is_string($record['end_time']) ? $record['end_time'] : $end->format('H:i:s');
    $taskKey = isset($record['task_key']) && is_string($record['task_key']) ? sanitize_key($record['task_key']) : '';

    $entry = array(
        'id' => $id > 0 ? 'hour-' . $id : uniqid('hour-', false),
        'hourId' => $id > 0 ? $id : null,
        'task' => $taskLabel,
        'project' => $project,
        'start' => $start->format(DATE_ATOM),
        'end' => $end->format(DATE_ATOM),
        'color' => $color,
        'durationMinutes' => $duration,
        'startTime' => $startTimeValue,
        'endTime' => $endTimeValue,
        'taskKey' => $taskKey,
    );

    /**
     * Permet de modifier l’entrée renvoyée au widget.
     *
     * @param array<string,mixed> $entry
     * @param array<string,mixed> $record
     */
    $entry = apply_filters('mj_member_hour_encode_formatted_entry', $entry, $record, $timezone);

    return is_array($entry) ? $entry : null;
}
