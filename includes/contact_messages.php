<?php

use Mj\Member\Classes\MjRoles;
use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('mj_member_contact_member_target_key')) {
    /**
     * @return string
     */
    function mj_member_contact_member_target_key() {
        return defined('MjContactMessages::TARGET_MEMBER')
            ? constant('MjContactMessages::TARGET_MEMBER')
            : 'member';
    }
}

if (!function_exists('mj_member_contact_get_default_avatar')) {
    /**
     * @return array<string,mixed>
     */
    function mj_member_contact_get_default_avatar() {
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        $cache = array(
            'id' => 0,
            'url' => '',
        );

        $default_id = (int) get_option('mj_login_default_avatar_id', 0);
        if ($default_id > 0) {
            $image = wp_get_attachment_image_src($default_id, 'thumbnail');
            if ($image) {
                $cache['id'] = $default_id;
                $cache['url'] = esc_url_raw($image[0]);
            }
        }

        return $cache;
    }
}

if (!function_exists('mj_member_contact_pick_initial')) {
    /**
     * @param string $string
     * @return string
     */
    function mj_member_contact_pick_initial($string) {
        $string = (string) $string;
        if ($string === '') {
            return '';
        }

        if (function_exists('mb_substr') && function_exists('mb_strtoupper')) {
            return mb_strtoupper(mb_substr($string, 0, 1, 'UTF-8'), 'UTF-8');
        }

        return strtoupper(substr($string, 0, 1));
    }
}

if (!function_exists('mj_member_contact_extract_initials')) {
    /**
     * @param string $label
     * @return string
     */
    function mj_member_contact_extract_initials($label) {
        if (function_exists('mj_member_extract_initials')) {
            return mj_member_extract_initials($label);
        }

        $label = trim((string) $label);
        if ($label === '') {
            return '';
        }

        $parts = preg_split('/[\s\-]+/', $label);
        $initials = '';

        if (is_array($parts)) {
            foreach ($parts as $part) {
                $part = trim((string) $part);
                if ($part === '') {
                    continue;
                }
                $initials .= mj_member_contact_pick_initial($part);
                if (strlen($initials) >= 2) {
                    break;
                }
            }
        }

        if ($initials === '') {
            $initials = mj_member_contact_pick_initial($label);
        }

        return $initials;
    }
}

if (!function_exists('mj_member_contact_build_avatar_payload')) {
    /**
     * @param string $display_name
     * @param object|null $member_row
     * @param string $email
     * @return array<string,mixed>
     */
    function mj_member_contact_build_avatar_payload($display_name, $member_row = null, $email = '') {
        $avatar_url = '';
        $avatar_id = 0;

        if (is_object($member_row) && !empty($member_row->photo_id)) {
            $photo_id = (int) $member_row->photo_id;
            if ($photo_id > 0) {
                $image = wp_get_attachment_image_src($photo_id, 'thumbnail');
                if ($image) {
                    $avatar_url = esc_url_raw($image[0]);
                    $avatar_id = $photo_id;
                }
            }
        }

        $default_avatar = mj_member_contact_get_default_avatar();
        if ($avatar_url === '' && !empty($default_avatar['url'])) {
            $avatar_url = esc_url_raw((string) $default_avatar['url']);
            if (!empty($default_avatar['id'])) {
                $avatar_id = (int) $default_avatar['id'];
            }
        }

        $email = sanitize_email($email);
        if ($avatar_url === '' && $email !== '' && function_exists('get_avatar_url')) {
            $avatar_url = esc_url_raw(get_avatar_url($email, array('size' => 96)));
        }

        $initials = mj_member_contact_extract_initials($display_name);
        $alt = $display_name !== '' ? sprintf(__('Photo de %s', 'mj-member'), $display_name) : __('Photo du destinataire', 'mj-member');

        return array(
            'url' => $avatar_url,
            'id' => $avatar_id,
            'alt' => sanitize_text_field($alt),
            'initials' => $initials,
        );
    }
}

