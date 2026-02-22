<?php

use Mj\Member\Core\Config;
use Mj\Member\Classes\MjRoles;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function mj_check_and_add_columns() {
    mj_member_run_schema_upgrade();
    mj_member_ensure_auxiliary_tables();
}

function mj_member_table_exists($table_name) {
    if (empty($table_name)) {
        return false;
    }

    global $wpdb;
    $result = $wpdb->get_var($wpdb->prepare(
        'SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s',
        DB_NAME,
        $table_name
    ));

    return $result === $table_name;
}

function mj_member_convert_table_to_utf8mb4($table_name) {
    if (empty($table_name) || !mj_member_table_exists($table_name)) {
        return;
    }

    global $wpdb;

    if (!method_exists($wpdb, 'has_cap') || !$wpdb->has_cap('utf8mb4')) {
        return;
    }

    $current_collation = $wpdb->get_var(
        $wpdb->prepare(
            'SELECT TABLE_COLLATION FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s',
            DB_NAME,
            $table_name
        )
    );

    if (is_string($current_collation) && stripos($current_collation, 'utf8mb4') !== false) {
        return;
    }

    $charset = 'utf8mb4';
    $collate = $wpdb->collate;
    if (!is_string($collate) || stripos($collate, 'utf8mb4') === false) {
        $collate = 'utf8mb4_unicode_ci';
    }

    $charset_sql = esc_sql($charset);
    $collate_sql = esc_sql($collate);

    $wpdb->query("ALTER TABLE {$table_name} CONVERT TO CHARACTER SET {$charset_sql} COLLATE {$collate_sql}");
}

function mj_member_get_events_table_name() {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    global $wpdb;
    $candidates = array(
        $wpdb->prefix . 'mj_events',
        $wpdb->prefix . 'events',
    );

    foreach ($candidates as $candidate) {
        if (mj_member_table_exists($candidate)) {
            $cached = $candidate;
            return $cached;
        }
    }

    $cached = $candidates[0];
    return $cached;
}

function mj_member_get_event_registrations_table_name() {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    global $wpdb;
    $candidates = array(
        $wpdb->prefix . 'mj_event_registrations',
        $wpdb->prefix . 'event_registrations',
    );

    foreach ($candidates as $candidate) {
        if (mj_member_table_exists($candidate)) {
            $cached = $candidate;
            return $cached;
        }
    }

    $cached = $candidates[0];
    return $cached;
}

function mj_member_get_event_date_occurrences_table_name() {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    global $wpdb;
    $candidates = array(
        $wpdb->prefix . 'mj_event_date_occurrences',
        $wpdb->prefix . 'event_date_occurrences',
        $wpdb->prefix . 'mj_event_occurrences',
        $wpdb->prefix . 'event_occurrences',
    );

    foreach ($candidates as $candidate) {
        if (mj_member_table_exists($candidate)) {
            $cached = $candidate;
            return $cached;
        }
    }

    $cached = $candidates[0];
    return $cached;
}

function mj_member_get_event_occurrences_table_name() {
    return mj_member_get_event_date_occurrences_table_name();
}

function mj_member_get_event_locations_table_name() {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    global $wpdb;
    $candidates = array(
        $wpdb->prefix . 'mj_event_locations',
        $wpdb->prefix . 'event_locations',
        $wpdb->prefix . 'mj_locations',
    );

    foreach ($candidates as $candidate) {
        if (mj_member_table_exists($candidate)) {
            $cached = $candidate;
            return $cached;
        }
    }

    $cached = $candidates[0];
    return $cached;
}

function mj_member_ensure_event_schedule_columns_fallback() {
    global $wpdb;

    if (!isset($wpdb) || !is_object($wpdb)) {
        return;
    }

    $candidates = array(
        $wpdb->prefix . 'mj_events',
        $wpdb->prefix . 'events',
    );

    foreach ($candidates as $events_table) {
        if ($events_table === '') {
            continue;
        }

        $table_pattern = $wpdb->esc_like($events_table);
        $table_like = $wpdb->prepare('SHOW TABLES LIKE %s', $table_pattern);
        $table_name = $wpdb->get_var($table_like);
        if ($table_name !== $events_table) {
            continue;
        }

        $table_sql = '`' . esc_sql($events_table) . '`';

        $schedule_mode_exists = $wpdb->get_var("SHOW COLUMNS FROM {$table_sql} LIKE 'schedule_mode'");
        if (empty($schedule_mode_exists)) {
            $guardian_column_exists = $wpdb->get_var("SHOW COLUMNS FROM {$table_sql} LIKE 'allow_guardian_registration'");
            if (!empty($guardian_column_exists)) {
                $wpdb->query("ALTER TABLE {$table_sql} ADD COLUMN schedule_mode varchar(20) NOT NULL DEFAULT 'fixed' AFTER allow_guardian_registration");
            } else {
                $wpdb->query("ALTER TABLE {$table_sql} ADD COLUMN schedule_mode varchar(20) NOT NULL DEFAULT 'fixed'");
            }
        }

        $schedule_payload_exists = $wpdb->get_var("SHOW COLUMNS FROM {$table_sql} LIKE 'schedule_payload'");
        if (empty($schedule_payload_exists)) {
            $schedule_mode_exists = $wpdb->get_var("SHOW COLUMNS FROM {$table_sql} LIKE 'schedule_mode'");
            if (!empty($schedule_mode_exists)) {
                $wpdb->query("ALTER TABLE {$table_sql} ADD COLUMN schedule_payload longtext DEFAULT NULL AFTER schedule_mode");
            } else {
                $wpdb->query("ALTER TABLE {$table_sql} ADD COLUMN schedule_payload longtext DEFAULT NULL");
            }
        }

        $index_exists = $wpdb->get_var("SHOW INDEX FROM {$table_sql} WHERE Key_name = 'idx_schedule_mode'");
        if (empty($index_exists)) {
            $wpdb->query("ALTER TABLE {$table_sql} ADD KEY idx_schedule_mode (schedule_mode)");
        }

        $wpdb->query("UPDATE {$table_sql} SET schedule_mode = 'fixed' WHERE schedule_mode IS NULL OR schedule_mode = ''");
    }
}
add_action('plugins_loaded', 'mj_member_ensure_event_schedule_columns_fallback', 1);
add_action('init', 'mj_member_ensure_event_schedule_columns_fallback', 4);
if (!did_action('plugins_loaded')) {
    mj_member_ensure_event_schedule_columns_fallback();
}

function mj_member_get_event_animateurs_table_name() {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    global $wpdb;
    $candidates = array(
        $wpdb->prefix . 'mj_event_animateurs',
        $wpdb->prefix . 'event_animateurs',
    );

    foreach ($candidates as $candidate) {
        if (mj_member_table_exists($candidate)) {
            $cached = $candidate;
            return $cached;
        }
    }

    $cached = $candidates[0];
    return $cached;
}

function mj_member_get_event_volunteers_table_name() {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    global $wpdb;
    $candidates = array(
        $wpdb->prefix . 'mj_event_volunteers',
        $wpdb->prefix . 'event_volunteers',
    );

    foreach ($candidates as $candidate) {
        if (mj_member_table_exists($candidate)) {
            $cached = $candidate;
            return $cached;
        }
    }

    $cached = $candidates[0];
    return $cached;
}

function mj_member_get_event_location_links_table_name() {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    global $wpdb;
    $candidates = array(
        $wpdb->prefix . 'mj_event_location_links',
        $wpdb->prefix . 'event_location_links',
    );

    foreach ($candidates as $candidate) {
        if (mj_member_table_exists($candidate)) {
            $cached = $candidate;
            return $cached;
        }
    }

    $cached = $candidates[0];
    return $cached;
}

function mj_member_get_event_attendance_table_name() {
    return mj_member_get_event_registrations_table_name();
}

function mj_member_get_hours_table_name() {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    global $wpdb;
    $candidate = $wpdb->prefix . 'mj_member_hours';

    if (mj_member_table_exists($candidate)) {
        $cached = $candidate;
        return $cached;
    }

    $cached = $candidate;
    return $cached;
}

function mj_member_get_todo_projects_table_name() {
    static $cached = null;
    if ($cached !== null && mj_member_table_exists($cached)) {
        return $cached;
    }

    global $wpdb;
    $primary = $wpdb->prefix . 'mj_projects';
    $legacy = $wpdb->prefix . 'mj_todo_projects';

    if (mj_member_table_exists($primary)) {
        $cached = $primary;
        return $cached;
    }

    if (mj_member_table_exists($legacy)) {
        $cached = $legacy;
        return $cached;
    }

    $cached = $primary;
    return $cached;
}

function mj_member_get_todos_table_name() {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    global $wpdb;
    $candidate = $wpdb->prefix . 'mj_todos';

    if (mj_member_table_exists($candidate)) {
        $cached = $candidate;
        return $cached;
    }

    $cached = $candidate;
    return $cached;
}

function mj_member_get_leave_types_table_name() {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    global $wpdb;
    $candidate = $wpdb->prefix . 'mj_leave_types';

    if (mj_member_table_exists($candidate)) {
        $cached = $candidate;
        return $cached;
    }

    $cached = $candidate;
    return $cached;
}

function mj_member_get_leave_requests_table_name() {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    global $wpdb;
    $candidate = $wpdb->prefix . 'mj_leave_requests';

    if (mj_member_table_exists($candidate)) {
        $cached = $candidate;
        return $cached;
    }

    $cached = $candidate;
    return $cached;
}

function mj_member_get_todo_assignments_table_name() {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    global $wpdb;
    $candidate = $wpdb->prefix . 'mj_todo_assignments';

    if (mj_member_table_exists($candidate)) {
        $cached = $candidate;
        return $cached;
    }

    $cached = $candidate;
    return $cached;
}

function mj_member_get_todo_notes_table_name() {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    global $wpdb;
    $candidate = $wpdb->prefix . 'mj_todo_notes';

    if (mj_member_table_exists($candidate)) {
        $cached = $candidate;
        return $cached;
    }

    $cached = $candidate;
    return $cached;
}

function mj_member_get_todo_media_table_name() {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    global $wpdb;
    $candidate = $wpdb->prefix . 'mj_todo_media';

    if (mj_member_table_exists($candidate)) {
        $cached = $candidate;
        return $cached;
    }

    $cached = $candidate;
    return $cached;
}

function mj_member_get_ideas_table_name() {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    global $wpdb;
    $candidate = $wpdb->prefix . 'mj_ideas';

    if (mj_member_table_exists($candidate)) {
        $cached = $candidate;
        return $cached;
    }

    $cached = $candidate;
    return $cached;
}

function mj_member_get_idea_votes_table_name() {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    global $wpdb;
    $candidate = $wpdb->prefix . 'mj_idea_votes';

    if (mj_member_table_exists($candidate)) {
        $cached = $candidate;
        return $cached;
    }

    $cached = $candidate;
    return $cached;
}

function mj_member_get_contact_message_recipients_table_name() {
    global $wpdb;
    return $wpdb->prefix . 'mj_contact_message_recipients';
}

function mj_member_get_notifications_table_name() {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    global $wpdb;
    $candidates = array(
        $wpdb->prefix . 'mj_notifications',
        $wpdb->prefix . 'notifications',
    );

    foreach ($candidates as $candidate) {
        if (mj_member_table_exists($candidate)) {
            $cached = $candidate;
            return $cached;
        }
    }

    $cached = $candidates[0];
    return $cached;
}

function mj_member_get_notification_recipients_table_name() {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    global $wpdb;
    $candidates = array(
        $wpdb->prefix . 'mj_notification_recipients',
        $wpdb->prefix . 'notification_recipients',
    );

    foreach ($candidates as $candidate) {
        if (mj_member_table_exists($candidate)) {
            $cached = $candidate;
            return $cached;
        }
    }

    $cached = $candidates[0];
    return $cached;
}

function mj_member_get_badges_table_name() {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    global $wpdb;
    $candidates = array(
        $wpdb->prefix . 'mj_badges',
        $wpdb->prefix . 'badges',
    );

    foreach ($candidates as $candidate) {
        if (mj_member_table_exists($candidate)) {
            $cached = $candidate;
            return $cached;
        }
    }

    $cached = $candidates[0];
    return $cached;
}

function mj_member_get_member_badges_table_name() {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    global $wpdb;
    $candidates = array(
        $wpdb->prefix . 'mj_member_badges',
        $wpdb->prefix . 'member_badges',
    );

    foreach ($candidates as $candidate) {
        if (mj_member_table_exists($candidate)) {
            $cached = $candidate;
            return $cached;
        }
    }

    $cached = $candidates[0];
    return $cached;
}

function mj_member_get_badge_criteria_table_name() {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    global $wpdb;
    $candidates = array(
        $wpdb->prefix . 'mj_badge_criteria',
        $wpdb->prefix . 'badge_criteria',
    );

    foreach ($candidates as $candidate) {
        if (mj_member_table_exists($candidate)) {
            $cached = $candidate;
            return $cached;
        }
    }

    $cached = $candidates[0];
    return $cached;
}

function mj_member_get_member_badge_criteria_table_name() {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    global $wpdb;
    $candidates = array(
        $wpdb->prefix . 'mj_member_badge_criteria',
        $wpdb->prefix . 'member_badge_criteria',
    );

    foreach ($candidates as $candidate) {
        if (mj_member_table_exists($candidate)) {
            $cached = $candidate;
            return $cached;
        }
    }

    $cached = $candidates[0];
    return $cached;
}

function mj_member_get_trophies_table_name() {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    global $wpdb;
    $candidates = array(
        $wpdb->prefix . 'mj_trophies',
        $wpdb->prefix . 'trophies',
    );

    foreach ($candidates as $candidate) {
        if (mj_member_table_exists($candidate)) {
            $cached = $candidate;
            return $cached;
        }
    }

    $cached = $candidates[0];
    return $cached;
}

function mj_member_get_member_trophies_table_name() {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    global $wpdb;
    $candidates = array(
        $wpdb->prefix . 'mj_member_trophies',
        $wpdb->prefix . 'member_trophies',
    );

    foreach ($candidates as $candidate) {
        if (mj_member_table_exists($candidate)) {
            $cached = $candidate;
            return $cached;
        }
    }

    $cached = $candidates[0];
    return $cached;
}

function mj_member_get_levels_table_name() {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    global $wpdb;
    $candidates = array(
        $wpdb->prefix . 'mj_levels',
        $wpdb->prefix . 'levels',
    );

    foreach ($candidates as $candidate) {
        if (mj_member_table_exists($candidate)) {
            $cached = $candidate;
            return $cached;
        }
    }

    $cached = $candidates[0];
    return $cached;
}

function mj_member_get_action_types_table_name() {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    global $wpdb;
    $candidates = array(
        $wpdb->prefix . 'mj_action_types',
    );

    foreach ($candidates as $candidate) {
        if (mj_member_table_exists($candidate)) {
            $cached = $candidate;
            return $cached;
        }
    }

    $cached = $candidates[0];
    return $cached;
}

function mj_member_get_member_actions_table_name() {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    global $wpdb;
    $candidates = array(
        $wpdb->prefix . 'mj_member_actions',
    );

    foreach ($candidates as $candidate) {
        if (mj_member_table_exists($candidate)) {
            $cached = $candidate;
            return $cached;
        }
    }

    $cached = $candidates[0];
    return $cached;
}

function mj_member_ensure_auxiliary_tables() {
    global $wpdb;
    if ( ! function_exists('dbDelta') ) {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    }

    $charset_collate = $wpdb->get_charset_collate();

    $templates_table = $wpdb->prefix . 'mj_email_templates';
    if ($wpdb->get_var("SHOW TABLES LIKE '$templates_table'") !== $templates_table) {
        $sql = "CREATE TABLE IF NOT EXISTS $templates_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            slug varchar(120) NOT NULL,
            subject varchar(255) NOT NULL,
            content longtext NOT NULL,
            sms_content longtext DEFAULT NULL,
            whatsapp_content longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY slug (slug)
        ) $charset_collate;";
        dbDelta($sql);
    } else {
        if (!mj_member_column_exists($templates_table, 'sms_content')) {
            $wpdb->query("ALTER TABLE $templates_table ADD COLUMN sms_content longtext DEFAULT NULL AFTER content");
        }
        if (!mj_member_column_exists($templates_table, 'whatsapp_content')) {
            $wpdb->query("ALTER TABLE $templates_table ADD COLUMN whatsapp_content longtext DEFAULT NULL AFTER sms_content");
        }
    }

    $payments_table = $wpdb->prefix . 'mj_payments';
    $sql_payments = "CREATE TABLE IF NOT EXISTS $payments_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        member_id mediumint(9) NOT NULL,
        payer_id mediumint(9) DEFAULT NULL,
        event_id bigint(20) unsigned DEFAULT NULL,
        registration_id bigint(20) unsigned DEFAULT NULL,
        amount decimal(10,2) NOT NULL DEFAULT '0.00',
        status varchar(20) NOT NULL DEFAULT 'pending',
        token varchar(120) NOT NULL,
        external_ref varchar(255) DEFAULT NULL,
        context varchar(60) NOT NULL DEFAULT 'membership',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        paid_at datetime DEFAULT NULL,
        PRIMARY KEY  (id),
        KEY payer_idx (payer_id),
        KEY idx_event (event_id),
        KEY idx_registration (registration_id)
    ) $charset_collate;";
    dbDelta($sql_payments);

    $payer_exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
        DB_NAME,
        $payments_table,
        'payer_id'
    ));
    if (!$payer_exists) {
        $wpdb->query("ALTER TABLE $payments_table ADD COLUMN payer_id mediumint(9) DEFAULT NULL AFTER member_id");
        $wpdb->query("ALTER TABLE $payments_table ADD KEY payer_idx (payer_id)");
    }

    if (!mj_member_column_exists($payments_table, 'event_id')) {
        $wpdb->query("ALTER TABLE $payments_table ADD COLUMN event_id bigint(20) unsigned DEFAULT NULL AFTER payer_id");
    }
    if (!mj_member_column_exists($payments_table, 'registration_id')) {
        $wpdb->query("ALTER TABLE $payments_table ADD COLUMN registration_id bigint(20) unsigned DEFAULT NULL AFTER event_id");
    }
    if (!mj_member_column_exists($payments_table, 'context')) {
        $wpdb->query("ALTER TABLE $payments_table ADD COLUMN context varchar(60) NOT NULL DEFAULT 'membership' AFTER registration_id");
    }
    if (!mj_member_index_exists($payments_table, 'idx_event')) {
        $wpdb->query("ALTER TABLE $payments_table ADD KEY idx_event (event_id)");
    }
    if (!mj_member_index_exists($payments_table, 'idx_registration')) {
        $wpdb->query("ALTER TABLE $payments_table ADD KEY idx_registration (registration_id)");
    }
    if (!mj_member_column_exists($payments_table, 'checkout_url')) {
        $wpdb->query("ALTER TABLE $payments_table ADD COLUMN checkout_url varchar(500) DEFAULT NULL AFTER external_ref");
    }

    $payments_hist_table = $wpdb->prefix . 'mj_payment_history';
    $sql_history = "CREATE TABLE IF NOT EXISTS $payments_hist_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        member_id mediumint(9) NOT NULL,
        payer_id mediumint(9) DEFAULT NULL,
        amount decimal(10,2) NOT NULL DEFAULT '0.00',
        payment_date datetime DEFAULT CURRENT_TIMESTAMP,
        method varchar(100) DEFAULT NULL,
        reference varchar(255) DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY payer_idx (payer_id)
    ) $charset_collate;";
    dbDelta($sql_history);

    $history_payer_exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
        DB_NAME,
        $payments_hist_table,
        'payer_id'
    ));
    if (!$history_payer_exists) {
        $wpdb->query("ALTER TABLE $payments_hist_table ADD COLUMN payer_id mediumint(9) DEFAULT NULL AFTER member_id");
        $wpdb->query("ALTER TABLE $payments_hist_table ADD KEY payer_idx (payer_id)");
    }

    $hours_table = mj_member_get_hours_table_name();
    $sql_hours = "CREATE TABLE IF NOT EXISTS $hours_table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        member_id bigint(20) unsigned NOT NULL,
        recorded_by bigint(20) unsigned NOT NULL DEFAULT 0,
        task_key varchar(120) DEFAULT NULL,
        task_label varchar(191) NOT NULL,
        activity_date date NOT NULL,
        start_time time DEFAULT NULL,
        end_time time DEFAULT NULL,
        duration_minutes int unsigned NOT NULL DEFAULT 0,
        notes text DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY member_date_idx (member_id, activity_date),
        KEY recorded_by_idx (recorded_by),
        KEY task_key_idx (task_key)
    ) $charset_collate;";
    dbDelta($sql_hours);

    $email_logs_table = $wpdb->prefix . 'mj_email_logs';
    $sql_email_logs = "CREATE TABLE IF NOT EXISTS $email_logs_table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        member_id bigint(20) unsigned DEFAULT NULL,
        template_id mediumint(9) DEFAULT NULL,
        template_slug varchar(190) DEFAULT NULL,
        subject varchar(255) NOT NULL DEFAULT '',
        recipients longtext NOT NULL,
        status varchar(20) NOT NULL DEFAULT 'sent',
        is_test_mode tinyint(1) NOT NULL DEFAULT 0,
        error_message text DEFAULT NULL,
        body_html longtext DEFAULT NULL,
        body_plain longtext DEFAULT NULL,
        headers longtext DEFAULT NULL,
        context longtext DEFAULT NULL,
        source varchar(120) NOT NULL DEFAULT '',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY member_idx (member_id),
        KEY status_idx (status),
        KEY template_idx (template_slug(100))
    ) $charset_collate;";
    dbDelta($sql_email_logs);
}

function mj_member_seed_email_templates() {
    global $wpdb;

    $table = $wpdb->prefix . 'mj_email_templates';
    if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
        return;
    }

    $templates = array(
        'member_registration' => array(
            'subject' => 'MJ Péry – Finaliser l\'inscription de {{member_full_name}}',
            'content' => <<<'HTML'
<p>Bonjour,</p>
<p>Nous avons bien reçu la demande d'inscription pour <strong>{{member_full_name}}</strong> (rôle&nbsp;: {{member_role}}).</p>
<p>Pour activer la cotisation annuelle d'un montant de <strong>{{payment_amount}} €</strong>, cliquez sur le lien sécurisé ci-dessous&nbsp;:</p>
<p>{{payment_button}}</p>
<p class="mj-muted">Si le lien ne s'ouvre pas, copiez l'adresse suivante dans votre navigateur&nbsp;:<br>{{payment_link}}</p>
<p>Informations utiles :</p>
<ul>
    <li>Membre : {{member_full_name}}</li>
    <li>Email de contact : {{member_email}}</li>
    <li>Tuteur : {{guardian_full_name}}</li>
    <li>Date d'inscription : {{date_inscription}}</li>
</ul>
<p>Merci et à très bientôt à la {{site_name}} !</p>
{{cash_payment_note}}
HTML
        ),
        'guardian_registration' => array(
            'subject' => 'MJ Péry – Paiements à effectuer pour vos jeunes',
            'content' => <<<'HTML'
<p>Bonjour {{member_first_name}},</p>
<p>Merci d'avoir inscrit vos jeunes à la {{site_name}}.</p>
{{guardian_children_note}}
<p>Pour finaliser les adhésions, veuillez effectuer les paiements suivants&nbsp;:</p>
{{children_payment_table}}
<p>Montant total à régler&nbsp;: <strong>{{children_payment_total}} €</strong></p>
<p>Chaque lien ouvre un paiement sécurisé. Si un lien ne fonctionne pas, copiez l'adresse correspondante dans votre navigateur ou contactez-nous.</p>
<p>À très bientôt,<br>L'équipe {{site_name}}</p>
{{cash_payment_note}}
HTML
        ),
        'payment_confirmation' => array(
            'subject' => 'MJ Péry – Paiement confirmé pour {{member_full_name}}',
            'content' => <<<'HTML'
<p>Bonjour {{member_first_name}},</p>
<p>Nous confirmons la réception de votre paiement de <strong>{{payment_amount}} €</strong> le {{payment_date}}.</p>
<p>Votre cotisation est désormais à jour. Merci pour votre confiance&nbsp;!</p>
<p>Besoin d'une attestation ou d'une information supplémentaire&nbsp;? Répondez simplement à cet e-mail.</p>
<p>À bientôt,<br>L'équipe {{site_name}}</p>
{{cash_payment_note}}
HTML
        ),
        'payment_reminder' => array(
            'subject' => 'MJ Péry – Renouvellement de cotisation pour {{member_full_name}}',
            'content' => <<<'HTML'
<p>Bonjour,</p>
<p>La cotisation de <strong>{{member_full_name}}</strong> a expiré (dernier paiement enregistré le {{payment_last_date}}).</p>
<p>Pour renouveler l'adhésion d'un montant de <strong>{{payment_amount}} €</strong>, merci d'utiliser le lien sécurisé suivant&nbsp;:</p>
<p>{{payment_button}}</p>
<p class="mj-muted">Si le lien ne fonctionne pas, copiez cette adresse dans votre navigateur&nbsp;:<br>{{payment_link}}</p>
<p>Nous restons disponibles en cas de question.</p>
<p>À très vite,<br>L'équipe {{site_name}}</p>
{{cash_payment_note}}
HTML
        ),
        'event_capacity_alert' => array(
            'subject' => 'MJ Péry – Alerte capacité pour {{event_title}}',
            'content' => <<<'HTML'
<p>Bonjour,</p>
<p>Le seuil de notification est atteint pour l'événement <strong>{{event_title}}</strong>.</p>
{{event_details_list}}
{{event_admin_link}}
<p>Pensez à vérifier la liste d'attente et à prévenir votre équipe si nécessaire.</p>
<p>— MJ Péry</p>
HTML
            ,
            'sms' => 'Alerte MJ Péry : {{event_title}} compte {{active_registrations}} inscrits sur {{capacity_total}} (reste {{remaining_slots}}).',
        ),
        'registration_admin_notification' => array(
            'subject' => 'MJ Péry – Nouvelle inscription en ligne',
            'content' => <<<'HTML'
<p>Bonjour,</p>
<p>Une nouvelle inscription vient d'être envoyée depuis le site.</p>
<p><strong>Type :</strong> {{registration_type_label}}</p>
{{guardian_summary_html}}
{{member_contact_html}}
<h3>Participants</h3>
{{children_list_html}}
<p><strong>Résumé texte :</strong></p>
<pre style="background:#f7f7f7;padding:12px;border-radius:6px;">{{children_list}}</pre>
<p>— MJ Péry</p>
HTML
            ,
            'sms' => 'MJ Péry : nouvelle inscription ({{registration_type_label}}). Participants : {{children_list}}.',
        ),
        'payment_request' => array(
            'subject' => 'MJ Péry – Paiement à compléter pour {{member_full_name}}',
            'content' => <<<'HTML'
<p>Bonjour {{member_first_name}},</p>
<p>Merci de finaliser la cotisation de <strong>{{payment_amount}} €</strong>.</p>
{{payment_button}}
<p>Vous pouvez également utiliser ce lien sécurisé : <a href="{{payment_checkout_url}}">{{payment_checkout_url}}</a></p>
{{payment_qr_block}}
{{cash_payment_note}}
<p>— MJ Péry</p>
HTML
            ,
            'sms' => 'MJ Péry : merci de régler {{payment_amount}} € pour {{member_full_name}} via {{payment_checkout_url}}.',
        ),
    );

    foreach ($templates as $slug => $data) {
        $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE slug = %s", $slug));
        if ($existing) {
            $updates = array();
            $formats = array();

            if (isset($data['sms']) && (!isset($existing->sms_content) || $existing->sms_content === '')) {
                $updates['sms_content'] = $data['sms'];
                $formats[] = '%s';
            }

            if (!empty($updates)) {
                $wpdb->update($table, $updates, array('id' => (int) $existing->id), $formats, array('%d'));
            }
            continue;
        }

        $insert_data = array(
            'slug' => $slug,
            'subject' => isset($data['subject']) ? $data['subject'] : '',
            'content' => isset($data['content']) ? $data['content'] : '',
        );
        $insert_formats = array('%s', '%s', '%s');

        if (isset($data['sms'])) {
            $insert_data['sms_content'] = $data['sms'];
            $insert_formats[] = '%s';
        }

        $wpdb->insert($table, $insert_data, $insert_formats);
    }
}
add_action('init', 'mj_member_seed_email_templates', 15);

