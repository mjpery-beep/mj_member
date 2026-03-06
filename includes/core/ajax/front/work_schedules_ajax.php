<?php
/**
 * AJAX front – Horaires de travail des employés.
 *
 * @package MjMember
 */

use Mj\Member\Classes\Crud\MjMembers;
use Mj\Member\Classes\Crud\MjMemberWorkSchedules;
use Mj\Member\Classes\MjRoles;
use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Return work schedules for all staff members.
 * Access: logged-in users with hoursCapability OR staff role.
 */
add_action('wp_ajax_mj_work_schedules_get_all', 'mj_work_schedules_get_all_handler');

function mj_work_schedules_get_all_handler(): void
{
    check_ajax_referer('mj_work_schedules', 'nonce');

    $currentUserId = get_current_user_id();
    if ($currentUserId <= 0) {
        wp_send_json_error(array('message' => __('Non connecté.', 'mj-member')));
    }

    // Check access: hoursCapability OR staff member
    $hasAccess = false;
    $hoursCapability = Config::hoursCapability();
    if ($hoursCapability !== '' && current_user_can($hoursCapability)) {
        $hasAccess = true;
    }

    if (!$hasAccess) {
        $members = MjMembers::get_all(array(
            'filters' => array('wp_user_id' => $currentUserId),
            'limit'   => 1,
        ));
        if (!empty($members)) {
            $hasAccess = MjRoles::isStaff($members[0]->role);
        }
    }

    if (!$hasAccess) {
        wp_send_json_error(array('message' => __('Accès refusé.', 'mj-member')));
    }

    // Reference date for "active" schedules (defaults to today)
    $referenceDate = isset($_POST['reference_date']) ? sanitize_text_field(wp_unslash($_POST['reference_date'])) : null;
    if ($referenceDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $referenceDate)) {
        $referenceDate = null;
    }

    // Get all staff members
    $staffMembers = MjMembers::get_all(array(
        'filters' => array('roles' => array(MjRoles::ANIMATEUR, MjRoles::COORDINATEUR)),
        'limit'   => 100,
    ));

    $results = array();

    foreach ($staffMembers as $member) {
        $memberId = (int) $member->id;
        $activeSchedule = MjMemberWorkSchedules::get_active_for_member($memberId, $referenceDate);

        $schedule = array();
        if ($activeSchedule && !empty($activeSchedule->schedule)) {
            $decoded = is_string($activeSchedule->schedule)
                ? json_decode($activeSchedule->schedule, true)
                : $activeSchedule->schedule;
            $schedule = is_array($decoded) ? $decoded : array();
        }

        $results[] = array(
            'memberId'  => $memberId,
            'name'      => trim($member->first_name . ' ' . $member->last_name),
            'role'      => $member->role,
            'schedule'  => $schedule,
            'startDate' => $activeSchedule ? $activeSchedule->start_date : null,
            'endDate'   => $activeSchedule ? $activeSchedule->end_date : null,
        );
    }

    wp_send_json_success(array('schedules' => $results));
}
