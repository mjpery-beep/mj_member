<?php

if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;
use Mj\Member\Core\Config;

class Mj_Member_Elementor_Contact_Form_Widget extends Widget_Base {
    public function get_name() {
        return 'mj-member-contact-form';
    }

    public function get_title() {
        return __('Formulaire de contact MJ', 'mj-member');
    }

    public function get_icon() {
        return 'eicon-form-horizontal';
    }

    public function get_categories() {
        return array('general');
    }

    public function get_keywords() {
        return array('mj', 'contact', 'formulaire', 'animateur');
    }

    public function get_script_depends() {
        return array('mj-member-contact-form');
    }

    public function get_style_depends() {
        return array('mj-member-components');
    }

    protected function register_controls() {
        $this->start_controls_section('section_content', array(
            'label' => __('Contenu', 'mj-member'),
        ));

        $this->add_control('title', array(
            'label' => __('Titre', 'mj-member'),
            'type' => Controls_Manager::TEXT,
            'default' => __('Contacter l’équipe MJ', 'mj-member'),
            'label_block' => true,
        ));

        $this->add_control('description', array(
            'label' => __('Introduction', 'mj-member'),
            'type' => Controls_Manager::TEXTAREA,
            'default' => __('Complétez les informations ci-dessous pour nous envoyer un message.', 'mj-member'),
            'rows' => 4,
        ));

        $this->add_control('success_message', array(
            'label' => __('Message de confirmation', 'mj-member'),
            'type' => Controls_Manager::TEXT,
            'default' => __('Merci ! Votre message a bien été envoyé.', 'mj-member'),
            'label_block' => true,
        ));

        $this->add_control('submit_label', array(
            'label' => __('Libellé du bouton', 'mj-member'),
            'type' => Controls_Manager::TEXT,
            'default' => __('Envoyer le message', 'mj-member'),
            'label_block' => true,
        ));

        $this->add_control('show_member_card', array(
            'label' => __('Afficher la fiche membre connectée', 'mj-member'),
            'type' => Controls_Manager::SWITCHER,
            'label_on' => __('Oui', 'mj-member'),
            'label_off' => __('Non', 'mj-member'),
            'return_value' => 'yes',
            'default' => 'yes',
            'description' => __('Affiche une carte avec les informations du membre actuellement connecté.', 'mj-member'),
        ));

        $this->end_controls_section();

        $this->start_controls_section('section_style_container', array(
            'label' => __('Conteneur', 'mj-member'),
            'tab' => Controls_Manager::TAB_STYLE,
        ));

        $this->add_control('container_background_color', array(
            'label' => __('Fond', 'mj-member'),
            'type' => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .mj-contact-form' => '--mj-contact-surface-bg: {{VALUE}};',
            ),
        ));

