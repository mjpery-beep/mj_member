<?php

if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

class Mj_Member_Elementor_Payment_Success_Widget extends Widget_Base {
    use Mj_Member_Elementor_Widget_Visibility;

    public function get_name() {
        return 'mj-member-payment-success';
    }

    public function get_title() {
        return __('Succès paiement Stripe MJ', 'mj-member');
    }

    public function get_icon() {
        return 'eicon-check-circle-o';
    }

    public function get_categories() {
        return array('general');
    }

    public function get_keywords() {
        return array('mj', 'stripe', 'paiement', 'member', 'success', 'confirmation');
    }

    protected function register_controls() {
        $this->start_controls_section(
            'section_content',
            array(
                'label' => __('Contenu', 'mj-member'),
            )
        );

        $this->add_control(
            'placeholders_info',
            array(
                'type' => Controls_Manager::RAW_HTML,
                'raw' => '<strong>' . esc_html__('Variables disponibles', 'mj-member') . '</strong><br>[member_name], [amount], [context_label], [event_title], [payment_date], [payment_status]',
                'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
            )
        );

        $this->add_control(
            'success_title',
            array(
                'label' => __('Titre (succès)', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Paiement confirmé', 'mj-member'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'success_message',
            array(
                'label' => __('Message (succès)', 'mj-member'),
                'type' => Controls_Manager::TEXTAREA,
                'rows' => 3,
                'default' => __('Merci [member_name] ! Nous avons bien confirmé ton paiement [context_label].', 'mj-member'),
            )
        );

        $this->add_control(
            'pending_title',
            array(
                'label' => __('Titre (confirmation en cours)', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Confirmation en cours', 'mj-member'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'pending_message',
            array(
                'label' => __('Message (confirmation en cours)', 'mj-member'),
                'type' => Controls_Manager::TEXTAREA,
                'rows' => 3,
                'default' => __('Nous avons bien reçu ton retour depuis Stripe. La confirmation peut prendre quelques instants.', 'mj-member'),
            )
        );

        $this->add_control(
            'failure_title',
            array(
                'label' => __('Titre (paiement annulé)', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Paiement annulé', 'mj-member'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'failure_message',
            array(
                'label' => __('Message (paiement annulé)', 'mj-member'),
                'type' => Controls_Manager::TEXTAREA,
                'rows' => 3,
                'default' => __('Le paiement a été annulé ou n\'a pas été confirmé. Tu peux relancer la procédure ou contacter la MJ.', 'mj-member'),
            )
        );

        $this->add_control(
            'missing_title',
            array(
                'label' => __('Titre (aucune donnée)', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Informations paiement manquantes', 'mj-member'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'missing_message',
            array(
                'label' => __('Message (aucune donnée)', 'mj-member'),
                'type' => Controls_Manager::TEXTAREA,
                'rows' => 3,
                'default' => __('Ce bloc affiche un récapitulatif juste après un paiement Stripe. Si tu viens de payer et que rien ne s\'affiche, contacte la MJ pour vérifier la transaction.', 'mj-member'),
            )
        );

        $this->add_control(
            'show_summary',
            array(
                'label' => __('Afficher le récapitulatif', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->add_control(
            'summary_heading',
            array(
                'label' => __('Titre du récapitulatif', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Récapitulatif de la transaction', 'mj-member'),
                'label_block' => true,
                'condition' => array('show_summary' => 'yes'),
            )
        );

        $this->add_control(
            'show_reference',
            array(
                'label' => __('Afficher la référence Stripe', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => '',
                'condition' => array('show_summary' => 'yes'),
            )
        );

        $this->add_control(
            'show_membership_details',
            array(
                'label' => __('Bloc cotisation', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->add_control(
            'membership_heading',
            array(
                'label' => __('Titre bloc cotisation', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Cotisation MJ Péry', 'mj-member'),
                'label_block' => true,
                'condition' => array('show_membership_details' => 'yes'),
            )
        );

        $this->add_control(
            'show_event_details',
            array(
                'label' => __('Bloc événement', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->add_control(
            'event_heading',
            array(
                'label' => __('Titre bloc événement', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Détails de l\'événement', 'mj-member'),
                'label_block' => true,
                'condition' => array('show_event_details' => 'yes'),
            )
        );

        $this->end_controls_section();

        $this->register_visibility_controls();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $this->apply_visibility_to_wrapper($settings, 'mj-payment-success');

        if (!class_exists('MjPayments')) {
            echo '<div class="mj-member-account-warning">' . esc_html__('Le module de paiements MJ Member est requis pour utiliser ce widget.', 'mj-member') . '</div>';
            return;
        }

        $is_preview = $this->is_preview_mode();

        $session_id = '';
        if (isset($_GET['session_id'])) {
            $session_id = sanitize_text_field(wp_unslash($_GET['session_id']));
        }

        $summary = null;
        if ($session_id !== '') {
            $summary = MjPayments::get_payment_summary_by_session($session_id);
        }

        if (!$summary && $is_preview) {
            $summary = $this->get_preview_summary();
        }

        $state = $summary ? $this->determine_state($summary) : 'missing';
        if ($summary && (!isset($summary['context_label']) || $summary['context_label'] === '')) {
            $summary['context_label'] = $this->fallback_context_label($summary);
        }

        $titles_default = array(
            'paid' => __('Paiement confirmé', 'mj-member'),
            'pending' => __('Confirmation en cours', 'mj-member'),
            'failed' => __('Paiement annulé', 'mj-member'),
            'missing' => __('Informations paiement manquantes', 'mj-member'),
        );

        $messages_default = array(
            'paid' => __('Merci [member_name] ! Nous avons bien confirmé ton paiement [context_label].', 'mj-member'),
            'pending' => __('Nous avons bien reçu ton retour depuis Stripe. La confirmation peut prendre quelques instants.', 'mj-member'),
            'failed' => __('Le paiement a été annulé ou n\'a pas été confirmé. Tu peux relancer la procédure ou contacter la MJ.', 'mj-member'),
            'missing' => __('Ce bloc affiche un récapitulatif juste après un paiement Stripe. Si tu viens de payer et que rien ne s\'affiche, contacte la MJ pour vérifier la transaction.', 'mj-member'),
        );

        $title_key = $this->get_state_setting_key($state, 'title');
        $message_key = $this->get_state_setting_key($state, 'message');

        $title = $this->get_setting_value($settings, $title_key, $titles_default[$state]);
        $message_template = $this->get_setting_value($settings, $message_key, $messages_default[$state]);
        $message = $this->format_message($message_template, $summary);

        $this->maybe_print_styles();

        $classes = array('mj-stripe-success', 'status-' . sanitize_html_class($state));
        if ($summary && !empty($summary['context'])) {
            $classes[] = 'context-' . sanitize_html_class($summary['context']);
        }

        echo '<div class="' . esc_attr(implode(' ', array_unique($classes))) . '">';
        echo '<div class="mj-stripe-success__header">';
        echo '<div class="mj-stripe-success__icon" aria-hidden="true">' . $this->get_state_icon($state) . '</div>';
        echo '<div class="mj-stripe-success__heading">';
        if ($title !== '') {
            echo '<h3 class="mj-stripe-success__title">' . esc_html($title) . '</h3>';
        }
        if ($message !== '') {
            echo '<div class="mj-stripe-success__message">' . wp_kses_post(wpautop($message)) . '</div>';
        }
        echo '</div>';
        echo '</div>';

        if ($summary) {
            $this->render_summary($summary, $settings);
            $this->render_context_block($summary, $settings);
        }

        echo '</div>';
    }

    private function get_setting_value($settings, $key, $default) {
        if ($key === '' || !is_array($settings)) {
            return $default;
        }

        if (isset($settings[$key]) && $settings[$key] !== '') {
            return $settings[$key];
        }

        return $default;
    }

    private function get_state_setting_key($state, $field) {
        $map = array(
            'paid' => array('title' => 'success_title', 'message' => 'success_message'),
            'pending' => array('title' => 'pending_title', 'message' => 'pending_message'),
            'failed' => array('title' => 'failure_title', 'message' => 'failure_message'),
            'missing' => array('title' => 'missing_title', 'message' => 'missing_message'),
        );

        return isset($map[$state][$field]) ? $map[$state][$field] : '';
    }

    private function determine_state($summary) {
        $status = isset($summary['status']) ? sanitize_key($summary['status']) : '';
        if (!empty($summary['is_paid'])) {
            return 'paid';
        }

        if (in_array($status, array('failed', 'canceled', 'cancelled', 'requires_payment_method_failed'), true)) {
            return 'failed';
        }

        if ($status === '' || in_array($status, array('pending', 'requires_payment_method', 'requires_action'), true)) {
            return 'pending';
        }

        return 'pending';
    }

    private function format_message($template, $summary) {
        $template = (string) $template;
        if ($template === '') {
            return '';
        }

        $member_name = isset($summary['member']['name']) ? $summary['member']['name'] : '';
        $amount = isset($summary['amount_display']) ? $summary['amount_display'] : '';
        $context_label = isset($summary['context_label']) ? $summary['context_label'] : '';
        $event_title = isset($summary['event']['title']) ? $summary['event']['title'] : '';
        $payment_date = '';
        if (!empty($summary['datetime_display'])) {
            $payment_date = $summary['datetime_display'];
        } elseif (!empty($summary['date_display'])) {
            $payment_date = $summary['date_display'];
        }
        $payment_status = isset($summary['status_label']) ? $summary['status_label'] : '';

        $replacements = array(
            '[member_name]' => esc_html($member_name),
            '[amount]' => esc_html($amount),
            '[context_label]' => esc_html($context_label),
            '[event_title]' => esc_html($event_title),
            '[payment_date]' => esc_html($payment_date),
            '[payment_status]' => esc_html($payment_status),
        );

        return strtr($template, $replacements);
    }

    private function render_summary($summary, $settings) {
        $enabled = !isset($settings['show_summary']) || $settings['show_summary'] === 'yes';
        if (!$enabled) {
            return;
        }

        $rows = array();

        if (!empty($summary['amount_display'])) {
            $rows[] = array(
                'label' => __('Montant', 'mj-member'),
                'value' => $summary['amount_display'],
            );
        }

        if (!empty($summary['datetime_display'])) {
            $rows[] = array(
                'label' => __('Date du paiement', 'mj-member'),
                'value' => $summary['datetime_display'],
            );
        } elseif (!empty($summary['date_display'])) {
            $rows[] = array(
                'label' => __('Date du paiement', 'mj-member'),
                'value' => $summary['date_display'],
            );
        }

        if (!empty($summary['status_label'])) {
            $rows[] = array(
                'label' => __('Statut', 'mj-member'),
                'value' => $summary['status_label'],
            );
        }

        if (!empty($summary['context_label'])) {
            $rows[] = array(
                'label' => __('Contexte', 'mj-member'),
                'value' => $summary['context_label'],
            );
        }

        if (!empty($summary['member']['name'])) {
            $rows[] = array(
                'label' => __('Membre concerné', 'mj-member'),
                'value' => $summary['member']['name'],
            );
        }

        if (!empty($summary['payer']['name']) && $summary['payer']['name'] !== $summary['member']['name']) {
            $rows[] = array(
                'label' => __('Payé par', 'mj-member'),
                'value' => $summary['payer']['name'],
            );
        }

        if (!empty($summary['session_id']) && isset($settings['show_reference']) && $settings['show_reference'] === 'yes') {
            $rows[] = array(
                'label' => __('Référence Stripe', 'mj-member'),
                'value' => $summary['session_id'],
            );
        }

        if (empty($rows)) {
            return;
        }

        $heading = $this->get_setting_value($settings, 'summary_heading', __('Récapitulatif de la transaction', 'mj-member'));

        echo '<div class="mj-stripe-success__summary">';
        if ($heading !== '') {
            echo '<h4 class="mj-stripe-success__summary-title">' . esc_html($heading) . '</h4>';
        }
        echo '<div class="mj-stripe-success__list">';
        foreach ($rows as $row) {
            echo '<div class="mj-stripe-success__row">';
            echo '<span class="mj-stripe-success__row-label">' . esc_html($row['label']) . '</span>';
            echo '<span class="mj-stripe-success__row-value">' . esc_html($row['value']) . '</span>';
            echo '</div>';
        }
        echo '</div>';
        echo '</div>';
    }

    private function render_context_block($summary, $settings) {
        $event = isset($summary['event']) && is_array($summary['event']) ? $summary['event'] : null;
        $registration = isset($summary['registration']) && is_array($summary['registration']) ? $summary['registration'] : null;
        $member = isset($summary['member']) && is_array($summary['member']) ? $summary['member'] : array();

        $show_event = !isset($settings['show_event_details']) || $settings['show_event_details'] === 'yes';
        $show_membership = !isset($settings['show_membership_details']) || $settings['show_membership_details'] === 'yes';

        if ($event && $show_event) {
            $heading = $this->get_setting_value($settings, 'event_heading', __('Détails de l\'événement', 'mj-member'));

            echo '<div class="mj-stripe-success__context">';
            if ($heading !== '') {
                echo '<h4 class="mj-stripe-success__context-title">' . esc_html($heading) . '</h4>';
            }
            echo '<ul class="mj-stripe-success__context-list">';
            if (!empty($event['title'])) {
                echo '<li class="mj-stripe-success__context-item"><span class="mj-stripe-success__context-label">' . esc_html__('Événement', 'mj-member') . '</span><span class="mj-stripe-success__context-value">' . esc_html($event['title']) . '</span></li>';
            }
            if (!empty($event['date_range'])) {
                echo '<li class="mj-stripe-success__context-item"><span class="mj-stripe-success__context-label">' . esc_html__('Dates', 'mj-member') . '</span><span class="mj-stripe-success__context-value">' . esc_html($event['date_range']) . '</span></li>';
            }
            if ($registration && !empty($registration['status_label'])) {
                echo '<li class="mj-stripe-success__context-item"><span class="mj-stripe-success__context-label">' . esc_html__('Statut d\'inscription', 'mj-member') . '</span><span class="mj-stripe-success__context-value">' . esc_html($registration['status_label']) . '</span></li>';
            }
            echo '</ul>';
            echo '</div>';

            return;
        }

        if ($show_membership && !empty($member['name'])) {
            $heading = $this->get_setting_value($settings, 'membership_heading', __('Cotisation MJ Péry', 'mj-member'));

            echo '<div class="mj-stripe-success__context">';
            if ($heading !== '') {
                echo '<h4 class="mj-stripe-success__context-title">' . esc_html($heading) . '</h4>';
            }
            echo '<ul class="mj-stripe-success__context-list">';
            echo '<li class="mj-stripe-success__context-item"><span class="mj-stripe-success__context-label">' . esc_html__('Bénéficiaire', 'mj-member') . '</span><span class="mj-stripe-success__context-value">' . esc_html($member['name']) . '</span></li>';
            if (!empty($summary['status_label'])) {
                echo '<li class="mj-stripe-success__context-item"><span class="mj-stripe-success__context-label">' . esc_html__('Statut', 'mj-member') . '</span><span class="mj-stripe-success__context-value">' . esc_html($summary['status_label']) . '</span></li>';
            }
            echo '</ul>';
            echo '</div>';
        }
    }

    private function maybe_print_styles() {
        static $printed = false;
        if ($printed) {
            return;
        }
        $printed = true;

        echo '<style>'
            . '.mj-stripe-success{position:relative;border:1px solid #e4e7ec;border-radius:16px;padding:24px;background:#ffffff;box-shadow:0 18px 32px rgba(15,23,42,0.08);}'
            . '.mj-stripe-success__header{display:flex;align-items:flex-start;gap:16px;}'
            . '.mj-stripe-success__icon{flex:0 0 52px;height:52px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:26px;font-weight:700;background:#e6f4ee;color:#17623a;}'
            . '.mj-stripe-success.status-pending .mj-stripe-success__icon{background:#fff5da;color:#c05600;}'
            . '.mj-stripe-success.status-failed .mj-stripe-success__icon{background:#fee4e2;color:#b42318;}'
            . '.mj-stripe-success.status-missing .mj-stripe-success__icon{background:#e0ecff;color:#1d4ed8;}'
            . '.mj-stripe-success__title{margin:0;font-size:1.4rem;font-weight:700;}'
            . '.mj-stripe-success__message{margin-top:6px;color:#475569;font-size:0.98rem;}'
            . '.mj-stripe-success__summary{margin-top:24px;padding-top:18px;border-top:1px solid #edf0f7;}'
            . '.mj-stripe-success__summary-title{margin:0 0 14px;font-size:1.1rem;font-weight:600;color:#1f2937;}'
            . '.mj-stripe-success__list{display:flex;flex-direction:column;gap:10px;}'
            . '.mj-stripe-success__row{display:flex;justify-content:space-between;gap:12px;padding:10px 14px;border-radius:10px;background:#f8fafc;}'
            . '.mj-stripe-success__row-label{font-weight:600;color:#1e293b;}'
            . '.mj-stripe-success__row-value{color:#0f172a;font-weight:500;text-align:right;}'
            . '.mj-stripe-success__context{margin-top:20px;padding:18px;border-radius:12px;background:#f1f5f9;}'
            . '.mj-stripe-success__context-title{margin:0 0 10px;font-size:1.05rem;font-weight:600;color:#1f2937;}'
            . '.mj-stripe-success__context-list{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:8px;}'
            . '.mj-stripe-success__context-item{display:flex;justify-content:space-between;gap:12px;}'
            . '.mj-stripe-success__context-label{font-weight:600;color:#1f2937;}'
            . '.mj-stripe-success__context-value{color:#0f172a;font-weight:500;text-align:right;}'
            . '@media (max-width:600px){.mj-stripe-success{padding:20px;}.mj-stripe-success__row,.mj-stripe-success__context-item{flex-direction:column;align-items:flex-start;}.mj-stripe-success__row-value,.mj-stripe-success__context-value{text-align:left;}}'
            . '</style>';
    }

    private function get_state_icon($state) {
        switch ($state) {
            case 'paid':
                return '&#10003;';
            case 'pending':
                return '&#8987;';
            case 'failed':
                return '&#10060;';
            default:
                return '&#9432;';
        }
    }

    private function fallback_context_label($summary) {
        $context = isset($summary['context']) ? sanitize_key($summary['context']) : '';
        switch ($context) {
            case 'event':
            case 'event_registration':
                return __('Inscription événement', 'mj-member');
            case 'membership':
            case 'membership_renewal':
                return __('Cotisation MJ Péry', 'mj-member');
            default:
                if ($context === '') {
                    return __('Paiement MJ', 'mj-member');
                }
                $context = str_replace('_', ' ', $context);
                return ucfirst($context);
        }
    }

    private function is_preview_mode() {
        if (function_exists('mj_member_login_component_is_preview_mode')) {
            return (bool) mj_member_login_component_is_preview_mode();
        }

        if (did_action('elementor/loaded')) {
            $elementor = \Elementor\Plugin::$instance ?? null;
            if ($elementor && isset($elementor->editor) && method_exists($elementor->editor, 'is_edit_mode')) {
                return (bool) $elementor->editor->is_edit_mode();
            }
        }

        return false;
    }

    private function get_preview_summary() {
        $now = current_time('timestamp');
        $amount = 12.00;
        $date_label = wp_date(get_option('date_format', 'd/m/Y'), $now);
        $time_label = wp_date(get_option('time_format', 'H:i'), $now);
        $datetime_label = sprintf(__('Le %1$s à %2$s', 'mj-member'), $date_label, $time_label);

        $summary = array(
            'session_id' => 'cs_preview_success',
            'status' => 'paid',
            'status_label' => __('Paiement confirmé', 'mj-member'),
            'is_paid' => true,
            'amount' => $amount,
            'amount_display' => number_format_i18n($amount, 2) . ' €',
            'currency_symbol' => '€',
            'context' => 'membership',
            'context_label' => __('Cotisation MJ Péry', 'mj-member'),
            'created_timestamp' => $now,
            'created_at' => wp_date('c', $now),
            'paid_timestamp' => $now,
            'paid_at' => wp_date('c', $now),
            'date_display' => $date_label,
            'time_display' => $time_label,
            'datetime_display' => $datetime_label,
            'member' => array(
                'id' => 0,
                'name' => __('Alexis Exemple', 'mj-member'),
            ),
            'payer' => array(
                'id' => 0,
                'name' => __('Parent Exemple', 'mj-member'),
            ),
            'event' => null,
            'registration' => null,
            'event_id' => 0,
            'registration_id' => 0,
        );

        $force_event = false;
        if (isset($_GET['mj_event_id'])) {
            $force_event = true;
        } elseif (isset($_GET['mj_preview_context'])) {
            $force_event = sanitize_key(wp_unslash($_GET['mj_preview_context'])) === 'event';
        }

        if ($force_event) {
            $start = strtotime('+7 days', $now);
            $end = strtotime('+9 days', $now);
            $summary['context'] = 'event';
            $summary['context_label'] = __('Inscription événement', 'mj-member');
            $summary['event'] = array(
                'id' => isset($_GET['mj_event_id']) ? (int) $_GET['mj_event_id'] : 27,
                'title' => __('Stage Street Art', 'mj-member'),
                'start_timestamp' => $start,
                'end_timestamp' => $end,
                'date_range' => sprintf(
                    __('Du %1$s au %2$s', 'mj-member'),
                    wp_date(get_option('date_format', 'd/m/Y'), $start),
                    wp_date(get_option('date_format', 'd/m/Y'), $end)
                ),
            );
            $summary['registration'] = array(
                'id' => 23,
                'status' => 'valide',
                'status_label' => __('Valide', 'mj-member'),
            );
            $summary['member']['name'] = __('Léa Exemple', 'mj-member');
        }

        return $summary;
    }
}
