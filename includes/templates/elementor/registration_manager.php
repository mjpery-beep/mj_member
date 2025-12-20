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
use Mj\Member\Classes\MjRoles;
use Mj\Member\Core\AssetsManager;
use Mj\Member\Core\Config;

$settings = $this->get_settings_for_display();
$widget_id = 'mj-registration-manager-' . $this->get_id();
$is_preview = \Elementor\Plugin::$instance->editor->is_edit_mode();

AssetsManager::requirePackage('registration-manager');

$title = !empty($settings['title']) ? $settings['title'] : __('Gestion des inscriptions', 'mj-member');
$show_all_events = !empty($settings['show_all_events']) && $settings['show_all_events'] === 'yes';
$show_past = !empty($settings['show_past_events']) && $settings['show_past_events'] === 'yes';
$per_page = !empty($settings['events_per_page']) ? (int) $settings['events_per_page'] : 20;
$default_filter = !empty($settings['default_filter']) ? $settings['default_filter'] : 'upcoming';
$allow_manual_payment = !empty($settings['allow_manual_payment']) && $settings['allow_manual_payment'] === 'yes';
$allow_delete_registration = !empty($settings['allow_delete_registration']) && $settings['allow_delete_registration'] === 'yes';
$allow_create_member = !empty($settings['allow_create_member']) && $settings['allow_create_member'] === 'yes';

