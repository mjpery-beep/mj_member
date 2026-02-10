<?php

namespace Mj\Member\Classes\Crud;

use Mj\Member\Classes\MjTools;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * CRUD class for leave quotas (yearly quotas per member and type).
 */
class MjLeaveQuotas extends MjTools
{
    /**
     * Get table name.
     *
     * @return string
     */
    public static function table_name(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'mj_leave_quotas';
    }

    /**
     * Get quota for a member, year, and type.
     *
     * @param int $member_id
     * @param int $year
     * @param int $type_id
     * @return int
     */
    public static function get_quota(int $member_id, int $year, int $type_id): int
    {
        global $wpdb;
        $table = self::table_name();

        $quota = $wpdb->get_var($wpdb->prepare(
            "SELECT quota FROM {$table} WHERE member_id = %d AND year = %d AND type_id = %d",
            $member_id,
            $year,
            $type_id
        ));

        return $quota !== null ? (int) $quota : 0;
    }

    /**
     * Get all quotas for a member and year.
     *
     * @param int $member_id
     * @param int $year
     * @return array<string,int> Associative array with type slug as key and quota as value
     */
    public static function get_quotas_for_member(int $member_id, int $year): array
    {
        global $wpdb;
        $table = self::table_name();
        $types_table = $wpdb->prefix . 'mj_leave_types';

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT t.slug, COALESCE(q.quota, 0) as quota
             FROM {$types_table} t
             LEFT JOIN {$table} q ON q.type_id = t.id AND q.member_id = %d AND q.year = %d
             WHERE t.is_active = 1
             ORDER BY t.sort_order",
            $member_id,
            $year
        ));

        $quotas = [];
        if (is_array($results)) {
            foreach ($results as $row) {
                $quotas[$row->slug] = (int) $row->quota;
            }
        }

        return $quotas;
    }

    /**
     * Set quota for a member, year, and type (insert or update).
     *
     * @param int $member_id
     * @param int $year
     * @param int $type_id
     * @param int $quota
     * @return bool
     */
    public static function set_quota(int $member_id, int $year, int $type_id, int $quota): bool
    {
        global $wpdb;
        $table = self::table_name();

        // Use INSERT ... ON DUPLICATE KEY UPDATE
        $result = $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table} (member_id, year, type_id, quota)
             VALUES (%d, %d, %d, %d)
             ON DUPLICATE KEY UPDATE quota = VALUES(quota), updated_at = CURRENT_TIMESTAMP",
            $member_id,
            $year,
            $type_id,
            $quota
        ));

        return $result !== false;
    }

    /**
     * Set multiple quotas for a member and year.
     *
     * @param int $member_id
     * @param int $year
     * @param array<int,int> $quotas Array with type_id as key and quota as value
     * @return bool
     */
    public static function set_quotas(int $member_id, int $year, array $quotas): bool
    {
        $success = true;
        foreach ($quotas as $type_id => $quota) {
            if (!self::set_quota($member_id, $year, (int) $type_id, (int) $quota)) {
                $success = false;
            }
        }
        return $success;
    }

    /**
     * Get all quotas for a year (all members).
     *
     * @param int $year
     * @return array<int,array<string,int>> Array with member_id as key
     */
    public static function get_all_for_year(int $year): array
    {
        global $wpdb;
        $table = self::table_name();
        $types_table = $wpdb->prefix . 'mj_leave_types';

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT q.member_id, t.slug, q.quota
             FROM {$table} q
             JOIN {$types_table} t ON t.id = q.type_id
             WHERE q.year = %d",
            $year
        ));

        $quotas = [];
        if (is_array($results)) {
            foreach ($results as $row) {
                $member_id = (int) $row->member_id;
                if (!isset($quotas[$member_id])) {
                    $quotas[$member_id] = [];
                }
                $quotas[$member_id][$row->slug] = (int) $row->quota;
            }
        }

        return $quotas;
    }

    /**
     * Delete all quotas for a member.
     *
     * @param int $member_id
     * @return bool
     */
    public static function delete_for_member(int $member_id): bool
    {
        global $wpdb;
        $table = self::table_name();

        $result = $wpdb->delete($table, ['member_id' => $member_id], ['%d']);

        return $result !== false;
    }

    /**
     * Copy quotas from one year to another for a member.
     *
     * @param int $member_id
     * @param int $from_year
     * @param int $to_year
     * @return bool
     */
    public static function copy_quotas(int $member_id, int $from_year, int $to_year): bool
    {
        global $wpdb;
        $table = self::table_name();

        // Get quotas from source year
        $source_quotas = $wpdb->get_results($wpdb->prepare(
            "SELECT type_id, quota FROM {$table} WHERE member_id = %d AND year = %d",
            $member_id,
            $from_year
        ));

        if (empty($source_quotas)) {
            return false;
        }

        $success = true;
        foreach ($source_quotas as $row) {
            if (!self::set_quota($member_id, $to_year, (int) $row->type_id, (int) $row->quota)) {
                $success = false;
            }
        }

        return $success;
    }
}
