<?php

namespace Mj\Member\Core\Ajax\Front;

use Mj\Member\Classes\Crud\MjInventory;
use Mj\Member\Classes\MjOpenAIClient;
use Mj\Member\Classes\MjRoles;
use Mj\Member\Core\Contracts\AjaxHandlerInterface;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

final class InventoryController implements AjaxHandlerInterface
{
    public function registerHooks(): void
    {
        foreach (array('list_items', 'get_item', 'create_item', 'update_item', 'delete_item', 'delete_photo', 'get_item_photo', 'borrow_item', 'return_item', 'list_categories', 'create_category', 'update_category', 'delete_category', 'list_locations', 'create_location', 'update_location', 'delete_location', 'analyze_photo') as $action) {
            add_action('wp_ajax_mj_inventory_' . $action, array($this, $action));
            add_action('wp_ajax_nopriv_mj_inventory_' . $action, array($this, $action));
        }
    }

    private function verify(): void
    {
        $nonce = isset($_REQUEST['nonce']) ? sanitize_text_field(wp_unslash($_REQUEST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'mj_inventory')) {
            wp_send_json_error(array('message' => __('Sécurité échouée.', 'mj-member')), 403);
        }
        $user = wp_get_current_user();
        $allowed = array_intersect((array) $user->roles, MjRoles::getWordPressInventoryRoles());
        if (!$user->exists() || !$allowed) {
            wp_send_json_error(array('message' => __('Accès refusé.', 'mj-member')), 403);
        }
    }

    public function list_items(): void
    {
        $this->verify();
        $filters = array();
        foreach (array('status', 'category_id', 'location_id', 'borrowed', 'q') as $key) {
            if (isset($_REQUEST[$key])) {
                $filters[$key] = sanitize_text_field(wp_unslash($_REQUEST[$key]));
            }
        }
        wp_send_json_success(array('items' => array_map(array($this, 'formatItem'), MjInventory::list($filters)), 'categories' => $this->formatRows(MjInventory::categories()), 'locations' => $this->formatRows(MjInventory::locations())));
    }

    public function get_item(): void
    {
        $this->verify();
        $item = MjInventory::get(absint($_REQUEST['id'] ?? 0));
        $item ? wp_send_json_success(array(
            'item' => $this->formatItem($item),
            'history' => $this->formatRows(MjInventory::borrowHistory((int) $item->id)),
            'photos' => $this->formatRows(MjInventory::photos((int) $item->id)),
        )) : wp_send_json_error(array('message' => __('Objet introuvable.', 'mj-member')), 404);
    }

    public function create_item(): void
    {
        $this->verify();
        $name = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
        if ($name === '') {
            wp_send_json_error(array('message' => __('Le nom est obligatoire.', 'mj-member')), 400);
        }
        $slug = MjInventory::uniqueSlug($name);
        $data = $this->itemData($slug);
        $data['created_by'] = get_current_user_id();
        if (!empty($_FILES['photo'])) {
            $photo = MjInventory::savePhoto($slug, $_FILES['photo']);
            if ($photo === false) {
                wp_send_json_error(array('message' => __('Photo invalide.', 'mj-member')), 400);
            }
            $data = array_merge($data, $photo);
        }
        $id = MjInventory::create($data);
        if ($id && !MjInventory::addPhotos($id, $slug, $this->additionalPhotoFiles())) {
            wp_send_json_error(array('message' => __('Une photo supplémentaire est invalide.', 'mj-member')), 400);
        }
        $id ? wp_send_json_success(array('item' => $this->formatItem(MjInventory::get($id)))) : wp_send_json_error(array('message' => __('Création impossible.', 'mj-member')), 500);
    }

    public function update_item(): void
    {
        $this->verify();
        $id = absint($_POST['id'] ?? 0);
        $item = MjInventory::get($id);
        if (!$item) {
            wp_send_json_error(array('message' => __('Objet introuvable.', 'mj-member')), 404);
        }
        $data = $this->itemData((string) $item->slug);
        if (!empty($_FILES['photo'])) {
            $photo = MjInventory::savePhoto((string) $item->slug, $_FILES['photo']);
            if ($photo === false) {
                wp_send_json_error(array('message' => __('Photo invalide.', 'mj-member')), 400);
            }
            $data = array_merge($data, $photo);
        }
        if (!MjInventory::update($id, $data)) {
            $databaseError = MjInventory::lastDatabaseError();
            $message = __('Modification impossible.', 'mj-member');
            if ($databaseError !== '') {
                $message .= ' ' . $databaseError;
            }
            wp_send_json_error(array('message' => $message), 500);
        }
        if (!MjInventory::addPhotos($id, (string) $item->slug, $this->additionalPhotoFiles())) {
            wp_send_json_error(array('message' => __('Une photo supplémentaire est invalide.', 'mj-member')), 400);
        }
        wp_send_json_success(array('item' => $this->formatItem(MjInventory::get($id))));
    }

