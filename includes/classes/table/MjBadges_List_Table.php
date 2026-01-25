<?php

namespace Mj\Member\Classes\Table;

use Mj\Member\Admin\Page\BadgesPage;
use Mj\Member\Classes\Crud\MjBadges;
use WP_List_Table;

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('\\WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

final class MjBadges_List_Table extends WP_List_Table
{
    private const PER_PAGE = 20;

    public function __construct()
    {
        parent::__construct(array(
            'singular' => 'mj_badge',
            'plural'   => 'mj_badges',
            'ajax'     => false,
        ));
    }

    public function get_columns()
    {
        return array(
            'cb'            => '<input type="checkbox" />',
            'image'         => __('Image', 'mj-member'),
            'label'         => __('Nom', 'mj-member'),
            'summary'       => __('Résumé', 'mj-member'),
            'status'        => __('Statut', 'mj-member'),
            'display_order' => __('Ordre', 'mj-member'),
            'updated_at'    => __('Mis à jour', 'mj-member'),
        );
    }

    public function get_sortable_columns()
    {
        return array(
            'label'         => array('label', true),
            'status'        => array('status', false),
            'display_order' => array('display_order', true),
            'updated_at'    => array('updated_at', true),
        );
    }

    public function get_bulk_actions()
    {
        return array(
            'activate' => __('Activer', 'mj-member'),
            'archive'  => __('Archiver', 'mj-member'),
            'delete'   => __('Supprimer', 'mj-member'),
        );
    }

    public function no_items()
    {
        esc_html_e('Aucun badge trouvé.', 'mj-member');
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

        $allowedOrderby = array('label', 'status', 'display_order', 'updated_at', 'created_at', 'id');
        $orderbyRequested = isset($_REQUEST['orderby']) ? sanitize_key(wp_unslash((string) $_REQUEST['orderby'])) : 'display_order';
        $orderby = in_array($orderbyRequested, $allowedOrderby, true) ? $orderbyRequested : 'display_order';

        $orderRequested = isset($_REQUEST['order']) ? strtoupper(sanitize_text_field(wp_unslash((string) $_REQUEST['order']))) : 'ASC';
        $order = $orderRequested === 'DESC' ? 'DESC' : 'ASC';

        $args = array(
            'search' => $search,
            'orderby' => $orderby,
            'order' => $order,
            'limit' => $perPage,
            'offset' => $offset,
        );

        $items = MjBadges::get_all($args);
        if (!is_array($items)) {
            $items = array();
        }

        $this->items = $items;

        $countArgs = array();
        if ($search !== '') {
            $countArgs['search'] = $search;
        }

        $totalItems = (int) MjBadges::count($countArgs);

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

        return '<input type="checkbox" name="badge[]" value="' . esc_attr((string) $id) . '" />';
    }

    protected function column_label($item)
    {
        $id = isset($item['id']) ? (int) $item['id'] : 0;
        $label = isset($item['label']) ? (string) $item['label'] : '';
        if ($label === '') {
            $label = $id > 0 ? sprintf(__('Badge #%d', 'mj-member'), $id) : __('Badge', 'mj-member');
        }

        $editUrl = add_query_arg(
            array(
                'page'   => BadgesPage::slug(),
                'action' => 'edit',
                'id'     => $id,
            ),
            admin_url('admin.php')
        );

        $assignUrl = add_query_arg(
            array(
                'page'   => BadgesPage::slug(),
                'action' => 'assign',
                'badge_id' => $id,
            ),
            admin_url('admin.php')
        );

        $assignedUrl = add_query_arg(
            array(
                'page'   => BadgesPage::slug(),
                'action' => 'assigned',
                'badge_id' => $id,
            ),
            admin_url('admin.php')
        );

        $deleteUrl = wp_nonce_url(
            add_query_arg(
                array(
                    'action'   => 'delete_badge',
                    'badge_id' => $id,
                ),
                admin_url('admin-post.php')
            ),
            BadgesPage::deleteNonceAction($id)
        );

        $actions = array(
            'edit' => '<a href="' . esc_url($editUrl) . '">' . esc_html__('Modifier', 'mj-member') . '</a>',
            'assign' => '<a href="' . esc_url($assignUrl) . '">' . esc_html__('Attribuer', 'mj-member') . '</a>',
            'assigned' => '<a href="' . esc_url($assignedUrl) . '">' . esc_html__('Membres attribués', 'mj-member') . '</a>',
            'delete' => '<a href="' . esc_url($deleteUrl) . '" class="submitdelete" onclick="return confirm(\'' . esc_js(__('Supprimer ce badge ?', 'mj-member')) . '\');">' . esc_html__('Supprimer', 'mj-member') . '</a>',
        );

        $output = '<strong><a href="' . esc_url($editUrl) . '">' . esc_html($label) . '</a></strong>';

        return $output . $this->row_actions($actions);
    }

    protected function column_summary($item)
    {
        $summary = isset($item['summary']) ? (string) $item['summary'] : '';
        if ($summary === '') {
            return '<span class="description">' . esc_html__('—', 'mj-member') . '</span>';
        }

        return esc_html(wp_trim_words($summary, 16, '…'));
    }

    protected function column_status($item)
    {
        $status = isset($item['status']) ? (string) $item['status'] : MjBadges::STATUS_ACTIVE;
        $labels = MjBadges::get_status_labels();
        $label = $labels[$status] ?? ucfirst($status);
        $class = $status === MjBadges::STATUS_ACTIVE ? 'status-active' : 'status-archived';

        return '<span class="mj-badge-status ' . esc_attr($class) . '">' . esc_html($label) . '</span>';
    }

    protected function column_display_order($item)
    {
        return isset($item['display_order']) ? (int) $item['display_order'] : 0;
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

    protected function column_image($item)
    {
        $badgeId = isset($item['id']) ? (int) $item['id'] : 0;
        $badgeLabel = isset($item['label']) ? (string) $item['label'] : '';

        $editUrl = add_query_arg(
            array(
                'page'   => BadgesPage::slug(),
                'action' => 'edit',
                'id'     => $badgeId,
            ),
            admin_url('admin.php')
        );

        $imageId = isset($item['image_id']) ? (int) $item['image_id'] : 0;
        if ($imageId > 0) {
            $imageHtml = wp_get_attachment_image(
                $imageId,
                array(64, 64),
                false,
                array(
                    'class' => 'mj-badge-list-image',
                    'style' => 'width:48px;height:48px;object-fit:cover;border-radius:4px;',
                )
            );

            if (!empty($imageHtml)) {
                return '<a href="' . esc_url($editUrl) . '" class="mj-badge-thumbnail">' . wp_kses_post($imageHtml) . '</a>';
            }
        }

        $icon = isset($item['icon']) ? sanitize_key((string) $item['icon']) : '';
        if ($icon !== '') {
            return '<a href="' . esc_url($editUrl) . '" class="mj-badge-thumbnail"><span class="dashicons dashicons-' . esc_attr($icon) . '"></span></a>';
        }

        if ($badgeLabel === '' && $badgeId > 0) {
            $badgeLabel = sprintf(__('Badge #%d', 'mj-member'), $badgeId);
        }

        return '<a href="' . esc_url($editUrl) . '" class="mj-badge-thumbnail"><span class="description">' . esc_html__('—', 'mj-member') . '</span></a>';
    }

    public function process_bulk_action()
    {
        $action = $this->current_action();
        if (!$action) {
            return;
        }

        check_admin_referer('bulk-' . $this->_args['plural']);

        $rawIds = isset($_REQUEST['badge']) ? wp_unslash($_REQUEST['badge']) : array();
        $ids = self::sanitizeIdList($rawIds);
        if (empty($ids)) {
            return;
        }

        $errors = array();
        foreach ($ids as $id) {
            if ($action === 'delete') {
                $result = MjBadges::delete($id);
            } else {
                $status = $action === 'archive' ? MjBadges::STATUS_ARCHIVED : MjBadges::STATUS_ACTIVE;
                $result = MjBadges::update($id, array('status' => $status));
            }

            if (is_wp_error($result)) {
                $errors[] = $result->get_error_message();
            }
        }

        $args = array('page' => BadgesPage::slug());
        if (empty($errors)) {
            $args['mj_badges_notice'] = $action === 'delete' ? 'deleted' : 'updated';
        } else {
            $args['mj_badges_notice'] = 'error';
            $args['mj_badges_error'] = rawurlencode(implode(' ', array_unique($errors)));
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
