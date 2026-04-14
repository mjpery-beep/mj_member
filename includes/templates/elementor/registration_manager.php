<?php
/**
 * Template du widget Gestionnaire d'inscriptions
 * 
 * @package MjMember
 */

if (!defined('ABSPATH')) {
    exit;
}

use Mj\Member\Classes\Crud\MjEvents;
use Mj\Member\Classes\Crud\MjEventRegistrations;
use Mj\Member\Classes\Crud\MjMembers;
use Mj\Member\Classes\Crud\MjEventAttendance;
use Mj\Member\Classes\Crud\MjLeaveTypes;
use Mj\Member\Classes\MjRoles;
use Mj\Member\Classes\MjNextcloud;
use Mj\Member\Classes\View\CreateEventModalRenderer;
use Mj\Member\Core\AssetsManager;
use Mj\Member\Core\Config;
use Mj\Member\Classes\MjOpenAIClient;

$settings = $this->get_settings_for_display();
$widget_id = 'mj-registration-manager-' . $this->get_id();

$elementor_plugin = \Elementor\Plugin::$instance;
$editor_is_edit = isset($elementor_plugin->editor) && method_exists($elementor_plugin->editor, 'is_edit_mode')
    ? (bool) $elementor_plugin->editor->is_edit_mode()
    : false;
$preview_is_active = isset($elementor_plugin->preview) && method_exists($elementor_plugin->preview, 'is_preview_mode')
    ? (bool) $elementor_plugin->preview->is_preview_mode()
    : false;
$settings_preview_flag = !empty($settings['__is_preview_mode']);
$is_preview = $editor_is_edit || $preview_is_active || $settings_preview_flag;

AssetsManager::requirePackage('registration-manager');

$title = !empty($settings['title']) ? $settings['title'] : __('Gestion des inscriptions', 'mj-member');
$show_all_events = !empty($settings['show_all_events']) && $settings['show_all_events'] === 'yes';
$show_past = !empty($settings['show_past_events']) && $settings['show_past_events'] === 'yes';
$per_page = !empty($settings['events_per_page']) ? (int) $settings['events_per_page'] : 10;
$default_filter = !empty($settings['default_filter']) ? $settings['default_filter'] : 'upcoming';
$allow_manual_payment = !empty($settings['allow_manual_payment']) && $settings['allow_manual_payment'] === 'yes';
$allow_delete_registration = !empty($settings['allow_delete_registration']) && $settings['allow_delete_registration'] === 'yes';
$allow_create_member = !empty($settings['allow_create_member']) && $settings['allow_create_member'] === 'yes';

$is_coordinateur = false;
$can_manage_accounts = current_user_can(Config::capability()) && (current_user_can('create_users') || current_user_can('promote_users'));
$nextcloud_available = MjNextcloud::isAvailable();
$can_manage_nextcloud = (current_user_can(Config::capability()) || $is_coordinateur) && $nextcloud_available;
$account_roles = array();
if ($can_manage_accounts && function_exists('get_editable_roles')) {
    $editable_roles = get_editable_roles();
    foreach ($editable_roles as $role_key => $role_data) {
        $account_roles[$role_key] = translate_user_role($role_data['name']);
    }
}

