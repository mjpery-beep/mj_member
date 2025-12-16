<?php

use Mj\Member\Admin\Page\HoursPage;
use Mj\Member\Classes\Crud\MjMemberHours;
use Mj\Member\Classes\Crud\MjMembers;
use Mj\Member\Classes\Value\MemberData;
use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_ajax_mj_member_hours_create', 'mj_member_ajax_hours_create');
add_action('wp_ajax_mj_member_hours_calendar', 'mj_member_ajax_hours_calendar');
add_action('wp_ajax_mj_member_hours_list', 'mj_member_ajax_hours_list');
add_action('wp_ajax_mj_member_hours_rename_task', 'mj_member_ajax_hours_rename_task');
add_action('wp_ajax_mj_member_hours_rename_project', 'mj_member_ajax_hours_rename_project');

function mj_member_ajax_hours_create() {
    check_ajax_referer('mj_member_hours', 'nonce');

    $capability = Config::hoursCapability();
    if ($capability === '') {
        $capability = Config::capability();
    }

    if (!current_user_can($capability)) {
        wp_send_json_error(array('message' => __('Accès refusé.', 'mj-member')), 403);
    }

    $memberId = isset($_POST['member_id']) ? (int) $_POST['member_id'] : 0;
    $taskLabel = isset($_POST['task_label']) ? sanitize_text_field(wp_unslash($_POST['task_label'])) : '';
    $taskKey = isset($_POST['task_key']) ? sanitize_key(wp_unslash($_POST['task_key'])) : '';
    $activityDate = isset($_POST['activity_date']) ? sanitize_text_field(wp_unslash($_POST['activity_date'])) : '';
    $startTime = isset($_POST['start_time']) ? sanitize_text_field(wp_unslash($_POST['start_time'])) : '';
    $endTime = isset($_POST['end_time']) ? sanitize_text_field(wp_unslash($_POST['end_time'])) : '';
    $duration = isset($_POST['duration_minutes']) ? (int) $_POST['duration_minutes'] : 0;
    $notes = isset($_POST['notes']) ? sanitize_textarea_field(wp_unslash($_POST['notes'])) : '';

    if ($memberId <= 0) {
        wp_send_json_error(array('message' => __('Sélectionnez un membre valide.', 'mj-member')));
    }

    if ($taskLabel === '') {
        wp_send_json_error(array('message' => __('Saisissez une tâche.', 'mj-member')));
    }

    if ($activityDate === '') {
        wp_send_json_error(array('message' => __('Indiquez une date.', 'mj-member')));
    }

    if (($startTime !== '' && $endTime === '') || ($startTime === '' && $endTime !== '')) {
        wp_send_json_error(array('message' => __('Renseignez une heure de début et une heure de fin.', 'mj-member')));
    }

    if ($startTime !== '' && $endTime !== '') {
        $startTimestamp = strtotime('1970-01-01 ' . $startTime);
        $endTimestamp = strtotime('1970-01-01 ' . $endTime);

        if ($startTimestamp === false || $endTimestamp === false || $endTimestamp <= $startTimestamp) {
            wp_send_json_error(array('message' => __('L’heure de fin doit être postérieure à l’heure de début.', 'mj-member')));
        }

        $computedMinutes = (int) round(($endTimestamp - $startTimestamp) / 60);
        if ($computedMinutes > 0) {
            $duration = $computedMinutes;
        }
    }

    if ($duration <= 0) {
        wp_send_json_error(array('message' => __('Durée invalide.', 'mj-member')));
    }

    if ($taskKey === '' && $taskLabel !== '') {
        $taskKey = sanitize_title($taskLabel);
    }

    $payload = array(
        'member_id' => $memberId,
        'task_label' => $taskLabel,
        'task_key' => $taskKey !== '' ? $taskKey : null,
        'activity_date' => $activityDate,
        'start_time' => $startTime,
        'end_time' => $endTime,
        'duration_minutes' => $duration,
        'notes' => $notes !== '' ? $notes : null,
        'recorded_by' => get_current_user_id(),
    );

    $result = MjMemberHours::create($payload);
    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()));
    }

    $entry = MjMemberHours::get($result);
    if (!$entry) {
        wp_send_json_error(array('message' => __('Heure encodée, mais impossible de récupérer la fiche.', 'mj-member')));
    }

    $member = MjMembers::getById($memberId);
    $memberLabel = '';
    if ($member) {
        $memberLabel = trim(sprintf('%s %s', $member->first_name ?? '', $member->last_name ?? ''));
    }
    if ($memberLabel === '') {
        $memberLabel = sprintf(__('Membre #%d', 'mj-member'), $memberId);
    }

    $entry['member_label'] = $memberLabel;
    $entry['duration_human'] = HoursPage::formatDuration((int) $entry['duration_minutes']);
    $entry['activity_date_display'] = HoursPage::formatDate($entry['activity_date']);
    $entry['created_at_display'] = HoursPage::formatDateTime($entry['created_at']);
    $entry['notes'] = isset($entry['notes']) && $entry['notes'] !== null ? $entry['notes'] : '';
    $entry['start_time_display'] = HoursPage::formatTime(isset($entry['start_time']) ? $entry['start_time'] : '');
    $entry['end_time_display'] = HoursPage::formatTime(isset($entry['end_time']) ? $entry['end_time'] : '');
    $entry['time_range_display'] = HoursPage::formatTimeRange(isset($entry['start_time']) ? $entry['start_time'] : '', isset($entry['end_time']) ? $entry['end_time'] : '');

    $userId = get_current_user_id();
    $recordedBy = $entry['recorded_by'] ?? $userId;
    $entry['recorded_by_label'] = '';
    if (!empty($recordedBy)) {
        $user = get_user_by('id', (int) $recordedBy);
        if ($user instanceof WP_User) {
            $entry['recorded_by_label'] = $user->display_name;
        }
    }

    $weeklySummary = HoursPage::getWeeklySummaryForMember($memberId, 8);

    $activityDate = isset($entry['activity_date']) ? (string) $entry['activity_date'] : '';
    $calendarMonth = null;
    if ($activityDate !== '') {
        $monthKey = substr($activityDate, 0, 7);
        if (is_string($monthKey) && strlen($monthKey) === 7) {
            $calendarMonth = HoursPage::prepareCalendarMonth($memberId, $monthKey);
        }
    }

    wp_send_json_success(array(
        'message' => __('Heures encodées avec succès.', 'mj-member'),
        'entry' => $entry,
        'weeklySummary' => $weeklySummary,
        'calendarMonth' => $calendarMonth,
    ));
}

