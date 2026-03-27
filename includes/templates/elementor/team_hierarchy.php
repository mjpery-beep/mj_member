<?php

use Mj\Member\Classes\Crud\MjLevels;
use Mj\Member\Classes\Crud\MjMembers;
use Mj\Member\Classes\MjRoles;
use Mj\Member\Core\AssetsManager;
use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

AssetsManager::requirePackage('team-hierarchy');

$settings = isset($widget) && is_object($widget) ? $widget->get_settings_for_display() : array();

$title = isset($settings['title']) ? sanitize_text_field((string) $settings['title']) : '';
$subtitle = isset($settings['subtitle']) ? sanitize_textarea_field((string) $settings['subtitle']) : '';
$coordinatorsLabel = isset($settings['coordinators_label']) ? sanitize_text_field((string) $settings['coordinators_label']) : __('Coordination', 'mj-member');
$animateursLabel = isset($settings['animateurs_label']) ? sanitize_text_field((string) $settings['animateurs_label']) : __('Equipe animation', 'mj-member');
$jeunesLabel = isset($settings['jeunes_label']) ? sanitize_text_field((string) $settings['jeunes_label']) : __('Jeunes', 'mj-member');
$teamStructure = isset($settings['team_structure']) ? sanitize_key((string) $settings['team_structure']) : 'hierarchy';
$flatLabel = isset($settings['flat_label']) ? sanitize_text_field((string) $settings['flat_label']) : __('Toute l\'equipe', 'mj-member');
$showCoordinatorsSection = !isset($settings['show_coordinators']) || $settings['show_coordinators'] === 'yes';
$showAnimateursSection = !isset($settings['show_animateurs']) || $settings['show_animateurs'] === 'yes';
$showJeunesSection = isset($settings['show_jeunes']) && $settings['show_jeunes'] === 'yes';
$showCoordinatorsWithPhotoOnly = isset($settings['show_coordinators_with_photo_only']) && $settings['show_coordinators_with_photo_only'] === 'yes';
$showAnimateursWithPhotoOnly = isset($settings['show_animateurs_with_photo_only']) && $settings['show_animateurs_with_photo_only'] === 'yes';
$showJeunesWithPhotoOnly = isset($settings['show_jeunes_with_photo_only']) && $settings['show_jeunes_with_photo_only'] === 'yes';
$showJeunesXpGtOneOnly = isset($settings['show_jeunes_xp_gt_1_only']) && $settings['show_jeunes_xp_gt_1_only'] === 'yes';
$memberCardsGrid = isset($settings['member_cards_grid']) ? sanitize_key((string) $settings['member_cards_grid']) : 'grid-3';
$emptyMessage = isset($settings['empty_message']) ? sanitize_text_field((string) $settings['empty_message']) : __('Aucun membre a afficher pour le moment.', 'mj-member');

if (!in_array($memberCardsGrid, array('grid-2', 'grid-3', 'grid-4'), true)) {
    $memberCardsGrid = 'grid-3';
}

$memberGridClass = 'mj-team-hierarchy__grid--' . $memberCardsGrid;

$showJobTitle = !isset($settings['show_job_title']) || $settings['show_job_title'] === 'yes';
$showBio = !isset($settings['show_bio']) || $settings['show_bio'] === 'yes';
$bioMaxLines = isset($settings['bio_max_lines']) ? max(1, min(10, (int) $settings['bio_max_lines'])) : 3;
$showContacts = !isset($settings['show_contacts']) || $settings['show_contacts'] === 'yes';
$showLevel = !isset($settings['show_level']) || $settings['show_level'] === 'yes';
$showProgress = !isset($settings['show_progress']) || $settings['show_progress'] === 'yes';
$showInactive = isset($settings['show_inactive']) && $settings['show_inactive'] === 'yes';

$maxCoordinators = isset($settings['max_coordinators']) ? max(0, (int) $settings['max_coordinators']) : 6;
$maxAnimateurs = isset($settings['max_animateurs']) ? max(0, (int) $settings['max_animateurs']) : 18;
$maxJeunes = isset($settings['max_jeunes']) ? max(0, (int) $settings['max_jeunes']) : 24;

$allowedOrderBy = array('last_name', 'first_name', 'xp_total', 'date_inscription');
$orderBy = isset($settings['orderby']) ? sanitize_key((string) $settings['orderby']) : 'last_name';
if (!in_array($orderBy, $allowedOrderBy, true)) {
    $orderBy = 'last_name';
}

$order = isset($settings['order']) && strtoupper((string) $settings['order']) === 'DESC' ? 'DESC' : 'ASC';

$roleLabels = MjMembers::getRoleLabels();
$jobTitleLabels = array(
    'coordination' => __('Coordination', 'mj-member'),
    'animateur' => __('Animateur', 'mj-member'),
    'communication' => __('Communication', 'mj-member'),
    'autre' => __('Autre', 'mj-member'),
);

