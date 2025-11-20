<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . 'MjEventAnimateurs.php';

class MjEventRegistrations {
    const STATUS_PENDING = 'en_attente';
    const STATUS_CONFIRMED = 'valide';
    const STATUS_CANCELLED = 'annule';

    const META_STATUS_LABELS = array(
        self::STATUS_PENDING => 'En attente',
        self::STATUS_CONFIRMED => 'Valide',
        self::STATUS_CANCELLED => 'Annule',
    );

    /**
     * @return array<string, string>
     */
    public static function get_status_labels() {
        return self::META_STATUS_LABELS;
    }

    /**
     * @param int $event_id
     * @return array<int, object>
     */
    public static function get_by_event($event_id) {
        $event_id = (int) $event_id;
        if ($event_id <= 0) {
            return array();
        }

        global $wpdb;
        $table = mj_member_get_event_registrations_table_name();
        $members_table = MjMembers_CRUD::getTableName(MjMembers_CRUD::TABLE_NAME);

        $sql = "SELECT r.*, m.first_name, m.last_name, m.role, m.birth_date, m.email, m.guardian_id, m.is_autonomous
                FROM {$table} AS r
                LEFT JOIN {$members_table} AS m ON m.id = r.member_id
                WHERE r.event_id = %d
                ORDER BY r.created_at DESC";

        return $wpdb->get_results($wpdb->prepare($sql, $event_id));
    }

    /**
     * @param int $registration_id
     * @return object|null
     */
    public static function get($registration_id) {
        global $wpdb;
        $table = mj_member_get_event_registrations_table_name();

        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", (int) $registration_id));
    }

    /**
     * @param int $event_id
     * @param int $member_id
     * @return object|null
     */
    public static function get_existing($event_id, $member_id) {
        global $wpdb;
        $table = mj_member_get_event_registrations_table_name();

        $sql = "SELECT * FROM {$table} WHERE event_id = %d AND member_id = %d ORDER BY created_at DESC LIMIT 1";
        return $wpdb->get_row($wpdb->prepare($sql, (int) $event_id, (int) $member_id));
    }

    /**
     * @param int $event_id
     * @param int $member_id
     * @param array<string, mixed> $args
     * @return int|WP_Error
     */
    public static function create($event_id, $member_id, array $args = array()) {
        $event_id = (int) $event_id;
        $member_id = (int) $member_id;

        if ($event_id <= 0 || $member_id <= 0) {
            return new WP_Error('mj_event_registration_invalid_ids', 'Evenement ou membre invalide.');
        }

        $event = MjEvents_CRUD::find($event_id);
        if (!$event) {
            return new WP_Error('mj_event_registration_missing_event', 'Evenement introuvable.');
        }

        $member = MjMembers_CRUD::getById($member_id);
        if (!$member) {
            return new WP_Error('mj_event_registration_missing_member', 'Membre introuvable.');
        }

        $validation = self::validate_registration($event, $member, $event_id, $member_id, $args);
        if (is_wp_error($validation)) {
            return $validation;
        }

        $guardian_id = isset($args['guardian_id']) ? (int) $args['guardian_id'] : 0;
        if ($guardian_id <= 0 && isset($member->guardian_id)) {
            $guardian_id = (int) $member->guardian_id;
        }
        if ($guardian_id <= 0) {
            $guardian_id = null;
        }

        $notes = isset($args['notes']) ? sanitize_textarea_field($args['notes']) : '';

        global $wpdb;
        $table = mj_member_get_event_registrations_table_name();

        $insert = array(
            'event_id' => $event_id,
            'member_id' => $member_id,
            'guardian_id' => $guardian_id,
            'statut' => self::STATUS_PENDING,
            'notes' => ($notes !== '') ? $notes : null,
            'created_at' => current_time('mysql'),
        );

        $result = $wpdb->insert($table, $insert, array('%d', '%d', '%d', '%s', '%s', '%s'));
        if ($result === false) {
            return new WP_Error('mj_event_registration_insert_failed', 'Impossible de creer cette inscription.');
        }

        $registration_id = (int) $wpdb->insert_id;
        $registration = self::get($registration_id);
        self::notify_registration_created($registration, $event, $member, $guardian_id);

        return $registration_id;
    }

    /**
     * @param int $registration_id
     * @param string $new_status
     * @return true|WP_Error
     */
    public static function update_status($registration_id, $new_status) {
        $registration_id = (int) $registration_id;
        if ($registration_id <= 0) {
            return new WP_Error('mj_event_registration_invalid_id', 'Inscription invalide.');
        }

        $new_status = sanitize_key($new_status);
        if (!array_key_exists($new_status, self::META_STATUS_LABELS)) {
            return new WP_Error('mj_event_registration_bad_status', 'Statut inconnu.');
        }

        global $wpdb;
        $table = mj_member_get_event_registrations_table_name();
        $updated = $wpdb->update($table, array('statut' => $new_status), array('id' => $registration_id), array('%s'), array('%d'));

        if ($updated === false) {
            return new WP_Error('mj_event_registration_update_failed', 'Impossible de mettre a jour l inscription.');
        }

        return true;
    }

