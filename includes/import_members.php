<?php

use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

function mj_member_import_members_page() {
    $capability = Config::capability();

    if (!current_user_can($capability)) {
        wp_die(__('Vous n\'avez pas les droits suffisants pour importer des membres.', 'mj-member'));
    }

    $view_stage = 'upload';
    $state = array(
        'errors' => array(),
        'notices' => array(),
        'warnings' => array(),
        'headers' => array(),
        'preview' => array(),
        'token' => '',
        'mapping' => array(),
        'duplicate_mode' => 'skip',
        'report' => null,
        'original_name' => '',
    );

    $available_fields = mj_member_import_get_available_fields();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $step = isset($_POST['import_step']) ? sanitize_key(wp_unslash($_POST['import_step'])) : 'upload';

        if ($step === 'handle_upload') {
            check_admin_referer('mj_member_import_upload', 'mj_member_import_nonce');
            $upload_result = mj_member_import_handle_upload();
            if (is_wp_error($upload_result)) {
                $state['errors'][] = $upload_result->get_error_message();
            } else {
                $state['headers'] = $upload_result['headers'];
                $state['preview'] = $upload_result['preview'];
                $state['token'] = $upload_result['token'];
                $state['original_name'] = $upload_result['original_name'];
                $view_stage = 'mapping';
            }
        } elseif ($step === 'process_import') {
            check_admin_referer('mj_member_import_process', 'mj_member_import_nonce');
            $token = isset($_POST['import_token']) ? sanitize_text_field(wp_unslash($_POST['import_token'])) : '';
            $duplicate_mode = isset($_POST['duplicate_mode']) ? sanitize_key(wp_unslash($_POST['duplicate_mode'])) : 'skip';
            $mapping_input = isset($_POST['mapping']) && is_array($_POST['mapping']) ? $_POST['mapping'] : array();
            $process = mj_member_import_process($token, $duplicate_mode, $mapping_input, $available_fields);

            if (is_wp_error($process)) {
                $state['errors'][] = $process->get_error_message();
                $recovery = $process->get_error_data();
                if (is_array($recovery)) {
                    $state['headers'] = isset($recovery['headers']) ? $recovery['headers'] : array();
                    $state['preview'] = isset($recovery['preview']) ? $recovery['preview'] : array();
                    $state['token'] = isset($recovery['token']) ? $recovery['token'] : '';
                    $state['mapping'] = isset($recovery['mapping']) ? $recovery['mapping'] : array();
                    $state['duplicate_mode'] = isset($recovery['duplicate_mode']) ? $recovery['duplicate_mode'] : 'skip';
                    $state['original_name'] = isset($recovery['original_name']) ? $recovery['original_name'] : '';
                    $view_stage = 'mapping';
                }
            } else {
                $state['report'] = $process['report'];
                $state['warnings'] = array();
                $state['notices'][] = $process['message'];
                $state['original_name'] = $process['original_name'];
                $view_stage = 'report';
            }
        }
    }

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__('Import CSV membres', 'mj-member') . '</h1>';

    foreach ($state['errors'] as $error_message) {
        echo '<div class="notice notice-error"><p>' . esc_html($error_message) . '</p></div>';
    }
    foreach ($state['notices'] as $notice_message) {
        echo '<div class="notice notice-success"><p>' . esc_html($notice_message) . '</p></div>';
    }
    foreach ($state['warnings'] as $warning_message) {
        echo '<div class="notice notice-warning"><p>' . esc_html($warning_message) . '</p></div>';
    }

    if ($view_stage === 'mapping') {
        mj_member_import_render_mapping_form($state, $available_fields);
    } elseif ($view_stage === 'report') {
        mj_member_import_render_report($state['report'], $state['original_name']);
    } else {
        mj_member_import_render_upload_form();
    }

    echo '</div>';
}

