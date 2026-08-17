<?php

namespace Mj\Member\Classes\Crud;

use Mj\Member\Classes\MjTools;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class MjRequestTypes extends MjTools implements CrudRepositoryInterface
{
    private const TABLE = 'mj_request_types';

    public const VISIBILITY_PUBLIC = 'public';
    public const VISIBILITY_RESTRICTED = 'restricted';

    private static function table_name(): string
    {
        if (function_exists('mj_member_get_request_types_table_name')) {
            return mj_member_get_request_types_table_name();
        }

        return self::getTableName(self::TABLE);
    }

    public static function normalize_visibility_mode(string $mode): string
    {
        $mode = strtolower(trim($mode));
        return $mode === self::VISIBILITY_RESTRICTED ? self::VISIBILITY_RESTRICTED : self::VISIBILITY_PUBLIC;
    }

    /**
     * @return array<int,string>
     */
    public static function decode_allowed_roles(string $json): array
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return array();
        }

        $result = array();
        foreach ($decoded as $role) {
            $role = sanitize_key((string) $role);
            if ($role !== '' && !in_array($role, $result, true)) {
                $result[] = $role;
            }
        }

        return $result;
    }

    /**
     * @param array<int,mixed> $roles
     */
    private static function encode_allowed_roles($roles): string
    {
        if (!is_array($roles)) {
            return wp_json_encode(array());
        }

        $result = array();
        foreach ($roles as $role) {
            $role = sanitize_key((string) $role);
            if ($role !== '' && !in_array($role, $result, true)) {
                $result[] = $role;
            }
        }

        return wp_json_encode($result);
    }

    public static function get_all(array $args = array())
    {
        global $wpdb;
        $table = self::table_name();

        $defaults = array(
            'include_inactive' => false,
            'limit' => 0,
            'offset' => 0,
        );
        $args = wp_parse_args($args, $defaults);

        $where = array();
        $params = array();

        if (empty($args['include_inactive'])) {
            $where[] = 'is_active = 1';
        }

        $sql = "SELECT * FROM {$table}";
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY sort_order ASC, id ASC';

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

    /**
     * @return array<int,object>
     */
    public static function get_active(): array
    {
        return self::get_all(array('include_inactive' => false));
    }

    public static function find_by_key(string $typeKey): ?object
    {
        $typeKey = sanitize_key($typeKey);
        if ($typeKey === '') {
            return null;
        }

        global $wpdb;
        $table = self::table_name();
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE type_key = %s", $typeKey));
        return $row ?: null;
    }

    public static function get_by_id(int $id): ?object
    {
        global $wpdb;
        $table = self::table_name();
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
        return $row ?: null;
    }

    public static function count(array $args = array())
    {
        global $wpdb;
        $table = self::table_name();

        $where = array('1=1');
        if (empty($args['include_inactive'])) {
            $where[] = 'is_active = 1';
        }

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE " . implode(' AND ', $where));
    }

    public static function create($data)
    {
        global $wpdb;
        $table = self::table_name();

        $label = isset($data['label']) ? sanitize_text_field((string) $data['label']) : '';
        if ($label === '') {
            return new WP_Error('missing_label', __('Le libellé est requis.', 'mj-member'));
        }

        $typeKey = isset($data['type_key']) ? sanitize_key((string) $data['type_key']) : '';
        if ($typeKey === '') {
            $typeKey = sanitize_key(remove_accents($label));
        }
        if ($typeKey === '') {
            return new WP_Error('missing_type_key', __('La clé du type est requise.', 'mj-member'));
        }

        if (self::find_by_key($typeKey)) {
            return new WP_Error('duplicate_type_key', __('Cette clé de type existe déjà.', 'mj-member'));
        }

        $insert = array(
            'type_key' => $typeKey,
            'emoji' => isset($data['emoji']) ? sanitize_text_field((string) $data['emoji']) : '',
            'color' => isset($data['color']) ? sanitize_hex_color((string) $data['color']) ?: '' : '',
            'label' => $label,
            'description' => isset($data['description']) ? wp_kses_post((string) $data['description']) : '',
            'allows_location' => !empty($data['allows_location']) ? 1 : 0,
            'allows_materials' => !empty($data['allows_materials']) ? 1 : 0,
            'allows_date' => !empty($data['allows_date']) ? 1 : 0,
            'allows_multiple_dates' => !empty($data['allows_multiple_dates']) ? 1 : 0,
            'requires_animateur' => !empty($data['requires_animateur']) ? 1 : 0,
            'visibility_mode' => self::normalize_visibility_mode((string) ($data['visibility_mode'] ?? self::VISIBILITY_PUBLIC)),
            'allowed_roles_json' => self::encode_allowed_roles($data['allowed_roles'] ?? array()),
            'is_active' => !empty($data['is_active']) ? 1 : 0,
            'sort_order' => isset($data['sort_order']) ? (int) $data['sort_order'] : 0,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        );

        $ok = $wpdb->insert($table, $insert);
        if ($ok === false) {
            return new WP_Error('db_insert_failed', __('Impossible de créer le type de demande.', 'mj-member'));
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

        if (isset($data['emoji'])) {
            $updates['emoji'] = sanitize_text_field((string) $data['emoji']);
        }

        if (isset($data['color'])) {
            $updates['color'] = sanitize_hex_color((string) $data['color']) ?: '';
        }

        if (isset($data['label'])) {
            $label = sanitize_text_field((string) $data['label']);
            if ($label === '') {
                return new WP_Error('missing_label', __('Le libellé est requis.', 'mj-member'));
            }
            $updates['label'] = $label;
        }

        if (isset($data['description'])) {
            $updates['description'] = wp_kses_post((string) $data['description']);
        }

        if (isset($data['allows_location'])) {
            $updates['allows_location'] = !empty($data['allows_location']) ? 1 : 0;
        }

        if (isset($data['allows_materials'])) {
            $updates['allows_materials'] = !empty($data['allows_materials']) ? 1 : 0;
        }

        if (isset($data['allows_date'])) {
            $updates['allows_date'] = !empty($data['allows_date']) ? 1 : 0;
        }

        if (isset($data['allows_multiple_dates'])) {
            $updates['allows_multiple_dates'] = !empty($data['allows_multiple_dates']) ? 1 : 0;
        }

        if (isset($data['requires_animateur'])) {
            $updates['requires_animateur'] = !empty($data['requires_animateur']) ? 1 : 0;
        }

        if (isset($data['visibility_mode'])) {
            $updates['visibility_mode'] = self::normalize_visibility_mode((string) $data['visibility_mode']);
        }

        if (isset($data['allowed_roles'])) {
            $updates['allowed_roles_json'] = self::encode_allowed_roles($data['allowed_roles']);
        }

        if (isset($data['is_active'])) {
            $updates['is_active'] = !empty($data['is_active']) ? 1 : 0;
        }

        if (isset($data['sort_order'])) {
            $updates['sort_order'] = (int) $data['sort_order'];
        }

        if (empty($updates)) {
            return true;
        }

        $updates['updated_at'] = current_time('mysql');

        $ok = $wpdb->update($table, $updates, array('id' => $id));
        if ($ok === false) {
            return new WP_Error('db_update_failed', __('Impossible de mettre à jour le type de demande.', 'mj-member'));
        }

        return true;
    }

    public static function delete($id)
    {
        global $wpdb;
        $table = self::table_name();
        $ok = $wpdb->delete($table, array('id' => (int) $id), array('%d'));
        if ($ok === false) {
            return new WP_Error('db_delete_failed', __('Impossible de supprimer le type de demande.', 'mj-member'));
        }

        return true;
    }
}
