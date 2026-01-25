<?php

namespace Mj\Member\Classes\Crud;

use Mj\Member\Classes\MjTools;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

final class MjBadges extends MjTools implements CrudRepositoryInterface
{
    private const TABLE = 'mj_badges';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_ARCHIVED = 'archived';

    /**
     * @return array<int,string>
     */
    public static function statuses(): array
    {
        return array(self::STATUS_ACTIVE, self::STATUS_ARCHIVED);
    }

    public static function get_status_labels(): array
    {
        return array(
            self::STATUS_ACTIVE => __('Actif', 'mj-member'),
            self::STATUS_ARCHIVED => __('Archivé', 'mj-member'),
        );
    }

    public static function table_name(): string
    {
        if (function_exists('mj_member_get_badges_table_name')) {
            return mj_member_get_badges_table_name();
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

        if (!empty($args['search'])) {
            $builder->where_like_any(array('label', 'summary', 'description'), (string) $args['search']);
        }

        $allowedOrderBy = array('display_order', 'label', 'created_at', 'updated_at', 'status', 'id');
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
     */
    public static function count(array $args = array())
    {
        global $wpdb;
        $table = self::table_name();

        $defaults = array(
            'status' => '',
            'statuses' => array(),
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

        if (!empty($args['search'])) {
            $builder->where_like_any(array('label', 'summary', 'description'), (string) $args['search']);
        }

        list($sql, $params) = $builder->build_count();
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        return (int) $wpdb->get_var($sql);
    }

    /**
     * @param mixed $data
     * @return int|WP_Error
     */
    public static function create($data)
    {
        global $wpdb;
        $table = self::table_name();

        $payload = self::sanitize_payload($data, false);
        if (is_wp_error($payload)) {
            return $payload;
        }

        $payload['created_at'] = current_time('mysql');
        $payload['updated_at'] = current_time('mysql');

        $inserted = $wpdb->insert($table, $payload, self::get_format($payload));
        if ($inserted === false) {
            return new WP_Error('mj_badge_create_failed', __('Impossible de créer le badge.', 'mj-member'));
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
        global $wpdb;
        $table = self::table_name();
        $id = (int) $id;
        if ($id <= 0) {
            return new WP_Error('mj_badge_invalid_id', __('Identifiant de badge invalide.', 'mj-member'));
        }

        $payload = self::sanitize_payload($data, true, $id);
        if (is_wp_error($payload)) {
            return $payload;
        }

        if (empty($payload)) {
            return true;
        }

        $payload['updated_at'] = current_time('mysql');

        $updated = $wpdb->update($table, $payload, array('id' => $id), self::get_format($payload), array('%d'));
        if ($updated === false) {
            return new WP_Error('mj_badge_update_failed', __('Impossible de mettre à jour le badge.', 'mj-member'));
        }

        return true;
    }

    /**
     * @param int $id
     * @return true|WP_Error
     */
    public static function delete($id)
    {
        global $wpdb;
        $table = self::table_name();
        $id = (int) $id;
        if ($id <= 0) {
            return new WP_Error('mj_badge_invalid_id', __('Identifiant de badge invalide.', 'mj-member'));
        }

        $deleted = $wpdb->delete($table, array('id' => $id), array('%d'));
        if ($deleted === false) {
            return new WP_Error('mj_badge_delete_failed', __('Impossible de supprimer le badge.', 'mj-member'));
        }

        return true;
    }

    /**
     * @param int $id
     * @return array<string,mixed>|null
     */
    public static function get($id): ?array
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
        $label = isset($row['label']) ? sanitize_text_field((string) $row['label']) : '';
        $summary = isset($row['summary']) ? sanitize_text_field((string) $row['summary']) : '';
        $description = isset($row['description']) ? wp_kses_post((string) $row['description']) : '';
        $criteriaRecords = MjBadgeCriteria::get_for_badge($id);
        $criteria = array();

        if (!empty($criteriaRecords)) {
            foreach ($criteriaRecords as $record) {
                $criteria[] = isset($record['label']) ? (string) $record['label'] : '';
            }
        } elseif (!empty($row['criteria'])) {
            $decoded = json_decode((string) $row['criteria'], true);
            if (is_array($decoded)) {
                foreach ($decoded as $entry) {
                    $criteria[] = sanitize_text_field((string) $entry);
                }
            }
        }

        return array(
            'id' => $id,
            'slug' => isset($row['slug']) ? sanitize_title((string) $row['slug']) : '',
            'label' => $label,
            'summary' => $summary,
            'description' => $description,
            'criteria' => $criteria,
            'criteria_records' => $criteriaRecords,
            'prompt' => isset($row['prompt']) ? sanitize_textarea_field((string) $row['prompt']) : '',
            'icon' => isset($row['icon']) ? sanitize_key((string) $row['icon']) : '',
            'image_id' => isset($row['image_id']) ? max(0, (int) $row['image_id']) : 0,
            'display_order' => isset($row['display_order']) ? (int) $row['display_order'] : 0,
            'status' => self::normalize_status($row['status'] ?? self::STATUS_ACTIVE) ?: self::STATUS_ACTIVE,
            'created_at' => isset($row['created_at']) ? (string) $row['created_at'] : '',
            'updated_at' => isset($row['updated_at']) ? (string) $row['updated_at'] : '',
        );
    }

    /**
     * @param mixed $value
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

        $label = '';
        if (isset($data['label'])) {
            $label = sanitize_text_field((string) $data['label']);
            if ($label === '' && !$isUpdate) {
                return new WP_Error('mj_badge_missing_label', __('Le nom du badge est requis.', 'mj-member'));
            }
            if ($label !== '') {
                $payload['label'] = $label;
            }
        } elseif (!$isUpdate) {
            return new WP_Error('mj_badge_missing_label', __('Le nom du badge est requis.', 'mj-member'));
        }

        $slug = null;
        if (isset($data['slug'])) {
            $slugCandidate = sanitize_title((string) $data['slug']);
            $slug = $slugCandidate !== '' ? $slugCandidate : null;
        } elseif (!$isUpdate && $label !== '') {
            $slug = sanitize_title($label);
        }

        if ($slug !== null) {
            if ($slug === '') {
                return new WP_Error('mj_badge_missing_slug', __('Le slug du badge est requis.', 'mj-member'));
            }
            if (self::slug_exists($slug, $currentId)) {
                return new WP_Error('mj_badge_duplicate_slug', __('Un badge avec ce slug existe déjà.', 'mj-member'));
            }
            $payload['slug'] = $slug;
        }

        if (isset($data['summary'])) {
            $payload['summary'] = sanitize_text_field((string) $data['summary']);
        }

        if (isset($data['description'])) {
            $payload['description'] = wp_kses_post((string) $data['description']);
        }

        if (isset($data['prompt'])) {
            $payload['prompt'] = sanitize_textarea_field((string) $data['prompt']);
        }

        if (isset($data['icon'])) {
            $payload['icon'] = sanitize_key((string) $data['icon']);
        }

        if (array_key_exists('image_id', $data)) {
            $imageId = (int) $data['image_id'];
            $payload['image_id'] = $imageId > 0 ? $imageId : 0;
        }

        if (isset($data['display_order'])) {
            $payload['display_order'] = (int) $data['display_order'];
        }

        if (isset($data['status'])) {
            $status = self::normalize_status($data['status']);
            if ($status === '') {
                $status = self::STATUS_ACTIVE;
            }
            $payload['status'] = $status;
        }

        if (isset($data['criteria'])) {
            $criteria = self::normalize_criteria($data['criteria']);
            $payload['criteria'] = !empty($criteria) ? wp_json_encode($criteria) : null;
        }

        return $payload;
    }

    /**
     * @param mixed $criteria
     * @return array<int,string>
     */
    private static function normalize_criteria($criteria): array
    {
        if (is_string($criteria)) {
            $criteria = array_map('trim', preg_split('/\r\n|\r|\n/', $criteria));
        }

        if (!is_array($criteria)) {
            return array();
        }

        $normalized = array();
        foreach ($criteria as $entry) {
            $candidate = sanitize_text_field((string) $entry);
            if ($candidate !== '') {
                $normalized[] = $candidate;
            }
        }

        return array_values(array_unique($normalized));
    }

    private static function slug_exists(string $slug, int $currentId = 0): bool
    {
        global $wpdb;
        $table = self::table_name();
        $slug = sanitize_title($slug);
        if ($slug === '') {
            return false;
        }

        if ($currentId > 0) {
            $sql = $wpdb->prepare("SELECT id FROM {$table} WHERE slug = %s AND id <> %d LIMIT 1", $slug, $currentId);
        } else {
            $sql = $wpdb->prepare("SELECT id FROM {$table} WHERE slug = %s LIMIT 1", $slug);
        }

        $found = $wpdb->get_var($sql);
        return !empty($found);
    }

    /**
     * @param array<string,mixed>|null $payload
     * @return array<int,string>
     */
    private static function get_format(?array $payload = null): array
    {
        $map = array(
            'slug' => '%s',
            'label' => '%s',
            'summary' => '%s',
            'description' => '%s',
            'criteria' => '%s',
            'prompt' => '%s',
            'icon' => '%s',
            'image_id' => '%d',
            'display_order' => '%d',
            'status' => '%s',
            'created_at' => '%s',
            'updated_at' => '%s',
        );

        if ($payload === null) {
            return array_values($map);
        }

        $formats = array();
        foreach ($payload as $key => $_) {
            if (isset($map[$key])) {
                $formats[] = $map[$key];
            }
        }

        return $formats;
    }
}
