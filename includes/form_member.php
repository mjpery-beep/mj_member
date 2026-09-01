<?php

$member = null;
$action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'add';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($action === 'edit' && $id > 0) {
    $member = MjMembers_CRUD::getById($id);
    if (!$member) {
        wp_die('Membre non trouvé');
    }
}

$allowed_roles = MjMembers_CRUD::getAllowedRoles();
$role_labels = MjMembers_CRUD::getRoleLabels();

$current_role = $member ? $member->role : MjMembers_CRUD::ROLE_JEUNE;
if (!$member && $action === 'add') {
    $requested_role = isset($_GET['role']) ? sanitize_text_field((string) wp_unslash($_GET['role'])) : '';
    if ($requested_role !== '' && in_array($requested_role, $allowed_roles, true)) {
        $current_role = $requested_role;
    }
}
$validation_errors = array();
$form_values = array();
$has_validation_errors = false;
$created_guardian_id = null;
$member_email_required = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mj_member_nonce'])) {
    if (!wp_verify_nonce($_POST['mj_member_nonce'], 'mj_member_form')) {
        wp_die('Vérification de sécurité échouée');
    }

    $requested_role = isset($_POST['member_role']) ? sanitize_text_field($_POST['member_role']) : $current_role;
    if (!in_array($requested_role, $allowed_roles, true)) {
        $requested_role = MjMembers_CRUD::ROLE_JEUNE;
    }
    $current_role = $requested_role;

    $date_last_payement_input = isset($_POST['date_last_payement']) ? sanitize_text_field($_POST['date_last_payement']) : '';
    $date_last_payement_db = ($date_last_payement_input !== '') ? $date_last_payement_input . ' ' . current_time('H:i:s') : null;

    $requires_payment = isset($_POST['requires_payment']) ? 1 : 0;
    if ($current_role === MjMembers_CRUD::ROLE_JEUNE && !isset($_POST['requires_payment'])) {
        $requires_payment = 1;
    }

    $min_password_length = (int) apply_filters('mj_member_min_password_length', 8);
    $account_login_raw = isset($_POST['member_account_login']) ? sanitize_text_field($_POST['member_account_login']) : '';
    $account_login_clean = $account_login_raw !== '' ? sanitize_user($account_login_raw, true) : '';
    $account_password = isset($_POST['member_account_password']) ? (string) wp_unslash($_POST['member_account_password']) : '';
    $account_password_confirm = isset($_POST['member_account_password_confirm']) ? (string) wp_unslash($_POST['member_account_password_confirm']) : '';
    $account_credentials_provided = ($account_login_raw !== '' || $account_password !== '' || $account_password_confirm !== '');

    $input_data = array(
        'member_role' => $current_role,
        'member_last_name' => isset($_POST['member_last_name']) ? sanitize_text_field($_POST['member_last_name']) : '',
        'member_first_name' => isset($_POST['member_first_name']) ? sanitize_text_field($_POST['member_first_name']) : '',
        'member_email' => isset($_POST['member_email']) ? sanitize_email($_POST['member_email']) : '',
        'member_phone' => isset($_POST['member_phone']) ? sanitize_text_field($_POST['member_phone']) : '',
        'member_birth_date' => isset($_POST['member_birth_date']) ? sanitize_text_field($_POST['member_birth_date']) : '',
        'member_address' => isset($_POST['member_address']) ? sanitize_text_field($_POST['member_address']) : '',
        'member_city' => isset($_POST['member_city']) ? sanitize_text_field($_POST['member_city']) : '',
        'member_postal' => isset($_POST['member_postal']) ? sanitize_text_field($_POST['member_postal']) : '',
        'member_description_courte' => isset($_POST['member_description_courte']) ? sanitize_text_field($_POST['member_description_courte']) : '',
        'member_description_longue' => isset($_POST['member_description_longue']) ? wp_kses_post($_POST['member_description_longue']) : '',
        'status' => isset($_POST['status']) ? sanitize_text_field($_POST['status']) : MjMembers_CRUD::STATUS_ACTIVE,
        'requires_payment' => $requires_payment,
        'date_last_payement' => $date_last_payement_input,
        'member_is_autonomous' => ($current_role === MjMembers_CRUD::ROLE_JEUNE) ? !empty($_POST['member_is_autonomous']) : true,
        'member_newsletter_opt_in' => !empty($_POST['member_newsletter_opt_in']),
        'member_sms_opt_in' => !empty($_POST['member_sms_opt_in']),
        'guardian_mode' => isset($_POST['guardian_mode']) ? sanitize_text_field($_POST['guardian_mode']) : 'existing',
        'guardian_id' => isset($_POST['guardian_id']) ? intval($_POST['guardian_id']) : 0,
        'guardian_last_name' => isset($_POST['guardian_last_name']) ? sanitize_text_field($_POST['guardian_last_name']) : '',
        'guardian_first_name' => isset($_POST['guardian_first_name']) ? sanitize_text_field($_POST['guardian_first_name']) : '',
        'guardian_email' => isset($_POST['guardian_email']) ? sanitize_email($_POST['guardian_email']) : '',
        'guardian_phone' => isset($_POST['guardian_phone']) ? sanitize_text_field($_POST['guardian_phone']) : '',
        'guardian_address' => isset($_POST['guardian_address']) ? sanitize_text_field($_POST['guardian_address']) : '',
        'guardian_city' => isset($_POST['guardian_city']) ? sanitize_text_field($_POST['guardian_city']) : '',
        'guardian_postal' => isset($_POST['guardian_postal']) ? sanitize_text_field($_POST['guardian_postal']) : '',
        'member_account_login' => $account_login_raw,
    );

    if (!in_array($input_data['guardian_mode'], array('existing', 'new'), true)) {
        $input_data['guardian_mode'] = 'existing';
    }

    if (!in_array($input_data['status'], array(MjMembers_CRUD::STATUS_ACTIVE, MjMembers_CRUD::STATUS_INACTIVE), true)) {
        $input_data['status'] = MjMembers_CRUD::STATUS_ACTIVE;
    }

    if ($current_role !== MjMembers_CRUD::ROLE_JEUNE) {
        $input_data['member_is_autonomous'] = true;
        $input_data['guardian_mode'] = 'existing';
        $input_data['guardian_id'] = 0;
    }

    if ($input_data['member_last_name'] === '') {
        $validation_errors[] = 'Le nom du membre est obligatoire.';
    }
    if ($input_data['member_first_name'] === '') {
        $validation_errors[] = 'Le prénom du membre est obligatoire.';
    }
    if ($current_role === MjMembers_CRUD::ROLE_JEUNE) {
        if ($input_data['member_email'] !== '' && !is_email($input_data['member_email'])) {
            $validation_errors[] = "L'email du membre n'est pas valide.";
        }
    } else {
        if ($input_data['member_email'] === '') {
            $validation_errors[] = "L'email du membre est obligatoire.";
        } elseif (!is_email($input_data['member_email'])) {
            $validation_errors[] = "L'email du membre n'est pas valide.";
        }
    }

    if ($current_role === MjMembers_CRUD::ROLE_JEUNE && !$input_data['member_is_autonomous']) {
        if ($input_data['guardian_mode'] === 'existing') {
            if ($input_data['guardian_id'] <= 0) {
                $validation_errors[] = 'Merci de sélectionner un tuteur existant.';
            } else {
                $guardian_candidate = MjMembers_CRUD::getById($input_data['guardian_id']);
                if (!$guardian_candidate || $guardian_candidate->role !== MjMembers_CRUD::ROLE_TUTEUR) {
                    $validation_errors[] = 'Le tuteur sélectionné est invalide.';
                }
            }
        } else {
            if ($input_data['guardian_last_name'] === '') {
                $validation_errors[] = 'Le nom du tuteur est obligatoire.';
            }
            if ($input_data['guardian_first_name'] === '') {
                $validation_errors[] = 'Le prénom du tuteur est obligatoire.';
            }
            if ($input_data['guardian_email'] === '') {
                $validation_errors[] = "L'email du tuteur est obligatoire.";
            } elseif (!is_email($input_data['guardian_email'])) {
                $validation_errors[] = "L'email du tuteur n'est pas valide.";
            }
        }
    }

    $existing_wp_user_id = ($member && !empty($member->wp_user_id)) ? (int) $member->wp_user_id : 0;

    if ($account_credentials_provided) {
        if ($account_login_raw === '' && !$existing_wp_user_id) {
            $validation_errors[] = "Indiquez un identifiant de connexion pour créer le compte utilisateur.";
        }

        if ($account_login_raw !== '' && $account_login_clean === '') {
            $validation_errors[] = "L'identifiant saisi contient des caractères non autorisés.";
        }

        if ($account_login_clean !== '') {
            $conflict_user_id = username_exists($account_login_clean);
            if ($conflict_user_id && (!$existing_wp_user_id || (int) $conflict_user_id !== $existing_wp_user_id)) {
                $validation_errors[] = "Cet identifiant est déjà utilisé par un autre compte.";
            }
        }

        if ($account_password === '') {
            $validation_errors[] = 'Saisissez un mot de passe pour le compte utilisateur.';
        }

        if ($account_password !== $account_password_confirm) {
            $validation_errors[] = 'La confirmation du mot de passe ne correspond pas.';
        }

        if ($account_password !== '' && strlen($account_password) < $min_password_length) {
            $validation_errors[] = sprintf('Le mot de passe doit contenir au moins %d caractères.', $min_password_length);
        }
    }

    if (!empty($validation_errors)) {
        $has_validation_errors = true;
        $form_values = $input_data;
        foreach ($validation_errors as $error) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($error) . '</p></div>';
        }
    } else {
        $member_email_value = $input_data['member_email'] !== '' ? $input_data['member_email'] : null;

        $member_payload = array(
            'first_name' => $input_data['member_first_name'],
            'last_name' => $input_data['member_last_name'],
            'email' => $member_email_value,
            'phone' => $input_data['member_phone'],
            'birth_date' => $input_data['member_birth_date'],
            'role' => $current_role,
            'status' => $input_data['status'],
            'requires_payment' => $input_data['requires_payment'],
            'address' => $input_data['member_address'],
            'city' => $input_data['member_city'],
            'postal_code' => $input_data['member_postal'],
            'description_courte' => $input_data['member_description_courte'] !== '' ? $input_data['member_description_courte'] : null,
            'description_longue' => $input_data['member_description_longue'] !== '' ? $input_data['member_description_longue'] : null,
            'date_last_payement' => $date_last_payement_db,
            'is_autonomous' => $input_data['member_is_autonomous'] ? 1 : 0,
            'guardian_id' => null,
            'newsletter_opt_in' => !empty($input_data['member_newsletter_opt_in']) ? 1 : 0,
            'sms_opt_in' => !empty($input_data['member_sms_opt_in']) ? 1 : 0,
        );

        if ($current_role === MjMembers_CRUD::ROLE_JEUNE) {
            if ($input_data['member_is_autonomous']) {
                $member_payload['guardian_id'] = null;
                $member_payload['is_autonomous'] = 1;
            } elseif ($input_data['guardian_mode'] === 'existing') {
                $member_payload['guardian_id'] = $input_data['guardian_id'] ?: null;
                $member_payload['is_autonomous'] = 0;
            } else {
                $guardian_payload = array(
                    'first_name' => $input_data['guardian_first_name'],
                    'last_name' => $input_data['guardian_last_name'],
                    'email' => $input_data['guardian_email'],
                    'phone' => $input_data['guardian_phone'],
                    'address' => $input_data['guardian_address'],
                    'city' => $input_data['guardian_city'],
                    'postal_code' => $input_data['guardian_postal'],
                    'status' => MjMembers_CRUD::STATUS_ACTIVE,
                );
                $existing_guardian_id = $member && !empty($member->guardian_id) ? (int) $member->guardian_id : 0;
                $guardian_id = MjMembers_CRUD::upsertGuardian($guardian_payload, $existing_guardian_id);
                if ($guardian_id) {
                    $member_payload['guardian_id'] = $guardian_id;
                    $member_payload['is_autonomous'] = 0;
                    $created_guardian_id = $guardian_id;
                }
            }
        }

        if ($action === 'add') {
            $result = MjMembers_CRUD::create($member_payload);
            if ($result) {
                $member = MjMembers_CRUD::getById($result);
                $current_role = $member ? $member->role : $current_role;
                $action = 'edit';
                $id = $result;
                $account_notice = '';
                if ($account_credentials_provided && $member) {
                    $login_to_use = ($account_login_clean !== '') ? $account_login_clean : '';
                    if ($login_to_use === '' && !empty($member->wp_user_id)) {
                        $existing_user = get_user_by('id', (int) $member->wp_user_id);
                        if ($existing_user) {
                            $login_to_use = $existing_user->user_login;
                        }
                    }

                    $account_result = mj_member_sync_member_user_account($member, array(
                        'role' => 'subscriber',
                        'send_notification' => false,
                        'user_login' => $login_to_use,
                        'user_pass' => $account_password,
                        'return_error' => true,
                    ));

                    if (is_wp_error($account_result)) {
                        $account_notice = $account_result->get_error_message();
                    }
                }

                echo '<div class="notice notice-success is-dismissible"><p>Membre ajouté avec succès.</p></div>';
                if ($account_notice !== '') {
                    echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($account_notice) . '</p></div>';
                }
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>Erreur lors de l\'ajout du membre.</p></div>';
            }
        } elseif ($action === 'edit' && $id > 0) {
            $update_result = MjMembers_CRUD::update($id, $member_payload);
            if ($update_result !== false) {
                $member = MjMembers_CRUD::getById($id);
                $current_role = $member ? $member->role : $current_role;
                $account_notice = '';
                if ($account_credentials_provided && $member) {
                    $login_to_use = ($account_login_clean !== '') ? $account_login_clean : '';
                    if ($login_to_use === '' && !empty($member->wp_user_id)) {
                        $existing_user = get_user_by('id', (int) $member->wp_user_id);
                        if ($existing_user) {
                            $login_to_use = $existing_user->user_login;
                        }
                    }

                    $account_result = mj_member_sync_member_user_account($member, array(
                        'role' => 'subscriber',
                        'send_notification' => false,
                        'user_login' => $login_to_use,
                        'user_pass' => $account_password,
                        'return_error' => true,
                    ));

                    if (is_wp_error($account_result)) {
                        $account_notice = $account_result->get_error_message();
                    }
                }

                echo '<div class="notice notice-success is-dismissible"><p>Membre mis à jour avec succès.</p></div>';
                if ($account_notice !== '') {
                    echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($account_notice) . '</p></div>';
                }
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>Erreur lors de la mise à jour du membre.</p></div>';
            }
        }
    }
}