if (!function_exists('mj_member_get_default_badge_slugs')) {
    function mj_member_get_default_badge_slugs(): array {
        return array(
            'premier-pas',
            'habitue-du-pery',
            'voix-du-groupe',
            'esprit-cracs',
            'coup-de-main',
            'creatif',
            'organisateur',
            'ambassadeur',
            'moteur-de-projet',
            'grimlin-du-pery',
        );
    }
}

if (!function_exists('mj_member_get_removed_default_badge_slugs')) {
    function mj_member_get_removed_default_badge_slugs(): array {
        $stored = get_option('mj_member_removed_default_badges', array());
        if (!is_array($stored)) {
            return array();
        }

        $normalized = array();
        foreach ($stored as $slug) {
            $candidate = sanitize_title((string) $slug);
            if ($candidate !== '') {
                $normalized[$candidate] = $candidate;
            }
        }

        return array_values($normalized);
    }
}

if (!function_exists('mj_member_mark_default_badge_removed')) {
    function mj_member_mark_default_badge_removed($slug): void {
        $normalized = sanitize_title((string) $slug);
        if ($normalized === '') {
            return;
        }

        $default_slugs = mj_member_get_default_badge_slugs();
        if (!in_array($normalized, $default_slugs, true)) {
            return;
        }

        $removed = mj_member_get_removed_default_badge_slugs();
        if (!in_array($normalized, $removed, true)) {
            $removed[] = $normalized;
            update_option('mj_member_removed_default_badges', $removed);
        }
    }
}

if (!function_exists('mj_member_unmark_default_badge_removed')) {
    function mj_member_unmark_default_badge_removed($slug): void {
        $normalized = sanitize_title((string) $slug);
        if ($normalized === '') {
            return;
        }

        $removed = mj_member_get_removed_default_badge_slugs();
        if (empty($removed)) {
            return;
        }

        $index = array_search($normalized, $removed, true);
        if ($index === false) {
            return;
        }

        unset($removed[$index]);
        if (empty($removed)) {
            delete_option('mj_member_removed_default_badges');
        } else {
            update_option('mj_member_removed_default_badges', array_values($removed));
        }
    }
}

function mj_member_seed_default_badges() {
    global $wpdb;

    $badges_table = mj_member_get_badges_table_name();
    if (!mj_member_table_exists($badges_table)) {
        return;
    }

    $badge_criteria_table = mj_member_get_badge_criteria_table_name();
    $has_criteria_table = mj_member_table_exists($badge_criteria_table);

    $badge_definitions = array(
        array(
            'slug' => 'premier-pas',
            'label' => 'Premier Pas',
            'summary' => "A franchi la porte de la MJ et commencé à s'impliquer.",
            'description' => "Attribué aux jeunes qui découvrent la MJ Péry et font leurs premiers pas, en participant activement et en échangeant avec l'équipe.",
            'criteria' => array(
                'Venir au moins 3 fois à la MJ',
                'Participer à une activité ou un temps collectif',
                'Échanger avec un·e animateur·rice'
            ),
            'prompt' => 'Grimlin mascot taking a confident first step forward, circular emblem badge, flat illustration, thick bold outlines, vibrant colors, street graffiti style, simple textured background, no text',
            'icon' => 'green_circle',
            'display_order' => 10,
        ),
        array(
            'slug' => 'habitue-du-pery',
            'label' => 'Habitué·e du Péry',
            'summary' => 'Présence régulière et respect du cadre.',
            'description' => "Récompense les jeunes qui fréquentent régulièrement la MJ et contribuent à un cadre respectueux et positif.",
            'criteria' => array(
                'Fréquenter la MJ sur plusieurs semaines',
                'Respecter le cadre et les autres',
                "Être reconnu·e par l'équipe comme présence positive"
            ),
            'prompt' => 'Grimlin mascot relaxed and comfortable, casual seated posture, circular emblem badge, flat illustration, thick bold outlines, vibrant colors, street graffiti style, simple textured background, no text',
            'icon' => 'yellow_circle',
            'display_order' => 20,
        ),
        array(
            'slug' => 'voix-du-groupe',
            'label' => 'Voix du Groupe',
            'summary' => "Ose s'exprimer et donner son avis.",
            'description' => "Valorise les prises de parole et la capacité à écouter les autres au sein du groupe.",
            'criteria' => array(
                "Prendre la parole au moins une fois dans un échange collectif",
                'Donner un avis ou une idée',
                'Écouter les autres sans les interrompre'
            ),
            'prompt' => 'Grimlin mascot speaking confidently into a microphone, expressive face, circular emblem badge, flat illustration, thick bold outlines, vibrant colors, street graffiti style, simple textured background, no text',
            'icon' => 'blue_circle',
            'display_order' => 30,
        ),
        array(
            'slug' => 'esprit-cracs',
            'label' => 'Esprit CRACS',
            'summary' => 'Posture critique, citoyenne et responsable.',
            'description' => "Salue les jeunes qui montrent un esprit critique, une réflexion citoyenne et un sens des responsabilités.",
            'criteria' => array(
                'Participer à un débat, atelier ou discussion',
                'Exprimer un point de vue personnel argumenté',
                'Montrer une capacité de recul ou de réflexion'
            ),
            'prompt' => 'Grimlin mascot thinking with a lightbulb above head, reflective posture, circular emblem badge, flat illustration, thick bold outlines, vibrant colors, street graffiti style, simple textured background, no text',
            'icon' => 'purple_circle',
            'display_order' => 40,
        ),
        array(
            'slug' => 'coup-de-main',
            'label' => 'Coup de Main',
            'summary' => 'Soutient le collectif par des actions concrètes.',
            'description' => "Met en lumière celles et ceux qui contribuent concrètement à la vie collective par des coups de main spontanés.",
            'criteria' => array(
                'Aider à installer, ranger ou organiser',
                'Donner un coup de main sans y être obligé·e',
                'Adopter une attitude solidaire'
            ),
            'prompt' => 'Grimlin mascot helping another Grimlin lift an object, teamwork posture, circular emblem badge, flat illustration, thick bold outlines, vibrant colors, street graffiti style, simple textured background, no text',
            'icon' => 'orange_circle',
            'display_order' => 50,
        ),
        array(
            'slug' => 'creatif',
            'label' => 'Créatif·ve',
            'summary' => "S'exprime par la création ou l'art.",
            'description' => "Récompense l'expression artistique et la capacité à partager une création avec le groupe.",
            'criteria' => array(
                'Participer à un atelier créatif ou artistique',
                'Produire ou co-produire une création',
                'Partager sa création avec le groupe'
            ),
            'prompt' => 'Grimlin mascot creating street art with spray paint, dynamic creative pose, circular emblem badge, flat illustration, thick bold outlines, vibrant colors, street graffiti style, simple textured background, no text',
            'icon' => 'artist_palette',
            'display_order' => 60,
        ),
        array(
            'slug' => 'organisateur',
            'label' => 'Organisateur·rice',
            'summary' => "S'implique dans la préparation d'une activité ou d'un projet.",
            'description' => "Souligne l'engagement dans l'organisation d'activités, avec prise de responsabilités et persévérance.",
            'criteria' => array(
                "Participer à l'organisation d'un événement ou projet",
                'Prendre au moins une responsabilité concrète',
                'Aller au bout de ce qui a été proposé'
            ),
            'prompt' => 'Grimlin mascot holding a clipboard and tools, planning posture, circular emblem badge, flat illustration, thick bold outlines, vibrant colors, street graffiti style, simple textured background, no text',
            'icon' => 'hammer_and_wrench',
            'display_order' => 70,
        ),
        array(
            'slug' => 'ambassadeur',
            'label' => 'Ambassadeur·rice',
            'summary' => 'Représente positivement la MJ à l\'extérieur.',
            'description' => "Valorise la représentation positive de la MJ en dehors de ses murs et la capacité à faire rayonner la structure.",
            'criteria' => array(
                "Parler de la MJ à quelqu'un d'extérieur",
                'Inviter un·e nouveau·elle jeune',
                'Participer à une action visible hors les murs'
            ),
            'prompt' => 'Grimlin mascot pointing outward proudly, confident stance, circular emblem badge, flat illustration, thick bold outlines, vibrant colors, street graffiti style, simple textured background, no text',
            'icon' => 'megaphone',
            'display_order' => 80,
        ),
        array(
            'slug' => 'moteur-de-projet',
            'label' => 'Moteur de Projet',
            'summary' => 'Participe activement à un projet sur toute sa durée.',
            'description' => "Récompense un engagement constant tout au long d'un projet collectif et la contribution active à sa réussite.",
            'criteria' => array(
                "S'impliquer du début à la fin d'un projet",
                'Être présent·e aux moments clés',
                'Contribuer à la réussite collective'
            ),
            'prompt' => 'Grimlin mascot pushing a large gear forward, determined action pose, circular emblem badge, flat illustration, thick bold outlines, vibrant colors, street graffiti style, simple textured background, no text',
            'icon' => 'gear',
            'display_order' => 90,
        ),
        array(
            'slug' => 'grimlin-du-pery',
            'label' => 'Grimlin du Péry',
            'summary' => 'Reconnaissance collective pour un engagement durable.',
            'description' => "Distinction ultime qui salue un engagement durable et l'incarnation quotidienne des valeurs de la MJ.",
            'criteria' => array(
                'Cumuler plusieurs badges précédents',
                "Être reconnu·e par les jeunes et l'équipe",
                'Incarner les valeurs de la MJ au quotidien'
            ),
            'prompt' => 'Iconic Grimlin mascot standing strong and proud, central heroic pose, circular emblem badge, flat illustration, thick bold outlines, vibrant colors, street graffiti style, simple textured background, no text',
            'icon' => 'star',
            'display_order' => 100,
        ),
    );

    $removed_slugs = mj_member_get_removed_default_badge_slugs();
    $removed_lookup = array();
    foreach ($removed_slugs as $slug) {
        $removed_lookup[$slug] = true;
    }

    foreach ($badge_definitions as $badge) {
        $slug = isset($badge['slug']) ? sanitize_title((string) $badge['slug']) : '';
        if ($slug === '') {
            continue;
        }

        $existing_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$badges_table} WHERE slug = %s",
            $slug
        ));

        if ($existing_id <= 0 && isset($removed_lookup[$slug])) {
            continue;
        }

        if ($existing_id > 0 && isset($removed_lookup[$slug])) {
            mj_member_unmark_default_badge_removed($slug);
            unset($removed_lookup[$slug]);
        }

        if ($existing_id <= 0) {
            $criteria_json = null;
            if (!empty($badge['criteria'])) {
                $encoded = wp_json_encode(array_values($badge['criteria']));
                if (is_string($encoded) && $encoded !== '') {
                    $criteria_json = $encoded;
                }
            }

            $wpdb->insert(
                $badges_table,
                array(
                    'slug' => $slug,
                    'label' => $badge['label'],
                    'summary' => $badge['summary'],
                    'description' => $badge['description'],
                    'criteria' => $criteria_json,
                    'prompt' => $badge['prompt'],
                    'icon' => $badge['icon'],
                    'display_order' => isset($badge['display_order']) ? (int) $badge['display_order'] : 0,
                    'status' => 'active',
                ),
                array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s')
            );

            $existing_id = (int) $wpdb->insert_id;
        }

        $badge_id = (int) $existing_id;
        if ($badge_id <= 0) {
            continue;
        }

        if ($has_criteria_table && !empty($badge['criteria'])) {
            $order = 0;
            foreach ($badge['criteria'] as $criterion_label) {
                $label = trim((string) $criterion_label);
                if ($label === '') {
                    continue;
                }

                // Vérifier si un critère avec ce LABEL existe déjà pour ce badge (peu importe le statut)
                $existing_criterion_id = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$badge_criteria_table} WHERE badge_id = %d AND label = %s LIMIT 1",
                    $badge_id,
                    $label
                ));

                if ($existing_criterion_id > 0) {
                    // Le critère existe déjà, on ne fait rien (pas de mise à jour de l'ordre pour éviter les conflits)
                    $order++;
                    continue;
                }

                // Le critère n'existe pas, on le crée
                $base_slug = sanitize_title($label);
                if ($base_slug === '') {
                    $base_slug = sanitize_title($slug . '-' . ($order + 1));
                }
                if ($base_slug === '') {
                    $base_slug = 'criterion-' . $badge_id . '-' . ($order + 1);
                }

                $criterion_slug = $base_slug;
                $suffix = 2;
                while ((int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$badge_criteria_table} WHERE badge_id = %d AND slug = %s",
                    $badge_id,
                    $criterion_slug
                )) > 0) {
                    $criterion_slug = $base_slug . '-' . $suffix;
                    $suffix++;
                }

                $wpdb->insert(
                    $badge_criteria_table,
                    array(
                        'badge_id' => $badge_id,
                        'slug' => $criterion_slug,
                        'label' => $label,
                        'display_order' => $order,
                        'status' => 'active',
                        'created_at' => current_time('mysql'),
                        'updated_at' => current_time('mysql'),
                    ),
                    array('%d', '%s', '%s', '%d', '%s', '%s', '%s')
                );

                $order++;
            }
        }
    }
}
add_action('init', 'mj_member_seed_default_badges', 16);

/**
 * Seed default trophies based on TROPHE.md.
 */
function mj_member_seed_default_trophies() {
    global $wpdb;

    $trophies_table = mj_member_get_trophies_table_name();
    if (!mj_member_table_exists($trophies_table)) {
        return;
    }

    $trophy_definitions = array(
        array(
            'slug' => 'compte-active',
            'title' => 'Compte activé',
            'description' => 'Le membre a complété et activé son compte sur le site.',
            'xp' => 10,
            'auto_mode' => 1,
            'auto_hook' => 'account_activated',
            'auto_threshold' => 1,
            'display_order' => 10,
        ),
        array(
            'slug' => 'cotisation-reglee',
            'title' => 'Cotisation réglée',
            'description' => 'Le membre a payé sa cotisation annuelle.',
            'xp' => 20,
            'auto_mode' => 1,
            'auto_hook' => 'subscription_paid',
            'auto_threshold' => 1,
            'display_order' => 20,
        ),
        array(
            'slug' => 'profil-complete',
            'title' => 'Profil complété',
            'description' => 'Le membre a complété les informations de son profil.',
            'xp' => 15,
            'auto_mode' => 1,
            'auto_hook' => 'profile_completed',
            'auto_threshold' => 1,
            'display_order' => 30,
        ),
        array(
            'slug' => 'premiere-photo-publiee',
            'title' => 'Première photo publiée',
            'description' => 'Le membre a publié une photo sur le site (galerie, activité, projet).',
            'xp' => 15,
            'auto_mode' => 1,
            'auto_hook' => 'photo_published',
            'auto_threshold' => 1,
            'display_order' => 40,
        ),
        array(
            'slug' => 'contributeur-visuel',
            'title' => 'Contributeur·rice visuel·le',
            'description' => 'Le membre a partagé plusieurs photos ou visuels.',
            'xp' => 30,
            'auto_mode' => 1,
            'auto_hook' => 'photos_count',
            'auto_threshold' => 5,
            'display_order' => 50,
        ),
        array(
            'slug' => 'proposition-idee',
            'title' => 'Proposition d\'idée',
            'description' => 'Le membre a proposé une idée via le site (formulaire, espace idées, projet).',
            'xp' => 20,
            'auto_mode' => 1,
            'auto_hook' => 'idea_submitted',
            'auto_threshold' => 1,
            'display_order' => 60,
        ),
        array(
            'slug' => 'force-de-proposition',
            'title' => 'Force de proposition',
            'description' => 'Le membre propose régulièrement des idées ou suggestions.',
            'xp' => 40,
            'auto_mode' => 1,
            'auto_hook' => 'ideas_count',
            'auto_threshold' => 5,
            'display_order' => 70,
        ),
        array(
            'slug' => 'inscrit-activite',
            'title' => 'Inscrit·e à une activité',
            'description' => 'Le membre s\'est inscrit à une activité via le site.',
            'xp' => 10,
            'auto_mode' => 1,
            'auto_hook' => 'event_registered',
            'auto_threshold' => 1,
            'display_order' => 80,
        ),
        array(
            'slug' => 'habitue-inscriptions',
            'title' => 'Habitué·e des inscriptions',
            'description' => 'Le membre utilise régulièrement le site pour s\'inscrire aux activités.',
            'xp' => 30,
            'auto_mode' => 1,
            'auto_hook' => 'registrations_count',
            'auto_threshold' => 10,
            'display_order' => 90,
        ),
        array(
            'slug' => 'commentateur',
            'title' => 'Commentateur·rice',
            'description' => 'Le membre interagit avec les contenus du site (commentaires, réactions).',
            'xp' => 15,
            'auto_mode' => 1,
            'auto_hook' => 'comment_posted',
            'auto_threshold' => 1,
            'display_order' => 100,
        ),
        array(
            'slug' => 'idee-retenue',
            'title' => 'Idée retenue',
            'description' => 'Une idée proposée par le membre a été retenue ou mise en projet.',
            'xp' => 50,
            'auto_mode' => 0,
            'auto_hook' => null,
            'auto_threshold' => 1,
            'display_order' => 110,
        ),
        array(
            'slug' => 'compte-de-confiance',
            'title' => 'Compte de confiance',
            'description' => 'Le membre utilise le site de manière respectueuse et autonome.',
            'xp' => 30,
            'auto_mode' => 0,
            'auto_hook' => null,
            'auto_threshold' => 1,
            'display_order' => 120,
        ),
        array(
            'slug' => 'membre-actif-en-ligne',
            'title' => 'Membre actif·ve en ligne',
            'description' => 'Le membre est régulièrement actif sur la plateforme.',
            'xp' => 40,
            'auto_mode' => 0,
            'auto_hook' => null,
            'auto_threshold' => 1,
            'display_order' => 130,
        ),
        array(
            'slug' => 'ambassadeur-numerique',
            'title' => 'Ambassadeur·rice numérique',
            'description' => 'Le membre aide d\'autres jeunes à utiliser le site ou ses fonctionnalités.',
            'xp' => 50,
            'auto_mode' => 0,
            'auto_hook' => null,
            'auto_threshold' => 1,
            'display_order' => 140,
        ),
    );

    foreach ($trophy_definitions as $trophy) {
        $slug = isset($trophy['slug']) ? sanitize_title((string) $trophy['slug']) : '';
        if ($slug === '') {
            continue;
        }

        $existing_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$trophies_table} WHERE slug = %s",
            $slug
        ));

        if ($existing_id > 0) {
            // Le trophée existe déjà, on le laisse tel quel
            continue;
        }

        $wpdb->insert(
            $trophies_table,
            array(
                'slug' => $slug,
                'title' => $trophy['title'],
                'description' => $trophy['description'],
                'xp' => isset($trophy['xp']) ? (int) $trophy['xp'] : 0,
                'auto_mode' => isset($trophy['auto_mode']) ? (int) $trophy['auto_mode'] : 0,
                'auto_hook' => $trophy['auto_hook'],
                'auto_threshold' => isset($trophy['auto_threshold']) ? (int) $trophy['auto_threshold'] : 1,
                'display_order' => isset($trophy['display_order']) ? (int) $trophy['display_order'] : 0,
                'status' => 'active',
            ),
            array('%s', '%s', '%s', '%d', '%d', '%s', '%d', '%d', '%s')
        );
    }
}
add_action('init', 'mj_member_seed_default_trophies', 17);