    /**
     * @param int $registration_id
     * @return true|WP_Error
     */
    public static function delete($registration_id) {
        $registration_id = (int) $registration_id;
        if ($registration_id <= 0) {
            return new WP_Error('mj_event_registration_invalid_id', 'Inscription invalide.');
        }

        global $wpdb;
        $table = mj_member_get_event_registrations_table_name();
        $deleted = $wpdb->delete($table, array('id' => $registration_id), array('%d'));

        if ($deleted === false) {
            return new WP_Error('mj_event_registration_delete_failed', 'Suppression impossible.');
        }

        return true;
    }

    /**
     * @param object $event
     * @param object $member
     * @param int $event_id
     * @param int $member_id
     * @param array<string, mixed> $args
     * @return true|WP_Error
     */
    private static function validate_registration($event, $member, $event_id, $member_id, array $args) {
        $existing = self::get_existing($event_id, $member_id);
        if ($existing && $existing->statut !== self::STATUS_CANCELLED) {
            return new WP_Error('mj_event_registration_duplicate', 'Ce membre est deja inscrit a cet evenement.');
        }

        $now = current_time('timestamp');
        $event_start = strtotime($event->date_debut);
        if ($event_start && $now > $event_start) {
            return new WP_Error('mj_event_registration_event_started', 'L evenement a deja commence.');
        }

        $deadline = self::resolve_deadline($event);
        if ($deadline && $now > $deadline) {
            return new WP_Error('mj_event_registration_deadline', 'La date limite est depassee.');
        }

        $allow_guardian_registration = 0;
        if (isset($event->allow_guardian_registration)) {
            $allow_guardian_registration = (int) $event->allow_guardian_registration;
        }
        $member_role = isset($member->role) ? sanitize_key($member->role) : '';
        if ($allow_guardian_registration !== 1 && $member_role === MjMembers_CRUD::ROLE_TUTEUR) {
            return new WP_Error('mj_event_registration_guardian_blocked', 'Les tuteurs ne peuvent pas s\'inscrire a cet evenement.');
        }

        $age_validation = self::validate_age($event, $member);
        if (is_wp_error($age_validation)) {
            return $age_validation;
        }

        return true;
    }

    /**
     * @param object $event
     * @return int|null
     */
    private static function resolve_deadline($event) {
        if (!empty($event->date_fin_inscription) && $event->date_fin_inscription !== '0000-00-00 00:00:00') {
            $timestamp = strtotime($event->date_fin_inscription);
            return $timestamp ?: null;
        }

        if (!empty($event->date_debut)) {
            $start = strtotime($event->date_debut);
            if ($start) {
                return $start - (14 * DAY_IN_SECONDS);
            }
        }

        return null;
    }

    /**
     * @param object $event
     * @param object $member
     * @return true|WP_Error
     */
    private static function validate_age($event, $member) {
        $age_min = isset($event->age_min) ? (int) $event->age_min : 0;
        $age_max = isset($event->age_max) ? (int) $event->age_max : 0;

        if ($age_min <= 0 && $age_max <= 0) {
            return true;
        }

        if (empty($member->birth_date) || $member->birth_date === '0000-00-00') {
            return new WP_Error('mj_event_registration_missing_birth_date', 'La date de naissance du membre est inconnue.');
        }

        $reference = !empty($event->date_debut) ? strtotime($event->date_debut) : current_time('timestamp');
        if (!$reference) {
            $reference = current_time('timestamp');
        }

        $age = self::calculate_age($member->birth_date, $reference);
        if ($age_min > 0 && $age < $age_min) {
            return new WP_Error('mj_event_registration_age_min', 'Le membre est trop jeune pour cet evenement.');
        }
        if ($age_max > 0 && $age > $age_max) {
            return new WP_Error('mj_event_registration_age_max', 'Le membre est trop age pour cet evenement.');
        }

        return true;
    }

    /**
     * @param string $birth_date
     * @param int $reference
     * @return int
     */
    private static function calculate_age($birth_date, $reference) {
        $birth_timestamp = strtotime($birth_date);
        if (!$birth_timestamp) {
            return 0;
        }

        $birth = new DateTime('@' . $birth_timestamp);
        $reference_date = new DateTime('@' . $reference);
        $interval = $birth->diff($reference_date);

        return (int) $interval->y;
    }

