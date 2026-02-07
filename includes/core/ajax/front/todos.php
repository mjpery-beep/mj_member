<?php

use Mj\Member\Classes\Crud\MjTodoMedia;
use Mj\Member\Classes\Crud\MjTodoNotes;
use Mj\Member\Classes\Crud\MjTodoProjects;
use Mj\Member\Classes\Crud\MjTodos;
use Mj\Member\Classes\Value\MemberData;

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('mj_member_ajax_todos_fetch_my')) {
    function mj_member_ajax_todos_fetch_my(): void
    {
        check_ajax_referer('mj_member_todo_widget', 'nonce');

        $member = mj_member_todo_resolve_member();
        if (!($member instanceof MemberData)) {
            wp_send_json_error(array('message' => __('Accès refusé.', 'mj-member')), 403);
        }

        $memberId = (int) $member->get('id', 0);
        $includeCompleted = isset($_POST['include_completed']) ? (int) $_POST['include_completed'] === 1 : true;
        $statuses = $includeCompleted ? array(MjTodos::STATUS_OPEN, MjTodos::STATUS_COMPLETED) : array(MjTodos::STATUS_OPEN);

        $todos = MjTodos::get_for_member($memberId, array(
            'statuses' => $statuses,
            'orderby' => 'position',
            'order' => 'ASC',
        ));

        $projects = MjTodoProjects::get_all(array(
            'orderby' => 'title',
            'order' => 'ASC',
        ));

        $projectMap = array();
        foreach ($projects as $project) {
            $projectId = isset($project['id']) ? (int) $project['id'] : 0;
            if ($projectId > 0) {
                $projectMap[$projectId] = isset($project['title']) ? sanitize_text_field((string) $project['title']) : '';
            }
        }

        $payloadTodos = array_map(static function ($todo) use ($projectMap, $memberId) {
            return mj_member_todo_prepare_payload($todo, $projectMap, $memberId);
        }, $todos);

        $projectIdsWithTodos = array();
        foreach ($payloadTodos as $payloadTodo) {
            if (!is_array($payloadTodo)) {
                continue;
            }
            $projectId = isset($payloadTodo['projectId']) ? (int) $payloadTodo['projectId'] : 0;
            if ($projectId > 0) {
                $projectIdsWithTodos[$projectId] = true;
            }
        }

        $payloadProjects = array();
        if (!empty($projectIdsWithTodos)) {
            foreach ($projects as $project) {
                if (!is_array($project)) {
                    continue;
                }
                $projectId = isset($project['id']) ? (int) $project['id'] : 0;
                if ($projectId <= 0 || !isset($projectIdsWithTodos[$projectId])) {
                    continue;
                }

                $title = isset($project['title']) ? sanitize_text_field((string) $project['title']) : '';
                $color = isset($project['color']) ? sanitize_hex_color((string) $project['color']) : '';

                $payloadProjects[] = array(
                    'id' => $projectId,
                    'title' => $title,
                    'color' => is_string($color) ? $color : '',
                );
            }
        }

        $assignableMembers = mj_member_todo_fetch_assignable_members($memberId);

        $memberName = trim(sprintf('%s %s', (string) $member->get('first_name', ''), (string) $member->get('last_name', '')));
        $memberName = $memberName !== '' ? sanitize_text_field($memberName) : '';

        wp_send_json_success(array(
            'todos' => $payloadTodos,
            'projects' => $payloadProjects,
            'assignableMembers' => $assignableMembers,
            'member' => array(
                'id' => $memberId,
                'name' => $memberName,
                'role' => sanitize_key((string) $member->get('role', '')),
            ),
        ));
    }
}


