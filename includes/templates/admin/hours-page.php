<?php

use Mj\Member\Admin\Page\HoursPage;

if (!defined('ABSPATH')) {
    exit;
}

$configJson = wp_json_encode($config);
if (!is_string($configJson)) {
    $configJson = '{}';
}

$pageTitle = isset($config['i18n']['pageTitle']) ? (string) $config['i18n']['pageTitle'] : __('Tableau de bord des heures', 'mj-member');
$updatedAtLabel = isset($config['i18n']['updatedAtLabel']) ? (string) $config['i18n']['updatedAtLabel'] : __('Mis à jour le %s', 'mj-member');
$updatedAtDisplay = isset($config['data']['generated_at_display']) ? (string) $config['data']['generated_at_display'] : '';

?>
<div class="wrap mj-hours-dashboard-wrap">
    <h1 class="wp-heading-inline"><?php echo esc_html($pageTitle); ?></h1>
    <?php if ($updatedAtDisplay !== '') : ?>
        <p class="description mj-hours-dashboard__updated">
            <?php echo esc_html(sprintf($updatedAtLabel, $updatedAtDisplay)); ?>
        </p>
    <?php endif; ?>

    <div class="mj-hours-dashboard" data-mj-hours-dashboard data-config="<?php echo esc_attr($configJson); ?>"></div>
</div>

