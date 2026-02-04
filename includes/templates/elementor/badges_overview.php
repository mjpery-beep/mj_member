<?php

use Mj\Member\Classes\Crud\MjBadges;
use Mj\Member\Classes\Crud\MjBadgeCriteria;
use Mj\Member\Classes\Crud\MjMemberBadges;
use Mj\Member\Classes\Crud\MjMemberBadgeCriteria;
use Mj\Member\Classes\Crud\MjMemberXp;
use Mj\Member\Classes\Crud\MjTrophies;
use Mj\Member\Classes\Crud\MjMemberTrophies;
use Mj\Member\Classes\Crud\MjLevels;
use Mj\Member\Core\AssetsManager;

if (!defined('ABSPATH')) {
    exit;
}

AssetsManager::requirePackage('badges-overview');

$title = isset($title) ? (string) $title : '';
$subtitleHtml = isset($subtitle) ? wp_kses_post($subtitle) : '';
$showSummary = isset($showSummary) ? (bool) $showSummary : true;

$isPreview = function_exists('is_elementor_preview') && is_elementor_preview();
$isLoggedIn = is_user_logged_in();

$memberId = 0;
if (!$isPreview && $isLoggedIn && function_exists('mj_member_get_current_member')) {
    $member = mj_member_get_current_member();
    if (is_object($member) && isset($member->id)) {
        $memberId = (int) $member->id;
    } elseif (is_array($member) && isset($member['id'])) {
        $memberId = (int) $member['id'];
    }
}

if (!$isPreview && (!$isLoggedIn || $memberId <= 0)) {
    echo '<div class="mj-badges-overview mj-badges-overview--unauthenticated">';
    echo '<p class="mj-badges-overview__notice">' . esc_html__('Connectez-vous pour consulter votre progression de badges.', 'mj-member') . '</p>';
    echo '</div>';
    return;
}

// Récupérer les XP du membre
$memberXp = 0;
$currentLevel = null;
$nextLevel = null;
$levelTitle = '';
$levelDescription = '';
$levelImage = '';
$xpForNextLevel = 0;
$xpInCurrentLevel = 0;
$xpProgressPercent = 0;
$xpRemaining = 0;
$isMaxLevel = false;

if ($isPreview) {
    $memberXp = 450;
} elseif ($memberId > 0) {
    $memberXp = MjMemberXp::get($memberId);
}

// Utiliser MjLevels pour calculer la progression
$progression = MjLevels::get_progression($memberXp);

$currentLevel = $progression['current_level'];
$nextLevel = $progression['next_level'];
$xpProgressPercent = $progression['progress_percent'];
$xpRemaining = $progression['xp_remaining'];
$isMaxLevel = $progression['is_max_level'];

$currentLevelNumber = $currentLevel ? (int) $currentLevel['level_number'] : 1;
$levelTitle = $currentLevel ? $currentLevel['title'] : __('Débutant', 'mj-member');
$levelDescription = $currentLevel ? $currentLevel['description'] : '';
$levelImage = $currentLevel && !empty($currentLevel['image_url']) ? $currentLevel['image_url'] : '';
$xpForNextLevel = $nextLevel ? (int) $nextLevel['xp_threshold'] : ($currentLevel ? (int) $currentLevel['xp_threshold'] : 0);
$nextLevelTitle = $nextLevel ? $nextLevel['title'] : '';
$nextLevelNumber = $nextLevel ? (int) $nextLevel['level_number'] : $currentLevelNumber;

$statusLabels = array(
    'complete' => __('Badge obtenu', 'mj-member'),
    'in_progress' => __('En progression', 'mj-member'),
    'locked' => __('À débloquer', 'mj-member'),
    'revoked' => __('À refaire', 'mj-member'),
);

$criteriaStatusLabels = array(
    'awarded' => __('Critère atteint', 'mj-member'),
    'pending' => __('Critère à réaliser', 'mj-member'),
    'revoked' => __('Critère à refaire', 'mj-member'),
);

$badgeEntries = array();