$isPreview = function_exists('is_elementor_preview') && is_elementor_preview();
$assetBaseUrl = trailingslashit(Config::url()) . 'css/img/';
$xpIconUrl = esc_url($assetBaseUrl . 'xp-gem.svg');

$getProgressionForXp = static function (int $xp) {
    static $cache = array();

    $xp = max(0, $xp);
    if (array_key_exists($xp, $cache)) {
        return $cache[$xp];
    }

    $cache[$xp] = MjLevels::get_progression($xp);
    return $cache[$xp];
};

$formatMemberCard = static function ($member, string $roleKey) use ($roleLabels, $jobTitleLabels, $getProgressionForXp): array {
    if (!$member || !is_object($member)) {
        return array();
    }

    $firstName = trim((string) $member->get('first_name', ''));
    $lastName = trim((string) $member->get('last_name', ''));
    $displayName = trim($firstName . ' ' . $lastName);
    if ($displayName === '') {
        $displayName = trim((string) $member->get('nickname', ''));
    }

    $email = sanitize_email((string) $member->get('email', ''));
    $phone = trim((string) $member->get('phone', ''));

    $avatarUrl = '';
    $photoId = max((int) $member->get('photo_id', 0), (int) $member->get('avatar_id', 0));
    if ($photoId > 0) {
        $avatarCandidate = wp_get_attachment_image_url($photoId, 'medium');
        if (!is_string($avatarCandidate) || $avatarCandidate === '') {
            $avatarCandidate = wp_get_attachment_url($photoId);
        }
        if (is_string($avatarCandidate) && $avatarCandidate !== '') {
            $avatarUrl = $avatarCandidate;
        }
    }

    if ($avatarUrl === '' && $email !== '') {
        $avatarUrl = (string) get_avatar_url($email, array('size' => 256));
    }

    $initialSource = $displayName !== '' ? $displayName : __('MJ', 'mj-member');
    $words = preg_split('/[\s\-]+/', $initialSource);
    if (!is_array($words)) {
        $words = array($initialSource);
    }

    $initials = '';
    foreach ($words as $word) {
        $word = trim((string) $word);
        if ($word === '') {
            continue;
        }

        if (function_exists('mb_substr')) {
            $initials .= mb_substr($word, 0, 1, 'UTF-8');
        } else {
            $initials .= substr($word, 0, 1);
        }

        $length = function_exists('mb_strlen') ? mb_strlen($initials, 'UTF-8') : strlen($initials);
        if ($length >= 2) {
            break;
        }
    }

    if ($initials === '') {
        if (function_exists('mb_substr')) {
            $initials = mb_substr($initialSource, 0, 1, 'UTF-8');
        } else {
            $initials = substr($initialSource, 0, 1);
        }
    }

    if (function_exists('mb_strtoupper')) {
        $initials = mb_strtoupper($initials, 'UTF-8');
    } else {
        $initials = strtoupper($initials);
    }

    $rawBio = trim((string) $member->get('description_courte', ''));
    if ($rawBio === '') {
        $rawBio = wp_strip_all_tags((string) $member->get('job_description', ''));
    }
    $bio = sanitize_text_field($rawBio);

    $jobTitleRaw = trim((string) $member->get('job_title', ''));
    $jobTitleKey = sanitize_key($jobTitleRaw);
    $jobTitleLabel = '';
    if ($jobTitleRaw !== '') {
        $jobTitleLabel = $jobTitleLabels[$jobTitleKey] ?? ucfirst(str_replace('_', ' ', $jobTitleKey));
        if ($jobTitleLabel === ucfirst(str_replace('_', ' ', $jobTitleKey))) {
            $jobTitleLabel = sanitize_text_field(str_replace(array('_', '-'), ' ', $jobTitleRaw));
        }
    }

    $xpTotal = max(0, (int) $member->get('xp_total', 0));
    $progression = $getProgressionForXp($xpTotal);

    $levelNumber = 0;
    $levelTitle = __('Niveau', 'mj-member');
    $progressPercent = 0;
    $levelImageUrl = '';

    if (is_array($progression)) {
        if (!empty($progression['current_level']) && is_array($progression['current_level'])) {
            $levelNumber = isset($progression['current_level']['level_number']) ? (int) $progression['current_level']['level_number'] : 0;
            $titleCandidate = isset($progression['current_level']['title']) ? (string) $progression['current_level']['title'] : '';
            if ($titleCandidate !== '') {
                $levelTitle = sanitize_text_field($titleCandidate);
            }

            $imageCandidate = isset($progression['current_level']['image_url']) ? (string) $progression['current_level']['image_url'] : '';
            if ($imageCandidate !== '') {
                $levelImageUrl = esc_url_raw($imageCandidate);
            }
        }
        $progressPercent = isset($progression['progress_percent']) ? max(0, min(100, (int) $progression['progress_percent'])) : 0;
    }

    $roleLabel = $roleLabels[$roleKey] ?? ucfirst($roleKey);

    $telHref = '';
    if ($phone !== '') {
        $telHrefCandidate = preg_replace('/[^0-9+]/', '', $phone);
        if (is_string($telHrefCandidate) && $telHrefCandidate !== '') {
            $telHref = 'tel:' . $telHrefCandidate;
        }
    }

    return array(
        'name' => $displayName !== '' ? sanitize_text_field($displayName) : __('Membre MJ', 'mj-member'),
        'role_label' => sanitize_text_field((string) $roleLabel),
        'job_title' => sanitize_text_field((string) $jobTitleLabel),
        'job_badge' => sanitize_text_field((string) $jobTitleLabel),
        'bio' => $bio,
        'avatar_url' => $avatarUrl !== '' ? esc_url($avatarUrl) : '',
        'avatar_alt' => sanitize_text_field(sprintf(__('Photo de %s', 'mj-member'), $displayName !== '' ? $displayName : __('Membre MJ', 'mj-member'))),
        'initials' => sanitize_text_field($initials),
        'has_photo' => $photoId > 0,
        'phone' => sanitize_text_field($phone),
        'tel_href' => $telHref,
        'email' => $email,
        'mailto_href' => $email !== '' ? 'mailto:' . rawurlencode($email) : '',
        'xp_total' => $xpTotal,
        'level_number' => $levelNumber,
        'level_title' => $levelTitle,
        'level_image_url' => $levelImageUrl,
        'progress_percent' => $progressPercent,
        'is_inactive' => sanitize_key((string) $member->get('status', 'active')) === MjMembers::STATUS_INACTIVE,
    );
};