if (!function_exists('mj_member_ajax_todos_toggle')) {
    function mj_member_ajax_todos_toggle(): void
    {
        check_ajax_referer('mj_member_todo_widget', 'nonce');

        $member = mj_member_todo_resolve_member();
        if (!($member instanceof MemberData)) {
            wp_send_json_error(array('message' => __('Accès refusé.', 'mj-member')), 403);
        }

        $todoId = isset($_POST['todo_id']) ? (int) $_POST['todo_id'] : 0;
        $complete = isset($_POST['complete']) ? (int) $_POST['complete'] === 1 : false;

        if ($todoId <= 0) {
            wp_send_json_error(array('message' => __('Tâche invalide.', 'mj-member')));
        }

        $todo = MjTodos::get($todoId);
        if (!$todo || !mj_member_todo_member_is_assignee($todo, $member)) {
            wp_send_json_error(array('message' => __('Vous ne pouvez pas modifier cette tâche.', 'mj-member')), 403);
        }

        $result = MjTodos::toggle_completion($todoId, get_current_user_id(), $complete);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        // Notifier les autres membres assignés si la tâche est terminée
        if ($complete) {
            $memberId = (int) $member->get('id', 0);
            $todoTitle = isset($todo['title']) ? (string) $todo['title'] : '';
            $assigneeIds = isset($todo['assigned_member_ids']) ? (array) $todo['assigned_member_ids'] : array();
            if (empty($assigneeIds) && isset($todo['assigned_member_id']) && (int) $todo['assigned_member_id'] > 0) {
                $assigneeIds = array((int) $todo['assigned_member_id']);
            }
            do_action('mj_member_todo_completed', $todoId, $memberId, $todoTitle, $assigneeIds);
        }

        $updated = MjTodos::get($todoId);
        if (!$updated) {
            wp_send_json_error(array('message' => __('Tâche introuvable après mise à jour.', 'mj-member')));
        }

        $projectMap = array();
        $projectId = isset($updated['project_id']) ? (int) $updated['project_id'] : 0;
        if ($projectId > 0) {
            $project = MjTodoProjects::get($projectId);
            if (is_array($project) && isset($project['title'])) {
                $projectMap[$projectId] = sanitize_text_field((string) $project['title']);
            }
        }

        $memberId = (int) $member->get('id', 0);
        $payload = mj_member_todo_prepare_payload($updated, $projectMap, $memberId);

        wp_send_json_success(array('todo' => $payload));
    }
}

if (!function_exists('mj_member_ajax_todos_create')) {
    function mj_member_ajax_todos_create(): void
    {
        check_ajax_referer('mj_member_todo_widget', 'nonce');

        $member = mj_member_todo_resolve_member();
        if (!($member instanceof MemberData)) {
            wp_send_json_error(array('message' => __('Accès refusé.', 'mj-member')), 403);
        }

        $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash((string) $_POST['title'])) : '';
        $projectId = isset($_POST['project_id']) ? (int) $_POST['project_id'] : 0;
        $dueDateRaw = isset($_POST['due_date']) ? (string) $_POST['due_date'] : '';
        $descriptionRaw = isset($_POST['description']) ? wp_unslash((string) $_POST['description']) : '';
        $description = sanitize_textarea_field($descriptionRaw);
        $emojiRaw = isset($_POST['emoji']) ? wp_unslash((string) $_POST['emoji']) : '';
        $emoji = MjTodos::sanitize_emoji_value($emojiRaw);
        $priorityRaw = isset($_POST['priority']) ? (int) $_POST['priority'] : 3;
        if ($priorityRaw <= 0) {
            $priorityRaw = 3;
        }
        $priority = max(1, min(5, $priorityRaw));

        if ($title === '') {
            wp_send_json_error(array('message' => __('Merci de saisir un titre.', 'mj-member')));
        }

        $dueDate = MjTodos::sanitize_due_date($dueDateRaw);
        if ($dueDate instanceof WP_Error) {
            wp_send_json_error(array('message' => $dueDate->get_error_message()));
        }

        if ($projectId > 0 && !MjTodoProjects::get($projectId)) {
            wp_send_json_error(array('message' => __('Dossier introuvable.', 'mj-member')));
        }

        $assignedMemberIdsRaw = isset($_POST['assigned_member_ids']) ? wp_unslash($_POST['assigned_member_ids']) : array();
        if (!is_array($assignedMemberIdsRaw)) {
            $assignedMemberIdsRaw = $assignedMemberIdsRaw === null ? array() : array($assignedMemberIdsRaw);
        }

        $assignedMemberIds = array();
        foreach ($assignedMemberIdsRaw as $value) {
            $candidate = (int) $value;
            if ($candidate > 0) {
                $assignedMemberIds[$candidate] = $candidate;
            }
        }

        $currentMemberId = (int) $member->get('id', 0);
        if (empty($assignedMemberIds) && $currentMemberId > 0) {
            $assignedMemberIds[$currentMemberId] = $currentMemberId;
        }

        $assignedMemberIds = array_values($assignedMemberIds);

        $attachmentIdsRaw = isset($_POST['attachment_ids']) ? wp_unslash($_POST['attachment_ids']) : array();
        if (!is_array($attachmentIdsRaw)) {
            $attachmentIdsRaw = $attachmentIdsRaw === null ? array() : array($attachmentIdsRaw);
        }

        $attachmentIds = array();
        foreach ($attachmentIdsRaw as $value) {
            $candidateId = (int) $value;
            if ($candidateId <= 0) {
                continue;
            }

            $attachment = get_post($candidateId);
            if ($attachment && $attachment instanceof WP_Post && $attachment->post_type === 'attachment') {
                $attachmentIds[$candidateId] = $candidateId;
            }
        }

        $data = array(
            'title' => $title,
            'project_id' => $projectId,
            'due_date' => $dueDate,
            'assigned_member_id' => !empty($assignedMemberIds) ? (int) $assignedMemberIds[0] : $currentMemberId,
            'assigned_member_ids' => $assignedMemberIds,
            'assigned_by' => get_current_user_id(),
            'created_by' => get_current_user_id(),
            'status' => MjTodos::STATUS_OPEN,
            'position' => $priority,
            'emoji' => $emoji,
        );

        if ($description !== '') {
            $data['description'] = $description;
        }

        $created = MjTodos::create($data);
        if (is_wp_error($created)) {
            wp_send_json_error(array('message' => $created->get_error_message()));
        }

        $todoId = (int) $created;

        $attachmentIdList = array_values($attachmentIds);
        if (!empty($attachmentIdList) && class_exists(MjTodoMedia::class)) {
            $memberIdForMedia = (int) $member->get('id', 0);
            $mediaResult = MjTodoMedia::attach_multiple($todoId, $attachmentIdList, $memberIdForMedia, get_current_user_id());
            if (is_wp_error($mediaResult)) {
                wp_send_json_error(array('message' => $mediaResult->get_error_message()));
            }
        }

        $todo = MjTodos::get($todoId);
        if (!$todo) {
            wp_send_json_error(array('message' => __('Impossible de récupérer la tâche créée.', 'mj-member')));
        }

        // Notifier les membres assignés
        if (!empty($assignedMemberIds)) {
            do_action('mj_member_todo_assigned', $todoId, $assignedMemberIds, $title, get_current_user_id());
        }

        $projectMap = array();
        if ($projectId > 0) {
            $project = MjTodoProjects::get($projectId);
            if (is_array($project) && isset($project['title'])) {
                $projectMap[$projectId] = sanitize_text_field((string) $project['title']);
            }
        }

        $payload = mj_member_todo_prepare_payload($todo, $projectMap, $currentMemberId);

        wp_send_json_success(array('todo' => $payload));
    }
}

