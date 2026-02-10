<?php

use Mj\Member\Classes\Crud\MjLeaveRequests;
use Mj\Member\Classes\Crud\MjLeaveTypes;
use Mj\Member\Classes\Crud\MjLeaveQuotas;
use Mj\Member\Classes\Crud\MjMembers;
use Mj\Member\Classes\Crud\MjMemberWorkSchedules;
use Mj\Member\Classes\MjRoles;
use Mj\Member\Core\AssetsManager;

if (!defined('ABSPATH')) {
    exit;
}

AssetsManager::requirePackage('leave-requests');

$title = isset($title) ? (string) $title : '';
$intro = isset($intro) ? (string) $intro : '';
$showQuotas = isset($showQuotas) ? (bool) $showQuotas : true;

$isPreview = function_exists('is_elementor_preview') && is_elementor_preview();
$currentUserId = get_current_user_id();
$currentMember = null;
$isCoordinator = false;
$hasAccess = false;

if (!$isPreview && $currentUserId > 0) {
    // Find member linked to user
    if (class_exists('Mj\\Member\\Classes\\Crud\\MjMembers')) {
        $members = MjMembers::get_all(array(
            'filters' => array('wp_user_id' => $currentUserId),
            'limit' => 1,
        ));
        if (!empty($members)) {
            $currentMember = $members[0];
            $isCoordinator = MjRoles::isCoordinateur($currentMember->role);
            $hasAccess = MjRoles::isStaff($currentMember->role);
        }
    }
}

if ($isPreview) {
    $hasAccess = true;
    $isCoordinator = true;
}

// Get leave types
$leaveTypes = array();
if (class_exists('Mj\\Member\\Classes\\Crud\\MjLeaveTypes')) {
    $types = MjLeaveTypes::get_active();
    foreach ($types as $type) {
        $leaveTypes[] = array(
            'id' => (int) $type->id,
            'name' => $type->name,
            'slug' => $type->slug,
            'color' => $type->color,
            'requiresDocument' => (bool) $type->requires_document,
            'requiresValidation' => (bool) $type->requires_validation,
        );
    }
}

// Get member quotas and usage (only for current member)
$quotas = array();
$usage = array();
$currentYear = (int) gmdate('Y');

if (!$isPreview && $currentMember) {
    // Get quotas from the yearly quotas table
    if (class_exists('Mj\\Member\\Classes\\Crud\\MjLeaveQuotas')) {
        $quotas = MjLeaveQuotas::get_quotas_for_member((int) $currentMember->id, $currentYear);
    }
    
    // Get usage per type
    if (class_exists('Mj\\Member\\Classes\\Crud\\MjLeaveRequests')) {
        foreach ($leaveTypes as $type) {
            $typeObj = MjLeaveTypes::get_by_slug($type['slug']);
            if ($typeObj) {
                $usage[$type['slug']] = MjLeaveRequests::get_days_used((int) $currentMember->id, (int) $typeObj->id, $currentYear);
            }
        }
    }
}

// Sample data for preview
$sampleRequests = array();
$sampleMembers = array();

