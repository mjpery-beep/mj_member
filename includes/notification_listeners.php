<?php
/**
 * Notification Listeners
 *
 * Écoute les événements du plugin et crée des notifications in-app + envoie emails/SMS
 * selon les préférences utilisateur.
 *
 * @package MjMember
 */

use Mj\Member\Classes\Crud\MjMembers;
use Mj\Member\Classes\Crud\MjEvents;
use Mj\Member\Classes\Crud\MjNotifications;
use Mj\Member\Classes\Crud\MjIdeas;
use Mj\Member\Classes\Crud\MjEmployeeDocuments;
use Mj\Member\Classes\MjNotificationManager;
use Mj\Member\Classes\MjRoles;
use Mj\Member\Classes\Value\MemberData;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Types de notifications supportés.
 */
final class MjNotificationTypes
{
    // Événements / Inscriptions
    const EVENT_REGISTRATION_CREATED = 'event_registration_created';
    const EVENT_REGISTRATION_CANCELLED = 'event_registration_cancelled';
    const EVENT_REMINDER = 'event_reminder';
    const EVENT_NEW_PUBLISHED = 'event_new_published';

    // Paiements
    const PAYMENT_COMPLETED = 'payment_completed';
    const PAYMENT_REMINDER = 'payment_reminder';

    // Membres
    const MEMBER_CREATED = 'member_created';
    const MEMBER_PROFILE_UPDATED = 'member_profile_updated';

    // Photos
    const PHOTO_UPLOADED = 'photo_uploaded';
    const PHOTO_APPROVED = 'photo_approved';

    // Idées
    const IDEA_PUBLISHED = 'idea_published';
    const IDEA_VOTED = 'idea_voted';

    // Gamification
    const TROPHY_EARNED = 'trophy_earned';
    const BADGE_EARNED = 'badge_earned';
    const CRITERION_EARNED = 'criterion_earned';
    const LEVEL_UP = 'level_up';
    const ACTION_AWARDED = 'action_awarded';

    // Avatar
    const AVATAR_APPLIED = 'avatar_applied';

    // Présence
    const ATTENDANCE_RECORDED = 'attendance_recorded';

    // Messages
    const MESSAGE_RECEIVED = 'message_received';

    // Tâches
    const TODO_ASSIGNED = 'todo_assigned';
    const TODO_NOTE_ADDED = 'todo_note_added';
    const TODO_MEDIA_ADDED = 'todo_media_added';
    const TODO_COMPLETED = 'todo_completed';

    // Articles
    const POST_PUBLISHED = 'post_published';

    // Témoignages
    const TESTIMONIAL_NEW_PENDING = 'testimonial_new_pending';
    const TESTIMONIAL_COMMENT_REPLY = 'testimonial_comment_reply';
    const TESTIMONIAL_COMMENT = 'testimonial_comment';
    const TESTIMONIAL_APPROVED = 'testimonial_approved';
    const TESTIMONIAL_REJECTED = 'testimonial_rejected';
    const TESTIMONIAL_REACTION = 'testimonial_reaction';

    // Demandes de congés
    const LEAVE_REQUEST_CREATED = 'leave_request_created';
    const LEAVE_REQUEST_APPROVED = 'leave_request_approved';
    const LEAVE_REQUEST_REJECTED = 'leave_request_rejected';

    // Notes de frais
    const EXPENSE_CREATED = 'expense_created';
    const EXPENSE_REIMBURSED = 'expense_reimbursed';
    const EXPENSE_REJECTED = 'expense_rejected';

    // Frais kilométriques
    const MILEAGE_CREATED = 'mileage_created';
    const MILEAGE_APPROVED = 'mileage_approved';
    const MILEAGE_REIMBURSED = 'mileage_reimbursed';

    // Documents employé
    const EMPLOYEE_DOCUMENT_UPLOADED = 'employee_document_uploaded';