if (!function_exists('mj_member_ajax_todo_project_create_front')) {
    function mj_member_ajax_todo_project_create_front(): void
    {
        check_ajax_referer('mj_member_todo_widget', 'nonce');

        $member = mj_member_todo_resolve_member();
        if (!($member instanceof MemberData)) {
            wp_send_json_error(array('message' => __('Accès refusé.', 'mj-member')), 403);
        }

        $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash((string) $_POST['title'])) : '';
        $description = isset($_POST['description']) ? sanitize_textarea_field(wp_unslash((string) $_POST['description'])) : '';
        $color = isset($_POST['color']) ? sanitize_hex_color((string) $_POST['color']) : '';

        if ($title === '') {
            wp_send_json_error(array('message' => __('Merci de saisir un titre.', 'mj-member')));
        }

        $payload = array(
            'title' => $title,
            'created_by' => get_current_user_id(),
        );

        if ($description !== '') {
            $payload['description'] = $description;
        }

        if ($color !== '' && $color !== null) {
            $payload['color'] = $color;
        }

        $created = MjTodoProjects::create($payload);
        if (is_wp_error($created)) {
            wp_send_json_error(array('message' => $created->get_error_message()));
        }

        $project = MjTodoProjects::get((int) $created);
        if (!is_array($project)) {
            wp_send_json_error(array('message' => __('Impossible de récupérer le dossier créé.', 'mj-member')));
        }

        $projectColor = isset($project['color']) ? sanitize_hex_color((string) $project['color']) : '';
        $responseProject = array(
            'id' => isset($project['id']) ? (int) $project['id'] : 0,
            'title' => isset($project['title']) ? sanitize_text_field((string) $project['title']) : '',
            'color' => $projectColor !== null ? $projectColor : '',
        );

        wp_send_json_success(array('project' => $responseProject));
    }
}

