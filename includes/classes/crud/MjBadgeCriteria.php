<?php

namespace Mj\Member\Classes\Crud;

use Mj\Member\Classes\MjTools;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

final class MjBadgeCriteria extends MjTools implements CrudRepositoryInterface
{
    private const TABLE = 'mj_badge_criteria';

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
        if (function_exists('mj_member_get_badge_criteria_table_name')) {
            return mj_member_get_badge_criteria_table_name();
        }

        return self::getTableName(self::TABLE);
    }

    /**
     * @param array<string,mixed> $args
     * @return array<int,array<string,mixed>>
     */
    public static function get_all(array $args = array())
    {
        $args = wp_parse_args($args, array(
            'badge_id' => 0,
            'status' => '',
            'include_archived' => false,
            'search' => '',
            'orderby' => 'display_order',
            'order' => 'ASC',
            'limit' => 0,
            'offset' => 0,
        ));

        $badgeId = (int) $args['badge_id'];
        if ($badgeId > 0 && empty($args['search'])) {
            return self::get_for_badge($badgeId, array(
                'include_archived' => !empty($args['include_archived']),
                'orderby' => $args['orderby'],
                'order' => $args['order'],
            ));
        }

        global $wpdb;
        $table = self::table_name();

        $where = array();
        $params = array();

        if ($badgeId > 0) {
            $where[] = 'badge_id = %d';
            $params[] = $badgeId;
        }

        $statusFilter = sanitize_key((string) $args['status']);
        if ($statusFilter !== '') {
            $normalized = self::normalize_status($statusFilter);
            if ($normalized !== '') {
                $where[] = 'status = %s';
                $params[] = $normalized;
            }
        } elseif (empty($args['include_archived'])) {
            $where[] = 'status = %s';
            $params[] = self::STATUS_ACTIVE;
        }

        if (!empty($args['search'])) {
            $like = '%' . $wpdb->esc_like((string) $args['search']) . '%';
            $where[] = '(label LIKE %s OR slug LIKE %s)';
            $params[] = $like;
            $params[] = $like;
        }

        $sql = "SELECT * FROM {$table}";
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $allowedOrderBy = array('display_order', 'label', 'created_at', 'updated_at', 'id', 'slug', 'status');
        $orderby = sanitize_key((string) $args['orderby']);
        if (!in_array($orderby, $allowedOrderBy, true)) {
            $orderby = 'display_order';
        }

        $order = strtoupper((string) $args['order']) === 'DESC' ? 'DESC' : 'ASC';
        $sql .= " ORDER BY {$orderby} {$order}";

        $limit = (int) $args['limit'];
        $offset = max(0, (int) $args['offset']);
        if ($limit > 0) {
            $sql .= $wpdb->prepare(' LIMIT %d OFFSET %d', $limit, $offset);
        }

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
        $args = wp_parse_args($args, array(
            'badge_id' => 0,
            'status' => '',
            'include_archived' => false,
            'search' => '',
        ));

        global $wpdb;
        $table = self::table_name();

        $where = array();
        $params = array();

        $badgeId = (int) $args['badge_id'];
        if ($badgeId > 0) {
            $where[] = 'badge_id = %d';
            $params[] = $badgeId;
        }

        $statusFilter = sanitize_key((string) $args['status']);
        if ($statusFilter !== '') {
            $normalized = self::normalize_status($statusFilter);
            if ($normalized !== '') {
                $where[] = 'status = %s';
                $params[] = $normalized;
            }
        } elseif (empty($args['include_archived'])) {
            $where[] = 'status = %s';
            $params[] = self::STATUS_ACTIVE;
        }

        if (!empty($args['search'])) {
            $like = '%' . $wpdb->esc_like((string) $args['search']) . '%';
            $where[] = '(label LIKE %s OR slug LIKE %s)';
            $params[] = $like;
            $params[] = $like;
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
     * Get a single criterion by ID.
     *
     * @param int $id
     * @return array<string,mixed>|null
     */
    public static function get_by_id(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        global $wpdb;
        $table = self::table_name();

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id),
            ARRAY_A
        );

        if (!$row) {
            return null;
        }

        return self::format_row($row);
    }

    /**
     * @param array<string,mixed> $data
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
            return new WP_Error('mj_badge_criteria_insert_failed', __('Impossible de créer un critère.', 'mj-member'));
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * @param int $id
     * @param array<string,mixed> $data
     * @return true|WP_Error
     */
    public static function update($id, $data)
    {
        global $wpdb;
        $table = self::table_name();
        $id = (int) $id;
        if ($id <= 0) {
            return new WP_Error('mj_badge_criteria_invalid_id', __('Identifiant de critère invalide.', 'mj-member'));
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
            return new WP_Error('mj_badge_criteria_update_failed', __('Impossible de mettre à jour le critère.', 'mj-member'));
        }

        return true;
    }

    public static function delete($id)
    {
        global $wpdb;
        $table = self::table_name();
        $id = (int) $id;
        if ($id <= 0) {
            return new WP_Error('mj_badge_criteria_invalid_id', __('Identifiant de critère invalide.', 'mj-member'));
        }

        $deleted = $wpdb->delete($table, array('id' => $id), array('%d'));
        if ($deleted === false) {
            return new WP_Error('mj_badge_criteria_delete_failed', __('Impossible de supprimer le critère.', 'mj-member'));
        }

        return true;
    }

    /**
     * @param int $badgeId
     * @param array<string,mixed> $args
     * @return array<int,array<string,mixed>>
     */
    public static function get_for_badge(int $badgeId, array $args = array()): array
    {
        $badgeId = (int) $badgeId;
        if ($badgeId <= 0) {
            return array();
        }

        global $wpdb;
        $table = self::table_name();

        $defaults = array(
            'include_archived' => false,
            'orderby' => 'display_order',
            'order' => 'ASC',
        );
        $args = wp_parse_args($args, $defaults);

        $where = array('badge_id = %d');
        $params = array($badgeId);

        if (empty($args['include_archived'])) {
            $where[] = 'status = %s';
            $params[] = self::STATUS_ACTIVE;
        }

        $sql = "SELECT * FROM {$table} WHERE " . implode(' AND ', $where);

        $allowedOrderBy = array('display_order', 'label', 'created_at', 'updated_at', 'id');
        $orderby = sanitize_key((string) $args['orderby']);
        if (!in_array($orderby, $allowedOrderBy, true)) {
            $orderby = 'display_order';
        }

        $order = strtoupper((string) $args['order']) === 'DESC' ? 'DESC' : 'ASC';
        $sql .= " ORDER BY {$orderby} {$order}";

        $prepared = $wpdb->prepare($sql, $params);
        $rows = $wpdb->get_results($prepared, ARRAY_A);
        if (empty($rows)) {
            return array();
        }

        return array_map(array(__CLASS__, 'format_row'), $rows);
    }

    /**
     * @param int $badgeId
     * @param array<int> $ids
     * @return array<int,int>
     */
    public static function filter_ids_for_badge(int $badgeId, array $ids): array
    {
        $badgeId = (int) $badgeId;
        if ($badgeId <= 0 || empty($ids)) {
            return array();
        }

        $ids = array_values(array_unique(array_map('intval', $ids)));
        $ids = array_filter($ids, static function ($id) {
            return $id > 0;
        });
        if (empty($ids)) {
            return array();
        }

        global $wpdb;
        $table = self::table_name();

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $params = array_merge(array($badgeId), $ids);

        $sql = $wpdb->prepare(
            "SELECT id FROM {$table} WHERE badge_id = %d AND id IN ({$placeholders})",
            $params
        );

        $rows = $wpdb->get_col($sql);
        if (empty($rows)) {
            return array();
        }

        return array_map('intval', $rows);
    }

    /**
     * @param int $badgeId
     * @param array<int|string> $labels
     * @return true|WP_Error
     */
    public static function sync_labels(int $badgeId, array $labels)
    {
        $badgeId = (int) $badgeId;
        if ($badgeId <= 0) {
            return new WP_Error('mj_badge_criteria_badge_required', __('Badge requis.', 'mj-member'));
        }

        global $wpdb;
        $table = self::table_name();

        $existing_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE badge_id = %d",
            $badgeId
        ), ARRAY_A);

        $existingBySlug = array();
        $existingById = array();
        if (is_array($existing_rows)) {
            foreach ($existing_rows as $row) {
                $slug = isset($row['slug']) ? (string) $row['slug'] : '';
                if ($slug !== '') {
                    $existingBySlug[$slug] = $row;
                }
                $existingById[(int) $row['id']] = $row;
            }
        }

        $reserved = array();
        $usedIds = array();
        $order = 0;

        foreach ($labels as $labelRaw) {
            $label = trim((string) $labelRaw);
            if ($label === '') {
                continue;
            }

            $baseSlug = sanitize_title($label);
            if ($baseSlug === '') {
                $baseSlug = sanitize_title('criterion-' . $badgeId);
            }
            if ($baseSlug === '') {
                $baseSlug = 'criterion-' . $badgeId;
            }

            $slug = $baseSlug;
            $suffix = 2;
            while (isset($reserved[$slug])) {
                $slug = $baseSlug . '-' . $suffix;
                $suffix++;
            }
            $reserved[$slug] = true;

            if (isset($existingBySlug[$slug])) {
                $row = $existingBySlug[$slug];
                $criterionId = (int) $row['id'];
                $usedIds[$criterionId] = true;

                $wpdb->update(
                    $table,
                    array(
                        'label' => sanitize_text_field($label),
                        'display_order' => $order,
                        'status' => self::STATUS_ACTIVE,
                        'updated_at' => current_time('mysql'),
                    ),
                    array('id' => $criterionId),
                    array('%s', '%d', '%s', '%s'),
                    array('%d')
                );
            } else {
                $inserted = $wpdb->insert(
                    $table,
                    array(
                        'badge_id' => $badgeId,
                        'slug' => $slug,
                        'label' => sanitize_text_field($label),
                        'display_order' => $order,
                        'status' => self::STATUS_ACTIVE,
                        'created_at' => current_time('mysql'),
                        'updated_at' => current_time('mysql'),
                    ),
                    array('%d', '%s', '%s', '%d', '%s', '%s', '%s')
                );

                if ($inserted === false) {
                    return new WP_Error('mj_badge_criteria_sync_failed', __('Impossible de synchroniser les critères.', 'mj-member'));
                }
            }

            $order++;
        }

        if (!empty($existingById)) {
            foreach ($existingById as $id => $row) {
                if (isset($usedIds[$id])) {
                    continue;
                }

                $wpdb->update(
                    $table,
                    array(
                        'status' => self::STATUS_ARCHIVED,
                        'updated_at' => current_time('mysql'),
                    ),
                    array('id' => $id),
                    array('%s', '%s'),
                    array('%d')
                );
            }
        }

        return true;
    }

    /**
     * @param array<string,mixed>|null $row
     * @return array<string,mixed>
     */
    private static function format_row($row): array
    {
        return array(
            'id' => isset($row['id']) ? (int) $row['id'] : 0,
            'badge_id' => isset($row['badge_id']) ? (int) $row['badge_id'] : 0,
            'slug' => isset($row['slug']) ? sanitize_title((string) $row['slug']) : '',
            'label' => isset($row['label']) ? sanitize_text_field((string) $row['label']) : '',
            'description' => isset($row['description']) ? wp_kses_post((string) $row['description']) : '',
            'xp' => isset($row['xp']) ? max(0, (int) $row['xp']) : 0,
            'coins' => isset($row['coins']) ? max(0, (int) $row['coins']) : 0,
            'display_order' => isset($row['display_order']) ? (int) $row['display_order'] : 0,
            'status' => self::normalize_status($row['status'] ?? self::STATUS_ACTIVE) ?: self::STATUS_ACTIVE,
            'created_at' => isset($row['created_at']) ? (string) $row['created_at'] : '',
            'updated_at' => isset($row['updated_at']) ? (string) $row['updated_at'] : '',
        );
    }

    private static function normalize_status($status): string
    {
        $candidate = sanitize_key((string) $status);
        if ($candidate === self::STATUS_ARCHIVED) {
            return self::STATUS_ARCHIVED;
        }

        if ($candidate === self::STATUS_ACTIVE) {
            return self::STATUS_ACTIVE;
        }

        return $candidate === '' ? self::STATUS_ACTIVE : self::STATUS_ACTIVE;
    }

    /**
     * @param array<string,mixed> $data
     * @param bool $isUpdate
     * @param int $currentId
     * @return array<string,mixed>|WP_Error
     */
    private static function sanitize_payload($data, bool $isUpdate = false, int $currentId = 0)
    {
        $payload = array();

        if (isset($data['badge_id'])) {
            $payload['badge_id'] = (int) $data['badge_id'];
            if ($payload['badge_id'] <= 0) {
                return new WP_Error('mj_badge_criteria_badge_required', __('Badge requis.', 'mj-member'));
            }
        } elseif (!$isUpdate) {
            return new WP_Error('mj_badge_criteria_badge_required', __('Badge requis.', 'mj-member'));
        }

        if (isset($data['label'])) {
            $label = sanitize_text_field((string) $data['label']);
            if ($label === '' && !$isUpdate) {
                return new WP_Error('mj_badge_criteria_label_required', __('Le libellé du critère est requis.', 'mj-member'));
            }
            if ($label !== '') {
                $payload['label'] = $label;
            }
        } elseif (!$isUpdate) {
            return new WP_Error('mj_badge_criteria_label_required', __('Le libellé du critère est requis.', 'mj-member'));
        }

        if (isset($data['description'])) {
            $payload['description'] = wp_kses_post((string) $data['description']);
        }

        if (isset($data['xp'])) {
            $payload['xp'] = max(0, (int) $data['xp']);
        }

        if (isset($data['coins'])) {
            $payload['coins'] = max(0, (int) $data['coins']);
        }

        if (isset($data['display_order'])) {
            $payload['display_order'] = (int) $data['display_order'];
        }

        if (isset($data['status'])) {
            $payload['status'] = self::normalize_status($data['status']);
        }

        if (isset($data['slug'])) {
            $slug = sanitize_title((string) $data['slug']);
            if ($slug === '' && !$isUpdate) {
                $slug = sanitize_title($payload['label'] ?? '');
            }

            if ($slug !== '') {
                $payload['slug'] = $slug;
            }
        }

        if (!$isUpdate && !isset($payload['slug'])) {
            $label = $payload['label'] ?? '';
            $slug = sanitize_title($label);
            if ($slug === '') {
                $slug = 'criterion-' . $payload['badge_id'];
            }
            $payload['slug'] = $slug;
        }

        if (isset($payload['slug']) && isset($payload['badge_id'])) {
            global $wpdb;
            $table = self::table_name();
            $slug = $payload['slug'];
            $badgeId = (int) $payload['badge_id'];

            if ($slug !== '') {
                if ($currentId > 0) {
                    $sql = $wpdb->prepare(
                        "SELECT id FROM {$table} WHERE badge_id = %d AND slug = %s AND id <> %d",
                        $badgeId,
                        $slug,
                        $currentId
                    );
                } else {
                    $sql = $wpdb->prepare(
                        "SELECT id FROM {$table} WHERE badge_id = %d AND slug = %s",
                        $badgeId,
                        $slug
                    );
                }

                $existing = $wpdb->get_var($sql);
                if (!empty($existing)) {
                    return new WP_Error('mj_badge_criteria_duplicate_slug', __('Un critère avec ce slug existe déjà pour ce badge.', 'mj-member'));
                }
            }
        }

        return $payload;
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<int,string>
     */
    private static function get_format(array $payload): array
    {
        $map = array(
            'badge_id' => '%d',
            'slug' => '%s',
            'label' => '%s',
            'description' => '%s',
            'xp' => '%d',
            'coins' => '%d',
            'display_order' => '%d',
            'status' => '%s',
            'created_at' => '%s',
            'updated_at' => '%s',
        );

        $formats = array();
        foreach ($payload as $key => $_) {
            if (isset($map[$key])) {
                $formats[] = $map[$key];
            }
        }

        return $formats;
    }
}
