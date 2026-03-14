<?php

if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Mj\Member\Core\Config;
use Mj\Member\Classes\Crud\MjDynamicFields;

class Mj_Member_Elementor_Admin_Dashboard_Widget extends Widget_Base {
    use Mj_Member_Elementor_Widget_Visibility;

    public function get_name() {
        return 'mj-member-admin-dashboard';
    }

    public function get_title() {
        return __('Tableau de bord MJ', 'mj-member');
    }

    public function get_icon() {
        return 'eicon-dashboard';
    }

    public function get_categories() {
        return array('mj-member');
    }

    public function get_keywords() {
        return array('mj', 'member', 'dashboard', 'tableau', 'bord', 'statistiques', 'stats', 'admin');
    }

    /**
     * Build option list of chart-friendly dynamic fields.
     *
     * @return array<string,string>
     */
    private function get_dynamic_field_options(): array {
        $options = array();

        if (!class_exists('\Mj\Member\Classes\Crud\MjDynamicFields')) {
            return $options;
        }

        $chart_types = array(
            MjDynamicFields::TYPE_DROPDOWN,
            MjDynamicFields::TYPE_RADIO,
            MjDynamicFields::TYPE_CHECKBOX,
            MjDynamicFields::TYPE_CHECKLIST,
        );

        $fields = MjDynamicFields::getAll();
        foreach ($fields as $field) {
            if (in_array($field->field_type, $chart_types, true)) {
                $type_label = MjDynamicFields::getTypeLabels()[$field->field_type] ?? $field->field_type;
                $options[(string) $field->id] = $field->title . ' (' . strip_tags($type_label) . ')';
            }
        }

        return $options;
    }

    protected function register_controls() {
        $this->start_controls_section(
            'section_content',
            array(
                'label' => __('Contenu', 'mj-member'),
            )
        );

        $this->add_control(
            'dynamic_field_ids',
            array(
                'label'       => __('🧩 Données dynamiques à afficher', 'mj-member'),
                'description' => __('Sélectionnez les champs dynamiques à inclure comme graphiques dans la section Membres.', 'mj-member'),
                'type'        => Controls_Manager::SELECT2,
                'multiple'    => true,
                'options'     => $this->get_dynamic_field_options(),
                'default'     => array(),
                'label_block' => true,
            )
        );

        $this->end_controls_section();

        $this->register_visibility_controls();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $this->apply_visibility_to_wrapper($settings, 'mj-admin-dashboard-widget');

        $template_path = Config::path() . 'includes/templates/elementor/admin_dashboard.php';
        if (file_exists($template_path)) {
            $widget = $this;
            include $template_path;
        }
    }
}
