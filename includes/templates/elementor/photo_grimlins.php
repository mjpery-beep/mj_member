<?php

use Mj\Member\Classes\MjRoles;
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
$show_avatars_section = !array_key_exists('show_avatars_section', $template_data) || !empty($template_data['show_avatars_section']);
$can_apply_avatar = false;
$can_delete_avatar = false;
$show_history = false;
$cta_register_enabled = !empty($template_data['cta_register_enabled']);
$cta_register_label = isset($template_data['cta_register_label']) ? (string) $template_data['cta_register_label'] : __('Utiliser cet avatar pour devenir membre', 'mj-member');
$cta_register_url = isset($template_data['cta_register_url']) ? (string) $template_data['cta_register_url'] : '/mon-compte/inscription';

$access_scope = $members_only ? 'members' : 'public';
$access_nonce = wp_create_nonce('mj_member_photo_grimlins_scope_' . $access_scope);

if (!$is_preview && is_user_logged_in() && function_exists('mj_member_get_current_member')) {
    $member_candidate = mj_member_get_current_member();
    $can_apply_avatar = $member_candidate && !empty($member_candidate->id);
    if ($can_apply_avatar) {
        $candidate_role = isset($member_candidate->role) ? (string) $member_candidate->role : '';
        $can_delete_avatar = MjRoles::isAnimateurOrCoordinateur($candidate_role);
        $show_history = true;
    }
}

if ($members_only && !is_user_logged_in() && !$is_preview) {
    echo '<div class="mj-member-account-warning" role="alert">' . esc_html__('Cette fonctionnalité est réservée aux membres connectés.', 'mj-member') . '</div>';
    return;
}

$fullscreen_dblclick = !array_key_exists('fullscreen_dblclick', $template_data) || !empty($template_data['fullscreen_dblclick']);

$show_avatar_tabs = false;
$allow_young_search = false;
$avatar_tabs = array();
if ($members_only && !$is_preview && is_user_logged_in() && function_exists('mj_member_get_current_member') && function_exists('mj_member_get_guardian_children')) {
    $tabs_member = mj_member_get_current_member();
    if ($tabs_member && !empty($tabs_member->id)) {
        $tabs_role = isset($tabs_member->role) ? (string) $tabs_member->role : '';
        $is_staff_tabs_role = MjRoles::isAnimateurOrCoordinateur($tabs_role);
        $is_tabs_role = MjRoles::isTuteur($tabs_role) || $is_staff_tabs_role;
        $allow_young_search = $is_staff_tabs_role;

        if ($is_tabs_role) {
            $self_photo_id = max((int) ($tabs_member->photo_id ?? 0), (int) ($tabs_member->avatar_id ?? 0));
            $self_photo_url = '';
            if ($self_photo_id > 0) {
                $self_photo_url = wp_get_attachment_image_url($self_photo_id, 'thumbnail');
                if (!$self_photo_url) {
                    $self_photo_url = wp_get_attachment_url($self_photo_id);
                }
            }
            if ($self_photo_url === '' && !empty($tabs_member->email)) {
                $self_photo_url = (string) get_avatar_url((string) $tabs_member->email, array('size' => 64));
            }
            $self_initials = strtoupper(substr((string) ($tabs_member->first_name ?? ''), 0, 1));
            if ($self_initials === '') {
                $self_initials = 'M';
            }

            $avatar_tabs[] = array(
                'member_id' => 0,
                'label' => __('Mon avatar', 'mj-member'),
                'photo_url' => is_string($self_photo_url) ? $self_photo_url : '',
                'initials' => $self_initials,
            );

            $tabs_children = mj_member_get_guardian_children($tabs_member);
            if (!empty($tabs_children) && is_array($tabs_children)) {
                foreach ($tabs_children as $tabs_child) {
                    if (!$tabs_child || !is_object($tabs_child) || empty($tabs_child->id)) {
                        continue;
                    }

                    $tabs_child_name = trim(sprintf('%s %s', (string) ($tabs_child->first_name ?? ''), (string) ($tabs_child->last_name ?? '')));
                    if ($tabs_child_name === '') {
                        $tabs_child_name = sprintf(__('Enfant #%d', 'mj-member'), (int) $tabs_child->id);
                    }

                    $tabs_child_photo_id = max((int) ($tabs_child->photo_id ?? 0), (int) ($tabs_child->avatar_id ?? 0));
                    $tabs_child_photo_url = '';
                    if ($tabs_child_photo_id > 0) {
                        $tabs_child_photo_url = wp_get_attachment_image_url($tabs_child_photo_id, 'thumbnail');
                        if (!$tabs_child_photo_url) {
                            $tabs_child_photo_url = wp_get_attachment_url($tabs_child_photo_id);
                        }
                    }
                    if ($tabs_child_photo_url === '' && !empty($tabs_child->email)) {
                        $tabs_child_photo_url = (string) get_avatar_url((string) $tabs_child->email, array('size' => 64));
                    }

                    $tabs_initials = strtoupper(substr((string) ($tabs_child->first_name ?? ''), 0, 1));
                    if ($tabs_initials === '') {
                        $tabs_initials = strtoupper(substr((string) ($tabs_child->last_name ?? ''), 0, 1));
                    }
                    if ($tabs_initials === '') {
                        $tabs_initials = 'J';
                    }

                    $avatar_tabs[] = array(
                        'member_id' => (int) $tabs_child->id,
                        'label' => $tabs_child_name,
                        'photo_url' => is_string($tabs_child_photo_url) ? $tabs_child_photo_url : '',
                        'initials' => $tabs_initials,
                    );
                }
            }

            $show_avatar_tabs = (count($avatar_tabs) >= 2) || $allow_young_search;
        }
    }
}

