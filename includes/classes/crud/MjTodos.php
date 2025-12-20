<?php

namespace Mj\Member\Classes\Crud;

use Mj\Member\Classes\MjRoles;
use Mj\Member\Classes\MjTools;
use Mj\Member\Classes\Value\MemberData;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class MjTodos extends MjTools implements CrudRepositoryInterface
{
    private const TABLE = 'mj_todos';
    private const ASSIGNMENTS_TABLE = 'mj_todo_assignments';

    public const STATUS_OPEN = 'open';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_ARCHIVED = 'archived';

    /**
     * @var array<int,string>
     */
    private const ASSIGNABLE_ROLES = array(
        MjRoles::ANIMATEUR,
        MjRoles::COORDINATEUR,
        MjRoles::BENEVOLE,
    );

    private static function table_name(): string
    {
        if (function_exists('mj_member_get_todos_table_name')) {
            return mj_member_get_todos_table_name();
        }

        return self::getTableName(self::TABLE);
    }

    private static function assignments_table_name(): string
    {
        if (function_exists('mj_member_get_todo_assignments_table_name')) {
            return mj_member_get_todo_assignments_table_name();
        }

        return self::getTableName(self::ASSIGNMENTS_TABLE);
    }

    /**
     * @return array<int,string>
     */
    public static function statuses(): array
    {
        return array(self::STATUS_OPEN, self::STATUS_COMPLETED, self::STATUS_ARCHIVED);
    }

    /**
     * @return array<int,string>
     */
    public static function assignableRoles(): array
    {
        return self::ASSIGNABLE_ROLES;
    }

    /**
     * @param array<string,mixed> $args
     * @return array<int,array<string,mixed>>
     */
    public static function get_all(array $args = array())
    {
        global $wpdb;
        $table = self::table_name();

        $defaults = array(
            'status' => '',
            'statuses' => array(),
            'project_id' => 0,
            'assigned_member_id' => 0,
            'include_ids' => array(),
            'search' => '',
            'limit' => 0,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC',
        );
        $args = wp_parse_args($args, $defaults);

        $builder = CrudQueryBuilder::for_table($table);

        $statuses = array();
        if (!empty($args['statuses']) && is_array($args['statuses'])) {
            foreach ($args['statuses'] as $candidate) {
                $normalized = self::normalize_status($candidate);
                if ($normalized !== '') {
                    $statuses[] = $normalized;
                }
            }
        } elseif (!empty($args['status'])) {
            $single = self::normalize_status($args['status']);
            if ($single !== '') {
                $statuses[] = $single;
            }
        }

        if (!empty($statuses)) {
            $builder->where_in_strings('status', $statuses, static function ($value) {
                return self::normalize_status($value);
            });
        }

        $projectId = (int) $args['project_id'];
        if ($projectId > 0) {
            $builder->where_equals_int('project_id', $projectId);
        }

        $assignedMemberId = (int) $args['assigned_member_id'];
        if ($assignedMemberId > 0) {
            $assignmentsTable = self::assignments_table_name();
            $tableExists = !function_exists('mj_member_table_exists') || mj_member_table_exists($assignmentsTable);
            if ($tableExists) {
                $builder->where_raw(
                    '(assigned_member_id = %d OR id IN (SELECT todo_id FROM ' . $assignmentsTable . ' WHERE member_id = %d))',
                    array($assignedMemberId, $assignedMemberId)
                );
            } else {
                $builder->where_equals_int('assigned_member_id', $assignedMemberId);
            }
        }

        if (!empty($args['include_ids'])) {
            $builder->where_in_int('id', (array) $args['include_ids']);
        }

        if (!empty($args['search'])) {
            $builder->where_like_any(array('title', 'description'), (string) $args['search']);
        }

        $allowedOrderBy = array('created_at', 'updated_at', 'due_date', 'position', 'title', 'status', 'id');
        $orderby = sanitize_key((string) $args['orderby']);
        if (!in_array($orderby, $allowedOrderBy, true)) {
            $orderby = 'created_at';
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

        $todos = array_map(array(__CLASS__, 'format_row'), $rows);
        $todos = self::attach_assignments($todos);
        $todos = self::attach_notes($todos);
        $todos = self::attach_media($todos);
        return $todos;
    }

    /**
     * @param array<int,array<string,mixed>> $todos
     * @return array<int,array<string,mixed>>
     */
    private static function attach_assignments(array $todos): array
    {
        if (empty($todos)) {
            return $todos;
        }

        $ids = array();
        foreach ($todos as $todo) {
            if (isset($todo['id'])) {
                $ids[] = (int) $todo['id'];
            }
        }

        $assignmentsMap = self::fetch_assignments_map($ids);

        foreach ($todos as &$todo) {
            $todoId = isset($todo['id']) ? (int) $todo['id'] : 0;
            $assignees = $assignmentsMap[$todoId] ?? array();
            $todo['assignees'] = $assignees;

            if (!empty($assignees)) {
                $todo['assigned_member_id'] = isset($todo['assigned_member_id']) && (int) $todo['assigned_member_id'] > 0
                    ? (int) $todo['assigned_member_id']
                    : (int) $assignees[0]['id'];
            } else {
                $todo['assigned_member_id'] = isset($todo['assigned_member_id']) ? (int) $todo['assigned_member_id'] : 0;
            }
        }
        unset($todo);

        return $todos;
    }

    /**
     * @param array<int,array<string,mixed>> $todos
     * @return array<int,array<string,mixed>>
     */
    private static function attach_notes(array $todos): array
    {
        if (empty($todos) || !class_exists(__NAMESPACE__ . '\\MjTodoNotes')) {
            foreach ($todos as &$todo) {
                if (!isset($todo['notes'])) {
                    $todo['notes'] = array();
                }
                if (!isset($todo['media'])) {
                    $todo['media'] = array();
                }
            }
            unset($todo);
            return $todos;
        }

        $ids = array();
        foreach ($todos as $todo) {
            if (isset($todo['id'])) {
                $ids[] = (int) $todo['id'];
            }
        }

        if (empty($ids)) {
            foreach ($todos as &$todo) {
                if (!isset($todo['notes'])) {
                    $todo['notes'] = array();
                }
            }
            unset($todo);
            return $todos;
        }

        $notesMap = MjTodoNotes::get_for_todo_ids($ids);

        foreach ($todos as &$todo) {
            $todoId = isset($todo['id']) ? (int) $todo['id'] : 0;
            $todo['notes'] = $notesMap[$todoId] ?? array();
            if (!isset($todo['media'])) {
                $todo['media'] = array();
            }
        }
        unset($todo);

        return $todos;
    }

    /**
     * @param array<int,array<string,mixed>> $todos
     * @return array<int,array<string,mixed>>
     */
    private static function attach_media(array $todos): array
    {
        if (empty($todos) || !class_exists(__NAMESPACE__ . '\\MjTodoMedia')) {
            foreach ($todos as &$todo) {
                if (!isset($todo['media'])) {
                    $todo['media'] = array();
                }
            }
            unset($todo);
            return $todos;
        }

        $ids = array();
        foreach ($todos as $todo) {
            if (isset($todo['id'])) {
                $ids[] = (int) $todo['id'];
            }
        }

        if (empty($ids)) {
            foreach ($todos as &$todo) {
                if (!isset($todo['media'])) {
                    $todo['media'] = array();
                }
            }
            unset($todo);
            return $todos;
        }

        $mediaMap = MjTodoMedia::get_for_todo_ids($ids);

        foreach ($todos as &$todo) {
            $todoId = isset($todo['id']) ? (int) $todo['id'] : 0;
            $todo['media'] = $mediaMap[$todoId] ?? array();
        }
        unset($todo);

        return $todos;
    }

    /**
     * @param array<int,int> $todoIds
     * @return array<int,array<int,array<string,mixed>>>
     */
    private static function fetch_assignments_map(array $todoIds): array
    {
        $todoIds = array_filter(array_unique(array_map('intval', $todoIds)), static function ($value) {
            return $value > 0;
        });

        if (empty($todoIds)) {
            return array();
        }

        $assignmentsTable = self::assignments_table_name();
        if (function_exists('mj_member_table_exists') && !mj_member_table_exists($assignmentsTable)) {
            return array();
        }

        global $wpdb;
        $membersTable = self::getTableName(MjMembers::TABLE_NAME);
        $idsSql = implode(',', array_map('intval', $todoIds));

        $results = $wpdb->get_results(
            "SELECT a.todo_id, a.member_id, a.assigned_by, a.assigned_at, m.first_name, m.last_name, m.role
            FROM {$assignmentsTable} a
            LEFT JOIN {$membersTable} m ON m.id = a.member_id
            WHERE a.todo_id IN ({$idsSql})
            ORDER BY a.todo_id ASC, m.last_name ASC, m.first_name ASC, a.member_id ASC",
            ARRAY_A
        );

        if (empty($results)) {
            return array();
        }

        $map = array();
        foreach ($results as $row) {
            $todoId = isset($row['todo_id']) ? (int) $row['todo_id'] : 0;
            $memberId = isset($row['member_id']) ? (int) $row['member_id'] : 0;
            if ($todoId <= 0 || $memberId <= 0) {
                continue;
            }

            $firstName = isset($row['first_name']) ? sanitize_text_field((string) $row['first_name']) : '';
            $lastName = isset($row['last_name']) ? sanitize_text_field((string) $row['last_name']) : '';
            $name = trim($firstName . ' ' . $lastName);
            if ($name === '') {
                $name = sprintf(__('Membre #%d', 'mj-member'), $memberId);
            }

            $role = isset($row['role']) ? sanitize_key((string) $row['role']) : '';

            $map[$todoId][] = array(
                'id' => $memberId,
                'name' => $name,
                'role' => $role,
                'assigned_by' => isset($row['assigned_by']) ? (int) $row['assigned_by'] : 0,
                'assigned_at' => isset($row['assigned_at']) ? (string) $row['assigned_at'] : '',
            );
        }

        return $map;
    }

    /**
     * @param array<string,mixed> $args
     * @return int
     */
    public static function count(array $args = array())
    {
        global $wpdb;
        $table = self::table_name();

        $defaults = array(
            'status' => '',
            'project_id' => 0,
            'assigned_member_id' => 0,
            'search' => '',
        );
        $args = wp_parse_args($args, $defaults);

        $builder = CrudQueryBuilder::for_table($table);

        $status = self::normalize_status($args['status']);
        if ($status !== '') {
            $builder->where_equals('status', $status, static function ($value) {
                return self::normalize_status($value);
            });
        }

        $projectId = (int) $args['project_id'];
        if ($projectId > 0) {
            $builder->where_equals_int('project_id', $projectId);
        }

        $assignedMemberId = (int) $args['assigned_member_id'];
        if ($assignedMemberId > 0) {
            $assignmentsTable = self::assignments_table_name();
            $tableExists = !function_exists('mj_member_table_exists') || mj_member_table_exists($assignmentsTable);
            if ($tableExists) {
                $builder->where_raw(
                    '(assigned_member_id = %d OR id IN (SELECT todo_id FROM ' . $assignmentsTable . ' WHERE member_id = %d))',
                    array($assignedMemberId, $assignedMemberId)
                );
            } else {
                $builder->where_equals_int('assigned_member_id', $assignedMemberId);
            }
        }

        if (!empty($args['search'])) {
            $builder->where_like_any(array('title', 'description'), (string) $args['search']);
        }

        list($sql, $params) = $builder->build_count('*');
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        $result = $wpdb->get_var($sql);
        return $result ? (int) $result : 0;
    }

    /**
     * @param array<string,mixed>|null $data
     * @return int|WP_Error
     */
    public static function create($data)
    {
        if (!is_array($data)) {
            return new WP_Error('mj_member_todo_invalid_payload', __('Format de données invalide pour le todo.', 'mj-member'));
        }

        global $wpdb;
        $table = self::table_name();

        $title = sanitize_text_field($data['title'] ?? '');
        if ($title === '') {
            return new WP_Error('mj_member_todo_missing_title', __('Le titre de la tâche est requis.', 'mj-member'));
        }

        $description = isset($data['description']) ? sanitize_textarea_field($data['description']) : '';
        $status = self::normalize_status($data['status'] ?? self::STATUS_OPEN);
        if ($status === '') {
            $status = self::STATUS_OPEN;
        }

        $projectId = isset($data['project_id']) ? (int) $data['project_id'] : 0;
        if ($projectId > 0 && !self::project_exists($projectId)) {
            return new WP_Error('mj_member_todo_invalid_project', __('Dossier introuvable.', 'mj-member'));
        }

        $assignedMemberIds = self::normalize_assigned_member_ids($data);
        foreach ($assignedMemberIds as $memberId) {
            $validation = self::validate_assignable_member($memberId);
            if (is_wp_error($validation)) {
                return $validation;
            }
        }
        $primaryAssigneeId = !empty($assignedMemberIds) ? (int) $assignedMemberIds[0] : 0;

        $dueDate = self::sanitize_due_date($data['due_date'] ?? '');
        if ($dueDate instanceof WP_Error) {
            return $dueDate;
        }

        $createdBy = isset($data['created_by']) ? (int) $data['created_by'] : get_current_user_id();
        if ($createdBy < 0) {
            $createdBy = 0;
        }

        $positionInput = isset($data['position']) ? (int) $data['position'] : 0;
        if ($positionInput <= 0) {
            $positionInput = 3;
        }
        $position = max(1, min(5, $positionInput));

        $assignedBy = isset($data['assigned_by']) ? (int) $data['assigned_by'] : 0;
        if ($assignedBy <= 0 && !empty($assignedMemberIds)) {
            $assignedBy = $createdBy;
        }

        $completedBy = isset($data['completed_by']) ? (int) $data['completed_by'] : ($status === self::STATUS_COMPLETED ? (!empty($assignedMemberIds) ? (int) $assignedMemberIds[0] : $createdBy) : 0);
        $completedAt = isset($data['completed_at']) ? self::sanitize_datetime($data['completed_at']) : ($status === self::STATUS_COMPLETED ? current_time('mysql') : null);
        if ($completedAt instanceof WP_Error) {
            return $completedAt;
        }

        $insert = array(
            'title' => $title,
            'status' => $status,
            'created_by' => $createdBy,
            'position' => $position,
        );
        $formats = array('%s', '%s', '%d', '%d');

        if ($description !== '') {
            $insert['description'] = $description;
            $formats[] = '%s';
        }

        if ($projectId > 0) {
            $insert['project_id'] = $projectId;
            $formats[] = '%s';
        }

        if ($dueDate !== null) {
            $insert['due_date'] = $dueDate;
            $formats[] = '%s';
        }

        if ($primaryAssigneeId > 0) {
            $insert['assigned_member_id'] = $primaryAssigneeId;
            $formats[] = '%s';
            if ($assignedBy > 0) {
                $insert['assigned_by'] = $assignedBy;
                $formats[] = '%s';
            }
        }

        if ($status === self::STATUS_COMPLETED) {
            if ($completedAt !== null) {
                $insert['completed_at'] = $completedAt;
                $formats[] = '%s';
            }
            if ($completedBy > 0) {
                $insert['completed_by'] = $completedBy;
                $formats[] = '%s';
            }
        }

        $result = $wpdb->insert($table, $insert, $formats);
        if ($result === false) {
            return new WP_Error('mj_member_todo_insert_failed', __('Impossible de créer la tâche.', 'mj-member'));
        }

        $todoId = (int) $wpdb->insert_id;
        if ($todoId <= 0) {
            return new WP_Error('mj_member_todo_insert_failed', __('Impossible de créer la tâche.', 'mj-member'));
        }

        $sync = self::sync_assignments($todoId, $assignedMemberIds, $assignedBy);
        if (is_wp_error($sync)) {
            return $sync;
        }

        return $todoId;
    }

    /**
     * @param int $id
     * @param array<string,mixed>|null $data
     * @return true|WP_Error
     */
    public static function update($id, $data)
    {
        $id = (int) $id;
        if ($id <= 0) {
            return new WP_Error('mj_member_todo_invalid_id', __('Identifiant de tâche invalide.', 'mj-member'));
        }

        if (!is_array($data)) {
            return new WP_Error('mj_member_todo_invalid_payload', __('Format de données invalide pour la tâche.', 'mj-member'));
        }

        $existing = self::get($id);
        if (!$existing) {
            return new WP_Error('mj_member_todo_not_found', __('Tâche introuvable.', 'mj-member'));
        }

        $assignmentsShouldSync = array_key_exists('assigned_member_ids', $data) || array_key_exists('assigned_member_id', $data);
        $assignedMemberIds = array();
        $assignedByOverride = array_key_exists('assigned_by', $data) ? (int) $data['assigned_by'] : null;
        if ($assignmentsShouldSync) {
            $assignedMemberIds = self::normalize_assigned_member_ids($data);
            foreach ($assignedMemberIds as $memberId) {
                $validation = self::validate_assignable_member($memberId);
                if (is_wp_error($validation)) {
                    return $validation;
                }
            }
        }

        $fields = array();
        $formats = array();

        if (array_key_exists('title', $data)) {
            $title = sanitize_text_field($data['title']);
            if ($title === '') {
                return new WP_Error('mj_member_todo_missing_title', __('Le titre de la tâche est requis.', 'mj-member'));
            }
            $fields['title'] = $title;
            $formats[] = '%s';
        }

        if (array_key_exists('description', $data)) {
            $description = sanitize_textarea_field($data['description']);
            $fields['description'] = $description === '' ? null : $description;
            $formats[] = '%s';
        }

        if (array_key_exists('status', $data)) {
            $status = self::normalize_status($data['status']);
            if ($status === '') {
                $status = self::STATUS_OPEN;
            }
            $fields['status'] = $status;
            $formats[] = '%s';

            if ($status === self::STATUS_COMPLETED) {
                $completedAt = isset($data['completed_at']) ? self::sanitize_datetime($data['completed_at']) : current_time('mysql');
                if ($completedAt instanceof WP_Error) {
                    return $completedAt;
                }
                $completedBy = isset($data['completed_by']) ? (int) $data['completed_by'] : get_current_user_id();
                if ($completedBy < 0) {
                    $completedBy = 0;
                }
                $fields['completed_at'] = $completedAt;
                $formats[] = '%s';
                $fields['completed_by'] = $completedBy > 0 ? $completedBy : null;
                $formats[] = '%s';
            } else {
                $fields['completed_at'] = null;
                $formats[] = '%s';
                $fields['completed_by'] = null;
                $formats[] = '%s';
            }
        }

        if (array_key_exists('project_id', $data)) {
            $projectId = (int) $data['project_id'];
            if ($projectId > 0 && !self::project_exists($projectId)) {
                return new WP_Error('mj_member_todo_invalid_project', __('Dossier introuvable.', 'mj-member'));
            }
            $fields['project_id'] = $projectId > 0 ? $projectId : null;
            $formats[] = '%s';
        }

        $assignedByForSync = 0;
        if ($assignmentsShouldSync) {
            $primaryAssigneeId = !empty($assignedMemberIds) ? (int) $assignedMemberIds[0] : 0;
            $fields['assigned_member_id'] = $primaryAssigneeId > 0 ? $primaryAssigneeId : null;
            $formats[] = '%s';

            $assignedByValue = $assignedByOverride;
            if ($assignedByValue === null) {
                if ($primaryAssigneeId > 0) {
                    $assignedByValue = isset($existing['assigned_by']) ? (int) $existing['assigned_by'] : get_current_user_id();
                } else {
                    $assignedByValue = 0;
                }
            }

            if ($primaryAssigneeId > 0 && $assignedByValue !== null && $assignedByValue <= 0) {
                $assignedByValue = get_current_user_id();
            }

            $assignedByForSync = ($assignedByValue !== null && $assignedByValue > 0) ? (int) $assignedByValue : 0;
            $fields['assigned_by'] = $assignedByForSync > 0 ? $assignedByForSync : null;
            $formats[] = '%s';
        } elseif ($assignedByOverride !== null) {
            $fields['assigned_by'] = $assignedByOverride > 0 ? $assignedByOverride : null;
            $formats[] = '%s';
        }

        if (array_key_exists('due_date', $data)) {
            $dueDate = self::sanitize_due_date($data['due_date']);
            if ($dueDate instanceof WP_Error) {
                return $dueDate;
            }
            $fields['due_date'] = $dueDate;
            $formats[] = '%s';
        }

        if (array_key_exists('position', $data)) {
            $position = (int) $data['position'];
            if ($position <= 0) {
                $position = 3;
            }
            $fields['position'] = max(1, min(5, $position));
            $formats[] = '%d';
        }

        if (array_key_exists('updated_by', $data)) {
            $updatedBy = (int) $data['updated_by'];
            if ($updatedBy > 0) {
                $fields['updated_by'] = $updatedBy;
                $formats[] = '%d';
            }
        }

        if (empty($fields)) {
            return true;
        }

        global $wpdb;
        $table = self::table_name();
        $result = $wpdb->update($table, $fields, array('id' => $id), $formats, array('%d'));
        if ($result === false) {
            return new WP_Error('mj_member_todo_update_failed', __('Impossible de mettre à jour la tâche.', 'mj-member'));
        }

        if ($assignmentsShouldSync) {
            $sync = self::sync_assignments($id, $assignedMemberIds, $assignedByForSync);
            if (is_wp_error($sync)) {
                return $sync;
            }
        }

        return true;
    }

    /**
     * @param int $id
     * @return true|WP_Error
     */
    public static function delete($id)
    {
        $id = (int) $id;
        if ($id <= 0) {
            return new WP_Error('mj_member_todo_invalid_id', __('Identifiant de tâche invalide.', 'mj-member'));
        }

        global $wpdb;
        $table = self::table_name();
        $deleted = $wpdb->delete($table, array('id' => $id), array('%d'));
        if ($deleted === false) {
            return new WP_Error('mj_member_todo_delete_failed', __('Suppression de la tâche impossible.', 'mj-member'));
        }

        self::delete_assignments($id);
        if (class_exists(__NAMESPACE__ . '\\MjTodoMedia')) {
            MjTodoMedia::delete_for_todo($id);
        }

        return true;
    }

    /**
     * @param int $id
     * @return array<string,mixed>|null
     */
    public static function get($id)
    {
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

        $todo = self::format_row($row);
        $withAssignments = self::attach_assignments(array($todo));
        $withNotes = self::attach_notes($withAssignments);
        $withMedia = self::attach_media($withNotes);
        return $withMedia[0] ?? $todo;
    }

    /**
     * @param int $memberId
     * @param array<string,mixed> $args
     * @return array<int,array<string,mixed>>
     */
    public static function get_for_member(int $memberId, array $args = array()): array
    {
        if ($memberId <= 0) {
            return array();
        }

        $args['assigned_member_id'] = $memberId;
        return self::get_all($args);
    }

    /**
     * @return true|WP_Error
     */
    public static function toggle_completion(int $id, int $userId, bool $complete)
    {
        $id = (int) $id;
        if ($id <= 0) {
            return new WP_Error('mj_member_todo_invalid_id', __('Identifiant de tâche invalide.', 'mj-member'));
        }

        $userId = (int) $userId;
        $status = $complete ? self::STATUS_COMPLETED : self::STATUS_OPEN;

        $fields = array(
            'status' => $status,
        );
        $formats = array('%s');

        if ($complete) {
            $fields['completed_at'] = current_time('mysql');
            $formats[] = '%s';
            $fields['completed_by'] = $userId > 0 ? $userId : null;
            $formats[] = '%s';
        } else {
            $fields['completed_at'] = null;
            $formats[] = '%s';
            $fields['completed_by'] = null;
            $formats[] = '%s';
        }

        global $wpdb;
        $table = self::table_name();
        $updated = $wpdb->update($table, $fields, array('id' => $id), $formats, array('%d'));
        if ($updated === false) {
            return new WP_Error('mj_member_todo_update_failed', __('Impossible de mettre à jour la tâche.', 'mj-member'));
        }

        return true;
    }

    public static function detachProject(int $projectId): void
    {
        $projectId = (int) $projectId;
        if ($projectId <= 0) {
            return;
        }

        global $wpdb;
        $table = self::table_name();
        $wpdb->query($wpdb->prepare("UPDATE {$table} SET project_id = NULL WHERE project_id = %d", $projectId));
    }

    /**
     * @param object|array<string,mixed> $row
     * @return array<string,mixed>
     */
    private static function format_row($row): array
    {
        if (is_object($row)) {
            $row = get_object_vars($row);
        }

        $status = self::normalize_status($row['status'] ?? self::STATUS_OPEN);

        return array(
            'id' => isset($row['id']) ? (int) $row['id'] : 0,
            'project_id' => isset($row['project_id']) ? (int) $row['project_id'] : 0,
            'title' => sanitize_text_field($row['title'] ?? ''),
            'description' => isset($row['description']) ? (string) $row['description'] : '',
            'status' => $status,
            'due_date' => isset($row['due_date']) ? (string) $row['due_date'] : '',
            'assigned_member_id' => isset($row['assigned_member_id']) ? (int) $row['assigned_member_id'] : 0,
            'assigned_by' => isset($row['assigned_by']) ? (int) $row['assigned_by'] : 0,
            'created_by' => isset($row['created_by']) ? (int) $row['created_by'] : 0,
            'completed_at' => isset($row['completed_at']) ? (string) $row['completed_at'] : '',
            'completed_by' => isset($row['completed_by']) ? (int) $row['completed_by'] : 0,
            'position' => isset($row['position']) ? (int) $row['position'] : 0,
            'priority' => isset($row['position']) ? (int) $row['position'] : 0,
            'created_at' => isset($row['created_at']) ? (string) $row['created_at'] : '',
            'updated_at' => isset($row['updated_at']) ? (string) $row['updated_at'] : '',
            'assignees' => array(),
            'notes' => array(),
            'media' => array(),
        );
    }

    /**
     * @param mixed $status
     */
    private static function normalize_status($status): string
    {
        $status = sanitize_key((string) $status);
        if (in_array($status, self::statuses(), true)) {
            return $status;
        }
        return '';
    }

    /**
     * @param mixed $value
     * @return string|null|WP_Error
     */
    public static function sanitize_due_date($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        $candidate = sanitize_text_field((string) $value);
        if ($candidate === '') {
            return null;
        }

        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $candidate, $matches)) {
            return new WP_Error('mj_member_todo_invalid_due_date', __('Date d’échéance invalide.', 'mj-member'));
        }

        $year = (int) $matches[1];
        $month = (int) $matches[2];
        $day = (int) $matches[3];
        if (!checkdate($month, $day, $year)) {
            return new WP_Error('mj_member_todo_invalid_due_date', __('Date d’échéance invalide.', 'mj-member'));
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    /**
     * @param mixed $value
     * @return string|null|WP_Error
     */
    private static function sanitize_datetime($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        $candidate = sanitize_text_field((string) $value);
        if ($candidate === '') {
            return null;
        }

        $timestamp = strtotime($candidate);
        if ($timestamp === false) {
            return new WP_Error('mj_member_todo_invalid_datetime', __('Date invalide.', 'mj-member'));
        }

        return wp_date('Y-m-d H:i:s', $timestamp);
    }

    /**
     * @param int $projectId
     */
    private static function project_exists(int $projectId): bool
    {
        if (!class_exists(__NAMESPACE__ . '\\MjTodoProjects')) {
            return false;
        }

        $project = MjTodoProjects::get($projectId);
        return is_array($project) && !empty($project);
    }

    /**
     * @return true|WP_Error
     */
    private static function validate_assignable_member(int $memberId)
    {
        if (!class_exists(__NAMESPACE__ . '\\MjMembers')) {
            return new WP_Error('mj_member_todo_invalid_member', __('Impossible de vérifier le membre assigné.', 'mj-member'));
        }

        $member = MjMembers::getById($memberId);
        if (!($member instanceof MemberData)) {
            return new WP_Error('mj_member_todo_invalid_member', __('Membre assigné introuvable.', 'mj-member'));
        }

        $role = (string) $member->get('role', '');
        if (!in_array($role, self::ASSIGNABLE_ROLES, true)) {
            return new WP_Error('mj_member_todo_unauthorized_member', __('Ce membre ne peut pas recevoir de tâches.', 'mj-member'));
        }

        return true;
    }

    /**
     * @param array<string,mixed> $data
     * @return array<int,int>
     */
    private static function normalize_assigned_member_ids(array $data): array
    {
        $raw = array();
        if (array_key_exists('assigned_member_ids', $data)) {
            $raw = $data['assigned_member_ids'];
        } elseif (array_key_exists('assigned_member_id', $data)) {
            $raw = $data['assigned_member_id'];
        }

        if (!is_array($raw)) {
            $raw = $raw === null ? array() : array($raw);
        }

        $ids = array();
        foreach ($raw as $value) {
            $candidate = (int) $value;
            if ($candidate > 0) {
                $ids[$candidate] = $candidate;
            }
        }

        return array_values($ids);
    }

    /**
     * @param int $todoId
     * @param array<int,int> $memberIds
     * @return true|WP_Error
     */
    private static function sync_assignments(int $todoId, array $memberIds, int $assignedBy)
    {
        $todoId = (int) $todoId;
        if ($todoId <= 0) {
            return new WP_Error('mj_member_todo_invalid_id', __('Identifiant de tâche invalide.', 'mj-member'));
        }

        $assignmentsTable = self::assignments_table_name();
        if (function_exists('mj_member_table_exists') && !mj_member_table_exists($assignmentsTable)) {
            return true;
        }

        global $wpdb;

        $memberIds = array_values(array_filter(array_unique(array_map('intval', $memberIds)), static function ($value) {
            return $value > 0;
        }));

        $existingRows = $wpdb->get_col($wpdb->prepare(
            "SELECT member_id FROM {$assignmentsTable} WHERE todo_id = %d",
            $todoId
        ));
        $existing = array();
        if (is_array($existingRows)) {
            foreach ($existingRows as $row) {
                $existing[(int) $row] = (int) $row;
            }
        }

        $toRemove = array_diff($existing, $memberIds);
        if (!empty($toRemove)) {
            $idsSql = implode(',', array_map('intval', $toRemove));
            $wpdb->query("DELETE FROM {$assignmentsTable} WHERE todo_id = {$todoId} AND member_id IN ({$idsSql})");
        }

        $assignedByValue = $assignedBy > 0 ? $assignedBy : 0;
        $timestamp = current_time('mysql');
        foreach ($memberIds as $memberId) {
            $wpdb->query($wpdb->prepare(
                "INSERT INTO {$assignmentsTable} (todo_id, member_id, assigned_by, assigned_at)
                 VALUES (%d, %d, %d, %s)
                 ON DUPLICATE KEY UPDATE assigned_by = VALUES(assigned_by), assigned_at = VALUES(assigned_at)",
                $todoId,
                $memberId,
                $assignedByValue,
                $timestamp
            ));
        }

        if (!empty($wpdb->last_error)) {
            return new WP_Error('mj_member_todo_assignment_sync_failed', __('Impossible de synchroniser les assignations.', 'mj-member'));
        }

        return true;
    }

    private static function delete_assignments(int $todoId): void
    {
        $todoId = (int) $todoId;
        if ($todoId <= 0) {
            return;
        }

        $assignmentsTable = self::assignments_table_name();
        if (function_exists('mj_member_table_exists') && !mj_member_table_exists($assignmentsTable)) {
            return;
        }

        global $wpdb;
        $wpdb->query($wpdb->prepare("DELETE FROM {$assignmentsTable} WHERE todo_id = %d", $todoId));
    }
}
