<?php

namespace Mj\Member\Classes\Crud;

use Mj\Member\Classes\MjTools;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * CRUD pour la gestion des trophées.
 */
final class MjTrophies extends MjTools implements CrudRepositoryInterface
{
    private const TABLE = 'mj_trophies';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_ARCHIVED = 'archived';


    /**
     * @return array<int,string>
     */
    public static function statuses(): array
    {
        return array(self::STATUS_ACTIVE, self::STATUS_ARCHIVED);
    }

    /**
     * @return array<string,string>
     */
    public static function get_status_labels(): array
    {
        return array(
            self::STATUS_ACTIVE => __('Actif', 'mj-member'),
            self::STATUS_ARCHIVED => __('Archivé', 'mj-member'),
        );
    }

    public static function table_name(): string
    {
        if (function_exists('mj_member_get_trophies_table_name')) {
            return mj_member_get_trophies_table_name();
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
            'status' => '',
            'statuses' => array(),
            'auto_mode' => null,
            'search' => '',
            'orderby' => 'display_order',
            'order' => 'ASC',
            'limit' => 0,
            'offset' => 0,
        );
        $args = wp_parse_args($args, $defaults);

        $builder = CrudQueryBuilder::for_table($table);

        $statuses = array();
        if (!empty($args['statuses']) && is_array($args['statuses'])) {
            foreach ($args['statuses'] as $candidate) {
                $normalized = self::normalize_status($candidate);
                if ($normalized !== '') {
                    $statuses[$normalized] = $normalized;
                }
            }
        } elseif (!empty($args['status'])) {
            $single = self::normalize_status($args['status']);
            if ($single !== '') {
                $statuses[$single] = $single;
            }
        }

        if (!empty($statuses)) {
            $builder->where_in_strings('status', array_values($statuses), static function ($value) {
                return self::normalize_status($value);
            });
        }

        if ($args['auto_mode'] !== null) {
            $builder->where_equals('auto_mode', (int) $args['auto_mode']);
        }

        if (!empty($args['search'])) {
            $builder->where_like_any(array('title', 'description'), (string) $args['search']);
        }

        $allowedOrderBy = array('display_order', 'title', 'xp', 'created_at', 'updated_at', 'status', 'id', 'auto_mode');
        $orderby = sanitize_key((string) $args['orderby']);
        if (!in_array($orderby, $allowedOrderBy, true)) {
            $orderby = 'display_order';
        }

        $order = strtoupper((string) $args['order']) === 'DESC' ? 'DESC' : 'ASC';
        $limit = (int) $args['limit'];
        $offset = max(0, (int) $args['offset']);

        list($sql, $params) = $builder->build_select('*', $orderby, $order, $limit, $offset);
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
            'status' => '',
            'statuses' => array(),
            'auto_mode' => null,
            'search' => '',
        );
        $args = wp_parse_args($args, $defaults);

        $builder = CrudQueryBuilder::for_table($table);

        $statuses = array();
        if (!empty($args['statuses']) && is_array($args['statuses'])) {
            foreach ($args['statuses'] as $candidate) {
                $normalized = self::normalize_status($candidate);
                if ($normalized !== '') {
                    $statuses[$normalized] = $normalized;
                }
            }
        } elseif (!empty($args['status'])) {
            $single = self::normalize_status($args['status']);
            if ($single !== '') {
                $statuses[$single] = $single;
            }
        }

        if (!empty($statuses)) {
            $builder->where_in_strings('status', array_values($statuses), static function ($value) {
                return self::normalize_status($value);
            });
        }

        if ($args['auto_mode'] !== null) {
            $builder->where_equals('auto_mode', (int) $args['auto_mode']);
        }

        if (!empty($args['search'])) {
            $builder->where_like_any(array('title', 'description'), (string) $args['search']);
        }

        list($sql, $params) = $builder->build_count();
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        return (int) $wpdb->get_var($sql);
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
     * @param string $slug
     * @return array<string,mixed>|null
     */
    public static function get_by_slug(string $slug): ?array
    {
        global $wpdb;
        $table = self::table_name();
        $slug = sanitize_title($slug);
        if ($slug === '') {
            return null;
        }

        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE slug = %s", $slug), ARRAY_A);
        if (!$row) {
            return null;
        }

        return self::format_row($row);
    }

    /**
     * @param array<string,mixed>|null $row
     * @return array<string,mixed>
     */
    private static function format_row($row): array
    {
        $id = isset($row['id']) ? (int) $row['id'] : 0;
        $title = isset($row['title']) ? sanitize_text_field((string) $row['title']) : '';
        $description = isset($row['description']) ? wp_kses_post((string) $row['description']) : '';

        return array(
            'id' => $id,
            'slug' => isset($row['slug']) ? sanitize_title((string) $row['slug']) : '',
            'title' => $title,
            'description' => $description,
            'xp' => isset($row['xp']) ? (int) $row['xp'] : 0,
            'auto_mode' => isset($row['auto_mode']) ? (bool) $row['auto_mode'] : false,
            'auto_hook' => isset($row['auto_hook']) ? sanitize_key((string) $row['auto_hook']) : '',
            'auto_threshold' => isset($row['auto_threshold']) ? (int) $row['auto_threshold'] : 0,
            'image_id' => isset($row['image_id']) ? max(0, (int) $row['image_id']) : 0,
            'display_order' => isset($row['display_order']) ? (int) $row['display_order'] : 0,
            'status' => self::normalize_status($row['status'] ?? self::STATUS_ACTIVE) ?: self::STATUS_ACTIVE,
            'created_at' => isset($row['created_at']) ? (string) $row['created_at'] : '',
            'updated_at' => isset($row['updated_at']) ? (string) $row['updated_at'] : '',
        );
    }