if (!function_exists('mj_member_contact_format_recipient_option')) {
    /**
     * @param array<string,mixed> $args
     * @return array<string,mixed>
     */
    function mj_member_contact_format_recipient_option($args) {
        $defaults = array(
            'value' => '',
            'label' => '',
            'type' => '',
            'reference' => 0,
            'role' => '',
            'role_label' => '',
            'member' => null,
            'email' => '',
            'description' => '',
            'is_cover' => false,
            'cover_theme' => '',
        );

        $data = wp_parse_args($args, $defaults);

        $value = (string) $data['value'];
        $label = trim((string) ($data['label'] !== '' ? $data['label'] : $value));
        $type = sanitize_key((string) $data['type']);
        $reference = (int) $data['reference'];
        $role = $data['role'] !== '' ? sanitize_key((string) $data['role']) : '';
        $role_label = $data['role_label'] !== '' ? sanitize_text_field((string) $data['role_label']) : '';
        $description = $data['description'] !== '' ? sanitize_text_field((string) $data['description']) : '';
        $cover_theme = $data['cover_theme'] !== '' ? sanitize_key((string) $data['cover_theme']) : '';

        $member_row = is_object($data['member']) ? $data['member'] : null;
        $email = isset($data['email']) ? sanitize_email((string) $data['email']) : '';

        $avatar = mj_member_contact_build_avatar_payload($label, $member_row, $email);

        return array(
            'value' => $value,
            'label' => sanitize_text_field($label),
            'type' => $type,
            'reference' => $reference,
            'role' => $role,
            'role_label' => $role_label,
            'avatar' => $avatar,
            'description' => $description,
            'email' => $email,
            'is_cover' => !empty($data['is_cover']),
            'cover_theme' => $cover_theme,
        );
    }
}

