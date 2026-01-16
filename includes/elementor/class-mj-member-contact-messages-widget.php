<?php

if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Controls_Manager;
use Elementor\Widget_Base;
use Mj\Member\Core\Config;

class Mj_Member_Elementor_Contact_Messages_Widget extends Widget_Base {
    use Mj_Member_Elementor_Widget_Visibility;

    public function get_name() {
        return 'mj-member-contact-messages';
    }

    public function get_title() {
        return __('Messages de contact MJ', 'mj-member');
    }

    public function get_icon() {
        return 'eicon-commenting-o';
    }

    public function get_categories() {
        return array('mj-member');
    }

    public function get_keywords() {
        return array('mj', 'messages', 'contact', 'support', 'ticket');
    }

    public function get_style_depends() {
        return array('mj-member-components');
    }

    public function get_script_depends() {
        return array('mj-member-contact-messages');
    }

    protected function register_controls() {
        $this->start_controls_section('section_content', array(
            'label' => __('Contenu', 'mj-member'),
        ));

        $this->add_control('title', array(
            'label' => __('Titre', 'mj-member'),
            'type' => Controls_Manager::TEXT,
            'default' => __('Messages et réponses', 'mj-member'),
            'label_block' => true,
        ));

        $this->add_control('description', array(
            'label' => __('Introduction', 'mj-member'),
            'type' => Controls_Manager::TEXTAREA,
            'rows' => 3,
            'default' => __('Retrouvez ici vos conversations récentes avec l’équipe MJ.', 'mj-member'),
        ));

        $this->add_control('items_per_page', array(
            'label' => __('Nombre de messages', 'mj-member'),
            'type' => Controls_Manager::NUMBER,
            'min' => 1,
            'max' => 20,
            'step' => 1,
            'default' => 5,
        ));

        $this->add_control('show_unread_only', array(
            'label' => __('Afficher uniquement les non lus', 'mj-member'),
            'type' => Controls_Manager::SWITCHER,
            'label_on' => __('Oui', 'mj-member'),
            'label_off' => __('Non', 'mj-member'),
            'return_value' => 'yes',
            'default' => '',
        ));

        $this->end_controls_section();

        $this->register_visibility_controls();

        $this->start_controls_section('section_style_container', array(
            'label' => __('Apparence', 'mj-member'),
            'tab' => Controls_Manager::TAB_STYLE,
        ));

        $this->add_control('container_background_color', array(
            'label' => __('Fond', 'mj-member'),
            'type' => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .mj-contact-messages__surface' => 'background-color: {{VALUE}};',
            ),
        ));

        $this->remove_control('container_border_color');

