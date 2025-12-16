<?php

use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('mj_event_closures_page')) {
    function mj_event_closures_page() {
        $capability = Config::capability();

        if (!current_user_can($capability)) {
            wp_die(__('Accès refusé.', 'mj-member'));
        }

        $notices = array();
        $errors = array();
        $form_state = array(
            'start' => '',
            'end' => '',
            'description' => '',
            'cover_id' => 0,
        );
        $editing_id = 0;

        wp_enqueue_media();
        $script_path = Config::path() . 'includes/js/admin-closures.js';
        $script_version = file_exists($script_path) ? (string) filemtime($script_path) : Config::version();
        wp_enqueue_script(
            'mj-admin-closures',
            Config::url() . 'includes/js/admin-closures.js',
            array('jquery'),
            $script_version,
            true
        );

        if (isset($_GET['action'], $_GET['closure'], $_GET['_wpnonce']) && $_GET['action'] === 'delete') {
            $closure_id = (int) $_GET['closure'];
            $nonce_value = sanitize_text_field(wp_unslash($_GET['_wpnonce']));
            if (!wp_verify_nonce($nonce_value, 'mj_closure_delete_' . $closure_id)) {
                $errors[] = __('La vérification de sécurité a échoué.', 'mj-member');
            } else {
                $deleted = MjEventClosures::delete($closure_id);
                if (is_wp_error($deleted)) {
                    $errors[] = $deleted->get_error_message();
                } else {
                    $notices[] = __('Fermeture supprimée.', 'mj-member');
                }
            }
        }

        if (
            $_SERVER['REQUEST_METHOD'] !== 'POST'
            && isset($_GET['action'], $_GET['closure'], $_GET['_wpnonce'])
            && $_GET['action'] === 'edit'
        ) {
            $closure_id = (int) $_GET['closure'];
            $nonce_value = sanitize_text_field(wp_unslash($_GET['_wpnonce']));
            if (!wp_verify_nonce($nonce_value, 'mj_closure_edit_' . $closure_id)) {
                $errors[] = __('La vérification de sécurité a échoué.', 'mj-member');
            } else {
                $existing = MjEventClosures::get_by_id($closure_id);
                if (!$existing) {
                    $errors[] = __('La fermeture demandée est introuvable.', 'mj-member');
                } else {
                    $editing_id = (int) $closure_id;
                    $form_state = array(
                        'start' => isset($existing->start_date) ? (string) $existing->start_date : '',
                        'end' => isset($existing->end_date) ? (string) $existing->end_date : '',
                        'description' => isset($existing->description) ? sanitize_text_field((string) $existing->description) : '',
                        'cover_id' => isset($existing->cover_id) ? (int) $existing->cover_id : 0,
                    );
                }
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mj_closure_nonce'])) {
            $nonce = sanitize_text_field(wp_unslash($_POST['mj_closure_nonce']));
            if (!wp_verify_nonce($nonce, 'mj_closure_save')) {
                $errors[] = __('La vérification de sécurité a échoué.', 'mj-member');
            } else {
                $editing_id = isset($_POST['closure_id']) ? (int) $_POST['closure_id'] : 0;
                if ($editing_id < 0) {
                    $editing_id = 0;
                }

                $start_value = isset($_POST['closure_start']) ? sanitize_text_field(wp_unslash($_POST['closure_start'])) : '';
                $end_value = isset($_POST['closure_end']) ? sanitize_text_field(wp_unslash($_POST['closure_end'])) : '';
                $description = isset($_POST['closure_description']) ? sanitize_text_field(wp_unslash($_POST['closure_description'])) : '';
                $cover_id = isset($_POST['closure_cover_id']) ? absint($_POST['closure_cover_id']) : 0;

                $form_state['start'] = $start_value;
                $form_state['end'] = $end_value;
                $form_state['description'] = $description;
                $form_state['cover_id'] = $cover_id;

                if ($start_value === '' || $end_value === '') {
                    $errors[] = __('Merci de saisir une période complète (début et fin).', 'mj-member');
                } else {
                    $start_time = strtotime($start_value);
                    $end_time = strtotime($end_value);
                    if ($start_time === false || $end_time === false) {
                        $errors[] = __('Les dates fournies sont invalides.', 'mj-member');
                    } elseif ($end_time < $start_time) {
                        $errors[] = __('La date de fin doit être postérieure ou égale à la date de début.', 'mj-member');
                    }
                }

                if (empty($errors)) {
                    $payload = array(
                        'start_date' => $start_value,
                        'end_date' => $end_value,
                        'description' => $description,
                        'cover_id' => $cover_id,
                    );

                    if ($editing_id > 0) {
                        $result = MjEventClosures::update($editing_id, $payload);
                        if (is_wp_error($result)) {
                            $errors[] = $result->get_error_message();
                        } else {
                            $notices[] = __('Fermeture mise à jour.', 'mj-member');
                            $form_state = array(
                                'start' => '',
                                'end' => '',
                                'description' => '',
                                'cover_id' => 0,
                            );
                            $editing_id = 0;
                        }
                    } else {
                        $result = MjEventClosures::create($payload);
                        if (is_wp_error($result)) {
                            $errors[] = $result->get_error_message();
                        } else {
                            $notices[] = __('Fermeture enregistrée.', 'mj-member');
                            $form_state = array(
                                'start' => '',
                                'end' => '',
                                'description' => '',
                                'cover_id' => 0,
                            );
                        }
                    }
                }
            }
        }

        $is_editing = $editing_id > 0;
        $form_title = $is_editing ? __('Modifier une fermeture', 'mj-member') : __('Ajouter une fermeture', 'mj-member');
        $submit_label = $is_editing ? __('Mettre à jour la fermeture', 'mj-member') : __('Enregistrer la fermeture', 'mj-member');
        $cancel_edit_url = admin_url('admin.php?page=mj_closures');

        $closures = MjEventClosures::get_all();
        $today = wp_date('Y-m-d');
        $upcoming = array();
        $past = array();

        foreach ($closures as $closure) {
            if (!isset($closure->start_date)) {
                continue;
            }
            $end_marker = isset($closure->end_date) && $closure->end_date !== '' ? $closure->end_date : $closure->start_date;
            if ($end_marker >= $today) {
                $upcoming[] = $closure;
            } else {
                $past[] = $closure;
            }
        }

        if (!empty($upcoming)) {
            usort($upcoming, static function ($a, $b) {
                $left = isset($a->start_date) ? $a->start_date : '';
                $right = isset($b->start_date) ? $b->start_date : '';
                return strcmp($left, $right);
            });
        }

        if (!empty($past)) {
            usort($past, static function ($a, $b) {
                $left = isset($a->start_date) ? $a->start_date : '';
                $right = isset($b->start_date) ? $b->start_date : '';
                return strcmp($right, $left);
            });
        }
        $format_range = static function ($item) {
            if (!is_object($item)) {
                return '';
            }

            $start_value = isset($item->start_date) ? $item->start_date : (isset($item->closure_date) ? $item->closure_date : '');
            if ($start_value === '') {
                return '';
            }

            $end_value = isset($item->end_date) && $item->end_date !== '' ? $item->end_date : $start_value;

            $start_ts = strtotime($start_value);
            $end_ts = strtotime($end_value);

            if ($start_ts === false) {
                return $start_value;
            }

            if ($end_ts === false) {
                $end_ts = $start_ts;
            }

            if ($start_value === $end_value) {
                return wp_date('d/m/Y', $start_ts);
            }

            return sprintf(
                __('Du %s au %s', 'mj-member'),
                wp_date('d/m/Y', $start_ts),
                wp_date('d/m/Y', $end_ts)
            );
        };

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Jours de fermeture de la MJ', 'mj-member'); ?></h1>

            <?php foreach ($notices as $notice) {
                echo '<div class="notice notice-success"><p>' . esc_html($notice) . '</p></div>';
            }

            foreach ($errors as $error) {
                echo '<div class="notice notice-error"><p>' . esc_html($error) . '</p></div>';
            }
            ?>

            <div class="card" style="max-width:600px;margin-bottom:24px;">
                <h2><?php echo esc_html($form_title); ?></h2>
                <?php if ($is_editing) { ?>
                    <p class="description" style="margin-top:6px;">
                        <?php esc_html_e('Vous modifiez une fermeture existante. Ajustez les informations ci-dessous puis enregistrez les changements.', 'mj-member'); ?>
                    </p>
                <?php } ?>
                <form method="post">
                    <?php wp_nonce_field('mj_closure_save', 'mj_closure_nonce'); ?>
                    <input type="hidden" name="closure_id" value="<?php echo esc_attr((int) $editing_id); ?>" />
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="mj-closure-start"><?php esc_html_e('Date de début', 'mj-member'); ?></label></th>
                            <td>
                                <input type="date" id="mj-closure-start" name="closure_start" value="<?php echo esc_attr($form_state['start']); ?>" required />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="mj-closure-end"><?php esc_html_e('Date de fin', 'mj-member'); ?></label></th>
                            <td>
                                <input type="date" id="mj-closure-end" name="closure_end" value="<?php echo esc_attr($form_state['end']); ?>" required />
                                <p class="description" style="margin-top:6px;">
                                    <?php esc_html_e('Les deux dates sont incluses. Pour une fermeture d’une journée, choisissez la même date.', 'mj-member'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Photo (optionnelle)', 'mj-member'); ?></th>
                            <td>
                                <input type="hidden" id="mj-closure-cover-id" name="closure_cover_id" value="<?php echo esc_attr((int) $form_state['cover_id']); ?>" />
                                <div id="mj-closure-cover-preview" data-empty-label="<?php echo esc_attr__('Aucune image sélectionnée.', 'mj-member'); ?>" style="margin-bottom:10px;">
                                    <?php
                                    $cover_preview_html = '<span>' . esc_html__('Aucune image sélectionnée.', 'mj-member') . '</span>';
                                    if (!empty($form_state['cover_id'])) {
                                        $preview_image = wp_get_attachment_image_src((int) $form_state['cover_id'], 'medium');
                                        if (!empty($preview_image[0])) {
                                            $cover_preview_html = '<img src="' . esc_url($preview_image[0]) . '" alt="" style="max-width:200px;height:auto;border-radius:8px;" />';
                                        }
                                    }
                                    echo $cover_preview_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                    ?>
                                </div>
                                <button type="button" class="button" id="mj-closure-cover-select"><?php esc_html_e('Choisir une image', 'mj-member'); ?></button>
                                <button type="button" class="button" id="mj-closure-cover-remove"><?php esc_html_e('Retirer', 'mj-member'); ?></button>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="mj-closure-description"><?php esc_html_e('Description (optionnelle)', 'mj-member'); ?></label></th>
                            <td>
                                <input type="text" id="mj-closure-description" name="closure_description" class="regular-text" maxlength="190" value="<?php echo esc_attr($form_state['description']); ?>" />
                            </td>
                        </tr>
                    </table>
                    <p class="submit">
                        <button type="submit" class="button button-primary"><?php echo esc_html($submit_label); ?></button>
                        <?php if ($is_editing) { ?>
                            <a class="button button-secondary" style="margin-left:8px;" href="<?php echo esc_url($cancel_edit_url); ?>"><?php esc_html_e('Annuler', 'mj-member'); ?></a>
                        <?php } ?>
                    </p>
                </form>
            </div>

            <h2><?php esc_html_e('Fermetures à venir', 'mj-member'); ?></h2>
            <?php
            if (empty($upcoming)) {
                echo '<p>' . esc_html__('Aucune fermeture planifiée.', 'mj-member') . '</p>';
            } else {
                echo '<table class="widefat striped">';
                echo '<thead><tr>';
                echo '<th>' . esc_html__('Période', 'mj-member') . '</th>';
                echo '<th>' . esc_html__('Photo', 'mj-member') . '</th>';
                echo '<th>' . esc_html__('Description', 'mj-member') . '</th>';
                echo '<th>' . esc_html__('Actions', 'mj-member') . '</th>';
                echo '</tr></thead><tbody>';
                foreach ($upcoming as $item) {
                    $delete_url = wp_nonce_url(
                        add_query_arg(
                            array(
                                'page' => 'mj_closures',
                                'action' => 'delete',
                                'closure' => (int) $item->id,
                            ),
                            admin_url('admin.php')
                        ),
                        'mj_closure_delete_' . (int) $item->id
                    );
                    $edit_url = wp_nonce_url(
                        add_query_arg(
                            array(
                                'page' => 'mj_closures',
                                'action' => 'edit',
                                'closure' => (int) $item->id,
                            ),
                            admin_url('admin.php')
                        ),
                        'mj_closure_edit_' . (int) $item->id
                    );

                    $cover_cell = '<span style="color:#6b7280;">' . esc_html__('Aucune', 'mj-member') . '</span>';
                    if (!empty($item->cover_id)) {
                        $attachment_id = (int) $item->cover_id;
                        $thumb_image = wp_get_attachment_image_src($attachment_id, 'thumbnail');
                        if (!empty($thumb_image[0])) {
                            $cover_cell = '<img src="' . esc_url($thumb_image[0]) . '" alt="" style="width:60px;height:60px;object-fit:cover;border-radius:6px;" />';
                        }
                    }

                    echo '<tr>';
                    echo '<td>' . esc_html($format_range($item)) . '</td>';
                    echo '<td>' . $cover_cell . '</td>';
                    echo '<td>' . esc_html($item->description) . '</td>';
                    echo '<td>';
                    echo '<a class="button button-small" href="' . esc_url($edit_url) . '">' . esc_html__('Modifier', 'mj-member') . '</a> ';
                    echo '<a class="button button-small" href="' . esc_url($delete_url) . '" onclick="return confirm(\'' . esc_js(__('Supprimer cette fermeture ?', 'mj-member')) . '\');">' . esc_html__('Supprimer', 'mj-member') . '</a>';
                    echo '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
            }
            ?>

            <h2 style="margin-top:32px;"><?php esc_html_e('Historique des fermetures', 'mj-member'); ?></h2>
            <?php
            if (empty($past)) {
                echo '<p>' . esc_html__('Aucune fermeture passée enregistrée.', 'mj-member') . '</p>';
            } else {
                echo '<table class="widefat striped">';
                echo '<thead><tr>';
                echo '<th>' . esc_html__('Période', 'mj-member') . '</th>';
                echo '<th>' . esc_html__('Photo', 'mj-member') . '</th>';
                echo '<th>' . esc_html__('Description', 'mj-member') . '</th>';
                echo '<th>' . esc_html__('Actions', 'mj-member') . '</th>';
                echo '</tr></thead><tbody>';
                foreach ($past as $item) {
                    $cover_cell = '<span style="color:#6b7280;">' . esc_html__('Aucune', 'mj-member') . '</span>';
                    if (!empty($item->cover_id)) {
                        $attachment_id = (int) $item->cover_id;
                        $thumb_image = wp_get_attachment_image_src($attachment_id, 'thumbnail');
                        if (!empty($thumb_image[0])) {
                            $cover_cell = '<img src="' . esc_url($thumb_image[0]) . '" alt="" style="width:60px;height:60px;object-fit:cover;border-radius:6px;" />';
                        }
                    }

                    $delete_url = wp_nonce_url(
                        add_query_arg(
                            array(
                                'page' => 'mj_closures',
                                'action' => 'delete',
                                'closure' => (int) $item->id,
                            ),
                            admin_url('admin.php')
                        ),
                        'mj_closure_delete_' . (int) $item->id
                    );

                    echo '<tr>';
                    echo '<td>' . esc_html($format_range($item)) . '</td>';
                    echo '<td>' . $cover_cell . '</td>';
                    echo '<td>' . esc_html($item->description) . '</td>';
                    echo '<td>';
                    echo '<a class="button button-small" href="' . esc_url($delete_url) . '" onclick="return confirm(\'' . esc_js(__('Supprimer cette fermeture ?', 'mj-member')) . '\');">' . esc_html__('Supprimer', 'mj-member') . '</a>';
                    echo '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
            }
            ?>
        </div>
        <?php
    }
}
