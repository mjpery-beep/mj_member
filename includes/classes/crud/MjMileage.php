<?php

namespace Mj\Member\Classes\Crud;

use Mj\Member\Classes\MjRoles;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * CRUD repository for mileage expense claims (frais kilométriques).
 */
class MjMileage
{
    const TABLE = 'mj_mileage';

    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_REIMBURSED = 'reimbursed';

    /**
     * @return string
     */
    public static function table_name(): string
    {
        return mj_member_get_mileage_table_name();
    }

    /**
     * @return array<string, string>
     */
    public static function get_status_labels(): array
    {
        return array(
            self::STATUS_PENDING    => __('En attente', 'mj-member'),
            self::STATUS_APPROVED   => __('Approuvé', 'mj-member'),
            self::STATUS_REJECTED   => __('Refusé', 'mj-member'),
            self::STATUS_REIMBURSED => __('Remboursé', 'mj-member'),
        );
    }

    /**
     * @param array<string, mixed> $args
     * @return array<int, object>
     */
    public static function get_all(array $args = array()): array
    {
        global $wpdb;
        $table = self::table_name();
        if (!mj_member_table_exists($table)) {
            return array();
        }

        $where = array();
        $values = array();

        if (!empty($args['member_id'])) {
            $where[] = 'member_id = %d';
            $values[] = (int) $args['member_id'];
        }

        if (!empty($args['status'])) {
            $where[] = 'status = %s';
            $values[] = sanitize_text_field($args['status']);
        }

        $where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $orderby = isset($args['orderby']) ? sanitize_key($args['orderby']) : 'trip_date';
        $allowed = array('trip_date', 'created_at', 'distance_km', 'total_cost', 'status');
        if (!in_array($orderby, $allowed, true)) {
            $orderby = 'trip_date';
        }
        $order = isset($args['order']) && strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        $limit_sql = '';
        if (isset($args['limit']) && (int) $args['limit'] > 0) {
            $limit_sql = sprintf('LIMIT %d', (int) $args['limit']);
        }

        $sql = "SELECT * FROM {$table} {$where_sql} ORDER BY {$orderby} {$order} {$limit_sql}";
        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, ...$values);
        }

        $results = $wpdb->get_results($sql);
        return is_array($results) ? $results : array();
    }

    /**
     * @param int $id
     * @return object|null
     */
    public static function get_by_id(int $id): ?object
    {
        if ($id <= 0) {
            return null;
        }

        global $wpdb;
        $table = self::table_name();
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
    }

    /**
     * @param array<string, mixed> $data
     * @return int|WP_Error
     */
    public static function create(array $data)
    {
        global $wpdb;
        $table = self::table_name();

        $member_id   = isset($data['member_id']) ? (int) $data['member_id'] : 0;
        $trip_date   = isset($data['trip_date']) ? sanitize_text_field($data['trip_date']) : '';
        $origin      = isset($data['origin']) ? sanitize_text_field($data['origin']) : '';
        $origin_location_id = isset($data['origin_location_id']) ? (int) $data['origin_location_id'] : null;
        $destination = isset($data['destination']) ? sanitize_text_field($data['destination']) : '';
        $destination_location_id = isset($data['destination_location_id']) ? (int) $data['destination_location_id'] : null;
        $distance_km = isset($data['distance_km']) ? round((float) $data['distance_km'], 2) : 0;
        $cost_per_km = isset($data['cost_per_km']) ? round((float) $data['cost_per_km'], 4) : 0;
        $total_cost  = round($distance_km * $cost_per_km, 2);
        $description = isset($data['description']) ? sanitize_textarea_field($data['description']) : '';
        $round_trip  = !empty($data['round_trip']) ? 1 : 0;

        if ($member_id <= 0) {
            return new WP_Error('mj_mileage_invalid_member', 'Membre invalide.');
        }
        if ($distance_km <= 0) {
            return new WP_Error('mj_mileage_invalid_distance', 'La distance doit être supérieure à 0.');
        }

        $insert = array(
            'member_id'               => $member_id,
            'trip_date'               => $trip_date,
            'origin'                  => $origin,
            'origin_location_id'      => $origin_location_id,
            'destination'             => $destination,
            'destination_location_id' => $destination_location_id,
            'distance_km'             => $distance_km,
            'cost_per_km'             => $cost_per_km,
            'total_cost'              => $total_cost,
            'description'             => $description,
            'round_trip'              => $round_trip,
            'status'                  => self::STATUS_PENDING,
            'created_at'              => current_time('mysql'),
            'updated_at'              => current_time('mysql'),
        );

        $formats = array('%d', '%s', '%s', '%d', '%s', '%d', '%f', '%f', '%f', '%s', '%d', '%s', '%s', '%s');

        $result = $wpdb->insert($table, $insert, $formats);
        if ($result === false) {
            return new WP_Error('mj_mileage_insert_failed', 'Impossible de créer le trajet.');
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * @param int $id
     * @param array<string, mixed> $data
     * @return true|WP_Error
     */
    public static function update(int $id, array $data)
    {
        global $wpdb;
        $table = self::table_name();

        $current = self::get_by_id($id);
        if (!$current) {
            return new WP_Error('mj_mileage_not_found', 'Trajet introuvable.');
        }

        $update = array();
        $formats = array();

        if (isset($data['trip_date'])) {
            $update['trip_date'] = sanitize_text_field($data['trip_date']);
            $formats[] = '%s';
        }
        if (isset($data['origin'])) {
            $update['origin'] = sanitize_text_field($data['origin']);
            $formats[] = '%s';
        }
        if (array_key_exists('origin_location_id', $data)) {
            $update['origin_location_id'] = $data['origin_location_id'] ? (int) $data['origin_location_id'] : null;
            $formats[] = '%d';
        }
        if (isset($data['destination'])) {
            $update['destination'] = sanitize_text_field($data['destination']);
            $formats[] = '%s';
        }
        if (array_key_exists('destination_location_id', $data)) {
            $update['destination_location_id'] = $data['destination_location_id'] ? (int) $data['destination_location_id'] : null;
            $formats[] = '%d';
        }
        if (isset($data['distance_km'])) {
            $update['distance_km'] = round((float) $data['distance_km'], 2);
            $formats[] = '%f';
        }
        if (isset($data['cost_per_km'])) {
            $update['cost_per_km'] = round((float) $data['cost_per_km'], 4);
            $formats[] = '%f';
        }
        if (isset($data['description'])) {
            $update['description'] = sanitize_textarea_field($data['description']);
            $formats[] = '%s';
        }
        if (isset($data['round_trip'])) {
            $update['round_trip'] = !empty($data['round_trip']) ? 1 : 0;
            $formats[] = '%d';
        }
        if (isset($data['status'])) {
            $update['status'] = sanitize_text_field($data['status']);
            $formats[] = '%s';
        }
        if (isset($data['reviewed_by'])) {
            $update['reviewed_by'] = (int) $data['reviewed_by'];
            $formats[] = '%d';
        }
        if (isset($data['reviewed_at'])) {
            $update['reviewed_at'] = sanitize_text_field($data['reviewed_at']);
            $formats[] = '%s';
        }
        if (isset($data['reviewer_comment'])) {
            $update['reviewer_comment'] = sanitize_textarea_field($data['reviewer_comment']);
            $formats[] = '%s';
        }

        // Recalculate total if distance or cost changed
        $distance = isset($update['distance_km']) ? $update['distance_km'] : (float) $current->distance_km;
        $cost = isset($update['cost_per_km']) ? $update['cost_per_km'] : (float) $current->cost_per_km;
        $update['total_cost'] = round($distance * $cost, 2);
        $formats[] = '%f';

        $update['updated_at'] = current_time('mysql');
        $formats[] = '%s';

        $result = $wpdb->update($table, $update, array('id' => $id), $formats, array('%d'));
        if ($result === false) {
            return new WP_Error('mj_mileage_update_failed', 'Impossible de mettre à jour le trajet.');
        }

        return true;
    }

    /**
     * @param int $id
     * @return true|WP_Error
     */
    public static function delete(int $id)
    {
        if ($id <= 0) {
            return new WP_Error('mj_mileage_invalid_id', 'Identifiant invalide.');
        }

        global $wpdb;
        $table = self::table_name();
        $deleted = $wpdb->delete($table, array('id' => $id), array('%d'));

        if ($deleted === false) {
            return new WP_Error('mj_mileage_delete_failed', 'Suppression impossible.');
        }

        return true;
    }

    /**
     * Enrich mileage rows with member names.
     *
     * @param array<int, object> $rows
     * @return array<int, object>
     */
    public static function enrich(array $rows): array
    {
        if (empty($rows)) {
            return array();
        }

        $memberIds = array_unique(array_map(function ($r) {
            return (int) $r->member_id;
        }, $rows));

        global $wpdb;
        $members_table = MjMembers::getTableName(MjMembers::TABLE_NAME);
        if (!$members_table || !\mj_member_table_exists($members_table)) {
            return $rows;
        }

        $placeholders = implode(',', array_fill(0, count($memberIds), '%d'));
        $sql = $wpdb->prepare(
            "SELECT id, first_name, last_name, photo_id FROM {$members_table} WHERE id IN ({$placeholders})",
            ...$memberIds
        );
        $memberRows = $wpdb->get_results($sql, OBJECT_K);

        foreach ($rows as $row) {
            $mid = (int) $row->member_id;
            if (isset($memberRows[$mid])) {
                $row->member_name = trim($memberRows[$mid]->first_name . ' ' . $memberRows[$mid]->last_name);
                $row->member_photo_id = $memberRows[$mid]->photo_id ?? null;
            } else {
                $row->member_name = '';
                $row->member_photo_id = null;
            }
        }

        return $rows;
    }

    /**
     * Get summary totals grouped by member.
     *
     * @return array<int, object>
     */
    public static function get_summary_by_member(): array
    {
        global $wpdb;
        $table = self::table_name();
        if (!mj_member_table_exists($table)) {
            return array();
        }

        $sql = "SELECT member_id,
                       COUNT(*) as total_trips,
                       SUM(distance_km) as total_km,
                       SUM(total_cost) as total_amount,
                       SUM(CASE WHEN status = 'pending' THEN total_cost ELSE 0 END) as pending_amount,
                       SUM(CASE WHEN status = 'reimbursed' THEN total_cost ELSE 0 END) as reimbursed_amount
                FROM {$table}
                GROUP BY member_id";

        $results = $wpdb->get_results($sql);
        return is_array($results) ? $results : array();
    }
}