        $this->add_control('contact_form_border_color', array(
            'label' => __('Bordure', 'mj-member'),
            'type' => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .mj-contact-form' => '--mj-contact-surface-border: {{VALUE}};',
            ),
        ));

        if (class_exists('Elementor\\Group_Control_Border')) {
            $this->add_group_control(
                Group_Control_Border::get_type(),
                array(
                    'name' => 'container_border',
                    'selector' => '{{WRAPPER}} .mj-contact-form__surface',
                )
            );
        }

        $this->add_responsive_control('container_padding', array(
            'label' => __('Marge intérieure', 'mj-member'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => array('px', 'em', '%'),
            'selectors' => array(
                '{{WRAPPER}} .mj-contact-form__surface' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ),
        ));

        $this->add_responsive_control('container_border_radius', array(
            'label' => __('Rayon de bordure', 'mj-member'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => array('px', '%'),
            'selectors' => array(
                '{{WRAPPER}} .mj-contact-form__surface' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ),
        ));

        if (class_exists('Elementor\\Group_Control_Box_Shadow')) {
            $this->add_group_control(
                Group_Control_Box_Shadow::get_type(),
                array(
                    'name' => 'container_box_shadow',
                    'selector' => '{{WRAPPER}} .mj-contact-form__surface',
                )
            );
        }

        $this->end_controls_section();

        $this->start_controls_section('section_style_header', array(
            'label' => __('En-tête', 'mj-member'),
            'tab' => Controls_Manager::TAB_STYLE,
        ));

        $this->add_control('title_color', array(
            'label' => __('Couleur du titre', 'mj-member'),
            'type' => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .mj-contact-form__title' => 'color: {{VALUE}};',
            ),
        ));

        if (class_exists('Elementor\\Group_Control_Typography')) {
            $this->add_group_control(
                Group_Control_Typography::get_type(),
                array(
                    'name' => 'title_typography',
                    'selector' => '{{WRAPPER}} .mj-contact-form__title',
                )
            );
        }

        $this->add_control('description_color', array(
            'label' => __('Couleur du texte', 'mj-member'),
            'type' => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .mj-contact-form__description' => 'color: {{VALUE}};',
            ),
        ));

        if (class_exists('Elementor\\Group_Control_Typography')) {
            $this->add_group_control(
                Group_Control_Typography::get_type(),
                array(
                    'name' => 'description_typography',
                    'selector' => '{{WRAPPER}} .mj-contact-form__description',
                )
            );
        }

        $this->end_controls_section();

        $this->start_controls_section('section_style_fields', array(
            'label' => __('Champs', 'mj-member'),
            'tab' => Controls_Manager::TAB_STYLE,
        ));

        $this->add_responsive_control('fields_gap', array(
            'label' => __('Espacement vertical', 'mj-member'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => array('px', 'em'),
            'range' => array(
                'px' => array('min' => 0, 'max' => 60),
                'em' => array('min' => 0, 'max' => 6),
            ),
            'selectors' => array(
                '{{WRAPPER}} .mj-contact-form__form' => 'row-gap: {{SIZE}}{{UNIT}};',
            ),
        ));

        $this->add_control('label_color', array(
            'label' => __('Couleur des labels', 'mj-member'),
            'type' => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .mj-contact-form__label' => 'color: {{VALUE}};',
            ),
        ));

        if (class_exists('Elementor\\Group_Control_Typography')) {
            $this->add_group_control(
                Group_Control_Typography::get_type(),
                array(
                    'name' => 'label_typography',
                    'selector' => '{{WRAPPER}} .mj-contact-form__label',
                )
            );
        }

        $this->add_control('input_background_color', array(
            'label' => __('Fond des champs', 'mj-member'),
            'type' => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .mj-contact-form' => '--mj-contact-input-bg: {{VALUE}};',
            ),
        ));

        $this->add_control('input_border_color', array(
            'label' => __('Bordure', 'mj-member'),
            'type' => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .mj-contact-form' => '--mj-contact-input-border: {{VALUE}};',
            ),
        ));

        $this->add_control('input_focus_border_color', array(
            'label' => __('Bordure (focus)', 'mj-member'),
            'type' => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .mj-contact-form' => '--mj-contact-input-border-focus: {{VALUE}};',
            ),
        ));

        $this->add_control('input_text_color', array(
            'label' => __('Texte', 'mj-member'),
            'type' => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .mj-contact-form' => '--mj-contact-input-text: {{VALUE}};',
            ),
        ));

        $this->add_control('input_placeholder_color', array(
            'label' => __('Texte indicatif', 'mj-member'),
            'type' => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .mj-contact-form' => '--mj-contact-input-placeholder: {{VALUE}};',
            ),
        ));

        if (class_exists('Elementor\\Group_Control_Typography')) {
            $this->add_group_control(
                Group_Control_Typography::get_type(),
                array(
                    'name' => 'input_typography',
                    'selector' => '{{WRAPPER}} .mj-contact-form__input, {{WRAPPER}} .mj-contact-form__textarea',
                )
            );
        }

        $this->add_responsive_control('input_padding', array(
            'label' => __('Marge interne des champs', 'mj-member'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => array('px', 'em'),
            'selectors' => array(
                '{{WRAPPER}} .mj-contact-form__input, {{WRAPPER}} .mj-contact-form__textarea' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ),
        ));

        $this->add_responsive_control('input_border_radius', array(
            'label' => __('Rayon des champs', 'mj-member'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => array('px', '%'),
            'selectors' => array(
                '{{WRAPPER}} .mj-contact-form__input, {{WRAPPER}} .mj-contact-form__textarea' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ),
        ));

        $this->end_controls_section();

        $this->start_controls_section('section_style_button', array(
            'label' => __('Bouton', 'mj-member'),
            'tab' => Controls_Manager::TAB_STYLE,
        ));

        $this->add_control('button_text_color', array(
            'label' => __('Texte', 'mj-member'),
            'type' => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .mj-contact-form__submit' => 'color: {{VALUE}};',
            ),
        ));

        $this->add_control('button_background_color', array(
            'label' => __('Fond', 'mj-member'),
            'type' => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .mj-contact-form__submit' => 'background-color: {{VALUE}}; border-color: {{VALUE}};',
            ),
        ));

        $this->add_control('button_text_color_hover', array(
            'label' => __('Texte (survol)', 'mj-member'),
            'type' => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .mj-contact-form__submit:hover, {{WRAPPER}} .mj-contact-form__submit:focus-visible' => 'color: {{VALUE}};',
            ),
        ));

        $this->add_control('button_background_color_hover', array(
            'label' => __('Fond (survol)', 'mj-member'),
            'type' => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .mj-contact-form__submit:hover, {{WRAPPER}} .mj-contact-form__submit:focus-visible' => 'background-color: {{VALUE}}; border-color: {{VALUE}};',
            ),
        ));

        if (class_exists('Elementor\\Group_Control_Typography')) {
            $this->add_group_control(
                Group_Control_Typography::get_type(),
                array(
                    'name' => 'button_typography',
                    'selector' => '{{WRAPPER}} .mj-contact-form__submit',
                )
            );
        }

        $this->add_responsive_control('button_padding', array(
            'label' => __('Marge interne', 'mj-member'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => array('px', 'em'),
            'selectors' => array(
                '{{WRAPPER}} .mj-contact-form__submit' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ),
        ));

        $this->add_responsive_control('button_border_radius', array(
            'label' => __('Rayon', 'mj-member'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => array('px', '%'),
            'selectors' => array(
                '{{WRAPPER}} .mj-contact-form__submit' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ),
        ));

        if (class_exists('Elementor\\Group_Control_Box_Shadow')) {
            $this->add_group_control(
                Group_Control_Box_Shadow::get_type(),
                array(
                    'name' => 'button_box_shadow',
                    'selector' => '{{WRAPPER}} .mj-contact-form__submit',
                )
            );
        }

        $this->end_controls_section();

        $this->start_controls_section('section_style_member_card', array(
            'label' => __('Fiche membre', 'mj-member'),
            'tab' => Controls_Manager::TAB_STYLE,
        ));

        $this->add_control('member_card_background_color', array(
            'label' => __('Fond de la carte', 'mj-member'),
            'type' => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .mj-contact-form' => '--mj-contact-member-surface: {{VALUE}};',
            ),
        ));

        $this->add_control('member_badge_background_color', array(
            'label' => __('Fond du badge', 'mj-member'),
            'type' => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .mj-contact-form' => '--mj-contact-member-chip-bg: {{VALUE}};',
            ),
        ));

        $this->add_control('member_badge_text_color', array(
            'label' => __('Texte du badge', 'mj-member'),
            'type' => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .mj-contact-form' => '--mj-contact-member-chip-color: {{VALUE}};',
            ),
        ));

        $this->add_control('member_name_color', array(
            'label' => __('Nom', 'mj-member'),
            'type' => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .mj-contact-form' => '--mj-contact-member-name: {{VALUE}};',
            ),
        ));

        $this->add_control('member_role_color', array(
            'label' => __('Rôle', 'mj-member'),
            'type' => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .mj-contact-form' => '--mj-contact-member-role: {{VALUE}};',
            ),
        ));

        $this->add_control('member_meta_color', array(
            'label' => __('Informations complémentaires', 'mj-member'),
            'type' => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .mj-contact-form' => '--mj-contact-member-meta: {{VALUE}};',
            ),
        ));

        if (class_exists('Elementor\\Group_Control_Typography')) {
            $this->add_group_control(
                Group_Control_Typography::get_type(),
                array(
                    'name' => 'member_name_typography',
                    'selector' => '{{WRAPPER}} .mj-contact-form__member-name',
                )
            );
        }

        if (class_exists('Elementor\\Group_Control_Typography')) {
            $this->add_group_control(
                Group_Control_Typography::get_type(),
                array(
                    'name' => 'member_role_typography',
                    'selector' => '{{WRAPPER}} .mj-contact-form__member-role',
                )
            );
        }

        if (class_exists('Elementor\\Group_Control_Typography')) {
            $this->add_group_control(
                Group_Control_Typography::get_type(),
                array(
                    'name' => 'member_meta_typography',
                    'selector' => '{{WRAPPER}} .mj-contact-form__member-email, {{WRAPPER}} .mj-contact-form__member-bio',
                )
            );
        }

        $this->add_responsive_control('member_card_padding', array(
            'label' => __('Marge interne', 'mj-member'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => array('px', 'em'),
            'selectors' => array(
                '{{WRAPPER}} .mj-contact-form__member-card' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ),
        ));

        $this->add_responsive_control('member_card_border_radius', array(
            'label' => __('Rayon', 'mj-member'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => array('px', '%'),
            'selectors' => array(
                '{{WRAPPER}} .mj-contact-form__member-card' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ),
        ));

        if (class_exists('Elementor\\Group_Control_Box_Shadow')) {
            $this->add_group_control(
                Group_Control_Box_Shadow::get_type(),
                array(
                    'name' => 'member_card_box_shadow',
                    'selector' => '{{WRAPPER}} .mj-contact-form__member-card',
                )
            );
        }

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $title = isset($settings['title']) ? $settings['title'] : '';
        $description = isset($settings['description']) ? $settings['description'] : '';
        $success_message = isset($settings['success_message']) ? $settings['success_message'] : __('Merci ! Votre message a bien été envoyé.', 'mj-member');
        $submit_label = isset($settings['submit_label']) ? $settings['submit_label'] : __('Envoyer le message', 'mj-member');

        $prefill = array(
            'name' => '',
            'email' => '',
            'member_id' => 0,
            'locked' => false,
        );

        $show_member_card = !isset($settings['show_member_card']) || $settings['show_member_card'] === 'yes';

        $current_member = null;

        if (is_user_logged_in()) {
            $prefill['locked'] = true;
            $wp_user = wp_get_current_user();
            $current_member = function_exists('mj_member_get_current_member') ? mj_member_get_current_member() : null;

            if ($current_member && isset($current_member->id)) {
                $prefill['member_id'] = (int) $current_member->id;
            }

            $resolved_name = '';
            if ($current_member) {
                $first_name = isset($current_member->first_name) ? trim((string) $current_member->first_name) : '';
                $last_name = isset($current_member->last_name) ? trim((string) $current_member->last_name) : '';
                $resolved_name = trim(trim($first_name . ' ' . $last_name));
                if ($resolved_name === '' && !empty($current_member->nickname)) {
                    $resolved_name = (string) $current_member->nickname;
                }
            }

            if ($resolved_name === '' && $wp_user) {
                $resolved_name = trim(trim((string) $wp_user->first_name . ' ' . (string) $wp_user->last_name));
                if ($resolved_name === '') {
                    $resolved_name = $wp_user->display_name !== '' ? (string) $wp_user->display_name : (string) $wp_user->user_login;
                }
            }

            if ($resolved_name !== '') {
                $prefill['name'] = $resolved_name;
            }

            $resolved_email = '';
            if ($current_member && !empty($current_member->email)) {
                $resolved_email = (string) $current_member->email;
            } elseif ($wp_user && !empty($wp_user->user_email)) {
                $resolved_email = (string) $wp_user->user_email;
            }

            if ($resolved_email !== '') {
                $prefill['email'] = sanitize_email($resolved_email);
            }
        }

        $member_card = array(
            'is_available' => false,
            'name' => $prefill['name'],
            'email' => sanitize_email($prefill['email']),
            'role_label' => '',
            'bio' => '',
            'cover_url' => '',
            'avatar' => array(
                'url' => '',
                'alt' => '',
                'initials' => '',
            ),
        );

        $role_labels = array();
        if (class_exists('MjMembers_CRUD')) {
            $role_labels = MjMembers_CRUD::getRoleLabels();
        }

        if (isset($current_member) && is_object($current_member)) {
            if (!empty($current_member->role)) {
                $role_key = sanitize_key((string) $current_member->role);
                if (isset($role_labels[$role_key])) {
                    $member_card['role_label'] = sanitize_text_field((string) $role_labels[$role_key]);
                } else {
                    $member_card['role_label'] = sanitize_text_field(ucfirst(str_replace('_', ' ', $role_key)));
                }
            }

            if (!empty($current_member->description_courte)) {
                $member_card['bio'] = sanitize_text_field((string) $current_member->description_courte);
            }

            $photo_id = !empty($current_member->photo_id) ? (int) $current_member->photo_id : 0;
            if ($photo_id > 0) {
                $cover_image = wp_get_attachment_image_src($photo_id, 'large');
                if ($cover_image) {
                    $member_card['cover_url'] = esc_url_raw((string) $cover_image[0]);
                }

                $avatar_image = wp_get_attachment_image_src($photo_id, 'thumbnail');
                if ($avatar_image) {
                    $member_card['avatar']['url'] = esc_url_raw((string) $avatar_image[0]);
                }
            }
        }

        if ($member_card['avatar']['url'] === '' && $member_card['email'] !== '' && function_exists('get_avatar_url')) {
            $member_card['avatar']['url'] = esc_url_raw(get_avatar_url($member_card['email'], array('size' => 192)));
        }

        if ($member_card['cover_url'] === '' && $member_card['avatar']['url'] !== '') {
            $member_card['cover_url'] = $member_card['avatar']['url'];
        }

        if ($member_card['name'] !== '') {
            $member_card['avatar']['alt'] = sanitize_text_field(sprintf(__('Photo de %s', 'mj-member'), $member_card['name']));
        } else {
            $member_card['avatar']['alt'] = __('Photo du membre', 'mj-member');
        }

        if ($member_card['avatar']['initials'] === '') {
            $label_for_initials = $member_card['name'] !== '' ? $member_card['name'] : __('MJ', 'mj-member');
            if (function_exists('mj_member_contact_extract_initials')) {
                $member_card['avatar']['initials'] = sanitize_text_field(mj_member_contact_extract_initials($label_for_initials));
            } else {
                if (function_exists('mb_substr') && function_exists('mb_strtoupper')) {
                    $initial = mb_strtoupper(mb_substr($label_for_initials, 0, 1, 'UTF-8'), 'UTF-8');
                } else {
                    $initial = strtoupper(substr($label_for_initials, 0, 1));
                }
                $member_card['avatar']['initials'] = sanitize_text_field($initial);
            }
        }

        $member_card['is_available'] = !empty($prefill['locked']) && ($member_card['name'] !== '' || $member_card['email'] !== '');

        if ($member_card['is_available'] && $member_card['role_label'] === '') {
            $member_card['role_label'] = __('Membre MJ', 'mj-member');
        }

        $is_preview = false;
        if (class_exists('Elementor\\Plugin')) {
            $elementor = Elementor\Plugin::instance();
            if (method_exists($elementor, 'editor') && $elementor->editor && method_exists($elementor->editor, 'is_edit_mode')) {
                $is_preview = $is_preview || (bool) $elementor->editor->is_edit_mode();
            }
            if (method_exists($elementor, 'preview') && $elementor->preview && method_exists($elementor->preview, 'is_preview_mode')) {
                $is_preview = $is_preview || (bool) $elementor->preview->is_preview_mode();
            }
        }

        $recipients = mj_member_get_contact_recipient_options();
        if ($is_preview && empty($recipients)) {
            $animateur_label = __('Les animateurs / coordinateurs', 'mj-member');
            $collectif_label = __('Toute l’équipe', 'mj-member');

            if (function_exists('mj_member_contact_format_recipient_option')) {
                $recipients = array(
                    mj_member_contact_format_recipient_option(array(
                        'value' => MjContactMessages::TARGET_ANIMATEUR,
                        'label' => $animateur_label,
                        'type' => MjContactMessages::TARGET_ANIMATEUR,
                        'reference' => 0,
                        'role' => 'animateur',
                        'role_label' => __('Équipe encadrante', 'mj-member'),
                    )),
                    mj_member_contact_format_recipient_option(array(
                        'value' => MjContactMessages::TARGET_ALL,
                        'label' => $collectif_label,
                        'type' => MjContactMessages::TARGET_ALL,
                        'reference' => 0,
                        'role' => 'group',
                        'role_label' => __('Maison de Jeune', 'mj-member'),
                    )),
                );
            } else {
                $recipients = array(
                    array(
                        'value' => MjContactMessages::TARGET_ANIMATEUR,
                        'label' => $animateur_label,
                        'type' => MjContactMessages::TARGET_ANIMATEUR,
                        'reference' => 0,
                    ),
                    array(
                        'value' => MjContactMessages::TARGET_ALL,
                        'label' => $collectif_label,
                        'type' => MjContactMessages::TARGET_ALL,
                        'reference' => 0,
                    ),
                );
            }
        }

        $config = array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mj-member-contact-message'),
            'successMessage' => $success_message,
            'errorMessage' => __('Une erreur est survenue. Merci de réessayer.', 'mj-member'),
            'isPreview' => $is_preview,
            'prefillLocked' => !empty($prefill['locked']),
        );

        $template_data = array(
            'title' => $title,
            'description' => $description,
            'recipients' => $recipients,
            'submit_label' => $submit_label,
            'config' => $config,
            'prefill' => $prefill,
            'show_member_card' => $show_member_card,
            'member_card' => $member_card,
        );

        $template_path = Config::path() . 'includes/templates/elementor/contact_form.php';
        if (file_exists($template_path)) {
            include $template_path;
        }
    }
}
