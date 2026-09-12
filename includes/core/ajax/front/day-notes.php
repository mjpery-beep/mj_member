<?php
/**
 * AJAX front – Notes de jour (calendrier "mj-member-events-calendar" et
 * widget de gestion "mj-member-day-notes").
 *
 * Écrit dans la même table que le widget Agenda unifié ({@see MjAgendaNotes})
 * mais avec ses propres endpoints : AgendaController n'implémente pas
 * l'écriture des notes (stubs 501) et gère une abstraction "entrée" bien
 * plus large que ce dont ce widget a besoin.
 *
 * @package MjMember
 */

namespace Mj\Member\Core\Ajax\Front;

use Mj\Member\Core\Contracts\AjaxHandlerInterface;
use Mj\Member\Core\Config;
use Mj\Member\Classes\MjAgendaAcl;
use Mj\Member\Classes\MjRoles;
use Mj\Member\Classes\Crud\MjMembers;
use Mj\Member\Classes\Crud\MjAgendaNotes;
use Mj\Member\Classes\Crud\MjNoteTypes;
use Mj\Member\Classes\Crud\MjNoteMedia;

if (!defined('ABSPATH')) {
    exit;
}

final class DayNotesAjaxController implements AjaxHandlerInterface
{
    private const NONCE = 'mj-member-day-notes';

    public function registerHooks(): void
    {
        add_action('wp_ajax_mj_member_day_notes_list', array($this, 'list'));
        add_action('wp_ajax_mj_member_day_notes_get_series', array($this, 'getSeries'));
        add_action('wp_ajax_mj_member_day_notes_create', array($this, 'create'));
        add_action('wp_ajax_mj_member_day_notes_update', array($this, 'update'));
        add_action('wp_ajax_mj_member_day_notes_delete', array($this, 'delete'));
        add_action('wp_ajax_mj_member_day_notes_upload_media', array($this, 'uploadMedia'));
        add_action('wp_ajax_mj_member_day_notes_delete_media', array($this, 'deleteMedia'));
        add_action('wp_ajax_mj_member_day_notes_search_jeunes', array($this, 'searchJeunes'));

        add_action('wp_ajax_mj_member_note_types_list', array($this, 'noteTypesList'));
        add_action('wp_ajax_mj_member_note_types_create', array($this, 'noteTypesCreate'));
        add_action('wp_ajax_mj_member_note_types_update', array($this, 'noteTypesUpdate'));
        add_action('wp_ajax_mj_member_note_types_delete', array($this, 'noteTypesDelete'));
    }