if ($isPreview) {
    // Constantes XP pour l'affichage
    $xpPerCriterion = MjMemberXp::XP_PER_CRITERION;
    $xpPerBadge = MjMemberXp::XP_PER_BADGE_COMPLETION;

    $badgeEntries = array(
        array(
            'id' => 1,
            'label' => __('Explorateur MJ', 'mj-member'),
            'summary' => __('Découvrir les activités MJ et participer activement.', 'mj-member'),
            'description' => '',
            'image' => '',
            'image_alt' => '',
            'icon' => 'eicon-globe',
            'state' => 'complete',
            'progress_percent' => 100,
            'awarded_count' => 3,
            'total_criteria' => 3,
            'awarded_at' => gmdate('Y-m-d'),
            'criteria' => array(
                array('label' => __('Participer à un atelier', 'mj-member'), 'description' => '', 'status' => 'awarded'),
                array('label' => __('Inviter un ami', 'mj-member'), 'description' => '', 'status' => 'awarded'),
                array('label' => __('Partager un feedback', 'mj-member'), 'description' => '', 'status' => 'awarded'),
            ),
        ),
        array(
            'id' => 2,
            'label' => __('Ambassadeur', 'mj-member'),
            'summary' => __('Animer la communauté MJ et soutenir les nouveaux.', 'mj-member'),
            'description' => '',
            'image' => '',
            'image_alt' => '',
            'icon' => 'eicon-users',
            'state' => 'in_progress',
            'progress_percent' => 50,
            'awarded_count' => 2,
            'total_criteria' => 4,
            'awarded_at' => '',
            'criteria' => array(
                array('label' => __('Accompagner un nouvel inscrit', 'mj-member'), 'description' => '', 'status' => 'awarded'),
                array('label' => __('Publier une ressource', 'mj-member'), 'description' => '', 'status' => 'awarded'),
                array('label' => __('Organiser un mini-événement', 'mj-member'), 'description' => '', 'status' => 'pending'),
                array('label' => __('Recevoir un avis positif', 'mj-member'), 'description' => '', 'status' => 'pending'),
            ),
        ),
        array(
            'id' => 3,
            'label' => __('Créatif MJ', 'mj-member'),
            'summary' => __('Proposer des idées et expérimenter.', 'mj-member'),
            'description' => '',
            'image' => '',
            'image_alt' => '',
            'icon' => 'eicon-lightbulb',
            'state' => 'locked',
            'progress_percent' => 0,
            'awarded_count' => 0,
            'total_criteria' => 3,
            'awarded_at' => '',
            'criteria' => array(
                array('label' => __('Partager une idée', 'mj-member'), 'description' => '', 'status' => 'pending'),
                array('label' => __('Participer à un atelier créatif', 'mj-member'), 'description' => '', 'status' => 'pending'),
                array('label' => __('Publier un rendu', 'mj-member'), 'description' => '', 'status' => 'pending'),
            ),
        ),
    );
} else {
    // Constantes XP pour l'affichage
    $xpPerCriterion = MjMemberXp::XP_PER_CRITERION;
    $xpPerBadge = MjMemberXp::XP_PER_BADGE_COMPLETION;

    $badges = MjBadges::get_all(array(
        'status' => MjBadges::STATUS_ACTIVE,
        'orderby' => 'display_order',
        'order' => 'ASC',
    ));

    if (!empty($badges)) {
        $assignments = MjMemberBadges::get_for_member($memberId);
        $assignmentMap = array();
        foreach ($assignments as $assignment) {
            $badgeId = isset($assignment['badge_id']) ? (int) $assignment['badge_id'] : 0;
            if ($badgeId > 0) {
                $assignmentMap[$badgeId] = $assignment;
            }
        }

        foreach ($badges as $badge) {
            $badgeId = isset($badge['id']) ? (int) $badge['id'] : 0;
            if ($badgeId <= 0) {
                continue;
            }

            $criteriaRecords = array();
            if (!empty($badge['criteria_records']) && is_array($badge['criteria_records'])) {
                foreach ($badge['criteria_records'] as $record) {
                    if (empty($record['id'])) {
                        continue;
                    }
                    if (!empty($record['status']) && $record['status'] === MjBadgeCriteria::STATUS_ARCHIVED) {
                        continue;
                    }
                    $criteriaRecords[] = array(
                        'id' => (int) $record['id'],
                        'label' => isset($record['label']) ? (string) $record['label'] : '',
                        'description' => isset($record['description']) ? (string) $record['description'] : '',
                    );
                }
            }

            if (empty($criteriaRecords) && !empty($badge['criteria']) && is_array($badge['criteria'])) {
                foreach ($badge['criteria'] as $fallbackIndex => $fallbackLabel) {
                    $label = trim((string) $fallbackLabel);
                    if ($label === '') {
                        continue;
                    }
                    $criteriaRecords[] = array(
                        'id' => 0,
                        'label' => $label,
                        'description' => '',
                    );
                }
            }

            $awardRows = MjMemberBadgeCriteria::get_for_member_badge($memberId, $badgeId);
            $awardedMap = array();
            foreach ($awardRows as $awardRow) {
                $criterionId = isset($awardRow['criterion_id']) ? (int) $awardRow['criterion_id'] : 0;
                if ($criterionId <= 0) {
                    continue;
                }
                $awardedMap[$criterionId] = isset($awardRow['status']) ? (string) $awardRow['status'] : MjMemberBadgeCriteria::STATUS_AWARDED;
            }

            $criteriaList = array();
            $awardedCount = 0;
            $totalCriteria = count($criteriaRecords);

            foreach ($criteriaRecords as $record) {
                $criterionId = isset($record['id']) ? (int) $record['id'] : 0;
                $awardStatus = 'pending';
                if ($criterionId > 0 && isset($awardedMap[$criterionId])) {
                    $statusValue = $awardedMap[$criterionId];
                    if ($statusValue === MjMemberBadgeCriteria::STATUS_AWARDED) {
                        $awardStatus = 'awarded';
                        $awardedCount++;
                    } elseif ($statusValue === MjMemberBadgeCriteria::STATUS_REVOKED) {
                        $awardStatus = 'revoked';
                    }
                }

                $criteriaList[] = array(
                    'label' => $record['label'],
                    'description' => $record['description'],
                    'status' => $awardStatus,
                );
            }

            $assignment = $assignmentMap[$badgeId] ?? null;
            $assignmentStatus = '';
            $awardedAt = '';
            if (is_array($assignment)) {
                $assignmentStatus = isset($assignment['status']) ? (string) $assignment['status'] : '';
                $awardedAt = isset($assignment['awarded_at']) ? (string) $assignment['awarded_at'] : '';
            }

            $state = 'locked';
            $progressPercent = 0;

            if ($totalCriteria > 0) {
                if ($awardedCount >= $totalCriteria) {
                    $state = 'complete';
                    $progressPercent = 100;
                } elseif ($awardedCount > 0) {
                    $state = 'in_progress';
                    $progressPercent = (int) round(($awardedCount / $totalCriteria) * 100);
                }
            } elseif ($assignmentStatus === MjMemberBadges::STATUS_AWARDED) {
                $state = 'complete';
                $progressPercent = 100;
            }

            if ($state !== 'complete' && $assignmentStatus === MjMemberBadges::STATUS_REVOKED) {
                $state = 'revoked';
                $progressPercent = max(0, $progressPercent);
            }

            $imageUrl = '';
            $imageAlt = '';
            if (!empty($badge['image_id'])) {
                $imageData = wp_get_attachment_image_src((int) $badge['image_id'], 'medium');
                if (is_array($imageData)) {
                    $imageUrl = isset($imageData[0]) ? (string) $imageData[0] : '';
                    $imageAlt = get_post_meta((int) $badge['image_id'], '_wp_attachment_image_alt', true);
                }
            }

            $badgeEntries[] = array(
                'id' => $badgeId,
                'label' => isset($badge['label']) ? (string) $badge['label'] : '',
                'summary' => isset($badge['summary']) ? (string) $badge['summary'] : '',
                'description' => isset($badge['description']) ? (string) $badge['description'] : '',
                'image' => $imageUrl,
                'image_alt' => $imageAlt,
                'icon' => isset($badge['icon']) ? (string) $badge['icon'] : '',
                'state' => $state,
                'progress_percent' => $progressPercent,
                'awarded_count' => $awardedCount,
                'total_criteria' => $totalCriteria,
                'awarded_at' => $awardedAt,
                'criteria' => $criteriaList,
            );
        }
    }
}

