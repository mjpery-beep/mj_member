<?php

use Mj\Member\Core\AssetsManager;

if (!defined('ABSPATH')) {
    exit;
}

AssetsManager::requirePackage('iframe-widget');

$template_data = isset($template_data) && is_array($template_data) ? $template_data : array();
$wrapper_classes = isset($template_data['wrapper_classes']) ? (string) $template_data['wrapper_classes'] : 'mj-iframe-widget';
$attributes = isset($template_data['attributes']) && is_array($template_data['attributes']) ? $template_data['attributes'] : array();
$show_placeholder = !empty($template_data['show_placeholder']);
$has_frame_source = !empty($template_data['has_frame_source']);
$placeholder_text = isset($template_data['placeholder_text']) ? (string) $template_data['placeholder_text'] : '';
$is_preview = !empty($template_data['is_preview']);

$attribute_html = '';
foreach ($attributes as $key => $value) {
    $attr_key = preg_replace('/[^a-zA-Z0-9_:\-]/', '', (string) $key);
    if (!is_string($attr_key) || $attr_key === '') {
        continue;
    }

    if ($value === true) {
        $attribute_html .= ' ' . esc_attr($attr_key);
        continue;
    }

    if ($value === false || $value === null) {
        continue;
    }

    $attribute_html .= sprintf(' %s="%s"', esc_attr($attr_key), esc_attr((string) $value));
}
?>
<div class="<?php echo esc_attr($wrapper_classes); ?>">
    <?php if ($has_frame_source) : ?>
        <iframe<?php echo $attribute_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>></iframe>
    <?php elseif ($show_placeholder || $is_preview) : ?>
        <div class="mj-iframe-widget__placeholder" role="status" aria-live="polite">
            <?php echo esc_html($placeholder_text); ?>
        </div>
    <?php endif; ?>
</div>
