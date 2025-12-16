<?php

use Mj\Member\Classes\Crud\MjMembers;
use Mj\Member\Classes\Crud\MjTodoMedia;
use Mj\Member\Classes\Crud\MjTodoNotes;
use Mj\Member\Classes\Crud\MjTodoProjects;
use Mj\Member\Classes\Crud\MjTodos;
use Mj\Member\Classes\Value\MemberData;
use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('mj_member_todo_user_has_access')) {
    function mj_member_todo_user_has_access(): bool
    {
        $capability = Config::todosCapability();
        if ($capability !== '' && current_user_can($capability)) {
            return true;
        }

        if (!class_exists(MjMembers::class)) {
            return false;
        }

        $userId = get_current_user_id();
        if ($userId <= 0) {
            return false;
        }

        $member = MjMembers::getByWpUserId($userId);
        if (!($member instanceof MemberData)) {
            return false;
        }

        $role = (string) $member->get('role', '');
        return in_array($role, MjTodos::assignableRoles(), true);
    }
}

if (!function_exists('mj_member_todo_widget_localize')) {
    function mj_member_todo_widget_localize(): void
    {
        if (!function_exists('wp_localize_script')) {
            return;
        }

        $memberId = 0;
        $hasAccess = mj_member_todo_user_has_access();

        if ($hasAccess && function_exists('mj_member_todo_resolve_member')) {
            $member = mj_member_todo_resolve_member();
            if ($member instanceof MemberData) {
                $memberId = (int) $member->get('id', 0);
            }
        }

        $config = array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mj_member_todo_widget'),
            'hasAccess' => $hasAccess,
            'memberId' => $memberId,
            'actions' => array(
                'fetch' => 'mj_member_todos_fetch_my',
                'toggle' => 'mj_member_todos_toggle',
                'create' => 'mj_member_todos_create',
                'update' => 'mj_member_todo_update_front',
                'create_project' => 'mj_member_todo_project_create_front',
                'add_note' => 'mj_member_todo_note_create',
                'delete_note' => 'mj_member_todo_note_delete',
                'attach_media' => 'mj_member_todo_media_attach',
                'detach_media' => 'mj_member_todo_media_detach',
                'archive' => 'mj_member_todo_archive',
                'fetch_archived' => 'mj_member_todos_fetch_archived',
                'delete' => 'mj_member_todo_delete_front',
            ),
            'i18n' => array(
                'loadError' => __('Impossible de charger les tâches.', 'mj-member'),
                'archivesLoadError' => __('Impossible de charger les archives.', 'mj-member'),
                'createError' => __('Impossible de créer la tâche.', 'mj-member'),
                'updateError' => __('Impossible de mettre à jour la tâche.', 'mj-member'),
                'toggleError' => __('Impossible de mettre à jour le statut de la tâche.', 'mj-member'),
                'archiveError' => __('Impossible d’archiver la tâche.', 'mj-member'),
                'deleteError' => __('Impossible de supprimer la tâche.', 'mj-member'),
                'noteError' => __('Impossible d’enregistrer la note.', 'mj-member'),
                'noteDeleteError' => __('Impossible de supprimer la note.', 'mj-member'),
                'mediaAttachError' => __('Impossible d’attacher les médias.', 'mj-member'),
                'mediaDetachError' => __('Impossible de détacher le média.', 'mj-member'),
                'projectCreateError' => __('Impossible de créer le dossier.', 'mj-member'),
            ),
        );

        wp_localize_script('mj-member-todo-widget', 'mjMemberTodoWidget', $config);
    }
}

if (!function_exists('mj_member_todo_resolve_member')) {
    function mj_member_todo_resolve_member(): ?MemberData
    {
        if (!mj_member_todo_user_has_access()) {
            return null;
        }

        if (!class_exists(MjMembers::class)) {
            return null;
        }

        $userId = get_current_user_id();
        if ($userId <= 0) {
            return null;
        }

        $member = MjMembers::getByWpUserId($userId);
        if (!($member instanceof MemberData)) {
            return null;
        }

        $role = (string) $member->get('role', '');
        if (!in_array($role, MjTodos::assignableRoles(), true)) {
            return null;
        }

        return $member;
    }
}

