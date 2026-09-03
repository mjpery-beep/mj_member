<?php

use Mj\Member\Classes\Crud\MjMembers;
use Mj\Member\Core\AssetsManager;

if (!defined('ABSPATH')) {
    exit;
}

AssetsManager::requirePackage('day-notes');

$title = isset($title) ? (string) $title : '';
$intro = isset($intro) ? (string) $intro : '';

$isPreview = function_exists('is_elementor_preview') && is_elementor_preview();
$currentUserId = get_current_user_id();
$hasAccess = $isPreview;

if (!$isPreview && $currentUserId > 0 && class_exists(MjMembers::class)) {
    $currentMember = MjMembers::getByWpUserId($currentUserId);
    $hasAccess = (bool) $currentMember;
}

if (!$hasAccess) {
    echo '<div class="mj-day-notes-widget"><p>' . esc_html__('Vous n\'avez pas accès à cette section.', 'mj-member') . '</p></div>';
    return;
}

?>
<div class="mj-day-notes-widget" id="mj-day-notes-app">
    <?php if ($title !== '') : ?>
        <h2 class="mj-day-notes-widget__title"><?php echo esc_html($title); ?></h2>
    <?php endif; ?>
    <?php if ($intro !== '') : ?>
        <p class="mj-day-notes-widget__intro"><?php echo wp_kses_post($intro); ?></p>
    <?php endif; ?>

    <div class="mj-day-notes-widget__loading">
        <p><?php echo esc_html__('Chargement…', 'mj-member'); ?></p>
    </div>
</div>
