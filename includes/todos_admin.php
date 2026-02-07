<?php
declare(strict_types=1);
use Mj\Member\Admin\Page\TodoProjectsPage;
use Mj\Member\Admin\Page\TodosPage;
use Mj\Member\Classes\Crud\MjTodoMedia;
use Mj\Member\Classes\Crud\MjTodoProjects;
use Mj\Member\Classes\Crud\MjTodos;
use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('mj_member_todos_admin_require_capability')) {
    function mj_member_todos_admin_require_capability(): void
    {
        $capability = Config::capability();
        if ($capability === '') {
            $capability = 'manage_options';
        }

        if (!current_user_can($capability)) {
            wp_die(esc_html__('Accès refusé.', 'mj-member'));
        }
    }
}

if (!function_exists('mj_member_todos_admin_allowed_pages')) {
    /**
     * @return array<int,string>
     */
    function mj_member_todos_admin_allowed_pages(): array
    {
        return array(
            TodosPage::slug(),
            TodoProjectsPage::slug(),
        );
    }
}

if (!function_exists('mj_member_todos_admin_resolve_page_slug')) {
    /**
     * @param mixed $value
     */
    function mj_member_todos_admin_resolve_page_slug($value = '', string $fallback = ''): string
    {
        $allowed = mj_member_todos_admin_allowed_pages();

        $candidate = sanitize_key((string) $value);
        if ($candidate !== '' && in_array($candidate, $allowed, true)) {
            return $candidate;
        }

        $fallbackCandidate = sanitize_key($fallback);
        if ($fallbackCandidate !== '' && in_array($fallbackCandidate, $allowed, true)) {
            return $fallbackCandidate;
        }

        return TodosPage::slug();
    }
}

if (!function_exists('mj_member_todos_sanitize_int_list')) {
    /**
     * @param mixed $values
     * @return array<int,int>
     */
    function mj_member_todos_sanitize_int_list($values): array
    {
        if (!is_array($values)) {
            $values = array($values);
        }

        $ids = array();
        foreach ($values as $value) {
            $candidate = (int) $value;
            if ($candidate > 0) {
                $ids[$candidate] = $candidate;
            }
        }

        return array_values($ids);
    }
}

if (!function_exists('mj_member_todos_sanitize_attachment_ids')) {
    /**
     * @param mixed $values
     * @return array<int,int>
     */
    function mj_member_todos_sanitize_attachment_ids($values): array
    {
        if (!is_array($values)) {
            $values = array($values);
        }

        $ids = array();
        foreach ($values as $value) {
            $candidate = (int) $value;
            if ($candidate <= 0) {
                continue;
            }

            $attachment = get_post($candidate);
            if ($attachment instanceof \WP_Post && $attachment->post_type === 'attachment') {
                $ids[$candidate] = $candidate;
            }
        }

        return array_values($ids);
    }
}

if (!function_exists('mj_member_todos_admin_redirect')) {
    /**
     * @param array<string,mixed> $args
     */
    function mj_member_todos_admin_redirect(array $args = array()): void
    {
        $baseUrl = admin_url('admin.php');
        $page = TodosPage::slug();
        if (isset($args['page'])) {
            $page = mj_member_todos_admin_resolve_page_slug($args['page']);
            unset($args['page']);
        }

        $query = array('page' => $page);
        foreach ($args as $key => $value) {
            if (!is_scalar($value)) {
                continue;
            }
            $query[$key] = (string) $value;
        }

        wp_safe_redirect(add_query_arg($query, $baseUrl));
        exit;
    }
}

