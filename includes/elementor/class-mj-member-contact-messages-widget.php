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
            'label' => __('Messages par page', 'mj-member'),
            'type' => Controls_Manager::NUMBER,
            'min' => 1,
            'max' => 200,
            'step' => 1,
            'default' => 50,
            'description' => __('Détermine combien de messages sont chargés par page.', 'mj-member'),
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

        $this->add_control('pagination_style_heading', array(
            'label' => __('Pagination', 'mj-member'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ));

        $this->add_group_control(Group_Control_Typography::get_type(), array(
            'name' => 'pagination_info_typography',
            'label' => __('Typographie des infos', 'mj-member'),
            'selector' => '{{WRAPPER}} .mj-contact-messages__pagination-info',
        ));

        $this->add_control('pagination_info_color', array(
            'label' => __('Couleur des infos', 'mj-member'),
            'type' => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .mj-contact-messages__pagination-info' => 'color: {{VALUE}};',
            ),
        ));

        $this->add_group_control(Group_Control_Typography::get_type(), array(
            'name' => 'pagination_links_typography',
            'label' => __('Typographie des liens', 'mj-member'),
            'selector' => '{{WRAPPER}} .mj-contact-messages__pagination-link',
        ));

        $this->add_control('pagination_controls_background', array(
            'label' => __('Fond du conteneur', 'mj-member'),
            'type' => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .mj-contact-messages__pagination-controls' => 'background-color: {{VALUE}};',
            ),
        ));

        $this->add_control('pagination_controls_gap', array(
            'label' => __('Espacement interne', 'mj-member'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => array('px', 'rem'),
            'range' => array(
                'px' => array('min' => 0, 'max' => 40),
                'rem' => array('min' => 0, 'max' => 3, 'step' => 0.05),
            ),
            'selectors' => array(
                '{{WRAPPER}} .mj-contact-messages__pagination-controls' => 'padding: {{SIZE}}{{UNIT}};',
            ),
        ));

        $this->add_group_control(Group_Control_Border::get_type(), array(
            'name' => 'pagination_controls_border',
            'selector' => '{{WRAPPER}} .mj-contact-messages__pagination-controls',
        ));

        $this->add_group_control(Group_Control_Box_Shadow::get_type(), array(
            'name' => 'pagination_controls_shadow',
            'selector' => '{{WRAPPER}} .mj-contact-messages__pagination-controls',
        ));

        $this->add_control('pagination_controls_radius', array(
            'label' => __('Rayon des angles', 'mj-member'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => array('px', 'rem'),
            'range' => array(
                'px' => array('min' => 0, 'max' => 60),
                'rem' => array('min' => 0, 'max' => 4, 'step' => 0.05),
            ),
            'selectors' => array(
                '{{WRAPPER}} .mj-contact-messages__pagination-controls' => 'border-radius: {{SIZE}}{{UNIT}};',
            ),
        ));

        $this->add_control('pagination_link_color', array(
            'label' => __('Couleur des liens', 'mj-member'),
            'type' => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .mj-contact-messages__pagination-link' => 'color: {{VALUE}};',
            ),
        ));

        $this->add_control('pagination_link_background', array(
            'label' => __('Fond des liens', 'mj-member'),
            'type' => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .mj-contact-messages__pagination-link' => 'background-color: {{VALUE}};',
            ),
        ));

        $this->add_control('pagination_link_border_color', array(
            'label' => __('Bordure des liens', 'mj-member'),
            'type' => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .mj-contact-messages__pagination-link' => 'border-color: {{VALUE}};',
            ),
        ));

        $this->add_control('pagination_link_radius', array(
            'label' => __('Arrondi des liens', 'mj-member'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => array('px', 'rem'),
            'range' => array(
                'px' => array('min' => 0, 'max' => 60),
                'rem' => array('min' => 0, 'max' => 4, 'step' => 0.05),
            ),
            'selectors' => array(
                '{{WRAPPER}} .mj-contact-messages__pagination-link' => 'border-radius: {{SIZE}}{{UNIT}};',
            ),
        ));

        $this->add_control('pagination_link_hover_heading', array(
            'label' => __('État survol/actif', 'mj-member'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ));

        $this->add_control('pagination_link_hover_color', array(
            'label' => __('Couleur au survol', 'mj-member'),
            'type' => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .mj-contact-messages__pagination-link:hover, {{WRAPPER}} .mj-contact-messages__pagination-link:focus-visible' => 'color: {{VALUE}};',
            ),
        ));

        $this->add_control('pagination_link_hover_background', array(
            'label' => __('Fond au survol', 'mj-member'),
            'type' => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .mj-contact-messages__pagination-link:hover, {{WRAPPER}} .mj-contact-messages__pagination-link:focus-visible' => 'background-color: {{VALUE}};',
            ),
        ));

        $this->add_control('pagination_link_active_color', array(
            'label' => __('Couleur active', 'mj-member'),
            'type' => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .mj-contact-messages__pagination-link--number.is-current' => 'color: {{VALUE}};',
            ),
        ));

        $this->add_control('pagination_link_active_background', array(
            'label' => __('Fond actif', 'mj-member'),
            'type' => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .mj-contact-messages__pagination-link--number.is-current' => 'background-color: {{VALUE}};',
            ),
        ));

        $this->add_control('pagination_link_active_border', array(
            'label' => __('Bordure active', 'mj-member'),
            'type' => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .mj-contact-messages__pagination-link--number.is-current' => 'border-color: {{VALUE}};',
            ),
        ));

        $this->add_group_control(Group_Control_Box_Shadow::get_type(), array(
            'name' => 'pagination_link_active_shadow',
            'selector' => '{{WRAPPER}} .mj-contact-messages__pagination-link--number.is-current',
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
        $default_per_page = 50;
        $per_page_setting = isset($settings['items_per_page']) ? (int) $settings['items_per_page'] : $default_per_page;
        if ($per_page_setting <= 0) {
            $per_page_setting = $default_per_page;
        }

        $max_per_page = (int) apply_filters('mj_member_contact_messages_max_per_page', 200);
        if ($max_per_page < 1) {
            $max_per_page = 200;
        }

        $per_page = min($max_per_page, max(1, $per_page_setting));

        $page_param = 'mj_contact_page';
        $current_page = 1;
        if (isset($_GET[$page_param])) {
            $requested_page = (int) wp_unslash($_GET[$page_param]);
            if ($requested_page > 0) {
                $current_page = $requested_page;
            }
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

        $sanitized_targets = array();
        if (!empty($recipient_target_specs)) {
            foreach ($recipient_target_specs as $spec) {
                if (!is_array($spec) || empty($spec['type'])) {
                    continue;
                }

                $type = sanitize_key($spec['type']);
                if ($type === '') {
                    continue;
                }

                $entry = array('type' => $type);
                if (array_key_exists('reference', $spec)) {
                    $entry['reference'] = (int) $spec['reference'];
                }

                $sanitized_targets[] = $entry;
            }
        }

        $messages_payload = array();
        $status_labels = MjContactMessages::get_status_labels();
        $view_all_url = admin_url('admin.php?page=mj_contact_messages');
        $current_url = function_exists('mj_member_get_current_url') ? mj_member_get_current_url() : $this->get_current_url();
        $current_url = is_string($current_url) ? html_entity_decode($current_url) : $current_url;

        $base_query_params = array();
        if (!empty($_GET) && is_array($_GET)) {
            foreach ($_GET as $key => $value) {
                if ($key === 'mj_contact_notice' || $key === $page_param) {
                    continue;
                }

                $sanitized_key = sanitize_key($key);
                if ($sanitized_key === '') {
                    continue;
                }

                if (is_array($value)) {
                    continue;
                }

                $base_query_params[$sanitized_key] = sanitize_text_field((string) wp_unslash($value));
            }
        }

        $current_url_without_page = remove_query_arg($page_param, $current_url);
        $pagination_base_candidate = add_query_arg($base_query_params, $current_url_without_page);
        $pagination_base_clean = remove_query_arg($page_param, $pagination_base_candidate);
        $pagination_base_url = $pagination_base_clean;

        $has_owner_identity = ($member_id > 0 || $current_user_email !== '');
        $include_owner_messages = !$can_moderate && $has_owner_identity;

        $total_items = 0;
        $total_pages = 0;
        $should_query = false;

        if ($can_moderate) {
            $should_query = true;
        } elseif ($is_logged_in && ($has_owner_identity || !empty($sanitized_targets))) {
            $should_query = true;
            $can_view = true;
        }

        if ($should_query) {
            $common_args = array(
                'read_state' => $show_unread_only ? 'unread' : '',
                'include_assigned' => $can_moderate,
                'include_all_targets' => false,
                'extra_targets' => $sanitized_targets,
                'member_id' => $member_id,
                'sender_email' => $current_user_email,
                'include_owner' => $include_owner_messages,
            );

            $total_items = MjContactMessages::count_for_user($current_user_id, $common_args);
            $total_pages = $total_items > 0 ? (int) ceil($total_items / $per_page) : 0;

            if ($total_pages > 0 && $current_page > $total_pages) {
                $current_page = $total_pages;
            }

            if ($total_pages === 0) {
                $current_page = 1;
            }

            $query_args = $common_args;
            $query_args['per_page'] = $per_page;
            $query_args['paged'] = $current_page;

            $messages = MjContactMessages::query_for_user($current_user_id, $query_args);
            $messages_payload = $this->format_messages_for_template($messages, $status_labels, array(
                'can_moderate' => $can_moderate,
                'include_activity' => true,
                'include_full' => true,
                'owner_view' => !$can_moderate,
                'activity_actions' => $can_moderate ? array() : array('reply_sent', 'reply_owner', 'note'),
            ));
        } else {
            $current_page = 1;
        }

        $is_preview = $this->is_elementor_preview_mode();
        if ($is_preview && empty($messages_payload)) {
            $messages_payload = $this->get_preview_messages();
            $can_view = true;
            $total_items = count($messages_payload);
            $total_pages = $total_items > 0 ? 1 : 0;
            $current_page = 1;
        }

        if ($total_pages === 0) {
            $current_page = 1;
        }

        $owner_view_active = !$can_moderate && $can_view;
        $empty_text = $show_unread_only
            ? __('Aucun message non lu pour le moment.', 'mj-member')
            : ($owner_view_active
                ? __('Vous n’avez pas encore de conversation.', 'mj-member')
                : __('Aucun message disponible pour le moment.', 'mj-member'));

        $range_start = $total_items > 0 ? (($current_page - 1) * $per_page) + 1 : 0;
        $range_end = $total_items > 0 ? min($range_start + $per_page - 1, $total_items) : 0;

        $pagination_url_builder = static function ($page_number) use ($page_param, $pagination_base_url) {
            $base = remove_query_arg($page_param, $pagination_base_url);
            if ($page_number > 1) {
                $base = add_query_arg($page_param, $page_number, $base);
            }

            return $base;
        };

        $prev_url = '';
        if ($current_page > 1 && $total_pages > 0) {
            $prev_url = $pagination_url_builder(max(1, $current_page - 1));
        }

        $next_url = '';
        if ($total_pages > 0 && $current_page < $total_pages) {
            $next_url = $pagination_url_builder($current_page + 1);
        }

        $redirect_base = $pagination_url_builder($current_page);

        $owner_reply = array(
            'enabled' => $owner_view_active && !$is_preview,
            'can_send' => $current_user_email !== '',
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mj-member-contact-message'),
            'sender_name' => $current_user_name,
            'sender_email' => $current_user_email,
            'member_id' => $member_id,
            'source' => $redirect_base,
        );

        $pagination_links = array();
        if ($total_pages > 1) {
            $window = 5;
            $half_window = (int) floor($window / 2);
            $start_page = max(1, $current_page - $half_window);
            $end_page = min($total_pages, $current_page + $half_window);

            if ($start_page <= 2) {
                $start_page = 1;
            }

            if ($end_page >= $total_pages - 1) {
                $end_page = $total_pages;
            }

            if ($start_page > 1) {
                $pagination_links[] = array(
                    'type' => 'page',
                    'number' => 1,
                    'url' => $pagination_url_builder(1),
                    'current' => ($current_page === 1),
                );

                if ($start_page > 2) {
                    $pagination_links[] = array('type' => 'ellipsis');
                }
            }

            for ($page = $start_page; $page <= $end_page; $page++) {
                $pagination_links[] = array(
                    'type' => 'page',
                    'number' => $page,
                    'url' => $pagination_url_builder($page),
                    'current' => ($page === $current_page),
                );
            }

            if ($end_page < $total_pages) {
                if ($end_page < $total_pages - 1) {
                    $pagination_links[] = array('type' => 'ellipsis');
                }

                $pagination_links[] = array(
                    'type' => 'page',
                    'number' => $total_pages,
                    'url' => $pagination_url_builder($total_pages),
                    'current' => ($current_page === $total_pages),
                );
            }
        }

        $pagination = array(
            'current_page' => $current_page,
            'per_page' => $per_page,
            'total_items' => $total_items,
            'total_pages' => $total_pages,
            'page_param' => $page_param,
            'base_url' => $pagination_url_builder(1),
            'prev_url' => $prev_url,
            'next_url' => $next_url,
            'range_start' => $range_start,
            'range_end' => $range_end,
            'links' => $pagination_links,
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
            'pagination' => $pagination,
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
