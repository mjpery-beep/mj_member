<?php

namespace Mj\Member\Classes\Crud;

use Mj\Member\Classes\MjTools;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Join table tracking the additional (non-default) guardians of a "jeune"
 * member. The default guardian keeps living on members.guardian_id (see
 * MjMembers::resolveGuardianId()); this table only stores the extra ones
 * shown in the widget gestionnaire.
 */
final class MjMemberGuardians extends MjTools
{
    private const TABLE = 'mj_member_guardians';

    public static function table_name(): string
    {
        if (function_exists('mj_member_get_member_guardians_table_name')) {
            return mj_member_get_member_guardians_table_name();
        }

        return self::getTableName(self::TABLE);
    }

    /**
     * @return array<int,int> additional guardian ids for a member, oldest first
     */
    public static function get_for_member(int $memberId): array
    {
        global $wpdb;
        if ($memberId <= 0) {
            return array();
        }

        $table = self::table_name();
        $rows = $wpdb->get_col($wpdb->prepare(
            "SELECT guardian_id FROM {$table} WHERE member_id = %d ORDER BY created_at ASC, id ASC",
            $memberId
        ));

        return array_map('intval', $rows ?: array());
    }

    /**
     * @return array<int,int> member ids for which this member is an additional guardian
     */
    public static function get_for_guardian(int $guardianId): array
    {
        global $wpdb;
        if ($guardianId <= 0) {
            return array();
        }

        $table = self::table_name();
        $rows = $wpdb->get_col($wpdb->prepare(
            "SELECT member_id FROM {$table} WHERE guardian_id = %d ORDER BY created_at ASC, id ASC",
            $guardianId
        ));

        return array_map('intval', $rows ?: array());
    }

    public static function exists(int $memberId, int $guardianId): bool
    {
        global $wpdb;
        if ($memberId <= 0 || $guardianId <= 0) {
            return false;
        }

        $table = self::table_name();
        $id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE member_id = %d AND guardian_id = %d",
            $memberId,
            $guardianId
        ));

        return $id > 0;
    }

    /**
     * Idempotent insert.
     */
    public static function add(int $memberId, int $guardianId): bool
    {
        global $wpdb;
        if ($memberId <= 0 || $guardianId <= 0 || $memberId === $guardianId) {
            return false;
        }

        if (self::exists($memberId, $guardianId)) {
            return true;
        }

        $table = self::table_name();
        $inserted = $wpdb->insert(
            $table,
            array(
                'member_id' => $memberId,
                'guardian_id' => $guardianId,
                'created_at' => current_time('mysql'),
            ),
            array('%d', '%d', '%s')
        );

        return $inserted !== false;
    }

    public static function remove(int $memberId, int $guardianId): bool
    {
        global $wpdb;
        if ($memberId <= 0 || $guardianId <= 0) {
            return false;
        }

        $table = self::table_name();
        $deleted = $wpdb->delete(
            $table,
            array('member_id' => $memberId, 'guardian_id' => $guardianId),
            array('%d', '%d')
        );

        return $deleted !== false;
    }

    /**
     * Removes every row involving this member, either as the "jeune" or as
     * an additional guardian. Used when a member is deleted.
     */
    public static function remove_all_for_member(int $memberId): void
    {
        global $wpdb;
        if ($memberId <= 0) {
            return;
        }

        $table = self::table_name();
        $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE member_id = %d OR guardian_id = %d", $memberId, $memberId));
    }
}
