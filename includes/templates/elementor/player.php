<?php

use Mj\Member\Core\AssetsManager;

if (!defined('ABSPATH')) {
    exit;
}

AssetsManager::requirePackage('player-widget');

$template_data = isset($template_data) && is_array($template_data) ? $template_data : array();
$title = isset($template_data['title']) ? (string) $template_data['title'] : '';
$intro = isset($template_data['intro']) ? (string) $template_data['intro'] : '';
$config = isset($template_data['config']) && is_array($template_data['config']) ? $template_data['config'] : array();

$configJson = wp_json_encode($config);
if (!is_string($configJson)) {
    $configJson = '{}';
}
?>
<div class="mj-player-widget" data-mj-player-widget data-config="<?php echo esc_attr($configJson); ?>">
    <?php if ($title !== '') : ?>
        <h3 class="mj-player-widget__title"><?php echo esc_html($title); ?></h3>
    <?php endif; ?>

    <?php if ($intro !== '') : ?>
        <p class="mj-player-widget__intro"><?php echo wp_kses_post($intro); ?></p>
    <?php endif; ?>

    <div class="mj-player-widget__app"></div>

    <noscript>
        <p class="mj-player-widget__notice"><?php esc_html_e('Ce player nécessite JavaScript pour fonctionner.', 'mj-member'); ?></p>
    </noscript>
</div>
