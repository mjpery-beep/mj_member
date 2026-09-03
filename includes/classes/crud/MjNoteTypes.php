<?php

namespace Mj\Member\Classes\Crud;

use Mj\Member\Classes\MjTools;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Small, admin-manageable lookup table for the "type" of a day note
 * (e.g. "Rappel", "Info parents", "Urgent"...).
 */
class MjNoteTypes extends MjTools implements CrudRepositoryInterface
{
    public const TABLE = 'mj_note_types';

    private static function table_name(): string
    {
        if (function_exists('mj_member_get_note_types_table_name')) {
            return mj_member_get_note_types_table_name();
        }

        return self::getTableName(self::TABLE);
    }

    private static function sanitize_label($value): string
    {
        $value = is_string($value) ? trim($value) : '';
        return sanitize_text_field($value);
    }

    private static function sanitize_color($value): ?string
    {
        $value = is_string($value) ? trim($value) : '';
        if ($value === '') {
            return null;
        }

        $color = sanitize_hex_color($value);
        return $color ?: null;
    }

    private static function sanitize_emoji($value): ?string
    {
        $value = is_string($value) ? trim($value) : '';
        if ($value === '') {
            return null;
        }

        return mb_substr(sanitize_text_field($value), 0, 16) ?: null;
    }

    /**
     * @param array<string,mixed> $args
     * @return array<int,array<string,mixed>>
     */
    public static function get_all(array $args = array())
    {
        global $wpdb;
        $table = self::table_name();

        $rows = $wpdb->get_results("SELECT * FROM {$table} ORDER BY sort_order ASC, label ASC", ARRAY_A);
        if (empty($rows)) {
            return array();
        }

        return array_map(array(self::class, 'format_row'), $rows);
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
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id), ARRAY_A);

        return $row ? self::format_row($row) : null;
    }

    public static function count(array $args = array())
    {
        global $wpdb;
        $table = self::table_name();
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
    }

    /**
     * @param array<string,mixed>|null $data
     * @return int|WP_Error
     */
    public static function create($data)
    {
        if (!is_array($data)) {
            return new WP_Error('mj_note_type_invalid_payload', __('Format de données invalide pour le type de note.', 'mj-member'));
        }

        $label = self::sanitize_label($data['label'] ?? '');
        if ($label === '') {
            return new WP_Error('mj_note_type_missing_label', __('Le libellé du type de note est requis.', 'mj-member'));
        }

        global $wpdb;
        $insert = array(
            'label' => $label,
            'color' => self::sanitize_color($data['color'] ?? null),
            'emoji' => self::sanitize_emoji($data['emoji'] ?? null),
            'sort_order' => isset($data['sort_order']) ? (int) $data['sort_order'] : 0,
        );

        $result = $wpdb->insert(self::table_name(), $insert, array('%s', '%s', '%s', '%d'));
        if ($result === false) {
            return new WP_Error('mj_note_type_insert_failed', __('Impossible de créer le type de note.', 'mj-member'));
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
        if ($id <= 0 || !is_array($data)) {
            return new WP_Error('mj_note_type_invalid_id', __('Identifiant de type de note invalide.', 'mj-member'));
        }

        $fields = array();
        $formats = array();

        if (array_key_exists('label', $data)) {
            $label = self::sanitize_label($data['label']);
            if ($label === '') {
                return new WP_Error('mj_note_type_missing_label', __('Le libellé du type de note est requis.', 'mj-member'));
            }
            $fields['label'] = $label;
            $formats[] = '%s';
        }
        if (array_key_exists('color', $data)) {
            $fields['color'] = self::sanitize_color($data['color']);
            $formats[] = '%s';
        }
        if (array_key_exists('emoji', $data)) {
            $fields['emoji'] = self::sanitize_emoji($data['emoji']);
            $formats[] = '%s';
        }
        if (array_key_exists('sort_order', $data)) {
            $fields['sort_order'] = (int) $data['sort_order'];
            $formats[] = '%d';
        }

        if (empty($fields)) {
            return true;
        }

        global $wpdb;
        $updated = $wpdb->update(self::table_name(), $fields, array('id' => $id), $formats, array('%d'));
        if ($updated === false) {
            return new WP_Error('mj_note_type_update_failed', __('Impossible de mettre à jour le type de note.', 'mj-member'));
        }

        return true;
    }

    /**
     * Deletes a note type. Notes referencing it are detached (note_type_id
     * set back to NULL) rather than deleted.
     *
     * @param int $id
     * @return true|WP_Error
     */
    public static function delete($id)
    {
        $id = (int) $id;
        if ($id <= 0) {
            return new WP_Error('mj_note_type_invalid_id', __('Identifiant de type de note invalide.', 'mj-member'));
        }

        global $wpdb;
        if (function_exists('mj_member_get_agenda_notes_table_name')) {
            $notesTable = mj_member_get_agenda_notes_table_name();
            $wpdb->update($notesTable, array('note_type_id' => null), array('note_type_id' => $id), array('%d'), array('%d'));
        }

        $deleted = $wpdb->delete(self::table_name(), array('id' => $id), array('%d'));
        if ($deleted === false) {
            return new WP_Error('mj_note_type_delete_failed', __('Suppression du type de note impossible.', 'mj-member'));
        }

        return true;
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private static function format_row(array $row): array
    {
        return array(
            'id' => (int) ($row['id'] ?? 0),
            'label' => (string) ($row['label'] ?? ''),
            'color' => isset($row['color']) && $row['color'] !== null ? (string) $row['color'] : '',
            'emoji' => isset($row['emoji']) && $row['emoji'] !== null ? (string) $row['emoji'] : '',
            'sort_order' => (int) ($row['sort_order'] ?? 0),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        );
    }
}
