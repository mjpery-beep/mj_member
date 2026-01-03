<?php

use Mj\Member\Core\AssetsManager;

if (!defined('ABSPATH')) {
    exit;
}

AssetsManager::requirePackage('photo-grimlins');

$title = isset($template_data['title']) ? (string) $template_data['title'] : __('Avatar Grimlins', 'mj-member');
$description = isset($template_data['description']) ? (string) $template_data['description'] : __('Transforme ta photo en version Grimlins stylisée en un clic.', 'mj-member');
$button_label = isset($template_data['button_label']) ? (string) $template_data['button_label'] : __('Générer mon Grimlins', 'mj-member');
$is_preview = !empty($template_data['is_preview']);
$placeholder_preview = isset($template_data['preview_image']) ? esc_url($template_data['preview_image']) : '';
$placeholder_result = isset($template_data['result_image']) ? esc_url($template_data['result_image']) : '';
$feature_enabled = function_exists('mj_member_photo_grimlins_is_enabled') ? mj_member_photo_grimlins_is_enabled() : false;
$members_only = !empty($template_data['members_only']);
$can_apply_avatar = false;
$show_history = $members_only && !$is_preview && is_user_logged_in();

$access_scope = $members_only ? 'members' : 'public';
$access_nonce = wp_create_nonce('mj_member_photo_grimlins_scope_' . $access_scope);

if ($members_only && !$is_preview && is_user_logged_in() && function_exists('mj_member_get_current_member')) {
    $member_candidate = mj_member_get_current_member();
    $can_apply_avatar = $member_candidate && !empty($member_candidate->id);
}

if ($members_only && !is_user_logged_in() && !$is_preview) {
    echo '<div class="mj-member-account-warning" role="alert">' . esc_html__('Cette fonctionnalité est réservée aux membres connectés.', 'mj-member') . '</div>';
    return;
}

$config = array(
    'isPreview' => $is_preview,
    'membersOnly' => $members_only,
    'accessScope' => $access_scope,
    'accessNonce' => $access_nonce,
    'canApplyAvatar' => $can_apply_avatar,
);

$config_json = wp_json_encode($config);
$component_id = 'mj-photo-grimlins-' . wp_generate_uuid4();
$dropzone_label_id = $component_id . '-label';
$status_id = $component_id . '-status';
?>

