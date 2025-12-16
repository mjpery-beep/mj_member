<?php

use Mj\Member\Classes\Crud\MjMemberHours;
use Mj\Member\Classes\Crud\MjMembers;
use Mj\Member\Core\Config;
use Mj\Member\Core\Logger;

if (!defined('ABSPATH')) {
    exit;
}

function mj_member_import_members_page() {
    $capability = Config::capability();

    if (!current_user_can($capability)) {
        wp_die(__('Vous n\'avez pas les droits suffisants pour importer des membres.', 'mj-member'));
    }

    $hoursCapability = Config::hoursCapability();
    if ($hoursCapability === '') {
        $hoursCapability = $capability;
    }

    $requestedModule = isset($_GET['module']) ? sanitize_key(wp_unslash($_GET['module'])) : 'members';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $target = isset($_POST['import_target']) ? sanitize_key(wp_unslash($_POST['import_target'])) : '';
        if ($target !== '') {
            $requestedModule = $target;
        }
    }

    $activeModule = $requestedModule === 'hours' ? 'hours' : 'members';
    $canAccessHours = current_user_can($hoursCapability);

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

    $available_fields = array();

    $hoursState = array(
        'errors' => array(),
        'notices' => array(),
        'warnings' => array(),
        'report' => null,
        'selected_member_id' => 0,
    );
    $hoursMemberOptions = array();

    if ($activeModule === 'hours') {
        $hoursMemberOptions = mj_member_import_hours_member_options();
        if (!$canAccessHours) {
            $hoursState['errors'][] = __('Vous n\'avez pas les droits suffisants pour importer des heures.', 'mj-member');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $step = isset($_POST['import_step']) ? sanitize_key(wp_unslash($_POST['import_step'])) : '';
            if ($step === 'hours_upload' && $canAccessHours) {
                check_admin_referer('mj_member_import_hours', 'mj_member_import_hours_nonce');

                $memberId = isset($_POST['member_id']) ? absint(wp_unslash($_POST['member_id'])) : 0;
                $hoursState['selected_member_id'] = $memberId;

                $memberLabels = array();
                foreach ($hoursMemberOptions as $option) {
                    $memberLabels[(int) $option['id']] = $option['label'];
                }

                if ($memberId <= 0 || !isset($memberLabels[$memberId])) {
                    $hoursState['errors'][] = __('Sélectionnez un membre valide pour l\'import.', 'mj-member');
                } elseif (!isset($_FILES['csv_file']) || !is_array($_FILES['csv_file'])) {
                    $hoursState['errors'][] = __('Aucun fichier reçu.', 'mj-member');
                } else {
                    $file = $_FILES['csv_file'];
                    if (!empty($file['error'])) {
                        $hoursState['errors'][] = sprintf(__('Erreur lors du téléversement (code %d).', 'mj-member'), (int) $file['error']);
                    } elseif (empty($file['tmp_name']) || !file_exists($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
                        $hoursState['errors'][] = __('Le fichier temporaire est introuvable. Vérifiez la configuration PHP (file_uploads).', 'mj-member');
                    } else {
                        $check = wp_check_filetype_and_ext($file['tmp_name'], $file['name'], array('csv' => 'text/csv', 'txt' => 'text/plain'));
                        if (empty($check['ext'])) {
                            $hoursState['errors'][] = __('Format non pris en charge. Importez un fichier CSV (.csv).', 'mj-member');
                        } else {
                            $originalName = isset($file['name']) ? sanitize_file_name($file['name']) : basename($file['tmp_name']);
                            $recordedBy = function_exists('get_current_user_id') ? get_current_user_id() : 0;
                            $process = mj_member_import_hours_process_file($file['tmp_name'], $memberId, (int) $recordedBy, $originalName);
                            if (is_wp_error($process)) {
                                $hoursState['errors'][] = $process->get_error_message();
                            } else {
                                $process['member_label'] = $memberLabels[$memberId];
                                $hoursState['report'] = $process;
                                $hoursState['notices'][] = __('Import des heures terminé.', 'mj-member');
                            }
                        }
                    }
                }
            }
        }
    } else {
        $available_fields = mj_member_import_get_available_fields();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $step = isset($_POST['import_step']) ? sanitize_key(wp_unslash($_POST['import_step'])) : 'upload';

            if ($step === 'handle_upload') {
                check_admin_referer('mj_member_import_upload', 'mj_member_import_nonce');
                $upload_result = mj_member_import_handle_upload();
                if (is_wp_error($upload_result)) {
                    Logger::error('CSV import upload failed', array(
                        'user_id' => function_exists('get_current_user_id') ? get_current_user_id() : 0,
                        'error_code' => $upload_result->get_error_code(),
                    ), 'import');
                    $state['errors'][] = $upload_result->get_error_message();
                } else {
                    $state['headers'] = $upload_result['headers'];
                    $state['preview'] = $upload_result['preview'];
                    $state['token'] = $upload_result['token'];
                    $state['original_name'] = $upload_result['original_name'];
                    Logger::info('CSV import staged', array(
                        'user_id' => function_exists('get_current_user_id') ? get_current_user_id() : 0,
                        'token' => $state['token'],
                        'file_name' => $state['original_name'],
                        'columns' => count($state['headers']),
                        'preview_rows' => count($state['preview']),
                    ), 'import');
                    $view_stage = 'mapping';
                }
            } elseif ($step === 'process_import') {
                check_admin_referer('mj_member_import_process', 'mj_member_import_nonce');
                $token = isset($_POST['import_token']) ? sanitize_text_field(wp_unslash($_POST['import_token'])) : '';
                $duplicate_mode = isset($_POST['duplicate_mode']) ? sanitize_key(wp_unslash($_POST['duplicate_mode'])) : 'skip';
                $mapping_input = isset($_POST['mapping']) && is_array($_POST['mapping']) ? $_POST['mapping'] : array();
                $process = mj_member_import_process($token, $duplicate_mode, $mapping_input, $available_fields);

                if (is_wp_error($process)) {
                    Logger::error('CSV import failed', array(
                        'user_id' => function_exists('get_current_user_id') ? get_current_user_id() : 0,
                        'token' => $token,
                        'duplicate_mode' => $duplicate_mode,
                        'error_code' => $process->get_error_code(),
                        'has_error_data' => $process->get_error_data() ? 1 : 0,
                    ), 'import');
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
                    $report = $process['report'];
                    Logger::info('CSV import completed', array(
                        'user_id' => function_exists('get_current_user_id') ? get_current_user_id() : 0,
                        'token' => $token,
                        'duplicate_mode' => $duplicate_mode,
                        'file_name' => $process['original_name'],
                        'summary' => array(
                            'total' => isset($report['total']) ? (int) $report['total'] : 0,
                            'created' => isset($report['created']) ? (int) $report['created'] : 0,
                            'updated' => isset($report['updated']) ? (int) $report['updated'] : 0,
                            'skipped' => isset($report['duplicate_skipped']) ? (int) $report['duplicate_skipped'] : 0,
                            'errors' => isset($report['errors']) ? count((array) $report['errors']) : 0,
                            'warnings' => isset($report['warnings']) ? count((array) $report['warnings']) : 0,
                        ),
                    ), 'import');
                    $view_stage = 'report';
                }
            }
        }
    }

    $baseUrl = remove_query_arg('module', add_query_arg(array()));
    $membersUrl = $baseUrl;
    $hoursUrl = add_query_arg(array('module' => 'hours'), $baseUrl);

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__('Imports CSV', 'mj-member') . '</h1>';

    echo '<h2 class="nav-tab-wrapper">';
    echo '<a href="' . esc_url($membersUrl) . '" class="nav-tab' . ($activeModule === 'members' ? ' nav-tab-active' : '') . '">' . esc_html__('Membres', 'mj-member') . '</a>';
    if ($canAccessHours) {
        echo '<a href="' . esc_url($hoursUrl) . '" class="nav-tab' . ($activeModule === 'hours' ? ' nav-tab-active' : '') . '">' . esc_html__('Encodage des heures', 'mj-member') . '</a>';
    }
    echo '</h2>';

    if ($activeModule === 'hours') {
        foreach ($hoursState['errors'] as $error_message) {
            echo '<div class="notice notice-error"><p>' . esc_html($error_message) . '</p></div>';
        }
        foreach ($hoursState['notices'] as $notice_message) {
            echo '<div class="notice notice-success"><p>' . esc_html($notice_message) . '</p></div>';
        }
        foreach ($hoursState['warnings'] as $warning_message) {
            echo '<div class="notice notice-warning"><p>' . esc_html($warning_message) . '</p></div>';
        }

        if ($hoursState['report']) {
            mj_member_import_render_hours_report($hoursState['report']);
        }

        if ($canAccessHours) {
            mj_member_import_render_hours_form($hoursState, $hoursMemberOptions);
        }
    } else {
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
            mj_member_import_render_mapping_form($state, $available_fields, 'members');
        } elseif ($view_stage === 'report') {
            mj_member_import_render_report($state['report'], $state['original_name']);
        } else {
            mj_member_import_render_upload_form('members');
        }
    }

    echo '</div>';
}

