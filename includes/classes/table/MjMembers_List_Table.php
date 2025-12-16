<?php

namespace Mj\Member\Classes\Table;

use Mj\Member\Classes\Crud\MjMembers;
use Mj\Member\Classes\MjTools;
use Mj\Member\Classes\Value\MemberData;
use WP_List_Table;

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('\\WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class MjMembers_List_Table extends WP_List_Table {
    private const DEFAULT_PER_PAGE = 20;

    private const DEFAULT_VISIBLE_COLUMNS = array(
        'photo',
        'detail',
        'last_name',
        'first_name',
        'age',
        'role',
        'email',
        'phone',
        'status',
        'manage',
        'photo_usage_consent',
        'date_inscription'
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

        $members      = MjMembers::getAll($per_page, $offset, $effective_orderby, $effective_order, $search, $this->activeFilters);
        $total_items  = MjMembers::countAll($search, $this->activeFilters);

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
            'detail'              => 'Détail',
            'last_name'           => 'Nom',
            'first_name'          => 'Prénom',
            'age'                 => 'Âge',
            'role'                => 'Rôle',
            'email'               => 'Email',
            'phone'               => 'Téléphone',
            'status'              => 'Statut',
            'manage'              => 'Accès & actions',
            'photo_usage_consent' => 'Consentement photo',
            'date_inscription'    => 'Inscription',
        );
    }

    public function get_hidden_columns() {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return array();
        }

        $visible_columns = get_user_meta($user_id, 'mj_visible_columns', true);
        if (!is_array($visible_columns)) {
            $visible_columns = array();
        }

        $all_columns = self::DEFAULT_VISIBLE_COLUMNS;
        $legacy_columns = array('login', 'payment_status', 'actions');

        $visible_columns = array_diff($visible_columns, $legacy_columns);
        $visible_columns = array_values(array_intersect($visible_columns, $all_columns));

        if (!in_array('manage', $visible_columns, true)) {
            $visible_columns[] = 'manage';
        }

        return array_diff($all_columns, $visible_columns);
    }

    public function get_sortable_columns() {
        return array(
            'last_name'        => array('last_name', true),
            'first_name'       => array('first_name', false),
            'age'              => array('age', false),
            'role'             => array('role', false),
            'status'           => array('status', false),
            'date_inscription' => array('date_inscription', true),
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
        $age_data = $this->getAgeData($item);

        if ($age_data['iso'] === '') {
            $age_data['iso'] = '';
        }

        if ($age_data['age'] !== null) {
            $display_html = esc_html(sprintf('%d ans', $age_data['age']));
            if ($age_data['formatted'] !== '') {
                $display_html .= '<br><span class="mj-birth-date-display" style="color:#555;font-size:12px;">' . esc_html($age_data['formatted']) . '</span>';
            }
        } else {
            $display_html = '<span style="color:#999;">—</span>';
        }

        $attributes = array(
            'class' => 'mj-editable',
            'data-member-id' => (string) $member_id,
            'data-field-name' => 'birth_date',
            'data-field-type' => 'date',
            'data-field-value' => $age_data['iso'],
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

    private function buildLoginSection($item) {
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
        $button_label = $has_user ? __('WP user', 'mj-member') : __('Créer un compte', 'mj-member');
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

        if (empty($output)) {
            return '';
        }

        return '<div class="mj-login-cell">' . implode('', $output) . '</div>';
    }

    public function column_phone($item) {
        $value = !empty($item->phone) ? $item->phone : 'N/A';
        return '<span class="mj-editable" data-member-id="' . esc_attr($item->id) . '" data-field-name="phone" title="Cliquez pour éditer">' . esc_html($value) . '</span>';
    }

    public function column_role($item) {
        $label = $this->formatRoleLabel($item->role);
        $role_badge = '<span class="badge" style="display:inline-flex;align-items:center;gap:4px;background-color:#eef1ff;color:#1d2b6b;padding:3px 8px;border-radius:12px;font-size:12px;">' . esc_html($label) . '</span>';

        $editable = '<span class="mj-editable" data-member-id="' . esc_attr($item->id) . '" data-field-name="role" data-field-type="select" data-field-value="' . esc_attr($item->role) . '" title="Cliquez pour éditer">' . $role_badge . '</span>';

        $extra_badge = '';
        if (!empty($item->is_volunteer)) {
            $extra_badge = '<span class="badge" style="display:inline-flex;align-items:center;gap:4px;background-color:#fff3d6;color:#6b4b1d;padding:3px 8px;border-radius:12px;font-size:12px;">Bénévole</span>';
        }

        $content = $editable . $extra_badge;

        return '<div class="mj-role-cell" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">' . $content . '</div>';
    }

    public function column_guardian($item) {
        if ($item->role !== MjMembers::ROLE_JEUNE) {
            return '<span style="color:#999;">Non applicable</span>';
        }

        if (!empty($item->guardian) && is_object($item->guardian)) {
            $button = $this->buildGuardianButton($item->guardian);

            $contacts = array();
            if (!empty($item->guardian->email)) {
                $contacts[] = '<a href="mailto:' . esc_attr($item->guardian->email) . '">' . esc_html($item->guardian->email) . '</a>';
            }

            if (!empty($item->guardian->phone)) {
                $raw_phone = (string) $item->guardian->phone;
                $tel_href  = preg_replace('/[^0-9+]/', '', $raw_phone);
                $tel_href  = $tel_href !== '' ? $tel_href : $raw_phone;
                $contacts[] = '<a href="tel:' . esc_attr($tel_href) . '">' . esc_html($raw_phone) . '</a>';
            }

            $contact_html = '';
            if (!empty($contacts)) {
                $contact_html = '<div class="mj-guardian-contact">' . implode('<br>', $contacts) . '</div>';
            }

            return '<div class="mj-guardian-cell">' . $button . $contact_html . '</div>';
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

        $suffix = ($item->role === MjMembers::ROLE_TUTEUR) ? ' (paye pour ses jeunes)' : '';

        return '<span class="badge" style="background-color:#28a745;color:#fff;padding:3px 8px;border-radius:12px;font-size:12px;">Obligatoire' . esc_html($suffix) . '</span>';
    }

    public function column_status($item) {
        $is_active = $item->status === MjMembers::STATUS_ACTIVE;
        $class     = $is_active ? 'background-color:#28a745;' : 'background-color:#fd7e14;';
        $label     = $is_active ? 'Actif' : 'Inactif';

        return '<span class="mj-editable badge" data-member-id="' . esc_attr($item->id) . '" data-field-name="status" data-field-type="select" data-field-value="' . esc_attr($item->status) . '" title="Cliquez pour éditer" style="' . esc_attr($class . 'color:#fff;padding:3px 8px;border-radius:12px;font-size:12px;display:inline-block;') . '">' . esc_html($label) . '</span>';
    }

    public function column_date_last_payement($item) {
        return $this->renderLastPaymentValue($item);
    }

    private function renderLastPaymentValue($item) {
        $member_id = isset($item->id) ? (int) $item->id : 0;
        $meta = $this->getPaymentStatusMeta($item);

        $classes = array('mj-editable', 'mj-payment-meta-value');
        if (!$meta['requires'] || $meta['last_iso'] === '') {
            $classes[] = 'mj-payment-meta-value--muted';
        }

        $label_text = '';
        if (!$meta['requires']) {
            $label_text = __('Non concerné', 'mj-member');
        } elseif ($meta['last_display'] === '') {
            $label_text = __('Aucun paiement', 'mj-member');
        } else {
            $label_text = $meta['last_display'];
        }

        $attributes = array(
            'data-member-id'       => (string) $member_id,
            'data-field-name'      => 'date_last_payement',
            'data-field-type'      => 'date',
            'data-field-value'     => $meta['last_iso'],
            'data-requires-payment'=> $meta['requires'] ? '1' : '0',
            'title'                => __('Cliquez pour éditer', 'mj-member'),
            'class'                => implode(' ', $classes),
        );

        $attr_html = '';
        foreach ($attributes as $name => $value) {
            $attr_html .= ' ' . $name . '="' . esc_attr($value) . '"';
        }

        return '<span' . $attr_html . '>' . esc_html($label_text) . '</span>';
    }

    private function buildPaymentSection($item) {
        $status_meta = $this->getPaymentStatusMeta($item);

        $badge_html = '<span class="mj-payment-status-pill mj-payment-status-pill--' . esc_attr($status_meta['modifier']) . '">' . esc_html($status_meta['label']) . '</span>';

        $last_payment_html = '';
        if ($status_meta['requires']) {
            $last_payment_html = '<div class="mj-payment-meta">' . $this->renderLastPaymentValue($item) . '</div>';
        }

        $actions = array();

        if ($status_meta['requires']) {
            $actions[] = '<button type="button" class="mj-member-login-action mj-payment-action mj-payment-action--qr mj-show-qr-btn" data-member-id="' . esc_attr($item->id) . '">⚡ ' . esc_html__('QR paiement', 'mj-member') . '</button>';
            $actions[] = '<button type="button" class="mj-member-login-action mj-payment-action mj-payment-action--mark mj-mark-paid-btn" data-member-id="' . esc_attr($item->id) . '">✅ ' . esc_html__('Marquer payé', 'mj-member') . '</button>';
        }

        $actions[] = '<button type="button" class="mj-member-login-action mj-payment-action mj-payment-action--history mj-payment-history-btn" data-member-id="' . esc_attr($item->id) . '">💳 ' . esc_html__('Historique', 'mj-member') . '</button>';

        $actions_html = '';
        if (!empty($actions)) {
            $actions_html = '<div class="mj-payment-actions">' . implode('', $actions) . '</div>';
        }

        return '<div class="mj-payment-cell"><div class="mj-payment-status">' . $badge_html . '</div>' . $last_payment_html . $actions_html . '</div>';
    }

    public function column_detail($item) {
        $sections = array();

        $identity_parts = array(
            $this->buildDetailNamePart($item, 'first_name'),
            $this->buildDetailNamePart($item, 'last_name'),
        );

        $identity_html = '<div class="mj-detail-identity-name">' . implode(' ', $identity_parts) . '</div>';

        $chips = array();
        $chips[] = $this->buildDetailRoleChip($item);
        $chips[] = $this->buildDetailStatusChip($item);

        $volunteer_chip = $this->buildDetailVolunteerChip($item);
        if ($volunteer_chip !== '') {
            $chips[] = $volunteer_chip;
        }

        $age_chip = $this->buildDetailAgeChip($item);
        if ($age_chip !== '') {
            $chips[] = $age_chip;
        }

        if (!empty($chips)) {
            $identity_html .= '<div class="mj-detail-chips">' . implode('', $chips) . '</div>';
        }

        $contact_entries = array();
        $contact_entries[] = $this->buildDetailContactEntry($item, 'email', '✉️');
        $contact_entries[] = $this->buildDetailContactEntry($item, 'phone', '📱');
        $contact_entries = array_filter($contact_entries);

        $contact_html = !empty($contact_entries)
            ? '<div class="mj-detail-contact">' . implode('', $contact_entries) . '</div>'
            : '<span class="mj-detail-muted">' . esc_html__('Non renseigné', 'mj-member') . '</span>';

        $identity_rows = array(
            $this->renderDetailRow('👤', __('Identité', 'mj-member'), $identity_html),
            $this->renderDetailRow('📞', __('Contact', 'mj-member'), $contact_html),
        );
        $sections[] = $this->renderDetailGrid($identity_rows);

        $finance_rows = array();

        if ($item->role === MjMembers::ROLE_JEUNE) {
            $guardian_html = $this->column_guardian($item);
            $finance_rows[] = $this->renderDetailRow('👥', __('Responsable', 'mj-member'), $guardian_html);
        }

        $summary_html = $this->buildMemberStatusSummary($item);
        if ($summary_html !== '') {
            $finance_rows[] = $this->renderDetailRow('📂', __('Statut & suivi', 'mj-member'), $summary_html);
        }

        $finance_rows = array_filter($finance_rows);
        if (!empty($finance_rows)) {
            $sections[] = $this->renderDetailGrid($finance_rows);
        }

        return '<div class="mj-detail-card">' . implode('', array_filter($sections)) . '</div>';
    }

    public function column_manage($item) {
        $sections = array(
            $this->buildLoginSection($item),
            $this->buildPaymentSection($item),
            $this->buildActionSection($item),
        );

        $sections = array_filter($sections, function ($section) {
            return is_string($section) && trim($section) !== '';
        });

        if (empty($sections)) {
            return '<span style="color:#999;">—</span>';
        }

        $wrapped_sections = array_map(function ($section) {
            return '<div class="mj-manage-card__section">' . $section . '</div>';
        }, $sections);

        return '<div class="mj-manage-cell"><div class="mj-manage-card">' . implode('', $wrapped_sections) . '</div></div>';
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

    private function buildActionSection($item) {
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

        $buttons[] = '<a href="' . esc_url($edit_url) . '" class="mj-member-login-action">✏️ ' . esc_html__('Éditer', 'mj-member') . '</a>';
        $buttons[] = '<a href="' . esc_url($delete_url) . '" class="mj-member-login-action mj-member-login-action--danger" onclick="return confirm(\'Êtes-vous sûr ?\');">🗑️ ' . esc_html__('Supprimer', 'mj-member') . '</a>';

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
        if (empty($buttons)) {
            return '';
        }

        return '<div class="mj-login-actions mj-manage-actions">' . implode('', $buttons) . '</div>';
    }

    public function extra_tablenav($which) {
        if ('top' !== $which) {
            return;
        }

        $filters = $this->activeFilters;
        $role_labels = MjMembers::getRoleLabels();
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
            'detail'              => 'Détail',
            'last_name'           => 'Nom',
            'first_name'          => 'Prénom',
            'age'                 => 'Âge',
            'role'                => 'Rôle',
            'email'               => 'Email',
            'phone'               => 'Téléphone',
            'status'              => 'Statut',
            'manage'              => 'Accès & actions',
            'photo_usage_consent' => 'Consentement photo',
            'date_inscription'    => 'Inscription',
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
            if (in_array($role, array_keys(MjMembers::getRoleLabels()), true)) {
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

        foreach ($members as $index => $member) {
            $user_id = !empty($member->wp_user_id) ? (int) $member->wp_user_id : 0;
            if ($user_id && isset($this->userCache[$user_id]) && $this->userCache[$user_id] instanceof WP_User) {
                $wp_user      = $this->userCache[$user_id];
                $user_payload = array(
                    'wp_user'       => $wp_user,
                    'wp_user_login' => $wp_user->user_login,
                    'wp_user_email' => $wp_user->user_email,
                    'wp_user_roles' => is_array($wp_user->roles) ? $wp_user->roles : array(),
                );

                if ($member instanceof MemberData) {
                    $members[$index] = $member->with($user_payload);
                } else {
                    $member->wp_user = $user_payload['wp_user'];
                    $member->wp_user_login = $user_payload['wp_user_login'];
                    $member->wp_user_email = $user_payload['wp_user_email'];
                    $member->wp_user_roles = $user_payload['wp_user_roles'];
                }
            } else {
                $user_payload = array(
                    'wp_user' => null,
                    'wp_user_login' => '',
                    'wp_user_email' => '',
                    'wp_user_roles' => array(),
                );

                if ($member instanceof MemberData) {
                    $members[$index] = $member->with($user_payload);
                } else {
                    $member->wp_user = null;
                    $member->wp_user_login = '';
                    $member->wp_user_email = '';
                    $member->wp_user_roles = array();
                }
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
            $table = MjMembers::getTableName(MjMembers::TABLE_NAME);
            $placeholders = implode(',', array_fill(0, count($missing_ids), '%d'));
            $sql = "SELECT id, first_name, last_name, email, phone FROM $table WHERE id IN ($placeholders)";
            $sql = call_user_func_array(array($wpdb, 'prepare'), array_merge(array($sql), $missing_ids));
            $results = $wpdb->get_results($sql);

            foreach ($missing_ids as $id) {
                $this->guardianCache[$id] = null;
            }

            foreach ($results as $row) {
                $this->guardianCache[(int) $row->id] = MemberData::fromRow($row);
            }
        }

        foreach ($members as $index => $member) {
            if (!empty($member->guardian_id) && isset($this->guardianCache[(int) $member->guardian_id])) {
                $guardian = $this->guardianCache[(int) $member->guardian_id];
            } else {
                $guardian = null;
            }

            if ($member instanceof MemberData) {
                $members[$index] = $member->with(array('guardian' => $guardian));
            } else {
                $member->guardian = $guardian;
            }
        }
    }

    /**
     * @param object $item
     * @return array{age:int|null, iso:string, formatted:string}
     */
    private function getAgeData($item) {
        $raw_birth = isset($item->birth_date) ? trim((string) $item->birth_date) : '';
        if ($raw_birth === '') {
            return array('age' => null, 'iso' => '', 'formatted' => '');
        }

        $timestamp = strtotime($raw_birth);
        if (!$timestamp) {
            return array('age' => null, 'iso' => '', 'formatted' => '');
        }

        $now = current_time('timestamp');
        $age = (int) floor(($now - $timestamp) / YEAR_IN_SECONDS);
        if ($age < 0) {
            $age = 0;
        }

        return array(
            'age'       => $age,
            'iso'       => gmdate('Y-m-d', $timestamp),
            'formatted' => wp_date('d/m/Y', $timestamp),
        );
    }

    /**
     * @param object $item
     * @return array{requires:bool, modifier:string, label:string, last_display:string, last_iso:string}
     */
    private function getPaymentStatusMeta($item) {
        $requires = !empty($item->requires_payment);
        $modifier = 'muted';
        $label    = __('Non concerné', 'mj-member');

        $raw_value = isset($item->date_last_payement) ? (string) $item->date_last_payement : '';
        $timestamp = $raw_value !== '' ? strtotime($raw_value) : false;
        if ($timestamp === false) {
            $timestamp = null;
        }

        if ($requires) {
            if ($timestamp === null) {
                $modifier = 'danger';
                $label    = __('Non payé', 'mj-member');
            } else {
                $current_time = current_time('timestamp');
                $days_diff    = max(0, (int) floor(($current_time - $timestamp) / DAY_IN_SECONDS));
                $days_display = number_format_i18n($days_diff);

                if ($days_diff < 30) {
                    $modifier = 'success';
                    $label    = sprintf(__('À jour (%s j)', 'mj-member'), $days_display);
                } elseif ($days_diff < 60) {
                    $modifier = 'warning';
                    $label    = sprintf(__('À renouveler (%s j)', 'mj-member'), $days_display);
                } else {
                    $modifier = 'danger';
                    $label    = sprintf(__('Retard (%s j)', 'mj-member'), $days_display);
                }
            }
        }

        $last_display = $timestamp ? wp_date('d/m/Y', $timestamp) : '';
        $last_iso     = $timestamp ? wp_date('Y-m-d', $timestamp) : '';

        return array(
            'requires'     => $requires,
            'modifier'     => $modifier,
            'label'        => $label,
            'last_display' => $last_display,
            'last_iso'     => $last_iso,
        );
    }

    private function buildGuardianButton($guardian) {
        if (!is_object($guardian)) {
            return '';
        }

        $name = trim(((string) ($guardian->first_name ?? '')) . ' ' . ((string) ($guardian->last_name ?? '')));
        if ($name === '') {
            $name = __('Responsable', 'mj-member');
        }

        $attributes = array(
            'type'  => 'button',
            'class' => 'mj-member-login-action mj-guardian-action-btn',
        );

        $guardian_id = isset($guardian->id) ? (int) $guardian->id : 0;
        if ($guardian_id > 0) {
            $attributes['data-guardian-id'] = (string) $guardian_id;
        }

        if (!empty($guardian->email)) {
            $attributes['data-guardian-email'] = (string) $guardian->email;
        }

        if (!empty($guardian->phone)) {
            $attributes['data-guardian-phone'] = (string) $guardian->phone;
        }

        $attr_html = '';
        foreach ($attributes as $attr_name => $attr_value) {
            $attr_html .= ' ' . $attr_name . '="' . esc_attr($attr_value) . '"';
        }

        return '<button' . $attr_html . '>👥 ' . esc_html($name) . '</button>';
    }

    private function renderDetailRow($icon, $label, $valueHtml) {
        $icon_html = '<span class="mj-detail-icon" role="img" aria-label="' . esc_attr($label) . '">' . esc_html($icon) . '</span>';
        $title_attr = ' title="' . esc_attr($label) . '"';
        $row_html = '<div class="mj-detail-row" data-detail-label="' . esc_attr($label) . '"' . $title_attr . '>' . $icon_html . '<div class="mj-detail-value">' . $valueHtml . '</div></div>';
        $label_html = '<span class="mj-detail-label-text">' . esc_html($label) . '</span>';

        return '<div class="mj-detail-row-wrapper" data-detail-label="' . esc_attr($label) . '">' . $label_html . $row_html . '</div>';
    }

    private function buildDetailNamePart($item, $field) {
        $member_id = isset($item->id) ? (int) $item->id : 0;
        $raw_value = isset($item->$field) ? trim((string) $item->$field) : '';
        $is_empty = ($raw_value === '');

        $placeholders = array(
            'first_name' => __('Ajouter un prénom', 'mj-member'),
            'last_name'  => __('Ajouter un nom', 'mj-member'),
        );

        $display_value = $is_empty ? ($placeholders[$field] ?? 'N/A') : $raw_value;

        $attributes = array(
            'class'            => 'mj-editable mj-detail-name-part' . ($is_empty ? ' mj-detail-name-part--empty' : ''),
            'data-member-id'   => (string) $member_id,
            'data-field-name'  => $field,
            'title'            => __('Cliquez pour éditer', 'mj-member'),
        );

        $attr_html = '';
        foreach ($attributes as $attr_name => $attr_value) {
            if ($attr_value === '') {
                continue;
            }
            $attr_html .= ' ' . $attr_name . '="' . esc_attr($attr_value) . '"';
        }

        return '<span' . $attr_html . '>' . esc_html($display_value) . '</span>';
    }

    private function buildDetailRoleChip($item) {
        $member_id = isset($item->id) ? (int) $item->id : 0;
        $raw_role = isset($item->role) ? (string) $item->role : '';
        $is_empty = ($raw_role === '');
        $label = $is_empty ? __('Choisir un rôle', 'mj-member') : $this->formatRoleLabel($raw_role);

        $attributes = array(
            'class'              => 'mj-editable mj-detail-chip mj-detail-chip--interactive' . ($is_empty ? ' mj-detail-chip--empty' : ''),
            'data-member-id'     => (string) $member_id,
            'data-field-name'    => 'role',
            'data-field-type'    => 'select',
            'data-field-value'   => $raw_role,
            'title'              => __('Cliquez pour éditer', 'mj-member'),
        );

        $attr_html = '';
        foreach ($attributes as $attr_name => $attr_value) {
            if ($attr_value === '') {
                continue;
            }
            $attr_html .= ' ' . $attr_name . '="' . esc_attr($attr_value) . '"';
        }

        return '<span' . $attr_html . '>' . esc_html($label) . '</span>';
    }

    private function buildDetailVolunteerChip($item) {
        $member_id = isset($item->id) ? (int) $item->id : 0;
        $raw_value = isset($item->is_volunteer) ? (int) $item->is_volunteer : 0;
        $is_volunteer = ($raw_value === 1);

        $modifier = $is_volunteer ? 'success' : 'muted';
        $label = $is_volunteer ? __('Bénévole', 'mj-member') : __('Non bénévole', 'mj-member');

        $classes = array('mj-editable', 'mj-detail-chip', 'mj-detail-chip--interactive', 'mj-detail-chip--' . $modifier);

        $attributes = array(
            'class'            => implode(' ', $classes),
            'data-member-id'   => (string) $member_id,
            'data-field-name'  => 'is_volunteer',
            'data-field-type'  => 'select',
            'data-field-value' => $is_volunteer ? '1' : '0',
            'title'            => __('Cliquez pour éditer', 'mj-member'),
        );

        $attr_html = '';
        foreach ($attributes as $attr_name => $attr_value) {
            if ($attr_value === '') {
                continue;
            }
            $attr_html .= ' ' . $attr_name . '="' . esc_attr($attr_value) . '"';
        }

        return '<span' . $attr_html . '>' . esc_html($label) . '</span>';
    }

    private function buildDetailStatusChip($item) {
        $member_id = isset($item->id) ? (int) $item->id : 0;
        $raw_status = isset($item->status) ? (string) $item->status : '';
        $is_active = ($raw_status === MjMembers::STATUS_ACTIVE);
        $modifier = $is_active ? 'success' : 'warning';
        $label = $is_active ? __('Actif', 'mj-member') : __('Inactif', 'mj-member');
        if ($raw_status === '') {
            $label = __('Définir le statut', 'mj-member');
            $modifier = 'muted';
        }

        $classes = array('mj-editable', 'mj-detail-chip', 'mj-detail-chip--interactive', 'mj-detail-chip--' . $modifier);
        if ($raw_status === '') {
            $classes[] = 'mj-detail-chip--empty';
        }

        $attributes = array(
            'class'            => implode(' ', $classes),
            'data-member-id'   => (string) $member_id,
            'data-field-name'  => 'status',
            'data-field-type'  => 'select',
            'data-field-value' => $raw_status,
            'title'            => __('Cliquez pour éditer', 'mj-member'),
        );

        $attr_html = '';
        foreach ($attributes as $attr_name => $attr_value) {
            if ($attr_value === '') {
                continue;
            }
            $attr_html .= ' ' . $attr_name . '="' . esc_attr($attr_value) . '"';
        }

        return '<span' . $attr_html . '>' . esc_html($label) . '</span>';
    }

    private function buildDetailAgeChip($item) {
        $member_id = isset($item->id) ? (int) $item->id : 0;
        $age_data = $this->getAgeData($item);

        $has_birth_date = ($age_data['iso'] !== '');

        $label_parts = array();
        if ($age_data['age'] !== null) {
            $label_parts[] = sprintf(_n('%d an', '%d ans', $age_data['age'], 'mj-member'), $age_data['age']);
        }
        if ($age_data['formatted'] !== '') {
            $label_parts[] = $age_data['formatted'];
        }

        if (empty($label_parts)) {
            $display = __('Définir la date de naissance', 'mj-member');
        } else {
            $display = implode(' - ', $label_parts);
        }

        $classes = array('mj-editable', 'mj-detail-chip', 'mj-detail-chip--interactive', 'mj-detail-chip--age');
        if (!$has_birth_date) {
            $classes[] = 'mj-detail-chip--empty';
        }

        $attributes = array(
            'class'            => implode(' ', $classes),
            'data-member-id'   => (string) $member_id,
            'data-field-name'  => 'birth_date',
            'data-field-type'  => 'date',
            'data-field-value' => $age_data['iso'],
            'title'            => __('Cliquez pour éditer', 'mj-member'),
        );

        if (!$has_birth_date) {
            $attributes['data-field-value'] = '';
        }

        $attr_html = '';
        foreach ($attributes as $attr_name => $attr_value) {
            if ($attr_value === '') {
                continue;
            }
            $attr_html .= ' ' . $attr_name . '="' . esc_attr($attr_value) . '"';
        }

        return '<span' . $attr_html . '>' . esc_html($display) . '</span>';
    }

    private function buildDetailContactEntry($item, $field, $icon) {
        $member_id = isset($item->id) ? (int) $item->id : 0;
        $raw_value = isset($item->$field) ? trim((string) $item->$field) : '';

        $placeholders = array(
            'email' => __('Ajouter un email', 'mj-member'),
            'phone' => __('Ajouter un téléphone', 'mj-member'),
        );

        $field_type = $field === 'email' ? 'email' : 'text';
        $is_empty = ($raw_value === '');
        $display_value = $is_empty ? ($placeholders[$field] ?? 'N/A') : $raw_value;

        $classes = array('mj-editable', 'mj-detail-contact-pill');
        if ($is_empty) {
            $classes[] = 'mj-detail-contact-pill--empty';
        }

        $attributes = array(
            'class'            => implode(' ', $classes),
            'data-member-id'   => (string) $member_id,
            'data-field-name'  => $field,
            'data-field-type'  => $field_type,
            'title'            => __('Cliquez pour éditer', 'mj-member'),
        );

        if (!$is_empty) {
            if ($field === 'email') {
                $attributes['data-field-value'] = $raw_value;
            } elseif ($field === 'phone') {
                $attributes['data-field-value'] = $raw_value;
            }
        } else {
            $attributes['data-field-value'] = '';
        }

        $attr_html = '';
        foreach ($attributes as $attr_name => $attr_value) {
            if ($attr_value === '') {
                continue;
            }
            $attr_html .= ' ' . $attr_name . '="' . esc_attr($attr_value) . '"';
        }

        $icon_html = '<span class="mj-detail-contact-icon">' . esc_html($icon) . '</span>';
        $text_html = '<span class="mj-detail-contact-text">' . esc_html($display_value) . '</span>';

        return '<div class="mj-detail-contact-item"><span' . $attr_html . '>' . $icon_html . $text_html . '</span></div>';
    }

    private function renderDetailGrid(array $rows) {
        if (empty($rows)) {
            return '';
        }

        return '<div class="mj-detail-grid">' . implode('', $rows) . '</div>';
    }

    private function formatDetailDateValue($rawValue, $format, $emptyText) {
        $text = $this->formatDetailDateText($rawValue, $format, $emptyText);
        if ($text === $emptyText) {
            return '<span class="mj-detail-muted">' . esc_html($text) . '</span>';
        }

        return '<span class="mj-detail-meta">' . esc_html($text) . '</span>';
    }

    private function buildMemberStatusSummary($item) {
        $summary_items = array();

        $requires_payment = !empty($item->requires_payment);
        $cotisation_text = $requires_payment
            ? esc_html__('Cotisation : Obligatoire', 'mj-member')
            : esc_html__('Cotisation : Dispensé', 'mj-member');

        if ($requires_payment && $item->role === MjMembers::ROLE_TUTEUR) {
            $cotisation_text .= ' ' . esc_html__('(paye pour ses jeunes)', 'mj-member');
        }

        $summary_items[] = $this->buildSummaryChip('🎟️', $cotisation_text, $requires_payment ? 'warning' : 'muted');

        $has_consent = !empty($item->photo_usage_consent);
        $summary_items[] = $this->buildSummaryChip(
            '📸',
            $has_consent ? esc_html__('Consentement photo : Accepté', 'mj-member') : esc_html__('Consentement photo : Refusé', 'mj-member'),
            $has_consent ? 'success' : 'danger'
        );

        $payment_meta = $this->getPaymentStatusMeta($item);
        $summary_items[] = $this->buildSummaryChip(
            '💶',
            sprintf(esc_html__('Cotisation : %s', 'mj-member'), $payment_meta['label']),
            $payment_meta['modifier']
        );

        $created_raw = $this->resolveMemberCreatedAt($item);
        $created_text = $this->formatDetailDateText($created_raw, 'd/m/Y', esc_html__('Non renseigné', 'mj-member'));
        $summary_items[] = $this->buildSummaryChip(
            '🗓️',
            sprintf(esc_html__('Créé le : %s', 'mj-member'), $created_text),
            $created_text === esc_html__('Non renseigné', 'mj-member') ? 'muted' : 'info'
        );

        $never_text = esc_html__('Jamais', 'mj-member');
        $updated_raw = $this->resolveMemberUpdatedAt($item, $created_raw);
        $updated_text = $this->formatDetailDateText($updated_raw, 'd/m/Y H:i', $never_text);
        $summary_items[] = $this->buildSummaryChip(
            '♻️',
            sprintf(esc_html__('Mis à jour : %s', 'mj-member'), $updated_text),
            $updated_text === $never_text ? 'muted' : 'info'
        );

        $summary_items = array_filter($summary_items);
        if (empty($summary_items)) {
            return '';
        }

        return '<div class="mj-detail-summary-group" style="display:flex;flex-wrap:wrap;gap:8px;">' . implode('', $summary_items) . '</div>';
    }

    private function buildSummaryChip($icon, $label, $theme = 'neutral') {
        if ($label === '') {
            return '';
        }

        $palette = array(
            'success' => array('#e6f4ea', '#1d6f42'),
            'danger'  => array('#fdecea', '#a4251c'),
            'warning' => array('#fff4e5', '#8a5200'),
            'info'    => array('#e7f0ff', '#1d4f8a'),
            'muted'   => array('#f3f4f6', '#374151'),
            'neutral' => array('#eef1f4', '#1f2933'),
        );

        $theme_key = array_key_exists($theme, $palette) ? $theme : 'neutral';
        $colors = $palette[$theme_key];

        $style = sprintf(
            'display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:999px;font-size:12px;background-color:%s;color:%s;',
            $colors[0],
            $colors[1]
        );

        $icon_html = $icon !== '' ? '<span class="mj-detail-summary-icon" aria-hidden="true">' . esc_html($icon) . '</span>' : '';
        $text_html = '<span class="mj-detail-summary-text">' . esc_html($label) . '</span>';

        return '<span class="mj-detail-summary-chip" style="' . esc_attr($style) . '">' . $icon_html . $text_html . '</span>';
    }

    private function formatDetailDateText($rawValue, $format, $emptyText) {
        if ($rawValue === '' || $this->isZeroDateValue($rawValue)) {
            return $emptyText;
        }

        $timestamp = strtotime($rawValue);
        if (!$timestamp) {
            return $emptyText;
        }

        return wp_date($format, $timestamp);
    }

    private function resolveMemberCreatedAt($item) {
        $candidates = array('date_inscription', 'joined_date', 'created_at');
        foreach ($candidates as $field) {
            if (isset($item->$field) && !$this->isZeroDateValue($item->$field)) {
                return (string) $item->$field;
            }
        }

        return '';
    }

    private function resolveMemberUpdatedAt($item, $createdRaw) {
        $candidates = array('updated_at', 'modified_at', 'last_modified', 'last_update');
        foreach ($candidates as $field) {
            if (isset($item->$field) && !$this->isZeroDateValue($item->$field)) {
                return (string) $item->$field;
            }
        }

        if (isset($item->joined_date) && !$this->isZeroDateValue($item->joined_date)) {
            $joined_raw = (string) $item->joined_date;
            if ($createdRaw === '' || $joined_raw !== $createdRaw) {
                return $joined_raw;
            }
        }

        return '';
    }

    private function isZeroDateValue($value) {
        if ($value === null) {
            return true;
        }

        $normalized = trim((string) $value);
        if ($normalized === '') {
            return true;
        }

        return in_array($normalized, array('0000-00-00', '0000-00-00 00:00:00'), true);
    }

    private function buildDetailPhotoConsent($item) {
        $member_id = isset($item->id) ? (int) $item->id : 0;
        $raw_value = !empty($item->photo_usage_consent);

        $label = $raw_value ? __('Autorisé', 'mj-member') : __('Refusé', 'mj-member');
        $modifier = $raw_value ? 'success' : 'danger';

        $classes = array('mj-editable', 'mj-detail-chip', 'mj-detail-chip--interactive', 'mj-detail-chip--' . $modifier);

        $attributes = array(
            'class'            => implode(' ', $classes),
            'data-member-id'   => (string) $member_id,
            'data-field-name'  => 'photo_usage_consent',
            'data-field-type'  => 'toggle',
            'data-field-value' => $raw_value ? '1' : '0',
            'title'            => __('Cliquez pour éditer', 'mj-member'),
        );

        $attr_html = '';
        foreach ($attributes as $attr_name => $attr_value) {
            if ($attr_value === '') {
                continue;
            }
            $attr_html .= ' ' . $attr_name . '="' . esc_attr($attr_value) . '"';
        }

        return '<div class="mj-detail-photo-consent"><span' . $attr_html . '>' . esc_html($label) . '</span></div>';
    }

    private function formatRoleLabel($role) {
        switch ($role) {
            case MjMembers::ROLE_JEUNE:
                return 'Jeune';
            case MjMembers::ROLE_ANIMATEUR:
                return 'Animateur';
            case MjMembers::ROLE_COORDINATEUR:
                return 'Coordinateur';
            case MjMembers::ROLE_BENEVOLE:
                return 'Bénévole';
            case MjMembers::ROLE_TUTEUR:
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