if (!function_exists('mj_member_todo_extract_initials')) {
    function mj_member_todo_extract_initials(string $name): string
    {
        $trimmed = trim($name);
        if ($trimmed === '') {
            return '';
        }

        $parts = preg_split('/[\s\-]+/', $trimmed);
        if (!is_array($parts)) {
            $parts = array($trimmed);
        }

        $initials = '';
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $initials .= mb_substr($part, 0, 1, 'UTF-8');
            if (mb_strlen($initials, 'UTF-8') >= 2) {
                break;
            }
        }

        return mb_strtoupper(mb_substr($initials, 0, 2, 'UTF-8'), 'UTF-8');
    }
}

if (!function_exists('mj_member_todo_build_avatar_payload')) {
    /**
     * @param array<string,mixed> $memberRow
     */
    function mj_member_todo_build_avatar_payload(array $memberRow, string $fallbackName): array
    {
        $photoId = isset($memberRow['photo_id']) ? (int) $memberRow['photo_id'] : 0;
        $url = '';

        if ($photoId > 0) {
            $candidate = wp_get_attachment_image_url($photoId, 'thumbnail');
            if (is_string($candidate)) {
                $url = $candidate;
            }
        }

        if ($url === '') {
            $userId = isset($memberRow['wp_user_id']) ? (int) $memberRow['wp_user_id'] : 0;
            if ($userId > 0) {
                $avatarUrl = get_avatar_url($userId, array('size' => 96));
                if (is_string($avatarUrl) && $avatarUrl !== '') {
                    $url = $avatarUrl;
                }
            }
        }

        $name = $fallbackName !== '' ? $fallbackName : __('Membre', 'mj-member');
        $initials = mj_member_todo_extract_initials($name);
        $alt = sprintf(__('Avatar de %s', 'mj-member'), $name);

        return array(
            'url' => $url,
            'initials' => $initials,
            'alt' => $alt,
        );
    }
}

if (!function_exists('mj_member_todo_fetch_assignable_members')) {
    /**
     * @return array<int,array<string,mixed>>
     */
    function mj_member_todo_fetch_assignable_members(int $highlightMemberId = 0): array
    {
        if (!class_exists(MjMembers::class)) {
            return array();
        }

        $roles = array_map(static function ($role) {
            return sanitize_key((string) $role);
        }, MjTodos::assignableRoles());

        $roles = array_filter(array_unique($roles), static function ($value) {
            return $value !== '';
        });

        if (empty($roles)) {
            return array();
        }

        global $wpdb;
        $table = MjMembers::getTableName(MjMembers::TABLE_NAME);
        $placeholders = implode(',', array_fill(0, count($roles), '%s'));
        $params = array_merge($roles, array(MjMembers::STATUS_ACTIVE));

        $query = "SELECT id, first_name, last_name, nickname, role, photo_id, wp_user_id FROM {$table}"
            . " WHERE role IN ({$placeholders}) AND status = %s"
            . ' ORDER BY last_name ASC, first_name ASC, id ASC';

        $prepared = $wpdb->prepare($query, $params);
        if (!is_string($prepared)) {
            return array();
        }

        $rows = $wpdb->get_results($prepared, ARRAY_A);
        if (empty($rows)) {
            return array();
        }

        $members = array();
        foreach ($rows as $row) {
            $memberId = isset($row['id']) ? (int) $row['id'] : 0;
            if ($memberId <= 0) {
                continue;
            }

            $firstName = isset($row['first_name']) ? sanitize_text_field((string) $row['first_name']) : '';
            $lastName = isset($row['last_name']) ? sanitize_text_field((string) $row['last_name']) : '';
            $nickname = isset($row['nickname']) ? sanitize_text_field((string) $row['nickname']) : '';
            $name = trim($firstName . ' ' . $lastName);
            if ($name === '' && $nickname !== '') {
                $name = $nickname;
            }
            if ($name === '') {
                $name = sprintf(__('Membre #%d', 'mj-member'), $memberId);
            }

            $role = isset($row['role']) ? sanitize_key((string) $row['role']) : '';
            $avatar = mj_member_todo_build_avatar_payload($row, $name);

            $memberPayload = array(
                'id' => $memberId,
                'name' => $name,
                'role' => $role,
                'isSelf' => $highlightMemberId > 0 && $memberId === $highlightMemberId,
                'avatar' => $avatar,
                'avatarUrl' => $avatar['url'],
                'avatarInitials' => $avatar['initials'],
                'avatarAlt' => $avatar['alt'],
            );

            $members[] = $memberPayload;
        }

        return $members;
    }
}