if (!function_exists('mj_member_todos_handle_project_create')) {
    function mj_member_todos_handle_project_create(): void
    {
        mj_member_todos_admin_require_capability();
        check_admin_referer('mj_member_todo_project_create');

        $redirectPageRaw = isset($_POST['redirect_page']) ? wp_unslash((string) $_POST['redirect_page']) : TodoProjectsPage::slug();
        $redirectPage = mj_member_todos_admin_resolve_page_slug($redirectPageRaw, TodoProjectsPage::slug());

        $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash((string) $_POST['title'])) : '';
        $slug = isset($_POST['slug']) ? sanitize_title(wp_unslash((string) $_POST['slug'])) : '';
        $description = isset($_POST['description']) ? sanitize_textarea_field(wp_unslash((string) $_POST['description'])) : '';
        $color = isset($_POST['color']) ? sanitize_hex_color(wp_unslash((string) $_POST['color'])) : '';

        if ($title === '') {
            mj_member_todos_admin_redirect(array(
                'page' => $redirectPage,
                'mj_todo_notice' => 'error',
                'mj_todo_error' => rawurlencode(__('Le titre du dossier est requis.', 'mj-member')),
            ));
        }

        $result = MjTodoProjects::create(array(
            'title' => $title,
            'slug' => $slug,
            'description' => $description,
            'color' => $color,
            'created_by' => get_current_user_id(),
        ));

        if (is_wp_error($result)) {
            mj_member_todos_admin_redirect(array(
                'page' => $redirectPage,
                'mj_todo_notice' => 'error',
                'mj_todo_error' => rawurlencode($result->get_error_message()),
            ));
        }

        mj_member_todos_admin_redirect(array(
            'page' => $redirectPage,
            'mj_todo_notice' => 'project_created',
        ));
    }
}
add_action('admin_post_mj_member_todo_project_create', 'mj_member_todos_handle_project_create');

if (!function_exists('mj_member_todos_handle_project_update')) {
    function mj_member_todos_handle_project_update(): void
    {
        mj_member_todos_admin_require_capability();
        check_admin_referer('mj_member_todo_project_update');

        $redirectPageRaw = isset($_POST['redirect_page']) ? wp_unslash((string) $_POST['redirect_page']) : TodoProjectsPage::slug();
        $redirectPage = mj_member_todos_admin_resolve_page_slug($redirectPageRaw, TodoProjectsPage::slug());

        $projectId = isset($_POST['project_id']) ? (int) $_POST['project_id'] : 0;
        if ($projectId <= 0) {
            mj_member_todos_admin_redirect(array(
                'page' => $redirectPage,
                'mj_todo_notice' => 'error',
                'mj_todo_error' => rawurlencode(__('Dossier introuvable.', 'mj-member')),
            ));
        }

        $payload = array();
        if (isset($_POST['title'])) {
            $payload['title'] = sanitize_text_field(wp_unslash((string) $_POST['title']));
        }
        if (isset($_POST['slug'])) {
            $payload['slug'] = sanitize_title(wp_unslash((string) $_POST['slug']));
        }
        if (isset($_POST['description'])) {
            $payload['description'] = sanitize_textarea_field(wp_unslash((string) $_POST['description']));
        }
        if (isset($_POST['color'])) {
            $payload['color'] = sanitize_hex_color(wp_unslash((string) $_POST['color']));
        }
        $payload['updated_by'] = get_current_user_id();

        $result = MjTodoProjects::update($projectId, $payload);
        if (is_wp_error($result)) {
            mj_member_todos_admin_redirect(array(
                'page' => $redirectPage,
                'mj_todo_notice' => 'error',
                'mj_todo_error' => rawurlencode($result->get_error_message()),
            ));
        }

        mj_member_todos_admin_redirect(array(
            'page' => $redirectPage,
            'mj_todo_notice' => 'project_updated',
        ));
    }
}
add_action('admin_post_mj_member_todo_project_update', 'mj_member_todos_handle_project_update');