function mj_member_import_render_upload_form($target = 'members') {
    ?>
    <form method="post" enctype="multipart/form-data">
        <?php wp_nonce_field('mj_member_import_upload', 'mj_member_import_nonce'); ?>
        <input type="hidden" name="import_step" value="handle_upload" />
        <input type="hidden" name="import_target" value="<?php echo esc_attr($target); ?>" />
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

function mj_member_import_render_mapping_form($state, $available_fields, $target = 'members') {
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
        <input type="hidden" name="import_target" value="<?php echo esc_attr($target); ?>" />

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

function mj_member_import_render_hours_form($state, $memberOptions) {
    $selectedMemberId = isset($state['selected_member_id']) ? (int) $state['selected_member_id'] : 0;
    ?>
    <form method="post" enctype="multipart/form-data" class="mj-member-import-hours-form">
        <?php wp_nonce_field('mj_member_import_hours', 'mj_member_import_hours_nonce'); ?>
        <input type="hidden" name="import_step" value="hours_upload" />
        <input type="hidden" name="import_target" value="hours" />
        <p><?php esc_html_e('Sélectionnez un membre et importez un fichier CSV contenant les heures à encoder.', 'mj-member'); ?></p>
        <p>
            <label for="mj-member-import-hours-member"><strong><?php esc_html_e('Membre cible', 'mj-member'); ?></strong></label><br />
            <select id="mj-member-import-hours-member" name="member_id">
                <option value="0"><?php esc_html_e('— Sélectionnez un membre —', 'mj-member'); ?></option>
                <?php foreach ($memberOptions as $option) :
                    $optionId = isset($option['id']) ? (int) $option['id'] : 0;
                    $optionLabel = isset($option['label']) ? (string) $option['label'] : '';
                    ?>
                    <option value="<?php echo esc_attr((string) $optionId); ?>" <?php selected($selectedMemberId, $optionId); ?>><?php echo esc_html($optionLabel); ?></option>
                <?php endforeach; ?>
            </select>
        </p>
        <p>
            <label for="mj-member-import-hours-file" class="screen-reader-text"><?php esc_html_e('Fichier CSV', 'mj-member'); ?></label>
            <input type="file" id="mj-member-import-hours-file" name="csv_file" accept=".csv,text/csv" required />
        </p>
        <p class="description">
            <?php esc_html_e('Le fichier doit contenir les colonnes : date, heure_debut, heure_fin, intitule, projet.', 'mj-member'); ?>
        </p>
        <p class="submit">
            <button type="submit" class="button button-primary"><?php esc_html_e('Importer les heures', 'mj-member'); ?></button>
        </p>
    </form>
    <?php
}

function mj_member_import_render_hours_report($report) {
    if (!is_array($report)) {
        return;
    }

    $fileName = isset($report['file_name']) ? (string) $report['file_name'] : '';
    $memberLabel = isset($report['member_label']) ? (string) $report['member_label'] : '';
    ?>
    <h2><?php esc_html_e('Résultat de l\'import des heures', 'mj-member'); ?></h2>
    <?php if ($memberLabel !== '') : ?>
        <p><strong><?php esc_html_e('Membre :', 'mj-member'); ?></strong> <?php echo esc_html($memberLabel); ?></p>
    <?php endif; ?>
    <?php if ($fileName !== '') : ?>
        <p><strong><?php esc_html_e('Fichier traité :', 'mj-member'); ?></strong> <?php echo esc_html($fileName); ?></p>
    <?php endif; ?>
    <ul>
        <li><?php printf(esc_html__('Lignes analysées : %d', 'mj-member'), isset($report['total']) ? (int) $report['total'] : 0); ?></li>
        <li><?php printf(esc_html__('Encodages créés : %d', 'mj-member'), isset($report['created']) ? (int) $report['created'] : 0); ?></li>
        <li><?php printf(esc_html__('Lignes ignorées : %d', 'mj-member'), isset($report['skipped']) ? (int) $report['skipped'] : 0); ?></li>
        <li><?php printf(esc_html__('Erreurs : %d', 'mj-member'), isset($report['errors']) ? count((array) $report['errors']) : 0); ?></li>
    </ul>

    <?php if (!empty($report['errors'])) : ?>
        <h3><?php esc_html_e('Erreurs rencontrées', 'mj-member'); ?></h3>
        <ol>
            <?php foreach ((array) $report['errors'] as $error_line) : ?>
                <li><?php echo esc_html($error_line); ?></li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>

    <?php if (!empty($report['warnings'])) : ?>
        <h3><?php esc_html_e('Avertissements', 'mj-member'); ?></h3>
        <ul>
            <?php foreach ((array) $report['warnings'] as $warning_line) : ?>
                <li><?php echo esc_html($warning_line); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <?php
}

function mj_member_import_hours_process_file($filePath, $memberId, $recordedBy, $originalName) {
    $filePath = (string) $filePath;
    if ($filePath === '' || !file_exists($filePath)) {
        return new WP_Error('mj-import-hours-missing-file', __('Impossible d\'ouvrir le fichier importé.', 'mj-member'));
    }

    $delimiter = mj_member_import_detect_delimiter($filePath);
    $handle = fopen($filePath, 'r');
    if (!$handle) {
        return new WP_Error('mj-import-hours-open-error', __('Impossible d\'ouvrir le fichier importé.', 'mj-member'));
    }

    $headerRow = fgetcsv($handle, 0, $delimiter);
    if ($headerRow === false) {
        fclose($handle);
        return new WP_Error('mj-import-hours-empty-file', __('Le fichier ne contient aucune donnée.', 'mj-member'));
    }

    $mapping = mj_member_import_hours_map_headers((array) $headerRow);
    if (is_wp_error($mapping)) {
        fclose($handle);
        return $mapping;
    }

    $report = array(
        'file_name' => $originalName,
        'total' => 0,
        'created' => 0,
        'skipped' => 0,
        'errors' => array(),
        'warnings' => array(),
    );

    $lineNumber = 1;
    while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
        $lineNumber++;
        if (mj_member_import_row_is_empty($row)) {
            $report['skipped']++;
            continue;
        }

        $report['total']++;

        $dateRaw = mj_member_import_hours_get_value($row, $mapping['date']);
        $startRaw = mj_member_import_hours_get_value($row, $mapping['heure_debut']);
        $endRaw = mj_member_import_hours_get_value($row, $mapping['heure_fin']);
        $taskRaw = mj_member_import_hours_get_value($row, $mapping['intitule']);
        $projectRaw = isset($mapping['projet']) ? mj_member_import_hours_get_value($row, $mapping['projet']) : '';

        $activityDate = mj_member_import_parse_date($dateRaw);
        if ($activityDate === null) {
            $report['errors'][] = sprintf(__('Ligne %1$d : date invalide « %2$s ».', 'mj-member'), $lineNumber, $dateRaw);
            continue;
        }

        $startTime = mj_member_import_hours_parse_time($startRaw);
        $endTime = mj_member_import_hours_parse_time($endRaw);
        if ($startTime === '' || $endTime === '') {
            $report['errors'][] = sprintf(__('Ligne %d : horaire incomplet.', 'mj-member'), $lineNumber);
            continue;
        }

        $durationMinutes = mj_member_import_hours_calculate_duration_minutes($startTime, $endTime);
        if ($durationMinutes <= 0) {
            $report['errors'][] = sprintf(__('Ligne %d : la durée calculée est invalide.', 'mj-member'), $lineNumber);
            continue;
        }

        $taskLabel = sanitize_text_field($taskRaw);
        if ($taskLabel === '') {
            $report['errors'][] = sprintf(__('Ligne %d : intitulé manquant.', 'mj-member'), $lineNumber);
            continue;
        }

        $projectLabel = sanitize_text_field($projectRaw);

        if ($projectLabel !== '') {
            $taskLabel = trim($taskLabel . ' - ' . $projectLabel);
        }

        $payload = array(
            'member_id' => $memberId,
            'task_label' => $taskLabel,
            'task_key' => sanitize_title($taskLabel) ?: null,
            'activity_date' => $activityDate,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'duration_minutes' => $durationMinutes,
            'notes' => $projectLabel !== '' ? $projectLabel : null,
            'recorded_by' => $recordedBy,
        );

        $result = MjMemberHours::create($payload);
        if (is_wp_error($result)) {
            $report['errors'][] = sprintf(__('Ligne %1$d : %2$s', 'mj-member'), $lineNumber, $result->get_error_message());
            continue;
        }

        $report['created']++;
    }

    fclose($handle);

    return $report;
}

function mj_member_import_hours_map_headers(array $headers) {
    $mapping = array(
        'date' => null,
        'heure_debut' => null,
        'heure_fin' => null,
        'intitule' => null,
        'projet' => null,
    );

    $cleanHeaders = array();
    $normalizedHeaders = array();

    foreach ($headers as $index => $header) {
        $clean = mj_member_import_clean_header($header);
        $normalized = mj_member_import_hours_normalize_header($clean);

        $cleanHeaders[] = $clean !== '' ? $clean : __('(vide)', 'mj-member');
        $normalizedHeaders[] = $normalized;

        if (array_key_exists($normalized, $mapping) && !is_int($mapping[$normalized])) {
            $mapping[$normalized] = (int) $index;
        }
    }

    $missing = array();
    foreach (array('date', 'heure_debut', 'heure_fin', 'intitule') as $required) {
        if (!is_int($mapping[$required])) {
            $missing[] = $required;
        }
    }

    if (!empty($missing)) {
        $details = new WP_Error('mj-import-hours-missing-columns', sprintf(
            __('Colonnes manquantes dans le CSV : %1$s. En-têtes détectées : %2$s.', 'mj-member'),
            implode(', ', $missing),
            implode(', ', $cleanHeaders)
        ));

        $details->add_data(array(
            'missing' => $missing,
            'headers' => $cleanHeaders,
            'normalized' => $normalizedHeaders,
        ));

        return $details;
    }

    return $mapping;
}

function mj_member_import_hours_normalize_header($header) {
    $header = mj_member_import_clean_header($header);
    $header = is_string($header) ? strtolower($header) : '';

    if (function_exists('remove_accents')) {
        $header = remove_accents($header);
    }

    $header = preg_replace('/[^a-z0-9]+/', '_', (string) $header);
    $header = trim((string) $header, '_');

    if ($header === '') {
        return '';
    }

    $aliases = array(
        'date' => 'date',
        'jour' => 'date',
        'journee' => 'date',
        'journe' => 'date',
        'date_activite' => 'date',
        'date_d_activite' => 'date',
        'date_activité' => 'date',
        'date_du_jour' => 'date',
        'day' => 'date',
        'date_jour' => 'date',

        'heure_debut' => 'heure_debut',
        'heure_debut_' => 'heure_debut',
        'debut' => 'heure_debut',
        'debut_heure' => 'heure_debut',
        'start' => 'heure_debut',
        'start_time' => 'heure_debut',
        'heure_debut_evenement' => 'heure_debut',

        'heure_fin' => 'heure_fin',
        'heure_fin_' => 'heure_fin',
        'fin' => 'heure_fin',
        'fin_heure' => 'heure_fin',
        'end' => 'heure_fin',
        'end_time' => 'heure_fin',
        'heure_fin_evenement' => 'heure_fin',

        'intitule' => 'intitule',
        'intitule_' => 'intitule',
        'intitules' => 'intitule',
        'intitule_activite' => 'intitule',
        'intitule_evenement' => 'intitule',
        'intitule_tache' => 'intitule',
        'libelle' => 'intitule',
        'libelle_tache' => 'intitule',
        'titre' => 'intitule',
        'tache' => 'intitule',
        'task' => 'intitule',
        'task_label' => 'intitule',

        'projet' => 'projet',
        'project' => 'projet',
        'projets' => 'projet',
        'notes' => 'projet',
        'commentaire' => 'projet',
        'commentaires' => 'projet',
    );

    if (isset($aliases[$header])) {
        return $aliases[$header];
    }

    return $header;
}

function mj_member_import_hours_get_value($row, $index) {
    if (!is_int($index)) {
        return '';
    }

    return isset($row[$index]) ? trim((string) $row[$index]) : '';
}

function mj_member_import_hours_parse_time($value) {
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    $normalized = str_replace(array('h', 'H', ',', '.', ' '), ':', $value);
    if (strpos($normalized, ':') === false && strlen($normalized) === 4) {
        $normalized = substr($normalized, 0, 2) . ':' . substr($normalized, 2);
    }
    if (strpos($normalized, ':') === false && strlen($normalized) === 2) {
        $normalized .= ':00';
    }
    if (strpos($normalized, ':') === false && strlen($normalized) === 1) {
        $normalized .= ':00';
    }
    if (substr($normalized, -1) === ':') {
        $normalized .= '00';
    }

    if (!preg_match('/^(\d{1,2}):(\d{1,2})(?::(\d{1,2}))?$/', $normalized, $matches)) {
        return '';
    }

    $hour = (int) $matches[1];
    $minute = (int) $matches[2];
    $second = isset($matches[3]) ? (int) $matches[3] : 0;

    if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59 || $second < 0 || $second > 59) {
        return '';
    }

    return sprintf('%02d:%02d:%02d', $hour, $minute, $second);
}

