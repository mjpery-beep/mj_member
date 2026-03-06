<?php
/**
 * Template Elementor – Horaire de travail des employés.
 *
 * Affiche une grille hebdomadaire avec l'horaire de chaque animateur/coordinateur.
 *
 * @package MjMember
 */

use Mj\Member\Classes\Crud\MjMembers;
use Mj\Member\Classes\Crud\MjMemberWorkSchedules;
use Mj\Member\Classes\MjRoles;
use Mj\Member\Core\AssetsManager;
use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

$title      = isset($title) && is_string($title) ? $title : '';
$intro_text = isset($intro_text) && is_string($intro_text) ? $intro_text : '';
$showBreaks = isset($showBreaks) ? (bool) $showBreaks : true;
$showTotals = isset($showTotals) ? (bool) $showTotals : true;

AssetsManager::requirePackage('work-schedule');

$isPreview     = function_exists('is_elementor_preview') && is_elementor_preview();
$currentUserId = get_current_user_id();
$hasAccess     = true;

// Build initial data for server-side render
$schedules = array();

if ($isPreview) {
    // Mock data for Elementor preview
    $schedules = array(
        array(
            'memberId'  => 1,
            'name'      => 'Alice Dupont',
            'role'      => 'coordinateur',
            'schedule'  => array(
                array('day' => 'monday',    'start' => '09:00', 'end' => '17:00', 'break_minutes' => 60, 'note' => 'Réunion équipe le matin'),
                array('day' => 'tuesday',   'start' => '09:00', 'end' => '17:00', 'break_minutes' => 60),
                array('day' => 'wednesday', 'start' => '09:00', 'end' => '17:00', 'break_minutes' => 60),
                array('day' => 'thursday',  'start' => '09:00', 'end' => '17:00', 'break_minutes' => 60),
                array('day' => 'friday',    'start' => '09:00', 'end' => '13:00', 'break_minutes' => 0, 'note' => 'Télétravail'),
            ),
            'startDate' => '2025-01-01',
            'endDate'   => null,
        ),
        array(
            'memberId'  => 2,
            'name'      => 'Bob Martin',
            'role'      => 'animateur',
            'schedule'  => array(
                array('day' => 'monday',    'start' => '13:00', 'end' => '21:00', 'break_minutes' => 30, 'note' => 'Atelier musique'),
                array('day' => 'wednesday', 'start' => '13:00', 'end' => '21:00', 'break_minutes' => 30),
                array('day' => 'friday',    'start' => '13:00', 'end' => '21:00', 'break_minutes' => 30),
                array('day' => 'saturday',  'start' => '10:00', 'end' => '18:00', 'break_minutes' => 60, 'note' => 'Permanence week-end'),
            ),
            'startDate' => '2025-03-01',
            'endDate'   => null,
        ),
        array(
            'memberId'  => 3,
            'name'      => 'Claire Leroy',
            'role'      => 'animateur',
            'schedule'  => array(
                array('day' => 'tuesday',  'start' => '10:00', 'end' => '18:00', 'break_minutes' => 60),
                array('day' => 'thursday', 'start' => '10:00', 'end' => '18:00', 'break_minutes' => 60),
                array('day' => 'friday',   'start' => '14:00', 'end' => '20:00', 'break_minutes' => 0),
            ),
            'startDate' => '2025-02-15',
            'endDate'   => null,
        ),
    );
} elseif ($hasAccess) {
    // Live data
    $staffMembers = MjMembers::get_all(array(
        'filters' => array(
            'roles'  => array(MjRoles::ANIMATEUR, MjRoles::COORDINATEUR),
            'status' => MjMembers::STATUS_ACTIVE,
        ),
        'limit'   => 100,
    ));

    foreach ($staffMembers as $member) {
        $memberId       = (int) $member->id;
        $activeSchedule = MjMemberWorkSchedules::get_active_for_member($memberId);

        $schedule = array();
        if ($activeSchedule && !empty($activeSchedule->schedule)) {
            $decoded  = is_string($activeSchedule->schedule)
                ? json_decode($activeSchedule->schedule, true)
                : $activeSchedule->schedule;
            $schedule = is_array($decoded) ? $decoded : array();
        }

        $schedules[] = array(
            'memberId'  => $memberId,
            'name'      => trim($member->first_name . ' ' . $member->last_name),
            'role'      => $member->role,
            'schedule'  => $schedule,
            'startDate' => $activeSchedule ? $activeSchedule->start_date : null,
            'endDate'   => $activeSchedule ? $activeSchedule->end_date : null,
        );
    }
}

$config = array(
    'title'      => $title,
    'intro'      => $intro_text,
    'showBreaks' => $showBreaks,
    'showTotals' => $showTotals,
    'hasAccess'  => $hasAccess,
    'preview'    => $isPreview,
    'schedules'  => $schedules,
    'nonce'      => wp_create_nonce('mj_work_schedules'),
    'ajaxUrl'    => admin_url('admin-ajax.php'),
    'i18n'       => array(
        'title'        => __('Horaire de travail', 'mj-member'),
        'noAccess'     => __('Vous n\'avez pas accès à cette section.', 'mj-member'),
        'noSchedule'   => __('Aucun horaire défini.', 'mj-member'),
        'noEmployees'  => __('Aucun employé trouvé.', 'mj-member'),
        'break'        => __('Pause', 'mj-member'),
        'total'        => __('Total', 'mj-member'),
        'hoursShort'   => __('h', 'mj-member'),
        'minutesShort' => __('min', 'mj-member'),
        'coordinator'  => __('Coordinateur', 'mj-member'),
        'animator'     => __('Animateur', 'mj-member'),
        'off'          => __('—', 'mj-member'),
        'loading'      => __('Chargement…', 'mj-member'),
        'error'        => __('Une erreur est survenue.', 'mj-member'),
        'refresh'      => __('Actualiser', 'mj-member'),
        'days'         => array(
            __('Lundi', 'mj-member'),
            __('Mardi', 'mj-member'),
            __('Mercredi', 'mj-member'),
            __('Jeudi', 'mj-member'),
            __('Vendredi', 'mj-member'),
            __('Samedi', 'mj-member'),
            __('Dimanche', 'mj-member'),
        ),
        'daysShort'    => array(
            __('Lun', 'mj-member'),
            __('Mar', 'mj-member'),
            __('Mer', 'mj-member'),
            __('Jeu', 'mj-member'),
            __('Ven', 'mj-member'),
            __('Sam', 'mj-member'),
            __('Dim', 'mj-member'),
        ),
    ),
);

$configJson = wp_json_encode($config);
if (!is_string($configJson)) {
    $configJson = '{}';
}
?>
<?php $fallbackNotice = (!$isPreview && !$hasAccess); ?>
<div class="mj-work-schedule-widget" data-mj-work-schedule-widget data-config="<?php echo esc_attr($configJson); ?>">
    <?php if ($fallbackNotice) : ?>
        <p class="mj-work-schedule-widget__notice mj-work-schedule-widget__notice--fallback">
            <?php esc_html_e("Vous n'avez pas accès à cette section.", 'mj-member'); ?>
        </p>
    <?php endif; ?>
    <noscript>
        <p class="mj-work-schedule-widget__notice">
            <?php esc_html_e('Ce module nécessite JavaScript pour fonctionner.', 'mj-member'); ?>
        </p>
    </noscript>
</div>