if (!function_exists('mj_member_ajax_todo_update_front')) {
    function mj_member_ajax_todo_update_front(): void
    {
        check_ajax_referer('mj_member_todo_widget', 'nonce');

        $member = mj_member_todo_resolve_member();
        if (!($member instanceof MemberData)) {
            wp_send_json_error(array('message' => __('Accès refusé.', 'mj-member')), 403);
        }

        $todoId = isset($_POST['todo_id']) ? (int) $_POST['todo_id'] : 0;
        if ($todoId <= 0) {
            wp_send_json_error(array('message' => __('Tâche invalide.', 'mj-member')));
        }

        $todo = MjTodos::get($todoId);
        if (!$todo || !mj_member_todo_member_is_assignee($todo, $member)) {
            wp_send_json_error(array('message' => __('Vous ne pouvez pas modifier cette tâche.', 'mj-member')), 403);
        }

        $payload = array();
        $hasField = false;

        if (array_key_exists('title', $_POST)) {
            $rawTitle = wp_unslash((string) $_POST['title']);
            $title = sanitize_text_field($rawTitle);
            if ($title === '') {
                wp_send_json_error(array('message' => __('Merci de saisir un titre.', 'mj-member')));
            }
            $payload['title'] = $title;
            $hasField = true;
        }

        if (array_key_exists('description', $_POST)) {
            $rawDescription = wp_unslash((string) $_POST['description']);
            $payload['description'] = sanitize_textarea_field($rawDescription);
            $hasField = true;
        }

        if (array_key_exists('priority', $_POST)) {
            $priorityValue = (int) $_POST['priority'];
            if ($priorityValue <= 0) {
                $priorityValue = 3;
            }
            $payload['position'] = max(1, min(5, $priorityValue));
            $hasField = true;
        }

        if (array_key_exists('emoji', $_POST)) {
            $rawEmoji = wp_unslash((string) $_POST['emoji']);
            $payload['emoji'] = MjTodos::sanitize_emoji_value($rawEmoji);
            $hasField = true;
        }

        if (!$hasField) {
            wp_send_json_error(array('message' => __('Aucun champ à mettre à jour.', 'mj-member')));
        }

        $result = MjTodos::update($todoId, $payload);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        $updated = MjTodos::get($todoId);
        if (!$updated) {
            wp_send_json_error(array('message' => __('Tâche introuvable après mise à jour.', 'mj-member')));
        }

        $projectMap = array();
        $projectId = isset($updated['project_id']) ? (int) $updated['project_id'] : 0;
        if ($projectId > 0) {
            $project = MjTodoProjects::get($projectId);
            if (is_array($project) && isset($project['title'])) {
                $projectMap[$projectId] = sanitize_text_field((string) $project['title']);
            }
        }

        $memberId = (int) $member->get('id', 0);
        $payloadTodo = mj_member_todo_prepare_payload($updated, $projectMap, $memberId);

        wp_send_json_success(array('todo' => $payloadTodo));
    }
}

if (!function_exists('mj_member_ajax_todo_note_create')) {
    function mj_member_ajax_todo_note_create(): void
    {
        check_ajax_referer('mj_member_todo_widget', 'nonce');

        $member = mj_member_todo_resolve_member();
        if (!($member instanceof MemberData)) {
            wp_send_json_error(array('message' => __('Accès refusé.', 'mj-member')), 403);
        }

        $todoId = isset($_POST['todo_id']) ? (int) $_POST['todo_id'] : 0;
        $contentRaw = isset($_POST['content']) ? wp_unslash((string) $_POST['content']) : '';
        $content = sanitize_textarea_field($contentRaw);

        if ($todoId <= 0) {
            wp_send_json_error(array('message' => __('Tâche invalide.', 'mj-member')));
        }

        if ($content === '') {
            wp_send_json_error(array('message' => __('Merci de saisir une note.', 'mj-member')));
        }

        $todo = MjTodos::get($todoId);
        if (!$todo || !mj_member_todo_member_is_assignee($todo, $member)) {
            wp_send_json_error(array('message' => __('Vous ne pouvez pas modifier cette tâche.', 'mj-member')), 403);
        }

        $memberId = (int) $member->get('id', 0);
        if ($memberId <= 0) {
            wp_send_json_error(array('message' => __('Auteur de la note invalide.', 'mj-member')));
        }

        $created = MjTodoNotes::create(array(
            'todo_id' => $todoId,
            'member_id' => $memberId,
            'wp_user_id' => get_current_user_id(),
            'content' => $content,
        ));

        if (is_wp_error($created)) {
            wp_send_json_error(array('message' => $created->get_error_message()));
        }

        $note = MjTodoNotes::get((int) $created);
        if (!is_array($note)) {
            wp_send_json_error(array('message' => __('Impossible de récupérer la note créée.', 'mj-member')));
        }

        // Notifier les autres membres assignés
        $todoTitle = isset($todo['title']) ? (string) $todo['title'] : '';
        $assigneeIds = isset($todo['assigned_member_ids']) ? (array) $todo['assigned_member_ids'] : array();
        if (empty($assigneeIds) && isset($todo['assigned_member_id']) && (int) $todo['assigned_member_id'] > 0) {
            $assigneeIds = array((int) $todo['assigned_member_id']);
        }
        do_action('mj_member_todo_note_added', $todoId, (int) $created, $memberId, $todoTitle, $assigneeIds);

        $payload = mj_member_todo_prepare_note_payload($note);

        wp_send_json_success(array(
            'note' => $payload,
            'todoId' => $todoId,
        ));
    }
}

