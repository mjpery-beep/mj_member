<?php

namespace Mj\Member\Admin\Page;

use Mj\Member\Classes\Crud\MjBadgeCriteria;
use Mj\Member\Classes\Crud\MjBadges;
use Mj\Member\Classes\Crud\MjMemberBadgeCriteria;
use Mj\Member\Classes\Crud\MjMemberBadges;
use Mj\Member\Classes\Crud\MjMembers;
use Mj\Member\Classes\Table\MjBadges_List_Table;
use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

final class BadgesPage
{
    private const DEBUG_LOG = 'mj-badges-debug.log';
    private static $actionsRegistered = false;

    private static function log_debug(string $message): void
    {
        // Intentionally left blank; former debug helper disabled.
    }

    public static function slug(): string
    {
        return 'mj-member-badges';
    }

    public static function registerHooks(string $hookSuffix): void
    {
        add_action('load-' . $hookSuffix, array(static::class, 'catch_bulk_actions'));
        add_action('admin_init', array(static::class, 'register_actions'));
    }

    public static function render(): void
    {
        if (!current_user_can(Config::contactCapability())) {
            wp_die(esc_html__('Vous n’avez pas les droits suffisants pour accéder à cette page.', 'mj-member'));
        }

        $action = isset($_GET['action']) ? sanitize_key(wp_unslash((string) $_GET['action'])) : '';
        if ($action === 'edit' || $action === 'new') {
            static::render_badge_form();
            return;
        }

        if ($action === 'assign') {
            static::render_assign_form();
            return;
        }

        if ($action === 'assigned') {
            static::render_assigned_list();
            return;
        }

        static::render_list();
    }

    public static function register_actions(): void
    {
        if (self::$actionsRegistered) {
            return;
        }

        self::$actionsRegistered = true;

        $forms = array(
            'save_badge'   => array(static::class, 'handle_save_badge'),
            'delete_badge' => array(static::class, 'handle_delete_badge'),
            'assign_badge' => array(static::class, 'handle_assign_badge'),
        );

        foreach ($forms as $name => $handler) {
            $hook = 'admin_post_' . $name;
            add_action($hook, $handler);
        }
    }

    public static function catch_bulk_actions(): void
    {
        if (!isset($_REQUEST['page']) || static::slug() !== sanitize_key(wp_unslash((string) $_REQUEST['page']))) {
            return;
        }

        if (!current_user_can(Config::contactCapability())) {
            return;
        }

        $listTable = new MjBadges_List_Table();
        $currentAction = $listTable->current_action();
        if (!$currentAction) {
            return;
        }

        $allowedActions = array_keys($listTable->get_bulk_actions());
        if (!in_array($currentAction, $allowedActions, true)) {
            return;
        }

        if (!isset($_REQUEST['_wpnonce'])) {
            return;
        }

        $listTable->process_bulk_action();
    }

    public static function deleteNonceAction(int $badgeId): string
    {
        return 'mj_member_delete_badge_' . $badgeId;
    }

    private static function render_list(): void
    {
        $listTable = new MjBadges_List_Table();
        $listTable->prepare_items();

        $createUrl = add_query_arg(
            array(
                'page'   => static::slug(),
                'action' => 'new',
            ),
            admin_url('admin.php')
        );

        $noticeKey = isset($_GET['mj_badges_notice']) ? sanitize_key(wp_unslash((string) $_GET['mj_badges_notice'])) : '';
        $errorMessage = isset($_GET['mj_badges_error']) ? rawurldecode((string) $_GET['mj_badges_error']) : '';

        $template = Config::path() . 'includes/templates/admin/badges/index.php';
        if (is_readable($template)) {
            include $template;
        }
    }

