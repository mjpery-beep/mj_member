<?php

namespace Mj\Member\Classes\Crud;

use Mj\Member\Classes\MjTools;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * CRUD pour la gestion des types d'actions de gamification.
 */
final class MjActionTypes extends MjTools implements CrudRepositoryInterface
{
    private const TABLE = 'mj_action_types';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_ARCHIVED = 'archived';

    public const ATTRIBUTION_AUTO = 'auto';
    public const ATTRIBUTION_MANUAL = 'manual';

    public const CATEGORY_SITE_IDEAS = 'site_ideas';
    public const CATEGORY_SITE_TESTIMONIALS = 'site_testimonials';
    public const CATEGORY_SITE_PHOTOS = 'site_photos';
    public const CATEGORY_SITE_ACTIVITIES = 'site_activities';
    public const CATEGORY_SITE_INTERACTIONS = 'site_interactions';
    public const CATEGORY_MJ_CLEANING = 'mj_cleaning';
    public const CATEGORY_MJ_LOGISTICS = 'mj_logistics';
    public const CATEGORY_MJ_COLLECTIVE = 'mj_collective';
    public const CATEGORY_MJ_ATTITUDE = 'mj_attitude';

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

    /**
     * @return array<string,string>
     */
    public static function get_attribution_labels(): array
    {
        return array(
            self::ATTRIBUTION_AUTO => __('Automatique', 'mj-member'),
            self::ATTRIBUTION_MANUAL => __('Manuelle', 'mj-member'),
        );
    }

    /**
     * @return array<string,string>
     */
    public static function get_category_labels(): array
    {
        return array(
            self::CATEGORY_SITE_IDEAS => __('Idées & participation (site)', 'mj-member'),
            self::CATEGORY_SITE_TESTIMONIALS => __('Témoignages (site)', 'mj-member'),
            self::CATEGORY_SITE_PHOTOS => __('Photos & événements (site)', 'mj-member'),
            self::CATEGORY_SITE_ACTIVITIES => __('Activités & ateliers (site)', 'mj-member'),
            self::CATEGORY_SITE_INTERACTIONS => __('Interactions (site)', 'mj-member'),
            self::CATEGORY_MJ_CLEANING => __('Entretien & respect des lieux (MJ)', 'mj-member'),
            self::CATEGORY_MJ_LOGISTICS => __('Aide logistique (MJ)', 'mj-member'),
            self::CATEGORY_MJ_COLLECTIVE => __('Vie collective (MJ)', 'mj-member'),
            self::CATEGORY_MJ_ATTITUDE => __('Attitude & régularité (MJ)', 'mj-member'),
        );
    }

