<?php

namespace Mj\Member\Classes\Crud;

use Mj\Member\Classes\MjTools;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * CRUD repository for leave types (mj_leave_types).
 */
class MjLeaveTypes extends MjTools implements CrudRepositoryInterface
{
    private const TABLE = 'mj_leave_types';

    public const SLUG_PAID = 'paid';
    public const SLUG_UNPAID = 'unpaid';
    public const SLUG_EXCEPTIONAL = 'exceptional';
    public const SLUG_RECOVERY = 'recovery';
    public const SLUG_SICK = 'sick';

    /**
     * @return string
     */
    private static function table_name(): string
    {
        if (function_exists('mj_member_get_leave_types_table_name')) {
            return mj_member_get_leave_types_table_name();
        }

        return self::getTableName(self::TABLE);
    }

    /**
     * Get all leave types.
     *
     * @param array<string,mixed> $args
     * @return array<int,object>
     */
    public static function get_all(array $args = array()): array
    {
        global $wpdb;
        $table = self::table_name();

        $defaults = array(
            'is_active' => null,
            'orderby' => 'sort_order',
            'order' => 'ASC',
        );
        $args = wp_parse_args($args, $defaults);

        $where = array();
        $params = array();

        if ($args['is_active'] !== null) {
            $where[] = 'is_active = %d';
            $params[] = (int) $args['is_active'];
        }

        $orderby = in_array($args['orderby'], array('sort_order', 'name', 'id', 'created_at'), true) ? $args['orderby'] : 'sort_order';
        $order = strtoupper($args['order']) === 'DESC' ? 'DESC' : 'ASC';

        $sql = "SELECT * FROM {$table}";
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= " ORDER BY {$orderby} {$order}";

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        $results = $wpdb->get_results($sql);

        return is_array($results) ? $results : array();
    }

    /**
     * Count leave types.
     *
     * @param array<string,mixed> $args
     * @return int
     */
    public static function count(array $args = array())
    {
        global $wpdb;
        $table = self::table_name();

        $where = array();
        $params = array();

        if (isset($args['is_active'])) {
            $where[] = 'is_active = %d';
            $params[] = (int) $args['is_active'];
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
     * Get only active leave types.
     *
     * @return array<int,object>
     */
    public static function get_active(): array
    {
        return self::get_all(array('is_active' => 1));
    }

    /**
     * Get leave type by ID.
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
     * Get leave type by slug.
     *
     * @param string $slug
     * @return object|null
     */
    public static function get_by_slug(string $slug): ?object
    {
        global $wpdb;
        $table = self::table_name();

        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE slug = %s", $slug));

        return $row ?: null;
    }

    /**
     * Check if a type requires validation.
     *
     * @param int $type_id
     * @return bool
     */
    public static function requires_validation(int $type_id): bool
    {
        $type = self::get_by_id($type_id);

        return $type ? (bool) $type->requires_validation : true;
    }

    /**
     * Check if a type requires a document.
     *
     * @param int $type_id
     * @return bool
     */
    public static function requires_document(int $type_id): bool
    {
        $type = self::get_by_id($type_id);

        return $type ? (bool) $type->requires_document : false;
    }

    /**
     * Create a new leave type.
     *
     * @param array<string,mixed> $data
     * @return int|WP_Error
     */
    public static function create($data)
    {
        global $wpdb;
        $table = self::table_name();

        if (empty($data['name'])) {
            return new WP_Error('mj_leave_type_invalid', __('Le nom du type est obligatoire.', 'mj-member'));
        }

        if (empty($data['slug'])) {
            $data['slug'] = sanitize_title($data['name']);
        }

        $existing = self::get_by_slug($data['slug']);
        if ($existing) {
            return new WP_Error('mj_leave_type_exists', __('Ce type de congé existe déjà.', 'mj-member'));
        }

        $insert = array(
            'name' => sanitize_text_field($data['name']),
            'slug' => sanitize_key($data['slug']),
            'requires_document' => isset($data['requires_document']) ? (int) $data['requires_document'] : 0,
            'requires_validation' => isset($data['requires_validation']) ? (int) $data['requires_validation'] : 1,
            'color' => isset($data['color']) ? sanitize_hex_color($data['color']) : '#6366f1',
            'sort_order' => isset($data['sort_order']) ? (int) $data['sort_order'] : 0,
            'is_active' => isset($data['is_active']) ? (int) $data['is_active'] : 1,
        );

        $result = $wpdb->insert($table, $insert);

        if ($result === false) {
            return new WP_Error('mj_leave_type_create_failed', __('Impossible de créer le type de congé.', 'mj-member'));
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * Update a leave type.
     *
     * @param int $id
     * @param array<string,mixed> $data
     * @return bool|WP_Error
     */
    public static function update($id, $data)
    {
        global $wpdb;
        $table = self::table_name();

        $existing = self::get_by_id($id);
        if (!$existing) {
            return new WP_Error('mj_leave_type_not_found', __('Type de congé introuvable.', 'mj-member'));
        }

        $updates = array();

        if (isset($data['name'])) {
            $updates['name'] = sanitize_text_field($data['name']);
        }
        if (isset($data['requires_document'])) {
            $updates['requires_document'] = (int) $data['requires_document'];
        }
        if (isset($data['requires_validation'])) {
            $updates['requires_validation'] = (int) $data['requires_validation'];
        }
        if (isset($data['color'])) {
            $updates['color'] = sanitize_hex_color($data['color']) ?: $existing->color;
        }
        if (isset($data['sort_order'])) {
            $updates['sort_order'] = (int) $data['sort_order'];
        }
        if (isset($data['is_active'])) {
            $updates['is_active'] = (int) $data['is_active'];
        }

        if (empty($updates)) {
            return true;
        }

        $result = $wpdb->update($table, $updates, array('id' => $id));

        if ($result === false) {
            return new WP_Error('mj_leave_type_update_failed', __('Impossible de mettre à jour le type de congé.', 'mj-member'));
        }

        return true;
    }

    /**
     * Delete a leave type.
     *
     * @param int $id
     * @return bool|WP_Error
     */
    public static function delete($id)
    {
        global $wpdb;
        $table = self::table_name();

        $existing = self::get_by_id($id);
        if (!$existing) {
            return new WP_Error('mj_leave_type_not_found', __('Type de congé introuvable.', 'mj-member'));
        }

        $result = $wpdb->delete($table, array('id' => $id), array('%d'));

        if ($result === false) {
            return new WP_Error('mj_leave_type_delete_failed', __('Impossible de supprimer le type de congé.', 'mj-member'));
        }

        return true;
    }

    /**
     * Get type labels for JavaScript.
     *
     * @return array<string,string>
     */
    public static function get_labels(): array
    {
        $types = self::get_active();
        $labels = array();

        foreach ($types as $type) {
            $labels[$type->slug] = $type->name;
        }

        return $labels;
    }

    /**
     * Get type colors for JavaScript.
     *
     * @return array<string,string>
     */
    public static function get_colors(): array
    {
        $types = self::get_active();
        $colors = array();

        foreach ($types as $type) {
            $colors[$type->slug] = $type->color;
        }

        return $colors;
    }
}
