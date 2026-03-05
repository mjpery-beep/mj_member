<?php

namespace Mj\Member\Classes\Crud;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * CRUD pour la table mj_push_subscriptions.
 * Gère les abonnements Web Push (Service Worker) des utilisateurs.
 */
class MjPushSubscriptions implements CrudRepositoryInterface
{
    /**
     * @return string
     */
    public static function get_table_name(): string
    {
        return function_exists('mj_member_get_push_subscriptions_table_name')
            ? mj_member_get_push_subscriptions_table_name()
            : '';
    }

    /**
     * @return array<string,mixed>
     */
    public static function get_default_values(): array
    {
        return array(
            'member_id'        => null,
            'user_id'          => null,
            'endpoint'         => '',
            'public_key'       => '',
            'auth_token'       => '',
            'content_encoding' => 'aesgcm',
            'user_agent'       => null,
            'created_at'       => current_time('mysql', 1),
            'expires_at'       => null,
        );
    }

    /**
     * @param array<string,mixed> $args
     * @return array<int,object>
     */
    public static function get_all(array $args = array()): array
    {
        $table = self::get_table_name();
        if ($table === '') {
            return array();
        }

        global $wpdb;

        $defaults = array(
            'member_id' => null,
            'user_id'   => null,
            'limit'     => 200,
            'offset'    => 0,
        );
        $args = wp_parse_args($args, $defaults);

        $where = array('1=1');
        $params = array();

        if ($args['member_id'] !== null) {
            $where[] = 'member_id = %d';
            $params[] = (int) $args['member_id'];
        }

        if ($args['user_id'] !== null) {
            $where[] = 'user_id = %d';
            $params[] = (int) $args['user_id'];
        }

        $limit = max(1, min(1000, (int) $args['limit']));
        $offset = max(0, (int) $args['offset']);

        $sql = sprintf(
            "SELECT * FROM %s WHERE %s ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $table,
            implode(' AND ', $where),
            $limit,
            $offset
        );

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        $rows = $wpdb->get_results($sql);
        return is_array($rows) ? $rows : array();
    }

    /**
     * @param array<string,mixed> $args
     * @return int
     */
    public static function count(array $args = array()): int
    {
        $table = self::get_table_name();
        if ($table === '') {
            return 0;
        }

        global $wpdb;

        $where = array('1=1');
        $params = array();

        if (isset($args['member_id'])) {
            $where[] = 'member_id = %d';
            $params[] = (int) $args['member_id'];
        }

        if (isset($args['user_id'])) {
            $where[] = 'user_id = %d';
            $params[] = (int) $args['user_id'];
        }

        $sql = sprintf("SELECT COUNT(*) FROM %s WHERE %s", $table, implode(' AND ', $where));

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
        $table = self::get_table_name();
        if ($table === '') {
            return new WP_Error('mj_push_sub_no_table', __('Table push_subscriptions introuvable.', 'mj-member'));
        }

        if (!is_array($data)) {
            return new WP_Error('mj_push_sub_invalid_data', __('Données invalides.', 'mj-member'));
        }

        $endpoint = isset($data['endpoint']) ? trim((string) $data['endpoint']) : '';
        if ($endpoint === '') {
            return new WP_Error('mj_push_sub_missing_endpoint', __('Endpoint manquant.', 'mj-member'));
        }

        global $wpdb;

        $defaults = self::get_default_values();
        $row = array_merge($defaults, array_intersect_key($data, $defaults));
        $row['endpoint'] = $endpoint;

        $formats = array(
            'member_id'        => $row['member_id'] !== null ? '%d' : null,
            'user_id'          => $row['user_id'] !== null ? '%d' : null,
            'endpoint'         => '%s',
            'public_key'       => '%s',
            'auth_token'       => '%s',
            'content_encoding' => '%s',
            'user_agent'       => '%s',
            'created_at'       => '%s',
            'expires_at'       => $row['expires_at'] !== null ? '%s' : null,
        );

        $insert_data = array();
        $insert_format = array();

        foreach ($formats as $key => $fmt) {
            if ($fmt === null) {
                continue;
            }
            $insert_data[$key] = $row[$key];
            $insert_format[] = $fmt;
        }

        $result = $wpdb->insert($table, $insert_data, $insert_format);
        if ($result === false) {
            return new WP_Error('mj_push_sub_insert_failed', __('Échec de l\'insertion.', 'mj-member'));
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
        $table = self::get_table_name();
        if ($table === '') {
            return new WP_Error('mj_push_sub_no_table', __('Table introuvable.', 'mj-member'));
        }

        global $wpdb;

        $allowed = array('member_id', 'user_id', 'endpoint', 'public_key', 'auth_token', 'content_encoding', 'user_agent', 'expires_at');
        $update_data = array();
        $update_format = array();

        foreach ($allowed as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }
            $update_data[$key] = $data[$key];
            $update_format[] = in_array($key, array('member_id', 'user_id'), true) ? '%d' : '%s';
        }

        if (empty($update_data)) {
            return true;
        }

        $result = $wpdb->update($table, $update_data, array('id' => (int) $id), $update_format, array('%d'));
        if ($result === false) {
            return new WP_Error('mj_push_sub_update_failed', __('Échec de la mise à jour.', 'mj-member'));
        }

        return true;
    }

