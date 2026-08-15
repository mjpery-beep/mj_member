<?php

use Mj\Member\Core\AssetsManager;

if (!defined('ABSPATH')) {
	exit;
}

AssetsManager::requirePackage('request-management');

$widgetTitle = isset($title) && $title !== '' ? (string) $title : __('Nouvelle Demande', 'mj-member');

$isPreview = function_exists('is_elementor_preview') && is_elementor_preview();
?>
<section class="mj-request-management" data-preview="<?php echo $isPreview ? '1' : '0'; ?>">
	<div class="mj-request-management__app" data-mj-request-management-app></div>
</section>
