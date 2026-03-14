<?php
/**
 * AJAX handlers for Employee Documents widget (front-end).
 *
 * Staff members can view their own documents.
 * Coordinators can view all staff documents, upload, and delete.
 *
 * @package MJ_Member
 */

if (!defined('ABSPATH')) {
    exit;
}

use Mj\Member\Classes\Crud\MjEmployeeDocuments;
use Mj\Member\Classes\Crud\MjMembers;
use Mj\Member\Classes\MjRoles;

/* ------------------------------------------------------------------ *
 * Registration                                                        *
 * ------------------------------------------------------------------ */

/**
 * Register AJAX actions for employee documents.
 *
 * @return void
 */
function mj_member_register_employee_documents_ajax(): void
{
    add_action('wp_ajax_mj_empdocs_get_my_documents', 'mj_empdocs_get_my_documents');
    add_action('wp_ajax_mj_empdocs_get_all_documents', 'mj_empdocs_get_all_documents');
    add_action('wp_ajax_mj_empdocs_upload', 'mj_empdocs_upload');
    add_action('wp_ajax_mj_empdocs_delete', 'mj_empdocs_delete');
    add_action('wp_ajax_mj_empdocs_download', 'mj_empdocs_download');
}
add_action('init', 'mj_member_register_employee_documents_ajax');

/* ------------------------------------------------------------------ *
 * Auth helper                                                         *
 * ------------------------------------------------------------------ */

/**
 * Verify nonce and return current member context.
 *
 * @return array{member: array, memberId: int, isCoordinator: bool}|null Returns context or null on failure (JSON error already sent).
 */
function mj_empdocs_verify(): ?array
{
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'mj_employee_documents')) {
        wp_send_json_error(array('message' => __('Sécurité échouée. Rechargez la page.', 'mj-member')), 403);
        return null;
    }

    $userId = get_current_user_id();
    if (!$userId) {
        wp_send_json_error(array('message' => __('Vous devez être connecté.', 'mj-member')), 403);
        return null;
    }

    $memberObj = MjMembers::getByWpUserId($userId);
    if (!$memberObj) {
        wp_send_json_error(array('message' => __('Membre introuvable.', 'mj-member')), 403);
        return null;
    }

    $member = is_array($memberObj) ? $memberObj : (method_exists($memberObj, 'toArray') ? $memberObj->toArray() : (array) $memberObj);
    $role = $member['role'] ?? '';

    if (!MjRoles::isStaff($role) && !current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Accès refusé.', 'mj-member')), 403);
        return null;
    }

    return array(
        'member'        => $member,
        'memberId'      => (int) ($member['id'] ?? 0),
        'isCoordinator' => MjRoles::isCoordinateur($role) || current_user_can('manage_options'),
    );
}

/* ------------------------------------------------------------------ *
 * Format helper                                                       *
 * ------------------------------------------------------------------ */

/**
 * Format a document row for JSON output.
 *
 * @param object $doc
 * @return array
 */
function mj_empdocs_format($doc): array
{
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
}

/* ------------------------------------------------------------------ *
 * Get my documents (staff member sees own docs)                       *
 * ------------------------------------------------------------------ */

function mj_empdocs_get_my_documents(): void
{
    $ctx = mj_empdocs_verify();
    if (!$ctx) return;

    $docs = MjEmployeeDocuments::get_all(array('member_id' => $ctx['memberId']));

    wp_send_json_success(array(
        'documents' => array_values(array_map('mj_empdocs_format', $docs)),
    ));
}

/* ------------------------------------------------------------------ *
 * Get all documents (coordinator only – grouped by member)            *
 * ------------------------------------------------------------------ */

function mj_empdocs_get_all_documents(): void
{
    $ctx = mj_empdocs_verify();
    if (!$ctx) return;

    if (!$ctx['isCoordinator']) {
        wp_send_json_error(array('message' => __('Permissions insuffisantes.', 'mj-member')), 403);
        return;
    }

    $memberId = isset($_POST['memberId']) ? absint($_POST['memberId']) : 0;

    if ($memberId > 0) {
        $docs = MjEmployeeDocuments::get_all(array('member_id' => $memberId));
    } else {
        // All staff documents
        $docs = MjEmployeeDocuments::get_all(array());
    }

    wp_send_json_success(array(
        'documents' => array_values(array_map('mj_empdocs_format', $docs)),
    ));
}

/* ------------------------------------------------------------------ *
 * Upload (coordinator only)                                           *
 * ------------------------------------------------------------------ */