    /**
     * Session data for the day-notes management widget.
     */
    public static function localize(): void
    {
        $userId = get_current_user_id();
        $memberId = 0;
        $role = MjRoles::JEUNE;
        if ($userId && class_exists(MjMembers::class)) {
            $member = MjMembers::getByWpUserId($userId);
            if ($member) {
                $arr = method_exists($member, 'toArray') ? $member->toArray() : (array) $member;
                $memberId = isset($arr['id']) ? (int) $arr['id'] : 0;
                $role = MjAgendaAcl::normalizeRole($arr['role'] ?? '');
            }
        }

        $groupOptions = array(
            array('value' => 'private', 'label' => __('Uniquement moi', 'mj-member')),
            array('value' => 'role:' . MjRoles::ANIMATEUR, 'label' => __('🎭 Animateurs', 'mj-member')),
            array('value' => 'role:' . MjRoles::COORDINATEUR, 'label' => __('👔 Coordinateurs', 'mj-member')),
            array('value' => 'role:' . MjRoles::BENEVOLE, 'label' => __('🤝 Bénévoles', 'mj-member')),
            array('value' => 'role:' . MjRoles::JEUNE, 'label' => __('🧒 Jeunes', 'mj-member')),
            array('value' => 'staff', 'label' => __('🏢 Staff', 'mj-member')),
            array('value' => 'all', 'label' => __('👥 Tous', 'mj-member')),
        );

        wp_localize_script('mj-member-day-notes-widget', 'mjMemberDayNotes', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce(self::NONCE),
            'nextcloudNonce' => wp_create_nonce('mj-registration-manager'),
            'currentMemberId' => $memberId,
            'canManageTypes' => current_user_can(Config::capability()),
            'noteTypes' => class_exists(MjNoteTypes::class) ? MjNoteTypes::get_all() : array(),
            'groupOptions' => $groupOptions,
            'members' => function_exists('mj_member_todo_fetch_assignable_members') ? mj_member_todo_fetch_assignable_members() : array(),
            'i18n' => array(
                'newNote' => __('Nouvelle note', 'mj-member'),
                'empty' => __('Aucune note pour le moment.', 'mj-member'),
            ),
        ));
    }

    // -----------------------------------------------------------------
    // Shared request plumbing
    // -----------------------------------------------------------------

    /**
     * @return array{user_id:int,member_id:int,role:string}
     */
    private function requireActor(): array
    {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash((string) $_POST['nonce'])), self::NONCE)) {
            wp_send_json_error(array('message' => __('Sécurité échouée.', 'mj-member')), 403);
        }

        $userId = get_current_user_id();
        if (!$userId) {
            wp_send_json_error(array('message' => __('Vous devez être connecté.', 'mj-member')), 403);
        }

        $memberId = 0;
        $role = MjRoles::JEUNE;
        if (class_exists(MjMembers::class)) {
            $member = MjMembers::getByWpUserId($userId);
            if ($member) {
                $arr = method_exists($member, 'toArray') ? $member->toArray() : (array) $member;
                $memberId = isset($arr['id']) ? (int) $arr['id'] : 0;
                $role = MjAgendaAcl::normalizeRole($arr['role'] ?? '');
            }
        }
        $role = MjAgendaAcl::normalizeRole($role);

        return array('user_id' => $userId, 'member_id' => $memberId, 'role' => $role);
    }

    private function requireNoteTypeManager(): void
    {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash((string) $_POST['nonce'])), self::NONCE)) {
            wp_send_json_error(array('message' => __('Sécurité échouée.', 'mj-member')), 403);
        }

        if (!current_user_can(Config::capability())) {
            wp_send_json_error(array('message' => __('Action non autorisée.', 'mj-member')), 403);
        }
    }

    private static function sanitizeDate($value): string
    {
        $value = is_string($value) ? trim($value) : '';
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
    }

    /**
     * Visibility tokens a given MJ role may read for day notes.
     *
     * @return string[]
     */
    private static function visibilitiesForRole(string $role): array
    {
        $tokens = array(MjAgendaNotes::VISIBILITY_ALL);
        if (in_array($role, array(MjRoles::COORDINATEUR, MjRoles::ANIMATEUR, MjRoles::BENEVOLE), true)) {
            $tokens[] = MjAgendaNotes::VISIBILITY_STAFF;
        }
        $tokens[] = 'role:' . $role;
        if ($role === MjRoles::COORDINATEUR) {
            foreach (MjAgendaAcl::roles() as $r) {
                $tokens[] = 'role:' . $r;
            }
        }

        return array_values(array_unique($tokens));
    }

    /**
     * @param array<int,array<string,mixed>> $notes
     * @return array<int,array<string,mixed>>
     */
    private static function withMedia(array $notes): array
    {
        foreach ($notes as &$note) {
            $note['media'] = self::formatMedia((int) ($note['id'] ?? 0));
            $authorMemberId = (int) ($note['author_member_id'] ?? 0);
            $note['author_avatar'] = self::avatarUrl($authorMemberId);
            $note['author_name'] = isset($note['author_name']) ? (string) $note['author_name'] : '';

            $assignedIds = isset($note['assigned_member_ids']) && is_array($note['assigned_member_ids'])
                ? $note['assigned_member_ids']
                : array();
            $assignedAvatars = array();
            foreach ($assignedIds as $assignedId) {
                // L'auteur a déjà son avatar affiché séparément ; éviter le doublon
                // quand la note lui est aussi assignée.
                if ((int) $assignedId === $authorMemberId) {
                    continue;
                }
                $url = self::avatarUrl((int) $assignedId);
                if ($url !== '') {
                    $assignedAvatars[] = $url;
                }
            }
            $note['assigned_avatars'] = $assignedAvatars;
            $note['note_type_label'] = self::noteTypeLabel((int) ($note['note_type_id'] ?? 0));
            $note['note_type_emoji'] = self::noteTypeEmoji((int) ($note['note_type_id'] ?? 0));
        }
        unset($note);

        return $notes;
    }

    /**
     * Resolves a member's mj-member avatar (never the WP/gravatar one).
     * Mirrors mj_regmgr_get_member_avatar_url() directly rather than
     * relying on it being loaded: that helper lives in an admin-only file
     * (includes/core/ajax/admin/registration-manager/helpers.php),
     * available here in practice since admin-ajax.php runs is_admin()=true,
     * but kept self-contained to not depend on module load order.
     */
    private static function avatarUrl(int $memberId): string
    {
        if ($memberId <= 0 || !class_exists(MjMembers::class)) {
            return '';
        }

        $member = MjMembers::getById($memberId);
        if (!$member) {
            return '';
        }

        $photoId = isset($member->photo_id) ? (int) $member->photo_id : 0;
        if ($photoId > 0) {
            $url = wp_get_attachment_image_url($photoId, 'thumbnail');
            if ($url) {
                return (string) $url;
            }
        }

        $avatarId = isset($member->avatar_id) ? (int) $member->avatar_id : 0;
        if ($avatarId > 0) {
            $url = wp_get_attachment_image_url($avatarId, 'thumbnail');
            if ($url) {
                return (string) $url;
            }
        }

        $defaultAvatarId = (int) get_option('mj_login_default_avatar_id', 0);
        if ($defaultAvatarId > 0) {
            $url = wp_get_attachment_image_url($defaultAvatarId, 'thumbnail');
            if ($url) {
                return (string) $url;
            }
        }

        return '';
    }

    /**
     * @return array<int,array{label:string,emoji:string}>
     */
    private static function noteTypeMap(): array
    {
        static $map = null;

        if ($map === null) {
            $map = array();
            if (class_exists(MjNoteTypes::class)) {
                foreach (MjNoteTypes::get_all() as $row) {
                    if (!empty($row['id'])) {
                        $map[(int) $row['id']] = array(
                            'label' => isset($row['label']) ? (string) $row['label'] : '',
                            'emoji' => isset($row['emoji']) ? (string) $row['emoji'] : '',
                        );
                    }
                }
            }
        }

        return $map;
    }

    private static function noteTypeLabel(int $noteTypeId): string
    {
        $map = self::noteTypeMap();
        return isset($map[$noteTypeId]) ? $map[$noteTypeId]['label'] : '';
    }

    private static function noteTypeEmoji(int $noteTypeId): string
    {
        $map = self::noteTypeMap();
        return isset($map[$noteTypeId]) ? $map[$noteTypeId]['emoji'] : '';
    }

    /**
     * @return array<int,array{id:int,url:string}>
     */
    private static function formatMedia(int $noteId): array
    {
        if ($noteId <= 0 || !class_exists(MjNoteMedia::class)) {
            return array();
        }

        $rows = MjNoteMedia::get_all(array('note_id' => $noteId));
        $media = array();
        foreach ($rows as $row) {
            $attachmentId = isset($row->attachment_id) ? (int) $row->attachment_id : 0;
            if ($attachmentId <= 0) {
                continue;
            }
            $url = wp_get_attachment_image_url($attachmentId, 'medium');
            $thumb = wp_get_attachment_image_url($attachmentId, 'thumbnail');
            $media[] = array(
                'id' => $attachmentId,
                'url' => $url ? (string) $url : '',
                'thumbUrl' => $thumb ? (string) $thumb : '',
            );
        }

        return $media;
    }

    /**
     * @param mixed $raw JSON-encoded array of attachment ids
     * @return int[]
     */
    private static function sanitizeAttachmentIds($raw): array
    {
        $decoded = is_string($raw) ? json_decode(wp_unslash($raw), true) : $raw;
        if (!is_array($decoded)) {
            return array();
        }

        $ids = array();
        foreach ($decoded as $value) {
            $id = (int) $value;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    private static function attachMedia(int $noteId, array $attachmentIds): void
    {
        if ($noteId <= 0 || !class_exists(MjNoteMedia::class)) {
            return;
        }

        $order = 0;
        foreach ($attachmentIds as $attachmentId) {
            MjNoteMedia::create(array(
                'note_id' => $noteId,
                'attachment_id' => $attachmentId,
                'sort_order' => $order++,
            ));
        }
    }

    // -----------------------------------------------------------------
    // Notes: list / create / update / delete
    // -----------------------------------------------------------------

    public function list(): void
    {
        $actor = $this->requireActor();

        $dateFrom = self::sanitizeDate($_POST['date_from'] ?? '');
        $dateTo = self::sanitizeDate($_POST['date_to'] ?? '');
        if ($dateFrom === '' || $dateTo === '') {
            wp_send_json_error(array('message' => __('Plage de dates invalide.', 'mj-member')), 400);
        }

        $notes = MjAgendaNotes::get_by_date_range($dateFrom, $dateTo, array(
            'visibilities' => self::visibilitiesForRole($actor['role']),
            'author_member_id' => $actor['member_id'],
            'assigned_member_id' => $actor['member_id'],
        ));

        foreach ($notes as &$note) {
            $note['can_edit'] = $note['author_member_id'] === $actor['member_id'];
        }
        unset($note);

        $notes = self::withMedia($notes);

        // The calendar's per-day rendering wants one row per date (default
        // behaviour, grouped client-side by note_date); the notes management
        // widget instead shows a recurring note as a single logical entry.
        if (!empty($_POST['collapse_series'])) {
            $notes = self::collapseSeries($notes);
        }

        wp_send_json_success(array('notes' => $notes));
    }

    /**
     * Collapses a recurring note's per-date rows into one representative
     * entry (the earliest occurrence returned by the query), carrying the
     * *full*, unbounded list of the series' dates in `series_dates` so an
     * edit form can show/adjust every occurrence, not just the ones inside
     * the current query window.
     *
     * @param array<int,array<string,mixed>> $notes
     * @return array<int,array<string,mixed>>
     */
    private static function collapseSeries(array $notes): array
    {
        $result = array();
        $seenSeries = array();

        foreach ($notes as $note) {
            $seriesId = (string) ($note['series_id'] ?? '');
            if ($seriesId === '') {
                $note['series_dates'] = array();
                $result[] = $note;
                continue;
            }

            if (isset($seenSeries[$seriesId])) {
                continue;
            }
            $seenSeries[$seriesId] = true;

            $dates = array_values(array_unique(array_map(
                static function ($row) {
                    return $row['note_date'];
                },
                MjAgendaNotes::get_series($seriesId)
            )));
            sort($dates);
            $note['series_dates'] = $dates;
            $result[] = $note;
        }

        return $result;
    }

    /**
     * Full date list of a note series, used by the edit form to know every
     * occurrence when it was only handed one of them (e.g. from the
     * calendar's per-day view) — editing must never drop occurrences it
     * doesn't know about.
     */
    public function getSeries(): void
    {
        $this->requireActor();

        $seriesId = isset($_POST['series_id']) ? sanitize_text_field(wp_unslash((string) $_POST['series_id'])) : '';
        if ($seriesId === '') {
            wp_send_json_error(array('message' => __('Série introuvable.', 'mj-member')), 404);
        }

        $rows = MjAgendaNotes::get_series($seriesId);
        $dates = array_values(array_unique(array_map(
            static function ($row) {
                return $row['note_date'];
            },
            $rows
        )));
        sort($dates);

        $recurrenceRule = null;
        foreach ($rows as $row) {
            if (!empty($row['recurrence_rule'])) {
                $recurrenceRule = $row['recurrence_rule'];
                break;
            }
        }

        wp_send_json_success(array('dates' => $dates, 'recurrence_rule' => $recurrenceRule));
    }

    public function create(): void
    {
        $actor = $this->requireActor();
        MjAgendaAcl::assert($actor['role'], 'internal_notes', 'create');

        $dates = self::sanitizeDatesInput($_POST['dates'] ?? ($_POST['note_date'] ?? ''));
        if (empty($dates)) {
            wp_send_json_error(array('message' => __('Au moins une date est requise.', 'mj-member')), 400);
        }

        $base = $this->sanitizePayloadFromRequest($actor);
        $attachmentIds = self::sanitizeAttachmentIds($_POST['attachment_ids'] ?? '[]');

        if (count($dates) === 1) {
            $payload = $base;
            $payload['note_date'] = $dates[0];
            $result = MjAgendaNotes::create($payload);
            if (is_wp_error($result)) {
                wp_send_json_error(array('message' => $result->get_error_message()), 400);
            }
            self::attachMedia((int) $result, $attachmentIds);
            wp_send_json_success(array('note' => self::withMedia(array(MjAgendaNotes::get((int) $result)))[0]));
        }

        $rows = array();
        foreach ($dates as $date) {
            $payload = $base;
            $payload['note_date'] = $date;
            $rows[] = $payload;
        }

        $result = MjAgendaNotes::create_many($rows);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 400);
        }

        foreach ($result['ids'] as $noteId) {
            self::attachMedia((int) $noteId, $attachmentIds);
        }

        wp_send_json_success(array('notes' => self::withMedia(MjAgendaNotes::get_series($result['series_id']))));
    }

    /**
     * A note is one logical entry that may span several dates ("récurrence").
     * All dates share one row per occurrence (tied by series_id) so the
     * calendar can query/display them per day, but editing acts on the
     * whole series: shared fields (content, title, visibility…) are applied
     * to every occurrence, and the date list itself is synced against the
     * submitted `dates` — occurrences no longer present are deleted, new
     * ones are created, unchanged ones keep their id (and media).
     */
    public function update(): void
    {
        $actor = $this->requireActor();

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $existing = $id > 0 ? MjAgendaNotes::get($id) : null;
        if (!$existing) {
            wp_send_json_error(array('message' => __('Note introuvable.', 'mj-member')), 404);
        }

        $isOwner = $existing['author_member_id'] === $actor['member_id'];
        MjAgendaAcl::assert($actor['role'], 'internal_notes', 'edit_own', $isOwner);

        $dates = self::sanitizeDatesInput($_POST['dates'] ?? ($_POST['note_date'] ?? $existing['note_date']));
        if (empty($dates)) {
            wp_send_json_error(array('message' => __('Au moins une date est requise.', 'mj-member')), 400);
        }

        $payload = $this->sanitizePayloadFromRequest($actor);
        unset($payload['author_member_id'], $payload['wp_user_id']);

        $attachmentIds = isset($_POST['attachment_ids']) ? self::sanitizeAttachmentIds($_POST['attachment_ids']) : null;

        $seriesId = $existing['series_id'] !== '' ? $existing['series_id'] : null;
        $existingRows = $seriesId !== null ? MjAgendaNotes::get_series($seriesId) : array($existing);

        $newDateSet = array_flip($dates);
        $targetSeriesId = count($dates) > 1 ? ($seriesId ?: wp_generate_uuid4()) : null;

        $keepIds = array();
        $existingByDate = array();
        foreach ($existingRows as $row) {
            $rowId = (int) $row['id'];
            $existingByDate[$row['note_date']] = $rowId;

            if (isset($newDateSet[$row['note_date']])) {
                $fields = $payload;
                $fields['series_id'] = $targetSeriesId;
                $result = MjAgendaNotes::update($rowId, $fields);
                if (is_wp_error($result)) {
                    wp_send_json_error(array('message' => $result->get_error_message()), 400);
                }
                $keepIds[] = $rowId;
            } else {
                if (class_exists(MjNoteMedia::class)) {
                    MjNoteMedia::delete_for_note($rowId);
                }
                MjAgendaNotes::delete($rowId);
            }
        }

        $createdIds = array();
        foreach ($dates as $date) {
            if (isset($existingByDate[$date])) {
                continue;
            }
            $createPayload = $payload;
            $createPayload['author_member_id'] = $existing['author_member_id'];
            $createPayload['wp_user_id'] = $existing['wp_user_id'];
            $createPayload['note_date'] = $date;
            $createPayload['series_id'] = $targetSeriesId;
            $newId = MjAgendaNotes::create($createPayload);
            if (is_wp_error($newId)) {
                wp_send_json_error(array('message' => $newId->get_error_message()), 400);
            }
            $createdIds[] = (int) $newId;
        }

        $allIds = array_merge($keepIds, $createdIds);

        if ($attachmentIds !== null) {
            foreach ($allIds as $noteId) {
                if (class_exists(MjNoteMedia::class)) {
                    MjNoteMedia::delete_for_note($noteId);
                }
                self::attachMedia($noteId, $attachmentIds);
            }
        } elseif (!empty($createdIds) && !empty($keepIds)) {
            // New occurrences inherit whatever media the note already has.
            $sourceMedia = self::formatMedia($keepIds[0]);
            if (!empty($sourceMedia)) {
                $copyIds = array_map(static function ($m) { return $m['id']; }, $sourceMedia);
                foreach ($createdIds as $noteId) {
                    self::attachMedia($noteId, $copyIds);
                }
            }
        }

        $representativeId = in_array($id, $allIds, true) ? $id : ($allIds[0] ?? $id);
        $result = MjAgendaNotes::get($representativeId);
        if (!$result) {
            wp_send_json_error(array('message' => __('Note introuvable après mise à jour.', 'mj-member')), 404);
        }

        wp_send_json_success(array('note' => self::withMedia(array($result))[0]));
    }

    public function delete(): void
    {
        $actor = $this->requireActor();

        $seriesId = isset($_POST['series_id']) ? sanitize_text_field(wp_unslash((string) $_POST['series_id'])) : '';
        if ($seriesId !== '') {
            $rows = MjAgendaNotes::get_series($seriesId);
            foreach ($rows as $row) {
                $isOwner = $row['author_member_id'] === $actor['member_id'];
                MjAgendaAcl::assert($actor['role'], 'internal_notes', 'delete', $isOwner);
            }
            foreach ($rows as $row) {
                if (class_exists(MjNoteMedia::class)) {
                    MjNoteMedia::delete_for_note((int) $row['id']);
                }
            }
            $result = MjAgendaNotes::delete_series($seriesId);
            if (is_wp_error($result)) {
                wp_send_json_error(array('message' => $result->get_error_message()), 400);
            }
            wp_send_json_success();
        }

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $existing = $id > 0 ? MjAgendaNotes::get($id) : null;
        if (!$existing) {
            wp_send_json_error(array('message' => __('Note introuvable.', 'mj-member')), 404);
        }

        $isOwner = $existing['author_member_id'] === $actor['member_id'];
        MjAgendaAcl::assert($actor['role'], 'internal_notes', 'delete', $isOwner);

        if (class_exists(MjNoteMedia::class)) {
            MjNoteMedia::delete_for_note($id);
        }

        $result = MjAgendaNotes::delete($id);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 400);
        }

        wp_send_json_success();
    }

    /**
     * @return string[] one or more Y-m-d dates, built from either a single
     *  'note_date' field or a JSON-encoded 'dates' array (multi-date / weekly
     *  recurrence picked client-side, already expanded to concrete dates).
     */
    private static function sanitizeDatesInput($raw): array
    {
        if (is_array($raw)) {
            $decoded = $raw;
        } else {
            $decoded = json_decode(wp_unslash((string) $raw), true);
        }

        if (!is_array($decoded)) {
            $single = self::sanitizeDate($raw);
            return $single !== '' ? array($single) : array();
        }

        $dates = array();
        foreach ($decoded as $value) {
            $date = self::sanitizeDate($value);
            if ($date !== '') {
                $dates[] = $date;
            }
        }

        return array_values(array_unique($dates));
    }

    /**
     * @param array{member_id:int,user_id:int} $actor
     * @return array<string,mixed>
     */
    private function sanitizePayloadFromRequest(array $actor): array
    {
        $memberId = isset($_POST['member_id']) ? (int) $_POST['member_id'] : 0;
        $memberIds = array();
        if (isset($_POST['member_ids'])) {
            $decoded = json_decode(wp_unslash((string) $_POST['member_ids']), true);
            if (is_array($decoded)) {
                $memberIds = $decoded;
            }
        }

        return array(
            'author_member_id' => $actor['member_id'],
            'wp_user_id' => $actor['user_id'],
            'start_time' => isset($_POST['start_time']) ? sanitize_text_field(wp_unslash((string) $_POST['start_time'])) : null,
            'end_time' => isset($_POST['end_time']) ? sanitize_text_field(wp_unslash((string) $_POST['end_time'])) : null,
            'title' => isset($_POST['title']) ? sanitize_text_field(wp_unslash((string) $_POST['title'])) : '',
            'emoji' => isset($_POST['emoji']) ? sanitize_text_field(wp_unslash((string) $_POST['emoji'])) : null,
            'content' => isset($_POST['content']) ? sanitize_textarea_field(wp_unslash((string) $_POST['content'])) : '',
            'color' => isset($_POST['color']) ? sanitize_text_field(wp_unslash((string) $_POST['color'])) : null,
            'note_type_id' => isset($_POST['note_type_id']) ? (int) $_POST['note_type_id'] : null,
            'visibility' => isset($_POST['visibility']) ? sanitize_text_field(wp_unslash((string) $_POST['visibility'])) : MjAgendaNotes::VISIBILITY_STAFF,
            'member_id' => $memberId > 0 ? $memberId : null,
            'assigned_member_ids' => $memberIds,
            'visible_to_assignees' => !empty($_POST['visible_to_assignees']),
            'recurrence_rule' => isset($_POST['recurrence_rule']) ? wp_unslash((string) $_POST['recurrence_rule']) : null,
        );
    }

    // -----------------------------------------------------------------
    // Media
    // -----------------------------------------------------------------

    public function uploadMedia(): void
    {
        $actor = $this->requireActor();
        MjAgendaAcl::assert($actor['role'], 'internal_notes', 'create');

        if (!function_exists('media_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
        }

        if (empty($_FILES['file'])) {
            wp_send_json_error(array('message' => __('Aucun fichier reçu.', 'mj-member')), 400);
        }

        $attachmentId = media_handle_upload('file', 0);
        if (is_wp_error($attachmentId)) {
            wp_send_json_error(array('message' => $attachmentId->get_error_message()), 400);
        }

        wp_send_json_success(array(
            'id' => (int) $attachmentId,
            'url' => (string) wp_get_attachment_image_url((int) $attachmentId, 'medium'),
            'thumbUrl' => (string) wp_get_attachment_image_url((int) $attachmentId, 'thumbnail'),
        ));
    }

    public function deleteMedia(): void
    {
        $actor = $this->requireActor();

        $noteId = isset($_POST['note_id']) ? (int) $_POST['note_id'] : 0;
        $attachmentId = isset($_POST['attachment_id']) ? (int) $_POST['attachment_id'] : 0;
        if ($noteId <= 0 || $attachmentId <= 0) {
            wp_send_json_error(array('message' => __('Paramètres invalides.', 'mj-member')), 400);
        }

        $existing = MjAgendaNotes::get($noteId);
        if (!$existing) {
            wp_send_json_error(array('message' => __('Note introuvable.', 'mj-member')), 404);
        }

        $isOwner = $existing['author_member_id'] === $actor['member_id'];
        MjAgendaAcl::assert($actor['role'], 'internal_notes', 'edit_own', $isOwner);

        global $wpdb;
        $table = mj_member_get_note_media_table_name();
        $wpdb->delete($table, array('note_id' => $noteId, 'attachment_id' => $attachmentId), array('%d', '%d'));

        wp_send_json_success();
    }

    // -----------------------------------------------------------------
    // Assignment: search "jeunes" (not part of the fixed staff list handed
    // to the front-end via localize()/config) to assign to a note, and
    // hydrate the names of already-assigned jeunes an edit form only knows
    // by id.
    // -----------------------------------------------------------------

    public function searchJeunes(): void
    {
        $this->requireActor();

        if (!class_exists(MjMembers::class)) {
            wp_send_json_success(array('members' => array()));
        }

        global $wpdb;
        $table = MjMembers::getTableName(MjMembers::TABLE_NAME);

        $ids = array();
        if (isset($_POST['ids'])) {
            $decoded = json_decode(wp_unslash((string) $_POST['ids']), true);
            if (is_array($decoded)) {
                foreach ($decoded as $value) {
                    $id = (int) $value;
                    if ($id > 0) {
                        $ids[] = $id;
                    }
                }
            }
        }

        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '%d'));
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, first_name, last_name, nickname, role, photo_id, wp_user_id FROM {$table} WHERE id IN ({$placeholders})",
                    $ids
                ),
                ARRAY_A
            );
        } else {
            $term = isset($_POST['search']) ? sanitize_text_field(wp_unslash((string) $_POST['search'])) : '';
            if (mb_strlen($term) < 2) {
                wp_send_json_success(array('members' => array()));
            }

            $like = '%' . $wpdb->esc_like($term) . '%';
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, first_name, last_name, nickname, role, photo_id, wp_user_id FROM {$table}
                    WHERE role = %s AND status = %s AND (first_name LIKE %s OR last_name LIKE %s OR nickname LIKE %s)
                    ORDER BY last_name ASC, first_name ASC, id ASC
                    LIMIT 20",
                    MjRoles::JEUNE,
                    MjMembers::STATUS_ACTIVE,
                    $like,
                    $like,
                    $like
                ),
                ARRAY_A
            );
        }

        $members = array();
        foreach ((array) $rows as $row) {
            $memberId = isset($row['id']) ? (int) $row['id'] : 0;
            if ($memberId <= 0) {
                continue;
            }

            $firstName = isset($row['first_name']) ? sanitize_text_field((string) $row['first_name']) : '';
            $lastName = isset($row['last_name']) ? sanitize_text_field((string) $row['last_name']) : '';
            $nickname = isset($row['nickname']) ? sanitize_text_field((string) $row['nickname']) : '';
            $name = trim($firstName . ' ' . $lastName);
            if ($name === '' && $nickname !== '') {
                $name = $nickname;
            }
            if ($name === '') {
                $name = sprintf(__('Membre #%d', 'mj-member'), $memberId);
            }

            $avatar = function_exists('mj_member_todo_build_avatar_payload')
                ? mj_member_todo_build_avatar_payload($row, $name)
                : array('url' => '', 'initials' => '', 'alt' => '');

            $members[] = array(
                'id' => $memberId,
                'name' => $name,
                'role' => isset($row['role']) ? sanitize_key((string) $row['role']) : MjRoles::JEUNE,
                'avatarUrl' => $avatar['url'],
                'avatarInitials' => $avatar['initials'],
            );
        }

        wp_send_json_success(array('members' => $members));
    }

    // -----------------------------------------------------------------
    // Note types (small lookup table, coordinator/staff managed)
    // -----------------------------------------------------------------

    public function noteTypesList(): void
    {
        $this->requireActor();
        wp_send_json_success(array('types' => MjNoteTypes::get_all()));
    }

    public function noteTypesCreate(): void
    {
        $this->requireNoteTypeManager();

        $result = MjNoteTypes::create(array(
            'label' => sanitize_text_field(wp_unslash((string) ($_POST['label'] ?? ''))),
            'color' => sanitize_text_field(wp_unslash((string) ($_POST['color'] ?? ''))),
            'emoji' => sanitize_text_field(wp_unslash((string) ($_POST['emoji'] ?? ''))),
            'sort_order' => isset($_POST['sort_order']) ? (int) $_POST['sort_order'] : 0,
        ));

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 400);
        }

        wp_send_json_success(array('type' => MjNoteTypes::get((int) $result)));
    }

    public function noteTypesUpdate(): void
    {
        $this->requireNoteTypeManager();

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $result = MjNoteTypes::update($id, array(
            'label' => sanitize_text_field(wp_unslash((string) ($_POST['label'] ?? ''))),
            'color' => sanitize_text_field(wp_unslash((string) ($_POST['color'] ?? ''))),
            'emoji' => sanitize_text_field(wp_unslash((string) ($_POST['emoji'] ?? ''))),
            'sort_order' => isset($_POST['sort_order']) ? (int) $_POST['sort_order'] : 0,
        ));

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 400);
        }

        wp_send_json_success(array('type' => MjNoteTypes::get($id)));
    }

    public function noteTypesDelete(): void
    {
        $this->requireNoteTypeManager();

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $result = MjNoteTypes::delete($id);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 400);
        }

        wp_send_json_success();
    }
}
