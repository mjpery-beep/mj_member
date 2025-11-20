<?php
if (!defined('ABSPATH')) {
    exit;
}

function mj_member_dashboard_page() {
    if (!current_user_can(MJ_MEMBER_CAPABILITY)) {
        wp_die(esc_html__('Vous n\'avez pas les droits suffisants pour accéder à ce tableau de bord.', 'mj-member'));
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mj_member_dashboard_nonce'])) {
        check_admin_referer('mj_member_dashboard_save', 'mj_member_dashboard_nonce');

        $about_content = '';
        if (isset($_POST['mj_dashboard_about'])) {
            $about_content = wp_kses_post(wp_unslash($_POST['mj_dashboard_about']));
        }

        update_option('mj_dashboard_about', $about_content);
        add_settings_error('mj_member_dashboard', 'mj_dashboard_saved', __('Présentation mise à jour.', 'mj-member'), 'updated');
    }

    $about_content = get_option('mj_dashboard_about', '');
    $stats = mj_member_get_dashboard_stats();
    $series = mj_member_get_dashboard_monthly_series();

    settings_errors('mj_member_dashboard');

    $timezone = wp_timezone();
    $max_value = 0;
    foreach ($series as $entry) {
        $max_value = max($max_value, (int) $entry['registrations'], (int) $entry['payments']);
    }
    if ($max_value <= 0) {
        $max_value = 1;
    }

    $recent_amount = number_format_i18n((float) $stats['recent_payments_total'], 2);
    $recent_payments_count = number_format_i18n((int) $stats['recent_payments_count']);
    $active_members = number_format_i18n((int) $stats['active_members']);
    $active_animateurs = number_format_i18n((int) $stats['active_animateurs']);
    $recent_registrations = number_format_i18n((int) $stats['recent_registrations']);
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

        <div class="mj-dashboard-split">
            <div class="mj-dashboard-panel">
                <h2><?php esc_html_e('Présentation de la MJ', 'mj-member'); ?></h2>
                <form method="post">
                    <?php wp_nonce_field('mj_member_dashboard_save', 'mj_member_dashboard_nonce'); ?>
                    <?php
                    wp_editor(
                        $about_content,
                        'mj_dashboard_about',
                        array(
                            'textarea_name' => 'mj_dashboard_about',
                            'media_buttons' => false,
                            'textarea_rows' => 8,
                            'editor_height' => 220,
                        )
                    );
                    ?>
                    <p>
                        <button type="submit" class="button button-primary"><?php esc_html_e('Enregistrer la présentation', 'mj-member'); ?></button>
                    </p>
                </form>
            </div>

            <div class="mj-dashboard-panel">
                <h2><?php esc_html_e('Inscriptions & paiements mensuels', 'mj-member'); ?></h2>
                <?php if (!empty($series)) : ?>
                    <div class="mj-dashboard-chart" role="img" aria-label="<?php esc_attr_e('Comparaison mensuelle des inscriptions et paiements', 'mj-member'); ?>">
                        <?php foreach ($series as $entry) :
                            $registration_percent = min(100, max(0, round(($entry['registrations'] / $max_value) * 100)));
                            $payment_percent = min(100, max(0, round(($entry['payments'] / $max_value) * 100)));
                            ?>
                            <div class="mj-dashboard-chart__row">
                                <div class="mj-dashboard-chart__label"><?php echo esc_html($entry['label']); ?></div>
                                <div class="mj-dashboard-chart__bars">
                                    <span class="mj-dashboard-chart__bar mj-dashboard-chart__bar--registrations" style="width: <?php echo esc_attr($registration_percent); ?>%" aria-label="<?php echo esc_attr(sprintf(__('Inscriptions : %d', 'mj-member'), (int) $entry['registrations'])); ?>"></span>
                                    <span class="mj-dashboard-chart__bar mj-dashboard-chart__bar--payments" style="width: <?php echo esc_attr($payment_percent); ?>%" aria-label="<?php echo esc_attr(sprintf(__('Paiements : %d', 'mj-member'), (int) $entry['payments'])); ?>"></span>
                                </div>
                                <div class="mj-dashboard-chart__counts">
                                    <?php
                                    printf(
                                        esc_html__('%1$s inscrits · %2$s paiements', 'mj-member'),
                                        esc_html(number_format_i18n((int) $entry['registrations'])),
                                        esc_html(number_format_i18n((int) $entry['payments']))
                                    );
                                    ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <p class="mj-dashboard-chart__legend">
                        <span class="mj-dashboard-chart__legend-item mj-dashboard-chart__legend-item--registrations"><?php esc_html_e('Inscriptions', 'mj-member'); ?></span>
                        <span class="mj-dashboard-chart__legend-item mj-dashboard-chart__legend-item--payments"><?php esc_html_e('Paiements', 'mj-member'); ?></span>
                    </p>
                <?php else : ?>
                    <p><?php esc_html_e('Aucune donnée disponible pour la période sélectionnée.', 'mj-member'); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
    mj_member_dashboard_styles();
}

