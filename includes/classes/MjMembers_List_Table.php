<?php

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

if (!class_exists('MjTools')) {
    require_once __DIR__ . '/MjTools.php';
}

if (!class_exists('MjMembers_CRUD')) {
    require_once __DIR__ . '/MjMembers_CRUD.php';
}

class MjMembers_List_Table extends WP_List_Table {
    private const DEFAULT_PER_PAGE = 20;

    private const DEFAULT_VISIBLE_COLUMNS = array(
        'photo',
        'last_name',
        'first_name',
        'age',
        'role',
        'email',
        'login',
        'phone',
        'guardian',
        'requires_payment',
        'status',
        'date_last_payement',
        'payment_status',
        'photo_usage_consent',
        'date_inscription',
        'actions'
    );

    /** @var array<int, object|null> */
    private $guardianCache = array();
    /** @var array<int, WP_User|null> */
    private $userCache = array();
    /** @var array<string, mixed> */
    private $activeFilters = array();

    public function __construct() {
        parent::__construct(array(
            'singular' => 'mj_member',
            'plural'   => 'mj_members',
            'ajax'     => true,
        ));
    }

    public function prepare_items() {
        $columns  = $this->get_columns();
        $hidden   = $this->get_hidden_columns();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array($columns, $hidden, $sortable);

        $per_page     = self::DEFAULT_PER_PAGE;
        $current_page = $this->get_pagenum();
        $offset       = ($current_page - 1) * $per_page;

        $orderby = isset($_REQUEST['orderby']) ? sanitize_text_field(wp_unslash($_REQUEST['orderby'])) : 'date_inscription';
        $order   = isset($_REQUEST['order']) ? sanitize_text_field(wp_unslash($_REQUEST['order'])) : 'DESC';
        $search  = isset($_REQUEST['s']) ? sanitize_text_field(wp_unslash($_REQUEST['s'])) : '';

        $this->activeFilters = $this->collectFilters();

        $allowed_orderby = array('last_name', 'first_name', 'age', 'birth_date', 'role', 'status', 'date_inscription', 'date_last_payement');
        if (!in_array($orderby, $allowed_orderby, true)) {
            $orderby = 'date_inscription';
        }
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

        $effective_orderby = $orderby;
        $effective_order   = $order;

        if ($orderby === 'age') {
            $effective_orderby = 'birth_date';
            $effective_order   = ($order === 'ASC') ? 'DESC' : 'ASC';
        }

        if ($orderby === 'birth_date') {
            $effective_orderby = 'birth_date';
        }

        $members      = MjMembers_CRUD::getAll($per_page, $offset, $effective_orderby, $effective_order, $search, $this->activeFilters);
        $total_items  = MjMembers_CRUD::countAll($search, $this->activeFilters);

        $this->hydrateGuardians($members);
        $this->hydrateUsers($members);

        $this->items = $members;
        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => (int) ceil($total_items / $per_page),
        ));
    }

    public function get_columns() {
        return array(
            'cb'                  => '<input type="checkbox" />',
            'photo'               => 'Photo',
            'last_name'           => 'Nom',
            'first_name'          => 'Prénom',
            'age'                 => 'Âge',
            'role'                => 'Rôle',
            'email'               => 'Email',
            'login'               => 'Identifiant',
            'phone'               => 'Téléphone',
            'guardian'            => 'Responsable',
            'requires_payment'    => 'Cotisation',
            'status'              => 'Statut',
            'date_last_payement'  => 'Dernier Paiement',
            'payment_status'      => 'État Paiement',
            'photo_usage_consent' => 'Photos',
            'date_inscription'    => 'Inscription',
            'actions'             => 'Actions',
        );
    }

    public function get_hidden_columns() {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return array();
        }

        $visible_columns = get_user_meta($user_id, 'mj_visible_columns', true);
        if (empty($visible_columns) || !is_array($visible_columns)) {
            return array();
        }

        if (!in_array('age', $visible_columns, true)) {
            $visible_columns[] = 'age';
        }

        $all_columns = self::DEFAULT_VISIBLE_COLUMNS;
        return array_diff($all_columns, $visible_columns);
    }

    public function get_sortable_columns() {
        return array(
            'last_name'          => array('last_name', true),
            'first_name'         => array('first_name', false),
            'age'                => array('age', false),
            'role'               => array('role', false),
            'status'             => array('status', false),
            'date_last_payement' => array('date_last_payement', false),
            'date_inscription'   => array('date_inscription', true),
        );
    }

    public function no_items() {
        esc_html_e('Aucun membre trouvé.', 'mj-member');
    }

    public function column_cb($item) {
        return sprintf('<input type="checkbox" name="member[]" value="%d" />', (int) $item->id);
    }

    public function column_photo($item) {
        $output = '<div class="mj-photo-container" style="text-align:center;">';

        if (!empty($item->photo_id)) {
            $image = wp_get_attachment_image_src((int) $item->photo_id, 'thumbnail');
            if (!empty($image[0])) {
                $output .= '<img src="' . esc_url($image[0]) . '" alt="Photo" class="mj-member-photo" style="width:50px;height:50px;border-radius:50%;object-fit:cover;">';
            } else {
                $output .= '<span class="mj-no-photo" style="color:#999;">Pas de photo</span>';
            }
            $output .= '<br><button class="button button-small mj-delete-photo-btn" data-member-id="' . esc_attr($item->id) . '">✕ Supprimer</button>';
        } else {
            $output .= '<span class="mj-no-photo" style="color:#999;">Pas de photo</span>';
            $output .= '<br><button class="button button-small mj-photo-upload-btn" data-member-id="' . esc_attr($item->id) . '">📷 Ajouter</button>';
        }

        $output .= '</div>';
        return $output;
    }

    public function column_last_name($item) {
        $value = !empty($item->last_name) ? $item->last_name : 'N/A';
        return '<span class="mj-editable" data-member-id="' . esc_attr($item->id) . '" data-field-name="last_name" title="Cliquez pour éditer">' . esc_html($value) . '</span>';
    }

    public function column_first_name($item) {
        $value = !empty($item->first_name) ? $item->first_name : 'N/A';
        return '<span class="mj-editable" data-member-id="' . esc_attr($item->id) . '" data-field-name="first_name" title="Cliquez pour éditer">' . esc_html($value) . '</span>';
    }

    public function column_age($item) {
        $member_id = isset($item->id) ? (int) $item->id : 0;
        $birth_iso = '';
        $display_html = '<span style="color:#999;">Ajouter</span>';

        if (!empty($item->birth_date)) {
            $birth_timestamp = strtotime($item->birth_date);
            if ($birth_timestamp) {
                $birth_iso = gmdate('Y-m-d', $birth_timestamp);
                $now = current_time('timestamp');
                $age = (int) floor(($now - $birth_timestamp) / YEAR_IN_SECONDS);
                if ($age < 0) {
                    $age = 0;
                }

                $formatted_date = wp_date('d/m/Y', $birth_timestamp);
                $display_html = esc_html(sprintf('%d ans', $age));
                $display_html .= '<br><span class="mj-birth-date-display" style="color:#555;font-size:12px;">' . esc_html($formatted_date) . '</span>';
            } else {
                $display_html = '<span style="color:#999;">—</span>';
            }
        } else {
            $display_html = '<span style="color:#999;">—</span>';
        }

        $attributes = array(
            'class' => 'mj-editable',
            'data-member-id' => (string) $member_id,
            'data-field-name' => 'birth_date',
            'data-field-type' => 'date',
            'data-field-value' => $birth_iso,
            'title' => 'Cliquez pour éditer',
        );

        $attr_html = '';
        foreach ($attributes as $name => $value) {
            $attr_html .= sprintf(' %s="%s"', $name, esc_attr($value));
        }

        return '<span' . $attr_html . '>' . $display_html . '</span>';
    }

    public function column_email($item) {
        if (empty($item->email)) {
            return '<span class="mj-editable" data-member-id="' . esc_attr($item->id) . '" data-field-name="email" data-field-type="email" title="Cliquez pour éditer">N/A</span>';
        }

        $link = '<a href="mailto:' . esc_attr($item->email) . '">' . esc_html($item->email) . '</a>';
        return '<span class="mj-editable" data-member-id="' . esc_attr($item->id) . '" data-field-name="email" data-field-type="email" title="Cliquez pour éditer">' . $link . '</span>';
    }

    public function column_login($item) {
        $login = isset($item->member_account_login) ? trim((string) $item->member_account_login) : '';
        $has_user = !empty($item->wp_user_id);

        if ($has_user && $login === '') {
            if (!empty($item->wp_user_login)) {
                $login = trim((string) $item->wp_user_login);
            } else {
                $user = get_user_by('ID', (int) $item->wp_user_id);
                if ($user instanceof WP_User) {
                    $login = trim((string) $user->user_login);
                    $item->wp_user_login = $login;
                    if (empty($item->wp_user_email)) {
                        $item->wp_user_email = (string) $user->user_email;
                    }
                    if (empty($item->wp_user_roles) && is_array($user->roles)) {
                        $item->wp_user_roles = $user->roles;
                    }
                }
            }
        }

        $roles = array();
        if (!empty($item->wp_user_roles) && is_array($item->wp_user_roles)) {
            $roles = $item->wp_user_roles;
        } elseif (!empty($item->wp_user) && $item->wp_user instanceof WP_User) {
            $roles = is_array($item->wp_user->roles) ? $item->wp_user->roles : array();
        }

        $role_meta = $this->resolveWpRoleMeta($roles);
        $user_link = $has_user ? get_edit_user_link((int) $item->wp_user_id) : '';

        $output = array();
        if ($has_user && $login !== '') {
            $pill_classes = array('mj-login-pill');
            if ($role_meta['class'] !== '') {
                $pill_classes[] = 'mj-login-pill--role-' . sanitize_html_class($role_meta['class']);
            }
            $pill_class_attr = implode(' ', array_map('sanitize_html_class', $pill_classes));
            $icon_markup = $role_meta['icon'] !== '' ? '<span class="mj-login-icon" aria-hidden="true">' . esc_html($role_meta['icon']) . '</span>' : '';
            $login_markup = $icon_markup . '<span class="mj-login-text">' . esc_html($login) . '</span>';
            if ($user_link) {
                $login_markup = '<a href="' . esc_url($user_link) . '" target="_blank" rel="noopener noreferrer">' . $login_markup . '</a>';
            }
            $title_attr = $role_meta['label'] !== '' ? ' title="' . esc_attr(sprintf(__('Rôle WordPress : %s', 'mj-member'), $role_meta['label'])) . '"' : '';
            $output[] = '<span class="' . esc_attr($pill_class_attr) . '"' . $title_attr . '>' . $login_markup . '</span>';
        }

        $buttons = array();
        $member_name = trim(((string) ($item->first_name ?? '')) . ' ' . ((string) ($item->last_name ?? '')));
        $member_name = $member_name !== '' ? $member_name : __('Membre', 'mj-member');
        $button_label = $has_user ? __('Détails', 'mj-member') : __('Créer un compte', 'mj-member');
        $button_icon = $has_user ? '🔎' : '✨';
        $button_classes = array('mj-member-login-action', 'mj-link-user-btn');
        if (!$has_user) {
            $button_classes[] = 'mj-member-login-action--create';
        }

        $button_attrs = array(
            'data-member-id'      => (string) $item->id,
            'data-member-name'    => $member_name,
            'data-member-role'    => $item->role,
            'data-has-user'       => $has_user ? '1' : '0',
            'data-login'          => $login,
            'data-wp-role'        => $role_meta['key'],
            'data-wp-role-label'  => $role_meta['label'],
            'data-user-edit-url'  => $user_link,
        );

        $button_attr_html = '';
        foreach ($button_attrs as $name => $value) {
            $button_attr_html .= ' ' . $name . '="' . esc_attr($value) . '"';
        }

        $buttons[] = '<button type="button" class="' . esc_attr(implode(' ', $button_classes)) . '"' . $button_attr_html . '>' . esc_html($button_icon . ' ' . $button_label) . '</button>';

        if (!empty($buttons)) {
            $output[] = '<div class="mj-login-actions">' . implode('', $buttons) . '</div>';
        }

        return '<div class="mj-login-cell">' . implode('', $output) . '</div>';
    }

    public function column_phone($item) {
        $value = !empty($item->phone) ? $item->phone : 'N/A';
        return '<span class="mj-editable" data-member-id="' . esc_attr($item->id) . '" data-field-name="phone" title="Cliquez pour éditer">' . esc_html($value) . '</span>';
    }

    public function column_role($item) {
        $label = $this->formatRoleLabel($item->role);
        $badge = '<span class="badge" style="display:inline-flex;align-items:center;gap:4px;background-color:#eef1ff;color:#1d2b6b;padding:3px 8px;border-radius:12px;font-size:12px;">' . esc_html($label) . '</span>';

        return '<span class="mj-editable" data-member-id="' . esc_attr($item->id) . '" data-field-name="role" data-field-type="select" data-field-value="' . esc_attr($item->role) . '" title="Cliquez pour éditer">' . $badge . '</span>';
    }

    public function column_guardian($item) {
        if ($item->role !== MjMembers_CRUD::ROLE_JEUNE) {
            return '<span style="color:#999;">Non applicable</span>';
        }

        if (!empty($item->guardian) && is_object($item->guardian)) {
            $name  = trim(($item->guardian->first_name ?? '') . ' ' . ($item->guardian->last_name ?? ''));
            $name  = $name ?: 'Tuteur';
            $email = !empty($item->guardian->email) ? '<br><a href="mailto:' . esc_attr($item->guardian->email) . '">' . esc_html($item->guardian->email) . '</a>' : '';

            return '<div>' . esc_html($name) . $email . '</div>';
        }

        if (!empty($item->is_autonomous)) {
            return '<span class="badge" style="background-color:#17a2b8;color:#fff;padding:3px 8px;border-radius:12px;font-size:12px;">Autonome</span>';
        }

        return '<span style="color:#d63638;">Aucun responsable</span>';
    }

    public function column_requires_payment($item) {
        if (empty($item->requires_payment)) {
            return '<span class="badge" style="background-color:#6c757d;color:#fff;padding:3px 8px;border-radius:12px;font-size:12px;">Dispensé</span>';
        }

        $suffix = ($item->role === MjMembers_CRUD::ROLE_TUTEUR) ? ' (paye pour ses jeunes)' : '';

        return '<span class="badge" style="background-color:#28a745;color:#fff;padding:3px 8px;border-radius:12px;font-size:12px;">Obligatoire' . esc_html($suffix) . '</span>';
    }

    public function column_status($item) {
        $is_active = $item->status === MjMembers_CRUD::STATUS_ACTIVE;
        $class     = $is_active ? 'background-color:#28a745;' : 'background-color:#fd7e14;';
        $label     = $is_active ? 'Actif' : 'Inactif';

        return '<span class="mj-editable badge" data-member-id="' . esc_attr($item->id) . '" data-field-name="status" data-field-type="select" data-field-value="' . esc_attr($item->status) . '" title="Cliquez pour éditer" style="' . esc_attr($class . 'color:#fff;padding:3px 8px;border-radius:12px;font-size:12px;display:inline-block;') . '">' . esc_html($label) . '</span>';
    }

    public function column_date_last_payement($item) {
        if (empty($item->date_last_payement)) {
            return '<span style="color:#999;">Aucun paiement</span>';
        }

        $date = wp_date('d/m/Y', strtotime($item->date_last_payement));
        return '<span class="mj-editable" data-member-id="' . esc_attr($item->id) . '" data-field-name="date_last_payement" data-field-type="date" title="Cliquez pour éditer">' . esc_html($date) . '</span>';
    }

    public function column_payment_status($item) {
        $requires_payment = !empty($item->requires_payment);
        $status_modifier   = 'muted';
        $status_label      = esc_html__('Non concerné', 'mj-member');

        if ($requires_payment) {
            $payment_time = null;
            if (!empty($item->date_last_payement)) {
                $timestamp = strtotime($item->date_last_payement);
                if ($timestamp !== false) {
                    $payment_time = $timestamp;
                }
            }

            if ($payment_time === null) {
                $status_modifier = 'danger';
                $status_label    = esc_html__('Non payé', 'mj-member');
            } else {
                $current_time = current_time('timestamp');
                $days_diff    = (int) floor(($current_time - $payment_time) / DAY_IN_SECONDS);
                $days_diff    = max(0, $days_diff);
                $days_display = number_format_i18n($days_diff);

                if ($days_diff < 30) {
                    $status_modifier = 'success';
                    $status_label    = sprintf(esc_html__('À jour (%s j)', 'mj-member'), $days_display);
                } elseif ($days_diff < 60) {
                    $status_modifier = 'warning';
                    $status_label    = sprintf(esc_html__('À renouveler (%s j)', 'mj-member'), $days_display);
                } else {
                    $status_modifier = 'danger';
                    $status_label    = sprintf(esc_html__('Retard (%s j)', 'mj-member'), $days_display);
                }
            }
        }

        $badge_html = '<span class="mj-payment-status-pill mj-payment-status-pill--' . esc_attr($status_modifier) . '">' . $status_label . '</span>';

        $actions = array();

        if ($requires_payment) {
            $actions[] = '<button type="button" class="mj-member-login-action mj-payment-action mj-payment-action--qr mj-show-qr-btn" data-member-id="' . esc_attr($item->id) . '">⚡ ' . esc_html__('QR paiement', 'mj-member') . '</button>';
            $actions[] = '<button type="button" class="mj-member-login-action mj-payment-action mj-payment-action--mark mj-mark-paid-btn" data-member-id="' . esc_attr($item->id) . '">✅ ' . esc_html__('Marquer payé', 'mj-member') . '</button>';
        }

        $actions[] = '<button type="button" class="mj-member-login-action mj-payment-action mj-payment-action--history mj-payment-history-btn" data-member-id="' . esc_attr($item->id) . '">💳 ' . esc_html__('Historique', 'mj-member') . '</button>';

        $actions_html = '';
        if (!empty($actions)) {
            $actions_html = '<div class="mj-payment-actions">' . implode('', $actions) . '</div>';
        }

        return '<div class="mj-payment-cell"><div class="mj-payment-status">' . $badge_html . '</div>' . $actions_html . '</div>';
    }

    public function column_photo_usage_consent($item) {
        $accepted = !empty($item->photo_usage_consent);
        $style    = $accepted ? 'background-color:#28a745;' : 'background-color:#d63638;';
        $label    = $accepted ? 'Accepté' : 'Refusé';

        $value = $accepted ? '1' : '0';

        return '<span class="mj-editable badge" data-member-id="' . esc_attr($item->id) . '" data-field-name="photo_usage_consent" data-field-type="toggle" data-field-value="' . esc_attr($value) . '" title="Cliquez pour éditer" style="' . esc_attr($style . 'color:#fff;padding:3px 8px;border-radius:12px;font-size:12px;display:inline-block;') . '">' . esc_html($label) . '</span>';
    }

    public function column_date_inscription($item) {
        if (empty($item->date_inscription)) {
            return '—';
        }

        return esc_html(wp_date('d/m/Y', strtotime($item->date_inscription)));
    }

    public function column_actions($item) {
        $edit_url = add_query_arg(array(
            'page'   => 'mj_members',
            'action' => 'edit',
            'id'     => (int) $item->id,
        ), admin_url('admin.php'));

        $delete_url = wp_nonce_url(
            add_query_arg(array(
                'page'   => 'mj_members',
                'action' => 'delete',
                'id'     => (int) $item->id,
            ), admin_url('admin.php')),
            'mj_delete_nonce',
            'nonce'
        );

        $buttons = array();
        $member_name = trim(((string) ($item->first_name ?? '')) . ' ' . ((string) ($item->last_name ?? '')));
        $member_name = $member_name !== '' ? $member_name : __('Membre', 'mj-member');

        $buttons[] = '<a href="' . esc_url($edit_url) . '" class="button button-small mj-member-action-btn mj-member-action-btn--primary">✏️ ' . esc_html__('Éditer', 'mj-member') . '</a>';
        $buttons[] = '<a href="' . esc_url($delete_url) . '" class="button button-small button-link-delete mj-member-action-btn mj-member-action-btn--danger" onclick="return confirm(\'Êtes-vous sûr ?\');">🗑️ ' . esc_html__('Supprimer', 'mj-member') . '</a>';

        $login = isset($item->member_account_login) ? trim((string) $item->member_account_login) : '';
        if ($login === '' && !empty($item->wp_user_login)) {
            $login = (string) $item->wp_user_login;
        }

        if ($login === '' && !empty($item->wp_user_id)) {
            $user = get_user_by('ID', (int) $item->wp_user_id);
            if ($user instanceof WP_User) {
                $login = (string) $user->user_login;
                if (empty($item->wp_user_email)) {
                    $item->wp_user_email = (string) $user->user_email;
                }
            }
        }
        $reset_email = isset($item->wp_user_email) && $item->wp_user_email !== '' ? $item->wp_user_email : (string) ($item->email ?? '');
        if ($login !== '' && $reset_email !== '') {
            $buttons[] = '<button type="button" class="button button-small mj-member-action-btn mj-member-action-btn--secondary mj-reset-password-btn" data-member-id="' . esc_attr($item->id) . '" data-login="' . esc_attr($login) . '" data-email="' . esc_attr($reset_email) . '" data-member-name="' . esc_attr($member_name) . '">🔐 ' . esc_html__('Réinitialiser', 'mj-member') . '</button>';
        }

        if (empty($buttons)) {
            return '';
        }

        return '<div class="mj-member-actions">' . implode('', $buttons) . '</div>';
    }

    public function extra_tablenav($which) {
        if ('top' !== $which) {
            return;
        }

        $filters = $this->activeFilters;
        $role_labels = MjMembers_CRUD::getRoleLabels();
        $page_slug = isset($_REQUEST['page']) ? sanitize_key(wp_unslash($_REQUEST['page'])) : 'mj_members';
        $reset_url = remove_query_arg(array('filter_last_name','filter_first_name','filter_email','filter_age_min','filter_age_max','filter_payment','filter_date_start','filter_date_end','filter_role','paged'));
        ?>
        <div class="mj-filter-bar" style="margin:10px 0 15px;padding:12px;background:#eef5fb;border-radius:6px;">
            <form method="get" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
                <input type="hidden" name="page" value="<?php echo esc_attr($page_slug); ?>">
                <?php if (!empty($_REQUEST['orderby'])) : ?>
                    <input type="hidden" name="orderby" value="<?php echo esc_attr(sanitize_text_field(wp_unslash($_REQUEST['orderby']))); ?>">
                <?php endif; ?>
                <?php if (!empty($_REQUEST['order'])) : ?>
                    <input type="hidden" name="order" value="<?php echo esc_attr(sanitize_text_field(wp_unslash($_REQUEST['order']))); ?>">
                <?php endif; ?>
                <?php if (!empty($_REQUEST['s'])) : ?>
                    <input type="hidden" name="s" value="<?php echo esc_attr(sanitize_text_field(wp_unslash($_REQUEST['s']))); ?>">
                <?php endif; ?>
                <label style="display:flex;flex-direction:column;gap:4px;font-weight:600;font-size:13px;">
                    Nom
                    <input type="text" name="filter_last_name" value="<?php echo esc_attr($filters['last_name'] ?? ''); ?>" style="min-width:140px;">
                </label>
                <label style="display:flex;flex-direction:column;gap:4px;font-weight:600;font-size:13px;">
                    Prénom
                    <input type="text" name="filter_first_name" value="<?php echo esc_attr($filters['first_name'] ?? ''); ?>" style="min-width:140px;">
                </label>
                <label style="display:flex;flex-direction:column;gap:4px;font-weight:600;font-size:13px;">
                    Âge min.
                    <input type="number" min="0" name="filter_age_min" value="<?php echo esc_attr($filters['age_min_raw'] ?? ''); ?>" style="min-width:100px;">
                </label>
                <label style="display:flex;flex-direction:column;gap:4px;font-weight:600;font-size:13px;">
                    Âge max.
                    <input type="number" min="0" name="filter_age_max" value="<?php echo esc_attr($filters['age_max_raw'] ?? ''); ?>" style="min-width:100px;">
                </label>
                <label style="display:flex;flex-direction:column;gap:4px;font-weight:600;font-size:13px;">
                    Email
                    <input type="text" name="filter_email" value="<?php echo esc_attr($filters['email'] ?? ''); ?>" style="min-width:180px;">
                </label>
                <label style="display:flex;flex-direction:column;gap:4px;font-weight:600;font-size:13px;">
                    Paiement
                    <select name="filter_payment" style="min-width:150px;">
                        <option value="">Tous</option>
                        <option value="paid" <?php selected(($filters['payment'] ?? '') === 'paid'); ?>>À jour</option>
                        <option value="due" <?php selected(($filters['payment'] ?? '') === 'due'); ?>>À payer</option>
                        <option value="exempt" <?php selected(($filters['payment'] ?? '') === 'exempt'); ?>>Dispensé</option>
                    </select>
                </label>
                <label style="display:flex;flex-direction:column;gap:4px;font-weight:600;font-size:13px;">
                    Date d'inscription (du)
                    <input type="date" name="filter_date_start" value="<?php echo esc_attr($filters['date_start_raw'] ?? ''); ?>">
                </label>
                <label style="display:flex;flex-direction:column;gap:4px;font-weight:600;font-size:13px;">
                    (au)
                    <input type="date" name="filter_date_end" value="<?php echo esc_attr($filters['date_end_raw'] ?? ''); ?>">
                </label>
                <label style="display:flex;flex-direction:column;gap:4px;font-weight:600;font-size:13px;">
                    Rôle
                    <select name="filter_role" style="min-width:140px;">
                        <option value="">Tous</option>
                        <?php foreach ($role_labels as $role_key => $role_label) : ?>
                            <option value="<?php echo esc_attr($role_key); ?>" <?php selected(($filters['role'] ?? '') === $role_key); ?>><?php echo esc_html($role_label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <div style="display:flex;gap:8px;align-items:center;">
                    <button type="submit" class="button button-primary">Filtrer</button>
                    <a href="<?php echo esc_url($reset_url); ?>" class="button">Réinitialiser</a>
                </div>
            </form>
        </div>
        <?php

        $user_id = get_current_user_id();
        $visible_columns = $user_id ? get_user_meta($user_id, 'mj_visible_columns', true) : array();
        if (empty($visible_columns) || !is_array($visible_columns)) {
            $visible_columns = self::DEFAULT_VISIBLE_COLUMNS;
        }

        $column_labels = array(
            'photo'               => 'Photo',
            'last_name'           => 'Nom',
            'first_name'          => 'Prénom',
            'role'                => 'Rôle',
            'email'               => 'Email',
            'login'               => 'Identifiant',
            'phone'               => 'Téléphone',
            'guardian'            => 'Responsable',
            'requires_payment'    => 'Cotisation',
            'status'              => 'Statut',
            'date_last_payement'  => 'Dernier Paiement',
            'payment_status'      => 'État Paiement',
            'photo_usage_consent' => 'Photos',
            'date_inscription'    => 'Inscription',
            'actions'             => 'Actions',
        );
        ?>
        <div class="mj-column-selector" style="margin:10px 0;padding:10px;background:#f5f5f5;border-radius:4px;">
            <strong style="display:block;margin-bottom:8px;">Afficher/Masquer les colonnes :</strong>
            <form method="post" style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;">
                <?php wp_nonce_field('mj_column_visibility_nonce'); ?>
                <?php foreach ($column_labels as $column_key => $label) : ?>
                    <label style="display:flex;align-items:center;gap:6px;">
                        <input type="checkbox" name="mj_columns[]" value="<?php echo esc_attr($column_key); ?>" <?php checked(in_array($column_key, $visible_columns, true)); ?> />
                        <?php echo esc_html($label); ?>
                    </label>
                <?php endforeach; ?>
                <button type="submit" name="mj_save_columns" class="button button-small">Enregistrer</button>
            </form>
        </div>
        <?php
    }

    public function column_default($item, $column_name) {
        if (isset($item->$column_name)) {
            return esc_html((string) $item->$column_name);
        }

        return 'N/A';
    }

    private function collectFilters() {
        $filters = array();

        if (!empty($_REQUEST['filter_last_name'])) {
            $filters['last_name'] = sanitize_text_field(wp_unslash($_REQUEST['filter_last_name']));
        }

        if (!empty($_REQUEST['filter_first_name'])) {
            $filters['first_name'] = sanitize_text_field(wp_unslash($_REQUEST['filter_first_name']));
        }

        if (!empty($_REQUEST['filter_email'])) {
            $filters['email'] = sanitize_text_field(wp_unslash($_REQUEST['filter_email']));
        }

        if (isset($_REQUEST['filter_age_min']) && $_REQUEST['filter_age_min'] !== '') {
            $age_min = intval($_REQUEST['filter_age_min']);
            if ($age_min >= 0) {
                $filters['age_min'] = $age_min;
                $filters['age_min_raw'] = (string) $age_min;
            }
        }

        if (isset($_REQUEST['filter_age_max']) && $_REQUEST['filter_age_max'] !== '') {
            $age_max = intval($_REQUEST['filter_age_max']);
            if ($age_max >= 0) {
                $filters['age_max'] = $age_max;
                $filters['age_max_raw'] = (string) $age_max;
            }
        }

        if (!empty($_REQUEST['filter_payment'])) {
            $payment = sanitize_key(wp_unslash($_REQUEST['filter_payment']));
            if (in_array($payment, array('paid', 'due', 'exempt'), true)) {
                $filters['payment'] = $payment;
            }
        }

        if (!empty($_REQUEST['filter_role'])) {
            $role = sanitize_key(wp_unslash($_REQUEST['filter_role']));
            if (in_array($role, array_keys(MjMembers_CRUD::getRoleLabels()), true)) {
                $filters['role'] = $role;
            }
        }

        if (!empty($_REQUEST['filter_date_start'])) {
            $raw = sanitize_text_field(wp_unslash($_REQUEST['filter_date_start']));
            $timestamp = strtotime($raw);
            if ($timestamp) {
                $date = wp_date('Y-m-d', $timestamp);
                $filters['date_start'] = $date . ' 00:00:00';
                $filters['date_start_raw'] = $date;
            }
        }

        if (!empty($_REQUEST['filter_date_end'])) {
            $raw = sanitize_text_field(wp_unslash($_REQUEST['filter_date_end']));
            $timestamp = strtotime($raw);
            if ($timestamp) {
                $date = wp_date('Y-m-d', $timestamp);
                $filters['date_end'] = $date . ' 23:59:59';
                $filters['date_end_raw'] = $date;
            }
        }

        return $filters;
    }

    private function hydrateUsers(array &$members) {
        if (empty($members)) {
            return;
        }

        $user_ids = array();
        foreach ($members as $member) {
            if (!empty($member->wp_user_id)) {
                $user_ids[] = (int) $member->wp_user_id;
            }
        }

        $user_ids = array_unique(array_filter($user_ids));
        if (empty($user_ids)) {
            foreach ($members as $member) {
                $member->wp_user = null;
                $member->wp_user_login = '';
                $member->wp_user_email = '';
            }
            return;
        }

        $missing_ids = array();
        foreach ($user_ids as $user_id) {
            if (!array_key_exists($user_id, $this->userCache)) {
                $missing_ids[] = $user_id;
            }
        }

        if (!empty($missing_ids)) {
            $users = get_users(array(
                'include' => $missing_ids,
            ));

            foreach ($missing_ids as $missing_id) {
                $this->userCache[$missing_id] = null;
            }

            foreach ($users as $user_object) {
                $this->userCache[(int) $user_object->ID] = $user_object;
            }
        }

        foreach ($members as $member) {
            $user_id = !empty($member->wp_user_id) ? (int) $member->wp_user_id : 0;
            if ($user_id && isset($this->userCache[$user_id]) && $this->userCache[$user_id] instanceof WP_User) {
                $member->wp_user = $this->userCache[$user_id];
                $member->wp_user_login = $this->userCache[$user_id]->user_login;
                $member->wp_user_email = $this->userCache[$user_id]->user_email;
                $member->wp_user_roles = is_array($this->userCache[$user_id]->roles) ? $this->userCache[$user_id]->roles : array();
            } else {
                $member->wp_user = null;
                $member->wp_user_login = '';
                $member->wp_user_email = '';
                $member->wp_user_roles = array();
            }
        }
    }

    private function hydrateGuardians(array &$members) {
        $guardian_ids = array();
        foreach ($members as $member) {
            if (!empty($member->guardian_id)) {
                $guardian_ids[] = (int) $member->guardian_id;
            }
        }

        $guardian_ids = array_unique($guardian_ids);
        if (empty($guardian_ids)) {
            return;
        }

        $missing_ids = array();
        foreach ($guardian_ids as $id) {
            if (!array_key_exists($id, $this->guardianCache)) {
                $missing_ids[] = $id;
            }
        }

        if (!empty($missing_ids)) {
            global $wpdb;
            $table = MjMembers_CRUD::getTableName(MjMembers_CRUD::TABLE_NAME);
            $placeholders = implode(',', array_fill(0, count($missing_ids), '%d'));
            $sql = "SELECT id, first_name, last_name, email, phone FROM $table WHERE id IN ($placeholders)";
            $sql = call_user_func_array(array($wpdb, 'prepare'), array_merge(array($sql), $missing_ids));
            $results = $wpdb->get_results($sql);

            foreach ($missing_ids as $id) {
                $this->guardianCache[$id] = null;
            }

            foreach ($results as $row) {
                $this->guardianCache[(int) $row->id] = $row;
            }
        }

        foreach ($members as $member) {
            if (!empty($member->guardian_id) && isset($this->guardianCache[(int) $member->guardian_id])) {
                $member->guardian = $this->guardianCache[(int) $member->guardian_id];
            } else {
                $member->guardian = null;
            }
        }
    }

    private function formatRoleLabel($role) {
        switch ($role) {
            case MjMembers_CRUD::ROLE_JEUNE:
                return 'Jeune';
            case MjMembers_CRUD::ROLE_ANIMATEUR:
                return 'Animateur';
            case MjMembers_CRUD::ROLE_BENEVOLE:
                return 'Bénévole';
            case MjMembers_CRUD::ROLE_TUTEUR:
                return 'Tuteur';
            default:
                return ucfirst($role);
        }
    }

    private function roleIcon($role) {
        return '';
    }

    /**
     * @param array<int, string> $roles
     * @return array{key:string,label:string,icon:string,class:string}
     */
    private function resolveWpRoleMeta(array $roles) {
        $primary_role = '';
        foreach ($roles as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                $primary_role = $candidate;
                break;
            }
        }

        $label = '';
        if ($primary_role !== '') {
            if (function_exists('wp_roles')) {
                $roles_object = wp_roles();
                if ($roles_object instanceof WP_Roles && isset($roles_object->roles[$primary_role])) {
                    $label = translate_user_role($roles_object->roles[$primary_role]['name']);
                }
            }
            if ($label === '') {
                $label = ucwords(str_replace(array('-', '_'), ' ', $primary_role));
            }
        }

        $map = array(
            'administrator' => array('icon' => '👑', 'class' => 'administrator'),
            'editor'        => array('icon' => '📝', 'class' => 'editor'),
            'author'        => array('icon' => '✍️', 'class' => 'author'),
            'contributor'   => array('icon' => '🧾', 'class' => 'contributor'),
            'subscriber'    => array('icon' => '🙋', 'class' => 'subscriber'),
            'shop_manager'  => array('icon' => '🛒', 'class' => 'shop-manager'),
        );

        $icon = '👤';
        $class = 'default';
        if ($primary_role !== '') {
            if (isset($map[$primary_role])) {
                $icon = $map[$primary_role]['icon'];
                $class = $map[$primary_role]['class'];
            } else {
                $class = strtolower(str_replace('_', '-', $primary_role));
            }
        }

        return array(
            'key'   => $primary_role,
            'label' => $label,
            'icon'  => $icon,
            'class' => $class,
        );
    }
}
