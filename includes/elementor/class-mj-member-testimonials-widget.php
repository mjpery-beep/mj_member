<?php

use Elementor\Controls_Manager;
use Elementor\Widget_Base;
use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Elementor widget for testimonials.
 * Allows members to submit testimonials with text, photos, and video.
 */
class Mj_Member_Elementor_Testimonials_Widget extends Widget_Base
{
    use Mj_Member_Elementor_Widget_Visibility;

    public function get_name()
    {
        return 'mj-member-testimonials';
    }

    public function get_title()
    {
        return __('MJ – Témoignages', 'mj-member');
    }

    public function get_icon()
    {
        return 'eicon-testimonial';
    }

    public function get_categories()
    {
        return array('mj-member');
    }

    public function get_keywords()
    {
        return array('mj', 'member', 'testimonial', 'témoignage', 'avis', 'review', 'photo', 'video');
    }

    protected function register_controls()
    {
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
                'default' => __('Témoignages', 'mj-member'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'intro_text',
            array(
                'label' => __('Texte introductif', 'mj-member'),
                'type' => Controls_Manager::TEXTAREA,
                'rows' => 3,
                'placeholder' => __('Partagez votre expérience à la Maison de Jeunes !', 'mj-member'),
            )
        );

        $this->add_control(
            'allow_submission',
            array(
                'label' => __('Autoriser la soumission', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->add_control(
            'show_approved_list',
            array(
                'label' => __('Afficher les témoignages approuvés', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->add_control(
            'per_page',
            array(
                'label' => __('Témoignages par page', 'mj-member'),
                'type' => Controls_Manager::NUMBER,
                'min' => 1,
                'max' => 50,
                'default' => 6,
                'condition' => array(
                    'show_approved_list' => 'yes',
                ),
            )
        );

        $this->add_control(
            'max_photos',
            array(
                'label' => __('Nombre max de photos', 'mj-member'),
                'type' => Controls_Manager::NUMBER,
                'min' => 1,
                'max' => 10,
                'default' => 5,
                'condition' => array(
                    'allow_submission' => 'yes',
                ),
            )
        );

        $this->add_control(
            'allow_video',
            array(
                'label' => __('Autoriser la vidéo', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
                'condition' => array(
                    'allow_submission' => 'yes',
                ),
            )
        );

        $this->add_control(
            'featured_only',
            array(
                'label' => __('Uniquement les mis en avant', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => '',
                'condition' => array(
                    'show_approved_list' => 'yes',
                ),
            )
        );

        $this->add_control(
            'display_template',
            array(
                'label' => __('Template d\'affichage', 'mj-member'),
                'type' => Controls_Manager::SELECT,
                'default' => 'feed',
                'options' => array(
                    'feed' => __('Par défaut (fil d\'actualité)', 'mj-member'),
                    'carousel-3' => __('Carrousel 3 témoignages', 'mj-member'),
                ),
                'condition' => array(
                    'show_approved_list' => 'yes',
                ),
            )
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style',
            array(
                'label' => __('Style', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'layout',
            array(
                'label' => __('Disposition', 'mj-member'),
                'type' => Controls_Manager::SELECT,
                'default' => 'grid',
                'options' => array(
                    'grid' => __('Grille', 'mj-member'),
                    'list' => __('Liste', 'mj-member'),
                    'carousel' => __('Carrousel', 'mj-member'),
                ),
            )
        );

        $this->add_control(
            'columns',
            array(
                'label' => __('Colonnes', 'mj-member'),
                'type' => Controls_Manager::SELECT,
                'default' => '2',
                'options' => array(
                    '1' => '1',
                    '2' => '2',
                    '3' => '3',
                ),
                'condition' => array(
                    'layout' => 'grid',
                ),
            )
        );

        $this->end_controls_section();

        $this->register_visibility_controls();
    }

    protected function render()
    {
        $settings = $this->get_settings_for_display();
        $this->apply_visibility_to_wrapper($settings, 'mj-testimonials');

        $template = Config::path() . 'includes/templates/elementor/testimonials.php';
        if (is_readable($template)) {
            $widget = $this;
            include $template;
        }
    }
}
