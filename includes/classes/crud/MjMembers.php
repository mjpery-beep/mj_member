<?php

namespace Mj\Member\Classes\Crud;

use Mj\Member\Classes\MjTools;
use Mj\Member\Classes\MjRoles;
use Mj\Member\Classes\Value\MemberData;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

if (class_exists(__NAMESPACE__ . '\\MjMembers')) {
    return;
}

class MjMembers extends MjTools implements CrudRepositoryInterface {

    const TABLE_NAME = 'mj_members';
    
    /**
     * @deprecated Utiliser MjRoles::JEUNE à la place
     */
    const ROLE_JEUNE = MjRoles::JEUNE;
    
    /**
     * @deprecated Utiliser MjRoles::ANIMATEUR à la place
     */
    const ROLE_ANIMATEUR = MjRoles::ANIMATEUR;
    
    /**
     * @deprecated Utiliser MjRoles::COORDINATEUR à la place
     */
    const ROLE_COORDINATEUR = MjRoles::COORDINATEUR;
    
    /**
     * @deprecated Utiliser MjRoles::BENEVOLE à la place
     */
    const ROLE_BENEVOLE = MjRoles::BENEVOLE;
    
    /**
     * @deprecated Utiliser MjRoles::TUTEUR à la place
     */
    const ROLE_TUTEUR = MjRoles::TUTEUR;
    
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';

    /** @var array<int,string>|null */
    private static $tableColumns = null;
    /** @var array<string,int> */
    private static $columnMaxLengths = array(
        'first_name' => 100,
        'last_name' => 100,
        'nickname' => 100,
        'email' => 150,
        'phone' => 30,
        'address' => 250,
        'city' => 120,
        'postal_code' => 20,
        'role' => 20,
        'status' => 50,
        'description_courte' => 255,
        'card_access_key' => 64,
        'school' => 150,
        'birth_country' => 120,
        'nationality' => 120,
    );

    /**
     * @return string
     */
    private static function table_name() {
        return self::getTableName(self::TABLE_NAME);
    }

    /**
     * @param array<string,mixed> $args
     * @return array<int,MemberData>
     */
    public static function get_all(array $args = array()) {
        $defaults = array(
            'limit' => 0,
            'offset' => 0,
            'orderby' => 'date_inscription',
            'order' => 'DESC',
            'search' => '',
            'filters' => array(),
        );

        $args = wp_parse_args($args, $defaults);

        return self::getAll(
            (int) $args['limit'],
            (int) $args['offset'],
            (string) $args['orderby'],
            (string) $args['order'],
            (string) $args['search'],
            is_array($args['filters']) ? $args['filters'] : array()
        );
    }

    /**
     * @param array<string,mixed> $args
     * @return int
     */
    public static function count(array $args = array()) {
        $defaults = array(
            'search' => '',
            'filters' => array(),
        );

        $args = wp_parse_args($args, $defaults);

        return self::countAll(
            (string) $args['search'],
            is_array($args['filters']) ? $args['filters'] : array()
        );
    }

    /**
     * @deprecated Utiliser MjRoles::getAllRoles() à la place
     */
    public static function getAllowedRoles() {
        return MjRoles::getAllRoles();
    }

    /**
     * @deprecated Utiliser MjRoles::getRoleLabels() à la place
     */
    public static function getRoleLabels() {
        return MjRoles::getRoleLabels();
    }

    /**
     * @param int $limit
     * @param int $offset
     * @param string $orderby
     * @param string $order
     * @param string $search
     * @param array<string,mixed> $filters
     * @return array<int,MemberData>
     */
    public static function getAll($limit = 0, $offset = 0, $orderby = 'date_inscription', $order = 'DESC', $search = '', $filters = array()) {
        $table_name = self::getTableName(self::TABLE_NAME);
        $wpdb = self::getWpdb();

        $allowed_orderby = array('last_name', 'first_name', 'role', 'status', 'date_inscription', 'date_last_payement', 'id');
        if (!in_array($orderby, $allowed_orderby, true)) {
            $orderby = 'date_inscription';
        }

        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

        if (!is_array($filters)) {
            $filters = array();
        }

        $builder = CrudQueryBuilder::for_table($table_name);
        self::apply_member_filters($builder, $search, $filters);

        list($sql, $params) = $builder->build_select('*', $orderby, $order, (int) $limit, (int) $offset);

        if (!empty($params)) {
            $sql = call_user_func_array(array($wpdb, 'prepare'), array_merge(array($sql), $params));
        }

        $results = $wpdb->get_results($sql);
        if (!is_array($results) || empty($results)) {
            return array();
        }

        return self::hydrate_members($results);
    }

    public static function countAll($search = '', $filters = array()) {
        $table_name = self::getTableName(self::TABLE_NAME);
        $wpdb = self::getWpdb();

        if (!is_array($filters)) {
            $filters = array();
        }

        $builder = CrudQueryBuilder::for_table($table_name);
        self::apply_member_filters($builder, $search, $filters);

        list($sql, $params) = $builder->build_count('*');

        if (!empty($params)) {
            $sql = call_user_func_array(array($wpdb, 'prepare'), array_merge(array($sql), $params));
        }

        return (int) $wpdb->get_var($sql);
    }

