<?php

if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Controls_Manager;
use Elementor\Widget_Base;
use Mj\Member\Core\AssetsManager;

class Mj_Member_Elementor_Notification_Preferences_Widget extends Widget_Base {
    use Mj_Member_Elementor_Widget_Visibility;

    public function get_name() {
        return 'mj-member-notification-preferences';
    }

    public function get_title() {
        return __('Notifications MJ', 'mj-member');
    }

    public function get_icon() {
        return 'eicon-notification';
    }

    public function get_categories() {
        return array('general');
    }

    public function get_keywords() {
        return array('mj', 'member', 'notifications', 'email', 'sms', 'preferences');
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
                'default' => __('Notifications et alertes', 'mj-member'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'description',
            array(
                'label' => __('Description', 'mj-member'),
                'type' => Controls_Manager::TEXTAREA,
                'rows' => 3,
                'placeholder' => __('Choisissez les emails et SMS que vous souhaitez recevoir.', 'mj-member'),
            )
        );

        $this->add_control(
            'email_group_title',
            array(
                'label' => __('Titre – Emails', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Notifications par email', 'mj-member'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'email_group_description',
            array(
                'label' => __('Description – Emails', 'mj-member'),
                'type' => Controls_Manager::TEXTAREA,
                'rows' => 2,
                'placeholder' => __('Recevez un résumé complet directement dans votre boîte mail.', 'mj-member'),
            )
        );

        $this->add_control(
            'sms_group_title',
            array(
                'label' => __('Titre – SMS', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Notifications par SMS', 'mj-member'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'sms_group_description',
            array(
                'label' => __('Description – SMS', 'mj-member'),
                'type' => Controls_Manager::TEXTAREA,
                'rows' => 2,
                'placeholder' => __('Recevez uniquement les alertes importantes directement sur votre mobile.', 'mj-member'),
            )
        );

        $this->add_control(
            'submit_label',
            array(
                'label' => __('Texte du bouton', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Enregistrer mes préférences', 'mj-member'),
                'label_block' => true,
            )
        );

        $this->end_controls_section();

        $this->register_visibility_controls();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $this->apply_visibility_to_wrapper($settings, 'mj-notification-preferences');

        if (!class_exists('MjMembers')) {
            echo '<div class="mj-member-account-warning">' . esc_html__('Le module MJ Member doit être actif pour utiliser ce widget.', 'mj-member') . '</div>';
            return;
        }

        $title = isset($settings['title']) ? $settings['title'] : '';
        $description = isset($settings['description']) ? $settings['description'] : '';
        $email_group_title = isset($settings['email_group_title']) ? $settings['email_group_title'] : __('Notifications par email', 'mj-member');
        $email_group_description = isset($settings['email_group_description']) ? $settings['email_group_description'] : '';
        $sms_group_title = isset($settings['sms_group_title']) ? $settings['sms_group_title'] : __('Notifications par SMS', 'mj-member');
        $sms_group_description = isset($settings['sms_group_description']) ? $settings['sms_group_description'] : '';
        $submit_label = isset($settings['submit_label']) && $settings['submit_label'] !== ''
            ? $settings['submit_label']
            : __('Enregistrer mes préférences', 'mj-member');

        $is_preview = function_exists('mj_member_login_component_is_preview_mode')
            ? mj_member_login_component_is_preview_mode()
            : (did_action('elementor/loaded') && isset(\Elementor\Plugin::$instance->editor) && \Elementor\Plugin::$instance->editor->is_edit_mode());

        $member = null;
        if (is_user_logged_in() && function_exists('mj_member_get_current_member')) {
            $member = mj_member_get_current_member();
        }

        if (!$member && !$is_preview) {
            $redirect = function_exists('mj_member_get_account_redirect')
                ? mj_member_get_account_redirect()
                : home_url('/mon-compte');
            $login_url = wp_login_url($redirect);
            echo '<div class="mj-member-account-warning">'
                . esc_html__('Connectez-vous pour gérer vos notifications.', 'mj-member')
                . ' <a href="' . esc_url($login_url) . '">' . esc_html__('Se connecter', 'mj-member') . '</a>'
                . '</div>';
            return;
        }

        $preferences = array();
        if ($member && isset($member->id)) {
            $preferences = MjMembers::getNotificationPreferences($member->id);
        } else {
            $preferences = MjMembers::getNotificationPreferenceDefaults(array(
                'newsletter_opt_in' => 1,
                'sms_opt_in' => 1,
            ));
        }

        $email_options = array(
            'email_event_registration' => array(
                'label' => __('Confirmations d\'inscription', 'mj-member'),
                'description' => __('Recevoir un email pour chaque inscription validée.', 'mj-member'),
            ),
            'email_payment_receipts' => array(
                'label' => __('Justificatifs de paiement', 'mj-member'),
                'description' => __('Suivi des paiements et confirmations Stripe.', 'mj-member'),
            ),
            'email_membership_reminders' => array(
                'label' => __('Rappels de cotisation', 'mj-member'),
                'description' => __('Être averti lorsque la cotisation annuelle approche de son échéance.', 'mj-member'),
            ),
            'email_event_reminders' => array(
                'label' => __('Rappels d\'événements', 'mj-member'),
                'description' => __('Recevoir un rappel quelques heures avant le début d\'un événement.', 'mj-member'),
            ),
            'email_event_news' => array(
                'label' => __('Nouveaux événements', 'mj-member'),
                'description' => __('Être averti dès qu\'un nouvel événement est publié.', 'mj-member'),
            ),
        );

        $sms_options = array(
            'sms_event_registration' => array(
                'label' => __('Confirmation instantanée', 'mj-member'),
                'description' => __('Recevoir un SMS lorsque une inscription est enregistrée.', 'mj-member'),
            ),
            'sms_payment_receipts' => array(
                'label' => __('Paiement reçu', 'mj-member'),
                'description' => __('Être averti par SMS lorsqu\'un paiement est confirmé.', 'mj-member'),
            ),
            'sms_membership_reminders' => array(
                'label' => __('Relances cotisation', 'mj-member'),
                'description' => __('Recevoir un rappel par SMS avant la fin de validité de la cotisation.', 'mj-member'),
            ),
            'sms_event_reminders' => array(
                'label' => __('Rappel événement', 'mj-member'),
                'description' => __('Être averti par SMS avant le début d\'un événement.', 'mj-member'),
            ),
            'sms_event_news' => array(
                'label' => __('Infos MJ', 'mj-member'),
                'description' => __('Recevoir les annonces importantes directement sur votre mobile.', 'mj-member'),
            ),
        );

        AssetsManager::requirePackage('notification-preferences');

        static $script_localized = false;
        if (!$script_localized) {
            wp_localize_script(
                'mj-member-notification-preferences',
                'MjMemberNotificationPreferences',
                array(
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('mj_member_update_notification_preferences'),
                    'strings' => array(
                        'saving' => __('Enregistrement…', 'mj-member'),
                        'success' => __('Préférences enregistrées.', 'mj-member'),
                        'error' => __('Impossible d’enregistrer vos préférences.', 'mj-member'),
                        'genericError' => __('Une erreur est survenue.', 'mj-member'),
                        'networkError' => __('Impossible de contacter le serveur.', 'mj-member'),
                    ),
                )
            );
            $script_localized = true;
        }

        $email_test_mode = get_option('mj_email_test_mode', '0') === '1';
        $sms_test_mode = get_option('mj_sms_test_mode', '0') === '1';
        $sms_provider = get_option('mj_sms_provider', 'disabled');
        $sms_provider_ready = $sms_provider !== 'disabled' && get_option('mj_sms_textbelt_api_key', '') !== '';

        $component_config = array(
            'preferences' => $preferences,
            'preview' => $is_preview && !$member,
        );

        $config_json = wp_json_encode($component_config);
        if (!is_string($config_json)) {
            $config_json = '{}';
        }

        $component_classes = array('mj-member-notifications');
        if ($is_preview && !$member) {
            $component_classes[] = 'is-preview';
        }

        $component_attributes = array(
            'class' => esc_attr(implode(' ', array_unique($component_classes))),
            'data-mj-notification-preferences' => '1',
            'data-config' => esc_attr($config_json),
        );

        if ($is_preview && !$member) {
            $component_attributes['data-preview'] = '1';
        }

        $component_attr_html = '';
        foreach ($component_attributes as $key => $value) {
            if ($value === '') {
                $component_attr_html .= ' ' . $key;
            } else {
                $component_attr_html .= ' ' . $key . '="' . $value . '"';
            }
        }

        echo '<div' . $component_attr_html . '>';

        echo '<div class="mj-member-notifications__header">';
        if ($title !== '') {
            echo '<h3 class="mj-member-notifications__title">' . esc_html($title) . '</h3>';
        }
        if ($description !== '') {
            echo '<p class="mj-member-notifications__description">' . esc_html($description) . '</p>';
        }
        echo '</div>';

        if ($email_test_mode) {
            echo '<p class="mj-member-notifications__alert mj-member-notifications__alert--info">' . esc_html__("Le mode test email est activé : aucun message ne sera réellement envoyé.", 'mj-member') . '</p>';
        }
        if ($sms_test_mode) {
            echo '<p class="mj-member-notifications__alert mj-member-notifications__alert--info">' . esc_html__("Le mode test SMS est activé : les messages sont journalisés mais non transmis.", 'mj-member') . '</p>';
        }
        if ($member && MjMembers::hasField($member, 'sms_opt_in') && (int) MjMembers::getField($member, 'sms_opt_in', 0) === 0) {
            echo '<p class="mj-member-notifications__alert">' . esc_html__("Vous avez actuellement refusé les SMS. Activez au moins une option pour rétablir l’autorisation.", 'mj-member') . '</p>';
        }
        if ($sms_provider === 'disabled' || !$sms_provider_ready) {
            echo '<p class="mj-member-notifications__alert">' . esc_html__("Le service SMS n’est pas configuré. Les alertes SMS resteront en attente.", 'mj-member') . '</p>';
        }

        echo '<div class="mj-member-notifications__feedback" data-role="feedback" aria-live="polite"></div>';

        echo '<form class="mj-member-notifications__form" data-role="notifications-form">';
        echo '<div class="mj-member-notifications__columns">';

        echo '<section class="mj-member-notifications__group">';
        echo '<h4 class="mj-member-notifications__group-title">' . esc_html($email_group_title) . '</h4>';
        if ($email_group_description !== '') {
            echo '<p class="mj-member-notifications__group-description">' . esc_html($email_group_description) . '</p>';
        }
        echo '<ul class="mj-member-notifications__options">';

        $component_id = sanitize_html_class($this->get_id());
        foreach ($email_options as $key => $option) {
            $field_id = $component_id . '-' . sanitize_html_class($key);
            $checked = !empty($preferences[$key]);
            echo '<li class="mj-member-notifications__option">';
            echo '<label class="mj-member-notifications__checkbox-wrapper" for="' . esc_attr($field_id) . '">';
            echo '<input type="checkbox" id="' . esc_attr($field_id) . '" class="mj-member-notifications__checkbox" data-preference-key="' . esc_attr($key) . '"' . checked($checked, true, false) . ' />';
            echo '<span class="mj-member-notifications__switch" aria-hidden="true"></span>';
            echo '</label>';
            echo '<div class="mj-member-notifications__texts">';
            echo '<span class="mj-member-notifications__option-title">' . esc_html($option['label']) . '</span>';
            if (!empty($option['description'])) {
                echo '<span class="mj-member-notifications__option-description">' . esc_html($option['description']) . '</span>';
            }
            echo '</div>';
            echo '</li>';
        }
        echo '</ul>';
        echo '</section>';

        echo '<section class="mj-member-notifications__group">';
        echo '<h4 class="mj-member-notifications__group-title">' . esc_html($sms_group_title) . '</h4>';
        if ($sms_group_description !== '') {
            echo '<p class="mj-member-notifications__group-description">' . esc_html($sms_group_description) . '</p>';
        }
        echo '<ul class="mj-member-notifications__options">';

        foreach ($sms_options as $key => $option) {
            $field_id = $component_id . '-' . sanitize_html_class($key);
            $checked = !empty($preferences[$key]);
            echo '<li class="mj-member-notifications__option">';
            echo '<label class="mj-member-notifications__checkbox-wrapper" for="' . esc_attr($field_id) . '">';
            echo '<input type="checkbox" id="' . esc_attr($field_id) . '" class="mj-member-notifications__checkbox" data-preference-key="' . esc_attr($key) . '"' . checked($checked, true, false) . ' />';
            echo '<span class="mj-member-notifications__switch" aria-hidden="true"></span>';
            echo '</label>';
            echo '<div class="mj-member-notifications__texts">';
            echo '<span class="mj-member-notifications__option-title">' . esc_html($option['label']) . '</span>';
            if (!empty($option['description'])) {
                echo '<span class="mj-member-notifications__option-description">' . esc_html($option['description']) . '</span>';
            }
            echo '</div>';
            echo '</li>';
        }
        echo '</ul>';
        echo '</section>';

        echo '</div>';

        echo '<div class="mj-member-notifications__footer">';
        echo '<button type="submit" class="mj-member-button" data-role="submit">' . esc_html($submit_label) . '</button>';
        echo '</div>';

        echo '</form>';
        echo '</div>';
    }
}