$config = array(
    'isPreview' => $is_preview,
    'membersOnly' => $members_only,
    'accessScope' => $access_scope,
    'accessNonce' => $access_nonce,
    'canApplyAvatar' => $can_apply_avatar,
    'canDeleteAvatar' => $can_delete_avatar,
    'ctaRegister' => $cta_register_enabled,
    'ctaRegisterUrl' => $cta_register_url,
    'fullscreenDblClick' => $fullscreen_dblclick,
    'canSearchYoung' => $allow_young_search,
);

$config_json = wp_json_encode($config);
$component_id = 'mj-photo-grimlins-' . wp_generate_uuid4();
$dropzone_label_id = $component_id . '-label';
$status_id = $component_id . '-status';

$mosaic_enabled = !empty($template_data['mosaic_enabled']);
$mosaic_sessions = isset($template_data['mosaic_sessions']) && is_array($template_data['mosaic_sessions']) ? $template_data['mosaic_sessions'] : array();
$mosaic_transition = isset($template_data['mosaic_transition']) && in_array($template_data['mosaic_transition'], array('flip', 'fade', 'hover'), true)
    ? $template_data['mosaic_transition']
    : 'hover';
$has_mosaic = $mosaic_enabled && !empty($mosaic_sessions);
if ($has_mosaic) {
    shuffle($mosaic_sessions);
}
$mosaic_speed = isset($template_data['mosaic_speed']) ? (float) $template_data['mosaic_speed'] : 5;
$fullscreen = !empty($template_data['fullscreen']);

$root_classes = 'mj-photo-grimlins';
if ($has_mosaic) {
    $root_classes .= ' mj-photo-grimlins--has-mosaic';
}
if ($fullscreen) {
    $root_classes .= ' mj-photo-grimlins--fullscreen';
}
?>

