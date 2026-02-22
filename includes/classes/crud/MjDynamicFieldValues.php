<?php

namespace Mj\Member\Classes\Crud;

use Mj\Member\Classes\MjTools;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * CRUD repository for dynamic field values stored per member.
 */
class MjDynamicFieldValues extends MjTools
{
    const TABLE_NAME = 'mj_dynamic_field_values';

    /**
     * @return string
     */
    private static function table_name(): string
    {
        return self::getTableName(self::TABLE_NAME);
    }

    /**
     * Get all values for a given member.
     *
     * @param int $member_id
     * @return array<int,object>
     */
    public static function getByMember(int $member_id): array
    {
        $wpdb = self::getWpdb();
        $table = self::table_name();

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT v.*, f.title AS field_title, f.field_type, f.slug AS field_slug
             FROM {$table} v
             INNER JOIN %i f ON f.id = v.field_id
             WHERE v.member_id = %d
             ORDER BY f.sort_order ASC, f.id ASC",
            self::getTableName(MjDynamicFields::TABLE_NAME),
            $member_id
        ));

        return is_array($results) ? $results : array();
    }

    /**
     * Get all values for a given member, keyed by field_id.
     *
     * @param int $member_id
     * @return array<int,string>
     */
    public static function getByMemberKeyed(int $member_id): array
    {
        $rows = self::getByMember($member_id);
        $keyed = array();

        foreach ($rows as $row) {
            $keyed[(int) $row->field_id] = $row->field_value ?? '';
        }

        return $keyed;
    }

    /**
     * Get value for a specific field and member.
     *
     * @param int $member_id
     * @param int $field_id
     * @return string|null
     */
    public static function getValue(int $member_id, int $field_id): ?string
    {
        $wpdb = self::getWpdb();
        $table = self::table_name();

        return $wpdb->get_var($wpdb->prepare(
            "SELECT field_value FROM {$table} WHERE member_id = %d AND field_id = %d LIMIT 1",
            $member_id,
            $field_id
        ));
    }

    /**
     * Upsert a single value for a member/field pair.
     *
     * @param int    $member_id
     * @param int    $field_id
     * @param string $value
     * @return bool
     */
    public static function setValue(int $member_id, int $field_id, string $value): bool
    {
        $wpdb = self::getWpdb();
        $table = self::table_name();

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE member_id = %d AND field_id = %d LIMIT 1",
            $member_id,
            $field_id
        ));

        if ($existing) {
            $result = $wpdb->update(
                $table,
                array('field_value' => $value),
                array('id' => (int) $existing),
                array('%s'),
                array('%d')
            );
        } else {
            $result = $wpdb->insert(
                $table,
                array(
                    'member_id'   => $member_id,
                    'field_id'    => $field_id,
                    'field_value' => $value,
                ),
                array('%d', '%d', '%s')
            );
        }

        return $result !== false;
    }

    /**
     * Save multiple field values for a member at once.
     *
     * @param int                  $member_id
     * @param array<int,string>    $values  Keyed by field_id.
     * @return void
     */
    public static function saveBulk(int $member_id, array $values): void
    {
        foreach ($values as $field_id => $value) {
            self::setValue($member_id, (int) $field_id, (string) $value);
        }
    }

    /**
     * Delete all values for a member.
     *
     * @param int $member_id
     * @return bool
     */
    public static function deleteByMember(int $member_id): bool
    {
        $wpdb = self::getWpdb();
        $table = self::table_name();

        $result = $wpdb->delete($table, array('member_id' => $member_id), array('%d'));

        return $result !== false;
    }

    /**
     * Delete all values for a field (when field config is removed).
     *
     * @param int $field_id
     * @return bool
     */
    public static function deleteByField(int $field_id): bool
    {
        $wpdb = self::getWpdb();
        $table = self::table_name();

        $result = $wpdb->delete($table, array('field_id' => $field_id), array('%d'));

        return $result !== false;
    }
}