$current_role = $member ? $member->role : $current_role;
$guardian = ($member && !empty($member->guardian_id)) ? MjMembers_CRUD::getById($member->guardian_id) : null;
$guardians = MjMembers_CRUD::getGuardians();

$birth_date_value = '';
if ($member && !empty($member->birth_date)) {
    $birth_timestamp = strtotime($member->birth_date);
    if ($birth_timestamp) {
        $birth_date_value = gmdate('Y-m-d', $birth_timestamp);
    }
}

$last_payment_value = '';
if ($member && !empty($member->date_last_payement) && $member->date_last_payement !== '0000-00-00 00:00:00') {
    $payment_timestamp = strtotime($member->date_last_payement);
    if ($payment_timestamp) {
        $last_payment_value = gmdate('Y-m-d', $payment_timestamp);
    }
}

$default_is_autonomous = ($current_role === MjMembers_CRUD::ROLE_JEUNE)
    ? ($member ? (bool) $member->is_autonomous : false)
    : true;

$default_requires_payment = $member ? (bool) $member->requires_payment : ($current_role === MjMembers_CRUD::ROLE_JEUNE);
$default_guardian_mode = $guardian ? 'existing' : 'new';
$default_guardian_id = $guardian ? (int) $guardian->id : ($created_guardian_id ?: 0);

