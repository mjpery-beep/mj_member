<?php 

class MjMembers_CRUD extends MjTools {

    const TABLE_NAME = 'mj_members';

    const ROLE_JEUNE = 'jeune';
    const ROLE_ANIMATEUR = 'animateur';
    const ROLE_BENEVOLE = 'benevole';
    const ROLE_TUTEUR = 'tuteur';

    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';

    public static function getAllowedRoles() {
        return array(
            self::ROLE_JEUNE,
            self::ROLE_ANIMATEUR,
            self::ROLE_BENEVOLE,
            self::ROLE_TUTEUR,
        );
    }

    public static function getRoleLabels() {
        return array(
            self::ROLE_JEUNE => 'Jeune',
            self::ROLE_TUTEUR => 'Tuteur',
            self::ROLE_ANIMATEUR => 'Animateur',
            self::ROLE_BENEVOLE => 'Bénévole',
        );
    }

    public static function getAll($limit = 0, $offset = 0, $orderby = 'date_inscription', $order = 'DESC', $search = '', $filters = array()) {
        $table_name = self::getTableName(self::TABLE_NAME);
        $wpdb = self::getWpdb();

        $allowed_orderby = array('last_name', 'first_name', 'role', 'status', 'date_inscription', 'date_last_payement', 'id');
        if (!in_array($orderby, $allowed_orderby, true)) {
            $orderby = 'date_inscription';
        }

        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

        $clauses = array();
        $params = array();
        $search = sanitize_text_field($search);
        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $clauses[] = '(first_name LIKE %s OR last_name LIKE %s OR email LIKE %s OR phone LIKE %s OR city LIKE %s OR postal_code LIKE %s)';
            $params = array_merge($params, array($like, $like, $like, $like, $like, $like));
        }

        if (!is_array($filters)) {
            $filters = array();
        }

        if (!empty($filters['last_name'])) {
            $like = '%' . $wpdb->esc_like(sanitize_text_field($filters['last_name'])) . '%';
            $clauses[] = 'last_name LIKE %s';
            $params[] = $like;
        }

        if (!empty($filters['first_name'])) {
            $like = '%' . $wpdb->esc_like(sanitize_text_field($filters['first_name'])) . '%';
            $clauses[] = 'first_name LIKE %s';
            $params[] = $like;
        }

        if (!empty($filters['email'])) {
            $like = '%' . $wpdb->esc_like(sanitize_text_field($filters['email'])) . '%';
            $clauses[] = 'email LIKE %s';
            $params[] = $like;
        }

        if (isset($filters['age_min'])) {
            $clauses[] = '(birth_date IS NOT NULL AND birth_date <> "0000-00-00" AND TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) >= %d)';
            $params[] = (int) $filters['age_min'];
        }

        if (isset($filters['age_max'])) {
            $clauses[] = '(birth_date IS NOT NULL AND birth_date <> "0000-00-00" AND TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) <= %d)';
            $params[] = (int) $filters['age_max'];
        }

        if (!empty($filters['role'])) {
            $role = sanitize_key($filters['role']);
            if (in_array($role, self::getAllowedRoles(), true)) {
                $clauses[] = 'role = %s';
                $params[] = $role;
            }
        }

        if (!empty($filters['payment'])) {
            $payment_filter = sanitize_key($filters['payment']);
            if ($payment_filter === 'paid') {
                $clauses[] = '(requires_payment = 1 AND date_last_payement IS NOT NULL AND date_last_payement <> %s AND CAST(date_last_payement AS CHAR) <> %s)';
                $params[] = '0000-00-00 00:00:00';
                $params[] = '';
            } elseif ($payment_filter === 'due') {
                $clauses[] = '(requires_payment = 1 AND (date_last_payement IS NULL OR date_last_payement = %s OR CAST(date_last_payement AS CHAR) = %s))';
                $params[] = '0000-00-00 00:00:00';
                $params[] = '';
            } elseif ($payment_filter === 'exempt') {
                $clauses[] = 'requires_payment = 0';
            }
        }

        if (!empty($filters['date_start'])) {
            $clauses[] = 'date_inscription >= %s';
            $params[] = sanitize_text_field($filters['date_start']);
        }

        if (!empty($filters['date_end'])) {
            $clauses[] = 'date_inscription <= %s';
            $params[] = sanitize_text_field($filters['date_end']);
        }

        $sql = "SELECT * FROM $table_name";
        if (!empty($clauses)) {
            $sql .= ' WHERE ' . implode(' AND ', $clauses);
        }
        $sql .= " ORDER BY $orderby $order";

        if ($limit > 0) {
            $sql .= ' LIMIT %d OFFSET %d';
            $params[] = (int) $limit;
            $params[] = (int) $offset;
        }

        if (!empty($params)) {
            $sql = call_user_func_array(array($wpdb, 'prepare'), array_merge(array($sql), $params));
        }