    /**
     * @param CrudQueryBuilder $builder
     * @param string $search
     * @param array<string,mixed> $filters
     * @return void
     */
    private static function apply_member_filters(CrudQueryBuilder $builder, $search, array $filters) {
        $builder->where_tokenized_search(
            array('first_name', 'last_name', 'nickname', 'email', 'phone', 'city', 'postal_code'),
            $search
        );

        if (!empty($filters['last_name'])) {
            $builder->where_like_any(array('last_name'), $filters['last_name']);
        }

        if (!empty($filters['first_name'])) {
            $builder->where_like_any(array('first_name'), $filters['first_name']);
        }

        if (!empty($filters['email'])) {
            $builder->where_like_any(array('email'), $filters['email']);
        }

        if (isset($filters['age_min'])) {
            $builder->where_raw(
                '(birth_date IS NOT NULL AND birth_date <> "0000-00-00" AND TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) >= %d)',
                array((int) $filters['age_min'])
            );
        }

        if (isset($filters['age_max'])) {
            $builder->where_raw(
                '(birth_date IS NOT NULL AND birth_date <> "0000-00-00" AND TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) <= %d)',
                array((int) $filters['age_max'])
            );
        }

        if (!empty($filters['roles']) && is_array($filters['roles'])) {
            $allowed = self::getAllowedRoles();
            $valid_roles = array();
            foreach ($filters['roles'] as $r) {
                $r = sanitize_key($r);
                if (in_array($r, $allowed, true)) {
                    $valid_roles[] = $r;
                }
            }
            if (!empty($valid_roles)) {
                $builder->where_in_strings('role', $valid_roles, 'sanitize_key');
            }
        } elseif (!empty($filters['role'])) {
            $role = sanitize_key($filters['role']);
            if (in_array($role, self::getAllowedRoles(), true)) {
                $builder->where_equals('role', $role, 'sanitize_key');
            }
        }

        if (array_key_exists('is_volunteer', $filters)) {
            $raw_flag = $filters['is_volunteer'];
            $parsed_flag = null;

            if ($raw_flag === true || $raw_flag === 1 || $raw_flag === '1' || $raw_flag === 'true' || $raw_flag === 'yes') {
                $parsed_flag = 1;
            } elseif ($raw_flag === false || $raw_flag === 0 || $raw_flag === '0' || $raw_flag === 'false' || $raw_flag === 'no') {
                $parsed_flag = 0;
            }

            if ($parsed_flag !== null) {
                $builder->where_equals_int('is_volunteer', $parsed_flag);
            }
        }

        if (!empty($filters['payment'])) {
            $payment_filter = sanitize_key($filters['payment']);
            if ($payment_filter === 'paid') {
                $builder->where_raw(
                    '(requires_payment = 1 AND date_last_payement IS NOT NULL AND date_last_payement <> %s AND CAST(date_last_payement AS CHAR) <> %s)',
                    array('0000-00-00 00:00:00', '')
                );
            } elseif ($payment_filter === 'due') {
                $current_year = (int) date('Y');
                $builder->where_raw(
                    '(requires_payment = 1 AND ((date_last_payement IS NULL OR date_last_payement = %s OR CAST(date_last_payement AS CHAR) = %s) OR YEAR(date_last_payement) < %d))',
                    array('0000-00-00 00:00:00', '', $current_year)
                );
            } elseif ($payment_filter === 'exempt') {
                $builder->where_raw('requires_payment = 0');
            }
        }

        if (!empty($filters['date_start'])) {
            $builder->where_compare('date_inscription', '>=', sanitize_text_field($filters['date_start']), '%s');
        }

        if (!empty($filters['date_end'])) {
            $builder->where_compare('date_inscription', '<=', sanitize_text_field($filters['date_end']), '%s');
        }
    }

    /**
     * @param int $id
     * @return MemberData|null
     */
    public static function getById($id) {
        $table_name = self::getTableName(self::TABLE_NAME);
        $row = self::getWpdb()->get_row(
            self::getWpdb()->prepare("SELECT * FROM $table_name WHERE id = %d", intval($id))
        );

        return $row ? MemberData::fromRow($row) : null;
    }

    /**
     * @param int $user_id
     * @return MemberData|null
     */
    public static function getByWpUserId($user_id) {
        $table_name = self::getTableName(self::TABLE_NAME);
        $user_id = intval($user_id);
        if ($user_id <= 0) {
            return null;
        }

        $row = self::getWpdb()->get_row(
            self::getWpdb()->prepare("SELECT * FROM $table_name WHERE wp_user_id = %d LIMIT 1", $user_id)
        );

        return $row ? MemberData::fromRow($row) : null;
    }

    /**
     * @param string $email
     * @return MemberData|null
     */
    public static function getByEmail($email) {
        $table_name = self::getTableName(self::TABLE_NAME);
        $email = self::sanitizeOptionalEmail($email);
        if ($email === null) {
            return null;
        }

        $row = self::getWpdb()->get_row(
            self::getWpdb()->prepare("SELECT * FROM $table_name WHERE email = %s LIMIT 1", $email)
        );

        return $row ? MemberData::fromRow($row) : null;
    }

