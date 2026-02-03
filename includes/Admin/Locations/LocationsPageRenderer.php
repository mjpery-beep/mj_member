<?php

namespace Mj\Member\Admin\Locations;

if (!defined('ABSPATH')) {
    exit;
}

final class LocationsPageRenderer
{
    public function render(LocationsPageContext $context): void
    {
        $notice = $context->getNotice();
        if ($notice !== null) {
            $class = 'notice notice-success';
            if ($notice['type'] === 'error') {
                $class = 'notice notice-error';
            } elseif ($notice['type'] === 'warning') {
                $class = 'notice notice-warning';
            } elseif ($notice['type'] === 'info') {
                $class = 'notice notice-info';
            }
            printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($notice['message']));
        }

        foreach ($context->getErrors() as $error) {
            printf('<div class="notice notice-error"><p>%s</p></div>', esc_html($error));
        }

        $isFormAction = $context->isFormAction();
        $isEditAction = $context->isEditAction();

        echo '<div class="mj-locations-admin" style="margin-top:20px;">';
        echo '<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:20px;">';
        echo '<div>';
        if ($isFormAction) {
            printf('<a class="button" href="%s">%s</a>', esc_url($context->getListUrl()), esc_html__('Retour a la liste', 'mj-member'));
        }
        if (!$isFormAction || $isEditAction) {
            printf('<a class="button button-primary" style="margin-left:%s" href="%s">%s</a>', $isFormAction ? '12px' : '0', esc_url($context->getAddUrl()), esc_html__('➕ Ajouter un lieu', 'mj-member'));
        }
        echo '</div>';
        echo '</div>';

