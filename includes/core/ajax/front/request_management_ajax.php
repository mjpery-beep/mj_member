<?php

namespace Mj\Member\Core\Ajax\Front;

use Mj\Member\Classes\Crud\MjMembers;
use Mj\Member\Classes\Crud\MjRequestMedia;
use Mj\Member\Classes\Crud\MjRequestNotes;
use Mj\Member\Classes\Crud\MjRequestRooms;
use Mj\Member\Classes\Crud\MjRequestTypes;
use Mj\Member\Classes\Crud\MjRequests;
use Mj\Member\Classes\MjRoles;
use Mj\Member\Core\Contracts\AjaxHandlerInterface;

if (!defined('ABSPATH')) {
    exit;
}

final class RequestManagementController implements AjaxHandlerInterface
{
    private const NONCE = 'mj-request-management';

    public function registerHooks(): void
    {
        add_action('wp_ajax_mj_request_management_bootstrap', array($this, 'bootstrap'));
        add_action('wp_ajax_mj_request_management_create', array($this, 'create'));
        add_action('wp_ajax_mj_request_management_update', array($this, 'update'));
        add_action('wp_ajax_mj_request_management_list_mine', array($this, 'listMine'));
        add_action('wp_ajax_mj_request_management_list_staff', array($this, 'listStaff'));
        add_action('wp_ajax_mj_request_management_change_status', array($this, 'changeStatus'));
        add_action('wp_ajax_mj_request_management_add_note', array($this, 'addNote'));
    }

