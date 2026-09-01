<?php

if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;

class Mj_Member_Elementor_Toto_Widget extends Widget_Base {
    use Mj_Member_Elementor_Widget_Visibility;

    public function get_name() {
        return 'mj-member-toto';
    }

    public function get_title() {
        return __('Annuaire animateurs & coordinateurs', 'mj-member');
    }

    public function get_icon() {
        return 'eicon-person';
    }

    public function get_categories() {
        return array('general');
    }

    public function get_keywords() {
        return array('mj', 'member', 'annuaire', 'animateur', 'coordinateur');
    }

    public function get_style_depends() {
        return array('mj-member-components');
    }

    protected function register_controls() {
        $role_labels = $this->get_role_labels();

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
                'default' => __('Rencontrez notre équipe', 'mj-member'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'display_title',
            array(
                'label' => __('Afficher le titre', 'mj-member'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Oui', 'mj-member'),
                'label_off' => __('Non', 'mj-member'),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->add_control(
            'description',
            array(
                'label' => __('Description', 'mj-member'),
                'type' => Controls_Manager::TEXTAREA,
                'rows' => 3,
                'placeholder' => __('Une équipe disponible pour accompagner les jeunes.', 'mj-member'),
            )
        );

        $this->add_control(
            'roles',
            array(
                'label' => __('Rôles affichés', 'mj-member'),
                'type' => Controls_Manager::SELECT2,
                'multiple' => true,
                'label_block' => true,
                'options' => $role_labels,
                'default' => array('animateur', 'coordinateur'),
            )
        );

        $this->add_control(
            'limit',
            array(
                'label' => __('Nombre maximum de fiches', 'mj-member'),
                'type' => Controls_Manager::NUMBER,
                'min' => 0,
                'max' => 50,
                'default' => 0,
                'description' => __('0 affiche tous les membres correspondant aux rôles sélectionnés.', 'mj-member'),
            )
        );

        $this->add_control(
            'orderby',
            array(
                'label' => __('Trier par', 'mj-member'),
                'type' => Controls_Manager::SELECT,
                'default' => 'last_name',
                'options' => array(
                    'last_name' => __('Nom', 'mj-member'),
                    'first_name' => __('Prénom', 'mj-member'),
                    'date_inscription' => __('Date d\'inscription', 'mj-member'),
                ),
            )
        );

        $this->add_control(
            'order',
            array(
                'label' => __('Ordre', 'mj-member'),
                'type' => Controls_Manager::SELECT,
                'default' => 'ASC',
                'options' => array(
                    'ASC' => __('Croissant', 'mj-member'),
                    'DESC' => __('Décroissant', 'mj-member'),
                ),
            )
        );

        $this->end_controls_section();

        $this->register_visibility_controls();

        $this->start_controls_section(
            'section_style_header',
            array(
                'label' => __('En-tête', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'header_alignment',
            array(
                'label' => __('Alignement', 'mj-member'),
                'type' => Controls_Manager::CHOOSE,
                'options' => array(
                    'left' => array('title' => __('Gauche', 'mj-member'), 'icon' => 'eicon-text-align-left'),
                    'center' => array('title' => __('Centre', 'mj-member'), 'icon' => 'eicon-text-align-center'),
                    'right' => array('title' => __('Droite', 'mj-member'), 'icon' => 'eicon-text-align-right'),
                ),
                'default' => 'left',
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-staff-directory__header' => 'text-align: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'title_color',
            array(
                'label' => __('Couleur du titre', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-staff-directory__title' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            array(
                'name' => 'title_typography',
                'selector' => '{{WRAPPER}} .mj-member-staff-directory__title',
            )
        );

        $this->add_control(
            'description_color',
            array(
                'label' => __('Couleur de la description', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-staff-directory__description' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            array(
                'name' => 'description_typography',
                'selector' => '{{WRAPPER}} .mj-member-staff-directory__description',
            )
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_cards',
            array(
                'label' => __('Cartes', 'mj-member'),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'grid_columns',
            array(
                'label' => __('Colonnes', 'mj-member'),
                'type' => Controls_Manager::NUMBER,
                'min' => 1,
                'max' => 4,
                'default' => 3,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-staff-directory__grid' => 'grid-template-columns: repeat({{VALUE}}, minmax(220px, 1fr));',
                ),
            )
        );

        $this->add_control(
            'card_background',
            array(
                'label' => __('Fond', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-staff-card' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'card_border_color',
            array(
                'label' => __('Bordure', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-staff-card' => 'border-color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            array(
                'name' => 'card_border',
                'selector' => '{{WRAPPER}} .mj-member-staff-card',
            )
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            array(
                'name' => 'card_shadow',
                'selector' => '{{WRAPPER}} .mj-member-staff-card',
            )
        );

        $this->add_control(
            'card_radius',
            array(
                'label' => __('Arrondi', 'mj-member'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array('px' => array('min' => 0, 'max' => 48)),
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-staff-card' => 'border-radius: {{SIZE}}{{UNIT}};',
                    '{{WRAPPER}} .mj-member-staff-card__cover img' => 'border-radius: calc({{SIZE}}{{UNIT}} - 4px);',
                ),
            )
        );

        $this->add_control(
            'accent_color',
            array(
                'label' => __('Couleur d\'accent', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-staff-directory' => '--mj-staff-accent: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'email_color',
            array(
                'label' => __('Couleur email', 'mj-member'),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .mj-member-staff-card__email a' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            array(
                'name' => 'surname_typography',
                'label' => __('Typo du nom', 'mj-member'),
                'selector' => '{{WRAPPER}} .mj-member-staff-card__surname',
            )
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            array(
                'name' => 'email_typography',
                'label' => __('Typo email', 'mj-member'),
                'selector' => '{{WRAPPER}} .mj-member-staff-card__email',
            )
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            array(
                'name' => 'description_card_typography',
                'label' => __('Typo description', 'mj-member'),
                'selector' => '{{WRAPPER}} .mj-member-staff-card__description',
            )
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $this->apply_visibility_to_wrapper($settings, 'mj-member-staff-directory');

        $title = isset($settings['title']) ? trim((string) $settings['title']) : '';
        $display_title = !isset($settings['display_title']) || $settings['display_title'] === 'yes';
        $description = isset($settings['description']) ? trim((string) $settings['description']) : '';

        $roles = $this->normalize_roles(isset($settings['roles']) ? $settings['roles'] : array());
        $limit = isset($settings['limit']) ? max(0, (int) $settings['limit']) : 0;

        $orderby = isset($settings['orderby']) ? sanitize_key($settings['orderby']) : 'last_name';
        $allowed_orderby = array('last_name', 'first_name', 'date_inscription');
        if (!in_array($orderby, $allowed_orderby, true)) {
            $orderby = 'last_name';
        }

        $order = isset($settings['order']) ? strtoupper((string) $settings['order']) : 'ASC';
        $order = $order === 'DESC' ? 'DESC' : 'ASC';

        $members = $this->get_members($roles, $limit, $orderby, $order);

        if (empty($members) && $this->is_elementor_preview()) {
            $members = $this->get_preview_members();
        }

        $role_labels = $this->get_role_labels();

        echo '<div class="mj-member-staff-directory">';

        if ($display_title && $title !== '') {
            echo '<div class="mj-member-staff-directory__header">';
            echo '<h2 class="mj-member-staff-directory__title">' . esc_html($title) . '</h2>';
            if ($description !== '') {
                echo '<p class="mj-member-staff-directory__description">' . esc_html($description) . '</p>';
            }
            echo '</div>';
        } elseif ($description !== '') {
            echo '<div class="mj-member-staff-directory__header">';
            echo '<p class="mj-member-staff-directory__description">' . esc_html($description) . '</p>';
            echo '</div>';
        }

        if (empty($members)) {
            echo '<div class="mj-member-staff-directory__empty">' . esc_html__('Aucun animateur ou coordinateur trouvé.', 'mj-member') . '</div>';
            echo '</div>';
            return;
        }

        echo '<div class="mj-member-staff-directory__grid">';

        foreach ($members as $member) {
            $member_id = $this->get_member_field($member, 'id', 0);
            $first_name = trim((string) $this->get_member_field($member, 'first_name', ''));
            $last_name = trim((string) $this->get_member_field($member, 'last_name', ''));
            $role_key = sanitize_key((string) $this->get_member_field($member, 'role', ''));
            $role_label = isset($role_labels[$role_key]) ? $role_labels[$role_key] : ucfirst($role_key);
            $email = sanitize_email((string) $this->get_member_field($member, 'email', ''));
            $description_courte = trim((string) $this->get_member_field($member, 'description_courte', ''));

            $cover = $this->get_member_cover_url($member);
            $initials = $this->get_member_initials($first_name, $last_name);

            $full_name = trim($first_name . ' ' . $last_name);
            $surname = $last_name !== '' ? $last_name : $full_name;

            $card_classes = array('mj-member-staff-card');
            if ($role_key !== '') {
                $card_classes[] = 'mj-member-staff-card--role-' . sanitize_html_class($role_key);
            }

            echo '<article class="' . esc_attr(implode(' ', $card_classes)) . '" data-member-id="' . esc_attr((string) $member_id) . '">';

            if ($cover !== '') {
                echo '<figure class="mj-member-staff-card__cover">';
                echo '<img src="' . esc_url($cover) . '" alt="' . esc_attr($full_name !== '' ? $full_name : $surname) . '" loading="lazy" decoding="async" />';
                echo '</figure>';
            } else {
                echo '<div class="mj-member-staff-card__cover mj-member-staff-card__cover--placeholder" aria-hidden="true">';
                echo '<span class="mj-member-staff-card__initials">' . esc_html($initials) . '</span>';
                echo '</div>';
            }

            echo '<div class="mj-member-staff-card__body">';
            if ($surname !== '') {
                echo '<p class="mj-member-staff-card__surname">' . esc_html($surname) . '</p>';
            }

            if ($first_name !== '' && $last_name !== '') {
                echo '<p class="mj-member-staff-card__firstname">' . esc_html($first_name) . '</p>';
            }

            if ($role_label !== '') {
                echo '<p class="mj-member-staff-card__role">' . esc_html($role_label) . '</p>';
            }

            if ($email !== '') {
                $email_safe = antispambot($email);
                echo '<p class="mj-member-staff-card__email"><a href="mailto:' . esc_attr($email_safe) . '">' . esc_html($email_safe) . '</a></p>';
            }

            if ($description_courte !== '') {
                echo '<p class="mj-member-staff-card__description">' . esc_html($description_courte) . '</p>';
            }

            echo '</div>';
            echo '</article>';
        }

        echo '</div>';
        echo '</div>';
    }

    /**
     * @param array<int,string>|string $roles
     * @return array<int,string>
     */
    private function normalize_roles($roles) {
        $allowed = array('animateur', 'coordinateur');
        $normalized = array();

        if (is_string($roles)) {
            $roles = array($roles);
        }

        if (is_array($roles)) {
            foreach ($roles as $role) {
                $key = sanitize_key((string) $role);
                if (in_array($key, $allowed, true)) {
                    $normalized[] = $key;
                }
            }
        }

        if (empty($normalized)) {
            $normalized = $allowed;
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param mixed $member
     * @param string $field
     * @param mixed $default
     * @return mixed
     */
    private function get_member_field($member, $field, $default = null) {
        if (class_exists('Mj\\Member\\Classes\\Crud\\MjMembers')) {
            return \Mj\Member\Classes\Crud\MjMembers::getField($member, $field, $default);
        }

        if (is_array($member) && array_key_exists($field, $member)) {
            return $member[$field];
        }

        if (is_object($member) && isset($member->{$field})) {
            return $member->{$field};
        }

        return $default;
    }

    /**
     * @param array<int,string> $roles
     * @return array<int,mixed>
     */
    private function get_members(array $roles, $limit, $orderby, $order) {
        if (!class_exists('Mj\\Member\\Classes\\Crud\\MjMembers')) {
            return array();
        }

        $all = array();
        foreach ($roles as $role) {
            $results = \Mj\Member\Classes\Crud\MjMembers::getAll(
                0,
                0,
                $orderby,
                $order,
                '',
                array('role' => $role)
            );

            if (is_array($results) && !empty($results)) {
                $all = array_merge($all, $results);
            }
        }

        if (empty($all)) {
            return array();
        }

        $all = $this->sort_members($all, $orderby, $order);

        if ($limit > 0 && count($all) > $limit) {
            $all = array_slice($all, 0, $limit);
        }

        return $all;
    }

    /**
     * @param array<int,mixed> $members
     * @return array<int,mixed>
     */
    private function sort_members(array $members, $orderby, $order) {
        $order_multiplier = $order === 'DESC' ? -1 : 1;

        usort(
            $members,
            function ($a, $b) use ($orderby, $order_multiplier) {
                $value_a = $this->get_member_field($a, $orderby, '');
                $value_b = $this->get_member_field($b, $orderby, '');

                if (is_string($value_a)) {
                    $value_a = strtolower($value_a);
                }

                if (is_string($value_b)) {
                    $value_b = strtolower($value_b);
                }

                if ($value_a === $value_b) {
                    $surname_a = strtolower((string) $this->get_member_field($a, 'last_name', ''));
                    $surname_b = strtolower((string) $this->get_member_field($b, 'last_name', ''));

                    if ($surname_a === $surname_b) {
                        return 0;
                    }

                    return ($surname_a < $surname_b ? -1 : 1) * $order_multiplier;
                }

                return ($value_a < $value_b ? -1 : 1) * $order_multiplier;
            }
        );

        return $members;
    }

    private function get_member_cover_url($member) {
        $photo_id = (int) $this->get_member_field($member, 'photo_id', 0);
        if ($photo_id > 0) {
            $image = wp_get_attachment_image_src($photo_id, 'medium');
            if (is_array($image) && !empty($image[0])) {
                return esc_url_raw($image[0]);
            }
        }

        $email = sanitize_email((string) $this->get_member_field($member, 'email', ''));
        if ($email !== '') {
            $avatar = get_avatar_url($email, array('size' => 256));
            if (is_string($avatar) && $avatar !== '') {
                return esc_url_raw($avatar);
            }
        }

        $wp_user_id = (int) $this->get_member_field($member, 'wp_user_id', 0);
        if ($wp_user_id > 0) {
            $avatar = get_avatar_url($wp_user_id, array('size' => 256));
            if (is_string($avatar) && $avatar !== '') {
                return esc_url_raw($avatar);
            }
        }

        return '';
    }

    private function get_member_initials($first_name, $last_name) {
        $initials = '';

        if ($last_name !== '') {
            $initials .= strtoupper(substr($last_name, 0, 1));
        }

        if ($first_name !== '') {
            $initials .= strtoupper(substr($first_name, 0, 1));
        }

        if ($initials === '') {
            $initials = __('?', 'mj-member');
        }

        return $initials;
    }

    private function get_role_labels() {
        if (!class_exists('Mj\\Member\\Classes\\Crud\\MjMembers')) {
            return array(
                'animateur' => __('Animateur', 'mj-member'),
                'coordinateur' => __('Coordinateur', 'mj-member'),
            );
        }

        $labels = \Mj\Member\Classes\Crud\MjMembers::getRoleLabels();

        $defaults = array(
            'animateur' => __('Animateur', 'mj-member'),
            'coordinateur' => __('Coordinateur', 'mj-member'),
        );

        return array_intersect_key($labels, $defaults) + $defaults;
    }

    private function is_elementor_preview() {
        if (!class_exists('Elementor\\Plugin')) {
            return false;
        }

        $instance = \Elementor\Plugin::$instance;
        if (!$instance) {
            return false;
        }

        if (isset($instance->editor) && method_exists($instance->editor, 'is_edit_mode')) {
            return (bool) $instance->editor->is_edit_mode();
        }

        return false;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function get_preview_members() {
        return array(
            array(
                'id' => 1,
                'first_name' => 'Léa',
                'last_name' => 'Dupont',
                'email' => 'lea.dupont@example.com',
                'role' => 'animateur',
                'description_courte' => __('Spécialiste des ateliers créatifs et numériques.', 'mj-member'),
            ),
            array(
                'id' => 2,
                'first_name' => 'Mika',
                'last_name' => 'Lambert',
                'email' => 'mika.lambert@example.com',
                'role' => 'coordinateur',
                'description_courte' => __('Coordinateur des projets jeunesse et des partenariats scolaires.', 'mj-member'),
            ),
        );
    }
}
