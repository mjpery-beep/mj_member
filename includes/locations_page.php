<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can(MJ_MEMBER_CAPABILITY)) {
    wp_die('Acces refuse');
}

$action = isset($_GET['action']) ? sanitize_key(wp_unslash($_GET['action'])) : 'list';
$location_id = isset($_GET['location']) ? (int) $_GET['location'] : 0;

$errors = array();
$form_errors = array();

$notice_raw = isset($_GET['mj_locations_message']) ? wp_unslash($_GET['mj_locations_message']) : '';
if ($notice_raw !== '') {
    $message = sanitize_text_field(rawurldecode($notice_raw));
    $type    = isset($_GET['mj_locations_message_type']) ? sanitize_key(wp_unslash($_GET['mj_locations_message_type'])) : 'success';

    $class = 'notice notice-success';
    if ($type === 'error') {
        $class = 'notice notice-error';
    } elseif ($type === 'warning') {
        $class = 'notice notice-warning';
    }

    printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
}

$state = array();
if (isset($GLOBALS['mj_member_locations_form_state']) && is_array($GLOBALS['mj_member_locations_form_state'])) {
    $state = $GLOBALS['mj_member_locations_form_state'];
    unset($GLOBALS['mj_member_locations_form_state']);
}

if (isset($state['force_action'])) {
    $action = $state['force_action'];
}

if (isset($state['location_id'])) {
    $location_id = (int) $state['location_id'];
}

if (!empty($state['errors']) && is_array($state['errors'])) {
    $errors = array_merge($errors, $state['errors']);
}

$default_values = MjEventLocations::get_default_values();
$form_values = $default_values;
$map_embed_url = '';

if ($action === 'edit' && $location_id > 0) {
    $existing = MjEventLocations::find($location_id);
    if (!$existing) {
        $errors[] = 'Lieu introuvable.';
        $action = 'add';
        $location_id = 0;
    } else {
        $form_values = array_merge($form_values, (array) $existing);
        $map_embed_url = MjEventLocations::build_map_embed_src($existing);
        if (!isset($form_values['notes']) || $form_values['notes'] === null) {
            $form_values['notes'] = '';
        }
    }
}

if (!empty($state['form_errors']) && is_array($state['form_errors'])) {
    $form_errors = array_merge($form_errors, $state['form_errors']);
}

if (!empty($state['form_values']) && is_array($state['form_values'])) {
    $form_values = array_merge($form_values, $state['form_values']);
    if (!isset($form_values['notes']) || $form_values['notes'] === null) {
        $form_values['notes'] = '';
    }
}

if (isset($state['map_embed_url'])) {
    $map_embed_url = $state['map_embed_url'];
}

if ($map_embed_url === '') {
    $map_embed_url = MjEventLocations::build_map_embed_src($form_values);
}

$locations = MjEventLocations::get_all();

if ($action === 'add' || $action === 'edit') {
    wp_enqueue_media();
    wp_enqueue_script(
        'mj-admin-locations',
        MJ_MEMBER_URL . 'includes/js/admin-locations.js',
        array('jquery'),
        MJ_MEMBER_VERSION,
        true
    );
}

$add_url = add_query_arg(array('page' => 'mj_locations', 'action' => 'add'), admin_url('admin.php'));
$list_url = add_query_arg(array('page' => 'mj_locations'), admin_url('admin.php'));
?>

