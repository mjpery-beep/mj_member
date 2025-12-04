<?php

namespace Mj\Member\Admin\Page;

use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

final class LocationsPage
{
    public static function slug(): string
    {
        return 'mj_locations';
    }

    public static function registerHooks(?string $hookSuffix): void
    {
        if ($hookSuffix === null || $hookSuffix === '') {
            return;
        }

        add_action('load-' . $hookSuffix, array(__CLASS__, 'handleLoad'));
    }

    public static function render(): void
    {
        if (!current_user_can(Config::capability())) {
            wp_die(esc_html__('Accès refusé.', 'mj-member'));
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Gestion des lieux', 'mj-member'); ?></h1>
            <?php require Config::path() . 'includes/locations_page.php'; ?>
        </div>
        <?php
    }

    public static function handleLoad(): void
    {
        if (!current_user_can(Config::capability())) {
            return;
        }

        $action = isset($_REQUEST['action']) ? sanitize_key(wp_unslash($_REQUEST['action'])) : 'list';
        $locationId = isset($_REQUEST['location']) ? (int) $_REQUEST['location'] : 0;

        $state = array();

        if ($action === 'delete') {
            if ($locationId <= 0) {
                $state['errors'][] = 'Identifiant de lieu invalide.';
                $state['force_action'] = 'list';
            } else {
                $nonceAction = 'mj_location_delete_' . $locationId;
                $nonceValue = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
                if ($nonceValue === '' || !wp_verify_nonce($nonceValue, $nonceAction)) {
                    $state['errors'][] = 'Verification de securite echouee.';
                    $state['force_action'] = 'list';
                } else {
                    $delete = \MjEventLocations::delete($locationId);
                    if (is_wp_error($delete)) {
                        $state['errors'][] = $delete->get_error_message();
                        $state['force_action'] = 'list';
                    } else {
                        $redirect = add_query_arg(
                            array(
                                'page' => self::slug(),
                                'mj_locations_message' => rawurlencode('Lieu supprime.'),
                            ),
                            admin_url('admin.php')
                        );
                        wp_safe_redirect($redirect);
                        exit;
                    }
                }
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mj_location_nonce'])) {
            $nonceValue = sanitize_text_field(wp_unslash($_POST['mj_location_nonce']));
            if (!wp_verify_nonce($nonceValue, 'mj_location_save')) {
                $state['form_errors'][] = 'Verification de securite echouee.';
            } else {
                $submitted = array(
                    'name' => isset($_POST['location_name']) ? sanitize_text_field(wp_unslash($_POST['location_name'])) : '',
                    'slug' => isset($_POST['location_slug']) ? sanitize_title(wp_unslash($_POST['location_slug'])) : '',
                    'address_line' => isset($_POST['location_address']) ? sanitize_text_field(wp_unslash($_POST['location_address'])) : '',
                    'postal_code' => isset($_POST['location_postal_code']) ? sanitize_text_field(wp_unslash($_POST['location_postal_code'])) : '',
                    'city' => isset($_POST['location_city']) ? sanitize_text_field(wp_unslash($_POST['location_city'])) : '',
                    'country' => isset($_POST['location_country']) ? sanitize_text_field(wp_unslash($_POST['location_country'])) : '',
                    'latitude' => isset($_POST['location_latitude']) ? sanitize_text_field(wp_unslash($_POST['location_latitude'])) : '',
                    'longitude' => isset($_POST['location_longitude']) ? sanitize_text_field(wp_unslash($_POST['location_longitude'])) : '',
                    'map_query' => isset($_POST['location_map_query']) ? sanitize_text_field(wp_unslash($_POST['location_map_query'])) : '',
                    'notes' => isset($_POST['location_notes']) ? sanitize_textarea_field(wp_unslash($_POST['location_notes'])) : '',
                );

                $coverId = isset($_POST['location_cover_id']) ? (int) $_POST['location_cover_id'] : 0;
                $submitted['cover_id'] = $coverId;

                if ($submitted['name'] === '') {
                    $state['form_errors'][] = 'Le nom du lieu est obligatoire.';
                }

                $formAction = $action === 'edit' ? 'edit' : 'add';
                $targetLocation = $formAction === 'edit' ? (int) $locationId : 0;

                if ($formAction === 'edit' && $targetLocation <= 0) {
                    $state['form_errors'][] = 'Edition impossible: identifiant invalide.';
                }

                if (empty($state['form_errors'])) {
                    if ($formAction === 'edit' && $targetLocation > 0) {
                        $result = \MjEventLocations::update($targetLocation, $submitted);
                        $successMessage = 'Lieu mis a jour.';
                    } else {
                        $result = \MjEventLocations::create($submitted);
                        $successMessage = 'Lieu cree avec succes.';
                    }

                    if (is_wp_error($result)) {
                        $state['form_errors'][] = $result->get_error_message();
                    } else {
                        $redirect = add_query_arg(
                            array(
                                'page' => self::slug(),
                                'mj_locations_message' => rawurlencode($successMessage),
                            ),
                            admin_url('admin.php')
                        );
                        wp_safe_redirect($redirect);
                        exit;
                    }
                }

                if (!empty($state['form_errors'])) {
                    $state['force_action'] = $formAction;
                    $state['location_id'] = $targetLocation;
                    $state['form_values'] = array_merge(\MjEventLocations::get_default_values(), $submitted);
                    $state['form_values']['cover_id'] = $submitted['cover_id'];
                    $state['map_embed_url'] = \MjEventLocations::build_map_embed_src($state['form_values']);
                }
            }
        }

        if (!empty($state)) {
            $GLOBALS['mj_member_locations_form_state'] = $state;
        } else {
            unset($GLOBALS['mj_member_locations_form_state']);
        }
    }
}