function mj_empdocs_upload(): void
{
    $ctx = mj_empdocs_verify();
    if (!$ctx) return;

    if (!$ctx['isCoordinator']) {
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
    $extension = strtolower($fileType['ext'] ?? '');

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
    $storedName = MjEmployeeDocuments::generateStoredName($memberId, $extension);
    $targetPath = MjEmployeeDocuments::uploadDir() . $storedName;

    if (!move_uploaded_file($_FILES['file']['tmp_name'], $targetPath)) {
        wp_send_json_error(array('message' => __('Erreur lors de l\'enregistrement du fichier.', 'mj-member')), 500);
        return;
    }

    // Parse metadata
    $docType      = isset($_POST['docType']) ? sanitize_key($_POST['docType']) : MjEmployeeDocuments::TYPE_PAYSLIP;
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

    // Auto-generate label for non-payslip types when no label provided
    if ($docType !== MjEmployeeDocuments::TYPE_PAYSLIP && $label === '') {
        $typeName = MjEmployeeDocuments::TYPES[$docType] ?? 'Document';
        $originalNameClean = pathinfo(sanitize_file_name($_FILES['file']['name']), PATHINFO_FILENAME);
        $label = $typeName . ' – ' . $originalNameClean;
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
        'uploaded_by'   => $ctx['memberId'],
    ));

    if (!$insertId) {
        @unlink($targetPath);
        wp_send_json_error(array('message' => __('Erreur lors de l\'enregistrement en base de données.', 'mj-member')), 500);
        return;
    }

    $doc = MjEmployeeDocuments::get_by_id($insertId);
    wp_send_json_success(array(
        'message'  => __('Document téléversé avec succès.', 'mj-member'),
        'document' => mj_empdocs_format($doc),
    ));
}

/* ------------------------------------------------------------------ *
 * Delete (own documents or coordinator)                                *
 * ------------------------------------------------------------------ */

function mj_empdocs_delete(): void
{
    $ctx = mj_empdocs_verify();
    if (!$ctx) return;

    $docId = isset($_POST['docId']) ? absint($_POST['docId']) : 0;
    if ($docId <= 0) {
        wp_send_json_error(array('message' => __('Identifiant document manquant.', 'mj-member')), 400);
        return;
    }

    // Staff may only delete their own documents; coordinators can delete any.
    if (!$ctx['isCoordinator']) {
        $doc = MjEmployeeDocuments::get_by_id($docId);
        if (!$doc || (int) $doc->member_id !== $ctx['memberId']) {
            wp_send_json_error(array('message' => __('Permissions insuffisantes.', 'mj-member')), 403);
            return;
        }
    }

    $deleted = MjEmployeeDocuments::delete($docId);
    if (!$deleted) {
        wp_send_json_error(array('message' => __('Impossible de supprimer le document.', 'mj-member')), 500);
        return;
    }

    wp_send_json_success(array('message' => __('Document supprimé.', 'mj-member')));
}

/* ------------------------------------------------------------------ *
 * Download (staff can download own, coordinator any)                  *
 * ------------------------------------------------------------------ */

function mj_empdocs_download(): void
{
    // Clean output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }

    // Verify nonce from GET or POST
    $nonceValid = false;
    if (isset($_POST['nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'mj_employee_documents')) {
        $nonceValid = true;
    } elseif (isset($_GET['nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['nonce'])), 'mj_employee_documents')) {
        $nonceValid = true;
    }
    if (!$nonceValid) {
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Erreur: Nonce invalide ou expiré. Rechargez la page.';
        exit;
    }

    $userId = get_current_user_id();
    if (!$userId) {
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Erreur: Vous devez être connecté.';
        exit;
    }

    $memberObj = MjMembers::getByWpUserId($userId);
    if (!$memberObj) {
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Erreur: Membre non trouvé.';
        exit;
    }

    $member = is_array($memberObj) ? $memberObj : (method_exists($memberObj, 'toArray') ? $memberObj->toArray() : (array) $memberObj);
    $role = $member['role'] ?? '';
    $memberId = (int) ($member['id'] ?? 0);
    $isCoordinator = MjRoles::isCoordinateur($role) || current_user_can('manage_options');

    if (!MjRoles::isStaff($role) && !current_user_can('manage_options')) {
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

    // Staff can only download their own documents
    if (!$isCoordinator && (int) $doc->member_id !== $memberId) {
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Erreur: Accès refusé.';
        exit;
    }

    $filePath = MjEmployeeDocuments::filePath($doc->stored_name);
    if (!file_exists($filePath)) {
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Erreur: Fichier non trouvé sur le serveur.';
        exit;
    }

    // Verify MIME
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $detectedMime = finfo_file($finfo, $filePath);
    finfo_close($finfo);

    if (!in_array($detectedMime, MjEmployeeDocuments::ALLOWED_MIME_TYPES, true)) {
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Erreur: Type de fichier non autorisé.';
        exit;
    }

    $displayName = !empty($doc->original_name) ? $doc->original_name : ('document_' . $docId . '.' . pathinfo($filePath, PATHINFO_EXTENSION));
    header('Content-Type: ' . $detectedMime);
    header('Content-Disposition: inline; filename="' . sanitize_file_name($displayName) . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: private, max-age=3600');
    header('X-Content-Type-Options: nosniff');

    readfile($filePath);
    exit;
}
