<?php
/**
 * AJAX handlers for Leave Requests (front-end).
 *
 * @package MJ_Member
 */

if (!defined('ABSPATH')) {
    exit;
}

use Mj\Member\Classes\Crud\MjLeaveRequests;
use Mj\Member\Classes\Crud\MjLeaveTypes;
use Mj\Member\Classes\Crud\MjLeaveQuotas;
use Mj\Member\Classes\Crud\MjMembers;
use Mj\Member\Classes\MjRoles;
use Mj\Member\Core\Config;

/**
 * Localize leave requests script data.
 *
 * @return void
 */
function mj_member_leave_requests_localize(): void
{
    $userId = get_current_user_id();
    $memberObj = $userId ? MjMembers::getByWpUserId($userId) : null;
    $member = $memberObj ? $memberObj->toArray() : null;
    $memberId = $member ? (int) $member['id'] : 0;
    $isCoordinator = $member && in_array($member['role'], [MjRoles::COORDINATEUR], true);
    $isAnimateur = $member && in_array($member['role'], [MjRoles::ANIMATEUR, MjRoles::COORDINATEUR], true);

    $types = MjLeaveTypes::get_active();
    $year = (int) date('Y');

    // Quotas and usage for current member
    $quotas = [];
    $usage = [];
    if ($memberId && $isAnimateur) {
        // Get quotas from the yearly quotas table
        $quotas = MjLeaveQuotas::get_quotas_for_member($memberId, $year);
        foreach ($types as $type) {
            $usage[$type->slug] = MjLeaveRequests::get_days_used($memberId, (int) $type->id, $year);
        }
    }

    // Own requests for current year
    $ownRequests = $memberId ? MjLeaveRequests::get_by_member($memberId, ['year' => $year]) : [];

    // Pending requests for coordinators
    $pendingRequests = [];
    $allAnimateurs = [];
    if ($isCoordinator) {
        $pendingRequests = MjLeaveRequests::get_pending();
        // Enrich with member info (name, avatar)
        $pendingRequests = MjLeaveRequests::enrich($pendingRequests);
        $allAnimateurs = MjMembers::get_all([
            'filters' => [
                'roles' => [MjRoles::ANIMATEUR, MjRoles::COORDINATEUR],
            ],
        ]);
        // Filter to keep only active members (status check not in CRUD filters)
        $allAnimateurs = array_filter($allAnimateurs, function ($m) {
            return $m->status === 'active';
        });
    }

    // Transform types to ensure boolean values for JS
    $typesForJs = array_map(function ($t) {
        return [
            'id' => (int) $t->id,
            'name' => $t->name,
            'slug' => $t->slug,
            'requires_document' => (bool) $t->requires_document,
            'requires_validation' => (bool) $t->requires_validation,
            'color' => $t->color,
        ];
    }, $types);

    wp_localize_script('mj-member-leave-requests', 'mjLeaveRequests', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('mj-leave-requests'),
        'memberId' => $memberId,
        'isCoordinator' => $isCoordinator,
        'isAnimateur' => $isAnimateur,
        'types' => array_values($typesForJs),
        'quotas' => $quotas,
        'usage' => $usage,
        'year' => $year,
        'ownRequests' => array_values($ownRequests),
        'pendingRequests' => array_values($pendingRequests),
        'animateurs' => $isCoordinator ? array_values(array_map(function ($m) {
            return [
                'id' => (int) $m->id,
                'name' => trim($m->first_name . ' ' . $m->last_name),
                'role' => $m->role,
            ];
        }, $allAnimateurs)) : [],
        'i18n' => [
            'title' => __('Demandes de Congés', 'mj-member'),
            'newRequest' => __('Nouvelle demande', 'mj-member'),
            'pending' => __('En attente', 'mj-member'),
            'approved' => __('Approuvée', 'mj-member'),
            'rejected' => __('Refusée', 'mj-member'),
            'myRequests' => __('Mes demandes', 'mj-member'),
            'pendingRequests' => __('À valider', 'mj-member'),
            'teamView' => __('Vue équipe', 'mj-member'),
            'allRequests' => __('Toutes les demandes', 'mj-member'),
            'animateurs' => __('Animateurs', 'mj-member'),
            'selectAnimateur' => __('Sélectionnez un animateur pour voir ses demandes', 'mj-member'),
            'requests' => __('Demandes', 'mj-member'),
            'approve' => __('Approuver', 'mj-member'),
            'reject' => __('Refuser', 'mj-member'),
            'cancel' => __('Annuler', 'mj-member'),
            'close' => __('Fermer', 'mj-member'),
            'submit' => __('Soumettre', 'mj-member'),
            'selectType' => __('Sélectionnez un type', 'mj-member'),
            'selectDates' => __('Sélectionnez les dates', 'mj-member'),
            'reason' => __('Motif (optionnel)', 'mj-member'),
            'certificate' => __('Certificat médical', 'mj-member'),
            'certificateRequired' => __('Certificat médical requis', 'mj-member'),
            'uploadCertificate' => __('Télécharger le certificat', 'mj-member'),
            'rejectionReason' => __('Motif du refus', 'mj-member'),
            'rejectionReasonRequired' => __('Veuillez indiquer un motif de refus.', 'mj-member'),
            'noRequests' => __('Aucune demande pour le moment.', 'mj-member'),
            'daysUsed' => __('jours pris', 'mj-member'),
            'daysRemaining' => __('jours restants', 'mj-member'),
            'autoApproved' => __('Approbation automatique', 'mj-member'),
            'success' => __('Demande créée avec succès.', 'mj-member'),
            'error' => __('Une erreur est survenue.', 'mj-member'),
            'confirmCancel' => __('Êtes-vous sûr de vouloir annuler cette demande ?', 'mj-member'),
            'selectedDates' => __('Dates sélectionnées', 'mj-member'),
            'noDatesSelected' => __('Aucune date sélectionnée', 'mj-member'),
            'weekdays' => [
                __('Lun', 'mj-member'),
                __('Mar', 'mj-member'),
                __('Mer', 'mj-member'),
                __('Jeu', 'mj-member'),
                __('Ven', 'mj-member'),
                __('Sam', 'mj-member'),
                __('Dim', 'mj-member'),
            ],
            'months' => [
                __('Janvier', 'mj-member'),
                __('Février', 'mj-member'),
                __('Mars', 'mj-member'),
                __('Avril', 'mj-member'),
                __('Mai', 'mj-member'),
                __('Juin', 'mj-member'),
                __('Juillet', 'mj-member'),
                __('Août', 'mj-member'),
                __('Septembre', 'mj-member'),
                __('Octobre', 'mj-member'),
                __('Novembre', 'mj-member'),
                __('Décembre', 'mj-member'),
            ],
        ],
    ]);
}