$queryForRole = static function (string $role, int $limit, bool $withPhotoOnly = false, ?callable $extraFilter = null) use ($orderBy, $order, $showInactive): array {
    $filters = array(
        'role' => $role,
    );

    if (!$showInactive) {
        $filters['status'] = MjMembers::STATUS_ACTIVE;
    }

    $queryLimit = ($withPhotoOnly || $extraFilter !== null) ? 0 : $limit;

    $rows = MjMembers::get_all(array(
        'limit' => $queryLimit,
        'orderby' => $orderBy,
        'order' => $order,
        'filters' => $filters,
    ));

    if ($withPhotoOnly) {
        $rows = array_values(array_filter($rows, static function ($member): bool {
            if (!$member || !is_object($member)) {
                return false;
            }

            $photoId = max((int) $member->get('photo_id', 0), (int) $member->get('avatar_id', 0));
            return $photoId > 0;
        }));
    }

    if ($extraFilter !== null) {
        $rows = array_values(array_filter($rows, $extraFilter));
    }

    if ($limit > 0) {
        $rows = array_slice($rows, 0, $limit);
    }

    return $rows;
};

$coordinatorsRaw = $queryForRole(MjRoles::COORDINATEUR, $maxCoordinators, $showCoordinatorsWithPhotoOnly);
$animateursRaw = $queryForRole(MjRoles::ANIMATEUR, $maxAnimateurs, $showAnimateursWithPhotoOnly);
$jeunesRaw = $queryForRole(
    MjRoles::JEUNE,
    $maxJeunes,
    $showJeunesWithPhotoOnly,
    $showJeunesXpGtOneOnly
        ? static function ($member): bool {
            if (!$member || !is_object($member)) {
                return false;
            }

            return (int) $member->get('xp_total', 0) > 1;
        }
        : null
);

$coordinators = array();
foreach ($coordinatorsRaw as $member) {
    $card = $formatMemberCard($member, MjRoles::COORDINATEUR);
    if (!empty($card)) {
        $coordinators[] = $card;
    }
}

$animateurs = array();
foreach ($animateursRaw as $member) {
    $card = $formatMemberCard($member, MjRoles::ANIMATEUR);
    if (!empty($card)) {
        $animateurs[] = $card;
    }
}

$jeunes = array();
foreach ($jeunesRaw as $member) {
    $card = $formatMemberCard($member, MjRoles::JEUNE);
    if (!empty($card)) {
        $jeunes[] = $card;
    }
}

