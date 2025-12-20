<?php

use Mj\Member\Admin\Page\HoursPage;
use Mj\Member\Core\Config;
use Mj\Member\Classes\Crud\MjEvents;
use Mj\Member\Classes\Crud\MjMembers;
use Mj\Member\Classes\Crud\MjMemberHours;
use Mj\Member\Classes\MjRoles;

if (!defined('ABSPATH')) {
    exit;
}

function mj_member_dashboard_page() {
    if (!current_user_can(Config::capability())) {
        wp_die(esc_html__('Vous n\'avez pas les droits suffisants pour accéder à ce tableau de bord.', 'mj-member'));
    }

    $stats = mj_member_get_dashboard_stats();
    $series = mj_member_get_dashboard_monthly_series();
    $member_stats = mj_member_get_member_statistics();
    $event_stats = mj_member_get_event_statistics();
    $membership_summary = mj_member_get_membership_due_summary();
    $recent_members = mj_member_get_recent_members();
    $hours_summary = mj_member_get_hours_weekly_summary();

    $timezone = wp_timezone();

    // Préparer les données pour les graphiques Chart.js
    $charts_config = array(
        'series' => $series,
        'memberStats' => array(
            'total' => isset($member_stats['total_members']) ? (string) $member_stats['total_members'] : '0',
            'roles' => array_values(array_filter(isset($member_stats['roles']) ? $member_stats['roles'] : array(), function($item) {
                return (int) $item['count'] > 0;
            })),
            'statuses' => array_values(array_filter(isset($member_stats['statuses']) ? $member_stats['statuses'] : array(), function($item) {
                return (int) $item['count'] > 0;
            })),
            'payments' => array_values(array_filter(isset($member_stats['payments']) ? $member_stats['payments'] : array(), function($item) {
                return (int) $item['count'] > 0;
            })),
            'age_brackets' => array_values(array_filter(isset($member_stats['age_brackets']) ? $member_stats['age_brackets'] : array(), function($item) {
                return (int) $item['count'] > 0;
            })),
        ),
        'eventStats' => array(
            'total_events' => isset($event_stats['total_events']) ? (int) $event_stats['total_events'] : 0,
            'registration_breakdown' => array_values(array_filter(isset($event_stats['registration_breakdown']) ? $event_stats['registration_breakdown'] : array(), function($item) {
                return (int) $item['count'] > 0;
            })),
        ),
    );

    // Enregistrer et charger le script des graphiques
    $script_url = plugins_url('js/admin-dashboard.js', dirname(__FILE__));
    $script_path = plugin_dir_path(dirname(__FILE__)) . 'js/admin-dashboard.js';
    $script_version = file_exists($script_path) ? filemtime($script_path) : MJ_MEMBER_VERSION;
    wp_enqueue_script('mj-member-admin-dashboard', $script_url, array(), $script_version, true);

    $recent_amount = number_format_i18n((float) $stats['recent_payments_total'], 2);
    $recent_payments_count = number_format_i18n((int) $stats['recent_payments_count']);
    $active_members = number_format_i18n((int) $stats['active_members']);
    $active_animateurs = number_format_i18n((int) $stats['active_animateurs']);
    $recent_registrations = number_format_i18n((int) $stats['recent_registrations']);
    $members_total = number_format_i18n((int) $member_stats['total_members']);
    $events_total = number_format_i18n((int) $event_stats['total_events']);
    $events_upcoming = number_format_i18n((int) $event_stats['upcoming_events']);
    $membership_requires_payment = number_format_i18n((int) $membership_summary['requires_payment_total']);
    $membership_missing = number_format_i18n((int) $membership_summary['missing_count']);
    $membership_expiring = number_format_i18n((int) $membership_summary['expiring_count']);
    $membership_expired = number_format_i18n((int) $membership_summary['expired_count']);
    $membership_up_to_date = number_format_i18n((int) $membership_summary['up_to_date_count']);
    $membership_upcoming_items = isset($membership_summary['upcoming']) ? $membership_summary['upcoming'] : array();
    $upcoming_events_summary = isset($event_stats['upcoming_events_summary']) ? $event_stats['upcoming_events_summary'] : array();
    $hours_summary_blocks = isset($hours_summary) ? $hours_summary : array();
    $upcoming_events_displayed = number_format_i18n(count($upcoming_events_summary));
    ?>
    <div class="wrap mj-member-dashboard">
        <h1><?php esc_html_e('Tableau de bord MJ Member', 'mj-member'); ?></h1>

        <div class="mj-dashboard-grid">
            <div class="mj-dashboard-card">
                <h3><?php esc_html_e('Membres actifs', 'mj-member'); ?></h3>
                <p class="mj-dashboard-card__metric"><?php echo esc_html($active_members); ?></p>
                <p class="mj-dashboard-card__hint"><?php esc_html_e('Total de membres ayant un statut actif.', 'mj-member'); ?></p>
            </div>
            <div class="mj-dashboard-card">
                <h3><?php esc_html_e('Animateurs actifs', 'mj-member'); ?></h3>
                <p class="mj-dashboard-card__metric"><?php echo esc_html($active_animateurs); ?></p>
                <p class="mj-dashboard-card__hint"><?php esc_html_e('Membres avec le rôle animateur et actifs.', 'mj-member'); ?></p>
            </div>
            <div class="mj-dashboard-card">
                <h3><?php esc_html_e('Paiements (30 jours)', 'mj-member'); ?></h3>
                <p class="mj-dashboard-card__metric"><?php echo esc_html($recent_payments_count); ?></p>
                <p class="mj-dashboard-card__hint"><?php printf(esc_html__('Montant encaissé : %s €', 'mj-member'), esc_html($recent_amount)); ?></p>
            </div>
            <div class="mj-dashboard-card">
                <h3><?php esc_html_e('Inscriptions (30 jours)', 'mj-member'); ?></h3>
                <p class="mj-dashboard-card__metric"><?php echo esc_html($recent_registrations); ?></p>
                <p class="mj-dashboard-card__hint"><?php esc_html_e('Inscriptions événements/stages hors annulations.', 'mj-member'); ?></p>
            </div>
        </div>

        <!-- Configuration pour les graphiques Chart.js -->
        <div id="mj-dashboard-charts-config" data-config="<?php echo esc_attr(wp_json_encode($charts_config)); ?>" style="display:none;"></div>

        <div class="mj-dashboard-split">
            <div class="mj-dashboard-panel mj-dashboard-panel--chart">
                <h2><?php esc_html_e('Inscriptions & paiements mensuels', 'mj-member'); ?></h2>
                <?php if (!empty($series)) : ?>
                    <div id="mj-dashboard-monthly-chart" class="mj-dashboard-chart-container" style="height: 280px;"></div>
                <?php else : ?>
                    <p class="mj-dashboard__empty"><?php esc_html_e('Aucune donnée disponible pour la période sélectionnée.', 'mj-member'); ?></p>
                <?php endif; ?>
            </div>
            <div class="mj-dashboard-panel mj-dashboard-panel--members">
                <h2><?php esc_html_e('Statistiques membres', 'mj-member'); ?></h2>
                <?php if ((int) $member_stats['total_members'] === 0) : ?>
                    <p class="mj-dashboard__empty"><?php esc_html_e('Aucune donnée membre disponible pour le moment.', 'mj-member'); ?></p>
                <?php else : ?>
                    <p class="mj-member-stats__summary"><?php printf(esc_html__('Total membres : %s', 'mj-member'), esc_html($members_total)); ?></p>
                    <div class="mj-member-stats mj-member-stats--charts">
                        <div class="mj-member-stats__chart-section">
                            <h3><?php esc_html_e('Répartition par rôle', 'mj-member'); ?></h3>
                            <div id="mj-dashboard-roles-chart" class="mj-dashboard-donut-container"></div>
                        </div>
                        <div class="mj-member-stats__chart-section">
                            <h3><?php esc_html_e('Statut', 'mj-member'); ?></h3>
                            <div id="mj-dashboard-statuses-chart" class="mj-dashboard-donut-container"></div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Deuxième rangée de graphiques donut -->
        <?php if ((int) $member_stats['total_members'] > 0) : ?>
        <div class="mj-dashboard-split mj-dashboard-split--charts">
            <div class="mj-dashboard-panel mj-dashboard-panel--chart-small">
                <h2><?php esc_html_e('Cotisations', 'mj-member'); ?></h2>
                <div id="mj-dashboard-payments-chart" class="mj-dashboard-donut-container"></div>
            </div>
            <div class="mj-dashboard-panel mj-dashboard-panel--chart-small">
                <h2><?php esc_html_e('Tranches d\'âge', 'mj-member'); ?></h2>
                <div id="mj-dashboard-ages-chart" class="mj-dashboard-donut-container"></div>
            </div>
        </div>
        <?php endif; ?>

        <div class="mj-dashboard-panels">
            <div class="mj-dashboard-panel mj-dashboard-panel--membership">
                <h2><?php esc_html_e('Cotisations à surveiller', 'mj-member'); ?></h2>
                <?php if ((int) $membership_summary['requires_payment_total'] === 0) : ?>
                    <p class="mj-membership-summary__empty"><?php esc_html_e('Toutes les cotisations requises sont à jour.', 'mj-member'); ?></p>
                <?php else : ?>
                    <p class="mj-membership-summary__intro"><?php printf(esc_html__('%s membre(s) nécessitent un suivi de paiement.', 'mj-member'), esc_html($membership_requires_payment)); ?></p>
                    <ul class="mj-membership-summary__metrics">
                        <li><?php printf(esc_html__('Paiement manquant : %s', 'mj-member'), esc_html($membership_missing)); ?></li>
                        <li><?php printf(esc_html__('Expire sous 30 jours : %s', 'mj-member'), esc_html($membership_expiring)); ?></li>
                        <li><?php printf(esc_html__('En retard : %s', 'mj-member'), esc_html($membership_expired)); ?></li>
                        <li><?php printf(esc_html__('À jour après contrôle : %s', 'mj-member'), esc_html($membership_up_to_date)); ?></li>
                    </ul>
                    <?php if (empty($membership_upcoming_items)) : ?>
                        <p class="mj-membership-summary__empty"><?php esc_html_e('Aucune échéance prioritaire sur les prochaines semaines.', 'mj-member'); ?></p>
                    <?php else : ?>
                        <div class="mj-membership-summary__upcoming">
                            <h3><?php esc_html_e('Échéances prioritaires', 'mj-member'); ?></h3>
                            <table class="mj-membership-summary__table">
                                <thead>
                                    <tr>
                                        <th scope="col"><?php esc_html_e('Membre', 'mj-member'); ?></th>
                                        <th scope="col"><?php esc_html_e('Statut', 'mj-member'); ?></th>
                                        <th scope="col"><?php esc_html_e('Échéance', 'mj-member'); ?></th>
                                        <th scope="col"><?php esc_html_e('Délai', 'mj-member'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($membership_upcoming_items as $membership_item) :
                                        $member_label = isset($membership_item['label']) ? $membership_item['label'] : '';
                                        $status_label = isset($membership_item['status_label']) ? $membership_item['status_label'] : '';
                                        $deadline_label = isset($membership_item['deadline']) ? $membership_item['deadline'] : '';
                                        $delay_label = isset($membership_item['delay_label']) ? $membership_item['delay_label'] : '';
                                        ?>
                                        <tr>
                                            <td><?php echo esc_html($member_label); ?></td>
                                            <td><?php echo esc_html($status_label); ?></td>
                                            <td><?php echo esc_html($deadline_label); ?></td>
                                            <td><?php echo esc_html($delay_label); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <div class="mj-dashboard-panel mj-dashboard-panel--hours">
                <h2><?php esc_html_e('Heures bénévoles (semaines récentes)', 'mj-member'); ?></h2>
                <?php if (empty($hours_summary_blocks)) : ?>
                    <p class="mj-hours-summary__empty"><?php esc_html_e('Aucune heure encodée récemment.', 'mj-member'); ?></p>
                <?php else : ?>
                    <?php foreach ($hours_summary_blocks as $hours_week) :
                        $week_label = isset($hours_week['week_label']) ? $hours_week['week_label'] : '';
                        $week_items = isset($hours_week['items']) ? $hours_week['items'] : array();
                        ?>
                        <section class="mj-hours-summary__week">
                            <h3><?php echo esc_html($week_label); ?></h3>
                            <?php if (empty($week_items)) : ?>
                                <p class="mj-hours-summary__week-empty"><?php esc_html_e('Aucune heure déclarée pour cette semaine.', 'mj-member'); ?></p>
                            <?php else : ?>
                                <table class="mj-hours-summary__table">
                                    <thead>
                                        <tr>
                                            <th><?php esc_html_e('Membre', 'mj-member'); ?></th>
                                            <th><?php esc_html_e('Durée totale', 'mj-member'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($week_items as $week_item) :
                                            $member_label = isset($week_item['member_label']) ? $week_item['member_label'] : '';
                                            $duration_label = isset($week_item['duration_human']) ? $week_item['duration_human'] : '';
                                            ?>
                                            <tr>
                                                <td><?php echo esc_html($member_label); ?></td>
                                                <td><?php echo esc_html($duration_label); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </section>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="mj-dashboard-panel mj-dashboard-panel--events-upcoming">
                <h2><?php esc_html_e('Événements à venir', 'mj-member'); ?></h2>
                <?php if (empty($upcoming_events_summary)) : ?>
                    <p class="mj-events-upcoming__empty"><?php esc_html_e('Aucun événement à venir n\'est planifié pour le moment.', 'mj-member'); ?></p>
                <?php else : ?>
                    <p class="mj-events-upcoming__intro"><?php printf(esc_html__('Prochains événements actifs : %1$s (affichage des %2$s plus proches).', 'mj-member'), esc_html($events_upcoming), esc_html($upcoming_events_displayed)); ?></p>
                    <table class="mj-events-upcoming__table">
                        <thead>
                            <tr>
                                <th scope="col"><?php esc_html_e('Événement', 'mj-member'); ?></th>
                                <th scope="col"><?php esc_html_e('Date', 'mj-member'); ?></th>
                                <th scope="col"><?php esc_html_e('Inscriptions actives', 'mj-member'); ?></th>
                                <th scope="col"><?php esc_html_e('Liste d\'attente', 'mj-member'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($upcoming_events_summary as $event_row) :
                                $event_title = isset($event_row['title']) ? $event_row['title'] : '';
                                $event_date = isset($event_row['date']) ? $event_row['date'] : '';
                                $active_count = isset($event_row['active_count']) ? (int) $event_row['active_count'] : 0;
                                $capacity_total = isset($event_row['capacity_total']) ? (int) $event_row['capacity_total'] : 0;
                                $waitlist_count = isset($event_row['waitlist_count']) ? (int) $event_row['waitlist_count'] : 0;
                                ?>
                                <tr>
                                    <td><?php echo esc_html($event_title); ?></td>
                                    <td><?php echo esc_html($event_date); ?></td>
                                    <td>
                                        <?php
                                        if ($capacity_total > 0) {
                                            printf(
                                                esc_html__('%1$s / %2$s', 'mj-member'),
                                                esc_html(number_format_i18n($active_count)),
                                                esc_html(number_format_i18n($capacity_total))
                                            );
                                        } else {
                                            echo esc_html(number_format_i18n($active_count));
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo esc_html(number_format_i18n($waitlist_count)); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <div class="mj-dashboard-panel mj-dashboard-panel--recent-members">
                <h2><?php esc_html_e('Derniers membres inscrits', 'mj-member'); ?></h2>
                <?php if (empty($recent_members)) : ?>
                    <p><?php esc_html_e('Aucun membre enregistré pour le moment.', 'mj-member'); ?></p>
                <?php else : ?>
                    <table class="mj-recent-members__table">
                        <thead>
                            <tr>
                                <th scope="col"><?php esc_html_e('Nom', 'mj-member'); ?></th>
                                <th scope="col"><?php esc_html_e('Statut', 'mj-member'); ?></th>
                                <th scope="col"><?php esc_html_e('Inscription', 'mj-member'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_members as $member_row) :
                                $name = isset($member_row['label']) ? $member_row['label'] : '';
                                $status_label = isset($member_row['status_label']) ? $member_row['status_label'] : '';
                                $status_class = isset($member_row['status']) ? $member_row['status'] : '';
                                $date_label = isset($member_row['date_display']) ? $member_row['date_display'] : '';
                                ?>
                                <tr>
                                    <td><?php echo esc_html($name); ?></td>
                                    <td><span class="mj-status-badge mj-status-badge--<?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_label); ?></span></td>
                                    <td><?php echo esc_html($date_label); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <div class="mj-dashboard-panel mj-dashboard-panel--events">
                <h2><?php esc_html_e('Statistiques événements', 'mj-member'); ?></h2>
                <?php if ((int) $event_stats['total_events'] === 0) : ?>
                    <p><?php esc_html_e('Aucun événement actif n\'est enregistré pour le moment.', 'mj-member'); ?></p>
                <?php else : ?>
                    <p class="mj-event-stats__summary"><?php printf(
                        esc_html__('Événements actifs : %1$s (dont %2$s à venir)', 'mj-member'),
                        esc_html($events_total),
                        esc_html($events_upcoming)
                    ); ?></p>
                    <div class="mj-event-stats">
                        <section class="mj-event-stats__section">
                            <h3><?php esc_html_e('Inscriptions par statut', 'mj-member'); ?></h3>
                            <ul class="mj-event-stats__list">
                                <?php foreach ($event_stats['registration_breakdown'] as $registration_entry) : ?>
                                    <li>
                                        <?php
                                        printf(
                                            esc_html__('%1$s : %2$s (%3$s%%)', 'mj-member'),
                                            esc_html($registration_entry['label']),
                                            esc_html(number_format_i18n((int) $registration_entry['count'])),
                                            esc_html(number_format_i18n((int) $registration_entry['percent']))
                                        );
                                        ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </section>
                        <section class="mj-event-stats__section">
                            <h3><?php esc_html_e('Annulations & liste d\'attente', 'mj-member'); ?></h3>
                            <ul class="mj-event-stats__list">
                                <li>
                                    <?php
                                    printf(
                                        esc_html__('Annulations enregistrées : %s', 'mj-member'),
                                        esc_html(number_format_i18n((int) $event_stats['cancelled_registrations']))
                                    );
                                    ?>
                                </li>
                                <li>
                                    <?php
                                    printf(
                                        esc_html__('Inscriptions en liste d’attente : %s', 'mj-member'),
                                        esc_html(number_format_i18n((int) $event_stats['waitlist_registrations']))
                                    );
                                    ?>
                                </li>
                                <li>
                                    <?php
                                    printf(
                                        esc_html__('Inscriptions actives : %s', 'mj-member'),
                                        esc_html(number_format_i18n((int) $event_stats['active_registrations']))
                                    );
                                    ?>
                                </li>
                            </ul>
                        </section>
                        <section class="mj-event-stats__section">
                            <h3><?php esc_html_e('Événements complets', 'mj-member'); ?></h3>
                            <?php if (empty($event_stats['full_events'])) : ?>
                                <p class="mj-event-stats__empty"><?php esc_html_e('Aucun événement n\'est complet pour l\'instant.', 'mj-member'); ?></p>
                            <?php else : ?>
                                <ul class="mj-event-stats__list">
                                    <?php foreach ($event_stats['full_events'] as $full_event) : ?>
                                        <li>
                                            <strong><?php echo esc_html($full_event['title']); ?></strong>
                                            <span class="mj-event-stats__meta">
                                                <?php
                                                printf(
                                                    esc_html__('%1$s - %2$s/%3$s inscrits', 'mj-member'),
                                                    esc_html($full_event['date']),
                                                    esc_html(number_format_i18n((int) $full_event['active_count'])),
                                                    esc_html(number_format_i18n((int) $full_event['capacity_total']))
                                                );
                                                if (!empty($full_event['waitlist_count'])) {
                                                    printf(
                                                        esc_html__(' (%s en attente)', 'mj-member'),
                                                        esc_html(number_format_i18n((int) $full_event['waitlist_count']))
                                                    );
                                                }
                                                ?>
                                            </span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </section>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
    mj_member_dashboard_styles();
}

function mj_member_get_dashboard_stats() {
    global $wpdb;

    $members_table = MjMembers::getTableName(MjMembers::TABLE_NAME);
    $payments_table = $wpdb->prefix . 'mj_payments';
    $registrations_table = mj_member_get_event_registrations_table_name();

    $cutoff_timestamp = current_time('timestamp') - (30 * DAY_IN_SECONDS);
    $cutoff_datetime = wp_date('Y-m-d H:i:s', $cutoff_timestamp, wp_timezone());

    $stats = array(
        'active_members' => 0,
        'active_animateurs' => 0,
        'recent_payments_count' => 0,
        'recent_payments_total' => 0.0,
        'recent_registrations' => 0,
    );

    $stats['active_members'] = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$members_table} WHERE status = %s",
        MjMembers::STATUS_ACTIVE
    ));

    $stats['active_animateurs'] = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$members_table} WHERE status = %s AND role = %s",
        MjMembers::STATUS_ACTIVE,
        MjRoles::ANIMATEUR
    ));

    $payments_row = $wpdb->get_row($wpdb->prepare(
        "SELECT COUNT(*) AS total, COALESCE(SUM(amount), 0) AS amount
         FROM {$payments_table}
         WHERE status = %s AND paid_at IS NOT NULL AND paid_at <> %s AND paid_at >= %s",
        'paid',
        '0000-00-00 00:00:00',
        $cutoff_datetime
    ));

    if ($payments_row) {
        $stats['recent_payments_count'] = (int) $payments_row->total;
        $stats['recent_payments_total'] = (float) $payments_row->amount;
    }

    $stats['recent_registrations'] = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$registrations_table} WHERE statut <> %s AND created_at >= %s",
        MjEventRegistrations::STATUS_CANCELLED,
        $cutoff_datetime
    ));

    return $stats;
}

