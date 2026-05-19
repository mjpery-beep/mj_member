<?php

use Mj\Member\Core\AssetsManager;

if (!defined('ABSPATH')) {
    exit;
}

AssetsManager::requirePackage('player-widget');

static $playerScriptLocalized = false;
if (!$playerScriptLocalized) {
    wp_localize_script(
        'mj-member-player-widget',
        'mjMemberPlayerWidget',
        array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mj-player-widget'),
            'canPersist' => is_user_logged_in(),
        )
    );
    $playerScriptLocalized = true;
}

$template_data = isset($template_data) && is_array($template_data) ? $template_data : array();
$title = isset($template_data['title']) ? (string) $template_data['title'] : '';
$intro = isset($template_data['intro']) ? (string) $template_data['intro'] : '';
$rootStyle = isset($template_data['root_style']) ? trim((string) $template_data['root_style']) : '';
$config = isset($template_data['config']) && is_array($template_data['config']) ? $template_data['config'] : array();

$configJson = wp_json_encode($config);
if (!is_string($configJson)) {
    $configJson = '{}';
}
?>
<?php if (function_exists('is_elementor_preview') && is_elementor_preview()) : ?>
    <div class="mj-player-widget--preview">
        <p><?php esc_html_e('Aperçu du widget YouTube Player. Le rendu complet est visible sur le frontend.', 'mj-member'); ?></p>
    </div>
<?php else : ?>
    <!-- Rendu complet du widget -->
    <div class="mj-player-widget" data-mj-player-widget data-config="<?php echo esc_attr($configJson); ?>"<?php echo $rootStyle !== '' ? ' style="' . esc_attr($rootStyle) . '"' : ''; ?>>
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
<?php endif; ?>
