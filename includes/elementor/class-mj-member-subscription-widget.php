<?php

if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;

class Mj_Member_Elementor_Subscription_Widget extends Widget_Base {
    public function get_name() {
        return 'mj-member-subscription-status';
    }

    public function get_title() {
        return __('Statut cotisation MJ', 'mj-member');
    }

    public function get_icon() {
        return 'eicon-check-circle-o';
    }

    public function get_categories() {
        return array('general');
    }

    public function get_keywords() {
        return array('mj', 'cotisation', 'member', 'paiement');
    }

    protected function register_controls() {
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
                'default' => __('Ma cotisation', 'mj-member'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'show_amount',
            array(
                'label' => __('Afficher le montant', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->add_control(
            'show_last_payment',
            array(
                'label' => __('Afficher le dernier paiement', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->add_control(
            'show_expiry',
            array(
                'label' => __('Afficher la date d\'échéance', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->add_control(
            'button_label',
            array(
                'label' => __('Texte du bouton de renouvellement', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Renouveler ma cotisation', 'mj-member'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'show_button',
            array(
                'label' => __('Afficher le bouton de paiement', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->add_control(
            'manual_payment_message',
            array(
                'label' => __('Message confort (paiement direct)', 'mj-member'),
                'type' => Controls_Manager::TEXTAREA,
                'default' => __('Tu préfères le contact direct ? Remets [montant_cotisation] à un animateur, tout fonctionne aussi !', 'mj-member'),
                'rows' => 3,
                'placeholder' => __('Laissez vide pour ne pas afficher de message.', 'mj-member'),
            )
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_card',
            array(
                'label' => __('Bloc', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'card_background',
            array(
                'label' => __('Couleur de fond', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-subscription' => 'background-color: {{VALUE}};',
                ),
            )
        );

        if (class_exists('Elementor\\Group_Control_Border')) {
            $this->add_group_control(
                Group_Control_Border::get_type(),
                array(
                    'name' => 'card_border',
                    'selector' => '{{WRAPPER}} .mj-member-subscription',
                )
            );
        }

        if (class_exists('Elementor\\Group_Control_Box_Shadow')) {
            $this->add_group_control(
                Group_Control_Box_Shadow::get_type(),
                array(
                    'name' => 'card_shadow',
                    'selector' => '{{WRAPPER}} .mj-member-subscription',
                )
            );
        }

        $this->add_responsive_control(
            'card_padding',
            array(
                'label' => __('Marge interne', 'mj-member'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => array('px', 'em', '%'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-subscription' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_badge',
            array(
                'label' => __('Badge de statut', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'badge_text_color',
            array(
                'label' => __('Couleur du texte', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-subscription__badge' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'badge_background_color',
            array(
                'label' => __('Couleur de fond', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-subscription__badge' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_button',
            array(
                'label' => __('Bouton', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'button_text_color',
            array(
                'label' => __('Couleur du texte', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-subscription__form .mj-member-button' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'button_background',
            array(
                'label' => __('Couleur de fond', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-subscription__form .mj-member-button' => 'background-color: {{VALUE}}; border-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'button_background_hover',
            array(
                'label' => __('Fond au survol', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-subscription__form .mj-member-button:hover' => 'background-color: {{VALUE}}; border-color: {{VALUE}};',
                ),
            )
        );

        if (class_exists('Elementor\\Group_Control_Border')) {
            $this->add_group_control(
                Group_Control_Border::get_type(),
                array(
                    'name' => 'button_border',
                    'selector' => '{{WRAPPER}} .mj-member-subscription__form .mj-member-button',
                )
            );
        }

        if (class_exists('Elementor\\Group_Control_Box_Shadow')) {
            $this->add_group_control(
                Group_Control_Box_Shadow::get_type(),
                array(
                    'name' => 'button_shadow',
                    'selector' => '{{WRAPPER}} .mj-member-subscription__form .mj-member-button',
                )
            );
        }

        $this->add_responsive_control(
            'button_padding',
            array(
                'label' => __('Marge interne', 'mj-member'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => array('px', 'em', '%'),
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-subscription__form .mj-member-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->end_controls_section();
    }

    protected function render() {
        if (!function_exists('mj_member_get_membership_status')) {
            echo '<div class="mj-member-account-warning">' . esc_html__('Le module MJ Member doit être actif pour utiliser ce widget.', 'mj-member') . '</div>';
            return;
        }

        if (!is_user_logged_in()) {
            $login_url = wp_login_url(mj_member_get_current_url());
            echo '<div class="mj-member-account-warning">' . esc_html__('Connectez-vous pour consulter votre statut de cotisation.', 'mj-member') . ' <a href="' . esc_url($login_url) . '">' . esc_html__('Se connecter', 'mj-member') . '</a></div>';
            return;
        }

        $member = mj_member_get_current_member();
        if (!$member) {
            echo '<div class="mj-member-account-warning">' . esc_html__('Aucun profil MJ associé à votre compte.', 'mj-member') . '</div>';
            return;
        }

        $settings = $this->get_settings_for_display();
        $status = mj_member_get_membership_status($member);
        $children_statuses = function_exists('mj_member_get_guardian_children_statuses') ? mj_member_get_guardian_children_statuses($member) : array();
        $children_due_count = 0;
        if (!empty($children_statuses)) {
            foreach ($children_statuses as $child_entry) {
                if (!empty($child_entry['requires_payment'])) {
                    $children_due_count++;
                }
            }
        }

        $amount_label = number_format((float) $status['amount'], 2, ',', ' ');
        $show_amount = isset($settings['show_amount']) && $settings['show_amount'] === 'yes';
        $show_last_payment = isset($settings['show_last_payment']) && $settings['show_last_payment'] === 'yes';
        $show_expiry = isset($settings['show_expiry']) && $settings['show_expiry'] === 'yes';
        $show_button = isset($settings['show_button']) && $settings['show_button'] === 'yes';

        $should_display_button = $show_button && $status['requires_payment'] && in_array($status['status'], array('missing', 'expired', 'expiring'), true);
        $button_label = isset($settings['button_label']) && $settings['button_label'] !== '' ? $settings['button_label'] : __('Renouveler ma cotisation', 'mj-member');

        $payment_error = isset($_GET['mj_member_payment_error']) ? sanitize_key(wp_unslash($_GET['mj_member_payment_error'])) : '';
        $error_message = '';
        if ($payment_error !== '') {
            switch ($payment_error) {
                case 'nonce':
                    $error_message = __('La vérification de sécurité a échoué. Merci de réessayer.', 'mj-member');
                    break;
                case 'stripe':
                    $error_message = __('La création du paiement Stripe a échoué. Contactez la MJ.', 'mj-member');
                    break;
                case 'member':
                    $error_message = __('Impossible de retrouver votre profil MJ. Contactez la MJ.', 'mj-member');
                    break;
                case 'missing_class':
                    $error_message = __('Le module de paiement n\'est pas disponible.', 'mj-member');
                    break;
                default:
                    $error_message = __('Une erreur est survenue lors de la génération du paiement.', 'mj-member');
                    break;
            }
        }

        $is_guardian = function_exists('mj_member_can_manage_children') ? mj_member_can_manage_children($member) : false;

        if ($is_guardian) {
            if (empty($settings['title']) || $settings['title'] === __('Ma cotisation', 'mj-member')) {
                $settings['title'] = __('Cotisation de mes jeunes', 'mj-member');
            }
            if (!empty($children_statuses) && (empty($settings['button_label']) || $settings['button_label'] === __('Renouveler ma cotisation', 'mj-member'))) {
                $settings['button_label'] = __('Payer toutes les cotisations', 'mj-member');
            }
            if (!empty($children_statuses) && (empty($settings['manual_payment_message']) || $settings['manual_payment_message'] === __('Tu préfères le contact direct ? Remets [montant_cotisation] à un animateur, tout fonctionne aussi !', 'mj-member'))) {
                $settings['manual_payment_message'] = __('Chaque jeune peut remettre 2 € directement à un animateur si tu préfères ce mode de paiement.', 'mj-member');
            }
        }

        $child_edit_nonce = (!empty($children_statuses) && $is_guardian) ? wp_create_nonce('mj_member_update_child_profile') : '';
        $child_payment_nonce = (!empty($children_statuses) && $is_guardian) ? wp_create_nonce('mj_member_create_child_payment_link') : '';

        $card_classes = array('mj-member-subscription', 'status-' . sanitize_html_class($status['status']));
        $card_class_attr = implode(' ', array_unique($card_classes));

        $redirect_to = mj_member_get_current_url();

        static $styles_printed = false;
        if (!$styles_printed) {
            $styles_printed = true;
            echo '<style>'
                . '.mj-member-subscription{border:1px solid #e3e6ea;border-radius:12px;padding:24px;background:#fff;box-shadow:0 10px 30px rgba(0,0,0,0.06);}'
                . '.mj-member-subscription__header{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:12px;}'
                . '.mj-member-subscription__title{margin:0;font-size:1.35rem;}'
                . '.mj-member-subscription__badge{display:inline-flex;align-items:center;gap:6px;padding:4px 12px;border-radius:999px;font-size:0.85rem;font-weight:600;background:#edf2ff;color:#2f5dff;}'
                . '.mj-member-subscription__badge-icon{display:inline-flex;align-items:center;justify-content:center;font-size:1rem;}'
                . '.mj-member-subscription.status-active .mj-member-subscription__badge{background:#e6f5ea;color:#1d6b2a;}'
                . '.mj-member-subscription.status-expired .mj-member-subscription__badge{background:#ffecec;color:#d63638;}'
                . '.mj-member-subscription.status-expiring .mj-member-subscription__badge{background:#fff2d6;color:#a05c00;}'
                . '.mj-member-subscription__meta{margin:8px 0;font-size:0.95rem;color:#495057;}'
                . '.mj-member-subscription__amount{font-weight:600;font-size:1.1rem;margin:12px 0;}'
                . '.mj-member-subscription__form{margin-top:16px;}'
                . '.mj-member-subscription__form .mj-member-button{display:inline-flex;align-items:center;justify-content:center;padding:12px 24px;border-radius:6px;border:none;background:#0073aa;color:#fff;font-size:16px;font-weight:600;cursor:pointer;transition:background 0.2s ease,transform 0.2s ease;}'
                . '.mj-member-subscription__form .mj-member-button:hover{background:#005f8d;transform:translateY(-1px);}'
                . '.mj-member-subscription__notice{margin-top:12px;padding:10px 14px;border-left:4px solid #d63638;background:#fbeaea;border-radius:6px;color:#8a1f1f;font-size:0.92rem;}'
                . '.mj-member-subscription__details{margin:0;font-size:0.95rem;color:#555;}'
                . '.mj-member-subscription__hint{margin-top:16px;padding:12px 16px;border-radius:8px;background:#f1f5f9;color:#1f2937;font-size:0.95rem;}'
                . '.mj-member-subscription__children{margin-top:24px;padding-top:16px;border-top:1px solid rgba(0,0,0,0.08);}'
                . '.mj-member-subscription__children-title{margin:0 0 12px;font-size:1.1rem;font-weight:600;}'
                . '.mj-member-subscription__children-list{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:10px;}'
                . '.mj-member-subscription__children-item{padding:12px 14px;border-radius:10px;background:#f8fafc;display:flex;flex-direction:column;gap:6px;}'
                . '.mj-member-subscription__children-item.status-expired{background:#fff4f4;}'
                . '.mj-member-subscription__children-item.status-expiring{background:#fff8e6;}'
                . '.mj-member-subscription__children-item.status-missing{background:#fef6ff;}'
                . '.mj-member-subscription__children-item.status-active .mj-member-subscription__children-badge{background:#dcf5e3;color:#1d6b2a;}'
                . '.mj-member-subscription__children-header{display:flex;align-items:center;justify-content:space-between;gap:12px;}'
                . '.mj-member-subscription__children-name{font-weight:600;font-size:1rem;margin:0;}'
                . '.mj-member-subscription__children-badge{display:inline-flex;align-items:center;padding:3px 10px;border-radius:999px;font-size:0.78rem;background:#e2e8f0;color:#1e293b;font-weight:600;}'
                . '.mj-member-subscription__children-meta{margin:0;color:#475569;font-size:0.9rem;line-height:1.45;}'
                . '.mj-member-subscription__children-amount{font-weight:600;}'
                . '.mj-member-subscription__children-note{margin-top:8px;font-size:0.92rem;color:#1f2937;background:#f1f5f9;padding:10px 12px;border-radius:8px;}'
                . '.mj-member-subscription__children-actions{margin-top:8px;display:flex;flex-direction:column;gap:10px;}'
                . '.mj-member-subscription__children-actions-main{display:flex;flex-wrap:wrap;gap:10px;align-items:center;}'
                . '.mj-member-button--secondary{display:inline-flex;align-items:center;justify-content:center;padding:10px 18px;border-radius:6px;border:1px solid #1d4ed8;background:#1d4ed8;color:#fff;font-weight:600;text-decoration:none;transition:background 0.2s ease,transform 0.2s ease;}'
                . '.mj-member-button--secondary:hover{background:#163fa3;border-color:#163fa3;transform:translateY(-1px);}'
                . '.mj-member-button--ghost{display:inline-flex;align-items:center;justify-content:center;padding:10px 18px;border-radius:6px;border:1px solid rgba(15,23,42,0.25);background:#fff;color:#1f2937;font-weight:600;text-decoration:none;cursor:pointer;transition:background 0.2s ease,transform 0.2s ease;}'
                . '.mj-member-button--ghost:hover{background:#f1f5f9;transform:translateY(-1px);}'
                . '.mj-member-button--primary{display:inline-flex;align-items:center;justify-content:center;padding:12px 24px;border-radius:6px;border:none;background:#0073aa;color:#fff;font-weight:600;cursor:pointer;transition:background 0.2s ease,transform 0.2s ease;}'
                . '.mj-member-button--primary:hover{background:#005f8d;transform:translateY(-1px);}'
                . '.mj-member-subscription__children-pay-note{margin:0;font-size:0.88rem;color:#475569;}'
                . '.mj-member-child-modal{position:fixed;inset:0;display:none;align-items:center;justify-content:center;padding:20px;z-index:10000;}'
                . '.mj-member-child-modal.is-visible{display:flex;}'
                . '.mj-member-child-modal__backdrop{position:absolute;inset:0;background:rgba(15,23,42,0.55);}'
                . '.mj-member-child-modal__dialog{position:relative;background:#fff;border-radius:12px;width:100%;max-width:520px;padding:24px;box-shadow:0 24px 48px rgba(15,23,42,0.2);display:flex;flex-direction:column;gap:16px;}'
                . '.mj-member-child-modal__title{margin:0;font-size:1.25rem;font-weight:600;color:#0f172a;}'
                . '.mj-member-child-modal__subtitle{margin:0;color:#475569;font-size:0.95rem;}'
                . '.mj-member-child-modal__close{position:absolute;top:12px;right:12px;background:transparent;border:none;font-size:1.4rem;line-height:1;color:#475569;cursor:pointer;}'
                . '.mj-member-child-modal__form{display:flex;flex-direction:column;gap:12px;}'
                . '.mj-member-child-modal__form label{display:flex;flex-direction:column;gap:6px;font-size:0.9rem;font-weight:600;color:#1f2937;}'
                . '.mj-member-child-modal__form input,.mj-member-child-modal__form textarea{width:100%;padding:10px 12px;border:1px solid #cbd5e1;border-radius:8px;font-size:0.95rem;}'
                . '.mj-member-child-modal__form textarea{min-height:96px;resize:vertical;}'
                . '.mj-member-child-modal__checkbox{display:flex;align-items:center;gap:8px;font-weight:500;color:#1f2937;font-size:0.9rem;}'
                . '.mj-member-child-modal__footer{display:flex;justify-content:flex-end;gap:12px;margin-top:4px;}'
                . '.mj-member-child-modal__feedback{display:none;border-radius:8px;padding:10px 12px;font-size:0.9rem;}'
                . '.mj-member-child-modal__feedback.is-error{display:block;background:#fee2e2;color:#991b1b;}'
                . '.mj-member-child-modal__feedback.is-success{display:block;background:#dcfce7;color:#166534;}'
                . '</style>';
        }

            static $script_printed = false;

        echo '<div class="' . esc_attr($card_class_attr) . '">';
        echo '<div class="mj-member-subscription__header">';
        $title = isset($settings['title']) ? $settings['title'] : '';
        if ($title !== '') {
            echo '<h3 class="mj-member-subscription__title">' . esc_html($title) . '</h3>';
        }
        if (!($is_guardian && !$status['requires_payment'])) {
            $badge_icon = '';
            if ($status['status'] === 'active') {
                $badge_icon = '<span class="mj-member-subscription__badge-icon" aria-hidden="true">&#10003;</span>';
            }
            echo '<span class="mj-member-subscription__badge">' . $badge_icon . esc_html($status['status_label']) . '</span>';
        }
        echo '</div>';

        if (!($is_guardian && !$status['requires_payment'])) {
            echo '<p class="mj-member-subscription__details">' . esc_html($status['description']) . '</p>';
        }

        if ($show_amount && $status['requires_payment']) {
            echo '<p class="mj-member-subscription__amount">' . sprintf(__('Cotisation annuelle : %s €', 'mj-member'), esc_html($amount_label)) . '</p>';
        }

        if ($show_last_payment && $status['last_payment_display'] !== '') {
            echo '<p class="mj-member-subscription__meta">' . sprintf(__('Dernier paiement : %s', 'mj-member'), esc_html($status['last_payment_display'])) . '</p>';
        }

        if ($show_expiry && $status['expires_display'] !== '') {
            echo '<p class="mj-member-subscription__meta">' . sprintf(__('Échéance : %s', 'mj-member'), esc_html($status['expires_display'])) . '</p>';
        }

        if ($error_message !== '') {
            echo '<div class="mj-member-subscription__notice">' . esc_html($error_message) . '</div>';
        }

        if (!empty($children_statuses)) {
            echo '<div class="mj-member-subscription__children">';
            echo '<h4 class="mj-member-subscription__children-title">' . esc_html__('Liste de mes jeunes', 'mj-member') . '</h4>';

            if ($children_due_count > 0) {
                echo '<div class="mj-member-subscription__children-note">' . esc_html(sprintf(_n('%d cotisation enfant est à régulariser.', '%d cotisations enfants sont à régulariser.', $children_due_count, 'mj-member'), $children_due_count)) . '</div>';
            }

            echo '<ul class="mj-member-subscription__children-list">';
            foreach ($children_statuses as $child_entry) {
                $child_status = isset($child_entry['status']) ? sanitize_html_class($child_entry['status']) : 'unknown';
                $amount_value = isset($child_entry['amount']) ? (float) $child_entry['amount'] : 0.0;
                $amount_label_child = number_format_i18n($amount_value, 2);

                echo '<li class="mj-member-subscription__children-item status-' . esc_attr($child_status) . '" data-child-id="' . esc_attr((int) $child_entry['id']) . '">';
                echo '<div class="mj-member-subscription__children-header">';
                echo '<span class="mj-member-subscription__children-name">' . esc_html($child_entry['full_name']) . '</span>';
                $child_badge_icon = '';
                if ($child_status === 'active') {
                    $child_badge_icon = '<span class="mj-member-subscription__badge-icon" aria-hidden="true">&#10003;</span>';
                }
                echo '<span class="mj-member-subscription__children-badge">' . $child_badge_icon . esc_html($child_entry['status_label']) . '</span>';
                echo '</div>';

                $meta_parts = array();
                if (!empty($child_entry['last_payment_display'])) {
                    $meta_parts[] = sprintf(__('Dernier paiement : %s', 'mj-member'), esc_html($child_entry['last_payment_display']));
                }
                if (!empty($child_entry['expires_display'])) {
                    $meta_parts[] = sprintf(__('Échéance : %s', 'mj-member'), esc_html($child_entry['expires_display']));
                }
                if (!empty($child_entry['requires_payment'])) {
                    $meta_parts[] = '<span class="mj-member-subscription__children-amount">' . sprintf(__('Montant dû : %s €', 'mj-member'), esc_html($amount_label_child)) . '</span>';
                }

                if (!empty($meta_parts)) {
                    $meta_html = implode('<br />', $meta_parts);
                    echo '<p class="mj-member-subscription__children-meta">' . wp_kses_post($meta_html) . '</p>';
                } elseif (!empty($child_entry['description'])) {
                    echo '<p class="mj-member-subscription__children-meta">' . esc_html($child_entry['description']) . '</p>';
                }

                $child_profile_attr = '{}';
                if (isset($child_entry['profile']) && is_array($child_entry['profile'])) {
                    $profile_json = wp_json_encode($child_entry['profile']);
                    if (is_string($profile_json)) {
                        $child_profile_attr = $profile_json;
                    }
                }

                echo '<div class="mj-member-subscription__children-actions">';
                echo '<div class="mj-member-subscription__children-actions-main">';
                if (!empty($child_entry['requires_payment']) && $child_payment_nonce !== '') {
                    echo '<form method="post" class="mj-member-subscription__child-form" data-child-id="' . esc_attr((int) $child_entry['id']) . '" data-ajax-nonce="' . esc_attr($child_payment_nonce) . '">';
                    echo '<button type="submit" class="mj-member-button mj-member-button--secondary">' . esc_html__('Payer cette cotisation', 'mj-member') . '</button>';
                    echo '</form>';
                }
                echo '<button type="button" class="mj-member-button mj-member-button--ghost mj-member-child-edit" data-child-id="' . esc_attr((int) $child_entry['id']) . '" data-child-profile="' . esc_attr($child_profile_attr) . '">' . esc_html__('Modifier les informations', 'mj-member') . '</button>';
                echo '</div>';
                if (!empty($child_entry['requires_payment'])) {
                    echo '<p class="mj-member-subscription__children-pay-note">' . esc_html__('Ce jeune peut aussi remettre 2 € directement à un animateur.', 'mj-member') . '</p>';
                }
                echo '</div>';

                echo '</li>';
            }
            echo '</ul>';
            echo '</div>';
        }

        $should_render_child_forms = !empty($children_statuses) && $child_payment_nonce !== '';
        $has_child_edit = !empty($children_statuses) && $child_edit_nonce !== '';
        $needs_widget_script = $should_display_button || $should_render_child_forms || $has_child_edit;

        if ($needs_widget_script && !$script_printed) {
            $script_printed = true;
            $config = array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'i18n' => array(
                    'loading' => __('Redirection vers Stripe...', 'mj-member'),
                    'error' => __('Impossible de générer le lien de paiement. Merci de réessayer.', 'mj-member'),
                    'childMissing' => __('Impossible de déterminer le jeune concerné.', 'mj-member'),
                    'editError' => __('Impossible de mettre à jour les informations du jeune. Merci de réessayer.', 'mj-member'),
                    'editSuccess' => __('Informations du jeune mises à jour.', 'mj-member'),
                    'editSaved' => __('Modifications enregistrées.', 'mj-member'),
                ),
            );
            if ($child_edit_nonce !== '') {
                $config['editNonce'] = $child_edit_nonce;
            }
            echo '<script>window.mjMemberPaymentConfig = Object.assign({}, window.mjMemberPaymentConfig || {}, ' . wp_json_encode($config) . ');</script>';
            ob_start();
            ?>
<script>
(function(){
    if (window.mjMemberPaymentInit) {
        return;
    }
    window.mjMemberPaymentInit = true;

    function initialize() {
        var config = window.mjMemberPaymentConfig || {};
        var ajaxUrl = config.ajaxUrl || '';
        var loadingText = (config.i18n && config.i18n.loading) ? config.i18n.loading : 'Redirection...';
        var errorText = (config.i18n && config.i18n.error) ? config.i18n.error : 'Une erreur est survenue.';
        var childMissingText = (config.i18n && config.i18n.childMissing) ? config.i18n.childMissing : errorText;
        var editErrorText = (config.i18n && config.i18n.editError) ? config.i18n.editError : errorText;
        var editSuccessText = (config.i18n && config.i18n.editSuccess) ? config.i18n.editSuccess : '';
        var editSavedText = (config.i18n && config.i18n.editSaved) ? config.i18n.editSaved : 'Modifications enregistrées.';
        var editNonce = config.editNonce || '';
        var modal = document.getElementById('mj-member-child-modal');
        var modalForm = modal ? modal.querySelector('form') : null;
        var modalFeedback = modal ? modal.querySelector('.mj-member-child-modal__feedback') : null;
        var modalSubtitle = modal ? modal.querySelector('.mj-member-child-modal__subtitle') : null;
        var modalNonceField = modalForm ? modalForm.querySelector('input[name="nonce"]') : null;

        if (editNonce && modalNonceField) {
            modalNonceField.value = editNonce;
        } else if (!editNonce && modalNonceField && modalNonceField.value) {
            editNonce = modalNonceField.value;
        } else if (!editNonce && modalForm) {
            var formNonceAttr = modalForm.getAttribute('data-edit-nonce');
            if (formNonceAttr) {
                editNonce = formNonceAttr;
            }
        }

        function updateEditNonce(value) {
            if (!value) {
                return;
            }
            editNonce = value;
            if (modalNonceField) {
                modalNonceField.value = value;
                modalNonceField.setAttribute('value', value);
            }
            if (modalForm) {
                modalForm.setAttribute('data-edit-nonce', value);
            }
        }

        function getEditNonce() {
            if (modalNonceField && modalNonceField.value) {
                return modalNonceField.value;
            }
            if (modalForm) {
                var attr = modalForm.getAttribute('data-edit-nonce');
                if (attr) {
                    return attr;
                }
            }
            return editNonce || '';
        }

        var activeChildId = null;
        var activeTrigger = null;

        function clearModalFeedback() {
            if (!modalFeedback) {
                return;
            }
            modalFeedback.classList.remove('is-visible', 'is-error', 'is-success');
            modalFeedback.removeAttribute('role');
            modalFeedback.textContent = '';
        }

        function showModalFeedback(message, type) {
            if (!modalFeedback) {
                window.alert(message);
                return;
            }
            clearModalFeedback();
            modalFeedback.textContent = message;
            modalFeedback.classList.add('is-visible');
            if (type === 'error') {
                modalFeedback.classList.add('is-error');
                modalFeedback.setAttribute('role', 'alert');
            } else {
                modalFeedback.classList.add('is-success');
                modalFeedback.setAttribute('role', 'status');
            }
        }

        function closeModal() {
            if (!modal) {
                return;
            }
            modal.classList.remove('is-visible');
            modal.setAttribute('aria-hidden', 'true');
            activeChildId = null;
            activeTrigger = null;
            clearModalFeedback();
            if (modalForm) {
                modalForm.reset();
            }
        }

        function fillField(selector, value) {
            if (!modalForm) {
                return;
            }
            var field = modalForm.querySelector(selector);
            if (field) {
                field.value = value || '';
            }
        }

        function setCheckbox(selector, checked) {
            if (!modalForm) {
                return;
            }
            var field = modalForm.querySelector(selector);
            if (field) {
                field.checked = !!checked;
            }
        }

        function openModal(profile, trigger) {
            if (!modal || !modalForm || !getEditNonce()) {
                window.alert(editErrorText);
                return;
            }
            activeChildId = profile && profile.id ? parseInt(profile.id, 10) : 0;
            if (!activeChildId) {
                window.alert(childMissingText);
                return;
            }
            activeTrigger = trigger || null;
            clearModalFeedback();
            modal.classList.add('is-visible');
            modal.setAttribute('aria-hidden', 'false');
            if (modalSubtitle) {
                modalSubtitle.textContent = profile.full_name || '';
            }
            fillField('input[name="child_id"]', activeChildId);
            fillField('input[name="first_name"]', profile.first_name || '');
            fillField('input[name="last_name"]', profile.last_name || '');
            fillField('input[name="email"]', profile.email || '');
            fillField('input[name="phone"]', profile.phone || '');
            fillField('input[name="birth_date"]', profile.birth_date || '');
            fillField('textarea[name="notes"]', profile.notes || '');
            setCheckbox('input[name="is_autonomous"]', profile.is_autonomous);
            setCheckbox('input[name="photo_usage_consent"]', profile.photo_usage_consent);
            var firstField = modalForm.querySelector('input[name="first_name"]');
            if (firstField) {
                firstField.focus();
                firstField.select();
            }
        }

        if (modal && modalForm) {
            modalForm.addEventListener('submit', function(event) {
                event.preventDefault();
                if (!ajaxUrl || !getEditNonce()) {
                    showModalFeedback(editErrorText, 'error');
                    return;
                }
                var submitButton = modalForm.querySelector('button[type="submit"]');
                var originalText = submitButton ? submitButton.textContent : '';
                if (submitButton) {
                    submitButton.disabled = true;
                    submitButton.dataset.originalText = originalText;
                    submitButton.textContent = loadingText;
                }
                clearModalFeedback();
                var formData = new FormData(modalForm);
                formData.append('action', 'mj_member_update_child_profile');
                var currentNonce = getEditNonce();
                if (!currentNonce) {
                    showModalFeedback(editErrorText, 'error');
                    if (submitButton) {
                        submitButton.disabled = false;
                        submitButton.textContent = submitButton.dataset.originalText || originalText;
                    }
                    return;
                }
                formData.set('nonce', currentNonce);
                var isAutonomousField = modalForm.querySelector('input[name="is_autonomous"]');
                var photoConsentField = modalForm.querySelector('input[name="photo_usage_consent"]');
                formData.set('is_autonomous', isAutonomousField && isAutonomousField.checked ? '1' : '0');
                formData.set('photo_usage_consent', photoConsentField && photoConsentField.checked ? '1' : '0');
                fetch(ajaxUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: formData
                }).then(function(response) {
                    if (!response.ok) {
                        throw new Error('http_error');
                    }
                    return response.json();
                }).then(function(json) {
                    if (!json || !json.success) {
                        var message = json && json.data && json.data.message ? json.data.message : editErrorText;
                        throw new Error(message);
                    }
                    var childData = json.data && json.data.child ? json.data.child : null;
                    if (childData) {
                        if (modalSubtitle) {
                            modalSubtitle.textContent = childData.full_name || '';
                        }
                        if (childData.profile) {
                            fillField('input[name="first_name"]', childData.profile.first_name || '');
                            fillField('input[name="last_name"]', childData.profile.last_name || '');
                            fillField('input[name="email"]', childData.profile.email || '');
                            fillField('input[name="phone"]', childData.profile.phone || '');
                            fillField('input[name="birth_date"]', childData.profile.birth_date || '');
                            fillField('textarea[name="notes"]', childData.profile.notes || '');
                            setCheckbox('input[name="is_autonomous"]', childData.profile.is_autonomous);
                            setCheckbox('input[name="photo_usage_consent"]', childData.profile.photo_usage_consent);
                        }
                        if (childData.id) {
                            var listItem = document.querySelector('.mj-member-subscription__children-item[data-child-id="' + childData.id + '"]');
                            if (listItem) {
                                var nameEl = listItem.querySelector('.mj-member-subscription__children-name');
                                if (nameEl && childData.full_name) {
                                    nameEl.textContent = childData.full_name;
                                }
                                var editBtn = listItem.querySelector('.mj-member-child-edit');
                                if (editBtn && childData.profile) {
                                    try {
                                        editBtn.setAttribute('data-child-profile', JSON.stringify(childData.profile));
                                    } catch (ignore) {}
                                }
                            }
                        }
                        if (activeTrigger && childData.profile) {
                            try {
                                activeTrigger.setAttribute('data-child-profile', JSON.stringify(childData.profile));
                            } catch (ignore) {}
                        }
                    }
                    if (json.data && json.data.nonce) {
                        updateEditNonce(json.data.nonce);
                    }
                    var successMessage = editSuccessText || editSavedText;
                    showModalFeedback(successMessage, 'success');
                }).catch(function(error) {
                    var message = error && error.message && error.message !== 'http_error' ? error.message : editErrorText;
                    showModalFeedback(message, 'error');
                }).finally(function() {
                    if (submitButton) {
                        submitButton.disabled = false;
                        submitButton.textContent = submitButton.dataset.originalText || originalText;
                    }
                });
            });
        }

        document.addEventListener('click', function(event) {
            if (!event || !event.target) {
                return;
            }
            if (modal && modal.classList.contains('is-visible')) {
                var dismissTarget = event.target.closest ? event.target.closest('[data-modal-dismiss="true"]') : null;
                if (dismissTarget) {
                    event.preventDefault();
                    closeModal();
                    return;
                }
                if (event.target === modal) {
                    event.preventDefault();
                    closeModal();
                    return;
                }
            }
            var editButton = event.target.closest ? event.target.closest('.mj-member-child-edit') : null;
            if (editButton) {
                event.preventDefault();
                if (!modal || !modalForm || !getEditNonce()) {
                    window.alert(editErrorText);
                    return;
                }
                var profileAttr = editButton.getAttribute('data-child-profile');
                var profileData = {};
                if (profileAttr) {
                    try {
                        profileData = JSON.parse(profileAttr);
                    } catch (ignore) {
                        profileData = {};
                    }
                }
                if (!profileData || typeof profileData !== 'object') {
                    profileData = {};
                }
                profileData.id = editButton.getAttribute('data-child-id') || profileData.id || '';
                openModal(profileData, editButton);
            }
        });

        document.addEventListener('keydown', function(event) {
            if (event && event.key === 'Escape' && modal && modal.classList.contains('is-visible')) {
                closeModal();
            }
        });

        document.addEventListener('submit', function(event) {
            var form = event.target;
            if (!form || !form.classList) {
                return;
            }
            var isPrimary = form.classList.contains('mj-member-subscription__form');
            var isChild = form.classList.contains('mj-member-subscription__child-form');
            if (!isPrimary && !isChild) {
                return;
            }
            var nonce = form.getAttribute('data-ajax-nonce');
            if (!nonce) {
                return;
            }
            event.preventDefault();
            var submit = form.querySelector('.mj-member-button');
            var originalText = submit ? submit.textContent : '';
            if (submit) {
                submit.disabled = true;
                submit.dataset.originalText = originalText;
                submit.textContent = loadingText;
            }
            var payload = new FormData();
            if (isPrimary) {
                payload.append('action', 'mj_member_create_payment_link');
            } else {
                var childId = form.getAttribute('data-child-id');
                if (!childId) {
                    if (submit) {
                        submit.disabled = false;
                        submit.textContent = submit.dataset.originalText || originalText;
                    }
                    window.alert(childMissingText);
                    return;
                }
                payload.append('action', 'mj_member_create_child_payment_link');
                payload.append('child_id', childId);
            }
            payload.append('nonce', nonce);
            fetch(ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: payload
            }).then(function(response) {
                if (!response.ok) {
                    throw new Error('http_error');
                }
                return response.json();
            }).then(function(json) {
                if (json && json.success && json.data && json.data.redirect_url) {
                    window.location.href = json.data.redirect_url;
                    return;
                }
                var message = json && json.data && json.data.message ? json.data.message : errorText;
                throw new Error(message);
            }).catch(function(error) {
                var message = error && error.message && error.message !== 'http_error' ? error.message : errorText;
                if (submit) {
                    submit.disabled = false;
                    submit.textContent = submit.dataset.originalText || originalText;
                }
                window.alert(message);
            });
        }, true);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize);
    } else {
        initialize();
    }
})();
</script>
<?php
            echo ob_get_clean();
        }

        static $modal_printed = false;
        if (!$modal_printed && $has_child_edit) {
            $modal_printed = true;
            echo '<div id="mj-member-child-modal" class="mj-member-child-modal" aria-hidden="true">';
            echo '<div class="mj-member-child-modal__backdrop" data-modal-dismiss="true"></div>';
            echo '<div class="mj-member-child-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="mj-member-child-modal__title">';
            echo '<button type="button" class="mj-member-child-modal__close" data-modal-dismiss="true" aria-label="' . esc_attr__('Fermer la fenêtre', 'mj-member') . '">&times;</button>';
            echo '<h3 class="mj-member-child-modal__title" id="mj-member-child-modal__title">' . esc_html__('Modifier les informations du jeune', 'mj-member') . '</h3>';
            echo '<p class="mj-member-child-modal__subtitle"></p>';
            echo '<div class="mj-member-child-modal__feedback" aria-live="polite"></div>';
            echo '<form class="mj-member-child-modal__form" data-edit-nonce="' . esc_attr($child_edit_nonce) . '">';
            echo '<input type="hidden" name="nonce" value="' . esc_attr($child_edit_nonce) . '" />';
            echo '<input type="hidden" name="child_id" value="" />';
            echo '<label><span>' . esc_html__('Prénom', 'mj-member') . '</span><input type="text" name="first_name" required /></label>';
            echo '<label><span>' . esc_html__('Nom', 'mj-member') . '</span><input type="text" name="last_name" required /></label>';
            echo '<label><span>' . esc_html__('Adresse email', 'mj-member') . '</span><input type="email" name="email" /></label>';
            echo '<label><span>' . esc_html__('Téléphone', 'mj-member') . '</span><input type="tel" name="phone" /></label>';
            echo '<label><span>' . esc_html__('Date de naissance', 'mj-member') . '</span><input type="date" name="birth_date" /></label>';
            echo '<label><span>' . esc_html__('Notes internes', 'mj-member') . '</span><textarea name="notes"></textarea></label>';
            echo '<label class="mj-member-child-modal__checkbox"><input type="checkbox" name="is_autonomous" value="1" />' . esc_html__('Ce jeune est autonome', 'mj-member') . '</label>';
            echo '<label class="mj-member-child-modal__checkbox"><input type="checkbox" name="photo_usage_consent" value="1" />' . esc_html__('Autorisation d\'utilisation d\'image', 'mj-member') . '</label>';
            echo '<div class="mj-member-child-modal__footer">';
            echo '<button type="button" class="mj-member-button mj-member-button--ghost" data-modal-dismiss="true">' . esc_html__('Annuler', 'mj-member') . '</button>';
            echo '<button type="submit" class="mj-member-button mj-member-button--primary">' . esc_html__('Enregistrer', 'mj-member') . '</button>';
            echo '</div>';
            echo '</form>';
            echo '</div>';
            echo '</div>';
        }

        $manual_message = isset($settings['manual_payment_message']) ? trim((string) $settings['manual_payment_message']) : '';

        if ($should_display_button && $manual_message !== '') {
            $amount_with_currency = sprintf('%s €', $amount_label);
            $message_prepared = str_replace('[montant_cotisation]', esc_html($amount_with_currency), $manual_message);
            $message_prepared = wp_kses_post($message_prepared);
            $message_prepared = preg_replace('/\r\n|\r|\n/', '<br>', $message_prepared);
            if ($message_prepared !== '') {
                echo '<div class="mj-member-subscription__hint">' . $message_prepared . '</div>';
            }
        }

        if ($should_display_button) {
            $ajax_nonce = wp_create_nonce('mj_member_create_payment_link');
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="mj-member-subscription__form" data-ajax-nonce="' . esc_attr($ajax_nonce) . '">';
            echo '<input type="hidden" name="action" value="mj_member_generate_payment_link" />';
            echo '<input type="hidden" name="redirect_to" value="' . esc_attr($redirect_to) . '" />';
            wp_nonce_field('mj_member_generate_payment_link', 'mj_member_payment_link_nonce');
            echo '<button type="submit" class="mj-member-button">' . esc_html($button_label) . '</button>';
            echo '</form>';
        }

        echo '</div>';
    }
}
