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

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mj_closure_nonce'])) {
            $nonce = sanitize_text_field(wp_unslash($_POST['mj_closure_nonce']));
            if (!wp_verify_nonce($nonce, 'mj_closure_save')) {
                $errors[] = __('La vérification de sécurité a échoué.', 'mj-member');
            } else {
                $date_value = isset($_POST['closure_date']) ? sanitize_text_field(wp_unslash($_POST['closure_date'])) : '';
                $description = isset($_POST['closure_description']) ? sanitize_text_field(wp_unslash($_POST['closure_description'])) : '';

                if ($date_value === '') {
                    $errors[] = __('Merci de sélectionner une date.', 'mj-member');
                } else {
                    $result = MjEventClosures::create($date_value, $description);
                    if (is_wp_error($result)) {
                        $errors[] = $result->get_error_message();
                    } else {
                        $notices[] = __('Fermeture enregistrée.', 'mj-member');
                    }
                }
            }
        }

        $closures = MjEventClosures::get_all();
        $today = wp_date('Y-m-d');
        $upcoming = array();
        $past = array();

        foreach ($closures as $closure) {
            if (!isset($closure->closure_date)) {
                continue;
            }
            if ($closure->closure_date >= $today) {
                $upcoming[] = $closure;
            } else {
                $past[] = $closure;
            }
        }
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
                <h2><?php esc_html_e('Ajouter une fermeture', 'mj-member'); ?></h2>
                <form method="post">
                    <?php wp_nonce_field('mj_closure_save', 'mj_closure_nonce'); ?>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="mj-closure-date"><?php esc_html_e('Date', 'mj-member'); ?></label></th>
                            <td>
                                <input type="date" id="mj-closure-date" name="closure_date" required />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="mj-closure-description"><?php esc_html_e('Description (optionnelle)', 'mj-member'); ?></label></th>
                            <td>
                                <input type="text" id="mj-closure-description" name="closure_description" class="regular-text" maxlength="190" />
                            </td>
                        </tr>
                    </table>
                    <?php submit_button(__('Enregistrer la fermeture', 'mj-member')); ?>
                </form>
            </div>

            <h2><?php esc_html_e('Fermetures à venir', 'mj-member'); ?></h2>
            <?php
            if (empty($upcoming)) {
                echo '<p>' . esc_html__('Aucune fermeture planifiée.', 'mj-member') . '</p>';
            } else {
                echo '<table class="widefat striped">';
                echo '<thead><tr>';
                echo '<th>' . esc_html__('Date', 'mj-member') . '</th>';
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

                    echo '<tr>';
                    echo '<td>' . esc_html(wp_date('d/m/Y', strtotime($item->closure_date))) . '</td>';
                    echo '<td>' . esc_html($item->description) . '</td>';
                    echo '<td>';
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
                echo '<th>' . esc_html__('Date', 'mj-member') . '</th>';
                echo '<th>' . esc_html__('Description', 'mj-member') . '</th>';
                echo '</tr></thead><tbody>';
                foreach ($past as $item) {
                    echo '<tr>';
                    echo '<td>' . esc_html(wp_date('d/m/Y', strtotime($item->closure_date))) . '</td>';
                    echo '<td>' . esc_html($item->description) . '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
            }
            ?>
        </div>
        <?php
    }
}