function mj_member_run_schema_upgrade() {
    static $running = false;
    if ($running) {
        return;
    }
    $running = true;

    global $wpdb;

    $stored_version = get_option('mj_member_schema_version', '1.0.0');
    $table_name = $wpdb->prefix . 'mj_members';

    $critical_columns = array('description_courte', 'description_longue', 'wp_user_id', 'card_access_key');
    $missing_columns = array();

    foreach ($critical_columns as $column) {
        if (!mj_member_column_exists($table_name, $column)) {
            $missing_columns[] = $column;
        }
    }

    $events_table = mj_member_get_events_table_name();
    $missing_event_columns = array();

    if (mj_member_table_exists($events_table)) {
        $event_critical_columns = array(
            'schedule_mode',
            'schedule_payload',
            'recurrence_until',
            'capacity_total',
            'capacity_waitlist',
            'capacity_notify_threshold',
            'capacity_notified',
            'slug',
            'requires_validation',
            'free_participation',
            'registration_payload',
            'emoji'
        );

        foreach ($event_critical_columns as $column) {
            if (!mj_member_column_exists($events_table, $column)) {
                $missing_event_columns[] = $column;
            }
        }
    }

    $todo_projects_table = function_exists('mj_member_get_todo_projects_table_name')
        ? mj_member_get_todo_projects_table_name()
        : $wpdb->prefix . 'mj_projects';
    $todos_table = function_exists('mj_member_get_todos_table_name')
        ? mj_member_get_todos_table_name()
        : $wpdb->prefix . 'mj_todos';
    $todo_assignments_table = function_exists('mj_member_get_todo_assignments_table_name')
        ? mj_member_get_todo_assignments_table_name()
        : $wpdb->prefix . 'mj_todo_assignments';
    $todo_notes_table = function_exists('mj_member_get_todo_notes_table_name')
        ? mj_member_get_todo_notes_table_name()
        : $wpdb->prefix . 'mj_todo_notes';
    $todo_media_table = function_exists('mj_member_get_todo_media_table_name')
        ? mj_member_get_todo_media_table_name()
        : $wpdb->prefix . 'mj_todo_media';
    $ideas_table = function_exists('mj_member_get_ideas_table_name')
        ? mj_member_get_ideas_table_name()
        : $wpdb->prefix . 'mj_ideas';
    $idea_votes_table = function_exists('mj_member_get_idea_votes_table_name')
        ? mj_member_get_idea_votes_table_name()
        : $wpdb->prefix . 'mj_idea_votes';

    $notifications_table = function_exists('mj_member_get_notifications_table_name')
        ? mj_member_get_notifications_table_name()
        : $wpdb->prefix . 'mj_notifications';

    $badges_table = function_exists('mj_member_get_badges_table_name')
        ? mj_member_get_badges_table_name()
        : $wpdb->prefix . 'mj_badges';
    $member_badges_table = function_exists('mj_member_get_member_badges_table_name')
        ? mj_member_get_member_badges_table_name()
        : $wpdb->prefix . 'mj_member_badges';

    $missing_todo_tables = array();
    if (!mj_member_table_exists($todo_projects_table)) {
        $missing_todo_tables[] = $todo_projects_table;
    }
    if (!mj_member_table_exists($todos_table)) {
        $missing_todo_tables[] = $todos_table;
    }
    if (!mj_member_table_exists($todo_assignments_table)) {
        $missing_todo_tables[] = $todo_assignments_table;
    }
    if (!mj_member_table_exists($todo_notes_table)) {
        $missing_todo_tables[] = $todo_notes_table;
    }
    if (!mj_member_table_exists($todo_media_table)) {
        $missing_todo_tables[] = $todo_media_table;
    }
    $missing_idea_tables = array();
    if (!mj_member_table_exists($ideas_table)) {
        $missing_idea_tables[] = $ideas_table;
    }
    if (!mj_member_table_exists($idea_votes_table)) {
        $missing_idea_tables[] = $idea_votes_table;
    }

    if (!mj_member_table_exists($notifications_table)) {
        $missing_idea_tables[] = $notifications_table;
    }

    $missing_badge_tables = array();
    if (!mj_member_table_exists($badges_table)) {
        $missing_badge_tables[] = $badges_table;
    }
    if (!mj_member_table_exists($member_badges_table)) {
        $missing_badge_tables[] = $member_badges_table;
    }

    $testimonials_table = function_exists('mj_member_get_testimonials_table_name')
        ? mj_member_get_testimonials_table_name()
        : $wpdb->prefix . 'mj_testimonials';
    $missing_testimonials_table = !mj_member_table_exists($testimonials_table);

    // Check for testimonial reactions and comments tables
    $reactions_table = mj_member_get_testimonial_reactions_table_name();
    $comments_table = mj_member_get_testimonial_comments_table_name();
    $missing_testimonial_social_tables = !mj_member_table_exists($reactions_table) || !mj_member_table_exists($comments_table);

    $schema_needs_upgrade = version_compare($stored_version, Config::schemaVersion(), '<')
        || !empty($missing_columns)
        || !empty($missing_event_columns)
        || !empty($missing_todo_tables)
        || !empty($missing_idea_tables)
        || !empty($missing_badge_tables)
        || $missing_testimonials_table
        || $missing_testimonial_social_tables;
    
 
    
    if (!$schema_needs_upgrade) {
        $running = false;
        return;
    }

    if (!function_exists('dbDelta')) {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    }

    mj_member_upgrade_to_2_0($wpdb);
    mj_member_upgrade_to_2_1($wpdb);
    mj_member_upgrade_to_2_2($wpdb);
    mj_member_upgrade_to_2_3($wpdb);
    mj_member_upgrade_to_2_4($wpdb);
    mj_member_upgrade_to_2_5($wpdb);
    mj_member_upgrade_to_2_6($wpdb);
    mj_member_upgrade_to_2_7($wpdb);
    mj_member_upgrade_to_2_8($wpdb);
    mj_member_upgrade_to_2_9($wpdb);
    mj_member_upgrade_to_2_10($wpdb);
    mj_member_upgrade_to_2_11($wpdb);
    mj_member_upgrade_to_2_12($wpdb);
    mj_member_upgrade_to_2_13($wpdb);
    mj_member_upgrade_to_2_14($wpdb);
    mj_member_upgrade_to_2_15($wpdb);
    mj_member_upgrade_to_2_16($wpdb);
    mj_member_upgrade_to_2_17($wpdb);
    mj_member_upgrade_to_2_18($wpdb);
    mj_member_upgrade_to_2_20($wpdb);
    mj_member_upgrade_to_2_21($wpdb);
    mj_member_upgrade_to_2_22($wpdb);
    mj_member_upgrade_to_2_23($wpdb);
    mj_member_upgrade_to_2_24($wpdb);
    mj_member_upgrade_to_2_25($wpdb);
    mj_member_upgrade_to_2_26($wpdb);
    mj_member_upgrade_to_2_27($wpdb);
    mj_member_upgrade_to_2_28($wpdb);
    mj_member_upgrade_to_2_29($wpdb);
    mj_member_upgrade_to_2_30($wpdb);
    mj_member_upgrade_to_2_31($wpdb);
    mj_member_upgrade_to_2_32($wpdb);
    mj_member_upgrade_to_2_33($wpdb);
    mj_member_upgrade_to_2_34($wpdb);
    mj_member_upgrade_to_2_35($wpdb);
    mj_member_upgrade_to_2_36($wpdb);
    mj_member_upgrade_to_2_37($wpdb);
    mj_member_upgrade_to_2_38($wpdb);
    mj_member_upgrade_to_2_39($wpdb);
    mj_member_upgrade_to_2_40($wpdb);
    mj_member_upgrade_to_2_41($wpdb);
    mj_member_upgrade_to_2_42($wpdb);
    mj_member_upgrade_to_2_43($wpdb);
    mj_member_upgrade_to_2_44($wpdb);
    mj_member_upgrade_to_2_45($wpdb);
    mj_member_upgrade_to_2_46($wpdb);
    mj_member_upgrade_to_2_47($wpdb);
    mj_member_upgrade_to_2_48($wpdb);
    mj_member_upgrade_to_2_49($wpdb);
    mj_member_upgrade_to_2_50($wpdb);
    mj_member_upgrade_to_2_51($wpdb);
    mj_member_upgrade_to_2_52($wpdb);
    mj_member_upgrade_to_2_53($wpdb);
    mj_member_upgrade_to_2_54($wpdb);
    mj_member_upgrade_to_2_55($wpdb);
    mj_member_upgrade_to_2_56($wpdb);
    mj_member_upgrade_to_2_57($wpdb);
    mj_member_upgrade_to_2_58($wpdb);
    mj_member_upgrade_to_2_59($wpdb);
    mj_member_upgrade_to_2_60($wpdb);
    mj_member_upgrade_to_2_61($wpdb);
    mj_member_upgrade_to_2_62($wpdb);
    mj_member_upgrade_to_2_63($wpdb);
    mj_member_upgrade_to_2_64($wpdb);
    
    $registrations_table = mj_member_get_event_registrations_table_name();
    if ($registrations_table && mj_member_table_exists($registrations_table)) {
        if (!mj_member_column_exists($registrations_table, 'attendance_payload')) {
            $wpdb->query("ALTER TABLE {$registrations_table} ADD COLUMN attendance_payload longtext DEFAULT NULL AFTER notes");
        }

        if (!mj_member_column_exists($registrations_table, 'attendance_updated_at')) {
            $wpdb->query("ALTER TABLE {$registrations_table} ADD COLUMN attendance_updated_at datetime DEFAULT NULL AFTER attendance_payload");
        }
    }

    if (class_exists('MjMembers')) {
        MjMembers::resetColumnCache();
    }

    update_option('mj_member_schema_version', Config::schemaVersion());
    flush_rewrite_rules(false);
    $running = false;
}

function mj_member_upgrade_to_2_0($wpdb) {
    $table_name = $wpdb->prefix . 'mj_members';

    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
        mj_install();
        return;
    }

    $columns_to_add = array(
        'first_name' => "ALTER TABLE $table_name ADD COLUMN first_name varchar(100) NOT NULL DEFAULT '' AFTER id",
        'last_name' => "ALTER TABLE $table_name ADD COLUMN last_name varchar(100) NOT NULL DEFAULT '' AFTER first_name",
        'email' => "ALTER TABLE $table_name ADD COLUMN email varchar(150) NOT NULL DEFAULT '' AFTER last_name",
        'phone' => "ALTER TABLE $table_name ADD COLUMN phone varchar(30) DEFAULT NULL AFTER email",
        'birth_date' => "ALTER TABLE $table_name ADD COLUMN birth_date date DEFAULT NULL AFTER phone",
        'role' => "ALTER TABLE $table_name ADD COLUMN role varchar(20) NOT NULL DEFAULT 'jeune' AFTER birth_date",
        'guardian_id' => "ALTER TABLE $table_name ADD COLUMN guardian_id mediumint(9) DEFAULT NULL AFTER role",
        'is_autonomous' => "ALTER TABLE $table_name ADD COLUMN is_autonomous tinyint(1) NOT NULL DEFAULT 0 AFTER guardian_id",
        'requires_payment' => "ALTER TABLE $table_name ADD COLUMN requires_payment tinyint(1) NOT NULL DEFAULT 1 AFTER is_autonomous",
        'address' => "ALTER TABLE $table_name ADD COLUMN address varchar(250) DEFAULT NULL AFTER requires_payment",
        'city' => "ALTER TABLE $table_name ADD COLUMN city varchar(120) DEFAULT NULL AFTER address",
        'postal_code' => "ALTER TABLE $table_name ADD COLUMN postal_code varchar(20) DEFAULT NULL AFTER city",
        'notes' => "ALTER TABLE $table_name ADD COLUMN notes text DEFAULT NULL AFTER postal_code"
    );

    foreach ($columns_to_add as $column => $statement) {
        if (!mj_member_column_exists($table_name, $column)) {
            $wpdb->query($statement);
        }
    }

    if (!mj_member_column_exists($table_name, 'photo_id')) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN photo_id bigint(20) DEFAULT NULL AFTER notes");
    }
    if (!mj_member_column_exists($table_name, 'photo_usage_consent')) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN photo_usage_consent tinyint(1) DEFAULT 0 AFTER photo_id");
    }
    if (!mj_member_column_exists($table_name, 'date_inscription')) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN date_inscription datetime NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER photo_usage_consent");
    }
    if (!mj_member_column_exists($table_name, 'date_last_payement')) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN date_last_payement datetime DEFAULT NULL AFTER date_inscription");
    }
    if (!mj_member_column_exists($table_name, 'status')) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN status varchar(50) NOT NULL DEFAULT 'active' AFTER date_last_payement");
    }
    if (!mj_member_column_exists($table_name, 'joined_date')) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN joined_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER status");
    }

    if (!mj_member_index_exists($table_name, 'idx_role')) {
        $wpdb->query("ALTER TABLE $table_name ADD KEY idx_role (role)");
    }
    if (!mj_member_index_exists($table_name, 'idx_guardian')) {
        $wpdb->query("ALTER TABLE $table_name ADD KEY idx_guardian (guardian_id)");
    }
    if (!mj_member_index_exists($table_name, 'idx_email')) {
        $wpdb->query("ALTER TABLE $table_name ADD KEY idx_email (email)");
    }

    mj_member_migrate_legacy_members($table_name);

    mj_member_drop_legacy_columns($table_name);
}

function mj_member_upgrade_to_2_1($wpdb) {
    $table_name = $wpdb->prefix . 'mj_members';

    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
        return;
    }

    $columns_to_add = array(
        'description_courte' => "ALTER TABLE $table_name ADD COLUMN description_courte varchar(255) DEFAULT NULL AFTER notes",
        'description_longue' => "ALTER TABLE $table_name ADD COLUMN description_longue longtext DEFAULT NULL AFTER description_courte",
        'wp_user_id' => "ALTER TABLE $table_name ADD COLUMN wp_user_id bigint(20) UNSIGNED DEFAULT NULL AFTER description_longue",
    );

    foreach ($columns_to_add as $column => $statement) {
        if (!mj_member_column_exists($table_name, $column)) {
            $wpdb->query($statement);
        }
    }

    if (!mj_member_index_exists($table_name, 'idx_wp_user')) {
        $wpdb->query("ALTER TABLE $table_name ADD KEY idx_wp_user (wp_user_id)");
    }
}

function mj_member_column_exists($table, $column) {
    global $wpdb;
    $result = $wpdb->get_var($wpdb->prepare(
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
        DB_NAME,
        $table,
        $column
    ));
    return !empty($result);
}

function mj_member_index_exists($table, $index) {
    global $wpdb;
    $result = $wpdb->get_var($wpdb->prepare(
        "SELECT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND INDEX_NAME = %s",
        DB_NAME,
        $table,
        $index
    ));
    return !empty($result);
}

function mj_member_migrate_legacy_members($table_name) {
    global $wpdb;

    if (!mj_member_column_exists($table_name, 'jeune_nom')) {
        return;
    }

    $needs_migration = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE (first_name = '' OR first_name IS NULL)");
    if (intval($needs_migration) === 0) {
        return;
    }

    $legacy_rows = $wpdb->get_results("SELECT * FROM $table_name", ARRAY_A);
    if (empty($legacy_rows)) {
        return;
    }

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $tutor_cache = array();

    foreach ($legacy_rows as $row) {
        $member_updates = array(
            'first_name' => sanitize_text_field($row['jeune_prenom']),
            'last_name' => sanitize_text_field($row['jeune_nom']),
            'email' => sanitize_email($row['jeune_email']),
            'phone' => sanitize_text_field($row['jeune_phone']),
            'role' => 'jeune',
            'status' => !empty($row['status']) ? sanitize_text_field($row['status']) : 'active',
            'requires_payment' => 1,
            'is_autonomous' => (empty($row['tutor_email']) ? 1 : 0),
            'address' => sanitize_text_field($row['tutor_address']),
            'city' => sanitize_text_field($row['tutor_city']),
            'postal_code' => sanitize_text_field($row['tutor_postal'])
        );

        $member_formats = array('%s','%s','%s','%s','%s','%s','%d','%d','%s','%s','%s');

        if (!empty($row['jeune_date_naissance']) && $row['jeune_date_naissance'] !== '0000-00-00') {
            $member_updates['birth_date'] = sanitize_text_field($row['jeune_date_naissance']);
            $member_formats[] = '%s';
        }

        $wpdb->update($table_name, $member_updates, array('id' => intval($row['id'])), $member_formats, array('%d'));

        if (!empty($row['tutor_email'])) {
            $tutor_key = strtolower(trim($row['tutor_email']));
            if (!isset($tutor_cache[$tutor_key])) {
                $existing_tutor_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM $table_name WHERE email = %s AND role = 'tuteur'",
                    sanitize_email($row['tutor_email'])
                ));

                if ($existing_tutor_id) {
                    $tutor_cache[$tutor_key] = intval($existing_tutor_id);
                } else {
                    $tutor_data = array(
                        'first_name' => sanitize_text_field($row['tutor_prenom']),
                        'last_name' => sanitize_text_field($row['tutor_nom']),
                        'email' => sanitize_email($row['tutor_email']),
                        'phone' => sanitize_text_field($row['tutor_phone']),
                        'role' => MjRoles::TUTEUR,
                        'status' => 'active',
                        'requires_payment' => 0,
                        'is_autonomous' => 1,
                        'address' => sanitize_text_field($row['tutor_address']),
                        'city' => sanitize_text_field($row['tutor_city']),
                        'postal_code' => sanitize_text_field($row['tutor_postal']),
                        'date_inscription' => !empty($row['date_inscription']) ? $row['date_inscription'] : current_time('mysql')
                    );

                    $tutor_formats = array('%s','%s','%s','%s','%s','%s','%d','%d','%s','%s','%s','%s');

                    $wpdb->insert($table_name, $tutor_data, $tutor_formats);
                    $tutor_cache[$tutor_key] = intval($wpdb->insert_id);
                }
            }

            if (!empty($tutor_cache[$tutor_key])) {
                $wpdb->update($table_name, array(
                    'guardian_id' => intval($tutor_cache[$tutor_key]),
                    'is_autonomous' => 0
                ), array('id' => intval($row['id'])), array('%d','%d'), array('%d'));
            }
        }
    }
}

function mj_member_drop_legacy_columns($table_name) {
    global $wpdb;
    $legacy_columns = array(
        'jeune_nom', 'jeune_prenom', 'jeune_email', 'jeune_phone', 'jeune_date_naissance',
        'tutor_nom', 'tutor_prenom', 'tutor_email', 'tutor_phone',
        'tutor_address', 'tutor_city', 'tutor_postal'
    );

    foreach ($legacy_columns as $column) {
        if (mj_member_column_exists($table_name, $column)) {
            $wpdb->query("ALTER TABLE $table_name DROP COLUMN $column");
        }
    }
}

function mj_member_seed_default_locations($wpdb, $locations_table) {
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $locations_table)) !== $locations_table) {
        return;
    }

    $existing_slugs = $wpdb->get_col("SELECT slug FROM $locations_table");
    if (!is_array($existing_slugs)) {
        $existing_slugs = array();
    }

    $existing_lookup = array();
    foreach ($existing_slugs as $slug) {
        $existing_lookup[strtolower((string) $slug)] = true;
    }

    $defaults = array(
        array(
            'slug' => 'la-bibi',
            'name' => 'La Bibi',
            'address_line' => 'Rue de la Station 12',
            'postal_code' => '4600',
            'city' => 'Pery',
            'country' => 'Belgique',
            'map_query' => 'La Bibi Pery',
        ),
        array(
            'slug' => 'mj-pery',
            'name' => 'Maison de Jeunes Pery',
            'address_line' => 'Rue des Jeunes 8',
            'postal_code' => '4600',
            'city' => 'Pery',
            'country' => 'Belgique',
            'map_query' => 'Maison de Jeunes Pery',
        ),
        array(
            'slug' => 'la-citadelle',
            'name' => 'La Citadelle',
            'address_line' => 'Parc de la Citadelle',
            'postal_code' => '4000',
            'city' => 'Liege',
            'country' => 'Belgique',
            'map_query' => 'Parc de la Citadelle Liege',
        ),
    );

    foreach ($defaults as $location) {
        $slug_key = strtolower($location['slug']);
        if (isset($existing_lookup[$slug_key])) {
            continue;
        }

        $wpdb->insert(
            $locations_table,
            array(
                'slug' => sanitize_title($location['slug']),
                'name' => sanitize_text_field($location['name']),
                'address_line' => sanitize_text_field($location['address_line']),
                'postal_code' => sanitize_text_field($location['postal_code']),
                'city' => sanitize_text_field($location['city']),
                'country' => sanitize_text_field($location['country']),
                'map_query' => sanitize_text_field($location['map_query']),
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
    }
}

function mj_member_seed_events_from_csv($wpdb) {
    $option_key = 'mj_member_events_seeded_from_csv';
    if (get_option($option_key, '') === '1') {
        return;
    }

    $events_table = mj_member_get_events_table_name();
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $events_table)) !== $events_table) {
        return;
    }

    $existing_events = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$events_table}");
    if ($existing_events > 0) {
        update_option($option_key, '1', false);
        return;
    }

    $csv_path = Config::path() . 'data/event.csv';
    if (!file_exists($csv_path) || !is_readable($csv_path)) {
        return;
    }

    $handle = fopen($csv_path, 'r');
    if (!$handle) {
        return;
    }

    $headers = fgetcsv($handle, 0, ',', '"');
    if (!$headers) {
        fclose($handle);
        return;
    }

    $header_map = array();
    foreach ($headers as $index => $label) {
        $normalized = strtolower(trim((string) $label));
        if ($normalized !== '') {
            $header_map[$normalized] = $index;
        }
    }

    $required = array('title', 'status', 'type', 'date_debut', 'date_fin');
    foreach ($required as $key) {
        if (!isset($header_map[$key])) {
            fclose($handle);
            return;
        }
    }

    $status_map = array(
        'STATUS_ACTIF' => MjMembers::STATUS_ACTIVE,
        'STATUS_BROUILLON' => MjMembers::STATUS_DRAFT,
        'STATUS_PASSE' => MjMembers::STATUS_PAST,
    );

    $type_map = array(
        'TYPE_STAGE' => MjMembers::TYPE_STAGE,
        'TYPE_SOIREE' => MjMembers::TYPE_SOIREE,
        'TYPE_SORTIE' => MjMembers::TYPE_SORTIE,
    );

    $normalize_datetime = static function ($value) {
        $value = trim((string) $value);
        if ($value === '' || strtoupper($value) === 'NULL') {
            return '';
        }

        $value = preg_replace('/^(\d{4}-\d{2}):(\d{2})(\s+.+)$/', '$1-$2$3', $value);

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return sanitize_text_field($value);
        }

        return date('Y-m-d H:i:s', $timestamp);
    };

    $inserted = 0;

    while (($row = fgetcsv($handle, 0, ',', '"')) !== false) {
        $raw_title = isset($header_map['title']) ? (string) $row[$header_map['title']] : '';
        $title = sanitize_text_field($raw_title);
        if ($title === '') {
            continue;
        }

        $status_key = isset($header_map['status']) ? strtoupper(trim((string) $row[$header_map['status']])) : '';
        $status = isset($status_map[$status_key]) ? $status_map[$status_key] : MjMembers::STATUS_DRAFT;

        $type_key = isset($header_map['type']) ? strtoupper(trim((string) $row[$header_map['type']])) : '';
        $type = isset($type_map[$type_key]) ? $type_map[$type_key] : MjMembers::TYPE_STAGE;

        $description_raw = isset($header_map['description']) ? (string) $row[$header_map['description']] : '';
        $description = $description_raw !== '' ? wp_kses_post($description_raw) : '';

        $cover_raw = isset($header_map['cover_id']) ? trim((string) $row[$header_map['cover_id']]) : '';
        $cover_id = ($cover_raw === '' || strtoupper($cover_raw) === 'NULL') ? 0 : (int) $cover_raw;

        $location_raw = isset($header_map['location_id']) ? trim((string) $row[$header_map['location_id']]) : '';
        $location_id = ($location_raw === '' || strtoupper($location_raw) === 'NULL') ? 0 : (int) $location_raw;

        $primary_raw = isset($header_map['animateur_id']) ? trim((string) $row[$header_map['animateur_id']]) : '';
        $primary_animateur = ($primary_raw === '' || strtoupper($primary_raw) === 'NULL') ? 0 : (int) $primary_raw;

        $animateur_ids_raw = isset($header_map['animateur_ids']) ? trim((string) $row[$header_map['animateur_ids']]) : '';
        $animateur_pool = array();
        if ($animateur_ids_raw !== '' && strtoupper($animateur_ids_raw) !== 'NULL') {
            $trimmed = trim($animateur_ids_raw, "[] ");
            if ($trimmed !== '') {
                $parts = preg_split('/\s*,\s*/', $trimmed);
                if (is_array($parts)) {
                    foreach ($parts as $candidate) {
                        $candidate = trim($candidate);
                        if ($candidate === '') {
                            continue;
                        }
                        $candidate_id = (int) $candidate;
                        if ($candidate_id > 0) {
                            $animateur_pool[$candidate_id] = $candidate_id;
                        }
                    }
                }
            }
        }

        $animateur_ids = array();
        if ($primary_animateur > 0) {
            $animateur_ids[$primary_animateur] = $primary_animateur;
        }
        foreach ($animateur_pool as $candidate_id) {
            if (!isset($animateur_ids[$candidate_id])) {
                $animateur_ids[$candidate_id] = $candidate_id;
            }
        }
        $animateur_list = array_values($animateur_ids);
        if ($primary_animateur <= 0 && !empty($animateur_list)) {
            $primary_animateur = (int) $animateur_list[0];
        }

        $age_min_raw = isset($header_map['age_min']) ? trim((string) $row[$header_map['age_min']]) : '';
        $age_min = ($age_min_raw === '' || strtoupper($age_min_raw) === 'NULL') ? 12 : (int) $age_min_raw;

        $age_max_raw = isset($header_map['age_max']) ? trim((string) $row[$header_map['age_max']]) : '';
        $age_max = ($age_max_raw === '' || strtoupper($age_max_raw) === 'NULL') ? 26 : (int) $age_max_raw;

        $start_raw = isset($header_map['date_debut']) ? trim((string) $row[$header_map['date_debut']]) : '';
        $end_raw = isset($header_map['date_fin']) ? trim((string) $row[$header_map['date_fin']]) : '';
        $deadline_raw = isset($header_map['date_fin_inscription']) ? trim((string) $row[$header_map['date_fin_inscription']]) : '';

        $start_date = $normalize_datetime($start_raw);
        $end_date = $normalize_datetime($end_raw);
        $deadline = $normalize_datetime($deadline_raw);

        if ($start_date === '' || $end_date === '') {
            continue;
        }

        $price_raw = isset($header_map['prix']) ? trim((string) $row[$header_map['prix']]) : '';
        $price_value = str_replace(',', '.', $price_raw);
        $price = $price_value === '' ? 0.0 : (float) $price_value;

        $event_data = array(
            'title' => $title,
            'status' => $status,
            'type' => $type,
            'description' => $description,
            'age_min' => $age_min,
            'age_max' => $age_max,
            'date_debut' => $start_date,
            'date_fin' => $end_date,
            'prix' => $price,
        );

        if ($cover_id > 0) {
            $event_data['cover_id'] = $cover_id;
        }

        if ($location_id > 0) {
            $event_data['location_id'] = $location_id;
        }

        if ($deadline !== '') {
            $event_data['date_fin_inscription'] = $deadline;
        }

        if ($primary_animateur > 0) {
            $event_data['animateur_id'] = $primary_animateur;
        }

        $created_at_raw = isset($header_map['created_at']) ? trim((string) $row[$header_map['created_at']]) : '';
        $created_at = $normalize_datetime($created_at_raw);

        $updated_at_raw = isset($header_map['updated_at']) ? trim((string) $row[$header_map['updated_at']]) : '';
        $updated_at = $normalize_datetime($updated_at_raw);

        $event_id = MjMembers::create($event_data);
        if (is_wp_error($event_id) || !$event_id) {
            continue;
        }

        if (!empty($animateur_list) && class_exists('MjEventAnimateurs')) {
            MjEventAnimateurs::sync_for_event($event_id, $animateur_list);
        } elseif ($primary_animateur > 0 && class_exists('MjEventAnimateurs')) {
            MjEventAnimateurs::sync_for_event($event_id, array($primary_animateur));
        }

        $dates_to_update = array();
        $date_formats = array();

        if ($created_at !== '' && strtotime($created_at) !== false) {
            $dates_to_update['created_at'] = $created_at;
            $date_formats[] = '%s';
        }

        if ($updated_at !== '' && strtotime($updated_at) !== false) {
            $dates_to_update['updated_at'] = $updated_at;
            $date_formats[] = '%s';
        }

        if (!empty($dates_to_update)) {
            $wpdb->update(
                $events_table,
                $dates_to_update,
                array('id' => $event_id),
                $date_formats,
                array('%d')
            );
        }

        $inserted++;
    }

    fclose($handle);

    if ($inserted > 0) {
        update_option($option_key, '1', false);
    }
}

