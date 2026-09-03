<?php

namespace Mj\Member\Classes\Crud;

use Mj\Member\Classes\MjTools;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Internal notes shown on the unified agenda widget.
 *
 * Modelled on {@see MjTodoNotes}: static CRUD, member author, front nonce.
 * A note is a free piece of text pinned to a day (optionally a time range),
 * with a visibility scope that gates who can read it.
 */
class MjAgendaNotes extends MjTools implements CrudRepositoryInterface
{
    public const TABLE = 'mj_agenda_notes';

    public const VISIBILITY_PRIVATE = 'private';
    public const VISIBILITY_STAFF = 'staff';
    public const VISIBILITY_ALL = 'all';

    /** @var string[] */
    private const BASE_VISIBILITIES = array(self::VISIBILITY_PRIVATE, self::VISIBILITY_STAFF, self::VISIBILITY_ALL);

    private static function table_name(): string
    {
        if (function_exists('mj_member_get_agenda_notes_table_name')) {
            return mj_member_get_agenda_notes_table_name();
        }

        return self::getTableName(self::TABLE);
    }

    /**
     * @return string[] allowed visibility tokens ('private', 'staff', 'role:<slug>')
     */
    public static function visibility_options(): array
    {
        $roles = array();
        if (class_exists('\\Mj\\Member\\Classes\\MjRoles')) {
            foreach (array('COORDINATEUR', 'ANIMATEUR', 'BENEVOLE', 'JEUNE') as $const) {
                $qualified = '\\Mj\\Member\\Classes\\MjRoles::' . $const;
                if (defined($qualified)) {
                    $roles[] = 'role:' . constant($qualified);
                }
            }
        }

        return array_merge(self::BASE_VISIBILITIES, $roles);
    }

    private static function sanitize_visibility($value): string
    {
        $value = is_string($value) ? trim($value) : '';
        $options = self::visibility_options();

        return in_array($value, $options, true) ? $value : self::VISIBILITY_STAFF;
    }

    private static function sanitize_date($value): string
    {
        $value = is_string($value) ? trim($value) : '';

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
    }