/**
 * Register AJAX actions for leave requests.
 *
 * @return void
 */
function mj_member_register_leave_requests_ajax(): void
{
    add_action('wp_ajax_mj_leave_request_create', 'mj_member_leave_request_create_handler');
    add_action('wp_ajax_mj_leave_request_cancel', 'mj_member_leave_request_cancel_handler');
    add_action('wp_ajax_mj_leave_request_get', 'mj_member_leave_request_get_handler');
    add_action('wp_ajax_mj_leave_request_certificate', 'mj_member_leave_request_certificate_handler');
}
add_action('init', 'mj_member_register_leave_requests_ajax');

/**
 * Handle creating a leave request.
 *
 * @return void
 */
function mj_member_leave_request_create_handler(): void
{
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'mj-leave-requests')) {
        wp_send_json_error(['message' => __('Sécurité échouée.', 'mj-member')], 403);
    }

    // Get current member
    $userId = get_current_user_id();
    if (!$userId) {
        wp_send_json_error(['message' => __('Vous devez être connecté.', 'mj-member')], 403);
    }

    $memberObj = MjMembers::getByWpUserId($userId);
    $member = $memberObj ? $memberObj->toArray() : null;
    if (!$member || !in_array($member['role'], [MjRoles::ANIMATEUR, MjRoles::COORDINATEUR], true)) {
        wp_send_json_error(['message' => __('Accès refusé.', 'mj-member')], 403);
    }

    $memberId = (int) $member['id'];

    // Validate type
    $typeId = isset($_POST['type_id']) ? (int) $_POST['type_id'] : 0;
    $type = MjLeaveTypes::get_by_id($typeId);
    if (!$type || !$type->is_active) {
        wp_send_json_error(['message' => __('Type de congé invalide.', 'mj-member')], 400);
    }

    // Validate dates
    $datesRaw = isset($_POST['dates']) ? sanitize_text_field(wp_unslash($_POST['dates'])) : '';
    $dates = json_decode($datesRaw, true);
    if (!is_array($dates) || empty($dates)) {
        wp_send_json_error(['message' => __('Veuillez sélectionner au moins une date.', 'mj-member')], 400);
    }

    // Sanitize and validate each date
    $validDates = [];
    foreach ($dates as $date) {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $validDates[] = $date;
        }
    }
    if (empty($validDates)) {
        wp_send_json_error(['message' => __('Format de date invalide.', 'mj-member')], 400);
    }
    sort($validDates);

    // Handle file upload for types requiring document
    $certificateFile = null;
    if ($type->requires_document) {
        if (empty($_FILES['certificate']) || $_FILES['certificate']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(['message' => __('Un certificat médical est requis.', 'mj-member')], 400);
        }

        // Validate file type
        $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/gif'];
        $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'gif'];
        $fileType = wp_check_filetype($_FILES['certificate']['name']);
        $mimeType = $fileType['type'];
        $extension = strtolower($fileType['ext']);
        
        if (!in_array($mimeType, $allowedTypes, true) || !in_array($extension, $allowedExtensions, true)) {
            wp_send_json_error(['message' => __('Format de fichier non supporté. Utilisez PDF, JPG, PNG ou GIF.', 'mj-member')], 400);
        }

        // Validate file size (max 10MB)
        $maxSize = 10 * 1024 * 1024;
        if ($_FILES['certificate']['size'] > $maxSize) {
            wp_send_json_error(['message' => __('Le fichier est trop volumineux. Maximum 10 Mo.', 'mj-member')], 400);
        }

        // Create secure directory if not exists
        $uploadDir = MJ_MEMBER_PATH . 'data/certifs/';
        if (!file_exists($uploadDir)) {
            wp_mkdir_p($uploadDir);
            // Create .htaccess if missing
            $htaccess = $uploadDir . '.htaccess';
            if (!file_exists($htaccess)) {
                file_put_contents($htaccess, "Order deny,allow\nDeny from all\n");
            }
        }

        // Generate unique secure filename (hash + timestamp + extension)
        $uniqueId = wp_generate_password(16, false);
        $timestamp = time();
        $secureFilename = sprintf('%d_%s_%s.%s', $memberId, $timestamp, $uniqueId, $extension);
        $targetPath = $uploadDir . $secureFilename;

        // Move uploaded file
        if (!move_uploaded_file($_FILES['certificate']['tmp_name'], $targetPath)) {
            wp_send_json_error(['message' => __('Erreur lors de l\'enregistrement du fichier.', 'mj-member')], 500);
        }

        $certificateFile = $secureFilename;
    }

    // Get reason
    $reason = isset($_POST['reason']) ? sanitize_textarea_field(wp_unslash($_POST['reason'])) : '';

    // Determine status based on type
    $status = $type->requires_validation ? MjLeaveRequests::STATUS_PENDING : MjLeaveRequests::STATUS_APPROVED;

    // Create the request
    $data = [
        'member_id' => $memberId,
        'type_id' => $typeId,
        'status' => $status,
        'dates' => wp_json_encode($validDates),
        'reason' => $reason,
        'certificate_file' => $certificateFile,
    ];

    // If auto-approved, set reviewed fields
    if ($status === MjLeaveRequests::STATUS_APPROVED) {
        $data['reviewed_by'] = $memberId; // Self-approved
        $data['reviewed_at'] = current_time('mysql');
        $data['reviewer_comment'] = __('Approuvé automatiquement', 'mj-member');
    }

    $requestId = MjLeaveRequests::create($data);
    if (!$requestId) {
        wp_send_json_error(['message' => __('Erreur lors de la création de la demande.', 'mj-member')], 500);
    }

    // Send notification if pending
    if ($status === MjLeaveRequests::STATUS_PENDING) {
        mj_member_notify_leave_request_created($requestId, $member, $type, $validDates);
    }

    // Return updated request
    $request = MjLeaveRequests::get_by_id($requestId);

    wp_send_json_success([
        'message' => $status === MjLeaveRequests::STATUS_APPROVED
            ? __('Demande créée et approuvée automatiquement.', 'mj-member')
            : __('Demande créée avec succès.', 'mj-member'),
        'request' => $request,
        'autoApproved' => $status === MjLeaveRequests::STATUS_APPROVED,
    ]);
}