function mj_member_import_render_upload_form() {
    ?>
    <form method="post" enctype="multipart/form-data">
        <?php wp_nonce_field('mj_member_import_upload', 'mj_member_import_nonce'); ?>
        <input type="hidden" name="import_step" value="handle_upload" />
        <p><?php esc_html_e('Sélectionnez un fichier CSV contenant les membres à importer. La première ligne doit contenir les en-têtes.', 'mj-member'); ?></p>
        <p>
            <label for="mj-member-import-file" class="screen-reader-text"><?php esc_html_e('Fichier CSV', 'mj-member'); ?></label>
            <input type="file" id="mj-member-import-file" name="csv_file" accept=".csv,text/csv" required />
        </p>
        <p class="submit">
            <button type="submit" class="button button-primary"><?php esc_html_e('Analyser le CSV', 'mj-member'); ?></button>
        </p>
    </form>
    <?php
}

function mj_member_import_render_mapping_form($state, $available_fields) {
    $headers = $state['headers'];
    $preview = $state['preview'];
    $token = $state['token'];
    $mapping = $state['mapping'];
    $duplicate_mode = $state['duplicate_mode'];
    $original_name = $state['original_name'];
    ?>
    <h2><?php esc_html_e('Etape 2 - Associer les colonnes', 'mj-member'); ?></h2>
    <?php if ($original_name) : ?>
        <p><strong><?php esc_html_e('Fichier analysé :', 'mj-member'); ?></strong> <?php echo esc_html($original_name); ?></p>
    <?php endif; ?>
    <form method="post">
        <?php wp_nonce_field('mj_member_import_process', 'mj_member_import_nonce'); ?>
        <input type="hidden" name="import_step" value="process_import" />
        <input type="hidden" name="import_token" value="<?php echo esc_attr($token); ?>" />

        <table class="widefat striped">
            <thead>
            <tr>
                <th><?php esc_html_e('Champ membre', 'mj-member'); ?></th>
                <th><?php esc_html_e('Colonne CSV', 'mj-member'); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($available_fields as $field_key => $field_label) :
                $selected = isset($mapping[$field_key]) ? $mapping[$field_key] : '';
                ?>
                <tr>
                    <td><?php echo esc_html($field_label); ?></td>
                    <td>
                        <select name="mapping[<?php echo esc_attr($field_key); ?>]">
                            <option value=""><?php esc_html_e('Ignorer', 'mj-member'); ?></option>
                            <?php foreach ($headers as $index => $header_label) :
                                $value = (string) $index;
                                ?>
                                <option value="<?php echo esc_attr($value); ?>" <?php selected($selected, $value); ?>>
                                    <?php echo esc_html($header_label !== '' ? $header_label : sprintf(__('Colonne %d', 'mj-member'), $index + 1)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <p>
            <label for="mj-member-duplicate-mode">
                <strong><?php esc_html_e('Gestion des doublons (basé sur l\'email)', 'mj-member'); ?></strong>
            </label>
            <select id="mj-member-duplicate-mode" name="duplicate_mode">
                <option value="skip" <?php selected($duplicate_mode, 'skip'); ?>><?php esc_html_e('Ignorer les doublons', 'mj-member'); ?></option>
                <option value="update" <?php selected($duplicate_mode, 'update'); ?>><?php esc_html_e('Mettre à jour les membres existants', 'mj-member'); ?></option>
            </select>
        </p>

        <?php if (!empty($preview)) : ?>
            <h3><?php esc_html_e('Aperçu des premières lignes', 'mj-member'); ?></h3>
            <table class="widefat striped">
                <thead>
                <tr>
                    <?php foreach ($headers as $header_label) : ?>
                        <th><?php echo esc_html($header_label !== '' ? $header_label : __('(vide)', 'mj-member')); ?></th>
                    <?php endforeach; ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($preview as $row) : ?>
                    <tr>
                        <?php foreach ($headers as $index => $unused) :
                            $value = isset($row[$index]) ? $row[$index] : '';
                            ?>
                            <td><?php echo esc_html($value); ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <p class="submit">
            <button type="submit" class="button button-primary"><?php esc_html_e('Lancer l\'import', 'mj-member'); ?></button>
            <a class="button" href="<?php echo esc_url(add_query_arg(array())); ?>"><?php esc_html_e('Annuler', 'mj-member'); ?></a>
        </p>
    </form>
    <?php
}

function mj_member_import_render_report($report, $original_name) {
    if (!$report) {
        return;
    }
    ?>
    <h2><?php esc_html_e('Resultat de l\'import', 'mj-member'); ?></h2>
    <?php if ($original_name) : ?>
        <p><strong><?php esc_html_e('Fichier traité :', 'mj-member'); ?></strong> <?php echo esc_html($original_name); ?></p>
    <?php endif; ?>
    <ul>
        <li><?php printf(esc_html__('Lignes analysées : %d', 'mj-member'), intval($report['total'])); ?></li>
        <li><?php printf(esc_html__('Nouveaux membres : %d', 'mj-member'), intval($report['created'])); ?></li>
        <li><?php printf(esc_html__('Mises à jour : %d', 'mj-member'), intval($report['updated'])); ?></li>
        <li><?php printf(esc_html__('Doublons ignorés : %d', 'mj-member'), intval($report['duplicate_skipped'])); ?></li>
        <li><?php printf(esc_html__('Lignes ignorées (erreurs) : %d', 'mj-member'), intval(count($report['errors']))); ?></li>
    </ul>

    <?php if (!empty($report['errors'])) : ?>
        <h3><?php esc_html_e('Erreurs rencontrées', 'mj-member'); ?></h3>
        <ol>
            <?php foreach ($report['errors'] as $error_line) : ?>
                <li><?php echo esc_html($error_line); ?></li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>

    <?php if (!empty($report['warnings'])) : ?>
        <h3><?php esc_html_e('Avertissements', 'mj-member'); ?></h3>
        <ul>
            <?php foreach ($report['warnings'] as $warning_line) : ?>
                <li><?php echo esc_html($warning_line); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <p class="submit">
        <a class="button button-primary" href="<?php echo esc_url(add_query_arg(array())); ?>"><?php esc_html_e('Nouvel import', 'mj-member'); ?></a>
    </p>
    <?php
}

function mj_member_import_handle_upload() {
    if (!isset($_FILES['csv_file']) || !is_array($_FILES['csv_file'])) {
        return new WP_Error('mj-import-no-file', __('Aucun fichier reçu.', 'mj-member'));
    }

    $file = $_FILES['csv_file'];
    if (!empty($file['error'])) {
        return new WP_Error('mj-import-upload-error', sprintf(__('Erreur lors du téléversement (code %d).', 'mj-member'), (int) $file['error']));
    }

    if (empty($file['tmp_name']) || !file_exists($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return new WP_Error('mj-import-missing-file', __('Le fichier temporaire est introuvable. Vérifiez la configuration PHP (file_uploads).', 'mj-member'));
    }

    $file_size = filesize($file['tmp_name']);
    if ($file_size === false || $file_size <= 0) {
        $sample = file_get_contents($file['tmp_name'], false, null, 0, 2048);
        if ($sample === false) {
            $sample = '';
        }
        if (trim($sample) === '') {
            $upload_limit = ini_get('upload_max_filesize');
            $post_limit = ini_get('post_max_size');
            $file_uploads = ini_get('file_uploads');
            $details = sprintf('upload_max_filesize=%s, post_max_size=%s, file_uploads=%s', $upload_limit, $post_limit, $file_uploads);
            return new WP_Error('mj-import-empty-file', sprintf(__('Le fichier reçu est vide. Vérifiez upload_max_filesize et post_max_size dans php.ini. (%s)', 'mj-member'), $details));
        }
        $file_size = strlen($sample);
    }

    $check = wp_check_filetype_and_ext($file['tmp_name'], $file['name'], array('csv' => 'text/csv', 'txt' => 'text/plain'));
    if (empty($check['ext'])) {
        return new WP_Error('mj-import-invalid-type', __('Format non pris en charge. Importez un fichier CSV (.csv).', 'mj-member'));
    }

    $temp_destination = wp_tempnam($file['name']);
    if (!$temp_destination) {
        return new WP_Error('mj-import-temp-error', __('Impossible de préparer un fichier temporaire pour l\'import.', 'mj-member'));
    }

    $moved = move_uploaded_file($file['tmp_name'], $temp_destination);
    if (!$moved) {
        $copied = @copy($file['tmp_name'], $temp_destination);
        if (!$copied) {
            @unlink($temp_destination);
            return new WP_Error('mj-import-move-failed', __('Impossible de copier le fichier importé. Vérifiez les permissions du système de fichiers.', 'mj-member'));
        }
    }

    $file_path = $temp_destination;

    $delimiter = mj_member_import_detect_delimiter($file_path);
    $parsed = mj_member_import_read_headers_and_preview($file_path, $delimiter);

    if (empty($parsed['headers'])) {
        wp_delete_file($file_path);
        return new WP_Error('mj-import-no-headers', __('Impossible de lire les en-têtes du CSV.', 'mj-member'));
    }

    $token = wp_generate_password(20, false);
    $transient_key = mj_member_import_build_transient_key($token);
    $stored = array(
        'file' => $file_path,
        'delimiter' => $delimiter,
        'headers' => $parsed['headers'],
        'preview' => $parsed['preview'],
        'original_name' => isset($file['name']) ? sanitize_file_name($file['name']) : basename($file_path),
        'mime_type' => $check['type'],
        'size' => $file_size,
    );

    set_transient($transient_key, $stored, 30 * MINUTE_IN_SECONDS);

    return array(
        'headers' => $parsed['headers'],
        'preview' => $parsed['preview'],
        'token' => $token,
        'original_name' => $stored['original_name'],
    );
}

function mj_member_import_process($token, $duplicate_mode, $mapping_input, $available_fields) {
    if ($token === '') {
        return new WP_Error('mj-import-missing-token', __('Token d\'import manquant, veuillez recommencer.', 'mj-member'));
    }

    $transient_key = mj_member_import_build_transient_key($token);
    $stored = get_transient($transient_key);
    if (!$stored || empty($stored['file']) || !file_exists($stored['file'])) {
        return new WP_Error('mj-import-expired', __('La session d\'import a expiré. Veuillez téléverser à nouveau le fichier.', 'mj-member'));
    }

    $headers = isset($stored['headers']) ? $stored['headers'] : array();
    $preview = isset($stored['preview']) ? $stored['preview'] : array();
    $delimiter = isset($stored['delimiter']) ? $stored['delimiter'] : ',';
    $original_name = isset($stored['original_name']) ? $stored['original_name'] : basename($stored['file']);

    $mapping = array();
    foreach ($mapping_input as $field_key => $value) {
        $field_key = sanitize_key($field_key);
        if (!array_key_exists($field_key, $available_fields)) {
            continue;
        }
        $index = is_numeric($value) ? (int) $value : null;
        if ($index === null) {
            continue;
        }
        if (!isset($headers[$index])) {
            continue;
        }
        $mapping[$field_key] = $index;
    }

    $required_fields = array('first_name', 'last_name');
    $missing_required = array();
    foreach ($required_fields as $required_field) {
        if (!isset($mapping[$required_field])) {
            $missing_required[] = $available_fields[$required_field];
        }
    }

    if (!empty($missing_required)) {
        $error = new WP_Error('mj-import-missing-mapping', sprintf(__('Merci d\'associer les colonnes obligatoires : %s.', 'mj-member'), implode(', ', $missing_required)));
        $error->add_data(array(
            'headers' => $headers,
            'preview' => $preview,
            'token' => $token,
            'mapping' => $mapping_input,
            'duplicate_mode' => $duplicate_mode,
            'original_name' => $original_name,
        ));
        return $error;
    }

    if ($duplicate_mode !== 'update') {
        $duplicate_mode = 'skip';
    }

    $handle = fopen($stored['file'], 'r');
    if (!$handle) {
        return new WP_Error('mj-import-open-error', __('Impossible d\'ouvrir le fichier importé.', 'mj-member'));
    }

    $report = array(
        'total' => 0,
        'created' => 0,
        'updated' => 0,
        'duplicate_skipped' => 0,
        'errors' => array(),
        'warnings' => array(),
    );
    $header_row = fgetcsv($handle, 0, $delimiter);
    if ($header_row === false) {
        fclose($handle);
        return new WP_Error('mj-import-empty-file', __('Le fichier ne contient aucune donnée.', 'mj-member'));
    }

    $line_number = 1;
    while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
        $line_number++;
        $report['total']++;

        if (mj_member_import_row_is_empty($row)) {
            $report['duplicate_skipped']++;
            continue;
        }

        $payload = array();
        $row_warnings = array();

        $first_name = mj_member_import_get_mapped_value($row, $mapping['first_name']);
        $last_name = mj_member_import_get_mapped_value($row, $mapping['last_name']);
        if ($first_name === '' || $last_name === '') {
            $report['errors'][] = sprintf(__('Ligne %d : prénom ou nom manquant.', 'mj-member'), $line_number);
            continue;
        }
        $payload['first_name'] = $first_name;
        $payload['last_name'] = $last_name;

        if (isset($mapping['email'])) {
            $raw_email = mj_member_import_get_mapped_value($row, $mapping['email']);
            if ($raw_email !== '') {
                $email = sanitize_email($raw_email);
                if ($email && is_email($email)) {
                    $payload['email'] = $email;
                } else {
                    $row_warnings[] = __('Email invalide, le membre sera importé sans email.', 'mj-member');
                }
            }
        }

        if (isset($mapping['phone'])) {
            $phone = mj_member_import_get_mapped_value($row, $mapping['phone']);
            if ($phone !== '') {
                $payload['phone'] = $phone;
            }
        }

        if (isset($mapping['birth_date'])) {
            $birth_raw = mj_member_import_get_mapped_value($row, $mapping['birth_date']);
            if ($birth_raw !== '') {
                $birth_date = mj_member_import_parse_date($birth_raw);
                if ($birth_date !== null) {
                    $payload['birth_date'] = $birth_date;
                } else {
                    $row_warnings[] = __('Date de naissance invalide ignorée.', 'mj-member');
                }
            }
        }

        if (isset($mapping['role'])) {
            $role_raw = mj_member_import_get_mapped_value($row, $mapping['role']);
            if ($role_raw !== '') {
                $payload['role'] = mj_member_import_normalize_role($role_raw, $row_warnings);
            }
        }

        if (isset($mapping['status'])) {
            $status_raw = mj_member_import_get_mapped_value($row, $mapping['status']);
            if ($status_raw !== '') {
                $payload['status'] = mj_member_import_normalize_status($status_raw, $row_warnings);
            }
        }

        if (isset($mapping['requires_payment'])) {
            $requires_raw = mj_member_import_get_mapped_value($row, $mapping['requires_payment']);
            if ($requires_raw !== '') {
                $requires_payment = mj_member_import_parse_bool($requires_raw);
                if ($requires_payment !== null) {
                    $payload['requires_payment'] = $requires_payment ? 1 : 0;
                } else {
                    $row_warnings[] = __('Valeur de cotisation invalide ignorée.', 'mj-member');
                }
            }
        }

        if (isset($mapping['address'])) {
            $address = mj_member_import_get_mapped_value($row, $mapping['address']);
            if ($address !== '') {
                $payload['address'] = $address;
            }
        }

        if (isset($mapping['city'])) {
            $city = mj_member_import_get_mapped_value($row, $mapping['city']);
            if ($city !== '') {
                $payload['city'] = $city;
            }
        }

        if (isset($mapping['postal_code'])) {
            $postal = mj_member_import_get_mapped_value($row, $mapping['postal_code']);
            if ($postal !== '') {
                $payload['postal_code'] = $postal;
            }
        }

        if (isset($mapping['notes'])) {
            $notes = mj_member_import_get_mapped_value($row, $mapping['notes']);
            if ($notes !== '') {
                $payload['notes'] = $notes;
            }
        }

        if (isset($mapping['description_courte'])) {
            $desc_short = mj_member_import_get_mapped_value($row, $mapping['description_courte']);
            if ($desc_short !== '') {
                $payload['description_courte'] = $desc_short;
            }
        }

        if (isset($mapping['description_longue'])) {
            $desc_long = mj_member_import_get_mapped_value($row, $mapping['description_longue']);
            if ($desc_long !== '') {
                $payload['description_longue'] = $desc_long;
            }
        }

        if (isset($mapping['newsletter_opt_in'])) {
            $newsletter_raw = mj_member_import_get_mapped_value($row, $mapping['newsletter_opt_in']);
            if ($newsletter_raw !== '') {
                $newsletter = mj_member_import_parse_bool($newsletter_raw);
                if ($newsletter !== null) {
                    $payload['newsletter_opt_in'] = $newsletter ? 1 : 0;
                } else {
                    $row_warnings[] = __('Valeur newsletter invalide ignorée.', 'mj-member');
                }
            }
        }

        if (isset($mapping['sms_opt_in'])) {
            $sms_raw = mj_member_import_get_mapped_value($row, $mapping['sms_opt_in']);
            if ($sms_raw !== '') {
                $sms = mj_member_import_parse_bool($sms_raw);
                if ($sms !== null) {
                    $payload['sms_opt_in'] = $sms ? 1 : 0;
                } else {
                    $row_warnings[] = __('Valeur SMS invalide ignorée.', 'mj-member');
                }
            }
        }

        if (isset($mapping['date_last_payement'])) {
            $payment_raw = mj_member_import_get_mapped_value($row, $mapping['date_last_payement']);
            if ($payment_raw !== '') {
                $payment_date = mj_member_import_parse_datetime($payment_raw);
                if ($payment_date !== null) {
                    $payload['date_last_payement'] = $payment_date;
                } else {
                    $row_warnings[] = __('Date de paiement invalide ignorée.', 'mj-member');
                }
            }
        }

        $guardian_id = null;
        if (isset($mapping['guardian_email'])) {
            $guardian_raw = mj_member_import_get_mapped_value($row, $mapping['guardian_email']);
            if ($guardian_raw !== '') {
                $guardian_email = sanitize_email($guardian_raw);
                if ($guardian_email && is_email($guardian_email)) {
                    $guardian = MjMembers_CRUD::getByEmail($guardian_email);
                    if ($guardian && $guardian->role === MjMembers_CRUD::ROLE_TUTEUR) {
                        $guardian_id = (int) $guardian->id;
                        $payload['guardian_id'] = $guardian_id;
                    } else {
                        $row_warnings[] = __('Tuteur introuvable, importé comme autonome.', 'mj-member');
                    }
                } else {
                    $row_warnings[] = __('Email tuteur invalide ignoré.', 'mj-member');
                }
            }
        }

        if (!isset($payload['role']) || $payload['role'] === '') {
            $payload['role'] = MjMembers_CRUD::ROLE_JEUNE;
        }

        if ($payload['role'] === MjMembers_CRUD::ROLE_JEUNE) {
            if ($guardian_id) {
                $payload['is_autonomous'] = 0;
            } else {
                $payload['is_autonomous'] = 1;
                $payload['guardian_id'] = null;
            }
            if (!isset($payload['requires_payment'])) {
                $payload['requires_payment'] = 1;
            }
        }

        $existing = null;
        if (isset($payload['email']) && $payload['email'] !== '') {
            $existing = MjMembers_CRUD::getByEmail($payload['email']);
        }

        if ($existing && $duplicate_mode === 'skip') {
            $report['duplicate_skipped']++;
            continue;
        }

        if ($existing && $duplicate_mode === 'update') {
            $update_payload = $payload;
            unset($update_payload['email']);
            $update_result = MjMembers_CRUD::update((int) $existing->id, $update_payload);
            if ($update_result === false) {
                $report['errors'][] = sprintf(__('Ligne %d : échec de la mise à jour.', 'mj-member'), $line_number);
                continue;
            }
            $report['updated']++;
        } else {
            $create_result = MjMembers_CRUD::create($payload);
            if (!$create_result) {
                $report['errors'][] = sprintf(__('Ligne %d : impossible de créer le membre.', 'mj-member'), $line_number);
                continue;
            }
            $report['created']++;
        }

        foreach ($row_warnings as $warning) {
            $report['warnings'][] = sprintf(__('Ligne %1$d : %2$s', 'mj-member'), $line_number, $warning);
        }
    }

    fclose($handle);
    delete_transient($transient_key);
    wp_delete_file($stored['file']);

    return array(
        'report' => $report,
        'message' => __('Import terminé.', 'mj-member'),
        'original_name' => $original_name,
    );
}

function mj_member_import_get_available_fields() {
    return array(
        'first_name' => __('Prénom (obligatoire)', 'mj-member'),
        'last_name' => __('Nom (obligatoire)', 'mj-member'),
        'email' => __('Email', 'mj-member'),
        'phone' => __('Téléphone', 'mj-member'),
        'birth_date' => __('Date de naissance', 'mj-member'),
        'role' => __('Rôle (jeune, tuteur, animateur, benevole)', 'mj-member'),
        'status' => __('Statut (active/inactive)', 'mj-member'),
        'requires_payment' => __('Cotisation requise (oui/non)', 'mj-member'),
        'address' => __('Adresse', 'mj-member'),
        'city' => __('Ville', 'mj-member'),
        'postal_code' => __('Code postal', 'mj-member'),
        'notes' => __('Notes', 'mj-member'),
        'description_courte' => __('Description courte', 'mj-member'),
        'description_longue' => __('Description longue', 'mj-member'),
        'newsletter_opt_in' => __('Newsletter (oui/non)', 'mj-member'),
        'sms_opt_in' => __('SMS (oui/non)', 'mj-member'),
        'date_last_payement' => __('Date du dernier paiement', 'mj-member'),
        'guardian_email' => __('Email du tuteur (pour les jeunes)', 'mj-member'),
    );
}

function mj_member_import_detect_delimiter($file_path) {
    $handle = fopen($file_path, 'r');
    if (!$handle) {
        return ',';
    }
    $line = fgets($handle);
    fclose($handle);
    if ($line === false) {
        return ',';
    }

    $delimiters = array(',', ';', "\t", '|');
    $max_count = 0;
    $selected = ',';
    foreach ($delimiters as $delimiter) {
        $count = substr_count($line, $delimiter);
        if ($count > $max_count) {
            $max_count = $count;
            $selected = $delimiter;
        }
    }
    return $selected;
}

function mj_member_import_read_headers_and_preview($file_path, $delimiter) {
    $headers = array();
    $preview = array();
    $handle = fopen($file_path, 'r');
    if (!$handle) {
        return compact('headers', 'preview');
    }

    $header_row = fgetcsv($handle, 0, $delimiter);
    if ($header_row !== false) {
        foreach ($header_row as $header) {
            $headers[] = mj_member_import_clean_header($header);
        }
    }

    $limit = 5;
    $count = 0;
    while ($count < $limit && ($row = fgetcsv($handle, 0, $delimiter)) !== false) {
        $preview[] = $row;
        $count++;
    }

    fclose($handle);
    return compact('headers', 'preview');
}

function mj_member_import_clean_header($header) {
    $header = (string) $header;
    $header = preg_replace('/^\xEF\xBB\xBF/', '', $header);
    return trim($header);
}

function mj_member_import_build_transient_key($token) {
    return 'mj_member_import_' . md5($token);
}

function mj_member_import_row_is_empty($row) {
    foreach ($row as $value) {
        if (trim((string) $value) !== '') {
            return false;
        }
    }
    return true;
}

function mj_member_import_get_mapped_value($row, $index) {
    if (!is_int($index)) {
        return '';
    }
    return isset($row[$index]) ? trim((string) $row[$index]) : '';
}

function mj_member_import_parse_bool($value) {
    $normalized = strtolower(remove_accents(trim((string) $value)));
    if ($normalized === '') {
        return null;
    }
    $true_values = array('1', 'true', 'yes', 'y', 'on', 'oui');
    $false_values = array('0', 'false', 'no', 'n', 'off', 'non');
    if (in_array($normalized, $true_values, true)) {
        return 1;
    }
    if (in_array($normalized, $false_values, true)) {
        return 0;
    }
    return null;
}

function mj_member_import_parse_date($value) {
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }
    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return null;
    }
    return gmdate('Y-m-d', $timestamp);
}