function mj_member_upgrade_to_2_2($wpdb) {
    $events_table = $wpdb->prefix . 'mj_events';
    $registrations_table = $wpdb->prefix . 'mj_event_registrations';
    $charset_collate = $wpdb->get_charset_collate();

    if (!function_exists('dbDelta')) {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    }

    $sql_events = "CREATE TABLE $events_table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        title varchar(190) NOT NULL,
        status varchar(20) NOT NULL DEFAULT 'brouillon',
        type varchar(30) NOT NULL DEFAULT 'stage',
        cover_id bigint(20) unsigned DEFAULT NULL,
        description longtext DEFAULT NULL,
        age_min smallint(3) unsigned NOT NULL DEFAULT 12,
        age_max smallint(3) unsigned NOT NULL DEFAULT 26,
        date_debut datetime NOT NULL,
        date_fin datetime NOT NULL,
        date_fin_inscription datetime DEFAULT NULL,
        prix decimal(10,2) NOT NULL DEFAULT 0.00,
        allow_guardian_registration tinyint(1) NOT NULL DEFAULT 0,
        requires_validation tinyint(1) NOT NULL DEFAULT 1,
        free_participation tinyint(1) NOT NULL DEFAULT 0,
        registration_payload longtext DEFAULT NULL,
        location_id bigint(20) unsigned DEFAULT NULL,
        animateur_id bigint(20) unsigned DEFAULT NULL,
        article_id bigint(20) unsigned DEFAULT NULL,
        schedule_mode varchar(20) NOT NULL DEFAULT 'fixed',
        schedule_payload longtext DEFAULT NULL,
        occurrence_selection_mode varchar(20) NOT NULL DEFAULT 'member_choice',
        recurrence_until datetime DEFAULT NULL,
        capacity_total int unsigned NOT NULL DEFAULT 0,
        capacity_waitlist int unsigned NOT NULL DEFAULT 0,
        capacity_notify_threshold int unsigned NOT NULL DEFAULT 0,
        capacity_notified tinyint(1) NOT NULL DEFAULT 0,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY idx_status (status),
        KEY idx_schedule_mode (schedule_mode),
        KEY idx_date_debut (date_debut),
        KEY idx_location (location_id),
        KEY idx_animateur (animateur_id),
        KEY idx_article (article_id)
    ) $charset_collate;";

    $sql_registrations = "CREATE TABLE $registrations_table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        event_id bigint(20) unsigned NOT NULL,
        member_id mediumint(9) NOT NULL,
        guardian_id mediumint(9) DEFAULT NULL,
        statut varchar(20) NOT NULL DEFAULT 'en_attente',
        notes text DEFAULT NULL,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY idx_event (event_id),
        KEY idx_member (member_id),
        KEY idx_statut (statut)
    ) $charset_collate;";

    if (!mj_member_table_exists($events_table)) {
        dbDelta($sql_events);
    }

    if (!mj_member_table_exists($registrations_table)) {
        dbDelta($sql_registrations);
    }
}

function mj_member_upgrade_to_2_3($wpdb) {
    $events_table = $wpdb->prefix . 'mj_events';
    $locations_table = $wpdb->prefix . 'mj_event_locations';
    $event_animateurs_table = $wpdb->prefix . 'mj_event_animateurs';
    $charset_collate = $wpdb->get_charset_collate();

    if (!function_exists('dbDelta')) {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    }

    $sql_locations = "CREATE TABLE $locations_table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        slug varchar(120) NOT NULL,
        name varchar(190) NOT NULL,
        address_line varchar(190) DEFAULT '',
        postal_code varchar(30) DEFAULT '',
        city varchar(120) DEFAULT '',
        country varchar(120) DEFAULT '',
        icon varchar(60) DEFAULT '',
        latitude decimal(10,6) DEFAULT NULL,
        longitude decimal(10,6) DEFAULT NULL,
        map_query varchar(255) DEFAULT NULL,
        cover_id bigint(20) unsigned DEFAULT NULL,
        notes text DEFAULT NULL,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY uniq_slug (slug),
        KEY idx_name (name)
    ) $charset_collate;";

    dbDelta($sql_locations);
    dbDelta("CREATE TABLE $event_animateurs_table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        event_id bigint(20) unsigned NOT NULL,
        animateur_id bigint(20) unsigned NOT NULL,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY uniq_event_animateur (event_id, animateur_id),
        KEY idx_event (event_id),
        KEY idx_animateur (animateur_id)
    ) $charset_collate;");

    if (mj_member_table_exists($events_table) && !mj_member_column_exists($events_table, 'location_id')) {
        $wpdb->query("ALTER TABLE $events_table ADD COLUMN location_id bigint(20) unsigned DEFAULT NULL AFTER prix");
    }

    if (mj_member_table_exists($events_table) && !mj_member_column_exists($events_table, 'animateur_id')) {
        $wpdb->query("ALTER TABLE $events_table ADD COLUMN animateur_id bigint(20) unsigned DEFAULT NULL AFTER location_id");
    }

    if (mj_member_table_exists($events_table) && !mj_member_index_exists($events_table, 'idx_location')) {
        $wpdb->query("ALTER TABLE $events_table ADD KEY idx_location (location_id)");
    }

    if (mj_member_table_exists($events_table) && !mj_member_index_exists($events_table, 'idx_animateur')) {
        $wpdb->query("ALTER TABLE $events_table ADD KEY idx_animateur (animateur_id)");
    }

    if (mj_member_table_exists($events_table) && mj_member_table_exists($event_animateurs_table) && mj_member_column_exists($events_table, 'animateur_id')) {
        $existing_pairs = $wpdb->get_results("SELECT id AS event_id, animateur_id FROM $events_table WHERE animateur_id IS NOT NULL AND animateur_id > 0", ARRAY_A);
        if (!empty($existing_pairs)) {
            foreach ($existing_pairs as $pair) {
                $event_id = (int) $pair['event_id'];
                $animateur_id = (int) $pair['animateur_id'];
                if ($event_id <= 0 || $animateur_id <= 0) {
                    continue;
                }

                $wpdb->query($wpdb->prepare(
                    "INSERT IGNORE INTO $event_animateurs_table (event_id, animateur_id, created_at) VALUES (%d, %d, %s)",
                    $event_id,
                    $animateur_id,
                    current_time('mysql')
                ));
            }
        }
    }

    mj_member_seed_default_locations($wpdb, $locations_table);
    mj_member_seed_events_from_csv($wpdb);
}

function mj_member_upgrade_to_2_4($wpdb) {
    $events_table = $wpdb->prefix . 'mj_events';

    if (!mj_member_table_exists($events_table)) {
        return;
    }

    if (mj_member_column_exists($events_table, 'allow_guardian_registration')) {
        return;
    }

    $after_column = '';
    if (mj_member_column_exists($events_table, 'animateur_id')) {
        $after_column = ' AFTER animateur_id';
    } elseif (mj_member_column_exists($events_table, 'location_id')) {
        $after_column = ' AFTER location_id';
    } elseif (mj_member_column_exists($events_table, 'prix')) {
        $after_column = ' AFTER prix';
    }

    $sql = "ALTER TABLE $events_table ADD COLUMN allow_guardian_registration tinyint(1) NOT NULL DEFAULT 0" . $after_column;
    $wpdb->query($sql);
}

function mj_member_upgrade_to_2_23($wpdb) {
    $events_table = mj_member_get_events_table_name();

    if (!mj_member_table_exists($events_table)) {
        return;
    }

    if (mj_member_column_exists($events_table, 'requires_validation')) {
        return;
    }

    $after_column = 'allow_guardian_registration';
    if (!mj_member_column_exists($events_table, $after_column)) {
        if (mj_member_column_exists($events_table, 'prix')) {
            $after_column = 'prix';
        } elseif (mj_member_column_exists($events_table, 'date_fin_inscription')) {
            $after_column = 'date_fin_inscription';
        } else {
            $after_column = '';
        }
    }

    $after_clause = '';
    if ($after_column !== '' && mj_member_column_exists($events_table, $after_column)) {
        $after_clause = ' AFTER ' . $after_column;
    }

    $wpdb->query("ALTER TABLE {$events_table} ADD COLUMN requires_validation tinyint(1) NOT NULL DEFAULT 1{$after_clause}");
    $wpdb->query("UPDATE {$events_table} SET requires_validation = 1 WHERE requires_validation IS NULL");
}

function mj_member_upgrade_to_2_24($wpdb) {
    if (!function_exists('dbDelta')) {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    }

    $projects_table = mj_member_get_todo_projects_table_name();
    $todos_table = mj_member_get_todos_table_name();
    $charset_collate = $wpdb->get_charset_collate();

    $sql_projects = "CREATE TABLE {$projects_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        title varchar(190) NOT NULL DEFAULT '',
        slug varchar(190) NOT NULL DEFAULT '',
        description text DEFAULT NULL,
        color varchar(20) DEFAULT NULL,
        created_by bigint(20) unsigned NOT NULL DEFAULT 0,
        updated_by bigint(20) unsigned DEFAULT NULL,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY slug (slug),
        KEY created_by_idx (created_by)
    ) {$charset_collate};";

    dbDelta($sql_projects);

    $sql_todos = "CREATE TABLE {$todos_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        project_id bigint(20) unsigned DEFAULT NULL,
        title varchar(190) NOT NULL DEFAULT '',
        description text DEFAULT NULL,
        emoji varchar(32) DEFAULT NULL,
        status varchar(20) NOT NULL DEFAULT 'open',
        due_date date DEFAULT NULL,
        assigned_member_id bigint(20) unsigned DEFAULT NULL,
        assigned_by bigint(20) unsigned DEFAULT NULL,
        created_by bigint(20) unsigned NOT NULL DEFAULT 0,
        completed_at datetime DEFAULT NULL,
        completed_by bigint(20) unsigned DEFAULT NULL,
        position int unsigned NOT NULL DEFAULT 0,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY project_idx (project_id),
        KEY status_idx (status),
        KEY assigned_member_idx (assigned_member_id),
        KEY due_date_idx (due_date),
        KEY status_member_idx (status, assigned_member_id)
    ) {$charset_collate};";

    dbDelta($sql_todos);
}

function mj_member_upgrade_to_2_25($wpdb) {
    if (!function_exists('dbDelta')) {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    }

    $assignments_table = mj_member_get_todo_assignments_table_name();
    $todos_table = mj_member_get_todos_table_name();
    $charset_collate = $wpdb->get_charset_collate();

    $sql_assignments = "CREATE TABLE {$assignments_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        todo_id bigint(20) unsigned NOT NULL,
        member_id bigint(20) unsigned NOT NULL,
        assigned_by bigint(20) unsigned DEFAULT NULL,
        assigned_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY uniq_todo_member (todo_id, member_id),
        KEY idx_member (member_id),
        KEY idx_todo (todo_id)
    ) {$charset_collate};";

    dbDelta($sql_assignments);

    if (!mj_member_table_exists($assignments_table) || !mj_member_table_exists($todos_table)) {
        return;
    }

    $existing = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$assignments_table}");
    if ($existing > 0) {
        return;
    }

    $rows = $wpdb->get_results("SELECT id, assigned_member_id, assigned_by, created_by FROM {$todos_table} WHERE assigned_member_id IS NOT NULL AND assigned_member_id > 0", ARRAY_A);
    if (empty($rows)) {
        return;
    }

    foreach ($rows as $row) {
        $todo_id = (int) ($row['id'] ?? 0);
        $member_id = (int) ($row['assigned_member_id'] ?? 0);
        if ($todo_id <= 0 || $member_id <= 0) {
            continue;
        }

        $assigned_by = isset($row['assigned_by']) ? (int) $row['assigned_by'] : 0;
        if ($assigned_by <= 0 && isset($row['created_by'])) {
            $assigned_by = (int) $row['created_by'];
        }

        $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$assignments_table} (todo_id, member_id, assigned_by, assigned_at) VALUES (%d, %d, %d, %s)",
            $todo_id,
            $member_id,
            $assigned_by,
            current_time('mysql')
        ));
    }
}

function mj_member_upgrade_to_2_26($wpdb) {
    if (!function_exists('dbDelta')) {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    }

    $ideas_table = mj_member_get_ideas_table_name();
    $votes_table = mj_member_get_idea_votes_table_name();
    $charset_collate = $wpdb->get_charset_collate();

    $sql_ideas = "CREATE TABLE {$ideas_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        member_id bigint(20) unsigned NOT NULL,
        title varchar(180) NOT NULL DEFAULT '',
        content text NOT NULL,
        status varchar(20) NOT NULL DEFAULT 'published',
        vote_count int unsigned NOT NULL DEFAULT 0,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY member_idx (member_id),
        KEY status_idx (status),
        KEY vote_count_idx (vote_count),
        KEY created_idx (created_at)
    ) {$charset_collate};";

    $sql_votes = "CREATE TABLE {$votes_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        idea_id bigint(20) unsigned NOT NULL,
        member_id bigint(20) unsigned NOT NULL,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY uniq_vote (idea_id, member_id),
        KEY idea_idx (idea_id),
        KEY member_idx (member_id)
    ) {$charset_collate};";

    dbDelta($sql_ideas);
    dbDelta($sql_votes);

    if (mj_member_table_exists($ideas_table) && mj_member_table_exists($votes_table)) {
        $wpdb->query("UPDATE {$ideas_table} AS i SET vote_count = (
            SELECT COUNT(*) FROM {$votes_table} AS v WHERE v.idea_id = i.id
        )");
    }
}

function mj_member_upgrade_to_2_27($wpdb) {
    if (!function_exists('dbDelta')) {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    }

    $notes_table = mj_member_get_todo_notes_table_name();
    $charset_collate = $wpdb->get_charset_collate();

    $sql_notes = "CREATE TABLE {$notes_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        todo_id bigint(20) unsigned NOT NULL,
        member_id bigint(20) unsigned NOT NULL,
        wp_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
        content text NOT NULL,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY todo_idx (todo_id),
        KEY member_idx (member_id)
    ) {$charset_collate};";

    dbDelta($sql_notes);
}

function mj_member_upgrade_to_2_28($wpdb) {
    $hours_table = mj_member_get_hours_table_name();
    if (!$hours_table || !mj_member_table_exists($hours_table)) {
        return;
    }

    $projects_table = mj_member_get_todo_projects_table_name();
    if (!$projects_table || !mj_member_table_exists($projects_table)) {
        return;
    }

    if (!mj_member_column_exists($hours_table, 'project_id')) {
        $wpdb->query("ALTER TABLE {$hours_table} ADD COLUMN project_id bigint(20) unsigned DEFAULT NULL AFTER notes");
        if (!mj_member_index_exists($hours_table, 'idx_project_id')) {
            $wpdb->query("ALTER TABLE {$hours_table} ADD KEY idx_project_id (project_id)");
        }
    } elseif (!mj_member_index_exists($hours_table, 'idx_project_id')) {
        $wpdb->query("ALTER TABLE {$hours_table} ADD KEY idx_project_id (project_id)");
    }

    $needs_migration = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$hours_table} WHERE (project_id IS NULL OR project_id = 0) AND notes IS NOT NULL AND notes <> ''"
    );

    if ($needs_migration <= 0) {
        return;
    }

    $existing_projects = $wpdb->get_results(
        "SELECT id, title, slug FROM {$projects_table}",
        ARRAY_A
    );

    $project_map = array();
    $project_titles = array();

    $normalize_key = static function ($value): string {
        $value = is_string($value) ? trim($value) : '';
        if ($value === '') {
            return '';
        }

        $slug = sanitize_title($value);
        if ($slug !== '') {
            return $slug;
        }

        $sanitized = sanitize_text_field($value);
        return $sanitized !== '' ? strtolower($sanitized) : '';
    };

    foreach ((array) $existing_projects as $project_row) {
        $project_id = isset($project_row['id']) ? (int) $project_row['id'] : 0;
        if ($project_id <= 0) {
            continue;
        }

        $title = isset($project_row['title']) ? sanitize_text_field((string) $project_row['title']) : '';
        $slug = isset($project_row['slug']) ? sanitize_text_field((string) $project_row['slug']) : '';

        $project_titles[$project_id] = $title;

        $keys = array();
        $title_key = $normalize_key($title);
        if ($title_key !== '') {
            $keys[] = $title_key;
        }
        $slug_key = $normalize_key($slug);
        if ($slug_key !== '' && $slug_key !== $title_key) {
            $keys[] = $slug_key;
        }

        $fallback_source = $title !== '' ? $title : ('project-' . $project_id);
        $fallback_key = 'hash:' . md5($fallback_source);
        if ($fallback_key !== '' && !in_array($fallback_key, $keys, true)) {
            $keys[] = $fallback_key;
        }

        $keys = array_filter(array_unique($keys));

        foreach ($keys as $key) {
            if (!isset($project_map[$key])) {
                $project_map[$key] = $project_id;
            }
        }
    }

    $raw_labels = $wpdb->get_results(
        "SELECT DISTINCT notes FROM {$hours_table} WHERE (project_id IS NULL OR project_id = 0) AND notes IS NOT NULL AND notes <> ''",
        ARRAY_A
    );

    if (empty($raw_labels)) {
        return;
    }

    $default_color = '#2563eb';
    $now = current_time('mysql');

    foreach ($raw_labels as $label_row) {
        $raw_label = isset($label_row['notes']) ? (string) $label_row['notes'] : '';
        $candidate = trim($raw_label);
        if ($candidate === '') {
            continue;
        }

        $keys = array();
        $title_key = $normalize_key($candidate);
        if ($title_key !== '') {
            $keys[] = $title_key;
        }
        $label_key = $normalize_key(sanitize_text_field($candidate));
        if ($label_key !== '' && $label_key !== $title_key) {
            $keys[] = $label_key;
        }

        $fallback_key = 'hash:' . md5($candidate);
        if ($fallback_key !== '' && !in_array($fallback_key, $keys, true)) {
            $keys[] = $fallback_key;
        }

        $keys = array_filter(array_unique($keys));

        $project_id = 0;
        foreach ($keys as $key) {
            if (isset($project_map[$key])) {
                $project_id = (int) $project_map[$key];
                break;
            }
        }

        if ($project_id <= 0) {
            $base_slug = $title_key !== '' ? $title_key : sanitize_title(__('dossier', 'mj-member'));
            if ($base_slug === '') {
                $base_slug = 'dossier';
            }

            $slug = $base_slug;
            $suffix = 2;
            while ($wpdb->get_var($wpdb->prepare("SELECT id FROM {$projects_table} WHERE slug = %s LIMIT 1", $slug))) {
                $slug = $base_slug . '-' . $suffix;
                $suffix++;
            }

            $sanitized_title = sanitize_text_field($candidate);
            $inserted = $wpdb->insert(
                $projects_table,
                array(
                    'title' => $sanitized_title,
                    'slug' => $slug,
                    'color' => $default_color,
                    'created_by' => 0,
                    'updated_by' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ),
                array('%s', '%s', '%s', '%d', '%d', '%s', '%s')
            );

            if ($inserted === false) {
                continue;
            }

            $project_id = (int) $wpdb->insert_id;
            if ($project_id <= 0) {
                continue;
            }

            $project_titles[$project_id] = $sanitized_title;

            foreach ($keys as $key) {
                if ($key !== '') {
                    $project_map[$key] = $project_id;
                }
            }
        }

        if ($project_id <= 0) {
            continue;
        }

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$hours_table} SET project_id = %d WHERE (project_id IS NULL OR project_id = 0) AND notes = %s",
                $project_id,
                $raw_label
            )
        );
    }

    foreach ($project_titles as $project_id => $title) {
        $project_id = (int) $project_id;
        $title = is_string($title) ? trim($title) : '';
        if ($project_id <= 0 || $title === '') {
            continue;
        }

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$hours_table} SET notes = %s WHERE project_id = %d",
                $title,
                $project_id
            )
        );
    }
}

function mj_member_upgrade_to_2_29($wpdb) {
    $legacy_table = $wpdb->prefix . 'mj_todo_projects';
    $target_table = $wpdb->prefix . 'mj_projects';

    if (mj_member_table_exists($target_table)) {
        return;
    }

    if (!mj_member_table_exists($legacy_table)) {
        return;
    }

    $legacy_name = str_replace('`', '', $legacy_table);
    $target_name = str_replace('`', '', $target_table);

    $wpdb->query(sprintf('ALTER TABLE `%s` RENAME TO `%s`', esc_sql($legacy_name), esc_sql($target_name)));
}

function mj_member_upgrade_to_2_30($wpdb) {
    if (!function_exists('dbDelta')) {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    }

    $table = mj_member_get_event_occurrences_table_name();
    if ($table === '') {
        return;
    }

    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE {$table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        event_id bigint(20) unsigned NOT NULL,
        start_at datetime NOT NULL,
        end_at datetime NOT NULL,
        status varchar(20) NOT NULL DEFAULT 'active',
        source varchar(40) NOT NULL DEFAULT 'manual',
        meta longtext DEFAULT NULL,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_event_start (event_id, start_at),
        KEY idx_event_status (event_id, status)
    ) {$charset_collate};";

    dbDelta($sql);
}

function mj_member_upgrade_to_2_31($wpdb) {
    if (!function_exists('dbDelta')) {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    }

    $table = mj_member_get_todo_media_table_name();
    if ($table === '') {
        return;
    }

    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE {$table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        todo_id bigint(20) unsigned NOT NULL,
        attachment_id bigint(20) unsigned NOT NULL,
        member_id bigint(20) unsigned DEFAULT NULL,
        wp_user_id bigint(20) unsigned DEFAULT NULL,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY uniq_todo_attachment (todo_id, attachment_id),
        KEY idx_todo (todo_id),
        KEY idx_attachment (attachment_id),
        KEY idx_member (member_id)
    ) {$charset_collate};";

    dbDelta($sql);
}

function mj_member_upgrade_to_2_32($wpdb) {
    $events_table = mj_member_get_events_table_name();

    if (!$events_table || !mj_member_table_exists($events_table)) {
        return;
    }

    if (!mj_member_column_exists($events_table, 'occurrence_selection_mode')) {
        $wpdb->query("ALTER TABLE {$events_table} ADD COLUMN occurrence_selection_mode varchar(20) NOT NULL DEFAULT 'member_choice' AFTER schedule_payload");
    }

    $wpdb->query("UPDATE {$events_table} SET occurrence_selection_mode = 'member_choice' WHERE occurrence_selection_mode IS NULL OR occurrence_selection_mode = ''");
}

function mj_member_upgrade_to_2_33($wpdb) {
    $events_table = mj_member_get_events_table_name();

    if (!$events_table || !mj_member_table_exists($events_table)) {
        return;
    }

    $has_free_participation = mj_member_column_exists($events_table, 'free_participation');

    if (!$has_free_participation && !mj_member_column_exists($events_table, 'registration_mode')) {
        $wpdb->query("ALTER TABLE {$events_table} ADD COLUMN registration_mode varchar(40) NOT NULL DEFAULT 'participant' AFTER requires_validation");
    }

    $after_column = 'requires_validation';
    if ($has_free_participation) {
        $after_column = 'free_participation';
    } elseif (mj_member_column_exists($events_table, 'registration_mode')) {
        $after_column = 'registration_mode';
    }

    if (!mj_member_column_exists($events_table, 'registration_payload')) {
        $wpdb->query("ALTER TABLE {$events_table} ADD COLUMN registration_payload longtext DEFAULT NULL AFTER {$after_column}");
    }

    if (!$has_free_participation && mj_member_column_exists($events_table, 'registration_mode')) {
        $wpdb->query("UPDATE {$events_table} SET registration_mode = 'participant' WHERE registration_mode IS NULL OR registration_mode = ''");
    }
}

function mj_member_upgrade_to_2_34($wpdb) {
    $events_table = mj_member_get_events_table_name();

    if (!$events_table || !mj_member_table_exists($events_table)) {
        return;
    }

    $has_free_participation = mj_member_column_exists($events_table, 'free_participation');
    $has_registration_mode = mj_member_column_exists($events_table, 'registration_mode');

    if (!$has_free_participation) {
        $after_column = 'requires_validation';
        if ($has_registration_mode) {
            $after_column = 'registration_mode';
        }

        $wpdb->query("ALTER TABLE {$events_table} ADD COLUMN free_participation tinyint(1) NOT NULL DEFAULT 0 AFTER {$after_column}");
        $has_free_participation = mj_member_column_exists($events_table, 'free_participation');
    }

    if ($has_free_participation && $has_registration_mode) {
        $default_free_registration_modes = array('attendance', 'attendance_free', 'free_participation', 'free', 'open_access', 'no_registration', 'optional', 'none', 'libre', 'presence');
        $placeholders = implode(',', array_fill(0, count($default_free_registration_modes), '%s'));
        if ($placeholders !== '') {
            $query_args = $default_free_registration_modes;
            array_unshift($query_args, "UPDATE {$events_table} SET free_participation = 1 WHERE registration_mode IN ({$placeholders})");
            $prepared = call_user_func_array(array($wpdb, 'prepare'), $query_args);
            if ($prepared !== false && $prepared !== null) {
                $wpdb->query($prepared);
            }
        }
    }

    if ($has_free_participation) {
        $wpdb->query("UPDATE {$events_table} SET free_participation = 0 WHERE free_participation IS NULL");
    }

    if ($has_registration_mode) {
        $wpdb->query("ALTER TABLE {$events_table} DROP COLUMN registration_mode");
    }
}

