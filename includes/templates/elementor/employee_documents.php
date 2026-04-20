<?php
/**
 * Template for the Employee Documents widget.
 *
 * Coordinator view: see all staff documents + bulk payslip upload form.
 * Staff view (animateur): see only own documents.
 *
 * @package MJ_Member
 */

use Mj\Member\Classes\Crud\MjEmployeeDocuments;
use Mj\Member\Classes\Crud\MjMembers;
use Mj\Member\Classes\MjRoles;
use Mj\Member\Core\AssetsManager;

if (!defined('ABSPATH')) {
    exit;
}

AssetsManager::requirePackage('employee-documents');

$title = isset($title) ? (string) $title : '';

$isPreview   = function_exists('is_elementor_preview') && is_elementor_preview();
$currentUserId = get_current_user_id();
$currentMember = null;
$hasAccess     = false;
$memberId      = 0;

if ($isPreview) {
    $hasAccess     = true;
    $memberId      = 1;
} elseif ($currentUserId > 0) {
    $currentMember = MjMembers::getByWpUserId($currentUserId);
    if ($currentMember) {
        $memberId      = (int) $currentMember->id;
        $hasAccess     = MjRoles::isStaff($currentMember->role);
    }

    // Admin WP : toujours accès
    if (!$hasAccess && current_user_can('manage_options')) {
        $hasAccess     = true;
    }
}

/* ------------------------------------------------------------------ *
 * Current member documents (own docs)                                 *
 * ------------------------------------------------------------------ */
$myDocuments = array();

if ($hasAccess && !$isPreview && $memberId > 0) {
    $raw = MjEmployeeDocuments::get_all(array('member_id' => $memberId));
    foreach ($raw as $doc) {
        $myDocuments[] = array(
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
}

/* ------------------------------------------------------------------ *
 * Preview sample data                                                 *
 * ------------------------------------------------------------------ */
if ($isPreview) {
    $myDocuments = array(
        array(
            'id' => 1, 'memberId' => 1, 'docType' => 'payslip',
            'label' => 'Fiche de paie – Juin 2025', 'originalName' => 'fiche_juin_2025.pdf',
            'mimeType' => 'application/pdf', 'fileSize' => 145000,
            'documentDate' => '2025-06-30', 'payslipMonth' => 6, 'payslipYear' => 2025,
            'createdAt' => wp_date('Y-m-d H:i:s'),
        ),
        array(
            'id' => 2, 'memberId' => 1, 'docType' => 'payslip',
            'label' => 'Fiche de paie – Mai 2025', 'originalName' => 'fiche_mai_2025.pdf',
            'mimeType' => 'application/pdf', 'fileSize' => 132000,
            'documentDate' => '2025-05-31', 'payslipMonth' => 5, 'payslipYear' => 2025,
            'createdAt' => wp_date('Y-m-d H:i:s'),
        ),
        array(
            'id' => 3, 'memberId' => 1, 'docType' => 'contract',
            'label' => 'Contrat de travail', 'originalName' => 'contrat_2024.pdf',
            'mimeType' => 'application/pdf', 'fileSize' => 280000,
            'documentDate' => '2024-09-01', 'payslipMonth' => null, 'payslipYear' => null,
            'createdAt' => wp_date('Y-m-d H:i:s'),
        ),
        array(
            'id' => 4, 'memberId' => 1, 'docType' => 'misc',
            'label' => 'Attestation employeur', 'originalName' => 'attestation.pdf',
            'mimeType' => 'application/pdf', 'fileSize' => 96000,
            'documentDate' => '2025-03-15', 'payslipMonth' => null, 'payslipYear' => null,
            'createdAt' => wp_date('Y-m-d H:i:s'),
        ),
    );
}

/* ------------------------------------------------------------------ *
 * Build config JSON                                                   *
 * ------------------------------------------------------------------ */
$config = array(
    'title'         => $title,
    'hasAccess'     => $hasAccess,
    'memberId'      => $memberId,
    'preview'       => $isPreview,
    'nonce'         => wp_create_nonce('mj_employee_documents'),
    'ajaxUrl'       => admin_url('admin-ajax.php'),
    'documents'     => $myDocuments,
    'i18n'          => array(
        'title'            => __('Mes documents', 'mj-member'),
        'tabPayslip'       => __('Fiches de paie', 'mj-member'),
        'tabContract'      => __('Emploi', 'mj-member'),
        'tabMisc'          => __('Divers', 'mj-member'),
        'noDocuments'      => __('Aucun document dans cette catégorie.', 'mj-member'),
        'preview'          => __('Aperçu', 'mj-member'),
        'download'         => __('Télécharger', 'mj-member'),
        'deleteBtn'        => __('Supprimer', 'mj-member'),
        'deleteTitle'      => __('Supprimer le document', 'mj-member'),
        'confirmDelete'    => __('Êtes-vous sûr de vouloir supprimer ce document ?', 'mj-member'),
        'cancel'           => __('Annuler', 'mj-member'),
        'close'            => __('Fermer', 'mj-member'),
        'previewUnavailable' => __('Aperçu non disponible pour ce type de fichier.', 'mj-member'),
        'deleted'          => __('Document supprimé.', 'mj-member'),
        'noAccess'         => __('Vous n\'avez pas accès à cette section.', 'mj-member'),
        'error'            => __('Une erreur est survenue.', 'mj-member'),
        'months'           => array(
            __('Janvier', 'mj-member'),   __('Février', 'mj-member'),
            __('Mars', 'mj-member'),      __('Avril', 'mj-member'),
            __('Mai', 'mj-member'),       __('Juin', 'mj-member'),
            __('Juillet', 'mj-member'),   __('Août', 'mj-member'),
            __('Septembre', 'mj-member'), __('Octobre', 'mj-member'),
            __('Novembre', 'mj-member'),  __('Décembre', 'mj-member'),
        ),
        'size'             => __('Taille', 'mj-member'),
        'date'             => __('Date', 'mj-member'),
        'period'           => __('Période', 'mj-member'),
    ),
);

$configJson = wp_json_encode($config);
if (!is_string($configJson)) {
    $configJson = '{}';
}
?>
<?php $fallbackNotice = (!$isPreview && !$hasAccess); ?>
<div class="mj-empdocs-widget" data-mj-employee-documents-widget data-config="<?php echo esc_attr($configJson); ?>">
    <?php if ($fallbackNotice) : ?>
        <p class="mj-empdocs-widget__notice mj-empdocs-widget__notice--fallback">
            <?php esc_html_e("Vous n'avez pas accès à cette section.", 'mj-member'); ?>
        </p>
    <?php endif; ?>
    <noscript>
        <p class="mj-empdocs-widget__notice">
            <?php esc_html_e('Ce module nécessite JavaScript pour fonctionner.', 'mj-member'); ?>
        </p>
    </noscript>
</div>
