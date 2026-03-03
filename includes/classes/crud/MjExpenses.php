<?php

namespace Mj\Member\Classes\Crud;

use Mj\Member\Classes\MjTools;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * CRUD repository for expense reports (mj_expenses).
 */
class MjExpenses extends MjTools implements CrudRepositoryInterface
{
    private const TABLE = 'mj_expenses';

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_REIMBURSED = 'reimbursed';

    /**
     * @return string
     */
    public static function table_name(): string
    {
        if (function_exists('mj_member_get_expenses_table_name')) {
            return mj_member_get_expenses_table_name();
        }

        return self::getTableName(self::TABLE);
    }

    /**
     * @return string
     */
    public static function events_link_table_name(): string
    {
        if (function_exists('mj_member_get_expense_events_table_name')) {
            return mj_member_get_expense_events_table_name();
        }

        return self::getTableName('mj_expense_events');
    }

    /**
     * @return array<int,string>
     */
    public static function statuses(): array
    {
        return array(
            self::STATUS_PENDING,
            self::STATUS_APPROVED,
            self::STATUS_REJECTED,
            self::STATUS_REIMBURSED,
        );
    }

    /**
     * @return array<string,string>
     */
    public static function get_status_labels(): array
    {
        return array(
            self::STATUS_PENDING    => __('En attente', 'mj-member'),
            self::STATUS_APPROVED   => __('Approuvée', 'mj-member'),
            self::STATUS_REJECTED   => __('Refusée', 'mj-member'),
            self::STATUS_REIMBURSED => __('Remboursée', 'mj-member'),
        );
    }

    /**
     * @param string $status
     * @return string
     */
    private static function normalize_status(string $status): string
    {
        $status = strtolower(trim($status));

        return in_array($status, self::statuses(), true) ? $status : self::STATUS_PENDING;
    }

    /**
     * Get all expenses with optional filters.
     *
     * @param array<string,mixed> $args
     * @return array<int,object>
     */
    public static function get_all(array $args = array()): array
    {
        global $wpdb;
        $table = self::table_name();

        $defaults = array(
            'member_id'  => 0,
            'project_id' => 0,
            'event_id'   => 0,
            'status'     => '',
            'statuses'   => array(),
            'limit'      => 0,
            'offset'     => 0,
            'orderby'    => 'created_at',
            'order'      => 'DESC',
        );
        $args = wp_parse_args($args, $defaults);

        $where = array();
        $params = array();

        if ((int) $args['member_id'] > 0) {
            $where[] = 'e.member_id = %d';
            $params[] = (int) $args['member_id'];
        }

        if ((int) $args['project_id'] > 0) {
            $where[] = 'e.project_id = %d';
            $params[] = (int) $args['project_id'];
        }

        if ((int) $args['event_id'] > 0) {
            $link_table = self::events_link_table_name();
            $where[] = "e.id IN (SELECT expense_id FROM {$link_table} WHERE event_id = %d)";
            $params[] = (int) $args['event_id'];
        }

        if (!empty($args['statuses']) && is_array($args['statuses'])) {
            $placeholders = implode(', ', array_fill(0, count($args['statuses']), '%s'));
            $where[] = "e.status IN ({$placeholders})";
            foreach ($args['statuses'] as $s) {
                $params[] = self::normalize_status($s);
            }
        } elseif (!empty($args['status'])) {
            $where[] = 'e.status = %s';
            $params[] = self::normalize_status($args['status']);
        }

        $allowedOrderBy = array('created_at', 'amount', 'status', 'id');
        $orderby = sanitize_key((string) $args['orderby']);
        if (!in_array($orderby, $allowedOrderBy, true)) {
            $orderby = 'created_at';
        }

        $order = strtoupper((string) $args['order']) === 'ASC' ? 'ASC' : 'DESC';

        $sql = "SELECT e.* FROM {$table} e";

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= " ORDER BY e.{$orderby} {$order}";

        $limit = (int) $args['limit'];
        $offset = max(0, (int) $args['offset']);
        if ($limit > 0) {
            $sql .= ' LIMIT %d OFFSET %d';
            $params[] = $limit;
            $params[] = $offset;
        }

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        $rows = $wpdb->get_results($sql);

        return is_array($rows) ? $rows : array();
    }

    /**
     * Count expenses.
     *
     * @param array<string,mixed> $args
     * @return int
     */
    public static function count(array $args = array()): int
    {
        global $wpdb;
        $table = self::table_name();

        $where = array();
        $params = array();

        if (!empty($args['member_id'])) {
            $where[] = 'member_id = %d';
            $params[] = (int) $args['member_id'];
        }

        if (!empty($args['status'])) {
            $where[] = 'status = %s';
            $params[] = self::normalize_status($args['status']);
        }

        $sql = "SELECT COUNT(*) FROM {$table}";

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        return (int) $wpdb->get_var($sql);
    }

