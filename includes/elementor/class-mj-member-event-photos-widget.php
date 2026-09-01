<?php

if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Controls_Manager;
use Elementor\Widget_Base;
use Mj\Member\Core\Config;

class Mj_Member_Elementor_Event_Photos_Widget extends Widget_Base {
    use Mj_Member_Elementor_Widget_Visibility;

    public function get_name() {
        return 'mj-member-event-photos';
    }

    public function get_title() {
        return __('Partage photos MJ', 'mj-member');
    }

    public function get_icon() {
        return 'eicon-gallery-grid';
    }

    public function get_categories() {
        return array('mj-member');
    }

    public function get_keywords() {
        return array('mj', 'photo', 'galerie', 'upload', 'event');
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
                'default' => __('Partager mes photos', 'mj-member'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'description',
            array(
                'label' => __('Description', 'mj-member'),
                'type' => Controls_Manager::TEXTAREA,
                'default' => __('Ajoute jusqu’à trois photos par événement pour illustrer tes souvenirs.', 'mj-member'),
                'rows' => 3,
            )
        );

        $this->add_control(
            'limit',
            array(
                'label' => __('Nombre d’événements maximum', 'mj-member'),
                'type' => Controls_Manager::NUMBER,
                'min' => 1,
                'max' => 20,
                'default' => 6,
            )
        );

        $this->add_control(
            'empty_message',
            array(
                'label' => __('Message lorsqu’aucun événement n’est disponible', 'mj-member'),
                'type' => Controls_Manager::TEXTAREA,
                'default' => __('Inscris-toi à un événement pour commencer à partager des photos.', 'mj-member'),
                'rows' => 3,
            )
        );

        $this->end_controls_section();

        $this->register_visibility_controls();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $this->apply_visibility_to_wrapper($settings, 'mj-event-photos-widget');

        if (!function_exists('mj_member_event_photos_get_member_upload_context')) {
            echo '<div class="mj-member-account-warning">' . esc_html__('Le module photo MJ Member doit être activé pour utiliser ce widget.', 'mj-member') . '</div>';
            return;
        }

        $limit = isset($settings['limit']) ? max(1, (int) $settings['limit']) : 6;
        $title = isset($settings['title']) ? (string) $settings['title'] : '';
        $description = isset($settings['description']) ? (string) $settings['description'] : '';
        $empty_message = isset($settings['empty_message']) ? (string) $settings['empty_message'] : '';

        $is_preview = $this->is_elementor_preview_mode();

        $member = null;

        if (!$is_preview) {
            if (!is_user_logged_in()) {
                $login_url = wp_login_url(function_exists('mj_member_get_current_url') ? mj_member_get_current_url() : home_url('/'));
                echo '<div class="mj-member-account-warning">' . esc_html__('Connecte-toi pour envoyer des photos.', 'mj-member') . ' <a href="' . esc_url($login_url) . '">' . esc_html__('Se connecter', 'mj-member') . '</a></div>';
                return;
            }

            if (!function_exists('mj_member_get_current_member')) {
                echo '<div class="mj-member-account-warning">' . esc_html__('Aucun profil MJ associé à ton compte.', 'mj-member') . '</div>';
                return;
            }

            $member = mj_member_get_current_member();
            if (!$member || empty($member->id)) {
                echo '<div class="mj-member-account-warning">' . esc_html__('Aucun profil MJ associé à ton compte.', 'mj-member') . '</div>';
                return;
            }
        } else {
            $member = null;
        }

        static $mj_event_photos_sent_nocache = false;
        if (!$is_preview && !$mj_event_photos_sent_nocache && function_exists('nocache_headers')) {
            nocache_headers();
            $mj_event_photos_sent_nocache = true;
        }

        $context = mj_member_event_photos_get_member_upload_context($member, array(
            'limit' => $limit,
            'preview' => $is_preview,
        ));

        $events = isset($context['events']) && is_array($context['events']) ? $context['events'] : array();
        $has_events = !empty($context['has_events']);

        $redirect_to = function_exists('mj_member_get_current_url') ? mj_member_get_current_url() : home_url('/');

        $notice = null;
        if (!$is_preview && isset($_GET['mj_event_photo'])) {
            $notice_code = sanitize_key((string) wp_unslash($_GET['mj_event_photo']));
            if ($notice_code !== '' && function_exists('mj_member_event_photos_get_notice')) {
                $notice = mj_member_event_photos_get_notice($notice_code);
            }
        }

        $template_path = trailingslashit(Config::path()) . 'includes/templates/elementor/event_photos_widget.php';
        if (!file_exists($template_path)) {
            echo '<div class="mj-member-account-warning">' . esc_html__('Le template du widget photo est introuvable.', 'mj-member') . '</div>';
            return;
        }

        $template_data = array_merge(
            array(
                'title' => $title,
                'description' => $description,
                'empty_message' => $empty_message,
                'is_preview' => $is_preview,
                'events' => $events,
                'has_events' => $has_events,
                'notice' => $notice,
                'redirect_to' => $redirect_to,
            )
        );

        /**
         * Filtre les données partagées avec le template du widget photos.
         *
         * @param array<string,mixed> $template_data
         * @param array<string,mixed> $settings
         * @param Mj_Member_Elementor_Event_Photos_Widget $widget
         */
        $template_data = apply_filters('mj_member_event_photos_widget_template_data', $template_data, $settings, $this);

        include $template_path;
    }

    private function is_elementor_preview_mode() {
        if (did_action('elementor/loaded')) {
            $elementor = \Elementor\Plugin::$instance;
            if ($elementor && isset($elementor->editor) && method_exists($elementor->editor, 'is_edit_mode')) {
                return (bool) $elementor->editor->is_edit_mode();
            }
        }

        return false;
    }
}
