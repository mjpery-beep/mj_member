<?php

use Mj\Member\Classes\Crud\MjBadges;
use Mj\Member\Classes\Crud\MjBadgeCriteria;
use Mj\Member\Classes\Crud\MjMemberBadges;
use Mj\Member\Classes\Crud\MjMemberBadgeCriteria;
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

$headingTitle = $title !== '' ? $title : __('Mes badges', 'mj-member');

?>
<div class="mj-badges-overview" data-mj-badges-overview>
    <div class="mj-badges-overview__header">
        <div class="mj-badges-overview__heading">
            <h3 class="mj-badges-overview__title"><?php echo esc_html($headingTitle); ?></h3>
            <?php if ($subtitleHtml !== '') : ?>
                <div class="mj-badges-overview__subtitle"><?php echo $subtitleHtml; ?></div>
            <?php endif; ?>
        </div>
        <?php if ($showSummary && $totalBadges > 0) : ?>
            <div class="mj-badges-overview__stats">
                <div class="mj-badges-overview__stat mj-badges-overview__stat--complete">
                    <span class="mj-badges-overview__stat-value"><?php echo esc_html($completedCount); ?></span>
                    <span class="mj-badges-overview__stat-label"><?php echo esc_html__('Badges obtenus', 'mj-member'); ?></span>
                </div>
                <div class="mj-badges-overview__stat mj-badges-overview__stat--progress">
                    <span class="mj-badges-overview__stat-value"><?php echo esc_html($inProgressCount); ?></span>
                    <span class="mj-badges-overview__stat-label"><?php echo esc_html__('En cours', 'mj-member'); ?></span>
                </div>
                <div class="mj-badges-overview__stat mj-badges-overview__stat--locked">
                    <span class="mj-badges-overview__stat-value"><?php echo esc_html($lockedCount); ?></span>
                    <span class="mj-badges-overview__stat-label"><?php echo esc_html__('À débloquer', 'mj-member'); ?></span>
                </div>
                <?php if ($revokedCount > 0) : ?>
                    <div class="mj-badges-overview__stat mj-badges-overview__stat--revoked">
                        <span class="mj-badges-overview__stat-value"><?php echo esc_html($revokedCount); ?></span>
                        <span class="mj-badges-overview__stat-label"><?php echo esc_html__('À refaire', 'mj-member'); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if (empty($badgeEntries)) : ?>
        <p class="mj-badges-overview__empty"><?php echo esc_html__('Aucun badge n\'est disponible pour le moment.', 'mj-member'); ?></p>
    <?php else : ?>
        <ul class="mj-badges-overview__list">
            <?php foreach ($badgeEntries as $entry) :
                $state = isset($entry['state']) ? $entry['state'] : 'locked';
                $stateLabel = isset($statusLabels[$state]) ? $statusLabels[$state] : $statusLabels['locked'];
                $criteriaList = isset($entry['criteria']) && is_array($entry['criteria']) ? $entry['criteria'] : array();
                $progressPercent = isset($entry['progress_percent']) ? max(0, min(100, (int) $entry['progress_percent'])) : 0;
                $hasImage = !empty($entry['image']);
                $hasIcon = !$hasImage && !empty($entry['icon']);
                ?>
                <li class="mj-badges-overview__item mj-badges-overview__item--<?php echo esc_attr($state); ?>" data-state="<?php echo esc_attr($state); ?>">
                    <div class="mj-badges-overview__card">
                        <div class="mj-badges-overview__media">
                            <?php if ($hasImage) : ?>
                                <img src="<?php echo esc_url($entry['image']); ?>" alt="<?php echo esc_attr($entry['image_alt']); ?>" loading="lazy" />
                            <?php elseif ($hasIcon) : ?>
                                <span class="mj-badges-overview__icon <?php echo esc_attr($entry['icon']); ?>" aria-hidden="true"></span>
                            <?php else : ?>
                                <span class="mj-badges-overview__placeholder" aria-hidden="true"></span>
                            <?php endif; ?>
                        </div>
                        <div class="mj-badges-overview__content">
                            <div class="mj-badges-overview__heading-row">
                                <h4 class="mj-badges-overview__badge-title"><?php echo esc_html($entry['label']); ?></h4>
                                <span class="mj-badges-overview__state mj-badges-overview__state--<?php echo esc_attr($state); ?>"><?php echo esc_html($stateLabel); ?></span>
                            </div>
                            <?php if (!empty($entry['summary'])) : ?>
                                <p class="mj-badges-overview__summary"><?php echo esc_html($entry['summary']); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($entry['description'])) : ?>
                                <div class="mj-badges-overview__description"><?php echo wp_kses_post($entry['description']); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($criteriaList)) :
                                $awardedCount = isset($entry['awarded_count']) ? (int) $entry['awarded_count'] : 0;
                                $totalCriteria = isset($entry['total_criteria']) ? (int) $entry['total_criteria'] : count($criteriaList);
                                ?>
                                <div class="mj-badges-overview__progress" role="meter" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr($progressPercent); ?>">
                                    <div class="mj-badges-overview__progress-header">
                                        <span class="mj-badges-overview__progress-label"><?php echo esc_html(sprintf(_n('%1$d critère sur %2$d', '%1$d critères sur %2$d', $totalCriteria, 'mj-member'), $awardedCount, $totalCriteria)); ?></span>
                                        <span class="mj-badges-overview__progress-percent"><?php echo esc_html($progressPercent); ?>%</span>
                                    </div>
                                    <div class="mj-badges-overview__progress-track">
                                        <div class="mj-badges-overview__progress-bar" style="width: <?php echo esc_attr($progressPercent); ?>%;"></div>
                                    </div>
                                </div>
                                <ul class="mj-badges-overview__criteria">
                                    <?php foreach ($criteriaList as $criterion) :
                                        $criterionStatus = isset($criterion['status']) ? $criterion['status'] : 'pending';
                                        $criterionLabel = isset($criteriaStatusLabels[$criterionStatus]) ? $criteriaStatusLabels[$criterionStatus] : $criteriaStatusLabels['pending'];
                                        ?>
                                        <li class="mj-badges-overview__criterion mj-badges-overview__criterion--<?php echo esc_attr($criterionStatus); ?>">
                                            <span class="mj-badges-overview__criterion-marker" aria-hidden="true"></span>
                                            <div class="mj-badges-overview__criterion-body">
                                                <span class="mj-badges-overview__criterion-title"><?php echo esc_html($criterion['label']); ?></span>
                                                <span class="mj-badges-overview__criterion-status"><?php echo esc_html($criterionLabel); ?></span>
                                                <?php if (!empty($criterion['description'])) : ?>
                                                    <div class="mj-badges-overview__criterion-description"><?php echo wp_kses_post($criterion['description']); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                            <?php if (!empty($entry['awarded_at']) && $state === 'complete') :
                                $awardedTimestamp = strtotime($entry['awarded_at']);
                                $awardedLabel = $awardedTimestamp ? date_i18n(get_option('date_format'), $awardedTimestamp) : sanitize_text_field((string) $entry['awarded_at']);
                                ?>
                                <p class="mj-badges-overview__awarded-date"><?php echo esc_html(sprintf(__('Obtenu le %s', 'mj-member'), $awardedLabel)); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