    public static function create($data) {
        if (!is_array($data)) {
            return new WP_Error('mj_member_invalid_payload', 'Format de donnees invalide pour le membre.');
        }

        $table_name = self::table_name();

        $raw_role = $data['role'] ?? self::ROLE_JEUNE;
        $role = self::normalizeRole($raw_role);
        $requires_payment = self::normalizePaymentFlag($role, $data['requires_payment'] ?? null);
        $is_volunteer = self::normalizeVolunteerFlag($data['is_volunteer'] ?? null, $raw_role);
        $status = self::normalizeStatus($data['status'] ?? self::STATUS_ACTIVE);
        $guardian_id = self::resolveGuardianId($data['guardian_id'] ?? null, $role, null);
        $is_autonomous = ($role === self::ROLE_JEUNE) ? (empty($guardian_id) ? 1 : (!empty($data['is_autonomous']) ? 1 : 0)) : 1;

        $first_name = sanitize_text_field($data['first_name'] ?? '');
        $last_name = sanitize_text_field($data['last_name'] ?? '');
        $email = self::sanitizeOptionalEmail($data['email'] ?? '');

        if ($first_name === '' || $last_name === '') {
            return new WP_Error('mj_member_missing_name', 'Le prenom et le nom sont obligatoires.');
        }

        if ($email !== null && !is_email($email)) {
            return new WP_Error('mj_member_invalid_email', 'Adresse e-mail invalide.');
        }

        if ($role !== self::ROLE_JEUNE && $role !== self::ROLE_TUTEUR && empty($email)) {
            return new WP_Error('mj_member_email_required', 'Une adresse e-mail est requise pour ce role.');
        }

        $insert = array(
            'first_name' => $first_name,
            'nickname' => self::sanitizeOptionalText($data['nickname'] ?? ''),
            'last_name' => $last_name,
            'email' => ($email !== null ? $email : ''),
            'phone' => self::sanitizeOptionalText($data['phone'] ?? ''),
            'birth_date' => self::sanitizeDate($data['birth_date'] ?? ''),
            'role' => $role,
            'guardian_id' => $guardian_id,
            'is_autonomous' => $is_autonomous,
            'requires_payment' => $requires_payment,
            'address' => self::sanitizeOptionalText($data['address'] ?? ''),
            'city' => self::sanitizeOptionalText($data['city'] ?? ''),
            'postal_code' => self::sanitizeOptionalText($data['postal_code'] ?? ''),
            'school' => self::sanitizeOptionalText($data['school'] ?? ''),
            'birth_country' => self::sanitizeOptionalText($data['birth_country'] ?? ''),
            'nationality' => self::sanitizeOptionalText($data['nationality'] ?? ''),
            'notes' => self::sanitizeNotes($data['notes'] ?? ''),
            'description_courte' => self::sanitizeOptionalText($data['description_courte'] ?? ''),
            'description_longue' => self::sanitizeNotes($data['description_longue'] ?? ''),
            'why_mj' => self::sanitizeNotes($data['why_mj'] ?? ''),
            'how_mj' => self::sanitizeNotes($data['how_mj'] ?? ''),
            'status' => $status,
            'date_last_payement' => self::sanitizeDateTime($data['date_last_payement'] ?? ''),
            'photo_id' => isset($data['photo_id']) ? intval($data['photo_id']) : null,
            'photo_usage_consent' => !empty($data['photo_usage_consent']) ? 1 : 0,
            'newsletter_opt_in' => array_key_exists('newsletter_opt_in', $data) ? (!empty($data['newsletter_opt_in']) ? 1 : 0) : 1,
            'sms_opt_in' => array_key_exists('sms_opt_in', $data) ? (!empty($data['sms_opt_in']) ? 1 : 0) : 1,
            'whatsapp_opt_in' => array_key_exists('whatsapp_opt_in', $data) ? (!empty($data['whatsapp_opt_in']) ? 1 : 0) : 1,
            'wp_user_id' => self::sanitizeUserId($data['wp_user_id'] ?? null),
            'is_volunteer' => $is_volunteer,
        );

        $insert['notification_preferences'] = self::sanitizeNotificationPreferences($data['notification_preferences'] ?? null, $insert);
        $insert['card_access_key'] = self::generateCardAccessKey();
        $insert['anonymized_at'] = null;

        $custom_inscription = self::sanitizeDateTime($data['date_inscription'] ?? '');
        if ($custom_inscription !== null) {
            $insert['date_inscription'] = $custom_inscription;
        }

        $insert = self::enforceColumnLengths($insert);
        $insert = self::filterAvailableColumns($insert);
        $insert = self::stripNulls($insert);

        $result = self::getWpdb()->insert($table_name, $insert);
        if ($result === false) {
            return new WP_Error('mj_member_insert_failed', 'Impossible de creer ce membre.');
        }

        $member_id = (int) self::getWpdb()->insert_id;
        if ($member_id <= 0) {
            return new WP_Error('mj_member_insert_failed', 'Impossible de creer ce membre.');
        }

        // Récupérer l'objet membre créé pour le passer au hook
        $created_member = self::getById($member_id);
        
        error_log('🚀 CRÉATION MEMBRE - ID: ' . $member_id . ' - Déclenchement du hook mj_member_quick_member_created');
        
        // Déclencher le hook pour notifier la création du nouveau membre
        do_action('mj_member_quick_member_created', $member_id, $created_member, [
            'source' => 'mj_members_create',
        ]);

        return $member_id;
    }

    /**
     * Supprime les données personnelles d’un membre en respectant la politique de rétention.
     *
     * @param int $member_id
     * @return true|WP_Error
     */
    public static function anonymizePersonalData($member_id)
    {
        $member_id = (int) $member_id;
        if ($member_id <= 0) {
            return new WP_Error('mj_member_invalid_id', 'Identifiant de membre invalide pour anonymisation.');
        }

        $member = self::getById($member_id);
        if (!$member) {
            return new WP_Error('mj_member_missing', 'Membre introuvable.');
        }

        $placeholder_prefix = 'anonymized-' . $member_id;
        $now = function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s');

        $updates = array(
            'first_name' => 'Anonymized',
            'last_name' => $placeholder_prefix,
            'email' => $placeholder_prefix . '@example.com',
            'phone' => null,
            'address' => null,
            'city' => null,
            'postal_code' => null,
            'notes' => null,
            'description_courte' => null,
            'description_longue' => null,
            'guardian_id' => null,
            'wp_user_id' => null,
            'photo_id' => null,
            'notification_preferences' => null,
            'anonymized_at' => $now,
            'status' => self::STATUS_INACTIVE,
        );

        $updates = self::enforceColumnLengths($updates);
        $updates = self::filterAvailableColumns($updates);

        $result = self::getWpdb()->update(
            self::table_name(),
            $updates,
            array('id' => $member_id),
            null,
            array('%d')
        );

        if ($result === false) {
            return new WP_Error('mj_member_anonymize_failed', 'Impossible d’anonymiser ce membre.');
        }

        return true;
    }