if (!function_exists('mj_member_todo_member_is_assignee')) {
    /**
     * @param array<string,mixed> $todo
     */
    function mj_member_todo_member_is_assignee(array $todo, MemberData $member): bool
    {
        $memberId = (int) $member->get('id', 0);
        if ($memberId <= 0) {
            return false;
        }

        if (isset($todo['assigned_member_id']) && (int) $todo['assigned_member_id'] === $memberId) {
            return true;
        }

        if (isset($todo['assigned_member_ids']) && is_array($todo['assigned_member_ids'])) {
            foreach ($todo['assigned_member_ids'] as $candidate) {
                if ((int) $candidate === $memberId) {
                    return true;
                }
            }
        }

        if (!empty($todo['assignees']) && is_array($todo['assignees'])) {
            foreach ($todo['assignees'] as $assignee) {
                if (!is_array($assignee)) {
                    continue;
                }
                $assigneeId = isset($assignee['id']) ? (int) $assignee['id'] : (isset($assignee['member_id']) ? (int) $assignee['member_id'] : 0);
                if ($assigneeId === $memberId) {
                    return true;
                }
            }
        }

        return false;
    }
}

if (!function_exists('mj_member_todo_prepare_assignees_payload')) {
    /**
     * @param array<string,mixed> $todo
     * @return array<int,array<string,mixed>>
     */
    function mj_member_todo_prepare_assignees_payload(array $todo, int $viewerMemberId = 0): array
    {
        if (empty($todo['assignees']) || !is_array($todo['assignees'])) {
            return array();
        }

        $assignees = array();
        foreach ($todo['assignees'] as $assignee) {
            if (!is_array($assignee)) {
                continue;
            }

            $assigneeId = isset($assignee['id']) ? (int) $assignee['id'] : (isset($assignee['member_id']) ? (int) $assignee['member_id'] : 0);
            if ($assigneeId <= 0) {
                continue;
            }

            $name = isset($assignee['name']) ? sanitize_text_field((string) $assignee['name']) : '';
            if ($name === '') {
                $name = sprintf(__('Membre #%d', 'mj-member'), $assigneeId);
            }

            $avatarData = array('url' => '', 'initials' => '', 'alt' => '');
            if (isset($assignee['avatar']) && is_array($assignee['avatar'])) {
                $avatarData['url'] = isset($assignee['avatar']['url']) ? esc_url_raw((string) $assignee['avatar']['url']) : '';
                $avatarData['initials'] = isset($assignee['avatar']['initials']) ? sanitize_text_field((string) $assignee['avatar']['initials']) : '';
                $avatarData['alt'] = isset($assignee['avatar']['alt']) ? sanitize_text_field((string) $assignee['avatar']['alt']) : '';
            }

            if ($avatarData['initials'] === '') {
                $avatarData['initials'] = mj_member_todo_extract_initials($name);
            }
            if ($avatarData['alt'] === '') {
                $avatarData['alt'] = sprintf(__('Avatar de %s', 'mj-member'), $name);
            }

            $role = isset($assignee['role']) ? sanitize_key((string) $assignee['role']) : '';
            $assignedAt = isset($assignee['assigned_at']) ? sanitize_text_field((string) $assignee['assigned_at']) : (isset($assignee['assignedAt']) ? sanitize_text_field((string) $assignee['assignedAt']) : '');
            $assignedBy = isset($assignee['assigned_by']) ? (int) $assignee['assigned_by'] : (isset($assignee['assignedBy']) ? (int) $assignee['assignedBy'] : 0);

            $assignees[] = array(
                'id' => $assigneeId,
                'name' => $name,
                'role' => $role,
                'isSelf' => $viewerMemberId > 0 && $assigneeId === $viewerMemberId,
                'assignedAt' => $assignedAt,
                'assignedBy' => $assignedBy,
                'avatar' => $avatarData,
                'avatarUrl' => $avatarData['url'],
                'avatarInitials' => $avatarData['initials'],
                'avatarAlt' => $avatarData['alt'],
            );
        }

        return $assignees;
    }
}

