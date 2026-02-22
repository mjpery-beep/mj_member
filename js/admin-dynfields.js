/**
 * Dynamic Fields admin configuration UI.
 * Loaded on the MJ settings page, dynfields tab.
 */
(function ($) {
    'use strict';

    if (typeof window.mjDynfieldsConfig === 'undefined') {
        return;
    }

    var config = window.mjDynfieldsConfig;
    var ajaxurl = config.ajaxurl || window.ajaxurl;
    var nonce = config.nonce || '';
    var container = document.getElementById('mj-dynfields-app');
    if (!container) return;

    var typeLabels = {
        text: 'Texte',
        textarea: 'Zone de texte',
        dropdown: 'Liste déroulante',
        radio: 'Boutons radio',
        checkbox: 'Case à cocher',
        checklist: 'Liste de cases à cocher',
        title: 'Titre de section'
    };

    var fields = [];
    var editingId = null;

    function esc(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str || ''));
        return div.innerHTML;
    }

    function render() {
        var html = '';
        html += '<div class="mj-dynfields">';
        html += '<div class="mj-dynfields__toolbar">';
        html += '<button type="button" class="button button-primary" id="mj-dynfields-add">+ Ajouter un champ</button>';
        html += '</div>';

        if (fields.length === 0) {
            html += '<div class="mj-dynfields__empty">';
            html += '<p style="color:#6b7280; font-style: italic;">Aucun champ dynamique configuré.</p>';
            html += '</div>';
        } else {
            html += '<div class="mj-dynfields__list" id="mj-dynfields-list">';
            for (var i = 0; i < fields.length; i++) {
                html += renderFieldRow(fields[i], i);
            }
            html += '</div>';
        }

        // Modal / form
        html += '<div id="mj-dynfields-modal" class="mj-dynfields-modal" style="display:none;">';
        html += '<div class="mj-dynfields-modal__backdrop"></div>';
        html += '<div class="mj-dynfields-modal__content">';
        html += '<div class="mj-dynfields-modal__header">';
        html += '<h3 id="mj-dynfields-modal-title">Ajouter un champ</h3>';
        html += '<button type="button" class="mj-dynfields-modal__close" id="mj-dynfields-modal-close">&times;</button>';
        html += '</div>';
        html += '<div class="mj-dynfields-modal__body">';
        html += renderForm();
        html += '</div>';
        html += '<div class="mj-dynfields-modal__footer">';
        html += '<button type="button" class="button" id="mj-dynfields-cancel">Annuler</button>';
        html += '<button type="button" class="button button-primary" id="mj-dynfields-save">Enregistrer</button>';
        html += '</div>';
        html += '</div>';
        html += '</div>';

        html += '</div>';

        container.innerHTML = html;
        bindEvents();
    }

    function renderFieldRow(field, index) {
        var isTitle = field.fieldType === 'title';
        var badges = [];
        if (!isTitle) {
            if (field.showInRegistration) badges.push('<span class="mj-dynfields__badge mj-dynfields__badge--registration">Inscription</span>');
            if (field.showInAccount) badges.push('<span class="mj-dynfields__badge mj-dynfields__badge--account">Mon compte</span>');
            if (field.isRequired) badges.push('<span class="mj-dynfields__badge mj-dynfields__badge--required">Requis</span>');
            if (field.allowOther) badges.push('<span class="mj-dynfields__badge mj-dynfields__badge--other">« Autre »</span>');
        }

        var typeLabel = typeLabels[field.fieldType] || field.fieldType;

        var itemClass = 'mj-dynfields__item' + (isTitle ? ' mj-dynfields__item--title' : '');

        var html = '';
        html += '<div class="' + itemClass + '" data-field-id="' + field.id + '" data-index="' + index + '">';
        html += '<div class="mj-dynfields__item-handle" title="Glisser pour réordonner">☰</div>';
        html += '<div class="mj-dynfields__item-info">';
        html += '<div class="mj-dynfields__item-title">' + (isTitle ? '§ ' : '') + esc(field.title) + '</div>';
        html += '<div class="mj-dynfields__item-meta">';
        html += '<span class="mj-dynfields__type-badge' + (isTitle ? ' mj-dynfields__type-badge--title' : '') + '">' + esc(typeLabel) + '</span>';
        if (field.description && !isTitle) {
            html += '<span class="mj-dynfields__item-desc">' + esc(field.description) + '</span>';
        }
        html += '</div>';
        if (badges.length) {
            html += '<div class="mj-dynfields__item-badges">' + badges.join(' ') + '</div>';
        }
        html += '</div>';
        html += '<div class="mj-dynfields__item-actions">';
        html += '<button type="button" class="button button-small mj-dynfields-edit" data-id="' + field.id + '">Modifier</button>';
        html += '<button type="button" class="button button-small button-link-delete mj-dynfields-delete" data-id="' + field.id + '">Supprimer</button>';
        html += '</div>';
        html += '</div>';
        return html;
    }

    function renderForm() {
        var html = '';
        html += '<div class="mj-dynfields-form">';

        html += '<div class="mj-dynfields-form__row">';
        html += '<label for="mj-dynfield-title">Titre *</label>';
        html += '<input type="text" id="mj-dynfield-title" class="regular-text" placeholder="Ex: Allergies connues" />';
        html += '</div>';

        html += '<div class="mj-dynfields-form__row">';
        html += '<label for="mj-dynfield-description">Description</label>';
        html += '<input type="text" id="mj-dynfield-description" class="regular-text" placeholder="Texte d\'aide affiché sous le champ" />';
        html += '</div>';

        html += '<div class="mj-dynfields-form__row">';
        html += '<label for="mj-dynfield-type">Type de champ</label>';
        html += '<select id="mj-dynfield-type">';
        html += '<option value="text">Texte</option>';
        html += '<option value="textarea">Zone de texte</option>';
        html += '<option value="dropdown">Liste déroulante</option>';
        html += '<option value="radio">Boutons radio</option>';
        html += '<option value="checkbox">Case à cocher</option>';
        html += '<option value="checklist">Liste de cases à cocher</option>';
        html += '<option value="title">Titre de section</option>';
        html += '</select>';
        html += '</div>';

        html += '<div class="mj-dynfields-form__row mj-dynfields-form__row--options" id="mj-dynfield-options-row" style="display:none;">';
        html += '<label for="mj-dynfield-options">Liste de valeurs <small>(une par ligne)</small></label>';
        html += '<textarea id="mj-dynfield-options" rows="4" placeholder="Valeur 1&#10;Valeur 2&#10;Valeur 3"></textarea>';
        html += '</div>';

        html += '<div class="mj-dynfields-form__checkboxes">';
        html += '<label class="mj-dynfields-form__checkbox"><input type="checkbox" id="mj-dynfield-registration" /> Afficher dans le formulaire d\'inscription</label>';
        html += '<label class="mj-dynfields-form__checkbox"><input type="checkbox" id="mj-dynfield-account" /> Afficher dans Mes informations</label>';
        html += '<label class="mj-dynfields-form__checkbox"><input type="checkbox" id="mj-dynfield-required" /> Champ obligatoire</label>';
        html += '<label class="mj-dynfields-form__checkbox" id="mj-dynfield-allow-other-row" style="display:none;"><input type="checkbox" id="mj-dynfield-allow-other" /> Proposer une option \u00ab Autre \u00bb (champ texte libre)</label>';
        html += '</div>';

        html += '</div>';
        return html;
    }

    function bindEvents() {
        var addBtn = document.getElementById('mj-dynfields-add');
        if (addBtn) addBtn.addEventListener('click', openAddModal);

        var saveBtn = document.getElementById('mj-dynfields-save');
        if (saveBtn) saveBtn.addEventListener('click', saveField);

        var cancelBtn = document.getElementById('mj-dynfields-cancel');
        if (cancelBtn) cancelBtn.addEventListener('click', closeModal);

        var closeBtn = document.getElementById('mj-dynfields-modal-close');
        if (closeBtn) closeBtn.addEventListener('click', closeModal);

        var backdrop = container.querySelector('.mj-dynfields-modal__backdrop');
        if (backdrop) backdrop.addEventListener('click', closeModal);

        var typeSelect = document.getElementById('mj-dynfield-type');
        if (typeSelect) typeSelect.addEventListener('change', toggleOptionsRow);

        // Edit/Delete buttons
        var editBtns = container.querySelectorAll('.mj-dynfields-edit');
        for (var e = 0; e < editBtns.length; e++) {
            editBtns[e].addEventListener('click', function () {
                openEditModal(parseInt(this.getAttribute('data-id'), 10));
            });
        }

        var deleteBtns = container.querySelectorAll('.mj-dynfields-delete');
        for (var d = 0; d < deleteBtns.length; d++) {
            deleteBtns[d].addEventListener('click', function () {
                deleteField(parseInt(this.getAttribute('data-id'), 10));
            });
        }

        // Drag & drop re-ordering
        initSortable();
    }

    function toggleOptionsRow() {
        var type = document.getElementById('mj-dynfield-type').value;
        var isTitle = type === 'title';
        var hasOptions = type === 'dropdown' || type === 'radio' || type === 'checklist';
        var row = document.getElementById('mj-dynfield-options-row');
        if (row) {
            row.style.display = hasOptions ? '' : 'none';
        }
        // Hide 'required' checkbox for title type
        var reqCheckbox = document.getElementById('mj-dynfield-required');
        if (reqCheckbox && reqCheckbox.closest) {
            reqCheckbox.closest('.mj-dynfields-form__checkbox').style.display = isTitle ? 'none' : '';
            if (isTitle) reqCheckbox.checked = false;
        }
        // Show/hide allow-other checkbox
        var allowOtherRow = document.getElementById('mj-dynfield-allow-other-row');
        if (allowOtherRow) {
            allowOtherRow.style.display = hasOptions ? '' : 'none';
            if (!hasOptions) document.getElementById('mj-dynfield-allow-other').checked = false;
        }
    }

    function openAddModal() {
        editingId = null;
        var titleEl = document.getElementById('mj-dynfields-modal-title');
        if (titleEl) titleEl.textContent = 'Ajouter un champ';
        resetForm();
        showModal();
    }

    function openEditModal(id) {
        var field = findField(id);
        if (!field) return;

        editingId = id;
        var titleEl = document.getElementById('mj-dynfields-modal-title');
        if (titleEl) titleEl.textContent = 'Modifier le champ';

        document.getElementById('mj-dynfield-title').value = field.title || '';
        document.getElementById('mj-dynfield-description').value = field.description || '';
        document.getElementById('mj-dynfield-type').value = field.fieldType || 'text';
        document.getElementById('mj-dynfield-options').value = (field.optionsList || []).join('\n');
        document.getElementById('mj-dynfield-registration').checked = !!field.showInRegistration;
        document.getElementById('mj-dynfield-account').checked = !!field.showInAccount;
        document.getElementById('mj-dynfield-required').checked = !!field.isRequired;
        document.getElementById('mj-dynfield-allow-other').checked = !!field.allowOther;

        toggleOptionsRow();
        showModal();
    }

    function resetForm() {
        document.getElementById('mj-dynfield-title').value = '';
        document.getElementById('mj-dynfield-description').value = '';
        document.getElementById('mj-dynfield-type').value = 'text';
        document.getElementById('mj-dynfield-options').value = '';
        document.getElementById('mj-dynfield-registration').checked = false;
        document.getElementById('mj-dynfield-account').checked = false;
        document.getElementById('mj-dynfield-required').checked = false;
        document.getElementById('mj-dynfield-allow-other').checked = false;
        toggleOptionsRow();
    }

    function showModal() {
        var modal = document.getElementById('mj-dynfields-modal');
        if (modal) modal.style.display = 'flex';
    }

    function closeModal() {
        var modal = document.getElementById('mj-dynfields-modal');
        if (modal) modal.style.display = 'none';
        editingId = null;
    }

    function findField(id) {
        for (var i = 0; i < fields.length; i++) {
            if (fields[i].id === id) return fields[i];
        }
        return null;
    }

    function saveField() {
        var title = document.getElementById('mj-dynfield-title').value.trim();
        if (!title) {
            alert('Le titre est obligatoire.');
            return;
        }

        var type = document.getElementById('mj-dynfield-type').value;
        var optionsRaw = document.getElementById('mj-dynfield-options').value.trim();
        var optionsList = optionsRaw ? optionsRaw.split('\n').map(function (v) { return v.trim(); }).filter(Boolean) : [];

        var payload = {
            _nonce: nonce,
            title: title,
            description: document.getElementById('mj-dynfield-description').value.trim(),
            field_type: type,
            show_in_registration: document.getElementById('mj-dynfield-registration').checked ? 1 : 0,
            show_in_account: document.getElementById('mj-dynfield-account').checked ? 1 : 0,
            is_required: type === 'title' ? 0 : (document.getElementById('mj-dynfield-required').checked ? 1 : 0),
            allow_other: document.getElementById('mj-dynfield-allow-other').checked ? 1 : 0,
            options_list: JSON.stringify(optionsList)
        };

        var action = 'mj_dynfields_create';
        if (editingId) {
            action = 'mj_dynfields_update';
            payload.field_id = editingId;
        }

        payload.action = action;

        var saveBtn = document.getElementById('mj-dynfields-save');
        if (saveBtn) saveBtn.disabled = true;

        $.post(ajaxurl, payload, function (resp) {
            if (saveBtn) saveBtn.disabled = false;
            if (resp.success) {
                closeModal();
                loadFields();
            } else {
                alert(resp.data && resp.data.message ? resp.data.message : 'Erreur.');
            }
        }).fail(function () {
            if (saveBtn) saveBtn.disabled = false;
            alert('Erreur de connexion.');
        });
    }

    function deleteField(id) {
        var field = findField(id);
        var name = field ? field.title : '#' + id;
        if (!confirm('Supprimer le champ « ' + name + ' » et toutes ses valeurs ?')) return;

        $.post(ajaxurl, {
            action: 'mj_dynfields_delete',
            _nonce: nonce,
            field_id: id
        }, function (resp) {
            if (resp.success) {
                loadFields();
            } else {
                alert(resp.data && resp.data.message ? resp.data.message : 'Erreur.');
            }
        });
    }

    function loadFields() {
        $.post(ajaxurl, {
            action: 'mj_dynfields_list',
            _nonce: nonce
        }, function (resp) {
            if (resp.success && resp.data && resp.data.fields) {
                fields = resp.data.fields;
            } else {
                fields = [];
            }
            render();
        }).fail(function () {
            container.innerHTML = '<p style="color:red;">Erreur de chargement des champs.</p>';
        });
    }

    function initSortable() {
        var list = document.getElementById('mj-dynfields-list');
        if (!list || !list.children.length) return;

        var dragItem = null;
        var items = list.querySelectorAll('.mj-dynfields__item');
        for (var i = 0; i < items.length; i++) {
            var handle = items[i].querySelector('.mj-dynfields__item-handle');
            if (!handle) continue;

            (function (item) {
                handle.addEventListener('mousedown', function (e) {
                    e.preventDefault();
                    dragItem = item;
                    item.classList.add('mj-dynfields__item--dragging');

                    var onMouseMove = function (e2) {
                        e2.preventDefault();
                        var afterElem = getDragAfterElement(list, e2.clientY);
                        if (afterElem == null) {
                            list.appendChild(item);
                        } else {
                            list.insertBefore(item, afterElem);
                        }
                    };

                    var onMouseUp = function () {
                        item.classList.remove('mj-dynfields__item--dragging');
                        document.removeEventListener('mousemove', onMouseMove);
                        document.removeEventListener('mouseup', onMouseUp);
                        saveSortOrder();
                    };

                    document.addEventListener('mousemove', onMouseMove);
                    document.addEventListener('mouseup', onMouseUp);
                });
            })(items[i]);
        }
    }

    function getDragAfterElement(container, y) {
        var elements = Array.prototype.slice.call(container.querySelectorAll('.mj-dynfields__item:not(.mj-dynfields__item--dragging)'));
        var closest = null;
        var closestOffset = Number.NEGATIVE_INFINITY;

        elements.forEach(function (el) {
            var box = el.getBoundingClientRect();
            var offset = y - box.top - box.height / 2;
            if (offset < 0 && offset > closestOffset) {
                closestOffset = offset;
                closest = el;
            }
        });

        return closest;
    }

    function saveSortOrder() {
        var items = document.querySelectorAll('#mj-dynfields-list .mj-dynfields__item');
        var order = [];
        for (var i = 0; i < items.length; i++) {
            order.push(parseInt(items[i].getAttribute('data-field-id'), 10));
        }

        $.post(ajaxurl, {
            action: 'mj_dynfields_reorder',
            _nonce: nonce,
            order: order
        });
    }

    // Init
    loadFields();
})(jQuery);
