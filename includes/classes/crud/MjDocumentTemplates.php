<?php

namespace Mj\Member\Classes\Crud;

use Mj\Member\Classes\MjTools;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Library of reusable document-template rows for the Registration Manager
 * contract: header, footer, parental authorization, attendance attestation,
 * and the two signature blocks (guardian / autonomous member).
 *
 * "Description de l'activité" is intentionally NOT a section here: it stays
 * a free per-event field (events.registration_document), AI-assisted.
 *
 * One row per named template; `is_default=1` marks the row used when an
 * event doesn't explicitly pick another template for that section.
 */
class MjDocumentTemplates extends MjTools implements CrudRepositoryInterface
{
    public const TABLE = 'mj_document_templates';

    public const SECTION_HEADER = 'header';
    public const SECTION_PARENTAL_AUTHORIZATION = 'parental_authorization';
    public const SECTION_ATTENDANCE_ATTESTATION = 'attendance_attestation';
    public const SECTION_SIGNATURE_GUARDIAN = 'signature_guardian';
    public const SECTION_SIGNATURE_AUTONOMOUS = 'signature_autonomous';
    public const SECTION_FOOTER = 'footer';

    // Member "fiche d'inscription" contract (Member fiche's "Contrat" tab):
    // a separate header/content/footer library, dedicated to member data so
    // it can evolve independently from the event registration contract above.
    public const SECTION_MEMBER_HEADER = 'member_header';
    public const SECTION_MEMBER_CONTENT = 'member_content';
    public const SECTION_MEMBER_FOOTER = 'member_footer';
    // 'cover' is intentionally absent (Phase 2): `section` stays a free
    // varchar precisely so it can be added later without a schema change.

    private static function table_name(): string
    {
        if (function_exists('mj_member_get_document_templates_table_name')) {
            return mj_member_get_document_templates_table_name();
        }

        return self::getTableName(self::TABLE);
    }

    /**
     * @return string[]
     */
    public static function known_sections(): array
    {
        return array(
            self::SECTION_HEADER,
            self::SECTION_PARENTAL_AUTHORIZATION,
            self::SECTION_ATTENDANCE_ATTESTATION,
            self::SECTION_SIGNATURE_GUARDIAN,
            self::SECTION_SIGNATURE_AUTONOMOUS,
            self::SECTION_FOOTER,
            self::SECTION_MEMBER_HEADER,
            self::SECTION_MEMBER_CONTENT,
            self::SECTION_MEMBER_FOOTER,
        );
    }

    private static function sanitize_section($value): string
    {
        $value = is_string($value) ? trim($value) : '';

        return in_array($value, self::known_sections(), true) ? $value : '';
    }

    private static function sanitize_name($value): string
    {
        $value = is_string($value) ? trim($value) : '';
        $value = sanitize_text_field($value);

        return mb_substr($value, 0, 190);
    }

    private static function sanitize_content($value): string
    {
        $raw = is_string($value) ? $value : '';
        if ($raw === '') {
            return '';
        }

        if (function_exists('mj_member_sanitize_pdf_rich_html')) {
            return mj_member_sanitize_pdf_rich_html($raw);
        }

        return wp_kses_post($raw);
    }

    /**
     * @param array<string,mixed> $args filter: 'section' => string, 'group_by_section' => bool
     * @return array<int,array<string,mixed>>|array<string,array<int,array<string,mixed>>>
     */
    public static function get_all(array $args = array())
    {
        global $wpdb;
        $table = self::table_name();

        $where = array();
        $params = array();

        $section = self::sanitize_section($args['section'] ?? '');
        if ($section !== '') {
            $where[] = 'section = %s';
            $params[] = $section;
        }

        $sql = "SELECT * FROM {$table}";
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY section ASC, is_default DESC, name ASC';

        $rows = !empty($params)
            ? $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A)
            : $wpdb->get_results($sql, ARRAY_A);

        if (empty($rows)) {
            return array();
        }

        $formatted = array_map(array(self::class, 'format_row'), $rows);

        if (empty($args['group_by_section'])) {
            return $formatted;
        }

        $grouped = array();
        foreach ($formatted as $row) {
            $grouped[$row['section']][] = $row;
        }

        return $grouped;
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