        return $wpdb->get_results($sql);
    }

    public static function countAll($search = '', $filters = array()) {
        $table_name = self::getTableName(self::TABLE_NAME);
        $wpdb = self::getWpdb();

        $clauses = array();
        $params = array();
        $search = sanitize_text_field($search);
        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $clauses[] = '(first_name LIKE %s OR last_name LIKE %s OR email LIKE %s OR phone LIKE %s OR city LIKE %s OR postal_code LIKE %s)';
            $params = array_merge($params, array($like, $like, $like, $like, $like, $like));
        }

        if (!is_array($filters)) {
            $filters = array();
        }

        if (!empty($filters['last_name'])) {
            $like = '%' . $wpdb->esc_like(sanitize_text_field($filters['last_name'])) . '%';
            $clauses[] = 'last_name LIKE %s';
            $params[] = $like;
        }

        if (!empty($filters['first_name'])) {
            $like = '%' . $wpdb->esc_like(sanitize_text_field($filters['first_name'])) . '%';
            $clauses[] = 'first_name LIKE %s';
            $params[] = $like;
        }

        if (!empty($filters['email'])) {
            $like = '%' . $wpdb->esc_like(sanitize_text_field($filters['email'])) . '%';
            $clauses[] = 'email LIKE %s';
            $params[] = $like;
        }

        if (isset($filters['age_min'])) {
            $clauses[] = '(birth_date IS NOT NULL AND birth_date <> "0000-00-00" AND TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) >= %d)';
            $params[] = (int) $filters['age_min'];
        }

        if (isset($filters['age_max'])) {
            $clauses[] = '(birth_date IS NOT NULL AND birth_date <> "0000-00-00" AND TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) <= %d)';
            $params[] = (int) $filters['age_max'];
        }

        if (!empty($filters['role'])) {
            $role = sanitize_key($filters['role']);
            if (in_array($role, self::getAllowedRoles(), true)) {
                $clauses[] = 'role = %s';
                $params[] = $role;
            }
        }

        if (!empty($filters['payment'])) {
            $payment_filter = sanitize_key($filters['payment']);
            if ($payment_filter === 'paid') {
                $clauses[] = '(requires_payment = 1 AND date_last_payement IS NOT NULL AND date_last_payement <> %s AND CAST(date_last_payement AS CHAR) <> %s)';
                $params[] = '0000-00-00 00:00:00';
                $params[] = '';
            } elseif ($payment_filter === 'due') {
                $clauses[] = '(requires_payment = 1 AND (date_last_payement IS NULL OR date_last_payement = %s OR CAST(date_last_payement AS CHAR) = %s))';
                $params[] = '0000-00-00 00:00:00';
                $params[] = '';
            } elseif ($payment_filter === 'exempt') {
                $clauses[] = 'requires_payment = 0';
            }
        }

        if (!empty($filters['date_start'])) {
            $clauses[] = 'date_inscription >= %s';
            $params[] = sanitize_text_field($filters['date_start']);
        }

        if (!empty($filters['date_end'])) {
            $clauses[] = 'date_inscription <= %s';
            $params[] = sanitize_text_field($filters['date_end']);
        }

        $sql = "SELECT COUNT(*) FROM $table_name";
        if (!empty($clauses)) {
            $sql .= ' WHERE ' . implode(' AND ', $clauses);
        }

        if (!empty($params)) {
            $sql = call_user_func_array(array($wpdb, 'prepare'), array_merge(array($sql), $params));
        }

        return (int) $wpdb->get_var($sql);
    }

    public static function getById($id) {
        $table_name = self::getTableName(self::TABLE_NAME);
        return self::getWpdb()->get_row(
            self::getWpdb()->prepare("SELECT * FROM $table_name WHERE id = %d", intval($id))
        );
    }

    public static function getByWpUserId($user_id) {
        $table_name = self::getTableName(self::TABLE_NAME);
        $user_id = intval($user_id);
        if ($user_id <= 0) {
            return null;
        }

        return self::getWpdb()->get_row(
            self::getWpdb()->prepare("SELECT * FROM $table_name WHERE wp_user_id = %d LIMIT 1", $user_id)
        );
    }

    public static function getByEmail($email) {
        $table_name = self::getTableName(self::TABLE_NAME);
        $email = self::sanitizeOptionalEmail($email);
        if ($email === null) {
            return null;
        }

        return self::getWpdb()->get_row(
            self::getWpdb()->prepare("SELECT * FROM $table_name WHERE email = %s LIMIT 1", $email)
        );
    }

    public static function create($data) {
        $table_name = self::getTableName(self::TABLE_NAME);

        $role = self::normalizeRole($data['role'] ?? self::ROLE_JEUNE);
        $requires_payment = self::normalizePaymentFlag($role, $data['requires_payment'] ?? null);
        $status = self::normalizeStatus($data['status'] ?? self::STATUS_ACTIVE);
        $guardian_id = self::resolveGuardianId($data['guardian_id'] ?? null, $role, null);
        $is_autonomous = ($role === self::ROLE_JEUNE) ? (empty($guardian_id) ? 1 : (!empty($data['is_autonomous']) ? 1 : 0)) : 1;

        $first_name = sanitize_text_field($data['first_name'] ?? '');
        $last_name = sanitize_text_field($data['last_name'] ?? '');
        $email = self::sanitizeOptionalEmail($data['email'] ?? '');

        if ($first_name === '' || $last_name === '') {
            return false;
        }

        if ($email !== null && !is_email($email)) {
            return false;
        }

        if ($role !== self::ROLE_JEUNE && empty($email)) {
            return false;
        }

        $insert = array(
            'first_name' => $first_name,
            'last_name' => $last_name,
            'email' => $email,
            'phone' => self::sanitizeOptionalText($data['phone'] ?? ''),
            'birth_date' => self::sanitizeDate($data['birth_date'] ?? ''),
            'role' => $role,
            'guardian_id' => $guardian_id,
            'is_autonomous' => $is_autonomous,
            'requires_payment' => $requires_payment,
            'address' => self::sanitizeOptionalText($data['address'] ?? ''),
            'city' => self::sanitizeOptionalText($data['city'] ?? ''),
            'postal_code' => self::sanitizeOptionalText($data['postal_code'] ?? ''),
            'notes' => self::sanitizeNotes($data['notes'] ?? ''),
            'description_courte' => self::sanitizeOptionalText($data['description_courte'] ?? ''),
            'description_longue' => self::sanitizeNotes($data['description_longue'] ?? ''),
            'status' => $status,
            'date_last_payement' => self::sanitizeDateTime($data['date_last_payement'] ?? ''),
            'photo_id' => isset($data['photo_id']) ? intval($data['photo_id']) : null,
            'photo_usage_consent' => !empty($data['photo_usage_consent']) ? 1 : 0,
            'wp_user_id' => self::sanitizeUserId($data['wp_user_id'] ?? null),
        );

        $insert = self::stripNulls($insert);
        $result = self::getWpdb()->insert($table_name, $insert);

        if ($result) {
            return (int) self::getWpdb()->insert_id;
        }

        return false;
    }

    public static function update($id, $data) {
        $table_name = self::getTableName(self::TABLE_NAME);
        $wpdb = self::getWpdb();

        $id = intval($id);
        if ($id <= 0) {
            return false;
        }

        $current = self::getById($id);
        if (!$current) {
            return false;
        }

        $updates = array();

        $allowed_fields = array(
            'first_name','last_name','email','phone','birth_date','role','guardian_id','is_autonomous','requires_payment','address','city','postal_code','notes','description_courte','description_longue','status','date_last_payement','photo_id','photo_usage_consent','wp_user_id'
        );

        foreach ($data as $field => $value) {
            if (!in_array($field, $allowed_fields, true)) {
                continue;
            }

            switch ($field) {
                case 'email':
                    $updates[$field] = self::sanitizeOptionalEmail($value);
                    break;
                case 'first_name':
                case 'last_name':
                    $updates[$field] = sanitize_text_field($value);
                    break;
                case 'phone':
                case 'address':
                case 'city':
                case 'postal_code':
                    $updates[$field] = self::sanitizeOptionalText($value);
                    break;
                case 'notes':
                    $updates[$field] = self::sanitizeNotes($value);
                    break;
                case 'description_courte':
                    $updates[$field] = self::sanitizeOptionalText($value);
                    break;
                case 'description_longue':
                    $updates[$field] = self::sanitizeNotes($value);
                    break;
                case 'birth_date':
                    $updates[$field] = self::sanitizeDate($value);
                    break;
                case 'date_last_payement':
                    $updates[$field] = self::sanitizeDateTime($value);
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
                case 'is_autonomous':
                case 'photo_usage_consent':
                    $updates[$field] = !empty($value) ? 1 : 0;
                    break;
                case 'photo_id':
                    $updates[$field] = intval($value);
                    break;
                case 'wp_user_id':
                    $updates[$field] = self::sanitizeUserId($value);
                    break;
                default:
                    $updates[$field] = $value;
            }
        }

        if (empty($updates)) {
            return false;
        }

        $effective_role = $updates['role'] ?? $current->role;

        if (array_key_exists('email', $updates) && $updates['email'] !== null && !is_email($updates['email'])) {
            return false;
        }

        if ($effective_role !== self::ROLE_JEUNE && array_key_exists('email', $updates) && empty($updates['email'])) {
            return false;
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
        return $result;
    }

    public static function delete($id) {
        $table_name = self::getTableName(self::TABLE_NAME);
        $wpdb = self::getWpdb();

        $id = intval($id);
        if ($id <= 0) {
            return false;
        }

        $wpdb->query($wpdb->prepare("UPDATE $table_name SET guardian_id = NULL WHERE guardian_id = %d", $id));

        return $wpdb->delete($table_name, array('id' => $id), array('%d'));
    }

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

        return $wpdb->get_results($sql);
    }

    public static function getByStatus($status) {
        $table_name = self::getTableName(self::TABLE_NAME);
        $status = self::normalizeStatus($status);

        return self::getWpdb()->get_results(
            self::getWpdb()->prepare("SELECT * FROM $table_name WHERE status = %s ORDER BY date_inscription DESC", $status)
        );
    }

    public static function getByRole($role) {
        $role = self::normalizeRole($role);
        $table_name = self::getTableName(self::TABLE_NAME);

        return self::getWpdb()->get_results(
            self::getWpdb()->prepare("SELECT * FROM $table_name WHERE role = %s ORDER BY last_name ASC, first_name ASC", $role)
        );
    }

    public static function getGuardians($search = '') {
        $table_name = self::getTableName(self::TABLE_NAME);
        $wpdb = self::getWpdb();

        $search = sanitize_text_field($search);
        if ($search === '') {
            return $wpdb->get_results("SELECT id, first_name, last_name, email FROM $table_name WHERE role = 'tuteur' ORDER BY last_name ASC, first_name ASC");
        }

        $like = '%' . $wpdb->esc_like($search) . '%';
        return $wpdb->get_results($wpdb->prepare(
            "SELECT id, first_name, last_name, email FROM $table_name WHERE role = 'tuteur' AND (first_name LIKE %s OR last_name LIKE %s OR email LIKE %s) ORDER BY last_name ASC, first_name ASC",
            $like,
            $like,
            $like
        ));
    }

    public static function getChildrenForGuardian($guardian_id) {
        $table_name = self::getTableName(self::TABLE_NAME);
        $guardian_id = intval($guardian_id);
        if ($guardian_id <= 0) {
            return array();
        }

        return self::getWpdb()->get_results(
            self::getWpdb()->prepare("SELECT * FROM $table_name WHERE guardian_id = %d ORDER BY last_name ASC, first_name ASC", $guardian_id)
        );
    }

    private static function normalizeRole($role) {
        $role = strtolower(sanitize_text_field($role));
        return in_array($role, self::getAllowedRoles(), true) ? $role : self::ROLE_JEUNE;
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

    private static function sanitizeOptionalEmail($value) {
        if ($value === null || $value === '') {
            return null;
        }

        $email = sanitize_email($value);
        return $email === '' ? null : $email;
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
     * @return int|null Guardian id or null if it cannot be resolved.
     */
    public static function upsertGuardian(array $data, $current_guardian_id = 0) {
        $email = sanitize_email($data['email'] ?? '');
        $first_name = sanitize_text_field($data['first_name'] ?? '');
        $last_name = sanitize_text_field($data['last_name'] ?? '');

        if (empty($email) || empty($first_name) || empty($last_name)) {
            return null;
        }

        $payload = array(
            'first_name' => $first_name,
            'last_name' => $last_name,
            'email' => $email,
            'phone' => self::sanitizeOptionalText($data['phone'] ?? ''),
            'address' => self::sanitizeOptionalText($data['address'] ?? ''),
            'city' => self::sanitizeOptionalText($data['city'] ?? ''),
            'postal_code' => self::sanitizeOptionalText($data['postal_code'] ?? ''),
            'status' => self::normalizeStatus($data['status'] ?? self::STATUS_ACTIVE),
            'role' => self::ROLE_TUTEUR,
            'requires_payment' => 0,
            'is_autonomous' => 1,
        );

        $payload = self::stripNulls($payload);

        $table = self::getTableName(self::TABLE_NAME);
        $wpdb = self::getWpdb();

        $target_id = intval($current_guardian_id);
        if ($target_id > 0) {
            $existing = self::getById($target_id);
            if ($existing && $existing->role === self::ROLE_TUTEUR) {
                self::update($target_id, $payload);
                return $target_id;
            }
        }

        $existing_id = $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM $table WHERE email = %s AND role = %s", $email, self::ROLE_TUTEUR)
        );

        if ($existing_id) {
            self::update((int) $existing_id, $payload);
            return (int) $existing_id;
        }

        $payload['role'] = self::ROLE_TUTEUR;
        $payload['requires_payment'] = 0;
        $payload['is_autonomous'] = 1;

        return self::create($payload);
    }

    private static function stripNulls(array $data) {
        foreach ($data as $key => $value) {
            if ($value === null) {
                unset($data[$key]);
            }
        }
        return $data;
    }
}