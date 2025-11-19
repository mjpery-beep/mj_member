<?php 

/*
Plugin Name: MJ Member
Plugin URI: https://mj-pery.be
Description: Gestion des membres avec table CRUD
Version: 2.1.0
Author: Simon
*/
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'MJ_MEMBER_VERSION', '2.3.0' );
define( 'MJ_MEMBER_SCHEMA_VERSION', '2.3.0' );
define( 'MJ_MEMBER_PATH', plugin_dir_path( __FILE__ ) );
define( 'MJ_MEMBER_URL', plugin_dir_url( __FILE__ ) );
define( 'MJ_MEMBER_CAPABILITY', 'mj_manage_members' );

if ( ! defined( 'MJ_MEMBER_PAYMENT_EXPIRATION_DAYS' ) ) {
    define( 'MJ_MEMBER_PAYMENT_EXPIRATION_DAYS', 365 );
}

/**
 * Ensure default roles have the custom capability used across the plugin.
 */
function mj_member_ensure_capabilities() {
    if ( ! function_exists( 'get_role' ) ) {
        return;
    }

    $roles = apply_filters( 'mj_member_capability_roles', array( 'administrator', 'editor' ) );

    foreach ( $roles as $role_name ) {
        $role = get_role( $role_name );
        if ( $role && ! $role->has_cap( MJ_MEMBER_CAPABILITY ) ) {
            $role->add_cap( MJ_MEMBER_CAPABILITY );
        }
    }
}
add_action( 'init', 'mj_member_ensure_capabilities', 3 );

/**
 * Remove the custom capability from default roles (used on deactivation).
 */
function mj_member_remove_capabilities() {
    if ( ! function_exists( 'get_role' ) ) {
        return;
    }

    $roles = apply_filters( 'mj_member_capability_roles', array( 'administrator', 'editor' ) );

    foreach ( $roles as $role_name ) {
        $role = get_role( $role_name );
        if ( $role && $role->has_cap( MJ_MEMBER_CAPABILITY ) ) {
            $role->remove_cap( MJ_MEMBER_CAPABILITY );
        }
    }
}

// Plugin activation hook
require plugin_dir_path( __FILE__ ) . 'includes/classes/MjTools.php';
require plugin_dir_path( __FILE__ ) . 'includes/classes/MjMembers_CRUD.php';
require plugin_dir_path( __FILE__ ) . 'includes/classes/MjPayments.php';
require plugin_dir_path( __FILE__ ) . 'includes/classes/MjEvents_CRUD.php';
require plugin_dir_path( __FILE__ ) . 'includes/classes/MjEventRegistrations.php';
require plugin_dir_path( __FILE__ ) . 'includes/classes/MjEventLocations.php';
require plugin_dir_path( __FILE__ ) . 'includes/classes/MjStripeConfig.php';
require plugin_dir_path( __FILE__ ) . 'includes/security.php'; // SÉCURITÉ
require plugin_dir_path( __FILE__ ) . 'includes/member_accounts.php';
require plugin_dir_path( __FILE__ ) . 'includes/login_component.php';
require plugin_dir_path( __FILE__ ) . 'includes/shortcode_inscription.php';
require plugin_dir_path( __FILE__ ) . 'includes/shortcode_member_account.php';
// Mail class and admin pages
require plugin_dir_path( __FILE__ ) . 'includes/classes/MjMail.php';
require plugin_dir_path( __FILE__ ) . 'includes/email_templates.php';
require plugin_dir_path( __FILE__ ) . 'includes/send_emails.php';
require plugin_dir_path( __FILE__ ) . 'includes/settings.php';

// Hook d'activation du plugin
register_activation_hook(__FILE__, 'mj_install');

// SÉCURITÉ: Protéger les clés Stripe de l'accès public via l'API REST
add_filter('rest_pre_dispatch', 'mj_protect_stripe_keys', 10, 3);
function mj_protect_stripe_keys($result, $server, $request) {
    // Bloquer l'accès à l'endpoint options si on essaie d'accéder aux clés Stripe
    if (strpos($request->get_route(), '/wp/v2/settings') !== false) {
        $params = $request->get_json_params();
        if (isset($params['mj_stripe_secret_key']) || isset($params['mj_stripe_secret_key_encrypted'])) {
            return new WP_Error('forbidden', 'Accès refusé', array('status' => 403));
        }
    }
    return $result;
}

// Vérifier et ajouter les colonnes manquantes à chaque chargement
add_action('init', 'mj_member_run_schema_upgrade', 5);
add_action('admin_init', 'mj_check_and_add_columns');
add_action('admin_init', 'mj_member_restrict_dashboard_access', 99);
add_action('after_setup_theme', 'mj_member_hide_admin_bar_for_members');

// Hook de désactivation du plugin
register_deactivation_hook(__FILE__, 'mj_uninstall');

function mj_check_and_add_columns() {
    mj_member_run_schema_upgrade();
    mj_member_ensure_auxiliary_tables();
}

function mj_member_restrict_dashboard_access() {
    if (!is_user_logged_in()) {
        return;
    }

    if (wp_doing_ajax() || (defined('DOING_CRON') && DOING_CRON)) {
        return;
    }

    $user = wp_get_current_user();
    if (empty($user) || 0 === (int) $user->ID) {
        return;
    }

    $allowed = user_can($user, 'manage_options') || user_can($user, MJ_MEMBER_CAPABILITY);
    $allowed = apply_filters('mj_member_allow_dashboard_access', $allowed, $user);

    if ($allowed) {
        return;
    }

    $redirect = '';
    if (function_exists('mj_member_get_account_redirect')) {
        $redirect = mj_member_get_account_redirect();
    }

    if ($redirect === '') {
        $redirect = home_url('/');
    }

    $redirect = apply_filters('mj_member_dashboard_redirect', $redirect, $user);

    wp_safe_redirect($redirect);
    exit;
}

