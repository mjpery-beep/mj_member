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

if (!function_exists('mj_member_process_account_deletion_early')) {
    /**
     * Traite la suppression de compte avant tout rendu.
     * Retourne l'URL de redirection si succès, false sinon.
     *
     * @return string|false URL de redirection ou false si erreur/non applicable.
     */
    function mj_member_process_account_deletion_early() {
        // Vérifier le nonce
        $nonce = isset($_POST['mj_member_account_delete_nonce']) ? sanitize_text_field(wp_unslash((string) $_POST['mj_member_account_delete_nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'mj_member_account_delete')) {
            return false;
        }

        // Vérifier que les classes nécessaires existent
        if (!class_exists('MjMembers') || !method_exists('MjMembers', 'update')) {
            return false;
        }

        // Récupérer le membre courant
        if (!function_exists('mj_member_get_current_member')) {
            return false;
        }

        $member = mj_member_get_current_member();
        if (!$member || !is_object($member)) {
            return false;
        }

        $member_id = isset($member->id) ? (int) $member->id : 0;
        $current_user_id = get_current_user_id();

        if ($member_id <= 0 || $current_user_id <= 0) {
            return false;
        }

        // Sauvegarder les valeurs précédentes pour rollback
        $previous_status = isset($member->status) ? sanitize_key((string) $member->status) : MjMembers::STATUS_ACTIVE;
        $previous_wp_user_id = isset($member->wp_user_id) ? (int) $member->wp_user_id : 0;
        $previous_newsletter = !empty($member->newsletter_opt_in) ? 1 : 0;
        $previous_sms = !empty($member->sms_opt_in) ? 1 : 0;
        $previous_whatsapp = !empty($member->whatsapp_opt_in) ? 1 : 0;

        // Désactiver le membre
        $deactivate_payload = array(
            'status' => MjMembers::STATUS_INACTIVE,
            'wp_user_id' => null,
            'newsletter_opt_in' => 0,
            'sms_opt_in' => 0,
            'whatsapp_opt_in' => 0,
        );

        $deactivate_result = MjMembers::update($member_id, $deactivate_payload);
        if (is_wp_error($deactivate_result)) {
            return false;
        }

        // Charger la fonction wp_delete_user si nécessaire
        if (!function_exists('wp_delete_user')) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
        }

        // Accorder temporairement les capacités de suppression
        $delete_cap_callback = static function ($allcaps, $caps, $args) use ($current_user_id) {
            if (empty($args) || !isset($args[0])) {
                return $allcaps;
            }

            $requested_capability = (string) $args[0];
            if ($requested_capability === 'delete_users') {
                $allcaps['delete_users'] = true;
                return $allcaps;
            }

            if ($requested_capability === 'delete_user') {
                $target_user_id = isset($args[2]) ? (int) $args[2] : 0;
                if ($target_user_id === $current_user_id) {
                    $allcaps['delete_user'] = true;
                }
            }

            return $allcaps;
        };

        add_filter('user_has_cap', $delete_cap_callback, 10, 3);

        $user_deleted = wp_delete_user($current_user_id);

        remove_filter('user_has_cap', $delete_cap_callback, 10);

        if (!$user_deleted) {
            // Rollback
            MjMembers::update($member_id, array(
                'status' => $previous_status !== '' ? $previous_status : MjMembers::STATUS_ACTIVE,
                'wp_user_id' => $previous_wp_user_id > 0 ? $previous_wp_user_id : null,
                'newsletter_opt_in' => $previous_newsletter,
                'sms_opt_in' => $previous_sms,
                'whatsapp_opt_in' => $previous_whatsapp,
            ));
            return false;
        }

        // Détruire la session explicitement
        wp_destroy_current_session();
        wp_clear_auth_cookie();
        wp_set_current_user(0);

        // Construire l'URL de redirection
        $default_redirect = add_query_arg('mj-account-deleted', '1', home_url('/'));

        /** @var string $redirect_url Permet de personnaliser la redirection après suppression. */
        return apply_filters('mj_member_account_deletion_redirect', $default_redirect, $member);
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

        // Traiter la suppression de compte EN PREMIER, avant tout output
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mj_member_account_action']) && $_POST['mj_member_account_action'] === 'delete_account') {
            $delete_redirect = mj_member_process_account_deletion_early();
            if ($delete_redirect !== false) {
                // Redirection immédiate - ne pas continuer le rendu
                if (!headers_sent()) {
                    wp_safe_redirect($delete_redirect);
                    exit;
                }
                // Headers déjà envoyés, retourner un script de redirection
                return '<script>window.location.href = ' . wp_json_encode($delete_redirect) . ';</script>'
                     . '<noscript><meta http-equiv="refresh" content="0;url=' . esc_attr($delete_redirect) . '"></noscript>';
            }
        }

        $defaults = array(
            'redirect' => '',
            'title' => __('Mes informations', 'mj-member'),
            'description' => '',
            'submit_label' => __('Enregistrer', 'mj-member'),
            'success_message' => __('Vos informations ont été mises à jour.', 'mj-member'),
            'show_children' => false,
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
        }

        if (is_user_logged_in()) {
            $has_elementor_preview_param = isset($_GET['elementor-preview']) && $_GET['elementor-preview'] !== ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

            if ($has_elementor_preview_param) {
                $is_preview = true;
            } elseif (!is_admin()) {
                $is_preview = false;
            }
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

            if (empty($options['show_children'])) {
                $children_statuses = array();
            }
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
                            // Save dynamic field values
                            $dyn_post_values = array();
                            foreach ($_POST as $pk => $pv) {
                                if (strpos($pk, 'dynfield_') !== 0) continue;
                                // Skip the companion "_other" text inputs; they are merged below
                                if (substr($pk, -6) === '_other') continue;

                                $df_fid = (int) substr($pk, 9);
                                if ($df_fid <= 0) continue;

                                if (is_array($pv)) {
                                    // Checklist: array of selected values
                                    $sanitized = array_map(function ($v) { return sanitize_text_field(wp_unslash($v)); }, $pv);
                                    // Replace __other placeholder with the real text
                                    $other_key   = $pk . '_other';
                                    $other_text  = isset($_POST[$other_key]) ? sanitize_text_field(wp_unslash($_POST[$other_key])) : '';
                                    $sanitized   = array_map(function ($v) use ($other_text) {
                                        return $v === '__other' && $other_text !== '' ? '__other:' . $other_text : $v;
                                    }, $sanitized);
                                    // Remove bare __other with no text
                                    $sanitized = array_filter($sanitized, function ($v) { return $v !== '__other'; });
                                    $dyn_post_values[$df_fid] = wp_json_encode(array_values($sanitized));
                                } else {
                                    $val = sanitize_text_field(wp_unslash($pv));
                                    if ($val === '__other') {
                                        $other_key  = $pk . '_other';
                                        $other_text = isset($_POST[$other_key]) ? sanitize_text_field(wp_unslash($_POST[$other_key])) : '';
                                        $val = $other_text !== '' ? '__other:' . $other_text : '';
                                    }
                                    $dyn_post_values[$df_fid] = $val;
                                }
                            }
                            if (!empty($dyn_post_values)) {
                                \Mj\Member\Classes\Crud\MjDynamicFieldValues::saveBulk((int) $member->id, $dyn_post_values);
                            }

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

        $children_payload = array();
        if (!empty($children_statuses)) {
            foreach ($children_statuses as $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                $child_id = isset($entry['id']) ? (int) $entry['id'] : 0;
                if ($child_id <= 0) {
                    continue;
                }

                $child_profile = isset($entry['profile']) && is_array($entry['profile'])
                    ? $entry['profile']
                    : array();

                $child_photo_meta = isset($entry['photo']) && is_array($entry['photo']) ? $entry['photo'] : array();
                $child_photo_id = isset($child_photo_meta['id']) ? (int) $child_photo_meta['id'] : (isset($child_profile['photo_id']) ? (int) $child_profile['photo_id'] : 0);
                $child_photo_url = isset($child_photo_meta['url']) ? esc_url_raw((string) $child_photo_meta['url']) : (isset($child_profile['photo_url']) ? esc_url_raw((string) $child_profile['photo_url']) : '');

                $children_payload[] = array(
                    'id' => $child_id,
                    'full_name' => isset($entry['full_name']) ? sanitize_text_field((string) $entry['full_name']) : '',
                    'status' => isset($entry['status']) ? sanitize_key((string) $entry['status']) : 'unknown',
                    'status_label' => isset($entry['status_label']) ? sanitize_text_field((string) $entry['status_label']) : '',
                    'description' => isset($entry['description']) ? sanitize_text_field((string) $entry['description']) : '',
                    'last_payment_display' => isset($entry['last_payment_display']) ? sanitize_text_field((string) $entry['last_payment_display']) : '',
                    'expires_display' => isset($entry['expires_display']) ? sanitize_text_field((string) $entry['expires_display']) : '',
                    'requires_payment' => !empty($entry['requires_payment']) ? 1 : 0,
                    'profile' => array(
                        'first_name' => isset($child_profile['first_name']) ? sanitize_text_field((string) $child_profile['first_name']) : '',
                        'last_name' => isset($child_profile['last_name']) ? sanitize_text_field((string) $child_profile['last_name']) : '',
                        'email' => isset($child_profile['email']) ? sanitize_email((string) $child_profile['email']) : '',
                        'phone' => isset($child_profile['phone']) ? sanitize_text_field((string) $child_profile['phone']) : '',
                        'birth_date' => isset($child_profile['birth_date']) ? mj_member_account_normalize_birth_date((string) $child_profile['birth_date']) : '',
                        'notes' => isset($child_profile['notes']) ? sanitize_textarea_field((string) $child_profile['notes']) : '',
                        'is_autonomous' => !empty($child_profile['is_autonomous']) ? 1 : 0,
                        'photo_usage_consent' => !empty($child_profile['photo_usage_consent']) ? 1 : 0,
                        'photo_id' => $child_photo_id,
                        'photo_url' => $child_photo_url,
                    ),
                    'photo' => array(
                        'id' => $child_photo_id,
                        'url' => $child_photo_url,
                    ),
                );
            }
        }

        if (!empty($options['show_children'])) {
            wp_enqueue_script('mj-member-member-account');
            $account_localization = array(
                'ajaxUrl' => esc_url_raw(admin_url('admin-ajax.php')),
                'isPreview' => $is_preview ? 1 : 0,
                'memberId' => isset($member->id) ? (int) $member->id : 0,
                'actions' => array(
                    'create' => 'mj_member_create_child_profile',
                    'update' => 'mj_member_update_child_profile',
                ),
                'nonces' => array(
                    'create' => wp_create_nonce('mj_member_create_child_profile'),
                    'update' => wp_create_nonce('mj_member_update_child_profile'),
                ),
                'children' => array_values($children_payload),
                'i18n' => array(
                    'addChild' => __('Ajouter un jeune', 'mj-member'),
                    'editChild' => __('Modifier', 'mj-member'),
                    'formTitleCreate' => __('Ajouter un jeune', 'mj-member'),
                    'formTitleEdit' => __('Modifier les informations du jeune', 'mj-member'),
                    'submitCreate' => __('Enregistrer le jeune', 'mj-member'),
                    'submitEdit' => __('Enregistrer les modifications', 'mj-member'),
                    'cancel' => __('Annuler', 'mj-member'),
                    'saving' => __('Enregistrement…', 'mj-member'),
                    'errorGeneric' => __('Une erreur est survenue. Merci de réessayer.', 'mj-member'),
                    'errorListIntro' => __('Merci de corriger les éléments suivants :', 'mj-member'),
                    'successCreate' => __('Le jeune a été ajouté.', 'mj-member'),
                    'successUpdate' => __('Les informations du jeune ont été mises à jour.', 'mj-member'),
                    'emptyChildren' => __('Aucun jeune rattaché pour le moment.', 'mj-member'),
                    'close' => __('Fermer', 'mj-member'),
                    'birthLabel' => __('Né(e) le', 'mj-member'),
                    'expiresLabel' => __('Expire le', 'mj-member'),
                    'notesLabel' => __('Notes', 'mj-member'),
                    'autonomousLabel' => __('Autorisation de sortie autonome', 'mj-member'),
                    'photoConsentLabel' => __('Autorisation photo', 'mj-member'),
                    'defaultChildName' => \Mj\Member\Classes\MjRoles::getRoleLabel(\Mj\Member\Classes\MjRoles::JEUNE),
                    'errorFirstName' => __('Merci de renseigner le prénom du jeune.', 'mj-member'),
                    'errorLastName' => __('Merci de renseigner le nom du jeune.', 'mj-member'),
                ),
            );

            static $mj_member_account_script_localized = false;
            if (!$mj_member_account_script_localized) {
                wp_localize_script('mj-member-member-account', 'mjMemberAccountData', $account_localization);
                $mj_member_account_script_localized = true;
            }
        }

        $child_photo_input_id = sanitize_html_class(wp_unique_id('mj-account-child-photo-'));
        $child_photo_label_id = $child_photo_input_id . '-label';
        $child_photo_hint_id = $child_photo_input_id . '-hint';
        $child_photo_trigger_id = $child_photo_input_id . '-trigger';
        $child_photo_default_text = __('Aucun fichier sélectionné', 'mj-member');

        $has_children_tabs = !empty($options['show_children']) && !empty($children_payload);

        ob_start();
        ?>
        <div class="mj-account-modern" data-mj-member-account>
            <div class="mj-account-shell">
                <?php if ($has_children_tabs) : ?>
                <nav class="mj-account-tabs" role="tablist" data-mj-account-tabs>
                    <button type="button" class="mj-account-tab mj-account-tab--active" role="tab" aria-selected="true" data-mj-tab="parent">
                        📋 <?php esc_html_e('Mes données', 'mj-member'); ?>
                    </button>
                    <?php foreach ($children_payload as $tab_child) : ?>
                    <button type="button" class="mj-account-tab" role="tab" aria-selected="false" data-mj-tab="child-<?php echo esc_attr((string) $tab_child['id']); ?>">
                        👶 <?php echo esc_html($tab_child['full_name'] !== '' ? $tab_child['full_name'] : __('Jeune', 'mj-member')); ?>
                    </button>
                    <?php endforeach; ?>
                    <button
                        type="button"
                        class="mj-account-tab mj-account-tab--add"
                        data-mj-member-child-add
                        <?php echo $is_preview ? 'disabled="disabled" aria-disabled="true"' : ''; ?>
                        aria-controls="mj-member-child-modal">
                        <span aria-hidden="true">＋</span>
                        <span><?php esc_html_e('Ajouter', 'mj-member'); ?></span>
                    </button>
                </nav>
                <?php endif; ?>
                <section class="mj-account-card mj-account-card--profile"<?php echo $has_children_tabs ? ' data-mj-tab-panel="parent"' : ''; ?>>
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
                                    <label for="mj-account-notes"><?php esc_html_e('Informations complémentaires à transmettre à nos animateurs', 'mj-member'); ?></label>
                                    <textarea id="mj-account-notes" name="member[notes]" rows="3" <?php echo $is_preview ? 'disabled="disabled"' : ''; ?>><?php echo esc_textarea($form_values['notes']); ?></textarea>
                                </div>
                            </div>
                        </fieldset>

                        <?php
                        // Dynamic fields (account panel)
                        $dyn_account_fields = \Mj\Member\Classes\Crud\MjDynamicFields::getAccountFields();
                        if (!empty($dyn_account_fields)) :
                            $dyn_member_id = $is_preview ? 0 : (int) ($member->id ?? 0);
                            $dyn_values = $dyn_member_id ? \Mj\Member\Classes\Crud\MjDynamicFieldValues::getByMemberKeyed($dyn_member_id) : array();
                        ?>
                        <fieldset class="mj-fieldset">
                            <div class="mj-field-grid mj-field-grid--dynfields">
                                <?php foreach ($dyn_account_fields as $df) :
                                    // Skip youth-only fields for guardians (members who have children)
                                    if (!empty($df->youth_only) && $has_children_tabs) continue;

                                    $df_id   = (int) $df->id;
                                    $df_name = 'dynfield_' . $df_id;
                                    $df_html = 'mj-account-dynfield-' . $df_id;
                                    $df_req  = (int) $df->is_required;
                                    $df_val  = $dyn_values[$df_id] ?? '';
                                    $df_opts = \Mj\Member\Classes\Crud\MjDynamicFields::decodeOptions($df->options_list);
                                    $df_label = esc_html($df->title) . ($df_req ? ' *' : '');
                                    $df_desc  = $df->description ? '<small class="mj-field-hint">' . esc_html($df->description) . '</small>' : '';

                                    // Section title — close grid, render heading, reopen grid
                                    if ($df->field_type === 'title') : ?>
                                        </div>
                                        <h5 class="mj-dynfield-section-title"><?php echo esc_html($df->title); ?></h5>
                                        <?php if ($df->description) : ?>
                                            <p class="mj-dynfield-section-desc"><?php echo esc_html($df->description); ?></p>
                                        <?php endif; ?>
                                        <div class="mj-field-grid--dynfields">
                                    <?php continue; endif; ?>
                                <?php
                                    // Decode checklist/other value helpers
                                    $df_allow_other = (int) ($df->allow_other ?? 0);
                                    $df_other_label = ($df->other_label ?? '') !== '' ? $df->other_label : 'Autre';
                                    $df_is_other    = false;
                                    $df_other_text  = '';
                                    $df_checked_arr = array();

                                    if ($df->field_type === 'checklist') {
                                        $df_checked_arr = is_string($df_val) && $df_val !== '' ? json_decode($df_val, true) : array();
                                        if (!is_array($df_checked_arr)) $df_checked_arr = array();
                                        foreach ($df_checked_arr as $ck => $cv) {
                                            if (strpos($cv, '__other:') === 0) {
                                                $df_other_text = substr($cv, 8);
                                                $df_is_other = true;
                                                unset($df_checked_arr[$ck]);
                                                break;
                                            }
                                        }
                                    } elseif (in_array($df->field_type, array('dropdown', 'radio'), true) && $df_allow_other) {
                                        if (strpos($df_val, '__other:') === 0) {
                                            $df_other_text = substr($df_val, 8);
                                            $df_is_other = true;
                                        }
                                    }
                                ?>
                                <div class="mj-field-group mj-field-group--dyn<?php echo in_array($df->field_type, array('textarea', 'checklist'), true) ? ' mj-field-group--full' : ''; ?>">
                                    <?php if ($df->field_type === 'text') : ?>
                                        <label for="<?php echo $df_html; ?>"><?php echo $df_label; ?></label>
                                        <?php echo $df_desc; ?>
                                        <input type="text" id="<?php echo $df_html; ?>" name="<?php echo $df_name; ?>" value="<?php echo esc_attr($df_val); ?>" <?php echo $is_preview ? 'disabled="disabled"' : ''; ?> <?php echo $df_req ? 'required' : ''; ?> />
                                    <?php elseif ($df->field_type === 'textarea') : ?>
                                        <label for="<?php echo $df_html; ?>"><?php echo $df_label; ?></label>
                                        <?php echo $df_desc; ?>
                                        <textarea id="<?php echo $df_html; ?>" name="<?php echo $df_name; ?>" rows="3" <?php echo $is_preview ? 'disabled="disabled"' : ''; ?> <?php echo $df_req ? 'required' : ''; ?>><?php echo esc_textarea($df_val); ?></textarea>
                                    <?php elseif ($df->field_type === 'dropdown') : ?>
                                        <label for="<?php echo $df_html; ?>"><?php echo $df_label; ?></label>
                                        <?php echo $df_desc; ?>
                                        <select id="<?php echo $df_html; ?>" name="<?php echo $df_name; ?>" <?php echo $is_preview ? 'disabled="disabled"' : ''; ?> <?php echo $df_req ? 'required' : ''; ?> <?php echo $df_allow_other ? 'data-dynfield-other="1"' : ''; ?>>
                                            <option value="">&mdash; Sélectionnez &mdash;</option>
                                            <?php foreach ($df_opts as $opt) : ?>
                                                <option value="<?php echo esc_attr($opt); ?>" <?php selected(!$df_is_other ? $df_val : '', $opt); ?>><?php echo esc_html($opt); ?></option>
                                            <?php endforeach; ?>
                                            <?php if ($df_allow_other) : ?>
                                                <option value="__other" <?php selected($df_is_other); ?>><?php echo esc_html($df_other_label); ?>…</option>
                                            <?php endif; ?>
                                        </select>
                                        <?php if ($df_allow_other) : ?>
                                            <input type="text" class="mj-dynfield-other-input" name="<?php echo $df_name; ?>_other" value="<?php echo esc_attr($df_other_text); ?>" placeholder="Précisez…" <?php echo $is_preview ? 'disabled="disabled"' : ''; ?> style="<?php echo $df_is_other ? '' : 'display:none;'; ?> margin-top:6px;" />
                                        <?php endif; ?>
                                    <?php elseif ($df->field_type === 'radio') : ?>
                                        <fieldset>
                                            <legend><?php echo $df_label; ?></legend>
                                            <?php echo $df_desc; ?>
                                            <?php foreach ($df_opts as $oi => $opt) : ?>
                                                <label class="mj-radio">
                                                    <input type="radio" name="<?php echo $df_name; ?>" value="<?php echo esc_attr($opt); ?>" <?php checked(!$df_is_other ? $df_val : '', $opt); ?> <?php echo $is_preview ? 'disabled="disabled"' : ''; ?> <?php echo ($df_req && $oi === 0 && !$df_allow_other) ? 'required' : ''; ?> />
                                                    <span><?php echo esc_html($opt); ?></span>
                                                </label>
                                            <?php endforeach; ?>
                                            <?php if ($df_allow_other) : ?>
                                                <label class="mj-radio">
                                                    <input type="radio" name="<?php echo $df_name; ?>" value="__other" <?php checked($df_is_other); ?> <?php echo $is_preview ? 'disabled="disabled"' : ''; ?> />
                                                    <span><?php echo esc_html($df_other_label); ?></span>
                                                </label>
                                                <input type="text" class="mj-dynfield-other-input" name="<?php echo $df_name; ?>_other" value="<?php echo esc_attr($df_other_text); ?>" placeholder="Précisez…" <?php echo $is_preview ? 'disabled="disabled"' : ''; ?> style="<?php echo $df_is_other ? '' : 'display:none;'; ?> margin-top:4px;" />
                                            <?php endif; ?>
                                        </fieldset>
                                    <?php elseif ($df->field_type === 'checklist') : ?>
                                        <fieldset>
                                            <legend><?php echo $df_label; ?></legend>
                                            <?php echo $df_desc; ?>
                                            <?php foreach ($df_opts as $opt) : ?>
                                                <label class="mj-checkbox">
                                                    <input type="checkbox" name="<?php echo $df_name; ?>[]" value="<?php echo esc_attr($opt); ?>" <?php checked(in_array($opt, $df_checked_arr, true)); ?> <?php echo $is_preview ? 'disabled="disabled"' : ''; ?> />
                                                    <span><?php echo esc_html($opt); ?></span>
                                                </label>
                                            <?php endforeach; ?>
                                            <?php if ($df_allow_other) : ?>
                                                <label class="mj-checkbox">
                                                    <input type="checkbox" name="<?php echo $df_name; ?>[]" value="__other" <?php checked($df_is_other); ?> <?php echo $is_preview ? 'disabled="disabled"' : ''; ?> />
                                                    <span><?php echo esc_html($df_other_label); ?></span>
                                                </label>
                                                <input type="text" class="mj-dynfield-other-input" name="<?php echo $df_name; ?>_other" value="<?php echo esc_attr($df_other_text); ?>" placeholder="Précisez…" <?php echo $is_preview ? 'disabled="disabled"' : ''; ?> style="<?php echo $df_is_other ? '' : 'display:none;'; ?> margin-top:4px;" />
                                            <?php endif; ?>
                                        </fieldset>
                                    <?php elseif ($df->field_type === 'checkbox') : ?>
                                        <label class="mj-checkbox">
                                            <input type="checkbox" name="<?php echo $df_name; ?>" value="1" <?php checked($df_val, '1'); ?> <?php echo $is_preview ? 'disabled="disabled"' : ''; ?> <?php echo $df_req ? 'required' : ''; ?> />
                                            <span><?php echo $df_label; ?></span>
                                        </label>
                                        <?php echo $df_desc; ?>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </fieldset>
                        <?php endif; ?>

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

                        <?php if (!$is_preview) : ?>
                        <div class="mj-account-danger-zone">
                            <h4 class="mj-account-danger-zone__title"><?php esc_html_e('Zone de danger', 'mj-member'); ?></h4>
                            <p class="mj-account-danger-zone__text">
                                <?php esc_html_e('La suppression de votre compte est irréversible. Toutes vos données personnelles seront effacées.', 'mj-member'); ?>
                            </p>
                            <button type="button" class="mj-button mj-button--danger" data-mj-member-delete-trigger>
                                <?php esc_html_e('Supprimer mon compte', 'mj-member'); ?>
                            </button>
                        </div>
                        <?php endif; ?>
                    </form>

                    <!-- Modal de confirmation de suppression -->
                    <div id="mj-member-delete-modal" class="mj-modal" hidden aria-labelledby="mj-member-delete-modal-title" aria-modal="true" role="dialog">
                        <div class="mj-modal__backdrop" data-mj-member-delete-dismiss></div>
                        <div class="mj-modal__dialog">
                            <header class="mj-modal__header">
                                <h3 id="mj-member-delete-modal-title" class="mj-modal__title"><?php esc_html_e('Confirmer la suppression', 'mj-member'); ?></h3>
                                <button type="button" class="mj-modal__close" data-mj-member-delete-dismiss aria-label="<?php esc_attr_e('Fermer', 'mj-member'); ?>">&times;</button>
                            </header>
                            <div class="mj-modal__body">
                                <p><?php esc_html_e('Êtes-vous sûr de vouloir supprimer votre compte ? Cette action est irréversible.', 'mj-member'); ?></p>
                                <ul>
                                    <li><?php esc_html_e('Votre profil membre sera désactivé', 'mj-member'); ?></li>
                                    <li><?php esc_html_e('Votre compte utilisateur WordPress sera supprimé', 'mj-member'); ?></li>
                                    <li><?php esc_html_e('Vous serez désinscrit de toutes les communications', 'mj-member'); ?></li>
                                </ul>
                            </div>
                            <footer class="mj-modal__footer">
                                <button type="button" class="mj-button mj-button--secondary" data-mj-member-delete-dismiss>
                                    <?php esc_html_e('Annuler', 'mj-member'); ?>
                                </button>
                                <form method="post" class="mj-account-delete-form">
                                    <?php wp_nonce_field('mj_member_account_delete', 'mj_member_account_delete_nonce'); ?>
                                    <input type="hidden" name="mj_member_account_action" value="delete_account">
                                    <button type="submit" class="mj-button mj-button--danger">
                                        <?php esc_html_e('Oui, supprimer mon compte', 'mj-member'); ?>
                                    </button>
                                </form>
                            </footer>
                        </div>
                    </div>
                </section>

                <?php if ($has_children_tabs) : foreach ($children_payload as $tab_child) :
                    $tc_status = $tab_child['status'];
                    $tc_label = $tab_child['status_label'];
                    $tc_desc = isset($tab_child['description']) ? $tab_child['description'] : '';
                    $tc_profile = $tab_child['profile'];
                    $tc_birth = !empty($tc_profile['birth_date']) ? $tc_profile['birth_date'] : '';
                    $tc_email = !empty($tc_profile['email']) ? $tc_profile['email'] : '';
                    $tc_phone = !empty($tc_profile['phone']) ? $tc_profile['phone'] : '';
                    $tc_notes = !empty($tc_profile['notes']) ? $tc_profile['notes'] : '';
                    $tc_autonomous = !empty($tc_profile['is_autonomous']);
                    $tc_photo_consent = !empty($tc_profile['photo_usage_consent']);
                    $tc_photo_url = !empty($tab_child['photo']['url']) ? $tab_child['photo']['url'] : '';
                    $tc_photo_id = isset($tab_child['photo']['id']) ? (int) $tab_child['photo']['id'] : 0;
                    $tc_full_name = $tab_child['full_name'] !== '' ? $tab_child['full_name'] : __('Jeune', 'mj-member');
                    $tc_expires = isset($tab_child['expires_display']) ? $tab_child['expires_display'] : '';
                    $tc_last_payment = isset($tab_child['last_payment_display']) ? $tab_child['last_payment_display'] : '';
                    $tc_requires_payment = !empty($tab_child['requires_payment']);
                    $tc_id = (int) $tab_child['id'];
                    $tc_form_id = 'mj-child-form-' . $tc_id;
                    $tc_prefix = 'mj-child-' . $tc_id;
                    $tc_has_photo = ($tc_photo_url !== '');
                    $tc_photo_input_id_tab = $tc_prefix . '-photo';
                    $tc_photo_label_id_tab = $tc_photo_input_id_tab . '-label';
                    $tc_photo_hint_id_tab = $tc_photo_input_id_tab . '-hint';
                    $tc_photo_trigger_id_tab = $tc_photo_input_id_tab . '-trigger';
                    $tc_upload_default = $tc_has_photo ? __('Photo actuelle enregistrée', 'mj-member') : __('Aucun fichier sélectionné', 'mj-member');
                ?>
                <section class="mj-account-card mj-account-card--child-detail" data-mj-tab-panel="child-<?php echo esc_attr((string) $tc_id); ?>" hidden>
                    <header class="mj-account-card__header">
                        <div class="mj-account-card__header-main">
                            <h2 class="mj-account-card__title">👶 <?php echo esc_html($tc_full_name); ?></h2>
                        </div>
                        <?php if ($tc_label !== '') : ?>
                            <span class="mj-account-chip mj-account-chip--<?php echo esc_attr($tc_status); ?>"><?php echo esc_html($tc_label); ?></span>
                        <?php endif; ?>
                    </header>

                    <div class="mj-account-children__feedback" data-mj-child-tab-feedback="<?php echo esc_attr((string) $tc_id); ?>" role="status" aria-live="polite" hidden></div>

                    <form method="post" enctype="multipart/form-data" class="mj-account-form" id="<?php echo esc_attr($tc_form_id); ?>" data-mj-child-tab-form="<?php echo esc_attr((string) $tc_id); ?>">
                        <input type="hidden" name="child_id" value="<?php echo esc_attr((string) $tc_id); ?>" />

                        <fieldset class="mj-fieldset">
                            <legend><?php esc_html_e('Informations personnelles', 'mj-member'); ?></legend>
                            <div class="mj-field-grid">
                                <div class="mj-field-group">
                                    <label for="<?php echo esc_attr($tc_prefix); ?>-first-name"><?php esc_html_e('Prénom', 'mj-member'); ?></label>
                                    <input type="text" id="<?php echo esc_attr($tc_prefix); ?>-first-name" name="first_name" value="<?php echo esc_attr($tc_profile['first_name']); ?>" autocomplete="given-name" <?php echo $is_preview ? 'disabled="disabled"' : 'required'; ?> />
                                </div>
                                <div class="mj-field-group">
                                    <label for="<?php echo esc_attr($tc_prefix); ?>-last-name"><?php esc_html_e('Nom', 'mj-member'); ?></label>
                                    <input type="text" id="<?php echo esc_attr($tc_prefix); ?>-last-name" name="last_name" value="<?php echo esc_attr($tc_profile['last_name']); ?>" autocomplete="family-name" <?php echo $is_preview ? 'disabled="disabled"' : 'required'; ?> />
                                </div>
                                <div class="mj-field-group">
                                    <label for="<?php echo esc_attr($tc_prefix); ?>-email"><?php esc_html_e('Email', 'mj-member'); ?></label>
                                    <input type="email" id="<?php echo esc_attr($tc_prefix); ?>-email" name="email" value="<?php echo esc_attr($tc_email); ?>" autocomplete="email" <?php echo $is_preview ? 'disabled="disabled"' : ''; ?> />
                                </div>
                                <div class="mj-field-group">
                                    <label for="<?php echo esc_attr($tc_prefix); ?>-phone"><?php esc_html_e('Téléphone', 'mj-member'); ?></label>
                                    <input type="tel" id="<?php echo esc_attr($tc_prefix); ?>-phone" name="phone" value="<?php echo esc_attr($tc_phone); ?>" autocomplete="tel" <?php echo $is_preview ? 'disabled="disabled"' : ''; ?> />
                                </div>
                                <div class="mj-field-group">
                                    <label for="<?php echo esc_attr($tc_prefix); ?>-birth-date"><?php esc_html_e('Date de naissance', 'mj-member'); ?></label>
                                    <input type="date" id="<?php echo esc_attr($tc_prefix); ?>-birth-date" name="birth_date" value="<?php echo esc_attr($tc_birth); ?>" autocomplete="bday" <?php echo $is_preview ? 'disabled="disabled"' : ''; ?> />
                                </div>
                                <div class="mj-field-group mj-field-group--full">
                                    <label for="<?php echo esc_attr($tc_prefix); ?>-notes"><?php esc_html_e('Informations complémentaires à transmettre à nos animateurs', 'mj-member'); ?></label>
                                    <textarea id="<?php echo esc_attr($tc_prefix); ?>-notes" name="notes" rows="3" <?php echo $is_preview ? 'disabled="disabled"' : ''; ?>><?php echo esc_textarea($tc_notes); ?></textarea>
                                </div>
                            </div>
                        </fieldset>

                        <?php
                        // Dynamic fields (youth-only) for this child
                        $tc_dyn_fields = \Mj\Member\Classes\Crud\MjDynamicFields::getAccountFields();
                        $tc_youth_fields = array();
                        if (!empty($tc_dyn_fields)) {
                            foreach ($tc_dyn_fields as $tdf) {
                                if (!empty($tdf->youth_only)) {
                                    $tc_youth_fields[] = $tdf;
                                }
                            }
                        }
                        if (!empty($tc_youth_fields)) :
                            $tc_dyn_vals = $is_preview ? array() : \Mj\Member\Classes\Crud\MjDynamicFieldValues::getByMemberKeyed($tc_id);
                        ?>
                        <fieldset class="mj-fieldset">
                            <div class="mj-field-grid mj-field-grid--dynfields">
                                <?php foreach ($tc_youth_fields as $df) :
                                    $df_id   = (int) $df->id;
                                    $df_name = 'dynfield_' . $df_id;
                                    $df_html = esc_attr($tc_prefix . '-dynfield-' . $df_id);
                                    $df_req  = (int) $df->is_required;
                                    $df_val  = $tc_dyn_vals[$df_id] ?? '';
                                    $df_opts = \Mj\Member\Classes\Crud\MjDynamicFields::decodeOptions($df->options_list);
                                    $df_label = esc_html($df->title) . ($df_req ? ' *' : '');
                                    $df_desc  = $df->description ? '<small class="mj-field-hint">' . esc_html($df->description) . '</small>' : '';

                                    // Section title
                                    if ($df->field_type === 'title') : ?>
                                        </div>
                                        <h5 class="mj-dynfield-section-title"><?php echo esc_html($df->title); ?></h5>
                                        <?php if ($df->description) : ?>
                                            <p class="mj-dynfield-section-desc"><?php echo esc_html($df->description); ?></p>
                                        <?php endif; ?>
                                        <div class="mj-field-grid--dynfields">
                                    <?php continue; endif; ?>
                                <?php
                                    $df_allow_other = (int) ($df->allow_other ?? 0);
                                    $df_other_label = ($df->other_label ?? '') !== '' ? $df->other_label : 'Autre';
                                    $df_is_other    = false;
                                    $df_other_text  = '';
                                    $df_checked_arr = array();

                                    if ($df->field_type === 'checklist') {
                                        $df_checked_arr = is_string($df_val) && $df_val !== '' ? json_decode($df_val, true) : array();
                                        if (!is_array($df_checked_arr)) $df_checked_arr = array();
                                        foreach ($df_checked_arr as $ck => $cv) {
                                            if (strpos($cv, '__other:') === 0) {
                                                $df_other_text = substr($cv, 8);
                                                $df_is_other = true;
                                                unset($df_checked_arr[$ck]);
                                                break;
                                            }
                                        }
                                    } elseif (in_array($df->field_type, array('dropdown', 'radio'), true) && $df_allow_other) {
                                        if (strpos($df_val, '__other:') === 0) {
                                            $df_other_text = substr($df_val, 8);
                                            $df_is_other = true;
                                        }
                                    }
                                ?>
                                <div class="mj-field-group mj-field-group--dyn<?php echo in_array($df->field_type, array('textarea', 'checklist'), true) ? ' mj-field-group--full' : ''; ?>">
                                    <?php if ($df->field_type === 'text') : ?>
                                        <label for="<?php echo $df_html; ?>"><?php echo $df_label; ?></label>
                                        <?php echo $df_desc; ?>
                                        <input type="text" id="<?php echo $df_html; ?>" name="<?php echo $df_name; ?>" value="<?php echo esc_attr($df_val); ?>" <?php echo $is_preview ? 'disabled="disabled"' : ''; ?> <?php echo $df_req ? 'required' : ''; ?> />
                                    <?php elseif ($df->field_type === 'textarea') : ?>
                                        <label for="<?php echo $df_html; ?>"><?php echo $df_label; ?></label>
                                        <?php echo $df_desc; ?>
                                        <textarea id="<?php echo $df_html; ?>" name="<?php echo $df_name; ?>" rows="3" <?php echo $is_preview ? 'disabled="disabled"' : ''; ?> <?php echo $df_req ? 'required' : ''; ?>><?php echo esc_textarea($df_val); ?></textarea>
                                    <?php elseif ($df->field_type === 'dropdown') : ?>
                                        <label for="<?php echo $df_html; ?>"><?php echo $df_label; ?></label>
                                        <?php echo $df_desc; ?>
                                        <select id="<?php echo $df_html; ?>" name="<?php echo $df_name; ?>" <?php echo $is_preview ? 'disabled="disabled"' : ''; ?> <?php echo $df_req ? 'required' : ''; ?> <?php echo $df_allow_other ? 'data-dynfield-other="1"' : ''; ?>>
                                            <option value="">&mdash; Sélectionnez &mdash;</option>
                                            <?php foreach ($df_opts as $opt) : ?>
                                                <option value="<?php echo esc_attr($opt); ?>" <?php selected(!$df_is_other ? $df_val : '', $opt); ?>><?php echo esc_html($opt); ?></option>
                                            <?php endforeach; ?>
                                            <?php if ($df_allow_other) : ?>
                                                <option value="__other" <?php selected($df_is_other); ?>><?php echo esc_html($df_other_label); ?>…</option>
                                            <?php endif; ?>
                                        </select>
                                        <?php if ($df_allow_other) : ?>
                                            <input type="text" class="mj-dynfield-other-input" name="<?php echo $df_name; ?>_other" value="<?php echo esc_attr($df_other_text); ?>" placeholder="Précisez…" <?php echo $is_preview ? 'disabled="disabled"' : ''; ?> style="<?php echo $df_is_other ? '' : 'display:none;'; ?> margin-top:6px;" />
                                        <?php endif; ?>
                                    <?php elseif ($df->field_type === 'radio') : ?>
                                        <fieldset>
                                            <legend><?php echo $df_label; ?></legend>
                                            <?php echo $df_desc; ?>
                                            <?php foreach ($df_opts as $oi => $opt) : ?>
                                                <label class="mj-radio">
                                                    <input type="radio" name="<?php echo $df_name; ?>" value="<?php echo esc_attr($opt); ?>" <?php checked(!$df_is_other ? $df_val : '', $opt); ?> <?php echo $is_preview ? 'disabled="disabled"' : ''; ?> <?php echo ($df_req && $oi === 0 && !$df_allow_other) ? 'required' : ''; ?> />
                                                    <span><?php echo esc_html($opt); ?></span>
                                                </label>
                                            <?php endforeach; ?>
                                            <?php if ($df_allow_other) : ?>
                                                <label class="mj-radio">
                                                    <input type="radio" name="<?php echo $df_name; ?>" value="__other" <?php checked($df_is_other); ?> <?php echo $is_preview ? 'disabled="disabled"' : ''; ?> />
                                                    <span><?php echo esc_html($df_other_label); ?></span>
                                                </label>
                                                <input type="text" class="mj-dynfield-other-input" name="<?php echo $df_name; ?>_other" value="<?php echo esc_attr($df_other_text); ?>" placeholder="Précisez…" <?php echo $is_preview ? 'disabled="disabled"' : ''; ?> style="<?php echo $df_is_other ? '' : 'display:none;'; ?> margin-top:4px;" />
                                            <?php endif; ?>
                                        </fieldset>
                                    <?php elseif ($df->field_type === 'checklist') : ?>
                                        <fieldset>
                                            <legend><?php echo $df_label; ?></legend>
                                            <?php echo $df_desc; ?>
                                            <?php foreach ($df_opts as $opt) : ?>
                                                <label class="mj-checkbox">
                                                    <input type="checkbox" name="<?php echo $df_name; ?>[]" value="<?php echo esc_attr($opt); ?>" <?php checked(in_array($opt, $df_checked_arr, true)); ?> <?php echo $is_preview ? 'disabled="disabled"' : ''; ?> />
                                                    <span><?php echo esc_html($opt); ?></span>
                                                </label>
                                            <?php endforeach; ?>
                                            <?php if ($df_allow_other) : ?>
                                                <label class="mj-checkbox">
                                                    <input type="checkbox" name="<?php echo $df_name; ?>[]" value="__other" <?php checked($df_is_other); ?> <?php echo $is_preview ? 'disabled="disabled"' : ''; ?> />
                                                    <span><?php echo esc_html($df_other_label); ?></span>
                                                </label>
                                                <input type="text" class="mj-dynfield-other-input" name="<?php echo $df_name; ?>_other" value="<?php echo esc_attr($df_other_text); ?>" placeholder="Précisez…" <?php echo $is_preview ? 'disabled="disabled"' : ''; ?> style="<?php echo $df_is_other ? '' : 'display:none;'; ?> margin-top:4px;" />
                                            <?php endif; ?>
                                        </fieldset>
                                    <?php elseif ($df->field_type === 'checkbox') : ?>
                                        <label class="mj-checkbox">
                                            <input type="checkbox" name="<?php echo $df_name; ?>" value="1" <?php checked($df_val, '1'); ?> <?php echo $is_preview ? 'disabled="disabled"' : ''; ?> <?php echo $df_req ? 'required' : ''; ?> />
                                            <span><?php echo $df_label; ?></span>
                                        </label>
                                        <?php echo $df_desc; ?>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </fieldset>
                        <?php endif; ?>

                        <div class="mj-account-photo-field">
                            <div class="mj-account-photo-control">
                                <div class="mj-account-photo-control__preview">
                                    <?php if ($tc_has_photo) : ?>
                                        <img src="<?php echo esc_url($tc_photo_url); ?>" alt="<?php echo esc_attr($tc_full_name); ?>" />
                                    <?php else : ?>
                                        <span class="mj-account-photo-control__placeholder">👤</span>
                                    <?php endif; ?>
                                </div>
                                <div class="mj-account-photo-control__fields">
                                    <div class="mj-field-group mj-account-photo-control__upload">
                                        <span id="<?php echo esc_attr($tc_photo_label_id_tab); ?>" class="mj-account-photo-control__label"><?php esc_html_e('Photo du jeune', 'mj-member'); ?></span>
                                        <div class="mj-account-upload" data-mj-account-upload>
                                            <input type="file" id="<?php echo esc_attr($tc_photo_input_id_tab); ?>" class="mj-account-upload__input" name="child_photo" accept="image/*" aria-labelledby="<?php echo esc_attr($tc_photo_label_id_tab . ' ' . $tc_photo_trigger_id_tab); ?>" aria-describedby="<?php echo esc_attr($tc_photo_hint_id_tab); ?>" <?php echo $is_preview ? 'disabled="disabled"' : ''; ?> />
                                            <label class="mj-account-upload__trigger" id="<?php echo esc_attr($tc_photo_trigger_id_tab); ?>" for="<?php echo esc_attr($tc_photo_input_id_tab); ?>"<?php echo $is_preview ? ' aria-disabled="true" tabindex="-1"' : ''; ?>>
                                                <span class="mj-account-upload__icon" aria-hidden="true">📷</span>
                                                <span class="mj-account-upload__text"><?php esc_html_e('Choisir une image', 'mj-member'); ?></span>
                                            </label>
                                            <div class="mj-account-upload__meta">
                                                <span class="mj-account-upload__filename" data-default="<?php echo esc_attr($tc_upload_default); ?>"><?php echo esc_html($tc_upload_default); ?></span>
                                                <span class="mj-account-upload__hint" id="<?php echo esc_attr($tc_photo_hint_id_tab); ?>"><?php esc_html_e('Formats acceptés : JPG ou PNG, 5 Mo max.', 'mj-member'); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    <?php if ($is_preview) : ?>
                                        <p class="mj-account-photo-control__remove"><?php esc_html_e('Modification désactivée en aperçu.', 'mj-member'); ?></p>
                                    <?php else : ?>
                                        <label class="mj-account-photo-control__remove">
                                            <input type="checkbox" name="photo_remove" value="1" />
                                            <span><?php esc_html_e('Supprimer la photo actuelle', 'mj-member'); ?></span>
                                        </label>
                                    <?php endif; ?>
                                    <input type="hidden" name="photo_id_existing" value="<?php echo esc_attr((string) $tc_photo_id); ?>" />
                                </div>
                            </div>
                        </div>

                        <fieldset class="mj-fieldset">
                            <legend><?php esc_html_e('Autorisations', 'mj-member'); ?></legend>
                            <div class="mj-consent-list">
                                <label class="mj-checkbox">
                                    <input type="checkbox" name="is_autonomous" value="1" <?php checked($tc_autonomous); ?> <?php echo $is_preview ? 'disabled="disabled"' : ''; ?> />
                                    <span><?php esc_html_e('Autorisation de sortie autonome', 'mj-member'); ?></span>
                                </label>
                                <label class="mj-checkbox">
                                    <input type="checkbox" name="photo_usage_consent" value="1" <?php checked($tc_photo_consent); ?> <?php echo $is_preview ? 'disabled="disabled"' : ''; ?> />
                                    <span><?php esc_html_e('J\'autorise l\'utilisation des photos sur les supports MJ.', 'mj-member'); ?></span>
                                </label>
                            </div>
                        </fieldset>

                        <div class="mj-form-actions">
                            <button type="submit" class="mj-button" <?php echo $is_preview ? 'disabled="disabled"' : ''; ?>>
                                <?php esc_html_e('Enregistrer', 'mj-member'); ?>
                            </button>
                        </div>
                    </form>
                </section>
                <?php endforeach; endif; ?>

                <?php if (!empty($options['show_children'])) : ?>
                    <section class="mj-account-card mj-account-card--children<?php echo $has_children_tabs ? ' mj-account-card--children-tabbed' : ''; ?>" data-mj-member-children-section>
                        <header class="mj-account-card__header">
                            <h3 class="mj-account-card__title"><?php esc_html_e('Mes jeunes rattachés', 'mj-member'); ?></h3>
                            <button
                                type="button"
                                class="mj-button mj-button--secondary mj-account-children__add"
                                data-mj-member-child-add
                                <?php echo $is_preview ? 'disabled="disabled" aria-disabled="true"' : ''; ?>
                                aria-controls="mj-member-child-modal">
                                <span class="mj-account-children__add-icon" aria-hidden="true">+</span>
                                <span><?php esc_html_e('Ajouter un jeune', 'mj-member'); ?></span>
                            </button>
                        </header>
                        <div class="mj-account-children__feedback" data-mj-member-child-feedback role="status" aria-live="polite" hidden></div>
                        <?php if (!empty($children_statuses)) : ?>
                            <div class="mj-account-children" data-mj-member-children>
                                <?php foreach ($children_statuses as $child) :
                                    $child_status = isset($child['status']) ? sanitize_key((string) $child['status']) : 'unknown';
                                    $child_label = isset($child['status_label']) ? sanitize_text_field((string) $child['status_label']) : '';
                                    $child_description = isset($child['description']) ? sanitize_text_field((string) $child['description']) : '';
                                    $child_profile = isset($child['profile']) && is_array($child['profile']) ? $child['profile'] : array();
                                    $child_birth = isset($child_profile['birth_date']) ? mj_member_account_normalize_birth_date((string) $child_profile['birth_date']) : '';
                                    $child_phone = isset($child_profile['phone']) ? sanitize_text_field((string) $child_profile['phone']) : '';
                                    $child_email = isset($child_profile['email']) ? sanitize_email((string) $child_profile['email']) : '';
                                    $child_notes = isset($child_profile['notes']) ? sanitize_textarea_field((string) $child_profile['notes']) : '';
                                    $child_autonomous = !empty($child_profile['is_autonomous']);
                                    $child_photo_consent = !empty($child_profile['photo_usage_consent']);
                                    $child_id = isset($child['id']) ? (int) $child['id'] : 0;
                                    $child_photo = isset($child['photo']) && is_array($child['photo']) ? $child['photo'] : array();
                                    $child_photo_url = isset($child_photo['url']) ? esc_url((string) $child_photo['url']) : '';
                                    ?>
                                    <article
                                        class="mj-account-child-card mj-account-child-card--<?php echo esc_attr($child_status); ?>"
                                        data-mj-member-child
                                        data-mj-child-id="<?php echo esc_attr((string) $child_id); ?>"
                                        data-mj-child-status="<?php echo esc_attr($child_status); ?>">
                                        <div class="mj-account-child-card__layout">
                                            <div class="mj-account-child-card__media">
                                                <?php if ($child_photo_url !== '') : ?>
                                                    <img src="<?php echo esc_url($child_photo_url); ?>" alt="<?php echo esc_attr(isset($child['full_name']) ? (string) $child['full_name'] : ''); ?>" />
                                                <?php else : ?>
                                                    <span class="mj-account-child-card__placeholder" aria-hidden="true">👤</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="mj-account-child-card__content">
                                                <div class="mj-account-child-card__header">
                                                    <div class="mj-account-child-card__heading">
                                                        <h4 class="mj-account-child-card__title"><?php echo esc_html(isset($child['full_name']) ? (string) $child['full_name'] : \Mj\Member\Classes\MjRoles::getRoleLabel(\Mj\Member\Classes\MjRoles::JEUNE)); ?></h4>
                                                        <?php if ($child_label !== '') : ?>
                                                            <span class="mj-account-chip mj-account-chip--<?php echo esc_attr($child_status); ?>"><?php echo esc_html($child_label); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <button
                                                        type="button"
                                                        class="mj-button mj-button--ghost mj-account-child-card__edit"
                                                        data-mj-member-child-edit
                                                        data-child-id="<?php echo esc_attr((string) $child_id); ?>"
                                                        <?php echo $is_preview ? 'disabled="disabled" aria-disabled="true"' : ''; ?>>
                                                        <?php esc_html_e('Modifier', 'mj-member'); ?>
                                                    </button>
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
                                                    <?php if ($child_autonomous) : ?>
                                                        <li><?php esc_html_e('Autorisé à rentrer seul', 'mj-member'); ?></li>
                                                    <?php endif; ?>
                                                    <?php if ($child_photo_consent) : ?>
                                                        <li><?php esc_html_e('Autorisation photo accordée', 'mj-member'); ?></li>
                                                    <?php endif; ?>
                                                </ul>
                                                <?php if ($child_notes !== '') : ?>
                                                    <p class="mj-account-child-card__notes"><strong><?php esc_html_e('Notes :', 'mj-member'); ?></strong> <?php echo esc_html($child_notes); ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php else : ?>
                            <p class="mj-account-empty" data-mj-member-children-empty><?php esc_html_e('Aucun jeune rattaché pour le moment.', 'mj-member'); ?></p>
                        <?php endif; ?>
                    </section>
                    <div
                        class="mj-modal"
                        data-mj-member-child-modal
                        id="mj-member-child-modal"
                        hidden
                        aria-hidden="true">
                        <div class="mj-modal__backdrop" data-mj-member-child-close></div>
                        <div class="mj-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="mj-member-child-modal-title">
                            <button type="button" class="mj-modal__close" data-mj-member-child-close <?php echo $is_preview ? 'disabled="disabled" aria-disabled="true"' : ''; ?>>
                                <span class="screen-reader-text"><?php esc_html_e('Fermer', 'mj-member'); ?></span>
                                <span aria-hidden="true">×</span>
                            </button>
                            <div class="mj-modal__header">
                                <h2 class="mj-modal__title" id="mj-member-child-modal-title" data-mj-member-child-title></h2>
                            </div>
                            <div class="mj-modal__body">
                                <form class="mj-modal-form" data-mj-member-child-form novalidate enctype="multipart/form-data">
                                    <input type="hidden" name="child_id" value="" data-mj-child-field="child_id" />
                                    <div class="mj-modal__messages" data-mj-member-child-errors hidden aria-live="assertive"></div>
                                    <div class="mj-modal__photo">
                                        <div class="mj-account-photo-control mj-account-photo-control--modal">
                                            <div class="mj-account-photo-control__preview" data-mj-child-photo-preview>
                                                <img src="" alt="" data-mj-child-photo-image hidden />
                                                <span class="mj-account-photo-control__placeholder" data-mj-child-photo-placeholder>👤</span>
                                            </div>
                                            <div class="mj-account-photo-control__fields">
                                                <div class="mj-field-group mj-account-photo-control__upload">
                                                    <span id="<?php echo esc_attr($child_photo_label_id); ?>" class="mj-account-photo-control__label"><?php esc_html_e('Photo du jeune', 'mj-member'); ?></span>
                                                    <div class="mj-account-upload" data-mj-account-upload>
                                                        <input
                                                            type="file"
                                                            id="<?php echo esc_attr($child_photo_input_id); ?>"
                                                            class="mj-account-upload__input"
                                                            name="child_photo"
                                                            data-mj-child-photo-input
                                                            accept="image/*"
                                                            aria-labelledby="<?php echo esc_attr($child_photo_label_id . ' ' . $child_photo_trigger_id); ?>"
                                                            aria-describedby="<?php echo esc_attr($child_photo_hint_id); ?>"
                                                            <?php echo $is_preview ? 'disabled="disabled"' : ''; ?> />
                                                        <label
                                                            class="mj-account-upload__trigger"
                                                            id="<?php echo esc_attr($child_photo_trigger_id); ?>"
                                                            for="<?php echo esc_attr($child_photo_input_id); ?>"<?php echo $is_preview ? ' aria-disabled="true" tabindex="-1"' : ''; ?>>
                                                            <span class="mj-account-upload__icon" aria-hidden="true">📷</span>
                                                            <span class="mj-account-upload__text"><?php esc_html_e('Choisir une image', 'mj-member'); ?></span>
                                                        </label>
                                                        <div class="mj-account-upload__meta">
                                                            <span class="mj-account-upload__filename" data-mj-child-photo-filename data-default="<?php echo esc_attr($child_photo_default_text); ?>"><?php echo esc_html($child_photo_default_text); ?></span>
                                                            <span class="mj-account-upload__hint" id="<?php echo esc_attr($child_photo_hint_id); ?>"><?php esc_html_e('Formats acceptés : JPG ou PNG, 5 Mo max.', 'mj-member'); ?></span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php if ($is_preview) : ?>
                                                    <p class="mj-account-photo-control__remove"><?php esc_html_e('Modification désactivée en aperçu.', 'mj-member'); ?></p>
                                                <?php else : ?>
                                                    <label class="mj-account-photo-control__remove">
                                                        <input type="checkbox" name="photo_remove" value="1" data-mj-child-field="photo_remove" />
                                                        <span><?php esc_html_e('Supprimer la photo actuelle', 'mj-member'); ?></span>
                                                    </label>
                                                <?php endif; ?>
                                                <input type="hidden" name="photo_id_existing" value="" data-mj-child-field="photo_id_existing" />
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mj-field-grid mj-field-grid--modal">
                                        <div class="mj-field-group">
                                            <label for="mj-member-child-first-name"><?php esc_html_e('Prénom', 'mj-member'); ?></label>
                                            <input
                                                type="text"
                                                id="mj-member-child-first-name"
                                                name="first_name"
                                                data-mj-child-field="first_name"
                                                autocomplete="given-name"
                                                <?php echo $is_preview ? 'disabled="disabled"' : 'required'; ?> />
                                        </div>
                                        <div class="mj-field-group">
                                            <label for="mj-member-child-last-name"><?php esc_html_e('Nom', 'mj-member'); ?></label>
                                            <input
                                                type="text"
                                                id="mj-member-child-last-name"
                                                name="last_name"
                                                data-mj-child-field="last_name"
                                                autocomplete="family-name"
                                                <?php echo $is_preview ? 'disabled="disabled"' : 'required'; ?> />
                                        </div>
                                        <div class="mj-field-group">
                                            <label for="mj-member-child-email"><?php esc_html_e('Email', 'mj-member'); ?></label>
                                            <input
                                                type="email"
                                                id="mj-member-child-email"
                                                name="email"
                                                data-mj-child-field="email"
                                                autocomplete="email"
                                                <?php echo $is_preview ? 'disabled="disabled"' : ''; ?> />
                                        </div>
                                        <div class="mj-field-group">
                                            <label for="mj-member-child-phone"><?php esc_html_e('Téléphone', 'mj-member'); ?></label>
                                            <input
                                                type="tel"
                                                id="mj-member-child-phone"
                                                name="phone"
                                                data-mj-child-field="phone"
                                                autocomplete="tel"
                                                <?php echo $is_preview ? 'disabled="disabled"' : ''; ?> />
                                        </div>
                                        <div class="mj-field-group">
                                            <label for="mj-member-child-birth-date"><?php esc_html_e('Date de naissance', 'mj-member'); ?></label>
                                            <input
                                                type="date"
                                                id="mj-member-child-birth-date"
                                                name="birth_date"
                                                data-mj-child-field="birth_date"
                                                autocomplete="bday"
                                                <?php echo $is_preview ? 'disabled="disabled"' : ''; ?> />
                                        </div>
                                        <div class="mj-field-group mj-field-group--full">
                                            <label for="mj-member-child-notes"><?php esc_html_e('Notes', 'mj-member'); ?></label>
                                            <textarea
                                                id="mj-member-child-notes"
                                                name="notes"
                                                rows="3"
                                                data-mj-child-field="notes"
                                                <?php echo $is_preview ? 'disabled="disabled"' : ''; ?>></textarea>
                                        </div>
                                    </div>
                                    <div class="mj-modal__options">
                                        <label class="mj-modal-checkbox">
                                            <input type="checkbox" name="is_autonomous" value="1" data-mj-child-field="is_autonomous" <?php echo $is_preview ? 'disabled="disabled"' : ''; ?> />
                                            <span><?php esc_html_e('Autorisation de sortie autonome', 'mj-member'); ?></span>
                                        </label>
                                        <label class="mj-modal-checkbox">
                                            <input type="checkbox" name="photo_usage_consent" value="1" data-mj-child-field="photo_usage_consent" <?php echo $is_preview ? 'disabled="disabled"' : ''; ?> />
                                            <span><?php esc_html_e('Autorisation photo accordée', 'mj-member'); ?></span>
                                        </label>
                                    </div>
                                    <div class="mj-modal__footer">
                                        <button type="button" class="mj-button mj-button--ghost mj-modal__cancel" data-mj-member-child-cancel <?php echo $is_preview ? 'disabled="disabled"' : ''; ?>>
                                            <?php esc_html_e('Annuler', 'mj-member'); ?>
                                        </button>
                                        <button type="submit" class="mj-button mj-modal__submit" data-mj-member-child-submit <?php echo $is_preview ? 'disabled="disabled"' : ''; ?>>
                                            <?php esc_html_e('Enregistrer', 'mj-member'); ?>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
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

            .mj-hidden {
                display: none !important;
            }

            #mj-children-wrapper {
                display: grid;
                gap: 24px;
            }

            .mj-child-card {
                background: rgba(255, 255, 255, 0.96);
                border: 1px solid rgba(148, 163, 184, 0.28);
                border-radius: 16px;
                padding: 24px;
                box-shadow: 0 20px 40px rgba(15, 23, 42, 0.14);
            }

            .mj-child-card__header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                margin-bottom: 18px;
            }

            .mj-child-card__title {
                margin: 0;
                font-size: 20px;
                font-weight: 700;
                color: #0f172a;
            }

            .mj-child-card__remove {
                border: none;
                background: transparent;
                color: #ef4444;
                font-size: 22px;
                cursor: pointer;
                transition: transform 0.2s ease, color 0.2s ease;
            }

            .mj-child-card__remove:hover {
                color: #b91c1c;
                transform: scale(1.08);
            }

            .mj-child-card__grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 22px;
                margin-bottom: 20px;
            }

            .mj-child-card__dynfields {
                grid-template-columns: 1fr;
            }

            .mj-dynfield-other-input {
                display: block;
                width: 100%;
                max-width: 400px;
                padding: 6px 10px;
                font-size: 14px;
                border: 1px solid #d1d5db;
                border-radius: 6px;
                box-sizing: border-box;
                margin-top: 6px;
            }

            .mj-child-card__options {
                display: flex;
                flex-direction: column;
                gap: 12px;
                margin-bottom: 20px;
            }

            .mj-button {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 10px;
                padding: 14px 28px;
                border-radius: 12px;
                border: none;
                background: var(--mj-accent);
                color: #fff;
                font-size: 16px;
                font-weight: 700;
                cursor: pointer;
                transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
                box-shadow: 0 22px 40px rgba(37, 99, 235, 0.28);
            }

            .mj-button:hover {
                background: var(--mj-accent-dark);
                transform: translateY(-2px);
                box-shadow: 0 26px 46px rgba(37, 99, 235, 0.34);
            }

            .mj-button:active {
                transform: translateY(0);
                box-shadow: 0 18px 34px rgba(37, 99, 235, 0.22);
            }

            .mj-radio {
                display: flex;
                align-items: flex-start;
                gap: 10px;
                margin-bottom: 6px;
                padding: 8px 12px;
                background: rgba(248, 250, 252, 0.8);
                border-radius: 10px;
                border: 1px solid rgba(148, 163, 184, 0.25);
                transition: border-color 0.2s ease, background 0.2s ease;
                cursor: pointer;
            }

            .mj-radio span {
                font-size: 14px;
                font-weight: 400;
                color: #334155;
            }

            .mj-radio input {
                margin-top: 3px;
            }

            .mj-radio:hover {
                border-color: var(--mj-accent);
                background: rgba(37, 99, 235, 0.06);
            }

            /* Dropdown / select styling inside dynamic fields */
            .mj-field-group--dyn select {
                padding: 10px 14px;
                border: 1px solid rgba(47, 82, 143, 0.16);
                border-radius: var(--mj-account-radius-sm, 10px);
                font-size: 14px;
                font-family: inherit;
                background: rgba(255, 255, 255, 0.92) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath d='M6 8L1 3h10z' fill='%2364748b'/%3E%3C/svg%3E") no-repeat right 14px center;
                background-size: 12px;
                appearance: none;
                -webkit-appearance: none;
                color: var(--mj-account-text, #0f172a);
                cursor: pointer;
                transition: border-color 0.2s ease, box-shadow 0.2s ease;
            }

            .mj-field-group--dyn select:focus {
                outline: none;
                border-color: var(--mj-account-accent);
                box-shadow: 0 0 0 3px rgba(47, 82, 143, 0.18);
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

.mj-button--secondary {
    background: rgba(47, 82, 143, 0.12);
    color: var(--mj-account-accent);
    border: 1px solid rgba(47, 82, 143, 0.28);
    box-shadow: none;
    font-weight: 600;
}

.mj-button--secondary:hover,
.mj-button--secondary:focus,
.mj-button--secondary:focus-visible {
    background: rgba(47, 82, 143, 0.2);
    transform: none;
    box-shadow: none;
}

.mj-button--secondary[disabled] {
    opacity: 0.6;
    border-color: rgba(47, 82, 143, 0.18);
}

.mj-button--ghost {
    background: transparent;
    color: var(--mj-account-accent);
    border: 1px solid rgba(47, 82, 143, 0.24);
    box-shadow: none;
    font-weight: 600;
    padding: 10px 18px;
}

.mj-button--ghost:hover,
.mj-button--ghost:focus,
.mj-button--ghost:focus-visible {
    background: rgba(47, 82, 143, 0.08);
    transform: none;
    box-shadow: none;
}

.mj-button--ghost[disabled] {
    opacity: 0.6;
    border-color: rgba(47, 82, 143, 0.18);
    cursor: not-allowed;
}

.mj-button--danger {
    background: #d64545;
    border: 1px solid #d64545;
    color: #ffffff;
    box-shadow: none;
}

.mj-button--danger:hover,
.mj-button--danger:focus,
.mj-button--danger:focus-visible {
    background: #bb3535;
    border-color: #bb3535;
    transform: none;
    box-shadow: 0 12px 20px rgba(214, 69, 69, 0.25);
}

.mj-button--danger[disabled] {
    opacity: 0.65;
    border-color: rgba(214, 69, 69, 0.4);
    box-shadow: none;
    cursor: not-allowed;
}

.mj-account-danger-zone {
    margin-top: 32px;
    padding: 24px;
    border-radius: var(--mj-account-radius);
    border: 1px solid rgba(214, 69, 69, 0.28);
    background: rgba(214, 69, 69, 0.08);
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.mj-account-danger-zone__title {
    font-size: 1.1rem;
    font-weight: 700;
    color: #b83232;
    margin: 0;
}

.mj-account-danger-zone__text {
    margin: 0;
    color: var(--mj-account-muted);
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

.mj-field-grid--dynfields {
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 16px 24px;
}

/* ── Dynamic-field group card style ── */
.mj-field-group--dyn {
    margin-bottom: 10px;
    background: rgba(248, 250, 252, 0.75);
    border: 1px solid rgba(47, 82, 143, 0.1);
    border-radius: var(--mj-account-radius-sm, 10px);
    padding: 16px;
    gap: 6px;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.mj-field-group--dyn:hover {
    border-color: rgba(47, 82, 143, 0.2);
}

.mj-field-group--dyn:focus-within {
    border-color: var(--mj-account-accent);
    box-shadow: 0 0 0 3px rgba(47, 82, 143, 0.12);
}

.mj-field-group--dyn > label:first-child,
.mj-field-group--dyn > fieldset > legend {
    font-weight: 600;
    font-size: 0.85rem;
    color: var(--mj-account-text);
    margin-bottom: 0;
}

.mj-field-group--dyn > .mj-field-hint,
.mj-field-group--dyn > fieldset > .mj-field-hint {
    display: block;
    font-size: 0.8rem;
    line-height: 1.4;
    color: #64748b;
    margin: 0 0 4px;
}

.mj-field-group--dyn > fieldset {
    border: none;
    padding: 0;
    margin: 0;
}

.mj-field-group--dyn > fieldset > legend {
    padding: 0;
    margin-bottom: 2px;
}

.mj-field-group--dyn .mj-checkbox span,
.mj-field-group--dyn .mj-radio span {
    font-size: 14px;
    font-weight: 400;
    color: #334155;
}

.mj-field-group--dyn .mj-checkbox {
    font-size: 14px;
}

.mj-dynfield-other-input {
    display: block;
    width: 100%;
    max-width: 400px;
    padding: 6px 10px;
    font-size: 14px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    box-sizing: border-box;
    margin-top: 6px;
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
    flex-wrap: wrap;
}

.mj-account-child-card__heading {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
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

.mj-account-child-card__notes {
    margin: 8px 0 0;
    color: var(--mj-account-muted);
}

.mj-account-child-card__notes strong {
    color: var(--mj-account-text);
}

.mj-account-children__add {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    white-space: nowrap;
}

.mj-account-children__add-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    border-radius: 50%;
    background: rgba(47, 82, 143, 0.18);
    color: var(--mj-account-accent);
    font-weight: 700;
    font-size: 1rem;
}

.mj-account-children__feedback {
    margin-bottom: 20px;
    padding: 14px 18px;
    border-radius: var(--mj-account-radius-sm);
    border: 1px solid transparent;
    font-weight: 600;
    display: block;
}

.mj-account-children__feedback[hidden] {
    display: none !important;
}

.mj-account-children__feedback.is-success {
    background: rgba(46, 204, 113, 0.12);
    border-color: rgba(46, 204, 113, 0.3);
    color: #2c7d49;
}

.mj-account-children__feedback.is-error {
    background: rgba(205, 45, 64, 0.12);
    border-color: rgba(205, 45, 64, 0.24);
    color: #901624;
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

.mj-modal-open {
    overflow: hidden;
}

.mj-modal {
    position: fixed;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10000;
}

.mj-modal[hidden] {
    display: none !important;
}

.mj-modal__backdrop {
    position: absolute;
    inset: 0;
    background: rgba(15, 23, 42, 0.55);
}

.mj-modal__dialog {
    position: relative;
    background: #ffffff;
    border-radius: var(--mj-account-radius-lg);
    max-width: 640px;
    width: min(640px, 92vw);
    padding: 32px;
    box-shadow: 0 28px 60px rgba(31, 39, 66, 0.35);
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.mj-modal__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.mj-modal__title {
    margin: 0;
    font-size: 1.45rem;
    font-weight: 700;
    color: var(--mj-account-text);
}

.mj-modal__close {
    position: absolute;
    top: 16px;
    right: 16px;
    border: none;
    background: rgba(47, 82, 143, 0.1);
    border-radius: 999px;
    width: 34px;
    height: 34px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    color: var(--mj-account-accent);
    font-size: 1.4rem;
    transition: background 0.2s ease;
}

.mj-modal__close:hover,
.mj-modal__close:focus,
.mj-modal__close:focus-visible {
    background: rgba(47, 82, 143, 0.2);
}

.mj-modal__body {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.mj-field-grid--modal {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 16px;
}

.mj-modal__options {
    display: flex;
    flex-wrap: wrap;
    gap: 12px 18px;
    align-items: center;
}

.mj-modal-checkbox {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    font-size: 0.95rem;
    color: var(--mj-account-text);
}

.mj-modal-checkbox input {
    margin: 0;
}

.mj-modal__messages {
    border-radius: var(--mj-account-radius-sm);
    border: 1px solid rgba(205, 45, 64, 0.22);
    background: rgba(205, 45, 64, 0.08);
    padding: 12px 16px;
    display: grid;
    gap: 10px;
}

.mj-modal__messages[hidden] {
    display: none !important;
}

.mj-modal__error-intro {
    margin: 0;
    font-weight: 600;
    color: #901624;
}

.mj-modal__error-list {
    margin: 0;
    padding-left: 20px;
    display: grid;
    gap: 6px;
    color: #901624;
}

.mj-modal__footer {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
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

    .mj-account-children__add {
        width: 100%;
        justify-content: center;
    }

    .mj-field-grid--modal {
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    }

    .mj-modal__dialog {
        width: min(96vw, 560px);
        padding: 24px;
    }

    .mj-modal__footer {
        flex-direction: column;
        align-items: stretch;
    }

    .mj-modal__footer .mj-button {
        width: 100%;
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

/* ── Account Tabs ── */
.mj-account-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    padding: 6px;
    background: rgba(47, 82, 143, 0.06);
    border-radius: var(--mj-account-radius-lg);
    border: 1px solid var(--mj-account-border);
}

.mj-account-tab {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 10px 18px;
    border: none;
    border-radius: calc(var(--mj-account-radius-lg) - 4px);
    background: transparent;
    color: var(--mj-account-muted);
    font-weight: 600;
    font-size: 0.95rem;
    cursor: pointer;
    transition: background 0.2s ease, color 0.2s ease, box-shadow 0.2s ease;
    white-space: nowrap;
    font-family: inherit;
}

.mj-account-tab:hover {
    background: rgba(47, 82, 143, 0.08);
    color: var(--mj-account-text);
}

.mj-account-tab--active {
    background: var(--mj-account-card-bg);
    color: var(--mj-account-accent);
    box-shadow: 0 2px 8px rgba(47, 82, 143, 0.12);
}

.mj-account-tab--add {
    margin-left: auto;
    color: var(--mj-account-accent);
    font-size: 0.9rem;
}

.mj-account-tab--add:hover {
    background: rgba(47, 82, 143, 0.12);
}

.mj-account-tab--add[disabled] {
    opacity: 0.5;
    cursor: not-allowed;
}

/* ── Child Detail Panel ── */
.mj-account-card--child-detail[hidden] {
    display: none !important;
}

.mj-account-children__feedback {
    padding: 12px 16px;
    border-radius: var(--mj-account-radius-sm);
    font-size: 0.95rem;
    font-weight: 500;
}

.mj-account-children__feedback[hidden] {
    display: none !important;
}

.mj-account-children__feedback--success {
    background: rgba(34, 197, 94, 0.1);
    color: #166534;
    border: 1px solid rgba(34, 197, 94, 0.25);
}

.mj-account-children__feedback--error {
    background: rgba(239, 68, 68, 0.1);
    color: #991b1b;
    border: 1px solid rgba(239, 68, 68, 0.25);
}

/* ── Hide children section card when using tabs ── */
.mj-account-card--children-tabbed {
    border: none !important;
    padding: 0 !important;
    box-shadow: none !important;
    background: transparent !important;
    min-height: 0 !important;
    overflow: hidden !important;
    max-height: 0 !important;
    margin: 0 !important;
}

.mj-account-card--children-tabbed > *:not(.mj-modal) {
    display: none !important;
}

@media (max-width: 640px) {
    .mj-account-tabs {
        gap: 4px;
        padding: 4px;
    }

    .mj-account-tab {
        padding: 8px 12px;
        font-size: 0.85rem;
    }

    .mj-account-tab--add {
        margin-left: 0;
        width: 100%;
        justify-content: center;
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

// ── "Autre" toggle for dynamic fields (dropdown / radio / checklist) ──
(function () {
    function initDynfieldOtherToggles(scope) {
        scope.querySelectorAll('select[data-dynfield-other="1"]').forEach(function (sel) {
            var otherInput = sel.parentElement.querySelector('.mj-dynfield-other-input');
            if (!otherInput) return;
            sel.addEventListener('change', function () {
                otherInput.style.display = sel.value === '__other' ? '' : 'none';
                if (sel.value !== '__other') otherInput.value = '';
            });
        });
        scope.querySelectorAll('input[value="__other"]').forEach(function (otherEl) {
            var container = otherEl.closest('fieldset') || otherEl.parentElement;
            var otherInput = container ? container.querySelector('.mj-dynfield-other-input') : null;
            if (!otherInput) return;
            if (otherEl.type === 'radio') {
                var radios = scope.querySelectorAll('input[type="radio"][name="' + otherEl.name + '"]');
                radios.forEach(function (r) {
                    r.addEventListener('change', function () {
                        var isOther = otherEl.checked;
                        otherInput.style.display = isOther ? '' : 'none';
                        if (!isOther) otherInput.value = '';
                    });
                });
            } else if (otherEl.type === 'checkbox') {
                otherEl.addEventListener('change', function () {
                    otherInput.style.display = otherEl.checked ? '' : 'none';
                    if (!otherEl.checked) otherInput.value = '';
                });
            }
        });
    }
    if (typeof document !== 'undefined') {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function () { initDynfieldOtherToggles(document); });
        } else {
            initDynfieldOtherToggles(document);
        }
    }
})();

// ── Account tabs switching ──
(function () {
    function initTabs(shell) {
        var nav = shell.querySelector('[data-mj-account-tabs]');
        if (!nav) return;
        var tabs = Array.prototype.slice.call(nav.querySelectorAll('[data-mj-tab]'));
        var panels = Array.prototype.slice.call(shell.querySelectorAll('[data-mj-tab-panel]'));
        if (!tabs.length || !panels.length) return;

        function activate(key) {
            tabs.forEach(function (t) {
                var isActive = t.getAttribute('data-mj-tab') === key;
                t.classList.toggle('mj-account-tab--active', isActive);
                t.setAttribute('aria-selected', isActive ? 'true' : 'false');
            });
            panels.forEach(function (p) {
                var isActive = p.getAttribute('data-mj-tab-panel') === key;
                if (isActive) {
                    p.removeAttribute('hidden');
                } else {
                    p.setAttribute('hidden', '');
                }
            });
        }

        nav.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-mj-tab]');
            if (!btn || !nav.contains(btn)) return;
            e.preventDefault();
            activate(btn.getAttribute('data-mj-tab'));
        });
    }

    function setup() {
        var shells = document.querySelectorAll('.mj-account-shell');
        for (var i = 0; i < shells.length; i++) {
            if (shells[i].dataset.mjTabsInit === '1') continue;
            shells[i].dataset.mjTabsInit = '1';
            initTabs(shells[i]);
        }
    }

    if (typeof document !== 'undefined') {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', setup);
        } else {
            setup();
        }
    }
})();

// ── Child tab form AJAX submission ──
(function () {
    function initChildForms() {
        var forms = document.querySelectorAll('[data-mj-child-tab-form]');
        if (!forms.length) return;
        var data = typeof mjMemberAccountData !== 'undefined' ? mjMemberAccountData : null;
        if (!data || !data.ajaxUrl) return;

        forms.forEach(function (form) {
            if (form.dataset.mjChildFormInit === '1') return;
            form.dataset.mjChildFormInit = '1';
            var childId = form.getAttribute('data-mj-child-tab-form');
            var feedback = form.parentElement.querySelector('[data-mj-child-tab-feedback="' + childId + '"]');

            form.addEventListener('submit', function (e) {
                e.preventDefault();
                var btn = form.querySelector('button[type="submit"]');
                if (btn) { btn.disabled = true; btn.textContent = 'Enregistrement…'; }
                if (feedback) { feedback.setAttribute('hidden', ''); feedback.className = 'mj-account-children__feedback'; feedback.textContent = ''; }

                var fd = new FormData(form);
                fd.append('action', 'mj_member_update_child_profile');
                fd.append('nonce', data.nonces && data.nonces.update ? data.nonces.update : '');

                // checkboxes not sent when unchecked
                if (!fd.has('is_autonomous')) fd.append('is_autonomous', '0');
                if (!fd.has('photo_usage_consent')) fd.append('photo_usage_consent', '0');

                var xhr = new XMLHttpRequest();
                xhr.open('POST', data.ajaxUrl, true);
                xhr.onload = function () {
                    if (btn) { btn.disabled = false; btn.textContent = 'Enregistrer'; }
                    try {
                        var resp = JSON.parse(xhr.responseText);
                        if (resp.success) {
                            if (feedback) {
                                feedback.removeAttribute('hidden');
                                feedback.className = 'mj-account-children__feedback mj-account-children__feedback--success';
                                feedback.textContent = resp.data && resp.data.message ? resp.data.message : 'Modifications enregistrées.';
                            }
                            // update photo preview if returned
                            if (resp.data && resp.data.child && resp.data.child.photo) {
                                var preview = form.parentElement.querySelector('.mj-account-photo-control__preview');
                                if (preview) {
                                    var url = resp.data.child.photo.url || '';
                                    if (url) {
                                        preview.innerHTML = '<img src="' + url + '" alt="" />';
                                    } else {
                                        preview.innerHTML = '<span class="mj-account-photo-control__placeholder">👤</span>';
                                    }
                                }
                            }
                            // update tab label if name changed
                            if (resp.data && resp.data.child) {
                                var tabBtn = document.querySelector('[data-mj-tab="child-' + childId + '"]');
                                if (tabBtn) {
                                    var newName = (resp.data.child.first_name || '') + ' ' + (resp.data.child.last_name || '');
                                    newName = newName.trim();
                                    if (newName) tabBtn.textContent = '👶 ' + newName;
                                }
                                // update header title
                                var header = form.parentElement.querySelector('.mj-account-card__title');
                                if (header && newName) header.textContent = '👶 ' + newName;
                            }
                        } else {
                            if (feedback) {
                                feedback.removeAttribute('hidden');
                                feedback.className = 'mj-account-children__feedback mj-account-children__feedback--error';
                                feedback.textContent = resp.data && typeof resp.data === 'string' ? resp.data : (resp.data && resp.data.message ? resp.data.message : 'Erreur lors de la mise à jour.');
                            }
                        }
                    } catch (ex) {
                        if (feedback) {
                            feedback.removeAttribute('hidden');
                            feedback.className = 'mj-account-children__feedback mj-account-children__feedback--error';
                            feedback.textContent = 'Erreur inattendue.';
                        }
                    }
                };
                xhr.onerror = function () {
                    if (btn) { btn.disabled = false; btn.textContent = 'Enregistrer'; }
                    if (feedback) {
                        feedback.removeAttribute('hidden');
                        feedback.className = 'mj-account-children__feedback mj-account-children__feedback--error';
                        feedback.textContent = 'Erreur réseau.';
                    }
                };
                xhr.send(fd);
            });
        });
    }

    if (typeof document !== 'undefined') {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initChildForms);
        } else {
            initChildForms();
        }
    }
})();
            </script><?php
        }

        return ob_get_clean();
    }
}