if (!function_exists('mj_member_get_contact_recipient_options')) {
    /**
     * @return array<int,array<string,mixed>>
     */
    function mj_member_get_contact_recipient_options() {
        $options = array();

        $current_member = function_exists('mj_member_get_current_member') ? mj_member_get_current_member() : null;
        $current_role = '';
        if ($current_member && isset($current_member->role)) {
            $current_role = sanitize_key((string) $current_member->role);
        }

        $member_target = mj_member_contact_member_target_key();

        $allow_member_targets = false;
        if ($current_role !== '' && MjRoles::isAnimateurOrCoordinateur($current_role)) {
            $allow_member_targets = true;
        }

        if (!$allow_member_targets && class_exists('\Mj\Member\Core\Config')) {
            $capability = Config::contactCapability();
            if (is_string($capability) && $capability !== '' && current_user_can($capability)) {
                $allow_member_targets = true;
            }
        }

        if (class_exists('MjMembers')) {
            $role_labels = MjMembers::getRoleLabels();

            $coordinateurs = MjMembers::getAll(0, 0, 'last_name', 'ASC', '', array('role' => MjRoles::COORDINATEUR));
            if (!empty($coordinateurs) && is_array($coordinateurs)) {
                foreach ($coordinateurs as $index => $coordinateur) {
                    if (!isset($coordinateur->id)) {
                        continue;
                    }

                    $first_name = isset($coordinateur->first_name) ? sanitize_text_field((string) $coordinateur->first_name) : '';
                    $last_name = isset($coordinateur->last_name) ? sanitize_text_field((string) $coordinateur->last_name) : '';
                    $full_name = trim($first_name . ' ' . $last_name);
                    if ($full_name === '') {
                        $full_name = sprintf(__('Coordinateur #%d', 'mj-member'), (int) $coordinateur->id);
                    }

                    $role_key = isset($coordinateur->role) ? sanitize_key((string) $coordinateur->role) : MjRoles::COORDINATEUR;
                    $role_label = isset($role_labels[$role_key]) ? $role_labels[$role_key] : \Mj\Member\Classes\MjRoles::getRoleLabel(\Mj\Member\Classes\MjRoles::COORDINATEUR);

                    $description = '';
                    if (!empty($coordinateur->description_courte)) {
                        $description = sanitize_text_field((string) $coordinateur->description_courte);
                    }
                    if ($description === '') {
                        $description = __('Coordination générale de la MJ.', 'mj-member');
                    }

                    $options[] = mj_member_contact_format_recipient_option(array(
                        'value' => MjContactMessages::TARGET_COORDINATEUR . ':' . (int) $coordinateur->id,
                        'label' => $full_name,
                        'type' => MjContactMessages::TARGET_COORDINATEUR,
                        'reference' => (int) $coordinateur->id,
                        'role' => $role_key,
                        'role_label' => $role_label,
                        'member' => $coordinateur,
                        'email' => isset($coordinateur->email) ? $coordinateur->email : '',
                        'description' => $description,
                        'is_cover' => true,
                        'cover_theme' => ($index % 2 === 0) ? 'indigo' : 'violet',
                    ));
                }
            }

            $animateurs = MjMembers::getAll(0, 0, 'last_name', 'ASC', '', array('role' => MjRoles::ANIMATEUR));
            if (!empty($animateurs) && is_array($animateurs)) {
                foreach ($animateurs as $index => $animateur) {
                    if (!isset($animateur->id)) {
                        continue;
                    }

                    $first_name = isset($animateur->first_name) ? sanitize_text_field((string) $animateur->first_name) : '';
                    $last_name = isset($animateur->last_name) ? sanitize_text_field((string) $animateur->last_name) : '';
                    $full_name = trim($first_name . ' ' . $last_name);
                    if ($full_name === '') {
                        $full_name = sprintf(__('Animateur #%d', 'mj-member'), (int) $animateur->id);
                    }

                    $role_key = isset($animateur->role) ? sanitize_key((string) $animateur->role) : MjRoles::ANIMATEUR;
                    $role_label = isset($role_labels[$role_key]) ? $role_labels[$role_key] : \Mj\Member\Classes\MjRoles::getRoleLabel(\Mj\Member\Classes\MjRoles::ANIMATEUR);

                    $description = '';
                    if (!empty($animateur->description_courte)) {
                        $description = sanitize_text_field((string) $animateur->description_courte);
                    }
                    if ($description === '') {
                        $description = __('Animateur·rice référent·e pour son activité.', 'mj-member');
                    }

                    $options[] = mj_member_contact_format_recipient_option(array(
                        'value' => MjContactMessages::TARGET_ANIMATEUR . ':' . (int) $animateur->id,
                        'label' => $full_name,
                        'type' => MjContactMessages::TARGET_ANIMATEUR,
                        'reference' => (int) $animateur->id,
                        'role' => $role_key,
                        'role_label' => $role_label,
                        'member' => $animateur,
                        'email' => isset($animateur->email) ? $animateur->email : '',
                        'description' => $description,
                        'is_cover' => ($index % 3 === 0),
                        'cover_theme' => ($index % 3 === 0) ? 'cyan' : 'slate',
                    ));
                }
            }

            if ($allow_member_targets) {
                $youth_filters = array('role' => MjRoles::JEUNE);
                if (defined('Mj\\Member\\Classes\\Crud\\MjMembers::STATUS_ACTIVE')) {
                    $youth_filters['status'] = \Mj\Member\Classes\Crud\MjMembers::STATUS_ACTIVE;
                }

                $youth_limit = (int) apply_filters('mj_member_contact_youth_recipient_limit', 200);
                if ($youth_limit < 0) {
                    $youth_limit = 0;
                }

                $jeunes = MjMembers::getAll($youth_limit, 0, 'last_name', 'ASC', '', $youth_filters);
                if (!empty($jeunes) && is_array($jeunes)) {
                    foreach ($jeunes as $index => $jeune) {
                        if (!isset($jeune->id)) {
                            continue;
                        }

                        $first_name = isset($jeune->first_name) ? sanitize_text_field((string) $jeune->first_name) : '';
                        $last_name = isset($jeune->last_name) ? sanitize_text_field((string) $jeune->last_name) : '';
                        $full_name = trim($first_name . ' ' . $last_name);
                        if ($full_name === '') {
                            $full_name = sprintf(__('Membre #%d', 'mj-member'), (int) $jeune->id);
                        }

                        $role_key = isset($jeune->role) ? sanitize_key((string) $jeune->role) : MjRoles::JEUNE;
                        $role_label = isset($role_labels[$role_key]) ? $role_labels[$role_key] : __('Jeune', 'mj-member');

                        $email_value = isset($jeune->email) ? sanitize_email((string) $jeune->email) : '';
                        if ($email_value === '' || !is_email($email_value)) {
                            continue;
                        }

                        $description = '';
                        if (!empty($jeune->description_courte)) {
                            $description = sanitize_text_field((string) $jeune->description_courte);
                        }
                        if ($description === '') {
                            $description = __('Jeune membre de la MJ.', 'mj-member');
                        }

                        $options[] = mj_member_contact_format_recipient_option(array(
                            'value' => $member_target . ':' . (int) $jeune->id,
                            'label' => $full_name,
                            'type' => $member_target,
                            'reference' => (int) $jeune->id,
                            'role' => $role_key,
                            'role_label' => $role_label,
                            'member' => $jeune,
                            'email' => $email_value,
                            'description' => $description,
                            'cover_theme' => ($index % 4 === 0) ? 'emerald' : '',
                        ));
                    }
                }
            }
        }

        $general_option = mj_member_contact_format_recipient_option(array(
            'value' => MjContactMessages::TARGET_ALL,
            'label' => __('Toute l\'équipe MJ', 'mj-member'),
            'type' => MjContactMessages::TARGET_ALL,
            'reference' => 0,
            'role' => 'group',
            'role_label' => __('Maison de Jeune', 'mj-member'),
            'description' => __('Votre message sera transmis à l\'ensemble de l\'équipe.', 'mj-member'),
            'is_cover' => true,
            'cover_theme' => 'indigo',
        ));

        $insert_general = true;
        foreach ($options as $option) {
            if (isset($option['value']) && (string) $option['value'] === MjContactMessages::TARGET_ALL) {
                $insert_general = false;
                break;
            }
        }

        if ($insert_general) {
            array_unshift($options, $general_option);
        } else {
            $options = array_map(function ($option) use ($general_option) {
                if (isset($option['value']) && (string) $option['value'] === MjContactMessages::TARGET_ALL) {
                    return array_merge($option, $general_option);
                }
                return $option;
            }, $options);
        }

        return $options;
    }
}

