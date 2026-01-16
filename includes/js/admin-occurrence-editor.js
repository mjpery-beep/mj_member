(function () {
    'use strict';

    function safeParseJSON(value, fallback) {
        if (typeof value !== 'string' || value === '') {
            return fallback;
        }
        try {
            var parsed = JSON.parse(value);
            return typeof parsed === 'undefined' ? fallback : parsed;
        } catch (error) {
            return fallback;
        }
    }

    function sanitizeDateTime(value) {
        if (typeof value !== 'string') {
            return '';
        }
        var trimmed = value.trim();
        if (trimmed === '') {
            return '';
        }
        if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(trimmed)) {
            return trimmed.replace('T', ' ') + ':00';
        }
        if (/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/.test(trimmed)) {
            return trimmed.length === 16 ? trimmed + ':00' : trimmed;
        }
        return '';
    }

    function toDateTimeLocal(value) {
        var sanitized = sanitizeDateTime(value);
        if (!sanitized) {
            return '';
        }
        return sanitized.substring(0, 16).replace(' ', 'T');
    }

    function fromDateTimeLocal(value) {
        if (typeof value !== 'string') {
            return '';
        }
        var trimmed = value.trim();
        if (trimmed === '') {
            return '';
        }
        if (!/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(trimmed)) {
            return '';
        }
        return trimmed.replace('T', ' ') + ':00';
    }

    function formatDateTimeDisplay(value) {
        var sanitized = sanitizeDateTime(value);
        if (!sanitized) {
            return value || '';
        }
        var date = new Date(sanitized.replace(' ', 'T'));
        if (isNaN(date.getTime())) {
            return sanitized;
        }
        var dateOptions = { year: 'numeric', month: '2-digit', day: '2-digit' };
        var timeOptions = { hour: '2-digit', minute: '2-digit' };
        return date.toLocaleDateString(undefined, dateOptions) + ' - ' + date.toLocaleTimeString(undefined, timeOptions);
    }

    function buildLabel(start, end) {
        var startDisplay = formatDateTimeDisplay(start);
        var sanitizedStart = sanitizeDateTime(start);
        var sanitizedEnd = sanitizeDateTime(end);
        if (!sanitizedEnd) {
            return startDisplay;
        }
        var startDate = new Date(sanitizedStart.replace(' ', 'T'));
        var endDate = new Date(sanitizedEnd.replace(' ', 'T'));
        if (isNaN(startDate.getTime()) || isNaN(endDate.getTime())) {
            return startDisplay + ' → ' + formatDateTimeDisplay(end);
        }
        var timeOptions = { hour: '2-digit', minute: '2-digit' };
        if (startDate.toDateString() === endDate.toDateString()) {
            return startDisplay + ' → ' + endDate.toLocaleTimeString(undefined, timeOptions);
        }
        var dateOptions = { year: 'numeric', month: '2-digit', day: '2-digit' };
        return startDisplay + ' → ' + endDate.toLocaleDateString(undefined, dateOptions);
    }

    function safeClone(item) {
        try {
            return JSON.parse(JSON.stringify(item));
        } catch (error) {
            var clone = {};
            if (item && typeof item === 'object') {
                Object.keys(item).forEach(function (key) {
                    clone[key] = item[key];
                });
            }
            return clone;
        }
    }

    function ready(handler) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', handler);
        } else {
            handler();
        }
    }

    ready(initOccurrenceEditor);

    function initOccurrenceEditor() {
        var editor = document.getElementById('mj-event-occurrence-editor');
        if (!editor) {
            return;
        }

        var payloadInput = document.getElementById('mj-event-occurrences-payload');
        if (!payloadInput) {
            return;
        }

        var listContainer = editor.querySelector('.mj-occurrence-editor__list');
        if (!listContainer) {
            return;
        }

        var alertsContainer = editor.querySelector('.mj-occurrence-editor__alerts');
        var boundaryStartInput = document.getElementById('mj-event-date-start');
        var boundaryEndInput = document.getElementById('mj-event-date-end');
        var addButton = editor.querySelector('[data-action="occurrence-add"]');
        var duplicateButton = editor.querySelector('[data-action="occurrence-duplicate"]');

        var strings = (window.mjAdminEvents && mjAdminEvents.occurrenceEditor) ? mjAdminEvents.occurrenceEditor : {};
        var config = safeParseJSON(editor.getAttribute('data-config') || '{}', {});
        var statusLabels = (config && typeof config.statusLabels === 'object') ? config.statusLabels : {};
        var defaultStatus = (typeof config.defaultStatus === 'string' && config.defaultStatus !== '') ? config.defaultStatus : (Object.keys(statusLabels)[0] || 'active');
        if (!statusLabels[defaultStatus]) {
            statusLabels[defaultStatus] = defaultStatus;
        }
        var nowAttr = parseInt(editor.getAttribute('data-now'), 10);
        var nowMs = !isNaN(nowAttr) ? nowAttr * 1000 : Date.now();

        function sanitizeStatus(value) {
            var candidate = (typeof value === 'string' ? value : '').toLowerCase().replace(/[^a-z0-9_\-]/g, '');
            if (candidate && Object.prototype.hasOwnProperty.call(statusLabels, candidate)) {
                return candidate;
            }
            return defaultStatus;
        }

        var state = { items: [] };
        var modal = null;
        var modalForm = null;
        var modalTitle = null;
        var modalError = null;
        var modalStartField = null;
        var modalEndField = null;
        var modalStatusField = null;
        var lastTrigger = null;

        function normalizeItem(raw) {
            if (!raw || typeof raw !== 'object') {
                return null;
            }
            var start = sanitizeDateTime(raw.start || (raw.startIso ? raw.startIso.replace('T', ' ') : ''));
            var end = sanitizeDateTime(raw.end || (raw.endIso ? raw.endIso.replace('T', ' ') : ''));
            if (!start || !end) {
                return null;
            }
            var status = sanitizeStatus(raw.status);
            var source = (typeof raw.source === 'string' && raw.source !== '') ? raw.source : 'manual';
            var meta = null;
            if (raw.meta && typeof raw.meta === 'string') {
                meta = safeParseJSON(raw.meta, null);
            } else if (raw.meta && typeof raw.meta === 'object') {
                meta = raw.meta;
            }
            var id = parseInt(raw.id, 10) || 0;
            var eventId = parseInt(raw.eventId, 10) || 0;
            var startTimestamp = Date.parse(start.replace(' ', 'T'));
            var endTimestamp = Date.parse(end.replace(' ', 'T'));
            return {
                id: id,
                eventId: eventId,
                start: start,
                end: end,
                startIso: start.replace(' ', 'T'),
                endIso: end.replace(' ', 'T'),
                status: status,
                source: source,
                meta: meta,
                label: (typeof raw.label === 'string' && raw.label !== '') ? raw.label : buildLabel(start, end),
                isPast: isFinite(startTimestamp) ? startTimestamp < nowMs : false,
                timestamp: isFinite(startTimestamp) ? startTimestamp : null,
                duration: (isFinite(startTimestamp) && isFinite(endTimestamp)) ? Math.max(0, endTimestamp - startTimestamp) : null
            };
        }

        function serializeItems() {
            return state.items.map(function (item) {
                return {
                    id: item.id,
                    eventId: item.eventId,
                    start: item.start,
                    end: item.end,
                    startIso: item.startIso,
                    endIso: item.endIso,
                    status: item.status,
                    source: item.source,
                    meta: item.meta,
                    label: item.label,
                    isPast: !!item.isPast
                };
            });
        }

        function sortItems() {
            state.items.sort(function (left, right) {
                if (left.start < right.start) {
                    return -1;
                }
                if (left.start > right.start) {
                    return 1;
                }
                return (left.id || 0) - (right.id || 0);
            });
        }

        function clearAlert() {
            if (alertsContainer) {
                alertsContainer.innerHTML = '';
            }
        }

        function showAlert(message, type) {
            if (!alertsContainer) {
                return;
            }
            alertsContainer.innerHTML = '';
            if (!message) {
                return;
            }
            var notice = document.createElement('div');
            notice.className = 'notice ' + (type === 'error' ? 'notice-error' : 'notice-info');
            notice.textContent = message;
            alertsContainer.appendChild(notice);
        }

        function renderTable() {
            listContainer.innerHTML = '';
            if (!state.items.length) {
                var empty = document.createElement('p');
                empty.className = 'mj-occurrence-editor__empty';
                empty.textContent = strings.emptyList || 'No occurrences are scheduled yet. Add one to initialise the planning.';
                listContainer.appendChild(empty);
                return;
            }

            var table = document.createElement('table');
            table.className = 'widefat fixed striped mj-occurrence-editor__table';

            var thead = document.createElement('thead');
            var headerRow = document.createElement('tr');
            var startHeader = document.createElement('th');
            startHeader.scope = 'col';
            startHeader.textContent = strings.startLabel || 'Start';
            headerRow.appendChild(startHeader);
            var endHeader = document.createElement('th');
            endHeader.scope = 'col';
            endHeader.textContent = strings.endLabel || 'End';
            headerRow.appendChild(endHeader);
            var statusHeader = document.createElement('th');
            statusHeader.scope = 'col';
            statusHeader.textContent = strings.statusLabel || 'Status';
            headerRow.appendChild(statusHeader);
            var actionsHeader = document.createElement('th');
            actionsHeader.scope = 'col';
            actionsHeader.className = 'mj-occurrence-editor__column--actions';
            actionsHeader.textContent = strings.actionsLabel || 'Actions';
            headerRow.appendChild(actionsHeader);
            thead.appendChild(headerRow);
            table.appendChild(thead);

            var tbody = document.createElement('tbody');
            state.items.forEach(function (item, index) {
                var row = document.createElement('tr');
                row.setAttribute('data-index', String(index));
                row.setAttribute('data-occurrence-id', String(item.id || 0));
                row.setAttribute('data-occurrence-status', item.status);
                row.setAttribute('data-occurrence-start', item.start);
                row.setAttribute('data-occurrence-end', item.end);
                if (item.isPast) {
                    row.classList.add('is-past');
                }

                var startCell = document.createElement('td');
                startCell.setAttribute('data-column', 'start');
                startCell.textContent = formatDateTimeDisplay(item.start);
                row.appendChild(startCell);

                var endCell = document.createElement('td');
                endCell.setAttribute('data-column', 'end');
                endCell.textContent = formatDateTimeDisplay(item.end);
                row.appendChild(endCell);

                var statusCell = document.createElement('td');
                statusCell.setAttribute('data-column', 'status');
                statusCell.textContent = statusLabels[item.status] || item.status;
                row.appendChild(statusCell);

                var actionsCell = document.createElement('td');
                actionsCell.className = 'mj-occurrence-editor__actions-cell';

                var editButton = document.createElement('button');
                editButton.type = 'button';
                editButton.className = 'button button-link mj-occurrence-editor__edit';
                editButton.setAttribute('data-action', 'occurrence-edit');
                editButton.textContent = strings.editLabel || 'Edit';
                actionsCell.appendChild(editButton);

                var removeButton = document.createElement('button');
                removeButton.type = 'button';
                removeButton.className = 'button button-link-delete mj-occurrence-editor__remove';
                removeButton.setAttribute('data-action', 'occurrence-remove');
                removeButton.textContent = strings.removeLabel || 'Remove';
                actionsCell.appendChild(removeButton);

                row.appendChild(actionsCell);
                tbody.appendChild(row);
            });

            table.appendChild(tbody);
            listContainer.appendChild(table);
        }

        function updateBoundaryFields() {
            if (!boundaryStartInput || !boundaryEndInput) {
                return;
            }
            if (!state.items.length) {
                boundaryStartInput.value = '';
                boundaryEndInput.value = '';
                return;
            }
            var first = state.items[0];
            var last = state.items[state.items.length - 1];
            boundaryStartInput.value = first.start;
            boundaryEndInput.value = last.end;
        }

        function sync() {
            sortItems();
            renderTable();
            payloadInput.value = JSON.stringify(serializeItems());
            updateBoundaryFields();
        }

        function ensureModal() {
            if (modal) {
                return modal;
            }

            modal = document.createElement('div');
            modal.className = 'mj-occurrence-editor__modal';
            modal.setAttribute('aria-hidden', 'true');
            modal.innerHTML = '' +
                '<div class="mj-occurrence-editor__modal-backdrop" tabindex="-1"></div>' +
                '<div class="mj-occurrence-editor__modal-dialog" role="dialog" aria-modal="true" aria-labelledby="mj-occurrence-editor-title">' +
                    '<form class="mj-occurrence-editor__form">' +
                        '<h3 id="mj-occurrence-editor-title" class="mj-occurrence-editor__modal-title"></h3>' +
                        '<div class="mj-occurrence-editor__modal-body">' +
                            '<label class="mj-occurrence-editor__field">' +
                                '<span>' + (strings.startLabel || 'Start') + '</span>' +
                                '<input type="datetime-local" name="occurrence-start" required />' +
                            '</label>' +
                            '<label class="mj-occurrence-editor__field">' +
                                '<span>' + (strings.endLabel || 'End') + '</span>' +
                                '<input type="datetime-local" name="occurrence-end" required />' +
                            '</label>' +
                            '<label class="mj-occurrence-editor__field">' +
                                '<span>' + (strings.statusLabel || 'Status') + '</span>' +
                                '<select name="occurrence-status"></select>' +
                            '</label>' +
                            '<div class="mj-occurrence-editor__modal-error" aria-live="assertive"></div>' +
                        '</div>' +
                        '<div class="mj-occurrence-editor__modal-actions">' +
                            '<button type="submit" class="button button-primary">' + (strings.saveLabel || 'Save') + '</button>' +
                            '<button type="button" class="button mj-occurrence-editor__cancel" data-action="cancel">' + (strings.cancelLabel || 'Cancel') + '</button>' +
                        '</div>' +
                    '</form>' +
                '</div>';

            editor.appendChild(modal);

            modalForm = modal.querySelector('form');
            modalTitle = modal.querySelector('.mj-occurrence-editor__modal-title');
            modalError = modal.querySelector('.mj-occurrence-editor__modal-error');
            modalStartField = modalForm.querySelector('input[name="occurrence-start"]');
            modalEndField = modalForm.querySelector('input[name="occurrence-end"]');
            modalStatusField = modalForm.querySelector('select[name="occurrence-status"]');

            if (modalStatusField && !modalStatusField.options.length) {
                Object.keys(statusLabels).forEach(function (statusKey) {
                    var option = document.createElement('option');
                    option.value = statusKey;
                    option.textContent = statusLabels[statusKey];
                    modalStatusField.appendChild(option);
                });
            }
            if (modalStatusField && modalStatusField.options.length) {
                modalStatusField.value = sanitizeStatus(modalStatusField.value);
            }

            modalForm.addEventListener('submit', function (event) {
                event.preventDefault();
                handleModalSubmit();
            });

            modal.querySelector('[data-action="cancel"]').addEventListener('click', function (event) {
                event.preventDefault();
                closeModal();
            });

            modal.querySelector('.mj-occurrence-editor__modal-backdrop').addEventListener('click', function (event) {
                event.preventDefault();
                closeModal();
            });

            modal.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    event.preventDefault();
                    closeModal();
                }
            });

            return modal;
        }

        function setFormError(message) {
            if (!modalError) {
                return;
            }
            modalError.textContent = message || '';
            if (message) {
                modalError.classList.add('is-visible');
            } else {
                modalError.classList.remove('is-visible');
            }
        }

        function clearFormError() {
            setFormError('');
        }

        function openForm(mode, data, index) {
            var dialog = ensureModal();
            clearFormError();

            dialog.setAttribute('data-mode', mode || 'create');
            dialog.setAttribute('data-index', typeof index === 'number' ? String(index) : '');

            var metaValue = '';
            if (data && data.meta) {
                try {
                    metaValue = JSON.stringify(data.meta);
                } catch (error) {
                    metaValue = '';
                }
            }

            dialog.setAttribute('data-meta', metaValue);
            dialog.setAttribute('data-id', (data && typeof data.id === 'number') ? String(data.id) : '0');
            dialog.setAttribute('data-event-id', (data && typeof data.eventId === 'number') ? String(data.eventId) : '0');
            dialog.setAttribute('data-source', (data && typeof data.source === 'string') ? data.source : 'manual');

            modalTitle.textContent = mode === 'edit'
                ? (strings.modalTitleEdit || 'Edit occurrence')
                : (strings.modalTitleCreate || 'New occurrence');

            modalStartField.value = (data && data.start) ? toDateTimeLocal(data.start) : '';
            modalEndField.value = (data && data.end) ? toDateTimeLocal(data.end) : '';
            if (modalStatusField) {
                modalStatusField.value = sanitizeStatus(data && data.status ? data.status : defaultStatus);
            }

            dialog.classList.add('is-open');
            dialog.setAttribute('aria-hidden', 'false');
            if (modalStartField && typeof modalStartField.focus === 'function') {
                modalStartField.focus({ preventScroll: true });
            }
        }

        function closeModal() {
            if (!modal) {
                return;
            }
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            modal.removeAttribute('data-mode');
            modal.removeAttribute('data-index');
            modal.removeAttribute('data-meta');
            modal.removeAttribute('data-id');
            modal.removeAttribute('data-event-id');
            modal.removeAttribute('data-source');
            if (lastTrigger && typeof lastTrigger.focus === 'function') {
                lastTrigger.focus({ preventScroll: true });
            }
        }

        function handleModalSubmit() {
            if (!modal) {
                return;
            }
            var mode = modal.getAttribute('data-mode') || 'create';
            var indexAttr = modal.getAttribute('data-index');
            var index = indexAttr !== '' ? parseInt(indexAttr, 10) : -1;

            var startValue = fromDateTimeLocal(modalStartField.value);
            var endValue = fromDateTimeLocal(modalEndField.value);

            if (!startValue || !endValue) {
                setFormError(strings.validationRequired || 'Please provide both start and end dates.');
                return;
            }

            var startDate = new Date(startValue.replace(' ', 'T'));
            var endDate = new Date(endValue.replace(' ', 'T'));

            if (!isFinite(startDate.getTime()) || !isFinite(endDate.getTime()) || endDate <= startDate) {
                setFormError(strings.validationOrder || 'End time must be after the start time.');
                return;
            }

            var base = {
                id: parseInt(modal.getAttribute('data-id'), 10) || 0,
                eventId: parseInt(modal.getAttribute('data-event-id'), 10) || 0,
                source: modal.getAttribute('data-source') || 'manual',
                meta: safeParseJSON(modal.getAttribute('data-meta') || '', null),
                start: startValue,
                end: endValue,
                status: sanitizeStatus(modalStatusField ? modalStatusField.value : defaultStatus)
            };

            if (mode !== 'edit') {
                base.id = 0;
                base.eventId = 0;
                base.source = 'manual';
            }

            var normalized = normalizeItem(base);
            if (!normalized) {
                setFormError(strings.validationRequired || 'Please provide both start and end dates.');
                return;
            }

            if (mode === 'edit' && index >= 0 && index < state.items.length) {
                state.items[index] = normalized;
            } else {
                state.items.push(normalized);
            }

            closeModal();
            clearFormError();
            clearAlert();
            sync();
        }

        function removeOccurrence(index) {
            if (index < 0 || index >= state.items.length) {
                return;
            }
            var confirmMessage = strings.removeConfirm || 'Remove this occurrence?';
            if (!window.confirm(confirmMessage)) {
                return;
            }
            state.items.splice(index, 1);
            clearAlert();
            sync();
        }

        var initialItems = safeParseJSON(payloadInput.value, null);
        if (!Array.isArray(initialItems) || !initialItems.length) {
            initialItems = Array.isArray(config.items) ? config.items : [];
        }
        state.items = initialItems.map(normalizeItem).filter(function (item) {
            return !!item;
        });
        sync();

        if (addButton) {
            addButton.addEventListener('click', function (event) {
                event.preventDefault();
                lastTrigger = addButton;
                openForm('create');
            });
        }

        if (duplicateButton) {
            duplicateButton.addEventListener('click', function (event) {
                event.preventDefault();
                lastTrigger = duplicateButton;
                if (!state.items.length) {
                    openForm('create');
                    return;
                }
                var template = safeClone(state.items[state.items.length - 1]);
                if (template) {
                    template.id = 0;
                    template.eventId = 0;
                    template.source = 'manual';
                }
                openForm('create', template || null);
                if (strings.duplicateHint) {
                    showAlert(strings.duplicateHint, 'info');
                }
            });
        }

        listContainer.addEventListener('click', function (event) {
            var actionElement = event.target.closest('[data-action]');
            if (!actionElement) {
                return;
            }
            var action = actionElement.getAttribute('data-action');
            if (!action) {
                return;
            }
            var row = actionElement.closest('tr');
            if (!row) {
                return;
            }
            var index = parseInt(row.getAttribute('data-index'), 10);
            if (isNaN(index) || index < 0 || index >= state.items.length) {
                return;
            }
            lastTrigger = actionElement;
            if (action === 'occurrence-edit') {
                openForm('edit', state.items[index], index);
            } else if (action === 'occurrence-remove') {
                removeOccurrence(index);
            }
        });
    }
})();
