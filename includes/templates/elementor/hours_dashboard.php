<?php

use Mj\Member\Admin\Page\HoursPage;
use Mj\Member\Classes\Crud\MjMembers;
use Mj\Member\Classes\MjRoles;
use Mj\Member\Core\AssetsManager;
use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

$isPreview = function_exists('is_elementor_preview') && is_elementor_preview();

// Resolve member and check coordinator role
$hasAccess = false;
$memberRole = '';

if (!$isPreview) {
    $currentUserId = get_current_user_id();
    if ($currentUserId > 0) {
        $memberObj = MjMembers::getByWpUserId($currentUserId);
        if ($memberObj && !empty($memberObj->role)) {
            $memberRole = (string) $memberObj->role;
            $hasAccess = MjRoles::isCoordinateur($memberRole)
                || current_user_can('manage_options');
        }
    }
}

if (!$hasAccess && !$isPreview) {
    echo '<div class="mj-hours-dashboard-widget mj-hours-dashboard-widget--restricted">'
        . esc_html__("Cette section est réservée aux coordinateurs.", 'mj-member')
        . '</div>';
    return;
}

AssetsManager::requirePackage('hours-dashboard');

$showEditTab = true;
if (isset($widget) && method_exists($widget, 'get_settings_for_display')) {
    $widgetSettings = $widget->get_settings_for_display();
    $showEditTab = !empty($widgetSettings['show_edit_tab']) && $widgetSettings['show_edit_tab'] === 'yes';
}

// Build the dashboard config using the same logic as the admin page
$config = array();