    public static function localize(): void
    {
        if (function_exists('mj_member_ensure_request_management_tables')) {
            mj_member_ensure_request_management_tables();
        }

        $actor = self::resolveActor();
        if (!$actor) {
            return;
        }

        $rooms = self::formatRooms(MjRequestRooms::get_all());
        $animateurs = self::formatAnimateurs();
        $requestTypes = self::formatRequestTypes(self::filterRequestTypesForActor(MjRequestTypes::get_active(), $actor));

        wp_localize_script('mj-member-request-management', 'mjRequestManagement', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce(self::NONCE),
            'currentMemberId' => $actor['member_id'],
            'isStaff' => $actor['is_staff'],
            'canManageStatuses' => $actor['is_staff'],
            'statusLabels' => MjRequests::get_status_labels(),
            'requestTypes' => $requestTypes,
            'rooms' => $rooms,
            'animateurs' => $animateurs,
            'mine' => self::enrichRequests(MjRequests::get_all(array('member_id' => $actor['member_id'], 'limit' => 200))),
            'staff' => $actor['is_staff'] ? self::enrichRequests(MjRequests::get_all(array('limit' => 300))) : array(),
            'i18n' => array(
                'send' => __('Envoyer la demande', 'mj-member'),
                'pending' => __('En attente', 'mj-member'),
                'approved' => __('Approuvée', 'mj-member'),
                'rejected' => __('Refusée', 'mj-member'),
                'cancelled' => __('Annulée', 'mj-member'),
                'mine' => __('Mes demandes', 'mj-member'),
                'staff' => __('Traitement animateurs', 'mj-member'),
                'save' => __('Enregistrer', 'mj-member'),
                'statusUpdated' => __('Statut mis à jour.', 'mj-member'),
                'noteAdded' => __('Note ajoutée.', 'mj-member'),
            ),
        ));
    }

    public function bootstrap(): void
    {
        $actor = $this->requireActor();
        if (!$actor) {
            return;
        }

        wp_send_json_success(array(
            'mine' => self::enrichRequests(MjRequests::get_all(array('member_id' => $actor['member_id'], 'limit' => 200))),
            'staff' => $actor['is_staff'] ? self::enrichRequests(MjRequests::get_all(array('limit' => 300))) : array(),
            'rooms' => self::formatRooms(MjRequestRooms::get_all()),
            'requestTypes' => self::formatRequestTypes(self::filterRequestTypesForActor(MjRequestTypes::get_active(), $actor)),
            'animateurs' => self::formatAnimateurs(),
        ));
    }

    public function create(): void
    {
        $actor = $this->requireActor();
        if (!$actor) {
            return;
        }

        $payload = $this->sanitizeRequestPayload($_POST, $actor);
        if (is_wp_error($payload)) {
            wp_send_json_error(array('message' => $payload->get_error_message()), 400);
        }
        $payload['member_id'] = $actor['member_id'];
        $payload['status'] = MjRequests::STATUS_PENDING;

        $created = MjRequests::create($payload);
        if (is_wp_error($created)) {
            wp_send_json_error(array('message' => $created->get_error_message()), 400);
        }

        $requestId = (int) $created;
        $uploadResult = $this->persistUploadedMedia($requestId, 'images');
        if (is_wp_error($uploadResult)) {
            wp_send_json_error(array('message' => $uploadResult->get_error_message()), 400);
        }

        $request = MjRequests::get_by_id($requestId);
        wp_send_json_success(array(
            'request' => self::formatRequest($request),
            'mine' => self::enrichRequests(MjRequests::get_all(array('member_id' => $actor['member_id'], 'limit' => 200))),
        ));
    }

    public function update(): void
    {
        $actor = $this->requireActor();
        if (!$actor) {
            return;
        }

        $requestId = isset($_POST['request_id']) ? (int) $_POST['request_id'] : 0;
        $request = MjRequests::get_by_id($requestId);
        if (!$request) {
            wp_send_json_error(array('message' => __('Demande introuvable.', 'mj-member')), 404);
        }

        if ((int) $request->member_id !== $actor['member_id']) {
            wp_send_json_error(array('message' => __('Action non autorisée.', 'mj-member')), 403);
        }

        if ((string) $request->status !== MjRequests::STATUS_PENDING) {
            wp_send_json_error(array('message' => __('Seules les demandes en attente peuvent être éditées.', 'mj-member')), 400);
        }

        $payload = $this->sanitizeRequestPayload($_POST, $actor);
        if (is_wp_error($payload)) {
            wp_send_json_error(array('message' => $payload->get_error_message()), 400);
        }
        $updated = MjRequests::update($requestId, $payload);
        if (is_wp_error($updated)) {
            wp_send_json_error(array('message' => $updated->get_error_message()), 400);
        }

        $uploadResult = $this->persistUploadedMedia($requestId, 'images');
        if (is_wp_error($uploadResult)) {
            wp_send_json_error(array('message' => $uploadResult->get_error_message()), 400);
        }

        wp_send_json_success(array(
            'request' => self::formatRequest(MjRequests::get_by_id($requestId)),
            'mine' => self::enrichRequests(MjRequests::get_all(array('member_id' => $actor['member_id'], 'limit' => 200))),
        ));
    }

    public function listMine(): void
    {
        $actor = $this->requireActor();
        if (!$actor) {
            return;
        }

        wp_send_json_success(array(
            'mine' => self::enrichRequests(MjRequests::get_all(array('member_id' => $actor['member_id'], 'limit' => 200))),
        ));
    }

    public function listStaff(): void
    {
        $actor = $this->requireActor();
        if (!$actor) {
            return;
        }

        if (!$actor['is_staff']) {
            wp_send_json_error(array('message' => __('Accès réservé au staff.', 'mj-member')), 403);
        }

        $status = isset($_POST['status']) ? sanitize_text_field(wp_unslash((string) $_POST['status'])) : '';
        $args = array('limit' => 300);
        if ($status !== '') {
            $args['status'] = $status;
        }

        wp_send_json_success(array('staff' => self::enrichRequests(MjRequests::get_all($args))));
    }

    public function changeStatus(): void
    {
        $actor = $this->requireActor();
        if (!$actor) {
            return;
        }

        if (!$actor['is_staff']) {
            wp_send_json_error(array('message' => __('Accès réservé au staff.', 'mj-member')), 403);
        }

        $requestId = isset($_POST['request_id']) ? (int) $_POST['request_id'] : 0;
        $status = isset($_POST['status']) ? sanitize_text_field(wp_unslash((string) $_POST['status'])) : '';
        $note = isset($_POST['status_note']) ? sanitize_textarea_field(wp_unslash((string) $_POST['status_note'])) : '';

        if (!in_array($status, MjRequests::statuses(), true)) {
            wp_send_json_error(array('message' => __('Statut invalide.', 'mj-member')), 400);
        }

        $request = MjRequests::get_by_id($requestId);
        if (!$request) {
            wp_send_json_error(array('message' => __('Demande introuvable.', 'mj-member')), 404);
        }

        $result = MjRequests::update($requestId, array(
            'status' => $status,
            'status_note' => $note,
            'assigned_to_member_id' => $actor['member_id'],
        ));

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 400);
        }

        if ($note !== '') {
            MjRequestNotes::create(array(
                'request_id' => $requestId,
                'author_member_id' => $actor['member_id'],
                'content' => $note,
            ));
        }

        wp_send_json_success(array(
            'request' => self::formatRequest(MjRequests::get_by_id($requestId)),
            'staff' => self::enrichRequests(MjRequests::get_all(array('limit' => 300))),
        ));
    }

    public function addNote(): void
    {
        $actor = $this->requireActor();
        if (!$actor) {
            return;
        }

        if (!$actor['is_staff']) {
            wp_send_json_error(array('message' => __('Accès réservé au staff.', 'mj-member')), 403);
        }

        $requestId = isset($_POST['request_id']) ? (int) $_POST['request_id'] : 0;
        $content = isset($_POST['content']) ? sanitize_textarea_field(wp_unslash((string) $_POST['content'])) : '';
        if ($requestId <= 0 || $content === '') {
            wp_send_json_error(array('message' => __('Note invalide.', 'mj-member')), 400);
        }

        $created = MjRequestNotes::create(array(
            'request_id' => $requestId,
            'author_member_id' => $actor['member_id'],
            'content' => $content,
        ));

        if (is_wp_error($created)) {
            wp_send_json_error(array('message' => $created->get_error_message()), 400);
        }

        $request = MjRequests::get_by_id($requestId);

        wp_send_json_success(array(
            'request' => self::formatRequest($request),
        ));
    }

    private static function resolveActor(): ?array
    {
        $userId = get_current_user_id();
        if ($userId <= 0) {
            return null;
        }

        $member = MjMembers::getByWpUserId($userId);
        if (!$member) {
            return null;
        }

        $role = isset($member->role) ? (string) $member->role : '';

        return array(
            'member_id' => (int) $member->id,
            'role' => $role,
            'is_staff' => MjRoles::isAnimateurOrCoordinateur($role) || MjRoles::isStaff($role),
            'label' => trim((string) $member->first_name . ' ' . (string) $member->last_name),
        );
    }

    private function requireActor(): ?array
    {
        if (function_exists('mj_member_ensure_request_management_tables')) {
            mj_member_ensure_request_management_tables();
        }

        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash((string) $_POST['nonce'])), self::NONCE)) {
            wp_send_json_error(array('message' => __('Jeton de sécurité invalide.', 'mj-member')), 403);
        }

        $actor = self::resolveActor();
        if (!$actor) {
            wp_send_json_error(array('message' => __('Utilisateur non autorisé.', 'mj-member')), 403);
        }

        return $actor;
    }

    private function sanitizeRequestPayload(array $input, array $actor)
    {
        $roomOptionsRaw = isset($input['room_options_json']) ? wp_unslash((string) $input['room_options_json']) : '[]';
        $materialsRaw = isset($input['materials_json']) ? wp_unslash((string) $input['materials_json']) : '[]';

        $roomOptions = json_decode($roomOptionsRaw, true);
        if (!is_array($roomOptions)) {
            $roomOptions = array();
        }

        $materials = json_decode($materialsRaw, true);
        if (!is_array($materials)) {
            $materials = array();
        }

        $requestType = isset($input['request_type']) ? sanitize_text_field(wp_unslash((string) $input['request_type'])) : '';
        $typeConfig = MjRequestTypes::find_by_key($requestType);
        if (!$typeConfig || empty($typeConfig->is_active)) {
            return new \WP_Error('invalid_request_type', __('Type de demande invalide.', 'mj-member'));
        }

        if (!self::isActorAllowedForType($actor, $typeConfig)) {
            return new \WP_Error('request_type_forbidden', __('Ce type de demande n\'est pas autorisé pour votre rôle.', 'mj-member'));
        }

        $assignedMemberIdsRaw = isset($input['assigned_member_ids_json']) ? wp_unslash((string) $input['assigned_member_ids_json']) : '[]';
        $assignedMemberIds = json_decode($assignedMemberIdsRaw, true);
        if (!is_array($assignedMemberIds)) {
            $assignedMemberIds = array();
        }
        $assignedMemberIds = array_values(array_unique(array_map('absint', $assignedMemberIds)));
        $assignedMemberIds = array_values(array_filter($assignedMemberIds, static function ($id) {
            return $id > 0;
        }));
        $assignedToMemberId = $assignedMemberIds[0] ?? 0;

        $roomId = isset($input['room_id']) ? (int) $input['room_id'] : 0;
        $isOutdoor = !empty($input['is_outdoor']) ? 1 : 0;
        $weekStart = isset($input['week_start']) ? sanitize_text_field(wp_unslash((string) $input['week_start'])) : '';
        $slotDay = isset($input['slot_day']) ? (int) $input['slot_day'] : 0;
        $slotStart = isset($input['slot_start']) ? sanitize_text_field(wp_unslash((string) $input['slot_start'])) : '';
        $slotEnd = isset($input['slot_end']) ? sanitize_text_field(wp_unslash((string) $input['slot_end'])) : '';

        $slotsRaw = isset($input['slots_json']) ? wp_unslash((string) $input['slots_json']) : '[]';
        $slots = json_decode($slotsRaw, true);
        if (!is_array($slots)) {
            $slots = array();
        }
        if (empty($slots) && $weekStart !== '') {
            $base = strtotime($weekStart);
            $slots = array(array(
                'date' => $base !== false ? wp_date('Y-m-d', $base + ($slotDay * DAY_IN_SECONDS)) : '',
                'start' => $slotStart,
                'end' => $slotEnd,
            ));
        }

        if (empty($typeConfig->allows_location)) {
            $roomId = 0;
            $isOutdoor = 0;
            $roomOptions = array();
        }

        if (empty($typeConfig->allows_materials)) {
            $materials = array();
        }

        if (empty($typeConfig->allows_date)) {
            $weekStart = '';
            $slotDay = 0;
            $slotStart = '';
            $slotEnd = '';
            $slots = array();
        }

        return array(
            'assigned_to_member_id' => $assignedToMemberId,
            'assigned_member_ids' => $assignedMemberIds,
            'request_type' => $requestType,
            'room_id' => $roomId,
            'is_outdoor' => $isOutdoor,
            'title' => isset($input['title']) ? sanitize_text_field(wp_unslash((string) $input['title'])) : '',
            'description' => isset($input['description']) ? sanitize_textarea_field(wp_unslash((string) $input['description'])) : '',
            'age_range' => isset($input['age_range']) ? sanitize_text_field(wp_unslash((string) $input['age_range'])) : '',
            'week_start' => $weekStart,
            'slot_day' => $slotDay,
            'slot_start' => $slotStart,
            'slot_end' => $slotEnd,
            'slots' => $slots,
            'room_options_json' => $roomOptions,
            'materials_json' => $materials,
        );
    }

    private static function formatRequestTypes(array $types): array
    {
        $result = array();
        foreach ($types as $type) {
            $result[] = array(
                'key' => (string) $type->type_key,
                'emoji' => isset($type->emoji) ? (string) $type->emoji : '',
                'color' => isset($type->color) ? (string) $type->color : '',
                'label' => (string) $type->label,
                'description' => (string) $type->description,
                'descriptionHtml' => wp_kses_post((string) $type->description),
                'options' => array(
                    'allowsLocation' => !empty($type->allows_location),
                    'allowsMaterials' => !empty($type->allows_materials),
                    'allowsDate' => !empty($type->allows_date),
                    'allowsMultipleDates' => !empty($type->allows_multiple_dates),
                    'requiresAnimateur' => !empty($type->requires_animateur),
                    'visibilityMode' => MjRequestTypes::normalize_visibility_mode((string) ($type->visibility_mode ?? 'public')),
                    'allowedRoles' => MjRequestTypes::decode_allowed_roles((string) ($type->allowed_roles_json ?? '[]')),
                ),
            );
        }

        return $result;
    }

    private static function filterRequestTypesForActor(array $types, array $actor): array
    {
        $filtered = array();
        foreach ($types as $type) {
            if (!is_object($type) || empty($type->is_active)) {
                continue;
            }

            if (!self::isActorAllowedForType($actor, $type)) {
                continue;
            }

            $filtered[] = $type;
        }

        return $filtered;
    }

    private static function isActorAllowedForType(array $actor, object $type): bool
    {
        $mode = MjRequestTypes::normalize_visibility_mode((string) ($type->visibility_mode ?? 'public'));
        if ($mode !== 'restricted') {
            return true;
        }

        $allowedRoles = MjRequestTypes::decode_allowed_roles((string) ($type->allowed_roles_json ?? '[]'));
        if (empty($allowedRoles)) {
            return false;
        }

        $actorRole = MjRoles::normalize((string) ($actor['role'] ?? ''));
        return in_array($actorRole, $allowedRoles, true);
    }

    private function persistUploadedMedia(int $requestId, string $fieldName)
    {
        if (empty($_FILES[$fieldName])) {
            return true;
        }

        if (!function_exists('media_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        $files = $_FILES[$fieldName];
        $attachments = array();

        // Normalize either single or multiple file input.
        if (is_array($files['name'])) {
            $count = count($files['name']);
            for ($index = 0; $index < $count; $index++) {
                if ((int) $files['error'][$index] !== UPLOAD_ERR_OK) {
                    continue;
                }

                $single = array(
                    'name' => $files['name'][$index],
                    'type' => $files['type'][$index],
                    'tmp_name' => $files['tmp_name'][$index],
                    'error' => $files['error'][$index],
                    'size' => $files['size'][$index],
                );

                $_FILES['mj_request_upload_single'] = $single;
                $attachmentId = media_handle_upload('mj_request_upload_single', 0);
                if (is_wp_error($attachmentId)) {
                    return $attachmentId;
                }

                $attachments[] = (int) $attachmentId;
            }
            unset($_FILES['mj_request_upload_single']);
        } elseif ((int) $files['error'] === UPLOAD_ERR_OK) {
            $attachmentId = media_handle_upload($fieldName, 0);
            if (is_wp_error($attachmentId)) {
                return $attachmentId;
            }
            $attachments[] = (int) $attachmentId;
        }

        foreach ($attachments as $attachmentId) {
            MjRequestMedia::create(array(
                'request_id' => $requestId,
                'attachment_id' => $attachmentId,
            ));
        }

        return true;
    }

    private static function enrichRequests(array $requests): array
    {
        $formatted = array();
        foreach ($requests as $request) {
            $formatted[] = self::formatRequest($request);
        }

        return $formatted;
    }

    private static function formatRequest(?object $request): ?array
    {
        if (!$request) {
            return null;
        }

        $member = MjMembers::getById((int) $request->member_id);
        $assignee = !empty($request->assigned_to_member_id) ? MjMembers::getById((int) $request->assigned_to_member_id) : null;
        $notes = MjRequestNotes::get_all(array('request_id' => (int) $request->id));
        $mediaRows = MjRequestMedia::get_all(array('request_id' => (int) $request->id));

        $media = array();
        foreach ($mediaRows as $row) {
            $attachmentId = isset($row->attachment_id) ? (int) $row->attachment_id : 0;
            if ($attachmentId <= 0) {
                continue;
            }
            $url = wp_get_attachment_url($attachmentId);
            if (!$url) {
                continue;
            }
            $media[] = array(
                'id' => (int) $row->id,
                'attachmentId' => $attachmentId,
                'url' => $url,
            );
        }

        $assignedMemberIds = MjRequests::get_assigned_member_ids($request);
        $assignedNames = array();
        foreach ($assignedMemberIds as $assignedId) {
            $assignedMember = MjMembers::getById($assignedId);
            if ($assignedMember) {
                $assignedNames[] = trim((string) $assignedMember->first_name . ' ' . (string) $assignedMember->last_name);
            }
        }

        return array(
            'id' => (int) $request->id,
            'memberId' => (int) $request->member_id,
            'memberName' => $member ? trim((string) $member->first_name . ' ' . (string) $member->last_name) : '',
            'assignedToMemberId' => (int) $request->assigned_to_member_id,
            'assignedToName' => $assignee ? trim((string) $assignee->first_name . ' ' . (string) $assignee->last_name) : '',
            'assignedMemberIds' => $assignedMemberIds,
            'assignedNames' => $assignedNames,
            'requestType' => (string) $request->request_type,
            'status' => (string) $request->status,
            'statusLabel' => MjRequests::get_status_labels()[(string) $request->status] ?? (string) $request->status,
            'roomId' => (int) $request->room_id,
            'isOutdoor' => !empty($request->is_outdoor),
            'title' => (string) $request->title,
            'description' => (string) $request->description,
            'ageRange' => (string) $request->age_range,
            'weekStart' => (string) $request->week_start,
            'slotDay' => (int) $request->slot_day,
            'slotStart' => (string) $request->slot_start,
            'slotEnd' => (string) $request->slot_end,
            'slots' => array_map(
                static function ($slot) {
                    return array(
                        'date' => (string) $slot['date'],
                        'start' => (string) $slot['start'],
                        'end' => (string) $slot['end'],
                    );
                },
                MjRequests::get_slots($request)
            ),
            'roomOptions' => json_decode((string) $request->room_options_json, true) ?: array(),
            'materials' => json_decode((string) $request->materials_json, true) ?: array(),
            'statusNote' => (string) $request->status_note,
            'notes' => array_map(
                static function ($note) {
                    $author = MjMembers::getById((int) $note->author_member_id);
                    return array(
                        'id' => (int) $note->id,
                        'authorMemberId' => (int) $note->author_member_id,
                        'authorName' => $author ? trim((string) $author->first_name . ' ' . (string) $author->last_name) : '',
                        'content' => (string) $note->content,
                        'createdAt' => (string) $note->created_at,
                    );
                },
                $notes
            ),
            'media' => $media,
            'createdAt' => (string) $request->created_at,
            'updatedAt' => (string) $request->updated_at,
        );
    }

    private static function formatRooms(array $rooms): array
    {
        return array_map(
            static function ($room) {
                $photoIds = json_decode((string) $room->photo_ids_json, true);
                if (!is_array($photoIds)) {
                    $photoIds = array();
                }

                $optionsRaw = json_decode((string) $room->options_json, true);
                $materialsRaw = json_decode((string) $room->materials_json, true);
                $optionsDetailed = self::normalizeRoomCatalogItems($optionsRaw);
                $materialsDetailed = self::normalizeRoomCatalogItems($materialsRaw);

                $photoUrls = array();
                foreach ($photoIds as $photoId) {
                    $url = wp_get_attachment_url((int) $photoId);
                    if ($url) {
                        $photoUrls[] = $url;
                    }
                }

                return array(
                    'id' => (int) $room->id,
                    'emoji' => isset($room->emoji) ? (string) $room->emoji : '',
                    'name' => (string) $room->name,
                    'description' => (string) $room->description,
                    'descriptionHtml' => wp_kses_post((string) $room->description),
                    'capacity' => (int) $room->capacity,
                    'options' => array_map(static function ($entry) {
                        return (string) $entry['title'];
                    }, $optionsDetailed),
                    'materials' => array_map(static function ($entry) {
                        return (string) $entry['title'];
                    }, $materialsDetailed),
                    'optionsDetailed' => $optionsDetailed,
                    'materialsDetailed' => $materialsDetailed,
                    'photoUrls' => $photoUrls,
                    'planId' => (int) $room->plan_id,
                );
            },
            $rooms
        );
    }

    /**
     * @param mixed $items
     * @return array<int,array{title:string,emoji:string,photoId:int,photoUrl:string}>
     */
    private static function normalizeRoomCatalogItems($items): array
    {
        if (!is_array($items)) {
            return array();
        }

        $result = array();
        foreach ($items as $item) {
            $title = '';
            $emoji = '';
            $photoId = 0;

            if (is_scalar($item) || (is_object($item) && method_exists($item, '__toString'))) {
                $title = sanitize_text_field((string) $item);
            } elseif (is_array($item)) {
                $title = sanitize_text_field((string) ($item['title'] ?? ($item['label'] ?? '')));
                $emoji = sanitize_text_field((string) ($item['emoji'] ?? ''));
                $photoId = (int) ($item['photo_id'] ?? ($item['photoId'] ?? 0));
            }

            if ($title === '') {
                continue;
            }

            $url = '';
            if ($photoId > 0) {
                $attachmentUrl = wp_get_attachment_image_url($photoId, 'thumbnail');
                $url = $attachmentUrl ? (string) $attachmentUrl : '';
            }

            $result[] = array(
                'title' => $title,
                'emoji' => $emoji,
                'photoId' => max(0, $photoId),
                'photoUrl' => $url,
            );
        }

        return $result;
    }

    private static function formatAnimateurs(): array
    {
        $rows = MjMembers::get_all(array(
            'filters' => array(
                'roles' => array(MjRoles::ANIMATEUR, MjRoles::COORDINATEUR),
            ),
            'limit' => 300,
        ));

        $result = array();
        foreach ($rows as $row) {
            if ((string) $row->status !== 'active') {
                continue;
            }

            $result[] = array(
                'id' => (int) $row->id,
                'name' => trim((string) $row->first_name . ' ' . (string) $row->last_name),
                'role' => (string) $row->role,
            );
        }

        return $result;
    }
}