        if ($isFormAction) {
            foreach ($context->getFormErrors() as $formError) {
                printf('<div class="notice notice-error"><p>%s</p></div>', esc_html($formError));
            }

            echo '<h2>' . esc_html($isEditAction ? __('Modifier un lieu', 'mj-member') : __('Ajouter un lieu', 'mj-member')) . '</h2>';

            $formValues = $context->getFormValues();
            $mapEmbedUrl = $context->getMapEmbedUrl();

            echo '<form method="post" class="mj-location-form" style="max-width:860px;">';
            wp_nonce_field('mj_location_save', 'mj_location_nonce');
            printf('<input type="hidden" name="location_cover_id" id="mj-location-cover-id" value="%s" />', esc_attr((int) ($formValues['cover_id'] ?? 0)));

            echo '<table class="form-table" role="presentation">';
            echo '<tr><th scope="row"><label for="mj-location-name">' . esc_html__('Nom du lieu', 'mj-member') . '</label></th><td>';
            printf('<input type="text" id="mj-location-name" name="location_name" class="regular-text" value="%s" required />', esc_attr($formValues['name'] ?? ''));
            echo '</td></tr>';

            echo '<tr><th scope="row"><label for="mj-location-slug">' . esc_html__('Identifiant', 'mj-member') . '</label></th><td>';
            printf('<input type="text" id="mj-location-slug" name="location_slug" class="regular-text" value="%s" />', esc_attr($formValues['slug'] ?? ''));
            echo '<p class="description">' . esc_html__('Laisser vide pour generer automatiquement.', 'mj-member') . '</p>';
            echo '</td></tr>';

            echo '<tr><th scope="row"><label for="mj-location-address">' . esc_html__('Adresse', 'mj-member') . '</label></th><td>';
            printf('<input type="text" id="mj-location-address" name="location_address" class="regular-text" value="%s" />', esc_attr($formValues['address_line'] ?? ''));
            echo '<div style="margin-top:8px; display:flex; gap:12px; flex-wrap:wrap;">';
            printf('<input type="text" name="location_postal_code" placeholder="%s" value="%s" style="width:120px;" />', esc_attr__('Code postal', 'mj-member'), esc_attr($formValues['postal_code'] ?? ''));
            printf('<input type="text" name="location_city" placeholder="%s" value="%s" style="width:180px;" />', esc_attr__('Ville', 'mj-member'), esc_attr($formValues['city'] ?? ''));
            printf('<input type="text" name="location_country" placeholder="%s" value="%s" style="width:180px;" />', esc_attr__('Pays', 'mj-member'), esc_attr($formValues['country'] ?? ''));
            echo '</div>';
            echo '</td></tr>';

            echo '<tr><th scope="row"><label for="mj-location-latitude">' . esc_html__('Coordonnees GPS', 'mj-member') . '</label></th><td>';
            printf('<input type="text" id="mj-location-latitude" name="location_latitude" value="%s" placeholder="%s" style="width:160px;" />', esc_attr($formValues['latitude'] ?? ''), esc_attr__('Latitude', 'mj-member'));
            printf('<input type="text" id="mj-location-longitude" name="location_longitude" value="%s" placeholder="%s" style="width:160px; margin-left:12px;" />', esc_attr($formValues['longitude'] ?? ''), esc_attr__('Longitude', 'mj-member'));
            echo '<p class="description">' . esc_html__('Optionnel: precisez latitude et longitude pour plus de precision.', 'mj-member') . '</p>';
            echo '</td></tr>';

            echo '<tr><th scope="row"><label for="mj-location-map-query">' . esc_html__('Requete Google Maps', 'mj-member') . '</label></th><td>';
            printf('<input type="text" id="mj-location-map-query" name="location_map_query" class="regular-text" value="%s" />', esc_attr($formValues['map_query'] ?? ''));
            echo '<p class="description">' . esc_html__('Optionnel: forcer la requete Google Maps (place ID, adresse detaillee, etc.).', 'mj-member') . '</p>';
            echo '</td></tr>';

            echo '<tr><th scope="row">' . esc_html__('Visuel', 'mj-member') . '</th><td>';
            echo '<div id="mj-location-cover-preview" style="margin-bottom:10px;">';
            $coverId = isset($formValues['cover_id']) ? (int) $formValues['cover_id'] : 0;
            if ($coverId > 0) {
                $image = wp_get_attachment_image_src($coverId, 'medium');
                if (!empty($image[0])) {
                    printf('<img src="%s" alt="" style="max-width:240px;height:auto;" />', esc_url($image[0]));
                } else {
                    echo '<span>' . esc_html__('Aucun visuel selectionne.', 'mj-member') . '</span>';
                }
            } else {
                echo '<span>' . esc_html__('Aucun visuel selectionne.', 'mj-member') . '</span>';
            }
            echo '</div>';
            echo '<button type="button" class="button" id="mj-location-cover-select">' . esc_html__('Choisir une image', 'mj-member') . '</button> ';
            echo '<button type="button" class="button" id="mj-location-cover-remove">' . esc_html__('Retirer', 'mj-member') . '</button>';
            echo '</td></tr>';

            echo '<tr><th scope="row"><label for="mj-location-notes">' . esc_html__('Notes internes', 'mj-member') . '</label></th><td>';
            printf('<textarea id="mj-location-notes" name="location_notes" rows="4" cols="60">%s</textarea>', esc_textarea($formValues['notes'] ?? ''));
            echo '</td></tr>';

            echo '<tr><th scope="row">' . esc_html__('Apercu Google Maps', 'mj-member') . '</th><td>';
            $fallback = esc_url(\MjEventLocations::build_map_embed_src($formValues));
            printf('<div id="mj-location-map" data-fallback="%s" style="max-width:520px;">', $fallback);
            if ($mapEmbedUrl !== '') {
                printf('<iframe id="mj-location-map-frame" src="%s" width="520" height="260" style="border:0;" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>', esc_url($mapEmbedUrl));
            } else {
                echo '<p class="description">' . esc_html__('Le plan apparaitra apres avoir renseigne l\'adresse ou la requete.', 'mj-member') . '</p>';
            }
            echo '</div>';
            echo '</td></tr>';

            echo '</table>';

            submit_button($isEditAction ? __('Enregistrer les modifications', 'mj-member') : __('Creer le lieu', 'mj-member'));
            echo '</form>';
        } else {
            echo '<h2>' . esc_html__('Liste des lieux', 'mj-member') . '</h2>';
            $locations = $context->getLocations();
            if (empty($locations)) {
                echo '<p>' . esc_html__('Aucun lieu enregistre pour le moment.', 'mj-member') . '</p>';
            } else {
                echo '<table class="widefat striped" style="margin-top:12px;">';
                echo '<thead><tr>';
                echo '<th>' . esc_html__('Nom', 'mj-member') . '</th>';
                echo '<th>' . esc_html__('Adresse', 'mj-member') . '</th>';
                echo '<th>' . esc_html__('Carte', 'mj-member') . '</th>';
                echo '<th>' . esc_html__('Visuel', 'mj-member') . '</th>';
                echo '<th>' . esc_html__('Actions', 'mj-member') . '</th>';
                echo '</tr></thead><tbody>';

                foreach ($locations as $location) {
                    $addressDisplay = \MjEventLocations::format_address($location);
                    $mapLink = \MjEventLocations::build_map_embed_src($location);
                    $locationId = is_object($location) && isset($location->id) ? (int) $location->id : (int) ($location['id'] ?? 0);
                    $editUrl = add_query_arg(
                        array(
                            'page' => 'mj_locations',
                            'action' => 'edit',
                            'location' => $locationId,
                        ),
                        admin_url('admin.php')
                    );
                    $deleteUrl = add_query_arg(
                        array(
                            'page' => 'mj_locations',
                            'action' => 'delete',
                            'location' => $locationId,
                        ),
                        admin_url('admin.php')
                    );
                    $deleteUrl = wp_nonce_url($deleteUrl, 'mj_location_delete_' . $locationId);

                    echo '<tr>';
                    echo '<td><strong>' . esc_html($location->name ?? ($location['name'] ?? '')) . '</strong><br /><span class="description">' . esc_html__('Slug:', 'mj-member') . ' ' . esc_html($location->slug ?? ($location['slug'] ?? '')) . '</span></td>';
                    if ($addressDisplay !== '') {
                        echo '<td>' . esc_html($addressDisplay) . '</td>';
                    } else {
                        echo '<td><span style="color:#6c757d;">-</span></td>';
                    }
                    echo '<td>';
                    if ($mapLink !== '') {
                        printf('<a class="button button-small" href="%s" target="_blank" rel="noopener noreferrer">%s</a>', esc_url(str_replace('&output=embed', '', $mapLink)), esc_html__('Voir', 'mj-member'));
                    } else {
                        echo '<span style="color:#6c757d;">-</span>';
                    }
                    echo '</td>';
                    echo '<td>';
                    $coverId = is_object($location) && isset($location->cover_id) ? (int) $location->cover_id : (int) ($location['cover_id'] ?? 0);
                    if ($coverId > 0) {
                        echo wp_get_attachment_image($coverId, array(64, 64), false, array('style' => 'max-width:64px;height:auto;'));
                    } else {
                        echo '<span style="color:#6c757d;">-</span>';
                    }
                    echo '</td>';
                    echo '<td>';
                    printf('<a class="button button-small" href="%s">%s</a> ', esc_url($editUrl), esc_html__('Modifier', 'mj-member'));
                    printf('<a class="button button-small" href="%s" onclick="return confirm(\'%s\');">%s</a>', esc_url($deleteUrl), esc_js(__('Supprimer ce lieu ?', 'mj-member')), esc_html__('Supprimer', 'mj-member'));
                    echo '</td>';
                    echo '</tr>';
                }

                echo '</tbody></table>';
            }
        }

        echo '</div>';
    }
}