<div class="mj-locations-admin" style="margin-top:20px;">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:20px;">
        <div>
            <?php if ($action === 'add') : ?>
                <a class="button" href="<?php echo esc_url($list_url); ?>">Retour a la liste</a>
            <?php else : ?>
                <a class="button button-primary" href="<?php echo esc_url($add_url); ?>">➕ Ajouter un lieu</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($errors)) : ?>
        <?php foreach ($errors as $error_message) : ?>
            <div class="notice notice-error"><p><?php echo esc_html($error_message); ?></p></div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if ($action === 'add' || $action === 'edit') : ?>
        <?php if (!empty($form_errors)) : ?>
            <?php foreach ($form_errors as $form_error) : ?>
                <div class="notice notice-error"><p><?php echo esc_html($form_error); ?></p></div>
            <?php endforeach; ?>
        <?php endif; ?>

        <form method="post" class="mj-location-form" style="max-width:860px;">
            <?php wp_nonce_field('mj_location_save', 'mj_location_nonce'); ?>
            <input type="hidden" name="location_cover_id" id="mj-location-cover-id" value="<?php echo esc_attr((int) $form_values['cover_id']); ?>" />

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="mj-location-name">Nom du lieu</label></th>
                    <td>
                        <input type="text" id="mj-location-name" name="location_name" class="regular-text" value="<?php echo esc_attr($form_values['name']); ?>" required />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="mj-location-slug">Identifiant</label></th>
                    <td>
                        <input type="text" id="mj-location-slug" name="location_slug" class="regular-text" value="<?php echo esc_attr($form_values['slug']); ?>" />
                        <p class="description">Laisser vide pour generer automatiquement.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="mj-location-address">Adresse</label></th>
                    <td>
                        <input type="text" id="mj-location-address" name="location_address" class="regular-text" value="<?php echo esc_attr($form_values['address_line']); ?>" />
                        <div style="margin-top:8px; display:flex; gap:12px; flex-wrap:wrap;">
                            <input type="text" name="location_postal_code" placeholder="Code postal" value="<?php echo esc_attr($form_values['postal_code']); ?>" style="width:120px;" />
                            <input type="text" name="location_city" placeholder="Ville" value="<?php echo esc_attr($form_values['city']); ?>" style="width:180px;" />
                            <input type="text" name="location_country" placeholder="Pays" value="<?php echo esc_attr($form_values['country']); ?>" style="width:180px;" />
                        </div>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="mj-location-latitude">Coordonnees GPS</label></th>
                    <td>
                        <input type="text" id="mj-location-latitude" name="location_latitude" value="<?php echo esc_attr($form_values['latitude']); ?>" placeholder="Latitude" style="width:160px;" />
                        <input type="text" id="mj-location-longitude" name="location_longitude" value="<?php echo esc_attr($form_values['longitude']); ?>" placeholder="Longitude" style="width:160px; margin-left:12px;" />
                        <p class="description">Optionnel: precisez latitude et longitude pour plus de precision.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="mj-location-map-query">Requete Google Maps</label></th>
                    <td>
                        <input type="text" id="mj-location-map-query" name="location_map_query" class="regular-text" value="<?php echo esc_attr($form_values['map_query']); ?>" />
                        <p class="description">Optionnel: forcer la requete Google Maps (place ID, adresse detaillee, etc.).</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Visuel</th>
                    <td>
                        <div id="mj-location-cover-preview" style="margin-bottom:10px;">
                            <?php
                            $cover_preview = '';
                            if (!empty($form_values['cover_id'])) {
                                $image = wp_get_attachment_image_src((int) $form_values['cover_id'], 'medium');
                                if (!empty($image[0])) {
                                    $cover_preview = '<img src="' . esc_url($image[0]) . '" alt="" style="max-width:240px;height:auto;" />';
                                }
                            }
                            echo $cover_preview !== '' ? $cover_preview : '<span>Aucun visuel selectionne.</span>';
                            ?>
                        </div>
                        <button type="button" class="button" id="mj-location-cover-select">Choisir une image</button>
                        <button type="button" class="button" id="mj-location-cover-remove">Retirer</button>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="mj-location-notes">Notes internes</label></th>
                    <td>
                        <textarea id="mj-location-notes" name="location_notes" rows="4" cols="60"><?php echo esc_textarea($form_values['notes']); ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Apercu Google Maps</th>
                    <td>
                        <div id="mj-location-map" data-fallback="<?php echo esc_url(MjEventLocations::build_map_embed_src($form_values)); ?>" style="max-width:520px;">
                            <?php if ($map_embed_url !== '') : ?>
                                <iframe id="mj-location-map-frame" src="<?php echo esc_url($map_embed_url); ?>" width="520" height="260" style="border:0;" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                            <?php else : ?>
                                <p class="description">Le plan apparaitra apres avoir renseigne l'adresse ou la requete.</p>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            </table>

            <?php submit_button($action === 'edit' ? 'Enregistrer les modifications' : 'Creer le lieu'); ?>
        </form>
        <hr />
    <?php endif; ?>

    <h2>Liste des lieux</h2>
    <?php if (empty($locations)) : ?>
        <p>Aucun lieu enregistre pour le moment.</p>
    <?php else : ?>
        <table class="widefat striped" style="margin-top:12px;">
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Adresse</th>
                    <th>Carte</th>
                    <th>Visuel</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($locations as $location) : ?>
                    <?php
                    $address_display = MjEventLocations::format_address($location);
                    $map_link = MjEventLocations::build_map_embed_src($location);
                    $edit_url = add_query_arg(
                        array(
                            'page' => 'mj_locations',
                            'action' => 'edit',
                            'location' => (int) $location->id,
                        ),
                        admin_url('admin.php')
                    );
                    $delete_url = add_query_arg(
                        array(
                            'page' => 'mj_locations',
                            'action' => 'delete',
                            'location' => (int) $location->id,
                        ),
                        admin_url('admin.php')
                    );
                    $delete_url = wp_nonce_url($delete_url, 'mj_location_delete_' . (int) $location->id);
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($location->name); ?></strong><br />
                            <span class="description">Slug: <?php echo esc_html($location->slug); ?></span>
                        </td>
                        <td><?php echo $address_display !== '' ? esc_html($address_display) : '<span style="color:#6c757d;">-</span>'; ?></td>
                        <td>
                            <?php if ($map_link !== '') : ?>
                                <a class="button button-small" href="<?php echo esc_url(str_replace('&output=embed', '', $map_link)); ?>" target="_blank" rel="noopener noreferrer">Voir</a>
                            <?php else : ?>
                                <span style="color:#6c757d;">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            if (!empty($location->cover_id)) {
                                echo wp_get_attachment_image((int) $location->cover_id, array(64, 64), false, array('style' => 'max-width:64px;height:auto;'));
                            } else {
                                echo '<span style="color:#6c757d;">-</span>';
                            }
                            ?>
                        </td>
                        <td>
                            <a class="button button-small" href="<?php echo esc_url($edit_url); ?>">Modifier</a>
                            <a class="button button-small" href="<?php echo esc_url($delete_url); ?>" onclick="return confirm('Supprimer ce lieu ?');">Supprimer</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
