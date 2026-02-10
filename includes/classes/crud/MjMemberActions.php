<?php

namespace Mj\Member\Classes\Crud;

use Mj\Member\Classes\MjTools;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * CRUD pour la gestion des actions attribuées aux membres.
 */
final class MjMemberActions extends MjTools implements CrudRepositoryInterface
{
    private const TABLE = 'mj_member_actions';

    /**
     * @return string
     */
    public static function table_name(): string
    {
        if (function_exists('mj_member_get_member_actions_table_name')) {
            return mj_member_get_member_actions_table_name();
        }

        return self::getTableName(self::TABLE);
    }

    /**
     * Obtenir toutes les actions attribuées à un membre avec détails.
     *
     * @param int $member_id
     * @param array<string,mixed> $args
     * @return array<int,array<string,mixed>>
     */
    public static function get_for_member(int $member_id, array $args = array()): array
    {
        global $wpdb;
        $table = self::table_name();
        $action_types_table = MjActionTypes::table_name();

        if ($member_id <= 0) {
            return array();
        }

        $defaults = array(
            'category' => '',
            'orderby' => 'created_at',
            'order' => 'DESC',
            'limit' => 0,
            'offset' => 0,
        );
        $args = wp_parse_args($args, $defaults);

        $where = "ma.member_id = %d AND at.status = 'active'";
        $params = array($member_id);

        if (!empty($args['category'])) {
            $where .= " AND at.category = %s";
            $params[] = sanitize_key((string) $args['category']);
        }

        $allowedOrderBy = array('created_at', 'at.title', 'at.display_order', 'at.xp', 'at.coins');
        $orderby = sanitize_key((string) $args['orderby']);
        if (!in_array($orderby, $allowedOrderBy, true)) {
            $orderby = 'created_at';
        }
        $order = strtoupper((string) $args['order']) === 'ASC' ? 'ASC' : 'DESC';

        $sql = "SELECT ma.*, at.slug, at.title, at.description, at.category, at.attribution, at.xp, at.coins, at.display_order
                FROM {$table} ma
                INNER JOIN {$action_types_table} at ON ma.action_type_id = at.id
                WHERE {$where}
                ORDER BY {$orderby} {$order}";

        if ((int) $args['limit'] > 0) {
            $sql .= sprintf(" LIMIT %d OFFSET %d", (int) $args['limit'], max(0, (int) $args['offset']));
        }

        $results = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
        if (empty($results)) {
            return array();
        }

        return array_map(array(__CLASS__, 'format_row'), $results);
    }

    /**
     * Compter les actions par type pour un membre.
     *
     * @param int $member_id
     * @return array<int,array<string,mixed>> Liste avec action_type_id, count, et détails
     */
    public static function get_counts_for_member(int $member_id): array
    {
        global $wpdb;
        $table = self::table_name();
        $action_types_table = MjActionTypes::table_name();

        if ($member_id <= 0) {
            return array();
        }

        $sql = $wpdb->prepare(
            "SELECT at.*, COUNT(ma.id) as action_count
             FROM {$action_types_table} at
             LEFT JOIN {$table} ma ON at.id = ma.action_type_id AND ma.member_id = %d
             WHERE at.status = 'active'
             GROUP BY at.id
             ORDER BY at.category ASC, at.display_order ASC",
            $member_id
        );

        $results = $wpdb->get_results($sql, ARRAY_A);
        if (empty($results)) {
            return array();
        }

        return array_map(function ($row) {
            return array(
                'id' => isset($row['id']) ? (int) $row['id'] : 0,
                'slug' => isset($row['slug']) ? (string) $row['slug'] : '',
                'title' => isset($row['title']) ? (string) $row['title'] : '',
                'description' => isset($row['description']) ? (string) $row['description'] : '',
                'emoji' => isset($row['emoji']) ? (string) $row['emoji'] : '',
                'category' => isset($row['category']) ? (string) $row['category'] : '',
                'categoryLabel' => isset($row['category']) ? (MjActionTypes::get_category_labels()[$row['category']] ?? '') : '',
                'attribution' => isset($row['attribution']) ? (string) $row['attribution'] : MjActionTypes::ATTRIBUTION_MANUAL,
                'attributionLabel' => isset($row['attribution']) ? (MjActionTypes::get_attribution_labels()[$row['attribution']] ?? '') : '',
                'xp' => isset($row['xp']) ? (int) $row['xp'] : 0,
                'coins' => isset($row['coins']) ? (int) $row['coins'] : 0,
                'displayOrder' => isset($row['display_order']) ? (int) $row['display_order'] : 0,
                'count' => isset($row['action_count']) ? (int) $row['action_count'] : 0,
            );
        }, $results);
    }