    /**
     * @param object|null $registration
     * @param object $event
     * @param object $member
     * @param int|null $guardian_id
     * @return void
     */
    private static function notify_registration_created($registration, $event, $member, $guardian_id) {
        if (!$registration) {
            return;
        }

        $guardian = null;
        if ($guardian_id) {
            $guardian = MjMembers_CRUD::getById($guardian_id);
        }

        $animateur_members = array();
        if (class_exists('MjEventAnimateurs')) {
            $animateur_members = MjEventAnimateurs::get_members_by_event((int) $event->id);
        }
        if (empty($animateur_members) && !empty($event->animateur_id)) {
            $legacy_animateur = MjMembers_CRUD::getById((int) $event->animateur_id);
            if ($legacy_animateur) {
                $animateur_members[] = $legacy_animateur;
            }
        }

        $subject = 'Nouvelle inscription: ' . $event->title;
        $event_dates = self::format_event_dates($event);
        $member_first = isset($member->first_name) ? $member->first_name : '';
        $member_last = isset($member->last_name) ? $member->last_name : '';
        $member_name = trim($member_first . ' ' . $member_last);
        if ($member_name === '') {
            $member_name = 'Membre #' . $member->id;
        }

        $message_lines = array(
            'Bonjour,',
            '',
            'Une nouvelle inscription a ete enregistree.',
            'Evenement: ' . $event->title,
            'Participant: ' . $member_name,
        );

        if ($event_dates !== '') {
            $message_lines[] = 'Dates: ' . $event_dates;
        }

        $status_label = isset(self::META_STATUS_LABELS[$registration->statut]) ? self::META_STATUS_LABELS[$registration->statut] : $registration->statut;
        $message_lines[] = 'Statut: ' . $status_label;
        $message_lines[] = 'Enregistre le: ' . wp_date('d/m/Y H:i', strtotime($registration->created_at));

        if (!empty($registration->notes)) {
            $message_lines[] = '';
            $message_lines[] = 'Notes: ' . $registration->notes;
        }

        if (!empty($animateur_members)) {
            $message_lines[] = '';
            if (count($animateur_members) === 1) {
                $first_animateur = reset($animateur_members);
                if ($first_animateur) {
                    $message_lines[] = 'Animateur referent: ' . self::format_member_summary($first_animateur);
                }
            } else {
                $message_lines[] = 'Animateurs referents:';
                foreach ($animateur_members as $animateur_member) {
                    $message_lines[] = ' - ' . self::format_member_summary($animateur_member);
                }
            }
        }

        $message_lines[] = '';
        $message_lines[] = 'Bien a vous,';
        $message_lines[] = get_bloginfo('name');

        $body = implode("\n", $message_lines);
        $animateur_body = $body;
        if (!empty($event->id)) {
            $event_admin_url = add_query_arg(
                array(
                    'page' => 'mj_events',
                    'action' => 'edit',
                    'event' => (int) $event->id,
                ),
                admin_url('admin.php')
            );
            $animateur_body .= "\n\nGestion de l evenement: " . $event_admin_url;
        }
        $headers = array('Content-Type: text/plain; charset=UTF-8');
        $sent_addresses = array();

        $recipient = '';
        if ($guardian && !empty($guardian->email) && is_email($guardian->email)) {
            $recipient = $guardian->email;
        } elseif (!empty($member->email) && is_email($member->email)) {
            $recipient = $member->email;
        }

        if ($recipient !== '') {
            wp_mail($recipient, $subject, $body, $headers);
            $sent_addresses[strtolower($recipient)] = true;
        }

        $notify_email = get_option('mj_notify_email', '');
        if ($notify_email !== '' && is_email($notify_email)) {
            $normalized_notify = strtolower($notify_email);
            if (!isset($sent_addresses[$normalized_notify])) {
                wp_mail($notify_email, $subject, $body, $headers);
                $sent_addresses[$normalized_notify] = true;
            }
        }

        if (!empty($animateur_members)) {
            foreach ($animateur_members as $animateur_member) {
                if (empty($animateur_member->email) || !is_email($animateur_member->email)) {
                    continue;
                }
                $animateur_email_norm = strtolower($animateur_member->email);
                if (isset($sent_addresses[$animateur_email_norm])) {
                    continue;
                }
                wp_mail($animateur_member->email, $subject, $animateur_body, $headers);
                $sent_addresses[$animateur_email_norm] = true;
            }
        }
    }

    /**
     * @param object $event
     * @return string
     */
    private static function format_event_dates($event) {
        if (empty($event->date_debut) || empty($event->date_fin)) {
            return '';
        }

        $start = strtotime($event->date_debut);
        $end = strtotime($event->date_fin);
        if (!$start || !$end) {
            return '';
        }

        $start_str = wp_date('d/m/Y H:i', $start);
        $end_str = wp_date('d/m/Y H:i', $end);

        if (wp_date('d/m/Y', $start) === wp_date('d/m/Y', $end)) {
            return $start_str . ' - ' . wp_date('H:i', $end);
        }

        return $start_str . ' -> ' . $end_str;
    }

    private static function format_member_summary($member) {
        $first = isset($member->first_name) ? $member->first_name : '';
        $last = isset($member->last_name) ? $member->last_name : '';
        $name = trim($first . ' ' . $last);
        if ($name === '') {
            $name = 'Animateur #' . (isset($member->id) ? (int) $member->id : 0);
        }

        if (!empty($member->email) && is_email($member->email)) {
            $name .= ' (' . $member->email . ')';
        }

        return $name;
    }
}
