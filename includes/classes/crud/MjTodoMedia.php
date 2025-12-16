<?php

namespace Mj\Member\Classes\Crud;

use Mj\Member\Classes\MjTools;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class MjTodoMedia extends MjTools implements CrudRepositoryInterface
{
    private const TABLE = 'mj_todo_media';

    private static function table_name(): string
    {
        if (function_exists('mj_member_get_todo_media_table_name')) {
            return mj_member_get_todo_media_table_name();
        }

        return self::getTableName(self::TABLE);
    }

    /**
     * @param array<string,mixed> $args
     * @return array<int,array<string,mixed>>
     */
    public static function get_all(array $args = array())
    {
        $todoId = isset($args['todo_id']) ? (int) $args['todo_id'] : 0;
        if ($todoId > 0) {
            $map = self::get_for_todo_ids(array($todoId));
            return $map[$todoId] ?? array();
        }

        $todoIds = array();
        if (isset($args['todo_ids']) && is_array($args['todo_ids'])) {
            foreach ($args['todo_ids'] as $candidate) {
                $candidateId = (int) $candidate;
                if ($candidateId > 0) {
                    $todoIds[] = $candidateId;
                }
            }
        }

        if (!empty($todoIds)) {
            $map = self::get_for_todo_ids($todoIds);
            $attachments = array();
            foreach ($todoIds as $id) {
                if (!empty($map[$id])) {
                    $attachments = array_merge($attachments, $map[$id]);
                }
            }
            return $attachments;
        }

        return array();
    }

    /**
     * @param array<string,mixed> $args
     */
    public static function count(array $args = array())
    {
        $todoId = isset($args['todo_id']) ? (int) $args['todo_id'] : 0;
        if ($todoId <= 0) {
            return 0;
        }

        global $wpdb;
        $table = self::table_name();
        if (function_exists('mj_member_table_exists') && !mj_member_table_exists($table)) {
            return 0;
        }

        $sql = $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE todo_id = %d", $todoId);
        $result = $wpdb->get_var($sql);

        return $result ? (int) $result : 0;
    }

    /**
     * @param array<string,mixed>|null $data
     * @return int|WP_Error
     */
    public static function create($data)
    {
        if (!is_array($data)) {
            return new WP_Error('mj_member_todo_media_invalid_payload', __('Format de données invalide pour le média.', 'mj-member'));
        }

        $todoId = isset($data['todo_id']) ? (int) $data['todo_id'] : 0;
        $attachmentId = isset($data['attachment_id']) ? (int) $data['attachment_id'] : 0;
        if ($todoId <= 0 || $attachmentId <= 0) {
            return new WP_Error('mj_member_todo_media_invalid_ids', __('Identifiants de média invalides.', 'mj-member'));
        }

        $memberId = isset($data['member_id']) ? (int) $data['member_id'] : 0;
        $userId = isset($data['wp_user_id']) ? (int) $data['wp_user_id'] : get_current_user_id();

        $result = self::attach_multiple($todoId, array($attachmentId), $memberId, $userId);
        if (is_wp_error($result)) {
            return $result;
        }

        $attachments = $result[$todoId] ?? array();
        foreach ($attachments as $entry) {
            if ((int) ($entry['attachment_id'] ?? 0) === $attachmentId) {
                return isset($entry['id']) ? (int) $entry['id'] : 0;
            }
        }

        return new WP_Error('mj_member_todo_media_attach_failed', __('Impossible d’associer le média à la tâche.', 'mj-member'));
    }

    /**
     * @param int $id
     * @param array<string,mixed>|null $data
     * @return true|WP_Error
     */
    public static function update($id, $data)
    {
        $id = (int) $id;
        if ($id <= 0) {
            return new WP_Error('mj_member_todo_media_invalid_id', __('Identifiant de média invalide.', 'mj-member'));
        }

        return new WP_Error('mj_member_todo_media_update_unsupported', __('La mise à jour de médias n’est pas supportée.', 'mj-member'));
    }

    /**
     * @param int $id
     * @return true|WP_Error
     */
    public static function delete($id)
    {
        $id = (int) $id;
        if ($id <= 0) {
            return new WP_Error('mj_member_todo_media_invalid_id', __('Identifiant de média invalide.', 'mj-member'));
        }

        global $wpdb;
        $table = self::table_name();
        if (function_exists('mj_member_table_exists') && !mj_member_table_exists($table)) {
            return true;
        }

        $deleted = $wpdb->delete($table, array('id' => $id), array('%d'));
        if ($deleted === false) {
            return new WP_Error('mj_member_todo_media_delete_failed', __('Suppression du média impossible.', 'mj-member'));
        }

        return true;
    }

    /**
     * @param int $todoId
     * @param array<int,int> $attachmentIds
     * @return array<int,array<int,array<string,mixed>>>|WP_Error
     */
    public static function attach_multiple(int $todoId, array $attachmentIds, int $memberId = 0, int $userId = 0)
    {
        $todoId = (int) $todoId;
        if ($todoId <= 0) {
            return new WP_Error('mj_member_todo_media_invalid_todo', __('Tâche introuvable pour le média.', 'mj-member'));
        }

        $attachmentIds = self::sanitize_attachment_ids($attachmentIds);
        if (empty($attachmentIds)) {
            return new WP_Error('mj_member_todo_media_invalid_attachment', __('Aucun média valide à associer.', 'mj-member'));
        }

        global $wpdb;
        $table = self::table_name();
        if (function_exists('mj_member_table_exists') && !mj_member_table_exists($table)) {
            return new WP_Error('mj_member_todo_media_table_missing', __('Table des médias introuvable.', 'mj-member'));
        }

        $memberId = max(0, $memberId);
        $userId = max(0, $userId);
        $now = current_time('mysql');

        foreach ($attachmentIds as $attachmentId) {
            $wpdb->query(
                $wpdb->prepare(
                    "INSERT INTO {$table} (todo_id, attachment_id, member_id, wp_user_id, created_at, updated_at)
                    VALUES (%d, %d, %d, %d, %s, %s)
                    ON DUPLICATE KEY UPDATE member_id = VALUES(member_id), wp_user_id = VALUES(wp_user_id), updated_at = VALUES(updated_at)",
                    $todoId,
                    $attachmentId,
                    $memberId,
                    $userId,
                    $now,
                    $now
                )
            );
        }

        return self::get_for_todo_ids(array($todoId));
    }

    /**
     * @return true|WP_Error
     */
    public static function detach(int $todoId, int $attachmentId)
    {
        $todoId = (int) $todoId;
        $attachmentId = (int) $attachmentId;
        if ($todoId <= 0 || $attachmentId <= 0) {
            return new WP_Error('mj_member_todo_media_invalid_ids', __('Identifiants de média invalides.', 'mj-member'));
        }

        global $wpdb;
        $table = self::table_name();
        if (function_exists('mj_member_table_exists') && !mj_member_table_exists($table)) {
            return true;
        }

        $deleted = $wpdb->delete(
            $table,
            array('todo_id' => $todoId, 'attachment_id' => $attachmentId),
            array('%d', '%d')
        );

        if ($deleted === false) {
            return new WP_Error('mj_member_todo_media_delete_failed', __('Suppression du média impossible.', 'mj-member'));
        }

        return true;
    }

    public static function delete_for_todo(int $todoId): void
    {
        $todoId = (int) $todoId;
        if ($todoId <= 0) {
            return;
        }

        global $wpdb;
        $table = self::table_name();
        if (function_exists('mj_member_table_exists') && !mj_member_table_exists($table)) {
            return;
        }

        $wpdb->delete($table, array('todo_id' => $todoId), array('%d'));
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function get_for_todo(int $todoId): array
    {
        $todoId = (int) $todoId;
        if ($todoId <= 0) {
            return array();
        }

        $map = self::get_for_todo_ids(array($todoId));
        return $map[$todoId] ?? array();
    }

    /**
     * @param array<int,int> $todoIds
     * @return array<int,array<int,array<string,mixed>>>
     */
    public static function get_for_todo_ids(array $todoIds): array
    {
        $todoIds = array_values(array_filter(array_unique(array_map('intval', $todoIds)), static function ($value) {
            return $value > 0;
        }));

        $map = array();
        foreach ($todoIds as $todoId) {
            $map[$todoId] = array();
        }

        if (empty($todoIds)) {
            return $map;
        }

        global $wpdb;
        $table = self::table_name();
        if (function_exists('mj_member_table_exists') && !mj_member_table_exists($table)) {
            return $map;
        }

        $idsSql = implode(',', array_map('intval', $todoIds));
        $results = $wpdb->get_results(
            "SELECT * FROM {$table} WHERE todo_id IN ({$idsSql}) ORDER BY created_at ASC, id ASC",
            ARRAY_A
        );

        if (empty($results)) {
            return $map;
        }

        $attachmentIds = array();
        foreach ($results as $row) {
            $candidate = isset($row['attachment_id']) ? (int) $row['attachment_id'] : 0;
            if ($candidate > 0) {
                $attachmentIds[$candidate] = $candidate;
            }
        }

        $meta = array();
        if (!empty($attachmentIds)) {
            $meta = self::prepare_attachment_meta(array_values($attachmentIds));
        }

        foreach ($results as $row) {
            $todoId = isset($row['todo_id']) ? (int) $row['todo_id'] : 0;
            if ($todoId <= 0) {
                continue;
            }

            $attachmentId = isset($row['attachment_id']) ? (int) $row['attachment_id'] : 0;
            $entry = array(
                'id' => isset($row['id']) ? (int) $row['id'] : 0,
                'todo_id' => $todoId,
                'attachment_id' => $attachmentId,
                'member_id' => isset($row['member_id']) ? (int) $row['member_id'] : 0,
                'wp_user_id' => isset($row['wp_user_id']) ? (int) $row['wp_user_id'] : 0,
                'created_at' => isset($row['created_at']) ? (string) $row['created_at'] : '',
                'updated_at' => isset($row['updated_at']) ? (string) $row['updated_at'] : '',
            );

            if ($attachmentId > 0 && isset($meta[$attachmentId])) {
                $entry = array_merge($entry, $meta[$attachmentId]);
            }

            if (!isset($map[$todoId])) {
                $map[$todoId] = array();
            }

            $map[$todoId][] = $entry;
        }

        return $map;
    }

    /**
     * @param array<int,int> $attachmentIds
     * @return array<int,int>
     */
    private static function sanitize_attachment_ids(array $attachmentIds): array
    {
        return array_values(array_filter(array_unique(array_map('intval', $attachmentIds)), static function ($value) {
            return $value > 0;
        }));
    }

    /**
     * @param array<int,int> $attachmentIds
     * @return array<int,array<string,string>>
     */
    private static function prepare_attachment_meta(array $attachmentIds): array
    {
        $attachmentIds = self::sanitize_attachment_ids($attachmentIds);
        if (empty($attachmentIds)) {
            return array();
        }

        $meta = array();
        foreach ($attachmentIds as $attachmentId) {
            $post = get_post($attachmentId);
            if (!$post || $post->post_type !== 'attachment') {
                continue;
            }

            $mimeType = get_post_mime_type($attachmentId);
            $type = '';
            if (is_string($mimeType) && $mimeType !== '') {
                $parts = explode('/', $mimeType);
                $type = $parts[0] ?? '';
            }

            $url = wp_get_attachment_url($attachmentId);
            $previewUrl = '';
            if (function_exists('wp_attachment_is_image') && wp_attachment_is_image($attachmentId)) {
                $previewUrl = wp_get_attachment_image_url($attachmentId, 'medium');
                if (!is_string($previewUrl) || $previewUrl === '') {
                    $previewUrl = $url;
                }
            }

            $attachedFile = get_attached_file($attachmentId);
            $filename = '';
            if (is_string($attachedFile) && $attachedFile !== '') {
                $filename = wp_basename($attachedFile);
            }

            $iconUrl = wp_mime_type_icon($attachmentId);

            $meta[$attachmentId] = array(
                'title' => get_the_title($attachmentId),
                'filename' => $filename,
                'url' => is_string($url) ? $url : '',
                'mime_type' => is_string($mimeType) ? $mimeType : '',
                'type' => is_string($type) ? $type : '',
                'icon_url' => is_string($iconUrl) ? $iconUrl : '',
                'preview_url' => is_string($previewUrl) ? $previewUrl : '',
            );
        }

        return $meta;
    }
}
