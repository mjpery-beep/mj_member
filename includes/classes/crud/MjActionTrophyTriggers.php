<?php

namespace Mj\Member\Classes\Crud;

use Mj\Member\Classes\MjTools;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * CRUD pour les règles de progression trophée basées sur les actions.
 */
final class MjActionTrophyTriggers extends MjTools implements CrudRepositoryInterface
{
    private const TABLE = 'mj_action_trophy_triggers';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_ARCHIVED = 'archived';

    public static function table_name(): string
    {
        if (function_exists('mj_member_get_action_trophy_triggers_table_name')) {
            return mj_member_get_action_trophy_triggers_table_name();
        }

        return self::getTableName(self::TABLE);
    }

    /**
     * @param array<string,mixed> $args
     * @return array<int,array<string,mixed>>
     */
    public static function get_all(array $args = array())
    {
        global $wpdb;
        $table = self::table_name();

        $defaults = array(
            'action_type_id' => 0,
            'trophy_id' => 0,
            'status' => self::STATUS_ACTIVE,
            'orderby' => 'id',
            'order' => 'ASC',
        );
        $args = wp_parse_args($args, $defaults);

        $where = array();
        $params = array();

        if ((int) $args['action_type_id'] > 0) {
            $where[] = 'action_type_id = %d';
            $params[] = (int) $args['action_type_id'];
        }

        if ((int) $args['trophy_id'] > 0) {
            $where[] = 'trophy_id = %d';
            $params[] = (int) $args['trophy_id'];
        }

        if (!empty($args['status'])) {
            $status = self::normalize_status($args['status']);
            if ($status !== '') {
                $where[] = 'status = %s';
                $params[] = $status;
            }
        }

        $sql = "SELECT * FROM {$table}";
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $allowed_orderby = array('id', 'action_type_id', 'trophy_id', 'bronze_threshold', 'silver_threshold', 'gold_threshold');
        $orderby = sanitize_key((string) $args['orderby']);
        if (!in_array($orderby, $allowed_orderby, true)) {
            $orderby = 'id';
        }

        $order = strtoupper((string) $args['order']) === 'DESC' ? 'DESC' : 'ASC';
        $sql .= " ORDER BY {$orderby} {$order}";

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        $rows = $wpdb->get_results($sql, ARRAY_A);
        if (empty($rows)) {
            return array();
        }

        return array_map(array(__CLASS__, 'format_row'), $rows);
    }