if (!function_exists('mj_member_todos_handle_project_delete')) {
    function mj_member_todos_handle_project_delete(): void
    {
        mj_member_todos_admin_require_capability();
        check_admin_referer('mj_member_todo_project_delete');

        $redirectPageRaw = isset($_POST['redirect_page']) ? wp_unslash((string) $_POST['redirect_page']) : TodoProjectsPage::slug();
        $redirectPage = mj_member_todos_admin_resolve_page_slug($redirectPageRaw, TodoProjectsPage::slug());

        $projectId = isset($_POST['project_id']) ? (int) $_POST['project_id'] : 0;
        if ($projectId <= 0) {
            mj_member_todos_admin_redirect(array(
                'page' => $redirectPage,
                'mj_todo_notice' => 'error',
                'mj_todo_error' => rawurlencode(__('Dossier introuvable.', 'mj-member')),
            ));
        }

        $result = MjTodoProjects::delete($projectId);
        if (is_wp_error($result)) {
            mj_member_todos_admin_redirect(array(
                'page' => $redirectPage,
                'mj_todo_notice' => 'error',
                'mj_todo_error' => rawurlencode($result->get_error_message()),
            ));
        }

        mj_member_todos_admin_redirect(array(
            'page' => $redirectPage,
            'mj_todo_notice' => 'project_deleted',
        ));
    }
}
add_action('admin_post_mj_member_todo_project_delete', 'mj_member_todos_handle_project_delete');

if (!function_exists('mj_member_todos_handle_todo_create')) {
    function mj_member_todos_handle_todo_create(): void
    {
        mj_member_todos_admin_require_capability();
        check_admin_referer('mj_member_todo_create');

        $redirectPageRaw = isset($_POST['redirect_page']) ? wp_unslash((string) $_POST['redirect_page']) : TodosPage::slug();
        $redirectPage = mj_member_todos_admin_resolve_page_slug($redirectPageRaw, TodosPage::slug());

        $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash((string) $_POST['title'])) : '';
        $description = isset($_POST['description']) ? sanitize_textarea_field(wp_unslash((string) $_POST['description'])) : '';
        $status = isset($_POST['status']) ? sanitize_key(wp_unslash((string) $_POST['status'])) : MjTodos::STATUS_OPEN;
        $projectId = isset($_POST['project_id']) ? (int) wp_unslash((string) $_POST['project_id']) : 0;

        $assignedIdsRaw = isset($_POST['assigned_member_ids']) ? wp_unslash((array) $_POST['assigned_member_ids']) : array();
        $assignedMemberIds = mj_member_todos_sanitize_int_list($assignedIdsRaw);
        $singleAssignee = isset($_POST['assigned_member_id']) ? (int) wp_unslash((string) $_POST['assigned_member_id']) : 0;
        if ($singleAssignee > 0 && !in_array($singleAssignee, $assignedMemberIds, true)) {
            $assignedMemberIds[] = $singleAssignee;
        }
        $assignedMemberIds = array_values(array_unique($assignedMemberIds));
        $primaryAssigneeId = !empty($assignedMemberIds) ? (int) $assignedMemberIds[0] : 0;

        $dueDateRaw = isset($_POST['due_date']) ? wp_unslash((string) $_POST['due_date']) : '';
        $dueDate = MjTodos::sanitize_due_date($dueDateRaw);

        if ($title === '') {
            mj_member_todos_admin_redirect(array(
                'page' => $redirectPage,
                'mj_todo_notice' => 'error',
                'mj_todo_error' => rawurlencode(__('Le titre est requis.', 'mj-member')),
            ));
        }

        if ($dueDate instanceof \WP_Error) {
            mj_member_todos_admin_redirect(array(
                'page' => $redirectPage,
                'mj_todo_notice' => 'error',
                'mj_todo_error' => rawurlencode($dueDate->get_error_message()),
            ));
        }

        $data = array(
            'title' => $title,
            'description' => $description,
            'status' => $status,
            'project_id' => $projectId,
            'assigned_member_id' => $primaryAssigneeId,
            'assigned_member_ids' => $assignedMemberIds,
            'assigned_by' => get_current_user_id(),
            'due_date' => $dueDate,
            'created_by' => get_current_user_id(),
        );

        $result = MjTodos::create($data);
        if (is_wp_error($result)) {
            mj_member_todos_admin_redirect(array(
                'page' => $redirectPage,
                'mj_todo_notice' => 'error',
                'mj_todo_error' => rawurlencode($result->get_error_message()),
            ));
        }

        $todoId = (int) $result;

        $newMediaRaw = isset($_POST['new_media_ids']) ? wp_unslash((array) $_POST['new_media_ids']) : array();
        $newMediaIds = mj_member_todos_sanitize_attachment_ids($newMediaRaw);
        if (!empty($newMediaIds) && class_exists(MjTodoMedia::class)) {
            $mediaResult = MjTodoMedia::attach_multiple($todoId, $newMediaIds, $primaryAssigneeId, get_current_user_id());
            if (is_wp_error($mediaResult)) {
                mj_member_todos_admin_redirect(array(
                    'page' => $redirectPage,
                    'mj_todo_notice' => 'error',
                    'mj_todo_error' => rawurlencode($mediaResult->get_error_message()),
                ));
            }
        }

        // Notifier les membres assignés
        if (!empty($assignedMemberIds)) {
            do_action('mj_member_todo_assigned', $todoId, $assignedMemberIds, $title, get_current_user_id());
        }

        mj_member_todos_admin_redirect(array(
            'page' => $redirectPage,
            'mj_todo_notice' => 'todo_created',
        ));
    }
}
add_action('admin_post_mj_member_todo_create', 'mj_member_todos_handle_todo_create');