/**
 * Ajoute la colonne work_schedule pour stocker l'emploi du temps contractuel des membres staff.
 */
function mj_member_upgrade_to_2_35($wpdb) {
    $members_table = $wpdb->prefix . 'mj_members';

    if (!mj_member_table_exists($members_table)) {
        return;
    }

    if (!mj_member_column_exists($members_table, 'work_schedule')) {
        $wpdb->query("ALTER TABLE {$members_table} ADD COLUMN work_schedule longtext DEFAULT NULL AFTER description_longue");
    }
}

function mj_member_upgrade_to_2_36($wpdb) {
    $locations_table = mj_member_get_event_locations_table_name();

    if (!$locations_table || !mj_member_table_exists($locations_table)) {
        return;
    }

    if (mj_member_column_exists($locations_table, 'icon')) {
        return;
    }

    $after_column = '';
    foreach (array('country', 'city', 'name') as $candidate) {
        if (mj_member_column_exists($locations_table, $candidate)) {
            $after_column = ' AFTER ' . $candidate;
            break;
        }
    }

    $wpdb->query("ALTER TABLE {$locations_table} ADD COLUMN icon varchar(60) DEFAULT ''{$after_column}");
    $wpdb->query("UPDATE {$locations_table} SET icon = '' WHERE icon IS NULL");
}

function mj_member_upgrade_to_2_37($wpdb) {
    $events_table = mj_member_get_events_table_name();

    if (!$events_table || !mj_member_table_exists($events_table)) {
        return;
    }

    if (mj_member_column_exists($events_table, 'emoji')) {
        return;
    }

    $after_clause = '';
    if (mj_member_column_exists($events_table, 'accent_color')) {
        $after_clause = ' AFTER accent_color';
    }

    $wpdb->query("ALTER TABLE {$events_table} ADD COLUMN emoji varchar(32) DEFAULT ''{$after_clause}");
    $wpdb->query("UPDATE {$events_table} SET emoji = '' WHERE emoji IS NULL");
}

function mj_member_upgrade_to_2_38($wpdb) {
    $events_table = mj_member_get_events_table_name();

    if (!$events_table || !mj_member_table_exists($events_table)) {
        return;
    }

    mj_member_convert_table_to_utf8mb4($events_table);
}

function mj_member_upgrade_to_2_39($wpdb) {
    $todos_table = mj_member_get_todos_table_name();

    if (!$todos_table || !mj_member_table_exists($todos_table)) {
        return;
    }

    if (!mj_member_column_exists($todos_table, 'emoji')) {
        $after_clause = '';
        if (mj_member_column_exists($todos_table, 'description')) {
            $after_clause = ' AFTER description';
        } elseif (mj_member_column_exists($todos_table, 'title')) {
            $after_clause = ' AFTER title';
        }

        $wpdb->query("ALTER TABLE {$todos_table} ADD COLUMN emoji varchar(32) DEFAULT NULL{$after_clause}");
    }

    mj_member_convert_table_to_utf8mb4($todos_table);
}

function mj_member_upgrade_to_2_40($wpdb) {
    if (!function_exists('dbDelta')) {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    }

    $target_table = $wpdb->prefix . 'mj_event_date_occurrences';
    $legacy_tables = array(
        $wpdb->prefix . 'mj_event_occurrences',
        $wpdb->prefix . 'event_occurrences',
        $wpdb->prefix . 'event_date_occurrences',
    );

    // Rename legacy tables to the new canonical name when possible.
    if (!mj_member_table_exists($target_table)) {
        foreach ($legacy_tables as $legacy_table) {
            if ($legacy_table === $target_table) {
                continue;
            }

            if (mj_member_table_exists($legacy_table)) {
                $wpdb->query(sprintf('ALTER TABLE `%s` RENAME TO `%s`', esc_sql($legacy_table), esc_sql($target_table)));
                break;
            }
        }
    }

    $charset_collate = $wpdb->get_charset_collate();

    if (!mj_member_table_exists($target_table)) {
        $sql = "CREATE TABLE {$target_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_id bigint(20) unsigned NOT NULL,
            start_at datetime NOT NULL,
            end_at datetime NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            source varchar(40) NOT NULL DEFAULT 'manual',
            meta longtext DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_event_start (event_id, start_at),
            KEY idx_event_status (event_id, status)
        ) {$charset_collate};";

        dbDelta($sql);
    }

    if (mj_member_table_exists($target_table)) {
        if (!mj_member_column_exists($target_table, 'status')) {
            $wpdb->query("ALTER TABLE {$target_table} ADD COLUMN status varchar(20) NOT NULL DEFAULT 'active' AFTER end_at");
        }

        if (!mj_member_column_exists($target_table, 'source')) {
            $wpdb->query("ALTER TABLE {$target_table} ADD COLUMN source varchar(40) NOT NULL DEFAULT 'manual' AFTER status");
        }

        if (!mj_member_column_exists($target_table, 'created_at')) {
            $wpdb->query("ALTER TABLE {$target_table} ADD COLUMN created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER meta");
        }

        if (!mj_member_column_exists($target_table, 'updated_at')) {
            $wpdb->query("ALTER TABLE {$target_table} ADD COLUMN updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at");
        }

        // Ensure status has only canonical values.
        $wpdb->query($wpdb->prepare(
            "UPDATE {$target_table} SET status = %s WHERE status IS NULL OR status = ''",
            'active'
        ));

        if (!mj_member_index_exists($target_table, 'idx_event_status')) {
            $wpdb->query("ALTER TABLE {$target_table} ADD KEY idx_event_status (event_id, status)");
        }

        if (mj_member_index_exists($target_table, 'idx_event_source')) {
            $wpdb->query("ALTER TABLE {$target_table} DROP INDEX idx_event_source");
        }
    }

    // Ensure event scheduling columns remain available for the calendar flows.
    $events_table = mj_member_get_events_table_name();
    if ($events_table && mj_member_table_exists($events_table)) {
        if (!mj_member_column_exists($events_table, 'schedule_mode')) {
            $wpdb->query("ALTER TABLE {$events_table} ADD COLUMN schedule_mode varchar(20) NOT NULL DEFAULT 'fixed' AFTER allow_guardian_registration");
        }

        if (!mj_member_column_exists($events_table, 'schedule_payload')) {
            $wpdb->query("ALTER TABLE {$events_table} ADD COLUMN schedule_payload longtext DEFAULT NULL AFTER schedule_mode");
        }

        if (!mj_member_index_exists($events_table, 'idx_schedule_mode')) {
            $wpdb->query("ALTER TABLE {$events_table} ADD KEY idx_schedule_mode (schedule_mode)");
        }
    }
}

function mj_member_upgrade_to_2_41($wpdb) {
    $members_table = $wpdb->prefix . 'mj_members';

    if (!mj_member_table_exists($members_table)) {
        return;
    }

    if (!mj_member_column_exists($members_table, 'school')) {
        $wpdb->query("ALTER TABLE {$members_table} ADD COLUMN school varchar(150) DEFAULT NULL AFTER postal_code");
    }

    if (!mj_member_column_exists($members_table, 'birth_country')) {
        $after_column = mj_member_column_exists($members_table, 'school') ? 'school' : 'postal_code';
        $after_sql = esc_sql($after_column);
        $wpdb->query("ALTER TABLE {$members_table} ADD COLUMN birth_country varchar(120) DEFAULT NULL AFTER {$after_sql}");
    }

    if (!mj_member_column_exists($members_table, 'nationality')) {
        $after_column = 'postal_code';
        if (mj_member_column_exists($members_table, 'birth_country')) {
            $after_column = 'birth_country';
        } elseif (mj_member_column_exists($members_table, 'school')) {
            $after_column = 'school';
        }
        $after_sql = esc_sql($after_column);
        $wpdb->query("ALTER TABLE {$members_table} ADD COLUMN nationality varchar(120) DEFAULT NULL AFTER {$after_sql}");
    }

    if (!mj_member_column_exists($members_table, 'why_mj')) {
        $after_column = mj_member_column_exists($members_table, 'description_longue') ? 'description_longue' : 'description_courte';
        $after_sql = esc_sql($after_column);
        $wpdb->query("ALTER TABLE {$members_table} ADD COLUMN why_mj text DEFAULT NULL AFTER {$after_sql}");
    }

    if (!mj_member_column_exists($members_table, 'how_mj')) {
        if (mj_member_column_exists($members_table, 'why_mj')) {
            $after_column = 'why_mj';
        } elseif (mj_member_column_exists($members_table, 'description_longue')) {
            $after_column = 'description_longue';
        } else {
            $after_column = 'description_courte';
        }
        $after_sql = esc_sql($after_column);
        $wpdb->query("ALTER TABLE {$members_table} ADD COLUMN how_mj text DEFAULT NULL AFTER {$after_sql}");
    }
}

function mj_member_upgrade_to_2_42($wpdb) {
    $messages_table = $wpdb->prefix . 'mj_contact_messages';
    $recipients_table = mj_member_get_contact_message_recipients_table_name();

    if (!function_exists('dbDelta')) {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    }

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$recipients_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        message_id bigint(20) unsigned NOT NULL,
        recipient_type varchar(30) NOT NULL,
        recipient_reference bigint(20) unsigned DEFAULT 0,
        recipient_label varchar(190) DEFAULT '',
        member_id bigint(20) unsigned DEFAULT NULL,
        user_id bigint(20) unsigned DEFAULT NULL,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY idx_message (message_id),
        KEY idx_recipient (recipient_type, recipient_reference),
        KEY idx_member (member_id),
        KEY idx_user (user_id)
    ) {$charset_collate};";

    dbDelta($sql);

    if (!mj_member_table_exists($messages_table) || !mj_member_table_exists($recipients_table)) {
        return;
    }

    $existing = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$recipients_table}");
    if ($existing > 0) {
        return;
    }

    $members_table = $wpdb->prefix . 'mj_members';
    $members_table_exists = mj_member_table_exists($members_table);
    $member_cache = array();

    $resolve_member = static function ($member_id) use (&$member_cache, $members_table_exists, $members_table, $wpdb) {
        $member_id = (int) $member_id;
        if ($member_id <= 0 || !$members_table_exists) {
            return array('member_id' => 0, 'user_id' => 0);
        }

        if (isset($member_cache[$member_id])) {
            return $member_cache[$member_id];
        }

        $row = $wpdb->get_row($wpdb->prepare("SELECT id, wp_user_id FROM {$members_table} WHERE id = %d", $member_id));
        $resolved_member_id = ($row && isset($row->id)) ? (int) $row->id : 0;
        $resolved_user_id = ($row && isset($row->wp_user_id) && (int) $row->wp_user_id > 0) ? (int) $row->wp_user_id : 0;

        $member_cache[$member_id] = array(
            'member_id' => $resolved_member_id,
            'user_id' => $resolved_user_id,
        );

        return $member_cache[$member_id];
    };

    $batch = 200;
    $offset = 0;

    while (true) {
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, target_type, target_reference, target_label, meta FROM {$messages_table} ORDER BY id ASC LIMIT %d OFFSET %d",
                (int) $batch,
                (int) $offset
            )
        );

        if (empty($rows)) {
            break;
        }

        foreach ($rows as $row) {
            if (!isset($row->id)) {
                continue;
            }

            $recipient_map = array();

            $append_recipient = static function ($type, $reference, $label = '') use (&$recipient_map) {
                $type = sanitize_key((string) $type);
                if ($type === '') {
                    return;
                }

                $reference = (int) $reference;
                if ($reference < 0) {
                    $reference = 0;
                }

                $label = $label !== '' ? sanitize_text_field((string) $label) : '';
                $map_key = $type . '|' . $reference;

                if (!isset($recipient_map[$map_key])) {
                    $recipient_map[$map_key] = array(
                        'type' => $type,
                        'reference' => $reference,
                        'label' => $label,
                    );
                } else {
                    if ($recipient_map[$map_key]['label'] === '' && $label !== '') {
                        $recipient_map[$map_key]['label'] = $label;
                    }
                }
            };

            if (!empty($row->meta)) {
                $decoded_meta = json_decode((string) $row->meta, true);
                if (is_array($decoded_meta) && !empty($decoded_meta['recipient_keys'])) {
                    $keys = explode('|', (string) $decoded_meta['recipient_keys']);
                    foreach ($keys as $raw_key) {
                        $raw_key = trim((string) $raw_key);
                        if ($raw_key === '') {
                            continue;
                        }

                        $type = $raw_key;
                        $reference = 0;

                        if (strpos($raw_key, ':') !== false) {
                            list($raw_type, $raw_reference) = explode(':', $raw_key, 2);
                            $type = $raw_type;
                            $reference = (int) $raw_reference;
                        }

                        $append_recipient($type, $reference);
                    }
                }
            }

            $base_type = isset($row->target_type) ? sanitize_key((string) $row->target_type) : '';
            if ($base_type === '') {
                $base_type = 'all';
            }

            $base_reference = isset($row->target_reference) ? (int) $row->target_reference : 0;
            if ($base_reference < 0) {
                $base_reference = 0;
            }

            $base_label = isset($row->target_label) ? sanitize_text_field((string) $row->target_label) : '';
            $append_recipient($base_type, $base_reference, $base_label);

            if (empty($recipient_map)) {
                continue;
            }

            $wpdb->delete($recipients_table, array('message_id' => (int) $row->id), array('%d'));

            foreach ($recipient_map as $recipient_entry) {
                $member_id = 0;
                $user_id = 0;

                if ($recipient_entry['reference'] > 0 && in_array($recipient_entry['type'], array('animateur', 'coordinateur', 'member'), true)) {
                    $resolved = $resolve_member($recipient_entry['reference']);
                    if (!empty($resolved['member_id'])) {
                        $member_id = (int) $resolved['member_id'];
                    }
                    if (!empty($resolved['user_id'])) {
                        $user_id = (int) $resolved['user_id'];
                    }
                }

                $wpdb->insert(
                    $recipients_table,
                    array(
                        'message_id' => (int) $row->id,
                        'recipient_type' => $recipient_entry['type'],
                        'recipient_reference' => (int) $recipient_entry['reference'],
                        'recipient_label' => $recipient_entry['label'],
                        'member_id' => $member_id > 0 ? $member_id : null,
                        'user_id' => $user_id > 0 ? $user_id : null,
                    ),
                    array('%d', '%s', '%d', '%s', '%d', '%d')
                );
            }
        }

        $offset += $batch;
    }
}

function mj_member_upgrade_to_2_43($wpdb) {
    $notifications_table = mj_member_get_notifications_table_name();
    $recipients_table = mj_member_get_notification_recipients_table_name();

    if (!function_exists('dbDelta')) {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    }

    $charset_collate = $wpdb->get_charset_collate();

    $sql_notifications = "CREATE TABLE {$notifications_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        uid varchar(64) NOT NULL,
        type varchar(60) NOT NULL,
        status varchar(20) NOT NULL DEFAULT 'published',
        priority smallint(5) unsigned NOT NULL DEFAULT 0,
        title varchar(255) NOT NULL DEFAULT '',
        excerpt text DEFAULT NULL,
        payload longtext DEFAULT NULL,
        url varchar(500) DEFAULT NULL,
        context varchar(120) DEFAULT NULL,
        source varchar(120) DEFAULT NULL,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        expires_at datetime DEFAULT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY uid (uid),
        KEY idx_type (type),
        KEY idx_status (status),
        KEY idx_priority (priority),
        KEY idx_created_at (created_at),
        KEY idx_expires_at (expires_at)
    ) {$charset_collate};";

    dbDelta($sql_notifications);

    $sql_recipients = "CREATE TABLE {$recipients_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        notification_id bigint(20) unsigned NOT NULL,
        member_id bigint(20) unsigned DEFAULT NULL,
        user_id bigint(20) unsigned DEFAULT NULL,
        role varchar(30) DEFAULT NULL,
        status varchar(20) NOT NULL DEFAULT 'unread',
        read_at datetime DEFAULT NULL,
        delivered_at datetime DEFAULT NULL,
        extra_meta longtext DEFAULT NULL,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY idx_notification (notification_id),
        KEY idx_member_status (member_id, status),
        KEY idx_user_status (user_id, status),
        KEY idx_role_status (role, status),
        CONSTRAINT fk_mj_notification_recipient FOREIGN KEY (notification_id) REFERENCES {$notifications_table} (id) ON DELETE CASCADE
    ) {$charset_collate};";

    dbDelta($sql_recipients);

    if (mj_member_table_exists($notifications_table)) {
        mj_member_convert_table_to_utf8mb4($notifications_table);

        if (!mj_member_column_exists($notifications_table, 'uid')) {
            $wpdb->query("ALTER TABLE {$notifications_table} ADD COLUMN uid varchar(64) NOT NULL AFTER id");
        }

        if (!mj_member_index_exists($notifications_table, 'uid')) {
            $wpdb->query("ALTER TABLE {$notifications_table} ADD UNIQUE KEY uid (uid)");
        }

        if (!mj_member_index_exists($notifications_table, 'idx_type')) {
            $wpdb->query("ALTER TABLE {$notifications_table} ADD KEY idx_type (type)");
        }

        if (!mj_member_index_exists($notifications_table, 'idx_status')) {
            $wpdb->query("ALTER TABLE {$notifications_table} ADD KEY idx_status (status)");
        }

        if (!mj_member_index_exists($notifications_table, 'idx_priority')) {
            $wpdb->query("ALTER TABLE {$notifications_table} ADD KEY idx_priority (priority)");
        }

        if (!mj_member_index_exists($notifications_table, 'idx_created_at')) {
            $wpdb->query("ALTER TABLE {$notifications_table} ADD KEY idx_created_at (created_at)");
        }

        if (!mj_member_index_exists($notifications_table, 'idx_expires_at')) {
            $wpdb->query("ALTER TABLE {$notifications_table} ADD KEY idx_expires_at (expires_at)");
        }
    }

    if (mj_member_table_exists($recipients_table)) {
        mj_member_convert_table_to_utf8mb4($recipients_table);

        if (!mj_member_column_exists($recipients_table, 'status')) {
            $wpdb->query("ALTER TABLE {$recipients_table} ADD COLUMN status varchar(20) NOT NULL DEFAULT 'unread' AFTER role");
        }

        if (!mj_member_index_exists($recipients_table, 'idx_notification')) {
            $wpdb->query("ALTER TABLE {$recipients_table} ADD KEY idx_notification (notification_id)");
        }

        if (!mj_member_index_exists($recipients_table, 'idx_member_status')) {
            $wpdb->query("ALTER TABLE {$recipients_table} ADD KEY idx_member_status (member_id, status)");
        }

        if (!mj_member_index_exists($recipients_table, 'idx_user_status')) {
            $wpdb->query("ALTER TABLE {$recipients_table} ADD KEY idx_user_status (user_id, status)");
        }

        if (!mj_member_index_exists($recipients_table, 'idx_role_status')) {
            $wpdb->query("ALTER TABLE {$recipients_table} ADD KEY idx_role_status (role, status)");
        }

        $has_fk = $wpdb->get_var($wpdb->prepare(
            "SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND REFERENCED_TABLE_NAME = %s",
            DB_NAME,
            $recipients_table,
            $notifications_table
        ));

        if (empty($has_fk)) {
            $wpdb->query("ALTER TABLE {$recipients_table} ADD CONSTRAINT fk_mj_notification_recipient FOREIGN KEY (notification_id) REFERENCES {$notifications_table} (id) ON DELETE CASCADE");
        }
    }
}

function mj_member_upgrade_to_2_44($wpdb) {
    $badges_table = mj_member_get_badges_table_name();
    $member_badges_table = mj_member_get_member_badges_table_name();

    if (!function_exists('dbDelta')) {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    }

    $charset_collate = $wpdb->get_charset_collate();

    $sql_badges = "CREATE TABLE {$badges_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        slug varchar(120) NOT NULL,
        label varchar(190) NOT NULL,
        summary text DEFAULT NULL,
        description longtext DEFAULT NULL,
        criteria longtext DEFAULT NULL,
        prompt longtext DEFAULT NULL,
        icon varchar(60) DEFAULT NULL,
        xp int unsigned NOT NULL DEFAULT 0,
        display_order smallint(5) unsigned NOT NULL DEFAULT 0,
        status varchar(20) NOT NULL DEFAULT 'active',
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY slug (slug),
        KEY idx_status (status),
        KEY idx_display_order (display_order)
    ) {$charset_collate};";

    dbDelta($sql_badges);

    $sql_member_badges = "CREATE TABLE {$member_badges_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        badge_id bigint(20) unsigned NOT NULL,
        member_id bigint(20) unsigned NOT NULL,
        awarded_by_user_id bigint(20) unsigned DEFAULT NULL,
        awarded_by_member_id bigint(20) unsigned DEFAULT NULL,
        awarded_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        status varchar(20) NOT NULL DEFAULT 'awarded',
        notes text DEFAULT NULL,
        evidence longtext DEFAULT NULL,
        revoked_at datetime DEFAULT NULL,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY idx_member_badge_unique (member_id, badge_id),
        KEY idx_badge (badge_id),
        KEY idx_member (member_id),
        KEY idx_status (status),
        KEY idx_awarded_by_user (awarded_by_user_id),
        KEY idx_awarded_by_member (awarded_by_member_id),
        KEY idx_awarded_at (awarded_at),
        CONSTRAINT fk_mj_member_badges_badge FOREIGN KEY (badge_id) REFERENCES {$badges_table} (id) ON DELETE CASCADE
    ) {$charset_collate};";

    dbDelta($sql_member_badges);

    if (mj_member_table_exists($badges_table)) {
        mj_member_convert_table_to_utf8mb4($badges_table);

        $badge_columns = array(
            'slug' => "ALTER TABLE {$badges_table} ADD COLUMN slug varchar(120) NOT NULL AFTER id",
            'label' => "ALTER TABLE {$badges_table} ADD COLUMN label varchar(190) NOT NULL AFTER slug",
            'summary' => "ALTER TABLE {$badges_table} ADD COLUMN summary text DEFAULT NULL AFTER label",
            'description' => "ALTER TABLE {$badges_table} ADD COLUMN description longtext DEFAULT NULL AFTER summary",
            'criteria' => "ALTER TABLE {$badges_table} ADD COLUMN criteria longtext DEFAULT NULL AFTER description",
            'prompt' => "ALTER TABLE {$badges_table} ADD COLUMN prompt longtext DEFAULT NULL AFTER criteria",
            'icon' => "ALTER TABLE {$badges_table} ADD COLUMN icon varchar(60) DEFAULT NULL AFTER prompt",
            'display_order' => "ALTER TABLE {$badges_table} ADD COLUMN display_order smallint(5) unsigned NOT NULL DEFAULT 0 AFTER icon",
            'status' => "ALTER TABLE {$badges_table} ADD COLUMN status varchar(20) NOT NULL DEFAULT 'active' AFTER display_order",
            'created_at' => "ALTER TABLE {$badges_table} ADD COLUMN created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER status",
            'updated_at' => "ALTER TABLE {$badges_table} ADD COLUMN updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at",
        );

        foreach ($badge_columns as $column => $statement) {
            if (!mj_member_column_exists($badges_table, $column)) {
                $wpdb->query($statement);
            }
        }

        if (!mj_member_index_exists($badges_table, 'slug')) {
            $wpdb->query("ALTER TABLE {$badges_table} ADD UNIQUE KEY slug (slug)");
        }

        if (!mj_member_index_exists($badges_table, 'idx_status')) {
            $wpdb->query("ALTER TABLE {$badges_table} ADD KEY idx_status (status)");
        }

        if (!mj_member_index_exists($badges_table, 'idx_display_order')) {
            $wpdb->query("ALTER TABLE {$badges_table} ADD KEY idx_display_order (display_order)");
        }

        $wpdb->query("UPDATE {$badges_table} SET status = 'active' WHERE status IS NULL OR status = ''");
    }

    if (mj_member_table_exists($member_badges_table)) {
        mj_member_convert_table_to_utf8mb4($member_badges_table);

        $member_badge_columns = array(
            'badge_id' => "ALTER TABLE {$member_badges_table} ADD COLUMN badge_id bigint(20) unsigned NOT NULL AFTER id",
            'member_id' => "ALTER TABLE {$member_badges_table} ADD COLUMN member_id bigint(20) unsigned NOT NULL AFTER badge_id",
            'awarded_by_user_id' => "ALTER TABLE {$member_badges_table} ADD COLUMN awarded_by_user_id bigint(20) unsigned DEFAULT NULL AFTER member_id",
            'awarded_by_member_id' => "ALTER TABLE {$member_badges_table} ADD COLUMN awarded_by_member_id bigint(20) unsigned DEFAULT NULL AFTER awarded_by_user_id",
            'awarded_at' => "ALTER TABLE {$member_badges_table} ADD COLUMN awarded_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER awarded_by_member_id",
            'status' => "ALTER TABLE {$member_badges_table} ADD COLUMN status varchar(20) NOT NULL DEFAULT 'awarded' AFTER awarded_at",
            'notes' => "ALTER TABLE {$member_badges_table} ADD COLUMN notes text DEFAULT NULL AFTER status",
            'evidence' => "ALTER TABLE {$member_badges_table} ADD COLUMN evidence longtext DEFAULT NULL AFTER notes",
            'revoked_at' => "ALTER TABLE {$member_badges_table} ADD COLUMN revoked_at datetime DEFAULT NULL AFTER evidence",
            'created_at' => "ALTER TABLE {$member_badges_table} ADD COLUMN created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER revoked_at",
            'updated_at' => "ALTER TABLE {$member_badges_table} ADD COLUMN updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at",
        );

        foreach ($member_badge_columns as $column => $statement) {
            if (!mj_member_column_exists($member_badges_table, $column)) {
                $wpdb->query($statement);
            }
        }

        $member_badge_indexes = array(
            'idx_member_badge_unique' => "ALTER TABLE {$member_badges_table} ADD UNIQUE KEY idx_member_badge_unique (member_id, badge_id)",
            'idx_badge' => "ALTER TABLE {$member_badges_table} ADD KEY idx_badge (badge_id)",
            'idx_member' => "ALTER TABLE {$member_badges_table} ADD KEY idx_member (member_id)",
            'idx_status' => "ALTER TABLE {$member_badges_table} ADD KEY idx_status (status)",
            'idx_awarded_by_user' => "ALTER TABLE {$member_badges_table} ADD KEY idx_awarded_by_user (awarded_by_user_id)",
            'idx_awarded_by_member' => "ALTER TABLE {$member_badges_table} ADD KEY idx_awarded_by_member (awarded_by_member_id)",
            'idx_awarded_at' => "ALTER TABLE {$member_badges_table} ADD KEY idx_awarded_at (awarded_at)",
        );

        foreach ($member_badge_indexes as $index => $statement) {
            if (!mj_member_index_exists($member_badges_table, $index)) {
                $wpdb->query($statement);
            }
        }

        $has_fk = $wpdb->get_var($wpdb->prepare(
            "SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND REFERENCED_TABLE_NAME = %s",
            DB_NAME,
            $member_badges_table,
            $badges_table
        ));

        if (empty($has_fk)) {
            $wpdb->query("ALTER TABLE {$member_badges_table} ADD CONSTRAINT fk_mj_member_badges_badge FOREIGN KEY (badge_id) REFERENCES {$badges_table} (id) ON DELETE CASCADE");
        }

        $wpdb->query("UPDATE {$member_badges_table} SET status = 'awarded' WHERE status IS NULL OR status = ''");
    }

    mj_member_seed_default_badges();
}