if (!function_exists('mj_member_ajax_todo_note_delete')) {
    function mj_member_ajax_todo_note_delete(): void
    {
        check_ajax_referer('mj_member_todo_widget', 'nonce');

        $member = mj_member_todo_resolve_member();
        if (!($member instanceof MemberData)) {
            wp_send_json_error(array('message' => __('Accès refusé.', 'mj-member')), 403);
        }

        $todoId = isset($_POST['todo_id']) ? (int) $_POST['todo_id'] : 0;
        $noteId = isset($_POST['note_id']) ? (int) $_POST['note_id'] : 0;

        if ($todoId <= 0 || $noteId <= 0) {
            wp_send_json_error(array('message' => __('Requête invalide.', 'mj-member')));
        }

        $todo = MjTodos::get($todoId);
        if (!$todo || !mj_member_todo_member_is_assignee($todo, $member)) {
            wp_send_json_error(array('message' => __('Vous ne pouvez pas modifier cette tâche.', 'mj-member')), 403);
        }

        $note = MjTodoNotes::get($noteId);
        if (!is_array($note) || (int) ($note['todo_id'] ?? 0) !== $todoId) {
            wp_send_json_error(array('message' => __('Note introuvable.', 'mj-member')));
        }

        $memberId = (int) $member->get('id', 0);
        if ($memberId <= 0 || (int) ($note['member_id'] ?? 0) !== $memberId) {
            wp_send_json_error(array('message' => __('Vous ne pouvez pas supprimer cette note.', 'mj-member')), 403);
        }

        $deleted = MjTodoNotes::delete($noteId);
        if (is_wp_error($deleted)) {
            wp_send_json_error(array('message' => $deleted->get_error_message()));
        }

        wp_send_json_success(array(
            'todoId' => $todoId,
            'noteId' => $noteId,
        ));
    }
}

if (!function_exists('mj_member_ajax_todo_archive')) {
    function mj_member_ajax_todo_archive(): void
    {
        check_ajax_referer('mj_member_todo_widget', 'nonce');

        $member = mj_member_todo_resolve_member();
        if (!($member instanceof MemberData)) {
            wp_send_json_error(array('message' => __('Accès refusé.', 'mj-member')), 403);
        }

        $todoId = isset($_POST['todo_id']) ? (int) $_POST['todo_id'] : 0;
        if ($todoId <= 0) {
            wp_send_json_error(array('message' => __('Tâche invalide.', 'mj-member')));
        }

        $todo = MjTodos::get($todoId);
        if (!$todo || !mj_member_todo_member_is_assignee($todo, $member)) {
            wp_send_json_error(array('message' => __('Vous ne pouvez pas modifier cette tâche.', 'mj-member')), 403);
        }

        $result = MjTodos::update($todoId, array('status' => MjTodos::STATUS_ARCHIVED));
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        $updated = MjTodos::get($todoId);
        if (!$updated) {
            wp_send_json_error(array('message' => __('Tâche introuvable après mise à jour.', 'mj-member')));
        }

        $projectMap = array();
        $projectId = isset($updated['project_id']) ? (int) $updated['project_id'] : 0;
        if ($projectId > 0) {
            $project = MjTodoProjects::get($projectId);
            if (is_array($project) && isset($project['title'])) {
                $projectMap[$projectId] = sanitize_text_field((string) $project['title']);
            }
        }

        $memberId = (int) $member->get('id', 0);
        $payload = mj_member_todo_prepare_payload($updated, $projectMap, $memberId);

        wp_send_json_success(array('todo' => $payload));
    }
}