    private static function sanitize_time($value): ?string
    {
        $value = is_string($value) ? trim($value) : '';
        if ($value === '') {
            return null;
        }

        if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)(:[0-5]\d)?$/', $value, $m)) {
            return $m[1] . ':' . $m[2] . ':00';
        }

        return null;
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

    private static function sanitize_content($value): string
    {
        if (!is_string($value)) {
            return '';
        }

        return trim(sanitize_textarea_field($value));
    }

    private static function sanitize_emoji($value): ?string
    {
        $value = is_string($value) ? trim($value) : '';
        if ($value === '') {
            return null;
        }

        $value = sanitize_text_field($value);
        // Emoji are short; guard against pasted paragraphs ending up in the column.
        return mb_substr($value, 0, 16) ?: null;
    }

    private static function sanitize_series_id($value): ?string
    {
        $value = is_string($value) ? trim($value) : '';
        if ($value === '' || !preg_match('/^[A-Za-z0-9\-]{1,36}$/', $value)) {
            return null;
        }

        return $value;
    }

    /**
     * @param array<string,mixed> $args
     * @return array<int,array<string,mixed>>
     */
    public static function get_all(array $args = array())
    {
        $from = self::sanitize_date($args['date_from'] ?? '');
        $to = self::sanitize_date($args['date_to'] ?? '');
        if ($from === '' || $to === '') {
            return array();
        }

        return self::get_by_date_range($from, $to, $args);
    }

    /**
     * @param array<string,mixed> $args author_member_id, visibilities[], event_id, member_id
     * @return array<int,array<string,mixed>>
     */
    public static function get_by_date_range(string $dateFrom, string $dateTo, array $args = array()): array
    {
        $dateFrom = self::sanitize_date($dateFrom);
        $dateTo = self::sanitize_date($dateTo);
        if ($dateFrom === '' || $dateTo === '') {
            return array();
        }

        global $wpdb;
        $table = self::table_name();
        $membersTable = self::getTableName(MjMembers::TABLE_NAME);

        $where = array('n.note_date BETWEEN %s AND %s');
        $params = array($dateFrom, $dateTo);

        if (!empty($args['event_id'])) {
            $where[] = 'n.event_id = %d';
            $params[] = (int) $args['event_id'];
        }

        if (!empty($args['member_id'])) {
            $where[] = 'n.member_id = %d';
            $params[] = (int) $args['member_id'];
        }

        // Visibility gate: caller passes the visibility tokens it is allowed to
        // see, plus (optionally) its own author id for the 'private' scope.
        $visClauses = array();
        if (isset($args['visibilities']) && is_array($args['visibilities']) && !empty($args['visibilities'])) {
            $placeholders = implode(',', array_fill(0, count($args['visibilities']), '%s'));
            $visClauses[] = "n.visibility IN ({$placeholders})";
            foreach ($args['visibilities'] as $vis) {
                $params[] = (string) $vis;
            }
        }
        if (!empty($args['author_member_id'])) {
            $visClauses[] = 'n.author_member_id = %d';
            $params[] = (int) $args['author_member_id'];
        }
        // A note assigned to a specific member ("Personne assignée") is always
        // visible to that member, regardless of the visibility token/their role.
        if (!empty($args['assigned_member_id'])) {
            $visClauses[] = 'n.member_id = %d';
            $params[] = (int) $args['assigned_member_id'];
        }
        if (!empty($visClauses)) {
            $where[] = '(' . implode(' OR ', $visClauses) . ')';
        }

        $sql = "SELECT n.*, m.first_name AS author_first_name, m.last_name AS author_last_name
            FROM {$table} AS n
            LEFT JOIN {$membersTable} AS m ON m.id = n.author_member_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY n.note_date ASC, n.start_time ASC, n.id ASC";

        $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
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
        $membersTable = self::getTableName(MjMembers::TABLE_NAME);

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT n.*, m.first_name AS author_first_name, m.last_name AS author_last_name
                FROM {$table} AS n
                LEFT JOIN {$membersTable} AS m ON m.id = n.author_member_id
                WHERE n.id = %d
                LIMIT 1",
                $id
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
        $from = self::sanitize_date($args['date_from'] ?? '');
        $to = self::sanitize_date($args['date_to'] ?? '');
        if ($from === '' || $to === '') {
            return 0;
        }

        global $wpdb;
        $table = self::table_name();

        return (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE note_date BETWEEN %s AND %s", $from, $to)
        );
    }

    /**
     * @param array<string,mixed>|null $data
     * @return int|WP_Error
     */
    public static function create($data)
    {
        if (!is_array($data)) {
            return new WP_Error('mj_agenda_note_invalid_payload', __('Format de données invalide pour la note.', 'mj-member'));
        }

        $authorMemberId = isset($data['author_member_id']) ? (int) $data['author_member_id'] : 0;
        $noteDate = self::sanitize_date($data['note_date'] ?? '');
        $content = self::sanitize_content($data['content'] ?? '');

        if ($authorMemberId <= 0) {
            return new WP_Error('mj_agenda_note_invalid_author', __('Auteur de la note invalide.', 'mj-member'));
        }
        if ($noteDate === '') {
            return new WP_Error('mj_agenda_note_invalid_date', __('Date de la note invalide.', 'mj-member'));
        }
        if ($content === '') {
            return new WP_Error('mj_agenda_note_missing_content', __('Le contenu de la note est requis.', 'mj-member'));
        }

        global $wpdb;

        $insert = array(
            'author_member_id' => $authorMemberId,
            'wp_user_id' => isset($data['wp_user_id']) ? max(0, (int) $data['wp_user_id']) : get_current_user_id(),
            'note_date' => $noteDate,
            'start_time' => self::sanitize_time($data['start_time'] ?? null),
            'end_time' => self::sanitize_time($data['end_time'] ?? null),
            'title' => isset($data['title']) ? (sanitize_text_field((string) $data['title']) ?: null) : null,
            'emoji' => self::sanitize_emoji($data['emoji'] ?? null),
            'content' => $content,
            'color' => self::sanitize_color($data['color'] ?? null),
            'note_type_id' => !empty($data['note_type_id']) ? (int) $data['note_type_id'] : null,
            'visibility' => self::sanitize_visibility($data['visibility'] ?? self::VISIBILITY_STAFF),
            'event_id' => !empty($data['event_id']) ? (int) $data['event_id'] : null,
            'member_id' => !empty($data['member_id']) ? (int) $data['member_id'] : null,
            'series_id' => self::sanitize_series_id($data['series_id'] ?? null),
        );
        $formats = array('%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%d', '%s');

        $result = $wpdb->insert(self::table_name(), $insert, $formats);
        if ($result === false) {
            return new WP_Error('mj_agenda_note_insert_failed', __('Impossible de créer la note.', 'mj-member'));
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * Create several notes sharing a common series id, e.g. one row per date
     * chosen in "dates multiples" / "jours de la semaine récurrents" mode.
     *
     * @param array<int,array<string,mixed>> $rows each row is a payload for create(), without series_id
     * @return array{series_id:string,ids:int[]}|WP_Error
     */
    public static function create_many(array $rows)
    {
        if (empty($rows)) {
            return new WP_Error('mj_agenda_note_empty_series', __('Aucune date fournie pour la note.', 'mj-member'));
        }

        $seriesId = wp_generate_uuid4();
        $ids = array();

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $row['series_id'] = $seriesId;
            $result = self::create($row);
            if (is_wp_error($result)) {
                foreach ($ids as $createdId) {
                    self::delete($createdId);
                }
                return $result;
            }
            $ids[] = $result;
        }

        if (empty($ids)) {
            return new WP_Error('mj_agenda_note_empty_series', __('Aucune date fournie pour la note.', 'mj-member'));
        }

        return array('series_id' => $seriesId, 'ids' => $ids);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function get_series(string $seriesId): array
    {
        $seriesId = self::sanitize_series_id($seriesId);
        if ($seriesId === null) {
            return array();
        }

        global $wpdb;
        $table = self::table_name();
        $membersTable = self::getTableName(MjMembers::TABLE_NAME);

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT n.*, m.first_name AS author_first_name, m.last_name AS author_last_name
                FROM {$table} AS n
                LEFT JOIN {$membersTable} AS m ON m.id = n.author_member_id
                WHERE n.series_id = %s
                ORDER BY n.note_date ASC, n.id ASC",
                $seriesId
            ),
            ARRAY_A
        );

        if (empty($rows)) {
            return array();
        }

        return array_map(array(self::class, 'format_row'), $rows);
    }

    /**
     * @return true|WP_Error
     */
    public static function delete_series(string $seriesId)
    {
        $seriesId = self::sanitize_series_id($seriesId);
        if ($seriesId === null) {
            return new WP_Error('mj_agenda_note_invalid_series', __('Identifiant de série invalide.', 'mj-member'));
        }

        global $wpdb;
        $deleted = $wpdb->delete(self::table_name(), array('series_id' => $seriesId), array('%s'));

        if ($deleted === false) {
            return new WP_Error('mj_agenda_note_delete_failed', __('Suppression de la série impossible.', 'mj-member'));
        }

        return true;
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
            return new WP_Error('mj_agenda_note_invalid_id', __('Identifiant de note invalide.', 'mj-member'));
        }

        $fields = array();
        $formats = array();

        if (array_key_exists('note_date', $data)) {
            $date = self::sanitize_date($data['note_date']);
            if ($date === '') {
                return new WP_Error('mj_agenda_note_invalid_date', __('Date de la note invalide.', 'mj-member'));
            }
            $fields['note_date'] = $date;
            $formats[] = '%s';
        }
        if (array_key_exists('start_time', $data)) {
            $fields['start_time'] = self::sanitize_time($data['start_time']);
            $formats[] = '%s';
        }
        if (array_key_exists('end_time', $data)) {
            $fields['end_time'] = self::sanitize_time($data['end_time']);
            $formats[] = '%s';
        }
        if (array_key_exists('title', $data)) {
            $fields['title'] = sanitize_text_field((string) $data['title']) ?: null;
            $formats[] = '%s';
        }
        if (array_key_exists('emoji', $data)) {
            $fields['emoji'] = self::sanitize_emoji($data['emoji']);
            $formats[] = '%s';
        }
        if (array_key_exists('note_type_id', $data)) {
            $fields['note_type_id'] = !empty($data['note_type_id']) ? (int) $data['note_type_id'] : null;
            $formats[] = '%d';
        }
        if (array_key_exists('content', $data)) {
            $content = self::sanitize_content($data['content']);
            if ($content === '') {
                return new WP_Error('mj_agenda_note_missing_content', __('Le contenu de la note est requis.', 'mj-member'));
            }
            $fields['content'] = $content;
            $formats[] = '%s';
        }
        if (array_key_exists('color', $data)) {
            $fields['color'] = self::sanitize_color($data['color']);
            $formats[] = '%s';
        }
        if (array_key_exists('visibility', $data)) {
            $fields['visibility'] = self::sanitize_visibility($data['visibility']);
            $formats[] = '%s';
        }
        if (array_key_exists('event_id', $data)) {
            $fields['event_id'] = !empty($data['event_id']) ? (int) $data['event_id'] : null;
            $formats[] = '%d';
        }
        if (array_key_exists('member_id', $data)) {
            $fields['member_id'] = !empty($data['member_id']) ? (int) $data['member_id'] : null;
            $formats[] = '%d';
        }

        if (empty($fields)) {
            return true;
        }

        global $wpdb;
        $updated = $wpdb->update(self::table_name(), $fields, array('id' => $id), $formats, array('%d'));

        if ($updated === false) {
            return new WP_Error('mj_agenda_note_update_failed', __('Impossible de mettre à jour la note.', 'mj-member'));
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
            return new WP_Error('mj_agenda_note_invalid_id', __('Identifiant de note invalide.', 'mj-member'));
        }

        global $wpdb;
        $deleted = $wpdb->delete(self::table_name(), array('id' => $id), array('%d'));

        if ($deleted === false) {
            return new WP_Error('mj_agenda_note_delete_failed', __('Suppression de la note impossible.', 'mj-member'));
        }

        return true;
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private static function format_row(array $row): array
    {
        $firstName = isset($row['author_first_name']) ? sanitize_text_field((string) $row['author_first_name']) : '';
        $lastName = isset($row['author_last_name']) ? sanitize_text_field((string) $row['author_last_name']) : '';
        $authorName = trim($firstName . ' ' . $lastName);

        return array(
            'id' => (int) ($row['id'] ?? 0),
            'author_member_id' => (int) ($row['author_member_id'] ?? 0),
            'wp_user_id' => (int) ($row['wp_user_id'] ?? 0),
            'note_date' => (string) ($row['note_date'] ?? ''),
            'start_time' => isset($row['start_time']) && $row['start_time'] !== null ? substr((string) $row['start_time'], 0, 5) : null,
            'end_time' => isset($row['end_time']) && $row['end_time'] !== null ? substr((string) $row['end_time'], 0, 5) : null,
            'title' => isset($row['title']) ? (string) $row['title'] : '',
            'emoji' => isset($row['emoji']) && $row['emoji'] !== null ? (string) $row['emoji'] : '',
            'content' => self::sanitize_content($row['content'] ?? ''),
            'color' => isset($row['color']) && $row['color'] !== null ? (string) $row['color'] : '',
            'note_type_id' => isset($row['note_type_id']) && $row['note_type_id'] !== null ? (int) $row['note_type_id'] : 0,
            'visibility' => (string) ($row['visibility'] ?? self::VISIBILITY_STAFF),
            'event_id' => isset($row['event_id']) && $row['event_id'] !== null ? (int) $row['event_id'] : 0,
            'member_id' => isset($row['member_id']) && $row['member_id'] !== null ? (int) $row['member_id'] : 0,
            'series_id' => isset($row['series_id']) && $row['series_id'] !== null ? (string) $row['series_id'] : '',
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
            'author_name' => $authorName !== '' ? $authorName : __('Auteur inconnu', 'mj-member'),
        );
    }
}