function mj_member_hide_admin_bar_for_members() {
    if (!is_user_logged_in() || is_admin()) {
        return;
    }

    $user = wp_get_current_user();
    if (empty($user) || 0 === (int) $user->ID) {
        return;
    }

    if (user_can($user, 'manage_options') || user_can($user, MJ_MEMBER_CAPABILITY)) {
        return;
    }

    $show_bar = apply_filters('mj_member_show_admin_bar', false, $user);
    if ($show_bar) {
        return;
    }

    show_admin_bar(false);
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

    // Templates d'email
    $templates_table = $wpdb->prefix . 'mj_email_templates';
    if ($wpdb->get_var("SHOW TABLES LIKE '$templates_table'") !== $templates_table) {
        $sql = "CREATE TABLE IF NOT EXISTS $templates_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            slug varchar(120) NOT NULL,
            subject varchar(255) NOT NULL,
            content longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY slug (slug)
        ) $charset_collate;";
        dbDelta($sql);
    }

    // Table des paiements
    $payments_table = $wpdb->prefix . 'mj_payments';
    $sql_payments = "CREATE TABLE IF NOT EXISTS $payments_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        member_id mediumint(9) NOT NULL,
        payer_id mediumint(9) DEFAULT NULL,
        amount decimal(10,2) NOT NULL DEFAULT '0.00',
        status varchar(20) NOT NULL DEFAULT 'pending',
        token varchar(120) NOT NULL,
        external_ref varchar(255) DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        paid_at datetime DEFAULT NULL,
        PRIMARY KEY  (id),
        KEY payer_idx (payer_id)
    ) $charset_collate;";
    dbDelta($sql_payments);

    // S'assurer que la colonne payer_id existe (dbDelta peut ne pas l'ajouter si table déjà créée)
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

    // Historique des paiements
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

    $schema_needs_upgrade = version_compare($stored_version, MJ_MEMBER_SCHEMA_VERSION, '<') || !empty($missing_columns);

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
    mj_member_upgrade_to_2_3($wpdb);

    update_option('mj_member_schema_version', MJ_MEMBER_SCHEMA_VERSION);
    $running = false;
}

function mj_member_upgrade_to_2_0($wpdb) {
    $table_name = $wpdb->prefix . 'mj_members';

    // Si la table n'existe pas encore, laisser l'installation standard la créer
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

    // Colonnes historiques à préserver
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

    // Index utiles
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
        return; // Rien à migrer
    }

    // Vérifier si les nouvelles colonnes sont déjà remplies
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

