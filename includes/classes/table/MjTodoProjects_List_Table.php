<?php

namespace Mj\Member\Classes\Table;

use Mj\Member\Admin\Page\TodoProjectsPage;
use Mj\Member\Classes\Crud\MjMemberHours;
use Mj\Member\Classes\Crud\MjTodoProjects;
use WP_List_Table;

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('\\WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

final class MjTodoProjects_List_Table extends WP_List_Table
{
    private const PER_PAGE = 20;

    /** @var array<int,array{total_minutes:int,entries:int}> Hours indexed by project_id */
    private array $hoursByProject = array();

    public function __construct()
    {
        parent::__construct(array(
            'singular' => 'mj_todo_project',
            'plural'   => 'mj_todo_projects',
            'ajax'     => false,
        ));
    }

    public function get_columns()
    {
        return array(
            'cb'          => '<input type="checkbox" />',
            'title'       => __('Titre', 'mj-member'),
            'slug'        => __('Identifiant', 'mj-member'),
            'color'       => __('Couleur', 'mj-member'),
            'hours'       => __('Heures', 'mj-member'),
            'description' => __('Description', 'mj-member'),
            'updated_at'  => __('Mis à jour', 'mj-member'),
        );
    }

    public function get_sortable_columns()
    {
        return array(
            'title'      => array('title', true),
            'updated_at' => array('updated_at', true),
            'created_at' => array('created_at', false),
        );
    }

    public function get_bulk_actions()
    {
        return array(
            'delete' => __('Supprimer', 'mj-member'),
        );
    }

    public function no_items()
    {
        esc_html_e('Aucun projet trouvé.', 'mj-member');
    }

    public function prepare_items()
    {
        $columns  = $this->get_columns();
        $hidden   = array();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array($columns, $hidden, $sortable);

        $search = isset($_REQUEST['s']) ? sanitize_text_field(wp_unslash((string) $_REQUEST['s'])) : '';

        $perPage = self::PER_PAGE;
        $currentPage = max(1, $this->get_pagenum());
        $offset = ($currentPage - 1) * $perPage;

        $allowedOrderby = array('title', 'updated_at', 'created_at', 'id');
        $orderbyRequested = isset($_REQUEST['orderby']) ? sanitize_key(wp_unslash((string) $_REQUEST['orderby'])) : 'title';
        $orderby = in_array($orderbyRequested, $allowedOrderby, true) ? $orderbyRequested : 'title';

        $orderRequested = isset($_REQUEST['order']) ? strtoupper(sanitize_text_field(wp_unslash((string) $_REQUEST['order']))) : 'ASC';
        $order = $orderRequested === 'DESC' ? 'DESC' : 'ASC';

        $args = array(
            'search' => $search,
            'orderby' => $orderby,
            'order' => $order,
            'limit' => $perPage,
            'offset' => $offset,
        );

        $items = MjTodoProjects::get_all($args);
        if (!is_array($items)) {
            $items = array();
        }

        $this->items = $items;

        // Load hours totals per project.
        $this->hoursByProject = array();
        $projectTotals = MjMemberHours::get_project_totals();
        foreach ($projectTotals as $row) {
            $pid = isset($row['project_id']) ? (int) $row['project_id'] : 0;
            if ($pid > 0) {
                $this->hoursByProject[$pid] = array(
                    'total_minutes' => (int) ($row['total_minutes'] ?? 0),
                    'entries'       => (int) ($row['entries'] ?? 0),
                );
            }
        }

        $countArgs = array();
        if ($search !== '') {
            $countArgs['search'] = $search;
        }
        $totalItems = MjTodoProjects::count($countArgs);

        $this->set_pagination_args(array(
            'total_items' => $totalItems,
            'per_page'    => $perPage,
            'total_pages' => (int) ceil($totalItems / max(1, $perPage)),
        ));
    }

    public function column_cb($item)
    {
        $id = isset($item['id']) ? (int) $item['id'] : 0;
        if ($id <= 0) {
            return '';
        }

        return '<input type="checkbox" name="project[]" value="' . esc_attr((string) $id) . '" />';
    }

    protected function column_title($item)
    {
        $id = isset($item['id']) ? (int) $item['id'] : 0;
        $title = isset($item['title']) ? (string) $item['title'] : '';
        if ($title === '') {
            $title = sprintf(__('Projet #%d', 'mj-member'), $id);
        }

        $editUrl = add_query_arg(
            array(
                'page'   => TodoProjectsPage::slug(),
                'action' => 'edit',
                'id'     => $id,
            ),
            admin_url('admin.php')
        );

        $actions = array(
            'edit' => '<a href="' . esc_url($editUrl) . '">' . esc_html__('Modifier', 'mj-member') . '</a>',
        );

        if ($id > 0) {
            $deleteUrl = wp_nonce_url(
                add_query_arg(
                    array(
                        'page'   => TodoProjectsPage::slug(),
                        'action' => 'delete',
                        'id'     => $id,
                    ),
                    admin_url('admin.php')
                ),
                TodoProjectsPage::deleteNonceAction($id)
            );
            $actions['delete'] = '<a href="' . esc_url($deleteUrl) . '" class="submitdelete" onclick="return confirm(\'' . esc_js(__('Supprimer ce projet ?', 'mj-member')) . '\');">' . esc_html__('Supprimer', 'mj-member') . '</a>';
        }

        $output = '<strong><a href="' . esc_url($editUrl) . '">' . esc_html($title) . '</a></strong>';

        return $output . $this->row_actions($actions);
    }

    protected function column_slug($item)
    {
        $slug = isset($item['slug']) ? (string) $item['slug'] : '';
        if ($slug === '') {
            return '<span class="description">' . esc_html__('—', 'mj-member') . '</span>';
        }

        return esc_html($slug);
    }

    protected function column_color($item)
    {
        $color = isset($item['color']) ? (string) $item['color'] : '';
        if ($color === '') {
            return '<span class="description">' . esc_html__('—', 'mj-member') . '</span>';
        }

        $swatch = '<span class="mj-project-color" style="display:inline-block;width:18px;height:18px;border-radius:50%;margin-right:6px;vertical-align:middle;background:' . esc_attr($color) . ';"></span>';

        return $swatch . '<code>' . esc_html($color) . '</code>';
    }

    protected function column_hours($item)
    {
        $id = isset($item['id']) ? (int) $item['id'] : 0;
        $data = $id > 0 && isset($this->hoursByProject[$id]) ? $this->hoursByProject[$id] : null;

        if (!$data || $data['total_minutes'] <= 0) {
            return '<span class="description">' . esc_html__('—', 'mj-member') . '</span>';
        }

        $hours   = floor($data['total_minutes'] / 60);
        $minutes = $data['total_minutes'] % 60;
        $formatted = $minutes > 0
            ? sprintf('%d h %02d min', $hours, $minutes)
            : sprintf('%d h', $hours);

        $entries = $data['entries'];
        $label   = sprintf(
            /* translators: %d = number of entries */
            _n('%d entrée', '%d entrées', $entries, 'mj-member'),
            $entries
        );

        $title = isset($item['title']) ? esc_attr((string) $item['title']) : '';

        return '<strong>' . esc_html($formatted) . '</strong><br>'
            . '<button type="button" class="button-link mj-project-hours-detail" data-project-id="' . esc_attr((string) $id) . '" data-project-title="' . $title . '">'
            . esc_html($label) . '</button>';
    }

    protected function column_description($item)
    {
        $description = isset($item['description']) ? (string) $item['description'] : '';
        if ($description === '') {
            return '<span class="description">' . esc_html__('—', 'mj-member') . '</span>';
        }

        return esc_html(wp_trim_words($description, 20, '…'));
    }

    protected function column_updated_at($item)
    {
        $updated = isset($item['updated_at']) ? (string) $item['updated_at'] : '';
        if ($updated === '') {
            $updated = isset($item['created_at']) ? (string) $item['created_at'] : '';
        }

        if ($updated === '') {
            return '<span class="description">' . esc_html__('—', 'mj-member') . '</span>';
        }

        $timestamp = strtotime($updated);
        if ($timestamp === false) {
            return esc_html($updated);
        }

        $format = get_option('date_format', 'Y-m-d') . ' ' . get_option('time_format', 'H:i');
        return esc_html(wp_date($format, $timestamp));
    }

    public function process_bulk_action()
    {
        if ($this->current_action() !== 'delete') {
            return;
        }

        check_admin_referer('bulk-' . $this->_args['plural']);

        $rawIds = isset($_REQUEST['project']) ? wp_unslash($_REQUEST['project']) : array();
        $ids = self::sanitizeIdList($rawIds);
        if (empty($ids)) {
            return;
        }

        $errors = array();
        foreach ($ids as $id) {
            $result = MjTodoProjects::delete($id);
            if (is_wp_error($result)) {
                $errors[] = $result->get_error_message();
            }
        }

        $args = array('page' => TodoProjectsPage::slug());
        if (empty($errors)) {
            $args['mj_todo_notice'] = 'project_deleted';
        } else {
            $args['mj_todo_notice'] = 'error';
            $args['mj_todo_error'] = rawurlencode(implode(' ', array_unique($errors)));
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
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
