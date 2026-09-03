<?php

namespace Mj\Member\Classes\Crud;

use Mj\Member\Classes\MjTools;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class MjNoteMedia extends MjTools implements CrudRepositoryInterface
{
    private const TABLE = 'mj_note_media';

    private static function table_name(): string
    {
        if (function_exists('mj_member_get_note_media_table_name')) {
            return mj_member_get_note_media_table_name();
        }

        return self::getTableName(self::TABLE);
    }

    public static function get_all(array $args = array())
    {
        global $wpdb;
        $table = self::table_name();

        $noteId = isset($args['note_id']) ? (int) $args['note_id'] : 0;
        if ($noteId <= 0) {
            return array();
        }

        $sql = $wpdb->prepare("SELECT * FROM {$table} WHERE note_id = %d ORDER BY sort_order ASC, id ASC", $noteId);
        $rows = $wpdb->get_results($sql);
        return is_array($rows) ? $rows : array();
    }

    public static function count(array $args = array())
    {
        global $wpdb;
        $table = self::table_name();

        $noteId = isset($args['note_id']) ? (int) $args['note_id'] : 0;
        if ($noteId <= 0) {
            return 0;
        }

        return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE note_id = %d", $noteId));
    }

    public static function create($data)
    {
        global $wpdb;
        $table = self::table_name();

        $noteId = isset($data['note_id']) ? (int) $data['note_id'] : 0;
        $attachmentId = isset($data['attachment_id']) ? (int) $data['attachment_id'] : 0;
        if ($noteId <= 0 || $attachmentId <= 0) {
            return new WP_Error('invalid_media_payload', __('Média invalide.', 'mj-member'));
        }

        $sortOrder = isset($data['sort_order']) ? (int) $data['sort_order'] : 0;

        $ok = $wpdb->insert(
            $table,
            array(
                'note_id' => $noteId,
                'attachment_id' => $attachmentId,
                'sort_order' => $sortOrder,
                'created_at' => current_time('mysql'),
            ),
            array('%d', '%d', '%d', '%s')
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

    /**
     * @return true|WP_Error
     */
    public static function delete_for_note(int $noteId)
    {
        global $wpdb;
        $table = self::table_name();
        $ok = $wpdb->delete($table, array('note_id' => $noteId), array('%d'));
        if ($ok === false) {
            return new WP_Error('db_delete_failed', __('Impossible de supprimer les médias.', 'mj-member'));
        }

        return true;
    }
}