$form_defaults = array(
    'member_role' => $current_role,
    'member_last_name' => $member ? $member->last_name : '',
    'member_first_name' => $member ? $member->first_name : '',
    'member_email' => ($member && isset($member->email)) ? $member->email : '',
    'member_phone' => $member ? $member->phone : '',
    'member_birth_date' => $birth_date_value,
    'member_address' => $member ? $member->address : '',
    'member_city' => $member ? $member->city : '',
    'member_postal' => $member ? $member->postal_code : '',
    'member_description_courte' => ($member && isset($member->description_courte)) ? $member->description_courte : '',
    'member_description_longue' => ($member && isset($member->description_longue)) ? $member->description_longue : '',
    'status' => $member ? $member->status : MjMembers_CRUD::STATUS_ACTIVE,
    'requires_payment' => $default_requires_payment,
    'date_last_payement' => $last_payment_value,
    'member_is_autonomous' => $default_is_autonomous,
    'member_newsletter_opt_in' => $member ? (isset($member->newsletter_opt_in) ? (bool) $member->newsletter_opt_in : true) : true,
    'member_sms_opt_in' => $member ? (isset($member->sms_opt_in) ? (bool) $member->sms_opt_in : true) : true,
    'guardian_mode' => $default_guardian_mode,
    'guardian_id' => $default_guardian_id,
    'guardian_last_name' => $guardian ? $guardian->last_name : '',
    'guardian_first_name' => $guardian ? $guardian->first_name : '',
    'guardian_email' => $guardian ? $guardian->email : '',
    'guardian_phone' => $guardian ? $guardian->phone : '',
    'guardian_address' => $guardian ? $guardian->address : '',
    'guardian_city' => $guardian ? $guardian->city : '',
    'guardian_postal' => $guardian ? $guardian->postal_code : '',
    'member_account_login' => '',
);