if (!function_exists('mj_member_todo_prepare_note_payload')) {
    /**
     * @param array<string,mixed> $note
     * @return array<string,mixed>
     */
    function mj_member_todo_prepare_note_payload(array $note): array
    {
        $id = isset($note['id']) ? (int) $note['id'] : (isset($note['note_id']) ? (int) $note['note_id'] : 0);
        $todoId = isset($note['todo_id']) ? (int) $note['todo_id'] : (isset($note['todoId']) ? (int) $note['todoId'] : 0);
        $memberId = isset($note['member_id']) ? (int) $note['member_id'] : (isset($note['memberId']) ? (int) $note['memberId'] : 0);

        $content = isset($note['content']) ? sanitize_textarea_field((string) $note['content']) : '';
        $authorName = isset($note['author_name']) ? sanitize_text_field((string) $note['author_name']) : (isset($note['authorName']) ? sanitize_text_field((string) $note['authorName']) : '');
        if ($authorName === '' && $memberId > 0) {
            $authorName = sprintf(__('Membre #%d', 'mj-member'), $memberId);
        }

        $createdAt = isset($note['created_at']) ? sanitize_text_field((string) $note['created_at']) : (isset($note['createdAt']) ? sanitize_text_field((string) $note['createdAt']) : '');

        return array(
            'id' => $id,
            'todoId' => $todoId,
            'memberId' => $memberId,
            'content' => $content,
            'authorName' => $authorName,
            'createdAt' => $createdAt,
        );
    }
}

if (!function_exists('mj_member_todo_prepare_media_payload')) {
    /**
     * @param array<string,mixed> $media
     * @return array<string,mixed>
     */
    function mj_member_todo_prepare_media_payload(array $media): array
    {
        $id = isset($media['id']) ? (int) $media['id'] : (isset($media['media_id']) ? (int) $media['media_id'] : 0);
        $todoId = isset($media['todo_id']) ? (int) $media['todo_id'] : (isset($media['todoId']) ? (int) $media['todoId'] : 0);
        $attachmentId = isset($media['attachment_id']) ? (int) $media['attachment_id'] : (isset($media['attachmentId']) ? (int) $media['attachmentId'] : 0);

        $title = isset($media['title']) ? sanitize_text_field((string) $media['title']) : '';
        $filename = isset($media['filename']) ? sanitize_text_field((string) $media['filename']) : '';

        $url = isset($media['url']) ? esc_url_raw((string) $media['url']) : '';
        if ($url === '' && $attachmentId > 0) {
            $attachmentUrl = wp_get_attachment_url($attachmentId);
            if (is_string($attachmentUrl)) {
                $url = $attachmentUrl;
            }
        }

        $previewUrl = isset($media['preview_url']) ? esc_url_raw((string) $media['preview_url']) : (isset($media['previewUrl']) ? esc_url_raw((string) $media['previewUrl']) : '');
        if ($previewUrl === '' && $attachmentId > 0 && function_exists('wp_attachment_is_image') && wp_attachment_is_image($attachmentId)) {
            $imageUrl = wp_get_attachment_image_url($attachmentId, 'medium');
            if (is_string($imageUrl) && $imageUrl !== '') {
                $previewUrl = $imageUrl;
            }
        }
        if ($previewUrl === '') {
            $previewUrl = $url;
        }

        $iconUrl = isset($media['icon_url']) ? esc_url_raw((string) $media['icon_url']) : (isset($media['iconUrl']) ? esc_url_raw((string) $media['iconUrl']) : '');
        if ($iconUrl === '' && $attachmentId > 0) {
            $iconCandidate = wp_mime_type_icon($attachmentId);
            if (is_string($iconCandidate)) {
                $iconUrl = $iconCandidate;
            }
        }

        $mimeType = isset($media['mime_type']) ? sanitize_text_field((string) $media['mime_type']) : (isset($media['mimeType']) ? sanitize_text_field((string) $media['mimeType']) : '');
        $type = isset($media['type']) ? sanitize_key((string) $media['type']) : '';
        if ($type === '' && $mimeType !== '' && strpos($mimeType, '/') !== false) {
            $parts = explode('/', $mimeType);
            $type = sanitize_key((string) ($parts[0] ?? ''));
        }

        $createdAt = isset($media['created_at']) ? sanitize_text_field((string) $media['created_at']) : (isset($media['createdAt']) ? sanitize_text_field((string) $media['createdAt']) : '');
        $addedByMember = isset($media['member_id']) ? (int) $media['member_id'] : (isset($media['memberId']) ? (int) $media['memberId'] : 0);
        $addedByUser = isset($media['wp_user_id']) ? (int) $media['wp_user_id'] : (isset($media['wpUserId']) ? (int) $media['wpUserId'] : 0);

        return array(
            'id' => $id,
            'todoId' => $todoId,
            'attachmentId' => $attachmentId,
            'title' => $title,
            'filename' => $filename,
            'url' => $url,
            'previewUrl' => $previewUrl,
            'iconUrl' => $iconUrl,
            'mimeType' => $mimeType,
            'type' => $type,
            'addedAt' => $createdAt,
            'addedBy' => $addedByMember,
            'addedByUser' => $addedByUser,
        );
    }
}

