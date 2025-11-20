<?php

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

if (!class_exists('MjMembers_CRUD')) {
    require_once __DIR__ . '/MjMembers_CRUD.php';
}

class MjEmailLogs_List_Table extends WP_List_Table {
    private const PER_PAGE = 20;

    /** @var array<int, object> */
    private $memberCache = array();

    /** @var array<string, int> */
    private $statusCounts = array();

    /** @var string */
    private $statusFilter = '';

    /** @var string */
    private $modeFilter = '';

    /** @var string */
    private $sourceFilter = '';

    public function __construct() {
        parent::__construct(array(
            'singular' => 'mj_email_log',
            'plural'   => 'mj_email_logs',
            'ajax'     => false,
        ));
    }

    public function get_columns() {
        return array(
            'created_at' => __('Date', 'mj-member'),
            'subject'    => __('Sujet', 'mj-member'),
            'recipients' => __('Destinataires', 'mj-member'),
            'status'     => __('Statut', 'mj-member'),
            'mode'       => __('Mode', 'mj-member'),
            'member'     => __('Membre', 'mj-member'),
            'template'   => __('Template', 'mj-member'),
            'source'     => __('Source', 'mj-member'),
        );
    }

    public function get_sortable_columns() {
        return array(
            'created_at' => array('created_at', true),
            'subject'    => array('subject', false),
            'status'     => array('status', false),
            'source'     => array('source', false),
        );
    }

    public function get_views() {
        $base_url = remove_query_arg(array('paged', 'log_status'));

        $views = array();
        $total = array_sum($this->statusCounts);
        $current = $this->statusFilter;

        $views['all'] = sprintf(
            '<a href="%1$s" class="%2$s">%3$s <span class="count">(%4$d)</span></a>',
            esc_url($base_url),
            $current === '' ? 'current' : '',
            esc_html__('Tous', 'mj-member'),
            (int) $total
        );

        $statuses = array(
            'sent'      => __('Envoyé', 'mj-member'),
            'failed'    => __('Échec', 'mj-member'),
            'simulated' => __('Simulé', 'mj-member'),
            'skipped'   => __('Ignoré', 'mj-member'),
        );

        foreach ($statuses as $status_key => $label) {
            $count = isset($this->statusCounts[$status_key]) ? (int) $this->statusCounts[$status_key] : 0;
            if ($count === 0 && $total > 0) {
                continue;
            }

            $url = add_query_arg('log_status', $status_key, $base_url);
            $views[$status_key] = sprintf(
                '<a href="%1$s" class="%2$s">%3$s <span class="count">(%4$d)</span></a>',
                esc_url($url),
                $current === $status_key ? 'current' : '',
                esc_html($label),
                (int) $count
            );
        }

        return $views;
    }

    public function no_items() {
        esc_html_e('Aucun email trouvé.', 'mj-member');
    }

    public function prepare_items() {
        global $wpdb;

        $table = $wpdb->prefix . 'mj_email_logs';

        $columns  = $this->get_columns();
        $hidden   = array();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array($columns, $hidden, $sortable);

        $this->statusFilter = isset($_REQUEST['log_status']) ? sanitize_text_field(wp_unslash($_REQUEST['log_status'])) : '';
        $this->modeFilter = isset($_REQUEST['log_mode']) ? sanitize_text_field(wp_unslash($_REQUEST['log_mode'])) : '';
        $this->sourceFilter = isset($_REQUEST['log_source']) ? sanitize_text_field(wp_unslash($_REQUEST['log_source'])) : '';
        $search = isset($_REQUEST['s']) ? sanitize_text_field(wp_unslash($_REQUEST['s'])) : '';

        $per_page = self::PER_PAGE;
        $current_page = max(1, $this->get_pagenum());
        $offset = ($current_page - 1) * $per_page;

        $allowed_orderby = array(
            'created_at' => 'created_at',
            'subject'    => 'subject',
            'status'     => 'status',
            'source'     => 'source',
        );
        $orderby_req = isset($_REQUEST['orderby']) ? sanitize_text_field(wp_unslash($_REQUEST['orderby'])) : 'created_at';
        $orderby = isset($allowed_orderby[$orderby_req]) ? $allowed_orderby[$orderby_req] : 'created_at';

        $order_req = isset($_REQUEST['order']) ? sanitize_text_field(wp_unslash($_REQUEST['order'])) : 'DESC';
        $order = strtoupper($order_req) === 'ASC' ? 'ASC' : 'DESC';

        $where_clauses = array();
        $where_params = array();

        if ($this->statusFilter !== '' && in_array($this->statusFilter, array('sent', 'failed', 'simulated', 'skipped'), true)) {
            $where_clauses[] = 'status = %s';
            $where_params[] = $this->statusFilter;
        }

        if ($this->modeFilter === 'test') {
            $where_clauses[] = 'is_test_mode = 1';
        } elseif ($this->modeFilter === 'live') {
            $where_clauses[] = 'is_test_mode = 0';
        }

        if ($this->sourceFilter !== '') {
            $where_clauses[] = 'source = %s';
            $where_params[] = $this->sourceFilter;
        }

        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where_clauses[] = '(subject LIKE %s OR template_slug LIKE %s OR recipients LIKE %s OR error_message LIKE %s OR source LIKE %s)';
            $where_params = array_merge($where_params, array($like, $like, $like, $like, $like));
        }