    /**
     * @param mixed $value
     * @return string
     */
    private static function normalize_status($value): string
    {
        $candidate = sanitize_key((string) $value);
        if ($candidate === self::STATUS_ARCHIVED) {
            return self::STATUS_ARCHIVED;
        }

        if ($candidate === self::STATUS_ACTIVE) {
            return self::STATUS_ACTIVE;
        }

        return $candidate === '' ? '' : self::STATUS_ACTIVE;
    }

    /**
     * @param mixed $data
     * @param bool $isUpdate
     * @param int $currentId
     * @return array<string,mixed>|WP_Error
     */
    private static function sanitize_payload($data, bool $isUpdate = false, int $currentId = 0)
    {
        $payload = array();

        $title = '';
        if (isset($data['title'])) {
            $title = sanitize_text_field((string) $data['title']);
            if ($title === '' && !$isUpdate) {
                return new WP_Error('mj_trophy_missing_title', __('Le titre du trophée est requis.', 'mj-member'));
            }
            $payload['title'] = $title;
        }

        if (isset($data['slug'])) {
            $slug = sanitize_title((string) $data['slug']);
            if ($slug === '' && $title !== '') {
                $slug = sanitize_title($title);
            }
            $payload['slug'] = $slug;
        } elseif (!$isUpdate && $title !== '') {
            $payload['slug'] = sanitize_title($title);
        }

        if (isset($data['description'])) {
            $payload['description'] = wp_kses_post((string) $data['description']);
        }

        if (isset($data['xp'])) {
            $payload['xp'] = max(0, (int) $data['xp']);
        }

        if (isset($data['auto_mode'])) {
            $payload['auto_mode'] = !empty($data['auto_mode']) ? 1 : 0;
        }

        if (isset($data['auto_hook'])) {
            $payload['auto_hook'] = sanitize_key((string) $data['auto_hook']);
        }

        if (isset($data['auto_threshold'])) {
            $payload['auto_threshold'] = max(0, (int) $data['auto_threshold']);
        }

        if (isset($data['image_id'])) {
            $payload['image_id'] = max(0, (int) $data['image_id']);
        }

        if (isset($data['display_order'])) {
            $payload['display_order'] = max(0, (int) $data['display_order']);
        }

        if (isset($data['status'])) {
            $normalized = self::normalize_status($data['status']);
            if ($normalized !== '') {
                $payload['status'] = $normalized;
            }
        }

        return $payload;
    }

    /**
     * @param array<string,mixed> $data
     * @return int|WP_Error
     */
    public static function create($data)
    {
        global $wpdb;
        $table = self::table_name();
        $data = is_array($data) ? $data : array();

        $payload = self::sanitize_payload($data, false, 0);
        if (is_wp_error($payload)) {
            return $payload;
        }

        if (!isset($payload['status'])) {
            $payload['status'] = self::STATUS_ACTIVE;
        }

        $payload['created_at'] = current_time('mysql');
        $payload['updated_at'] = current_time('mysql');

        $formats = self::get_formats($payload);
        $result = $wpdb->insert($table, $payload, $formats);
        if ($result === false) {
            return new WP_Error('mj_trophy_insert_failed', __('Impossible de créer le trophée.', 'mj-member'));
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * @param int $id
     * @param array<string,mixed> $data
     * @return bool|WP_Error
     */
    public static function update($id, $data)
    {
        global $wpdb;
        $table = self::table_name();
        $id = (int) $id;
        $data = is_array($data) ? $data : array();

        if ($id <= 0) {
            return new WP_Error('mj_trophy_invalid_id', __('Identifiant de trophée invalide.', 'mj-member'));
        }

        $payload = self::sanitize_payload($data, true, $id);
        if (is_wp_error($payload)) {
            return $payload;
        }

        if (empty($payload)) {
            return true;
        }

        $payload['updated_at'] = current_time('mysql');
        $formats = self::get_formats($payload);

        $result = $wpdb->update($table, $payload, array('id' => $id), $formats, array('%d'));

        return $result !== false;
    }

    /**
     * @param int $id
     * @return bool
     */
    public static function delete($id)
    {
        global $wpdb;
        $table = self::table_name();
        $id = (int) $id;

        if ($id <= 0) {
            return false;
        }

        $result = $wpdb->delete($table, array('id' => $id), array('%d'));
        return $result !== false;
    }

    /**
     * @param int $id
     * @return bool
     */
    public static function archive(int $id): bool
    {
        $result = self::update($id, array('status' => self::STATUS_ARCHIVED));
        return $result === true;
    }

    /**
     * @param int $id
     * @return bool
     */
    public static function restore(int $id): bool
    {
        $result = self::update($id, array('status' => self::STATUS_ACTIVE));
        return $result === true;
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string>
     */
    private static function get_formats(array $payload): array
    {
        $formats = array();
        foreach ($payload as $key => $value) {
            if (in_array($key, array('id', 'xp', 'auto_mode', 'auto_threshold', 'image_id', 'display_order'), true)) {
                $formats[] = '%d';
            } else {
                $formats[] = '%s';
            }
        }
        return $formats;
    }

    /**
     * Récupère les trophées auto selon un hook spécifique.
     *
     * @param string $hook
     * @return array<int,array<string,mixed>>
     */
    public static function get_by_auto_hook(string $hook): array
    {
        global $wpdb;
        $table = self::table_name();
        $hook = sanitize_key($hook);

        if ($hook === '') {
            return array();
        }

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE auto_mode = 1 AND auto_hook = %s AND status = %s ORDER BY display_order ASC",
            $hook,
            self::STATUS_ACTIVE
        ), ARRAY_A);

        if (empty($rows)) {
            return array();
        }

        return array_map(array(__CLASS__, 'format_row'), $rows);
    }
}
