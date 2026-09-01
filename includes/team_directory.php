<?php

use Mj\Member\Classes\Crud\CrudQueryBuilder;
use Mj\Member\Classes\Crud\MjMembers;
use Mj\Member\Classes\Value\MemberData;

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('mj_member_get_team_directory_members')) {
    /**
     * Récupère les membres destinés à l'annuaire des animateurs/coordinateurs.
     *
     * @param array<string,mixed> $args
     * @return array<int,array<string,mixed>>
     */
    function mj_member_get_team_directory_members($args = array()) {
        if (!class_exists(MjMembers::class) || !class_exists(CrudQueryBuilder::class)) {
            return array();
        }

        $defaults = array(
            'roles' => array(MjMembers::ROLE_ANIMATEUR, MjMembers::ROLE_COORDINATEUR),
            'limit' => 0,
            'orderby' => 'last_name',
            'order' => 'ASC',
            'include_inactive' => false,
            'search' => '',
        );

        $args = wp_parse_args($args, $defaults);

        $requested_roles = array();
        $allowed_roles = array(
            MjMembers::ROLE_ANIMATEUR,
            MjMembers::ROLE_COORDINATEUR,
        );

        foreach ((array) $args['roles'] as $role) {
            $role_key = sanitize_key((string) $role);
            if ($role_key !== '' && in_array($role_key, $allowed_roles, true)) {
                $requested_roles[$role_key] = $role_key;
            }
        }

        if (empty($requested_roles)) {
            $requested_roles = array(
                MjMembers::ROLE_ANIMATEUR => MjMembers::ROLE_ANIMATEUR,
                MjMembers::ROLE_COORDINATEUR => MjMembers::ROLE_COORDINATEUR,
            );
        }

        $allowed_orderby = array('last_name', 'first_name', 'nickname', 'date_inscription', 'created_at');
        $orderby = isset($args['orderby']) ? sanitize_key((string) $args['orderby']) : 'last_name';
        if (!in_array($orderby, $allowed_orderby, true)) {
            $orderby = 'last_name';
        }

        $order = isset($args['order']) ? strtoupper((string) $args['order']) : 'ASC';
        $order = $order === 'DESC' ? 'DESC' : 'ASC';

        $limit = isset($args['limit']) ? max(0, (int) $args['limit']) : 0;
        $include_inactive = !empty($args['include_inactive']);
        $search = isset($args['search']) ? sanitize_text_field((string) $args['search']) : '';

        $table = MjMembers::getTableName(MjMembers::TABLE_NAME);
        $builder = CrudQueryBuilder::for_table($table);
        $builder->where_in_strings('role', array_values($requested_roles), 'sanitize_key');

        if (!$include_inactive) {
            $builder->where_equals('status', MjMembers::STATUS_ACTIVE, 'sanitize_key');
        }

        $builder->where_raw('(anonymized_at IS NULL OR anonymized_at = %s)', array(''));

        if ($search !== '') {
            $builder->where_tokenized_search(
                array('first_name', 'last_name', 'nickname', 'email'),
                $search
            );
        }

        list($sql, $params) = $builder->build_select('*', $orderby, $order, $limit, 0);
        $wpdb = MjMembers::getWpdb();

        if (!empty($params)) {
            $sql = call_user_func_array(array($wpdb, 'prepare'), array_merge(array($sql), $params));
        }

        $rows = $wpdb->get_results($sql);
        if (!is_array($rows) || empty($rows)) {
            return array();
        }

        $role_labels = MjMembers::getRoleLabels();
        $members = array();

        foreach ($rows as $row) {
            $member = MemberData::fromRow($row);
            $members[] = mj_member_prepare_team_directory_member($member, $role_labels);
        }

        return $members;
    }
}

if (!function_exists('mj_member_prepare_team_directory_member')) {
    /**
     * Normalise les données d'un membre pour l'affichage dans le widget Elementor.
     *
     * @param MemberData $member
     * @param array<string,string> $role_labels
     * @return array<string,mixed>
     */
    function mj_member_prepare_team_directory_member(MemberData $member, array $role_labels = array()) {
        $id = (int) $member->get('id', 0);
        $first_name = sanitize_text_field((string) $member->get('first_name', ''));
        $last_name = sanitize_text_field((string) $member->get('last_name', ''));
        $nickname = sanitize_text_field((string) $member->get('nickname', ''));
        $display_name = trim(trim($first_name . ' ' . $last_name));
        if ($display_name === '' && $nickname !== '') {
            $display_name = $nickname;
        }

        $role_key = sanitize_key((string) $member->get('role', ''));
        $role_label = '';
        if ($role_key !== '' && isset($role_labels[$role_key])) {
            $role_label = sanitize_text_field((string) $role_labels[$role_key]);
        } elseif ($role_key !== '') {
            $role_label = sanitize_text_field(ucfirst(str_replace('_', ' ', $role_key)));
        }

        $email = sanitize_email((string) $member->get('email', ''));
        $description = sanitize_textarea_field((string) $member->get('description_courte', ''));

        $photo_id = 0;
        $photo_value = $member->get('photo_id');
        if (!empty($photo_value)) {
            $photo_id = (int) $photo_value;
        }

        $cover_url = '';
        $avatar_url = '';
        if ($photo_id > 0) {
            $cover_image = wp_get_attachment_image_src($photo_id, 'large');
            if (is_array($cover_image) && isset($cover_image[0])) {
                $cover_url = esc_url_raw((string) $cover_image[0]);
            }

            $avatar_image = wp_get_attachment_image_src($photo_id, 'thumbnail');
            if (is_array($avatar_image) && isset($avatar_image[0])) {
                $avatar_url = esc_url_raw((string) $avatar_image[0]);
            }
        }

        if ($avatar_url === '' && $email !== '' && function_exists('get_avatar_url')) {
            $avatar_url = esc_url_raw(get_avatar_url($email, array('size' => 192)));
        }

        if ($cover_url === '' && $avatar_url !== '') {
            $cover_url = $avatar_url;
        }

        $cover_alt = $display_name !== ''
            ? sprintf(__('Photo de %s', 'mj-member'), $display_name)
            : __('Photo du membre', 'mj-member');

        $initials_source = $display_name !== '' ? $display_name : ($nickname !== '' ? $nickname : $first_name);
        $initials = mj_member_team_directory_initials($initials_source);

        return array(
            'id' => $id,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'name' => $display_name,
            'nickname' => $nickname,
            'role' => $role_key,
            'role_label' => $role_label,
            'email' => $email,
            'mailto' => $email !== '' ? 'mailto:' . $email : '',
            'description' => $description,
            'cover' => array(
                'id' => $photo_id,
                'url' => $cover_url,
                'alt' => $cover_alt,
            ),
            'avatar' => array(
                'url' => $avatar_url,
                'initials' => $initials,
                'alt' => $cover_alt,
            ),
        );
    }
}