function mj_member_get_dashboard_monthly_series($months = 6) {
    global $wpdb;

    $months = max(1, (int) $months);
    $timezone = wp_timezone();
    $start = new DateTimeImmutable('first day of this month', $timezone);
    $start = $start->modify('-' . ($months - 1) . ' months');

    $series = array();
    for ($index = 0; $index < $months; $index++) {
        $current = $start->modify('+' . $index . ' months');
        $key = $current->format('Y-m');
        $series[$key] = array(
            'label' => wp_date('M Y', $current->getTimestamp(), $timezone),
            'registrations' => 0,
            'payments' => 0,
            'amount' => 0.0,
        );
    }

    $min_date = $start->format('Y-m-01 00:00:00');
    $payments_table = $wpdb->prefix . 'mj_payments';
    $registrations_table = mj_member_get_event_registrations_table_name();

    $payments_rows = $wpdb->get_results($wpdb->prepare(
        "SELECT DATE_FORMAT(paid_at, '%%Y-%%m') AS ym, COUNT(*) AS total, COALESCE(SUM(amount), 0) AS amount
         FROM {$payments_table}
         WHERE status = %s AND paid_at IS NOT NULL AND paid_at <> %s AND paid_at >= %s
         GROUP BY ym",
        'paid',
        '0000-00-00 00:00:00',
        $min_date
    ));

    foreach ($payments_rows as $row) {
        $key = $row->ym;
        if (isset($series[$key])) {
            $series[$key]['payments'] = (int) $row->total;
            $series[$key]['amount'] = (float) $row->amount;
        }
    }

    $registrations_rows = $wpdb->get_results($wpdb->prepare(
        "SELECT DATE_FORMAT(created_at, '%%Y-%%m') AS ym, COUNT(*) AS total
         FROM {$registrations_table}
         WHERE statut <> %s AND created_at >= %s
         GROUP BY ym",
        MjEventRegistrations::STATUS_CANCELLED,
        $min_date
    ));

    foreach ($registrations_rows as $row) {
        $key = $row->ym;
        if (isset($series[$key])) {
            $series[$key]['registrations'] = (int) $row->total;
        }
    }

    return array_values($series);
}

