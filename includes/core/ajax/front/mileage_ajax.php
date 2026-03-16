<?php
/**
 * AJAX handlers for Mileage Expense Claims (front-end).
 *
 * @package MJ_Member
 */

if (!defined('ABSPATH')) {
    exit;
}

use Mj\Member\Classes\Crud\MjMileage;
use Mj\Member\Classes\Crud\MjMembers;
use Mj\Member\Classes\Crud\MjEventLocations;
use Mj\Member\Classes\MjRoles;
use Mj\Member\Core\Config;

/**
 * Localize mileage widget script data.
 */
function mj_member_mileage_localize(): void
{
    $userId = get_current_user_id();
    $memberObj = $userId ? MjMembers::getByWpUserId($userId) : null;
    $member = $memberObj ? $memberObj->toArray() : null;
    $memberId = $member ? (int) $member['id'] : 0;
    $memberRole = $member ? ($member['role'] ?? '') : '';
    $isCoordinator = MjRoles::isCoordinateur($memberRole);
    $hasAccess = MjRoles::isAnimateurOrCoordinateur($memberRole);

    $costPerKm = (float) get_option('mj_mileage_cost_per_km', 0.4326);
    $disclaimer = get_option('mj_mileage_disclaimer', 'Les déplacements de chez vous jusque la MJ sont déjà payés dans votre salaire.');
    $defaultOriginId = (int) get_option('mj_mileage_default_origin_id', 0);

    // Get own mileage claims
    $ownMileage = array();
    if ($memberId > 0) {
        $rows = MjMileage::get_all(array('member_id' => $memberId, 'limit' => 0));
        $ownMileage = MjMileage::enrich($rows);
    }

    // Get locations for selector
    $locationsList = array();
    $allLocations = MjEventLocations::get_all(array('orderby' => 'name', 'order' => 'ASC'));
    foreach ($allLocations as $loc) {
        $id = is_object($loc) ? ($loc->id ?? 0) : ($loc['id'] ?? 0);
        $name = is_object($loc) ? ($loc->name ?? '') : ($loc['name'] ?? '');
        $address = '';
        if (is_object($loc)) {
            $address = MjEventLocations::format_address($loc);
        }
        $lat = is_object($loc) ? ($loc->latitude ?? null) : ($loc['latitude'] ?? null);
        $lng = is_object($loc) ? ($loc->longitude ?? null) : ($loc['longitude'] ?? null);
        $locationsList[] = array(
            'id'      => (int) $id,
            'name'    => $name,
            'address' => $address,
            'lat'     => $lat ? (float) $lat : null,
            'lng'     => $lng ? (float) $lng : null,
        );
    }

    // Coordinator: all mileage claims + members list
    $allMileage = array();
    $membersList = array();
    if ($isCoordinator) {
        $rows = MjMileage::get_all(array('limit' => 0));
        $allMileage = MjMileage::enrich($rows);

        $allMembers = MjMembers::get_all(array(
            'filters' => array(
                'roles' => array(MjRoles::ANIMATEUR, MjRoles::COORDINATEUR),
            ),
        ));
        $allMembers = array_filter($allMembers, function ($m) {
            return $m->status === 'active';
        });
        foreach ($allMembers as $m) {
            $avatarUrl = '';
            if (!empty($m->photo_id)) {
                $avatarUrl = wp_get_attachment_image_url((int) $m->photo_id, 'thumbnail') ?? '';
            }
            $membersList[] = array(
                'id'     => (int) $m->id,
                'name'   => trim($m->first_name . ' ' . $m->last_name),
                'role'   => $m->role,
                'avatar' => $avatarUrl,
            );
        }
    }

    $formatMileage = function ($row) {
        return array(
            'id'                      => (int) $row->id,
            'member_id'               => (int) $row->member_id,
            'member_name'             => $row->member_name ?? '',
            'trip_date'               => $row->trip_date ?? '',
            'origin'                  => $row->origin ?? '',
            'origin_location_id'      => $row->origin_location_id ? (int) $row->origin_location_id : null,
            'destination'             => $row->destination ?? '',
            'destination_location_id' => $row->destination_location_id ? (int) $row->destination_location_id : null,
            'distance_km'             => (float) ($row->distance_km ?? 0),
            'cost_per_km'             => (float) ($row->cost_per_km ?? 0),
            'total_cost'              => (float) ($row->total_cost ?? 0),
            'description'             => $row->description ?? '',
            'round_trip'              => !empty($row->round_trip),
            'status'                  => $row->status ?? 'pending',
            'reviewed_by'             => $row->reviewed_by ? (int) $row->reviewed_by : null,
            'reviewer_comment'        => $row->reviewer_comment ?? '',
            'created_at'              => $row->created_at ?? '',
        );
    };

    $googleApiKey = get_option('elementor_google_maps_api_key', '');

    wp_localize_script('mj-member-mileage', 'mjMileage', array(
        'ajaxUrl'         => admin_url('admin-ajax.php'),
        'nonce'           => wp_create_nonce('mj-mileage'),
        'memberId'        => $memberId,
        'isCoordinator'   => $isCoordinator,
        'hasAccess'       => $hasAccess,
        'costPerKm'       => $costPerKm,
        'disclaimer'      => $disclaimer,
        'defaultOriginId' => $defaultOriginId,
        'googleApiKey'    => $googleApiKey,
        'locations'       => $locationsList,
        'members'         => $membersList,
        'ownMileage'      => array_values(array_map($formatMileage, $ownMileage)),
        'allMileage'      => $isCoordinator ? array_values(array_map($formatMileage, $allMileage)) : array(),
        'statusLabels'    => MjMileage::get_status_labels(),
        'i18n'            => array(
            'title'              => __('Frais kilométriques', 'mj-member'),
            'newTrip'            => __('Nouveau trajet', 'mj-member'),
            'tripDate'           => __('Date du trajet', 'mj-member'),
            'origin'             => __('Départ', 'mj-member'),
            'destination'        => __('Destination', 'mj-member'),
            'distance'           => __('Distance (km)', 'mj-member'),
            'costPerKm'          => __('Coût/km', 'mj-member'),
            'totalCost'          => __('Montant', 'mj-member'),
            'description'        => __('Description / Justification', 'mj-member'),
            'roundTrip'          => __('Aller-retour', 'mj-member'),
            'status'             => __('Statut', 'mj-member'),
            'date'               => __('Date', 'mj-member'),
            'member'             => __('Membre', 'mj-member'),
            'actions'            => __('Actions', 'mj-member'),
            'approve'            => __('Approuver', 'mj-member'),
            'reimburse'          => __('Marquer remboursé', 'mj-member'),
            'reject'             => __('Refuser', 'mj-member'),
            'delete'             => __('Supprimer', 'mj-member'),
            'edit'               => __('Modifier', 'mj-member'),
            'cancel'             => __('Annuler', 'mj-member'),
            'save'               => __('Enregistrer', 'mj-member'),
            'submit'             => __('Soumettre', 'mj-member'),
            'close'              => __('Fermer', 'mj-member'),
            'noTrips'            => __('Aucun trajet enregistré.', 'mj-member'),
            'myTrips'            => __('Mes trajets', 'mj-member'),
            'allTrips'           => __('Tous les trajets', 'mj-member'),
            'filterByMember'     => __('Filtrer par membre', 'mj-member'),
            'filterByStatus'     => __('Filtrer par statut', 'mj-member'),
            'allMembers'         => __('Tous les membres', 'mj-member'),
            'allStatuses'        => __('Tous les statuts', 'mj-member'),
            'pending'            => __('En attente', 'mj-member'),
            'approved'           => __('Approuvé', 'mj-member'),
            'rejected'           => __('Refusé', 'mj-member'),
            'reimbursed'         => __('Remboursé', 'mj-member'),
            'total'              => __('Total', 'mj-member'),
            'totalKm'            => __('Total km', 'mj-member'),
            'rejectionReason'    => __('Motif du refus', 'mj-member'),
            'rejectionReasonRequired' => __('Veuillez indiquer un motif de refus.', 'mj-member'),
            'success'            => __('Opération réussie.', 'mj-member'),
            'error'              => __('Une erreur est survenue.', 'mj-member'),
            'confirmDelete'      => __('Êtes-vous sûr de vouloir supprimer ce trajet ?', 'mj-member'),
            'distanceRequired'   => __('La distance est requise.', 'mj-member'),
            'descriptionRequired' => __('La description / justification est requise.', 'mj-member'),
            'originRequired'     => __('Le lieu de départ est requis.', 'mj-member'),
            'destinationRequired' => __('La destination est requise.', 'mj-member'),
            'currency'           => __('€', 'mj-member'),
            'summaryByMember'    => __('Résumé par membre', 'mj-member'),
            'pendingAmount'      => __('En attente', 'mj-member'),
            'reimbursedAmount'   => __('Remboursé', 'mj-member'),
            'selectLocation'     => __('Choisir un lieu existant', 'mj-member'),
            'manualAddress'      => __('Adresse manuelle', 'mj-member'),
            'useLocation'        => __('Utiliser un lieu existant', 'mj-member'),
            'calculateRoute'     => __('Calculer l\'itinéraire', 'mj-member'),
            'routeCalculated'    => __('Itinéraire calculé', 'mj-member'),
            'editTrip'           => __('Modifier le trajet', 'mj-member'),
        ),
    ));
}