    /**
     * Get a single expense by ID.
     *
     * @param int $id
     * @return object|null
     */
    public static function get_by_id(int $id): ?object
    {
        global $wpdb;
        $table = self::table_name();

        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));

        return $row ?: null;
    }

    /**
     * Decode the receipt_file column into an array of filenames.
     *
     * Handles both legacy single-filename strings and JSON arrays.
     *
     * @param string|null $raw Raw DB value.
     * @return array<int,string>
     */
    public static function decode_receipt_files($raw): array
    {
        if (empty($raw)) {
            return array();
        }

        // Try JSON first
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return array_values(array_filter(array_map('sanitize_file_name', $decoded)));
        }

        // Legacy: single filename string
        $name = sanitize_file_name($raw);
        return $name !== '' ? array($name) : array();
    }

    /**
     * Encode an array of filenames for DB storage.
     *
     * @param array<int,string> $files
     * @return string|null JSON string or null if empty.
     */
    private static function encode_receipt_files(array $files): ?string
    {
        $files = array_values(array_filter(array_map('sanitize_file_name', $files)));
        return !empty($files) ? wp_json_encode($files) : null;
    }

    /**
     * Create an expense.
     *
     * @param array<string,mixed> $data
     * @return int|WP_Error
     */
    public static function create($data)
    {
        global $wpdb;
        $table = self::table_name();

        $memberId = isset($data['member_id']) ? (int) $data['member_id'] : 0;
        $amount = isset($data['amount']) ? floatval($data['amount']) : 0;
        $description = isset($data['description']) ? sanitize_textarea_field($data['description']) : '';
        $projectId = isset($data['project_id']) ? (int) $data['project_id'] : null;
        $status = isset($data['status']) ? self::normalize_status($data['status']) : self::STATUS_PENDING;

        // Accept receipt_files (array) or legacy receipt_file (string)
        $receiptJson = null;
        if (!empty($data['receipt_files']) && is_array($data['receipt_files'])) {
            $receiptJson = self::encode_receipt_files($data['receipt_files']);
        } elseif (!empty($data['receipt_file'])) {
            $receiptJson = self::encode_receipt_files(array($data['receipt_file']));
        }

        if ($memberId <= 0) {
            return new WP_Error('invalid_member', __('Membre invalide.', 'mj-member'));
        }
        if ($amount <= 0) {
            return new WP_Error('invalid_amount', __('Le montant doit être supérieur à 0.', 'mj-member'));
        }

        $result = $wpdb->insert($table, array(
            'member_id'    => $memberId,
            'amount'       => $amount,
            'description'  => $description,
            'project_id'   => $projectId,
            'receipt_file' => $receiptJson,
            'status'       => $status,
        ), array('%d', '%f', '%s', '%d', '%s', '%s'));

        if ($result === false) {
            return new WP_Error('db_error', __('Erreur lors de la création.', 'mj-member'));
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * Update an expense.
     *
     * @param int                  $id
     * @param array<string,mixed> $data
     * @return bool|WP_Error
     */
    public static function update($id, $data)
    {
        global $wpdb;
        $table = self::table_name();

        $set = array();
        $formats = array();

        if (isset($data['amount'])) {
            $set['amount'] = floatval($data['amount']);
            $formats[] = '%f';
        }
        if (isset($data['description'])) {
            $set['description'] = sanitize_textarea_field($data['description']);
            $formats[] = '%s';
        }
        if (array_key_exists('project_id', $data)) {
            $set['project_id'] = $data['project_id'] !== null ? (int) $data['project_id'] : null;
            $formats[] = '%d';
        }
        if (!empty($data['receipt_files']) && is_array($data['receipt_files'])) {
            $set['receipt_file'] = self::encode_receipt_files($data['receipt_files']);
            $formats[] = '%s';
        } elseif (isset($data['receipt_file'])) {
            // Legacy single-file or raw JSON string
            if (is_array($data['receipt_file'])) {
                $set['receipt_file'] = self::encode_receipt_files($data['receipt_file']);
            } else {
                $set['receipt_file'] = self::encode_receipt_files(array($data['receipt_file']));
            }
            $formats[] = '%s';
        }
        if (isset($data['status'])) {
            $set['status'] = self::normalize_status($data['status']);
            $formats[] = '%s';
        }
        if (isset($data['reviewed_by'])) {
            $set['reviewed_by'] = (int) $data['reviewed_by'];
            $formats[] = '%d';
        }
        if (isset($data['reviewed_at'])) {
            $set['reviewed_at'] = sanitize_text_field($data['reviewed_at']);
            $formats[] = '%s';
        }
        if (isset($data['reviewer_comment'])) {
            $set['reviewer_comment'] = sanitize_textarea_field($data['reviewer_comment']);
            $formats[] = '%s';
        }

        if (empty($set)) {
            return new WP_Error('no_data', __('Aucune donnée à mettre à jour.', 'mj-member'));
        }

        $result = $wpdb->update($table, $set, array('id' => $id), $formats, array('%d'));

        return $result !== false;
    }

    /**
     * Delete an expense.
     *
     * @param int $id
     * @return bool
     */
    public static function delete($id): bool
    {
        global $wpdb;
        $table = self::table_name();

        // Delete linked events
        $link_table = self::events_link_table_name();
        if (function_exists('mj_member_table_exists') && mj_member_table_exists($link_table)) {
            $wpdb->delete($link_table, array('expense_id' => $id), array('%d'));
        }

        // Delete all receipt files
        $expense = self::get_by_id($id);
        if ($expense && !empty($expense->receipt_file)) {
            $files = self::decode_receipt_files($expense->receipt_file);
            foreach ($files as $fname) {
                $fpath = MJ_MEMBER_PATH . 'data/expenses/' . $fname;
                if (file_exists($fpath)) {
                    @unlink($fpath);
                }
            }
        }

        return $wpdb->delete($table, array('id' => $id), array('%d')) !== false;
    }

    /**
     * Link events to an expense (many-to-many).
     *
     * @param int        $expenseId
     * @param array<int> $eventIds
     * @return void
     */
    public static function sync_events(int $expenseId, array $eventIds): void
    {
        global $wpdb;
        $link_table = self::events_link_table_name();

        // Remove existing links
        $wpdb->delete($link_table, array('expense_id' => $expenseId), array('%d'));

        // Insert new links
        foreach ($eventIds as $eventId) {
            $eventId = (int) $eventId;
            if ($eventId > 0) {
                $wpdb->insert($link_table, array(
                    'expense_id' => $expenseId,
                    'event_id'   => $eventId,
                ), array('%d', '%d'));
            }
        }
    }

    /**
     * Get linked event IDs for an expense.
     *
     * @param int $expenseId
     * @return array<int>
     */
    public static function get_event_ids(int $expenseId): array
    {
        global $wpdb;
        $link_table = self::events_link_table_name();

        if (!function_exists('mj_member_table_exists') || !mj_member_table_exists($link_table)) {
            return array();
        }

        $rows = $wpdb->get_col($wpdb->prepare(
            "SELECT event_id FROM {$link_table} WHERE expense_id = %d",
            $expenseId
        ));

        return array_map('intval', $rows);
    }

    /**
     * Enrich expense rows with member and event data.
     *
     * @param array<int,object> $expenses
     * @return array<int,object>
     */
    public static function enrich(array $expenses): array
    {
        if (empty($expenses)) {
            return array();
        }

        // Collect IDs
        $memberIds = array();
        $projectIds = array();
        foreach ($expenses as $exp) {
            $memberIds[] = (int) $exp->member_id;
            if (!empty($exp->project_id)) {
                $projectIds[] = (int) $exp->project_id;
            }
        }

        // Fetch members
        $memberMap = array();
        $memberIds = array_unique($memberIds);
        if (!empty($memberIds)) {
            $members = MjMembers::get_all(array(
                'filters' => array('ids' => $memberIds),
                'limit' => 0,
            ));
            foreach ($members as $m) {
                $memberMap[(int) $m->id] = $m;
            }
        }

        // Fetch projects
        $projectMap = array();
        $projectIds = array_unique($projectIds);
        if (!empty($projectIds) && class_exists('Mj\\Member\\Classes\\Crud\\MjTodoProjects')) {
            $projects = MjTodoProjects::get_all(array('include_ids' => $projectIds, 'limit' => 0));
            foreach ($projects as $p) {
                $pid = is_array($p) ? (int) $p['id'] : (int) $p->id;
                $projectMap[$pid] = $p;
            }
        }

        // Enrich
        foreach ($expenses as &$exp) {
            $mid = (int) $exp->member_id;
            if (isset($memberMap[$mid])) {
                $m = $memberMap[$mid];
                $exp->member_name = trim($m->first_name . ' ' . $m->last_name);
                $exp->member_role = $m->role;
            } else {
                $exp->member_name = __('Membre inconnu', 'mj-member');
                $exp->member_role = '';
            }

            $pid = !empty($exp->project_id) ? (int) $exp->project_id : 0;
            if ($pid > 0 && isset($projectMap[$pid])) {
                $p = $projectMap[$pid];
                $exp->project_name = is_array($p) ? ($p['title'] ?? '') : ($p->title ?? '');
            } else {
                $exp->project_name = '';
            }

            // Attach event IDs
            $exp->event_ids = self::get_event_ids((int) $exp->id);
        }
        unset($exp);

        return $expenses;
    }

    /**
     * Get summary per member (for coordinator view).
     *
     * @return array<int,object>
     */
    public static function get_summary_by_member(): array
    {
        global $wpdb;
        $table = self::table_name();

        $rows = $wpdb->get_results(
            "SELECT member_id,
                    COUNT(*) as total_count,
                    SUM(amount) as total_amount,
                    SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending_amount,
                    SUM(CASE WHEN status = 'reimbursed' THEN amount ELSE 0 END) as reimbursed_amount
             FROM {$table}
             GROUP BY member_id
             ORDER BY total_amount DESC"
        );

        return is_array($rows) ? $rows : array();
    }
}