    /**
     * @param array<string,mixed> $args
     * @return int
     */
    public static function count(array $args = array())
    {
        global $wpdb;
        $table = self::table_name();

        $defaults = array(
            'action_type_id' => 0,
            'trophy_id' => 0,
            'status' => self::STATUS_ACTIVE,
        );
        $args = wp_parse_args($args, $defaults);

        $where = array();
        $params = array();

        if ((int) $args['action_type_id'] > 0) {
            $where[] = 'action_type_id = %d';
            $params[] = (int) $args['action_type_id'];
        }

        if ((int) $args['trophy_id'] > 0) {
            $where[] = 'trophy_id = %d';
            $params[] = (int) $args['trophy_id'];
        }

        if (!empty($args['status'])) {
            $status = self::normalize_status((string) $args['status']);
            if ($status !== '') {
                $where[] = 'status = %s';
                $params[] = $status;
            }
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
     * @return array<int,array<string,mixed>>
     */
    public static function get_for_action(int $actionTypeId): array
    {
        return self::get_all(array(
            'action_type_id' => $actionTypeId,
            'status' => self::STATUS_ACTIVE,
            'orderby' => 'trophy_id',
            'order' => 'ASC',
        ));
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function get_for_trophy(int $trophyId): array
    {
        return self::get_all(array(
            'trophy_id' => $trophyId,
            'status' => self::STATUS_ACTIVE,
            'orderby' => 'action_type_id',
            'order' => 'ASC',
        ));
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function get_trigger(int $actionTypeId, int $trophyId): ?array
    {
        global $wpdb;
        $table = self::table_name();

        if ($actionTypeId <= 0 || $trophyId <= 0) {
            return null;
        }

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE action_type_id = %d AND trophy_id = %d LIMIT 1",
            $actionTypeId,
            $trophyId
        ), ARRAY_A);

        if (!$row) {
            return null;
        }

        return self::format_row($row);
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return true|WP_Error
     */
    public static function replace_for_action(int $actionTypeId, array $rows)
    {
        global $wpdb;
        $table = self::table_name();

        if ($actionTypeId <= 0) {
            return new WP_Error('mj_action_trophy_trigger_invalid_action', __('Action invalide.', 'mj-member'));
        }

        $wpdb->delete($table, array('action_type_id' => $actionTypeId), array('%d'));

        foreach ($rows as $row) {
            $trophyId = isset($row['trophy_id']) ? (int) $row['trophy_id'] : 0;
            $bronze = isset($row['bronze_threshold']) ? (int) $row['bronze_threshold'] : 0;
            $silver = isset($row['silver_threshold']) ? (int) $row['silver_threshold'] : 0;
            $gold = isset($row['gold_threshold']) ? (int) $row['gold_threshold'] : 0;

            if ($trophyId <= 0 || $bronze <= 0 || $silver <= 0 || $gold <= 0) {
                continue;
            }

            if (!($bronze <= $silver && $silver <= $gold)) {
                continue;
            }

            $wpdb->insert(
                $table,
                array(
                    'action_type_id' => $actionTypeId,
                    'trophy_id' => $trophyId,
                    'bronze_threshold' => $bronze,
                    'silver_threshold' => $silver,
                    'gold_threshold' => $gold,
                    'status' => self::STATUS_ACTIVE,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql'),
                ),
                array('%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s')
            );
        }

        return true;
    }

    /**
     * @param array<string,mixed> $data
     * @return int|WP_Error
     */
    public static function create($data)
    {
        global $wpdb;
        $table = self::table_name();

        if (!is_array($data)) {
            return new WP_Error('mj_action_trophy_trigger_invalid_data', __('Données invalides.', 'mj-member'));
        }

        $actionTypeId = isset($data['action_type_id']) ? (int) $data['action_type_id'] : 0;
        $trophyId = isset($data['trophy_id']) ? (int) $data['trophy_id'] : 0;
        $bronze = isset($data['bronze_threshold']) ? (int) $data['bronze_threshold'] : 0;
        $silver = isset($data['silver_threshold']) ? (int) $data['silver_threshold'] : 0;
        $gold = isset($data['gold_threshold']) ? (int) $data['gold_threshold'] : 0;

        if ($actionTypeId <= 0 || $trophyId <= 0 || $bronze <= 0 || $silver <= 0 || $gold <= 0) {
            return new WP_Error('mj_action_trophy_trigger_missing_fields', __('Paramètres de règle incomplets.', 'mj-member'));
        }

        if (!($bronze <= $silver && $silver <= $gold)) {
            return new WP_Error('mj_action_trophy_trigger_invalid_thresholds', __('Les seuils doivent être croissants.', 'mj-member'));
        }

        $status = isset($data['status']) ? self::normalize_status((string) $data['status']) : self::STATUS_ACTIVE;
        if ($status === '') {
            $status = self::STATUS_ACTIVE;
        }

        $inserted = $wpdb->insert(
            $table,
            array(
                'action_type_id' => $actionTypeId,
                'trophy_id' => $trophyId,
                'bronze_threshold' => $bronze,
                'silver_threshold' => $silver,
                'gold_threshold' => $gold,
                'status' => $status,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ),
            array('%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s')
        );

        if ($inserted === false) {
            return new WP_Error('mj_action_trophy_trigger_create_failed', __('Impossible de créer la règle.', 'mj-member'));
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * @param int $id
     * @param array<string,mixed> $data
     * @return true|WP_Error
     */
    public static function update($id, $data)
    {
        global $wpdb;
        $table = self::table_name();

        $id = (int) $id;
        if ($id <= 0) {
            return new WP_Error('mj_action_trophy_trigger_invalid_id', __('Identifiant invalide.', 'mj-member'));
        }

        if (!is_array($data)) {
            return new WP_Error('mj_action_trophy_trigger_invalid_data', __('Données invalides.', 'mj-member'));
        }

        $payload = array();
        $formats = array();

        if (isset($data['bronze_threshold'])) {
            $payload['bronze_threshold'] = max(1, (int) $data['bronze_threshold']);
            $formats[] = '%d';
        }
        if (isset($data['silver_threshold'])) {
            $payload['silver_threshold'] = max(1, (int) $data['silver_threshold']);
            $formats[] = '%d';
        }
        if (isset($data['gold_threshold'])) {
            $payload['gold_threshold'] = max(1, (int) $data['gold_threshold']);
            $formats[] = '%d';
        }
        if (isset($data['status'])) {
            $status = self::normalize_status((string) $data['status']);
            if ($status !== '') {
                $payload['status'] = $status;
                $formats[] = '%s';
            }
        }

        if (empty($payload)) {
            return true;
        }

        $payload['updated_at'] = current_time('mysql');
        $formats[] = '%s';

        $updated = $wpdb->update($table, $payload, array('id' => $id), $formats, array('%d'));
        if ($updated === false) {
            return new WP_Error('mj_action_trophy_trigger_update_failed', __('Impossible de mettre à jour la règle.', 'mj-member'));
        }

        return true;
    }

    /**
     * @param int $id
     * @return true|WP_Error
     */
    public static function delete($id)
    {
        global $wpdb;
        $table = self::table_name();

        $id = (int) $id;
        if ($id <= 0) {
            return new WP_Error('mj_action_trophy_trigger_invalid_id', __('Identifiant invalide.', 'mj-member'));
        }

        $deleted = $wpdb->delete($table, array('id' => $id), array('%d'));
        if ($deleted === false) {
            return new WP_Error('mj_action_trophy_trigger_delete_failed', __('Impossible de supprimer la règle.', 'mj-member'));
        }

        return true;
    }

    /**
     * @param int $id
     * @return array<string,mixed>|null
     */
    public static function get(int $id): ?array
    {
        global $wpdb;
        $table = self::table_name();

        $id = (int) $id;
        if ($id <= 0) {
            return null;
        }

        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id), ARRAY_A);
        if (!$row) {
            return null;
        }

        return self::format_row($row);
    }

    /**
     * @param string $status
     * @return string
     */
    private static function normalize_status(string $status): string
    {
        $candidate = sanitize_key($status);
        if ($candidate === self::STATUS_ACTIVE || $candidate === self::STATUS_ARCHIVED) {
            return $candidate;
        }
        return '';
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private static function format_row(array $row): array
    {
        return array(
            'id' => isset($row['id']) ? (int) $row['id'] : 0,
            'action_type_id' => isset($row['action_type_id']) ? (int) $row['action_type_id'] : 0,
            'trophy_id' => isset($row['trophy_id']) ? (int) $row['trophy_id'] : 0,
            'bronze_threshold' => isset($row['bronze_threshold']) ? (int) $row['bronze_threshold'] : 0,
            'silver_threshold' => isset($row['silver_threshold']) ? (int) $row['silver_threshold'] : 0,
            'gold_threshold' => isset($row['gold_threshold']) ? (int) $row['gold_threshold'] : 0,
            'status' => isset($row['status']) ? (string) $row['status'] : self::STATUS_ACTIVE,
            'created_at' => isset($row['created_at']) ? (string) $row['created_at'] : '',
            'updated_at' => isset($row['updated_at']) ? (string) $row['updated_at'] : '',
        );
    }
}