    public static function table_name(): string
    {
        if (function_exists('mj_member_get_action_types_table_name')) {
            return mj_member_get_action_types_table_name();
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
            'category' => '',
            'attribution' => '',
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

        if (!empty($args['category'])) {
            $builder->where_equals('category', sanitize_key((string) $args['category']));
        }

        if (!empty($args['attribution'])) {
            $builder->where_equals('attribution', sanitize_key((string) $args['attribution']));
        }

        if (!empty($args['search'])) {
            $builder->where_like_any(array('title', 'description'), (string) $args['search']);
        }

        $allowedOrderBy = array('display_order', 'title', 'xp', 'coins', 'created_at', 'updated_at', 'status', 'id', 'category');
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
            'category' => '',
            'attribution' => '',
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

        if (!empty($args['category'])) {
            $builder->where_equals('category', sanitize_key((string) $args['category']));
        }

        if (!empty($args['attribution'])) {
            $builder->where_equals('attribution', sanitize_key((string) $args['attribution']));
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
            return new WP_Error('mj_action_type_create_failed', __('Impossible de créer le type d\'action.', 'mj-member'));
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
            return new WP_Error('mj_action_type_invalid_id', __('Identifiant de type d\'action invalide.', 'mj-member'));
        }

        $payload = self::sanitize_payload($data, true);
        if (is_wp_error($payload)) {
            return $payload;
        }

        if (empty($payload)) {
            return true;
        }

        $payload['updated_at'] = current_time('mysql');

        $updated = $wpdb->update($table, $payload, array('id' => $id), self::get_format($payload), array('%d'));
        if ($updated === false) {
            return new WP_Error('mj_action_type_update_failed', __('Impossible de mettre à jour le type d\'action.', 'mj-member'));
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
            return new WP_Error('mj_action_type_invalid_id', __('Identifiant de type d\'action invalide.', 'mj-member'));
        }

        $deleted = $wpdb->delete($table, array('id' => $id), array('%d'));
        if ($deleted === false) {
            return new WP_Error('mj_action_type_delete_failed', __('Impossible de supprimer le type d\'action.', 'mj-member'));
        }

        return true;
    }

    /**
     * @param string $candidate
     * @return string
     */
    private static function normalize_status($candidate): string
    {
        $candidate = strtolower(trim((string) $candidate));
        $valid = self::statuses();
        return in_array($candidate, $valid, true) ? $candidate : '';
    }

    /**
     * @param mixed $data
     * @param bool $isUpdate
     * @return array<string,mixed>|WP_Error
     */
    private static function sanitize_payload($data, bool $isUpdate)
    {
        $payload = array();

        if (!is_array($data) && !is_object($data)) {
            return $isUpdate ? $payload : new WP_Error('mj_action_type_invalid_data', __('Données invalides.', 'mj-member'));
        }

        $data = (array) $data;

        if (isset($data['slug'])) {
            $slug = sanitize_title((string) $data['slug']);
            if ($slug !== '') {
                $payload['slug'] = $slug;
            }
        }

        if (isset($data['title'])) {
            $title = sanitize_text_field((string) $data['title']);
            if (!$isUpdate && $title === '') {
                return new WP_Error('mj_action_type_title_required', __('Le titre est requis.', 'mj-member'));
            }
            if ($title !== '') {
                $payload['title'] = $title;
            }
        } elseif (!$isUpdate) {
            return new WP_Error('mj_action_type_title_required', __('Le titre est requis.', 'mj-member'));
        }

        if (isset($data['description'])) {
            $payload['description'] = wp_kses_post((string) $data['description']);
        }

        if (isset($data['emoji'])) {
            $payload['emoji'] = sanitize_text_field((string) $data['emoji']);
        }

        if (isset($data['category'])) {
            $category = sanitize_key((string) $data['category']);
            $valid_categories = array_keys(self::get_category_labels());
            if (in_array($category, $valid_categories, true)) {
                $payload['category'] = $category;
            }
        }

        if (isset($data['attribution'])) {
            $attribution = sanitize_key((string) $data['attribution']);
            if ($attribution === self::ATTRIBUTION_AUTO || $attribution === self::ATTRIBUTION_MANUAL) {
                $payload['attribution'] = $attribution;
            }
        }

        if (isset($data['auto_hook'])) {
            $payload['auto_hook'] = sanitize_key((string) $data['auto_hook']);
        }

        if (isset($data['xp'])) {
            $payload['xp'] = max(0, (int) $data['xp']);
        }

        if (isset($data['coins'])) {
            $payload['coins'] = max(0, min(1, (int) $data['coins']));
        }

        if (isset($data['display_order'])) {
            $payload['display_order'] = (int) $data['display_order'];
        }

        if (isset($data['status'])) {
            $status = self::normalize_status($data['status']);
            if ($status !== '') {
                $payload['status'] = $status;
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
        $formats = array();
        foreach ($payload as $key => $value) {
            if (in_array($key, array('xp', 'coins', 'display_order', 'id'), true)) {
                $formats[] = '%d';
            } else {
                $formats[] = '%s';
            }
        }
        return $formats;
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private static function format_row(array $row): array
    {
        return array(
            'id' => isset($row['id']) ? (int) $row['id'] : 0,
            'slug' => isset($row['slug']) ? (string) $row['slug'] : '',
            'title' => isset($row['title']) ? (string) $row['title'] : '',
            'description' => isset($row['description']) ? (string) $row['description'] : '',
            'emoji' => isset($row['emoji']) ? (string) $row['emoji'] : '',
            'category' => isset($row['category']) ? (string) $row['category'] : '',
            'categoryLabel' => isset($row['category']) ? (self::get_category_labels()[$row['category']] ?? '') : '',
            'attribution' => isset($row['attribution']) ? (string) $row['attribution'] : self::ATTRIBUTION_MANUAL,
            'attributionLabel' => isset($row['attribution']) ? (self::get_attribution_labels()[$row['attribution']] ?? '') : '',
            'autoHook' => isset($row['auto_hook']) ? (string) $row['auto_hook'] : '',
            'xp' => isset($row['xp']) ? (int) $row['xp'] : 0,
            'coins' => isset($row['coins']) ? (int) $row['coins'] : 0,
            'displayOrder' => isset($row['display_order']) ? (int) $row['display_order'] : 0,
            'status' => isset($row['status']) ? (string) $row['status'] : self::STATUS_ACTIVE,
            'statusLabel' => isset($row['status']) ? (self::get_status_labels()[$row['status']] ?? '') : '',
            'createdAt' => isset($row['created_at']) ? (string) $row['created_at'] : '',
            'updatedAt' => isset($row['updated_at']) ? (string) $row['updated_at'] : '',
        );
    }
}