        $this->add_control('container_border_color', array(
            'label' => __('Bordure', 'mj-member'),
            'type' => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .mj-contact-messages__surface' => 'border-color: {{VALUE}};',
            ),
        ));

        $this->add_control('status_badge_color', array(
            'label' => __('Couleur du badge', 'mj-member'),
            'type' => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .mj-contact-messages__state-indicator' => 'background-color: {{VALUE}};',
            ),
        ));

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $this->apply_visibility_to_wrapper($settings, 'mj-contact-messages');

        $title = isset($settings['title']) && $settings['title'] !== '' ? $settings['title'] : __('Messages et réponses', 'mj-member');
        $default_received_label = __('Messages reçus', 'mj-member');
        if (is_string($title) && trim($title) === $default_received_label) {
            $title = __('Messages et réponses', 'mj-member');
        }

        $description = isset($settings['description']) ? $settings['description'] : '';
        $limit = isset($settings['items_per_page']) ? (int) $settings['items_per_page'] : 5;
        if ($limit <= 0) {
            $limit = 5;
        }

        $show_unread_only = !empty($settings['show_unread_only']) && $settings['show_unread_only'] === 'yes';

        $current_user_id = get_current_user_id();
        $is_logged_in = $current_user_id > 0;
        $can_moderate = current_user_can(Config::contactCapability()) && $is_logged_in;
        $can_view = $can_moderate;

        $current_user = $is_logged_in ? wp_get_current_user() : null;
        $current_user_email = ($current_user instanceof WP_User && !empty($current_user->user_email))
            ? sanitize_email((string) $current_user->user_email)
            : '';

        $current_user_name = '';
        if ($current_user instanceof WP_User) {
            if ($current_user->display_name !== '') {
                $current_user_name = sanitize_text_field((string) $current_user->display_name);
            } elseif ($current_user->user_login !== '') {
                $current_user_name = sanitize_text_field((string) $current_user->user_login);
            }
        }

        $member = null;
        $member_id = 0;
        $member_role = '';
        if ($is_logged_in && function_exists('mj_member_get_current_member')) {
            $member = mj_member_get_current_member();
            if (!empty($member) && isset($member->id)) {
                $member_id = (int) $member->id;
            }

            if (!empty($member) && isset($member->role)) {
                $member_role = sanitize_key((string) $member->role);
            }

            if ($current_user_name === '') {
                $first = isset($member->first_name) ? trim((string) $member->first_name) : '';
                $last = isset($member->last_name) ? trim((string) $member->last_name) : '';
                $composed = trim($first . ' ' . $last);
                if ($composed !== '') {
                    $current_user_name = sanitize_text_field($composed);
                }
            }
        }

        $recipient_target_specs = $this->build_recipient_target_queries($member_id, $member_role, $can_moderate);

        $messages_payload = array();
        $status_labels = MjContactMessages::get_status_labels();
        $view_all_url = admin_url('admin.php?page=mj_contact_messages');
        $current_url = $this->get_current_url();
        $redirect_base = $can_moderate ? esc_url_raw(remove_query_arg(array('mj_contact_notice'), $current_url)) : '';

        if ($can_moderate) {
            $assigned_args = array(
                'per_page' => $limit,
                'paged' => 1,
                'order' => 'DESC',
                'orderby' => 'created_at',
                'assigned_to' => $current_user_id,
            );

            $all_targets_args = array(
                'per_page' => $limit,
                'paged' => 1,
                'order' => 'DESC',
                'orderby' => 'created_at',
                'target_type' => MjContactMessages::TARGET_ALL,
            );

            if ($show_unread_only) {
                $assigned_args['read_state'] = 'unread';
                $all_targets_args['read_state'] = 'unread';
            }

            $assigned_messages = MjContactMessages::query($assigned_args);
            $all_target_messages = MjContactMessages::query($all_targets_args);

            $message_sets = array($assigned_messages, $all_target_messages);

            if (!empty($recipient_target_specs)) {
                foreach ($recipient_target_specs as $spec) {
                    if (!isset($spec['type']) || $spec['type'] === MjContactMessages::TARGET_ALL) {
                        continue;
                    }

                    $recipient_args = array(
                        'per_page' => $limit,
                        'paged' => 1,
                        'order' => 'DESC',
                        'orderby' => 'created_at',
                        'target_type' => $spec['type'],
                    );

                    if (array_key_exists('reference', $spec)) {
                        $recipient_args['target_reference'] = $spec['reference'];
                    }

                    if ($show_unread_only) {
                        $recipient_args['read_state'] = 'unread';
                    }

                    $message_sets[] = MjContactMessages::query($recipient_args);
                }
            }

            $messages = $this->merge_message_sets($message_sets, $limit);
            $messages_payload = $this->format_messages_for_template($messages, $status_labels, array(
                'can_moderate' => true,
                'include_activity' => true,
                'include_full' => true,
                'owner_view' => false,
                'activity_actions' => array(),
            ));
        } elseif ($is_logged_in) {
            $has_owner_identity = ($member_id > 0 || $current_user_email !== '');
            $message_sets = array();

            if ($has_owner_identity) {
                $own_args = array(
                    'per_page' => $limit,
                    'paged' => 1,
                    'order' => 'DESC',
                    'orderby' => 'created_at',
                );

                if ($member_id > 0) {
                    $own_args['member_id'] = $member_id;
                }

                if ($current_user_email !== '') {
                    $own_args['sender_email'] = $current_user_email;
                }

                if ($show_unread_only) {
                    $own_args['read_state'] = 'unread';
                }

                $own_messages = MjContactMessages::query($own_args);
                if (!empty($own_messages)) {
                    $message_sets[] = $own_messages;
                }
            }

            if (!empty($recipient_target_specs)) {
                foreach ($recipient_target_specs as $spec) {
                    if (!isset($spec['type'])) {
                        continue;
                    }

                    $recipient_args = array(
                        'per_page' => $limit,
                        'paged' => 1,
                        'order' => 'DESC',
                        'orderby' => 'created_at',
                        'target_type' => $spec['type'],
                    );

                    if (array_key_exists('reference', $spec)) {
                        $recipient_args['target_reference'] = $spec['reference'];
                    }

                    if ($show_unread_only) {
                        $recipient_args['read_state'] = 'unread';
                    }

                    $recipient_messages = MjContactMessages::query($recipient_args);
                    if (!empty($recipient_messages)) {
                        $message_sets[] = $recipient_messages;
                    }
                }
            }

            $combined_messages = !empty($message_sets) ? $this->merge_message_sets($message_sets, $limit) : array();
            $messages_payload = $this->format_messages_for_template($combined_messages, $status_labels, array(
                'can_moderate' => false,
                'include_activity' => true,
                'include_full' => true,
                'owner_view' => true,
                'activity_actions' => array('reply_sent', 'reply_owner', 'note'),
            ));

            if ($has_owner_identity || !empty($recipient_target_specs)) {
                $can_view = true;
            }
        }

        $is_preview = $this->is_elementor_preview_mode();
        if ($is_preview && empty($messages_payload)) {
            $messages_payload = $this->get_preview_messages();
            $can_view = true;
        }

        $owner_view_active = !$can_moderate && $can_view;
        $empty_text = $show_unread_only
            ? __('Aucun message non lu pour le moment.', 'mj-member')
            : ($owner_view_active
                ? __('Vous n’avez pas encore de conversation.', 'mj-member')
                : __('Aucun message disponible pour le moment.', 'mj-member'));

        $owner_reply = array(
            'enabled' => $owner_view_active && !$is_preview,
            'can_send' => $current_user_email !== '',
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mj-member-contact-message'),
            'sender_name' => $current_user_name,
            'sender_email' => $current_user_email,
            'member_id' => $member_id,
            'source' => $current_url,
        );

        $template_path = Config::path() . 'includes/templates/elementor/contact_messages.php';
        $template_data = array(
            'title' => $title,
            'description' => $description,
            'messages' => $messages_payload,
            'has_permission' => $can_moderate,
            'can_view' => $can_view,
            'can_moderate' => $can_moderate,
            'owner_view' => $owner_view_active,
            'empty_text' => $empty_text,
            'restricted_text' => $is_logged_in ? __('Vous ne pouvez pas accéder à ces messages.', 'mj-member') : __('Vous devez être connecté pour consulter vos messages.', 'mj-member'),
            'view_all_url' => $can_moderate ? $view_all_url : '',
            'is_preview' => $is_preview,
            'redirect_base' => $redirect_base,
            'owner_reply' => $owner_reply,
        );

        if (file_exists($template_path)) {
            /**
             * Filtre les données passées au template des messages Elementor.
             *
             * @param array<string,mixed> $template_data
             * @param array<string,mixed> $settings
             * @param Mj_Member_Elementor_Contact_Messages_Widget $widget
             */
            $template_data = apply_filters('mj_member_contact_messages_widget_template_data', $template_data, $settings, $this);

            include $template_path;
        }
    }

    /**
     * @param array<int,object>|null $messages
     * @param array<string,string> $status_labels
     * @return array<int,array<string,mixed>>
     */
    private function format_messages_for_template($messages, $status_labels, $options = array()) {
        $defaults = array(
            'can_moderate' => true,
            'include_activity' => false,
            'include_full' => false,
            'owner_view' => false,
            'activity_actions' => array(),
        );

        $items = array();

        if (empty($messages) || !is_array($messages)) {
            return $items;
        }

        foreach ($messages as $message) {
            if (!isset($message->id)) {
                continue;
            }

            $status_key = isset($message->status) ? sanitize_key($message->status) : MjContactMessages::STATUS_NEW;
            $status_label = isset($status_labels[$status_key]) ? $status_labels[$status_key] : $status_key;
            $sender_name = isset($message->sender_name) ? sanitize_text_field((string) $message->sender_name) : '';
            $sender_email = isset($message->sender_email) ? sanitize_email((string) $message->sender_email) : '';
            $subject = isset($message->subject) ? sanitize_text_field((string) $message->subject) : '';
            if ($subject === '') {
                $subject = __('(Sans sujet)', 'mj-member');
            }
            $created_at = isset($message->created_at) ? strtotime((string) $message->created_at) : false;
            $full_message_raw = isset($message->message) ? (string) $message->message : '';
            $excerpt = $full_message_raw !== '' ? wp_trim_words(wp_strip_all_tags($full_message_raw), 18, '...') : '';
            $target_label = isset($message->target_label) ? sanitize_text_field((string) $message->target_label) : '';
            $target_type = isset($message->target_type) ? sanitize_key((string) $message->target_type) : '';
            if ($target_type === MjContactMessages::TARGET_ALL) {
                if ($target_label === '' || $target_label === __('Tous les destinataires', 'mj-member')) {
                    $target_label = __('Toute l\'équipe', 'mj-member');
                }
            }
            $is_unread = MjContactMessages::is_unread($message);
            $reply_subject = $subject !== '' ? sprintf(__('Re: %s', 'mj-member'), $subject) : __('Réponse à votre message', 'mj-member');
            $activity_entries = array();
            $is_archived = ($status_key === MjContactMessages::STATUS_ARCHIVED);

            if (!empty($options['include_activity'])) {
                $raw_entries = MjContactMessages::get_activity_entries($message);

                if (!empty($raw_entries) && is_array($raw_entries)) {
                    foreach ($raw_entries as $entry) {
                        if (!isset($entry['action'])) {
                            continue;
                        }

                        $action_key = sanitize_key((string) $entry['action']);
                        if (!empty($options['activity_actions']) && !in_array($action_key, (array) $options['activity_actions'], true)) {
                            continue;
                        }

                        $note = isset($entry['note']) ? sanitize_text_field((string) $entry['note']) : '';
                        $timestamp_raw = isset($entry['timestamp']) ? strtotime((string) $entry['timestamp']) : false;
                        $entry_meta = array();
                        if (isset($entry['meta']) && is_array($entry['meta'])) {
                            foreach ($entry['meta'] as $meta_key => $meta_value) {
                                $meta_key_sanitized = sanitize_key((string) $meta_key);
                                if ($meta_key_sanitized === '') {
                                    continue;
                                }
                                $entry_meta[$meta_key_sanitized] = is_string($meta_value) ? $meta_value : (string) $meta_value;
                            }
                        }

                        $activity_entries[] = array(
                            'action' => $action_key,
                            'note' => $note,
                            'timestamp' => $timestamp_raw ? $timestamp_raw : 0,
                            'time_human' => $timestamp_raw ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $timestamp_raw) : '',
                            'meta' => $entry_meta,
                        );
                    }
                }
            }

            $search_terms_parts = array(
                $subject,
                $sender_name,
                $sender_email,
                $target_label,
                $status_label,
                $excerpt,
            );
            $search_terms = sanitize_text_field(trim(implode(' ', array_filter($search_terms_parts))));
            $archive_nonce = !empty($options['can_moderate'])
                ? wp_create_nonce('mj-member-archive-contact-message-' . (int) $message->id)
                : '';
            $delete_nonce = !empty($options['can_moderate'])
                ? wp_create_nonce('mj-member-delete-contact-message-' . (int) $message->id)
                : '';

            $items[] = array(
                'id' => (int) $message->id,
                'status_key' => $status_key,
                'status_label' => $status_label,
                'subject' => $subject,
                'sender_name' => $sender_name,
                'sender_email' => $sender_email,
                'target_label' => $target_label,
                'is_unread' => $is_unread,
                'is_read' => !$is_unread,
                'is_archived' => $is_archived,
                'date_human' => $created_at ? date_i18n(get_option('date_format'), $created_at) : '',
                'time_human' => $created_at ? date_i18n(get_option('time_format'), $created_at) : '',
                'timestamp' => $created_at ? $created_at : 0,
                'excerpt' => $excerpt,
                'full_message_raw' => $options['include_full'] ? $full_message_raw : '',
                'activity' => $activity_entries,
                'view_url' => admin_url('admin.php?page=mj_contact_messages&action=view&message=' . (int) $message->id),
                'toggle_nonce' => !empty($options['can_moderate']) ? wp_create_nonce('mj-member-toggle-contact-message-read-' . (int) $message->id) : '',
                'reply_nonce' => !empty($options['can_moderate']) ? wp_create_nonce('mj-member-reply-contact-message-' . (int) $message->id) : '',
                'reply_subject' => !empty($options['can_moderate']) ? $reply_subject : '',
                'can_moderate' => !empty($options['can_moderate']),
                'owner_view' => !empty($options['owner_view']),
                'target_type' => $target_type,
                'target_reference' => isset($message->target_reference) ? (int) $message->target_reference : 0,
                'recipient_choice' => self::build_recipient_choice($message),
                'quick_reply_subject' => $reply_subject,
                'archive_nonce' => $archive_nonce,
                'delete_nonce' => $delete_nonce,
                'search_terms' => $search_terms,
            );
        }

        return $items;
    }

    /**
     * @param int $member_id
     * @param string $member_role
     * @param bool $include_global
     * @return array<int,array<string,mixed>>
     */
    private function build_recipient_target_queries($member_id, $member_role, $include_global = false) {
        $member_id = (int) $member_id;
        $member_role = sanitize_key((string) $member_role);

        $targets = array();

        $append_target = static function ($type, $reference = null) use (&$targets) {
            $normalized_reference = null;
            if ($reference !== null) {
                $candidate = (int) $reference;
                if ($candidate > 0) {
                    $normalized_reference = $candidate;
                }
            }

            $key = $type . '|' . ($normalized_reference === null ? 'null' : (string) $normalized_reference);
            if (isset($targets[$key])) {
                return;
            }

            $entry = array('type' => $type);
            if ($normalized_reference !== null) {
                $entry['reference'] = $normalized_reference;
            }

            $targets[$key] = $entry;
        };
        $is_animateur = false;
        $is_coordinateur = false;

        if (class_exists('Mj\\Member\\Classes\\MjRoles')) {
            $is_animateur = \Mj\Member\Classes\MjRoles::isAnimateur($member_role);
            $is_coordinateur = \Mj\Member\Classes\MjRoles::isCoordinateur($member_role);
        } else {
            $is_animateur = ($member_role === 'animateur');
            $is_coordinateur = ($member_role === 'coordinateur');
        }

        $should_include_global = $include_global || $is_animateur || $is_coordinateur;

        if ($should_include_global) {
            $append_target(MjContactMessages::TARGET_ALL, null);
        }

        if ($member_id <= 0) {
            return array_values($targets);
        }

        $member_target = defined('MjContactMessages::TARGET_MEMBER')
            ? constant('MjContactMessages::TARGET_MEMBER')
            : 'member';

        $append_target($member_target, $member_id);

        if ($is_animateur || $is_coordinateur) {
            $append_target(MjContactMessages::TARGET_ANIMATEUR, $member_id);
            $append_target(MjContactMessages::TARGET_ANIMATEUR, 0);
        }

        if ($is_coordinateur) {
            $append_target(MjContactMessages::TARGET_COORDINATEUR, $member_id);
            $append_target(MjContactMessages::TARGET_COORDINATEUR, 0);
        }

        return array_values($targets);
    }

    /**
     * @param object $message
     * @return string
     */
    private static function build_recipient_choice($message) {
        if (!isset($message->target_type)) {
            return '';
        }

        $target_type = sanitize_key((string) $message->target_type);
        $reference = isset($message->target_reference) ? (int) $message->target_reference : 0;

        if ($target_type === MjContactMessages::TARGET_ALL) {
            return MjContactMessages::TARGET_ALL;
        }

        if ($target_type === MjContactMessages::TARGET_ANIMATEUR) {
            return $reference > 0
                ? MjContactMessages::TARGET_ANIMATEUR . ':' . $reference
                : MjContactMessages::TARGET_ANIMATEUR;
        }

        if ($target_type === MjContactMessages::TARGET_COORDINATEUR) {
            return $reference > 0
                ? MjContactMessages::TARGET_COORDINATEUR . ':' . $reference
                : MjContactMessages::TARGET_COORDINATEUR;
        }

        $member_target = defined('MjContactMessages::TARGET_MEMBER')
            ? constant('MjContactMessages::TARGET_MEMBER')
            : 'member';

        if ($target_type === $member_target) {
            return $reference > 0
                ? $member_target . ':' . $reference
                : $member_target;
        }

        return $target_type;
    }

    /**
     * @param array<int,array<int,object>|null> $message_sets
     * @param int $limit
     * @return array<int,object>
     */
    private function merge_message_sets($message_sets, $limit) {
        $combined = array();

        foreach ($message_sets as $set) {
            if (empty($set) || !is_array($set)) {
                continue;
            }

            foreach ($set as $message) {
                if (!isset($message->id)) {
                    continue;
                }

                $combined[$message->id] = $message;
            }
        }

        if (empty($combined)) {
            return array();
        }

        $messages = array_values($combined);
        usort($messages, static function ($a, $b) {
            $time_a = isset($a->created_at) ? strtotime((string) $a->created_at) : 0;
            $time_b = isset($b->created_at) ? strtotime((string) $b->created_at) : 0;

            if ($time_a === $time_b) {
                return 0;
            }

            return ($time_a > $time_b) ? -1 : 1;
        });

        if ($limit > 0 && count($messages) > $limit) {
            return array_slice($messages, 0, $limit);
        }

        return $messages;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function get_preview_messages() {
        $now = current_time('timestamp');
        $default_reply = __('Réponse à votre message', 'mj-member');

        return array(
            array(
                'id' => 1,
                'status_key' => MjContactMessages::STATUS_NEW,
                'status_label' => __('Nouveau', 'mj-member'),
                'subject' => __('Question sur les stages', 'mj-member'),
                'sender_name' => 'Camille Demo',
                'sender_email' => 'camille@example.com',
                'target_label' => __('Coordinateur', 'mj-member'),
                'is_unread' => true,
                'is_read' => false,
                'date_human' => date_i18n(get_option('date_format'), $now),
                'time_human' => date_i18n(get_option('time_format'), $now),
                'timestamp' => $now,
                'excerpt' => __('Bonjour, je souhaite en savoir plus sur les stages à venir.', 'mj-member'),
                'view_url' => '#',
                'toggle_nonce' => '',
                'reply_nonce' => '',
                'reply_subject' => $default_reply,
            ),
            array(
                'id' => 2,
                'status_key' => MjContactMessages::STATUS_IN_PROGRESS,
                'status_label' => __('En cours', 'mj-member'),
                'subject' => __('Demande d\'inscription', 'mj-member'),
                'sender_name' => 'Louis Exemple',
                'sender_email' => 'louis@example.com',
                'target_label' => __('Toute l\'équipe MJ', 'mj-member'),
                'is_unread' => false,
                'is_read' => true,
                'date_human' => date_i18n(get_option('date_format'), $now - DAY_IN_SECONDS),
                'time_human' => date_i18n(get_option('time_format'), $now - DAY_IN_SECONDS),
                'timestamp' => $now - DAY_IN_SECONDS,
                'excerpt' => __('Merci de confirmer la réception de mon inscription.', 'mj-member'),
                'view_url' => '#',
                'toggle_nonce' => '',
                'reply_nonce' => '',
                'reply_subject' => $default_reply,
            ),
        );
    }

    private function is_elementor_preview_mode() {
        if (!class_exists('\\Elementor\\Plugin')) {
            return false;
        }

        $elementor = \Elementor\Plugin::$instance;
        if (!$elementor) {
            return false;
        }

        if (isset($elementor->editor) && method_exists($elementor->editor, 'is_edit_mode')) {
            return (bool) $elementor->editor->is_edit_mode();
        }

        return false;
    }

    private function get_current_url() {
        global $wp;

        if (isset($wp) && is_object($wp) && property_exists($wp, 'request')) {
            return home_url(add_query_arg(array(), $wp->request));
        }

        return home_url(add_query_arg(array()));
    }
}
