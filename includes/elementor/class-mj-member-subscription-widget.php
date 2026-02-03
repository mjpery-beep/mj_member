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
    use Mj_Member_Elementor_Widget_Visibility;

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
        return array('mj-member');
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
                'default' => __('Tu préfères le contact direct ? Remets [montant_cotisation] à un animateur, ca fonctionne aussi !', 'mj-member'),
                'rows' => 3,
                'placeholder' => __('Laissez vide pour ne pas afficher de message.', 'mj-member'),
            )
        );

        $this->end_controls_section();

        $this->register_visibility_controls();

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
        $settings = $this->get_settings_for_display();
        $this->apply_visibility_to_wrapper($settings, 'mj-member-subscription');

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
        $manual_amount_option = get_option('mj_annual_fee_manual', '');
        $manual_amount_label = $amount_label;
        if ($manual_amount_option !== '' && is_numeric($manual_amount_option)) {
            $manual_amount_label = number_format((float) $manual_amount_option, 2, ',', ' ');
        }
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
            if (!empty($children_statuses) && (empty($settings['manual_payment_message']) || $settings['manual_payment_message'] === __('Tu préfères le contact direct ? Remets [montant_cotisation] à un animateur, ca fonctionne aussi !', 'mj-member'))) {
                $settings['manual_payment_message'] = sprintf(
                    __('Chaque jeune peut remettre %s € directement à un animateur si tu préfères ce mode de paiement.', 'mj-member'),
                    $manual_amount_label
                );
            }
        }

        $allow_child_edit = !$is_guardian;

        $child_edit_nonce = (!empty($children_statuses) && $allow_child_edit) ? wp_create_nonce('mj_member_update_child_profile') : '';
        $child_payment_nonce = (!empty($children_statuses) && $is_guardian) ? wp_create_nonce('mj_member_create_child_payment_link') : '';

        $card_classes = array('mj-member-subscription', 'status-' . sanitize_html_class($status['status']));
        $card_class_attr = implode(' ', array_unique($card_classes));

        $redirect_to = mj_member_get_current_url();

        static $styles_printed = false;
        if (!$styles_printed) {
            $styles_printed = true;
            ob_start();
            ?>
<style>
.mj-member-subscription {
    position: relative;
    overflow: hidden;
    border-radius: 24px;
    padding: 2.2rem;
    border: 1px solid rgba(15, 23, 42, 0.08);
    background: linear-gradient(135deg, #ffffff 5%, #f7f8ff 100%);
    box-shadow: 0 24px 48px rgba(15, 23, 42, 0.12);
    display: grid;
    gap: 1.4rem;
    color: #0f172a;
}
.mj-member-subscription::before,
.mj-member-subscription::after {
    content: "";
    position: absolute;
    border-radius: 50%;
    z-index: 0;
}
.mj-member-subscription::before {
    top: -80px;
    right: -90px;
    width: 240px;
    height: 240px;
    background: radial-gradient(circle at center, rgba(59, 130, 246, 0.37), transparent 68%);
}
.mj-member-subscription::after {
    bottom: -120px;
    left: -80px;
    width: 200px;
    height: 200px;
    background: radial-gradient(circle at center, rgba(79, 70, 229, 0.22), transparent 70%);
}
.mj-member-subscription > * {
    position: relative;
    z-index: 1;
}
.mj-member-subscription.status-active {
    border-color: rgba(34, 197, 94, 0.22);
}
.mj-member-subscription.status-expiring {
    border-color: rgba(250, 204, 21, 0.35);
}
.mj-member-subscription.status-expired {
    border-color: rgba(239, 68, 68, 0.45);
    background: linear-gradient(135deg, #fff5f5 5%, #fee2e2 100%);
    box-shadow: 0 26px 54px rgba(220, 38, 38, 0.2);
}
.mj-member-subscription.status-expired::before {
    background: radial-gradient(circle at center, rgba(248, 113, 113, 0.52), transparent 72%);
}
.mj-member-subscription.status-expired::after {
    background: radial-gradient(circle at center, rgba(239, 68, 68, 0.32), transparent 74%);
}
.mj-member-subscription.status-missing {
    border-color: rgba(251, 191, 36, 0.45);
    background: linear-gradient(135deg, #fff7ed 5%, #fde68a 100%);
    box-shadow: 0 26px 54px rgba(217, 119, 6, 0.18);
}
.mj-member-subscription.status-missing::before {
    background: radial-gradient(circle at center, rgba(251, 191, 36, 0.38), transparent 70%);
}
.mj-member-subscription.status-missing::after {
    background: radial-gradient(circle at center, rgba(234, 179, 8, 0.26), transparent 72%);
}
.mj-member-subscription__header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1.5rem;
}
.mj-member-subscription__title {
    margin: 0;
    font-size: 2rem;
    font-weight: 700;
    letter-spacing: -0.02em;
}
.mj-member-subscription__badge {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 52px;
    height: 52px;
    border-radius: 50%;
    background: rgba(59, 130, 246, 0.12);
    color: #1d4ed8;
    box-shadow: 0 14px 28px rgba(59, 130, 246, 0.2);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.mj-member-subscription__badge:hover,
.mj-member-subscription__badge:focus-visible {
    transform: translateY(-2px);
    box-shadow: 0 18px 34px rgba(59, 130, 246, 0.25);
}
.mj-member-subscription__badge-symbol {
    font-size: 1.35rem;
    line-height: 1;
}
.mj-member-subscription.status-active .mj-member-subscription__badge {
    background: rgba(34, 197, 94, 0.18);
    color: #0f766e;
    box-shadow: 0 14px 28px rgba(34, 197, 94, 0.2);
}
.mj-member-subscription.status-expiring .mj-member-subscription__badge {
    background: rgba(250, 204, 21, 0.2);
    color: #b45309;
    box-shadow: 0 14px 28px rgba(251, 191, 36, 0.18);
}
.mj-member-subscription.status-expired .mj-member-subscription__badge {
    background: rgba(248, 113, 113, 0.24);
    color: #b91c1c;
    box-shadow: 0 16px 34px rgba(248, 113, 113, 0.28);
}
.mj-member-subscription.status-missing .mj-member-subscription__badge {
    background: rgba(251, 191, 36, 0.24);
    color: #92400e;
    box-shadow: 0 16px 34px rgba(251, 191, 36, 0.24);
}
.mj-member-subscription__badge .screen-reader-text,
.mj-member-subscription__children-badge .screen-reader-text {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}
.mj-member-subscription__details {
    margin: 0;
    font-size: 1.05rem;
    line-height: 1.65;
    color: rgba(15, 23, 42, 0.78);
}
.mj-member-subscription__amount {
    margin: 0;
    font-size: 1.9rem;
    font-weight: 700;
    letter-spacing: -0.03em;
    color: #0f172a;
}
.mj-member-subscription__meta {
    margin: 0;
    font-size: 0.95rem;
    color: rgba(30, 41, 59, 0.78);
}
.mj-member-subscription__meta + .mj-member-subscription__meta {
    margin-top: 0.35rem;
}
.mj-member-subscription__form {
    margin-top: 0.5rem;
}
.mj-member-subscription__form .mj-member-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.45rem;
    padding: 0.85rem 1.9rem;
    border-radius: 16px;
    border: none;
    background: linear-gradient(135deg, #2563eb 0%, #7c3aed 100%);
    color: #ffffff;
    font-size: 0.98rem;
    font-weight: 600;
    box-shadow: 0 20px 38px rgba(79, 70, 229, 0.28);
    cursor: pointer;
    transition: transform 0.2s ease, box-shadow 0.2s ease, filter 0.2s ease;
}
.mj-member-subscription__form .mj-member-button:hover,
.mj-member-subscription__form .mj-member-button:focus-visible {
    transform: translateY(-3px);
    box-shadow: 0 26px 44px rgba(79, 70, 229, 0.32);
    filter: brightness(1.05);
}
.mj-member-subscription__form .mj-member-button:focus-visible {
    outline: 3px solid rgba(79, 70, 229, 0.35);
    outline-offset: 3px;
}
.mj-member-subscription__notice {
    margin-top: 0.5rem;
    padding: 0.9rem 1.1rem;
    border-radius: 14px;
    border: 1px solid rgba(248, 113, 113, 0.35);
    background: rgba(254, 226, 226, 0.65);
    color: #b91c1c;
    font-size: 0.92rem;
    box-shadow: 0 8px 18px rgba(248, 113, 113, 0.16);
}
.mj-member-subscription__hint {
    margin: 0;
    padding: 1rem 1.2rem;
    border-radius: 18px;
    background: rgba(244, 247, 255, 0.9);
    color: #1f2937;
    font-size: 0.93rem;
    box-shadow: 0 10px 20px rgba(59, 130, 246, 0.08);
}
.mj-member-subscription__children {
    margin-top: 1.6rem;
    padding-top: 1.6rem;
    border-top: 1px dashed rgba(148, 163, 184, 0.35);
    display: grid;
    gap: 1.1rem;
}
.mj-member-subscription__children-title {
    margin: 0;
    font-size: 1.15rem;
    font-weight: 700;
    color: #0f172a;
}
.mj-member-subscription__children-note {
    margin: 0;
    padding: 0.75rem 0.95rem;
    border-radius: 16px;
    background: rgba(59, 130, 246, 0.08);
    color: #1d4ed8;
    font-size: 0.9rem;
    box-shadow: 0 10px 20px rgba(59, 130, 246, 0.1);
}
.mj-member-subscription__children-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: grid;
    gap: 0.9rem;
}
.mj-member-subscription__children-item {
    background: rgba(255, 255, 255, 0.9);
    border-radius: 18px;
    padding: 1rem 1.15rem;
    border: 1px solid rgba(148, 163, 184, 0.2);
    box-shadow: 0 14px 26px rgba(15, 23, 42, 0.08);
    display: grid;
    gap: 0.55rem;
}
.mj-member-subscription__children-item.status-expired {
    background: rgba(254, 226, 226, 0.6);
    border-color: rgba(248, 113, 113, 0.45);
}
.mj-member-subscription__children-item.status-expiring {
    background: rgba(254, 249, 195, 0.58);
    border-color: rgba(250, 204, 21, 0.45);
}
.mj-member-subscription__children-item.status-missing {
    background: rgba(254, 243, 199, 0.62);
    border-color: rgba(251, 191, 36, 0.48);
}
.mj-member-subscription__children-item.status-active .mj-member-subscription__children-badge {
    background: rgba(34, 197, 94, 0.18);
    color: #0f766e;
}
.mj-member-subscription__children-item.status-expiring .mj-member-subscription__children-badge,
.mj-member-subscription__children-item.status-missing .mj-member-subscription__children-badge {
    background: rgba(251, 191, 36, 0.25);
    color: #92400e;
}
.mj-member-subscription__children-item.status-expired .mj-member-subscription__children-badge {
    background: rgba(248, 113, 113, 0.26);
    color: #b91c1c;
}
.mj-member-subscription__children-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.8rem;
}
.mj-member-subscription__children-name {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 600;
}
.mj-member-subscription__children-badge {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 42px;
    height: 42px;
    border-radius: 50%;
    font-size: 1.1rem;
    background: rgba(148, 163, 184, 0.2);
    color: #1f2937;
    box-shadow: inset 0 0 0 1px rgba(148, 163, 184, 0.25);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.mj-member-subscription__children-badge:hover,
.mj-member-subscription__children-badge:focus-visible {
    transform: translateY(-2px);
    box-shadow: 0 14px 24px rgba(148, 163, 184, 0.28);
}
.mj-member-subscription__children-badge-symbol {
    line-height: 1;
}
.mj-member-subscription__children-meta {
    margin: 0;
    font-size: 0.9rem;
    line-height: 1.5;
    color: rgba(51, 65, 85, 0.9);
}
.mj-member-subscription__children-amount {
    font-weight: 600;
}
.mj-member-subscription__children-actions {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}
.mj-member-subscription__children-actions-main {
    display: flex;
    flex-wrap: wrap;
    gap: 0.6rem;
}
.mj-member-button--secondary {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.75rem 1.5rem;
    border-radius: 14px;
    border: none;
    background: linear-gradient(135deg, #2563eb, #4c1d95);
    color: #ffffff;
    font-weight: 600;
    box-shadow: 0 20px 32px rgba(79, 70, 229, 0.26);
    cursor: pointer;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.mj-member-button--secondary:hover,
.mj-member-button--secondary:focus-visible {
    transform: translateY(-2px);
    box-shadow: 0 26px 42px rgba(79, 70, 229, 0.3);
}
.mj-member-button--ghost {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.75rem 1.4rem;
    border-radius: 14px;
    border: 1px solid rgba(148, 163, 184, 0.55);
    background: rgba(255, 255, 255, 0.92);
    color: #1f2937;
    font-weight: 600;
    cursor: pointer;
    transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
}
.mj-member-button--ghost:hover,
.mj-member-button--ghost:focus-visible {
    transform: translateY(-2px);
    background: rgba(241, 245, 249, 0.9);
    box-shadow: 0 16px 30px rgba(148, 163, 184, 0.26);
}
.mj-member-subscription__children-pay-note {
    margin: 0;
    font-size: 0.85rem;
    color: rgba(71, 85, 105, 0.9);
}
.mj-member-child-modal {
    position: fixed;
    inset: 0;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
    z-index: 10000;
}
.mj-member-child-modal.is-visible {
    display: flex;
}
.mj-member-child-modal__backdrop {
    position: absolute;
    inset: 0;
    background: rgba(15, 23, 42, 0.55);
}
.mj-member-child-modal__dialog {
    position: relative;
    background: #ffffff;
    border-radius: 16px;
    width: 100%;
    max-width: 520px;
    padding: 24px;
    box-shadow: 0 24px 48px rgba(15, 23, 42, 0.2);
    display: flex;
    flex-direction: column;
    gap: 16px;
}
.mj-member-child-modal__title {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 600;
    color: #0f172a;
}
.mj-member-child-modal__subtitle {
    margin: 0;
    color: #475569;
    font-size: 0.95rem;
}
.mj-member-child-modal__close {
    position: absolute;
    top: 12px;
    right: 12px;
    background: transparent;
    border: none;
    font-size: 1.4rem;
    line-height: 1;
    color: #475569;
    cursor: pointer;
}
.mj-member-child-modal__form {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.mj-member-child-modal__form label {
    display: flex;
    flex-direction: column;
    gap: 6px;
    font-size: 0.9rem;
    font-weight: 600;
    color: #1f2937;
}
.mj-member-child-modal__form input,
.mj-member-child-modal__form textarea {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #cbd5e1;
    border-radius: 10px;
    font-size: 0.95rem;
}
.mj-member-child-modal__form textarea {
    min-height: 96px;
    resize: vertical;
}
.mj-member-child-modal__checkbox {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 500;
    color: #1f2937;
    font-size: 0.9rem;
}
.mj-member-child-modal__footer {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    margin-top: 4px;
}
.mj-member-child-modal__feedback {
    display: none;
    border-radius: 8px;
    padding: 10px 12px;
    font-size: 0.9rem;
}
.mj-member-child-modal__feedback.is-error {
    display: block;
    background: #fee2e2;
    color: #991b1b;
}
.mj-member-child-modal__feedback.is-success {
    display: block;
    background: #dcfce7;
    color: #166534;
}
@media (max-width: 768px) {
    .mj-member-subscription {
        padding: 1.6rem;
        gap: 1.1rem;
    }
    .mj-member-subscription__header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    .mj-member-subscription__title {
        font-size: 1.6rem;
    }
    .mj-member-subscription__children-item {
        padding: 0.9rem 1rem;
    }
    .mj-member-subscription__form .mj-member-button,
    .mj-member-button--secondary,
    .mj-member-button--ghost {
        width: 100%;
    }
}
</style>
<?php
            echo ob_get_clean();
        }

            static $script_printed = false;

        echo '<div class="' . esc_attr($card_class_attr) . '">';
        echo '<div class="mj-member-subscription__header">';
        $title = isset($settings['title']) ? $settings['title'] : '';
        if ($title !== '') {
            echo '<h2 class="mj-member-subscription__title">' . esc_html($title) . '</h2>';
        }
        if (!($is_guardian && !$status['requires_payment'])) {
            $badge_label = isset($status['status_label']) ? $status['status_label'] : '';
            $badge_icon = $this->get_membership_status_icon(isset($status['status']) ? $status['status'] : '');
            echo '<span class="mj-member-subscription__badge" role="img" title="' . esc_attr($badge_label) . '" aria-label="' . esc_attr($badge_label) . '">';
            echo '<span class="mj-member-subscription__badge-symbol" aria-hidden="true">' . esc_html($badge_icon) . '</span>';
            echo '<span class="screen-reader-text">' . esc_html($badge_label) . '</span>';
            echo '</span>';
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
                $child_badge_label = isset($child_entry['status_label']) ? $child_entry['status_label'] : '';
                $child_badge_icon = $this->get_membership_status_icon($child_status);
                echo '<span class="mj-member-subscription__children-badge" role="img" title="' . esc_attr($child_badge_label) . '" aria-label="' . esc_attr($child_badge_label) . '">';
                echo '<span class="mj-member-subscription__children-badge-symbol" aria-hidden="true">' . esc_html($child_badge_icon) . '</span>';
                echo '<span class="screen-reader-text">' . esc_html($child_badge_label) . '</span>';
                echo '</span>';
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
                    $child_payment_label = $is_guardian ? __('Régler la cotisation', 'mj-member') : __('Payer cette cotisation', 'mj-member');
                    echo '<form method="post" class="mj-member-subscription__child-form" data-child-id="' . esc_attr((int) $child_entry['id']) . '" data-ajax-nonce="' . esc_attr($child_payment_nonce) . '">';
                    echo '<button type="submit" class="mj-member-button mj-member-button--secondary">' . esc_html($child_payment_label) . '</button>';
                    echo '</form>';
                }
                if ($allow_child_edit) {
                    echo '<button type="button" class="mj-member-button mj-member-button--ghost mj-member-child-edit" data-child-id="' . esc_attr((int) $child_entry['id']) . '" data-child-profile="' . esc_attr($child_profile_attr) . '">' . esc_html__('Modifier les informations', 'mj-member') . '</button>';
                }
                echo '</div>';
                if (!empty($child_entry['requires_payment'])) {
                    $manual_note = sprintf(__('Ce jeune peut aussi remettre %s € directement à un animateur.', 'mj-member'), $manual_amount_label);
                    echo '<p class="mj-member-subscription__children-pay-note">' . esc_html($manual_note) . '</p>';
                }
                echo '</div>';

                echo '</li>';
            }
            echo '</ul>';
            echo '</div>';
        }

        $should_render_child_forms = !empty($children_statuses) && $child_payment_nonce !== '';
        $has_child_edit = $allow_child_edit && !empty($children_statuses) && $child_edit_nonce !== '';
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
            $manual_amount_with_currency = sprintf('%s €', $manual_amount_label);
            $message_prepared = str_replace('[montant_cotisation]', esc_html($manual_amount_with_currency), $manual_message);
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

    private function get_membership_status_icon($status) {
        $normalized = is_string($status) ? sanitize_key($status) : '';

        switch ($normalized) {
            case 'active':
            case 'paid':
            case 'complete':
                return '✔';
            case 'expiring':
            case 'expiring_soon':
            case 'due_soon':
                return '⏳';
            case 'expired':
                return '✖';
            case 'missing':
            case 'overdue':
            case 'missing_payment':
            case 'unpaid':
                return '⚠';
            case 'pending':
            case 'processing':
            case 'awaiting':
                return '…';
            case 'free':
            case 'not_required':
            case 'complimentary':
                return '◎';
            default:
                return 'ℹ';
        }
    }
}
