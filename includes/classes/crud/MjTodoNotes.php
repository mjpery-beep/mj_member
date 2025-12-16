<?php

namespace Mj\Member\Classes\Crud;

use Mj\Member\Classes\MjTools;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class MjTodoNotes extends MjTools implements CrudRepositoryInterface
{
    private const TABLE = 'mj_todo_notes';

    private static function table_name(): string
    {
        if (function_exists('mj_member_get_todo_notes_table_name')) {
            return mj_member_get_todo_notes_table_name();
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
            $notes = array();
            foreach ($todoIds as $id) {
                if (!empty($map[$id])) {
                    $notes = array_merge($notes, $map[$id]);
                }
            }
            return $notes;
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
            return new WP_Error('mj_member_todo_note_invalid_payload', __('Format de données invalide pour la note.', 'mj-member'));
        }

        $todoId = isset($data['todo_id']) ? (int) $data['todo_id'] : 0;
        $memberId = isset($data['member_id']) ? (int) $data['member_id'] : 0;
        $userId = isset($data['wp_user_id']) ? (int) $data['wp_user_id'] : get_current_user_id();
        $content = self::sanitize_content($data['content'] ?? '');

        if ($todoId <= 0) {
            return new WP_Error('mj_member_todo_note_invalid_todo', __('Tâche introuvable pour la note.', 'mj-member'));
        }

        if ($memberId <= 0) {
            return new WP_Error('mj_member_todo_note_invalid_member', __('Auteur de la note invalide.', 'mj-member'));
        }

        if ($content === '') {
            return new WP_Error('mj_member_todo_note_missing_content', __('Le contenu de la note est requis.', 'mj-member'));
        }

        global $wpdb;
        $table = self::table_name();

        $insert = array(
            'todo_id' => $todoId,
            'member_id' => $memberId,
            'wp_user_id' => max(0, $userId),
            'content' => $content,
        );
        $formats = array('%d', '%d', '%d', '%s');

        $result = $wpdb->insert($table, $insert, $formats);
        if ($result === false) {
            return new WP_Error('mj_member_todo_note_insert_failed', __('Impossible de créer la note.', 'mj-member'));
        }

        return (int) $wpdb->insert_id;
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
            return new WP_Error('mj_member_todo_note_invalid_id', __('Identifiant de note invalide.', 'mj-member'));
        }

        if (!is_array($data) || !array_key_exists('content', $data)) {
            return new WP_Error('mj_member_todo_note_invalid_payload', __('Format de données invalide pour la note.', 'mj-member'));
        }

        $content = self::sanitize_content($data['content']);
        if ($content === '') {
            return new WP_Error('mj_member_todo_note_missing_content', __('Le contenu de la note est requis.', 'mj-member'));
        }

        global $wpdb;
        $table = self::table_name();
        $updated = $wpdb->update($table, array('content' => $content), array('id' => $id), array('%s'), array('%d'));

        if ($updated === false) {
            return new WP_Error('mj_member_todo_note_update_failed', __('Impossible de mettre à jour la note.', 'mj-member'));
        }

        return true;
    }

    /**
     * @param int $id
     * @return true|WP_Error
     */
    public static function delete($id)
    {
        $id = (int) $id;
        if ($id <= 0) {
            return new WP_Error('mj_member_todo_note_invalid_id', __('Identifiant de note invalide.', 'mj-member'));
        }

        global $wpdb;
        $table = self::table_name();
        $deleted = $wpdb->delete($table, array('id' => $id), array('%d'));

        if ($deleted === false) {
            return new WP_Error('mj_member_todo_note_delete_failed', __('Suppression de la note impossible.', 'mj-member'));
        }

        return true;
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function get($id)
    {
        $id = (int) $id;
        if ($id <= 0) {
            return null;
        }

        global $wpdb;
        $table = self::table_name();
        $membersTable = self::getTableName(MjMembers::TABLE_NAME);

        $sql = $wpdb->prepare(
            "SELECT n.*, m.first_name AS member_first_name, m.last_name AS member_last_name
            FROM {$table} AS n
            LEFT JOIN {$membersTable} AS m ON m.id = n.member_id
            WHERE n.id = %d
            LIMIT 1",
            $id
        );

        $row = $wpdb->get_row($sql, ARRAY_A);
        if (!$row) {
            return null;
        }

        return self::format_row($row);
    }

    /**
     * @param array<int,int> $todoIds
     * @return array<int,array<int,array<string,mixed>>>
     */
    public static function get_for_todo_ids(array $todoIds): array
    {
        $todoIds = array_filter(array_unique(array_map('intval', $todoIds)), static function ($value) {
            return $value > 0;
        });

        if (empty($todoIds)) {
            return array();
        }

        global $wpdb;
        $table = self::table_name();
        $membersTable = self::getTableName(MjMembers::TABLE_NAME);
        $idsSql = implode(',', $todoIds);

        $results = $wpdb->get_results(
            "SELECT n.*, m.first_name AS member_first_name, m.last_name AS member_last_name
            FROM {$table} AS n
            LEFT JOIN {$membersTable} AS m ON m.id = n.member_id
            WHERE n.todo_id IN ({$idsSql})
            ORDER BY n.todo_id ASC, n.created_at ASC, n.id ASC",
            ARRAY_A
        );

        if (empty($results)) {
            return array();
        }

        $map = array();
        foreach ($results as $row) {
            $note = self::format_row($row);
            $todoId = $note['todo_id'];
            if (!isset($map[$todoId])) {
                $map[$todoId] = array();
            }
            $map[$todoId][] = $note;
        }

        return $map;
    }

    private static function format_member_name(string $firstName, string $lastName, int $fallbackId): string
    {
        $name = trim($firstName . ' ' . $lastName);
        if ($name !== '') {
            return $name;
        }

        if ($fallbackId > 0) {
            return sprintf(__('Membre #%d', 'mj-member'), $fallbackId);
        }

        return __('Auteur inconnu', 'mj-member');
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private static function format_row(array $row): array
    {
        $todoId = isset($row['todo_id']) ? (int) $row['todo_id'] : 0;
        $memberId = isset($row['member_id']) ? (int) $row['member_id'] : 0;
        $firstName = isset($row['member_first_name']) ? sanitize_text_field((string) $row['member_first_name']) : '';
        $lastName = isset($row['member_last_name']) ? sanitize_text_field((string) $row['member_last_name']) : '';
        $content = isset($row['content']) ? self::sanitize_content($row['content']) : '';

        return array(
            'id' => isset($row['id']) ? (int) $row['id'] : 0,
            'todo_id' => $todoId,
            'member_id' => $memberId,
            'wp_user_id' => isset($row['wp_user_id']) ? (int) $row['wp_user_id'] : 0,
            'content' => $content,
            'created_at' => isset($row['created_at']) ? (string) $row['created_at'] : '',
            'updated_at' => isset($row['updated_at']) ? (string) $row['updated_at'] : '',
            'author_name' => self::format_member_name($firstName, $lastName, $memberId),
        );
    }

    private static function sanitize_content($value): string
    {
        if (!is_string($value)) {
            return '';
        }

        $content = sanitize_textarea_field($value);
        return trim($content);
    }
}