function mj_member_get_member_statistics() {
    global $wpdb;

    $table = MjMembers::getTableName(MjMembers::TABLE_NAME);
    $total_members = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
    $percent_base = $total_members > 0 ? $total_members : 1;

    // Aggregate counts by role, status, payment situation, and age brackets.
    $role_labels = MjMembers::getRoleLabels();
    $roles = array();
    foreach ($role_labels as $key => $label) {
        $roles[$key] = array(
            'key' => $key,
            'label' => $label,
            'count' => 0,
            'percent' => 0,
        );
    }
    $roles['unknown'] = array(
        'key' => 'unknown',
        'label' => __('Non défini', 'mj-member'),
        'count' => 0,
        'percent' => 0,
    );

    $role_rows = $wpdb->get_results("SELECT role, COUNT(*) AS total FROM {$table} GROUP BY role");
    foreach ($role_rows as $row) {
        $role_key = (string) $row->role;
        if ($role_key === '') {
            $role_key = 'unknown';
        }
        $count = (int) $row->total;
        if (!isset($roles[$role_key])) {
            $label_fallback = sanitize_text_field($role_key);
            $roles[$role_key] = array(
                'key' => $role_key,
                'label' => $label_fallback !== '' ? ucfirst($label_fallback) : __('Non défini', 'mj-member'),
                'count' => 0,
                'percent' => 0,
            );
        }
        $roles[$role_key]['count'] = $count;
    }

    foreach ($roles as &$role) {
        $role['percent'] = ($total_members > 0) ? round(($role['count'] / $percent_base) * 100) : 0;
    }
    unset($role);

    $status_labels = array(
        MjMembers::STATUS_ACTIVE => __('Actif', 'mj-member'),
        MjMembers::STATUS_INACTIVE => __('Inactif', 'mj-member'),
        'other' => __('Autre', 'mj-member'),
    );
    $statuses = array();
    foreach ($status_labels as $key => $label) {
        $statuses[$key] = array(
            'key' => $key,
            'label' => $label,
            'count' => 0,
            'percent' => 0,
        );
    }

    $status_rows = $wpdb->get_results("SELECT status, COUNT(*) AS total FROM {$table} GROUP BY status");
    foreach ($status_rows as $row) {
        $status_key = (string) $row->status;
        if ($status_key === '') {
            $status_key = 'other';
        }
        $count = (int) $row->total;
        if (!isset($statuses[$status_key])) {
            $label_fallback = sanitize_text_field($status_key);
            $statuses[$status_key] = array(
                'key' => $status_key,
                'label' => $label_fallback !== '' ? ucfirst($label_fallback) : __('Autre', 'mj-member'),
                'count' => 0,
                'percent' => 0,
            );
        }
        $statuses[$status_key]['count'] = $count;
    }

    foreach ($statuses as &$status) {
        $status['percent'] = ($total_members > 0) ? round(($status['count'] / $percent_base) * 100) : 0;
    }
    unset($status);

    $payments = array(
        'up_to_date' => array(
            'key' => 'up_to_date',
            'label' => __('Cotisations à jour', 'mj-member'),
            'count' => 0,
            'percent' => 0,
        ),
        'due' => array(
            'key' => 'due',
            'label' => __('Cotisations à régulariser', 'mj-member'),
            'count' => 0,
            'percent' => 0,
        ),
        'exempt' => array(
            'key' => 'exempt',
            'label' => __('Membres exonérés', 'mj-member'),
            'count' => 0,
            'percent' => 0,
        ),
    );

    $payment_row = $wpdb->get_row("
        SELECT
            SUM(CASE WHEN requires_payment = 1 AND date_last_payement IS NOT NULL AND date_last_payement <> '0000-00-00 00:00:00' AND CAST(date_last_payement AS CHAR) <> '' THEN 1 ELSE 0 END) AS up_to_date,
            SUM(CASE WHEN requires_payment = 1 AND (date_last_payement IS NULL OR date_last_payement = '0000-00-00 00:00:00' OR CAST(date_last_payement AS CHAR) = '') THEN 1 ELSE 0 END) AS due,
            SUM(CASE WHEN requires_payment = 0 THEN 1 ELSE 0 END) AS exempt
        FROM {$table}
    ");
    if ($payment_row) {
        $payments['up_to_date']['count'] = (int) $payment_row->up_to_date;
        $payments['due']['count'] = (int) $payment_row->due;
        $payments['exempt']['count'] = (int) $payment_row->exempt;
    }

    foreach ($payments as &$payment) {
        $payment['percent'] = ($total_members > 0) ? round(($payment['count'] / $percent_base) * 100) : 0;
    }
    unset($payment);

    $age_brackets = array(
        'under_12' => array(
            'key' => 'under_12',
            'label' => __('Moins de 12 ans', 'mj-member'),
            'count' => 0,
            'percent' => 0,
        ),
        'age_12_17' => array(
            'key' => 'age_12_17',
            'label' => __('12 à 17 ans', 'mj-member'),
            'count' => 0,
            'percent' => 0,
        ),
        'age_18_25' => array(
            'key' => 'age_18_25',
            'label' => __('18 à 25 ans', 'mj-member'),
            'count' => 0,
            'percent' => 0,
        ),
        'age_26_35' => array(
            'key' => 'age_26_35',
            'label' => __('26 à 35 ans', 'mj-member'),
            'count' => 0,
            'percent' => 0,
        ),
        'age_36_45' => array(
            'key' => 'age_36_45',
            'label' => __('36 à 45 ans', 'mj-member'),
            'count' => 0,
            'percent' => 0,
        ),
        'age_46_plus' => array(
            'key' => 'age_46_plus',
            'label' => __('46 ans et plus', 'mj-member'),
            'count' => 0,
            'percent' => 0,
        ),
        'unknown' => array(
            'key' => 'unknown',
            'label' => __('Âge non communiqué', 'mj-member'),
            'count' => 0,
            'percent' => 0,
        ),
    );

    $age_row = $wpdb->get_row("
        SELECT
            SUM(CASE WHEN birth_date IS NULL OR birth_date = '0000-00-00' THEN 1 ELSE 0 END) AS unknown,
            SUM(CASE WHEN birth_date IS NOT NULL AND birth_date <> '0000-00-00' AND TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) < 12 THEN 1 ELSE 0 END) AS under_12,
            SUM(CASE WHEN birth_date IS NOT NULL AND birth_date <> '0000-00-00' AND TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) BETWEEN 12 AND 17 THEN 1 ELSE 0 END) AS age_12_17,
            SUM(CASE WHEN birth_date IS NOT NULL AND birth_date <> '0000-00-00' AND TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) BETWEEN 18 AND 25 THEN 1 ELSE 0 END) AS age_18_25,
            SUM(CASE WHEN birth_date IS NOT NULL AND birth_date <> '0000-00-00' AND TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) BETWEEN 26 AND 35 THEN 1 ELSE 0 END) AS age_26_35,
            SUM(CASE WHEN birth_date IS NOT NULL AND birth_date <> '0000-00-00' AND TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) BETWEEN 36 AND 45 THEN 1 ELSE 0 END) AS age_36_45,
            SUM(CASE WHEN birth_date IS NOT NULL AND birth_date <> '0000-00-00' AND TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) >= 46 THEN 1 ELSE 0 END) AS age_46_plus
        FROM {$table}
    ");
    if ($age_row) {
        foreach ($age_brackets as $key => &$entry) {
            if (property_exists($age_row, $key)) {
                $entry['count'] = (int) $age_row->{$key};
            }
            $entry['percent'] = ($total_members > 0) ? round(($entry['count'] / $percent_base) * 100) : 0;
        }
        unset($entry);
    }

    return array(
        'total_members' => $total_members,
        'roles' => array_values($roles),
        'statuses' => array_values($statuses),
        'payments' => array_values($payments),
        'age_brackets' => array_values($age_brackets),
    );
}

