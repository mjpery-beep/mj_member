<?php

namespace Mj\Member\Classes\Table;

use Mj\Member\Classes\MjTools;
use Mj\Member\Core\Config;
use WP_List_Table;

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('\\WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class MjEvents_List_Table extends WP_List_Table {
    private const DEFAULT_PER_PAGE = 20;

    private const STATUSES = array(
        'actif'     => 'Actif',
        'brouillon' => 'Brouillon',
        'passe'     => 'Passé',
    );

    private const TYPES = array(
        'stage'  => 'Stage',
        'soiree' => 'Soirée',
        'sortie' => 'Sortie',
    );

    /** @var array<string, string> */
    private $activeFilters = array();
    /** @var array<int, array<string, int>> */
    private $registrationStats = array();

    public function __construct() {
        parent::__construct(array(
            'singular' => 'mj_event',
            'plural'   => 'mj_events',
            'ajax'     => false,
        ));
    }

    public function get_columns() {
        return array(
            'cb'                    => '<input type="checkbox" />',
            'cover'                 => 'Visuel',
            'title'                 => 'Titre',
            'status'                => 'Statut',
            'type'                  => 'Type',
            'date_debut'            => 'Début',
            'date_fin'              => 'Fin',
            'date_fin_inscription'  => 'Fin d\'inscription',
            'location'              => 'Lieu',
            'age_range'             => 'Âges',
            'prix'                  => 'Tarif',
            'registrations'         => 'Inscriptions',
            'updated_at'            => 'Modifié',
            'actions'               => 'Actions',
        );
    }

    public function get_sortable_columns() {
        return array(
            'title'                => array('title', true),
            'status'               => array('status', false),
            'type'                 => array('type', false),
            'date_debut'           => array('date_debut', true),
            'date_fin'             => array('date_fin', true),
            'date_fin_inscription' => array('date_fin_inscription', true),
            'location'             => array('location', false),
            'prix'                 => array('prix', false),
            'updated_at'           => array('updated_at', true),
        );
    }

    public function get_bulk_actions() {
        return array(
            'activate'  => 'Marquer comme actif',
            'archive'   => 'Archiver',
            'duplicate' => 'Dupliquer',
            'delete'    => 'Supprimer',
        );
    }

    public function prepare_items() {
        $columns  = $this->get_columns();
        $hidden   = array();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array($columns, $hidden, $sortable);

        $per_page     = self::DEFAULT_PER_PAGE;
        $current_page = $this->get_pagenum();
        $offset       = ($current_page - 1) * $per_page;

        $status_filter = isset($_REQUEST['filter_status']) ? sanitize_key(wp_unslash($_REQUEST['filter_status'])) : '';
        $type_filter   = isset($_REQUEST['filter_type']) ? sanitize_key(wp_unslash($_REQUEST['filter_type'])) : '';
        $search        = isset($_REQUEST['s']) ? sanitize_text_field(wp_unslash($_REQUEST['s'])) : '';

        if (!array_key_exists($status_filter, self::STATUSES)) {
            $status_filter = '';
        }

        if (!array_key_exists($type_filter, self::TYPES)) {
            $type_filter = '';
        }

        $this->activeFilters = array(
            'status' => $status_filter,
            'type'   => $type_filter,
        );

        $wpdb            = MjTools::getWpdb();
        $events_table    = mj_member_get_events_table_name();
        $locations_table = mj_member_get_event_locations_table_name();

        if (!mj_member_table_exists($events_table)) {
            mj_member_upgrade_to_2_2($wpdb);
            mj_member_upgrade_to_2_3($wpdb);

            if (!mj_member_table_exists($events_table)) {
                $this->items = array();
                $this->set_pagination_args(array(
                    'total_items' => 0,
                    'per_page'    => $per_page,
                    'total_pages' => 1,
                ));
                return;
            }
        }

        if (!mj_member_table_exists($locations_table)) {
            mj_member_upgrade_to_2_3($wpdb);
        }

        $has_locations = mj_member_table_exists($locations_table);

        $allowed_orderby = array('title', 'status', 'type', 'date_debut', 'date_fin', 'date_fin_inscription', 'location', 'prix', 'updated_at');
        $orderby         = isset($_REQUEST['orderby']) ? sanitize_key(wp_unslash($_REQUEST['orderby'])) : 'date_debut';
        if (!in_array($orderby, $allowed_orderby, true)) {
            $orderby = 'date_debut';
        }

        $order = isset($_REQUEST['order']) ? strtoupper(sanitize_text_field(wp_unslash($_REQUEST['order']))) : 'DESC';
        $order = $order === 'ASC' ? 'ASC' : 'DESC';

        $orderby_map = array(
            'title'                => 'events.title',
            'status'               => 'events.status',
            'type'                 => 'events.type',
            'date_debut'           => 'events.date_debut',
            'date_fin'             => 'events.date_fin',
            'date_fin_inscription' => 'events.date_fin_inscription',
            'location'             => $has_locations ? 'location_name' : 'events.title',
            'prix'                 => 'events.prix',
            'updated_at'           => 'events.updated_at',
        );
        $where_parts  = array();
        $where_values = array();

        if ($status_filter !== '') {
            $where_parts[]  = 'events.status = %s';
            $where_values[] = $status_filter;
        }

        if ($type_filter !== '') {
            $where_parts[]  = 'events.type = %s';
            $where_values[] = $type_filter;
        }

        if ($search !== '') {
            $like             = '%' . $wpdb->esc_like($search) . '%';
            $search_condition = '(events.title LIKE %s OR events.description LIKE %s';
            if ($has_locations) {
                $search_condition .= ' OR locations.name LIKE %s OR locations.city LIKE %s';
            }
            $search_condition .= ')';
            $where_parts[]   = $search_condition;
            $where_values[]  = $like;
            $where_values[]  = $like;
            if ($has_locations) {
                $where_values[] = $like;
                $where_values[] = $like;
            }
        }

        $where_sql = '';
        if (!empty($where_parts)) {
            $where_sql = 'WHERE ' . implode(' AND ', $where_parts);
        }

        $select_fields = array(
            'events.id AS id',
            'events.title AS title',
            'events.status AS status',
            'events.type AS type',
            'events.cover_id AS cover_id',
            'events.article_id AS article_id',
            'events.description AS description',
            'events.age_min AS age_min',
            'events.age_max AS age_max',
            'events.date_debut AS date_debut',
            'events.date_fin AS date_fin',
            'events.date_fin_inscription AS date_fin_inscription',
            'events.location_id AS location_id',
            'events.prix AS prix',
            'events.created_at AS created_at',
            'events.updated_at AS updated_at',
        );

        if ($has_locations) {
            $select_fields[] = 'locations.name AS location_name';
            $select_fields[] = 'locations.city AS location_city';
        } else {
            $select_fields[] = 'NULL AS location_name';
            $select_fields[] = 'NULL AS location_city';
        }

        $select_clause = implode(', ', $select_fields);
        $join_sql = $has_locations ? " LEFT JOIN {$locations_table} AS locations ON locations.id = events.location_id" : '';

        $query_base = "SELECT {$select_clause} FROM {$events_table} AS events{$join_sql} {$where_sql} ORDER BY " . $orderby_map[$orderby] . " {$order} LIMIT %d OFFSET %d";

        $items_params   = $where_values;
        $items_params[] = $per_page;
        $items_params[] = $offset;

        array_unshift($items_params, $query_base);
        $prepared_query = call_user_func_array(array($wpdb, 'prepare'), $items_params);
        $items          = $wpdb->get_results($prepared_query, ARRAY_A);

        $count_query = "SELECT COUNT(DISTINCT events.id) FROM {$events_table} AS events{$join_sql} {$where_sql}";
        if (!empty($where_values)) {
            $count_params = $where_values;
            array_unshift($count_params, $count_query);
            $count_query = call_user_func_array(array($wpdb, 'prepare'), $count_params);
        }
        $total_items = (int) $wpdb->get_var($count_query);

        $this->items = is_array($items) ? $items : array();
        $this->hydrateRegistrationStats();

        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => $per_page > 0 ? (int) ceil($total_items / $per_page) : 1,
        ));
    }

    public function column_cb($item) {
        return '<input type="checkbox" name="event[]" value="' . esc_attr((int) $item['id']) . '" />';
    }

    public function column_title($item) {
        $title = '<strong>' . esc_html($item['title']) . '</strong>';

        $edit_url = add_query_arg(
            array(
                'page'   => 'mj_events',
                'action' => 'edit',
                'event'  => (int) $item['id'],
            ),
            admin_url('admin.php')
        );

        $duplicate_url = add_query_arg(
            array(
                'page'   => 'mj_events',
                'action' => 'duplicate',
                'event'  => (int) $item['id'],
            ),
            admin_url('admin.php')
        );
        $duplicate_url = wp_nonce_url($duplicate_url, 'mj_events_row_action');

        $actions = array(
            'edit'      => '<a href="' . esc_url($edit_url) . '">Éditer</a>',
            'duplicate' => '<a href="' . esc_url($duplicate_url) . '">Dupliquer</a>',
        );

        return $title . $this->row_actions($actions);
    }

    public function column_status($item) {
        $status = isset($item['status']) ? $item['status'] : '';
        $label  = self::STATUSES[$status] ?? ucfirst($status);

        $color_map = array(
            'actif'     => '#28a745',
            'brouillon' => '#6c757d',
            'passe'     => '#6f42c1',
        );
        $background = $color_map[$status] ?? '#6c757d';

        return '<span class="mj-status-badge" style="display:inline-block;padding:3px 8px;border-radius:12px;font-size:12px;color:#fff;background:' . esc_attr($background) . ';">' . esc_html($label) . '</span>';
    }

    public function column_type($item) {
        $type  = isset($item['type']) ? $item['type'] : '';
        $label = self::TYPES[$type] ?? ucfirst($type);
        return esc_html($label);
    }

    public function column_cover($item) {
        $cover_id = isset($item['cover_id']) ? (int) $item['cover_id'] : 0;
        $article_id = isset($item['article_id']) ? (int) $item['article_id'] : 0;
        $title = isset($item['title']) ? (string) $item['title'] : '';

        if ($cover_id > 0) {
            $image_html = wp_get_attachment_image($cover_id, array(80, 80), false, array(
                'style' => 'max-height:60px;width:auto;border-radius:4px;display:block;margin:0 auto;',
            ));

            if ($image_html) {
                return wp_kses_post($image_html);
            }
        }

        if ($article_id > 0) {
            $article_status = get_post_status($article_id);
            if ($article_status && $article_status !== 'trash') {
                $sizes = array('medium', 'large', 'full');
                $article_cover_url = '';

                foreach ($sizes as $size) {
                    $candidate = get_the_post_thumbnail_url($article_id, $size);
                    if (!empty($candidate)) {
                        $article_cover_url = $candidate;
                        break;
                    }
                }

                if ($article_cover_url !== '') {
                    return sprintf(
                        '<img src="%1$s" alt="%2$s" style="max-height:60px;width:auto;border-radius:4px;display:block;margin:0 auto;" />',
                        esc_url($article_cover_url),
                        esc_attr($title)
                    );
                }
            }
        }

        return '<span style="color:#6c757d;">Aucun visuel</span>';
    }

    public function column_date_debut($item) {
        if (empty($item['date_debut'])) {
            return '—';
        }

        $timestamp = strtotime($item['date_debut']);
        if (!$timestamp) {
            return esc_html($item['date_debut']);
        }

        return esc_html(wp_date('d/m/Y H:i', $timestamp));
    }

    public function column_date_fin($item) {
        if (empty($item['date_fin'])) {
            return '—';
        }

        $timestamp = strtotime($item['date_fin']);
        if (!$timestamp) {
            return esc_html($item['date_fin']);
        }

        return esc_html(wp_date('d/m/Y H:i', $timestamp));
    }

    public function column_date_fin_inscription($item) {
        if (empty($item['date_fin_inscription'])) {
            return '<span style="color:#6c757d;">Automatique</span>';
        }

        $timestamp = strtotime($item['date_fin_inscription']);
        if (!$timestamp) {
            return esc_html($item['date_fin_inscription']);
        }

        return esc_html(wp_date('d/m/Y H:i', $timestamp));
    }

    public function column_location($item) {
        $name = isset($item['location_name']) ? trim((string) $item['location_name']) : '';
        $city = isset($item['location_city']) ? trim((string) $item['location_city']) : '';

        if ($name === '') {
            return '<span style="color:#6c757d;">Non défini</span>';
        }

        if ($city !== '') {
            return esc_html($name) . '<br /><span style="color:#6c757d;font-size:11px;">' . esc_html($city) . '</span>';
        }

        return esc_html($name);
    }

    public function column_age_range($item) {
        $min = isset($item['age_min']) ? (int) $item['age_min'] : 0;
        $max = isset($item['age_max']) ? (int) $item['age_max'] : 0;
        if ($min <= 0 && $max <= 0) {
            return '—';
        }
        return esc_html($min . ' - ' . $max . ' ans');
    }

    public function column_prix($item) {
        if (!isset($item['prix'])) {
            return '—';
        }

        $price = (float) $item['prix'];
        return esc_html(number_format_i18n($price, 2) . ' €');
    }

    public function column_registrations($item) {
        $event_id = (int) $item['id'];
        if (!isset($this->registrationStats[$event_id])) {
            return '<span style="color:#6c757d;">Aucune</span>';
        }

        $stats      = $this->registrationStats[$event_id];
        $total      = isset($stats['total']) ? (int) $stats['total'] : 0;
        $confirmed  = isset($stats['confirmed']) ? (int) $stats['confirmed'] : 0;

        if ($total === 0) {
            return '<span style="color:#6c757d;">Aucune</span>';
        }

        return esc_html($confirmed . ' validé(s) / ' . $total);
    }

    public function column_updated_at($item) {
        if (empty($item['updated_at'])) {
            return '—';
        }

        $timestamp = strtotime($item['updated_at']);
        if (!$timestamp) {
            return esc_html($item['updated_at']);
        }

        $diff = human_time_diff($timestamp, current_time('timestamp'));
        return esc_html(sprintf(__('il y a %s', 'mj-member'), $diff));
    }

    public function column_actions($item) {
        $edit_url = add_query_arg(
            array(
                'page'   => 'mj_events',
                'action' => 'edit',
                'event'  => (int) $item['id'],
            ),
            admin_url('admin.php')
        );

        $duplicate_url = add_query_arg(
            array(
                'page'   => 'mj_events',
                'action' => 'duplicate',
                'event'  => (int) $item['id'],
            ),
            admin_url('admin.php')
        );
        $duplicate_url = wp_nonce_url($duplicate_url, 'mj_events_row_action');

        $buttons = array();
        $buttons[] = '<a class="mj-member-login-action" href="' . esc_url($edit_url) . '">✏️ Éditer</a>';
        $buttons[] = '<a class="mj-member-login-action mj-member-login-action--create" href="' . esc_url($duplicate_url) . '">📄 Dupliquer</a>';

        if (empty($buttons)) {
            return '';
        }

        return '<div class="mj-login-actions">' . implode('', $buttons) . '</div>';
    }

    public function column_default($item, $column_name) {
        if (isset($item[$column_name])) {
            return esc_html((string) $item[$column_name]);
        }

        return '—';
    }

    public function no_items() {
        esc_html_e('Aucun événement trouvé.', 'mj-member');
    }

    protected function extra_tablenav($which) {
        if ('top' !== $which) {
            return;
        }

        $status_value = $this->activeFilters['status'] ?? '';
        $type_value   = $this->activeFilters['type'] ?? '';

        echo '<div class="alignleft actions">';

        echo '<label class="screen-reader-text" for="mj-filter-status">Filtrer par statut</label>';
        echo '<select name="filter_status" id="mj-filter-status">';
        echo '<option value="">Tous les statuts</option>';
        foreach (self::STATUSES as $key => $label) {
            printf('<option value="%s" %s>%s</option>', esc_attr($key), selected($status_value, $key, false), esc_html($label));
        }
        echo '</select>';

        echo '<label class="screen-reader-text" for="mj-filter-type">Filtrer par type</label>';
        echo '<select name="filter_type" id="mj-filter-type" style="margin-left:6px;">';
        echo '<option value="">Tous les types</option>';
        foreach (self::TYPES as $key => $label) {
            printf('<option value="%s" %s>%s</option>', esc_attr($key), selected($type_value, $key, false), esc_html($label));
        }
        echo '</select>';

        submit_button('Filtrer', '', 'filter_action', false, array('style' => 'margin-left:8px;'));

        $reset_url = remove_query_arg(array('filter_status', 'filter_type', 'paged'));
        echo ' <a href="' . esc_url($reset_url) . '" class="button">Réinitialiser</a>';

        echo '</div>';
    }

    public function process_table_actions() {
        $this->process_bulk_action();
    }

    public function process_bulk_action() {
        $action = $this->current_action();
        if (!$action || !in_array($action, array('activate', 'archive', 'duplicate', 'delete'), true)) {
            return;
        }

        $capability = Config::capability();

        if (!current_user_can($capability)) {
            $this->redirect_with_message('Action non autorisée.', 'error');
        }

        $nonce = isset($_REQUEST['_wpnonce']) ? sanitize_text_field(wp_unslash($_REQUEST['_wpnonce'])) : '';
        $nonce_action = 'mj_events_row_action';
        if (isset($_REQUEST['event']) && is_array($_REQUEST['event'])) {
            $nonce_action = 'bulk-' . $this->_args['plural'];
        }

        if (!$nonce || !wp_verify_nonce($nonce, $nonce_action)) {
            $this->redirect_with_message('Sécurité invalide, veuillez réessayer.', 'error');
        }

        $event_ids = $this->parse_event_ids();
        if (empty($event_ids)) {
            $this->redirect_with_message('Aucun événement sélectionné.', 'warning');
        }

        $message = '';
        $message_type = 'success';
        switch ($action) {
            case 'activate':
                $updated = $this->update_status($event_ids, 'actif');
                $message = sprintf('%d événement(s) marqué(s) comme actif(s).', $updated);
                break;
            case 'archive':
                $updated = $this->update_status($event_ids, 'passe');
                $message = sprintf('%d événement(s) archivé(s).', $updated);
                break;
            case 'duplicate':
                $created = $this->duplicate_events($event_ids);
                $message = sprintf('%d copie(s) créée(s).', $created);
                break;
            case 'delete':
                $deleted = $this->delete_events($event_ids);
                if ($deleted > 0) {
                    $message = sprintf('%d événement(s) supprimé(s).', $deleted);
                } else {
                    $message = 'Aucun événement supprimé.';
                    $message_type = 'warning';
                }
                break;
        }

        $this->redirect_with_message($message, $message_type);
    }

    /**
     * @return array<int>
     */
    private function parse_event_ids() {
        $ids = array();

        if (isset($_REQUEST['event'])) {
            $raw = wp_unslash($_REQUEST['event']);
            if (!is_array($raw)) {
                $raw = array($raw);
            }
            foreach ($raw as $value) {
                $id = (int) $value;
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        } elseif (isset($_REQUEST['id'])) {
            $id = (int) $_REQUEST['id'];
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param array<int> $event_ids
     */
    private function update_status(array $event_ids, $status) {
        if (empty($event_ids)) {
            return 0;
        }

        $allowed_statuses = array_keys(self::STATUSES);
        if (!in_array($status, $allowed_statuses, true)) {
            return 0;
        }

        $wpdb         = MjTools::getWpdb();
        $events_table = mj_member_get_events_table_name();
        $placeholders = implode(',', array_fill(0, count($event_ids), '%d'));

        $query  = "UPDATE $events_table SET status = %s, updated_at = %s WHERE id IN ($placeholders)";
        $params = array_merge(array($status, current_time('mysql')), $event_ids);
        array_unshift($params, $query);
        $prepared = call_user_func_array(array($wpdb, 'prepare'), $params);

        $wpdb->query($prepared);
        return (int) $wpdb->rows_affected;
    }

    /**
     * @param array<int> $event_ids
     */
    private function duplicate_events(array $event_ids) {
        if (empty($event_ids)) {
            return 0;
        }

        $wpdb         = MjTools::getWpdb();
        $events_table = mj_member_get_events_table_name();
        $placeholders = implode(',', array_fill(0, count($event_ids), '%d'));

        $select_sql = "SELECT * FROM $events_table WHERE id IN ($placeholders)";
        $select_params = $event_ids;
        array_unshift($select_params, $select_sql);
        $prepared_select = call_user_func_array(array($wpdb, 'prepare'), $select_params);
        $rows = $wpdb->get_results($prepared_select, ARRAY_A);

        if (empty($rows)) {
            return 0;
        }

        $now     = current_time('mysql');
        $created = 0;

        foreach ($rows as $row) {
            $new_title = isset($row['title']) ? $row['title'] . ' (Copie)' : 'Événement (Copie)';

            $data = array(
                'title'                => $new_title,
                'status'               => 'brouillon',
                'type'                 => $row['type'] ?? 'stage',
                'cover_id'             => !empty($row['cover_id']) ? (int) $row['cover_id'] : null,
                'description'          => $row['description'] ?? null,
                'age_min'              => isset($row['age_min']) ? (int) $row['age_min'] : 12,
                'age_max'              => isset($row['age_max']) ? (int) $row['age_max'] : 26,
                'date_debut'           => $row['date_debut'] ?? $now,
                'date_fin'             => $row['date_fin'] ?? $now,
                'date_fin_inscription' => $row['date_fin_inscription'] ?? null,
                'prix'                 => isset($row['prix']) ? $row['prix'] : 0.00,
                'created_at'           => $now,
                'updated_at'           => $now,
            );

            $format = array(
                'title'                => '%s',
                'status'               => '%s',
                'type'                 => '%s',
                'cover_id'             => '%d',
                'description'          => '%s',
                'age_min'              => '%d',
                'age_max'              => '%d',
                'date_debut'           => '%s',
                'date_fin'             => '%s',
                'date_fin_inscription' => '%s',
                'prix'                 => '%f',
                'created_at'           => '%s',
                'updated_at'           => '%s',
            );

            if ($data['cover_id'] === null) {
                unset($data['cover_id'], $format['cover_id']);
            }

            if ($data['description'] === null) {
                unset($data['description'], $format['description']);
            }

            if ($data['date_fin_inscription'] === null) {
                unset($data['date_fin_inscription'], $format['date_fin_inscription']);
            }

            $inserted = $wpdb->insert($events_table, $data, array_values($format));
            if ($inserted !== false) {
                $created++;
            }
        }

        return $created;
    }

    /**
     * @param array<int> $event_ids
     */
    private function delete_events(array $event_ids) {
        if (empty($event_ids)) {
            return 0;
        }

        $normalized_ids = array();
        foreach ($event_ids as $event_id) {
            $event_id = (int) $event_id;
            if ($event_id > 0) {
                $normalized_ids[$event_id] = $event_id;
            }
        }

        if (empty($normalized_ids)) {
            return 0;
        }

        $wpdb         = MjTools::getWpdb();
        $events_table = mj_member_get_events_table_name();

        if (!mj_member_table_exists($events_table)) {
            return 0;
        }

        $ids           = array_values($normalized_ids);
        $placeholders  = implode(',', array_fill(0, count($ids), '%d'));
        $registration_ids = array();

        if (function_exists('mj_member_get_event_registrations_table_name')) {
            $registrations_table = mj_member_get_event_registrations_table_name();
            if ($registrations_table && mj_member_table_exists($registrations_table)) {
                $select_sql  = "SELECT id FROM {$registrations_table} WHERE event_id IN ($placeholders)";
                $select_args = $ids;
                array_unshift($select_args, $select_sql);
                $prepared_select = call_user_func_array(array($wpdb, 'prepare'), $select_args);
                $registration_ids = $wpdb->get_col($prepared_select);
                if (!is_array($registration_ids)) {
                    $registration_ids = array();
                }

                $delete_sql  = "DELETE FROM {$registrations_table} WHERE event_id IN ($placeholders)";
                $delete_args = $ids;
                array_unshift($delete_args, $delete_sql);
                $prepared_delete = call_user_func_array(array($wpdb, 'prepare'), $delete_args);
                $wpdb->query($prepared_delete);
            }
        }

        if (function_exists('mj_member_get_event_animateurs_table_name')) {
            $animateurs_table = mj_member_get_event_animateurs_table_name();
            if ($animateurs_table && mj_member_table_exists($animateurs_table)) {
                $delete_sql  = "DELETE FROM {$animateurs_table} WHERE event_id IN ($placeholders)";
                $delete_args = $ids;
                array_unshift($delete_args, $delete_sql);
                $prepared_delete = call_user_func_array(array($wpdb, 'prepare'), $delete_args);
                $wpdb->query($prepared_delete);
            }
        }

        $payments_table = $wpdb->prefix . 'mj_payments';
        if (mj_member_table_exists($payments_table)) {
            $update_sql  = "UPDATE {$payments_table} SET event_id = NULL WHERE event_id IN ($placeholders)";
            $update_args = $ids;
            array_unshift($update_args, $update_sql);
            $prepared_update = call_user_func_array(array($wpdb, 'prepare'), $update_args);
            $wpdb->query($prepared_update);

            if (!empty($registration_ids)) {
                $registration_ids = array_values(array_unique(array_map('intval', $registration_ids)));
                $registration_ids = array_filter($registration_ids, static function ($value) {
                    return $value > 0;
                });

                if (!empty($registration_ids)) {
                    $reg_placeholders = implode(',', array_fill(0, count($registration_ids), '%d'));
                    $reg_update_sql   = "UPDATE {$payments_table} SET registration_id = NULL WHERE registration_id IN ($reg_placeholders)";
                    $reg_update_args  = $registration_ids;
                    array_unshift($reg_update_args, $reg_update_sql);
                    $prepared_reg_update = call_user_func_array(array($wpdb, 'prepare'), $reg_update_args);
                    $wpdb->query($prepared_reg_update);
                }
            }
        }

        $delete_sql  = "DELETE FROM {$events_table} WHERE id IN ($placeholders)";
        $delete_args = $ids;
        array_unshift($delete_args, $delete_sql);
        $prepared_delete = call_user_func_array(array($wpdb, 'prepare'), $delete_args);
        $wpdb->query($prepared_delete);

        return (int) $wpdb->rows_affected;
    }

    private function redirect_with_message($message, $type = 'success') {
        $allowed_types = array('success', 'error', 'warning');
        if (!in_array($type, $allowed_types, true)) {
            $type = 'success';
        }

        $query_args = array();
        foreach ($_GET as $key => $value) {
            if (in_array($key, array('action', 'action2', 'event', '_wpnonce', 'mj_events_message', 'mj_events_message_type'), true)) {
                continue;
            }
            if (is_array($value)) {
                $query_args[$key] = array_map('sanitize_text_field', wp_unslash($value));
            } else {
                $query_args[$key] = sanitize_text_field(wp_unslash($value));
            }
        }

        $query_args['page']                 = 'mj_events';
        $query_args['mj_events_message']     = rawurlencode($message);
        $query_args['mj_events_message_type'] = $type;

        $redirect_url = add_query_arg($query_args, admin_url('admin.php'));

        if (headers_sent()) {
            $_GET['mj_events_message'] = rawurlencode($message);
            $_GET['mj_events_message_type'] = $type;
            return;
        }

        wp_safe_redirect($redirect_url);
        exit;
    }

    private function hydrateRegistrationStats() {
        if (empty($this->items)) {
            $this->registrationStats = array();
            return;
        }

        $event_ids = array();
        foreach ($this->items as $item) {
            $event_ids[] = (int) $item['id'];
        }

        $event_ids = array_values(array_unique(array_filter($event_ids)));
        if (empty($event_ids)) {
            $this->registrationStats = array();
            return;
        }

        $wpdb                 = MjTools::getWpdb();
        $registrations_table  = mj_member_get_event_registrations_table_name();
        $placeholders         = implode(',', array_fill(0, count($event_ids), '%d'));
        $sql                  = "SELECT event_id, COUNT(*) AS total, SUM(CASE WHEN statut = 'valide' THEN 1 ELSE 0 END) AS confirmed FROM $registrations_table WHERE event_id IN ($placeholders) GROUP BY event_id";
        $params               = $event_ids;
        array_unshift($params, $sql);
        $prepared             = call_user_func_array(array($wpdb, 'prepare'), $params);
        $results              = $wpdb->get_results($prepared, ARRAY_A);

        $stats = array();
        foreach ($results as $row) {
            $event_id = isset($row['event_id']) ? (int) $row['event_id'] : 0;
            if ($event_id <= 0) {
                continue;
            }
            $stats[$event_id] = array(
                'total'     => isset($row['total']) ? (int) $row['total'] : 0,
                'confirmed' => isset($row['confirmed']) ? (int) $row['confirmed'] : 0,
            );
        }

        $this->registrationStats = $stats;
    }
}
