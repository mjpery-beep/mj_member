<?php
/**
 * AJAX front – Widget Agenda unifié.
 *
 * Agrège 7 couches datées (occurrences d'événement, todos, congés, horaires de
 * travail, heures prestées, demandes, notes internes) et expose les opérations
 * d'écriture pilotées par la matrice d'ACL {@see MjAgendaAcl}.
 *
 * @package MjMember
 */

namespace Mj\Member\Core\Ajax\Front;

use Mj\Member\Core\Contracts\AjaxHandlerInterface;
use Mj\Member\Core\Config;
use Mj\Member\Classes\MjAgendaAcl;
use Mj\Member\Classes\MjRoles;
use Mj\Member\Classes\Crud\MjMembers;
use Mj\Member\Classes\Crud\MjAgendaNotes;
use Mj\Member\Classes\Crud\MjMemberHours;
use Mj\Member\Classes\Crud\MjTodos;
use Mj\Member\Classes\MjEventSchedule;

if (!defined('ABSPATH')) {
    exit;
}

final class AgendaController implements AjaxHandlerInterface
{
    private const NONCE = 'mj-member-agenda';

    public function registerHooks(): void
    {
        add_action('wp_ajax_mj_member_agenda_fetch', array($this, 'fetch'));
        add_action('wp_ajax_mj_member_agenda_save_entry', array($this, 'saveEntry'));
        add_action('wp_ajax_mj_member_agenda_move_entry', array($this, 'moveEntry'));
        add_action('wp_ajax_mj_member_agenda_delete_entry', array($this, 'deleteEntry'));
        add_action('wp_ajax_mj_member_agenda_save_prefs', array($this, 'savePrefs'));

        add_action('admin_init', array(__CLASS__, 'seedDefaults'));
    }

    public static function seedDefaults(): void
    {
        if (class_exists(MjAgendaAcl::class)) {
            MjAgendaAcl::seedDefaults();
        }
    }