function mj_member_get_event_statistics() {
    global $wpdb;

    $events_table = mj_member_get_events_table_name();
    $registrations_table = mj_member_get_event_registrations_table_name();

    $timezone = wp_timezone();
    $now = current_time('timestamp');
    $today_start = wp_date('Y-m-d 00:00:00', $now, $timezone);

    $stats = array(
        'total_events' => 0,
        'upcoming_events' => 0,
        'registration_breakdown' => array(),
        'active_registrations' => 0,
        'cancelled_registrations' => 0,
        'waitlist_registrations' => 0,
        'total_registrations' => 0,
        'full_events' => array(),
        'upcoming_events_summary' => array(),
    );

    if (empty($events_table) || empty($registrations_table)) {
        return $stats;
    }

    if (class_exists('MjEvents')) {
        $stats['total_events'] = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$events_table} WHERE status = %s",
            MjEvents::STATUS_ACTIVE
        ));

        $stats['upcoming_events'] = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$events_table}
             WHERE status = %s
             AND (date_debut IS NULL OR date_debut = '0000-00-00 00:00:00' OR date_debut >= %s)",
            MjEvents::STATUS_ACTIVE,
            $today_start
        ));
    }

    $status_labels = MjEventRegistrations::get_status_labels();
    $status_totals = array(
        MjEventRegistrations::STATUS_PENDING => 0,
        MjEventRegistrations::STATUS_CONFIRMED => 0,
        MjEventRegistrations::STATUS_WAITLIST => 0,
        MjEventRegistrations::STATUS_CANCELLED => 0,
    );
    $total_registrations = 0;

    $registration_rows = $wpdb->get_results("SELECT statut, COUNT(*) AS total FROM {$registrations_table} GROUP BY statut");
    if (is_array($registration_rows)) {
        foreach ($registration_rows as $row) {
            $status_key = sanitize_key((string) $row->statut);
            $count = isset($row->total) ? (int) $row->total : 0;
            $total_registrations += $count;
            if (isset($status_totals[$status_key])) {
                $status_totals[$status_key] = $count;
            } else {
                $status_totals[$status_key] = $count;
            }
        }
    }

    $stats['total_registrations'] = $total_registrations;
    $stats['active_registrations'] = $status_totals[MjEventRegistrations::STATUS_PENDING] + $status_totals[MjEventRegistrations::STATUS_CONFIRMED];
    $stats['cancelled_registrations'] = $status_totals[MjEventRegistrations::STATUS_CANCELLED];
    $stats['waitlist_registrations'] = $status_totals[MjEventRegistrations::STATUS_WAITLIST];

    $registration_breakdown = array();
    foreach ($status_totals as $status_key => $count) {
        $label = isset($status_labels[$status_key]) ? $status_labels[$status_key] : ucfirst(str_replace('_', ' ', $status_key));
        $percent = ($total_registrations > 0) ? round(($count / $total_registrations) * 100) : 0;
        $registration_breakdown[] = array(
            'status' => $status_key,
            'label' => $label,
            'count' => $count,
            'percent' => $percent,
        );
    }
    $stats['registration_breakdown'] = $registration_breakdown;

    if (class_exists('MjEvents')) {
        $active_statuses = array(
            MjEventRegistrations::STATUS_PENDING,
            MjEventRegistrations::STATUS_CONFIRMED,
        );
        $active_placeholders = implode(',', array_fill(0, count($active_statuses), '%s'));

        $full_events_query = "
            SELECT
                e.id,
                e.title,
                e.date_debut,
                e.date_fin,
                e.capacity_total,
                COALESCE(SUM(CASE WHEN r.statut IN ($active_placeholders) THEN 1 ELSE 0 END), 0) AS active_count,
                COALESCE(SUM(CASE WHEN r.statut = %s THEN 1 ELSE 0 END), 0) AS waitlist_count
            FROM {$events_table} e
            LEFT JOIN {$registrations_table} r ON r.event_id = e.id
            WHERE e.status = %s AND e.capacity_total IS NOT NULL AND e.capacity_total > 0
            GROUP BY e.id
            HAVING active_count >= e.capacity_total
            ORDER BY e.date_debut ASC
            LIMIT 6
        ";

        $full_params = array_merge(
            $active_statuses,
            array(
                MjEventRegistrations::STATUS_WAITLIST,
                MjEvents::STATUS_ACTIVE,
            )
        );

        $prepared_query = call_user_func_array(array($wpdb, 'prepare'), array_merge(array($full_events_query), $full_params));
        $full_rows = $wpdb->get_results($prepared_query);

        if (is_array($full_rows)) {
            foreach ($full_rows as $row) {
                $title = isset($row->title) ? sanitize_text_field((string) $row->title) : '';
                $date_debut = isset($row->date_debut) ? (string) $row->date_debut : '';
                $formatted_date = '';
                if ($date_debut !== '' && $date_debut !== '0000-00-00 00:00:00') {
                    $timestamp = strtotime($date_debut);
                    if ($timestamp) {
                        $formatted_date = wp_date(get_option('date_format'), $timestamp, $timezone);
                    }
                }
                if ($formatted_date === '') {
                    $formatted_date = __('Date à confirmer', 'mj-member');
                }

                $stats['full_events'][] = array(
                    'id' => isset($row->id) ? (int) $row->id : 0,
                    'title' => $title,
                    'date' => $formatted_date,
                    'capacity_total' => isset($row->capacity_total) ? (int) $row->capacity_total : 0,
                    'active_count' => isset($row->active_count) ? (int) $row->active_count : 0,
                    'waitlist_count' => isset($row->waitlist_count) ? (int) $row->waitlist_count : 0,
                );
            }
        }

        $upcoming_query = "
            SELECT
                e.id,
                e.title,
                e.date_debut,
                e.date_fin,
                e.capacity_total,
                COALESCE(SUM(CASE WHEN r.statut IN ($active_placeholders) THEN 1 ELSE 0 END), 0) AS active_count,
                COALESCE(SUM(CASE WHEN r.statut = %s THEN 1 ELSE 0 END), 0) AS waitlist_count
            FROM {$events_table} e
            LEFT JOIN {$registrations_table} r ON r.event_id = e.id
            WHERE e.status = %s
              AND (e.date_debut IS NULL OR e.date_debut = '0000-00-00 00:00:00' OR e.date_debut >= %s)
            GROUP BY e.id
            ORDER BY
                CASE WHEN e.date_debut IS NULL OR e.date_debut = '0000-00-00 00:00:00' THEN 1 ELSE 0 END,
                e.date_debut ASC
            LIMIT 6
        ";

        $upcoming_params = array_merge(
            $active_statuses,
            array(
                MjEventRegistrations::STATUS_WAITLIST,
                MjEvents::STATUS_ACTIVE,
                $today_start,
            )
        );

        $prepared_upcoming_query = call_user_func_array(array($wpdb, 'prepare'), array_merge(array($upcoming_query), $upcoming_params));
        $upcoming_rows = $wpdb->get_results($prepared_upcoming_query);

        if (is_array($upcoming_rows)) {
            foreach ($upcoming_rows as $row) {
                $title = isset($row->title) ? sanitize_text_field((string) $row->title) : '';
                $date_debut = isset($row->date_debut) ? (string) $row->date_debut : '';
                $formatted_date = '';
                if ($date_debut !== '' && $date_debut !== '0000-00-00 00:00:00') {
                    $timestamp = strtotime($date_debut);
                    if ($timestamp) {
                        $formatted_date = wp_date(get_option('date_format'), $timestamp, $timezone);
                    }
                }
                if ($formatted_date === '') {
                    $formatted_date = __('Date à confirmer', 'mj-member');
                }

                $stats['upcoming_events_summary'][] = array(
                    'id' => isset($row->id) ? (int) $row->id : 0,
                    'title' => $title,
                    'date' => $formatted_date,
                    'capacity_total' => isset($row->capacity_total) ? (int) $row->capacity_total : 0,
                    'active_count' => isset($row->active_count) ? (int) $row->active_count : 0,
                    'waitlist_count' => isset($row->waitlist_count) ? (int) $row->waitlist_count : 0,
                );
            }
        }
    }

    return $stats;
}