function mj_member_import_hours_calculate_duration_minutes($startTime, $endTime) {
    $startTimestamp = strtotime('1970-01-01 ' . $startTime);
    $endTimestamp = strtotime('1970-01-01 ' . $endTime);

    if ($startTimestamp === false || $endTimestamp === false) {
        return 0;
    }

    $delta = (int) round(($endTimestamp - $startTimestamp) / 60);
    return $delta > 0 ? $delta : 0;
}

function mj_member_import_hours_member_options() {
    if (!class_exists(MjMembers::class)) {
        return array();
    }

    $members = MjMembers::getAll(0, 0, 'last_name', 'ASC');
    if (empty($members)) {
        return array();
    }

    $options = array();
    foreach ($members as $member) {
        $id = isset($member->id) ? (int) $member->id : 0;
        if ($id <= 0) {
            continue;
        }
        $first = isset($member->first_name) ? trim((string) $member->first_name) : '';
        $last = isset($member->last_name) ? trim((string) $member->last_name) : '';
        $label = trim($first . ' ' . $last);
        if ($label === '') {
            $label = sprintf(__('Membre #%d', 'mj-member'), $id);
        }
        $options[] = array(
            'id' => $id,
            'label' => $label,
        );
    }

    return $options;
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
                $volunteer_from_role = false;
                $payload['role'] = mj_member_import_normalize_role($role_raw, $row_warnings, $volunteer_from_role);
                if ($volunteer_from_role) {
                    $payload['is_volunteer'] = 1;
                }
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

        if (isset($mapping['is_volunteer'])) {
            $volunteer_raw = mj_member_import_get_mapped_value($row, $mapping['is_volunteer']);
            if ($volunteer_raw !== '') {
                $volunteer_flag = mj_member_import_parse_bool($volunteer_raw);
                if ($volunteer_flag !== null) {
                    $payload['is_volunteer'] = $volunteer_flag ? 1 : 0;
                } else {
                    $row_warnings[] = __('Valeur bénévole invalide ignorée.', 'mj-member');
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
                    $guardian = MjMembers::getByEmail($guardian_email);
                    if ($guardian && $guardian->role === MjMembers::ROLE_TUTEUR) {
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
            $payload['role'] = MjMembers::ROLE_JEUNE;
        }

        if ($payload['role'] === MjMembers::ROLE_JEUNE) {
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

        if (!isset($payload['is_volunteer'])) {
            $payload['is_volunteer'] = 0;
        }

        $existing = null;
        if (isset($payload['email']) && $payload['email'] !== '') {
            $existing = MjMembers::getByEmail($payload['email']);
        }

        if ($existing && $duplicate_mode === 'skip') {
            $report['duplicate_skipped']++;
            continue;
        }

        if ($existing && $duplicate_mode === 'update') {
            $update_payload = $payload;
            unset($update_payload['email']);
            $update_result = MjMembers::update((int) $existing->id, $update_payload);
            if (is_wp_error($update_result)) {
                $report['errors'][] = sprintf(__('Ligne %d : %s', 'mj-member'), $line_number, $update_result->get_error_message());
                continue;
            }
            $report['updated']++;
        } else {
            $create_result = MjMembers::create($payload);
            if (is_wp_error($create_result) || !$create_result) {
                $message = is_wp_error($create_result) ? $create_result->get_error_message() : __('Impossible de créer le membre.', 'mj-member');
                $report['errors'][] = sprintf(__('Ligne %d : %s', 'mj-member'), $line_number, $message);
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
        'role' => __('Rôle (jeune, tuteur, animateur, coordinateur)', 'mj-member'),
        'status' => __('Statut (active/inactive)', 'mj-member'),
        'requires_payment' => __('Cotisation requise (oui/non)', 'mj-member'),
        'is_volunteer' => __('Bénévole (oui/non)', 'mj-member'),
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

    $patterns = array(
        'Y-m-d',
        'd/m/Y',
        'd-m-Y',
        'd.m.Y',
        'm/d/Y',
        'd/m/y',
        'd-m-y',
    );

    foreach ($patterns as $pattern) {
        $dateTime = DateTime::createFromFormat($pattern, $value);
        if ($dateTime instanceof DateTime) {
            return gmdate('Y-m-d', $dateTime->getTimestamp());
        }
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

function mj_member_import_normalize_role($value, &$row_warnings, &$volunteer_flag = false) {
    $volunteer_flag = false;
    $normalized = strtolower(remove_accents(trim((string) $value)));
    $mapping = array(
        'jeune' => MjMembers::ROLE_JEUNE,
        'jeunes' => MjMembers::ROLE_JEUNE,
        'tuteur' => MjMembers::ROLE_TUTEUR,
        'tutrice' => MjMembers::ROLE_TUTEUR,
        'parent' => MjMembers::ROLE_TUTEUR,
        'parents' => MjMembers::ROLE_TUTEUR,
        'animateur' => MjMembers::ROLE_ANIMATEUR,
        'animatrice' => MjMembers::ROLE_ANIMATEUR,
        'coordinateur' => MjMembers::ROLE_COORDINATEUR,
        'coordinatrice' => MjMembers::ROLE_COORDINATEUR,
    );

    if (isset($mapping[$normalized])) {
        return $mapping[$normalized];
    }

    if (in_array($normalized, array('benevole', 'benevoles'), true)) {
        $volunteer_flag = true;
        $row_warnings[] = __('Rôle "Bénévole" converti en option bénévole.', 'mj-member');
        return MjMembers::ROLE_JEUNE;
    }

    $row_warnings[] = __('Role inconnu, remplace par "jeune".', 'mj-member');
    return MjMembers::ROLE_JEUNE;
}

function mj_member_import_normalize_status($value, &$row_warnings) {
    $normalized = strtolower(remove_accents(trim((string) $value)));
    $mapping = array(
        'active' => MjMembers::STATUS_ACTIVE,
        'actif' => MjMembers::STATUS_ACTIVE,
        'inactive' => MjMembers::STATUS_INACTIVE,
        'inactif' => MjMembers::STATUS_INACTIVE,
    );
    if (isset($mapping[$normalized])) {
        return $mapping[$normalized];
    }
    $row_warnings[] = __('Statut inconnu, remplace par "active".', 'mj-member');
    return MjMembers::STATUS_ACTIVE;
}