    public static function update($id, $data) {
        $id = intval($id);
        if ($id <= 0) {
            return new WP_Error('mj_member_invalid_id', 'Identifiant de membre invalide.');
        }

        if (!is_array($data)) {
            return new WP_Error('mj_member_invalid_payload', 'Format de donnees invalide pour le membre.');
        }

        $table_name = self::table_name();
        $wpdb = self::getWpdb();

        $current = self::getById($id);
        if (!$current) {
            return new WP_Error('mj_member_missing', 'Membre introuvable.');
        }

        $updates = array();

        $allowed_fields = array(
            'first_name','last_name','nickname','email','phone','birth_date','role','guardian_id','is_autonomous','is_volunteer', 'is_trusted_member','requires_payment','address','city','postal_code','school','birth_country','nationality','notes','description_courte','description_longue','why_mj','how_mj','work_schedule','leave_quota_paid','leave_quota_unpaid','leave_quota_exceptional','leave_quota_recovery','status','date_inscription','date_last_payement','photo_id','photo_usage_consent','newsletter_opt_in','sms_opt_in','whatsapp_opt_in','notification_preferences','wp_user_id','card_access_key','anonymized_at'
        );

        foreach ($data as $field => $value) {
            if (!in_array($field, $allowed_fields, true)) {
                continue;
            }

            switch ($field) {
                case 'email':
                    $updates[$field] = self::sanitizeOptionalEmail($value, false);
                    break;
                case 'first_name':
                case 'last_name':
                    $updates[$field] = sanitize_text_field($value);
                    break;
                case 'nickname':
                case 'school':
                case 'birth_country':
                case 'nationality':
                    $updates[$field] = self::sanitizeOptionalText($value);
                    break;
                case 'phone':
                case 'address':
                case 'city':
                case 'postal_code':
                    $updates[$field] = self::sanitizeOptionalText($value);
                    break;
                case 'notes':
                case 'description_longue':
                case 'why_mj':
                case 'how_mj':
                    $updates[$field] = self::sanitizeNotes($value);
                    break;
                case 'description_courte':
                    $updates[$field] = self::sanitizeOptionalText($value);
                    break;
                case 'date_inscription':
                case 'date_last_payement':
                    $updates[$field] = self::sanitizeDateTime($value);
                    break;
                case 'birth_date':
                    $updates[$field] = self::sanitizeDate($value);
                    break;
                case 'role':
                    $updates[$field] = self::normalizeRole($value);
                    break;
                case 'requires_payment':
                    $updates[$field] = self::normalizePaymentFlag($data['role'] ?? $current->role, $value);
                    break;
                case 'status':
                    $updates[$field] = self::normalizeStatus($value);
                    break;
                case 'guardian_id':
                    $updates[$field] = intval($value);
                    break;
                case 'is_volunteer':
                    $updates[$field] = self::normalizeVolunteerFlag($value, $data['role'] ?? $current->role);
                    break;
                case 'is_trusted_member':
                case 'is_autonomous':
                case 'photo_usage_consent':
                case 'newsletter_opt_in':
                case 'sms_opt_in':
                case 'whatsapp_opt_in':
                    $updates[$field] = !empty($value) ? 1 : 0;
                    break;
                case 'notification_preferences':
                    $updates[$field] = self::sanitizeNotificationPreferences($value, $current);
                    break;
                case 'photo_id':
                    $updates[$field] = intval($value);
                    break;
                case 'wp_user_id':
                    $updates[$field] = self::sanitizeUserId($value);
                    break;
                case 'card_access_key':
                    $sanitized = self::sanitizeCardAccessKey($value);
                    $updates[$field] = $sanitized !== '' ? $sanitized : null;
                    break;
                case 'anonymized_at':
                    $updates[$field] = ($value === null || $value === '') ? null : self::sanitizeDateTime($value);
                    break;
                case 'work_schedule':
                    $updates[$field] = self::sanitizeWorkSchedule($value);
                    break;
                case 'leave_quota_paid':
                case 'leave_quota_unpaid':
                case 'leave_quota_exceptional':
                case 'leave_quota_recovery':
                    $updates[$field] = max(0, (int) $value);
                    break;
                default:
                    $updates[$field] = $value;
            }
        }

        if (!array_key_exists('is_volunteer', $updates) && array_key_exists('role', $data) && self::isLegacyVolunteerRole($data['role'])) {
            $updates['is_volunteer'] = 1;
        }

        $updates = self::enforceColumnLengths($updates);
        $updates = self::filterAvailableColumns($updates);

        if (empty($updates)) {
            return true;
        }

        $effective_role = $updates['role'] ?? $current->role;

        if (array_key_exists('email', $updates) && $updates['email'] !== null && $updates['email'] !== '' && !is_email($updates['email'])) {
            return new WP_Error('mj_member_invalid_email', 'Adresse e-mail invalide.');
        }

        if ($effective_role !== self::ROLE_JEUNE && $effective_role !== self::ROLE_TUTEUR && array_key_exists('email', $updates) && empty($updates['email'])) {
            return new WP_Error('mj_member_email_required', 'Une adresse e-mail est requise pour ce role.');
        }

        if (array_key_exists('guardian_id', $updates)) {
            $updates['guardian_id'] = self::resolveGuardianId($updates['guardian_id'], $effective_role, $id);
        }

        if (isset($updates['role']) && !isset($updates['requires_payment'])) {
            $updates['requires_payment'] = self::normalizePaymentFlag($updates['role'], null);
        }

        if ($effective_role !== self::ROLE_JEUNE) {
            $updates['guardian_id'] = null;
            $updates['is_autonomous'] = 1;
        } else {
            if (!array_key_exists('is_autonomous', $updates)) {
                $updates['is_autonomous'] = empty($updates['guardian_id']) ? 1 : (isset($data['is_autonomous']) ? (int) !empty($data['is_autonomous']) : (int) $current->is_autonomous);
            }
        }

        $result = $wpdb->update($table_name, $updates, array('id' => $id));
        if ($result === false) {
            return new WP_Error('mj_member_update_failed', 'Impossible de mettre a jour ce membre.');
        }

        return true;
    }

