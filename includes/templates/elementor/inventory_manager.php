<?php

use Mj\Member\Core\AssetsManager;

if (!defined('ABSPATH')) {
    exit;
}

AssetsManager::requirePackage('inventory-manager');

$inventoryUser = wp_get_current_user();
$inventoryPreview = function_exists('is_elementor_preview') && is_elementor_preview();
$inventoryAllowed = $inventoryPreview || ($inventoryUser->exists() && (bool) array_intersect((array) $inventoryUser->roles, array('administrator', 'animateur', 'coordinateur')));
if (!$inventoryAllowed) {
    return;
}
?>
<section class="mj-inventory" data-mj-inventory>
    <div class="mj-inventory__message" role="status" aria-live="polite"></div>
    <div class="mj-inventory__filters">
        <div class="mj-inventory__search-row">
            <label class="mj-inventory__sr-only" for="mj-inventory-search">Rechercher dans l’inventaire</label>
            <span class="mj-inventory__search-icon" aria-hidden="true">⌕</span>
            <input id="mj-inventory-search" class="mj-inventory__search" type="search" placeholder="Rechercher un objet…" aria-label="Rechercher un objet">
            <button type="button" class="mj-inventory__clear-search" data-action="clear-search" aria-label="Effacer la recherche">×</button>
            <button type="button" class="mj-inventory__filter-toggle" data-action="toggle-filters" aria-expanded="false" aria-controls="mj-inventory-filter-panel">Filtres <span data-filter-count>0</span></button>
        </div>
        <div id="mj-inventory-filter-panel" class="mj-inventory__filter-panel" data-filter-panel hidden>
            <label>Catégorie<select class="mj-inventory__filter" data-filter="category_id"><option value="">Toutes</option></select></label>
            <label>Localisation<select class="mj-inventory__filter" data-filter="location_id"><option value="">Toutes</option></select></label>
            <label>État<select class="mj-inventory__filter" data-filter="status"><option value="">Tous</option><option value="good">Bon état</option><option value="damaged">Abîmé</option><option value="broken">Cassé</option></select></label>
            <button type="button" class="mj-inventory__reset" data-action="reset-filters">Réinitialiser</button>
        </div>
        <div class="mj-inventory__active-filters" data-active-filters aria-live="polite"></div>
        <div class="mj-inventory__actions">
            <button type="button" class="mj-inventory__button mj-inventory__button--primary" data-action="new">Ajouter un objet</button>
            <button type="button" class="mj-inventory__button" data-action="add-category">Ajouter une catégorie</button>
            <button type="button" class="mj-inventory__button" data-action="add-location">Ajouter une localisation</button>
        </div>
    </div>
    <div class="mj-inventory__grid" data-items></div>
    <dialog class="mj-inventory__dialog" data-dialog>
        <form method="dialog" class="mj-inventory__form" data-form enctype="multipart/form-data">
            <button type="button" class="mj-inventory__close" data-action="close" aria-label="Fermer">×</button>
            <h3 data-form-title>Nouvel objet</h3>
            <input type="hidden" name="id">
            <label>Nom<input required name="name"></label>
            <label>Description<textarea name="description"></textarea></label>
            <label>Photo<input type="file" name="photo" accept="image/*" data-photo></label>
            <button type="button" class="mj-inventory__button" data-action="analyze">Analyser avec l'IA</button>
            <label>État<select name="status"><option value="good">Bon état</option><option value="damaged">Abîmé</option><option value="broken">Cassé</option></select></label>
            <label>Catégorie<select name="category_id" data-form-category></select></label>
            <label>Localisation<select name="location_id" data-form-location></select></label>
            <label>Note de sécurité courte<input name="safety_note_short"></label>
            <label>Note de sécurité<textarea name="safety_note_long"></textarea></label>
            <button type="submit" class="mj-inventory__button mj-inventory__button--primary">Enregistrer</button>
        </form>
    </dialog>
</section>