/**
 * Register AJAX actions for mileage.
 */
function mj_member_register_mileage_ajax(): void
{
    add_action('wp_ajax_mj_mileage_create', 'mj_member_mileage_create_handler');
    add_action('wp_ajax_mj_mileage_update', 'mj_member_mileage_update_handler');
    add_action('wp_ajax_mj_mileage_update_status', 'mj_member_mileage_update_status_handler');
    add_action('wp_ajax_mj_mileage_delete', 'mj_member_mileage_delete_handler');
}
add_action('init', 'mj_member_register_mileage_ajax');

/**
 * Get current member context.
 *
 * @return array{member: array, memberId: int, isCoordinator: bool}
 */
function mj_member_mileage_get_current_member(): array
{
    $userId = get_current_user_id();
    if (!$userId) {
        wp_send_json_error(array('message' => __('Vous devez être connecté.', 'mj-member')), 403);
    }

    $memberObj = MjMembers::getByWpUserId($userId);
    $member = $memberObj ? $memberObj->toArray() : null;
    if (!$member) {
        wp_send_json_error(array('message' => __('Membre introuvable.', 'mj-member')), 403);
    }

    $role = $member['role'] ?? '';
    $hasAccess = MjRoles::isAnimateurOrCoordinateur($role);
    if (!$hasAccess) {
        wp_send_json_error(array('message' => __('Accès refusé.', 'mj-member')), 403);
    }

    return array(
        'member'        => $member,
        'memberId'      => (int) $member['id'],
        'isCoordinator' => MjRoles::isCoordinateur($role),
    );
}