function mj_member_get_hours_weekly_summary($weeks = 6, $perWeek = 5) {
    global $wpdb;

    if (!class_exists(MjMemberHours::class)) {
        return array();
    }

    $weeks = max(1, (int) $weeks);
    $perWeek = max(1, (int) $perWeek);

    $rows = MjMemberHours::get_weekly_totals(array(
        'weeks' => $weeks,
    ));

    if (empty($rows)) {
        return array();
    }

    $grouped = array();
    $memberIds = array();

    foreach ($rows as $row) {
        $weekKey = isset($row['week_key']) ? (int) $row['week_key'] : 0;
        $memberId = isset($row['member_id']) ? (int) $row['member_id'] : 0;
        $minutes = isset($row['total_minutes']) ? (int) $row['total_minutes'] : 0;

        if ($memberId <= 0 || $minutes <= 0) {
            continue;
        }

        if (!isset($grouped[$weekKey])) {
            $grouped[$weekKey] = array(
                'week_key' => $weekKey,
                'week_start' => isset($row['week_start']) ? (string) $row['week_start'] : '',
                'week_end' => isset($row['week_end']) ? (string) $row['week_end'] : '',
                'items' => array(),
            );
        }

        if (count($grouped[$weekKey]['items']) >= $perWeek) {
            continue;
        }

        $grouped[$weekKey]['items'][] = array(
            'member_id' => $memberId,
            'total_minutes' => $minutes,
        );

        $memberIds[$memberId] = true;
    }

    if (empty($grouped)) {
        return array();
    }

    $memberLabels = mj_member_load_member_labels(array_keys($memberIds));

    foreach ($grouped as $weekKey => &$weekData) {
        $range = mj_member_format_hour_week_range($weekKey, $weekData['week_start'], $weekData['week_end']);
        $weekData['week_label'] = $range['label'];

        $weekData['items'] = array_map(static function ($item) use ($memberLabels) {
            $memberId = isset($item['member_id']) ? (int) $item['member_id'] : 0;
            $minutes = isset($item['total_minutes']) ? (int) $item['total_minutes'] : 0;

            $label = isset($memberLabels[$memberId]) ? $memberLabels[$memberId] : sprintf(__('Membre #%d', 'mj-member'), $memberId);

            return array(
                'member_id' => $memberId,
                'member_label' => $label,
                'total_minutes' => $minutes,
                'duration_human' => HoursPage::formatDuration($minutes),
            );
        }, $weekData['items']);
    }
    unset($weekData);

    krsort($grouped);

    return array_values($grouped);
}

