<?php
/**
 * Elementor template – Tableau de bord MJ (front-end).
 *
 * Renders the same dashboard data as the admin page, but inside
 * an Elementor widget using Preact + Chart.js for interactivity.
 *
 * @package mj-member
 */

use Mj\Member\Core\AssetsManager;
use Mj\Member\Core\Config;
use Mj\Member\Classes\Crud\MjDynamicFields;
use Mj\Member\Classes\Crud\MjDynamicFieldValues;

if (!defined('ABSPATH')) {
    exit;
}

$isPreview = function_exists('is_elementor_preview') && is_elementor_preview();

// Access check — gestionnaires / admins only
$hasAccess = false;
if (!$isPreview) {
    $hasAccess = current_user_can(Config::capability());
}

if (!$hasAccess && !$isPreview) {
    echo '<div class="mj-admin-dash mj-admin-dash--restricted">'
        . esc_html__('Cette section est réservée aux gestionnaires.', 'mj-member')
        . '</div>';
    return;
}

AssetsManager::requirePackage('admin-dashboard-widget');

// Read widget settings — selected dynamic field IDs
$selected_dynfield_ids = array();
if (isset($widget) && method_exists($widget, 'get_settings_for_display')) {
    $ws = $widget->get_settings_for_display();
    if (!empty($ws['dynamic_field_ids']) && is_array($ws['dynamic_field_ids'])) {
        $selected_dynfield_ids = array_map('intval', $ws['dynamic_field_ids']);
    }
}

// ── Gather data ─────────────────────────────────────────────────