if (!function_exists('mj_member_ajax_todo_unarchive')) {
    function mj_member_ajax_todo_unarchive(): void
    {
        check_ajax_referer('mj_member_todo_widget', 'nonce');

        $member = mj_member_todo_resolve_member();
        if (!($member instanceof MemberData)) {
            wp_send_json_error(array('message' => __('Accès refusé.', 'mj-member')), 403);
        }

        $todoId = isset($_POST['todo_id']) ? (int) $_POST['todo_id'] : 0;
        if ($todoId <= 0) {
            wp_send_json_error(array('message' => __('Tâche invalide.', 'mj-member')));
        }

        $todo = MjTodos::get($todoId);
        if (!$todo || !mj_member_todo_member_is_assignee($todo, $member)) {
            wp_send_json_error(array('message' => __('Vous ne pouvez pas modifier cette tâche.', 'mj-member')), 403);
        }

        $updatePayload = array(
            'status' => MjTodos::STATUS_OPEN,
        );

        $todosTable = function_exists('mj_member_get_todos_table_name')
            ? mj_member_get_todos_table_name()
            : '';

        if ($todosTable === '' && class_exists(MjTodos::class) && method_exists(MjTodos::class, 'getTableName')) {
            $todosTable = MjTodos::getTableName('mj_todos');
        }

        $supportsUpdatedBy = $todosTable !== ''
            && function_exists('mj_member_column_exists')
            && mj_member_column_exists($todosTable, 'updated_by');

        if ($supportsUpdatedBy) {
            $updatePayload['updated_by'] = get_current_user_id();
        }

        $result = MjTodos::update($todoId, $updatePayload);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        $updated = MjTodos::get($todoId);
        if (!$updated) {
            wp_send_json_error(array('message' => __('Tâche introuvable après mise à jour.', 'mj-member')));
        }

        $projectMap = array();
        $projectId = isset($updated['project_id']) ? (int) $updated['project_id'] : 0;
        if ($projectId > 0) {
            $project = MjTodoProjects::get($projectId);
            if (is_array($project) && isset($project['title'])) {
                $projectMap[$projectId] = sanitize_text_field((string) $project['title']);
            }
        }

        $memberId = (int) $member->get('id', 0);
        $payload = mj_member_todo_prepare_payload($updated, $projectMap, $memberId);

        wp_send_json_success(array('todo' => $payload));
    }
}

if (!function_exists('mj_member_ajax_todos_fetch_archived')) {
    function mj_member_ajax_todos_fetch_archived(): void
    {
        check_ajax_referer('mj_member_todo_widget', 'nonce');

        $member = mj_member_todo_resolve_member();
        if (!($member instanceof MemberData)) {
            wp_send_json_error(array('message' => __('Accès refusé.', 'mj-member')), 403);
        }

        $memberId = (int) $member->get('id', 0);
        if ($memberId <= 0) {
            wp_send_json_error(array('message' => __('Membre invalide.', 'mj-member')));
        }

        $todos = MjTodos::get_for_member($memberId, array(
            'statuses' => array(MjTodos::STATUS_ARCHIVED),
            'orderby' => 'updated_at',
            'order' => 'DESC',
        ));

        $projects = MjTodoProjects::get_all(array(
            'orderby' => 'title',
            'order' => 'ASC',
        ));

        $projectMap = array();
        foreach ($projects as $project) {
            $projectId = isset($project['id']) ? (int) $project['id'] : 0;
            if ($projectId > 0) {
                $projectMap[$projectId] = isset($project['title'])
                    ? sanitize_text_field((string) $project['title'])
                    : '';
            }
        }

        $payloadTodos = array_map(static function ($todo) use ($projectMap, $memberId) {
            return mj_member_todo_prepare_payload($todo, $projectMap, $memberId);
        }, $todos);

        $projectIdsWithTodos = array();
        foreach ($payloadTodos as $payloadTodo) {
            if (!is_array($payloadTodo)) {
                continue;
            }
            $projectId = isset($payloadTodo['projectId']) ? (int) $payloadTodo['projectId'] : 0;
            if ($projectId > 0) {
                $projectIdsWithTodos[$projectId] = true;
            }
        }

        $payloadProjects = array();
        if (!empty($projectIdsWithTodos)) {
            foreach ($projects as $project) {
                if (!is_array($project)) {
                    continue;
                }
                $projectId = isset($project['id']) ? (int) $project['id'] : 0;
                if ($projectId <= 0 || !isset($projectIdsWithTodos[$projectId])) {
                    continue;
                }

                $title = isset($project['title']) ? sanitize_text_field((string) $project['title']) : '';
                $color = isset($project['color']) ? sanitize_hex_color((string) $project['color']) : '';

                $payloadProjects[] = array(
                    'id' => $projectId,
                    'title' => $title,
                    'color' => is_string($color) ? $color : '',
                );
            }
        }

        wp_send_json_success(array(
            'todos' => $payloadTodos,
            'projects' => $payloadProjects,
        ));
    }
}