    /**
     * Retourne les labels pour chaque type de notification.
     *
     * @return array<string,string>
     */
    public static function get_labels(): array
    {
        return array(
            self::EVENT_REGISTRATION_CREATED => __('Inscription à un événement', 'mj-member'),
            self::EVENT_REGISTRATION_CANCELLED => __('Annulation d\'inscription', 'mj-member'),
            self::EVENT_REMINDER => __('Rappel d\'événement', 'mj-member'),
            self::EVENT_NEW_PUBLISHED => __('Nouvel événement publié', 'mj-member'),
            self::PAYMENT_COMPLETED => __('Paiement confirmé', 'mj-member'),
            self::PAYMENT_REMINDER => __('Rappel de paiement', 'mj-member'),
            self::MEMBER_CREATED => __('Nouveau membre', 'mj-member'),
            self::MEMBER_PROFILE_UPDATED => __('Mise à jour du profil', 'mj-member'),
            self::PHOTO_UPLOADED => __('Photo partagée', 'mj-member'),
            self::PHOTO_APPROVED => __('Photo validée', 'mj-member'),
            self::IDEA_PUBLISHED => __('Nouvelle idée', 'mj-member'),
            self::IDEA_VOTED => __('Vote sur une idée', 'mj-member'),
            self::TROPHY_EARNED => __('Trophée obtenu', 'mj-member'),
            self::BADGE_EARNED => __('Badge débloqué', 'mj-member'),
            self::CRITERION_EARNED => __('Critère validé', 'mj-member'),
            self::LEVEL_UP => __('Niveau supérieur', 'mj-member'),
            self::ACTION_AWARDED => __('Action attribuée', 'mj-member'),
            self::AVATAR_APPLIED => __('Nouvel avatar', 'mj-member'),
            self::ATTENDANCE_RECORDED => __('Présence enregistrée', 'mj-member'),
            self::MESSAGE_RECEIVED => __('Message reçu', 'mj-member'),
            self::TODO_ASSIGNED => __('Tâche assignée', 'mj-member'),
            self::TODO_NOTE_ADDED => __('Note ajoutée', 'mj-member'),
            self::TODO_MEDIA_ADDED => __('Document ajouté', 'mj-member'),
            self::TODO_COMPLETED => __('Tâche terminée', 'mj-member'),
            self::POST_PUBLISHED => __('Nouvel article publié', 'mj-member'),
            self::TESTIMONIAL_NEW_PENDING => __('Témoignage à modérer', 'mj-member'),
            self::TESTIMONIAL_COMMENT_REPLY => __('Réponse à un commentaire de témoignage', 'mj-member'),
            self::TESTIMONIAL_COMMENT => __('Nouveau commentaire de témoignage', 'mj-member'),
            self::TESTIMONIAL_APPROVED => __('Témoignage approuvé', 'mj-member'),
            self::TESTIMONIAL_REJECTED => __('Témoignage rejeté', 'mj-member'),
            self::TESTIMONIAL_REACTION => __('Réaction à un témoignage', 'mj-member'),
            self::LEAVE_REQUEST_CREATED => __('Nouvelle demande de congé', 'mj-member'),
            self::LEAVE_REQUEST_APPROVED => __('Demande de congé approuvée', 'mj-member'),
            self::LEAVE_REQUEST_REJECTED => __('Demande de congé refusée', 'mj-member'),
            self::EXPENSE_CREATED => __('Nouvelle note de frais', 'mj-member'),
            self::EXPENSE_REIMBURSED => __('Note de frais remboursée', 'mj-member'),
            self::EXPENSE_REJECTED => __('Note de frais refusée', 'mj-member'),
            self::MILEAGE_CREATED => __('Nouveau frais kilométrique', 'mj-member'),
            self::MILEAGE_APPROVED => __('Frais kilométrique approuvé', 'mj-member'),
            self::MILEAGE_REIMBURSED => __('Frais kilométrique remboursé', 'mj-member'),
            self::EMPLOYEE_DOCUMENT_UPLOADED => __('Nouveau document employé', 'mj-member'),
        );
    }
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

if (!function_exists('mj_member_notification_should_send_email')) {
    /**
     * Vérifie si l'email doit être envoyé pour ce type de notification.
     *
     * @param int    $member_id
     * @param string $preference_key La clé de préférence (ex: email_event_registration)
     * @return bool
     */
    function mj_member_notification_should_send_email($member_id, $preference_key): bool
    {
        if (!class_exists(MjMembers::class)) {
            return false;
        }

        $preferences = MjMembers::getNotificationPreferences((int) $member_id);
        return !empty($preferences[$preference_key]);
    }
}

if (!function_exists('mj_member_notification_should_send_sms')) {
    /**
     * Vérifie si le SMS doit être envoyé pour ce type de notification.
     *
     * @param int    $member_id
     * @param string $preference_key La clé de préférence (ex: sms_event_registration)
     * @return bool
     */
    function mj_member_notification_should_send_sms($member_id, $preference_key): bool
    {
        if (!class_exists(MjMembers::class)) {
            return false;
        }

        $preferences = MjMembers::getNotificationPreferences((int) $member_id);
        return !empty($preferences[$preference_key]);
    }
}

if (!function_exists('mj_member_notification_get_member_name')) {
    /**
     * Retourne le nom complet du membre.
     *
     * @param object|MemberData|null $member
     * @return string
     */
    function mj_member_notification_get_member_name($member): string
    {
        if (!$member) {
            return '';
        }

        if ($member instanceof MemberData) {
            $first = $member->get('first_name', '');
            $last = $member->get('last_name', '');
        } else {
            $first = isset($member->first_name) ? (string) $member->first_name : '';
            $last = isset($member->last_name) ? (string) $member->last_name : '';
        }

        $name = trim($first . ' ' . $last);
        return $name !== '' ? $name : __('Membre', 'mj-member');
    }
}

if (!function_exists('mj_member_notification_log')) {
    /**
     * Log une notification dans le debug.log.
     *
     * @param string              $type
     * @param array<string,mixed> $context
     * @return void
     */
    function mj_member_notification_log(string $type, array $context): void
    {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        if (class_exists('Mj\Member\Core\Logger')) {
            \Mj\Member\Core\Logger::info('[Notification] ' . $type, $context, 'notifications');
        } else {
            error_log('[MJ Notification] ' . $type . ' - ' . wp_json_encode($context));
        }
    }
}

if (!function_exists('mj_member_notification_get_manager_url')) {
    /**
     * Génère une URL vers le gestionnaire front-end.
     *
     * @param string $type   'member' ou 'event'
     * @param int    $id     ID du membre ou de l'événement
     * @param string $tab    Onglet optionnel (registrations, attendance, photos, etc.)
     * @return string
     */
    function mj_member_notification_get_manager_url(string $type, int $id, string $tab = ''): string
    {
        $base_url = home_url('/mon-compte/gestionnaire/');
        
        $args = array();
        if ($type === 'member') {
            $args['member'] = $id;
        } elseif ($type === 'event') {
            $args['event'] = $id;
        }
        
        if (!empty($tab)) {
            $args['tab'] = $tab;
        }
        
        return add_query_arg($args, $base_url);
    }
}

if (!function_exists('mj_member_notification_get_admin_recipients')) {
    /**
     * Retourne les destinataires admin (animateurs + coordinateurs) pour une notification.
     *
     * @return array<int,array<string,mixed>>
     */
    function mj_member_notification_get_admin_recipients(): array
    {
        $recipients = array();

        // Notifier les animateurs et coordinateurs
        if (class_exists(MjRoles::class)) {
            $recipients[] = array('role' => MjRoles::ANIMATEUR);
            $recipients[] = array('role' => MjRoles::COORDINATEUR);
        }

        return $recipients;
    }
}

// ============================================================================
// LISTENER: Inscription à un événement
// ============================================================================

if (!function_exists('mj_member_notification_on_registration_created')) {
    /**
     * Déclenché lors de la création d'une inscription.
     *
     * @param array|int $result
     * @param int       $event_id
     * @param int       $member_id
     * @param object    $current_member
     * @return void
     */
    function mj_member_notification_on_registration_created($result, $event_id, $member_id, $current_member): void
    {
        if (!function_exists('mj_member_record_notification')) {
            return;
        }

        $member_id = (int) $member_id;
        $event_id = (int) $event_id;

        if ($member_id <= 0 || $event_id <= 0) {
            return;
        }

        // Récupérer les infos de l'événement
        $event = null;
        $event_title = sprintf(__('Événement #%d', 'mj-member'), $event_id);
        $event_url = '';

        if (class_exists(MjEvents::class)) {
            $event = MjEvents::find($event_id);
            if ($event) {
                $event_title = isset($event->title) ? (string) $event->title : $event_title;
                $event_url = apply_filters('mj_member_event_permalink', '', $event);
            }
        }

        // Récupérer le membre inscrit
        $member = null;
        $member_name = '';
        if (class_exists(MjMembers::class)) {
            $member = MjMembers::getById($member_id);
            $member_name = mj_member_notification_get_member_name($member);
        }

        // Créer la notification in-app pour le membre inscrit
        $notification_data = array(
            'type' => MjNotificationTypes::EVENT_REGISTRATION_CREATED,
            'title' => sprintf(__('Inscription confirmée : %s', 'mj-member'), $event_title),
            'excerpt' => sprintf(__('Votre inscription à l\'événement « %s » a été enregistrée.', 'mj-member'), $event_title),
            'url' => $event_url,
            'context' => 'event_registration',
            'source' => 'system',
            'payload' => array(
                'event_id' => $event_id,
                'event_title' => $event_title,
                'member_id' => $member_id,
                'member_name' => $member_name,
            ),
        );

        $recipients = array($member_id);

        $result_notification = mj_member_record_notification($notification_data, $recipients);

        mj_member_notification_log('registration_created', array(
            'event_id' => $event_id,
            'member_id' => $member_id,
            'notification_result' => is_wp_error($result_notification) ? $result_notification->get_error_message() : 'success',
        ));

        // Envoyer email si activé dans les préférences
        if (mj_member_notification_should_send_email($member_id, 'email_event_registration')) {
            do_action('mj_member_send_registration_confirmation_email', $member_id, $event_id, $event);
        }

        // Envoyer SMS si activé dans les préférences
        if (mj_member_notification_should_send_sms($member_id, 'sms_event_registration')) {
            do_action('mj_member_send_registration_confirmation_sms', $member_id, $event_id, $event);
        }

        // Notifier les admins
        $admin_notification = array(
            'type' => MjNotificationTypes::EVENT_REGISTRATION_CREATED,
            'title' => sprintf(__('Nouvelle inscription : %s', 'mj-member'), $member_name),
            'excerpt' => sprintf(__('%s s\'est inscrit à « %s ».', 'mj-member'), $member_name, $event_title),
            'url' => mj_member_notification_get_manager_url('event', $event_id, 'registrations'),
            'context' => 'admin_notification',
            'source' => 'system',
            'payload' => array(
                'event_id' => $event_id,
                'event_title' => $event_title,
                'member_id' => $member_id,
                'member_name' => $member_name,
            ),
        );

        $admin_recipients = mj_member_notification_get_admin_recipients();
        if (!empty($admin_recipients)) {
            mj_member_record_notification($admin_notification, $admin_recipients);
        }
    }

    add_action('mj_member_event_registration_created', 'mj_member_notification_on_registration_created', 10, 4);
}

// ============================================================================
// LISTENER: Annulation d'inscription
// ============================================================================

if (!function_exists('mj_member_notification_on_registration_cancelled')) {
    /**
     * Déclenché lors de l'annulation d'une inscription.
     *
     * @param int    $registration_id
     * @param int    $event_id
     * @param int    $member_id
     * @param object $current_member
     * @return void
     */
    function mj_member_notification_on_registration_cancelled($registration_id, $event_id, $member_id, $current_member): void
    {
        if (!function_exists('mj_member_record_notification')) {
            return;
        }

        $member_id = (int) $member_id;
        $event_id = (int) $event_id;

        if ($member_id <= 0 || $event_id <= 0) {
            return;
        }

        // Récupérer les infos de l'événement
        $event_title = sprintf(__('Événement #%d', 'mj-member'), $event_id);

        if (class_exists(MjEvents::class)) {
            $event = MjEvents::find($event_id);
            if ($event && isset($event->title)) {
                $event_title = (string) $event->title;
            }
        }

        $notification_data = array(
            'type' => MjNotificationTypes::EVENT_REGISTRATION_CANCELLED,
            'title' => sprintf(__('Inscription annulée : %s', 'mj-member'), $event_title),
            'excerpt' => sprintf(__('Votre inscription à l\'événement « %s » a été annulée.', 'mj-member'), $event_title),
            'context' => 'event_registration',
            'source' => 'system',
            'payload' => array(
                'event_id' => $event_id,
                'event_title' => $event_title,
                'member_id' => $member_id,
                'registration_id' => (int) $registration_id,
            ),
        );

        mj_member_record_notification($notification_data, array($member_id));

        mj_member_notification_log('registration_cancelled', array(
            'event_id' => $event_id,
            'member_id' => $member_id,
            'registration_id' => $registration_id,
        ));
    }

    add_action('mj_member_event_registration_cancelled', 'mj_member_notification_on_registration_cancelled', 10, 4);
}

// ============================================================================
// LISTENER: Paiement confirmé (inscription)
// ============================================================================

if (!function_exists('mj_member_notification_on_payment_confirmed')) {
    /**
     * Déclenché lors de la confirmation d'un paiement.
     *
     * @param int    $registration_id
     * @param object $registration
     * @param array  $args
     * @return void
     */
    function mj_member_notification_on_payment_confirmed($registration_id, $registration, $args): void
    {
        if (!function_exists('mj_member_record_notification')) {
            return;
        }

        $member_id = isset($registration->member_id) ? (int) $registration->member_id : 0;
        $event_id = isset($registration->event_id) ? (int) $registration->event_id : 0;

        if ($member_id <= 0) {
            return;
        }

        $event_title = '';
        $amount = isset($args['amount']) ? (float) $args['amount'] : 0;

        if ($event_id > 0 && class_exists(MjEvents::class)) {
            $event = MjEvents::find($event_id);
            if ($event && isset($event->title)) {
                $event_title = (string) $event->title;
            }
        }

        $title = $event_title !== ''
            ? sprintf(__('Paiement confirmé pour %s', 'mj-member'), $event_title)
            : __('Paiement confirmé', 'mj-member');

        $excerpt = $amount > 0
            ? sprintf(__('Votre paiement de %.2f € a été confirmé.', 'mj-member'), $amount)
            : __('Votre paiement a été confirmé. Merci !', 'mj-member');

        $notification_data = array(
            'type' => MjNotificationTypes::PAYMENT_COMPLETED,
            'title' => $title,
            'excerpt' => $excerpt,
            'context' => 'payment',
            'source' => 'stripe',
            'payload' => array(
                'registration_id' => (int) $registration_id,
                'event_id' => $event_id,
                'event_title' => $event_title,
                'amount' => $amount,
            ),
        );

        mj_member_record_notification($notification_data, array($member_id));

        mj_member_notification_log('payment_confirmed', array(
            'member_id' => $member_id,
            'registration_id' => $registration_id,
            'amount' => $amount,
        ));

        // Envoyer email si activé
        if (mj_member_notification_should_send_email($member_id, 'email_payment_receipts')) {
            do_action('mj_member_send_payment_receipt_email', $member_id, $registration_id, $amount);
        }

        // Envoyer SMS si activé
        if (mj_member_notification_should_send_sms($member_id, 'sms_payment_receipts')) {
            do_action('mj_member_send_payment_receipt_sms', $member_id, $registration_id, $amount);
        }
    }

    add_action('mj_member_event_registration_payment_confirmed', 'mj_member_notification_on_payment_confirmed', 10, 3);
}

// ============================================================================
// LISTENER: Nouveau membre créé
// ============================================================================

if (!function_exists('mj_member_notification_on_member_created')) {
    /**
     * Déclenché lors de la création d'un membre.
     *
     * @param int    $member_id
     * @param object $member
     * @param array  $context
     * @return void
     */
    function mj_member_notification_on_member_created($member_id, $member, $context = array()): void
    {
        error_log('🎯 LISTENER DÉCLENCHÉ - member_id: ' . $member_id);
        
        if (!function_exists('mj_member_record_notification')) {
            error_log('⚠️ mj_member_record_notification n\'existe pas');
            return;
        }

        $member_id = (int) $member_id;
        if ($member_id <= 0) {
            return;
        }

        $member_name = mj_member_notification_get_member_name($member);
        $role = isset($member->role) ? sanitize_key((string) $member->role) : '';
        $role_label = class_exists(MjRoles::class) ? MjRoles::getRoleLabel($role) : $role;

        // Notification pour les admins/coordinateurs
        $admin_notification = array(
            'type' => MjNotificationTypes::MEMBER_CREATED,
            'title' => sprintf(__('Nouveau membre : %s', 'mj-member'), $member_name),
            'excerpt' => sprintf(__('%s a rejoint la MJ en tant que %s.', 'mj-member'), $member_name, $role_label),
            'url' => mj_member_notification_get_manager_url('member', $member_id),
            'context' => 'admin_notification',
            'source' => 'system',
            'payload' => array(
                'member_id' => $member_id,
                'member_name' => $member_name,
                'role' => $role,
                'context' => $context,
            ),
        );

        $admin_recipients = mj_member_notification_get_admin_recipients();
        if (!empty($admin_recipients)) {
            mj_member_record_notification($admin_notification, $admin_recipients);
        }

        // Notification de bienvenue pour le membre
        $welcome_notification = array(
            'type' => MjNotificationTypes::MEMBER_CREATED,
            'title' => __('Bienvenue à la MJ Péry !', 'mj-member'),
            'excerpt' => __('Votre profil a été créé avec succès. Découvrez les événements à venir !', 'mj-member'),
            'url' => home_url('/mon-compte/'),
            'context' => 'welcome',
            'source' => 'system',
            'payload' => array(
                'member_id' => $member_id,
            ),
        );

        mj_member_record_notification($welcome_notification, array($member_id));

        mj_member_notification_log('member_created', array(
            'member_id' => $member_id,
            'member_name' => $member_name,
            'role' => $role,
        ));
    }

    add_action('mj_member_quick_member_created', 'mj_member_notification_on_member_created', 10, 3);
}

// ============================================================================
// LISTENER: Photo uploadée
// ============================================================================

if (!function_exists('mj_member_notification_on_photo_uploaded')) {
    /**
     * Déclenché après la création d'une photo d'événement.
     * On écoute l'action hook existante ou on crée la nôtre.
     *
     * @param int    $photo_id
     * @param int    $event_id
     * @param int    $member_id
     * @param string $status
     * @return void
     */
    function mj_member_notification_on_photo_uploaded($photo_id, $event_id, $member_id, $status = 'pending'): void
    {
        if (!function_exists('mj_member_record_notification')) {
            return;
        }

        $member_id = (int) $member_id;
        $event_id = (int) $event_id;

        if ($member_id <= 0 || $event_id <= 0) {
            return;
        }

        $member_name = '';
        if (class_exists(MjMembers::class)) {
            $member = MjMembers::getById($member_id);
            $member_name = mj_member_notification_get_member_name($member);
        }

        $event_title = sprintf(__('Événement #%d', 'mj-member'), $event_id);
        if (class_exists(MjEvents::class)) {
            $event = MjEvents::find($event_id);
            if ($event && isset($event->title)) {
                $event_title = (string) $event->title;
            }
        }

        // Notification pour les admins/coordinateurs
        $admin_notification = array(
            'type' => MjNotificationTypes::PHOTO_UPLOADED,
            'title' => sprintf(__('Nouvelle photo : %s', 'mj-member'), $event_title),
            'excerpt' => sprintf(__('%s a partagé une photo pour « %s ».', 'mj-member'), $member_name, $event_title),
            'url' => mj_member_notification_get_manager_url('event', $event_id, 'photos'),
            'context' => 'photo_moderation',
            'source' => 'system',
            'payload' => array(
                'photo_id' => (int) $photo_id,
                'event_id' => $event_id,
                'event_title' => $event_title,
                'member_id' => $member_id,
                'member_name' => $member_name,
                'status' => $status,
            ),
        );

        $admin_recipients = mj_member_notification_get_admin_recipients();
        if (!empty($admin_recipients)) {
            mj_member_record_notification($admin_notification, $admin_recipients);
        }

        mj_member_notification_log('photo_uploaded', array(
            'photo_id' => $photo_id,
            'event_id' => $event_id,
            'member_id' => $member_id,
            'status' => $status,
        ));
    }

    add_action('mj_member_event_photo_created', 'mj_member_notification_on_photo_uploaded', 10, 4);
}

// ============================================================================
// LISTENER: Photo validée
// ============================================================================

if (!function_exists('mj_member_notification_on_photo_approved')) {
    /**
     * Déclenché après validation d'une photo.
     *
     * @param int    $photo_id
     * @param string $new_status
     * @param string $old_status
     * @param object $photo
     * @return void
     */
    function mj_member_notification_on_photo_approved($photo_id, $new_status, $old_status, $photo): void
    {
        // Ne notifier que si la photo passe en "approved"
        if ($new_status !== \Mj\Member\Classes\Crud\MjEventPhotos::STATUS_APPROVED) {
            return;
        }

        if (!function_exists('mj_member_record_notification')) {
            return;
        }

        $member_id = isset($photo->member_id) ? (int) $photo->member_id : 0;
        $event_id = isset($photo->event_id) ? (int) $photo->event_id : 0;

        if ($member_id <= 0) {
            return;
        }

        // Récupérer le titre de l'événement
        $event_title = sprintf(__('Événement #%d', 'mj-member'), $event_id);
        if (class_exists(MjEvents::class) && $event_id > 0) {
            $event = MjEvents::find($event_id);
            if ($event && isset($event->title)) {
                $event_title = (string) $event->title;
            }
        }

        // URL vers les photos du membre
        $photos_url = home_url('/mon-compte/mes-photos/?section=photos');

        // Notification pour le membre propriétaire de la photo
        $notification_data = array(
            'type' => MjNotificationTypes::PHOTO_APPROVED,
            'title' => __('📸 Ta photo a été validée !', 'mj-member'),
            'excerpt' => sprintf(__('Ta photo pour « %s » est maintenant visible par tous.', 'mj-member'), $event_title),
            'url' => $photos_url,
            'context' => 'photo_moderation',
            'source' => 'system',
            'payload' => array(
                'photo_id' => (int) $photo_id,
                'event_id' => $event_id,
                'event_title' => $event_title,
            ),
        );

        $recipients = array($member_id);
        mj_member_record_notification($notification_data, $recipients);

        mj_member_notification_log('photo_approved', array(
            'photo_id' => $photo_id,
            'event_id' => $event_id,
            'member_id' => $member_id,
        ));
    }

    add_action('mj_member_event_photo_status_changed', 'mj_member_notification_on_photo_approved', 10, 4);
}

// ============================================================================
// LISTENER: Mise à jour du profil membre
// ============================================================================

if (!function_exists('mj_member_notification_on_profile_updated')) {
    /**
     * Déclenché après la mise à jour d'un profil.
     *
     * @param int   $member_id
     * @param array $updated_fields
     * @param array $context
     * @return void
     */
    function mj_member_notification_on_profile_updated($member_id, $updated_fields = array(), $context = array()): void
    {
        if (!function_exists('mj_member_record_notification')) {
            return;
        }

        $member_id = (int) $member_id;
        if ($member_id <= 0) {
            return;
        }

        // Ne notifier que les admins pour les mises à jour de profil
        $member_name = '';
        if (class_exists(MjMembers::class)) {
            $member = MjMembers::getById($member_id);
            $member_name = mj_member_notification_get_member_name($member);
        }

        $fields_label = !empty($updated_fields) ? implode(', ', array_keys($updated_fields)) : __('profil', 'mj-member');

        $admin_notification = array(
            'type' => MjNotificationTypes::MEMBER_PROFILE_UPDATED,
            'title' => sprintf(__('Profil mis à jour : %s', 'mj-member'), $member_name),
            'excerpt' => sprintf(__('%s a mis à jour son profil (%s).', 'mj-member'), $member_name, $fields_label),
            'url' => mj_member_notification_get_manager_url('member', $member_id, 'information'),
            'context' => 'admin_notification',
            'source' => 'system',
            'payload' => array(
                'member_id' => $member_id,
                'member_name' => $member_name,
                'updated_fields' => array_keys($updated_fields),
                'context' => $context,
            ),
        );

        $admin_recipients = mj_member_notification_get_admin_recipients();
        if (!empty($admin_recipients)) {
            mj_member_record_notification($admin_notification, $admin_recipients);
        }

        mj_member_notification_log('profile_updated', array(
            'member_id' => $member_id,
            'updated_fields' => array_keys($updated_fields),
        ));
    }

    add_action('mj_member_profile_updated', 'mj_member_notification_on_profile_updated', 10, 3);
}

// ============================================================================
// LISTENER: Nouvelle idée publiée
// ============================================================================

if (!function_exists('mj_member_notification_on_idea_published')) {
    /**
     * Déclenché après la publication d'une idée.
     *
     * @param int    $idea_id
     * @param int    $member_id
     * @param string $title
     * @param string $content
     * @return void
     */
    function mj_member_notification_on_idea_published($idea_id, $member_id, $title, $content = ''): void
    {
        if (!function_exists('mj_member_record_notification')) {
            return;
        }

        $idea_id = (int) $idea_id;
        $member_id = (int) $member_id;

        if ($idea_id <= 0 || $member_id <= 0) {
            return;
        }

        $member_name = '';
        if (class_exists(MjMembers::class)) {
            $member = MjMembers::getById($member_id);
            $member_name = mj_member_notification_get_member_name($member);
        }

        $title = sanitize_text_field($title);
        $excerpt = mb_strlen($content) > 100 ? mb_substr($content, 0, 100) . '…' : $content;

        // Notification pour les admins/coordinateurs
        $admin_notification = array(
            'type' => MjNotificationTypes::IDEA_PUBLISHED,
            'title' => sprintf(__('Nouvelle idée : %s', 'mj-member'), $title),
            'excerpt' => sprintf(__('%s propose : « %s »', 'mj-member'), $member_name, $excerpt),
            'url' => mj_member_notification_get_manager_url('member', $member_id, 'ideas'),
            'context' => 'idea_box',
            'source' => 'system',
            'payload' => array(
                'idea_id' => $idea_id,
                'member_id' => $member_id,
                'member_name' => $member_name,
                'title' => $title,
            ),
        );

        $admin_recipients = mj_member_notification_get_admin_recipients();
        if (!empty($admin_recipients)) {
            mj_member_record_notification($admin_notification, $admin_recipients);
        }

        mj_member_notification_log('idea_published', array(
            'idea_id' => $idea_id,
            'member_id' => $member_id,
            'title' => $title,
        ));
    }

    add_action('mj_member_idea_published', 'mj_member_notification_on_idea_published', 10, 4);
}

// ============================================================================
// LISTENER: Vote sur une idée
// ============================================================================

if (!function_exists('mj_member_notification_on_idea_voted')) {
    /**
     * Déclenché quand un membre vote pour une idée.
     *
     * @param int   $idea_id   ID de l'idée
     * @param int   $owner_id  ID du propriétaire de l'idée
     * @param int   $voter_id  ID du membre qui a voté
     * @param array $idea      Données de l'idée
     * @return void
     */
    function mj_member_notification_on_idea_voted($idea_id, $owner_id, $voter_id, $idea): void
    {
        if (!function_exists('mj_member_record_notification')) {
            return;
        }

        $idea_id = (int) $idea_id;
        $owner_id = (int) $owner_id;
        $voter_id = (int) $voter_id;

        if ($idea_id <= 0 || $owner_id <= 0 || $voter_id <= 0) {
            return;
        }

        // Récupérer le nom du votant
        $voter_name = '';
        if (class_exists(MjMembers::class)) {
            $voter = MjMembers::getById($voter_id);
            $voter_name = mj_member_notification_get_member_name($voter);
        }

        // Titre de l'idée
        $idea_title = isset($idea['title']) ? sanitize_text_field((string) $idea['title']) : sprintf(__('Idée #%d', 'mj-member'), $idea_id);

        // URL vers la boîte à idées
        $ideas_url = home_url('/mon-compte/boite-idees/');

        // Notification au propriétaire de l'idée
        $notification_data = array(
            'type' => MjNotificationTypes::IDEA_VOTED,
            'title' => __('Quelqu\'un aime ton idée !', 'mj-member'),
            'excerpt' => sprintf(__('%s a voté pour ton idée « %s ».', 'mj-member'), $voter_name, $idea_title),
            'url' => $ideas_url,
            'context' => 'idea_box',
            'source' => 'system',
            'payload' => array(
                'idea_id' => $idea_id,
                'idea_title' => $idea_title,
                'voter_id' => $voter_id,
                'voter_name' => $voter_name,
            ),
        );

        $recipients = array($owner_id);
        mj_member_record_notification($notification_data, $recipients);

        mj_member_notification_log('idea_voted', array(
            'idea_id' => $idea_id,
            'owner_id' => $owner_id,
            'voter_id' => $voter_id,
        ));
    }

    add_action('mj_member_idea_voted', 'mj_member_notification_on_idea_voted', 10, 4);
}

// ============================================================================
// LISTENER: Trophée obtenu
// ============================================================================

if (!function_exists('mj_member_notification_on_trophy_earned')) {
    /**
     * Déclenché lors de l'attribution d'un trophée.
     *
     * @param int  $member_id      ID du membre
     * @param int  $trophy_id      ID du trophée
     * @param int  $assignment_id  ID de l'attribution
     * @param bool $is_reactivation True si c'est une réactivation
     * @return void
     */
    function mj_member_notification_on_trophy_earned($member_id, $trophy_id, $assignment_id, $is_reactivation = false): void
    {
        if (!function_exists('mj_member_record_notification')) {
            return;
        }

        $member_id = (int) $member_id;
        $trophy_id = (int) $trophy_id;

        if ($member_id <= 0 || $trophy_id <= 0) {
            return;
        }

        // Récupérer les données du trophée
        $trophy = null;
        $trophy_name = sprintf(__('Trophée #%d', 'mj-member'), $trophy_id);
        $trophy_description = '';
        $trophy_icon = '🏆';
        $trophy_slug = '';

        if (class_exists(\Mj\Member\Classes\Crud\MjTrophies::class)) {
            $trophy = \Mj\Member\Classes\Crud\MjTrophies::get($trophy_id);
            if ($trophy) {
                $trophy_name = isset($trophy['name']) ? (string) $trophy['name'] : $trophy_name;
                $trophy_description = isset($trophy['description']) ? (string) $trophy['description'] : '';
                $trophy_icon = isset($trophy['icon']) && !empty($trophy['icon']) ? (string) $trophy['icon'] : '🏆';
                $trophy_slug = isset($trophy['slug']) ? (string) $trophy['slug'] : '';
            }
        }

        // Récupérer le membre
        $member = null;
        $member_name = '';
        if (class_exists(MjMembers::class)) {
            $member = MjMembers::getById($member_id);
            $member_name = mj_member_notification_get_member_name($member);
        }

        // Notification au membre
        $notification_data = array(
            'type' => MjNotificationTypes::TROPHY_EARNED,
            'title' => sprintf(__('%s Trophée obtenu : %s', 'mj-member'), $trophy_icon, $trophy_name),
            'excerpt' => !empty($trophy_description)
                ? $trophy_description
                : sprintf(__('Félicitations ! Tu as obtenu le trophée « %s ».', 'mj-member'), $trophy_name),
            'url' => home_url('/mon-compte/badge/'),
            'context' => 'gamification',
            'source' => 'system',
            'payload' => array(
                'member_id' => $member_id,
                'trophy_id' => $trophy_id,
                'trophy_slug' => $trophy_slug,
                'trophy_name' => $trophy_name,
                'assignment_id' => $assignment_id,
            ),
        );

        $recipients = array($member_id);
        mj_member_record_notification($notification_data, $recipients);

        // Notifier les admins
        $admin_notification = array(
            'type' => MjNotificationTypes::TROPHY_EARNED,
            'title' => sprintf(__('%s a obtenu un trophée', 'mj-member'), $member_name),
            'excerpt' => sprintf(__('%s a débloqué le trophée « %s ».', 'mj-member'), $member_name, $trophy_name),
            'url' => mj_member_notification_get_manager_url('member', $member_id, 'badges'),
            'context' => 'admin_notification',
            'source' => 'system',
            'payload' => array(
                'member_id' => $member_id,
                'member_name' => $member_name,
                'trophy_id' => $trophy_id,
                'trophy_slug' => $trophy_slug,
                'trophy_name' => $trophy_name,
            ),
        );

        $admin_recipients = mj_member_notification_get_admin_recipients();
        if (!empty($admin_recipients)) {
            mj_member_record_notification($admin_notification, $admin_recipients);
        }

        mj_member_notification_log('trophy_earned', array(
            'member_id' => $member_id,
            'trophy_id' => $trophy_id,
            'trophy_slug' => $trophy_slug,
            'trophy_name' => $trophy_name,
        ));
    }

    add_action('mj_member_trophy_awarded', 'mj_member_notification_on_trophy_earned', 10, 4);
}

// ============================================================================
// LISTENER: Critère de badge validé
// ============================================================================

if (!function_exists('mj_member_notification_on_criterion_earned')) {
    /**
     * Déclenché après l'attribution de critères de badge à un membre.
     *
     * @param int   $member_id   ID du membre
     * @param int   $badge_id    ID du badge
     * @param array $criteria_ids IDs des critères nouvellement attribués
     * @return void
     */
    function mj_member_notification_on_criterion_earned($member_id, $badge_id, $criteria_ids): void
    {
        if (!function_exists('mj_member_record_notification')) {
            return;
        }

        $member_id = (int) $member_id;
        $badge_id = (int) $badge_id;

        if ($member_id <= 0 || $badge_id <= 0 || empty($criteria_ids)) {
            return;
        }

        // Récupérer les informations du badge
        $badge_name = sprintf(__('Badge #%d', 'mj-member'), $badge_id);
        $badge_icon = '🏅';
        if (class_exists(\Mj\Member\Classes\Crud\MjBadges::class)) {
            $badge = \Mj\Member\Classes\Crud\MjBadges::get($badge_id);
            if ($badge) {
                $badge_name = isset($badge['name']) ? (string) $badge['name'] : $badge_name;
                $badge_icon = isset($badge['icon']) && !empty($badge['icon']) ? (string) $badge['icon'] : $badge_icon;
            }
        }

        // Récupérer les noms des critères
        $criteria_names = array();
        if (class_exists(\Mj\Member\Classes\Crud\MjBadgeCriteria::class)) {
            foreach ($criteria_ids as $criterion_id) {
                $criterion = \Mj\Member\Classes\Crud\MjBadgeCriteria::get_by_id((int) $criterion_id);
                if ($criterion && isset($criterion['label'])) {
                    $criteria_names[] = (string) $criterion['label'];
                }
            }
        }

        $criteria_count = count($criteria_ids);
        $criteria_label = !empty($criteria_names)
            ? implode(', ', $criteria_names)
            : sprintf(_n('%d critère', '%d critères', $criteria_count, 'mj-member'), $criteria_count);

        // URL vers la page des badges du membre
        $badges_url = home_url('/mon-compte/badge/');

        // Notification au membre
        $notification_data = array(
            'type' => MjNotificationTypes::CRITERION_EARNED,
            'title' => sprintf(__('%s Critère validé !', 'mj-member'), $badge_icon),
            'excerpt' => sprintf(
                _n(
                    'Tu as validé le critère « %s » pour le badge « %s ».',
                    'Tu as validé les critères « %s » pour le badge « %s ».',
                    $criteria_count,
                    'mj-member'
                ),
                $criteria_label,
                $badge_name
            ),
            'url' => $badges_url,
            'context' => 'gamification',
            'source' => 'system',
            'payload' => array(
                'member_id' => $member_id,
                'badge_id' => $badge_id,
                'badge_name' => $badge_name,
                'criteria_ids' => $criteria_ids,
                'criteria_names' => $criteria_names,
            ),
        );

        $recipients = array($member_id);
        mj_member_record_notification($notification_data, $recipients);

        mj_member_notification_log('criterion_earned', array(
            'member_id' => $member_id,
            'badge_id' => $badge_id,
            'criteria_ids' => $criteria_ids,
        ));
    }

    add_action('mj_member_badge_criteria_awarded', 'mj_member_notification_on_criterion_earned', 10, 3);
}

// ============================================================================
// LISTENER: Avatar Grimlins appliqué
// ============================================================================

if (!function_exists('mj_member_notification_on_avatar_applied')) {
    /**
     * Déclenché lors de l'application d'un avatar Grimlins.
     *
     * @param object $member        Objet membre
     * @param int    $attachment_id ID de l'attachment
     * @return void
     */
    function mj_member_notification_on_avatar_applied($member, $attachment_id): void
    {
        if (!function_exists('mj_member_record_notification')) {
            return;
        }

        $member_id = isset($member->id) ? (int) $member->id : 0;
        if ($member_id <= 0) {
            return;
        }

        $member_name = mj_member_notification_get_member_name($member);

        // Notification au membre
        $notification_data = array(
            'type' => MjNotificationTypes::AVATAR_APPLIED,
            'title' => __('Nouvel avatar !', 'mj-member'),
            'excerpt' => __('Ton nouveau avatar personnalisé a été appliqué à ton profil.', 'mj-member'),
            'url' => '',
            'context' => 'avatar',
            'source' => 'system',
            'payload' => array(
                'member_id' => $member_id,
                'attachment_id' => $attachment_id,
            ),
        );

        $recipients = array($member_id);
        mj_member_record_notification($notification_data, $recipients);

        // Notifier les admins
        $admin_notification = array(
            'type' => MjNotificationTypes::AVATAR_APPLIED,
            'title' => sprintf(__('%s a un nouvel avatar', 'mj-member'), $member_name),
            'excerpt' => sprintf(__('%s a personnalisé son avatar via Grimlins.', 'mj-member'), $member_name),
            'url' => mj_member_notification_get_manager_url('member', $member_id),
            'context' => 'admin_notification',
            'source' => 'system',
            'payload' => array(
                'member_id' => $member_id,
                'member_name' => $member_name,
                'attachment_id' => $attachment_id,
            ),
        );

        $admin_recipients = mj_member_notification_get_admin_recipients();
        if (!empty($admin_recipients)) {
            mj_member_record_notification($admin_notification, $admin_recipients);
        }

        mj_member_notification_log('avatar_applied', array(
            'member_id' => $member_id,
            'attachment_id' => $attachment_id,
        ));
    }

    add_action('mj_member_grimlins_avatar_applied', 'mj_member_notification_on_avatar_applied', 10, 2);
}

// ============================================================================
// LISTENER: Tâche assignée
// ============================================================================

if (!function_exists('mj_member_notification_on_todo_assigned')) {
    /**
     * Déclenché lors de l'assignation d'une tâche.
     *
     * @param int    $todo_id           ID de la tâche
     * @param array  $assigned_member_ids IDs des membres assignés
     * @param string $title             Titre de la tâche
     * @param int    $assigned_by       ID utilisateur qui a assigné
     * @return void
     */
    function mj_member_notification_on_todo_assigned($todo_id, $assigned_member_ids, $title, $assigned_by): void
    {
        if (!function_exists('mj_member_record_notification')) {
            return;
        }

        $todo_id = (int) $todo_id;
        $assigned_by = (int) $assigned_by;

        if ($todo_id <= 0 || empty($assigned_member_ids)) {
            return;
        }

        // Récupérer le nom de celui qui assigne
        $assigner_name = '';
        $assigner_user = get_user_by('id', $assigned_by);
        if ($assigner_user) {
            $assigner_name = $assigner_user->display_name;
        }

        // Titre de la tâche
        $task_title = $title !== '' ? sanitize_text_field($title) : sprintf(__('Tâche #%d', 'mj-member'), $todo_id);

        // URL vers la tâche
        $todo_url = home_url('/mon-compte/todo/?tache=' . $todo_id);

        // Notification aux membres assignés
        $notification_data = array(
            'type' => MjNotificationTypes::TODO_ASSIGNED,
            'title' => __('Nouvelle tâche assignée', 'mj-member'),
            'excerpt' => $assigner_name !== ''
                ? sprintf(__('%s t\'a assigné la tâche « %s ».', 'mj-member'), $assigner_name, $task_title)
                : sprintf(__('Tu as été assigné à la tâche « %s ».', 'mj-member'), $task_title),
            'url' => $todo_url,
            'context' => 'todos',
            'source' => 'system',
            'payload' => array(
                'todo_id' => $todo_id,
                'todo_title' => $task_title,
                'assigned_by' => $assigned_by,
                'assigner_name' => $assigner_name,
            ),
        );

        // Convertir les IDs en entiers et exclure celui qui assigne
        $recipients = array();
        foreach ($assigned_member_ids as $member_id) {
            $member_id = (int) $member_id;
            if ($member_id > 0 && $member_id !== $assigned_by) {
                $recipients[] = $member_id;
            }
        }

        if (!empty($recipients)) {
            mj_member_record_notification($notification_data, $recipients);
        }

        mj_member_notification_log('todo_assigned', array(
            'todo_id' => $todo_id,
            'assigned_member_ids' => $assigned_member_ids,
            'assigned_by' => $assigned_by,
        ));
    }

    add_action('mj_member_todo_assigned', 'mj_member_notification_on_todo_assigned', 10, 4);
}

// ============================================================================
// LISTENER: Note ajoutée sur une tâche
// ============================================================================

if (!function_exists('mj_member_notification_on_todo_note_added')) {
    /**
     * Déclenché lors de l'ajout d'une note sur une tâche.
     *
     * @param int    $todo_id       ID de la tâche
     * @param int    $note_id       ID de la note
     * @param int    $author_id     ID du membre auteur
     * @param string $todo_title    Titre de la tâche
     * @param array  $assignee_ids  IDs des membres assignés
     * @return void
     */
    function mj_member_notification_on_todo_note_added($todo_id, $note_id, $author_id, $todo_title, $assignee_ids): void
    {
        if (!function_exists('mj_member_record_notification')) {
            return;
        }

        $todo_id = (int) $todo_id;
        $note_id = (int) $note_id;
        $author_id = (int) $author_id;

        if ($todo_id <= 0 || $note_id <= 0 || $author_id <= 0 || empty($assignee_ids)) {
            return;
        }

        // Récupérer le nom de l'auteur
        $author_name = '';
        if (class_exists(MjMembers::class)) {
            $author = MjMembers::getById($author_id);
            $author_name = mj_member_notification_get_member_name($author);
        }

        // Titre de la tâche
        $task_title = $todo_title !== '' ? sanitize_text_field($todo_title) : sprintf(__('Tâche #%d', 'mj-member'), $todo_id);

        // URL vers la tâche
        $todo_url = home_url('/mon-compte/todo/?tache=' . $todo_id);

        // Notification aux autres membres assignés (exclure l'auteur)
        $recipients = array();
        foreach ($assignee_ids as $member_id) {
            $member_id = (int) $member_id;
            if ($member_id > 0 && $member_id !== $author_id) {
                $recipients[] = $member_id;
            }
        }

        if (empty($recipients)) {
            return;
        }

        $notification_data = array(
            'type' => MjNotificationTypes::TODO_NOTE_ADDED,
            'title' => __('Note ajoutée', 'mj-member'),
            'excerpt' => sprintf(__('%s a ajouté une note sur la tâche « %s ».', 'mj-member'), $author_name, $task_title),
            'url' => $todo_url,
            'context' => 'todos',
            'source' => 'system',
            'payload' => array(
                'todo_id' => $todo_id,
                'note_id' => $note_id,
                'todo_title' => $task_title,
                'author_id' => $author_id,
                'author_name' => $author_name,
            ),
        );

        mj_member_record_notification($notification_data, $recipients);

        mj_member_notification_log('todo_note_added', array(
            'todo_id' => $todo_id,
            'note_id' => $note_id,
            'author_id' => $author_id,
        ));
    }

    add_action('mj_member_todo_note_added', 'mj_member_notification_on_todo_note_added', 10, 5);
}

// ============================================================================
// LISTENER: Document ajouté sur une tâche
// ============================================================================

if (!function_exists('mj_member_notification_on_todo_media_added')) {
    /**
     * Déclenché lors de l'ajout d'un document sur une tâche.
     *
     * @param int    $todo_id       ID de la tâche
     * @param int    $author_id     ID du membre auteur
     * @param string $todo_title    Titre de la tâche
     * @param array  $assignee_ids  IDs des membres assignés
     * @param int    $media_count   Nombre de médias ajoutés
     * @return void
     */
    function mj_member_notification_on_todo_media_added($todo_id, $author_id, $todo_title, $assignee_ids, $media_count = 1): void
    {
        if (!function_exists('mj_member_record_notification')) {
            return;
        }

        $todo_id = (int) $todo_id;
        $author_id = (int) $author_id;
        $media_count = max(1, (int) $media_count);

        if ($todo_id <= 0 || $author_id <= 0 || empty($assignee_ids)) {
            return;
        }

        // Récupérer le nom de l'auteur
        $author_name = '';
        if (class_exists(MjMembers::class)) {
            $author = MjMembers::getById($author_id);
            $author_name = mj_member_notification_get_member_name($author);
        }

        // Titre de la tâche
        $task_title = $todo_title !== '' ? sanitize_text_field($todo_title) : sprintf(__('Tâche #%d', 'mj-member'), $todo_id);

        // URL vers la tâche
        $todo_url = home_url('/mon-compte/todo/?tache=' . $todo_id);

        // Notification aux autres membres assignés (exclure l'auteur)
        $recipients = array();
        foreach ($assignee_ids as $member_id) {
            $member_id = (int) $member_id;
            if ($member_id > 0 && $member_id !== $author_id) {
                $recipients[] = $member_id;
            }
        }

        if (empty($recipients)) {
            return;
        }

        $excerpt = $media_count > 1
            ? sprintf(__('%s a ajouté %d documents sur la tâche « %s ».', 'mj-member'), $author_name, $media_count, $task_title)
            : sprintf(__('%s a ajouté un document sur la tâche « %s ».', 'mj-member'), $author_name, $task_title);

        $notification_data = array(
            'type' => MjNotificationTypes::TODO_MEDIA_ADDED,
            'title' => __('📎 Document ajouté', 'mj-member'),
            'excerpt' => $excerpt,
            'url' => $todo_url,
            'context' => 'todos',
            'source' => 'system',
            'payload' => array(
                'todo_id' => $todo_id,
                'todo_title' => $task_title,
                'author_id' => $author_id,
                'author_name' => $author_name,
                'media_count' => $media_count,
            ),
        );

        mj_member_record_notification($notification_data, $recipients);

        mj_member_notification_log('todo_media_added', array(
            'todo_id' => $todo_id,
            'author_id' => $author_id,
            'media_count' => $media_count,
        ));
    }

    add_action('mj_member_todo_media_added', 'mj_member_notification_on_todo_media_added', 10, 5);
}

// ============================================================================
// LISTENER: Tâche terminée
// ============================================================================

if (!function_exists('mj_member_notification_on_todo_completed')) {
    /**
     * Déclenché lors de la clôture d'une tâche.
     *
     * @param int    $todo_id       ID de la tâche
     * @param int    $completed_by  ID du membre qui a terminé
     * @param string $todo_title    Titre de la tâche
     * @param array  $assignee_ids  IDs des membres assignés
     * @return void
     */
    function mj_member_notification_on_todo_completed($todo_id, $completed_by, $todo_title, $assignee_ids): void
    {
        if (!function_exists('mj_member_record_notification')) {
            return;
        }

        $todo_id = (int) $todo_id;
        $completed_by = (int) $completed_by;

        if ($todo_id <= 0 || $completed_by <= 0 || empty($assignee_ids)) {
            return;
        }

        // Récupérer le nom de celui qui a terminé
        $completer_name = '';
        if (class_exists(MjMembers::class)) {
            $completer = MjMembers::getById($completed_by);
            $completer_name = mj_member_notification_get_member_name($completer);
        }

        // Titre de la tâche
        $task_title = $todo_title !== '' ? sanitize_text_field($todo_title) : sprintf(__('Tâche #%d', 'mj-member'), $todo_id);

        // URL vers la tâche
        $todo_url = home_url('/mon-compte/todo/?tache=' . $todo_id);

        // Notification aux autres membres assignés (exclure celui qui a terminé)
        $recipients = array();
        foreach ($assignee_ids as $member_id) {
            $member_id = (int) $member_id;
            if ($member_id > 0 && $member_id !== $completed_by) {
                $recipients[] = $member_id;
            }
        }

        if (empty($recipients)) {
            return;
        }

        $notification_data = array(
            'type' => MjNotificationTypes::TODO_COMPLETED,
            'title' => __('✅ Tâche terminée', 'mj-member'),
            'excerpt' => sprintf(__('%s a terminé la tâche « %s ».', 'mj-member'), $completer_name, $task_title),
            'url' => $todo_url,
            'context' => 'todos',
            'source' => 'system',
            'payload' => array(
                'todo_id' => $todo_id,
                'todo_title' => $task_title,
                'completed_by' => $completed_by,
                'completer_name' => $completer_name,
            ),
        );

        mj_member_record_notification($notification_data, $recipients);

        mj_member_notification_log('todo_completed', array(
            'todo_id' => $todo_id,
            'completed_by' => $completed_by,
        ));
    }

    add_action('mj_member_todo_completed', 'mj_member_notification_on_todo_completed', 10, 4);
}

// ============================================================================
// LISTENER: Événement publié
// ============================================================================

if (!function_exists('mj_member_notification_on_event_published')) {
    /**
     * Déclenché lorsqu'un événement passe de brouillon à actif.
     *
     * @param int    $event_id ID de l'événement
     * @param object $event    L'événement publié
     * @return void
     */
    function mj_member_notification_on_event_published($event_id, $event): void
    {
        if (!function_exists('mj_member_record_notification')) {
            return;
        }

        $event_id = (int) $event_id;
        if ($event_id <= 0 || !$event) {
            return;
        }

        // Récupérer les infos de l'événement
        $event_title = isset($event->title) ? sanitize_text_field((string) $event->title) : sprintf(__('Événement #%d', 'mj-member'), $event_id);
        $event_emoji = isset($event->emoji) ? (string) $event->emoji : '📅';
        $event_slug = isset($event->slug) ? (string) $event->slug : '';

        // Construire l'URL vers l'événement
        $event_url = '';
        if ($event_slug !== '') {
            $event_url = home_url('/evenement/' . $event_slug . '/');
        } else {
            $event_url = home_url('/evenement/?id=' . $event_id);
        }

        // Récupérer tous les membres actifs
        $recipients = array();
        if (class_exists(MjMembers::class)) {
            $active_members = MjMembers::get_all(array(
                'filters' => array('status' => MjMembers::STATUS_ACTIVE),
                'limit' => 10000,
            ));
            if (is_array($active_members)) {
                foreach ($active_members as $member) {
                    if (isset($member->id) && (int) $member->id > 0) {
                        $recipients[] = (int) $member->id;
                    }
                }
            }
        }

        if (empty($recipients)) {
            return;
        }

        $notification_data = array(
            'type' => MjNotificationTypes::EVENT_NEW_PUBLISHED,
            'title' => sprintf(__('%s Nouvel événement !', 'mj-member'), $event_emoji),
            'excerpt' => sprintf(__('Découvre le nouvel événement « %s » et inscris-toi !', 'mj-member'), $event_title),
            'url' => $event_url,
            'context' => 'events',
            'source' => 'system',
            'payload' => array(
                'event_id' => $event_id,
                'event_title' => $event_title,
                'event_emoji' => $event_emoji,
                'event_slug' => $event_slug,
            ),
        );

        mj_member_record_notification($notification_data, $recipients);

        mj_member_notification_log('event_published', array(
            'event_id' => $event_id,
            'event_title' => $event_title,
            'recipients_count' => count($recipients),
        ));
    }

    add_action('mj_member_event_published', 'mj_member_notification_on_event_published', 10, 2);
}


// ============================================================================
// HOOK: Ajouter do_action dans les endroits manquants
// ============================================================================

// Ces hooks seront déclenchés par le code existant. Il faut ajouter les do_action
// dans les fichiers appropriés pour que les listeners fonctionnent.

/**
 * Liste des hooks à ajouter dans le code existant :
 *
 * 1. mj_member_event_photo_created
 *    - Fichier: includes/event_photos.php
 *    - Après: MjEventPhotos::create($photo_payload)
 *    - Params: $photo_id, $event_id, $member_id, $status
 *
 * 2. mj_member_profile_updated
 *    - Fichier: includes/member_accounts.php (mj_member_ajax_update_child_profile)
 *    - Après: MjMembers::update($child_id, $updates)
 *    - Params: $child_id, $updates, $context
 *
 * 3. mj_member_idea_published
 *    - Fichier: includes/idea_box.php (mj_member_ajax_idea_box_create)
 *    - Après: MjIdeas::create(...)
 *    - Params: $idea_id, $member_id, $title, $content
 */

// ============================================================================
// TÉMOIGNAGES NOTIFICATIONS
// ============================================================================

if (!function_exists('mj_member_notification_on_testimonial_approved')) {
    /**
     * Notification quand un témoignage est approuvé.
     *
     * @param int $testimonial_id
     * @param int $member_id Auteur du témoignage
     */
    function mj_member_notification_on_testimonial_approved(int $testimonial_id, int $member_id): void
    {
        if (!class_exists(MjNotifications::class)) {
            return;
        }

        $notification_data = array(
            'type' => MjNotificationTypes::TESTIMONIAL_APPROVED,
            'title' => __('Ton témoignage a été publié !', 'mj-member'),
            'excerpt' => __('Ton témoignage a été approuvé et est maintenant visible par tous les membres.', 'mj-member'),
            'url' => home_url('/mon-compte/temoignages/?section=testimonials&post=' . $testimonial_id),
            'context' => 'testimonials',
            'source' => 'system',
            'payload' => array(
                'testimonial_id' => $testimonial_id,
            ),
        );

        mj_member_record_notification($notification_data, array($member_id));

        mj_member_notification_log('testimonial_approved', array(
            'testimonial_id' => $testimonial_id,
            'member_id' => $member_id,
        ));
    }

    add_action('mj_member_testimonial_approved', 'mj_member_notification_on_testimonial_approved', 10, 2);
}

if (!function_exists('mj_member_notification_on_testimonial_rejected')) {
    /**
     * Notification quand un témoignage est refusé.
     *
     * @param int    $testimonial_id
     * @param int    $member_id Auteur du témoignage
     * @param string $reason Raison du refus
     */
    function mj_member_notification_on_testimonial_rejected(int $testimonial_id, int $member_id, string $reason = ''): void
    {
        if (!class_exists(MjNotifications::class)) {
            return;
        }

        $excerpt = __('Ton témoignage n\'a pas été approuvé.', 'mj-member');
        if (!empty($reason)) {
            $excerpt .= ' ' . sprintf(__('Raison : %s', 'mj-member'), $reason);
        }

        $notification_data = array(
            'type' => MjNotificationTypes::TESTIMONIAL_REJECTED,
            'title' => __('Témoignage non publié', 'mj-member'),
            'excerpt' => $excerpt,
            'url' => home_url('/mon-compte/temoignages/?section=testimonials&post=' . $testimonial_id),
            'context' => 'testimonials',
            'source' => 'system',
            'payload' => array(
                'testimonial_id' => $testimonial_id,
                'reason' => $reason,
            ),
        );

        mj_member_record_notification($notification_data, array($member_id));

        mj_member_notification_log('testimonial_rejected', array(
            'testimonial_id' => $testimonial_id,
            'member_id' => $member_id,
            'reason' => $reason,
        ));
    }

    add_action('mj_member_testimonial_rejected', 'mj_member_notification_on_testimonial_rejected', 10, 3);
}

if (!function_exists('mj_member_notification_on_testimonial_reaction')) {
    /**
     * Notification quand quelqu'un réagit à un témoignage.
     *
     * @param int    $testimonial_id
     * @param int    $author_member_id Auteur du témoignage
     * @param int    $reactor_member_id Membre qui a réagi
     * @param string $reaction_type Type de réaction (like, love, etc.)
     */
    function mj_member_notification_on_testimonial_reaction(int $testimonial_id, int $author_member_id, int $reactor_member_id, string $reaction_type): void
    {
        // Ne pas notifier si l'auteur réagit à son propre témoignage
        if ($author_member_id === $reactor_member_id) {
            return;
        }

        if (!class_exists(MjNotifications::class) || !class_exists(MjMembers::class)) {
            return;
        }

        $reactor = MjMembers::getById($reactor_member_id);
        $reactor_name = mj_member_notification_get_member_name($reactor);

        $reaction_emojis = array(
            'like' => '👍',
            'love' => '❤️',
            'haha' => '😂',
            'wow' => '😮',
            'sad' => '😢',
            'angry' => '😡',
        );
        $emoji = isset($reaction_emojis[$reaction_type]) ? $reaction_emojis[$reaction_type] : '👍';

        $notification_data = array(
            'type' => MjNotificationTypes::TESTIMONIAL_REACTION,
            'title' => sprintf(__('%s %s a réagi à ton témoignage', 'mj-member'), $emoji, $reactor_name),
            'excerpt' => __('Clique pour voir ton témoignage.', 'mj-member'),
            'url' => home_url('/mon-compte/temoignages/?section=testimonials&post=' . $testimonial_id),
            'context' => 'testimonials',
            'source' => 'member',
            'payload' => array(
                'testimonial_id' => $testimonial_id,
                'reactor_member_id' => $reactor_member_id,
                'reaction_type' => $reaction_type,
            ),
        );

        mj_member_record_notification($notification_data, array($author_member_id));

        mj_member_notification_log('testimonial_reaction', array(
            'testimonial_id' => $testimonial_id,
            'author_member_id' => $author_member_id,
            'reactor_member_id' => $reactor_member_id,
            'reaction_type' => $reaction_type,
        ));
    }

    add_action('mj_member_testimonial_reaction', 'mj_member_notification_on_testimonial_reaction', 10, 4);
}

if (!function_exists('mj_member_notification_on_testimonial_comment')) {
    /**
     * Notification quand quelqu'un commente un témoignage.
     *
     * @param int $testimonial_id
     * @param int $author_member_id Auteur du témoignage
     * @param int $commenter_member_id Membre qui a commenté
     * @param int $comment_id
     */
    function mj_member_notification_on_testimonial_comment(int $testimonial_id, int $author_member_id, int $commenter_member_id, int $comment_id): void
    {
        // Ne pas notifier si l'auteur commente son propre témoignage
        if ($author_member_id === $commenter_member_id) {
            return;
        }

        if (!class_exists(MjNotifications::class) || !class_exists(MjMembers::class)) {
            return;
        }

        $commenter = MjMembers::getById($commenter_member_id);
        $commenter_name = mj_member_notification_get_member_name($commenter);

        $notification_data = array(
            'type' => MjNotificationTypes::TESTIMONIAL_COMMENT,
            'title' => sprintf(__('%s a commenté ton témoignage', 'mj-member'), $commenter_name),
            'excerpt' => __('Clique pour voir le commentaire.', 'mj-member'),
            'url' => home_url('/mon-compte/temoignages/?section=testimonials&post=' . $testimonial_id),
            'context' => 'testimonials',
            'source' => 'member',
            'payload' => array(
                'testimonial_id' => $testimonial_id,
                'commenter_member_id' => $commenter_member_id,
                'comment_id' => $comment_id,
            ),
        );

        mj_member_record_notification($notification_data, array($author_member_id));

        mj_member_notification_log('testimonial_comment', array(
            'testimonial_id' => $testimonial_id,
            'author_member_id' => $author_member_id,
            'commenter_member_id' => $commenter_member_id,
            'comment_id' => $comment_id,
        ));
    }

    add_action('mj_member_testimonial_comment', 'mj_member_notification_on_testimonial_comment', 10, 4);
}

if (!function_exists('mj_member_notification_on_testimonial_comment_reply')) {
    /**
     * Notification quand quelqu'un répond à un commentaire.
     *
     * @param int $testimonial_id
     * @param int $original_commenter_id Auteur du commentaire original
     * @param int $replier_member_id Membre qui a répondu
     * @param int $reply_comment_id
     */
    function mj_member_notification_on_testimonial_comment_reply(int $testimonial_id, int $original_commenter_id, int $replier_member_id, int $reply_comment_id): void
    {
        // Ne pas notifier si on répond à son propre commentaire
        if ($original_commenter_id === $replier_member_id) {
            return;
        }

        if (!class_exists(MjNotifications::class) || !class_exists(MjMembers::class)) {
            return;
        }

        $replier = MjMembers::getById($replier_member_id);
        $replier_name = mj_member_notification_get_member_name($replier);

        $notification_data = array(
            'type' => MjNotificationTypes::TESTIMONIAL_COMMENT_REPLY,
            'title' => sprintf(__('%s a répondu à ton commentaire', 'mj-member'), $replier_name),
            'excerpt' => __('Clique pour voir la réponse.', 'mj-member'),
            'url' => home_url('/mon-compte/temoignages/?section=testimonials&post=' . $testimonial_id),
            'context' => 'testimonials',
            'source' => 'member',
            'payload' => array(
                'testimonial_id' => $testimonial_id,
                'replier_member_id' => $replier_member_id,
                'reply_comment_id' => $reply_comment_id,
            ),
        );

        mj_member_record_notification($notification_data, array($original_commenter_id));

        mj_member_notification_log('testimonial_comment_reply', array(
            'testimonial_id' => $testimonial_id,
            'original_commenter_id' => $original_commenter_id,
            'replier_member_id' => $replier_member_id,
            'reply_comment_id' => $reply_comment_id,
        ));
    }

    add_action('mj_member_testimonial_comment_reply', 'mj_member_notification_on_testimonial_comment_reply', 10, 4);
}

if (!function_exists('mj_member_notification_on_testimonial_new_pending')) {
    /**
     * Notification aux admins quand un nouveau témoignage est soumis.
     *
     * @param int $testimonial_id
     * @param int $member_id Auteur du témoignage
     */
    function mj_member_notification_on_testimonial_new_pending(int $testimonial_id, int $member_id): void
    {
        if (!class_exists(MjNotifications::class) || !class_exists(MjMembers::class) || !class_exists(MjRoles::class)) {
            return;
        }

        $author = MjMembers::getById($member_id);
        $author_name = mj_member_notification_get_member_name($author);

        // Récupérer les coordinateurs et animateurs (staff)
        $coordinateurs = MjMembers::getByRole(MjRoles::COORDINATEUR);
        $animateurs = MjMembers::getByRole(MjRoles::ANIMATEUR);
        $staff = array_merge($coordinateurs ?: [], $animateurs ?: []);
        if (empty($staff)) {
            return;
        }

        $admin_ids = array_map(function ($member) {
            return isset($member->id) ? (int) $member->id : 0;
        }, $staff);
        $admin_ids = array_filter($admin_ids);

        if (empty($admin_ids)) {
            return;
        }

        $notification_data = array(
            'type' => MjNotificationTypes::TESTIMONIAL_NEW_PENDING,
            'title' => __('Nouveau témoignage à modérer', 'mj-member'),
            'excerpt' => sprintf(__('%s a soumis un témoignage.', 'mj-member'), $author_name),
            'url' => mj_member_notification_get_manager_url('member', $member_id, 'testimonials'),
            'context' => 'testimonials',
            'source' => 'system',
            'payload' => array(
                'testimonial_id' => $testimonial_id,
                'author_member_id' => $member_id,
            ),
        );

        mj_member_record_notification($notification_data, $admin_ids);

        mj_member_notification_log('testimonial_new_pending', array(
            'testimonial_id' => $testimonial_id,
            'member_id' => $member_id,
            'admins_count' => count($admin_ids),
        ));
    }

    add_action('mj_member_testimonial_created', 'mj_member_notification_on_testimonial_new_pending', 10, 2);
}

// ============================================================================
// LISTENER: Nouvelle note de frais créée → Notifier les coordinateurs
// ============================================================================

if (!function_exists('mj_member_notification_on_expense_created')) {
    /**
     * @param int   $expense_id
     * @param int   $member_id  Auteur de la note
     * @param float $amount
     * @return void
     */
    function mj_member_notification_on_expense_created(int $expense_id, int $member_id, float $amount): void
    {
        if (!function_exists('mj_member_record_notification') || !class_exists(MjMembers::class) || !class_exists(MjRoles::class)) {
            return;
        }

        $author = MjMembers::getById($member_id);
        $author_name = mj_member_notification_get_member_name($author);
        $formatted = number_format($amount, 2, ',', ' ') . ' €';

        // Récupérer les coordinateurs
        $coordinateurs = MjMembers::getByRole(MjRoles::COORDINATEUR);
        if (empty($coordinateurs)) {
            return;
        }

        $recipient_ids = array();
        foreach ($coordinateurs as $c) {
            $cid = isset($c->id) ? (int) $c->id : 0;
            if ($cid > 0 && $cid !== $member_id) {
                $recipient_ids[] = $cid;
            }
        }

        if (empty($recipient_ids)) {
            return;
        }

        $notification_data = array(
            'type'    => MjNotificationTypes::EXPENSE_CREATED,
            'title'   => __('Nouvelle note de frais', 'mj-member'),
            'excerpt' => sprintf(__('%s a soumis une note de frais de %s.', 'mj-member'), $author_name, $formatted),
            'url'     => home_url('/mon-compte/notes-de-frais/?section=expenses'),
            'context' => 'expenses',
            'source'  => 'system',
            'payload' => array(
                'expense_id'  => $expense_id,
                'member_id'   => $member_id,
                'amount'      => $amount,
                'author_name' => $author_name,
            ),
        );

        mj_member_record_notification($notification_data, $recipient_ids);

        mj_member_notification_log('expense_created', array(
            'expense_id'       => $expense_id,
            'member_id'        => $member_id,
            'recipients_count' => count($recipient_ids),
        ));
    }

    add_action('mj_member_expense_created', 'mj_member_notification_on_expense_created', 10, 3);
}

// ============================================================================
// LISTENER: Note de frais remboursée → Notifier le membre
// ============================================================================

if (!function_exists('mj_member_notification_on_expense_reimbursed')) {
    /**
     * @param int   $expense_id
     * @param int   $member_id  Propriétaire de la note
     * @param float $amount
     * @return void
     */
    function mj_member_notification_on_expense_reimbursed(int $expense_id, int $member_id, float $amount): void
    {
        if (!function_exists('mj_member_record_notification') || !class_exists(MjMembers::class)) {
            return;
        }

        if ($member_id <= 0) {
            return;
        }

        $formatted = number_format($amount, 2, ',', ' ') . ' €';

        $notification_data = array(
            'type'    => MjNotificationTypes::EXPENSE_REIMBURSED,
            'title'   => __('Note de frais remboursée', 'mj-member'),
            'excerpt' => sprintf(__('Ta note de frais de %s a été remboursée.', 'mj-member'), $formatted),
            'url'     => home_url('/mon-compte/notes-de-frais/?section=expenses'),
            'context' => 'expenses',
            'source'  => 'system',
            'payload' => array(
                'expense_id' => $expense_id,
                'amount'     => $amount,
            ),
        );

        mj_member_record_notification($notification_data, array($member_id));

        mj_member_notification_log('expense_reimbursed', array(
            'expense_id' => $expense_id,
            'member_id'  => $member_id,
        ));
    }

    add_action('mj_member_expense_reimbursed', 'mj_member_notification_on_expense_reimbursed', 10, 3);
}
// ============================================================================
// LISTENER: Note de frais refusée → Notifier le membre
// ============================================================================

if (!function_exists('mj_member_notification_on_expense_rejected')) {
    /**
     * @param int    $expense_id
     * @param int    $member_id  Propriétaire de la note
     * @param float  $amount
     * @param string $reason     Motif du refus
     * @return void
     */
    function mj_member_notification_on_expense_rejected(int $expense_id, int $member_id, float $amount, string $reason = ''): void
    {
        if (!function_exists('mj_member_record_notification') || !class_exists(MjMembers::class)) {
            return;
        }

        if ($member_id <= 0) {
            return;
        }

        $formatted = number_format($amount, 2, ',', ' ') . ' €';

        $excerpt = sprintf(__('Ta note de frais de %s a été refusée.', 'mj-member'), $formatted);
        if ($reason !== '') {
            $excerpt .= ' ' . sprintf(__('Motif : %s', 'mj-member'), $reason);
        }

        $notification_data = array(
            'type'    => MjNotificationTypes::EXPENSE_REJECTED,
            'title'   => __('Note de frais refusée', 'mj-member'),
            'excerpt' => $excerpt,
            'url'     => home_url('/mon-compte/notes-de-frais/?section=expenses'),
            'context' => 'expenses',
            'source'  => 'system',
            'payload' => array(
                'expense_id' => $expense_id,
                'amount'     => $amount,
                'reason'     => $reason,
            ),
        );

        mj_member_record_notification($notification_data, array($member_id));

        mj_member_notification_log('expense_rejected', array(
            'expense_id' => $expense_id,
            'member_id'  => $member_id,
        ));
    }

    add_action('mj_member_expense_rejected', 'mj_member_notification_on_expense_rejected', 10, 4);
}

// ============================================================================
// LISTENER: Nouveau frais kilométrique créé → Notifier les coordinateurs
// ============================================================================

if (!function_exists('mj_member_notification_on_mileage_created')) {
    /**
     * @param int   $mileage_id
     * @param int   $member_id  Auteur de la demande
     * @param float $total_cost
     * @return void
     */
    function mj_member_notification_on_mileage_created(int $mileage_id, int $member_id, float $total_cost): void
    {
        if (!function_exists('mj_member_record_notification') || !class_exists(MjMembers::class) || !class_exists(MjRoles::class)) {
            return;
        }

        $author = MjMembers::getById($member_id);
        $author_name = mj_member_notification_get_member_name($author);
        $formatted = number_format($total_cost, 2, ',', ' ') . ' €';

        // Récupérer les coordinateurs
        $coordinateurs = MjMembers::getByRole(MjRoles::COORDINATEUR);
        if (empty($coordinateurs)) {
            return;
        }

        $recipient_ids = array();
        foreach ($coordinateurs as $c) {
            $cid = isset($c->id) ? (int) $c->id : 0;
            if ($cid > 0 && $cid !== $member_id) {
                $recipient_ids[] = $cid;
            }
        }

        if (empty($recipient_ids)) {
            return;
        }

        $notification_data = array(
            'type'    => MjNotificationTypes::MILEAGE_CREATED,
            'title'   => __('Nouveau frais kilométrique', 'mj-member'),
            'excerpt' => sprintf(__('%s a soumis un frais kilométrique de %s.', 'mj-member'), $author_name, $formatted),
            'url'     => home_url('/mon-compte/frais-kilometrique/?section=mileage'),
            'context' => 'mileage',
            'source'  => 'system',
            'payload' => array(
                'mileage_id'  => $mileage_id,
                'member_id'   => $member_id,
                'total_cost'  => $total_cost,
                'author_name' => $author_name,
            ),
        );

        mj_member_record_notification($notification_data, $recipient_ids);

        mj_member_notification_log('mileage_created', array(
            'mileage_id'       => $mileage_id,
            'member_id'        => $member_id,
            'recipients_count' => count($recipient_ids),
        ));
    }

    add_action('mj_member_mileage_created', 'mj_member_notification_on_mileage_created', 10, 3);
}

// ============================================================================
// LISTENER: Frais kilométrique approuvé → Notifier le membre
// ============================================================================

if (!function_exists('mj_member_notification_on_mileage_approved')) {
    /**
     * @param int   $mileage_id
     * @param int   $member_id  Propriétaire du trajet
     * @param float $total_cost
     * @return void
     */
    function mj_member_notification_on_mileage_approved(int $mileage_id, int $member_id, float $total_cost): void
    {
        if (!function_exists('mj_member_record_notification') || !class_exists(MjMembers::class)) {
            return;
        }

        if ($member_id <= 0) {
            return;
        }

        $formatted = number_format($total_cost, 2, ',', ' ') . ' €';

        $notification_data = array(
            'type'    => MjNotificationTypes::MILEAGE_APPROVED,
            'title'   => __('Frais kilométrique approuvé', 'mj-member'),
            'excerpt' => sprintf(__('Ton frais kilométrique de %s a été approuvé.', 'mj-member'), $formatted),
            'url'     => home_url('/mon-compte/frais-kilometrique/?section=mileage'),
            'context' => 'mileage',
            'source'  => 'system',
            'payload' => array(
                'mileage_id' => $mileage_id,
                'total_cost' => $total_cost,
            ),
        );

        mj_member_record_notification($notification_data, array($member_id));

        mj_member_notification_log('mileage_approved', array(
            'mileage_id' => $mileage_id,
            'member_id'  => $member_id,
        ));
    }

    add_action('mj_member_mileage_approved', 'mj_member_notification_on_mileage_approved', 10, 3);
}

// ============================================================================
// LISTENER: Frais kilométrique remboursé → Notifier le membre
// ============================================================================

if (!function_exists('mj_member_notification_on_mileage_reimbursed')) {
    /**
     * @param int   $mileage_id
     * @param int   $member_id  Propriétaire du trajet
     * @param float $total_cost
     * @return void
     */
    function mj_member_notification_on_mileage_reimbursed(int $mileage_id, int $member_id, float $total_cost): void
    {
        if (!function_exists('mj_member_record_notification') || !class_exists(MjMembers::class)) {
            return;
        }

        if ($member_id <= 0) {
            return;
        }

        $formatted = number_format($total_cost, 2, ',', ' ') . ' €';

        $notification_data = array(
            'type'    => MjNotificationTypes::MILEAGE_REIMBURSED,
            'title'   => __('Frais kilométrique remboursé', 'mj-member'),
            'excerpt' => sprintf(__('Ton frais kilométrique de %s a été remboursé.', 'mj-member'), $formatted),
            'url'     => home_url('/mon-compte/frais-kilometrique/?section=mileage'),
            'context' => 'mileage',
            'source'  => 'system',
            'payload' => array(
                'mileage_id' => $mileage_id,
                'total_cost' => $total_cost,
            ),
        );

        mj_member_record_notification($notification_data, array($member_id));

        mj_member_notification_log('mileage_reimbursed', array(
            'mileage_id' => $mileage_id,
            'member_id'  => $member_id,
        ));
    }

    add_action('mj_member_mileage_reimbursed', 'mj_member_notification_on_mileage_reimbursed', 10, 3);
}

// ============================================================================
// LISTENER: Document employé ajouté
// ============================================================================

if (!function_exists('mj_member_notification_on_employee_document_uploaded')) {
    /**
     * Notifie le membre lorsqu'un document est ajouté à son dossier employé.
     *
     * @param int    $doc_id      ID du document créé.
     * @param int    $member_id   ID du membre destinataire.
     * @param string $doc_type    Type du document (payslip, contract, misc).
     * @param string $label       Libellé du document.
     * @param int    $uploaded_by ID du membre qui a uploadé (coordinateur).
     */
    function mj_member_notification_on_employee_document_uploaded(
        int $doc_id,
        int $member_id,
        string $doc_type,
        string $label,
        int $uploaded_by
    ): void {
        if (!class_exists(MjNotifications::class) || !class_exists(MjMembers::class)) {
            return;
        }

        // Ne pas notifier si le coordinateur s'ajoute un document à lui-même
        if ($member_id === $uploaded_by) {
            return;
        }

        // Build a descriptive string depending on the document type
        $doc = MjEmployeeDocuments::get_by_id($doc_id);
        $months = array(
            1 => 'janvier', 2 => 'février', 3 => 'mars', 4 => 'avril',
            5 => 'mai', 6 => 'juin', 7 => 'juillet', 8 => 'août',
            9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'décembre',
        );

        if ($doc_type === MjEmployeeDocuments::TYPE_PAYSLIP && $doc) {
            $m = isset($doc->payslip_month) ? (int) $doc->payslip_month : 0;
            $y = isset($doc->payslip_year)  ? (int) $doc->payslip_year  : 0;
            $period = ($m && $y) ? ($months[$m] ?? $m) . ' ' . $y : '';
            $description = $period
                ? sprintf(__('Fiche de paie (%s)', 'mj-member'), $period)
                : __('Fiche de paie', 'mj-member');
        } elseif ($doc_type === MjEmployeeDocuments::TYPE_CONTRACT) {
            $file_name = $doc ? ($doc->original_name ?? '') : '';
            $description = $file_name
                ? sprintf(__('Emploi (%s)', 'mj-member'), $file_name)
                : __('Emploi', 'mj-member');
        } else {
            $file_name = $doc ? ($doc->original_name ?? '') : '';
            $description = $file_name
                ? sprintf(__('Divers (%s)', 'mj-member'), $file_name)
                : __('Divers', 'mj-member');
        }

        $notification_data = array(
            'type'    => MjNotificationTypes::EMPLOYEE_DOCUMENT_UPLOADED,
            'title'   => __('📄 Nouveau document', 'mj-member'),
            'excerpt' => sprintf(
                __('Un nouveau document a été ajouté à ton dossier : %s.', 'mj-member'),
                $description
            ),
            'url'     => home_url('/mon-compte/documents-employe/'),
            'context' => 'employee_documents',
            'source'  => 'system',
            'payload' => array(
                'doc_id'      => $doc_id,
                'member_id'   => $member_id,
                'doc_type'    => $doc_type,
                'label'       => $label,
                'uploaded_by' => $uploaded_by,
            ),
        );

        mj_member_record_notification($notification_data, array($member_id));

        mj_member_notification_log('employee_document_uploaded', array(
            'doc_id'      => $doc_id,
            'member_id'   => $member_id,
            'doc_type'    => $doc_type,
            'uploaded_by' => $uploaded_by,
        ));
    }

    add_action('mj_member_employee_document_uploaded', 'mj_member_notification_on_employee_document_uploaded', 10, 5);
}