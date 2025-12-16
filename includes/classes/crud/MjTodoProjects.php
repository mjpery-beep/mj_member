<?php

namespace Mj\Member\Classes\Crud;

use Mj\Member\Classes\MjTools;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class MjTodoProjects extends MjTools implements CrudRepositoryInterface
{
    private const TABLE = 'mj_projects';

    private static function table_name(): string
    {
        if (function_exists('mj_member_get_todo_projects_table_name')) {
            return mj_member_get_todo_projects_table_name();
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
            'search' => '',
            'orderby' => 'title',
            'order' => 'ASC',
            'limit' => 0,
            'offset' => 0,
            'include_ids' => array(),
        );
        $args = wp_parse_args($args, $defaults);

        $builder = CrudQueryBuilder::for_table($table);

        if (!empty($args['search'])) {
            $builder->where_like_any(array('title', 'slug'), (string) $args['search']);
        }

        if (!empty($args['include_ids'])) {
            $builder->where_in_int('id', (array) $args['include_ids']);
        }

        $allowedOrderBy = array('title', 'created_at', 'updated_at', 'id');
        $orderby = sanitize_key((string) $args['orderby']);
        if (!in_array($orderby, $allowedOrderBy, true)) {
            $orderby = 'title';
        }

        $order = strtoupper((string) $args['order']) === 'DESC' ? 'DESC' : 'ASC';
        $limit = (int) $args['limit'];
        $offset = max(0, (int) $args['offset']);

        list($sql, $params) = $builder->build_select('*', $orderby, $order, $limit, $offset);
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        $rows = $wpdb->get_results($sql);
        if (!is_array($rows) || empty($rows)) {
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
            'search' => '',
        );
        $args = wp_parse_args($args, $defaults);

        $builder = CrudQueryBuilder::for_table($table);

        if (!empty($args['search'])) {
            $builder->where_like_any(array('title', 'slug'), (string) $args['search']);
        }

        list($sql, $params) = $builder->build_count('*');
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        $result = $wpdb->get_var($sql);
        return $result ? (int) $result : 0;
    }

    /**
     * @param array<string,mixed>|null $data
     * @return int|WP_Error
     */
    public static function create($data)
    {
        if (!is_array($data)) {
            return new WP_Error('mj_member_todo_project_invalid_payload', __('Format de données invalide pour le dossier.', 'mj-member'));
        }

        global $wpdb;
        $table = self::table_name();

        $title = sanitize_text_field($data['title'] ?? '');
        if ($title === '') {
            return new WP_Error('mj_member_todo_project_missing_title', __('Le titre du dossier est requis.', 'mj-member'));
        }

        $providedSlug = isset($data['slug']) ? (string) $data['slug'] : '';
        $slug = self::generate_unique_slug($title, $providedSlug, 0);

        $description = isset($data['description']) ? sanitize_textarea_field($data['description']) : '';
        $color = isset($data['color']) ? sanitize_hex_color((string) $data['color']) : '';

        $createdBy = isset($data['created_by']) ? (int) $data['created_by'] : get_current_user_id();
        if ($createdBy < 0) {
            $createdBy = 0;
        }

        $insert = array(
            'title' => $title,
            'slug' => $slug,
            'created_by' => $createdBy,
        );
        $formats = array('%s', '%s', '%d');

        if ($description !== '') {
            $insert['description'] = $description;
            $formats[] = '%s';
        }

        if ($color !== '' && $color !== false) {
            $insert['color'] = $color;
            $formats[] = '%s';
        }

        if ($createdBy > 0) {
            $insert['updated_by'] = $createdBy;
            $formats[] = '%d';
        }

        $result = $wpdb->insert($table, $insert, $formats);
        if ($result === false) {
            return new WP_Error('mj_member_todo_project_insert_failed', __('Impossible de créer le dossier.', 'mj-member'));
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * @param int $id
     * @param array<string,mixed>|null $data
     * @return true|WP_Error
     */
    public static function update($id, $data)
    {
        $id = (int) $id;
        if ($id <= 0) {
            return new WP_Error('mj_member_todo_project_invalid_id', __('Identifiant de dossier invalide.', 'mj-member'));
        }

        if (!is_array($data)) {
            return new WP_Error('mj_member_todo_project_invalid_payload', __('Format de données invalide pour le dossier.', 'mj-member'));
        }

        $fields = array();
        $formats = array();

        if (array_key_exists('title', $data)) {
            $title = sanitize_text_field($data['title']);
            if ($title === '') {
                return new WP_Error('mj_member_todo_project_missing_title', __('Le titre du dossier est requis.', 'mj-member'));
            }
            $fields['title'] = $title;
            $formats[] = '%s';
        }

        if (array_key_exists('slug', $data)) {
            $titleForSlug = isset($fields['title']) ? $fields['title'] : (self::get($id)['title'] ?? '');
            $fields['slug'] = self::generate_unique_slug($titleForSlug, (string) $data['slug'], $id);
            $formats[] = '%s';
        }

        if (array_key_exists('description', $data)) {
            $description = sanitize_textarea_field($data['description']);
            $fields['description'] = $description === '' ? null : $description;
            $formats[] = '%s';
        }

        if (array_key_exists('color', $data)) {
            $color = sanitize_hex_color((string) $data['color']);
            $fields['color'] = ($color === '' || $color === null) ? null : $color;
            $formats[] = '%s';
        }

        $updatedBy = isset($data['updated_by']) ? (int) $data['updated_by'] : get_current_user_id();
        if ($updatedBy > 0) {
            $fields['updated_by'] = $updatedBy;
            $formats[] = '%d';
        }

        if (empty($fields)) {
            return true;
        }

        global $wpdb;
        $table = self::table_name();

        $result = $wpdb->update($table, $fields, array('id' => $id), $formats, array('%d'));
        if ($result === false) {
            return new WP_Error('mj_member_todo_project_update_failed', __('Impossible de mettre à jour le dossier.', 'mj-member'));
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
            return new WP_Error('mj_member_todo_project_invalid_id', __('Identifiant de dossier invalide.', 'mj-member'));
        }

        global $wpdb;
        $table = self::table_name();

        if (class_exists(__NAMESPACE__ . '\\MjTodos')) {
            MjTodos::detachProject($id);
        }

        $deleted = $wpdb->delete($table, array('id' => $id), array('%d'));
        if ($deleted === false) {
            return new WP_Error('mj_member_todo_project_delete_failed', __('Suppression du dossier impossible.', 'mj-member'));
        }

        return true;
    }

    /**
     * @param int $id
     * @return array<string,mixed>|null
     */
    public static function get($id)
    {
        $id = (int) $id;
        if ($id <= 0) {
            return null;
        }

        global $wpdb;
        $table = self::table_name();

        $sql = $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id);
        $row = $wpdb->get_row($sql);
        if (!$row) {
            return null;
        }

        return self::format_row($row);
    }

    /**
     * @param object|array<string,mixed> $row
     * @return array<string,mixed>
     */
    private static function format_row($row): array
    {
        if (is_object($row)) {
            $row = get_object_vars($row);
        }

        $id = isset($row['id']) ? (int) $row['id'] : 0;
        $title = isset($row['title']) ? sanitize_text_field((string) $row['title']) : '';
        $slug = isset($row['slug']) ? sanitize_title((string) $row['slug']) : '';
        $description = isset($row['description']) ? (string) $row['description'] : '';
        $color = isset($row['color']) ? sanitize_hex_color((string) $row['color']) : '';

        return array(
            'id' => $id,
            'title' => $title,
            'slug' => $slug,
            'description' => $description,
            'color' => $color !== '' ? $color : '',
            'created_by' => isset($row['created_by']) ? (int) $row['created_by'] : 0,
            'updated_by' => isset($row['updated_by']) ? (int) $row['updated_by'] : 0,
            'created_at' => isset($row['created_at']) ? (string) $row['created_at'] : '',
            'updated_at' => isset($row['updated_at']) ? (string) $row['updated_at'] : '',
        );
    }

    private static function generate_unique_slug(string $title, string $provided, int $excludeId): string
    {
        $base = sanitize_title($provided !== '' ? $provided : $title);
        if ($base === '') {
            $base = sanitize_title(__('dossier', 'mj-member'));
        }
        if ($base === '') {
            $base = 'dossier';
        }

        $slug = $base;
        $suffix = 2;
        while (self::slug_exists($slug, $excludeId)) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }

    private static function slug_exists(string $slug, int $excludeId): bool
    {
        global $wpdb;
        $table = self::table_name();

        if ($excludeId > 0) {
            $sql = $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE slug = %s AND id <> %d", $slug, $excludeId);
        } else {
            $sql = $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE slug = %s", $slug);
        }

        $count = $wpdb->get_var($sql);
        return $count ? (int) $count > 0 : false;
    }
}
