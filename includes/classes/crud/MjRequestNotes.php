<?php

namespace Mj\Member\Classes\Crud;

use Mj\Member\Classes\MjTools;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class MjRequestNotes extends MjTools implements CrudRepositoryInterface
{
    private const TABLE = 'mj_request_notes';

    private static function table_name(): string
    {
        if (function_exists('mj_member_get_request_notes_table_name')) {
            return mj_member_get_request_notes_table_name();
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

        $sql = $wpdb->prepare("SELECT * FROM {$table} WHERE request_id = %d ORDER BY created_at ASC", $requestId);
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
        $authorMemberId = isset($data['author_member_id']) ? (int) $data['author_member_id'] : 0;
        $content = isset($data['content']) ? sanitize_textarea_field((string) $data['content']) : '';

        if ($requestId <= 0 || $authorMemberId <= 0 || $content === '') {
            return new WP_Error('invalid_note_payload', __('Note invalide.', 'mj-member'));
        }

        $ok = $wpdb->insert(
            $table,
            array(
                'request_id' => $requestId,
                'author_member_id' => $authorMemberId,
                'content' => $content,
                'created_at' => current_time('mysql'),
            ),
            array('%d', '%d', '%s', '%s')
        );

        if ($ok === false) {
            return new WP_Error('db_insert_failed', __('Impossible d\'ajouter la note.', 'mj-member'));
        }

        return (int) $wpdb->insert_id;
    }

    public static function update($id, $data)
    {
        global $wpdb;
        $table = self::table_name();

        $content = isset($data['content']) ? sanitize_textarea_field((string) $data['content']) : '';
        if ($content === '') {
            return new WP_Error('missing_content', __('Contenu de note invalide.', 'mj-member'));
        }

        $ok = $wpdb->update($table, array('content' => $content), array('id' => (int) $id), array('%s'), array('%d'));
        if ($ok === false) {
            return new WP_Error('db_update_failed', __('Impossible de mettre à jour la note.', 'mj-member'));
        }

        return true;
    }

    public static function delete($id)
    {
        global $wpdb;
        $table = self::table_name();
        $ok = $wpdb->delete($table, array('id' => (int) $id), array('%d'));
        if ($ok === false) {
            return new WP_Error('db_delete_failed', __('Impossible de supprimer la note.', 'mj-member'));
        }

        return true;
    }
}