if ($member && !empty($member->wp_user_id)) {
    $existing_user = get_user_by('id', (int) $member->wp_user_id);
    if ($existing_user) {
        $form_defaults['member_account_login'] = $existing_user->user_login;
    }
}

if ($has_validation_errors) {
    $form_values = array_merge($form_defaults, $form_values);
} else {
    $form_values = $form_defaults;
}

$form_values['member_role'] = in_array($form_values['member_role'], $allowed_roles, true) ? $form_values['member_role'] : MjMembers_CRUD::ROLE_JEUNE;
$form_values['status'] = in_array($form_values['status'], array(MjMembers_CRUD::STATUS_ACTIVE, MjMembers_CRUD::STATUS_INACTIVE), true)
    ? $form_values['status']
    : MjMembers_CRUD::STATUS_ACTIVE;
$form_values['requires_payment'] = !empty($form_values['requires_payment']);
$form_values['member_is_autonomous'] = !empty($form_values['member_is_autonomous']);
$form_values['member_newsletter_opt_in'] = !empty($form_values['member_newsletter_opt_in']);
$form_values['member_sms_opt_in'] = !empty($form_values['member_sms_opt_in']);
$form_values['guardian_id'] = isset($form_values['guardian_id']) ? intval($form_values['guardian_id']) : 0;
if (!in_array($form_values['guardian_mode'], array('existing', 'new'), true)) {
    $form_values['guardian_mode'] = 'existing';
}