function mj_custom_admin_styles($hook) {
    wp_enqueue_style('custom-styles', plugins_url('/css/styles.css', __FILE__ ));

    $inline_edit_path = plugin_dir_path(__FILE__) . 'js/inline-edit.js';
    $inline_edit_version = file_exists($inline_edit_path) ? filemtime($inline_edit_path) : '1.0.0';
    wp_enqueue_script('mj-inline-edit', plugins_url('/js/inline-edit.js', __FILE__), array('jquery'), $inline_edit_version, true);
    wp_enqueue_media();
    wp_enqueue_script('mj-photo-upload', plugins_url('/js/photo-upload.js', __FILE__), array('jquery'), '1.0.0', true);
    wp_localize_script('mj-inline-edit', 'mjMembers', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('mj_inline_edit_nonce'),
        'allowedRoles' => MjMembers_CRUD::getAllowedRoles(),
        'roleLabels' => MjMembers_CRUD::getRoleLabels(),
        'statusLabels' => array(
            MjMembers_CRUD::STATUS_ACTIVE => 'Actif',
            MjMembers_CRUD::STATUS_INACTIVE => 'Inactif',
        ),
        'photoConsentLabels' => array(
            '1' => 'Accepté',
            '0' => 'Refusé',
        ),
    ));
    wp_localize_script('mj-photo-upload', 'mjPhotoUpload', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('mj_photo_upload_nonce')
    ));

    // Admin payments JS (for QR preview)
    $payments_js_path = plugin_dir_path(__FILE__) . 'includes/js/admin-payments.js';
    $payments_js_version = file_exists($payments_js_path) ? filemtime($payments_js_path) : '1.0.1';
    wp_enqueue_script('mj-admin-payments', plugins_url('includes/js/admin-payments.js', __FILE__), array('jquery'), $payments_js_version, true);
    wp_localize_script('mj-admin-payments', 'mjPayments', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('mj_admin_payments_nonce')
    ));

    // WordPress account linking modal
    $user_link_js_path = plugin_dir_path(__FILE__) . 'js/member-user-link.js';
    $user_link_js_version = file_exists($user_link_js_path) ? filemtime($user_link_js_path) : MJ_MEMBER_VERSION;
    wp_enqueue_script('mj-member-user-link', plugins_url('js/member-user-link.js', __FILE__), array('jquery'), $user_link_js_version, true);

    $editable_roles = function_exists('get_editable_roles') ? get_editable_roles() : array();
    $roles_for_modal = array();
    foreach ($editable_roles as $role_key => $role_data) {
        $roles_for_modal[$role_key] = translate_user_role($role_data['name']);
    }

    wp_localize_script('mj-member-user-link', 'mjMemberUsers', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('mj_link_member_user'),
        'roles' => $roles_for_modal,
        'i18n' => array(
            'titleCreate' => __('Créer un compte WordPress', 'mj-member'),
            'titleUpdate' => __('Mettre à jour le compte WordPress', 'mj-member'),
            'submitCreate' => __('Créer le compte', 'mj-member'),
            'submitUpdate' => __('Mettre à jour', 'mj-member'),
            'cancel' => __('Annuler', 'mj-member'),
            'passwordLabel' => __('Mot de passe généré :', 'mj-member'),
            'copySuccess' => __('Mot de passe copié dans le presse-papiers.', 'mj-member'),
            'roleLabel' => __('Rôle WordPress attribué', 'mj-member'),
            'accountPasswordLabel' => __('Mot de passe du compte WordPress', 'mj-member'),
            'accountPasswordHint' => __('Laissez vide pour générer un mot de passe automatique.', 'mj-member'),
            'chooseRolePlaceholder' => __('Sélectionnez un rôle…', 'mj-member'),
            'successLinked' => __('Le compte WordPress est maintenant lié.', 'mj-member'),
            'errorGeneric' => __('Une erreur est survenue. Merci de réessayer.', 'mj-member'),
        ),
    ));

    if (is_string($hook) && strpos($hook, 'mj_send_emails') !== false) {
        wp_enqueue_editor();

        $send_emails_css_path = plugin_dir_path(__FILE__) . 'css/admin-send-emails.css';
        $send_emails_css_version = file_exists($send_emails_css_path) ? filemtime($send_emails_css_path) : MJ_MEMBER_VERSION;
        wp_enqueue_style('mj-member-admin-send-emails', plugins_url('css/admin-send-emails.css', __FILE__), array(), $send_emails_css_version);

        $send_emails_js_path = plugin_dir_path(__FILE__) . 'js/admin-send-emails.js';
        $send_emails_js_version = file_exists($send_emails_js_path) ? filemtime($send_emails_js_path) : MJ_MEMBER_VERSION;
        wp_enqueue_script('mj-member-admin-send-emails', plugins_url('js/admin-send-emails.js', __FILE__), array('jquery'), $send_emails_js_version, true);

        wp_localize_script('mj-member-admin-send-emails', 'mjSendEmails', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mj_send_emails'),
            'errorLoadTemplate' => __('Impossible de charger le template. Merci de réessayer.', 'mj-member'),
            'i18n' => array(
                'prepareError' => __('Impossible de préparer l’envoi. Merci de réessayer.', 'mj-member'),
                'sendError' => __('Une erreur est survenue pendant l’envoi.', 'mj-member'),
                'logPending' => __('Envoi en cours…', 'mj-member'),
                'logSent' => __('Envoyé', 'mj-member'),
                'logFailed' => __('Échec', 'mj-member'),
                'logSkipped' => __('Ignoré', 'mj-member'),
                'skippedNoEmail' => __('Aucune adresse email valide pour ce membre.', 'mj-member'),
                'summary' => __('Récapitulatif : %1$s envoyé(s), %2$s échec(s), %3$s ignoré(s).', 'mj-member'),
                'finished' => __('Envoi terminé.', 'mj-member'),
                'skippedTitle' => __('Destinataires ignorés (sans email) :', 'mj-member'),
                'previewShow' => __('Voir le message', 'mj-member'),
                'previewHide' => __('Masquer le message', 'mj-member'),
                'previewSubjectLabel' => __('Sujet', 'mj-member'),
                'statusTestMode' => __('Mode test', 'mj-member'),
                'summaryTestMode' => __('Mode test actif : aucun email réel ne sera envoyé.', 'mj-member'),
            ),
        ));
    }
}
add_action('admin_enqueue_scripts', 'mj_custom_admin_styles');

/**
 * Enable debug display for administrators only.
 * This won't change WP_DEBUG constants (they are defined in wp-config.php),
 * but will enable PHP error display and reporting for users with manage_options.
 */
add_action('init', 'mj_enable_admin_debug', 1);
function mj_enable_admin_debug() {
    // Only attempt when WP user system is available
    if (!function_exists('is_user_logged_in')) return;

    if (is_user_logged_in() && current_user_can('manage_options')) {
        @ini_set('display_errors', 1);
        @ini_set('display_startup_errors', 1);
        error_reporting(E_ALL);

        // If WP_DEBUG_LOG isn't enabled we still ensure there's a log file in wp-content
        if (!defined('WP_DEBUG_LOG') || (defined('WP_DEBUG_LOG') && !WP_DEBUG_LOG)) {
            @ini_set('error_log', WP_CONTENT_DIR . '/debug.log');
        }

        // Show an admin notice so it's clear that debug is on for this user
        add_action('admin_notices', function() {
            echo '<div class="notice notice-info is-dismissible"><p><strong>Mode debug activé :</strong> affichage des erreurs activé pour l\'administrateur courant.</p></div>';
        });
    }
}

// Action AJAX pour l'édition inline
add_action('wp_ajax_mj_inline_edit_member', 'mj_inline_edit_member_callback');
add_action('wp_ajax_mj_link_member_user', 'mj_link_member_user_callback');
add_action('wp_ajax_mj_get_email_template', 'mj_member_get_email_template_callback');
add_action('wp_ajax_mj_member_prepare_email_send', 'mj_member_prepare_email_send_callback');
add_action('wp_ajax_mj_member_send_single_email', 'mj_member_send_single_email_callback');