    /**
     * Obtenir toutes les actions (pour tous les membres).
     *
     * @param array<string,mixed> $args
     * @return array<int,array<string,mixed>>
     */
    public static function get_all(array $args = array())
    {
        global $wpdb;
        $table = self::table_name();
        $action_types_table = MjActionTypes::table_name();

        $defaults = array(
            'member_id' => 0,
            'action_type_id' => 0,
            'awarded_by' => 0,
            'category' => '',
            'orderby' => 'created_at',
            'order' => 'DESC',
            'limit' => 50,
            'offset' => 0,
        );
        $args = wp_parse_args($args, $defaults);

        $where = array("at.status = 'active'");
        $params = array();

        if ((int) $args['member_id'] > 0) {
            $where[] = "ma.member_id = %d";
            $params[] = (int) $args['member_id'];
        }

        if ((int) $args['action_type_id'] > 0) {
            $where[] = "ma.action_type_id = %d";
            $params[] = (int) $args['action_type_id'];
        }

        if ((int) $args['awarded_by'] > 0) {
            $where[] = "ma.awarded_by = %d";
            $params[] = (int) $args['awarded_by'];
        }

        if (!empty($args['category'])) {
            $where[] = "at.category = %s";
            $params[] = sanitize_key((string) $args['category']);
        }

        $where_clause = implode(' AND ', $where);

        $allowedOrderBy = array('created_at', 'at.title', 'at.display_order');
        $orderby = sanitize_key((string) $args['orderby']);
        if (!in_array($orderby, $allowedOrderBy, true)) {
            $orderby = 'created_at';
        }
        $order = strtoupper((string) $args['order']) === 'ASC' ? 'ASC' : 'DESC';

        $sql = "SELECT ma.*, at.slug, at.title, at.description, at.category, at.attribution, at.xp, at.coins, at.display_order
                FROM {$table} ma
                INNER JOIN {$action_types_table} at ON ma.action_type_id = at.id
                WHERE {$where_clause}
                ORDER BY {$orderby} {$order}
                LIMIT %d OFFSET %d";

        $params[] = (int) $args['limit'];
        $params[] = max(0, (int) $args['offset']);

        $results = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
        if (empty($results)) {
            return array();
        }

        return array_map(array(__CLASS__, 'format_row'), $results);
    }

    /**
     * Compter les actions.
     *
     * @param array<string,mixed> $args
     * @return int
     */
    public static function count(array $args = array())
    {
        global $wpdb;
        $table = self::table_name();
        $action_types_table = MjActionTypes::table_name();

        $defaults = array(
            'member_id' => 0,
            'action_type_id' => 0,
            'awarded_by' => 0,
            'category' => '',
        );
        $args = wp_parse_args($args, $defaults);

        $where = array("at.status = 'active'");
        $params = array();

        if ((int) $args['member_id'] > 0) {
            $where[] = "ma.member_id = %d";
            $params[] = (int) $args['member_id'];
        }

        if ((int) $args['action_type_id'] > 0) {
            $where[] = "ma.action_type_id = %d";
            $params[] = (int) $args['action_type_id'];
        }

        if ((int) $args['awarded_by'] > 0) {
            $where[] = "ma.awarded_by = %d";
            $params[] = (int) $args['awarded_by'];
        }

        if (!empty($args['category'])) {
            $where[] = "at.category = %s";
            $params[] = sanitize_key((string) $args['category']);
        }

        $where_clause = implode(' AND ', $where);

        $sql = "SELECT COUNT(ma.id) FROM {$table} ma
                INNER JOIN {$action_types_table} at ON ma.action_type_id = at.id
                WHERE {$where_clause}";

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        return (int) $wpdb->get_var($sql);
    }