/**
 * Create a mileage claim.
 */
function mj_member_mileage_create_handler(): void
{
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'mj-mileage')) {
        wp_send_json_error(array('message' => __('Sécurité échouée.', 'mj-member')), 403);
    }

    $ctx = mj_member_mileage_get_current_member();

    $tripDate    = isset($_POST['trip_date']) ? sanitize_text_field(wp_unslash($_POST['trip_date'])) : '';
    $origin      = isset($_POST['origin']) ? sanitize_text_field(wp_unslash($_POST['origin'])) : '';
    $originLocId = isset($_POST['origin_location_id']) && $_POST['origin_location_id'] !== '' ? (int) $_POST['origin_location_id'] : null;
    $destination = isset($_POST['destination']) ? sanitize_text_field(wp_unslash($_POST['destination'])) : '';
    $destLocId   = isset($_POST['destination_location_id']) && $_POST['destination_location_id'] !== '' ? (int) $_POST['destination_location_id'] : null;
    $distanceKm  = isset($_POST['distance_km']) ? (float) $_POST['distance_km'] : 0;
    $description = isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '';
    $roundTrip   = !empty($_POST['round_trip']);
    $costPerKm   = (float) get_option('mj_mileage_cost_per_km', 0.4326);

    if ($tripDate === '') {
        $tripDate = current_time('Y-m-d');
    }
    if ($origin === '') {
        wp_send_json_error(array('message' => __('Le lieu de départ est requis.', 'mj-member')), 400);
    }
    if ($destination === '') {
        wp_send_json_error(array('message' => __('La destination est requise.', 'mj-member')), 400);
    }
    if ($distanceKm <= 0) {
        wp_send_json_error(array('message' => __('La distance doit être supérieure à 0.', 'mj-member')), 400);
    }
    if ($description === '') {
        wp_send_json_error(array('message' => __('La description est requise.', 'mj-member')), 400);
    }

    if ($roundTrip) {
        $distanceKm = $distanceKm * 2;
    }

    $result = MjMileage::create(array(
        'member_id'               => $ctx['memberId'],
        'trip_date'               => $tripDate,
        'origin'                  => $origin,
        'origin_location_id'      => $originLocId,
        'destination'             => $destination,
        'destination_location_id' => $destLocId,
        'distance_km'             => $distanceKm,
        'cost_per_km'             => $costPerKm,
        'description'             => $description,
        'round_trip'              => $roundTrip ? 1 : 0,
    ));

    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()), 400);
    }

    $row = MjMileage::get_by_id($result);
    wp_send_json_success(array(
        'id'          => $result,
        'total_cost'  => $row ? (float) $row->total_cost : 0,
        'distance_km' => $row ? (float) $row->distance_km : 0,
        'message'     => __('Trajet enregistré.', 'mj-member'),
    ));
}

/**
 * Update a mileage claim (only owner, only pending).
 */