        $where_sql = '';
        if (!empty($where_clauses)) {
            $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
        }

        $count_sql = "SELECT COUNT(*) FROM $table $where_sql";
        $count_query = $wpdb->prepare($count_sql, $where_params);
        $total_items = (int) $wpdb->get_var($count_query);

        $data_sql = "SELECT * FROM $table $where_sql ORDER BY $orderby $order LIMIT %d OFFSET %d";
        $data_params = array_merge($where_params, array($per_page, $offset));
        $data_query = $wpdb->prepare($data_sql, $data_params);
        $rows = $wpdb->get_results($data_query);

        $this->items = array();
        $member_ids = array();

        if (!empty($rows)) {
            foreach ($rows as $row) {
                $member_id = isset($row->member_id) ? (int) $row->member_id : 0;
                if ($member_id > 0) {
                    $member_ids[$member_id] = $member_id;
                }

                $recipients = array();
                $decoded_recipients = json_decode((string) $row->recipients, true);
                if (is_array($decoded_recipients)) {
                    foreach ($decoded_recipients as $recipient) {
                        $recipient = trim((string) $recipient);
                        if ($recipient !== '') {
                            $recipients[] = $recipient;
                        }
                    }
                }

                $this->items[] = array(
                    'id'            => (int) $row->id,
                    'created_at'    => (string) $row->created_at,
                    'subject'       => (string) $row->subject,
                    'recipients'    => $recipients,
                    'status'        => (string) $row->status,
                    'is_test_mode'  => (int) $row->is_test_mode === 1,
                    'member_id'     => $member_id,
                    'template_id'   => isset($row->template_id) ? (int) $row->template_id : 0,
                    'template_slug' => (string) $row->template_slug,
                    'source'        => (string) $row->source,
                    'error_message' => (string) $row->error_message,
                );
            }
        }

        if (!empty($member_ids)) {
            foreach ($member_ids as $member_id) {
                $member = MjMembers_CRUD::getById($member_id);
                if ($member) {
                    $this->memberCache[$member_id] = $member;
                }
            }
        }