if ($isPreview && empty($coordinators) && empty($animateurs)) {
    $coordinators = array(
        array(
            'name' => 'Sarah Dupont',
            'role_label' => __('Coordinateur', 'mj-member'),
            'job_title' => __('Coordination', 'mj-member'),
            'job_badge' => __('Coordination', 'mj-member'),
            'bio' => __('Pilote les projets pedagogiques et accompagne l\'equipe terrain.', 'mj-member'),
            'avatar_url' => '',
            'avatar_alt' => __('Photo de Sarah Dupont', 'mj-member'),
            'initials' => 'SD',
            'has_photo' => false,
            'phone' => '+32 470 11 22 33',
            'tel_href' => 'tel:+32470112233',
            'email' => 'sarah.dupont@example.com',
            'mailto_href' => 'mailto:sarah.dupont%40example.com',
            'xp_total' => 1680,
            'level_number' => 8,
            'level_title' => __('Leader terrain', 'mj-member'),
            'level_image_url' => '',
            'progress_percent' => 64,
            'is_inactive' => false,
        ),
    );

    $animateurs = array(
        array(
            'name' => 'Yassine El Aroussi',
            'role_label' => __('Animateur', 'mj-member'),
            'job_title' => __('Animateur', 'mj-member'),
            'job_badge' => __('Animateur', 'mj-member'),
            'bio' => __('Anime les ateliers urbains et la programmation multimedia.', 'mj-member'),
            'avatar_url' => '',
            'avatar_alt' => __('Photo de Yassine El Aroussi', 'mj-member'),
            'initials' => 'YA',
            'has_photo' => false,
            'phone' => '+32 473 10 10 10',
            'tel_href' => 'tel:+32473101010',
            'email' => 'yassine.elaroussi@example.com',
            'mailto_href' => 'mailto:yassine.elaroussi%40example.com',
            'xp_total' => 980,
            'level_number' => 5,
            'level_title' => __('Explorateur', 'mj-member'),
            'level_image_url' => '',
            'progress_percent' => 38,
            'is_inactive' => false,
        ),
        array(
            'name' => 'Laura Martin',
            'role_label' => __('Animateur', 'mj-member'),
            'job_title' => __('Communication', 'mj-member'),
            'job_badge' => __('Communication', 'mj-member'),
            'bio' => __('Coordonne les contenus reseaux et les actions de visibilite locale.', 'mj-member'),
            'avatar_url' => '',
            'avatar_alt' => __('Photo de Laura Martin', 'mj-member'),
            'initials' => 'LM',
            'has_photo' => false,
            'phone' => '+32 478 55 44 33',
            'tel_href' => 'tel:+32478554433',
            'email' => 'laura.martin@example.com',
            'mailto_href' => 'mailto:laura.martin%40example.com',
            'xp_total' => 720,
            'level_number' => 4,
            'level_title' => __('Engage', 'mj-member'),
            'level_image_url' => '',
            'progress_percent' => 55,
            'is_inactive' => false,
        ),
        array(
            'name' => 'Bastien Leroy',
            'role_label' => __('Animateur', 'mj-member'),
            'job_title' => __('Animateur', 'mj-member'),
            'job_badge' => __('Animateur', 'mj-member'),
            'bio' => __('Accompagne les jeunes sur les projets sportifs et les sorties.', 'mj-member'),
            'avatar_url' => '',
            'avatar_alt' => __('Photo de Bastien Leroy', 'mj-member'),
            'initials' => 'BL',
            'has_photo' => false,
            'phone' => '',
            'tel_href' => '',
            'email' => 'bastien.leroy@example.com',
            'mailto_href' => 'mailto:bastien.leroy%40example.com',
            'xp_total' => 540,
            'level_number' => 3,
            'level_title' => __('Apprenant', 'mj-member'),
            'level_image_url' => '',
            'progress_percent' => 74,
            'is_inactive' => false,
        ),
    );
}

if ($isPreview && $showJeunesSection && empty($jeunes)) {
    $jeunes = array(
        array(
            'name' => 'Nora Janssens',
            'role_label' => __('Jeune', 'mj-member'),
            'job_title' => '',
            'job_badge' => '',
            'bio' => __('Participe aux ateliers photo et aux projets multimedia.', 'mj-member'),
            'avatar_url' => '',
            'avatar_alt' => __('Photo de Nora Janssens', 'mj-member'),
            'initials' => 'NJ',
            'has_photo' => false,
            'phone' => '',
            'tel_href' => '',
            'email' => 'nora.janssens@example.com',
            'mailto_href' => 'mailto:nora.janssens%40example.com',
            'xp_total' => 260,
            'level_number' => 2,
            'level_title' => __('Explorateur', 'mj-member'),
            'level_image_url' => '',
            'progress_percent' => 41,
            'is_inactive' => false,
        ),
    );
}

if (!$showCoordinatorsSection) {
    $coordinators = array();
}

if (!$showAnimateursSection) {
    $animateurs = array();
}

if (!$showJeunesSection) {
    $jeunes = array();
}
$hasMembers = !empty($coordinators) || !empty($animateurs) || !empty($jeunes);
$isFlatStructure = ($teamStructure === 'flat');
$allMembers = array_values(array_merge($coordinators, $animateurs, $jeunes));
?>

