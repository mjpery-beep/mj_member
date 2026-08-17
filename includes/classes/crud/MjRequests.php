<?php

namespace Mj\Member\Classes\Crud;

use Mj\Member\Classes\MjTools;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class MjRequests extends MjTools implements CrudRepositoryInterface
{
    private const TABLE = 'mj_requests';

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    public static function statuses(): array
    {
        return array(
            self::STATUS_PENDING,
            self::STATUS_APPROVED,
            self::STATUS_REJECTED,
            self::STATUS_CANCELLED,
        );
    }

    public static function get_status_labels(): array
    {
        return array(
            self::STATUS_PENDING => __('En attente', 'mj-member'),
            self::STATUS_APPROVED => __('Approuvée', 'mj-member'),
            self::STATUS_REJECTED => __('Refusée', 'mj-member'),
            self::STATUS_CANCELLED => __('Annulée', 'mj-member'),
        );
    }

    private static function table_name(): string
    {
        if (function_exists('mj_member_get_requests_table_name')) {
            return mj_member_get_requests_table_name();
        }

        return self::getTableName(self::TABLE);
    }

    private static function normalize_status(string $status): string
    {
        $status = strtolower(trim($status));
        return in_array($status, self::statuses(), true) ? $status : self::STATUS_PENDING;
    }

    /**
     * @param mixed $slots
     * @return array<int,array{date:string,start:string,end:string}>
     */
    private static function normalize_slots($slots): array
    {
        if (is_string($slots)) {
            $decoded = json_decode($slots, true);
            $slots = is_array($decoded) ? $decoded : array();
        }

        if (!is_array($slots)) {
            return array();
        }

        $result = array();
        foreach ($slots as $slot) {
            if (!is_array($slot)) {
                continue;
            }

            $date = isset($slot['date']) ? sanitize_text_field((string) $slot['date']) : '';
            if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                continue;
            }

            $result[] = array(
                'date' => $date,
                'start' => isset($slot['start']) ? sanitize_text_field((string) $slot['start']) : '',
                'end' => isset($slot['end']) ? sanitize_text_field((string) $slot['end']) : '',
            );
        }

        usort($result, static function ($a, $b) {
            return strcmp($a['date'] . $a['start'], $b['date'] . $b['start']);
        });

        return $result;
    }

    /**
     * @param mixed $ids
     * @return array<int,int>
     */
    private static function normalize_member_ids($ids): array
    {
        if (is_string($ids)) {
            $decoded = json_decode($ids, true);
            $ids = is_array($decoded) ? $decoded : array();
        }

        if (!is_array($ids)) {
            return array();
        }

        $result = array();
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0 && !in_array($id, $result, true)) {
                $result[] = $id;
            }
        }

        return $result;
    }

    public static function get_all(array $args = array())
    {
        global $wpdb;
        $table = self::table_name();

        $defaults = array(
            'member_id' => 0,
            'assigned_to_member_id' => 0,
            'room_id' => 0,
            'status' => '',
            'statuses' => array(),
            'type' => '',
            'limit' => 100,
            'offset' => 0,
            'order' => 'DESC',
            'orderby' => 'created_at',
            'search' => '',
        );
        $args = wp_parse_args($args, $defaults);

        $where = array();
        $params = array();

        if ((int) $args['member_id'] > 0) {
            $where[] = 'member_id = %d';
            $params[] = (int) $args['member_id'];
        }

        if ((int) $args['assigned_to_member_id'] > 0) {
            $where[] = 'assigned_to_member_id = %d';
            $params[] = (int) $args['assigned_to_member_id'];
        }

        if ((int) $args['room_id'] > 0) {
            $where[] = 'room_id = %d';
            $params[] = (int) $args['room_id'];
        }

        if (!empty($args['statuses']) && is_array($args['statuses'])) {
            $safe = array_values(array_filter(array_map('strval', $args['statuses'])));
            if (!empty($safe)) {
                $placeholders = implode(', ', array_fill(0, count($safe), '%s'));
                $where[] = "status IN ({$placeholders})";
                foreach ($safe as $status) {
                    $params[] = self::normalize_status($status);
                }
            }
        } elseif (!empty($args['status'])) {
            $where[] = 'status = %s';
            $params[] = self::normalize_status((string) $args['status']);
        }

        if (!empty($args['type'])) {
            $where[] = 'request_type = %s';
            $params[] = sanitize_text_field((string) $args['type']);
        }

        if (!empty($args['search'])) {
            $search = '%' . $wpdb->esc_like((string) $args['search']) . '%';
            $where[] = '(title LIKE %s OR description LIKE %s)';
            $params[] = $search;
            $params[] = $search;
        }

        $orderby = in_array($args['orderby'], array('created_at', 'updated_at', 'status', 'id'), true)
            ? $args['orderby']
            : 'created_at';
        $order = strtoupper((string) $args['order']) === 'ASC' ? 'ASC' : 'DESC';

        $sql = "SELECT * FROM {$table}";
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= " ORDER BY {$orderby} {$order}";

        $limit = max(0, (int) $args['limit']);
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

    public static function count(array $args = array())
    {
        global $wpdb;
        $table = self::table_name();

        $where = array('1=1');
        $params = array();

        if (!empty($args['member_id'])) {
            $where[] = 'member_id = %d';
            $params[] = (int) $args['member_id'];
        }

        if (!empty($args['status'])) {
            $where[] = 'status = %s';
            $params[] = self::normalize_status((string) $args['status']);
        }

        $sql = "SELECT COUNT(*) FROM {$table} WHERE " . implode(' AND ', $where);
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        return (int) $wpdb->get_var($sql);
    }

    public static function get_by_id(int $id): ?object
    {
        global $wpdb;
        $table = self::table_name();
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
        return $row ?: null;
    }

    public static function create($data)
    {
        global $wpdb;
        $table = self::table_name();

        $memberId = isset($data['member_id']) ? (int) $data['member_id'] : 0;
        if ($memberId <= 0) {
            return new WP_Error('invalid_member', __('Membre invalide.', 'mj-member'));
        }

        $title = isset($data['title']) ? sanitize_text_field((string) $data['title']) : '';
        if ($title === '') {
            return new WP_Error('missing_title', __('Le titre est requis.', 'mj-member'));
        }

        $requestType = isset($data['request_type']) ? sanitize_text_field((string) $data['request_type']) : '';
        if ($requestType === '') {
            return new WP_Error('missing_type', __('Le type de demande est requis.', 'mj-member'));
        }

        $slots = self::normalize_slots($data['slots'] ?? array());
        $firstSlot = $slots[0] ?? array('date' => '', 'start' => '', 'end' => '');
        $weekStart = isset($data['week_start']) ? sanitize_text_field((string) $data['week_start']) : '';
        $slotStart = isset($data['slot_start']) ? sanitize_text_field((string) $data['slot_start']) : $firstSlot['start'];
        $slotEnd = isset($data['slot_end']) ? sanitize_text_field((string) $data['slot_end']) : $firstSlot['end'];

        $memberIds = self::normalize_member_ids($data['assigned_member_ids'] ?? array());
        $assignedToMemberId = isset($data['assigned_to_member_id']) ? (int) $data['assigned_to_member_id'] : ($memberIds[0] ?? 0);

        $insert = array(
            'member_id' => $memberId,
            'assigned_to_member_id' => $assignedToMemberId,
            'request_type' => $requestType,
            'status' => self::normalize_status((string) ($data['status'] ?? self::STATUS_PENDING)),
            'room_id' => isset($data['room_id']) ? (int) $data['room_id'] : 0,
            'is_outdoor' => !empty($data['is_outdoor']) ? 1 : 0,
            'title' => $title,
            'description' => isset($data['description']) ? sanitize_textarea_field((string) $data['description']) : '',
            'age_range' => isset($data['age_range']) ? sanitize_text_field((string) $data['age_range']) : '',
            'week_start' => $weekStart,
            'slot_day' => isset($data['slot_day']) ? max(0, min(6, (int) $data['slot_day'])) : 0,
            'slot_start' => $slotStart,
            'slot_end' => $slotEnd,
            'slots_json' => wp_json_encode($slots),
            'assigned_member_ids_json' => wp_json_encode($memberIds),
            'room_options_json' => isset($data['room_options_json']) ? wp_json_encode($data['room_options_json']) : wp_json_encode(array()),
            'materials_json' => isset($data['materials_json']) ? wp_json_encode($data['materials_json']) : wp_json_encode(array()),
            'status_note' => isset($data['status_note']) ? sanitize_textarea_field((string) $data['status_note']) : '',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        );

        $ok = $wpdb->insert($table, $insert);

        if ($ok === false) {
            return new WP_Error('db_insert_failed', __('Impossible de créer la demande.', 'mj-member'));
        }

        return (int) $wpdb->insert_id;
    }

    public static function update($id, $data)
    {
        global $wpdb;
        $table = self::table_name();

        $id = (int) $id;
        if ($id <= 0) {
            return new WP_Error('invalid_id', __('Identifiant invalide.', 'mj-member'));
        }

        $updates = array();
        $formats = array();

        if (isset($data['assigned_to_member_id'])) {
            $updates['assigned_to_member_id'] = (int) $data['assigned_to_member_id'];
            $formats[] = '%d';
        }

        if (isset($data['assigned_member_ids'])) {
            $memberIds = self::normalize_member_ids($data['assigned_member_ids']);
            $updates['assigned_member_ids_json'] = wp_json_encode($memberIds);
            $formats[] = '%s';

            if (!isset($data['assigned_to_member_id'])) {
                $updates['assigned_to_member_id'] = $memberIds[0] ?? 0;
                $formats[] = '%d';
            }
        }

        if (isset($data['status'])) {
            $updates['status'] = self::normalize_status((string) $data['status']);
            $formats[] = '%s';
        }

        if (isset($data['room_id'])) {
            $updates['room_id'] = (int) $data['room_id'];
            $formats[] = '%d';
        }

        if (isset($data['is_outdoor'])) {
            $updates['is_outdoor'] = !empty($data['is_outdoor']) ? 1 : 0;
            $formats[] = '%d';
        }

        if (isset($data['title'])) {
            $updates['title'] = sanitize_text_field((string) $data['title']);
            $formats[] = '%s';
        }

        if (isset($data['description'])) {
            $updates['description'] = sanitize_textarea_field((string) $data['description']);
            $formats[] = '%s';
        }

        if (isset($data['age_range'])) {
            $updates['age_range'] = sanitize_text_field((string) $data['age_range']);
            $formats[] = '%s';
        }

        if (isset($data['week_start'])) {
            $updates['week_start'] = sanitize_text_field((string) $data['week_start']);
            $formats[] = '%s';
        }

        if (isset($data['slot_day'])) {
            $updates['slot_day'] = max(0, min(6, (int) $data['slot_day']));
            $formats[] = '%d';
        }

        if (isset($data['slot_start'])) {
            $updates['slot_start'] = sanitize_text_field((string) $data['slot_start']);
            $formats[] = '%s';
        }

        if (isset($data['slot_end'])) {
            $updates['slot_end'] = sanitize_text_field((string) $data['slot_end']);
            $formats[] = '%s';
        }

        if (isset($data['slots'])) {
            $slots = self::normalize_slots($data['slots']);
            $firstSlot = $slots[0] ?? array('date' => '', 'start' => '', 'end' => '');
            $updates['slots_json'] = wp_json_encode($slots);
            $formats[] = '%s';

            if (!isset($data['slot_start'])) {
                $updates['slot_start'] = $firstSlot['start'];
                $formats[] = '%s';
            }

            if (!isset($data['slot_end'])) {
                $updates['slot_end'] = $firstSlot['end'];
                $formats[] = '%s';
            }
        }

        if (isset($data['room_options_json'])) {
            $updates['room_options_json'] = wp_json_encode($data['room_options_json']);
            $formats[] = '%s';
        }

        if (isset($data['materials_json'])) {
            $updates['materials_json'] = wp_json_encode($data['materials_json']);
            $formats[] = '%s';
        }

        if (isset($data['status_note'])) {
            $updates['status_note'] = sanitize_textarea_field((string) $data['status_note']);
            $formats[] = '%s';
        }

        if (empty($updates)) {
            return true;
        }

        $updates['updated_at'] = current_time('mysql');
        $formats[] = '%s';

        $ok = $wpdb->update($table, $updates, array('id' => $id), $formats, array('%d'));
        if ($ok === false) {
            return new WP_Error('db_update_failed', __('Impossible de mettre à jour la demande.', 'mj-member'));
        }

        return true;
    }

    /**
     * @return array<int,array{date:string,start:string,end:string}>
     */
    public static function get_slots(object $request): array
    {
        $slots = self::normalize_slots($request->slots_json ?? '');
        if (!empty($slots)) {
            return $slots;
        }

        $date = '';
        if (!empty($request->week_start) && isset($request->slot_day)) {
            $base = strtotime((string) $request->week_start);
            if ($base !== false) {
                $date = wp_date('Y-m-d', $base + ((int) $request->slot_day * DAY_IN_SECONDS));
            }
        }

        if ($date === '' && empty($request->slot_start) && empty($request->slot_end)) {
            return array();
        }

        return array(array(
            'date' => $date,
            'start' => (string) ($request->slot_start ?? ''),
            'end' => (string) ($request->slot_end ?? ''),
        ));
    }

    /**
     * @return array<int,int>
     */
    public static function get_assigned_member_ids(object $request): array
    {
        $ids = self::normalize_member_ids($request->assigned_member_ids_json ?? '');
        if (!empty($ids)) {
            return $ids;
        }

        $legacyId = isset($request->assigned_to_member_id) ? (int) $request->assigned_to_member_id : 0;
        return $legacyId > 0 ? array($legacyId) : array();
    }

    public static function delete($id)
    {
        global $wpdb;
        $table = self::table_name();
        $ok = $wpdb->delete($table, array('id' => (int) $id), array('%d'));
        if ($ok === false) {
            return new WP_Error('db_delete_failed', __('Impossible de supprimer la demande.', 'mj-member'));
        }

        return true;
    }
}