if (!function_exists('mj_member_ajax_todo_media_attach')) {
    function mj_member_ajax_todo_media_attach(): void
    {
        check_ajax_referer('mj_member_todo_widget', 'nonce');

        $member = mj_member_todo_resolve_member();
        if (!($member instanceof MemberData)) {
            wp_send_json_error(array('message' => __('Accès refusé.', 'mj-member')), 403);
        }

        $todoId = isset($_POST['todo_id']) ? (int) $_POST['todo_id'] : 0;
        if ($todoId <= 0) {
            wp_send_json_error(array('message' => __('Tâche invalide.', 'mj-member')));
        }

        $todo = MjTodos::get($todoId);
        if (!$todo || !mj_member_todo_member_is_assignee($todo, $member)) {
            wp_send_json_error(array('message' => __('Vous ne pouvez pas modifier cette tâche.', 'mj-member')), 403);
        }

        if (!class_exists(MjTodoMedia::class)) {
            wp_send_json_error(array('message' => __('Gestion de médias indisponible.', 'mj-member')));
        }

        $rawAttachmentIds = array();
        if (isset($_POST['attachment_ids'])) {
            $rawAttachmentIds = wp_unslash($_POST['attachment_ids']);
        } elseif (isset($_POST['attachment_id'])) {
            $rawAttachmentIds = array(wp_unslash((string) $_POST['attachment_id']));
        }

        if (!is_array($rawAttachmentIds)) {
            $rawAttachmentIds = array($rawAttachmentIds);
        }

        $attachmentIds = array();
        foreach ($rawAttachmentIds as $candidate) {
            $candidateId = (int) $candidate;
            if ($candidateId <= 0) {
                continue;
            }

            $attachment = get_post($candidateId);
            if ($attachment && $attachment instanceof WP_Post && $attachment->post_type === 'attachment') {
                $attachmentIds[$candidateId] = $candidateId;
            }
        }

        if (empty($attachmentIds)) {
            wp_send_json_error(array('message' => __('Aucun média valide sélectionné.', 'mj-member')));
        }

        $memberId = (int) $member->get('id', 0);
        $userId = get_current_user_id();

        $result = MjTodoMedia::attach_multiple($todoId, array_values($attachmentIds), $memberId, $userId);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        // Notifier les autres membres assignés
        $todoTitle = isset($todo['title']) ? (string) $todo['title'] : '';
        $assigneeIds = isset($todo['assigned_member_ids']) ? (array) $todo['assigned_member_ids'] : array();
        if (empty($assigneeIds) && isset($todo['assigned_member_id']) && (int) $todo['assigned_member_id'] > 0) {
            $assigneeIds = array((int) $todo['assigned_member_id']);
        }
        do_action('mj_member_todo_media_added', $todoId, $memberId, $todoTitle, $assigneeIds, count($attachmentIds));

        $updated = MjTodos::get($todoId);
        if (!$updated) {
            wp_send_json_error(array('message' => __('Tâche introuvable après mise à jour.', 'mj-member')));
        }

        $projectMap = array();
        $projectId = isset($updated['project_id']) ? (int) $updated['project_id'] : 0;
        if ($projectId > 0) {
            $project = MjTodoProjects::get($projectId);
            if (is_array($project) && isset($project['title'])) {
                $projectMap[$projectId] = sanitize_text_field((string) $project['title']);
            }
        }

        $payloadTodo = mj_member_todo_prepare_payload($updated, $projectMap, $memberId);

        wp_send_json_success(array('todo' => $payloadTodo));
    }
}

