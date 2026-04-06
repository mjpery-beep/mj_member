<?php
/**
 * AJAX front – Horaires de travail des employés.
 *
 * @package MjMember
 */

namespace Mj\Member\Core\Ajax\Front;

use Mj\Member\Core\Contracts\AjaxHandlerInterface;
use Mj\Member\Classes\Crud\MjMembers;
use Mj\Member\Classes\Crud\MjMemberWorkSchedules;
use Mj\Member\Classes\MjRoles;
use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

final class WorkSchedulesController implements AjaxHandlerInterface
{
    public function registerHooks(): void
    {
        add_action('wp_ajax_mj_work_schedules_get_all', [$this, 'workSchedulesGetAll']);
        add_action('wp_ajax_nopriv_mj_work_schedules_get_all', [$this, 'workSchedulesGetAll']);
    }

    /**
     * Return work schedules for all staff members.
     * Access: any visitor (logged-in or not).
     */
    public function workSchedulesGetAll(): void
    {
        check_ajax_referer('mj_work_schedules', 'nonce');

        // Reference date for "active" schedules (defaults to today)
        $referenceDate = isset($_POST['reference_date']) ? sanitize_text_field(wp_unslash($_POST['reference_date'])) : null;
        if ($referenceDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $referenceDate)) {
            $referenceDate = null;
        }

        // Get all active staff members
        $staffMembers = MjMembers::get_all(array(
            'filters' => array(
                'roles'  => array(MjRoles::ANIMATEUR, MjRoles::COORDINATEUR),
                'status' => MjMembers::STATUS_ACTIVE,
            ),
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
}
