<?php

use Elementor\Controls_Manager;
use Elementor\Widget_Base;
use Mj\Member\Classes\Crud\MjEvents;
use Mj\Member\Classes\Crud\MjMembers;
use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

class Mj_Member_Elementor_Payments_Widget extends Widget_Base
{
    use Mj_Member_Elementor_Widget_Visibility;

    public function get_name()
    {
        return 'mj-member-payments-overview';
    }

    public function get_title()
    {
        return __('MJ – Paiements', 'mj-member');
    }

    public function get_icon()
    {
        return 'eicon-checkout';
    }

    public function get_categories()
    {
        return array('mj-member');
    }

    public function get_keywords()
    {
        return array('paiement', 'payments', 'stripe', 'mj', 'cotisation');
    }

    protected function register_controls()
    {
        $this->start_controls_section(
            'section_content',
            array(
                'label' => __('Contenu', 'mj-member'),
            )
        );

        $this->add_control(
            'title',
            array(
                'label' => __('Titre', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Suivi des paiements', 'mj-member'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'intro_text',
            array(
                'label' => __('Texte introductif', 'mj-member'),
                'type' => Controls_Manager::TEXTAREA,
                'rows' => 3,
                'placeholder' => __('Expliquez comment finaliser ou consulter les règlements.', 'mj-member'),
            )
        );

        $this->add_control(
            'display_mode',
            array(
                'label' => __('Présentation', 'mj-member'),
                'type' => Controls_Manager::SELECT,
                'default' => 'tabs',
                'options' => array(
                    'tabs' => __('Onglets (confirmés / en attente)', 'mj-member'),
                    'stack' => __('Sections empilées', 'mj-member'),
                ),
            )
        );

        $this->add_control(
            'include_children',
            array(
                'label' => __('Inclure mes jeunes', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->add_control(
            'confirmed_limit',
            array(
                'label' => __('Paiements confirmés à afficher', 'mj-member'),
                'type' => Controls_Manager::NUMBER,
                'min' => 1,
                'max' => 50,
                'step' => 1,
                'default' => 5,
            )
        );

        $this->add_control(
            'pending_limit',
            array(
                'label' => __('Paiements en attente à afficher', 'mj-member'),
                'type' => Controls_Manager::NUMBER,
                'min' => 1,
                'max' => 50,
                'step' => 1,
                'default' => 5,
            )
        );

        $this->add_control(
            'empty_confirmed_message',
            array(
                'label' => __('Message si aucun paiement confirmé', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Aucun paiement confirmé pour le moment.', 'mj-member'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'empty_pending_message',
            array(
                'label' => __('Message si aucun paiement en attente', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Aucun paiement en attente.', 'mj-member'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'show_context',
            array(
                'label' => __('Afficher le contexte du paiement', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->add_control(
            'show_reference',
            array(
                'label' => __('Afficher la référence', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'no',
            )
        );

        $this->end_controls_section();

        $this->register_visibility_controls();

        $this->start_controls_section(
            'section_style',
            array(
                'label' => __('Mise en forme', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'card_background',
            array(
                'label' => __('Fond du widget', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-payments-overview' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'accent_color',
            array(
                'label' => __('Couleur accent', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-payments-overview__tab.is-active' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .mj-payments-overview__badge--confirmed' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_section();
    }

    protected function render()
    {
        $settings = $this->get_settings_for_display();
        $this->apply_visibility_to_wrapper($settings, 'mj-payments-overview');

        $template_path = Config::path() . 'includes/templates/elementor/payments_overview.php';
        if (!is_readable($template_path)) {
            echo '<div class="mj-member-account-warning">' . esc_html__('Le template de paiements est introuvable.', 'mj-member') . '</div>';
            return;
        }

        $is_preview = function_exists('is_elementor_preview') && is_elementor_preview();
        $template_data = $this->prepare_template_data($settings, $is_preview);

        $widget = $this;
        include $template_path;
    }

    private function prepare_template_data(array $settings, $is_preview)
    {
        $display_mode = isset($settings['display_mode']) ? sanitize_key((string) $settings['display_mode']) : 'tabs';
        if (!in_array($display_mode, array('tabs', 'stack'), true)) {
            $display_mode = 'tabs';
        }

        $include_children = !empty($settings['include_children']) && $settings['include_children'] === 'yes';
        $confirmed_limit = isset($settings['confirmed_limit']) ? max(1, (int) $settings['confirmed_limit']) : 5;
        $pending_limit = isset($settings['pending_limit']) ? max(1, (int) $settings['pending_limit']) : 5;
        $show_context = !empty($settings['show_context']) && $settings['show_context'] === 'yes';
        $show_reference = !empty($settings['show_reference']) && $settings['show_reference'] === 'yes';

        if ($is_preview || !is_user_logged_in() || !function_exists('mj_member_get_current_member')) {
            return $this->build_preview_data(
                $settings,
                array(
                    'display_mode' => $display_mode,
                    'show_context' => $show_context,
                    'show_reference' => $show_reference,
                    'confirmed_limit' => $confirmed_limit,
                    'pending_limit' => $pending_limit,
                )
            );
        }

        $member = mj_member_get_current_member();
        if (!$member) {
            return $this->build_logged_out_data($settings, $display_mode);
        }

        $members_index = $this->collect_members($member, $include_children);
        if (empty($members_index)) {
            $members_index[(int) $member->id] = $this->format_member_summary($member, 'primary');
        }

        $dataset = $this->collect_payments_dataset(
            $members_index,
            $confirmed_limit,
            $pending_limit,
            array(
                'show_context' => $show_context,
                'show_reference' => $show_reference,
            )
        );

        return array(
            'title' => isset($settings['title']) ? sanitize_text_field((string) $settings['title']) : '',
            'intro' => isset($settings['intro_text']) ? wp_kses_post((string) $settings['intro_text']) : '',
            'display_mode' => $display_mode,
            'messages' => array(
                'empty_confirmed' => isset($settings['empty_confirmed_message']) ? sanitize_text_field((string) $settings['empty_confirmed_message']) : '',
                'empty_pending' => isset($settings['empty_pending_message']) ? sanitize_text_field((string) $settings['empty_pending_message']) : '',
                'not_logged_in' => __('Connectez-vous pour consulter vos paiements.', 'mj-member'),
                'view_qr' => __('Voir le QR code', 'mj-member'),
                'qr_modal_title' => __('Payer avec le QR code', 'mj-member'),
                'qr_modal_hint' => __('Scannez ce code ou ouvrez le lien pour finaliser le paiement.', 'mj-member'),
                'qr_modal_cta' => __('Ouvrir le lien de paiement', 'mj-member'),
                'qr_modal_close' => __('Fermer', 'mj-member'),
                'qr_modal_image_alt' => __('QR code du paiement', 'mj-member'),
            ),
            'options' => array(
                'show_context' => $show_context,
                'show_reference' => $show_reference,
            ),
            'viewer' => array(
                'can_manage_children' => function_exists('mj_member_can_manage_children') ? mj_member_can_manage_children($member) : false,
            ),
            'members' => array_values($members_index),
            'confirmed' => $dataset['confirmed'],
            'pending' => $dataset['pending'],
        );
    }

    private function collect_members($member, $include_children)
    {
        $members = array();
        if ($member && isset($member->id)) {
            $members[(int) $member->id] = $this->format_member_summary($member, 'primary');
        }

        if (!$include_children || !$member || !function_exists('mj_member_can_manage_children') || !mj_member_can_manage_children($member)) {
            return $members;
        }

        if (!function_exists('mj_member_get_guardian_children')) {
            return $members;
        }

        $children = mj_member_get_guardian_children($member);
        if (empty($children) || !is_array($children)) {
            return $members;
        }

        foreach ($children as $child) {
            if (!$child || !isset($child->id)) {
                continue;
            }
            $members[(int) $child->id] = $this->format_member_summary($child, 'child');
        }

        return $members;
    }

    private function format_member_summary($member, $type)
    {
        $member_id = isset($member->id) ? (int) $member->id : 0;
        $first = isset($member->first_name) ? sanitize_text_field((string) $member->first_name) : '';
        $last = isset($member->last_name) ? sanitize_text_field((string) $member->last_name) : '';
        $nickname = isset($member->nickname) ? sanitize_text_field((string) $member->nickname) : '';
        $full_name = trim($first . ' ' . $last);
        if ($full_name === '') {
            $full_name = $nickname !== '' ? $nickname : sprintf(__('Membre #%d', 'mj-member'), $member_id);
        }

        $role = isset($member->role) ? sanitize_key((string) $member->role) : '';
        $role_labels = MjMembers::getRoleLabels();
        $role_label = isset($role_labels[$role]) ? $role_labels[$role] : '';
        if ($role_label !== '') {
            $role_label = __($role_label, 'mj-member');
        }

        return array(
            'id' => $member_id,
            'label' => $full_name,
            'role' => $role,
            'role_label' => $role_label,
            'type' => $type === 'child' ? 'child' : 'primary',
        );
    }

    private function collect_payments_dataset(array $members_index, $confirmed_limit, $pending_limit, array $options)
    {
        $member_ids = array_keys($members_index);
        $fetch_multiplier = max(1, count($member_ids));

        $confirmed_rows = $this->query_confirmed_rows($member_ids, $confirmed_limit * $fetch_multiplier);
        $pending_rows = $this->query_pending_rows($member_ids, $pending_limit * $fetch_multiplier);

        $confirmed_entries = $this->normalize_confirmed_entries($confirmed_rows, $members_index, $confirmed_limit);
        $pending_entries = $this->normalize_pending_entries($pending_rows, $members_index, $pending_limit, $options);

        return array(
            'confirmed' => array(
                'entries' => $confirmed_entries['entries'],
                'count' => $confirmed_entries['count'],
                'total_amount' => $confirmed_entries['total'],
            ),
            'pending' => array(
                'entries' => $pending_entries['entries'],
                'count' => $pending_entries['count'],
                'total_amount' => $pending_entries['total'],
            ),
        );
    }

    private function query_confirmed_rows(array $member_ids, $limit)
    {
        if (empty($member_ids)) {
            return array();
        }

        $wpdb = MjMembers::getWpdb();
        $history_table = $wpdb->prefix . 'mj_payment_history';

        $placeholders = implode(',', array_fill(0, count($member_ids), '%d'));
        $sql = "SELECT id, member_id, amount, payment_date, method, reference FROM {$history_table} WHERE member_id IN ({$placeholders}) ORDER BY payment_date DESC LIMIT %d";

        $params = $member_ids;
        $params[] = max(1, (int) $limit);

        $prepared = call_user_func_array(array($wpdb, 'prepare'), array_merge(array($sql), $params));
        $rows = $wpdb->get_results($prepared);

        return is_array($rows) ? $rows : array();
    }

    private function query_pending_rows(array $member_ids, $limit)
    {
        if (empty($member_ids)) {
            return array();
        }

        $wpdb = MjMembers::getWpdb();
        $payments_table = $wpdb->prefix . 'mj_payments';

        $placeholders = implode(',', array_fill(0, count($member_ids), '%d'));
        $sql = "SELECT id, member_id, payer_id, event_id, registration_id, amount, status, context, created_at, external_ref, checkout_url, token, paid_at FROM {$payments_table} WHERE member_id IN ({$placeholders}) ORDER BY created_at DESC LIMIT %d";

        $params = $member_ids;
        $params[] = max(1, (int) $limit);

        $prepared = call_user_func_array(array($wpdb, 'prepare'), array_merge(array($sql), $params));
        $rows = $wpdb->get_results($prepared);

        return is_array($rows) ? $rows : array();
    }

    private function normalize_confirmed_entries(array $rows, array $members_index, $limit)
    {
        $entries = array();
        $date_format = get_option('date_format', 'd/m/Y');

        foreach ($rows as $row) {
            $member_id = (int) $row->member_id;
            if (!isset($members_index[$member_id])) {
                continue;
            }

            $timestamp = !empty($row->payment_date) ? strtotime((string) $row->payment_date) : 0;
            $amount = isset($row->amount) ? (float) $row->amount : 0.0;

            $entries[] = array(
                'id' => (int) $row->id,
                'member_id' => $member_id,
                'member_label' => $members_index[$member_id]['label'],
                'member_role_label' => $members_index[$member_id]['role_label'],
                'amount' => $amount,
                'amount_display' => number_format_i18n($amount, 2),
                'date_display' => $timestamp ? date_i18n($date_format, $timestamp) : __('Date inconnue', 'mj-member'),
                'timestamp' => $timestamp,
                'method_label' => !empty($row->method) ? sanitize_text_field((string) $row->method) : __('Paiement enregistré', 'mj-member'),
                'reference' => !empty($row->reference) ? sanitize_text_field((string) $row->reference) : '',
                'status' => 'paid',
                'status_label' => __('Paiement confirmé', 'mj-member'),
            );
        }

        usort($entries, static function ($a, $b) {
            $tsA = isset($a['timestamp']) ? (int) $a['timestamp'] : 0;
            $tsB = isset($b['timestamp']) ? (int) $b['timestamp'] : 0;
            if ($tsA === $tsB) {
                return 0;
            }
            return ($tsA > $tsB) ? -1 : 1;
        });

        $entries = array_slice($entries, 0, max(1, (int) $limit));

        $total = 0.0;
        foreach ($entries as $entry) {
            $total += isset($entry['amount']) ? (float) $entry['amount'] : 0.0;
        }

        return array(
            'entries' => $entries,
            'count' => count($entries),
            'total' => $total,
        );
    }

    private function normalize_pending_entries(array $rows, array $members_index, $limit, array $options)
    {
        $entries = array();
        $date_format = get_option('date_format', 'd/m/Y');
        $pending_statuses = array('pending', 'requires_payment_method', 'requires_action', 'processing', 'incomplete', 'open');
        $ignored_statuses = array('paid', 'succeeded', 'completed', 'canceled', 'cancelled', 'failed', 'requires_payment_method_failed', 'refunded');

        $event_titles = $this->preload_events_for_rows($rows);

        foreach ($rows as $row) {
            $member_id = (int) $row->member_id;
            if (!isset($members_index[$member_id])) {
                continue;
            }

            $status = isset($row->status) ? sanitize_key((string) $row->status) : 'pending';
            if (in_array($status, $ignored_statuses, true)) {
                continue;
            }
            if (!in_array($status, $pending_statuses, true) && !empty($row->paid_at)) {
                continue;
            }

            $amount = isset($row->amount) ? (float) $row->amount : 0.0;

            $created_ts = !empty($row->created_at) ? strtotime((string) $row->created_at) : 0;
            $status_label = function_exists('mj_format_payment_status') ? mj_format_payment_status($status) : __('Paiement en attente', 'mj-member');
            $context_key = isset($row->context) ? sanitize_key((string) $row->context) : '';
            $event_title = (!empty($row->event_id) && isset($event_titles[(int) $row->event_id])) ? $event_titles[(int) $row->event_id] : '';
            $context_label = $this->format_context_label($context_key, $event_title);

            $checkout_url = '';
            if (!empty($row->checkout_url)) {
                $checkout_url = esc_url((string) $row->checkout_url);
            }

            if ($checkout_url === '' && !empty($row->token)) {
                $token = sanitize_text_field((string) $row->token);
                if ($token !== '') {
                    $checkout_url = esc_url(add_query_arg(array('mj_payment_confirm' => $token), site_url('/')));
                }
            }

            $qr_url = $checkout_url !== '' ? $this->build_qr_url($checkout_url) : '';

            $entries[] = array(
                'id' => (int) $row->id,
                'member_id' => $member_id,
                'member_label' => $members_index[$member_id]['label'],
                'member_role_label' => $members_index[$member_id]['role_label'],
                'amount' => $amount,
                'amount_display' => number_format_i18n($amount, 2),
                'created_display' => $created_ts ? date_i18n($date_format, $created_ts) : __('Non daté', 'mj-member'),
                'timestamp' => $created_ts,
                'status' => $status,
                'status_label' => $status_label,
                'context' => $context_key,
                'context_label' => $context_label,
                'reference' => !empty($row->external_ref) ? sanitize_text_field((string) $row->external_ref) : '',
                'checkout_url' => $checkout_url,
                'qr_url' => $qr_url,
                'show_context' => !empty($options['show_context']),
                'show_reference' => !empty($options['show_reference']),
            );
        }

        usort($entries, static function ($a, $b) {
            $tsA = isset($a['timestamp']) ? (int) $a['timestamp'] : 0;
            $tsB = isset($b['timestamp']) ? (int) $b['timestamp'] : 0;
            if ($tsA === $tsB) {
                return 0;
            }
            return ($tsA > $tsB) ? -1 : 1;
        });

        $entries = array_slice($entries, 0, max(1, (int) $limit));

        $total = 0.0;
        foreach ($entries as $entry) {
            $total += isset($entry['amount']) ? (float) $entry['amount'] : 0.0;
        }

        return array(
            'entries' => $entries,
            'count' => count($entries),
            'total' => $total,
        );
    }

    private function build_qr_url($url)
    {
        if (!is_string($url)) {
            if (is_scalar($url)) {
                $url = (string) $url;
            } else {
                return '';
            }
        }

        $url = trim($url);
        if ($url === '') {
            return '';
        }

        $encoded = rawurlencode($url);
        $default = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data={$encoded}&format=png&ecc=M&margin=0";
        $filtered = apply_filters('mj_member_payments_widget_qr_url', $default, $url, $this);

        return esc_url($filtered !== '' ? $filtered : $default);
    }

    private function preload_events_for_rows(array $rows)
    {
        if (empty($rows) || !class_exists(MjEvents::class)) {
            return array();
        }

        $event_ids = array();
        foreach ($rows as $row) {
            if (!empty($row->event_id)) {
                $event_ids[(int) $row->event_id] = (int) $row->event_id;
            }
        }

        if (empty($event_ids)) {
            return array();
        }

        $titles = array();
        foreach ($event_ids as $event_id) {
            $event = MjEvents::find($event_id);
            if ($event && isset($event->title)) {
                $titles[$event_id] = sanitize_text_field((string) $event->title);
            }
        }

        return $titles;
    }

    private function format_context_label($context, $event_title)
    {
        switch ($context) {
            case 'event':
                return $event_title !== ''
                    ? sprintf(__('Paiement événement – %s', 'mj-member'), $event_title)
                    : __('Paiement événement', 'mj-member');
            case 'registration':
                return __('Inscription événement', 'mj-member');
            case 'membership':
                return __('Cotisation MJ', 'mj-member');
            case 'donation':
                return __('Don MJ', 'mj-member');
            default:
                if ($context === '') {
                    return __('Paiement MJ', 'mj-member');
                }
                $context_clean = str_replace('_', ' ', $context);
                return ucfirst($context_clean);
        }
    }

    private function build_preview_data(array $settings, array $options)
    {
        $pending_label = function_exists('mj_format_payment_status') ? mj_format_payment_status('pending') : __('Paiement en attente', 'mj-member');
        $action_label = function_exists('mj_format_payment_status') ? mj_format_payment_status('requires_action') : __('Paiement en attente', 'mj-member');

        // Utiliser MjRoles pour les labels
        $labelJeune = class_exists('Mj\\Member\\Classes\\MjRoles') 
            ? \Mj\Member\Classes\MjRoles::getRoleLabel(\Mj\Member\Classes\MjRoles::JEUNE) 
            : __('Jeune', 'mj-member');

        $sample_confirmed = array(
            array(
                'id' => 1,
                'member_id' => 101,
                'member_label' => __('Alex Martin', 'mj-member'),
                'member_role_label' => $labelJeune,
                'amount' => 20.0,
                'amount_display' => number_format_i18n(20.0, 2),
                'date_display' => date_i18n(get_option('date_format', 'd/m/Y'), strtotime('-12 days')),
                'timestamp' => strtotime('-12 days'),
                'method_label' => __('Stripe', 'mj-member'),
                'reference' => 'cs_test_preview_1',
                'status' => 'paid',
                'status_label' => __('Paiement confirmé', 'mj-member'),
            ),
            array(
                'id' => 2,
                'member_id' => 102,
                'member_label' => __('Lina Martin', 'mj-member'),
                'member_role_label' => $labelJeune,
                'amount' => 10.0,
                'amount_display' => number_format_i18n(10.0, 2),
                'date_display' => date_i18n(get_option('date_format', 'd/m/Y'), strtotime('-32 days')),
                'timestamp' => strtotime('-32 days'),
                'method_label' => __('QR code', 'mj-member'),
                'reference' => 'hist_preview_2',
                'status' => 'paid',
                'status_label' => __('Paiement confirmé', 'mj-member'),
            ),
        );

        $sample_pending = array(
            array(
                'id' => 41,
                'member_id' => 101,
                'member_label' => __('Alex Martin', 'mj-member'),
                'member_role_label' => $labelJeune,
                'amount' => 25.0,
                'amount_display' => number_format_i18n(25.0, 2),
                'created_display' => date_i18n(get_option('date_format', 'd/m/Y'), strtotime('-2 days')),
                'timestamp' => strtotime('-2 days'),
                'status' => 'pending',
                'status_label' => $pending_label,
                'context' => 'event',
                'context_label' => sprintf(__('Paiement événement – %s', 'mj-member'), __('Atelier Graffiti', 'mj-member')),
                'reference' => 'cs_test_preview_pending',
                'show_context' => !empty($options['show_context']),
                'show_reference' => !empty($options['show_reference']),
                'checkout_url' => esc_url(home_url('/paiement/inscription-jeune')),
                'qr_url' => esc_url($this->build_qr_url(home_url('/paiement/inscription-jeune'))),
            ),
            array(
                'id' => 42,
                'member_id' => 201,
                'member_label' => __('Jeanne Martin', 'mj-member'),
                'member_role_label' => \Mj\Member\Classes\MjRoles::getRoleLabel(\Mj\Member\Classes\MjRoles::TUTEUR),
                'amount' => 60.0,
                'amount_display' => number_format_i18n(60.0, 2),
                'created_display' => date_i18n(get_option('date_format', 'd/m/Y'), strtotime('-5 days')),
                'timestamp' => strtotime('-5 days'),
                'status' => 'requires_action',
                'status_label' => $action_label,
                'context' => 'membership',
                'context_label' => __('Cotisation MJ', 'mj-member'),
                'reference' => 'cs_preview_guardian',
                'show_context' => !empty($options['show_context']),
                'show_reference' => !empty($options['show_reference']),
                'checkout_url' => esc_url(home_url('/paiement/cotisation')),
                'qr_url' => esc_url($this->build_qr_url(home_url('/paiement/cotisation'))),
            ),
        );

        // Utiliser MjRoles pour les données d'aperçu
        $roleJeune = class_exists('Mj\\Member\\Classes\\MjRoles') ? \Mj\Member\Classes\MjRoles::JEUNE : 'jeune';
        $roleTuteur = class_exists('Mj\\Member\\Classes\\MjRoles') ? \Mj\Member\Classes\MjRoles::TUTEUR : 'tuteur';
        $labelJeune = class_exists('Mj\\Member\\Classes\\MjRoles') ? \Mj\Member\Classes\MjRoles::getRoleLabel($roleJeune) : __('Jeune', 'mj-member');
        $labelTuteur = class_exists('Mj\\Member\\Classes\\MjRoles') ? \Mj\Member\Classes\MjRoles::getRoleLabel($roleTuteur) : __('Tuteur', 'mj-member');

        $members = array(
            array(
                'id' => 201,
                'label' => __('Jeanne Martin', 'mj-member'),
                'role' => $roleTuteur,
                'role_label' => $labelTuteur,
                'type' => 'primary',
            ),
            array(
                'id' => 101,
                'label' => __('Alex Martin', 'mj-member'),
                'role' => $roleJeune,
                'role_label' => $labelJeune,
                'type' => 'child',
            ),
            array(
                'id' => 102,
                'label' => __('Lina Martin', 'mj-member'),
                'role' => $roleJeune,
                'role_label' => $labelJeune,
                'type' => 'child',
            ),
        );

        $confirmed_limit = isset($options['confirmed_limit']) ? max(1, (int) $options['confirmed_limit']) : count($sample_confirmed);
        $pending_limit = isset($options['pending_limit']) ? max(1, (int) $options['pending_limit']) : count($sample_pending);

        $confirmed_entries = array_slice($sample_confirmed, 0, $confirmed_limit);
        $confirmed_total = 0.0;
        foreach ($confirmed_entries as $entry) {
            $confirmed_total += isset($entry['amount']) ? (float) $entry['amount'] : 0.0;
        }

        $pending_entries = array_slice($sample_pending, 0, $pending_limit);
        $pending_total = 0.0;
        foreach ($pending_entries as $entry) {
            $pending_total += isset($entry['amount']) ? (float) $entry['amount'] : 0.0;
        }

        return array(
            'title' => isset($settings['title']) ? sanitize_text_field((string) $settings['title']) : '',
            'intro' => isset($settings['intro_text']) ? wp_kses_post((string) $settings['intro_text']) : '',
            'display_mode' => isset($options['display_mode']) ? $options['display_mode'] : 'tabs',
            'messages' => array(
                'empty_confirmed' => isset($settings['empty_confirmed_message']) ? sanitize_text_field((string) $settings['empty_confirmed_message']) : '',
                'empty_pending' => isset($settings['empty_pending_message']) ? sanitize_text_field((string) $settings['empty_pending_message']) : '',
                'not_logged_in' => __('Connectez-vous pour consulter vos paiements.', 'mj-member'),
                'view_qr' => __('Voir le QR code', 'mj-member'),
                'qr_modal_title' => __('Payer avec le QR code', 'mj-member'),
                'qr_modal_hint' => __('Scannez ce code ou ouvrez le lien pour finaliser le paiement.', 'mj-member'),
                'qr_modal_cta' => __('Ouvrir le lien de paiement', 'mj-member'),
                'qr_modal_close' => __('Fermer', 'mj-member'),
                'qr_modal_image_alt' => __('QR code du paiement', 'mj-member'),
            ),
            'options' => array(
                'show_context' => !empty($options['show_context']),
                'show_reference' => !empty($options['show_reference']),
            ),
            'viewer' => array(
                'can_manage_children' => true,
            ),
            'members' => $members,
            'confirmed' => array(
                'entries' => $confirmed_entries,
                'count' => count($confirmed_entries),
                'total_amount' => $confirmed_total,
            ),
            'pending' => array(
                'entries' => $pending_entries,
                'count' => count($pending_entries),
                'total_amount' => $pending_total,
            ),
        );
    }

    private function build_logged_out_data(array $settings, $display_mode)
    {
        return array(
            'title' => isset($settings['title']) ? sanitize_text_field((string) $settings['title']) : '',
            'intro' => isset($settings['intro_text']) ? wp_kses_post((string) $settings['intro_text']) : '',
            'display_mode' => $display_mode,
            'messages' => array(
                'empty_confirmed' => isset($settings['empty_confirmed_message']) ? sanitize_text_field((string) $settings['empty_confirmed_message']) : '',
                'empty_pending' => isset($settings['empty_pending_message']) ? sanitize_text_field((string) $settings['empty_pending_message']) : '',
                'not_logged_in' => __('Connectez-vous pour consulter vos paiements.', 'mj-member'),
            ),
            'options' => array(
                'show_context' => !empty($settings['show_context']) && $settings['show_context'] === 'yes',
                'show_reference' => !empty($settings['show_reference']) && $settings['show_reference'] === 'yes',
            ),
            'viewer' => array(
                'can_manage_children' => false,
            ),
            'members' => array(),
            'confirmed' => array(
                'entries' => array(),
                'count' => 0,
                'total_amount' => 0.0,
            ),
            'pending' => array(
                'entries' => array(),
                'count' => 0,
                'total_amount' => 0.0,
            ),
        );
    }
}
