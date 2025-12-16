<?php

namespace Mj\Member\Admin\Page;

use Mj\Member\Admin\RequestGuard;
use Mj\Member\Classes\Crud\MjMembers;
use Mj\Member\Classes\Crud\MjTodoProjects;
use Mj\Member\Classes\Crud\MjTodos;
use Mj\Member\Classes\Table\MjTodos_List_Table;
use Mj\Member\Classes\Value\MemberData;
use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

final class TodosPage
{
    public static function slug(): string
    {
        return 'mj_member_todos';
    }

    public static function registerHooks(?string $hookSuffix): void
    {
        if ($hookSuffix === null || $hookSuffix === '') {
            return;
        }

        add_action('load-' . $hookSuffix, array(__CLASS__, 'handleLoad'));
    }

    public static function deleteNonceAction(int $todoId): string
    {
        return 'mj_member_todo_delete_' . $todoId;
    }

    public static function render(): void
    {
        $capability = Config::capability();
        if ($capability === '') {
            $capability = 'manage_options';
        }

        RequestGuard::ensureCapabilityOrDie($capability);

        $mode = self::determineMode();

        $view = array(
            'mode' => $mode,
            'notice' => self::extractNotice(),
            'projects' => self::getProjects(),
            'members' => self::getAssignableMembers(),
            'statuses' => MjTodos::statuses(),
            'status_labels' => self::statusLabels(),
        );

        if ($mode === 'edit') {
            $todoId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
            $todo = $todoId > 0 ? MjTodos::get($todoId) : null;
            if (!$todo) {
                self::redirectWithError(__('Tâche introuvable.', 'mj-member'));
            }
            $view['todo'] = $todo;
        } elseif ($mode === 'add') {
            $view['todo'] = self::defaultTodoPayload();
        }

        $template = Config::path() . 'includes/templates/admin/todos-page.php';
        if (is_readable($template)) {
            $table = null;
            if ($mode === 'list') {
                $table = new MjTodos_List_Table(array(
                    'projects' => $view['projects'],
                    'assignable_members' => $view['members'],
                    'status_labels' => $view['status_labels'],
                ));
            }
            $view['table'] = $table;
            require $template;
        }
    }

    public static function handleLoad(): void
    {
        $capability = Config::capability();
        if ($capability === '') {
            $capability = 'manage_options';
        }

        if (!RequestGuard::ensureCapability($capability)) {
            return;
        }

        $action = isset($_GET['action']) ? sanitize_key(wp_unslash((string) $_GET['action'])) : '';
        if ($action !== 'delete') {
            return;
        }

        $todoId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        $nonce = isset($_GET['_wpnonce']) ? (string) $_GET['_wpnonce'] : '';

        if ($todoId <= 0 || !wp_verify_nonce($nonce, self::deleteNonceAction($todoId))) {
            wp_die(esc_html__('Action non autorisée.', 'mj-member'));
        }

        $result = MjTodos::delete($todoId);
        if (is_wp_error($result)) {
            self::redirectWithError($result->get_error_message());
        }

        self::redirectWithMessage('todo_deleted');
    }

    private static function determineMode(): string
    {
        $action = isset($_GET['action']) ? sanitize_key(wp_unslash((string) $_GET['action'])) : '';

        if ($action === 'add') {
            return 'add';
        }

        if ($action === 'edit') {
            return 'edit';
        }

        return 'list';
    }

    private static function statusLabels(): array
    {
        return array(
            MjTodos::STATUS_OPEN => __('À faire', 'mj-member'),
            MjTodos::STATUS_COMPLETED => __('Terminée', 'mj-member'),
            MjTodos::STATUS_ARCHIVED => __('Archivée', 'mj-member'),
        );
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function getProjects(): array
    {
        $projects = MjTodoProjects::get_all(array(
            'orderby' => 'title',
            'order' => 'ASC',
        ));

        $indexed = array();
        foreach ($projects as $project) {
            $id = isset($project['id']) ? (int) $project['id'] : 0;
            if ($id <= 0) {
                continue;
            }
            $indexed[$id] = $project;
        }

        return $indexed;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function getAssignableMembers(): array
    {
        if (!class_exists(MjMembers::class)) {
            return array();
        }

        $roles = MjTodos::assignableRoles();
        $options = array();

        foreach ($roles as $role) {
            $members = MjMembers::getAll(0, 0, 'last_name', 'ASC', '', array('role' => $role));
            foreach ($members as $member) {
                if (!($member instanceof MemberData)) {
                    continue;
                }
                $id = (int) $member->get('id', 0);
                if ($id <= 0) {
                    continue;
                }
                $label = trim(sprintf('%s %s', (string) $member->get('first_name', ''), (string) $member->get('last_name', '')));
                if ($label === '') {
                    $label = sprintf(__('Membre #%d', 'mj-member'), $id);
                }
                $options[] = array(
                    'id' => $id,
                    'label' => $label,
                    'role' => (string) $member->get('role', ''),
                );
            }
        }

        usort($options, static function ($a, $b): int {
            return strcasecmp($a['label'], $b['label']);
        });

        return $options;
    }

    private static function defaultTodoPayload(): array
    {
        return array(
            'id' => 0,
            'title' => '',
            'description' => '',
            'status' => MjTodos::STATUS_OPEN,
            'project_id' => 0,
            'assigned_member_id' => 0,
            'assignees' => array(),
            'due_date' => '',
            'media' => array(),
        );
    }

    /**
     * @return array<string,string>
     */
    private static function extractNotice(): array
    {
        if (!isset($_GET['mj_todo_notice'])) {
            return array();
        }

        $type = sanitize_key((string) wp_unslash($_GET['mj_todo_notice']));
        if ($type === '') {
            return array();
        }

        $messages = array(
            'project_created' => __('Projet créé avec succès.', 'mj-member'),
            'project_updated' => __('Projet mis à jour.', 'mj-member'),
            'project_deleted' => __('Projet supprimé.', 'mj-member'),
            'todo_created' => __('Tâche créée.', 'mj-member'),
            'todo_updated' => __('Tâche mise à jour.', 'mj-member'),
            'todo_deleted' => __('Tâche supprimée.', 'mj-member'),
        );

        if ($type === 'error') {
            $rawMessage = isset($_GET['mj_todo_error']) ? (string) $_GET['mj_todo_error'] : '';
            $decoded = $rawMessage !== '' ? rawurldecode($rawMessage) : '';
            $message = $decoded !== '' ? sanitize_text_field(wp_unslash($decoded)) : __('Une erreur est survenue.', 'mj-member');
            return array(
                'type' => 'error',
                'message' => $message,
            );
        }

        if (!isset($messages[$type])) {
            return array();
        }

        return array(
            'type' => 'success',
            'message' => $messages[$type],
        );
    }

    private static function redirectWithMessage(string $type, ?string $message = null): void
    {
        $args = array(
            'page' => self::slug(),
            'mj_todo_notice' => $type,
        );

        if ($type === 'error' && $message !== null) {
            $args['mj_todo_error'] = rawurlencode($message);
        }

        $target = add_query_arg($args, admin_url('admin.php'));
        wp_safe_redirect($target);
        exit;
    }

    private static function redirectWithError(string $message): void
    {
        self::redirectWithMessage('error', $message);
    }
}