function mj_member_load_member_labels(array $memberIds) {
    global $wpdb;

    $labels = array();

    if (empty($memberIds)) {
        return $labels;
    }

    $memberIds = array_filter(array_map('intval', $memberIds));
    if (empty($memberIds)) {
        return $labels;
    }

    $placeholders = implode(',', array_fill(0, count($memberIds), '%d'));
    $table = MjMembers::getTableName(MjMembers::TABLE_NAME);
    $query = $wpdb->prepare(
        "SELECT id, first_name, last_name FROM {$table} WHERE id IN ({$placeholders})",
        ...$memberIds
    );
    $rows = $wpdb->get_results($query);

    if (!is_array($rows)) {
        return $labels;
    }

    foreach ($rows as $row) {
        $id = isset($row->id) ? (int) $row->id : 0;
        $first = isset($row->first_name) ? trim((string) $row->first_name) : '';
        $last = isset($row->last_name) ? trim((string) $row->last_name) : '';
        $label = trim($first . ' ' . $last);
        if ($label === '') {
            $label = sprintf(__('Membre #%d', 'mj-member'), $id);
        }
        $labels[$id] = $label;
    }

    return $labels;
}

function mj_member_format_hour_week_range(int $weekKey, string $fallbackStart = '', string $fallbackEnd = '') {
    $timezone = wp_timezone();
    $weekNumber = 0;
    $startDate = $fallbackStart;
    $endDate = $fallbackEnd;

    if ($weekKey > 0) {
        $isoYear = (int) floor($weekKey / 100);
        $isoWeek = $weekKey % 100;

        try {
            $start = (new \DateTimeImmutable('now', $timezone))->setISODate($isoYear, $isoWeek, 1);
            $end = (new \DateTimeImmutable('now', $timezone))->setISODate($isoYear, $isoWeek, 7);

            $startDate = $start->format('Y-m-d');
            $endDate = $end->format('Y-m-d');
            $weekNumber = (int) $start->format('W');
        } catch (\Exception $exception) {
            // Fallback to existing dates if parsing fails.
        }
    }

    if ($weekNumber === 0 && $startDate !== '') {
        $timestamp = strtotime($startDate);
        if ($timestamp !== false) {
            $weekNumber = (int) gmdate('W', $timestamp);
        }
    }

    $startLabel = HoursPage::formatDate($startDate);
    if ($startLabel === '' && $startDate !== '') {
        $startLabel = $startDate;
    }

    $endLabel = HoursPage::formatDate($endDate);
    if ($endLabel === '' && $endDate !== '') {
        $endLabel = $endDate;
    }

    $label = ($startLabel !== '' && $endLabel !== '')
        ? sprintf(__('Semaine %1$d : %2$s - %3$s', 'mj-member'), $weekNumber, $startLabel, $endLabel)
        : sprintf(__('Semaine %d', 'mj-member'), $weekNumber);

    return array(
        'label' => $label,
        'start' => $startDate,
        'end' => $endDate,
        'week_number' => $weekNumber,
    );
}

