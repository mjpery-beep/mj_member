(function () {
    'use strict';

    var Utils = window.MjMemberUtils || {};
    var domReady = typeof Utils.domReady === 'function'
        ? Utils.domReady
        : function (callback) {
            if (typeof callback !== 'function') {
                return;
            }
            if (typeof document === 'undefined') {
                callback();
                return;
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', callback);
            } else {
                callback();
            }
        };

    var settings = window.mjMemberRegistrationsWidget || {};
    var ajaxUrl = settings.ajaxUrl || '';
    var nonce = settings.nonce || '';
    var strings = settings.strings || {};
    var locale = typeof strings.locale === 'string' && strings.locale ? strings.locale.replace(/_/g, '-') : (navigator.language || 'fr-FR');

    var weekdayKeys = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
    var weekdayOrder = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    function normalizeOccurrenceValue(value) {
        if (typeof value === 'string') {
            return value.trim();
        }
        if (value === null || value === undefined) {
            return '';
        }
        return String(value).trim();
    }

    function parseOccurrenceDate(value) {
        if (!value) {
            return null;
        }
        var pattern = /^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?/;
        var match = String(value).match(pattern);
        if (!match) {
            return null;
        }
        var year = parseInt(match[1], 10);
        var month = parseInt(match[2], 10) - 1;
        var day = parseInt(match[3], 10);
        var hour = match[4] !== undefined ? parseInt(match[4], 10) : 0;
        var minute = match[5] !== undefined ? parseInt(match[5], 10) : 0;
        var second = match[6] !== undefined ? parseInt(match[6], 10) : 0;
        if (Number.isNaN(year) || Number.isNaN(month) || Number.isNaN(day)) {
            return null;
        }
        return new Date(year, month, day, hour, minute, second);
    }

    function formatWeekday(date) {
        if (!(date instanceof Date)) {
            return '';
        }
        try {
            var weekday = new Intl.DateTimeFormat(locale, { weekday: 'long' }).format(date);
            if (weekday.length === 0) {
                return '';
            }
            return weekday.charAt(0).toUpperCase() + weekday.slice(1);
        } catch (error) {
            var fallbackIndex = date.getDay();
            var fallbackNames = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
            return fallbackNames[fallbackIndex] || '';
        }
    }

    function formatTimeLabel(date) {
        if (!(date instanceof Date)) {
            return '';
        }
        var hours = date.getHours();
        var minutes = date.getMinutes();
        var prefix = hours.toString();
        if (minutes === 0) {
            return prefix + 'h';
        }
        return prefix + 'h' + String(minutes).padStart(2, '0');
    }

    function formatOccurrenceLabel(occurrence) {
        if (!occurrence) {
            return '';
        }
        if (occurrence.label) {
            return String(occurrence.label);
        }
        var startDate = parseOccurrenceDate(occurrence.start);
        var endDate = parseOccurrenceDate(occurrence.end);
        if (!startDate) {
            return occurrence.start ? String(occurrence.start) : '';
        }
        var weekday = formatWeekday(startDate);
        var startTime = formatTimeLabel(startDate);
        var endTime = endDate ? formatTimeLabel(endDate) : '';
        if (weekday && startTime && endTime) {
            return weekday + ' ' + startTime + ' → ' + endTime;
        }
        if (weekday && startTime) {
            return weekday + ' ' + startTime;
        }
        if (startTime && endTime) {
            return startTime + ' → ' + endTime;
        }
        return weekday || startTime || String(occurrence.start || '');
    }

    function buildSelectionLookup(values) {
        var lookup = Object.create(null);
        if (!Array.isArray(values)) {
            return lookup;
        }
        for (var i = 0; i < values.length; i += 1) {
            var normalized = normalizeOccurrenceValue(values[i]);
            if (normalized) {
                lookup[normalized] = true;
            }
        }
        return lookup;
    }

    function computeSelectedSet(config) {
        var selectedValues = [];
        if (config.occurrenceScope === 'custom' && Array.isArray(config.occurrences) && config.occurrences.length) {
            for (var i = 0; i < config.occurrences.length; i += 1) {
                var normalized = normalizeOccurrenceValue(config.occurrences[i].start);
                if (normalized) {
                    selectedValues.push(normalized);
                }
            }
        } else if (Array.isArray(config.availableOccurrences)) {
            for (var j = 0; j < config.availableOccurrences.length; j += 1) {
                var value = normalizeOccurrenceValue(config.availableOccurrences[j].start);
                if (value) {
                    selectedValues.push(value);
                }
            }
        }
        var lookup = buildSelectionLookup(selectedValues);
        return { values: selectedValues, lookup: lookup };
    }

    function computeCalendarSets(occurrences) {
        var set = Object.create(null);
        if (!Array.isArray(occurrences)) {
            return set;
        }
        for (var i = 0; i < occurrences.length; i += 1) {
            var entry = occurrences[i];
            if (!entry) {
                continue;
            }
            var date = parseOccurrenceDate(entry.start);
            if (!date) {
                continue;
            }
            var weekdayIndex = date.getDay();
            var key = weekdayKeys[weekdayIndex];
            if (key) {
                set[key] = true;
            }
        }
        return set;
    }

    function computeCalendarSlots(config, selectedLookup) {
        var availableSet = computeCalendarSets(config.availableOccurrences);
        var selectedEntries = [];
        if (selectedLookup && typeof selectedLookup === 'object') {
            var selectedKeys = Object.keys(selectedLookup);
            for (var idx = 0; idx < selectedKeys.length; idx += 1) {
                selectedEntries.push({ start: selectedKeys[idx] });
            }
        }
        var selectedSet = computeCalendarSets(selectedEntries);
        var slots = [];
        var hasAvailable = false;
        for (var i = 0; i < weekdayOrder.length; i += 1) {
            var key = weekdayOrder[i];
            var isAvailable = !!availableSet[key];
            var isSelected = !!selectedSet[key];
            if (isAvailable) {
                hasAvailable = true;
            }
            slots.push({
                key: key,
                available: isAvailable,
                selected: isSelected,
            });
        }
        if (!hasAvailable) {
            return [];
        }
        return slots;
    }

    function applyCalendarState(container, slots) {
        if (!container || !slots.length) {
            return;
        }
        for (var i = 0; i < slots.length; i += 1) {
            var slot = slots[i];
            var selector = '[data-weekday="' + slot.key + '"]';
            var cell = container.querySelector(selector);
            if (!cell) {
                continue;
            }
            cell.classList.toggle('is-available', slot.available);
            cell.classList.toggle('is-selected', slot.selected);
            var stateLabel = strings.calendarStateNone || 'Aucune séance prévue';
            if (slot.selected) {
                stateLabel = strings.calendarStateSelected || 'Séance sélectionnée';
            } else if (slot.available) {
                stateLabel = strings.calendarStateAvailable || 'Séance possible';
            }
            var longLabel = cell.getAttribute('data-weekday-label') || '';
            if (!longLabel) {
                switch (slot.key) {
                    case 'monday':
                        longLabel = 'Lundi';
                        break;
                    case 'tuesday':
                        longLabel = 'Mardi';
                        break;
                    case 'wednesday':
                        longLabel = 'Mercredi';
                        break;
                    case 'thursday':
                        longLabel = 'Jeudi';
                        break;
                    case 'friday':
                        longLabel = 'Vendredi';
                        break;
                    case 'saturday':
                        longLabel = 'Samedi';
                        break;
                    case 'sunday':
                        longLabel = 'Dimanche';
                        break;
                    default:
                        longLabel = strings.calendarDay || 'Jour de séance';
                }
            }
            cell.setAttribute('aria-label', longLabel + ' — ' + stateLabel);
        }
    }

    function stringify(value) {
        if (value === undefined || value === null) {
            return '';
        }
        if (typeof value === 'string') {
            return value;
        }
        return String(value);
    }

    function parseConfig(node) {
        if (!node) {
            return null;
        }
        var raw = node.getAttribute('data-config') || '';
        if (!raw) {
            return null;
        }
        try {
            return JSON.parse(raw);
        } catch (error) {
            return null;
        }
    }

    function buildOccurrenceItem(occurrence, selectedLookup) {
        var wrapper = document.createElement('label');
        wrapper.className = 'mj-member-registrations__manager-option';
        var checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.value = stringify(occurrence.start);
        checkbox.className = 'mj-member-registrations__manager-checkbox';
        var normalized = normalizeOccurrenceValue(occurrence.start);
        checkbox.checked = !!(normalized && selectedLookup[normalized]);
        var border = document.createElement('span');
        border.className = 'mj-member-registrations__manager-checkmark';
        var label = document.createElement('span');
        label.className = 'mj-member-registrations__manager-label';
        label.textContent = formatOccurrenceLabel(occurrence);
        var date = parseOccurrenceDate(occurrence.start);
        if (date && date.getTime() < Date.now()) {
            checkbox.disabled = true;
            wrapper.classList.add('is-past');
        }
        wrapper.appendChild(checkbox);
        wrapper.appendChild(border);
        wrapper.appendChild(label);
        return { element: wrapper, checkbox: checkbox, normalized: normalized };
    }

    function updateAgendaDisplay(scope, occurrences, elements, availableCount) {
        if (!elements || !elements.agenda) {
            return;
        }
        var agendaTitle = strings.agendaTitleDefault || 'Agenda';
        if (scope === 'custom') {
            agendaTitle = strings.agendaTitleCustom || 'Jours sélectionnés';
        } else if (scope === 'all') {
            agendaTitle = strings.agendaTitleAll || 'Occurrences de l’événement';
        }
        if (elements.title) {
            elements.title.textContent = agendaTitle;
        }

        var totalSelected = occurrences.length;
        var displayLimit = 8;
        if (elements.list) {
            elements.list.innerHTML = '';
        }
        if (elements.empty) {
            elements.empty.hidden = true;
            elements.empty.textContent = '';
        }
        if (totalSelected > 0 && elements.list) {
            var displayCount = Math.min(displayLimit, totalSelected);
            for (var i = 0; i < displayCount; i += 1) {
                var entry = occurrences[i];
                var item = document.createElement('li');
                item.className = 'mj-member-registrations__agenda-item';
                item.textContent = formatOccurrenceLabel(entry);
                elements.list.appendChild(item);
            }
            if (elements.list) {
                elements.list.hidden = false;
            }
            if (elements.empty) {
                elements.empty.hidden = true;
            }
            if (elements.more) {
                var remaining = totalSelected - displayLimit;
                if (remaining < 0) {
                    remaining = 0;
                }
                if (remaining > 0) {
                    var template = remaining === 1
                        ? (strings.moreOne || '+ %d autre occurrence')
                        : (strings.moreMany || '+ %d autres occurrences');
                    elements.more.textContent = template.replace('%d', remaining);
                    elements.more.hidden = false;
                } else {
                    elements.more.hidden = true;
                    elements.more.textContent = '';
                }
            }
        } else {
            if (elements.list) {
                elements.list.hidden = true;
            }
            if (elements.more) {
                elements.more.hidden = true;
                elements.more.textContent = '';
            }
            if (elements.empty) {
                var message = strings.agendaEmptyUnknown || 'Agenda à confirmer.';
                if (scope === 'all') {
                    message = strings.agendaEmptyAll || 'Inscription valable pour toutes les occurrences de l’événement.';
                } else if (scope === 'custom') {
                    message = strings.agendaEmptyCustom || 'Aucune occurrence sélectionnée pour cette inscription.';
                }
                elements.empty.textContent = message;
                elements.empty.hidden = false;
            }
        }

        if (elements.count) {
            var scopeCount = scope === 'all' ? availableCount : totalSelected;
            if (scopeCount > totalSelected) {
                totalSelected = scopeCount;
            }
            var displayCount = Math.min(displayLimit, totalSelected);
            var remaining = scopeCount - displayCount;
            if (remaining > 0) {
                var templateRemaining = remaining === 1
                    ? (strings.moreOne || '+ %d autre occurrence')
                    : (strings.moreMany || '+ %d autres occurrences');
                elements.count.textContent = templateRemaining.replace('%d', remaining);
                elements.count.hidden = false;
            } else {
                elements.count.hidden = true;
                elements.count.textContent = '';
            }
        }
    }

    function compareSelection(base, next) {
        if (base.length !== next.length) {
            return false;
        }
        var lookup = Object.create(null);
        for (var i = 0; i < base.length; i += 1) {
            lookup[base[i]] = true;
        }
        for (var j = 0; j < next.length; j += 1) {
            if (!lookup[next[j]]) {
                return false;
            }
        }
        return true;
    }

    function showFeedback(target, message, isError) {
        if (!target) {
            return;
        }
        target.textContent = message || '';
        target.classList.toggle('is-error', !!isError);
    }

    function syncPanelHelp(state) {
        if (!state || !state.panelHelp) {
            return;
        }
        if (state.config.occurrenceScope === 'all') {
            state.panelHelp.textContent = strings.panelHelpAll || 'Toutes les occurrences sont actuellement incluses.';
        } else {
            state.panelHelp.textContent = strings.panelHelpCustom || 'Sélectionne les séances auxquelles tu participes.';
        }
    }

    function resolveOccurrencesDetails(config, selectedValues, scopeOverride) {
        var scope = scopeOverride || config.occurrenceScope || 'custom';
        if (scope === 'all') {
            return Array.isArray(config.availableOccurrences)
                ? config.availableOccurrences.slice()
                : [];
        }

        var lookup = buildSelectionLookup(selectedValues);
        var details = [];
        if (Array.isArray(config.availableOccurrences)) {
            for (var j = 0; j < config.availableOccurrences.length; j += 1) {
                var occurrence = config.availableOccurrences[j];
                var normalized = normalizeOccurrenceValue(occurrence.start);
                if (normalized && lookup[normalized]) {
                    details.push(occurrence);
                    delete lookup[normalized];
                }
            }
        }

        var remainingKeys = Object.keys(lookup);
        for (var k = 0; k < remainingKeys.length; k += 1) {
            details.push({ start: remainingKeys[k], end: '', label: remainingKeys[k] });
        }

        return details;
    }

    function handleSubmit(state) {
        if (!state || state.submitting) {
            return;
        }
        var selected = Array.from(state.pendingSelection.values());
        if (!selected.length) {
            showFeedback(state.feedback, strings.selectionRequired || 'Merci de sélectionner au moins une occurrence.', true);
            return;
        }
        if (!ajaxUrl || !nonce) {
            showFeedback(state.feedback, strings.error || 'Une erreur est survenue. Merci de réessayer.', true);
            return;
        }
        if (compareSelection(state.currentSelection, selected)) {
            showFeedback(state.feedback, strings.noChange || 'Aucun changement détecté.', false);
            return;
        }

        state.submitting = true;
        state.form.setAttribute('data-submitting', '1');
        if (state.submit) {
            state.submit.disabled = true;
            state.submit.textContent = strings.loading || 'En cours...';
        }
        if (state.cancel) {
            state.cancel.disabled = true;
        }
        showFeedback(state.feedback, '', false);

        var payload = new window.FormData();
        payload.append('action', 'mj_member_update_event_assignments');
        payload.append('nonce', nonce);
        payload.append('event_id', stringify(state.config.eventId));
        payload.append('member_id', stringify(state.config.memberId));
        if (state.config.registrationId) {
            payload.append('registration_id', stringify(state.config.registrationId));
        }
        try {
            payload.append('occurrences', JSON.stringify(selected));
        } catch (error) {
            payload.append('occurrences', '[]');
        }

        window.fetch(ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: payload,
        }).then(function (response) {
            return response.json().then(function (json) {
                return { ok: response.ok, json: json };
            }).catch(function () {
                return { ok: response.ok, json: null };
            });
        }).then(function (result) {
            if (!result.ok || !result.json) {
                throw new Error(strings.error || 'Une erreur est survenue. Merci de réessayer.');
            }
            if (!result.json.success) {
                var messageFail = strings.error || 'Une erreur est survenue. Merci de réessayer.';
                if (result.json.data && result.json.data.message) {
                    messageFail = String(result.json.data.message);
                }
                throw new Error(messageFail);
            }
            var assignments = result.json.data && result.json.data.assignments ? result.json.data.assignments : null;
            var scope = assignments && assignments.mode === 'custom' && Array.isArray(assignments.occurrences) && assignments.occurrences.length ? 'custom' : 'all';
            var normalizedSelection = [];
            if (scope === 'custom' && assignments && Array.isArray(assignments.occurrences)) {
                for (var i = 0; i < assignments.occurrences.length; i += 1) {
                    var normalized = normalizeOccurrenceValue(assignments.occurrences[i]);
                    if (normalized) {
                        normalizedSelection.push(normalized);
                    }
                }
            } else {
                normalizedSelection = selected.slice();
            }
            var details = resolveOccurrencesDetails(state.config, normalizedSelection, scope);
            state.currentSelection = normalizedSelection;
            state.config.occurrenceScope = scope;
            state.config.occurrences = details;
            var availableTotal = Array.isArray(state.config.availableOccurrences) ? state.config.availableOccurrences.length : details.length;
            state.config.occurrenceCount = scope === 'all' ? availableTotal : details.length;
            state.pendingSelection = new Set(normalizedSelection);
            state.pendingSelectionLookup = buildSelectionLookup(normalizedSelection);
            refreshState(state, true);
            showFeedback(state.feedback, strings.success || 'Occurrences mises à jour.', false);
            togglePanel(state, false);
        }).catch(function (error) {
            var message = error && error.message ? String(error.message) : (strings.error || 'Une erreur est survenue. Merci de réessayer.');
            showFeedback(state.feedback, message, true);
        }).finally(function () {
            state.submitting = false;
            state.form.setAttribute('data-submitting', '0');
            if (state.submit) {
                state.submit.disabled = false;
                state.submit.textContent = strings.submitLabel || 'Enregistrer';
            }
            if (state.cancel) {
                state.cancel.disabled = false;
            }
        });
    }

    function refreshState(state, updateAgenda) {
        if (!state) {
            return;
        }
        if (state.list) {
            var checkboxes = state.list.querySelectorAll('.mj-member-registrations__manager-checkbox');
            checkboxes.forEach(function (checkbox) {
                var normalized = normalizeOccurrenceValue(checkbox.value);
                checkbox.checked = state.pendingSelection.has(normalized);
            });
        }
        if (state.submit) {
            state.submit.disabled = state.pendingSelection.size === 0 || state.submitting;
        }
        if (updateAgenda) {
            var lookup = buildSelectionLookup(state.currentSelection);
            if (state.calendarGrid) {
                var calendarSlots = computeCalendarSlots(state.config, lookup);
                applyCalendarState(state.calendarGrid, calendarSlots);
            }
            var details = resolveOccurrencesDetails(state.config, state.currentSelection);
            var availableCount = Array.isArray(state.config.availableOccurrences) ? state.config.availableOccurrences.length : 0;
            updateAgendaDisplay(state.config.occurrenceScope, details, state.agendaElements, availableCount);
            syncPanelHelp(state);
        }
    }

    function togglePanel(state, open) {
        if (!state || !state.panel) {
            return;
        }
        var shouldOpen = open;
        if (typeof shouldOpen !== 'boolean') {
            shouldOpen = state.panel.hidden;
        }
        state.panel.hidden = !shouldOpen;
        if (state.manageButton) {
            state.manageButton.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
        }
        state.root.setAttribute('data-manager-open', shouldOpen ? '1' : '0');
        if (!shouldOpen) {
            state.pendingSelection = new Set(state.currentSelection);
            state.pendingSelectionLookup = buildSelectionLookup(state.currentSelection);
            refreshState(state, false);
            showFeedback(state.feedback, '', false);
            if (state.manageButton) {
                state.manageButton.focus();
            }
        } else if (state.panel) {
            state.panel.focus();
        }
    }

    function buildManager(root, config) {
        var form = root.querySelector('[data-mj-registrations-form]');
        if (!form) {
            return null;
        }
        var list = form.querySelector('[data-mj-registrations-list]');
        if (!list) {
            return null;
        }
        var manageButton = root.querySelector('[data-mj-registrations-manage]');
        if (!manageButton) {
            return null;
        }
        var panel = root.querySelector('[data-mj-registrations-panel]');
        if (!panel) {
            return null;
        }
        var feedback = form.querySelector('[data-mj-registrations-feedback]');
        var submit = form.querySelector('[data-mj-registrations-submit]');
        var cancel = form.querySelector('[data-mj-registrations-cancel]');
        var panelTitle = panel.querySelector('[data-mj-registrations-panel-title]');
        var panelHelp = panel.querySelector('[data-mj-registrations-panel-help]');

        if (panelTitle) {
            panelTitle.textContent = strings.panelTitle || 'Choisis tes séances';
        }

        var selected = computeSelectedSet(config);
        var pending = new Set(selected.values);
        var elements = [];
        list.innerHTML = '';
        if (Array.isArray(config.availableOccurrences)) {
            for (var i = 0; i < config.availableOccurrences.length; i += 1) {
                var occurrence = config.availableOccurrences[i];
                var item = buildOccurrenceItem(occurrence, selected.lookup);
                list.appendChild(item.element);
                elements.push(item);
                item.checkbox.addEventListener('change', function (event) {
                    var checkbox = event.currentTarget;
                    var normalized = normalizeOccurrenceValue(checkbox.value);
                    if (!normalized) {
                        return;
                    }
                    if (checkbox.checked) {
                        pending.add(normalized);
                    } else {
                        pending.delete(normalized);
                    }
                    state.pendingSelection = new Set(pending);
                    state.pendingSelectionLookup = buildSelectionLookup(Array.from(state.pendingSelection));
                    refreshState(state, false);
                });
            }
        }

        var itemRoot = root.closest('[data-mj-registrations-item]');
        var agendaElements = {
            agenda: itemRoot ? itemRoot.querySelector('[data-mj-registrations-agenda]') : null,
            title: itemRoot ? itemRoot.querySelector('[data-mj-registrations-agenda-title]') : null,
            list: itemRoot ? itemRoot.querySelector('[data-mj-registrations-agenda-list]') : null,
            empty: itemRoot ? itemRoot.querySelector('[data-mj-registrations-agenda-empty]') : null,
            more: itemRoot ? itemRoot.querySelector('[data-mj-registrations-agenda-more]') : null,
        };
        var calendarGrid = itemRoot ? itemRoot.querySelector('.mj-member-registrations__calendar-grid') : null;

        var state = {
            root: root,
            form: form,
            panel: panel,
            panelHelp: panelHelp,
            list: list,
            feedback: feedback,
            submit: submit,
            cancel: cancel,
            manageButton: manageButton,
            config: config,
            currentSelection: selected.values,
            pendingSelection: pending,
            pendingSelectionLookup: selected.lookup,
            agendaElements: agendaElements,
            calendarGrid: calendarGrid,
            submitting: false,
        };

        manageButton.addEventListener('click', function () {
            togglePanel(state, true);
        });
        manageButton.setAttribute('aria-expanded', 'false');
        if (!manageButton.getAttribute('aria-controls')) {
            var panelId = panel.id || ('mj-member-registrations-panel-' + Math.floor(Math.random() * 100000));
            panel.id = panelId;
            manageButton.setAttribute('aria-controls', panelId);
        }
        if (cancel) {
            cancel.addEventListener('click', function (event) {
                event.preventDefault();
                togglePanel(state, false);
            });
        }
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            handleSubmit(state);
        });

        syncPanelHelp(state);
        refreshState(state, true);
        return state;
    }

    domReady(function () {
        var nodes = document.querySelectorAll('[data-mj-registrations-entry]');
        nodes.forEach(function (node) {
            var config = parseConfig(node);
            if (!config || !config.canManageOccurrences) {
                return;
            }
            if (!Array.isArray(config.availableOccurrences) || !config.availableOccurrences.length) {
                return;
            }
            if (!config.memberId || !config.eventId) {
                return;
            }
            var state = buildManager(node, config);
            if (!state) {
                return;
            }
            node.dataset.initialized = '1';
        });
    });
})();
