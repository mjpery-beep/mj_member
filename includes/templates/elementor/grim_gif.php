<?php

use Mj\Member\Core\AssetsManager;

if (!defined('ABSPATH')) {
    exit;
}

AssetsManager::requirePackage('grim-gif');

$title = isset($template_data['title']) ? (string) $template_data['title'] : '';
$subtitle = isset($template_data['subtitle']) ? (string) $template_data['subtitle'] : '';
$gif_url = isset($template_data['gif_url']) ? (string) $template_data['gif_url'] : '';
$gif_name = isset($template_data['gif_name']) ? (string) $template_data['gif_name'] : '';
$show_filename = !empty($template_data['show_filename']);
$switch_enabled = !empty($template_data['switch_enabled']);
$switch_interval = isset($template_data['switch_interval']) ? max(1, (int) $template_data['switch_interval']) : 8;
$gif_pool = isset($template_data['gif_pool']) && is_array($template_data['gif_pool']) ? array_values($template_data['gif_pool']) : array();
$gif_pool_json = wp_json_encode($gif_pool);
if (!is_string($gif_pool_json)) {
    $gif_pool_json = '[]';
}
$message_pool = isset($template_data['message_pool']) && is_array($template_data['message_pool']) ? array_values($template_data['message_pool']) : array();
$message_pool_json = wp_json_encode($message_pool);
if (!is_string($message_pool_json)) {
    $message_pool_json = '[]';
}
$is_preview = !empty($template_data['is_preview']);
$random_message = isset($template_data['message']) ? (string) $template_data['message'] : '';

if ($gif_url === '' && $is_preview) {
    $gif_url = 'data:image/gif;base64,R0lGODlhAQABAIABAP///wAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==';
    $gif_name = __('Aperçu Grimlins', 'mj-member');
}

$alt_text = $gif_name !== ''
    ? sprintf(__('Animation Grimlins : %s', 'mj-member'), $gif_name)
    : __('Animation Grimlins', 'mj-member');
$alt_label = __('Animation Grimlins', 'mj-member');
?>

<div
    class="mj-grim-gif"
    data-mj-grim-gif
    data-switch-enabled="<?php echo $switch_enabled ? '1' : '0'; ?>"
    data-switch-interval="<?php echo esc_attr((string) $switch_interval); ?>"
    data-show-filename="<?php echo $show_filename ? '1' : '0'; ?>"
    data-alt-label="<?php echo esc_attr($alt_label); ?>"
    data-gif-pool="<?php echo esc_attr($gif_pool_json); ?>"
    data-message-pool="<?php echo esc_attr($message_pool_json); ?>"
>
    <?php if ($title !== '') : ?>
        <h2 class="mj-grim-gif__title"><?php echo esc_html($title); ?></h2>
    <?php endif; ?>

    <?php if ($subtitle !== '') : ?>
        <p class="mj-grim-gif__subtitle"><?php echo esc_html($subtitle); ?></p>
    <?php endif; ?>

    <?php if ($gif_url !== '') : ?>
        <figure class="mj-grim-gif__figure">
            <img class="mj-grim-gif__image" data-mj-grim-gif-image src="<?php echo esc_url($gif_url); ?>" alt="<?php echo esc_attr($alt_text); ?>" loading="lazy">
            <?php if ($show_filename && $gif_name !== '') : ?>
                <figcaption class="mj-grim-gif__caption" data-mj-grim-gif-caption>#<?php echo esc_html($gif_name); ?></figcaption>
            <?php endif; ?>
        </figure>
    <?php else : ?>
        <p class="mj-grim-gif__fallback" role="status">
            <?php esc_html_e('Impossible de charger un GIF Grimlins pour le moment.', 'mj-member'); ?>
        </p>
    <?php endif; ?>

    <?php if ($random_message !== '') : ?>
        <p class="mj-grim-gif__message" data-mj-grim-gif-message>“<?php echo esc_html($random_message); ?>”</p>
    <?php endif; ?>

    <?php if ($is_preview && $gif_url !== '' && !$show_filename) : ?>
        <p class="mj-grim-gif__hint">
            <?php esc_html_e('Astuce : activez le nom de fichier pour afficher un hashtag unique sous le GIF.', 'mj-member'); ?>
        </p>
    <?php endif; ?>
</div>
