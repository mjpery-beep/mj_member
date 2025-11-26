<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('mj_member_get_account_redirect')) {
    function mj_member_get_account_redirect($atts = array()) {
        $redirect = isset($atts['redirect']) ? trim($atts['redirect']) : '';
        if ($redirect === '') {
            $redirect = home_url('/mon-compte');
        }

        return apply_filters('mj_member_account_redirect_url', esc_url_raw($redirect), $atts);
    }
}

if (!function_exists('mj_member_shortcode_bool')) {
    function mj_member_shortcode_bool($value, $default = true) {
        if ($value === null || $value === '') {
            return $default;
        }

        $value = strtolower(trim((string) $value));

        if (in_array($value, array('1', 'true', 'yes', 'on'), true)) {
            return true;
        }

        if (in_array($value, array('0', 'false', 'no', 'off'), true)) {
            return false;
        }

        return $default;
    }
}

if (!function_exists('mj_member_login_shortcode')) {
    function mj_member_login_shortcode($atts = array(), $content = '') {
        $atts = shortcode_atts(array(
            'redirect' => '',
        ), $atts, 'mj_member_login');

        if (is_user_logged_in()) {
            $redirect = mj_member_get_account_redirect($atts);
            $message = __('Vous êtes déjà connecté.', 'mj-member');
            $link = sprintf('<a href="%1$s">%2$s</a>', esc_url($redirect), esc_html__('Accéder à mon espace', 'mj-member'));
            return '<div class="mj-member-login-status">' . esc_html($message) . ' ' . $link . '</div>';
        }

        $errors = array();
        $submitted_login = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mj_member_login_form'])) {
            $nonce = sanitize_text_field(wp_unslash($_POST['mj_member_login_nonce'] ?? ''));
            if (!wp_verify_nonce($nonce, 'mj_member_login_form')) {
                $errors[] = __('La vérification de sécurité a échoué. Merci de réessayer.', 'mj-member');
            } else {
                $submitted_login = sanitize_text_field(wp_unslash($_POST['log'] ?? ''));
                $password = (string) wp_unslash($_POST['pwd'] ?? '');
                $remember = !empty($_POST['rememberme']);

                if ($submitted_login === '' || $password === '') {
                    $errors[] = __('Merci de renseigner vos identifiants.', 'mj-member');
                } else {
                    $creds = array(
                        'user_login' => $submitted_login,
                        'user_password' => $password,
                        'remember' => $remember,
                    );
                    $user = wp_signon($creds, false);
                    if (is_wp_error($user)) {
                        $errors[] = $user->get_error_message();
                    } else {
                        $redirect_target = esc_url_raw(wp_unslash($_POST['redirect_to'] ?? ''));
                        if ($redirect_target === '') {
                            $redirect_target = mj_member_get_account_redirect($atts);
                        }
                        $redirect_target = apply_filters('mj_member_login_redirect', $redirect_target, $user, $atts);
                        wp_safe_redirect($redirect_target);
                        exit;
                    }
                }
            }
        }

        ob_start();
        ?>
        <form method="post" class="mj-member-login-form">
            <?php
            if (!empty($errors)) {
                echo '<div class="mj-member-form-errors">';
                foreach ($errors as $error) {
                    echo '<p>' . esc_html($error) . '</p>';
                }
                echo '</div>';
            }
            ?>
            <div class="mj-member-field">
                <label for="mj_member_login_user"><?php esc_html_e('Adresse email ou identifiant', 'mj-member'); ?></label>
                <input type="text" id="mj_member_login_user" name="log" value="<?php echo esc_attr($submitted_login); ?>" required />
            </div>
            <div class="mj-member-field">
                <label for="mj_member_login_pass"><?php esc_html_e('Mot de passe', 'mj-member'); ?></label>
                <input type="password" id="mj_member_login_pass" name="pwd" required />
            </div>
            <div class="mj-member-field mj-member-field--inline">
                <label>
                    <input type="checkbox" name="rememberme" value="1" />
                    <span><?php esc_html_e('Se souvenir de moi', 'mj-member'); ?></span>
                </label>
            </div>
            <?php
            $redirect_value = mj_member_get_account_redirect($atts);
            ?>
            <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_value); ?>" />
            <input type="hidden" name="mj_member_login_form" value="1" />
            <?php wp_nonce_field('mj_member_login_form', 'mj_member_login_nonce'); ?>
            <div class="mj-member-actions">
                <button type="submit" class="mj-member-button"><?php esc_html_e('Connexion', 'mj-member'); ?></button>
            </div>
            <p class="mj-member-login-help">
                <a href="<?php echo esc_url(wp_lostpassword_url()); ?>"><?php esc_html_e('Mot de passe oublié ?', 'mj-member'); ?></a>
            </p>
        </form>
        <?php
        return ob_get_clean();
    }
    add_shortcode('mj_member_login', 'mj_member_login_shortcode');
}