    private static function render_badge_form(): void
    {
        $badgeId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        $isEdit = $badgeId > 0;

        $defaults = array(
            'label'          => '',
            'slug'           => '',
            'summary'        => '',
            'description'    => '',
            'criteria'       => array(),
            'display_order'  => 0,
            'status'         => MjBadges::STATUS_ACTIVE,
            'prompt'         => '',
            'icon'           => '',
            'image_id'       => 0,
        );

        $badge = $isEdit ? MjBadges::get($badgeId) : $defaults;
        if ($isEdit && !is_array($badge)) {
            wp_safe_redirect(add_query_arg(array(
                'page' => static::slug(),
                'mj_badges_notice' => 'error',
                'mj_badges_error' => rawurlencode(__('Badge introuvable.', 'mj-member')),
            ), admin_url('admin.php')));
            exit;
        }

        if (!is_array($badge)) {
            $badge = $defaults;
        }

        $criteriaRecords = array();
        if (isset($badge['criteria_records']) && is_array($badge['criteria_records'])) {
            $criteriaRecords = $badge['criteria_records'];
        }

        $criteriaLabels = array();
        foreach ($criteriaRecords as $record) {
            if (!is_array($record)) {
                continue;
            }
            $label = isset($record['label']) ? trim((string) $record['label']) : '';
            if ($label !== '') {
                $criteriaLabels[] = $label;
            }
        }

        if (empty($criteriaLabels)) {
            $rawCriteria = isset($badge['criteria']) ? $badge['criteria'] : array();
            if (is_string($rawCriteria)) {
                $rawCriteria = preg_split('/\r\n|\r|\n/', $rawCriteria);
            }
            if (is_array($rawCriteria)) {
                foreach ($rawCriteria as $entry) {
                    $label = trim((string) $entry);
                    if ($label !== '') {
                        $criteriaLabels[] = $label;
                    }
                }
            }
        }

        $criteriaText = implode("\n", $criteriaLabels);

        $backUrl = add_query_arg('page', static::slug(), admin_url('admin.php'));

        if (!isset($badge['image_id'])) {
            $badge['image_id'] = 0;
        }

        if (function_exists('wp_enqueue_media')) {
            wp_enqueue_media();
        }

        $template = Config::path() . 'includes/templates/admin/badges/form.php';
        if (is_readable($template)) {
            include $template;
        }
    }