if (!function_exists('mj_member_todo_prepare_media_payloads')) {
    /**
     * @param array<int,array<string,mixed>> $mediaList
     * @return array<int,array<string,mixed>>
     */
    function mj_member_todo_prepare_media_payloads(array $mediaList): array
    {
        if (empty($mediaList)) {
            return array();
        }

        $payload = array();
        foreach ($mediaList as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $payload[] = mj_member_todo_prepare_media_payload($entry);
        }

        return $payload;
    }
}

if (!function_exists('mj_member_todo_prepare_payload')) {
    /**
     * @param array<string,mixed> $todo
     * @param array<int,string> $projectMap
     * @return array<string,mixed>
     */
    function mj_member_todo_prepare_payload(array $todo, array $projectMap, int $viewerMemberId = 0): array
    {
        $todoId = isset($todo['id']) ? (int) $todo['id'] : 0;
        $projectId = isset($todo['project_id']) ? (int) $todo['project_id'] : 0;
        $projectTitle = $projectMap[$projectId] ?? '';

        $title = isset($todo['title']) ? sanitize_text_field((string) $todo['title']) : '';
        $description = isset($todo['description']) ? wp_kses_post((string) $todo['description']) : '';
        $status = isset($todo['status']) ? sanitize_key((string) $todo['status']) : MjTodos::STATUS_OPEN;
        $position = isset($todo['position']) ? (int) $todo['position'] : 0;
        $priority = isset($todo['priority']) ? (int) $todo['priority'] : $position;
        $dueDate = isset($todo['due_date']) ? sanitize_text_field((string) $todo['due_date']) : '';
        $completedAt = isset($todo['completed_at']) ? sanitize_text_field((string) $todo['completed_at']) : '';

        $assignees = mj_member_todo_prepare_assignees_payload($todo, $viewerMemberId);
        $notesRaw = isset($todo['notes']) && is_array($todo['notes']) ? $todo['notes'] : array();
        $notes = array();
        foreach ($notesRaw as $note) {
            if (!is_array($note)) {
                continue;
            }
            $notes[] = mj_member_todo_prepare_note_payload($note);
        }

        $mediaRaw = isset($todo['media']) && is_array($todo['media']) ? $todo['media'] : array();
        $media = mj_member_todo_prepare_media_payloads($mediaRaw);

        $assignedMemberId = isset($todo['assigned_member_id']) ? (int) $todo['assigned_member_id'] : 0;

        return array(
            'id' => $todoId,
            'projectId' => $projectId,
            'projectTitle' => is_string($projectTitle) ? $projectTitle : '',
            'title' => $title,
            'description' => $description,
            'status' => $status,
            'priority' => $priority,
            'position' => $position,
            'dueDate' => $dueDate,
            'completedAt' => $completedAt,
            'assignees' => $assignees,
            'assignedMemberId' => $assignedMemberId,
            'notes' => $notes,
            'media' => $media,
            'createdAt' => isset($todo['created_at']) ? sanitize_text_field((string) $todo['created_at']) : '',
            'updatedAt' => isset($todo['updated_at']) ? sanitize_text_field((string) $todo['updated_at']) : '',
        );
    }
}