<div class="mj-photo-grimlins" id="<?php echo esc_attr($component_id); ?>" data-mj-photo-grimlins data-config='<?php echo esc_attr($config_json); ?>'>
    <header class="mj-photo-grimlins__header">
        <h2 class="mj-photo-grimlins__title"><?php echo esc_html($title); ?></h2>
        <?php if ($description !== '') : ?>
            <p class="mj-photo-grimlins__description"><?php echo esc_html($description); ?></p>
        <?php endif; ?>
    </header>

    <?php if (!$feature_enabled && !$is_preview) : ?>
        <div class="mj-member-account-warning" role="alert">
            <?php esc_html_e('La génération Grimlins est désactivée pour le moment. Configurez la clé OpenAI dans les paramètres MJ Member.', 'mj-member'); ?>
        </div>
    <?php endif; ?>

    <form class="mj-photo-grimlins__form" data-photo-grimlins="form" novalidate>
        <div class="mj-photo-grimlins__dropzone" data-photo-grimlins="dropzone" role="button" tabindex="0" aria-labelledby="<?php echo esc_attr($dropzone_label_id); ?>">
            <input type="file" name="mj-photo-grimlins-source" accept="image/jpeg,image/png,image/webp,image/*" data-photo-grimlins="file" aria-hidden="true">
            <input type="file" accept="image/*" capture="environment" data-photo-grimlins="camera-input" aria-hidden="true" class="mj-photo-grimlins__camera-input" tabindex="-1">
            <p id="<?php echo esc_attr($dropzone_label_id); ?>">
                <strong><?php esc_html_e('Dépose ta photo ici', 'mj-member'); ?></strong>
                <br>
                <span><?php esc_html_e('JPG, PNG ou WebP – 5 Mo max.', 'mj-member'); ?></span>
            </p>
            <p>
                <button type="button" class="mj-photo-grimlins__choose" data-photo-grimlins="choose"><?php esc_html_e('Choisir une image', 'mj-member'); ?></button>
            </p>
        </div>

        <div class="mj-photo-grimlins__actions">
            <button type="button" class="mj-photo-grimlins__camera" data-photo-grimlins="camera"><?php esc_html_e('Prendre une photo', 'mj-member'); ?></button>
            <button type="submit" class="mj-photo-grimlins__submit" data-photo-grimlins="submit"><?php echo esc_html($button_label); ?></button>
            <button type="button" class="mj-photo-grimlins__reset" data-photo-grimlins="reset"><?php esc_html_e('Réinitialiser', 'mj-member'); ?></button>
        </div>
    </form>

    <div class="mj-photo-grimlins__camera-modal" data-photo-grimlins="camera-modal" role="dialog" aria-modal="true" aria-labelledby="<?php echo esc_attr($component_id); ?>-camera-title" hidden>
        <div class="mj-photo-grimlins__camera-content">
            <h3 id="<?php echo esc_attr($component_id); ?>-camera-title" class="screen-reader-text"><?php esc_html_e('Prévisualisation caméra', 'mj-member'); ?></h3>
            <video class="mj-photo-grimlins__camera-video" data-photo-grimlins="camera-video" autoplay playsinline muted></video>
            <div class="mj-photo-grimlins__camera-actions">
                <button type="button" class="mj-photo-grimlins__camera-capture" data-photo-grimlins="camera-capture"><?php esc_html_e('Capturer', 'mj-member'); ?></button>
                <button type="button" class="mj-photo-grimlins__camera-cancel" data-photo-grimlins="camera-cancel"><?php esc_html_e('Annuler', 'mj-member'); ?></button>
            </div>
        </div>
    </div>

    <div class="mj-photo-grimlins__preview" data-photo-grimlins="preview-box" <?php echo $is_preview && $placeholder_preview ? '' : 'hidden'; ?> data-placeholder="1">
        <h3 class="screen-reader-text"><?php esc_html_e('Aperçu de la photo sélectionnée', 'mj-member'); ?></h3>
        <div class="mj-photo-grimlins__preview-stage" data-photo-grimlins="preview-stage">
            <img src="<?php echo esc_url($placeholder_preview ?: ''); ?>" alt="" data-photo-grimlins="preview">
            <div class="mj-photo-grimlins__preview-loader" data-photo-grimlins="preview-loader" aria-hidden="true" hidden>
                <div class="mj-photo-grimlins__loader-sphere">
                    <span class="mj-photo-grimlins__loader-orbit"></span>
                    <span class="mj-photo-grimlins__loader-orbit mj-photo-grimlins__loader-orbit--delayed"></span>
                    <span class="mj-photo-grimlins__loader-core"></span>
                </div>
                <span class="mj-photo-grimlins__loader-label"><?php esc_html_e('Transformation en cours', 'mj-member'); ?></span>
            </div>
        </div>
    </div>

    <div class="mj-photo-grimlins__result" data-photo-grimlins="result-box" <?php echo $is_preview && $placeholder_result ? '' : 'hidden'; ?>>
        <h3 class="screen-reader-text"><?php esc_html_e('Avatar Grimlins généré', 'mj-member'); ?></h3>
        <img src="<?php echo esc_url($placeholder_result ?: ''); ?>" alt="" data-photo-grimlins="result">
        <a href="#" class="mj-photo-grimlins__download is-hidden" data-photo-grimlins="download">
            <?php esc_html_e('Télécharger', 'mj-member'); ?>
        </a>
        <button type="button" class="mj-photo-grimlins__apply-avatar is-hidden" data-photo-grimlins="apply-avatar">
            <?php esc_html_e('Utiliser comme avatar', 'mj-member'); ?>
        </button>
    </div>

    <div class="mj-photo-grimlins__status" id="<?php echo esc_attr($status_id); ?>" data-photo-grimlins="status" aria-live="polite"></div>

    <?php if ($show_history) : ?>
        <section class="mj-photo-grimlins__history" data-photo-grimlins="history">
            <header class="mj-photo-grimlins__history-header">
                <h3 class="mj-photo-grimlins__history-title">
                    <?php esc_html_e('Mes avatars Grimlins', 'mj-member'); ?>
                </h3>
                <p class="mj-photo-grimlins__history-limit" data-photo-grimlins="history-limit" hidden></p>
            </header>
            <p class="mj-photo-grimlins__history-empty" data-photo-grimlins="history-empty">
                <?php esc_html_e('Commence par générer un premier avatar Grimlins.', 'mj-member'); ?>
            </p>
            <div class="mj-photo-grimlins__history-list" data-photo-grimlins="history-list" role="list"></div>
        </section>
    <?php elseif ($is_preview) : ?>
        <section class="mj-photo-grimlins__history mj-photo-grimlins__history--preview">
            <header class="mj-photo-grimlins__history-header">
                <h3 class="mj-photo-grimlins__history-title">
                    <?php esc_html_e('Mes avatars Grimlins', 'mj-member'); ?>
                </h3>
            </header>
            <div class="mj-photo-grimlins__history-list" role="list">
                <div class="mj-photo-grimlins__history-item" role="listitem">
                    <div class="mj-photo-grimlins__history-thumb">
                        <img src="<?php echo esc_url($placeholder_result ?: $placeholder_preview); ?>" alt="" loading="lazy">
                    </div>
                    <div class="mj-photo-grimlins__history-meta">
                        <p class="mj-photo-grimlins__history-label">
                            <?php esc_html_e("Exemple d'avatar Grimlins", 'mj-member'); ?>
                        </p>
                        <p class="mj-photo-grimlins__history-actions">
                            <button type="button" disabled>
                                <?php esc_html_e('Utiliser cet avatar', 'mj-member'); ?>
                            </button>
                        </p>
                    </div>
                </div>
            </div>
        </section>
    <?php endif; ?>
</div>