<div class="mj-team-hierarchy" data-has-members="<?php echo $hasMembers ? '1' : '0'; ?>" style="--mj-team-bio-lines: <?php echo esc_attr((string) $bioMaxLines); ?>;">
    <?php if ($title !== '') : ?>
        <h2 class="mj-team-hierarchy__title"><?php echo esc_html($title); ?></h2>
    <?php endif; ?>

    <?php if ($subtitle !== '') : ?>
        <p class="mj-team-hierarchy__subtitle"><?php echo esc_html($subtitle); ?></p>
    <?php endif; ?>

    <?php if (!$hasMembers) : ?>
        <div class="mj-team-hierarchy__empty"><?php echo esc_html($emptyMessage); ?></div>
    <?php else : ?>
        <?php if ($isFlatStructure) : ?>
            <section class="mj-team-hierarchy__section mj-team-hierarchy__section--flat">
                <h3 class="mj-team-hierarchy__section-title"><?php echo esc_html($flatLabel); ?></h3>
                <div class="mj-team-hierarchy__grid mj-team-hierarchy__grid--flat <?php echo esc_attr($memberGridClass); ?> <?php echo count($allMembers) === 1 ? 'is-single-card' : ''; ?>">
                    <?php foreach ($allMembers as $member) : ?>
                        <article class="mj-team-hierarchy__card <?php echo !empty($member['is_inactive']) ? 'is-inactive' : ''; ?>">
                            <div class="mj-team-hierarchy__avatar-wrap" aria-hidden="true">
                                <?php if (!empty($member['avatar_url'])) : ?>
                                    <img class="mj-team-hierarchy__avatar" src="<?php echo esc_url($member['avatar_url']); ?>" alt="<?php echo esc_attr($member['avatar_alt']); ?>" loading="lazy" />
                                <?php else : ?>
                                    <span class="mj-team-hierarchy__avatar-fallback"><?php echo esc_html($member['initials']); ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="mj-team-hierarchy__body">
                                <div class="mj-team-hierarchy__meta-row">
                                    <?php if ($showJobTitle && !empty($member['job_badge'])) : ?>
                                        <span class="mj-team-hierarchy__role-pill"><?php echo esc_html($member['job_badge']); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($member['is_inactive'])) : ?>
                                        <span class="mj-team-hierarchy__inactive-pill"><?php esc_html_e('Inactif', 'mj-member'); ?></span>
                                    <?php endif; ?>
                                </div>

                                <h4 class="mj-team-hierarchy__name"><?php echo esc_html($member['name']); ?></h4>

                                <?php if ($showBio && !empty($member['bio'])) : ?>
                                    <p class="mj-team-hierarchy__bio"><?php echo esc_html($member['bio']); ?></p>
                                <?php endif; ?>

                                <?php if ($showLevel) : ?>
                                    <div class="mj-team-hierarchy__stats">
                                        <span class="mj-team-hierarchy__stat-chip">
                                            <span class="mj-team-hierarchy__stat-icon mj-team-hierarchy__stat-icon--level" aria-hidden="true">
                                                <svg viewBox="0 0 24 24"><path d="M12 2l2.7 5.48 6.05.88-4.38 4.27 1.03 6.03L12 15.77 6.6 18.66l1.03-6.03-4.38-4.27 6.05-.88L12 2z"/></svg>
                                            </span>
                                            <?php echo esc_html(sprintf(__('Niv. %1$d - %2$s', 'mj-member'), max(0, (int) $member['level_number']), $member['level_title'])); ?>
                                        </span>
                                        <span class="mj-team-hierarchy__stat-chip">
                                            <img class="mj-team-hierarchy__stat-icon" src="<?php echo esc_url($xpIconUrl); ?>" alt="" aria-hidden="true" />
                                            <?php echo esc_html((string) $member['xp_total']); ?> XP
                                        </span>
                                    </div>
                                    <?php if ($showProgress) : ?>
                                        <div class="mj-team-hierarchy__progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr((string) $member['progress_percent']); ?>">
                                            <span class="mj-team-hierarchy__progress-bar" style="width: <?php echo esc_attr((string) $member['progress_percent']); ?>%;"></span>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <?php if ($showContacts) : ?>
                                    <div class="mj-team-hierarchy__contacts">
                                        <?php if (!empty($member['tel_href'])) : ?>
                                            <a class="mj-team-hierarchy__contact-btn" href="<?php echo esc_attr($member['tel_href']); ?>" aria-label="<?php esc_attr_e('Appeler', 'mj-member'); ?>">
                                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.86 19.86 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.86 19.86 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.77 12.77 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.77 12.77 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                                            </a>
                                        <?php endif; ?>

                                        <?php if (!empty($member['mailto_href'])) : ?>
                                            <a class="mj-team-hierarchy__contact-btn" href="<?php echo esc_attr($member['mailto_href']); ?>" aria-label="<?php esc_attr_e('Envoyer un e-mail', 'mj-member'); ?>">
                                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h16a2 2 0 0 1 2 2v.2l-10 5.9a2 2 0 0 1-2 0L2 6.2V6a2 2 0 0 1 2-2zm18 5.03V18a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V9.03l7 4.13a6 6 0 0 0 6 0l7-4.13z"/></svg>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php else : ?>
        <?php if (!empty($coordinators)) : ?>
            <section class="mj-team-hierarchy__section mj-team-hierarchy__section--coordinators">
                <h3 class="mj-team-hierarchy__section-title"><?php echo esc_html($coordinatorsLabel); ?></h3>
                <div class="mj-team-hierarchy__grid mj-team-hierarchy__grid--coordinators <?php echo esc_attr($memberGridClass); ?> <?php echo count($coordinators) === 1 ? 'is-single-card' : ''; ?>">
                    <?php foreach ($coordinators as $member) : ?>
                        <article class="mj-team-hierarchy__card <?php echo !empty($member['is_inactive']) ? 'is-inactive' : ''; ?>">
                            <div class="mj-team-hierarchy__avatar-wrap" aria-hidden="true">
                                <?php if (!empty($member['avatar_url'])) : ?>
                                    <img class="mj-team-hierarchy__avatar" src="<?php echo esc_url($member['avatar_url']); ?>" alt="<?php echo esc_attr($member['avatar_alt']); ?>" loading="lazy" />
                                <?php else : ?>
                                    <span class="mj-team-hierarchy__avatar-fallback"><?php echo esc_html($member['initials']); ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="mj-team-hierarchy__body">
                                <div class="mj-team-hierarchy__meta-row">
                                    <?php if ($showJobTitle && !empty($member['job_badge'])) : ?>
                                        <span class="mj-team-hierarchy__role-pill"><?php echo esc_html($member['job_badge']); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($member['is_inactive'])) : ?>
                                        <span class="mj-team-hierarchy__inactive-pill"><?php esc_html_e('Inactif', 'mj-member'); ?></span>
                                    <?php endif; ?>
                                </div>

                                <h4 class="mj-team-hierarchy__name"><?php echo esc_html($member['name']); ?></h4>

                                <?php if ($showBio && !empty($member['bio'])) : ?>
                                    <p class="mj-team-hierarchy__bio"><?php echo esc_html($member['bio']); ?></p>
                                <?php endif; ?>

                                <?php if ($showLevel) : ?>
                                    <div class="mj-team-hierarchy__stats">
                                        <span class="mj-team-hierarchy__stat-chip">
                                            <span class="mj-team-hierarchy__stat-icon mj-team-hierarchy__stat-icon--level" aria-hidden="true">
                                                <svg viewBox="0 0 24 24"><path d="M12 2l2.7 5.48 6.05.88-4.38 4.27 1.03 6.03L12 15.77 6.6 18.66l1.03-6.03-4.38-4.27 6.05-.88L12 2z"/></svg>
                                            </span>
                                            <?php echo esc_html(sprintf(__('Niv. %1$d - %2$s', 'mj-member'), max(0, (int) $member['level_number']), $member['level_title'])); ?>
                                        </span>
                                        <span class="mj-team-hierarchy__stat-chip">
                                            <img class="mj-team-hierarchy__stat-icon" src="<?php echo esc_url($xpIconUrl); ?>" alt="" aria-hidden="true" />
                                            <?php echo esc_html((string) $member['xp_total']); ?> XP
                                        </span>
                                    </div>
                                    <?php if ($showProgress) : ?>
                                        <div class="mj-team-hierarchy__progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr((string) $member['progress_percent']); ?>">
                                            <span class="mj-team-hierarchy__progress-bar" style="width: <?php echo esc_attr((string) $member['progress_percent']); ?>%;"></span>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <?php if ($showContacts) : ?>
                                    <div class="mj-team-hierarchy__contacts">
                                        <?php if (!empty($member['tel_href'])) : ?>
                                            <a class="mj-team-hierarchy__contact-btn" href="<?php echo esc_attr($member['tel_href']); ?>" aria-label="<?php esc_attr_e('Appeler', 'mj-member'); ?>">
                                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.86 19.86 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.86 19.86 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.77 12.77 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.77 12.77 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                                            </a>
                                        <?php endif; ?>

                                        <?php if (!empty($member['mailto_href'])) : ?>
                                            <a class="mj-team-hierarchy__contact-btn" href="<?php echo esc_attr($member['mailto_href']); ?>" aria-label="<?php esc_attr_e('Envoyer un e-mail', 'mj-member'); ?>">
                                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h16a2 2 0 0 1 2 2v.2l-10 5.9a2 2 0 0 1-2 0L2 6.2V6a2 2 0 0 1 2-2zm18 5.03V18a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V9.03l7 4.13a6 6 0 0 0 6 0l7-4.13z"/></svg>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if (!empty($coordinators) && (!empty($animateurs) || !empty($jeunes))) : ?>
            <div class="mj-team-hierarchy__connector" aria-hidden="true"></div>
        <?php endif; ?>

        <?php if (!empty($animateurs)) : ?>
            <section class="mj-team-hierarchy__section mj-team-hierarchy__section--animateurs">
                <h3 class="mj-team-hierarchy__section-title"><?php echo esc_html($animateursLabel); ?></h3>
                <div class="mj-team-hierarchy__grid mj-team-hierarchy__grid--animateurs <?php echo esc_attr($memberGridClass); ?> <?php echo count($animateurs) === 1 ? 'is-single-card' : ''; ?>">
                    <?php foreach ($animateurs as $member) : ?>
                        <article class="mj-team-hierarchy__card <?php echo !empty($member['is_inactive']) ? 'is-inactive' : ''; ?>">
                            <div class="mj-team-hierarchy__avatar-wrap" aria-hidden="true">
                                <?php if (!empty($member['avatar_url'])) : ?>
                                    <img class="mj-team-hierarchy__avatar" src="<?php echo esc_url($member['avatar_url']); ?>" alt="<?php echo esc_attr($member['avatar_alt']); ?>" loading="lazy" />
                                <?php else : ?>
                                    <span class="mj-team-hierarchy__avatar-fallback"><?php echo esc_html($member['initials']); ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="mj-team-hierarchy__body">
                                <div class="mj-team-hierarchy__meta-row">
                                    <?php if ($showJobTitle && !empty($member['job_badge'])) : ?>
                                        <span class="mj-team-hierarchy__role-pill"><?php echo esc_html($member['job_badge']); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($member['is_inactive'])) : ?>
                                        <span class="mj-team-hierarchy__inactive-pill"><?php esc_html_e('Inactif', 'mj-member'); ?></span>
                                    <?php endif; ?>
                                </div>

                                <h4 class="mj-team-hierarchy__name"><?php echo esc_html($member['name']); ?></h4>

                                <?php if ($showBio && !empty($member['bio'])) : ?>
                                    <p class="mj-team-hierarchy__bio"><?php echo esc_html($member['bio']); ?></p>
                                <?php endif; ?>

                                <?php if ($showLevel) : ?>
                                    <div class="mj-team-hierarchy__stats">
                                        <span class="mj-team-hierarchy__stat-chip">
                                            <span class="mj-team-hierarchy__stat-icon mj-team-hierarchy__stat-icon--level" aria-hidden="true">
                                                <svg viewBox="0 0 24 24"><path d="M12 2l2.7 5.48 6.05.88-4.38 4.27 1.03 6.03L12 15.77 6.6 18.66l1.03-6.03-4.38-4.27 6.05-.88L12 2z"/></svg>
                                            </span>
                                            <?php echo esc_html(sprintf(__('Niv. %1$d - %2$s', 'mj-member'), max(0, (int) $member['level_number']), $member['level_title'])); ?>
                                        </span>
                                        <span class="mj-team-hierarchy__stat-chip">
                                            <img class="mj-team-hierarchy__stat-icon" src="<?php echo esc_url($xpIconUrl); ?>" alt="" aria-hidden="true" />
                                            <?php echo esc_html((string) $member['xp_total']); ?> XP
                                        </span>
                                    </div>
                                    <?php if ($showProgress) : ?>
                                        <div class="mj-team-hierarchy__progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr((string) $member['progress_percent']); ?>">
                                            <span class="mj-team-hierarchy__progress-bar" style="width: <?php echo esc_attr((string) $member['progress_percent']); ?>%;"></span>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <?php if ($showContacts) : ?>
                                    <div class="mj-team-hierarchy__contacts">
                                        <?php if (!empty($member['tel_href'])) : ?>
                                            <a class="mj-team-hierarchy__contact-btn" href="<?php echo esc_attr($member['tel_href']); ?>" aria-label="<?php esc_attr_e('Appeler', 'mj-member'); ?>">
                                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.86 19.86 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.86 19.86 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.77 12.77 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.77 12.77 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                                            </a>
                                        <?php endif; ?>

                                        <?php if (!empty($member['mailto_href'])) : ?>
                                            <a class="mj-team-hierarchy__contact-btn" href="<?php echo esc_attr($member['mailto_href']); ?>" aria-label="<?php esc_attr_e('Envoyer un e-mail', 'mj-member'); ?>">
                                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h16a2 2 0 0 1 2 2v.2l-10 5.9a2 2 0 0 1-2 0L2 6.2V6a2 2 0 0 1 2-2zm18 5.03V18a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V9.03l7 4.13a6 6 0 0 0 6 0l7-4.13z"/></svg>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if (!empty($jeunes)) : ?>
            <section class="mj-team-hierarchy__section mj-team-hierarchy__section--jeunes">
                <h3 class="mj-team-hierarchy__section-title"><?php echo esc_html($jeunesLabel); ?></h3>
                <div class="mj-team-hierarchy__grid mj-team-hierarchy__grid--jeunes <?php echo esc_attr($memberGridClass); ?> <?php echo count($jeunes) === 1 ? 'is-single-card' : ''; ?>">
                    <?php foreach ($jeunes as $member) : ?>
                        <article class="mj-team-hierarchy__card <?php echo !empty($member['is_inactive']) ? 'is-inactive' : ''; ?>">
                            <div class="mj-team-hierarchy__avatar-wrap" aria-hidden="true">
                                <?php if (!empty($member['avatar_url'])) : ?>
                                    <img class="mj-team-hierarchy__avatar" src="<?php echo esc_url($member['avatar_url']); ?>" alt="<?php echo esc_attr($member['avatar_alt']); ?>" loading="lazy" />
                                <?php else : ?>
                                    <span class="mj-team-hierarchy__avatar-fallback"><?php echo esc_html($member['initials']); ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="mj-team-hierarchy__body">
                                <div class="mj-team-hierarchy__meta-row">
                                    <?php if ($showJobTitle && !empty($member['job_badge'])) : ?>
                                        <span class="mj-team-hierarchy__role-pill"><?php echo esc_html($member['job_badge']); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($member['is_inactive'])) : ?>
                                        <span class="mj-team-hierarchy__inactive-pill"><?php esc_html_e('Inactif', 'mj-member'); ?></span>
                                    <?php endif; ?>
                                </div>

                                <h4 class="mj-team-hierarchy__name"><?php echo esc_html($member['name']); ?></h4>

                                <?php if ($showBio && !empty($member['bio'])) : ?>
                                    <p class="mj-team-hierarchy__bio"><?php echo esc_html($member['bio']); ?></p>
                                <?php endif; ?>

                                <?php if ($showLevel) : ?>
                                    <div class="mj-team-hierarchy__stats">
                                        <span class="mj-team-hierarchy__stat-chip">
                                            <span class="mj-team-hierarchy__stat-icon mj-team-hierarchy__stat-icon--level" aria-hidden="true">
                                                <svg viewBox="0 0 24 24"><path d="M12 2l2.7 5.48 6.05.88-4.38 4.27 1.03 6.03L12 15.77 6.6 18.66l1.03-6.03-4.38-4.27 6.05-.88L12 2z"/></svg>
                                            </span>
                                            <?php echo esc_html(sprintf(__('Niv. %1$d - %2$s', 'mj-member'), max(0, (int) $member['level_number']), $member['level_title'])); ?>
                                        </span>
                                        <span class="mj-team-hierarchy__stat-chip">
                                            <img class="mj-team-hierarchy__stat-icon" src="<?php echo esc_url($xpIconUrl); ?>" alt="" aria-hidden="true" />
                                            <?php echo esc_html((string) $member['xp_total']); ?> XP
                                        </span>
                                    </div>
                                    <?php if ($showProgress) : ?>
                                        <div class="mj-team-hierarchy__progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr((string) $member['progress_percent']); ?>">
                                            <span class="mj-team-hierarchy__progress-bar" style="width: <?php echo esc_attr((string) $member['progress_percent']); ?>%;"></span>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <?php if ($showContacts) : ?>
                                    <div class="mj-team-hierarchy__contacts">
                                        <?php if (!empty($member['tel_href'])) : ?>
                                            <a class="mj-team-hierarchy__contact-btn" href="<?php echo esc_attr($member['tel_href']); ?>" aria-label="<?php esc_attr_e('Appeler', 'mj-member'); ?>">
                                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.86 19.86 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.86 19.86 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.77 12.77 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.77 12.77 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                                            </a>
                                        <?php endif; ?>

                                        <?php if (!empty($member['mailto_href'])) : ?>
                                            <a class="mj-team-hierarchy__contact-btn" href="<?php echo esc_attr($member['mailto_href']); ?>" aria-label="<?php esc_attr_e('Envoyer un e-mail', 'mj-member'); ?>">
                                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h16a2 2 0 0 1 2 2v.2l-10 5.9a2 2 0 0 1-2 0L2 6.2V6a2 2 0 0 1 2-2zm18 5.03V18a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V9.03l7 4.13a6 6 0 0 0 6 0l7-4.13z"/></svg>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>