    /**
     * @return array<string,mixed>|null
     */
    public static function get_default(string $section)
    {
        $section = self::sanitize_section($section);
        if ($section === '') {
            return null;
        }

        global $wpdb;
        $table = self::table_name();

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE section = %s AND is_default = 1 ORDER BY id ASC LIMIT 1",
                $section
            ),
            ARRAY_A
        );

        return $row ? self::format_row($row) : null;
    }

    /**
     * @param array<string,mixed> $args
     */
    public static function count(array $args = array())
    {
        global $wpdb;
        $table = self::table_name();

        $section = self::sanitize_section($args['section'] ?? '');
        if ($section !== '') {
            return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE section = %s", $section));
        }

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
    }

    /**
     * @param array<string,mixed>|null $data
     * @return int|WP_Error
     */
    public static function create($data)
    {
        if (!is_array($data)) {
            return new WP_Error('mj_doctpl_invalid_payload', __('Format de données invalide pour le modèle.', 'mj-member'));
        }

        $section = self::sanitize_section($data['section'] ?? '');
        if ($section === '') {
            return new WP_Error('mj_doctpl_invalid_section', __('Section de modèle invalide.', 'mj-member'));
        }

        $name = self::sanitize_name($data['name'] ?? '');
        if ($name === '') {
            return new WP_Error('mj_doctpl_invalid_name', __('Le nom du modèle est requis.', 'mj-member'));
        }

        $content = self::sanitize_content($data['content'] ?? '');
        $isDefault = !empty($data['is_default']);
        $now = current_time('mysql');

        global $wpdb;
        $table = self::table_name();

        $result = $wpdb->insert(
            $table,
            array(
                'section' => $section,
                'name' => $name,
                'content' => $content,
                'is_default' => $isDefault ? 1 : 0,
                'created_at' => $now,
                'updated_at' => $now,
            ),
            array('%s', '%s', '%s', '%d', '%s', '%s')
        );

        if ($result === false) {
            return new WP_Error('mj_doctpl_insert_failed', __('Impossible de créer le modèle.', 'mj-member'));
        }

        $id = (int) $wpdb->insert_id;

        if ($isDefault) {
            self::set_default($id);
        }

        return $id;
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
            return new WP_Error('mj_doctpl_invalid_id', __('Identifiant de modèle invalide.', 'mj-member'));
        }

        $fields = array();
        $formats = array();

        if (array_key_exists('name', $data)) {
            $name = self::sanitize_name($data['name']);
            if ($name === '') {
                return new WP_Error('mj_doctpl_invalid_name', __('Le nom du modèle est requis.', 'mj-member'));
            }
            $fields['name'] = $name;
            $formats[] = '%s';
        }

        if (array_key_exists('content', $data)) {
            $fields['content'] = self::sanitize_content($data['content']);
            $formats[] = '%s';
        }

        if (empty($fields)) {
            return true;
        }

        $fields['updated_at'] = current_time('mysql');
        $formats[] = '%s';

        global $wpdb;
        $updated = $wpdb->update(self::table_name(), $fields, array('id' => $id), $formats, array('%d'));

        if ($updated === false) {
            return new WP_Error('mj_doctpl_update_failed', __('Impossible de mettre à jour le modèle.', 'mj-member'));
        }

        return true;
    }

    /**
     * Marks $id as the default template for its section, clearing the
     * previous default. Two sequential updates (no dedicated transaction
     * API in this plugin's $wpdb usage) — acceptable here since a stale
     * "no default" window only affects fallback resolution, not data loss.
     *
     * @return true|WP_Error
     */
    public static function set_default($id)
    {
        $template = self::get($id);
        if (!$template) {
            return new WP_Error('mj_doctpl_invalid_id', __('Identifiant de modèle invalide.', 'mj-member'));
        }

        global $wpdb;
        $table = self::table_name();

        $wpdb->update($table, array('is_default' => 0), array('section' => $template['section']), array('%d'), array('%s'));
        $result = $wpdb->update($table, array('is_default' => 1), array('id' => $id), array('%d'), array('%d'));

        if ($result === false) {
            return new WP_Error('mj_doctpl_set_default_failed', __('Impossible de définir ce modèle par défaut.', 'mj-member'));
        }

        return true;
    }

    /**
     * @param int $id
     * @return true|WP_Error
     */
    public static function delete($id)
    {
        $template = self::get((int) $id);
        if (!$template) {
            return new WP_Error('mj_doctpl_invalid_id', __('Identifiant de modèle invalide.', 'mj-member'));
        }

        if (!empty($template['is_default'])) {
            $siblingCount = self::count(array('section' => $template['section']));
            if ($siblingCount <= 1) {
                return new WP_Error(
                    'mj_doctpl_cannot_delete_last',
                    __('Impossible de supprimer le dernier modèle d\'une section.', 'mj-member')
                );
            }

            return new WP_Error(
                'mj_doctpl_cannot_delete_default',
                __('Définissez un autre modèle par défaut avant de supprimer celui-ci.', 'mj-member')
            );
        }

        global $wpdb;
        $deleted = $wpdb->delete(self::table_name(), array('id' => (int) $id), array('%d'));

        if ($deleted === false) {
            return new WP_Error('mj_doctpl_delete_failed', __('Suppression du modèle impossible.', 'mj-member'));
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
            'section' => (string) ($row['section'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'content' => (string) ($row['content'] ?? ''),
            'is_default' => !empty($row['is_default']),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        );
    }
}
