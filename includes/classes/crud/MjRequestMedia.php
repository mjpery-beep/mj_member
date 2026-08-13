<?php

namespace Mj\Member\Classes\Crud;

use Mj\Member\Classes\MjTools;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class MjRequestMedia extends MjTools implements CrudRepositoryInterface
{
    private const TABLE = 'mj_request_media';

    private static function table_name(): string
    {
        if (function_exists('mj_member_get_request_media_table_name')) {
            return mj_member_get_request_media_table_name();
        }

        return self::getTableName(self::TABLE);
    }

    public static function get_all(array $args = array())
    {
        global $wpdb;
        $table = self::table_name();

        $requestId = isset($args['request_id']) ? (int) $args['request_id'] : 0;
        if ($requestId <= 0) {
            return array();
        }

        $sql = $wpdb->prepare("SELECT * FROM {$table} WHERE request_id = %d ORDER BY id ASC", $requestId);
        $rows = $wpdb->get_results($sql);
        return is_array($rows) ? $rows : array();
    }

    public static function count(array $args = array())
    {
        global $wpdb;
        $table = self::table_name();

        $requestId = isset($args['request_id']) ? (int) $args['request_id'] : 0;
        if ($requestId <= 0) {
            return 0;
        }

        return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE request_id = %d", $requestId));
    }

    public static function create($data)
    {
        global $wpdb;
        $table = self::table_name();

        $requestId = isset($data['request_id']) ? (int) $data['request_id'] : 0;
        $attachmentId = isset($data['attachment_id']) ? (int) $data['attachment_id'] : 0;
        if ($requestId <= 0 || $attachmentId <= 0) {
            return new WP_Error('invalid_media_payload', __('Média invalide.', 'mj-member'));
        }

        $ok = $wpdb->insert(
            $table,
            array(
                'request_id' => $requestId,
                'attachment_id' => $attachmentId,
                'created_at' => current_time('mysql'),
            ),
            array('%d', '%d', '%s')
        );

        if ($ok === false) {
            return new WP_Error('db_insert_failed', __('Impossible d\'ajouter le média.', 'mj-member'));
        }

        return (int) $wpdb->insert_id;
    }

    public static function update($id, $data)
    {
        return true;
    }

    public static function delete($id)
    {
        global $wpdb;
        $table = self::table_name();
        $ok = $wpdb->delete($table, array('id' => (int) $id), array('%d'));
        if ($ok === false) {
            return new WP_Error('db_delete_failed', __('Impossible de supprimer le média.', 'mj-member'));
        }

        return true;
    }
}