if ($isPreview) {
    $now = current_time('timestamp');
    
    $quotas = array(
        'paid' => 25,
        'unpaid' => 10,
        'exceptional' => 5,
        'recovery' => 10,
    );
    
    $usage = array(
        'paid' => 5,
        'unpaid' => 0,
        'exceptional' => 1,
        'recovery' => 2,
        'sick' => 3,
    );
    
    $sampleRequests = array(
        array(
            'id' => 101,
            'member_id' => 1,
            'member_name' => 'Jean Dupont',
            'type_id' => 1,
            'type_name' => 'Congé payé',
            'type_slug' => 'paid',
            'type_color' => '#22c55e',
            'status' => MjLeaveRequests::STATUS_PENDING,
            'status_label' => __('En attente', 'mj-member'),
            'dates_array' => array(
                wp_date('Y-m-d', $now + (7 * DAY_IN_SECONDS)),
                wp_date('Y-m-d', $now + (8 * DAY_IN_SECONDS)),
                wp_date('Y-m-d', $now + (9 * DAY_IN_SECONDS)),
            ),
            'days_count' => 3,
            'reason' => 'Vacances familiales',
            'created_at' => wp_date('Y-m-d H:i:s', $now - DAY_IN_SECONDS),
            'reviewer_comment' => '',
        ),
        array(
            'id' => 102,
            'member_id' => 1,
            'member_name' => 'Jean Dupont',
            'type_id' => 5,
            'type_name' => 'Maladie',
            'type_slug' => 'sick',
            'type_color' => '#ef4444',
            'status' => MjLeaveRequests::STATUS_APPROVED,
            'status_label' => __('Approuvée', 'mj-member'),
            'dates_array' => array(
                wp_date('Y-m-d', $now - (5 * DAY_IN_SECONDS)),
                wp_date('Y-m-d', $now - (4 * DAY_IN_SECONDS)),
            ),
            'days_count' => 2,
            'reason' => 'Grippe',
            'created_at' => wp_date('Y-m-d H:i:s', $now - (6 * DAY_IN_SECONDS)),
            'reviewer_comment' => 'Approbation automatique',
        ),
        array(
            'id' => 103,
            'member_id' => 2,
            'member_name' => 'Marie Martin',
            'type_id' => 4,
            'type_name' => 'Récupération',
            'type_slug' => 'recovery',
            'type_color' => '#3b82f6',
            'status' => MjLeaveRequests::STATUS_PENDING,
            'status_label' => __('En attente', 'mj-member'),
            'dates_array' => array(
                wp_date('Y-m-d', $now + (14 * DAY_IN_SECONDS)),
            ),
            'days_count' => 1,
            'reason' => 'Heures supplémentaires festival',
            'created_at' => wp_date('Y-m-d H:i:s', $now - (2 * DAY_IN_SECONDS)),
            'reviewer_comment' => '',
        ),
    );
    
    $sampleMembers = array(
        array('id' => 1, 'name' => 'Jean Dupont', 'role' => 'animateur'),
        array('id' => 2, 'name' => 'Marie Martin', 'role' => 'animateur'),
        array('id' => 3, 'name' => 'Pierre Durand', 'role' => 'coordinateur'),
    );
    
    if (empty($leaveTypes)) {
        $leaveTypes = array(
            array('id' => 1, 'name' => 'Congé payé', 'slug' => 'paid', 'color' => '#22c55e', 'requiresDocument' => false, 'requiresValidation' => true),
            array('id' => 2, 'name' => 'Congé sans solde', 'slug' => 'unpaid', 'color' => '#f59e0b', 'requiresDocument' => false, 'requiresValidation' => true),
            array('id' => 3, 'name' => 'Congé exceptionnel', 'slug' => 'exceptional', 'color' => '#8b5cf6', 'requiresDocument' => false, 'requiresValidation' => true),
            array('id' => 4, 'name' => 'Récupération', 'slug' => 'recovery', 'color' => '#3b82f6', 'requiresDocument' => false, 'requiresValidation' => true),
            array('id' => 5, 'name' => 'Maladie', 'slug' => 'sick', 'color' => '#ef4444', 'requiresDocument' => true, 'requiresValidation' => false),
        );
    }
    
    // Sample work schedule for preview (Mon-Fri)
    $workSchedule = array(
        'startDate' => wp_date('Y-m-d', strtotime('-1 year')),
        'endDate' => null,
        'schedule' => array(
            array('day' => 1, 'start' => '09:00', 'end' => '17:00'), // Monday
            array('day' => 2, 'start' => '09:00', 'end' => '17:00'), // Tuesday
            array('day' => 3, 'start' => '09:00', 'end' => '17:00'), // Wednesday
            array('day' => 4, 'start' => '09:00', 'end' => '17:00'), // Thursday
            array('day' => 5, 'start' => '09:00', 'end' => '17:00'), // Friday
        ),
    );
    
    // Sample reserved dates for preview
    $reservedDates = array(
        wp_date('Y-m-d', $now + (7 * DAY_IN_SECONDS)) => array('status' => 'pending', 'type_id' => 1),
        wp_date('Y-m-d', $now + (8 * DAY_IN_SECONDS)) => array('status' => 'pending', 'type_id' => 1),
        wp_date('Y-m-d', $now - (5 * DAY_IN_SECONDS)) => array('status' => 'approved', 'type_id' => 5),
    );
}

// Get real requests for current member
$myRequests = array();
$pendingRequests = array();
$allStaffMembers = array();
$workSchedule = null;
$reservedDates = array();

if (!$isPreview && $currentMember && class_exists('Mj\\Member\\Classes\\Crud\\MjLeaveRequests')) {
    $raw = MjLeaveRequests::get_by_member((int) $currentMember->id, array('limit' => 50));
    $myRequests = MjLeaveRequests::enrich($raw);
    
    // Build reserved dates (dates already used by pending or approved requests)
    foreach ($raw as $req) {
        if ($req->status === MjLeaveRequests::STATUS_REJECTED) {
            continue;
        }
        $dates = json_decode($req->dates, true) ?: [];
        foreach ($dates as $d) {
            $reservedDates[$d] = array(
                'status' => $req->status,
                'type_id' => (int) $req->type_id,
            );
        }
    }
    
    // Get active work schedule for member
    if (class_exists('Mj\\Member\\Classes\\Crud\\MjMemberWorkSchedules')) {
        $activeSchedule = MjMemberWorkSchedules::get_active_for_member((int) $currentMember->id);
        if ($activeSchedule) {
            $decoded = is_string($activeSchedule->schedule) 
                ? json_decode($activeSchedule->schedule, true) 
                : $activeSchedule->schedule;
            $workSchedule = array(
                'startDate' => $activeSchedule->start_date,
                'endDate' => $activeSchedule->end_date,
                'schedule' => is_array($decoded) ? $decoded : [],
            );
        }
    }
    
    // For coordinators, get pending requests from all staff
    if ($isCoordinator) {
        $rawPending = MjLeaveRequests::get_pending(array('limit' => 100));
        $pendingRequests = MjLeaveRequests::enrich($rawPending);
        
        // Get all staff members
        $allStaff = MjMembers::get_all(array(
            'filters' => array('roles' => array(MjRoles::ANIMATEUR, MjRoles::COORDINATEUR)),
            'limit' => 100,
        ));
        foreach ($allStaff as $staff) {
            $allStaffMembers[] = array(
                'id' => (int) $staff->id,
                'name' => trim($staff->first_name . ' ' . $staff->last_name),
                'role' => $staff->role,
            );
        }
    }
}