    public static function delete($id) {
        $id = intval($id);
        if ($id <= 0) {
            return new WP_Error('mj_member_invalid_id', 'Identifiant de membre invalide.');
        }

        $table_name = self::table_name();
        $wpdb = self::getWpdb();

        $wpdb->query($wpdb->prepare("UPDATE $table_name SET guardian_id = NULL WHERE guardian_id = %d", $id));

        $deleted = $wpdb->delete($table_name, array('id' => $id), array('%d'));
        if ($deleted === false) {
            return new WP_Error('mj_member_delete_failed', 'Suppression du membre impossible.');
        }

        if ((int) $deleted === 0) {
            return new WP_Error('mj_member_missing', 'Membre introuvable.');
        }

        return true;
    }

    /**
     * @param string $query
     * @return array<int,MemberData>
     */
    public static function search($query) {
        $table_name = self::getTableName(self::TABLE_NAME);
        $wpdb = self::getWpdb();

        $query = sanitize_text_field($query);
        if ($query === '') {
            return array();
        }

        $like = '%' . $wpdb->esc_like($query) . '%';
        $sql = $wpdb->prepare(
            "SELECT * FROM $table_name WHERE first_name LIKE %s OR last_name LIKE %s OR email LIKE %s OR phone LIKE %s ORDER BY date_inscription DESC",
            $like,
            $like,
            $like,
            $like
        );

        $rows = $wpdb->get_results($sql);
        if (!is_array($rows) || empty($rows)) {
            return array();
        }

        return self::hydrate_members($rows);
    }
    
    /**
     * @param string $status
     * @return array<int,MemberData>
     */
    public static function getByStatus($status) {
        $table_name = self::getTableName(self::TABLE_NAME);
        $status = self::normalizeStatus($status);

        $rows = self::getWpdb()->get_results(
            self::getWpdb()->prepare("SELECT * FROM $table_name WHERE status = %s ORDER BY date_inscription DESC", $status)
        );

        if (!is_array($rows) || empty($rows)) {
            return array();
        }

        return self::hydrate_members($rows);
    }

    /**
     * @param string $role
     * @return array<int,MemberData>
     */
    public static function getByRole($role) {
        $role = self::normalizeRole($role);
        $table_name = self::getTableName(self::TABLE_NAME);

        $rows = self::getWpdb()->get_results(
            self::getWpdb()->prepare("SELECT * FROM $table_name WHERE role = %s ORDER BY last_name ASC, first_name ASC", $role)
        );

        if (!is_array($rows) || empty($rows)) {
            return array();
        }

        return self::hydrate_members($rows);
    }

