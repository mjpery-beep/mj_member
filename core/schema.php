<?php

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

function mj_member_get_event_attendance_table_name() {
    return mj_member_get_event_registrations_table_name();
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
    );

    foreach ($templates as $slug => $data) {
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE slug = %s", $slug));
        if ($exists) {
            continue;
        }

        $wpdb->insert(
            $table,
            array(
                'slug' => $slug,
                'subject' => $data['subject'],
                'content' => $data['content'],
            ),
            array('%s', '%s', '%s')
        );
    }
}
add_action('init', 'mj_member_seed_email_templates', 15);

function mj_member_run_schema_upgrade() {
    static $running = false;
    if ($running) {
        return;
    }
    $running = true;

    global $wpdb;

    $stored_version = get_option('mj_member_schema_version', '1.0.0');
    $table_name = $wpdb->prefix . 'mj_members';

    $critical_columns = array('description_courte', 'description_longue', 'wp_user_id');
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
            'slug'
        );

        foreach ($event_critical_columns as $column) {
            if (!mj_member_column_exists($events_table, $column)) {
                $missing_event_columns[] = $column;
            }
        }
    }

    $schema_needs_upgrade = version_compare($stored_version, MJ_MEMBER_SCHEMA_VERSION, '<')
        || !empty($missing_columns)
        || !empty($missing_event_columns);
    
 

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
    
    $registrations_table = mj_member_get_event_registrations_table_name();
    if ($registrations_table && mj_member_table_exists($registrations_table)) {
        if (!mj_member_column_exists($registrations_table, 'attendance_payload')) {
            $wpdb->query("ALTER TABLE {$registrations_table} ADD COLUMN attendance_payload longtext DEFAULT NULL AFTER notes");
        }

        if (!mj_member_column_exists($registrations_table, 'attendance_updated_at')) {
            $wpdb->query("ALTER TABLE {$registrations_table} ADD COLUMN attendance_updated_at datetime DEFAULT NULL AFTER attendance_payload");
        }
    }

    if (class_exists('MjMembers_CRUD')) {
        MjMembers_CRUD::resetColumnCache();
    }

    update_option('mj_member_schema_version', MJ_MEMBER_SCHEMA_VERSION);
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
                        'role' => 'tuteur',
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

    $csv_path = MJ_MEMBER_PATH . 'data/event.csv';
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
        'STATUS_ACTIF' => MjEvents_CRUD::STATUS_ACTIVE,
        'STATUS_BROUILLON' => MjEvents_CRUD::STATUS_DRAFT,
        'STATUS_PASSE' => MjEvents_CRUD::STATUS_PAST,
    );

    $type_map = array(
        'TYPE_STAGE' => MjEvents_CRUD::TYPE_STAGE,
        'TYPE_SOIREE' => MjEvents_CRUD::TYPE_SOIREE,
        'TYPE_SORTIE' => MjEvents_CRUD::TYPE_SORTIE,
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
        $status = isset($status_map[$status_key]) ? $status_map[$status_key] : MjEvents_CRUD::STATUS_DRAFT;

        $type_key = isset($header_map['type']) ? strtoupper(trim((string) $row[$header_map['type']])) : '';
        $type = isset($type_map[$type_key]) ? $type_map[$type_key] : MjEvents_CRUD::TYPE_STAGE;

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

        $event_id = MjEvents_CRUD::create($event_data);
        if (!$event_id) {
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
        location_id bigint(20) unsigned DEFAULT NULL,
        animateur_id bigint(20) unsigned DEFAULT NULL,
        article_id bigint(20) unsigned DEFAULT NULL,
        schedule_mode varchar(20) NOT NULL DEFAULT 'fixed',
        schedule_payload longtext DEFAULT NULL,
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
        closure_date date NOT NULL,
        description varchar(190) DEFAULT '',
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY uniq_closure_date (closure_date)
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

    if (!class_exists('MjEvents_CRUD')) {
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

        MjEvents_CRUD::sync_slug((int) $row->id, $base);
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
        last_name varchar(100) NOT NULL,
        email varchar(150) NOT NULL,
        phone varchar(30) DEFAULT NULL,
        birth_date date DEFAULT NULL,
        role varchar(20) NOT NULL DEFAULT 'jeune',
        guardian_id mediumint(9) DEFAULT NULL,
        is_autonomous tinyint(1) NOT NULL DEFAULT 0,
        requires_payment tinyint(1) NOT NULL DEFAULT 1,
        address varchar(250) DEFAULT NULL,
        city varchar(120) DEFAULT NULL,
        postal_code varchar(20) DEFAULT NULL,
        notes text DEFAULT NULL,
        description_courte varchar(255) DEFAULT NULL,
        description_longue longtext DEFAULT NULL,
        wp_user_id bigint(20) UNSIGNED DEFAULT NULL,
        date_inscription datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        date_last_payement datetime DEFAULT NULL,
        status varchar(50) NOT NULL DEFAULT 'active',
        photo_id bigint(20) DEFAULT NULL,
        photo_usage_consent tinyint(1) DEFAULT 0,
        newsletter_opt_in tinyint(1) NOT NULL DEFAULT 1,
        sms_opt_in tinyint(1) NOT NULL DEFAULT 1,
        notification_preferences longtext DEFAULT NULL,
        joined_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY idx_role (role),
        KEY idx_guardian (guardian_id),
        KEY idx_email (email),
        KEY idx_wp_user (wp_user_id)
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
        'joined_date' => $now
    ), array('%s','%s','%s','%s','%s','%s','%d','%d','%s','%s','%s','%s','%d','%d','%s'));

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
        'joined_date' => $now
    ), array('%s','%s','%s','%s','%s','%s','%d','%d','%d','%s','%s','%s','%d','%d','%s'));
}

function mj_uninstall()
{
    mj_member_remove_capabilities();

    global $wpdb;
    $table_name = $wpdb->prefix . 'mj_members';
    $payments_table = $wpdb->prefix . 'mj_payments';
    $payment_history_table = $wpdb->prefix . 'mj_payment_history';

    $wpdb->query("DROP TABLE IF EXISTS $payment_history_table");
    $wpdb->query("DROP TABLE IF EXISTS $payments_table");
    $wpdb->query("DROP TABLE IF EXISTS $table_name");
}

add_action('init', 'mj_member_run_schema_upgrade', 5);
add_action('admin_init', 'mj_check_and_add_columns');