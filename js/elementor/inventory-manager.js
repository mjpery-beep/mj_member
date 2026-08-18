(function () {
    'use strict';

    const config = window.mjInventoryConfig || {};
    const request = async (action, data, files) => {
        const body = new FormData();
        body.append('action', 'mj_inventory_' + action);
        body.append('nonce', config.nonce || '');
        Object.entries(data || {}).forEach(([key, value]) => body.append(key, value == null ? '' : value));
        if (files) Object.entries(files).forEach(([key, value]) => {
            if (Array.isArray(value)) value.forEach((file) => { if (file) body.append(key + '[]', file); });
            else if (value) body.append(key, value);
        });
        const response = await fetch(config.ajaxUrl || '/wp-admin/admin-ajax.php', { method: 'POST', body });
        const json = await response.json();
        if (!json.success) throw new Error(json.data && json.data.message ? json.data.message : 'Une erreur est survenue.');
        return json.data;
    };

    const escapeHtml = (value) => String(value || '').replace(/[&<>'"]/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[char]));

    document.querySelectorAll('[data-mj-inventory]').forEach((root) => {
        const grid = root.querySelector('[data-items]');
        const form = root.querySelector('[data-form]');
        const dialog = root.querySelector('[data-dialog]');
        const detailDialog = root.querySelector('[data-detail-dialog]');
        const detailContent = root.querySelector('[data-detail-content]');
        const taxonomyDialog = root.querySelector('[data-taxonomy-dialog]');
        const taxonomyForm = root.querySelector('[data-taxonomy-form]');
        const taxonomyIconInput = root.querySelector('[data-taxonomy-icon]');
        const taxonomyEmojiPicker = root.querySelector('[data-taxonomy-emoji-picker]');
        const coverPreview = root.querySelector('[data-cover-preview]');
        const photosPreview = root.querySelector('[data-photos-preview]');
        const message = root.querySelector('.mj-inventory__message');
        const filterPanel = root.querySelector('[data-filter-panel]');
        const filterToggle = root.querySelector('[data-action="toggle-filters"]');
        const filterCount = root.querySelector('[data-filter-count]');
        const activeFilters = root.querySelector('[data-active-filters]');
        let items = [];
        let taxonomies = { categories: [], locations: [] };
        let editingItem = null;

        const notify = (text, error) => { message.textContent = text || ''; message.classList.toggle('is-error', !!error); };
        const fillSelect = (selector, rows, empty) => {
            root.querySelectorAll(selector).forEach((select) => {
                const current = select.value;
                select.innerHTML = '<option value="">' + empty + '</option>' + rows.map((row) => '<option value="' + row.id + '">' + escapeHtml(row.name) + '</option>').join('');
                select.value = current;
            });
        };
        const updateFilterSummary = () => {
            const search = root.querySelector('.mj-inventory__search');
            const active = [];
            if (search.value.trim()) active.push({ key: 'q', label: 'Recherche : ' + search.value.trim() });
            root.querySelectorAll('[data-filter]').forEach((select) => {
                if (!select.value) return;
                const option = select.options[select.selectedIndex];
                active.push({ key: select.dataset.filter, label: option ? option.textContent : select.value });
            });
            filterCount.textContent = active.length;
            filterToggle.classList.toggle('has-active', active.length > 0);
            activeFilters.innerHTML = active.map((filter) => '<button type="button" class="mj-inventory__filter-chip" data-action="remove-filter" data-filter-key="' + filter.key + '">' + escapeHtml(filter.label) + ' <span aria-hidden="true">×</span></button>').join('');
        };
        const render = () => {
            grid.innerHTML = items.length ? items.map((item) => '<article class="mj-inventory__card" data-id="' + item.id + '">' + (item.thumbnail ? '<img src="' + item.thumbnail + '" alt="">' : '<div class="mj-inventory__placeholder">MJ</div>') + '<div class="mj-inventory__card-body"><h2>' + escapeHtml(item.name) + '</h2><span class="mj-inventory__status mj-inventory__status--' + escapeHtml(item.status) + '">' + escapeHtml({ good: 'Bon état', damaged: 'Abîmé', broken: 'Cassé' }[item.status]) + '</span>' + (item.borrowed_by ? '<span class="mj-inventory__borrowed">Emprunté</span>' : '') + '<p>' + escapeHtml(item.location_icon || '') + ' ' + escapeHtml(item.location_name || 'Localisation non définie') + ' · ' + escapeHtml(item.quantity || 1) + ' unité(s)</p><button type="button" data-action="view">Fiche</button> <button type="button" data-action="edit">Modifier</button> <button type="button" data-action="delete">Supprimer</button></div></article>').join('') : '<p class="mj-inventory__empty">Aucun objet trouvé.</p>';
            const taxonomyManager = root.querySelector('[data-taxonomies]');
            if (taxonomyManager) {
                taxonomyManager.innerHTML = ['categories', 'locations'].map((type) => '<div><strong>' + (type === 'categories' ? 'Catégories' : 'Localisations') + '</strong> ' + taxonomies[type].map((row) => '<span class="mj-inventory__taxonomy">' + escapeHtml(row.icon || '') + ' ' + escapeHtml(row.name) + ' <button type="button" data-action="edit-taxonomy" data-taxonomy="' + type + '" data-taxonomy-id="' + row.id + '" aria-label="Modifier ' + escapeHtml(row.name) + '">Modifier</button><button type="button" data-action="delete-taxonomy" data-taxonomy="' + type + '" data-taxonomy-id="' + row.id + '" aria-label="Supprimer ' + escapeHtml(row.name) + '">×</button></span>').join('') + '</div>').join('');
            }
        };
        const load = async () => {
            try {
                const filters = {};
                root.querySelectorAll('[data-filter]').forEach((input) => { if (input.value) filters[input.dataset.filter] = input.value; });
                const search = root.querySelector('.mj-inventory__search');
                if (search.value) filters.q = search.value;
                const data = await request('list_items', filters);
                items = data.items || [];
                taxonomies = { categories: data.categories || [], locations: data.locations || [] };
                fillSelect('[data-filter="category_id"]', taxonomies.categories, 'Toutes les catégories');
                fillSelect('[data-filter="location_id"]', taxonomies.locations, 'Toutes les localisations');
                fillSelect('[data-form-category]', taxonomies.categories, 'Choisir une catégorie');
                fillSelect('[data-form-location]', taxonomies.locations, 'Choisir une localisation');
                render();
                updateFilterSummary();
                notify('');
            } catch (error) { notify(error.message, true); }
        };
        const renderExistingPhotos = (item, photos) => {
            coverPreview.innerHTML = item && item.thumbnail ? '<figure class="mj-inventory__photo-preview"><img src="' + item.thumbnail + '" alt="Photo principale"><button type="button" data-action="delete-photo" data-photo-id="0" aria-label="Supprimer la photo principale">×</button></figure>' : '';
            photosPreview.innerHTML = (photos || []).map((photo) => '<figure class="mj-inventory__photo-preview"><img src="' + photo.thumbnail + '" alt="Photo supplémentaire"><button type="button" data-action="delete-photo" data-photo-id="' + photo.id + '" aria-label="Supprimer cette photo">×</button></figure>').join('');
        };
        const renderSelectedPhotos = (input, target) => {
            target.innerHTML = Array.from(input.files || []).map((file) => '<figure class="mj-inventory__photo-preview"><img src="' + URL.createObjectURL(file) + '" alt="Nouvelle photo sélectionnée"></figure>').join('');
        };
        const openForm = async (item) => {
            form.reset();
            editingItem = item || null;
            form.querySelector('[name="id"]').value = item ? item.id : '';
            form.querySelector('[data-form-title]').textContent = item ? 'Modifier l’objet' : 'Nouvel objet';
            if (item) {
                try {
                    const data = await request('get_item', { id: item.id });
                    editingItem = data.item;
                    Object.keys(data.item).forEach((key) => { const input = form.querySelector('[name="' + key + '"]'); if (input && data.item[key] != null) input.value = data.item[key]; });
                    renderExistingPhotos(data.item, data.photos);
                } catch (error) { notify(error.message, true); }
            } else {
                renderExistingPhotos(null, []);
            }
            dialog.showModal();
        };
        const openTaxonomyForm = (type, row) => {
            taxonomyForm.reset();
            taxonomyForm.querySelector('[name="id"]').value = row ? row.id : '';
            taxonomyForm.querySelector('[name="type"]').value = type;
            taxonomyForm.querySelector('[name="name"]').value = row ? row.name : '';
            taxonomyForm.querySelector('[name="icon"]').value = row && row.icon ? row.icon : (type === 'locations' ? '📍' : '📦');
            taxonomyForm.querySelector('[name="description"]').value = row ? (row.description || '') : '';
            taxonomyForm.querySelector('[data-taxonomy-title]').textContent = (row ? 'Modifier ' : 'Nouvelle ') + (type === 'categories' ? 'catégorie' : 'localisation');
            taxonomyForm.querySelector('[data-location-description]').hidden = type !== 'locations';
            const emojiPicker = window.MjRegMgrEmojiPicker;
            if (emojiPicker && window.preact && taxonomyEmojiPicker) {
                window.preact.render(window.preact.h(emojiPicker.EmojiPickerField, {
                    value: taxonomyIconInput.value,
                    onChange: (value) => { taxonomyIconInput.value = value; },
                    strings: { eventEmojiPicker: 'Choisir un emoji', eventEmojiPickerClose: 'Fermer' },
                }), taxonomyEmojiPicker);
            }
            taxonomyDialog.showModal();
        };
        const openDetail = async (id) => {
            try {
                const data = await request('get_item', { id });
                const item = data.item;
                const history = data.history || [];
                const photos = data.photos || [];
                const historyMarkup = history.length ? '<ol class="mj-inventory__history">' + history.map((entry) => '<li><strong>' + escapeHtml(entry.borrower_name || 'Utilisateur supprimé') + '</strong><br><small>Emprunté le ' + escapeHtml(entry.borrowed_at) + (entry.returned_at ? ' · retourné le ' + escapeHtml(entry.returned_at) : ' · en cours') + '</small></li>').join('') + '</ol>' : '<p>Aucun emprunt enregistré.</p>';
                const gallery = [item.thumbnail].concat(photos.map((photo) => photo.thumbnail)).filter(Boolean).map((thumbnail) => '<img src="' + thumbnail + '" alt="Photo de ' + escapeHtml(item.name) + '">').join('');
                detailContent.innerHTML = '<button type="button" class="mj-inventory__close" data-action="close-detail" aria-label="Fermer">×</button><h2>' + escapeHtml(item.name) + '</h2><p class="mj-inventory__detail-meta">' + escapeHtml(item.category_icon || '') + ' ' + escapeHtml(item.category_name || 'Sans catégorie') + ' · ' + escapeHtml(item.location_icon || '') + ' ' + escapeHtml(item.location_name || 'Localisation non définie') + '</p>' + (gallery ? '<div class="mj-inventory__gallery">' + gallery + '</div>' : '') + '<p><strong>Quantité :</strong> ' + escapeHtml(item.quantity) + '</p><p>' + escapeHtml(item.description || '') + '</p>' + (item.safety_note_long ? '<aside class="mj-inventory__safety"><strong>Sécurité</strong><br>' + escapeHtml(item.safety_note_long) + '</aside>' : '') + '<h3>Historique des emprunts</h3>' + historyMarkup;
                detailDialog.showModal();
            } catch (error) { notify(error.message, true); }
        };
        root.addEventListener('click', async (event) => {
            const action = event.target.closest('[data-action]');
            if (!action) return;
            if (action.dataset.action === 'close') return dialog.close();
            if (action.dataset.action === 'close-detail') return detailDialog.close();
            if (action.dataset.action === 'close-taxonomy') return taxonomyDialog.close();
            if (action.dataset.action === 'toggle-filters') {
                const isOpen = !filterPanel.hidden;
                filterPanel.hidden = isOpen;
                filterToggle.setAttribute('aria-expanded', String(!isOpen));
                return;
            }
            if (action.dataset.action === 'clear-search') {
                root.querySelector('.mj-inventory__search').value = '';
                return load();
            }
            if (action.dataset.action === 'reset-filters') {
                root.querySelector('.mj-inventory__search').value = '';
                root.querySelectorAll('[data-filter]').forEach((select) => { select.value = ''; });
                return load();
            }
            if (action.dataset.action === 'remove-filter') {
                if (action.dataset.filterKey === 'q') root.querySelector('.mj-inventory__search').value = '';
                else { const select = root.querySelector('[data-filter="' + action.dataset.filterKey + '"]'); if (select) select.value = ''; }
                return load();
            }
            if (action.dataset.action === 'delete-photo' && editingItem && window.confirm('Supprimer cette photo ?')) {
                try {
                    await request('delete_photo', { id: editingItem.id, photo_id: action.dataset.photoId });
                    await openForm(editingItem);
                    await load();
                } catch (error) { notify(error.message, true); }
                return;
            }
            if (action.dataset.action === 'new') return openForm();
            if (action.dataset.action === 'add-category') return openTaxonomyForm('categories');
            if (action.dataset.action === 'add-location') return openTaxonomyForm('locations');
            if (action.dataset.action === 'edit-taxonomy') {
                const rows = taxonomies[action.dataset.taxonomy] || [];
                const row = rows.find((entry) => String(entry.id) === action.dataset.taxonomyId);
                if (row) openTaxonomyForm(action.dataset.taxonomy, row);
                return;
            }
            if (action.dataset.action === 'delete-taxonomy' && window.confirm('Supprimer cet élément ?')) {
                try { await request(action.dataset.taxonomy === 'categories' ? 'delete_category' : 'delete_location', { id: action.dataset.taxonomyId }); await load(); } catch (error) { notify(error.message, true); }
                return;
            }
            const card = action.closest('[data-id]');
            const item = card && items.find((entry) => String(entry.id) === card.dataset.id);
            if (action.dataset.action === 'edit' && item) return openForm(item);
            if (action.dataset.action === 'delete' && item && window.confirm('Supprimer cet objet ?')) { try { await request('delete_item', { id: item.id }); await load(); } catch (error) { notify(error.message, true); } return; }
            if (action.dataset.action === 'view' && item) return openDetail(item.id);
            if (action.dataset.action === 'analyze') {
                try {
                    const data = await request('analyze_photo', {}, { photo: form.querySelector('[data-photo]').files[0] });
                    if (data.category_id && data.new_category_name) {
                        const categorySelect = form.querySelector('[data-form-category]');
                        if (categorySelect && !categorySelect.querySelector('option[value="' + data.category_id + '"]')) {
                            categorySelect.add(new Option(data.new_category_name, data.category_id));
                        }
                    }
                    Object.entries(data).forEach(([key, value]) => { const input = form.querySelector('[name="' + key + '"]'); if (input && value != null) input.value = value; });
                    notify('Analyse terminée.');
                } catch (error) { notify(error.message, true); }
            }
        });
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const data = Object.fromEntries(Array.from(new FormData(form).entries()).filter(([key]) => key !== 'photo' && key !== 'photos[]'));
            const action = data.id ? 'update_item' : 'create_item';
            try { await request(action, data, { photo: form.querySelector('[data-photo]').files[0], photos: Array.from(form.querySelector('[data-photos]').files) }); dialog.close(); await load(); } catch (error) { notify(error.message, true); }
        });
        form.querySelector('[data-photo]').addEventListener('change', (event) => renderSelectedPhotos(event.target, coverPreview));
        form.querySelector('[data-photos]').addEventListener('change', (event) => renderSelectedPhotos(event.target, photosPreview));
        taxonomyForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const data = Object.fromEntries(new FormData(taxonomyForm).entries());
            const singular = data.type === 'categories' ? 'category' : 'location';
            try { await request(data.id ? 'update_' + singular : 'create_' + singular, data); taxonomyDialog.close(); await load(); } catch (error) { notify(error.message, true); }
        });
        root.querySelectorAll('[data-filter]').forEach((input) => input.addEventListener('change', load));
        root.querySelector('.mj-inventory__search').addEventListener('input', window.MjMemberDebounce ? window.MjMemberDebounce(load, 250) : load);
        load();
    });
}());