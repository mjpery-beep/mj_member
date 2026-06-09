<?php

use Mj\Member\Core\AssetsManager;

if (!defined('ABSPATH')) {
    exit;
}

AssetsManager::requirePackage('tactile-keyboard');

$template_data = isset($template_data) && is_array($template_data) ? $template_data : array();
$wrapper_classes = isset($template_data['wrapper_classes']) ? (string) $template_data['wrapper_classes'] : 'mj-tactile-keyboard';
$config_json = isset($template_data['config_json']) ? (string) $template_data['config_json'] : '{}';
$show_demo_input = !empty($template_data['show_demo_input']);
$input_label = isset($template_data['input_label']) ? (string) $template_data['input_label'] : __('Zone de saisie', 'mj-member');
$input_placeholder = isset($template_data['input_placeholder']) ? (string) $template_data['input_placeholder'] : __('Touchez les touches pour ecrire ici.', 'mj-member');
$is_preview = !empty($template_data['is_preview']);
?>
<div class="<?php echo esc_attr($wrapper_classes); ?>" data-config="<?php echo esc_attr($config_json); ?>" data-preview="<?php echo $is_preview ? 'yes' : 'no'; ?>" aria-hidden="<?php echo $is_preview ? 'false' : 'true'; ?>">
    <div class="mj-tactile-keyboard__shell">
        <?php if ($show_demo_input) : ?>
            <label class="mj-tactile-keyboard__display-label" for="<?php echo esc_attr($widget->get_id()); ?>-display">
                <?php echo esc_html($input_label); ?>
            </label>
            <div class="mj-tactile-keyboard__display-wrap">
                <input
                    id="<?php echo esc_attr($widget->get_id()); ?>-display"
                    class="mj-tactile-keyboard__display"
                    data-role="display"
                    type="text"
                    autocomplete="off"
                    spellcheck="false"
                    placeholder="<?php echo esc_attr($input_placeholder); ?>"
                />
            </div>
        <?php endif; ?>

        <div class="mj-tactile-keyboard__viewport">
            <div class="mj-tactile-keyboard__main">
                <div class="mj-tactile-keyboard__rows" data-role="keyboard"></div>
                <div class="mj-tactile-keyboard__emoji" data-role="emoji"></div>
            </div>
            <aside class="mj-tactile-keyboard__numpad" data-role="numpad"></aside>
        </div>

        <?php if ($is_preview) : ?>
            <p class="mj-tactile-keyboard__preview-note">
                <?php esc_html_e('Apercu Elementor: le clavier fonctionne directement dans la zone de saisie integree.', 'mj-member'); ?>
            </p>
        <?php endif; ?>
    </div>
</div>