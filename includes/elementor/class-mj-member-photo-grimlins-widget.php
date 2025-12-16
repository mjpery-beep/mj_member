<?php

use Elementor\Controls_Manager;
use Elementor\Widget_Base;
use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

class Mj_Member_Elementor_Photo_Grimlins_Widget extends Widget_Base {
    use Mj_Member_Elementor_Widget_Visibility;

    public function get_name() {
        return 'mj-member-photo-grimlins';
    }

    public function get_title() {
        return __('Photo Grimlins MJ', 'mj-member');
    }

    public function get_icon() {
        return 'eicon-person';
    }

    public function get_categories() {
        return array('general');
    }

    public function get_keywords() {
        return array('mj', 'photo', 'avatar', 'grimlins', 'ia');
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
                'default' => __('Transforme ta photo en Grimlins', 'mj-member'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'description',
            array(
                'label' => __('Description', 'mj-member'),
                'type' => Controls_Manager::TEXTAREA,
                'default' => __('Téléverse ta photo et laisse la magie opérer : le widget génère un avatar « Grimlins » amusant en quelques instants.', 'mj-member'),
                'rows' => 3,
            )
        );

        $this->add_control(
            'button_label',
            array(
                'label' => __('Libellé du bouton', 'mj-member'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Générer mon Grimlins', 'mj-member'),
            )
        );

        $this->add_control(
            'members_only',
            array(
                'label' => __('Accessible uniquement pour les membres', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'no',
            )
        );

        $this->end_controls_section();

        $this->register_visibility_controls();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $this->apply_visibility_to_wrapper($settings, 'mj-photo-grimlins');

        $template_path = trailingslashit(Config::path()) . 'includes/templates/elementor/photo_grimlins.php';
        if (!file_exists($template_path)) {
            echo '<div class="mj-member-account-warning">' . esc_html__('Template du widget Photo Grimlins introuvable.', 'mj-member') . '</div>';
            return;
        }

        $is_preview = $this->is_elementor_preview_mode();

        if (!empty($settings['members_only']) && $settings['members_only'] === 'yes' && !is_user_logged_in() && !$is_preview) {
            echo '<div class="mj-member-account-warning">' . esc_html__('Cette fonctionnalité est réservée aux membres connectés.', 'mj-member') . '</div>';
            return;
        }

        $preview_svg = 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="512" height="512" viewBox="0 0 512 512"><defs><linearGradient id="g" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#a855f7"/><stop offset="100%" stop-color="#3b82f6"/></linearGradient></defs><rect width="512" height="512" fill="url(#g)"/><g fill="#fff" font-family="Arial, sans-serif" text-anchor="middle"><text x="50%" y="45%" font-size="42">Photo MJ</text><text x="50%" y="60%" font-size="28">(aperçu)</text></g></svg>');
        $result_svg = 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="512" height="512" viewBox="0 0 512 512"><defs><linearGradient id="r" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#22d3ee"/><stop offset="100%" stop-color="#14b8a6"/></linearGradient></defs><rect width="512" height="512" fill="url(#r)"/><circle cx="256" cy="210" r="120" fill="#0f172a" opacity="0.85"/><path d="M160 344c32-36 87-36 119 0 36-40 95-40 131 0-11 72-92 124-130 124-41 0-118-56-120-124z" fill="#0f172a" opacity="0.75"/><g fill="#fff" font-family="Arial, sans-serif" text-anchor="middle"><text x="50%" y="86%" font-size="32">Grimlins</text></g></svg>');

        $template_data = array(
            'title' => isset($settings['title']) ? (string) $settings['title'] : '',
            'description' => isset($settings['description']) ? (string) $settings['description'] : '',
            'button_label' => isset($settings['button_label']) ? (string) $settings['button_label'] : '',
            'is_preview' => $is_preview,
            'preview_image' => $is_preview ? $preview_svg : '',
            'result_image' => $is_preview ? $result_svg : '',
            'members_only' => (!empty($settings['members_only']) && $settings['members_only'] === 'yes'),
        );

        /**
         * Permet aux modules d’adapter les données envoyées au template Photo Grimlins.
         *
         * @param array<string,mixed> $template_data
         * @param array<string,mixed> $settings
         * @param Mj_Member_Elementor_Photo_Grimlins_Widget $widget
         */
        $template_data = apply_filters('mj_member_photo_grimlins_template_data', $template_data, $settings, $this);
        $template_data = is_array($template_data) ? $template_data : array();
        $template_data['is_preview'] = !empty($template_data['is_preview']);

        include $template_path;
    }

    private function is_elementor_preview_mode(): bool
    {
        if (did_action('elementor/loaded')) {
            $elementor = \Elementor\Plugin::$instance;
            if ($elementor && isset($elementor->editor) && method_exists($elementor->editor, 'is_edit_mode')) {
                return (bool) $elementor->editor->is_edit_mode();
            }
        }

        return false;
    }
}