/**
 * Handle cancelling a leave request.
 *
 * @return void
 */
function mj_member_leave_request_cancel_handler(): void
{
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'mj-leave-requests')) {
        wp_send_json_error(['message' => __('Sécurité échouée.', 'mj-member')], 403);
    }

    // Get current member
    $userId = get_current_user_id();
    if (!$userId) {
        wp_send_json_error(['message' => __('Vous devez être connecté.', 'mj-member')], 403);
    }

    $memberObj = MjMembers::getByWpUserId($userId);
    $member = $memberObj ? $memberObj->toArray() : null;
    if (!$member) {
        wp_send_json_error(['message' => __('Membre non trouvé.', 'mj-member')], 403);
    }

    $memberId = (int) $member['id'];
    $requestId = isset($_POST['request_id']) ? (int) $_POST['request_id'] : 0;

    // Get the request
    $request = MjLeaveRequests::get_by_id($requestId);
    if (!$request) {
        wp_send_json_error(['message' => __('Demande non trouvée.', 'mj-member')], 404);
    }

    // Check ownership
    if ((int) $request->member_id !== $memberId) {
        wp_send_json_error(['message' => __('Vous ne pouvez annuler que vos propres demandes.', 'mj-member')], 403);
    }

    // Can cancel pending requests anytime, or approved requests if all dates are in the future
    if ($request->status === MjLeaveRequests::STATUS_REJECTED) {
        wp_send_json_error(['message' => __('Les demandes refusées ne peuvent pas être supprimées.', 'mj-member')], 400);
    }

    // For approved requests, check that all dates are in the future
    if ($request->status === MjLeaveRequests::STATUS_APPROVED) {
        $dates = json_decode($request->dates, true) ?: [];
        $today = wp_date('Y-m-d');
        $hasPastDates = false;
        foreach ($dates as $date) {
            if ($date <= $today) {
                $hasPastDates = true;
                break;
            }
        }
        if ($hasPastDates) {
            wp_send_json_error(['message' => __('Impossible de supprimer une demande dont les dates sont passées.', 'mj-member')], 400);
        }
    }

    // Delete the request
    $deleted = MjLeaveRequests::delete($requestId);
    if (!$deleted) {
        wp_send_json_error(['message' => __('Erreur lors de l\'annulation.', 'mj-member')], 500);
    }

    wp_send_json_success([
        'message' => __('Demande annulée avec succès.', 'mj-member'),
    ]);
}

