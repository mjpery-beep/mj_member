<?php
/**
 * Template for the Payslip Upload widget.
 *
 * Coordinator-only: bulk payslip upload with per-employee drag-and-drop zones.
 *
 * @package MJ_Member
 */

use Mj\Member\Classes\Crud\MjMembers;
use Mj\Member\Classes\MjRoles;
use Mj\Member\Core\AssetsManager;
use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

AssetsManager::requirePackage('payslip-upload');

$title = isset($title) ? (string) $title : '';

$isPreview     = function_exists('is_elementor_preview') && is_elementor_preview();
$currentUserId = get_current_user_id();
$isCoordinator = false;
$hasAccess     = false;

if ($isPreview) {
    $hasAccess     = true;
    $isCoordinator = true;
} elseif ($currentUserId > 0) {
    $currentMember = MjMembers::getByWpUserId($currentUserId);
    if ($currentMember) {
        $hasAccess     = MjRoles::isCoordinateur($currentMember->role);
        $isCoordinator = $hasAccess;
    }

    // Admin WP : toujours accès coordinateur
    if (!$hasAccess && current_user_can('manage_options')) {
        $hasAccess     = true;
        $isCoordinator = true;
    }
}

/* ------------------------------------------------------------------ *
 * Employees list                                                      *
 * ------------------------------------------------------------------ */
$employees = array();

if ($hasAccess && $isCoordinator && !$isPreview) {
    $staffRoles = array(MjRoles::ANIMATEUR, MjRoles::COORDINATEUR);
    $staff      = MjMembers::get_all(array(
        'filters' => array('roles' => $staffRoles),
        'limit'   => 100,
    ));
    foreach ($staff as $s) {
        $avatarUrl = '';
        if (!empty($s->photo_id)) {
            $url = wp_get_attachment_image_url((int) $s->photo_id, 'thumbnail');
            if ($url) {
                $avatarUrl = $url;
            }
        }
        $name = trim(($s->first_name ?? '') . ' ' . ($s->last_name ?? ''));
        $initials = '';
        if ($name !== '') {
            $parts    = explode(' ', $name);
            $initials = mb_strtoupper(mb_substr($parts[0], 0, 1, 'UTF-8'), 'UTF-8');
            if (count($parts) > 1) {
                $initials .= mb_strtoupper(mb_substr(end($parts), 0, 1, 'UTF-8'), 'UTF-8');
            }
        }
        $employees[] = array(
            'id'       => (int) $s->id,
            'name'     => $name,
            'avatar'   => $avatarUrl,
            'initials' => $initials,
        );
    }
    usort($employees, function ($a, $b) {
        return strcasecmp($a['name'], $b['name']);
    });
}

/* ------------------------------------------------------------------ *
 * Preview sample data                                                 *
 * ------------------------------------------------------------------ */
if ($isPreview) {
    $employees = array(
        array('id' => 1, 'name' => 'Marie Martin',   'avatar' => '', 'initials' => 'MM'),
        array('id' => 2, 'name' => 'Jean Dupont',    'avatar' => '', 'initials' => 'JD'),
        array('id' => 3, 'name' => 'Pierre Durand',  'avatar' => '', 'initials' => 'PD'),
        array('id' => 4, 'name' => 'Sophie Lambert', 'avatar' => '', 'initials' => 'SL'),
    );
}

/* ------------------------------------------------------------------ *
 * Build config JSON                                                   *
 * ------------------------------------------------------------------ */
use Mj\Member\Classes\Crud\MjEmployeeDocuments;

$config = array(
    'title'         => $title,
    'hasAccess'     => $hasAccess,
    'isCoordinator' => $isCoordinator,
    'preview'       => $isPreview,
    'nonce'         => wp_create_nonce('mj_employee_documents'),
    'ajaxUrl'       => admin_url('admin-ajax.php'),
    'employees'     => $employees,
    'docTypes'      => array(
        array('value' => MjEmployeeDocuments::TYPE_PAYSLIP,  'label' => __('Fiche de paie', 'mj-member')),
        array('value' => MjEmployeeDocuments::TYPE_CONTRACT, 'label' => __('Emploi', 'mj-member')),
        array('value' => MjEmployeeDocuments::TYPE_MISC,     'label' => __('Divers', 'mj-member')),
    ),
    'i18n'          => array(
        'uploadTitle'    => __('Ajouter des documents', 'mj-member'),
        'docTypeLabel'   => __('Type de document', 'mj-member'),
        'year'           => __('Année', 'mj-member'),
        'month'          => __('Mois', 'mj-member'),
        'dropHint'       => __('Glissez un fichier PDF ici', 'mj-member'),
        'dropHintActive' => __('Déposez le fichier…', 'mj-member'),
        'uploading'      => __('Envoi en cours…', 'mj-member'),
        'uploaded'        => __('Envoyé !', 'mj-member'),
        'uploadError'    => __('Erreur lors de l\'envoi.', 'mj-member'),
        'fileTooBig'     => __('Fichier trop volumineux (max 10 Mo).', 'mj-member'),
        'invalidFormat'  => __('Format non supporté. Utilisez PDF, JPG ou PNG.', 'mj-member'),
        'noAccess'       => __('Vous n\'avez pas accès à cette section.', 'mj-member'),
        'months'         => array(
            __('Janvier', 'mj-member'),   __('Février', 'mj-member'),
            __('Mars', 'mj-member'),      __('Avril', 'mj-member'),
            __('Mai', 'mj-member'),       __('Juin', 'mj-member'),
            __('Juillet', 'mj-member'),   __('Août', 'mj-member'),
            __('Septembre', 'mj-member'), __('Octobre', 'mj-member'),
            __('Novembre', 'mj-member'),  __('Décembre', 'mj-member'),
        ),
    ),
);

$configJson = wp_json_encode($config);
if (!is_string($configJson)) {
    $configJson = '{}';
}
?>
<?php $fallbackNotice = (!$isPreview && !$hasAccess); ?>
<div class="mj-payslip-upload-widget" data-mj-payslip-upload-widget data-config="<?php echo esc_attr($configJson); ?>">
    <?php if ($fallbackNotice) : ?>
        <p class="mj-payslip-upload-widget__notice mj-payslip-upload-widget__notice--fallback">
            <?php esc_html_e("Vous n'avez pas accès à cette section.", 'mj-member'); ?>
        </p>
    <?php endif; ?>
    <noscript>
        <p class="mj-payslip-upload-widget__notice">
            <?php esc_html_e('Ce module nécessite JavaScript pour fonctionner.', 'mj-member'); ?>
        </p>
    </noscript>
</div>