if ($form_values['guardian_id'] && empty(array_filter($guardians, static function ($candidate) use ($form_values) {
    return intval($candidate->id) === intval($form_values['guardian_id']);
}))) {
    $linked_guardian = MjMembers_CRUD::getById($form_values['guardian_id']);
    if ($linked_guardian && $linked_guardian->role === MjMembers_CRUD::ROLE_TUTEUR) {
        $guardians[] = $linked_guardian;
    }
}

$title = ($action === 'add') ? 'Ajouter un membre' : 'Éditer le membre';
$member_email_required = ($form_values['member_role'] !== MjMembers_CRUD::ROLE_JEUNE);
?>

<div class="mj-form-container">
    <h2><?php echo esc_html($title); ?></h2>

    <form method="post" class="mj-member-form">
        <?php wp_nonce_field('mj_member_form', 'mj_member_nonce'); ?>

        <div class="mj-form-section">
            <h3>Informations du membre</h3>

            <table class="form-table">
                <tr>
                    <th><label for="member_role">Type de membre *</label></th>
                    <td>
                        <select id="member_role" name="member_role" class="regular-text">
                            <?php foreach ($allowed_roles as $role_key) :
                                $label = isset($role_labels[$role_key]) ? $role_labels[$role_key] : ucfirst($role_key);
                                ?>
                                <option value="<?php echo esc_attr($role_key); ?>" <?php selected($form_values['member_role'], $role_key); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Le type de membre détermine les champs affichés et les droits liés à ce profil.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="member_last_name">Nom *</label></th>
                    <td>
                        <input type="text" id="member_last_name" name="member_last_name" value="<?php echo esc_attr($form_values['member_last_name']); ?>" class="regular-text" required />
                    </td>
                </tr>
                <tr>
                    <th><label for="member_first_name">Prénom *</label></th>
                    <td>
                        <input type="text" id="member_first_name" name="member_first_name" value="<?php echo esc_attr($form_values['member_first_name']); ?>" class="regular-text" required />
                    </td>
                </tr>
                <tr>
                    <th><label for="member_email" id="member_email_label">Email <?php echo $member_email_required ? '*' : '(optionnel)'; ?></label></th>
                    <td>
                        <input type="email" id="member_email" name="member_email" value="<?php echo esc_attr($form_values['member_email']); ?>" class="regular-text"<?php echo $member_email_required ? ' required' : ''; ?> />
                    </td>
                </tr>
                <tr>
                    <th><label for="member_phone">Téléphone</label></th>
                    <td>
                        <input type="tel" id="member_phone" name="member_phone" value="<?php echo esc_attr($form_values['member_phone']); ?>" class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th>Préférences de contact</th>
                    <td>
                        <label style="display:block;margin-bottom:6px;">
                            <input type="checkbox" name="member_newsletter_opt_in" value="1" <?php checked($form_values['member_newsletter_opt_in'], true); ?> />
                            Recevoir les newsletters et communications par email
                        </label>
                        <label style="display:block;">
                            <input type="checkbox" name="member_sms_opt_in" value="1" <?php checked($form_values['member_sms_opt_in'], true); ?> />
                            Recevoir des SMS informatifs de la MJ
                        </label>
                        <p class="description">Ces réglages sont également visibles dans l’espace membre.</p>
                    </td>
                </tr>
                <tr class="js-role-jeune">
                    <th><label for="member_birth_date">Date de naissance</label></th>
                    <td>
                        <input type="date" id="member_birth_date" name="member_birth_date" value="<?php echo esc_attr($form_values['member_birth_date']); ?>" class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th><label for="member_address">Adresse</label></th>
                    <td>
                        <input type="text" id="member_address" name="member_address" value="<?php echo esc_attr($form_values['member_address']); ?>" class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th><label for="member_city">Ville</label></th>
                    <td>
                        <input type="text" id="member_city" name="member_city" value="<?php echo esc_attr($form_values['member_city']); ?>" class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th><label for="member_postal">Code postal</label></th>
                    <td>
                        <input type="text" id="member_postal" name="member_postal" value="<?php echo esc_attr($form_values['member_postal']); ?>" class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th><label for="member_description_courte">Description courte</label></th>
                    <td>
                        <input type="text" id="member_description_courte" name="member_description_courte" value="<?php echo esc_attr($form_values['member_description_courte']); ?>" class="regular-text" maxlength="255" />
                        <p class="description">Texte court utilisé dans les listes ou aperçus (255 caractères max).</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="member_description_longue">Description longue</label></th>
                    <td>
                        <textarea id="member_description_longue" name="member_description_longue" rows="5" class="large-text"><?php echo esc_textarea($form_values['member_description_longue']); ?></textarea>
                        <p class="description">Contenu libre qui sera visible sur la fiche détaillée du membre (HTML de base autorisé).</p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="mj-form-section js-role-jeune" id="jeune-extra-section">
            <h3>Autonomie & Responsable</h3>

            <table class="form-table">
                <tr id="autonomy-row">
                    <th><label for="member_is_autonomous">Jeune autonome</label></th>
                    <td>
                        <label>
                            <input type="checkbox" id="member_is_autonomous" name="member_is_autonomous" <?php checked($form_values['member_is_autonomous'], true); ?> />
                            Ce jeune est autonome (pas de tuteur à rattacher)
                        </label>
                    </td>
                </tr>

                <tr class="js-guardian-block" id="guardian-mode-row">
                    <th>Tuteur</th>
                    <td>
                        <label style="display:block;margin-bottom:6px;">
                            <input type="radio" name="guardian_mode" value="existing" <?php checked($form_values['guardian_mode'], 'existing'); ?> />
                            Associer un tuteur existant
                        </label>
                        <label style="display:block;">
                            <input type="radio" name="guardian_mode" value="new" <?php checked($form_values['guardian_mode'], 'new'); ?> />
                            Créer un nouveau tuteur
                        </label>
                        <p class="description">Un même tuteur peut être lié à plusieurs jeunes (frères et sœurs, par exemple).</p>
                    </td>
                </tr>

                <tr class="js-guardian-block js-guardian-existing">
                    <th><label for="guardian_id">Tuteur existant</label></th>
                    <td>
                        <select id="guardian_id" name="guardian_id" class="regular-text">
                            <option value="">— Sélectionner —</option>
                            <?php foreach ($guardians as $guardian_option) :
                                $guardian_label = trim($guardian_option->last_name . ' ' . $guardian_option->first_name);
                                $guardian_label = $guardian_label !== '' ? $guardian_label : 'Tuteur';
                                $guardian_email = !empty($guardian_option->email) ? ' — ' . $guardian_option->email : '';
                                ?>
                                <option value="<?php echo esc_attr($guardian_option->id); ?>" <?php selected($form_values['guardian_id'], intval($guardian_option->id)); ?>>
                                    <?php echo esc_html($guardian_label . $guardian_email); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>

                <tr class="js-guardian-block js-guardian-new">
                    <th><label for="guardian_last_name">Nom du tuteur *</label></th>
                    <td>
                        <input type="text" id="guardian_last_name" name="guardian_last_name" value="<?php echo esc_attr($form_values['guardian_last_name']); ?>" class="regular-text" />
                    </td>
                </tr>
                <tr class="js-guardian-block js-guardian-new">
                    <th><label for="guardian_first_name">Prénom du tuteur *</label></th>
                    <td>
                        <input type="text" id="guardian_first_name" name="guardian_first_name" value="<?php echo esc_attr($form_values['guardian_first_name']); ?>" class="regular-text" />
                    </td>
                </tr>
                <tr class="js-guardian-block js-guardian-new">
                    <th><label for="guardian_email">Email du tuteur *</label></th>
                    <td>
                        <input type="email" id="guardian_email" name="guardian_email" value="<?php echo esc_attr($form_values['guardian_email']); ?>" class="regular-text" />
                    </td>
                </tr>
                <tr class="js-guardian-block js-guardian-new">
                    <th><label for="guardian_phone">Téléphone du tuteur</label></th>
                    <td>
                        <input type="tel" id="guardian_phone" name="guardian_phone" value="<?php echo esc_attr($form_values['guardian_phone']); ?>" class="regular-text" />
                    </td>
                </tr>
                <tr class="js-guardian-block js-guardian-new">
                    <th><label for="guardian_address">Adresse du tuteur</label></th>
                    <td>
                        <input type="text" id="guardian_address" name="guardian_address" value="<?php echo esc_attr($form_values['guardian_address']); ?>" class="regular-text" />
                    </td>
                </tr>
                <tr class="js-guardian-block js-guardian-new">
                    <th><label for="guardian_city">Ville du tuteur</label></th>
                    <td>
                        <input type="text" id="guardian_city" name="guardian_city" value="<?php echo esc_attr($form_values['guardian_city']); ?>" class="regular-text" />
                    </td>
                </tr>
                <tr class="js-guardian-block js-guardian-new">
                    <th><label for="guardian_postal">Code postal du tuteur</label></th>
                    <td>
                        <input type="text" id="guardian_postal" name="guardian_postal" value="<?php echo esc_attr($form_values['guardian_postal']); ?>" class="regular-text" />
                    </td>
                </tr>
            </table>
        </div>

        <div class="mj-form-section">
            <h3>Accès en ligne</h3>

            <table class="form-table">
                <tr>
                    <th><label for="member_account_login">Identifiant WordPress</label></th>
                    <td>
                        <input type="text" id="member_account_login" name="member_account_login" value="<?php echo esc_attr($form_values['member_account_login']); ?>" class="regular-text" autocomplete="username" />
                        <p class="description">Laissez vide pour conserver l’identifiant actuel.</p>
                        <?php if ($member && !empty($member->wp_user_id)) :
                            $linked_user = get_user_by('id', (int) $member->wp_user_id);
                            if ($linked_user) : ?>
                                <p class="description">Identifiant actuel : <code><?php echo esc_html($linked_user->user_login); ?></code></p>
                            <?php endif;
                        endif; ?>
                    </td>
                </tr>
                <tr>
                    <th><label for="member_account_password">Nouveau mot de passe</label></th>
                    <td>
                        <input type="password" id="member_account_password" name="member_account_password" class="regular-text" autocomplete="new-password" />
                        <p class="description">Minimum <?php echo (int) apply_filters('mj_member_min_password_length', 8); ?> caractères.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="member_account_password_confirm">Confirmer le mot de passe</label></th>
                    <td>
                        <input type="password" id="member_account_password_confirm" name="member_account_password_confirm" class="regular-text" autocomplete="new-password" />
                    </td>
                </tr>
            </table>
        </div>

        <div class="mj-form-section">
            <h3>Statut & Cotisation</h3>

            <table class="form-table">
                <tr>
                    <th><label for="status">Statut *</label></th>
                    <td>
                        <select id="status" name="status" class="regular-text" required>
                            <option value="<?php echo esc_attr(MjMembers_CRUD::STATUS_ACTIVE); ?>" <?php selected($form_values['status'], MjMembers_CRUD::STATUS_ACTIVE); ?>>Actif</option>
                            <option value="<?php echo esc_attr(MjMembers_CRUD::STATUS_INACTIVE); ?>" <?php selected($form_values['status'], MjMembers_CRUD::STATUS_INACTIVE); ?>>Inactif</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="requires_payment">Cotisation requise</label></th>
                    <td>
                        <label>
                            <input type="checkbox" id="requires_payment" name="requires_payment" <?php checked($form_values['requires_payment'], true); ?> />
                            Ce membre doit régler la cotisation annuelle
                        </label>
                    </td>
                </tr>
                <?php if ($member && !empty($member->wp_user_id)) :
                    $linked_user = get_user_by('id', (int) $member->wp_user_id);
                    $user_label = $linked_user ? sprintf('%s (%s)', $linked_user->display_name, $linked_user->user_email) : sprintf('Utilisateur #%d', (int) $member->wp_user_id);
                    $user_link = $linked_user ? get_edit_user_link($linked_user->ID) : '';
                ?>
                <tr>
                    <th>Compte WordPress lié</th>
                    <td>
                        <?php if ($user_link) : ?>
                            <a href="<?php echo esc_url($user_link); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($user_label); ?></a>
                        <?php else : ?>
                            <?php echo esc_html($user_label); ?>
                        <?php endif; ?>
                        <p class="description">Utilisez l'action « Compte WP » dans la liste des membres pour modifier le rôle ou actualiser le lien.</p>
                    </td>
                </tr>
                <?php endif; ?>
                <tr>
                    <th><label for="date_last_payement">Dernier paiement</label></th>
                    <td>
                        <input type="date" id="date_last_payement" name="date_last_payement" value="<?php echo esc_attr($form_values['date_last_payement']); ?>" class="regular-text" />
                        <p class="description">Format : AAAA-MM-JJ</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="photo">Photo de profil</label></th>
                    <td>
                        <div class="mj-photo-form-container">
                            <?php
                            if ($member && !empty($member->photo_id)) {
                                $image_url = wp_get_attachment_image_src($member->photo_id, 'thumbnail');
                                if (!empty($image_url[0])) {
                                    echo '<img src="' . esc_url($image_url[0]) . '" alt="Photo" class="mj-member-photo" style="width:100px;height:100px;border-radius:50%;object-fit:cover;margin-bottom:10px;display:block;">';
                                }
                                echo '<button type="button" class="button button-small mj-photo-upload-btn" data-member-id="' . esc_attr($member->id) . '">📷 Modifier la photo</button> ';
                                echo '<button type="button" class="button button-small mj-delete-photo-btn" data-member-id="' . esc_attr($member->id) . '">✕ Supprimer</button>';
                            } else {
                                echo '<p style="color:#999;">Pas de photo</p>';
                                if ($action === 'edit' && $member) {
                                    echo '<button type="button" class="button button-small mj-photo-upload-btn" data-member-id="' . esc_attr($member->id) . '">📷 Ajouter une photo</button>';
                                } else {
                                    echo '<p class="description">Vous pourrez ajouter une photo après la création du membre.</p>';
                                }
                            }
                            ?>
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="mj-form-actions">
            <?php submit_button('Enregistrer', 'primary', 'submit'); ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=mj_members')); ?>" class="button">Annuler</a>
        </div>
    </form>