    public function delete_item(): void
    {
        $this->verify();
        MjInventory::delete(absint($_POST['id'] ?? 0)) ? wp_send_json_success() : wp_send_json_error(array('message' => __('Suppression impossible.', 'mj-member')), 404);
    }

    public function delete_photo(): void
    {
        $this->verify();
        $itemId = absint($_POST['id'] ?? 0);
        $photoId = absint($_POST['photo_id'] ?? 0);
        MjInventory::deletePhoto($itemId, $photoId) ? wp_send_json_success() : wp_send_json_error(array('message' => __('Suppression de la photo impossible.', 'mj-member')), 404);
    }

    public function get_item_photo(): void
    {
        $this->verify();
        $item = MjInventory::get(absint($_REQUEST['id'] ?? 0));
        if (!$item || empty($item->photo_path)) {
            wp_send_json_error(array('message' => __('Photo introuvable.', 'mj-member')), 404);
        }
        $path = MjInventory::uploadDir((string) $item->slug) . 'cover.jpg';
        $contents = is_readable($path) ? file_get_contents($path) : false;
        $contents ? wp_send_json_success(array('mime' => 'image/jpeg', 'data' => base64_encode($contents))) : wp_send_json_error(array('message' => __('Photo introuvable.', 'mj-member')), 404);
    }

    public function borrow_item(): void
    {
        $this->verify();
        $result = MjInventory::borrow(absint($_POST['id'] ?? 0), get_current_user_id());
        $result ? wp_send_json_success(array('borrowed' => $result)) : wp_send_json_error(array('message' => __('Cet objet est déjà emprunté.', 'mj-member')), 409);
    }

    public function return_item(): void
    {
        $this->verify();
        $item = MjInventory::get(absint($_POST['id'] ?? 0));
        $user = wp_get_current_user();
        $isManager = (bool) array_intersect((array) $user->roles, array('administrator', 'coordinateur'));
        if (!$item || (!$isManager && (int) $item->borrowed_by !== get_current_user_id())) {
            wp_send_json_error(array('message' => __('Retour non autorisé.', 'mj-member')), 403);
        }
        MjInventory::returnItem((int) $item->id, get_current_user_id()) ? wp_send_json_success() : wp_send_json_error(array('message' => __('Retour impossible.', 'mj-member')), 409);
    }

    public function list_categories(): void { $this->verify(); wp_send_json_success(array('categories' => $this->formatRows(MjInventory::categories()))); }
    public function list_locations(): void { $this->verify(); wp_send_json_success(array('locations' => $this->formatRows(MjInventory::locations()))); }

    public function create_category(): void
    {
        $this->verify();
        $id = MjInventory::createCategory((string) ($_POST['name'] ?? ''), (string) ($_POST['icon'] ?? ''));
        $id ? wp_send_json_success(array('id' => $id)) : wp_send_json_error(array('message' => __('Création impossible.', 'mj-member')), 400);
    }

    public function create_location(): void
    {
        $this->verify();
        $id = MjInventory::createLocation((string) ($_POST['name'] ?? ''), (string) ($_POST['icon'] ?? ''), (string) ($_POST['description'] ?? ''));
        $id ? wp_send_json_success(array('id' => $id)) : wp_send_json_error(array('message' => __('Création impossible.', 'mj-member')), 400);
    }

    public function update_category(): void
    {
        $this->verify();
        $updated = MjInventory::updateCategory(absint($_POST['id'] ?? 0), (string) ($_POST['name'] ?? ''), (string) ($_POST['icon'] ?? ''));
        $updated ? wp_send_json_success() : wp_send_json_error(array('message' => __('Modification impossible.', 'mj-member')), 400);
    }

    public function update_location(): void
    {
        $this->verify();
        $updated = MjInventory::updateLocation(absint($_POST['id'] ?? 0), (string) ($_POST['name'] ?? ''), (string) ($_POST['icon'] ?? ''), (string) ($_POST['description'] ?? ''));
        $updated ? wp_send_json_success() : wp_send_json_error(array('message' => __('Modification impossible.', 'mj-member')), 400);
    }