// Aperçu Elementor
if ($is_preview) {
    $preview_events = array(
        array(
            'title' => __('Atelier sérigraphie', 'mj-member'),
            'emoji' => '🎨',
            'date' => __('Samedi 12 avril · 10h-12h', 'mj-member'),
            'registrations' => '12/20',
            'price' => '8',
            'type_label' => __('Atelier', 'mj-member'),
            'type_class' => 'atelier',
            'status_label' => __('Ouvert', 'mj-member'),
            'status_class' => 'published',
            'accent' => '#2563eb',
        ),
        array(
            'title' => __('Sortie parc aventure', 'mj-member'),
            'emoji' => '🧗',
            'date' => __('Mercredi 23 avril · 14h-17h', 'mj-member'),
            'registrations' => '18/18',
            'price' => '12',
            'type_label' => __('Sortie', 'mj-member'),
            'type_class' => 'ponctuel',
            'status_label' => __('Complet', 'mj-member'),
            'status_class' => 'full',
            'accent' => '#f59e0b',
        ),
        array(
            'title' => __('Stage vidéo', 'mj-member'),
            'emoji' => '🎬',
            'date' => __('Du 6 au 8 mai', 'mj-member'),
            'registrations' => '5/12',
            'price' => '0',
            'type_label' => __('Stage', 'mj-member'),
            'type_class' => 'serie_dates',
            'status_label' => __('Brouillon', 'mj-member'),
            'status_class' => 'draft',
            'accent' => '#a855f7',
        ),
    );

    $preview_participants = array(
        array(
            'name' => 'Léa Martin',
            'email' => 'lea.martin@example.com',
            'initials' => 'LM',
            'role_class' => 'jeune',
            'status_label' => __('Validée', 'mj-member'),
            'status_class' => 'status-valide',
            'payment_label' => __('Réglé', 'mj-member'),
            'payment_class' => 'paid',
        ),
        array(
            'name' => 'Mathis Leroy',
            'email' => 'mathis.leroy@example.com',
            'initials' => 'ML',
            'role_class' => 'jeune',
            'status_label' => __('En attente', 'mj-member'),
            'status_class' => 'status-en_attente',
            'payment_label' => __('À encaisser', 'mj-member'),
            'payment_class' => 'pending',
        ),
        array(
            'name' => 'Sophie Bernard',
            'email' => 'sophie.bernard@example.com',
            'initials' => 'SB',
            'role_class' => 'benevole',
            'status_label' => __('Validée', 'mj-member'),
            'status_class' => 'status-valide',
            'payment_label' => __('Non requis', 'mj-member'),
            'payment_class' => 'paid',
        ),
    );
    ?>
    <div class="mj-registration-manager mj-registration-manager--preview" data-widget-id="<?php echo esc_attr($widget_id); ?>">
        <div class="mj-registration-manager__preview-note">
            <?php esc_html_e('Aperçu statique : les données affichées sont fictives. Le widget final charge ses informations après publication.', 'mj-member'); ?>
        </div>
        <div class="mj-regmgr mj-regmgr--preview" aria-hidden="true">
            <div class="mj-regmgr__layout">
                <aside class="mj-regmgr-sidebar" aria-label="<?php esc_attr_e('Aperçu de la liste des événements', 'mj-member'); ?>">
                    <div class="mj-regmgr-sidebar__header">
                        <div class="mj-regmgr-sidebar__mode-tabs" role="tablist">
                            <button type="button" class="mj-regmgr-sidebar__mode-tab mj-regmgr-sidebar__mode-tab--active" role="tab" aria-selected="true" disabled>
                                <span class="mj-regmgr-sidebar__mode-tab-emoji" aria-hidden="true">📅</span>
                                <span><?php esc_html_e('Événements', 'mj-member'); ?></span>
                            </button>
                            <button type="button" class="mj-regmgr-sidebar__mode-tab" role="tab" aria-selected="false" disabled>
                                <span class="mj-regmgr-sidebar__mode-tab-emoji" aria-hidden="true">👥</span>
                                <span><?php esc_html_e('Membres', 'mj-member'); ?></span>
                            </button>
                        </div>
                    </div>
                    <div class="mj-regmgr-sidebar__search">
                        <div class="mj-regmgr-search-input">
                            <svg class="mj-regmgr-search-input__icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8"></circle>
                                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                            </svg>
                            <input type="text" class="mj-regmgr-search-input__field" placeholder="<?php esc_attr_e('Rechercher un événement...', 'mj-member'); ?>" disabled />
                        </div>
                    </div>
                    <div class="mj-regmgr-sidebar__filters">
                        <div class="mj-regmgr-filter-tabs" role="tablist">
                            <button type="button" class="mj-regmgr-filter-tab mj-regmgr-filter-tab--active" role="tab" aria-selected="true" disabled><?php esc_html_e('Assignés', 'mj-member'); ?></button>
                            <button type="button" class="mj-regmgr-filter-tab" role="tab" aria-selected="false" disabled><?php esc_html_e('À venir', 'mj-member'); ?></button>
                            <button type="button" class="mj-regmgr-filter-tab" role="tab" aria-selected="false" disabled><?php esc_html_e('Passés', 'mj-member'); ?></button>
                            <button type="button" class="mj-regmgr-filter-tab" role="tab" aria-selected="false" disabled><?php esc_html_e('Brouillons', 'mj-member'); ?></button>
                            <button type="button" class="mj-regmgr-filter-tab" role="tab" aria-selected="false" disabled><?php esc_html_e('Internes', 'mj-member'); ?></button>
                        </div>
                    </div>
                    <div class="mj-regmgr-sidebar__list">
                        <div class="mj-regmgr-events-list">
                            <?php foreach ($preview_events as $index => $event) : ?>
                                <?php
                                $preview_title = !empty($event['title']) ? (string) $event['title'] : __('Sans titre', 'mj-member');
                                $preview_emoji = !empty($event['emoji']) && is_string($event['emoji']) ? (string) $event['emoji'] : '';
                                $preview_title_label = $preview_emoji !== '' ? trim($preview_emoji . ' ' . $preview_title) : $preview_title;
                                ?>
                                <div class="mj-regmgr-event-card<?php echo $index === 0 ? ' mj-regmgr-event-card--selected' : ''; ?>">
                                    <?php if (!empty($event['accent'])) : ?>
                                        <div class="mj-regmgr-event-card__accent" style="background-color: <?php echo esc_attr($event['accent']); ?>"></div>
                                    <?php endif; ?>
                                    <div class="mj-regmgr-event-card__content">
                                        <div class="mj-regmgr-event-card__header">
                                            <span class="mj-regmgr-event-card__type mj-regmgr-event-card__type--<?php echo esc_attr($event['type_class']); ?>"><?php echo esc_html($event['type_label']); ?></span>
                                            <span class="mj-regmgr-event-card__status mj-regmgr-event-card__status--<?php echo esc_attr($event['status_class']); ?>"><?php echo esc_html($event['status_label']); ?></span>
                                        </div>
                                        <h2 class="mj-regmgr-event-card__title" aria-label="<?php echo esc_attr($preview_title_label); ?>">
                                            <?php if ($preview_emoji !== '') : ?>
                                                <span class="mj-regmgr-event-card__emoji" aria-hidden="true"><?php echo esc_html($preview_emoji); ?></span>
                                            <?php endif; ?>
                                            <span class="mj-regmgr-event-card__title-text"><?php echo esc_html($preview_title); ?></span>
                                        </h2>
                                        <div class="mj-regmgr-event-card__date">
                                            <svg class="mj-regmgr-event-card__icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                                <line x1="3" y1="10" x2="21" y2="10"></line>
                                            </svg>
                                            <span><?php echo esc_html($event['date']); ?></span>
                                        </div>
                                        <div class="mj-regmgr-event-card__footer">
                                            <div class="mj-regmgr-event-card__registrations">
                                                <svg class="mj-regmgr-event-card__icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                                    <circle cx="9" cy="7" r="4"></circle>
                                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                                </svg>
                                                <span><?php echo esc_html($event['registrations']); ?></span>
                                            </div>
                                            <?php if (!empty($event['price']) && $event['price'] !== '0') : ?>
                                                <div class="mj-regmgr-event-card__price"><?php echo esc_html($event['price']); ?> €</div>
                                            <?php else : ?>
                                                <div class="mj-regmgr-event-card__price mj-regmgr-event-card__price--free"><?php esc_html_e('Gratuit', 'mj-member'); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </aside>
                <main class="mj-regmgr__main" aria-label="<?php esc_attr_e('Aperçu de la gestion des inscriptions', 'mj-member'); ?>">
                    <div class="mj-regmgr-tabs" role="tablist">
                        <button type="button" class="mj-regmgr-tab mj-regmgr-tab--active" role="tab" aria-selected="true" disabled>
                            <span><?php esc_html_e('Inscriptions', 'mj-member'); ?></span>
                            <span class="mj-regmgr-tab__badge">8</span>
                        </button>
                        <button type="button" class="mj-regmgr-tab" role="tab" aria-selected="false" disabled>
                            <span><?php esc_html_e('Présence', 'mj-member'); ?></span>
                            <span class="mj-regmgr-tab__badge">3</span>
                        </button>
                        <button type="button" class="mj-regmgr-tab" role="tab" aria-selected="false" disabled>
                            <span><?php esc_html_e('Détails', 'mj-member'); ?></span>
                        </button>
                    </div>
                    <div class="mj-regmgr__tab-content">
                        <div class="mj-regmgr-registrations">
                            <div class="mj-regmgr-registrations__header">
                                <div class="mj-regmgr-registrations__title">
                                    <h3><?php esc_html_e('Liste des inscrits', 'mj-member'); ?></h3>
                                    <span class="mj-regmgr-registrations__count">(8)</span>
                                </div>
                                <button type="button" class="mj-btn mj-btn--primary" disabled>
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                        <circle cx="8.5" cy="7" r="4"></circle>
                                        <line x1="20" y1="8" x2="20" y2="14"></line>
                                        <line x1="23" y1="11" x2="17" y2="11"></line>
                                    </svg>
                                    <span><?php esc_html_e('Ajouter un participant', 'mj-member'); ?></span>
                                </button>
                            </div>
                            <div class="mj-regmgr-registrations__list">
                                <?php foreach ($preview_participants as $participant) : ?>
                                    <div class="mj-regmgr-reg-card">
                                        <div class="mj-regmgr-avatar mj-regmgr-avatar--role-<?php echo esc_attr($participant['role_class']); ?>">
                                            <?php echo esc_html($participant['initials']); ?>
                                        </div>
                                        <div class="mj-regmgr-reg-card__info">
                                            <div class="mj-regmgr-reg-card__name"><?php echo esc_html($participant['name']); ?></div>
                                            <div class="mj-regmgr-reg-card__email"><?php echo esc_html($participant['email']); ?></div>
                                        </div>
                                        <div class="mj-regmgr-reg-card__status">
                                            <span class="mj-regmgr-badge mj-regmgr-badge--<?php echo esc_attr($participant['status_class']); ?>"><?php echo esc_html($participant['status_label']); ?></span>
                                            <span class="mj-regmgr-reg-card__payment mj-regmgr-reg-card__payment--<?php echo esc_attr($participant['payment_class']); ?>">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
                                                    <line x1="1" y1="10" x2="23" y2="10"></line>
                                                </svg>
                                                <span><?php echo esc_html($participant['payment_label']); ?></span>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <p class="mj-regmgr-registrations__empty">
                                <?php esc_html_e('Les actions (validation, paiements, notes) seront disponibles en front-office.', 'mj-member'); ?>
                            </p>
                        </div>
                    </div>
                </main>
            </div>
        </div>
    </div>
    <?php
    return;
}

// Vérifier que l'utilisateur est connecté
if (!is_user_logged_in()) {
    ?>
    <div class="mj-registration-manager mj-registration-manager--guest">
        <p><?php esc_html_e('Vous devez être connecté pour accéder à cette fonctionnalité.', 'mj-member'); ?></p>
    </div>
    <?php
    return;
}

// Vérifier les permissions
$current_user_id = get_current_user_id();
$member = MjMembers::getByWpUserId($current_user_id);

if (!$member) {
    ?>
    <div class="mj-registration-manager mj-registration-manager--no-member">
        <p><?php esc_html_e('Vous devez avoir un profil membre pour accéder à cette fonctionnalité.', 'mj-member'); ?></p>
    </div>
    <?php
    return;
}

$member_role = isset($member->role) ? $member->role : '';
$allowed_roles = array(MjRoles::ANIMATEUR, MjRoles::BENEVOLE, MjRoles::COORDINATEUR);

if (!in_array($member_role, $allowed_roles, true) && !current_user_can('manage_options')) {
    ?>
    <div class="mj-registration-manager mj-registration-manager--forbidden">
        <p><?php esc_html_e('Vous n\'avez pas les permissions nécessaires pour accéder à cette fonctionnalité.', 'mj-member'); ?></p>
    </div>
    <?php
    return;
}

$is_coordinateur = $member_role === MjRoles::COORDINATEUR || current_user_can('manage_options');
$can_manage_children = current_user_can(Config::capability()) || $is_coordinateur;
$allow_attach_child = $can_manage_children;
$allow_create_child = $allow_create_member && $can_manage_children;

// Préparer les labels
$event_types = MjEvents::get_type_labels();
$event_statuses = MjEvents::get_status_labels();
$registration_statuses = MjEventRegistrations::get_status_labels();
$payment_statuses = MjEventRegistrations::get_payment_status_labels();
$attendance_statuses = array(
    MjEventAttendance::STATUS_PRESENT => __('Présent', 'mj-member'),
    MjEventAttendance::STATUS_ABSENT => __('Absent', 'mj-member'),
    MjEventAttendance::STATUS_PENDING => __('À confirmer', 'mj-member'),
);
$role_labels = MjRoles::getRoleLabels();