if (!function_exists('mj_member_ajax_todo_media_detach')) {
    function mj_member_ajax_todo_media_detach(): void
    {
        check_ajax_referer('mj_member_todo_widget', 'nonce');

        $member = mj_member_todo_resolve_member();
        if (!($member instanceof MemberData)) {
            wp_send_json_error(array('message' => __('Accès refusé.', 'mj-member')), 403);
        }

        $todoId = isset($_POST['todo_id']) ? (int) $_POST['todo_id'] : 0;
        $attachmentId = isset($_POST['attachment_id']) ? (int) $_POST['attachment_id'] : 0;

        if ($todoId <= 0 || $attachmentId <= 0) {
            wp_send_json_error(array('message' => __('Média invalide.', 'mj-member')));
        }

        $todo = MjTodos::get($todoId);
        if (!$todo || !mj_member_todo_member_is_assignee($todo, $member)) {
            wp_send_json_error(array('message' => __('Vous ne pouvez pas modifier cette tâche.', 'mj-member')), 403);
        }

        if (!class_exists(MjTodoMedia::class)) {
            wp_send_json_error(array('message' => __('Gestion de médias indisponible.', 'mj-member')));
        }

        $deleted = MjTodoMedia::detach($todoId, $attachmentId);
        if (is_wp_error($deleted)) {
            wp_send_json_error(array('message' => $deleted->get_error_message()));
        }

        $updated = MjTodos::get($todoId);
        if (!$updated) {
            wp_send_json_error(array('message' => __('Tâche introuvable après mise à jour.', 'mj-member')));
        }

        $projectMap = array();
        $projectId = isset($updated['project_id']) ? (int) $updated['project_id'] : 0;
        if ($projectId > 0) {
            $project = MjTodoProjects::get($projectId);
            if (is_array($project) && isset($project['title'])) {
                $projectMap[$projectId] = sanitize_text_field((string) $project['title']);
            }
        }

        $memberId = (int) $member->get('id', 0);
        $payloadTodo = mj_member_todo_prepare_payload($updated, $projectMap, $memberId);

        wp_send_json_success(array('todo' => $payloadTodo));
    }
}

if (!function_exists('mj_member_ajax_todo_delete_front')) {
    function mj_member_ajax_todo_delete_front(): void
    {
        check_ajax_referer('mj_member_todo_widget', 'nonce');

        $member = mj_member_todo_resolve_member();
        if (!($member instanceof MemberData)) {
            wp_send_json_error(array('message' => __('Accès refusé.', 'mj-member')), 403);
        }

        $todoId = isset($_POST['todo_id']) ? (int) $_POST['todo_id'] : 0;
        if ($todoId <= 0) {
            wp_send_json_error(array('message' => __('Tâche invalide.', 'mj-member')));
        }

        $todo = MjTodos::get($todoId);
        if (!$todo || !mj_member_todo_member_is_assignee($todo, $member)) {
            wp_send_json_error(array('message' => __('Vous ne pouvez pas modifier cette tâche.', 'mj-member')), 403);
        }

        $result = MjTodos::delete($todoId);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array('todoId' => $todoId));
    }
}

add_action('wp_ajax_mj_member_todos_fetch_my', 'mj_member_ajax_todos_fetch_my');
add_action('wp_ajax_mj_member_todos_toggle', 'mj_member_ajax_todos_toggle');
add_action('wp_ajax_mj_member_todos_create', 'mj_member_ajax_todos_create');
add_action('wp_ajax_mj_member_todo_project_create_front', 'mj_member_ajax_todo_project_create_front');
add_action('wp_ajax_mj_member_todo_update_front', 'mj_member_ajax_todo_update_front');
add_action('wp_ajax_mj_member_todo_note_create', 'mj_member_ajax_todo_note_create');
add_action('wp_ajax_mj_member_todo_note_delete', 'mj_member_ajax_todo_note_delete');
add_action('wp_ajax_mj_member_todo_archive', 'mj_member_ajax_todo_archive');
add_action('wp_ajax_mj_member_todo_unarchive', 'mj_member_ajax_todo_unarchive');
add_action('wp_ajax_mj_member_todos_fetch_archived', 'mj_member_ajax_todos_fetch_archived');
add_action('wp_ajax_mj_member_todo_media_attach', 'mj_member_ajax_todo_media_attach');
add_action('wp_ajax_mj_member_todo_media_detach', 'mj_member_ajax_todo_media_detach');
add_action('wp_ajax_mj_member_todo_delete_front', 'mj_member_ajax_todo_delete_front');