function mj_member_get_membership_due_summary() {
    global $wpdb;

    $summary = array(
        'requires_payment_total' => 0,
        'missing_count' => 0,
        'expiring_count' => 0,
        'expired_count' => 0,
        'up_to_date_count' => 0,
        'upcoming' => array(),
    );

    if (!class_exists('MjMembers')) {
        return $summary;
    }

    $members_table = MjMembers::getTableName(MjMembers::TABLE_NAME);
    if (empty($members_table)) {
        return $summary;
    }

    $expiration_days = (int) apply_filters('mj_member_payment_expiration_days', Config::paymentExpirationDays());
    $expiration_days = max(1, $expiration_days);
    $expiring_threshold = (int) apply_filters('mj_member_membership_expiring_threshold_days', 30);
    $expiring_threshold = max(0, $expiring_threshold);
    $timezone = wp_timezone();
    $now = current_time('timestamp');

    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT id, first_name, last_name, email, date_last_payement, requires_payment, status
         FROM {$members_table}
         WHERE requires_payment = 1 AND (status = %s OR status = %s OR status IS NULL)",
        MjMembers::STATUS_ACTIVE,
        ''
    ));

    if (!is_array($rows) || empty($rows)) {
        return $summary;
    }

    $summary['requires_payment_total'] = count($rows);
    $upcoming_candidates = array();

    foreach ($rows as $row) {
        $last_payment = isset($row->date_last_payement) ? (string) $row->date_last_payement : '';
        $requires_payment = !empty($row->requires_payment);

        if (!$requires_payment) {
            continue;
        }

        $first_name = isset($row->first_name) ? sanitize_text_field($row->first_name) : '';
        $last_name = isset($row->last_name) ? sanitize_text_field($row->last_name) : '';
        $label = trim($first_name . ' ' . $last_name);
        if ($label === '') {
            $email = isset($row->email) ? sanitize_email($row->email) : '';
            $label = $email !== '' ? $email : sprintf(__('Membre #%d', 'mj-member'), (int) $row->id);
        }

        if ($last_payment === '' || $last_payment === '0000-00-00 00:00:00') {
            $summary['missing_count']++;
            continue;
        }

        $last_timestamp = strtotime($last_payment);
        if (!$last_timestamp) {
            $summary['missing_count']++;
            continue;
        }

        $expiry_timestamp = $last_timestamp + ($expiration_days * DAY_IN_SECONDS);
        $days_remaining = (int) floor(($expiry_timestamp - $now) / DAY_IN_SECONDS);
        $deadline_display = wp_date(get_option('date_format', 'd/m/Y'), $expiry_timestamp, $timezone);

        if ($expiry_timestamp <= $now) {
            $summary['expired_count']++;
            $upcoming_candidates[] = array(
                'label' => $label,
                'deadline' => $deadline_display,
                'status_label' => __('Cotisation expirée', 'mj-member'),
                'delay_label' => sprintf(__('En retard de %d jours', 'mj-member'), abs($days_remaining)),
                'days' => $days_remaining,
            );
        } elseif ($days_remaining <= $expiring_threshold) {
            $summary['expiring_count']++;
            $delay_label = $days_remaining <= 0
                ? __('Expire aujourd\'hui', 'mj-member')
                : sprintf(__('Dans %d jours', 'mj-member'), $days_remaining);

            $upcoming_candidates[] = array(
                'label' => $label,
                'deadline' => $deadline_display,
                'status_label' => __('Renouvellement recommandé', 'mj-member'),
                'delay_label' => $delay_label,
                'days' => $days_remaining,
            );
        } else {
            $summary['up_to_date_count']++;
        }
    }

    if (!empty($upcoming_candidates)) {
        usort($upcoming_candidates, function ($a, $b) {
            if ($a['days'] === $b['days']) {
                return strcmp($a['label'], $b['label']);
            }
            return $a['days'] <=> $b['days'];
        });
        $summary['upcoming'] = array_slice($upcoming_candidates, 0, 6);
    }

    return $summary;
}

function mj_member_get_recent_members($limit = 5) {
    if (!class_exists('MjMembers')) {
        return array();
    }

    global $wpdb;
    $table = MjMembers::getTableName(MjMembers::TABLE_NAME);
    if (empty($table)) {
        return array();
    }

    $limit = max(1, (int) $limit);

    $sql = $wpdb->prepare(
        "SELECT id, first_name, last_name, email, status, date_inscription
         FROM {$table}
         ORDER BY date_inscription DESC, id DESC
         LIMIT %d",
        $limit
    );

    $rows = $wpdb->get_results($sql);
    if (empty($rows)) {
        return array();
    }

    $status_labels = array(
        MjMembers::STATUS_ACTIVE => __('Actif', 'mj-member'),
        MjMembers::STATUS_INACTIVE => __('Inactif', 'mj-member'),
    );

    $timezone = wp_timezone();
    $date_format = get_option('date_format', 'd/m/Y');

    $items = array();
    foreach ($rows as $row) {
        $first = isset($row->first_name) ? sanitize_text_field((string) $row->first_name) : '';
        $last = isset($row->last_name) ? sanitize_text_field((string) $row->last_name) : '';
        $email = isset($row->email) ? sanitize_email((string) $row->email) : '';
        $label = trim($first . ' ' . $last);
        if ($label === '') {
            $label = $email !== '' ? $email : sprintf(__('Membre #%d', 'mj-member'), (int) $row->id);
        }

        $status_key = isset($row->status) ? sanitize_key((string) $row->status) : '';
        $status_label = isset($status_labels[$status_key]) ? $status_labels[$status_key] : ucfirst(str_replace('_', ' ', $status_key));
        if ($status_label === '') {
            $status_label = __('Inconnu', 'mj-member');
        }

        $date_raw = isset($row->date_inscription) ? (string) $row->date_inscription : '';
        $date_display = __('Date non communiquée', 'mj-member');
        if ($date_raw !== '' && $date_raw !== '0000-00-00 00:00:00') {
            $timestamp = strtotime($date_raw);
            if ($timestamp) {
                $date_display = wp_date($date_format, $timestamp, $timezone);
            }
        }

        $items[] = array(
            'id' => isset($row->id) ? (int) $row->id : 0,
            'label' => $label,
            'status' => $status_key !== '' ? $status_key : 'unknown',
            'status_label' => $status_label,
            'date_display' => $date_display,
        );
    }

    return $items;
}