function mj_member_upgrade_to_2_45($wpdb) {
    $badges_table = mj_member_get_badges_table_name();
    $badge_criteria_table = mj_member_get_badge_criteria_table_name();
    $member_badge_criteria_table = mj_member_get_member_badge_criteria_table_name();

    if (!function_exists('dbDelta')) {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    }

    $charset_collate = $wpdb->get_charset_collate();

    $sql_badge_criteria = "CREATE TABLE {$badge_criteria_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        badge_id bigint(20) unsigned NOT NULL,
        slug varchar(160) NOT NULL,
        label varchar(190) NOT NULL,
        description text DEFAULT NULL,
        xp int unsigned NOT NULL DEFAULT 0,
        display_order smallint(5) unsigned NOT NULL DEFAULT 0,
        status varchar(20) NOT NULL DEFAULT 'active',
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY idx_badge_slug (badge_id, slug),
        KEY idx_badge (badge_id),
        KEY idx_status (status),
        KEY idx_display_order (display_order)
    ) {$charset_collate};";

    dbDelta($sql_badge_criteria);

    $sql_member_badge_criteria = "CREATE TABLE {$member_badge_criteria_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        badge_id bigint(20) unsigned NOT NULL,
        criterion_id bigint(20) unsigned NOT NULL,
        member_id bigint(20) unsigned NOT NULL,
        status varchar(20) NOT NULL DEFAULT 'awarded',
        notes text DEFAULT NULL,
        evidence longtext DEFAULT NULL,
        awarded_by_user_id bigint(20) unsigned DEFAULT NULL,
        awarded_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        revoked_at datetime DEFAULT NULL,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY idx_member_criterion_unique (member_id, criterion_id),
        KEY idx_badge (badge_id),
        KEY idx_member (member_id),
        KEY idx_criterion (criterion_id),
        KEY idx_status (status),
        KEY idx_awarded_at (awarded_at)
    ) {$charset_collate};";

    dbDelta($sql_member_badge_criteria);

    if (mj_member_table_exists($badge_criteria_table)) {
        mj_member_convert_table_to_utf8mb4($badge_criteria_table);

        $columns = array(
            'badge_id' => "ALTER TABLE {$badge_criteria_table} ADD COLUMN badge_id bigint(20) unsigned NOT NULL AFTER id",
            'slug' => "ALTER TABLE {$badge_criteria_table} ADD COLUMN slug varchar(160) NOT NULL AFTER badge_id",
            'label' => "ALTER TABLE {$badge_criteria_table} ADD COLUMN label varchar(190) NOT NULL AFTER slug",
            'description' => "ALTER TABLE {$badge_criteria_table} ADD COLUMN description text DEFAULT NULL AFTER label",
            'display_order' => "ALTER TABLE {$badge_criteria_table} ADD COLUMN display_order smallint(5) unsigned NOT NULL DEFAULT 0 AFTER description",
            'status' => "ALTER TABLE {$badge_criteria_table} ADD COLUMN status varchar(20) NOT NULL DEFAULT 'active' AFTER display_order",
            'created_at' => "ALTER TABLE {$badge_criteria_table} ADD COLUMN created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER status",
            'updated_at' => "ALTER TABLE {$badge_criteria_table} ADD COLUMN updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at",
        );

        foreach ($columns as $column => $statement) {
            if (!mj_member_column_exists($badge_criteria_table, $column)) {
                $wpdb->query($statement);
            }
        }

        $indexes = array(
            'idx_badge_slug' => "ALTER TABLE {$badge_criteria_table} ADD UNIQUE KEY idx_badge_slug (badge_id, slug)",
            'idx_badge' => "ALTER TABLE {$badge_criteria_table} ADD KEY idx_badge (badge_id)",
            'idx_status' => "ALTER TABLE {$badge_criteria_table} ADD KEY idx_status (status)",
            'idx_display_order' => "ALTER TABLE {$badge_criteria_table} ADD KEY idx_display_order (display_order)",
        );

        foreach ($indexes as $index => $statement) {
            if (!mj_member_index_exists($badge_criteria_table, $index)) {
                $wpdb->query($statement);
            }
        }

        $wpdb->query("UPDATE {$badge_criteria_table} SET status = 'active' WHERE status IS NULL OR status = ''");
    }

    if (mj_member_table_exists($member_badge_criteria_table)) {
        mj_member_convert_table_to_utf8mb4($member_badge_criteria_table);

        $columns = array(
            'badge_id' => "ALTER TABLE {$member_badge_criteria_table} ADD COLUMN badge_id bigint(20) unsigned NOT NULL AFTER id",
            'criterion_id' => "ALTER TABLE {$member_badge_criteria_table} ADD COLUMN criterion_id bigint(20) unsigned NOT NULL AFTER badge_id",
            'member_id' => "ALTER TABLE {$member_badge_criteria_table} ADD COLUMN member_id bigint(20) unsigned NOT NULL AFTER criterion_id",
            'status' => "ALTER TABLE {$member_badge_criteria_table} ADD COLUMN status varchar(20) NOT NULL DEFAULT 'awarded' AFTER member_id",
            'notes' => "ALTER TABLE {$member_badge_criteria_table} ADD COLUMN notes text DEFAULT NULL AFTER status",
            'evidence' => "ALTER TABLE {$member_badge_criteria_table} ADD COLUMN evidence longtext DEFAULT NULL AFTER notes",
            'awarded_by_user_id' => "ALTER TABLE {$member_badge_criteria_table} ADD COLUMN awarded_by_user_id bigint(20) unsigned DEFAULT NULL AFTER evidence",
            'awarded_at' => "ALTER TABLE {$member_badge_criteria_table} ADD COLUMN awarded_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER awarded_by_user_id",
            'revoked_at' => "ALTER TABLE {$member_badge_criteria_table} ADD COLUMN revoked_at datetime DEFAULT NULL AFTER awarded_at",
            'created_at' => "ALTER TABLE {$member_badge_criteria_table} ADD COLUMN created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER revoked_at",
            'updated_at' => "ALTER TABLE {$member_badge_criteria_table} ADD COLUMN updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at",
        );

        foreach ($columns as $column => $statement) {
            if (!mj_member_column_exists($member_badge_criteria_table, $column)) {
                $wpdb->query($statement);
            }
        }

        $indexes = array(
            'idx_member_criterion_unique' => "ALTER TABLE {$member_badge_criteria_table} ADD UNIQUE KEY idx_member_criterion_unique (member_id, criterion_id)",
            'idx_badge' => "ALTER TABLE {$member_badge_criteria_table} ADD KEY idx_badge (badge_id)",
            'idx_member' => "ALTER TABLE {$member_badge_criteria_table} ADD KEY idx_member (member_id)",
            'idx_criterion' => "ALTER TABLE {$member_badge_criteria_table} ADD KEY idx_criterion (criterion_id)",
            'idx_status' => "ALTER TABLE {$member_badge_criteria_table} ADD KEY idx_status (status)",
            'idx_awarded_at' => "ALTER TABLE {$member_badge_criteria_table} ADD KEY idx_awarded_at (awarded_at)",
        );

        foreach ($indexes as $index => $statement) {
            if (!mj_member_index_exists($member_badge_criteria_table, $index)) {
                $wpdb->query($statement);
            }
        }

        $wpdb->query("UPDATE {$member_badge_criteria_table} SET status = 'awarded' WHERE status IS NULL OR status = ''");

        // Add FOREIGN KEY constraints manually (dbDelta does not support them)
        $has_fk_badge = $wpdb->get_var($wpdb->prepare(
            "SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND CONSTRAINT_NAME = %s",
            DB_NAME,
            $member_badge_criteria_table,
            'fk_mj_badge_criteria_badge'
        ));
        if (empty($has_fk_badge)) {
            $wpdb->query("ALTER TABLE {$member_badge_criteria_table} ADD CONSTRAINT fk_mj_badge_criteria_badge FOREIGN KEY (badge_id) REFERENCES {$badges_table} (id) ON DELETE CASCADE");
        }

        $has_fk_criterion = $wpdb->get_var($wpdb->prepare(
            "SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND CONSTRAINT_NAME = %s",
            DB_NAME,
            $member_badge_criteria_table,
            'fk_mj_member_badge_criteria'
        ));
        if (empty($has_fk_criterion)) {
            $wpdb->query("ALTER TABLE {$member_badge_criteria_table} ADD CONSTRAINT fk_mj_member_badge_criteria FOREIGN KEY (criterion_id) REFERENCES {$badge_criteria_table} (id) ON DELETE CASCADE");
        }
    }

    if (mj_member_table_exists($badge_criteria_table) && mj_member_table_exists($badges_table) && mj_member_column_exists($badges_table, 'criteria')) {
        $badge_rows = $wpdb->get_results("SELECT id, criteria FROM {$badges_table} WHERE criteria IS NOT NULL AND criteria <> ''", ARRAY_A);
        if (is_array($badge_rows)) {
            foreach ($badge_rows as $row) {
                $badge_id = isset($row['id']) ? (int) $row['id'] : 0;
                if ($badge_id <= 0) {
                    continue;
                }

                $decoded = json_decode((string) $row['criteria'], true);
                if (empty($decoded) || !is_array($decoded)) {
                    continue;
                }

                $order = 0;
                foreach ($decoded as $entry) {
                    if (is_array($entry)) {
                        $label = isset($entry['label']) ? (string) $entry['label'] : '';
                    } else {
                        $label = (string) $entry;
                    }

                    $label = trim($label);
                    if ($label === '') {
                        continue;
                    }

                    // Check if criterion with this label already exists (regardless of status)
                    $existing_id = (int) $wpdb->get_var($wpdb->prepare(
                        "SELECT id FROM {$badge_criteria_table} WHERE badge_id = %d AND label = %s LIMIT 1",
                        $badge_id,
                        $label
                    ));

                    // Skip if already exists - don't create duplicates
                    if ($existing_id > 0) {
                        $order++;
                        continue;
                    }

                    $slug = sanitize_title($label);
                    if ($slug === '') {
                        $slug = 'criterion-' . $badge_id;
                    }

                    $base_slug = $slug;
                    $suffix = 2;
                    while ((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$badge_criteria_table} WHERE badge_id = %d AND slug = %s", $badge_id, $slug)) > 0) {
                        $slug = $base_slug . '-' . $suffix;
                        $suffix++;
                    }

                    $wpdb->insert(
                        $badge_criteria_table,
                        array(
                            'badge_id' => $badge_id,
                            'slug' => $slug,
                            'label' => $label,
                            'display_order' => $order,
                            'status' => 'active',
                            'created_at' => current_time('mysql'),
                            'updated_at' => current_time('mysql'),
                        ),
                        array('%d', '%s', '%s', '%d', '%s', '%s', '%s')
                    );

                    $order++;
                }
            }
        }
    }

    mj_member_seed_default_badges();
}

function mj_member_upgrade_to_2_46($wpdb) {
    $badges_table = mj_member_get_badges_table_name();

    if (!mj_member_table_exists($badges_table)) {
        return;
    }

    if (!mj_member_column_exists($badges_table, 'image_id')) {
        $wpdb->query("ALTER TABLE {$badges_table} ADD COLUMN image_id bigint(20) unsigned DEFAULT NULL AFTER icon");
    }
}

/**
 * Upgrade to version 2.47: Add registration_document column to events table.
 *
 * @param wpdb $wpdb
 */
function mj_member_upgrade_to_2_47($wpdb) {
    $events_table = mj_member_get_events_table_name();

    if (!mj_member_table_exists($events_table)) {
        return;
    }

    if (!mj_member_column_exists($events_table, 'registration_document')) {
        $wpdb->query("ALTER TABLE {$events_table} ADD COLUMN registration_document longtext DEFAULT NULL AFTER description");
    }
}

/**
 * Add xp_total column to members table for gamification.
 */
function mj_member_upgrade_to_2_48($wpdb) {
    $members_table = $wpdb->prefix . 'mj_members';

    if (!mj_member_table_exists($members_table)) {
        return;
    }

    if (!mj_member_column_exists($members_table, 'xp_total')) {
        $wpdb->query("ALTER TABLE {$members_table} ADD COLUMN xp_total int unsigned NOT NULL DEFAULT 0 AFTER anonymized_at");
    }

    if (!mj_member_index_exists($members_table, 'idx_xp_total')) {
        $wpdb->query("ALTER TABLE {$members_table} ADD KEY idx_xp_total (xp_total)");
    }
}

/**
 * Schema upgrade 2.49: Create trophies tables.
 */
function mj_member_upgrade_to_2_49($wpdb) {
    if (!function_exists('dbDelta')) {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    }

    $charset_collate = $wpdb->get_charset_collate();

    $trophies_table = mj_member_get_trophies_table_name();
    $member_trophies_table = mj_member_get_member_trophies_table_name();

    $sql_trophies = "CREATE TABLE {$trophies_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        slug varchar(120) NOT NULL,
        title varchar(190) NOT NULL,
        description longtext DEFAULT NULL,
        xp int unsigned NOT NULL DEFAULT 0,
        auto_mode tinyint(1) NOT NULL DEFAULT 0,
        auto_hook varchar(60) DEFAULT NULL,
        auto_threshold int unsigned NOT NULL DEFAULT 1,
        image_id bigint(20) unsigned DEFAULT NULL,
        display_order smallint(5) unsigned NOT NULL DEFAULT 0,
        status varchar(20) NOT NULL DEFAULT 'active',
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY slug (slug),
        KEY idx_status (status),
        KEY idx_auto_mode (auto_mode),
        KEY idx_auto_hook (auto_hook),
        KEY idx_display_order (display_order)
    ) {$charset_collate};";

    dbDelta($sql_trophies);

    $sql_member_trophies = "CREATE TABLE {$member_trophies_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        trophy_id bigint(20) unsigned NOT NULL,
        member_id bigint(20) unsigned NOT NULL,
        status varchar(20) NOT NULL DEFAULT 'awarded',
        notes text DEFAULT NULL,
        awarded_by_user_id bigint(20) unsigned DEFAULT NULL,
        awarded_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        revoked_at datetime DEFAULT NULL,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY idx_member_trophy_unique (member_id, trophy_id),
        KEY idx_trophy (trophy_id),
        KEY idx_member (member_id),
        KEY idx_status (status),
        KEY idx_awarded_at (awarded_at),
        CONSTRAINT fk_mj_member_trophies_trophy FOREIGN KEY (trophy_id) REFERENCES {$trophies_table} (id) ON DELETE CASCADE
    ) {$charset_collate};";

    dbDelta($sql_member_trophies);

    if (mj_member_table_exists($trophies_table)) {
        mj_member_convert_table_to_utf8mb4($trophies_table);
    }

    if (mj_member_table_exists($member_trophies_table)) {
        mj_member_convert_table_to_utf8mb4($member_trophies_table);

        $has_fk = $wpdb->get_var($wpdb->prepare(
            "SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND REFERENCED_TABLE_NAME = %s",
            DB_NAME,
            $member_trophies_table,
            $trophies_table
        ));

        if (empty($has_fk)) {
            $wpdb->query("ALTER TABLE {$member_trophies_table} ADD CONSTRAINT fk_mj_member_trophies_trophy FOREIGN KEY (trophy_id) REFERENCES {$trophies_table} (id) ON DELETE CASCADE");
        }
    }

    mj_member_seed_default_trophies();
}

/**
 * Schema upgrade 2.50: Create levels table for member progression.
 */
function mj_member_upgrade_to_2_50($wpdb) {
    if (!function_exists('dbDelta')) {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    }

    $charset_collate = $wpdb->get_charset_collate();
    $levels_table = mj_member_get_levels_table_name();

    $sql_levels = "CREATE TABLE {$levels_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        level_number smallint(5) unsigned NOT NULL,
        title varchar(190) NOT NULL,
        description longtext DEFAULT NULL,
        image_id bigint(20) unsigned DEFAULT NULL,
        xp_reward int unsigned NOT NULL DEFAULT 0,
        xp_threshold int unsigned NOT NULL DEFAULT 0,
        status varchar(20) NOT NULL DEFAULT 'active',
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY idx_level_number (level_number),
        KEY idx_status (status),
        KEY idx_xp_threshold (xp_threshold)
    ) {$charset_collate};";

    dbDelta($sql_levels);

    if (mj_member_table_exists($levels_table)) {
        mj_member_convert_table_to_utf8mb4($levels_table);
    }

    mj_member_seed_default_levels();
}

/**
 * Seed default levels (10 levels) for member progression.
 */
function mj_member_seed_default_levels() {
    global $wpdb;

    $levels_table = mj_member_get_levels_table_name();
    if (!mj_member_table_exists($levels_table)) {
        return;
    }

    $level_definitions = array(
        array(
            'level_number' => 1,
            'title' => 'Débutant',
            'description' => 'Tu viens de rejoindre la communauté ! Bienvenue parmi nous.',
            'xp_reward' => 10,
            'coins' => 10,
            'xp_threshold' => 0,
        ),
        array(
            'level_number' => 2,
            'title' => 'Apprenti',
            'description' => 'Tu commences à prendre tes marques et à participer aux activités.',
            'xp_reward' => 15,
            'coins' => 20,
            'xp_threshold' => 50,
        ),
        array(
            'level_number' => 3,
            'title' => 'Explorateur',
            'description' => 'Tu explores les différentes possibilités offertes par la MJ.',
            'xp_reward' => 20,
            'coins' => 30,
            'xp_threshold' => 150,
        ),
        array(
            'level_number' => 4,
            'title' => 'Aventurier',
            'description' => 'Tu t\'impliques de plus en plus dans les projets et activités.',
            'xp_reward' => 25,
            'coins' => 40,
            'xp_threshold' => 300,
        ),
        array(
            'level_number' => 5,
            'title' => 'Confirmé',
            'description' => 'Tu es un membre actif et reconnu au sein de la communauté.',
            'xp_reward' => 30,
            'coins' => 50,
            'xp_threshold' => 500,
        ),
        array(
            'level_number' => 6,
            'title' => 'Expert',
            'description' => 'Tu maîtrises les rouages et participes régulièrement aux décisions.',
            'xp_reward' => 40,
            'coins' => 60,
            'xp_threshold' => 800,
        ),
        array(
            'level_number' => 7,
            'title' => 'Mentor',
            'description' => 'Tu aides les nouveaux membres et partages ton expérience.',
            'xp_reward' => 50,
            'coins' => 70,
            'xp_threshold' => 1200,
        ),
        array(
            'level_number' => 8,
            'title' => 'Leader',
            'description' => 'Tu portes des projets et inspires les autres membres.',
            'xp_reward' => 60,
            'coins' => 80,
            'xp_threshold' => 1800,
        ),
        array(
            'level_number' => 9,
            'title' => 'Champion',
            'description' => 'Tu es un pilier incontournable de la MJ !',
            'xp_reward' => 75,
            'coins' => 90,
            'xp_threshold' => 2500,
        ),
        array(
            'level_number' => 10,
            'title' => 'Légende',
            'description' => 'Tu as atteint le sommet ! Tu es une véritable légende de la MJ.',
            'xp_reward' => 100,
            'coins' => 100,
            'xp_threshold' => 3500,
        ),
    );

    foreach ($level_definitions as $level) {
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$levels_table} WHERE level_number = %d",
            $level['level_number']
        ));

        if (!empty($exists)) {
            continue;
        }

        $wpdb->insert(
            $levels_table,
            array(
                'level_number' => (int) $level['level_number'],
                'title' => $level['title'],
                'description' => $level['description'],
                'xp_reward' => isset($level['xp_reward']) ? (int) $level['xp_reward'] : 0,
                'coins' => isset($level['coins']) ? (int) $level['coins'] : 0,
                'xp_threshold' => isset($level['xp_threshold']) ? (int) $level['xp_threshold'] : 0,
                'status' => 'active',
            ),
            array('%d', '%s', '%s', '%d', '%d', '%d', '%s')
        );
    }
}
add_action('init', 'mj_member_seed_default_levels', 18);

/**
 * Schema upgrade 2.51: Add coins column to badges, badge_criteria, trophies and members tables.
 */
function mj_member_upgrade_to_2_51($wpdb) {
    $badges_table = mj_member_get_badges_table_name();
    $badge_criteria_table = mj_member_get_badge_criteria_table_name();
    $trophies_table = mj_member_get_trophies_table_name();
    $members_table = $wpdb->prefix . 'mj_members';

    // Add coins column to badges table
    if (mj_member_table_exists($badges_table) && !mj_member_column_exists($badges_table, 'coins')) {
        $wpdb->query("ALTER TABLE {$badges_table} ADD COLUMN coins int unsigned NOT NULL DEFAULT 0 AFTER xp");
    }

    // Add coins column to badge_criteria table (default 1 coin per criterion)
    if (mj_member_table_exists($badge_criteria_table) && !mj_member_column_exists($badge_criteria_table, 'coins')) {
        $wpdb->query("ALTER TABLE {$badge_criteria_table} ADD COLUMN coins int unsigned NOT NULL DEFAULT 1 AFTER xp");
    }

    // Update existing criteria to have 1 coin if they have 0
    if (mj_member_table_exists($badge_criteria_table) && mj_member_column_exists($badge_criteria_table, 'coins')) {
        $wpdb->query("UPDATE {$badge_criteria_table} SET coins = 1 WHERE coins = 0");
    }

    // Add coins column to trophies table
    if (mj_member_table_exists($trophies_table) && !mj_member_column_exists($trophies_table, 'coins')) {
        $wpdb->query("ALTER TABLE {$trophies_table} ADD COLUMN coins int unsigned NOT NULL DEFAULT 0 AFTER xp");
    }

    // Add coins_total column to members table
    if (mj_member_table_exists($members_table) && !mj_member_column_exists($members_table, 'coins_total')) {
        $wpdb->query("ALTER TABLE {$members_table} ADD COLUMN coins_total int unsigned NOT NULL DEFAULT 0 AFTER xp_total");
    }

    // Add index for coins_total
    if (mj_member_table_exists($members_table) && !mj_member_index_exists($members_table, 'idx_coins_total')) {
        $wpdb->query("ALTER TABLE {$members_table} ADD KEY idx_coins_total (coins_total)");
    }

    // Add coins column to levels table
    $levels_table = mj_member_get_levels_table_name();
    if (mj_member_table_exists($levels_table) && !mj_member_column_exists($levels_table, 'coins')) {
        $wpdb->query("ALTER TABLE {$levels_table} ADD COLUMN coins int unsigned NOT NULL DEFAULT 0 AFTER xp_reward");
    }

    // Set default coins values for existing levels (increasing with level number)
    if (mj_member_table_exists($levels_table) && mj_member_column_exists($levels_table, 'coins')) {
        $wpdb->query("UPDATE {$levels_table} SET coins = level_number * 10 WHERE coins = 0");
    }
}

