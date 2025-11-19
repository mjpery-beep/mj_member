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
                . '</style>';
        }

            static $script_printed = false;

        echo '<div class="' . esc_attr($card_class_attr) . '">';
        echo '<div class="mj-member-subscription__header">';
        $title = isset($settings['title']) ? $settings['title'] : '';
        if ($title !== '') {
            echo '<h3 class="mj-member-subscription__title">' . esc_html($title) . '</h3>';
        }
        echo '<span class="mj-member-subscription__badge">' . esc_html($status['status_label']) . '</span>';
        echo '</div>';

        echo '<p class="mj-member-subscription__details">' . esc_html($status['description']) . '</p>';

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

            if (!$script_printed) {
                $script_printed = true;
                $config = array(
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'i18n' => array(
                        'loading' => __('Redirection vers Stripe...', 'mj-member'),
                        'error' => __('Impossible de générer le lien de paiement. Merci de réessayer.', 'mj-member'),
                    ),
                );
                echo '<script>window.mjMemberPaymentConfig = ' . wp_json_encode($config) . ';</script>';
                echo '<script>(function(){if(window.mjMemberPaymentInit){return;}window.mjMemberPaymentInit=true;var config=window.mjMemberPaymentConfig||{};if(!config.ajaxUrl){return;}var loadingText=config.i18n&&config.i18n.loading?config.i18n.loading:"Redirection...";var errorText=config.i18n&&config.i18n.error?config.i18n.error:"Une erreur est survenue.";document.addEventListener("submit",function(event){var form=event.target;if(!form.classList||!form.classList.contains("mj-member-subscription__form")){return;}var nonce=form.getAttribute("data-ajax-nonce");if(!nonce){return;}event.preventDefault();var submit=form.querySelector(".mj-member-button");var originalText=submit?submit.textContent:"";if(submit){submit.disabled=true;submit.dataset.originalText=originalText;submit.textContent=loadingText;}var payload=new FormData();payload.append("action","mj_member_create_payment_link");payload.append("nonce",nonce);fetch(config.ajaxUrl,{method:"POST",credentials:"same-origin",body:payload}).then(function(response){if(!response.ok){throw new Error("http_error");}return response.json();}).then(function(json){if(json&&json.success&&json.data&&json.data.redirect_url){window.location.href=json.data.redirect_url;return;}var message=json&&json.data&&json.data.message?json.data.message:errorText;throw new Error(message);}).catch(function(error){var message=error&&error.message&&error.message!=="http_error"?error.message:errorText;if(submit){submit.disabled=false;submit.textContent=submit.dataset.originalText||originalText;}window.alert(message);});},true);})();</script>';
            }

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