// AJAX: generate payment QR for admin preview (creates payment record but does not send emails)
function mj_link_member_user_callback() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mj_link_member_user')) {
        wp_send_json_error(array('message' => __('Vérification de sécurité échouée.', 'mj-member')), 403);
    }

    if (!current_user_can(MJ_MEMBER_CAPABILITY) || (!current_user_can('create_users') && !current_user_can('promote_users'))) {
        wp_send_json_error(array('message' => __('Accès refusé.', 'mj-member')), 403);
    }

    $member_id = isset($_POST['member_id']) ? intval($_POST['member_id']) : 0;
    $manual_password = isset($_POST['manual_password']) ? wp_unslash($_POST['manual_password']) : '';
    $target_role = isset($_POST['role']) ? sanitize_key($_POST['role']) : '';

    if ($member_id <= 0) {
        wp_send_json_error(array('message' => __('Identifiant membre manquant.', 'mj-member')));
    }

    $editable_roles = function_exists('get_editable_roles') ? get_editable_roles() : array();
    if (empty($target_role) || !isset($editable_roles[$target_role])) {
        wp_send_json_error(array('message' => __('Rôle sélectionné invalide.', 'mj-member')));
    }

    $member = MjMembers_CRUD::getById($member_id);
    if (!$member) {
        wp_send_json_error(array('message' => __('Membre introuvable.', 'mj-member')));
    }

    $existing_user = null;
    if (!empty($member->wp_user_id)) {
        $existing_user = get_user_by('id', (int) $member->wp_user_id);
    }
    if (!$existing_user && !empty($member->email)) {
        $existing_user = get_user_by('email', $member->email);
    }

    if (!$existing_user && empty($member->email)) {
        wp_send_json_error(array('message' => __('L’adresse e-mail du membre est requise pour créer un compte.', 'mj-member')));
    }

    $user_created = false;
    $generated_password = '';
    $user_login = '';

    if ($existing_user) {
        if (!current_user_can('promote_user', $existing_user->ID)) {
            wp_send_json_error(array('message' => __('Vous n’avez pas les droits suffisants pour modifier ce compte utilisateur.', 'mj-member')), 403);
        }

        $update_data = array('ID' => $existing_user->ID, 'role' => $target_role);
        if (!empty($member->first_name)) {
            $update_data['first_name'] = $member->first_name;
        }
        if (!empty($member->last_name)) {
            $update_data['last_name'] = $member->last_name;
        }

        $updated = wp_update_user($update_data);
        if (is_wp_error($updated)) {
            wp_send_json_error(array('message' => $updated->get_error_message()));
        }

        $user_id = $existing_user->ID;
    } else {
        if (!current_user_can('create_users')) {
            wp_send_json_error(array('message' => __('Vous n’avez pas les droits pour créer des utilisateurs.', 'mj-member')), 403);
        }

        $candidates = array();
        if (!empty($member->email)) {
            $email_login = sanitize_user(current(explode('@', $member->email)), true);
            if (!empty($email_login)) {
                $candidates[] = $email_login;
            }
        }

        $name_login = sanitize_user($member->first_name . '.' . $member->last_name, true);
        if (!empty($name_login)) {
            $candidates[] = $name_login;
        }

        $fallback_login = 'member' . $member->id;
        $candidates[] = sanitize_user($fallback_login, true);

        foreach ($candidates as $candidate) {
            if ($candidate === '') {
                continue;
            }
            $login_candidate = $candidate;
            $suffix = 1;
            while (username_exists($login_candidate)) {
                $login_candidate = $candidate . $suffix;
                $suffix++;
            }
            $user_login = $login_candidate;
            break;
        }

        if ($user_login === '') {
            $user_login = 'member' . $member->id;
            $suffix = 1;
            while (username_exists($user_login)) {
                $user_login = 'member' . $member->id . '_' . $suffix;
                $suffix++;
            }
        }

        $manual_password = is_string($manual_password) ? trim($manual_password) : '';
        if ($manual_password !== '') {
            if (strlen($manual_password) < 8) {
                wp_send_json_error(array('message' => __('Le mot de passe doit contenir au moins 8 caractères.', 'mj-member')));
            }
            if (strlen($manual_password) > 128) {
                $manual_password = substr($manual_password, 0, 128);
            }
        }

        $generated_password = $manual_password !== '' ? $manual_password : wp_generate_password(12, true, false);
        $user_id = wp_insert_user(array(
            'user_login' => $user_login,
            'user_email' => $member->email,
            'user_pass' => $generated_password,
            'first_name' => $member->first_name,
            'last_name' => $member->last_name,
            'role' => $target_role,
        ));

        if (is_wp_error($user_id)) {
            wp_send_json_error(array('message' => $user_id->get_error_message()));
        }

        $user_created = true;
    }

    if (!$user_created && is_string($manual_password)) {
        $manual_password = trim($manual_password);
        if ($manual_password !== '') {
            if (strlen($manual_password) < 8) {
                wp_send_json_error(array('message' => __('Le mot de passe doit contenir au moins 8 caractères.', 'mj-member')));
            }
            if (strlen($manual_password) > 128) {
                $manual_password = substr($manual_password, 0, 128);
            }

            wp_set_password($manual_password, $existing_user ? $existing_user->ID : $user_id);
            $generated_password = $manual_password;
        }
    }

    MjMembers_CRUD::update($member_id, array('wp_user_id' => $user_id));

    $response = array(
        'user_id' => $user_id,
        'created' => $user_created,
        'message' => $user_created
            ? __('Compte WordPress créé et lié avec succès.', 'mj-member')
            : __('Compte WordPress mis à jour et lié avec succès.', 'mj-member'),
        'user_edit_url' => get_edit_user_link($user_id),
        'role' => $target_role,
        'role_label' => isset($editable_roles[$target_role]['name']) ? translate_user_role($editable_roles[$target_role]['name']) : $target_role,
    );

    $user_object = get_user_by('id', $user_id);
    if ($user_object) {
        $response['login'] = $user_object->user_login;
    }

    if (!empty($generated_password)) {
        $response['generated_password'] = $generated_password;
    }

    wp_send_json_success($response);
}