        $this->statusCounts = $this->resolve_status_counts();

        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ($per_page > 0) ? (int) ceil($total_items / $per_page) : 1,
        ));
    }

    private function resolve_status_counts() {
        global $wpdb;

        $table = $wpdb->prefix . 'mj_email_logs';
        $results = $wpdb->get_results("SELECT status, COUNT(*) AS total FROM $table GROUP BY status", OBJECT_K);

        $counts = array(
            'sent'      => 0,
            'failed'    => 0,
            'simulated' => 0,
            'skipped'   => 0,
        );

        if ($results) {
            foreach ($results as $status => $data) {
                $counts[$status] = (int) $data->total;
            }
        }

        return $counts;
    }

    protected function column_default($item, $column_name) {
        switch ($column_name) {
            case 'created_at':
                $format = get_option('date_format') . ' ' . get_option('time_format');
                $display = mysql2date($format, $item['created_at'], true);
                return esc_html($display);

            case 'status':
                $label_map = array(
                    'sent'      => __('Envoyé', 'mj-member'),
                    'failed'    => __('Échec', 'mj-member'),
                    'simulated' => __('Simulé', 'mj-member'),
                    'skipped'   => __('Ignoré', 'mj-member'),
                );
                $status = strtolower($item['status']);
                $label = isset($label_map[$status]) ? $label_map[$status] : ucfirst($status);
                $class = 'mj-email-log-pill mj-email-log-pill--' . preg_replace('/[^a-z0-9\-]/', '', $status);
                return '<span class="' . esc_attr($class) . '">' . esc_html($label) . '</span>';

            case 'mode':
                return $item['is_test_mode']
                    ? '<span class="mj-email-log-pill mj-email-log-pill--test">' . esc_html__('Test', 'mj-member') . '</span>'
                    : esc_html__('Normal', 'mj-member');

            case 'member':
                return $this->render_member_column($item);

            case 'template':
                if ($item['template_slug'] !== '') {
                    return esc_html($item['template_slug']);
                }
                if ($item['template_id'] > 0) {
                    return sprintf(__('Template #%d', 'mj-member'), (int) $item['template_id']);
                }
                return '<span class="description">' . esc_html__('N/A', 'mj-member') . '</span>';

            case 'source':
                return $item['source'] !== '' ? esc_html($item['source']) : '<span class="description">' . esc_html__('N/A', 'mj-member') . '</span>';
        }

        return isset($item[$column_name]) ? esc_html((string) $item[$column_name]) : '';
    }

    protected function column_subject($item) {
        $subject = $item['subject'] !== '' ? $item['subject'] : __('(Sans sujet)', 'mj-member');
        $subject_html = esc_html($subject);

        if ($item['error_message'] !== '') {
            $subject_html .= ' <span class="mj-email-log-warning">' . esc_html__('⚠', 'mj-member') . '</span>';
        }

        $view_url = add_query_arg(
            array(
                'page'   => 'mj_email_logs',
                'log_id' => $item['id'],
            ),
            admin_url('admin.php')
        );

        $actions = array(
            'view' => '<a href="' . esc_url($view_url) . '">' . esc_html__('Consulter', 'mj-member') . '</a>',
        );

        return $subject_html . $this->row_actions($actions);
    }

    protected function column_recipients($item) {
        if (empty($item['recipients'])) {
            return '<span class="description">' . esc_html__('Aucun', 'mj-member') . '</span>';
        }

        $list = array();
        foreach ($item['recipients'] as $recipient) {
            $list[] = '<span class="mj-email-log-recipient">' . esc_html($recipient) . '</span>';
        }

        return implode('<br>', $list);
    }

    private function render_member_column($item) {
        $member_id = (int) $item['member_id'];
        if ($member_id <= 0) {
            return '<span class="description">' . esc_html__('N/A', 'mj-member') . '</span>';
        }

        if (!isset($this->memberCache[$member_id])) {
            return '<span class="description">' . esc_html__('N/A', 'mj-member') . '</span>';
        }

        $member = $this->memberCache[$member_id];
        $name = trim($member->first_name . ' ' . $member->last_name);
        if ($name === '') {
            $name = sprintf(__('Membre #%d', 'mj-member'), $member_id);
        }

        $url = add_query_arg(
            array(
                'page'   => 'mj_members',
                'action' => 'edit',
                'id'     => $member_id,
            ),
            admin_url('admin.php')
        );

        $email = isset($member->email) && $member->email !== '' ? '<br><a href="mailto:' . esc_attr($member->email) . '">' . esc_html($member->email) . '</a>' : '';

        return '<a href="' . esc_url($url) . '">' . esc_html($name) . '</a>' . $email;
    }

    protected function extra_tablenav($which) {
        if ($which !== 'top') {
            return;
        }

        $current_status = $this->statusFilter;
        $current_mode = $this->modeFilter;
        $current_source = $this->sourceFilter;

        echo '<div class="alignleft actions">';

        echo '<label class="screen-reader-text" for="mj-email-log-status">' . esc_html__('Filtrer par statut', 'mj-member') . '</label>';
        echo '<select name="log_status" id="mj-email-log-status">';
        echo '<option value="">' . esc_html__('Tous les statuts', 'mj-member') . '</option>';
        $statuses = array(
            'sent'      => __('Envoyé', 'mj-member'),
            'failed'    => __('Échec', 'mj-member'),
            'simulated' => __('Simulé', 'mj-member'),
            'skipped'   => __('Ignoré', 'mj-member'),
        );
        foreach ($statuses as $key => $label) {
            printf('<option value="%1$s" %2$s>%3$s</option>', esc_attr($key), selected($current_status, $key, false), esc_html($label));
        }
        echo '</select>';

        echo '<label class="screen-reader-text" for="mj-email-log-mode">' . esc_html__('Filtrer par mode', 'mj-member') . '</label>';
        echo '<select name="log_mode" id="mj-email-log-mode">';
        echo '<option value="">' . esc_html__('Tous les modes', 'mj-member') . '</option>';
        echo '<option value="live" ' . selected($current_mode, 'live', false) . '>' . esc_html__('Normal', 'mj-member') . '</option>';
        echo '<option value="test" ' . selected($current_mode, 'test', false) . '>' . esc_html__('Test', 'mj-member') . '</option>';
        echo '</select>';

        echo '<label class="screen-reader-text" for="mj-email-log-source">' . esc_html__('Filtrer par source', 'mj-member') . '</label>';
        echo '<input type="text" name="log_source" id="mj-email-log-source" value="' . esc_attr($current_source) . '" placeholder="' . esc_attr__('Source', 'mj-member') . '" />';

        submit_button(__('Filtrer', 'mj-member'), '', 'filter_action', false);
        echo '</div>';
    }
}
