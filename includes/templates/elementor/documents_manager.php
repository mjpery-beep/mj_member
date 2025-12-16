<?php

use Mj\Member\Core\AssetsManager;
use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

AssetsManager::requirePackage('documents-manager');

$title = isset($title) ? (string) $title : '';
$intro = isset($intro) ? (string) $intro : '';
$defaultFolderId = isset($defaultFolderId) ? (string) $defaultFolderId : '';
$allowUpload = isset($allowUpload) ? (bool) $allowUpload : true;
$allowCreateFolder = isset($allowCreateFolder) ? (bool) $allowCreateFolder : true;
$allowRename = isset($allowRename) ? (bool) $allowRename : true;

$isPreview = function_exists('is_elementor_preview') && is_elementor_preview();
$hasAccess = $isPreview;
if (!$hasAccess && function_exists('mj_member_documents_user_has_access')) {
    $hasAccess = mj_member_documents_user_has_access();
}

$isConfigured = function_exists('mj_member_documents_is_configured') ? mj_member_documents_is_configured() : false;

$introHtml = $intro !== '' ? wp_kses_post($intro) : '';

$sampleFolder = array(
    'folder' => array(
        'id' => 'preview-root',
        'name' => __('Documents MJ', 'mj-member'),
        'type' => 'folder',
        'modifiedTime' => current_time('mysql'),
    ),
    'breadcrumbs' => array(
        array(
            'id' => 'preview-root',
            'name' => __('Documents MJ', 'mj-member'),
        ),
    ),
    'items' => array(
        array(
            'id' => 'preview-folder-1',
            'name' => __('Comptes rendus', 'mj-member'),
            'type' => 'folder',
            'mimeType' => 'application/vnd.google-apps.folder',
            'modifiedTime' => current_time('mysql'),
            'size' => 0,
            'webViewLink' => '',
            'iconLink' => '',
            'parents' => array('preview-root'),
        ),
        array(
            'id' => 'preview-file-1',
            'name' => __('Planning ateliers.pdf', 'mj-member'),
            'type' => 'file',
            'mimeType' => 'application/pdf',
            'modifiedTime' => current_time('mysql'),
            'size' => 1024 * 256,
            'webViewLink' => '#',
            'iconLink' => '',
            'parents' => array('preview-root'),
        ),
    ),
);

$config = array(
    'title' => $title,
    'intro' => $introHtml,
    'defaultFolderId' => $defaultFolderId,
    'allowUpload' => $allowUpload,
    'allowCreateFolder' => $allowCreateFolder,
    'allowRename' => $allowRename,
    'hasAccess' => $hasAccess,
    'isConfigured' => $isConfigured,
    'preview' => $isPreview,
    'previewData' => $isPreview ? $sampleFolder : array(),
);

$defaultRoot = Config::googleDriveRootFolderId();
if ($config['defaultFolderId'] === '' && $defaultRoot !== '') {
    $config['defaultFolderId'] = $defaultRoot;
}

$configJson = wp_json_encode($config);
if (!is_string($configJson)) {
    $configJson = '{}';
}

$fallbackNotice = (!$isPreview && (!$hasAccess || !$isConfigured));
?>
<div class="mj-documents-widget" data-mj-member-documents-widget data-config="<?php echo esc_attr($configJson); ?>">
    <?php if ($fallbackNotice) : ?>
        <p class="mj-documents-widget__notice mj-documents-widget__notice--fallback">
            <?php
            if (!$hasAccess) {
                esc_html_e('Vous n’avez pas accès à cette section.', 'mj-member');
            } elseif (!$isConfigured) {
                esc_html_e('La connexion Google Drive n’est pas encore configurée.', 'mj-member');
            }
            ?>
        </p>
    <?php endif; ?>
    <noscript>
        <p class="mj-documents-widget__notice">
            <?php esc_html_e('Ce module nécessite JavaScript pour afficher les documents.', 'mj-member'); ?>
        </p>
    </noscript>
</div>