// Aperçu Elementor
if ($is_preview) {
    ?>
    <div class="mj-registration-manager mj-registration-manager--preview" data-widget-id="<?php echo esc_attr($widget_id); ?>">
        <div class="mj-registration-manager__preview">
            <div class="mj-registration-manager__preview-icon">
                <span class="dashicons dashicons-groups"></span>
            </div>
            <h3><?php echo esc_html($title); ?></h3>
            <p><?php esc_html_e('Le gestionnaire d\'inscriptions est visible uniquement pour les animateurs, bénévoles et coordinateurs en production.', 'mj-member'); ?></p>
            <div class="mj-registration-manager__preview-features">
                <ul>
                    <li><?php esc_html_e('Liste des événements avec filtres', 'mj-member'); ?></li>
                    <li><?php esc_html_e('Gestion des inscrits', 'mj-member'); ?></li>
                    <li><?php esc_html_e('Feuille de présence', 'mj-member'); ?></li>
                    <li><?php esc_html_e('Validation des paiements', 'mj-member'); ?></li>
                </ul>
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
$admin_member_url = admin_url('admin.php?page=mj_members&action=edit&member=');

// Prix de la cotisation
$membership_price = (float) get_option('mj_annual_fee', '2.00');
$membership_price_manual = get_option('mj_annual_fee_manual', '');
if ($membership_price_manual === '' || $membership_price_manual === null) {
    $membership_price_manual = $membership_price;
} else {
    $membership_price_manual = (float) $membership_price_manual;
}

// Nonce et URL AJAX
$ajax_url = admin_url('admin-ajax.php');
$ajax_nonce = wp_create_nonce('mj-registration-manager');

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
    'adminEditUrl' => $admin_edit_url,
    'adminMemberUrl' => $admin_member_url,
    'membershipPrice' => $membership_price,
    'membershipPriceManual' => $membership_price_manual,
    'eventTypes' => $event_types,
    'eventStatuses' => $event_statuses,
    'registrationStatuses' => $registration_statuses,
    'paymentStatuses' => $payment_statuses,
    'attendanceStatuses' => $attendance_statuses,
    'roleLabels' => $role_labels,
    'ageRanges' => $age_ranges,
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
        'filterUpcoming' => __('À venir', 'mj-member'),
        'filterPast' => __('Passés', 'mj-member'),
        'filterDraft' => __('Brouillons', 'mj-member'),
        'filterInternal' => __('Internes', 'mj-member'),
        
        // Événements
        'noEvents' => __('Aucun événement trouvé.', 'mj-member'),
        'eventDetails' => __('Détails de l\'événement', 'mj-member'),
        'editEvent' => __('Modifier l\'événement', 'mj-member'),
        'eventDate' => __('Date', 'mj-member'),
        'eventLocation' => __('Lieu', 'mj-member'),
        'eventCapacity' => __('Capacité', 'mj-member'),
        'eventPrice' => __('Prix', 'mj-member'),
        'eventRegistrations' => __('Inscriptions', 'mj-member'),
        'eventFree' => __('Gratuit', 'mj-member'),
        
        // Inscriptions
        'registrationList' => __('Liste des inscrits', 'mj-member'),
        'addParticipant' => __('Ajouter un participant', 'mj-member'),
        'addSelectedParticipants' => __('Ajouter les membres sélectionnés', 'mj-member'),
        'noRegistrations' => __('Aucun inscrit pour le moment.', 'mj-member'),
        'validateRegistration' => __('Valider l\'inscription', 'mj-member'),
        'cancelRegistration' => __('Annuler l\'inscription', 'mj-member'),
        'deleteRegistration' => __('Supprimer l\'inscription', 'mj-member'),
        'confirmDeleteRegistration' => __('Voulez-vous vraiment supprimer cette inscription ?', 'mj-member'),
        'registrationStatus' => __('Statut inscription', 'mj-member'),
        'changeOccurrences' => __('Modifier les séances', 'mj-member'),
        
        // Paiements
        'paymentStatus' => __('Statut paiement', 'mj-member'),
        'validatePayment' => __('Valider le paiement', 'mj-member'),
        'paymentQRCode' => __('QR Code de paiement', 'mj-member'),
        'showQRCode' => __('Afficher QR Code', 'mj-member'),
        
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
        'subscriptionActive' => __('Cotisation active', 'mj-member'),
        'subscriptionExpired' => __('Cotisation expirée', 'mj-member'),
        'subscriptionNone' => __('Aucune cotisation', 'mj-member'),
        'createNewMember' => __('Créer un nouveau membre', 'mj-member'),
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
        
        // Restrictions
        'ageExceeded' => __('Âge supérieur au maximum (%d ans).', 'mj-member'),
        'ageTooYoung' => __('Âge inférieur au minimum (%d ans).', 'mj-member'),
        'alreadyRegistered' => __('Inscrit sur une autre séance', 'mj-member'),
        'tutorNotAllowed' => __('Rôle tuteur non autorisé pour cet événement.', 'mj-member'),
        
        // Onglets
        'tabRegistrations' => __('Inscriptions', 'mj-member'),
        'tabAttendance' => __('Présence', 'mj-member'),
        'tabDetails' => __('Détails', 'mj-member'),
        
        // Types d'événements (pour présence)
        'scheduleFixed' => __('Date fixe', 'mj-member'),
        'scheduleWeekly' => __('Récurrence hebdomadaire', 'mj-member'),
        'scheduleMonthly' => __('Récurrence mensuelle', 'mj-member'),
        'scheduleSeries' => __('Série de dates', 'mj-member'),
    ),
));
?>

