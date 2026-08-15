<?php

namespace Mj\Member\Classes\Crud;

use Mj\Member\Classes\MjTools;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class MjRequestRooms extends MjTools implements CrudRepositoryInterface
{
    private const TABLE = 'mj_request_rooms';

    private static function table_name(): string
    {
        if (function_exists('mj_member_get_request_rooms_table_name')) {
            return mj_member_get_request_rooms_table_name();
        }

        return self::getTableName(self::TABLE);
    }

    public static function get_all(array $args = array())
    {
        global $wpdb;
        $table = self::table_name();

        $where = array('is_active = 1');
        $params = array();
        if (isset($args['include_inactive']) && $args['include_inactive']) {
            $where = array('1=1');
        }

        if (!empty($args['id'])) {
            $where[] = 'id = %d';
            $params[] = (int) $args['id'];
        }

        $sql = "SELECT * FROM {$table} WHERE " . implode(' AND ', $where) . ' ORDER BY sort_order ASC, name ASC';
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        $rows = $wpdb->get_results($sql);
        return is_array($rows) ? $rows : array();
    }

    public static function count(array $args = array())
    {
        return count(self::get_all($args));
    }

    public static function find(int $id): ?object
    {
        $rows = self::get_all(array('id' => $id, 'include_inactive' => true));
        return isset($rows[0]) ? $rows[0] : null;
    }

    public static function create($data)
    {
        global $wpdb;
        $table = self::table_name();

        $name = isset($data['name']) ? sanitize_text_field((string) $data['name']) : '';
        if ($name === '') {
            return new WP_Error('missing_name', __('Nom de salle requis.', 'mj-member'));
        }

        $ok = $wpdb->insert(
            $table,
            array(
                'emoji' => self::resolve_emoji(isset($data['emoji']) ? $data['emoji'] : '', $name),
                'name' => $name,
                'description' => isset($data['description']) ? self::sanitize_description($data['description']) : '',
                'capacity' => isset($data['capacity']) ? max(0, (int) $data['capacity']) : 0,
                'options_json' => isset($data['options_json']) ? wp_json_encode(self::normalizeCatalogItems($data['options_json'])) : wp_json_encode(array()),
                'materials_json' => isset($data['materials_json']) ? wp_json_encode(self::normalizeCatalogItems($data['materials_json'])) : wp_json_encode(array()),
                'photo_ids_json' => isset($data['photo_ids_json']) ? wp_json_encode($data['photo_ids_json']) : wp_json_encode(array()),
                'plan_id' => isset($data['plan_id']) ? (int) $data['plan_id'] : 0,
                'is_active' => isset($data['is_active']) ? (!empty($data['is_active']) ? 1 : 0) : 1,
                'sort_order' => isset($data['sort_order']) ? (int) $data['sort_order'] : 0,
            ),
            array('%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%d', '%d')
        );

        if ($ok === false) {
            return new WP_Error('db_insert_failed', __('Impossible de créer la salle.', 'mj-member'));
        }

        return (int) $wpdb->insert_id;
    }

    public static function update($id, $data)
    {
        global $wpdb;
        $table = self::table_name();

        $updates = array();
        $formats = array();
        $nameValue = isset($data['name']) ? sanitize_text_field((string) $data['name']) : '';

        if (isset($data['emoji'])) {
            $updates['emoji'] = self::resolve_emoji($data['emoji'], $nameValue);
            $formats[] = '%s';
        }
        if (isset($data['name'])) {
            $updates['name'] = $nameValue;
            $formats[] = '%s';
        }
        if (isset($data['description'])) {
            $updates['description'] = self::sanitize_description($data['description']);
            $formats[] = '%s';
        }
        if (isset($data['capacity'])) {
            $updates['capacity'] = max(0, (int) $data['capacity']);
            $formats[] = '%d';
        }
        if (isset($data['options_json'])) {
            $updates['options_json'] = wp_json_encode(self::normalizeCatalogItems($data['options_json']));
            $formats[] = '%s';
        }
        if (isset($data['materials_json'])) {
            $updates['materials_json'] = wp_json_encode(self::normalizeCatalogItems($data['materials_json']));
            $formats[] = '%s';
        }
        if (isset($data['photo_ids_json'])) {
            $updates['photo_ids_json'] = wp_json_encode($data['photo_ids_json']);
            $formats[] = '%s';
        }
        if (isset($data['plan_id'])) {
            $updates['plan_id'] = (int) $data['plan_id'];
            $formats[] = '%d';
        }
        if (isset($data['is_active'])) {
            $updates['is_active'] = !empty($data['is_active']) ? 1 : 0;
            $formats[] = '%d';
        }
        if (isset($data['sort_order'])) {
            $updates['sort_order'] = (int) $data['sort_order'];
            $formats[] = '%d';
        }

        if (empty($updates)) {
            return true;
        }

        $ok = $wpdb->update($table, $updates, array('id' => (int) $id), $formats, array('%d'));
        if ($ok === false) {
            return new WP_Error('db_update_failed', __('Impossible de mettre à jour la salle.', 'mj-member'));
        }

        return true;
    }

    public static function delete($id)
    {
        global $wpdb;
        $table = self::table_name();
        $ok = $wpdb->delete($table, array('id' => (int) $id), array('%d'));
        if ($ok === false) {
            return new WP_Error('db_delete_failed', __('Impossible de supprimer la salle.', 'mj-member'));
        }

        return true;
    }

    private static function sanitize_description($value): string
    {
        if (is_object($value) && method_exists($value, '__toString')) {
            $value = (string) $value;
        }

        if (!is_scalar($value)) {
            return '';
        }

        return wp_kses_post(wp_unslash((string) $value));
    }

    private static function sanitize_emoji($value): string
    {
        if (function_exists('mj_member_admin_sanitize_emoji')) {
            return (string) mj_member_admin_sanitize_emoji($value);
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            $value = (string) $value;
        }

        if (!is_scalar($value)) {
            return '';
        }

        $candidate = wp_check_invalid_utf8((string) $value);
        if ($candidate === '') {
            return '';
        }

        $candidate = wp_strip_all_tags($candidate, false);
        $candidate = preg_replace('/[\x00-\x1F\x7F]+/', '', $candidate);
        if (!is_string($candidate)) {
            return '';
        }

        $candidate = trim($candidate);
        if ($candidate === '') {
            return '';
        }

        return trim(wp_html_excerpt($candidate, 16, ''));
    }

    private static function resolve_emoji($emoji, string $name): string
    {
        $resolved = self::sanitize_emoji($emoji);
        if ($resolved !== '') {
            return $resolved;
        }

        if (function_exists('mj_member_request_default_room_emoji')) {
            return (string) mj_member_request_default_room_emoji($name);
        }

        return '📍';
    }

    /**
     * @param mixed $items
     * @return array<int,array{title:string,emoji:string,photo_id:int}>
     */
    private static function normalizeCatalogItems($items): array
    {
        if (!is_array($items)) {
            return array();
        }

        $result = array();
        foreach ($items as $item) {
            $title = '';
            $emoji = '';
            $photoId = 0;

            if (is_scalar($item) || (is_object($item) && method_exists($item, '__toString'))) {
                $title = sanitize_text_field((string) $item);
            } elseif (is_array($item)) {
                $title = sanitize_text_field((string) ($item['title'] ?? ($item['label'] ?? '')));
                $emoji = self::sanitize_emoji((string) ($item['emoji'] ?? ''));
                $photoId = (int) ($item['photo_id'] ?? ($item['photoId'] ?? 0));
            }

            if ($title === '') {
                continue;
            }

            $result[] = array(
                'title' => $title,
                'emoji' => $emoji,
                'photo_id' => max(0, $photoId),
            );
        }

        return $result;
    }
}