<div class="<?php echo esc_attr($root_classes); ?>" id="<?php echo esc_attr($component_id); ?>" data-mj-photo-grimlins data-config='<?php echo esc_attr($config_json); ?>'>

    <?php if ($has_mosaic) : ?>
        <div class="mj-photo-grimlins__mosaic" aria-hidden="true">
            <?php foreach ($mosaic_sessions as $mi => $msession) :
                $m_before = isset($msession['original_url']) ? esc_url($msession['original_url']) : '';
                $m_after = isset($msession['result_url']) ? esc_url($msession['result_url']) : '';
                if ($m_before === '' && $m_after === '') {
                    continue;
                }
                $tile_delay = round(($mi % 6) * 0.8 + (intdiv($mi, 6) * 0.5), 2);
                $tile_duration = round($mosaic_speed + ($mi % 3) * ($mosaic_speed * 0.3), 2);
                $transition_class = 'mj-photo-grimlins__tile--' . $mosaic_transition;
                ?>
                <div class="mj-photo-grimlins__tile <?php echo esc_attr($transition_class); ?>"
                     style="--tile-delay: <?php echo esc_attr($tile_delay); ?>s; --tile-duration: <?php echo esc_attr($tile_duration); ?>s;">
                    <?php if ($m_before !== '') : ?>
                        <div class="mj-photo-grimlins__tile-before" style="background-image:url('<?php echo $m_before; ?>');"></div>
                    <?php endif; ?>
                    <?php if ($m_after !== '') : ?>
                        <div class="mj-photo-grimlins__tile-after" style="background-image:url('<?php echo $m_after; ?>');"></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="mj-photo-grimlins__content">

    <header class="mj-photo-grimlins__header">
        <h2 class="mj-photo-grimlins__title"><?php echo esc_html($title); ?></h2>
        <?php if ($description !== '') : ?>
            <p class="mj-photo-grimlins__description"><?php echo esc_html($description); ?></p>
        <?php endif; ?>
    </header>

    <?php if ($show_avatar_tabs) : ?>
        <nav class="mj-photo-grimlins-tabs" role="tablist" aria-label="<?php esc_attr_e('Choisir la personne', 'mj-member'); ?>">
            <?php foreach ($avatar_tabs as $tab_index => $avatar_tab) : ?>
                <button
                    type="button"
                    class="mj-photo-grimlins-tabs__btn<?php echo $tab_index === 0 ? ' mj-photo-grimlins-tabs__btn--active' : ''; ?>"
                    role="tab"
                    aria-selected="<?php echo $tab_index === 0 ? 'true' : 'false'; ?>"
                    data-mj-pg-tab-btn
                    data-mj-pg-target-member="<?php echo esc_attr((string) $avatar_tab['member_id']); ?>"
                    data-mj-pg-member-label="<?php echo esc_attr((string) $avatar_tab['label']); ?>"
                    <?php if ($tab_index > 0) : ?>tabindex="-1"<?php endif; ?>
                >
                    <span class="mj-photo-grimlins-tabs__avatar" aria-hidden="true">
                        <?php if (!empty($avatar_tab['photo_url'])) : ?>
                            <img src="<?php echo esc_url((string) $avatar_tab['photo_url']); ?>" alt="" loading="lazy" />
                        <?php else : ?>
                            <span class="mj-photo-grimlins-tabs__avatar-fallback"><?php echo esc_html((string) ($avatar_tab['initials'] ?? 'M')); ?></span>
                        <?php endif; ?>
                    </span>
                    <?php echo esc_html((string) $avatar_tab['label']); ?>
                </button>
            <?php endforeach; ?>
            <?php if ($allow_young_search) : ?>
                <button
                    type="button"
                    class="mj-photo-grimlins-tabs__search-btn"
                    data-photo-grimlins="open-young-search"
                >
                    <?php esc_html_e('Chercher un membre +', 'mj-member'); ?>
                </button>
            <?php endif; ?>
        </nav>

        <?php if ($allow_young_search) : ?>
            <div class="mj-photo-grimlins-young-search" data-photo-grimlins="young-search-modal" role="dialog" aria-modal="true" aria-labelledby="<?php echo esc_attr($component_id); ?>-young-search-title" hidden>
                <div class="mj-photo-grimlins-young-search__content" role="document">
                    <header class="mj-photo-grimlins-young-search__header">
                        <h3 id="<?php echo esc_attr($component_id); ?>-young-search-title" class="mj-photo-grimlins-young-search__title"><?php esc_html_e('Chercher un membre', 'mj-member'); ?></h3>
                        <button type="button" class="mj-photo-grimlins-young-search__close" data-photo-grimlins="close-young-search" aria-label="<?php esc_attr_e('Fermer', 'mj-member'); ?>">&times;</button>
                    </header>
                    <form class="mj-photo-grimlins-young-search__form" data-photo-grimlins="young-search-form">
                        <input type="search" data-photo-grimlins="young-search-input" placeholder="<?php esc_attr_e('Nom, prénom ou email (jeune, animateur, coordinateur)', 'mj-member'); ?>" autocomplete="off" />
                        <button type="submit" data-photo-grimlins="young-search-submit"><?php esc_html_e('Rechercher', 'mj-member'); ?></button>
                    </form>
                    <p class="mj-photo-grimlins-young-search__status" data-photo-grimlins="young-search-status" aria-live="polite"></p>
                    <div class="mj-photo-grimlins-young-search__results" data-photo-grimlins="young-search-results" role="list"></div>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (!$feature_enabled && !$is_preview) : ?>
        <div class="mj-member-account-warning" role="alert">
            <?php esc_html_e('La génération Grimlins est désactivée pour le moment. Configurez la clé OpenAI dans les paramètres MJ Member.', 'mj-member'); ?>
        </div>
    <?php endif; ?>

    <form class="mj-photo-grimlins__form" data-photo-grimlins="form" novalidate>
        <div class="mj-photo-grimlins__dropzone" data-photo-grimlins="dropzone" role="button" tabindex="0" aria-labelledby="<?php echo esc_attr($dropzone_label_id); ?>">
            <input type="file" name="mj-photo-grimlins-source" accept="image/jpeg,image/png,image/webp,image/*" data-photo-grimlins="file" aria-hidden="true">
            <input type="file" accept="image/*" capture="environment" data-photo-grimlins="camera-input" aria-hidden="true" class="mj-photo-grimlins__camera-input" tabindex="-1">
            <div class="mj-photo-grimlins__dropzone-placeholder" data-photo-grimlins="dropzone-placeholder">
                <p id="<?php echo esc_attr($dropzone_label_id); ?>">
                    <strong><?php esc_html_e('Dépose ta photo ici', 'mj-member'); ?></strong>
                    <br>
                    <span><?php esc_html_e('JPG, PNG ou WebP – 5 Mo max.', 'mj-member'); ?></span>
                </p>
                <p>
                    <button type="button" class="mj-photo-grimlins__choose" data-photo-grimlins="choose"><?php esc_html_e('Choisir une image', 'mj-member'); ?></button>
                </p>
            </div>

            <div class="mj-photo-grimlins__camera-inline" data-photo-grimlins="camera-modal" role="group" aria-labelledby="<?php echo esc_attr($component_id); ?>-camera-title" hidden>
                <div class="mj-photo-grimlins__camera-content">
                    <h3 id="<?php echo esc_attr($component_id); ?>-camera-title" class="screen-reader-text"><?php esc_html_e('Prévisualisation caméra', 'mj-member'); ?></h3>
                    <video class="mj-photo-grimlins__camera-video" data-photo-grimlins="camera-video" autoplay playsinline muted></video>
                    <div class="mj-photo-grimlins__camera-actions">
                        <button type="button" class="mj-photo-grimlins__camera-capture" data-photo-grimlins="camera-capture"><?php esc_html_e('Capturer', 'mj-member'); ?></button>
                        <button type="button" class="mj-photo-grimlins__camera-cancel" data-photo-grimlins="camera-cancel"><?php esc_html_e('Annuler', 'mj-member'); ?></button>
                    </div>
                </div>
            </div>
        </div>

        <div class="mj-photo-grimlins__actions">
            <button type="button" class="mj-photo-grimlins__camera" data-photo-grimlins="camera"><?php esc_html_e('Prendre une photo', 'mj-member'); ?></button>
            <button type="submit" class="mj-photo-grimlins__submit" data-photo-grimlins="submit" hidden><?php echo esc_html($button_label); ?></button>
            <button type="button" class="mj-photo-grimlins__reset" data-photo-grimlins="reset" hidden><?php esc_html_e('Réinitialiser', 'mj-member'); ?></button>
        </div>
    </form>

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
        <?php if ($cta_register_enabled) : ?>
            <a href="<?php echo esc_url($cta_register_url); ?>" class="mj-photo-grimlins__cta-register is-hidden" data-photo-grimlins="cta-register">
                <?php echo esc_html($cta_register_label); ?>
            </a>
        <?php endif; ?>
    </div>

    <div class="mj-photo-grimlins__status" id="<?php echo esc_attr($status_id); ?>" data-photo-grimlins="status" aria-live="polite"></div>

    <?php if ($show_history && $show_avatars_section) : ?>
        <section class="mj-photo-grimlins__history" data-photo-grimlins="history">
            <header class="mj-photo-grimlins__history-header">
                <h3 class="mj-photo-grimlins__history-title" data-photo-grimlins="history-title" data-history-title-default="<?php esc_attr_e('Mes avatars Grimlins', 'mj-member'); ?>">
                    <?php esc_html_e('Mes avatars Grimlins', 'mj-member'); ?>
                </h3>
                <p class="mj-photo-grimlins__history-limit" data-photo-grimlins="history-limit" hidden></p>
            </header>
            <p class="mj-photo-grimlins__history-empty" data-photo-grimlins="history-empty">
                <?php esc_html_e('Commence par générer un premier avatar Grimlins.', 'mj-member'); ?>
            </p>
            <div class="mj-photo-grimlins__history-list" data-photo-grimlins="history-list" role="list"></div>
        </section>
    <?php elseif ($is_preview && $show_avatars_section) : ?>
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

    </div><!-- /.mj-photo-grimlins__content -->
</div>