function mj_member_mileage_update_handler(): void
{
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'mj-mileage')) {
        wp_send_json_error(array('message' => __('Sécurité échouée.', 'mj-member')), 403);
    }

    $ctx = mj_member_mileage_get_current_member();

    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    $existing = MjMileage::get_by_id($id);
    if (!$existing) {
        wp_send_json_error(array('message' => __('Trajet introuvable.', 'mj-member')), 404);
    }

    // Only owner can edit, and only if pending
    if ((int) $existing->member_id !== $ctx['memberId'] && !$ctx['isCoordinator']) {
        wp_send_json_error(array('message' => __('Accès refusé.', 'mj-member')), 403);
    }
    if ($existing->status !== MjMileage::STATUS_PENDING) {
        wp_send_json_error(array('message' => __('Seuls les trajets en attente peuvent être modifiés.', 'mj-member')), 400);
    }

    $data = array();
    if (isset($_POST['trip_date'])) {
        $data['trip_date'] = sanitize_text_field(wp_unslash($_POST['trip_date']));
    }
    if (isset($_POST['origin'])) {
        $data['origin'] = sanitize_text_field(wp_unslash($_POST['origin']));
    }
    if (array_key_exists('origin_location_id', $_POST)) {
        $data['origin_location_id'] = $_POST['origin_location_id'] !== '' ? (int) $_POST['origin_location_id'] : null;
    }
    if (isset($_POST['destination'])) {
        $data['destination'] = sanitize_text_field(wp_unslash($_POST['destination']));
    }
    if (array_key_exists('destination_location_id', $_POST)) {
        $data['destination_location_id'] = $_POST['destination_location_id'] !== '' ? (int) $_POST['destination_location_id'] : null;
    }
    if (isset($_POST['distance_km'])) {
        $data['distance_km'] = (float) $_POST['distance_km'];
    }
    if (isset($_POST['description'])) {
        $data['description'] = sanitize_textarea_field(wp_unslash($_POST['description']));
    }
    if (isset($_POST['round_trip'])) {
        $data['round_trip'] = !empty($_POST['round_trip']) ? 1 : 0;
    }

    $data['cost_per_km'] = (float) get_option('mj_mileage_cost_per_km', 0.4326);

    $result = MjMileage::update($id, $data);
    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()), 400);
    }

    wp_send_json_success(array('message' => __('Trajet mis à jour.', 'mj-member')));
}

/**
 * Update mileage claim status (coordinator only).
 */
function mj_member_mileage_update_status_handler(): void
{
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'mj-mileage')) {
        wp_send_json_error(array('message' => __('Sécurité échouée.', 'mj-member')), 403);
    }

    $ctx = mj_member_mileage_get_current_member();
    if (!$ctx['isCoordinator']) {
        wp_send_json_error(array('message' => __('Réservé au coordinateur.', 'mj-member')), 403);
    }

    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    $newStatus = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : '';
    $comment = isset($_POST['comment']) ? sanitize_textarea_field(wp_unslash($_POST['comment'])) : '';

    $allowed = array(
        MjMileage::STATUS_APPROVED,
        MjMileage::STATUS_REJECTED,
        MjMileage::STATUS_REIMBURSED,
    );
    if (!in_array($newStatus, $allowed, true)) {
        wp_send_json_error(array('message' => __('Statut invalide.', 'mj-member')), 400);
    }

    $existing = MjMileage::get_by_id($id);
    if (!$existing) {
        wp_send_json_error(array('message' => __('Trajet introuvable.', 'mj-member')), 404);
    }

    if ($newStatus === MjMileage::STATUS_REJECTED && $comment === '') {
        wp_send_json_error(array('message' => __('Un motif de refus est requis.', 'mj-member')), 400);
    }

    $data = array(
        'status'           => $newStatus,
        'reviewed_by'      => $ctx['memberId'],
        'reviewed_at'      => current_time('mysql'),
        'reviewer_comment' => $comment,
    );

    $result = MjMileage::update($id, $data);
    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()), 400);
    }

    wp_send_json_success(array('message' => __('Statut mis à jour.', 'mj-member')));
}

/**
 * Delete a mileage claim.
 */
function mj_member_mileage_delete_handler(): void
{
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'mj-mileage')) {
        wp_send_json_error(array('message' => __('Sécurité échouée.', 'mj-member')), 403);
    }

    $ctx = mj_member_mileage_get_current_member();

    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    $existing = MjMileage::get_by_id($id);
    if (!$existing) {
        wp_send_json_error(array('message' => __('Trajet introuvable.', 'mj-member')), 404);
    }

    // Only owner or coordinator can delete
    if ((int) $existing->member_id !== $ctx['memberId'] && !$ctx['isCoordinator']) {
        wp_send_json_error(array('message' => __('Accès refusé.', 'mj-member')), 403);
    }

    // Owner can only delete pending
    if ((int) $existing->member_id === $ctx['memberId'] && !$ctx['isCoordinator'] && $existing->status !== MjMileage::STATUS_PENDING) {
        wp_send_json_error(array('message' => __('Seuls les trajets en attente peuvent être supprimés.', 'mj-member')), 400);
    }

    $result = MjMileage::delete($id);
    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()), 400);
    }

    wp_send_json_success(array('message' => __('Trajet supprimé.', 'mj-member')));
}