    /**
     * Compter le nombre d'occurrences d'une action pour un membre.
     *
     * @param int $member_id
     * @param int $action_type_id
     * @return int
     */
    public static function count_for_member_action(int $member_id, int $action_type_id): int
    {
        global $wpdb;
        $table = self::table_name();

        if ($member_id <= 0 || $action_type_id <= 0) {
            return 0;
        }

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE member_id = %d AND action_type_id = %d",
            $member_id,
            $action_type_id
        ));
    }

    /**
     * @param int $id
     * @return array<string,mixed>|null
     */
    public static function get(int $id): ?array
    {
        global $wpdb;
        $table = self::table_name();
        $action_types_table = MjActionTypes::table_name();

        if ($id <= 0) {
            return null;
        }

        $sql = $wpdb->prepare(
            "SELECT ma.*, at.slug, at.title, at.description, at.category, at.attribution, at.xp, at.coins, at.display_order
             FROM {$table} ma
             INNER JOIN {$action_types_table} at ON ma.action_type_id = at.id
             WHERE ma.id = %d",
            $id
        );

        $row = $wpdb->get_row($sql, ARRAY_A);
        if (!$row) {
            return null;
        }

        return self::format_row($row);
    }

    /**
     * Attribuer une action à un membre.
     *
     * @param int $member_id
     * @param int $action_type_id
     * @param int $awarded_by ID du membre qui attribue (0 si automatique)
     * @param string $notes Notes optionnelles
     * @return int|WP_Error L'ID de l'action créée
     */
    public static function award(int $member_id, int $action_type_id, int $awarded_by = 0, string $notes = '')
    {
        global $wpdb;
        $table = self::table_name();

        if ($member_id <= 0) {
            return new WP_Error('mj_member_action_invalid_member', __('Membre invalide.', 'mj-member'));
        }

        if ($action_type_id <= 0) {
            return new WP_Error('mj_member_action_invalid_type', __('Type d\'action invalide.', 'mj-member'));
        }

        // Vérifier que le type d'action existe
        $action_type = MjActionTypes::get($action_type_id);
        if (!$action_type) {
            return new WP_Error('mj_member_action_type_not_found', __('Type d\'action non trouvé.', 'mj-member'));
        }

        $payload = array(
            'member_id' => $member_id,
            'action_type_id' => $action_type_id,
            'awarded_by' => $awarded_by > 0 ? $awarded_by : null,
            'notes' => sanitize_textarea_field($notes),
            'created_at' => current_time('mysql'),
        );

        $formats = array('%d', '%d', $awarded_by > 0 ? '%d' : null, '%s', '%s');
        $formats = array_filter($formats);

        $inserted = $wpdb->insert($table, $payload, $formats);
        if ($inserted === false) {
            return new WP_Error('mj_member_action_create_failed', __('Impossible d\'attribuer l\'action.', 'mj-member'));
        }

        $action_id = (int) $wpdb->insert_id;

        // Ajouter XP et coins au membre
        if ($action_type['xp'] > 0 || $action_type['coins'] > 0) {
            self::apply_rewards($member_id, $action_type['xp'], $action_type['coins']);
        }

        // Notifier le membre
        self::notify_member($member_id, $action_type);

        return $action_id;
    }

    /**
     * Alias pour create.
     *
     * @param mixed $data
     * @return int|WP_Error
     */
    public static function create($data)
    {
        if (!is_array($data)) {
            return new WP_Error('mj_member_action_invalid_data', __('Données invalides.', 'mj-member'));
        }

        $member_id = isset($data['member_id']) ? (int) $data['member_id'] : 0;
        $action_type_id = isset($data['action_type_id']) ? (int) $data['action_type_id'] : 0;
        $awarded_by = isset($data['awarded_by']) ? (int) $data['awarded_by'] : 0;
        $notes = isset($data['notes']) ? (string) $data['notes'] : '';

        return self::award($member_id, $action_type_id, $awarded_by, $notes);
    }

    /**
     * Mise à jour - non supportée pour les actions (elles ne sont pas modifiables).
     *
     * @param int $id
     * @param mixed $data
     * @return WP_Error
     */
    public static function update($id, $data)
    {
        return new WP_Error('mj_member_action_update_not_supported', __('Les actions ne peuvent pas être modifiées.', 'mj-member'));
    }

    /**
     * Supprimer une action attribuée.
     *
     * @param int $id
     * @return true|WP_Error
     */
    public static function delete($id)
    {
        global $wpdb;
        $table = self::table_name();
        $id = (int) $id;

        if ($id <= 0) {
            return new WP_Error('mj_member_action_invalid_id', __('Identifiant d\'action invalide.', 'mj-member'));
        }

        // Récupérer l'action pour retirer les récompenses
        $action = self::get($id);
        if ($action) {
            // Retirer XP et coins (valeurs négatives)
            self::apply_rewards($action['memberId'], -$action['xp'], -$action['coins']);
        }

        $deleted = $wpdb->delete($table, array('id' => $id), array('%d'));
        if ($deleted === false) {
            return new WP_Error('mj_member_action_delete_failed', __('Impossible de supprimer l\'action.', 'mj-member'));
        }

        return true;
    }

    /**
     * Appliquer les récompenses (XP et coins) à un membre.
     *
     * @param int $member_id
     * @param int $xp
     * @param int $coins
     */
    private static function apply_rewards(int $member_id, int $xp, int $coins): void
    {
        if ($member_id <= 0) {
            return;
        }

        if ($xp !== 0 && class_exists('Mj\Member\Classes\Crud\MjMemberXp')) {
            if ($xp > 0) {
                MjMemberXp::add($member_id, $xp, 'action');
            } else {
                MjMemberXp::remove($member_id, abs($xp), 'action_revoked');
            }
        }

        if ($coins !== 0 && class_exists('Mj\Member\Classes\Crud\MjMemberCoins')) {
            if ($coins > 0) {
                MjMemberCoins::add($member_id, $coins, 'action');
            } else {
                MjMemberCoins::remove($member_id, abs($coins), 'action_revoked');
            }
        }
    }

    /**
     * Notifier le membre qu'une action lui a été attribuée.
     *
     * @param int $member_id
     * @param array<string,mixed> $action_type
     * @return void
     */
    private static function notify_member(int $member_id, array $action_type): void
    {
        if ($member_id <= 0) {
            return;
        }

        if (!class_exists('Mj\Member\Classes\MjNotificationManager')) {
            return;
        }

        $rewards = array();
        if (!empty($action_type['xp']) && (int) $action_type['xp'] > 0) {
            $rewards[] = '+' . (int) $action_type['xp'] . ' XP';
        }
        if (!empty($action_type['coins']) && (int) $action_type['coins'] > 0) {
            $rewards[] = '+' . (int) $action_type['coins'] . ' 🪙';
        }
        
        $reward_text = !empty($rewards) ? ' (' . implode(', ', $rewards) . ')' : '';
        $action_title = $action_type['title'] ?? __('Action', 'mj-member');

        \Mj\Member\Classes\MjNotificationManager::record(
            array(
                'type' => 'action_awarded',
                'title' => sprintf(
                    /* translators: %s: action title */
                    __('Action « %s »', 'mj-member'),
                    esc_html($action_title)
                ),
                'excerpt' => sprintf(
                    /* translators: %1$s: action title, %2$s: rewards text */
                    __('Félicitations ! Tu as reçu l\'action « %1$s »%2$s', 'mj-member'),
                    esc_html($action_title),
                    $reward_text
                ),
            ),
            array($member_id)
        );
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private static function format_row(array $row): array
    {
        return array(
            'id' => isset($row['id']) ? (int) $row['id'] : 0,
            'memberId' => isset($row['member_id']) ? (int) $row['member_id'] : 0,
            'actionTypeId' => isset($row['action_type_id']) ? (int) $row['action_type_id'] : 0,
            'awardedBy' => isset($row['awarded_by']) ? (int) $row['awarded_by'] : 0,
            'notes' => isset($row['notes']) ? (string) $row['notes'] : '',
            'createdAt' => isset($row['created_at']) ? (string) $row['created_at'] : '',
            // Champs joints depuis action_types
            'slug' => isset($row['slug']) ? (string) $row['slug'] : '',
            'title' => isset($row['title']) ? (string) $row['title'] : '',
            'description' => isset($row['description']) ? (string) $row['description'] : '',
            'category' => isset($row['category']) ? (string) $row['category'] : '',
            'categoryLabel' => isset($row['category']) ? (MjActionTypes::get_category_labels()[$row['category']] ?? '') : '',
            'attribution' => isset($row['attribution']) ? (string) $row['attribution'] : MjActionTypes::ATTRIBUTION_MANUAL,
            'xp' => isset($row['xp']) ? (int) $row['xp'] : 0,
            'coins' => isset($row['coins']) ? (int) $row['coins'] : 0,
            'displayOrder' => isset($row['display_order']) ? (int) $row['display_order'] : 0,
        );
    }
}