if ($isPreview) {
    // Mock data for Elementor editor preview
    $dashboardData = array(
        'stats' => array(
            'total_members'          => 58,
            'active_members'         => 42,
            'active_animateurs'      => 6,
            'recent_payments_count'  => 12,
            'recent_payments_total'  => 360.00,
            'recent_registrations'   => 28,
        ),
        'series' => array(
            array('label' => 'Oct 2025', 'registrations' => 15, 'payments' => 4, 'amount' => 120.0),
            array('label' => 'Nov 2025', 'registrations' => 22, 'payments' => 8, 'amount' => 240.0),
            array('label' => 'Déc 2025', 'registrations' => 18, 'payments' => 5, 'amount' => 150.0),
            array('label' => 'Jan 2026', 'registrations' => 30, 'payments' => 10, 'amount' => 300.0),
            array('label' => 'Fév 2026', 'registrations' => 25, 'payments' => 7, 'amount' => 210.0),
            array('label' => 'Mar 2026', 'registrations' => 28, 'payments' => 12, 'amount' => 360.0),
        ),
        'memberStats' => array(
            'total' => '42',
            'roles' => array(
                array('key' => 'membre', 'label' => 'Membre', 'count' => 30, 'percent' => 71),
                array('key' => 'animateur', 'label' => 'Animateur', 'count' => 6, 'percent' => 14),
                array('key' => 'coordinateur', 'label' => 'Coordinateur', 'count' => 4, 'percent' => 10),
                array('key' => 'benevole', 'label' => 'Bénévole', 'count' => 2, 'percent' => 5),
            ),
            'statuses' => array(
                array('key' => 'active', 'label' => 'Actif', 'count' => 38, 'percent' => 90),
                array('key' => 'inactive', 'label' => 'Inactif', 'count' => 4, 'percent' => 10),
            ),
            'payments' => array(
                array('key' => 'up_to_date', 'label' => 'À jour', 'count' => 28, 'percent' => 67),
                array('key' => 'due', 'label' => 'À régulariser', 'count' => 10, 'percent' => 24),
                array('key' => 'exempt', 'label' => 'Exonérés', 'count' => 4, 'percent' => 9),
            ),
            'age_brackets' => array(
                array('key' => 'under_12', 'label' => 'Moins de 12 ans', 'count' => 5, 'percent' => 12),
                array('key' => 'age_12_17', 'label' => '12-17 ans', 'count' => 20, 'percent' => 48),
                array('key' => 'age_18_25', 'label' => '18-25 ans', 'count' => 10, 'percent' => 24),
                array('key' => 'age_26_35', 'label' => '26-35 ans', 'count' => 5, 'percent' => 12),
                array('key' => 'unknown', 'label' => 'Non communiqué', 'count' => 2, 'percent' => 4),
            ),
        ),
        'eventStats' => array(
            'total_events'           => 15,
            'upcoming_events'        => 8,
            'active_registrations'   => 65,
            'cancelled_registrations'=> 3,
            'waitlist_registrations' => 5,
            'registration_breakdown' => array(
                array('status' => 'pending', 'label' => 'En attente', 'count' => 10, 'percent' => 14),
                array('status' => 'confirmed', 'label' => 'Confirmé', 'count' => 55, 'percent' => 75),
                array('status' => 'waitlist', 'label' => 'Liste attente', 'count' => 5, 'percent' => 7),
                array('status' => 'cancelled', 'label' => 'Annulé', 'count' => 3, 'percent' => 4),
            ),
            'upcoming_events_summary' => array(
                array('title' => 'Atelier créatif', 'date' => '20/03/2026', 'active_count' => 12, 'capacity_total' => 15, 'waitlist_count' => 2),
                array('title' => 'Sortie bowling', 'date' => '25/03/2026', 'active_count' => 8, 'capacity_total' => 20, 'waitlist_count' => 0),
                array('title' => 'Tournoi FIFA', 'date' => '01/04/2026', 'active_count' => 16, 'capacity_total' => 16, 'waitlist_count' => 4),
            ),
            'full_events' => array(
                array('title' => 'Tournoi FIFA', 'date' => '01/04/2026', 'active_count' => 16, 'capacity_total' => 16, 'waitlist_count' => 4),
            ),
        ),
        'membershipSummary' => array(
            'requires_payment_total' => 10,
            'missing_count'          => 4,
            'expiring_count'         => 3,
            'expired_count'          => 3,
            'up_to_date_count'       => 0,
            'upcoming'               => array(
                array('label' => 'Jean Dupont', 'status_label' => 'Cotisation expirée', 'deadline' => '01/03/2026', 'delay_label' => 'En retard de 13 jours'),
                array('label' => 'Marie Martin', 'status_label' => 'Renouvellement recommandé', 'deadline' => '28/03/2026', 'delay_label' => 'Dans 14 jours'),
            ),
        ),
        'recentMembers' => array(
            array('label' => 'Alice Renard', 'status' => 'active', 'status_label' => 'Actif', 'date_display' => '10/03/2026'),
            array('label' => 'Bob Leroy', 'status' => 'active', 'status_label' => 'Actif', 'date_display' => '08/03/2026'),
            array('label' => 'Clara Petit', 'status' => 'inactive', 'status_label' => 'Inactif', 'date_display' => '01/03/2026'),
        ),
        'testimonialStats' => array(
            'total'    => 18,
            'pending'  => 3,
            'approved' => 14,
            'rejected' => 1,
            'recent'   => array(
                array('author' => 'Alice Renard', 'excerpt' => 'Super ambiance à la MJ, on se sent vraiment chez soi !', 'status' => 'approved', 'date' => '12/03/2026'),
                array('author' => 'Bob Leroy', 'excerpt' => 'Les activités proposées sont top, surtout les tournois FIFA.', 'status' => 'pending', 'date' => '10/03/2026'),
                array('author' => 'Clara Petit', 'excerpt' => 'Merci aux animateurs pour leur énergie !', 'status' => 'approved', 'date' => '08/03/2026'),
            ),
        ),
        'membersWithLogin' => array(
            array('label' => 'Alice Renard',  'role' => 'Animateur',     'status' => 'active', 'status_label' => 'Actif',   'last_login' => '14/03/2026 09:32', 'last_activity' => '14/03/2026 10:15'),
            array('label' => 'Bob Leroy',     'role' => 'Membre',        'status' => 'active', 'status_label' => 'Actif',   'last_login' => '13/03/2026 18:04', 'last_activity' => '13/03/2026 19:30'),
            array('label' => 'Jean Dupont',   'role' => 'Coordinateur',  'status' => 'active', 'status_label' => 'Actif',   'last_login' => '12/03/2026 14:20', 'last_activity' => '12/03/2026 15:00'),
            array('label' => 'Marie Martin',  'role' => 'Animateur',     'status' => 'active', 'status_label' => 'Actif',   'last_login' => '10/03/2026 08:45', 'last_activity' => '11/03/2026 09:00'),
            array('label' => 'Clara Petit',   'role' => 'Membre',        'status' => 'inactive', 'status_label' => 'Inactif', 'last_login' => '01/03/2026 16:10', 'last_activity' => '01/03/2026 16:10'),
        ),
        'dynamicFieldStats' => array(
            array(
                'title' => 'Sport préféré',
                'items' => array(
                    array('key' => 'football',   'label' => 'Football',   'count' => 14, 'percent' => 33),
                    array('key' => 'basketball', 'label' => 'Basketball', 'count' => 10, 'percent' => 24),
                    array('key' => 'tennis',     'label' => 'Tennis',     'count' => 8,  'percent' => 19),
                    array('key' => 'natation',   'label' => 'Natation',   'count' => 6,  'percent' => 14),
                    array('key' => 'autre',      'label' => 'Autre',      'count' => 4,  'percent' => 10),
                ),
                'total' => 42,
            ),
            array(
                'title' => 'Niveau scolaire',
                'items' => array(
                    array('key' => 'primaire',  'label' => 'Primaire',    'count' => 8,  'percent' => 19),
                    array('key' => 'secondaire','label' => 'Secondaire',  'count' => 22, 'percent' => 52),
                    array('key' => 'superieur', 'label' => 'Supérieur',   'count' => 7,  'percent' => 17),
                    array('key' => 'autre',     'label' => 'Non communiqué', 'count' => 5,  'percent' => 12),
                ),
                'total' => 42,
            ),
        ),
    );
} else {
    // Real data
    $stats              = function_exists('mj_member_get_dashboard_stats') ? mj_member_get_dashboard_stats() : array();
    $series             = function_exists('mj_member_get_dashboard_monthly_series') ? mj_member_get_dashboard_monthly_series() : array();
    $member_stats       = function_exists('mj_member_get_member_statistics') ? mj_member_get_member_statistics() : array();
    $event_stats        = function_exists('mj_member_get_event_statistics') ? mj_member_get_event_statistics() : array();
    $membership_summary = function_exists('mj_member_get_membership_due_summary') ? mj_member_get_membership_due_summary() : array();
    $recent_members     = function_exists('mj_member_get_recent_members') ? mj_member_get_recent_members() : array();

    // Testimonial stats
    $testimonial_total    = class_exists('\Mj\Member\Classes\Crud\MjTestimonials') ? \Mj\Member\Classes\Crud\MjTestimonials::count() : 0;
    $testimonial_pending  = class_exists('\Mj\Member\Classes\Crud\MjTestimonials') ? \Mj\Member\Classes\Crud\MjTestimonials::count(array('status' => 'pending')) : 0;
    $testimonial_approved = class_exists('\Mj\Member\Classes\Crud\MjTestimonials') ? \Mj\Member\Classes\Crud\MjTestimonials::count(array('status' => 'approved')) : 0;
    $testimonial_rejected = class_exists('\Mj\Member\Classes\Crud\MjTestimonials') ? \Mj\Member\Classes\Crud\MjTestimonials::count(array('status' => 'rejected')) : 0;
    $testimonial_recent   = array();
    if (class_exists('\Mj\Member\Classes\Crud\MjTestimonials')) {
        $raw_testimonials = \Mj\Member\Classes\Crud\MjTestimonials::query(array('per_page' => 5, 'orderby' => 'created_at', 'order' => 'DESC'));
        foreach ($raw_testimonials as $t) {
            $author = trim(($t->first_name ?? '') . ' ' . ($t->last_name ?? ''));
            $testimonial_recent[] = array(
                'author'  => $author ?: __('Anonyme', 'mj-member'),
                'excerpt' => wp_trim_words(wp_strip_all_tags($t->content ?? ''), 15, '…'),
                'status'  => $t->status ?? 'pending',
                'date'    => isset($t->created_at) ? date_i18n('d/m/Y', strtotime($t->created_at)) : '',
            );
        }
    }

    $members_with_login = function_exists('mj_member_get_members_with_login') ? mj_member_get_members_with_login() : array();

    $dashboardData = array(
        'stats' => $stats,
        'series' => array_values($series),
        'memberStats' => array(
            'total' => isset($member_stats['total_members']) ? (string) $member_stats['total_members'] : '0',
            'roles' => array_values(array_filter(isset($member_stats['roles']) ? $member_stats['roles'] : array(), function ($item) {
                return (int) $item['count'] > 0;
            })),
            'statuses' => array_values(array_filter(isset($member_stats['statuses']) ? $member_stats['statuses'] : array(), function ($item) {
                return (int) $item['count'] > 0;
            })),
            'payments' => array_values(array_filter(isset($member_stats['payments']) ? $member_stats['payments'] : array(), function ($item) {
                return (int) $item['count'] > 0;
            })),
            'age_brackets' => array_values(array_filter(isset($member_stats['age_brackets']) ? $member_stats['age_brackets'] : array(), function ($item) {
                return (int) $item['count'] > 0;
            })),
        ),
        'eventStats' => array(
            'total_events'            => isset($event_stats['total_events']) ? (int) $event_stats['total_events'] : 0,
            'upcoming_events'         => isset($event_stats['upcoming_events']) ? (int) $event_stats['upcoming_events'] : 0,
            'active_registrations'    => isset($event_stats['active_registrations']) ? (int) $event_stats['active_registrations'] : 0,
            'cancelled_registrations' => isset($event_stats['cancelled_registrations']) ? (int) $event_stats['cancelled_registrations'] : 0,
            'waitlist_registrations'  => isset($event_stats['waitlist_registrations']) ? (int) $event_stats['waitlist_registrations'] : 0,
            'registration_breakdown'  => array_values(array_filter(isset($event_stats['registration_breakdown']) ? $event_stats['registration_breakdown'] : array(), function ($item) {
                return (int) $item['count'] > 0;
            })),
            'upcoming_events_summary' => isset($event_stats['upcoming_events_summary']) ? $event_stats['upcoming_events_summary'] : array(),
            'full_events'             => isset($event_stats['full_events']) ? $event_stats['full_events'] : array(),
        ),
        'membershipSummary' => $membership_summary,
        'recentMembers'     => $recent_members,
        'testimonialStats'  => array(
            'total'    => $testimonial_total,
            'pending'  => $testimonial_pending,
            'approved' => $testimonial_approved,
            'rejected' => $testimonial_rejected,
            'recent'   => $testimonial_recent,
        ),
        'membersWithLogin' => $members_with_login,
        'dynamicFieldStats' => mj_member_dashboard_compute_dynfield_stats($selected_dynfield_ids),
    );
}

$configJson = wp_json_encode($dashboardData, JSON_UNESCAPED_UNICODE);
if (!is_string($configJson)) {
    $configJson = '{}';
}
?>
<div class="mj-admin-dash" data-mj-admin-dashboard data-config="<?php echo esc_attr($configJson); ?>">
    <div class="mj-admin-dash__loading">
        <?php esc_html_e('Chargement du tableau de bord…', 'mj-member'); ?>
    </div>
</div>