function mj_member_dashboard_styles() {
    static $printed = false;
    if ($printed) {
        return;
    }
    $printed = true;
    ?>
    <style>
        .mj-member-dashboard { max-width: 1200px; }
        .mj-dashboard-grid { display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); margin-bottom: 24px; }
        .mj-dashboard-card { background: #fff; border: 1px solid #dcdcde; border-radius: 10px; padding: 18px; box-shadow: 0 6px 18px rgba(0,0,0,0.05); }
        .mj-dashboard-card__metric { font-size: 2rem; font-weight: 700; margin: 8px 0 4px; color: #1d2327; }
        .mj-dashboard-card__hint { margin: 0; color: #50575e; font-size: 0.9rem; }
        .mj-dashboard-split { display: grid; gap: 24px; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); margin-bottom: 24px; }
        .mj-dashboard-split--charts { grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); }
        .mj-dashboard-panels { display: grid; gap: 24px; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); margin-top: 24px; }
        .mj-dashboard-panel { background: #fff; border: 1px solid #dcdcde; border-radius: 10px; padding: 20px; box-shadow: 0 6px 18px rgba(0,0,0,0.04); overflow: hidden; }
        .mj-dashboard-panel h2 { margin-top: 0; font-size: 1.15rem; }
        .mj-dashboard-panel--chart-small { min-height: 320px; }
        .mj-dashboard-panel--members { overflow: visible; }
        .mj-dashboard__empty { margin: 0; color: #50575e; }

        /* Chart.js containers */
        .mj-dashboard-chart-container { position: relative; width: 100%; min-height: 280px; }
        .mj-dashboard-donut-container { position: relative; min-height: 180px; overflow: hidden; }

        /* Donut chart styling */
        .mj-dashboard-donut { display: flex; flex-direction: column; gap: 16px; overflow: hidden; }
        .mj-dashboard-donut__canvas-wrapper { position: relative; width: 100%; height: 140px; max-width: 180px; margin: 0 auto; }
        .mj-dashboard-donut__center { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; pointer-events: none; }
        .mj-dashboard-donut__center-value { display: block; font-size: 1.25rem; font-weight: 700; color: #1d2327; line-height: 1.2; }
        .mj-dashboard-donut__center-label { display: block; font-size: 0.75rem; color: #64748b; margin-top: 2px; }
        .mj-dashboard-donut__legend { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 4px; max-height: 120px; overflow-y: auto; }
        .mj-dashboard-donut__legend-item { display: flex; align-items: center; gap: 6px; font-size: 0.8rem; color: #475569; }
        .mj-dashboard-donut__legend-swatch { width: 10px; height: 10px; border-radius: 2px; flex-shrink: 0; }
        .mj-dashboard-donut__legend-label { flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .mj-dashboard-donut__legend-value { color: #64748b; font-size: 0.75rem; white-space: nowrap; }

        /* Member stats with charts */
        .mj-member-stats--charts { display: grid; gap: 16px; }
        .mj-member-stats__chart-section { min-width: 0; overflow: hidden; }
        .mj-member-stats__chart-section h3 { margin: 0 0 10px; font-size: 0.9rem; color: #1d2327; font-weight: 600; }
        @media (min-width: 700px) {
            .mj-member-stats--charts { grid-template-columns: 1fr 1fr; }
        }

        .mj-dashboard-panel--members .mj-member-stats { display: grid; gap: 18px; }
        .mj-member-stats__summary { margin: 0 0 12px; font-weight: 600; color: #1d2327; font-size: 0.9rem; }
        .mj-member-stats__section h3 { margin: 0 0 6px; font-size: 1rem; color: #1d2327; }
        .mj-member-stats__list { margin: 0; padding-left: 1.2em; color: #2c3338; }
        .mj-member-stats__list li { margin: 0 0 4px; }
        @media (min-width: 900px) {
            .mj-dashboard-panel--members .mj-member-stats:not(.mj-member-stats--charts) { grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }
        }
        .mj-dashboard-panel--membership .mj-membership-summary__intro { margin: 0 0 12px; color: #1d2327; font-weight: 600; }
        .mj-membership-summary__metrics { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 8px 16px; margin: 0 0 18px; padding-left: 0; list-style: none; }
        .mj-membership-summary__metrics li { margin: 0; color: #2c3338; }
        .mj-membership-summary__upcoming h3 { margin: 0 0 8px; font-size: 1rem; color: #1d2327; }
        .mj-membership-summary__empty { margin: 0; color: #50575e; }
        .mj-membership-summary__table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        .mj-membership-summary__table th,
        .mj-membership-summary__table td { text-align: left; padding: 6px 0; border-bottom: 1px solid #dcdcde; }
        .mj-membership-summary__table th { font-weight: 600; color: #1d2327; }
        .mj-dashboard-panel--events-upcoming .mj-events-upcoming__intro { margin: 0 0 12px; color: #1d2327; font-weight: 600; }
        .mj-events-upcoming__empty { margin: 0; color: #50575e; }
        .mj-events-upcoming__table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        .mj-events-upcoming__table th,
        .mj-events-upcoming__table td { text-align: left; padding: 6px 0; border-bottom: 1px solid #dcdcde; }
        .mj-events-upcoming__table th { font-weight: 600; color: #1d2327; }
        .mj-dashboard-panel--hours .mj-hours-summary__week { margin: 18px 0 0; padding: 16px 0 0; border-top: 1px solid #dcdcde; }
        .mj-dashboard-panel--hours .mj-hours-summary__week:first-of-type { margin-top: 12px; border-top: none; padding-top: 0; }
        .mj-hours-summary__week h3 { margin: 0 0 8px; font-size: 1rem; color: #1d2327; }
        .mj-hours-summary__table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        .mj-hours-summary__table th,
        .mj-hours-summary__table td { text-align: left; padding: 6px 0; border-bottom: 1px solid #dcdcde; }
        .mj-hours-summary__table th { font-weight: 600; color: #1d2327; }
        .mj-hours-summary__week-empty,
        .mj-hours-summary__empty { margin: 0; color: #50575e; }
        .mj-dashboard-panel--recent-members .mj-recent-members__table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        .mj-recent-members__table th,
        .mj-recent-members__table td { text-align: left; padding: 6px 0; border-bottom: 1px solid #dcdcde; }
        .mj-recent-members__table th { font-weight: 600; color: #1d2327; }
        .mj-status-badge { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 0.8rem; line-height: 1.4; background: #f0f6ff; color: #1d2327; border: 1px solid #d0def5; }
        .mj-status-badge--active { background: #def7ec; border-color: #b7f0d7; color: #046c4e; }
        .mj-status-badge--inactive { background: #fde8e8; border-color: #f9d2d2; color: #9b1c1c; }
        .mj-status-badge--unknown { background: #f3f4f6; border-color: #e5e7eb; color: #374151; }

        /* Event stats with chart */
        .mj-event-stats--with-chart { display: grid; gap: 20px; }
        .mj-event-stats__chart-section { }
        .mj-event-stats__chart-section h3 { margin: 0 0 12px; font-size: 0.95rem; color: #1d2327; font-weight: 600; }
        @media (min-width: 600px) {
            .mj-event-stats--with-chart { grid-template-columns: 1fr 1fr; }
        }

        .mj-dashboard-panel--events .mj-event-stats:not(.mj-event-stats--with-chart) { display: grid; gap: 18px; }
        .mj-event-stats__summary { margin: 0 0 14px; font-weight: 600; color: #1d2327; }
        .mj-event-stats__section h3 { margin: 0 0 6px; font-size: 1rem; color: #1d2327; }
        .mj-event-stats__list { margin: 0; padding-left: 1.2em; color: #2c3338; }
        .mj-event-stats__list li { margin: 0 0 4px; }
        .mj-event-stats__meta { display: block; font-size: 0.85rem; color: #50575e; }
        .mj-event-stats__empty { margin: 0; color: #50575e; }
        .mj-event-stats__table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        .mj-event-stats__table th,
        .mj-event-stats__table td { text-align: left; padding: 6px 0; border-bottom: 1px solid #dcdcde; }
        .mj-event-stats__table th { font-weight: 600; color: #1d2327; }
        @media (min-width: 900px) {
            .mj-dashboard-panel--events .mj-event-stats:not(.mj-event-stats--with-chart) { grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }
        }
        @media (min-width: 1100px) {
            .mj-dashboard-panel--events-upcoming { grid-column: span 2; }
        }
        @media (max-width: 782px) {
            .mj-dashboard-chart__row { grid-template-columns: 1fr; gap: 6px; }
            .mj-dashboard-chart__counts { justify-self: flex-start; }
        }
    </style>
    <?php
}

function mj_member_register_wp_dashboard_widget() {
    if (!current_user_can(Config::capability())) {
        return;
    }

    wp_add_dashboard_widget(
        'mj_member_stats_widget',
        __('MJ Member – Statistiques rapides', 'mj-member'),
        'mj_member_render_wp_dashboard_widget'
    );
}
add_action('wp_dashboard_setup', 'mj_member_register_wp_dashboard_widget');

function mj_member_render_wp_dashboard_widget() {
    $stats = mj_member_get_dashboard_stats();
    $series = mj_member_get_dashboard_monthly_series(3);

    $recent_amount = number_format_i18n((float) $stats['recent_payments_total'], 2);
    ?>
    <ul class="mj-dashboard-widget">
        <li><strong><?php esc_html_e('Membres actifs :', 'mj-member'); ?></strong> <?php echo esc_html(number_format_i18n((int) $stats['active_members'])); ?></li>
        <li><strong><?php esc_html_e('Animateurs actifs :', 'mj-member'); ?></strong> <?php echo esc_html(number_format_i18n((int) $stats['active_animateurs'])); ?></li>
        <li><strong><?php esc_html_e('Paiements 30 derniers jours :', 'mj-member'); ?></strong> <?php echo esc_html(number_format_i18n((int) $stats['recent_payments_count'])); ?> (<?php echo esc_html($recent_amount); ?> €)</li>
        <li><strong><?php esc_html_e('Inscriptions 30 derniers jours :', 'mj-member'); ?></strong> <?php echo esc_html(number_format_i18n((int) $stats['recent_registrations'])); ?></li>
    </ul>
    <?php if (!empty($series)) : ?>
        <table class="mj-dashboard-widget__table">
            <thead>
                <tr>
                    <th scope="col"><?php esc_html_e('Mois', 'mj-member'); ?></th>
                    <th scope="col"><?php esc_html_e('Inscriptions', 'mj-member'); ?></th>
                    <th scope="col"><?php esc_html_e('Paiements', 'mj-member'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($series as $entry) : ?>
                    <tr>
                        <td><?php echo esc_html($entry['label']); ?></td>
                        <td><?php echo esc_html(number_format_i18n((int) $entry['registrations'])); ?></td>
                        <td><?php echo esc_html(number_format_i18n((int) $entry['payments'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    <style>
        .mj-dashboard-widget { margin: 0 0 12px 1.2em; }
        .mj-dashboard-widget__table { width: 100%; border-collapse: collapse; }
        .mj-dashboard-widget__table th,
        .mj-dashboard-widget__table td { text-align: left; padding: 4px 0; border-bottom: 1px solid #dcdcde; }
        .mj-dashboard-widget__table th { font-weight: 600; }
    </style>
    <?php
}
