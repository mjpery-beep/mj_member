<?php
/**
 * Template for the Job Profile widget.
 *
 * Displays the current employee's job profile card:
 * title, work regime, funding source, and rich description.
 *
 * @package MJ_Member
 */

use Mj\Member\Classes\Crud\MjMembers;
use Mj\Member\Classes\MjRoles;
use Mj\Member\Core\AssetsManager;

if (!defined('ABSPATH')) {
    exit;
}

AssetsManager::requirePackage('job-profile');

$title           = isset($title) ? (string) $title : '';
$emptyMessage    = isset($emptyMessage) ? (string) $emptyMessage : __('Aucun profil de fonction défini.', 'mj-member');
$showDescription  = isset($showDescription) ? (bool) $showDescription : true;
$showFunding      = isset($showFunding) ? (bool) $showFunding : true;
$commonProvisions = isset($commonProvisions) ? (string) $commonProvisions : '';

$isPreview     = function_exists('is_elementor_preview') && is_elementor_preview();
$currentUserId = get_current_user_id();
$currentMember = null;
$hasAccess     = false;
$jobTitle       = '';
$workRegime     = '';
$fundingSource  = '';
$jobDescription = '';

if ($isPreview) {
    $hasAccess = true;
    // Mock data for preview
    $jobTitle       = 'coordination';
    $workRegime     = 'temps-plein';
    $fundingSource  = 'Fédération Wallonie-Bruxelles';
    $jobDescription = '<p>Coordination de l\'équipe d\'animation, gestion des plannings et suivi des projets pédagogiques.</p>';
} elseif ($currentUserId > 0) {
    $currentMember = MjMembers::getByWpUserId($currentUserId);
    if ($currentMember) {
        $hasAccess     = MjRoles::isStaff($currentMember->role);

        $jobTitle       = $currentMember->get('job_title', '');
        $workRegime     = $currentMember->get('work_regime', '');
        $fundingSource  = $currentMember->get('funding_source', '');
        $jobDescription = $currentMember->get('job_description', '');
    }

    // Admin override
    if (!$hasAccess && current_user_can('manage_options')) {
        $hasAccess = true;
    }
}

// Label maps
$titleLabels = array(
    'coordination'  => __('Coordination', 'mj-member'),
    'animateur'     => __('Animateur', 'mj-member'),
    'communication' => __('Communication', 'mj-member'),
    'autre'         => __('Autre', 'mj-member'),
);

$regimeLabels = array(
    'mi-temps'          => __('Mi-temps (19h)', 'mj-member'),
    'temps-plein'       => __('Temps plein (38h)', 'mj-member'),
    'quatre-cinquieme'  => __('Quatre cinquième temps (30h30)', 'mj-member'),
);

$hasProfile = !empty($jobTitle) || !empty($workRegime) || !empty($fundingSource) || !empty($jobDescription);

// Icon map per job title
$titleIcons = array(
    'coordination'  => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
    'animateur'     => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>',
    'communication' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
    'autre'         => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>',
);

// Color map per job title
$titleColors = array(
    'coordination'  => 'coordination',
    'animateur'     => 'animateur',
    'communication' => 'communication',
    'autre'         => 'autre',
);

$titleColorClass = !empty($jobTitle) && isset($titleColors[$jobTitle])
    ? 'mj-jp__badge--' . $titleColors[$jobTitle]
    : 'mj-jp__badge--autre';
?>

<?php if (!$hasAccess) : ?>
    <?php return; ?>
<?php endif; ?>

<div class="mj-jp">

    <?php if ($title) : ?>
        <h2 class="mj-jp__heading"><?php echo esc_html($title); ?></h2>
    <?php endif; ?>

    <?php if (!$hasProfile) : ?>
        <div class="mj-jp__empty">
            <div class="mj-jp__empty-icon">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
            </div>
            <p class="mj-jp__empty-text"><?php echo esc_html($emptyMessage); ?></p>
        </div>
    <?php else : ?>

        <div class="mj-jp__card">

            <!-- Header zone with badge -->
            <div class="mj-jp__header">
                <?php if (!empty($jobTitle)) : ?>
                    <span class="mj-jp__badge <?php echo esc_attr($titleColorClass); ?>">
                        <?php echo $titleIcons[$jobTitle] ?? $titleIcons['autre']; ?>
                        <span><?php echo esc_html($titleLabels[$jobTitle] ?? ucfirst($jobTitle)); ?></span>
                    </span>
                <?php endif; ?>

                <?php if (!empty($workRegime)) : ?>
                    <span class="mj-jp__regime">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        <?php echo esc_html($regimeLabels[$workRegime] ?? ucfirst($workRegime)); ?>
                    </span>
                <?php endif; ?>

                <?php if ($showFunding && !empty($fundingSource)) : ?>
                    <span class="mj-jp__regime mj-jp__regime--funding">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                        <?php echo esc_html($fundingSource); ?>
                    </span>
                <?php endif; ?>
            </div>

            <!-- Dispositions communes (after header) -->
            <?php if (!empty($commonProvisions)) : ?>
                <div class="mj-jp__provisions">
                    <div class="mj-jp__provisions-label">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        <?php esc_html_e('Dispositions communes', 'mj-member'); ?>
                    </div>
                    <div class="mj-jp__provisions-body">
                        <?php echo wp_kses_post($commonProvisions); ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Description block -->
            <?php if ($showDescription && !empty($jobDescription)) : ?>
                <div class="mj-jp__desc">
                    <div class="mj-jp__desc-label">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                        <?php esc_html_e('Description du poste', 'mj-member'); ?>
                    </div>
                    <div class="mj-jp__desc-body">
                        <?php echo wp_kses_post($jobDescription); ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>

    <?php endif; ?>

</div>