function mj_member_get_dashboard_stats() {
    global $wpdb;

    $members_table = MjMembers_CRUD::getTableName(MjMembers_CRUD::TABLE_NAME);
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
        MjMembers_CRUD::STATUS_ACTIVE
    ));

    $stats['active_animateurs'] = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$members_table} WHERE status = %s AND role = %s",
        MjMembers_CRUD::STATUS_ACTIVE,
        MjMembers_CRUD::ROLE_ANIMATEUR
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

function mj_member_dashboard_styles() {
    static $printed = false;
    if ($printed) {
        return;
    }
    $printed = true;
    ?>
    <style>
        .mj-member-dashboard { max-width: 1100px; }
        .mj-dashboard-grid { display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); margin-bottom: 24px; }
        .mj-dashboard-card { background: #fff; border: 1px solid #dcdcde; border-radius: 10px; padding: 18px; box-shadow: 0 6px 18px rgba(0,0,0,0.05); }
        .mj-dashboard-card__metric { font-size: 2rem; font-weight: 700; margin: 8px 0 4px; color: #1d2327; }
        .mj-dashboard-card__hint { margin: 0; color: #50575e; font-size: 0.9rem; }
        .mj-dashboard-split { display: grid; gap: 24px; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); }
        .mj-dashboard-panel { background: #fff; border: 1px solid #dcdcde; border-radius: 10px; padding: 20px; box-shadow: 0 6px 18px rgba(0,0,0,0.04); }
        .mj-dashboard-panel h2 { margin-top: 0; }
        .mj-dashboard-chart { display: flex; flex-direction: column; gap: 12px; margin-top: 12px; }
        .mj-dashboard-chart__row { display: grid; grid-template-columns: 110px 1fr auto; gap: 12px; align-items: center; }
        .mj-dashboard-chart__label { font-weight: 600; color: #1d2327; }
        .mj-dashboard-chart__bars { display: flex; gap: 6px; align-items: center; min-height: 12px; }
        .mj-dashboard-chart__bar { display: inline-block; height: 10px; border-radius: 999px; transition: width 0.3s ease; background: rgba(37, 99, 235, 0.15); min-width: 4px; }
        .mj-dashboard-chart__bar--registrations { background: #2563eb; }
        .mj-dashboard-chart__bar--payments { background: #0ea5e9; }
        .mj-dashboard-chart__counts { font-size: 0.9rem; color: #2c3338; white-space: nowrap; }
        .mj-dashboard-chart__legend { display: flex; gap: 16px; font-size: 0.9rem; margin: 12px 0 0; }
        .mj-dashboard-chart__legend-item { display: inline-flex; align-items: center; gap: 8px; }
        .mj-dashboard-chart__legend-item::before { content: ''; width: 12px; height: 12px; border-radius: 4px; display: inline-block; }
        .mj-dashboard-chart__legend-item--registrations::before { background: #2563eb; }
        .mj-dashboard-chart__legend-item--payments::before { background: #0ea5e9; }
        @media (max-width: 782px) {
            .mj-dashboard-chart__row { grid-template-columns: 1fr; gap: 6px; }
            .mj-dashboard-chart__counts { justify-self: flex-start; }
        }
    </style>
    <?php
}

function mj_member_register_wp_dashboard_widget() {
    if (!current_user_can(MJ_MEMBER_CAPABILITY)) {
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