function mj_member_upgrade_to_2_5($wpdb) {
    $members_table = $wpdb->prefix . 'mj_members';
    $templates_table = $wpdb->prefix . 'mj_email_templates';

    if (mj_member_table_exists($members_table)) {
        if (!mj_member_column_exists($members_table, 'newsletter_opt_in')) {
            $wpdb->query("ALTER TABLE $members_table ADD COLUMN newsletter_opt_in tinyint(1) NOT NULL DEFAULT 1 AFTER photo_usage_consent");
        }

        $after_column_name = mj_member_column_exists($members_table, 'newsletter_opt_in') ? 'newsletter_opt_in' : 'photo_usage_consent';
        if (!mj_member_column_exists($members_table, 'sms_opt_in')) {
            $after_clause = sprintf(' AFTER `%s`', esc_sql($after_column_name));
            $wpdb->query("ALTER TABLE $members_table ADD COLUMN sms_opt_in tinyint(1) NOT NULL DEFAULT 1$after_clause");
        }

        $wpdb->query("UPDATE $members_table SET newsletter_opt_in = 1 WHERE newsletter_opt_in IS NULL");
        $wpdb->query("UPDATE $members_table SET sms_opt_in = 1 WHERE sms_opt_in IS NULL");
    }

    if (mj_member_table_exists($templates_table) && !mj_member_column_exists($templates_table, 'sms_content')) {
        $wpdb->query("ALTER TABLE $templates_table ADD COLUMN sms_content longtext DEFAULT NULL AFTER content");
    }
}

function mj_member_upgrade_to_2_6($wpdb) {
    if (!function_exists('dbDelta')) {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    }

    $events_table = $wpdb->prefix . 'mj_events';
    $charset_collate = $wpdb->get_charset_collate();

    if (mj_member_table_exists($events_table)) {
        $columns_to_add = array(
            'schedule_mode' => "ALTER TABLE {$events_table} ADD COLUMN schedule_mode varchar(20) NOT NULL DEFAULT 'fixed' AFTER allow_guardian_registration",
            'schedule_payload' => "ALTER TABLE {$events_table} ADD COLUMN schedule_payload longtext DEFAULT NULL AFTER schedule_mode",
            'recurrence_until' => "ALTER TABLE {$events_table} ADD COLUMN recurrence_until datetime DEFAULT NULL AFTER schedule_payload",
            'capacity_total' => "ALTER TABLE {$events_table} ADD COLUMN capacity_total int unsigned NOT NULL DEFAULT 0 AFTER recurrence_until",
            'capacity_waitlist' => "ALTER TABLE {$events_table} ADD COLUMN capacity_waitlist int unsigned NOT NULL DEFAULT 0 AFTER capacity_total",
            'capacity_notify_threshold' => "ALTER TABLE {$events_table} ADD COLUMN capacity_notify_threshold int unsigned NOT NULL DEFAULT 0 AFTER capacity_waitlist",
            'capacity_notified' => "ALTER TABLE {$events_table} ADD COLUMN capacity_notified tinyint(1) NOT NULL DEFAULT 0 AFTER capacity_notify_threshold",
        );

        foreach ($columns_to_add as $column => $statement) {
            if (!mj_member_column_exists($events_table, $column)) {
                $wpdb->query($statement);
            }
        }

        if (!mj_member_index_exists($events_table, 'idx_schedule_mode')) {
            $wpdb->query("ALTER TABLE {$events_table} ADD KEY idx_schedule_mode (schedule_mode)");
        }

        if (mj_member_column_exists($events_table, 'schedule_mode')) {
            $wpdb->query("UPDATE {$events_table} SET schedule_mode = 'fixed' WHERE schedule_mode IS NULL OR schedule_mode = ''");
        }

        if (mj_member_column_exists($events_table, 'capacity_notified')) {
            $wpdb->query("UPDATE {$events_table} SET capacity_notified = 0 WHERE capacity_notified IS NULL");
        }
    }

    $closures_table = $wpdb->prefix . 'mj_event_closures';
    $sql_closures = "CREATE TABLE {$closures_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        start_date date NOT NULL,
        end_date date NOT NULL,
        description varchar(190) DEFAULT '',
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY idx_closure_range (start_date, end_date),
        KEY idx_closure_start (start_date),
        KEY idx_closure_end (end_date)
    ) {$charset_collate};";
    dbDelta($sql_closures);

    $payments_table = $wpdb->prefix . 'mj_payments';
    if (mj_member_table_exists($payments_table)) {
        $payment_columns = array(
            'event_id' => "ALTER TABLE {$payments_table} ADD COLUMN event_id bigint(20) unsigned DEFAULT NULL AFTER payer_id",
            'registration_id' => "ALTER TABLE {$payments_table} ADD COLUMN registration_id bigint(20) unsigned DEFAULT NULL AFTER event_id",
            'context' => "ALTER TABLE {$payments_table} ADD COLUMN context varchar(60) NOT NULL DEFAULT 'membership' AFTER registration_id"
        );

        foreach ($payment_columns as $column => $statement) {
            if (!mj_member_column_exists($payments_table, $column)) {
                $wpdb->query($statement);
            }
        }

        if (!mj_member_index_exists($payments_table, 'idx_event')) {
            $wpdb->query("ALTER TABLE {$payments_table} ADD KEY idx_event (event_id)");
        }

        if (!mj_member_index_exists($payments_table, 'idx_registration')) {
            $wpdb->query("ALTER TABLE {$payments_table} ADD KEY idx_registration (registration_id)");
        }
    }
}

function mj_member_upgrade_to_2_7($wpdb) {
    // Réservé pour les migrations futures ; laissé intentionnellement vide.
}

function mj_member_upgrade_to_2_8($wpdb) {
    $events_table = $wpdb->prefix . 'mj_events';

    if (!mj_member_table_exists($events_table)) {
        return;
    }

    if (!mj_member_column_exists($events_table, 'article_id')) {
        $wpdb->query("ALTER TABLE {$events_table} ADD COLUMN article_id bigint(20) unsigned DEFAULT NULL AFTER animateur_id");
    }

    if (!mj_member_index_exists($events_table, 'idx_article')) {
        $wpdb->query("ALTER TABLE {$events_table} ADD KEY idx_article (article_id)");
    }
}

function mj_member_upgrade_to_2_9($wpdb) {
    $members_table = $wpdb->prefix . 'mj_members';

    if (!mj_member_table_exists($members_table)) {
        return;
    }

    if (!mj_member_column_exists($members_table, 'notification_preferences')) {
        $wpdb->query("ALTER TABLE {$members_table} ADD COLUMN notification_preferences longtext DEFAULT NULL AFTER sms_opt_in");
    }
}

function mj_member_upgrade_to_2_10($wpdb) {
    $events_table = $wpdb->prefix . 'mj_events';

    if (!mj_member_table_exists($events_table)) {
        return;
    }

    if (!mj_member_column_exists($events_table, 'accent_color')) {
        $wpdb->query("ALTER TABLE {$events_table} ADD COLUMN accent_color varchar(7) DEFAULT NULL AFTER type");
    }
}

function mj_member_upgrade_to_2_11($wpdb) {
    $events_table = mj_member_get_events_table_name();

    if (!mj_member_table_exists($events_table)) {
        return;
    }

    $slug_added = false;
    if (!mj_member_column_exists($events_table, 'slug')) {
        $wpdb->query("ALTER TABLE {$events_table} ADD COLUMN slug varchar(191) DEFAULT NULL AFTER title");
        $slug_added = true;
    }

    if (!mj_member_index_exists($events_table, 'idx_slug')) {
        $wpdb->query("ALTER TABLE {$events_table} ADD UNIQUE KEY idx_slug (slug)");
    }

    if (!class_exists('MjMembers')) {
        return;
    }

    $rows = $wpdb->get_results("SELECT id, title, slug FROM {$events_table} WHERE slug IS NULL OR slug = ''");
    if (empty($rows)) {
        if ($slug_added) {
            flush_rewrite_rules(false);
        }
        return;
    }

    foreach ($rows as $row) {
        $base = '';
        if (!empty($row->slug)) {
            $base = (string) $row->slug;
        } elseif (!empty($row->title)) {
            $base = (string) $row->title;
        }

        MjMembers::sync_slug((int) $row->id, $base);
    }
}

function mj_member_upgrade_to_2_12($wpdb) {
    $photos_table = $wpdb->prefix . 'mj_event_photos';

    if (!function_exists('dbDelta')) {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    }

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$photos_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        event_id bigint(20) unsigned NOT NULL,
        registration_id bigint(20) unsigned DEFAULT NULL,
        member_id bigint(20) unsigned NOT NULL,
        attachment_id bigint(20) unsigned NOT NULL,
        caption varchar(255) DEFAULT NULL,
        status varchar(20) NOT NULL DEFAULT 'pending',
        rejection_reason text DEFAULT NULL,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        reviewed_at datetime DEFAULT NULL,
        reviewed_by bigint(20) unsigned DEFAULT NULL,
        PRIMARY KEY  (id),
        KEY idx_event_status (event_id, status),
        KEY idx_member (member_id),
        KEY idx_registration (registration_id)
    ) {$charset_collate};";

    dbDelta($sql);
}

function mj_member_upgrade_to_2_13($wpdb) {
    $table = $wpdb->prefix . 'mj_contact_messages';

    if (!function_exists('dbDelta')) {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    }

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        sender_name varchar(190) NOT NULL,
        sender_email varchar(190) NOT NULL,
        subject varchar(190) DEFAULT '',
        message longtext NOT NULL,
        target_type varchar(30) NOT NULL DEFAULT 'all',
        target_reference bigint(20) unsigned DEFAULT NULL,
        target_label varchar(190) DEFAULT '',
        status varchar(20) NOT NULL DEFAULT 'nouveau',
        is_read tinyint(1) NOT NULL DEFAULT 0,
        assigned_to bigint(20) unsigned DEFAULT NULL,
        source_url varchar(255) DEFAULT '',
        activity_log longtext DEFAULT NULL,
        meta longtext DEFAULT NULL,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY idx_status (status),
        KEY idx_target (target_type, target_reference),
        KEY idx_assigned (assigned_to),
        KEY idx_read (is_read)
    ) {$charset_collate};";

    dbDelta($sql);
}

function mj_member_upgrade_to_2_14($wpdb) {
    $table = $wpdb->prefix . 'mj_contact_messages';

    if (!mj_member_table_exists($table)) {
        return;
    }

    if (!mj_member_column_exists($table, 'is_read')) {
        $wpdb->query("ALTER TABLE {$table} ADD COLUMN is_read tinyint(1) NOT NULL DEFAULT 0 AFTER status");
    }

    if (!mj_member_index_exists($table, 'idx_read')) {
        $wpdb->query("ALTER TABLE {$table} ADD KEY idx_read (is_read)");
    }
}

function mj_member_upgrade_to_2_15($wpdb) {
    $table = $wpdb->prefix . 'mj_members';

    if (!mj_member_table_exists($table)) {
        return;
    }

    if (!mj_member_column_exists($table, 'card_access_key')) {
        $wpdb->query("ALTER TABLE {$table} ADD COLUMN card_access_key varchar(64) DEFAULT NULL AFTER notification_preferences");
    }

    if (!mj_member_index_exists($table, 'idx_card_key')) {
        $wpdb->query("ALTER TABLE {$table} ADD UNIQUE KEY idx_card_key (card_access_key)");
    }
}

function mj_member_upgrade_to_2_16($wpdb) {
    $table = $wpdb->prefix . 'mj_members';

    if (!mj_member_table_exists($table)) {
        return;
    }

    if (!mj_member_column_exists($table, 'anonymized_at')) {
        $wpdb->query("ALTER TABLE {$table} ADD COLUMN anonymized_at datetime DEFAULT NULL AFTER joined_date");
    }
}

function mj_member_upgrade_to_2_17($wpdb) {
    $table = $wpdb->prefix . 'mj_members';

    if (!mj_member_table_exists($table)) {
        return;
    }

    if (!mj_member_column_exists($table, 'is_volunteer')) {
        $wpdb->query("ALTER TABLE {$table} ADD COLUMN is_volunteer tinyint(1) NOT NULL DEFAULT 0 AFTER is_autonomous");
    }

    $wpdb->query("UPDATE {$table} SET is_volunteer = 1 WHERE role = 'benevole'");
    $wpdb->query("UPDATE {$table} SET role = 'jeune' WHERE role = 'benevole'");
}

function mj_member_upgrade_to_2_18($wpdb) {
    $table = mj_member_get_hours_table_name();

    if (!mj_member_table_exists($table)) {
        return;
    }

    if (!mj_member_column_exists($table, 'start_time')) {
        $wpdb->query("ALTER TABLE {$table} ADD COLUMN start_time time DEFAULT NULL AFTER activity_date");
    }

    if (!mj_member_column_exists($table, 'end_time')) {
        $wpdb->query("ALTER TABLE {$table} ADD COLUMN end_time time DEFAULT NULL AFTER start_time");
    }
}

function mj_member_upgrade_to_2_20($wpdb) {
    $table = $wpdb->prefix . 'mj_event_closures';

    if (!mj_member_table_exists($table)) {
        return;
    }

    if (!mj_member_column_exists($table, 'start_date') && mj_member_column_exists($table, 'closure_date')) {
        $wpdb->query("ALTER TABLE {$table} CHANGE COLUMN closure_date start_date date NOT NULL");
    } elseif (!mj_member_column_exists($table, 'start_date')) {
        $wpdb->query("ALTER TABLE {$table} ADD COLUMN start_date date NOT NULL AFTER id");
    }

    if (!mj_member_column_exists($table, 'end_date')) {
        $wpdb->query("ALTER TABLE {$table} ADD COLUMN end_date date NOT NULL AFTER start_date");
        $wpdb->query("UPDATE {$table} SET end_date = start_date WHERE end_date IS NULL OR end_date = '0000-00-00'");
    }

    if (mj_member_index_exists($table, 'uniq_closure_date')) {
        $wpdb->query("ALTER TABLE {$table} DROP INDEX uniq_closure_date");
    }

    if (!mj_member_index_exists($table, 'idx_closure_range')) {
        $wpdb->query("ALTER TABLE {$table} ADD UNIQUE KEY idx_closure_range (start_date, end_date)");
    }

    if (!mj_member_index_exists($table, 'idx_closure_start')) {
        $wpdb->query("ALTER TABLE {$table} ADD KEY idx_closure_start (start_date)");
    }

    if (!mj_member_index_exists($table, 'idx_closure_end')) {
        $wpdb->query("ALTER TABLE {$table} ADD KEY idx_closure_end (end_date)");
    }
}

function mj_member_upgrade_to_2_21($wpdb) {
    $table = $wpdb->prefix . 'mj_event_volunteers';

    if (!function_exists('dbDelta')) {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    }

    if (function_exists('dbDelta')) {
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_id bigint(20) unsigned NOT NULL,
            volunteer_id bigint(20) unsigned NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY uniq_event_volunteer (event_id, volunteer_id),
            KEY idx_event (event_id),
            KEY idx_volunteer (volunteer_id)
        ) {$charset_collate};";
        dbDelta($sql);
    }
}

function mj_member_upgrade_to_2_22($wpdb) {
    $members_table = $wpdb->prefix . 'mj_members';
    
    if (!mj_member_table_exists($members_table)) {
        return;
    }

    if (!mj_member_column_exists($members_table, 'nickname')) {
        $after_clause = '';
        if (mj_member_column_exists($members_table, 'first_name')) {
            $after_clause = ' AFTER first_name';
        }
        $wpdb->query("ALTER TABLE {$members_table} ADD COLUMN nickname varchar(100) DEFAULT NULL{$after_clause}");
    }

    if (!mj_member_column_exists($members_table, 'whatsapp_opt_in')) {
        $after_clause = '';
        if (mj_member_column_exists($members_table, 'sms_opt_in')) {
            $after_clause = ' AFTER sms_opt_in';
        } elseif (mj_member_column_exists($members_table, 'newsletter_opt_in')) {
            $after_clause = ' AFTER newsletter_opt_in';
        } elseif (mj_member_column_exists($members_table, 'photo_usage_consent')) {
            $after_clause = ' AFTER photo_usage_consent';
        }

        $wpdb->query("ALTER TABLE {$members_table} ADD COLUMN whatsapp_opt_in tinyint(1) NOT NULL DEFAULT 1{$after_clause}");
        $wpdb->query("UPDATE {$members_table} SET whatsapp_opt_in = 1 WHERE whatsapp_opt_in IS NULL");
    }
}

function mj_install()
{
    mj_member_ensure_capabilities();

    global $wpdb;
    $table_name = $wpdb->prefix . 'mj_members';
    $payments_table = $wpdb->prefix . 'mj_payments';
    $payment_history_table = $wpdb->prefix . 'mj_payment_history';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        first_name varchar(100) NOT NULL,
        nickname varchar(100) DEFAULT NULL,
        last_name varchar(100) NOT NULL,
        email varchar(150) NOT NULL,
        phone varchar(30) DEFAULT NULL,
        birth_date date DEFAULT NULL,
        role varchar(20) NOT NULL DEFAULT 'jeune',
        guardian_id mediumint(9) DEFAULT NULL,
        is_autonomous tinyint(1) NOT NULL DEFAULT 0,
        is_volunteer tinyint(1) NOT NULL DEFAULT 0,
        requires_payment tinyint(1) NOT NULL DEFAULT 1,
        address varchar(250) DEFAULT NULL,
        city varchar(120) DEFAULT NULL,
        postal_code varchar(20) DEFAULT NULL,
        school varchar(150) DEFAULT NULL,
        birth_country varchar(120) DEFAULT NULL,
        nationality varchar(120) DEFAULT NULL,
        notes text DEFAULT NULL,
        description_courte varchar(255) DEFAULT NULL,
        description_longue longtext DEFAULT NULL,
        why_mj text DEFAULT NULL,
        how_mj text DEFAULT NULL,
        wp_user_id bigint(20) UNSIGNED DEFAULT NULL,
        date_inscription datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        date_last_payement datetime DEFAULT NULL,
        status varchar(50) NOT NULL DEFAULT 'active',
        photo_id bigint(20) DEFAULT NULL,
        photo_usage_consent tinyint(1) DEFAULT 0,
        newsletter_opt_in tinyint(1) NOT NULL DEFAULT 1,
        sms_opt_in tinyint(1) NOT NULL DEFAULT 1,
        whatsapp_opt_in tinyint(1) NOT NULL DEFAULT 1,
        notification_preferences longtext DEFAULT NULL,
        card_access_key varchar(64) DEFAULT NULL,
        joined_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        anonymized_at datetime DEFAULT NULL,
        PRIMARY KEY  (id),
        KEY idx_role (role),
        KEY idx_guardian (guardian_id),
        KEY idx_email (email),
        KEY idx_wp_user (wp_user_id),
        UNIQUE KEY idx_card_key (card_access_key)
    ) $charset_collate;";

    $sql_payments = "CREATE TABLE IF NOT EXISTS $payments_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        member_id mediumint(9) NOT NULL,
        payer_id mediumint(9) DEFAULT NULL,
        amount decimal(10,2) NOT NULL,
        status varchar(20) NOT NULL DEFAULT 'pending',
        token varchar(50) NOT NULL UNIQUE,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        paid_at datetime DEFAULT NULL,
        external_ref varchar(100) DEFAULT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    $sql_payment_history = "CREATE TABLE IF NOT EXISTS $payment_history_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        member_id mediumint(9) NOT NULL,
        payer_id mediumint(9) DEFAULT NULL,
        amount decimal(10,2) NOT NULL,
        payment_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        method varchar(50) DEFAULT NULL,
        reference varchar(100) DEFAULT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    dbDelta($sql_payments);
    dbDelta($sql_payment_history);
    mj_member_upgrade_to_2_2($wpdb);
    mj_member_upgrade_to_2_3($wpdb);
    mj_member_upgrade_to_2_4($wpdb);
    mj_member_upgrade_to_2_5($wpdb);
    mj_member_upgrade_to_2_6($wpdb);
    mj_member_upgrade_to_2_7($wpdb);
    mj_member_upgrade_to_2_8($wpdb);
    mj_member_upgrade_to_2_9($wpdb);

    mj_install_test_data($table_name);

    $account_page = get_page_by_path('mon-compte');
    if (!$account_page) {
        wp_insert_post(array(
            'post_title'   => 'Mon compte',
            'post_name'    => 'mon-compte',
            'post_content' => '[mj_member_account]',
            'post_status'  => 'publish',
            'post_type'    => 'page',
        ));
    }

    // Import des pages de l'espace membre depuis les exports JSON
    if (class_exists('Mj\\Member\\Classes\\MjAccountPagesExport')) {
        \Mj\Member\Classes\MjAccountPagesExport::onPluginActivation();
    }

    flush_rewrite_rules(false);
}

function mj_add_photo_column() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'mj_members';

    $column_exists = $wpdb->get_results("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='$table_name' AND COLUMN_NAME='photo_id'");

    if (empty($column_exists)) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN `photo_id` bigint(20) DEFAULT NULL");
    }
}

function mj_install_test_data($table_name) {
    global $wpdb;

    $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    if ($count > 0) {
        return;
    }

    $now = current_time('mysql');

    $wpdb->insert($table_name, array(
        'first_name' => 'Louis',
        'last_name' => 'Wathieu',
        'email' => 'louiswathieu@hotmail.com',
        'phone' => '0493741041',
        'role' => 'tuteur',
        'status' => 'active',
        'requires_payment' => 0,
        'is_autonomous' => 1,
        'address' => '24 Quai Sainte Barbe',
        'city' => 'Liège',
        'postal_code' => '4000',
        'date_inscription' => $now,
        'newsletter_opt_in' => 1,
        'sms_opt_in' => 1,
        'whatsapp_opt_in' => 1,
        'joined_date' => $now
    ), array('%s','%s','%s','%s','%s','%s','%d','%d','%s','%s','%s','%s','%d','%d','%d','%s'));

    $guardian_id = intval($wpdb->insert_id);

    $wpdb->insert($table_name, array(
        'first_name' => 'Simon',
        'last_name' => 'Bonjean',
        'email' => 'simon@mj-pery.be',
        'phone' => '+32472123456',
        'birth_date' => '2008-05-15',
        'role' => 'jeune',
        'guardian_id' => $guardian_id,
        'is_autonomous' => 0,
        'requires_payment' => 1,
        'status' => 'active',
        'date_last_payement' => date('Y-m-d H:i:s', strtotime('-2 month')),
        'date_inscription' => $now,
        'newsletter_opt_in' => 1,
        'sms_opt_in' => 1,
        'whatsapp_opt_in' => 1,
        'joined_date' => $now
    ), array('%s','%s','%s','%s','%s','%s','%d','%d','%d','%s','%s','%s','%d','%d','%d','%s'));
}

/**
 * Get the testimonials table name.
 *
 * @return string
 */
function mj_member_get_testimonials_table_name() {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    global $wpdb;
    $cached = $wpdb->prefix . 'mj_testimonials';
    return $cached;
}

function mj_member_get_testimonial_reactions_table_name() {
    global $wpdb;
    return $wpdb->prefix . 'mj_testimonial_reactions';
}

function mj_member_get_testimonial_comments_table_name() {
    global $wpdb;
    return $wpdb->prefix . 'mj_testimonial_comments';
}

/**
 * Schema upgrade 2.52: Create testimonials table for member testimonials with photos and video.
 *
 * @param wpdb $wpdb
 */
function mj_member_upgrade_to_2_52($wpdb) {
    $table = mj_member_get_testimonials_table_name();

    if (!function_exists('dbDelta')) {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    }

    $charset_collate = $wpdb->get_charset_collate();

    // Main testimonials table
    $sql = "CREATE TABLE {$table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        member_id bigint(20) unsigned NOT NULL,
        content longtext DEFAULT NULL,
        photo_ids longtext DEFAULT NULL,
        video_id bigint(20) unsigned DEFAULT NULL,
        status varchar(20) NOT NULL DEFAULT 'pending',
        rejection_reason text DEFAULT NULL,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        reviewed_at datetime DEFAULT NULL,
        reviewed_by bigint(20) unsigned DEFAULT NULL,
        PRIMARY KEY  (id),
        KEY idx_member (member_id),
        KEY idx_status (status),
        KEY idx_created (created_at)
    ) {$charset_collate};";

    dbDelta($sql);

    // Reactions table (Facebook-style reactions)
    $reactions_table = mj_member_get_testimonial_reactions_table_name();
    $sql_reactions = "CREATE TABLE {$reactions_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        testimonial_id bigint(20) unsigned NOT NULL,
        member_id bigint(20) unsigned NOT NULL,
        reaction_type varchar(20) NOT NULL DEFAULT 'like',
        reacted_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY uniq_testimonial_member (testimonial_id, member_id),
        KEY idx_testimonial (testimonial_id),
        KEY idx_member (member_id),
        KEY idx_type (reaction_type)
    ) {$charset_collate};";

    dbDelta($sql_reactions);

    // Comments table
    $comments_table = mj_member_get_testimonial_comments_table_name();
    $sql_comments = "CREATE TABLE {$comments_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        testimonial_id bigint(20) unsigned NOT NULL,
        member_id bigint(20) unsigned NOT NULL,
        content text NOT NULL,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT NULL,
        PRIMARY KEY  (id),
        KEY idx_testimonial (testimonial_id),
        KEY idx_member (member_id),
        KEY idx_created (created_at)
    ) {$charset_collate};";

    dbDelta($sql_comments);
}