$totalBadges = count($badgeEntries);
$completedCount = 0;
$inProgressCount = 0;
$revokedCount = 0;

if (!empty($badgeEntries)) {
    foreach ($badgeEntries as $entry) {
        if ($entry['state'] === 'complete') {
            $completedCount++;
        } elseif ($entry['state'] === 'in_progress') {
            $inProgressCount++;
        } elseif ($entry['state'] === 'revoked') {
            $revokedCount++;
        }
    }
}

$lockedCount = max(0, $totalBadges - $completedCount - $inProgressCount - $revokedCount);

// Récupérer les trophées
$trophyEntries = array();
$totalTrophies = 0;
$awardedTrophies = 0;

if ($isPreview) {
    $trophyEntries = array(
        array(
            'id' => 1,
            'title' => __('Première Participation', 'mj-member'),
            'description' => __('Participer à votre premier événement.', 'mj-member'),
            'xp' => 50,
            'image' => '',
            'state' => 'awarded',
            'awarded_at' => gmdate('Y-m-d', strtotime('-30 days')),
        ),
        array(
            'id' => 2,
            'title' => __('Fidèle Membre', 'mj-member'),
            'description' => __('Participer à 10 événements.', 'mj-member'),
            'xp' => 100,
            'image' => '',
            'state' => 'awarded',
            'awarded_at' => gmdate('Y-m-d', strtotime('-7 days')),
        ),
        array(
            'id' => 3,
            'title' => __('Champion MJ', 'mj-member'),
            'description' => __('Participer à 50 événements.', 'mj-member'),
            'xp' => 500,
            'image' => '',
            'state' => 'locked',
            'awarded_at' => '',
        ),
        array(
            'id' => 4,
            'title' => __('Super Animateur', 'mj-member'),
            'description' => __('Animer 5 événements avec succès.', 'mj-member'),
            'xp' => 200,
            'image' => '',
            'state' => 'locked',
            'awarded_at' => '',
        ),
        array(
            'id' => 5,
            'title' => __('Contributeur', 'mj-member'),
            'description' => __('Aider à organiser un événement.', 'mj-member'),
            'xp' => 75,
            'image' => '',
            'state' => 'awarded',
            'awarded_at' => gmdate('Y-m-d', strtotime('-14 days')),
        ),
        array(
            'id' => 6,
            'title' => __('Légende MJ', 'mj-member'),
            'description' => __('Atteindre le niveau 10.', 'mj-member'),
            'xp' => 1000,
            'image' => '',
            'state' => 'locked',
            'awarded_at' => '',
        ),
    );
    $totalTrophies = count($trophyEntries);
    $awardedTrophies = 3;
} else {
    $trophies = MjTrophies::get_all(array(
        'status' => MjTrophies::STATUS_ACTIVE,
        'orderby' => 'display_order',
        'order' => 'ASC',
    ));

    if (!empty($trophies)) {
        $memberTrophyAssignments = MjMemberTrophies::get_for_member($memberId);
        $trophyAssignmentMap = array();
        foreach ($memberTrophyAssignments as $assignment) {
            $trophyId = isset($assignment['trophy_id']) ? (int) $assignment['trophy_id'] : 0;
            if ($trophyId > 0) {
                $trophyAssignmentMap[$trophyId] = $assignment;
            }
        }

        foreach ($trophies as $trophy) {
            $trophyId = isset($trophy['id']) ? (int) $trophy['id'] : 0;
            if ($trophyId <= 0) {
                continue;
            }

            $assignment = $trophyAssignmentMap[$trophyId] ?? null;
            $state = 'locked';
            $awardedAt = '';

            if ($assignment !== null) {
                $assignmentStatus = isset($assignment['status']) ? (string) $assignment['status'] : '';
                if ($assignmentStatus === MjMemberTrophies::STATUS_AWARDED) {
                    $state = 'awarded';
                    $awardedAt = isset($assignment['awarded_at']) ? (string) $assignment['awarded_at'] : '';
                    $awardedTrophies++;
                }
            }

            $imageUrl = '';
            if (!empty($trophy['image_id'])) {
                $imageData = wp_get_attachment_image_src((int) $trophy['image_id'], 'large');
                if (is_array($imageData) && !empty($imageData[0])) {
                    $imageUrl = (string) $imageData[0];
                }
            }

            $trophyEntries[] = array(
                'id' => $trophyId,
                'title' => isset($trophy['title']) ? (string) $trophy['title'] : '',
                'description' => isset($trophy['description']) ? (string) $trophy['description'] : '',
                'xp' => isset($trophy['xp']) ? (int) $trophy['xp'] : 0,
                'image' => $imageUrl,
                'state' => $state,
                'awarded_at' => $awardedAt,
            );
        }
        $totalTrophies = count($trophyEntries);
    }
}