    /**
     * Session data injected next to the widget script.
     */
    public static function localize(): void
    {
        $userId = get_current_user_id();
        $prefs = $userId ? get_user_meta($userId, 'mj_member_agenda_prefs', true) : array();

        wp_localize_script('mj-member-agenda-app', 'mjMemberAgenda', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce(self::NONCE),
            'prefs' => is_array($prefs) ? $prefs : array(),
            'i18n' => array(
                'loading' => __('Chargement de l\'agenda…', 'mj-member'),
                'error' => __('Impossible de charger l\'agenda.', 'mj-member'),
                'today' => __('Aujourd\'hui', 'mj-member'),
                'add' => __('Ajouter', 'mj-member'),
                'move_confirm' => __('Déplacer cet élément ?', 'mj-member'),
                'forbidden' => __('Action non autorisée pour votre rôle.', 'mj-member'),
            ),
        ));
    }

    // -----------------------------------------------------------------
    // Shared request plumbing
    // -----------------------------------------------------------------

    /**
     * @return array{user_id:int,member_id:int,role:string}
     */
    private function requireActor(): array
    {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), self::NONCE)) {
            wp_send_json_error(array('message' => __('Sécurité échouée.', 'mj-member')), 403);
        }

        $userId = get_current_user_id();
        if (!$userId) {
            wp_send_json_error(array('message' => __('Vous devez être connecté.', 'mj-member')), 403);
        }

        $memberId = 0;
        $role = MjRoles::JEUNE;
        if (class_exists(MjMembers::class)) {
            $member = MjMembers::getByWpUserId($userId);
            if ($member) {
                $arr = method_exists($member, 'toArray') ? $member->toArray() : (array) $member;
                $memberId = isset($arr['id']) ? (int) $arr['id'] : 0;
                $role = MjAgendaAcl::normalizeRole($arr['role'] ?? '');
            }
        }
        $role = MjAgendaAcl::normalizeRole($role);

        return array('user_id' => $userId, 'member_id' => $memberId, 'role' => $role);
    }

    private static function sanitizeDate($value): string
    {
        $value = is_string($value) ? trim($value) : '';
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
    }

    /**
     * Visibility tokens a given MJ role may read for internal notes.
     *
     * @return string[]
     */
    private static function noteVisibilitiesForRole(string $role): array
    {
        $tokens = array();
        // Staff roles can see staff + their own role-scoped notes.
        if (in_array($role, array(MjRoles::COORDINATEUR, MjRoles::ANIMATEUR, MjRoles::BENEVOLE), true)) {
            $tokens[] = MjAgendaNotes::VISIBILITY_STAFF;
        }
        $tokens[] = 'role:' . $role;
        if ($role === MjRoles::COORDINATEUR) {
            // Coordinators see everything role-scoped.
            foreach (MjAgendaAcl::roles() as $r) {
                $tokens[] = 'role:' . $r;
            }
        }

        return array_values(array_unique($tokens));
    }

    // -----------------------------------------------------------------
    // fetch
    // -----------------------------------------------------------------

    public function fetch(): void
    {
        $actor = $this->requireActor();
        $role = $actor['role'];
        $memberId = $actor['member_id'];

        $start = self::sanitizeDate($_POST['start'] ?? '');
        $end = self::sanitizeDate($_POST['end'] ?? '');
        if ($start === '' || $end === '') {
            wp_send_json_error(array('message' => __('Plage de dates invalide.', 'mj-member')), 400);
        }
        if (strtotime($end) < strtotime($start)) {
            list($start, $end) = array($end, $start);
        }

        $requestedLayers = isset($_POST['layers']) && is_array($_POST['layers'])
            ? array_map('sanitize_key', wp_unslash($_POST['layers']))
            : MjAgendaAcl::LAYERS;
        $requestedLayers = array_values(array_intersect(MjAgendaAcl::LAYERS, $requestedLayers));

        $eventStatuses = isset($_POST['eventStatuses']) && is_array($_POST['eventStatuses'])
            ? array_values(array_filter(array_map('sanitize_key', wp_unslash($_POST['eventStatuses']))))
            : array('actif');
        $eventTypes = isset($_POST['eventTypes']) && is_array($_POST['eventTypes'])
            ? array_values(array_filter(array_map('sanitize_key', wp_unslash($_POST['eventTypes']))))
            : array();

        $events = array();
        $legend = array();
        $errors = array();

        $labels = array(
            'event_occurrences' => __('Événements', 'mj-member'),
            'todos' => __('Tâches', 'mj-member'),
            'leave_requests' => __('Congés', 'mj-member'),
            'work_schedules' => __('Horaires de travail', 'mj-member'),
            'worked_hours' => __('Heures prestées', 'mj-member'),
            'requests' => __('Demandes', 'mj-member'),
            'internal_notes' => __('Notes internes', 'mj-member'),
        );

        foreach ($requestedLayers as $layer) {
            if (!MjAgendaAcl::can($role, $layer, 'view')) {
                continue;
            }
            $canOthers = MjAgendaAcl::can($role, $layer, 'view_others');
            $scopeMemberId = $canOthers ? 0 : $memberId;

            // One broken data source must not 500 the whole agenda.
            try {
                switch ($layer) {
                    case 'event_occurrences':
                        $events = array_merge($events, $this->fetchOccurrences($start, $end, $eventStatuses, $eventTypes, $role, $memberId));
                        break;
                    case 'todos':
                        $events = array_merge($events, $this->fetchTodos($start, $end, $scopeMemberId, $role, $memberId));
                        break;
                    case 'leave_requests':
                        $events = array_merge($events, $this->fetchLeaves($start, $end, $scopeMemberId, $role, $memberId));
                        break;
                    case 'work_schedules':
                        $events = array_merge($events, $this->fetchWorkSchedules($start, $end, $scopeMemberId));
                        break;
                    case 'worked_hours':
                        $events = array_merge($events, $this->fetchWorkedHours($start, $end, $scopeMemberId, $role, $memberId));
                        break;
                    case 'requests':
                        $events = array_merge($events, $this->fetchRequests($start, $end, $scopeMemberId, $role, $memberId));
                        break;
                    case 'internal_notes':
                        $events = array_merge($events, $this->fetchInternalNotes($start, $end, $role, $memberId));
                        break;
                }
                $legend[] = array('layer' => $layer, 'label' => $labels[$layer] ?? $layer);
            } catch (\Throwable $e) {
                $errors[$layer] = $e->getMessage();
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[mj-agenda] layer ' . $layer . ' failed: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
                }
            }
        }

        $closures = array();
        try {
            $closures = $this->fetchClosures($start, $end);
        } catch (\Throwable $e) {
            $errors['closures'] = $e->getMessage();
        }

        wp_send_json_success(array(
            'events' => array_values($events),
            'meta' => array(
                'range' => array('start' => $start, 'end' => $end),
                'closures' => $closures,
                'legend' => $legend,
                'role' => $role,
                'errors' => $errors,
            ),
        ));
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function fetchOccurrences(string $start, string $end, array $statuses, array $types, string $role, int $memberId): array
    {
        if (!function_exists('mj_member_get_public_events') || !class_exists(MjEventSchedule::class)) {
            return array();
        }

        $since = $start . ' 00:00:00';
        $until = $end . ' 23:59:59';

        $statusFilter = !empty($statuses) ? $statuses : array('actif');
        if (in_array($role, array(MjRoles::COORDINATEUR, MjRoles::ANIMATEUR), true) && !in_array('brouillon', $statusFilter, true)) {
            $statusFilter[] = 'brouillon';
        }

        $eventList = mj_member_get_public_events(array(
            'statuses' => $statusFilter,
            'types' => $types,
            'limit' => 300,
            'order' => 'ASC',
            'orderby' => 'date_debut',
            'include_past' => true,
        ));

        $out = array();
        $canEdit = MjAgendaAcl::can($role, 'event_occurrences', 'edit_own', true)
            || MjAgendaAcl::can($role, 'event_occurrences', 'edit_others', false);
        $canMove = MjAgendaAcl::can($role, 'event_occurrences', 'move', true)
            || MjAgendaAcl::can($role, 'event_occurrences', 'move', false);

        foreach ((array) $eventList as $event) {
            if (!is_array($event) || empty($event['id'])) {
                continue;
            }

            $occurrences = MjEventSchedule::get_occurrences($event, array(
                'since' => $since,
                'until' => $until,
                'include_past' => true,
                'max' => 400,
            ));

            foreach ((array) $occurrences as $occ) {
                if (empty($occ['start']) || empty($occ['end'])) {
                    continue;
                }
                $startTs = strtotime((string) $occ['start']);
                if ($startTs === false || $startTs < strtotime($since) || $startTs > strtotime($until)) {
                    continue;
                }

                $cancelled = isset($occ['status']) && in_array($occ['status'], array('annule', 'cancelled'), true);
                $draft = isset($event['status']) && in_array($event['status'], array('brouillon', 'draft'), true);

                $out[] = array(
                    'id' => 'occurrence:' . (int) $event['id'] . ':' . $startTs,
                    'layer' => 'event_occurrences',
                    'kind' => 'occurrence',
                    'title' => (string) ($event['emoji'] ? $event['emoji'] . ' ' : '') . ($event['title'] ?? __('Événement', 'mj-member')),
                    'start' => self::toDisplay((string) $occ['start']),
                    'end' => self::toDisplay((string) $occ['end']),
                    'allDay' => false,
                    'color' => $event['accent_color'] ?? '',
                    'editable' => $canEdit && !$cancelled,
                    'movable' => $canMove && !$cancelled,
                    'flags' => array('cancelled' => $cancelled, 'draft' => $draft),
                    'preview' => array(
                        'event_id' => (int) $event['id'],
                        'occurrence_start' => (string) $occ['start'],
                        'title' => $event['title'] ?? '',
                        'emoji' => $event['emoji'] ?? '',
                        'type' => $event['type'] ?? '',
                        'status' => $event['status'] ?? '',
                        'occurrence_status' => $occ['status'] ?? '',
                        'location' => $event['location'] ?? '',
                        'price' => $event['price'] ?? 0,
                        'permalink' => $event['permalink'] ?? '',
                        'description' => $event['description'] ?? '',
                    ),
                );
            }
        }

        return $out;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function fetchTodos(string $start, string $end, int $scopeMemberId, string $role, int $memberId): array
    {
        if (!class_exists(MjTodos::class) || !method_exists(MjTodos::class, 'get_by_date_range')) {
            return array();
        }

        $todos = MjTodos::get_by_date_range($start, $end);
        $out = array();

        $canMove = MjAgendaAcl::can($role, 'todos', 'move', true) || MjAgendaAcl::can($role, 'todos', 'move', false);
        $canEdit = MjAgendaAcl::can($role, 'todos', 'edit_own', true) || MjAgendaAcl::can($role, 'todos', 'edit_others', false);

        foreach ((array) $todos as $todo) {
            $due = isset($todo['due_date']) ? substr((string) $todo['due_date'], 0, 10) : '';
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $due)) {
                continue;
            }

            $assignees = array();
            if (!empty($todo['assignees']) && is_array($todo['assignees'])) {
                foreach ($todo['assignees'] as $a) {
                    if (isset($a['id'])) {
                        $assignees[] = (int) $a['id'];
                    }
                }
            }
            if ($scopeMemberId > 0 && !in_array($scopeMemberId, $assignees, true)
                && (int) ($todo['assigned_member_id'] ?? 0) !== $scopeMemberId) {
                continue;
            }

            $completed = isset($todo['status']) && $todo['status'] === 'completed';

            $out[] = array(
                'id' => 'todo:' . (int) $todo['id'],
                'layer' => 'todos',
                'kind' => 'todo',
                'title' => '📋 ' . (string) ($todo['title'] ?? __('Tâche', 'mj-member')),
                'start' => $due,
                'end' => $due,
                'allDay' => true,
                'color' => $todo['project_color'] ?? '',
                'editable' => $canEdit,
                'movable' => $canMove,
                'flags' => array('completed' => $completed),
                'preview' => array(
                    'todo_id' => (int) $todo['id'],
                    'title' => $todo['title'] ?? '',
                    'description' => $todo['description'] ?? '',
                    'project' => $todo['project_name'] ?? '',
                    'priority' => (int) ($todo['position'] ?? 0),
                    'due_date' => $due,
                    'status' => $todo['status'] ?? 'open',
                ),
            );
        }

        return $out;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function fetchLeaves(string $start, string $end, int $scopeMemberId, string $role, int $memberId): array
    {
        if (!class_exists('\\Mj\\Member\\Classes\\Crud\\MjLeaveRequests')) {
            return array();
        }

        $cls = '\\Mj\\Member\\Classes\\Crud\\MjLeaveRequests';
        $rows = $cls::get_all(array(
            'statuses' => array($cls::STATUS_APPROVED, $cls::STATUS_PENDING),
        ));

        $typeCache = array();
        $memberCache = array();
        $out = array();

        foreach ((array) $rows as $row) {
            $row = (array) $row;
            $rowMemberId = (int) ($row['member_id'] ?? 0);
            if ($scopeMemberId > 0 && $rowMemberId !== $scopeMemberId) {
                continue;
            }

            $dates = json_decode((string) ($row['dates'] ?? '[]'), true);
            if (!is_array($dates)) {
                continue;
            }

            $typeId = (int) ($row['type_id'] ?? 0);
            if ($typeId > 0 && !isset($typeCache[$typeId]) && class_exists('\\Mj\\Member\\Classes\\Crud\\MjLeaveTypes')) {
                $typeCache[$typeId] = \Mj\Member\Classes\Crud\MjLeaveTypes::get_by_id($typeId);
            }
            $type = $typeCache[$typeId] ?? null;
            $typeArr = $type ? (array) $type : array();

            if (!isset($memberCache[$rowMemberId]) && $rowMemberId > 0 && class_exists(MjMembers::class) && method_exists(MjMembers::class, 'getById')) {
                $memberCache[$rowMemberId] = MjMembers::getById($rowMemberId);
            }
            $m = $memberCache[$rowMemberId] ?? null;
            $mArr = $m ? (method_exists($m, 'toArray') ? $m->toArray() : (array) $m) : array();
            $memberName = trim(($mArr['first_name'] ?? '') . ' ' . ($mArr['last_name'] ?? ''));

            foreach ($dates as $day) {
                $day = substr((string) $day, 0, 10);
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) || $day < $start || $day > $end) {
                    continue;
                }

                $out[] = array(
                    'id' => 'leave:' . (int) ($row['id'] ?? 0) . ':' . $day,
                    'layer' => 'leave_requests',
                    'kind' => 'leave',
                    'title' => '🌴 ' . ($memberName !== '' ? $memberName : __('Congé', 'mj-member')),
                    'start' => $day,
                    'end' => $day,
                    'allDay' => true,
                    'background' => true,
                    'color' => $typeArr['color'] ?? '',
                    'editable' => false,
                    'movable' => false,
                    'preview' => array(
                        'leave_id' => (int) ($row['id'] ?? 0),
                        'member_name' => $memberName,
                        'type_name' => $typeArr['name'] ?? '',
                        'status' => $row['status'] ?? '',
                        'reason' => $row['reason'] ?? '',
                    ),
                );
            }
        }

        return $out;
    }

    /**
     * Phase 1: naive weekly projection of the active schedule across the range.
     *
     * @return array<int,array<string,mixed>>
     */
    private function fetchWorkSchedules(string $start, string $end, int $scopeMemberId): array
    {
        $cls = '\\Mj\\Member\\Classes\\Crud\\MjMemberWorkSchedules';
        if (!class_exists($cls) || !method_exists($cls, 'get_active_for_member')) {
            return array();
        }

        $members = array();
        if ($scopeMemberId > 0) {
            $members[] = $scopeMemberId;
        } elseif (class_exists(MjMembers::class) && method_exists(MjMembers::class, 'get_all')) {
            $staff = MjMembers::get_all(array(
                'filters' => array('roles' => array(MjRoles::ANIMATEUR, MjRoles::COORDINATEUR)),
                'limit' => 100,
            ));
            foreach ((array) $staff as $m) {
                $members[] = (int) $m->id;
            }
        }

        $mid = (int) ((strtotime($start) + strtotime($end)) / 2);
        $refDate = gmdate('Y-m-d', $mid);
        $out = array();

        foreach ($members as $mId) {
            $active = $cls::get_active_for_member($mId, $refDate);
            if (!$active || empty($active->schedule)) {
                continue;
            }
            $schedule = is_string($active->schedule) ? json_decode($active->schedule, true) : $active->schedule;
            if (!is_array($schedule)) {
                continue;
            }

            $cursor = strtotime($start);
            $limit = strtotime($end);
            while ($cursor <= $limit) {
                $day = gmdate('Y-m-d', $cursor);
                $dow = (int) gmdate('N', $cursor); // 1=Mon..7=Sun
                $slots = self::scheduleSlotsForDow($schedule, $dow, $day);
                foreach ($slots as $slot) {
                    $out[] = array(
                        'id' => 'work_schedule:' . $mId . ':' . $day . ':' . $slot['start'],
                        'layer' => 'work_schedules',
                        'kind' => 'work_schedule',
                        'title' => '🗓️ ' . __('Horaire', 'mj-member'),
                        'start' => $day . ' ' . $slot['start'],
                        'end' => $day . ' ' . $slot['end'],
                        'allDay' => false,
                        'background' => true,
                        'editable' => false,
                        'movable' => false,
                        'preview' => array(
                            'member_id' => $mId,
                            'period_start' => $active->start_date ?? '',
                            'period_end' => $active->end_date ?? '',
                        ),
                    );
                }
                $cursor += DAY_IN_SECONDS;
            }
        }

        return $out;
    }

    /**
     * Best-effort extraction of {start,end} time slots for a weekday from the
     * loosely-typed work-schedule JSON blob.
     *
     * @param array<string,mixed> $schedule
     * @return array<int,array{start:string,end:string}>
     */
    private static function scheduleSlotsForDow(array $schedule, int $dow, string $day): array
    {
        $keys = array(
            (string) $dow,
            (string) ($dow - 1),
            strtolower(gmdate('l', strtotime($day))),
            substr(strtolower(gmdate('D', strtotime($day))), 0, 3),
        );

        $bucket = null;
        foreach ($keys as $k) {
            if (isset($schedule[$k])) {
                $bucket = $schedule[$k];
                break;
            }
        }
        if ($bucket === null && isset($schedule['days']) && is_array($schedule['days'])) {
            foreach ($keys as $k) {
                if (isset($schedule['days'][$k])) {
                    $bucket = $schedule['days'][$k];
                    break;
                }
            }
        }
        if (!is_array($bucket)) {
            return array();
        }

        $slots = array();
        $candidates = isset($bucket[0]) ? $bucket : array($bucket);
        foreach ($candidates as $c) {
            if (!is_array($c)) {
                continue;
            }
            $s = $c['start'] ?? ($c['from'] ?? ($c['debut'] ?? ''));
            $e = $c['end'] ?? ($c['to'] ?? ($c['fin'] ?? ''));
            if (preg_match('/^\d{1,2}:\d{2}$/', (string) $s) && preg_match('/^\d{1,2}:\d{2}$/', (string) $e)) {
                $slots[] = array('start' => substr('0' . $s, -5), 'end' => substr('0' . $e, -5));
            }
        }

        return $slots;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function fetchWorkedHours(string $start, string $end, int $scopeMemberId, string $role, int $memberId): array
    {
        if (!class_exists(MjMemberHours::class) || !method_exists(MjMemberHours::class, 'get_all')) {
            return array();
        }

        $args = array('date_from' => $start, 'date_to' => $end, 'limit' => 1000, 'order' => 'ASC');
        if ($scopeMemberId > 0) {
            $args['member_id'] = $scopeMemberId;
        }
        $rows = MjMemberHours::get_all($args);

        $canMove = MjAgendaAcl::can($role, 'worked_hours', 'move', true) || MjAgendaAcl::can($role, 'worked_hours', 'move', false);
        $canEdit = MjAgendaAcl::can($role, 'worked_hours', 'edit_own', true) || MjAgendaAcl::can($role, 'worked_hours', 'edit_others', false);

        $out = array();
        foreach ((array) $rows as $row) {
            $day = substr((string) ($row['activity_date'] ?? ''), 0, 10);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
                continue;
            }
            $startTime = $row['start_time'] ? substr((string) $row['start_time'], 0, 5) : null;
            $endTime = $row['end_time'] ? substr((string) $row['end_time'], 0, 5) : null;
            $timed = $startTime && $endTime;

            $out[] = array(
                'id' => 'worked_hours:' . (int) $row['id'],
                'layer' => 'worked_hours',
                'kind' => 'worked_hours',
                'title' => '⏱️ ' . (string) ($row['task_label'] ?? __('Heures', 'mj-member')),
                'start' => $timed ? $day . ' ' . $startTime : $day,
                'end' => $timed ? $day . ' ' . $endTime : $day,
                'allDay' => !$timed,
                'editable' => $canEdit,
                'movable' => $canMove,
                'preview' => array(
                    'entry_id' => (int) $row['id'],
                    'task_label' => $row['task_label'] ?? '',
                    'project_id' => (int) ($row['project_id'] ?? 0),
                    'member_id' => (int) ($row['member_id'] ?? 0),
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'duration_minutes' => (int) ($row['duration_minutes'] ?? 0),
                    'notes' => $row['notes'] ?? '',
                ),
            );
        }

        return $out;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function fetchRequests(string $start, string $end, int $scopeMemberId, string $role, int $memberId): array
    {
        $cls = '\\Mj\\Member\\Classes\\Crud\\MjRequests';
        if (!class_exists($cls) || !method_exists($cls, 'get_all')) {
            return array();
        }

        $rows = $cls::get_all(array('limit' => 500));
        $out = array();

        foreach ((array) $rows as $rowObj) {
            $row = (array) $rowObj;
            if ($scopeMemberId > 0 && (int) ($row['member_id'] ?? 0) !== $scopeMemberId) {
                continue;
            }

            $slots = array();
            if (is_object($rowObj) && method_exists($cls, 'get_slots')) {
                try {
                    $slots = (array) $cls::get_slots($rowObj);
                } catch (\Throwable $e) {
                    $slots = array();
                }
            }
            if (empty($slots) && !empty($row['slots_json'])) {
                $decoded = json_decode((string) $row['slots_json'], true);
                if (is_array($decoded)) {
                    $slots = $decoded;
                }
            }
            if (empty($slots) && !empty($row['week_start']) && isset($row['slot_day'])) {
                $dayTs = strtotime((string) $row['week_start'] . ' +' . (int) $row['slot_day'] . ' days');
                if ($dayTs !== false) {
                    $slots = array(array(
                        'date' => gmdate('Y-m-d', $dayTs),
                        'start' => $row['slot_start'] ?? '',
                        'end' => $row['slot_end'] ?? '',
                    ));
                }
            }

            foreach ($slots as $slot) {
                $day = substr((string) ($slot['date'] ?? ''), 0, 10);
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) || $day < $start || $day > $end) {
                    continue;
                }
                $s = preg_match('/^\d{1,2}:\d{2}$/', (string) ($slot['start'] ?? '')) ? substr('0' . $slot['start'], -5) : '';
                $e = preg_match('/^\d{1,2}:\d{2}$/', (string) ($slot['end'] ?? '')) ? substr('0' . $slot['end'], -5) : '';
                $timed = $s !== '' && $e !== '';

                $out[] = array(
                    'id' => 'request:' . (int) ($row['id'] ?? 0) . ':' . $day,
                    'layer' => 'requests',
                    'kind' => 'request',
                    'title' => '📨 ' . (string) ($row['title'] ?? __('Demande', 'mj-member')),
                    'start' => $timed ? $day . ' ' . $s : $day,
                    'end' => $timed ? $day . ' ' . $e : $day,
                    'allDay' => !$timed,
                    'editable' => false,
                    'movable' => false,
                    'preview' => array(
                        'request_id' => (int) ($row['id'] ?? 0),
                        'title' => $row['title'] ?? '',
                        'request_type' => $row['request_type'] ?? '',
                        'status' => $row['status'] ?? '',
                        'description' => $row['description'] ?? '',
                        'age_range' => $row['age_range'] ?? '',
                    ),
                );
            }
        }

        return $out;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function fetchInternalNotes(string $start, string $end, string $role, int $memberId): array
    {
        if (!class_exists(MjAgendaNotes::class)) {
            return array();
        }

        $rows = MjAgendaNotes::get_by_date_range($start, $end, array(
            'visibilities' => self::noteVisibilitiesForRole($role),
            'author_member_id' => $memberId,
        ));

        $canEditOthers = MjAgendaAcl::can($role, 'internal_notes', 'edit_others', false);
        $canMove = MjAgendaAcl::can($role, 'internal_notes', 'move', true) || MjAgendaAcl::can($role, 'internal_notes', 'move', false);
        $out = array();

        foreach ($rows as $note) {
            $isOwner = (int) $note['author_member_id'] === $memberId && $memberId > 0;
            $day = $note['note_date'];
            $timed = $note['start_time'] && $note['end_time'];
            $label = $note['title'] !== '' ? $note['title'] : wp_trim_words($note['content'], 8, '…');

            $out[] = array(
                'id' => 'internal_note:' . (int) $note['id'],
                'layer' => 'internal_notes',
                'kind' => 'internal_note',
                'title' => '📝 ' . $label,
                'start' => $timed ? $day . ' ' . $note['start_time'] : $day,
                'end' => $timed ? $day . ' ' . $note['end_time'] : $day,
                'allDay' => !$timed,
                'color' => $note['color'],
                'editable' => $isOwner || $canEditOthers,
                'movable' => ($isOwner || $canEditOthers) && $canMove,
                'preview' => array(
                    'note_id' => (int) $note['id'],
                    'title' => $note['title'],
                    'content' => $note['content'],
                    'visibility' => $note['visibility'],
                    'author_name' => $note['author_name'],
                    'is_owner' => $isOwner,
                ),
            );
        }

        return $out;
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private function fetchClosures(string $start, string $end): array
    {
        if (!class_exists('MjEventClosures') || !method_exists('MjEventClosures', 'get_dates_map_between')) {
            return array();
        }
        $map = \MjEventClosures::get_dates_map_between($start, $end);

        return is_array($map) ? $map : array();
    }

    private static function toDisplay(string $mysqlDateTime): string
    {
        // 'Y-m-d H:i:s' -> 'Y-m-d H:i'
        $ts = strtotime($mysqlDateTime);
        return $ts ? gmdate('Y-m-d H:i', $ts) : substr($mysqlDateTime, 0, 16);
    }

    // -----------------------------------------------------------------
    // write endpoints (skeletons — phase 2)
    // -----------------------------------------------------------------

    public function saveEntry(): void
    {
        $this->requireActor();
        wp_send_json_error(array('message' => __('Édition indisponible pour le moment.', 'mj-member')), 501);
    }

    public function moveEntry(): void
    {
        $this->requireActor();
        wp_send_json_error(array('message' => __('Déplacement indisponible pour le moment.', 'mj-member')), 501);
    }

    public function deleteEntry(): void
    {
        $this->requireActor();
        wp_send_json_error(array('message' => __('Suppression indisponible pour le moment.', 'mj-member')), 501);
    }

    public function savePrefs(): void
    {
        $actor = $this->requireActor();

        $view = isset($_POST['view']) ? sanitize_key(wp_unslash($_POST['view'])) : '';
        $filtersRaw = isset($_POST['filters']) ? wp_unslash($_POST['filters']) : '';
        $filters = array();
        if (is_string($filtersRaw) && $filtersRaw !== '') {
            $decoded = json_decode($filtersRaw, true);
            if (is_array($decoded)) {
                $filters = array_map('sanitize_text_field', array_map('strval', $decoded));
            }
        }

        update_user_meta($actor['user_id'], 'mj_member_agenda_prefs', array(
            'view' => $view,
            'filters' => $filters,
            'saved_at' => time(),
        ));

        wp_send_json_success(array('saved' => true));
    }
}

/**
 * Back-compat helper invoked by AssetsManager::requirePackage().
 */
function mj_member_agenda_localize(): void
{
    AgendaController::localize();
}