if (!function_exists('mj_member_todos_handle_todo_update')) {
    function mj_member_todos_handle_todo_update(): void
    {
        mj_member_todos_admin_require_capability();
        check_admin_referer('mj_member_todo_update');

        $redirectPageRaw = isset($_POST['redirect_page']) ? wp_unslash((string) $_POST['redirect_page']) : TodosPage::slug();
        $redirectPage = mj_member_todos_admin_resolve_page_slug($redirectPageRaw, TodosPage::slug());

        $todoId = isset($_POST['todo_id']) ? (int) $_POST['todo_id'] : 0;
        if ($todoId <= 0) {
            mj_member_todos_admin_redirect(array(
                'page' => $redirectPage,
                'mj_todo_notice' => 'error',
                'mj_todo_error' => rawurlencode(__('Tâche introuvable.', 'mj-member')),
            ));
        }

        $payload = array();
        $assignedMemberIds = array();

        if (isset($_POST['title'])) {
            $payload['title'] = sanitize_text_field(wp_unslash((string) $_POST['title']));
        }
        if (isset($_POST['description'])) {
            $payload['description'] = sanitize_textarea_field(wp_unslash((string) $_POST['description']));
        }
        if (isset($_POST['status'])) {
            $payload['status'] = sanitize_key(wp_unslash((string) $_POST['status']));
        }
        if (isset($_POST['project_id'])) {
            $payload['project_id'] = (int) wp_unslash((string) $_POST['project_id']);
        }

        if (isset($_POST['assigned_member_ids']) || isset($_POST['assigned_member_id'])) {
            $assignedIdsRaw = isset($_POST['assigned_member_ids']) ? wp_unslash((array) $_POST['assigned_member_ids']) : array();
            $assignedMemberIds = mj_member_todos_sanitize_int_list($assignedIdsRaw);
            $singleAssignee = isset($_POST['assigned_member_id']) ? (int) wp_unslash((string) $_POST['assigned_member_id']) : 0;
            if ($singleAssignee > 0 && !in_array($singleAssignee, $assignedMemberIds, true)) {
                $assignedMemberIds[] = $singleAssignee;
            }
            $assignedMemberIds = array_values(array_unique($assignedMemberIds));
            $payload['assigned_member_ids'] = $assignedMemberIds;

            if (!empty($assignedMemberIds)) {
                $payload['assigned_by'] = get_current_user_id();
            }
        }

        if (isset($_POST['due_date'])) {
            $dueDate = MjTodos::sanitize_due_date(wp_unslash((string) $_POST['due_date']));
            if ($dueDate instanceof \WP_Error) {
                mj_member_todos_admin_redirect(array(
                    'page' => $redirectPage,
                    'mj_todo_notice' => 'error',
                    'mj_todo_error' => rawurlencode($dueDate->get_error_message()),
                ));
            }
            $payload['due_date'] = $dueDate;
        }

        $payload['updated_by'] = get_current_user_id();

        $result = MjTodos::update($todoId, $payload);
        if (is_wp_error($result)) {
            mj_member_todos_admin_redirect(array(
                'page' => $redirectPage,
                'mj_todo_notice' => 'error',
                'mj_todo_error' => rawurlencode($result->get_error_message()),
            ));
        }

        $newMediaRaw = isset($_POST['new_media_ids']) ? wp_unslash((array) $_POST['new_media_ids']) : array();
        $newMediaIds = mj_member_todos_sanitize_attachment_ids($newMediaRaw);
        if (!empty($newMediaIds) && class_exists(MjTodoMedia::class)) {
            $memberIdForMedia = !empty($assignedMemberIds) ? (int) $assignedMemberIds[0] : 0;
            $mediaResult = MjTodoMedia::attach_multiple($todoId, $newMediaIds, $memberIdForMedia, get_current_user_id());
            if (is_wp_error($mediaResult)) {
                mj_member_todos_admin_redirect(array(
                    'page' => $redirectPage,
                    'mj_todo_notice' => 'error',
                    'mj_todo_error' => rawurlencode($mediaResult->get_error_message()),
                ));
            }
        }

        $removeMediaRaw = isset($_POST['remove_media_ids']) ? wp_unslash((array) $_POST['remove_media_ids']) : array();
        $removeMediaIds = mj_member_todos_sanitize_attachment_ids($removeMediaRaw);
        if (!empty($removeMediaIds) && class_exists(MjTodoMedia::class)) {
            foreach ($removeMediaIds as $attachmentId) {
                $detachResult = MjTodoMedia::detach($todoId, $attachmentId);
                if (is_wp_error($detachResult)) {
                    mj_member_todos_admin_redirect(array(
                        'page' => $redirectPage,
                        'mj_todo_notice' => 'error',
                        'mj_todo_error' => rawurlencode($detachResult->get_error_message()),
                    ));
                }
            }
        }

        // Notifier les nouveaux membres assignés
        if (!empty($assignedMemberIds)) {
            $todoTitle = isset($payload['title']) ? $payload['title'] : '';
            if ($todoTitle === '') {
                $existingTodo = MjTodos::get_by_id($todoId);
                $todoTitle = $existingTodo ? (string) ($existingTodo['title'] ?? '') : '';
            }
            do_action('mj_member_todo_assigned', $todoId, $assignedMemberIds, $todoTitle, get_current_user_id());
        }

        mj_member_todos_admin_redirect(array(
            'page' => $redirectPage,
            'mj_todo_notice' => 'todo_updated',
        ));
    }
}
add_action('admin_post_mj_member_todo_update', 'mj_member_todos_handle_todo_update');