// Préparer les tranches d'âge pour les filtres
$age_ranges = array(
    '6-11' => __('6-11 ans', 'mj-member'),
    '12-14' => __('12-14 ans', 'mj-member'),
    '15-17' => __('15-17 ans', 'mj-member'),
    '18-25' => __('18-25 ans', 'mj-member'),
    '26+' => __('26 ans et +', 'mj-member'),
);

// URL de l'admin pour le bouton modifier
$admin_edit_url = admin_url('admin.php?page=mj_events&action=edit&event=');
$admin_add_url = admin_url('admin.php?page=mj_events&action=add');
$admin_member_url = admin_url('admin.php?page=mj_members&action=edit&id=');

$social_n8n_enabled = get_option('mj_social_n8n_enabled', '0') === '1';
$social_n8n_webhook_url = esc_url_raw((string) get_option('mj_social_n8n_webhook_url', ''));

// Prix de la cotisation
$membership_price = (float) get_option('mj_annual_fee', '2.00');
$membership_price_manual = get_option('mj_annual_fee_manual', '');
if ($membership_price_manual === '' || $membership_price_manual === null) {
    $membership_price_manual = $membership_price;
} else {
    $membership_price_manual = (float) $membership_price_manual;
}

// Types de congés pour l'onglet quotas
$leave_types_raw = MjLeaveTypes::get_all(['is_active' => 1]);
$leave_types = [];
foreach ($leave_types_raw as $lt) {
    $leave_types[] = [
        'id' => (int) $lt->id,
        'slug' => $lt->slug,
        'name' => $lt->name,
    ];
}

// Nonce et URL AJAX
$ajax_url = admin_url('admin-ajax.php');
$ajax_nonce = wp_create_nonce('mj-registration-manager');

$prefill_event_id = 0;
if (isset($_GET['event'])) {
    $prefill_event_id = absint(wp_unslash($_GET['event']));
} elseif (isset($_GET['event_id'])) {
    $prefill_event_id = absint(wp_unslash($_GET['event_id']));
}
$url_event_id = $prefill_event_id > 0 ? $prefill_event_id : null;
$url_tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : null;
$url_member_id = isset($_GET['member']) ? absint(wp_unslash($_GET['member'])) : null;
$url_main_tab = isset($_GET['main-tab']) ? sanitize_key(wp_unslash($_GET['main-tab'])) : null;

