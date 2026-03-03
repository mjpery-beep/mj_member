<?php

use Mj\Member\Classes\Crud\MjExpenses;
use Mj\Member\Classes\Crud\MjMembers;
use Mj\Member\Classes\MjRoles;
use Mj\Member\Core\AssetsManager;
use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

AssetsManager::requirePackage('expenses');

$title = isset($title) ? (string) $title : '';
$intro = isset($intro) ? (string) $intro : '';

$isPreview = function_exists('is_elementor_preview') && is_elementor_preview();
$currentUserId = get_current_user_id();
$currentMember = null;
$isCoordinator = false;
$hasAccess = false;

if ($isPreview) {
    $hasAccess = true;
    $isCoordinator = true;
} elseif ($currentUserId > 0) {
    if (class_exists('Mj\\Member\\Classes\\Crud\\MjMembers')) {
        $currentMember = MjMembers::getByWpUserId($currentUserId);
        if ($currentMember) {
            $role = $currentMember->role ?? '';
            $hasAccess = MjRoles::isAnimateurOrCoordinateur($role) || MjRoles::isBenevole($role);
            $isCoordinator = MjRoles::isCoordinateur($role);
        }
    }
}

if (!$hasAccess && !$isPreview) {
    echo '<div class="mj-expenses-widget"><p>' . esc_html__('Vous n\'avez pas accès à cette section.', 'mj-member') . '</p></div>';
    return;
}

?>
<div class="mj-expenses-widget" id="mj-expenses-app">
    <?php if ($title !== '') : ?>
        <h2 class="mj-expenses-widget__title"><?php echo esc_html($title); ?></h2>
    <?php endif; ?>
    <?php if ($intro !== '') : ?>
        <p class="mj-expenses-widget__intro"><?php echo wp_kses_post($intro); ?></p>
    <?php endif; ?>

    <div class="mj-expenses-widget__loading">
        <p><?php echo esc_html__('Chargement…', 'mj-member'); ?></p>
    </div>
</div>