function mj_member_ajax_hours_calendar() {
    check_ajax_referer('mj_member_hours', 'nonce');

    $capability = Config::hoursCapability();
    if ($capability === '') {
        $capability = Config::capability();
    }

    if (!current_user_can($capability)) {
        wp_send_json_error(array('message' => __('Accès refusé.', 'mj-member')), 403);
    }

    $currentUserId = get_current_user_id();
    if ($currentUserId <= 0) {
        wp_send_json_error(array('message' => __('Utilisateur non authentifié.', 'mj-member')), 401);
    }

    $member = MjMembers::getByWpUserId($currentUserId);
    if (!($member instanceof MemberData)) {
        wp_send_json_error(array('message' => __('Impossible de déterminer le membre associé.', 'mj-member')), 400);
    }

    $memberId = isset($member->id) ? (int) $member->id : 0;
    if ($memberId <= 0) {
        wp_send_json_error(array('message' => __('Profil membre invalide.', 'mj-member')), 400);
    }

    $canManageOthers = HoursPage::canManageOtherMembers();

    $requestedMemberId = isset($_POST['member_id']) ? (int) $_POST['member_id'] : $memberId;
    if (!$canManageOthers && $requestedMemberId > 0 && $requestedMemberId !== $memberId) {
        wp_send_json_error(array('message' => __('Vous ne pouvez pas consulter les encodages d’un autre membre.', 'mj-member')), 403);
    }

    if ($requestedMemberId <= 0) {
        wp_send_json_success(array('calendar' => HoursPage::prepareCalendarMonth(0, null)));
    }

    $monthKey = isset($_POST['month']) ? sanitize_text_field(wp_unslash($_POST['month'])) : '';
    $calendar = HoursPage::prepareCalendarMonth($requestedMemberId, $monthKey !== '' ? $monthKey : null);

    wp_send_json_success(array(
        'calendar' => $calendar,
    ));
}