$config_json = wp_json_encode(array(
    'widgetId' => $widget_id,
    'ajaxUrl' => $ajax_url,
    'nonce' => $ajax_nonce,
    'memberId' => isset($member->id) ? (int) $member->id : 0,
    'memberRole' => $member_role,
    'isCoordinateur' => $is_coordinateur,
    'showAllEvents' => $show_all_events || $is_coordinateur,
    'showPast' => $show_past,
    'perPage' => $per_page,
    'defaultFilter' => $default_filter,
    'allowManualPayment' => $allow_manual_payment,
    'allowDeleteRegistration' => $allow_delete_registration,
    'allowCreateMember' => $allow_create_member,
    'canManageChildren' => $can_manage_children,
    'allowCreateChild' => $allow_create_child,
    'allowAttachChild' => $allow_attach_child,
    'canManageAccounts' => $can_manage_accounts,
    'canManageNextcloud' => $can_manage_nextcloud,
    'hasNextcloudIntegration' => $nextcloud_available,
    'nextcloudGroups' => $can_manage_nextcloud ? Config::nextcloudGroups() : [],
    'accountLinkNonce' => $can_manage_accounts ? wp_create_nonce('mj_link_member_user') : '',
    'accountRoles' => $account_roles,
    'canChangeMemberAvatar' => current_user_can(Config::capability()) || $is_coordinateur,
    'canCreateEvent' => current_user_can(Config::capability()),
    'canDeleteEvent' => current_user_can(Config::capability()) || $is_coordinateur,
    'canManageLocations' => current_user_can(Config::capability()) || $is_coordinateur,
    'canDeleteMember' => current_user_can(Config::capability()) || $is_coordinateur,
    'adminEditUrl' => $admin_edit_url,
    'adminAddEventUrl' => $admin_add_url,
    'adminMemberUrl' => $admin_member_url,
    'contactMessageListUrl' => admin_url('admin.php?page=mj_contact_messages'),
    'contactMessageViewUrl' => admin_url('admin.php?page=mj_contact_messages&action=view&message='),
    'membershipPrice' => $membership_price,
    'membershipPriceManual' => $membership_price_manual,
    'eventTypes' => $event_types,
    'eventStatuses' => $event_statuses,
    'registrationStatuses' => $registration_statuses,
    'paymentStatuses' => $payment_statuses,
    'attendanceStatuses' => $attendance_statuses,
    'roleLabels' => $role_labels,
    'ageRanges' => $age_ranges,
    'leaveTypes' => $leave_types,
    'locale' => function_exists('determine_locale') ? determine_locale() : get_locale(),
    'prefillEventId' => $prefill_event_id > 0 ? $prefill_event_id : null,
    'urlEventId' => $url_event_id,
    'urlMemberId' => $url_member_id > 0 ? $url_member_id : null,
    'urlTab' => $url_tab !== '' ? $url_tab : null,
    'urlMainTab' => $url_main_tab !== '' ? $url_main_tab : null,
    'regDocHeader' => wpautop(get_option('mj_regdoc_header', '')),
    'regDocFooter' => wpautop(get_option('mj_regdoc_footer', '')),
    'socialPublish' => array(
        'n8nEnabled' => $social_n8n_enabled,
        'n8nConfigured' => $social_n8n_enabled && $social_n8n_webhook_url !== '',
    ),
    'aiEnabled' => (new MjOpenAIClient())->isEnabled(),
    'aiSiteName' => get_bloginfo('name'),
    'aiDescriptionPrompt' => (string) get_option('mj_member_ai_description_prompt', get_option('mj_ai_description_prompt', '')),
    'aiSocialDescriptionPrompt' => (string) get_option('mj_member_ai_social_description_prompt', get_option('mj_ai_social_description_prompt', '')),
    'aiRegDocPrompt' => (string) get_option('mj_member_ai_regdoc_prompt', get_option('mj_ai_regdoc_prompt', '')),
    'strings' => array(
        // Général
        'loading' => __('Chargement...', 'mj-member'),
        'error' => __('Une erreur est survenue.', 'mj-member'),
        'success' => __('Opération réussie.', 'mj-member'),
        'cancel' => __('Annuler', 'mj-member'),
        'close' => __('Fermer', 'mj-member'),
        'save' => __('Enregistrer', 'mj-member'),
        'confirm' => __('Confirmer', 'mj-member'),
        'search' => __('Rechercher...', 'mj-member'),
        'noResults' => __('Aucun résultat', 'mj-member'),
        
        // Filtres événements
        'filterAll' => __('Tous', 'mj-member'),
        'filterAssigned' => __('Mes événements', 'mj-member'),
        'filterShowAllEvents' => __('Afficher tout', 'mj-member'),
        'filterUpcoming' => __('À venir', 'mj-member'),
        'filterPast' => __('Passés', 'mj-member'),
        'filterDraft' => __('Brouillons', 'mj-member'),
        'filterInternal' => __('Internes', 'mj-member'),

        // Navigation mobile
        'backToEvents' => __('Retour aux événements', 'mj-member'),
        'backToMembers' => __('Retour à la liste', 'mj-member'),

        // Filtres membres
        'filterJeune' => __('Jeunes', 'mj-member'),
        'filterAnimateur' => __('Animateurs', 'mj-member'),
        'filterParent' => __('Parents', 'mj-member'),
        'filterMembershipDue' => __('Cotisation à régulariser', 'mj-member'),

        // Tri membres
        'sortBy' => __('Trier par', 'mj-member'),
        'sortByName' => __('Nom (A-Z)', 'mj-member'),
        'sortByRegistration' => __('Date d\'inscription', 'mj-member'),
        'sortByMembership' => __('Date de cotisation', 'mj-member'),
        
        // Événements
        'noEvents' => __('Aucun événement trouvé.', 'mj-member'),
        'eventDetails' => __('Détails de l\'événement', 'mj-member'),
        'editEvent' => __('Modifier l\'événement', 'mj-member'),
        'deleteEvent' => __('Supprimer l\'événement', 'mj-member'),
        'deleteEventConfirm' => __('Voulez-vous vraiment supprimer cet événement ? Cette action est irréversible.', 'mj-member'),
        'deleteEventLoading' => __('Suppression...', 'mj-member'),
        'eventDeleted' => __('Événement supprimé.', 'mj-member'),
        'addEvent' => __('Ajouter un événement', 'mj-member'),
        'addMember' => __('Ajouter un membre', 'mj-member'),
        'eventDate' => __('Date', 'mj-member'),
        'eventLocation' => __('Lieu', 'mj-member'),
        'eventCapacity' => __('Capacité', 'mj-member'),
        'eventPrice' => __('Prix', 'mj-member'),
        'eventRegistrations' => __('Inscriptions', 'mj-member'),
        'eventFree' => __('Gratuit', 'mj-member'),
        'eventFreeParticipation' => __('Participation libre', 'mj-member'),
        'eventUntitled' => __('Événement sans titre', 'mj-member'),
        'openEventPage' => __('Voir la page événement', 'mj-member'),
        'viewLinkedArticle' => __('Voir l\'article lié', 'mj-member'),
        'scheduleModeFixedShort' => __('Date unique', 'mj-member'),
        'scheduleModeRangeShort' => __('Période continue', 'mj-member'),
        'scheduleModeRecurringShort' => __('Récurrence', 'mj-member'),
        'scheduleModeSeriesShort' => __('Série personnalisée', 'mj-member'),
        
        // Inscriptions
        'registrationList' => __('Liste des inscrits', 'mj-member'),
        'addParticipant' => __('Ajouter un participant', 'mj-member'),
        'addSelectedParticipants' => __('Ajouter les membres sélectionnés', 'mj-member'),
        'noRegistrations' => __('Aucun inscrit pour le moment.', 'mj-member'),
        'validateRegistration' => __('Valider l\'inscription', 'mj-member'),
        'cancelRegistration' => __('Annuler l\'inscription', 'mj-member'),
        'deleteRegistration' => __('Supprimer l\'inscription', 'mj-member'),
        'confirmDeleteRegistration' => __('Voulez-vous vraiment supprimer cette inscription ?', 'mj-member'),
        'editOccurrences' => __('Modifier les séances', 'mj-member'),
        'viewEvent' => __('Voir l\'événement', 'mj-member'),
        'allOccurrencesOption' => __('Toutes les séances', 'mj-member'),
        'customOccurrencesOption' => __('Séances spécifiques', 'mj-member'),
        'noOccurrencesSelected' => __('Aucune séance sélectionnée', 'mj-member'),
        'registrationStatus' => __('Statut inscription', 'mj-member'),
        'changeOccurrences' => __('Modifier les séances', 'mj-member'),
        'attendanceList' => __('Liste de présence', 'mj-member'),
        'attendanceAllMembersToggle' => __('Afficher tous les membres dans la liste de présence', 'mj-member'),
        'attendanceAllMembersHint' => __('Permet de pointer les membres autorisés même sans inscription préalable.', 'mj-member'),
        'attendanceAllMembers' => __('Liste de présence : tous les membres', 'mj-member'),
        'attendanceRegisteredOnly' => __('Liste de présence : inscrits uniquement', 'mj-member'),
        
        // Paiements
        'paymentStatus' => __('Statut paiement', 'mj-member'),
        'validatePayment' => __('Valider le paiement', 'mj-member'),
        'paymentQRCode' => __('QR Code de paiement', 'mj-member'),
        'showQRCode' => __('Afficher QR Code', 'mj-member'),
        'contactWhatsapp' => __('Contacter sur WhatsApp', 'mj-member'),
        'memberProfile' => __('Profil', 'mj-member'),
        'memberAccount' => __('Compte WordPress', 'mj-member'),
        'memberAccountLinked' => __('Modifier le compte WordPress', 'mj-member'),
        'memberAccountUnlinked' => __('Lier un compte WordPress', 'mj-member'),
        'memberAccountStatusLinked' => __('Un compte WordPress est lié à ce membre.', 'mj-member'),
        'memberAccountStatusUnlinked' => __('Aucun compte WordPress n\'est encore lié.', 'mj-member'),
        'memberAccountModalTitle' => __('Gestion du compte WordPress', 'mj-member'),
        'memberAccountModalDescription' => __('Créez, liez ou mettez à jour le compte WordPress associé à ce membre.', 'mj-member'),
        'memberAccountRoleLabel' => __('Rôle WordPress attribué', 'mj-member'),
        'memberAccountRolePlaceholder' => __('Sélectionnez un rôle…', 'mj-member'),
        'memberAccountLoginLabel' => __('Identifiant WordPress', 'mj-member'),
        'memberAccountLoginPlaceholder' => __('ex : prenom.nom', 'mj-member'),
        'memberAccountLoginHelp' => __('Laissez vide pour proposer un identifiant automatiquement.', 'mj-member'),
        'memberAccountPasswordLabel' => __('Mot de passe', 'mj-member'),
        'memberAccountPasswordHelp' => __('Laissez vide pour générer un mot de passe sécurisé automatiquement.', 'mj-member'),
        'memberAccountGeneratePassword' => __('Générer un mot de passe sécurisé', 'mj-member'),
        'memberAccountCopyPassword' => __('Copier le mot de passe', 'mj-member'),
        'memberAccountPasswordCopied' => __('Mot de passe copié dans le presse-papiers.', 'mj-member'),
        'memberAccountSubmitCreate' => __('Créer et lier le compte', 'mj-member'),
        'memberAccountSubmitUpdate' => __('Mettre à jour le compte', 'mj-member'),
        'memberAccountSuccessCreate' => __('Compte WordPress créé et lié avec succès.', 'mj-member'),
        'memberAccountSuccessUpdate' => __('Compte WordPress mis à jour avec succès.', 'mj-member'),
        'memberAccountResetEmail' => __('Envoyer un email de réinitialisation', 'mj-member'),
        'memberAccountResetEmailSuccess' => __('Email de réinitialisation envoyé.', 'mj-member'),
        'memberAccountNoRoles' => __('Aucun rôle WordPress n\'est disponible pour votre compte.', 'mj-member'),
        'memberAccountClaimLinkLabel' => __('Lien de création de compte', 'mj-member'),
        'memberAccountClaimLinkHelp' => __('Partagez ce lien avec le membre pour qu\'il crée son accès à partir de ses informations existantes.', 'mj-member'),
        'memberAccountCopyLink' => __('Copier le lien', 'mj-member'),
        'memberAccountLinkCopied' => __('Lien copié dans le presse-papiers.', 'mj-member'),
        'memberNextcloudCreate' => __('Créer un login Nextcloud', 'mj-member'),
        'memberNextcloudExists' => __('Login Nextcloud déjà créé', 'mj-member'),
        'memberNextcloudStatusMissing' => __('Aucun login Nextcloud n\'est associé à ce membre.', 'mj-member'),
        'memberNextcloudStatusReady' => __('Un login Nextcloud est associé à ce membre.', 'mj-member'),
        'memberNextcloudCreating' => __('Création du login Nextcloud...', 'mj-member'),
        'memberNextcloudSuccess' => __('Login Nextcloud créé avec succès.', 'mj-member'),
        'memberNextcloudCopyPassword' => __('Mot de passe Nextcloud: %s', 'mj-member'),
        'memberAvatarChange' => __('Changer la photo de profil', 'mj-member'),
        'memberAvatarRemove' => __('Retirer la photo', 'mj-member'),
        'memberAvatarRemoveConfirm' => __('Retirer la photo actuelle de ce membre ?', 'mj-member'),
        'memberAvatarUpdated' => __('Photo de profil mise à jour.', 'mj-member'),
        'memberAvatarRemoved' => __('Photo de profil retirée.', 'mj-member'),
        'memberAvatarUpdateError' => __('Impossible de mettre à jour la photo de profil.', 'mj-member'),
        'memberAvatarLibraryTitle' => __('Sélectionner une image pour le membre', 'mj-member'),
        'memberAvatarLibraryButton' => __('Utiliser cette image', 'mj-member'),
        'memberAvatarMediaUnavailable' => __('La médiathèque WordPress est indisponible sur cette page.', 'mj-member'),
        'memberAvatarCapture' => __('Prendre une photo', 'mj-member'),
        'memberAvatarCaptureTitle' => __('Prendre une photo', 'mj-member'),
        'memberAvatarCaptureInstructions' => __('Positionnez le membre dans le cadre puis cliquez sur "Capturer".', 'mj-member'),
        'memberAvatarCaptureGrant' => __('Autorisez l\'accès à la caméra si demandé.', 'mj-member'),
        'memberAvatarCaptureTake' => __('Capturer', 'mj-member'),
        'memberAvatarCaptureRetake' => __('Reprendre', 'mj-member'),
        'memberAvatarCaptureConfirm' => __('Utiliser cette photo', 'mj-member'),
        'memberAvatarCaptureCancel' => __('Annuler', 'mj-member'),
        'memberAvatarCaptureSaving' => __('Enregistrement...', 'mj-member'),
        'memberAvatarCaptureUnsupported' => __('La capture photo n\'est pas supportée sur ce navigateur.', 'mj-member'),
        'memberAvatarCaptureError' => __('Impossible d\'accéder à la caméra.', 'mj-member'),
        'memberAvatarCaptureInvalid' => __('Le fichier sélectionné n\'est pas une image.', 'mj-member'),
        // Gestion des enfants
        'guardianChildSectionTitle' => __('Enfants', 'mj-member'),
        'guardianChildEmpty' => __('Aucun enfant rattaché pour le moment.', 'mj-member'),
        'guardianChildAddNew' => __('Ajouter un enfant', 'mj-member'),
        'guardianChildAttachExisting' => __('Rattacher un enfant existant', 'mj-member'),
        'guardianChildSearchPlaceholder' => __('Rechercher un jeune...', 'mj-member'),
        'guardianChildSearchHelp' => __('Tapez au moins 2 caractères pour rechercher', 'mj-member'),
        'guardianChildSearchAction' => __('Rechercher', 'mj-member'),
        'guardianChildSearchNoResults' => __('Aucun jeune trouvé.', 'mj-member'),
        'guardianChildSearchIntro' => __('Recherchez un membre à rattacher comme enfant.', 'mj-member'),
        'guardianChildAttachAction' => __('Rattacher', 'mj-member'),
        'guardianChildAlreadyLinked' => __('Déjà rattaché à un autre tuteur', 'mj-member'),
        'guardianChildAlreadyAssigned' => __('Déjà rattaché à ce tuteur', 'mj-member'),
        'guardianChildRoleRestriction' => __('Seuls les membres avec le rôle « Jeune » peuvent être rattachés.', 'mj-member'),
        'guardianChildAttached' => __('Enfant rattaché avec succès.', 'mj-member'),
        'guardianChildCreated' => __('Enfant ajouté et rattaché avec succès.', 'mj-member'),
        'guardianChildCreationTitle' => __('Ajouter un enfant', 'mj-member'),
        'guardianChildCreationSubtitle' => __('L\'enfant sera rattaché à %s.', 'mj-member'),
        'guardianChildRoleLocked' => __('Le rôle est verrouillé sur « Jeune » pour les enfants.', 'mj-member'),
        'guardianChildCreationError' => __('Impossible de créer l\'enfant.', 'mj-member'),
        'memberAvatarUploading' => __('Mise à jour de la photo...', 'mj-member'),
        'deleteMember' => __('Supprimer le membre', 'mj-member'),
        'deleteMemberConfirm' => __('Voulez-vous vraiment supprimer ce membre ? Cette action est irréversible.', 'mj-member'),
        'deleteMemberProcessing' => __('Suppression...', 'mj-member'),
        'memberDeleted' => __('Membre supprimé.', 'mj-member'),
        'memberBioShort' => __('Bio courte', 'mj-member'),
        'memberBioLong' => __('Bio détaillée', 'mj-member'),
        'memberPhotos' => __('Photos partagées', 'mj-member'),
        'memberNoPhotos' => __('Aucune photo validée pour ce membre.', 'mj-member'),
        'memberIdeas' => __('Idées proposées', 'mj-member'),
        'memberNoIdeas' => __('Aucune idée proposée pour le moment.', 'mj-member'),
        'memberMessages' => __('Messages reçus', 'mj-member'),
        'memberNoMessages' => __('Aucun échange trouvé pour ce membre.', 'mj-member'),
        'viewMessage' => __('Ouvrir le message', 'mj-member'),
        'viewAllMessages' => __('Voir tous les messages', 'mj-member'),
        'messageHistory' => __('Historique', 'mj-member'),
        'chipNewsletter' => __('Newsletter', 'mj-member'),
        'chipSMS' => __('SMS', 'mj-member'),
        'chipWhatsapp' => __('WhatsApp', 'mj-member'),
        'chipPhotoConsent' => __('Consentement photo', 'mj-member'),
        
        // Quotas de congés
        'tabLeaveQuotas' => __('Employé', 'mj-member'),
        'leaveQuotasTitle' => __('Quotas de congés', 'mj-member'),
        'leaveQuotasYear' => __('Année', 'mj-member'),
        'leaveQuotasType' => __('Type de congé', 'mj-member'),
        'leaveQuotasQuota' => __('Jours', 'mj-member'),
        'leaveQuotasCopyPrevious' => __('Copier depuis l\'année précédente', 'mj-member'),
        'leaveQuotasEmpty' => __('Aucun type de congé configuré.', 'mj-member'),
        'leaveQuotasSaved' => __('Quotas mis à jour avec succès.', 'mj-member'),
        
        // Horaires contractuels
        'workSchedulesTitle' => __('Horaires contractuels', 'mj-member'),
        'workScheduleAdd' => __('Ajouter une période', 'mj-member'),
        'workScheduleEdit' => __('Modifier la période', 'mj-member'),
        'workScheduleDelete' => __('Supprimer', 'mj-member'),
        'workScheduleStartDate' => __('Date de début', 'mj-member'),
        'workScheduleEndDate' => __('Date de fin', 'mj-member'),
        'workScheduleEndDateHint' => __('Laisser vide si en cours', 'mj-member'),
        'workScheduleOngoing' => __('En cours', 'mj-member'),
        'workScheduleEmpty' => __('Aucun horaire défini.', 'mj-member'),
        'workScheduleSaved' => __('Horaire enregistré avec succès.', 'mj-member'),
        'workScheduleDeleted' => __('Horaire supprimé.', 'mj-member'),
        'workScheduleOverlap' => __('Cette période chevauche un horaire existant.', 'mj-member'),
        'workScheduleConfirmDelete' => __('Êtes-vous sûr de vouloir supprimer cette période ?', 'mj-member'),
        'workScheduleDay' => __('Jour', 'mj-member'),
        'workScheduleStart' => __('Début', 'mj-member'),
        'workScheduleEnd' => __('Fin', 'mj-member'),
        'workScheduleBreak' => __('Pause (min)', 'mj-member'),
        'workScheduleAddSlot' => __('Ajouter un créneau', 'mj-member'),
        'workScheduleWeeklyTotal' => __('Total hebdomadaire', 'mj-member'),
        'workScheduleDays' => [
            'monday' => __('Lundi', 'mj-member'),
            'tuesday' => __('Mardi', 'mj-member'),
            'wednesday' => __('Mercredi', 'mj-member'),
            'thursday' => __('Jeudi', 'mj-member'),
            'friday' => __('Vendredi', 'mj-member'),
            'saturday' => __('Samedi', 'mj-member'),
            'sunday' => __('Dimanche', 'mj-member'),
        ],
        
        // Employee documents
        'employeeDocsTitle' => __('Documents employé', 'mj-member'),

        // Job profile
        'jobProfileTitle' => __('Profil de fonction', 'mj-member'),
        'jobProfileJobTitle' => __('Titre de fonction', 'mj-member'),
        'jobProfileWorkRegime' => __('Régime de travail', 'mj-member'),
        'jobProfileFundingSource' => __('Origine du financement', 'mj-member'),
        'jobProfileDescription' => __('Description du poste', 'mj-member'),
        'jobProfileSignatureMessage' => __('Signature Message', 'mj-member'),
        'jobProfileSignaturePreview' => __('Aperçu de la signature', 'mj-member'),
        'jobProfileSignaturePreviewCopy' => __('Copier l’aperçu', 'mj-member'),
        'jobProfileSignaturePreviewCopied' => __('Aperçu copié.', 'mj-member'),
        'jobProfileSignaturePreviewCopyError' => __('Impossible de copier l’aperçu.', 'mj-member'),
        'jobProfileSave' => __('Enregistrer', 'mj-member'),
        'jobProfileSaved' => __('Profil de fonction enregistré.', 'mj-member'),
        'jobProfileSaving' => __('Enregistrement…', 'mj-member'),
        'jobProfileTitleSuggestions' => [
            __('Animateur', 'mj-member'),
            __('Coordinateur', 'mj-member'),
            __('Employé', 'mj-member'),
        ],
        'jobProfileRegimes' => [
            'mi-temps' => __('Mi-temps (19h)', 'mj-member'),
            'temps-plein' => __('Temps plein (38h)', 'mj-member'),
            'quatre-cinquieme' => __('Quatre cinquième temps (30h30)', 'mj-member'),
        ],
        'employeeDocsEmpty' => __('Aucun document pour ce membre.', 'mj-member'),
        'employeeDocsUpload' => __('Téléverser un document', 'mj-member'),
        'employeeDocsUploading' => __('Téléversement…', 'mj-member'),
        'employeeDocsDelete' => __('Supprimer', 'mj-member'),
        'employeeDocsConfirmDelete' => __('Êtes-vous sûr de vouloir supprimer ce document ?', 'mj-member'),
        'employeeDocsDeleted' => __('Document supprimé.', 'mj-member'),
        'employeeDocsSaved' => __('Document enregistré.', 'mj-member'),
        'employeeDocsDownload' => __('Télécharger', 'mj-member'),
        'employeeDocsPreview' => __('Aperçu', 'mj-member'),
        'employeeDocsPreviewTitle' => __('Aperçu du document', 'mj-member'),
        'employeeDocsPreviewUnavailable' => __('Ce format ne peut pas être prévisualisé.', 'mj-member'),
        'employeeDocsLabel' => __('Libellé', 'mj-member'),
        'employeeDocsType' => __('Type', 'mj-member'),
        'employeeDocsDate' => __('Date du document', 'mj-member'),
        'employeeDocsFile' => __('Fichier', 'mj-member'),
        'employeeDocsPayslipMonth' => __('Mois', 'mj-member'),
        'employeeDocsPayslipYear' => __('Année', 'mj-member'),
        'employeeDocsTypePayslip' => __('Fiche de paie', 'mj-member'),
        'employeeDocsTypeContract' => __('Emploi', 'mj-member'),
        'employeeDocsTypeMisc' => __('Divers', 'mj-member'),
        'employeeDocsDropHint' => __('Glissez un fichier ou cliquez pour parcourir', 'mj-member'),
        'employeeDocsMaxSize' => __('PDF, JPG, PNG ou GIF – 10 Mo max.', 'mj-member'),
        'employeeDocsMonths' => [
            1 => __('Janvier', 'mj-member'),
            2 => __('Février', 'mj-member'),
            3 => __('Mars', 'mj-member'),
            4 => __('Avril', 'mj-member'),
            5 => __('Mai', 'mj-member'),
            6 => __('Juin', 'mj-member'),
            7 => __('Juillet', 'mj-member'),
            8 => __('Août', 'mj-member'),
            9 => __('Septembre', 'mj-member'),
            10 => __('Octobre', 'mj-member'),
            11 => __('Novembre', 'mj-member'),
            12 => __('Décembre', 'mj-member'),
        ],

        'addLocation' => __('Ajouter un lieu', 'mj-member'),
        'editLocation' => __('Modifier le lieu', 'mj-member'),
        'manageLocationHint' => __('Ajoutez ou éditez un lieu sans quitter ce formulaire.', 'mj-member'),
        'locationPermissionError' => __('Vous ne pouvez pas gérer les lieux.', 'mj-member'),
        'locationLoadError' => __('Impossible de charger ce lieu.', 'mj-member'),
        'locationSaveError' => __('Impossible d\'enregistrer ce lieu.', 'mj-member'),
        'locationSaved' => __('Lieu enregistré.', 'mj-member'),
        'locationCreateTitle' => __('Nouveau lieu', 'mj-member'),
        'locationEditTitle' => __('Modifier le lieu', 'mj-member'),
        'locationNameLabel' => __('Nom du lieu', 'mj-member'),
        'locationAddressLabel' => __('Adresse', 'mj-member'),
        'locationPostalCodeLabel' => __('Code postal', 'mj-member'),
        'locationCityLabel' => __('Ville', 'mj-member'),
        'locationCountryLabel' => __('Pays', 'mj-member'),
        'locationCoverLabel' => __('Visuel du lieu', 'mj-member'),
        'locationCoverEmpty' => __('Aucun visuel défini pour ce lieu.', 'mj-member'),
        'locationCoverSelectButton' => __('Choisir un visuel', 'mj-member'),
        'locationCoverRemoveButton' => __('Retirer le visuel', 'mj-member'),
        'locationCoverSelectModalTitle' => __('Choisir un visuel pour ce lieu', 'mj-member'),
        'locationMapQueryLabel' => __('Recherche Google Maps', 'mj-member'),
        'locationLatitudeLabel' => __('Latitude', 'mj-member'),
        'locationLongitudeLabel' => __('Longitude', 'mj-member'),
        'locationNotesLabel' => __('Notes internes', 'mj-member'),
        'locationPreviewLabel' => __('Aperçu de la carte', 'mj-member'),
        'locationOpenExternal' => __('Ouvrir dans Google Maps', 'mj-member'),
        'locationSaveButton' => __('Enregistrer le lieu', 'mj-member'),
        'locationSaving' => __('Enregistrement du lieu...', 'mj-member'),
        'locationModalLoading' => __('Chargement du lieu...', 'mj-member'),
        'locationModalEmpty' => __('Impossible d\'afficher les détails de ce lieu.', 'mj-member'),
        
        // Présence
        'attendanceSheet' => __('Feuille de présence', 'mj-member'),
        'markPresent' => __('Marquer présent', 'mj-member'),
        'markAbsent' => __('Marquer absent', 'mj-member'),
        'present' => __('Présent', 'mj-member'),
        'absent' => __('Absent', 'mj-member'),
        'pending' => __('À confirmer', 'mj-member'),
        'selectOccurrence' => __('Sélectionner une séance', 'mj-member'),
        'noOccurrences' => __('Aucune séance disponible', 'mj-member'),
        'allParticipants' => __('Tous les participants', 'mj-member'),
        
        // Modal ajout membre
        'searchMember' => __('Rechercher un membre...', 'mj-member'),
        'filterByAge' => __('Filtrer par âge', 'mj-member'),
        'filterBySubscription' => __('Filtrer par cotisation', 'mj-member'),
        'creatingEvent' => __('Création en cours...', 'mj-member'),
        'createEventModalTitle' => __('Créer un événement', 'mj-member'),
        'createEventTitleLabel' => __('Titre du brouillon', 'mj-member'),
        'createEventTitlePlaceholder' => __('Ex: Atelier découverte', 'mj-member'),
        'createEventSubmit' => __('Créer le brouillon', 'mj-member'),
        'createEventSuccess' => __('Événement brouillon créé. Complétez les informations avant publication.', 'mj-member'),
        'createEventError' => __('Impossible de créer cet événement.', 'mj-member'),
        'createEventTitleRequired' => __('Le titre est requis.', 'mj-member'),
        'subscriptionActive' => __('Cotisation active', 'mj-member'),
        'subscriptionExpired' => __('Cotisation expirée', 'mj-member'),
        'subscriptionNone' => __('Aucune cotisation', 'mj-member'),
        'createNewMember' => __('Créer un nouveau membre', 'mj-member'),
        'createMemberError' => __('Impossible de créer ce membre.', 'mj-member'),
        'createMemberNameRequired' => __('Prénom et nom sont requis.', 'mj-member'),
        'firstName' => __('Prénom', 'mj-member'),
        'lastName' => __('Nom', 'mj-member'),
        'email' => __('Email (optionnel)', 'mj-member'),
        'memberAge' => __('%d ans', 'mj-member'),
        'memberRole' => __('Rôle', 'mj-member'),
        
        // Notes
        'addNote' => __('Ajouter une note', 'mj-member'),
        'editNote' => __('Modifier la note', 'mj-member'),
        'deleteNote' => __('Supprimer la note', 'mj-member'),
        'notePrivate' => __('Note privée (visible uniquement par l\'équipe)', 'mj-member'),
        'notePlaceholder' => __('Saisissez votre note ici...', 'mj-member'),
        'noNotes' => __('Aucune note', 'mj-member'),
        'generalNote' => __('Note générale', 'mj-member'),
        
        // Restrictions
        'ageExceeded' => __('Âge supérieur au maximum (%d ans).', 'mj-member'),
        'ageTooYoung' => __('Âge inférieur au minimum (%d ans).', 'mj-member'),
        'alreadyRegistered' => __('Inscrit sur une autre séance', 'mj-member'),
        'tutorNotAllowed' => __('Rôle tuteur non autorisé pour cet événement.', 'mj-member'),
        'paymentPending' => __('Non payé', 'mj-member'),
        'registrationPending' => __('Inscription à valider', 'mj-member'),
        'occurrenceMissing' => __('Non inscrit à cette séance', 'mj-member'),
        
        // Onglets
        'tabRegistrations' => __('Inscriptions', 'mj-member'),
        'tabAttendance' => __('Présence', 'mj-member'),
        'tabDescription' => __('Description', 'mj-member'),
        'tabDetails' => __('Détails', 'mj-member'),
        'tabPublish' => __('Publier', 'mj-member'),
        'tabEditor' => __('Éditer', 'mj-member'),
        'tabOccurrenceEncoder' => __('Occurence de date', 'mj-member'),

        // Publication réseaux
        'publishTitle' => __('Publier cet événement', 'mj-member'),
        'publishDescriptionLabel' => __('Description à partager', 'mj-member'),
        'publishDescriptionPlaceholder' => __('Ex: Rejoins-nous pour cet événement ! Toutes les infos via le lien ci-dessous.', 'mj-member'),
        'publishDescriptionHelp' => __('Le message est envoyé au workflow n8n avec le lien de l\'événement.', 'mj-member'),
        'publishDescriptionSaveButton' => __('Enregistrer la description', 'mj-member'),
        'publishDescriptionSaving' => __('Enregistrement...', 'mj-member'),
        'publishDescriptionSaved' => __('Description de partage enregistrée.', 'mj-member'),
        'publishDescriptionSaveError' => __('Impossible d\'enregistrer la description de partage.', 'mj-member'),
        'publishDescriptionUnsavedChanges' => __('Modifications non enregistrées', 'mj-member'),
        'aiGeneratePublishDescription' => __('✨ Générer avec l\'IA', 'mj-member'),
        'publishEventLinkLabel' => __('Lien de l\'événement', 'mj-member'),
        'publishNoEventLink' => __('Aucun lien événement disponible pour le moment.', 'mj-member'),
        'publishNotConfigured' => __('Configurez n8n dans l\'onglet module « Publier sur les réseaux ».', 'mj-member'),
        'publishActionTitle' => __('Workflow n8n', 'mj-member'),
        'publishActionHint' => __('Envoie le message et les données de l\'événement au webhook n8n configuré.', 'mj-member'),
        'publishActionButton' => __('Publier via n8n', 'mj-member'),
        'publishActionSuccess' => __('Publication envoyée au workflow n8n.', 'mj-member'),
        'publishCopyMessage' => __('Copier le message', 'mj-member'),
        'publishMessageCopied' => __('Message copié dans le presse-papiers.', 'mj-member'),
        'publishCopyError' => __('Impossible de copier automatiquement. Copiez le texte manuellement.', 'mj-member'),
        'publishError' => __('Erreur lors de la publication.', 'mj-member'),

        // Description
        'descriptionLabel' => __('Description de l\'événement', 'mj-member'),
        'descriptionPlaceholder' => __('Décrivez le contenu de cet événement...', 'mj-member'),
        'descriptionSaveButton' => __('Enregistrer la description', 'mj-member'),
        'descriptionSaving' => __('Enregistrement de la description...', 'mj-member'),
        'descriptionSaved' => __('Description mise à jour.', 'mj-member'),
        'descriptionSaveError' => __('Impossible d\'enregistrer la description.', 'mj-member'),
        'descriptionUnsavedChanges' => __('Modifications non enregistrées', 'mj-member'),
        'descriptionLastSaved' => __('Description mise à jour à %s.', 'mj-member'),
        'descriptionResetButton' => __('Annuler les modifications', 'mj-member'),
        
        // Types d'événements (pour présence)
        'scheduleFixed' => __('Date fixe', 'mj-member'),
        'scheduleWeekly' => __('Récurrence hebdomadaire', 'mj-member'),
        'scheduleMonthly' => __('Récurrence mensuelle', 'mj-member'),
        'scheduleSeries' => __('Série de dates', 'mj-member'),

        // Encodage des occurrences
        'occurrencePanelTitle' => __('Gestionnaire d\'occurrences', 'mj-member'),
        'occurrenceQuarterRange' => __('Vue trimestrielle', 'mj-member'),
        'occurrenceMonthRange' => __('Vue mensuelle', 'mj-member'),
        'occurrenceWeekRange' => __('Semaine du {start} au {end}', 'mj-member'),
        'occurrenceNavPrevious' => __('Mois précédent', 'mj-member'),
        'occurrenceNavNext' => __('Mois suivant', 'mj-member'),
        'occurrenceViewQuarter' => __('4 mois', 'mj-member'),
        'occurrenceViewMonth' => __('Mois', 'mj-member'),
        'occurrenceViewWeek' => __('Semaine', 'mj-member'),
        'occurrenceEditorTitle' => __('Modifier l\'occurrence sélectionnée', 'mj-member'),
        'occurrenceDateLabel' => __('Date', 'mj-member'),
        'occurrenceTypeLabel' => __('Type', 'mj-member'),
        'occurrenceStatusPlanned' => __('Prévu', 'mj-member'),
        'occurrenceStatusConfirmed' => __('Confirmée', 'mj-member'),
        'occurrenceStatusCancelled' => __('Annulé', 'mj-member'),
        'occurrenceReasonLabel' => __('Motif d\'annulation', 'mj-member'),
        'occurrenceReasonPlaceholder' => __('Ex: Problème technique', 'mj-member'),
        'occurrenceAllDayCheckbox' => __('Toute la journée', 'mj-member'),
        'occurrenceAllDayLabel' => __('Toute la journée', 'mj-member'),
        'occurrenceStartLabel' => __('Heure de début', 'mj-member'),
        'occurrenceEndLabel' => __('Heure de fin', 'mj-member'),
        'occurrenceUpdateButton' => __('Modifier cette occurrence', 'mj-member'),
        'occurrenceCreateButton' => __('Créer l\'occurrence', 'mj-member'),
        'occurrenceCancelButton' => __('Annuler', 'mj-member'),
        'occurrenceDeleteButton' => __('Supprimer', 'mj-member'),
        'occurrenceDeleteConfirm' => __('Supprimer cette occurrence ?', 'mj-member'),
        'occurrenceDeleteAllButton' => __('Supprimer toutes les occurrences', 'mj-member'),
        'occurrenceDeleteAllConfirm' => __('Voulez-vous vraiment supprimer toutes les occurrences ?', 'mj-member'),
        'occurrenceSaveSuccess' => __('Occurrences mises à jour.', 'mj-member'),
        'occurrenceSaveError' => __('Impossible d\'enregistrer les occurrences.', 'mj-member'),
        'occurrenceEmptySelection' => __('Sélectionnez une date dans le calendrier pour commencer.', 'mj-member'),
        'occurrenceGeneratorTitle' => __('Générer des occurrences', 'mj-member'),
        'occurrenceGeneratorDescription' => __('Planifiez la récurrence automatique de cet événement.', 'mj-member'),
        'occurrenceGeneratorModeLabel' => __('Mode', 'mj-member'),
        'occurrenceGeneratorModeWeekly' => __('Hebdomadaire', 'mj-member'),
        'occurrenceGeneratorModeMonthly' => __('Mensuel', 'mj-member'),
        'occurrenceGeneratorModeRange' => __('Plage de dates', 'mj-member'),
        'occurrenceGeneratorFrequencyLabel' => __('Fréquence', 'mj-member'),
        'occurrenceGeneratorEveryWeek' => __('Chaque semaine', 'mj-member'),
        'occurrenceGeneratorEveryTwoWeeks' => __('Toutes les deux semaines', 'mj-member'),
        'occurrenceGeneratorStartDate' => __('Date de début', 'mj-member'),
        'occurrencePreviewRangePattern' => __('Du {{startDay}} à {{endDay}} de {{startTime}} à {{endTime}}', 'mj-member'),
        'occurrencePreviewRangeDatesOnly' => __('Du {{startDay}} à {{endDay}}', 'mj-member'),
        'occurrenceGeneratorAddButton' => __('Ajouter les occurrences', 'mj-member'),
        'occurrenceGenerationHistoryTitle' => __('Lot d\'occurrences', 'mj-member'),
        'occurrenceGenerationHistoryEmpty' => __('Aucune génération enregistrée pour cet événement.', 'mj-member'),
        'occurrenceGenerationHistoryUnknownDate' => __('date inconnue', 'mj-member'),
        'occurrenceGenerationHistoryUnknownBatch' => __('Lot sans identifiant', 'mj-member'),
        'occurrenceGenerationHistoryCount' => __('{{count}} occurrence(s)', 'mj-member'),
        'occurrenceGenerationHistoryLot' => __('Lot #{{n}}', 'mj-member'),
        'occurrenceGenerationDeleteBatch' => __('Supprimer ce lot', 'mj-member'),
        'occurrenceGenerationDeleteConfirm' => __('Supprimer les {{count}} occurrence(s) de ce lot ?', 'mj-member'),
        'occurrenceGenerationSchedulePreview' => __('Horaire : ', 'mj-member'),
        'occurrenceGenerationSchedulePreviewTitle' => __('Aperçu de l\'horaire global', 'mj-member'),
        'occurrenceGenerationSchedulePreviewEmpty' => __('Cochez "Ajouter à l\'horaire global" pour afficher les sections de lots.', 'mj-member'),
        'occurrenceGenerationSchedulePreviewFallback' => __('Aucun résumé disponible pour ce lot.', 'mj-member'),
        'occurrenceGenerationSelectedLotsCount' => __('{{count}} lot(s) sélectionné(s)', 'mj-member'),
        'occurrenceGenerationAddToGlobalSchedule' => __('Ajouter à l\'horaire global', 'mj-member'),
        'occurrenceGenerationIncludeDatesInPreview' => __('Ajouter date début/fin dans l\'aperçu horaire', 'mj-member'),
        'occurrenceGenerationRenameBatch' => __('Renommer', 'mj-member'),
        'occurrenceGenerationRenameBatchPrompt' => __('Titre du lot', 'mj-member'),
        'occurrenceGenerationAssignLocation' => __('Lieu du lot', 'mj-member'),
        'occurrenceGenerationAssignLocationPlaceholder' => __('Choisir un lieu...', 'mj-member'),
        'occurrenceGenerationAssignMember' => __('Membre assigné (optionnel)', 'mj-member'),
        'occurrenceGenerationAssignMemberPlaceholder' => __('Aucun membre', 'mj-member'),
        'occurrenceGenerationPreviewLocationPrefix' => __('Lieu : {{value}}', 'mj-member'),
        'occurrenceGenerationPreviewMembersPrefix' => __('Animateur : {{value}}', 'mj-member'),
        'occurrenceGenerationPreviewStartDate' => __('Date de début : {{value}}', 'mj-member'),
        'occurrenceGenerationPreviewEndDate' => __('Date de fin : {{value}}', 'mj-member'),
        'occurrenceWeekTimeColumn' => __('Horaires', 'mj-member'),
        'occurrenceWeekEmptyDay' => __('Aucune occurrence planifiée', 'mj-member'),
        'occurrenceWeekPlaceholder' => __('Aucune occurrence à afficher.', 'mj-member'),
        'occurrenceDayMon' => __('Lun', 'mj-member'),
        'occurrenceDayTue' => __('Mar', 'mj-member'),
        'occurrenceDayWed' => __('Mer', 'mj-member'),
        'occurrenceDayThu' => __('Jeu', 'mj-member'),
        'occurrenceDayFri' => __('Ven', 'mj-member'),
        'occurrenceDaySat' => __('Sam', 'mj-member'),
        'occurrenceDaySun' => __('Dim', 'mj-member'),
        'occurrenceNoEventSelected' => __('Sélectionnez un événement pour gérer ses occurrences.', 'mj-member'),
        // AI Generate Modal
        'aiModalDescriptionTitle' => __('Générer une description', 'mj-member'),
        'aiModalRegDocTitle' => __('Générer un document d\'inscription', 'mj-member'),
        'aiContextLabel' => __('Données injectées dans le prompt', 'mj-member'),
        'aiNotePlaceholder' => __('Instruction complémentaire (optionnelle)', 'mj-member'),
        'aiNoteHint' => __('Ex: Mettez l\'accent sur les activités en plein air...', 'mj-member'),
        'aiGenerate' => __('Générer', 'mj-member'),
        'aiPromptPreviewLabel' => __('Aperçu du prompt envoyé', 'mj-member'),
        'aiSystemPromptLabel' => __('Instructions système', 'mj-member'),
        'aiUserPromptLabel' => __('Requête utilisateur', 'mj-member'),
    ),
) + CreateEventModalRenderer::buildConfig());
?>