    private static function render_assign_form(): void
    {
        $badgeId = isset($_GET['badge_id']) ? (int) $_GET['badge_id'] : 0;
        $badge = $badgeId > 0 ? MjBadges::get($badgeId) : null;
        if (!$badge) {
            wp_safe_redirect(add_query_arg('page', static::slug(), admin_url('admin.php')));
            exit;
        }

        $criteriaRecords = MjBadgeCriteria::get_for_badge($badgeId);
        $selectedCriteriaIds = array();

        $memberRecords = MjMembers::get_all(array(
            'limit' => 200,
            'orderby' => 'last_name',
            'order' => 'ASC',
        ));

        $selectedMemberId = isset($_GET['member_id']) ? (int) $_GET['member_id'] : 0;
        $selectedStatus = MjMemberBadges::STATUS_AWARDED;
        $selectedEvidence = '';
        $selectedAwardedAt = '';
        $isEditing = false;

        $members = array();
        foreach ($memberRecords as $member) {
            if ($member instanceof \Mj\Member\Classes\Value\MemberData) {
                $members[] = array(
                    'id' => (int) $member->get('id'),
                    'first_name' => (string) $member->get('first_name'),
                    'last_name' => (string) $member->get('last_name'),
                );
            }
        }

        if ($selectedMemberId > 0) {
            $assignmentRecords = MjMemberBadges::get_all(array(
                'badge_id' => $badgeId,
                'member_id' => $selectedMemberId,
                'limit' => 1,
            ));

            if (!empty($assignmentRecords)) {
                $assignment = array_shift($assignmentRecords);
                if (is_array($assignment)) {
                    if (!empty($assignment['status'])) {
                        $selectedStatus = (string) $assignment['status'];
                    }
                    if (!empty($assignment['evidence'])) {
                        $selectedEvidence = (string) $assignment['evidence'];
                    }
                    if (!empty($assignment['awarded_at'])) {
                        $timestamp = strtotime((string) $assignment['awarded_at']);
                        if ($timestamp !== false) {
                            $selectedAwardedAt = wp_date('Y-m-d\TH:i', $timestamp);
                        }
                    }
                    $isEditing = true;
                }
            }

            $criteriaAwards = MjMemberBadgeCriteria::get_for_member_badge($selectedMemberId, $badgeId);
            foreach ($criteriaAwards as $award) {
                if (!is_array($award)) {
                    continue;
                }
                $criterionId = isset($award['criterion_id']) ? (int) $award['criterion_id'] : 0;
                if ($criterionId <= 0) {
                    continue;
                }
                if (!empty($award['status']) && $award['status'] === MjMemberBadgeCriteria::STATUS_REVOKED) {
                    continue;
                }
                $selectedCriteriaIds[$criterionId] = $criterionId;
            }

            $selectedCriteriaIds = array_values($selectedCriteriaIds);

            $found = false;
            foreach ($members as $record) {
                if (isset($record['id']) && (int) $record['id'] === $selectedMemberId) {
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $memberData = MjMembers::getById($selectedMemberId);
                if ($memberData instanceof \Mj\Member\Classes\Value\MemberData) {
                    $members[] = array(
                        'id' => (int) $memberData->get('id'),
                        'first_name' => (string) $memberData->get('first_name'),
                        'last_name' => (string) $memberData->get('last_name'),
                    );
                }
            }
        }

        if (!empty($members)) {
            usort($members, static function ($a, $b) {
                $aName = trim(sprintf('%s %s', $a['last_name'] ?? '', $a['first_name'] ?? ''));
                $bName = trim(sprintf('%s %s', $b['last_name'] ?? '', $b['first_name'] ?? ''));

                return strcasecmp($aName, $bName);
            });
        }

        $backUrl = add_query_arg('page', static::slug(), admin_url('admin.php'));
        $assignedListUrl = add_query_arg(array(
            'page' => static::slug(),
            'action' => 'assigned',
            'badge_id' => $badgeId,
        ), admin_url('admin.php'));

        $template = Config::path() . 'includes/templates/admin/badges/assign.php';
        if (is_readable($template)) {
            include $template;
        }
    }

    private static function render_assigned_list(): void
    {
        $badgeId = isset($_GET['badge_id']) ? (int) $_GET['badge_id'] : 0;
        $badge = $badgeId > 0 ? MjBadges::get($badgeId) : null;
        if (!$badge) {
            wp_safe_redirect(add_query_arg('page', static::slug(), admin_url('admin.php')));
            exit;
        }

        $assignments = MjMemberBadges::get_all(array(
            'badge_id' => $badgeId,
            'orderby' => 'awarded_at',
            'order' => 'DESC',
            'limit' => 0,
        ));

        $badgeCriteria = MjBadgeCriteria::get_for_badge($badgeId);
        $criterionIndex = array();
        foreach ($badgeCriteria as $criterion) {
            if (!is_array($criterion)) {
                continue;
            }
            $criterionId = isset($criterion['id']) ? (int) $criterion['id'] : 0;
            if ($criterionId > 0) {
                $criterionIndex[$criterionId] = $criterion;
            }
        }

        $assignmentCriteria = array();
        if (!empty($assignments)) {
            foreach ($assignments as $assignment) {
                $memberId = isset($assignment['member_id']) ? (int) $assignment['member_id'] : 0;
                if ($memberId <= 0) {
                    continue;
                }
                $criteria = MjMemberBadgeCriteria::get_for_member_badge($memberId, $badgeId);
                $assignmentCriteria[$memberId] = $criteria;
            }
        }

        $backUrl = add_query_arg(array(
            'page' => static::slug(),
            'action' => 'assign',
            'badge_id' => $badgeId,
        ), admin_url('admin.php'));

        $assignUrl = add_query_arg(array(
            'page' => static::slug(),
            'action' => 'assign',
            'badge_id' => $badgeId,
        ), admin_url('admin.php'));

        $template = Config::path() . 'includes/templates/admin/badges/assigned.php';
        if (is_readable($template)) {
            include $template;
        }
    }

    public static function handle_save_badge(): void
    {
        if (!current_user_can(Config::contactCapability())) {
            wp_die(esc_html__('Droits insuffisants.', 'mj-member'));
        }

        check_admin_referer('mj_member_save_badge');

        $badgeId = isset($_POST['badge_id']) ? (int) $_POST['badge_id'] : 0;

        $payload = array(
            'label'          => isset($_POST['label']) ? sanitize_text_field(wp_unslash((string) $_POST['label'])) : '',
            'display_order'  => isset($_POST['display_order']) ? (int) $_POST['display_order'] : 0,
            'status'         => isset($_POST['status']) ? sanitize_text_field(wp_unslash((string) $_POST['status'])) : MjBadges::STATUS_ACTIVE,
            'prompt'         => isset($_POST['prompt']) ? sanitize_textarea_field(wp_unslash((string) $_POST['prompt'])) : '',
            'icon'           => isset($_POST['icon']) ? sanitize_key(wp_unslash((string) $_POST['icon'])) : '',
        );

        if (isset($_POST['slug'])) {
            $slugValue = sanitize_title(wp_unslash((string) $_POST['slug']));
            if ($slugValue !== '') {
                $payload['slug'] = $slugValue;
            }
        }

        if (isset($_POST['summary'])) {
            $payload['summary'] = sanitize_textarea_field(wp_unslash((string) $_POST['summary']));
        }

        if (isset($_POST['description'])) {
            $payload['description'] = wp_kses_post(wp_unslash((string) $_POST['description']));
        }

        $criteriaLabels = array();
        if (isset($_POST['criteria'])) {
            $rawCriteria = wp_unslash($_POST['criteria']);
            $lines = array();

            if (is_array($rawCriteria)) {
                $lines = $rawCriteria;
            } else {
                $lines = preg_split('/\r\n|\r|\n/', (string) $rawCriteria);
            }

            if (!is_array($lines)) {
                $lines = array();
            }

            foreach ($lines as $entry) {
                $candidate = sanitize_text_field(trim((string) $entry));
                if ($candidate !== '') {
                    $criteriaLabels[] = $candidate;
                }
            }

            $payload['criteria'] = $criteriaLabels;
        }

        $payload['image_id'] = isset($_POST['image_id']) ? absint($_POST['image_id']) : 0;

        if ($badgeId > 0) {
            $result = MjBadges::update($badgeId, $payload);
        } else {
            $result = MjBadges::create($payload);
            if (!is_wp_error($result)) {
                $badgeId = (int) $result;
            }
        }

        if (!is_wp_error($result) && $badgeId > 0) {
            $syncResult = MjBadgeCriteria::sync_labels($badgeId, $criteriaLabels);
            if (is_wp_error($syncResult)) {
                $result = $syncResult;
            }
        }

        if (is_wp_error($result)) {
            $redirect = add_query_arg(array(
                'page' => static::slug(),
                'action' => $badgeId > 0 ? 'edit' : 'new',
                'id' => $badgeId,
                'error' => rawurlencode($result->get_error_message()),
            ), admin_url('admin.php'));
        } else {
            $redirect = add_query_arg(array(
                'page' => static::slug(),
                'action' => 'edit',
                'id' => $badgeId,
                'mj_badges_notice' => 'saved',
            ), admin_url('admin.php'));
        }

        wp_safe_redirect($redirect);
        exit;
    }

    public static function handle_delete_badge(): void
    {
        if (!current_user_can(Config::contactCapability())) {
            wp_die(esc_html__('Droits insuffisants.', 'mj-member'));
        }

        $badgeId = isset($_REQUEST['badge_id']) ? (int) $_REQUEST['badge_id'] : 0;
        check_admin_referer(static::deleteNonceAction($badgeId));

        $badgeSlug = '';

        if ($badgeId > 0) {
            $badgeData = MjBadges::get($badgeId);
            if (is_array($badgeData) && !empty($badgeData['slug'])) {
                $badgeSlug = sanitize_title((string) $badgeData['slug']);
            }
            $result = MjBadges::delete($badgeId);
        } else {
            $result = new \WP_Error('missing_id', __('Badge introuvable.', 'mj-member'));
        }

        $args = array(
            'page' => static::slug(),
        );

        if (is_wp_error($result)) {
            $args['mj_badges_notice'] = 'error';
            $args['mj_badges_error'] = rawurlencode($result->get_error_message());
        } else {
            $args['mj_badges_notice'] = 'deleted';
            if ($badgeSlug !== '' && function_exists('mj_member_mark_default_badge_removed')) {
                mj_member_mark_default_badge_removed($badgeSlug);
            }
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    public static function handle_assign_badge(): void
    {
        self::log_debug('handle_assign_badge invoked');

        if (!current_user_can(Config::contactCapability())) {
            self::log_debug('current_user_can failed, aborting');
            wp_die(esc_html__('Droits insuffisants.', 'mj-member'));
        }

        $postedNonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash((string) $_POST['_wpnonce'])) : '';
        if ($postedNonce === '' || !wp_verify_nonce($postedNonce, 'mj_member_assign_badge')) {
            self::log_debug('nonce verification failed');

            $redirect = add_query_arg(array(
                'page' => static::slug(),
                'action' => 'assign',
                'badge_id' => isset($_POST['badge_id']) ? (int) $_POST['badge_id'] : 0,
                'error' => rawurlencode(__('La vérification de sécurité a échoué. Merci de réessayer.', 'mj-member')),
            ), admin_url('admin.php'));

            wp_safe_redirect($redirect);
            exit;
        }

        $memberId = isset($_POST['member_id']) ? (int) $_POST['member_id'] : 0;
        $badgeId = isset($_POST['badge_id']) ? (int) $_POST['badge_id'] : 0;
        $status = isset($_POST['status']) ? sanitize_text_field(wp_unslash((string) $_POST['status'])) : MjMemberBadges::STATUS_AWARDED;
        $evidence = isset($_POST['evidence']) ? wp_kses_post(wp_unslash((string) $_POST['evidence'])) : '';
        $awardedAt = isset($_POST['awarded_at']) ? sanitize_text_field(wp_unslash((string) $_POST['awarded_at'])) : '';

        self::log_debug(sprintf('assign handler start badge=%d member=%d', $badgeId, $memberId));

        $criteriaIds = array();
        if (isset($_POST['criteria_ids'])) {
            $rawCriteriaIds = wp_unslash($_POST['criteria_ids']);
            if (!is_array($rawCriteriaIds)) {
                $rawCriteriaIds = array($rawCriteriaIds);
            }
            foreach ($rawCriteriaIds as $candidate) {
                $criterionId = (int) $candidate;
                if ($criterionId > 0) {
                    $criteriaIds[] = $criterionId;
                }
            }
        }

        $payload = array(
            'member_id' => $memberId,
            'badge_id'  => $badgeId,
            'status'    => $status,
            'evidence'  => $evidence,
            'awarded_by_user_id' => get_current_user_id(),
        );

        if ($awardedAt !== '') {
            $payload['awarded_at'] = $awardedAt;
        }

        $result = MjMemberBadges::create($payload);
        self::log_debug(is_wp_error($result) ? 'create error: ' . $result->get_error_message() : 'badge record id=' . (int) $result);

        if (!is_wp_error($result)) {
            $criteriaSync = MjMemberBadgeCriteria::sync_awards($memberId, $badgeId, $criteriaIds, get_current_user_id());
            if (is_wp_error($criteriaSync)) {
                $result = $criteriaSync;
                self::log_debug('criteria sync error: ' . $criteriaSync->get_error_message());
            } else {
                self::log_debug('criteria synced count=' . count($criteriaIds));
            }
        }

        if (is_wp_error($result)) {
            $redirect = add_query_arg(array(
                'page' => static::slug(),
                'action' => 'assign',
                'badge_id' => $badgeId,
                'error' => rawurlencode($result->get_error_message()),
            ), admin_url('admin.php'));
            self::log_debug('redirect with error');
        } else {
            $redirect = add_query_arg(array(
                'page' => static::slug(),
                'mj_badges_notice' => 'assigned',
            ), admin_url('admin.php'));
            self::log_debug('redirect success');
        }

        wp_safe_redirect($redirect);
        exit;
    }
}