/**
 * Handle getting leave requests for current member.
 *
 * @return void
 */
function mj_member_leave_request_get_handler(): void
{
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'mj-leave-requests')) {
        wp_send_json_error(['message' => __('Sécurité échouée.', 'mj-member')], 403);
    }

    $userId = get_current_user_id();
    if (!$userId) {
        wp_send_json_error(['message' => __('Vous devez être connecté.', 'mj-member')], 403);
    }

    $memberObj = MjMembers::getByWpUserId($userId);
    $member = $memberObj ? $memberObj->toArray() : null;
    if (!$member) {
        wp_send_json_error(['message' => __('Membre non trouvé.', 'mj-member')], 403);
    }

    $memberId = (int) $member['id'];
    $isCoordinator = in_array($member['role'], [MjRoles::COORDINATEUR], true);
    $year = isset($_POST['year']) ? (int) $_POST['year'] : (int) date('Y');
    // Validate year range (5 years back, 1 year ahead)
    $currentYear = (int) date('Y');
    if ($year < $currentYear - 5 || $year > $currentYear + 1) {
        $year = $currentYear;
    }

    // Get types
    $types = MjLeaveTypes::get_active();

    // Get quotas for the selected year
    $quotas = MjLeaveQuotas::get_quotas_for_member($memberId, $year);

    // Calculate usage
    $usage = [];
    foreach ($types as $type) {
        $usage[$type->slug] = MjLeaveRequests::get_days_used($memberId, (int) $type->id, $year);
    }

    // Get own requests for selected year
    $ownRequests = MjLeaveRequests::get_by_member($memberId, ['year' => $year]);

    // Pending requests for coordinators (always show all pending, not filtered by year)
    $pendingRequests = [];
    if ($isCoordinator) {
        $pendingRequests = MjLeaveRequests::get_pending();
        // Enrich with member info (name, avatar)
        $pendingRequests = MjLeaveRequests::enrich($pendingRequests);
    }

    wp_send_json_success([
        'quotas' => $quotas,
        'usage' => $usage,
        'year' => $year,
        'ownRequests' => array_values($ownRequests),
        'pendingRequests' => array_values($pendingRequests),
    ]);
}