if (!function_exists('mj_member_parse_contact_recipient_choice')) {
    /**
     * @param string $choice
     * @return array<string,mixed>|WP_Error
     */
    function mj_member_parse_contact_recipient_choice($choice) {
        $choice = sanitize_text_field($choice);
        if ($choice === '' || $choice === MjContactMessages::TARGET_ALL) {
            return array(
                'type' => MjContactMessages::TARGET_ALL,
                'reference' => 0,
                'label' => __('Toute l\'équipe', 'mj-member'),
            );
        }

        $member_target = mj_member_contact_member_target_key();

        if ($choice === MjContactMessages::TARGET_COORDINATEUR) {
            return array(
                'type' => MjContactMessages::TARGET_COORDINATEUR,
                'reference' => 0,
                'label' => \Mj\Member\Classes\MjRoles::getRoleLabel(\Mj\Member\Classes\MjRoles::COORDINATEUR),
            );
        }

        if (strpos($choice, MjContactMessages::TARGET_COORDINATEUR . ':') === 0) {
            $parts = explode(':', $choice);
            $member_id = isset($parts[1]) ? (int) $parts[1] : 0;
            if ($member_id <= 0) {
                return new WP_Error('mj_contact_invalid_recipient', __('Destinataire introuvable.', 'mj-member'));
            }

            $label = sprintf(__('Coordinateur #%d', 'mj-member'), $member_id);
            if (class_exists('MjMembers')) {
                $row = MjMembers::getById($member_id);
                if ($row && isset($row->first_name, $row->last_name)) {
                    $candidate = trim($row->first_name . ' ' . $row->last_name);
                    if ($candidate !== '') {
                        $label = $candidate;
                    }
                }
            }

            return array(
                'type' => MjContactMessages::TARGET_COORDINATEUR,
                'reference' => $member_id,
                'label' => $label,
            );
        }

        if ($choice === MjContactMessages::TARGET_ANIMATEUR) {
            return array(
                'type' => MjContactMessages::TARGET_ANIMATEUR,
                'reference' => 0,
                'label' => __('Les animateurs / coordinateurs', 'mj-member'),
            );
        }

        if (strpos($choice, MjContactMessages::TARGET_ANIMATEUR . ':') === 0) {
            $parts = explode(':', $choice);
            $member_id = isset($parts[1]) ? (int) $parts[1] : 0;
            if ($member_id <= 0) {
                return new WP_Error('mj_contact_invalid_recipient', __('Destinataire introuvable.', 'mj-member'));
            }

            $label = sprintf(__('Animateur #%d', 'mj-member'), $member_id);
            if (class_exists('MjMembers')) {
                $row = MjMembers::getById($member_id);
                if ($row && isset($row->first_name, $row->last_name)) {
                    $candidate = trim($row->first_name . ' ' . $row->last_name);
                    if ($candidate !== '') {
                        $label = $candidate;
                    }
                }
            }

            return array(
                'type' => MjContactMessages::TARGET_ANIMATEUR,
                'reference' => $member_id,
                'label' => $label,
            );
        }

        if ($choice === $member_target) {
            return array(
                'type' => $member_target,
                'reference' => 0,
                'label' => __('Jeunes', 'mj-member'),
            );
        }

        if (strpos($choice, $member_target . ':') === 0) {
            $parts = explode(':', $choice);
            $member_id = isset($parts[1]) ? (int) $parts[1] : 0;
            if ($member_id <= 0) {
                return new WP_Error('mj_contact_invalid_recipient', __('Destinataire introuvable.', 'mj-member'));
            }

            $label = sprintf(__('Jeune #%d', 'mj-member'), $member_id);
            if (class_exists('MjMembers')) {
                $row = MjMembers::getById($member_id);
                if ($row && isset($row->first_name, $row->last_name)) {
                    $candidate = trim($row->first_name . ' ' . $row->last_name);
                    if ($candidate !== '') {
                        $label = $candidate;
                    }
                }
            }

            return array(
                'type' => $member_target,
                'reference' => $member_id,
                'label' => $label,
            );
        }

        return new WP_Error('mj_contact_invalid_recipient', __('Destinataire introuvable.', 'mj-member'));
    }
}