    /**
     * @param string $search
     * @return array<int,MemberData>
     */
    public static function getGuardians($search = '') {
        $table_name = self::getTableName(self::TABLE_NAME);
        $wpdb = self::getWpdb();

        $search = sanitize_text_field($search);
        if ($search === '') {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT id, first_name, last_name, email FROM $table_name WHERE role = %s ORDER BY last_name ASC, first_name ASC",
                self::ROLE_TUTEUR
            ));
            return self::hydrate_members(is_array($rows) ? $rows : array());
        }

        $like = '%' . $wpdb->esc_like($search) . '%';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, first_name, last_name, email FROM $table_name WHERE role = %s AND (first_name LIKE %s OR last_name LIKE %s OR email LIKE %s) ORDER BY last_name ASC, first_name ASC",
            self::ROLE_TUTEUR,
            $like,
            $like,
            $like
        ));

        return self::hydrate_members(is_array($rows) ? $rows : array());
    }

    /**
     * @param int $guardian_id
     * @return array<int,MemberData>
     */
    public static function getChildrenForGuardian($guardian_id) {
        $table_name = self::getTableName(self::TABLE_NAME);
        $guardian_id = intval($guardian_id);
        if ($guardian_id <= 0) {
            return array();
        }

        $rows = self::getWpdb()->get_results(
            self::getWpdb()->prepare("SELECT * FROM $table_name WHERE guardian_id = %d ORDER BY last_name ASC, first_name ASC", $guardian_id)
        );

        if (!is_array($rows) || empty($rows)) {
            return array();
        }

        return self::hydrate_members($rows);
    }

    /**
     * @param array<int,object|array<string,mixed>|MemberData> $rows
     * @return array<int,MemberData>
     */
    private static function hydrate_members(array $rows) {
        $hydrated = array();

        foreach ($rows as $row) {
            $hydrated[] = MemberData::fromRow($row);
        }

        return $hydrated;
    }

    private static function normalizeRole($role) {
        $role = strtolower(sanitize_text_field($role));
        if (self::isLegacyVolunteerRole($role)) {
            return self::ROLE_JEUNE;
        }
        return in_array($role, self::getAllowedRoles(), true) ? $role : self::ROLE_JEUNE;
    }

    private static function normalizeVolunteerFlag($value, $rawRole = null) {
        if ($rawRole !== null && self::isLegacyVolunteerRole($rawRole)) {
            return 1;
        }

        if ($value === null || $value === '') {
            return 0;
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        if (is_int($value)) {
            return $value === 1 ? 1 : 0;
        }

        if (is_numeric($value)) {
            return ((int) $value) === 1 ? 1 : 0;
        }

        if (is_string($value)) {
            $candidate = strtolower(trim($value));
            if ($candidate === '') {
                return 0;
            }

            $truthy = array('1', 'true', 'yes', 'on', 'oui', 'vrai');
            $falsy = array('0', 'false', 'no', 'off', 'non', 'faux');

            if (in_array($candidate, $truthy, true)) {
                return 1;
            }

            if (in_array($candidate, $falsy, true)) {
                return 0;
            }
        }

        return !empty($value) ? 1 : 0;
    }

    private static function isLegacyVolunteerRole($role) {
        $normalized = strtolower(sanitize_text_field($role));
        return $normalized === self::ROLE_BENEVOLE || $normalized === 'benevoles';
    }

    private static function normalizeStatus($status) {
        $status = strtolower(sanitize_text_field($status));
        return in_array($status, array(self::STATUS_ACTIVE, self::STATUS_INACTIVE), true) ? $status : self::STATUS_ACTIVE;
    }

    private static function normalizePaymentFlag($role, $value) {
        if ($value === null || $value === '') {
            return ($role === self::ROLE_JEUNE) ? 1 : 0;
        }
        return (int) !empty($value);
    }

    private static function sanitizeOptionalEmail($value, $allow_null = true) {
        if ($value === null || $value === '') {
            return $allow_null ? null : '';
        }

        $email = sanitize_email($value);
        if ($email === '') {
            return $allow_null ? null : '';
        }

        return $email;
    }

    private static function sanitizeOptionalText($value) {
        if ($value === null) {
            return null;
        }
        $clean = sanitize_text_field($value);
        return $clean === '' ? null : $clean;
    }

    private static function sanitizeNotes($value) {
        if ($value === null || $value === '') {
            return null;
        }
        return wp_kses_post($value);
    }

    private static function sanitizeDate($value) {
        if (empty($value)) {
            return null;
        }
        $timestamp = strtotime($value);
        return $timestamp ? gmdate('Y-m-d', $timestamp) : null;
    }

    private static function sanitizeDateTime($value) {
        if (empty($value)) {
            return null;
        }
        $timestamp = strtotime($value);
        return $timestamp ? gmdate('Y-m-d H:i:s', $timestamp) : null;
    }

    private static function sanitizeUserId($value) {
        if ($value === null || $value === '') {
            return null;
        }

        $user_id = intval($value);
        return $user_id > 0 ? $user_id : null;
    }

    /**
     * Sanitize et valide les données d'emploi du temps contractuel.
     * Format attendu: JSON array avec objets {day, start, end, break_minutes}
     * @param mixed $value
     * @return string|null JSON string ou null
     */
    private static function sanitizeWorkSchedule($value) {
        if (empty($value)) {
            return null;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return null;
            }
            $value = $decoded;
        }

        if (!is_array($value)) {
            return null;
        }

        $valid_days = array('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday');
        $sanitized = array();

        foreach ($value as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $day = isset($entry['day']) ? strtolower(trim($entry['day'])) : '';
            if (!in_array($day, $valid_days, true)) {
                continue;
            }

            $start = isset($entry['start']) ? sanitize_text_field($entry['start']) : '';
            $end = isset($entry['end']) ? sanitize_text_field($entry['end']) : '';
            $break_minutes = isset($entry['break_minutes']) ? absint($entry['break_minutes']) : 0;

            // Validation format HH:MM
            if (!preg_match('/^\d{1,2}:\d{2}$/', $start) || !preg_match('/^\d{1,2}:\d{2}$/', $end)) {
                continue;
            }

            $sanitized[] = array(
                'day' => $day,
                'start' => $start,
                'end' => $end,
                'break_minutes' => $break_minutes,
            );
        }

        return !empty($sanitized) ? wp_json_encode($sanitized) : null;
    }

    private static function getTableColumns() {
        if (self::$tableColumns !== null) {
            return self::$tableColumns;
        }

        $table = self::getTableName(self::TABLE_NAME);
        $table_sql = esc_sql($table);
        $results = self::getWpdb()->get_results("SHOW COLUMNS FROM `$table_sql`", ARRAY_A);

        if (empty($results)) {
            self::$tableColumns = array();
            return self::$tableColumns;
        }

        $columns = array();
        foreach ($results as $row) {
            if (isset($row['Field'])) {
                $columns[] = strtolower((string) $row['Field']);
            }
        }

        self::$tableColumns = $columns;
        return self::$tableColumns;
    }

    private static function filterAvailableColumns(array $data) {
        $columns = self::getTableColumns();
        if (empty($columns)) {
            return $data;
        }

        foreach (array_keys($data) as $key) {
            if (!in_array(strtolower($key), $columns, true)) {
                unset($data[$key]);
            }
        }

        return $data;
    }

    public static function resetColumnCache() {
        self::$tableColumns = null;
    }

    public static function hasColumn($column) {
        if ($column === '' || $column === null) {
            return false;
        }

        $columns = self::getTableColumns();
        if (empty($columns)) {
            return false;
        }

        return in_array(strtolower($column), $columns, true);
    }

    private static function enforceColumnLengths(array $data) {
        foreach (self::$columnMaxLengths as $column => $length) {
            if (!isset($data[$column])) {
                continue;
            }

            $value = $data[$column];
            if (!is_string($value)) {
                continue;
            }

            $len_fn = function_exists('mb_strlen') ? 'mb_strlen' : 'strlen';
            $substr_fn = function_exists('mb_substr') ? 'mb_substr' : 'substr';

            if ($len_fn($value) > $length) {
                $data[$column] = $substr_fn($value, 0, $length);
            }
        }

        return $data;
    }

    private static function resolveGuardianId($guardian_id, $role, $self_id) {
        if ($role !== self::ROLE_JEUNE) {
            return null;
        }

        $guardian_id = intval($guardian_id);
        if ($guardian_id <= 0) {
            return null;
        }

        if (!empty($self_id) && intval($self_id) === $guardian_id) {
            return null;
        }

        $guardian = self::getById($guardian_id);
        if (!$guardian || $guardian->role !== self::ROLE_TUTEUR) {
            return null;
        }

        return $guardian_id;
    }

    /**
     * Create or update a guardian (tuteur) record and return its ID.
     *
     * @param array $data Basic guardian information (first_name, last_name, email, phone, address, city, postal_code, status).
     * @param int   $current_guardian_id Existing guardian id linked to the member (optional).
    * @return int|WP_Error|null Guardian id, erreur ou null si non resolu.
     */
    public static function upsertGuardian(array $data, $current_guardian_id = 0) {
        $email = sanitize_email($data['email'] ?? '');
        $first_name = sanitize_text_field($data['first_name'] ?? '');
        $last_name = sanitize_text_field($data['last_name'] ?? '');

        if ($first_name === '' || $last_name === '') {
            return null;
        }

        $payload = array(
            'first_name' => $first_name,
            'last_name' => $last_name,
            'phone' => self::sanitizeOptionalText($data['phone'] ?? ''),
            'address' => self::sanitizeOptionalText($data['address'] ?? ''),
            'city' => self::sanitizeOptionalText($data['city'] ?? ''),
            'postal_code' => self::sanitizeOptionalText($data['postal_code'] ?? ''),
            'status' => self::normalizeStatus($data['status'] ?? self::STATUS_ACTIVE),
            'role' => self::ROLE_TUTEUR,
            'requires_payment' => 0,
            'is_autonomous' => 1,
        );

        if ($email !== '') {
            $payload['email'] = $email;
        }

        $payload = self::stripNulls($payload);

        $table = self::getTableName(self::TABLE_NAME);
        $wpdb = self::getWpdb();

        $target_id = intval($current_guardian_id);
        if ($target_id > 0) {
            $existing = self::getById($target_id);
            if ($existing && $existing->role === self::ROLE_TUTEUR) {
                $update_payload = $payload;
                if ($email === '') {
                    unset($update_payload['email']);
                }

                $update_result = self::update($target_id, $update_payload);
                if (is_wp_error($update_result)) {
                    return $update_result;
                }

                return $target_id;
            }
        }

        $existing_guardian = null;
        if ($email !== '') {
            $candidate = self::getByEmail($email);
            if ($candidate && $candidate->role === self::ROLE_TUTEUR) {
                $existing_guardian = $candidate;
            }
        }

        if (!$existing_guardian) {
            $candidate = self::findGuardianByName($first_name, $last_name);
            if ($candidate && $candidate->role === self::ROLE_TUTEUR) {
                $existing_guardian = $candidate;
            }
        }

        if ($existing_guardian) {
            $update_payload = $payload;
            if ($email === '') {
                unset($update_payload['email']);
            }

            $update_result = self::update((int) $existing_guardian->id, $update_payload);
            if (is_wp_error($update_result)) {
                return $update_result;
            }

            return (int) $existing_guardian->id;
        }

        if ($email === '') {
            $payload['email'] = '';
        }

        $payload['role'] = self::ROLE_TUTEUR;
        $payload['requires_payment'] = 0;
        $payload['is_autonomous'] = 1;

        $created = self::create($payload);
        if (is_wp_error($created)) {
            return $created;
        }

        return $created;
    }

    private static function findGuardianByName($first_name, $last_name) {
        $table = self::getTableName(self::TABLE_NAME);
        $wpdb = self::getWpdb();

        $query = $wpdb->prepare(
            "SELECT * FROM $table WHERE role = %s AND first_name = %s AND last_name = %s LIMIT 1",
            self::ROLE_TUTEUR,
            $first_name,
            $last_name
        );

        if ($query === false) {
            return null;
        }

        $row = $wpdb->get_row($query);

        return $row ? MemberData::fromRow($row) : null;
    }

    public static function getNotificationPreferences($member_id) {
        $member_id = intval($member_id);
        if ($member_id <= 0) {
            return self::getNotificationPreferenceDefaults();
        }

        $member = self::getById($member_id);
        if (!$member) {
            return self::getNotificationPreferenceDefaults();
        }

        $stored = isset($member->notification_preferences) ? $member->notification_preferences : null;
        return self::parseNotificationPreferences($stored, $member);
    }

    public static function updateNotificationPreferences($member_id, $preferences) {
        $member_id = intval($member_id);
        if ($member_id <= 0) {
            return false;
        }

        $member = self::getById($member_id);
        if (!$member) {
            return false;
        }

        $encoded = self::sanitizeNotificationPreferences($preferences, $member);
        if (!is_string($encoded) || $encoded === '') {
            $encoded = '{}';
        }

        $parsed = self::parseNotificationPreferences($encoded, $member);
        $newsletter_opt_in = !empty($parsed['email_event_news']) ? 1 : 0;
        $sms_opt_in = self::hasEnabledPreferenceWithPrefix($parsed, 'sms_') ? 1 : 0;

        $table = self::getTableName(self::TABLE_NAME);
        $result = self::getWpdb()->update(
            $table,
            array(
                'notification_preferences' => $encoded,
                'newsletter_opt_in' => $newsletter_opt_in,
                'sms_opt_in' => $sms_opt_in,
            ),
            array('id' => $member_id),
            array('%s', '%d', '%d'),
            array('%d')
        );

        if ($result === false) {
            return false;
        }

        return $parsed;
    }

    public static function getNotificationPreferenceDefaults($member = null) {
        $newsletter_opt_in = true;
        $sms_opt_in = true;

        if (is_object($member) || is_array($member)) {
            if (self::hasField($member, 'newsletter_opt_in')) {
                $newsletter_opt_in = ((int) self::getField($member, 'newsletter_opt_in', 0)) === 1;
            }
            if (self::hasField($member, 'sms_opt_in')) {
                $sms_opt_in = ((int) self::getField($member, 'sms_opt_in', 0)) === 1;
            }
        }

        return array(
            'email_event_registration' => true,
            'email_payment_receipts' => true,
            'email_membership_reminders' => true,
            'email_event_reminders' => true,
            'email_event_news' => $newsletter_opt_in,
            'sms_event_registration' => $sms_opt_in,
            'sms_payment_receipts' => $sms_opt_in,
            'sms_membership_reminders' => $sms_opt_in,
            'sms_event_reminders' => $sms_opt_in,
            'sms_event_news' => false,
        );
    }

    private static function sanitizeNotificationPreferences($value, $member = null) {
        $parsed = self::parseNotificationPreferences($value, $member);
        $encoded = wp_json_encode($parsed);
        if (!is_string($encoded) || $encoded === '') {
            $encoded = json_encode($parsed);
        }

        return is_string($encoded) && $encoded !== '' ? $encoded : '{}';
    }

    private static function parseNotificationPreferences($value, $member = null) {
        $defaults = self::getNotificationPreferenceDefaults($member);
        $parsed = $defaults;

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $value = $decoded;
            } else {
                $value = array();
            }
        }

        if (is_array($value)) {
            foreach ($defaults as $key => $default) {
                if (array_key_exists($key, $value)) {
                    $parsed[$key] = self::normalizePreferenceFlag($value[$key]);
                }
            }
        }

        return $parsed;
    }

    private static function normalizePreferenceFlag($value) {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (is_numeric($value)) {
            return ((int) $value) === 1;
        }

        if (is_string($value)) {
            $candidate = strtolower(trim($value));
            return in_array($candidate, array('1', 'true', 'yes', 'on'), true);
        }

        return !empty($value);
    }

    private static function hasEnabledPreferenceWithPrefix(array $preferences, $prefix) {
        foreach ($preferences as $key => $flag) {
            if (strpos((string) $key, (string) $prefix) === 0 && !empty($flag)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param mixed $member
     */
    public static function hasField($member, $field) {
        if ($member instanceof MemberData) {
            return $member->has($field);
        }

        if (is_array($member)) {
            return array_key_exists($field, $member);
        }

        if (is_object($member)) {
            return property_exists($member, $field);
        }

        return false;
    }

    /**
     * @param mixed $member
     * @param mixed $default
     * @return mixed
     */
    public static function getField($member, $field, $default = null) {
        if ($member instanceof MemberData) {
            if ($member->has($field)) {
                return $member->get($field);
            }

            return $default;
        }

        if (is_array($member)) {
            return array_key_exists($field, $member) ? $member[$field] : $default;
        }

        if (is_object($member)) {
            if (isset($member->{$field})) {
                return $member->{$field};
            }

            if (property_exists($member, $field)) {
                return $member->{$field};
            }
        }

        return $default;
    }

    private static function stripNulls(array $data) {
        foreach ($data as $key => $value) {
            if ($value === null) {
                unset($data[$key]);
            }
        }
        return $data;
    }

    public static function ensureCardAccessKey($member_id) {
        $member_id = intval($member_id);
        if ($member_id <= 0) {
            return '';
        }

        $member = self::getById($member_id);
        if (!$member) {
            return '';
        }

        $current = self::sanitizeCardAccessKey(self::getField($member, 'card_access_key', ''));
        if ($current !== '') {
            return $current;
        }

        $key = self::generateCardAccessKey();
        if ($key === '') {
            return '';
        }

        $table = self::getTableName(self::TABLE_NAME);
        self::getWpdb()->update($table, array('card_access_key' => $key), array('id' => $member_id), array('%s'), array('%d'));

        return $key;
    }

    public static function getByCardAccessKey($key) {
        $key = self::sanitizeCardAccessKey($key);
        if ($key === '') {
            return null;
        }

        $table = self::getTableName(self::TABLE_NAME);
        $row = self::getWpdb()->get_row(
            self::getWpdb()->prepare("SELECT * FROM $table WHERE card_access_key = %s LIMIT 1", $key)
        );

        return $row ? MemberData::fromRow($row) : null;
    }

    private static function sanitizeCardAccessKey($value) {
        if (!is_string($value)) {
            if (is_object($value) && method_exists($value, '__toString')) {
                $value = (string) $value;
            } elseif (is_scalar($value)) {
                $value = (string) $value;
            } else {
                return '';
            }
        }

        $value = strtolower(trim($value));
        if ($value === '') {
            return '';
        }

        $value = preg_replace('/[^a-z0-9]/', '', $value);
        if (!is_string($value) || $value === '') {
            return '';
        }

        if (function_exists('mb_substr')) {
            $value = mb_substr($value, 0, 64);
        } else {
            $value = substr($value, 0, 64);
        }

        return $value;
    }

    private static function generateCardAccessKey() {
        $attempts = 0;
        $key = '';
        $table = self::getTableName(self::TABLE_NAME);
        $wpdb = self::getWpdb();

        do {
            $attempts++;
            $key = strtolower(wp_generate_password(32, false, false));
            $key = self::sanitizeCardAccessKey($key);
            if ($key === '') {
                continue;
            }

            $exists = $wpdb->get_var(
                $wpdb->prepare("SELECT id FROM $table WHERE card_access_key = %s LIMIT 1", $key)
            );

            if (empty($exists)) {
                return $key;
            }
        } while ($attempts < 5);

        return $key;
    }
}

\class_alias(__NAMESPACE__ . '\\MjMembers', 'MjMembers');

