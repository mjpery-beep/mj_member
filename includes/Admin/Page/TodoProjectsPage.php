<?php

namespace Mj\Member\Admin\Page;

use Mj\Member\Admin\RequestGuard;
use Mj\Member\Classes\Crud\MjTodoProjects;
use Mj\Member\Classes\Table\MjTodoProjects_List_Table;
use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

final class TodoProjectsPage
{
    public static function slug(): string
    {
        return 'mj_member_projects';
    }

    public static function registerHooks(?string $hookSuffix): void
    {
        if ($hookSuffix === null || $hookSuffix === '') {
            return;
        }

        add_action('load-' . $hookSuffix, array(__CLASS__, 'handleLoad'));
    }

    public static function deleteNonceAction(int $projectId): string
    {
        return 'mj_member_todo_project_delete_' . $projectId;
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
        );

        if ($mode === 'edit') {
            $projectId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
            $project = $projectId > 0 ? MjTodoProjects::get($projectId) : null;
            if (!$project) {
                self::redirectWithError(__('Projet introuvable.', 'mj-member'));
            }
            $view['project'] = $project;
        } else {
            $view['project'] = array(
                'id' => 0,
                'title' => '',
                'slug' => '',
                'description' => '',
                'color' => '',
            );
        }

        if ($mode === 'list') {
            $view['table'] = new MjTodoProjects_List_Table();
        } else {
            $view['table'] = null;
        }

        $template = Config::path() . 'includes/templates/admin/todo-projects-page.php';
        if (is_readable($template)) {
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

        $projectId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        $nonce = isset($_GET['_wpnonce']) ? (string) $_GET['_wpnonce'] : '';

        if ($projectId <= 0 || !wp_verify_nonce($nonce, self::deleteNonceAction($projectId))) {
            wp_die(esc_html__('Action non autorisée.', 'mj-member'));
        }

        $result = MjTodoProjects::delete($projectId);
        if (is_wp_error($result)) {
            self::redirectWithError($result->get_error_message());
        }

        self::redirectWithMessage('project_deleted');
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