if (!function_exists('mj_member_handle_contact_message_submission')) {
    function mj_member_handle_contact_message_submission() {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'mj-member-contact-message')) {
            wp_send_json_error(array('message' => __('La session a expiré. Merci de recharger la page.', 'mj-member')), 400);
        }

        $sender_name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $sender_email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $subject = isset($_POST['subject']) ? sanitize_text_field(wp_unslash($_POST['subject'])) : '';
        $message = isset($_POST['message']) ? wp_kses_post(wp_unslash($_POST['message'])) : '';
        $recipient_raw = isset($_POST['recipient']) ? wp_unslash($_POST['recipient']) : '';
        $recipient_raw = is_string($recipient_raw) ? trim($recipient_raw) : '';
        $recipient_sanitized = sanitize_text_field($recipient_raw);
        $choice_list = array();

        if ($recipient_raw !== '') {
            $decoded_choices = json_decode($recipient_raw, true);
            if (is_array($decoded_choices)) {
                foreach ($decoded_choices as $decoded_choice) {
                    $candidate = sanitize_text_field((string) $decoded_choice);
                    if ($candidate !== '') {
                        $choice_list[] = $candidate;
                    }
                }
            }
        }

        if (empty($choice_list) && $recipient_sanitized !== '') {
            $choice_list[] = $recipient_sanitized;
        }

        if (!empty($choice_list)) {
            $choice_list = array_values(array_unique($choice_list));
        }
        $posted_member_id = isset($_POST['member_id']) ? (int) wp_unslash($_POST['member_id']) : 0;
        $parent_message_id = isset($_POST['parent_message_id']) ? (int) wp_unslash($_POST['parent_message_id']) : 0;
        if ($parent_message_id <= 0 && isset($_POST['parent_id'])) {
            $parent_message_id = (int) wp_unslash($_POST['parent_id']);
        }
        $parent_message = null;

        $member_id = 0;
        $user_id = get_current_user_id();

        if ($user_id > 0) {
            $wp_user = wp_get_current_user();
            $current_member = function_exists('mj_member_get_current_member') ? mj_member_get_current_member() : null;

            if ($current_member && isset($current_member->id)) {
                $member_id = (int) $current_member->id;
            }

            $locked_name = '';
            if ($current_member) {
                $first = isset($current_member->first_name) ? trim((string) $current_member->first_name) : '';
                $last = isset($current_member->last_name) ? trim((string) $current_member->last_name) : '';
                $locked_name = trim($first . ' ' . $last);
                if ($locked_name === '' && !empty($current_member->nickname)) {
                    $locked_name = (string) $current_member->nickname;
                }
            }

            if ($locked_name === '' && $wp_user) {
                $name_parts = array();
                if (!empty($wp_user->first_name)) {
                    $name_parts[] = (string) $wp_user->first_name;
                }
                if (!empty($wp_user->last_name)) {
                    $name_parts[] = (string) $wp_user->last_name;
                }
                $locked_name = trim(implode(' ', $name_parts));
                if ($locked_name === '') {
                    $locked_name = $wp_user->display_name !== '' ? (string) $wp_user->display_name : (string) $wp_user->user_login;
                }
            }

            if ($locked_name !== '') {
                $sender_name = sanitize_text_field($locked_name);
            }

            $locked_email = '';
            if ($current_member && !empty($current_member->email)) {
                $locked_email = (string) $current_member->email;
            } elseif ($wp_user && !empty($wp_user->user_email)) {
                $locked_email = (string) $wp_user->user_email;
            }

            if ($locked_email !== '') {
                $sender_email = sanitize_email($locked_email);
            }
        } elseif ($posted_member_id > 0 && class_exists('MjMembers') && $sender_email !== '') {
            $possible_member = MjMembers::getById($posted_member_id);
            if ($possible_member && !empty($possible_member->email) && strcasecmp((string) $possible_member->email, $sender_email) === 0) {
                $member_id = (int) $possible_member->id;
            }
        }

        if ($sender_name === '' || $sender_email === '' || !is_email($sender_email) || $message === '') {
            wp_send_json_error(array('message' => __('Veuillez compléter tous les champs obligatoires.', 'mj-member')), 400);
        }

        if ($parent_message_id > 0) {
            $parent_message = MjContactMessages::get($parent_message_id);
            if (!$parent_message) {
                wp_send_json_error(array('message' => __('Ce message n’est plus disponible.', 'mj-member')), 404);
            }

            $ownership_confirmed = false;
            if (!empty($parent_message->sender_email) && strcasecmp((string) $parent_message->sender_email, $sender_email) === 0) {
                $ownership_confirmed = true;
            }

            if (!$ownership_confirmed && $member_id > 0) {
                $parent_meta_raw = isset($parent_message->meta) ? $parent_message->meta : '';
                if ($parent_meta_raw !== '') {
                    $parent_meta = json_decode($parent_meta_raw, true);
                    if (is_array($parent_meta) && isset($parent_meta['member_id']) && (int) $parent_meta['member_id'] === $member_id) {
                        $ownership_confirmed = true;
                    }
                }
            }

            if (!$ownership_confirmed) {
                wp_send_json_error(array('message' => __('Vous ne pouvez pas répondre à cette conversation.', 'mj-member')), 403);
            }
        }

        $recipient_specs = array();

        if ($parent_message instanceof stdClass && isset($parent_message->target_type)) {
            $target_type = sanitize_key((string) $parent_message->target_type);
            if ($target_type === '') {
                $target_type = MjContactMessages::TARGET_ALL;
            }

            $recipient_specs[] = array(
                'type' => $target_type,
                'reference' => isset($parent_message->target_reference) ? (int) $parent_message->target_reference : 0,
                'label' => isset($parent_message->target_label) ? sanitize_text_field((string) $parent_message->target_label) : '',
            );
        } else {
            if (empty($choice_list)) {
                wp_send_json_error(array('message' => __('Veuillez sélectionner au moins un destinataire.', 'mj-member')), 400);
            }

            if (in_array(MjContactMessages::TARGET_ALL, $choice_list, true) && count($choice_list) > 1) {
                $choice_list = array(MjContactMessages::TARGET_ALL);
            }

            foreach ($choice_list as $choice_item) {
                $recipient = mj_member_parse_contact_recipient_choice($choice_item);
                if (is_wp_error($recipient)) {
                    wp_send_json_error(array('message' => $recipient->get_error_message()), 400);
                }
                $recipient_specs[] = $recipient;
            }
        }

        if (!empty($recipient_specs)) {
            $normalized_recipients = array();
            $recipient_seen = array();

            foreach ($recipient_specs as $recipient_entry) {
                if (!is_array($recipient_entry)) {
                    continue;
                }

                $type = isset($recipient_entry['type']) ? sanitize_key((string) $recipient_entry['type']) : '';
                if ($type === '') {
                    continue;
                }

                $reference = isset($recipient_entry['reference']) ? (int) $recipient_entry['reference'] : 0;
                if ($reference < 0) {
                    $reference = 0;
                }

                $label = isset($recipient_entry['label']) ? sanitize_text_field((string) $recipient_entry['label']) : '';

                $seen_key = $type . '|' . $reference;
                if (isset($recipient_seen[$seen_key])) {
                    continue;
                }
                $recipient_seen[$seen_key] = true;

                $normalized_recipients[] = array(
                    'type' => $type,
                    'reference' => $reference,
                    'label' => $label,
                );
            }

            $recipient_specs = $normalized_recipients;
        }

        $source_url = isset($_POST['source']) ? esc_url_raw(wp_unslash($_POST['source'])) : wp_get_referer();
        $meta = array(
            'ip' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field((string) $_SERVER['REMOTE_ADDR']) : '',
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field((string) $_SERVER['HTTP_USER_AGENT']) : '',
        );

        if ($member_id > 0) {
            $meta['member_id'] = (string) $member_id;
        }

        if ($parent_message_id > 0) {
            $meta['parent_message_id'] = (string) $parent_message_id;
            $meta['conversation_reply'] = '1';
        }

        $response_message = __('Merci ! Votre message a été envoyé et sera traité rapidement.', 'mj-member');
        $activity_payload = null;

        if ($parent_message_id > 0) {
            $activity_note = $sender_name !== ''
                ? sprintf(__('Réponse envoyée par %s.', 'mj-member'), $sender_name)
                : __('Réponse envoyée.', 'mj-member');

            $recorded = MjContactMessages::record_activity($parent_message_id, 'reply_owner', array(
                'note' => $activity_note,
                'user_id' => $user_id,
                'meta' => array(
                    'subject' => $subject,
                    'body' => $message,
                    'author_name' => $sender_name,
                    'author_email' => $sender_email,
                ),
            ));

            if (!$recorded) {
                wp_send_json_error(array('message' => __('Impossible d’enregistrer la réponse.', 'mj-member')), 500);
            }

            $status_update = MjContactMessages::update($parent_message_id, array(
                'status' => MjContactMessages::STATUS_IN_PROGRESS,
                'is_read' => 0,
            ));

            if (is_wp_error($status_update) || !$status_update) {
                wp_send_json_error(array('message' => __('Impossible de mettre à jour le message.', 'mj-member')), 500);
            }

            $updated_parent = MjContactMessages::get($parent_message_id);
            $status_key = MjContactMessages::STATUS_IN_PROGRESS;
            $status_labels = MjContactMessages::get_status_labels();
            $status_label = isset($status_labels[$status_key]) ? $status_labels[$status_key] : $status_key;

            $activity_time = current_time('timestamp');
            $response_message = __('Votre réponse a bien été envoyée.', 'mj-member');
            $activity_payload = array(
                'action' => 'reply_owner',
                'note' => $activity_note,
                'time_human' => date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $activity_time),
                'meta' => array(
                    'body' => $message,
                    'author_name' => $sender_name,
                ),
            );

            $payload = array(
                'message' => $response_message,
                'activity' => $activity_payload,
                'status_key' => $status_key,
                'status_label' => $status_label,
                'is_read' => 0,
            );

            wp_send_json_success($payload);
        }

        $recipient_total = count($recipient_specs);
        if ($recipient_total === 0) {
            wp_send_json_error(array('message' => __('Veuillez sélectionner au moins un destinataire.', 'mj-member')), 400);
        }

        $primary_recipient = $recipient_specs[0];
        $primary_type = isset($primary_recipient['type']) ? sanitize_key((string) $primary_recipient['type']) : MjContactMessages::TARGET_ALL;
        if ($primary_type === '') {
            $primary_type = MjContactMessages::TARGET_ALL;
        }

        $primary_reference = isset($primary_recipient['reference']) ? (int) $primary_recipient['reference'] : 0;
        if ($primary_reference < 0) {
            $primary_reference = 0;
        }

        $primary_label = isset($primary_recipient['label']) ? sanitize_text_field((string) $primary_recipient['label']) : '';

        $recipient_keys = array();
        $recipient_types = array();
        foreach ($recipient_specs as $recipient_entry) {
            if (!is_array($recipient_entry)) {
                continue;
            }

            $type = isset($recipient_entry['type']) ? sanitize_key((string) $recipient_entry['type']) : '';
            if ($type === '') {
                continue;
            }

            $reference = isset($recipient_entry['reference']) ? (int) $recipient_entry['reference'] : 0;
            if ($reference < 0) {
                $reference = 0;
            }

            $meta_key = $reference > 0 ? $type . ':' . $reference : $type;

            if (!in_array($meta_key, $recipient_keys, true)) {
                $recipient_keys[] = $meta_key;
            }

            if (!in_array($type, $recipient_types, true)) {
                $recipient_types[] = $type;
            }
        }

        if (!empty($recipient_keys)) {
            $meta['recipient_keys'] = implode('|', $recipient_keys);
        }

        if (!empty($recipient_types)) {
            $meta['recipient_types'] = implode('|', $recipient_types);
        }

        $meta['recipient_total'] = (string) $recipient_total;

        $created_message_id = MjContactMessages::create(array(
            'sender_name' => $sender_name,
            'sender_email' => $sender_email,
            'subject' => $subject,
            'message' => $message,
            'target_type' => $primary_type,
            'target_reference' => $primary_reference,
            'target_label' => $primary_label,
            'source_url' => $source_url,
            'meta' => $meta,
            'user_id' => $user_id,
        ));

        if (is_wp_error($created_message_id)) {
            wp_send_json_error(array('message' => $created_message_id->get_error_message()), 500);
        }

        if ($recipient_total > 1) {
            $response_message = sprintf(
                _n(
                    'Merci ! Votre message a été envoyé à %d destinataire.',
                    'Merci ! Votre message a été envoyé à %d destinataires.',
                    $recipient_total,
                    'mj-member'
                ),
                $recipient_total
            );
        }

        $payload = array('message' => $response_message);

        wp_send_json_success($payload);
    }

    add_action('wp_ajax_mj_member_submit_contact_message', 'mj_member_handle_contact_message_submission');
    add_action('wp_ajax_nopriv_mj_member_submit_contact_message', 'mj_member_handle_contact_message_submission');
}