function mj_member_get_email_template_callback() {
    if (!current_user_can(MJ_MEMBER_CAPABILITY)) {
        wp_send_json_error(array('message' => __('Accès refusé.', 'mj-member')), 403);
    }

    check_ajax_referer('mj_send_emails', 'nonce');

    $template_id = isset($_POST['template_id']) ? sanitize_text_field(wp_unslash($_POST['template_id'])) : '';
    if ($template_id === '') {
        wp_send_json_error(array('message' => __('Template introuvable.', 'mj-member')));
    }

    $template = MjMail::get_template_by($template_id);
    if (!$template) {
        wp_send_json_error(array('message' => __('Template introuvable.', 'mj-member')));
    }

    $subject = isset($template->subject) ? $template->subject : (isset($template->sujet) ? $template->sujet : '');
    $content = isset($template->content) ? $template->content : (isset($template->text) ? $template->text : '');

    wp_send_json_success(array(
        'subject' => $subject,
        'content' => $content,
    ));
}

add_action('wp_ajax_mj_admin_get_qr', 'mj_admin_get_qr_callback');
function mj_admin_get_qr_callback() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mj_admin_payments_nonce')) {
        wp_send_json_error('Nonce invalide');
    }
    if (!current_user_can(MJ_MEMBER_CAPABILITY)) {
        wp_send_json_error('Accès non autorisé');
    }
    $member_id = isset($_POST['member_id']) ? intval($_POST['member_id']) : 0;
    if ($member_id <= 0) {
        wp_send_json_error('Member missing');
    }
    require_once plugin_dir_path(__FILE__) . 'includes/classes/MjPayments.php';
    $info = MjPayments::create_payment_record($member_id);
    if (!$info) wp_send_json_error('Erreur création paiement');
    
    // SÉCURITÉ: Filtrer la réponse pour exclure toute information sensible
    $safe_response = array(
        'payment_id' => isset($info['payment_id']) ? $info['payment_id'] : null,
        'stripe_session_id' => isset($info['stripe_session_id']) ? $info['stripe_session_id'] : null,
        'checkout_url' => isset($info['checkout_url']) ? $info['checkout_url'] : (isset($info['confirm_url']) ? $info['confirm_url'] : null),
        'qr_url' => isset($info['qr_url']) ? $info['qr_url'] : null,
        'amount' => isset($info['amount']) ? $info['amount'] : '2.00'
    );
    
    wp_send_json_success($safe_response);
}

// Action AJAX pour l'upload de photo
add_action('wp_ajax_mj_upload_member_photo', 'mj_upload_member_photo_callback');
function mj_inline_edit_member_callback() {
    // Vérifier le nonce avec une meilleure gestion d'erreur
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mj_inline_edit_nonce')) {
        wp_send_json_error(array('message' => 'Vérification de sécurité échouée'));
    }
    
    if (!current_user_can(MJ_MEMBER_CAPABILITY)) {
        wp_send_json_error(array('message' => 'Accès non autorisé'));
    }
    
    if (isset($_POST['member_id']) && isset($_POST['field_name'])) {
        $member_id = intval($_POST['member_id']);
        $field_name = sanitize_text_field($_POST['field_name']);
        $field_value = isset($_POST['field_value']) ? wp_unslash($_POST['field_value']) : '';

        if ($member_id <= 0) {
            wp_send_json_error(array('message' => 'Identifiant membre invalide'));
        }

        $allowed_fields = array(
            'first_name',
            'last_name',
            'email',
            'phone',
            'role',
            'status',
            'date_last_payement',
            'requires_payment',
            'photo_usage_consent',
            'photo_id'
        );

        if (!in_array($field_name, $allowed_fields, true)) {
            wp_send_json_error(array('message' => 'Champ non autorisé'));
        }

        $member = MjMembers_CRUD::getById($member_id);
        if (!$member) {
            wp_send_json_error(array('message' => 'Membre introuvable'));
        }

        switch ($field_name) {
            case 'email':
                $raw_email = trim((string) $field_value);
                $sanitized_email = sanitize_email($field_value);

                if ($sanitized_email === '') {
                    if ($raw_email === '' && $member->role === MjMembers_CRUD::ROLE_JEUNE) {
                        $field_value = null;
                        break;
                    }

                    wp_send_json_error(array('message' => 'Email invalide'));
                }

                if (!is_email($sanitized_email)) {
                    wp_send_json_error(array('message' => 'Email invalide'));
                }

                $field_value = $sanitized_email;
                break;
            case 'first_name':
            case 'last_name':
                $field_value = sanitize_text_field($field_value);
                if ($field_value === '') {
                    wp_send_json_error(array('message' => 'Valeur invalide'));
                }
                break;
            case 'phone':
                $field_value = sanitize_text_field($field_value);
                break;
            case 'role':
                $field_value = sanitize_text_field($field_value);
                $allowed_roles = MjMembers_CRUD::getAllowedRoles();
                if (!in_array($field_value, $allowed_roles, true)) {
                    wp_send_json_error(array('message' => 'Rôle invalide'));
                }
                if ($field_value !== MjMembers_CRUD::ROLE_JEUNE && empty($member->email)) {
                    wp_send_json_error(array('message' => 'Ajoutez un email avant de changer le rôle.'));
                }
                break;
            case 'status':
                $field_value = sanitize_text_field($field_value);
                if (!in_array($field_value, array(MjMembers_CRUD::STATUS_ACTIVE, MjMembers_CRUD::STATUS_INACTIVE), true)) {
                    wp_send_json_error(array('message' => 'Statut invalide'));
                }
                break;
            case 'date_last_payement':
                $field_value = sanitize_text_field($field_value);
                break;
            case 'requires_payment':
                $field_value = (!empty($field_value) && $field_value !== '0' && strtolower($field_value) !== 'false') ? 1 : 0;
                break;
            case 'photo_usage_consent':
                $normalized = strtolower(trim($field_value));
                if (in_array($normalized, array('accepté', 'accepte', 'oui', 'yes', '1', 'true'), true)) {
                    $field_value = 1;
                } elseif (in_array($normalized, array('refusé', 'refuse', 'non', 'no', '0', 'false'), true)) {
                    $field_value = 0;
                } else {
                    $field_value = !empty($field_value) ? 1 : 0;
                }
                break;
            case 'photo_id':
                $field_value = intval($field_value);
                if ($field_value <= 0) {
                    $field_value = null;
                }
                break;
            default:
                $field_value = sanitize_text_field($field_value);
        }

        $data = array($field_name => $field_value);
        $result = MjMembers_CRUD::update($member_id, $data);

        if ($result !== false) {
            wp_send_json_success(array(
                'message' => 'Mise à jour réussie',
                'value' => $field_value
            ));
        } else {
            wp_send_json_error(array('message' => 'Erreur lors de la mise à jour'));
        }
    } else {
        wp_send_json_error(array('message' => 'Données manquantes'));
    }
}