function mj_member_ajax_hours_list() {
    check_ajax_referer('mj_member_hours', 'nonce');

    $capability = Config::hoursCapability();
    if ($capability === '') {
        $capability = Config::capability();
    }

    if (!current_user_can($capability)) {
        wp_send_json_error(array('message' => __('Accès refusé.', 'mj-member')), 403);
    }

    $currentUserId = get_current_user_id();
    if ($currentUserId <= 0) {
        wp_send_json_error(array('message' => __('Utilisateur non authentifié.', 'mj-member')), 401);
    }

    $member = MjMembers::getByWpUserId($currentUserId);
    if (!($member instanceof MemberData)) {
        wp_send_json_error(array('message' => __('Impossible de déterminer le membre associé.', 'mj-member')), 400);
    }

    $currentMemberId = isset($member->id) ? (int) $member->id : 0;
    if ($currentMemberId <= 0) {
        wp_send_json_error(array('message' => __('Profil membre invalide.', 'mj-member')), 400);
    }

    $currentMemberLabel = HoursPage::formatMemberLabel($member);
    $canManageOthers = HoursPage::canManageOtherMembers();

    $requestedMemberId = isset($_POST['member_id']) ? (int) $_POST['member_id'] : $currentMemberId;
    if (!$canManageOthers) {
        $requestedMemberId = $currentMemberId;
    }

    $projectRaw = isset($_POST['project']) ? (string) wp_unslash($_POST['project']) : '';
    $projectKey = HoursPage::sanitizeProjectKeyRequest($projectRaw);

    $calendarMonth = isset($_POST['calendar_month']) ? sanitize_text_field(wp_unslash((string) $_POST['calendar_month'])) : '';
    $includeCalendar = $requestedMemberId > 0 && (!isset($_POST['include_calendar']) || (int) $_POST['include_calendar'] === 1);

    $state = HoursPage::getAdminState(
        $currentMemberId,
        $currentMemberLabel,
        $canManageOthers,
        array(
            'member_id' => $requestedMemberId,
            'project_key' => $projectKey,
            'calendar_month' => $calendarMonth !== '' ? $calendarMonth : null,
            'include_calendar' => $includeCalendar,
        )
    );

    $filters = array(
        'canManageOthers' => $canManageOthers,
        'selectedMemberId' => isset($state['selected_member_id']) ? (int) $state['selected_member_id'] : 0,
        'selectedProjectKey' => isset($state['selected_project_key']) ? (string) $state['selected_project_key'] : '',
        'members' => isset($state['member_options']) ? array_values($state['member_options']) : array(),
        'projects' => isset($state['project_options']) ? $state['project_options'] : array(),
        'projectEmptyKey' => MjMemberHours::PROJECT_EMPTY_KEY,
    );

    wp_send_json_success(array(
        'filters' => $filters,
        'recentEntries' => HoursPage::prepareRecentEntriesPayload($state['recent_entries']),
        'weeklySummary' => isset($state['weekly_summary']) ? $state['weekly_summary'] : array(),
        'calendar' => isset($state['calendar']) ? $state['calendar'] : array(),
        'hasCalendar' => !empty($state['has_calendar']),
        'projectSummary' => isset($state['project_summary']) ? $state['project_summary'] : array(),
        'projectWithoutLabel' => isset($state['project_without_label']) ? (string) $state['project_without_label'] : '',
    ));
}

