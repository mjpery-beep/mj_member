<?php
/**
 * AJAX handlers for Registration Manager widget
 *
 * @package MjMember
 */

namespace Mj\Member\Core\Ajax\Admin;

use Mj\Member\Core\Contracts\AjaxHandlerInterface;
use Mj\Member\Classes\Crud\MjEvents;
use Mj\Member\Classes\Crud\MjEventRegistrations;
use Mj\Member\Classes\Crud\MjEventAttendance;
use Mj\Member\Classes\Crud\MjEventOccurrences;
use Mj\Member\Classes\Crud\MjEventAnimateurs;
use Mj\Member\Classes\Crud\MjEventVolunteers;
use Mj\Member\Classes\Crud\MjEventLocations;
use Mj\Member\Classes\Crud\MjEventLocationLinks;
use Mj\Member\Classes\Crud\MjEventPhotos;
use Mj\Member\Classes\Crud\MjContactMessages;
use Mj\Member\Classes\Crud\MjIdeas;
use Mj\Member\Classes\Crud\MjIdeaVotes;
use Mj\Member\Classes\Crud\MjTestimonials;
use Mj\Member\Classes\Crud\MjTestimonialComments;
use Mj\Member\Classes\Crud\MjTestimonialReactions;
use Mj\Member\Classes\Crud\MjMembers;
use Mj\Member\Classes\Crud\MjNotifications;
use Mj\Member\Classes\Crud\MjNotificationRecipients;
use Mj\Member\Classes\Crud\MjBadges;
use Mj\Member\Classes\Crud\MjMemberBadges;
use Mj\Member\Classes\Crud\MjBadgeCriteria;
use Mj\Member\Classes\Crud\MjMemberBadgeCriteria;
use Mj\Member\Classes\Crud\MjMemberXp;
use Mj\Member\Classes\Crud\MjMemberCoins;
use Mj\Member\Classes\Crud\MjTrophies;
use Mj\Member\Classes\Crud\MjMemberTrophies;
use Mj\Member\Classes\Crud\MjActionTypes;
use Mj\Member\Classes\Crud\MjMemberActions;
use Mj\Member\Classes\Crud\MjLevels;
use Mj\Member\Classes\Crud\MjLeaveTypes;
use Mj\Member\Classes\Crud\MjLeaveQuotas;
use Mj\Member\Classes\Crud\MjEmployeeDocuments;
use Mj\Member\Classes\Forms\EventFormDataMapper;
use Mj\Member\Classes\Forms\EventFormOptionsBuilder;
use Mj\Member\Classes\MjEventSchedule;
use Mj\Member\Classes\MjNextcloud;
use Mj\Member\Classes\MjPayments;
use Mj\Member\Classes\MjRoles;
use Mj\Member\Classes\MjStripeConfig;
use Mj\Member\Classes\MjTrophyService;
use Mj\Member\Core\Config;
use Mj\Member\Classes\Value\EventLocationData;
use Mj\Member\Classes\MjOpenAIClient;
use Mj\Member\Classes\MjSocialMediaPublisher;
use Mj\Member\Classes\View\EventPage\EventPageViewBuilder;
use DateTime;

if (!defined('ABSPATH')) {
    exit;
}

// Include social media publishing handler
require_once __DIR__ . '/publish-event.php';
require_once __DIR__ . '/nextcloud-media.php';

final class RegistrationManagerController implements AjaxHandlerInterface
{
    private static ?self $currentInstance = null;

    public static function getInstance(): self
    {
        if (self::$currentInstance === null) {
            throw new \RuntimeException('RegistrationManagerController not yet initialized.');
        }
        return self::$currentInstance;
    }

    /**
     * Allows global forwarding stubs to call private methods via this public bridge.
     * @internal
     */
    public function callInternal(string $method, array $args): mixed
    {
        return $this->$method(...$args);
    }

    public function registerHooks(): void
    {
        self::$currentInstance = $this;

        add_action('wp_ajax_mj_regmgr_get_events', [$this, 'getEvents']);
        add_action('wp_ajax_mj_regmgr_get_event_details', [$this, 'getEventDetails']);
        add_action('wp_ajax_mj_regmgr_get_event_photos', [$this, 'getEventPhotos']);
        add_action('wp_ajax_mj_regmgr_upload_event_photo', [$this, 'uploadEventPhoto']);
        add_action('wp_ajax_mj_regmgr_get_event_editor', [$this, 'getEventEditor']);
        add_action('wp_ajax_mj_regmgr_update_event', [$this, 'updateEvent']);
        add_action('wp_ajax_mj_regmgr_create_event', [$this, 'createEvent']);
        add_action('wp_ajax_mj_regmgr_delete_event', [$this, 'deleteEvent']);
        add_action('wp_ajax_mj_regmgr_get_registrations', [$this, 'getRegistrations']);
        add_action('wp_ajax_mj_regmgr_search_members', [$this, 'searchMembers']);
        add_action('wp_ajax_mj_regmgr_add_registration', [$this, 'addRegistration']);
        add_action('wp_ajax_mj_regmgr_update_registration', [$this, 'updateRegistration']);
        add_action('wp_ajax_mj_regmgr_delete_registration', [$this, 'deleteRegistration']);
        add_action('wp_ajax_mj_regmgr_update_attendance', [$this, 'updateAttendance']);
        add_action('wp_ajax_mj_regmgr_bulk_attendance', [$this, 'bulkAttendance']);
        add_action('wp_ajax_mj_regmgr_validate_payment', [$this, 'validatePayment']);
        add_action('wp_ajax_mj_regmgr_cancel_payment', [$this, 'cancelPayment']);
        add_action('wp_ajax_mj_regmgr_create_quick_member', [$this, 'createQuickMember']);
        add_action('wp_ajax_mj_regmgr_get_member_notes', [$this, 'getMemberNotes']);
        add_action('wp_ajax_mj_regmgr_save_member_note', [$this, 'saveMemberNote']);
        add_action('wp_ajax_mj_regmgr_delete_member_note', [$this, 'deleteMemberNote']);
        add_action('wp_ajax_mj_regmgr_get_payment_qr', [$this, 'getPaymentQr']);
        add_action('wp_ajax_mj_regmgr_update_occurrences', [$this, 'updateOccurrences']);
        add_action('wp_ajax_mj_regmgr_save_event_occurrences', [$this, 'saveEventOccurrences']);
        add_action('wp_ajax_mj_regmgr_get_location', [$this, 'getLocation']);
        add_action('wp_ajax_mj_regmgr_save_location', [$this, 'saveLocation']);

        // Members management actions
        add_action('wp_ajax_mj_regmgr_get_members', [$this, 'getMembers']);
        add_action('wp_ajax_mj_regmgr_get_member_details', [$this, 'getMemberDetails']);
        add_action('wp_ajax_mj_regmgr_update_member', [$this, 'updateMember']);
        add_action('wp_ajax_mj_regmgr_update_member_trusted_status', [$this, 'updateMemberTrustedStatus']);
        add_action('wp_ajax_mj_regmgr_get_member_registrations', [$this, 'getMemberRegistrations']);
        add_action('wp_ajax_mj_regmgr_update_registration_occurrences', [$this, 'updateRegistrationOccurrences']);
        add_action('wp_ajax_mj_regmgr_send_registration_contract', [$this, 'sendRegistrationContract']);
        add_action('wp_ajax_mj_regmgr_download_registration_contract_pdf', [$this, 'downloadRegistrationContractPdf']);
        add_action('wp_ajax_mj_regmgr_mark_membership_paid', [$this, 'markMembershipPaid']);
        add_action('wp_ajax_mj_regmgr_create_membership_payment_link', [$this, 'createMembershipPaymentLink']);
        add_action('wp_ajax_mj_regmgr_update_member_idea', [$this, 'updateMemberIdea']);
        add_action('wp_ajax_mj_regmgr_delete_member_idea', [$this, 'deleteMemberIdea']);
        add_action('wp_ajax_mj_regmgr_update_member_photo', [$this, 'updateMemberPhoto']);
        add_action('wp_ajax_mj_regmgr_delete_member_photo', [$this, 'deleteMemberPhoto']);
        add_action('wp_ajax_mj_regmgr_capture_member_photo', [$this, 'captureMemberPhoto']);
        add_action('wp_ajax_mj_regmgr_create_member_message', [$this, 'createMemberMessage']);
        add_action('wp_ajax_mj_regmgr_delete_member_message', [$this, 'deleteMemberMessage']);
        add_action('wp_ajax_mj_regmgr_update_member_notification', [$this, 'updateMemberNotification']);
        add_action('wp_ajax_mj_regmgr_delete_member_notification', [$this, 'deleteMemberNotification']);
        add_action('wp_ajax_mj_regmgr_reset_member_password', [$this, 'resetMemberPassword']);
        add_action('wp_ajax_mj_regmgr_create_member_nextcloud_login', [$this, 'createMemberNextcloudLogin']);
        add_action('wp_ajax_mj_regmgr_delete_member', [$this, 'deleteMember']);
        add_action('wp_ajax_mj_regmgr_sync_member_badge', [$this, 'syncMemberBadge']);
        add_action('wp_ajax_mj_regmgr_adjust_member_xp', [$this, 'adjustMemberXp']);
        add_action('wp_ajax_mj_regmgr_toggle_member_trophy', [$this, 'toggleMemberTrophy']);
        add_action('wp_ajax_mj_regmgr_award_member_action', [$this, 'awardMemberAction']);
        add_action('wp_ajax_mj_regmgr_delete_member_testimonial', [$this, 'deleteMemberTestimonial']);
        add_action('wp_ajax_mj_regmgr_update_member_testimonial_status', [$this, 'updateMemberTestimonialStatus']);
        add_action('wp_ajax_mj_regmgr_toggle_testimonial_featured', [$this, 'toggleTestimonialFeatured']);
        add_action('wp_ajax_mj_regmgr_edit_testimonial_content', [$this, 'editTestimonialContent']);
        add_action('wp_ajax_mj_regmgr_add_testimonial_comment', [$this, 'addTestimonialComment']);
        add_action('wp_ajax_mj_regmgr_edit_testimonial_comment', [$this, 'editTestimonialComment']);
        add_action('wp_ajax_mj_regmgr_delete_testimonial_comment', [$this, 'deleteTestimonialComment']);
        add_action('wp_ajax_mj_regmgr_add_testimonial_reaction', [$this, 'addTestimonialReaction']);
        add_action('wp_ajax_mj_regmgr_remove_testimonial_reaction', [$this, 'removeTestimonialReaction']);
        add_action('wp_ajax_mj_regmgr_update_social_link', [$this, 'updateSocialLink']);
        add_action('wp_ajax_mj_regmgr_update_member_leave_quotas', [$this, 'updateMemberLeaveQuotas']);
        add_action('wp_ajax_mj_regmgr_save_member_work_schedule', [$this, 'saveMemberWorkSchedule']);
        add_action('wp_ajax_mj_regmgr_delete_member_work_schedule', [$this, 'deleteMemberWorkSchedule']);
        add_action('wp_ajax_mj_regmgr_save_member_dynfields', [$this, 'saveMemberDynfields']);

        // Employee documents actions
        add_action('wp_ajax_mj_regmgr_get_employee_documents', [$this, 'getEmployeeDocuments']);
        add_action('wp_ajax_mj_regmgr_upload_employee_document', [$this, 'uploadEmployeeDocument']);
        add_action('wp_ajax_mj_regmgr_update_employee_document', [$this, 'updateEmployeeDocument']);
        add_action('wp_ajax_mj_regmgr_delete_employee_document', [$this, 'deleteEmployeeDocument']);
        add_action('wp_ajax_mj_regmgr_download_employee_document', [$this, 'downloadEmployeeDocument']);

        // Job profile
        add_action('wp_ajax_mj_regmgr_save_job_profile', [$this, 'saveJobProfile']);

        // Favorites actions
        add_action('wp_ajax_mj_regmgr_get_favorites', [$this, 'getFavorites']);
        add_action('wp_ajax_mj_regmgr_toggle_favorite', [$this, 'toggleFavorite']);

        // AI text generation
        add_action('wp_ajax_mj_regmgr_generate_ai_text', [$this, 'generateAiText']);

        // Social media publishing
        add_action('wp_ajax_mj_regmgr_publish_event', [$this, 'publishEvent']);
    }

    /**
     * Delegate to the global mj_regmgr_publish_event() defined in publish-event.php.
     */
    public function publishEvent(): void
    {
        mj_regmgr_publish_event();
    }

    /**
     * Verify nonce and check user permissions
     * 
     * @return array|false Member data if authorized, false otherwise
     */
    private function verifyRequest() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mj-registration-manager')) {
            wp_send_json_error(array('message' => __('Vérification de sécurité échouée.', 'mj-member')), 403);
            return false;
        }

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Vous devez être connecté.', 'mj-member')), 401);
            return false;
        }

        $current_user_id = get_current_user_id();
        $member = MjMembers::getByWpUserId($current_user_id);

        if (!$member) {
            wp_send_json_error(array('message' => __('Profil membre introuvable.', 'mj-member')), 403);
            return false;
        }

        $member_role = isset($member->role) ? $member->role : '';
        $allowed_roles = array(MjRoles::ANIMATEUR, MjRoles::BENEVOLE, MjRoles::COORDINATEUR);

        if (!in_array($member_role, $allowed_roles, true) && !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permissions insuffisantes.', 'mj-member')), 403);
            return false;
        }

        return array(
            'member' => $member,
            'member_id' => isset($member->id) ? (int) $member->id : 0,
            'role' => $member_role,
            'is_coordinateur' => $member_role === MjRoles::COORDINATEUR || current_user_can('manage_options'),
        );
    }

    /**
     * Get member avatar URL
     * 
     * @param int $member_id Member ID
     * @return string Avatar URL or empty string
     */
    private function getMemberAvatarUrl($member_id) {
        $member = MjMembers::getById($member_id);
        if (!$member) {
            return '';
        }

        // Check if member has photo_id (primary) or avatar_id (fallback)
        $photo_id = isset($member->photo_id) ? (int) $member->photo_id : 0;
        if ($photo_id > 0) {
            $url = wp_get_attachment_image_url($photo_id, 'thumbnail');
            if ($url) {
                return $url;
            }
        }

        // Check avatar_id as fallback
        $avatar_id = isset($member->avatar_id) ? (int) $member->avatar_id : 0;
        if ($avatar_id > 0) {
            $url = wp_get_attachment_image_url($avatar_id, 'thumbnail');
            if ($url) {
                return $url;
            }
        }

        // Fallback to default avatar
        $default_avatar_id = (int) get_option('mj_login_default_avatar_id', 0);
        if ($default_avatar_id > 0) {
            $url = wp_get_attachment_image_url($default_avatar_id, 'thumbnail');
            if ($url) {
                return $url;
            }
        }

        return '';
    }

    /**
     * Normalize a boolean-like value coming from the frontend payload.
     *
     * @param mixed $value Raw value
     * @param bool $default Fallback when value cannot be interpreted
     * @return bool
     */
    private function toBool($value, $default = false) {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (int) $value === 1;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, array('1', 'true', 'yes', 'on'), true)) {
                return true;
            }
            if (in_array($normalized, array('0', 'false', 'no', 'off', ''), true)) {
                return false;
            }
        }

        return $default;
    }

    /**
     * Retourne un emoji normalisé à partir d'un événement ou d'un tableau associatif.
     *
     * @param object|array|null $event Source contenant éventuellement une clé/propriété "emoji"
     * @return string Emoji nettoyé (16 caractères max) ou chaîne vide
     */
    private function getEventEmojiValue($event) {
        $raw = '';

        if (is_array($event) && isset($event['emoji'])) {
            $raw = $event['emoji'];
        } elseif (is_object($event) && isset($event->emoji)) {
            $raw = $event->emoji;
        }

        if (!is_scalar($raw)) {
            return '';
        }

        $candidate = wp_check_invalid_utf8((string) $raw);
        if ($candidate === '') {
            return '';
        }

        $candidate = wp_strip_all_tags($candidate, false);
        $candidate = preg_replace('/[\x00-\x1F\x7F]+/', '', $candidate);
        if (!is_string($candidate)) {
            return '';
        }

        $candidate = trim($candidate);
        if ($candidate === '') {
            return '';
        }

        if (function_exists('wp_html_excerpt')) {
            $candidate = wp_html_excerpt($candidate, 16, '');
        } elseif (function_exists('mb_substr')) {
            $candidate = mb_substr($candidate, 0, 16);
        } else {
            $candidate = substr($candidate, 0, 16);
        }

        return trim($candidate);
    }

    /**
     * Prépare les données d'un événement pour la sidebar.
     *
     * @param object|null $event
     * @param array<string,string>|null $type_labels
     * @param array<string,string>|null $status_labels
     * @return array<string,mixed>|null
     */
    private function buildEventSidebarItem($event, $type_labels = null, $status_labels = null) {
        if (!$event || !isset($event->id)) {
            return null;
        }

        if ($type_labels === null) {
            $type_labels = MjEvents::get_type_labels();
        }

        if ($status_labels === null) {
            $status_labels = MjEvents::get_status_labels();
        }

        $event_id = (int) $event->id;
        $type_key = isset($event->type) ? sanitize_key((string) $event->type) : '';
        $status_key = isset($event->status) ? sanitize_key((string) $event->status) : '';

        $schedule_mode = isset($event->schedule_mode) ? sanitize_key((string) $event->schedule_mode) : 'fixed';
        if ($schedule_mode === '') {
            $schedule_mode = 'fixed';
        }

        $occurrence_mode = isset($event->occurrence_selection_mode) ? sanitize_key((string) $event->occurrence_selection_mode) : 'member_choice';
        if (!in_array($occurrence_mode, array('member_choice', 'all_occurrences'), true)) {
            $occurrence_mode = 'member_choice';
        }

        $registrations_count = MjEventRegistrations::count(array('event_id' => $event_id));

        $schedule_info = $this->buildEventScheduleInfo($event, $schedule_mode);

        $registration_payload = $this->decodeJsonField(isset($event->registration_payload) ? $event->registration_payload : array());
        $attendance_show_all_members = !empty($registration_payload['attendance_show_all_members']);
        if (!$attendance_show_all_members && isset($event->attendance_show_all_members)) {
            $attendance_show_all_members = !empty($event->attendance_show_all_members);
        }

        $emoji_value = $this->getEventEmojiValue($event);

        return array(
            'id' => $event_id,
            'title' => isset($event->title) ? (string) $event->title : '',
            'emoji' => $emoji_value,
            'type' => $type_key,
            'typeLabel' => isset($type_labels[$type_key]) ? $type_labels[$type_key] : ($type_key !== '' ? $type_key : ''),
            'status' => $status_key,
            'statusLabel' => isset($status_labels[$status_key]) ? $status_labels[$status_key] : ($status_key !== '' ? $status_key : ''),
            'dateDebut' => isset($event->date_debut) ? (string) $event->date_debut : '',
            'dateFin' => isset($event->date_fin) ? (string) $event->date_fin : '',
            'dateDebutFormatted' => $this->formatDate(isset($event->date_debut) ? $event->date_debut : ''),
            'dateFinFormatted' => $this->formatDate(isset($event->date_fin) ? $event->date_fin : ''),
            'coverId' => isset($event->cover_id) ? (int) $event->cover_id : 0,
            'coverUrl' => $this->getEventCoverUrl($event, 'thumbnail'),
            'accentColor' => isset($event->accent_color) ? (string) $event->accent_color : '',
            'registrationsCount' => $registrations_count,
            'capacityTotal' => isset($event->capacity_total) ? (int) $event->capacity_total : 0,
            'prix' => isset($event->prix) ? (float) $event->prix : 0.0,
            'scheduleMode' => $schedule_mode,
            'scheduleSummary' => isset($schedule_info['summary']) ? $schedule_info['summary'] : '',
            'scheduleDetail' => isset($schedule_info['detail']) ? $schedule_info['detail'] : '',
            'freeParticipation' => !empty($event->free_participation),
            'occurrenceSelectionMode' => $occurrence_mode,
            'attendanceShowAllMembers' => $attendance_show_all_members,
        );
    }

    private function formatDatetimeCompact($datetime_value) {
        if (!is_string($datetime_value) || $datetime_value === '') {
            return '';
        }
        $timestamp = strtotime($datetime_value);
        if ($timestamp === false) {
            return '';
        }
        return wp_date('d/m H:i', $timestamp);
    }

    private function formatDateCompact($datetime_value) {
        if (!is_string($datetime_value) || $datetime_value === '') {
            return '';
        }
        $timestamp = strtotime($datetime_value);
        if ($timestamp === false) {
            return '';
        }
        return wp_date('d/m', $timestamp);
    }

    private function formatTimeCompact($datetime_value) {
        if (!is_string($datetime_value) || $datetime_value === '') {
            return '';
        }
        $timestamp = strtotime($datetime_value);
        if ($timestamp === false) {
            return '';
        }
        return wp_date('H:i', $timestamp);
    }

    private function occurrenceStatusFromFront($status) {
        $candidate = sanitize_key((string) $status);
        switch ($candidate) {
            case 'confirmed':
                return MjEventOccurrences::STATUS_ACTIVE;
            case 'cancelled':
                return MjEventOccurrences::STATUS_ANNULE;
            case 'postponed':
                return MjEventOccurrences::STATUS_REPORTE;
            case 'planned':
            case 'pending':
            default:
                return MjEventOccurrences::STATUS_A_CONFIRMER;
        }
    }

    private function occurrenceStatusToFront($status) {
        $candidate = sanitize_key((string) $status);
        switch ($candidate) {
            case MjEventOccurrences::STATUS_ACTIVE:
                return 'confirmed';
            case MjEventOccurrences::STATUS_ANNULE:
                return 'cancelled';
            case MjEventOccurrences::STATUS_REPORTE:
                return 'planned';
            case MjEventOccurrences::STATUS_A_CONFIRMER:
            default:
                return 'planned';
        }
    }

    private function sanitizeTimeValue($value) {
        if (!is_string($value)) {
            return '';
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }
        if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $trimmed)) {
            return $trimmed;
        }

        if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d$/', $trimmed)) {
            return substr($trimmed, 0, 5);
        }

        return '';
    }

    private function sanitizeDateValue($value) {
        if (!is_string($value)) {
            return '';
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $trimmed)) {
            return '';
        }

        return $trimmed;
    }

    private function sanitizeOccurrenceGeneratorPlan($input) {
        if (!is_array($input)) {
            return array();
        }

        $known_keys = array(
            'mode',
            'frequency',
            'startDate',
            'startDateISO',
            'endDate',
            'endDateISO',
            'startTime',
            'endTime',
            'days',
            'overrides',
            'timeOverrides',
            'monthlyOrdinal',
            'monthlyWeekday',
            'explicitStart',
            '_explicitStart',
            'version',
        );

        $has_any = false;
        foreach ($known_keys as $key) {
            if (array_key_exists($key, $input)) {
                $has_any = true;
                break;
            }
        }

        if (!$has_any) {
            return array();
        }

        $weekday_keys = array('mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun');

        $mode = isset($input['mode']) ? sanitize_key($input['mode']) : '';
        if (!in_array($mode, array('weekly', 'monthly', 'range', 'custom'), true)) {
            $mode = 'weekly';
        }

        $frequency = isset($input['frequency']) ? sanitize_key($input['frequency']) : '';
        if (!in_array($frequency, array('every_week', 'every_two_weeks'), true)) {
            $frequency = 'every_week';
        }

        $start_candidates = array();
        if (isset($input['startDateISO'])) {
            $start_candidates[] = $input['startDateISO'];
        }
        if (isset($input['startDate'])) {
            $start_candidates[] = $input['startDate'];
        }

        $start_date = '';
        foreach ($start_candidates as $candidate) {
            $candidate = $this->sanitizeDateValue($candidate);
            if ($candidate !== '') {
                $start_date = $candidate;
                break;
            }
        }

        $end_candidates = array();
        if (isset($input['endDateISO'])) {
            $end_candidates[] = $input['endDateISO'];
        }
        if (isset($input['endDate'])) {
            $end_candidates[] = $input['endDate'];
        }

        $end_date = '';
        foreach ($end_candidates as $candidate) {
            $candidate = $this->sanitizeDateValue($candidate);
            if ($candidate !== '') {
                $end_date = $candidate;
                break;
            }
        }

        $start_time = isset($input['startTime']) ? $this->sanitizeTimeValue($input['startTime']) : '';
        $end_time = isset($input['endTime']) ? $this->sanitizeTimeValue($input['endTime']) : '';

        $days = array();
        $days_source = isset($input['days']) ? $input['days'] : array();
        foreach ($weekday_keys as $weekday) {
            $value = false;
            if (is_array($days_source)) {
                if (array_values($days_source) === $days_source) {
                    $normalized_source = array_map('sanitize_key', $days_source);
                    $value = in_array($weekday, $normalized_source, true);
                } elseif (array_key_exists($weekday, $days_source)) {
                    $value = !empty($days_source[$weekday]);
                }
            }
            $days[$weekday] = $value ? true : false;
        }

        $overrides = array();
        $overrides_source = array();
        if (isset($input['overrides']) && is_array($input['overrides'])) {
            $overrides_source = $input['overrides'];
        } elseif (isset($input['timeOverrides']) && is_array($input['timeOverrides'])) {
            $overrides_source = $input['timeOverrides'];
        }

        foreach ($weekday_keys as $weekday) {
            if (!isset($overrides_source[$weekday]) || !is_array($overrides_source[$weekday])) {
                continue;
            }

            $entry = array();
            if (isset($overrides_source[$weekday]['start'])) {
                $override_start = $this->sanitizeTimeValue($overrides_source[$weekday]['start']);
                if ($override_start !== '') {
                    $entry['start'] = $override_start;
                }
            }
            if (isset($overrides_source[$weekday]['end'])) {
                $override_end = $this->sanitizeTimeValue($overrides_source[$weekday]['end']);
                if ($override_end !== '') {
                    $entry['end'] = $override_end;
                }
            }
            if (!empty($entry)) {
                $overrides[$weekday] = $entry;
            }
        }

        $monthly_ordinal = isset($input['monthlyOrdinal']) ? sanitize_key($input['monthlyOrdinal']) : '';
        if (!in_array($monthly_ordinal, array('first', 'second', 'third', 'fourth', 'last'), true)) {
            $monthly_ordinal = 'first';
        }

        $monthly_weekday = isset($input['monthlyWeekday']) ? sanitize_key($input['monthlyWeekday']) : '';
        if (!in_array($monthly_weekday, $weekday_keys, true)) {
            $monthly_weekday = 'mon';
        }

        $explicit_start = false;
        if (isset($input['explicitStart'])) {
            $explicit_start = $this->toBool($input['explicitStart'], false);
        } elseif (isset($input['_explicitStart'])) {
            $explicit_start = $this->toBool($input['_explicitStart'], false);
        }
        if ($start_date !== '') {
            $explicit_start = true;
        }

        return array(
            'version' => 'occurrence-editor',
            'mode' => $mode,
            'frequency' => $frequency,
            'startDate' => $start_date,
            'endDate' => $end_date,
            'startTime' => $start_time,
            'endTime' => $end_time,
            'days' => $days,
            'overrides' => $overrides,
            'monthlyOrdinal' => $monthly_ordinal,
            'monthlyWeekday' => $monthly_weekday,
            'explicitStart' => $explicit_start,
        );
    }

    private function deriveGeneratorPlanFromSchedule($schedule_payload) {
        if (!is_array($schedule_payload)) {
            return array();
        }

        $mode = isset($schedule_payload['mode']) ? sanitize_key($schedule_payload['mode']) : '';
        if ($mode !== 'recurring') {
            return array();
        }

        $weekday_keys = array('mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun');
        $days_map = array();
        foreach ($weekday_keys as $weekday_key) {
            $days_map[$weekday_key] = false;
        }

        if (isset($schedule_payload['weekdays']) && is_array($schedule_payload['weekdays'])) {
            foreach ($schedule_payload['weekdays'] as $weekday) {
                $weekday = sanitize_key($weekday);
                if (isset($days_map[$weekday])) {
                    $days_map[$weekday] = true;
                }
            }
        }

        $weekday_times = array();
        if (isset($schedule_payload['weekday_times']) && is_array($schedule_payload['weekday_times'])) {
            foreach ($schedule_payload['weekday_times'] as $weekday => $time_info) {
                $weekday = sanitize_key($weekday);
                if (!isset($days_map[$weekday])) {
                    $days_map[$weekday] = true;
                }
                if (is_array($time_info)) {
                    $weekday_times[$weekday] = $time_info;
                }
            }
        }

        $base_start_time = isset($schedule_payload['start_time']) ? $this->sanitizeTimeValue($schedule_payload['start_time']) : '';
        $base_end_time = isset($schedule_payload['end_time']) ? $this->sanitizeTimeValue($schedule_payload['end_time']) : '';

        $overrides = array();
        foreach ($days_map as $weekday => $is_active) {
            if (!$is_active) {
                continue;
            }

            $specific = isset($weekday_times[$weekday]) && is_array($weekday_times[$weekday]) ? $weekday_times[$weekday] : array();
            $specific_start = isset($specific['start']) ? $this->sanitizeTimeValue($specific['start']) : '';
            $specific_end = isset($specific['end']) ? $this->sanitizeTimeValue($specific['end']) : '';

            if ($specific_start === '' && $base_start_time !== '') {
                $specific_start = $base_start_time;
            }
            if ($specific_end === '' && $base_end_time !== '') {
                $specific_end = $base_end_time;
            }

            if ($specific_start !== '' || $specific_end !== '') {
                $entry = array();
                if ($specific_start !== '') {
                    $entry['start'] = $specific_start;
                }
                if ($specific_end !== '') {
                    $entry['end'] = $specific_end;
                }
                $overrides[$weekday] = $entry;
            }
        }

        $start_date = '';
        if (isset($schedule_payload['start_date'])) {
            $start_date = $this->sanitizeDateValue($schedule_payload['start_date']);
        }

        $end_date = '';
        if (isset($schedule_payload['end_date'])) {
            $end_date = $this->sanitizeDateValue($schedule_payload['end_date']);
        }
        if ($end_date === '' && isset($schedule_payload['until'])) {
            $until_candidate = (string) $schedule_payload['until'];
            if ($until_candidate !== '') {
                $end_date = $this->sanitizeDateValue(substr($until_candidate, 0, 10));
            }
        }

        $frequency_source = isset($schedule_payload['frequency']) ? sanitize_key($schedule_payload['frequency']) : 'weekly';
        $interval = isset($schedule_payload['interval']) ? max(1, (int) $schedule_payload['interval']) : 1;

        $plan_mode = $frequency_source === 'monthly' ? 'monthly' : 'weekly';
        $plan_frequency = ($interval >= 2) ? 'every_two_weeks' : 'every_week';

        $plan = array(
            'version' => 'occurrence-editor',
            'mode' => $plan_mode,
            'frequency' => $plan_frequency,
            'startDate' => $start_date,
            'endDate' => $end_date,
            'startTime' => $base_start_time,
            'endTime' => $base_end_time,
            'days' => $days_map,
            'overrides' => $overrides,
            'explicitStart' => $start_date !== '',
        );

        if ($plan_mode === 'monthly') {
            $ordinal = isset($schedule_payload['ordinal']) ? sanitize_key($schedule_payload['ordinal']) : '';
            if ($ordinal !== '') {
                $plan['monthlyOrdinal'] = $ordinal;
            }
            $weekday = isset($schedule_payload['weekday']) ? sanitize_key($schedule_payload['weekday']) : '';
            if ($weekday !== '') {
                $plan['monthlyWeekday'] = $weekday;
                if (isset($plan['days'][$weekday])) {
                    $plan['days'][$weekday] = true;
                }
            }
        }

        return $this->sanitizeOccurrenceGeneratorPlan($plan);
    }

    private function mergeGeneratorPlans(array $primary, array $fallback) {
        if (empty($primary)) {
            return $fallback;
        }
        if (empty($fallback)) {
            return $this->sanitizeOccurrenceGeneratorPlan($primary);
        }

        $merged = $primary;

        if (isset($fallback['days']) && is_array($fallback['days'])) {
            $merged_days = isset($merged['days']) && is_array($merged['days']) ? $merged['days'] : array();
            foreach ($fallback['days'] as $weekday => $flag) {
                if (!isset($merged_days[$weekday]) || $merged_days[$weekday] === false) {
                    $merged_days[$weekday] = !empty($flag);
                }
            }
            $merged['days'] = $merged_days;
        }

        if (isset($fallback['overrides']) && is_array($fallback['overrides'])) {
            $merged_overrides = isset($merged['overrides']) && is_array($merged['overrides']) ? $merged['overrides'] : array();
            foreach ($fallback['overrides'] as $weekday => $override) {
                if (!is_array($override)) {
                    continue;
                }

                if (!isset($merged_overrides[$weekday]) || !is_array($merged_overrides[$weekday]) || empty($merged_overrides[$weekday])) {
                    $merged_overrides[$weekday] = $override;
                    continue;
                }

                $current = $merged_overrides[$weekday];

                if ((!isset($current['start']) || $current['start'] === '') && isset($override['start']) && $override['start'] !== '') {
                    $current['start'] = $override['start'];
                }

                if ((!isset($current['end']) || $current['end'] === '') && isset($override['end']) && $override['end'] !== '') {
                    $current['end'] = $override['end'];
                }

                $merged_overrides[$weekday] = $current;
            }
            $merged['overrides'] = $merged_overrides;
        }

        $simple_keys = array('mode', 'frequency', 'startDate', 'endDate', 'startTime', 'endTime', 'monthlyOrdinal', 'monthlyWeekday');
        foreach ($simple_keys as $key) {
            if ((!isset($merged[$key]) || $merged[$key] === '' || $merged[$key] === null) && isset($fallback[$key])) {
                $merged[$key] = $fallback[$key];
            }
        }

        if (empty($merged['explicitStart']) && !empty($fallback['explicitStart'])) {
            $merged['explicitStart'] = true;
        }

        if (empty($merged['version'])) {
            $merged['version'] = 'occurrence-editor';
        }

        return $this->sanitizeOccurrenceGeneratorPlan($merged);
    }

    private function extractOccurrenceGeneratorFromPayload($payload) {
        if (!is_array($payload)) {
            return array();
        }

        if (isset($payload['occurrence_generator']) && is_array($payload['occurrence_generator'])) {
            return $this->sanitizeOccurrenceGeneratorPlan($payload['occurrence_generator']);
        }

        if (isset($payload['occurrenceGenerator']) && is_array($payload['occurrenceGenerator'])) {
            return $this->sanitizeOccurrenceGeneratorPlan($payload['occurrenceGenerator']);
        }

        $derived = $this->deriveGeneratorPlanFromSchedule($payload);
        if (!empty($derived)) {
            return $derived;
        }

        return array();
    }

    private function extractOccurrenceGeneratorFromEvent($event) {
        if (!$event || !isset($event->schedule_payload)) {
            return array();
        }

        $payload = $this->decodeJsonField($event->schedule_payload);
        if (!is_array($payload)) {
            return array();
        }

        return $this->extractOccurrenceGeneratorFromPayload($payload);
    }

    private function schedulePayloadHasOccurrenceEntities($payload) {
        if (!is_array($payload)) {
            return false;
        }

        $collections = array();
        if (isset($payload['occurrences']) && is_array($payload['occurrences'])) {
            $collections[] = $payload['occurrences'];
        }
        if (isset($payload['items']) && is_array($payload['items'])) {
            $collections[] = $payload['items'];
        }

        foreach ($collections as $entries) {
            foreach ($entries as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                foreach ($entry as $value) {
                    if ($value !== null && $value !== '') {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function shouldAllowOccurrenceFallback($event) {
        if (!$event) {
            return false;
        }

        $schedule_mode = '';
        if (is_object($event) && isset($event->schedule_mode)) {
            $schedule_mode = sanitize_key((string) $event->schedule_mode);
        } elseif (is_array($event) && isset($event['schedule_mode'])) {
            $schedule_mode = sanitize_key((string) $event['schedule_mode']);
        }

        if ($schedule_mode === 'series' || $schedule_mode === 'recurring') {
            return false;
        }

        $date_debut = isset($event->date_debut) ? (string) $event->date_debut : '';
        if ($date_debut === '') {
            return false;
        }

        $payload = $this->decodeJsonField(isset($event->schedule_payload) ? $event->schedule_payload : array());
        if (!is_array($payload)) {
            return true;
        }

        $mode = isset($payload['mode']) ? sanitize_key((string) $payload['mode']) : '';
        $version = isset($payload['version']) ? sanitize_key((string) $payload['version']) : '';

        if (($version === 'occurrence-editor' || $mode === 'series') && !$this->schedulePayloadHasOccurrenceEntities($payload)) {
            return false;
        }

        return true;
    }

    private function prepareEventOccurrenceRows($input) {
        $normalized = array(
            'rows' => array(),
            'stats' => array(
                'min_start' => null,
                'max_end' => null,
            ),
        );

        if (!is_array($input)) {
            return $normalized;
        }

        $timezone = function_exists('wp_timezone') ? wp_timezone() : new \DateTimeZone('UTC');

        foreach ($input as $item) {
            if (!is_array($item)) {
                continue;
            }

            $date = isset($item['date']) ? sanitize_text_field($item['date']) : '';
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                continue;
            }

            $start_time = isset($item['startTime']) ? sanitize_text_field($item['startTime']) : '';
            if (!preg_match('/^\d{2}:\d{2}$/', $start_time)) {
                $start_time = '09:00';
            }

            $end_time = isset($item['endTime']) ? sanitize_text_field($item['endTime']) : '';
            if (!preg_match('/^\d{2}:\d{2}$/', $end_time)) {
                $end_time = $start_time;
            }

            $is_all_day = false;
            if (isset($item['isAllDay'])) {
                $raw_all_day = $item['isAllDay'];
            } elseif (isset($item['allDay'])) {
                $raw_all_day = $item['allDay'];
            } elseif (isset($item['all_day'])) {
                $raw_all_day = $item['all_day'];
            } else {
                $raw_all_day = null;
            }
            if (is_bool($raw_all_day)) {
                $is_all_day = $raw_all_day;
            } elseif (is_numeric($raw_all_day)) {
                $is_all_day = ((int) $raw_all_day) === 1;
            } elseif (is_string($raw_all_day)) {
                $is_all_day = in_array(strtolower(trim($raw_all_day)), array('1', 'true', 'yes', 'on'), true);
            }
            if ($is_all_day) {
                $start_time = '00:00';
                $end_time = '23:59';
            }

            $start_string = $date . ' ' . $start_time . ':00';
            $end_string = $date . ' ' . $end_time . ':00';

            $start_dt = \DateTime::createFromFormat('Y-m-d H:i:s', $start_string, $timezone);
            if (!$start_dt instanceof \DateTime) {
                $timestamp = strtotime($start_string);
                if ($timestamp === false) {
                    continue;
                }
                $start_dt = new \DateTime('@' . $timestamp);
                $start_dt->setTimezone($timezone);
            }

            $end_dt = \DateTime::createFromFormat('Y-m-d H:i:s', $end_string, $timezone);
            if (!$end_dt instanceof \DateTime) {
                $timestamp_end = strtotime($end_string);
                if ($timestamp_end === false) {
                    $end_dt = clone $start_dt;
                    $end_dt->modify('+1 hour');
                } else {
                    $end_dt = new \DateTime('@' . $timestamp_end);
                    $end_dt->setTimezone($timezone);
                }
            }

            if ($end_dt <= $start_dt) {
                $end_dt = clone $start_dt;
                $end_dt->modify('+1 hour');
            }

            $status = $this->occurrenceStatusFromFront(isset($item['status']) ? $item['status'] : '');
            $source = isset($item['source']) ? sanitize_key((string) $item['source']) : '';
            if ($source === '') {
                $source = MjEventOccurrences::SOURCE_MANUAL;
            }

            $meta = array();
            if (!empty($item['reason'])) {
                $meta['reason'] = sanitize_text_field($item['reason']);
            }
            if (!empty($item['id'])) {
                $meta['client_id'] = sanitize_text_field((string) $item['id']);
            }
            if ($status !== '') {
                $meta['status'] = $status;
            }
            if ($is_all_day) {
                $meta['all_day'] = 1;
            }

            $start_formatted = $start_dt->format('Y-m-d H:i:s');
            $end_formatted = $end_dt->format('Y-m-d H:i:s');

            $row = array(
                'start' => $start_formatted,
                'end' => $end_formatted,
                'status' => $status,
                'source' => $source,
                'meta' => !empty($meta) ? $meta : null,
            );

            $normalized['rows'][] = $row;

            if ($normalized['stats']['min_start'] === null || strcmp($start_formatted, $normalized['stats']['min_start']) < 0) {
                $normalized['stats']['min_start'] = $start_formatted;
            }

            if ($normalized['stats']['max_end'] === null || strcmp($end_formatted, $normalized['stats']['max_end']) > 0) {
                $normalized['stats']['max_end'] = $end_formatted;
            }
        }

        if (!empty($normalized['rows'])) {
            usort(
                $normalized['rows'],
                static function ($left, $right) {
                    return strcmp($left['start'], $right['start']);
                }
            );
        }

        return $normalized;
    }

    private function formatEventOccurrencesForFront($occurrences) {
        $formatted = array();
        if (!is_array($occurrences)) {
            return $formatted;
        }

        foreach ($occurrences as $occurrence) {
            if (!is_array($occurrence)) {
                continue;
            }

            $start = isset($occurrence['start']) ? (string) $occurrence['start'] : '';
            if ($start === '') {
                continue;
            }

            $end = isset($occurrence['end']) ? (string) $occurrence['end'] : '';
            $status = isset($occurrence['status']) ? $this->occurrenceStatusToFront($occurrence['status']) : 'planned';
            $reason = '';
            $is_all_day = false;
            if (isset($occurrence['meta'])) {
                $meta = $occurrence['meta'];
                if (is_string($meta)) {
                    $decoded_meta = json_decode($meta, true);
                    if (is_array($decoded_meta)) {
                        $meta = $decoded_meta;
                    }
                }
                if (is_array($meta) && isset($meta['reason'])) {
                    $reason = sanitize_text_field((string) $meta['reason']);
                }
                if (is_array($meta) && isset($meta['all_day'])) {
                    $raw_all_day = $meta['all_day'];
                    if (is_bool($raw_all_day)) {
                        $is_all_day = $raw_all_day;
                    } elseif (is_numeric($raw_all_day)) {
                        $is_all_day = ((int) $raw_all_day) === 1;
                    } elseif (is_string($raw_all_day)) {
                        $is_all_day = in_array(strtolower(trim($raw_all_day)), array('1', 'true', 'yes', 'on'), true);
                    }
                }
            }

            $id = isset($occurrence['id']) ? $occurrence['id'] : (isset($occurrence['timestamp']) ? $occurrence['timestamp'] : md5($start));
            $start_time = substr($start, 11, 5);
            $end_time = $end !== '' ? substr($end, 11, 5) : '';

            $formatted[] = array(
                'id' => $id !== null ? (string) $id : md5($start),
                'start' => $start,
                'end' => $end,
                'date' => substr($start, 0, 10),
                'startTime' => preg_match('/^\d{2}:\d{2}$/', $start_time) ? $start_time : '',
                'endTime' => preg_match('/^\d{2}:\d{2}$/', $end_time) ? $end_time : '',
                'isAllDay' => $is_all_day,
                'status' => $status,
                'reason' => $reason,
                'startFormatted' => $this->formatDate($start, true),
                'endFormatted' => $end !== '' ? $this->formatDate($end, true) : '',
            );
        }

        return $formatted;
    }

    /**
     * @param array<int, array<string, mixed>> $occurrences
     * @return array<int, array<string, mixed>>
     */
    private function normalizeOccurrencesForInlineSchedule(array $occurrences): array
    {
        $normalized = array();

        foreach ($occurrences as $occurrence) {
            if (!is_array($occurrence)) {
                continue;
            }

            $start = isset($occurrence['start']) ? trim((string) $occurrence['start']) : '';
            if ($start === '' && !empty($occurrence['date'])) {
                $time = isset($occurrence['startTime']) && preg_match('/^\d{2}:\d{2}$/', (string) $occurrence['startTime'])
                    ? (string) $occurrence['startTime']
                    : '00:00';
                $start = trim((string) $occurrence['date']) . ' ' . $time . ':00';
            }

            if ($start === '') {
                continue;
            }

            $end = isset($occurrence['end']) ? trim((string) $occurrence['end']) : '';
            if ($end === '' && !empty($occurrence['date'])) {
                $endTime = isset($occurrence['endTime']) && preg_match('/^\d{2}:\d{2}$/', (string) $occurrence['endTime'])
                    ? (string) $occurrence['endTime']
                    : '';
                if ($endTime !== '') {
                    $end = trim((string) $occurrence['date']) . ' ' . $endTime . ':00';
                }
            }

            $timestamp = isset($occurrence['timestamp']) ? (int) $occurrence['timestamp'] : 0;
            if ($timestamp <= 0) {
                $timestamp = strtotime($start);
            }

            $entry = array(
                'start' => $start,
                'end' => $end,
            );

            if ($timestamp > 0) {
                $entry['timestamp'] = $timestamp;
            }

            $normalized[] = $entry;
        }

        return $normalized;
    }

    /**
     * @param array<int, array<string, mixed>> $occurrences
     * @param array<string, mixed> $schedule_info
     * @param array<string, mixed> $occurrence_generator_plan
     * @return array{html:string,schedule:array<string,mixed>}
     */
    private function buildInlineSchedulePreviewData($event, array $occurrences, array $schedule_info = array(), array $occurrence_generator_plan = array()): array
    {
        if (is_object($event)) {
            if (method_exists($event, 'toArray')) {
                $event = $event->toArray();
            } else {
                $event = get_object_vars($event);
            }
        }

        if (!is_array($event)) {
            $event = array();
        }

        $schedule = array(
            'mode' => isset($event['schedule_mode']) ? sanitize_key((string) $event['schedule_mode']) : 'fixed',
            'schedule_summary' => isset($schedule_info['summary']) ? (string) $schedule_info['summary'] : '',
            'display_label' => isset($schedule_info['detail']) ? (string) $schedule_info['detail'] : '',
            'date_debut' => isset($event['date_debut']) ? (string) $event['date_debut'] : '',
            'date_fin' => isset($event['date_fin']) ? (string) $event['date_fin'] : '',
            'occurrences' => $this->normalizeOccurrencesForInlineSchedule($occurrences),
            'weekly_schedule' => array(
                'from_generator' => !empty($occurrence_generator_plan),
            ),
        );

        return array(
            'html' => EventPageViewBuilder::renderInlineScheduleHtml($schedule),
            'schedule' => $schedule,
        );
    }

    private function findNextOccurrence(array $occurrences) {
        if (empty($occurrences)) {
            return null;
        }

        $now = current_time('timestamp');
        foreach ($occurrences as $occurrence) {
            if (!is_array($occurrence)) {
                continue;
            }
            if (!isset($occurrence['timestamp'])) {
                continue;
            }
            if ((int) $occurrence['timestamp'] >= $now) {
                return $occurrence;
            }
        }

        foreach ($occurrences as $occurrence) {
            if (is_array($occurrence)) {
                return $occurrence;
            }
        }

        return null;
    }

    private function buildEventScheduleInfo($event, $mode = '') {
        if (is_object($event)) {
            if (method_exists($event, 'toArray')) {
                $event = $event->toArray();
            } else {
                $event = get_object_vars($event);
            }
        }
        if (!is_array($event)) {
            $event = array();
        }

        $schedule_mode = $mode !== '' ? sanitize_key((string) $mode) : '';
        if ($schedule_mode === '' && isset($event['schedule_mode'])) {
            $schedule_mode = sanitize_key((string) $event['schedule_mode']);
        }
        if ($schedule_mode === '') {
            $schedule_mode = 'fixed';
        }

        $summary = '';
        $detail_parts = array();

        $start_raw = isset($event['date_debut']) ? (string) $event['date_debut'] : '';
        $end_raw = isset($event['date_fin']) ? (string) $event['date_fin'] : '';
        $schedule_payload = array();
        if (isset($event['schedule_payload'])) {
            $schedule_payload = $this->decodeJsonField($event['schedule_payload']);
        }

        switch ($schedule_mode) {
            case 'range':
                $summary = __('Période continue', 'mj-member');
                $start_date = $this->formatDateCompact($start_raw);
                $end_date = $this->formatDateCompact($end_raw);
                if ($start_date !== '' && $end_date !== '') {
                    $detail_parts[] = $start_date . ' → ' . $end_date;
                } elseif ($start_date !== '') {
                    $detail_parts[] = $start_date;
                }
                $start_time = $this->formatTimeCompact($start_raw);
                $end_time = $this->formatTimeCompact($end_raw);
                if ($start_time !== '' && $end_time !== '') {
                    $detail_parts[] = $start_time . ' → ' . $end_time;
                } elseif ($start_time !== '') {
                    $detail_parts[] = $start_time;
                }
                break;

            case 'recurring':
            case 'series':
                $summary = $schedule_mode === 'recurring'
                    ? __('Récurrence', 'mj-member')
                    : __('Série personnalisée', 'mj-member');

                $occurrences = array();
                if (class_exists(MjEventSchedule::class)) {
                    $occurrences = MjEventSchedule::build_all_occurrences($event);
                }
                $occurrence_count = is_array($occurrences) ? count($occurrences) : 0;
                $weekday_summary = '';

                if ($schedule_mode === 'recurring') {
                    $frequency = isset($schedule_payload['frequency']) ? sanitize_key((string) $schedule_payload['frequency']) : 'weekly';
                    if ($frequency === '') {
                        $frequency = 'weekly';
                    }

                    if ($frequency === 'weekly') {
                        $weekday_labels = $this->getScheduleWeekdays();
                        $weekday_keys = array();

                        if (isset($schedule_payload['weekdays']) && is_array($schedule_payload['weekdays'])) {
                            $weekday_keys = $schedule_payload['weekdays'];
                        }

                        if (empty($weekday_keys) && isset($schedule_payload['weekday_times']) && is_array($schedule_payload['weekday_times'])) {
                            $weekday_keys = array_keys($schedule_payload['weekday_times']);
                        }

                        if (!empty($weekday_keys)) {
                            $weekday_keys = array_values(array_unique(array_map('sanitize_key', $weekday_keys)));
                            $ordered_keys = array();
                            $week_order = array('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday');
                            foreach ($week_order as $weekday_key) {
                                if (in_array($weekday_key, $weekday_keys, true)) {
                                    $ordered_keys[] = $weekday_key;
                                }
                            }

                            $weekday_names = array();
                            foreach ($ordered_keys as $weekday_key) {
                                if (isset($weekday_labels[$weekday_key])) {
                                    $weekday_names[] = $weekday_labels[$weekday_key];
                                }
                            }

                            if (!empty($weekday_names)) {
                                $weekday_summary = implode(', ', $weekday_names);
                            }
                        }
                    } elseif ($frequency === 'monthly') {
                        $weekday_labels = $this->getScheduleWeekdays();
                        $ordinal_labels = $this->getScheduleMonthOrdinals();

                        $ordinal = isset($schedule_payload['ordinal']) ? sanitize_key((string) $schedule_payload['ordinal']) : '';
                        $weekday_key = isset($schedule_payload['weekday']) ? sanitize_key((string) $schedule_payload['weekday']) : '';

                        if (isset($ordinal_labels[$ordinal]) && isset($weekday_labels[$weekday_key])) {
                            $weekday_summary = trim($ordinal_labels[$ordinal] . ' ' . $weekday_labels[$weekday_key]);
                        }
                    }
                }

                if ($schedule_mode === 'recurring' && $occurrence_count > 0) {
                    $detail_parts[] = sprintf(_n('%d séance', '%d séances', $occurrence_count, 'mj-member'), $occurrence_count);
                } elseif ($schedule_mode === 'series' && $occurrence_count > 0) {
                    $detail_parts[] = sprintf(_n('%d date', '%d dates', $occurrence_count, 'mj-member'), $occurrence_count);
                }

                if (!empty($weekday_summary)) {
                    $summary = sprintf(__('Récurrence · %s', 'mj-member'), $weekday_summary);
                }

                $next_occurrence = $this->findNextOccurrence(is_array($occurrences) ? $occurrences : array());
                if ($next_occurrence && !empty($next_occurrence['start'])) {
                    $detail_parts[] = sprintf(__('Prochaine : %s', 'mj-member'), $this->formatDatetimeCompact($next_occurrence['start']));
                } elseif ($start_raw !== '') {
                    $detail_parts[] = $this->formatDatetimeCompact($start_raw);
                }
                break;

            case 'fixed':
            default:
                $summary = __('Date unique', 'mj-member');
                $start_compact = $this->formatDatetimeCompact($start_raw);
                if ($start_compact !== '') {
                    $detail = $start_compact;
                    $end_compact_time = '';
                    $end_date = $this->formatDateCompact($end_raw);
                    $start_date = $this->formatDateCompact($start_raw);
                    if ($end_date !== '' && $start_date !== '' && $end_date === $start_date) {
                        $end_compact_time = $this->formatTimeCompact($end_raw);
                    } else {
                        $end_compact_time = $this->formatDatetimeCompact($end_raw);
                    }
                    if ($end_compact_time !== '') {
                        $detail .= ' → ' . $end_compact_time;
                    }
                    $detail_parts[] = $detail;
                }

                // If a generated occurrences payload exists, prefer it over the legacy fixed-date label.
                $payload_plan = $this->extractOccurrenceGeneratorFromPayload($schedule_payload);
                $payload_occurrences = isset($schedule_payload['occurrences']) && is_array($schedule_payload['occurrences'])
                    ? $schedule_payload['occurrences']
                    : array();
                if (empty($payload_occurrences) && isset($schedule_payload['items']) && is_array($schedule_payload['items'])) {
                    $payload_occurrences = $schedule_payload['items'];
                }

                $has_payload_occurrences = !empty($payload_occurrences);
                $has_payload_plan = !empty($payload_plan);

                if ($has_payload_plan || $has_payload_occurrences) {
                    $payload_detail_parts = array();

                    if ($has_payload_occurrences) {
                        $payload_detail_parts[] = sprintf(
                            _n('%d date', '%d dates', count($payload_occurrences), 'mj-member'),
                            count($payload_occurrences)
                        );
                    }

                    $plan_mode = isset($payload_plan['mode']) ? sanitize_key((string) $payload_plan['mode']) : '';
                    $plan_frequency = isset($payload_plan['frequency']) ? sanitize_key((string) $payload_plan['frequency']) : '';
                    $plan_days = isset($payload_plan['days']) && is_array($payload_plan['days']) ? $payload_plan['days'] : array();
                    $plan_start_date = isset($payload_plan['startDate']) ? (string) $payload_plan['startDate'] : '';
                    $plan_end_date = isset($payload_plan['endDate']) ? (string) $payload_plan['endDate'] : '';
                    $plan_start_time = isset($payload_plan['startTime']) ? (string) $payload_plan['startTime'] : '';
                    $plan_end_time = isset($payload_plan['endTime']) ? (string) $payload_plan['endTime'] : '';

                    if ($plan_mode === 'weekly') {
                        $weekday_labels = array(
                            'mon' => __('Lundi', 'mj-member'),
                            'tue' => __('Mardi', 'mj-member'),
                            'wed' => __('Mercredi', 'mj-member'),
                            'thu' => __('Jeudi', 'mj-member'),
                            'fri' => __('Vendredi', 'mj-member'),
                            'sat' => __('Samedi', 'mj-member'),
                            'sun' => __('Dimanche', 'mj-member'),
                        );

                        $active_days = array();
                        foreach (array('mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun') as $weekday_key) {
                            if (!empty($plan_days[$weekday_key]) && isset($weekday_labels[$weekday_key])) {
                                $active_days[] = $weekday_labels[$weekday_key];
                            }
                        }

                        if (!empty($active_days)) {
                            $payload_detail_parts[] = implode(', ', $active_days);
                        }

                        if ($plan_frequency === 'every_two_weeks') {
                            $payload_detail_parts[] = __('Toutes les 2 semaines', 'mj-member');
                        } elseif ($plan_frequency === 'every_week') {
                            $payload_detail_parts[] = __('Chaque semaine', 'mj-member');
                        }
                    } elseif ($plan_mode === 'monthly') {
                        $month_ordinals = $this->getScheduleMonthOrdinals();
                        $ordinal_key = isset($payload_plan['monthlyOrdinal']) ? sanitize_key((string) $payload_plan['monthlyOrdinal']) : '';
                        $weekday_key = isset($payload_plan['monthlyWeekday']) ? sanitize_key((string) $payload_plan['monthlyWeekday']) : '';

                        $weekday_labels = array(
                            'mon' => __('Lundi', 'mj-member'),
                            'tue' => __('Mardi', 'mj-member'),
                            'wed' => __('Mercredi', 'mj-member'),
                            'thu' => __('Jeudi', 'mj-member'),
                            'fri' => __('Vendredi', 'mj-member'),
                            'sat' => __('Samedi', 'mj-member'),
                            'sun' => __('Dimanche', 'mj-member'),
                        );

                        if (isset($month_ordinals[$ordinal_key]) && isset($weekday_labels[$weekday_key])) {
                            $payload_detail_parts[] = trim($month_ordinals[$ordinal_key] . ' ' . $weekday_labels[$weekday_key]);
                        }
                    }

                    if ($plan_start_date !== '' || $plan_end_date !== '') {
                        $start_date_compact = $plan_start_date !== '' ? $this->formatDateCompact($plan_start_date . ' 00:00:00') : '';
                        $end_date_compact = $plan_end_date !== '' ? $this->formatDateCompact($plan_end_date . ' 00:00:00') : '';

                        if ($start_date_compact !== '' && $end_date_compact !== '') {
                            $payload_detail_parts[] = $start_date_compact . ' → ' . $end_date_compact;
                        } elseif ($start_date_compact !== '') {
                            $payload_detail_parts[] = $start_date_compact;
                        }
                    }

                    if ($plan_start_time !== '' && $plan_end_time !== '') {
                        $payload_detail_parts[] = $plan_start_time . ' → ' . $plan_end_time;
                    } elseif ($plan_start_time !== '') {
                        $payload_detail_parts[] = $plan_start_time;
                    }

                    if (!empty($payload_detail_parts)) {
                        $summary = __('Occurrences générées', 'mj-member');
                        $detail_parts = $payload_detail_parts;
                    }
                }
                break;
        }

        if (is_array($schedule_payload) && isset($schedule_payload['occurrence_summary'])) {
            $custom_summary = trim((string) $schedule_payload['occurrence_summary']);
            if ($custom_summary !== '') {
                $summary = $custom_summary;
            }
        }

        return array(
            'summary' => $summary,
            'detail' => implode(' · ', array_filter($detail_parts)),
        );
    }

    /**
     * Get events list
     */
    public function getEvents() {
        $auth = $this->verifyRequest();
        if (!$auth) return;

        // Support multi-filter (array) or legacy single filter (string)
        // Empty array or missing = show all (no filter)
        $raw_filter = isset($_POST['filter']) ? $_POST['filter'] : null;
        if (is_array($raw_filter)) {
            $active_filters = array_filter(array_map('sanitize_key', $raw_filter), function($v) { return $v !== ''; });
        } elseif (is_string($raw_filter) && $raw_filter !== '') {
            $single = sanitize_key($raw_filter);
            $active_filters = ($single !== '' && $single !== 'all') ? array($single) : array();
        } else {
            // No filter sent at all: default to assigned for backward compatibility
            $active_filters = array('assigned');
        }

        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $page = isset($_POST['page']) ? max(1, (int) $_POST['page']) : 1;
        $per_page = isset($_POST['perPage']) ? max(5, min(100, (int) $_POST['perPage'])) : 10;

        // Sort parameters
        $sort = isset($_POST['sort']) ? sanitize_key($_POST['sort']) : 'date';
        $sort_order_param = isset($_POST['sortOrder']) ? strtoupper(sanitize_key($_POST['sortOrder'])) : '';

        $sort_map = array(
            'date'  => 'date_debut',
            'title' => 'title',
            'type'  => 'type',
            'status' => 'status',
            'created' => 'created_at',
        );
        $orderby = isset($sort_map[$sort]) ? $sort_map[$sort] : 'date_debut';

        // Default order depends on sort
        $default_order = ($sort === 'title') ? 'ASC' : 'DESC';
        $order = in_array($sort_order_param, array('ASC', 'DESC'), true) ? $sort_order_param : $default_order;

        $now = current_time('mysql');
        $events = array();
        $total = 0;

        // Determine if 'assigned' filter is active
        $use_assigned = in_array('assigned', $active_filters, true);

        // Determine if 'favorites' filter is active
        $use_favorites = in_array('favorites', $active_filters, true);
        $favorite_event_ids = array();
        if ($use_favorites) {
            $fav_events = get_user_meta(get_current_user_id(), '_mj_regmgr_fav_events', true);
            $favorite_event_ids = is_array($fav_events) ? array_map('intval', $fav_events) : array();
        }

        // Build status/type constraints from active filters
        $status_constraints = array();
        $type_constraints = array();
        $use_after = false;

        foreach ($active_filters as $f) {
            switch ($f) {
                case 'upcoming':
                    $status_constraints[] = MjEvents::STATUS_ACTIVE;
                    $use_after = true;
                    break;
                case 'past':
                    $status_constraints[] = MjEvents::STATUS_PAST;
                    break;
                case 'draft':
                    $status_constraints[] = MjEvents::STATUS_DRAFT;
                    break;
                case 'internal':
                    $type_constraints[] = MjEvents::TYPE_INTERNE;
                    break;
            }
        }

        if ($use_assigned) {
            // Use assigned events for this member
            $assigned_args = array(
                'orderby' => $orderby,
                'order' => $order,
            );
            if (!empty($status_constraints)) {
                $assigned_args['statuses'] = array_unique($status_constraints);
            } else {
                $assigned_args['statuses'] = array(MjEvents::STATUS_ACTIVE);
            }
            $all_assigned = MjEventAnimateurs::get_events_for_member($auth['member_id'], $assigned_args);
        
            // Apply search
            if ($search !== '') {
                $search_lower = mb_strtolower($search);
                $all_assigned = array_filter($all_assigned, function($event) use ($search_lower) {
                    return mb_strpos(mb_strtolower($event->title), $search_lower) !== false;
                });
            }

            // Apply type filter
            if (!empty($type_constraints)) {
                $all_assigned = array_filter($all_assigned, function($event) use ($type_constraints) {
                    return isset($event->type) && in_array($event->type, $type_constraints, true);
                });
            }

            // Apply after filter
            if ($use_after) {
                $all_assigned = array_filter($all_assigned, function($event) use ($now) {
                    return isset($event->date_debut) && $event->date_debut >= $now;
                });
            }

            // Apply favorites filter
            if ($use_favorites) {
                if (empty($favorite_event_ids)) {
                    $all_assigned = array();
                } else {
                    $all_assigned = array_filter($all_assigned, function($event) use ($favorite_event_ids) {
                        return isset($event->id) && in_array((int) $event->id, $favorite_event_ids, true);
                    });
                }
            }
        
            $total = count($all_assigned);
            $events = array_slice(array_values($all_assigned), ($page - 1) * $per_page, $per_page);
        } else {
            // Use MjEvents::get_all
            $args = array(
                'search' => $search,
                'orderby' => $orderby,
                'order' => $order,
                'limit' => $per_page,
                'offset' => ($page - 1) * $per_page,
            );

            if (!empty($status_constraints)) {
                $args['statuses'] = array_unique($status_constraints);
            }
            if (!empty($type_constraints)) {
                $args['types'] = array_unique($type_constraints);
            }
            if ($use_after) {
                $args['after'] = $now;
            }

            // Apply favorites filter
            if ($use_favorites) {
                if (empty($favorite_event_ids)) {
                    $args['ids'] = array(0); // No favorites → return nothing
                } else {
                    $args['ids'] = $favorite_event_ids;
                }
            }

            $events = MjEvents::get_all($args);
            $total = MjEvents::count($args);
        }

        // Grand total (unfiltered)
        $total_all = MjEvents::count(array());

        // Support targetEventId: ensure a specific event is always included in results
        // (used when navigating from calendar edit icon with ?event=ID)
        $target_event_id = isset($_POST['targetEventId']) ? absint($_POST['targetEventId']) : 0;

        $type_labels = MjEvents::get_type_labels();
        $status_labels = MjEvents::get_status_labels();

        $events_data = array();
        $target_found = false;
        foreach ($events as $event) {
            $formatted = $this->buildEventSidebarItem($event, $type_labels, $status_labels);
            if ($formatted !== null) {
                $events_data[] = $formatted;
                if ($target_event_id > 0 && isset($event->id) && (int) $event->id === $target_event_id) {
                    $target_found = true;
                }
            }
        }

        // If the target event was not in the paginated results, fetch and prepend it
        if ($target_event_id > 0 && !$target_found) {
            $target_event = MjEvents::find($target_event_id);
            if ($target_event) {
                $target_formatted = $this->buildEventSidebarItem($target_event, $type_labels, $status_labels);
                if ($target_formatted !== null) {
                    array_unshift($events_data, $target_formatted);
                }
            }
        }

        wp_send_json_success(array(
            'events' => $events_data,
            'pagination' => array(
                'total' => $total,
                'totalAll' => $total_all,
                'page' => $page,
                'perPage' => $per_page,
                'totalPages' => $total > 0 ? ceil($total / $per_page) : 1,
            ),
        ));
    }

    /**
     * Get single event details with occurrences
     */
    public function getEventDetails() {
        $auth = $this->verifyRequest();
        if (!$auth) return;

        $event_id = isset($_POST['eventId']) ? (int) $_POST['eventId'] : 0;
        if ($event_id <= 0) {
            wp_send_json_error(array('message' => __('ID événement invalide.', 'mj-member')));
            return;
        }

        $event = MjEvents::find($event_id);
        if (!$event) {
            wp_send_json_error(array('message' => __('Événement introuvable.', 'mj-member')));
            return;
        }

        // Get occurrences
        $occurrences = array();
        $occurrence_source = array();
        $schedule_mode = isset($event->schedule_mode) && $event->schedule_mode !== ''
            ? sanitize_key((string) $event->schedule_mode)
            : 'fixed';

        if (class_exists(MjEventSchedule::class)) {
            $raw_occurrences = MjEventSchedule::get_occurrences(
                $event,
                array(
                    'max' => 100,
                    'include_past' => true,
                    'include_cancelled' => true,
                )
            );
            $occurrence_source = is_array($raw_occurrences) ? $raw_occurrences : array();
            $occurrences = $this->formatEventOccurrencesForFront($raw_occurrences);
        }

        // Fallback: si pas d'occurrences mais event avec date, créer une occurrence unique
        if (empty($occurrences) && $this->shouldAllowOccurrenceFallback($event)) {
            $fallback_end = isset($event->date_fin) ? (string) $event->date_fin : '';
            $occurrences[] = array(
                'id' => 'single_' . $event->id,
                'start' => (string) $event->date_debut,
                'end' => $fallback_end,
                'date' => substr((string) $event->date_debut, 0, 10),
                'startTime' => substr((string) $event->date_debut, 11, 5),
                'endTime' => $fallback_end !== '' ? substr($fallback_end, 11, 5) : '',
                'status' => 'planned',
                'reason' => '',
                'startFormatted' => $this->formatDate((string) $event->date_debut, true),
                'endFormatted' => $fallback_end !== '' ? $this->formatDate($fallback_end, true) : '',
            );
            $fallback_timestamp = strtotime((string) $event->date_debut);
            $occurrence_source[] = array(
                'start' => (string) $event->date_debut,
                'end' => $fallback_end,
                'timestamp' => $fallback_timestamp > 0 ? $fallback_timestamp : null,
            );
        }

        $schedule_info = $this->buildEventScheduleInfo($event, $schedule_mode);
        $occurrence_generator_plan = $this->extractOccurrenceGeneratorFromEvent($event);
        $inline_schedule_preview = $this->buildInlineSchedulePreviewData($event, $occurrence_source, $schedule_info, $occurrence_generator_plan);

        // Get location
        $location = null;
        if (!empty($event->location_id) && class_exists('Mj\Member\Classes\Crud\MjEventLocations')) {
            $loc = \Mj\Member\Classes\Crud\MjEventLocations::find($event->location_id);
            if ($loc) {
                $location = array(
                    'id' => $loc->id,
                    'name' => $loc->name,
                    'address' => $loc->address ?? '',
                );
            }
        }

        // Get location links (multiple locations with types)
        $location_links = array();
        if (class_exists(MjEventLocationLinks::class)) {
            $location_links = MjEventLocationLinks::get_with_locations($event_id);
        }

        // Get animateurs
        $animateurs = array();
        if (class_exists('Mj\Member\Classes\Crud\MjEventAnimateurs')) {
            $animateurs_list = MjEventAnimateurs::get_members_by_event($event_id);
            foreach ($animateurs_list as $member) {
                if ($member) {
                    $animateurs[] = array(
                        'id' => $member->id,
                        'name' => trim(($member->first_name ?? '') . ' ' . ($member->last_name ?? '')),
                        'role' => $member->role ?? '',
                    );
                }
            }
        }

        $creator = null;
        $creator_member_id = isset($event->created_by_member_id) ? (int) $event->created_by_member_id : 0;
        if ($creator_member_id <= 0) {
            $creator_member_id = isset($event->animateur_id) ? (int) $event->animateur_id : 0;
        }
        if ($creator_member_id > 0) {
            $creator_member = MjMembers::getById($creator_member_id);
            if ($creator_member) {
                $creator_name = trim(($creator_member->first_name ?? '') . ' ' . ($creator_member->last_name ?? ''));
                if ($creator_name === '') {
                    $creator_name = sprintf(__('Membre #%d', 'mj-member'), $creator_member_id);
                }

                $creator = array(
                    'id' => $creator_member_id,
                    'firstName' => isset($creator_member->first_name) ? (string) $creator_member->first_name : '',
                    'lastName' => isset($creator_member->last_name) ? (string) $creator_member->last_name : '',
                    'name' => $creator_name,
                    'avatarUrl' => $this->getMemberAvatarUrl($creator_member_id),
                );
            }
        }

        $registrations_count = MjEventRegistrations::count(array('event_id' => $event_id));

        $type_labels = MjEvents::get_type_labels();
        $status_labels = MjEvents::get_status_labels();

        // Build front URL from article_id if available
        $front_url = '';
        if (!empty($event->article_id) && $event->article_id > 0) {
            $front_url = get_permalink($event->article_id);
        }

        $event_page_url = apply_filters('mj_member_event_permalink', '', $event);

        $registration_payload = $this->decodeJsonField(isset($event->registration_payload) ? $event->registration_payload : array());
        $attendance_show_all_members = !empty($registration_payload['attendance_show_all_members']);
        if (!$attendance_show_all_members && isset($event->attendance_show_all_members)) {
            $attendance_show_all_members = !empty($event->attendance_show_all_members);
        }

        $event_emoji = $this->getEventEmojiValue($event);

        wp_send_json_success(array(
            'event' => array(
                'id' => $event->id,
                'title' => $event->title,
                'slug' => $event->slug,
                'type' => $event->type,
                'emoji' => $event_emoji,
                'typeLabel' => isset($type_labels[$event->type]) ? $type_labels[$event->type] : $event->type,
                'status' => $event->status,
                'statusLabel' => isset($status_labels[$event->status]) ? $status_labels[$event->status] : $event->status,
                'description' => $event->description,
                'registrationDocument' => isset($event->registration_document) ? $event->registration_document : '',
                'socialPublishDescription' => isset($registration_payload['social_publish_description']) ? (string) $registration_payload['social_publish_description'] : '',
                'registrationPayload' => $registration_payload,
                'dateDebut' => $event->date_debut,
                'dateFin' => $event->date_fin,
                'dateDebutFormatted' => $this->formatDate($event->date_debut, true),
                'dateFinFormatted' => $this->formatDate($event->date_fin, true),
                'dateFinInscription' => $event->date_fin_inscription,
                'coverId' => $event->cover_id,
                'coverUrl' => $this->getEventCoverUrl($event, 'medium'),
                'coverFullUrl' => $this->getEventCoverUrl($event, 'full'),
                'accentColor' => $event->accent_color,
                'prix' => (float) $event->prix,
                'freeParticipation' => !empty($event->free_participation),
                'requiresValidation' => !empty($event->requires_validation),
                'allowGuardianRegistration' => !empty($event->allow_guardian_registration),
                'ageMin' => (int) ($event->age_min ?? 0),
                'ageMax' => (int) ($event->age_max ?? 99),
                'capacityTotal' => (int) ($event->capacity_total ?? 0),
                'capacityWaitlist' => (int) ($event->capacity_waitlist ?? 0),
                'registrationsCount' => $registrations_count,
                'scheduleMode' => $schedule_mode,
                'scheduleSummary' => isset($schedule_info['summary']) ? $schedule_info['summary'] : '',
                'scheduleDetail' => isset($schedule_info['detail']) ? $schedule_info['detail'] : '',
                'occurrences' => $occurrences,
                'occurrenceGenerator' => $occurrence_generator_plan,
                'isFromGenerator' => !empty($occurrence_generator_plan),
                'inlineScheduleHtml' => $inline_schedule_preview['html'],
                'location' => $location,
                'locationLinks' => $location_links,
                'animateurs' => $animateurs,
                'creator' => $creator,
                'frontUrl' => $front_url ?: null,
                'eventPageUrl' => !empty($event_page_url) ? $event_page_url : null,
                'articleId' => !empty($event->article_id) ? (int) $event->article_id : null,
                'occurrenceSelectionMode' => isset($event->occurrence_selection_mode) && $event->occurrence_selection_mode !== ''
                    ? $event->occurrence_selection_mode
                    : 'member_choice',
                'attendanceShowAllMembers' => $attendance_show_all_members,
            ),
        ));
    }

    public function getEventEditor() {
        $auth = $this->verifyRequest();
        if (!$auth) {
            return;
        }

        $event_id = isset($_POST['eventId']) ? (int) $_POST['eventId'] : 0;
        if ($event_id <= 0) {
            wp_send_json_error(array('message' => __('ID événement invalide.', 'mj-member')));
            return;
        }

        $event = MjEvents::find($event_id);
        if (!$event) {
            wp_send_json_error(array('message' => __('Événement introuvable.', 'mj-member')));
            return;
        }

        if (!$auth['is_coordinateur'] && !MjEventAnimateurs::member_is_assigned($event_id, $auth['member_id'])) {
            wp_send_json_error(array('message' => __('Permissions insuffisantes pour modifier cet événement.', 'mj-member')), 403);
            return;
        }

        $schedule_weekdays = $this->getScheduleWeekdays();
        $schedule_month_ordinals = $this->getScheduleMonthOrdinals();

        $form_values = $this->prepareEventFormValues($event, $schedule_weekdays, $schedule_month_ordinals);
        $references = $this->collectEventEditorAssets($event, $form_values);

        $status_labels = MjEvents::get_status_labels();
        $type_labels = MjEvents::get_type_labels();
        $type_colors = MjEvents::get_type_colors();

        $event_form_options = EventFormOptionsBuilder::build(array(
            'status_labels' => $status_labels,
            'type_labels' => $type_labels,
            'type_colors' => $type_colors,
            'current_type' => isset($form_values['type']) ? $form_values['type'] : '',
            'accent_default_color' => isset($form_values['accent_color']) ? $form_values['accent_color'] : '',
            'article_categories' => $references['article_categories'],
            'articles' => $references['articles'],
            'locations' => $references['locations'],
            'animateurs' => $references['animateurs'],
            'volunteers' => $references['volunteers'],
            'schedule_weekdays' => $schedule_weekdays,
            'schedule_month_ordinals' => $schedule_month_ordinals,
        ));

        $form_defaults = EventFormDataMapper::fromValues($form_values);

        $meta = array(
            'scheduleMode' => isset($form_values['schedule_mode']) ? $form_values['schedule_mode'] : 'fixed',
            'schedulePayload' => isset($form_values['schedule_payload']) ? $form_values['schedule_payload'] : array(),
            'scheduleWeekdayTimes' => isset($form_values['schedule_weekday_times']) ? $form_values['schedule_weekday_times'] : array(),
            'scheduleExceptions' => isset($form_values['schedule_exceptions']) ? array_values($form_values['schedule_exceptions']) : array(),
            'scheduleShowDateRange' => !empty($form_values['schedule_show_date_range']),
            'registrationPayload' => isset($form_values['registration_payload']) ? $form_values['registration_payload'] : array(),
        );

        wp_send_json_success(array(
            'event' => $this->serializeEventSummary($event),
            'form' => array(
                'values' => $form_defaults,
                'options' => $event_form_options,
                'meta' => $meta,
            ),
        ));
    }

    public function updateEvent() {
        $auth = $this->verifyRequest();
        if (!$auth) {
            return;
        }

        $decoded_json = null;
        $event_id = isset($_POST['eventId']) ? (int) $_POST['eventId'] : 0;

        $content_type = isset($_SERVER['CONTENT_TYPE']) ? strtolower((string) $_SERVER['CONTENT_TYPE']) : '';
        if (strpos($content_type, 'application/json') !== false) {
            $raw_body = file_get_contents('php://input');
            $decoded_candidate = json_decode($raw_body, true);
            if (is_array($decoded_candidate)) {
                $decoded_json = $decoded_candidate;
                if ($event_id <= 0 && isset($decoded_json['eventId'])) {
                    $event_id = (int) $decoded_json['eventId'];
                }
                if (!isset($_POST['form']) && isset($decoded_json['form']) && is_array($decoded_json['form'])) {
                    $_POST['form'] = $decoded_json['form'];
                }
                if (!isset($_POST['meta']) && isset($decoded_json['meta']) && is_array($decoded_json['meta'])) {
                    $_POST['meta'] = $decoded_json['meta'];
                }
            }
        }

        if ($event_id <= 0) {
            wp_send_json_error(array('message' => __('ID événement invalide.', 'mj-member')));
            return;
        }

        $event = MjEvents::find($event_id);
        if (!$event) {
            wp_send_json_error(array('message' => __('Événement introuvable.', 'mj-member')));
            return;
        }

        if (!$auth['is_coordinateur'] && !MjEventAnimateurs::member_is_assigned($event_id, $auth['member_id'])) {
            wp_send_json_error(array('message' => __('Permissions insuffisantes pour modifier cet événement.', 'mj-member')), 403);
            return;
        }

        $schedule_weekdays = $this->getScheduleWeekdays();
        $schedule_month_ordinals = $this->getScheduleMonthOrdinals();

        $form_values = $this->prepareEventFormValues($event, $schedule_weekdays, $schedule_month_ordinals);
        $references = $this->collectEventEditorAssets($event, $form_values);

        $form_input = array();
        if (isset($_POST['form']) && is_array($_POST['form'])) {
            $form_input = wp_unslash($_POST['form']);
        } else {
            foreach ($_POST as $key => $value) {
                if (!is_string($key) || strpos($key, 'event_') !== 0) {
                    continue;
                }
                $form_input[$key] = is_array($value) ? wp_unslash($value) : wp_unslash((string) $value);
            }
        }

        // DEBUG registration_document
        error_log('[MjRegMgr] form_input keys: ' . implode(', ', array_keys($form_input)));
        if (isset($form_input['event_registration_document'])) {
            error_log('[MjRegMgr] event_registration_document length: ' . strlen($form_input['event_registration_document']));
        } else {
            error_log('[MjRegMgr] event_registration_document NOT in form_input');
        }

        $meta = array();
        if (isset($_POST['meta']) && is_array($_POST['meta'])) {
            $meta = wp_unslash($_POST['meta']);
        } elseif (is_array($decoded_json) && isset($decoded_json['meta']) && is_array($decoded_json['meta'])) {
            $meta = $decoded_json['meta'];
        }

        if (!empty($form_input)) {
            $form_values = EventFormDataMapper::mergeIntoValues($form_values, $form_input);
        }

        // DEBUG après merge
        if (isset($form_values['registration_document'])) {
            error_log('[MjRegMgr] After merge - registration_document length: ' . strlen($form_values['registration_document']));
        } else {
            error_log('[MjRegMgr] After merge - registration_document NOT SET');
        }

        if (isset($meta['scheduleWeekdayTimes'])) {
            $form_values['schedule_weekday_times'] = $this->sanitizeWeekdayTimes($meta['scheduleWeekdayTimes'], $schedule_weekdays);
        }
        if (isset($meta['scheduleShowDateRange'])) {
            $form_values['schedule_show_date_range'] = $this->toBool($meta['scheduleShowDateRange'], false);
        }
        if (isset($meta['scheduleExceptions'])) {
            $form_values['schedule_exceptions'] = $this->sanitizeRecurrenceExceptions($meta['scheduleExceptions']);
        }
        if (isset($meta['registrationPayload'])) {
            if (is_array($meta['registrationPayload'])) {
                $form_values['registration_payload'] = $meta['registrationPayload'];
            } else {
                $form_values['registration_payload'] = $this->decodeJsonField($meta['registrationPayload']);
            }
        }

        $errors = array();
        $build = $this->buildEventUpdatePayload($event, $form_values, $meta, $references, $schedule_weekdays, $schedule_month_ordinals, $errors);

        if (!empty($errors)) {
            wp_send_json_error(array('errors' => $errors));
            return;
        }

        if (empty($build) || empty($build['payload']) || !is_array($build['payload'])) {
            wp_send_json_error(array('message' => __('Données de mise à jour invalides.', 'mj-member')));
            return;
        }

        $update_result = MjEvents::update($event_id, $build['payload']);
        if (is_wp_error($update_result)) {
            wp_send_json_error(array('message' => $update_result->get_error_message()));
            return;
        }

        $animateur_ids = isset($build['animateur_ids']) && is_array($build['animateur_ids']) ? $build['animateur_ids'] : array();
        $volunteer_ids = isset($build['volunteer_ids']) && is_array($build['volunteer_ids']) ? $build['volunteer_ids'] : array();

        if (class_exists(MjEventAnimateurs::class)) {
            MjEventAnimateurs::sync_for_event($event_id, $animateur_ids);
        }
        if (class_exists(MjEventVolunteers::class)) {
            MjEventVolunteers::sync_for_event($event_id, $volunteer_ids);
        }

        // Sync location links if provided
        $location_links = isset($build['location_links']) && is_array($build['location_links']) ? $build['location_links'] : null;
        if ($location_links !== null && class_exists(MjEventLocationLinks::class)) {
            MjEventLocationLinks::sync_for_event($event_id, $location_links);
        }

        $updated_event = MjEvents::find($event_id);
        if (!$updated_event) {
            wp_send_json_success(array('message' => __('Événement mis à jour.', 'mj-member')));
            return;
        }

        $updated_form_values = $this->prepareEventFormValues($updated_event, $schedule_weekdays, $schedule_month_ordinals);
        $updated_references = $this->collectEventEditorAssets($updated_event, $updated_form_values);

        $status_labels = MjEvents::get_status_labels();
        $type_labels = MjEvents::get_type_labels();
        $type_colors = MjEvents::get_type_colors();

        $updated_options = EventFormOptionsBuilder::build(array(
            'status_labels' => $status_labels,
            'type_labels' => $type_labels,
            'type_colors' => $type_colors,
            'current_type' => isset($updated_form_values['type']) ? $updated_form_values['type'] : '',
            'accent_default_color' => isset($updated_form_values['accent_color']) ? $updated_form_values['accent_color'] : '',
            'article_categories' => $updated_references['article_categories'],
            'articles' => $updated_references['articles'],
            'locations' => $updated_references['locations'],
            'animateurs' => $updated_references['animateurs'],
            'volunteers' => $updated_references['volunteers'],
            'schedule_weekdays' => $schedule_weekdays,
            'schedule_month_ordinals' => $schedule_month_ordinals,
        ));

        $response_meta = array(
            'scheduleMode' => isset($updated_form_values['schedule_mode']) ? $updated_form_values['schedule_mode'] : 'fixed',
            'schedulePayload' => isset($updated_form_values['schedule_payload']) ? $updated_form_values['schedule_payload'] : array(),
            'scheduleWeekdayTimes' => isset($updated_form_values['schedule_weekday_times']) ? $updated_form_values['schedule_weekday_times'] : array(),
            'scheduleExceptions' => isset($updated_form_values['schedule_exceptions']) ? array_values($updated_form_values['schedule_exceptions']) : array(),
            'scheduleShowDateRange' => !empty($updated_form_values['schedule_show_date_range']),
            'registrationPayload' => isset($updated_form_values['registration_payload']) ? $updated_form_values['registration_payload'] : array(),
        );

        wp_send_json_success(array(
            'message' => __('Événement mis à jour.', 'mj-member'),
            'event' => $this->serializeEventSummary($updated_event),
            'form' => array(
                'values' => EventFormDataMapper::fromValues($updated_form_values),
                'options' => $updated_options,
                'meta' => $response_meta,
            ),
        ));
    }

    /**
     * Create a new draft event
     */
    public function createEvent() {
        $auth = $this->verifyRequest();
        if (!$auth) {
            return;
        }

        if (!current_user_can(Config::capability())) {
            wp_send_json_error(array('message' => __('Permissions insuffisantes pour créer un événement.', 'mj-member')), 403);
            return;
        }

        $raw_title = isset($_POST['title']) ? wp_unslash($_POST['title']) : '';
        $title = sanitize_text_field($raw_title);
        $title = trim($title);

        if ($type === 'description') {
            $default_description_prompt = (string) get_option('mj_member_ai_description_prompt', get_option('mj_ai_description_prompt', ''));
            if ($default_description_prompt === '') {
                $default_description_prompt = sprintf(
                    'Tu es un assistant rédacteur pour une association jeunesse (%s). Tu rédiges des descriptions d\'événements en français, de manière claire, engageante et adaptée à un public familial. Réponds uniquement avec le texte de la description, sans titre ni introduction.',
                    $site_name
                );
            }

            $system_prompt = apply_filters('mj_member_ai_description_system_prompt', $default_description_prompt);
            $user_prompt = sprintf(
                "Rédige une description attrayante pour l'événement suivant :\n\n%s",
                $event_context
            );
        } else {
            $default_regdoc_prompt = (string) get_option('mj_member_ai_regdoc_prompt', get_option('mj_ai_regdoc_prompt', ''));
            if ($default_regdoc_prompt === '') {
                $default_regdoc_prompt = sprintf(
                    'Tu es un assistant pour une association jeunesse (%s). Tu rédiges des documents d\'inscription en français. Le document doit contenir les informations essentielles sur l\'événement et les instructions pour les participants. Utilise les variables entre crochets (ex : [member_name], [event_name]) pour personnaliser le document. Réponds uniquement avec le contenu du document.',
                    $site_name
                );
            }

            $system_prompt = apply_filters('mj_member_ai_regdoc_system_prompt', $default_regdoc_prompt);
            $user_prompt = sprintf(
                "Rédige un document d'inscription complet pour l'événement suivant :\n\n%s",
                $event_context
            );
        }

        $result = $client->generateText($system_prompt, $user_prompt, array('max_tokens' => 1200));
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 503);
            return;
        }

        wp_send_json_success(array(
            'text' => $result['text'],
            'type' => $type,
        ));
    }

    /**
     * Delete an event and its dependencies
     */
    public function deleteEvent() {
        $auth = $this->verifyRequest();
        if (!$auth) {
            return;
        }

        $event_id = isset($_POST['eventId']) ? (int) $_POST['eventId'] : 0;
        if ($event_id <= 0) {
            wp_send_json_error(array('message' => __('ID événement invalide.', 'mj-member')));
            return;
        }

        if (!$auth['is_coordinateur'] && !current_user_can(Config::capability())) {
            wp_send_json_error(array('message' => __('Permissions insuffisantes pour supprimer cet événement.', 'mj-member')), 403);
            return;
        }

        $event = MjEvents::find($event_id);
        if (!$event) {
            wp_send_json_error(array('message' => __('Événement introuvable.', 'mj-member')), 404);
            return;
        }

        $result = MjEvents::delete($event_id);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 500);
            return;
        }

        wp_send_json_success(array(
            'eventId' => $event_id,
            'message' => __('Événement supprimé.', 'mj-member'),
        ));
    }

    /**
     * Get registrations for an event
     */
    public function getRegistrations() {
        $auth = $this->verifyRequest();
        if (!$auth) return;

        $event_id = isset($_POST['eventId']) ? (int) $_POST['eventId'] : 0;
        if ($event_id <= 0) {
            wp_send_json_error(array('message' => __('ID événement invalide.', 'mj-member')));
            return;
        }

        $event = MjEvents::find($event_id);
        $registration_payload = $event ? $this->decodeJsonField(isset($event->registration_payload) ? $event->registration_payload : array()) : array();
        $attendance_show_all_members = !empty($registration_payload['attendance_show_all_members']);
        if (!$attendance_show_all_members && $event && isset($event->attendance_show_all_members)) {
            $attendance_show_all_members = !empty($event->attendance_show_all_members);
        }

        $registrations = MjEventRegistrations::get_by_event($event_id);
    
        $data = array();
        $attendance_members = array();
        $existing_member_ids = array();
        $now = new DateTime();

        $build_member_payload = function ($member) use ($now) {
            if (!$member) {
                return null;
            }

            $member_id = isset($member->id) ? (int) $member->id : 0;
            if ($member_id <= 0) {
                return null;
            }

            $birth_date = isset($member->birth_date) ? $member->birth_date : null;
            $age = null;
            if (!empty($birth_date) && $birth_date !== '0000-00-00') {
                try {
                    $birth = new DateTime($birth_date);
                    $age = $now->diff($birth)->y;
                } catch (\Exception $e) {
                    $age = null;
                }
            }

            $subscription_status = 'none';
            $last_payment_raw = isset($member->date_last_payement) ? $member->date_last_payement : '';
            if (!empty($last_payment_raw) && $last_payment_raw !== '0000-00-00 00:00:00') {
                try {
                    $last_payment = new DateTime($last_payment_raw);
                    $expiry = clone $last_payment;
                    $expiry->modify('+1 year');
                    $subscription_status = $expiry > $now ? 'active' : 'expired';
                } catch (\Exception $e) {
                    $subscription_status = 'none';
                }
            }

            $role = isset($member->role) ? (string) $member->role : '';
            $photo_id = isset($member->photo_id) ? (int) $member->photo_id : 0;
            $photo_url = $photo_id > 0 ? wp_get_attachment_image_url($photo_id, 'thumbnail') : '';

            // Guardian phone: fetch from the member's linked guardian
            $guardian_phone = '';
            $guardian_whatsapp_opt_in = true;
            $guardian_id_val = isset($member->guardian_id) ? (int) $member->guardian_id : 0;
            if ($guardian_id_val > 0) {
                $member_guardian = MjMembers::getById($guardian_id_val);
                if ($member_guardian) {
                    $guardian_phone = isset($member_guardian->phone) ? (string) $member_guardian->phone : '';
                    $guardian_whatsapp_opt_in = isset($member_guardian->whatsapp_opt_in) ? ((int) $member_guardian->whatsapp_opt_in !== 0) : true;
                }
            }

            return array(
                'id' => $member_id,
                'firstName' => isset($member->first_name) ? (string) $member->first_name : '',
                'lastName' => isset($member->last_name) ? (string) $member->last_name : '',
                'nickname' => isset($member->nickname) ? (string) $member->nickname : '',
                'email' => isset($member->email) ? (string) $member->email : '',
                'phone' => isset($member->phone) ? (string) $member->phone : '',
                'address' => isset($member->address) ? (string) $member->address : '',
                'postalCode' => isset($member->postal_code) ? (string) $member->postal_code : '',
                'city' => isset($member->city) ? (string) $member->city : '',
                'role' => $role,
                'roleLabel' => MjRoles::getRoleLabel($role),
                'photoId' => $photo_id,
                'photoUrl' => $photo_url ?: '',
                'age' => $age,
                'birthDate' => $birth_date ?: '',
                'subscriptionStatus' => $subscription_status,
                'whatsappOptIn' => isset($member->whatsapp_opt_in) ? ((int) $member->whatsapp_opt_in !== 0) : true,
                'isVolunteer' => !empty($member->is_volunteer),
                'guardianPhone' => $guardian_phone,
                'guardianWhatsappOptIn' => $guardian_whatsapp_opt_in,
            );
        };

        foreach ($registrations as $reg) {
            $member = null;
            $guardian = null;

            // Count dynfields shown in notes that have a non-empty value
            $dyn_fields_count = 0;
            if (!empty($reg->member_id)) {
                if (!isset($dyn_note_field_ids)) {
                    $dyn_note_field_ids = array();
                    foreach (\Mj\Member\Classes\Crud\MjDynamicFields::getAll() as $_df) {
                        if (!empty($_df->show_in_notes) && $_df->field_type !== 'title') {
                            $dyn_note_field_ids[] = (int) $_df->id;
                        }
                    }
                }
                if (!empty($dyn_note_field_ids)) {
                    $dv = \Mj\Member\Classes\Crud\MjDynamicFieldValues::getByMemberKeyed((int) $reg->member_id);
                    foreach ($dyn_note_field_ids as $_fid) {
                        if (!empty($dv[$_fid])) {
                            $dyn_fields_count++;
                        }
                    }
                }
            }
        
            if (!empty($reg->member_id)) {
                $member = MjMembers::getById($reg->member_id);
            }
        
            // Always prefer the member's CURRENT guardian_id (source of truth,
            // may have been updated after the registration was created).
            // Fall back to the registration's guardian_id only when the member
            // record does not reference a guardian.
            $resolved_guardian_id = 0;
            if ($member && !empty($member->guardian_id)) {
                $resolved_guardian_id = (int) $member->guardian_id;
            }
            if ($resolved_guardian_id <= 0 && !empty($reg->guardian_id)) {
                $resolved_guardian_id = (int) $reg->guardian_id;
            }
            if ($resolved_guardian_id > 0) {
                $guardian = MjMembers::getById($resolved_guardian_id);
            }

            $member_payload = $build_member_payload($member);
            if ($member_payload && isset($member_payload['id'])) {
                $existing_member_ids[(int) $member_payload['id']] = true;
            }

            // Get attendance data
            $attendance = array();
            if (!empty($reg->attendance_payload)) {
                $payload = json_decode($reg->attendance_payload, true);
                if (is_array($payload) && isset($payload['occurrences'])) {
                    $attendance = $payload['occurrences'];
                }
            }

            // Get assigned occurrences
            $assigned_occurrences = array();
            if (!empty($reg->selected_occurrences)) {
                $assigned_occurrences = json_decode($reg->selected_occurrences, true);
                if (!is_array($assigned_occurrences)) {
                    $assigned_occurrences = array();
                }
            }

            // Fallback: read from attendance_payload assignments when selected_occurrences is empty
            if (empty($assigned_occurrences)) {
                $att_assignments = MjEventAttendance::get_registration_assignments($reg);
                if (isset($att_assignments['mode']) && $att_assignments['mode'] === 'custom' && !empty($att_assignments['occurrences'])) {
                    $assigned_occurrences = $att_assignments['occurrences'];
                }
            }

            // Count notes for this member
            $notes_count = 0;
            if (!empty($reg->member_id)) {
                global $wpdb;
                $notes_table = $wpdb->prefix . 'mj_member_notes';
                $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $notes_table));
                if ($table_exists) {
                    $notes_count = (int) $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM {$notes_table} WHERE member_id = %d",
                        $reg->member_id
                    ));
                }
            }

            $guardian_payload = null;
            if ($guardian) {
                $guardian_payload = array(
                    'id' => $guardian->id,
                    'firstName' => $guardian->first_name,
                    'lastName' => $guardian->last_name,
                    'email' => isset($guardian->email) ? $guardian->email : '',
                    'phone' => isset($guardian->phone) ? $guardian->phone : '',
                    'address' => isset($guardian->address) ? (string) $guardian->address : '',
                    'postalCode' => isset($guardian->postal_code) ? (string) $guardian->postal_code : '',
                    'city' => isset($guardian->city) ? (string) $guardian->city : '',
                    'whatsappOptIn' => isset($guardian->whatsapp_opt_in) ? ((int) $guardian->whatsapp_opt_in !== 0) : true,
                );
            }

            $contract_email_status = $this->extractContractEmailStatus(isset($reg->attendance_payload) ? $reg->attendance_payload : null);

            $data[] = array(
                'id' => $reg->id,
                'eventId' => $reg->event_id,
                'memberId' => $reg->member_id,
                'guardianId' => $resolved_guardian_id ?: ($reg->guardian_id ?? 0),
                'status' => $reg->statut,
                'statusLabel' => MjEventRegistrations::META_STATUS_LABELS[$reg->statut] ?? $reg->statut,
                'paymentStatus' => $reg->payment_status ?? 'unpaid',
                'paymentMethod' => $reg->payment_method ?? '',
                'paymentRecordedAt' => $reg->payment_recorded_at ?? '',
                'notes' => $reg->notes ?? '',
                'createdAt' => $reg->created_at,
                'createdAtFormatted' => $this->formatDate($reg->created_at, true),
                'member' => $member_payload,
                'guardian' => $guardian_payload,
                'attendance' => $attendance,
                'occurrences' => $assigned_occurrences,
                'assignedOccurrences' => $assigned_occurrences,
                'notesCount' => $notes_count,
                'dynFieldsCount' => $dyn_fields_count,
                'contractEmailStatus' => $contract_email_status,
            );
        }

        if ($attendance_show_all_members) {
            $all_members = MjMembers::get_all(array(
                'limit' => 0,
                'orderby' => 'last_name',
                'order' => 'ASC',
            ));

            foreach ($all_members as $member) {
                $member_payload = $build_member_payload($member);
                if (!$member_payload) {
                    continue;
                }

                $member_id = isset($member_payload['id']) ? (int) $member_payload['id'] : 0;
                if ($member_id <= 0 || isset($existing_member_ids[$member_id])) {
                    continue;
                }

                if (isset($member->status) && $member->status !== MjMembers::STATUS_ACTIVE) {
                    continue;
                }

                $attendance_members[] = $member_payload;
            }
        }

        wp_send_json_success(array(
            'registrations' => $data,
            'attendanceMembers' => $attendance_members,
        ));
    }

    /**
     * Search members for adding to event
     */
    public function searchMembers() {
        $auth = $this->verifyRequest();
        if (!$auth) return;

        $event_id = isset($_POST['eventId']) ? (int) $_POST['eventId'] : 0;
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $age_range = isset($_POST['ageRange']) ? sanitize_text_field($_POST['ageRange']) : '';
        $subscription_filter = isset($_POST['subscriptionFilter']) ? sanitize_key($_POST['subscriptionFilter']) : '';
        $role_filter = isset($_POST['role']) ? sanitize_key($_POST['role']) : '';
        $page = isset($_POST['page']) ? max(1, (int) $_POST['page']) : 1;
        $per_page = 50;

        // Get event for age restrictions
        $event = null;
        if ($event_id > 0) {
            $event = MjEvents::find($event_id);
        }

        // Get existing registrations for this event
        $existing_member_ids = array();
        if ($event_id > 0) {
            $existing_regs = MjEventRegistrations::get_by_event($event_id);
            foreach ($existing_regs as $reg) {
                if (!empty($reg->member_id)) {
                    $existing_member_ids[] = (int) $reg->member_id;
                }
            }
        }

        $filters = array();
    
        // Age range filter
        if ($age_range !== '') {
            $filters['age_range'] = $age_range;
        }

        // Subscription filter
        if ($subscription_filter !== '') {
            $filters['subscription_status'] = $subscription_filter;
        }

        if ($role_filter !== '') {
            $filters['role'] = $role_filter;
        }

        $members = MjMembers::get_all(array(
            'search' => $search,
            'filters' => $filters,
            'limit' => $per_page,
            'offset' => ($page - 1) * $per_page,
            'orderby' => 'last_name',
            'order' => 'ASC',
        ));

        $data = array();
        $now = new DateTime();

        foreach ($members as $member) {
            // Calculate age
            $age = null;
            if (!empty($member->birth_date)) {
                $birth = new DateTime($member->birth_date);
                $age = $now->diff($birth)->y;
            }

            // Check age restrictions
            $age_restriction = null;
            if ($event && $age !== null) {
                $age_min = (int) ($event->age_min ?? 0);
                $age_max = (int) ($event->age_max ?? 99);
            
                if ($age < $age_min) {
                    $age_restriction = sprintf(__('Âge inférieur au minimum (%d ans).', 'mj-member'), $age_min);
                } elseif ($age > $age_max) {
                    $age_restriction = sprintf(__('Âge supérieur au maximum (%d ans).', 'mj-member'), $age_max);
                }
            }

            // Check if tutor role allowed
            $role_restriction = null;
            if ($event && $member->role === MjRoles::TUTEUR && empty($event->allow_guardian_registration)) {
                $role_restriction = __('Rôle tuteur non autorisé pour cet événement.', 'mj-member');
            }

            // Check subscription status
            $subscription_status = 'none';
            if (!empty($member->date_last_payement)) {
                $last_payment = new DateTime($member->date_last_payement);
                $expiry = clone $last_payment;
                $expiry->modify('+1 year');
                $subscription_status = $expiry > $now ? 'active' : 'expired';
            }

            // Check if already registered
            $already_registered = in_array($member->id, $existing_member_ids, true);

            $data[] = array(
                'id' => $member->id,
                'firstName' => $member->first_name,
                'lastName' => $member->last_name,
                'nickname' => $member->nickname ?? '',
                'email' => $member->email ?? '',
                'role' => $member->role,
                'roleLabel' => MjRoles::getRoleLabel($member->role),
                'photoId' => $member->photo_id ?? 0,
                'photoUrl' => !empty($member->photo_id) ? wp_get_attachment_image_url($member->photo_id, 'thumbnail') : '',
                'age' => $age,
                'subscriptionStatus' => $subscription_status,
                'alreadyRegistered' => $already_registered,
                'ageRestriction' => $age_restriction,
                'roleRestriction' => $role_restriction,
                'guardianId' => isset($member->guardian_id) ? (int) $member->guardian_id : 0,
            );
        }

        $total = MjMembers::count(array(
            'search' => $search,
            'filters' => $filters,
        ));

        wp_send_json_success(array(
            'members' => $data,
            'pagination' => array(
                'total' => $total,
                'page' => $page,
                'perPage' => $per_page,
                'totalPages' => ceil($total / $per_page),
            ),
        ));
    }

    /**
     * Add registration(s) to event
     */
    public function addRegistration() {
        $auth = $this->verifyRequest();
        if (!$auth) return;

        $event_id = isset($_POST['eventId']) ? (int) $_POST['eventId'] : 0;
        $member_ids = isset($_POST['memberIds']) ? array_map('intval', (array) $_POST['memberIds']) : array();
        $occurrences = isset($_POST['occurrences']) ? (array) $_POST['occurrences'] : array();

        if ($event_id <= 0) {
            wp_send_json_error(array('message' => __('ID événement invalide.', 'mj-member')));
            return;
        }

        if (empty($member_ids)) {
            wp_send_json_error(array('message' => __('Aucun membre sélectionné.', 'mj-member')));
            return;
        }

        $event = MjEvents::find($event_id);
        if (!$event) {
            wp_send_json_error(array('message' => __('Événement introuvable.', 'mj-member')));
            return;
        }

        $added = 0;
        $errors = array();

        foreach ($member_ids as $member_id) {
            // Check if already registered
            $existing = MjEventRegistrations::get_all(array(
                'event_id' => $event_id,
                'member_id' => $member_id,
            ));

            if (!empty($existing)) {
                $member = MjMembers::getById($member_id);
                $name = $member ? trim($member->first_name . ' ' . $member->last_name) : "ID $member_id";
                $errors[] = sprintf(__('%s est déjà inscrit.', 'mj-member'), $name);
                continue;
            }

            $data = array(
                'event_id' => $event_id,
                'member_id' => $member_id,
                'statut' => $event->requires_validation ? MjEventRegistrations::STATUS_PENDING : MjEventRegistrations::STATUS_CONFIRMED,
                'payment_status' => ((float) $event->prix > 0 && empty($event->free_participation)) ? 'unpaid' : 'paid',
                'allow_late_registration' => true, // Allow retrospective registration from admin
            );

            if (!empty($occurrences)) {
                $data['selected_occurrences'] = wp_json_encode($occurrences);
            }

            $result = MjEventRegistrations::create($data);

            if (is_wp_error($result)) {
                $member = MjMembers::getById($member_id);
                $name = $member ? trim($member->first_name . ' ' . $member->last_name) : "ID $member_id";
                $errors[] = sprintf(__('Erreur pour %s: %s', 'mj-member'), $name, $result->get_error_message());
            } else {
                $added++;
            }
        }

        if ($added === 0 && !empty($errors)) {
            wp_send_json_error(array('message' => implode("\n", $errors)));
            return;
        }

        $message = sprintf(_n('%d inscription ajoutée.', '%d inscriptions ajoutées.', $added, 'mj-member'), $added);
        if (!empty($errors)) {
            $message .= "\n" . implode("\n", $errors);
        }

        wp_send_json_success(array(
            'message' => $message,
            'added' => $added,
        ));
    }

    /**
     * Update registration status
     */
    public function updateRegistration() {
        $auth = $this->verifyRequest();
        if (!$auth) return;

        $registration_id = isset($_POST['registrationId']) ? (int) $_POST['registrationId'] : 0;
        $status = isset($_POST['status']) ? sanitize_key($_POST['status']) : '';

        if ($registration_id <= 0) {
            wp_send_json_error(array('message' => __('ID inscription invalide.', 'mj-member')));
            return;
        }

        $valid_statuses = array_keys(MjEventRegistrations::META_STATUS_LABELS);
        if (!in_array($status, $valid_statuses, true)) {
            wp_send_json_error(array('message' => __('Statut invalide.', 'mj-member')));
            return;
        }

        $result = MjEventRegistrations::update($registration_id, array('statut' => $status));

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
            return;
        }

        wp_send_json_success(array(
            'message' => __('Inscription mise à jour.', 'mj-member'),
        ));
    }

    /**
     * Delete registration
     */
    public function deleteRegistration() {
        $auth = $this->verifyRequest();
        if (!$auth) return;

        $registration_id = isset($_POST['registrationId']) ? (int) $_POST['registrationId'] : 0;

        if ($registration_id <= 0) {
            wp_send_json_error(array('message' => __('ID inscription invalide.', 'mj-member')));
            return;
        }

        $result = MjEventRegistrations::delete($registration_id);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
            return;
        }

        wp_send_json_success(array(
            'message' => __('Inscription supprimée.', 'mj-member'),
        ));
    }

    /**
     * Update registration occurrence assignments
     */
    public function updateRegistrationOccurrences() {
        $auth = $this->verifyRequest();
        if (!$auth) return;

        $registration_id = isset($_POST['registrationId']) ? (int) $_POST['registrationId'] : 0;
        $mode = isset($_POST['mode']) ? sanitize_key($_POST['mode']) : 'all';
        $occurrences = isset($_POST['occurrences']) ? $_POST['occurrences'] : array();

        if ($registration_id <= 0) {
            wp_send_json_error(array('message' => __('ID inscription invalide.', 'mj-member')));
            return;
        }

        // Validate mode
        if (!in_array($mode, array('all', 'custom'), true)) {
            $mode = 'all';
        }

        // Parse occurrences
        $parsed_occurrences = array();
        if ($mode === 'custom') {
            if (is_string($occurrences)) {
                $decoded = json_decode(stripslashes($occurrences), true);
                if (is_array($decoded)) {
                    $occurrences = $decoded;
                }
            }

            if (is_array($occurrences)) {
                foreach ($occurrences as $occurrence) {
                    $normalized = MjEventAttendance::normalize_occurrence($occurrence);
                    if ($normalized !== '' && !in_array($normalized, $parsed_occurrences, true)) {
                        $parsed_occurrences[] = $normalized;
                    }
                }
            }
        }

        $assignments = array(
            'mode' => $mode,
            'occurrences' => $parsed_occurrences,
        );

        $result = MjEventAttendance::set_registration_assignments($registration_id, $assignments);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
            return;
        }

        wp_send_json_success(array(
            'message' => __('Occurrences mises à jour.', 'mj-member'),
            'assignments' => $assignments,
        ));
    }

    /**
     * Update attendance for a single registration/occurrence
     */
    private function eventAllowsAttendanceWithoutRegistration($event) {
        if (!$event) {
            return false;
        }

        $registration_payload = $this->decodeJsonField(isset($event->registration_payload) ? $event->registration_payload : array());
        $attendance_show_all_members = !empty($registration_payload['attendance_show_all_members']);
        if (!$attendance_show_all_members && isset($event->attendance_show_all_members)) {
            $attendance_show_all_members = !empty($event->attendance_show_all_members);
        }

        return $attendance_show_all_members;
    }

    /**
     * Ensure a lightweight registration exists so attendance can be recorded.
     *
     * @param object $event
     * @param int    $member_id
     * @return int|WP_Error
     */
    private function ensureAttendanceRegistration($event, $member_id) {
        $event_id = isset($event->id) ? (int) $event->id : 0;
        $member_id = (int) $member_id;

        if ($event_id <= 0 || $member_id <= 0) {
            return new WP_Error('mj_regmgr_attendance_invalid_args', __('Paramètres de présence invalides.', 'mj-member'));
        }

        $existing = MjEventRegistrations::get_existing($event_id, $member_id);
        if ($existing && isset($existing->id)) {
            return (int) $existing->id;
        }

        if (!function_exists('mj_member_get_event_registrations_table_name')) {
            return new WP_Error('mj_regmgr_attendance_missing_table', __('Table des inscriptions introuvable.', 'mj-member'));
        }

        $table = mj_member_get_event_registrations_table_name();
        if (!$table) {
            return new WP_Error('mj_regmgr_attendance_missing_table', __('Table des inscriptions introuvable.', 'mj-member'));
        }

        $guardian_id = null;
        $member = MjMembers::getById($member_id);
        if ($member && !empty($member->guardian_id)) {
            $guardian_id = (int) $member->guardian_id;
        }

        $now = current_time('mysql');

        $insert = array(
            'event_id' => $event_id,
            'member_id' => $member_id,
        );
        $formats = array('%d', '%d');

        if ($guardian_id) {
            $insert['guardian_id'] = $guardian_id;
            $formats[] = '%d';
        }

        $insert['statut'] = MjEventRegistrations::STATUS_CONFIRMED;
        $formats[] = '%s';

        $insert['created_at'] = $now;
        $formats[] = '%s';

        global $wpdb;
        $result = $wpdb->insert($table, $insert, $formats);
        if ($result === false) {
            return new WP_Error('mj_regmgr_attendance_insert_failed', __('Impossible de créer une inscription automatique.', 'mj-member'));
        }

        return (int) $wpdb->insert_id;
    }

    public function updateAttendance() {
        $auth = $this->verifyRequest();
        if (!$auth) return;

        $event_id = isset($_POST['eventId']) ? (int) $_POST['eventId'] : 0;
        $member_id = isset($_POST['memberId']) ? (int) $_POST['memberId'] : 0;
        $occurrence = isset($_POST['occurrence']) ? sanitize_text_field($_POST['occurrence']) : '';
        $status = isset($_POST['status']) ? sanitize_key($_POST['status']) : '';

        if ($event_id <= 0 || $member_id <= 0) {
            wp_send_json_error(array('message' => __('Paramètres invalides.', 'mj-member')));
            return;
        }

        $valid_statuses = array(
            MjEventAttendance::STATUS_PRESENT,
            MjEventAttendance::STATUS_ABSENT,
            MjEventAttendance::STATUS_PENDING,
            '', // Allow clearing
        );

        if (!in_array($status, $valid_statuses, true)) {
            wp_send_json_error(array('message' => __('Statut invalide.', 'mj-member')));
            return;
        }

        $result = MjEventAttendance::record($event_id, $member_id, $occurrence, $status, array(
            'recorded_by' => $auth['member_id'],
        ));

        if (is_wp_error($result)) {
            $error_code = $result->get_error_code();

            if ($error_code === 'mj_event_attendance_missing_registration') {
                if ($status === '') {
                    wp_send_json_success(array(
                        'message' => __('Présence mise à jour.', 'mj-member'),
                    ));
                    return;
                }

                $event = MjEvents::find($event_id);
                if ($event && $this->eventAllowsAttendanceWithoutRegistration($event)) {
                    $registration_id = $this->ensureAttendanceRegistration($event, $member_id);
                    if (is_wp_error($registration_id)) {
                        wp_send_json_error(array('message' => $registration_id->get_error_message()));
                        return;
                    }

                    $result = MjEventAttendance::record($event_id, $member_id, $occurrence, $status, array(
                        'recorded_by' => $auth['member_id'],
                        'registration_id' => $registration_id,
                    ));
                }
            }

            if (is_wp_error($result)) {
                wp_send_json_error(array('message' => $result->get_error_message()));
                return;
            }
        }

        wp_send_json_success(array(
            'message' => __('Présence mise à jour.', 'mj-member'),
        ));
    }

    /**
     * Bulk update attendance for multiple members
     */
    public function bulkAttendance() {
        $auth = $this->verifyRequest();
        if (!$auth) return;

        $event_id = isset($_POST['eventId']) ? (int) $_POST['eventId'] : 0;
        $occurrence = isset($_POST['occurrence']) ? sanitize_text_field($_POST['occurrence']) : '';
    
        // Handle updates - may come as JSON string or array
        $updates_raw = isset($_POST['updates']) ? $_POST['updates'] : array();
        if (is_string($updates_raw)) {
            $updates = json_decode(stripslashes($updates_raw), true);
            if (!is_array($updates)) {
                $updates = array();
            }
        } else {
            $updates = (array) $updates_raw;
        }

        if ($event_id <= 0) {
            wp_send_json_error(array('message' => __('ID événement invalide.', 'mj-member')));
            return;
        }

        if (empty($updates)) {
            wp_send_json_error(array('message' => __('Aucune mise à jour fournie.', 'mj-member')));
            return;
        }

        $success = 0;
        $errors = 0;

        $event = MjEvents::find($event_id);
        $allow_virtual_registrations = $this->eventAllowsAttendanceWithoutRegistration($event);

        foreach ($updates as $update) {
            $member_id = isset($update['memberId']) ? (int) $update['memberId'] : 0;
            $status = isset($update['status']) ? sanitize_key($update['status']) : '';

            if ($member_id <= 0) {
                $errors++;
                continue;
            }

            $result = MjEventAttendance::record($event_id, $member_id, $occurrence, $status, array(
                'recorded_by' => $auth['member_id'],
            ));

            if (is_wp_error($result)) {
                $error_code = $result->get_error_code();

                if ($error_code === 'mj_event_attendance_missing_registration') {
                    if ($status === '') {
                        $success++;
                        continue;
                    }

                    if ($allow_virtual_registrations && $event) {
                        $registration_id = $this->ensureAttendanceRegistration($event, $member_id);
                        if (is_wp_error($registration_id)) {
                            $errors++;
                            continue;
                        }

                        $result = MjEventAttendance::record($event_id, $member_id, $occurrence, $status, array(
                            'recorded_by' => $auth['member_id'],
                            'registration_id' => $registration_id,
                        ));
                    }
                }

                if (is_wp_error($result)) {
                    $errors++;
                    continue;
                }
            }

            $success++;
        }

        if ($success === 0 && $errors > 0) {
            wp_send_json_error(array('message' => __('Échec de la mise à jour des présences.', 'mj-member')));
            return;
        }

        wp_send_json_success(array(
            'message' => sprintf(__('%d présence(s) mise(s) à jour.', 'mj-member'), $success),
            'success' => $success,
            'errors' => $errors,
        ));
    }

    /**
     * Validate payment manually
     */
    public function validatePayment() {
        $auth = $this->verifyRequest();
        if (!$auth) return;

        $registration_id = isset($_POST['registrationId']) ? (int) $_POST['registrationId'] : 0;
        $payment_method = isset($_POST['paymentMethod']) ? sanitize_text_field($_POST['paymentMethod']) : 'manual';

        if ($registration_id <= 0) {
            wp_send_json_error(array('message' => __('ID inscription invalide.', 'mj-member')));
            return;
        }

        $result = MjEventRegistrations::update($registration_id, array(
            'status' => 'valide',
            'payment_status' => 'paid',
            'payment_method' => $payment_method,
            'payment_recorded_at' => current_time('mysql'),
            'payment_recorded_by' => $auth['member_id'],
        ));

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
            return;
        }

        wp_send_json_success(array(
            'message' => __('Paiement validé.', 'mj-member'),
        ));
    }

    /**
     * Cancel payment (set back to unpaid)
     */
    public function cancelPayment() {
        $auth = $this->verifyRequest();
        if (!$auth) return;

        $registration_id = isset($_POST['registrationId']) ? (int) $_POST['registrationId'] : 0;

        if ($registration_id <= 0) {
            wp_send_json_error(array('message' => __('ID inscription invalide.', 'mj-member')));
            return;
        }

        $result = MjEventRegistrations::update($registration_id, array(
            'payment_status' => 'unpaid',
            'payment_method' => null,
            'payment_recorded_at' => null,
            'payment_recorded_by' => null,
        ));

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
            return;
        }

        wp_send_json_success(array(
            'message' => __('Paiement annulé.', 'mj-member'),
        ));
    }

    /**
     * Create a quick member (minimal data)
     */
    public function createQuickMember() {
        $auth = $this->verifyRequest();
        if (!$auth) return;

        $first_name = isset($_POST['firstName']) ? sanitize_text_field($_POST['firstName']) : '';
        $last_name = isset($_POST['lastName']) ? sanitize_text_field($_POST['lastName']) : '';
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $role = isset($_POST['role']) ? sanitize_key($_POST['role']) : MjRoles::JEUNE;
        $birth_date = isset($_POST['birthDate']) ? sanitize_text_field($_POST['birthDate']) : '';
        $guardian_id = isset($_POST['guardianId']) ? (int) $_POST['guardianId'] : 0;

        if ($first_name === '' || $last_name === '') {
            wp_send_json_error(array('message' => __('Prénom et nom sont requis.', 'mj-member')));
            return;
        }

        $data = array(
            'first_name' => $first_name,
            'last_name' => $last_name,
            'role' => $role,
            'status' => MjMembers::STATUS_ACTIVE,
        );

        if ($guardian_id > 0) {
            $guardian = MjMembers::getById($guardian_id);
            if (!$guardian || $guardian->role !== MjRoles::TUTEUR) {
                wp_send_json_error(array('message' => __('Le tuteur spécifié est introuvable ou invalide.', 'mj-member')));
                return;
            }

            if ($role !== MjRoles::JEUNE) {
                $role = MjRoles::JEUNE;
                $data['role'] = $role;
            }

            $data['guardian_id'] = $guardian_id;
            $data['is_autonomous'] = 0;
        }

        // Ajouter la date de naissance si fournie
        if ($birth_date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $birth_date)) {
            $data['birth_date'] = $birth_date;
        }

        if ($email !== '') {
            // Check if email already exists
            $existing = MjMembers::getByEmail($email);
            if ($existing) {
                wp_send_json_error(array('message' => __('Un membre avec cet email existe déjà.', 'mj-member')));
                return;
            }
            $data['email'] = $email;
        }

        $result = MjMembers::create($data);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
            return;
        }

        $member = MjMembers::getById($result);

        wp_send_json_success(array(
            'message' => __('Membre créé avec succès.', 'mj-member'),
            'member' => array(
                'id' => $member->id,
                'firstName' => $member->first_name,
                'lastName' => $member->last_name,
                'email' => $member->email ?? '',
                'role' => $member->role,
                'roleLabel' => MjRoles::getRoleLabel($member->role),
                'guardianId' => $guardian_id > 0 ? $guardian_id : ($member->guardian_id ?? 0),
            ),
        ));
    }

    /**
     * Get member notes
     */
    public function getMemberNotes() {
        $auth = $this->verifyRequest();
        if (!$auth) return;

        $member_id = isset($_POST['memberId']) ? (int) $_POST['memberId'] : 0;

        if ($member_id <= 0) {
            wp_send_json_error(array('message' => __('ID membre invalide.', 'mj-member')));
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'mj_member_notes';
        $members_table = $wpdb->prefix . 'mj_members';
        $events_table = $wpdb->prefix . 'mj_events';

        // Ensure table exists with event_id column
        $this->ensureNotesTable();

        // Check if table exists
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
        if (!$table_exists) {
            // Return empty if table doesn't exist yet
            wp_send_json_success(array('notes' => array()));
            return;
        }

        $notes = $wpdb->get_results($wpdb->prepare(
            "SELECT n.*, 
                    m.first_name AS author_first_name, 
                    m.last_name AS author_last_name,
                    e.title AS event_title,
                    e.emoji AS event_emoji
             FROM {$table} n
             LEFT JOIN {$members_table} m ON n.author_id = m.id
             LEFT JOIN {$events_table} e ON n.event_id = e.id
             WHERE n.member_id = %d
             ORDER BY n.created_at DESC",
            $member_id
        ));

        $data = array();
        foreach ($notes as $note) {
            $note_data = array(
                'id' => $note->id,
                'content' => wp_unslash($note->content),
                'authorId' => $note->author_id,
                'authorName' => trim(($note->author_first_name ?? '') . ' ' . ($note->author_last_name ?? '')),
                'createdAt' => $note->created_at,
                'createdAtFormatted' => $this->formatDate($note->created_at, true),
                'canEdit' => (int) $note->author_id === $auth['member_id'] || $auth['is_coordinateur'],
                'eventId' => isset($note->event_id) ? (int) $note->event_id : null,
                'eventTitle' => $note->event_title ?? null,
                'eventEmoji' => $note->event_emoji ?? null,
            );
            $data[] = $note_data;
        }

        wp_send_json_success(array('notes' => $data, 'dynFields' => $this->getNotesDynfields($member_id)));
    }

    /**
     * Build dynamic fields flagged "show in notes" for a member.
     */
    private function getNotesDynfields($member_id) {
        $fields = \Mj\Member\Classes\Crud\MjDynamicFields::getAll();
        $vals   = \Mj\Member\Classes\Crud\MjDynamicFieldValues::getByMemberKeyed($member_id);
        $out    = array();
        foreach ($fields as $df) {
            if (empty($df->show_in_notes)) continue;
            if ($df->field_type === 'title') continue;
            $out[] = array(
                'id'    => (int) $df->id,
                'title' => $df->title,
                'type'  => $df->field_type,
                'value' => isset($vals[(int) $df->id]) ? $vals[(int) $df->id] : '',
            );
        }
        return $out;
    }

    /**
     * Save member note
     */
    public function saveMemberNote() {
        $auth = $this->verifyRequest();
        if (!$auth) return;

        $member_id = isset($_POST['memberId']) ? (int) $_POST['memberId'] : 0;
        $note_id = isset($_POST['noteId']) ? (int) $_POST['noteId'] : 0;
        $event_id = isset($_POST['eventId']) ? (int) $_POST['eventId'] : 0;
        $content = isset($_POST['content']) ? sanitize_textarea_field($_POST['content']) : '';

        if ($member_id <= 0) {
            wp_send_json_error(array('message' => __('ID membre invalide.', 'mj-member')));
            return;
        }

        if ($content === '') {
            wp_send_json_error(array('message' => __('Le contenu de la note est requis.', 'mj-member')));
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'mj_member_notes';

        // Ensure table exists
        $this->ensureNotesTable();

        if ($note_id > 0) {
            // Update existing note
            $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $note_id));
            if (!$existing) {
                wp_send_json_error(array('message' => __('Note introuvable.', 'mj-member')));
                return;
            }

            // Check permission
            if ((int) $existing->author_id !== $auth['member_id'] && !$auth['is_coordinateur']) {
                wp_send_json_error(array('message' => __('Vous ne pouvez modifier que vos propres notes.', 'mj-member')));
                return;
            }

            $wpdb->update(
                $table,
                array('content' => $content, 'updated_at' => current_time('mysql')),
                array('id' => $note_id),
                array('%s', '%s'),
                array('%d')
            );

            $message = __('Note mise à jour.', 'mj-member');
        } else {
            // Create new note
            $insert_data = array(
                'member_id' => $member_id,
                'author_id' => $auth['member_id'],
                'content' => $content,
                'created_at' => current_time('mysql'),
            );
            $insert_format = array('%d', '%d', '%s', '%s');
        
            if ($event_id > 0) {
                $insert_data['event_id'] = $event_id;
                $insert_format[] = '%d';
            }
        
            $wpdb->insert($table, $insert_data, $insert_format);

            $note_id = $wpdb->insert_id;
            $message = __('Note ajoutée.', 'mj-member');
        }

        wp_send_json_success(array(
            'message' => $message,
            'noteId' => $note_id,
        ));
    }

    /**
     * Delete member testimonial
     */
    public function deleteMemberTestimonial() {
        $auth = $this->verifyRequest();
        if (!$auth) return;

        if (!$auth['is_coordinateur']) {
            wp_send_json_error(array('message' => __('Vous n\'avez pas la permission de supprimer des témoignages.', 'mj-member')));
            return;
        }

        $testimonial_id = isset($_POST['testimonialId']) ? (int) $_POST['testimonialId'] : 0;

        if ($testimonial_id <= 0) {
            wp_send_json_error(array('message' => __('ID témoignage invalide.', 'mj-member')));
            return;
        }

        $result = MjTestimonials::delete($testimonial_id);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
            return;
        }

        wp_send_json_success(array(
            'message' => __('Témoignage supprimé.', 'mj-member'),
        ));
    }

    /**
     * Update member testimonial status (approve/reject)
     */
    public function updateMemberTestimonialStatus() {
        $auth = $this->verifyRequest();
        if (!$auth) return;

        if (!$auth['is_coordinateur']) {
            wp_send_json_error(array('message' => __('Vous n\'avez pas la permission de valider des témoignages.', 'mj-member')));
            return;
        }

        $testimonial_id = isset($_POST['testimonialId']) ? (int) $_POST['testimonialId'] : 0;
        $status = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : '';
        $rejection_reason = isset($_POST['rejectionReason']) ? sanitize_textarea_field(wp_unslash($_POST['rejectionReason'])) : '';

        if ($testimonial_id <= 0) {
            wp_send_json_error(array('message' => __('ID témoignage invalide.', 'mj-member')));
            return;
        }

        $valid_statuses = array('pending', 'approved', 'rejected');
        if (!in_array($status, $valid_statuses, true)) {
            wp_send_json_error(array('message' => __('Statut invalide.', 'mj-member')));
            return;
        }

        $update_data = array(
            'status' => $status,
            'reviewed_at' => current_time('mysql'),
            'reviewed_by' => $auth['member_id'],
        );

        if ($status === 'rejected') {
            $update_data['rejection_reason'] = $rejection_reason;
        } else {
            $update_data['rejection_reason'] = null;
        }

        $result = MjTestimonials::update($testimonial_id, $update_data);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
            return;
        }

        $status_labels = array(
            'approved' => __('Témoignage approuvé.', 'mj-member'),
            'rejected' => __('Témoignage refusé.', 'mj-member'),
            'pending' => __('Témoignage remis en attente.', 'mj-member'),
        );

        wp_send_json_success(array(
            'message' => $status_labels[$status],
            'status' => $status,
        ));
    }

    /**
     * Toggle le flag "featured" d'un témoignage (affichage page d'accueil)
     */
    public function toggleTestimonialFeatured() {
        $auth = $this->verifyRequest();
        if (!$auth) return;

        if (!$auth['is_coordinateur']) {
            wp_send_json_error(array('message' => __('Vous n\'avez pas la permission de modifier ce témoignage.', 'mj-member')));
            return;
        }

        $testimonial_id = isset($_POST['testimonialId']) ? (int) $_POST['testimonialId'] : 0;

        if ($testimonial_id <= 0) {
            wp_send_json_error(array('message' => __('ID témoignage invalide.', 'mj-member')));
            return;
        }

        $result = MjTestimonials::toggle_featured($testimonial_id);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
            return;
        }

        $testimonial = MjTestimonials::get_by_id($testimonial_id);
        $is_featured = (bool) ($testimonial->featured ?? false);

        wp_send_json_success(array(
            'message' => $is_featured 
                ? __('Témoignage ajouté à la page d\'accueil.', 'mj-member') 
                : __('Témoignage retiré de la page d\'accueil.', 'mj-member'),
            'featured' => $is_featured,
        ));
    }

    /**
     * Edit testimonial content
     */
    public function editTestimonialContent() {
        $auth = $this->verifyRequest();
        if (!$auth) return;

        if (!$auth['is_coordinateur']) {
            wp_send_json_error(array('message' => __('Vous n\'avez pas la permission de modifier les témoignages.', 'mj-member')));
            return;
        }

        $testimonial_id = isset($_POST['testimonialId']) ? (int) $_POST['testimonialId'] : 0;
        $content = isset($_POST['content']) ? sanitize_textarea_field(wp_unslash($_POST['content'])) : '';

        if ($testimonial_id <= 0) {
            wp_send_json_error(array('message' => __('ID témoignage invalide.', 'mj-member')));
            return;
        }

        if (empty($content) || strlen($content) < 10) {
            wp_send_json_error(array('message' => __('Le contenu doit contenir au moins 10 caractères.', 'mj-member')));
            return;
        }

        $result = MjTestimonials::update($testimonial_id, array(
            'content' => $content,
            'updated_at' => current_time('mysql'),
        ));

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
            return;
        }

        wp_send_json_success(array(
            'message' => __('Contenu du témoignage mis à jour.', 'mj-member'),
            'content' => $content,
        ));
    }

    /**
     * Add testimonial comment
     */
    public function addTestimonialComment() {
        $auth = $this->verifyRequest();
        if (!$auth) return;

        $testimonial_id = isset($_POST['testimonialId']) ? (int) $_POST['testimonialId'] : 0;
        $content = isset($_POST['content']) ? sanitize_textarea_field(wp_unslash($_POST['content'])) : '';

        if ($testimonial_id <= 0) {
            wp_send_json_error(array('message' => __('ID témoignage invalide.', 'mj-member')));
            return;
        }

        if (empty($content) || strlen($content) < 2) {
            wp_send_json_error(array('message' => __('Le commentaire ne peut pas être vide.', 'mj-member')));
            return;
        }

        $comment_id = MjTestimonialComments::add($testimonial_id, $auth['member_id'], $content);

        if (!$comment_id) {
            wp_send_json_error(array('message' => __('Erreur lors de l\'ajout du commentaire.', 'mj-member')));
            return;
        }

        $member = MjMembers::getById($auth['member_id']);
        $member_name = $member ? ($member->first_name . ' ' . $member->last_name) : 'Anonyme';

        wp_send_json_success(array(
            'message' => __('Commentaire ajouté.', 'mj-member'),
            'comment' => array(
                'id' => (int) $comment_id,
                'memberId' => (int) $auth['member_id'],
                'memberName' => $member_name,
                'content' => $content,
                'createdAt' => current_time('mysql'),
            ),
        ));
    }

    /**
     * Edit testimonial comment
     */
    public function editTestimonialComment() {
        $auth = $this->verifyRequest();
        if (!$auth) return;

        $comment_id = isset($_POST['commentId']) ? (int) $_POST['commentId'] : 0;
        $content = isset($_POST['content']) ? sanitize_textarea_field(wp_unslash($_POST['content'])) : '';

        if ($comment_id <= 0) {
            wp_send_json_error(array('message' => __('ID commentaire invalide.', 'mj-member')));
            return;
        }

        if (empty($content) || strlen($content) < 2) {
            wp_send_json_error(array('message' => __('Le commentaire ne peut pas être vide.', 'mj-member')));
            return;
        }

        // Verify ownership or coordinator privilege
        $comment = MjTestimonialComments::get($comment_id);
        if (!$comment) {
            wp_send_json_error(array('message' => __('Commentaire non trouvé.', 'mj-member')));
            return;
        }

        if ((int) $comment->member_id !== (int) $auth['member_id'] && !$auth['is_coordinateur']) {
            wp_send_json_error(array('message' => __('Vous ne pouvez pas modifier ce commentaire.', 'mj-member')));
            return;
        }

        $result = MjTestimonialComments::update($comment_id, $content);

        if (!$result) {
            wp_send_json_error(array('message' => __('Erreur lors de la mise à jour du commentaire.', 'mj-member')));
            return;
        }

        wp_send_json_success(array(
            'message' => __('Commentaire mis à jour.', 'mj-member'),
            'content' => $content,
        ));
    }

    /**
     * Delete testimonial comment
     */
    public function deleteTestimonialComment() {
        $auth = $this->verifyRequest();
        if (!$auth) return;

        $comment_id = isset($_POST['commentId']) ? (int) $_POST['commentId'] : 0;

        if ($comment_id <= 0) {
            wp_send_json_error(array('message' => __('ID commentaire invalide.', 'mj-member')));
            return;
        }

        // Verify ownership or coordinator privilege
        $comment = MjTestimonialComments::get($comment_id);
        if (!$comment) {
            wp_send_json_error(array('message' => __('Commentaire non trouvé.', 'mj-member')));
            return;
        }

        if ((int) $comment->member_id !== (int) $auth['member_id'] && !$auth['is_coordinateur']) {
            wp_send_json_error(array('message' => __('Vous ne pouvez pas supprimer ce commentaire.', 'mj-member')));
            return;
        }

        $result = MjTestimonialComments::delete($comment_id);

        if (!$result) {
            wp_send_json_error(array('message' => __('Erreur lors de la suppression du commentaire.', 'mj-member')));
            return;
        }

        wp_send_json_success(array(
            'message' => __('Commentaire supprimé.', 'mj-member'),
        ));
    }

    /**
     * Add testimonial reaction (emoji)
     */
    public function addTestimonialReaction() {
        $auth = $this->verifyRequest();
        if (!$auth) return;

        $testimonial_id = isset($_POST['testimonialId']) ? (int) $_POST['testimonialId'] : 0;
        $reaction_type = isset($_POST['reactionType']) ? sanitize_text_field(wp_unslash($_POST['reactionType'])) : '';

        if ($testimonial_id <= 0) {
            wp_send_json_error(array('message' => __('ID témoignage invalide.', 'mj-member')));
            return;
        }

        if (!MjTestimonialReactions::is_valid_type($reaction_type)) {
            wp_send_json_error(array('message' => __('Type de réaction invalide.', 'mj-member')));
            return;
        }

        $result = MjTestimonialReactions::react($testimonial_id, $auth['member_id'], $reaction_type);

        if (!$result) {
            wp_send_json_error(array('message' => __('Erreur lors de l\'ajout de la réaction.', 'mj-member')));
            return;
        }

        // Get updated counts
        $reaction_counts = MjTestimonialReactions::get_counts($testimonial_id);
        $count = isset($reaction_counts[$reaction_type]) ? (int) $reaction_counts[$reaction_type] : 0;

        wp_send_json_success(array(
            'message' => __('Réaction ajoutée.', 'mj-member'),
            'reactionType' => $reaction_type,
            'count' => $count,
        ));
    }

    /**
     * Remove testimonial reaction
     */
    public function removeTestimonialReaction() {
        $auth = $this->verifyRequest();
        if (!$auth) return;

        $testimonial_id = isset($_POST['testimonialId']) ? (int) $_POST['testimonialId'] : 0;
        $reaction_type = isset($_POST['reactionType']) ? sanitize_text_field(wp_unslash($_POST['reactionType'])) : '';

        if ($testimonial_id <= 0) {
            wp_send_json_error(array('message' => __('ID témoignage invalide.', 'mj-member')));
            return;
        }

        if (!MjTestimonialReactions::is_valid_type($reaction_type)) {
            wp_send_json_error(array('message' => __('Type de réaction invalide.', 'mj-member')));
            return;
        }

        $result = MjTestimonialReactions::remove_reaction($testimonial_id, $auth['member_id']);

        if (!$result) {
            wp_send_json_error(array('message' => __('Erreur lors de la suppression de la réaction.', 'mj-member')));
            return;
        }

        // Get updated counts
        $reaction_counts = MjTestimonialReactions::get_counts($testimonial_id);
        $count = isset($reaction_counts[$reaction_type]) ? (int) $reaction_counts[$reaction_type] : 0;

        wp_send_json_success(array(
            'message' => __('Réaction supprimée.', 'mj-member'),
            'reactionType' => $reaction_type,
            'count' => $count,
        ));
    }

    /**
     * Update social media link for testimony
     */
    public function updateSocialLink() {
        $auth = $this->verifyRequest();
        if (!$auth) return;

        if (!$auth['is_coordinateur']) {
            wp_send_json_error(array('message' => __('Vous n\'avez pas la permission de modifier les liens.', 'mj-member')));
            return;
        }

        $testimonial_id = isset($_POST['testimonialId']) ? (int) $_POST['testimonialId'] : 0;
        $action = isset($_POST['action']) ? sanitize_text_field(wp_unslash($_POST['action'])) : '';
        $url = isset($_POST['url']) ? esc_url_raw(wp_unslash($_POST['url'])) : '';

        if ($testimonial_id <= 0) {
            wp_send_json_error(array('message' => __('ID témoignage invalide.', 'mj-member')));
            return;
        }

        if ($action === 'add' && empty($url)) {
            wp_send_json_error(array('message' => __('URL invalide.', 'mj-member')));
            return;
        }

        $update_data = array('social_link' => null, 'social_link_title' => null, 'social_link_preview' => null);
    
        if ($action === 'add') {
            // Try to get metadata from URL using Open Graph or basic parsing
            $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
            $preview = isset($_POST['preview']) ? sanitize_textarea_field(wp_unslash($_POST['preview'])) : '';
        
            $update_data['social_link'] = $url;
            $update_data['social_link_title'] = $title;
            $update_data['social_link_preview'] = $preview;
        }

        $result = MjTestimonials::update($testimonial_id, $update_data);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
            return;
        }

        wp_send_json_success(array(
            'message' => $action === 'add' ? __('Lien ajouté.', 'mj-member') : __('Lien supprimé.', 'mj-member'),
            'link' => $action === 'add' ? array(
                'url' => $url,
                'title' => $update_data['social_link_title'],
                'preview' => $update_data['social_link_preview'],
            ) : null,
        ));
    }

    /**
     * Delete member note
     */
    public function deleteMemberNote() {
        $auth = $this->verifyRequest();
        if (!$auth) return;

        $note_id = isset($_POST['noteId']) ? (int) $_POST['noteId'] : 0;

        if ($note_id <= 0) {
            wp_send_json_error(array('message' => __('ID note invalide.', 'mj-member')));
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'mj_member_notes';

        $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $note_id));
        if (!$existing) {
            wp_send_json_error(array('message' => __('Note introuvable.', 'mj-member')));
            return;
        }

        // Check permission
        if ((int) $existing->author_id !== $auth['member_id'] && !$auth['is_coordinateur']) {
            wp_send_json_error(array('message' => __('Vous ne pouvez supprimer que vos propres notes.', 'mj-member')));
            return;
        }

        $wpdb->delete($table, array('id' => $note_id), array('%d'));

        wp_send_json_success(array(
            'message' => __('Note supprimée.', 'mj-member'),
        ));
    }

    /**
     * Get payment QR code URL
     */
    public function getPaymentQr() {
        $auth = $this->verifyRequest();
        if (!$auth) return;

        $registration_id = isset($_POST['registrationId']) ? (int) $_POST['registrationId'] : 0;

        if ($registration_id <= 0) {
            wp_send_json_error(array('message' => __('ID inscription invalide.', 'mj-member')));
            return;
        }

        $registration = MjEventRegistrations::get($registration_id);
        if (!$registration) {
            wp_send_json_error(array('message' => __('Inscription introuvable.', 'mj-member')));
            return;
        }

        $event = MjEvents::find($registration->event_id);
        if (!$event) {
            wp_send_json_error(array('message' => __('Événement introuvable.', 'mj-member')));
            return;
        }

        $amount = (float) $event->prix;
    
        // Check if there's already a pending Stripe payment for this registration
        $existing_payment = MjPayments::get_pending_payment_for_registration($registration_id);
    
        if ($existing_payment && !empty($existing_payment->checkout_url)) {
            // Use existing Stripe checkout URL
            $checkout_url = $existing_payment->checkout_url;
            $qr_url = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . rawurlencode($checkout_url);
        
            wp_send_json_success(array(
                'qrUrl' => $qr_url,
                'paymentUrl' => $checkout_url,
                'amount' => $amount,
                'eventTitle' => $event->title,
            ));
            return;
        }
    
        // Create a new Stripe payment session
        if (class_exists(MjPayments::class) && class_exists(MjStripeConfig::class) && MjStripeConfig::is_configured()) {
            $payment_result = MjPayments::create_stripe_payment(
                $registration->member_id,
                $amount,
                array(
                    'context' => 'event',
                    'event_id' => $event->id,
                    'registration_id' => $registration_id,
                    'event' => $event,
                )
            );
        
            if ($payment_result && !empty($payment_result['checkout_url'])) {
                wp_send_json_success(array(
                    'qrUrl' => $payment_result['qr_url'] ?? ('https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . rawurlencode($payment_result['checkout_url'])),
                    'paymentUrl' => $payment_result['checkout_url'],
                    'amount' => $amount,
                    'eventTitle' => $event->title,
                ));
                return;
            }
        }
    
        // Fallback: return error if Stripe is not configured
        wp_send_json_error(array(
            'message' => __('Le paiement Stripe n\'est pas configuré. Veuillez contacter l\'administrateur.', 'mj-member'),
        ));
    }

    private function getScheduleWeekdays() {
        return array(
            'monday'    => __('Lundi', 'mj-member'),
            'tuesday'   => __('Mardi', 'mj-member'),
            'wednesday' => __('Mercredi', 'mj-member'),
            'thursday'  => __('Jeudi', 'mj-member'),
            'friday'    => __('Vendredi', 'mj-member'),
            'saturday'  => __('Samedi', 'mj-member'),
            'sunday'    => __('Dimanche', 'mj-member'),
        );
    }

    private function getScheduleMonthOrdinals() {
        return array(
            'first'  => __('1er', 'mj-member'),
            'second' => __('2e', 'mj-member'),
            'third'  => __('3e', 'mj-member'),
            'fourth' => __('4e', 'mj-member'),
            'last'   => __('Dernier', 'mj-member'),
        );
    }

    private function normalizeHexColor($value) {
        if (is_string($value)) {
            $candidate = trim($value);
        } elseif (is_object($value) && method_exists($value, '__toString')) {
            $candidate = trim((string) $value);
        } else {
            $candidate = trim((string) $value);
        }

        if ($candidate === '') {
            return '';
        }

        if ($candidate[0] !== '#') {
            $candidate = '#' . $candidate;
        }

        $sanitized = sanitize_hex_color($candidate);
        if (!is_string($sanitized) || $sanitized === '') {
            return '';
        }

        $sanitized = strtoupper($sanitized);
        if (strlen($sanitized) === 4) {
            return '#' . $sanitized[1] . $sanitized[1] . $sanitized[2] . $sanitized[2] . $sanitized[3] . $sanitized[3];
        }

        return $sanitized;
    }

    private function decodeJsonField($value) {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return array();
    }

    private function formatEventDatetime($value) {
        if (empty($value) || $value === '0000-00-00 00:00:00') {
            return '';
        }

        $timezone = wp_timezone();

        if ($value instanceof \DateTimeInterface) {
            $datetime = new DateTime($value->format('Y-m-d H:i:s'), $timezone);
        } else {
            $datetime = date_create((string) $value, $timezone);
        }

        if (!$datetime) {
            return '';
        }

        $datetime->setTimezone($timezone);

        return $datetime->format('Y-m-d\TH:i');
    }

    private function parseEventDatetime($value) {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $normalized = str_replace('T', ' ', $value);
        $timezone = wp_timezone();

        $formats = array('Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d');
        foreach ($formats as $format) {
            $datetime = DateTime::createFromFormat($format, $normalized, $timezone);
            if ($datetime instanceof DateTime) {
                if ($format === 'Y-m-d') {
                    $datetime->setTime(0, 0, 0);
                }
                return $datetime->format('Y-m-d H:i:s');
            }
        }

        $timestamp = strtotime($normalized);
        if ($timestamp === false) {
            return '';
        }

        return wp_date('Y-m-d H:i:s', $timestamp, $timezone);
    }

    private function parseRecurrenceUntil($value, $end_time, \DateTimeZone $timezone) {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $time_part = trim((string) $end_time);
        if ($time_part === '') {
            $time_part = '23:59';
        }

        $datetime = DateTime::createFromFormat('Y-m-d H:i', $value . ' ' . $time_part, $timezone);
        if ($datetime instanceof DateTime) {
            return $datetime->format('Y-m-d H:i:s');
        }

        $datetime = DateTime::createFromFormat('Y-m-d', $value, $timezone);
        if ($datetime instanceof DateTime) {
            $datetime->setTime(23, 59, 0);
            return $datetime->format('Y-m-d H:i:s');
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return '';
        }

        return wp_date('Y-m-d H:i:s', $timestamp, $timezone);
    }

    private function eventsSupportsPrimaryAnimateur() {
        static $supported = null;

        if ($supported !== null) {
            return $supported;
        }

        if (!function_exists('mj_member_column_exists')) {
            $supported = false;
            return $supported;
        }

        $table = mj_member_get_events_table_name();
        $supported = mj_member_column_exists($table, 'animateur_id');

        return $supported;
    }

    private function prepareEventFormValues($event, array $schedule_weekdays, array $schedule_month_ordinals) {
        $defaults = MjEvents::get_default_values();

        $form_values = $defaults;
        $form_values['accent_color'] = isset($defaults['accent_color']) ? $defaults['accent_color'] : '';
        $form_values['emoji'] = isset($defaults['emoji']) ? $defaults['emoji'] : '';
        $form_values['animateur_ids'] = array();
        $form_values['volunteer_ids'] = array();
        $form_values['schedule_mode'] = isset($defaults['schedule_mode']) ? $defaults['schedule_mode'] : 'fixed';
        $form_values['schedule_payload'] = array();
        $form_values['schedule_series_items'] = array();
        $form_values['schedule_show_date_range'] = false;
        $form_values['schedule_weekday_times'] = array();
        $form_values['schedule_exceptions'] = array();
        $form_values['occurrence_selection_mode'] = isset($defaults['occurrence_selection_mode']) ? $defaults['occurrence_selection_mode'] : 'member_choice';
        $form_values['recurrence_until'] = '';
        $form_values['schedule_recurring_start_date'] = '';
        $form_values['schedule_recurring_start_time'] = '';
        $form_values['schedule_recurring_end_time'] = '';
        $form_values['schedule_recurring_frequency'] = 'weekly';
        $form_values['schedule_recurring_interval'] = 1;
        $form_values['schedule_recurring_weekdays'] = array();
        $form_values['schedule_recurring_month_ordinal'] = 'first';
        $form_values['schedule_recurring_month_weekday'] = 'saturday';
        $form_values['schedule_fixed_date'] = '';
        $form_values['schedule_fixed_start_time'] = '';
        $form_values['schedule_fixed_end_time'] = '';
        $form_values['schedule_range_start'] = '';
        $form_values['schedule_range_end'] = '';
        $form_values['article_cat'] = 0;
        $form_values['registration_payload'] = array();
        $form_values['registration_is_free_participation'] = !empty($defaults['free_participation']);
        $form_values['free_participation'] = !empty($defaults['free_participation']);
        $form_values['attendance_show_all_members'] = false;

        if ($event) {
            $accent_color = $this->normalizeHexColor(isset($event->accent_color) ? $event->accent_color : '');
            $occurrence_mode = isset($event->occurrence_selection_mode) ? sanitize_key((string) $event->occurrence_selection_mode) : 'member_choice';
            if (!in_array($occurrence_mode, array('member_choice', 'all_occurrences'), true)) {
                $occurrence_mode = 'member_choice';
            }

            $raw_emoji = isset($event->emoji) ? (string) $event->emoji : '';
            $sanitized_emoji = $raw_emoji;
            if ($sanitized_emoji === '' && $raw_emoji !== '') {
                $sanitized_emoji = $raw_emoji;
            }

            $form_values = array_merge($form_values, array(
                'title' => isset($event->title) ? (string) $event->title : '',
                'slug' => isset($event->slug) ? (string) $event->slug : '',
                'status' => isset($event->status) ? (string) $event->status : $form_values['status'],
                'type' => isset($event->type) ? (string) $event->type : $form_values['type'],
                'accent_color' => $accent_color,
                'emoji' => $sanitized_emoji,
                'cover_id' => isset($event->cover_id) ? (int) $event->cover_id : 0,
                'article_id' => isset($event->article_id) ? (int) $event->article_id : 0,
                'location_id' => isset($event->location_id) ? (int) $event->location_id : 0,
                'allow_guardian_registration' => !empty($event->allow_guardian_registration),
                'requires_validation' => isset($event->requires_validation) ? !empty($event->requires_validation) : true,
                'description' => isset($event->description) ? (string) $event->description : '',
                'registration_document' => isset($event->registration_document) ? (string) $event->registration_document : '',
                'age_min' => isset($event->age_min) ? (int) $event->age_min : (int) $form_values['age_min'],
                'age_max' => isset($event->age_max) ? (int) $event->age_max : (int) $form_values['age_max'],
                'date_debut' => $this->formatEventDatetime(isset($event->date_debut) ? $event->date_debut : ''),
                'date_fin' => $this->formatEventDatetime(isset($event->date_fin) ? $event->date_fin : ''),
                'date_fin_inscription' => $this->formatEventDatetime(isset($event->date_fin_inscription) ? $event->date_fin_inscription : ''),
                'prix' => number_format(isset($event->prix) ? (float) $event->prix : 0.0, 2, '.', ''),
                'schedule_mode' => isset($event->schedule_mode) ? sanitize_key((string) $event->schedule_mode) : 'fixed',
                'occurrence_selection_mode' => $occurrence_mode,
                'recurrence_until' => (!empty($event->recurrence_until) && strtotime($event->recurrence_until)) ? date_i18n('Y-m-d', strtotime($event->recurrence_until)) : '',
                'capacity_total' => isset($event->capacity_total) ? (int) $event->capacity_total : 0,
                'capacity_waitlist' => isset($event->capacity_waitlist) ? (int) $event->capacity_waitlist : 0,
                'capacity_notify_threshold' => isset($event->capacity_notify_threshold) ? (int) $event->capacity_notify_threshold : 0,
                'occurrenceGenerator' => $this->extractOccurrenceGeneratorFromEvent($event),
                'free_participation' => !empty($event->free_participation),
                'registration_is_free_participation' => !empty($event->free_participation),
            ));

            $form_values['registration_payload'] = $this->decodeJsonField(isset($event->registration_payload) ? $event->registration_payload : array());
            $form_values['attendance_show_all_members'] = !empty($form_values['registration_payload']['attendance_show_all_members']);
            if (!$form_values['attendance_show_all_members'] && isset($event->attendance_show_all_members)) {
                $form_values['attendance_show_all_members'] = !empty($event->attendance_show_all_members);
            }

            $animateur_ids = class_exists(MjEventAnimateurs::class) ? MjEventAnimateurs::get_ids_by_event((int) $event->id) : array();
            if (empty($animateur_ids) && isset($event->animateur_id) && (int) $event->animateur_id > 0) {
                $animateur_ids = array((int) $event->animateur_id);
            }
            $form_values['animateur_ids'] = array_values(array_unique(array_map('intval', $animateur_ids)));
            $form_values['animateur_id'] = !empty($form_values['animateur_ids']) ? (int) $form_values['animateur_ids'][0] : 0;

            $volunteer_ids = class_exists(MjEventVolunteers::class) ? MjEventVolunteers::get_ids_by_event((int) $event->id) : array();
            $form_values['volunteer_ids'] = array_values(array_unique(array_map('intval', $volunteer_ids)));

            // Location links (multiple locations with types)
            $location_links = class_exists(MjEventLocationLinks::class) ? MjEventLocationLinks::get_by_event((int) $event->id) : array();
            $form_values['location_links'] = array_map(function($link) {
                return array(
                    'locationId' => $link['location_id'],
                    'locationType' => $link['location_type'],
                    'customLabel' => isset($link['custom_label']) ? $link['custom_label'] : '',
                    'meetingTime' => isset($link['meeting_time']) ? $link['meeting_time'] : '',
                    'meetingTimeEnd' => isset($link['meeting_time_end']) ? $link['meeting_time_end'] : '',
                    'sortOrder' => $link['sort_order'],
                );
            }, $location_links);

            $form_values = $this->fillScheduleValues($event, $form_values, $schedule_weekdays, $schedule_month_ordinals);

        } else {
            $timezone = wp_timezone();
            $now = current_time('timestamp');
            $default_start = $now + 21 * DAY_IN_SECONDS;
            $default_end = $default_start + 2 * HOUR_IN_SECONDS;

            $form_values['date_debut'] = wp_date('Y-m-d\TH:i', $default_start, $timezone);
            $form_values['date_fin'] = wp_date('Y-m-d\TH:i', $default_end, $timezone);
            $form_values['schedule_fixed_date'] = substr($form_values['date_debut'], 0, 10);
            $form_values['schedule_fixed_start_time'] = substr($form_values['date_debut'], 11, 5);
            $form_values['schedule_fixed_end_time'] = substr($form_values['date_fin'], 11, 5);
            $form_values['schedule_range_start'] = $form_values['date_debut'];
            $form_values['schedule_range_end'] = $form_values['date_fin'];
        }

        return $form_values;
    }

    private function fillScheduleValues($event, array $form_values, array $schedule_weekdays, array $schedule_month_ordinals) {
        $payload = array();
        if (isset($event->schedule_payload)) {
            $payload = $this->decodeJsonField($event->schedule_payload);
        }

        $schedule_mode = isset($form_values['schedule_mode']) ? sanitize_key((string) $form_values['schedule_mode']) : 'fixed';
        if (!in_array($schedule_mode, array('fixed', 'range', 'recurring', 'series'), true)) {
            $schedule_mode = 'fixed';
        }
        $form_values['schedule_mode'] = $schedule_mode;
        $form_values['schedule_payload'] = $payload;
        $form_values['schedule_series_items'] = array();
        $form_values['schedule_weekday_times'] = array();
        $form_values['schedule_show_date_range'] = false;

        $default_start_time = $form_values['date_debut'] !== '' ? substr($form_values['date_debut'], 11, 5) : '';
        $default_end_time = $form_values['date_fin'] !== '' ? substr($form_values['date_fin'], 11, 5) : '';
        $default_date = $form_values['date_debut'] !== '' ? substr($form_values['date_debut'], 0, 10) : '';

        $form_values['schedule_fixed_date'] = $default_date;
        $form_values['schedule_fixed_start_time'] = $default_start_time;
        $form_values['schedule_fixed_end_time'] = $default_end_time;
        $form_values['schedule_range_start'] = $form_values['date_debut'];
        $form_values['schedule_range_end'] = $form_values['date_fin'];

        if ($schedule_mode === 'recurring') {
            $frequency = isset($payload['frequency']) ? sanitize_key($payload['frequency']) : 'weekly';
            if (!in_array($frequency, array('weekly', 'monthly'), true)) {
                $frequency = 'weekly';
            }

            $form_values['schedule_recurring_frequency'] = $frequency;
            $form_values['schedule_recurring_interval'] = isset($payload['interval']) ? max(1, (int) $payload['interval']) : 1;
            $form_values['schedule_recurring_start_date'] = isset($payload['start_date']) ? (string) $payload['start_date'] : $default_date;
            $form_values['schedule_recurring_start_time'] = isset($payload['start_time']) ? (string) $payload['start_time'] : $default_start_time;
            $form_values['schedule_recurring_end_time'] = isset($payload['end_time']) ? (string) $payload['end_time'] : $default_end_time;
            $form_values['schedule_show_date_range'] = !empty($payload['show_date_range']);
            $form_values['schedule_exceptions'] = $this->sanitizeRecurrenceExceptions(isset($payload['exceptions']) ? $payload['exceptions'] : array());

            if (!isset($payload['until']) || !is_string($payload['until']) || $payload['until'] === '') {
                $until_source = isset($form_values['recurrence_until']) ? (string) $form_values['recurrence_until'] : '';
                if ($until_source !== '') {
                    $timezone = wp_timezone();
                    $until_value = $this->parseRecurrenceUntil($until_source, $form_values['schedule_recurring_end_time'], $timezone);
                    if ($until_value !== '') {
                        $payload['until'] = $until_value;
                        $form_values['schedule_payload'] = $payload;
                    }
                }
            } else {
                $form_values['schedule_payload'] = $payload;
            }

            if ($frequency === 'weekly') {
                $weekdays = array();
                if (isset($payload['weekdays']) && is_array($payload['weekdays'])) {
                    foreach ($payload['weekdays'] as $weekday) {
                        $weekday = sanitize_key($weekday);
                        if (isset($schedule_weekdays[$weekday])) {
                            $weekdays[$weekday] = $weekday;
                        }
                    }
                }
                $form_values['schedule_recurring_weekdays'] = array_values($weekdays);

                $weekday_times = array();
                if (isset($payload['weekday_times']) && is_array($payload['weekday_times'])) {
                    foreach ($payload['weekday_times'] as $weekday_key => $time_info) {
                        $weekday_key = sanitize_key($weekday_key);
                        if (!isset($schedule_weekdays[$weekday_key]) || !is_array($time_info)) {
                            continue;
                        }
                        $weekday_times[$weekday_key] = array(
                            'start' => isset($time_info['start']) ? (string) $time_info['start'] : '',
                            'end' => isset($time_info['end']) ? (string) $time_info['end'] : '',
                        );
                    }
                }
                $form_values['schedule_weekday_times'] = $weekday_times;
                $form_values['schedule_recurring_month_ordinal'] = 'first';
                $form_values['schedule_recurring_month_weekday'] = 'saturday';
            } else {
                $ordinal = isset($payload['ordinal']) ? sanitize_key($payload['ordinal']) : 'first';
                if (!isset($schedule_month_ordinals[$ordinal])) {
                    $ordinal = 'first';
                }
                $weekday = isset($payload['weekday']) ? sanitize_key($payload['weekday']) : 'saturday';
                if (!isset($schedule_weekdays[$weekday])) {
                    $weekday = 'saturday';
                }

                $form_values['schedule_recurring_weekdays'] = array();
                $form_values['schedule_recurring_month_ordinal'] = $ordinal;
                $form_values['schedule_recurring_month_weekday'] = $weekday;
                $form_values['schedule_weekday_times'] = array();
            }

            $form_values['schedule_range_start'] = '';
            $form_values['schedule_range_end'] = '';
        } elseif ($schedule_mode === 'range') {
            $form_values['schedule_range_start'] = isset($payload['start']) ? (string) $payload['start'] : $form_values['date_debut'];
            $form_values['schedule_range_end'] = isset($payload['end']) ? (string) $payload['end'] : $form_values['date_fin'];
            $form_values['schedule_exceptions'] = array();
        } elseif ($schedule_mode === 'series') {
            $series_items = array();
            if (isset($payload['items']) && is_array($payload['items'])) {
                foreach ($payload['items'] as $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    $date = isset($item['date']) ? (string) $item['date'] : '';
                    $start_time = isset($item['start_time']) ? (string) $item['start_time'] : '';
                    $end_time = isset($item['end_time']) ? (string) $item['end_time'] : '';
                    if ($date === '' || $start_time === '') {
                        continue;
                    }
                    $series_items[] = array(
                        'date' => $date,
                        'start_time' => $start_time,
                        'end_time' => $end_time,
                    );
                }
            }
            $form_values['schedule_series_items'] = $series_items;
            $form_values['schedule_range_start'] = '';
            $form_values['schedule_range_end'] = '';
            $form_values['schedule_exceptions'] = array();
        }

        if (!empty($form_values['date_debut'])) {
            $form_values['schedule_fixed_date'] = substr($form_values['date_debut'], 0, 10);
            $form_values['schedule_fixed_start_time'] = substr($form_values['date_debut'], 11, 5);
        }
        if (!empty($form_values['date_fin'])) {
            $form_values['schedule_fixed_end_time'] = substr($form_values['date_fin'], 11, 5);
        }

        return $form_values;
    }

    private function collectEventEditorAssets($event, array &$form_values) {
        $article_categories = get_categories(array('hide_empty' => false));
        if (!is_array($article_categories)) {
            $article_categories = array();
        }

        $selected_cat = isset($form_values['article_cat']) ? (int) $form_values['article_cat'] : 0;
        if ($selected_cat <= 0 && !empty($form_values['article_id'])) {
            $article_terms = get_the_category((int) $form_values['article_id']);
            if (!empty($article_terms)) {
                $selected_cat = (int) $article_terms[0]->term_id;
            }
        }
        if ($selected_cat <= 0 && !empty($article_categories)) {
            $selected_cat = (int) $article_categories[0]->term_id;
        }
        $form_values['article_cat'] = $selected_cat;

        $article_args = array(
            'numberposts' => 50,
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC',
        );
        if ($selected_cat > 0) {
            $article_args['cat'] = $selected_cat;
        }
        $articles = get_posts($article_args);
        if (!is_array($articles)) {
            $articles = array();
        }
        if (!empty($form_values['article_id'])) {
            $article_id = (int) $form_values['article_id'];
            $found = false;
            foreach ($articles as $article) {
                if (!is_object($article) || !isset($article->ID)) {
                    continue;
                }
                if ((int) $article->ID === $article_id) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $current_article = get_post($article_id);
                if ($current_article && $current_article->post_status === 'publish') {
                    array_unshift($articles, $current_article);
                }
            }
        }

        $locations = class_exists(MjEventLocations::class) ? MjEventLocations::get_all(array('orderby' => 'name', 'order' => 'ASC')) : array();
        if (!is_array($locations)) {
            $locations = array();
        }
        $location_ids = array();
        foreach ($locations as $location) {
            if (is_object($location) && isset($location->id)) {
                $location_ids[(int) $location->id] = true;
            }
        }

        $animateur_filters = array('roles' => array(MjRoles::ANIMATEUR, MjRoles::COORDINATEUR));
        $animateurs = MjMembers::getAll(0, 0, 'last_name', 'ASC', '', $animateur_filters);
        if (!is_array($animateurs)) {
            $animateurs = array();
        }
        $available_animateur_ids = array();
        foreach ($animateurs as $animateur) {
            if (is_object($animateur) && isset($animateur->id)) {
                $available_animateur_ids[(int) $animateur->id] = true;
            }
        }

        $volunteer_filters = array('is_volunteer' => 1);
        $volunteers = MjMembers::getAll(0, 0, 'last_name', 'ASC', '', $volunteer_filters);
        if (!is_array($volunteers)) {
            $volunteers = array();
        }
        $available_volunteer_ids = array();
        foreach ($volunteers as $volunteer) {
            if (is_object($volunteer) && isset($volunteer->id)) {
                $available_volunteer_ids[(int) $volunteer->id] = true;
            }
        }

        return array(
            'article_categories' => $article_categories,
            'articles' => $articles,
            'locations' => $locations,
            'location_type_labels' => class_exists(MjEventLocationLinks::class) ? MjEventLocationLinks::get_type_labels() : array(),
            'animateurs' => $animateurs,
            'volunteers' => $volunteers,
            'available_animateur_ids' => $available_animateur_ids,
            'available_volunteer_ids' => $available_volunteer_ids,
            'location_ids' => $location_ids,
            'animateur_assignments_ready' => class_exists(MjEventAnimateurs::class) ? MjEventAnimateurs::is_ready() : false,
            'volunteer_assignments_ready' => class_exists(MjEventVolunteers::class) ? MjEventVolunteers::is_ready() : false,
            'animateur_column_supported' => $this->eventsSupportsPrimaryAnimateur(),
        );
    }

    private function userCanManageLocations($auth) {
        if (current_user_can(Config::capability())) {
            return true;
        }
        if (is_array($auth) && !empty($auth['is_coordinateur'])) {
            return true;
        }
        return false;
    }

    private function buildLocationLookupQuery(array $location) {
        if (!empty($location['map_query'])) {
            return (string) $location['map_query'];
        }

        $latitude = isset($location['latitude']) && $location['latitude'] !== null
            ? trim((string) $location['latitude'])
            : '';
        $longitude = isset($location['longitude']) && $location['longitude'] !== null
            ? trim((string) $location['longitude'])
            : '';

        if ($latitude !== '' && $longitude !== '') {
            return $latitude . ',' . $longitude;
        }

        $parts = array();
        if (!empty($location['address_line'])) {
            $parts[] = (string) $location['address_line'];
        }
        if (!empty($location['postal_code'])) {
            $parts[] = (string) $location['postal_code'];
        }
        if (!empty($location['city'])) {
            $parts[] = (string) $location['city'];
        }
        if (!empty($location['country'])) {
            $parts[] = (string) $location['country'];
        }

        if (empty($parts)) {
            return '';
        }

        return implode(', ', $parts);
    }

    private function buildLocationMapPreviewUrl(array $location) {
        $query = $this->buildLocationLookupQuery($location);
        if ($query === '') {
            return '';
        }

        return 'https://maps.google.com/maps?q=' . rawurlencode($query) . '&output=embed';
    }

    private function buildLocationMapLink(array $location) {
        $query = $this->buildLocationLookupQuery($location);
        if ($query === '') {
            return '';
        }

        return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($query);
    }

    private function formatLocationPayload($location) {
        if ($location instanceof EventLocationData) {
            $location_data = $location->toArray();
        } elseif (is_object($location)) {
            $location_data = get_object_vars($location);
        } else {
            $location_data = (array) $location;
        }

        $id = isset($location_data['id']) ? (int) $location_data['id'] : 0;
        $name = isset($location_data['name']) ? sanitize_text_field((string) $location_data['name']) : '';
        $address = isset($location_data['address_line']) ? sanitize_text_field((string) $location_data['address_line']) : '';
        $postal_code = isset($location_data['postal_code']) ? sanitize_text_field((string) $location_data['postal_code']) : '';
        $city = isset($location_data['city']) ? sanitize_text_field((string) $location_data['city']) : '';
        $country = isset($location_data['country']) ? sanitize_text_field((string) $location_data['country']) : '';
        $icon = isset($location_data['icon']) ? sanitize_text_field((string) $location_data['icon']) : '';
        $cover_id = isset($location_data['cover_id']) ? (int) $location_data['cover_id'] : 0;
        $cover_url = '';
        if ($cover_id > 0 && function_exists('wp_get_attachment_image_url')) {
            $cover_candidate = wp_get_attachment_image_url($cover_id, 'medium');
            if (is_string($cover_candidate)) {
                $cover_url = $cover_candidate;
            }
        }
        $cover_admin_url = '';
        if ($id > 0) {
            $cover_admin_url = add_query_arg(
                array(
                    'page' => 'mj_locations',
                    'action' => 'edit',
                    'location' => $id,
                ),
                admin_url('admin.php')
            );
        }
        $map_query = isset($location_data['map_query']) ? sanitize_text_field((string) $location_data['map_query']) : '';
        $latitude = isset($location_data['latitude']) && $location_data['latitude'] !== null ? trim((string) $location_data['latitude']) : '';
        $longitude = isset($location_data['longitude']) && $location_data['longitude'] !== null ? trim((string) $location_data['longitude']) : '';
        $notes = isset($location_data['notes']) ? sanitize_textarea_field((string) $location_data['notes']) : '';

        $normalized = array(
            'id' => $id,
            'name' => $name,
            'address_line' => $address,
            'postal_code' => $postal_code,
            'city' => $city,
            'country' => $country,
            'icon' => $icon,
            'cover_id' => $cover_id,
            'map_query' => $map_query,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'notes' => $notes,
        );

        $map_embed = class_exists(MjEventLocations::class) ? MjEventLocations::build_map_embed_src($location_data) : '';
        $formatted_address = class_exists(MjEventLocations::class) ? MjEventLocations::format_address($location_data) : '';
        $map_preview = $map_embed !== '' ? $map_embed : $this->buildLocationMapPreviewUrl($normalized);
        $map_link = $this->buildLocationMapLink($normalized);

        $label = $name;
        if ($label === '' && $id > 0) {
            /* translators: %d: location identifier */
            $label = sprintf(__('Lieu #%d', 'mj-member'), $id);
        }
        if ($city !== '') {
            $label .= $label !== '' ? ' (' . $city . ')' : $city;
        }

        $option = null;
        if ($id > 0) {
            $option = array(
                'id' => $id,
                'label' => $label,
                'attributes' => array(
                    'data-address' => $formatted_address ? $formatted_address : '',
                    'data-map' => $map_preview,
                    'data-notes' => $notes,
                    'data-city' => $city,
                    'data-country' => $country,
                    'data-icon' => $icon,
                    'data-cover-id' => $cover_id > 0 ? (string) $cover_id : '',
                    'data-cover-src' => $cover_url !== '' ? esc_url_raw($cover_url) : '',
                    'data-cover-admin' => $cover_admin_url !== '' ? esc_url_raw($cover_admin_url) : '',
                ),
            );
        }

        $normalized['formattedAddress'] = $formatted_address ? $formatted_address : '';
        $normalized['mapEmbed'] = $map_preview;
        $normalized['mapLink'] = $map_link;
        $normalized['coverId'] = $cover_id;
        $normalized['coverUrl'] = $cover_url !== '' ? esc_url_raw($cover_url) : '';
        $normalized['coverAdminUrl'] = $cover_admin_url !== '' ? esc_url_raw($cover_admin_url) : '';
        $normalized['cover_url'] = $cover_url !== '' ? esc_url_raw($cover_url) : '';
        $normalized['cover_admin_url'] = $cover_admin_url !== '' ? esc_url_raw($cover_admin_url) : '';

        return array(
            'location' => $normalized,
            'option' => $option,
        );
    }

    public function getLocation() {
        $auth = $this->verifyRequest();
        if (!$auth) {
            return;
        }

        if (!$this->userCanManageLocations($auth)) {
            wp_send_json_error(array('message' => __('Permissions insuffisantes pour gérer les lieux.', 'mj-member')), 403);
            return;
        }

        if (!class_exists(MjEventLocations::class)) {
            wp_send_json_error(array('message' => __('Gestion des lieux indisponible.', 'mj-member')), 500);
            return;
        }

        $location_id = isset($_POST['locationId']) ? (int) $_POST['locationId'] : 0;

        if ($location_id > 0) {
            $location = MjEventLocations::find($location_id);
            if (!$location) {
                wp_send_json_error(array('message' => __('Lieu introuvable.', 'mj-member')), 404);
                return;
            }
            $payload = $this->formatLocationPayload($location);
        } else {
            $defaults = MjEventLocations::get_default_values();
            $defaults['id'] = 0;
            $payload = $this->formatLocationPayload($defaults);
        }

        wp_send_json_success($payload);
    }

    public function saveLocation() {
        $auth = $this->verifyRequest();
        if (!$auth) {
            return;
        }

        if (!$this->userCanManageLocations($auth)) {
            wp_send_json_error(array('message' => __('Permissions insuffisantes pour gérer les lieux.', 'mj-member')), 403);
            return;
        }

        if (!class_exists(MjEventLocations::class)) {
            wp_send_json_error(array('message' => __('Gestion des lieux indisponible.', 'mj-member')), 500);
            return;
        }

        $location_id = isset($_POST['locationId']) ? (int) $_POST['locationId'] : 0;
        $raw_data = isset($_POST['data']) ? wp_unslash((string) $_POST['data']) : '';
        $payload = json_decode($raw_data, true);
        if (!is_array($payload)) {
            wp_send_json_error(array('message' => __('Format de données invalide.', 'mj-member')), 400);
            return;
        }

        $allowed_fields = array('name', 'slug', 'address_line', 'postal_code', 'city', 'country', 'icon', 'cover_id', 'map_query', 'latitude', 'longitude', 'notes');
        $data = array();
        foreach ($allowed_fields as $field) {
            if (array_key_exists($field, $payload)) {
                $data[$field] = $payload[$field];
            }
        }

        if ($location_id > 0) {
            $result = MjEventLocations::update($location_id, $data);
        } else {
            $result = MjEventLocations::create($data);
        }

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 400);
            return;
        }

        $new_id = $location_id > 0 ? $location_id : (int) $result;
        $location = MjEventLocations::find($new_id);
        if (!$location) {
            wp_send_json_error(array('message' => __('Lieu introuvable après enregistrement.', 'mj-member')), 500);
            return;
        }

        $payload = $this->formatLocationPayload($location);
        $message = $location_id > 0 ? __('Lieu mis à jour.', 'mj-member') : __('Lieu créé.', 'mj-member');

        wp_send_json_success(array(
            'message' => $message,
            'location' => $payload['location'],
            'option' => $payload['option'],
        ));
    }

    private function sanitizeWeekdayTimes($weekday_times, array $schedule_weekdays) {
        $sanitized = array();

        if (!is_array($weekday_times)) {
            return $sanitized;
        }

        foreach ($weekday_times as $key => $time_info) {
            $key = sanitize_key($key);
            if (!isset($schedule_weekdays[$key]) || !is_array($time_info)) {
                continue;
            }

            $sanitized[$key] = array(
                'start' => isset($time_info['start']) ? sanitize_text_field($time_info['start']) : '',
                'end' => isset($time_info['end']) ? sanitize_text_field($time_info['end']) : '',
            );
        }

        return $sanitized;
    }

    private function sanitizeRecurrenceExceptions($value) {
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $value = $decoded;
            }
        }

        if ($value instanceof \Traversable) {
            $value = iterator_to_array($value);
        }

        if (!is_array($value)) {
            return array();
        }

        $timezone = wp_timezone();
        $normalized = array();

        foreach ($value as $item) {
            if ($item === null) {
                continue;
            }

            $candidate = $item;
            if (is_object($candidate)) {
                $candidate = get_object_vars($candidate);
            }

            $date_raw = '';
            $reason_raw = '';

            if (is_array($candidate)) {
                if (isset($candidate['date'])) {
                    $date_raw = $candidate['date'];
                } elseif (isset($candidate[0])) {
                    $date_raw = $candidate[0];
                }
                if (isset($candidate['reason'])) {
                    $reason_raw = $candidate['reason'];
                }
            } else {
                $date_raw = $candidate;
            }

            if (!is_scalar($date_raw)) {
                continue;
            }

            $raw_date = sanitize_text_field((string) $date_raw);
            if ($raw_date === '') {
                continue;
            }

            $date = \DateTime::createFromFormat('Y-m-d', $raw_date, $timezone);
            if (!$date instanceof \DateTime) {
                continue;
            }

            $key = $date->format('Y-m-d');

            $reason_value = '';
            if (is_scalar($reason_raw)) {
                $reason_value = sanitize_text_field((string) $reason_raw);
                if ($reason_value !== '') {
                    $reason_value = wp_strip_all_tags($reason_value, false);
                    if ($reason_value !== '') {
                        if (function_exists('mb_substr')) {
                            $reason_value = mb_substr($reason_value, 0, 200);
                        } else {
                            $reason_value = substr($reason_value, 0, 200);
                        }
                        $reason_value = trim($reason_value);
                    }
                }
            }

            $entry = array('date' => $key);
            if ($reason_value !== '') {
                $entry['reason'] = $reason_value;
            }

            $normalized[$key] = $entry;

            if (count($normalized) >= 365) {
                break;
            }
        }

        return array_values($normalized);
    }

    /**
     * @param array<string,mixed> $form_values
     * @param array<string,mixed> $meta
     * @return array<int,array<string,string>>
     */
    private function resolveScheduleExceptions(array $form_values, array $meta) {
        if (array_key_exists('schedule_exceptions', $form_values)) {
            return $this->sanitizeRecurrenceExceptions($form_values['schedule_exceptions']);
        }

        if (isset($meta['scheduleExceptions'])) {
            $from_meta = $this->sanitizeRecurrenceExceptions($meta['scheduleExceptions']);
            if (!empty($from_meta)) {
                return $from_meta;
            }
        }

        if (
            isset($meta['schedulePayload'])
            && is_array($meta['schedulePayload'])
            && array_key_exists('exceptions', $meta['schedulePayload'])
        ) {
            return $this->sanitizeRecurrenceExceptions($meta['schedulePayload']['exceptions']);
        }

        return array();
    }
    /**
     * Builds and validates the payload for updating or creating an event based on form values.
     */
    private function buildEventUpdatePayload($event, array $form_values, array $meta, array $references, array $schedule_weekdays, array $schedule_month_ordinals, array &$errors) {
        $errors = array();

        $existing_generator_plan = $this->extractOccurrenceGeneratorFromEvent($event);

        $submitted_generator_plan = array();
        $generator_plan_provided = false;

        if (isset($meta['occurrenceGenerator'])) {
            $generator_plan_provided = true;
            $submitted_generator_plan = $this->sanitizeOccurrenceGeneratorPlan($meta['occurrenceGenerator']);
        } elseif (isset($meta['schedulePayload']) && is_array($meta['schedulePayload'])) {
            $payload_plan = $this->extractOccurrenceGeneratorFromPayload($meta['schedulePayload']);
            if (!empty($payload_plan) || array_key_exists('occurrence_generator', $meta['schedulePayload']) || array_key_exists('occurrenceGenerator', $meta['schedulePayload'])) {
                $generator_plan_provided = true;
                $submitted_generator_plan = $payload_plan;
            }
        } elseif (isset($form_values['occurrenceGenerator']) && is_array($form_values['occurrenceGenerator'])) {
            $generator_plan_provided = true;
            $submitted_generator_plan = $this->sanitizeOccurrenceGeneratorPlan($form_values['occurrenceGenerator']);
        } elseif (isset($form_values['schedule_payload']) && is_array($form_values['schedule_payload'])) {
            if (array_key_exists('occurrence_generator', $form_values['schedule_payload']) || array_key_exists('occurrenceGenerator', $form_values['schedule_payload'])) {
                $payload_plan = $this->extractOccurrenceGeneratorFromPayload($form_values['schedule_payload']);
                if (!empty($payload_plan)) {
                    $submitted_generator_plan = $payload_plan;
                }
            }
        }

        $title = isset($form_values['title']) ? sanitize_text_field((string) $form_values['title']) : '';
        if ($title === '') {
            $errors[] = __('Le titre est obligatoire.', 'mj-member');
        }

        $status_labels = MjEvents::get_status_labels();
        $status = isset($form_values['status']) ? sanitize_key((string) $form_values['status']) : MjEvents::STATUS_DRAFT;
        if (!isset($status_labels[$status])) {
            $errors[] = __('Le statut sélectionné est invalide.', 'mj-member');
            $status = MjEvents::STATUS_DRAFT;
        }

        $type_labels = MjEvents::get_type_labels();
        $type = isset($form_values['type']) ? sanitize_key((string) $form_values['type']) : MjEvents::TYPE_STAGE;
        if (!isset($type_labels[$type])) {
            $errors[] = __('Le type sélectionné est invalide.', 'mj-member');
            $type = MjEvents::TYPE_STAGE;
        }

        $accent_color = isset($form_values['accent_color']) ? $this->normalizeHexColor($form_values['accent_color']) : '';
        $cover_id = isset($form_values['cover_id']) ? (int) $form_values['cover_id'] : 0;
        $description = isset($form_values['description']) ? $this->sanitizeRichHtmlForPdfTemplates($form_values['description']) : '';
        $registration_document = isset($form_values['registration_document']) ? $this->sanitizeRichHtmlForPdfTemplates($form_values['registration_document']) : '';

        $age_min = isset($form_values['age_min']) ? (int) $form_values['age_min'] : 0;
        $age_max = isset($form_values['age_max']) ? (int) $form_values['age_max'] : 0;
        if ($age_min < 0) {
            $age_min = 0;
        }
        if ($age_max < 0) {
            $age_max = 0;
        }
        if ($age_min > $age_max) {
            $errors[] = __('L\'âge minimum doit être inférieur ou égal à l\'âge maximum.', 'mj-member');
        }

        $capacity_total = isset($form_values['capacity_total']) ? max(0, (int) $form_values['capacity_total']) : 0;
        $capacity_waitlist = isset($form_values['capacity_waitlist']) ? max(0, (int) $form_values['capacity_waitlist']) : 0;
        $capacity_notify_threshold = isset($form_values['capacity_notify_threshold']) ? max(0, (int) $form_values['capacity_notify_threshold']) : 0;
        if ($capacity_total === 0 && $capacity_notify_threshold > 0) {
            $capacity_notify_threshold = 0;
        }
        if ($capacity_total > 0 && $capacity_notify_threshold >= $capacity_total) {
            $errors[] = __('Le seuil d\'alerte doit être inférieur au nombre total de places.', 'mj-member');
        }

        $price = isset($form_values['prix']) ? round((float) $form_values['prix'], 2) : 0.0;

        $schedule_mode = isset($form_values['schedule_mode']) ? sanitize_key((string) $form_values['schedule_mode']) : 'fixed';
        if (!in_array($schedule_mode, array('fixed', 'range', 'recurring', 'series'), true)) {
            $schedule_mode = 'fixed';
        }

        $timezone = wp_timezone();

        $date_debut = '';
        $date_fin = '';
        $schedule_payload = array();
        $series_items_clean = array();
        $recurrence_until_value = '';


        if ($date_debut === '' && !empty($form_values['date_debut'])) {
            $parsed = $this->parseEventDatetime($form_values['date_debut']);
            if ($parsed !== '') {
                $date_debut = $parsed;
            }
        }
        if ($date_fin === '' && !empty($form_values['date_fin'])) {
            $parsed = $this->parseEventDatetime($form_values['date_fin']);
            if ($parsed !== '') {
                $date_fin = $parsed;
            }
        }

        $date_fin_inscription_raw = isset($form_values['date_fin_inscription']) ? $form_values['date_fin_inscription'] : '';
        $date_fin_inscription = $date_fin_inscription_raw !== '' ? $this->parseEventDatetime($date_fin_inscription_raw) : '';

        $location_id = isset($form_values['location_id']) ? (int) $form_values['location_id'] : 0;
        if ($location_id > 0 && (!isset($references['location_ids'][$location_id]) || !$references['location_ids'][$location_id])) {
            $location_id = 0;
        }

        $animateur_ids = array();
        if (isset($form_values['animateur_ids']) && is_array($form_values['animateur_ids'])) {
            foreach ($form_values['animateur_ids'] as $animateur_candidate) {
                $animateur_candidate = (int) $animateur_candidate;
                if ($animateur_candidate <= 0) {
                    continue;
                }
                if (!empty($references['available_animateur_ids']) && !isset($references['available_animateur_ids'][$animateur_candidate])) {
                    $errors[] = __('Un animateur sélectionné est invalide.', 'mj-member');
                    continue;
                }
                $animateur_ids[$animateur_candidate] = $animateur_candidate;
            }
        }
        $animateur_ids = array_values($animateur_ids);

        $volunteer_ids = array();
        if (isset($form_values['volunteer_ids']) && is_array($form_values['volunteer_ids'])) {
            foreach ($form_values['volunteer_ids'] as $volunteer_candidate) {
                $volunteer_candidate = (int) $volunteer_candidate;
                if ($volunteer_candidate <= 0) {
                    continue;
                }
                if (!empty($references['available_volunteer_ids']) && !isset($references['available_volunteer_ids'][$volunteer_candidate])) {
                    $errors[] = __('Un bénévole sélectionné est invalide.', 'mj-member');
                    continue;
                }
                $volunteer_ids[$volunteer_candidate] = $volunteer_candidate;
            }
        }
        $volunteer_ids = array_values($volunteer_ids);

        // Location links (multiple locations with types)
        $location_links = null;
        if (isset($form_values['location_links']) && is_array($form_values['location_links'])) {
            $location_links = array();
            $valid_location_types = class_exists(MjEventLocationLinks::class) ? MjEventLocationLinks::get_valid_types() : array('departure', 'activity', 'return', 'other');
            $sort = 0;
            foreach ($form_values['location_links'] as $link) {
                $link_location_id = isset($link['locationId']) ? (int) $link['locationId'] : (isset($link['location_id']) ? (int) $link['location_id'] : 0);
                $link_type = isset($link['locationType']) ? sanitize_key($link['locationType']) : (isset($link['location_type']) ? sanitize_key($link['location_type']) : 'activity');
                $link_custom_label = isset($link['customLabel']) ? sanitize_textarea_field($link['customLabel']) : (isset($link['custom_label']) ? sanitize_textarea_field($link['custom_label']) : '');
                $link_meeting_time = isset($link['meetingTime']) ? sanitize_text_field($link['meetingTime']) : (isset($link['meeting_time']) ? sanitize_text_field($link['meeting_time']) : '');
                $link_meeting_time_end = isset($link['meetingTimeEnd']) ? sanitize_text_field($link['meetingTimeEnd']) : (isset($link['meeting_time_end']) ? sanitize_text_field($link['meeting_time_end']) : '');
            
                if ($link_location_id <= 0) {
                    continue;
                }
                if (!empty($references['location_ids']) && !isset($references['location_ids'][$link_location_id])) {
                    continue;
                }
                if (!in_array($link_type, $valid_location_types, true)) {
                    $link_type = 'activity';
                }
            
                $location_links[] = array(
                    'location_id' => $link_location_id,
                    'location_type' => $link_type,
                    'custom_label' => $link_custom_label,
                    'meeting_time' => $link_meeting_time,
                    'meeting_time_end' => $link_meeting_time_end,
                    'sort_order' => $sort,
                );
                $sort++;
            }
        }

        // If location_links are provided, derive primary location_id from first activity or first link
        if ($location_links !== null && !empty($location_links)) {
            $primary_location = 0;
            foreach ($location_links as $link) {
                if ($primary_location === 0) {
                    $primary_location = $link['location_id'];
                }
                if ($link['location_type'] === 'activity') {
                    $primary_location = $link['location_id'];
                    break;
                }
            }
            $location_id = $primary_location;
        }

        $primary_animateur_id = !empty($animateur_ids) ? (int) $animateur_ids[0] : 0;

        $allow_guardian_registration = !empty($form_values['allow_guardian_registration']);
        $requires_validation = array_key_exists('requires_validation', $form_values) ? !empty($form_values['requires_validation']) : true;
        $free_participation = !empty($form_values['registration_is_free_participation']) || !empty($form_values['free_participation']);

        $occurrence_selection_mode = isset($form_values['occurrence_selection_mode']) ? sanitize_key((string) $form_values['occurrence_selection_mode']) : 'member_choice';
        if (!in_array($occurrence_selection_mode, array('member_choice', 'all_occurrences'), true)) {
            $occurrence_selection_mode = 'member_choice';
        }

        $article_id = isset($form_values['article_id']) ? (int) $form_values['article_id'] : 0;

        $registration_payload = isset($form_values['registration_payload']) ? $form_values['registration_payload'] : array();
        if (!is_array($registration_payload)) {
            $registration_payload = $this->decodeJsonField($registration_payload);
        }

        $previous_capacity_total = isset($event->capacity_total) ? (int) $event->capacity_total : 0;
        $previous_capacity_threshold = isset($event->capacity_notify_threshold) ? (int) $event->capacity_notify_threshold : 0;
        $previous_capacity_notified = !empty($event->capacity_notified) ? 1 : 0;

        $capacity_notified_value = ($previous_capacity_total === $capacity_total && $previous_capacity_threshold === $capacity_notify_threshold) ? $previous_capacity_notified : 0;


        $derived_generator_plan = $this->deriveGeneratorPlanFromSchedule($schedule_payload);
        if (!empty($derived_generator_plan)) {
            if (!empty($submitted_generator_plan)) {
                $submitted_generator_plan = $this->mergeGeneratorPlans($submitted_generator_plan, $derived_generator_plan);
            } elseif (!empty($existing_generator_plan)) {
                $existing_generator_plan = $this->mergeGeneratorPlans($existing_generator_plan, $derived_generator_plan);
            } else {
                $submitted_generator_plan = $derived_generator_plan;
                $generator_plan_provided = true;
            }
        }

        if (!is_array($schedule_payload)) {
            $schedule_payload = array();
        }

        if (!empty($submitted_generator_plan)) {
            $schedule_payload['occurrence_generator'] = $submitted_generator_plan;
        } elseif ($generator_plan_provided) {
            unset($schedule_payload['occurrence_generator'], $schedule_payload['occurrenceGenerator']);
        } elseif (!isset($schedule_payload['occurrence_generator']) || !is_array($schedule_payload['occurrence_generator'])) {
            if (!empty($existing_generator_plan)) {
                $schedule_payload['occurrence_generator'] = $existing_generator_plan;
            }
        }

        $slug = isset($form_values['slug']) ? sanitize_title((string) $form_values['slug']) : '';

        $payload = array(
            'title' => $title,
            'slug' => $slug,
            'status' => $status,
            'type' => $type,
            'accent_color' => $accent_color,
            'emoji' => isset($form_values['emoji']) ? sanitize_text_field((string) $form_values['emoji']) : '',
            'cover_id' => $cover_id,
            'description' => $description,
            'registration_document' => $registration_document,
            'age_min' => $age_min,
            'age_max' => $age_max,
            'date_fin_inscription' => $date_fin_inscription !== '' ? $date_fin_inscription : null,
            'prix' => $price,
            'location_id' => $location_id,
            'animateur_id' => !empty($references['animateur_column_supported']) ? ($primary_animateur_id > 0 ? $primary_animateur_id : null) : null,
            'allow_guardian_registration' => $allow_guardian_registration ? 1 : 0,
            'requires_validation' => $requires_validation ? 1 : 0,
            'schedule_mode' => $schedule_mode,
            'schedule_payload' => $schedule_payload,
            'occurrence_selection_mode' => $occurrence_selection_mode,
            'free_participation' => $free_participation ? 1 : 0,
            'recurrence_until' => $recurrence_until_value !== '' ? $recurrence_until_value : null,
            'capacity_total' => $capacity_total,
            'capacity_waitlist' => $capacity_waitlist,
            'capacity_notify_threshold' => $capacity_notify_threshold,
            'capacity_notified' => $capacity_notified_value,
            'article_id' => $article_id,
            'registration_payload' => $registration_payload,
        );

        if (!empty($errors)) {
            return array(
                'payload' => array(),
                'animateur_ids' => array(),
                'volunteer_ids' => array(),
                'location_links' => null,
                'values' => $form_values,
            );
        }

        $form_values['schedule_payload'] = $schedule_payload;
        $form_values['schedule_series_items'] = $series_items_clean;
        $form_values['date_debut'] = $this->formatEventDatetime($date_debut);
        $form_values['date_fin'] = $this->formatEventDatetime($date_fin);
        $form_values['date_fin_inscription'] = $this->formatEventDatetime($date_fin_inscription);
        $form_values['recurrence_until'] = $recurrence_until_value !== '' ? date_i18n('Y-m-d', strtotime($recurrence_until_value)) : '';

        return array(
            'payload' => $payload,
            'animateur_ids' => $animateur_ids,
            'volunteer_ids' => $volunteer_ids,
            'location_links' => $location_links,
            'values' => $form_values,
        );
    }

    /**
     * Sanitize rich HTML while preserving table/layout styles needed for PDF templates.
     */
    private function sanitizeRichHtmlForPdfTemplates($html): string {
        $raw = is_string($html) ? $html : (string) $html;
        if ($raw === '') {
            return '';
        }

        $allowed_tags = wp_kses_allowed_html('post');
        $table_tags = array('table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'colgroup', 'col', 'caption');
        foreach ($table_tags as $tag) {
            if (!isset($allowed_tags[$tag]) || !is_array($allowed_tags[$tag])) {
                $allowed_tags[$tag] = array();
            }
            $allowed_tags[$tag] = array_merge($allowed_tags[$tag], array(
                'class' => true,
                'id' => true,
                'style' => true,
                'align' => true,
                'valign' => true,
                'width' => true,
                'height' => true,
            ));
        }

        if (!isset($allowed_tags['td'])) {
            $allowed_tags['td'] = array();
        }
        $allowed_tags['td']['colspan'] = true;
        $allowed_tags['td']['rowspan'] = true;

        if (!isset($allowed_tags['th'])) {
            $allowed_tags['th'] = array();
        }
        $allowed_tags['th']['colspan'] = true;
        $allowed_tags['th']['rowspan'] = true;
        $allowed_tags['th']['scope'] = true;

        if (!isset($allowed_tags['img'])) {
            $allowed_tags['img'] = array();
        }
        $allowed_tags['img'] = array_merge($allowed_tags['img'], array(
            'class' => true,
            'id' => true,
            'style' => true,
            'src' => true,
            'srcset' => true,
            'sizes' => true,
            'alt' => true,
            'title' => true,
            'width' => true,
            'height' => true,
            'loading' => true,
            'data-src' => true,
            'data-lazy-src' => true,
            'data-original' => true,
        ));

        $extra_safe_css = array(
            'float', 'clear', 'display',
            'width', 'height', 'min-width', 'min-height', 'max-width', 'max-height',
            'margin', 'margin-left', 'margin-right', 'margin-top', 'margin-bottom',
            'padding', 'padding-left', 'padding-right', 'padding-top', 'padding-bottom',
            'border', 'border-width', 'border-style', 'border-color',
            'border-collapse', 'border-spacing', 'table-layout', 'vertical-align',
            'text-align', 'font-size', 'font-weight', 'line-height',
            'background', 'background-color', 'color'
        );

        $safe_css_filter = static function ($styles) use ($extra_safe_css) {
            if (!is_array($styles)) {
                $styles = array();
            }
            return array_values(array_unique(array_merge($styles, $extra_safe_css)));
        };

        add_filter('safe_style_css', $safe_css_filter, 999);
        $sanitized = wp_kses($raw, $allowed_tags);
        remove_filter('safe_style_css', $safe_css_filter, 999);

        return is_string($sanitized) ? $sanitized : '';
    }

    private function serializeEventSummary($event) {
        if (!$event) {
            return array();
        }

        $type_labels = MjEvents::get_type_labels();
        $status_labels = MjEvents::get_status_labels();

        $schedule_mode = isset($event->schedule_mode) ? sanitize_key((string) $event->schedule_mode) : 'fixed';
        if ($schedule_mode === '') {
            $schedule_mode = 'fixed';
        }
        $schedule_info = $this->buildEventScheduleInfo($event, $schedule_mode);
        $occurrence_generator = $this->extractOccurrenceGeneratorFromEvent($event);
        $inline_schedule_preview = $this->buildInlineSchedulePreviewData($event, array(), $schedule_info, $occurrence_generator);

        return array(
            'id' => isset($event->id) ? (int) $event->id : 0,
            'title' => isset($event->title) ? (string) $event->title : '',
            'slug' => isset($event->slug) ? (string) $event->slug : '',
            'status' => isset($event->status) ? (string) $event->status : '',
            'statusLabel' => isset($event->status) && isset($status_labels[$event->status]) ? $status_labels[$event->status] : (isset($event->status) ? $event->status : ''),
            'type' => isset($event->type) ? (string) $event->type : '',
            'typeLabel' => isset($event->type) && isset($type_labels[$event->type]) ? $type_labels[$event->type] : (isset($event->type) ? $event->type : ''),
            'dateDebut' => isset($event->date_debut) ? (string) $event->date_debut : '',
            'dateFin' => isset($event->date_fin) ? (string) $event->date_fin : '',
            'occurrenceSelectionMode' => isset($event->occurrence_selection_mode) && $event->occurrence_selection_mode !== '' ? (string) $event->occurrence_selection_mode : 'member_choice',
            'accentColor' => isset($event->accent_color) ? (string) $event->accent_color : '',
            'articleId' => isset($event->article_id) ? (int) $event->article_id : 0,
            'coverId' => isset($event->cover_id) ? (int) $event->cover_id : 0,
            'coverUrl' => $this->getEventCoverUrl($event, 'medium'),
            'coverFullUrl' => $this->getEventCoverUrl($event, 'full'),
            'capacityTotal' => isset($event->capacity_total) ? (int) $event->capacity_total : 0,
            'capacityWaitlist' => isset($event->capacity_waitlist) ? (int) $event->capacity_waitlist : 0,
            'prix' => isset($event->prix) ? (float) $event->prix : 0.0,
            'occurrenceGenerator' => $occurrence_generator,
            'isFromGenerator' => !empty($occurrence_generator),
            'inlineScheduleHtml' => $inline_schedule_preview['html'],
            'scheduleMode' => $schedule_mode,
            'scheduleSummary' => isset($schedule_info['summary']) ? $schedule_info['summary'] : '',
            'scheduleDetail' => isset($schedule_info['detail']) ? $schedule_info['detail'] : '',
            'occurrenceScheduleSummary' => isset($schedule_info['summary']) ? $schedule_info['summary'] : '',
            'occurrence_schedule_summary' => isset($schedule_info['summary']) ? $schedule_info['summary'] : '',
        );
    }

    public function saveEventOccurrences() {
        $auth = $this->verifyRequest();
        if (!$auth) {
            return;
        }

        $event_id = isset($_POST['eventId']) ? (int) $_POST['eventId'] : 0;
        if ($event_id <= 0) {
            wp_send_json_error(array('message' => __('ID événement invalide.', 'mj-member')), 400);
            return;
        }

        $event = MjEvents::find($event_id);
        if (!$event) {
            wp_send_json_error(array('message' => __('Événement introuvable.', 'mj-member')), 404);
            return;
        }

        $can_manage = current_user_can(Config::capability()) || !empty($auth['is_coordinateur']);
        if (!$can_manage) {
            if (!class_exists(MjEventAnimateurs::class) || !MjEventAnimateurs::member_is_assigned($event_id, $auth['member_id'])) {
                wp_send_json_error(array('message' => __('Permissions insuffisantes pour modifier les occurrences de cet événement.', 'mj-member')), 403);
                return;
            }
        }

        $existing_schedule_payload = array();
        if (!empty($event->schedule_payload)) {
            $decoded_payload = $this->decodeJsonField($event->schedule_payload);
            if (is_array($decoded_payload)) {
                $existing_schedule_payload = $decoded_payload;
            }
        }
        $existing_generator_plan = $this->extractOccurrenceGeneratorFromPayload($existing_schedule_payload);

        $submitted_schedule_summary = '';
        if (isset($_POST['scheduleSummary'])) {
            $submitted_schedule_summary = trim((string) wp_unslash($_POST['scheduleSummary']));
            if ($submitted_schedule_summary !== '' && function_exists('mb_substr')) {
                $submitted_schedule_summary = mb_substr($submitted_schedule_summary, 0, 600);
            } else {
                $submitted_schedule_summary = substr($submitted_schedule_summary, 0, 600);
            }
            $submitted_schedule_summary = wp_strip_all_tags($submitted_schedule_summary);
        }

        $generator_plan_input = array();
        $generator_plan_provided = false;
        if (isset($_POST['generatorPlan'])) {
            $generator_plan_provided = true;
            $plan_candidate = wp_unslash($_POST['generatorPlan']);
            if (is_string($plan_candidate)) {
                $decoded_plan = json_decode($plan_candidate, true);
                if (is_array($decoded_plan)) {
                    $generator_plan_input = $decoded_plan;
                }
            } elseif (is_array($plan_candidate)) {
                $generator_plan_input = $plan_candidate;
            }
        }

        $sanitized_generator_plan = array();
        if ($generator_plan_provided) {
            $sanitized_generator_plan = $this->sanitizeOccurrenceGeneratorPlan($generator_plan_input);
        }

        $raw_occurrences = array();
        if (isset($_POST['occurrences'])) {
            $payload = $_POST['occurrences'];
            if (is_string($payload)) {
                $decoded = json_decode(wp_unslash($payload), true);
                if (is_array($decoded)) {
                    $raw_occurrences = $decoded;
                }
            } elseif (is_array($payload)) {
                foreach ($payload as $value) {
                    if (is_array($value)) {
                        $raw_occurrences[] = $value;
                    } elseif (is_string($value)) {
                        $decoded_value = json_decode(wp_unslash($value), true);
                        if (is_array($decoded_value)) {
                            $raw_occurrences[] = $decoded_value;
                        }
                    }
                }
            }
        }

        if (empty($raw_occurrences)) {
            $prefixed_candidates = array();
            foreach ($_POST as $key => $value) {
                if (strpos($key, 'occurrences[') === 0) {
                    $prefixed_candidates[] = $value;
                }
            }
            if (!empty($prefixed_candidates)) {
                foreach ($prefixed_candidates as $candidate) {
                    if (is_string($candidate)) {
                        $decoded_candidate = json_decode(wp_unslash($candidate), true);
                        if (is_array($decoded_candidate)) {
                            $raw_occurrences[] = $decoded_candidate;
                        }
                    } elseif (is_array($candidate)) {
                        $raw_occurrences[] = $candidate;
                    }
                }
            }
        }

        $normalized = $this->prepareEventOccurrenceRows($raw_occurrences);

        $schedule_mode = isset($event->schedule_mode) && $event->schedule_mode !== ''
            ? sanitize_key((string) $event->schedule_mode)
            : 'fixed';

        $schedule_payload_update = null;
        if (!empty($normalized['rows'])) {
            if ($schedule_mode === 'fixed') {
                $min_start = $normalized['stats']['min_start'];
                $max_end = $normalized['stats']['max_end'];
                if ($min_start !== null && $max_end !== null) {
                    $schedule_payload_update = array(
                        'mode' => 'fixed',
                        'version' => 'occurrence-editor',
                        'date' => substr($min_start, 0, 10),
                        'start_time' => substr($min_start, 11, 5),
                        'end_time' => substr($max_end, 11, 5),
                    );
                }
            } elseif ($schedule_mode === 'series') {
                $occurrence_payload_items = array();
                $series_items = array();
                foreach ($normalized['rows'] as $row_item) {
                    $occurrence_payload_items[] = $row_item;
                    $series_items[] = array(
                        'date' => substr($row_item['start'], 0, 10),
                        'start_time' => substr($row_item['start'], 11, 5),
                        'end_time' => substr($row_item['end'], 11, 5),
                        'status' => isset($row_item['status']) ? $row_item['status'] : MjEventOccurrences::STATUS_ACTIVE,
                    );
                }
                $schedule_payload_update = array(
                    'mode' => 'series',
                    'version' => 'occurrence-editor',
                    'occurrences' => $occurrence_payload_items,
                    'items' => $series_items,
                );
            }
        } else {
            if ($schedule_mode === 'fixed') {
                $schedule_payload_update = array(
                    'mode' => 'fixed',
                    'version' => 'occurrence-editor',
                );
            } elseif ($schedule_mode === 'series') {
                $schedule_payload_update = array(
                    'mode' => 'series',
                    'version' => 'occurrence-editor',
                    'occurrences' => array(),
                    'items' => array(),
                );
            }
        }

        if ($schedule_payload_update === null) {
            $existing_payload = $existing_schedule_payload;
            if (!isset($existing_payload['mode']) && $schedule_mode !== '') {
                $existing_payload['mode'] = $schedule_mode;
            }
            $existing_payload['occurrence_summary'] = $submitted_schedule_summary;
            if (!isset($existing_payload['version']) || $existing_payload['version'] === '') {
                $existing_payload['version'] = 'occurrence-editor';
            }
            $schedule_payload_update = $existing_payload;
        } else {
            $schedule_payload_update['occurrence_summary'] = $submitted_schedule_summary;
            if (!isset($schedule_payload_update['version']) || $schedule_payload_update['version'] === '') {
                $schedule_payload_update['version'] = 'occurrence-editor';
            }
        }

        if ($generator_plan_provided) {
            $schedule_payload_update['occurrence_generator'] = $sanitized_generator_plan;
        } elseif (!empty($existing_generator_plan)) {
            $schedule_payload_update['occurrence_generator'] = $existing_generator_plan;
        }

        $updates = array();
        if (!empty($normalized['rows'])) {
            if (!empty($normalized['stats']['min_start'])) {
                $updates['date_debut'] = $normalized['stats']['min_start'];
            }
            if (!empty($normalized['stats']['max_end'])) {
                $updates['date_fin'] = $normalized['stats']['max_end'];
            }
        } else {
            $updates['date_debut'] = null;
            $updates['date_fin'] = null;
        }

        if ($schedule_payload_update !== null) {
            $updates['schedule_payload'] = $schedule_payload_update;
        }

        if (!empty($updates)) {
            $update_result = MjEvents::update($event_id, $updates);
            if (is_wp_error($update_result)) {
                wp_send_json_error(array('message' => $update_result->get_error_message()), 500);
                return;
            }
        }

        MjEventOccurrences::replace_for_event($event_id, $normalized['rows']);

        $refreshed_event = MjEvents::find($event_id);
        if (!$refreshed_event) {
            $refreshed_event = $event;
        }

        $schedule_mode = isset($refreshed_event->schedule_mode) && $refreshed_event->schedule_mode !== ''
            ? sanitize_key((string) $refreshed_event->schedule_mode)
            : 'fixed';
        $schedule_info = $this->buildEventScheduleInfo($refreshed_event, $schedule_mode);

        $occurrence_rows = class_exists(MjEventSchedule::class)
            ? MjEventSchedule::build_all_occurrences($refreshed_event)
            : array();
        $occurrence_payload = $this->formatEventOccurrencesForFront($occurrence_rows);

        if (empty($occurrence_payload) && $this->shouldAllowOccurrenceFallback($refreshed_event)) {
            $fallback_end = isset($refreshed_event->date_fin) ? (string) $refreshed_event->date_fin : '';
            $occurrence_payload[] = array(
                'id' => 'event-' . $refreshed_event->id,
                'start' => (string) $refreshed_event->date_debut,
                'end' => $fallback_end,
                'date' => substr((string) $refreshed_event->date_debut, 0, 10),
                'startTime' => substr((string) $refreshed_event->date_debut, 11, 5),
                'endTime' => $fallback_end !== '' ? substr($fallback_end, 11, 5) : '',
                'isAllDay' => false,
                'status' => 'planned',
                'reason' => '',
                'startFormatted' => $this->formatDate((string) $refreshed_event->date_debut, true),
                'endFormatted' => $fallback_end !== '' ? $this->formatDate($fallback_end, true) : '',
            );
        }

        $updated_generator_plan = $this->extractOccurrenceGeneratorFromEvent($refreshed_event);
        $inline_schedule_preview = $this->buildInlineSchedulePreviewData($refreshed_event, $occurrence_rows, $schedule_info, $updated_generator_plan);

        $response_event = array(
            'id' => isset($refreshed_event->id) ? (int) $refreshed_event->id : $event_id,
            'occurrences' => $occurrence_payload,
            'dateDebut' => isset($refreshed_event->date_debut) ? (string) $refreshed_event->date_debut : '',
            'dateFin' => isset($refreshed_event->date_fin) ? (string) $refreshed_event->date_fin : '',
            'dateDebutFormatted' => $this->formatDate(isset($refreshed_event->date_debut) ? $refreshed_event->date_debut : '', true),
            'dateFinFormatted' => $this->formatDate(isset($refreshed_event->date_fin) ? $refreshed_event->date_fin : '', true),
            'scheduleSummary' => isset($schedule_info['summary']) ? $schedule_info['summary'] : '',
            'scheduleDetail' => isset($schedule_info['detail']) ? $schedule_info['detail'] : '',
            'occurrenceScheduleSummary' => $submitted_schedule_summary !== ''
                ? $submitted_schedule_summary
                : (isset($schedule_info['summary']) ? $schedule_info['summary'] : ''),
            'occurrenceGenerator' => $updated_generator_plan,
            'isFromGenerator' => !empty($updated_generator_plan),
            'inlineScheduleHtml' => $inline_schedule_preview['html'],
        );

        if (!empty($normalized['stats']['min_start'])) {
            $response_event['dateDebut'] = $normalized['stats']['min_start'];
            $response_event['dateDebutFormatted'] = $this->formatDate($normalized['stats']['min_start'], true);
        }
        if (!empty($normalized['stats']['max_end'])) {
            $response_event['dateFin'] = $normalized['stats']['max_end'];
            $response_event['dateFinFormatted'] = $this->formatDate($normalized['stats']['max_end'], true);
        }

        if (!empty($normalized['stats']['min_start']) || !empty($normalized['stats']['max_end'])) {
            $refreshed_event = $refreshed_event ? $refreshed_event->with(array(
                'date_debut' => !empty($normalized['stats']['min_start']) ? $normalized['stats']['min_start'] : $refreshed_event->date_debut,
                'date_fin' => !empty($normalized['stats']['max_end']) ? $normalized['stats']['max_end'] : $refreshed_event->date_fin,
            )) : $refreshed_event;
        }

        $event_summary = $this->serializeEventSummary($refreshed_event);

        wp_send_json_success(array(
            'message' => __('Occurrences mises à jour.', 'mj-member'),
            'event' => $response_event,
            'eventSummary' => $event_summary,
        ));
    }

    /**
     * Update selected occurrences for a registration
     */
    public function updateOccurrences() {
        $auth = $this->verifyRequest();
        if (!$auth) return;

        $registration_id = isset($_POST['registrationId']) ? (int) $_POST['registrationId'] : 0;
        $occurrences = isset($_POST['occurrences']) ? (array) $_POST['occurrences'] : array();

        if ($registration_id <= 0) {
            wp_send_json_error(array('message' => __('ID inscription invalide.', 'mj-member')));
            return;
        }

        // Sanitize occurrences
        $sanitized = array();
        foreach ($occurrences as $occ) {
            $sanitized[] = sanitize_text_field($occ);
        }

        $result = MjEventRegistrations::update($registration_id, array(
            'selected_occurrences' => wp_json_encode($sanitized),
        ));

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
            return;
        }

        // Also sync to attendance_payload assignments so public and admin sides stay aligned
        $assignments_mode = empty($sanitized) ? 'all' : 'custom';
        MjEventAttendance::set_registration_assignments($registration_id, array(
            'mode' => $assignments_mode,
            'occurrences' => $sanitized,
        ));

        wp_send_json_success(array(
            'message' => __('Séances mises à jour.', 'mj-member'),
        ));
    }

    /**
     * Send the registration contract by email to the selected recipient.
     */
    public function sendRegistrationContract() {
        $auth = $this->verifyRequest();
        if (!$auth) return;

        $registration_id = isset($_POST['registrationId']) ? (int) $_POST['registrationId'] : 0;
        $recipient_type = isset($_POST['recipientType']) ? sanitize_key((string) $_POST['recipientType']) : '';

        if (!in_array($recipient_type, array('young', 'guardian'), true)) {
            wp_send_json_error(array('message' => __('Destinataire invalide.', 'mj-member')), 400);
            return;
        }

        if ($registration_id <= 0) {
            wp_send_json_error(array('message' => __('ID inscription invalide.', 'mj-member')), 400);
            return;
        }

        global $wpdb;
        $registrations_table = function_exists('mj_member_get_event_registrations_table_name')
            ? mj_member_get_event_registrations_table_name()
            : '';
        if ($registrations_table === '') {
            wp_send_json_error(array('message' => __('Table des inscriptions introuvable.', 'mj-member')), 500);
            return;
        }

        $registration = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$registrations_table} WHERE id = %d", $registration_id));
        if (!$registration) {
            wp_send_json_error(array('message' => __('Inscription introuvable.', 'mj-member')), 404);
            return;
        }

        $event = MjEvents::find((int) $registration->event_id);
        if (!$event) {
            wp_send_json_error(array('message' => __('Événement introuvable.', 'mj-member')), 404);
            return;
        }

        $registration_document = isset($event->registration_document) ? (string) $event->registration_document : '';
        if ($registration_document === '') {
            wp_send_json_error(array('message' => __('Aucun contrat n\'est configuré pour cet événement.', 'mj-member')), 400);
            return;
        }

        $member = MjMembers::getById((int) $registration->member_id);
        if (!$member) {
            wp_send_json_error(array('message' => __('Membre introuvable.', 'mj-member')), 404);
            return;
        }

        $guardian_id = !empty($member->guardian_id) ? (int) $member->guardian_id : (int) ($registration->guardian_id ?? 0);
        $guardian = $guardian_id > 0 ? MjMembers::getById($guardian_id) : null;

        $recipient_email = '';
        $recipient_name = '';
        if ($recipient_type === 'young') {
            $recipient_email = isset($member->email) ? sanitize_email((string) $member->email) : '';
            $recipient_name = trim(((string) ($member->first_name ?? '')) . ' ' . ((string) ($member->last_name ?? '')));
        } else {
            $recipient_email = $guardian ? sanitize_email((string) ($guardian->email ?? '')) : '';
            $recipient_name = $guardian
                ? trim(((string) ($guardian->first_name ?? '')) . ' ' . ((string) ($guardian->last_name ?? '')))
                : '';
        }

        if ($recipient_email === '' || !is_email($recipient_email)) {
            wp_send_json_error(array('message' => __('Adresse email du destinataire introuvable ou invalide.', 'mj-member')), 400);
            return;
        }

        $variables = $this->buildRegistrationDocumentVariables($event, $member, $guardian);
        $processed_header = $this->interpolateRegistrationDocumentTemplate((string) get_option('mj_regdoc_header', ''), $variables);
        $processed_content = $this->interpolateRegistrationDocumentTemplate($registration_document, $variables);
        $processed_footer = $this->interpolateRegistrationDocumentTemplate((string) get_option('mj_regdoc_footer', ''), $variables);

        $event_title = isset($event->title) ? (string) $event->title : __('Événement', 'mj-member');
        $member_name_for_contract = trim(((string) ($member->first_name ?? '')) . ' ' . ((string) ($member->last_name ?? '')));

        $pdf_result = $this->buildRegistrationContractPdf(
            $processed_header,
            $processed_content,
            $processed_footer,
            $event_title,
            $member_name_for_contract
        );

        if (is_wp_error($pdf_result)) {
            wp_send_json_error(array('message' => $pdf_result->get_error_message()), 500);
            return;
        }

        $tmp_file = wp_tempnam('mj-reg-contract-' . $registration_id);
        if (!is_string($tmp_file) || $tmp_file === '') {
            wp_send_json_error(array('message' => __('Impossible de préparer le fichier PDF.', 'mj-member')), 500);
            return;
        }

        $tmp_dir = dirname($tmp_file);
        if (file_exists($tmp_file)) {
            @unlink($tmp_file);
        }
        $attachment_name = isset($pdf_result['filename']) ? (string) $pdf_result['filename'] : '';
        if ($attachment_name === '') {
            $attachment_name = 'contrat-inscription-' . date_i18n('Ymd') . '.pdf';
        }
        $attachment_name = wp_unique_filename($tmp_dir, $attachment_name);
        $tmp_file = trailingslashit($tmp_dir) . $attachment_name;

        if (file_put_contents($tmp_file, (string) $pdf_result['content']) === false) {
            if (file_exists($tmp_file)) {
                @unlink($tmp_file);
            }
            wp_send_json_error(array('message' => __('Impossible de générer le fichier PDF.', 'mj-member')), 500);
            return;
        }

        $recipient_label = $recipient_type === 'young'
            ? __('au jeune', 'mj-member')
            : __('au tuteur', 'mj-member');

        $subject = sprintf(__('Contrat d\'inscription - %s', 'mj-member'), $event_title);
        $message = sprintf(
            '<p>%s</p><p>%s</p><p>%s</p>',
            esc_html(sprintf(__('Bonjour %s,', 'mj-member'), $recipient_name !== '' ? $recipient_name : __('à vous', 'mj-member'))),
            esc_html(sprintf(__('Vous trouverez en pièce jointe le contrat d\'inscription pour %s.', 'mj-member'), $event_title)),
            esc_html__('Cordialement,', 'mj-member')
        );

        $sender_email = 'site@mj-pery.be';
        $site_name = wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES);
        if ($site_name === '') {
            $site_name = 'MJ Pery';
        }

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $site_name . ' <' . $sender_email . '>',
            'Reply-To: ' . $sender_email,
        );
        $mail_sent = wp_mail($recipient_email, $subject, $message, $headers, array($tmp_file));

        if (file_exists($tmp_file)) {
            @unlink($tmp_file);
        }

        if (!$mail_sent) {
            wp_send_json_error(array('message' => __('Impossible d\'envoyer l\'email.', 'mj-member')), 500);
            return;
        }

        $payload = $this->decodeRegistrationPayloadForStorage(isset($registration->attendance_payload) ? $registration->attendance_payload : null);
        if (!isset($payload['meta']) || !is_array($payload['meta'])) {
            $payload['meta'] = array();
        }
        if (!isset($payload['meta']['contract_emails']) || !is_array($payload['meta']['contract_emails'])) {
            $payload['meta']['contract_emails'] = array();
        }

        $now = current_time('mysql');
        $payload['meta']['contract_emails'][$recipient_type . '_sent_at'] = $now;
        $payload['meta']['contract_emails'][$recipient_type . '_sent_by'] = get_current_user_id();

        $encoded_payload = wp_json_encode($payload);
        if ($encoded_payload === false || $encoded_payload === null) {
            wp_send_json_error(array('message' => __('Impossible de sauvegarder l\'état d\'envoi du contrat.', 'mj-member')), 500);
            return;
        }

        $updated = $wpdb->update(
            $registrations_table,
            array(
                'attendance_payload' => $encoded_payload,
                'attendance_updated_at' => $now,
            ),
            array('id' => $registration_id),
            array('%s', '%s'),
            array('%d')
        );

        if ($updated === false) {
            wp_send_json_error(array('message' => __('Email envoyé, mais impossible de sauvegarder son statut.', 'mj-member')), 500);
            return;
        }

        wp_send_json_success(array(
            'message' => sprintf(__('Contrat envoyé %s.', 'mj-member'), $recipient_label),
            'registrationId' => $registration_id,
            'recipientType' => $recipient_type,
            'contractEmailStatus' => $this->extractContractEmailStatus($encoded_payload),
        ));
    }

    /**
     * Generate a registration contract PDF and return it for direct download.
     */
    public function downloadRegistrationContractPdf() {
        try {
            $auth = $this->verifyRequest();
            if (!$auth) return;

            $registration_id = isset($_POST['registrationId']) ? (int) $_POST['registrationId'] : 0;
            if ($registration_id <= 0) {
                wp_send_json_error(array('message' => __('ID inscription invalide.', 'mj-member')), 400);
                return;
            }

            global $wpdb;
            $registrations_table = function_exists('mj_member_get_event_registrations_table_name')
                ? mj_member_get_event_registrations_table_name()
                : '';
            if ($registrations_table === '') {
                wp_send_json_error(array('message' => __('Table des inscriptions introuvable.', 'mj-member')), 500);
                return;
            }

            $registration = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$registrations_table} WHERE id = %d", $registration_id));
            if (!$registration) {
                wp_send_json_error(array('message' => __('Inscription introuvable.', 'mj-member')), 404);
                return;
            }

            $event = MjEvents::find((int) $registration->event_id);
            if (!$event) {
                wp_send_json_error(array('message' => __('Événement introuvable.', 'mj-member')), 404);
                return;
            }

            $registration_document = isset($event->registration_document) ? (string) $event->registration_document : '';
            if ($registration_document === '') {
                wp_send_json_error(array('message' => __('Aucun contrat n\'est configuré pour cet événement.', 'mj-member')), 400);
                return;
            }

            $member = MjMembers::getById((int) $registration->member_id);
            if (!$member) {
                wp_send_json_error(array('message' => __('Membre introuvable.', 'mj-member')), 404);
                return;
            }

            $guardian_id = !empty($member->guardian_id) ? (int) $member->guardian_id : (int) ($registration->guardian_id ?? 0);
            $guardian = $guardian_id > 0 ? MjMembers::getById($guardian_id) : null;

            $variables = $this->buildRegistrationDocumentVariables($event, $member, $guardian);
            $processed_header = $this->interpolateRegistrationDocumentTemplate((string) get_option('mj_regdoc_header', ''), $variables);
            $processed_content = $this->interpolateRegistrationDocumentTemplate($registration_document, $variables);
            $processed_footer = $this->interpolateRegistrationDocumentTemplate((string) get_option('mj_regdoc_footer', ''), $variables);

            $event_title = isset($event->title) ? (string) $event->title : __('Événement', 'mj-member');
            $member_name_for_contract = trim(((string) ($member->first_name ?? '')) . ' ' . ((string) ($member->last_name ?? '')));

            $pdf_result = $this->buildRegistrationContractPdf(
                $processed_header,
                $processed_content,
                $processed_footer,
                $event_title,
                $member_name_for_contract
            );

            if (is_wp_error($pdf_result)) {
                error_log('[MjRegMgr] PDF download generation failed for registration #' . $registration_id . ': ' . $pdf_result->get_error_message());
                wp_send_json_error(array('message' => $pdf_result->get_error_message()), 500);
                return;
            }

            $filename = isset($pdf_result['filename']) ? (string) $pdf_result['filename'] : '';
            if ($filename === '') {
                $filename = 'contrat-inscription-' . date_i18n('Ymd') . '.pdf';
            }

            $content = isset($pdf_result['content']) ? (string) $pdf_result['content'] : '';
            if ($content === '') {
                wp_send_json_error(array('message' => __('Impossible de générer le PDF.', 'mj-member')), 500);
                return;
            }

            $renderer = isset($pdf_result['renderer']) ? (string) $pdf_result['renderer'] : 'unknown';
            error_log('[MjRegMgr] PDF download renderer for registration #' . $registration_id . ': ' . $renderer);

            $upload_dir = wp_upload_dir();
            if (!is_array($upload_dir) || !empty($upload_dir['error'])) {
                wp_send_json_error(array('message' => __('Impossible de préparer le dossier de téléchargement.', 'mj-member')), 500);
                return;
            }

            $subdir = '/mj-member/reg-contracts';
            $target_dir = trailingslashit($upload_dir['basedir']) . ltrim($subdir, '/');
            if (!wp_mkdir_p($target_dir)) {
                wp_send_json_error(array('message' => __('Impossible de créer le dossier de téléchargement.', 'mj-member')), 500);
                return;
            }

            $stored_name = wp_unique_filename($target_dir, $filename);
            $stored_path = trailingslashit($target_dir) . $stored_name;
            $write_result = file_put_contents($stored_path, $content);
            if ($write_result === false) {
                wp_send_json_error(array('message' => __('Impossible d\'écrire le fichier PDF.', 'mj-member')), 500);
                return;
            }

            $download_url = trailingslashit($upload_dir['baseurl']) . ltrim($subdir, '/') . '/' . rawurlencode($stored_name);

            wp_send_json_success(array(
                'message' => __('PDF généré.', 'mj-member'),
                'registrationId' => $registration_id,
                'filename' => $stored_name,
                'downloadUrl' => $download_url,
                'renderer' => $renderer,
            ));
        } catch (\Throwable $e) {
            error_log('[MjRegMgr] downloadRegistrationContractPdf fatal: ' . $e->getMessage());
            wp_send_json_error(array('message' => __('Erreur interne lors de la génération du PDF.', 'mj-member')), 500);
        }
    }

    /**
     * Extract contract email status flags from attendance payload.
     */
    private function extractContractEmailStatus($payload_raw): array {
        $payload = $this->decodeRegistrationPayloadForStorage($payload_raw);
        $meta = isset($payload['meta']) && is_array($payload['meta']) ? $payload['meta'] : array();

        $contracts = array();
        if (isset($meta['contract_emails']) && is_array($meta['contract_emails'])) {
            $contracts = $meta['contract_emails'];
        } elseif (isset($payload['contract_emails']) && is_array($payload['contract_emails'])) {
            $contracts = $payload['contract_emails'];
        }

        $young_sent_at = isset($contracts['young_sent_at']) ? (string) $contracts['young_sent_at'] : '';
        $guardian_sent_at = isset($contracts['guardian_sent_at']) ? (string) $contracts['guardian_sent_at'] : '';

        return array(
            'youngSent' => $young_sent_at !== '',
            'youngSentAt' => $young_sent_at,
            'guardianSent' => $guardian_sent_at !== '',
            'guardianSentAt' => $guardian_sent_at,
        );
    }

    /**
     * Decode registration payload while preserving custom meta keys.
     */
    private function decodeRegistrationPayloadForStorage($payload_raw): array {
        if (!is_string($payload_raw) || $payload_raw === '') {
            return array(
                'occurrences' => array(),
                'assignments' => array('mode' => 'all', 'occurrences' => array()),
                'meta' => array(),
            );
        }

        $decoded = json_decode($payload_raw, true);
        if (!is_array($decoded)) {
            return array(
                'occurrences' => array(),
                'assignments' => array('mode' => 'all', 'occurrences' => array()),
                'meta' => array(),
            );
        }

        $payload = array(
            'occurrences' => array(),
            'assignments' => array('mode' => 'all', 'occurrences' => array()),
            'meta' => array(),
        );

        if (isset($decoded['occurrences']) && is_array($decoded['occurrences'])) {
            $payload['occurrences'] = $decoded['occurrences'];
        } elseif (!isset($decoded['assignments']) && !isset($decoded['meta'])) {
            // Legacy payload format where occurrences were stored at root level.
            $payload['occurrences'] = $decoded;
        }

        if (isset($decoded['assignments']) && is_array($decoded['assignments'])) {
            $mode = isset($decoded['assignments']['mode']) && $decoded['assignments']['mode'] === 'custom' ? 'custom' : 'all';
            $occurrences = array();
            if ($mode === 'custom' && isset($decoded['assignments']['occurrences']) && is_array($decoded['assignments']['occurrences'])) {
                foreach ($decoded['assignments']['occurrences'] as $occurrence) {
                    $normalized = MjEventAttendance::normalize_occurrence($occurrence);
                    if ($normalized !== '') {
                        $occurrences[$normalized] = true;
                    }
                }
            }
            $payload['assignments'] = array(
                'mode' => $mode,
                'occurrences' => array_values(array_keys($occurrences)),
            );
        }

        if (isset($decoded['meta']) && is_array($decoded['meta'])) {
            $payload['meta'] = $decoded['meta'];
        }

        return $payload;
    }

    /**
     * Replace [variable_name] tokens in registration templates.
     */
    private function interpolateRegistrationDocumentTemplate(string $text, array $variables): string {
        if ($text === '') {
            return '';
        }

        $replaced = $text;
        foreach ($variables as $key => $value) {
            $replaced = str_ireplace('[' . $key . ']', (string) $value, $replaced);
        }

        return $replaced;
    }

    /**
     * Build template variables for a registration document.
     */
    private function buildRegistrationDocumentVariables($event, $member, $guardian): array {
        $event_name = isset($event->title) ? (string) $event->title : '';
        $event_type = isset($event->event_type) ? (string) $event->event_type : '';
        $event_status = isset($event->statut) ? (string) $event->statut : '';
        $event_price = isset($event->prix) ? (string) $event->prix : '0';

        $event_url = '';
        if (!empty($event->article_id)) {
            $permalink = get_permalink((int) $event->article_id);
            if (is_string($permalink) && $permalink !== '') {
                $event_url = $permalink;
            }
        }
        $event_page_url = apply_filters('mj_member_event_permalink', '', $event);
        if (is_string($event_page_url) && $event_page_url !== '') {
            $event_url = $event_page_url;
        }

        $event_location = '';
        $event_location_address = '';
        if (!empty($event->location_name)) {
            $event_location = (string) $event->location_name;
        } elseif (!empty($event->location_id)) {
            $location = MjEventLocations::find((int) $event->location_id);
            if ($location) {
                $event_location = isset($location->name) ? (string) $location->name : '';
                $event_location_address = isset($location->address) ? (string) $location->address : '';
            }
        }

        $member_first_name = isset($member->first_name) ? (string) $member->first_name : '';
        $member_last_name = isset($member->last_name) ? (string) $member->last_name : '';
        $member_name = trim($member_first_name . ' ' . $member_last_name);
        $member_address_line = isset($member->address) ? (string) $member->address : '';
        $member_postal_code = isset($member->postal_code) ? (string) $member->postal_code : '';
        $member_city = isset($member->city) ? (string) $member->city : '';
        $member_address = trim($member_address_line . ($member_postal_code !== '' || $member_city !== '' ? ', ' : '') . trim($member_postal_code . ' ' . $member_city));

        $guardian_first_name = $guardian ? (string) ($guardian->first_name ?? '') : '';
        $guardian_last_name = $guardian ? (string) ($guardian->last_name ?? '') : '';
        $guardian_name = trim($guardian_first_name . ' ' . $guardian_last_name);
        $guardian_address_line = $guardian ? (string) ($guardian->address ?? '') : '';
        $guardian_postal_code = $guardian ? (string) ($guardian->postal_code ?? '') : '';
        $guardian_city = $guardian ? (string) ($guardian->city ?? '') : '';
        $guardian_address = trim($guardian_address_line . ($guardian_postal_code !== '' || $guardian_city !== '' ? ', ' : '') . trim($guardian_postal_code . ' ' . $guardian_city));

        if ($guardian_name === '') {
            $guardian_first_name = $member_first_name;
            $guardian_last_name = $member_last_name;
            $guardian_name = $member_name;
            $guardian_address_line = $member_address_line;
            $guardian_postal_code = $member_postal_code;
            $guardian_city = $member_city;
            $guardian_address = $member_address;
        }

        return array(
            'event_name' => $event_name,
            'event_type' => $event_type,
            'event_status' => $event_status,
            'event_date_start' => $this->formatDate(isset($event->date_debut) ? (string) $event->date_debut : '', true),
            'event_date_end' => $this->formatDate(isset($event->date_fin) ? (string) $event->date_fin : '', true),
            'event_date_deadline' => $this->formatDate(isset($event->date_fin_inscription) ? (string) $event->date_fin_inscription : '', true),
            'event_price' => $event_price . ' €',
            'event_url' => $event_url,
            'event_location' => $event_location,
            'event_location_address' => $event_location_address,
            'event_age_min' => isset($event->age_min) ? (string) $event->age_min : '',
            'event_age_max' => isset($event->age_max) ? (string) $event->age_max : '',
            'event_capacity' => isset($event->capacity) ? (string) $event->capacity : '',
            'member_name' => $member_name,
            'member_first_name' => $member_first_name,
            'member_last_name' => $member_last_name,
            'member_email' => isset($member->email) ? (string) $member->email : '',
            'member_phone' => isset($member->phone) ? (string) $member->phone : '',
            'member_birth_date' => $this->formatDate(isset($member->birth_date) ? (string) $member->birth_date : '', false),
            'member_address' => $member_address,
            'member_address_line' => $member_address_line,
            'member_postal_code' => $member_postal_code,
            'member_city' => $member_city,
            'guardian_name' => $guardian_name,
            'guardian_first_name' => $guardian_first_name,
            'guardian_last_name' => $guardian_last_name,
            'guardian_email' => $guardian ? (string) ($guardian->email ?? '') : (string) ($member->email ?? ''),
            'guardian_phone' => $guardian ? (string) ($guardian->phone ?? '') : (string) ($member->phone ?? ''),
            'guardian_address' => $guardian_address,
            'guardian_address_line' => $guardian_address_line,
            'guardian_postal_code' => $guardian_postal_code,
            'guardian_city' => $guardian_city,
            'site_name' => (string) get_bloginfo('name'),
            'site_url' => (string) home_url('/'),
            'current_date' => date_i18n('d/m/Y'),
            'current_year' => date_i18n('Y'),
        );
    }

    /**
     * Build a PDF file content from registration contract HTML fragments.
     *
     * @return array<string,string>|\WP_Error
     */
    private function buildRegistrationContractPdf(string $header_html, string $content_html, string $footer_html, string $event_title, string $member_name) {
        $render_errors = array();

        $dompdf_result = $this->buildRegistrationContractPdfWithDompdf($header_html, $content_html, $footer_html, $event_title, $member_name);
        if (is_array($dompdf_result)) {
            $dompdf_result['renderer'] = 'dompdf';
            return $dompdf_result;
        }
        if (is_wp_error($dompdf_result)) {
            error_log('[MjRegMgr] Dompdf render failed: ' . $dompdf_result->get_error_message());
            $render_errors[] = 'Dompdf: ' . $dompdf_result->get_error_message();
        }

        // Prefer mPDF fallback to keep rich HTML when Dompdf is unavailable.
        $allow_mpdf_fallback = (bool) apply_filters('mj_member_regdoc_allow_mpdf_fallback', true);
        if ($allow_mpdf_fallback) {
            $mpdf_result = $this->buildRegistrationContractPdfWithMpdf($header_html, $content_html, $footer_html, $event_title, $member_name);
            if (is_array($mpdf_result)) {
                $mpdf_result['renderer'] = 'mpdf';
                return $mpdf_result;
            }
            if (is_wp_error($mpdf_result)) {
                error_log('[MjRegMgr] mPDF render failed: ' . $mpdf_result->get_error_message());
                $render_errors[] = 'mPDF: ' . $mpdf_result->get_error_message();
            }
        }

        // Keep FPDF fallback opt-in: default is HTML-first rendering only.
        $allow_fpdf_fallback = (bool) apply_filters('mj_member_regdoc_allow_fpdf_fallback', false);
        if (!$allow_fpdf_fallback) {
            $suffix = !empty($render_errors) ? ' ' . implode(' | ', $render_errors) : '';
            return new \WP_Error(
                'mj_regmgr_contract_pdf_html_renderer_unavailable',
                __('Impossible de rendre le HTML du contrat avec les moteurs disponibles.', 'mj-member') . $suffix
            );
        }

        error_log('[MjRegMgr] Falling back to FPDF for contract PDF rendering.');

        if (!defined('FPDF_FONTPATH')) {
            define('FPDF_FONTPATH', Config::path() . 'includes/vendor/font/');
        }

        if (!class_exists('FPDF')) {
            require_once Config::path() . 'includes/vendor/fpdf.php';
        }

        if (!class_exists('FPDF')) {
            return new \WP_Error('mj_regmgr_contract_pdf_lib_missing', __('La bibliothèque PDF est introuvable.', 'mj-member'));
        }

        $pdf = new \FPDF('P', 'mm', 'A4');
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->AddPage();
        $pdf->SetFont('Arial', '', 11);

        $pdf->SetFont('Arial', '', 10);
        $header_rendered = $this->renderPdfHtmlSection($pdf, $header_html, 5.0);
        if ($header_rendered) {
            $pdf->Ln(2);
            $y = $pdf->GetY();
            $pdf->Line(10, $y, 200, $y);
            $pdf->Ln(4);
        }

        $content_rendered = $this->renderPdfHtmlSection($pdf, $content_html, 6.0);
        if (!$content_rendered) {
            return new \WP_Error('mj_regmgr_contract_pdf_empty', __('Le contrat est vide.', 'mj-member'));
        }

        if (trim($footer_html) !== '') {
            $pdf->Ln(4);
            $y = $pdf->GetY();
            $pdf->Line(10, $y, 200, $y);
            $pdf->Ln(3);
            $pdf->SetFont('Arial', '', 9);
            $this->renderPdfHtmlSection($pdf, $footer_html, 4.5);
        }
        $pdf->SetFont('Arial', '', 11);

        $content = $pdf->Output('S');
        if (!is_string($content) || $content === '') {
            return new \WP_Error('mj_regmgr_contract_pdf_failed', __('Impossible de générer le PDF du contrat.', 'mj-member'));
        }

        $event_part = $this->buildContractFilenamePart($event_title !== '' ? $event_title : 'evenement', 36);
        $member_part = $this->buildContractFilenamePart($member_name !== '' ? $member_name : 'membre', 26);
        $date_part = date_i18n('Ymd');

        $filename_base = trim($event_part . '-' . $member_part . '-' . $date_part, '-');
        if ($filename_base === '') {
            $filename_base = 'contrat-inscription-' . $date_part;
        }

        return array(
            'filename' => $filename_base . '.pdf',
            'content' => $content,
            'renderer' => 'fpdf',
        );
    }

    /**
     * Build contract PDF using mPDF when available for robust HTML/CSS rendering.
     *
     * @return array{filename:string,content:string}|\WP_Error|null
     */
    private function buildRegistrationContractPdfWithMpdf(string $header_html, string $content_html, string $footer_html, string $event_title, string $member_name) {
        if (!$this->ensureMpdfLoaded()) {
            return null;
        }

        if (!class_exists('Mpdf\\Mpdf')) {
            return new \WP_Error('mj_regmgr_contract_pdf_mpdf_missing', __('La bibliothèque mPDF est introuvable.', 'mj-member'));
        }

        $header_html = $this->normalizeHtmlFragmentForPdf($header_html);
        $body_html = $this->normalizeHtmlFragmentForPdf($content_html);
        $footer_html = $this->normalizeHtmlFragmentForPdf($footer_html);

        if ($body_html === '') {
            return null;
        }

        $base_href = esc_url(home_url('/'));

        $composed_html = '<!doctype html><html><head><meta charset="utf-8">'
            . '<base href="' . $base_href . '">'
            . '<style>'
            . '@page{size:A4;margin:14mm 12mm 14mm 12mm;}'
            . 'body{font-family:dejavusans,Arial,sans-serif;font-size:12px;line-height:1.45;color:#111;margin:0;padding:0;}'
            . '.mj-regdoc{margin:0;padding:0;}'
            . '.mj-regdoc-header{font-size:11px;color:#333;margin-bottom:12px;padding-bottom:8px;border-bottom:1px solid #ddd;}'
            . '.mj-regdoc-content{font-size:12px;}'
            . '.mj-regdoc-footer{font-size:10px;color:#444;margin-top:14px;padding-top:8px;border-top:1px solid #ddd;}'
            . '.mj-regdoc img{max-width:100%;height:auto;}'
            . '.mj-regdoc table{width:100%;border-collapse:collapse;margin:8px 0;}'
            . '.mj-regdoc th,.mj-regdoc td{border:1px solid #d1d5db;padding:6px;vertical-align:top;text-align:left;}'
            . '.mj-regdoc th{background:#f3f4f6;font-weight:700;}'
            . '.mj-regdoc .regdoc-page{page-break-before:auto !important;page-break-after:auto !important;break-before:auto !important;break-after:auto !important;}'
            . '</style></head><body><div class="mj-regdoc">'
            . '<div class="mj-regdoc-header">' . $header_html . '</div>'
            . '<div class="mj-regdoc-content">' . $body_html . '</div>'
            . '<div class="mj-regdoc-footer">' . $footer_html . '</div>'
            . '</div></body></html>';

        try {
            $temp_dir = wp_normalize_path(get_temp_dir());
            if (!is_string($temp_dir) || $temp_dir === '') {
                $temp_dir = wp_normalize_path(sys_get_temp_dir());
            }

            $mpdf = new \Mpdf\Mpdf(array(
                'mode' => 'utf-8',
                'format' => 'A4',
                'tempDir' => $temp_dir,
            ));
            $mpdf->showImageErrors = false;
            $mpdf->WriteHTML($composed_html);

            // Emergency guard: reject pathological pagination explosions.
            $page_count = isset($mpdf->page) ? (int) $mpdf->page : 0;
            if ($page_count > 40) {
                throw new \RuntimeException('Pagination runaway detected in mPDF (' . $page_count . ' pages).');
            }

            $content = $mpdf->Output('', 'S');
        } catch (\Throwable $e) {
            return new \WP_Error('mj_regmgr_contract_pdf_mpdf_failed', __('Le rendu HTML en PDF a échoué (mPDF).', 'mj-member') . ' ' . $e->getMessage());
        }

        if (!is_string($content) || $content === '') {
            return new \WP_Error('mj_regmgr_contract_pdf_mpdf_empty', __('Le rendu mPDF a retourné un PDF vide.', 'mj-member'));
        }

        $event_part = $this->buildContractFilenamePart($event_title !== '' ? $event_title : 'evenement', 36);
        $member_part = $this->buildContractFilenamePart($member_name !== '' ? $member_name : 'membre', 26);
        $date_part = date_i18n('Ymd');

        $filename_base = trim($event_part . '-' . $member_part . '-' . $date_part, '-');
        if ($filename_base === '') {
            $filename_base = 'contrat-inscription-' . $date_part;
        }

        return array(
            'filename' => $filename_base . '.pdf',
            'content' => $content,
        );
    }

    /**
     * Emergency simplification for mPDF runaway pagination cases.
     */
    private function simplifyHtmlForMpdfSafeMode(string $html): string {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        // Last-resort mode: strip to plain text to prevent pathological pagination loops.
        $text = html_entity_decode(wp_strip_all_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = (string) preg_replace('/\r\n|\r/', "\n", $text);
        $text = (string) preg_replace('/[ \t\x{00A0}]+/u', ' ', $text);
        $text = (string) preg_replace('/\n{3,}/', "\n\n", $text);
        $text = trim($text);

        if ($text === '') {
            return '';
        }

        if (function_exists('mb_substr')) {
            $text = mb_substr($text, 0, 20000);
        } else {
            $text = substr($text, 0, 20000);
        }

        return '<p>' . nl2br(esc_html($text)) . '</p>';
    }

    /**
     * Build contract PDF using Dompdf when available for robust HTML/CSS rendering.
     *
     * @return array{filename:string,content:string}|\WP_Error|null
     */
    private function buildRegistrationContractPdfWithDompdf(string $header_html, string $content_html, string $footer_html, string $event_title, string $member_name) {
        if (!$this->ensureDompdfLoaded()) {
            return null;
        }

        if (!class_exists('Dompdf\\Dompdf') || !class_exists('Dompdf\\Options')) {
            return new \WP_Error('mj_regmgr_contract_pdf_dompdf_missing', __('La bibliothèque Dompdf est introuvable.', 'mj-member'));
        }

        if (!class_exists('Masterminds\\HTML5')) {
            return new \WP_Error(
                'mj_regmgr_contract_pdf_dompdf_html5_missing',
                __('Dompdf est installé mais la dépendance Masterminds\\HTML5 est manquante.', 'mj-member')
            );
        }

        $header_html = $this->normalizeHtmlFragmentForPdf($header_html);
        $body_html = $this->normalizeHtmlFragmentForPdf($content_html);
        $footer_html = $this->normalizeHtmlFragmentForPdf($footer_html);

        if ($body_html === '') {
            return null;
        }

        $base_href = esc_url(home_url('/'));

        $composed_html = '<!doctype html><html><head><meta charset="utf-8">'
            . '<base href="' . $base_href . '">'
            . '<style>'
            . 'body{font-family:DejaVu Sans,Arial,sans-serif;font-size:12px;line-height:1.45;color:#111;margin:0;padding:0;}'
            . '.mj-regdoc{padding:24px 28px;}'
            . '.mj-regdoc-header{font-size:11px;color:#333;margin-bottom:12px;padding-bottom:8px;border-bottom:1px solid #ddd;}'
            . '.mj-regdoc-content{font-size:12px;}'
            . '.mj-regdoc-footer{font-size:10px;color:#444;margin-top:14px;padding-top:8px;border-top:1px solid #ddd;}'
            . '.mj-regdoc img{max-width:100%;height:auto;}'
            . '.mj-regdoc table{width:100%;border-collapse:collapse;margin:8px 0;}'
            . '.mj-regdoc th,.mj-regdoc td{border:1px solid #d1d5db;padding:6px;vertical-align:top;text-align:left;}'
            . '.mj-regdoc th{background:#f3f4f6;font-weight:700;}'
            . '</style></head><body><div class="mj-regdoc">'
            . '<div class="mj-regdoc-header">' . $header_html . '</div>'
            . '<div class="mj-regdoc-content">' . $body_html . '</div>'
            . '<div class="mj-regdoc-footer">' . $footer_html . '</div>'
            . '</div></body></html>';

        try {
            $options = new \Dompdf\Options();
            $options->set('isRemoteEnabled', true);
            // Some production installs miss Masterminds\\HTML5 dependency.
            // Disable HTML5 parser to use Dompdf internal parser instead.
            $options->set('isHtml5ParserEnabled', false);
            $options->set('isPhpEnabled', false);
            $options->set('isFontSubsettingEnabled', true);
            $options->set('defaultPaperSize', 'a4');
            $options->set('defaultFont', 'DejaVu Sans');
            $options->set('chroot', wp_normalize_path(ABSPATH));
            $options->set('tempDir', wp_normalize_path(get_temp_dir()));

            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->loadHtml($composed_html, 'UTF-8');
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            $content = $dompdf->output();
        } catch (\Throwable $e) {
            error_log('[MJ Member] Dompdf render error: ' . $e->getMessage());
            return new \WP_Error('mj_regmgr_contract_pdf_dompdf_failed', __('Le rendu HTML en PDF a échoué (Dompdf).', 'mj-member'));
        }

        if (!is_string($content) || $content === '') {
            return new \WP_Error('mj_regmgr_contract_pdf_dompdf_empty', __('Le rendu Dompdf a retourné un PDF vide.', 'mj-member'));
        }

        $event_part = $this->buildContractFilenamePart($event_title !== '' ? $event_title : 'evenement', 36);
        $member_part = $this->buildContractFilenamePart($member_name !== '' ? $member_name : 'membre', 26);
        $date_part = date_i18n('Ymd');

        $filename_base = trim($event_part . '-' . $member_part . '-' . $date_part, '-');
        if ($filename_base === '') {
            $filename_base = 'contrat-inscription-' . $date_part;
        }

        return array(
            'filename' => $filename_base . '.pdf',
            'content' => $content,
        );
    }

    /**
     * Normalize HTML fragment for Dompdf rendering.
     */
    private function normalizeHtmlFragmentForPdf(string $html): string {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        // Some editors/store paths keep HTML entity-encoded tags (&lt;table&gt;...)
        // which must be decoded before Dompdf can render them as real elements.
        $decoded = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($decoded !== '') {
            $has_real_tags = preg_match('/<\s*(table|tr|td|th|p|div|span|strong|img|h[1-6])\b/i', $html) === 1;
            $has_encoded_tags = preg_match('/&lt;\s*(table|tr|td|th|p|div|span|strong|img|h[1-6])\b/i', $html) === 1;
            if ($has_encoded_tags || !$has_real_tags) {
                $html = $decoded;
            }
        }

        // If a full HTML document is provided, keep only <body> content.
        if (preg_match('/<body\b[^>]*>(.*?)<\/body>/is', $html, $match)) {
            $html = isset($match[1]) ? (string) $match[1] : $html;
        }

        $html = $this->canonicalizeHtmlFragmentWithDomDocument($html);

        $html = $this->sanitizeHtmlForPredictablePdfLayout($html);
        $html = $this->normalizeTableMarkupForPdf($html);

        // Rebalance malformed markup emitted by rich-text editors.
        if (function_exists('force_balance_tags')) {
            $html = force_balance_tags($html);
        }

        $html = $this->neutralizeAggressivePageBreaksForPdf($html);

        $html = $this->normalizeImgSourcesForPdf($html);

        return $html;
    }

    /**
     * Normalize malformed fragments through DOM parsing to rebalance broken HTML.
     */
    private function canonicalizeHtmlFragmentWithDomDocument(string $html): string {
        if (trim($html) === '' || !class_exists('DOMDocument') || !class_exists('DOMXPath')) {
            return $html;
        }

        $wrapped = '<!doctype html><html><body><div id="mj-regdoc-root">' . $html . '</div></body></html>';
        $dom = new \DOMDocument('1.0', 'UTF-8');

        $previous_use_internal_errors = libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML(
            '<?xml encoding="utf-8" ?>' . $wrapped,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous_use_internal_errors);

        if (!$loaded) {
            return $html;
        }

        $xpath = new \DOMXPath($dom);
        $root_nodes = $xpath->query('//*[@id="mj-regdoc-root"]');
        if (!$root_nodes || $root_nodes->length < 1) {
            return $html;
        }

        $root = $root_nodes->item(0);
        if (!$root) {
            return $html;
        }

        $normalized = '';
        foreach ($root->childNodes as $child) {
            $normalized .= (string) $dom->saveHTML($child);
        }

        $normalized = trim($normalized);
        return $normalized !== '' ? $normalized : $html;
    }

    /**
     * Normalize malformed table markup that can crash HTML-to-PDF engines.
     */
    private function normalizeTableMarkupForPdf(string $html): string {
        if (trim($html) === '') {
            return '';
        }

        $has_table = preg_match('/<\s*table\b/i', $html) === 1;
        $has_cells = preg_match('/<\s*\/?\s*(?:tr|td|th)\b/i', $html) === 1;

        if ($has_cells && !$has_table) {
            // Convert orphan table tags into plain spacing when no table container exists.
            $html = (string) preg_replace('/<\s*\/?\s*(?:tr|td|th|tbody|thead|tfoot)\b[^>]*>/i', ' ', $html);
        }

        // If table markup is structurally inconsistent, flatten it to block content
        // to avoid Dompdf/mPDF crashes on orphan td/tr elements.
        if ($has_table && $this->hasBrokenTableMarkup($html)) {
            $html = (string) preg_replace('/<\s*\/?\s*(?:table|tbody|thead|tfoot|tr|td|th|colgroup|col|caption)\b[^>]*>/i', ' ', $html);
        }

        // Remove empty table shells.
        $html = (string) preg_replace('/<table\b[^>]*>\s*<\/table>/i', '', $html);

        return $html;
    }

    /**
     * Heuristic detection of malformed table markup.
     */
    private function hasBrokenTableMarkup(string $html): bool {
        $open_tables = preg_match_all('/<\s*table\b/i', $html);
        $close_tables = preg_match_all('/<\s*\/\s*table\s*>/i', $html);
        if ($open_tables !== $close_tables) {
            return true;
        }

        $open_rows = preg_match_all('/<\s*tr\b/i', $html);
        $close_rows = preg_match_all('/<\s*\/\s*tr\s*>/i', $html);
        if ($open_rows !== $close_rows) {
            return true;
        }

        $open_cells = preg_match_all('/<\s*(?:td|th)\b/i', $html);
        $close_cells = preg_match_all('/<\s*\/\s*(?:td|th)\s*>/i', $html);

        return $open_cells !== $close_cells;
    }

    /**
     * Remove risky constructs from rich HTML that can explode page count in PDF engines.
     */
    private function sanitizeHtmlForPredictablePdfLayout(string $html): string {
        if (trim($html) === '') {
            return '';
        }

        // Remove control characters that may be interpreted as hard page breaks by PDF engines (notably form-feed \f).
        $html = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $html);
        $html = str_replace(array("\u{2028}", "\u{2029}"), "\n", $html);

        // mPDF custom explicit page breaks.
        $html = (string) preg_replace('/<\s*pagebreak\b[^>]*>/i', '', $html);

        // Remove mPDF-specific control tags that can create runaway pagination.
        $html = (string) preg_replace('/<\/?\s*(?:htmlpageheader|sethtmlpageheader|htmlpagefooter|sethtmlpagefooter|tocpagebreak|columns|columnbreak|newcolumn)\b[^>]*>/i', '', $html);

        // Remove embedded style/script blocks from user content; keep predictable base CSS in renderer.
        $html = (string) preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html);
        $html = (string) preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);

        // Remove huge height declarations that can generate thousands of blank pages.
        $huge_height_pattern = '/(?:^|;)\s*(?:height|min-height)\s*:\s*([0-9]{4,})(?:\s*(?:px|pt|mm|cm|in|pc))?/i';
        $html = (string) preg_replace_callback('/\bstyle\s*=\s*("|\')(.*?)\1/is', function (array $matches) use ($huge_height_pattern): string {
            $quote = isset($matches[1]) ? (string) $matches[1] : '"';
            $style = isset($matches[2]) ? (string) $matches[2] : '';

            $clean = preg_replace($huge_height_pattern, '', $style);
            $clean = is_string($clean) ? $clean : $style;
            $clean = preg_replace('/;\s*;/', ';', $clean);
            $clean = trim((string) $clean, " \t\n\r\0\x0B;");

            if ($clean === '') {
                return '';
            }

            return 'style=' . $quote . $clean . $quote;
        }, $html);

        // Keep semantic HTML but drop attributes that commonly destabilize PDF layout.
        $allowed = array(
            'h1' => array(),
            'h2' => array(),
            'h3' => array(),
            'h4' => array(),
            'h5' => array(),
            'h6' => array(),
            'p' => array(),
            'br' => array(),
            'strong' => array(),
            'b' => array(),
            'em' => array(),
            'i' => array(),
            'u' => array(),
            'ul' => array(),
            'ol' => array(),
            'li' => array(),
            'table' => array(),
            'thead' => array(),
            'tbody' => array(),
            'tfoot' => array(),
            'tr' => array(),
            'th' => array('colspan' => true, 'rowspan' => true),
            'td' => array('colspan' => true, 'rowspan' => true),
            'a' => array('href' => true, 'target' => true),
            'img' => array('src' => true, 'alt' => true, 'width' => true, 'height' => true),
            'blockquote' => array(),
            'hr' => array(),
        );
        $html = wp_kses($html, $allowed);

        // Collapse empty blocks and repeated line breaks that can create thousands of blank PDF pages.
        $html = (string) preg_replace('/<p>\s*(?:&nbsp;|\xC2\xA0|<br\s*\/?\s*>|\s)*<\/p>/iu', '', $html);
        $html = (string) preg_replace('/(?:<br\s*\/?\s*>\s*){3,}/i', '<br><br>', $html);
        $html = (string) preg_replace('/(?:<p>\s*<\/p>\s*){2,}/i', '', $html);

        // Reduce oversized whitespace in text nodes.
        $html = str_replace('&nbsp;', ' ', $html);
        $html = (string) preg_replace('/[ \t\x{00A0}]{3,}/u', '  ', $html);
        $html = (string) preg_replace('/(?:\r?\n\s*){4,}/', "\n\n\n", $html);

        // Heuristic safety mode: if content still contains extreme amounts of line-break wrappers,
        // simplify structure to avoid thousands of PDF pages with near-empty lines.
        $br_count = preg_match_all('/<br\b/i', $html, $matches_br);
        $p_count = preg_match_all('/<p\b/i', $html, $matches_p);
        $h_count = preg_match_all('/<h[1-6]\b/i', $html, $matches_h);

        $br_count = is_int($br_count) ? $br_count : 0;
        $p_count = is_int($p_count) ? $p_count : 0;
        $h_count = is_int($h_count) ? $h_count : 0;

        if ($br_count > 300 || $p_count > 800) {
            // Flatten pathological wrapping while preserving semantic tags.
            $html = (string) preg_replace('/<br\s*\/?\s*>/i', ' ', $html);
            $html = (string) preg_replace('/<\/p>\s*<p\b[^>]*>/i', ' ', $html);
            $html = str_replace(array('<p>', '</p>'), ' ', $html);
            $html = (string) preg_replace('/\s{2,}/', ' ', $html);
            $html = trim($html);

            error_log('[MjRegMgr] PDF HTML safety mode enabled: br=' . $br_count . ' p=' . $p_count . ' h=' . $h_count);
        }

        // Final trim keeps only meaningful content boundaries.
        $html = trim($html);

        return $html;
    }

    /**
     * Neutralize inline/style-based forced page breaks that can generate hundreds of blank pages.
     */
    private function neutralizeAggressivePageBreaksForPdf(string $html): string {
        if (trim($html) === '') {
            return '';
        }

        $style_decl_pattern = '/(?:^|;)\s*(?:page-break-(?:before|after|inside)|break-(?:before|after|inside))\s*:\s*[^;]+/i';

        // Clean forced breaks in inline style attributes.
        $html = (string) preg_replace_callback('/\bstyle\s*=\s*("|\')(.*?)\1/is', function (array $matches) use ($style_decl_pattern): string {
            $quote = isset($matches[1]) ? (string) $matches[1] : '"';
            $style = isset($matches[2]) ? (string) $matches[2] : '';

            $clean = preg_replace($style_decl_pattern, '', $style);
            $clean = is_string($clean) ? $clean : $style;
            $clean = preg_replace('/;\s*;/', ';', $clean);
            $clean = trim((string) $clean, " \t\n\r\0\x0B;");

            if ($clean === '') {
                return '';
            }

            return 'style=' . $quote . $clean . $quote;
        }, $html);

        // Clean forced breaks in <style> blocks.
        $html = (string) preg_replace_callback('/<style\b[^>]*>(.*?)<\/style>/is', function (array $matches) use ($style_decl_pattern): string {
            $css = isset($matches[1]) ? (string) $matches[1] : '';
            $clean_css = preg_replace($style_decl_pattern, '', $css);
            $clean_css = is_string($clean_css) ? $clean_css : $css;
            return '<style>' . $clean_css . '</style>';
        }, $html);

        return $html;
    }

    /**
     * Replace lazy image attributes by src when needed.
     */
    private function normalizeImgSourcesForPdf(string $html): string {
        return (string) preg_replace_callback('/<img\b[^>]*>/i', function (array $matches): string {
            $tag = isset($matches[0]) ? (string) $matches[0] : '';
            if ($tag === '') {
                return $tag;
            }

            $src = $this->extractHtmlTagAttribute($tag, 'src');
            if ($src !== '' && strpos($src, 'data:image/svg+xml') !== 0) {
                return $tag;
            }

            $candidate = $this->extractHtmlTagAttribute($tag, 'data-src');
            if ($candidate === '') {
                $candidate = $this->extractHtmlTagAttribute($tag, 'data-lazy-src');
            }
            if ($candidate === '') {
                $candidate = $this->extractHtmlTagAttribute($tag, 'data-original');
            }
            if ($candidate === '') {
                $srcset = $this->extractHtmlTagAttribute($tag, 'srcset');
                if ($srcset !== '') {
                    $candidate = $this->extractFirstSrcFromSrcset($srcset);
                }
            }

            if ($candidate === '') {
                return $tag;
            }

            if ($src !== '') {
                return preg_replace('/\bsrc\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', 'src="' . esc_attr($candidate) . '"', $tag, 1) ?: $tag;
            }

            return rtrim($tag, '>') . ' src="' . esc_attr($candidate) . '">';
        }, $html);
    }

    /**
     * Build a list of Composer autoload candidates for this plugin environment.
     *
     * @return array<int,string>
     */
    private function getPdfComposerAutoloadCandidates(): array {
        $candidates = array(
            Config::path() . 'vendor/autoload.php',
            dirname(__DIR__, 4) . '/vendor/autoload.php',
            trailingslashit(ABSPATH) . 'wp-content/plugins/mj-member/vendor/autoload.php',
            trailingslashit(ABSPATH) . 'vendor/autoload.php',
        );

        if (defined('WP_PLUGIN_DIR') && is_string(WP_PLUGIN_DIR) && WP_PLUGIN_DIR !== '') {
            $candidates[] = trailingslashit(WP_PLUGIN_DIR) . 'mj-member/vendor/autoload.php';
        }

        $normalized = array();
        foreach ($candidates as $path) {
            if (!is_string($path) || $path === '') {
                continue;
            }
            $normalized[] = wp_normalize_path($path);
        }

        return array_values(array_unique($normalized));
    }

    /**
     * Try to load Dompdf from plugin or global Composer autoloaders.
     */
    private function ensureMpdfLoaded(): bool {
        if (class_exists('Mpdf\\Mpdf')) {
            return true;
        }

        $autoload_candidates = $this->getPdfComposerAutoloadCandidates();
        $checked_paths = array();

        foreach ($autoload_candidates as $autoload_path) {
            if (!is_string($autoload_path) || $autoload_path === '' || !is_readable($autoload_path)) {
                continue;
            }
            $checked_paths[] = $autoload_path;
            require_once $autoload_path;
            if (class_exists('Mpdf\\Mpdf')) {
                return true;
            }
        }

        if (!empty($checked_paths)) {
            error_log('[MjRegMgr] mPDF unavailable after loading candidates: ' . implode(' | ', $checked_paths));
        } else {
            error_log('[MjRegMgr] mPDF autoload candidates not readable.');
        }

        return false;
    }

    /**
     * Try to load Dompdf from plugin or global Composer autoloaders.
     */
    private function ensureDompdfLoaded(): bool {
        if (class_exists('Dompdf\\Dompdf') && class_exists('Dompdf\\Options')) {
            return true;
        }

        $autoload_candidates = array_merge(
            $this->getPdfComposerAutoloadCandidates(),
            array(
            Config::path() . 'vendor/dompdf/dompdf/autoload.inc.php',
            trailingslashit(ABSPATH) . 'wp-content/plugins/mj-member/vendor/dompdf/dompdf/autoload.inc.php',
            )
        );

        $checked_paths = array();

        foreach ($autoload_candidates as $autoload_path) {
            if (!is_string($autoload_path) || $autoload_path === '' || !is_readable($autoload_path)) {
                continue;
            }
            $checked_paths[] = $autoload_path;
            require_once $autoload_path;
            if (class_exists('Dompdf\\Dompdf') && class_exists('Dompdf\\Options')) {
                return true;
            }
        }

        if (!empty($checked_paths)) {
            error_log('[MjRegMgr] Dompdf unavailable after loading candidates: ' . implode(' | ', $checked_paths));
        } else {
            error_log('[MjRegMgr] Dompdf autoload candidates not readable.');
        }

        return false;
    }

    /**
     * Convert UTF-8 text for FPDF.
     */
    private function toPdfText(string $text): string {
        if ($text === '') {
            return '';
        }

        $converted = @iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $text);
        if ($converted === false || $converted === null) {
            return $text;
        }

        return $converted;
    }

    /**
     * Convert a small HTML fragment into printable PDF lines.
     * Keeps paragraph structure to preserve header/content/footer readability.
     *
     * @return array<int,string>
     */
    private function extractPdfLinesFromHtml(string $html): array {
        if (trim($html) === '') {
            return array();
        }

        $normalized = $html;

        // Keep non-textual images traceable in PDF output.
        $normalized = preg_replace_callback(
            '/<img[^>]*alt=["\']?([^"\'>]*)["\']?[^>]*>/i',
            function (array $matches): string {
                $alt = isset($matches[1]) ? trim((string) $matches[1]) : '';
                if ($alt === '') {
                    $alt = __('Image', 'mj-member');
                }
                return "\n[" . $alt . "]\n";
            },
            $normalized
        );

        $normalized = preg_replace('/<br\s*\/?\s*>/i', "\n", $normalized);
        // Keep HTML tables readable in PDF without changing global rendering flow.
        $normalized = preg_replace('/<\/t[dh]\s*>/i', ' | ', $normalized);
        $normalized = preg_replace('/<t[dh]\b[^>]*>/i', '', $normalized);
        $normalized = preg_replace('/<\/tr\s*>/i', "\n", $normalized);
        $normalized = preg_replace('/<tr\b[^>]*>/i', '', $normalized);
        $normalized = preg_replace('/<\/?(?:table|thead|tbody|tfoot)\b[^>]*>/i', "\n", $normalized);
        $normalized = preg_replace('/<\/(p|div|h1|h2|h3|h4|h5|h6|li|tr|table|section|article)\s*>/i', "\n", $normalized);
        $normalized = preg_replace('/<(ul|ol)\b[^>]*>/i', "\n", $normalized);
        $normalized = preg_replace('/<li\b[^>]*>/i', "\n- ", $normalized);

        $text = html_entity_decode(wp_strip_all_tags($normalized), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/\r\n|\r/", "\n", $text);
        $text = preg_replace("/\t+/", ' ', (string) $text);
        $text = preg_replace("/\n{3,}/", "\n\n", (string) $text);

        $chunks = preg_split('/\n/', (string) $text);
        if (!is_array($chunks)) {
            return array();
        }

        $lines = array();
        foreach ($chunks as $chunk) {
            $line = trim(preg_replace('/\s{2,}/', ' ', (string) $chunk));
            if ($line === '') {
                // Preserve readable spacing between paragraphs.
                if (!empty($lines) && end($lines) !== '') {
                    $lines[] = '';
                }
                continue;
            }
            $lines[] = $line;
        }

        // Remove trailing blank lines.
        while (!empty($lines) && end($lines) === '') {
            array_pop($lines);
        }

        return $lines;
    }

    /**
     * Render an HTML fragment into the PDF, including images.
     */
    private function renderPdfHtmlSection($pdf, string $html, float $line_height = 6.0): bool {
        $segments = $this->extractPdfHtmlSegments($html);
        if (empty($segments)) {
            return false;
        }

        $rendered = false;
        foreach ($segments as $segment) {
            $type = isset($segment['type']) ? (string) $segment['type'] : 'text';
            if ($type === 'image') {
                $src = isset($segment['src']) ? (string) $segment['src'] : '';
                $alt = isset($segment['alt']) ? (string) $segment['alt'] : '';
                $width_hint = isset($segment['widthHint']) && is_array($segment['widthHint']) ? $segment['widthHint'] : null;
                $height_hint = isset($segment['heightHint']) && is_array($segment['heightHint']) ? $segment['heightHint'] : null;
                $image_rendered = $this->renderPdfImageFromSrc($pdf, $src, $alt, $width_hint, $height_hint);
                if ($image_rendered) {
                    $rendered = true;
                } elseif ($alt !== '') {
                    $pdf->MultiCell(0, $line_height, $this->toPdfText('[' . $alt . ']'));
                    $rendered = true;
                }
                continue;
            }

            $text_html = isset($segment['html']) ? (string) $segment['html'] : '';
            $lines = $this->extractPdfLinesFromHtml($text_html);
            foreach ($lines as $line) {
                if (trim($line) === '') {
                    $pdf->Ln(max(2.0, $line_height - 2.0));
                    continue;
                }
                $pdf->MultiCell(0, $line_height, $this->toPdfText($line));
                $rendered = true;
            }
        }

        return $rendered;
    }

    /**
     * Split HTML into text and image segments in source order.
     *
    * @return array<int,array<string,mixed>>
     */
    private function extractPdfHtmlSegments(string $html): array {
        $segments = array();
        if (trim($html) === '') {
            return $segments;
        }

        if (!preg_match_all('/<img\b[^>]*>/i', $html, $matches, PREG_OFFSET_CAPTURE)) {
            return array(array('type' => 'text', 'html' => $html));
        }

        $offset = 0;
        foreach ($matches[0] as $match) {
            $tag = (string) ($match[0] ?? '');
            $pos = (int) ($match[1] ?? 0);

            if ($pos > $offset) {
                $before = substr($html, $offset, $pos - $offset);
                if ($before !== false && trim((string) $before) !== '') {
                    $segments[] = array('type' => 'text', 'html' => (string) $before);
                }
            }

            $alt = $this->extractHtmlTagAttribute($tag, 'alt');
            $style_attr = $this->extractHtmlTagAttribute($tag, 'style');
            $style_dimensions = $this->extractPdfImageStyleDimensions($style_attr);
            $width_hint = $style_dimensions['width'] ?? $this->parsePdfImageDimensionHint($this->extractHtmlTagAttribute($tag, 'width'));
            $height_hint = $style_dimensions['height'] ?? $this->parsePdfImageDimensionHint($this->extractHtmlTagAttribute($tag, 'height'));

            // Support lazy-load/image optimization attributes often used in editors.
            $src = $this->extractHtmlTagAttribute($tag, 'src');
            if ($src === '' || strpos($src, 'data:image/svg+xml') === 0) {
                $src = $this->extractHtmlTagAttribute($tag, 'data-src');
            }
            if ($src === '' || strpos($src, 'data:image/svg+xml') === 0) {
                $src = $this->extractHtmlTagAttribute($tag, 'data-lazy-src');
            }
            if ($src === '' || strpos($src, 'data:image/svg+xml') === 0) {
                $src = $this->extractHtmlTagAttribute($tag, 'data-original');
            }
            if ($src === '' || strpos($src, 'data:image/svg+xml') === 0) {
                $srcset = $this->extractHtmlTagAttribute($tag, 'srcset');
                if ($srcset !== '') {
                    $src = $this->extractFirstSrcFromSrcset($srcset);
                }
            }

            if ($src !== '') {
                $segments[] = array(
                    'type' => 'image',
                    'src' => html_entity_decode($src, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                    'alt' => html_entity_decode($alt, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                    'widthHint' => $width_hint,
                    'heightHint' => $height_hint,
                );
            }

            $offset = $pos + strlen($tag);
        }

        if ($offset < strlen($html)) {
            $after = substr($html, $offset);
            if ($after !== false && trim((string) $after) !== '') {
                $segments[] = array('type' => 'text', 'html' => (string) $after);
            }
        }

        return $segments;
    }

    /**
     * Extract a single attribute value from an HTML tag.
     */
    private function extractHtmlTagAttribute(string $tag, string $attr): string {
        $pattern = "/\\b" . preg_quote($attr, '/') . "\\s*=\\s*(?:\"([^\"]*)\"|'([^']*)'|([^\\s>]+))/i";
        if (!preg_match($pattern, $tag, $match)) {
            return '';
        }

        $value1 = isset($match[1]) ? (string) $match[1] : '';
        $value2 = isset($match[2]) ? (string) $match[2] : '';
        $value3 = isset($match[3]) ? (string) $match[3] : '';

        if ($value1 !== '') {
            return $value1;
        }
        if ($value2 !== '') {
            return $value2;
        }

        return $value3;
    }

    /**
     * Extract first usable URL from a srcset attribute value.
     */
    private function extractFirstSrcFromSrcset(string $srcset): string {
        $srcset = trim($srcset);
        if ($srcset === '') {
            return '';
        }

        $parts = explode(',', $srcset);
        foreach ($parts as $part) {
            $candidate = trim($part);
            if ($candidate === '') {
                continue;
            }

            // srcset item format: "url 300w" or "url 2x"
            $tokens = preg_split('/\s+/', $candidate);
            if (!is_array($tokens) || empty($tokens[0])) {
                continue;
            }

            $url = trim((string) $tokens[0]);
            if ($url !== '') {
                return $url;
            }
        }

        return '';
    }

    /**
     * Parse width/height declarations from inline style.
     *
     * @return array<string,array<string,mixed>>
     */
    private function extractPdfImageStyleDimensions(string $style): array {
        $dimensions = array();
        $style = trim($style);
        if ($style === '') {
            return $dimensions;
        }

        foreach (array('width', 'height') as $property) {
            $pattern = '/(?:^|;)\s*' . preg_quote($property, '/') . '\s*:\s*([^;]+)/i';
            if (!preg_match($pattern, $style, $match)) {
                continue;
            }

            $parsed = $this->parsePdfImageDimensionToken((string) ($match[1] ?? ''));
            if ($parsed !== null) {
                $dimensions[$property] = $parsed;
            }
        }

        return $dimensions;
    }

    /**
     * Parse an img width/height attribute or style token.
     *
     * @return array<string,mixed>|null
     */
    private function parsePdfImageDimensionHint(string $value): ?array {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return $this->parsePdfImageDimensionToken($value);
    }

    /**
     * Parse a single dimension token (e.g. 120, 120px, 80%).
     *
     * @return array<string,mixed>|null
     */
    private function parsePdfImageDimensionToken(string $value): ?array {
        $value = strtolower(trim($value));
        if ($value === '' || in_array($value, array('auto', 'initial', 'inherit', 'unset', 'none'), true)) {
            return null;
        }

        if (!preg_match('/^([0-9]+(?:\.[0-9]+)?)\s*(px|%)?$/i', $value, $match)) {
            return null;
        }

        $amount = (float) ($match[1] ?? 0.0);
        if ($amount <= 0) {
            return null;
        }

        $unit = isset($match[2]) && $match[2] !== '' ? strtolower((string) $match[2]) : 'px';

        return array(
            'value' => $amount,
            'unit' => $unit,
        );
    }

    /**
     * Convert parsed dimension hint to mm.
     */
    private function pdfImageHintToMm(?array $hint, float $base_mm): ?float {
        if (!is_array($hint)) {
            return null;
        }

        $value = isset($hint['value']) ? (float) $hint['value'] : 0.0;
        $unit = isset($hint['unit']) ? strtolower((string) $hint['unit']) : 'px';
        if ($value <= 0) {
            return null;
        }

        if ($unit === '%') {
            return ($base_mm * $value) / 100.0;
        }

        // Assume px when unit is omitted or explicitly set to px.
        return $value * 0.2645833333;
    }

    /**
     * Render an image from src URL or data URI into the PDF.
     */
    private function renderPdfImageFromSrc($pdf, string $src, string $alt = '', ?array $width_hint = null, ?array $height_hint = null): bool {
        $src = trim($src);
        if ($src === '') {
            return false;
        }

        $tmp_file = '';
        $cleanup = false;

        // 1) data:image/...;base64,...
        if (strpos($src, 'data:image/') === 0) {
            if (!preg_match('/^data:image\/(png|jpe?g|gif|webp);base64,(.+)$/i', $src, $m)) {
                return false;
            }
            $ext = strtolower((string) $m[1]);
            $raw = base64_decode((string) $m[2], true);
            if (!is_string($raw) || $raw === '') {
                return false;
            }
            $tmp = wp_tempnam('mj-regdoc-img');
            if (!is_string($tmp) || $tmp === '') {
                return false;
            }
            $tmp_file = $tmp . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);
            if (@file_put_contents($tmp_file, $raw) === false) {
                return false;
            }
            $cleanup = true;
        } else {
            $normalized = $src;
            if (strpos($normalized, '//') === 0) {
                $normalized = 'https:' . $normalized;
            } elseif (strpos($normalized, '/') === 0) {
                $normalized = home_url($normalized);
            } elseif (!preg_match('#^https?://#i', $normalized)) {
                $normalized = home_url('/' . ltrim($normalized, './'));
            }

            // Try local path first for this WP install.
            $path = wp_parse_url($normalized, PHP_URL_PATH);
            if (is_string($path) && $path !== '') {
                $local_candidate = wp_normalize_path(untrailingslashit(ABSPATH) . $path);
                if (file_exists($local_candidate) && is_readable($local_candidate)) {
                    $tmp_file = $local_candidate;
                }
            }

            if ($tmp_file === '') {
                $resp = wp_remote_get($normalized, array('timeout' => 20, 'redirection' => 3));
                if (is_wp_error($resp)) {
                    return false;
                }
                $body = wp_remote_retrieve_body($resp);
                if (!is_string($body) || $body === '') {
                    return false;
                }

                $tmp = wp_tempnam('mj-regdoc-img');
                if (!is_string($tmp) || $tmp === '') {
                    return false;
                }
                $tmp_file = $tmp . '.img';
                if (@file_put_contents($tmp_file, $body) === false) {
                    return false;
                }
                $cleanup = true;
            }
        }

        $info = @getimagesize($tmp_file);
        if (!is_array($info) || empty($info[0]) || empty($info[1])) {
            if ($cleanup && file_exists($tmp_file)) {
                @unlink($tmp_file);
            }
            return false;
        }

        $width_px = (float) $info[0];
        $height_px = (float) $info[1];
        $width_mm = max(10.0, $width_px * 0.2645833333);
        $height_mm = max(8.0, $height_px * 0.2645833333);
        $max_width = 170.0;
        $max_height = 90.0;

        $target_w = $width_mm;
        $target_h = $height_mm;

        $hint_w_mm = $this->pdfImageHintToMm($width_hint, $max_width);
        $hint_h_mm = $this->pdfImageHintToMm($height_hint, $max_height);

        if ($hint_w_mm !== null && $hint_h_mm !== null) {
            $target_w = max(5.0, $hint_w_mm);
            $target_h = max(5.0, $hint_h_mm);
        } elseif ($hint_w_mm !== null) {
            $target_w = max(5.0, $hint_w_mm);
            $target_h = max(5.0, $target_w * ($height_mm / $width_mm));
        } elseif ($hint_h_mm !== null) {
            $target_h = max(5.0, $hint_h_mm);
            $target_w = max(5.0, $target_h * ($width_mm / $height_mm));
        }

        $scale = min(1.0, $max_width / $target_w, $max_height / $target_h);
        $draw_w = $target_w * $scale;
        $draw_h = $target_h * $scale;

        if ((float) $pdf->GetY() + $draw_h > 275.0) {
            $pdf->AddPage();
        }

        $x = max(10.0, (210.0 - $draw_w) / 2.0);
        try {
            $pdf->Image($tmp_file, $x, null, $draw_w, $draw_h);
            $pdf->Ln($draw_h + 2.0);
        } catch (\Throwable $e) {
            if ($cleanup && file_exists($tmp_file)) {
                @unlink($tmp_file);
            }
            return false;
        }

        if ($cleanup && file_exists($tmp_file)) {
            @unlink($tmp_file);
        }

        return true;
    }

    /**
     * Build a short, filesystem-safe filename segment.
     */
    private function buildContractFilenamePart(string $value, int $max_chars = 30): string {
        $value = trim(wp_strip_all_tags($value));
        if ($value === '') {
            return '';
        }

        if ($max_chars > 0) {
            if (function_exists('mb_substr')) {
                $value = (string) mb_substr($value, 0, $max_chars, 'UTF-8');
            } else {
                $value = substr($value, 0, $max_chars);
            }
        }

        $slug = sanitize_title($value);
        if ($slug === '') {
            $slug = 'part';
        }

        return $slug;
    }

    /**
     * Helper: Format date
     */
    private function formatDate($date, $with_time = false) {
        if (empty($date)) {
            return '';
        }

        $format = $with_time ? 'd/m/Y H:i' : 'd/m/Y';
        $timestamp = strtotime($date);

        if (!$timestamp) {
            return $date;
        }

        return date_i18n($format, $timestamp);
    }

    /**
     * Helper: Get event cover URL
     * Falls back to linked article's featured image if no direct cover
     */
    private function getEventCoverUrl($event, $size = 'thumbnail') {
        // First try direct cover_id
        if (!empty($event->cover_id) && $event->cover_id > 0) {
            $url = wp_get_attachment_image_url($event->cover_id, $size);
            if ($url) {
                return $url;
            }
        }
    
        // Fallback to linked article's featured image
        if (!empty($event->article_id) && $event->article_id > 0) {
            $thumbnail_id = get_post_thumbnail_id($event->article_id);
            if ($thumbnail_id) {
                $url = wp_get_attachment_image_url($thumbnail_id, $size);
                if ($url) {
                    return $url;
                }
            }
        }
    
        return '';
    }

    /**
     * Ensure member notes table exists
     */
    private function ensureNotesTable() {
        global $wpdb;
        $table = $wpdb->prefix . 'mj_member_notes';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            member_id bigint(20) unsigned NOT NULL,
            author_id bigint(20) unsigned NOT NULL,
            content text NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY member_id (member_id),
            KEY author_id (author_id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Backward compatibility wrapper for legacy hooks expecting this function.
     */
    private function createNotesTableIfNotExists() {
        $this->ensureNotesTable();
    }

    /**
     * Get members list with filtering and pagination
     */
    public function getMembers() {
        $current_member = $this->verifyRequest();
        if (!$current_member) return;

        $raw_filter = isset($_POST['filter']) ? $_POST['filter'] : 'all';
        // Support both legacy string filter and new array of active filters
        if (is_array($raw_filter)) {
            $active_filters = array_map('sanitize_text_field', $raw_filter);
        } else {
            $filter_str = sanitize_text_field($raw_filter);
            $active_filters = ($filter_str === 'all' || $filter_str === '') ? array() : array($filter_str);
        }
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $sort = isset($_POST['sort']) ? sanitize_text_field($_POST['sort']) : 'name';
        $sort_order_override = isset($_POST['sortOrder']) && in_array(strtoupper($_POST['sortOrder']), array('ASC', 'DESC'), true)
            ? strtoupper($_POST['sortOrder'])
            : '';
        $page = isset($_POST['page']) ? absint($_POST['page']) : 1;
        $per_page = isset($_POST['perPage']) ? absint($_POST['perPage']) : 10;

        // Déterminer le tri
        $orderby = 'last_name';
        $order = 'ASC';
        switch ($sort) {
            case 'registration_date':
                $orderby = 'date_inscription';
                $order = 'DESC';
                break;
            case 'membership_date':
                $orderby = 'date_last_payement';
                $order = 'DESC';
                break;
            case 'last_login':
                $orderby = 'last_login_at';
                $order = 'DESC';
                break;
            case 'last_activity':
                $orderby = 'last_activity_at';
                $order = 'DESC';
                break;
            case 'level':
                $orderby = 'xp_total';
                $order = 'DESC';
                break;
            case 'name':
            default:
                $orderby = 'last_name';
                $order = 'ASC';
                break;
        }

        // Allow client-side override of sort direction
        if ($sort_order_override !== '') {
            $order = $sort_order_override;
        }

        // Build filters array
        $filters = array();

        if (in_array('membership_due', $active_filters, true)) {
            $filters['payment'] = 'due';
        }

        // Role filters (multiple allowed, normalize aliases like 'parent' -> 'tuteur')
        $valid_roles = array('jeune', 'animateur', 'tuteur', 'benevole', 'coordinateur');
        $role_filters = array();
        foreach ($active_filters as $af) {
            $normalized = MjRoles::normalize($af);
            if (in_array($normalized, $valid_roles, true)) {
                $role_filters[] = $normalized;
            }
        }
        if (!empty($role_filters)) {
            $filters['roles'] = array_values(array_unique($role_filters));
        }

        // Has login filter
        if (in_array('has_login', $active_filters, true)) {
            $filters['has_login'] = true;
        }

        // Favorites filter
        if (in_array('favorites', $active_filters, true)) {
            $fav_members = get_user_meta(get_current_user_id(), '_mj_regmgr_fav_members', true);
            $fav_member_ids = is_array($fav_members) ? array_map('intval', $fav_members) : array();
            if (empty($fav_member_ids)) {
                $filters['ids'] = array(0); // No favorites → return nothing
            } else {
                $filters['ids'] = $fav_member_ids;
            }
        }

        // Get total count (filtered)
        $total = MjMembers::count(array(
            'search' => $search,
            'filters' => $filters,
        ));

        // Get grand total (unfiltered)
        $total_all = MjMembers::countAll();

        // Get members
        $offset = ($page - 1) * $per_page;
        $member_objects = MjMembers::get_all(array(
            'limit' => $per_page,
            'offset' => $offset,
            'orderby' => $orderby,
            'order' => $order,
            'search' => $search,
            'filters' => $filters,
        ));

        // Pre-load all active levels once to compute levelNumber without N+1 queries
        $all_levels = MjLevels::get_all(array('status' => 'active', 'orderby' => 'xp_threshold', 'order' => 'DESC'));

        $current_year = (int) date('Y');
        $members = array();
        foreach ($member_objects as $member) {
            // Check if member requires payment
            $requires_payment = !empty($member->requires_payment) ? true : false;
        
            // Check membership status
            $membership_year = !empty($member->membership_paid_year) ? (int) $member->membership_paid_year : 0;
            $membership_status = 'not_required'; // Default: no payment needed

            if ($requires_payment) {
                $last_payment = !empty($member->date_last_payement) ? $member->date_last_payement : null;
                $last_payment_year = null;

                if ($last_payment && $last_payment !== '0000-00-00 00:00:00') {
                    $timestamp = strtotime($last_payment);
                    if ($timestamp) {
                        $last_payment_year = (int) date('Y', $timestamp);
                    }
                }

                $effective_year = $membership_year;
                if (empty($effective_year) && $last_payment_year) {
                    $effective_year = $last_payment_year;
                }

                if ($effective_year >= $current_year) {
                    $membership_status = 'paid';
                } elseif ($effective_year > 0) {
                    $membership_status = 'expired';
                } else {
                    $membership_status = 'unpaid';
                }
            }

            $members[] = array(
                'id' => (int) $member->id,
                'firstName' => $member->first_name ?? '',
                'lastName' => $member->last_name ?? '',
                'email' => $member->email ?? '',
                'phone' => $member->phone ?? '',
                'phoneSecondary' => $member->phone_secondary ?? '',
                'role' => $member->role ?? 'jeune',
                'birthDate' => $member->birth_date ?? null,
                'avatarUrl' => $this->getMemberAvatarUrl((int) $member->id),
                'requiresPayment' => $requires_payment,
                'membershipStatus' => $membership_status,
                'membershipYear' => $membership_year > 0 ? $membership_year : null,
                'isVolunteer' => !empty($member->is_volunteer),
                'status' => isset($member->status) ? (string) $member->status : MjMembers::STATUS_ACTIVE,
                'xpTotal' => isset($member->xp_total) ? (int) $member->xp_total : 0,
                'coinsTotal' => isset($member->coins_total) ? (int) $member->coins_total : 0,
                'levelNumber' => (function () use ($member, $all_levels) {
                    $xp = isset($member->xp_total) ? (int) $member->xp_total : 0;
                    foreach ($all_levels as $level) {
                        if ((int) $level['xp_threshold'] <= $xp) {
                            return (int) $level['level_number'];
                        }
                    }
                    return 0;
                })(),
                'createdAt' => isset($member->date_inscription) ? $member->date_inscription : null,
                'wpUserId' => !empty($member->wp_user_id) ? (int) $member->wp_user_id : null,
                'lastLoginAt' => !empty($member->last_login_at) ? $member->last_login_at : null,
                'lastActivityAt' => !empty($member->last_activity_at) ? $member->last_activity_at : null,
            );
        }

        wp_send_json_success(array(
            'members' => $members,
            'pagination' => array(
                'page' => $page,
                'totalPages' => $total > 0 ? ceil($total / $per_page) : 1,
                'total' => $total,
                'totalAll' => $total_all,
            ),
        ));
    }

    /**
     * Build level progression payload for a member.
     *
     * @param int $xp_total
     * @return array<string,mixed>
     */
    private function getMemberLevelProgression($xp_total) {
        $xp_total = (int) $xp_total;
        $progression = MjLevels::get_progression($xp_total);

        $current_level = isset($progression['current_level']) ? $progression['current_level'] : null;
        $next_level = isset($progression['next_level']) ? $progression['next_level'] : null;

        $current_level_image = '';
        if ($current_level && !empty($current_level['image_id'])) {
            $current_level_image = wp_get_attachment_image_url((int) $current_level['image_id'], 'thumbnail');
            if (!$current_level_image) {
                $current_level_image = wp_get_attachment_url((int) $current_level['image_id']);
            }
        }

        $next_level_image = '';
        if ($next_level && !empty($next_level['image_id'])) {
            $next_level_image = wp_get_attachment_image_url((int) $next_level['image_id'], 'thumbnail');
            if (!$next_level_image) {
                $next_level_image = wp_get_attachment_url((int) $next_level['image_id']);
            }
        }

        return array(
            'currentLevel' => $current_level ? array(
                'id' => (int) $current_level['id'],
                'levelNumber' => (int) $current_level['level_number'],
                'title' => isset($current_level['title']) ? (string) $current_level['title'] : '',
                'description' => isset($current_level['description']) ? (string) $current_level['description'] : '',
                'xpThreshold' => (int) $current_level['xp_threshold'],
                'xpReward' => isset($current_level['xp_reward']) ? (int) $current_level['xp_reward'] : 0,
                'imageUrl' => $current_level_image ?: '',
            ) : null,
            'nextLevel' => $next_level ? array(
                'id' => (int) $next_level['id'],
                'levelNumber' => (int) $next_level['level_number'],
                'title' => isset($next_level['title']) ? (string) $next_level['title'] : '',
                'description' => isset($next_level['description']) ? (string) $next_level['description'] : '',
                'xpThreshold' => (int) $next_level['xp_threshold'],
                'xpReward' => isset($next_level['xp_reward']) ? (int) $next_level['xp_reward'] : 0,
                'imageUrl' => $next_level_image ?: '',
            ) : null,
            'xpCurrent' => $xp_total,
            'xpForNext' => isset($progression['xp_for_next']) ? (int) $progression['xp_for_next'] : 0,
            'xpProgress' => isset($progression['xp_progress']) ? (int) $progression['xp_progress'] : 0,
            'xpRemaining' => isset($progression['xp_remaining']) ? (int) $progression['xp_remaining'] : 0,
            'progressPercent' => isset($progression['progress_percent']) ? (int) $progression['progress_percent'] : 0,
            'isMaxLevel' => isset($progression['is_max_level']) ? (bool) $progression['is_max_level'] : false,
        );
    }

    /**
     * Build badges payload for a member within the registration manager.
     *
     * @param int $member_id
     * @return array<int,array<string,mixed>>
     */
    private function getMemberBadgesPayload($member_id) {
        $member_id = (int) $member_id;
        if ($member_id <= 0) {
            return array();
        }

        $badges = MjBadges::get_all(array(
            'status' => MjBadges::STATUS_ACTIVE,
            'orderby' => 'display_order',
            'order' => 'ASC',
        ));

        if (empty($badges)) {
            return array();
        }

        $assignments = MjMemberBadges::get_all(array(
            'member_id' => $member_id,
        ));

        $assignment_map = array();
        if (!empty($assignments)) {
            foreach ($assignments as $assignment) {
                $assignment_badge_id = isset($assignment['badge_id']) ? (int) $assignment['badge_id'] : 0;
                if ($assignment_badge_id > 0) {
                    $assignment_map[$assignment_badge_id] = $assignment;
                }
            }
        }

        $payload = array();
        foreach ($badges as $badge) {
            $entry = $this->prepareMemberBadgeEntry($badge, $member_id, $assignment_map);
            if ($entry !== null) {
                $payload[] = $entry;
            }
        }

        return $payload;
    }

    /**
     * Prepare a badge entry payload for a member.
     *
     * @param array<string,mixed>|null $badge
     * @param int $member_id
     * @param array<int,array<string,mixed>>|null $assignment_map
     * @return array<string,mixed>|null
     */
    private function prepareMemberBadgeEntry($badge, $member_id, $assignment_map = null) {
        if (!is_array($badge) || empty($badge['id'])) {
            return null;
        }

        $badge_id = (int) $badge['id'];
        if ($badge_id <= 0) {
            return null;
        }

        if ($assignment_map === null) {
            $assignment_rows = MjMemberBadges::get_all(array(
                'member_id' => $member_id,
                'badge_id' => $badge_id,
                'limit' => 1,
            ));
            $assignment_map = array();
            if (!empty($assignment_rows)) {
                $assignment_map[$badge_id] = $assignment_rows[0];
            }
        }

        $assignment = isset($assignment_map[$badge_id]) ? $assignment_map[$badge_id] : null;
        $assignment_status = '';
        $awarded_at = '';
        $revoked_at = '';

        $image_id = isset($badge['image_id']) ? (int) $badge['image_id'] : 0;
        $image_url = '';
        if ($image_id > 0) {
            $image_url = wp_get_attachment_image_url($image_id, 'medium');
            if (!$image_url) {
                $image_url = wp_get_attachment_url($image_id);
            }
        }

        if (is_array($assignment)) {
            $assignment_status = isset($assignment['status']) ? (string) $assignment['status'] : '';
            $awarded_at = isset($assignment['awarded_at']) ? (string) $assignment['awarded_at'] : '';
            $revoked_at = isset($assignment['revoked_at']) ? (string) $assignment['revoked_at'] : '';
        }

        $criteria_records = array();
        if (!empty($badge['criteria_records']) && is_array($badge['criteria_records'])) {
            foreach ($badge['criteria_records'] as $record) {
                if (empty($record['id'])) {
                    continue;
                }
                if (!empty($record['status']) && $record['status'] === MjBadgeCriteria::STATUS_ARCHIVED) {
                    continue;
                }
                $criteria_records[] = $record;
            }
        }

        $awards = MjMemberBadgeCriteria::get_for_member_badge($member_id, $badge_id);
        $awarded_map = array();
        if (!empty($awards)) {
            foreach ($awards as $award_row) {
                $criterion_id = isset($award_row['criterion_id']) ? (int) $award_row['criterion_id'] : 0;
                if ($criterion_id <= 0) {
                    continue;
                }
                $awarded_map[$criterion_id] = isset($award_row['status']) ? (string) $award_row['status'] : MjMemberBadgeCriteria::STATUS_AWARDED;
            }
        }

        $criteria = array();
        $awardable_total = 0;
        $awarded_count = 0;

        foreach ($criteria_records as $record) {
            $criterion_id = isset($record['id']) ? (int) $record['id'] : 0;
            $can_toggle = $criterion_id > 0;

            $status = 'pending';
            $awarded = false;

            if ($can_toggle && isset($awarded_map[$criterion_id])) {
                $candidate_status = $awarded_map[$criterion_id];
                if ($candidate_status === MjMemberBadgeCriteria::STATUS_AWARDED) {
                    $status = 'awarded';
                    $awarded = true;
                    $awarded_count++;
                } elseif ($candidate_status === MjMemberBadgeCriteria::STATUS_REVOKED) {
                    $status = 'revoked';
                }
            }

            if ($can_toggle) {
                $awardable_total++;
            }

            $criteria[] = array(
                'id' => $criterion_id,
                'label' => isset($record['label']) ? (string) $record['label'] : '',
                'description' => isset($record['description']) ? (string) $record['description'] : '',
                'xp' => isset($record['xp']) ? (int) $record['xp'] : 0,
                'coins' => isset($record['coins']) ? (int) $record['coins'] : 0,
                'awarded' => $awarded,
                'status' => $status,
                'canToggle' => $can_toggle,
            );
        }

        if (empty($criteria) && !empty($badge['criteria']) && is_array($badge['criteria'])) {
            foreach ($badge['criteria'] as $label_raw) {
                $label = trim((string) $label_raw);
                if ($label === '') {
                    continue;
                }
                $criteria[] = array(
                    'id' => 0,
                    'label' => $label,
                    'description' => '',
                    'awarded' => false,
                    'status' => 'pending',
                    'canToggle' => false,
                );
            }
        }

        $progress = 0;
        if ($awardable_total > 0) {
            $progress = (int) round(($awarded_count / $awardable_total) * 100);
        } elseif ($assignment_status === MjMemberBadges::STATUS_AWARDED) {
            $progress = 100;
        }

        if ($progress < 0) {
            $progress = 0;
        } elseif ($progress > 100) {
            $progress = 100;
        }

        return array(
            'id' => $badge_id,
            'label' => isset($badge['label']) ? (string) $badge['label'] : '',
            'summary' => isset($badge['summary']) ? (string) $badge['summary'] : '',
            'description' => isset($badge['description']) ? (string) $badge['description'] : '',
            'icon' => isset($badge['icon']) ? (string) $badge['icon'] : '',
            'imageId' => $image_id,
            'imageUrl' => $image_url,
            'xp' => isset($badge['xp']) ? (int) $badge['xp'] : 0,
            'coins' => isset($badge['coins']) ? (int) $badge['coins'] : 0,
            'status' => $assignment_status,
            'awardedAt' => $awarded_at,
            'revokedAt' => $revoked_at,
            'totalCriteria' => $awardable_total,
            'awardedCount' => $awarded_count,
            'progressPercent' => $progress,
            'criteria' => $criteria,
        );
    }

        /**
         * Normalise une clé de type notification pour assurer une forme stable.
         * Exemple: "avatar applied" => "avatar_applied".
         *
         * @param string $type
         * @return string
         */
        private function normalizeNotificationTypeKey($type) {
            $type = strtolower(trim((string) $type));
            if ($type === '') {
                return '';
            }

            $type = preg_replace('/[\s\-]+/', '_', $type);
            if (!is_string($type)) {
                return '';
            }

            $type = preg_replace('/[^a-z0-9_]/', '', $type);
            if (!is_string($type)) {
                return '';
            }

            $type = preg_replace('/_+/', '_', $type);
            if (!is_string($type)) {
                return '';
            }

            return trim($type, '_');
        }

        /**
         * Retourne un emoji par défaut selon le type de notification.
         *
         * @param string $type
         * @return string
         */
        private function getNotificationTypeEmoji($type) {
            $type_key = $this->normalizeNotificationTypeKey((string) $type);
            if ($type_key === '') {
                return '';
            }

            static $emoji_by_type = array(
                'payment_completed' => '💰',
                'payment_reminder' => '💳',
                'member_created' => '👤',
                'member_profile_updated' => '✏️',
                'profile_updated' => '✏️',
                'photo_uploaded' => '📷',
                'photo_approved' => '✅',
                'idea_published' => '💡',
                'idea_voted' => '👍',
                'trophy_earned' => '🏆',
                'badge_earned' => '🎖️',
                'criterion_earned' => '✓',
                'level_up' => '🚀',
                'avatar_applied' => '🎭',
                'attendance_recorded' => '⏱️',
                'message_received' => '💬',
                'todo_assigned' => '📋',
                'todo_note_added' => '📝',
                'todo_media_added' => '📎',
                'todo_completed' => '✅',
                'testimonial_approved' => '✅',
                'testimonial_rejected' => '❌',
                'testimonial_reaction' => '👍',
                'testimonial_comment' => '💬',
                'testimonial_comment_reply' => '↩️',
                'testimonial_new_pending' => '📝',
                'leave_request_created' => '⛵',
                'leave_request_approved' => '🏖️',
                'leave_request_rejected' => '🚫',
                'info' => 'ℹ️',
            );

            return isset($emoji_by_type[$type_key]) ? $emoji_by_type[$type_key] : '';
        }

        /**
         * Extrait un emoji pertinent depuis une notification format feed.
         *
         * @param array<string,mixed> $notification
         * @return string
         */
        private function extractNotificationEmoji(array $notification) {
            $payload = array();
            if (isset($notification['payload']) && is_array($notification['payload'])) {
                $payload = $notification['payload'];
            }

            $candidates = array();
            if (isset($notification['type']) && is_string($notification['type'])) {
                $candidates[] = $notification['type'];
            }

            $candidate_keys = array('emoji', 'eventEmoji', 'icon', 'symbol', 'typeEmoji');
            foreach ($candidate_keys as $key) {
                if (isset($payload[$key]) && is_scalar($payload[$key])) {
                    $candidates[] = (string) $payload[$key];
                }
            }

            if (isset($notification['title']) && is_string($notification['title'])) {
                $candidates[] = $notification['title'];
            }

            foreach ($candidates as $candidate) {
                $value = wp_check_invalid_utf8((string) $candidate);
                if ($value === '') {
                    continue;
                }

                $value = wp_strip_all_tags($value, false);
                $value = preg_replace('/[\x00-\x1F\x7F]+/', '', $value);
                if (!is_string($value)) {
                    continue;
                }

                $value = trim($value);
                if ($value === '') {
                    continue;
                }

                if (function_exists('mb_substr')) {
                    $value = mb_substr($value, 0, 2);
                } else {
                    $value = substr($value, 0, 2);
                }

                if ($value === '') {
                    continue;
                }

                if (preg_match('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $value)) {
                    return trim($value);
                }
            }

            if (isset($notification['type']) && is_string($notification['type'])) {
                $type_emoji = $this->getNotificationTypeEmoji((string) $notification['type']);
                if ($type_emoji !== '') {
                    return $type_emoji;
                }
            }

            return '';
        }


        /**
         * Formate une entrée notification pour la fiche membre.
         *
         * @param array<string,mixed> $feed_row
         * @return array<string,mixed>
         */
        private function formatMemberNotification(array $feed_row) {
            $notification = isset($feed_row['notification']) && is_array($feed_row['notification'])
                ? $feed_row['notification']
                : array();

            $notification_id = isset($notification['id']) ? (int) $notification['id'] : 0;
            $recipient_id = isset($feed_row['recipient_id']) ? (int) $feed_row['recipient_id'] : 0;
            $type = isset($notification['type']) ? $this->normalizeNotificationTypeKey((string) $notification['type']) : '';

            $type_label = $type !== '' ? ucwords(str_replace('_', ' ', $type)) : __('Notification', 'mj-member');
            $title = isset($notification['title']) ? sanitize_text_field((string) $notification['title']) : '';
            $excerpt = isset($notification['excerpt']) ? sanitize_text_field((string) $notification['excerpt']) : '';
            $text = $title !== '' ? $title : $excerpt;

            return array(
                'id' => $recipient_id,
                'recipientId' => $recipient_id,
                'notificationId' => $notification_id,
                'type' => $type,
                'typeLabel' => $type_label,
                'emoji' => $this->extractNotificationEmoji($notification),
                'title' => $title,
                'excerpt' => $excerpt,
                'text' => $text,
                'url' => isset($notification['url']) ? esc_url_raw((string) $notification['url']) : '',
                'status' => isset($feed_row['recipient_status']) ? sanitize_key((string) $feed_row['recipient_status']) : '',
                'createdAt' => isset($notification['created_at']) ? (string) $notification['created_at'] : '',
            );
        }

    /**
     * Get member details
     */
    public function getMemberDetails() {
        $current_member = $this->verifyRequest();
        if (!$current_member) return;

        $member_id = isset($_POST['memberId']) ? absint($_POST['memberId']) : 0;
        if (!$member_id) {
            wp_send_json_error(array('message' => __('ID du membre manquant.', 'mj-member')));
            return;
        }

        $memberData = MjMembers::getById($member_id);

        if (!$memberData) {
            wp_send_json_error(array('message' => __('Membre non trouvé.', 'mj-member')));
            return;
        }

        // Build address string
        $address = '';
        $address_parts = array_filter(array(
            $memberData->address ?? '',
            $memberData->postal_code ?? '',
            $memberData->city ?? '',
        ));
        if (!empty($address_parts)) {
            $address = implode(' ', $address_parts);
        }

        // Check if member requires payment
        $current_year = (int) date('Y');
        $requires_payment = !empty($memberData->requires_payment) ? true : false;
        $membership_year = !empty($memberData->membership_paid_year) ? (int) $memberData->membership_paid_year : 0;
        $membership_status = 'not_required';
    
        if ($requires_payment) {
            if ($membership_year >= $current_year) {
                $membership_status = 'paid';
            } elseif ($membership_year > 0) {
                $membership_status = 'expired';
            } else {
                $membership_status = 'unpaid';
            }
        }

        // Get role label
        $role_labels = array(
            'jeune' => __('Jeune', 'mj-member'),
            'animateur' => __('Animateur', 'mj-member'),
            'tuteur' => __('Tuteur', 'mj-member'),
            'benevole' => __('Bénévole', 'mj-member'),
            'coordinateur' => __('Coordinateur', 'mj-member'),
        );
        $role = $memberData->role ?? 'jeune';

        $member = array(
            'id' => (int) $memberData->id,
            'firstName' => $memberData->first_name ?? '',
            'lastName' => $memberData->last_name ?? '',
            'nickname' => $memberData->nickname ?? '',
            'email' => $memberData->email ?? '',
            'phone' => $memberData->phone ?? '',
            'phoneSecondary' => $memberData->phone_secondary ?? '',
            'role' => $role,
            'roleLabel' => $role_labels[$role] ?? ucfirst($role),
            'birthDate' => $memberData->birth_date ?? null,
            'address' => $address,
            'addressLine' => $memberData->address ?? '',
            'city' => $memberData->city ?? '',
            'postalCode' => $memberData->postal_code ?? '',
            'avatarUrl' => $this->getMemberAvatarUrl((int) $memberData->id),
            'userId' => $memberData->wp_user_id ?? null,
            'hasLinkedAccount' => !empty($memberData->wp_user_id),
            'accountLogin' => isset($memberData->member_account_login) ? (string) $memberData->member_account_login : '',
            'nextcloudLogin' => isset($memberData->member_nextcloud_login) ? (string) $memberData->member_nextcloud_login : '',
            'nextcloudPassword' => isset($memberData->member_nextcloud_password) ? (string) $memberData->member_nextcloud_password : '',
            'accountEmail' => '',
            'accountRole' => '',
            'accountRoleLabel' => '',
            'accountEditUrl' => '',
            'cardClaimUrl' => '',
            'createdAt' => $memberData->created_at ?? null,
            'dateInscription' => $memberData->date_inscription ?? null,
            'status' => $memberData->status ?? 'active',
            // Cotisation
            'requiresPayment' => $requires_payment,
            'membershipStatus' => $membership_status,
            'membershipYear' => $membership_year > 0 ? $membership_year : null,
            'membershipNumber' => $memberData->membership_number ?? null,
            // Autres infos
            'isAutonomous' => !empty($memberData->is_autonomous),
            'isVolunteer' => !empty($memberData->is_volunteer),
            'isTrustedMember' => !empty($memberData->is_trusted_member),
            'guardianId' => $memberData->guardian_id ?? null,
            'descriptionShort' => $memberData->description_courte ?? '',
            'descriptionLong' => $memberData->description_longue ?? '',
            'newsletterOptIn' => isset($memberData->newsletter_opt_in) ? !empty($memberData->newsletter_opt_in) : true,
            'smsOptIn' => isset($memberData->sms_opt_in) ? !empty($memberData->sms_opt_in) : true,
            'whatsappOptIn' => isset($memberData->whatsapp_opt_in) ? !empty($memberData->whatsapp_opt_in) : true,
            'photoUsageConsent' => (bool) $memberData->get('photo_usage_consent', 0),
            'photoId' => $memberData->get('photo_id', null),
            'xpTotal' => isset($memberData->xp_total) ? (int) $memberData->xp_total : 0,
            'coinsTotal' => isset($memberData->coins_total) ? (int) $memberData->coins_total : 0,
            'guardian' => null,
            // Job profile
            'jobTitle' => $memberData->get('job_title', ''),
            'workRegime' => $memberData->get('work_regime', ''),
            'fundingSource' => $memberData->get('funding_source', ''),
            'jobDescription' => $memberData->get('job_description', ''),
            'signatureMessage' => $memberData->get('signature_message', ''),
        );

        if ($member['hasLinkedAccount']) {
            $user_object = get_user_by('id', (int) $member['userId']);
            if ($user_object) {
                $member['accountEmail'] = $user_object->user_email ?? '';
                if ($member['accountLogin'] === '') {
                    $member['accountLogin'] = $user_object->user_login;
                }
                if (!empty($user_object->roles) && is_array($user_object->roles)) {
                    $primary_role = reset($user_object->roles);
                    if (is_string($primary_role) && $primary_role !== '') {
                        $member['accountRole'] = $primary_role;
                        $role_object = get_role($primary_role);
                        if ($role_object && isset($role_object->name)) {
                            $member['accountRoleLabel'] = translate_user_role($role_object->name);
                        } else {
                            $member['accountRoleLabel'] = ucfirst(str_replace('_', ' ', $primary_role));
                        }
                    }
                }

                $member['accountEditUrl'] = get_edit_user_link($user_object->ID);
            }
        }

        if (function_exists('mj_member_get_card_claim_url')) {
            $member['cardClaimUrl'] = mj_member_get_card_claim_url((int) $memberData->id);
        }

        // Add guardian info if exists
        if (!empty($memberData->guardian_id)) {
            $guardian = MjMembers::getById((int) $memberData->guardian_id);
            if ($guardian) {
                $guardian_role = $guardian->role ?? '';
                $guardian_role_label = '';

                if ($guardian_role !== '' && isset($role_labels[$guardian_role])) {
                    $guardian_role_label = $role_labels[$guardian_role];
                } elseif ($guardian_role !== '') {
                    $guardian_role_label = ucfirst($guardian_role);
                }

                $member['guardianName'] = trim(($guardian->first_name ?? '') . ' ' . ($guardian->last_name ?? ''));
                $member['guardian'] = array(
                    'id' => (int) $guardian->id,
                    'firstName' => $guardian->first_name ?? '',
                    'lastName' => $guardian->last_name ?? '',
                    'avatarUrl' => $this->getMemberAvatarUrl((int) $guardian->id),
                    'role' => $guardian_role,
                    'roleLabel' => $guardian_role_label,
                    'email' => $guardian->email ?? '',
                    'phone' => $guardian->phone ?? '',
                    'phoneSecondary' => $guardian->phone_secondary ?? '',
                );
            }
        }

        // Add children info if member is a guardian (tuteur)
        $children = MjMembers::getChildrenForGuardian($member_id);
        if (!empty($children)) {
            $member['children'] = array();
            foreach ($children as $child) {
                // Get child membership status
                $child_requires_payment = !empty($child->requires_payment) ? true : false;
                $child_membership_year = !empty($child->membership_paid_year) ? (int) $child->membership_paid_year : 0;
                $child_last_payment = isset($child->date_last_payement) ? $child->date_last_payement : null;
                $child_last_payment_year = null;

                if ($child_last_payment && $child_last_payment !== '0000-00-00 00:00:00') {
                    $timestamp = strtotime($child_last_payment);
                    if ($timestamp) {
                        $child_last_payment_year = (int) date('Y', $timestamp);
                    }
                }

                $child_effective_year = $child_membership_year;
                if (empty($child_effective_year) && $child_last_payment_year) {
                    $child_effective_year = $child_last_payment_year;
                }

                $child_membership_status = 'not_required';
                if ($child_requires_payment) {
                    if ($child_effective_year >= $current_year) {
                        $child_membership_status = 'paid';
                    } elseif ($child_effective_year > 0) {
                        $child_membership_status = 'expired';
                    } else {
                        $child_membership_status = 'unpaid';
                    }
                }

                $child_role = $child->role ?? 'jeune';

                $member['children'][] = array(
                    'id' => (int) $child->id,
                    'firstName' => $child->first_name ?? '',
                    'lastName' => $child->last_name ?? '',
                    'birthDate' => $child->birth_date ?? null,
                    'role' => $child_role,
                    'roleLabel' => $role_labels[$child_role] ?? ucfirst($child_role),
                    'avatarUrl' => $this->getMemberAvatarUrl((int) $child->id),
                    'requiresPayment' => $child_requires_payment,
                    'membershipStatus' => $child_membership_status,
                    'membershipYear' => $child_membership_year > 0 ? $child_membership_year : null,
                );
            }
        }

        // Collect event photos for this member (pending + approved)
        $photo_status_labels = MjEventPhotos::get_status_labels();

        $photos = MjEventPhotos::query(array(
            'member_id' => $member_id,
            'status' => array(MjEventPhotos::STATUS_PENDING, MjEventPhotos::STATUS_APPROVED),
            'per_page' => 20,
            'paged' => 1,
            'orderby' => 'created_at',
            'order' => 'DESC',
        ));

        if (!empty($photos)) {
            $member['photos'] = array();
            foreach ($photos as $photo) {
                $attachment_id = isset($photo->attachment_id) ? (int) $photo->attachment_id : 0;
                if ($attachment_id <= 0) {
                    continue;
                }

                $thumbnail = wp_get_attachment_image_url($attachment_id, 'medium');
                if (!$thumbnail) {
                    $thumbnail = wp_get_attachment_url($attachment_id);
                }

                $full = wp_get_attachment_image_url($attachment_id, 'large');
                if (!$full) {
                    $full = wp_get_attachment_url($attachment_id);
                }

                $eventTitle = '';
                if (!empty($photo->event_id)) {
                    $event = MjEvents::find((int) $photo->event_id);
                    if ($event) {
                        $eventTitle = $event->title ?? '';
                    }
                }

                $status_key = isset($photo->status) ? (string) $photo->status : MjEventPhotos::STATUS_APPROVED;

                $member['photos'][] = array(
                    'id' => (int) $photo->id,
                    'eventId' => isset($photo->event_id) ? (int) $photo->event_id : 0,
                    'eventTitle' => $eventTitle,
                    'caption' => isset($photo->caption) ? (string) $photo->caption : '',
                    'thumbnailUrl' => $thumbnail ?: '',
                    'fullUrl' => $full ?: $thumbnail ?: '',
                    'createdAt' => isset($photo->created_at) ? (string) $photo->created_at : '',
                    'status' => $status_key,
                    'statusLabel' => isset($photo_status_labels[$status_key]) ? (string) $photo_status_labels[$status_key] : $status_key,
                );
            }
        }

        // Fetch idea suggestions created by the member
        $ideas = MjIdeas::get_all(array(
            'member_id' => $member_id,
            'statuses' => array(MjIdeas::STATUS_PUBLISHED, MjIdeas::STATUS_ARCHIVED),
            'limit' => 25,
            'orderby' => 'created_at',
            'order' => 'DESC',
        ));

        if (!empty($ideas)) {
            // Collect idea IDs so we can batch-fetch voters
            $idea_ids = array();
            foreach ($ideas as $idea) {
                if (!empty($idea['id'])) {
                    $idea_ids[] = (int) $idea['id'];
                }
            }
            $voters_map = !empty($idea_ids) ? MjIdeaVotes::get_voters_for_ideas($idea_ids) : array();

            $member['ideas'] = array();
            foreach ($ideas as $idea) {
                $idea_id = isset($idea['id']) ? (int) $idea['id'] : 0;
                $voters_raw = isset($voters_map[$idea_id]) ? $voters_map[$idea_id] : array();
                $voters = array();
                foreach ($voters_raw as $voter) {
                    $voters[] = array(
                        'id'   => (int) $voter['id'],
                        'name' => trim($voter['first_name'] . ' ' . $voter['last_name']),
                    );
                }

                $member['ideas'][] = array(
                    'id' => $idea_id,
                    'title' => isset($idea['title']) ? (string) $idea['title'] : '',
                    'content' => isset($idea['content']) ? (string) $idea['content'] : '',
                    'status' => isset($idea['status']) ? (string) $idea['status'] : '',
                    'voteCount' => isset($idea['vote_count']) ? (int) $idea['vote_count'] : 0,
                    'createdAt' => isset($idea['created_at']) ? (string) $idea['created_at'] : '',
                    'voters' => $voters,
                );
            }
        }

        // Retrieve latest contact messages linked to the member
        $messages = MjContactMessages::get_all(array(
            'member_id' => $member_id,
            'per_page' => 20,
            'paged' => 1,
            'orderby' => 'created_at',
            'order' => 'DESC',
        ));

        if (!empty($messages)) {
            $member['messages'] = array();
            foreach ($messages as $message) {
                $activity_log = array();
                if (!empty($message->activity_log)) {
                    $decoded = json_decode($message->activity_log, true);
                    if (is_array($decoded)) {
                        $activity_log = $decoded;
                    }
                }

                $member['messages'][] = array(
                    'id' => (int) $message->id,
                    'senderName' => isset($message->sender_name) ? (string) $message->sender_name : '',
                    'senderEmail' => isset($message->sender_email) ? (string) $message->sender_email : '',
                    'subject' => isset($message->subject) ? (string) $message->subject : '',
                    'message' => isset($message->message) ? wp_kses_post($message->message) : '',
                    'status' => isset($message->status) ? (string) $message->status : '',
                    'isRead' => !empty($message->is_read),
                    'assignedTo' => isset($message->assigned_to) ? (int) $message->assigned_to : 0,
                    'targetType' => isset($message->target_type) ? (string) $message->target_type : '',
                    'createdAt' => isset($message->created_at) ? (string) $message->created_at : '',
                    'activityLog' => $activity_log,
                );
            }
        }

        $member_notifications = array();
        if (function_exists('mj_member_get_member_notifications_feed')) {
            $feed = mj_member_get_member_notifications_feed($member_id, array(
                'limit' => 250,
                'include_archived' => true,
                'include_drafts' => true,
                'include_expired' => true,
                'order' => 'DESC',
            ));

            if (is_array($feed)) {
                foreach ($feed as $feed_row) {
                    if (!is_array($feed_row)) {
                        continue;
                    }
                    $member_notifications[] = $this->formatMemberNotification($feed_row);
                }
            }
        }
        $member['notifications'] = $member_notifications;

        $member['badges'] = $this->getMemberBadgesPayload($member_id);
        $member['trophies'] = $this->getMemberTrophiesPayload($member_id);
        $member['actions'] = $this->getMemberActionsPayload($member_id);

        // Retrieve testimonials submitted by the member
        $testimonials_results = MjTestimonials::query(array(
            'member_id' => $member_id,
            'per_page' => 20,
            'page' => 1,
            'orderby' => 'created_at',
            'order' => 'DESC',
        ));

        if (!empty($testimonials_results)) {
            $member['testimonials'] = array();
            foreach ($testimonials_results as $testimonial) {
                $photos = MjTestimonials::get_photo_urls($testimonial, 'medium');
                $video = MjTestimonials::get_video_data($testimonial);

                // Load comments for this testimonial
                $comments = MjTestimonialComments::get_for_testimonial($testimonial->id);
                $comments_data = array();
                if (!empty($comments)) {
                    foreach ($comments as $comment) {
                        $comment_author = MjMembers::getById($comment->member_id);
                        $comments_data[] = array(
                            'id' => (int) $comment->id,
                            'memberId' => (int) $comment->member_id,
                            'memberName' => $comment_author ? ($comment_author->first_name . ' ' . $comment_author->last_name) : 'Anonyme',
                            'content' => isset($comment->content) ? (string) $comment->content : '',
                            'createdAt' => isset($comment->created_at) ? (string) $comment->created_at : '',
                        );
                    }
                }

                // Load reactions for this testimonial
                $reaction_counts = MjTestimonialReactions::get_counts($testimonial->id);
                $reactions_data = array();
                if (!empty($reaction_counts)) {
                    $reaction_types = MjTestimonialReactions::get_reaction_types();
                    foreach ($reaction_counts as $type => $count) {
                        if (isset($reaction_types[$type])) {
                            $reactions_data[] = array(
                                'type' => $type,
                                'emoji' => $reaction_types[$type]['emoji'],
                                'label' => $reaction_types[$type]['label'],
                                'count' => $count,
                            );
                        }
                    }
                }

                // Get social media link if exists
                $link_preview = null;
                if (isset($testimonial->social_link) && !empty($testimonial->social_link)) {
                    $link_preview = array(
                        'url' => (string) $testimonial->social_link,
                        'title' => isset($testimonial->social_link_title) ? (string) $testimonial->social_link_title : '',
                        'preview' => isset($testimonial->social_link_preview) ? (string) $testimonial->social_link_preview : '',
                    );
                }

                $member['testimonials'][] = array(
                    'id' => (int) $testimonial->id,
                    'content' => isset($testimonial->content) ? (string) $testimonial->content : '',
                    'status' => isset($testimonial->status) ? (string) $testimonial->status : 'pending',
                    'featured' => isset($testimonial->featured) ? (bool) $testimonial->featured : false,
                    'rejection_reason' => isset($testimonial->rejection_reason) ? (string) $testimonial->rejection_reason : '',
                    'photos' => array_map(function ($p) {
                        return array(
                            'thumb' => $p['thumb'],
                            'url' => $p['full'],
                        );
                    }, $photos),
                    'video' => $video ? array(
                        'url' => $video['url'],
                        'poster' => $video['poster'],
                    ) : null,
                    'linkPreview' => $link_preview,
                    'comments' => $comments_data,
                    'reactions' => $reactions_data,
                    'created_at' => isset($testimonial->created_at) ? (string) $testimonial->created_at : '',
                );
            }
        }

        // Ajouter les informations de niveau
        $member['levelProgression'] = $this->getMemberLevelProgression($member['xpTotal']);

        // Ajouter les quotas de congés (si l'utilisateur a le droit de gérer les membres)
        if (current_user_can(Config::capability()) || !empty($current_member['is_coordinateur'])) {
            $current_year = (int) date('Y');
            $years_to_load = [$current_year - 1, $current_year, $current_year + 1];
            $member['leaveQuotas'] = [];
        
            foreach ($years_to_load as $year) {
                $quotas = MjLeaveQuotas::get_quotas_for_member($member_id, $year);
                $member['leaveQuotas'][$year] = [];
            
                // Récupérer les types pour construire le tableau complet
                $leave_types = MjLeaveTypes::get_all(['is_active' => 1]);
                foreach ($leave_types as $lt) {
                    $member['leaveQuotas'][$year][] = [
                        'typeId' => (int) $lt->id,
                        'slug' => $lt->slug,
                        'name' => $lt->name,
                        'quota' => isset($quotas[$lt->slug]) ? (int) $quotas[$lt->slug] : 0,
                    ];
                }
            }

            // Load work schedules for staff members
            $schedules = \Mj\Member\Classes\Crud\MjMemberWorkSchedules::get_for_member($member_id);
            $member['workSchedules'] = \Mj\Member\Classes\Crud\MjMemberWorkSchedules::format_for_response($schedules);
        }

        // Dynamic field values
        $dyn_fields_all = \Mj\Member\Classes\Crud\MjDynamicFields::getAll();
        $dyn_vals = \Mj\Member\Classes\Crud\MjDynamicFieldValues::getByMemberKeyed($member_id);
        $member['dynamicFields'] = array();
        foreach ($dyn_fields_all as $df) {
            $member['dynamicFields'][] = array(
                'id'          => (int) $df->id,
                'title'       => $df->title,
                'description' => $df->description ?? '',
                'type'        => $df->field_type,
                'value'       => isset($dyn_vals[(int) $df->id]) ? $dyn_vals[(int) $df->id] : '',
                'options'     => \Mj\Member\Classes\Crud\MjDynamicFields::decodeOptions($df->options_list),
                'allowOther'  => (bool) ($df->allow_other ?? 0),
                'otherLabel'  => $df->other_label ?? '',
                'isRequired'  => (bool) ($df->is_required ?? 0),
                'showInNotes' => (bool) ($df->show_in_notes ?? 0),
                'youthOnly'   => (bool) ($df->youth_only ?? 0),
            );
        }

        wp_send_json_success(array('member' => $member));
    }

    /**
     * Met à jour le label, l'URL et le statut d'une notification pour un membre.
     */
    public function updateMemberNotification() {
        $current_member = $this->verifyRequest();
        if (!$current_member) {
            return;
        }

        $member_id = isset($_POST['memberId']) ? absint($_POST['memberId']) : 0;
        $notification_id = isset($_POST['notificationId']) ? absint($_POST['notificationId']) : 0;
        $text = isset($_POST['text']) ? sanitize_textarea_field(wp_unslash($_POST['text'])) : '';
        $url = isset($_POST['url']) ? esc_url_raw(wp_unslash($_POST['url'])) : '';
        $status = isset($_POST['status']) ? sanitize_key(wp_unslash($_POST['status'])) : '';

        if ($member_id <= 0 || $notification_id <= 0) {
            wp_send_json_error(array('message' => __('Notification invalide.', 'mj-member')));
            return;
        }

        $recipients = MjNotificationRecipients::get_all(array(
            'member_ids' => array($member_id),
            'notification_ids' => array($notification_id),
            'limit' => 50,
        ));

        if (empty($recipients)) {
            wp_send_json_error(array('message' => __('Cette notification n\'est pas liée à ce membre.', 'mj-member')));
            return;
        }

        if ($text === '') {
            wp_send_json_error(array('message' => __('Le texte de notification est requis.', 'mj-member')));
            return;
        }

        $update = MjNotifications::update($notification_id, array(
            'title' => $text,
            'excerpt' => $text,
            'url' => $url,
        ));

        if (is_wp_error($update)) {
            wp_send_json_error(array('message' => $update->get_error_message()));
            return;
        }

        if ($status !== '') {
            $allowed_statuses = MjNotificationRecipients::get_statuses();
            if (!in_array($status, $allowed_statuses, true)) {
                wp_send_json_error(array('message' => __('Statut de notification invalide.', 'mj-member')));
                return;
            }

            $recipient_ids = array();
            foreach ($recipients as $recipient) {
                $recipient_id = isset($recipient->id) ? (int) $recipient->id : 0;
                if ($recipient_id > 0) {
                    $recipient_ids[] = $recipient_id;
                }
            }

            if (!empty($recipient_ids)) {
                $status_update = MjNotificationRecipients::mark_status($recipient_ids, $status);
                if (is_wp_error($status_update)) {
                    wp_send_json_error(array('message' => $status_update->get_error_message()));
                    return;
                }
            }
        }

        wp_send_json_success(array(
            'message' => __('Notification mise à jour.', 'mj-member'),
            'notificationId' => $notification_id,
            'text' => $text,
            'url' => $url,
            'status' => $status,
        ));
    }

    /**
     * Supprime l'association d'une notification pour un membre.
     */
    public function deleteMemberNotification() {
        $current_member = $this->verifyRequest();
        if (!$current_member) {
            return;
        }

        $member_id = isset($_POST['memberId']) ? absint($_POST['memberId']) : 0;
        $notification_id = isset($_POST['notificationId']) ? absint($_POST['notificationId']) : 0;

        if ($member_id <= 0 || $notification_id <= 0) {
            wp_send_json_error(array('message' => __('Notification invalide.', 'mj-member')));
            return;
        }

        $recipients = MjNotificationRecipients::get_all(array(
            'member_ids' => array($member_id),
            'notification_ids' => array($notification_id),
            'limit' => 50,
        ));

        if (empty($recipients)) {
            wp_send_json_error(array('message' => __('Cette notification n\'est pas liée à ce membre.', 'mj-member')));
            return;
        }

        foreach ($recipients as $recipient) {
            $recipient_id = isset($recipient->id) ? (int) $recipient->id : 0;
            if ($recipient_id <= 0) {
                continue;
            }
            $deleted = MjNotificationRecipients::delete($recipient_id);
            if (is_wp_error($deleted)) {
                wp_send_json_error(array('message' => $deleted->get_error_message()));
                return;
            }
        }

        $remaining = MjNotificationRecipients::count(array(
            'notification_ids' => array($notification_id),
        ));
        if ((int) $remaining === 0) {
            MjNotifications::delete($notification_id);
        }

        wp_send_json_success(array(
            'message' => __('Notification supprimée.', 'mj-member'),
            'notificationId' => $notification_id,
        ));
    }

    /**
     * Synchronize badge criteria for a member
     */
    public function syncMemberBadge() {
        $current_member = $this->verifyRequest();
        if (!$current_member) return;

        $member_id = isset($_POST['memberId']) ? absint($_POST['memberId']) : 0;
        $badge_id = isset($_POST['badgeId']) ? absint($_POST['badgeId']) : 0;

        if ($member_id <= 0 || $badge_id <= 0) {
            wp_send_json_error(array('message' => __('Paramètres invalides.', 'mj-member')));
            return;
        }

        $target_member = MjMembers::getById($member_id);
        if (!$target_member) {
            wp_send_json_error(array('message' => __('Membre introuvable.', 'mj-member')));
            return;
        }

        $badge = MjBadges::get($badge_id);
        if (!$badge || (isset($badge['status']) && $badge['status'] === MjBadges::STATUS_ARCHIVED)) {
            wp_send_json_error(array('message' => __('Badge introuvable ou archivé.', 'mj-member')));
            return;
        }

        $raw_ids = array();
        if (isset($_POST['criterionIds'])) {
            $raw_ids = $_POST['criterionIds'];
            if (is_string($raw_ids)) {
                $decoded = json_decode(stripslashes($raw_ids), true);
                $raw_ids = is_array($decoded) ? $decoded : array();
            }
        }

        $criterion_ids = array();
        if (is_array($raw_ids)) {
            foreach ($raw_ids as $value) {
                $criterion_ids[] = (int) $value;
            }
        }

        $criterion_ids = array_values(array_filter($criterion_ids, static function ($id) {
            return $id > 0;
        }));

        if (!empty($criterion_ids)) {
            $criterion_ids = MjBadgeCriteria::filter_ids_for_badge($badge_id, $criterion_ids);
        }

        // Determine badge completion state BEFORE sync
        $was_complete = $this->isBadgeComplete($member_id, $badge_id);

        $awarded_by = get_current_user_id();

        $sync = MjMemberBadgeCriteria::sync_awards($member_id, $badge_id, $criterion_ids, $awarded_by);
        if (is_wp_error($sync)) {
            wp_send_json_error(array('message' => $sync->get_error_message()));
            return;
        }

        // Determine badge completion state AFTER sync
        $is_complete = $this->isBadgeComplete($member_id, $badge_id);

        // Award or revoke XP and Coins for badge completion
        if ($is_complete && !$was_complete) {
            // Badge just became complete - award XP and Coins
            MjMemberXp::awardForBadgeCompletion($member_id);
            MjMemberCoins::awardForBadgeCompletion($member_id, $badge_id);
        } elseif (!$is_complete && $was_complete) {
            // Badge was complete but no longer is - revoke XP and Coins
            MjMemberXp::revokeForBadgeCompletion($member_id);
            MjMemberCoins::revokeForBadgeCompletion($member_id, $badge_id);
        }

        $status = empty($criterion_ids) ? MjMemberBadges::STATUS_REVOKED : MjMemberBadges::STATUS_AWARDED;
        $assignment = MjMemberBadges::create(array(
            'member_id' => $member_id,
            'badge_id' => $badge_id,
            'status' => $status,
            'awarded_by_user_id' => $awarded_by,
        ));

        if (is_wp_error($assignment)) {
            wp_send_json_error(array('message' => $assignment->get_error_message()));
            return;
        }

        $badge_payload = $this->prepareMemberBadgeEntry($badge, $member_id);

        // Include updated XP and level progression in response
        $updated_member = MjMembers::getById($member_id);
        $xp_total = isset($updated_member->xp_total) ? (int) $updated_member->xp_total : 0;
        $coins_total = isset($updated_member->coins_total) ? (int) $updated_member->coins_total : 0;
        $level_progression = $this->getMemberLevelProgression($xp_total);

        wp_send_json_success(array(
            'message' => __('Progression du badge mise à jour.', 'mj-member'),
            'badge' => $badge_payload,
            'xpTotal' => $xp_total,
            'coinsTotal' => $coins_total,
            'levelProgression' => $level_progression,
        ));
    }

    /**
     * Check if a badge is complete for a given member.
     *
     * A badge is complete when all toggleable criteria are awarded.
     *
     * @param int $member_id Member ID.
     * @param int $badge_id Badge ID.
     * @return bool True if badge is complete.
     */
    private function isBadgeComplete($member_id, $badge_id) {
        $criteria_records = MjBadgeCriteria::get_for_badge($badge_id);
        if (empty($criteria_records)) {
            return false;
        }

        // Filter out archived criteria
        $active_criteria = array_filter($criteria_records, static function ($record) {
            return empty($record['status']) || $record['status'] !== MjBadgeCriteria::STATUS_ARCHIVED;
        });

        $awardable_total = count($active_criteria);
        if ($awardable_total === 0) {
            return false;
        }

        $awards = MjMemberBadgeCriteria::get_for_member_badge($member_id, $badge_id);
        $awarded_count = 0;

        if (!empty($awards)) {
            foreach ($awards as $award_row) {
                $status = isset($award_row['status']) ? (string) $award_row['status'] : '';
                if ($status === MjMemberBadgeCriteria::STATUS_AWARDED) {
                    $awarded_count++;
                }
            }
        }

        return $awarded_count >= $awardable_total;
    }

    /**
     * Adjust member XP manually (add or remove).
     */
    public function adjustMemberXp() {
        $current_member = $this->verifyRequest();
        if (!$current_member) {
            return;
        }

        $member_id = isset($_POST['memberId']) ? absint($_POST['memberId']) : 0;
        $amount = isset($_POST['amount']) ? (int) $_POST['amount'] : 0;

        if ($member_id <= 0) {
            wp_send_json_error(array('message' => __('ID du membre manquant.', 'mj-member')));
            return;
        }

        if ($amount === 0) {
            wp_send_json_error(array('message' => __('Montant XP invalide.', 'mj-member')));
            return;
        }

        $member = MjMembers::getById($member_id);
        if (!$member) {
            wp_send_json_error(array('message' => __('Membre introuvable.', 'mj-member')));
            return;
        }

        if ($amount > 0) {
            $result = MjMemberXp::add($member_id, $amount);
        } else {
            $result = MjMemberXp::subtract($member_id, abs($amount));
        }

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
            return;
        }

        $action_label = $amount > 0 ? 'ajoutés' : 'retirés';
        $abs_amount = abs($amount);
        $level_progression = $this->getMemberLevelProgression($result);

        $updated_member = MjMembers::getById($member_id);
        $coins_total = isset($updated_member->coins_total) ? (int) $updated_member->coins_total : 0;

        wp_send_json_success(array(
            'message' => sprintf(__('%d XP %s.', 'mj-member'), $abs_amount, $action_label),
            'xpTotal' => $result,
            'coinsTotal' => $coins_total,
            'levelProgression' => $level_progression,
        ));
    }

    /**
     * Update member information
     */
    public function updateMember() {
        $current_member = $this->verifyRequest();
        if (!$current_member) return;

        $member_id = isset($_POST['memberId']) ? absint($_POST['memberId']) : 0;
        if (!$member_id) {
            wp_send_json_error(array('message' => __('ID du membre manquant.', 'mj-member')));
            return;
        }

        // Parse data
        $data = isset($_POST['data']) ? $_POST['data'] : array();
        if (is_string($data)) {
            $data = json_decode(stripslashes($data), true);
        }

        if (empty($data)) {
            wp_send_json_error(array('message' => __('Données manquantes.', 'mj-member')));
            return;
        }

        // Build update data with correct column names
        $update_data = array();

        if (isset($data['firstName'])) {
            $update_data['first_name'] = sanitize_text_field($data['firstName']);
        }
        if (isset($data['lastName'])) {
            $update_data['last_name'] = sanitize_text_field($data['lastName']);
        }
        if (isset($data['email'])) {
            $update_data['email'] = sanitize_email($data['email']);
        }
        if (isset($data['phone'])) {
            $update_data['phone'] = sanitize_text_field($data['phone']);
        }
        if (isset($data['phoneSecondary'])) {
            $update_data['phone_secondary'] = sanitize_text_field($data['phoneSecondary']);
        }
        if (isset($data['birthDate'])) {
            $update_data['birth_date'] = sanitize_text_field($data['birthDate']);
        }
        if (array_key_exists('nickname', $data)) {
            $update_data['nickname'] = sanitize_text_field($data['nickname']);
        }
        if (array_key_exists('addressLine', $data)) {
            $update_data['address'] = sanitize_text_field($data['addressLine']);
        }
        if (array_key_exists('city', $data)) {
            $update_data['city'] = sanitize_text_field($data['city']);
        }
        if (array_key_exists('postalCode', $data)) {
            $update_data['postal_code'] = sanitize_text_field($data['postalCode']);
        }
        if (array_key_exists('isVolunteer', $data)) {
            $update_data['is_volunteer'] = $this->toBool($data['isVolunteer']) ? 1 : 0;
        }
        if (array_key_exists('isAutonomous', $data)) {
            $update_data['is_autonomous'] = $this->toBool($data['isAutonomous']) ? 1 : 0;
        }
        if (array_key_exists('isTrustedMember', $data)) {
            $update_data['is_trusted_member'] = $this->toBool($data['isTrustedMember']) ? 1 : 0;
        }
        if (array_key_exists('descriptionShort', $data)) {
            $update_data['description_courte'] = wp_kses_post($data['descriptionShort']);
        }
        if (array_key_exists('descriptionLong', $data)) {
            $update_data['description_longue'] = wp_kses_post($data['descriptionLong']);
        }
        if (array_key_exists('newsletterOptIn', $data)) {
            $update_data['newsletter_opt_in'] = $this->toBool($data['newsletterOptIn'], true) ? 1 : 0;
        }
        if (array_key_exists('smsOptIn', $data)) {
            $update_data['sms_opt_in'] = $this->toBool($data['smsOptIn'], true) ? 1 : 0;
        }
        if (array_key_exists('whatsappOptIn', $data)) {
            $update_data['whatsapp_opt_in'] = $this->toBool($data['whatsappOptIn'], true) ? 1 : 0;
        }
        if (array_key_exists('photoUsageConsent', $data)) {
            $update_data['photo_usage_consent'] = $this->toBool($data['photoUsageConsent']) ? 1 : 0;
        }
        if (array_key_exists('status', $data)) {
            $allowed_statuses = array(
                MjMembers::STATUS_ACTIVE,
                MjMembers::STATUS_INACTIVE,
            );
            $candidate_status = sanitize_key((string) $data['status']);
            if (!in_array($candidate_status, $allowed_statuses, true)) {
                wp_send_json_error(array('message' => __('Statut de membre invalide.', 'mj-member')));
                return;
            }
            $update_data['status'] = $candidate_status;
        }
        if (array_key_exists('guardianId', $data)) {
            $raw_guardian_id = (int) $data['guardianId'];
            if ($raw_guardian_id > 0) {
                $guardian = MjMembers::getById($raw_guardian_id);
                $allowed_guardian_roles = array(MjRoles::TUTEUR, MjRoles::ANIMATEUR, MjRoles::COORDINATEUR);
                if (!$guardian || !in_array($guardian->role, $allowed_guardian_roles, true)) {
                    wp_send_json_error(array('message' => __('Le tuteur spécifié est introuvable ou invalide.', 'mj-member')));
                    return;
                }
                $update_data['guardian_id'] = $raw_guardian_id;
            } else {
                $update_data['guardian_id'] = null;
            }
        }
        if (array_key_exists('photoId', $data)) {
            $raw_photo_id = (int) $data['photoId'];
            if ($raw_photo_id > 0) {
                if (!current_user_can('upload_files')) {
                    wp_send_json_error(array('message' => __('Vous ne pouvez pas gérer la médiathèque.', 'mj-member')));
                    return;
                }

                $attachment = get_post($raw_photo_id);
                if (!$attachment || 'attachment' !== $attachment->post_type) {
                    wp_send_json_error(array('message' => __('Fichier de média introuvable.', 'mj-member')));
                    return;
                }
                if (!wp_attachment_is_image($raw_photo_id)) {
                    wp_send_json_error(array('message' => __('Le fichier sélectionné n\'est pas une image.', 'mj-member')));
                    return;
                }

                $update_data['photo_id'] = $raw_photo_id;
            } else {
                $update_data['photo_id'] = 0;
            }
        }

        if (empty($update_data)) {
            wp_send_json_error(array('message' => __('Aucune donnée à mettre à jour.', 'mj-member')));
            return;
        }

        $result = MjMembers::update($member_id, $update_data);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
            return;
        }

        $response = array(
            'message' => __('Membre mis à jour avec succès.', 'mj-member'),
        );

        if (array_key_exists('photo_id', $update_data)) {
            $response['photoId'] = (int) $update_data['photo_id'];
            $response['avatarUrl'] = $this->getMemberAvatarUrl($member_id);
        }

        if (array_key_exists('guardian_id', $update_data)) {
            $response['guardianId'] = $update_data['guardian_id'] ? (int) $update_data['guardian_id'] : 0;
        }

        wp_send_json_success($response);
    }

    /**
     * Update member trusted status (is_trusted_member)
     */
    public function updateMemberTrustedStatus() {
        error_log('[MJ-Member] mj_regmgr_update_member_trusted_status called with POST: ' . print_r($_POST, true));
    
        $current_member = $this->verifyRequest();
        if (!$current_member) {
            error_log('[MJ-Member] Verify request failed');
            return;
        }

        $member_id = isset($_POST['memberId']) ? absint($_POST['memberId']) : 0;
        $is_trusted = isset($_POST['isTrustedMember']) ? $this->toBool($_POST['isTrustedMember']) : false;

        error_log('[MJ-Member] Updating member ' . $member_id . ' isTrustedMember: ' . ($is_trusted ? 'true' : 'false'));

        if (!$member_id) {
            wp_send_json_error(array('message' => __('ID du membre manquant.', 'mj-member')));
            return;
        }

        $member = MjMembers::getById($member_id);
        if (!$member) {
            wp_send_json_error(array('message' => __('Membre introuvable.', 'mj-member')));
            return;
        }

        $result = MjMembers::update($member_id, array(
            'is_trusted_member' => $is_trusted ? 1 : 0,
        ));

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
            return;
        }

        error_log('[MJ-Member] Member ' . $member_id . ' updated successfully');

        wp_send_json_success(array(
            'message' => $is_trusted
                ? __('Membre marqué comme membre de confiance.', 'mj-member')
                : __('Membre retiré de la liste des membres de confiance.', 'mj-member'),
            'isTrustedMember' => $is_trusted,
        ));
    }

    /**
     * Update leave quotas for a member
     */
    public function updateMemberLeaveQuotas() {
        $current_member = $this->verifyRequest();
        if (!$current_member) return;

        // Only coordinators or admins can update quotas
        if (!current_user_can(Config::capability()) && empty($current_member['is_coordinateur'])) {
            wp_send_json_error(array('message' => __('Permissions insuffisantes.', 'mj-member')), 403);
            return;
        }

        $member_id = isset($_POST['memberId']) ? absint($_POST['memberId']) : 0;
        $year = isset($_POST['year']) ? absint($_POST['year']) : 0;

        if (!$member_id || !$year) {
            wp_send_json_error(array('message' => __('Paramètres manquants.', 'mj-member')));
            return;
        }

        // Validate year (allow current year -5 to +2)
        $current_year = (int) date('Y');
        if ($year < $current_year - 5 || $year > $current_year + 2) {
            wp_send_json_error(array('message' => __('Année invalide.', 'mj-member')));
            return;
        }

        // Parse quotas
        $quotas_raw = isset($_POST['quotas']) ? $_POST['quotas'] : array();
        if (is_string($quotas_raw)) {
            $quotas_raw = json_decode(stripslashes($quotas_raw), true);
        }

        if (!is_array($quotas_raw) || empty($quotas_raw)) {
            wp_send_json_error(array('message' => __('Données de quotas manquantes.', 'mj-member')));
            return;
        }

        // Build quotas array with type_id as key
        $quotas = [];
        foreach ($quotas_raw as $item) {
            if (!isset($item['typeId']) || !isset($item['quota'])) {
                continue;
            }
            $type_id = absint($item['typeId']);
            $quota = absint($item['quota']);
            if ($type_id > 0) {
                $quotas[$type_id] = $quota;
            }
        }

        if (empty($quotas)) {
            wp_send_json_error(array('message' => __('Aucun quota valide fourni.', 'mj-member')));
            return;
        }

        // Save quotas
        $success = MjLeaveQuotas::set_quotas($member_id, $year, $quotas);

        if (!$success) {
            wp_send_json_error(array('message' => __('Erreur lors de l\'enregistrement des quotas.', 'mj-member')));
            return;
        }

        // Return updated quotas for this year
        $updated_quotas = MjLeaveQuotas::get_quotas_for_member($member_id, $year);
        $leave_types = MjLeaveTypes::get_all(['is_active' => 1]);
        $response_quotas = [];
    
        foreach ($leave_types as $lt) {
            $response_quotas[] = [
                'typeId' => (int) $lt->id,
                'slug' => $lt->slug,
                'name' => $lt->name,
                'quota' => isset($updated_quotas[$lt->slug]) ? (int) $updated_quotas[$lt->slug] : 0,
            ];
        }

        wp_send_json_success([
            'message' => __('Quotas mis à jour avec succès.', 'mj-member'),
            'year' => $year,
            'quotas' => $response_quotas,
        ]);
    }

    /**
     * Save a member work schedule (create or update)
     */
    public function saveMemberWorkSchedule() {
        $current_member = $this->verifyRequest();
        if (!$current_member) return;

        // Only coordinators or admins can manage work schedules
        if (!current_user_can(Config::capability()) && empty($current_member['is_coordinateur'])) {
            wp_send_json_error(array('message' => __('Permissions insuffisantes.', 'mj-member')), 403);
            return;
        }

        $member_id = isset($_POST['memberId']) ? absint($_POST['memberId']) : 0;
        $schedule_id = isset($_POST['scheduleId']) ? absint($_POST['scheduleId']) : 0;
        $start_date = isset($_POST['startDate']) ? sanitize_text_field($_POST['startDate']) : '';
        $end_date = isset($_POST['endDate']) ? sanitize_text_field($_POST['endDate']) : '';
        $schedule_raw = isset($_POST['schedule']) ? $_POST['schedule'] : '[]';

        if (!$member_id || !$start_date) {
            wp_send_json_error(array('message' => __('Paramètres manquants.', 'mj-member')));
            return;
        }

        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) {
            wp_send_json_error(array('message' => __('Format de date de début invalide.', 'mj-member')));
            return;
        }

        if ($end_date && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
            wp_send_json_error(array('message' => __('Format de date de fin invalide.', 'mj-member')));
            return;
        }

        // Validate that end date is after start date
        if ($end_date && $end_date < $start_date) {
            wp_send_json_error(array('message' => __('La date de fin doit être après la date de début.', 'mj-member')));
            return;
        }

        // Parse schedule
        if (is_string($schedule_raw)) {
            $schedule = json_decode(stripslashes($schedule_raw), true);
        } else {
            $schedule = $schedule_raw;
        }

        if (!is_array($schedule)) {
            $schedule = [];
        }

        // Check for overlapping schedules
        $has_overlap = \Mj\Member\Classes\Crud\MjMemberWorkSchedules::has_overlap(
            $member_id,
            $start_date,
            $end_date ?: null,
            $schedule_id ?: null
        );

        if ($has_overlap) {
            wp_send_json_error(array('message' => __('Cette période chevauche un horaire existant.', 'mj-member')));
            return;
        }

        $data = [
            'member_id' => $member_id,
            'start_date' => $start_date,
            'end_date' => $end_date ?: null,
            'schedule' => $schedule,
        ];

        if ($schedule_id > 0) {
            // Update existing schedule
            $success = \Mj\Member\Classes\Crud\MjMemberWorkSchedules::update($schedule_id, $data);
            if (!$success) {
                wp_send_json_error(array('message' => __('Erreur lors de la mise à jour de l\'horaire.', 'mj-member')));
                return;
            }
        } else {
            // Create new schedule
            $insert_id = \Mj\Member\Classes\Crud\MjMemberWorkSchedules::create($data);
            if (!$insert_id) {
                wp_send_json_error(array('message' => __('Erreur lors de la création de l\'horaire.', 'mj-member')));
                return;
            }
            $schedule_id = $insert_id;
        }

        // Return updated schedules list
        $schedules = \Mj\Member\Classes\Crud\MjMemberWorkSchedules::get_for_member($member_id);
        $formatted = \Mj\Member\Classes\Crud\MjMemberWorkSchedules::format_for_response($schedules);

        wp_send_json_success([
            'message' => __('Horaire enregistré avec succès.', 'mj-member'),
            'scheduleId' => $schedule_id,
            'workSchedules' => $formatted,
        ]);
    }

    /**
     * Delete a member work schedule
     */
    public function deleteMemberWorkSchedule() {
        $current_member = $this->verifyRequest();
        if (!$current_member) return;

        // Only coordinators or admins can manage work schedules
        if (!current_user_can(Config::capability()) && empty($current_member['is_coordinateur'])) {
            wp_send_json_error(array('message' => __('Permissions insuffisantes.', 'mj-member')), 403);
            return;
        }

        $member_id = isset($_POST['memberId']) ? absint($_POST['memberId']) : 0;
        $schedule_id = isset($_POST['scheduleId']) ? absint($_POST['scheduleId']) : 0;

        if (!$member_id || !$schedule_id) {
            wp_send_json_error(array('message' => __('Paramètres manquants.', 'mj-member')));
            return;
        }

        // Verify that the schedule belongs to the member
        $schedule = \Mj\Member\Classes\Crud\MjMemberWorkSchedules::get($schedule_id);
        if (!$schedule || (int) $schedule->member_id !== $member_id) {
            wp_send_json_error(array('message' => __('Horaire introuvable.', 'mj-member')));
            return;
        }

        $success = \Mj\Member\Classes\Crud\MjMemberWorkSchedules::delete($schedule_id);
        if (!$success) {
            wp_send_json_error(array('message' => __('Erreur lors de la suppression de l\'horaire.', 'mj-member')));
            return;
        }

        // Return updated schedules list
        $schedules = \Mj\Member\Classes\Crud\MjMemberWorkSchedules::get_for_member($member_id);
        $formatted = \Mj\Member\Classes\Crud\MjMemberWorkSchedules::format_for_response($schedules);

        wp_send_json_success([
            'message' => __('Horaire supprimé avec succès.', 'mj-member'),
            'workSchedules' => $formatted,
        ]);
    }

    /**
     * Delete a member from the directory
     */
    public function deleteMember() {
        $auth = $this->verifyRequest();
        if (!$auth) {
            return;
        }

        $member_id = isset($_POST['memberId']) ? absint($_POST['memberId']) : 0;
        if ($member_id <= 0) {
            wp_send_json_error(array('message' => __('Identifiant de membre invalide.', 'mj-member')));
            return;
        }

        $can_delete = !empty($auth['is_coordinateur']) || current_user_can(Config::capability());
        if (!$can_delete) {
            wp_send_json_error(
                array('message' => __('Vous ne pouvez pas supprimer ce membre.', 'mj-member')),
                403
            );
            return;
        }

        $current_member_id = isset($auth['member_id']) ? (int) $auth['member_id'] : 0;
        if ($current_member_id === $member_id) {
            wp_send_json_error(array('message' => __('Vous ne pouvez pas supprimer votre propre fiche depuis cet écran.', 'mj-member')));
            return;
        }

        $member = MjMembers::getById($member_id);
        if (!$member) {
            wp_send_json_error(array('message' => __('Membre introuvable.', 'mj-member')));
            return;
        }

        $result = MjMembers::delete($member_id);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
            return;
        }

        wp_send_json_success(array(
            'message' => __('Membre supprimé.', 'mj-member'),
            'memberId' => $member_id,
        ));
    }

    /**
     * Create a Nextcloud login for a member.
     */
    public function createMemberNextcloudLogin() {
        $auth = $this->verifyRequest();
        if (!$auth) {
            return;
        }

        $operations = array();
        $log_op = static function (string $message) use (&$operations): void {
            $operations[] = '[' . current_time('H:i:s') . '] ' . $message;
        };

        $send_error = static function (string $message, int $status = 200) use (&$operations): void {
            wp_send_json_error(array(
                'message' => $message,
                'operations' => $operations,
            ), $status);
        };

        $log_op('Validation de la requête');

        if (!current_user_can(Config::capability()) && empty($auth['is_coordinateur'])) {
            $log_op('Permissions insuffisantes');
            $send_error(__('Permissions insuffisantes.', 'mj-member'), 403);
            return;
        }

        if (!MjNextcloud::isAvailable()) {
            $log_op('Configuration Nextcloud incomplète');
            $send_error(__('La configuration Nextcloud est incomplète.', 'mj-member'));
            return;
        }

        $member_id = isset($_POST['memberId']) ? absint($_POST['memberId']) : 0;
        if ($member_id <= 0) {
            $log_op('memberId invalide');
            $send_error(__('Identifiant de membre invalide.', 'mj-member'));
            return;
        }

        $member = MjMembers::getById($member_id);
        if (!$member) {
            $log_op('Membre introuvable');
            $send_error(__('Membre introuvable.', 'mj-member'));
            return;
        }

        $log_op('Membre chargé: #' . $member_id);

        $requested_login_raw = isset($_POST['login']) ? wp_unslash($_POST['login']) : '';
        $requested_login = sanitize_user($requested_login_raw, true);

        if ($requested_login_raw !== '' && $requested_login === '') {
            $log_op('Login demandé invalide');
            $send_error(__('Identifiant Nextcloud invalide.', 'mj-member'));
            return;
        }

        $candidate_logins = array();
        if ($requested_login !== '') {
            $candidate_logins[] = $requested_login;
        }

        $stored_login = isset($member->member_nextcloud_login) ? sanitize_user((string) $member->member_nextcloud_login, true) : '';
        if ($stored_login !== '') {
            $candidate_logins[] = $stored_login;
        }

        if (!empty($member->wp_user_id)) {
            $wp_user = get_user_by('id', (int) $member->wp_user_id);
            if ($wp_user && !empty($wp_user->user_login)) {
                $candidate_logins[] = sanitize_user((string) $wp_user->user_login, true);
            }
        }

        if (!empty($member->email) && is_email($member->email)) {
            $from_email = sanitize_user((string) current(explode('@', (string) $member->email)), true);
            if ($from_email !== '') {
                $candidate_logins[] = $from_email;
            }
        }

        $name_login = sanitize_user(trim((($member->first_name ?? '') . '.' . ($member->last_name ?? ''))), true);
        if ($name_login !== '') {
            $candidate_logins[] = $name_login;
        }

        $candidate_logins[] = sanitize_user('member' . (int) $member->id, true);

        $nextcloud = MjNextcloud::make();
        if (is_wp_error($nextcloud)) {
            $log_op('Client Nextcloud indisponible: ' . $nextcloud->get_error_message());
            $send_error($nextcloud->get_error_message());
            return;
        }

        $log_op('Client Nextcloud initialisé');

        $stored_nextcloud_login = sanitize_user(trim((string) ($member->member_nextcloud_login ?? '')), true);
        if ($stored_nextcloud_login !== '') {
            array_unshift($candidate_logins, $stored_nextcloud_login);
        }

        $candidate_logins = array_values(array_unique(array_filter(array_map(
            static function ($candidate) {
                return sanitize_user((string) $candidate, true);
            },
            $candidate_logins
        ))));

        $nextcloud_login = '';
        $login_exists = false;

        foreach ($candidate_logins as $candidate) {
            if ($candidate === '') {
                continue;
            }

            $exists = $nextcloud->userExists($candidate);
            if ($exists && $stored_nextcloud_login !== '' && $candidate === $stored_nextcloud_login) {
                $nextcloud_login = $candidate;
                $login_exists = true;
                break;
            }

            if (!$exists) {
                $nextcloud_login = $candidate;
                $login_exists = false;
                break;
            }
        }

        if ($nextcloud_login === '') {
            $log_op('Aucun login Nextcloud disponible');
            $send_error(__('Impossible de trouver un identifiant Nextcloud disponible.', 'mj-member'));
            return;
        }

        $log_op($login_exists
            ? 'Login existant détecté: ' . $nextcloud_login
            : 'Login disponible sélectionné: ' . $nextcloud_login
        );

        $manual_password = isset($_POST['password']) ? trim((string) wp_unslash($_POST['password'])) : '';
        if ($manual_password !== '' && strlen($manual_password) < 8) {
            $log_op('Mot de passe refusé (< 8 caractères)');
            $send_error(__('Le mot de passe doit contenir au moins 8 caractères.', 'mj-member'));
            return;
        }

        $is_admin = isset($_POST['is_admin']) && in_array(strtolower(trim((string) wp_unslash($_POST['is_admin']))), array('1', 'true', 'yes', 'on'), true);
        if ($is_admin) {
            $log_op('Option admin activée');
        }

        $password_to_store = '';
        if ($manual_password !== '') {
            $password_to_store = $manual_password;
        } elseif (!$login_exists) {
            $password_to_store = wp_generate_password(14, true, false);
        }

        $display_name = trim((string) (($member->first_name ?? '') . ' ' . ($member->last_name ?? '')));
        $email = !empty($member->email) && is_email($member->email) ? (string) $member->email : '';

        global $wpdb;
        $table_name = MjMembers::getTableName(MjMembers::TABLE_NAME);
        $columns = $wpdb->get_col("SHOW COLUMNS FROM {$table_name}");

        if ($login_exists) {
            if ($manual_password !== '') {
                $log_op('Mise à jour du mot de passe Nextcloud');
                $password_updated = $nextcloud->setUserPassword($nextcloud_login, $manual_password);
                if (is_wp_error($password_updated)) {
                    $log_op('Échec mise à jour mot de passe: ' . $password_updated->get_error_message());
                    $send_error($password_updated->get_error_message());
                    return;
                }
                $log_op('Mot de passe Nextcloud mis à jour');
            }
        } else {
            $log_op('Création du compte Nextcloud');
            $created = $nextcloud->createUser($nextcloud_login, $password_to_store, $display_name, $email);
            if (is_wp_error($created)) {
                $log_op('Échec création du compte: ' . $created->get_error_message());
                $send_error($created->get_error_message());
                return;
            }
            $log_op('Compte Nextcloud créé');
        }

        // Utilise les groupes envoyés par le client, sinon repli sur la configuration par défaut
        $configured_groups = Config::nextcloudGroups();
        if (isset($_POST['groups']) && is_array($_POST['groups'])) {
            $configured_groups = array_values(array_filter(array_map(
                function ($g) {
                    return trim(sanitize_text_field((string) wp_unslash($g)));
                },
                (array) $_POST['groups']
            )));
        }

        if ($is_admin && !in_array('admin', $configured_groups, true)) {
            $configured_groups[] = 'admin';
        }

        $configured_groups = array_values(array_unique(array_filter($configured_groups)));
        $log_op('Attribution des groupes: ' . (!empty($configured_groups) ? implode(', ', $configured_groups) : '(aucun)'));

        $assigned_groups = array();
        $group_errors = array();

        foreach ($configured_groups as $group_id) {
            $assign_result = $nextcloud->addUserToGroup($nextcloud_login, $group_id);
            if (is_wp_error($assign_result)) {
                $group_errors[] = sprintf('%s: %s', $group_id, $assign_result->get_error_message());
                $log_op('Échec groupe ' . $group_id . ': ' . $assign_result->get_error_message());
                continue;
            }
            $assigned_groups[] = $group_id;
            $log_op('Groupe ajouté: ' . $group_id);
        }

        // Synchronise l'avatar du profil MJ vers l'avatar du compte Nextcloud (API OCS)
        $avatar_attachment_id = isset($member->photo_id) ? (int) $member->photo_id : 0;
        if ($avatar_attachment_id <= 0 && isset($member->avatar_id)) {
            $avatar_attachment_id = (int) $member->avatar_id;
        }

        if ($avatar_attachment_id > 0) {
            $avatar_file = get_attached_file($avatar_attachment_id);
            if (is_string($avatar_file) && $avatar_file !== '' && file_exists($avatar_file) && is_readable($avatar_file)) {
                $avatar_content = file_get_contents($avatar_file);
                if ($avatar_content !== false) {
                    $avatar_size = strlen($avatar_content);
                    if ($avatar_size <= 0) {
                        $log_op('Avatar non synchronisé: contenu source vide');
                        $avatar_content = false;
                    }

                }

                if ($avatar_content !== false) {
                    $avatar_ext = strtolower((string) pathinfo($avatar_file, PATHINFO_EXTENSION));
                    if ($avatar_ext === '') {
                        $avatar_ext = 'jpg';
                    }
                    $avatar_mime = (string) get_post_mime_type($avatar_attachment_id);
                    if ($avatar_mime === '') {
                        $avatar_mime = $avatar_ext === 'png' ? 'image/png' : 'image/jpeg';
                    }

                    // Normalise l'image en JPEG 512px max pour éviter certains échecs serveur (500/415).
                    if (function_exists('wp_get_image_editor')) {
                        $image_editor = wp_get_image_editor($avatar_file);
                        if (!is_wp_error($image_editor)) {
                            $image_editor->resize(512, 512, false);
                            if (method_exists($image_editor, 'set_quality')) {
                                $image_editor->set_quality(85);
                            }

                            // wp_tempnam() creates a file without a proper extension; the image editor
                            // may save to a different path (appending .jpg). We must use $saved['path']
                            // to read the actual output file instead of the original empty temp file.
                            $tmp_avatar_file = wp_tempnam('mj-nc-av-' . (int) $member_id);
                            if (is_string($tmp_avatar_file) && $tmp_avatar_file !== '') {
                                $saved = $image_editor->save($tmp_avatar_file, 'image/jpeg');
                                if (!is_wp_error($saved)) {
                                    $actual_saved_path = (isset($saved['path']) && $saved['path'] !== '') ? $saved['path'] : $tmp_avatar_file;
                                    if (file_exists($actual_saved_path) && is_readable($actual_saved_path)) {
                                        $normalized_content = file_get_contents($actual_saved_path);
                                        if ($normalized_content !== false && strlen($normalized_content) > 0) {
                                            $avatar_content = $normalized_content;
                                            $avatar_mime = 'image/jpeg';
                                            $log_op('Avatar normalisé en JPEG (512px, ' . strlen($normalized_content) . ' bytes, path: ' . basename($actual_saved_path) . ')');
                                        } else {
                                            $log_op('Normalisation JPEG ignorée: fichier normalisé vide à ' . $actual_saved_path);
                                        }
                                    } else {
                                        $log_op('Normalisation JPEG ignorée: chemin introuvable: ' . $actual_saved_path);
                                    }
                                    if ($actual_saved_path !== $tmp_avatar_file) {
                                        @unlink($actual_saved_path);
                                    }
                                } else {
                                    $log_op('Normalisation JPEG ignorée: save() erreur: ' . $saved->get_error_message());
                                }
                                @unlink($tmp_avatar_file);
                            }
                        }
                    }

                    if (!is_string($avatar_content) || strlen($avatar_content) <= 0) {
                        $log_op('Avatar non synchronisé: contenu final vide');
                    } else {
                        $log_op('Tentative mise à jour avatar Nextcloud (' . strlen($avatar_content) . ' bytes, ' . $avatar_mime . ')');
                    }

                    if (is_string($avatar_content) && strlen($avatar_content) > 0) {
                        $avatar_updated = $nextcloud->setUserAvatar($nextcloud_login, $avatar_content, $avatar_mime);
                        if (is_wp_error($avatar_updated)) {
                            $log_op('Échec mise à jour avatar Nextcloud: ' . $avatar_updated->get_error_message());

                            // Fallback: persist avatar as a regular file in Nextcloud when avatar API is unsupported.
                            $fallback_filename = 'mj-member-avatar-' . sanitize_title($nextcloud_login) . '.dat';
                            $fallback_parent = Config::nextcloudRootFolder();
                            // Use .dat extension + octet-stream to bypass server-side image MIME sniffing (415).
                            $fallback_upload = $nextcloud->uploadContent($fallback_parent, $fallback_filename, $avatar_content, 'application/octet-stream');

                            if (is_wp_error($fallback_upload)) {
                                $log_op('Fallback upload avatar (fichier) échoué: ' . $fallback_upload->get_error_message());
                            } else {
                                $log_op('Fallback upload avatar (fichier) réussi: ' . $fallback_filename);
                            }
                        } else {
                            $log_op('Avatar du compte Nextcloud mis à jour');
                        }
                    }
                } else {
                    $log_op('Avatar non synchronisé: lecture fichier impossible');
                }
            } else {
                $log_op('Avatar non synchronisé: fichier avatar introuvable');
            }
        } else {
            $log_op('Avatar non synchronisé: aucun avatar membre');
        }

        $updates = array();
        $formats = array();

        if (is_array($columns) && in_array('member_nextcloud_login', $columns, true)) {
            $updates['member_nextcloud_login'] = $nextcloud_login;
            $formats[] = '%s';
        }

        if ($password_to_store !== '' && is_array($columns) && in_array('member_nextcloud_password', $columns, true)) {
            $updates['member_nextcloud_password'] = $password_to_store;
            $formats[] = '%s';
        }

        $now = current_time('mysql');
        if (!$login_exists && is_array($columns) && in_array('nextcloud_last_creation_date', $columns, true)) {
            $updates['nextcloud_last_creation_date'] = $now;
            $formats[] = '%s';
        }
        if (is_array($columns) && in_array('nextcloud_last_connexion_date', $columns, true)) {
            $updates['nextcloud_last_connexion_date'] = $now;
            $formats[] = '%s';
        }

        if (!empty($updates)) {
            $wpdb->update(
                $table_name,
                $updates,
                array('id' => $member_id),
                $formats,
                array('%d')
            );
            $log_op('Métadonnées membre mises à jour en base');
        }

        $response = array(
            'message' => $login_exists
                ? __('Login Nextcloud mis à jour avec succès.', 'mj-member')
                : __('Login Nextcloud créé avec succès.', 'mj-member'),
            'login' => $nextcloud_login,
            'groups_assigned' => $assigned_groups,
            'login_exists' => $login_exists,
            'operations' => $operations,
        );

        if (!empty($group_errors)) {
            $response['groups_errors'] = $group_errors;
            $response['message'] .= ' ' . __('Certains groupes n\'ont pas pu être attribués.', 'mj-member');
        }

        if (!$login_exists && $manual_password === '') {
            $response['generated_password'] = $password_to_store;
        }

        $log_op('Opération terminée avec succès');
        $response['operations'] = $operations;

        wp_send_json_success($response);
    }

    /**
     * Trigger a password reset email for a member's linked WordPress account
     */
    public function resetMemberPassword() {
        $auth = $this->verifyRequest();
        if (!$auth) {
            return;
        }

        $member_id = isset($_POST['memberId']) ? absint($_POST['memberId']) : 0;
        if ($member_id <= 0) {
            wp_send_json_error(array('message' => __('Identifiant de membre invalide.', 'mj-member')));
            return;
        }

        $member = MjMembers::getById($member_id);
        if (!$member) {
            wp_send_json_error(array('message' => __('Membre introuvable.', 'mj-member')));
            return;
        }

        $user_id = isset($member->wp_user_id) ? (int) $member->wp_user_id : 0;
        if ($user_id <= 0) {
            wp_send_json_error(array('message' => __('Aucun compte WordPress n\'est associé à ce membre.', 'mj-member')));
            return;
        }

        $user = get_user_by('id', $user_id);
        if (!$user) {
            wp_send_json_error(array('message' => __('Compte utilisateur introuvable.', 'mj-member')));
            return;
        }

        if (!apply_filters('allow_password_reset', true, $user->ID)) {
            wp_send_json_error(array('message' => __('La réinitialisation du mot de passe est désactivée pour ce compte.', 'mj-member')));
            return;
        }

        $reset = retrieve_password($user->user_login);
        if (is_wp_error($reset)) {
            wp_send_json_error(array('message' => $reset->get_error_message()));
            return;
        }

        wp_send_json_success(array(
            'message' => __('Un email de réinitialisation a été envoyé au membre.', 'mj-member'),
            'email' => $user->user_email,
        ));
    }

    /**
     * Update a member idea
     */
    public function updateMemberIdea() {
        $auth = $this->verifyRequest();
        if (!$auth) {
            return;
        }

        $idea_id = isset($_POST['ideaId']) ? (int) $_POST['ideaId'] : 0;
        $member_id = isset($_POST['memberId']) ? (int) $_POST['memberId'] : 0;

        if ($idea_id <= 0 || $member_id <= 0) {
            wp_send_json_error(array('message' => __('Identifiant d\'idée ou de membre invalide.', 'mj-member')));
            return;
        }

        $payload = isset($_POST['data']) ? $_POST['data'] : array();
        if (is_string($payload)) {
            $decoded = json_decode(stripslashes($payload), true);
            $payload = is_array($decoded) ? $decoded : array();
        }

        if (empty($payload) || !is_array($payload)) {
            wp_send_json_error(array('message' => __('Aucune donnée fournie pour la mise à jour.', 'mj-member')));
            return;
        }

        $idea = MjIdeas::get($idea_id);
        if (!$idea) {
            wp_send_json_error(array('message' => __('Idée introuvable.', 'mj-member')));
            return;
        }

        if ((int) ($idea['member_id'] ?? 0) !== $member_id) {
            wp_send_json_error(array('message' => __('Cette idée n\'est pas associée à ce membre.', 'mj-member')));
            return;
        }

        $update = array();

        if (array_key_exists('title', $payload)) {
            $update['title'] = sanitize_text_field(wp_unslash((string) $payload['title']));
        }

        if (array_key_exists('content', $payload)) {
            $update['content'] = sanitize_textarea_field(wp_unslash((string) $payload['content']));
        }

        if (array_key_exists('status', $payload)) {
            $status = sanitize_key($payload['status']);
            if (!in_array($status, MjIdeas::statuses(), true)) {
                wp_send_json_error(array('message' => __('Statut d\'idée invalide.', 'mj-member')));
                return;
            }
            $update['status'] = $status;
        }

        if (empty($update)) {
            wp_send_json_error(array('message' => __('Aucune donnée à mettre à jour.', 'mj-member')));
            return;
        }

        $result = MjIdeas::update($idea_id, $update);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
            return;
        }

        $updated = MjIdeas::get($idea_id);
        if (!$updated) {
            wp_send_json_success(array(
                'message' => __('Idée mise à jour.', 'mj-member'),
            ));
            return;
        }

        $response = array(
            'id' => (int) ($updated['id'] ?? $idea_id),
            'title' => isset($updated['title']) ? (string) $updated['title'] : '',
            'content' => isset($updated['content']) ? (string) $updated['content'] : '',
            'status' => isset($updated['status']) ? (string) $updated['status'] : MjIdeas::STATUS_PUBLISHED,
            'voteCount' => isset($updated['vote_count']) ? (int) $updated['vote_count'] : 0,
            'createdAt' => isset($updated['created_at']) ? (string) $updated['created_at'] : '',
            'updatedAt' => isset($updated['updated_at']) ? (string) $updated['updated_at'] : '',
        );

        wp_send_json_success(array(
            'message' => __('Idée mise à jour.', 'mj-member'),
            'idea' => $response,
        ));
    }

    /**
     * Delete a member idea
     */
    public function deleteMemberIdea() {
        $auth = $this->verifyRequest();
        if (!$auth) {
            return;
        }

        $idea_id = isset($_POST['ideaId']) ? (int) $_POST['ideaId'] : 0;
        $member_id = isset($_POST['memberId']) ? (int) $_POST['memberId'] : 0;

        if ($idea_id <= 0 || $member_id <= 0) {
            wp_send_json_error(array('message' => __('Identifiant d\'idée ou de membre invalide.', 'mj-member')));
            return;
        }

        $idea = MjIdeas::get($idea_id);
        if (!$idea) {
            wp_send_json_error(array('message' => __('Idée introuvable.', 'mj-member')));
            return;
        }

        if ((int) ($idea['member_id'] ?? 0) !== $member_id) {
            wp_send_json_error(array('message' => __('Cette idée n\'est pas associée à ce membre.', 'mj-member')));
            return;
        }

        $result = MjIdeas::delete($idea_id);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
            return;
        }

        wp_send_json_success(array(
            'message' => __('Idée supprimée.', 'mj-member'),
            'ideaId' => $idea_id,
        ));
    }

    /**
     * Capture and assign a new member photo via direct upload
     */
    public function captureMemberPhoto() {
        $auth = $this->verifyRequest();
        if (!$auth) {
            return;
        }

        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => __('Vous ne pouvez pas gérer la médiathèque.', 'mj-member')));
            return;
        }

        $member_id = isset($_POST['memberId']) ? (int) $_POST['memberId'] : 0;
        if ($member_id <= 0) {
            wp_send_json_error(array('message' => __('Identifiant de membre invalide.', 'mj-member')));
            return;
        }

        $member = MjMembers::getById($member_id);
        if (!$member) {
            wp_send_json_error(array('message' => __('Membre introuvable.', 'mj-member')));
            return;
        }

        $can_change = !empty($auth['is_coordinateur']) || current_user_can(Config::capability());
        if (!$can_change) {
            wp_send_json_error(array('message' => __('Accès refusé pour mettre à jour cette photo.', 'mj-member')), 403);
            return;
        }

        if (!isset($_FILES['photo'])) {
            wp_send_json_error(array('message' => __('Aucune photo reçue.', 'mj-member')));
            return;
        }

        $file = $_FILES['photo'];
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            wp_send_json_error(array('message' => __('Le fichier envoyé est invalide.', 'mj-member')));
            return;
        }

        $file_check = wp_check_filetype_and_ext($file['tmp_name'], $file['name']);
        if (empty($file_check['type']) || strpos($file_check['type'], 'image/') !== 0) {
            wp_send_json_error(array('message' => __('Le fichier sélectionné n\'est pas une image.', 'mj-member')));
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $upload_overrides = array(
            'test_form' => false,
            'mimes' => array(
                'jpg|jpeg|jpe' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'webp' => 'image/webp',
            ),
        );

        $uploaded = wp_handle_upload($file, $upload_overrides);
        if (isset($uploaded['error'])) {
            wp_send_json_error(array('message' => $uploaded['error']));
            return;
        }

        $filename = $uploaded['file'];
        $attachment = array(
            'guid' => $uploaded['url'],
            'post_mime_type' => $file_check['type'],
            'post_title' => sanitize_text_field(pathinfo($filename, PATHINFO_FILENAME)),
            'post_content' => '',
            'post_status' => 'inherit',
        );

        $attachment_id = wp_insert_attachment($attachment, $filename);
        if (is_wp_error($attachment_id)) {
            if (file_exists($filename)) {
                wp_delete_file($filename);
            }
            wp_send_json_error(array('message' => $attachment_id->get_error_message()));
            return;
        }

        $metadata = wp_generate_attachment_metadata($attachment_id, $filename);
        if (!is_wp_error($metadata)) {
            wp_update_attachment_metadata($attachment_id, $metadata);
        }

        $update = MjMembers::update($member_id, array('photo_id' => $attachment_id));
        if (is_wp_error($update)) {
            wp_delete_attachment($attachment_id, true);
            wp_send_json_error(array('message' => $update->get_error_message()));
            return;
        }

        $avatar_url = $this->getMemberAvatarUrl($member_id);

        wp_send_json_success(array(
            'message' => __('Photo de profil mise à jour.', 'mj-member'),
            'photoId' => $attachment_id,
            'avatarUrl' => $avatar_url,
        ));
    }

    /**
     * Update a member photo (caption or status)
     */
    public function updateMemberPhoto() {
        $auth = $this->verifyRequest();
        if (!$auth) {
            return;
        }

        $photo_id = isset($_POST['photoId']) ? (int) $_POST['photoId'] : 0;
        $member_id = isset($_POST['memberId']) ? (int) $_POST['memberId'] : 0;

        if ($photo_id <= 0 || $member_id <= 0) {
            wp_send_json_error(array('message' => __('Identifiant de photo ou de membre invalide.', 'mj-member')));
            return;
        }

        $payload = isset($_POST['data']) ? $_POST['data'] : array();
        if (is_string($payload)) {
            $decoded = json_decode(stripslashes($payload), true);
            $payload = is_array($decoded) ? $decoded : array();
        }

        if (empty($payload) || !is_array($payload)) {
            wp_send_json_error(array('message' => __('Aucune donnée fournie pour la mise à jour.', 'mj-member')));
            return;
        }

        $photo = MjEventPhotos::get($photo_id);
        if (!$photo) {
            wp_send_json_error(array('message' => __('Photo introuvable.', 'mj-member')));
            return;
        }

        if ((int) ($photo->member_id ?? 0) !== $member_id) {
            wp_send_json_error(array('message' => __('Cette photo n\'est pas associée à ce membre.', 'mj-member')));
            return;
        }

        $update = array();

        if (array_key_exists('caption', $payload)) {
            $caption = sanitize_textarea_field(wp_unslash((string) $payload['caption']));
            $update['caption'] = $caption;
        }

        if (array_key_exists('status', $payload)) {
            $status = sanitize_key($payload['status']);
            $status_labels = MjEventPhotos::get_status_labels();
            if (!array_key_exists($status, $status_labels)) {
                wp_send_json_error(array('message' => __('Statut photo invalide.', 'mj-member')));
                return;
            }
            // Utiliser update_status pour déclencher le hook de notification
            $status_result = MjEventPhotos::update_status($photo_id, $status, array(
                'reviewed_at' => current_time('mysql'),
                'reviewed_by' => get_current_user_id(),
            ));
            if (is_wp_error($status_result)) {
                wp_send_json_error(array('message' => $status_result->get_error_message()));
                return;
            }
        }

        if (empty($update)) {
            // Si seul le statut a été mis à jour, on continue quand même
            if (!array_key_exists('status', $payload)) {
                wp_send_json_error(array('message' => __('Aucune donnée à mettre à jour.', 'mj-member')));
                return;
            }
        } else {
            $result = MjEventPhotos::update($photo_id, $update);
            if (is_wp_error($result)) {
                wp_send_json_error(array('message' => $result->get_error_message()));
                return;
            }
        }

        $updated = MjEventPhotos::get($photo_id);

        $photo_payload = array('id' => $photo_id);
        if ($updated) {
            $attachment_id = isset($updated->attachment_id) ? (int) $updated->attachment_id : 0;
            $thumbnail = $attachment_id > 0 ? wp_get_attachment_image_url($attachment_id, 'medium') : '';
            if (!$thumbnail && $attachment_id > 0) {
                $thumbnail = wp_get_attachment_url($attachment_id);
            }
            $full = $attachment_id > 0 ? wp_get_attachment_image_url($attachment_id, 'large') : '';
            if (!$full && $attachment_id > 0) {
                $full = wp_get_attachment_url($attachment_id);
            }

            $event_title = '';
            if (!empty($updated->event_id)) {
                $event = MjEvents::find((int) $updated->event_id);
                if ($event) {
                    $event_title = isset($event->title) ? (string) $event->title : '';
                }
            }

            $status_labels = MjEventPhotos::get_status_labels();
            $status_key = isset($updated->status) ? (string) $updated->status : MjEventPhotos::STATUS_APPROVED;

            $photo_payload = array(
                'id' => (int) $updated->id,
                'eventId' => isset($updated->event_id) ? (int) $updated->event_id : 0,
                'eventTitle' => $event_title,
                'caption' => isset($updated->caption) ? (string) $updated->caption : '',
                'status' => $status_key,
                'statusLabel' => isset($status_labels[$status_key]) ? (string) $status_labels[$status_key] : $status_key,
                'thumbnailUrl' => $thumbnail ?: '',
                'fullUrl' => $full ?: $thumbnail ?: '',
                'createdAt' => isset($updated->created_at) ? (string) $updated->created_at : '',
            );
        }

        wp_send_json_success(array(
            'message' => __('Photo mise à jour.', 'mj-member'),
            'photo' => $photo_payload,
        ));
    }

    /**
     * Delete a member photo
     */
    public function deleteMemberPhoto() {
        $auth = $this->verifyRequest();
        if (!$auth) {
            return;
        }

        // Debug: log received POST data
        error_log('[MJ-Member] delete_member_photo POST: ' . print_r($_POST, true));

        $photo_id = isset($_POST['photoId']) ? (int) $_POST['photoId'] : 0;
        $member_id = isset($_POST['memberId']) ? (int) $_POST['memberId'] : 0;

        if ($photo_id <= 0) {
            wp_send_json_error(array('message' => __('Identifiant de photo invalide.', 'mj-member')));
            return;
        }

        if ($member_id <= 0) {
            wp_send_json_error(array('message' => __('Identifiant de membre invalide.', 'mj-member')));
            return;
        }

        $photo = MjEventPhotos::get($photo_id);
        if (!$photo) {
            wp_send_json_error(array('message' => __('Photo introuvable.', 'mj-member')));
            return;
        }

        if ((int) ($photo->member_id ?? 0) !== $member_id) {
            wp_send_json_error(array('message' => __('Cette photo n\'est pas associée à ce membre.', 'mj-member')));
            return;
        }

        $result = MjEventPhotos::delete($photo_id);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
            return;
        }

        wp_send_json_success(array(
            'message' => __('Photo supprimée.', 'mj-member'),
            'photoId' => $photo_id,
        ));
    }

    /**
     * Delete a member contact message
     */
    public function createMemberMessage() {
        $auth = $this->verifyRequest();
        if (!$auth) {
            return;
        }

        $member_id = isset($_POST['memberId']) ? (int) $_POST['memberId'] : 0;
        $subject   = isset($_POST['subject']) ? sanitize_text_field(wp_unslash($_POST['subject'])) : '';
        $message   = isset($_POST['message']) ? wp_kses_post(wp_unslash($_POST['message'])) : '';

        if ($member_id <= 0) {
            wp_send_json_error(array('message' => __('Identifiant de membre invalide.', 'mj-member')));
            return;
        }

        if ($subject === '' || $message === '') {
            wp_send_json_error(array('message' => __('Le sujet et le message sont requis.', 'mj-member')));
            return;
        }

        $member = MjMembers::getById($member_id);
        if (!$member) {
            wp_send_json_error(array('message' => __('Membre introuvable.', 'mj-member')));
            return;
        }

        $current_user = wp_get_current_user();
        $sender_name  = $current_user->display_name ?: $current_user->user_login;
        $sender_email = $current_user->user_email;

        $result = MjContactMessages::create(array(
            'sender_name'  => $sender_name,
            'sender_email' => $sender_email,
            'subject'      => $subject,
            'message'      => $message,
            'target_type'  => MjContactMessages::TARGET_MEMBER,
            'target_reference' => $member_id,
            'target_label' => trim(($member->first_name ?? '') . ' ' . ($member->last_name ?? '')),
            'status'       => MjContactMessages::STATUS_NEW,
            'is_read'      => 0,
            'assigned_to'  => 0,
            'meta'         => array('member_id' => $member_id),
            'user_id'      => $current_user->ID,
        ));

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
            return;
        }

        MjContactMessages::record_activity((int) $result, 'created', array(
            'note'    => __('Message créé depuis le gestionnaire.', 'mj-member'),
            'user_id' => $current_user->ID,
        ));

        wp_send_json_success(array(
            'message'   => __('Message créé avec succès.', 'mj-member'),
            'messageId' => (int) $result,
        ));
    }

    public function deleteMemberMessage() {
        $auth = $this->verifyRequest();
        if (!$auth) {
            return;
        }

        $message_id = isset($_POST['messageId']) ? (int) $_POST['messageId'] : 0;
        $member_id = isset($_POST['memberId']) ? (int) $_POST['memberId'] : 0;

        if ($message_id <= 0 || $member_id <= 0) {
            wp_send_json_error(array('message' => __('Identifiant de message ou de membre invalide.', 'mj-member')));
            return;
        }

        $message = MjContactMessages::get($message_id);
        if (!$message) {
            wp_send_json_error(array('message' => __('Message introuvable.', 'mj-member')));
            return;
        }

        $member = MjMembers::getById($member_id);
        if (!$member) {
            wp_send_json_error(array('message' => __('Membre introuvable.', 'mj-member')));
            return;
        }

        $linked_member_id = 0;
        if (!empty($message->meta)) {
            $meta = json_decode($message->meta, true);
            if (is_array($meta)) {
                if (isset($meta['member_id'])) {
                    $linked_member_id = (int) $meta['member_id'];
                } elseif (isset($meta['member']['id'])) {
                    $linked_member_id = (int) $meta['member']['id'];
                }
            }
            if ($linked_member_id === 0 && is_string($message->meta)) {
                if (preg_match('/"member_id"\s*:\s*"?(\d+)/', $message->meta, $matches)) {
                    $linked_member_id = (int) $matches[1];
                }
            }
        }

        $target_reference = isset($message->target_reference) ? (int) $message->target_reference : 0;
        $sender_email = isset($message->sender_email) ? (string) $message->sender_email : '';
        $member_email = isset($member->email) ? (string) $member->email : '';

        $belongs_to_member = false;
        if ($linked_member_id === $member_id) {
            $belongs_to_member = true;
        } elseif ($target_reference === $member_id) {
            $belongs_to_member = true;
        } elseif ($member_email !== '' && $sender_email !== '' && strcasecmp($member_email, $sender_email) === 0) {
            $belongs_to_member = true;
        }

        if (!$belongs_to_member) {
            wp_send_json_error(array('message' => __('Ce message n\'est pas associé à ce membre.', 'mj-member')));
            return;
        }

        MjContactMessages::record_activity($message_id, 'deleted', array(
            'user_id' => get_current_user_id(),
        ));

        $result = MjContactMessages::delete($message_id);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
            return;
        }

        wp_send_json_success(array(
            'message' => __('Message supprimé.', 'mj-member'),
            'messageId' => $message_id,
        ));
    }

    /**
     * Get member's registration history
     */
    public function getMemberRegistrations() {
        $current_member = $this->verifyRequest();
        if (!$current_member) return;

        $member_id = isset($_POST['memberId']) ? absint($_POST['memberId']) : 0;
        if (!$member_id) {
            wp_send_json_error(array('message' => __('ID du membre manquant.', 'mj-member')));
            return;
        }

        // Get registrations for this member
        $registrations_data = MjEventRegistrations::get_all(array(
            'member_id' => $member_id,
            'limit' => 50,
            'orderby' => 'created_at',
            'order' => 'DESC',
        ));

        $status_labels = MjEventRegistrations::get_status_labels();

        $events_cache = array();
        $event_cover_cache = array();
        $occurrence_cache = array();
        $now_timestamp = current_time('timestamp');

        $format_occurrence_label = static function ($start_value, $end_value = null) {
            $start_value = (string) $start_value;
            if ($start_value === '') {
                return '';
            }

            $start_ts = strtotime($start_value);
            if ($start_ts === false) {
                return $start_value;
            }

            $date_format = get_option('date_format', 'd/m/Y');
            $time_format = get_option('time_format', 'H:i');

            $label = date_i18n($date_format, $start_ts) . ' - ' . date_i18n($time_format, $start_ts);

            if ($end_value) {
                $end_ts = strtotime((string) $end_value);
                if ($end_ts !== false && $end_ts > $start_ts) {
                    if (date_i18n('Ymd', $start_ts) === date_i18n('Ymd', $end_ts)) {
                        $label .= ' -> ' . date_i18n($time_format, $end_ts);
                    } else {
                        $label .= ' -> ' . date_i18n($date_format, $end_ts);
                    }
                }
            }

            return $label;
        };

        $registrations = array();
        foreach ($registrations_data as $reg) {
            if (!$reg) {
                continue;
            }

            $event_id = isset($reg->event_id) ? (int) $reg->event_id : 0;

            if ($event_id > 0 && !array_key_exists($event_id, $events_cache)) {
                $event = MjEvents::find($event_id);
                $events_cache[$event_id] = $event ? $event : null;
            }

            $event = ($event_id > 0 && isset($events_cache[$event_id])) ? $events_cache[$event_id] : null;

            if ($event_id > 0 && !array_key_exists($event_id, $event_cover_cache)) {
                $cover_payload = array(
                    'id' => 0,
                    'url' => '',
                    'alt' => '',
                );

                if ($event && !empty($event->cover_id)) {
                    $cover_id = (int) $event->cover_id;
                    if ($cover_id > 0) {
                        $cover_payload['id'] = $cover_id;

                        $cover_source = wp_get_attachment_image_src($cover_id, 'large');
                        if ($cover_source) {
                            $cover_payload['url'] = esc_url_raw($cover_source[0]);
                        } else {
                            $cover_fallback = wp_get_attachment_image_src($cover_id, 'medium');
                            if ($cover_fallback) {
                                $cover_payload['url'] = esc_url_raw($cover_fallback[0]);
                            }
                        }

                        $alt_meta = get_post_meta($cover_id, '_wp_attachment_image_alt', true);
                        if (is_string($alt_meta) && trim($alt_meta) !== '') {
                            $cover_payload['alt'] = sanitize_text_field($alt_meta);
                        } elseif ($event && !empty($event->title)) {
                            $cover_payload['alt'] = sanitize_text_field((string) $event->title);
                        }
                    }
                }

                if ($cover_payload['url'] === '' && $event && !empty($event->article_id)) {
                    $article_id = (int) $event->article_id;
                    if ($article_id > 0) {
                        $article_thumb_id = get_post_thumbnail_id($article_id);
                        if ($article_thumb_id) {
                            $cover_source = wp_get_attachment_image_src($article_thumb_id, 'large');
                            if ($cover_source) {
                                $cover_payload['id'] = (int) $article_thumb_id;
                                $cover_payload['url'] = esc_url_raw($cover_source[0]);
                            } else {
                                $cover_fallback = wp_get_attachment_image_src($article_thumb_id, 'medium');
                                if ($cover_fallback) {
                                    $cover_payload['id'] = (int) $article_thumb_id;
                                    $cover_payload['url'] = esc_url_raw($cover_fallback[0]);
                                }
                            }

                            if ($cover_payload['alt'] === '') {
                                $alt_meta = get_post_meta($article_thumb_id, '_wp_attachment_image_alt', true);
                                if (is_string($alt_meta) && trim($alt_meta) !== '') {
                                    $cover_payload['alt'] = sanitize_text_field($alt_meta);
                                } elseif ($event && !empty($event->title)) {
                                    $cover_payload['alt'] = sanitize_text_field((string) $event->title);
                                }
                            }
                        }
                    }
                }

                $event_cover_cache[$event_id] = $cover_payload;
            }

            if (!array_key_exists($event_id, $occurrence_cache)) {
                $event_occurrences = array();

                $event = ($event_id > 0 && isset($events_cache[$event_id])) ? $events_cache[$event_id] : null;

                if ($event && class_exists(MjEventSchedule::class)) {
                    $occurrences = MjEventSchedule::get_occurrences($event, array(
                        'include_past' => true,
                        'max' => 200,
                    ));

                    if (is_array($occurrences)) {
                        foreach ($occurrences as $occurrence) {
                            if (!is_array($occurrence)) {
                                continue;
                            }

                            $start_value = isset($occurrence['start']) ? $occurrence['start'] : '';
                            $normalized_start = MjEventAttendance::normalize_occurrence($start_value);
                            if ($normalized_start === '') {
                                continue;
                            }

                            $end_value = isset($occurrence['end']) ? (string) $occurrence['end'] : null;
                            $label_value = isset($occurrence['label']) ? (string) $occurrence['label'] : $format_occurrence_label($normalized_start, $end_value);
                            $is_past = isset($occurrence['is_past']) ? (bool) $occurrence['is_past'] : (strtotime($normalized_start) < $now_timestamp);

                            $event_occurrences[$normalized_start] = array(
                                'start' => $normalized_start,
                                'end' => $end_value,
                                'label' => $label_value,
                                'isPast' => $is_past,
                            );
                        }
                    }
                }

                $occurrence_cache[$event_id] = $event_occurrences;
            }

            $event_cover = isset($event_cover_cache[$event_id]) ? $event_cover_cache[$event_id] : array('id' => 0, 'url' => '', 'alt' => '');
            $event = ($event_id > 0 && isset($events_cache[$event_id])) ? $events_cache[$event_id] : null;
            $event_title = $event ? $event->title : __('Événement supprimé', 'mj-member');

            $event_date = null;
            if ($event) {
                if (!empty($event->start_date)) {
                    $event_date = $event->start_date;
                } elseif (!empty($event->date_debut)) {
                    $event_date = $event->date_debut;
                }
            }

            $assignments_raw = MjEventAttendance::get_registration_assignments($reg);
            $assignment_mode = isset($assignments_raw['mode']) ? sanitize_key((string) $assignments_raw['mode']) : 'all';
            if ($assignment_mode !== 'custom') {
                $assignment_mode = 'all';
            }

            $assigned_occurrences = array();
            if ($assignment_mode === 'custom' && !empty($assignments_raw['occurrences']) && is_array($assignments_raw['occurrences'])) {
                foreach ($assignments_raw['occurrences'] as $candidate) {
                    $normalized_candidate = MjEventAttendance::normalize_occurrence($candidate);
                    if ($normalized_candidate === '' || in_array($normalized_candidate, $assigned_occurrences, true)) {
                        continue;
                    }
                    $assigned_occurrences[] = $normalized_candidate;
                }
            }

            $event_occurrence_map = ($event_id > 0 && isset($occurrence_cache[$event_id])) ? $occurrence_cache[$event_id] : array();

            $sessions = array();
            if ($assignment_mode === 'custom' && !empty($assigned_occurrences)) {
                foreach ($assigned_occurrences as $occurrence_key) {
                    if (isset($event_occurrence_map[$occurrence_key])) {
                        $sessions[] = $event_occurrence_map[$occurrence_key];
                    } else {
                        $sessions[] = array(
                            'start' => $occurrence_key,
                            'end' => null,
                            'label' => $format_occurrence_label($occurrence_key),
                            'isPast' => (strtotime($occurrence_key) < $now_timestamp),
                        );
                    }
                }
            } elseif (!empty($event_occurrence_map)) {
                $sessions = array_values($event_occurrence_map);
            }

            $status_key = isset($reg->statut) ? (string) $reg->statut : (isset($reg->status) ? (string) $reg->status : 'en_attente');
            if ($status_key === '') {
                $status_key = 'en_attente';
            }

            $registrations[] = array(
                'id' => isset($reg->id) ? (int) $reg->id : 0,
                'eventId' => $event_id,
                'eventTitle' => $event_title,
                'eventCover' => $event_cover,
                'status' => $status_key,
                'statusLabel' => isset($status_labels[$status_key]) ? $status_labels[$status_key] : $status_key,
                'createdAt' => isset($reg->created_at) ? $reg->created_at : null,
                'eventDate' => $event_date,
                'occurrenceAssignments' => array(
                    'mode' => $assignment_mode,
                    'occurrences' => $assigned_occurrences,
                ),
                'occurrenceDetails' => $sessions,
                'allOccurrences' => array_values($event_occurrence_map),
                'coversAllOccurrences' => ($assignment_mode !== 'custom'),
                'totalOccurrences' => count($event_occurrence_map),
                'canEditOccurrences' => count($event_occurrence_map) > 1,
            );
        }

        wp_send_json_success(array('registrations' => $registrations));
    }

    /**
     * Publish an event to Facebook, Instagram, or WhatsApp
     *
     * POST params:
     *   eventId    (int)    – ID of the event.
     *   platform   (string) – 'facebook', 'instagram', or 'whatsapp'.
     *   message    (string) – Message/caption to publish.
     *   imageUrl   (string) – Optional image URL for Instagram.
     */
    /**
     * Mark member's membership as paid
     */
    public function markMembershipPaid() {
        $current_member = $this->verifyRequest();
        if (!$current_member) return;

        $member_id = isset($_POST['memberId']) ? absint($_POST['memberId']) : 0;
        $payment_method = isset($_POST['paymentMethod']) ? sanitize_text_field($_POST['paymentMethod']) : 'cash';
        $year = isset($_POST['year']) ? absint($_POST['year']) : (int) date('Y');

        if (!$member_id) {
            wp_send_json_error(array('message' => __('ID du membre manquant.', 'mj-member')));
            return;
        }

        // Validate year
        $current_year = (int) date('Y');
        if ($year < $current_year - 1 || $year > $current_year + 1) {
            wp_send_json_error(array('message' => __('Année invalide.', 'mj-member')));
            return;
        }

        // Update member's membership paid year
        $update_data = array(
            'membership_paid_year' => (string) $year,
            'date_last_payement' => current_time('mysql'),
        );

        $result = MjMembers::update($member_id, $update_data);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
            return;
        }

        // Log the payment (optional - add a note)
        $payment_method_labels = array(
            'cash' => __('espèces', 'mj-member'),
            'check' => __('chèque', 'mj-member'),
            'transfer' => __('virement', 'mj-member'),
            'card' => __('carte bancaire', 'mj-member'),
        );

        $author = $current_member['member'];
        $author_name = trim(($author->first_name ?? '') . ' ' . ($author->last_name ?? ''));
        $method_label = $payment_method_labels[$payment_method] ?? $payment_method;

        // Create a note for the payment
        $this->createNotesTableIfNotExists();

        global $wpdb;
        $notes_table = $wpdb->prefix . 'mj_member_notes';

        $wpdb->insert($notes_table, array(
            'member_id' => $member_id,
            'author_id' => $current_member['member_id'],
            'content' => sprintf(
                __('Cotisation %d payée (%s) - enregistré par %s', 'mj-member'),
                $year,
                $method_label,
                $author_name
            ),
            'created_at' => current_time('mysql'),
        ), array('%d', '%d', '%s', '%s'));

        // Attribuer le trophée "Cotisation réglée"
        MjTrophyService::assignBySlug($member_id, MjTrophyService::MEMBERSHIP_PAID, array(
            'notes' => sprintf(__('Cotisation %d - %s', 'mj-member'), $year, $method_label),
        ));

        wp_send_json_success(array(
            'message' => sprintf(__('Cotisation %d enregistrée avec succès.', 'mj-member'), $year),
            'membershipYear' => $year,
            'membershipStatus' => 'paid',
        ));
    }

    /**
     * Create a Stripe payment link for membership
     */
    public function createMembershipPaymentLink() {
        $current_member = $this->verifyRequest();
        if (!$current_member) return;

        $member_id = isset($_POST['memberId']) ? absint($_POST['memberId']) : 0;

        if (!$member_id) {
            wp_send_json_error(array('message' => __('ID du membre manquant.', 'mj-member')));
            return;
        }

        // Get member data
        $member = MjMembers::getById($member_id);
        if (!$member) {
            wp_send_json_error(array('message' => __('Membre introuvable.', 'mj-member')));
            return;
        }

        // Check if MjPayments class exists
        if (!class_exists('Mj\Member\Classes\MjPayments')) {
            wp_send_json_error(array('message' => __('Module de paiement non disponible.', 'mj-member')));
            return;
        }

        // Get the membership amount
        $amount = (float) apply_filters('mj_member_membership_amount', (float) get_option('mj_annual_fee', '2.00'), $member);

        if ($amount <= 0) {
            wp_send_json_error(array('message' => __('Montant de cotisation invalide.', 'mj-member')));
            return;
        }

        // Create Stripe payment
        try {
            $payment = \Mj\Member\Classes\MjPayments::create_stripe_payment($member_id, $amount);

            if (!$payment || empty($payment['checkout_url'])) {
                wp_send_json_error(array('message' => __('Impossible de créer le lien de paiement Stripe.', 'mj-member')));
                return;
            }

            wp_send_json_success(array(
                'checkoutUrl' => $payment['checkout_url'],
                'qrUrl' => $payment['qr_url'] ?? null,
                'paymentId' => $payment['payment_id'] ?? null,
                'amount' => $amount,
            ));
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * Build actions payload for a member within the registration manager.
     *
     * @param int $member_id
     * @return array<int,array<string,mixed>>
     */
    private function getMemberActionsPayload($member_id) {
        $member_id = (int) $member_id;
        if ($member_id <= 0) {
            return array();
        }

        // Get all active action types with counts for this member
        $actions = MjMemberActions::get_counts_for_member($member_id);

        if (empty($actions)) {
            return array();
        }

        $payload = array();
        foreach ($actions as $action) {
            $action_id = isset($action['id']) ? (int) $action['id'] : 0;
            if ($action_id <= 0) {
                continue;
            }

            $is_auto = !empty($action['attribution']) && $action['attribution'] === MjActionTypes::ATTRIBUTION_AUTO;

            $payload[] = array(
                'id' => $action_id,
                'slug' => isset($action['slug']) ? (string) $action['slug'] : '',
                'title' => isset($action['title']) ? (string) $action['title'] : '',
                'description' => isset($action['description']) ? (string) $action['description'] : '',
                'emoji' => isset($action['emoji']) ? (string) $action['emoji'] : '',
                'category' => isset($action['category']) ? (string) $action['category'] : '',
                'categoryLabel' => isset($action['categoryLabel']) ? (string) $action['categoryLabel'] : '',
                'attribution' => isset($action['attribution']) ? (string) $action['attribution'] : 'manual',
                'xp' => isset($action['xp']) ? (int) $action['xp'] : 0,
                'coins' => isset($action['coins']) ? (int) $action['coins'] : 0,
                'count' => isset($action['count']) ? (int) $action['count'] : 0,
                'isAuto' => $is_auto,
                'canAward' => !$is_auto,
            );
        }

        return $payload;
    }

    /**
     * Build trophies payload for a member within the registration manager.
     *
     * @param int $member_id
     * @return array<int,array<string,mixed>>
     */
    private function getMemberTrophiesPayload($member_id) {
        $member_id = (int) $member_id;
        if ($member_id <= 0) {
            return array();
        }

        $trophies = MjTrophies::get_all(array(
            'status' => MjTrophies::STATUS_ACTIVE,
            'orderby' => 'display_order',
            'order' => 'ASC',
        ));

        if (empty($trophies)) {
            return array();
        }

        // Get member's trophy assignments
        $assignments = MjMemberTrophies::get_all(array(
            'member_id' => $member_id,
        ));

        $assignment_map = array();
        if (!empty($assignments)) {
            foreach ($assignments as $assignment) {
                $trophy_id = isset($assignment['trophy_id']) ? (int) $assignment['trophy_id'] : 0;
                if ($trophy_id > 0) {
                    $assignment_map[$trophy_id] = $assignment;
                }
            }
        }

        $payload = array();
        foreach ($trophies as $trophy) {
            $trophy_id = isset($trophy['id']) ? (int) $trophy['id'] : 0;
            if ($trophy_id <= 0) {
                continue;
            }

            $assignment = isset($assignment_map[$trophy_id]) ? $assignment_map[$trophy_id] : null;
            $assignment_status = '';
            $awarded_at = '';

            if (is_array($assignment)) {
                $assignment_status = isset($assignment['status']) ? (string) $assignment['status'] : '';
                $awarded_at = isset($assignment['awarded_at']) ? (string) $assignment['awarded_at'] : '';
            }

            $image_id = isset($trophy['image_id']) ? (int) $trophy['image_id'] : 0;
            $image_url = '';
            if ($image_id > 0) {
                $image_url = wp_get_attachment_image_url($image_id, 'medium');
                if (!$image_url) {
                    $image_url = wp_get_attachment_url($image_id);
                }
            }

            $is_auto = !empty($trophy['auto_mode']);
            $is_awarded = $assignment_status === MjMemberTrophies::STATUS_AWARDED;

            $payload[] = array(
                'id' => $trophy_id,
                'title' => isset($trophy['title']) ? (string) $trophy['title'] : '',
                'description' => isset($trophy['description']) ? (string) $trophy['description'] : '',
                'xp' => isset($trophy['xp']) ? (int) $trophy['xp'] : 0,
                'coins' => isset($trophy['coins']) ? (int) $trophy['coins'] : 0,
                'imageId' => $image_id,
                'imageUrl' => $image_url,
                'autoMode' => $is_auto,
                'awarded' => $is_awarded,
                'awardedAt' => $awarded_at,
                'canToggle' => !$is_auto,
            );
        }

        return $payload;
    }

    /**
     * Toggle a trophy for a member (manual trophies only)
     */
    public function toggleMemberTrophy() {
        $current_member = $this->verifyRequest();
        if (!$current_member) return;

        $member_id = isset($_POST['memberId']) ? absint($_POST['memberId']) : 0;
        $trophy_id = isset($_POST['trophyId']) ? absint($_POST['trophyId']) : 0;
        $awarded = isset($_POST['awarded']) && $_POST['awarded'] === 'true';

        if ($member_id <= 0 || $trophy_id <= 0) {
            wp_send_json_error(array('message' => __('Paramètres invalides.', 'mj-member')));
            return;
        }

        $target_member = MjMembers::getById($member_id);
        if (!$target_member) {
            wp_send_json_error(array('message' => __('Membre introuvable.', 'mj-member')));
            return;
        }

        $trophy = MjTrophies::get($trophy_id);
        if (!$trophy || (isset($trophy['status']) && $trophy['status'] === MjTrophies::STATUS_ARCHIVED)) {
            wp_send_json_error(array('message' => __('Trophée introuvable ou archivé.', 'mj-member')));
            return;
        }

        // Check if trophy is manual (not auto_mode)
        if (!empty($trophy['auto_mode'])) {
            wp_send_json_error(array('message' => __('Ce trophée est automatique et ne peut pas être attribué manuellement.', 'mj-member')));
            return;
        }

        if ($awarded) {
            $result = MjMemberTrophies::award($member_id, $trophy_id);
        } else {
            $result = MjMemberTrophies::revoke($member_id, $trophy_id);
        }

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
            return;
        }

        // Build updated trophy entry
        $updated_trophy = $this->getMemberTrophiesPayload($member_id);
        $updated_entry = null;
        foreach ($updated_trophy as $entry) {
            if ((int) $entry['id'] === $trophy_id) {
                $updated_entry = $entry;
                break;
            }
        }

        wp_send_json_success(array(
            'trophy' => $updated_entry,
            'message' => $awarded
                ? __('Trophée attribué.', 'mj-member')
                : __('Trophée retiré.', 'mj-member'),
        ));
    }

    /**
     * Award an action to a member
     */
    public function awardMemberAction() {
        $current_member = $this->verifyRequest();
        if (!$current_member) return;

        $member_id = isset($_POST['memberId']) ? absint($_POST['memberId']) : 0;
        $action_type_id = isset($_POST['actionTypeId']) ? absint($_POST['actionTypeId']) : 0;
        $notes = isset($_POST['notes']) ? sanitize_textarea_field($_POST['notes']) : '';

        if ($member_id <= 0 || $action_type_id <= 0) {
            wp_send_json_error(array('message' => __('Paramètres invalides.', 'mj-member')));
            return;
        }

        $target_member = MjMembers::getById($member_id);
        if (!$target_member) {
            wp_send_json_error(array('message' => __('Membre introuvable.', 'mj-member')));
            return;
        }

        $action_type = MjActionTypes::get($action_type_id);
        if (!$action_type || (isset($action_type['status']) && $action_type['status'] === MjActionTypes::STATUS_ARCHIVED)) {
            wp_send_json_error(array('message' => __('Type d\'action introuvable ou archivé.', 'mj-member')));
            return;
        }

        // Only manual actions can be awarded through this endpoint
        if (!empty($action_type['attribution']) && $action_type['attribution'] === MjActionTypes::ATTRIBUTION_AUTO) {
            wp_send_json_error(array('message' => __('Cette action est automatique et ne peut pas être attribuée manuellement.', 'mj-member')));
            return;
        }

        $awarded_by = get_current_user_id();
        $result = MjMemberActions::award($member_id, $action_type_id, $awarded_by, $notes);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
            return;
        }

        // Build updated actions payload
        $updated_actions = $this->getMemberActionsPayload($member_id);
        $updated_entry = null;
        foreach ($updated_actions as $entry) {
            if ((int) $entry['id'] === $action_type_id) {
                $updated_entry = $entry;
                break;
            }
        }

        // Get updated XP/coins
        $xp = MjMemberXp::get($member_id);
        $coins = MjMemberCoins::get($member_id);

        wp_send_json_success(array(
            'action' => $updated_entry,
            'xp' => $xp,
            'coins' => $coins,
            'message' => sprintf(
                __('Action « %s » attribuée.', 'mj-member'),
                $action_type['title'] ?? ''
            ),
        ));
    }

    /**
     * Get photos for an event
     */
    public function getEventPhotos() {
        $member = $this->verifyRequest();
        if (!$member) {
            return;
        }

        $event_id = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;
        if ($event_id <= 0) {
            wp_send_json_error(array('message' => __('Événement invalide.', 'mj-member')));
            return;
        }

        $photo_status_labels = MjEventPhotos::get_status_labels();

        $photos = MjEventPhotos::query(array(
            'event_id' => $event_id,
            'status' => array(MjEventPhotos::STATUS_PENDING, MjEventPhotos::STATUS_APPROVED),
            'per_page' => 50,
            'paged' => 1,
            'orderby' => 'created_at',
            'order' => 'DESC',
        ));

        $result = array();
        if (!empty($photos)) {
            foreach ($photos as $photo) {
                $attachment_id = isset($photo->attachment_id) ? (int) $photo->attachment_id : 0;
                if ($attachment_id <= 0) {
                    continue;
                }

                $thumbnail = wp_get_attachment_image_url($attachment_id, 'medium');
                if (!$thumbnail) {
                    $thumbnail = wp_get_attachment_url($attachment_id);
                }

                $full = wp_get_attachment_image_url($attachment_id, 'large');
                if (!$full) {
                    $full = wp_get_attachment_url($attachment_id);
                }

                // Get member info
                $memberName = '';
                $memberAvatar = '';
                $memberId = isset($photo->member_id) ? (int) $photo->member_id : 0;
                if ($memberId > 0) {
                    $photoMember = MjMembers::getById($memberId);
                    if ($photoMember) {
                        $memberName = trim(($photoMember->first_name ?? '') . ' ' . ($photoMember->last_name ?? ''));
                        $memberAvatar = $this->getMemberAvatarUrl($memberId);
                    }
                }

                $status_key = isset($photo->status) ? (string) $photo->status : MjEventPhotos::STATUS_APPROVED;

                $result[] = array(
                    'id' => (int) $photo->id,
                    'memberId' => $memberId,
                    'memberName' => $memberName,
                    'memberAvatar' => $memberAvatar,
                    'caption' => isset($photo->caption) ? (string) $photo->caption : '',
                    'thumbnailUrl' => $thumbnail ?: '',
                    'fullUrl' => $full ?: $thumbnail ?: '',
                    'createdAt' => isset($photo->created_at) ? (string) $photo->created_at : '',
                    'status' => $status_key,
                    'statusLabel' => isset($photo_status_labels[$status_key]) ? (string) $photo_status_labels[$status_key] : $status_key,
                );
            }
        }

        wp_send_json_success(array('photos' => $result));
    }

    /**
     * Upload a new photo for an event
     */
    public function uploadEventPhoto() {
        $auth = $this->verifyRequest();
        if (!$auth) {
            return;
        }

        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => __('Vous ne pouvez pas gérer la médiathèque.', 'mj-member')));
            return;
        }

        $event_id = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;
        if ($event_id <= 0) {
            wp_send_json_error(array('message' => __('Événement invalide.', 'mj-member')));
            return;
        }

        $event = MjEvents::find($event_id);
        if (!$event) {
            wp_send_json_error(array('message' => __('Événement introuvable.', 'mj-member')));
            return;
        }

        // Check permission: must be coordinateur or have manage capability
        $can_upload = !empty($auth['is_coordinateur']) || !empty($auth['is_animateur']) || current_user_can(Config::capability());
        if (!$can_upload) {
            wp_send_json_error(array('message' => __('Accès refusé pour ajouter une photo.', 'mj-member')), 403);
            return;
        }

        if (!isset($_FILES['photo'])) {
            wp_send_json_error(array('message' => __('Aucune photo reçue.', 'mj-member')));
            return;
        }

        $file = $_FILES['photo'];
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            wp_send_json_error(array('message' => __('Le fichier envoyé est invalide.', 'mj-member')));
            return;
        }

        $file_check = wp_check_filetype_and_ext($file['tmp_name'], $file['name']);
        if (empty($file_check['type']) || strpos($file_check['type'], 'image/') !== 0) {
            wp_send_json_error(array('message' => __('Le fichier sélectionné n\'est pas une image.', 'mj-member')));
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $upload_overrides = array(
            'test_form' => false,
            'mimes' => array(
                'jpg|jpeg|jpe' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'webp' => 'image/webp',
            ),
        );

        $uploaded = wp_handle_upload($file, $upload_overrides);
        if (isset($uploaded['error'])) {
            wp_send_json_error(array('message' => $uploaded['error']));
            return;
        }

        $filename = $uploaded['file'];
        $attachment = array(
            'guid' => $uploaded['url'],
            'post_mime_type' => $file_check['type'],
            'post_title' => sanitize_text_field(pathinfo($filename, PATHINFO_FILENAME)),
            'post_content' => '',
            'post_status' => 'inherit',
        );

        $attachment_id = wp_insert_attachment($attachment, $filename);
        if (is_wp_error($attachment_id)) {
            if (file_exists($filename)) {
                wp_delete_file($filename);
            }
            wp_send_json_error(array('message' => $attachment_id->get_error_message()));
            return;
        }

        $metadata = wp_generate_attachment_metadata($attachment_id, $filename);
        if (!is_wp_error($metadata)) {
            wp_update_attachment_metadata($attachment_id, $metadata);
        }

        // Get member_id from auth
        $member_id = isset($auth['member_id']) ? (int) $auth['member_id'] : 0;
        $caption = isset($_POST['caption']) ? sanitize_text_field($_POST['caption']) : '';

        // Create the event photo record - auto approved for animateur/coordinateur
        $photo_id = MjEventPhotos::create(array(
            'event_id' => $event_id,
            'member_id' => $member_id,
            'attachment_id' => $attachment_id,
            'caption' => $caption,
            'status' => MjEventPhotos::STATUS_APPROVED,
            'reviewed_at' => current_time('mysql'),
            'reviewed_by' => get_current_user_id(),
        ));

        if (is_wp_error($photo_id)) {
            wp_delete_attachment($attachment_id, true);
            wp_send_json_error(array('message' => $photo_id->get_error_message()));
            return;
        }

        $thumbnail = wp_get_attachment_image_url($attachment_id, 'medium');
        if (!$thumbnail) {
            $thumbnail = wp_get_attachment_url($attachment_id);
        }
        $full = wp_get_attachment_image_url($attachment_id, 'large');
        if (!$full) {
            $full = wp_get_attachment_url($attachment_id);
        }

        $memberName = '';
        $memberAvatar = '';
        if ($member_id > 0) {
            $photoMember = MjMembers::getById($member_id);
            if ($photoMember) {
                $memberName = trim(($photoMember->first_name ?? '') . ' ' . ($photoMember->last_name ?? ''));
                $memberAvatar = $this->getMemberAvatarUrl($member_id);
            }
        }

        $status_labels = MjEventPhotos::get_status_labels();

        wp_send_json_success(array(
            'message' => __('Photo ajoutée avec succès.', 'mj-member'),
            'photo' => array(
                'id' => (int) $photo_id,
                'memberId' => $member_id,
                'memberName' => $memberName,
                'memberAvatar' => $memberAvatar,
                'caption' => $caption,
                'thumbnailUrl' => $thumbnail ?: '',
                'fullUrl' => $full ?: $thumbnail ?: '',
                'createdAt' => current_time('mysql'),
                'status' => MjEventPhotos::STATUS_APPROVED,
                'statusLabel' => $status_labels[MjEventPhotos::STATUS_APPROVED] ?? 'Validée',
            ),
        ));
    }

    /**
     * Save dynamic field values for a member from the gestionnaire.
     */
    public function saveMemberDynfields() {
        $current_member = $this->verifyRequest();
        if (!$current_member) return;

        $member_id = isset($_POST['member_id']) ? absint($_POST['member_id']) : 0;
        if (!$member_id) {
            wp_send_json_error(array('message' => __('ID du membre manquant.', 'mj-member')));
        }

        $raw_values = array();
        if (isset($_POST['values'])) {
            if (is_array($_POST['values'])) {
                $raw_values = $_POST['values'];
            } elseif (is_string($_POST['values'])) {
                $decoded = json_decode(wp_unslash($_POST['values']), true);
                if (is_array($decoded)) {
                    $raw_values = $decoded;
                }
            }
        }
        $fields_map = array();
        foreach (\Mj\Member\Classes\Crud\MjDynamicFields::getAll() as $df) {
            $fields_map[(int) $df->id] = $df;
        }

        $save = array();
        foreach ($raw_values as $entry) {
            $field_id = isset($entry['id']) ? absint($entry['id']) : 0;
            if (!$field_id || !isset($fields_map[$field_id])) continue;

            $df = $fields_map[$field_id];
            $raw = isset($entry['value']) ? $entry['value'] : '';

            if ($df->field_type === 'checklist') {
                // value arrives as JSON string from JS
                $arr = is_string($raw) ? json_decode($raw, true) : (is_array($raw) ? $raw : array());
                if (!is_array($arr)) $arr = array();
                $arr = array_map('sanitize_text_field', $arr);
                $save[$field_id] = wp_json_encode(array_values($arr));
            } else {
                $save[$field_id] = sanitize_text_field(wp_unslash((string) $raw));
            }
        }

        if (!empty($save)) {
            \Mj\Member\Classes\Crud\MjDynamicFieldValues::saveBulk($member_id, $save);
        }

        // Return fresh values
        $dyn_fields_all = \Mj\Member\Classes\Crud\MjDynamicFields::getAll();
        $dyn_vals = \Mj\Member\Classes\Crud\MjDynamicFieldValues::getByMemberKeyed($member_id);
        $dyndata = array();
        foreach ($dyn_fields_all as $df) {
            $dyndata[] = array(
                'id'          => (int) $df->id,
                'title'       => $df->title,
                'description' => $df->description ?? '',
                'type'        => $df->field_type,
                'value'       => isset($dyn_vals[(int) $df->id]) ? $dyn_vals[(int) $df->id] : '',
                'options'     => \Mj\Member\Classes\Crud\MjDynamicFields::decodeOptions($df->options_list),
                'allowOther'  => (bool) ($df->allow_other ?? 0),
                'otherLabel'  => $df->other_label ?? '',
                'isRequired'  => (bool) ($df->is_required ?? 0),
                'showInNotes' => (bool) ($df->show_in_notes ?? 0),
                'youthOnly'   => (bool) ($df->youth_only ?? 0),
            );
        }

        wp_send_json_success(array('dynamicFields' => $dyndata));
    }

    // ============================================
    // FAVORITES
    // ============================================

    /**
     * Get all favorites (member IDs and event IDs) for the current user.
     */
    public function getFavorites() {
        $auth = $this->verifyRequest();
        if (!$auth) return;

        $user_id = get_current_user_id();

        $fav_members = get_user_meta($user_id, '_mj_regmgr_fav_members', true);
        $fav_events  = get_user_meta($user_id, '_mj_regmgr_fav_events', true);

        wp_send_json_success(array(
            'favoriteMembers' => is_array($fav_members) ? array_values(array_map('intval', $fav_members)) : array(),
            'favoriteEvents'  => is_array($fav_events)  ? array_values(array_map('intval', $fav_events))  : array(),
        ));
    }

    /**
     * Toggle a favorite (add or remove).
     *
     * POST params:
     *  - type: 'member' | 'event'
     *  - targetId: int
     */
    public function toggleFavorite() {
        $auth = $this->verifyRequest();
        if (!$auth) return;

        $type      = isset($_POST['type']) ? sanitize_key($_POST['type']) : '';
        $target_id = isset($_POST['target_id']) ? absint($_POST['target_id']) : 0;

        if (!in_array($type, array('member', 'event'), true) || $target_id <= 0) {
            wp_send_json_error(array('message' => __('Paramètres invalides.', 'mj-member')), 400);
            return;
        }

        $user_id  = get_current_user_id();
        $meta_key = $type === 'member' ? '_mj_regmgr_fav_members' : '_mj_regmgr_fav_events';

        $favorites = get_user_meta($user_id, $meta_key, true);
        if (!is_array($favorites)) {
            $favorites = array();
        }
        $favorites = array_map('intval', $favorites);

        $is_favorite = in_array($target_id, $favorites, true);

        if ($is_favorite) {
            $favorites = array_values(array_diff($favorites, array($target_id)));
        } else {
            $favorites[] = $target_id;
        }

        update_user_meta($user_id, $meta_key, array_values(array_unique($favorites)));

        wp_send_json_success(array(
            'type'       => $type,
            'targetId'   => $target_id,
            'isFavorite' => !$is_favorite,
            'favorites'  => array_values(array_unique(array_map('intval', $favorites))),
        ));
    }

    /* ================================================================== *
     * Employee Documents – AJAX handlers                                  *
     * ================================================================== */

    /**
     * Get all employee documents for a member.
     */
    public function getEmployeeDocuments() {
        $auth = $this->verifyRequest();
        if (!$auth) return;

        // Only coordinators or admins
        if (!$auth['is_coordinateur']) {
            wp_send_json_error(array('message' => __('Permissions insuffisantes.', 'mj-member')), 403);
            return;
        }

        $memberId = isset($_POST['memberId']) ? absint($_POST['memberId']) : 0;
        if ($memberId <= 0) {
            wp_send_json_error(array('message' => __('Identifiant membre manquant.', 'mj-member')), 400);
            return;
        }

        $docs = MjEmployeeDocuments::get_all(array('member_id' => $memberId));
        $result = array_map(function ($doc) {
            return array(
                'id'           => (int) $doc->id,
                'memberId'     => (int) $doc->member_id,
                'docType'      => $doc->doc_type,
                'label'        => $doc->label,
                'originalName' => $doc->original_name,
                'mimeType'     => $doc->mime_type,
                'fileSize'     => (int) $doc->file_size,
                'documentDate' => $doc->document_date,
                'payslipMonth' => $doc->payslip_month !== null ? (int) $doc->payslip_month : null,
                'payslipYear'  => $doc->payslip_year !== null ? (int) $doc->payslip_year : null,
                'createdAt'    => $doc->created_at,
            );
        }, $docs);

        wp_send_json_success(array('documents' => array_values($result)));
    }

    /**
     * Upload a new employee document.
     */
    public function uploadEmployeeDocument() {
        $auth = $this->verifyRequest();
        if (!$auth) return;

        if (!$auth['is_coordinateur']) {
            wp_send_json_error(array('message' => __('Permissions insuffisantes.', 'mj-member')), 403);
            return;
        }

        $memberId = isset($_POST['memberId']) ? absint($_POST['memberId']) : 0;
        if ($memberId <= 0) {
            wp_send_json_error(array('message' => __('Identifiant membre manquant.', 'mj-member')), 400);
            return;
        }

        // Validate file
        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(array('message' => __('Aucun fichier reçu.', 'mj-member')), 400);
            return;
        }

        $fileType  = wp_check_filetype($_FILES['file']['name']);
        $mimeType  = $fileType['type'];
        $extension = strtolower($fileType['ext']);

        if (!in_array($mimeType, MjEmployeeDocuments::ALLOWED_MIME_TYPES, true) || !in_array($extension, MjEmployeeDocuments::ALLOWED_EXTENSIONS, true)) {
            wp_send_json_error(array('message' => __('Format de fichier non supporté. Utilisez PDF, JPG, PNG ou GIF.', 'mj-member')), 400);
            return;
        }

        if ($_FILES['file']['size'] > MjEmployeeDocuments::MAX_FILE_SIZE) {
            wp_send_json_error(array('message' => __('Le fichier est trop volumineux. Maximum 10 Mo.', 'mj-member')), 400);
            return;
        }

        // Ensure upload directory
        MjEmployeeDocuments::ensureUploadDir();

        // Generate secure filename
        $storedName = MjEmployeeDocuments::generateStoredName($memberId, $extension);
        $targetPath = MjEmployeeDocuments::uploadDir() . $storedName;

        if (!move_uploaded_file($_FILES['file']['tmp_name'], $targetPath)) {
            wp_send_json_error(array('message' => __('Erreur lors de l\'enregistrement du fichier.', 'mj-member')), 500);
            return;
        }

        // Parse metadata
        $docType      = isset($_POST['docType']) ? sanitize_key($_POST['docType']) : MjEmployeeDocuments::TYPE_MISC;
        $label        = isset($_POST['label']) ? sanitize_text_field(wp_unslash($_POST['label'])) : '';
        $documentDate = isset($_POST['documentDate']) ? sanitize_text_field($_POST['documentDate']) : current_time('Y-m-d');
        $payslipMonth = isset($_POST['payslipMonth']) && $_POST['payslipMonth'] !== '' ? absint($_POST['payslipMonth']) : null;
        $payslipYear  = isset($_POST['payslipYear']) && $_POST['payslipYear'] !== '' ? absint($_POST['payslipYear']) : null;

        if (!array_key_exists($docType, MjEmployeeDocuments::TYPES)) {
            $docType = MjEmployeeDocuments::TYPE_MISC;
        }

        // Auto-generate label for payslips
        if ($docType === MjEmployeeDocuments::TYPE_PAYSLIP && $label === '' && $payslipMonth && $payslipYear) {
            $months = array(
                1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
                5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
                9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
            );
            $label = 'Fiche de paie – ' . ($months[$payslipMonth] ?? '') . ' ' . $payslipYear;
        }

        $insertId = MjEmployeeDocuments::create(array(
            'member_id'     => $memberId,
            'doc_type'      => $docType,
            'label'         => $label,
            'original_name' => sanitize_file_name($_FILES['file']['name']),
            'stored_name'   => $storedName,
            'mime_type'     => $mimeType,
            'file_size'     => (int) $_FILES['file']['size'],
            'document_date' => $documentDate,
            'payslip_month' => $payslipMonth,
            'payslip_year'  => $payslipYear,
            'uploaded_by'   => $auth['member_id'],
        ));

        if (!$insertId) {
            // Clean up file
            @unlink($targetPath);
            wp_send_json_error(array('message' => __('Erreur lors de l\'enregistrement en base de données.', 'mj-member')), 500);
            return;
        }

        $doc = MjEmployeeDocuments::get_by_id($insertId);
        wp_send_json_success(array(
            'message'  => __('Document téléversé avec succès.', 'mj-member'),
            'document' => array(
                'id'           => (int) $doc->id,
                'memberId'     => (int) $doc->member_id,
                'docType'      => $doc->doc_type,
                'label'        => $doc->label,
                'originalName' => $doc->original_name,
                'mimeType'     => $doc->mime_type,
                'fileSize'     => (int) $doc->file_size,
                'documentDate' => $doc->document_date,
                'payslipMonth' => $doc->payslip_month !== null ? (int) $doc->payslip_month : null,
                'payslipYear'  => $doc->payslip_year !== null ? (int) $doc->payslip_year : null,
                'createdAt'    => $doc->created_at,
            ),
        ));
    }

    /**
     * Update metadata of an existing employee document.
     */
    public function updateEmployeeDocument() {
        $auth = $this->verifyRequest();
        if (!$auth) return;

        if (!$auth['is_coordinateur']) {
            wp_send_json_error(array('message' => __('Permissions insuffisantes.', 'mj-member')), 403);
            return;
        }

        $docId = isset($_POST['docId']) ? absint($_POST['docId']) : 0;
        if ($docId <= 0) {
            wp_send_json_error(array('message' => __('Identifiant document manquant.', 'mj-member')), 400);
            return;
        }

        $doc = MjEmployeeDocuments::get_by_id($docId);
        if (!$doc) {
            wp_send_json_error(array('message' => __('Document introuvable.', 'mj-member')), 404);
            return;
        }

        $data = array();
        if (isset($_POST['docType'])) {
            $dtype = sanitize_key($_POST['docType']);
            if (array_key_exists($dtype, MjEmployeeDocuments::TYPES)) {
                $data['doc_type'] = $dtype;
            }
        }
        if (isset($_POST['label'])) {
            $data['label'] = sanitize_text_field(wp_unslash($_POST['label']));
        }
        if (isset($_POST['documentDate'])) {
            $data['document_date'] = sanitize_text_field($_POST['documentDate']);
        }
        if (array_key_exists('payslipMonth', $_POST)) {
            $data['payslip_month'] = $_POST['payslipMonth'] !== '' && $_POST['payslipMonth'] !== null ? absint($_POST['payslipMonth']) : null;
        }
        if (array_key_exists('payslipYear', $_POST)) {
            $data['payslip_year'] = $_POST['payslipYear'] !== '' && $_POST['payslipYear'] !== null ? absint($_POST['payslipYear']) : null;
        }

        $success = MjEmployeeDocuments::update($docId, $data);
        if (!$success) {
            wp_send_json_error(array('message' => __('Erreur lors de la mise à jour.', 'mj-member')), 500);
            return;
        }

        $updated = MjEmployeeDocuments::get_by_id($docId);
        wp_send_json_success(array(
            'message'  => __('Document mis à jour.', 'mj-member'),
            'document' => array(
                'id'           => (int) $updated->id,
                'memberId'     => (int) $updated->member_id,
                'docType'      => $updated->doc_type,
                'label'        => $updated->label,
                'originalName' => $updated->original_name,
                'mimeType'     => $updated->mime_type,
                'fileSize'     => (int) $updated->file_size,
                'documentDate' => $updated->document_date,
                'payslipMonth' => $updated->payslip_month !== null ? (int) $updated->payslip_month : null,
                'payslipYear'  => $updated->payslip_year !== null ? (int) $updated->payslip_year : null,
                'createdAt'    => $updated->created_at,
            ),
        ));
    }

    /**
     * Delete an employee document (file + DB row).
     */
    public function deleteEmployeeDocument() {
        $auth = mj_regmgr_verify_request();
        if (!$auth) return;

        if (!$auth['is_coordinateur']) {
            wp_send_json_error(array('message' => __('Permissions insuffisantes.', 'mj-member')), 403);
            return;
        }

        $docId = isset($_POST['docId']) ? absint($_POST['docId']) : 0;
        if ($docId <= 0) {
            wp_send_json_error(array('message' => __('Identifiant document manquant.', 'mj-member')), 400);
            return;
        }

        $deleted = MjEmployeeDocuments::delete($docId);
        if (!$deleted) {
            wp_send_json_error(array('message' => __('Impossible de supprimer le document.', 'mj-member')), 500);
            return;
        }

        wp_send_json_success(array('message' => __('Document supprimé.', 'mj-member')));
    }

    /**
     * Securely serve an employee document file (coordinator only).
     * Uses GET parameters (nonce via POST nonce already verified at handler level).
     */
    public function downloadEmployeeDocument() {
        // Clean output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }

        // Verify nonce + permissions via the standard mechanism
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mj-registration-manager')) {
            // Also support GET nonce for direct link downloads
            if (!isset($_GET['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['nonce'])), 'mj-registration-manager')) {
                header('Content-Type: text/plain; charset=utf-8');
                echo 'Erreur: Nonce invalide ou expiré. Rechargez la page et réessayez.';
                exit;
            }
        }

        $userId = get_current_user_id();
        if (!$userId) {
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Erreur: Vous devez être connecté.';
            exit;
        }

        // Check coordinator role
        $memberObj = MjMembers::getByWpUserId($userId);
        $member = $memberObj ? (is_array($memberObj) ? $memberObj : (method_exists($memberObj, 'toArray') ? $memberObj->toArray() : (array) $memberObj)) : null;
        if (!$member) {
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Erreur: Membre non trouvé.';
            exit;
        }

        $role = is_array($member) ? ($member['role'] ?? '') : ($member->role ?? '');
        $isCoordinator = $role === MjRoles::COORDINATEUR || current_user_can('manage_options');
        if (!$isCoordinator) {
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Erreur: Accès refusé.';
            exit;
        }

        $docId = isset($_GET['doc_id']) ? absint($_GET['doc_id']) : (isset($_POST['docId']) ? absint($_POST['docId']) : 0);
        if ($docId <= 0) {
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Erreur: Identifiant document invalide.';
            exit;
        }

        $doc = MjEmployeeDocuments::get_by_id($docId);
        if (!$doc || empty($doc->stored_name)) {
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Erreur: Document non trouvé.';
            exit;
        }

        $filePath = MjEmployeeDocuments::filePath($doc->stored_name);
        if (!file_exists($filePath)) {
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Erreur: Fichier non trouvé sur le serveur.';
            exit;
        }

        // Verify MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMime = finfo_file($finfo, $filePath);
        finfo_close($finfo);

        if (!in_array($detectedMime, MjEmployeeDocuments::ALLOWED_MIME_TYPES, true)) {
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Erreur: Type de fichier non autorisé.';
            exit;
        }

        // Serve file
        $displayName = !empty($doc->original_name) ? $doc->original_name : ('document_' . $docId . '.' . pathinfo($filePath, PATHINFO_EXTENSION));
        header('Content-Type: ' . $detectedMime);
        header('Content-Disposition: inline; filename="' . sanitize_file_name($displayName) . '"');
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: private, max-age=3600');
        header('X-Content-Type-Options: nosniff');

        readfile($filePath);
        exit;
    }

    /**
     * Save job profile fields for a member (employee tab).
     */
    public function saveJobProfile() {
        $current_member = mj_regmgr_verify_request();
        if (!$current_member) return;

        // Only coordinators or admins
        if (!current_user_can(Config::capability()) && empty($current_member['is_coordinateur'])) {
            wp_send_json_error(array('message' => __('Permissions insuffisantes.', 'mj-member')), 403);
            return;
        }

        $member_id = isset($_POST['memberId']) ? absint($_POST['memberId']) : 0;
        if (!$member_id) {
            wp_send_json_error(array('message' => __('ID du membre manquant.', 'mj-member')));
            return;
        }

        $allowed_regimes = array('mi-temps', 'temps-plein', 'quatre-cinquieme');

        $raw_job_title = isset($_POST['jobTitle']) ? sanitize_text_field(wp_unslash($_POST['jobTitle'])) : '';
        $legacy_job_title_map = array(
            'coordination' => 'Coordinateur',
            'animateur' => 'Animateur',
            'communication' => 'Employe',
        );

        $job_title = $raw_job_title;

        if ($raw_job_title !== '') {
            $legacy_key = sanitize_key($raw_job_title);

            if (isset($legacy_job_title_map[$legacy_key])) {
                $job_title = $legacy_job_title_map[$legacy_key];
            } elseif ($legacy_key === 'autre') {
                $custom_title_candidates = array('jobTitleOther', 'jobTitleCustom', 'customJobTitle', 'jobTitleFree', 'job_title_custom');
                $custom_title = '';

                foreach ($custom_title_candidates as $candidate_key) {
                    if (!isset($_POST[$candidate_key])) {
                        continue;
                    }

                    $candidate_value = sanitize_text_field(wp_unslash($_POST[$candidate_key]));
                    if ($candidate_value !== '') {
                        $custom_title = $candidate_value;
                        break;
                    }
                }

                // Never persist the legacy key itself.
                $job_title = $custom_title;
            }
        }

        if ($job_title !== '') {
            $job_title = function_exists('mb_substr') ? mb_substr($job_title, 0, 100) : substr($job_title, 0, 100);
        }

        $work_regime = isset($_POST['workRegime']) ? sanitize_text_field($_POST['workRegime']) : '';
        if ($work_regime !== '' && !in_array($work_regime, $allowed_regimes, true)) {
            $work_regime = '';
        }

        $funding_source = isset($_POST['fundingSource']) ? sanitize_text_field($_POST['fundingSource']) : '';
        $job_description = isset($_POST['jobDescription']) ? wp_kses_post(wp_unslash($_POST['jobDescription'])) : '';
        $signature_message = isset($_POST['signatureMessage']) ? wp_kses_post(wp_unslash($_POST['signatureMessage'])) : '';

        $result = MjMembers::update($member_id, array(
            'job_title'       => $job_title,
            'work_regime'     => $work_regime,
            'funding_source'  => $funding_source,
            'job_description' => $job_description,
            'signature_message' => $signature_message,
        ));

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
            return;
        }

        wp_send_json_success(array(
            'message'       => __('Profil de fonction enregistré.', 'mj-member'),
            'jobTitle'      => $job_title,
            'workRegime'    => $work_regime,
            'fundingSource' => $funding_source,
            'jobDescription'=> $job_description,
            'signatureMessage' => $signature_message,
        ));
    }

    /* ================================================================== *
     * AI Text Generation                                                  *
     * ================================================================== */

    /**
     * Generate text (event description or registration document) using OpenAI.
     *
     * POST params:
     *   eventId  (int)    – ID of the event.
     *   type     (string) – 'description' or 'regdoc'.
     *   hint     (string) – Optional user-provided context hint.
     *   includedFields (array|string) – Optional selected context field keys.
     *   contextData (json|string) – Optional context values sent by the modal.
     */
    public function generateAiText() {
        $auth = mj_regmgr_verify_request();
        if (!$auth) return;

        if (!$auth['is_coordinateur']) {
            wp_send_json_error(array('message' => __('Permissions insuffisantes pour utiliser la génération IA.', 'mj-member')), 403);
            return;
        }

        $client = new MjOpenAIClient();
        if (!$client->isEnabled()) {
            wp_send_json_error(array('message' => __('La clé API OpenAI est manquante.', 'mj-member')), 503);
            return;
        }

        $event_id = isset($_POST['eventId']) ? absint($_POST['eventId']) : 0;
        if ($event_id <= 0) {
            wp_send_json_error(array('message' => __('Identifiant événement invalide.', 'mj-member')), 400);
            return;
        }

        $type = isset($_POST['type']) ? sanitize_key((string) $_POST['type']) : '';
        if (!in_array($type, array('description', 'regdoc', 'social_description'), true)) {
            wp_send_json_error(array('message' => __('Type de génération invalide.', 'mj-member')), 400);
            return;
        }

        $hint_raw = isset($_POST['hint']) ? wp_unslash((string) $_POST['hint']) : '';
        $hint = sanitize_textarea_field($hint_raw);

        $allowed_context_keys = array(
            'event_name',
            'event_type',
            'event_status',
            'event_date_start',
            'event_date_end',
            'event_date_deadline',
            'event_price',
            'event_url',
            'event_location',
            'event_location_address',
            'event_age_min',
            'event_age_max',
            'event_capacity',
            'event_occurrences',
            'event_animateurs',
        );

        $included_fields = array();
        if (isset($_POST['includedFields'])) {
            $raw_included = wp_unslash($_POST['includedFields']);

            if (is_array($raw_included)) {
                foreach ($raw_included as $field_key) {
                    $candidate = sanitize_key((string) $field_key);
                    if ($candidate !== '' && in_array($candidate, $allowed_context_keys, true)) {
                        $included_fields[] = $candidate;
                    }
                }
            } elseif (is_string($raw_included) && $raw_included !== '') {
                $decoded_included = json_decode($raw_included, true);
                if (is_array($decoded_included)) {
                    foreach ($decoded_included as $field_key) {
                        $candidate = sanitize_key((string) $field_key);
                        if ($candidate !== '' && in_array($candidate, $allowed_context_keys, true)) {
                            $included_fields[] = $candidate;
                        }
                    }
                }
            }
        }
        $included_fields = array_values(array_unique($included_fields));

        $context_data = array();
        if (isset($_POST['contextData'])) {
            $raw_context = wp_unslash((string) $_POST['contextData']);
            if ($raw_context !== '') {
                $decoded_context = json_decode($raw_context, true);
                if (is_array($decoded_context)) {
                    $context_data = $decoded_context;
                }
            }
        }

        $event = MjEvents::find($event_id);
        if (!$event) {
            wp_send_json_error(array('message' => __('Événement introuvable.', 'mj-member')), 404);
            return;
        }

        $site_name = get_bloginfo('name');

        $event_location_name = '';
        $event_location_address = '';
        if (!empty($event->location_id) && class_exists(MjEventLocations::class)) {
            $location = MjEventLocations::find((int) $event->location_id);
            if ($location) {
                $event_location_name = isset($location->name) ? sanitize_text_field((string) $location->name) : '';
                $event_location_address = isset($location->address) ? sanitize_text_field((string) $location->address) : '';
            }
        }

        $normalize_scalar = static function ($value) {
            if ($value === null || $value === false) {
                return '';
            }
            if (is_array($value) || is_object($value)) {
                return '';
            }
            $text = sanitize_text_field((string) $value);
            return trim($text);
        };

        $normalize_list = static function ($value) use ($normalize_scalar) {
            $items = array();

            if (is_array($value)) {
                foreach ($value as $entry) {
                    $normalized = $normalize_scalar($entry);
                    if ($normalized !== '') {
                        $items[] = $normalized;
                    }
                }
            } elseif (is_string($value)) {
                $normalized = $normalize_scalar($value);
                if ($normalized !== '') {
                    $items[] = $normalized;
                }
            }

            return array_values(array_unique($items));
        };

        $defaults = array(
            'event_name' => $normalize_scalar(isset($event->title) ? $event->title : ''),
            'event_type' => $normalize_scalar(isset($event->type) ? $event->type : ''),
            'event_status' => $normalize_scalar(isset($event->status) ? $event->status : ''),
            'event_date_start' => $normalize_scalar(isset($event->date_debut) ? $event->date_debut : (isset($event->date_start) ? $event->date_start : '')),
            'event_date_end' => $normalize_scalar(isset($event->date_fin) ? $event->date_fin : (isset($event->date_end) ? $event->date_end : '')),
            'event_date_deadline' => $normalize_scalar(isset($event->date_fin_inscription) ? $event->date_fin_inscription : ''),
            'event_price' => $normalize_scalar(isset($event->prix) ? $event->prix : (isset($event->price) ? $event->price : '')),
            'event_url' => $normalize_scalar(isset($event->event_page_url) ? $event->event_page_url : (isset($event->front_url) ? $event->front_url : '')),
            'event_location' => $event_location_name,
            'event_location_address' => $event_location_address,
            'event_age_min' => $normalize_scalar(isset($event->age_min) ? $event->age_min : ''),
            'event_age_max' => $normalize_scalar(isset($event->age_max) ? $event->age_max : ''),
            'event_capacity' => $normalize_scalar(isset($event->capacity_total) ? $event->capacity_total : ''),
            'event_occurrences' => array(),
            'event_animateurs' => array(),
        );

        $context_labels = array(
            'event_name' => 'Nom de l\'événement',
            'event_type' => 'Type',
            'event_status' => 'Statut',
            'event_date_start' => 'Date de début',
            'event_date_end' => 'Date de fin',
            'event_date_deadline' => 'Date limite d\'inscription',
            'event_price' => 'Tarif',
            'event_url' => 'URL de l\'événement',
            'event_location' => 'Lieu',
            'event_location_address' => 'Adresse du lieu',
            'event_age_min' => 'Âge minimum',
            'event_age_max' => 'Âge maximum',
            'event_capacity' => 'Capacité totale',
            'event_occurrences' => 'Occurrences',
            'event_animateurs' => 'Animateurs associés',
        );

        $context_is_list = array(
            'event_occurrences' => true,
            'event_animateurs' => true,
        );

        $context_values = array();
        foreach ($allowed_context_keys as $key) {
            $is_list = isset($context_is_list[$key]) && $context_is_list[$key] === true;
            $raw_value = array_key_exists($key, $context_data) ? $context_data[$key] : (isset($defaults[$key]) ? $defaults[$key] : '');
            $context_values[$key] = $is_list ? $normalize_list($raw_value) : $normalize_scalar($raw_value);
        }

        $keys_to_use = $included_fields;
        if (empty($keys_to_use)) {
            foreach ($allowed_context_keys as $key) {
                $value = isset($context_values[$key]) ? $context_values[$key] : '';
                if (is_array($value)) {
                    if (!empty($value)) {
                        $keys_to_use[] = $key;
                    }
                } elseif ($value !== '') {
                    $keys_to_use[] = $key;
                }
            }
        }

        $context_parts = array();
        foreach ($keys_to_use as $key) {
            if (!in_array($key, $allowed_context_keys, true)) {
                continue;
            }

            $label = isset($context_labels[$key]) ? $context_labels[$key] : $key;
            $is_list = isset($context_is_list[$key]) && $context_is_list[$key] === true;
            $value = isset($context_values[$key]) ? $context_values[$key] : '';

            if ($is_list) {
                if (!is_array($value) || empty($value)) {
                    continue;
                }
                $context_parts[] = $label . ' :';
                foreach ($value as $entry) {
                    $context_parts[] = '- ' . $entry;
                }
                continue;
            }

            if ($value === '') {
                continue;
            }

            if ($key === 'event_price' && $value !== '0' && $value !== '0.00' && strpos($value, '€') === false) {
                $value .= ' €';
            }

            $context_parts[] = sprintf('%s : %s', $label, $value);
        }

        if ($hint !== '') {
            $context_parts[] = sprintf('Informations complémentaires fournies par l\'organisateur : %s', $hint);
        }

        $event_context = implode("\n", $context_parts);
        $hint_directive = '';
        if ($hint !== '') {
            $hint_directive = sprintf(
                "\n\nConsigne prioritaire de l'organisateur (à respecter en priorité) : %s\nAdapte explicitement la réponse à cette consigne.",
                $hint
            );
        }

        $priority_rules_raw = (string) get_option('mj_member_ai_priority_rules', '');
        if ($priority_rules_raw === '') {
            $priority_rules_raw = "Hiérarchie stricte des consignes :\n"
                . "1) La consigne prioritaire de l'organisateur prévaut sur toute autre consigne de style/marketing.\n"
                . "2) Ne produis jamais de phrase qui contredit la consigne prioritaire.\n"
                . "3) En cas de conflit, applique la consigne prioritaire et adapte le reste du texte.";
        }
        $priority_rules = "\n\n" . trim($priority_rules_raw);

        $closure_rules = '';
        if ($hint !== '' && preg_match('/(cl[oô]tur|ferm[ée]e|close|termin[ée]e|complet)/iu', $hint)) {
            $closure_rules_raw = (string) get_option('mj_member_ai_closure_rules', '');
            if ($closure_rules_raw === '') {
                $closure_rules_raw = "Contrainte de cohérence importante : si la consigne indique que les inscriptions sont clôturées/fermées, "
                    . "n'utilise aucun appel à l'action d'inscription (ex: participez, inscrivez-vous, rejoignez-nous). "
                    . "Privilégie un message informatif (ex: inscriptions clôturées, restez informés des prochaines dates).";
            }
            $closure_rules = "\n\n" . trim($closure_rules_raw);
        }

        if ($type === 'description') {
            $default_description_prompt = (string) get_option('mj_member_ai_description_prompt', get_option('mj_ai_description_prompt', ''));
            if ($default_description_prompt === '') {
                $default_description_prompt = sprintf(
                    'Tu es un assistant rédacteur pour une association jeunesse (%s). Tu rédiges des descriptions d\'événements en français, de manière claire, engageante et adaptée à un public familial. Réponds uniquement avec le texte de la description, sans titre ni introduction.',
                    $site_name
                );
            }
            $system_prompt = apply_filters('mj_member_ai_description_system_prompt', $default_description_prompt);
            $system_prompt .= "\n\n" . 'Format de sortie obligatoire: retourne uniquement du HTML valide (pas de Markdown, pas de triple backticks). Utilise des balises simples adaptées au rendu web (ex: <p>, <strong>, <ul>, <li>).';
            $system_prompt .= $priority_rules;
            $system_prompt .= $closure_rules;
            $user_prompt = sprintf(
                "Rédige une description attrayante en HTML pour l'événement suivant:\n\n%s%s",
                $event_context,
                $hint_directive
            );
        } elseif ($type === 'regdoc') {
            $default_regdoc_prompt = (string) get_option('mj_member_ai_regdoc_prompt', get_option('mj_ai_regdoc_prompt', ''));
            if ($default_regdoc_prompt === '') {
                $default_regdoc_prompt = sprintf(
                    'Tu es un assistant pour une association jeunesse (%s). Tu rédiges des documents d\'inscription en français. Le document doit contenir les informations essentielles sur l\'événement et les instructions pour les participants. Utilise les variables entre crochets (ex : [member_name], [event_name]) pour personnaliser le document. Réponds uniquement avec le contenu du document.',
                    $site_name
                );
            }
            $system_prompt = apply_filters('mj_member_ai_regdoc_system_prompt', $default_regdoc_prompt);
            $system_prompt .= "\n\n" . 'Format de sortie obligatoire: retourne uniquement du HTML valide (pas de Markdown, pas de triple backticks). Structure le document avec des balises HTML (<h2>, <p>, <ul>, <li>, <strong>) sans code fence.';
            $system_prompt .= $priority_rules;
            $system_prompt .= $closure_rules;
            $user_prompt = sprintf(
                "Rédige un document d'inscription en HTML pour l'événement suivant:\n\n%s%s",
                $event_context,
                $hint_directive
            );
        } else {
            $default_social_prompt = (string) get_option('mj_member_ai_social_description_prompt', get_option('mj_ai_social_description_prompt', ''));
            if ($default_social_prompt === '') {
                $default_social_prompt = sprintf(
                    'Tu es community manager pour une association jeunesse (%s). Rédige un texte court, engageant et naturel pour promouvoir un événement sur les réseaux sociaux. Utilise un ton chaleureux, concret et orienté action. Réponds uniquement avec le message final en texte brut, sans HTML ni markdown.',
                    $site_name
                );
            }

            $system_prompt = apply_filters('mj_member_ai_social_description_system_prompt', $default_social_prompt);
            $system_prompt .= "\n\n" . 'Format de sortie obligatoire: texte brut uniquement (pas de HTML, pas de Markdown, pas de guillemets autour du texte). 2 à 5 phrases maximum.';
            $system_prompt .= $priority_rules;
            $system_prompt .= $closure_rules;
            $user_prompt = sprintf(
                "Rédige une description de publication réseaux sociaux pour l'événement suivant:\n\n%s%s",
                $event_context,
                $hint_directive
            );
        }

        $result = $client->generateText($system_prompt, $user_prompt);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 503);
            return;
        }

        $generated_text = isset($result['text']) ? trim((string) $result['text']) : '';

        // Defensive cleanup: if the model still wraps HTML in markdown fences, unwrap it.
        if (preg_match('/^```(?:html)?\s*([\s\S]*?)\s*```$/i', $generated_text, $matches)) {
            $generated_text = trim((string) $matches[1]);
        }

        wp_send_json_success(array(
            'text' => $generated_text,
            'type' => $type,
        ));
    }
} // End of RegistrationManagerController

// ---------------------------------------------------------------------------
// Global forwarding stubs — allow intra-class calls that still use the old
// mj_regmgr_*() function names inside method bodies to reach the class methods.
// ---------------------------------------------------------------------------
function mj_regmgr_verify_request() { return RegistrationManagerController::getInstance()->callInternal('verifyRequest', func_get_args()); }
function mj_regmgr_get_member_avatar_url() { return RegistrationManagerController::getInstance()->callInternal('getMemberAvatarUrl', func_get_args()); }
function mj_regmgr_to_bool() { return RegistrationManagerController::getInstance()->callInternal('toBool', func_get_args()); }
function mj_regmgr_get_event_emoji_value() { return RegistrationManagerController::getInstance()->callInternal('getEventEmojiValue', func_get_args()); }
function mj_regmgr_build_event_sidebar_item() { return RegistrationManagerController::getInstance()->callInternal('buildEventSidebarItem', func_get_args()); }
function mj_regmgr_format_datetime_compact() { return RegistrationManagerController::getInstance()->callInternal('formatDatetimeCompact', func_get_args()); }
function mj_regmgr_format_date_compact() { return RegistrationManagerController::getInstance()->callInternal('formatDateCompact', func_get_args()); }
function mj_regmgr_format_time_compact() { return RegistrationManagerController::getInstance()->callInternal('formatTimeCompact', func_get_args()); }
function mj_regmgr_occurrence_status_from_front() { return RegistrationManagerController::getInstance()->callInternal('occurrenceStatusFromFront', func_get_args()); }
function mj_regmgr_occurrence_status_to_front() { return RegistrationManagerController::getInstance()->callInternal('occurrenceStatusToFront', func_get_args()); }
function mj_regmgr_sanitize_time_value() { return RegistrationManagerController::getInstance()->callInternal('sanitizeTimeValue', func_get_args()); }
function mj_regmgr_sanitize_date_value() { return RegistrationManagerController::getInstance()->callInternal('sanitizeDateValue', func_get_args()); }
function mj_regmgr_sanitize_occurrence_generator_plan() { return RegistrationManagerController::getInstance()->callInternal('sanitizeOccurrenceGeneratorPlan', func_get_args()); }
function mj_regmgr_derive_generator_plan_from_schedule() { return RegistrationManagerController::getInstance()->callInternal('deriveGeneratorPlanFromSchedule', func_get_args()); }
function mj_regmgr_merge_generator_plans() { return RegistrationManagerController::getInstance()->callInternal('mergeGeneratorPlans', func_get_args()); }
function mj_regmgr_extract_occurrence_generator_from_payload() { return RegistrationManagerController::getInstance()->callInternal('extractOccurrenceGeneratorFromPayload', func_get_args()); }
function mj_regmgr_extract_occurrence_generator_from_event() { return RegistrationManagerController::getInstance()->callInternal('extractOccurrenceGeneratorFromEvent', func_get_args()); }
function mj_regmgr_schedule_payload_has_occurrence_entities() { return RegistrationManagerController::getInstance()->callInternal('schedulePayloadHasOccurrenceEntities', func_get_args()); }
function mj_regmgr_should_allow_occurrence_fallback() { return RegistrationManagerController::getInstance()->callInternal('shouldAllowOccurrenceFallback', func_get_args()); }
function mj_regmgr_prepare_event_occurrence_rows() { return RegistrationManagerController::getInstance()->callInternal('prepareEventOccurrenceRows', func_get_args()); }
function mj_regmgr_format_event_occurrences_for_front() { return RegistrationManagerController::getInstance()->callInternal('formatEventOccurrencesForFront', func_get_args()); }
function mj_regmgr_find_next_occurrence() { return RegistrationManagerController::getInstance()->callInternal('findNextOccurrence', func_get_args()); }
function mj_regmgr_build_event_schedule_info() { return RegistrationManagerController::getInstance()->callInternal('buildEventScheduleInfo', func_get_args()); }
function mj_regmgr_event_allows_attendance_without_registration() { return RegistrationManagerController::getInstance()->callInternal('eventAllowsAttendanceWithoutRegistration', func_get_args()); }
function mj_regmgr_ensure_attendance_registration() { return RegistrationManagerController::getInstance()->callInternal('ensureAttendanceRegistration', func_get_args()); }
function mj_regmgr_get_notes_dynfields() { return RegistrationManagerController::getInstance()->callInternal('getNotesDynfields', func_get_args()); }
function mj_regmgr_get_schedule_weekdays() { return RegistrationManagerController::getInstance()->callInternal('getScheduleWeekdays', func_get_args()); }
function mj_regmgr_get_schedule_month_ordinals() { return RegistrationManagerController::getInstance()->callInternal('getScheduleMonthOrdinals', func_get_args()); }
function mj_regmgr_normalize_hex_color() { return RegistrationManagerController::getInstance()->callInternal('normalizeHexColor', func_get_args()); }
function mj_regmgr_decode_json_field() { return RegistrationManagerController::getInstance()->callInternal('decodeJsonField', func_get_args()); }
function mj_regmgr_format_event_datetime() { return RegistrationManagerController::getInstance()->callInternal('formatEventDatetime', func_get_args()); }
function mj_regmgr_parse_event_datetime() { return RegistrationManagerController::getInstance()->callInternal('parseEventDatetime', func_get_args()); }
function mj_regmgr_parse_recurrence_until() { return RegistrationManagerController::getInstance()->callInternal('parseRecurrenceUntil', func_get_args()); }
function mj_regmgr_events_supports_primary_animateur() { return RegistrationManagerController::getInstance()->callInternal('eventsSupportsPrimaryAnimateur', func_get_args()); }
function mj_regmgr_prepare_event_form_values() { return RegistrationManagerController::getInstance()->callInternal('prepareEventFormValues', func_get_args()); }
function mj_regmgr_fill_schedule_values() { return RegistrationManagerController::getInstance()->callInternal('fillScheduleValues', func_get_args()); }
function mj_regmgr_collect_event_editor_assets() { return RegistrationManagerController::getInstance()->callInternal('collectEventEditorAssets', func_get_args()); }
function mj_regmgr_user_can_manage_locations() { return RegistrationManagerController::getInstance()->callInternal('userCanManageLocations', func_get_args()); }
function mj_regmgr_build_location_lookup_query() { return RegistrationManagerController::getInstance()->callInternal('buildLocationLookupQuery', func_get_args()); }
function mj_regmgr_build_location_map_preview_url() { return RegistrationManagerController::getInstance()->callInternal('buildLocationMapPreviewUrl', func_get_args()); }
function mj_regmgr_build_location_map_link() { return RegistrationManagerController::getInstance()->callInternal('buildLocationMapLink', func_get_args()); }
function mj_regmgr_format_location_payload() { return RegistrationManagerController::getInstance()->callInternal('formatLocationPayload', func_get_args()); }
function mj_regmgr_sanitize_weekday_times() { return RegistrationManagerController::getInstance()->callInternal('sanitizeWeekdayTimes', func_get_args()); }
function mj_regmgr_sanitize_recurrence_exceptions() { return RegistrationManagerController::getInstance()->callInternal('sanitizeRecurrenceExceptions', func_get_args()); }
function mj_regmgr_resolve_schedule_exceptions() { return RegistrationManagerController::getInstance()->callInternal('resolveScheduleExceptions', func_get_args()); }
function mj_regmgr_build_event_update_payload() { return RegistrationManagerController::getInstance()->callInternal('buildEventUpdatePayload', func_get_args()); }
function mj_regmgr_serialize_event_summary() { return RegistrationManagerController::getInstance()->callInternal('serializeEventSummary', func_get_args()); }
function mj_regmgr_format_date() { return RegistrationManagerController::getInstance()->callInternal('formatDate', func_get_args()); }
function mj_regmgr_get_event_cover_url() { return RegistrationManagerController::getInstance()->callInternal('getEventCoverUrl', func_get_args()); }
function mj_regmgr_ensure_notes_table() { return RegistrationManagerController::getInstance()->callInternal('ensureNotesTable', func_get_args()); }
function mj_regmgr_create_notes_table_if_not_exists() { return RegistrationManagerController::getInstance()->callInternal('createNotesTableIfNotExists', func_get_args()); }
function mj_regmgr_get_member_level_progression() { return RegistrationManagerController::getInstance()->callInternal('getMemberLevelProgression', func_get_args()); }
function mj_regmgr_get_member_badges_payload() { return RegistrationManagerController::getInstance()->callInternal('getMemberBadgesPayload', func_get_args()); }
function mj_regmgr_prepare_member_badge_entry() { return RegistrationManagerController::getInstance()->callInternal('prepareMemberBadgeEntry', func_get_args()); }
function mj_regmgr_is_badge_complete() { return RegistrationManagerController::getInstance()->callInternal('isBadgeComplete', func_get_args()); }
function mj_regmgr_get_member_actions_payload() { return RegistrationManagerController::getInstance()->callInternal('getMemberActionsPayload', func_get_args()); }
function mj_regmgr_get_member_trophies_payload() { return RegistrationManagerController::getInstance()->callInternal('getMemberTrophiesPayload', func_get_args()); }