<div class="mj-registration-manager" 
     id="<?php echo esc_attr($widget_id); ?>"
     data-widget-id="<?php echo esc_attr($widget_id); ?>" 
     data-mj-registration-manager 
     data-config="<?php echo esc_attr($config_json); ?>">
    
    <div class="mj-registration-manager__layout">
        <!-- Sidebar avec liste des événements -->
        <aside class="mj-registration-manager__sidebar" data-sidebar>
            <div class="mj-registration-manager__sidebar-header">
                <h2 class="mj-registration-manager__title"><?php echo esc_html($title); ?></h2>
            </div>
            
            <div class="mj-registration-manager__filters">
                <div class="mj-registration-manager__search">
                    <input type="text" 
                           class="mj-registration-manager__search-input" 
                           placeholder="<?php esc_attr_e('Rechercher...', 'mj-member'); ?>" 
                           data-search-input 
                           aria-label="<?php esc_attr_e('Rechercher un événement', 'mj-member'); ?>" />
                    <span class="mj-registration-manager__search-icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"></circle>
                            <path d="M21 21l-4.35-4.35"></path>
                        </svg>
                    </span>
                </div>
                
                <div class="mj-registration-manager__filter-tabs" role="tablist" data-filter-tabs>
                    <button type="button" 
                            class="mj-registration-manager__filter-tab <?php echo $default_filter === 'assigned' ? 'mj-registration-manager__filter-tab--active' : ''; ?>" 
                            data-filter="assigned" 
                            role="tab"
                            aria-selected="<?php echo $default_filter === 'assigned' ? 'true' : 'false'; ?>">
                        <?php esc_html_e('Assignés', 'mj-member'); ?>
                    </button>
                    <button type="button" 
                            class="mj-registration-manager__filter-tab <?php echo $default_filter === 'upcoming' ? 'mj-registration-manager__filter-tab--active' : ''; ?>" 
                            data-filter="upcoming"
                            role="tab"
                            aria-selected="<?php echo $default_filter === 'upcoming' ? 'true' : 'false'; ?>">
                        <?php esc_html_e('À venir', 'mj-member'); ?>
                    </button>
                    <button type="button" 
                            class="mj-registration-manager__filter-tab <?php echo $default_filter === 'past' ? 'mj-registration-manager__filter-tab--active' : ''; ?>" 
                            data-filter="past"
                            role="tab"
                            aria-selected="<?php echo $default_filter === 'past' ? 'true' : 'false'; ?>">
                        <?php esc_html_e('Passés', 'mj-member'); ?>
                    </button>
                    <button type="button" 
                            class="mj-registration-manager__filter-tab <?php echo $default_filter === 'draft' ? 'mj-registration-manager__filter-tab--active' : ''; ?>" 
                            data-filter="draft"
                            role="tab"
                            aria-selected="<?php echo $default_filter === 'draft' ? 'true' : 'false'; ?>">
                        <?php esc_html_e('Brouillons', 'mj-member'); ?>
                    </button>
                    <button type="button" 
                            class="mj-registration-manager__filter-tab <?php echo $default_filter === 'internal' ? 'mj-registration-manager__filter-tab--active' : ''; ?>" 
                            data-filter="internal"
                            role="tab"
                            aria-selected="<?php echo $default_filter === 'internal' ? 'true' : 'false'; ?>">
                        <?php esc_html_e('Internes', 'mj-member'); ?>
                    </button>
                </div>
            </div>
            
            <div class="mj-registration-manager__events-list" data-events-list role="listbox">
                <div class="mj-registration-manager__loading" data-loading>
                    <div class="mj-registration-manager__spinner"></div>
                    <p><?php esc_html_e('Chargement des événements...', 'mj-member'); ?></p>
                </div>
            </div>
        </aside>
        
        <!-- Zone principale -->
        <main class="mj-registration-manager__main" data-main>
            <div class="mj-registration-manager__empty-state" data-empty-state>
                <div class="mj-registration-manager__empty-icon">
                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="16" y1="2" x2="16" y2="6"></line>
                        <line x1="8" y1="2" x2="8" y2="6"></line>
                        <line x1="3" y1="10" x2="21" y2="10"></line>
                    </svg>
                </div>
                <h3><?php esc_html_e('Sélectionnez un événement', 'mj-member'); ?></h3>
                <p><?php esc_html_e('Choisissez un événement dans la liste pour gérer les inscriptions et la présence.', 'mj-member'); ?></p>
            </div>
            
            <!-- Zone de contenu de l'événement (rendue par Preact) -->
            <div class="mj-registration-manager__event-content" data-event-content hidden></div>
        </main>
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
</div>