function mj_member_ajax_hours_rename_task() {
    check_ajax_referer('mj_member_hours', 'nonce');

    $capability = Config::hoursCapability();
    if ($capability === '') {
        $capability = Config::capability();
    }

    if (!current_user_can($capability)) {
        wp_send_json_error(array('message' => __('Accès refusé.', 'mj-member')), 403);
    }

    $currentUserId = get_current_user_id();
    if ($currentUserId <= 0) {
        wp_send_json_error(array('message' => __('Utilisateur non authentifié.', 'mj-member')), 401);
    }

    $member = MjMembers::getByWpUserId($currentUserId);
    if (!($member instanceof MemberData)) {
        wp_send_json_error(array('message' => __('Impossible de déterminer le membre associé.', 'mj-member')), 400);
    }

    $currentMemberId = isset($member->id) ? (int) $member->id : 0;
    if ($currentMemberId <= 0) {
        wp_send_json_error(array('message' => __('Profil membre invalide.', 'mj-member')), 400);
    }

    $canManageOthers = HoursPage::canManageOtherMembers();

    $oldLabel = isset($_POST['old_label']) ? sanitize_text_field(wp_unslash((string) $_POST['old_label'])) : '';
    $newLabel = isset($_POST['new_label']) ? sanitize_text_field(wp_unslash((string) $_POST['new_label'])) : '';

    if ($oldLabel === '' || $newLabel === '') {
        wp_send_json_error(array('message' => __('Libellé de tâche invalide.', 'mj-member')));
    }

    if (strcasecmp($oldLabel, $newLabel) === 0) {
        wp_send_json_success(array('updated' => 0));
    }

    $scopeMemberId = isset($_POST['member_id']) ? (int) $_POST['member_id'] : 0;
    if (!$canManageOthers) {
        $scopeMemberId = $currentMemberId;
    }

    $scope = array();
    if ($scopeMemberId > 0) {
        $scope['member_id'] = $scopeMemberId;
    }

    $result = MjMemberHours::bulkRenameTask($oldLabel, $newLabel, $scope);
    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()));
    }

    wp_send_json_success(array('updated' => (int) $result));
}

function mj_member_ajax_hours_rename_project() {
    check_ajax_referer('mj_member_hours', 'nonce');

    $capability = Config::hoursCapability();
    if ($capability === '') {
        $capability = Config::capability();
    }

    if (!current_user_can($capability)) {
        wp_send_json_error(array('message' => __('Accès refusé.', 'mj-member')), 403);
    }

    $currentUserId = get_current_user_id();
    if ($currentUserId <= 0) {
        wp_send_json_error(array('message' => __('Utilisateur non authentifié.', 'mj-member')), 401);
    }

    $member = MjMembers::getByWpUserId($currentUserId);
    if (!($member instanceof MemberData)) {
        wp_send_json_error(array('message' => __('Impossible de déterminer le membre associé.', 'mj-member')), 400);
    }

    $currentMemberId = isset($member->id) ? (int) $member->id : 0;
    if ($currentMemberId <= 0) {
        wp_send_json_error(array('message' => __('Profil membre invalide.', 'mj-member')), 400);
    }

    $canManageOthers = HoursPage::canManageOtherMembers();

    $projectKey = isset($_POST['project_key']) ? sanitize_text_field(wp_unslash((string) $_POST['project_key'])) : '';
    $oldLabelRaw = isset($_POST['old_label']) ? sanitize_text_field(wp_unslash((string) $_POST['old_label'])) : '';
    $newLabel = isset($_POST['new_label']) ? sanitize_text_field(wp_unslash((string) $_POST['new_label'])) : '';

    if ($newLabel === '') {
        wp_send_json_error(array('message' => __('Le libellé de projet est invalide.', 'mj-member')));
    }

    if (strcasecmp($oldLabelRaw, $newLabel) === 0) {
        wp_send_json_success(array('updated' => 0));
    }

    if (strcasecmp($projectKey, MjMemberHours::PROJECT_EMPTY_KEY) === 0) {
        $oldLabelRaw = '';
    }

    $scopeMemberId = isset($_POST['member_id']) ? (int) $_POST['member_id'] : 0;
    if (!$canManageOthers) {
        $scopeMemberId = $currentMemberId;
    }

    $scope = array();
    if ($scopeMemberId > 0) {
        $scope['member_id'] = $scopeMemberId;
    }

    $result = MjMemberHours::bulkRenameProject($oldLabelRaw, $newLabel, $scope);
    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()));
    }

    wp_send_json_success(array('updated' => (int) $result));
}
