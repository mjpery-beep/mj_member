<?php

namespace Mj\Member\Classes\Crud;

use Mj\Member\Classes\MjTools;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class MjDocumentFolders extends MjTools implements CrudRepositoryInterface
{
    private const TABLE = 'mj_document_folders';

    private static function table_name(): string
    {
        if (function_exists('mj_member_get_document_folders_table_name')) {
            return mj_member_get_document_folders_table_name();
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
            'include_ids' => array(),
            'parent_id' => null,
            'search' => '',
            'orderby' => 'position',
            'order' => 'ASC',
        );
        $args = wp_parse_args($args, $defaults);

        $builder = CrudQueryBuilder::for_table($table);

        if ($args['parent_id'] !== null) {
            $parentId = (int) $args['parent_id'];
            if ($parentId >= 0) {
                $builder->where_equals_int('parent_id', $parentId);
            }
        }

        if (!empty($args['include_ids'])) {
            $builder->where_in_int('id', (array) $args['include_ids']);
        }

        if (!empty($args['search'])) {
            $builder->where_like_any(array('name', 'slug'), (string) $args['search']);
        }

        $allowedOrderBy = array('position', 'name', 'created_at', 'updated_at', 'id');
        $orderby = sanitize_key((string) $args['orderby']);
        if (!in_array($orderby, $allowedOrderBy, true)) {
            $orderby = 'position';
        }

        $order = strtoupper((string) $args['order']) === 'DESC' ? 'DESC' : 'ASC';

        list($sql, $params) = $builder->build_select('*', $orderby, $order);
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
            'parent_id' => null,
            'search' => '',
        );
        $args = wp_parse_args($args, $defaults);

        $builder = CrudQueryBuilder::for_table($table);

        if ($args['parent_id'] !== null) {
            $parentId = (int) $args['parent_id'];
            if ($parentId >= 0) {
                $builder->where_equals_int('parent_id', $parentId);
            }
        }

        if (!empty($args['search'])) {
            $builder->where_like_any(array('name', 'slug'), (string) $args['search']);
        }

        list($sql, $params) = $builder->build_count('*');
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        $result = $wpdb->get_var($sql);
        return $result ? (int) $result : 0;
    }

    /**
     * @param mixed $data
     * @return int|WP_Error
     */
    public static function create($data)
    {
        if (!is_array($data)) {
            return new WP_Error('mj_document_folder_invalid_payload', __('Données invalides pour le dossier.', 'mj-member'));
        }

        global $wpdb;
        $table = self::table_name();

        $name = isset($data['name']) ? sanitize_text_field((string) $data['name']) : '';
        if ($name === '') {
            return new WP_Error('mj_document_folder_missing_name', __('Le nom du dossier est requis.', 'mj-member'));
        }

        $parentId = isset($data['parent_id']) ? (int) $data['parent_id'] : 0;
        if ($parentId < 0) {
            $parentId = 0;
        }

        $slug = isset($data['slug']) ? sanitize_title((string) $data['slug']) : '';
        if ($slug === '') {
            $slug = sanitize_title($name);
        }
        if ($slug === '') {
            $slug = 'dossier-' . wp_generate_password(8, false, false);
        }
        $slug = self::ensure_unique_slug($slug, $parentId, 0);

        $color = isset($data['color']) ? sanitize_hex_color((string) $data['color']) : '';
        if ($color === null) {
            $color = '';
        }

        $icon = isset($data['icon']) ? sanitize_key((string) $data['icon']) : '';
        $position = isset($data['position']) ? (int) $data['position'] : self::next_position($parentId);

        $userId = get_current_user_id();

        $inserted = $wpdb->insert(
            $table,
            array(
                'parent_id' => $parentId,
                'name' => $name,
                'slug' => $slug,
                'color' => $color,
                'icon' => $icon,
                'position' => max(0, $position),
                'created_by' => $userId,
                'updated_by' => $userId,
            ),
            array('%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d')
        );

        if (!$inserted) {
            return new WP_Error('mj_document_folder_insert_failed', __('Impossible de créer le dossier.', 'mj-member'));
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * @param int $id
     * @param mixed $data
     * @return true|WP_Error
     */
    public static function update($id, $data)
    {
        $id = (int) $id;
        if ($id <= 0) {
            return new WP_Error('mj_document_folder_invalid_id', __('Identifiant de dossier invalide.', 'mj-member'));
        }

        if (!is_array($data)) {
            return new WP_Error('mj_document_folder_invalid_payload', __('Données invalides pour le dossier.', 'mj-member'));
        }

        global $wpdb;
        $table = self::table_name();

        $payload = array();
        $formats = array();

        if (isset($data['name'])) {
            $name = sanitize_text_field((string) $data['name']);
            if ($name === '') {
                return new WP_Error('mj_document_folder_missing_name', __('Le nom du dossier est requis.', 'mj-member'));
            }
            $payload['name'] = $name;
            $formats[] = '%s';
        }

        $parentId = null;
        if (array_key_exists('parent_id', $data)) {
            $parentId = (int) $data['parent_id'];
            if ($parentId < 0) {
                $parentId = 0;
            }
            if ($parentId === $id) {
                return new WP_Error('mj_document_folder_self_parent', __('Un dossier ne peut pas être son propre parent.', 'mj-member'));
            }
            $payload['parent_id'] = $parentId;
            $formats[] = '%d';
        }

        $slugProvided = array_key_exists('slug', $data);
        if ($slugProvided) {
            $slug = sanitize_title((string) $data['slug']);
            if ($slug === '') {
                $slug = sanitize_title(isset($payload['name']) ? $payload['name'] : self::get_name($id));
            }
            if ($slug === '') {
                $slug = 'dossier-' . wp_generate_password(8, false, false);
            }
            $resolvedParent = $parentId !== null ? $parentId : self::get_parent_id($id);
            $slug = self::ensure_unique_slug($slug, $resolvedParent, $id);
            $payload['slug'] = $slug;
            $formats[] = '%s';
        }

        if (isset($data['color'])) {
            $color = sanitize_hex_color((string) $data['color']);
            if ($color === null) {
                $color = '';
            }
            $payload['color'] = $color;
            $formats[] = '%s';
        }

        if (isset($data['icon'])) {
            $payload['icon'] = sanitize_key((string) $data['icon']);
            $formats[] = '%s';
        }

        if (isset($data['position'])) {
            $payload['position'] = max(0, (int) $data['position']);
            $formats[] = '%d';
        }

        if (empty($payload)) {
            return true;
        }

        $payload['updated_by'] = get_current_user_id();
        $formats[] = '%d';

        $updated = $wpdb->update($table, $payload, array('id' => $id), $formats, array('%d'));
        if ($updated === false) {
            return new WP_Error('mj_document_folder_update_failed', __('Impossible de mettre à jour le dossier.', 'mj-member'));
        }

        return true;
    }

    /**
     * @param int $id
     * @return true|WP_Error
     */
    public static function delete($id)
    {
        $id = (int) $id;
        if ($id <= 0) {
            return new WP_Error('mj_document_folder_invalid_id', __('Identifiant de dossier invalide.', 'mj-member'));
        }

        global $wpdb;
        $table = self::table_name();

        $hasChildren = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(id) FROM {$table} WHERE parent_id = %d", $id));
        if ($hasChildren > 0) {
            return new WP_Error('mj_document_folder_has_children', __('Ce dossier contient des sous-dossiers.', 'mj-member'));
        }

        if (class_exists(__NAMESPACE__ . '\\MjDocuments')) {
            $documentsCount = MjDocuments::count(array('folder_id' => $id));
            if ($documentsCount > 0) {
                return new WP_Error('mj_document_folder_has_files', __('Ce dossier contient des documents.', 'mj-member'));
            }
        }

        $deleted = $wpdb->delete($table, array('id' => $id), array('%d'));
        if (!$deleted) {
            return new WP_Error('mj_document_folder_delete_failed', __('Impossible de supprimer le dossier.', 'mj-member'));
        }

        return true;
    }

    /**
     * @param array<string,mixed>|object $row
     * @return array<string,mixed>
     */
    private static function format_row($row): array
    {
        if (is_object($row)) {
            $row = (array) $row;
        }

        $id = isset($row['id']) ? (int) $row['id'] : 0;
        $parent = isset($row['parent_id']) ? (int) $row['parent_id'] : 0;
        $position = isset($row['position']) ? (int) $row['position'] : 0;

        $color = '';
        if (isset($row['color'])) {
            $sanitizedColor = sanitize_hex_color((string) $row['color']);
            $color = $sanitizedColor === null ? '' : $sanitizedColor;
        }

        return array(
            'id' => $id,
            'parentId' => $parent,
            'name' => isset($row['name']) ? sanitize_text_field((string) $row['name']) : '',
            'slug' => isset($row['slug']) ? sanitize_title((string) $row['slug']) : '',
            'color' => $color,
            'icon' => isset($row['icon']) ? sanitize_key((string) $row['icon']) : '',
            'position' => $position,
            'createdAt' => isset($row['created_at']) ? (string) $row['created_at'] : '',
            'updatedAt' => isset($row['updated_at']) ? (string) $row['updated_at'] : '',
        );
    }

    private static function next_position(int $parentId): int
    {
        global $wpdb;
        $table = self::table_name();
        $max = (int) $wpdb->get_var($wpdb->prepare("SELECT MAX(position) FROM {$table} WHERE parent_id = %d", $parentId));
        return $max + 1;
    }

    private static function ensure_unique_slug(string $slug, int $parentId, int $excludeId): string
    {
        global $wpdb;
        $table = self::table_name();

        $base = $slug;
        $suffix = 1;

        while (true) {
            if ($excludeId > 0) {
                $query = $wpdb->prepare(
                    "SELECT id FROM {$table} WHERE parent_id = %d AND slug = %s AND id <> %d",
                    $parentId,
                    $slug,
                    $excludeId
                );
            } else {
                $query = $wpdb->prepare(
                    "SELECT id FROM {$table} WHERE parent_id = %d AND slug = %s",
                    $parentId,
                    $slug
                );
            }

            $exists = $wpdb->get_var($query);
            if (!$exists) {
                return $slug;
            }

            $suffix += 1;
            $slug = $base . '-' . $suffix;
        }
    }

    private static function get_name(int $id): string
    {
        global $wpdb;
        $table = self::table_name();
        $name = $wpdb->get_var($wpdb->prepare("SELECT name FROM {$table} WHERE id = %d", $id));
        return $name ? sanitize_text_field((string) $name) : '';
    }

    private static function get_parent_id(int $id): int
    {
        global $wpdb;
        $table = self::table_name();
        $parent = $wpdb->get_var($wpdb->prepare("SELECT parent_id FROM {$table} WHERE id = %d", $id));
        return $parent ? (int) $parent : 0;
    }
}