/**
 * Notify coordinators about a new leave request.
 *
 * @param int      $requestId Request ID.
 * @param array    $member    Member data.
 * @param stdClass $type      Leave type data.
 * @param array    $dates     Array of dates.
 * @return void
 */
function mj_member_notify_leave_request_created(int $requestId, array $member, $type, array $dates): void
{
    if (!function_exists('mj_member_record_notification')) {
        return;
    }

    $memberName = trim($member['first_name'] . ' ' . $member['last_name']);
    $dateCount = count($dates);
    $dateRange = $dateCount === 1
        ? date_i18n('j F Y', strtotime($dates[0]))
        : sprintf(
            __('du %s au %s', 'mj-member'),
            date_i18n('j F', strtotime($dates[0])),
            date_i18n('j F Y', strtotime($dates[$dateCount - 1]))
        );

    mj_member_record_notification(
        [
            'type' => 'leave_request_created',
            'title' => __('Nouvelle demande de congé', 'mj-member'),
            'excerpt' => sprintf(
                /* translators: 1: member name, 2: leave type, 3: date range */
                __('%1$s demande un %2$s %3$s (%4$d jour(s))', 'mj-member'),
                $memberName,
                $type->name,
                $dateRange,
                $dateCount
            ),
            'url' => home_url('/ressources-humaine/?member_id=' . (int) $member['id']),
            'payload' => [
                'request_id' => $requestId,
                'member_id' => (int) $member['id'],
                'type_slug' => $type->slug,
            ],
        ],
        [['role' => MjRoles::COORDINATEUR]]
    );
}
/**
 * Handle secure certificate download.
 * Only the request owner or a coordinator can access the certificate.
 *
 * @return void
 */
function mj_member_leave_request_certificate_handler(): void
{
    // Clean output buffers first
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Verify nonce
    if (!isset($_GET['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['nonce'])), 'mj-leave-requests')) {
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Erreur: Nonce invalide ou expiré. Rechargez la page et réessayez.';
        exit;
    }

    $userId = get_current_user_id();
    if (!$userId) {
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Erreur: Vous devez être connecté.';
        exit;
    }

    $requestId = isset($_GET['request_id']) ? (int) $_GET['request_id'] : 0;
    if ($requestId <= 0) {
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Erreur: ID de demande invalide.';
        exit;
    }

    // Get request
    $request = MjLeaveRequests::get_by_id($requestId);
    if (!$request || empty($request->certificate_file)) {
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Erreur: Certificat non trouvé.';
        exit;
    }

    // Get current member
    $memberObj = MjMembers::getByWpUserId($userId);
    $member = $memberObj ? $memberObj->toArray() : null;
    if (!$member) {
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Erreur: Membre non trouvé.';
        exit;
    }

    // Check access: owner or coordinator
    $isOwner = (int) $member['id'] === (int) $request->member_id;
    $isCoordinator = in_array($member['role'], [MjRoles::COORDINATEUR], true);

    if (!$isOwner && !$isCoordinator) {
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Erreur: Accès refusé.';
        exit;
    }

    // Build file path
    $filePath = MJ_MEMBER_PATH . 'data/certifs/' . sanitize_file_name($request->certificate_file);

    if (!file_exists($filePath)) {
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Erreur: Fichier non trouvé sur le serveur.';
        exit;
    }

    // Get mime type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $filePath);
    finfo_close($finfo);

    // Allowed mime types
    $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($mimeType, $allowedTypes, true)) {
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Erreur: Type de fichier non autorisé.';
        exit;
    }

    // Serve file
    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: inline; filename="certificat_' . $requestId . '.' . pathinfo($filePath, PATHINFO_EXTENSION) . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: private, max-age=3600');
    header('X-Content-Type-Options: nosniff');
    
    readfile($filePath);
    exit;
}