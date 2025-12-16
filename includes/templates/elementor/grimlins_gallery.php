<?php

use Mj\Member\Core\AssetsManager;

if (!defined('ABSPATH')) {
    exit;
}

AssetsManager::requirePackage('grimlins-gallery');

$title = isset($template_data['title']) ? (string) $template_data['title'] : __('Galerie Grimlins', 'mj-member');
$description = isset($template_data['description']) ? (string) $template_data['description'] : '';
$empty_message = isset($template_data['empty_message']) ? (string) $template_data['empty_message'] : __('Aucune transformation Grimlins pour le moment.', 'mj-member');
$limit = isset($template_data['limit']) ? max(0, (int) $template_data['limit']) : 0;
$order = isset($template_data['order']) && strtolower((string) $template_data['order']) === 'asc' ? 'asc' : 'desc';
$is_preview = !empty($template_data['is_preview']);
$allow_delete = !$is_preview && is_user_logged_in() && current_user_can('manage_options');

$sessions = array();

if ($is_preview) {
    if (function_exists('mj_member_grimlins_gallery_sample_data')) {
        $sessions = mj_member_grimlins_gallery_sample_data();
    }
} elseif (function_exists('mj_member_grimlins_gallery_list_sessions')) {
    $sessions = mj_member_grimlins_gallery_list_sessions(array(
        'limit' => $limit,
        'order' => $order,
    ));
}

if (!is_array($sessions)) {
    $sessions = array();
}

$sessions = array_values(array_filter($sessions, static function ($item) {
    $has_original = isset($item['original_url']) && is_string($item['original_url']) && $item['original_url'] !== '';
    $has_result = isset($item['result_url']) && is_string($item['result_url']) && $item['result_url'] !== '';

    return $has_original || $has_result;
}));

$component_id = 'mj-grimlins-gallery-' . wp_generate_uuid4();
$has_sessions = !empty($sessions);
?>

<section class="mj-grimlins-gallery" id="<?php echo esc_attr($component_id); ?>" data-grimlins-gallery>
    <header class="mj-grimlins-gallery__header">
        <?php if ($title !== '') : ?>
            <h2 class="mj-grimlins-gallery__title"><?php echo esc_html($title); ?></h2>
        <?php endif; ?>
        <?php if ($description !== '') : ?>
            <p class="mj-grimlins-gallery__description"><?php echo esc_html($description); ?></p>
        <?php endif; ?>
    </header>

    <?php if (!$has_sessions) : ?>
        <div class="mj-grimlins-gallery__empty" role="status">
            <?php echo esc_html($empty_message); ?>
        </div>
    <?php else : ?>
        <div class="mj-grimlins-gallery__track" role="list">
            <?php foreach ($sessions as $index => $session) :
                $session_value = isset($session['session']) ? (string) $session['session'] : ('session-' . $index);
                $session_label = isset($session['created_label']) ? (string) $session['created_label'] : '';
                $before_url = isset($session['original_url']) ? esc_url($session['original_url']) : '';
                $after_url = isset($session['result_url']) ? esc_url($session['result_url']) : '';
                $before_alt = $session_label !== ''
                    ? sprintf(__('Photo originale Grimlins (%s)', 'mj-member'), $session_label)
                    : __('Photo originale Grimlins', 'mj-member');
                $after_alt = $session_label !== ''
                    ? sprintf(__('Avatar Grimlins généré (%s)', 'mj-member'), $session_label)
                    : __('Avatar Grimlins généré', 'mj-member');
                $time_value = isset($session['created_at']) ? (int) $session['created_at'] : 0;
                $display_label = $session_label !== '' ? $session_label : ($time_value > 0 ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $time_value) : '');
                $session_attr = $session_value !== '' ? $session_value : ('session-' . $index);
                ?>
                <article class="mj-grimlins-gallery__item" role="listitem" data-session-name="<?php echo esc_attr($session_attr); ?>" data-session="<?php echo esc_attr($session_value); ?>">
                    <div class="mj-grimlins-gallery__pair">
                        <?php if ($before_url !== '') : ?>
                            <figure class="mj-grimlins-gallery__image">
                                <img src="<?php echo $before_url; ?>" alt="<?php echo esc_attr($before_alt); ?>" width="200" height="200" loading="lazy">
                                <span class="mj-grimlins-gallery__badge"><?php esc_html_e('Avant', 'mj-member'); ?></span>
                            </figure>
                        <?php endif; ?>
                        <?php if ($after_url !== '') : ?>
                            <figure class="mj-grimlins-gallery__image">
                                <img src="<?php echo $after_url; ?>" alt="<?php echo esc_attr($after_alt); ?>" width="200" height="200" loading="lazy">
                                <span class="mj-grimlins-gallery__badge mj-grimlins-gallery__badge--after"><?php esc_html_e('Après', 'mj-member'); ?></span>
                            </figure>
                        <?php endif; ?>
                    </div>
                    <?php if ($display_label !== '' || $time_value > 0 || $allow_delete) : ?>
                        <div class="mj-grimlins-gallery__meta">
                            <strong><?php esc_html_e('Transformation Grimlins', 'mj-member'); ?></strong>
                            <div class="mj-grimlins-gallery__meta-actions">
                                <?php if ($display_label !== '') : ?>
                                    <?php if ($time_value > 0) : ?>
                                        <time datetime="<?php echo esc_attr(gmdate(DATE_ATOM, $time_value)); ?>"><?php echo esc_html($display_label); ?></time>
                                    <?php else : ?>
                                        <span><?php echo esc_html($display_label); ?></span>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php if ($allow_delete && $session_value !== '') : ?>
                                    <button type="button" class="mj-grimlins-gallery__delete" data-grimlins-gallery-delete data-session="<?php echo esc_attr($session_value); ?>">
                                        <?php esc_html_e('Supprimer', 'mj-member'); ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
