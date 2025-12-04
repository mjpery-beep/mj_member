<?php

namespace Mj\Member\Admin\Service;

use Mj\Member\Admin\Page\LocationsPage;
use Mj\Member\Admin\RequestGuard;
use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

final class LocationsActionHandler
{
    /**
     * @var array<string,mixed>
     */
    private $state = array();

    public function handle(): void
    {
        if (!RequestGuard::ensureCapability(Config::capability())) {
            return;
        }

        $action = isset($_REQUEST['action']) ? sanitize_key(wp_unslash($_REQUEST['action'])) : 'list';
        $locationId = isset($_REQUEST['location']) ? (int) $_REQUEST['location'] : 0;

        $this->maybeHandleDelete($action, $locationId);
        $this->maybeHandleFormSubmission($action, $locationId);

        $this->syncGlobalState();
    }

    private function maybeHandleDelete(string $action, int $locationId): void
    {
        if ($action !== 'delete') {
            return;
        }

        if ($locationId <= 0) {
            $this->state['errors'][] = 'Identifiant de lieu invalide.';
            $this->state['force_action'] = 'list';
            return;
        }

        $nonceAction = 'mj_location_delete_' . $locationId;
        $nonceValue = RequestGuard::readNonce($_GET, '_wpnonce');
        if (!RequestGuard::verifyNonce($nonceValue, $nonceAction)) {
            $this->state['errors'][] = 'Verification de securite echouee.';
            $this->state['force_action'] = 'list';
            return;
        }

        $deleteResult = \MjEventLocations::delete($locationId);
        if (is_wp_error($deleteResult)) {
            $this->state['errors'][] = $deleteResult->get_error_message();
            $this->state['force_action'] = 'list';
            return;
        }

        $redirect = add_query_arg(
            array(
                'page' => LocationsPage::slug(),
                'mj_locations_message' => rawurlencode('Lieu supprime.'),
            ),
            admin_url('admin.php')
        );

        wp_safe_redirect($redirect);
        exit;
    }

    private function maybeHandleFormSubmission(string $action, int $locationId): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['mj_location_nonce'])) {
            return;
        }

        $nonceValue = RequestGuard::readNonce($_POST, 'mj_location_nonce');
        if (!RequestGuard::verifyNonce($nonceValue, 'mj_location_save')) {
            $this->state['form_errors'][] = 'Verification de securite echouee.';
            return;
        }

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

        $submitted['cover_id'] = isset($_POST['location_cover_id']) ? (int) $_POST['location_cover_id'] : 0;

        if ($submitted['name'] === '') {
            $this->state['form_errors'][] = 'Le nom du lieu est obligatoire.';
        }

        $formAction = $action === 'edit' ? 'edit' : 'add';
        $targetLocation = $formAction === 'edit' ? (int) $locationId : 0;

        if ($formAction === 'edit' && $targetLocation <= 0) {
            $this->state['form_errors'][] = 'Edition impossible: identifiant invalide.';
        }

        if (!empty($this->state['form_errors'])) {
            $this->prepareFormState($formAction, $targetLocation, $submitted);
            return;
        }

        if ($formAction === 'edit' && $targetLocation > 0) {
            $result = \MjEventLocations::update($targetLocation, $submitted);
            $successMessage = 'Lieu mis a jour.';
        } else {
            $result = \MjEventLocations::create($submitted);
            $successMessage = 'Lieu cree avec succes.';
        }

        if (is_wp_error($result)) {
            $this->state['form_errors'][] = $result->get_error_message();
            $this->prepareFormState($formAction, $targetLocation, $submitted);
            return;
        }

        $redirect = add_query_arg(
            array(
                'page' => LocationsPage::slug(),
                'mj_locations_message' => rawurlencode($successMessage),
            ),
            admin_url('admin.php')
        );

        wp_safe_redirect($redirect);
        exit;
    }

    private function prepareFormState(string $formAction, int $targetLocation, array $submitted): void
    {
        $this->state['force_action'] = $formAction;
        $this->state['location_id'] = $targetLocation;
        $this->state['form_values'] = array_merge(\MjEventLocations::get_default_values(), $submitted);
        $this->state['form_values']['cover_id'] = $submitted['cover_id'];
        $this->state['map_embed_url'] = \MjEventLocations::build_map_embed_src($this->state['form_values']);
    }

    private function syncGlobalState(): void
    {
        if (!empty($this->state)) {
            $GLOBALS['mj_member_locations_form_state'] = $this->state;
        } else {
            unset($GLOBALS['mj_member_locations_form_state']);
        }
    }
}