if (!function_exists('mj_member_todos_handle_todo_delete')) {
    function mj_member_todos_handle_todo_delete(): void
    {
        mj_member_todos_admin_require_capability();
        check_admin_referer('mj_member_todo_delete');

        $redirectPageRaw = isset($_POST['redirect_page']) ? wp_unslash((string) $_POST['redirect_page']) : TodosPage::slug();
        $redirectPage = mj_member_todos_admin_resolve_page_slug($redirectPageRaw, TodosPage::slug());

        $todoId = isset($_POST['todo_id']) ? (int) $_POST['todo_id'] : 0;
        if ($todoId <= 0) {
            mj_member_todos_admin_redirect(array(
                'page' => $redirectPage,
                'mj_todo_notice' => 'error',
                'mj_todo_error' => rawurlencode(__('Tâche introuvable.', 'mj-member')),
            ));
        }

        $result = MjTodos::delete($todoId);
        if (is_wp_error($result)) {
            mj_member_todos_admin_redirect(array(
                'page' => $redirectPage,
                'mj_todo_notice' => 'error',
                'mj_todo_error' => rawurlencode($result->get_error_message()),
            ));
        }

        mj_member_todos_admin_redirect(array(
            'page' => $redirectPage,
            'mj_todo_notice' => 'todo_deleted',
        ));
    }
}
add_action('admin_post_mj_member_todo_delete', 'mj_member_todos_handle_todo_delete');