</div>

<style>
.mj-form-container {
    background: #fff;
    padding: 20px;
    border-radius: 5px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    max-width: 900px;
}

.mj-form-section {
    margin: 30px 0;
    padding: 20px;
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 3px;
}

.mj-form-section h3 {
    margin-top: 0;
    border-bottom: 2px solid #0073aa;
    padding-bottom: 10px;
}

.mj-form-actions {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #ddd;
}

.mj-form-actions .button {
    margin-right: 10px;
}

.js-guardian-new {
    display: none;
}
</style>

<script>
(function() {
    function updateRoleSections() {
        const roleSelect = document.getElementById('member_role');
        const role = roleSelect ? roleSelect.value : 'jeune';
        const jeuneElements = document.querySelectorAll('.js-role-jeune');
        const autonomyRow = document.getElementById('autonomy-row');
        const isAutonomousCheckbox = document.getElementById('member_is_autonomous');

        jeuneElements.forEach(function(section) {
            section.style.display = (role === 'jeune') ? '' : 'none';
        });

        if (role !== 'jeune' && isAutonomousCheckbox) {
            isAutonomousCheckbox.checked = true;
        }

        if (autonomyRow) {
            autonomyRow.style.display = (role === 'jeune') ? '' : 'none';
        }

        updateAutonomyBlock();
        updateEmailRequirement();
    }

    function updateGuardianMode() {
        const roleSelect = document.getElementById('member_role');
        const role = roleSelect ? roleSelect.value : 'jeune';
        const guardianModeRow = document.getElementById('guardian-mode-row');
        const guardianBlocks = document.querySelectorAll('.js-guardian-block');
        const existingRow = document.querySelector('.js-guardian-existing');
        const newRows = document.querySelectorAll('.js-guardian-new');
        const isAutonomousCheckbox = document.getElementById('member_is_autonomous');

        const isJeune = role === 'jeune';
        const isAutonomous = isAutonomousCheckbox ? isAutonomousCheckbox.checked : false;

        if (!isJeune || isAutonomous) {
            guardianBlocks.forEach(function(row) {
                row.style.display = 'none';
            });
            return;
        }

        const guardianModeInput = document.querySelector('input[name="guardian_mode"]:checked');
        const mode = guardianModeInput ? guardianModeInput.value : 'existing';

        if (guardianModeRow) {
            guardianModeRow.style.display = '';
        }

        if (existingRow) {
            existingRow.style.display = (mode === 'existing') ? '' : 'none';
        }

        newRows.forEach(function(row) {
            row.style.display = (mode === 'new') ? '' : 'none';
        });
    }

    function updateAutonomyBlock() {
        const roleSelect = document.getElementById('member_role');
        const role = roleSelect ? roleSelect.value : 'jeune';
        const isAutonomousCheckbox = document.getElementById('member_is_autonomous');
        const guardianBlocks = document.querySelectorAll('.js-guardian-block');
        const guardianId = document.getElementById('guardian_id');

        if (!isAutonomousCheckbox) {
            updateGuardianMode();
            return;
        }

        if (role !== 'jeune' || isAutonomousCheckbox.checked) {
            guardianBlocks.forEach(function(row) {
                row.style.display = 'none';
            });
            if (guardianId) {
                guardianId.value = '';
            }
            return;
        }

        guardianBlocks.forEach(function(row) {
            row.style.display = '';
        });
        updateGuardianMode();
    }

    function updateEmailRequirement() {
        const roleSelect = document.getElementById('member_role');
        const role = roleSelect ? roleSelect.value : 'jeune';
        const emailInput = document.getElementById('member_email');
        const emailLabel = document.getElementById('member_email_label');

        if (!emailInput || !emailLabel) {
            return;
        }

        if (role === 'jeune') {
            emailInput.removeAttribute('required');
            emailLabel.textContent = 'Email (optionnel)';
        } else {
            emailInput.setAttribute('required', 'required');
            emailLabel.textContent = 'Email *';
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const roleSelect = document.getElementById('member_role');
        const isAutonomousCheckbox = document.getElementById('member_is_autonomous');
        const guardianModeInputs = document.querySelectorAll('input[name="guardian_mode"]');

        if (roleSelect) {
            roleSelect.addEventListener('change', updateRoleSections);
        }
        if (isAutonomousCheckbox) {
            isAutonomousCheckbox.addEventListener('change', updateAutonomyBlock);
        }
        guardianModeInputs.forEach(function(radio) {
            radio.addEventListener('change', updateGuardianMode);
        });

        updateRoleSections();
    });
})();
</script>