if (!function_exists('mj_member_team_directory_initials')) {
    /**
     * Extrait des initiales à partir d'un label.
     *
     * @param string $label
     * @return string
     */
    function mj_member_team_directory_initials($label) {
        $label = trim((string) $label);
        if ($label === '') {
            return 'MJ';
        }

        if (function_exists('mj_member_contact_extract_initials')) {
            $initials = mj_member_contact_extract_initials($label);
            return sanitize_text_field($initials !== '' ? $initials : 'MJ');
        }

        if (function_exists('mb_strtoupper') && function_exists('mb_substr')) {
            $chunks = preg_split('/\s+/', $label);
            if (is_array($chunks) && count($chunks) >= 2) {
                $initials = mb_strtoupper(mb_substr($chunks[0], 0, 1, 'UTF-8') . mb_substr($chunks[1], 0, 1, 'UTF-8'), 'UTF-8');
                return sanitize_text_field($initials !== '' ? $initials : 'MJ');
            }

            $single = mb_strtoupper(mb_substr($label, 0, 2, 'UTF-8'), 'UTF-8');
            return sanitize_text_field($single !== '' ? $single : 'MJ');
        }

        $parts = preg_split('/\s+/', $label);
        if (is_array($parts) && count($parts) >= 2) {
            $initials = strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
            $initials = preg_replace('/[^A-Z]/', '', (string) $initials);
            return $initials !== '' ? $initials : 'MJ';
        }

        $fallback = strtoupper(substr($label, 0, 2));
        $fallback = preg_replace('/[^A-Z]/', '', (string) $fallback);
        return $fallback !== '' ? $fallback : 'MJ';
    }
}

if (!function_exists('mj_member_get_team_directory_preview_members')) {
    /**
     * Jeu de données fictives pour l'aperçu Elementor.
     *
     * @return array<int,array<string,mixed>>
     */
    function mj_member_get_team_directory_preview_members() {
        return array(
            array(
                'id' => 1,
                'first_name' => 'Camille',
                'last_name' => 'Dupont',
                'name' => 'Camille Dupont',
                'nickname' => 'Cam',
                'role' => MjMembers::ROLE_ANIMATEUR,
                'role_label' => __('Animateur', 'mj-member'),
                'email' => 'camille.dupont@example.com',
                'mailto' => 'mailto:camille.dupont@example.com',
                'description' => __('Anime les ateliers créatifs et les sorties culturelles.', 'mj-member'),
                'cover' => array(
                    'id' => 0,
                    'url' => '',
                    'alt' => __('Photo de Camille Dupont', 'mj-member'),
                ),
                'avatar' => array(
                    'url' => '',
                    'initials' => 'CD',
                    'alt' => __('Photo de Camille Dupont', 'mj-member'),
                ),
            ),
            array(
                'id' => 2,
                'first_name' => 'Noah',
                'last_name' => 'Van Acker',
                'name' => 'Noah Van Acker',
                'nickname' => 'Nova',
                'role' => MjMembers::ROLE_COORDINATEUR,
                'role_label' => __('Coordinateur', 'mj-member'),
                'email' => 'noah.vanacker@example.com',
                'mailto' => 'mailto:noah.vanacker@example.com',
                'description' => __('Coordonne les projets solidaires et le suivi des bénévoles.', 'mj-member'),
                'cover' => array(
                    'id' => 0,
                    'url' => '',
                    'alt' => __('Photo de Noah Van Acker', 'mj-member'),
                ),
                'avatar' => array(
                    'url' => '',
                    'initials' => 'NV',
                    'alt' => __('Photo de Noah Van Acker', 'mj-member'),
                ),
            ),
            array(
                'id' => 3,
                'first_name' => 'Inès',
                'last_name' => 'Mertens',
                'name' => 'Inès Mertens',
                'nickname' => 'Ness',
                'role' => MjMembers::ROLE_ANIMATEUR,
                'role_label' => __('Animatrice', 'mj-member'),
                'email' => 'ines.mertens@example.com',
                'mailto' => 'mailto:ines.mertens@example.com',
                'description' => __('Référente soirées jeux et ateliers médias.', 'mj-member'),
                'cover' => array(
                    'id' => 0,
                    'url' => '',
                    'alt' => __("Photo d'Inès Mertens", 'mj-member'),
                ),
                'avatar' => array(
                    'url' => '',
                    'initials' => 'IM',
                    'alt' => __("Photo d'Inès Mertens", 'mj-member'),
                ),
            ),
        );
    }
}