$introHtml = $intro !== '' ? wp_kses_post($intro) : '';

$config = array(
    'title' => $title,
    'intro' => $introHtml,
    'showQuotas' => $showQuotas,
    'hasAccess' => $hasAccess,
    'isCoordinator' => $isCoordinator,
    'memberId' => $currentMember ? (int) $currentMember->id : 0,
    'memberName' => $currentMember ? trim($currentMember->first_name . ' ' . $currentMember->last_name) : '',
    'preview' => $isPreview,
    'currentYear' => (int) gmdate('Y'),
    'leaveTypes' => $leaveTypes,
    'quotas' => $quotas,
    'usage' => $usage,
    'workSchedule' => $workSchedule,
    'reservedDates' => $reservedDates,
    'nonce' => wp_create_nonce('mj_leave_requests'),
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'i18n' => array(
        'newRequest' => __('Nouvelle demande', 'mj-member'),
        'myRequests' => __('Mes demandes', 'mj-member'),
        'pendingRequests' => __('Demandes en attente', 'mj-member'),
        'allRequests' => __('Toutes les demandes', 'mj-member'),
        'selectType' => __('Type de congé', 'mj-member'),
        'selectDates' => __('Sélectionnez les dates', 'mj-member'),
        'reason' => __('Motif (optionnel)', 'mj-member'),
        'certificate' => __('Certificat médical', 'mj-member'),
        'submit' => __('Soumettre', 'mj-member'),
        'cancel' => __('Annuler', 'mj-member'),
        'approve' => __('Approuver', 'mj-member'),
        'reject' => __('Refuser', 'mj-member'),
        'rejectReason' => __('Raison du refus', 'mj-member'),
        'days' => __('jours', 'mj-member'),
        'day' => __('jour', 'mj-member'),
        'used' => __('utilisés', 'mj-member'),
        'remaining' => __('restants', 'mj-member'),
        'noLimit' => __('Illimité', 'mj-member'),
        'pending' => __('En attente', 'mj-member'),
        'approved' => __('Approuvée', 'mj-member'),
        'rejected' => __('Refusée', 'mj-member'),
        'noRequests' => __('Aucune demande pour le moment.', 'mj-member'),
        'noPending' => __('Aucune demande en attente.', 'mj-member'),
        'autoApproved' => __('Approbation automatique', 'mj-member'),
        'requiresCertificate' => __('Certificat requis', 'mj-member'),
        'uploadCertificate' => __('Téléverser le certificat', 'mj-member'),
        'confirmCancel' => __('Annuler cette demande ?', 'mj-member'),
        'confirmApprove' => __('Approuver cette demande ?', 'mj-member'),
        'success' => __('Demande soumise avec succès.', 'mj-member'),
        'error' => __('Une erreur est survenue.', 'mj-member'),
        'selectMember' => __('Sélectionnez un animateur', 'mj-member'),
        'allMembers' => __('Tous les animateurs', 'mj-member'),
        'today' => __('Aujourd\'hui', 'mj-member'),
        'requestsInPeriod' => __('Demandes sur cette période', 'mj-member'),
        'delete' => __('Supprimer', 'mj-member'),
        'confirmDelete' => __('Supprimer cette demande ?', 'mj-member'),
        'months' => array('Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'),
        'weekdays' => array('Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'),
    ),
    'previewData' => $isPreview ? array(
        'requests' => $sampleRequests,
        'pendingRequests' => $sampleRequests,
        'members' => $sampleMembers,
    ) : array(
        'requests' => array_map(function ($r) {
            return (array) $r;
        }, $myRequests),
        'pendingRequests' => array_map(function ($r) {
            return (array) $r;
        }, $pendingRequests),
        'members' => $allStaffMembers,
    ),
);

$configJson = wp_json_encode($config);
if (!is_string($configJson)) {
    $configJson = '{}';
}
?>
<?php $fallbackNotice = (!$isPreview && !$hasAccess); ?>
<div class="mj-leave-requests-widget" data-mj-leave-requests-widget data-config="<?php echo esc_attr($configJson); ?>">
    <?php if ($fallbackNotice) : ?>
        <p class="mj-leave-requests-widget__notice mj-leave-requests-widget__notice--fallback">
            <?php esc_html_e("Vous n'avez pas accès à cette section.", 'mj-member'); ?>
        </p>
    <?php endif; ?>
    <noscript>
        <p class="mj-leave-requests-widget__notice">
            <?php esc_html_e('Ce module nécessite JavaScript pour fonctionner.', 'mj-member'); ?>
        </p>
    </noscript>
</div>