if ($isPreview) {
    // Mock data for Elementor preview
    $config = array(
        'data' => array(
            'projects' => array(
                array('key' => 'animation', 'label' => 'Animation', 'minutes' => 2400, 'human' => '40 heures', 'entries' => 30, 'is_unassigned' => false, 'color' => ''),
                array('key' => 'coordination', 'label' => 'Coordination', 'minutes' => 1200, 'human' => '20 heures', 'entries' => 15, 'is_unassigned' => false, 'color' => ''),
                array('key' => '__empty__', 'label' => 'Sans projet', 'minutes' => 600, 'human' => '10 heures', 'entries' => 8, 'is_unassigned' => true, 'color' => ''),
            ),
            'members' => array(
                array(
                    'id' => 1,
                    'label' => 'Marie Dupont',
                    'minutes' => 2100,
                    'human' => '35 heures',
                    'entries' => 28,
                    'projects' => array(
                        array('key' => 'animation', 'label' => 'Animation', 'minutes' => 1500, 'human' => '25 heures', 'entries' => 20, 'is_unassigned' => false, 'color' => ''),
                        array('key' => 'coordination', 'label' => 'Coordination', 'minutes' => 600, 'human' => '10 heures', 'entries' => 8, 'is_unassigned' => false, 'color' => ''),
                    ),
                    'weekly_contract_minutes' => 2280,
                    'weekly_contract_human' => '38 heures',
                    'work_schedule' => array(),
                    'cumulative_balance' => null,
                    'weekly_balance_minutes' => 120,
                    'weekly_balance_human' => '+2 heures',
                ),
                array(
                    'id' => 2,
                    'label' => 'Jean Martin',
                    'minutes' => 2100,
                    'human' => '35 heures',
                    'entries' => 25,
                    'projects' => array(
                        array('key' => 'animation', 'label' => 'Animation', 'minutes' => 900, 'human' => '15 heures', 'entries' => 10, 'is_unassigned' => false, 'color' => ''),
                        array('key' => 'coordination', 'label' => 'Coordination', 'minutes' => 600, 'human' => '10 heures', 'entries' => 7, 'is_unassigned' => false, 'color' => ''),
                        array('key' => '__empty__', 'label' => 'Sans projet', 'minutes' => 600, 'human' => '10 heures', 'entries' => 8, 'is_unassigned' => true, 'color' => ''),
                    ),
                    'weekly_contract_minutes' => 2280,
                    'weekly_contract_human' => '38 heures',
                    'work_schedule' => array(),
                    'cumulative_balance' => null,
                    'weekly_balance_minutes' => -60,
                    'weekly_balance_human' => '-1 heure',
                ),
            ),
            'totals' => array(
                'minutes' => 4200,
                'human' => '70 heures',
                'entries' => 53,
                'member_count' => 2,
                'project_count' => 3,
                'unassigned_minutes' => 600,
                'unassigned_human' => '10 heures',
                'weekly_average_minutes' => 2100,
                'weekly_average_human' => '35 heures',
                'weekly_average_weeks' => 2,
                'weekly_average_meta' => 'Moyenne calculée sur 2 semaines récentes',
                'weekly_contract_minutes' => 4560,
                'weekly_contract_human' => '76 heures',
                'weekly_extra_recent_minutes' => 0,
                'weekly_extra_recent_human' => '0 min',
                'weekly_balance_minutes' => 60,
                'weekly_balance_human' => '+1 heure',
            ),
            'timeseries' => array(
                'months' => array(
                    array('key' => '2026-01-01', 'label' => 'Janvier 2026', 'short_label' => 'Jan', 'minutes' => 9600, 'human' => '160 heures', 'entries' => 80),
                    array('key' => '2026-02-01', 'label' => 'Février 2026', 'short_label' => 'Fév', 'minutes' => 8400, 'human' => '140 heures', 'entries' => 70),
                    array('key' => '2026-03-01', 'label' => 'Mars 2026', 'short_label' => 'Mar', 'minutes' => 4200, 'human' => '70 heures', 'entries' => 53),
                ),
                'weeks' => array(
                    array('key' => '2026-W10', 'label' => 'Semaine 10', 'short_label' => '10', 'minutes' => 2100, 'human' => '35 heures', 'entries' => 26,
                        'expected_minutes' => 4560, 'required_minutes' => 2100, 'extra_minutes' => 0, 'deficit_minutes' => 2460, 'difference_minutes' => -2460),
                    array('key' => '2026-W11', 'label' => 'Semaine 11', 'short_label' => '11', 'minutes' => 2100, 'human' => '35 heures', 'entries' => 27,
                        'expected_minutes' => 4560, 'required_minutes' => 2100, 'extra_minutes' => 0, 'deficit_minutes' => 2460, 'difference_minutes' => -2460),
                ),
                'months_by_member' => array(),
                'weeks_by_member' => array(),
            ),
            'generated_at_display' => date_i18n(get_option('date_format') . ' ' . get_option('time_format')),
        ),
        'i18n' => array(
            'pageTitle' => __('Tableau de bord des heures', 'mj-member'),
            'updatedAtLabel' => __('Mis à jour le %s', 'mj-member'),
            'totalHours' => __('Heures totales encodées', 'mj-member'),
            'membersCount' => __('Membres', 'mj-member'),
            'projectsCount' => __('Projets', 'mj-member'),
            'unassignedHours' => __('Heures sans projet', 'mj-member'),
            'entriesLabel' => __('encodages', 'mj-member'),
            'projectsDonutTitle' => __('Répartition des heures par projet', 'mj-member'),
            'memberDonutTitle' => __('Répartition des projets du membre', 'mj-member'),
            'memberSelectLabel' => __('Sélectionnez un membre', 'mj-member'),
            'memberSelectHelper' => __('Le membre sélectionné met à jour tous les graphiques ci-dessous.', 'mj-member'),
            'memberTableTitle' => __('Heures par membre', 'mj-member'),
            'memberColumn' => __('Membre', 'mj-member'),
            'hoursColumn' => __('Heures', 'mj-member'),
            'entriesColumn' => __('Encodages', 'mj-member'),
            'rateColumn' => __('Taux', 'mj-member'),
            'noMemberData' => __('Aucun encodage enregistré pour l\'instant.', 'mj-member'),
            'noProjectsForMember' => __('Aucun projet encodé pour ce membre.', 'mj-member'),
            'monthlyHoursTitle' => __('Heures encodées par mois', 'mj-member'),
            'weeklyHoursTitle' => __('Heures encodées par semaine', 'mj-member'),
            'averageWeeklyHours' => __('Moyenne hebdomadaire encodée', 'mj-member'),
            'weeklyRequiredLabel' => __('Heures dues', 'mj-member'),
            'weeklyExtraLabel' => __('Heures supplémentaires', 'mj-member'),
            'weeklyExpectedLabel' => __('Heures attendues', 'mj-member'),
            'weeklyDeficitLabel' => __('Heures manquantes', 'mj-member'),
            'weeklyBalanceNetLabel' => __('Solde cumulé', 'mj-member'),
            'barChartEmpty' => __('Aucune donnée disponible pour cette période.', 'mj-member'),
            'renderError' => __('Impossible d\'afficher le tableau de bord pour le moment. Merci de rafraîchir la page.', 'mj-member'),
            'graphsTabLabel' => __('Graphiques', 'mj-member'),
            'editTabLabel' => __('Éditer les heures', 'mj-member'),
            'projectWithoutLabel' => __('Sans projet', 'mj-member'),
        ),
        'showEditTab' => $showEditTab,
    );
} else {
    // Real data – reuse HoursPage's preparation logic
    $config = HoursPage::prepareFrontDashboardConfig($showEditTab);
}

$configJson = wp_json_encode($config);
if (!is_string($configJson)) {
    $configJson = '{}';
}

$updatedAtDisplay = isset($config['data']['generated_at_display']) ? (string) $config['data']['generated_at_display'] : '';

?>
<div class="mj-hours-dashboard-widget">
    <?php if ($updatedAtDisplay !== '') : ?>
        <p class="mj-hours-dashboard-widget__updated">
            <?php echo esc_html(sprintf(
                __('Mis à jour le %s', 'mj-member'),
                $updatedAtDisplay
            )); ?>
        </p>
    <?php endif; ?>

    <div class="mj-hours-dashboard-front" data-mj-hours-dashboard-front data-config="<?php echo esc_attr($configJson); ?>"></div>
</div>
