<?php

namespace Mj\Member\Classes\Table;

use Mj\Member\Admin\Page\TodosPage;
use Mj\Member\Classes\Crud\MjMembers;
use Mj\Member\Classes\Crud\MjTodoProjects;
use Mj\Member\Classes\Crud\MjTodos;
use Mj\Member\Classes\Value\MemberData;
use WP_List_Table;

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('\\WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

final class MjTodos_List_Table extends WP_List_Table
{
    private const PER_PAGE = 20;

    /** @var array<int,array<string,mixed>> */
    private $projects = array();

    /** @var array<int,array<string,string>> */
    private $assignableMembers = array();

    /** @var array<string,string> */
    private $statusLabels = array();

    /** @var array<int,array<string,string>> */
    private $memberCache = array();

    /** @var array<string,int> */
    private $statusCounts = array();

    /** @var string */
    private $statusFilter = '';

    /** @var int */
    private $projectFilter = 0;

    /** @var int */
    private $assigneeFilter = 0;

    /**
     * @param array<string,mixed> $args
     */
    public function __construct(array $args = array())
    {
        parent::__construct(array(
            'singular' => 'mj_todo',
            'plural'   => 'mj_todos',
            'ajax'     => false,
        ));

        if (isset($args['projects']) && is_array($args['projects'])) {
            $this->projects = $args['projects'];
        }

        if (isset($args['assignable_members']) && is_array($args['assignable_members'])) {
            $this->assignableMembers = $args['assignable_members'];
        }

        if (isset($args['status_labels']) && is_array($args['status_labels'])) {
            $this->statusLabels = $args['status_labels'];
        } else {
            $this->statusLabels = self::defaultStatusLabels();
        }
    }

    private static function defaultStatusLabels(): array
    {
        return array(
            MjTodos::STATUS_OPEN => __('À faire', 'mj-member'),
            MjTodos::STATUS_COMPLETED => __('Terminée', 'mj-member'),
            MjTodos::STATUS_ARCHIVED => __('Archivée', 'mj-member'),
        );
    }

    public function get_columns()
    {
        return array(
            'cb'         => '<input type="checkbox" />',
            'title'      => __('Titre', 'mj-member'),
            'project'    => __('Projet', 'mj-member'),
            'assignees'  => __('Assigné à', 'mj-member'),
            'status'     => __('Statut', 'mj-member'),
            'due_date'   => __('Échéance', 'mj-member'),
            'media'      => __('Médias', 'mj-member'),
            'updated_at' => __('Mis à jour', 'mj-member'),
        );
    }

    public function get_sortable_columns()
    {
        return array(
            'title'      => array('title', false),
            'status'     => array('status', false),
            'due_date'   => array('due_date', false),
            'updated_at' => array('updated_at', true),
            'project'    => array('project_id', false),
        );
    }

    public function get_views()
    {
        $baseUrl = remove_query_arg(array('paged', 'todo_status'));
        $current = $this->statusFilter;

        $total = isset($this->statusCounts['all']) ? (int) $this->statusCounts['all'] : 0;
        $views = array();

        $views['all'] = sprintf(
            '<a href="%1$s" class="%2$s">%3$s <span class="count">(%4$d)</span></a>',
            esc_url($baseUrl),
            $current === '' ? 'current' : '',
            esc_html__('Tous', 'mj-member'),
            $total
        );

        foreach (MjTodos::statuses() as $status) {
            $count = isset($this->statusCounts[$status]) ? (int) $this->statusCounts[$status] : 0;
            $label = isset($this->statusLabels[$status]) ? $this->statusLabels[$status] : ucfirst($status);
            $url = add_query_arg('todo_status', $status, $baseUrl);
            $views[$status] = sprintf(
                '<a href="%1$s" class="%2$s">%3$s <span class="count">(%4$d)</span></a>',
                esc_url($url),
                $current === $status ? 'current' : '',
                esc_html($label),
                $count
            );
        }

        return $views;
    }

    public function get_bulk_actions()
    {
        return array(
            'delete' => __('Supprimer', 'mj-member'),
        );
    }

    public function no_items()
    {
        esc_html_e('Aucune tâche trouvée.', 'mj-member');
    }

    protected function extra_tablenav($which)
    {
        if ($which !== 'top') {
            return;
        }

        echo '<div class="alignleft actions">';

        if (!empty($this->projects)) {
            $label = __('Filtrer par projet', 'mj-member');
            echo '<label class="screen-reader-text" for="mj-member-todo-project">' . esc_html($label) . '</label>';
            echo '<select name="todo_project" id="mj-member-todo-project">';
            echo '<option value="">' . esc_html__('Tous les projets', 'mj-member') . '</option>';
            foreach ($this->projects as $projectId => $project) {
                $title = isset($project['title']) ? (string) $project['title'] : '';
                printf(
                    '<option value="%1$d" %2$s>%3$s</option>',
                    (int) $projectId,
                    selected($this->projectFilter, (int) $projectId, false),
                    esc_html($title)
                );
            }
            echo '</select>';
        }

        if (!empty($this->assignableMembers)) {
            $label = __('Filtrer par assigné', 'mj-member');
            echo '<label class="screen-reader-text" for="mj-member-todo-assignee">' . esc_html($label) . '</label>';
            echo '<select name="todo_assignee" id="mj-member-todo-assignee">';
            echo '<option value="">' . esc_html__('Tous les membres', 'mj-member') . '</option>';
            foreach ($this->assignableMembers as $member) {
                $memberId = isset($member['id']) ? (int) $member['id'] : 0;
                if ($memberId <= 0) {
                    continue;
                }
                $labelText = isset($member['label']) ? (string) $member['label'] : '';
                printf(
                    '<option value="%1$d" %2$s>%3$s</option>',
                    $memberId,
                    selected($this->assigneeFilter, $memberId, false),
                    esc_html($labelText)
                );
            }
            echo '</select>';
        }

        submit_button(__('Filtrer', 'mj-member'), '', 'filter_action', false);

        if ($this->projectFilter > 0 || $this->assigneeFilter > 0) {
            $resetUrl = remove_query_arg(array('todo_project', 'todo_assignee', 'paged'));
            echo '<a class="button" href="' . esc_url($resetUrl) . '">' . esc_html__('Réinitialiser', 'mj-member') . '</a>';
        }

        echo '</div>';
    }

    public function column_cb($item)
    {
        $id = isset($item['id']) ? (int) $item['id'] : 0;
        if ($id <= 0) {
            return '';
        }

        return '<input type="checkbox" name="todo[]" value="' . esc_attr((string) $id) . '" />';
    }

    protected function column_title($item)
    {
        $id = isset($item['id']) ? (int) $item['id'] : 0;
        $title = isset($item['title']) ? (string) $item['title'] : '';
        if ($title === '') {
            $title = sprintf(__('Tâche #%d', 'mj-member'), $id);
        }

        $editUrl = add_query_arg(
            array(
                'page'   => TodosPage::slug(),
                'action' => 'edit',
                'id'     => $id,
            ),
            admin_url('admin.php')
        );

        $actions = array();
        $actions['edit'] = '<a href="' . esc_url($editUrl) . '">' . esc_html__('Modifier', 'mj-member') . '</a>';

        if ($id > 0) {
            $deleteUrl = wp_nonce_url(
                add_query_arg(
                    array(
                        'page'   => TodosPage::slug(),
                        'action' => 'delete',
                        'id'     => $id,
                    ),
                    admin_url('admin.php')
                ),
                TodosPage::deleteNonceAction($id)
            );
            $actions['delete'] = '<a href="' . esc_url($deleteUrl) . '" class="submitdelete" onclick="return confirm(\'' . esc_js(__('Supprimer cette tâche ?', 'mj-member')) . '\');">' . esc_html__('Supprimer', 'mj-member') . '</a>';
        }

        $excerpt = isset($item['description']) ? (string) $item['description'] : '';
        if ($excerpt !== '') {
            $excerpt = wp_trim_words($excerpt, 20, '…');
        }

        $output = '<strong><a href="' . esc_url($editUrl) . '">' . esc_html($title) . '</a></strong>';
        if ($excerpt !== '') {
            $output .= '<p class="description" style="margin:4px 0 0;">' . esc_html($excerpt) . '</p>';
        }

        return $output . $this->row_actions($actions);
    }

    protected function column_status($item)
    {
        $status = isset($item['status']) ? (string) $item['status'] : MjTodos::STATUS_OPEN;
        $label = isset($this->statusLabels[$status]) ? $this->statusLabels[$status] : ucfirst($status);
        $class = 'mj-todo-status mj-todo-status--' . sanitize_html_class($status);

        return '<span class="' . esc_attr($class) . '">' . esc_html($label) . '</span>';
    }

    protected function column_project($item)
    {
        $projectId = isset($item['project_id']) ? (int) $item['project_id'] : 0;
        if ($projectId <= 0) {
            return '<span class="description">' . esc_html__('Aucun', 'mj-member') . '</span>';
        }

        if (!isset($this->projects[$projectId])) {
            $projects = MjTodoProjects::get_all(array('include_ids' => array($projectId)));
            if (!empty($projects)) {
                $project = $projects[0];
                $this->projects[$projectId] = array(
                    'id' => $projectId,
                    'title' => (string) ($project['title'] ?? ''),
                    'color' => (string) ($project['color'] ?? ''),
                );
            }
        }

        $project = $this->projects[$projectId] ?? null;
        if (!is_array($project)) {
            return '<span class="description">' . esc_html__('Aucun', 'mj-member') . '</span>';
        }

        $title = isset($project['title']) ? (string) $project['title'] : sprintf(__('Projet #%d', 'mj-member'), $projectId);
        $color = isset($project['color']) ? (string) $project['color'] : '';
        $dot = '';
        if ($color !== '') {
            $dot = '<span class="mj-todo-project-dot" style="background-color:' . esc_attr($color) . ';"></span>';
        }

        return '<span class="mj-todo-project">' . $dot . esc_html($title) . '</span>';
    }

    protected function column_assignees($item)
    {
        return $this->formatAssignees($item);
    }

    protected function column_due_date($item)
    {
        $due = isset($item['due_date']) ? (string) $item['due_date'] : '';
        if ($due === '') {
            return '<span class="description">' . esc_html__('Non définie', 'mj-member') . '</span>';
        }

        $timestamp = strtotime($due);
        if ($timestamp === false) {
            return esc_html($due);
        }

        $display = wp_date(get_option('date_format', 'Y-m-d'), $timestamp);
        return esc_html($display);
    }

    protected function column_media($item)
    {
        $media = isset($item['media']) && is_array($item['media']) ? $item['media'] : array();
        $count = count($media);
        if ($count === 0) {
            return '<span class="description">' . esc_html__('Aucun média', 'mj-member') . '</span>';
        }

        $editUrl = add_query_arg(
            array(
                'page'   => TodosPage::slug(),
                'action' => 'edit',
                'id'     => (int) $item['id'],
                'tab'    => 'media',
            ),
            admin_url('admin.php')
        );

        $label = sprintf(_n('%d média', '%d médias', $count, 'mj-member'), $count);
        return '<a href="' . esc_url($editUrl) . '" class="mj-todo-media-link">' . esc_html($label) . '</a>';
    }

    protected function column_updated_at($item)
    {
        $updated = isset($item['updated_at']) ? (string) $item['updated_at'] : '';
        if ($updated === '') {
            $created = isset($item['created_at']) ? (string) $item['created_at'] : '';
            if ($created === '') {
                return '<span class="description">' . esc_html__('Inconnu', 'mj-member') . '</span>';
            }
            $updated = $created;
        }

        $timestamp = strtotime($updated);
        if ($timestamp === false) {
            return esc_html($updated);
        }

        $format = get_option('date_format', 'Y-m-d') . ' ' . get_option('time_format', 'H:i');
        return esc_html(wp_date($format, $timestamp));
    }

    protected function column_default($item, $columnName)
    {
        if (isset($item[$columnName])) {
            return esc_html((string) $item[$columnName]);
        }

        return '';
    }

    public function prepare_items()
    {
        $columns  = $this->get_columns();
        $hidden   = array();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array($columns, $hidden, $sortable);

        $this->statusFilter = isset($_REQUEST['todo_status']) ? sanitize_key(wp_unslash((string) $_REQUEST['todo_status'])) : '';
        $this->projectFilter = isset($_REQUEST['todo_project']) ? (int) $_REQUEST['todo_project'] : 0;
        $this->assigneeFilter = isset($_REQUEST['todo_assignee']) ? (int) $_REQUEST['todo_assignee'] : 0;
        $search = isset($_REQUEST['s']) ? sanitize_text_field(wp_unslash((string) $_REQUEST['s'])) : '';

        $perPage = self::PER_PAGE;
        $currentPage = max(1, $this->get_pagenum());
        $offset = ($currentPage - 1) * $perPage;

        $allowedOrderBy = array('title', 'status', 'due_date', 'updated_at', 'project_id', 'created_at');
        $orderbyRequested = isset($_REQUEST['orderby']) ? sanitize_key(wp_unslash((string) $_REQUEST['orderby'])) : 'updated_at';
        $orderby = in_array($orderbyRequested, $allowedOrderBy, true) ? $orderbyRequested : 'updated_at';

        $orderRequested = isset($_REQUEST['order']) ? strtoupper(sanitize_text_field(wp_unslash((string) $_REQUEST['order']))) : 'DESC';
        $order = $orderRequested === 'ASC' ? 'ASC' : 'DESC';

        $queryArgs = array(
            'limit'  => $perPage,
            'offset' => $offset,
            'orderby' => $orderby,
            'order'   => $order,
        );

        if ($search !== '') {
            $queryArgs['search'] = $search;
        }

        if ($this->statusFilter !== '' && in_array($this->statusFilter, MjTodos::statuses(), true)) {
            $queryArgs['status'] = $this->statusFilter;
        }

        if ($this->projectFilter > 0) {
            $queryArgs['project_id'] = $this->projectFilter;
        }

        if ($this->assigneeFilter > 0) {
            $queryArgs['assigned_member_id'] = $this->assigneeFilter;
        }

        $items = MjTodos::get_all($queryArgs);
        if (!is_array($items)) {
            $items = array();
        }

        $this->items = $items;

        $countArgs = array();
        if ($this->statusFilter !== '' && in_array($this->statusFilter, MjTodos::statuses(), true)) {
            $countArgs['status'] = $this->statusFilter;
        }
        if ($this->projectFilter > 0) {
            $countArgs['project_id'] = $this->projectFilter;
        }
        if ($this->assigneeFilter > 0) {
            $countArgs['assigned_member_id'] = $this->assigneeFilter;
        }
        if ($search !== '') {
            $countArgs['search'] = $search;
        }

        $totalItems = MjTodos::count($countArgs);
        $this->statusCounts = $this->buildStatusCounts();

        $this->primeCaches($items);

        $this->set_pagination_args(array(
            'total_items' => $totalItems,
            'per_page'    => $perPage,
            'total_pages' => (int) ceil($totalItems / max(1, $perPage)),
        ));
    }

    /**
     * @param array<int,array<string,mixed>> $items
     */
    private function primeCaches(array $items): void
    {
        $projectIds = array();
        $memberIds = array();

        foreach ($items as $item) {
            if (isset($item['project_id'])) {
                $candidate = (int) $item['project_id'];
                if ($candidate > 0) {
                    $projectIds[$candidate] = $candidate;
                }
            }

            if (isset($item['assigned_member_id'])) {
                $memberId = (int) $item['assigned_member_id'];
                if ($memberId > 0) {
                    $memberIds[$memberId] = $memberId;
                }
            }

            if (isset($item['assignees']) && is_array($item['assignees'])) {
                foreach ($item['assignees'] as $assignee) {
                    $candidate = isset($assignee['id']) ? (int) $assignee['id'] : 0;
                    if ($candidate > 0) {
                        $memberIds[$candidate] = $candidate;
                    }
                }
            }
        }

        $projectIds = array_diff(array_values($projectIds), array_keys($this->projects));
        if (!empty($projectIds)) {
            $projects = MjTodoProjects::get_all(array('include_ids' => $projectIds));
            foreach ($projects as $project) {
                $projectId = isset($project['id']) ? (int) $project['id'] : 0;
                if ($projectId <= 0) {
                    continue;
                }
                $this->projects[$projectId] = array(
                    'id' => $projectId,
                    'title' => isset($project['title']) ? (string) $project['title'] : '',
                    'color' => isset($project['color']) ? (string) $project['color'] : '',
                );
            }
        }

        $memberIds = array_diff(array_values($memberIds), array_keys($this->memberCache));
        if (!empty($memberIds) && class_exists(MjMembers::class)) {
            foreach ($memberIds as $memberId) {
                $member = MjMembers::getById($memberId);
                if (!($member instanceof MemberData)) {
                    continue;
                }
                $firstName = (string) $member->get('first_name', '');
                $lastName = (string) $member->get('last_name', '');
                $name = trim($firstName . ' ' . $lastName);
                if ($name === '') {
                    $name = sprintf(__('Membre #%d', 'mj-member'), $memberId);
                }
                $role = (string) $member->get('role', '');
                $this->memberCache[$memberId] = array(
                    'name' => $name,
                    'role' => $role,
                );
            }
        }
    }

    private function formatAssignees(array $item): string
    {
        $display = array();

        $primaryId = isset($item['assigned_member_id']) ? (int) $item['assigned_member_id'] : 0;
        $primaryName = $this->resolveMemberName($primaryId);
        if ($primaryId > 0 && $primaryName !== '') {
            $display[$primaryId] = '<strong>' . esc_html($primaryName) . '</strong>';
        }

        if (isset($item['assignees']) && is_array($item['assignees'])) {
            foreach ($item['assignees'] as $assignee) {
                $memberId = isset($assignee['id']) ? (int) $assignee['id'] : 0;
                if ($memberId <= 0 || isset($display[$memberId])) {
                    continue;
                }
                $display[$memberId] = esc_html($this->resolveMemberName($memberId));
            }
        }

        if (empty($display)) {
            return '<span class="description">' . esc_html__('Non assigné', 'mj-member') . '</span>';
        }

        return implode('<br>', $display);
    }

    private function resolveMemberName(int $memberId): string
    {
        if ($memberId <= 0) {
            return '';
        }

        if (!isset($this->memberCache[$memberId]) && class_exists(MjMembers::class)) {
            $member = MjMembers::getById($memberId);
            if ($member instanceof MemberData) {
                $firstName = (string) $member->get('first_name', '');
                $lastName = (string) $member->get('last_name', '');
                $name = trim($firstName . ' ' . $lastName);
                if ($name === '') {
                    $name = sprintf(__('Membre #%d', 'mj-member'), $memberId);
                }
                $this->memberCache[$memberId] = array(
                    'name' => $name,
                    'role' => (string) $member->get('role', ''),
                );
            }
        }

        return isset($this->memberCache[$memberId]['name']) ? (string) $this->memberCache[$memberId]['name'] : '';
    }

    /**
     * @return array<string,int>
     */
    private function buildStatusCounts(): array
    {
        $counts = array(
            'all' => (int) MjTodos::count(array()),
        );

        foreach (MjTodos::statuses() as $status) {
            $counts[$status] = (int) MjTodos::count(array('status' => $status));
        }

        return $counts;
    }

    public function process_bulk_action()
    {
        if ($this->current_action() !== 'delete') {
            return;
        }

        check_admin_referer('bulk-' . $this->_args['plural']);

        $rawIds = isset($_REQUEST['todo']) ? wp_unslash($_REQUEST['todo']) : array();
        $ids = self::sanitizeIdList($rawIds);
        if (empty($ids)) {
            return;
        }

        $errors = array();
        foreach ($ids as $id) {
            $result = MjTodos::delete($id);
            if (is_wp_error($result)) {
                $errors[] = $result->get_error_message();
            }
        }

        $query = array('page' => TodosPage::slug());
        if (empty($errors)) {
            $query['mj_todo_notice'] = 'todo_deleted';
        } else {
            $query['mj_todo_notice'] = 'error';
            $query['mj_todo_error'] = rawurlencode(implode(' ', array_unique($errors)));
        }

        wp_safe_redirect(add_query_arg($query, admin_url('admin.php')));
        exit;
    }

    /**
     * @param mixed $values
     * @return array<int,int>
     */
    private static function sanitizeIdList($values): array
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