/**
 * Schema upgrade 2.53: Add link_preview column to testimonials table for URL previews.
 *
 * @param wpdb $wpdb
 */
function mj_member_upgrade_to_2_53($wpdb) {
    $table = mj_member_get_testimonials_table_name();
    
    if (!mj_member_table_exists($table)) {
        return;
    }
    
    if (!mj_member_column_exists($table, 'link_preview')) {
        $wpdb->query("ALTER TABLE {$table} ADD COLUMN link_preview longtext DEFAULT NULL AFTER video_id");
    }
}

/**
 * Schema upgrade 2.54: Add featured column to testimonials table for homepage display.
 *
 * @param wpdb $wpdb
 */
function mj_member_upgrade_to_2_54($wpdb) {
    $table = mj_member_get_testimonials_table_name();
    
    if (!mj_member_table_exists($table)) {
        return;
    }
    
    if (!mj_member_column_exists($table, 'featured')) {
        $wpdb->query("ALTER TABLE {$table} ADD COLUMN featured tinyint(1) NOT NULL DEFAULT 0 AFTER link_preview");
        $wpdb->query("ALTER TABLE {$table} ADD INDEX idx_featured (featured)");
    }
}

/**
 * Schema upgrade 2.55: Create action_types and member_actions tables for gamification actions.
 *
 * @param wpdb $wpdb
 */
function mj_member_upgrade_to_2_55($wpdb) {
    if (!function_exists('dbDelta')) {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    }
    
    $charset_collate = $wpdb->get_charset_collate();
    
    // Create action_types table
    $action_types_table = mj_member_get_action_types_table_name();
    if (!mj_member_table_exists($action_types_table)) {
        $sql = "CREATE TABLE {$action_types_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            slug varchar(100) NOT NULL,
            title varchar(255) NOT NULL,
            description text DEFAULT NULL,
            emoji varchar(32) DEFAULT NULL,
            category varchar(50) NOT NULL DEFAULT 'site_interactions',
            attribution varchar(20) NOT NULL DEFAULT 'manual',
            auto_hook varchar(100) DEFAULT NULL,
            xp int(11) NOT NULL DEFAULT 5,
            coins int(11) NOT NULL DEFAULT 0,
            display_order int(11) NOT NULL DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT 'active',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY idx_category (category),
            KEY idx_attribution (attribution),
            KEY idx_status (status),
            KEY idx_display_order (display_order)
        ) {$charset_collate};";
        
        dbDelta($sql);
        
        // Seed default actions
        mj_member_seed_default_actions();
    }
    
    // Create member_actions table
    $member_actions_table = mj_member_get_member_actions_table_name();
    if (!mj_member_table_exists($member_actions_table)) {
        $sql = "CREATE TABLE {$member_actions_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            member_id bigint(20) unsigned NOT NULL,
            action_type_id bigint(20) unsigned NOT NULL,
            awarded_by bigint(20) unsigned DEFAULT NULL,
            notes text DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_member (member_id),
            KEY idx_action_type (action_type_id),
            KEY idx_awarded_by (awarded_by),
            KEY idx_created_at (created_at)
        ) {$charset_collate};";
        
        dbDelta($sql);
    }
}

/**
 * Schema upgrade 2.56: Add emojis to existing action types.
 *
 * @param wpdb $wpdb
 */
function mj_member_upgrade_to_2_56($wpdb) {
    $table = mj_member_get_action_types_table_name();
    
    if (!mj_member_table_exists($table)) {
        return;
    }
    
    // Add emoji column if missing
    if (!mj_member_column_exists($table, 'emoji')) {
        $wpdb->query("ALTER TABLE {$table} ADD COLUMN emoji varchar(32) DEFAULT NULL AFTER description");
    }
    
    $emojis = array(
        'idea_submitted' => '💡',
        'idea_adopted' => '🎯',
        'testimonial_submitted' => '📝',
        'testimonial_published' => '✅',
        'testimonial_featured' => '⭐',
        'photo_shared' => '📸',
        'photo_published' => '🖼️',
        'photo_cover' => '🏆',
        'event_created' => '📅',
        'event_animated' => '🎭',
        'comment_posted' => '💬',
        'referral_signup' => '🤝',
        'social_share' => '📣',
        'cleaning_site' => '🧹',
        'equipment_maintenance' => '🔧',
        'transport_material' => '🚗',
        'shopping_supplies' => '🛒',
        'logistics_other' => '📦',
        'assembly_attended' => '🏛️',
        'meeting_attended' => '📋',
        'workshop_facilitated' => '🎓',
        'external_event' => '🌍',
        'excellent_attitude' => '🌟',
        'conflict_resolution' => '🕊️',
        'newcomer_welcome' => '👋',
    );
    
    foreach ($emojis as $slug => $emoji) {
        $wpdb->update(
            $table,
            array('emoji' => $emoji),
            array('slug' => $slug),
            array('%s'),
            array('%s')
        );
    }
}

/**
 * Schema upgrade 2.57: Create leave management tables and add quota fields to members.
 *
 * @param wpdb $wpdb
 */
function mj_member_upgrade_to_2_57($wpdb) {
    if (!function_exists('dbDelta')) {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    }
    
    $charset_collate = $wpdb->get_charset_collate();
    
    // Create mj_leave_types table
    $leave_types_table = mj_member_get_leave_types_table_name();
    if (!mj_member_table_exists($leave_types_table)) {
        $sql = "CREATE TABLE {$leave_types_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            slug varchar(50) NOT NULL,
            requires_document tinyint(1) NOT NULL DEFAULT 0,
            requires_validation tinyint(1) NOT NULL DEFAULT 1,
            color varchar(20) DEFAULT '#6366f1',
            sort_order int NOT NULL DEFAULT 0,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY idx_active (is_active),
            KEY idx_sort (sort_order)
        ) {$charset_collate};";
        
        dbDelta($sql);
        
        // Seed default leave types
        mj_member_seed_leave_types();
    }
    
    // Create mj_leave_requests table
    $leave_requests_table = mj_member_get_leave_requests_table_name();
    if (!mj_member_table_exists($leave_requests_table)) {
        $sql = "CREATE TABLE {$leave_requests_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            member_id bigint(20) unsigned NOT NULL,
            type_id bigint(20) unsigned NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            dates longtext NOT NULL,
            reason text DEFAULT NULL,
            certificate_file varchar(255) DEFAULT NULL,
            reviewed_by bigint(20) unsigned DEFAULT NULL,
            reviewed_at datetime DEFAULT NULL,
            reviewer_comment text DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_member (member_id),
            KEY idx_type (type_id),
            KEY idx_status (status),
            KEY idx_created (created_at)
        ) {$charset_collate};";
        
        dbDelta($sql);
    }
    
    // Add certificate_file column for secure file storage
    if (mj_member_table_exists($leave_requests_table)) {
        if (!mj_member_column_exists($leave_requests_table, 'certificate_file')) {
            $wpdb->query("ALTER TABLE {$leave_requests_table} ADD COLUMN certificate_file varchar(255) DEFAULT NULL AFTER attachment_id");
        }
    }
    
    // Create mj_leave_quotas table for yearly quotas
    $leave_quotas_table = $wpdb->prefix . 'mj_leave_quotas';
    if (!mj_member_table_exists($leave_quotas_table)) {
        $sql = "CREATE TABLE {$leave_quotas_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            member_id bigint(20) unsigned NOT NULL,
            year int NOT NULL,
            type_id bigint(20) unsigned NOT NULL,
            quota int NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_member_year_type (member_id, year, type_id),
            KEY idx_member (member_id),
            KEY idx_year (year)
        ) {$charset_collate};";
        
        dbDelta($sql);
    }
    
    // Legacy: Add leave quota columns to mj_members (kept for backwards compatibility)
    $members_table = $wpdb->prefix . 'mj_members';
    if (mj_member_table_exists($members_table)) {
        if (!mj_member_column_exists($members_table, 'leave_quota_paid')) {
            $wpdb->query("ALTER TABLE {$members_table} ADD COLUMN leave_quota_paid int NOT NULL DEFAULT 0 AFTER work_schedule");
        }
        if (!mj_member_column_exists($members_table, 'leave_quota_unpaid')) {
            $wpdb->query("ALTER TABLE {$members_table} ADD COLUMN leave_quota_unpaid int NOT NULL DEFAULT 0 AFTER leave_quota_paid");
        }
        if (!mj_member_column_exists($members_table, 'leave_quota_exceptional')) {
            $wpdb->query("ALTER TABLE {$members_table} ADD COLUMN leave_quota_exceptional int NOT NULL DEFAULT 0 AFTER leave_quota_unpaid");
        }
        if (!mj_member_column_exists($members_table, 'leave_quota_recovery')) {
            $wpdb->query("ALTER TABLE {$members_table} ADD COLUMN leave_quota_recovery int NOT NULL DEFAULT 0 AFTER leave_quota_exceptional");
        }
    }
}

/**
 * Schema upgrade 2.58: Create member work schedules table for managing time-bounded schedules.
 *
 * @param wpdb $wpdb
 */
function mj_member_upgrade_to_2_58($wpdb) {
    if (!function_exists('dbDelta')) {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    }

    $charset_collate = $wpdb->get_charset_collate();
    $table = $wpdb->prefix . 'mj_member_work_schedules';

    if (!mj_member_table_exists($table)) {
        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            member_id bigint(20) unsigned NOT NULL,
            start_date date NOT NULL,
            end_date date DEFAULT NULL,
            schedule longtext NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_member (member_id),
            KEY idx_start_date (start_date),
            KEY idx_end_date (end_date),
            KEY idx_member_dates (member_id, start_date, end_date)
        ) {$charset_collate};";

        dbDelta($sql);
    }
}

/**
 * Seed default leave types.
 */
function mj_member_seed_leave_types() {
    global $wpdb;
    $table = mj_member_get_leave_types_table_name();
    
    $types = array(
        array('name' => 'Congé payé', 'slug' => 'paid', 'requires_document' => 0, 'requires_validation' => 1, 'color' => '#22c55e', 'sort_order' => 10),
        array('name' => 'Congé sans solde', 'slug' => 'unpaid', 'requires_document' => 0, 'requires_validation' => 1, 'color' => '#f59e0b', 'sort_order' => 20),
        array('name' => 'Congé exceptionnel', 'slug' => 'exceptional', 'requires_document' => 0, 'requires_validation' => 1, 'color' => '#8b5cf6', 'sort_order' => 30),
        array('name' => 'Récupération', 'slug' => 'recovery', 'requires_document' => 0, 'requires_validation' => 1, 'color' => '#3b82f6', 'sort_order' => 40),
        array('name' => 'Maladie', 'slug' => 'sick', 'requires_document' => 1, 'requires_validation' => 0, 'color' => '#ef4444', 'sort_order' => 50),
    );
    
    foreach ($types as $type) {
        $wpdb->insert($table, $type);
    }
}

/**
 * Seed default action types from ACTION.md specification.
 */
function mj_member_seed_default_actions() {
    global $wpdb;
    $table = mj_member_get_action_types_table_name();
    
    $actions = array(
        // site_ideas - Contributions intellectuelles
        array('slug' => 'idea_submitted', 'title' => 'Idée soumise', 'description' => 'Proposition originale soumise via le formulaire d\'idées', 'category' => 'site_ideas', 'attribution' => 'auto', 'auto_hook' => 'mj_member_idea_submitted', 'xp' => 5, 'coins' => 0, 'emoji' => '💡', 'display_order' => 10),
        array('slug' => 'idea_adopted', 'title' => 'Idée retenue', 'description' => 'Idée validée par l\'équipe et programmée pour réalisation', 'category' => 'site_ideas', 'attribution' => 'manual', 'xp' => 20, 'coins' => 1, 'emoji' => '🎯', 'display_order' => 20),
        
        // site_testimonials - Témoignages
        array('slug' => 'testimonial_submitted', 'title' => 'Témoignage soumis', 'description' => 'Témoignage envoyé via le formulaire (texte, photo ou vidéo)', 'category' => 'site_testimonials', 'attribution' => 'auto', 'auto_hook' => 'mj_member_testimonial_submitted', 'xp' => 10, 'coins' => 0, 'emoji' => '📝', 'display_order' => 30),
        array('slug' => 'testimonial_published', 'title' => 'Témoignage publié', 'description' => 'Témoignage validé et visible sur le site', 'category' => 'site_testimonials', 'attribution' => 'auto', 'auto_hook' => 'mj_member_testimonial_approved', 'xp' => 5, 'coins' => 0, 'emoji' => '✅', 'display_order' => 40),
        array('slug' => 'testimonial_featured', 'title' => 'Témoignage mis en avant', 'description' => 'Témoignage sélectionné pour affichage en page d\'accueil', 'category' => 'site_testimonials', 'attribution' => 'manual', 'xp' => 15, 'coins' => 1, 'emoji' => '⭐', 'display_order' => 50),
        
        // site_photos - Contributions visuelles
        array('slug' => 'photo_shared', 'title' => 'Photo partagée', 'description' => 'Photo d\'activité envoyée via le formulaire dédié', 'category' => 'site_photos', 'attribution' => 'auto', 'auto_hook' => 'mj_member_photo_submitted', 'xp' => 5, 'coins' => 0, 'emoji' => '📸', 'display_order' => 60),
        array('slug' => 'photo_published', 'title' => 'Photo publiée', 'description' => 'Photo validée et ajoutée à la médiathèque publique', 'category' => 'site_photos', 'attribution' => 'auto', 'auto_hook' => 'mj_member_photo_approved', 'xp' => 3, 'coins' => 0, 'emoji' => '🖼️', 'display_order' => 70),
        array('slug' => 'photo_cover', 'title' => 'Photo utilisée en couverture', 'description' => 'Photo choisie comme image de couverture d\'un événement ou d\'une page', 'category' => 'site_photos', 'attribution' => 'manual', 'xp' => 10, 'coins' => 1, 'emoji' => '🏆', 'display_order' => 80),
        
        // site_activities - Engagement événementiel
        array('slug' => 'event_created', 'title' => 'Événement proposé', 'description' => 'Nouvel événement créé / proposé dans le système', 'category' => 'site_activities', 'attribution' => 'auto', 'auto_hook' => 'mj_member_event_created', 'xp' => 15, 'coins' => 0, 'emoji' => '📅', 'display_order' => 90),
        array('slug' => 'event_animated', 'title' => 'Événement animé', 'description' => 'Animation effective d\'un événement (après confirmation présence)', 'category' => 'site_activities', 'attribution' => 'auto', 'auto_hook' => 'mj_member_event_animated', 'xp' => 20, 'coins' => 0, 'emoji' => '🎭', 'display_order' => 100),
        
        // site_interactions - Interactions communautaires
        array('slug' => 'comment_posted', 'title' => 'Commentaire posté', 'description' => 'Commentaire publié sur le blog ou un événement', 'category' => 'site_interactions', 'attribution' => 'auto', 'auto_hook' => 'mj_member_comment_approved', 'xp' => 2, 'coins' => 0, 'emoji' => '💬', 'display_order' => 110),
        array('slug' => 'referral_signup', 'title' => 'Parrainage', 'description' => 'Nouveau membre inscrit grâce au parrainage', 'category' => 'site_interactions', 'attribution' => 'manual', 'xp' => 30, 'coins' => 1, 'emoji' => '🤝', 'display_order' => 120),
        array('slug' => 'social_share', 'title' => 'Partage réseaux sociaux', 'description' => 'Partage d\'un contenu MJ sur les réseaux sociaux (vérification manuelle)', 'category' => 'site_interactions', 'attribution' => 'manual', 'xp' => 5, 'coins' => 0, 'emoji' => '📣', 'display_order' => 130),
        
        // mj_cleaning - Nettoyage & Entretien
        array('slug' => 'cleaning_site', 'title' => 'Nettoyage du site', 'description' => 'Participation au nettoyage d\'un lieu d\'activité', 'category' => 'mj_cleaning', 'attribution' => 'manual', 'xp' => 15, 'coins' => 1, 'emoji' => '🧹', 'display_order' => 140),
        array('slug' => 'equipment_maintenance', 'title' => 'Entretien du matériel', 'description' => 'Maintenance, réparation ou inventaire du matériel', 'category' => 'mj_cleaning', 'attribution' => 'manual', 'xp' => 10, 'coins' => 0, 'emoji' => '🔧', 'display_order' => 150),
        
        // mj_logistics - Logistique & Transport
        array('slug' => 'transport_material', 'title' => 'Transport de matériel', 'description' => 'Acheminement de matériel avant/après une activité', 'category' => 'mj_logistics', 'attribution' => 'manual', 'xp' => 10, 'coins' => 0, 'emoji' => '🚗', 'display_order' => 160),
        array('slug' => 'shopping_supplies', 'title' => 'Achats & Courses', 'description' => 'Courses pour l\'association (fournitures, nourriture…)', 'category' => 'mj_logistics', 'attribution' => 'manual', 'xp' => 10, 'coins' => 0, 'emoji' => '🛒', 'display_order' => 170),
        array('slug' => 'logistics_other', 'title' => 'Logistique diverse', 'description' => 'Autre contribution logistique significative', 'category' => 'mj_logistics', 'attribution' => 'manual', 'xp' => 10, 'coins' => 0, 'emoji' => '📦', 'display_order' => 180),
        
        // mj_collective - Vie associative
        array('slug' => 'assembly_attended', 'title' => 'Participation AG', 'description' => 'Présence à l\'Assemblée Générale annuelle', 'category' => 'mj_collective', 'attribution' => 'manual', 'xp' => 20, 'coins' => 1, 'emoji' => '🏛️', 'display_order' => 190),
        array('slug' => 'meeting_attended', 'title' => 'Réunion de bureau', 'description' => 'Participation à une réunion du bureau ou du CA', 'category' => 'mj_collective', 'attribution' => 'manual', 'xp' => 10, 'coins' => 0, 'emoji' => '📋', 'display_order' => 200),
        array('slug' => 'workshop_facilitated', 'title' => 'Atelier animé', 'description' => 'Animation d\'un atelier de réflexion ou de formation', 'category' => 'mj_collective', 'attribution' => 'manual', 'xp' => 15, 'coins' => 1, 'emoji' => '🎓', 'display_order' => 210),
        array('slug' => 'external_event', 'title' => 'Représentation externe', 'description' => 'Participation à un événement externe au nom de l\'association', 'category' => 'mj_collective', 'attribution' => 'manual', 'xp' => 15, 'coins' => 0, 'emoji' => '🌍', 'display_order' => 220),
        
        // mj_attitude - Comportement & Valeurs
        array('slug' => 'excellent_attitude', 'title' => 'Attitude exemplaire', 'description' => 'Comportement remarquable lors d\'une activité (bienveillance, entraide…)', 'category' => 'mj_attitude', 'attribution' => 'manual', 'xp' => 10, 'coins' => 0, 'emoji' => '🌟', 'display_order' => 230),
        array('slug' => 'conflict_resolution', 'title' => 'Résolution de conflit', 'description' => 'Médiation réussie ou gestion positive d\'une situation tendue', 'category' => 'mj_attitude', 'attribution' => 'manual', 'xp' => 15, 'coins' => 1, 'emoji' => '🕊️', 'display_order' => 240),
        array('slug' => 'newcomer_welcome', 'title' => 'Accueil des nouveaux', 'description' => 'Accompagnement bienveillant d\'un nouveau participant', 'category' => 'mj_attitude', 'attribution' => 'manual', 'xp' => 10, 'coins' => 0, 'emoji' => '👋', 'display_order' => 250),
    );
    
    $order = 0;
    foreach ($actions as $action) {
        $action['display_order'] = $order;
        $order += 10;
        
        $wpdb->insert($table, $action);
    }
}

function mj_uninstall()
{
    mj_member_remove_capabilities();

    if (function_exists('mj_member_clear_data_retention_schedule')) {
        mj_member_clear_data_retention_schedule();
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'mj_members';
    $payments_table = $wpdb->prefix . 'mj_payments';
    $payment_history_table = $wpdb->prefix . 'mj_payment_history';

    $wpdb->query("DROP TABLE IF EXISTS $payment_history_table");
    $wpdb->query("DROP TABLE IF EXISTS $payments_table");
    $wpdb->query("DROP TABLE IF EXISTS $table_name");
}

/**
 * Ensure the event_location_links table exists
 * 
 * This table allows associating multiple locations to an event with different types:
 * - departure: Lieu de départ
 * - activity: Lieu d'activité
 * - return: Lieu de retour  
 * - other: Autre
 */
function mj_member_ensure_event_location_links_table() {
    global $wpdb;

    if (!function_exists('dbDelta')) {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    }

    $table = mj_member_get_event_location_links_table_name();
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        event_id bigint(20) unsigned NOT NULL,
        location_id bigint(20) unsigned NOT NULL,
        location_type varchar(30) NOT NULL DEFAULT 'activity',
        custom_label varchar(100) DEFAULT NULL,
        meeting_time varchar(10) DEFAULT NULL,
        meeting_time_end varchar(10) DEFAULT NULL,
        sort_order int unsigned NOT NULL DEFAULT 0,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_event_location_type (event_id, location_id, location_type),
        KEY idx_event (event_id),
        KEY idx_location (location_id),
        KEY idx_type (location_type)
    ) {$charset_collate};";

    dbDelta($sql);
}

/**
 * Migration 2.59: Add is_trusted_member column to mj_members table.
 */
function mj_member_upgrade_to_2_59($wpdb) {
    $table = $wpdb->prefix . 'mj_members';
    
    if (mj_member_table_exists($table)) {
        if (!mj_member_column_exists($table, 'is_trusted_member')) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN is_trusted_member tinyint(1) NOT NULL DEFAULT 0 AFTER role");
        }
    }
}

/**
 * Migration 2.60: Create dynamic fields tables.
 */
function mj_member_upgrade_to_2_60($wpdb) {
    if ( ! function_exists('dbDelta') ) {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    }

    $charset_collate = $wpdb->get_charset_collate();

    $fields_table = $wpdb->prefix . 'mj_dynamic_fields';
    $sql_fields = "CREATE TABLE IF NOT EXISTS {$fields_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        slug varchar(120) NOT NULL,
        field_type varchar(30) NOT NULL DEFAULT 'text',
        title varchar(255) NOT NULL DEFAULT '',
        description text DEFAULT NULL,
        show_in_registration tinyint(1) NOT NULL DEFAULT 0,
        show_in_account tinyint(1) NOT NULL DEFAULT 0,
        is_required tinyint(1) NOT NULL DEFAULT 0,
        options_list longtext DEFAULT NULL,
        sort_order int unsigned NOT NULL DEFAULT 0,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY slug (slug)
    ) {$charset_collate};";
    dbDelta($sql_fields);

    $values_table = $wpdb->prefix . 'mj_dynamic_field_values';
    $sql_values = "CREATE TABLE IF NOT EXISTS {$values_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        member_id bigint(20) unsigned NOT NULL,
        field_id bigint(20) unsigned NOT NULL,
        field_value longtext DEFAULT NULL,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_member_field (member_id, field_id),
        KEY idx_member (member_id),
        KEY idx_field (field_id)
    ) {$charset_collate};";
    dbDelta($sql_values);
}

/**
 * Migration 2.61: Add allow_other column to dynamic fields table.
 */
function mj_member_upgrade_to_2_61($wpdb) {
    $fields_table = $wpdb->prefix . 'mj_dynamic_fields';
    if (mj_member_table_exists($fields_table) && !mj_member_column_exists($fields_table, 'allow_other')) {
        $wpdb->query("ALTER TABLE {$fields_table} ADD COLUMN allow_other tinyint(1) NOT NULL DEFAULT 0 AFTER is_required");
    }
}

/**
 * Migration 2.62: Add show_in_notes column to dynamic fields table.
 */
function mj_member_upgrade_to_2_62($wpdb) {
    $fields_table = $wpdb->prefix . 'mj_dynamic_fields';
    if (mj_member_table_exists($fields_table) && !mj_member_column_exists($fields_table, 'show_in_notes')) {
        $wpdb->query("ALTER TABLE {$fields_table} ADD COLUMN show_in_notes tinyint(1) NOT NULL DEFAULT 0 AFTER allow_other");
    }
}

/**
 * Migration 2.63: Add other_label column to dynamic fields table.
 */
function mj_member_upgrade_to_2_63($wpdb) {
    $fields_table = $wpdb->prefix . 'mj_dynamic_fields';
    if (mj_member_table_exists($fields_table) && !mj_member_column_exists($fields_table, 'other_label')) {
        $wpdb->query("ALTER TABLE {$fields_table} ADD COLUMN other_label varchar(255) NOT NULL DEFAULT '' AFTER allow_other");
    }
}

/**
 * Migration 2.64: Add youth_only column to dynamic fields table.
 */
function mj_member_upgrade_to_2_64($wpdb) {
    $fields_table = $wpdb->prefix . 'mj_dynamic_fields';
    if (mj_member_table_exists($fields_table) && !mj_member_column_exists($fields_table, 'youth_only')) {
        $wpdb->query("ALTER TABLE {$fields_table} ADD COLUMN youth_only tinyint(1) NOT NULL DEFAULT 0 AFTER show_in_notes");
    }
}

add_action('init', 'mj_member_run_schema_upgrade', 5);
add_action('admin_init', 'mj_check_and_add_columns');
add_action('plugins_loaded', 'mj_member_ensure_event_location_links_table', 20);