$headingTitle = $title !== '' ? $title : __('Mes Succès', 'mj-member');

?>
<div class="mj-badges-overview" data-mj-badges-overview>
    <?php if (empty($badgeEntries)) : ?>
        <p class="mj-badges-overview__empty"><?php echo esc_html__('Aucun badge n\'est disponible pour le moment.', 'mj-member'); ?></p>
    <?php else : ?>
        <div class="mj-badges-overview__grid">
            <!-- XP Gaming Bar as first grid item -->
            <div class="mj-badges-xp-bar" <?php if ($levelImage) : ?>style="--level-bg-image: url('<?php echo esc_url($levelImage); ?>');"<?php endif; ?>>
                <?php if ($levelImage) : ?>
                    <div class="mj-badges-xp-bar__bg-image"></div>
                <?php endif; ?>
                <div class="mj-badges-xp-bar__content">
                    <div class="mj-badges-xp-bar__level">
                        <?php if (!$levelImage) : ?>
                            <div class="mj-badges-xp-bar__level-badge">
                                <span class="mj-badges-xp-bar__level-number"><?php echo esc_html($currentLevelNumber); ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="mj-badges-xp-bar__level-info">
                            <span class="mj-badges-xp-bar__level-title"><?php echo esc_html($levelTitle); ?></span>
                            <span class="mj-badges-xp-bar__level-label"><?php echo esc_html(sprintf(__('Niveau %d', 'mj-member'), $currentLevelNumber)); ?></span>
                            <?php if (!empty($levelDescription)) : ?>
                                <p class="mj-badges-xp-bar__level-description"><?php echo esc_html($levelDescription); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                <div class="mj-badges-xp-bar__progress-wrapper">
                    <div class="mj-badges-xp-bar__xp-info">
                        <span class="mj-badges-xp-bar__xp-current">
                            <span class="mj-badges-xp-bar__xp-icon">⚡</span>
                            <span class="mj-badges-xp-bar__xp-value"><?php echo esc_html(number_format($memberXp, 0, ',', ' ')); ?></span>
                            <span class="mj-badges-xp-bar__xp-label">XP</span>
                        </span>
                        <?php if (!$isMaxLevel && $nextLevel) : ?>
                            <span class="mj-badges-xp-bar__xp-next">
                                <?php echo esc_html(sprintf(__('%s XP pour %s', 'mj-member'), number_format($xpRemaining, 0, ',', ' '), $nextLevelTitle)); ?>
                            </span>
                        <?php else : ?>
                            <span class="mj-badges-xp-bar__xp-next mj-badges-xp-bar__xp-next--max">
                                <?php echo esc_html__('Niveau maximum atteint ! 🌟', 'mj-member'); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="mj-badges-xp-bar__track">
                        <div class="mj-badges-xp-bar__fill" style="width: <?php echo esc_attr($xpProgressPercent); ?>%;">
                            <div class="mj-badges-xp-bar__glow"></div>
                        </div>
                    </div>
                    <?php if (!$isMaxLevel && $nextLevel) : ?>
                        <div class="mj-badges-xp-bar__next-level-preview">
                            <span class="mj-badges-xp-bar__next-label"><?php echo esc_html__('Prochain niveau :', 'mj-member'); ?></span>
                            <span class="mj-badges-xp-bar__next-title"><?php echo esc_html($nextLevelTitle); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="mj-badges-xp-bar__badges-count">
                    <div class="mj-badges-xp-bar__badge-stat mj-badges-xp-bar__badge-stat--complete">
                        <span class="mj-badges-xp-bar__badge-stat-icon">🏆</span>
                        <span class="mj-badges-xp-bar__badge-stat-value"><?php echo esc_html($completedCount); ?></span>
                    </div>
                    <div class="mj-badges-xp-bar__badge-stat mj-badges-xp-bar__badge-stat--progress">
                        <span class="mj-badges-xp-bar__badge-stat-icon">⏳</span>
                        <span class="mj-badges-xp-bar__badge-stat-value"><?php echo esc_html($inProgressCount); ?></span>
                    </div>
                    <div class="mj-badges-xp-bar__badge-stat mj-badges-xp-bar__badge-stat--locked">
                        <span class="mj-badges-xp-bar__badge-stat-icon">🔒</span>
                        <span class="mj-badges-xp-bar__badge-stat-value"><?php echo esc_html($lockedCount); ?></span>
                    </div>
                </div>
                </div>
            </div>

            <!-- Badges list using display:contents -->
            <ul class="mj-badges-overview__list">
                <?php foreach ($badgeEntries as $entry) :
                    $state = isset($entry['state']) ? $entry['state'] : 'locked';
                    $stateLabel = isset($statusLabels[$state]) ? $statusLabels[$state] : $statusLabels['locked'];
                    $criteriaList = isset($entry['criteria']) && is_array($entry['criteria']) ? $entry['criteria'] : array();
                    $progressPercent = isset($entry['progress_percent']) ? max(0, min(100, (int) $entry['progress_percent'])) : 0;
                    $hasImage = !empty($entry['image']);
                    $hasIcon = !$hasImage && !empty($entry['icon']);
                    $awardedCount = isset($entry['awarded_count']) ? (int) $entry['awarded_count'] : 0;
                    $totalCriteria = isset($entry['total_criteria']) ? (int) $entry['total_criteria'] : count($criteriaList);
                    // Calculer XP du badge: XP par critère * nombre critères + bonus badge complet
                    $badgeTotalXp = ($totalCriteria * $xpPerCriterion) + $xpPerBadge;
                    ?>
                    <li class="mj-badges-overview__item mj-badges-overview__item--<?php echo esc_attr($state); ?>" data-state="<?php echo esc_attr($state); ?>">
                        <div class="mj-badges-overview__card">
                            <div class="mj-badges-overview__media" style="--progress: <?php echo esc_attr($progressPercent); ?>%;">
                                <?php if ($hasImage) : ?>
                                    <img src="<?php echo esc_url($entry['image']); ?>" alt="<?php echo esc_attr($entry['image_alt']); ?>" loading="lazy" class="mj-badges-overview__media-img" />
                                    <img src="<?php echo esc_url($entry['image']); ?>" alt="" loading="lazy" class="mj-badges-overview__media-overlay" aria-hidden="true" />
                                <?php elseif ($hasIcon) : ?>
                                    <span class="mj-badges-overview__icon <?php echo esc_attr($entry['icon']); ?>" aria-hidden="true"></span>
                                <?php else : ?>
                                    <span class="mj-badges-overview__placeholder" aria-hidden="true"></span>
                                <?php endif; ?>
                            </div>
                            <div class="mj-badges-overview__content">
                                <div class="mj-badges-overview__heading-row">
                                    <span class="mj-badges-overview__state mj-badges-overview__state--<?php echo esc_attr($state); ?>"><?php echo esc_html($stateLabel); ?></span>
                                    <span class="mj-badges-overview__xp">
                                        <span class="mj-badges-overview__xp-icon">⚡</span>
                                        <span class="mj-badges-overview__xp-value"><?php echo esc_html($badgeTotalXp); ?></span>
                                        <span class="mj-badges-overview__xp-label">XP</span>
                                    </span>
                                </div>
                                <?php if ($totalCriteria > 0) : ?>
                                    <div class="mj-badges-overview__progress" role="meter" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr($progressPercent); ?>">
                                        <div class="mj-badges-overview__progress-header">
                                            <span class="mj-badges-overview__progress-label"><?php echo esc_html($awardedCount . '/' . $totalCriteria); ?></span>
                                            <span class="mj-badges-overview__progress-percent"><?php echo esc_html($progressPercent); ?>%</span>
                                        </div>
                                        <div class="mj-badges-overview__progress-track">
                                            <div class="mj-badges-overview__progress-bar" style="width: <?php echo esc_attr($progressPercent); ?>%;"></div>
                                        </div>
                                    </div>
                                    <?php if (!empty($criteriaList)) : ?>
                                        <ul class="mj-badges-overview__criteria">
                                            <?php foreach ($criteriaList as $criterion) :
                                                $criterionStatus = isset($criterion['status']) ? $criterion['status'] : 'pending';
                                                ?>
                                                <li class="mj-badges-overview__criterion mj-badges-overview__criterion--<?php echo esc_attr($criterionStatus); ?>">
                                                    <span class="mj-badges-overview__criterion-marker" aria-hidden="true"></span>
                                                    <div class="mj-badges-overview__criterion-body">
                                                        <span class="mj-badges-overview__criterion-title"><?php echo esc_html($criterion['label']); ?></span>
                                                        <span class="mj-badges-overview__criterion-xp">
                                                            <span class="mj-badges-overview__criterion-xp-icon">⚡</span>
                                                            <span class="mj-badges-overview__criterion-xp-value"><?php echo esc_html($xpPerCriterion); ?></span>
                                                        </span>
                                                    </div>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php if (!empty($entry['awarded_at']) && $state === 'complete') :
                                    $awardedTimestamp = strtotime($entry['awarded_at']);
                                    $awardedLabel = $awardedTimestamp ? date_i18n('d/m/Y', $awardedTimestamp) : sanitize_text_field((string) $entry['awarded_at']);
                                    ?>
                                    <p class="mj-badges-overview__awarded-date"><?php echo esc_html($awardedLabel); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!empty($trophyEntries)) : ?>
        <!-- Trophées Section -->
        <div class="mj-trophies-section">
            <div class="mj-trophies-section__header">
                <div class="mj-trophies-section__title-wrapper">
                    <span class="mj-trophies-section__icon">🏆</span>
                    <h3 class="mj-trophies-section__title"><?php echo esc_html__('Mes Trophées', 'mj-member'); ?></h3>
                </div>
                <div class="mj-trophies-section__counter">
                    <span class="mj-trophies-section__counter-value"><?php echo esc_html($awardedTrophies); ?></span>
                    <span class="mj-trophies-section__counter-separator">/</span>
                    <span class="mj-trophies-section__counter-total"><?php echo esc_html($totalTrophies); ?></span>
                </div>
            </div>
            <div class="mj-trophies-section__grid">
                <?php foreach ($trophyEntries as $trophy) :
                    $trophyState = isset($trophy['state']) ? $trophy['state'] : 'locked';
                    $hasImage = !empty($trophy['image']);
                    $trophyXp = isset($trophy['xp']) ? (int) $trophy['xp'] : 0;
                    ?>
                    <div class="mj-trophy mj-trophy--<?php echo esc_attr($trophyState); ?>" data-trophy-id="<?php echo esc_attr($trophy['id']); ?>">
                        <div class="mj-trophy__visual">
                            <?php if ($hasImage) : ?>
                                <img src="<?php echo esc_url($trophy['image']); ?>" alt="<?php echo esc_attr($trophy['title']); ?>" class="mj-trophy__image" loading="lazy" />
                            <?php else : ?>
                                <span class="mj-trophy__emoji">🏆</span>
                            <?php endif; ?>
                            <?php if ($trophyState === 'locked') : ?>
                                <span class="mj-trophy__lock">🔒</span>
                            <?php endif; ?>
                        </div>
                        <div class="mj-trophy__content">
                            <h4 class="mj-trophy__title"><?php echo esc_html($trophy['title']); ?></h4>
                            <?php if (!empty($trophy['description'])) : ?>
                                <p class="mj-trophy__description"><?php echo esc_html($trophy['description']); ?></p>
                            <?php endif; ?>
                            <?php if ($trophyXp > 0) : ?>
                                <span class="mj-trophy__xp">
                                    <span class="mj-trophy__xp-icon">⚡</span>
                                    <span class="mj-trophy__xp-value"><?php echo esc_html($trophyXp); ?></span>
                                    <span class="mj-trophy__xp-label">XP</span>
                                </span>
                            <?php endif; ?>
                            <?php if ($trophyState === 'awarded' && !empty($trophy['awarded_at'])) :
                                $trophyAwardedTimestamp = strtotime($trophy['awarded_at']);
                                $trophyAwardedLabel = $trophyAwardedTimestamp ? date_i18n('d/m/Y', $trophyAwardedTimestamp) : '';
                                if ($trophyAwardedLabel) : ?>
                                    <span class="mj-trophy__awarded-date"><?php echo esc_html($trophyAwardedLabel); ?></span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
