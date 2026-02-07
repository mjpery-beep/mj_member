<?php

namespace Mj\Member\Classes\Table;

use Mj\Member\Admin\Page\NotificationsPage;
use Mj\Member\Classes\Crud\MjMembers;
use Mj\Member\Classes\Crud\MjNotifications;
use Mj\Member\Classes\Crud\MjNotificationRecipients;
use WP_List_Table;

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('\\WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

final class MjNotifications_List_Table extends WP_List_Table
{
    private const PER_PAGE = 25;

    /** @var array<string,string> */
    private $typeLabels = array();

    /** @var array<string,string> */
    private $statusLabels = array();

    /** @var string */
    private $statusFilter = '';

    /** @var string */
    private $typeFilter = '';

    /** @var array<string,int> */
    private $statusCounts = array();

    /**
     * @param array<string,mixed> $args
     */
    public function __construct(array $args = array())
    {
        parent::__construct(array(
            'singular' => 'mj_notification',
            'plural'   => 'mj_notifications',
            'ajax'     => false,
        ));

        if (isset($args['type_labels']) && is_array($args['type_labels'])) {
            $this->typeLabels = $args['type_labels'];
        }

        if (isset($args['status_labels']) && is_array($args['status_labels'])) {
            $this->statusLabels = $args['status_labels'];
        } else {
            $this->statusLabels = NotificationsPage::getStatusLabels();
        }
    }

    public function get_columns()
    {
        return array(
            'cb'           => '<input type="checkbox" />',
            'title'        => __('Titre', 'mj-member'),
            'type'         => __('Type', 'mj-member'),
            'status'       => __('Statut', 'mj-member'),
            'priority'     => __('Priorité', 'mj-member'),
            'recipients'   => __('Destinataires', 'mj-member'),
            'created_at'   => __('Créée le', 'mj-member'),
            'expires_at'   => __('Expire le', 'mj-member'),
        );
    }

    public function get_sortable_columns()
    {
        return array(
            'title'      => array('title', false),
            'type'       => array('type', false),
            'status'     => array('status', false),
            'priority'   => array('priority', true),
            'created_at' => array('created_at', true),
            'expires_at' => array('expires_at', false),
        );
    }

    public function get_views()
    {
        $baseUrl = remove_query_arg(array('paged', 'notification_status'));
        $current = $this->statusFilter;

        $total = isset($this->statusCounts['all']) ? (int) $this->statusCounts['all'] : 0;
        $views = array();

        $views['all'] = sprintf(
            '<a href="%1$s" class="%2$s">%3$s <span class="count">(%4$d)</span></a>',
            esc_url($baseUrl),
            $current === '' ? 'current' : '',
            esc_html__('Toutes', 'mj-member'),
            $total
        );

        $statuses = NotificationsPage::getStatuses();
        foreach ($statuses as $status) {
            $count = isset($this->statusCounts[$status]) ? (int) $this->statusCounts[$status] : 0;
            $label = isset($this->statusLabels[$status]) ? $this->statusLabels[$status] : ucfirst($status);
            $url = add_query_arg('notification_status', $status, $baseUrl);
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
            'delete'  => __('Supprimer', 'mj-member'),
            'archive' => __('Archiver', 'mj-member'),
        );
    }

    public function no_items()
    {
        esc_html_e('Aucune notification trouvée.', 'mj-member');
    }

    protected function extra_tablenav($which)
    {
        if ($which !== 'top') {
            return;
        }

        echo '<div class="alignleft actions">';

        // Filtre par type
        if (!empty($this->typeLabels)) {
            $label = __('Filtrer par type', 'mj-member');
            echo '<label class="screen-reader-text" for="mj-notification-type">' . esc_html($label) . '</label>';
            echo '<select name="notification_type" id="mj-notification-type">';
            echo '<option value="">' . esc_html__('Tous les types', 'mj-member') . '</option>';
            foreach ($this->typeLabels as $type => $typeLabel) {
                printf(
                    '<option value="%1$s" %2$s>%3$s</option>',
                    esc_attr($type),
                    selected($this->typeFilter, $type, false),
                    esc_html($typeLabel)
                );
            }
            echo '</select>';
        }

        submit_button(__('Filtrer', 'mj-member'), '', 'filter_action', false);

        if ($this->typeFilter !== '' || $this->statusFilter !== '') {
            $resetUrl = remove_query_arg(array('notification_type', 'notification_status', 'paged'));
            echo '<a class="button" href="' . esc_url($resetUrl) . '">' . esc_html__('Réinitialiser', 'mj-member') . '</a>';
        }

        echo '</div>';
    }

    public function column_cb($item)
    {
        $id = isset($item->id) ? (int) $item->id : 0;
        if ($id <= 0) {
            return '';
        }

        return '<input type="checkbox" name="notification[]" value="' . esc_attr((string) $id) . '" />';
    }

    protected function column_title($item)
    {
        $id = isset($item->id) ? (int) $item->id : 0;
        $title = isset($item->title) ? (string) $item->title : '';
        if ($title === '') {
            $title = sprintf(__('Notification #%d', 'mj-member'), $id);
        }

        $viewUrl = add_query_arg(
            array(
                'page'   => NotificationsPage::slug(),
                'action' => 'view',
                'id'     => $id,
            ),
            admin_url('admin.php')
        );

        $actions = array();
        $actions['view'] = '<a href="' . esc_url($viewUrl) . '">' . esc_html__('Voir', 'mj-member') . '</a>';

        if ($id > 0) {
            $archiveUrl = wp_nonce_url(
                add_query_arg(
                    array(
                        'page'   => NotificationsPage::slug(),
                        'action' => 'archive',
                        'id'     => $id,
                    ),
                    admin_url('admin.php')
                ),
                'mj_member_notification_archive_' . $id
            );
            $actions['archive'] = '<a href="' . esc_url($archiveUrl) . '">' . esc_html__('Archiver', 'mj-member') . '</a>';

            $deleteUrl = wp_nonce_url(
                add_query_arg(
                    array(
                        'page'   => NotificationsPage::slug(),
                        'action' => 'delete',
                        'id'     => $id,
                    ),
                    admin_url('admin.php')
                ),
                NotificationsPage::deleteNonceAction($id)
            );
            $actions['delete'] = '<a href="' . esc_url($deleteUrl) . '" class="submitdelete" onclick="return confirm(\'' . esc_js(__('Supprimer cette notification ?', 'mj-member')) . '\');">' . esc_html__('Supprimer', 'mj-member') . '</a>';
        }

        $excerpt = isset($item->excerpt) ? (string) $item->excerpt : '';
        if ($excerpt !== '') {
            $excerpt = wp_trim_words($excerpt, 15, '…');
        }

        $output = '<strong><a href="' . esc_url($viewUrl) . '">' . esc_html($title) . '</a></strong>';
        if ($excerpt !== '') {
            $output .= '<p class="description" style="margin:4px 0 0;">' . esc_html($excerpt) . '</p>';
        }

        return $output . $this->row_actions($actions);
    }

    protected function column_type($item)
    {
        $type = isset($item->type) ? (string) $item->type : '';
        if ($type === '') {
            return '<span class="description">' . esc_html__('Inconnu', 'mj-member') . '</span>';
        }

        $label = isset($this->typeLabels[$type]) ? $this->typeLabels[$type] : $type;
        $class = 'mj-notification-type mj-notification-type--' . sanitize_html_class($type);

        return '<span class="' . esc_attr($class) . '">' . esc_html($label) . '</span>';
    }

    protected function column_status($item)
    {
        $status = isset($item->status) ? (string) $item->status : MjNotifications::STATUS_PUBLISHED;
        $label = isset($this->statusLabels[$status]) ? $this->statusLabels[$status] : ucfirst($status);
        
        $colorClass = 'mj-notification-status';
        if ($status === MjNotifications::STATUS_PUBLISHED) {
            $colorClass .= ' mj-notification-status--published';
        } elseif ($status === MjNotifications::STATUS_ARCHIVED) {
            $colorClass .= ' mj-notification-status--archived';
        } elseif ($status === MjNotifications::STATUS_DRAFT) {
            $colorClass .= ' mj-notification-status--draft';
        }

        return '<span class="' . esc_attr($colorClass) . '">' . esc_html($label) . '</span>';
    }

    protected function column_priority($item)
    {
        $priority = isset($item->priority) ? (int) $item->priority : 0;
        
        $label = __('Normal', 'mj-member');
        $class = 'mj-notification-priority';
        
        if ($priority >= 80) {
            $label = __('Urgent', 'mj-member');
            $class .= ' mj-notification-priority--urgent';
        } elseif ($priority >= 50) {
            $label = __('Haute', 'mj-member');
            $class .= ' mj-notification-priority--high';
        } elseif ($priority >= 20) {
            $label = __('Normale', 'mj-member');
            $class .= ' mj-notification-priority--normal';
        } else {
            $label = __('Basse', 'mj-member');
            $class .= ' mj-notification-priority--low';
        }

        return '<span class="' . esc_attr($class) . '">' . esc_html($label) . ' (' . $priority . ')</span>';
    }

    protected function column_recipients($item)
    {
        $id = isset($item->id) ? (int) $item->id : 0;
        if ($id <= 0) {
            return '<span class="description">0</span>';
        }

        $count = MjNotificationRecipients::count(array(
            'notification_ids' => array($id),
        ));

        $viewUrl = add_query_arg(
            array(
                'page'   => NotificationsPage::slug(),
                'action' => 'view',
                'id'     => $id,
            ),
            admin_url('admin.php')
        );

        if ($count === 0) {
            return '<span class="description">0</span>';
        }

        return '<a href="' . esc_url($viewUrl) . '">' . esc_html(sprintf(_n('%d destinataire', '%d destinataires', $count, 'mj-member'), $count)) . '</a>';
    }

    protected function column_created_at($item)
    {
        $created = isset($item->created_at) ? (string) $item->created_at : '';
        if ($created === '') {
            return '<span class="description">' . esc_html__('Inconnu', 'mj-member') . '</span>';
        }

        $timestamp = strtotime($created);
        if ($timestamp === false) {
            return esc_html($created);
        }

        $format = get_option('date_format', 'Y-m-d') . ' ' . get_option('time_format', 'H:i');
        $display = wp_date($format, $timestamp);
        
        $humanTime = human_time_diff($timestamp, current_time('timestamp', 1));

        return sprintf(
            '<span title="%1$s">%2$s</span><br><small class="description">%3$s</small>',
            esc_attr($display !== false ? $display : $created),
            esc_html($display !== false ? $display : $created),
            esc_html(sprintf(__('Il y a %s', 'mj-member'), $humanTime))
        );
    }

    protected function column_expires_at($item)
    {
        $expires = isset($item->expires_at) ? (string) $item->expires_at : '';
        if ($expires === '' || $expires === '0000-00-00 00:00:00') {
            return '<span class="description">' . esc_html__('Jamais', 'mj-member') . '</span>';
        }

        $timestamp = strtotime($expires);
        if ($timestamp === false) {
            return esc_html($expires);
        }

        $now = current_time('timestamp', 1);
        $isExpired = $timestamp < $now;

        $format = get_option('date_format', 'Y-m-d') . ' ' . get_option('time_format', 'H:i');
        $display = wp_date($format, $timestamp);
        
        $class = $isExpired ? 'mj-notification-expired' : '';
        $suffix = $isExpired ? ' <span class="mj-notification-expired-badge">' . esc_html__('(Expirée)', 'mj-member') . '</span>' : '';

        return '<span class="' . esc_attr($class) . '">' . esc_html($display !== false ? $display : $expires) . '</span>' . $suffix;
    }

    protected function column_default($item, $columnName)
    {
        if (isset($item->$columnName)) {
            return esc_html((string) $item->$columnName);
        }

        return '';
    }

    public function prepare_items()
    {
        $columns  = $this->get_columns();
        $hidden   = array();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array($columns, $hidden, $sortable);

        // Les bulk actions sont gérées dans NotificationsPage::handleLoad()

        $this->statusFilter = isset($_REQUEST['notification_status']) ? sanitize_key(wp_unslash((string) $_REQUEST['notification_status'])) : '';
        $this->typeFilter = isset($_REQUEST['notification_type']) ? sanitize_text_field(wp_unslash((string) $_REQUEST['notification_type'])) : '';
        $search = isset($_REQUEST['s']) ? sanitize_text_field(wp_unslash((string) $_REQUEST['s'])) : '';

        $perPage = self::PER_PAGE;
        $currentPage = max(1, $this->get_pagenum());
        $offset = ($currentPage - 1) * $perPage;

        $allowedOrderBy = array('title', 'type', 'status', 'priority', 'created_at', 'expires_at');
        $orderbyRequested = isset($_REQUEST['orderby']) ? sanitize_key(wp_unslash((string) $_REQUEST['orderby'])) : 'created_at';
        $orderby = in_array($orderbyRequested, $allowedOrderBy, true) ? $orderbyRequested : 'created_at';

        $orderRequested = isset($_REQUEST['order']) ? strtoupper(sanitize_text_field(wp_unslash((string) $_REQUEST['order']))) : 'DESC';
        $order = $orderRequested === 'ASC' ? 'ASC' : 'DESC';

        $queryArgs = array(
            'limit'   => $perPage,
            'offset'  => $offset,
            'orderby' => $orderby,
            'order'   => $order,
        );

        if ($search !== '') {
            $queryArgs['search'] = $search;
        }

        $validStatuses = NotificationsPage::getStatuses();
        if ($this->statusFilter !== '' && in_array($this->statusFilter, $validStatuses, true)) {
            $queryArgs['statuses'] = array($this->statusFilter);
        }

        if ($this->typeFilter !== '') {
            $queryArgs['types'] = array($this->typeFilter);
        }

        $items = MjNotifications::get_all($queryArgs);
        if (!is_array($items)) {
            $items = array();
        }

        $this->items = $items;

        $countArgs = array();
        if ($this->statusFilter !== '' && in_array($this->statusFilter, $validStatuses, true)) {
            $countArgs['statuses'] = array($this->statusFilter);
        }
        if ($this->typeFilter !== '') {
            $countArgs['types'] = array($this->typeFilter);
        }
        if ($search !== '') {
            $countArgs['search'] = $search;
        }

        $totalItems = MjNotifications::count($countArgs);
        $this->statusCounts = $this->buildStatusCounts();

        $this->set_pagination_args(array(
            'total_items' => $totalItems,
            'per_page'    => $perPage,
            'total_pages' => ceil($totalItems / $perPage),
        ));
    }

    /**
     * @return array<string,int>
     */
    private function buildStatusCounts(): array
    {
        $counts = array(
            'all' => MjNotifications::count(array()),
        );

        $statuses = NotificationsPage::getStatuses();
        foreach ($statuses as $status) {
            $counts[$status] = MjNotifications::count(array('statuses' => array($status)));
        }

        return $counts;
    }
}