function mj_member_import_parse_datetime($value) {
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }
    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return null;
    }
    return gmdate('Y-m-d H:i:s', $timestamp);
}

function mj_member_import_normalize_role($value, &$row_warnings) {
    $normalized = strtolower(remove_accents(trim((string) $value)));
    $mapping = array(
        'jeune' => MjMembers_CRUD::ROLE_JEUNE,
        'jeunes' => MjMembers_CRUD::ROLE_JEUNE,
        'tuteur' => MjMembers_CRUD::ROLE_TUTEUR,
        'tutrice' => MjMembers_CRUD::ROLE_TUTEUR,
        'parent' => MjMembers_CRUD::ROLE_TUTEUR,
        'parents' => MjMembers_CRUD::ROLE_TUTEUR,
        'animateur' => MjMembers_CRUD::ROLE_ANIMATEUR,
        'animatrice' => MjMembers_CRUD::ROLE_ANIMATEUR,
        'coordinateur' => MjMembers_CRUD::ROLE_COORDINATEUR,
        'coordinatrice' => MjMembers_CRUD::ROLE_COORDINATEUR,
        'benevole' => MjMembers_CRUD::ROLE_BENEVOLE,
        'benevoles' => MjMembers_CRUD::ROLE_BENEVOLE,
    );
    if (isset($mapping[$normalized])) {
        return $mapping[$normalized];
    }
    $row_warnings[] = __('Role inconnu, remplace par "jeune".', 'mj-member');
    return MjMembers_CRUD::ROLE_JEUNE;
}

function mj_member_import_normalize_status($value, &$row_warnings) {
    $normalized = strtolower(remove_accents(trim((string) $value)));
    $mapping = array(
        'active' => MjMembers_CRUD::STATUS_ACTIVE,
        'actif' => MjMembers_CRUD::STATUS_ACTIVE,
        'inactive' => MjMembers_CRUD::STATUS_INACTIVE,
        'inactif' => MjMembers_CRUD::STATUS_INACTIVE,
    );
    if (isset($mapping[$normalized])) {
        return $mapping[$normalized];
    }
    $row_warnings[] = __('Statut inconnu, remplace par "active".', 'mj-member');
    return MjMembers_CRUD::STATUS_ACTIVE;
}
