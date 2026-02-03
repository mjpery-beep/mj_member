<?php

namespace Mj\Member\Classes\Crud;

use Mj\Member\Classes\MjTools;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * CRUD pour la gestion des niveaux de progression des membres.
 */
final class MjLevels extends MjTools implements CrudRepositoryInterface
{
    private const TABLE = 'mj_levels';

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
        if (function_exists('mj_member_get_levels_table_name')) {
            return mj_member_get_levels_table_name();
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
            'orderby' => 'level_number',
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
            $builder->where_like_any(array('title', 'description'), (string) $args['search']);
        }

        $allowedOrderBy = array('level_number', 'title', 'xp_threshold', 'xp_reward', 'created_at', 'updated_at', 'status', 'id');
        $orderby = sanitize_key((string) $args['orderby']);
        if (!in_array($orderby, $allowedOrderBy, true)) {
            $orderby = 'level_number';
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
    public static function get(int $id)
    {
        global $wpdb;
        $table = self::table_name();

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $id
        ), ARRAY_A);

        if (empty($row)) {
            return null;
        }

        return self::format_row($row);
    }

    /**
     * Récupère un niveau par son numéro.
     *
     * @param int $level_number
     * @return array<string,mixed>|null
     */
    public static function get_by_number(int $level_number)
    {
        global $wpdb;
        $table = self::table_name();

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE level_number = %d",
            $level_number
        ), ARRAY_A);

        if (empty($row)) {
            return null;
        }

        return self::format_row($row);
    }

    /**
     * Récupère le niveau correspondant à un total d'XP donné.
     *
     * @param int $xp_total
     * @return array<string,mixed>|null
     */
    public static function get_level_for_xp(int $xp_total)
    {
        global $wpdb;
        $table = self::table_name();

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE status = 'active' AND xp_threshold <= %d ORDER BY xp_threshold DESC LIMIT 1",
            $xp_total
        ), ARRAY_A);

        if (empty($row)) {
            return null;
        }

        return self::format_row($row);
    }

    /**
     * Récupère le niveau suivant à atteindre pour un total d'XP donné.
     *
     * @param int $xp_total
     * @return array<string,mixed>|null
     */
    public static function get_next_level_for_xp(int $xp_total)
    {
        global $wpdb;
        $table = self::table_name();

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE status = 'active' AND xp_threshold > %d ORDER BY xp_threshold ASC LIMIT 1",
            $xp_total
        ), ARRAY_A);

        if (empty($row)) {
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

        if (!is_array($data)) {
            return new WP_Error('invalid_data', __('Données invalides.', 'mj-member'));
        }

        $required_fields = array('level_number', 'title');
        foreach ($required_fields as $field) {
            if (empty($data[$field]) && $data[$field] !== 0) {
                return new WP_Error('missing_field', sprintf(__('Le champ %s est requis.', 'mj-member'), $field));
            }
        }

        // Vérifier que le numéro de niveau n'existe pas déjà
        $existing = self::get_by_number((int) $data['level_number']);
        if ($existing) {
            return new WP_Error('duplicate_level', __('Un niveau avec ce numéro existe déjà.', 'mj-member'));
        }

        $insert_data = array(
            'level_number' => (int) $data['level_number'],
            'title' => sanitize_text_field($data['title']),
            'description' => isset($data['description']) ? wp_kses_post($data['description']) : null,
            'image_id' => isset($data['image_id']) ? (int) $data['image_id'] : null,
            'xp_reward' => isset($data['xp_reward']) ? (int) $data['xp_reward'] : 0,
            'xp_threshold' => isset($data['xp_threshold']) ? (int) $data['xp_threshold'] : 0,
            'status' => isset($data['status']) ? self::normalize_status($data['status']) : self::STATUS_ACTIVE,
        );

        if (empty($insert_data['status'])) {
            $insert_data['status'] = self::STATUS_ACTIVE;
        }

        $result = $wpdb->insert($table, $insert_data, array('%d', '%s', '%s', '%d', '%d', '%d', '%s'));

        if ($result === false) {
            return new WP_Error('db_error', __('Erreur lors de la création du niveau.', 'mj-member'));
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
        if (!is_array($data)) {
            return new WP_Error('invalid_data', __('Données invalides.', 'mj-member'));
        }

        $existing = self::get($id);
        if (!$existing) {
            return new WP_Error('not_found', __('Niveau non trouvé.', 'mj-member'));
        }

        $update_data = array();
        $formats = array();

        if (isset($data['level_number'])) {
            // Vérifier que le numéro n'est pas déjà utilisé par un autre niveau
            $other = self::get_by_number((int) $data['level_number']);
            if ($other && (int) $other['id'] !== $id) {
                return new WP_Error('duplicate_level', __('Un autre niveau avec ce numéro existe déjà.', 'mj-member'));
            }
            $update_data['level_number'] = (int) $data['level_number'];
            $formats[] = '%d';
        }

        if (isset($data['title'])) {
            $update_data['title'] = sanitize_text_field($data['title']);
            $formats[] = '%s';
        }

        if (array_key_exists('description', $data)) {
            $update_data['description'] = $data['description'] !== null ? wp_kses_post($data['description']) : null;
            $formats[] = '%s';
        }

        if (array_key_exists('image_id', $data)) {
            $update_data['image_id'] = $data['image_id'] !== null ? (int) $data['image_id'] : null;
            $formats[] = '%d';
        }

        if (isset($data['xp_reward'])) {
            $update_data['xp_reward'] = (int) $data['xp_reward'];
            $formats[] = '%d';
        }

        if (isset($data['xp_threshold'])) {
            $update_data['xp_threshold'] = (int) $data['xp_threshold'];
            $formats[] = '%d';
        }

        if (isset($data['status'])) {
            $normalized = self::normalize_status($data['status']);
            if ($normalized !== '') {
                $update_data['status'] = $normalized;
                $formats[] = '%s';
            }
        }

        if (empty($update_data)) {
            return true;
        }

        $result = $wpdb->update($table, $update_data, array('id' => $id), $formats, array('%d'));

        if ($result === false) {
            return new WP_Error('db_error', __('Erreur lors de la mise à jour du niveau.', 'mj-member'));
        }

        return true;
    }

    /**
     * @param int $id
     * @return bool|WP_Error
     */
    public static function delete($id)
    {
        global $wpdb;
        $table = self::table_name();

        $id = (int) $id;
        $existing = self::get($id);
        if (!$existing) {
            return new WP_Error('not_found', __('Niveau non trouvé.', 'mj-member'));
        }

        $result = $wpdb->delete($table, array('id' => $id), array('%d'));

        if ($result === false) {
            return new WP_Error('db_error', __('Erreur lors de la suppression du niveau.', 'mj-member'));
        }

        return true;
    }

    /**
     * @param string $status
     * @return string
     */
    private static function normalize_status(string $status): string
    {
        $status = strtolower(trim($status));
        return in_array($status, self::statuses(), true) ? $status : '';
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private static function format_row(array $row): array
    {
        return array(
            'id' => isset($row['id']) ? (int) $row['id'] : 0,
            'level_number' => isset($row['level_number']) ? (int) $row['level_number'] : 0,
            'title' => isset($row['title']) ? (string) $row['title'] : '',
            'description' => isset($row['description']) ? (string) $row['description'] : '',
            'image_id' => isset($row['image_id']) && $row['image_id'] !== null ? (int) $row['image_id'] : null,
            'image_url' => isset($row['image_id']) && $row['image_id'] ? wp_get_attachment_url((int) $row['image_id']) : null,
            'xp_reward' => isset($row['xp_reward']) ? (int) $row['xp_reward'] : 0,
            'xp_threshold' => isset($row['xp_threshold']) ? (int) $row['xp_threshold'] : 0,
            'status' => isset($row['status']) ? (string) $row['status'] : self::STATUS_ACTIVE,
            'created_at' => isset($row['created_at']) ? (string) $row['created_at'] : '',
            'updated_at' => isset($row['updated_at']) ? (string) $row['updated_at'] : '',
        );
    }

    /**
     * Calcule la progression vers le niveau suivant.
     *
     * @param int $xp_total
     * @return array<string,mixed>
     */
    public static function get_progression(int $xp_total): array
    {
        $current_level = self::get_level_for_xp($xp_total);
        $next_level = self::get_next_level_for_xp($xp_total);

        if (!$current_level) {
            return array(
                'current_level' => null,
                'next_level' => $next_level,
                'xp_current' => $xp_total,
                'xp_for_next' => $next_level ? $next_level['xp_threshold'] : 0,
                'xp_progress' => 0,
                'progress_percent' => 0,
            );
        }

        $xp_in_current_level = $xp_total - $current_level['xp_threshold'];
        $xp_needed_for_next = $next_level ? ($next_level['xp_threshold'] - $current_level['xp_threshold']) : 0;
        $progress_percent = $xp_needed_for_next > 0 ? min(100, round(($xp_in_current_level / $xp_needed_for_next) * 100)) : 100;

        return array(
            'current_level' => $current_level,
            'next_level' => $next_level,
            'xp_current' => $xp_total,
            'xp_for_next' => $next_level ? $next_level['xp_threshold'] : $current_level['xp_threshold'],
            'xp_progress' => $xp_in_current_level,
            'xp_remaining' => $next_level ? max(0, $next_level['xp_threshold'] - $xp_total) : 0,
            'progress_percent' => (int) $progress_percent,
            'is_max_level' => $next_level === null,
        );
    }
}
