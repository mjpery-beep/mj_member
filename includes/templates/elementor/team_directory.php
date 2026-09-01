<?php

if (!defined('ABSPATH')) {
    exit;
}

$title = isset($template_data['title']) ? (string) $template_data['title'] : '';
$description = isset($template_data['description']) ? (string) $template_data['description'] : '';
$members = isset($template_data['members']) && is_array($template_data['members']) ? $template_data['members'] : array();
$is_preview = !empty($template_data['is_preview']);
$empty_message = isset($template_data['empty_message']) ? (string) $template_data['empty_message'] : '';

$wrapper_classes = array('mj-team-directory');
if ($is_preview) {
    $wrapper_classes[] = 'is-preview';
}

$surface_classes = array('mj-team-directory__surface');
if (empty($members)) {
    $surface_classes[] = 'is-empty';
}

?>
<div class="<?php echo esc_attr(implode(' ', $wrapper_classes)); ?>">
    <div class="<?php echo esc_attr(implode(' ', $surface_classes)); ?>">
        <?php if ($title !== '' || $description !== '') : ?>
            <header class="mj-team-directory__header">
                <?php if ($title !== '') : ?>
                    <h2 class="mj-team-directory__title"><?php echo esc_html($title); ?></h2>
                <?php endif; ?>
                <?php if ($description !== '') : ?>
                    <p class="mj-team-directory__description"><?php echo esc_html($description); ?></p>
                <?php endif; ?>
            </header>
        <?php endif; ?>

        <?php if (!empty($members)) : ?>
            <div class="mj-team-directory__grid">
                <?php foreach ($members as $member) :
                    $name = isset($member['name']) ? (string) $member['name'] : '';
                    $first_name = isset($member['first_name']) ? (string) $member['first_name'] : '';
                    $last_name = isset($member['last_name']) ? (string) $member['last_name'] : '';
                    $nickname = isset($member['nickname']) ? (string) $member['nickname'] : '';
                    $role_label = isset($member['role_label']) ? (string) $member['role_label'] : '';
                    $role_key = isset($member['role']) ? sanitize_key((string) $member['role']) : '';
                    $email = isset($member['email']) ? sanitize_email((string) $member['email']) : '';
                    $mailto = isset($member['mailto']) ? (string) $member['mailto'] : '';
                    $description_short = isset($member['description']) ? (string) $member['description'] : '';

                    $cover = isset($member['cover']) && is_array($member['cover']) ? $member['cover'] : array();
                    $cover_url = isset($cover['url']) ? (string) $cover['url'] : '';
                    $cover_alt = isset($cover['alt']) ? (string) $cover['alt'] : ($name !== '' ? sprintf(__('Photo de %s', 'mj-member'), $name) : __('Photo du membre', 'mj-member'));

                    $avatar = isset($member['avatar']) && is_array($member['avatar']) ? $member['avatar'] : array();
                    $avatar_url = isset($avatar['url']) ? (string) $avatar['url'] : '';
                    $avatar_initials = isset($avatar['initials']) ? (string) $avatar['initials'] : '';
                    $avatar_alt = isset($avatar['alt']) ? (string) $avatar['alt'] : $cover_alt;
                    ?>
                    <article class="mj-team-directory__card"<?php echo $role_key !== '' ? ' data-role="' . esc_attr($role_key) . '"' : ''; ?>>
                        <div class="mj-team-directory__media">
                            <?php if ($cover_url !== '') : ?>
                                <img class="mj-team-directory__cover" src="<?php echo esc_url($cover_url); ?>" alt="<?php echo esc_attr($cover_alt); ?>" loading="lazy" />
                            <?php else : ?>
                                <div class="mj-team-directory__cover-placeholder" aria-hidden="true"></div>
                            <?php endif; ?>
                            <div class="mj-team-directory__avatar" role="presentation">
                                <?php if ($avatar_url !== '') : ?>
                                    <img class="mj-team-directory__avatar-image" src="<?php echo esc_url($avatar_url); ?>" alt="<?php echo esc_attr($avatar_alt); ?>" loading="lazy" />
                                <?php elseif ($avatar_initials !== '') : ?>
                                    <span class="mj-team-directory__avatar-initials" aria-label="<?php echo esc_attr($name !== '' ? sprintf(__('Initiales de %s', 'mj-member'), $name) : __('Initiales du membre', 'mj-member')); ?>"><?php echo esc_html($avatar_initials); ?></span>
                                <?php else : ?>
                                    <span class="mj-team-directory__avatar-placeholder" aria-hidden="true">MJ</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="mj-team-directory__content">
                            <?php if ($role_label !== '') : ?>
                                <span class="mj-team-directory__role"><?php echo esc_html($role_label); ?></span>
                            <?php endif; ?>
                            <?php if ($name !== '') : ?>
                                <h3 class="mj-team-directory__name"><?php echo esc_html($name); ?></h3>
                            <?php elseif ($first_name !== '' || $last_name !== '') : ?>
                                <h3 class="mj-team-directory__name"><?php echo esc_html(trim($first_name . ' ' . $last_name)); ?></h3>
                            <?php endif; ?>
                            <?php if ($nickname !== '') : ?>
                                <p class="mj-team-directory__nickname"><?php echo esc_html($nickname); ?></p>
                            <?php endif; ?>
                            <?php if ($description_short !== '') : ?>
                                <p class="mj-team-directory__bio"><?php echo esc_html($description_short); ?></p>
                            <?php endif; ?>
                            <?php if ($email !== '') : ?>
                                <a class="mj-team-directory__email" href="<?php echo esc_url($mailto !== '' ? $mailto : 'mailto:' . $email); ?>"><?php echo esc_html($email); ?></a>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php elseif ($empty_message !== '') : ?>
            <p class="mj-team-directory__empty"><?php echo esc_html($empty_message); ?></p>
        <?php endif; ?>
    </div>
</div>