    public function delete_category(): void { $this->deleteTaxonomy('categories'); }
    public function delete_location(): void { $this->deleteTaxonomy('locations'); }

    private function deleteTaxonomy(string $type): void
    {
        $this->verify();
        global $wpdb;
        $id = absint($_POST['id'] ?? 0);
        $deleted = $wpdb->delete(MjInventory::tableForAjax($type), array('id' => $id), array('%d'));
        $deleted ? wp_send_json_success() : wp_send_json_error(array('message' => __('Suppression impossible.', 'mj-member')), 404);
    }

    public function analyze_photo(): void
    {
        $this->verify();
        if (empty($_FILES['photo']['tmp_name'])) {
            wp_send_json_error(array('message' => __('Photo manquante.', 'mj-member')), 400);
        }
        $result = (new MjOpenAIClient())->analyzeInventoryPhoto($_FILES['photo']['tmp_name'], MjInventory::categories(), MjInventory::locations());
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 502);
        }

        $categoryIds = array_map(static function ($category): int {
            return (int) ((array) $category)['id'];
        }, MjInventory::categories());
        if (empty($result['category_id']) || !in_array((int) $result['category_id'], $categoryIds, true)) {
            $newCategoryName = sanitize_text_field((string) ($result['new_category_name'] ?? ''));
            if ($newCategoryName !== '') {
                $existingCategoryId = 0;
                foreach (MjInventory::categories() as $category) {
                    if (strcasecmp($newCategoryName, (string) ((array) $category)['name']) === 0) {
                        $existingCategoryId = (int) ((array) $category)['id'];
                        break;
                    }
                }
                $result['category_id'] = $existingCategoryId ?: MjInventory::createCategory($newCategoryName, (string) ($result['new_category_icon'] ?? ''));
            } else {
                $result['category_id'] = null;
            }
        }
        $locationIds = array_map(static function ($location): int {
            return (int) ((array) $location)['id'];
        }, MjInventory::locations());
        if (empty($result['location_id']) || !in_array((int) $result['location_id'], $locationIds, true)) {
            $newLocationName = sanitize_text_field((string) ($result['new_location_name'] ?? ''));
            if ($newLocationName !== '') {
                $existingLocationId = 0;
                foreach (MjInventory::locations() as $location) {
                    if (strcasecmp($newLocationName, (string) ((array) $location)['name']) === 0) {
                        $existingLocationId = (int) ((array) $location)['id'];
                        break;
                    }
                }
                $result['location_id'] = $existingLocationId ?: MjInventory::createLocation($newLocationName, (string) ($result['new_location_icon'] ?? ''));
            } else {
                $result['location_id'] = null;
            }
        }
        wp_send_json_success($result);
    }

    private function itemData(string $slug): array
    {
        $data = array('slug' => $slug);
        foreach (array('name', 'description', 'status', 'safety_note_long', 'safety_note_short') as $key) {
            $data[$key] = sanitize_textarea_field(wp_unslash($_POST[$key] ?? ''));
        }
        $data['quantity'] = max(1, absint($_POST['quantity'] ?? 1));
        foreach (array('category_id', 'location_id') as $key) {
            $data[$key] = absint($_POST[$key] ?? 0) ?: null;
        }
        return $data;
    }

    private function additionalPhotoFiles(): array
    {
        if (empty($_FILES['photos']) || !is_array($_FILES['photos']['name'] ?? null)) {
            return array();
        }
        $files = array();
        foreach ($_FILES['photos']['name'] as $index => $name) {
            if (($name === '' || $name === null) && (int) ($_FILES['photos']['error'][$index] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $files[] = array(
                'name' => $name,
                'type' => $_FILES['photos']['type'][$index] ?? '',
                'tmp_name' => $_FILES['photos']['tmp_name'][$index] ?? '',
                'error' => $_FILES['photos']['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                'size' => $_FILES['photos']['size'][$index] ?? 0,
            );
        }
        return $files;
    }

    private function formatItem($item): array
    {
        $data = (array) $item;
        $data['id'] = (int) $data['id'];
        $data['quantity'] = max(1, (int) $data['quantity']);
        $data['category_id'] = $data['category_id'] ? (int) $data['category_id'] : null;
        $data['location_id'] = $data['location_id'] ? (int) $data['location_id'] : null;
        $data['borrowed_by'] = $data['borrowed_by'] ? (int) $data['borrowed_by'] : null;
        return $data;
    }

    private function formatRows(array $rows): array
    {
        return array_map(static function ($row): array { return (array) $row; }, $rows);
    }
}