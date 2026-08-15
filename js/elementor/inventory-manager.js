(function () {
    'use strict';

    const config = window.mjInventoryConfig || {};
    const request = async (action, data, files) => {
        const body = new FormData();
        body.append('action', 'mj_inventory_' + action);
        body.append('nonce', config.nonce || '');
        Object.entries(data || {}).forEach(([key, value]) => body.append(key, value == null ? '' : value));
        if (files) Object.entries(files).forEach(([key, value]) => { if (value) body.append(key, value); });
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
        const message = root.querySelector('.mj-inventory__message');
        const filterPanel = root.querySelector('[data-filter-panel]');
        const filterToggle = root.querySelector('[data-action="toggle-filters"]');
        const filterCount = root.querySelector('[data-filter-count]');
        const activeFilters = root.querySelector('[data-active-filters]');
        let items = [];
        let taxonomies = { categories: [], locations: [] };

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
            grid.innerHTML = items.length ? items.map((item) => '<article class="mj-inventory__card" data-id="' + item.id + '">' + (item.thumbnail ? '<img src="' + item.thumbnail + '" alt="">' : '<div class="mj-inventory__placeholder">MJ</div>') + '<div class="mj-inventory__card-body"><h3>' + escapeHtml(item.name) + '</h3><span class="mj-inventory__status mj-inventory__status--' + escapeHtml(item.status) + '">' + escapeHtml({ good: 'Bon état', damaged: 'Abîmé', broken: 'Cassé' }[item.status]) + '</span>' + (item.borrowed_by ? '<span class="mj-inventory__borrowed">Emprunté</span>' : '') + '<p>' + escapeHtml(item.location_name || 'Localisation non définie') + '</p><button type="button" data-action="view">Voir</button> <button type="button" data-action="edit">Modifier</button> <button type="button" data-action="delete">Supprimer</button></div></article>').join('') : '<p class="mj-inventory__empty">Aucun objet trouvé.</p>';
            root.querySelector('[data-taxonomies]').innerHTML = ['categories', 'locations'].map((type) => '<div><strong>' + (type === 'categories' ? 'Catégories' : 'Localisations') + '</strong> ' + taxonomies[type].map((row) => '<span class="mj-inventory__taxonomy">' + escapeHtml(row.name) + ' <button type="button" data-action="delete-taxonomy" data-taxonomy="' + type + '" data-taxonomy-id="' + row.id + '" aria-label="Supprimer ' + escapeHtml(row.name) + '">×</button></span>').join('') + '</div>').join('');
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
        const openForm = (item) => {
            form.reset();
            form.querySelector('[name="id"]').value = item ? item.id : '';
            form.querySelector('[data-form-title]').textContent = item ? 'Modifier l’objet' : 'Nouvel objet';
            if (item) Object.keys(item).forEach((key) => { const input = form.querySelector('[name="' + key + '"]'); if (input && item[key] != null) input.value = item[key]; });
            dialog.showModal();
        };
        root.addEventListener('click', async (event) => {
            const action = event.target.closest('[data-action]');
            if (!action) return;
            if (action.dataset.action === 'close') return dialog.close();
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
            if (action.dataset.action === 'new') return openForm();
            if (action.dataset.action === 'add-category' || action.dataset.action === 'add-location') {
                const name = window.prompt('Nom :');
                if (name) { try { await request(action.dataset.action === 'add-category' ? 'create_category' : 'create_location', { name }); await load(); } catch (error) { notify(error.message, true); } }
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
            if (action.dataset.action === 'view' && item) {
                const detail = item.safety_note_long ? '\n\nSécurité: ' + item.safety_note_long : '';
                if (confirm(item.name + '\n' + (item.description || '') + detail + '\n\n' + (item.borrowed_by ? 'Retourner cet objet ?' : 'Emprunter cet objet ?'))) {
                    try { await request(item.borrowed_by ? 'return_item' : 'borrow_item', { id: item.id }); await load(); } catch (error) { notify(error.message, true); }
                }
            }
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
            const data = Object.fromEntries(new FormData(form).entries());
            const action = data.id ? 'update_item' : 'create_item';
            try { await request(action, data, { photo: form.querySelector('[data-photo]').files[0] }); dialog.close(); await load(); } catch (error) { notify(error.message, true); }
        });
        root.querySelectorAll('[data-filter]').forEach((input) => input.addEventListener('change', load));
        root.querySelector('.mj-inventory__search').addEventListener('input', window.MjMemberDebounce ? window.MjMemberDebounce(load, 250) : load);
        load();
    });
}());