// Action AJAX pour l'upload de photo
function mj_upload_member_photo_callback() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mj_photo_upload_nonce')) {
        wp_send_json_error(array('message' => 'Vérification de sécurité échouée'));
    }
    
    if (!current_user_can(MJ_MEMBER_CAPABILITY)) {
        wp_send_json_error(array('message' => 'Accès non autorisé'));
    }
    
    if (!isset($_POST['member_id']) || !isset($_POST['attachment_id'])) {
        wp_send_json_error(array('message' => 'Données manquantes'));
    }
    
    $member_id = intval($_POST['member_id']);
    $attachment_id = intval($_POST['attachment_id']);
    
    // Vérifier que le membre existe
    $member = MjMembers_CRUD::getById($member_id);
    if (!$member) {
        wp_send_json_error(array('message' => 'Membre introuvable'));
    }
    
    // Vérifier que l'attachment existe
    if (!get_post($attachment_id)) {
        wp_send_json_error(array('message' => 'Pièce jointe introuvable'));
    }
    
    // Sauvegarder l'ID de la photo dans la base de données
    global $wpdb;
    $table_name = $wpdb->prefix . 'mj_members';
    
    $result = $wpdb->update(
        $table_name,
        array('photo_id' => $attachment_id),
        array('id' => $member_id),
        array('%d'),
        array('%d')
    );
    
    if ($result !== false) {
        $image_url = wp_get_attachment_image_src($attachment_id, 'thumbnail');
        wp_send_json_success(array(
            'attachment_id' => $attachment_id,
            'image_url' => $image_url[0]
        ));
    } else {
        wp_send_json_error(array('message' => 'Erreur lors de la sauvegarde de la photo: ' . $wpdb->last_error));
    }
}

// Ajouter le menu admin
add_action('admin_menu', 'mj_add_admin_menu');
function mj_add_admin_menu() {
    // Top level menu 'Mj Péry'
    $parent = add_menu_page(
        'Maison de Jeune',
        'Maison de Jeune',
        MJ_MEMBER_CAPABILITY,
        'mj_member',
        'mj_members_page',
        'dashicons-admin-site',
        30
    );


    // Submenu alias to support links with page=mj_members
    add_submenu_page('mj_member', 'Membres', 'Membres', MJ_MEMBER_CAPABILITY, 'mj_members', 'mj_members_page');

    // Submenu: Événements / Stages
    add_submenu_page('mj_member', 'Événements', 'Événements', MJ_MEMBER_CAPABILITY, 'mj_events', 'mj_events_page');

    // Submenu: Lieux d'événements
    add_submenu_page('mj_member', 'Lieux', 'Lieux', MJ_MEMBER_CAPABILITY, 'mj_locations', 'mj_event_locations_page');

    // Submenu: Template emails
    add_submenu_page('mj_member', 'Template emails', 'Template emails', MJ_MEMBER_CAPABILITY, 'mj_email_templates', 'mj_email_templates_page');

    // Submenu: Envoye email
    add_submenu_page('mj_member', 'Envoye email', 'Envoye email', MJ_MEMBER_CAPABILITY, 'mj_send_emails', 'mj_send_emails_page');

    // Submenu: Configuration
    add_submenu_page('mj_member', 'Configuration', 'Configuration', 'manage_options', 'mj_settings', 'mj_settings_page');
}

// Page admin
function mj_members_page() {
    ?>
    <div class="wrap">
        <h1>Gestion des Membres</h1>
        <?php
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        
        if ($action === 'add' || $action === 'edit') {
            require plugin_dir_path(__FILE__) . 'includes/form_member.php';
        } else {
            require plugin_dir_path(__FILE__) . 'includes/table_members.php';
        }
        ?>
    </div>
    <?php
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
    
    // Ajouter des données de test
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
    
    // Vérifier s'il y a déjà des données
    $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    if ($count > 0) {
        return; // Ne pas ajouter de données si la table n'est pas vide
    }
    
    $now = current_time('mysql');

    // Créer un tuteur de démonstration
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
        'joined_date' => $now
    ), array('%s','%s','%s','%s','%s','%s','%d','%d','%s','%s','%s','%s','%s'));

    $guardian_id = intval($wpdb->insert_id);

    // Créer un jeune lié au tuteur
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
        'joined_date' => $now
    ), array('%s','%s','%s','%s','%s','%s','%d','%d','%d','%s','%s','%s','%s'));
}

function mj_uninstall()
{
    mj_member_remove_capabilities();

    global $wpdb;
    $table_name = $wpdb->prefix . 'mj_members';
    $payments_table = $wpdb->prefix . 'mj_payments';
    $payment_history_table = $wpdb->prefix . 'mj_payment_history';

    // Supprimer les tables dans le bon ordre (les tables dépendantes d'abord)
    $wpdb->query("DROP TABLE IF EXISTS $payment_history_table");
    $wpdb->query("DROP TABLE IF EXISTS $payments_table");
    $wpdb->query("DROP TABLE IF EXISTS $table_name");
}

