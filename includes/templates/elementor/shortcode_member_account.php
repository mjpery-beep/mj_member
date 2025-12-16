<?php

use Mj\Member\Core\AssetsManager;

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('mj_member_get_account_redirect')) {
    /**
     * Détermine l'URL de redirection par défaut vers l'espace membre.
     *
     * @param array<string,mixed> $atts
     * @return string
     */
    function mj_member_get_account_redirect($atts = array()) {
        $atts = wp_parse_args(is_array($atts) ? $atts : array(), array(
            'redirect' => '',
        ));

        $candidates = array();

        if (!empty($atts['redirect'])) {
            $candidates[] = $atts['redirect'];
        }

        if (isset($_REQUEST['redirect_to'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $candidates[] = wp_unslash((string) $_REQUEST['redirect_to']);
        }

        if (isset($_REQUEST['redirect'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $candidates[] = wp_unslash((string) $_REQUEST['redirect']);
        }

        $account_links_settings = get_option('mj_account_links_settings', array());
        if (is_array($account_links_settings) && isset($account_links_settings['profile'])) {
            $profile_link = $account_links_settings['profile'];
            if (!empty($profile_link['page_id'])) {
                $page_id = (int) $profile_link['page_id'];
                $profile_permalink = get_permalink($page_id);
                if ($profile_permalink) {
                    $candidates[] = $profile_permalink;
                }
            }
            if (empty($profile_permalink) && !empty($profile_link['slug'])) {
                $slug = ltrim((string) $profile_link['slug'], '/');
                if ($slug !== '') {
                    $candidates[] = home_url('/' + $slug);
                }
            }
        }

        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate === '') {
                continue;
            }

            $validated = wp_validate_redirect($candidate, '');
            if ($validated !== '') {
                return esc_url_raw($validated);
            }
        }

        $page_id = (int) get_option('mj_member_account_page_id', 0);
        if ($page_id > 0) {
            $permalink = get_permalink($page_id);
            if ($permalink) {
                return esc_url_raw($permalink);
            }
        }

        $account_page = get_page_by_path('mon-compte');
        if ($account_page) {
            $permalink = get_permalink($account_page);
            if ($permalink) {
                return esc_url_raw($permalink);
            }
        }

        $default = home_url('/mon-compte');

        return esc_url_raw(apply_filters('mj_member_account_redirect_url', $default, $atts));
    }
}

if (!function_exists('mj_member_account_fake_context')) {
    /**
     * Génère un jeu de données de démonstration pour l'aperçu Elementor.
     *
     * @param array<string,mixed> $options
     * @return array{
     *     0: object,
     *     1: array<string,mixed>,
     *     2: array<int,array<string,mixed>>,
     *     3: array<int,array<string,string>>
     * }
     */
    function mj_member_account_fake_context($options = array()) {
        $now = current_time('timestamp');

        $member = (object) array(
            'id' => 0,
            'first_name' => 'Camille',
            'last_name' => 'Dupont',
            'email' => 'camille.dupont@example.com',
            'phone' => '0470 12 34 56',
            'address' => "12 rue des Artistes",
            'city' => 'Péry',
            'postal_code' => '2600',
            'birth_date' => '2005-03-21',
            'notes' => 'Allergie aux fruits à coque.',
            'photo_id' => 0,
            'photo_usage_consent' => 1,
            'newsletter_opt_in' => 1,
            'sms_opt_in' => 0,
            'requires_payment' => 1,
            'date_last_payement' => gmdate('Y-m-d H:i:s', strtotime('-9 months', $now)),
        );

        $status = function_exists('mj_member_get_membership_status')
            ? mj_member_get_membership_status($member, array('now' => $now))
            : array(
                'status' => 'active',
                'status_label' => __('Cotisation à jour', 'mj-member'),
                'description' => __('Vos informations sont à jour.', 'mj-member'),
                'last_payment_display' => date_i18n(get_option('date_format', 'd/m/Y'), strtotime('-9 months', $now)),
                'expires_display' => date_i18n(get_option('date_format', 'd/m/Y'), strtotime('+3 months', $now)),
                'amount' => 15.0,
                'requires_payment' => false,
            );

        $children = array(
            array(
                'id' => 101,
                'full_name' => 'Lou Dupont',
                'status' => 'active',
                'status_label' => __('Cotisation à jour', 'mj-member'),
                'description' => __('Valide jusqu’au 12/2024.', 'mj-member'),
                'last_payment_display' => date_i18n(get_option('date_format', 'd/m/Y'), strtotime('-4 months', $now)),
                'expires_display' => date_i18n(get_option('date_format', 'd/m/Y'), strtotime('+8 months', $now)),
                'amount' => 12.0,
                'requires_payment' => false,
                'profile' => array(
                    'id' => 101,
                    'first_name' => 'Lou',
                    'last_name' => 'Dupont',
                    'email' => 'lou.dupont@example.com',
                    'phone' => '',
                    'birth_date' => '2011-06-04',
                    'notes' => '',
                    'is_autonomous' => 0,
                    'photo_usage_consent' => 1,
                ),
            ),
            array(
                'id' => 102,
                'full_name' => 'Nino Dupont',
                'status' => 'missing',
                'status_label' => __('Cotisation à régler', 'mj-member'),
                'description' => __('Aucune cotisation enregistrée pour la saison en cours.', 'mj-member'),
                'last_payment_display' => '',
                'expires_display' => '',
                'amount' => 12.0,
                'requires_payment' => true,
                'profile' => array(
                    'id' => 102,
                    'first_name' => 'Nino',
                    'last_name' => 'Dupont',
                    'email' => '',
                    'phone' => '',
                    'birth_date' => '2013-11-18',
                    'notes' => '',
                    'is_autonomous' => 0,
                    'photo_usage_consent' => 0,
                ),
            ),
        );

        $payments = array(
            array(
                'date' => date_i18n(get_option('date_format', 'd/m/Y'), strtotime('-10 months', $now)),
                'amount' => '25,00',
                'status_label' => __('Cotisation annuelle', 'mj-member'),
                'reference' => 'MJ-2023-104',
            ),
            array(
                'date' => date_i18n(get_option('date_format', 'd/m/Y'), strtotime('-18 months', $now)),
                'amount' => '25,00',
                'status_label' => __('Cotisation annuelle', 'mj-member'),
                'reference' => 'MJ-2022-078',
            ),
        );

        return array($member, $status, $children, $payments);
    }
}

if (!function_exists('mj_member_account_get_photo_preview')) {
    /**
     * Retourne l’aperçu de la photo de profil d’un membre.
     *
     * @param object|null $member
     * @return array{url:string,id:int}
     */
    function mj_member_account_get_photo_preview($member) {
        $photo_id = 0;
        if ($member && is_object($member) && isset($member->photo_id)) {
            $photo_id = (int) $member->photo_id;
        }

        $url = '';
        if ($photo_id > 0) {
            $image = wp_get_attachment_image_src($photo_id, 'thumbnail');
            if ($image) {
                $url = (string) $image[0];
            }
        }

        if ($url === '' && function_exists('mj_member_login_component_get_member_avatar')) {
            $avatar = mj_member_login_component_get_member_avatar(null, $member);
            if (!empty($avatar['url'])) {
                $url = (string) $avatar['url'];
                if ($photo_id <= 0 && !empty($avatar['id'])) {
                    $photo_id = (int) $avatar['id'];
                }
            }
        }

        if ($url === '' && $photo_id > 0) {
            $fallback = wp_get_attachment_url($photo_id);
            if ($fallback) {
                $url = $fallback;
            }
        }

        if ($url === '' && $member && is_object($member)) {
            $member_email = isset($member->email) ? sanitize_email((string) $member->email) : '';
            if ($member_email !== '') {
                $url = get_avatar_url($member_email, array('size' => 96));
            }
        }

        return array(
            'url' => $url !== '' ? esc_url($url) : '',
            'id' => $photo_id,
        );
    }
}

if (!function_exists('mj_member_account_normalize_birth_date')) {
    /**
     * Normalise une date de naissance au format AAAA-MM-JJ.
     *
     * @param string $raw
     * @return string
     */
    function mj_member_account_normalize_birth_date($raw) {
        $raw = trim((string) $raw);
        if ($raw === '' || $raw === '0000-00-00') {
            return '';
        }

        $timestamp = strtotime($raw);
        if ($timestamp) {
            return gmdate('Y-m-d', $timestamp);
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return $raw;
        }

        return '';
    }
}

if (!function_exists('mj_member_render_account_component')) {
    /**
     * Rend le composant Elementor / shortcode de l’espace membre.
     *
     * @param array<string,mixed> $args
     * @return string
     */
    function mj_member_render_account_component($args = array()) {
        if (class_exists(AssetsManager::class)) {
            AssetsManager::requirePackage('member-account');
        } else {
            wp_enqueue_style('mj-member-components');
        }

        $defaults = array(
            'redirect' => '',
            'title' => __('Mes informations', 'mj-member'),
            'description' => '',
            'submit_label' => __('Enregistrer', 'mj-member'),
            'success_message' => __('Vos informations ont été mises à jour.', 'mj-member'),
            'show_children' => true,
            'show_payments' => true,
            'payment_limit' => 10,
            'show_membership' => true,
            'form_id' => '',
            'context' => '',
        );
        $options = wp_parse_args($args, $defaults);

        $display_membership = !empty($options['show_membership']);

        $is_elementor_context = isset($options['context']) && $options['context'] === 'elementor';
        $is_preview = function_exists('mj_member_login_component_is_preview_mode')
            ? mj_member_login_component_is_preview_mode()
            : false;
        if ($is_elementor_context) {
            $is_preview = $is_preview || (did_action('elementor/loaded') && !is_user_logged_in());
        } elseif (is_user_logged_in()) {
            $is_preview = false;
        }

        $current_url = function_exists('mj_member_get_current_url')
            ? mj_member_get_current_url()
            : home_url('/');
        if (!function_exists('mj_member_get_current_url') && isset($_SERVER['REQUEST_URI'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $current_url = home_url(sanitize_text_field(wp_unslash((string) $_SERVER['REQUEST_URI'])));
        }

        $redirect_url = mj_member_get_account_redirect(array(
            'redirect' => $options['redirect'],
        ));

        if (!$is_preview && !is_user_logged_in()) {
            ob_start();
            ?>
            <div class="mj-account-modern">
                <p class="mj-member-login-status">
                    <?php esc_html_e('Vous devez être connecté pour accéder à votre espace membre.', 'mj-member'); ?>
                    <a class="mj-account-link" href="<?php echo esc_url(wp_login_url($redirect_url)); ?>">
                        <?php esc_html_e('Se connecter', 'mj-member'); ?>
                    </a>
                </p>
            </div>
            <?php
            return ob_get_clean();
        }

        $member = null;
        $membership_status = array();
        $children_statuses = array();
        $payment_history = array();

        if ($is_preview) {
            list($member, $membership_status, $children_statuses, $payment_history) = mj_member_account_fake_context($options);
        } else {
            if (!function_exists('mj_member_get_current_member')) {
                ob_start();
                ?>
                <div class="mj-account-modern">
                    <p class="mj-member-login-status">
                        <?php esc_html_e('Le module MJ Member n’est pas disponible pour charger votre profil.', 'mj-member'); ?>
                    </p>
                </div>
                <?php
                return ob_get_clean();
            }

            $member = mj_member_get_current_member();
            if (!$member || !is_object($member)) {
                ob_start();
                ?>
                <div class="mj-account-modern">
                    <p class="mj-member-login-status">
                        <?php esc_html_e('Votre profil MJ est introuvable. Merci de contacter l’équipe MJ.', 'mj-member'); ?>
                    </p>
                </div>
                <?php
                return ob_get_clean();
            }

            if ($display_membership && function_exists('mj_member_get_membership_status')) {
                $membership_status = mj_member_get_membership_status($member);
            }

            if (!empty($options['show_children']) && function_exists('mj_member_get_guardian_children_statuses')) {
                $children_statuses = mj_member_get_guardian_children_statuses($member);
            }

            if (!empty($options['show_payments']) && function_exists('mj_member_get_payment_timeline')) {
                $payment_history = mj_member_get_payment_timeline((int) $member->id, max(1, (int) $options['payment_limit']));
            }
        }

        $form_values = array(
            'first_name' => isset($member->first_name) ? sanitize_text_field((string) $member->first_name) : '',
            'last_name' => isset($member->last_name) ? sanitize_text_field((string) $member->last_name) : '',
            'email' => isset($member->email) ? sanitize_email((string) $member->email) : '',
            'phone' => isset($member->phone) ? sanitize_text_field((string) $member->phone) : '',
            'address' => isset($member->address) ? sanitize_textarea_field((string) $member->address) : '',
            'postal_code' => isset($member->postal_code) ? sanitize_text_field((string) $member->postal_code) : '',
            'city' => isset($member->city) ? sanitize_text_field((string) $member->city) : '',
            'birth_date' => mj_member_account_normalize_birth_date(isset($member->birth_date) ? (string) $member->birth_date : ''),
            'notes' => isset($member->notes) ? sanitize_textarea_field((string) $member->notes) : '',
            'photo_usage_consent' => !empty($member->photo_usage_consent) ? 1 : 0,
            'newsletter_opt_in' => !empty($member->newsletter_opt_in) ? 1 : 0,
            'sms_opt_in' => !empty($member->sms_opt_in) ? 1 : 0,
            'photo_remove' => 0,
        );

        $photo_preview = mj_member_account_get_photo_preview($member);

        $messages = array();
        $errors = array();
        $success = false;

        if (!$is_preview && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mj_member_account_action']) && $_POST['mj_member_account_action'] === 'update_profile') {
            $nonce = isset($_POST['mj_member_account_nonce']) ? sanitize_text_field(wp_unslash((string) $_POST['mj_member_account_nonce'])) : '';
            if (!wp_verify_nonce($nonce, 'mj_member_account_update')) {
                $errors[] = __('La vérification de sécurité a échoué. Merci de réessayer.', 'mj-member');
            } else {
                $posted = isset($_POST['member']) && is_array($_POST['member']) ? wp_unslash($_POST['member']) : array();

                $first_name = isset($posted['first_name']) ? sanitize_text_field((string) $posted['first_name']) : '';
                $last_name = isset($posted['last_name']) ? sanitize_text_field((string) $posted['last_name']) : '';
                $email = isset($posted['email']) ? sanitize_email((string) $posted['email']) : '';
                $phone = isset($posted['phone']) ? sanitize_text_field((string) $posted['phone']) : '';
                $address = isset($posted['address']) ? sanitize_textarea_field((string) $posted['address']) : '';
                $postal_code = isset($posted['postal_code']) ? sanitize_text_field((string) $posted['postal_code']) : '';
                $city = isset($posted['city']) ? sanitize_text_field((string) $posted['city']) : '';
                $birth_date = isset($posted['birth_date']) ? mj_member_account_normalize_birth_date((string) $posted['birth_date']) : '';
                $notes = isset($posted['notes']) ? sanitize_textarea_field((string) $posted['notes']) : '';
                $photo_usage_consent = !empty($posted['photo_usage_consent']) ? 1 : 0;
                $newsletter_opt_in = !empty($posted['newsletter_opt_in']) ? 1 : 0;
                $sms_opt_in = !empty($posted['sms_opt_in']) ? 1 : 0;
                $photo_remove = !empty($posted['photo_remove']);
                $existing_photo_id = isset($posted['photo_id_existing']) ? (int) $posted['photo_id_existing'] : 0;

                if ($first_name === '') {
                    $errors[] = __('Merci de renseigner votre prénom.', 'mj-member');
                }

                if ($last_name === '') {
                    $errors[] = __('Merci de renseigner votre nom.', 'mj-member');
                }

                if ($email === '' || !is_email($email)) {
                    $errors[] = __('Merci de fournir une adresse email valide.', 'mj-member');
                } else {
                    $current_user_id = get_current_user_id();
                    if ($current_user_id > 0) {
                        $existing_user = get_user_by('email', $email);
                        if ($existing_user && (int) $existing_user->ID !== (int) $current_user_id) {
                            $errors[] = __('Cette adresse email est déjà utilisée par un autre compte.', 'mj-member');
                        }
                    }

                    if (empty($errors) && class_exists('MjMembers') && method_exists('MjMembers', 'getByEmail')) {
                        $other_member = MjMembers::getByEmail($email);
                        if ($other_member && isset($member->id) && (int) $other_member->id !== (int) $member->id) {
                            $errors[] = __('Cette adresse email est déjà utilisée par un autre membre MJ.', 'mj-member');
                        }
                    }
                }

                $new_photo_id = null;
                if (!$photo_remove && !empty($_FILES['member_photo']) && !empty($_FILES['member_photo']['name'])) {
                    require_once ABSPATH . 'wp-admin/includes/file.php';
                    require_once ABSPATH . 'wp-admin/includes/media.php';
                    require_once ABSPATH . 'wp-admin/includes/image.php';

                    add_filter('user_has_cap', 'mj_member_temp_allow_upload_cap', 10, 3);
                    $upload_id = media_handle_upload('member_photo', 0, array(), array('test_form' => false));
                    remove_filter('user_has_cap', 'mj_member_temp_allow_upload_cap', 10);

                    if (is_wp_error($upload_id)) {
                        $errors[] = $upload_id->get_error_message();
                    } else {
                        $new_photo_id = (int) $upload_id;
                    }
                }

                if (empty($errors)) {
                    if (!class_exists('MjMembers') || !method_exists('MjMembers', 'update')) {
                        $errors[] = __('La mise à jour du profil est indisponible.', 'mj-member');
                    } else {
                        $payload = array(
                            'first_name' => $first_name,
                            'last_name' => $last_name,
                            'email' => $email,
                            'phone' => $phone,
                            'address' => $address,
                            'postal_code' => $postal_code,
                            'city' => $city,
                            'notes' => $notes,
                            'photo_usage_consent' => $photo_usage_consent,
                            'newsletter_opt_in' => $newsletter_opt_in,
                            'sms_opt_in' => $sms_opt_in,
                        );

                        if ($birth_date !== '') {
                            $payload['birth_date'] = $birth_date;
                        }

                        if ($photo_remove) {
                            $payload['photo_id'] = null;
                        } elseif ($new_photo_id !== null) {
                            $payload['photo_id'] = $new_photo_id;
                        } elseif ($existing_photo_id > 0) {
                            $payload['photo_id'] = $existing_photo_id;
                        }

                        $update_result = MjMembers::update((int) $member->id, $payload);
                        if (is_wp_error($update_result)) {
                            $errors[] = $update_result->get_error_message();
                        } else {
                            $sync_result = mj_member_sync_member_user_account($member->id, array(
                                'return_error' => true,
                                'send_notification' => false,
                            ));

                            if (is_wp_error($sync_result)) {
                                $errors[] = $sync_result->get_error_message();
                            }
                        }
                    }
                }

                if (empty($errors) && class_exists('MjMembers') && method_exists('MjMembers', 'getById')) {
                    $member = MjMembers::getById((int) $member->id);
                    if ($member && is_object($member)) {
                        $form_values = array(
                            'first_name' => isset($member->first_name) ? sanitize_text_field((string) $member->first_name) : '',
                            'last_name' => isset($member->last_name) ? sanitize_text_field((string) $member->last_name) : '',
                            'email' => isset($member->email) ? sanitize_email((string) $member->email) : '',
                            'phone' => isset($member->phone) ? sanitize_text_field((string) $member->phone) : '',
                            'address' => isset($member->address) ? sanitize_textarea_field((string) $member->address) : '',
                            'postal_code' => isset($member->postal_code) ? sanitize_text_field((string) $member->postal_code) : '',
                            'city' => isset($member->city) ? sanitize_text_field((string) $member->city) : '',
                            'birth_date' => mj_member_account_normalize_birth_date(isset($member->birth_date) ? (string) $member->birth_date : ''),
                            'notes' => isset($member->notes) ? sanitize_textarea_field((string) $member->notes) : '',
                            'photo_usage_consent' => !empty($member->photo_usage_consent) ? 1 : 0,
                            'newsletter_opt_in' => !empty($member->newsletter_opt_in) ? 1 : 0,
                            'sms_opt_in' => !empty($member->sms_opt_in) ? 1 : 0,
                            'photo_remove' => 0,
                        );

                        $photo_preview = mj_member_account_get_photo_preview($member);

                        if (function_exists('mj_member_get_membership_status')) {
                            $membership_status = mj_member_get_membership_status($member);
                        }

                        if (!empty($options['show_children']) && function_exists('mj_member_get_guardian_children_statuses')) {
                            $children_statuses = mj_member_get_guardian_children_statuses($member);
                        }

                        if (!empty($options['show_payments']) && function_exists('mj_member_get_payment_timeline')) {
                            $payment_history = mj_member_get_payment_timeline((int) $member->id, max(1, (int) $options['payment_limit']));
                        }
                    }

                    $success = true;
                    if (!empty($options['success_message'])) {
                        $messages[] = sanitize_text_field((string) $options['success_message']);
                    }
                }

                if (!$success) {
                    $form_values = array_merge($form_values, array(
                        'first_name' => $first_name,
                        'last_name' => $last_name,
                        'email' => $email,
                        'phone' => $phone,
                        'address' => $address,
                        'postal_code' => $postal_code,
                        'city' => $city,
                        'birth_date' => $birth_date,
                        'notes' => $notes,
                        'photo_usage_consent' => $photo_usage_consent,
                        'newsletter_opt_in' => $newsletter_opt_in,
                        'sms_opt_in' => $sms_opt_in,
                        'photo_remove' => $photo_remove ? 1 : 0,
                    ));

                    if ($photo_remove) {
                        $photo_preview = array('url' => '', 'id' => 0);
                    } elseif ($new_photo_id !== null) {
                        $photo_preview = mj_member_account_get_photo_preview((object) array('photo_id' => $new_photo_id, 'email' => $email));
                    }
                }
            }
        }

        $form_id = $options['form_id'] !== ''
            ? sanitize_html_class((string) $options['form_id'])
            : wp_unique_id('mj-member-account-form-');

        $title = isset($options['title']) ? sanitize_text_field((string) $options['title']) : '';
        $description = isset($options['description']) ? $options['description'] : '';
        $submit_label = isset($options['submit_label']) ? sanitize_text_field((string) $options['submit_label']) : __('Enregistrer', 'mj-member');

        $status_key = isset($membership_status['status']) ? sanitize_key((string) $membership_status['status']) : 'unknown';
        $status_label = isset($membership_status['status_label']) ? sanitize_text_field((string) $membership_status['status_label']) : '';
        $status_description = isset($membership_status['description']) ? sanitize_text_field((string) $membership_status['description']) : '';
        $status_last_payment = isset($membership_status['last_payment_display']) ? sanitize_text_field((string) $membership_status['last_payment_display']) : '';
        $status_expires = isset($membership_status['expires_display']) ? sanitize_text_field((string) $membership_status['expires_display']) : '';
        $status_amount = isset($membership_status['amount']) ? (float) $membership_status['amount'] : 0.0;
        $status_requires_payment = !empty($membership_status['requires_payment']);

        $show_status_badge = ($status_label !== '' && !(!$status_requires_payment && $status_key === 'not_required'));
        $has_status_description = ($status_description !== '');
        $has_status_meta = ($status_last_payment !== '' || $status_expires !== '' || ($status_amount > 0 && $status_requires_payment));
        $has_status_cta = (!$is_preview && $status_requires_payment && in_array($status_key, array('missing', 'expired', 'expiring'), true))
            || ($is_preview && $status_requires_payment);

        if (!$display_membership) {
            $show_status_badge = false;
            $has_status_description = false;
            $has_status_meta = false;
            $has_status_cta = false;
        }

        $show_membership_section = $display_membership && ($has_status_description || $has_status_meta || $has_status_cta);

        $photo_input_id = sanitize_html_class($form_id . '-photo-upload');
        $photo_label_id = $photo_input_id . '-label';
        $photo_hint_id = $photo_input_id . '-hint';
        $photo_trigger_id = $photo_input_id . '-trigger';
        $has_existing_photo = !empty($photo_preview['url']);
        $upload_default_text = $has_existing_photo
            ? __('Photo actuelle enregistrée', 'mj-member')
            : __('Aucun fichier sélectionné', 'mj-member');
        $upload_container_classes = array('mj-account-upload');
        if ($is_preview) {
            $upload_container_classes[] = 'is-disabled';
        }

        ob_start();
        ?>
        <div class="mj-account-modern">
            <div class="mj-account-shell">
                <section class="mj-account-card mj-account-card--profile">
                    <header class="mj-account-card__header">
                        <div class="mj-account-card__header-main">
                            <?php if ($title !== '') : ?>
                                <h2 class="mj-account-card__title"><?php echo esc_html($title); ?></h2>
                            <?php endif; ?>
                            <?php if ($description !== '') : ?>
                                <p class="mj-account-card__intro"><?php echo wp_kses_post($description); ?></p>
                            <?php endif; ?>
                        </div>
                        <?php if ($show_status_badge) : ?>
                            <span class="mj-account-card__badge mj-account-status-badge mj-account-status-badge--<?php echo esc_attr($status_key !== '' ? $status_key : 'unknown'); ?>">
                                <?php echo esc_html($status_label); ?>
                            </span>
                        <?php endif; ?>
                    </header>

                    <?php if (!empty($errors)) : ?>
                        <div class="mj-notice mj-notice--error">
                            <ul class="mj-notice__list">
                                <?php foreach ($errors as $error_message) : ?>
                                    <li><?php echo esc_html($error_message); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if ($success && !empty($messages)) : ?>
                        <div class="mj-notice mj-notice--success">
                            <?php foreach ($messages as $success_message) : ?>
                                <p><?php echo esc_html($success_message); ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($show_membership_section) : ?>
                        <div class="mj-account-membership mj-account-membership--<?php echo esc_attr($status_key !== '' ? $status_key : 'unknown'); ?>">
                            <?php if ($has_status_description) : ?>
                                <p class="mj-account-membership__description"><?php echo esc_html($status_description); ?></p>
                            <?php endif; ?>
                            <?php if ($has_status_meta) : ?>
                                <ul class="mj-account-membership__meta">
                                    <?php if ($status_last_payment !== '') : ?>
                                        <li><strong><?php esc_html_e('Dernier paiement', 'mj-member'); ?></strong> <?php echo esc_html($status_last_payment); ?></li>
                                    <?php endif; ?>
                                    <?php if ($status_expires !== '') : ?>
                                        <li><strong><?php esc_html_e('Expire le', 'mj-member'); ?></strong> <?php echo esc_html($status_expires); ?></li>
                                    <?php endif; ?>
                                    <?php if ($status_amount > 0 && $status_requires_payment) : ?>
                                        <li><strong><?php esc_html_e('Montant annuel', 'mj-member'); ?></strong> <?php echo esc_html(number_format_i18n($status_amount, 2)); ?> €</li>
                                    <?php endif; ?>
                                </ul>
                            <?php endif; ?>
                            <?php if (!$is_preview && $status_requires_payment && in_array($status_key, array('missing', 'expired', 'expiring'), true)) : ?>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="mj-account-membership__cta">
                                    <?php wp_nonce_field('mj_member_generate_payment_link', 'mj_member_payment_link_nonce'); ?>
                                    <input type="hidden" name="action" value="mj_member_generate_payment_link" />
                                    <input type="hidden" name="redirect_to" value="<?php echo esc_url($current_url); ?>" />
                                    <button type="submit" class="mj-button">
                                        <?php esc_html_e('Régler ma cotisation', 'mj-member'); ?>
                                    </button>
                                </form>
                            <?php elseif ($is_preview && $status_requires_payment) : ?>
                                <div class="mj-account-membership__cta">
                                    <button type="button" class="mj-button" disabled="disabled"><?php esc_html_e('Régler ma cotisation', 'mj-member'); ?></button>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <form method="post" enctype="multipart/form-data" class="mj-account-form" id="<?php echo esc_attr($form_id); ?>">
                        <?php if (!$is_preview) : ?>
                            <?php wp_nonce_field('mj_member_account_update', 'mj_member_account_nonce'); ?>
                            <input type="hidden" name="mj_member_account_action" value="update_profile" />
                        <?php endif; ?>

                        <fieldset class="mj-fieldset">
                            <legend><?php esc_html_e('Informations personnelles', 'mj-member'); ?></legend>
                            <div class="mj-field-grid">
                                <div class="mj-field-group">
                                    <label for="mj-account-first-name"><?php esc_html_e('Prénom', 'mj-member'); ?></label>
                                    <input type="text" id="mj-account-first-name" name="member[first_name]" value="<?php echo esc_attr($form_values['first_name']); ?>" autocomplete="given-name" <?php echo $is_preview ? 'disabled="disabled"' : 'required'; ?> />
                                </div>
                                <div class="mj-field-group">
                                    <label for="mj-account-last-name"><?php esc_html_e('Nom', 'mj-member'); ?></label>
                                    <input type="text" id="mj-account-last-name" name="member[last_name]" value="<?php echo esc_attr($form_values['last_name']); ?>" autocomplete="family-name" <?php echo $is_preview ? 'disabled="disabled"' : 'required'; ?> />
                                </div>
                                <div class="mj-field-group">
                                    <label for="mj-account-email"><?php esc_html_e('Email', 'mj-member'); ?></label>
                                    <input type="email" id="mj-account-email" name="member[email]" value="<?php echo esc_attr($form_values['email']); ?>" autocomplete="email" <?php echo $is_preview ? 'disabled="disabled"' : 'required'; ?> />
                                </div>
                                <div class="mj-field-group">
                                    <label for="mj-account-phone"><?php esc_html_e('Téléphone', 'mj-member'); ?></label>
                                    <input type="tel" id="mj-account-phone" name="member[phone]" value="<?php echo esc_attr($form_values['phone']); ?>" autocomplete="tel" <?php echo $is_preview ? 'disabled="disabled"' : ''; ?> />
                                </div>
                                <div class="mj-field-group mj-field-group--full">
                                    <label for="mj-account-address"><?php esc_html_e('Adresse postale', 'mj-member'); ?></label>
                                    <textarea id="mj-account-address" name="member[address]" rows="2" <?php echo $is_preview ? 'disabled="disabled"' : ''; ?>><?php echo esc_textarea($form_values['address']); ?></textarea>
                                </div>
                                <div class="mj-field-group">
                                    <label for="mj-account-postal"><?php esc_html_e('Code postal', 'mj-member'); ?></label>
                                    <input type="text" id="mj-account-postal" name="member[postal_code]" value="<?php echo esc_attr($form_values['postal_code']); ?>" autocomplete="postal-code" <?php echo $is_preview ? 'disabled="disabled"' : ''; ?> />
                                </div>
                                <div class="mj-field-group">
                                    <label for="mj-account-city"><?php esc_html_e('Ville', 'mj-member'); ?></label>
                                    <input type="text" id="mj-account-city" name="member[city]" value="<?php echo esc_attr($form_values['city']); ?>" autocomplete="address-level2" <?php echo $is_preview ? 'disabled="disabled"' : ''; ?> />
                                </div>
                                <div class="mj-field-group">
                                    <label for="mj-account-birth-date"><?php esc_html_e('Date de naissance', 'mj-member'); ?></label>
                                    <input type="date" id="mj-account-birth-date" name="member[birth_date]" value="<?php echo esc_attr($form_values['birth_date']); ?>" autocomplete="bday" <?php echo $is_preview ? 'disabled="disabled"' : ''; ?> />
                                </div>
                                <div class="mj-field-group mj-field-group--full">
                                    <label for="mj-account-notes"><?php esc_html_e('Informations complémentaires (allergies, santé, etc.)', 'mj-member'); ?></label>
                                    <textarea id="mj-account-notes" name="member[notes]" rows="3" <?php echo $is_preview ? 'disabled="disabled"' : ''; ?>><?php echo esc_textarea($form_values['notes']); ?></textarea>
                                </div>
                            </div>
                        </fieldset>

                        <div class="mj-account-photo-field">
                            <div class="mj-account-photo-control">
                                <div class="mj-account-photo-control__preview">
                                    <?php if (!empty($photo_preview['url'])) : ?>
                                        <img src="<?php echo esc_url($photo_preview['url']); ?>" alt="<?php echo esc_attr(trim($form_values['first_name'] . ' ' . $form_values['last_name'])); ?>" />
                                    <?php else : ?>
                                        <span class="mj-account-photo-control__placeholder">👤</span>
                                    <?php endif; ?>
                                </div>
                                <div class="mj-account-photo-control__fields">
                                    <div class="mj-field-group mj-account-photo-control__upload">
                                        <span id="<?php echo esc_attr($photo_label_id); ?>" class="mj-account-photo-control__label"><?php esc_html_e('Photo de profil', 'mj-member'); ?></span>
                                        <div class="<?php echo esc_attr(implode(' ', $upload_container_classes)); ?>" data-mj-account-upload>
                                            <input
                                                type="file"
                                                id="<?php echo esc_attr($photo_input_id); ?>"
                                                class="mj-account-upload__input"
                                                name="member_photo"
                                                accept="image/*"
                                                aria-labelledby="<?php echo esc_attr($photo_label_id . ' ' . $photo_trigger_id); ?>"
                                                aria-describedby="<?php echo esc_attr($photo_hint_id); ?>"
                                                <?php echo $is_preview ? 'disabled="disabled"' : ''; ?> />
                                            <label
                                                class="mj-account-upload__trigger"
                                                id="<?php echo esc_attr($photo_trigger_id); ?>"
                                                for="<?php echo esc_attr($photo_input_id); ?>"<?php echo $is_preview ? ' aria-disabled="true" tabindex="-1"' : ''; ?>>
                                                <span class="mj-account-upload__icon" aria-hidden="true">📷</span>
                                                <span class="mj-account-upload__text"><?php esc_html_e('Choisir une image', 'mj-member'); ?></span>
                                            </label>
                                            <div class="mj-account-upload__meta">
                                                <span class="mj-account-upload__filename" data-default="<?php echo esc_attr($upload_default_text); ?>"><?php echo esc_html($upload_default_text); ?></span>
                                                <span class="mj-account-upload__hint" id="<?php echo esc_attr($photo_hint_id); ?>"><?php esc_html_e('Formats acceptés : JPG ou PNG, 5 Mo max.', 'mj-member'); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    <?php if ($is_preview) : ?>
                                        <p class="mj-account-photo-control__remove"><?php esc_html_e('Modification désactivée en aperçu.', 'mj-member'); ?></p>
                                    <?php else : ?>
                                        <label class="mj-account-photo-control__remove">
                                            <input type="checkbox" name="member[photo_remove]" value="1" <?php checked($form_values['photo_remove'], 1); ?> />
                                            <span><?php esc_html_e('Supprimer ma photo actuelle', 'mj-member'); ?></span>
                                        </label>
                                    <?php endif; ?>
                                    <input type="hidden" name="member[photo_id_existing]" value="<?php echo esc_attr((string) $photo_preview['id']); ?>" />
                                </div>
                            </div>
                        </div>

                        <fieldset class="mj-fieldset">
                            <legend><?php esc_html_e('Consentements et notifications', 'mj-member'); ?></legend>
                            <div class="mj-consent-list">
                                <label class="mj-checkbox">
                                    <input type="checkbox" name="member[photo_usage_consent]" value="1" <?php checked($form_values['photo_usage_consent'], 1); ?> <?php echo $is_preview ? 'disabled="disabled"' : ''; ?> />
                                    <span><?php esc_html_e('J’autorise l’utilisation de mes photos sur les supports MJ.', 'mj-member'); ?></span>
                                </label>
                                <label class="mj-checkbox">
                                    <input type="checkbox" name="member[newsletter_opt_in]" value="1" <?php checked($form_values['newsletter_opt_in'], 1); ?> <?php echo $is_preview ? 'disabled="disabled"' : ''; ?> />
                                    <span><?php esc_html_e('Je souhaite recevoir les informations par email.', 'mj-member'); ?></span>
                                </label>
                                <label class="mj-checkbox">
                                    <input type="checkbox" name="member[sms_opt_in]" value="1" <?php checked($form_values['sms_opt_in'], 1); ?> <?php echo $is_preview ? 'disabled="disabled"' : ''; ?> />
                                    <span><?php esc_html_e('Je souhaite recevoir les rappels importants par SMS.', 'mj-member'); ?></span>
                                </label>
                            </div>
                        </fieldset>

                        <div class="mj-form-actions">
                            <button type="submit" class="mj-button" <?php echo $is_preview ? 'disabled="disabled"' : ''; ?>>
                                <?php echo esc_html($submit_label); ?>
                            </button>
                        </div>
                    </form>
                </section>

                <?php if (!empty($options['show_children'])) : ?>
                    <section class="mj-account-card mj-account-card--children">
                        <header class="mj-account-card__header">
                            <h3 class="mj-account-card__title"><?php esc_html_e('Mes jeunes rattachés', 'mj-member'); ?></h3>
                        </header>
                        <?php if (!empty($children_statuses)) : ?>
                            <div class="mj-account-children">
                                <?php foreach ($children_statuses as $child) :
                                    $child_status = isset($child['status']) ? sanitize_key((string) $child['status']) : 'unknown';
                                    $child_label = isset($child['status_label']) ? sanitize_text_field((string) $child['status_label']) : '';
                                    $child_description = isset($child['description']) ? sanitize_text_field((string) $child['description']) : '';
                                    $child_profile = isset($child['profile']) && is_array($child['profile']) ? $child['profile'] : array();
                                    $child_birth = isset($child_profile['birth_date']) ? mj_member_account_normalize_birth_date((string) $child_profile['birth_date']) : '';
                                    $child_phone = isset($child_profile['phone']) ? sanitize_text_field((string) $child_profile['phone']) : '';
                                    $child_email = isset($child_profile['email']) ? sanitize_email((string) $child_profile['email']) : '';
                                    ?>
                                    <article class="mj-account-child-card mj-account-child-card--<?php echo esc_attr($child_status); ?>">
                                        <div class="mj-account-child-card__header">
                                            <h4 class="mj-account-child-card__title"><?php echo esc_html(isset($child['full_name']) ? (string) $child['full_name'] : __('Jeune', 'mj-member')); ?></h4>
                                            <?php if ($child_label !== '') : ?>
                                                <span class="mj-account-chip mj-account-chip--<?php echo esc_attr($child_status); ?>"><?php echo esc_html($child_label); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($child_description !== '') : ?>
                                            <p class="mj-account-child-card__summary"><?php echo esc_html($child_description); ?></p>
                                        <?php endif; ?>
                                        <ul class="mj-account-child-card__meta">
                                            <?php if ($child_birth !== '') : ?>
                                                <li><?php esc_html_e('Né(e) le', 'mj-member'); ?> <?php echo esc_html($child_birth); ?></li>
                                            <?php endif; ?>
                                            <?php if ($child_email !== '') : ?>
                                                <li><a class="mj-account-link" href="mailto:<?php echo esc_attr($child_email); ?>"><?php echo esc_html($child_email); ?></a></li>
                                            <?php endif; ?>
                                            <?php if ($child_phone !== '') : ?>
                                                <li><a class="mj-account-link" href="tel:<?php echo esc_attr($child_phone); ?>"><?php echo esc_html($child_phone); ?></a></li>
                                            <?php endif; ?>
                                            <?php if (!empty($child['expires_display'])) : ?>
                                                <li><?php esc_html_e('Expire le', 'mj-member'); ?> <?php echo esc_html((string) $child['expires_display']); ?></li>
                                            <?php endif; ?>
                                        </ul>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php else : ?>
                            <p class="mj-account-empty"><?php esc_html_e('Aucun jeune rattaché pour le moment.', 'mj-member'); ?></p>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>

                <?php if (!empty($options['show_payments'])) : ?>
                    <section class="mj-account-card mj-account-card--payments">
                        <header class="mj-account-card__header">
                            <h3 class="mj-account-card__title"><?php esc_html_e('Historique des paiements', 'mj-member'); ?></h3>
                        </header>
                        <?php if (!empty($payment_history)) : ?>
                            <div class="mj-account-payments">
                                <?php foreach ($payment_history as $entry) :
                                    $entry_label = isset($entry['status_label']) ? sanitize_text_field((string) $entry['status_label']) : '';
                                    $entry_amount = isset($entry['amount']) ? sanitize_text_field((string) $entry['amount']) : '';
                                    $entry_date = isset($entry['date']) ? sanitize_text_field((string) $entry['date']) : '';
                                    $entry_reference = isset($entry['reference']) ? sanitize_text_field((string) $entry['reference']) : '';
                                    ?>
                                    <article class="mj-account-payment">
                                        <div class="mj-account-payment__main">
                                            <span class="mj-account-payment__label"><?php echo esc_html($entry_label !== '' ? $entry_label : __('Paiement', 'mj-member')); ?></span>
                                            <?php if ($entry_amount !== '') : ?>
                                                <span class="mj-account-payment__amount"><?php echo esc_html($entry_amount); ?> €</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="mj-account-payment__meta">
                                            <?php if ($entry_date !== '') : ?>
                                                <span class="mj-account-payment__date"><?php echo esc_html($entry_date); ?></span>
                                            <?php endif; ?>
                                            <?php if ($entry_reference !== '') : ?>
                                                <span class="mj-account-payment__reference">#<?php echo esc_html($entry_reference); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php else : ?>
                            <p class="mj-account-empty"><?php esc_html_e('Aucun paiement enregistré pour le moment.', 'mj-member'); ?></p>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>
            </div>
        </div>
        <?php
        static $mj_account_styles_printed = false;
        if (!$mj_account_styles_printed) {
            $mj_account_styles_printed = true;
            ?>
            <style>
.mj-account-modern {
    --mj-account-card-bg: #ffffff;
    --mj-account-border: rgba(47, 82, 143, 0.08);
    --mj-account-accent: #2f528f;
    --mj-account-radius-lg: 24px;
    --mj-account-radius: 18px;
    --mj-account-radius-sm: 12px;
    --mj-account-shadow: 0 18px 35px rgba(36, 64, 109, 0.12);
    --mj-account-text: #1f2742;
    --mj-account-muted: #5c6b8a;
    font-family: inherit;
    color: var(--mj-account-text);
    padding: 48px 24px;
}

.mj-account-modern a.mj-account-link {
    color: var(--mj-account-accent);
    font-weight: 600;
    text-decoration: none;
}

.mj-account-modern a.mj-account-link:hover,
.mj-account-modern a.mj-account-link:focus {
    text-decoration: underline;
}

.mj-account-shell {
    max-width: 1100px;
    margin: 0 auto;
    display: grid;
    gap: 32px;
}

.mj-account-card {
    background: var(--mj-account-card-bg);
    border-radius: var(--mj-account-radius-lg);
    border: 1px solid var(--mj-account-border);
    box-shadow: var(--mj-account-shadow);
    padding: 32px;
    min-width: 100%;
}

.mj-account-card__header {
    display: flex;
    justify-content: space-between;
    gap: 24px;
    align-items: flex-start;
    margin-bottom: 24px;
}

.mj-account-card__title {
    margin: 0;
    font-size: 1.6rem;
    font-weight: 700;
}

.mj-account-card__intro {
    margin: 8px 0 0;
    color: var(--mj-account-muted);
    line-height: 1.5;
}

.mj-account-card__badge {
    display: inline-flex;
    align-items: center;
    padding: 8px 14px;
    border-radius: 999px;
    background: rgba(47, 82, 143, 0.08);
    color: var(--mj-account-accent);
    font-weight: 600;
}

.mj-account-status-badge--missing,
.mj-account-status-badge--expired {
    background: rgba(205, 45, 64, 0.1);
    color: #b71c1c;
}

.mj-account-status-badge--expiring {
    background: rgba(255, 153, 0, 0.12);
    color: #c06a00;
}

.mj-account-membership {
    padding: 24px;
    border-radius: var(--mj-account-radius);
    border: 1px dashed rgba(47, 82, 143, 0.16);
    margin-bottom: 32px;
    background: rgba(47, 82, 143, 0.04);
}

.mj-account-membership--active {
    background: rgba(46, 204, 113, 0.08);
    border-color: rgba(46, 204, 113, 0.24);
}

.mj-account-membership--missing,
.mj-account-membership--expired {
    background: rgba(205, 45, 64, 0.08);
    border-color: rgba(205, 45, 64, 0.24);
}

.mj-account-membership--expiring {
    background: rgba(255, 153, 0, 0.1);
    border-color: rgba(255, 153, 0, 0.24);
}

.mj-account-membership__description {
    margin: 0 0 16px;
    font-weight: 500;
}

.mj-account-membership__meta {
    margin: 0;
    padding: 0;
    list-style: none;
    display: flex;
    flex-wrap: wrap;
    gap: 12px 24px;
    color: var(--mj-account-muted);
}

.mj-account-membership__meta strong {
    color: var(--mj-account-text);
    margin-right: 6px;
}

.mj-account-membership__cta {
    margin-top: 20px;
}

.mj-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    background: var(--mj-account-accent);
    color: #ffffff;
    border: none;
    border-radius: 999px;
    padding: 12px 24px;
    font-weight: 700;
    cursor: pointer;
    box-shadow: 0 12px 24px rgba(47, 82, 143, 0.25);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.mj-button:hover,
.mj-button:focus {
    transform: translateY(-1px);
    box-shadow: 0 18px 30px rgba(47, 82, 143, 0.28);
}

.mj-button[disabled] {
    opacity: 0.6;
    cursor: not-allowed;
    box-shadow: none;
}

.mj-account-form {
    display: flex;
    flex-direction: column;
    gap: 32px;
}

.mj-fieldset {
    margin: 0;
    padding: 0;
    border: none;
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.mj-fieldset > legend {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--mj-account-text);
}

.mj-field-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 18px 24px;
}

.mj-field-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.mj-field-group--full {
    grid-column: 1 / -1;
}

.mj-field-group label {
    font-weight: 600;
    color: var(--mj-account-text);
}

.mj-field-group input,
.mj-field-group textarea {
    border: 1px solid rgba(47, 82, 143, 0.16);
    border-radius: var(--mj-account-radius-sm);
    padding: 12px 14px;
    font-size: 1rem;
    background: rgba(255, 255, 255, 0.92);
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.mj-field-group textarea {
    min-height: 90px;
    resize: vertical;
}

.mj-field-group input:focus,
.mj-field-group textarea:focus {
    outline: none;
    border-color: var(--mj-account-accent);
    box-shadow: 0 0 0 3px rgba(47, 82, 143, 0.18);
}

.mj-account-photo-field {
    padding: 24px;
    background: rgba(47, 82, 143, 0.05);
    border-radius: var(--mj-account-radius);
    border: 1px solid rgba(47, 82, 143, 0.12);
}

.mj-account-photo-control {
    display: flex;
    gap: 24px;
    align-items: center;
}

.mj-account-photo-control__preview {
    width: 96px;
    height: 96px;
    border-radius: 50%;
    overflow: hidden;
    background: rgba(47, 82, 143, 0.08);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 40px;
}

.mj-account-photo-control__preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.mj-account-photo-control__fields {
    display: flex;
    flex-direction: column;
    gap: 12px;
    flex: 1;
}

.mj-account-photo-control__upload {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.mj-account-photo-control__label {
    font-weight: 600;
    font-size: 0.95rem;
    color: var(--mj-account-text);
}

.mj-account-upload {
    position: relative;
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
}

.mj-account-upload__input {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    border: 0;
    clip: rect(0, 0, 0, 0);
    overflow: hidden;
    white-space: nowrap;
}

.mj-account-upload__trigger {
    display: inline-flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.65rem 1.1rem;
    border-radius: var(--mj-account-radius-sm);
    background: rgba(47, 82, 143, 0.12);
    border: 1px dashed rgba(47, 82, 143, 0.45);
    color: var(--mj-account-accent);
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s ease, border-color 0.2s ease, color 0.2s ease, transform 0.2s ease;
}

.mj-account-upload__input:focus + .mj-account-upload__trigger,
.mj-account-upload__input:focus-visible + .mj-account-upload__trigger,
.mj-account-upload__trigger:hover,
.mj-account-upload__trigger:focus-visible {
    background: rgba(47, 82, 143, 0.18);
    border-color: var(--mj-account-accent);
    outline: none;
    transform: translateY(-1px);
}

.mj-account-upload__icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.4rem;
    height: 2.4rem;
    border-radius: 50%;
    background: rgba(47, 82, 143, 0.15);
    font-size: 1.1rem;
}

.mj-account-upload__meta {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    font-size: 0.9rem;
    color: var(--mj-account-muted);
}

.mj-account-upload__filename {
    font-weight: 500;
    color: var(--mj-account-text);
    word-break: break-word;
}

.mj-account-upload__hint {
    font-size: 0.85rem;
}

.mj-account-upload.has-file .mj-account-upload__trigger {
    background: rgba(47, 82, 143, 0.2);
    border-style: solid;
}

.mj-account-upload.is-disabled .mj-account-upload__trigger {
    cursor: not-allowed;
    opacity: 0.6;
    transform: none;
}

.mj-account-upload.is-disabled .mj-account-upload__trigger:hover,
.mj-account-upload.is-disabled .mj-account-upload__trigger:focus-visible {
    background: rgba(47, 82, 143, 0.12);
    border-color: rgba(47, 82, 143, 0.45);
    transform: none;
}

.mj-account-photo-control__remove {
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--mj-account-muted);
    font-size: 0.95rem;
}

.mj-consent-list {
    display: grid;
    gap: 18px;
}

.mj-checkbox {
    display: flex;
    gap: 12px;
    align-items: flex-start;
    background: rgba(47, 82, 143, 0.04);
    border-radius: var(--mj-account-radius-sm);
    padding: 14px;
    border: 1px solid rgba(47, 82, 143, 0.12);
}

.mj-checkbox input[type="checkbox"] {
    margin-top: 2px;
}

.mj-form-actions {
    display: flex;
    justify-content: flex-end;
}

.mj-account-card--children,
.mj-account-card--payments {
    padding: 28px;
}

.mj-account-children {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 20px;
}

.mj-account-child-card {
    border-radius: var(--mj-account-radius);
    border: 1px solid rgba(47, 82, 143, 0.12);
    padding: 20px;
    background: rgba(255, 255, 255, 0.95);
    display: flex;
    flex-direction: column;
    gap: 14px;
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.5);
}

.mj-account-child-card--missing,
.mj-account-child-card--expired {
    background: rgba(205, 45, 64, 0.06);
}

.mj-account-child-card__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
}

.mj-account-child-card__title {
    margin: 0;
    font-size: 1.2rem;
}

.mj-account-chip {
    display: inline-flex;
    padding: 6px 12px;
    border-radius: 999px;
    font-size: 0.85rem;
    font-weight: 600;
    background: rgba(47, 82, 143, 0.08);
    color: var(--mj-account-accent);
}

.mj-account-chip--missing,
.mj-account-chip--expired {
    background: rgba(205, 45, 64, 0.12);
    color: #b71c1c;
}

.mj-account-child-card__summary {
    margin: 0;
    color: var(--mj-account-muted);
}

.mj-account-child-card__meta {
    margin: 0;
    padding: 0;
    list-style: none;
    display: grid;
    gap: 6px;
    color: var(--mj-account-muted);
}

.mj-account-payments {
    display: grid;
    gap: 12px;
}

.mj-account-payment {
    padding: 16px 18px;
    border-radius: var(--mj-account-radius-sm);
    border: 1px solid rgba(47, 82, 143, 0.12);
    background: rgba(47, 82, 143, 0.05);
    display: flex;
    flex-direction: column;
    gap: 8px;
        min-width: 100%;
}

.mj-account-payment__main {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
}

.mj-account-payment__label {
    font-weight: 600;
}

.mj-account-payment__amount {
    font-weight: 700;
    color: var(--mj-account-accent);
}

.mj-account-payment__meta {
    display: flex;
    gap: 16px;
    font-size: 0.95rem;
    color: var(--mj-account-muted);
}

.mj-account-empty {
    margin: 0;
    color: var(--mj-account-muted);
    font-style: italic;
}

.mj-notice {
    padding: 16px 18px;
    border-radius: var(--mj-account-radius-sm);
    border: 1px solid transparent;
    margin-bottom: 20px;
}

.mj-notice--error {
    background: rgba(205, 45, 64, 0.08);
    border-color: rgba(205, 45, 64, 0.18);
    color: #901624;
}

.mj-notice--success {
    background: rgba(46, 204, 113, 0.08);
    border-color: rgba(46, 204, 113, 0.18);
    color: #2c7d49;
}

.mj-notice__list {
    margin: 0;
    padding-left: 18px;
    display: grid;
    gap: 6px;
}

@media (max-width: 960px) {
    .mj-account-card {
        padding: 24px;
    }

    .mj-account-card__header {
        flex-direction: column;
        align-items: flex-start;
    }

    .mj-account-photo-control {
        flex-direction: column;
        align-items: flex-start;
    }

    .mj-form-actions {
        justify-content: stretch;
    }

    .mj-form-actions .mj-button {
        width: 100%;
    }
}

@media (max-width: 640px) {
    .mj-account-modern {
        padding: 32px 18px;
    }

    .mj-account-shell {
        gap: 24px;
    }

    .mj-account-membership__meta {
        flex-direction: column;
        gap: 8px;
    }

    .mj-account-payment__main {
        flex-direction: column;
        align-items: flex-start;
    }

    .mj-account-payment__meta {
        flex-direction: column;
        gap: 6px;
    }
}
            </style><?php
        }

        static $mj_account_upload_script_printed = false;
        if (!$mj_account_upload_script_printed) {
            $mj_account_upload_script_printed = true;
            ?>
            <script>
(function () {
    function initUpload(container) {
        if (!container) {
            return;
        }
        var input = container.querySelector('input[type="file"]');
        var filename = container.querySelector('.mj-account-upload__filename');
        if (!input || !filename) {
            return;
        }
        var defaultText = filename.getAttribute('data-default') || filename.textContent || '';
        var update = function () {
            var name = '';
            if (input.files && input.files.length > 0) {
                name = input.files[0].name;
            }
            if (name) {
                filename.textContent = name;
                container.classList.add('has-file');
            } else {
                filename.textContent = defaultText;
                container.classList.remove('has-file');
            }
        };
        input.addEventListener('change', update);
        var form = input.form;
        if (form) {
            form.addEventListener('reset', function () {
                window.setTimeout(update, 0);
            });
        }
        update();
    }
    function setup() {
        var nodes = document.querySelectorAll('[data-mj-account-upload]');
        for (var i = 0; i < nodes.length; i++) {
            var node = nodes[i];
            if (node.dataset.mjAccountUploadInit === '1') {
                continue;
            }
            node.dataset.mjAccountUploadInit = '1';
            initUpload(node);
        }
    }
    if (typeof document !== 'undefined') {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', setup);
        } else {
            setup();
        }
        if (typeof MutationObserver !== 'undefined') {
            var observer = new MutationObserver(function () {
                setup();
            });
            observer.observe(document.documentElement, { childList: true, subtree: true });
        }
    }
})();
            </script><?php
        }

        return ob_get_clean();
    }
}
