<?php

use Elementor\Controls_Manager;
use Elementor\Widget_Base;
use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

class Mj_Member_Elementor_Inventory_Widget extends Widget_Base
{
    use Mj_Member_Elementor_Widget_Visibility;

    public function get_name() { return 'mj-member-inventory'; }
    public function get_title() { return __('MJ – Inventaire', 'mj-member'); }
    public function get_icon() { return 'eicon-library-upload'; }
    public function get_categories() { return array('mj-member'); }
    public function get_keywords() { return array('inventaire', 'matériel', 'objet', 'emprunt'); }

    protected function register_controls()
    {
        $this->start_controls_section('section_content', array('label' => __('Contenu', 'mj-member')));
        $this->add_control('title', array(
            'label' => __('Titre', 'mj-member'),
            'type' => Controls_Manager::TEXT,
            'default' => __('Inventaire du matériel', 'mj-member'),
            'label_block' => true,
        ));
        $this->end_controls_section();
        $this->register_visibility_controls();
    }

    public function render()
    {
        $settings = $this->get_settings_for_display();
        $this->apply_visibility_to_wrapper($settings, 'mj-inventory-widget');
        $title = sanitize_text_field((string) ($settings['title'] ?? ''));
        
        // Ensure the script is localized with nonce
        self::ensure_script_localized();
        
        $template = Config::path() . 'includes/templates/elementor/inventory_manager.php';
        if (is_readable($template)) {
            include $template;
        }
    }

    private static function ensure_script_localized(): void
    {
        static $localized = false;

        if ($localized) {
            return;
        }

        $handle = 'mj-member-inventory-manager';
        
        if (!wp_script_is($handle, 'registered')) {
            return;
        }

        // Enqueue the script and style first
        wp_enqueue_style($handle);
        wp_enqueue_script($handle);

        // Then localize with nonce
        wp_localize_script(
            $handle,
            'mjInventoryConfig',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mj_inventory'),
            )
        );

        $localized = true;
    }
}