// Handle payment confirmation via GET token
add_action('init', 'mj_handle_payment_confirmation');
function mj_handle_payment_confirmation() {
    if (!empty($_GET['mj_payment_confirm'])) {
        $token = sanitize_text_field($_GET['mj_payment_confirm']);
        require_once plugin_dir_path(__FILE__) . 'includes/classes/MjPayments.php';
        $ok = MjPayments::confirm_payment_by_token($token);
        // Simple feedback page
        if ($ok) {
            wp_redirect(add_query_arg('mj_payment_status', 'ok', remove_query_arg('mj_payment_confirm')));
            exit;
        } else {
            wp_redirect(add_query_arg('mj_payment_status', 'error', remove_query_arg('mj_payment_confirm')));
            exit;
        }
    }
}

// Register AJAX action for fetching payment history
add_action('wp_ajax_mj_admin_get_payment_history', 'mj_admin_get_payment_history');
add_action('wp_ajax_mj_admin_delete_payment', 'mj_admin_delete_payment');
add_action('wp_ajax_mj_member_mark_paid', 'mj_member_mark_paid');

function mj_admin_get_payment_history() {
    // Verify nonce and permissions
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mj_admin_payments_nonce')) {
        wp_send_json_error('Nonce invalide.');
    }
    if (!current_user_can(MJ_MEMBER_CAPABILITY)) {
        wp_send_json_error('Permissions insuffisantes.');
    }

    $member_id = isset($_POST['member_id']) ? intval($_POST['member_id']) : 0;
    if (!$member_id) {
        wp_send_json_error('ID membre invalide.');
    }

    global $wpdb;
    $history_table = $wpdb->prefix . 'mj_payment_history';
    $payments_table = $wpdb->prefix . 'mj_payments';

    // Prefer detailed history table if available
    $history = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT h.id AS history_id, h.payment_date, h.amount, h.method, h.reference, h.payer_id, p.id AS payment_id
             FROM $history_table h
             LEFT JOIN $payments_table p ON (p.external_ref = h.reference OR CAST(p.id AS CHAR) = h.reference) AND p.member_id = h.member_id
             WHERE h.member_id = %d
             ORDER BY h.payment_date DESC",
            $member_id
        )
    );

    $entries = array();

    if ($history) {
        foreach ($history as $row) {
            $entries[] = array(
                'date' => $row->payment_date ? date_i18n('d/m/Y H:i', strtotime($row->payment_date)) : __('Inconnue', 'mj-member'),
                'amount' => number_format((float)$row->amount, 2),
                'reference' => $row->reference ? sanitize_text_field($row->reference) : __('N/A', 'mj-member'),
                'method' => $row->method ? sanitize_text_field($row->method) : __('Inconnue', 'mj-member'),
                'status' => $row->payment_id ? sanitize_text_field($row->method) : '',
                'status_label' => $row->payment_id ? mj_format_payment_status($row->method) : __('ℹ️ Entrée historique (hors suivi Stripe)', 'mj-member'),
                'history_id' => isset($row->history_id) ? intval($row->history_id) : 0,
                'payment_id' => $row->payment_id ? intval($row->payment_id) : null,
                'payer_id' => isset($row->payer_id) ? (int) $row->payer_id : 0,
            );
        }
    } else {
        // Fallback to mj_payments table if no history entries exist
        $payments = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id AS payment_id, COALESCE(paid_at, created_at) AS date_ref, amount, external_ref, status, payer_id
                 FROM $payments_table
                 WHERE member_id = %d
                 ORDER BY created_at DESC",
                $member_id
            )
        );

        if ($payments) {
            foreach ($payments as $row) {
                $entries[] = array(
                    'date' => $row->date_ref ? date_i18n('d/m/Y H:i', strtotime($row->date_ref)) : __('Inconnue', 'mj-member'),
                    'amount' => number_format((float)$row->amount, 2),
                    'reference' => $row->external_ref ? sanitize_text_field($row->external_ref) : __('N/A', 'mj-member'),
                    'status' => sanitize_text_field($row->status),
                    'status_label' => mj_format_payment_status($row->status),
                    'method' => __('Enregistré', 'mj-member'),
                    'history_id' => null,
                    'payment_id' => isset($row->payment_id) ? intval($row->payment_id) : 0,
                    'payer_id' => isset($row->payer_id) ? (int) $row->payer_id : 0,
                );
            }
        }
    }

    wp_send_json_success([
        'payments' => $entries,
        'can_delete' => current_user_can(MJ_MEMBER_CAPABILITY)
    ]);
}

function mj_format_payment_status($status) {
    $status = strtolower(trim((string)$status));

    switch ($status) {
        case 'paid':
        case 'succeeded':
        case 'completed':
            return __('✅ Payé – paiement confirmé par Stripe/Webhook', 'mj-member');

        case 'pending':
        case 'requires_payment_method':
        case 'requires_action':
            return __('⏳ En attente – paiement créé, en cours de confirmation', 'mj-member');

        case 'canceled':
        case 'cancelled':
            return __('🚫 Annulé – paiement annulé ou expiré', 'mj-member');

        case 'failed':
        case 'requires_payment_method_failed':
            return __('❌ Échec – tentative de paiement refusée', 'mj-member');

        default:
            return __('ℹ️ Statut inconnu / historique importé', 'mj-member');
    }
}