if (!function_exists('mj_member_render_account_component')) {
    function mj_member_render_account_component($options = array()) {
        $defaults = array(
            'redirect' => '',
            'show_profile_form' => true,
            'show_children' => true,
            'show_payments' => true,
            'payment_limit' => 10,
            'form_id' => '',
            'title' => __('Mes informations', 'mj-member'),
            'description' => '',
            'submit_label' => __('Enregistrer', 'mj-member'),
            'success_message' => __('Vos informations ont été mises à jour.', 'mj-member'),
            'login_message' => __('Vous devez être connecté pour accéder à cette page.', 'mj-member'),
            'context' => 'shortcode',
            'wrapper_class' => '',
        );

        $settings = wp_parse_args($options, $defaults);

        $posted_form_id = isset($_POST['mj_member_account_form_id']) ? sanitize_html_class(wp_unslash($_POST['mj_member_account_form_id'])) : '';
        $form_id = $settings['form_id'] !== '' ? sanitize_html_class((string) $settings['form_id']) : '';
        if ($form_id === '') {
            $form_id = $posted_form_id !== '' ? $posted_form_id : sanitize_html_class(wp_unique_id('mj-member-account-'));
        }
        $settings['form_id'] = $form_id;

        $redirect_to = mj_member_get_account_redirect(array('redirect' => $settings['redirect']));

        if (!is_user_logged_in()) {
            $login_url = apply_filters('mj_member_account_login_url', wp_login_url($redirect_to), $redirect_to, $settings);
            return '<div class="mj-member-account-warning">' . esc_html($settings['login_message']) . ' <a href="' . esc_url($login_url) . '">' . esc_html__('Se connecter', 'mj-member') . '</a></div>';
        }

        $member = mj_member_get_current_member();
        if (!$member) {
            return '<div class="mj-member-account-warning">' . esc_html__('Aucun profil MJ n’est associé à votre compte. Merci de contacter la MJ.', 'mj-member') . '</div>';
        }

        $errors = array();
        $success = false;
        $is_target_form = $settings['show_profile_form'] && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mj_member_account_form'], $_POST['mj_member_account_form_id']) && $_POST['mj_member_account_form'] === '1' && $posted_form_id === $form_id;

        if ($is_target_form) {
            $nonce = sanitize_text_field(wp_unslash($_POST['mj_member_account_nonce'] ?? ''));
            if (!wp_verify_nonce($nonce, 'mj_member_account_form')) {
                $errors[] = __('La vérification de sécurité a échoué. Merci de réessayer.', 'mj-member');
            } else {
                $update = array(
                    'first_name' => sanitize_text_field(wp_unslash($_POST['member_first_name'] ?? '')),
                    'last_name' => sanitize_text_field(wp_unslash($_POST['member_last_name'] ?? '')),
                    'email' => sanitize_email(wp_unslash($_POST['member_email'] ?? '')),
                    'phone' => sanitize_text_field(wp_unslash($_POST['member_phone'] ?? '')),
                    'address' => sanitize_text_field(wp_unslash($_POST['member_address'] ?? '')),
                    'postal_code' => sanitize_text_field(wp_unslash($_POST['member_postal_code'] ?? '')),
                    'city' => sanitize_text_field(wp_unslash($_POST['member_city'] ?? '')),
                    'photo_usage_consent' => !empty($_POST['member_photo_consent']) ? 1 : 0,
                    'newsletter_opt_in' => !empty($_POST['member_newsletter_opt_in']) ? 1 : 0,
                    'sms_opt_in' => !empty($_POST['member_sms_opt_in']) ? 1 : 0,
                );

                $birth_date = sanitize_text_field(wp_unslash($_POST['member_birth_date'] ?? ''));
                if ($birth_date !== '') {
                    $update['birth_date'] = $birth_date;
                }

                if ($update['first_name'] === '' || $update['last_name'] === '') {
                    $errors[] = __('Le prénom et le nom sont requis.', 'mj-member');
                }

                if ($update['email'] === '' || !is_email($update['email'])) {
                    $errors[] = __('Merci de renseigner une adresse email valide.', 'mj-member');
                }

                if (!empty($_POST['member_photo_remove'])) {
                    $update['photo_id'] = null;
                }

                if (!empty($_FILES['member_photo']['name'] ?? '')) {
                    if (!function_exists('media_handle_upload')) {
                        require_once ABSPATH . 'wp-admin/includes/file.php';
                        require_once ABSPATH . 'wp-admin/includes/media.php';
                        require_once ABSPATH . 'wp-admin/includes/image.php';
                    }

                    $cap_filter_added = false;
                    if (!current_user_can('upload_files') && function_exists('mj_member_temp_allow_upload_cap')) {
                        add_filter('user_has_cap', 'mj_member_temp_allow_upload_cap', 10, 3);
                        $cap_filter_added = true;
                    }

                    $uploaded = media_handle_upload('member_photo', 0);

                    if ($cap_filter_added) {
                        remove_filter('user_has_cap', 'mj_member_temp_allow_upload_cap', 10);
                    }

                    if (is_wp_error($uploaded)) {
                        $errors[] = $uploaded->get_error_message();
                    } else {
                        $update['photo_id'] = (int) $uploaded;
                    }
                }

                if (empty($errors)) {
                    $result = MjMembers_CRUD::update($member->id, $update);
                    if ($result === false) {
                        $errors[] = __('Une erreur est survenue lors de la mise à jour. Merci de réessayer.', 'mj-member');
                    } else {
                        $success = true;
                        $member = MjMembers_CRUD::getById($member->id);
                        mj_member_sync_member_user_account($member, array('role' => 'subscriber', 'send_notification' => false));
                    }
                }
            }
        }

        $children = $settings['show_children'] ? mj_member_get_guardian_children($member) : array();
        $payment_history = $settings['show_payments'] ? mj_member_get_payment_timeline($member->id, (int) $settings['payment_limit']) : array();

        if (function_exists('mj_member_login_component_get_member_avatar')) {
            $avatar = mj_member_login_component_get_member_avatar(null, $member);
        } else {
            $avatar = array(
                'url' => get_avatar_url(get_current_user_id(), array('size' => 128)),
                'id' => !empty($member->photo_id) ? (int) $member->photo_id : 0,
            );
        }

        $wrapper_classes = array('mj-member-account');
        if (!empty($settings['wrapper_class'])) {
            $additional = is_array($settings['wrapper_class']) ? $settings['wrapper_class'] : preg_split('/\s+/', (string) $settings['wrapper_class']);
            foreach ($additional as $extra) {
                $extra = trim((string) $extra);
                if ($extra !== '') {
                    $wrapper_classes[] = sanitize_html_class($extra);
                }
            }
        }

        $wrapper_attr = implode(' ', array_unique($wrapper_classes));

        ob_start();
        ?>
        <div class="<?php echo esc_attr($wrapper_attr); ?>">
            <?php if ($settings['show_profile_form']) : ?>
                <div class="mj-member-account__section mj-member-account__section--profile">
                    <?php if ($settings['title'] !== '') : ?>
                        <h2 class="mj-member-account__title"><?php echo esc_html($settings['title']); ?></h2>
                    <?php endif; ?>
                    <?php if ($settings['description'] !== '') : ?>
                        <p class="mj-member-account__intro"><?php echo wp_kses_post($settings['description']); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($errors)) : ?>
                        <div class="mj-member-form-errors">
                            <?php foreach ($errors as $error) : ?>
                                <p><?php echo esc_html($error); ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($success) : ?>
                        <div class="mj-member-form-success">
                            <p><?php echo esc_html($settings['success_message']); ?></p>
                        </div>
                    <?php endif; ?>
                    <form method="post" class="mj-member-account-form" enctype="multipart/form-data">
                        <div class="mj-member-grid">
                            <div class="mj-member-field mj-member-field--photo">
                                <label for="<?php echo esc_attr($form_id); ?>_photo"><?php esc_html_e('Photo de profil', 'mj-member'); ?></label>
                                <div class="mj-member-photo-control">
                                    <div class="mj-member-photo-control__preview">
                                        <?php if (!empty($avatar['url'])) : ?>
                                            <img src="<?php echo esc_url($avatar['url']); ?>" alt="<?php echo esc_attr__('Photo de profil', 'mj-member'); ?>" />
                                        <?php else : ?>
                                            <span class="mj-member-photo-control__placeholder" aria-hidden="true">?</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="mj-member-photo-control__fields">
                                        <input type="file" id="<?php echo esc_attr($form_id); ?>_photo" name="member_photo" accept="image/*" />
                                        <?php if (!empty($member->photo_id)) : ?>
                                            <label class="mj-member-photo-control__remove">
                                                <input type="checkbox" name="member_photo_remove" value="1" />
                                                <span><?php esc_html_e('Supprimer la photo actuelle', 'mj-member'); ?></span>
                                            </label>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="mj-member-field">
                                <label for="member_first_name"><?php esc_html_e('Prénom', 'mj-member'); ?></label>
                                <input type="text" id="member_first_name" name="member_first_name" value="<?php echo esc_attr($member->first_name); ?>" required />
                            </div>
                            <div class="mj-member-field">
                                <label for="member_last_name"><?php esc_html_e('Nom', 'mj-member'); ?></label>
                                <input type="text" id="member_last_name" name="member_last_name" value="<?php echo esc_attr($member->last_name); ?>" required />
                            </div>
                            <div class="mj-member-field">
                                <label for="member_email"><?php esc_html_e('Email', 'mj-member'); ?></label>
                                <input type="email" id="member_email" name="member_email" value="<?php echo esc_attr($member->email); ?>" required />
                            </div>
                            <div class="mj-member-field">
                                <label for="member_phone"><?php esc_html_e('Téléphone', 'mj-member'); ?></label>
                                <input type="tel" id="member_phone" name="member_phone" value="<?php echo esc_attr($member->phone); ?>" />
                            </div>
                            <div class="mj-member-field">
                                <label for="member_birth_date"><?php esc_html_e('Date de naissance', 'mj-member'); ?></label>
                                <input type="date" id="member_birth_date" name="member_birth_date" value="<?php echo esc_attr($member->birth_date); ?>" />
                            </div>
                            <div class="mj-member-field">
                                <label for="member_address"><?php esc_html_e('Adresse', 'mj-member'); ?></label>
                                <input type="text" id="member_address" name="member_address" value="<?php echo esc_attr($member->address); ?>" />
                            </div>
                            <div class="mj-member-field">
                                <label for="member_postal_code"><?php esc_html_e('Code postal', 'mj-member'); ?></label>
                                <input type="text" id="member_postal_code" name="member_postal_code" value="<?php echo esc_attr($member->postal_code); ?>" />
                            </div>
                            <div class="mj-member-field">
                                <label for="member_city"><?php esc_html_e('Ville', 'mj-member'); ?></label>
                                <input type="text" id="member_city" name="member_city" value="<?php echo esc_attr($member->city); ?>" />
                            </div>
                        </div>
                        <div class="mj-member-field mj-member-field--inline">
                            <label>
                                <input type="checkbox" name="member_photo_consent" value="1" <?php checked((int) $member->photo_usage_consent, 1); ?> />
                                <span><?php esc_html_e('J’autorise l’utilisation d’images dans le cadre des activités de la MJ.', 'mj-member'); ?></span>
                            </label>
                        </div>
                        <div class="mj-member-field mj-member-field--inline">
                            <label>
                                <input type="checkbox" name="member_newsletter_opt_in" value="1" <?php checked(!empty($member->newsletter_opt_in)); ?> />
                                <span><?php esc_html_e('Je souhaite recevoir les newsletters et informations par email.', 'mj-member'); ?></span>
                            </label>
                        </div>
                        <div class="mj-member-field mj-member-field--inline">
                            <label>
                                <input type="checkbox" name="member_sms_opt_in" value="1" <?php checked(!empty($member->sms_opt_in)); ?> />
                                <span><?php esc_html_e('Je souhaite recevoir des SMS importants de la MJ.', 'mj-member'); ?></span>
                            </label>
                        </div>
                        <input type="hidden" name="mj_member_account_form" value="1" />
                        <input type="hidden" name="mj_member_account_form_id" value="<?php echo esc_attr($form_id); ?>" />
                        <?php wp_nonce_field('mj_member_account_form', 'mj_member_account_nonce'); ?>
                        <div class="mj-member-actions">
                            <button type="submit" class="mj-member-button"><?php echo esc_html($settings['submit_label']); ?></button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <?php if ($settings['show_children'] && !empty($children)) : ?>
                <div class="mj-member-account__section mj-member-account__section--children">
                    <h3><?php esc_html_e('Jeunes associés', 'mj-member'); ?></h3>
                    <ul class="mj-member-account-children">
                        <?php foreach ($children as $child) : ?>
                            <li>
                                <strong><?php echo esc_html(trim($child->first_name . ' ' . $child->last_name)); ?></strong>
                                <?php if (!empty($child->birth_date)) : ?>
                                    <span>— <?php echo esc_html(date_i18n(get_option('date_format', 'd/m/Y'), strtotime($child->birth_date))); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($child->email)) : ?>
                                    <span>— <?php echo esc_html($child->email); ?></span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <p class="mj-member-account-children__help"><?php esc_html_e('Pour mettre à jour les informations liées à vos jeunes, merci de contacter l’équipe de la MJ.', 'mj-member'); ?></p>
                </div>
            <?php endif; ?>

            <?php if ($settings['show_payments']) : ?>
                <div class="mj-member-account__section mj-member-account__section--payments">
                    <h3><?php esc_html_e('Mes paiements', 'mj-member'); ?></h3>
                    <?php if (!empty($payment_history)) : ?>
                        <ul class="mj-member-account-payments__list">
                            <?php foreach ($payment_history as $payment) : ?>
                                <li class="mj-member-account-payments__item">
                                    <span class="mj-payment-date"><?php echo esc_html($payment['date']); ?></span>
                                    <span class="mj-payment-status"><?php echo esc_html($payment['status_label']); ?></span>
                                    <span class="mj-payment-amount"><?php echo esc_html($payment['amount']); ?> €</span>
                                    <?php if (!empty($payment['reference'])) : ?>
                                        <span class="mj-payment-reference"><?php echo esc_html($payment['reference']); ?></span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else : ?>
                        <p class="mj-member-account-payments__empty"><?php esc_html_e('Aucun paiement enregistré pour le moment.', 'mj-member'); ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        static $styles_printed = false;
        if (!$styles_printed) {
            $styles_printed = true;
            ?>
            <style>
                .mj-member-account {
                    display: grid;
                    gap: 28px;
                }

                .mj-member-account__title {
                    margin-top: 0;
                    margin-bottom: 16px;
                    font-size: 1.5rem;
                }

                .mj-member-account__intro {
                    margin-top: -8px;
                    margin-bottom: 24px;
                    color: #495057;
                }

                .mj-member-account-form {
                    background: #fff;
                    border: 1px solid #e3e6ea;
                    border-radius: 8px;
                    padding: 24px;
                    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.06);
                }

                .mj-member-field {
                    margin-bottom: 18px;
                }

                .mj-member-field input[type="text"],
                .mj-member-field input[type="email"],
                .mj-member-field input[type="password"],
                .mj-member-field input[type="tel"],
                .mj-member-field input[type="date"],
                .mj-member-field input[type="file"] {
                    width: 100%;
                    padding: 10px 14px;
                    border: 1px solid #ccd0d4;
                    border-radius: 6px;
                    font-size: 15px;
                    transition: border-color 0.2s ease;
                }

                .mj-member-field input:focus {
                    outline: none;
                    border-color: #0073aa;
                    box-shadow: 0 0 0 2px rgba(0, 115, 170, 0.15);
                }

                .mj-member-field label {
                    font-weight: 600;
                    color: #333;
                    margin-bottom: 6px;
                    display: block;
                }

                .mj-member-field--inline label {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    margin: 0;
                    font-weight: 500;
                }

                .mj-member-field--photo {
                    grid-column: 1 / -1;
                }

                .mj-member-photo-control {
                    display: flex;
                    gap: 18px;
                    align-items: center;
                    flex-wrap: wrap;
                }

                .mj-member-photo-control__preview {
                    width: 96px;
                    height: 96px;
                    border-radius: 50%;
                    overflow: hidden;
                    background: #f4f6f8;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }

                .mj-member-photo-control__preview img {
                    width: 100%;
                    height: 100%;
                    object-fit: cover;
                }

                .mj-member-photo-control__placeholder {
                    font-size: 32px;
                    color: #8892a0;
                }

                .mj-member-photo-control__fields {
                    flex: 1 1 220px;
                    min-width: 220px;
                }

                .mj-member-photo-control__remove {
                    display: inline-flex;
                    align-items: center;
                    gap: 8px;
                    margin-top: 10px;
                    font-size: 14px;
                    color: #555;
                }

                .mj-member-actions {
                    text-align: right;
                }

                .mj-member-button {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    padding: 12px 24px;
                    border-radius: 6px;
                    border: none;
                    background: #0073aa;
                    color: #fff;
                    font-size: 16px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: background 0.2s ease, transform 0.2s ease;
                }

                .mj-member-button:hover {
                    background: #005f8d;
                    transform: translateY(-1px);
                }

                .mj-member-form-errors {
                    background: #fbeaea;
                    border-left: 4px solid #d63638;
                    padding: 14px 18px;
                    border-radius: 6px;
                    margin-bottom: 18px;
                }

                .mj-member-form-success {
                    background: #e8f7ef;
                    border-left: 4px solid #1a7f37;
                    padding: 14px 18px;
                    border-radius: 6px;
                    margin-bottom: 18px;
                    color: #145126;
                }

                .mj-member-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
                    gap: 16px 20px;
                }

                .mj-member-account-children {
                    list-style: none;
                    margin: 0;
                    padding: 0;
                }

                .mj-member-account-children li {
                    padding: 10px 12px;
                    border: 1px solid #e3e6ea;
                    border-radius: 6px;
                    margin-bottom: 10px;
                    background: #fafbfc;
                }

                .mj-member-account-children__help {
                    margin-top: 8px;
                    font-size: 14px;
                    color: #555;
                }

                .mj-member-account-payments__list {
                    list-style: none;
                    margin: 0;
                    padding: 0;
                }

                .mj-member-account-payments__item {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
                    gap: 8px;
                    padding: 12px;
                    border: 1px solid #e3e6ea;
                    border-radius: 6px;
                    margin-bottom: 10px;
                    background: #f7f9fb;
                    font-size: 14px;
                }

                .mj-member-account-payments__item .mj-payment-status {
                    font-weight: 600;
                    color: #1a7f37;
                }

                .mj-member-account-payments__item .mj-payment-amount {
                    font-weight: 600;
                    color: #111;
                }

                .mj-member-account-payments__empty {
                    margin: 12px 0 0;
                    font-size: 14px;
                    color: #555;
                }

                .mj-member-account-warning,
                .mj-member-login-status {
                    max-width: 720px;
                    margin: 0 auto;
                    padding: 18px;
                    background: #f0f6fb;
                    border-left: 4px solid #0073aa;
                    border-radius: 6px;
                }

                @media (max-width: 640px) {
                    .mj-member-photo-control {
                        flex-direction: column;
                        align-items: flex-start;
                    }

                    .mj-member-photo-control__fields {
                        width: 100%;
                    }

                    .mj-member-actions {
                        text-align: center;
                    }

                    .mj-member-button {
                        width: 100%;
                    }
                }
            </style>
            <?php
        }

        return ob_get_clean();
    }
}

if (!function_exists('mj_member_account_shortcode')) {
    function mj_member_account_shortcode($atts = array(), $content = '') {
        $atts = shortcode_atts(array(
            'redirect' => '',
            'show_children' => 'yes',
            'show_payments' => 'yes',
            'payment_limit' => 10,
        ), $atts, 'mj_member_account');

        return mj_member_render_account_component(array(
            'redirect' => $atts['redirect'],
            'show_children' => mj_member_shortcode_bool($atts['show_children'], true),
            'show_payments' => mj_member_shortcode_bool($atts['show_payments'], true),
            'payment_limit' => max(1, (int) $atts['payment_limit']),
        ));
    }
    add_shortcode('mj_member_account', 'mj_member_account_shortcode');
}

if (!function_exists('mj_member_profile_form_shortcode')) {
    function mj_member_profile_form_shortcode($atts = array(), $content = '') {
        $atts = shortcode_atts(array(
            'redirect' => '',
        ), $atts, 'mj_member_profile_form');

        return mj_member_render_account_component(array(
            'redirect' => $atts['redirect'],
            'show_children' => false,
            'show_payments' => false,
        ));
    }
    add_shortcode('mj_member_profile_form', 'mj_member_profile_form_shortcode');
}