    /**
     * @param int $id
     * @return true|WP_Error
     */
    public static function delete($id)
    {
        $table = self::get_table_name();
        if ($table === '') {
            return new WP_Error('mj_push_sub_no_table', __('Table introuvable.', 'mj-member'));
        }

        global $wpdb;
        $result = $wpdb->delete($table, array('id' => (int) $id), array('%d'));
        if ($result === false) {
            return new WP_Error('mj_push_sub_delete_failed', __('Échec de la suppression.', 'mj-member'));
        }

        return true;
    }

    /**
     * Supprime un abonnement par son endpoint.
     *
     * @param string $endpoint
     * @return true|WP_Error
     */
    public static function delete_by_endpoint(string $endpoint)
    {
        $table = self::get_table_name();
        if ($table === '') {
            return new WP_Error('mj_push_sub_no_table', __('Table introuvable.', 'mj-member'));
        }

        global $wpdb;
        $result = $wpdb->delete($table, array('endpoint' => $endpoint), array('%s'));
        if ($result === false) {
            return new WP_Error('mj_push_sub_delete_failed', __('Échec de la suppression.', 'mj-member'));
        }

        return true;
    }

    /**
     * Trouve un abonnement existant par endpoint.
     *
     * @param string $endpoint
     * @return object|null
     */
    public static function find_by_endpoint(string $endpoint): ?object
    {
        $table = self::get_table_name();
        if ($table === '') {
            return null;
        }

        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE endpoint = %s LIMIT 1", $endpoint));
        return $row ?: null;
    }

    /**
     * Récupère tous les abonnements actifs pour un member_id.
     *
     * @param int $member_id
     * @return array<int,object>
     */
    public static function get_for_member(int $member_id): array
    {
        return self::get_all(array('member_id' => $member_id));
    }

    /**
     * Récupère tous les abonnements actifs pour un user_id.
     *
     * @param int $user_id
     * @return array<int,object>
     */
    public static function get_for_user(int $user_id): array
    {
        return self::get_all(array('user_id' => $user_id));
    }

    /**
     * Supprime tous les abonnements d'un utilisateur.
     *
     * @param int|null $member_id
     * @param int|null $user_id
     * @return int Nombre de lignes supprimées
     */
    public static function delete_all_for_user(?int $member_id = null, ?int $user_id = null): int
    {
        $table = self::get_table_name();
        if ($table === '') {
            return 0;
        }

        global $wpdb;

        if ($member_id !== null && $member_id > 0) {
            return (int) $wpdb->delete($table, array('member_id' => $member_id), array('%d'));
        }

        if ($user_id !== null && $user_id > 0) {
            return (int) $wpdb->delete($table, array('user_id' => $user_id), array('%d'));
        }

        return 0;
    }

    /**
     * Supprime tous les anciens abonnements d'un user_id, sauf celui qu'on veut garder.
     *
     * @param int $user_id
     * @param int $keep_id  L'ID de l'abonnement à garder
     * @return int Nombre de lignes supprimées
     */
    public static function delete_stale_for_user(int $user_id, int $keep_id): int
    {
        $table = self::get_table_name();
        if ($table === '' || $user_id <= 0) {
            return 0;
        }

        global $wpdb;
        return (int) $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} WHERE user_id = %d AND id != %d",
            $user_id,
            $keep_id
        ));
    }

    /**
     * Nettoie les abonnements expirés.
     *
     * @return int Nombre de lignes supprimées
     */
    public static function cleanup_expired(): int
    {
        $table = self::get_table_name();
        if ($table === '') {
            return 0;
        }

        global $wpdb;
        $now = current_time('mysql', 1);
        return (int) $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} WHERE expires_at IS NOT NULL AND expires_at < %s",
            $now
        ));
    }
}