function mj_admin_delete_payment() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mj_admin_payments_nonce')) {
        wp_send_json_error('Nonce invalide.');
    }
    if (!current_user_can(MJ_MEMBER_CAPABILITY)) {
        wp_send_json_error('Permissions insuffisantes.');
    }

    $member_id = isset($_POST['member_id']) ? intval($_POST['member_id']) : 0;
    $history_id = isset($_POST['history_id']) ? intval($_POST['history_id']) : 0;
    $payment_id = isset($_POST['payment_id']) ? intval($_POST['payment_id']) : 0;

    if (!$member_id || (!$history_id && !$payment_id)) {
        wp_send_json_error('Données invalides.');
    }

    global $wpdb;
    $history_table = $wpdb->prefix . 'mj_payment_history';
    $payments_table = $wpdb->prefix . 'mj_payments';

    $deleted = false;

    if ($history_id) {
        $deleted = (false !== $wpdb->delete($history_table, array('id' => $history_id, 'member_id' => $member_id), array('%d', '%d')));
    }

    if ($payment_id) {
        $deleted_payment = (false !== $wpdb->delete($payments_table, array('id' => $payment_id, 'member_id' => $member_id), array('%d', '%d')));
        $deleted = $deleted || $deleted_payment;
    }

    if (!$deleted) {
        wp_send_json_error('Aucune ligne supprimée.');
    }

    wp_send_json_success();
}

function mj_member_mark_paid() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mj_admin_payments_nonce')) {
        wp_send_json_error(array('message' => __('Nonce invalide.', 'mj-member')), 403);
    }

    if (!current_user_can(MJ_MEMBER_CAPABILITY)) {
        wp_send_json_error(array('message' => __('Permissions insuffisantes.', 'mj-member')), 403);
    }

    $member_id = isset($_POST['member_id']) ? intval($_POST['member_id']) : 0;
    if ($member_id <= 0) {
        wp_send_json_error(array('message' => __('ID membre invalide.', 'mj-member')));
    }

    $member = MjMembers_CRUD::getById($member_id);
    if (!$member) {
        wp_send_json_error(array('message' => __('Membre introuvable.', 'mj-member')));
    }

    $admin_user_id = get_current_user_id();

    $now = current_time('mysql');

    $update_payload = array(
        'date_last_payement' => $now,
        'status' => MjMembers_CRUD::STATUS_ACTIVE,
    );

    $updated = MjMembers_CRUD::update($member_id, $update_payload);
    if ($updated === false) {
        wp_send_json_error(array('message' => __('Impossible de mettre à jour la fiche membre.', 'mj-member')));
    }

    global $wpdb;
    $history_table = $wpdb->prefix . 'mj_payment_history';
    $table_like = $wpdb->esc_like($history_table);
    $table_check = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_like));
    if ($table_check === $history_table) {
        $amount = (float) apply_filters('mj_member_membership_amount', (float) get_option('mj_annual_fee', '2.00'), $member);
        $amount_formatted = number_format($amount, 2, '.', '');
        $reference = 'manual-' . wp_generate_password(8, false, false);
        $history_data = array(
            'member_id' => $member_id,
            'amount' => $amount_formatted,
            'payment_date' => $now,
            'method' => 'manual_admin',
            'reference' => $reference,
        );
        $history_format = array('%d', '%f', '%s', '%s', '%s');

        if ($admin_user_id > 0) {
            $history_data = array(
                'member_id' => $member_id,
                'payer_id' => $admin_user_id,
                'amount' => $amount_formatted,
                'payment_date' => $now,
                'method' => 'manual_admin',
                'reference' => $reference,
            );
            $history_format = array('%d', '%d', '%f', '%s', '%s', '%s');
        }

        $wpdb->insert(
            $history_table,
            $history_data,
            $history_format
        );
    }

    $updated_member = MjMembers_CRUD::getById($member_id);
    $date_display = ($updated_member && !empty($updated_member->date_last_payement)) ? wp_date('d/m/Y', strtotime($updated_member->date_last_payement)) : '';
    $status_label = ($updated_member && $updated_member->status === MjMembers_CRUD::STATUS_ACTIVE) ? __('Actif', 'mj-member') : __('Inactif', 'mj-member');

    $admin_name = '';
    if ($admin_user_id > 0) {
        $user_obj = get_userdata($admin_user_id);
        if ($user_obj) {
            $admin_name = $user_obj->display_name ?: $user_obj->user_login;
        }
    }

    $response = array(
        'message' => __('Cotisation enregistrée.', 'mj-member'),
        'date_last_payement' => $date_display,
        'status_label' => $status_label,
        'recorded_by' => array(
            'id' => $admin_user_id,
            'name' => $admin_name,
        ),
    );

    if ($updated_member && function_exists('mj_member_get_membership_status')) {
        $status_info = mj_member_get_membership_status($updated_member);
        if (is_array($status_info)) {
            $response['membership_status'] = $status_info['status_label'];
            $response['membership_status_key'] = $status_info['status'];
        }
    }

    wp_send_json_success($response);
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
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY idx_status (status),
        KEY idx_date_debut (date_debut)
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

    if (mj_member_table_exists($events_table) && !mj_member_column_exists($events_table, 'location_id')) {
        $wpdb->query("ALTER TABLE $events_table ADD COLUMN location_id bigint(20) unsigned DEFAULT NULL AFTER prix");
    }

    if (mj_member_table_exists($events_table) && !mj_member_index_exists($events_table, 'idx_location')) {
        $wpdb->query("ALTER TABLE $events_table ADD KEY idx_location (location_id)");
    }

    mj_member_seed_default_locations($wpdb, $locations_table);
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

function mj_events_page() {
    ?>
    <div class="wrap">
        <h1>Gestion des événements &amp; stages</h1>
        <?php
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';

        if ($action === 'add' || $action === 'edit') {
            require plugin_dir_path(__FILE__) . 'includes/form_event.php';
        } else {
            require plugin_dir_path(__FILE__) . 'includes/table_events.php';
        }
        ?>
    </div>
    <?php
}

function mj_event_locations_page() {
    ?>
    <div class="wrap">
        <h1>Gestion des lieux</h1>
        <?php require plugin_dir_path(__FILE__) . 'includes/locations_page.php'; ?>
    </div>
    <?php
}