<div class="mj-registration-manager mj-registration-manager--booting" 
     id="<?php echo esc_attr($widget_id); ?>"
     data-widget-id="<?php echo esc_attr($widget_id); ?>" 
     data-mj-registration-manager 
     data-config="<?php echo esc_attr($config_json); ?>">

    <div class="mj-registration-manager__boot-loader" data-boot-loader>
        <div class="mj-registration-manager__boot-spinner" aria-hidden="true"></div>
        <p class="mj-registration-manager__boot-text"><?php esc_html_e('Chargement du gestionnaire...', 'mj-member'); ?></p>
    </div>
    

    <!-- Modal pour l'ajout de participants -->
    <div class="mj-registration-manager__modal" data-modal="add-participant" hidden>
        <div class="mj-registration-manager__modal-overlay" data-modal-close></div>
        <div class="mj-registration-manager__modal-container">
            <div class="mj-registration-manager__modal-header">
                <h3 class="mj-registration-manager__modal-title"><?php esc_html_e('Ajouter des participants', 'mj-member'); ?></h3>
                <button type="button" class="mj-registration-manager__modal-close" data-modal-close aria-label="<?php esc_attr_e('Fermer', 'mj-member'); ?>">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            <div class="mj-registration-manager__modal-body" data-modal-body>
                <!-- Contenu rendu par Preact -->
            </div>
            <div class="mj-registration-manager__modal-footer" data-modal-footer>
                <button type="button" class="mj-btn mj-btn--secondary" data-modal-close>
                    <?php esc_html_e('Annuler', 'mj-member'); ?>
                </button>
                <button type="button" class="mj-btn mj-btn--primary" data-action="confirm-add-participants" disabled>
                    <?php esc_html_e('Ajouter les membres sélectionnés', 'mj-member'); ?>
                </button>
            </div>
        </div>
    </div>
    
    <!-- Modal QR Code -->
    <div class="mj-registration-manager__modal" data-modal="qr-code" hidden>
        <div class="mj-registration-manager__modal-overlay" data-modal-close></div>
        <div class="mj-registration-manager__modal-container mj-registration-manager__modal-container--small">
            <div class="mj-registration-manager__modal-header">
                <h3 class="mj-registration-manager__modal-title"><?php esc_html_e('QR Code de paiement', 'mj-member'); ?></h3>
                <button type="button" class="mj-registration-manager__modal-close" data-modal-close aria-label="<?php esc_attr_e('Fermer', 'mj-member'); ?>">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            <div class="mj-registration-manager__modal-body" data-qr-code-body>
                <!-- QR Code rendu dynamiquement -->
            </div>
        </div>
    </div>
    
    <!-- Modal création membre -->
    <div class="mj-registration-manager__modal" data-modal="create-member" hidden>
        <div class="mj-registration-manager__modal-overlay" data-modal-close></div>
        <div class="mj-registration-manager__modal-container mj-registration-manager__modal-container--small">
            <div class="mj-registration-manager__modal-header">
                <h3 class="mj-registration-manager__modal-title"><?php esc_html_e('Créer un nouveau membre', 'mj-member'); ?></h3>
                <button type="button" class="mj-registration-manager__modal-close" data-modal-close aria-label="<?php esc_attr_e('Fermer', 'mj-member'); ?>">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            <div class="mj-registration-manager__modal-body" data-create-member-body>
                <!-- Formulaire rendu par Preact -->
            </div>
        </div>
    </div>
    
    <!-- Modal notes membre -->
    <div class="mj-registration-manager__modal" data-modal="member-notes" hidden>
        <div class="mj-registration-manager__modal-overlay" data-modal-close></div>
        <div class="mj-registration-manager__modal-container">
            <div class="mj-registration-manager__modal-header">
                <h3 class="mj-registration-manager__modal-title"><?php esc_html_e('Notes sur le membre', 'mj-member'); ?></h3>
                <button type="button" class="mj-registration-manager__modal-close" data-modal-close aria-label="<?php esc_attr_e('Fermer', 'mj-member'); ?>">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            <div class="mj-registration-manager__modal-body" data-member-notes-body>
                <!-- Notes rendues par Preact -->
            </div>
        </div>
    </div>
    
    <!-- Toast notifications -->
    <div class="mj-registration-manager__toasts" data-toasts aria-live="polite"></div>

    <!-- Shared Create Event Modal (CCM) -->
    <?php CreateEventModalRenderer::render($widget_id); ?>
</div>
