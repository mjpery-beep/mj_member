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

    private static function table_name(): string
    {
        if (function_exists('mj_member_get_request_types_table_name')) {
            return mj_member_get_request_types_table_name();
        }

        return self::getTableName(self::TABLE);
    }

    public static function get_all(array $args = array())
    {
        global $wpdb;
        $table = self::table_name();

        $includeInactive = !empty($args['include_inactive']);
        $where = $includeInactive ? '1=1' : 'is_active = 1';

        $sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY sort_order ASC, label ASC";
        $rows = $wpdb->get_results($sql);

        return is_array($rows) ? $rows : array();
    }

    public static function count(array $args = array())
    {
        return count(self::get_all($args));
    }

    public static function get_active(): array
    {
        return self::get_all(array('include_inactive' => false));
    }

    public static function find_by_key(string $typeKey): ?object
    {
        global $wpdb;
        $table = self::table_name();

        $typeKey = sanitize_key($typeKey);
        if ($typeKey === '') {
            return null;
        }

        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE type_key = %s LIMIT 1", $typeKey));
        return $row ?: null;
    }

    public static function create($data)
    {
        global $wpdb;
        $table = self::table_name();

        $typeKey = isset($data['type_key']) ? sanitize_key((string) $data['type_key']) : '';
        $label = isset($data['label']) ? sanitize_text_field((string) $data['label']) : '';
        if ($typeKey === '' || $label === '') {
            return new WP_Error('invalid_type', __('Type de demande invalide.', 'mj-member'));
        }

        $insert = array(
            'type_key' => $typeKey,
            'emoji' => self::resolve_emoji(isset($data['emoji']) ? $data['emoji'] : '', $typeKey, $label),
            'color' => self::resolve_color(isset($data['color']) ? $data['color'] : '', $typeKey, $label),
            'label' => $label,
            'description' => isset($data['description']) ? self::sanitize_description($data['description']) : '',
            'allows_location' => !empty($data['allows_location']) ? 1 : 0,
            'allows_materials' => !empty($data['allows_materials']) ? 1 : 0,
            'allows_date' => !empty($data['allows_date']) ? 1 : 0,
            'allows_multiple_dates' => !empty($data['allows_multiple_dates']) ? 1 : 0,
            'requires_animateur' => !empty($data['requires_animateur']) ? 1 : 0,
            'is_active' => isset($data['is_active']) ? (!empty($data['is_active']) ? 1 : 0) : 1,
            'sort_order' => isset($data['sort_order']) ? (int) $data['sort_order'] : 0,
        );

        $ok = $wpdb->insert(
            $table,
            $insert,
            array('%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%d')
        );

        if ($ok === false) {
            return new WP_Error('db_insert_failed', __('Impossible de créer le type de demande.', 'mj-member'));
        }

        return (int) $wpdb->insert_id;
    }

    public static function update($id, $data)
    {
        global $wpdb;
        $table = self::table_name();

        $updates = array();
        $formats = array();
        $labelValue = isset($data['label']) ? sanitize_text_field((string) $data['label']) : '';

        if (isset($data['emoji'])) {
            $typeKeyValue = isset($data['type_key']) ? sanitize_key((string) $data['type_key']) : '';
            $updates['emoji'] = self::resolve_emoji($data['emoji'], $typeKeyValue, $labelValue);
            $formats[] = '%s';
        }
        if (isset($data['color'])) {
            $typeKeyValue = isset($data['type_key']) ? sanitize_key((string) $data['type_key']) : '';
            $updates['color'] = self::resolve_color($data['color'], $typeKeyValue, $labelValue);
            $formats[] = '%s';
        }
        if (isset($data['label'])) {
            $updates['label'] = $labelValue;
            $formats[] = '%s';
        }
        if (isset($data['description'])) {
            $updates['description'] = self::sanitize_description($data['description']);
            $formats[] = '%s';
        }
        if (isset($data['allows_location'])) {
            $updates['allows_location'] = !empty($data['allows_location']) ? 1 : 0;
            $formats[] = '%d';
        }
        if (isset($data['allows_materials'])) {
            $updates['allows_materials'] = !empty($data['allows_materials']) ? 1 : 0;
            $formats[] = '%d';
        }
        if (isset($data['allows_date'])) {
            $updates['allows_date'] = !empty($data['allows_date']) ? 1 : 0;
            $formats[] = '%d';
        }
        if (isset($data['allows_multiple_dates'])) {
            $updates['allows_multiple_dates'] = !empty($data['allows_multiple_dates']) ? 1 : 0;
            $formats[] = '%d';
        }
        if (isset($data['requires_animateur'])) {
            $updates['requires_animateur'] = !empty($data['requires_animateur']) ? 1 : 0;
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

    private static function resolve_emoji($emoji, string $typeKey, string $label): string
    {
        $resolved = self::sanitize_emoji($emoji);
        if ($resolved !== '') {
            return $resolved;
        }

        if (function_exists('mj_member_request_default_type_emoji')) {
            return (string) mj_member_request_default_type_emoji($typeKey, $label);
        }

        return '📝';
    }

    private static function resolve_color($color, string $typeKey, string $label): string
    {
        $resolved = self::sanitize_color($color);
        if ($resolved !== '') {
            return $resolved;
        }

        if (function_exists('mj_member_request_default_type_color')) {
            return (string) mj_member_request_default_type_color($typeKey, $label);
        }

        return '#1F6FEB';
    }

    private static function sanitize_color($value): string
    {
        if (is_object($value) && method_exists($value, '__toString')) {
            $value = (string) $value;
        }

        if (!is_scalar($value)) {
            return '';
        }

        $candidate = sanitize_hex_color((string) $value);
        if (!is_string($candidate) || $candidate === '') {
            return '';
        }

        $candidate = strtoupper($candidate);
        if (strlen($candidate) === 4) {
            return '#' . $candidate[1] . $candidate[1] . $candidate[2] . $candidate[2] . $candidate[3] . $candidate[3];
        }

        return $candidate;
    }
}
