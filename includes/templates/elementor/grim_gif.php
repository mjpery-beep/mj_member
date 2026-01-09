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
$is_preview = !empty($template_data['is_preview']);
$random_message = isset($template_data['message']) ? (string) $template_data['message'] : '';

if ($gif_url === '' && $is_preview) {
    $gif_url = 'data:image/gif;base64,R0lGODlhAQABAIABAP///wAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==';
    $gif_name = __('Aperçu Grimlins', 'mj-member');
}

$alt_text = $gif_name !== ''
    ? sprintf(__('Animation Grimlins : %s', 'mj-member'), $gif_name)
    : __('Animation Grimlins', 'mj-member');
?>

<div class="mj-grim-gif" data-mj-grim-gif>
    <?php if ($title !== '') : ?>
        <h2 class="mj-grim-gif__title"><?php echo esc_html($title); ?></h2>
    <?php endif; ?>

    <?php if ($subtitle !== '') : ?>
        <p class="mj-grim-gif__subtitle"><?php echo esc_html($subtitle); ?></p>
    <?php endif; ?>

    <?php if ($gif_url !== '') : ?>
        <figure class="mj-grim-gif__figure">
            <img class="mj-grim-gif__image" src="<?php echo esc_url($gif_url); ?>" alt="<?php echo esc_attr($alt_text); ?>" loading="lazy">
            <?php if ($show_filename && $gif_name !== '') : ?>
                <figcaption class="mj-grim-gif__caption">#<?php echo esc_html($gif_name); ?></figcaption>
            <?php endif; ?>
        </figure>
    <?php else : ?>
        <p class="mj-grim-gif__fallback" role="status">
            <?php esc_html_e('Impossible de charger un GIF Grimlins pour le moment.', 'mj-member'); ?>
        </p>
    <?php endif; ?>

    <?php if ($random_message !== '') : ?>
        <p class="mj-grim-gif__message">“<?php echo esc_html($random_message); ?>”</p>
    <?php endif; ?>

    <?php if ($is_preview && $gif_url !== '' && !$show_filename) : ?>
        <p class="mj-grim-gif__hint">
            <?php esc_html_e('Astuce : activez le nom de fichier pour afficher un hashtag unique sous le GIF.', 'mj-member'); ?>
        </p>
    <?php endif; ?>
</div>
